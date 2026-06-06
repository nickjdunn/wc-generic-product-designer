<?php
/**
 * Per-product designer settings (saved on product meta).
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product-level designer configuration.
 */
class WC_GPD_Product_Settings {

	const META_KEY = '_wc_gpd_product_settings';

	const DEFAULTS = array(
		'replace_product_gallery' => false,
		'canvas_bg_color'         => '#f0f0f0',
		'enable_popout'           => true,
		'allow_text_color'        => true,
		'single_color_only'       => false,
		'forced_text_color'       => '#000000',
		'allow_bold'              => true,
		'allow_italic'            => true,
		'allow_underline'         => true,
		'allow_line_height'       => true,
		'allow_letter_spacing'    => true,
		'allow_font_family'       => true,
		'allow_font_size'         => true,
		'allow_text_align'        => true,
		'allow_free_text'         => true,
		'allow_add_text'          => true,
		'allow_add_shape'         => false,
		'allow_add_graphic'       => false,
		'allow_add_image'         => false,
		'allow_add_icon'          => false,
		'allow_layers_panel'      => true,
		'allow_details_panel'     => true,
		'allow_customer_graphics' => true,
		'customer_panel_position' => 'auto',
		'use_same_colors_entire_template' => false,
		'outline_color'           => '#ff0000',
		'outline_stroke_width'    => 1,
		'bbox_stroke_color'       => '#ff0000',
		'bbox_stroke_width'       => 1,
		'export_outline_color'    => '#ff0000',
		'export_outline_width'    => 0.25,
		'export_hairline_outline' => true,
	);

	/**
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public static function get( $product_id ) {
		$stored = get_post_meta( absint( $product_id ), self::META_KEY, true );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged = wp_parse_args( $stored, self::DEFAULTS );
		$merged['canvas_bg_color']      = self::sanitize_color( $merged['canvas_bg_color'], '#f0f0f0' );
		$merged['forced_text_color']    = self::sanitize_color( $merged['forced_text_color'], '#000000' );
		$merged['outline_color']        = self::sanitize_color( $merged['outline_color'], '#ff0000' );
		$merged['bbox_stroke_color']    = self::sanitize_color( $merged['bbox_stroke_color'], '#ff0000' );
		$merged['export_outline_color'] = self::sanitize_color( $merged['export_outline_color'], '#ff0000' );
		$merged['outline_stroke_width'] = max( 0.1, min( 20, (float) $merged['outline_stroke_width'] ) );
		$merged['bbox_stroke_width']    = max( 0.1, min( 20, (float) $merged['bbox_stroke_width'] ) );
		$merged['export_outline_width'] = max( 0.1, min( 20, (float) $merged['export_outline_width'] ) );
		$merged['customer_panel_position'] = self::sanitize_panel_position( $merged['customer_panel_position'] ?? 'auto' );

		if ( ! array_key_exists( 'allow_add_text', $stored ) ) {
			$merged['allow_add_text'] = ! empty( $merged['allow_free_text'] );
		}
		$merged['allow_free_text'] = ! empty( $merged['allow_add_text'] );

		return $merged;
	}

	/**
	 * @param int   $product_id Product ID.
	 * @param array $settings   Settings.
	 */
	public static function save( $product_id, array $settings ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}

		$clean = array(
			'replace_product_gallery' => ! empty( $settings['replace_product_gallery'] ),
			'canvas_bg_color'         => self::sanitize_color( $settings['canvas_bg_color'] ?? '', '#f0f0f0' ),
			'enable_popout'           => ! empty( $settings['enable_popout'] ),
			'allow_text_color'        => ! empty( $settings['allow_text_color'] ),
			'single_color_only'       => ! empty( $settings['single_color_only'] ),
			'forced_text_color'       => self::sanitize_color( $settings['forced_text_color'] ?? '', '#000000' ),
			'allow_bold'              => ! empty( $settings['allow_bold'] ),
			'allow_italic'            => ! empty( $settings['allow_italic'] ),
			'allow_underline'         => ! empty( $settings['allow_underline'] ),
			'allow_line_height'       => ! empty( $settings['allow_line_height'] ),
			'allow_letter_spacing'    => ! empty( $settings['allow_letter_spacing'] ),
			'allow_font_family'       => ! empty( $settings['allow_font_family'] ),
			'allow_font_size'         => ! empty( $settings['allow_font_size'] ),
			'allow_text_align'        => ! empty( $settings['allow_text_align'] ),
			'allow_free_text'         => ! empty( $settings['allow_add_text'] ) || ! empty( $settings['allow_free_text'] ),
			'allow_add_text'          => ! empty( $settings['allow_add_text'] ) || ! empty( $settings['allow_free_text'] ),
			'allow_add_shape'         => ! empty( $settings['allow_add_shape'] ),
			'allow_add_graphic'       => ! empty( $settings['allow_add_graphic'] ),
			'allow_add_image'         => ! empty( $settings['allow_add_image'] ),
			'allow_add_icon'          => ! empty( $settings['allow_add_icon'] ),
			'allow_layers_panel'      => ! empty( $settings['allow_layers_panel'] ),
			'allow_details_panel'     => ! empty( $settings['allow_details_panel'] ),
			'allow_customer_graphics' => ! empty( $settings['allow_customer_graphics'] ),
			'customer_panel_position' => self::sanitize_panel_position( $settings['customer_panel_position'] ?? 'auto' ),
			'use_same_colors_entire_template' => ! empty( $settings['use_same_colors_entire_template'] ),
			'outline_color'           => self::sanitize_color( $settings['outline_color'] ?? '', '#ff0000' ),
			'outline_stroke_width'    => isset( $settings['outline_stroke_width'] ) ? (float) $settings['outline_stroke_width'] : 1,
			'bbox_stroke_color'       => self::sanitize_color( $settings['bbox_stroke_color'] ?? '', '#ff0000' ),
			'bbox_stroke_width'       => isset( $settings['bbox_stroke_width'] ) ? (float) $settings['bbox_stroke_width'] : 1,
			'export_outline_color'    => self::sanitize_color( $settings['export_outline_color'] ?? '', '#ff0000' ),
			'export_outline_width'    => isset( $settings['export_outline_width'] ) ? (float) $settings['export_outline_width'] : 0.25,
			'export_hairline_outline' => ! empty( $settings['export_hairline_outline'] ),
		);

