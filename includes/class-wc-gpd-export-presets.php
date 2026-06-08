<?php
/**
 * Saved production export presets for batch and order downloads.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Production export preset storage and CRUD.
 */
class WC_GPD_Export_Presets {

	const MIGRATION_FLAG = 'wc_gpd_export_presets_migrated_v151';

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function list() {
		self::maybe_migrate();
		$presets = WC_GPD_Settings::get( 'export_presets', array() );
		return is_array( $presets ) ? array_values( $presets ) : array();
	}

	/**
	 * @param string $id Preset ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		$id = sanitize_key( (string) $id );
		foreach ( self::list() as $preset ) {
			if ( ( $preset['id'] ?? '' ) === $id ) {
				return self::sanitize_preset( $preset );
			}
		}
		return null;
	}

	/**
	 * @return string
	 */
	public static function default_id() {
		self::maybe_migrate();
		$id = sanitize_key( (string) WC_GPD_Settings::get( 'default_export_preset_id', '' ) );
		if ( $id && self::get( $id ) ) {
			return $id;
		}
		$list = self::list();
		return ! empty( $list[0]['id'] ) ? (string) $list[0]['id'] : 'production-default';
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function default_production() {
		$preset = self::get( self::default_id() );
		return $preset ? $preset : self::sanitize_preset( self::factory_default() );
	}

	/**
	 * Export options array for WC_GPD_Export.
	 *
	 * @param array|string|null $preset Preset array or ID.
	 * @return array
	 */
	public static function export_options( $preset = null ) {
		if ( is_string( $preset ) ) {
			$preset = self::get( $preset );
		}
		if ( ! is_array( $preset ) ) {
			$preset = self::default_production();
		}
		$preset = self::sanitize_preset( $preset );

		return array(
			'include_background' => ! empty( $preset['include_background'] ),
			'include_text'       => ! empty( $preset['include_text'] ),
			'include_outlines'   => ! empty( $preset['include_outlines'] ),
			'include_shapes'     => ! empty( $preset['include_shapes'] ),
			'rasterize'          => ! empty( $preset['rasterize'] ),
			'outline_color'      => (string) ( $preset['outline_color'] ?? '#ff0000' ),
			'outline_width'      => (float) ( $preset['outline_width'] ?? 0.25 ),
			'preset'             => 'production',
			'preset_id'          => (string) ( $preset['id'] ?? '' ),
		);
	}

	/**
	 * Bed config from preset.
	 *
	 * @param array|string|null $preset Preset array or ID.
	 * @return array
	 */
	public static function bed_from_preset( $preset = null ) {
		if ( is_string( $preset ) ) {
			$preset = self::get( $preset );
		}
		if ( ! is_array( $preset ) ) {
			$preset = self::default_production();
		}
		$preset = self::sanitize_preset( $preset );

		return WC_GPD_Batch_Layout::bed_with_pixels(
			(float) ( $preset['bed_width'] ?? 24 ),
			(float) ( $preset['bed_height'] ?? 18 ),
			(string) ( $preset['bed_unit'] ?? 'in' ),
			absint( $preset['dpi'] ?? 96 )
		);
	}

	/**
	 * @param array $preset Preset data.
	 * @return array|WP_Error
	 */
	public static function save( array $preset ) {
		$clean = self::sanitize_preset( $preset );
		if ( empty( $clean['name'] ) ) {
			return new WP_Error( 'wc_gpd_preset_name', __( 'Preset name is required.', 'wc-generic-product-designer' ) );
		}

		$list   = self::list();
		$found  = false;
		$update = array();
		foreach ( $list as $row ) {
			if ( ( $row['id'] ?? '' ) === $clean['id'] ) {
				$update[] = $clean;
				$found    = true;
			} else {
				$update[] = $row;
			}
		}
		if ( ! $found ) {
			$update[] = $clean;
		}

		WC_GPD_Settings::update(
			array(
				'export_presets' => $update,
			)
		);

		if ( empty( WC_GPD_Settings::get( 'default_export_preset_id', '' ) ) ) {
			WC_GPD_Settings::update( array( 'default_export_preset_id' => $clean['id'] ) );
		}

		return $clean;
	}

	/**
	 * @param string $id Preset ID.
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
			return new WP_Error( 'wc_gpd_preset_missing', __( 'Preset not found.', 'wc-generic-product-designer' ) );
		}
		if ( empty( $next ) ) {
			$next[] = self::factory_default();
		}
		WC_GPD_Settings::update( array( 'export_presets' => $next ) );
		if ( self::default_id() === $id ) {
			WC_GPD_Settings::update( array( 'default_export_preset_id' => $next[0]['id'] ) );
		}
		return true;
	}

	/**
	 * @param string $id Preset ID.
	 * @return bool
	 */
	public static function set_default( $id ) {
		$id = sanitize_key( (string) $id );
		if ( ! self::get( $id ) ) {
			return false;
		}
		WC_GPD_Settings::update( array( 'default_export_preset_id' => $id ) );
		return true;
	}

	/**
	 * Migrate legacy global export settings into first preset.
	 */
	public static function maybe_migrate() {
		if ( get_option( self::MIGRATION_FLAG ) ) {
			return;
		}

		$existing = WC_GPD_Settings::get( 'export_presets', array() );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			update_option( self::MIGRATION_FLAG, 1, false );
			return;
		}

		$preset = self::factory_default_from_legacy();
		WC_GPD_Settings::update(
			array(
				'export_presets'            => array( $preset ),
				'default_export_preset_id'  => $preset['id'],
			)
		);
		update_option( self::MIGRATION_FLAG, 1, false );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function factory_default() {
		return self::sanitize_preset(
			array(
				'id'                 => 'production-default',
				'name'               => __( 'Engraving SVG', 'wc-generic-product-designer' ),
				'type'               => 'production',
				'include_background' => false,
				'include_text'       => true,
				'include_outlines'   => true,
				'include_shapes'     => true,
				'rasterize'          => false,
				'outline_color'      => '#ff0000',
				'outline_width'      => 0.25,
				'bed_width'          => 24,
				'bed_height'         => 18,
				'bed_unit'           => 'in',
				'dpi'                => 96,
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function factory_default_from_legacy() {
		$s = WC_GPD_Settings::all();
		return self::sanitize_preset(
			array(
				'id'                 => 'production-default',
				'name'               => __( 'Engraving SVG', 'wc-generic-product-designer' ),
				'type'               => 'production',
				'include_background' => ! empty( $s['export_include_background'] ),
				'include_text'       => ! empty( $s['export_include_text'] ),
				'include_outlines'   => ! empty( $s['export_include_outlines'] ),
				'include_shapes'     => ! empty( $s['export_include_shapes'] ),
				'rasterize'          => ! empty( $s['export_rasterize'] ),
				'outline_color'      => '#ff0000',
				'outline_width'      => 0.25,
				'bed_width'          => (float) ( $s['batch_bed_width'] ?? 24 ),
				'bed_height'         => (float) ( $s['batch_bed_height'] ?? 18 ),
				'bed_unit'           => (string) ( $s['batch_bed_unit'] ?? 'in' ),
				'dpi'                => absint( $s['batch_export_dpi'] ?? 96 ),
			)
		);
	}

	/**
	 * @param array $preset Raw preset.
	 * @return array<string,mixed>
	 */
	public static function sanitize_preset( array $preset ) {
		$id = ! empty( $preset['id'] ) ? sanitize_key( (string) $preset['id'] ) : 'preset-' . wp_generate_password( 8, false );
		$unit = in_array( $preset['bed_unit'] ?? 'in', array( 'in', 'mm' ), true ) ? $preset['bed_unit'] : 'in';
		$color = sanitize_hex_color( $preset['outline_color'] ?? '#ff0000' );

		return array(
			'id'                 => $id,
			'name'               => sanitize_text_field( $preset['name'] ?? __( 'Production preset', 'wc-generic-product-designer' ) ),
			'type'               => 'production',
			'include_background' => ! empty( $preset['include_background'] ),
			'include_text'       => ! isset( $preset['include_text'] ) || ! empty( $preset['include_text'] ),
			'include_outlines'   => ! isset( $preset['include_outlines'] ) || ! empty( $preset['include_outlines'] ),
			'include_shapes'     => ! isset( $preset['include_shapes'] ) || ! empty( $preset['include_shapes'] ),
			'rasterize'          => ! empty( $preset['rasterize'] ),
			'outline_color'      => $color ? $color : '#ff0000',
			'outline_width'      => max( 0.1, min( 20, (float) ( $preset['outline_width'] ?? 0.25 ) ) ),
			'bed_width'          => max( 1, (float) ( $preset['bed_width'] ?? 24 ) ),
			'bed_height'         => max( 1, (float) ( $preset['bed_height'] ?? 18 ) ),
			'bed_unit'           => $unit,
			'dpi'                => max( 72, min( 600, absint( $preset['dpi'] ?? 96 ) ) ),
		);
	}
}
