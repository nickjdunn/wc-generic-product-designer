<?php
/**
 * Frontend canvas and asset loading.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend designer UI.
 */
class WC_GPD_Frontend implements WC_GPD_Module {

	/**
	 * @var WC_GPD_Frontend|null
	 */
	private static $instance = null;

	const NONCE_ACTION = 'wc_gpd_add_to_cart';
	const NONCE_NAME   = 'wc_gpd_add_to_cart_nonce';

	/**
	 * @var bool
	 */
	private $designer_rendered = false;

	/**
	 * @return WC_GPD_Frontend
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
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_designer' ), 5 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_filter( 'woocommerce_product_supports', array( $this, 'disable_ajax_add_to_cart' ), 10, 3 );
	}

	/**
	 * Force standard form POST on designer products (not AJAX from archive handlers).
	 *
	 * @param bool       $supports  Whether product supports feature.
	 * @param string     $feature   Feature name.
	 * @param WC_Product $product   Product.
	 * @return bool
	 */
	public function disable_ajax_add_to_cart( $supports, $feature, $product ) {
		if ( 'ajax_add_to_cart' === $feature && $product && WC_GPD_Product_Meta::is_enabled( $product->get_id() ) ) {
			return false;
		}
		return $supports;
	}

	/**
	 * Hide default gallery via CSS when designer is active on this product.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		if ( $this->is_designer_context() ) {
			$classes[] = 'wc-gpd-product';
		}
		return $classes;
	}

	/**
	 * Whether the current product has an active designer.
	 *
	 * @return bool
	 */
	private function is_designer_context() {
		if ( ! is_product() ) {
			return false;
		}
		$product_id = get_queried_object_id();
		return $product_id && WC_GPD_Product_Meta::is_enabled( $product_id );
	}

