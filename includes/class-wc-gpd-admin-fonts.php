<?php
/**
 * Fonts admin screen under Template Designer.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Font management admin UI.
 */
class WC_GPD_Admin_Fonts implements WC_GPD_Module {

	const PAGE_SLUG    = 'wc-gpd-fonts';
	const NONCE_ACTION = 'wc_gpd_save_fonts';
	const NONCE_NAME   = 'wc_gpd_fonts_nonce';
	const AJAX_SEARCH  = 'wc_gpd_search_google_fonts';

	/**
	 * @var WC_GPD_Admin_Fonts|null
	 */
	private static $instance = null;

	/**
	 * @return WC_GPD_Admin_Fonts
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
		add_action( 'wp_ajax_' . self::AJAX_SEARCH, array( $this, 'ajax_search_google_fonts' ) );
	}

	/**
	 * Register submenu.
	 */
	public function register_menu() {
		add_submenu_page(
			WC_GPD_Admin_Templates::PAGE_SLUG,
			__( 'Fonts', 'wc-generic-product-designer' ),
			__( 'Fonts', 'wc-generic-product-designer' ),
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
			'wc-gpd-admin-fonts',
			WC_GPD_PLUGIN_URL . 'assets/js/admin-fonts.js',
			array( 'jquery' ),
			WC_GPD_VERSION,
			true
		);

		wp_localize_script(
			'wc-gpd-admin-fonts',
			'wcGpdFontsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_SEARCH ),
				'i18n'    => array(
					'searchPlaceholder' => __( 'Search Google Fonts…', 'wc-generic-product-designer' ),
					'addFont'           => __( 'Add to site', 'wc-generic-product-designer' ),
					'added'             => __( 'Added', 'wc-generic-product-designer' ),
					'noResults'         => __( 'No fonts found.', 'wc-generic-product-designer' ),
					'searching'         => __( 'Searching…', 'wc-generic-product-designer' ),
					'customerLabel'     => __( 'Customer sees', 'wc-generic-product-designer' ),
					'originalLabel'     => __( 'Original', 'wc-generic-product-designer' ),
					'remove'            => __( 'Remove', 'wc-generic-product-designer' ),
				),
			)
		);

