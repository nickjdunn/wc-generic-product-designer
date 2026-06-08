<?php
/**
 * Branded proof header templates.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Proof header rendering with token replacement.
 */
class WC_GPD_Proof_Header {

	/**
	 * Token labels for the visual designer palette.
	 *
	 * @return array<string,string>
	 */
	public static function token_labels() {
		return array(
			'site_name'               => __( 'Site name', 'wc-generic-product-designer' ),
			'site_url'                => __( 'Site URL', 'wc-generic-product-designer' ),
			'order_number'            => __( 'Order number', 'wc-generic-product-designer' ),
			'order_id'                => __( 'Order ID', 'wc-generic-product-designer' ),
			'customer_name'           => __( 'Customer name', 'wc-generic-product-designer' ),
			'order_date'              => __( 'Order date', 'wc-generic-product-designer' ),
			'product_name'            => __( 'Product name', 'wc-generic-product-designer' ),
			'personalization_summary' => __( 'Personalization summary', 'wc-generic-product-designer' ),
			'logo'                    => __( 'Logo image', 'wc-generic-product-designer' ),
		);
	}

	/**
	 * Default header design JSON for the visual designer.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_design() {
		return array(
			'width'      => 800,
			'height'     => 120,
			'background' => '#1e293b',
			'elements'   => array(
				array(
					'type'       => 'text',
					'token'      => 'site_name',
					'text'       => '{site_name}',
					'left'       => 20,
					'top'        => 28,
					'fontSize'   => 22,
					'fill'       => '#ffffff',
					'fontFamily' => 'Arial, sans-serif',
					'fontWeight' => '700',
				),
				array(
					'type'       => 'text',
					'token'      => 'site_url',
					'text'       => '{site_url}',
					'left'       => 20,
					'top'        => 54,
					'fontSize'   => 14,
					'fill'       => '#cbd5e1',
					'fontFamily' => 'Arial, sans-serif',
					'fontWeight' => '400',
				),
				array(
					'type'       => 'text',
					'token'      => 'order_number',
					'text'       => 'Order {order_number} · {customer_name} · {order_date}',
					'left'       => 20,
					'top'        => 80,
					'fontSize'   => 13,
					'fill'       => '#e2e8f0',
					'fontFamily' => 'Arial, sans-serif',
					'fontWeight' => '400',
				),
				array(
					'type'       => 'text',
					'token'      => 'product_name',
					'text'       => '{product_name}',
					'left'       => 20,
					'top'        => 100,
					'fontSize'   => 12,
					'fill'       => '#94a3b8',
					'fontFamily' => 'Arial, sans-serif',
					'fontWeight' => '400',
				),
				array(
					'type'   => 'logo',
					'left'   => 680,
					'top'    => 20,
					'width'  => 100,
					'height' => 80,
				),
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_design() {
		$raw = WC_GPD_Settings::get( 'proof_header_design', '' );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && ! empty( $decoded['elements'] ) ) {
				return $decoded;
			}
		}
		return self::default_design();
	}

	/**
	 * Default header template (SVG fragment with tokens).
	 *
	 * @return string
	 */
	public static function default_template() {
		return '<rect x="0" y="0" width="{width}" height="120" fill="#1e293b"/>'
			. '<text x="20" y="36" fill="#ffffff" font-family="Arial,sans-serif" font-size="22" font-weight="700">{site_name}</text>'
			. '<text x="20" y="62" fill="#cbd5e1" font-family="Arial,sans-serif" font-size="14">{site_url}</text>'
			. '<text x="20" y="88" fill="#e2e8f0" font-family="Arial,sans-serif" font-size="13">Order {order_number} · {customer_name} · {order_date}</text>'
			. '<text x="20" y="108" fill="#94a3b8" font-family="Arial,sans-serif" font-size="12">{product_name}</text>';
	}

	/**
	 * @return string
	 */
	public static function get_template() {
		$template = trim( (string) WC_GPD_Settings::get( 'proof_header_template', '' ) );
		return $template ? $template : self::default_template();
	}

	/**
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item  Line item.
	 * @param int                   $width Canvas width.
	 * @return string SVG fragment.
	 */
	public static function render_svg( $order, $item, $width = 800 ) {
		$design = self::get_design();
		if ( ! empty( $design['elements'] ) ) {
			return self::design_to_svg( $design, $order, $item, $width );
		}

		$tokens = self::tokens( $order, $item, $width );
		$svg    = self::get_template();
		foreach ( $tokens as $key => $value ) {
			$svg = str_replace( '{' . $key . '}', esc_html( $value ), $svg );
		}

		$logo_id = absint( WC_GPD_Settings::get( 'proof_header_logo_id', 0 ) );
		if ( $logo_id && false === strpos( $svg, '{logo}' ) ) {
			$href = wp_get_attachment_url( $logo_id );
			if ( $href ) {
				$svg = '<image x="' . ( $width - 140 ) . '" y="20" width="100" height="80" preserveAspectRatio="xMidYMid meet" href="' . esc_url( $href ) . '" />' . $svg;
			}
		} else {
			$logo_markup = '';
			if ( $logo_id ) {
				$href = wp_get_attachment_url( $logo_id );
				if ( $href ) {
					$logo_markup = '<image x="' . ( $width - 140 ) . '" y="20" width="100" height="80" preserveAspectRatio="xMidYMid meet" href="' . esc_url( $href ) . '" />';
				}
			}
			$svg = str_replace( '{logo}', $logo_markup, $svg );
		}

		return $svg;
	}

