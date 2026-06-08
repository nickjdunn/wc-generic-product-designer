<?php
/**
 * Parse external personalization text into design JSON.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Etsy-style personalization into template placeholders.
 */
class WC_GPD_Personalization_Parser {

	/**
	 * Parse freeform or line-based personalization text.
	 *
	 * @param string $text  Raw personalization.
	 * @param array  $rules Mapping rules.
	 * @return array<string,string>
	 */
	public static function parse_text( $text, array $rules = array() ) {
		$text   = trim( (string) $text );
		$fields = array();

		if ( ! empty( $rules['fields'] ) && is_array( $rules['fields'] ) ) {
			foreach ( $rules['fields'] as $field ) {
				if ( empty( $field['etsy_label'] ) || empty( $field['placeholder_key'] ) ) {
					continue;
				}
				$label = (string) $field['etsy_label'];
				$key   = sanitize_key( (string) $field['placeholder_key'] );
				$value = self::extract_labeled_value( $text, $label );
				if ( '' !== $value ) {
					$fields[ $key ] = $value;
				}
			}
		}

		if ( empty( $fields ) && $text ) {
			$lines = preg_split( '/\r\n|\r|\n/', $text );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( ! $line ) {
					continue;
				}
				if ( preg_match( '/^([^:]+):\s*(.+)$/', $line, $matches ) ) {
					$fields[ sanitize_key( strtolower( trim( $matches[1] ) ) ) ] = trim( $matches[2] );
				}
			}
			if ( empty( $fields ) ) {
				$fields['personalization'] = $text;
			}
		}

		return $fields;
	}

	/**
	 * @param string $text  Body text.
	 * @param string $label Label to find.
	 * @return string
	 */
	private static function extract_labeled_value( $text, $label ) {
		$pattern = '/(?:^|\n)\s*' . preg_quote( $label, '/' ) . '\s*[:=]\s*(.+?)(?=\n|$)/i';
		if ( preg_match( $pattern, $text, $matches ) ) {
			return trim( $matches[1] );
		}
		return '';
	}

	/**
	 * Build design JSON from parsed fields + product template.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $fields     Parsed field map.
	 * @param array $rules      Mapping rules with font_map.
	 * @return array{json:string,svg:string,fields:array}|WP_Error
	 */
	public static function build_design_for_product( $product_id, array $fields, array $rules = array() ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return new WP_Error( 'wc_gpd_no_product', __( 'Product is required.', 'wc-generic-product-designer' ) );
		}

		$settings = WC_GPD_Product_Meta::get_settings( $product_id );
		if ( empty( $settings['template_json'] ) ) {
			return new WP_Error( 'wc_gpd_no_template', __( 'Product has no design template.', 'wc-generic-product-designer' ) );
		}

		$template_doc = WC_GPD_Template_Json::parse( $settings['template_json'] );
		$views        = ! empty( $template_doc['views'] ) ? $template_doc['views'] : array();
		$design_views = array();

		foreach ( $views as $view ) {
			if ( empty( $view['id'] ) ) {
				continue;
			}
			$view_id = sanitize_key( (string) $view['id'] );
			$objects = array();

			if ( ! empty( $view['objects'] ) && is_array( $view['objects'] ) ) {
				foreach ( $view['objects'] as $obj ) {
					if ( ! is_array( $obj ) ) {
						continue;
					}
					$layer_type = $obj['wcGpdLayerType'] ?? '';
					if ( 'placeholder' !== $layer_type ) {
						continue;
					}

					$key = ! empty( $obj['wcGpdPlaceholderKey'] ) ? sanitize_key( (string) $obj['wcGpdPlaceholderKey'] ) : '';
					if ( ! $key || ! isset( $fields[ $key ] ) ) {
						continue;
					}

					$clone         = $obj;
					$clone['text']   = $fields[ $key ];
					$clone['type']   = $clone['type'] ?? 'textbox';
					$clone['wcGpdTextLayer'] = true;

					if ( ! empty( $rules['font_map'] ) && is_array( $rules['font_map'] ) && empty( $clone['wcGpdLockFont'] ) ) {
						$font_key = self::resolve_font_from_fields( $fields, $rules['font_map'] );
						if ( $font_key ) {
							$catalog = WC_GPD_Font_Registry::all_fonts_catalog();
							if ( isset( $catalog[ $font_key ] ) ) {
								$clone['fontFamily'] = $catalog[ $font_key ]['family'];
							}
						}
					}

					$objects[] = $clone;
				}
			}

			$design_views[ $view_id ] = array( 'objects' => $objects );
		}

		$design_doc = array(
			'version' => 2,
			'views'   => $design_views,
		);

		$json_string = wp_json_encode( $design_doc );
		$sanitized   = WC_GPD_Design_Json::sanitize( $json_string );
		if ( ! $sanitized ) {
			return new WP_Error( 'wc_gpd_design_invalid', __( 'Could not build design JSON from personalization.', 'wc-generic-product-designer' ) );
		}

		$product_settings = WC_GPD_Product_Settings::get( $product_id );
		$svg              = WC_GPD_Export::build_svg_document(
			$settings,
			'',
			$sanitized,
			$settings['template_json'],
			WC_GPD_Settings::export_defaults(),
			$product_settings
		);

		if ( ! $svg ) {
			return new WP_Error( 'wc_gpd_svg_failed', __( 'Could not generate SVG from personalization.', 'wc-generic-product-designer' ) );
		}

		return array(
			'json'   => $sanitized,
			'svg'    => $svg,
			'fields' => $fields,
		);
	}

	/**
	 * @param array $fields   Parsed fields.
	 * @param array $font_map Font label => registry key.
	 * @return string
	 */
	private static function resolve_font_from_fields( array $fields, array $font_map ) {
		$candidates = array( '_font', 'font', 'font_choice', 'font_style' );
		foreach ( $candidates as $key ) {
			if ( empty( $fields[ $key ] ) ) {
				continue;
			}
			$label = trim( (string) $fields[ $key ] );
			if ( isset( $font_map[ $label ] ) ) {
				return sanitize_text_field( (string) $font_map[ $label ] );
			}
			foreach ( $font_map as $map_label => $registry_key ) {
				if ( 0 === strcasecmp( $map_label, $label ) ) {
					return sanitize_text_field( (string) $registry_key );
				}
			}
		}
		return '';
	}
}
