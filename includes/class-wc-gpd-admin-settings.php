<?php
/**
 * Plugin settings screen under WooCommerce.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page.
 */
class WC_GPD_Admin_Settings implements WC_GPD_Module {

	/**
	 * @var WC_GPD_Admin_Settings|null
	 */
	private static $instance = null;

	const PAGE_SLUG    = 'wc-gpd-settings';
	const NONCE_ACTION = 'wc_gpd_save_settings';

	/**
	 * @return WC_GPD_Admin_Settings
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
	 * Add submenu under WooCommerce.
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Designer settings', 'wc-generic-product-designer' ),
			__( 'Designer settings', 'wc-generic-product-designer' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wc-gpd-admin-settings',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			WC_GPD_VERSION
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_POST['wc_gpd_settings_save'] ) ) {
			check_admin_referer( self::NONCE_ACTION );
			$this->save_settings();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wc-generic-product-designer' ) . '</p></div>';
		}

		$settings = WC_GPD_Settings::all();
		?>
		<div class="wrap wc-gpd-settings-wrap">
			<h1><?php esc_html_e( 'Product Designer Settings', 'wc-generic-product-designer' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Configure storefront buttons, production export defaults, and debug tools.', 'wc-generic-product-designer' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="wc_gpd_settings_save" value="1" />

				<h2><?php esc_html_e( 'Storefront button', 'wc-generic-product-designer' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Controls the add-to-cart button on product and shop pages for products with the designer enabled. Only the label and optional CSS are changed — theme colors stay unless you add CSS below.', 'wc-generic-product-designer' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wc_gpd_start_designing_label"><?php esc_html_e( 'Button label', 'wc-generic-product-designer' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="wc_gpd_start_designing_label" name="start_designing_label" value="<?php echo esc_attr( $settings['start_designing_label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Start designing', 'wc-generic-product-designer' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to use “Start designing”.', 'wc-generic-product-designer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wc_gpd_cta_button_custom_css"><?php esc_html_e( 'Button CSS', 'wc-generic-product-designer' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="6" id="wc_gpd_cta_button_custom_css" name="cta_button_custom_css" placeholder="border-radius: 999px;&#10;font-weight: 700;&#10;padding: 0.75rem 1.5rem;"><?php echo esc_textarea( $settings['cta_button_custom_css'] ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional CSS declarations applied to the start-designing button on product and category pages (e.g. border-radius, padding). Do not include the selector or braces.', 'wc-generic-product-designer' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="description"><?php esc_html_e( 'Production export options and batch bed settings are configured in Template Designer → Production → batch layout editor.', 'wc-generic-product-designer' ); ?></p>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'wc-generic-product-designer' ); ?></button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-gpd-debug' ) ); ?>"><?php esc_html_e( 'Open debug panel', 'wc-generic-product-designer' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Strip tags and unsafe CSS from custom button rules.
	 *
	 * @param string $css Raw CSS declarations.
	 * @return string
	 */
	public static function sanitize_cta_css( $css ) {
		$css = wp_strip_all_tags( (string) $css );
		$css = preg_replace( '/@import|expression|javascript:|behavior:/i', '', $css );
		return trim( $css );
	}

	/**
	 * Save posted settings.
	 */
	private function save_settings() {
		WC_GPD_Settings::update(
			array(
				'start_designing_label'     => isset( $_POST['start_designing_label'] ) ? sanitize_text_field( wp_unslash( $_POST['start_designing_label'] ) ) : '',
				'cta_button_custom_css'     => isset( $_POST['cta_button_custom_css'] ) ? WC_GPD_Admin_Settings::sanitize_cta_css( wp_unslash( $_POST['cta_button_custom_css'] ) ) : '',
			)
		);
	}
}
