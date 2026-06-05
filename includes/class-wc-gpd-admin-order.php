<?php
/**
 * Admin order: design preview and SVG download.
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

	const DOWNLOAD_ACTION = 'wc_gpd_download_svg';
	const NONCE_ACTION    = 'wc_gpd_download_svg';
	const TYPE_COMPOSITE  = 'composite';
	const TYPE_LAYERS     = 'layers';

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
	 * Constructor.
	 */
	private function __construct() {
		// Hooks registered via register().
	}

	/**
	 * Register module hooks.
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ), 20 );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'handle_download' ) );
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
			__( 'Production designs', 'wc-generic-product-designer' ),
			array( $this, 'render_meta_box' ),
			$screen,
			'normal',
			'high'
		);
	}

	/**
	 * Collect line items with design SVG.
	 *
	 * @param WC_Order $order Order.
	 * @return array<int, array{item: WC_Order_Item_Product, svg: string}>
	 */
	private function get_design_items( $order ) {
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$svg = $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, true );
			if ( $svg ) {
				$items[ $item_id ] = array(
					'item' => $item,
					'svg'  => $svg,
				);
			}
		}
		return $items;
	}

	/**
	 * Render meta box content.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
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

		echo '<div class="wc-gpd-order-designs">';
		foreach ( $design_items as $item_id => $data ) {
			/** @var WC_Order_Item_Product $item */
			$item              = $data['item'];
			$product_name      = $item->get_name();
			$composite_url     = WC_GPD_Preview::preview_url_from_order_item( $item );
			$download_composite = $this->get_download_url( $order->get_id(), $item_id, self::TYPE_COMPOSITE );
			$download_layers    = $this->get_download_url( $order->get_id(), $item_id, self::TYPE_LAYERS );

			?>
			<div class="wc-gpd-order-design">
				<h4><?php echo esc_html( $product_name ); ?></h4>
				<?php if ( $composite_url ) : ?>
					<div class="wc-gpd-order-design__preview">
						<img
							src="<?php echo esc_url( $composite_url ); ?>"
							alt="<?php echo esc_attr( $product_name ); ?>"
							class="wc-gpd-order-design__preview-img"
							loading="lazy"
							decoding="async"
						/>
					</div>
				<?php endif; ?>
				<p class="wc-gpd-order-design__actions">
					<a href="<?php echo esc_url( $download_composite ); ?>" class="button button-primary button-large wc-gpd-download-svg">
						<?php esc_html_e( 'Download production SVG', 'wc-generic-product-designer' ); ?>
					</a>
					<a href="<?php echo esc_url( $download_layers ); ?>" class="button button-secondary wc-gpd-download-svg-layers">
						<?php esc_html_e( 'Download text layers only', 'wc-generic-product-designer' ); ?>
					</a>
				</p>
				<p class="description">
					<?php esc_html_e( 'Production SVG includes the product template and text at full canvas resolution. Text-only is vector layers without the template background.', 'wc-generic-product-designer' ); ?>
				</p>
			</div>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Build secure download URL.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $item_id  Line item ID.
	 * @param string $type     composite|layers.
	 * @return string
	 */
	private function get_download_url( $order_id, $item_id, $type = self::TYPE_COMPOSITE ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => self::DOWNLOAD_ACTION,
					'order_id' => absint( $order_id ),
					'item_id'  => absint( $item_id ),
					'type'     => $type,
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION . '_' . absint( $order_id ) . '_' . absint( $item_id )
		);
	}

	/**
	 * Stream SVG file download.
	 */
	public function handle_download() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$item_id  = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
		$type     = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : self::TYPE_COMPOSITE;

		if ( ! $order_id || ! $item_id ) {
			wp_die( esc_html__( 'Invalid request.', 'wc-generic-product-designer' ), 400 );
		}

		check_admin_referer( self::NONCE_ACTION . '_' . $order_id . '_' . $item_id );

		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'wc-generic-product-designer' ), 403 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'wc-generic-product-designer' ), 404 );
		}

		$item = $order->get_item( $item_id );
		if ( ! $item instanceof WC_Order_Item_Product ) {
			wp_die( esc_html__( 'Line item not found.', 'wc-generic-product-designer' ), 404 );
		}

		$layers_svg = WC_GPD_SVG_Sanitizer::sanitize( $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, true ) );
		if ( ! $layers_svg ) {
			wp_die( esc_html__( 'Design file not available.', 'wc-generic-product-designer' ), 404 );
		}

		if ( self::TYPE_LAYERS === $type ) {
			$svg      = $layers_svg;
			$suffix   = 'layers';
		} else {
			$svg = WC_GPD_Preview::composite_from_order_item( $item );
			if ( ! $svg ) {
				$svg    = $layers_svg;
				$suffix = 'layers';
			} else {
				$suffix = 'production';
			}
		}

		$filename = sprintf(
			'order-%d-item-%d-design-%s.svg',
			$order_id,
			$item_id,
			$suffix
		);

		header( 'Content-Type: image/svg+xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $svg ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-cache' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary/text file output.
		echo $svg;
		exit;
	}

	/**
	 * Order screen styles for previews.
	 *
	 * @param string $hook Admin hook.
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
