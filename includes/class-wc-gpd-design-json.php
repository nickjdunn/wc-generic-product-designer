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
	const FORMAT_V2 = 2;

	/**
	 * Allowed Fabric object types.
	 *
	 * @var string[]
	 */
	private static $allowed_types = array(
		'i-text',
		'text',
		'textbox',
		'rect',
		'circle',
		'ellipse',
		'image',
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
		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( ! empty( $data['views'] ) && is_array( $data['views'] ) ) {
			return self::sanitize_multi_view( $data );
		}

		if ( empty( $data['objects'] ) || ! is_array( $data['objects'] ) ) {
			return false;
		}

		$objects = self::sanitize_objects( $data['objects'] );
		if ( empty( $objects ) ) {
			return false;
		}

		$encoded = wp_json_encode(
			array(
				'version' => self::FORMAT_V2,
				'views'   => array(
					'view_front' => array(
						'objects' => $objects,
					),
				),
			)
		);

		return $encoded ? $encoded : false;
	}

	/**
	 * @param array $data Design JSON.
	 * @return string|false
	 */
	private static function sanitize_multi_view( array $data ) {
		$views = array();

		foreach ( $data['views'] as $view_id => $view_data ) {
			$id = sanitize_key( (string) $view_id );
			if ( ! $id || ! is_array( $view_data ) || empty( $view_data['objects'] ) || ! is_array( $view_data['objects'] ) ) {
				continue;
			}

			$objects = self::sanitize_objects( $view_data['objects'] );
			if ( ! empty( $objects ) ) {
				$views[ $id ] = array( 'objects' => $objects );
			}
		}

		if ( empty( $views ) ) {
			return false;
		}

		$encoded = wp_json_encode(
			array(
				'version' => self::FORMAT_V2,
				'views'   => $views,
			)
		);

		return $encoded ? $encoded : false;
	}

	/**
	 * @param array $objects Raw objects.
	 * @return array
	 */
	private static function sanitize_objects( array $objects ) {
		$clean = array();

		foreach ( $objects as $object ) {
			if ( ! is_array( $object ) || empty( $object['type'] ) ) {
				continue;
			}

			$type = strtolower( (string) $object['type'] );
			if ( ! in_array( $type, self::$allowed_types, true ) ) {
				continue;
			}

			if ( ! empty( $object['wcGpdOutlineLayer'] ) || ! empty( $object['wcGpdBoundingBox'] ) ) {
				continue;
			}

			if ( ! empty( $object['wcGpdTemplateLayer'] ) && empty( $object['wcGpdPlaceholderKey'] ) && 'placeholder' !== ( $object['wcGpdLayerType'] ?? '' ) ) {
				continue;
			}

			if ( in_array( $type, array( 'i-text', 'text', 'textbox' ), true ) ) {
				$text = isset( $object['text'] ) ? trim( (string) $object['text'] ) : '';
				$is_placeholder = ! empty( $object['wcGpdPlaceholderKey'] ) || ( ! empty( $object['wcGpdLayerType'] ) && 'placeholder' === $object['wcGpdLayerType'] );
				if ( '' === $text && ! $is_placeholder ) {
					continue;
				}
				$object['wcGpdLayerType'] = $is_placeholder ? 'placeholder' : 'text';
				$object['wcGpdTextLayer']  = true;
				if ( $is_placeholder && ! empty( $object['wcGpdPlaceholderKey'] ) ) {
					$object['wcGpdPlaceholderKey'] = sanitize_key( (string) $object['wcGpdPlaceholderKey'] );
				}
			} elseif ( 'image' === $type ) {
				$src = ! empty( $object['src'] ) ? esc_url_raw( (string) $object['src'] ) : '';
				if ( ! $src ) {
					continue;
				}
				$object['src']            = $src;
				$object['wcGpdLayerType'] = 'graphic';
				$object['wcGpdGraphicLayer'] = true;
				if ( ! empty( $object['wcGpdGraphicSlotUid'] ) ) {
					$object['wcGpdGraphicSlotUid'] = sanitize_text_field( (string) $object['wcGpdGraphicSlotUid'] );
				}
				if ( ! empty( $object['wcGpdAttachmentId'] ) ) {
					$object['wcGpdAttachmentId'] = absint( $object['wcGpdAttachmentId'] );
				}
			} else {
				$object['wcGpdLayerType'] = 'shape';
			}

			$clean[] = $object;
		}

		return $clean;
	}

	/**
	 * Parse design JSON into a normalized structure.
	 *
	 * @param string $json Stored JSON.
	 * @return array
	 */
	public static function parse( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return array(
				'version' => self::FORMAT_V2,
				'views'   => array(),
			);
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array(
				'version' => self::FORMAT_V2,
				'views'   => array(),
			);
		}

		if ( ! empty( $data['views'] ) && is_array( $data['views'] ) ) {
			return array(
				'version' => self::FORMAT_V2,
				'views'   => $data['views'],
			);
		}

		if ( ! empty( $data['objects'] ) && is_array( $data['objects'] ) ) {
			return array(
				'version' => self::FORMAT_V2,
				'views'   => array(
					'view_front' => array(
						'objects' => $data['objects'],
					),
				),
			);
		}

		return array(
			'version' => self::FORMAT_V2,
			'views'   => array(),
		);
	}

	/**
	 * Whether any view contains customer objects.
	 *
	 * @param string $json Stored JSON.
	 * @return bool
	 */
	public static function has_design( $json ) {
		$parsed = self::parse( $json );
		if ( empty( $parsed['views'] ) || ! is_array( $parsed['views'] ) ) {
			return false;
		}

		foreach ( $parsed['views'] as $view ) {
			if ( ! empty( $view['objects'] ) && is_array( $view['objects'] ) && count( $view['objects'] ) > 0 ) {
				return true;
			}
		}

		return false;
	}
}
