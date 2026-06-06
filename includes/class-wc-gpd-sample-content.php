<?php
/**
 * Demo product + design template with known test layers for troubleshooting.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and maintains the GPD demo product/template pair.
 */
class WC_GPD_Sample_Content {

	const OPTION_IDS         = 'wc_gpd_demo_content';
	const PENDING_OPTION     = 'wc_gpd_pending_demo_install';
	const VERSION_OPTION     = 'wc_gpd_demo_content_version';
	const META_FLAG          = '_wc_gpd_demo_sample';
	const SAMPLE_VERSION     = '7';
	const DEMO_MARKER_UID    = 'gpd-demo-text-all';
	const BUNDLED_JSON       = 'assets/demo/gpd-demo-template.json';
	const PRODUCT_SLUG       = 'gpd-demo-product';
	const TEMPLATE_TITLE     = 'GPD Demo Template';
	const PRODUCT_TITLE      = 'GPD Demo Product';

	/**
	 * Prevent recursive install within one request.
	 *
	 * @var bool
	 */
	private static $installing = false;

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 20 );

		$stored = get_option( self::VERSION_OPTION, '' );
		if ( $stored !== self::SAMPLE_VERSION ) {
			update_option( self::PENDING_OPTION, '1' );
			update_option( self::VERSION_OPTION, self::SAMPLE_VERSION );
		}
	}

	/**
	 * Queue demo content creation on plugin activation.
	 */
	public static function schedule_install() {
		update_option( self::PENDING_OPTION, '1' );
	}

	/**
	 * @param bool $force Recreate sample posts from scratch.
	 */
	public static function maybe_install( $force = false ) {
		if ( self::$installing ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$pending = get_option( self::PENDING_OPTION );
		if ( $force || $pending || self::needs_refresh() ) {
			self::$installing = true;
			self::install( $force );
			self::$installing = false;
			delete_option( self::PENDING_OPTION );
		}
	}

	/**
	 * @return bool
	 */
	private static function needs_refresh() {
		$ids = self::get_ids();

		if ( empty( $ids['product_id'] ) || ! get_post( $ids['product_id'] ) ) {
			return true;
		}

		$template_id = absint( $ids['template_id'] ?? 0 );
		if ( ! $template_id || ! get_post( $template_id ) ) {
			return true;
		}

		if ( 'yes' !== get_post_meta( $template_id, self::META_FLAG, true ) ) {
			return true;
		}

		if ( self::template_object_count( $template_id ) < 1 ) {
			return true;
		}

		if ( ! self::template_has_demo_markers( $template_id ) ) {
			return true;
		}

		$sample_version = isset( $ids['sample_version'] ) ? (string) $ids['sample_version'] : '';
		return self::SAMPLE_VERSION !== $sample_version;
	}

	/**
	 * @param int $template_id Template post ID.
	 * @return bool
	 */
	private static function template_has_demo_markers( $template_id ) {
		$json = get_post_meta( absint( $template_id ), WC_GPD_Design_Template::META_TEMPLATE_JSON, true );
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return false;
		}

		return false !== strpos( $json, self::DEMO_MARKER_UID );
	}

	/**
	 * @param int $template_id Template post ID.
	 * @return int
	 */
	private static function template_object_count( $template_id ) {
		$json = get_post_meta( absint( $template_id ), WC_GPD_Design_Template::META_TEMPLATE_JSON, true );
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return 0;
		}

		$parsed = WC_GPD_Template_Json::parse( $json );
		$count  = 0;
		foreach ( $parsed['views'] ?? array() as $view ) {
			if ( ! is_array( $view ) ) {
				continue;
			}
			$count += count( $view['objects'] ?? array() );
		}

		return $count;
	}

	/**
	 * @param bool $force Unused; kept for backward compatibility with debug refresh.
	 * @return array{product_id:int,template_id:int,product_url:string,edit_url:string}
	 */
	public static function install( $force = false ) {
		WC_GPD_Design_Template::register_post_type();
		WC_GPD_Graphic_Libraries::maybe_seed_demo_libraries();

		$ids         = self::get_ids();
		$template_id = absint( $ids['template_id'] ?? 0 );
		$product_id  = absint( $ids['product_id'] ?? 0 );

		if ( $template_id && ( ! get_post( $template_id ) || 'yes' !== get_post_meta( $template_id, self::META_FLAG, true ) ) ) {
			$template_id = 0;
		}
		if ( $product_id && ( ! get_post( $product_id ) || 'yes' !== get_post_meta( $product_id, self::META_FLAG, true ) ) ) {
			$product_id = 0;
		}

		if ( ! $template_id ) {
			$template_id = self::find_sample_template_id();
		}
		if ( ! $product_id ) {
			$product_id = self::find_sample_product_id();
		}

		$template_id = self::upsert_template( $template_id );
		$product_id  = self::upsert_product( $product_id, $template_id );

		update_option(
			self::OPTION_IDS,
			array(
				'template_id'    => $template_id,
				'product_id'     => $product_id,
				'plugin_version' => WC_GPD_VERSION,
				'sample_version' => self::SAMPLE_VERSION,
				'created_at'     => gmdate( 'c' ),
			),
			false
		);

		$object_count = self::template_object_count( $template_id );

		WC_GPD_Logger::info(
			'Demo product and template ready',
			array(
				'product_id'   => $product_id,
				'template_id'  => $template_id,
				'object_count' => $object_count,
			)
		);

		if ( $object_count < 4 ) {
			WC_GPD_Logger::error(
				'Demo template missing expected layers — check bundled demo JSON',
				array(
					'template_id'  => $template_id,
					'object_count' => $object_count,
				)
			);
		}

		return array(
			'product_id'  => $product_id,
			'template_id' => $template_id,
			'product_url' => get_permalink( $product_id ),
			'edit_url'    => get_edit_post_link( $product_id, 'raw' ),
		);
	}

	/**
	 * @return array{product_id:int,template_id:int,sample_version:string,created_at:string}
	 */
	public static function get_ids() {
		$stored = get_option( self::OPTION_IDS, array() );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return $stored;
		}

		// Back-compat with older troubleshoot option keys.
		$legacy = get_option( 'wc_gpd_troubleshoot_content', array() );
		return is_array( $legacy ) ? $legacy : array();
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_sample_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return false;
		}
		if ( 'yes' === get_post_meta( $product_id, self::META_FLAG, true ) ) {
			return true;
		}
		$ids = self::get_ids();
		return ! empty( $ids['product_id'] ) && (int) $ids['product_id'] === $product_id;
	}

	/**
	 * @return array{product_id:int,template_id:int,product_url:string,edit_url:string,template_edit_url:string}|null
	 */
	public static function get_links() {
		$ids = self::get_ids();
		if ( empty( $ids['product_id'] ) || ! get_post( $ids['product_id'] ) ) {
			return null;
		}

		$product_id  = absint( $ids['product_id'] );
		$template_id = absint( $ids['template_id'] ?? 0 );

		return array(
			'product_id'        => $product_id,
			'template_id'       => $template_id,
			'product_url'       => get_permalink( $product_id ),
			'edit_url'          => get_edit_post_link( $product_id, 'raw' ),
			'template_edit_url' => $template_id ? WC_GPD_Design_Template::edit_url( $template_id ) : '',
		);
	}

	/**
	 * @return int
	 */
	private static function find_sample_template_id() {
		$posts = get_posts(
			array(
				'post_type'      => WC_GPD_Design_Template::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => self::META_FLAG,
				'meta_value'     => 'yes',
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts[0] ) ? absint( $posts[0] ) : 0;
	}

	/**
	 * @return int
	 */
	private static function find_sample_product_id() {
		$existing = get_page_by_path( self::PRODUCT_SLUG, OBJECT, 'product' );
		if ( $existing && 'yes' === get_post_meta( $existing->ID, self::META_FLAG, true ) ) {
			return absint( $existing->ID );
		}

		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => self::META_FLAG,
				'meta_value'     => 'yes',
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts[0] ) ? absint( $posts[0] ) : 0;
	}

	/**
	 * @param int $template_id Existing sample template ID or 0.
	 * @return int
	 */
	private static function upsert_template( $template_id ) {
		if ( $template_id && get_post( $template_id ) ) {
			$post_id = $template_id;
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_title'  => self::TEMPLATE_TITLE,
					'post_status' => 'publish',
				)
			);
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'   => WC_GPD_Design_Template::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => self::TEMPLATE_TITLE,
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				return 0;
			}
		}

		update_post_meta( $post_id, self::META_FLAG, 'yes' );
		update_post_meta( $post_id, WC_GPD_Design_Template::META_CANVAS_WIDTH, 800 );
		update_post_meta( $post_id, WC_GPD_Design_Template::META_CANVAS_HEIGHT, 600 );
		update_post_meta( $post_id, WC_GPD_Design_Template::META_MAX_DESIGN_VIEWS, 1 );

		self::persist_template_json( $post_id );

		$palettes = WC_GPD_Design_Template::default_palettes_data();
		$palettes['palettes'][] = array(
			'id'     => 'pal_demo',
			'name'   => __( 'Demo palette', 'wc-generic-product-designer' ),
			'colors' => array( '#000000', '#2563eb', '#dc2626', '#16a34a' ),
		);
		update_post_meta( $post_id, WC_GPD_Design_Template::META_TEMPLATE_PALETTES, wp_json_encode( $palettes ) );
		WC_GPD_Product_Settings::save( $post_id, self::demo_product_settings() );
		self::persist_demo_library_assignments( $post_id );

		return (int) $post_id;
	}

	/**
	 * Assign demo graphic, photo, and icon libraries to the sample template.
	 *
	 * @param int $template_id Template post ID.
	 */
	private static function persist_demo_library_assignments( $template_id ) {
		$template_id = absint( $template_id );
		if ( ! $template_id ) {
			return;
		}

		WC_GPD_Graphic_Libraries::maybe_seed_demo_libraries();

		$assignments = array(
			'graphic' => array( WC_GPD_Graphic_Libraries::DEMO_GRAPHIC_ID ),
			'photo'   => array( WC_GPD_Graphic_Libraries::DEMO_PHOTO_ID ),
			'icon'    => array(
				WC_GPD_Graphic_Libraries::DEMO_ICON_ID,
				WC_GPD_Graphic_Libraries::ALL_ICONS_ID,
			),
		);

		update_post_meta( $template_id, WC_GPD_Design_Template::META_GRAPHIC_LIBRARIES, wp_slash( wp_json_encode( $assignments ) ) );

		$graphic_ids = WC_GPD_Graphic_Libraries::attachment_ids_for_libraries(
			$assignments['graphic'],
			WC_GPD_Graphic_Libraries::TYPE_GRAPHIC
		);
		update_post_meta( $template_id, WC_GPD_Design_Template::META_GRAPHIC_LIBRARY, wp_json_encode( $graphic_ids ) );
	}

	/**
	 * Demo template/product customer-tool defaults.
	 *
	 * @return array
	 */
	private static function demo_product_settings() {
		return array_merge(
			WC_GPD_Product_Settings::DEFAULTS,
			array(
				'allow_add_text'    => true,
				'allow_add_shape'   => true,
				'allow_add_graphic' => true,
				'allow_add_image'   => true,
				'allow_add_icon'    => true,
				'allow_free_text'   => true,
			)
		);
	}

	/**
	 * @param int $post_id Template post ID.
	 */
	private static function persist_template_json( $post_id ) {
		$json = self::get_demo_template_json();
		if ( '' === $json ) {
			WC_GPD_Logger::error(
				'Demo template JSON could not be built',
				array( 'template_id' => $post_id )
			);
			return;
		}

		WC_GPD_Design_Template::update_template_json( $post_id, $json );

		$stored_count = self::template_object_count( $post_id );
		if ( $stored_count < 4 ) {
			WC_GPD_Logger::error(
				'Demo template JSON did not persist correctly after save',
				array(
					'template_id'   => $post_id,
					'expected'      => 4,
					'stored_count'  => $stored_count,
					'payload_count' => self::json_object_count( $json ),
					'json_bytes'    => strlen( $json ),
				)
			);
		}
	}

	/**
	 * @return string Sanitized demo template JSON.
	 */
	private static function get_demo_template_json() {
		$bundled_path = WC_GPD_PLUGIN_DIR . self::BUNDLED_JSON;
		if ( is_readable( $bundled_path ) ) {
			$raw       = file_get_contents( $bundled_path );
			$sanitized = is_string( $raw ) ? WC_GPD_Template_Json::sanitize( $raw ) : false;
			if ( $sanitized && self::json_object_count( $sanitized ) >= 4 ) {
				return $sanitized;
			}
		}

		$encoded = wp_json_encode( self::build_template_document() );
		if ( ! $encoded ) {
			return '';
		}

		$sanitized = WC_GPD_Template_Json::sanitize( $encoded );
		if ( $sanitized && self::json_object_count( $sanitized ) >= 4 ) {
			return $sanitized;
		}

		$parsed = WC_GPD_Template_Json::parse( $encoded );
		return wp_json_encode( $parsed );
	}

	/**
	 * @param string $json Template JSON.
	 * @return int
	 */
	private static function json_object_count( $json ) {
		$parsed = WC_GPD_Template_Json::parse( $json );
		$count  = 0;
		foreach ( $parsed['views'] ?? array() as $view ) {
			if ( ! is_array( $view ) ) {
				continue;
			}
			$count += count( $view['objects'] ?? array() );
		}

		return $count;
	}

	/**
	 * @param int $product_id  Existing sample product ID or 0.
	 * @param int $template_id Template post ID.
	 * @return int
	 */
	private static function upsert_product( $product_id, $template_id ) {
		if ( $product_id && wc_get_product( $product_id ) ) {
			$product = wc_get_product( $product_id );
		} else {
			$existing = get_page_by_path( self::PRODUCT_SLUG, OBJECT, 'product' );
			if ( $existing ) {
				$product = wc_get_product( $existing->ID );
			} else {
				$product = new WC_Product_Simple();
			}
		}

		if ( ! $product ) {
			return 0;
		}

		$product->set_name( self::PRODUCT_TITLE );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_regular_price( '1.00' );
		$product->set_slug( self::PRODUCT_SLUG );
		$product->set_description(
			__( 'Demo product created by WC Generic Product Designer. Open the designer, select each labeled layer, and use Copy diagnostics in the footer when troubleshooting.', 'wc-generic-product-designer' )
		);
		$product->set_short_description(
			__( 'Demo product with sample text and shape layers for testing customer permissions.', 'wc-generic-product-designer' )
		);

		$saved_id = $product->save();
		if ( ! $saved_id ) {
			return 0;
		}

		update_post_meta( $saved_id, self::META_FLAG, 'yes' );
		update_post_meta( $saved_id, WC_GPD_Product_Meta::META_ENABLED, 'yes' );
		update_post_meta( $saved_id, WC_GPD_Product_Meta::META_TEMPLATE_REF, absint( $template_id ) );
		update_post_meta( $saved_id, WC_GPD_Product_Meta::META_REPLACE_GALLERY, 'yes' );
		WC_GPD_Product_Settings::save( $saved_id, self::demo_product_settings() );

		return (int) $saved_id;
	}

	/**
	 * @return array
	 */
	private static function build_template_document() {
		$objects = array(
			self::text_layer(
				'gpd-demo-text-all',
				'Demo: all editable',
				'All controls editable',
				80,
				80,
				array(
					'wcGpdCustomerPaletteOnly' => false,
					'wcGpdPaletteId'           => 'pal_demo',
				)
			),
			self::text_layer(
				'gpd-demo-text-color',
				'Demo: color only',
				'Color only layer',
				80,
				190,
				array(
					'wcGpdLockFont'            => true,
					'wcGpdLockSize'            => true,
					'wcGpdLockBold'            => true,
					'wcGpdLockItalic'          => true,
					'wcGpdLockUnderline'       => true,
					'wcGpdLockAlign'           => true,
					'wcGpdLockLineHeight'      => true,
					'wcGpdLockLetterSpacing'   => true,
					'wcGpdLockText'            => true,
					'wcGpdLockMove'            => true,
					'wcGpdLockScale'           => true,
					'wcGpdLockColor'           => false,
					'wcGpdCustomerPaletteOnly' => false,
					'wcGpdPaletteId'           => 'pal_demo',
				)
			),
			self::text_layer(
				'gpd-demo-text-locked',
				'Demo: locked',
				'Fully locked text',
				80,
				300,
				array(
					'wcGpdLockFont'          => true,
					'wcGpdLockSize'          => true,
					'wcGpdLockBold'          => true,
					'wcGpdLockItalic'        => true,
					'wcGpdLockUnderline'     => true,
					'wcGpdLockAlign'         => true,
					'wcGpdLockLineHeight'    => true,
					'wcGpdLockLetterSpacing' => true,
					'wcGpdLockText'          => true,
					'wcGpdLockMove'          => true,
					'wcGpdLockScale'         => true,
					'wcGpdLockColor'         => true,
					'wcGpdPaletteId'         => 'pal_demo',
				)
			),
			self::shape_layer(
				'gpd-demo-shape',
				'Demo: shape color + move',
				500,
				120,
				160,
				110
			),
		);

		$view            = WC_GPD_Template_Json::empty_view( 'view_front', __( 'Front', 'wc-generic-product-designer' ) );
		$view['objects'] = $objects;

		return array(
			'version' => 2,
			'views'   => array( $view ),
		);
	}

	/**
	 * @param string $uid         Layer UID.
	 * @param string $label       Layer label.
	 * @param string $text        Text content.
	 * @param int    $left        X position.
	 * @param int    $top         Y position.
	 * @param array  $extra_props Extra props.
	 * @return array
	 */
	private static function text_layer( $uid, $label, $text, $left, $top, array $extra_props = array() ) {
		$base = array(
			'type'                   => 'textbox',
			'version'                => '5.3.0',
			'left'                   => $left,
			'top'                    => $top,
			'width'                  => 340,
			'height'                 => 80,
			'scaleX'                 => 1,
			'scaleY'                 => 1,
			'angle'                  => 0,
			'originX'                => 'left',
			'originY'                => 'top',
			'text'                   => $text,
			'fontFamily'             => 'Times New Roman, Times, serif',
			'fontSize'               => 28,
			'fontWeight'             => 'normal',
			'fontStyle'              => 'normal',
			'fill'                   => '#111111',
			'textAlign'              => 'left',
			'lineHeight'             => 1.16,
			'charSpacing'            => 0,
			'wcGpdUid'               => $uid,
			'wcGpdTemplateLayer'     => true,
			'wcGpdLayerType'         => 'text',
			'wcGpdLayerLabel'        => $label,
			'wcGpdCustomerEditable'  => true,
			'wcGpdPaletteId'         => 'pal_demo',
			'wcGpdCustomerPaletteOnly' => true,
			'wcGpdLockFont'          => false,
			'wcGpdLockSize'          => false,
			'wcGpdLockColor'         => false,
			'wcGpdLockBold'          => false,
			'wcGpdLockItalic'        => false,
			'wcGpdLockUnderline'     => false,
			'wcGpdLockAlign'         => false,
			'wcGpdLockLineHeight'    => false,
			'wcGpdLockLetterSpacing' => false,
			'wcGpdLockText'          => false,
			'wcGpdLockMove'          => false,
			'wcGpdLockScale'         => false,
			'selectable'             => false,
			'evented'                => false,
		);

		return array_merge( $base, $extra_props );
	}

	/**
	 * @param string $uid    Layer UID.
	 * @param string $label  Layer label.
	 * @param int    $left   X position.
	 * @param int    $top    Y position.
	 * @param int    $width  Width.
	 * @param int    $height Height.
	 * @return array
	 */
	private static function shape_layer( $uid, $label, $left, $top, $width, $height ) {
		return array(
			'type'                     => 'rect',
			'version'                  => '5.3.0',
			'left'                     => $left,
			'top'                      => $top,
			'width'                    => $width,
			'height'                   => $height,
			'scaleX'                   => 1,
			'scaleY'                   => 1,
			'angle'                    => 0,
			'originX'                  => 'left',
			'originY'                  => 'top',
			'fill'                     => '#2563eb',
			'stroke'                   => '#1e3a8a',
			'strokeWidth'              => 2,
			'wcGpdUid'                 => $uid,
			'wcGpdTemplateLayer'       => true,
			'wcGpdLayerType'           => 'shape',
			'wcGpdLayerLabel'          => $label,
			'wcGpdCustomerEditable'    => true,
			'wcGpdShapeUseFill'        => true,
			'wcGpdShapeUseStroke'      => true,
			'wcGpdPaletteId'           => 'pal_demo',
			'wcGpdStrokePaletteId'     => 'pal_demo',
			'wcGpdCustomerPaletteOnly' => true,
			'wcGpdLockColor'           => false,
			'wcGpdLockMove'            => false,
			'wcGpdLockScale'           => false,
			'selectable'               => false,
			'evented'                  => false,
		);
	}
}
