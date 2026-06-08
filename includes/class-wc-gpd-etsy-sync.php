<?php
/**
 * Sync Etsy orders into WooCommerce production jobs.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Etsy order import.
 */
class WC_GPD_Etsy_Sync implements WC_GPD_Module {

	const CRON_HOOK = 'wc_gpd_etsy_sync_orders';
	const OPTION_IMPORTED = 'wc_gpd_etsy_imported_receipts';

	/**
	 * @var WC_GPD_Etsy_Sync|null
	 */
	private static $instance = null;

	/**
	 * @return WC_GPD_Etsy_Sync
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_sync' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
	}

	/**
	 * Schedule hourly sync when configured.
	 */
	public function maybe_schedule_cron() {
		if ( ! WC_GPD_Etsy_Client::is_configured() ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Cron entry point.
	 */
	public function run_scheduled_sync() {
		self::sync_recent_receipts();
	}

	/**
	 * @param int $limit Receipt limit.
	 * @return array{imported:int,skipped:int,errors:array}
	 */
	public static function sync_recent_receipts( $limit = 25 ) {
		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		if ( ! WC_GPD_Etsy_Client::is_configured() ) {
			$result['errors'][] = __( 'Etsy is not configured.', 'wc-generic-product-designer' );
			return $result;
		}

		$response = WC_GPD_Etsy_Client::get_shop_receipts( $limit );
		if ( is_wp_error( $response ) ) {
			$result['errors'][] = $response->get_error_message();
			return $result;
		}

		$receipts  = ! empty( $response['results'] ) ? $response['results'] : array();
		$imported  = get_option( self::OPTION_IMPORTED, array() );
		if ( ! is_array( $imported ) ) {
			$imported = array();
		}

		foreach ( $receipts as $receipt ) {
			if ( empty( $receipt['receipt_id'] ) ) {
				continue;
			}
			$receipt_id = (string) $receipt['receipt_id'];
			if ( in_array( $receipt_id, $imported, true ) ) {
				++$result['skipped'];
				continue;
			}

			$import_result = self::import_receipt( $receipt );
			if ( is_wp_error( $import_result ) ) {
				$result['errors'][] = $import_result->get_error_message();
				continue;
			}

			$imported[] = $receipt_id;
			++$result['imported'];
		}

		update_option( self::OPTION_IMPORTED, array_values( array_unique( $imported ) ), false );

		return $result;
	}

	/**
	 * @param array $receipt Etsy receipt payload.
	 * @return int|WP_Error Order ID.
	 */
	public static function import_receipt( array $receipt ) {
		if ( empty( $receipt['transactions'] ) || ! is_array( $receipt['transactions'] ) ) {
			return new WP_Error( 'wc_gpd_etsy_empty', __( 'Receipt has no transactions.', 'wc-generic-product-designer' ) );
		}

		$order = wc_create_order();
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$order->set_billing_first_name( ! empty( $receipt['name'] ) ? sanitize_text_field( (string) $receipt['name'] ) : 'Etsy' );
		$order->set_status( 'processing' );
		$order->add_order_note( __( 'Imported from Etsy for production review.', 'wc-generic-product-designer' ) );

		$has_design = false;

		foreach ( $receipt['transactions'] as $transaction ) {
			$listing_id = ! empty( $transaction['listing_id'] ) ? (string) $transaction['listing_id'] : '';
			$map        = $listing_id ? WC_GPD_Etsy_Client::get_map_for_listing( $listing_id ) : null;
			if ( ! $map || empty( $map['product_id'] ) ) {
				continue;
			}

			$product_id = absint( $map['product_id'] );
			$product    = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$personalization = '';
			if ( ! empty( $transaction['variations'] ) && is_array( $transaction['variations'] ) ) {
				$chunks = array();
				foreach ( $transaction['variations'] as $variation ) {
					if ( ! empty( $variation['formatted_name'] ) && ! empty( $variation['formatted_value'] ) ) {
						$chunks[] = $variation['formatted_name'] . ': ' . $variation['formatted_value'];
					}
				}
				$personalization = implode( "\n", $chunks );
			}

			$rules  = ! empty( $map['rules'] ) && is_array( $map['rules'] ) ? $map['rules'] : array();
			$fields = WC_GPD_Personalization_Parser::parse_text( $personalization, $rules );
			$built  = WC_GPD_Personalization_Parser::build_design_for_product( $product_id, $fields, $rules );

			if ( is_wp_error( $built ) ) {
				continue;
			}

			$item_id = $order->add_product( $product, 1 );
			$item    = $order->get_item( $item_id );
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$svg = WC_GPD_SVG_Sanitizer::sanitize( $built['svg'] );
			if ( $svg ) {
				$item->add_meta_data( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, $svg, true );
				$item->add_meta_data( WC_GPD_Product_Meta::ORDER_META_DESIGN_JSON, $built['json'], true );
				$item->add_meta_data( '_wc_gpd_has_design', 'yes', true );
				$item->add_meta_data( WC_GPD_Production_Jobs::META_STATUS, WC_GPD_Production_Jobs::STATUS_PENDING, true );
				$item->add_meta_data( WC_GPD_Production_Jobs::META_SOURCE, WC_GPD_Production_Jobs::SOURCE_ETSY, true );
				$item->add_meta_data( WC_GPD_Production_Jobs::META_ETSY_ORDER, (string) ( $receipt['receipt_id'] ?? '' ), true );
				$item->add_meta_data( WC_GPD_Production_Jobs::META_ETSY_LISTING, $listing_id, true );
				$item->add_meta_data( WC_GPD_Production_Jobs::META_PARSED_FIELDS, wp_json_encode( $built['fields'] ), true );
				$item->save();

				$preview = WC_GPD_Preview_Storage::save_design_preview( $svg, WC_GPD_Product_Meta::get_settings( $product_id ), $product_id );
				if ( ! empty( $preview['url'] ) ) {
					$item->update_meta_data( WC_GPD_Product_Meta::ORDER_META_PREVIEW_URL, esc_url_raw( $preview['url'] ) );
					$item->save();
				}

				$has_design = true;
			}
		}

		if ( ! $has_design ) {
			$order->delete( true );
			return new WP_Error( 'wc_gpd_etsy_no_design', __( 'No mapped listings with valid personalization were found on this receipt.', 'wc-generic-product-designer' ) );
		}

		$order->save();
		return $order->get_id();
	}
}
