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
class WC_GPD_Cart implements WC_GPD_Module {

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
		// Hooks registered via register().
	}

	/**
	 * Register module hooks.
	 */
	public function register() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'remove_replaced_cart_item' ), 20, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'cart_item_thumbnail' ), 99, 3 );
		add_filter( 'woocommerce_widget_cart_item_thumbnail', array( $this, 'cart_item_thumbnail' ), 99, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_line_item_meta' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_cart_styles' ) );
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
			WC_GPD_Logger::warning( 'Add to cart nonce failed', array( 'product_id' => $product_id ) );
			wc_add_notice( __( 'Security check failed. Please refresh the page and try again.', 'wc-generic-product-designer' ), 'error' );
			return false;
		}

		$raw_svg = isset( $_POST['wc_gpd_design_svg'] ) ? wp_unslash( $_POST['wc_gpd_design_svg'] ) : '';
		$svg     = WC_GPD_SVG_Sanitizer::sanitize( is_string( $raw_svg ) ? $raw_svg : '' );

		if ( ! $svg ) {
			WC_GPD_Logger::warning(
				'Add to cart rejected: invalid or missing SVG',
				array(
					'product_id' => $product_id,
					'raw_length' => is_string( $raw_svg ) ? strlen( $raw_svg ) : 0,
				)
			);
			wc_add_notice( __( 'Please complete your product design before adding to cart.', 'wc-generic-product-designer' ), 'error' );
			return false;
		}

		WC_GPD_Logger::info(
			'Design validated for cart',
			array(
				'product_id' => $product_id,
				'svg_bytes'  => strlen( $svg ),
			)
		);

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

			$raw_json = isset( $_POST['wc_gpd_design_json'] ) ? wp_unslash( $_POST['wc_gpd_design_json'] ) : '';
			$json     = WC_GPD_Design_Json::sanitize( is_string( $raw_json ) ? $raw_json : '' );
			if ( $json ) {
				$cart_item_data[ WC_GPD_Product_Meta::CART_KEY_DESIGN_JSON ] = $json;
			}

			$cart_item_data['unique_key'] = md5( $svg . ( $json ? $json : '' ) );
		}

		return $cart_item_data;
	}

	/**
	 * Show design meta + edit link in cart/checkout.
	 *
	 * @param array $item_data Item display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ) ) {
			return $item_data;
		}

		$edit_url = self::get_edit_design_url( $cart_item );
		$link     = $edit_url
			? sprintf(
				'<a href="%1$s" class="wc-gpd-edit-design">%2$s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit design', 'wc-generic-product-designer' )
			)
			: '';

		$item_data[] = array(
			'key'     => __( 'Design', 'wc-generic-product-designer' ),
			'value'   => __( 'Custom design', 'wc-generic-product-designer' ),
			'display' => $link ? wp_kses_post( $link ) : esc_html__( 'Custom design', 'wc-generic-product-designer' ),
		);

		return $item_data;
	}

	/**
	 * Replace product thumbnail with design preview in cart.
	 *
	 * @param string $thumbnail     Original thumbnail HTML.
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function cart_item_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ) ) {
			return $thumbnail;
		}

		$svg = WC_GPD_SVG_Sanitizer::sanitize( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] );
		if ( ! $svg ) {
			return $thumbnail;
		}

		$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		$settings   = WC_GPD_Product_Meta::get_settings( $product_id );
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$alt        = $product ? $product->get_name() : __( 'Custom design', 'wc-generic-product-designer' );

		$preview = WC_GPD_Preview::cart_thumbnail_html( $svg, $settings, $alt );
		if ( ! $preview ) {
			return $thumbnail;
		}

		return $preview;
	}

	/**
	 * When updating a design, remove the previous cart line before adding the new one.
	 *
	 * @param bool $passed     Validation passed.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public function remove_replaced_cart_item( $passed, $product_id, $quantity ) {
		if ( ! $passed || ! WC_GPD_Product_Meta::is_enabled( $product_id ) ) {
			return $passed;
		}

		if ( empty( $_POST['wc_gpd_edit_cart_key'] ) ) {
			return $passed;
		}

		$old_key = sanitize_text_field( wp_unslash( $_POST['wc_gpd_edit_cart_key'] ) );
		if ( ! $old_key || ! WC()->cart ) {
			return $passed;
		}

		$old_item = WC()->cart->get_cart_item( $old_key );
		if ( ! $old_item || (int) $old_item['product_id'] !== (int) $product_id ) {
			return $passed;
		}

		WC()->cart->remove_cart_item( $old_key );
		WC_GPD_Logger::info( 'Removed cart item for design edit', array( 'cart_item_key' => $old_key ) );

		return $passed;
	}

	/**
	 * Build product URL to edit a cart line design.
	 *
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key (optional).
	 * @return string
	 */
	public static function get_edit_design_url( $cart_item, $cart_item_key = '' ) {
		if ( empty( $cart_item['product_id'] ) ) {
			return '';
		}

		if ( ! $cart_item_key ) {
			$cart_item_key = self::find_cart_item_key( $cart_item );
		}

		if ( ! $cart_item_key ) {
			return '';
		}

		$permalink = get_permalink( absint( $cart_item['product_id'] ) );
		if ( ! $permalink ) {
			return '';
		}

		return add_query_arg(
			array(
				'wc_gpd_edit' => $cart_item_key,
			),
			$permalink
		);
	}

	/**
	 * Locate cart item key when WooCommerce does not pass it to item-data filters.
	 *
	 * @param array $cart_item Cart item.
	 * @return string
	 */
	private static function find_cart_item_key( $cart_item ) {
		if ( ! WC()->cart ) {
			return '';
		}

		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( (int) $item['product_id'] !== (int) $cart_item['product_id'] ) {
				continue;
			}

			$item_svg   = $item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ?? '';
			$search_svg = $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ?? '';

			if ( $item_svg && $search_svg && $item_svg === $search_svg ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Cart/checkout thumbnail styles.
	 */
	public function enqueue_cart_styles() {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'wc-gpd-cart',
			WC_GPD_PLUGIN_URL . 'assets/css/cart.css',
			array(),
			WC_GPD_VERSION
		);
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
			WC_GPD_Logger::info(
				'Design saved to order line item',
				array(
					'order_id' => $order->get_id(),
					'item_id'  => $item->get_id(),
					'svg_bytes' => strlen( $svg ),
				)
			);
		}
	}
}
