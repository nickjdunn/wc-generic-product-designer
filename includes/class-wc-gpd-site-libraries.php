<?php
/**
 * Site-wide reusable color palettes and font libraries.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared palette / font library definitions (Template Designer → Libraries).
 */
class WC_GPD_Site_Libraries {

	const OPTION_COLOR_PALETTES = 'wc_gpd_site_color_palettes';
	const OPTION_FONT_LIBRARIES = 'wc_gpd_site_font_libraries';

	/**
	 * @return array
	 */
	public static function default_color_palettes_document() {
		return array(
			'palettes' => array(
				array(
					'id'     => 'pal_default',
					'name'   => __( 'Default', 'wc-generic-product-designer' ),
					'colors' => array( '#000000' ),
				),
			),
		);
	}

	/**
	 * @return array
	 */
	public static function default_font_libraries_document() {
		return array(
			'libraries' => array(
				array(
					'id'    => 'fp_default',
					'name'  => __( 'Default', 'wc-generic-product-designer' ),
					'fonts' => array(),
				),
			),
		);
	}

	/**
	 * @return array
	 */
	public static function get_color_palettes_document() {
		$raw = get_option( self::OPTION_COLOR_PALETTES, array() );
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$data = json_decode( $raw, true );
			if ( is_array( $data ) ) {
				return self::sanitize_color_palettes_document( $data );
			}
		}
		if ( is_array( $raw ) && ! empty( $raw ) ) {
			return self::sanitize_color_palettes_document( $raw );
		}
		return self::default_color_palettes_document();
	}

	/**
	 * @param array $data Document.
	 * @return array
	 */
	public static function sanitize_color_palettes_document( array $data ) {
		$defaults = self::default_color_palettes_document();
		$clean    = array( 'palettes' => array() );

		if ( ! empty( $data['palettes'] ) && is_array( $data['palettes'] ) ) {
			foreach ( $data['palettes'] as $palette ) {
				if ( ! is_array( $palette ) ) {
					continue;
				}
				$id = ! empty( $palette['id'] ) ? sanitize_key( (string) $palette['id'] ) : '';
				if ( ! $id ) {
					continue;
				}
				$name   = ! empty( $palette['name'] ) ? sanitize_text_field( (string) $palette['name'] ) : $id;
				$colors = array();
				if ( ! empty( $palette['colors'] ) && is_array( $palette['colors'] ) ) {
					foreach ( $palette['colors'] as $color ) {
						$hex = sanitize_hex_color( (string) $color );
						if ( $hex ) {
							$colors[] = $hex;
						}
					}
				}
				if ( empty( $colors ) ) {
					$colors[] = '#000000';
				}
				$clean['palettes'][] = array(
					'id'     => $id,
					'name'   => $name,
					'colors' => array_values( array_unique( $colors ) ),
				);
			}
		}

		if ( empty( $clean['palettes'] ) ) {
			$clean['palettes'] = $defaults['palettes'];
		}

		return $clean;
	}

	/**
	 * @param string $json JSON.
	 * @return array
	 */
	public static function sanitize_color_palettes_json( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return self::default_color_palettes_document();
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return self::default_color_palettes_document();
		}
		return self::sanitize_color_palettes_document( $data );
	}

	/**
	 * @param array $data Document.
	 */
	public static function save_color_palettes_document( array $data ) {
		$clean = self::sanitize_color_palettes_document( $data );
		update_option( self::OPTION_COLOR_PALETTES, wp_json_encode( $clean ) );
	}

	/**
	 * @return array
	 */
	public static function get_font_libraries_document() {
		$raw = get_option( self::OPTION_FONT_LIBRARIES, array() );
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$data = json_decode( $raw, true );
			if ( is_array( $data ) ) {
				return self::sanitize_font_libraries_document( $data );
			}
		}
		if ( is_array( $raw ) && ! empty( $raw ) ) {
			return self::sanitize_font_libraries_document( $raw );
		}
		return self::default_font_libraries_document();
	}

	/**
	 * @param array $data Document.
	 * @return array
	 */
	public static function sanitize_font_libraries_document( array $data ) {
		$defaults     = self::default_font_libraries_document();
		$allowed_keys = self::allowed_font_keys();
		$clean        = array( 'libraries' => array() );
		$rows         = ! empty( $data['libraries'] ) ? $data['libraries'] : ( $data['palettes'] ?? array() );

		if ( is_array( $rows ) ) {
			foreach ( $rows as $library ) {
				if ( ! is_array( $library ) ) {
					continue;
				}
				$id = ! empty( $library['id'] ) ? sanitize_key( (string) $library['id'] ) : '';
				if ( ! $id ) {
					continue;
				}
				$name  = ! empty( $library['name'] ) ? sanitize_text_field( (string) $library['name'] ) : $id;
				$fonts = self::sanitize_font_keys( $library['fonts'] ?? array(), $allowed_keys );
				if ( empty( $fonts ) && ! empty( $allowed_keys ) ) {
					$fonts = array_slice( $allowed_keys, 0, min( 8, count( $allowed_keys ) ) );
				}
				$clean['libraries'][] = array(
					'id'    => $id,
					'name'  => $name,
					'fonts' => array_values( array_unique( $fonts ) ),
				);
			}
		}

		if ( empty( $clean['libraries'] ) ) {
			$clean['libraries'] = $defaults['libraries'];
			if ( ! empty( $allowed_keys ) ) {
				$clean['libraries'][0]['fonts'] = array_slice( $allowed_keys, 0, min( 8, count( $allowed_keys ) ) );
			}
		}

		return $clean;
	}

	/**
	 * @param string $json JSON.
	 * @return array
	 */
	public static function sanitize_font_libraries_json( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return self::default_font_libraries_document();
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return self::default_font_libraries_document();
		}
		return self::sanitize_font_libraries_document( $data );
	}

	/**
	 * @param array $data Document.
	 */
	public static function save_font_libraries_document( array $data ) {
		$clean = self::sanitize_font_libraries_document( $data );
		update_option( self::OPTION_FONT_LIBRARIES, wp_json_encode( $clean ) );
	}

	/**
	 * @return string[]
	 */
	public static function allowed_font_keys() {
		$keys = array();
		foreach ( WC_GPD_Font_Registry::all_fonts_catalog() as $key => $font ) {
			$keys[] = sanitize_text_field( (string) $key );
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * @param mixed    $fonts        Font keys.
	 * @param string[] $allowed_keys Allowed keys.
	 * @return string[]
	 */
	private static function sanitize_font_keys( $fonts, array $allowed_keys ) {
		if ( ! is_array( $fonts ) ) {
			return array();
		}
		$clean   = array();
		$allowed = ! empty( $allowed_keys );
		foreach ( $fonts as $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( ! $key ) {
				continue;
			}
			if ( $allowed && ! in_array( $key, $allowed_keys, true ) ) {
				continue;
			}
			$clean[] = $key;
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Font libraries document shaped like legacy template font palettes for JS.
	 *
	 * @return array
	 */
	public static function font_libraries_as_template_palettes() {
		$doc = self::get_font_libraries_document();
		return array(
			'palettes' => $doc['libraries'],
		);
	}
}
