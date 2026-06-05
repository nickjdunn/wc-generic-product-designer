<?php
/**
 * Convert Fabric.js JSON objects to SVG fragments.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fabric JSON to SVG helpers.
 */
class WC_GPD_Fabric_Svg {

	/**
	 * Render Fabric objects as SVG group content.
	 *
	 * @param array $objects Fabric object list.
	 * @return string
	 */
	public static function objects_to_fragment( $objects ) {
		if ( ! is_array( $objects ) ) {
			return '';
		}

		$parts = array();
		foreach ( $objects as $object ) {
			if ( ! is_array( $object ) ) {
				continue;
			}
			$markup = self::object_to_markup( $object );
			if ( $markup ) {
				$parts[] = $markup;
			}
		}

		return implode( "\n", $parts );
	}

	/**
	 * Filter objects by wcGpdLayerType.
	 *
	 * @param array  $objects Fabric objects.
	 * @param string $type    outline|text|shape.
	 * @return array
	 */
	public static function filter_by_layer_type( $objects, $type ) {
		if ( ! is_array( $objects ) ) {
			return array();
		}

		$filtered = array();
		foreach ( $objects as $object ) {
			if ( ! is_array( $object ) ) {
				continue;
			}
			$layer_type = self::resolve_layer_type( $object );
			if ( $type === $layer_type ) {
				$filtered[] = $object;
			}
		}

		return $filtered;
	}

	/**
	 * Apply production outline stroke from per-product export settings.
	 *
	 * @param array $objects          Outline Fabric objects.
	 * @param array $product_settings Product designer settings.
	 * @return array
	 */
	public static function apply_export_outline_style( $objects, $product_settings ) {
		if ( ! is_array( $objects ) || ! is_array( $product_settings ) ) {
			return is_array( $objects ) ? $objects : array();
		}

		$color = ! empty( $product_settings['export_outline_color'] )
			? sanitize_hex_color( (string) $product_settings['export_outline_color'] )
			: '#ff0000';
		$width = isset( $product_settings['export_outline_width'] )
			? (float) $product_settings['export_outline_width']
			: 0.25;

		if ( ! $color ) {
			$color = '#ff0000';
		}

		foreach ( $objects as &$object ) {
			if ( ! is_array( $object ) ) {
				continue;
			}
			$object['stroke']      = $color;
			$object['strokeWidth'] = max( 0.1, $width );
			$object['fill']        = 'transparent';
		}
		unset( $object );

		return $objects;
	}

	/**
	 * Resolve layer type from Fabric object flags.
	 *
	 * @param array $object Fabric object.
	 * @return string outline|text|shape
	 */
	public static function resolve_layer_type( $object ) {
		if ( ! empty( $object['wcGpdLayerType'] ) ) {
			return sanitize_key( (string) $object['wcGpdLayerType'] );
		}
		if ( ! empty( $object['wcGpdBoundingBox'] ) || ! empty( $object['wcGpdOutlineLayer'] ) ) {
			return 'outline';
		}
		if ( ! empty( $object['wcGpdTextLayer'] ) ) {
			return 'text';
		}

		$type = isset( $object['type'] ) ? strtolower( (string) $object['type'] ) : '';
		if ( in_array( $type, array( 'i-text', 'text', 'textbox' ), true ) ) {
			return 'text';
		}
		if ( in_array( $type, array( 'rect', 'circle', 'ellipse' ), true ) ) {
			return 'shape';
		}

		return 'shape';
	}

	/**
	 * Convert one Fabric object to SVG markup.
	 *
	 * @param array $object Fabric object.
	 * @return string
	 */
	private static function object_to_markup( $object ) {
		$type = isset( $object['type'] ) ? strtolower( (string) $object['type'] ) : '';

		switch ( $type ) {
			case 'rect':
				return self::rect_markup( $object );
			case 'circle':
				return self::circle_markup( $object );
			case 'ellipse':
				return self::ellipse_markup( $object );
			case 'i-text':
			case 'text':
			case 'textbox':
				return self::text_markup( $object );
			default:
				return '';
		}
	}

	/**
	 * @param array $object Fabric rect.
	 * @return string
	 */
	private static function rect_markup( $object ) {
		$width  = self::dimension( $object, 'width' );
		$height = self::dimension( $object, 'height' );
		$left   = self::position( $object, 'left' );
		$top    = self::position( $object, 'top' );

		return sprintf(
			'<rect x="%1$s" y="%2$s" width="%3$s" height="%4$s" fill="%5$s" stroke="%6$s" stroke-width="%7$s" />',
			esc_attr( (string) round( $left, 2 ) ),
			esc_attr( (string) round( $top, 2 ) ),
			esc_attr( (string) round( $width, 2 ) ),
			esc_attr( (string) round( $height, 2 ) ),
			esc_attr( self::fill( $object ) ),
			esc_attr( self::stroke( $object ) ),
			esc_attr( (string) self::stroke_width( $object ) )
		);
	}

	/**
	 * @param array $object Fabric circle.
	 * @return string
	 */
	private static function circle_markup( $object ) {
		$radius = self::dimension( $object, 'radius' );
		$left   = self::position( $object, 'left' );
		$top    = self::position( $object, 'top' );

		return sprintf(
			'<circle cx="%1$s" cy="%2$s" r="%3$s" fill="%4$s" stroke="%5$s" stroke-width="%6$s" />',
			esc_attr( (string) round( $left, 2 ) ),
			esc_attr( (string) round( $top, 2 ) ),
			esc_attr( (string) round( $radius, 2 ) ),
			esc_attr( self::fill( $object ) ),
			esc_attr( self::stroke( $object ) ),
			esc_attr( (string) self::stroke_width( $object ) )
		);
	}

