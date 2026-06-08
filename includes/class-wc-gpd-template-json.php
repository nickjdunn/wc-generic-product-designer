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
			'id'                  => sanitize_key( $id ),
			'label'               => sanitize_text_field( $label ),
			'template_image_id'   => 0,
			'bounding_box_uid'    => '',
			'product_outline_uid' => '',
			'imprint_area_uid'    => '',
			'objects'             => array(),
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

		$view            = self::empty_view( 'view_front', __( 'Front', 'wc-generic-product-designer' ) );
		$view['objects'] = $objects;
		$role_uids       = self::find_bbox_role_uids( $objects );
		$view['product_outline_uid'] = $role_uids['product_outline_uid'];
		$view['imprint_area_uid']    = $role_uids['imprint_area_uid'];
		$view['bounding_box_uid']    = $role_uids['product_outline_uid'];

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

		$outline_uid = ! empty( $view['product_outline_uid'] ) ? sanitize_text_field( (string) $view['product_outline_uid'] ) : '';
		if ( ! $outline_uid && ! empty( $view['bounding_box_uid'] ) ) {
			$outline_uid = sanitize_text_field( (string) $view['bounding_box_uid'] );
		}
		$imprint_uid = ! empty( $view['imprint_area_uid'] ) ? sanitize_text_field( (string) $view['imprint_area_uid'] ) : '';

		$role_uids = self::enforce_bbox_roles( $objects, $outline_uid, $imprint_uid );

		return array(
			'id'                  => $id,
			'label'               => $label,
			'template_image_id'   => isset( $view['template_image_id'] ) ? absint( $view['template_image_id'] ) : 0,
			'bounding_box_uid'    => $role_uids['product_outline_uid'],
			'product_outline_uid' => $role_uids['product_outline_uid'],
			'imprint_area_uid'    => $role_uids['imprint_area_uid'],
			'objects'             => $objects,
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
				$clean_obj = self::sanitize_text_object( $object, $type, $uid );
				if ( $clean_obj ) {
					$clean[] = $clean_obj;
				}
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
				self::sanitize_bbox_role_props( $object );
				$object['wcGpdLayerType']     = ! empty( $object['wcGpdLayerType'] ) ? sanitize_key( (string) $object['wcGpdLayerType'] ) : 'shape';
				$object['selectable']         = false;
				$object['evented']            = false;
				if ( ! empty( $object['wcGpdLayerLabel'] ) ) {
					$object['wcGpdLayerLabel'] = sanitize_text_field( (string) $object['wcGpdLayerLabel'] );
				} else {
					unset( $object['wcGpdLayerLabel'] );
				}
				self::sanitize_shape_style_props( $object );
				$clean[] = $object;
				continue;
			}

			$is_outline = ! empty( $object['wcGpdOutlineLayer'] );
			$layer_type = ! empty( $object['wcGpdLayerType'] ) ? sanitize_key( (string) $object['wcGpdLayerType'] ) : ( $is_outline ? 'outline' : 'shape' );
			$is_replaceable = 'graphic_slot' === $layer_type || 'replaceable' === $layer_type || ! empty( $object['wcGpdReplaceable'] ) || ! empty( $object['wcGpdGraphicSlot'] );
			if ( $is_replaceable ) {
				$is_outline = false;
			}

			$object['wcGpdUid']           = $uid;
			$object['wcGpdTemplateLayer'] = true;
			$object['wcGpdOutlineLayer']  = $is_outline;
			self::sanitize_bbox_role_props( $object );
			$object['wcGpdLayerType']     = $layer_type;
			$object['selectable']         = false;
			$object['evented']            = false;

			if ( $is_replaceable ) {
				$object['wcGpdReplaceable']     = true;
				$object['wcGpdReplaceableKind'] = ! empty( $object['wcGpdReplaceableKind'] )
					? sanitize_key( (string) $object['wcGpdReplaceableKind'] )
					: 'graphic';
				if ( 'graphic' !== $object['wcGpdReplaceableKind'] ) {
					$object['wcGpdReplaceableKind'] = 'graphic';
				}
				if ( 'graphic_slot' === $layer_type || ! empty( $object['wcGpdGraphicSlot'] ) ) {
					$object['wcGpdLayerType']   = 'graphic_slot';
					$object['wcGpdGraphicSlot'] = true;
				}
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

			if ( in_array( $layer_type, array( 'shape', 'outline' ), true ) || in_array( $type, array( 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'path', 'line' ), true ) ) {
				self::sanitize_shape_style_props( $object );
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
		$object['wcGpdCustomerPaletteOnly']    = ! array_key_exists( 'wcGpdCustomerPaletteOnly', $object ) || ! empty( $object['wcGpdCustomerPaletteOnly'] );
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

		self::sanitize_layer_color_props( $object );

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
			$is_slot    = ! empty( $object['wcGpdGraphicSlot'] ) || 'graphic_slot' === ( ! empty( $object['wcGpdLayerType'] ) ? sanitize_key( (string) $object['wcGpdLayerType'] ) : '' );
			$layer_type = $is_slot ? 'graphic_slot' : 'graphic';
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
			if ( $is_slot ) {
				$object['wcGpdGraphicSlot']       = true;
				$object['wcGpdReplaceable']     = true;
				$object['wcGpdReplaceableKind'] = 'graphic';
			}
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
	 * Sanitize per-layer palette ID and optional custom color list.
	 *
	 * @param array  $object Fabric object (by reference).
	 * @param string $role   fill|stroke.
	 */
	private static function sanitize_layer_color_props( array &$object, $role = 'fill' ) {
		$id_key     = 'stroke' === $role ? 'wcGpdStrokePaletteId' : 'wcGpdPaletteId';
		$colors_key = 'stroke' === $role ? 'wcGpdStrokeLayerColors' : 'wcGpdLayerColors';
		$palette_id = ! empty( $object[ $id_key ] ) ? sanitize_key( (string) $object[ $id_key ] ) : 'pal_default';
		if ( 'pal_custom' === $palette_id ) {
			$object[ $id_key ] = 'pal_custom';
			$colors            = array();
			if ( ! empty( $object[ $colors_key ] ) && is_array( $object[ $colors_key ] ) ) {
				foreach ( $object[ $colors_key ] as $color ) {
					$hex = sanitize_hex_color( (string) $color );
					if ( $hex ) {
						$colors[] = $hex;
					}
				}
			}
			$object[ $colors_key ] = array_values( array_unique( $colors ) );
			return;
		}

		$object[ $id_key ] = $palette_id;
		unset( $object[ $colors_key ] );
	}

	private static function sanitize_layer_customer_props( array &$object ) {
		$object['wcGpdLockColor']            = ! empty( $object['wcGpdLockColor'] );
		$object['wcGpdLockMove']             = ! empty( $object['wcGpdLockMove'] );
		$object['wcGpdLockScale']            = ! empty( $object['wcGpdLockScale'] );
		$object['wcGpdLockAspect']           = ! empty( $object['wcGpdLockAspect'] );
		$object['wcGpdCustomerEditable']     = ! isset( $object['wcGpdCustomerEditable'] ) || ! empty( $object['wcGpdCustomerEditable'] );
		$object['wcGpdHideFromCustomerLayers'] = ! empty( $object['wcGpdHideFromCustomerLayers'] );
	}

	/**
	 * Sanitize shape/icon fill-stroke flags and palette props.
	 *
	 * @param array $object Fabric object (by reference).
	 */
	private static function sanitize_shape_style_props( array &$object ) {
		$object['wcGpdShapeUseFill']   = ! isset( $object['wcGpdShapeUseFill'] ) || ! empty( $object['wcGpdShapeUseFill'] );
		$object['wcGpdShapeUseStroke'] = ! isset( $object['wcGpdShapeUseStroke'] ) || ! empty( $object['wcGpdShapeUseStroke'] );
		if ( empty( $object['wcGpdShapeUseFill'] ) && empty( $object['wcGpdShapeUseStroke'] ) ) {
			$object['wcGpdShapeUseFill'] = true;
		}
		$object['wcGpdCustomerPaletteOnly'] = ! array_key_exists( 'wcGpdCustomerPaletteOnly', $object ) || ! empty( $object['wcGpdCustomerPaletteOnly'] );
		self::sanitize_layer_customer_props( $object );
		self::sanitize_layer_color_props( $object, 'fill' );
		self::sanitize_layer_color_props( $object, 'stroke' );
	}

	/**
	 * Sanitize bbox role and migrate legacy wcGpdBoundingBox flag.
	 *
	 * @param array $object Fabric object (by reference).
	 */
	private static function sanitize_bbox_role_props( array &$object ) {
		if ( ! empty( $object['wcGpdBoundingBox'] ) && empty( $object['wcGpdBboxRole'] ) ) {
			$object['wcGpdBboxRole'] = 'product_outline';
		}

		$role = ! empty( $object['wcGpdBboxRole'] ) ? sanitize_key( (string) $object['wcGpdBboxRole'] ) : '';
		if ( ! in_array( $role, array( 'product_outline', 'imprint_area', '' ), true ) ) {
			$role = '';
		}

		$object['wcGpdBboxRole']   = $role;
		$object['wcGpdBoundingBox']  = ( 'product_outline' === $role );
	}

	/**
	 * Keep at most one product outline and one imprint area per view.
	 *
	 * @param array  $objects           Sanitized objects.
	 * @param string $preferred_outline Preferred product outline UID.
	 * @param string $preferred_imprint Preferred imprint area UID.
	 * @return array{product_outline_uid:string,imprint_area_uid:string}
	 */
	private static function enforce_bbox_roles( array &$objects, $preferred_outline = '', $preferred_imprint = '' ) {
		$outline_uid   = '';
		$imprint_uid   = '';
		$outline_found = false;
		$imprint_found = false;

		foreach ( $objects as &$object ) {
			self::sanitize_bbox_role_props( $object );

			$role = isset( $object['wcGpdBboxRole'] ) ? (string) $object['wcGpdBboxRole'] : '';
			if ( '' === $role ) {
				continue;
			}

			$uid = isset( $object['wcGpdUid'] ) ? (string) $object['wcGpdUid'] : '';

			if ( 'product_outline' === $role ) {
				if ( ! $outline_found && ( ! $preferred_outline || $preferred_outline === $uid ) ) {
					$object['wcGpdBboxRole']     = 'product_outline';
					$object['wcGpdOutlineLayer'] = true;
					$object['wcGpdBoundingBox']  = true;
					$object['wcGpdLayerType']    = 'outline';
					$outline_uid                 = $uid;
					$outline_found               = true;
					continue;
				}

				$object['wcGpdBboxRole']    = '';
				$object['wcGpdBoundingBox'] = false;
				continue;
			}

			if ( 'imprint_area' === $role ) {
				if ( ! $imprint_found && ( ! $preferred_imprint || $preferred_imprint === $uid ) ) {
					$object['wcGpdBboxRole']     = 'imprint_area';
					$object['wcGpdOutlineLayer'] = true;
					$object['wcGpdBoundingBox']  = false;
					$object['wcGpdLayerType']    = 'outline';
					$imprint_uid                 = $uid;
					$imprint_found               = true;
					continue;
				}

				$object['wcGpdBboxRole'] = '';
			}
		}
		unset( $object );

		return array(
			'product_outline_uid' => $outline_uid,
			'imprint_area_uid'    => $imprint_uid,
		);
	}

	/**
	 * @param array $objects Objects list.
	 * @return array{product_outline_uid:string,imprint_area_uid:string}
	 */
	private static function find_bbox_role_uids( array $objects ) {
		$outline_uid = '';
		$imprint_uid = '';

		foreach ( $objects as $object ) {
			if ( empty( $object['wcGpdUid'] ) ) {
				continue;
			}

			if ( ! empty( $object['wcGpdBoundingBox'] ) && ! $outline_uid ) {
				$outline_uid = (string) $object['wcGpdUid'];
			}

			$role = ! empty( $object['wcGpdBboxRole'] ) ? (string) $object['wcGpdBboxRole'] : '';
			if ( 'product_outline' === $role && ! $outline_uid ) {
				$outline_uid = (string) $object['wcGpdUid'];
			}
			if ( 'imprint_area' === $role && ! $imprint_uid ) {
				$imprint_uid = (string) $object['wcGpdUid'];
			}
		}

		return array(
			'product_outline_uid' => $outline_uid,
			'imprint_area_uid'    => $imprint_uid,
		);
	}
}
