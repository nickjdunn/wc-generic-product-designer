<?php
/**
 * Sanitize admin template canvas JSON (multi-view outlines / bounding boxes).
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template JSON sanitizer.
 */
class WC_GPD_Template_Json {

	const MAX_BYTES = 524288;
	const FORMAT_V2 = 2;

	/**
	 * @var string[]
	 */
	private static $allowed_types = array(
		'rect',
		'circle',
		'ellipse',
		'image',
	);

	/**
	 * Sanitize template JSON from admin.
	 *
	 * @param string $json Raw JSON.
	 * @return string|false
	 */
	public static function sanitize( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return wp_json_encode( self::empty_document() );
		}

		if ( strlen( $json ) > self::MAX_BYTES ) {
			return false;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return false;
		}

		$normalized = self::normalize_array( $data );
		$encoded    = wp_json_encode( $normalized );

		return $encoded ? $encoded : false;
	}

	/**
	 * Parse and normalize template data for PHP/JS consumers.
	 *
	 * @param string $json Raw or stored JSON.
	 * @return array
	 */
	public static function parse( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return self::empty_document();
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return self::empty_document();
		}

		return self::normalize_array( $data );
	}

	/**
	 * Default empty multi-view document.
	 *
	 * @return array
	 */
	public static function empty_document() {
		return array(
			'version' => self::FORMAT_V2,
			'views'   => array(
				self::empty_view( 'view_front', __( 'Front', 'wc-generic-product-designer' ) ),
			),
		);
	}

	/**
	 * @param string $id    View ID.
	 * @param string $label View label.
	 * @return array
	 */
	public static function empty_view( $id, $label ) {
		return array(
			'id'               => sanitize_key( $id ),
			'label'            => sanitize_text_field( $label ),
			'template_image_id' => 0,
			'bounding_box_uid' => '',
			'objects'          => array(),
		);
	}

	/**
	 * @param array $data Decoded JSON.
	 * @return array
	 */
	private static function normalize_array( array $data ) {
		if ( ! empty( $data['views'] ) && is_array( $data['views'] ) ) {
			$views = array();
			foreach ( $data['views'] as $view ) {
				if ( ! is_array( $view ) ) {
					continue;
				}
				$clean = self::sanitize_view( $view );
				if ( $clean ) {
					$views[] = $clean;
				}
			}
			if ( empty( $views ) ) {
				$views[] = self::empty_view( 'view_front', __( 'Front', 'wc-generic-product-designer' ) );
			}
			return array(
				'version' => self::FORMAT_V2,
				'views'   => $views,
			);
		}

		// Legacy single-canvas format.
		$objects = array();
		if ( ! empty( $data['objects'] ) && is_array( $data['objects'] ) ) {
			$objects = self::sanitize_objects( $data['objects'] );
		}

		$view = self::empty_view( 'view_front', __( 'Front', 'wc-generic-product-designer' ) );
		$view['objects'] = $objects;
		$view['bounding_box_uid'] = self::find_bounding_box_uid( $objects );

		return array(
			'version' => self::FORMAT_V2,
			'views'   => array( $view ),
		);
	}

	/**
	 * @param array $view View payload.
	 * @return array|null
	 */
	private static function sanitize_view( array $view ) {
		$id = ! empty( $view['id'] ) ? sanitize_key( (string) $view['id'] ) : '';
		if ( ! $id ) {
			return null;
		}

		$label = ! empty( $view['label'] ) ? sanitize_text_field( (string) $view['label'] ) : $id;
		$objects = ! empty( $view['objects'] ) && is_array( $view['objects'] )
			? self::sanitize_objects( $view['objects'] )
			: array();

		$bbox_uid = ! empty( $view['bounding_box_uid'] ) ? sanitize_text_field( (string) $view['bounding_box_uid'] ) : '';
		$bbox_uid = self::enforce_single_bounding_box( $objects, $bbox_uid );

		return array(
			'id'                => $id,
			'label'             => $label,
			'template_image_id' => isset( $view['template_image_id'] ) ? absint( $view['template_image_id'] ) : 0,
			'bounding_box_uid'  => $bbox_uid,
			'objects'           => $objects,
		);
	}

	/**
	 * @param array $objects Fabric objects.
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

			$uid = ! empty( $object['wcGpdUid'] ) ? sanitize_text_field( (string) $object['wcGpdUid'] ) : 'gpd-' . wp_generate_password( 10, false );

			if ( 'image' === $type ) {
				$src = ! empty( $object['src'] ) ? esc_url_raw( (string) $object['src'] ) : '';
				if ( ! $src ) {
					continue;
				}
				$object['type']               = 'image';
				$object['src']                = $src;
				$object['wcGpdUid']           = $uid;
				$object['wcGpdTemplateLayer'] = true;
				$object['wcGpdMockupImage']   = true;
				$object['wcGpdMockupVisible'] = ! isset( $object['wcGpdMockupVisible'] ) || ! empty( $object['wcGpdMockupVisible'] );
				$object['wcGpdLayerType']     = 'mockup';
				$object['wcGpdAttachmentId']  = isset( $object['wcGpdAttachmentId'] ) ? absint( $object['wcGpdAttachmentId'] ) : 0;
				$object['wcGpdOutlineLayer']  = false;
				$object['wcGpdBoundingBox']   = false;
				$clean[] = $object;
				continue;
			}

			$is_outline = ! empty( $object['wcGpdOutlineLayer'] );
			$is_bbox    = $is_outline && ! empty( $object['wcGpdBoundingBox'] );

			$object['wcGpdUid']           = $uid;
			$object['wcGpdTemplateLayer'] = true;
			$object['wcGpdOutlineLayer']  = $is_outline;
			$object['wcGpdBoundingBox']   = $is_bbox;
			$object['wcGpdLayerType']     = $is_outline ? 'outline' : 'shape';
			$object['selectable']         = false;
			$object['evented']            = false;

			$clean[] = $object;
		}

		return $clean;
	}

	/**
	 * Keep only one bounding box flag per view.
	 *
	 * @param array  $objects  Sanitized objects.
	 * @param string $preferred Preferred UID.
	 * @return string
	 */
	private static function enforce_single_bounding_box( array &$objects, $preferred = '' ) {
		$active_uid = '';
		$found      = false;

		foreach ( $objects as &$object ) {
			if ( empty( $object['wcGpdBoundingBox'] ) ) {
				continue;
			}

			$uid = isset( $object['wcGpdUid'] ) ? (string) $object['wcGpdUid'] : '';
			if ( ! $found && ( ! $preferred || $preferred === $uid ) ) {
				$object['wcGpdBoundingBox']  = true;
				$object['wcGpdOutlineLayer'] = true;
				$object['wcGpdLayerType']    = 'outline';
				$active_uid                  = $uid;
				$found                       = true;
				continue;
			}

			$object['wcGpdBoundingBox'] = false;
		}
		unset( $object );

		return $active_uid;
	}

	/**
	 * @param array $objects Objects list.
	 * @return string
	 */
	private static function find_bounding_box_uid( array $objects ) {
		foreach ( $objects as $object ) {
			if ( ! empty( $object['wcGpdBoundingBox'] ) && ! empty( $object['wcGpdUid'] ) ) {
				return (string) $object['wcGpdUid'];
			}
		}
		return '';
	}
}
