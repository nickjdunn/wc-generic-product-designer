<?php
/**
 * Proof template presets (header, mockup, layer options, PDF settings).
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Proof template storage and composite rendering.
 */
class WC_GPD_Proof_Template {

	const MIGRATION_FLAG = 'wc_gpd_proof_templates_migrated_v151';

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function list() {
		self::maybe_migrate();
		$templates = WC_GPD_Settings::get( 'proof_templates', array() );
		return is_array( $templates ) ? array_values( $templates ) : array();
	}

	/**
	 * @param string $id Template ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		$id = sanitize_key( (string) $id );
		foreach ( self::list() as $template ) {
			if ( ( $template['id'] ?? '' ) === $id ) {
				return self::sanitize_template( $template );
			}
		}
		return null;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_default() {
		$template = self::get( self::default_id() );
		return $template ? $template : self::sanitize_template( self::factory_default() );
	}

	/**
	 * @return string
	 */
	public static function default_id() {
		self::maybe_migrate();
		$id = sanitize_key( (string) WC_GPD_Settings::get( 'default_proof_template_id', '' ) );
		if ( $id && self::get( $id ) ) {
			return $id;
		}
		$list = self::list();
		return ! empty( $list[0]['id'] ) ? (string) $list[0]['id'] : 'proof-standard';
	}

	/**
	 * Export options for proof body.
	 *
	 * @param array|string|null $template Template or ID.
	 * @return array
	 */
	public static function export_options( $template = null ) {
		if ( is_string( $template ) ) {
			$template = self::get( $template );
		}
		if ( ! is_array( $template ) ) {
			$template = self::get_default();
		}
		$template = self::sanitize_template( $template );
		$opts     = is_array( $template['export_options'] ?? null ) ? $template['export_options'] : array();

		return array(
			'include_background' => ! empty( $opts['include_background'] ),
			'include_text'       => ! isset( $opts['include_text'] ) || ! empty( $opts['include_text'] ),
			'include_outlines'   => ! empty( $opts['include_outlines'] ),
			'include_shapes'     => ! isset( $opts['include_shapes'] ) || ! empty( $opts['include_shapes'] ),
			'rasterize'          => false,
			'preset'             => 'proof',
			'template_id'        => (string) ( $template['id'] ?? '' ),
		);
	}

	/**
	 * @param WC_Order              $order Order.
	 * @param WC_Order_Item_Product $item  Item.
	 * @param array|string|null     $template Template or ID.
	 * @return string|WP_Error SVG document.
	 */
	public static function render_composite_svg( $order, $item, $template = null ) {
		if ( is_string( $template ) ) {
			$template = self::get( $template );
		}
		if ( ! is_array( $template ) ) {
			$template = self::get_default();
		}
		$template = self::sanitize_template( $template );

		$product_id = $item->get_product_id();
		$settings   = $product_id ? WC_GPD_Product_Meta::get_settings( $product_id ) : array();
		$width      = absint( $template['canvas_width'] ?? ( $settings['width'] ?? 800 ) );
		$design_h   = absint( $template['design_height'] ?? ( $settings['height'] ?? 600 ) );
		$header_h   = absint( $template['header_design']['height'] ?? 120 );

		$design = WC_GPD_Export::build_for_order_item( $item, self::export_options( $template ) );
		if ( is_wp_error( $design ) ) {
			return $design;
		}

		$header_svg   = WC_GPD_Proof_Header::design_to_svg( $template['header_design'], $order, $item, $width, absint( $template['logo_id'] ?? 0 ) );
		$design_inner = WC_GPD_Preview::extract_svg_inner_public( $design['content'] );
		if ( ! $design_inner ) {
			return new WP_Error( 'wc_gpd_proof_empty', __( 'Nothing to include in proof.', 'wc-generic-product-designer' ) );
		}

		$mockup_id  = absint( $template['mockup_attachment_id'] ?? 0 );
		$mockup_url = $mockup_id ? wp_get_attachment_url( $mockup_id ) : '';
		$total_h    = $header_h + $design_h;

		$parts   = array();
		$parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$parts[] = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ' . $width . ' ' . $total_h . '" width="' . $width . '" height="' . $total_h . '">';
		$parts[] = '<g transform="translate(0,0)">' . $header_svg . '</g>';
		$parts[] = '<g transform="translate(0,' . $header_h . ')">';
		if ( $mockup_url ) {
			$parts[] = '<image x="0" y="0" width="' . $width . '" height="' . $design_h . '" preserveAspectRatio="xMidYMid meet" href="' . esc_url( $mockup_url ) . '" data-wc-gpd-proof-mockup="1" />';
		}
		$parts[] = $design_inner;
		$parts[] = '</g>';
		$parts[] = '</svg>';

		return implode( "\n", $parts );
	}

