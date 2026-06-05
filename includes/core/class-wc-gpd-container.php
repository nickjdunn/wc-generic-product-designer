<?php
/**
 * Simple service container.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service locator for plugin singletons.
 */
class WC_GPD_Container {

	/**
	 * @var array<string,mixed>
	 */
	private static $services = array();

	/**
	 * Register a service.
	 *
	 * @param string   $key      Service key.
	 * @param callable $factory  Factory returning instance.
	 */
	public static function set( $key, callable $factory ) {
		self::$services[ $key ] = $factory;
	}

	/**
	 * Retrieve a service.
	 *
	 * @param string $key Service key.
	 * @return mixed
	 */
	public static function get( $key ) {
		if ( ! isset( self::$services[ $key ] ) ) {
			return null;
		}

		$factory = self::$services[ $key ];
		$value   = $factory();

		if ( is_callable( $factory ) ) {
			// Cache resolved singletons (factories return same instance).
			self::$services[ $key ] = function () use ( $value ) {
				return $value;
			};
		}

		return $value;
	}

	/**
	 * Check if service is registered.
	 *
	 * @param string $key Service key.
	 * @return bool
	 */
	public static function has( $key ) {
		return isset( self::$services[ $key ] );
	}
}
