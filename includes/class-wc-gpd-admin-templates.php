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
			'wc-gpd-studio-shell',
			WC_GPD_PLUGIN_URL . 'assets/css/studio-shell.css',
			array(),
			WC_GPD_VERSION
		);
		wp_enqueue_style(
			'wc-gpd-admin-product',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-product.css',
			array( 'wc-gpd-admin-templates', 'wc-gpd-studio-shell' ),
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
		$template_fonts_json    = wp_json_encode( $settings['template_fonts'] );
		$template_palettes_json = wp_json_encode( ! empty( $settings['template_palettes'] ) ? $settings['template_palettes'] : WC_GPD_Design_Template::default_palettes_data() );
		$font_options           = WC_GPD_Font_Registry::fonts_for_template( $settings['id'] );
		?>
		<div class="wc-gpd-template-editor-wrap wc-gpd-modern-studio-root" id="wc-gpd-template-editor-root">
			<textarea id="wc_gpd_template_json" name="wc_gpd_template_json" class="wc-gpd-template-json-field" aria-hidden="true"><?php echo esc_textarea( $template_json ); ?></textarea>
			<textarea id="wc_gpd_template_fonts" name="wc_gpd_template_fonts" class="wc-gpd-template-json-field" aria-hidden="true"><?php echo esc_textarea( $template_fonts_json ? $template_fonts_json : '[]' ); ?></textarea>
			<textarea id="wc_gpd_template_palettes" name="wc_gpd_template_palettes" class="wc-gpd-template-json-field" aria-hidden="true"><?php echo esc_textarea( $template_palettes_json ? $template_palettes_json : '{}' ); ?></textarea>
			<input type="hidden" id="wc_gpd_template_canvas_width" value="<?php echo esc_attr( (string) $settings['width'] ); ?>" />
			<input type="hidden" id="wc_gpd_template_canvas_height" value="<?php echo esc_attr( (string) $settings['height'] ); ?>" />
			<input type="hidden" id="wc_gpd_max_design_views" name="wc_gpd_max_design_views" value="<?php echo esc_attr( (string) $settings['max_views'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_outline_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_outline_width" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_bbox_color" value="<?php echo esc_attr( $ps['bbox_stroke_color'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_default_bbox_width" value="<?php echo esc_attr( (string) $ps['bbox_stroke_width'] ); ?>" />
			<input type="hidden" id="wc_gpd_tpl_canvas_bg" value="<?php echo esc_attr( $ps['canvas_bg_color'] ); ?>" />
			<header class="wc-gpd-studio-chrome wc-gpd-tpl-studio-chrome">
				<div class="wc-gpd-studio-chrome__left">
					<span class="wc-gpd-studio-chrome__product"><?php esc_html_e( 'Template designer', 'wc-generic-product-designer' ); ?></span>
				</div>
				<div class="wc-gpd-studio-chrome__center">
					<div class="wc-gpd-template-view-tabs" id="wc-gpd-template-view-tabs" role="tablist"></div>
				</div>
				<div class="wc-gpd-studio-chrome__right wc-gpd-tpl-header-actions">
					<div class="wc-gpd-tpl-canvas-size-inline" title="<?php esc_attr_e( 'Production canvas size in pixels', 'wc-generic-product-designer' ); ?>">
						<span class="wc-gpd-tpl-canvas-size-label"><?php esc_html_e( 'Canvas', 'wc-generic-product-designer' ); ?></span>
						<input type="number" id="wc_gpd_canvas_width" name="wc_gpd_canvas_width" class="wc-gpd-tpl-size-input" min="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MIN_DIMENSION ); ?>" max="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MAX_DIMENSION ); ?>" value="<?php echo esc_attr( (string) $settings['width'] ); ?>" aria-label="<?php esc_attr_e( 'Canvas width in pixels', 'wc-generic-product-designer' ); ?>" />
						<span class="wc-gpd-tpl-size-sep" aria-hidden="true">×</span>
						<input type="number" id="wc_gpd_canvas_height" name="wc_gpd_canvas_height" class="wc-gpd-tpl-size-input" min="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MIN_DIMENSION ); ?>" max="<?php echo esc_attr( (string) WC_GPD_Product_Meta::MAX_DIMENSION ); ?>" value="<?php echo esc_attr( (string) $settings['height'] ); ?>" aria-label="<?php esc_attr_e( 'Canvas height in pixels', 'wc-generic-product-designer' ); ?>" />
						<span class="wc-gpd-tpl-size-unit">px</span>
					</div>
					<button type="button" class="button button-small" id="wc-gpd-template-add-view"><?php esc_html_e( 'Add area', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-small" id="wc-gpd-template-rename-view"><?php esc_html_e( 'Rename', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-small" id="wc-gpd-template-delete-view" title="<?php esc_attr_e( 'Remove this design area', 'wc-generic-product-designer' ); ?>"><?php esc_html_e( 'Delete area', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-small wc-gpd-popout-trigger" id="wc-gpd-template-popout"><?php esc_html_e( 'Expand', 'wc-generic-product-designer' ); ?></button>
				</div>
			</header>
			<div class="wc-gpd-modern-studio wc-gpd-tpl-layout">
				<nav class="wc-gpd-studio-nav" id="wc-gpd-admin-studio-nav" aria-label="<?php esc_attr_e( 'Template tools', 'wc-generic-product-designer' ); ?>">
					<button type="button" class="wc-gpd-studio-nav__btn wc-gpd-studio-nav__btn--add is-active" data-section="add"><span class="wc-gpd-studio-nav__icon">+</span><span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Add', 'wc-generic-product-designer' ); ?></span></button>
					<button type="button" class="wc-gpd-studio-nav__btn" data-section="layers"><span class="wc-gpd-studio-nav__icon">☰</span><span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Layers', 'wc-generic-product-designer' ); ?></span></button>
					<button type="button" class="wc-gpd-studio-nav__btn" data-section="context" id="wc-gpd-nav-context" hidden><span class="wc-gpd-studio-nav__icon">✎</span><span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Edit', 'wc-generic-product-designer' ); ?></span></button>
					<button type="button" class="wc-gpd-studio-nav__btn" data-section="template"><span class="wc-gpd-studio-nav__icon">⚙</span><span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Template', 'wc-generic-product-designer' ); ?></span></button>
				</nav>
				<aside class="wc-gpd-studio-drawer">
					<div class="wc-gpd-studio-drawer__head">
						<h2 class="wc-gpd-studio-drawer__title" id="wc-gpd-admin-drawer-title"><?php esc_html_e( 'Add', 'wc-generic-product-designer' ); ?></h2>
					</div>
					<div class="wc-gpd-studio-drawer__body">
				<aside class="wc-gpd-tpl-accordion" id="wc-gpd-tpl-accordion">
					<div class="wc-gpd-accordion-section is-open" data-section="add">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="true"><?php esc_html_e( 'Add to design', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body">
							<p class="wc-gpd-tpl-panel-desc"><?php esc_html_e( 'Choose what to add to the current design area.', 'wc-generic-product-designer' ); ?></p>
							<div class="wc-gpd-add-menu wc-gpd-add-menu--collapsible">
								<div class="wc-gpd-add-menu__group">
									<button type="button" class="wc-gpd-add-menu__toggle" aria-expanded="false"><?php esc_html_e( 'Text', 'wc-generic-product-designer' ); ?></button>
									<div class="wc-gpd-add-menu__body" hidden>
										<button type="button" class="button button-small wc-gpd-add-menu__btn" id="wc-gpd-template-add-text"><?php esc_html_e( 'Text field', 'wc-generic-product-designer' ); ?></button>
									</div>
								</div>
								<div class="wc-gpd-add-menu__group">
									<button type="button" class="wc-gpd-add-menu__toggle" aria-expanded="false"><?php esc_html_e( 'Images & graphics', 'wc-generic-product-designer' ); ?></button>
									<div class="wc-gpd-add-menu__body" hidden>
										<button type="button" class="button button-small wc-gpd-add-menu__btn" id="wc-gpd-template-add-image"><?php esc_html_e( 'Image from library', 'wc-generic-product-designer' ); ?></button>
										<button type="button" class="button button-small wc-gpd-add-menu__btn" id="wc-gpd-template-add-graphic-slot"><?php esc_html_e( 'Customer pick area', 'wc-generic-product-designer' ); ?></button>
									</div>
								</div>
								<div class="wc-gpd-add-menu__group">
									<button type="button" class="wc-gpd-add-menu__toggle" aria-expanded="false"><?php esc_html_e( 'Shapes', 'wc-generic-product-designer' ); ?></button>
									<div class="wc-gpd-add-menu__body" hidden>
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
									</div>
								</div>
								<div class="wc-gpd-add-menu__group">
									<button type="button" class="wc-gpd-add-menu__toggle" aria-expanded="false"><?php esc_html_e( 'Icons', 'wc-generic-product-designer' ); ?></button>
									<div class="wc-gpd-add-menu__body" hidden>
										<div class="wc-gpd-shape-library-grid wc-gpd-bootstrap-icon-featured" id="wc-gpd-bootstrap-icon-featured"></div>
										<div class="wc-gpd-bootstrap-icons-toolbar">
											<input type="search" id="wc-gpd-bootstrap-icon-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search icons…', 'wc-generic-product-designer' ); ?>" />
											<button type="button" class="button button-small" id="wc-gpd-bootstrap-icon-search-btn"><?php esc_html_e( 'Search', 'wc-generic-product-designer' ); ?></button>
										</div>
										<p class="description" id="wc-gpd-bootstrap-icon-status" hidden></p>
										<div class="wc-gpd-shape-library-grid wc-gpd-bootstrap-icon-results" id="wc-gpd-bootstrap-icon-results"></div>
										<p class="wc-gpd-bootstrap-icon-load-more" id="wc-gpd-bootstrap-icon-load-more-wrap" hidden>
											<button type="button" class="button button-small" id="wc-gpd-bootstrap-icon-load-more"><?php esc_html_e( 'Load more', 'wc-generic-product-designer' ); ?></button>
										</p>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="wc-gpd-accordion-section" data-section="layers" data-layer-title="0">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="true" data-base-title="<?php esc_attr_e( 'Layers', 'wc-generic-product-designer' ); ?>"><?php esc_html_e( 'Layers', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body">
							<ul class="wc-gpd-tpl-layers-list" id="wc-gpd-template-layers-list"></ul>
							<p class="wc-gpd-tpl-hint" id="wc-gpd-layers-empty-hint"><?php esc_html_e( 'Layers appear here as you add content.', 'wc-generic-product-designer' ); ?></p>
						</div>
					</div>
					<div class="wc-gpd-accordion-section" data-section="context" data-layer-title="1">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="false" data-base-title="<?php esc_attr_e( 'Properties', 'wc-generic-product-designer' ); ?>"><?php esc_html_e( 'Properties', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body" hidden>
						<p class="wc-gpd-context-empty" id="wc-gpd-context-empty"><?php esc_html_e( 'Select a layer on the canvas to edit its properties, or use Add to create something new.', 'wc-generic-product-designer' ); ?></p>
						<div class="wc-gpd-context-pane" id="wc-gpd-context-pane" hidden>
						<p class="wc-gpd-context-layer-name" id="wc-gpd-context-layer-name"></p>
						<div class="wc-gpd-context-accordion is-open wc-gpd-context-block--customer" id="wc-gpd-context-block-customer" data-context-for="text,image,shape,slot">
							<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Customer access', 'wc-generic-product-designer' ); ?></button>
							<div class="wc-gpd-context-accordion__body">
							<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_customer_editable" checked="checked" /> <?php esc_html_e( 'Customer can edit this layer', 'wc-generic-product-designer' ); ?></label>
							<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_show_in_customer_layers" checked="checked" /> <?php esc_html_e( 'Show in customer layers list', 'wc-generic-product-designer' ); ?></label>
							<div class="wc-gpd-customer-access-type" id="wc-gpd-customer-access-text" data-access-for="text" hidden>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_text_customer_fills" /> <?php esc_html_e( 'Customer enters text in Details panel', 'wc-generic-product-designer' ); ?></label>
								<fieldset class="wc-gpd-tpl-customer-options">
									<legend><?php esc_html_e( 'Customer can change', 'wc-generic-product-designer' ); ?></legend>
									<div class="wc-gpd-tpl-customer-grid">
										<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_text_edit" checked="checked" /> <?php esc_html_e( 'Text on canvas', 'wc-generic-product-designer' ); ?></label>
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
										<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_allow_resize" checked="checked" /> <?php esc_html_e( 'Resize box', 'wc-generic-product-designer' ); ?></label>
									</div>
								</fieldset>
							</div>
							<div class="wc-gpd-customer-access-type" id="wc-gpd-customer-access-image" data-access-for="image" hidden>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_allow_move" checked="checked" /> <?php esc_html_e( 'Customer can move', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_allow_resize" checked="checked" /> <?php esc_html_e( 'Customer can resize', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_graphic_lock_aspect" /> <?php esc_html_e( 'Lock aspect ratio', 'wc-generic-product-designer' ); ?></label>
							</div>
							<div class="wc-gpd-customer-access-type" id="wc-gpd-customer-access-shape" data-access-for="shape" hidden>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_shape_allow_color" checked="checked" /> <?php esc_html_e( 'Customer can change color', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_shape_allow_move" checked="checked" /> <?php esc_html_e( 'Customer can move', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_shape_allow_resize" checked="checked" /> <?php esc_html_e( 'Customer can resize', 'wc-generic-product-designer' ); ?></label>
								<p class="wc-gpd-tpl-subheading"><?php esc_html_e( 'Production role', 'wc-generic-product-designer' ); ?></p>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_customer_shape_outline" checked="checked" /> <?php esc_html_e( 'Cut line (production outline)', 'wc-generic-product-designer' ); ?></label>
								<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_customer_shape_bbox" /> <?php esc_html_e( 'Bounding box guide', 'wc-generic-product-designer' ); ?></label>
							</div>
							<div class="wc-gpd-customer-access-type" id="wc-gpd-customer-access-slot" data-access-for="slot" hidden>
								<p class="description"><?php esc_html_e( 'Customers pick a graphic inside this area. Use image settings on placed graphics for move/resize.', 'wc-generic-product-designer' ); ?></p>
							</div>
							</div>
						</div>
						<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-dims" data-context-for="all">
							<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Size & position', 'wc-generic-product-designer' ); ?></button>
							<div class="wc-gpd-context-accordion__body">
							<div class="wc-gpd-dims-compact" id="wc-gpd-selection-dims-panel">
								<label class="wc-gpd-dims-compact__units"><?php esc_html_e( 'Units', 'wc-generic-product-designer' ); ?>
									<select id="wc_gpd_tpl_units">
										<option value="px">px</option>
										<option value="in">in</option>
										<option value="mm">mm</option>
										<option value="cm">cm</option>
									</select>
								</label>
								<div class="wc-gpd-dims-compact__row">
									<label>W <input type="number" id="wc_gpd_sel_width" min="0.01" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_w">px</span></label>
									<label>H <input type="number" id="wc_gpd_sel_height" min="0.01" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_h">px</span></label>
								</div>
								<div class="wc-gpd-dims-compact__row">
									<label>X <input type="number" id="wc_gpd_sel_left" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_x">px</span></label>
									<label>Y <input type="number" id="wc_gpd_sel_top" step="0.01" /><span class="wc-gpd-unit-suffix" id="wc_gpd_unit_suffix_y">px</span></label>
								</div>
								<label class="wc-gpd-tpl-check wc-gpd-dims-compact__lock"><input type="checkbox" id="wc_gpd_lock_aspect" /> <?php esc_html_e( 'Lock aspect', 'wc-generic-product-designer' ); ?></label>
							</div>
							</div>
						</div>
						<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-colors" data-context-for="text,shape" hidden>
							<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Colors', 'wc-generic-product-designer' ); ?></button>
							<div class="wc-gpd-context-accordion__body">
							<div id="wc-gpd-layer-colors-panel">
								<p><label><?php esc_html_e( 'Palette for layer', 'wc-generic-product-designer' ); ?> <select id="wc_gpd_layer_palette_id"></select></label></p>
								<div class="wc-gpd-layer-color-swatches" id="wc-gpd-layer-color-swatches"></div>
							</div>
							</div>
						</div>
						<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-text" data-context-for="text" hidden>
							<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Text', 'wc-generic-product-designer' ); ?></button>
							<div class="wc-gpd-context-accordion__body">
							<div class="wc-gpd-tpl-text-content-row">
								<label for="wc_gpd_tpl_text_content"><?php esc_html_e( 'Text content', 'wc-generic-product-designer' ); ?></label>
								<textarea id="wc_gpd_tpl_text_content" class="wc-gpd-rich-textarea wc-gpd-tpl-text-content-input" rows="4" placeholder="<?php esc_attr_e( 'Type your text…', 'wc-generic-product-designer' ); ?>"></textarea>
								<p class="description"><?php esc_html_e( 'Edit here or double-click the text on the canvas.', 'wc-generic-product-designer' ); ?></p>
							</div>
						<div class="wc-gpd-tpl-selection wc-gpd-tpl-text-editor" id="wc-gpd-text-editor">
							<p><label><?php esc_html_e( 'Layer label', 'wc-generic-product-designer' ); ?> <input type="text" id="wc_gpd_text_layer_label" class="widefat" /></label></p>
							<p><label><?php esc_html_e( 'Box width (px)', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_placeholder_width" min="40" max="2000" value="240" /></label></p>
							<p>
								<label><?php esc_html_e( 'Fit to box', 'wc-generic-product-designer' ); ?>
									<select id="wc_gpd_tpl_fit_mode">
										<option value="none"><?php esc_html_e( 'None', 'wc-generic-product-designer' ); ?></option>
										<option value="horizontal"><?php esc_html_e( 'Horizontal', 'wc-generic-product-designer' ); ?></option>
										<option value="vertical"><?php esc_html_e( 'Vertical', 'wc-generic-product-designer' ); ?></option>
										<option value="both"><?php esc_html_e( 'Horizontal & vertical', 'wc-generic-product-designer' ); ?></option>
									</select>
								</label>
							</p>
							<div class="wc-gpd-rich-text-box">
								<label class="wc-gpd-rich-label"><?php esc_html_e( 'Formatting', 'wc-generic-product-designer' ); ?></label>
								<div class="wc-gpd-rich-toolbar" aria-label="<?php esc_attr_e( 'Text formatting', 'wc-generic-product-designer' ); ?>">
									<div class="wc-gpd-rich-toolbar-row">
										<select id="wc_gpd_tpl_font_family" class="wc-gpd-rich-font-select" title="<?php esc_attr_e( 'Font family', 'wc-generic-product-designer' ); ?>"></select>
										<input type="number" id="wc_gpd_tpl_font_size" class="wc-gpd-rich-size wc-gpd-rich-size--compact" min="8" max="400" value="32" title="<?php esc_attr_e( 'Font size', 'wc-generic-product-designer' ); ?>" />
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
										<div class="wc-gpd-stepper" title="<?php esc_attr_e( 'Line height', 'wc-generic-product-designer' ); ?>">
											<button type="button" class="wc-gpd-stepper-btn" data-stepper="line_height" data-dir="-1" aria-label="<?php esc_attr_e( 'Decrease line height', 'wc-generic-product-designer' ); ?>">−</button>
											<span class="wc-gpd-stepper-val" id="wc_gpd_tpl_line_height_display">1.16</span>
											<button type="button" class="wc-gpd-stepper-btn" data-stepper="line_height" data-dir="1" aria-label="<?php esc_attr_e( 'Increase line height', 'wc-generic-product-designer' ); ?>">+</button>
											<input type="hidden" id="wc_gpd_tpl_line_height" value="1.16" />
										</div>
										<div class="wc-gpd-stepper" title="<?php esc_attr_e( 'Letter spacing', 'wc-generic-product-designer' ); ?>">
											<button type="button" class="wc-gpd-stepper-btn" data-stepper="letter_spacing" data-dir="-1" aria-label="<?php esc_attr_e( 'Decrease letter spacing', 'wc-generic-product-designer' ); ?>">−</button>
											<span class="wc-gpd-stepper-val" id="wc_gpd_tpl_letter_spacing_display">0</span>
											<button type="button" class="wc-gpd-stepper-btn" data-stepper="letter_spacing" data-dir="1" aria-label="<?php esc_attr_e( 'Increase letter spacing', 'wc-generic-product-designer' ); ?>">+</button>
											<input type="hidden" id="wc_gpd_tpl_letter_spacing" value="0" />
										</div>
									</div>
								</div>
							</div>
						</div>
							</div>
						</div>
						<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-image" data-context-for="image" hidden>
							<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Image', 'wc-generic-product-designer' ); ?></button>
							<div class="wc-gpd-context-accordion__body">
							<div class="wc-gpd-tpl-selection" id="wc-gpd-image-props">
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
								<div id="wc-gpd-image-customer-options" hidden></div>
								<button type="button" class="button button-link-delete" id="wc-gpd-template-delete-image"><?php esc_html_e( 'Remove image', 'wc-generic-product-designer' ); ?></button>
							</div>
							</div>
						</div>
						<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-slot" data-context-for="slot" hidden>
							<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Graphic pick area', 'wc-generic-product-designer' ); ?></button>
							<div class="wc-gpd-context-accordion__body">
							<div class="wc-gpd-tpl-selection" id="wc-gpd-graphic-slot-props">
								<p><label><?php esc_html_e( 'Graphic library', 'wc-generic-product-designer' ); ?> <select id="wc_gpd_slot_library_id"></select></label></p>
								<p class="description"><?php esc_html_e( 'Customers choose a graphic and can move or resize it within this box.', 'wc-generic-product-designer' ); ?></p>
								<button type="button" class="button button-link-delete" id="wc-gpd-template-delete-slot"><?php esc_html_e( 'Remove pick area', 'wc-generic-product-designer' ); ?></button>
							</div>
							</div>
						</div>
						<div class="wc-gpd-context-accordion is-open" id="wc-gpd-context-block-shape" data-context-for="shape" hidden>
							<button type="button" class="wc-gpd-context-accordion__toggle" aria-expanded="true"><?php esc_html_e( 'Shape appearance', 'wc-generic-product-designer' ); ?></button>
							<div class="wc-gpd-context-accordion__body">
							<div class="wc-gpd-tpl-selection" id="wc-gpd-shape-props-fields">
								<p class="wc-gpd-context-field"><label><?php esc_html_e( 'Color', 'wc-generic-product-designer' ); ?> <input type="color" id="wc_gpd_template_stroke_color" value="<?php echo esc_attr( $ps['outline_color'] ); ?>" /></label></p>
								<p class="wc-gpd-context-field" id="wc-gpd-shape-stroke-width-row"><label><?php esc_html_e( 'Line thickness', 'wc-generic-product-designer' ); ?> <input type="number" id="wc_gpd_template_stroke_width" min="0.1" max="20" step="0.1" value="<?php echo esc_attr( (string) $ps['outline_stroke_width'] ); ?>" /></label></p>
								<p class="description" id="wc-gpd-shape-fill-note" hidden><?php esc_html_e( 'Filled icons use solid color. Line thickness applies to outline shapes.', 'wc-generic-product-designer' ); ?></p>
								<p class="wc-gpd-context-field"><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_template_is_outline" checked="checked" /> <?php esc_html_e( 'Production outline (cut line)', 'wc-generic-product-designer' ); ?></label></p>
								<p class="wc-gpd-context-field"><label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_template_is_bbox" /> <?php esc_html_e( 'Bounding box guide', 'wc-generic-product-designer' ); ?></label></p>
							</div>
							</div>
						</div>
						</div>
						</div>
					</div>
					<div class="wc-gpd-accordion-section" data-section="template" data-layer-title="0">
						<button type="button" class="wc-gpd-accordion-toggle" aria-expanded="false" data-base-title="<?php esc_attr_e( 'Template settings', 'wc-generic-product-designer' ); ?>"><?php esc_html_e( 'Template settings', 'wc-generic-product-designer' ); ?></button>
						<div class="wc-gpd-accordion-body" hidden>
							<h5 class="wc-gpd-tpl-subheading"><?php esc_html_e( 'Fonts', 'wc-generic-product-designer' ); ?></h5>
							<p class="wc-gpd-tpl-panel-desc"><?php esc_html_e( 'Choose fonts for this template.', 'wc-generic-product-designer' ); ?></p>
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
							<h5 class="wc-gpd-tpl-subheading"><?php esc_html_e( 'Color palettes', 'wc-generic-product-designer' ); ?></h5>
							<div id="wc-gpd-palettes-admin">
								<p class="description"><?php esc_html_e( 'Create palettes for customer color choices. Enable “same colors on entire template” in Settings for one global palette.', 'wc-generic-product-designer' ); ?></p>
								<div id="wc-gpd-palettes-list"></div>
								<button type="button" class="button button-small" id="wc-gpd-add-palette"><?php esc_html_e( 'Add palette', 'wc-generic-product-designer' ); ?></button>
							</div>
							<h5 class="wc-gpd-tpl-subheading"><?php esc_html_e( 'Canvas guides', 'wc-generic-product-designer' ); ?></h5>
							<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_tpl_show_ruler" /> <?php esc_html_e( 'Show ruler', 'wc-generic-product-designer' ); ?></label>
							<label class="wc-gpd-tpl-check"><input type="checkbox" id="wc_gpd_tpl_show_measurements" /> <?php esc_html_e( 'Show measurements', 'wc-generic-product-designer' ); ?></label>
						</div>
					</div>
				</aside>
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
		<div class="wc-gpd-customer-tools-layout">
			<div class="wc-gpd-customer-mockup-wrap">
				<h4><?php esc_html_e( 'Customer preview', 'wc-generic-product-designer' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Live preview of the customer designer. Select a layer on the canvas, then use the ⚙ settings on that layer to control what shoppers can change—the preview updates automatically.', 'wc-generic-product-designer' ); ?></p>
				<div class="wc-gpd-customer-mockup wc-gpd-mockup-studio wc-gpd-modern-studio-root" id="wc-gpd-customer-mockup">
					<div class="wc-gpd-mockup-studio__shell">
						<nav class="wc-gpd-studio-nav wc-gpd-mockup-nav" id="wc-gpd-mockup-nav" aria-label="<?php esc_attr_e( 'Preview designer tools', 'wc-generic-product-designer' ); ?>">
							<button type="button" class="wc-gpd-studio-nav__btn wc-gpd-studio-nav__btn--add is-active" data-mockup-nav="add"><span class="wc-gpd-studio-nav__icon">+</span><span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Add', 'wc-generic-product-designer' ); ?></span></button>
							<button type="button" class="wc-gpd-studio-nav__btn" data-mockup-nav="layers" data-mockup="layers"><span class="wc-gpd-studio-nav__icon">☰</span><span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Layers', 'wc-generic-product-designer' ); ?></span></button>
							<button type="button" class="wc-gpd-studio-nav__btn" data-mockup-nav="details" data-mockup="details" hidden><span class="wc-gpd-studio-nav__icon">✎</span><span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Details', 'wc-generic-product-designer' ); ?></span></button>
							<button type="button" class="wc-gpd-studio-nav__btn" data-mockup-nav="context" data-mockup-edit-nav hidden><span class="wc-gpd-studio-nav__icon">✎</span><span class="wc-gpd-studio-nav__label"><?php esc_html_e( 'Edit', 'wc-generic-product-designer' ); ?></span></button>
						</nav>
						<aside class="wc-gpd-studio-drawer wc-gpd-mockup-drawer">
							<div class="wc-gpd-studio-drawer__head">
								<h2 class="wc-gpd-studio-drawer__title" id="wc-gpd-mockup-drawer-title"><?php esc_html_e( 'Add', 'wc-generic-product-designer' ); ?></h2>
							</div>
							<div class="wc-gpd-studio-drawer__body">
								<div class="wc-gpd-mockup-panel is-active" data-mockup-panel="add">
									<p class="wc-gpd-tpl-panel-desc"><?php esc_html_e( 'Add elements to your design.', 'wc-generic-product-designer' ); ?></p>
									<button type="button" class="button button-small wc-gpd-mockup-control" data-mockup="free_text" disabled><?php esc_html_e( 'Add text', 'wc-generic-product-designer' ); ?></button>
								</div>
								<div class="wc-gpd-mockup-panel" data-mockup-panel="layers" hidden>
									<ul class="wc-gpd-mockup-layers">
										<li><?php esc_html_e( 'Your text', 'wc-generic-product-designer' ); ?></li>
									</ul>
								</div>
								<div class="wc-gpd-mockup-panel" data-mockup-panel="details" hidden>
									<div class="wc-gpd-mockup-field" id="wc-gpd-mockup-fields" data-mockup="details">
										<label><?php esc_html_e( 'Name', 'wc-generic-product-designer' ); ?></label>
										<input type="text" class="regular-text" value="<?php esc_attr_e( 'Sample', 'wc-generic-product-designer' ); ?>" disabled />
									</div>
									<div class="wc-gpd-mockup-graphics" data-mockup="graphics">
										<span class="wc-gpd-mockup-graphic-thumb"></span>
										<span class="wc-gpd-mockup-graphic-thumb"></span>
									</div>
								</div>
								<div class="wc-gpd-mockup-panel" data-mockup-panel="context" hidden>
									<p class="wc-gpd-context-layer-name"><?php esc_html_e( 'Your text', 'wc-generic-product-designer' ); ?></p>
									<div class="wc-gpd-mockup-tools">
										<div class="wc-gpd-mockup-tools-row">
											<span class="wc-gpd-mockup-pill" data-mockup="font"><?php esc_html_e( 'Font', 'wc-generic-product-designer' ); ?></span>
											<span class="wc-gpd-mockup-pill" data-mockup="size">32</span>
										</div>
										<div class="wc-gpd-mockup-tools-row">
											<span class="wc-gpd-mockup-pill" data-mockup="bold"><strong>B</strong></span>
											<span class="wc-gpd-mockup-pill" data-mockup="italic"><em>I</em></span>
											<span class="wc-gpd-mockup-pill" data-mockup="underline"><span class="wc-gpd-u">U</span></span>
											<span class="wc-gpd-mockup-pill" data-mockup="align">L C R</span>
											<span class="wc-gpd-mockup-swatch" data-mockup="color"></span>
										</div>
										<div class="wc-gpd-mockup-tools-row">
											<span class="wc-gpd-mockup-pill wc-gpd-mockup-pill--mini" data-mockup="line_height"><?php esc_html_e( 'Line', 'wc-generic-product-designer' ); ?> 1.16</span>
											<span class="wc-gpd-mockup-pill wc-gpd-mockup-pill--mini" data-mockup="letter_spacing"><?php esc_html_e( 'Space', 'wc-generic-product-designer' ); ?> 0</span>
										</div>
									</div>
								</div>
							</div>
						</aside>
						<main class="wc-gpd-mockup-canvas-area">
							<div class="wc-gpd-mockup-canvas-stage">
								<div class="wc-gpd-mockup-canvas-inner">
									<span class="wc-gpd-mockup-sample-text"><?php esc_html_e( 'Your text', 'wc-generic-product-designer' ); ?></span>
								</div>
							</div>
						</main>
					</div>
				</div>
			</div>
		<div class="wc-gpd-settings-grid wc-gpd-settings-grid--customer-global">
			<div class="wc-gpd-settings-card wc-gpd-settings-card--wide">
				<h4><?php esc_html_e( 'Global customer options', 'wc-generic-product-designer' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Per-layer permissions are set with the ⚙ button on each layer in the template designer (or Edit panel when a layer is selected). These options apply to the whole product.', 'wc-generic-product-designer' ); ?></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_free_text" value="1" <?php checked( $ps['allow_free_text'] ); ?> /> <?php esc_html_e( 'Allow customers to add their own text', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_layers_panel" value="1" <?php checked( $ps['allow_layers_panel'] ); ?> /> <?php esc_html_e( 'Show layers panel in designer', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_details_panel" value="1" <?php checked( $ps['allow_details_panel'] ); ?> /> <?php esc_html_e( 'Show details panel (variable fields)', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_customer_graphics" value="1" <?php checked( $ps['allow_customer_graphics'] ); ?> /> <?php esc_html_e( 'Show graphic picker in details', 'wc-generic-product-designer' ); ?></label></p>
			</div>
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
			<div class="wc-gpd-settings-card wc-gpd-settings-card--colors">
				<h4><?php esc_html_e( 'Template colors', 'wc-generic-product-designer' ); ?></h4>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_use_same_colors" value="1" id="wc_gpd_ps_use_same_colors" <?php checked( ! empty( $ps['use_same_colors_entire_template'] ) ); ?> /> <?php esc_html_e( 'Use same colors on entire template', 'wc-generic-product-designer' ); ?></label></p>
				<div id="wc-gpd-global-colors-panel" <?php echo empty( $ps['use_same_colors_entire_template'] ) ? 'hidden' : ''; ?>>
					<p class="description"><?php esc_html_e( 'These colors apply to all layers. At least one color is required.', 'wc-generic-product-designer' ); ?></p>
					<div id="wc-gpd-global-colors-list" class="wc-gpd-global-colors-list"></div>
					<button type="button" class="button button-small" id="wc-gpd-add-global-color"><?php esc_html_e( 'Add color', 'wc-generic-product-designer' ); ?></button>
				</div>
			</div>
			<div class="wc-gpd-settings-card">
				<h4><?php esc_html_e( 'Designer', 'wc-generic-product-designer' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Customers open a full-screen designer from the Start designing button on the product page.', 'wc-generic-product-designer' ); ?></p>
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