	/**
	 * @param array $template Template data.
	 * @return array|WP_Error
	 */
	public static function save( array $template ) {
		$clean = self::sanitize_template( $template );
		if ( empty( $clean['name'] ) ) {
			return new WP_Error( 'wc_gpd_proof_name', __( 'Template name is required.', 'wc-generic-product-designer' ) );
		}

		$list  = self::list();
		$found = false;
		$next  = array();
		foreach ( $list as $row ) {
			if ( ( $row['id'] ?? '' ) === $clean['id'] ) {
				$next[] = $clean;
				$found  = true;
			} else {
				$next[] = $row;
			}
		}
		if ( ! $found ) {
			$next[] = $clean;
		}

		WC_GPD_Settings::update( array( 'proof_templates' => $next ) );
		if ( empty( WC_GPD_Settings::get( 'default_proof_template_id', '' ) ) ) {
			WC_GPD_Settings::update( array( 'default_proof_template_id' => $clean['id'] ) );
		}
		return $clean;
	}

	/**
	 * @param string $id Template ID.
	 * @return array|WP_Error
	 */
	public static function duplicate( $id ) {
		$source = self::get( $id );
		if ( ! $source ) {
			return new WP_Error( 'wc_gpd_proof_missing', __( 'Template not found.', 'wc-generic-product-designer' ) );
		}
		$source['id']   = 'proof-' . wp_generate_password( 8, false );
		$source['name'] = $source['name'] . ' ' . __( '(copy)', 'wc-generic-product-designer' );
		return self::save( $source );
	}

	/**
	 * @param string $id Template ID.
	 * @return bool|WP_Error
	 */
	public static function delete( $id ) {
		$id   = sanitize_key( (string) $id );
		$list = self::list();
		$next = array();
		$removed = false;
		foreach ( $list as $row ) {
			if ( ( $row['id'] ?? '' ) === $id ) {
				$removed = true;
				continue;
			}
			$next[] = $row;
		}
		if ( ! $removed ) {
			return new WP_Error( 'wc_gpd_proof_missing', __( 'Template not found.', 'wc-generic-product-designer' ) );
		}
		if ( empty( $next ) ) {
			$next[] = self::factory_default();
		}
		WC_GPD_Settings::update( array( 'proof_templates' => $next ) );
		if ( self::default_id() === $id ) {
			WC_GPD_Settings::update( array( 'default_proof_template_id' => $next[0]['id'] ) );
		}
		return true;
	}

	/**
	 * @param string $id Template ID.
	 * @return bool
	 */
	public static function set_default( $id ) {
		$id = sanitize_key( (string) $id );
		if ( ! self::get( $id ) ) {
			return false;
		}
		WC_GPD_Settings::update( array( 'default_proof_template_id' => $id ) );
		return true;
	}

	/**
	 * Migrate legacy proof header settings.
	 */
	public static function maybe_migrate() {
		if ( get_option( self::MIGRATION_FLAG ) ) {
			return;
		}

		$existing = WC_GPD_Settings::get( 'proof_templates', array() );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			update_option( self::MIGRATION_FLAG, 1, false );
			return;
		}

