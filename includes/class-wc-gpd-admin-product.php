<?php
/**
 * WooCommerce product admin: Product Designer tab.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin product settings.
 */
class WC_GPD_Admin_Product implements WC_GPD_Module {

	/**
	 * @var WC_GPD_Admin_Product|null
	 */
	private static $instance = null;

	const NONCE_ACTION = 'wc_gpd_save_product_meta';
	const NONCE_NAME   = 'wc_gpd_product_meta_nonce';

	/**
	 * @return WC_GPD_Admin_Product
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
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_tab_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register Product Designer tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_product_tab( $tabs ) {
		$tabs['wc_gpd_designer'] = array(
			'label'    => __( 'Product Designer', 'wc-generic-product-designer' ),
			'target'   => 'wc_gpd_product_designer_panel',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Render tab panel fields.
	 */
	public function render_product_tab_panel() {
		global $post;

		$product_id   = $post ? absint( $post->ID ) : 0;
		$enabled      = get_post_meta( $product_id, WC_GPD_Product_Meta::META_ENABLED, true );
		$width        = get_post_meta( $product_id, WC_GPD_Product_Meta::META_CANVAS_WIDTH, true );
		$height       = get_post_meta( $product_id, WC_GPD_Product_Meta::META_CANVAS_HEIGHT, true );
		$template_id  = absint( get_post_meta( $product_id, WC_GPD_Product_Meta::META_TEMPLATE_ID, true ) );
		$template_url = $template_id ? wp_get_attachment_image_url( $template_id, 'thumbnail' ) : '';

		if ( '' === $width ) {
			$width = WC_GPD_Product_Meta::DEFAULT_WIDTH;
		}
		if ( '' === $height ) {
			$height = WC_GPD_Product_Meta::DEFAULT_HEIGHT;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div id="wc_gpd_product_designer_panel" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'wc_gpd_enabled',
						'label'       => __( 'Enable product designer', 'wc-generic-product-designer' ),
						'description' => __( 'Show the canvas designer on the product page.', 'wc-generic-product-designer' ),
						'value'       => 'yes' === $enabled ? 'yes' : 'no',
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => 'wc_gpd_canvas_width',
						'label'             => __( 'Canvas width (px)', 'wc-generic-product-designer' ),
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => WC_GPD_Product_Meta::MIN_DIMENSION,
							'max'  => WC_GPD_Product_Meta::MAX_DIMENSION,
							'step' => '1',
						),
						'value'             => absint( $width ),
						'desc_tip'          => true,
						'description'       => __( 'Production canvas width in pixels.', 'wc-generic-product-designer' ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => 'wc_gpd_canvas_height',
						'label'             => __( 'Canvas height (px)', 'wc-generic-product-designer' ),
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => WC_GPD_Product_Meta::MIN_DIMENSION,
							'max'  => WC_GPD_Product_Meta::MAX_DIMENSION,
							'step' => '1',
						),
						'value'             => absint( $height ),
						'desc_tip'          => true,
						'description'       => __( 'Production canvas height in pixels.', 'wc-generic-product-designer' ),
					)
				);
				?>
				<p class="form-field wc_gpd_template_field">
					<label for="wc_gpd_template_image_id"><?php esc_html_e( 'Blank template image', 'wc-generic-product-designer' ); ?></label>
					<input type="hidden" id="wc_gpd_template_image_id" name="wc_gpd_template_image_id" value="<?php echo esc_attr( (string) $template_id ); ?>" />
					<span class="wc_gpd_template_preview">
						<?php if ( $template_url ) : ?>
							<img src="<?php echo esc_url( $template_url ); ?>" alt="" style="max-width:120px;height:auto;display:block;margin-bottom:8px;" />
						<?php endif; ?>
					</span>
					<button type="button" class="button wc_gpd_upload_template"><?php esc_html_e( 'Select / upload image', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button wc_gpd_remove_template" <?php echo $template_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'wc-generic-product-designer' ); ?></button>
					<span class="description"><?php esc_html_e( 'Base product image shown behind text layers (non-editable background).', 'wc-generic-product-designer' ); ?></span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product meta.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_meta( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST['wc_gpd_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, WC_GPD_Product_Meta::META_ENABLED, $enabled );

		$width  = isset( $_POST['wc_gpd_canvas_width'] ) ? absint( $_POST['wc_gpd_canvas_width'] ) : WC_GPD_Product_Meta::DEFAULT_WIDTH;
		$height = isset( $_POST['wc_gpd_canvas_height'] ) ? absint( $_POST['wc_gpd_canvas_height'] ) : WC_GPD_Product_Meta::DEFAULT_HEIGHT;

		$width  = min( WC_GPD_Product_Meta::MAX_DIMENSION, max( WC_GPD_Product_Meta::MIN_DIMENSION, $width ) );
		$height = min( WC_GPD_Product_Meta::MAX_DIMENSION, max( WC_GPD_Product_Meta::MIN_DIMENSION, $height ) );

		update_post_meta( $post_id, WC_GPD_Product_Meta::META_CANVAS_WIDTH, $width );
		update_post_meta( $post_id, WC_GPD_Product_Meta::META_CANVAS_HEIGHT, $height );

		$template_id = isset( $_POST['wc_gpd_template_image_id'] ) ? absint( $_POST['wc_gpd_template_image_id'] ) : 0;
		if ( $template_id && ! wp_attachment_is_image( $template_id ) ) {
			$template_id = 0;
		}
		update_post_meta( $post_id, WC_GPD_Product_Meta::META_TEMPLATE_ID, $template_id );

		WC_GPD_Logger::info(
			'Product designer settings saved',
			array(
				'product_id' => $post_id,
				'enabled'    => $enabled,
				'width'      => $width,
				'height'     => $height,
			)
		);
	}

	/**
	 * Enqueue media uploader on product edit screen.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'wc-gpd-admin-product',
			WC_GPD_PLUGIN_URL . 'assets/js/admin-product.js',
			array( 'jquery' ),
			WC_GPD_VERSION,
			true
		);
	}
}
