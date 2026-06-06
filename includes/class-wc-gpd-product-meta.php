<?php
/**
 * Product meta keys and helpers.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Central product meta definitions.
 */
class WC_GPD_Product_Meta {

	const META_ENABLED          = '_wc_gpd_enabled';
	const META_TEMPLATE_REF     = '_wc_gpd_template_ref';
	const META_REPLACE_GALLERY  = '_wc_gpd_replace_gallery';
	const META_CANVAS_WIDTH     = '_wc_gpd_canvas_width';
	const META_CANVAS_HEIGHT    = '_wc_gpd_canvas_height';
	const META_TEMPLATE_ID      = '_wc_gpd_template_image_id';
	const META_TEMPLATE_JSON    = '_wc_gpd_template_json';
	const META_MAX_DESIGN_VIEWS = '_wc_gpd_max_design_views';

	const MIN_VIEWS = 1;
	const MAX_VIEWS = 6;

	const CART_KEY_DESIGN_SVG  = 'wc_gpd_design_svg';
	const CART_KEY_DESIGN_JSON = 'wc_gpd_design_json';
	const CART_KEY_PREVIEW_URL = 'wc_gpd_preview_url';
	const CART_KEY_PREVIEW_ID  = 'wc_gpd_preview_id';

	const ORDER_META_DESIGN_SVG   = '_wc_gpd_design_svg';
	const ORDER_META_DESIGN_JSON  = '_wc_gpd_design_json';
	const ORDER_META_PREVIEW_URL  = '_wc_gpd_preview_url';

	const DEFAULT_WIDTH  = 800;
	const DEFAULT_HEIGHT = 600;

	const MIN_DIMENSION = 100;
	const MAX_DIMENSION = 4000;

	/**
	 * Check if designer is enabled for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_enabled( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return false;
		}
		return 'yes' === get_post_meta( $product_id, self::META_ENABLED, true );
	}

	/**
	 * Get designer settings for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array{width:int,height:int,template_url:string,template_id:int,template_json:string}
	 */
	public static function get_settings( $product_id ) {
		$product_id = absint( $product_id );
		$template_ref = absint( get_post_meta( $product_id, self::META_TEMPLATE_REF, true ) );
		$replace_gallery = 'yes' === get_post_meta( $product_id, self::META_REPLACE_GALLERY, true );

		if ( $template_ref ) {
			$template = WC_GPD_Design_Template::get_settings( $template_ref );
			if ( $template ) {
				// Storefront uses the WooCommerce product's designer settings; template supplies canvas/export defaults.
				$product_settings = array_merge(
					$template['product_settings'],
					WC_GPD_Product_Settings::get( $product_id )
				);
				$product_settings['replace_product_gallery'] = false;

				return array(
					'enabled'           => self::is_enabled( $product_id ),
					'template_ref'      => $template_ref,
					'width'             => $template['width'],
					'height'            => $template['height'],
					'template_id'       => 0,
					'template_url'      => '',
					'template_json'     => $template['template_json'],
					'template_views'    => $template['template_views'],
					'max_views'         => $template['max_views'],
					'graphic_library'   => $template['graphic_library'],
					'graphic_libraries' => $template['graphic_libraries'],
					'template_palettes' => $template['template_palettes'],
					'product_settings'  => $product_settings,
				);
			}
		}

		return self::get_legacy_settings( $product_id, $replace_gallery );
	}

	/**
	 * Legacy per-product template storage (pre-1.11).
	 *
	 * @param int  $product_id      Product ID.
	 * @param bool $replace_gallery Replace gallery flag.
	 * @return array
	 */
	private static function get_legacy_settings( $product_id, $replace_gallery ) {
		$width    = absint( get_post_meta( $product_id, self::META_CANVAS_WIDTH, true ) );
		$height   = absint( get_post_meta( $product_id, self::META_CANVAS_HEIGHT, true ) );
		$image_id = absint( get_post_meta( $product_id, self::META_TEMPLATE_ID, true ) );

		if ( $width < self::MIN_DIMENSION || $width > self::MAX_DIMENSION ) {
			$width = self::DEFAULT_WIDTH;
		}
		if ( $height < self::MIN_DIMENSION || $height > self::MAX_DIMENSION ) {
			$height = self::DEFAULT_HEIGHT;
		}

		$template_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
		$template_json = get_post_meta( $product_id, self::META_TEMPLATE_JSON, true );
		if ( ! is_string( $template_json ) ) {
			$template_json = '';
		}

		$max_views = absint( get_post_meta( $product_id, self::META_MAX_DESIGN_VIEWS, true ) );
		if ( $max_views < self::MIN_VIEWS || $max_views > self::MAX_VIEWS ) {
			$max_views = self::MIN_VIEWS;
		}

		$product_settings = WC_GPD_Product_Settings::get( $product_id );
		$product_settings['replace_product_gallery'] = $replace_gallery;

		return array(
			'enabled'          => self::is_enabled( $product_id ),
			'template_ref'     => 0,
			'width'            => $width,
			'height'           => $height,
			'template_id'      => $image_id,
			'template_url'     => $template_url ? $template_url : '',
			'template_json'    => $template_json,
			'template_views'   => WC_GPD_Template_Json::parse( $template_json ),
			'max_views'        => $max_views,
			'product_settings' => $product_settings,
		);
	}

	/**
	 * Product IDs with designer enabled.
	 *
	 * @param int $limit Max results.
	 * @return int[]
	 */
	public static function get_enabled_product_ids( $limit = 20 ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => absint( $limit ),
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_ENABLED,
						'value' => 'yes',
					),
				),
			)
		);

		return array_map( 'absint', $query->posts );
	}
}
