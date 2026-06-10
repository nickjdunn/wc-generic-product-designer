<?php
/**
 * Admin order: design preview, edit, and configurable downloads.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order admin export panel.
 */
class WC_GPD_Admin_Order implements WC_GPD_Module {

	/**
	 * @var WC_GPD_Admin_Order|null
	 */
	private static $instance = null;

	const DOWNLOAD_ACTION     = 'wc_gpd_download_design';
	const SAVE_ORDER_ACTION   = 'wc_gpd_save_order_design';
	const REVERT_ORDER_ACTION = 'wc_gpd_revert_order_design';
	const NONCE_DOWNLOAD      = 'wc_gpd_download_design';
	const NONCE_SAVE_ORDER    = 'wc_gpd_save_order_design';
	const NONCE_REVERT_ORDER  = 'wc_gpd_revert_order_design';

	/**
	 * @return WC_GPD_Admin_Order
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register module hooks.
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ), 20 );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'handle_download' ) );
		add_action( 'admin_post_' . self::SAVE_ORDER_ACTION, array( $this, 'handle_save_order_design' ) );
		add_action( 'admin_post_' . self::REVERT_ORDER_ACTION, array( $this, 'handle_revert_order_design' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_styles' ) );
		add_action( 'admin_notices', array( $this, 'render_order_saved_notice' ) );
	}

	/**
	 * Register order meta box.
	 */
	public function register_meta_box() {
		$screen = 'shop_order';
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
			&& function_exists( 'wc_get_page_screen_id' ) ) {
			$screen = wc_get_page_screen_id( 'shop-order' );
		}

