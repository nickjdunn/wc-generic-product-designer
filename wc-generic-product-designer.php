<?php
/**
 * Plugin Name:       WC Generic Product Designer
 * Plugin URI:        https://example.com/wc-generic-product-designer
 * Description:       Let customers design products with text layers on a canvas; export production-ready SVG on order.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WC Generic Product Designer
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-generic-product-designer
 * Domain Path:       /languages
 *
 * GitHub Plugin URI: your-github-username/wc-generic-product-designer
 * Primary Branch:    main
 * Requires Plugins:  woocommerce
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_GPD_VERSION', '1.0.0' );
define( 'WC_GPD_PLUGIN_FILE', __FILE__ );
define( 'WC_GPD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_GPD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_GPD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin bootstrap.
 */
final class WC_GPD_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WC_GPD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WC_GPD_Plugin
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton.' );
	}

	/**
	 * Load required files.
	 */
	private function includes() {
		require_once WC_GPD_PLUGIN_DIR . 'includes/class-wc-gpd-svg-sanitizer.php';
		require_once WC_GPD_PLUGIN_DIR . 'includes/class-wc-gpd-product-meta.php';
		require_once WC_GPD_PLUGIN_DIR . 'includes/class-wc-gpd-admin-product.php';
		require_once WC_GPD_PLUGIN_DIR . 'includes/class-wc-gpd-frontend.php';
		require_once WC_GPD_PLUGIN_DIR . 'includes/class-wc-gpd-cart.php';
		require_once WC_GPD_PLUGIN_DIR . 'includes/class-wc-gpd-admin-order.php';
	}

	/**
	 * Register hooks after plugins loaded.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		register_activation_hook( WC_GPD_PLUGIN_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Initialize components when WooCommerce is available.
	 */
	public function on_plugins_loaded() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		WC_GPD_Admin_Product::instance();
		WC_GPD_Frontend::instance();
		WC_GPD_Cart::instance();
		WC_GPD_Admin_Order::instance();

		load_plugin_textdomain(
			'wc-generic-product-designer',
			false,
			dirname( WC_GPD_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Activation hook.
	 */
	public function activate() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( WC_GPD_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'WC Generic Product Designer requires WooCommerce to be installed and active.', 'wc-generic-product-designer' ),
				esc_html__( 'Plugin Activation Error', 'wc-generic-product-designer' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Admin notice when WooCommerce is missing.
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'WC Generic Product Designer requires WooCommerce to be installed and active.', 'wc-generic-product-designer' )
		);
	}
}

/**
 * Returns the main plugin instance.
 *
 * @return WC_GPD_Plugin
 */
function wc_gpd() {
	return WC_GPD_Plugin::instance();
}

wc_gpd();
