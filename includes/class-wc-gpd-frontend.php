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
		if ( ! $product_id || ! WC_GPD_Product_Meta::is_enabled( $product_id ) ) {
			return false;
		}
		$settings = WC_GPD_Product_Meta::get_settings( $product_id );
		return ! empty( $settings['template_url'] );
	}

	/**
	 * Enqueue Fabric.js and plugin assets on eligible product pages.
	 */
	public function maybe_enqueue_assets() {
		if ( ! $this->is_designer_context() ) {
			return;
		}

		$product_id = get_queried_object_id();
		$settings     = WC_GPD_Product_Meta::get_settings( $product_id );

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
			'wc-gpd-designer',
			WC_GPD_PLUGIN_URL . 'assets/js/designer.js',
			array( 'fabric-js', 'wc-gpd-debug' ),
			WC_GPD_VERSION,
			true
		);

		wp_localize_script(
			'wc-gpd-designer',
			'wcGpdDesigner',
			array(
				'canvasWidth'  => $settings['width'],
				'canvasHeight' => $settings['height'],
				'templateUrl'  => $settings['template_url'],
				'debug'          => WC_GPD_Settings::is_js_debug_enabled(),
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'nonceName'    => self::NONCE_NAME,
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

		$settings = WC_GPD_Product_Meta::get_settings( $product->get_id() );
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
			<div class="wc-gpd-designer__layout">
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
						<div class="wc-gpd-designer__align-row" role="group" aria-label="<?php esc_attr_e( 'Text alignment', 'wc-generic-product-designer' ); ?>">
							<button type="button" class="button wc-gpd-align" data-align="left" aria-pressed="true"><?php esc_html_e( 'Left', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button wc-gpd-align" data-align="center" aria-pressed="false"><?php esc_html_e( 'Center', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button wc-gpd-align" data-align="right" aria-pressed="false"><?php esc_html_e( 'Right', 'wc-generic-product-designer' ); ?></button>
						</div>
					</fieldset>
				</div>
				<div class="wc-gpd-designer__canvas-wrap">
					<canvas id="wc-gpd-canvas" aria-label="<?php esc_attr_e( 'Design canvas', 'wc-generic-product-designer' ); ?>"></canvas>
				</div>
			</div>
			<input type="hidden" name="wc_gpd_design_svg" id="wc-gpd-design-svg" value="" />
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
		</div>
		<?php
	}
}
