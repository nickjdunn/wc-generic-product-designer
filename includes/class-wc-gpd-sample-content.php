<?php
/**
 * Troubleshoot sample template + product for frontend diagnostics.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates a known test product/template pair for debugging customer permissions.
 */
class WC_GPD_Sample_Content {

	const OPTION_IDS           = 'wc_gpd_troubleshoot_content';
	const PENDING_OPTION       = 'wc_gpd_pending_troubleshoot_install';
	const META_FLAG            = '_wc_gpd_troubleshoot_sample';
	const PRODUCT_SLUG         = 'gpd-designer-troubleshoot-test';
	const TEMPLATE_TITLE       = 'GPD Troubleshoot Template';
	const PRODUCT_TITLE        = 'GPD Designer Troubleshoot Test';

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 20 );
	}

	/**
	 * Queue sample content creation on plugin activation.
	 */
	public static function schedule_install() {
		update_option( self::PENDING_OPTION, '1' );
	}

	/**
	 * Create sample content when pending or when posts are missing.
	 *
	 * @param bool $force Recreate even if content already exists.
	 */
	public static function maybe_install( $force = false ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$pending = get_option( self::PENDING_OPTION );
		$ids     = self::get_ids();

		if ( ! $force && ! $pending ) {
			if ( ! empty( $ids['product_id'] ) && get_post( $ids['product_id'] ) ) {
				return;
			}
		}

		self::install( $force );
		delete_option( self::PENDING_OPTION );
	}

	/**
	 * @param bool $force When true, refresh template JSON on existing posts.
	 * @return array{product_id:int,template_id:int,product_url:string,edit_url:string}
	 */
	public static function install( $force = false ) {
		WC_GPD_Design_Template::register_post_type();

		$ids         = self::get_ids();
		$template_id = self::upsert_template( $force ? 0 : absint( $ids['template_id'] ?? 0 ) );
		$product_id  = self::upsert_product( $force ? 0 : absint( $ids['product_id'] ?? 0 ), $template_id );

		$data = array(
			'template_id' => $template_id,
			'product_id'  => $product_id,
			'version'     => WC_GPD_VERSION,
			'created_at'  => gmdate( 'c' ),
		);
		update_option( self::OPTION_IDS, $data, false );

		WC_GPD_Logger::info(
			'Troubleshoot sample content ready',
			array(
				'product_id'  => $product_id,
				'template_id' => $template_id,
			)
		);

		return array(
			'product_id'  => $product_id,
			'template_id' => $template_id,
			'product_url' => get_permalink( $product_id ),
			'edit_url'    => get_edit_post_link( $product_id, 'raw' ),
		);
	}

	/**
	 * @return array{product_id:int,template_id:int,version:string,created_at:string}
	 */
	public static function get_ids() {
		$stored = get_option( self::OPTION_IDS, array() );
		return is_array( $stored ) ? $stored : array();
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
	 * @param int $template_id Existing template ID or 0.
	 * @return int
	 */
	private static function upsert_template( $template_id ) {
		if ( $template_id && get_post( $template_id ) ) {
			$post_id = $template_id;
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => self::TEMPLATE_TITLE,
					'post_status'=> 'publish',
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

		$document = self::build_template_document();
		$json       = wp_json_encode( $document );
		$sanitized  = WC_GPD_Template_Json::sanitize( $json );
		update_post_meta( $post_id, WC_GPD_Design_Template::META_TEMPLATE_JSON, $sanitized ? $sanitized : $json );

		$palettes = WC_GPD_Design_Template::default_palettes_data();
		$palettes['palettes'][] = array(
			'id'     => 'pal_test',
			'name'   => 'Test palette',
			'colors' => array( '#000000', '#2563eb', '#dc2626', '#16a34a' ),
		);
		update_post_meta( $post_id, WC_GPD_Design_Template::META_TEMPLATE_PALETTES, wp_json_encode( $palettes ) );

		WC_GPD_Product_Settings::save( $post_id, WC_GPD_Product_Settings::DEFAULTS );

		return (int) $post_id;
	}

	/**
	 * @param int $product_id  Existing product ID or 0.
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
			__( 'Auto-created by WC Generic Product Designer for troubleshooting the customer editor. Select each labeled layer and use “Copy diagnostics” in the designer footer.', 'wc-generic-product-designer' )
		);
		$product->set_short_description(
			__( 'Troubleshoot product — not for sale to customers. Use to verify layer permissions and copy diagnostic reports.', 'wc-generic-product-designer' )
		);

		$saved_id = $product->save();
		if ( ! $saved_id ) {
			return 0;
		}

		update_post_meta( $saved_id, self::META_FLAG, 'yes' );
		update_post_meta( $saved_id, WC_GPD_Product_Meta::META_ENABLED, 'yes' );
		update_post_meta( $saved_id, WC_GPD_Product_Meta::META_TEMPLATE_REF, absint( $template_id ) );
		update_post_meta( $saved_id, WC_GPD_Product_Meta::META_REPLACE_GALLERY, 'yes' );

		WC_GPD_Product_Settings::save( $saved_id, WC_GPD_Product_Settings::DEFAULTS );

		return (int) $saved_id;
	}

	/**
	 * Known template layers for permission testing.
	 *
	 * @return array
	 */
	private static function build_template_document() {
		$font = '"Times New Roman", Times, serif';

		$objects = array(
			self::text_layer(
				'gpd-test-text-all',
				'Test: all editable',
				'All controls editable',
				120,
				120,
				array(
					'wcGpdCustomerPaletteOnly' => false,
				)
			),
			self::text_layer(
				'gpd-test-text-color',
				'Test: color only',
				'Color only layer',
				120,
				220,
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
				)
			),
			self::text_layer(
				'gpd-test-text-locked',
				'Test: locked',
				'Fully locked text',
				120,
				320,
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
				)
			),
			array(
				'type'               => 'rect',
				'left'               => 480,
				'top'                => 140,
				'width'              => 140,
				'height'             => 100,
				'originX'            => 'left',
				'originY'            => 'top',
				'fill'               => '#2563eb',
				'stroke'             => '#1e3a8a',
				'strokeWidth'        => 2,
				'wcGpdUid'           => 'gpd-test-shape',
				'wcGpdTemplateLayer'   => true,
				'wcGpdLayerType'     => 'shape',
				'wcGpdLayerLabel'    => 'Test: shape color + move',
				'wcGpdCustomerEditable'=> true,
				'wcGpdShapeUseFill'  => true,
				'wcGpdShapeUseStroke'=> true,
				'wcGpdPaletteId'     => 'pal_test',
				'wcGpdStrokePaletteId' => 'pal_test',
				'wcGpdCustomerPaletteOnly' => true,
				'wcGpdLockColor'     => false,
				'wcGpdLockMove'      => false,
				'wcGpdLockScale'     => false,
				'selectable'         => false,
				'evented'            => false,
			),
		);

		$view = WC_GPD_Template_Json::empty_view( 'view_front', __( 'Front', 'wc-generic-product-designer' ) );
		$view['objects'] = $objects;

		return array(
			'version' => 2,
			'views'   => array( $view ),
		);
	}

	/**
	 * @param string $uid         Layer UID.
	 * @param string $label       Admin/customer label.
	 * @param string $text          Text content.
	 * @param int    $left          X position.
	 * @param int    $top           Y position.
	 * @param array  $extra_props Extra Fabric props.
	 * @return array
	 */
	private static function text_layer( $uid, $label, $text, $left, $top, array $extra_props = array() ) {
		$base = array(
			'type'                 => 'textbox',
			'left'                 => $left,
			'top'                  => $top,
			'width'                => 320,
			'originX'              => 'left',
			'originY'              => 'top',
			'text'                 => $text,
			'fontFamily'           => '"Times New Roman", Times, serif',
			'fontSize'             => 28,
			'fill'                 => '#000000',
			'textAlign'            => 'left',
			'lineHeight'           => 1.16,
			'charSpacing'          => 0,
			'wcGpdUid'             => $uid,
			'wcGpdTemplateLayer'   => true,
			'wcGpdLayerType'       => 'text',
			'wcGpdLayerLabel'      => $label,
			'wcGpdCustomerEditable'=> true,
			'wcGpdPaletteId'       => 'pal_test',
			'wcGpdCustomerPaletteOnly' => true,
			'wcGpdLockFont'        => false,
			'wcGpdLockSize'        => false,
			'wcGpdLockColor'       => false,
			'wcGpdLockBold'        => false,
			'wcGpdLockItalic'      => false,
			'wcGpdLockUnderline'   => false,
			'wcGpdLockAlign'       => false,
			'wcGpdLockLineHeight'  => false,
			'wcGpdLockLetterSpacing' => false,
			'wcGpdLockText'        => false,
			'wcGpdLockMove'        => false,
			'wcGpdLockScale'       => false,
			'selectable'           => false,
			'evented'              => false,
		);

		return array_merge( $base, $extra_props );
	}
}
