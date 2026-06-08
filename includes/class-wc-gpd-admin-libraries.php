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

		$font_options = array();
		foreach ( WC_GPD_Font_Registry::all_fonts_catalog() as $key => $font ) {
			$font_options[] = array(
				'key'         => $key,
				'family'      => $font['family'],
				'label'       => ! empty( $font['display_label'] ) ? $font['display_label'] : $font['label'],
				'admin_label' => $font['label'],
				'css'         => $font['family'],
			);
		}

		wp_localize_script(
			'wc-gpd-admin-libraries',
			'wcGpdLibrariesAdmin',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'ajaxAction'        => WC_GPD_Bootstrap_Icons::AJAX_SEARCH,
				'nonce'             => wp_create_nonce( WC_GPD_Bootstrap_Icons::NONCE_ACTION ),
				'iconBaseUrl'       => WC_GPD_PLUGIN_URL . WC_GPD_Bootstrap_Icons::ICONS_DIR . '/',
				'fontOptions'       => $font_options,
				'colorPalettes'     => WC_GPD_Site_Libraries::get_color_palettes_document(),
				'fontLibraries'     => WC_GPD_Site_Libraries::get_font_libraries_document(),
				'i18n'              => array(
					'addLibrary'       => __( 'Add library', 'wc-generic-product-designer' ),
					'removeLibrary'    => __( 'Remove library', 'wc-generic-product-designer' ),
					'addImages'        => __( 'Add images', 'wc-generic-product-designer' ),
					'addPhotos'        => __( 'Add photos', 'wc-generic-product-designer' ),
					'libraryName'      => __( 'Library name', 'wc-generic-product-designer' ),
					'emptyLibrary'     => __( 'No libraries yet. Add one to get started.', 'wc-generic-product-designer' ),
					'typeGraphic'      => __( 'Graphics library', 'wc-generic-product-designer' ),
					'typePhoto'        => __( 'Photo library', 'wc-generic-product-designer' ),
					'typeIcon'         => __( 'Icon library', 'wc-generic-product-designer' ),
					'allIcons'         => __( 'Include all Bootstrap icons', 'wc-generic-product-designer' ),
					'allIconsNote'     => __( 'This library includes every bundled Bootstrap icon.', 'wc-generic-product-designer' ),
					'searchIcons'      => __( 'Search icons to add…', 'wc-generic-product-designer' ),
					'search'           => __( 'Search', 'wc-generic-product-designer' ),
					'loadAllIcons'     => __( 'Browse all icons', 'wc-generic-product-designer' ),
					'loadMoreIcons'    => __( 'Load more icons', 'wc-generic-product-designer' ),
					'searching'        => __( 'Loading icons…', 'wc-generic-product-designer' ),
					'noResults'        => __( 'No icons found.', 'wc-generic-product-designer' ),
					'addFontLibrary'   => __( 'Add font library', 'wc-generic-product-designer' ),
					'addColorPalette'  => __( 'Add color palette', 'wc-generic-product-designer' ),
					'addColor'         => __( 'Add color', 'wc-generic-product-designer' ),
					'emptyFontLibs'    => __( 'No font libraries yet.', 'wc-generic-product-designer' ),
					'emptyColorPalettes' => __( 'No color palettes yet.', 'wc-generic-product-designer' ),
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
		$libraries      = WC_GPD_Graphic_Libraries::get_all();
		$color_palettes = WC_GPD_Site_Libraries::get_color_palettes_document();
		$font_libraries = WC_GPD_Site_Libraries::get_font_libraries_document();

		if ( isset( $_POST['wc_gpd_libraries_save'] ) ) {
			check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
			$raw = isset( $_POST['wc_gpd_libraries_json'] ) ? wp_unslash( $_POST['wc_gpd_libraries_json'] ) : '';
			WC_GPD_Graphic_Libraries::save_all( WC_GPD_Graphic_Libraries::sanitize_from_json( is_string( $raw ) ? $raw : '' ) );
			$libraries = WC_GPD_Graphic_Libraries::get_all();

			$color_raw = isset( $_POST['wc_gpd_site_color_palettes_json'] ) ? wp_unslash( $_POST['wc_gpd_site_color_palettes_json'] ) : '';
			WC_GPD_Site_Libraries::save_color_palettes_document(
				WC_GPD_Site_Libraries::sanitize_color_palettes_json( is_string( $color_raw ) ? $color_raw : '' )
			);
			$color_palettes = WC_GPD_Site_Libraries::get_color_palettes_document();

			$font_raw = isset( $_POST['wc_gpd_site_font_libraries_json'] ) ? wp_unslash( $_POST['wc_gpd_site_font_libraries_json'] ) : '';
			WC_GPD_Site_Libraries::save_font_libraries_document(
				WC_GPD_Site_Libraries::sanitize_font_libraries_json( is_string( $font_raw ) ? $font_raw : '' )
			);
			$font_libraries = WC_GPD_Site_Libraries::get_font_libraries_document();

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Libraries saved.', 'wc-generic-product-designer' ) . '</p></div>';
		}

		$json        = wp_json_encode( $libraries );
		$color_json  = wp_json_encode( $color_palettes );
		$font_json   = wp_json_encode( $font_libraries );
		?>
		<div class="wrap wc-gpd-templates-wrap wc-gpd-libraries-admin">
			<h1><?php esc_html_e( 'Libraries', 'wc-generic-product-designer' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Manage graphics, fonts, and colors in one place. Assign media and font libraries to templates on the Customer tools tab.', 'wc-generic-product-designer' ); ?></p>

			<form method="post" id="wc-gpd-libraries-form">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="wc_gpd_libraries_save" value="1" />
				<input type="hidden" id="wc_gpd_libraries_json" name="wc_gpd_libraries_json" value="<?php echo esc_attr( $json ? $json : '[]' ); ?>" />
				<input type="hidden" id="wc_gpd_site_color_palettes_json" name="wc_gpd_site_color_palettes_json" value="<?php echo esc_attr( $color_json ? $color_json : '{}' ); ?>" />
				<input type="hidden" id="wc_gpd_site_font_libraries_json" name="wc_gpd_site_font_libraries_json" value="<?php echo esc_attr( $font_json ? $font_json : '{}' ); ?>" />

				<div class="wc-gpd-libraries-sections">
					<section class="wc-gpd-libraries-section">
						<h2 class="wc-gpd-libraries-section__title"><?php esc_html_e( 'Graphics, photos & icons', 'wc-generic-product-designer' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Image and icon collections customers can add from the storefront designer.', 'wc-generic-product-designer' ); ?></p>
						<div id="wc-gpd-libraries-admin-list" class="wc-gpd-libraries-admin-list"></div>
						<p><button type="button" class="button" id="wc-gpd-libraries-add"><?php esc_html_e( 'Add library', 'wc-generic-product-designer' ); ?></button></p>
					</section>

					<section class="wc-gpd-libraries-section">
						<h2 class="wc-gpd-libraries-section__title"><?php esc_html_e( 'Font libraries', 'wc-generic-product-designer' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Group site fonts into reusable libraries (e.g. 7–10 fonts per product type). Templates reference these when limiting customer font choices.', 'wc-generic-product-designer' ); ?></p>
						<div id="wc-gpd-font-libraries-list" class="wc-gpd-libraries-admin-list"></div>
						<p><button type="button" class="button" id="wc-gpd-add-font-library"><?php esc_html_e( 'Add font library', 'wc-generic-product-designer' ); ?></button></p>
					</section>

					<section class="wc-gpd-libraries-section">
						<h2 class="wc-gpd-libraries-section__title"><?php esc_html_e( 'Color palettes', 'wc-generic-product-designer' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Shared swatch palettes for shapes, text, icons, and graphics across all templates.', 'wc-generic-product-designer' ); ?></p>
						<div id="wc-gpd-site-color-palettes-list" class="wc-gpd-libraries-admin-list"></div>
						<p><button type="button" class="button" id="wc-gpd-add-site-color-palette"><?php esc_html_e( 'Add color palette', 'wc-generic-product-designer' ); ?></button></p>
					</section>
				</div>

				<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save libraries', 'wc-generic-product-designer' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
