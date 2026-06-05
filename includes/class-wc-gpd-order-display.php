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
	 * Register module hooks.
	 */
	public function register() {
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_internal_meta' ) );
		add_filter( 'woocommerce_order_item_thumbnail', array( $this, 'order_item_thumbnail' ), 99, 3 );
		add_filter( 'woocommerce_email_order_item_thumbnail', array( $this, 'order_item_thumbnail' ), 99, 3 );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'render_item_design_preview' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Hide internal design meta from customer-facing order views.
	 *
	 * @param array $hidden Hidden meta keys.
	 * @return array
	 */
	public function hide_internal_meta( $hidden ) {
		$hidden[] = '_wc_gpd_has_design';
		$hidden[] = WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG;
		$hidden[] = WC_GPD_Product_Meta::ORDER_META_DESIGN_JSON;
		$hidden[] = WC_GPD_Product_Meta::ORDER_META_PREVIEW_URL;
		return $hidden;
	}

	/**
	 * Whether the line item has a saved design.
	 *
	 * @param WC_Order_Item_Product $item Line item.
	 * @return bool
	 */
	private function item_has_design( $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return false;
		}

		if ( 'yes' === $item->get_meta( '_wc_gpd_has_design', true ) ) {
			return true;
		}

		return (bool) WC_GPD_SVG_Sanitizer::sanitize( $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, true ) );
	}

	/**
	 * Replace order line thumbnail with the saved design preview.
	 *
	 * @param string                $image Original image HTML.
	 * @param WC_Order_Item_Product $item  Order item.
	 * @param WC_Order|false        $order Order.
	 * @return string
	 */
	public function order_item_thumbnail( $image, $item, $order = false ) {
		if ( ! $this->item_has_design( $item ) ) {
			return $image;
		}

		$html = $this->build_preview_html( $item );
		return $html ? $html : $image;
	}

	/**
	 * Explicit preview block for block themes / thank-you pages.
	 *
	 * @param int                   $item_id   Item ID.
	 * @param WC_Order_Item_Product $item      Item.
	 * @param WC_Order              $order     Order.
	 * @param bool                  $plain_text Plain text context.
	 */
	public function render_item_design_preview( $item_id, $item, $order, $plain_text = false ) {
		if ( $plain_text || ! $this->item_has_design( $item ) ) {
			return;
		}

		$html = $this->build_preview_html( $item, 'wc-gpd-order-item-preview' );
		if ( ! $html ) {
			return;
		}

		echo '<div class="wc-gpd-order-item-preview-wrap">';
		echo '<p class="wc-gpd-order-item-preview-label"><strong>' . esc_html__( 'Your design', 'wc-generic-product-designer' ) . '</strong></p>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
		echo $html;
		echo '</div>';
	}

	/**
	 * Build preview image HTML for an order item.
	 *
	 * @param WC_Order_Item_Product $item  Item.
	 * @param string                $class Extra class.
	 * @return string
	 */
	private function build_preview_html( $item, $class = '' ) {
		$url = WC_GPD_Preview::preview_url_from_order_item( $item );
		if ( ! $url ) {
			return '';
		}

		$classes = trim( 'wc-gpd-cart-thumb-img wc-gpd-order-design-preview ' . $class );
		$alt     = $item->get_name();

		return sprintf(
			'<img src="%1$s" class="%2$s" width="300" height="225" alt="%3$s" loading="lazy" decoding="async" />',
			esc_url( $url ),
			esc_attr( $classes ),
			esc_attr( $alt )
		);
	}

	/**
	 * Order view thumbnail styles.
	 */
	public function enqueue_styles() {
		if ( ! function_exists( 'is_order_received_page' ) ) {
			return;
		}

		if ( ! is_order_received_page() && ! is_wc_endpoint_url( 'view-order' ) && ! is_checkout() ) {
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
