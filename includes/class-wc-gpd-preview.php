<?php
/**
 * Design preview markup for cart and checkout.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preview HTML helpers.
 */
class WC_GPD_Preview {

	/**
	 * Build cart thumbnail as a theme-compatible <img> tag.
	 *
	 * @param string $svg      Sanitized design SVG (text layers).
	 * @param array  $settings Product designer settings.
	 * @param string $alt      Alt text.
	 * @param string $src_url  Optional direct image URL (attachment or file).
	 * @return string
	 */
	public static function cart_thumbnail_html( $svg, $settings, $alt = '', $src_url = '' ) {
		if ( $src_url ) {
			return sprintf(
				'<img src="%1$s" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail wc-gpd-cart-thumb-img" width="300" height="225" alt="%2$s" loading="lazy" decoding="async" />',
				esc_url( $src_url ),
				esc_attr( $alt )
			);
		}

		$data_uri = self::composite_data_uri( $svg, $settings );
		if ( ! $data_uri ) {
			return '';
		}

		return sprintf(
			'<img src="%1$s" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail wc-gpd-cart-thumb-img" width="300" height="225" alt="%2$s" loading="lazy" decoding="async" />',
			esc_attr( $data_uri ),
			esc_attr( $alt )
		);
	}

	/**
	 * Full composite SVG document for file storage or data URIs.
	 *
	 * @param string $svg      Sanitized design SVG.
	 * @param array  $settings Product designer settings.
	 * @return string
	 */
	public static function build_composite_svg_document( $svg, $settings ) {
		$svg = WC_GPD_SVG_Sanitizer::sanitize( $svg );
		if ( ! $svg ) {
			return '';
		}

		$width  = isset( $settings['width'] ) ? absint( $settings['width'] ) : 800;
		$height = isset( $settings['height'] ) ? absint( $settings['height'] ) : 600;
		$inner  = self::extract_svg_inner( $svg );

		$document  = '<?xml version="1.0" encoding="UTF-8"?>';
		$document .= '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"';
		$document .= ' viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '">';

		$template_data = self::template_data_uri( isset( $settings['template_id'] ) ? absint( $settings['template_id'] ) : 0 );
		if ( $template_data ) {
			$document .= '<image x="0" y="0" width="' . $width . '" height="' . $height . '" preserveAspectRatio="xMidYMid slice" href="' . $template_data . '" />';
		} else {
			$document .= '<rect x="0" y="0" width="100%" height="100%" fill="#f4f4f4" />';
		}

		$document .= $inner;
		$document .= '</svg>';

		return $document;
	}

	/**
	 * Composite template + design layers into one SVG data URI.
	 *
	 * @param string $svg      Sanitized design SVG.
	 * @param array  $settings Product designer settings.
	 * @return string
	 */
	public static function composite_data_uri( $svg, $settings ) {
		$document = self::build_composite_svg_document( $svg, $settings );
		if ( ! $document ) {
			return '';
		}

		return 'data:image/svg+xml;base64,' . base64_encode( $document );
	}

	/**
	 * Extract inner markup from an SVG string.
	 *
	 * @param string $svg SVG document.
	 * @return string
	 */
	private static function extract_svg_inner( $svg ) {
		if ( preg_match( '/<svg[^>]*>(.*)<\/svg>/is', $svg, $matches ) ) {
			return trim( $matches[1] );
		}
		return '';
	}

	/**
	 * Embed local template as base64 data URI for reliable cart previews.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function template_data_uri( $attachment_id ) {
		if ( ! $attachment_id ) {
			return '';
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return '';
		}

		$mime = wp_check_filetype( $path );
		$type = ! empty( $mime['type'] ) ? $mime['type'] : 'image/png';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return '';
		}

		return 'data:' . $type . ';base64,' . base64_encode( $contents );
	}

	/**
	 * Return sanitized inline SVG for embedding.
	 *
	 * @param string $svg Sanitized SVG string.
	 * @return string
	 */
	public static function inline_svg( $svg ) {
		$clean = WC_GPD_SVG_Sanitizer::sanitize( $svg );
		return $clean ? $clean : '';
	}
}
