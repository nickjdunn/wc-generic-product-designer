<?php
/**
 * Reusable design templates (custom post type).
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Design template storage and helpers.
 */
class WC_GPD_Design_Template {

	const POST_TYPE = 'wc_gpd_template';

	const META_CANVAS_WIDTH      = '_wc_gpd_canvas_width';
	const META_CANVAS_HEIGHT     = '_wc_gpd_canvas_height';
	const META_TEMPLATE_JSON     = '_wc_gpd_template_json';
	const META_MAX_DESIGN_VIEWS  = '_wc_gpd_max_design_views';
	const META_GRAPHIC_LIBRARY   = '_wc_gpd_graphic_library';

	/**
	 * Register post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Design templates', 'wc-generic-product-designer' ),
					'singular_name' => __( 'Design template', 'wc-generic-product-designer' ),
					'add_new_item'  => __( 'Add design template', 'wc-generic-product-designer' ),
					'edit_item'     => __( 'Edit design template', 'wc-generic-product-designer' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'delete_with_user'    => false,
			)
		);
	}

	/**
	 * @return array<int,array{id:int,title:string,width:int,height:int,views:int}>
	 */
	public static function list_templates() {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$list = array();
		foreach ( $query->posts as $post ) {
			$settings = self::get_settings( $post->ID );
			$list[]   = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'width'  => $settings['width'],
				'height' => $settings['height'],
				'views'  => $settings['max_views'],
			);
		}

