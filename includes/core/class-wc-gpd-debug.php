<?php
/**
 * Admin debug panel and diagnostics.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Debug tools for shop managers.
 */
class WC_GPD_Debug implements WC_GPD_Module {

	/**
	 * @var WC_GPD_Debug|null
	 */
	private static $instance = null;

	const PAGE_SLUG     = 'wc-gpd-debug';
	const NONCE_ACTION  = 'wc_gpd_debug_settings';
	const NONCE_ACTION_LOG = 'wc_gpd_debug_log_action';

	/**
	 * @return WC_GPD_Debug
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
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 99 );
		add_action( 'admin_init', array( $this, 'handle_form_submit' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * WooCommerce submenu.
	 */
	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Product Designer Debug', 'wc-generic-product-designer' ),
			__( 'Designer Debug', 'wc-generic-product-designer' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle settings save and log actions.
	 */
	public function handle_form_submit() {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_POST['wc_gpd_debug_settings'] ) ) {
			check_admin_referer( self::NONCE_ACTION );

			WC_GPD_Settings::update(
				array(
					'debug_enabled' => ! empty( $_POST['wc_gpd_debug_enabled'] ),
					'log_level'     => sanitize_key( wp_unslash( $_POST['wc_gpd_log_level'] ?? 'debug' ) ),
					'js_debug'      => ! empty( $_POST['wc_gpd_js_debug'] ),
				)
			);

			WC_GPD_Logger::info( 'Debug settings saved', array( 'user_id' => get_current_user_id() ) );

			wp_safe_redirect( add_query_arg( 'wc_gpd_saved', '1', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
			exit;
		}

		if ( isset( $_GET['wc_gpd_action'], $_GET['_wpnonce'] ) ) {
			$action = sanitize_key( wp_unslash( $_GET['wc_gpd_action'] ) );

			if ( 'test_log' === $action ) {
				check_admin_referer( self::NONCE_ACTION_LOG . '_test' );
				WC_GPD_Logger::info(
					'Test log entry from debug panel',
					array(
						'time'    => time(),
						'user_id' => get_current_user_id(),
					)
				);
				wp_safe_redirect( add_query_arg( 'wc_gpd_tested', '1', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
				exit;
			}

			if ( 'clear_logs' === $action ) {
				check_admin_referer( self::NONCE_ACTION_LOG . '_clear' );
				WC_GPD_Logger::clear_buffer();
				wp_safe_redirect( add_query_arg( 'wc_gpd_cleared', '1', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
				exit;
			}
		}
	}

	/**
	 * @param string $hook Admin hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wc-gpd-debug-admin',
			WC_GPD_PLUGIN_URL . 'assets/css/debug-admin.css',
			array(),
			WC_GPD_VERSION
		);
	}

	/**
	 * Render debug admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-generic-product-designer' ) );
		}

		$settings = WC_GPD_Settings::all();
		$logs     = WC_GPD_Logger::get_buffer( 50 );
		$env      = $this->get_environment();

		if ( isset( $_GET['wc_gpd_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Debug settings saved.', 'wc-generic-product-designer' ) . '</p></div>';
		}
		if ( isset( $_GET['wc_gpd_tested'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test log entry written.', 'wc-generic-product-designer' ) . '</p></div>';
		}
		if ( isset( $_GET['wc_gpd_cleared'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log buffer cleared.', 'wc-generic-product-designer' ) . '</p></div>';
		}

		$test_url  = wp_nonce_url(
			add_query_arg( 'wc_gpd_action', 'test_log', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			self::NONCE_ACTION_LOG . '_test'
		);
		$clear_url = wp_nonce_url(
			add_query_arg( 'wc_gpd_action', 'clear_logs', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			self::NONCE_ACTION_LOG . '_clear'
		);

		?>
		<div class="wrap wc-gpd-debug-wrap">
			<h1><?php esc_html_e( 'Product Designer — Debug', 'wc-generic-product-designer' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Enable logging to trace cart, SVG export, and module lifecycle events. Logs also write to WooCommerce → Status → Logs when debug is on.', 'wc-generic-product-designer' ); ?>
			</p>

			<div class="wc-gpd-debug-grid">
				<div class="wc-gpd-debug-panel">
					<h2><?php esc_html_e( 'Debug settings', 'wc-generic-product-designer' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( self::NONCE_ACTION ); ?>
						<input type="hidden" name="wc_gpd_debug_settings" value="1" />
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable debug logging', 'wc-generic-product-designer' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wc_gpd_debug_enabled" value="1" <?php checked( $settings['debug_enabled'] ); ?> />
										<?php esc_html_e( 'Log plugin events (or define WC_GPD_DEBUG in wp-config.php)', 'wc-generic-product-designer' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="wc_gpd_log_level"><?php esc_html_e( 'Minimum log level', 'wc-generic-product-designer' ); ?></label></th>
								<td>
									<select name="wc_gpd_log_level" id="wc_gpd_log_level">
										<?php foreach ( array( 'debug', 'info', 'warning', 'error' ) as $level ) : ?>
											<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $settings['log_level'], $level ); ?>>
												<?php echo esc_html( ucfirst( $level ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Frontend JS debug', 'wc-generic-product-designer' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wc_gpd_js_debug" value="1" <?php checked( $settings['js_debug'] ); ?> />
										<?php esc_html_e( 'Output designer events to browser console on product pages', 'wc-generic-product-designer' ); ?>
									</label>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Save settings', 'wc-generic-product-designer' ) ); ?>
					</form>
					<p>
						<a href="<?php echo esc_url( $test_url ); ?>" class="button"><?php esc_html_e( 'Write test log entry', 'wc-generic-product-designer' ); ?></a>
						<a href="<?php echo esc_url( $clear_url ); ?>" class="button"><?php esc_html_e( 'Clear log buffer', 'wc-generic-product-designer' ); ?></a>
					</p>
				</div>

				<div class="wc-gpd-debug-panel">
					<h2><?php esc_html_e( 'Environment', 'wc-generic-product-designer' ); ?></h2>
					<table class="widefat striped wc-gpd-env-table">
						<tbody>
							<?php foreach ( $env as $label => $value ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $label ); ?></th>
									<td><code><?php echo esc_html( (string) $value ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="wc-gpd-debug-panel wc-gpd-debug-panel--logs">
				<h2><?php esc_html_e( 'Recent log buffer', 'wc-generic-product-designer' ); ?></h2>
				<?php if ( empty( $logs ) ) : ?>
					<p><?php esc_html_e( 'No log entries yet. Enable debug and trigger an action, or use “Write test log entry”.', 'wc-generic-product-designer' ); ?></p>
				<?php else : ?>
					<table class="widefat striped wc-gpd-log-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Time (UTC)', 'wc-generic-product-designer' ); ?></th>
								<th><?php esc_html_e( 'Level', 'wc-generic-product-designer' ); ?></th>
								<th><?php esc_html_e( 'Message', 'wc-generic-product-designer' ); ?></th>
								<th><?php esc_html_e( 'Context', 'wc-generic-product-designer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row['time'] ?? '' ); ?></td>
									<td><span class="wc-gpd-log-level wc-gpd-log-level--<?php echo esc_attr( $row['level'] ?? 'debug' ); ?>"><?php echo esc_html( strtoupper( $row['level'] ?? '' ) ); ?></span></td>
									<td><?php echo esc_html( $row['message'] ?? '' ); ?></td>
									<td><code class="wc-gpd-log-context"><?php echo esc_html( wp_json_encode( $row['context'] ?? array() ) ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Collect environment diagnostics.
	 *
	 * @return array<string,string>
	 */
	private function get_environment() {
		global $wp_version;

		return array(
			__( 'Plugin version', 'wc-generic-product-designer' ) => WC_GPD_VERSION,
			__( 'WordPress', 'wc-generic-product-designer' )        => $wp_version,
			__( 'WooCommerce', 'wc-generic-product-designer' )      => defined( 'WC_VERSION' ) ? WC_VERSION : '—',
			__( 'PHP', 'wc-generic-product-designer' )                => PHP_VERSION,
			__( 'Debug active', 'wc-generic-product-designer' )     => WC_GPD_Settings::is_debug_enabled() ? 'yes' : 'no',
			__( 'WP_DEBUG', 'wc-generic-product-designer' )           => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'true' : 'false',
			__( 'WC_GPD_DEBUG constant', 'wc-generic-product-designer' ) => defined( 'WC_GPD_DEBUG' ) ? ( WC_GPD_DEBUG ? 'true' : 'false' ) : 'not defined',
			__( 'Fabric.js CDN', 'wc-generic-product-designer' )      => '5.3.1 (cdnjs)',
		);
	}
}
