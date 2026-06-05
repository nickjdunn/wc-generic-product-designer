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
			WC_GPD_Design_Template::save_from_post( $template_id );
			$settings = WC_GPD_Design_Template::get_settings( $template_id );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template saved.', 'wc-generic-product-designer' ) . '</p></div>';
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

			<form method="post" id="wc-gpd-template-form" class="wc-gpd-template-form">
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
		$graphic_library_json = wp_json_encode( array_column( $settings['graphic_library'], 'id' ) );
		?>
		<div class="wc-gpd-template-canvas-settings">
			<div class="wc-gpd-settings-grid wc-gpd-settings-grid--3">
				<div class="wc-gpd-settings-card">
					<h4><?php esc_html_e( 'Production canvas', 'wc-generic-product-designer' ); ?></h4>
					<p class="description"><?php esc_html_e( 'Exact pixel size used on the customer designer and exports.', 'wc-generic-product-designer' ); ?></p>
					<p>
						<label for="wc_gpd_canvas_width"><?php esc_html_e( 'Width (px)', 'wc-generic-product-designer' ); ?></label>
						<input type="number" id="wc_gpd_canvas_width" name="wc_gpd_canvas_width" min="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MIN_DIMENSION ); ?>" max="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MAX_DIMENSION ); ?>" value="<?php echo esc_attr( (string) $settings['width'] ); ?>" />
					</p>
					<p>
						<label for="wc_gpd_canvas_height"><?php esc_html_e( 'Height (px)', 'wc-generic-product-designer' ); ?></label>
						<input type="number" id="wc_gpd_canvas_height" name="wc_gpd_canvas_height" min="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MIN_DIMENSION ); ?>" max="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MAX_DIMENSION ); ?>" value="<?php echo esc_attr( (string) $settings['height'] ); ?>" />
					</p>
					<p>
						<label for="wc_gpd_max_design_views"><?php esc_html_e( 'Maximum design areas', 'wc-generic-product-designer' ); ?></label>
						<input type="number" id="wc_gpd_max_design_views" name="wc_gpd_max_design_views" min="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MIN_VIEWS ); ?>" max="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MAX_VIEWS ); ?>" value="<?php echo esc_attr( (string) $settings['max_views'] ); ?>" />
					</p>
				</div>
			</div>
		</div>
		<div class="wc-gpd-template-editor-wrap" id="wc-gpd-template-editor-root">
			<input type="hidden" id="wc_gpd_template_json" name="wc_gpd_template_json" value="<?php echo esc_attr( $template_json ); ?>" />
			<input type="hidden" id="wc_gpd_graphic_library" name="wc_gpd_graphic_library" value="<?php echo esc_attr( $graphic_library_json ? $graphic_library_json : '[]' ); ?>" />
			<input type="hidden" id="wc_gpd_template_canvas_width" value="<?php echo esc_attr( (string) $settings['width'] ); ?>" />
			<input type="hidden" id="wc_gpd_template_canvas_height" value="<?php echo esc_attr( (string) $settings['height'] ); ?>" />
			<input type="hidden" id="wc_gpd_template_max_views" value="<?php echo esc_attr( (string) $settings['max_views'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_outline_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_outline_width" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_bbox_color" value="<?php echo esc_attr( $ps['bbox_stroke_color'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_bbox_width" value="<?php echo esc_attr( (string) $ps['bbox_stroke_width'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_canvas_bg" value="<?php echo esc_attr( $ps['canvas_bg_color'] ); ?>" />
			<div class="wc-gpd-tpl-header">
				<div class="wc-gpd-template-view-tabs" id="wc-gpd-template-view-tabs" role="tablist"></div>
				<div class="wc-gpd-tpl-header-actions">
					<button type="button" class="button button-small" id="wc-gpd-template-add-view"><?php esc_html_e( 'Add area', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-small" id="wc-gpd-template-rename-view"><?php esc_html_e( 'Rename', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-small wc-gpd-popout-trigger" id="wc-gpd-template-popout"><?php esc_html_e( 'Expand', 'wc-generic-product-designer' ); ?></button>
				</div>
			</div>
			<div class="wc-gpd-tpl-layout">
				<aside class="wc-gpd-tpl-sidebar">
					<div class="wc-gpd-tpl-panel wc-gpd-tpl-panel--layers">
						<h4><?php esc_html_e( 'Layers', 'wc-generic-product-designer' ); ?></h4>
						<ul class="wc-gpd-tpl-layers-list" id="wc-gpd-template-layers-list"></ul>
						<p class="wc-gpd-tpl-hint" id="wc-gpd-layers-empty-hint"><?php esc_html_e( 'Layers appear here as you add content.', 'wc-generic-product-designer' ); ?></p>
					</div>
					<div class="wc-gpd-tpl-panel">
						<h4><?php esc_html_e( 'Text', 'wc-generic-product-designer' ); ?></h4>
						<div class="wc-gpd-tpl-btn-row">
							<button type="button" class="button button-small" id="wc-gpd-template-add-text"><?php esc_html_e( 'Fixed text', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button button-small" id="wc-gpd-template-add-placeholder"><?php esc_html_e( 'Variable field', 'wc-generic-product-designer' ); ?></button>
						</div>
						<div class="wc-gpd-tpl-selection" id="wc-gpd-text-props" hidden>
							<p><label><?php esc_html_e( 'Text', 'wc-generic-product-designer' ); ?> <input type="text" id="wc_gpd_template_text_content" class="widefat" /></label></p>
							<p><label><?php esc_html_e( 'Font', 'wc-generic-product-designer' ); ?> <select id="wc_gpd_template_font_family"><option value="Arial">Arial</option><option value="Georgia">Georgia</option><option value="Times New Roman">Times New Roman</option><option value="Courier New">Courier New</option><option value="Verdana">Verdana</option></select></label></p>
							<p><label><?php esc_html_e( 'Size', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_template_font_size" min="8" max="400" value="32" /></label></p>
							<p><label><?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?> <input type="color" id="wc_gpd_template_text_color" value="#000000" /></label></p>
							<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_template_shrink_fit" /> <?php esc_html_e( 'Shrink text to fit box', 'wc-generic-product-designer' ); ?></label></p>
							<fieldset class="wc-gpd-tpl-locks">
								<legend><?php esc_html_e( 'Lock for customer', 'wc-generic-product-designer' ); ?></legend>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_lock_font" /> <?php esc_html_e( 'Font', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_lock_size" /> <?php esc_html_e( 'Size', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_lock_color" /> <?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_lock_bold" /> <?php esc_html_e( 'Bold', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_lock_italic" /> <?php esc_html_e( 'Italic', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_lock_align" /> <?php esc_html_e( 'Alignment', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_lock_move" /> <?php esc_html_e( 'Position', 'wc-generic-product-designer' ); ?></label>
							</fieldset>
						</div>
						<div class="wc-gpd-tpl-selection" id="wc-gpd-placeholder-props" hidden>
							<p><label><?php esc_html_e( 'Field label', 'wc-generic-product-designer' ); ?> <input type="text" id="wc_gpd_placeholder_label" class="widefat" /></label></p>
							<p><label><?php esc_html_e( 'Field key', 'wc-generic-product-designer' ); ?> <input type="text" id="wc_gpd_placeholder_key" class="widefat" /></label></p>
							<p><label><?php esc_html_e( 'Default / hint', 'wc-generic-product-designer' ); ?> <input type="text" id="wc_gpd_placeholder_default" class="widefat" /></label></p>
							<p><label><?php esc_html_e( 'Box width', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_placeholder_width" min="40" max="2000" value="240" /></label></p>
							<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_placeholder_shrink_fit" /> <?php esc_html_e( 'Shrink text to fit box', 'wc-generic-product-designer' ); ?></label></p>
							<fieldset class="wc-gpd-tpl-locks">
								<legend><?php esc_html_e( 'Lock for customer', 'wc-generic-product-designer' ); ?></legend>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_ph_lock_font" /> <?php esc_html_e( 'Font', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_ph_lock_size" /> <?php esc_html_e( 'Size', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_ph_lock_color" /> <?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_ph_lock_bold" /> <?php esc_html_e( 'Bold', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_ph_lock_italic" /> <?php esc_html_e( 'Italic', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_ph_lock_align" /> <?php esc_html_e( 'Alignment', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_ph_lock_move" /> <?php esc_html_e( 'Position', 'wc-generic-product-designer' ); ?></label>
							</fieldset>
						</div>
					</div>
					<div class="wc-gpd-tpl-panel">
						<h4><?php esc_html_e( 'Graphics', 'wc-generic-product-designer' ); ?></h4>
						<p class="wc-gpd-tpl-panel-desc"><?php esc_html_e( 'Upload artwork customers can pick, or fixed graphics for production files.', 'wc-generic-product-designer' ); ?></p>
						<div class="wc-gpd-tpl-btn-row">
							<button type="button" class="button button-small" id="wc-gpd-template-add-graphic"><?php esc_html_e( 'Fixed graphic', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button button-small" id="wc-gpd-template-add-graphic-slot"><?php esc_html_e( 'Customer pick area', 'wc-generic-product-designer' ); ?></button>
						</div>
						<button type="button" class="button button-small" id="wc-gpd-manage-graphic-library"><?php esc_html_e( 'Manage graphic library', 'wc-generic-product-designer' ); ?></button>
						<ul class="wc-gpd-graphic-library-preview" id="wc-gpd-graphic-library-preview"></ul>
						<div class="wc-gpd-tpl-selection" id="wc-gpd-graphic-props" hidden>
							<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_export" checked="checked" /> <?php esc_html_e( 'Include in production file', 'wc-generic-product-designer' ); ?></label></p>
							<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_lock_move" /> <?php esc_html_e( 'Lock position for customer', 'wc-generic-product-designer' ); ?></label></p>
							<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_lock_scale" /> <?php esc_html_e( 'Lock size for customer', 'wc-generic-product-designer' ); ?></label></p>
							<button type="button" class="button button-link-delete" id="wc-gpd-template-delete-graphic"><?php esc_html_e( 'Remove graphic', 'wc-generic-product-designer' ); ?></button>
						</div>
						<div class="wc-gpd-tpl-selection" id="wc-gpd-graphic-slot-props" hidden>
							<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_slot_lock_move" /> <?php esc_html_e( 'Lock position for customer', 'wc-generic-product-designer' ); ?></label></p>
							<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_slot_lock_scale" /> <?php esc_html_e( 'Lock size for customer', 'wc-generic-product-designer' ); ?></label></p>
							<button type="button" class="button button-link-delete" id="wc-gpd-template-delete-slot"><?php esc_html_e( 'Remove pick area', 'wc-generic-product-designer' ); ?></button>
						</div>
					</div>
					<div class="wc-gpd-tpl-panel">
						<h4><?php esc_html_e( 'Mockup photos', 'wc-generic-product-designer' ); ?></h4>
						<p class="wc-gpd-tpl-panel-desc"><?php esc_html_e( 'Upload slate photos. Drag and resize on canvas.', 'wc-generic-product-designer' ); ?></p>
						<button type="button" class="button button-primary" id="wc-gpd-template-add-image"><?php esc_html_e( 'Add photo', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-tpl-selection" id="wc-gpd-image-props" hidden>
							<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_template_mockup_visible" checked="checked" /> <?php esc_html_e( 'Show in customer mockup', 'wc-generic-product-designer' ); ?></label>
							<button type="button" class="button button-link-delete" id="wc-gpd-template-delete-image"><?php esc_html_e( 'Remove photo', 'wc-generic-product-designer' ); ?></button>
						</div>
					</div>
					<div class="wc-gpd-tpl-panel">
						<h4><?php esc_html_e( 'Outlines', 'wc-generic-product-designer' ); ?></h4>
						<div class="wc-gpd-tpl-btn-row">
							<button type="button" class="button button-small wc-gpd-add-template-rect"><?php esc_html_e( 'Rectangle', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button button-small wc-gpd-add-template-square"><?php esc_html_e( 'Square', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button button-small wc-gpd-add-template-circle"><?php esc_html_e( 'Circle', 'wc-generic-product-designer' ); ?></button>
						</div>
						<div class="wc-gpd-tpl-selection" id="wc-gpd-shape-props-fields" hidden>
							<p><label><?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?> <input type="color" id="wc_gpd_template_stroke_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" /></label></p>
							<p><label><?php esc_html_e( 'Width', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_template_stroke_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" /></label></p>
							<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_template_is_outline" checked="checked" /> <?php esc_html_e( 'Production outline', 'wc-generic-product-designer' ); ?></label></p>
							<p><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_template_is_bbox" /> <?php esc_html_e( 'Bounding box', 'wc-generic-product-designer' ); ?></label></p>
						</div>
						<p class="wc-gpd-tpl-hint" id="wc-gpd-shape-props-hint"><?php esc_html_e( 'Select a layer on the canvas to edit it.', 'wc-generic-product-designer' ); ?></p>
						<button type="button" class="button button-link-delete" id="wc-gpd-template-delete-layer"><?php esc_html_e( 'Delete selected layer', 'wc-generic-product-designer' ); ?></button>
					</div>
				</aside>
				<div class="wc-gpd-tpl-canvas-col">
					<p class="wc-gpd-canvas-size-label" id="wc-gpd-canvas-size-label"><?php echo esc_html( $settings['width'] . ' × ' . $settings['height'] . ' px' ); ?></p>
					<canvas id="wc-gpd-template-canvas" width="<?php echo esc_attr( (string) $settings['width'] ); ?>" height="<?php echo esc_attr( (string) $settings['height'] ); ?>"></canvas>
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
