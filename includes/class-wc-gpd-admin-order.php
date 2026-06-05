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
	const NONCE_DOWNLOAD      = 'wc_gpd_download_design';
	const NONCE_SAVE_ORDER    = 'wc_gpd_save_order_design';

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_styles' ) );
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

		$defaults = WC_GPD_Settings::export_defaults();
		$proof    = WC_GPD_Settings::proof_export_defaults();

		echo '<div class="wc-gpd-order-designs">';
		foreach ( $design_items as $item_id => $data ) {
			/** @var WC_Order_Item_Product $item */
			$item         = $data['item'];
			$product_name = $item->get_name();
			$preview_url  = WC_GPD_Preview::preview_url_from_order_item( $item );
			$edit_url     = self::get_edit_design_url( $order->get_id(), $item_id, $item );
			?>
			<div class="wc-gpd-order-design">
				<h4><?php echo esc_html( $product_name ); ?></h4>

				<?php if ( $preview_url ) : ?>
					<div class="wc-gpd-order-design__preview">
						<img src="<?php echo esc_url( $preview_url ); ?>" alt="<?php echo esc_attr( $product_name ); ?>" class="wc-gpd-order-design__preview-img" loading="lazy" decoding="async" />
					</div>
				<?php endif; ?>

				<?php if ( $edit_url ) : ?>
					<p>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Edit design before download', 'wc-generic-product-designer' ); ?>
						</a>
					</p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wc-gpd-order-download-form">
					<?php wp_nonce_field( self::NONCE_DOWNLOAD . '_' . $order->get_id() . '_' . $item_id ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::DOWNLOAD_ACTION ); ?>" />
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
					<input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $item_id ); ?>" />
					<input type="hidden" name="wc_gpd_preset" value="production" />

					<p><strong><?php esc_html_e( 'Download production file', 'wc-generic-product-designer' ); ?></strong></p>
					<p class="description"><?php esc_html_e( 'Uses your default settings from WooCommerce → Product Designer.', 'wc-generic-product-designer' ); ?></p>
					<input type="hidden" name="wc_gpd_inc_background" value="<?php echo ! empty( $defaults['include_background'] ) ? '1' : '0'; ?>" />
					<input type="hidden" name="wc_gpd_inc_text" value="<?php echo ! empty( $defaults['include_text'] ) ? '1' : '0'; ?>" />
					<input type="hidden" name="wc_gpd_inc_outlines" value="<?php echo ! empty( $defaults['include_outlines'] ) ? '1' : '0'; ?>" />
					<input type="hidden" name="wc_gpd_inc_shapes" value="<?php echo ! empty( $defaults['include_shapes'] ) ? '1' : '0'; ?>" />
					<input type="hidden" name="wc_gpd_rasterize" value="<?php echo ! empty( $defaults['rasterize'] ) ? '1' : '0'; ?>" />
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Download production file', 'wc-generic-product-designer' ); ?></button>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wc-gpd-order-download-form wc-gpd-order-download-form--proof">
					<?php wp_nonce_field( self::NONCE_DOWNLOAD . '_' . $order->get_id() . '_' . $item_id ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::DOWNLOAD_ACTION ); ?>" />
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
					<input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $item_id ); ?>" />
					<input type="hidden" name="wc_gpd_preset" value="proof" />
					<input type="hidden" name="wc_gpd_inc_background" value="<?php echo ! empty( $proof['include_background'] ) ? '1' : '0'; ?>" />
					<input type="hidden" name="wc_gpd_inc_text" value="1" />
					<input type="hidden" name="wc_gpd_inc_outlines" value="0" />
					<input type="hidden" name="wc_gpd_inc_shapes" value="1" />
					<input type="hidden" name="wc_gpd_rasterize" value="0" />
					<button type="submit" class="button"><?php esc_html_e( 'Download customer proof', 'wc-generic-product-designer' ); ?></button>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wc-gpd-order-download-form wc-gpd-order-download-form--custom">
					<?php wp_nonce_field( self::NONCE_DOWNLOAD . '_' . $order->get_id() . '_' . $item_id ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::DOWNLOAD_ACTION ); ?>" />
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
					<input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $item_id ); ?>" />
					<input type="hidden" name="wc_gpd_preset" value="custom" />

					<p><strong><?php esc_html_e( 'Download with custom options', 'wc-generic-product-designer' ); ?></strong></p>
					<div class="wc-gpd-download-options">
						<label><input type="checkbox" name="wc_gpd_inc_background" value="1" /> <?php esc_html_e( 'Product background image', 'wc-generic-product-designer' ); ?></label>
						<label><input type="checkbox" name="wc_gpd_inc_text" value="1" checked="checked" /> <?php esc_html_e( 'Customer text', 'wc-generic-product-designer' ); ?></label>
						<label><input type="checkbox" name="wc_gpd_inc_outlines" value="1" checked="checked" /> <?php esc_html_e( 'Template outline lines', 'wc-generic-product-designer' ); ?></label>
						<label><input type="checkbox" name="wc_gpd_inc_shapes" value="1" checked="checked" /> <?php esc_html_e( 'Customer shapes', 'wc-generic-product-designer' ); ?></label>
						<label><input type="checkbox" name="wc_gpd_rasterize" value="1" id="wc_gpd_rasterize_<?php echo esc_attr( (string) $item_id ); ?>" /> <?php esc_html_e( 'Rasterize (PNG)', 'wc-generic-product-designer' ); ?></label>
						<label><input type="checkbox" name="wc_gpd_transparent_raster" value="1" checked="checked" /> <?php esc_html_e( 'Transparent PNG background (when rasterizing)', 'wc-generic-product-designer' ); ?></label>
					</div>
					<button type="submit" class="button"><?php esc_html_e( 'Download custom file', 'wc-generic-product-designer' ); ?></button>
				</form>
			</div>
			<?php
		}
		echo '</div>';
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

		$preset = isset( $_POST['wc_gpd_preset'] ) ? sanitize_key( wp_unslash( $_POST['wc_gpd_preset'] ) ) : 'custom';
		$options = WC_GPD_Export::options_from_request( wp_unslash( $_POST ) );
		if ( 'production' === $preset ) {
			$options = WC_GPD_Settings::export_defaults();
		} elseif ( 'proof' === $preset ) {
			$options = WC_GPD_Settings::proof_export_defaults();
		}
		$options['preset'] = $preset;

		$result = WC_GPD_Export::build_for_order_item( $item, $options );
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

		$redirect = self::get_edit_design_url( $order_id, $item_id, $item );
		$redirect = add_query_arg( 'wc_gpd_order_saved', '1', $redirect );

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
