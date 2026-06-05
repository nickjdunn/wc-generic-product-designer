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
		$template_url  = $template_id ? wp_get_attachment_image_url( $template_id, 'thumbnail' ) : '';
		$template_json = get_post_meta( $product_id, WC_GPD_Product_Meta::META_TEMPLATE_JSON, true );
		if ( ! is_string( $template_json ) || '' === trim( $template_json ) ) {
			$template_json = wp_json_encode( WC_GPD_Template_Json::empty_document() );
		}
		$max_views = absint( get_post_meta( $product_id, WC_GPD_Product_Meta::META_MAX_DESIGN_VIEWS, true ) );
		if ( $max_views < WC_GPD_Product_Meta::MIN_VIEWS ) {
			$max_views = WC_GPD_Product_Meta::MIN_VIEWS;
		}
		$ps = WC_GPD_Product_Settings::get( $product_id );

		if ( '' === $width ) {
			$width = WC_GPD_Product_Meta::DEFAULT_WIDTH;
		}
		if ( '' === $height ) {
			$height = WC_GPD_Product_Meta::DEFAULT_HEIGHT;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div id="wc_gpd_product_designer_panel" class="panel woocommerce_options_panel hidden">
			<nav class="wc-gpd-product-subtabs-nav" aria-label="<?php esc_attr_e( 'Product designer sections', 'wc-generic-product-designer' ); ?>">
				<a href="#wc_gpd_subtab_general" class="wc-gpd-product-subtab-link is-active" aria-selected="true"><?php esc_html_e( 'General', 'wc-generic-product-designer' ); ?></a>
				<a href="#wc_gpd_subtab_template" class="wc-gpd-product-subtab-link" aria-selected="false"><?php esc_html_e( 'Template', 'wc-generic-product-designer' ); ?></a>
				<a href="#wc_gpd_subtab_tools" class="wc-gpd-product-subtab-link" aria-selected="false"><?php esc_html_e( 'Customer tools', 'wc-generic-product-designer' ); ?></a>
				<a href="#wc_gpd_subtab_settings" class="wc-gpd-product-subtab-link" aria-selected="false"><?php esc_html_e( 'Settings', 'wc-generic-product-designer' ); ?></a>
			</nav>

			<div id="wc_gpd_subtab_general" class="wc-gpd-product-subtab-panel options_group">
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
					<label for="wc_gpd_template_image_id"><?php esc_html_e( 'Default background image', 'wc-generic-product-designer' ); ?></label>
					<input type="hidden" id="wc_gpd_template_image_id" name="wc_gpd_template_image_id" value="<?php echo esc_attr( (string) $template_id ); ?>" />
					<span class="wc_gpd_template_preview">
						<?php if ( $template_url ) : ?>
							<img src="<?php echo esc_url( $template_url ); ?>" alt="" style="max-width:120px;height:auto;display:block;margin-bottom:8px;" />
						<?php endif; ?>
					</span>
					<button type="button" class="button wc_gpd_upload_template"><?php esc_html_e( 'Select / upload image', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button wc_gpd_remove_template" <?php echo $template_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'wc-generic-product-designer' ); ?></button>
					<span class="description"><?php esc_html_e( 'Fallback product photo used when a design area has no specific background.', 'wc-generic-product-designer' ); ?></span>
				</p>
				<?php
				woocommerce_wp_text_input(
					array(
						'id'                => 'wc_gpd_max_design_views',
						'label'             => __( 'Maximum design areas', 'wc-generic-product-designer' ),
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => WC_GPD_Product_Meta::MIN_VIEWS,
							'max'  => WC_GPD_Product_Meta::MAX_VIEWS,
							'step' => '1',
						),
						'value'             => $max_views,
						'desc_tip'          => true,
						'description'       => __( 'How many switchable areas customers can design (e.g. Front, Back, Sleeve = 3).', 'wc-generic-product-designer' ),
					)
				);
				?>
			</div>

			<div id="wc_gpd_subtab_template" class="wc-gpd-product-subtab-panel options_group" hidden>
				<div class="wc-gpd-template-editor-wrap" id="wc-gpd-template-editor-root">
					<h3><?php esc_html_e( 'Template areas & outlines', 'wc-generic-product-designer' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Upload a photo of the product (e.g. slate) per area, draw a red outline around the engrave region, and mark it as the bounding box. Customers see the live mockup; production downloads omit the photo.', 'wc-generic-product-designer' ); ?>
					</p>
					<input type="hidden" id="wc_gpd_template_json" name="wc_gpd_template_json" value="<?php echo esc_attr( $template_json ); ?>" />
					<input type="hidden" id="wc_gpd_template_canvas_width" value="<?php echo esc_attr( (string) absint( $width ) ); ?>" />
					<input type="hidden" id="wc_gpd_template_canvas_height" value="<?php echo esc_attr( (string) absint( $height ) ); ?>" />
					<input type="hidden" id="wc_gpd_template_max_views" value="<?php echo esc_attr( (string) $max_views ); ?>" />
					<input type="hidden" id="wc_gpd_template_default_image_id" value="<?php echo esc_attr( (string) $template_id ); ?>" />
					<input type="hidden" id="wc_gpd_tpl_default_outline_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" />
					<input type="hidden" id="wc_gpd_tpl_default_outline_width" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" />
					<input type="hidden" id="wc_gpd_tpl_default_bbox_color" value="<?php echo esc_attr( $ps['bbox_stroke_color'] ); ?>" />
					<input type="hidden" id="wc_gpd_tpl_default_bbox_width" value="<?php echo esc_attr( (string) $ps['bbox_stroke_width'] ); ?>" />
					<div class="wc-gpd-template-view-tabs" id="wc-gpd-template-view-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Template design areas', 'wc-generic-product-designer' ); ?>"></div>
					<div class="wc-gpd-template-view-toolbar">
						<button type="button" class="button" id="wc-gpd-template-add-view"><?php esc_html_e( 'Add design area', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button" id="wc-gpd-template-rename-view"><?php esc_html_e( 'Rename area', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button wc-gpd-popout-trigger" id="wc-gpd-template-popout"><?php esc_html_e( 'Expand designer', 'wc-generic-product-designer' ); ?></button>
					</div>
					<div class="wc-gpd-template-area-photo">
						<strong><?php esc_html_e( 'Area background photo', 'wc-generic-product-designer' ); ?></strong>
						<input type="hidden" id="wc_gpd_template_view_image_id" value="0" />
						<span class="wc-gpd-template-view-image-preview" id="wc-gpd-template-view-image-preview"></span>
						<button type="button" class="button" id="wc-gpd-template-view-image-select"><?php esc_html_e( 'Upload / select photo', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button" id="wc-gpd-template-view-image-clear"><?php esc_html_e( 'Use product default', 'wc-generic-product-designer' ); ?></button>
						<p class="description"><?php esc_html_e( 'Shown to customers as a live mockup. Omitted from production exports unless you include background.', 'wc-generic-product-designer' ); ?></p>
					</div>
					<div class="wc-gpd-template-editor-toolbar">
						<button type="button" class="button wc-gpd-add-template-rect"><?php esc_html_e( 'Add rectangle', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button wc-gpd-add-template-square"><?php esc_html_e( 'Add square', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button wc-gpd-add-template-circle"><?php esc_html_e( 'Add circle', 'wc-generic-product-designer' ); ?></button>
					</div>
					<fieldset class="wc-gpd-template-shape-props" id="wc-gpd-template-shape-props">
						<legend><?php esc_html_e( 'Selected shape', 'wc-generic-product-designer' ); ?></legend>
						<p class="wc-gpd-shape-props-hint" id="wc-gpd-shape-props-hint"><?php esc_html_e( 'Add or select a shape to edit outline and bounding box options.', 'wc-generic-product-designer' ); ?></p>
						<div class="wc-gpd-shape-props-fields" id="wc-gpd-shape-props-fields" hidden>
							<label>
								<?php esc_html_e( 'Stroke color', 'wc-generic-product-designer' ); ?>
								<input type="color" id="wc_gpd_template_stroke_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" />
							</label>
							<label>
								<?php esc_html_e( 'Stroke width', 'wc-generic-product-designer' ); ?>
								<input type="number" id="wc_gpd_template_stroke_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" />
							</label>
							<label class="wc-gpd-template-outline-toggle">
								<input type="checkbox" id="wc_gpd_template_is_outline" checked="checked" />
								<?php esc_html_e( 'Template outline (production line)', 'wc-generic-product-designer' ); ?>
							</label>
							<label class="wc-gpd-template-bbox-toggle">
								<input type="checkbox" id="wc_gpd_template_is_bbox" />
								<?php esc_html_e( 'Use as bounding box', 'wc-generic-product-designer' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'One bounding box per area constrains customer text. Bounding boxes are auto-marked as outlines.', 'wc-generic-product-designer' ); ?></p>
						</div>
					</fieldset>
					<canvas id="wc-gpd-template-canvas" width="<?php echo esc_attr( (string) absint( $width ) ); ?>" height="<?php echo esc_attr( (string) absint( $height ) ); ?>"></canvas>
				</div>
			</div>

			<div id="wc_gpd_subtab_tools" class="wc-gpd-product-subtab-panel options_group" hidden>
				<p class="description"><?php esc_html_e( 'Choose which design tools customers can use on this product.', 'wc-generic-product-designer' ); ?></p>
				<p class="form-field">
					<label><input type="checkbox" name="wc_gpd_ps_allow_text_color" value="1" <?php checked( $ps['allow_text_color'] ); ?> /> <?php esc_html_e( 'Allow text color picker', 'wc-generic-product-designer' ); ?></label>
				</p>
				<p class="form-field">
					<label><input type="checkbox" name="wc_gpd_ps_single_color_only" value="1" <?php checked( $ps['single_color_only'] ); ?> /> <?php esc_html_e( 'Single color text only (no color picker)', 'wc-generic-product-designer' ); ?></label>
				</p>
				<p class="form-field">
					<label for="wc_gpd_ps_forced_text_color"><?php esc_html_e( 'Forced text color', 'wc-generic-product-designer' ); ?></label>
					<input type="color" id="wc_gpd_ps_forced_text_color" name="wc_gpd_ps_forced_text_color" value="<?php echo esc_attr( $ps['forced_text_color'] ); ?>" />
					<span class="description"><?php esc_html_e( 'Used when single-color mode is on, or as the default text color.', 'wc-generic-product-designer' ); ?></span>
				</p>
				<p class="form-field">
					<label><input type="checkbox" name="wc_gpd_ps_allow_bold" value="1" <?php checked( $ps['allow_bold'] ); ?> /> <?php esc_html_e( 'Allow bold', 'wc-generic-product-designer' ); ?></label>
				</p>
				<p class="form-field">
					<label><input type="checkbox" name="wc_gpd_ps_allow_italic" value="1" <?php checked( $ps['allow_italic'] ); ?> /> <?php esc_html_e( 'Allow italic', 'wc-generic-product-designer' ); ?></label>
				</p>
				<p class="form-field">
					<label><input type="checkbox" name="wc_gpd_ps_allow_font_family" value="1" <?php checked( $ps['allow_font_family'] ); ?> /> <?php esc_html_e( 'Allow font family', 'wc-generic-product-designer' ); ?></label>
				</p>
				<p class="form-field">
					<label><input type="checkbox" name="wc_gpd_ps_allow_font_size" value="1" <?php checked( $ps['allow_font_size'] ); ?> /> <?php esc_html_e( 'Allow font size', 'wc-generic-product-designer' ); ?></label>
				</p>
				<p class="form-field">
					<label><input type="checkbox" name="wc_gpd_ps_allow_text_align" value="1" <?php checked( $ps['allow_text_align'] ); ?> /> <?php esc_html_e( 'Allow text alignment', 'wc-generic-product-designer' ); ?></label>
				</p>
			</div>

			<div id="wc_gpd_subtab_settings" class="wc-gpd-product-subtab-panel options_group" hidden>
				<p class="description"><?php esc_html_e( 'Outline and export defaults saved with this product.', 'wc-generic-product-designer' ); ?></p>
				<p class="form-field">
					<label><input type="checkbox" name="wc_gpd_ps_enable_popout" value="1" <?php checked( $ps['enable_popout'] ); ?> /> <?php esc_html_e( 'Enable expand / pop-out designer (admin + product page)', 'wc-generic-product-designer' ); ?></label>
				</p>
				<p class="form-field">
					<label for="wc_gpd_ps_outline_color"><?php esc_html_e( 'Default outline color (template editor)', 'wc-generic-product-designer' ); ?></label>
					<input type="color" id="wc_gpd_ps_outline_color" name="wc_gpd_ps_outline_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" />
				</p>
				<p class="form-field">
					<label for="wc_gpd_ps_outline_stroke_width"><?php esc_html_e( 'Default outline width (template editor)', 'wc-generic-product-designer' ); ?></label>
					<input type="number" id="wc_gpd_ps_outline_stroke_width" name="wc_gpd_ps_outline_stroke_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" />
				</p>
				<p class="form-field">
					<label for="wc_gpd_ps_bbox_stroke_color"><?php esc_html_e( 'Bounding box color (template editor)', 'wc-generic-product-designer' ); ?></label>
					<input type="color" id="wc_gpd_ps_bbox_stroke_color" name="wc_gpd_ps_bbox_stroke_color" value="<?php echo esc_attr( $ps['bbox_stroke_color'] ); ?>" />
				</p>
				<p class="form-field">
					<label for="wc_gpd_ps_bbox_stroke_width"><?php esc_html_e( 'Bounding box width (template editor)', 'wc-generic-product-designer' ); ?></label>
					<input type="number" id="wc_gpd_ps_bbox_stroke_width" name="wc_gpd_ps_bbox_stroke_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['bbox_stroke_width'] ); ?>" />
				</p>
				<p class="form-field">
					<label for="wc_gpd_ps_export_outline_color"><?php esc_html_e( 'Production export outline color', 'wc-generic-product-designer' ); ?></label>
					<input type="color" id="wc_gpd_ps_export_outline_color" name="wc_gpd_ps_export_outline_color" value="<?php echo esc_attr( $ps['export_outline_color'] ); ?>" />
					<span class="description"><?php esc_html_e( 'Typically hairline red for laser/engrave software.', 'wc-generic-product-designer' ); ?></span>
				</p>
				<p class="form-field">
					<label for="wc_gpd_ps_export_outline_width"><?php esc_html_e( 'Production export outline width', 'wc-generic-product-designer' ); ?></label>
					<input type="number" id="wc_gpd_ps_export_outline_width" name="wc_gpd_ps_export_outline_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['export_outline_width'] ); ?>" />
				</p>
				<p class="form-field">
					<label><input type="checkbox" name="wc_gpd_ps_export_hairline_outline" value="1" <?php checked( $ps['export_hairline_outline'] ); ?> /> <?php esc_html_e( 'Force hairline outline width on export', 'wc-generic-product-designer' ); ?></label>
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

		$max_views = isset( $_POST['wc_gpd_max_design_views'] ) ? absint( $_POST['wc_gpd_max_design_views'] ) : WC_GPD_Product_Meta::MIN_VIEWS;
		$max_views = min( WC_GPD_Product_Meta::MAX_VIEWS, max( WC_GPD_Product_Meta::MIN_VIEWS, $max_views ) );
		update_post_meta( $post_id, WC_GPD_Product_Meta::META_MAX_DESIGN_VIEWS, $max_views );

		$raw_template_json = isset( $_POST['wc_gpd_template_json'] ) ? wp_unslash( $_POST['wc_gpd_template_json'] ) : '';
		$template_json     = WC_GPD_Template_Json::sanitize( is_string( $raw_template_json ) ? $raw_template_json : '' );
		if ( false !== $template_json ) {
			update_post_meta( $post_id, WC_GPD_Product_Meta::META_TEMPLATE_JSON, $template_json );
		}

		WC_GPD_Product_Settings::save( $post_id, WC_GPD_Product_Settings::from_post( wp_unslash( $_POST ) ) );

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
		wp_enqueue_style(
			'wc-gpd-admin-product',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-product.css',
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
			'wc-gpd-designer-popout',
			WC_GPD_PLUGIN_URL . 'assets/js/designer-popout.js',
			array(),
			WC_GPD_VERSION,
			true
		);
		wp_enqueue_script(
			'wc-gpd-admin-product',
			WC_GPD_PLUGIN_URL . 'assets/js/admin-product.js',
			array( 'jquery' ),
			WC_GPD_VERSION,
			true
		);
		wp_enqueue_script(
			'wc-gpd-admin-product-tabs',
			WC_GPD_PLUGIN_URL . 'assets/js/admin-product-tabs.js',
			array( 'jquery' ),
			WC_GPD_VERSION,
			true
		);
		wp_enqueue_script(
			'wc-gpd-admin-template-editor',
			WC_GPD_PLUGIN_URL . 'assets/js/admin-template-editor.js',
			array( 'jquery', 'fabric-js', 'wc-gpd-designer-popout' ),
			WC_GPD_VERSION,
			true
		);
	}
}
