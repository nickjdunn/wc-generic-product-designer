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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_storefront_cta_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_deferred_designer' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_fallback_start_button' ), 31 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_filter( 'woocommerce_product_supports', array( $this, 'disable_ajax_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'filter_add_to_cart_text' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'filter_add_to_cart_text' ), 10, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'filter_loop_add_to_cart_link' ), 10, 3 );
		add_filter( 'post_class', array( $this, 'add_designer_product_post_class' ), 10, 3 );
	}

	/**
	 * Mark designer products in loops so optional CTA CSS can target them.
	 *
	 * @param array      $classes Post classes.
	 * @param string|array $class   Extra class.
	 * @param int        $post_id Post ID.
	 * @return array
	 */
	public function add_designer_product_post_class( $classes, $class, $post_id ) {
		if ( $post_id && 'product' === get_post_type( $post_id ) && WC_GPD_Product_Meta::is_enabled( $post_id ) ) {
			$classes[] = 'wc-gpd-has-designer';
		}
		return $classes;
	}

	/**
	 * Label for the storefront designer CTA.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function get_cta_label( $product ) {
		if ( ! $product ) {
			return WC_GPD_Settings::start_designing_label();
		}
		if ( $this->get_edit_order_context( $product->get_id() ) ) {
			return __( 'Add to cart', 'woocommerce' );
		}
		if ( $this->get_edit_cart_context( $product->get_id() ) ) {
			return __( 'Update cart with design', 'wc-generic-product-designer' );
		}
		return WC_GPD_Settings::start_designing_label();
	}

	/**
	 * Whether the product should use the start-designing CTA.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	private function product_uses_designer_cta( $product ) {
		return $product && WC_GPD_Product_Meta::is_enabled( $product->get_id() )
			&& ! $this->get_edit_order_context( $product->get_id() );
	}

	/**
	 * Change add-to-cart label on single product and shop loops.
	 *
	 * @param string     $text    Button text.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function filter_add_to_cart_text( $text, $product ) {
		if ( ! $this->product_uses_designer_cta( $product ) ) {
			return $text;
		}
		return $this->get_cta_label( $product );
	}

	/**
	 * Shop/category: link to product page and auto-open the designer.
	 *
	 * @param string     $html    Add to cart anchor HTML.
	 * @param WC_Product $product Product.
	 * @param array      $args    Link args.
	 * @return string
	 */
	public function filter_loop_add_to_cart_link( $html, $product, $args ) {
		if ( ! $this->product_uses_designer_cta( $product ) ) {
			return $html;
		}

		$url = add_query_arg( 'wc_gpd_design', '1', $product->get_permalink() );
		$classes = isset( $args['class'] ) ? $args['class'] : 'button';
		$classes .= ' wc-gpd-start-designing-link';

		return sprintf(
			'<a href="%s" class="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $classes ),
			esc_attr( $this->get_cta_label( $product ) ),
			esc_html( $this->get_cta_label( $product ) )
		);
	}

	/**
	 * Enqueue optional CTA CSS on any page that may show designer product buttons.
	 */
	public function enqueue_storefront_cta_assets() {
		if ( is_admin() ) {
			return;
		}

		$css_block = WC_GPD_Settings::cta_button_css_block();
		if ( '' === $css_block ) {
			return;
		}

		wp_register_style( 'wc-gpd-storefront-cta', false, array(), WC_GPD_VERSION );
		wp_enqueue_style( 'wc-gpd-storefront-cta' );
		wp_add_inline_style( 'wc-gpd-storefront-cta', $css_block );
	}

	/**
	 * Fallback CTA when the theme does not output a standard add-to-cart button.
	 */
	public function render_fallback_start_button() {
		if ( ! $this->is_designer_context() ) {
			return;
		}

		global $product;
		if ( ! $product || ! $this->product_uses_designer_cta( $product ) ) {
			return;
		}

		$auto_open = isset( $_GET['wc_gpd_design'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<p class="wc-gpd-fallback-cta" id="wc-gpd-fallback-cta" hidden>
			<button type="button" class="button alt wc-gpd-fallback-start" id="wc-gpd-fallback-start">
				<?php echo esc_html( $this->get_cta_label( $product ) ); ?>
			</button>
		</p>
		<?php if ( $auto_open ) : ?>
			<span id="wc-gpd-auto-open-flag" hidden aria-hidden="true"></span>
		<?php endif; ?>
		<?php
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
		if ( ! $this->is_designer_context() ) {
			return $classes;
		}

		$classes[] = 'wc-gpd-product';
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
			'wc-gpd-studio-shell',
			WC_GPD_PLUGIN_URL . 'assets/css/studio-shell.css',
			array(),
			WC_GPD_VERSION
		);

		wp_enqueue_style(
			'wc-gpd-designer',
			WC_GPD_PLUGIN_URL . 'assets/css/designer.css',
			array( 'wc-gpd-studio-shell' ),
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

		$template_ref = ! empty( $settings['template_ref'] ) ? absint( $settings['template_ref'] ) : 0;
		WC_GPD_Font_Registry::enqueue_for_designer( $template_ref );

		$diagnostics_enabled = WC_GPD_Settings::is_js_debug_enabled()
			|| current_user_can( 'manage_woocommerce' )
			|| WC_GPD_Sample_Content::is_sample_product( $product_id );

		wp_localize_script(
			'wc-gpd-designer',
			'wcGpdDesigner',
			array(
				'canvasWidth'  => $settings['width'],
				'canvasHeight' => $settings['height'],
				'pluginVersion'      => WC_GPD_VERSION,
				'productId'          => $product_id,
				'templateRef'        => $template_ref,
				'diagnosticsEnabled' => $diagnostics_enabled,
				'isSampleProduct'    => WC_GPD_Sample_Content::is_sample_product( $product_id ),
				'templateUrl'        => $settings['template_url'],
				'templateJson'       => $settings['template_json'],
				'templateViews'      => self::template_views_for_js( $settings ),
				'maxViews'           => $settings['max_views'],
				'productSettings'    => ! empty( $settings['product_settings'] ) ? $settings['product_settings'] : WC_GPD_Product_Settings::get( $product_id ),
				'templatePalettes'   => ! empty( $settings['template_palettes'] ) ? $settings['template_palettes'] : WC_GPD_Design_Template::default_palettes_data(),
				'launchMode'         => 'start_designing',
				'autoOpenDesigner'   => isset( $_GET['wc_gpd_design'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'startDesigningLabel' => WC_GPD_Settings::start_designing_label(),
				'productName'        => get_the_title( $product_id ),
				'productPrice'       => wc_get_product( $product_id ) ? wc_get_product( $product_id )->get_price_html() : '',
				'galleryImages'      => self::get_listing_gallery_images( wc_get_product( $product_id ) ),
				'graphicLibrary'     => self::graphic_library_for_product( $product_id, $settings ),
				'graphicLibraries'   => ! empty( $settings['graphic_libraries'] ) ? $settings['graphic_libraries'] : array(),
				'bootstrapIcons'     => array(
					'featured'    => WC_GPD_Bootstrap_Icons::featured_slugs(),
					'iconBaseUrl' => WC_GPD_PLUGIN_URL . WC_GPD_Bootstrap_Icons::ICONS_DIR . '/',
				),
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
					'layerShape'    => __( 'Shape', 'wc-generic-product-designer' ),
					'layerIcon'     => __( 'Icon', 'wc-generic-product-designer' ),
					'layerGraphic'  => __( 'Graphic', 'wc-generic-product-designer' ),
					'layerImage'    => __( 'Uploaded image', 'wc-generic-product-designer' ),
					'fillColor'     => __( 'Fill', 'wc-generic-product-designer' ),
					'outlineColor'  => __( 'Outline', 'wc-generic-product-designer' ),
					'noGraphicsAvailable' => __( 'No graphics are available yet. Add images under WooCommerce → Graphic Libraries, or attach graphics to this template.', 'wc-generic-product-designer' ),
					'noIconsAvailable' => __( 'Icons are not available. Ensure Bootstrap Icons are bundled with the plugin.', 'wc-generic-product-designer' ),
					'graphicLayerHint' => __( 'Drag to move. Use the corner handles to resize.', 'wc-generic-product-designer' ),
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
					'startDesigning'  => WC_GPD_Settings::start_designing_label(),
					'addToCart'       => __( 'Add to cart', 'wc-generic-product-designer' ),
					'designYourProduct' => __( 'Design your product', 'wc-generic-product-designer' ),
					'closeDesigner'   => __( 'Close designer', 'wc-generic-product-designer' ),
					'addTextShort'    => __( 'Add text', 'wc-generic-product-designer' ),
					'chooseGraphic'   => __( 'Choose graphic', 'wc-generic-product-designer' ),
					'placeholderRequired' => __( 'Fill in the required fields before adding to cart.', 'wc-generic-product-designer' ),
					'layers'          => __( 'Layers', 'wc-generic-product-designer' ),
					'customizeTitle'  => __( 'Customize your design', 'wc-generic-product-designer' ),
					'yourDetails'     => __( 'Your details', 'wc-generic-product-designer' ),
					'selectTextHint'  => __( 'Tap text on the canvas to edit, or add new text below.', 'wc-generic-product-designer' ),
					'panelAdd'        => __( 'Add', 'wc-generic-product-designer' ),
					'panelDetails'    => __( 'Your details', 'wc-generic-product-designer' ),
					'panelContext'    => __( 'Edit', 'wc-generic-product-designer' ),
					'panelLayers'     => __( 'Layers', 'wc-generic-product-designer' ),
					'copyDiagnostics' => __( 'Copy diagnostics', 'wc-generic-product-designer' ),
					'diagnosticsCopied' => __( 'Diagnostics copied to clipboard. Paste this into your support message.', 'wc-generic-product-designer' ),
					'diagnosticsCopyFailed' => __( 'Could not copy diagnostics. Open the browser console and run wcGpdGetDiagnostics().', 'wc-generic-product-designer' ),
					'sampleProductHint' => __( 'Demo product — select each labeled layer to test permissions, then copy diagnostics from the footer.', 'wc-generic-product-designer' ),
				),
				'fonts'        => WC_GPD_Font_Registry::font_families_for_js( $template_ref ),
				'fontOptions'  => WC_GPD_Font_Registry::fonts_for_template( $template_ref ),
				'defaultFont'  => WC_GPD_Font_Registry::default_font_family(),
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
	 * Render hidden designer shell in the footer (opened via Start designing).
	 */
	public function render_deferred_designer() {
		if ( ! $this->is_designer_context() ) {
			return;
		}
		$this->render_designer( 'deferred' );
	}

	/**
	 * Output designer markup.
	 *
	 * @param string $placement gallery|summary.
	 */
	public function render_designer( $placement = 'summary' ) {
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

		$designer_classes = array(
			'wc-gpd-designer',
			'wc-gpd-designer--' . $placement,
			'wc-gpd-modern-studio-root',
		);

		$atc_label = $edit_context
			? __( 'Update cart with design', 'wc-generic-product-designer' )
			: __( 'Add to cart', 'wc-generic-product-designer' );

		$show_diagnostics = WC_GPD_Settings::is_js_debug_enabled()
			|| current_user_can( 'manage_woocommerce' )
			|| WC_GPD_Sample_Content::is_sample_product( $product->get_id() );
		$is_sample_product = WC_GPD_Sample_Content::is_sample_product( $product->get_id() );

		?>
		<div
			class="<?php echo esc_attr( implode( ' ', $designer_classes ) ); ?>"
			id="wc-gpd-designer"
			data-canvas-width="<?php echo esc_attr( (string) $settings['width'] ); ?>"
			data-canvas-height="<?php echo esc_attr( (string) $settings['height'] ); ?>"
			style="--wc-gpd-aspect: <?php echo esc_attr( (string) $aspect ); ?>;"
			aria-hidden="true"
			hidden
		>
			<?php if ( $order_edit ) : ?>
				<p class="wc-gpd-designer__notice wc-gpd-designer__notice--inline" role="status">
					<?php esc_html_e( 'You are editing this order design. Save when finished, then return to the order to download.', 'wc-generic-product-designer' ); ?>
				</p>
			<?php elseif ( $edit_context ) : ?>
				<p class="wc-gpd-designer__notice wc-gpd-designer__notice--inline" role="status">
					<?php esc_html_e( 'You are editing a design from your cart. Update when finished.', 'wc-generic-product-designer' ); ?>
				</p>
			<?php elseif ( $is_sample_product ) : ?>
				<p class="wc-gpd-designer__notice wc-gpd-designer__notice--inline wc-gpd-designer__notice--sample" role="status">
					<?php esc_html_e( 'Demo product — select each labeled layer to test permissions, then copy diagnostics from the footer.', 'wc-generic-product-designer' ); ?>
				</p>
			<?php endif; ?>
			<header class="wc-gpd-studio-chrome" id="wc-gpd-popout-chrome" hidden>
				<div class="wc-gpd-studio-chrome__left">
					<span class="wc-gpd-studio-chrome__product" id="wc-gpd-studio-product-name"><?php echo esc_html( $product->get_name() ); ?></span>
				</div>
				<div class="wc-gpd-studio-chrome__center">
					<div class="wc-gpd-view-switcher" id="wc-gpd-view-switcher" role="tablist" aria-label="<?php esc_attr_e( 'Switch design area', 'wc-generic-product-designer' ); ?>"></div>
				</div>
				<div class="wc-gpd-studio-chrome__right">
					<button type="button" class="wc-gpd-studio-chrome__close" id="wc-gpd-popout-close" aria-label="<?php esc_attr_e( 'Close designer', 'wc-generic-product-designer' ); ?>">&times;</button>
				</div>
			</header>
			<div class="wc-gpd-modern-studio" id="wc-gpd-studio">
				<nav class="wc-gpd-studio-nav" id="wc-gpd-studio-nav" aria-label="<?php esc_attr_e( 'Designer tools', 'wc-generic-product-designer' ); ?>">
					<button type="button" class="wc-gpd-studio-nav__btn wc-gpd-studio-nav__btn--add is-active" data-section="add" id="wc-gpd-nav-add">
						<span class="wc-gpd-studio-nav__icon" aria-hidden="true">+</span>
						<span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Add', 'wc-generic-product-designer' ); ?></span>
					</button>
					<button type="button" class="wc-gpd-studio-nav__btn" data-section="layers" id="wc-gpd-nav-layers">
						<span class="wc-gpd-studio-nav__icon" aria-hidden="true">☰</span>
						<span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Layers', 'wc-generic-product-designer' ); ?></span>
					</button>
					<button type="button" class="wc-gpd-studio-nav__btn" data-section="details" id="wc-gpd-nav-details" hidden>
						<span class="wc-gpd-studio-nav__icon" aria-hidden="true">✎</span>
						<span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Details', 'wc-generic-product-designer' ); ?></span>
					</button>
					<button type="button" class="wc-gpd-studio-nav__btn" data-section="context" id="wc-gpd-nav-context" hidden>
						<span class="wc-gpd-studio-nav__icon" aria-hidden="true">✎</span>
						<span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Edit', 'wc-generic-product-designer' ); ?></span>
					</button>
				</nav>
				<aside class="wc-gpd-studio-drawer" id="wc-gpd-studio-panel">
					<div class="wc-gpd-studio-drawer__head">
						<h2 class="wc-gpd-studio-drawer__title" id="wc-gpd-studio-drawer-title"><?php esc_html_e( 'Add', 'wc-generic-product-designer' ); ?></h2>
					</div>
					<div class="wc-gpd-studio-drawer__body">
						<div class="wc-gpd-studio-panel-section is-active" data-section="add" id="wc-gpd-section-add">
							<p class="wc-gpd-tpl-panel-desc"><?php esc_html_e( 'Add elements to your design.', 'wc-generic-product-designer' ); ?></p>
							<p class="wc-gpd-add-empty" id="wc-gpd-add-empty" hidden><?php esc_html_e( 'Adding custom elements is disabled for this product.', 'wc-generic-product-designer' ); ?></p>
							<div class="wc-gpd-add-menu wc-gpd-add-menu--collapsible" id="wc-gpd-add-menu">
								<div class="wc-gpd-add-menu__group" data-add-group="text" hidden>
									<button type="button" class="wc-gpd-add-menu__toggle" aria-expanded="false"><?php esc_html_e( 'Text', 'wc-generic-product-designer' ); ?></button>
									<div class="wc-gpd-add-menu__body" hidden>
										<button type="button" class="button button-small wc-gpd-add-menu__btn wc-gpd-tool-btn wc-gpd-tool-btn--add" id="wc-gpd-add-text"><?php esc_html_e( 'Add text', 'wc-generic-product-designer' ); ?></button>
									</div>
								</div>
								<div class="wc-gpd-add-menu__group" data-add-group="shape" hidden>
									<button type="button" class="wc-gpd-add-menu__toggle" aria-expanded="false"><?php esc_html_e( 'Shapes', 'wc-generic-product-designer' ); ?></button>
									<div class="wc-gpd-add-menu__body" hidden>
										<button type="button" class="button button-small wc-gpd-add-menu__btn wc-gpd-tool-btn wc-gpd-tool-btn--add" id="wc-gpd-add-shape"><?php esc_html_e( 'Add rectangle', 'wc-generic-product-designer' ); ?></button>
									</div>
								</div>
								<div class="wc-gpd-add-menu__group" data-add-group="graphic" hidden>
									<button type="button" class="wc-gpd-add-menu__toggle" aria-expanded="false"><?php esc_html_e( 'Graphics', 'wc-generic-product-designer' ); ?></button>
									<div class="wc-gpd-add-menu__body" hidden>
										<div class="wc-gpd-add-graphic-library" id="wc-gpd-add-graphic-library" role="group" aria-label="<?php esc_attr_e( 'Choose a graphic', 'wc-generic-product-designer' ); ?>"></div>
									</div>
								</div>
								<div class="wc-gpd-add-menu__group" data-add-group="image" hidden>
									<button type="button" class="wc-gpd-add-menu__toggle" aria-expanded="false"><?php esc_html_e( 'Images', 'wc-generic-product-designer' ); ?></button>
									<div class="wc-gpd-add-menu__body" hidden>
										<button type="button" class="button button-small wc-gpd-add-menu__btn wc-gpd-tool-btn wc-gpd-tool-btn--add" id="wc-gpd-add-image"><?php esc_html_e( 'Upload image', 'wc-generic-product-designer' ); ?></button>
										<input type="file" id="wc-gpd-add-image-file" accept="image/png,image/jpeg,image/webp,image/gif" hidden />
									</div>
								</div>
								<div class="wc-gpd-add-menu__group" data-add-group="icon" hidden>
									<button type="button" class="wc-gpd-add-menu__toggle" aria-expanded="false"><?php esc_html_e( 'Icons', 'wc-generic-product-designer' ); ?></button>
									<div class="wc-gpd-add-menu__body" hidden>
										<div class="wc-gpd-add-icon-picker" id="wc-gpd-add-icon-picker" role="group" aria-label="<?php esc_attr_e( 'Choose an icon', 'wc-generic-product-designer' ); ?>"></div>
									</div>
								</div>
							</div>
						</div>
						<div class="wc-gpd-studio-panel-section" data-section="details" id="wc-gpd-section-details" hidden>
							<div class="wc-gpd-placeholder-fields" id="wc-gpd-placeholder-fields"></div>
							<div class="wc-gpd-graphic-pickers" id="wc-gpd-graphic-pickers"></div>
						</div>
						<div class="wc-gpd-studio-panel-section" data-section="context" id="wc-gpd-section-context" hidden>
							<p class="wc-gpd-context-empty" id="wc-gpd-context-empty"><?php esc_html_e( 'Select a layer on the canvas to edit its properties.', 'wc-generic-product-designer' ); ?></p>
							<div class="wc-gpd-context-pane" id="wc-gpd-context-pane" hidden>
								<p class="wc-gpd-context-layer-name" id="wc-gpd-context-layer-name"></p>
								<p class="wc-gpd-context-hint wc-gpd-control-graphic" id="wc-gpd-context-graphic-hint" data-customer-context="graphic" hidden><?php esc_html_e( 'Drag to move. Use the corner handles to resize.', 'wc-generic-product-designer' ); ?></p>
								<div class="wc-gpd-tools-panel wc-gpd-tools-panel--rows" id="wc-gpd-tools-panel">
									<div class="wc-gpd-prop-row" data-customer-context="text">
										<label class="wc-gpd-prop-label" for="wc-gpd-font-family"><?php esc_html_e( 'Font', 'wc-generic-product-designer' ); ?></label>
										<select id="wc-gpd-font-family" class="wc-gpd-prop-control wc-gpd-tool-select"></select>
									</div>
									<div class="wc-gpd-prop-row" data-customer-context="text">
										<label class="wc-gpd-prop-label" for="wc-gpd-font-size"><?php esc_html_e( 'Font size', 'wc-generic-product-designer' ); ?></label>
										<input type="number" id="wc-gpd-font-size" class="wc-gpd-prop-control wc-gpd-tool-size" min="8" max="400" step="1" value="32" />
									</div>
									<div class="wc-gpd-prop-row" data-customer-context="text">
										<span class="wc-gpd-prop-label"><?php esc_html_e( 'Style', 'wc-generic-product-designer' ); ?></span>
										<div class="wc-gpd-prop-btn-group" role="group" aria-label="<?php esc_attr_e( 'Text style', 'wc-generic-product-designer' ); ?>">
											<button type="button" class="wc-gpd-tool-toggle" id="wc-gpd-bold-btn" data-prop="bold" title="<?php esc_attr_e( 'Bold', 'wc-generic-product-designer' ); ?>"><strong>B</strong></button>
											<button type="button" class="wc-gpd-tool-toggle" id="wc-gpd-italic-btn" data-prop="italic" title="<?php esc_attr_e( 'Italic', 'wc-generic-product-designer' ); ?>"><em>I</em></button>
											<button type="button" class="wc-gpd-tool-toggle" id="wc-gpd-underline-btn" data-prop="underline" title="<?php esc_attr_e( 'Underline', 'wc-generic-product-designer' ); ?>"><span class="wc-gpd-u">U</span></button>
										</div>
									</div>
									<div class="wc-gpd-prop-row" data-customer-context="text">
										<span class="wc-gpd-prop-label"><?php esc_html_e( 'Alignment', 'wc-generic-product-designer' ); ?></span>
										<div class="wc-gpd-prop-btn-group wc-gpd-control-align" role="group" aria-label="<?php esc_attr_e( 'Alignment', 'wc-generic-product-designer' ); ?>">
											<button type="button" class="wc-gpd-align wc-gpd-tool-toggle" data-align="left" aria-pressed="true" title="<?php esc_attr_e( 'Align left', 'wc-generic-product-designer' ); ?>">L</button>
											<button type="button" class="wc-gpd-align wc-gpd-tool-toggle" data-align="center" aria-pressed="false" title="<?php esc_attr_e( 'Align center', 'wc-generic-product-designer' ); ?>">C</button>
											<button type="button" class="wc-gpd-align wc-gpd-tool-toggle" data-align="right" aria-pressed="false" title="<?php esc_attr_e( 'Align right', 'wc-generic-product-designer' ); ?>">R</button>
										</div>
									</div>
									<div class="wc-gpd-prop-row wc-gpd-control-text-color">
										<span class="wc-gpd-prop-label"><?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?></span>
										<div class="wc-gpd-tool-group wc-gpd-color-swatches" id="wc-gpd-color-swatches" role="group" aria-label="<?php esc_attr_e( 'Palette colors', 'wc-generic-product-designer' ); ?>"></div>
										<input type="color" id="wc-gpd-text-color" class="wc-gpd-prop-color wc-gpd-control-color-picker" value="#000000" title="<?php esc_attr_e( 'Pick any color', 'wc-generic-product-designer' ); ?>" />
									</div>
									<div class="wc-gpd-prop-row wc-gpd-control-line-height" data-customer-context="text">
										<label class="wc-gpd-prop-label" for="wc-gpd-line-height"><?php esc_html_e( 'Line height', 'wc-generic-product-designer' ); ?></label>
										<input type="number" id="wc-gpd-line-height" class="wc-gpd-prop-control wc-gpd-tool-mini" min="0.5" max="3" step="0.05" value="1.16" />
									</div>
									<div class="wc-gpd-prop-row wc-gpd-control-letter-spacing" data-customer-context="text">
										<label class="wc-gpd-prop-label" for="wc-gpd-letter-spacing"><?php esc_html_e( 'Letter spacing', 'wc-generic-product-designer' ); ?></label>
										<input type="number" id="wc-gpd-letter-spacing" class="wc-gpd-prop-control wc-gpd-tool-mini" min="-50" max="200" step="1" value="0" />
									</div>
								</div>
								<input type="checkbox" id="wc-gpd-bold" class="screen-reader-text" tabindex="-1" aria-hidden="true" />
								<input type="checkbox" id="wc-gpd-italic" class="screen-reader-text" tabindex="-1" aria-hidden="true" />
								<input type="checkbox" id="wc-gpd-underline" class="screen-reader-text" tabindex="-1" aria-hidden="true" />
							</div>
						</div>
						<div class="wc-gpd-studio-panel-section" data-section="layers" hidden>
							<ul class="wc-gpd-tpl-layers-list wc-gpd-customer-layers-list" id="wc-gpd-layers-list"></ul>
						</div>
					</div>
				</aside>
				<main class="wc-gpd-studio-canvas-area">
					<div class="wc-gpd-designer__canvas-stage">
						<div class="wc-gpd-designer__canvas-wrap">
							<canvas id="wc-gpd-canvas" aria-label="<?php esc_attr_e( 'Design canvas', 'wc-generic-product-designer' ); ?>"></canvas>
						</div>
					</div>
				</main>
			</div>
			<footer class="wc-gpd-studio-footer" id="wc-gpd-studio-footer">
				<div class="wc-gpd-studio-footer__left">
					<div class="wc-gpd-studio-footer__price" id="wc-gpd-studio-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
					<?php if ( $show_diagnostics ) : ?>
						<button type="button" class="wc-gpd-studio-footer__diagnostics" id="wc-gpd-copy-diagnostics" title="<?php esc_attr_e( 'Copy a JSON report for troubleshooting', 'wc-generic-product-designer' ); ?>">
							<?php esc_html_e( 'Copy diagnostics', 'wc-generic-product-designer' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<button type="button" class="wc-gpd-studio-footer__atc" id="wc-gpd-designer-atc"><?php echo esc_html( $atc_label ); ?></button>
			</footer>
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
	 * Product listing/gallery images for the photos modal.
	 *
	 * @param WC_Product|false $product Product.
	 * @return array<int,array{src:string,thumb:string,alt:string}>
	 */
	public static function get_listing_gallery_images( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return array();
		}

		$ids = array();
		if ( $product->get_image_id() ) {
			$ids[] = absint( $product->get_image_id() );
		}
		foreach ( $product->get_gallery_image_ids() as $gallery_id ) {
			$gallery_id = absint( $gallery_id );
			if ( $gallery_id && ! in_array( $gallery_id, $ids, true ) ) {
				$ids[] = $gallery_id;
			}
		}

		$images = array();
		foreach ( $ids as $image_id ) {
			$src   = wp_get_attachment_image_url( $image_id, 'full' );
			$thumb = wp_get_attachment_image_url( $image_id, 'woocommerce_single' );
			if ( ! $src ) {
				continue;
			}
			$images[] = array(
				'src'   => $src,
				'thumb' => $thumb ? $thumb : $src,
				'alt'   => trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ),
			);
		}

		return $images;
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

	/**
	 * Graphic library items for the storefront Add menu.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $settings   Resolved product settings.
	 * @return array<int,array{id:int,url:string,title:string}>
	 */
	private static function graphic_library_for_product( $product_id, $settings ) {
		$library = ! empty( $settings['graphic_library'] ) && is_array( $settings['graphic_library'] )
			? $settings['graphic_library']
			: array();

		if ( ! empty( $library ) ) {
			return $library;
		}

		if ( WC_GPD_Sample_Content::is_sample_product( $product_id ) ) {
			return self::bundled_demo_graphics();
		}

		return array();
	}

	/**
	 * Bundled demo graphics for the sample product when no media library items exist.
	 *
	 * @return array<int,array{id:int,url:string,title:string}>
	 */
	private static function bundled_demo_graphics() {
		return array(
			array(
				'id'    => 0,
				'url'   => WC_GPD_PLUGIN_URL . 'assets/demo/gpd-demo-graphic.svg',
				'title' => __( 'Demo star', 'wc-generic-product-designer' ),
			),
		);
	}
}
