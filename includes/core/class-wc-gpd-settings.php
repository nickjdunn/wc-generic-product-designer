<?php
/**
 * Plugin settings (options API).
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Global plugin settings.
 */
class WC_GPD_Settings {

	const OPTION_KEY = 'wc_gpd_settings';

	const DEFAULTS = array(
		'debug_enabled' => false,
		'log_level'     => 'debug',
		'js_debug'      => false,
	);

	/**
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$cache = wp_parse_args( $stored, self::DEFAULTS );
		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist settings.
	 *
	 * @param array $settings Partial settings to merge.
	 */
	public static function update( array $settings ) {
		$merged = wp_parse_args( $settings, self::all() );
		self::$cache = $merged;
		update_option( self::OPTION_KEY, $merged, false );
	}

	/**
	 * Whether debug logging is active.
	 *
	 * @return bool
	 */
	public static function is_debug_enabled() {
		if ( defined( 'WC_GPD_DEBUG' ) && WC_GPD_DEBUG ) {
			return true;
		}
		return (bool) self::get( 'debug_enabled', false );
	}

	/**
	 * Whether frontend JS console debug is active.
	 *
	 * @return bool
	 */
	public static function is_js_debug_enabled() {
		if ( ! self::is_debug_enabled() ) {
			return false;
		}
		if ( defined( 'WC_GPD_JS_DEBUG' ) && WC_GPD_JS_DEBUG ) {
			return true;
		}
		return (bool) self::get( 'js_debug', false );
	}

	/**
	 * Minimum log level as numeric rank.
	 *
	 * @return int
	 */
	public static function log_level_threshold() {
		$levels = array(
			'debug'   => 0,
			'info'    => 1,
			'warning' => 2,
			'error'   => 3,
		);
		$level = (string) self::get( 'log_level', 'debug' );
		return isset( $levels[ $level ] ) ? $levels[ $level ] : 0;
	}

	/**
	 * Clear settings cache (after external updates).
	 */
	public static function flush_cache() {
		self::$cache = null;
	}
}