	/**
	 * @param array $object Fabric ellipse.
	 * @return string
	 */
	private static function ellipse_markup( $object ) {
		$rx   = self::dimension( $object, 'rx', self::dimension( $object, 'radius' ) );
		$ry   = self::dimension( $object, 'ry', self::dimension( $object, 'radius' ) );
		$left = self::position( $object, 'left' );
		$top  = self::position( $object, 'top' );

		return sprintf(
			'<ellipse cx="%1$s" cy="%2$s" rx="%3$s" ry="%4$s" fill="%5$s" stroke="%6$s" stroke-width="%7$s" />',
			esc_attr( (string) round( $left, 2 ) ),
			esc_attr( (string) round( $top, 2 ) ),
			esc_attr( (string) round( $rx, 2 ) ),
			esc_attr( (string) round( $ry, 2 ) ),
			esc_attr( self::fill( $object ) ),
			esc_attr( self::stroke( $object ) ),
			esc_attr( (string) self::stroke_width( $object ) )
		);
	}

	/**
	 * @param array $object Fabric text.
	 * @return string
	 */
	private static function text_markup( $object ) {
		$text = isset( $object['text'] ) ? (string) $object['text'] : '';
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}

		$left      = self::position( $object, 'left' );
		$top       = self::position( $object, 'top' );
		$font_size = isset( $object['fontSize'] ) ? absint( $object['fontSize'] ) : 32;
		$family    = isset( $object['fontFamily'] ) ? sanitize_text_field( (string) $object['fontFamily'] ) : 'Arial';
		$fill      = self::fill( $object, '#000000' );
		$weight    = ( ! empty( $object['fontWeight'] ) && 'bold' === $object['fontWeight'] ) ? 'bold' : 'normal';
		$style     = ( ! empty( $object['fontStyle'] ) && 'italic' === $object['fontStyle'] ) ? 'italic' : 'normal';
		$anchor    = 'start';

		if ( ! empty( $object['textAlign'] ) ) {
			$align = sanitize_key( (string) $object['textAlign'] );
			if ( 'center' === $align ) {
				$anchor = 'middle';
			} elseif ( 'right' === $align ) {
				$anchor = 'end';
			}
		}

		return sprintf(
			'<text x="%1$s" y="%2$s" font-family="%3$s" font-size="%4$s" fill="%5$s" font-weight="%6$s" font-style="%7$s" text-anchor="%8$s">%9$s</text>',
			esc_attr( (string) round( $left, 2 ) ),
			esc_attr( (string) round( $top, 2 ) ),
			esc_attr( $family ),
			esc_attr( (string) $font_size ),
			esc_attr( $fill ),
			esc_attr( $weight ),
			esc_attr( $style ),
			esc_attr( $anchor ),
			esc_html( $text )
		);
	}

	/**
	 * @param array  $object Object.
	 * @param string $key    Dimension key.
	 * @param float  $fallback Fallback.
	 * @return float
	 */
	private static function dimension( $object, $key, $fallback = 0 ) {
		$value   = isset( $object[ $key ] ) ? (float) $object[ $key ] : (float) $fallback;
		$scale_x = isset( $object['scaleX'] ) ? (float) $object['scaleX'] : 1;
		$scale_y = isset( $object['scaleY'] ) ? (float) $object['scaleY'] : 1;

		if ( in_array( $key, array( 'width', 'radius', 'rx' ), true ) ) {
			return $value * $scale_x;
		}
		if ( in_array( $key, array( 'height', 'ry' ), true ) ) {
			return $value * $scale_y;
		}

		return $value;
	}

	/**
	 * @param array  $object Object.
	 * @param string $key    left|top.
	 * @return float
	 */
	private static function position( $object, $key ) {
		return isset( $object[ $key ] ) ? (float) $object[ $key ] : 0;
	}

	/**
	 * @param array  $object  Object.
	 * @param string $default Default color.
	 * @return string
	 */
	private static function fill( $object, $default = 'none' ) {
		if ( empty( $object['fill'] ) || 'transparent' === $object['fill'] ) {
			return $default === 'none' ? 'none' : $default;
		}
		return sanitize_hex_color( (string) $object['fill'] ) ?: $default;
	}

	/**
	 * @param array $object Object.
	 * @return string
	 */
	private static function stroke( $object ) {
		if ( empty( $object['stroke'] ) ) {
			return '#000000';
		}
		return sanitize_hex_color( (string) $object['stroke'] ) ?: '#000000';
	}

	/**
	 * @param array $object Object.
	 * @return float
	 */
	private static function stroke_width( $object ) {
		return isset( $object['strokeWidth'] ) ? max( 0, (float) $object['strokeWidth'] ) : 1;
	}

	/**
	 * Decode JSON string to objects array.
	 *
	 * @param string $json JSON.
	 * @return array
	 */
	public static function decode_objects( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return array();
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data['objects'] ) || ! is_array( $data['objects'] ) ) {
			return array();
		}
		return $data['objects'];
	}
}