		return $list;
	}

	/**
	 * @param int $template_id Template post ID.
	 * @return array|null
	 */
	public static function get_settings( $template_id ) {
		$template_id = absint( $template_id );
		$post        = $template_id ? get_post( $template_id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$width  = absint( get_post_meta( $template_id, self::META_CANVAS_WIDTH, true ) );
		$height = absint( get_post_meta( $template_id, self::META_CANVAS_HEIGHT, true ) );

		if ( $width < WC_GPD_Product_Meta::MIN_DIMENSION || $width > WC_GPD_Product_Meta::MAX_DIMENSION ) {
			$width = WC_GPD_Product_Meta::DEFAULT_WIDTH;
		}
		if ( $height < WC_GPD_Product_Meta::MIN_DIMENSION || $height > WC_GPD_Product_Meta::MAX_DIMENSION ) {
			$height = WC_GPD_Product_Meta::DEFAULT_HEIGHT;
		}

		$template_json = get_post_meta( $template_id, self::META_TEMPLATE_JSON, true );
		if ( ! is_string( $template_json ) ) {
			$template_json = '';
		}

		$max_views = absint( get_post_meta( $template_id, self::META_MAX_DESIGN_VIEWS, true ) );
		if ( $max_views < WC_GPD_Product_Meta::MIN_VIEWS || $max_views > WC_GPD_Product_Meta::MAX_VIEWS ) {
			$max_views = WC_GPD_Product_Meta::MIN_VIEWS;
		}

		return array(
			'id'               => $template_id,
			'title'            => $post->post_title,
			'width'            => $width,
			'height'           => $height,
			'template_json'    => $template_json,
			'template_views'   => WC_GPD_Template_Json::parse( $template_json ),
			'max_views'        => $max_views,
			'graphic_library'  => self::get_graphic_library( $template_id ),
			'product_settings' => WC_GPD_Product_Settings::get( $template_id ),
		);
	}

	/**
	 * Create a new template.
	 *
	 * @param string $title Template name.
	 * @return int|WP_Error Post ID.
	 */
	public static function create( $title ) {
		$title = sanitize_text_field( $title );
		if ( '' === $title ) {
			$title = __( 'New template', 'wc-generic-product-designer' );
		}

		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::META_CANVAS_WIDTH, WC_GPD_Product_Meta::DEFAULT_WIDTH );
		update_post_meta( $id, self::META_CANVAS_HEIGHT, WC_GPD_Product_Meta::DEFAULT_HEIGHT );
		update_post_meta( $id, self::META_MAX_DESIGN_VIEWS, WC_GPD_Product_Meta::MIN_VIEWS );
		update_post_meta( $id, self::META_TEMPLATE_JSON, wp_json_encode( WC_GPD_Template_Json::empty_document() ) );

		return $id;
	}

	/**
	 * Save template from admin form POST.
	 *
	 * @param int $template_id Template ID.
	 */
	public static function save_from_post( $template_id ) {
		$template_id = absint( $template_id );
		if ( ! $template_id || ! current_user_can( 'edit_post', $template_id ) ) {
			return;
		}

		if ( isset( $_POST['wc_gpd_template_title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $template_id,
					'post_title' => sanitize_text_field( wp_unslash( $_POST['wc_gpd_template_title'] ) ),
				)
			);
		}

		$width  = isset( $_POST['wc_gpd_canvas_width'] ) ? absint( $_POST['wc_gpd_canvas_width'] ) : WC_GPD_Product_Meta::DEFAULT_WIDTH;
		$height = isset( $_POST['wc_gpd_canvas_height'] ) ? absint( $_POST['wc_gpd_canvas_height'] ) : WC_GPD_Product_Meta::DEFAULT_HEIGHT;
		$width  = min( WC_GPD_Product_Meta::MAX_DIMENSION, max( WC_GPD_Product_Meta::MIN_DIMENSION, $width ) );
		$height = min( WC_GPD_Product_Meta::MAX_DIMENSION, max( WC_GPD_Product_Meta::MIN_DIMENSION, $height ) );

		update_post_meta( $template_id, self::META_CANVAS_WIDTH, $width );
		update_post_meta( $template_id, self::META_CANVAS_HEIGHT, $height );

		$raw_json = isset( $_POST['wc_gpd_template_json'] ) ? wp_unslash( $_POST['wc_gpd_template_json'] ) : '';
		$json     = WC_GPD_Template_Json::sanitize( is_string( $raw_json ) ? $raw_json : '' );
		if ( false !== $json ) {
			update_post_meta( $template_id, self::META_TEMPLATE_JSON, $json );
		}

		$max_views = WC_GPD_Product_Meta::MIN_VIEWS;
		if ( false !== $json ) {
			$parsed = json_decode( $json, true );
			if ( is_array( $parsed ) && ! empty( $parsed['views'] ) && is_array( $parsed['views'] ) ) {
				$max_views = count( $parsed['views'] );
			}
		}
		$max_views = min( WC_GPD_Product_Meta::MAX_VIEWS, max( WC_GPD_Product_Meta::MIN_VIEWS, $max_views ) );
		update_post_meta( $template_id, self::META_MAX_DESIGN_VIEWS, $max_views );

		$library_raw = isset( $_POST['wc_gpd_graphic_library'] ) ? wp_unslash( $_POST['wc_gpd_graphic_library'] ) : '';
		$library     = self::sanitize_graphic_library( is_string( $library_raw ) ? $library_raw : '' );
		update_post_meta( $template_id, self::META_GRAPHIC_LIBRARY, wp_json_encode( $library ) );

		WC_GPD_Product_Settings::save( $template_id, WC_GPD_Product_Settings::from_post( wp_unslash( $_POST ) ) );
	}

	/**
	 * @param int $template_id Template ID.
	 * @return array<int,array{id:int,url:string,title:string}>
	 */
	public static function get_graphic_library( $template_id ) {
		$template_id = absint( $template_id );
		$raw         = get_post_meta( $template_id, self::META_GRAPHIC_LIBRARY, true );
		$ids         = array();

		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$ids = $decoded;
			}
		} elseif ( is_array( $raw ) ) {
			$ids = $raw;
		}

		$library = array();
		foreach ( $ids as $id ) {
			$attachment_id = absint( $id );
			if ( ! $attachment_id ) {
				continue;
			}
			$url = wp_get_attachment_url( $attachment_id );
			if ( ! $url ) {
				continue;
			}
			$library[] = array(
				'id'    => $attachment_id,
				'url'   => $url,
				'title' => get_the_title( $attachment_id ),
			);
		}

		return $library;
	}

	/**
	 * @param string $json JSON array of attachment IDs.
	 * @return int[]
	 */
	public static function sanitize_graphic_library( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return array();
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		$clean = array();
		foreach ( $data as $id ) {
			$attachment_id = absint( $id );
			if ( $attachment_id && wp_get_attachment_url( $attachment_id ) ) {
				$clean[] = $attachment_id;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Migrate legacy per-product template data into a reusable template.
	 *
	 * @param int $product_id Product ID.
	 * @return int Template ID or 0.
	 */
	public static function migrate_from_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return 0;
		}

		$product = get_post( $product_id );
		if ( ! $product || 'product' !== $product->post_type ) {
			return 0;
		}

		$title = sprintf(
			/* translators: %s: product name */
			__( '%s template', 'wc-generic-product-designer' ),
			$product->post_title
		);

		$template_id = self::create( $title );
		if ( is_wp_error( $template_id ) ) {
			return 0;
		}

		$width  = absint( get_post_meta( $product_id, WC_GPD_Product_Meta::META_CANVAS_WIDTH, true ) );
		$height = absint( get_post_meta( $product_id, WC_GPD_Product_Meta::META_CANVAS_HEIGHT, true ) );
		$json   = get_post_meta( $product_id, WC_GPD_Product_Meta::META_TEMPLATE_JSON, true );
		$views  = absint( get_post_meta( $product_id, WC_GPD_Product_Meta::META_MAX_DESIGN_VIEWS, true ) );

		if ( $width ) {
			update_post_meta( $template_id, self::META_CANVAS_WIDTH, $width );
		}
		if ( $height ) {
			update_post_meta( $template_id, self::META_CANVAS_HEIGHT, $height );
		}
		if ( $views ) {
			update_post_meta( $template_id, self::META_MAX_DESIGN_VIEWS, $views );
		}
		if ( is_string( $json ) && '' !== trim( $json ) ) {
			$sanitized = WC_GPD_Template_Json::sanitize( $json );
			if ( false !== $sanitized ) {
				update_post_meta( $template_id, self::META_TEMPLATE_JSON, $sanitized );
			}
		}

		$product_settings = WC_GPD_Product_Settings::get( $product_id );
		WC_GPD_Product_Settings::save( $template_id, $product_settings );

		update_post_meta( $product_id, WC_GPD_Product_Meta::META_TEMPLATE_REF, $template_id );

		return $template_id;
	}

	/**
	 * Count products using a template.
	 *
	 * @param int $template_id Template ID.
	 * @return int
	 */
	public static function count_products_using( $template_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => WC_GPD_Product_Meta::META_TEMPLATE_REF,
						'value' => absint( $template_id ),
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Admin edit URL for a template.
	 *
	 * @param int $template_id Template ID.
	 * @return string
	 */
	public static function edit_url( $template_id ) {
		return add_query_arg(
			array(
				'page'        => WC_GPD_Admin_Templates::PAGE_SLUG,
				'action'      => 'edit',
				'template_id' => absint( $template_id ),
			),
			admin_url( 'admin.php' )
		);
	}
}