		WC_GPD_Font_Registry::enqueue_for_designer();
	}

	/**
	 * AJAX: search Google Fonts catalog.
	 */
	public function ajax_search_google_fonts() {
		check_ajax_referer( self::AJAX_SEARCH, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-generic-product-designer' ) ), 403 );
		}

		$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$limit = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 40;
		$limit = min( 80, max( 5, $limit ) );

		$results = WC_GPD_Font_Registry::search_google_fonts( $query, $limit );
		wp_send_json_success( array( 'fonts' => $results ) );
	}

	/**
	 * Render fonts page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$registry = WC_GPD_Font_Registry::get_registry();
		$catalog  = WC_GPD_Font_Registry::all_fonts_catalog();

		if ( isset( $_POST['wc_gpd_fonts_save'] ) ) {
			check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

			$payload = array(
				'default_font' => isset( $_POST['wc_gpd_default_font'] ) ? sanitize_key( wp_unslash( $_POST['wc_gpd_default_font'] ) ) : 'times_new_roman',
				'enabled'      => array(),
				'google_fonts' => $registry['google_fonts'],
				'custom'       => array(),
			);

			if ( ! empty( $_POST['wc_gpd_fonts_state'] ) ) {
				$decoded = json_decode( wp_unslash( $_POST['wc_gpd_fonts_state'] ), true );
				if ( is_array( $decoded ) ) {
					if ( ! empty( $decoded['enabled'] ) && is_array( $decoded['enabled'] ) ) {
						$payload['enabled'] = $decoded['enabled'];
					}
					if ( ! empty( $decoded['google_fonts'] ) && is_array( $decoded['google_fonts'] ) ) {
						$payload['google_fonts'] = $decoded['google_fonts'];
					}
					if ( ! empty( $decoded['custom'] ) && is_array( $decoded['custom'] ) ) {
						$payload['custom'] = $decoded['custom'];
					}
					if ( ! empty( $decoded['display_labels'] ) && is_array( $decoded['display_labels'] ) ) {
						$payload['display_labels'] = $decoded['display_labels'];
					}
				}
			}

			WC_GPD_Font_Registry::save_registry( $payload );
			$registry = WC_GPD_Font_Registry::get_registry();
			$catalog  = WC_GPD_Font_Registry::all_fonts_catalog();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Fonts saved.', 'wc-generic-product-designer' ) . '</p></div>';
		}

		$state = array(
			'enabled'        => $registry['enabled'],
			'google_fonts'   => array_values( $registry['google_fonts'] ),
			'custom'         => array_values( $registry['custom'] ),
			'display_labels' => ! empty( $registry['display_labels'] ) ? $registry['display_labels'] : array(),
			'catalog'        => $catalog,
		);
		?>
		<div class="wrap wc-gpd-templates-wrap wc-gpd-fonts-admin">
			<h1><?php esc_html_e( 'Designer fonts', 'wc-generic-product-designer' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Browse Google Fonts, rename how fonts appear to customers, and upload custom files. Assign fonts per template in the template editor.', 'wc-generic-product-designer' ); ?></p>

			<form method="post" id="wc-gpd-fonts-form" novalidate>
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="wc_gpd_fonts_save" value="1" />
				<input type="hidden" id="wc_gpd_fonts_state" name="wc_gpd_fonts_state" value="<?php echo esc_attr( wp_json_encode( $state ) ); ?>" />

				<div class="wc-gpd-fonts-layout">
					<div class="wc-gpd-settings-card wc-gpd-fonts-installed">
						<h4><?php esc_html_e( 'Installed fonts', 'wc-generic-product-designer' ); ?></h4>
						<p class="description"><?php esc_html_e( 'Original names are shown for your reference. Edit “Customer sees” to change the label in the designer.', 'wc-generic-product-designer' ); ?></p>
						<table class="widefat striped wc-gpd-fonts-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Preview', 'wc-generic-product-designer' ); ?></th>
									<th><?php esc_html_e( 'Original', 'wc-generic-product-designer' ); ?></th>
									<th><?php esc_html_e( 'Customer sees', 'wc-generic-product-designer' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody id="wc-gpd-installed-fonts-list"></tbody>
						</table>
						<p>
							<label><?php esc_html_e( 'Default font', 'wc-generic-product-designer' ); ?>
								<select name="wc_gpd_default_font" id="wc-gpd-default-font-select">
									<?php foreach ( $registry['enabled'] as $key ) : ?>
										<?php
										if ( ! isset( $catalog[ $key ] ) && 0 !== strpos( $key, 'custom:' ) ) {
											continue;
										}
										$row = isset( $catalog[ $key ] ) ? $catalog[ $key ] : null;
										if ( ! $row && 0 === strpos( $key, 'custom:' ) ) {
											$cid = substr( $key, 7 );
											$row = ! empty( $registry['custom'][ $cid ] ) ? $registry['custom'][ $cid ] : null;
										}
										if ( ! $row ) {
											continue;
										}
										$label = ! empty( $row['display_label'] ) ? $row['display_label'] : ( ! empty( $row['label'] ) ? $row['label'] : $key );
										$css   = ! empty( $row['family'] ) ? $row['family'] : 'inherit';
										?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $registry['default_font'], $key ); ?> style="font-family:<?php echo esc_attr( $css ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</p>
					</div>

					<div class="wc-gpd-settings-card wc-gpd-fonts-browser">
						<h4><?php esc_html_e( 'Browse Google Fonts', 'wc-generic-product-designer' ); ?></h4>
						<p class="wc-gpd-fonts-search-row">
							<input type="search" id="wc-gpd-google-font-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search Google Fonts…', 'wc-generic-product-designer' ); ?>" />
							<button type="button" class="button" id="wc-gpd-google-font-search-btn"><?php esc_html_e( 'Search', 'wc-generic-product-designer' ); ?></button>
						</p>
						<ul id="wc-gpd-google-font-results" class="wc-gpd-google-font-results"></ul>
					</div>

					<div class="wc-gpd-settings-card wc-gpd-fonts-custom">
						<h4><?php esc_html_e( 'Custom uploaded fonts', 'wc-generic-product-designer' ); ?></h4>
						<p class="description"><?php esc_html_e( 'Upload .woff, .woff2, or .ttf files.', 'wc-generic-product-designer' ); ?></p>
						<button type="button" class="button" id="wc-gpd-add-custom-font"><?php esc_html_e( 'Upload font', 'wc-generic-product-designer' ); ?></button>
					</div>
				</div>

				<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save fonts', 'wc-generic-product-designer' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
