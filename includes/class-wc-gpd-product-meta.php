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

	const META_ENABLED       = '_wc_gpd_enabled';
	const META_CANVAS_WIDTH  = '_wc_gpd_canvas_width';
	const META_CANVAS_HEIGHT = '_wc_gpd_canvas_height';
	const META_TEMPLATE_ID   = '_wc_gpd_template_image_id';
	const META_TEMPLATE_JSON = '_wc_gpd_template_json';

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
		$width      = absint( get_post_meta( $product_id, self::META_CANVAS_WIDTH, true ) );
		$height     = absint( get_post_meta( $product_id, self::META_CANVAS_HEIGHT, true ) );
		$image_id   = absint( get_post_meta( $product_id, self::META_TEMPLATE_ID, true ) );

		if ( $width < self::MIN_DIMENSION || $width > self::MAX_DIMENSION ) {
			$width = self::DEFAULT_WIDTH;
		}
		if ( $height < self::MIN_DIMENSION || $height > self::MAX_DIMENSION ) {
			$height = self::DEFAULT_HEIGHT;
		}

		$template_url = '';
		if ( $image_id ) {
			$template_url = wp_get_attachment_image_url( $image_id, 'full' );
			if ( ! $template_url ) {
				$image_id = 0;
			}
		}

		$template_json = get_post_meta( $product_id, self::META_TEMPLATE_JSON, true );
		if ( ! is_string( $template_json ) ) {
			$template_json = '';
		}

		return array(
			'enabled'       => self::is_enabled( $product_id ),
			'width'         => $width,
			'height'        => $height,
			'template_id'   => $image_id,
			'template_url'  => $template_url ? $template_url : '',
			'template_json' => $template_json,
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