		update_post_meta( $product_id, self::META_KEY, $clean );
	}

	/**
	 * Parse POST fields into settings array.
	 *
	 * @param array $post $_POST subset.
	 * @return array
	 */
	public static function from_post( array $post ) {
		return array(
			'replace_product_gallery' => ! empty( $post['wc_gpd_ps_replace_gallery'] ),
			'canvas_bg_color'         => $post['wc_gpd_ps_canvas_bg_color'] ?? '#f0f0f0',
			'enable_popout'           => ! empty( $post['wc_gpd_ps_enable_popout'] ),
			'allow_text_color'        => ! empty( $post['wc_gpd_ps_allow_text_color'] ),
			'single_color_only'       => ! empty( $post['wc_gpd_ps_single_color_only'] ),
			'forced_text_color'       => $post['wc_gpd_ps_forced_text_color'] ?? '#000000',
			'allow_bold'              => ! empty( $post['wc_gpd_ps_allow_bold'] ),
			'allow_italic'            => ! empty( $post['wc_gpd_ps_allow_italic'] ),
			'allow_underline'         => ! empty( $post['wc_gpd_ps_allow_underline'] ),
			'allow_line_height'       => ! empty( $post['wc_gpd_ps_allow_line_height'] ),
			'allow_letter_spacing'    => ! empty( $post['wc_gpd_ps_allow_letter_spacing'] ),
			'allow_font_family'       => ! empty( $post['wc_gpd_ps_allow_font_family'] ),
			'allow_font_size'         => ! empty( $post['wc_gpd_ps_allow_font_size'] ),
			'allow_text_align'        => ! empty( $post['wc_gpd_ps_allow_text_align'] ),
			'allow_add_text'          => ! empty( $post['wc_gpd_ps_allow_add_text'] ) || ! empty( $post['wc_gpd_ps_allow_free_text'] ),
			'allow_free_text'         => ! empty( $post['wc_gpd_ps_allow_add_text'] ) || ! empty( $post['wc_gpd_ps_allow_free_text'] ),
			'allow_add_shape'         => ! empty( $post['wc_gpd_ps_allow_add_shape'] ),
			'allow_add_graphic'       => ! empty( $post['wc_gpd_ps_allow_add_graphic'] ),
			'allow_add_image'         => ! empty( $post['wc_gpd_ps_allow_add_image'] ),
			'allow_add_icon'          => ! empty( $post['wc_gpd_ps_allow_add_icon'] ),
			'allow_layers_panel'      => ! empty( $post['wc_gpd_ps_allow_layers_panel'] ),
			'allow_details_panel'     => ! empty( $post['wc_gpd_ps_allow_details_panel'] ),
			'allow_customer_graphics' => ! empty( $post['wc_gpd_ps_allow_customer_graphics'] ),
			'customer_panel_position' => self::sanitize_panel_position( $post['wc_gpd_ps_customer_panel_position'] ?? 'auto' ),
			'use_same_colors_entire_template' => ! empty( $post['wc_gpd_ps_use_same_colors'] ),
			'outline_color'           => $post['wc_gpd_ps_outline_color'] ?? '#ff0000',
			'outline_stroke_width'    => $post['wc_gpd_ps_outline_stroke_width'] ?? 1,
			'bbox_stroke_color'       => $post['wc_gpd_ps_bbox_stroke_color'] ?? '#ff0000',
			'bbox_stroke_width'       => $post['wc_gpd_ps_bbox_stroke_width'] ?? 1,
			'export_outline_color'    => $post['wc_gpd_ps_export_outline_color'] ?? '#ff0000',
			'export_outline_width'    => $post['wc_gpd_ps_export_outline_width'] ?? 0.25,
			'export_hairline_outline' => ! empty( $post['wc_gpd_ps_export_hairline_outline'] ),
		);
	}

	/**
	 * @param mixed $value Panel position key.
	 * @return string
	 */
	public static function sanitize_panel_position( $value ) {
		$allowed = array( 'auto', 'left', 'right', 'top', 'bottom' );
		$value   = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : 'auto';
	}

	/**
	 * @param mixed  $value    Color value.
	 * @param string $fallback Fallback hex.
	 * @return string
	 */
	private static function sanitize_color( $value, $fallback ) {
		$color = sanitize_hex_color( (string) $value );
		return $color ? $color : $fallback;
	}
}
