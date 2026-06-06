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
		'polygon',
		'polyline',
		'path',
		'line',
		'image',
		'i-text',
		'text',
		'textbox',
		'group',
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
			'id'                => sanitize_key( $id ),
			'label'             => sanitize_text_field( $label ),
			'template_image_id' => 0,
			'bounding_box_uid'  => '',
			'objects'           => array(),
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

		$view                     = self::empty_view( 'view_front', __( 'Front', 'wc-generic-product-designer' ) );
		$view['objects']          = $objects;
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

		$label   = ! empty( $view['label'] ) ? sanitize_text_field( (string) $view['label'] ) : $id;
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

			if ( in_array( $type, array( 'i-text', 'text', 'textbox' ), true ) ) {
				$clean[] = self::sanitize_text_object( $object, $type, $uid );
				continue;
			}

			if ( 'image' === $type ) {
				$clean_obj = self::sanitize_image_object( $object, $uid );
				if ( $clean_obj ) {
					$clean[] = $clean_obj;
				}
				continue;
			}

			if ( 'group' === $type ) {
				$object['wcGpdUid']           = $uid;
				$object['wcGpdTemplateLayer'] = true;
				$object['wcGpdOutlineLayer']  = ! empty( $object['wcGpdOutlineLayer'] );
				$object['wcGpdBoundingBox']   = ! empty( $object['wcGpdBoundingBox'] );
				$object['wcGpdLayerType']     = ! empty( $object['wcGpdLayerType'] ) ? sanitize_key( (string) $object['wcGpdLayerType'] ) : 'shape';
				$object['selectable']         = false;
				$object['evented']            = false;
				if ( ! empty( $object['wcGpdLayerLabel'] ) ) {
					$object['wcGpdLayerLabel'] = sanitize_text_field( (string) $object['wcGpdLayerLabel'] );
				} else {
					unset( $object['wcGpdLayerLabel'] );
				}
				$clean[] = $object;
				continue;
			}

			$is_outline = ! empty( $object['wcGpdOutlineLayer'] );
			$is_bbox    = $is_outline && ! empty( $object['wcGpdBoundingBox'] );
			$layer_type = ! empty( $object['wcGpdLayerType'] ) ? sanitize_key( (string) $object['wcGpdLayerType'] ) : ( $is_outline ? 'outline' : 'shape' );
			if ( 'graphic_slot' === $layer_type ) {
				$is_outline = false;
				$is_bbox    = false;
			}

			$object['wcGpdUid']           = $uid;
			$object['wcGpdTemplateLayer'] = true;
			$object['wcGpdOutlineLayer']  = $is_outline;
			$object['wcGpdBoundingBox']   = $is_bbox;
			$object['wcGpdLayerType']     = $layer_type;
			$object['selectable']         = false;
			$object['evented']            = false;

			if ( 'graphic_slot' === $layer_type ) {
				$object['wcGpdCustomerMovable']   = ! empty( $object['wcGpdCustomerMovable'] );
				$object['wcGpdCustomerResizable'] = ! empty( $object['wcGpdCustomerResizable'] );
				$object['wcGpdGraphicLibraryId']  = ! empty( $object['wcGpdGraphicLibraryId'] )
					? sanitize_key( (string) $object['wcGpdGraphicLibraryId'] )
					: '';
			}

			if ( ! empty( $object['wcGpdLayerLabel'] ) ) {
				$object['wcGpdLayerLabel'] = sanitize_text_field( (string) $object['wcGpdLayerLabel'] );
			} else {
				unset( $object['wcGpdLayerLabel'] );
			}

			$clean[] = $object;
		}

		return $clean;
	}

	/**
	 * @param array  $object Fabric text object.
	 * @param string $type   Object type.
	 * @param string $uid    Layer UID.
	 * @return array|null
	 */
	private static function sanitize_text_object( array $object, $type, $uid ) {
		$text = isset( $object['text'] ) ? (string) $object['text'] : '';
		$layer_type = ! empty( $object['wcGpdLayerType'] ) ? sanitize_key( (string) $object['wcGpdLayerType'] ) : 'text';
		if ( ! in_array( $layer_type, array( 'text', 'placeholder' ), true ) ) {
			$layer_type = 'text';
		}

		if ( 'placeholder' === $layer_type && '' === trim( $text ) ) {
			$text = __( 'Your text', 'wc-generic-product-designer' );
		}

		if ( 'text' === $layer_type && '' === trim( $text ) ) {
			return null;
		}

		$object['type']               = $type;
		$object['text']               = sanitize_textarea_field( $text );
		$object['wcGpdUid']           = $uid;
		$object['wcGpdTemplateLayer'] = true;
		$object['wcGpdLayerType']     = $layer_type;
		$object['wcGpdOutlineLayer']  = false;
		$object['wcGpdBoundingBox']   = false;
		$object['wcGpdShrinkToFit']   = ! empty( $object['wcGpdShrinkToFit'] );
		$object['wcGpdLockFont']      = ! empty( $object['wcGpdLockFont'] );
		$object['wcGpdLockSize']      = ! empty( $object['wcGpdLockSize'] );
		$object['wcGpdLockColor']     = ! empty( $object['wcGpdLockColor'] );
		$object['wcGpdLockBold']      = ! empty( $object['wcGpdLockBold'] );
		$object['wcGpdLockItalic']    = ! empty( $object['wcGpdLockItalic'] );
		$object['wcGpdLockAlign']          = ! empty( $object['wcGpdLockAlign'] );
		$object['wcGpdLockUnderline']      = ! empty( $object['wcGpdLockUnderline'] );
		$object['wcGpdLockLineHeight']     = ! empty( $object['wcGpdLockLineHeight'] );
		$object['wcGpdLockLetterSpacing']  = ! empty( $object['wcGpdLockLetterSpacing'] );
		$object['wcGpdLockMove']           = ! empty( $object['wcGpdLockMove'] );
		$object['wcGpdLockScale']          = ! empty( $object['wcGpdLockScale'] );
		$object['wcGpdLockText']           = ! empty( $object['wcGpdLockText'] );
		$object['wcGpdCustomerEditable']   = ! isset( $object['wcGpdCustomerEditable'] ) || ! empty( $object['wcGpdCustomerEditable'] );
		$object['wcGpdHideFromCustomerLayers'] = ! empty( $object['wcGpdHideFromCustomerLayers'] );
		$object['wcGpdCustomerPaletteOnly']    = ! empty( $object['wcGpdCustomerPaletteOnly'] );
		if ( ! empty( $object['wcGpdLayerLabel'] ) ) {
			$object['wcGpdLayerLabel'] = sanitize_text_field( (string) $object['wcGpdLayerLabel'] );
		} else {
			unset( $object['wcGpdLayerLabel'] );
		}

		if ( 'placeholder' === $layer_type ) {
			$key = ! empty( $object['wcGpdPlaceholderKey'] ) ? sanitize_key( (string) $object['wcGpdPlaceholderKey'] ) : sanitize_key( $uid );
			$object['wcGpdPlaceholderKey']   = $key;
			$object['wcGpdPlaceholderLabel'] = ! empty( $object['wcGpdPlaceholderLabel'] )
				? sanitize_text_field( (string) $object['wcGpdPlaceholderLabel'] )
				: __( 'Field', 'wc-generic-product-designer' );
		}

		return $object;
	}

	/**
	 * @param array  $object Fabric image object.
	 * @param string $uid    Layer UID.
	 * @return array|null
	 */
	private static function sanitize_image_object( array $object, $uid ) {
		$src = ! empty( $object['src'] ) ? esc_url_raw( (string) $object['src'] ) : '';
		if ( ! $src ) {
			return null;
		}

		$is_graphic = ! empty( $object['wcGpdGraphicLayer'] );
		if ( $is_graphic ) {
			$layer_type = ! empty( $object['wcGpdGraphicSlot'] ) ? 'graphic_slot' : 'graphic';
			$object['type']                 = 'image';
			$object['src']                  = $src;
			$object['wcGpdUid']             = $uid;
			$object['wcGpdTemplateLayer']   = true;
			$object['wcGpdGraphicLayer']    = true;
			$object['wcGpdLayerType']       = $layer_type;
			$object['wcGpdExportGraphic']   = ! isset( $object['wcGpdExportGraphic'] ) || ! empty( $object['wcGpdExportGraphic'] );
			$object['wcGpdCustomerMovable'] = ! empty( $object['wcGpdCustomerMovable'] );
			$object['wcGpdCustomerResizable'] = ! empty( $object['wcGpdCustomerResizable'] );
			$object['wcGpdAttachmentId']    = isset( $object['wcGpdAttachmentId'] ) ? absint( $object['wcGpdAttachmentId'] ) : 0;
			$object['wcGpdOutlineLayer']    = false;
			$object['wcGpdBoundingBox']     = false;
			$object['wcGpdMockupImage']     = false;
			return $object;
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

		return $object;
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
