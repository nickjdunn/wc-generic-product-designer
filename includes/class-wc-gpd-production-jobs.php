<?php
/**
 * Production job workflow for custom design order line items.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query and manage per-line-item production status.
 */
class WC_GPD_Production_Jobs {

	const META_STATUS       = '_wc_gpd_prod_status';
	const META_NOTES        = '_wc_gpd_prod_notes';
	const META_BATCH_ID     = '_wc_gpd_prod_batch_id';
	const META_EXPORTED_AT  = '_wc_gpd_prod_exported_at';
	const META_SOURCE       = '_wc_gpd_prod_source';
	const META_ETSY_ORDER   = '_wc_gpd_etsy_order_id';
	const META_ETSY_LISTING = '_wc_gpd_etsy_listing_id';
	const META_PROOF_SENT   = '_wc_gpd_proof_sent_at';
	const META_PARSED_FIELDS = '_wc_gpd_parsed_personalization';

	const STATUS_PENDING         = 'pending';
	const STATUS_PROOF_SENT      = 'proof_sent';
	const STATUS_PROOF_APPROVED  = 'proof_approved';
	const STATUS_READY           = 'ready';
	const STATUS_IN_BATCH        = 'in_batch';
	const STATUS_EXPORTED        = 'exported';

	const SOURCE_WOOCOMMERCE = 'woocommerce';
	const SOURCE_ETSY        = 'etsy';

	/**
	 * @return string[]
	 */
	public static function statuses() {
		return array(
			self::STATUS_PENDING,
			self::STATUS_PROOF_SENT,
			self::STATUS_PROOF_APPROVED,
			self::STATUS_READY,
			self::STATUS_IN_BATCH,
			self::STATUS_EXPORTED,
		);
	}

