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
	const META_GRAPHIC_LIBRARY    = '_wc_gpd_graphic_library';
	const META_GRAPHIC_LIBRARIES = '_wc_gpd_graphic_libraries';
	const META_TEMPLATE_FONTS    = '_wc_gpd_template_fonts';
	const META_TEMPLATE_PALETTES = '_wc_gpd_template_palettes';

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
			'graphic_library'       => self::get_graphic_library( $template_id ),
			'photo_library'           => self::get_photo_library( $template_id ),
			'graphic_libraries'       => self::get_assigned_libraries( $template_id, WC_GPD_Graphic_Libraries::TYPE_GRAPHIC ),
			'library_assignments'     => self::get_library_assignments( $template_id ),
			'icon_slugs'              => self::get_icon_slugs_for_template( $template_id ),
			'template_fonts'     => self::get_template_fonts( $template_id ),
			'template_palettes'  => self::get_palettes( $template_id ),
			'product_settings'   => WC_GPD_Product_Settings::get( $template_id ),
		);
	}

	/**
	 * Default palette document.
	 *
	 * @return array
	 */
	public static function default_palettes_data() {
		return array(
			'palettes'            => array(
				array(
					'id'     => 'pal_default',
					'name'   => __( 'Default', 'wc-generic-product-designer' ),
					'colors' => array( '#000000' ),
				),
			),
			'use_global_colors'   => false,
			'global_colors'       => array( '#000000' ),
		);
	}

	/**
	 * @param int $template_id Template ID.
	 * @return array
	 */
	public static function get_palettes( $template_id ) {
		$raw = get_post_meta( absint( $template_id ), self::META_TEMPLATE_PALETTES, true );
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$data = json_decode( $raw, true );
			if ( is_array( $data ) ) {
				return self::sanitize_palettes_data( $data );
			}
		}
		if ( is_array( $raw ) ) {
			return self::sanitize_palettes_data( $raw );
		}
		return self::default_palettes_data();
	}

	/**
	 * @param array $data Palette payload.
	 * @return array
	 */
	public static function sanitize_palettes_data( array $data ) {
		$defaults = self::default_palettes_data();
		$clean    = array(
			'palettes'          => array(),
			'use_global_colors' => ! empty( $data['use_global_colors'] ),
			'global_colors'     => array(),
		);

		if ( ! empty( $data['palettes'] ) && is_array( $data['palettes'] ) ) {
			foreach ( $data['palettes'] as $palette ) {
				if ( ! is_array( $palette ) ) {
					continue;
				}
				$id = ! empty( $palette['id'] ) ? sanitize_key( (string) $palette['id'] ) : '';
				if ( ! $id ) {
					continue;
				}
				$name   = ! empty( $palette['name'] ) ? sanitize_text_field( (string) $palette['name'] ) : $id;
				$colors = array();
				if ( ! empty( $palette['colors'] ) && is_array( $palette['colors'] ) ) {
					foreach ( $palette['colors'] as $color ) {
						$hex = sanitize_hex_color( (string) $color );
						if ( $hex ) {
							$colors[] = $hex;
						}
					}
				}
				if ( empty( $colors ) ) {
					$colors[] = '#000000';
				}
				$clean['palettes'][] = array(
					'id'     => $id,
					'name'   => $name,
					'colors' => array_values( array_unique( $colors ) ),
				);
			}
		}

		if ( empty( $clean['palettes'] ) ) {
			$clean['palettes'] = $defaults['palettes'];
		}

		if ( ! empty( $data['global_colors'] ) && is_array( $data['global_colors'] ) ) {
			foreach ( $data['global_colors'] as $color ) {
				$hex = sanitize_hex_color( (string) $color );
				if ( $hex ) {
					$clean['global_colors'][] = $hex;
				}
			}
		}
		if ( empty( $clean['global_colors'] ) ) {
			$clean['global_colors'] = array( '#000000' );
		}

		return $clean;
	}

	/**
	 * @param string $json JSON palettes.
	 * @return array
	 */
	public static function sanitize_palettes_json( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return self::default_palettes_data();
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return self::default_palettes_data();
		}
		return self::sanitize_palettes_data( $data );
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
	/**
	 * @param int $template_id Template ID.
	 * @return array{ok:bool,json_saved:bool,message:string}
	 */
	public static function save_from_post( $template_id ) {
		$template_id = absint( $template_id );
		if ( ! $template_id || ! current_user_can( 'edit_post', $template_id ) ) {
			return array(
				'ok'         => false,
				'json_saved' => false,
				'message'    => __( 'Permission denied.', 'wc-generic-product-designer' ),
			);
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

		$raw_json    = isset( $_POST['wc_gpd_template_json'] ) ? wp_unslash( $_POST['wc_gpd_template_json'] ) : '';
		$json_saved  = false;
		$json        = WC_GPD_Template_Json::sanitize( is_string( $raw_json ) ? $raw_json : '' );
		if ( false !== $json ) {
			update_post_meta( $template_id, self::META_TEMPLATE_JSON, wp_slash( $json ) );
			$json_saved = true;
		} else {
			WC_GPD_Logger::error(
				'Template JSON sanitize failed',
				array(
					'template_id' => $template_id,
					'bytes'       => is_string( $raw_json ) ? strlen( $raw_json ) : 0,
				)
			);
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

		$assignments_raw = isset( $_POST['wc_gpd_library_assignments'] ) ? wp_unslash( $_POST['wc_gpd_library_assignments'] ) : '';
		$assignments     = self::sanitize_library_assignments( is_string( $assignments_raw ) ? $assignments_raw : '' );
		update_post_meta( $template_id, self::META_GRAPHIC_LIBRARIES, wp_slash( wp_json_encode( $assignments ) ) );

		$graphic_ids = WC_GPD_Graphic_Libraries::attachment_ids_for_libraries(
			$assignments['graphic'],
			WC_GPD_Graphic_Libraries::TYPE_GRAPHIC
		);
		update_post_meta( $template_id, self::META_GRAPHIC_LIBRARY, wp_json_encode( $graphic_ids ) );

		$fonts_raw = isset( $_POST['wc_gpd_template_fonts'] ) ? wp_unslash( $_POST['wc_gpd_template_fonts'] ) : '';
		$fonts     = self::sanitize_template_fonts( is_string( $fonts_raw ) ? $fonts_raw : '' );
		update_post_meta( $template_id, self::META_TEMPLATE_FONTS, $fonts );

		$palettes_raw = isset( $_POST['wc_gpd_template_palettes'] ) ? wp_unslash( $_POST['wc_gpd_template_palettes'] ) : '';
		$palettes     = self::sanitize_palettes_json( is_string( $palettes_raw ) ? $palettes_raw : '' );
		update_post_meta( $template_id, self::META_TEMPLATE_PALETTES, wp_slash( wp_json_encode( $palettes ) ) );

		WC_GPD_Product_Settings::save( $template_id, WC_GPD_Product_Settings::from_post( wp_unslash( $_POST ) ) );

		return array(
			'ok'         => $json_saved,
			'json_saved' => $json_saved,
			'message'    => $json_saved
				? __( 'Template saved.', 'wc-generic-product-designer' )
				: __( 'Template could not be saved. The design data was invalid or too large. Try saving again or simplify the template.', 'wc-generic-product-designer' ),
		);
	}

	/**
	 * @param int $template_id Template ID.
	 * @return string[]
	 */
	public static function get_template_fonts( $template_id ) {
		$stored = get_post_meta( absint( $template_id ), self::META_TEMPLATE_FONTS, true );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @param string $json JSON font keys.
	 * @return string[]
	 */
	public static function sanitize_template_fonts( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return array();
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$clean = array();
		foreach ( $data as $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( $key ) {
				$clean[] = $key;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * @return array{graphic:string[],photo:string[],icon:string[]}
	 */
	public static function default_library_assignments() {
		return array(
			'graphic' => array(),
			'photo'   => array(),
			'icon'    => array( WC_GPD_Graphic_Libraries::ALL_ICONS_ID ),
		);
	}

	/**
	 * @param int $template_id Template ID.
	 * @return array{graphic:string[],photo:string[],icon:string[]}
	 */
	public static function get_library_assignments( $template_id ) {
		$template_id = absint( $template_id );
		$defaults    = self::default_library_assignments();
		$raw         = get_post_meta( $template_id, self::META_GRAPHIC_LIBRARIES, true );

		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$data = json_decode( $raw, true );
			if ( is_array( $data ) && isset( $data['graphic'] ) ) {
				return self::sanitize_library_assignments_array( $data );
			}
			if ( is_array( $data ) && isset( $data[0]['id'] ) ) {
				$legacy_ids = array();
				foreach ( $data as $row ) {
					if ( is_array( $row ) && ! empty( $row['id'] ) ) {
						$legacy_ids[] = sanitize_key( (string) $row['id'] );
					}
				}
				$defaults['graphic'] = $legacy_ids;
				return $defaults;
			}
		}

		return $defaults;
	}

	/**
	 * @param string $json Assignments JSON.
	 * @return array{graphic:string[],photo:string[],icon:string[]}
	 */
	public static function sanitize_library_assignments( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return self::default_library_assignments();
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return self::default_library_assignments();
		}
		return self::sanitize_library_assignments_array( $data );
	}

	/**
	 * @param array $data Raw assignments.
	 * @return array{graphic:string[],photo:string[],icon:string[]}
	 */
	public static function sanitize_library_assignments_array( array $data ) {
		$clean = self::default_library_assignments();
		foreach ( array( 'graphic', 'photo', 'icon' ) as $type ) {
			if ( empty( $data[ $type ] ) || ! is_array( $data[ $type ] ) ) {
				continue;
			}
			$ids = array();
			foreach ( $data[ $type ] as $library_id ) {
				$library_id = sanitize_key( (string) $library_id );
				if ( $library_id && WC_GPD_Graphic_Libraries::get_by_id( $library_id ) ) {
					$ids[] = $library_id;
				}
			}
			$clean[ $type ] = array_values( array_unique( $ids ) );
		}
		return $clean;
	}

	/**
	 * Resolve assigned library IDs for a type (empty = all libraries of that type).
	 *
	 * @param int    $template_id Template ID.
	 * @param string $type        Library type.
	 * @return string[]
	 */
	public static function resolved_library_ids_for_type( $template_id, $type ) {
		$assignments = self::get_library_assignments( $template_id );
		$type        = WC_GPD_Graphic_Libraries::sanitize_type( $type );
		$assigned    = ! empty( $assignments[ $type ] ) ? $assignments[ $type ] : array();
		if ( ! empty( $assigned ) ) {
			return $assigned;
		}
		return array_map(
			static function ( $library ) {
				return $library['id'] ?? '';
			},
			WC_GPD_Graphic_Libraries::libraries_for_type( $type )
		);
	}

	/**
	 * @param int    $template_id Template ID.
	 * @param string $type        Library type.
	 * @return array<int,array{id:string,name:string,type:string,ids:int[],icon_slugs:string[],all_icons:bool}>
	 */
	public static function get_assigned_libraries( $template_id, $type ) {
		$type    = WC_GPD_Graphic_Libraries::sanitize_type( $type );
		$allowed = array_flip( self::resolved_library_ids_for_type( $template_id, $type ) );
		$clean   = array();
		foreach ( WC_GPD_Graphic_Libraries::get_all() as $library ) {
			if ( ( $library['type'] ?? WC_GPD_Graphic_Libraries::TYPE_GRAPHIC ) !== $type ) {
				continue;
			}
			if ( ! isset( $allowed[ $library['id'] ?? '' ] ) ) {
				continue;
			}
			$clean[] = $library;
		}
		return $clean;
	}

	/**
	 * @param int $template_id Template ID.
	 * @return string[]
	 */
	public static function get_icon_slugs_for_template( $template_id ) {
		$library_ids = self::resolved_library_ids_for_type( $template_id, WC_GPD_Graphic_Libraries::TYPE_ICON );
		return WC_GPD_Graphic_Libraries::icon_slugs_for_libraries( $library_ids );
	}

	/**
	 * @param array $libraries Raw libraries.
	 * @return array<int,array{id:string,name:string,ids:int[]}>
	 */
	public static function normalize_libraries_array( array $libraries ) {
		$clean = array();
		foreach ( $libraries as $library ) {
			if ( ! is_array( $library ) ) {
				continue;
			}
			$id = ! empty( $library['id'] ) ? sanitize_key( (string) $library['id'] ) : '';
			if ( ! $id ) {
				continue;
			}
			$name = ! empty( $library['name'] ) ? sanitize_text_field( (string) $library['name'] ) : $id;
			$ids  = array();
			if ( ! empty( $library['ids'] ) && is_array( $library['ids'] ) ) {
				foreach ( $library['ids'] as $attachment_id ) {
					$attachment_id = absint( $attachment_id );
					if ( $attachment_id && wp_get_attachment_url( $attachment_id ) ) {
						$ids[] = $attachment_id;
					}
				}
			}
			$clean[] = array(
				'id'   => $id,
				'name' => $name,
				'ids'  => array_values( array_unique( $ids ) ),
			);
		}
		if ( empty( $clean ) ) {
			$clean[] = array(
				'id'   => 'default',
				'name' => __( 'Default graphics', 'wc-generic-product-designer' ),
				'ids'  => array(),
			);
		}
		return $clean;
	}

	/**
	 * @param string $json Libraries JSON.
	 * @return array<int,array{id:string,name:string,ids:int[]}>
	 */
	public static function sanitize_graphic_libraries( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return self::normalize_libraries_array( array() );
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return self::normalize_libraries_array( array() );
		}
		return self::normalize_libraries_array( $data );
	}

	/**
	 * @param int $template_id Template ID.
	 * @return array<int,array{id:int,url:string,title:string}>
	 */
	public static function get_graphic_library( $template_id ) {
		$library_ids = self::resolved_library_ids_for_type( $template_id, WC_GPD_Graphic_Libraries::TYPE_GRAPHIC );
		return WC_GPD_Graphic_Libraries::media_items_for_libraries( $library_ids, WC_GPD_Graphic_Libraries::TYPE_GRAPHIC );
	}

	/**
	 * @param int $template_id Template ID.
	 * @return array<int,array{id:int,url:string,title:string}>
	 */
	public static function get_photo_library( $template_id ) {
		$library_ids = self::resolved_library_ids_for_type( $template_id, WC_GPD_Graphic_Libraries::TYPE_PHOTO );
		return WC_GPD_Graphic_Libraries::media_items_for_libraries( $library_ids, WC_GPD_Graphic_Libraries::TYPE_PHOTO );
	}

	/**
	 * @param int $template_id Template ID.
	 * @return array<int,array{id:string,name:string,ids:int[]}>
	 */
	public static function get_graphic_libraries( $template_id ) {
		return self::get_assigned_libraries( $template_id, WC_GPD_Graphic_Libraries::TYPE_GRAPHIC );
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
				self::update_template_json( $template_id, $sanitized );
			}
		}

		$product_settings = WC_GPD_Product_Settings::get( $product_id );
		WC_GPD_Product_Settings::save( $template_id, $product_settings );

		update_post_meta( $product_id, WC_GPD_Product_Meta::META_TEMPLATE_REF, $template_id );

		return $template_id;
	}

	/**
	 * @param int $template_id Template ID.
	 * @return int[]
	 */
	public static function get_product_ids_using( $template_id ) {
		$template_id = absint( $template_id );
		if ( ! $template_id ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => WC_GPD_Product_Meta::META_TEMPLATE_REF,
						'value' => $template_id,
					),
				),
			)
		);

		return array_map( 'absint', $query->posts );
	}

	/**
	 * @param int $template_id Template ID.
	 * @return array<int,array{id:int,title:string,edit_url:string}>
	 */
	public static function get_products_using( $template_id ) {
		$products = array();
		foreach ( self::get_product_ids_using( $template_id ) as $product_id ) {
			if ( ! $product_id ) {
				continue;
			}
			$products[] = array(
				'id'       => $product_id,
				'title'    => get_the_title( $product_id ) ?: sprintf(
					/* translators: %d: product ID */
					__( 'Product #%d', 'wc-generic-product-designer' ),
					$product_id
				),
				'edit_url' => (string) get_edit_post_link( $product_id, 'raw' ),
			);
		}

		return $products;
	}

	/**
	 * Count products using a template.
	 *
	 * @param int $template_id Template ID.
	 * @return int
	 */
	public static function count_products_using( $template_id ) {
		return count( self::get_product_ids_using( $template_id ) );
	}

	/**
	 * Permanently delete a template and clear product assignments.
	 *
	 * @param int $template_id Template ID.
	 * @return true|WP_Error
	 */
	public static function delete( $template_id ) {
		$template_id = absint( $template_id );
		if ( ! $template_id || ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'gpd_forbidden', __( 'Permission denied.', 'wc-generic-product-designer' ) );
		}

		$post = get_post( $template_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'gpd_not_found', __( 'Template not found.', 'wc-generic-product-designer' ) );
		}

		$product_ids = self::get_product_ids_using( $template_id );
		foreach ( $product_ids as $product_id ) {
			delete_post_meta( $product_id, WC_GPD_Product_Meta::META_TEMPLATE_REF );
		}

		$deleted = wp_delete_post( $template_id, true );
		if ( ! $deleted ) {
			return new WP_Error( 'gpd_delete_failed', __( 'Could not delete the template.', 'wc-generic-product-designer' ) );
		}

		WC_GPD_Logger::info(
			'Design template deleted',
			array(
				'template_id'        => $template_id,
				'products_unlinked'  => count( $product_ids ),
			)
		);

		return true;
	}

	/**
	 * Persist sanitized template JSON to post meta (WordPress-safe slashes).
	 *
	 * @param int    $template_id Template post ID.
	 * @param string $json        Sanitized JSON string.
	 * @return bool
	 */
	public static function update_template_json( $template_id, $json ) {
		$template_id = absint( $template_id );
		if ( ! $template_id || ! is_string( $json ) || '' === trim( $json ) ) {
			return false;
		}

		delete_post_meta( $template_id, self::META_TEMPLATE_JSON );
		update_post_meta( $template_id, self::META_TEMPLATE_JSON, wp_slash( $json ) );
		clean_post_cache( $template_id );

		return true;
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
