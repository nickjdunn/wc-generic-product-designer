<?php
/**
 * Site-wide font registry (Google + custom uploads).
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Font registry helpers.
 */
class WC_GPD_Font_Registry {

	const OPTION_KEY = 'wc_gpd_font_registry';

	/**
	 * Curated Google Fonts available for activation.
	 *
	 * @return array<string,array{family:string,label:string,weights:string}>
	 */
	public static function google_catalog() {
		return array(
			'times_new_roman' => array(
				'family'  => '"Times New Roman", Times, serif',
				'label'   => 'Times New Roman',
				'weights' => '400,700',
				'google'  => false,
			),
			'arial'           => array(
				'family'  => 'Arial, Helvetica, sans-serif',
				'label'   => 'Arial',
				'weights' => '400,700',
				'google'  => false,
			),
			'roboto'          => array(
				'family'  => 'Roboto, sans-serif',
				'label'   => 'Roboto',
				'weights' => '400,700',
				'google'  => 'Roboto',
			),
			'open_sans'       => array(
				'family'  => '"Open Sans", sans-serif',
				'label'   => 'Open Sans',
				'weights' => '400,700',
				'google'  => 'Open+Sans',
			),
			'lato'            => array(
				'family'  => 'Lato, sans-serif',
				'label'   => 'Lato',
				'weights' => '400,700',
				'google'  => 'Lato',
			),
			'montserrat'      => array(
				'family'  => 'Montserrat, sans-serif',
				'label'   => 'Montserrat',
				'weights' => '400,700',
				'google'  => 'Montserrat',
			),
			'playfair'        => array(
				'family'  => '"Playfair Display", serif',
				'label'   => 'Playfair Display',
				'weights' => '400,700',
				'google'  => 'Playfair+Display',
			),
			'oswald'          => array(
				'family'  => 'Oswald, sans-serif',
				'label'   => 'Oswald',
				'weights' => '400,700',
				'google'  => 'Oswald',
			),
			'great_vibes'     => array(
				'family'  => '"Great Vibes", cursive',
				'label'   => 'Great Vibes',
				'weights' => '400',
				'google'  => 'Great+Vibes',
			),
		);
	}

	/**
	 * @return array{enabled_google:array,default_font:string,custom:array}
	 */
	public static function get_registry() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults = array(
			'enabled_google' => array( 'times_new_roman', 'arial', 'roboto', 'open_sans' ),
			'default_font'   => 'times_new_roman',
			'custom'         => array(),
		);

		$merged = wp_parse_args( $stored, $defaults );
		if ( ! is_array( $merged['enabled_google'] ) ) {
			$merged['enabled_google'] = $defaults['enabled_google'];
		}
		if ( ! is_array( $merged['custom'] ) ) {
			$merged['custom'] = array();
		}