	/**
	 * Build SVG from visual designer JSON.
	 *
	 * @param array                 $design Design.
	 * @param WC_Order              $order  Order.
	 * @param WC_Order_Item_Product $item   Item.
	 * @param int                   $width  Width.
	 * @return string
	 */
	public static function design_to_svg( array $design, $order, $item, $width = 800, $logo_id = 0 ) {
		$tokens   = self::tokens( $order, $item, $width );
		$height   = absint( $design['height'] ?? 120 );
		$bg       = sanitize_hex_color( $design['background'] ?? '#1e293b' ) ?: '#1e293b';
		$logo_id  = $logo_id ? absint( $logo_id ) : absint( WC_GPD_Settings::get( 'proof_header_logo_id', 0 ) );
		$logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';

		$svg = '<rect x="0" y="0" width="' . absint( $width ) . '" height="' . $height . '" fill="' . esc_attr( $bg ) . '"/>';

		foreach ( $design['elements'] as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$type = $element['type'] ?? '';
			if ( 'logo' === $type ) {
				if ( ! $logo_url ) {
					continue;
				}
				$svg .= '<image x="' . (float) ( $element['left'] ?? 0 ) . '" y="' . (float) ( $element['top'] ?? 0 ) . '"'
					. ' width="' . (float) ( $element['width'] ?? 100 ) . '" height="' . (float) ( $element['height'] ?? 80 ) . '"'
					. ' preserveAspectRatio="xMidYMid meet" href="' . esc_url( $logo_url ) . '" />';
				continue;
			}
			if ( 'text' !== $type ) {
				continue;
			}
			$text = (string) ( $element['text'] ?? '' );
			foreach ( $tokens as $key => $value ) {
				$text = str_replace( '{' . $key . '}', $value, $text );
			}
			$font_size   = (float) ( $element['fontSize'] ?? 14 );
			$fill        = sanitize_hex_color( $element['fill'] ?? '#ffffff' ) ?: '#ffffff';
			$font_family = esc_attr( $element['fontFamily'] ?? 'Arial, sans-serif' );
			$font_weight = esc_attr( $element['fontWeight'] ?? '400' );
			$x           = (float) ( $element['left'] ?? 0 );
			$y           = (float) ( $element['top'] ?? 0 ) + $font_size;
			$svg        .= '<text x="' . $x . '" y="' . $y . '" fill="' . esc_attr( $fill ) . '"'
				. ' font-family="' . $font_family . '" font-size="' . $font_size . '" font-weight="' . $font_weight . '">'
				. esc_html( $text ) . '</text>';
		}

		return $svg;
	}

	/**
	 * Sample token values for designer preview.
	 *
	 * @return array<string,string>
	 */
	public static function sample_tokens() {
		return array(
			'site_name'               => get_bloginfo( 'name' ),
			'site_url'                => home_url(),
			'order_number'            => '1042',
			'order_id'                => '1042',
			'customer_name'           => __( 'Jane Smith', 'wc-generic-product-designer' ),
			'order_date'              => wp_date( get_option( 'date_format' ) ),
			'product_name'            => __( 'Custom Engraved Plaque', 'wc-generic-product-designer' ),
			'personalization_summary' => __( 'Name: Jane · Font: Script', 'wc-generic-product-designer' ),
		);
	}

	/**
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item  Item.
	 * @param int                   $width Width.
	 * @return array<string,string>
	 */
	public static function tokens( $order, $item, $width = 800 ) {
		$summary = self::personalization_summary( $item );

		return array(
			'width'                   => (string) absint( $width ),
			'site_name'               => get_bloginfo( 'name' ),
			'site_url'                => home_url(),
			'order_number'            => $order->get_order_number(),
			'order_id'                => (string) $order->get_id(),
			'customer_name'           => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'order_date'              => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
			'product_name'            => $item->get_name(),
			'personalization_summary' => $summary,
			'logo'                    => '',
		);
	}

	/**
	 * @param WC_Order_Item_Product $item Item.
	 * @return string
	 */
	public static function personalization_summary( $item ) {
		$parsed = $item->get_meta( WC_GPD_Production_Jobs::META_PARSED_FIELDS, true );
		if ( is_string( $parsed ) && '' !== $parsed ) {
			$data = json_decode( $parsed, true );
			if ( is_array( $data ) ) {
				$parts = array();
				foreach ( $data as $key => $value ) {
					$parts[] = $key . ': ' . $value;
				}
				return implode( ' · ', $parts );
			}
		}

		$json = $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_JSON, true );
		$doc  = WC_GPD_Design_Json::parse( is_string( $json ) ? $json : '' );
		$parts = array();
		if ( ! empty( $doc['views'] ) && is_array( $doc['views'] ) ) {
			foreach ( $doc['views'] as $view ) {
				if ( empty( $view['objects'] ) || ! is_array( $view['objects'] ) ) {
					continue;
				}
				foreach ( $view['objects'] as $obj ) {
					if ( ! is_array( $obj ) ) {
						continue;
					}
					$type = $obj['wcGpdLayerType'] ?? '';
					if ( 'placeholder' !== $type && 'text' !== $type ) {
						continue;
					}
					$label = ! empty( $obj['wcGpdPlaceholderLabel'] ) ? $obj['wcGpdPlaceholderLabel'] : ( $obj['wcGpdPlaceholderKey'] ?? 'Text' );
					$text  = ! empty( $obj['text'] ) ? $obj['text'] : '';
					if ( $text ) {
						$parts[] = $label . ': ' . $text;
					}
				}
			}
		}
		return implode( ' · ', $parts );
	}
}
