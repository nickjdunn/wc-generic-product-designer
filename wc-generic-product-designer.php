<?php
/**
 * Plugin Name:       WC Generic Product Designer
 * Plugin URI:        https://github.com/nickjdunn/wc-generic-product-designer
 * Description:       Let customers design products with text layers on a canvas; export production-ready SVG on order.
 * Version:           1.43.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WC Generic Product Designer
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-generic-product-designer
 * Domain Path:       /languages
 *
 * GitHub Plugin URI: nickjdunn/wc-generic-product-designer
 * Primary Branch:    main
 * Requires Plugins:  woocommerce
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_GPD_VERSION', '1.43.1' );
define( 'WC_GPD_PLUGIN_FILE', __FILE__ );
define( 'WC_GPD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_GPD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_GPD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WC_GPD_PLUGIN_DIR . 'includes/core/class-wc-gpd-autoloader.php';
WC_GPD_Autoloader::register();

// Core dependencies loaded before plugin boot (autoloader handles the rest).
require_once WC_GPD_PLUGIN_DIR . 'includes/core/interface-wc-gpd-module.php';
require_once WC_GPD_PLUGIN_DIR . 'includes/core/class-wc-gpd-container.php';
require_once WC_GPD_PLUGIN_DIR . 'includes/core/class-wc-gpd-settings.php';
require_once WC_GPD_PLUGIN_DIR . 'includes/core/class-wc-gpd-logger.php';
require_once WC_GPD_PLUGIN_DIR . 'includes/core/class-wc-gpd-plugin.php';

/**
 * Returns the main plugin instance.
 *
 * @return WC_GPD_Plugin
 */
function wc_gpd() {
	return WC_GPD_Plugin::instance();
}

/**
 * Shortcut to logger (no-op when debug disabled).
 *
 * @return WC_GPD_Logger
 */
function wc_gpd_logger() {
	return WC_GPD_Logger::class;
}

wc_gpd();
