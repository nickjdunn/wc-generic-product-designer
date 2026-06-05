<?php
/**
 * PSR-4-style autoloader for WC_GPD_* classes.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class autoloader.
 */
class WC_GPD_Autoloader {

	/**
	 * Register spl autoload.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Resolve class file from class name.
	 *
	 * @param string $class Class name.
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, 'WC_GPD_' ) ) {
			return;
		}

		$relative = strtolower( str_replace( '_', '-', $class ) );
		$file     = $relative . '.php';

		$paths = array(
			WC_GPD_PLUGIN_DIR . 'includes/core/' . $file,
			WC_GPD_PLUGIN_DIR . 'includes/' . $file,
		);

		foreach ( $paths as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
