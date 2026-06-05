<?php
/**
 * Module contract — each feature registers its hooks via register().
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin module interface.
 */
interface WC_GPD_Module {

	/**
	 * Register WordPress / WooCommerce hooks.
	 */
	public function register();
}
