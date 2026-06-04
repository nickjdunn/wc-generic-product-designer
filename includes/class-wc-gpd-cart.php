<?php
/**
 * Cart and checkout: persist design SVG.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce cart/order integration.
 */
class WC_GPD_Cart {

	/**
	 * @var WC_GPD_Cart|null
	 */
	private static $instance = null;

	/**
	 * @return WC_GPD_Cart
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
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_line_item_meta' ), 10, 4 );
	}

	/**
	 * Require design SVG when designer is enabled.
	 *
	 * @param bool $passed      Validation passed.
	 * @param int  $product_id  Product ID.
	 * @param int  $quantity    Quantity.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		if ( ! WC_GPD_Product_Meta::is_enabled( $product_id ) ) {
			return $passed;
		}

		if ( ! isset( $_POST[ WC_GPD_Frontend::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ WC_GPD_Frontend::NONCE_NAME ] ) ), WC_GPD_Frontend::NONCE_ACTION ) ) {
			wc_add_notice( __( 'Security check failed. Please refresh the page and try again.', 'wc-generic-product-designer' ), 'error' );
			return false;
		}

		$raw_svg = isset( $_POST['wc_gpd_design_svg'] ) ? wp_unslash( $_POST['wc_gpd_design_svg'] ) : '';
		$svg     = WC_GPD_SVG_Sanitizer::sanitize( is_string( $raw_svg ) ? $raw_svg : '' );

		if ( ! $svg ) {
			wc_add_notice( __( 'Please complete your product design before adding to cart.', 'wc-generic-product-designer' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Attach sanitized SVG to cart item.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 * @param int   $variation_id   Variation ID.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( ! WC_GPD_Product_Meta::is_enabled( $product_id ) ) {
			return $cart_item_data;
		}

		if ( ! isset( $_POST[ WC_GPD_Frontend::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ WC_GPD_Frontend::NONCE_NAME ] ) ), WC_GPD_Frontend::NONCE_ACTION ) ) {
			return $cart_item_data;
		}

		$raw_svg = isset( $_POST['wc_gpd_design_svg'] ) ? wp_unslash( $_POST['wc_gpd_design_svg'] ) : '';
		$svg     = WC_GPD_SVG_Sanitizer::sanitize( is_string( $raw_svg ) ? $raw_svg : '' );

		if ( $svg ) {
			$cart_item_data[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] = $svg;
			// Distinct cart lines per unique design (WooCommerce core behavior).
			$cart_item_data['unique_key'] = md5( $svg );
		}

		return $cart_item_data;
	}

	/**
	 * Show "Design attached" in cart/checkout.
	 *
	 * @param array $item_data Item display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( ! empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ) ) {
			$item_data[] = array(
				'key'   => __( 'Design', 'wc-generic-product-designer' ),
				'value' => __( 'Design attached', 'wc-generic-product-designer' ),
			);
		}
		return $item_data;
	}

	/**
	 * Persist SVG on order line item (hidden from customer emails by default via underscore prefix).
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart values.
	 * @param WC_Order              $order         Order.
	 */
	public function save_order_line_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ) ) {
			return;
		}

		$svg = WC_GPD_SVG_Sanitizer::sanitize( $values[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] );
		if ( $svg ) {
			$item->add_meta_data( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, $svg, true );
			$item->add_meta_data(
				'_wc_gpd_has_design',
				'yes',
				true
			);
		}
	}
}
