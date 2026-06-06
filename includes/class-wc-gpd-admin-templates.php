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

	const PAGE_SLUG           = 'wc-gpd-templates';
	const NONCE_ACTION        = 'wc_gpd_save_template';
	const NONCE_NAME          = 'wc_gpd_template_nonce';
	const NONCE_DELETE        = 'wc_gpd_delete_template';
	const NONCE_DELETE_CONFIRM = 'wc_gpd_delete_template_confirm';

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
		$template_settings = $template_id ? WC_GPD_Design_Template::get_settings( $template_id ) : null;
		wp_localize_script(
			'wc-gpd-admin-template-editor',
			'wcGpdTemplateEditor',
			array(
				'maxViews' => WC_GPD_Product_Meta::MAX_VIEWS,
				'fonts'    => WC_GPD_Font_Registry::font_families_for_js( $template_id ),
				'fontOptions' => WC_GPD_Font_Registry::fonts_for_template( $template_id ),
				'defaultFont'   => WC_GPD_Font_Registry::default_font_family(),
				'libraryAssignments' => ( $template_settings && ! empty( $template_settings['library_assignments'] ) )
					? $template_settings['library_assignments']
					: WC_GPD_Design_Template::default_library_assignments(),
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

		if ( isset( $_GET['action'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->process_delete();
			return;
		}

		if ( isset( $_GET['action'] ) && 'delete_confirm' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_delete_confirm_screen();
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

		if ( isset( $_GET['deleted'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['deleted'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template deleted.', 'wc-generic-product-designer' ) . '</p></div>';
		}
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
							<?php
							$product_count = WC_GPD_Design_Template::count_products_using( $row['id'] );
							$delete_link   = $this->get_delete_link( $row['id'], $product_count );
							?>
							<tr>
								<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
								<td><?php echo esc_html( $row['width'] . ' × ' . $row['height'] . ' px' ); ?></td>
								<td><?php echo esc_html( (string) $row['views'] ); ?></td>
								<td><?php echo esc_html( (string) $product_count ); ?></td>
								<td class="wc-gpd-template-actions">
									<a href="<?php echo esc_url( WC_GPD_Design_Template::edit_url( $row['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit template', 'wc-generic-product-designer' ); ?></a>
									<?php if ( $delete_link ) : ?>
										<a href="<?php echo esc_url( $delete_link['url'] ); ?>" class="button button-small button-link-delete wc-gpd-template-delete"<?php echo $delete_link['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Delete', 'wc-generic-product-designer' ); ?></a>
									<?php endif; ?>
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
	 * @param int $template_id   Template ID.
	 * @param int $product_count Assigned product count.
	 * @return array{url:string,attrs:string}|null
	 */
	private function get_delete_link( $template_id, $product_count ) {
		$template_id = absint( $template_id );
		if ( ! $template_id ) {
			return null;
		}

		if ( $product_count > 0 ) {
			return array(
				'url'   => wp_nonce_url(
					add_query_arg(
						array(
							'page'        => self::PAGE_SLUG,
							'action'      => 'delete_confirm',
							'template_id' => $template_id,
						),
						admin_url( 'admin.php' )
					),
					self::NONCE_DELETE . '_' . $template_id
				),
				'attrs' => '',
			);
		}

		$confirm = sprintf(
			/* translators: %s: template name */
			__( 'Delete "%s"? This cannot be undone.', 'wc-generic-product-designer' ),
			get_the_title( $template_id )
		);

		return array(
			'url'   => wp_nonce_url(
				add_query_arg(
					array(
						'page'        => self::PAGE_SLUG,
						'action'      => 'delete',
						'template_id' => $template_id,
					),
					admin_url( 'admin.php' )
				),
				self::NONCE_DELETE . '_' . $template_id
			),
			'attrs' => ' onclick="return confirm(' . wp_json_encode( $confirm ) . ');"',
		);
	}

	/**
	 * Delete an unassigned template (GET + nonce, after JS confirm).
	 */
	private function process_delete() {
		$template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $template_id ) {
			wp_die( esc_html__( 'Template not found.', 'wc-generic-product-designer' ) );
		}

		check_admin_referer( self::NONCE_DELETE . '_' . $template_id );

		if ( WC_GPD_Design_Template::count_products_using( $template_id ) > 0 ) {
			wp_safe_redirect(
				wp_nonce_url(
					add_query_arg(
						array(
							'page'        => self::PAGE_SLUG,
							'action'      => 'delete_confirm',
							'template_id' => $template_id,
						),
						admin_url( 'admin.php' )
					),
					self::NONCE_DELETE . '_' . $template_id
				)
			);
			exit;
		}

		$this->execute_delete( $template_id );
	}

	/**
	 * Confirm deletion when a template is assigned to products.
	 */
	private function render_delete_confirm_screen() {
		$template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $template_id ) {
			wp_die( esc_html__( 'Template not found.', 'wc-generic-product-designer' ) );
		}

		check_admin_referer( self::NONCE_DELETE . '_' . $template_id );

		$settings = WC_GPD_Design_Template::get_settings( $template_id );
		if ( ! $settings ) {
			wp_die( esc_html__( 'Template not found.', 'wc-generic-product-designer' ) );
		}

		if ( isset( $_POST['wc_gpd_confirm_delete'] ) ) {
			check_admin_referer( self::NONCE_DELETE_CONFIRM . '_' . $template_id );
			$this->execute_delete( $template_id );
		}

		$products  = WC_GPD_Design_Template::get_products_using( $template_id );
		$list_url  = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );
		$edit_url  = WC_GPD_Design_Template::edit_url( $template_id );
		?>
		<div class="wrap wc-gpd-templates-wrap">
			<h1><?php esc_html_e( 'Delete template', 'wc-generic-product-designer' ); ?></h1>
			<p>
				<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'All templates', 'wc-generic-product-designer' ); ?></a>
			</p>

			<div class="notice notice-warning">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: template name, 2: number of products */
							_n(
								'"%1$s" is assigned to %2$d product. Delete it anyway? The product will lose its template assignment.',
								'"%1$s" is assigned to %2$d products. Delete it anyway? Those products will lose their template assignment.',
								count( $products ),
								'wc-generic-product-designer'
							),
							$settings['title'],
							count( $products )
						)
					);
					?>
				</p>
			</div>

			<?php if ( ! empty( $products ) ) : ?>
				<ul class="wc-gpd-template-delete-products">
					<?php foreach ( $products as $product ) : ?>
						<li>
							<?php if ( ! empty( $product['edit_url'] ) ) : ?>
								<a href="<?php echo esc_url( $product['edit_url'] ); ?>"><?php echo esc_html( $product['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $product['title'] ); ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<form method="post" class="wc-gpd-template-delete-form">
				<?php wp_nonce_field( self::NONCE_DELETE_CONFIRM . '_' . $template_id ); ?>
				<input type="hidden" name="wc_gpd_confirm_delete" value="1" />
				<p>
					<button type="submit" class="button button-primary button-link-delete"><?php esc_html_e( 'Yes, delete this template', 'wc-generic-product-designer' ); ?></button>
					<a href="<?php echo esc_url( $edit_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'wc-generic-product-designer' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * @param int $template_id Template ID.
	 */
	private function execute_delete( $template_id ) {
		$result = WC_GPD_Design_Template::delete( $template_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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
		$assignments   = ! empty( $settings['library_assignments'] ) ? $settings['library_assignments'] : WC_GPD_Design_Template::default_library_assignments();
		$assignments_json = wp_json_encode( $assignments );
		$template_json = $settings['template_json'];
		if ( '' === trim( $template_json ) ) {
			$template_json = wp_json_encode( WC_GPD_Template_Json::empty_document() );
		}

		$list_url       = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );
		$product_count  = WC_GPD_Design_Template::count_products_using( $template_id );
		$delete_link    = $this->get_delete_link( $template_id, $product_count );
		?>
		<div class="wrap wc-gpd-template-edit-wrap">
			<h1>
				<?php esc_html_e( 'Edit template', 'wc-generic-product-designer' ); ?>
				<a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action"><?php esc_html_e( 'All templates', 'wc-generic-product-designer' ); ?></a>
				<?php if ( $delete_link ) : ?>
					<a href="<?php echo esc_url( $delete_link['url'] ); ?>" class="page-title-action button-link-delete wc-gpd-template-delete"<?php echo $delete_link['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Delete template', 'wc-generic-product-designer' ); ?></a>
				<?php endif; ?>
			</h1>
			<?php if ( $product_count > 0 ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of products */
							_n(
								'This template is assigned to %d product.',
								'This template is assigned to %d products.',
								$product_count,
								'wc-generic-product-designer'
							),
							$product_count
						)
					);
					?>
				</p>
			<?php endif; ?>

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
			<input type="hidden" id="wc_gpd_library_assignments" name="wc_gpd_library_assignments" value="<?php echo esc_attr( $assignments_json ? $assignments_json : '{}' ); ?>" />
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
						<?php require WC_GPD_PLUGIN_DIR . 'includes/partials/admin-context-pane.php'; ?>
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
				<p class="description"><?php esc_html_e( 'Preview of panels and add options. Per-layer edit permissions are set on the Template tab when you select a layer.', 'wc-generic-product-designer' ); ?></p>
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
									<button type="button" class="button button-small wc-gpd-mockup-control" data-mockup="add_text" disabled><?php esc_html_e( 'Add text', 'wc-generic-product-designer' ); ?></button>
									<button type="button" class="button button-small wc-gpd-mockup-control" data-mockup="add_shape" disabled hidden><?php esc_html_e( 'Add shape', 'wc-generic-product-designer' ); ?></button>
									<button type="button" class="button button-small wc-gpd-mockup-control" data-mockup="add_graphic" disabled hidden><?php esc_html_e( 'Add graphic', 'wc-generic-product-designer' ); ?></button>
									<button type="button" class="button button-small wc-gpd-mockup-control" data-mockup="add_image" disabled hidden><?php esc_html_e( 'Upload image', 'wc-generic-product-designer' ); ?></button>
									<button type="button" class="button button-small wc-gpd-mockup-control" data-mockup="add_icon" disabled hidden><?php esc_html_e( 'Add icon', 'wc-generic-product-designer' ); ?></button>
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
				<h4><?php esc_html_e( 'Assigned libraries', 'wc-generic-product-designer' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Choose which graphic, photo, and icon libraries customers can use on this template. Create libraries under Template Designer → Libraries.', 'wc-generic-product-designer' ); ?></p>
				<div id="wc-gpd-library-assignments" class="wc-gpd-library-assignments"></div>
			</div>
			<div class="wc-gpd-settings-card wc-gpd-settings-card--wide">
				<h4><?php esc_html_e( 'What customers can add', 'wc-generic-product-designer' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Controls the Add menu on the storefront designer. Template layers are configured separately on the Template tab.', 'wc-generic-product-designer' ); ?></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_add_text" value="1" <?php checked( ! empty( $ps['allow_add_text'] ) ); ?> /> <?php esc_html_e( 'Text', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_add_shape" value="1" <?php checked( ! empty( $ps['allow_add_shape'] ) ); ?> /> <?php esc_html_e( 'Shapes (rectangle, circle, polygon, heart)', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_add_graphic" value="1" <?php checked( ! empty( $ps['allow_add_graphic'] ) ); ?> /> <?php esc_html_e( 'Graphics from assigned libraries', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_add_image" value="1" <?php checked( ! empty( $ps['allow_add_image'] ) ); ?> /> <?php esc_html_e( 'Photos from libraries and/or upload their own image', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_add_icon" value="1" <?php checked( ! empty( $ps['allow_add_icon'] ) ); ?> /> <?php esc_html_e( 'Icons (Bootstrap Icons)', 'wc-generic-product-designer' ); ?></label></p>
			</div>
			<div class="wc-gpd-settings-card">
				<h4><?php esc_html_e( 'Panels', 'wc-generic-product-designer' ); ?></h4>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_layers_panel" value="1" <?php checked( $ps['allow_layers_panel'] ); ?> /> <?php esc_html_e( 'Layers panel', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_details_panel" value="1" <?php checked( $ps['allow_details_panel'] ); ?> /> <?php esc_html_e( 'Details panel (variable fields)', 'wc-generic-product-designer' ); ?></label></p>
				<p><label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_customer_graphics" value="1" <?php checked( $ps['allow_customer_graphics'] ); ?> /> <?php esc_html_e( 'Graphic picker in Details (for template slots)', 'wc-generic-product-designer' ); ?></label></p>
			</div>
			<div class="wc-gpd-settings-card wc-gpd-settings-card--wide">
				<h4><?php esc_html_e( 'Customer-added text formatting', 'wc-generic-product-designer' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Applies only to text the customer adds — not template layers (those use per-layer locks on the Template tab).', 'wc-generic-product-designer' ); ?></p>
				<div class="wc-gpd-settings-check-grid">
					<label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_font_family" value="1" <?php checked( $ps['allow_font_family'] ); ?> /> <?php esc_html_e( 'Font family', 'wc-generic-product-designer' ); ?></label>
					<label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_font_size" value="1" <?php checked( $ps['allow_font_size'] ); ?> /> <?php esc_html_e( 'Font size', 'wc-generic-product-designer' ); ?></label>
					<label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_bold" value="1" <?php checked( $ps['allow_bold'] ); ?> /> <?php esc_html_e( 'Bold', 'wc-generic-product-designer' ); ?></label>
					<label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_italic" value="1" <?php checked( $ps['allow_italic'] ); ?> /> <?php esc_html_e( 'Italic', 'wc-generic-product-designer' ); ?></label>
					<label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_underline" value="1" <?php checked( $ps['allow_underline'] ); ?> /> <?php esc_html_e( 'Underline', 'wc-generic-product-designer' ); ?></label>
					<label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_text_align" value="1" <?php checked( $ps['allow_text_align'] ); ?> /> <?php esc_html_e( 'Alignment', 'wc-generic-product-designer' ); ?></label>
					<label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_line_height" value="1" <?php checked( $ps['allow_line_height'] ); ?> /> <?php esc_html_e( 'Line height', 'wc-generic-product-designer' ); ?></label>
					<label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_letter_spacing" value="1" <?php checked( $ps['allow_letter_spacing'] ); ?> /> <?php esc_html_e( 'Letter spacing', 'wc-generic-product-designer' ); ?></label>
					<label class="wc-gpd-settings-check"><input type="checkbox" name="wc_gpd_ps_allow_text_color" value="1" <?php checked( $ps['allow_text_color'] ); ?> /> <?php esc_html_e( 'Text color', 'wc-generic-product-designer' ); ?></label>
				</div>
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
		<p class="description"><?php esc_html_e( 'Template-wide visual defaults and export options. Customer permissions and add options are on the Customer tools tab; per-layer locks are on the Template tab.', 'wc-generic-product-designer' ); ?></p>
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
				<h4><?php esc_html_e( 'Storefront canvas', 'wc-generic-product-designer' ); ?></h4>
				<p><label class="wc-gpd-settings-color"><?php esc_html_e( 'Background color', 'wc-generic-product-designer' ); ?> <input type="color" name="wc_gpd_ps_canvas_bg_color" value="<?php echo esc_attr( $ps['canvas_bg_color'] ); ?>" /></label></p>
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