	/**
	 * Enqueue Fabric.js and plugin assets on eligible product pages.
	 */
	public function maybe_enqueue_assets() {
		if ( ! $this->is_designer_context() ) {
			return;
		}

		$product_id   = get_queried_object_id();
		$settings     = WC_GPD_Product_Meta::get_settings( $product_id );
		$edit_context = $this->get_edit_cart_context( $product_id );
		$order_edit   = $this->get_edit_order_context( $product_id );
		if ( $order_edit ) {
			$edit_context = $order_edit;
		}

		wp_enqueue_style(
			'wc-gpd-designer',
			WC_GPD_PLUGIN_URL . 'assets/css/designer.css',
			array(),
			WC_GPD_VERSION
		);

		wp_enqueue_script(
			'fabric-js',
			'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js',
			array(),
			'5.3.1',
			true
		);

		wp_enqueue_script(
			'wc-gpd-debug',
			WC_GPD_PLUGIN_URL . 'assets/js/debug.js',
			array(),
			WC_GPD_VERSION,
			true
		);

		wp_enqueue_script(
			'wc-gpd-designer-popout',
			WC_GPD_PLUGIN_URL . 'assets/js/designer-popout.js',
			array(),
			WC_GPD_VERSION,
			true
		);

		wp_enqueue_script(
			'wc-gpd-designer',
			WC_GPD_PLUGIN_URL . 'assets/js/designer.js',
			array( 'fabric-js', 'wc-gpd-debug', 'wc-gpd-designer-popout' ),
			WC_GPD_VERSION,
			true
		);

		wp_localize_script(
			'wc-gpd-designer',
			'wcGpdDesigner',
			array(
				'canvasWidth'  => $settings['width'],
				'canvasHeight' => $settings['height'],
				'templateUrl'        => $settings['template_url'],
				'templateJson'       => $settings['template_json'],
				'templateViews'      => self::template_views_for_js( $settings ),
				'maxViews'           => $settings['max_views'],
				'productSettings'    => ! empty( $settings['product_settings'] ) ? $settings['product_settings'] : WC_GPD_Product_Settings::get( $product_id ),
				'debug'              => WC_GPD_Settings::is_js_debug_enabled(),
				'nonce'              => wp_create_nonce( self::NONCE_ACTION ),
				'nonceName'          => self::NONCE_NAME,
				'editCartKey'        => ( $edit_context && ! empty( $edit_context['cart_item_key'] ) ) ? $edit_context['cart_item_key'] : '',
				'existingDesignSvg'  => $edit_context ? $edit_context['svg'] : '',
				'existingDesignJson' => $edit_context && ! empty( $edit_context['json'] ) ? $edit_context['json'] : '',
				'isEditing'          => (bool) $edit_context,
				'orderEdit'          => (bool) ( $order_edit ?? false ),
				'orderId'            => $order_edit ? (int) $order_edit['order_id'] : 0,
				'orderItemId'        => $order_edit ? (int) $order_edit['item_id'] : 0,
				'orderSaveNonce'     => $order_edit ? wp_create_nonce( WC_GPD_Admin_Order::NONCE_SAVE_ORDER . '_' . $order_edit['order_id'] . '_' . $order_edit['item_id'] ) : '',
				'orderSaveAction'    => WC_GPD_Admin_Order::SAVE_ORDER_ACTION,
				'adminPostUrl'       => admin_url( 'admin-post.php' ),
				'i18n'         => array(
					'addText'       => __( 'Add text layer', 'wc-generic-product-designer' ),
					'selectLayer'   => __( 'Select a text layer on the canvas to edit it.', 'wc-generic-product-designer' ),
					'fontFamily'    => __( 'Font family', 'wc-generic-product-designer' ),
					'fontSize'      => __( 'Font size', 'wc-generic-product-designer' ),
					'bold'          => __( 'Bold', 'wc-generic-product-designer' ),
					'italic'        => __( 'Italic', 'wc-generic-product-designer' ),
					'alignLeft'     => __( 'Align left', 'wc-generic-product-designer' ),
					'alignCenter'   => __( 'Align center', 'wc-generic-product-designer' ),
					'alignRight'    => __( 'Align right', 'wc-generic-product-designer' ),
					'layerRequired' => __( 'Add at least one text layer before adding to cart.', 'wc-generic-product-designer' ),
					'exportError'   => __( 'Could not export your design. Please try again.', 'wc-generic-product-designer' ),
					'editingNotice' => __( 'You are editing a design from your cart. Update when finished.', 'wc-generic-product-designer' ),
					'updateCart'    => __( 'Update cart with design', 'wc-generic-product-designer' ),
					'loadDesignError' => __( 'Could not load your saved design. Please create a new one.', 'wc-generic-product-designer' ),
					'layersTitle'   => __( 'Layers', 'wc-generic-product-designer' ),
					'layerText'     => __( 'Text layer', 'wc-generic-product-designer' ),
					'bringForward'  => __( 'Bring forward', 'wc-generic-product-designer' ),
					'sendBackward'  => __( 'Send backward', 'wc-generic-product-designer' ),
					'deleteLayer'   => __( 'Delete layer', 'wc-generic-product-designer' ),
					'noLayers'        => __( 'No layers yet. Add a text layer to begin.', 'wc-generic-product-designer' ),
					'designArea'      => __( 'Design area', 'wc-generic-product-designer' ),
					'switchArea'      => __( 'Switch design area', 'wc-generic-product-designer' ),
					'orderEditNotice' => __( 'You are editing this order design. Save when finished, then return to the order to download.', 'wc-generic-product-designer' ),
					'saveOrderDesign' => __( 'Save design to order', 'wc-generic-product-designer' ),
					'orderSaved'      => __( 'Design saved to order.', 'wc-generic-product-designer' ),
					'textColor'       => __( 'Text color', 'wc-generic-product-designer' ),
					'underline'       => __( 'Underline', 'wc-generic-product-designer' ),
					'lineHeight'      => __( 'Line height', 'wc-generic-product-designer' ),
					'letterSpacing'   => __( 'Letter spacing', 'wc-generic-product-designer' ),
					'expandDesigner'  => __( 'Expand designer', 'wc-generic-product-designer' ),
				),
				'fonts'        => array(
					'Arial, Helvetica, sans-serif',
					'Georgia, serif',
					'"Times New Roman", Times, serif',
					'Impact, Charcoal, sans-serif',
					'Courier, "Courier New", monospace',
					'Verdana, Geneva, sans-serif',
				),
			)
		);

		WC_GPD_Logger::debug(
			'Designer assets enqueued',
			array(
				'product_id' => $product_id,
				'js_debug'   => WC_GPD_Settings::is_js_debug_enabled(),
			)
		);
	}

