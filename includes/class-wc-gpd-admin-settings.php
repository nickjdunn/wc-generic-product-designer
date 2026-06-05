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
			__( 'Export defaults', 'wc-generic-product-designer' ),
			__( 'Export defaults', 'wc-generic-product-designer' ),
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
				<?php esc_html_e( 'Configure default production export options and debug tools.', 'wc-generic-product-designer' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="wc_gpd_settings_save" value="1" />

				<h2><?php esc_html_e( 'Production download defaults', 'wc-generic-product-designer' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Used by the “Download production file” button on orders. You can override per download with custom checkboxes.', 'wc-generic-product-designer' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Production file includes', 'wc-generic-product-designer' ); ?></th>
						<td>
							<label><input type="checkbox" name="export_include_background" value="1" <?php checked( ! empty( $settings['export_include_background'] ) ); ?> /> <?php esc_html_e( 'Product background image', 'wc-generic-product-designer' ); ?></label><br />
							<label><input type="checkbox" name="export_include_text" value="1" <?php checked( ! empty( $settings['export_include_text'] ) ); ?> /> <?php esc_html_e( 'Customer text layers', 'wc-generic-product-designer' ); ?></label><br />
							<label><input type="checkbox" name="export_include_outlines" value="1" <?php checked( ! empty( $settings['export_include_outlines'] ) ); ?> /> <?php esc_html_e( 'Template outline lines', 'wc-generic-product-designer' ); ?></label><br />
							<label><input type="checkbox" name="export_include_shapes" value="1" <?php checked( ! empty( $settings['export_include_shapes'] ) ); ?> /> <?php esc_html_e( 'Customer shape layers', 'wc-generic-product-designer' ); ?></label><br />
							<label><input type="checkbox" name="export_rasterize" value="1" <?php checked( ! empty( $settings['export_rasterize'] ) ); ?> /> <?php esc_html_e( 'Rasterize export (PNG via Imagick)', 'wc-generic-product-designer' ); ?></label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'wc-generic-product-designer' ); ?></button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-gpd-debug' ) ); ?>"><?php esc_html_e( 'Open debug panel', 'wc-generic-product-designer' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Save posted settings.
	 */
	private function save_settings() {
		WC_GPD_Settings::update(
			array(
				'export_include_background' => isset( $_POST['export_include_background'] ),
				'export_include_text'       => isset( $_POST['export_include_text'] ),
				'export_include_outlines'   => isset( $_POST['export_include_outlines'] ),
				'export_include_shapes'     => isset( $_POST['export_include_shapes'] ),
				'export_rasterize'          => isset( $_POST['export_rasterize'] ),
			)
		);
	}
}
