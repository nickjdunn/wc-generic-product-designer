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
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_contents', array( $this, 'apply_preview_image_ids' ), 20, 1 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_preview_image_ids_on_cart' ), 5 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'cart_item_thumbnail' ), 99, 3 );
		add_filter( 'woocommerce_widget_cart_item_thumbnail', array( $this, 'cart_item_thumbnail' ), 99, 3 );
		add_filter( 'woocommerce_store_api_cart_item_images', array( $this, 'store_api_cart_item_images' ), 99, 3 );
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
	 * Attach sanitized SVG and preview file to cart item.
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

		if ( ! $svg ) {
			return $cart_item_data;
		}

		$cart_item_data[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] = $svg;

		$raw_json = isset( $_POST['wc_gpd_design_json'] ) ? wp_unslash( $_POST['wc_gpd_design_json'] ) : '';
		$json     = WC_GPD_Design_Json::sanitize( is_string( $raw_json ) ? $raw_json : '' );
		if ( $json ) {
			$cart_item_data[ WC_GPD_Product_Meta::CART_KEY_DESIGN_JSON ] = $json;
		}

		$cart_item_data['unique_key'] = md5( $svg . ( $json ? $json : '' ) );

		$raw_preview = isset( $_POST['wc_gpd_preview_image'] ) ? wp_unslash( $_POST['wc_gpd_preview_image'] ) : '';
		$png_result  = WC_GPD_Preview_Storage::save_from_data_url( is_string( $raw_preview ) ? $raw_preview : '', $product_id );
		if ( ! empty( $png_result['url'] ) ) {
			$cart_item_data[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_URL ] = $png_result['url'];
			if ( ! empty( $png_result['id'] ) ) {
				$cart_item_data[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_ID ] = (int) $png_result['id'];
			}
		}

		if ( empty( $cart_item_data[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_URL ] ) ) {
			$settings = WC_GPD_Product_Meta::get_settings( $product_id );
			$svg_file = WC_GPD_Preview_Storage::save_design_preview( $svg, $settings, $product_id );
			if ( ! empty( $svg_file['url'] ) ) {
				$cart_item_data[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_URL ] = $svg_file['url'];
				if ( ! empty( $svg_file['id'] ) ) {
					$cart_item_data[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_ID ] = (int) $svg_file['id'];
				}
			}
		}

		return $cart_item_data;
	}

	/**
	 * Restore custom cart keys after session load (required for previews on cart page).
	 *
	 * @param array  $cart_item Cart item.
	 * @param array  $values    Session values.
	 * @param string $key       Cart item key.
	 * @return array
	 */
	public function get_cart_item_from_session( $cart_item, $values, $key ) {
		$keys = array(
			WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG,
			WC_GPD_Product_Meta::CART_KEY_DESIGN_JSON,
			WC_GPD_Product_Meta::CART_KEY_PREVIEW_URL,
			WC_GPD_Product_Meta::CART_KEY_PREVIEW_ID,
		);

		foreach ( $keys as $meta_key ) {
			if ( isset( $values[ $meta_key ] ) ) {
				$cart_item[ $meta_key ] = $values[ $meta_key ];
			}
		}

		return $cart_item;
	}

	/**
	 * When a cart line is hydrated, point its product image at the design preview.
	 *
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return array
	 */
	public function add_cart_item( $cart_item, $cart_item_key ) {
		$this->maybe_set_product_image_id( $cart_item, $cart_item_key );
		return $cart_item;
	}

	/**
	 * Blocks / Store API read product image ID from the cart product object.
	 *
	 * @param array $cart_contents Cart contents.
	 * @return array
	 */
	public function apply_preview_image_ids( $cart_contents ) {
		if ( ! is_array( $cart_contents ) ) {
			return $cart_contents;
		}

		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			$this->maybe_set_product_image_id( $cart_item, $cart_item_key );
		}

		return $cart_contents;
	}

	/**
	 * Set preview attachment on each cart line product (Cart block + mini-cart).
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function apply_preview_image_ids_on_cart( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$this->maybe_set_product_image_id( $cart_item, $cart_item_key );
		}
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

		$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$alt        = $product ? $product->get_name() : __( 'Custom design', 'wc-generic-product-designer' );

		$preview = $this->resolve_preview_for_cart_item( $cart_item, $cart_item_key );
		if ( ! empty( $preview['url'] ) ) {
			$html = WC_GPD_Preview::cart_thumbnail_html( '', array(), $alt, $preview['url'] );
			if ( $html ) {
				return $html;
			}
		}

		$svg = WC_GPD_SVG_Sanitizer::sanitize( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] );
		if ( ! $svg ) {
			return $thumbnail;
		}

		$settings = WC_GPD_Product_Meta::get_settings( $product_id );
		$html     = WC_GPD_Preview::cart_thumbnail_html( $svg, $settings, $alt );

		return $html ? $html : $thumbnail;
	}

	/**
	 * Block / Store API cart images (Cart block, mini-cart drawer on block themes).
	 *
	 * @param array  $product_images Image payloads.
	 * @param array  $cart_item      Cart item.
	 * @param string $cart_item_key  Cart item key.
	 * @return array
	 */
	public function store_api_cart_item_images( $product_images, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ) ) {
			return $product_images;
		}

		$preview = $this->resolve_preview_for_cart_item( $cart_item, $cart_item_key );
		$url     = ! empty( $preview['url'] ) ? esc_url( $preview['url'] ) : '';

		if ( ! $url ) {
			$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$svg        = WC_GPD_SVG_Sanitizer::sanitize( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] );
			if ( $svg && $product_id ) {
				$url = WC_GPD_Preview::composite_data_uri( $svg, WC_GPD_Product_Meta::get_settings( $product_id ) );
			}
		}

		if ( ! $url ) {
			return $product_images;
		}

		$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$alt        = $product ? $product->get_name() : __( 'Custom design', 'wc-generic-product-designer' );

		return array(
			(object) array(
				'id'        => ! empty( $preview['id'] ) ? (int) $preview['id'] : 0,
				'src'       => $url,
				'thumbnail' => $url,
				'srcset'    => '',
				'sizes'     => '',
				'name'      => $alt,
				'alt'       => $alt,
			),
		);
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
	 * Resolve preview URL/attachment for a cart line (generate on demand for legacy lines).
	 *
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return array{url:string,id:int}
	 */
	private function resolve_preview_for_cart_item( $cart_item, $cart_item_key ) {
		$url = ! empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_URL ] )
			? esc_url( $cart_item[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_URL ] )
			: '';
		$id  = ! empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_ID ] )
			? absint( $cart_item[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_ID ] )
			: 0;

		if ( $url ) {
			return array(
				'url' => $url,
				'id'  => $id,
			);
		}

		if ( empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ) ) {
			return array(
				'url' => '',
				'id'  => 0,
			);
		}

		$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
		$svg        = WC_GPD_SVG_Sanitizer::sanitize( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] );
		if ( ! $svg || ! $product_id ) {
			return array(
				'url' => '',
				'id'  => 0,
			);
		}

		$result = WC_GPD_Preview_Storage::save_design_preview(
			$svg,
			WC_GPD_Product_Meta::get_settings( $product_id ),
			$product_id
		);

		if ( ! empty( $result['url'] ) && $cart_item_key && WC()->cart && isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			WC()->cart->cart_contents[ $cart_item_key ][ WC_GPD_Product_Meta::CART_KEY_PREVIEW_URL ] = $result['url'];
			if ( ! empty( $result['id'] ) ) {
				WC()->cart->cart_contents[ $cart_item_key ][ WC_GPD_Product_Meta::CART_KEY_PREVIEW_ID ] = (int) $result['id'];
				$this->maybe_set_product_image_id( WC()->cart->cart_contents[ $cart_item_key ], $cart_item_key );
			}
		}

		return array(
			'url' => ! empty( $result['url'] ) ? $result['url'] : '',
			'id'  => ! empty( $result['id'] ) ? (int) $result['id'] : 0,
		);
	}

	/**
	 * Point the cart line product at the preview attachment (Cart/Checkout blocks).
	 *
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 */
	private function maybe_set_product_image_id( array $cart_item, $cart_item_key ) {
		if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
			return;
		}

		if ( empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ) ) {
			return;
		}

		$preview_id = ! empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_ID ] )
			? absint( $cart_item[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_ID ] )
			: 0;

		if ( ! $preview_id ) {
			$resolved   = $this->resolve_preview_for_cart_item( $cart_item, $cart_item_key );
			$preview_id = ! empty( $resolved['id'] ) ? (int) $resolved['id'] : 0;
		}

		if ( $preview_id ) {
			$cart_item['data']->set_image_id( $preview_id );
		}
	}

	/**
	 * Cart/checkout thumbnail styles.
	 */
	public function enqueue_cart_styles() {
		if ( ! class_exists( 'WooCommerce' ) ) {
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

			if ( ! empty( $values[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_URL ] ) ) {
				$item->add_meta_data(
					WC_GPD_Product_Meta::ORDER_META_PREVIEW_URL,
					esc_url_raw( $values[ WC_GPD_Product_Meta::CART_KEY_PREVIEW_URL ] ),
					true
				);
			}

			if ( ! empty( $values[ WC_GPD_Product_Meta::CART_KEY_DESIGN_JSON ] ) ) {
				$item->add_meta_data(
					WC_GPD_Product_Meta::ORDER_META_DESIGN_JSON,
					$values[ WC_GPD_Product_Meta::CART_KEY_DESIGN_JSON ],
					true
				);
			}
			WC_GPD_Production_Jobs::maybe_init_status( $item );

			WC_GPD_Logger::info(
				'Design saved to order line item',
				array(
					'order_id'  => $order->get_id(),
					'item_id'   => $item->get_id(),
					'svg_bytes' => strlen( $svg ),
				)
			);
		}
	}
}
