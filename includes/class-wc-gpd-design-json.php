<?php
/**
 * Sanitize Fabric.js canvas JSON for cart round-trip editing.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Design JSON sanitizer.
 */
class WC_GPD_Design_Json {

	const MAX_BYTES = 524288;

	/**
	 * Allowed Fabric object types.
	 *
	 * @var string[]
	 */
	private static $allowed_types = array(
		'i-text',
		'text',
		'textbox',
		'group',
	);

	/**
	 * Sanitize posted canvas JSON.
	 *
	 * @param string $json Raw JSON string.
	 * @return string|false
	 */
	public static function sanitize( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return false;
		}

		if ( strlen( $json ) > self::MAX_BYTES ) {
			return false;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data['objects'] ) || ! is_array( $data['objects'] ) ) {
			return false;
		}

		$objects = array();
		foreach ( $data['objects'] as $object ) {
			if ( ! is_array( $object ) || empty( $object['type'] ) ) {
				continue;
			}

			$type = strtolower( (string) $object['type'] );
			if ( ! in_array( $type, self::$allowed_types, true ) ) {
				return false;
			}

			$objects[] = $object;
		}

		if ( empty( $objects ) ) {
			return false;
		}

		$clean = array(
			'version' => isset( $data['version'] ) ? sanitize_text_field( (string) $data['version'] ) : '5.3.1',
			'objects' => $objects,
		);

		$encoded = wp_json_encode( $clean );
		return $encoded ? $encoded : false;
	}
}
