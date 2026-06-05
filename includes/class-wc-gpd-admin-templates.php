<?php
/**
 * Full-page template designer admin UI.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template designer admin screens.
 */
class WC_GPD_Admin_Templates implements WC_GPD_Module {

	/**
	 * @var WC_GPD_Admin_Templates|null
	 */
	private static $instance = null;

	const PAGE_SLUG      = 'wc-gpd-templates';
	const NONCE_ACTION   = 'wc_gpd_save_template';
	const NONCE_NAME     = 'wc_gpd_template_nonce';

	/**
	 * @return WC_GPD_Admin_Templates
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( 'WC_GPD_Design_Template', 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 58 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		WC_GPD_Bootstrap_Icons::register_ajax();
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Template Designer', 'wc-generic-product-designer' ),
			__( 'Template Designer', 'wc-generic-product-designer' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-art',
			56
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Export defaults', 'wc-generic-product-designer' ),
			__( 'Export defaults', 'wc-generic-product-designer' ),
			'manage_woocommerce',
			WC_GPD_Admin_Settings::PAGE_SLUG,
			array( WC_GPD_Admin_Settings::instance(), 'render_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Debug', 'wc-generic-product-designer' ),
			__( 'Debug', 'wc-generic-product-designer' ),
			'manage_woocommerce',
			WC_GPD_Debug::PAGE_SLUG,
			array( WC_GPD_Debug::instance(), 'render_page' )
		);
	}

	/**
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' !== $action ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'wc-gpd-admin-templates',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-templates.css',
			array(),
			WC_GPD_VERSION
		);
		wp_enqueue_style(
			'wc-gpd-admin-product',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-product.css',
			array( 'wc-gpd-admin-templates' ),
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
		wp_enqueue_script(
			'wc-gpd-admin-bootstrap-icons',
			WC_GPD_PLUGIN_URL . 'assets/js/admin-bootstrap-icons.js',
			array( 'wc-gpd-admin-template-editor' ),
			WC_GPD_VERSION,
			true
		);
		$template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_localize_script(
			'wc-gpd-admin-template-editor',
			'wcGpdTemplateEditor',
			array(
				'maxViews' => WC_GPD_Product_Meta::MAX_VIEWS,
				'fonts'    => WC_GPD_Font_Registry::font_families_for_js( $template_id ),
				'fontOptions' => WC_GPD_Font_Registry::fonts_for_template( $template_id ),
				'defaultFont'   => WC_GPD_Font_Registry::default_font_family(),
				'siteLibraries' => WC_GPD_Graphic_Libraries::get_all(),
				'bootstrapIcons' => array(
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( WC_GPD_Bootstrap_Icons::NONCE_ACTION ),
					'iconBaseUrl' => WC_GPD_PLUGIN_URL . WC_GPD_Bootstrap_Icons::ICONS_DIR . '/',
				),
			)
		);
		wp_localize_script(
			'wc-gpd-admin-bootstrap-icons',
			'wcGpdBootstrapIcons',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( WC_GPD_Bootstrap_Icons::NONCE_ACTION ),
				'iconBaseUrl' => WC_GPD_PLUGIN_URL . WC_GPD_Bootstrap_Icons::ICONS_DIR . '/',
				'i18n'        => array(
					'searching' => __( 'Loading icons…', 'wc-generic-product-designer' ),
					'noResults' => __( 'No icons found.', 'wc-generic-product-designer' ),
					'showing'   => __( 'Showing %1$s–%2$s of %3$s', 'wc-generic-product-designer' ),
				),
			)
		);
		WC_GPD_Font_Registry::enqueue_for_designer( $template_id );
	}

	/**
	 * Route list vs edit screen.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_GET['action'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_edit_screen();
			return;
		}

		if ( isset( $_GET['action'] ) && 'new' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->create_and_redirect();
			return;
		}

		$this->render_list_screen();
	}

	/**
	 * Create template and redirect to editor.
	 */
	private function create_and_redirect() {
		check_admin_referer( 'wc_gpd_new_template' );
		$title = isset( $_GET['title'] ) ? sanitize_text_field( wp_unslash( $_GET['title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id    = WC_GPD_Design_Template::create( $title );
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		wp_safe_redirect( WC_GPD_Design_Template::edit_url( $id ) );
		exit;
	}

	/**
	 * Template list.
	 */
	private function render_list_screen() {
		$templates = WC_GPD_Design_Template::list_templates();
		$new_url   = wp_nonce_url(
			add_query_arg( array( 'page' => self::PAGE_SLUG, 'action' => 'new' ), admin_url( 'admin.php' ) ),
			'wc_gpd_new_template'
		);
		?>
		<div class="wrap wc-gpd-templates-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Design templates', 'wc-generic-product-designer' ); ?></h1>
			<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'wc-generic-product-designer' ); ?></a>
			<hr class="wp-header-end" />
			<p class="description"><?php esc_html_e( 'Build reusable templates here, then assign them to products in the Product Designer tab.', 'wc-generic-product-designer' ); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Canvas size', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Areas', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Products', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wc-generic-product-designer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $templates ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No templates yet. Create one to get started.', 'wc-generic-product-designer' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $templates as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
								<td><?php echo esc_html( $row['width'] . ' × ' . $row['height'] . ' px' ); ?></td>
								<td><?php echo esc_html( (string) $row['views'] ); ?></td>
								<td><?php echo esc_html( (string) WC_GPD_Design_Template::count_products_using( $row['id'] ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( WC_GPD_Design_Template::edit_url( $row['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit template', 'wc-generic-product-designer' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Full-page template editor.
	 */
	private function render_edit_screen() {
		$template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$settings    = $template_id ? WC_GPD_Design_Template::get_settings( $template_id ) : null;

		if ( ! $settings ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Template not found.', 'wc-generic-product-designer' ) . '</p></div>';
			return;
		}

		if ( isset( $_POST['wc_gpd_template_save'] ) ) {
			check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
			$result   = WC_GPD_Design_Template::save_from_post( $template_id );
			$settings = WC_GPD_Design_Template::get_settings( $template_id );
			$class    = ! empty( $result['ok'] ) ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $result['message'] ?? '' ) . '</p></div>';
		}

		$ps            = $settings['product_settings'];
		$template_json = $settings['template_json'];
		if ( '' === trim( $template_json ) ) {
			$template_json = wp_json_encode( WC_GPD_Template_Json::empty_document() );
		}

		$list_url = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );
		?>
		<div class="wrap wc-gpd-template-edit-wrap">
			<h1>
				<?php esc_html_e( 'Edit template', 'wc-generic-product-designer' ); ?>
				<a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action"><?php esc_html_e( 'All templates', 'wc-generic-product-designer' ); ?></a>
			</h1>

			<form method="post" id="wc-gpd-template-form" class="wc-gpd-template-form" novalidate>
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="wc_gpd_template_save" value="1" />

				<div id="wc_gpd_template_designer_panel" class="wc-gpd-fullpage-panel wc-gpd-template-designer-panel">
					<p class="wc-gpd-template-title-row">
						<label for="wc_gpd_template_title"><?php esc_html_e( 'Template name', 'wc-generic-product-designer' ); ?></label>
						<input type="text" id="wc_gpd_template_title" name="wc_gpd_template_title" class="regular-text" value="<?php echo esc_attr( $settings['title'] ); ?>" />
					</p>

					<nav class="wc-gpd-product-subtabs-nav">
						<a href="#wc_gpd_subtab_template" class="wc-gpd-product-subtab-link is-active"><?php esc_html_e( 'Template', 'wc-generic-product-designer' ); ?></a>
						<a href="#wc_gpd_subtab_tools" class="wc-gpd-product-subtab-link"><?php esc_html_e( 'Customer tools', 'wc-generic-product-designer' ); ?></a>
						<a href="#wc_gpd_subtab_settings" class="wc-gpd-product-subtab-link"><?php esc_html_e( 'Settings', 'wc-generic-product-designer' ); ?></a>
					</nav>

					<div id="wc_gpd_subtab_template" class="wc-gpd-product-subtab-panel">
						<?php $this->render_template_canvas( $settings, $template_json, $ps ); ?>
					</div>

					<div id="wc_gpd_subtab_tools" class="wc-gpd-product-subtab-panel" hidden>
						<?php $this->render_customer_tools( $ps ); ?>
					</div>

					<div id="wc_gpd_subtab_settings" class="wc-gpd-product-subtab-panel" hidden>
						<?php $this->render_template_settings( $ps ); ?>
					</div>
				</div>

				<p class="submit wc-gpd-template-submit">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save template', 'wc-generic-product-designer' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * @param array  $settings      Template settings.
	 * @param string $template_json JSON string.
	 * @param array  $ps            Product settings.
	 */
	private function render_template_canvas( $settings, $template_json, $ps ) {
		$template_fonts_json = wp_json_encode( $settings['template_fonts'] );
		$font_options           = WC_GPD_Font_Registry::fonts_for_template( $settings['id'] );
		?>
		<div class="wc-gpd-template-editor-wrap" id="wc-gpd-template-editor-root">
			<textarea id="wc_gpd_template_json" name="wc_gpd_template_json" class="wc-gpd-template-json-field" aria-hidden="true"><?php echo esc_textarea( $template_json ); ?></textarea>
			<textarea id="wc_gpd_template_fonts" name="wc_gpd_template_fonts" class="wc-gpd-template-json-field" aria-hidden="true"><?php echo esc_textarea( $template_fonts_json ? $template_fonts_json : '[]' ); ?></textarea>
			<input type="hidden" id="wc_gpd_template_canvas_width" value="<?php echo esc_attr( (string) $settings['width'] ); ?>" />
			<input type="hidden" id="wc_gpd_template_canvas_height" value="<?php echo esc_attr( (string) $settings['height'] ); ?>" />
			<input type="hidden" id="wc_gpd_max_design_views" name="wc_gpd_max_design_views" value="<?php echo esc_attr( (string) $settings['max_views'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_outline_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_outline_width" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_bbox_color" value="<?php echo esc_attr( $ps['bbox_stroke_color'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_bbox_width" value="<?php echo esc_attr( (string) $ps['bbox_stroke_width'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_canvas_bg" value="<?php echo esc_attr( $ps['canvas_bg_color'] ); ?>" />
			<div class="wc-gpd-tpl-header">
				<div class="wc-gpd-template-view-tabs" id="wc-gpd-template-view-tabs" role="tablist"></div>
				<div class="wc-gpd-tpl-canvas-size-inline" title="<?php esc_attr_e( 'Production canvas size in pixels', 'wc-generic-product-designer' ); ?>">
					<span class="wc-gpd-tpl-canvas-size-label"><?php esc_html_e( 'Canvas', 'wc-generic-product-designer' ); ?></span>
					<input type="number" id="wc_gpd_canvas_width" name="wc_gpd_canvas_width" class="wc-gpd-tpl-size-input" min="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MIN_DIMENSION ); ?>" max="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MAX_DIMENSION ); ?>" value="<?php echo esc_attr( (string) $settings['width'] ); ?>" aria-label="<?php esc_attr_e( 'Canvas width in pixels', 'wc-generic-product-designer' ); ?>" />
					<span class="wc-gpd-tpl-size-sep" aria-hidden="true">×</span>
					<input type="number" id="wc_gpd_canvas_height" name="wc_gpd_canvas_height" class="wc-gpd-tpl-size-input" min="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MIN_DIMENSION ); ?>" max="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MAX_DIMENSION ); ?>" value="<?php echo esc_attr( (string) $settings['height'] ); ?>" aria-label="<?php esc_attr_e( 'Canvas height in pixels', 'wc-generic-product-designer' ); ?>" />
					<span class="wc-gpd-tpl-size-unit">px</span>
				</div>
				<div class="wc-gpd-tpl-header-actions">
					<button type="button" class="button button-small" id="wc-gpd-template-add-view"><?php esc_html_e( 'Add area', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-small" id="wc-gpd-template-rename-view"><?php esc_html_e( 'Rename', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-small" id="wc-gpd-template-delete-view" title="<?php esc_attr_e( 'Remove this design area', 'wc-generic-product-designer' ); ?>"><?php esc_html_e( 'Delete area', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-small wc-gpd-popout-trigger" id="wc-gpd-template-popout"><?php esc_html_e( 'Expand', 'wc-generic-product-designer' ); ?></button>
				</div>
			</div>
			<div class="wc-gpd-tpl-layout">
				<aside class="wc-gpd-tpl-accordion" id="wc-gpd-tpl-accordion">
					<div class="wc-gpd-accordion-section is-open" data-section="layers">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="true"><?php esc_html_e( 'Layers', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body">
							<ul class="wc-gpd-tpl-layers-list" id="wc-gpd-template-layers-list"></ul>
							<p class="wc-gpd-tpl-hint" id="wc-gpd-layers-empty-hint"><?php esc_html_e( 'Layers appear here as you add content.', 'wc-generic-product-designer' ); ?></p>
						</div>
					</div>
					<div class="wc-gpd-accordion-section" data-section="text">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="false"><?php esc_html_e( 'Text', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body" hidden>
						<div class="wc-gpd-tpl-btn-row">
							<button type="button" class="button button-small" id="wc-gpd-template-add-text"><?php esc_html_e( 'Fixed text', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button button-small" id="wc-gpd-template-add-placeholder"><?php esc_html_e( 'Variable field', 'wc-generic-product-designer' ); ?></button>
						</div>
						<div class="wc-gpd-tpl-selection wc-gpd-tpl-text-editor" id="wc-gpd-text-editor" hidden>
							<div class="wc-gpd-tpl-placeholder-meta" id="wc-gpd-placeholder-meta" hidden>
								<p><label><?php esc_html_e( 'Field label', 'wc-generic-product-designer' ); ?> <input type="text" id="wc_gpd_placeholder_label" class="widefat" /></label></p>
								<p><label><?php esc_html_e( 'Field key', 'wc-generic-product-designer' ); ?> <input type="text" id="wc_gpd_placeholder_key" class="widefat" /></label></p>
								<p><label><?php esc_html_e( 'Box width (px)', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_placeholder_width" min="40" max="2000" value="240" /></label></p>
							</div>
							<div class="wc-gpd-rich-text-box">
								<label for="wc_gpd_tpl_text_content" class="wc-gpd-rich-label"><?php esc_html_e( 'Text', 'wc-generic-product-designer' ); ?></label>
								<textarea id="wc_gpd_tpl_text_content" class="wc-gpd-rich-textarea" rows="3"></textarea>
								<div class="wc-gpd-rich-toolbar" aria-label="<?php esc_attr_e( 'Text formatting', 'wc-generic-product-designer' ); ?>">
									<div class="wc-gpd-rich-toolbar-row">
										<select id="wc_gpd_tpl_font_family" class="wc-gpd-rich-font-select" title="<?php esc_attr_e( 'Font family', 'wc-generic-product-designer' ); ?>"></select>
										<input type="number" id="wc_gpd_tpl_font_size" class="wc-gpd-rich-size" min="8" max="400" value="32" title="<?php esc_attr_e( 'Font size', 'wc-generic-product-designer' ); ?>" />
									</div>
									<div class="wc-gpd-rich-toolbar-row wc-gpd-rich-toolbar-row--icons">
										<button type="button" class="wc-gpd-rich-btn" id="wc_gpd_tpl_bold" title="<?php esc_attr_e( 'Bold', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-bold"></span></button>
										<button type="button" class="wc-gpd-rich-btn" id="wc_gpd_tpl_italic" title="<?php esc_attr_e( 'Italic', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-italic"></span></button>
										<button type="button" class="wc-gpd-rich-btn" id="wc_gpd_tpl_underline" title="<?php esc_attr_e( 'Underline', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-underline"></span></button>
										<span class="wc-gpd-rich-sep" aria-hidden="true"></span>
										<span class="wc-gpd-tpl-align-group" role="group" aria-label="<?php esc_attr_e( 'Alignment', 'wc-generic-product-designer' ); ?>">
											<button type="button" class="wc-gpd-rich-btn wc-gpd-tpl-align" data-align="left" title="<?php esc_attr_e( 'Align left', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-alignleft"></span></button>
											<button type="button" class="wc-gpd-rich-btn wc-gpd-tpl-align" data-align="center" title="<?php esc_attr_e( 'Align center', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-aligncenter"></span></button>
											<button type="button" class="wc-gpd-rich-btn wc-gpd-tpl-align" data-align="right" title="<?php esc_attr_e( 'Align right', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-editor-alignright"></span></button>
										</span>
										<span class="wc-gpd-rich-sep" aria-hidden="true"></span>
										<label class="wc-gpd-rich-color-btn" title="<?php esc_attr_e( 'Text color', 'wc-generic-product-designer' ); ?>"><span class="dashicons dashicons-admin-appearance"></span><input type="color" id="wc_gpd_tpl_text_color" value="#000000" /></label>
										<input type="number" id="wc_gpd_tpl_line_height" class="wc-gpd-rich-mini" min="0.5" max="3" step="0.01" value="1.16" title="<?php esc_attr_e( 'Line height', 'wc-generic-product-designer' ); ?>" />
										<input type="number" id="wc_gpd_tpl_letter_spacing" class="wc-gpd-rich-mini" min="-50" max="200" step="1" value="0" title="<?php esc_attr_e( 'Letter spacing', 'wc-generic-product-designer' ); ?>" />
									</div>
								</div>
							</div>
							<p id="wc-gpd-shrink-fit-row" hidden><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_tpl_shrink_fit" /> <?php esc_html_e( 'Shrink text to fit box', 'wc-generic-product-designer' ); ?></label></p>
							<fieldset class="wc-gpd-tpl-customer-options">
								<legend><?php esc_html_e( 'Customer can change', 'wc-generic-product-designer' ); ?></legend>
								<div class="wc-gpd-tpl-customer-grid">
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_font" checked="checked" /> <?php esc_html_e( 'Font', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_size" checked="checked" /> <?php esc_html_e( 'Size', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_color" checked="checked" /> <?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_bold" checked="checked" /> <?php esc_html_e( 'Bold', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_italic" checked="checked" /> <?php esc_html_e( 'Italic', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_underline" checked="checked" /> <?php esc_html_e( 'Underline', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_align" checked="checked" /> <?php esc_html_e( 'Alignment', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_line_height" checked="checked" /> <?php esc_html_e( 'Line height', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_letter_spacing" checked="checked" /> <?php esc_html_e( 'Letter spacing', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_move" checked="checked" /> <?php esc_html_e( 'Position', 'wc-generic-product-designer' ); ?></label>
								</div>
							</fieldset>
						</div>
						<p class="wc-gpd-tpl-hint" id="wc-gpd-text-editor-hint"><?php esc_html_e( 'Add text or select a text layer to edit.', 'wc-generic-product-designer' ); ?></p>
						</div>
					</div>
					<div class="wc-gpd-accordion-section" data-section="fonts">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="false"><?php esc_html_e( 'Template fonts', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body" hidden>
							<p class="wc-gpd-tpl-panel-desc"><?php esc_html_e( 'Choose fonts for this template. Manage fonts under Template Designer → Fonts.', 'wc-generic-product-designer' ); ?></p>
							<div class="wc-gpd-tpl-font-picks" id="wc-gpd-template-font-picks">
								<?php foreach ( $font_options as $font ) : ?>
									<label class="wc-gpd-tpl-check wc-gpd-tpl-font-pick-row" style="font-family:<?php echo esc_attr( $font['css'] ); ?>">
										<input type="checkbox" class="wc-gpd-template-font-pick" value="<?php echo esc_attr( $font['key'] ); ?>" <?php checked( empty( $settings['template_fonts'] ) || in_array( $font['key'], $settings['template_fonts'], true ) ); ?> />
										<?php echo esc_html( $font['label'] ); ?>
										<?php if ( ! empty( $font['admin_label'] ) && $font['admin_label'] !== $font['label'] ) : ?>
											<span class="wc-gpd-font-admin-name">(<?php echo esc_html( $font['admin_label'] ); ?>)</span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="wc-gpd-accordion-section" data-section="images">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="false"><?php esc_html_e( 'Images & graphics', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body" hidden>
							<p class="wc-gpd-tpl-panel-desc"><?php esc_html_e( 'Add images from the media library. Choose fixed or customer-repositionable graphics. Create pick-area libraries under Template Designer → Libraries.', 'wc-generic-product-designer' ); ?></p>
							<div class="wc-gpd-tpl-btn-row">
								<button type="button" class="button button-small" id="wc-gpd-template-add-image"><?php esc_html_e( 'Add image', 'wc-generic-product-designer' ); ?></button>
								<button type="button" class="button button-small" id="wc-gpd-template-add-graphic-slot"><?php esc_html_e( 'Customer pick area', 'wc-generic-product-designer' ); ?></button>
							</div>
							<div class="wc-gpd-tpl-selection" id="wc-gpd-image-props" hidden>
								<fieldset class="wc-gpd-tpl-fieldset">
									<legend><?php esc_html_e( 'Image role', 'wc-generic-product-designer' ); ?></legend>
									<label class="wc-gpd-tpl-check"><input type="radio" name="wc_gpd_image_role" value="fixed" checked="checked" /> <?php esc_html_e( 'Fixed graphic', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="radio" name="wc_gpd_image_role" value="repositionable" /> <?php esc_html_e( 'Customer repositionable', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="radio" name="wc_gpd_image_role" value="mockup" /> <?php esc_html_e( 'Mockup photo', 'wc-generic-product-designer' ); ?></label>
								</fieldset>
								<div id="wc-gpd-image-mockup-options" hidden>
									<button type="button" class="button button-small" id="wc-gpd-set-mockup-background"><?php esc_html_e( 'Set as mockup background', 'wc-generic-product-designer' ); ?></button>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_template_mockup_visible" checked="checked" /> <?php esc_html_e( 'Show in customer mockup', 'wc-generic-product-designer' ); ?></label>
								</div>
								<div id="wc-gpd-image-graphic-options">
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_export" checked="checked" /> <?php esc_html_e( 'Include in production file', 'wc-generic-product-designer' ); ?></label>
								</div>
								<div id="wc-gpd-image-customer-options" hidden>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_allow_move" checked="checked" /> <?php esc_html_e( 'Customer can move', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_allow_resize" checked="checked" /> <?php esc_html_e( 'Customer can resize', 'wc-generic-product-designer' ); ?></label>
									<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_lock_aspect" /> <?php esc_html_e( 'Lock aspect ratio for customer', 'wc-generic-product-designer' ); ?></label>
								</div>
								<button type="button" class="button button-link-delete" id="wc-gpd-template-delete-image"><?php esc_html_e( 'Remove image', 'wc-generic-product-designer' ); ?></button>
							</div>
							<div class="wc-gpd-tpl-selection" id="wc-gpd-graphic-slot-props" hidden>
								<p><label><?php esc_html_e( 'Graphic library', 'wc-generic-product-designer' ); ?> <select id="wc_gpd_slot_library_id"></select></label></p>
								<p class="description"><?php esc_html_e( 'The pick area stays fixed. Customers choose a graphic and can move or resize it within this box.', 'wc-generic-product-designer' ); ?></p>
								<button type="button" class="button button-link-delete" id="wc-gpd-template-delete-slot"><?php esc_html_e( 'Remove pick area', 'wc-generic-product-designer' ); ?></button>
							</div>
						</div>
					</div>
					<div class="wc-gpd-accordion-section" data-section="size" id="wc-gpd-accordion-size">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="false"><?php esc_html_e( 'Size & position', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body" id="wc-gpd-selection-dims-panel" hidden>
							<p>
								<label><?php esc_html_e( 'Units', 'wc-generic-product-designer' ); ?>
									<select id="wc_gpd_tpl_units">
										<option value="px"><?php esc_html_e( 'Pixels (px)', 'wc-generic-product-designer' ); ?></option>
										<option value="in"><?php esc_html_e( 'Inches (in)', 'wc-generic-product-designer' ); ?></option>
										<option value="mm"><?php esc_html_e( 'Millimeters (mm)', 'wc-generic-product-designer' ); ?></option>
										<option value="cm"><?php esc_html_e( 'Centimeters (cm)', 'wc-generic-product-designer' ); ?></option>
									</select>
								</label>
							</p>
							<fieldset class="wc-gpd-tpl-fieldset">
								<legend><?php esc_html_e( 'Size', 'wc-generic-product-designer' ); ?></legend>
								<div class="wc-gpd-tpl-dims-grid">
									<label><?php esc_html_e( 'Width', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_sel_width" min="0.01" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_w">px</span></label>
									<label><?php esc_html_e( 'Height', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_sel_height" min="0.01" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_h">px</span></label>
								</div>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_lock_aspect" /> <?php esc_html_e( 'Lock aspect ratio while editing', 'wc-generic-product-designer' ); ?></label>
							</fieldset>
							<fieldset class="wc-gpd-tpl-fieldset">
								<legend><?php esc_html_e( 'Position', 'wc-generic-product-designer' ); ?></legend>
								<div class="wc-gpd-tpl-dims-grid">
									<label><?php esc_html_e( 'X', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_sel_left" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_x">px</span></label>
									<label><?php esc_html_e( 'Y', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_sel_top" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_y">px</span></label>
								</div>
							</fieldset>
							<fieldset class="wc-gpd-tpl-fieldset">
								<legend><?php esc_html_e( 'Canvas guides', 'wc-generic-product-designer' ); ?></legend>
								<label class="wc-gpd-tpl-check" title="<?php esc_attr_e( 'Show ruler guides on the canvas', 'wc-generic-product-designer' ); ?>"><input type="checkbox" id="wc_gpd_tpl_show_ruler" /> <?php esc_html_e( 'Show ruler', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check" title="<?php esc_attr_e( 'Show measurements around the canvas', 'wc-generic-product-designer' ); ?>"><input type="checkbox" id="wc_gpd_tpl_show_measurements" /> <?php esc_html_e( 'Show measurements', 'wc-generic-product-designer' ); ?></label>
							</fieldset>
						</div>
					</div>
					<div class="wc-gpd-accordion-section" data-section="shapes">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="false"><?php esc_html_e( 'Shapes', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body" hidden>
							<p class="wc-gpd-tpl-panel-desc"><?php esc_html_e( 'Add production outlines, bounding boxes, or engraving-friendly vector shapes.', 'wc-generic-product-designer' ); ?></p>
							<div class="wc-gpd-tpl-btn-row">
								<button type="button" class="button button-small wc-gpd-add-template-rect"><?php esc_html_e( 'Rectangle', 'wc-generic-product-designer' ); ?></button>
								<button type="button" class="button button-small wc-gpd-add-template-square"><?php esc_html_e( 'Square', 'wc-generic-product-designer' ); ?></button>
								<button type="button" class="button button-small wc-gpd-add-template-circle"><?php esc_html_e( 'Circle', 'wc-generic-product-designer' ); ?></button>
								<button type="button" class="button button-small wc-gpd-add-template-hexagon"><?php esc_html_e( 'Hexagon', 'wc-generic-product-designer' ); ?></button>
								<button type="button" class="button button-small wc-gpd-add-template-octagon"><?php esc_html_e( 'Octagon', 'wc-generic-product-designer' ); ?></button>
								<button type="button" class="button button-small wc-gpd-add-template-heart"><?php esc_html_e( 'Heart', 'wc-generic-product-designer' ); ?></button>
								<button type="button" class="button button-small wc-gpd-add-template-freeform" id="wc-gpd-add-template-freeform"><?php esc_html_e( 'Freeform', 'wc-generic-product-designer' ); ?></button>
							</div>
							<p class="wc-gpd-tpl-hint wc-gpd-freeform-hint" id="wc-gpd-freeform-hint" hidden><?php esc_html_e( 'Click to place points. Click the first point again (or double-click) to close the shape.', 'wc-generic-product-designer' ); ?></p>
							<h5 class="wc-gpd-tpl-subheading"><?php esc_html_e( 'Bootstrap Icons', 'wc-generic-product-designer' ); ?></h5>
							<p class="description wc-gpd-bootstrap-icons-credit">
								<?php esc_html_e( 'Over 2,000 MIT-licensed icons bundled for offline use. Click an icon to add it to the template.', 'wc-generic-product-designer' ); ?>
								<a href="https://icons.getbootstrap.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'icons.getbootstrap.com', 'wc-generic-product-designer' ); ?></a>
							</p>
							<div class="wc-gpd-shape-library-grid wc-gpd-bootstrap-icon-featured" id="wc-gpd-bootstrap-icon-featured"></div>
							<div class="wc-gpd-bootstrap-icons-toolbar">
								<input type="search" id="wc-gpd-bootstrap-icon-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search icons (e.g. heart, star, flower)…', 'wc-generic-product-designer' ); ?>" />
								<button type="button" class="button button-small" id="wc-gpd-bootstrap-icon-search-btn"><?php esc_html_e( 'Search', 'wc-generic-product-designer' ); ?></button>
								<label class="wc-gpd-bootstrap-icon-limit-label">
									<?php esc_html_e( 'Per page', 'wc-generic-product-designer' ); ?>
									<select id="wc-gpd-bootstrap-icon-limit">
										<option value="48">48</option>
										<option value="60" selected="selected">60</option>
										<option value="96">96</option>
										<option value="120">120</option>
									</select>
								</label>
							</div>
							<p class="description" id="wc-gpd-bootstrap-icon-status" hidden></p>
							<div class="wc-gpd-shape-library-grid wc-gpd-bootstrap-icon-results" id="wc-gpd-bootstrap-icon-results"></div>
							<p class="wc-gpd-bootstrap-icon-load-more" id="wc-gpd-bootstrap-icon-load-more-wrap" hidden>
								<button type="button" class="button button-small" id="wc-gpd-bootstrap-icon-load-more"><?php esc_html_e( 'Load more', 'wc-generic-product-designer' ); ?></button>
							</p>
							<div class="wc-gpd-tpl-selection" id="wc-gpd-shape-props-fields" hidden>
								<p><label><?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?> <input type="color" id="wc_gpd_template_stroke_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" /></label></p>
								<p><label><?php esc_html_e( 'Width', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_template_stroke_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" /></label></p>
								<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_template_is_outline" checked="checked" /> <?php esc_html_e( 'Production outline', 'wc-generic-product-designer' ); ?></label></p>
								<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_template_is_bbox" /> <?php esc_html_e( 'Bounding box', 'wc-generic-product-designer' ); ?></label></p>
							</div>
							<p class="wc-gpd-tpl-hint" id="wc-gpd-shape-props-hint"><?php esc_html_e( 'Select a shape layer to edit. Delete layers from the Layers panel.', 'wc-generic-product-designer' ); ?></p>
						</div>
					</div>
				</aside>
				<div class="wc-gpd-tpl-canvas-col" id="wc-gpd-tpl-canvas-col">
					<div class="wc-gpd-tpl-canvas-frame" id="wc-gpd-tpl-canvas-frame">
						<div class="wc-gpd-tpl-ruler wc-gpd-tpl-ruler--top" id="wc-gpd-tpl-ruler-top" hidden></div>
						<div class="wc-gpd-tpl-ruler wc-gpd-tpl-ruler--left" id="wc-gpd-tpl-ruler-left" hidden></div>
						<canvas id="wc-gpd-template-canvas" width="<?php echo esc_attr( (string) $settings['width'] ); ?>" height="<?php echo esc_attr( (string) $settings['height'] ); ?>"></canvas>
						<div class="wc-gpd-tpl-measure wc-gpd-tpl-measure--bottom" id="wc-gpd-tpl-measure-bottom" hidden></div>
						<div class="wc-gpd-tpl-measure wc-gpd-tpl-measure--right" id="wc-gpd-tpl-measure-right" hidden></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array $ps Settings.
	 */
	private function render_customer_tools( $ps ) {
		?>
		<div class="wc-gpd-settings-grid">
			<div class="wc-gpd-settings-card">
				<h4><?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?></h4>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_text_color" value="1" <?php checked( $ps['allow_text_color'] ); ?> /> <?php esc_html_e( 'Text color picker', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_single_color_only" value="1" <?php checked( $ps['single_color_only'] ); ?> /> <?php esc_html_e( 'Single color only', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-color"><?php esc_html_e( 'Forced color', 'wc-generic-product-designer' ); ?> <input type="color" name="wc_gpd_ps_forced_text_color" value="<?php echo esc_attr( $ps['forced_text_color'] ); ?>" /></label></p>
			</div>
			<div class="wc-gpd-settings-card">
				<h4><?php esc_html_e( 'Typography', 'wc-generic-product-designer' ); ?></h4>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_font_family" value="1" <?php checked( $ps['allow_font_family'] ); ?> /> <?php esc_html_e( 'Font family', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_font_size" value="1" <?php checked( $ps['allow_font_size'] ); ?> /> <?php esc_html_e( 'Font size', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_bold" value="1" <?php checked( $ps['allow_bold'] ); ?> /> <?php esc_html_e( 'Bold', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_italic" value="1" <?php checked( $ps['allow_italic'] ); ?> /> <?php esc_html_e( 'Italic', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_underline" value="1" <?php checked( $ps['allow_underline'] ); ?> /> <?php esc_html_e( 'Underline', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_line_height" value="1" <?php checked( $ps['allow_line_height'] ); ?> /> <?php esc_html_e( 'Line height', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_letter_spacing" value="1" <?php checked( $ps['allow_letter_spacing'] ); ?> /> <?php esc_html_e( 'Letter spacing', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_text_align" value="1" <?php checked( $ps['allow_text_align'] ); ?> /> <?php esc_html_e( 'Alignment', 'wc-generic-product-designer' ); ?></label></p>
			</div>
			<div class="wc-gpd-settings-card">
				<h4><?php esc_html_e( 'Content', 'wc-generic-product-designer' ); ?></h4>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_free_text" value="1" <?php checked( $ps['allow_free_text'] ); ?> /> <?php esc_html_e( 'Allow customer to add free text', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_customer_graphics" value="1" <?php checked( $ps['allow_customer_graphics'] ); ?> /> <?php esc_html_e( 'Allow customer graphic picker', 'wc-generic-product-designer' ); ?></label></p>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array $ps Settings.
	 */
	private function render_template_settings( $ps ) {
		?>
		<div class="wc-gpd-settings-grid">
			<div class="wc-gpd-settings-card">
				<h4><?php esc_html_e( 'Designer', 'wc-generic-product-designer' ); ?></h4>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_enable_popout" value="1" <?php checked( $ps['enable_popout'] ); ?> /> <?php esc_html_e( 'Pop-out / expand mode', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-color"><?php esc_html_e( 'Canvas background', 'wc-generic-product-designer' ); ?> <input type="color" name="wc_gpd_ps_canvas_bg_color" value="<?php echo esc_attr( $ps['canvas_bg_color'] ); ?>" /></label></p>
			</div>
			<div class="wc-gpd-settings-card">
				<h4><?php esc_html_e( 'Editor defaults', 'wc-generic-product-designer' ); ?></h4>
				<p><label class="wc-gpd-settings-color"><?php esc_html_e( 'Outline color', 'wc-generic-product-designer' ); ?> <input type="color" id="wc_gpd_ps_outline_color" name="wc_gpd_ps_outline_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" /></label></p>
				<p><label><?php esc_html_e( 'Outline width', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_ps_outline_stroke_width" name="wc_gpd_ps_outline_stroke_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" /></label></p>
				<p><label class="wc-gpd-settings-color"><?php esc_html_e( 'BBox color', 'wc-generic-product-designer' ); ?> <input type="color" id="wc_gpd_ps_bbox_stroke_color" name="wc_gpd_ps_bbox_stroke_color" value="<?php echo esc_attr( $ps['bbox_stroke_color'] ); ?>" /></label></p>
				<p><label><?php esc_html_e( 'BBox width', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_ps_bbox_stroke_width" name="wc_gpd_ps_bbox_stroke_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['bbox_stroke_width'] ); ?>" /></label></p>
			</div>
			<div class="wc-gpd-settings-card">
				<h4><?php esc_html_e( 'Production export', 'wc-generic-product-designer' ); ?></h4>
				<p><label class="wc-gpd-settings-color"><?php esc_html_e( 'Outline color', 'wc-generic-product-designer' ); ?> <input type="color" name="wc_gpd_ps_export_outline_color" value="<?php echo esc_attr( $ps['export_outline_color'] ); ?>" /></label></p>
				<p><label><?php esc_html_e( 'Outline width', 'wc-generic-product-designer' ); ?> <input type="number" name="wc_gpd_ps_export_outline_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['export_outline_width'] ); ?>" /></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_export_hairline_outline" value="1" <?php checked( $ps['export_hairline_outline'] ); ?> /> <?php esc_html_e( 'Hairline on export', 'wc-generic-product-designer' ); ?></label></p>
			</div>
		</div>
		<?php
	}
}
