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