	/**
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			self::STATUS_PENDING        => __( 'Pending', 'wc-generic-product-designer' ),
			self::STATUS_PROOF_SENT     => __( 'Proof sent', 'wc-generic-product-designer' ),
			self::STATUS_PROOF_APPROVED => __( 'Proof approved', 'wc-generic-product-designer' ),
			self::STATUS_READY          => __( 'Ready', 'wc-generic-product-designer' ),
			self::STATUS_IN_BATCH       => __( 'In batch', 'wc-generic-product-designer' ),
			self::STATUS_EXPORTED       => __( 'Exported', 'wc-generic-product-designer' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * @param WC_Order_Item_Product $item Line item.
	 * @return bool
	 */
	public static function item_has_design( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return false;
		}
		$svg = WC_GPD_SVG_Sanitizer::sanitize( $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, true ) );
		return (bool) $svg;
	}

	/**
	 * @param WC_Order_Item_Product $item Line item.
	 * @return string
	 */
	public static function get_status( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return self::STATUS_PENDING;
		}
		$status = sanitize_key( (string) $item->get_meta( self::META_STATUS, true ) );
		return in_array( $status, self::statuses(), true ) ? $status : self::STATUS_PENDING;
	}

	/**
	 * @param WC_Order_Item_Product $item   Line item.
	 * @param string                $status New status.
	 * @param WC_Order|null         $order  Order for notes.
	 * @return bool
	 */
	public static function set_status( $item, $status, $order = null ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return false;
		}
		$status = sanitize_key( $status );
		if ( ! in_array( $status, self::statuses(), true ) ) {
			return false;
		}

		$previous = self::get_status( $item );
		$item->update_meta_data( self::META_STATUS, $status );

		if ( self::STATUS_EXPORTED === $status ) {
			$item->update_meta_data( self::META_EXPORTED_AT, (string) time() );
		}

		if ( self::STATUS_PROOF_SENT === $status ) {
			$item->update_meta_data( self::META_PROOF_SENT, (string) time() );
		}

		if ( in_array( $status, array( self::STATUS_PENDING, self::STATUS_READY, self::STATUS_PROOF_APPROVED, self::STATUS_PROOF_SENT ), true ) ) {
			$item->delete_meta_data( self::META_BATCH_ID );
		}

		$item->save();

		if ( $order instanceof WC_Order ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: item name, 2: previous status, 3: new status */
					__( 'GPD production: %1$s status changed from %2$s to %3$s.', 'wc-generic-product-designer' ),
					$item->get_name(),
					self::status_label( $previous ),
					self::status_label( $status )
				)
			);
			$order->save();
		}

		return true;
	}

	/**
	 * Ensure new designs start as pending.
	 *
	 * @param WC_Order_Item_Product $item Line item.
	 */
	public static function maybe_init_status( $item ) {
		if ( ! self::item_has_design( $item ) ) {
			return;
		}
		$existing = $item->get_meta( self::META_STATUS, true );
		if ( $existing ) {
			return;
		}
		$item->add_meta_data( self::META_STATUS, self::STATUS_PENDING, true );
		$item->add_meta_data( self::META_SOURCE, self::SOURCE_WOOCOMMERCE, true );
	}

	/**
	 * Query production jobs across orders.
	 *
	 * @param array $args Query args.
	 * @return array{items:array<int,array>,total:int,page:int,pages:int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'      => '',
			'product_id'  => 0,
			'template_id' => 0,
			'order_id'    => 0,
			'source'      => '',
			'search'      => '',
			'date_after'  => '',
			'date_before' => '',
			'per_page'    => 20,
			'page'        => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$per_page = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$page     = max( 1, absint( $args['page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		$oi_table  = $wpdb->prefix . 'woocommerce_order_items';
		$oim_table = $wpdb->prefix . 'woocommerce_order_itemmeta';

		$joins  = array();
		$wheres = array(
			"oi.order_item_type = 'line_item'",
			"has_design.meta_key = '_wc_gpd_has_design'",
			"has_design.meta_value = 'yes'",
		);

		$joins[] = "INNER JOIN {$oim_table} has_design ON has_design.order_item_id = oi.order_item_id";

		$filter_status = $args['status'] ? sanitize_key( $args['status'] ) : '';

		if ( $args['order_id'] ) {
			$wheres[] = $wpdb->prepare( 'oi.order_id = %d', absint( $args['order_id'] ) );
		}

		if ( $args['product_id'] ) {
			$joins[] = $wpdb->prepare(
				"INNER JOIN {$oim_table} product_meta ON product_meta.order_item_id = oi.order_item_id AND product_meta.meta_key = '_product_id' AND product_meta.meta_value = %d",
				absint( $args['product_id'] )
			);
		}

		if ( $args['source'] ) {
			$joins[] = $wpdb->prepare(
				"INNER JOIN {$oim_table} source_meta ON source_meta.order_item_id = oi.order_item_id AND source_meta.meta_key = %s AND source_meta.meta_value = %s",
				self::META_SOURCE,
				sanitize_key( $args['source'] )
			);
		}

		$join_sql  = implode( "\n", array_unique( $joins ) );
		$where_sql = implode( ' AND ', $wheres );

		$count_sql = "SELECT COUNT(DISTINCT oi.order_item_id) FROM {$oi_table} oi {$join_sql} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$list_sql = $wpdb->prepare(
			"SELECT DISTINCT oi.order_item_id, oi.order_id FROM {$oi_table} oi {$join_sql} WHERE {$where_sql} ORDER BY oi.order_item_id DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		$rows  = $wpdb->get_results( $list_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = array();

		foreach ( $rows as $row ) {
			$order = wc_get_order( (int) $row->order_id );
			if ( ! $order ) {
				continue;
			}
			$item = $order->get_item( (int) $row->order_item_id );
			if ( ! $item instanceof WC_Order_Item_Product || ! self::item_has_design( $item ) ) {
				continue;
			}

			$job = self::format_job_row( $order, $item );

			if ( $args['template_id'] && absint( $args['template_id'] ) !== absint( $job['template_id'] ) ) {
				continue;
			}

			if ( $args['search'] ) {
				$needle = strtolower( $args['search'] );
				$hay    = strtolower(
					$job['order_number'] . ' ' . $job['customer_name'] . ' ' . $job['product_name']
				);
				if ( false === strpos( $hay, $needle ) ) {
					continue;
				}
			}

			if ( $args['date_after'] || $args['date_before'] ) {
				$order_date = $order->get_date_created();
				if ( $order_date ) {
					$ts = $order_date->getTimestamp();
					if ( $args['date_after'] && $ts < strtotime( $args['date_after'] . ' 00:00:00' ) ) {
						continue;
					}
					if ( $args['date_before'] && $ts > strtotime( $args['date_before'] . ' 23:59:59' ) ) {
						continue;
					}
				}
			}

			if ( $filter_status && $filter_status !== $job['status'] ) {
				continue;
			}

			$items[] = $job;
		}

		return array(
			'items' => $items,
			'total' => $total,
			'page'  => $page,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item  Item.
	 * @return array
	 */
	public static function format_job_row( $order, $item ) {
		$product_id   = $item->get_product_id();
		$settings     = $product_id ? WC_GPD_Product_Meta::get_settings( $product_id ) : array();
		$template_id  = ! empty( $settings['template_ref'] ) ? absint( $settings['template_ref'] ) : 0;
		$template     = $template_id ? WC_GPD_Design_Template::get_settings( $template_id ) : null;
		$preview_url  = WC_GPD_Preview::preview_url_from_order_item( $item );
		$edit_url     = WC_GPD_Admin_Order::get_edit_design_url( $order->get_id(), $item->get_id(), $item );

		return array(
			'order_id'       => $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'order_date'     => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
			'order_edit_url' => $order->get_edit_order_url(),
			'item_id'        => $item->get_id(),
			'product_id'     => $product_id,
			'product_name'   => $item->get_name(),
			'customer_name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'preview_url'    => $preview_url,
			'edit_url'       => $edit_url,
			'template_id'    => $template_id,
			'template_name'  => $template ? $template['title'] : '',
			'status'         => self::get_status( $item ),
			'status_label'   => self::status_label( self::get_status( $item ) ),
			'batch_id'       => absint( $item->get_meta( self::META_BATCH_ID, true ) ),
			'source'         => sanitize_key( (string) $item->get_meta( self::META_SOURCE, true ) ) ?: self::SOURCE_WOOCOMMERCE,
			'notes'          => (string) $item->get_meta( self::META_NOTES, true ),
			'etsy_order_id'  => (string) $item->get_meta( self::META_ETSY_ORDER, true ),
		);
	}

	/**
	 * @param int   $order_id Order ID.
	 * @param int   $item_id  Item ID.
	 * @return WC_Order_Item_Product|null
	 */
	public static function get_item( $order_id, $item_id ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order ) {
			return null;
		}
		$item = $order->get_item( absint( $item_id ) );
		return ( $item instanceof WC_Order_Item_Product && self::item_has_design( $item ) ) ? $item : null;
	}

	/**
	 * Bulk status update.
	 *
	 * @param array  $refs   Array of {order_id, item_id}.
	 * @param string $status Status.
	 * @return int Updated count.
	 */
	public static function bulk_set_status( array $refs, $status ) {
		$count = 0;
		foreach ( $refs as $ref ) {
			if ( empty( $ref['order_id'] ) || empty( $ref['item_id'] ) ) {
				continue;
			}
			$order = wc_get_order( absint( $ref['order_id'] ) );
			$item  = $order ? $order->get_item( absint( $ref['item_id'] ) ) : null;
			if ( $item && self::set_status( $item, $status, $order ) ) {
				++$count;
			}
		}
		return $count;
	}
}