	/**
	 * Output designer markup before add to cart.
	 */
	public function render_designer() {
		if ( $this->designer_rendered || ! $this->is_designer_context() ) {
			return;
		}

		global $product;
		if ( ! $product ) {
			return;
		}

		$settings     = WC_GPD_Product_Meta::get_settings( $product->get_id() );
		$edit_context = $this->get_edit_cart_context( $product->get_id() );
		$order_edit   = $this->get_edit_order_context( $product->get_id() );
		if ( $order_edit ) {
			$edit_context = $order_edit;
		}
		$this->designer_rendered = true;

		WC_GPD_Logger::debug(
			'Designer UI rendered',
			array( 'product_id' => $product->get_id() )
		);

		$aspect = $settings['height'] > 0
			? ( $settings['width'] / $settings['height'] )
			: ( 4 / 3 );

		?>
		<div
			class="wc-gpd-designer"
			id="wc-gpd-designer"
			data-canvas-width="<?php echo esc_attr( (string) $settings['width'] ); ?>"
			data-canvas-height="<?php echo esc_attr( (string) $settings['height'] ); ?>"
			style="--wc-gpd-aspect: <?php echo esc_attr( (string) $aspect ); ?>;"
			role="region"
			aria-label="<?php esc_attr_e( 'Product designer', 'wc-generic-product-designer' ); ?>"
		>
			<?php if ( $order_edit ) : ?>
				<p class="wc-gpd-designer__notice" role="status">
					<?php esc_html_e( 'You are editing this order design. Save when finished, then return to the order to download.', 'wc-generic-product-designer' ); ?>
				</p>
				<?php if ( isset( $_GET['wc_gpd_order_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<p class="wc-gpd-designer__notice wc-gpd-designer__notice--success" role="status">
						<?php esc_html_e( 'Design saved to order.', 'wc-generic-product-designer' ); ?>
					</p>
				<?php endif; ?>
			<?php elseif ( $edit_context ) : ?>
				<p class="wc-gpd-designer__notice" role="status">
					<?php esc_html_e( 'You are editing a design from your cart. Update when finished.', 'wc-generic-product-designer' ); ?>
				</p>
			<?php endif; ?>
			<div class="wc-gpd-designer__layout">
				<div class="wc-gpd-designer__sidebar">
				<div class="wc-gpd-designer__toolbar" aria-label="<?php esc_attr_e( 'Design tools', 'wc-generic-product-designer' ); ?>">
					<div class="wc-gpd-designer__toolbar-row">
						<button type="button" class="button wc-gpd-btn-add-text" id="wc-gpd-add-text">
							<?php esc_html_e( 'Add text layer', 'wc-generic-product-designer' ); ?>
						</button>
					</div>
					<p class="wc-gpd-designer__hint" id="wc-gpd-hint">
						<?php esc_html_e( 'Select a text layer on the canvas to edit it.', 'wc-generic-product-designer' ); ?>
					</p>
					<fieldset class="wc-gpd-designer__controls" id="wc-gpd-controls" disabled>
						<legend class="screen-reader-text"><?php esc_html_e( 'Text layer properties', 'wc-generic-product-designer' ); ?></legend>
						<label for="wc-gpd-font-family">
							<?php esc_html_e( 'Font family', 'wc-generic-product-designer' ); ?>
							<select id="wc-gpd-font-family" name="wc_gpd_font_family"></select>
						</label>
						<label for="wc-gpd-font-size">
							<?php esc_html_e( 'Font size', 'wc-generic-product-designer' ); ?>
							<input type="number" id="wc-gpd-font-size" min="8" max="400" step="1" value="32" />
						</label>
						<div class="wc-gpd-designer__style-row">
							<label>
								<input type="checkbox" id="wc-gpd-bold" />
								<?php esc_html_e( 'Bold', 'wc-generic-product-designer' ); ?>
							</label>
							<label>
								<input type="checkbox" id="wc-gpd-italic" />
								<?php esc_html_e( 'Italic', 'wc-generic-product-designer' ); ?>
							</label>
						</div>
						<label for="wc-gpd-text-color" class="wc-gpd-control-text-color" id="wc-gpd-text-color-wrap">
							<?php esc_html_e( 'Text color', 'wc-generic-product-designer' ); ?>
							<input type="color" id="wc-gpd-text-color" value="#000000" />
						</label>
						<label for="wc-gpd-line-height" class="wc-gpd-control-line-height">
							<?php esc_html_e( 'Line height', 'wc-generic-product-designer' ); ?>
							<input type="number" id="wc-gpd-line-height" min="0.5" max="3" step="0.05" value="1.16" />
						</label>
						<label for="wc-gpd-letter-spacing" class="wc-gpd-control-letter-spacing">
							<?php esc_html_e( 'Letter spacing', 'wc-generic-product-designer' ); ?>
							<input type="number" id="wc-gpd-letter-spacing" min="-50" max="200" step="1" value="0" />
						</label>
						<label class="wc-gpd-control-underline">
							<input type="checkbox" id="wc-gpd-underline" />
							<?php esc_html_e( 'Underline', 'wc-generic-product-designer' ); ?>
						</label>
						<div class="wc-gpd-designer__align-row wc-gpd-control-align" role="group" aria-label="<?php esc_attr_e( 'Text alignment', 'wc-generic-product-designer' ); ?>">
							<button type="button" class="button wc-gpd-align" data-align="left" aria-pressed="true"><?php esc_html_e( 'Left', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button wc-gpd-align" data-align="center" aria-pressed="false"><?php esc_html_e( 'Center', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button wc-gpd-align" data-align="right" aria-pressed="false"><?php esc_html_e( 'Right', 'wc-generic-product-designer' ); ?></button>
						</div>
					</fieldset>
				</div>
				<div class="wc-gpd-designer__layers" aria-label="<?php esc_attr_e( 'Design layers', 'wc-generic-product-designer' ); ?>">
					<h3 class="wc-gpd-designer__layers-title"><?php esc_html_e( 'Layers', 'wc-generic-product-designer' ); ?></h3>
					<ul class="wc-gpd-layers-list" id="wc-gpd-layers-list"></ul>
					<div class="wc-gpd-layers-actions">
						<button type="button" class="button button-small" id="wc-gpd-layer-forward"><?php esc_html_e( 'Bring forward', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button button-small" id="wc-gpd-layer-backward"><?php esc_html_e( 'Send backward', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button button-small" id="wc-gpd-layer-delete"><?php esc_html_e( 'Delete layer', 'wc-generic-product-designer' ); ?></button>
					</div>
				</div>
				</div>
				<div class="wc-gpd-designer__canvas-column">
					<div class="wc-gpd-designer__canvas-header">
						<div
							class="wc-gpd-view-switcher"
							id="wc-gpd-view-switcher"
							role="tablist"
							aria-label="<?php esc_attr_e( 'Switch design area', 'wc-generic-product-designer' ); ?>"
						></div>
						<button type="button" class="button wc-gpd-popout-trigger" id="wc-gpd-popout-btn">
							<?php esc_html_e( 'Expand designer', 'wc-generic-product-designer' ); ?>
						</button>
					</div>
					<div class="wc-gpd-designer__canvas-wrap">
						<canvas id="wc-gpd-canvas" aria-label="<?php esc_attr_e( 'Design canvas', 'wc-generic-product-designer' ); ?>"></canvas>
					</div>
				</div>
			</div>
			<input type="hidden" name="wc_gpd_design_svg" id="wc-gpd-design-svg" value="" />
			<input type="hidden" name="wc_gpd_design_json" id="wc-gpd-design-json" value="" />
			<input type="hidden" name="wc_gpd_preview_image" id="wc-gpd-preview-image" value="" />
			<?php if ( $edit_context && ! empty( $edit_context['cart_item_key'] ) ) : ?>
				<input type="hidden" name="wc_gpd_edit_cart_key" id="wc-gpd-edit-cart-key" value="<?php echo esc_attr( $edit_context['cart_item_key'] ); ?>" />
			<?php endif; ?>
			<?php if ( $order_edit ) : ?>
				<p class="wc-gpd-designer__order-save">
					<button type="button" class="button button-primary" id="wc-gpd-save-order-design">
						<?php esc_html_e( 'Save design to order', 'wc-generic-product-designer' ); ?>
					</button>
				</p>
			<?php endif; ?>
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
		</div>
		<?php
	}

	/**
	 * Load cart design for editing when ?wc_gpd_edit= cart key is present.
	 *
	 * @param int $product_id Product ID.
	 * @return array{cart_item_key:string,svg:string,json:string}|null
	 */
	private function get_edit_cart_context( $product_id ) {
		if ( ! $product_id || ! isset( $_GET['wc_gpd_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		if ( ! WC()->cart ) {
			return null;
		}

		$cart_item_key = sanitize_text_field( wp_unslash( $_GET['wc_gpd_edit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $cart_item_key ) {
			return null;
		}

		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		if ( ! $cart_item || (int) $cart_item['product_id'] !== (int) $product_id ) {
			return null;
		}

		if ( empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] ) ) {
			return null;
		}

		$svg = WC_GPD_SVG_Sanitizer::sanitize( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_SVG ] );
		if ( ! $svg ) {
			return null;
		}

		return array(
			'cart_item_key' => $cart_item_key,
			'svg'           => $svg,
			'json'          => ! empty( $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_JSON ] )
				? $cart_item[ WC_GPD_Product_Meta::CART_KEY_DESIGN_JSON ]
				: '',
		);
	}

	/**
	 * Load order line design for admin editing.
	 *
	 * @param int $product_id Product ID.
	 * @return array{order_id:int,item_id:int,svg:string,json:string}|null
	 */
	private function get_edit_order_context( $product_id ) {
		if ( ! $product_id || ! isset( $_GET['wc_gpd_edit_order'], $_GET['wc_gpd_edit_item'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return null;
		}

		$order_id = absint( $_GET['wc_gpd_edit_order'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$item_id  = absint( $_GET['wc_gpd_edit_item'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $order_id || ! $item_id ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		$item  = $order ? $order->get_item( $item_id ) : null;
		if ( ! $item instanceof WC_Order_Item_Product || (int) $item->get_product_id() !== (int) $product_id ) {
			return null;
		}

		$svg = WC_GPD_SVG_Sanitizer::sanitize( $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, true ) );
		if ( ! $svg ) {
			return null;
		}

		return array(
			'order_id' => $order_id,
			'item_id'  => $item_id,
			'svg'      => $svg,
			'json'     => $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_JSON, true ),
		);
	}

	/**
	 * Build template view payloads for the storefront designer.
	 *
	 * @param array $settings Product designer settings.
	 * @return array<int,array<string,mixed>>
	 */
	public static function template_views_for_js( $settings ) {
		$views    = array();
		$fallback = ! empty( $settings['template_url'] ) ? $settings['template_url'] : '';
		$parsed   = isset( $settings['template_views'] ) && is_array( $settings['template_views'] )
			? $settings['template_views']
			: WC_GPD_Template_Json::empty_document();

		if ( empty( $parsed['views'] ) || ! is_array( $parsed['views'] ) ) {
			return array(
				array(
					'id'              => 'view_front',
					'label'           => __( 'Front', 'wc-generic-product-designer' ),
					'templateUrl'     => $fallback,
					'boundingBoxUid'  => '',
					'objects'         => array(),
				),
			);
		}

		foreach ( $parsed['views'] as $view ) {
			if ( ! is_array( $view ) || empty( $view['id'] ) ) {
				continue;
			}

			$image_id = ! empty( $view['template_image_id'] ) ? absint( $view['template_image_id'] ) : 0;
			if ( ! $image_id && ! empty( $settings['template_id'] ) ) {
				$image_id = absint( $settings['template_id'] );
			}

			$url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : $fallback;

			$views[] = array(
				'id'             => sanitize_key( (string) $view['id'] ),
				'label'          => ! empty( $view['label'] ) ? sanitize_text_field( (string) $view['label'] ) : sanitize_key( (string) $view['id'] ),
				'templateUrl'    => $url ? $url : '',
				'boundingBoxUid' => ! empty( $view['bounding_box_uid'] ) ? sanitize_text_field( (string) $view['bounding_box_uid'] ) : '',
				'objects'        => ! empty( $view['objects'] ) && is_array( $view['objects'] ) ? $view['objects'] : array(),
			);
		}

		return $views;
	}
}
