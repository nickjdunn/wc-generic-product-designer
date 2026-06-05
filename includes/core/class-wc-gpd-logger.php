<?php
/**
 * Structured logger with ring buffer + WooCommerce log integration.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin logger.
 */
class WC_GPD_Logger {

	const BUFFER_OPTION = 'wc_gpd_log_buffer';
	const BUFFER_MAX    = 100;
	const SOURCE        = 'wc-generic-product-designer';

	const LEVEL_DEBUG   = 'debug';
	const LEVEL_INFO    = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	/**
	 * @var array<string,int>
	 */
	private static $level_rank = array(
		self::LEVEL_DEBUG   => 0,
		self::LEVEL_INFO    => 1,
		self::LEVEL_WARNING => 2,
		self::LEVEL_ERROR   => 3,
	);

	/**
	 * Log debug message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 */
	public static function debug( $message, array $context = array() ) {
		self::log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Log info message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 */
	public static function info( $message, array $context = array() ) {
		self::log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 */
	public static function warning( $message, array $context = array() ) {
		self::log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context data.
	 */
	public static function error( $message, array $context = array() ) {
		self::log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Write log entry when debug is enabled and level passes threshold.
	 *
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public static function log( $level, $message, array $context = array() ) {
		if ( ! WC_GPD_Settings::is_debug_enabled() ) {
			return;
		}

		$level = strtolower( (string) $level );
		if ( ! isset( self::$level_rank[ $level ] ) ) {
			$level = self::LEVEL_DEBUG;
		}

		if ( self::$level_rank[ $level ] < WC_GPD_Settings::log_level_threshold() ) {
			return;
		}

		$entry = array(
			'time'    => gmdate( 'Y-m-d H:i:s' ),
			'level'   => $level,
			'message' => (string) $message,
			'context' => $context,
		);

		self::push_buffer( $entry );
		self::write_wc_log( $level, $message, $context );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[WC GPD][%s] %s %s', strtoupper( $level ), $message, wp_json_encode( $context ) ) );
		}
	}

	/**
	 * @param array $entry Log entry.
	 */
	private static function push_buffer( array $entry ) {
		$buffer = get_option( self::BUFFER_OPTION, array() );
		if ( ! is_array( $buffer ) ) {
			$buffer = array();
		}
		array_unshift( $buffer, $entry );
		$buffer = array_slice( $buffer, 0, self::BUFFER_MAX );
		update_option( self::BUFFER_OPTION, $buffer, false );
	}

	/**
	 * @param string $level   Level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	private static function write_wc_log( $level, $message, array $context ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger  = wc_get_logger();
		$payload = array( 'source' => self::SOURCE );

		if ( ! empty( $context ) ) {
			$payload['context'] = $context;
		}

		$line = $message;
		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}

		if ( method_exists( $logger, $level ) ) {
			$logger->{$level}( $line, $payload );
		} else {
			$logger->debug( $line, $payload );
		}
	}

	/**
	 * Get buffered log entries for admin UI.
	 *
	 * @param int $limit Max entries.
	 * @return array<int,array>
	 */
	public static function get_buffer( $limit = 50 ) {
		$buffer = get_option( self::BUFFER_OPTION, array() );
		if ( ! is_array( $buffer ) ) {
			return array();
		}
		return array_slice( $buffer, 0, max( 1, absint( $limit ) ) );
	}

	/**
	 * Clear in-admin log buffer.
	 */
	public static function clear_buffer() {
		delete_option( self::BUFFER_OPTION );
	}
}
