<?php
/**
 * Graphic libraries admin screen.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Libraries admin UI.
 */
class WC_GPD_Admin_Libraries implements WC_GPD_Module {

	const PAGE_SLUG    = 'wc-gpd-libraries';
	const NONCE_ACTION = 'wc_gpd_save_libraries';
	const NONCE_NAME   = 'wc_gpd_libraries_nonce';

	/**
	 * @var WC_GPD_Admin_Libraries|null
	 */
	private static $instance = null;

	/**
	 * @return WC_GPD_Admin_Libraries
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
		add_action( 'admin_menu', array( $this, 'register_menu' ), 59 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu.
	 */
	public function register_menu() {
		add_submenu_page(
			WC_GPD_Admin_Templates::PAGE_SLUG,
			__( 'Libraries', 'wc-generic-product-designer' ),
			__( 'Libraries', 'wc-generic-product-designer' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'template-designer_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'wc-gpd-admin-templates',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-templates.css',
			array(),
			WC_GPD_VERSION
		);
		wp_enqueue_script(
			'wc-gpd-admin-libraries',
			WC_GPD_PLUGIN_URL . 'assets/js/admin-libraries.js',
			array( 'jquery' ),
			WC_GPD_VERSION,
			true
		);
		wp_localize_script(
			'wc-gpd-admin-libraries',
			'wcGpdLibrariesAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'ajaxAction'  => WC_GPD_Bootstrap_Icons::AJAX_SEARCH,
				'nonce'       => wp_create_nonce( WC_GPD_Bootstrap_Icons::NONCE_ACTION ),
				'iconBaseUrl' => WC_GPD_PLUGIN_URL . WC_GPD_Bootstrap_Icons::ICONS_DIR . '/',
				'i18n' => array(
					'addLibrary'    => __( 'Add library', 'wc-generic-product-designer' ),
					'removeLibrary' => __( 'Remove library', 'wc-generic-product-designer' ),
					'addImages'     => __( 'Add images', 'wc-generic-product-designer' ),
					'addPhotos'     => __( 'Add photos', 'wc-generic-product-designer' ),
					'libraryName'   => __( 'Library name', 'wc-generic-product-designer' ),
					'emptyLibrary'  => __( 'No libraries yet. Add one to get started.', 'wc-generic-product-designer' ),
					'typeGraphic'   => __( 'Graphics library', 'wc-generic-product-designer' ),
					'typePhoto'     => __( 'Photo library', 'wc-generic-product-designer' ),
					'typeIcon'      => __( 'Icon library', 'wc-generic-product-designer' ),
					'allIcons'      => __( 'Include all Bootstrap icons', 'wc-generic-product-designer' ),
					'allIconsNote'  => __( 'This library includes every bundled Bootstrap icon.', 'wc-generic-product-designer' ),
					'searchIcons'   => __( 'Search icons to add…', 'wc-generic-product-designer' ),
					'search'        => __( 'Search', 'wc-generic-product-designer' ),
					'loadAllIcons'  => __( 'Browse all icons', 'wc-generic-product-designer' ),
					'loadMoreIcons' => __( 'Load more icons', 'wc-generic-product-designer' ),
					'searching'     => __( 'Loading icons…', 'wc-generic-product-designer' ),
					'noResults'     => __( 'No icons found.', 'wc-generic-product-designer' ),
				),
			)
		);
	}

	/**
	 * Render libraries page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		WC_GPD_Graphic_Libraries::maybe_seed_demo_libraries();
		$libraries = WC_GPD_Graphic_Libraries::get_all();

		if ( isset( $_POST['wc_gpd_libraries_save'] ) ) {
			check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
			$raw = isset( $_POST['wc_gpd_libraries_json'] ) ? wp_unslash( $_POST['wc_gpd_libraries_json'] ) : '';
			WC_GPD_Graphic_Libraries::save_all( WC_GPD_Graphic_Libraries::sanitize_from_json( is_string( $raw ) ? $raw : '' ) );
			$libraries = WC_GPD_Graphic_Libraries::get_all();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Libraries saved.', 'wc-generic-product-designer' ) . '</p></div>';
		}

		$json = wp_json_encode( $libraries );
		?>
		<div class="wrap wc-gpd-templates-wrap wc-gpd-libraries-admin">
			<h1><?php esc_html_e( 'Libraries', 'wc-generic-product-designer' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Create graphics, photo, and icon libraries. Assign them to individual templates on the Customer tools tab.', 'wc-generic-product-designer' ); ?></p>

			<form method="post" id="wc-gpd-libraries-form">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="wc_gpd_libraries_save" value="1" />
				<input type="hidden" id="wc_gpd_libraries_json" name="wc_gpd_libraries_json" value="<?php echo esc_attr( $json ? $json : '[]' ); ?>" />

				<div id="wc-gpd-libraries-admin-list" class="wc-gpd-libraries-admin-list"></div>
				<p><button type="button" class="button" id="wc-gpd-libraries-add"><?php esc_html_e( 'Add library', 'wc-generic-product-designer' ); ?></button></p>
				<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save libraries', 'wc-generic-product-designer' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
