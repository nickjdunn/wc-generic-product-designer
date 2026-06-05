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
		'debug_enabled'                 => false,
		'log_level'                     => 'debug',
		'js_debug'                      => false,
		'export_include_background'     => false,
		'export_include_text'           => true,
		'export_include_outlines'       => true,
		'export_include_shapes'         => true,
		'export_rasterize'              => false,
		'start_designing_label'         => '',
		'cta_button_custom_css'         => '',
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

	/**
	 * Default export options for production downloads.
	 *
	 * @return array
	 */
	public static function export_defaults() {
		return array(
			'include_background' => (bool) self::get( 'export_include_background', false ),
			'include_text'       => (bool) self::get( 'export_include_text', true ),
			'include_outlines'   => (bool) self::get( 'export_include_outlines', true ),
			'include_shapes'     => (bool) self::get( 'export_include_shapes', true ),
			'rasterize'          => (bool) self::get( 'export_rasterize', false ),
			'preset'             => 'production',
		);
	}

	/**
	 * Preset for customer proof exports.
	 *
	 * @return array
	 */
	/**
	 * Storefront CTA label for designer products.
	 *
	 * @return string
	 */
	public static function start_designing_label() {
		$label = trim( (string) self::get( 'start_designing_label', '' ) );
		if ( '' !== $label ) {
			return $label;
		}
		return __( 'Start designing', 'wc-generic-product-designer' );
	}

	/**
	 * Scoped CSS for add-to-cart / start-designing buttons on designer products.
	 *
	 * @return string
	 */
	public static function cta_button_css_block() {
		$rules = trim( (string) self::get( 'cta_button_custom_css', '' ) );
		if ( '' === $rules ) {
			return '';
		}

		$selectors = implode(
			",\n",
			array(
				'.wc-gpd-product .single_add_to_cart_button',
				'.wc-gpd-product .add_to_cart_button',
				'.wc-gpd-product a.wc-gpd-start-designing-link',
				'.wc-gpd-product .wc-gpd-fallback-start',
				'.product.wc-gpd-has-designer .add_to_cart_button',
				'.product.wc-gpd-has-designer a.wc-gpd-start-designing-link',
			)
		);

		return $selectors . ' { ' . $rules . ' }';
	}

	public static function proof_export_defaults() {
		return array(
			'include_background' => true,
			'include_text'       => true,
			'include_outlines'   => false,
			'include_shapes'     => true,
			'rasterize'          => false,
			'preset'             => 'proof',
		);
	}
}
