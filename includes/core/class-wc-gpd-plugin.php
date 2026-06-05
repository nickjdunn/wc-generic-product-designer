<?php
/**
 * Plugin orchestrator — loads modules and core services.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin application class.
 */
final class WC_GPD_Plugin {

	/**
	 * @var WC_GPD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var array<int,string>
	 */
	private $modules = array(
		WC_GPD_Debug::class,
		WC_GPD_Admin_Product::class,
		WC_GPD_Frontend::class,
		WC_GPD_Cart::class,
		WC_GPD_Order_Display::class,
		WC_GPD_Admin_Order::class,
	);

	/**
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
		$this->register_services();
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
	 * Bind core services into container.
	 */
	private function register_services() {
		WC_GPD_Container::set(
			'plugin',
			function () {
				return self::$instance;
			}
		);
		WC_GPD_Container::set(
			'settings',
			function () {
				return WC_GPD_Settings::class;
			}
		);
		WC_GPD_Container::set(
			'logger',
			function () {
				return WC_GPD_Logger::class;
			}
		);
	}

	/**
	 * Register WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'boot' ), 5 );
		register_activation_hook( WC_GPD_PLUGIN_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Boot plugin after WooCommerce is available.
	 */
	public function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->load_modules();

		load_plugin_textdomain(
			'wc-generic-product-designer',
			false,
			dirname( WC_GPD_PLUGIN_BASENAME ) . '/languages'
		);

		WC_GPD_Logger::debug( 'Plugin booted', array( 'version' => WC_GPD_VERSION ) );

		do_action( 'wc_gpd_loaded' );
	}

	/**
	 * Instantiate and register each module.
	 */
	private function load_modules() {
		foreach ( $this->modules as $module_class ) {
			if ( ! class_exists( $module_class ) ) {
				WC_GPD_Logger::error( 'Module class not found', array( 'class' => $module_class ) );
				continue;
			}

			$module = $module_class::instance();

			if ( $module instanceof WC_GPD_Module ) {
				$module->register();
			}

			WC_GPD_Logger::debug( 'Module registered', array( 'module' => $module_class ) );
		}
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

		if ( false === get_option( WC_GPD_Settings::OPTION_KEY, false ) ) {
			WC_GPD_Settings::update( WC_GPD_Settings::DEFAULTS );
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