		add_meta_box(
			'wc_gpd_production_designs',
			__( 'Custom product designs', 'wc-generic-product-designer' ),
			array( $this, 'render_meta_box' ),
			$screen,
			'normal',
			'high'
		);
	}

	/**
	 * @param WC_Order $order Order.
	 * @return array<int, array{item: WC_Order_Item_Product}>
	 */
	private function get_design_items( $order ) {
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			if ( WC_GPD_SVG_Sanitizer::sanitize( $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, true ) ) ) {
				$items[ $item_id ] = array( 'item' => $item );
			}
		}
		return $items;
	}

	/**
	 * @param WP_Post|WC_Order $post_or_order Post or order.
	 */
	public function render_meta_box( $post_or_order ) {
		$order = ( $post_or_order instanceof WP_Post )
			? wc_get_order( $post_or_order->ID )
			: $post_or_order;

		if ( ! $order instanceof WC_Order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'wc-generic-product-designer' ) . '</p>';
			return;
		}

		$design_items = $this->get_design_items( $order );
		if ( empty( $design_items ) ) {
			echo '<p>' . esc_html__( 'No custom designs on this order.', 'wc-generic-product-designer' ) . '</p>';
			return;
		}

		$saved_item = isset( $_GET['wc_gpd_item'] ) ? absint( $_GET['wc_gpd_item'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wc-gpd-order-designs">';
		foreach ( $design_items as $item_id => $data ) {
			/** @var WC_Order_Item_Product $item */
			$item         = $data['item'];
			$product_name = $item->get_name();
			$preview_url  = WC_GPD_Preview::preview_url_from_order_item( $item );
			if ( $saved_item === (int) $item_id && $preview_url ) {
				$preview_url = add_query_arg( 'v', (string) time(), $preview_url );
			}
			$edit_url     = self::get_edit_design_url( $order->get_id(), $item_id, $item );
			$has_original = (bool) $item->get_meta( WC_GPD_Product_Meta::ORDER_META_ORIGINAL_DESIGN_SVG, true );
			?>
			<div class="wc-gpd-order-design">
				<h4><?php echo esc_html( $product_name ); ?></h4>

				<?php if ( $preview_url ) : ?>
					<div class="wc-gpd-order-design__preview">
						<img src="<?php echo esc_url( $preview_url ); ?>" alt="<?php echo esc_attr( $product_name ); ?>" class="wc-gpd-order-design__preview-img" loading="lazy" decoding="async" />
					</div>
				<?php endif; ?>

				<?php if ( $edit_url ) : ?>
					<p class="wc-gpd-order-design__actions">
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Edit design', 'wc-generic-product-designer' ); ?>
						</a>
						<?php if ( $has_original ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wc-gpd-order-revert-form">
								<?php wp_nonce_field( self::NONCE_REVERT_ORDER . '_' . $order->get_id() . '_' . $item_id ); ?>
								<input type="hidden" name="action" value="<?php echo esc_attr( self::REVERT_ORDER_ACTION ); ?>" />
								<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
								<input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $item_id ); ?>" />
								<button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Restore the customer’s original design? Admin edits will be discarded.', 'wc-generic-product-designer' ) ); ?>');">
									<?php esc_html_e( 'Revert to customer design', 'wc-generic-product-designer' ); ?>
								</button>
							</form>
						<?php endif; ?>
					</p>
					<p class="description"><?php esc_html_e( 'Open the designer to edit, save changes, and download production or proof files.', 'wc-generic-product-designer' ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Admin notice after saving a design from the order editor.
	 */
	public function render_order_saved_notice() {
		$saved   = isset( $_GET['wc_gpd_order_saved'] ) && '1' === (string) $_GET['wc_gpd_order_saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reverted = isset( $_GET['wc_gpd_order_reverted'] ) && '1' === (string) $_GET['wc_gpd_order_reverted']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $saved && ! $reverted ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$allowed = array( 'shop_order', wc_get_page_screen_id( 'shop-order' ) );
		if ( ! in_array( $screen->id, $allowed, true ) ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		if ( $reverted ) {
			esc_html_e( 'Customer design restored. The preview below has been updated.', 'wc-generic-product-designer' );
		} else {
			esc_html_e( 'Order design saved. The preview below has been updated.', 'wc-generic-product-designer' );
		}
		echo '</p></div>';
	}

	/**
	 * @param int                   $order_id Order ID.
	 * @param int                   $item_id  Item ID.
	 * @param WC_Order_Item_Product $item     Item.
	 * @return string
	 */
	public static function get_edit_design_url( $order_id, $item_id, $item ) {
		$product_id = $item->get_product_id();
		$permalink  = $product_id ? get_permalink( $product_id ) : '';
		if ( ! $permalink ) {
			return '';
		}

		return add_query_arg(
			array(
				'wc_gpd_edit_order' => absint( $order_id ),
				'wc_gpd_edit_item'  => absint( $item_id ),
			),
			$permalink
		);
	}

	/**
	 * @param array $post Request data.
	 * @return array
	 */
	private function download_options_from_request( array $post ) {
		$preset_type = isset( $post['wc_gpd_preset'] ) ? sanitize_key( (string) $post['wc_gpd_preset'] ) : 'production';
		$preset_id   = isset( $post['wc_gpd_preset_id'] ) ? sanitize_key( (string) $post['wc_gpd_preset_id'] ) : '';
		$proof_id    = isset( $post['wc_gpd_proof_template_id'] ) ? sanitize_key( (string) $post['wc_gpd_proof_template_id'] ) : '';

		if ( 'proof' === $preset_type ) {
			if ( $proof_id && WC_GPD_Proof_Template::get( $proof_id ) ) {
				$options = WC_GPD_Proof_Template::export_options( $proof_id );
				$options['template_id'] = $proof_id;
			} else {
				$options = WC_GPD_Settings::proof_export_defaults();
			}
			$options['preset'] = 'proof';
		} elseif ( $preset_id && WC_GPD_Export_Presets::get( $preset_id ) ) {
			$options = WC_GPD_Export_Presets::export_options( $preset_id );
		} elseif ( 'production' === $preset_type ) {
			$options = WC_GPD_Export_Presets::export_options( WC_GPD_Export_Presets::default_id() );
		} else {
			$options = WC_GPD_Settings::export_defaults();
			$options['preset'] = $preset_type;
		}

		$override_fields = array(
			'wc_gpd_inc_background'      => 'include_background',
			'wc_gpd_inc_text'            => 'include_text',
			'wc_gpd_inc_outlines'        => 'include_outlines',
			'wc_gpd_inc_shapes'          => 'include_shapes',
			'wc_gpd_rasterize'           => 'rasterize',
			'wc_gpd_transparent_raster'  => 'transparent_raster',
		);
		foreach ( $override_fields as $field => $key ) {
			if ( isset( $post[ $field ] ) ) {
				$options[ $key ] = '1' === (string) $post[ $field ];
			}
		}

		if ( isset( $post['wc_gpd_outline_color'] ) ) {
			$color = sanitize_hex_color( (string) $post['wc_gpd_outline_color'] );
			if ( $color ) {
				$options['outline_color'] = $color;
			}
		}
		if ( isset( $post['wc_gpd_outline_width'] ) ) {
			$options['outline_width'] = (float) $post['wc_gpd_outline_width'];
		}

		return $options;
	}

	/**
	 * Stream export file.
	 */
	public function handle_download() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		if ( ! $order_id || ! $item_id ) {
			wp_die( esc_html__( 'Invalid request.', 'wc-generic-product-designer' ), 400 );
		}

		check_admin_referer( self::NONCE_DOWNLOAD . '_' . $order_id . '_' . $item_id );

		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'wc-generic-product-designer' ), 403 );
		}

		$order = wc_get_order( $order_id );
		$item  = $order ? $order->get_item( $item_id ) : null;
		if ( ! $order || ! $item instanceof WC_Order_Item_Product ) {
			wp_die( esc_html__( 'Line item not found.', 'wc-generic-product-designer' ), 404 );
		}

		$post    = wp_unslash( $_POST );
		$options = $this->download_options_from_request( is_array( $post ) ? $post : array() );
		$preset  = isset( $options['preset'] ) ? (string) $options['preset'] : 'production';

		if ( 'proof' === $preset && ! empty( $options['template_id'] ) && WC_GPD_Proof_Template::get( $options['template_id'] ) ) {
			$result = WC_GPD_Export::build_proof_for_order_item( $item, $options['template_id'] );
		} else {
			$result = WC_GPD_Export::build_for_order_item( $item, $options );
		}

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), 500 );
		}

		header( 'Content-Type: ' . $result['mime'] );
		header( 'Content-Disposition: attachment; filename="' . $result['filename'] . '"' );
		header( 'Content-Length: ' . strlen( $result['content'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-cache' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $result['content'];
		exit;
	}

	/**
	 * Save edited design back to order line item.
	 */
	public function handle_save_order_design() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		if ( ! $order_id || ! $item_id ) {
			wp_die( esc_html__( 'Invalid request.', 'wc-generic-product-designer' ), 400 );
		}

		check_admin_referer( self::NONCE_SAVE_ORDER . '_' . $order_id . '_' . $item_id, '_wc_gpd_save_order_nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this design.', 'wc-generic-product-designer' ), 403 );
		}

		$order = wc_get_order( $order_id );
		$item  = $order ? $order->get_item( $item_id ) : null;
		if ( ! $order || ! $item instanceof WC_Order_Item_Product ) {
			wp_die( esc_html__( 'Line item not found.', 'wc-generic-product-designer' ), 404 );
		}

		$raw_svg  = isset( $_POST['wc_gpd_design_svg'] ) ? wp_unslash( $_POST['wc_gpd_design_svg'] ) : '';
		$svg      = WC_GPD_SVG_Sanitizer::sanitize( is_string( $raw_svg ) ? $raw_svg : '' );
		$raw_json = isset( $_POST['wc_gpd_design_json'] ) ? wp_unslash( $_POST['wc_gpd_design_json'] ) : '';
		$json     = WC_GPD_Design_Json::sanitize( is_string( $raw_json ) ? $raw_json : '' );

		if ( ! $svg ) {
			wp_die( esc_html__( 'Design SVG is missing or invalid.', 'wc-generic-product-designer' ), 400 );
		}

		$item->update_meta_data( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, $svg );
		if ( $json ) {
			$item->update_meta_data( WC_GPD_Product_Meta::ORDER_META_DESIGN_JSON, $json );
		}

		$product_id = $item->get_product_id();
		$preview    = WC_GPD_Preview_Storage::save_design_preview(
			$svg,
			WC_GPD_Product_Meta::get_settings( $product_id ),
			$product_id
		);
		if ( ! empty( $preview['url'] ) ) {
			$item->update_meta_data( WC_GPD_Product_Meta::ORDER_META_PREVIEW_URL, esc_url_raw( $preview['url'] ) );
		}

		$item->update_meta_data( '_wc_gpd_has_design', 'yes' );
		$item->save();
		$order->save();

		$redirect = $order->get_edit_order_url();
		$redirect = add_query_arg(
			array(
				'wc_gpd_order_saved' => '1',
				'wc_gpd_item'        => $item_id,
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Restore the customer's original design on a line item.
	 */
	public function handle_revert_order_design() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		if ( ! $order_id || ! $item_id ) {
			wp_die( esc_html__( 'Invalid request.', 'wc-generic-product-designer' ), 400 );
		}

		check_admin_referer( self::NONCE_REVERT_ORDER . '_' . $order_id . '_' . $item_id );

		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this design.', 'wc-generic-product-designer' ), 403 );
		}

		$order = wc_get_order( $order_id );
		$item  = $order ? $order->get_item( $item_id ) : null;
		if ( ! $order || ! $item instanceof WC_Order_Item_Product ) {
			wp_die( esc_html__( 'Line item not found.', 'wc-generic-product-designer' ), 404 );
		}

		$svg = WC_GPD_SVG_Sanitizer::sanitize( $item->get_meta( WC_GPD_Product_Meta::ORDER_META_ORIGINAL_DESIGN_SVG, true ) );
		if ( ! $svg ) {
			wp_die( esc_html__( 'No original customer design is stored for this item.', 'wc-generic-product-designer' ), 400 );
		}

		$item->update_meta_data( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, $svg );

		$json = (string) $item->get_meta( WC_GPD_Product_Meta::ORDER_META_ORIGINAL_DESIGN_JSON, true );
		if ( $json ) {
			$item->update_meta_data( WC_GPD_Product_Meta::ORDER_META_DESIGN_JSON, $json );
		} else {
			$item->delete_meta_data( WC_GPD_Product_Meta::ORDER_META_DESIGN_JSON );
		}

		$preview = (string) $item->get_meta( WC_GPD_Product_Meta::ORDER_META_ORIGINAL_PREVIEW_URL, true );
		if ( $preview ) {
			$item->update_meta_data( WC_GPD_Product_Meta::ORDER_META_PREVIEW_URL, esc_url_raw( $preview ) );
		}

		$item->save();
		$order->save();

		$redirect = $order->get_edit_order_url();
		$redirect = add_query_arg(
			array(
				'wc_gpd_order_reverted' => '1',
				'wc_gpd_item'           => $item_id,
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @param string $hook Hook.
	 */
	public function enqueue_order_styles( $hook ) {
		$screens = array( 'post.php', 'post-new.php', 'woocommerce_page_wc-orders' );
		if ( ! in_array( $hook, $screens, true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$allowed = array( 'shop_order', wc_get_page_screen_id( 'shop-order' ) );
		if ( ! in_array( $screen->id, $allowed, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wc-gpd-admin-order',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-order.css',
			array(),
			WC_GPD_VERSION
		);
	}
}
