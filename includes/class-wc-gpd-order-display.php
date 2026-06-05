<?php
/**
 * Customer-facing order views: thumbnails on thank-you, account, and emails.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order display integration.
 */
class WC_GPD_Order_Display implements WC_GPD_Module {

	/**
	 * @var WC_GPD_Order_Display|null
	 */
	private static $instance = null;

	/**
	 * @return WC_GPD_Order_Display
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
		add_filter( 'woocommerce_order_item_thumbnail', array( $this, 'order_item_thumbnail' ), 99, 3 );
		add_filter( 'woocommerce_email_order_item_thumbnail', array( $this, 'order_item_thumbnail' ), 99, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Replace order line thumbnail with the saved design preview.
	 *
	 * @param string                $image   Original image HTML.
	 * @param WC_Order_Item_Product $item    Order item.
	 * @param WC_Order|false        $order   Order (email filter omits this).
	 * @return string
	 */
	public function order_item_thumbnail( $image, $item, $order = false ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return $image;
		}

		if ( 'yes' !== $item->get_meta( '_wc_gpd_has_design', true ) ) {
			return $image;
		}

		$url = WC_GPD_Preview::preview_url_from_order_item( $item );
		if ( ! $url ) {
			return $image;
		}

		$alt = $item->get_name();

		$html = WC_GPD_Preview::cart_thumbnail_html( '', array(), $alt, $url );
		return $html ? $html : $image;
	}

	/**
	 * Order view thumbnail styles.
	 */
	public function enqueue_styles() {
		if ( ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'view-order' ) && ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'wc-gpd-cart',
			WC_GPD_PLUGIN_URL . 'assets/css/cart.css',
			array(),
			WC_GPD_VERSION
		);
	}
}
