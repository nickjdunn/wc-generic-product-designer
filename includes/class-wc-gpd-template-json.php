<?php
/**
 * Sanitize admin template canvas JSON (outlines / guides).
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template JSON sanitizer.
 */
class WC_GPD_Template_Json {

	const MAX_BYTES = 262144;

	/**
	 * @var string[]
	 */
	private static $allowed_types = array(
		'rect',
		'circle',
		'ellipse',
	);

	/**
	 * Sanitize template JSON from admin.
	 *
	 * @param string $json Raw JSON.
	 * @return string|false
	 */
	public static function sanitize( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return '';
		}

		if ( strlen( $json ) > self::MAX_BYTES ) {
			return false;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( empty( $data['objects'] ) || ! is_array( $data['objects'] ) ) {
			return wp_json_encode(
				array(
					'version' => '5.3.1',
					'objects' => array(),
				)
			);
		}

		$objects = array();
		foreach ( $data['objects'] as $object ) {
			if ( ! is_array( $object ) || empty( $object['type'] ) ) {
				continue;
			}

			$type = strtolower( (string) $object['type'] );
			if ( ! in_array( $type, self::$allowed_types, true ) ) {
				continue;
			}

			$object['wcGpdLayerType']    = ! empty( $object['wcGpdOutlineLayer'] ) ? 'outline' : 'shape';
			$object['wcGpdOutlineLayer']   = ! empty( $object['wcGpdOutlineLayer'] );
			$object['wcGpdTemplateLayer'] = true;
			$object['selectable']          = false;
			$object['evented']             = false;

			$objects[] = $object;
		}

		$encoded = wp_json_encode(
			array(
				'version' => isset( $data['version'] ) ? sanitize_text_field( (string) $data['version'] ) : '5.3.1',
				'objects' => $objects,
			)
		);

		return $encoded ? $encoded : false;
	}
}
