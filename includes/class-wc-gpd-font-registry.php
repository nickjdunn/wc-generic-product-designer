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

	const OPTION_KEY         = 'wc_gpd_font_registry';
	const GOOGLE_CACHE_KEY   = 'wc_gpd_google_fonts_metadata';
	const GOOGLE_CACHE_TTL   = WEEK_IN_SECONDS;

	/**
	 * Built-in system fonts (not loaded from Google).
	 *
	 * @return array<string,array{family:string,label:string,weights:string,google:false}>
	 */
	public static function builtin_catalog() {
		return array(
			'times_new_roman' => array(
				'family'        => '"Times New Roman", Times, serif',
				'label'         => 'Times New Roman',
				'display_label' => 'Times New Roman',
				'weights'       => '400,700',
				'google'        => false,
			),
			'arial'           => array(
				'family'        => 'Arial, Helvetica, sans-serif',
				'label'         => 'Arial',
				'display_label' => 'Arial',
				'weights'       => '400,700',
				'google'        => false,
			),
		);
	}

	/**
	 * Legacy curated list — used only to seed first-time installs.
	 *
	 * @return array<string,array{family:string,label:string,weights:string,google:string|false}>
	 */
	public static function seed_google_catalog() {
		return array(
			'roboto'      => array(
				'family'        => 'Roboto, sans-serif',
				'label'         => 'Roboto',
				'display_label' => 'Roboto',
				'weights'       => '400,700',
				'google'        => 'Roboto',
			),
			'open_sans'   => array(
				'family'        => '"Open Sans", sans-serif',
				'label'         => 'Open Sans',
				'display_label' => 'Open Sans',
				'weights'       => '400,700',
				'google'        => 'Open+Sans',
			),
			'lato'        => array(
				'family'        => 'Lato, sans-serif',
				'label'         => 'Lato',
				'display_label' => 'Lato',
				'weights'       => '400,700',
				'google'        => 'Lato',
			),
			'montserrat'  => array(
				'family'        => 'Montserrat, sans-serif',
				'label'         => 'Montserrat',
				'display_label' => 'Montserrat',
				'weights'       => '400,700',
				'google'        => 'Montserrat',
			),
			'playfair'    => array(
				'family'        => '"Playfair Display", serif',
				'label'         => 'Playfair Display',
				'display_label' => 'Playfair Display',
				'weights'       => '400,700',
				'google'        => 'Playfair+Display',
			),
			'oswald'      => array(
				'family'        => 'Oswald, sans-serif',
				'label'         => 'Oswald',
				'display_label' => 'Oswald',
				'weights'       => '400,700',
				'google'        => 'Oswald',
			),
			'great_vibes' => array(
				'family'        => '"Great Vibes", cursive',
				'label'         => 'Great Vibes',
				'display_label' => 'Great Vibes',
				'weights'       => '400',
				'google'        => 'Great+Vibes',
			),
		);
	}

	/**
	 * @deprecated Use builtin_catalog() or all_fonts_catalog().
	 * @return array
	 */
	public static function google_catalog() {
		return array_merge( self::builtin_catalog(), self::seed_google_catalog() );
	}

	/**
	 * @return array{enabled:array,default_font:string,google_fonts:array,custom:array}
	 */
	public static function get_registry() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults = array(
			'enabled'        => array( 'times_new_roman', 'arial', 'roboto', 'open_sans' ),
			'default_font'   => 'times_new_roman',
			'google_fonts'   => self::seed_google_catalog(),
			'custom'         => array(),
			'display_labels' => array(),
		);

		$merged = wp_parse_args( $stored, $defaults );
		if ( ! is_array( $merged['enabled'] ) ) {
			// Migrate legacy enabled_google key.
			$merged['enabled'] = ! empty( $stored['enabled_google'] ) && is_array( $stored['enabled_google'] )
				? $stored['enabled_google']
				: $defaults['enabled'];
		}
		if ( ! is_array( $merged['google_fonts'] ) ) {
			$merged['google_fonts'] = $defaults['google_fonts'];
		}
		if ( ! is_array( $merged['custom'] ) ) {
			$merged['custom'] = array();
		}

		$merged['google_fonts'] = self::normalize_google_fonts( $merged['google_fonts'] );
		if ( ! is_array( $merged['display_labels'] ) ) {
			$merged['display_labels'] = array();
		}

		return $merged;
	}

	/**
	 * @param array $fonts Google font rows keyed by ID.
	 * @return array
	 */
	public static function normalize_google_fonts( array $fonts ) {
		$clean = array();
		foreach ( $fonts as $key => $font ) {
			if ( ! is_array( $font ) ) {
				continue;
			}
			$key = ! empty( $font['key'] ) ? sanitize_key( (string) $font['key'] ) : sanitize_key( (string) $key );
			if ( ! $key ) {
				continue;
			}
			$label = ! empty( $font['label'] ) ? sanitize_text_field( (string) $font['label'] ) : $key;
			$display = ! empty( $font['display_label'] ) ? sanitize_text_field( (string) $font['display_label'] ) : $label;
			$google  = ! empty( $font['google'] ) ? sanitize_text_field( (string) $font['google'] ) : false;
			if ( false === $google && ! empty( $font['family'] ) ) {
				$google = self::google_api_name_from_family( $label );
			}
			$family = ! empty( $font['family'] ) ? sanitize_text_field( (string) $font['family'] ) : self::css_family_from_google_name( $label );
			$clean[ $key ] = array(
				'key'           => $key,
				'family'        => $family,
				'label'         => $label,
				'display_label' => $display,
				'weights'       => ! empty( $font['weights'] ) ? sanitize_text_field( (string) $font['weights'] ) : '400,700',
				'google'        => $google,
			);
		}
		return $clean;
	}

	/**
	 * @param string $family Google family name.
	 * @return string
	 */
	public static function font_key_from_family( $family ) {
		$slug = strtolower( (string) $family );
		$slug = preg_replace( '/[^a-z0-9]+/', '_', $slug );
		return sanitize_key( trim( $slug, '_' ) );
	}

	/**
	 * @param string $family Google family name.
	 * @return string
	 */
	public static function google_api_name_from_family( $family ) {
		return str_replace( ' ', '+', trim( (string) $family ) );
	}

	/**
	 * @param string $family Google family name.
	 * @return string
	 */
	public static function css_family_from_google_name( $family ) {
		$family = trim( (string) $family );
		if ( strpos( $family, ' ' ) !== false ) {
			return '"' . $family . '", sans-serif';
		}
		return $family . ', sans-serif';
	}

	/**
	 * All fonts available on the site (builtin + saved Google + custom).
	 *
	 * @return array<string,array>
	 */
	public static function apply_display_label( $key, array $font, array $overrides ) {
		if ( ! empty( $overrides[ $key ] ) ) {
			$font['display_label'] = sanitize_text_field( (string) $overrides[ $key ] );
		}
		return $font;
	}

	/**
	 * All fonts available on the site (builtin + saved Google + custom).
	 *
	 * @return array<string,array>
	 */
	public static function all_fonts_catalog() {
		$registry = self::get_registry();
		$overrides = ! empty( $registry['display_labels'] ) && is_array( $registry['display_labels'] )
			? $registry['display_labels']
			: array();
		$all      = self::builtin_catalog();

		foreach ( $all as $key => $font ) {
			$all[ $key ] = self::apply_display_label( $key, $font, $overrides );
		}

		foreach ( $registry['google_fonts'] as $key => $font ) {
			$all[ $key ] = self::apply_display_label( $key, $font, $overrides );
		}

		if ( ! empty( $registry['custom'] ) && is_array( $registry['custom'] ) ) {
			foreach ( $registry['custom'] as $custom ) {
				if ( ! is_array( $custom ) || empty( $custom['id'] ) ) {
					continue;
				}
				$id = sanitize_key( (string) $custom['id'] );
				$all[ 'custom:' . $id ] = array(
					'key'           => 'custom:' . $id,
					'family'        => ! empty( $custom['family'] ) ? (string) $custom['family'] : 'wc-gpd-custom-' . $id,
					'label'         => ! empty( $custom['label'] ) ? (string) $custom['label'] : $id,
					'display_label' => ! empty( $custom['display_label'] ) ? (string) $custom['display_label'] : ( ! empty( $custom['label'] ) ? (string) $custom['label'] : $id ),
					'google'        => false,
					'custom'        => true,
					'url'           => ! empty( $custom['url'] ) ? (string) $custom['url'] : '',
				);
			}
		}

		return $all;
	}

	/**
	 * @param array $registry Registry payload.
	 */
	public static function save_registry( array $registry ) {
		$enabled = array();
		if ( ! empty( $registry['enabled'] ) && is_array( $registry['enabled'] ) ) {
			foreach ( $registry['enabled'] as $key ) {
				$key = sanitize_text_field( (string) $key );
				if ( $key ) {
					$enabled[] = $key;
				}
			}
		} elseif ( ! empty( $registry['enabled_google'] ) && is_array( $registry['enabled_google'] ) ) {
			foreach ( $registry['enabled_google'] as $key ) {
				$key = sanitize_text_field( (string) $key );
				if ( $key ) {
					$enabled[] = $key;
				}
			}
		}

		$google_fonts = array();
		if ( ! empty( $registry['google_fonts'] ) && is_array( $registry['google_fonts'] ) ) {
			$google_fonts = self::normalize_google_fonts( $registry['google_fonts'] );
		}

		$all_keys = array_keys( array_merge( self::builtin_catalog(), $google_fonts ) );
		$valid    = array();
		foreach ( $enabled as $key ) {
			if ( in_array( $key, $all_keys, true ) || 0 === strpos( $key, 'custom:' ) ) {
				$valid[] = $key;
			}
		}
		if ( empty( $valid ) ) {
			$valid = array( 'times_new_roman' );
		}

		$default = ! empty( $registry['default_font'] ) ? sanitize_key( (string) $registry['default_font'] ) : 'times_new_roman';
		if ( ! in_array( $default, $valid, true ) ) {
			$default = $valid[0];
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
				$label   = ! empty( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $id;
				$display = ! empty( $row['display_label'] ) ? sanitize_text_field( (string) $row['display_label'] ) : $label;
				$custom[ $id ] = array(
					'id'            => $id,
					'label'         => $label,
					'display_label' => $display,
					'attachment_id' => $attachment_id,
					'url'           => $url,
					'family'        => 'wc-gpd-custom-' . $id,
				);
			}
		}

		$display_labels = array();
		if ( ! empty( $registry['display_labels'] ) && is_array( $registry['display_labels'] ) ) {
			foreach ( $registry['display_labels'] as $key => $label ) {
				$key = sanitize_text_field( (string) $key );
				$label = sanitize_text_field( (string) $label );
				if ( $key && $label ) {
					$display_labels[ $key ] = $label;
				}
			}
		}

		update_option(
			self::OPTION_KEY,
			array(
				'enabled'        => array_values( array_unique( $valid ) ),
				'default_font'   => $default,
				'google_fonts'   => $google_fonts,
				'custom'         => $custom,
				'display_labels' => $display_labels,
			)
		);
	}

	/**
	 * Fetch Google Fonts metadata (cached).
	 *
	 * @return array<int,array{family:string,category:string,variants:array}>
	 */
	public static function fetch_google_metadata() {
		$cached = get_transient( self::GOOGLE_CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://fonts.google.com/metadata/fonts',
			array(
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$body = preg_replace( '/^\)\]\}\'\s*/', '', $body );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['familyMetadataList'] ) || ! is_array( $data['familyMetadataList'] ) ) {
			return array();
		}

		$list = array();
		foreach ( $data['familyMetadataList'] as $row ) {
			if ( empty( $row['family'] ) ) {
				continue;
			}
			$list[] = array(
				'family'   => sanitize_text_field( (string) $row['family'] ),
				'category' => ! empty( $row['category'] ) ? sanitize_text_field( (string) $row['category'] ) : '',
				'variants' => ! empty( $row['fonts'] ) && is_array( $row['fonts'] ) ? $row['fonts'] : array(),
			);
		}

		set_transient( self::GOOGLE_CACHE_KEY, $list, self::GOOGLE_CACHE_TTL );
		return $list;
	}

	/**
	 * @return string[]
	 */
	public static function get_google_categories() {
		$list = self::fetch_google_metadata();
		$cats = array();
		foreach ( $list as $row ) {
			if ( ! empty( $row['category'] ) ) {
				$cats[ $row['category'] ] = true;
			}
		}
		$keys = array_keys( $cats );
		sort( $keys );
		return $keys;
	}

	/**
	 * @param string $query    Search string.
	 * @param int    $limit    Max results.
	 * @param int    $offset   Result offset.
	 * @param string $category Category filter.
	 * @return array{fonts:array,total:int}
	 */
	public static function search_google_fonts( $query = '', $limit = 40, $offset = 0, $category = '' ) {
		$list     = self::fetch_google_metadata();
		$query    = strtolower( trim( (string) $query ) );
		$category = sanitize_text_field( (string) $category );
		$limit    = min( 1000, max( 5, absint( $limit ) ) );
		$offset   = max( 0, absint( $offset ) );
		$matched  = array();

		foreach ( $list as $row ) {
			if ( $category && ( empty( $row['category'] ) || $category !== $row['category'] ) ) {
				continue;
			}
			if ( $query && false === strpos( strtolower( $row['family'] ), $query ) ) {
				continue;
			}
			$matched[] = array(
				'family'   => $row['family'],
				'category' => $row['category'],
			);
		}

		return array(
			'fonts' => array_slice( $matched, $offset, $limit ),
			'total' => count( $matched ),
		);
	}

	/**
	 * Build a google_fonts row from a Google family name.
	 *
	 * @param string $family Family name.
	 * @return array
	 */
	public static function google_font_row_from_family( $family ) {
		$family = sanitize_text_field( (string) $family );
		$key    = self::font_key_from_family( $family );
		return array(
			'key'           => $key,
			'family'        => self::css_family_from_google_name( $family ),
			'label'         => $family,
			'display_label' => $family,
			'weights'       => '400,700',
			'google'        => self::google_api_name_from_family( $family ),
		);
	}

	/**
	 * Fonts enabled for a template (subset of site registry).
	 *
	 * @param int $template_id Template ID.
	 * @return array<int,array{key:string,family:string,label:string,admin_label:string,css:string,url?:string}>
	 */
	public static function fonts_for_template( $template_id = 0 ) {
		$registry = self::get_registry();
		$catalog  = self::all_fonts_catalog();
		$allowed  = get_post_meta( absint( $template_id ), '_wc_gpd_template_fonts', true );
		if ( ! is_array( $allowed ) || empty( $allowed ) ) {
			$allowed = $registry['enabled'];
		}

		$fonts = array();
		foreach ( $allowed as $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( ! $key ) {
				continue;
			}

			if ( 0 === strpos( $key, 'custom:' ) ) {
				$custom_id = sanitize_key( substr( $key, 7 ) );
				if ( ! empty( $registry['custom'][ $custom_id ] ) ) {
					$custom = $registry['custom'][ $custom_id ];
					$fonts[] = array(
						'key'         => $key,
						'family'      => (string) $custom['family'],
						'label'       => ! empty( $custom['display_label'] ) ? (string) $custom['display_label'] : (string) $custom['label'],
						'admin_label' => (string) $custom['label'],
						'css'         => (string) $custom['family'],
						'url'         => ! empty( $custom['url'] ) ? (string) $custom['url'] : '',
					);
				}
				continue;
			}

			if ( isset( $catalog[ $key ] ) ) {
				$row = $catalog[ $key ];
				$fonts[] = array(
					'key'         => $key,
					'family'      => $row['family'],
					'label'       => ! empty( $row['display_label'] ) ? (string) $row['display_label'] : (string) $row['label'],
					'admin_label' => (string) $row['label'],
					'css'         => $row['family'],
					'url'         => ! empty( $row['url'] ) ? (string) $row['url'] : '',
				);
			}
		}

		if ( empty( $fonts ) ) {
			$fonts[] = array(
				'key'         => 'times_new_roman',
				'family'      => '"Times New Roman", Times, serif',
				'label'       => 'Times New Roman',
				'admin_label' => 'Times New Roman',
				'css'         => '"Times New Roman", Times, serif',
			);
		}

		return $fonts;
	}

	/**
	 * @return string CSS font-family stack.
	 */
	public static function default_font_family() {
		$registry = self::get_registry();
		$catalog  = self::all_fonts_catalog();
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
		$catalog  = self::all_fonts_catalog();
		$families = array();
		$targets  = $template_id ? self::fonts_for_template( $template_id ) : array();
		$keys     = array();

		if ( $targets ) {
			foreach ( $targets as $font ) {
				$keys[] = $font['key'];
			}
		} else {
			$keys = $registry['enabled'];
		}

		foreach ( $keys as $key ) {
			if ( 0 === strpos( $key, 'custom:' ) ) {
				continue;
			}
			if ( empty( $catalog[ $key ]['google'] ) ) {
				continue;
			}
			$google = $catalog[ $key ]['google'];
			$weight = ! empty( $catalog[ $key ]['weights'] ) ? $catalog[ $key ]['weights'] : '400,700';
			$families[] = 'family=' . rawurlencode( str_replace( '+', ' ', (string) $google ) ) . ':wght@' . $weight;
		}

		if ( ! empty( $families ) ) {
			$url = 'https://fonts.googleapis.com/css2?' . implode( '&', array_unique( $families ) ) . '&display=swap';
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
			$family  = preg_replace( '/[^a-z0-9\-]/i', '', (string) $font['family'] );
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