		$template = self::factory_default_from_legacy();
		WC_GPD_Settings::update(
			array(
				'proof_templates'            => array( $template ),
				'default_proof_template_id'  => $template['id'],
			)
		);
		update_option( self::MIGRATION_FLAG, 1, false );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function factory_default() {
		return self::sanitize_template(
			array(
				'id'                   => 'proof-standard',
				'name'                 => __( 'Standard proof', 'wc-generic-product-designer' ),
				'header_design'        => WC_GPD_Proof_Header::default_design(),
				'logo_id'              => 0,
				'mockup_attachment_id' => 0,
				'canvas_width'         => 800,
				'design_height'        => 600,
				'export_options'       => array(
					'include_background' => true,
					'include_text'       => true,
					'include_outlines'   => false,
					'include_shapes'     => true,
				),
				'pdf_dpi'              => 150,
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function factory_default_from_legacy() {
		$design_raw = WC_GPD_Settings::get( 'proof_header_design', '' );
		$design     = WC_GPD_Proof_Header::default_design();
		if ( is_string( $design_raw ) && '' !== $design_raw ) {
			$decoded = json_decode( $design_raw, true );
			if ( is_array( $decoded ) && ! empty( $decoded['elements'] ) ) {
				$design = $decoded;
			}
		}

		return self::sanitize_template(
			array(
				'id'                   => 'proof-standard',
				'name'                 => __( 'Standard proof', 'wc-generic-product-designer' ),
				'header_design'        => $design,
				'logo_id'              => absint( WC_GPD_Settings::get( 'proof_header_logo_id', 0 ) ),
				'mockup_attachment_id' => 0,
				'canvas_width'         => absint( $design['width'] ?? 800 ),
				'design_height'        => 600,
				'export_options'       => array(
					'include_background' => true,
					'include_text'       => true,
					'include_outlines'   => false,
					'include_shapes'     => true,
				),
				'pdf_dpi'              => 150,
			)
		);
	}

	/**
	 * @param array $template Raw template.
	 * @return array<string,mixed>
	 */
	public static function sanitize_template( array $template ) {
		$id = ! empty( $template['id'] ) ? sanitize_key( (string) $template['id'] ) : 'proof-' . wp_generate_password( 8, false );

		$header = $template['header_design'] ?? WC_GPD_Proof_Header::default_design();
		if ( is_string( $header ) ) {
			$header = json_decode( $header, true );
		}
		if ( ! is_array( $header ) || empty( $header['elements'] ) ) {
			$header = WC_GPD_Proof_Header::default_design();
		}

		$export_opts = is_array( $template['export_options'] ?? null ) ? $template['export_options'] : array();

		return array(
			'id'                   => $id,
			'name'                 => sanitize_text_field( $template['name'] ?? __( 'Proof template', 'wc-generic-product-designer' ) ),
			'header_design'        => $header,
			'logo_id'              => absint( $template['logo_id'] ?? 0 ),
			'mockup_attachment_id' => absint( $template['mockup_attachment_id'] ?? 0 ),
			'canvas_width'         => max( 200, absint( $template['canvas_width'] ?? 800 ) ),
			'design_height'        => max( 100, absint( $template['design_height'] ?? 600 ) ),
			'export_options'       => array(
				'include_background' => ! empty( $export_opts['include_background'] ),
				'include_text'       => ! isset( $export_opts['include_text'] ) || ! empty( $export_opts['include_text'] ),
				'include_outlines'   => ! empty( $export_opts['include_outlines'] ),
				'include_shapes'     => ! isset( $export_opts['include_shapes'] ) || ! empty( $export_opts['include_shapes'] ),
			),
			'pdf_dpi'              => max( 72, min( 600, absint( $template['pdf_dpi'] ?? 150 ) ) ),
		);
	}
}