		return $merged;
	}

	/**
	 * @param array $registry Registry payload.
	 */
	public static function save_registry( array $registry ) {
		$catalog = self::google_catalog();
		$enabled = array();
		if ( ! empty( $registry['enabled_google'] ) && is_array( $registry['enabled_google'] ) ) {
			foreach ( $registry['enabled_google'] as $key ) {
				$key = sanitize_key( (string) $key );
				if ( $key && isset( $catalog[ $key ] ) ) {
					$enabled[] = $key;
				}
			}
		}
		if ( empty( $enabled ) ) {
			$enabled = array( 'times_new_roman' );
		}

		$default = ! empty( $registry['default_font'] ) ? sanitize_key( (string) $registry['default_font'] ) : 'times_new_roman';
		if ( ! in_array( $default, $enabled, true ) ) {
			$default = $enabled[0];
		}

		$custom = array();
		if ( ! empty( $registry['custom'] ) && is_array( $registry['custom'] ) ) {
			foreach ( $registry['custom'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id = ! empty( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
				if ( ! $id ) {
					continue;
				}
				$attachment_id = ! empty( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
				$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
				if ( ! $url ) {
					continue;
				}
				$label = ! empty( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $id;
				$custom[ $id ] = array(
					'id'            => $id,
					'label'         => $label,
					'attachment_id' => $attachment_id,
					'url'           => $url,
					'family'        => 'wc-gpd-custom-' . $id,
				);
			}
		}

		update_option(
			self::OPTION_KEY,
			array(
				'enabled_google' => array_values( array_unique( $enabled ) ),
				'default_font'   => $default,
				'custom'         => $custom,
			)
		);
	}

	/**
	 * Fonts enabled for a template (subset of site registry).
	 *
	 * @param int $template_id Template ID.
	 * @return array<int,array{key:string,family:string,label:string,css:string}>
	 */
	public static function fonts_for_template( $template_id = 0 ) {
		$registry = self::get_registry();
		$catalog  = self::google_catalog();
		$allowed  = get_post_meta( absint( $template_id ), '_wc_gpd_template_fonts', true );
		if ( ! is_array( $allowed ) || empty( $allowed ) ) {
			$allowed = $registry['enabled_google'];
		}

		$fonts = array();
		foreach ( $allowed as $key ) {
			$key = sanitize_key( (string) $key );
			if ( $key && isset( $catalog[ $key ] ) ) {
				$row = $catalog[ $key ];
				$fonts[] = array(
					'key'    => $key,
					'family' => $row['family'],
					'label'  => $row['label'],
					'css'    => $row['family'],
				);
			}
		}

		if ( ! empty( $registry['custom'] ) && is_array( $registry['custom'] ) ) {
			foreach ( $registry['custom'] as $custom ) {
				if ( ! is_array( $custom ) || empty( $custom['family'] ) ) {
					continue;
				}
				$key = ! empty( $custom['id'] ) ? sanitize_key( (string) $custom['id'] ) : '';
				if ( $allowed && is_array( $allowed ) && ! empty( $allowed ) && ! in_array( $key, $allowed, true ) && ! in_array( 'custom:' . $key, $allowed, true ) ) {
					continue;
				}
				$fonts[] = array(
					'key'    => 'custom:' . $key,
					'family' => (string) $custom['family'],
					'label'  => ! empty( $custom['label'] ) ? (string) $custom['label'] : $key,
					'css'    => (string) $custom['family'],
					'url'    => ! empty( $custom['url'] ) ? (string) $custom['url'] : '',
				);
			}
		}

		if ( empty( $fonts ) ) {
			$fonts[] = array(
				'key'    => 'times_new_roman',
				'family' => '"Times New Roman", Times, serif',
				'label'  => 'Times New Roman',
				'css'    => '"Times New Roman", Times, serif',
			);
		}

		return $fonts;
	}

	/**
	 * @return string CSS font-family stack.
	 */
	public static function default_font_family() {
		$registry = self::get_registry();
		$catalog  = self::google_catalog();
		$key      = ! empty( $registry['default_font'] ) ? sanitize_key( (string) $registry['default_font'] ) : 'times_new_roman';
		if ( isset( $catalog[ $key ] ) ) {
			return $catalog[ $key ]['family'];
		}
		return '"Times New Roman", Times, serif';
	}

	/**
	 * Enqueue Google/font-face assets for storefront designer.
	 *
	 * @param int $template_id Template ID.
	 */
	public static function enqueue_for_designer( $template_id = 0 ) {
		$registry = self::get_registry();
		$catalog  = self::google_catalog();
		$families = array();

		foreach ( $registry['enabled_google'] as $key ) {
			if ( empty( $catalog[ $key ]['google'] ) ) {
				continue;
			}
			$google = $catalog[ $key ]['google'];
			$weight = ! empty( $catalog[ $key ]['weights'] ) ? $catalog[ $key ]['weights'] : '400,700';
			$families[] = 'family=' . rawurlencode( str_replace( '+', ' ', $google ) ) . ':wght@' . $weight;
		}

		if ( ! empty( $families ) ) {
			$url = 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
			wp_enqueue_style( 'wc-gpd-google-fonts', $url, array(), WC_GPD_VERSION );
		}

		$custom_css = self::custom_font_face_css( $template_id );
		if ( $custom_css ) {
			wp_register_style( 'wc-gpd-custom-fonts', false, array(), WC_GPD_VERSION );
			wp_enqueue_style( 'wc-gpd-custom-fonts' );
			wp_add_inline_style( 'wc-gpd-custom-fonts', $custom_css );
		}
	}

	/**
	 * @param int $template_id Template ID.
	 * @return string
	 */
	public static function custom_font_face_css( $template_id = 0 ) {
		$fonts = self::fonts_for_template( $template_id );
		$parts = array();
		foreach ( $fonts as $font ) {
			if ( empty( $font['url'] ) || empty( $font['family'] ) ) {
				continue;
			}
			$family = preg_replace( '/[^a-z0-9\-]/i', '', (string) $font['family'] );
			$parts[] = sprintf(
				"@font-face{font-family:%s;src:url('%s') format('woff2'),url('%s') format('woff');font-weight:normal;font-style:normal;}",
				$family,
				esc_url_raw( $font['url'] ),
				esc_url_raw( $font['url'] )
			);
		}
		return implode( "\n", $parts );
	}

	/**
	 * @return string[]
	 */
	public static function font_families_for_js( $template_id = 0 ) {
		$fonts = self::fonts_for_template( $template_id );
		$list  = array();
		foreach ( $fonts as $font ) {
			$list[] = $font['family'];
		}
		return $list;
	}
}
