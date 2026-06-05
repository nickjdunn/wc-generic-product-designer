<?php
/**
 * Sanitize customer-submitted SVG for storage and display.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * SVG sanitizer — strips scripts and dangerous attributes.
 */
class WC_GPD_SVG_Sanitizer {

	const MAX_SVG_BYTES = 524288; // 512 KB.

	/**
	 * Sanitize SVG string for safe storage.
	 *
	 * @param string $svg Raw SVG.
	 * @return string|false Sanitized SVG or false on failure.
	 */
	public static function sanitize( $svg ) {
		if ( ! is_string( $svg ) || '' === trim( $svg ) ) {
			WC_GPD_Logger::debug( 'SVG sanitize failed: empty input' );
			return false;
		}

		if ( strlen( $svg ) > self::MAX_SVG_BYTES ) {
			WC_GPD_Logger::warning( 'SVG sanitize failed: exceeds max size', array( 'bytes' => strlen( $svg ) ) );
			return false;
		}

		// Must look like SVG.
		if ( ! preg_match( '/<svg[\s>]/i', $svg ) ) {
			WC_GPD_Logger::debug( 'SVG sanitize failed: not valid SVG markup' );
			return false;
		}

		// Block obvious dangerous patterns before DOM parse.
		$blocked_patterns = array(
			'/<script\b/i',
			'/<foreignObject\b/i',
			'/\bon\w+\s*=/i',
			'/javascript:/i',
			'/data:text\/html/i',
		);
		foreach ( $blocked_patterns as $pattern ) {
			if ( preg_match( $pattern, $svg ) ) {
				return false;
			}
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return self::strip_tags_fallback( $svg );
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument( '1.0', 'UTF-8' );
		$loaded   = $dom->loadXML( $svg, LIBXML_NONET );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			WC_GPD_Logger::debug( 'SVG DOM parse failed, using tag-strip fallback' );
			return self::strip_tags_fallback( $svg );
		}

		$disallowed_tags = array( 'script', 'foreignObject', 'iframe', 'object', 'embed', 'link' );
		foreach ( $disallowed_tags as $tag ) {
			$nodes = $dom->getElementsByTagName( $tag );
			for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
				$node = $nodes->item( $i );
				if ( $node && $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}

		$xpath = new DOMXPath( $dom );
		foreach ( $xpath->query( '//*' ) as $element ) {
			if ( ! $element instanceof DOMElement ) {
				continue;
			}
			$remove = array();
			foreach ( $element->attributes as $attr ) {
				$name  = strtolower( $attr->name );
				$value = $attr->value;
				if ( 0 === strpos( $name, 'on' ) ) {
					$remove[] = $attr->name;
					continue;
				}
				if ( preg_match( '/^(xlink:)?href$/i', $attr->name ) && preg_match( '/^\s*javascript:/i', $value ) ) {
					$remove[] = $attr->name;
				}
			}
			foreach ( $remove as $attr_name ) {
				$element->removeAttribute( $attr_name );
			}
		}

		$root = $dom->documentElement;
		if ( ! $root || 'svg' !== strtolower( $root->nodeName ) ) {
			return false;
		}

		$output = $dom->saveXML( $root );
		return $output ? $output : false;
	}

	/**
	 * Fallback when DOMDocument is unavailable.
	 *
	 * @param string $svg Raw SVG.
	 * @return string|false
	 */
	private static function strip_tags_fallback( $svg ) {
		$allowed = '<svg><g><defs><clipPath><rect><circle><ellipse><line><polyline><polygon><path><text><tspan><image><use><style>';
		$clean   = strip_tags( $svg, $allowed );
		return $clean && preg_match( '/<svg[\s>]/i', $clean ) ? $clean : false;
	}

	/**
	 * Escape SVG for safe inline admin preview.
	 *
	 * @param string $svg Sanitized SVG.
	 * @return string
	 */
	public static function preview_markup( $svg ) {
		$sanitized = self::sanitize( $svg );
		if ( ! $sanitized ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized SVG for admin preview only.
		return $sanitized;
	}
}
