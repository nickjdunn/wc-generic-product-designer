<?php
/**
 * Batch layout storage for production nesting.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Batch custom post type and layout helpers.
 */
class WC_GPD_Batch_Layout {

	const POST_TYPE = 'wc_gpd_batch';

	const META_LAYOUT   = '_wc_gpd_batch_layout_json';
	const META_BED      = '_wc_gpd_batch_bed';
	const META_ITEMS    = '_wc_gpd_batch_item_refs';
	const META_EXPORT_OPTS = '_wc_gpd_batch_export_options';

	/**
	 * Register post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Production batches', 'wc-generic-product-designer' ),
					'singular_name' => __( 'Production batch', 'wc-generic-product-designer' ),
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
	 * Default machine bed from settings.
	 *
	 * @return array{width:float,height:float,unit:string,dpi:int,width_px:int,height_px:int}
	 */
	public static function default_bed() {
		return WC_GPD_Export_Presets::bed_from_preset();
	}

	/**
	 * @param float  $width  Bed width.
	 * @param float  $height Bed height.
	 * @param string $unit   Unit (in, mm).
	 * @param int    $dpi    DPI.
	 * @return array
	 */
	public static function bed_with_pixels( $width, $height, $unit, $dpi ) {
		$width  = max( 1, (float) $width );
		$height = max( 1, (float) $height );
		$unit   = in_array( $unit, array( 'in', 'mm' ), true ) ? $unit : 'in';

		if ( 'mm' === $unit ) {
			$width_px  = (int) round( ( $width / 25.4 ) * $dpi );
			$height_px = (int) round( ( $height / 25.4 ) * $dpi );
		} else {
			$width_px  = (int) round( $width * $dpi );
			$height_px = (int) round( $height * $dpi );
		}

		return array(
			'width'      => $width,
			'height'     => $height,
			'unit'       => $unit,
			'dpi'        => $dpi,
			'width_px'   => max( 100, $width_px ),
			'height_px'  => max( 100, $height_px ),
		);
	}

	/**
	 * @param array $item_refs Job refs.
	 * @param array $bed       Bed config.
	 * @return int|WP_Error
	 */
	public static function create( array $item_refs, array $bed = array(), $preset_id = '' ) {
		if ( empty( $item_refs ) ) {
			return new WP_Error( 'wc_gpd_batch_empty', __( 'Select at least one job for the batch.', 'wc-generic-product-designer' ) );
		}

		$preset = $preset_id ? WC_GPD_Export_Presets::get( $preset_id ) : WC_GPD_Export_Presets::default_production();
		$bed    = ! empty( $bed ) ? $bed : WC_GPD_Export_Presets::bed_from_preset( $preset );
		$export = WC_GPD_Export_Presets::export_options( $preset );
		$title = sprintf(
			/* translators: %s: date */
			__( 'Batch %s', 'wc-generic-product-designer' ),
			wp_date( 'Y-m-d H:i' )
		);

		$batch_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $batch_id ) ) {
			return $batch_id;
		}

		$layout = array();
		$x      = 20;
		$y      = 20;
		$gap    = 20;

		foreach ( $item_refs as $ref ) {
			$order_id = absint( $ref['order_id'] ?? 0 );
			$item_id  = absint( $ref['item_id'] ?? 0 );
			if ( ! $order_id || ! $item_id ) {
				continue;
			}
			$layout[] = array(
				'order_id'  => $order_id,
				'item_id'   => $item_id,
				'x'         => $x,
				'y'         => $y,
				'scale'     => 1,
				'rotation'  => 0,
				'width'     => 0,
				'height'    => 0,
			);
			$x += 200 + $gap;
		}

		update_post_meta( $batch_id, self::META_LAYOUT, wp_json_encode( $layout ) );
		update_post_meta( $batch_id, self::META_BED, wp_json_encode( $bed ) );
		update_post_meta( $batch_id, self::META_ITEMS, wp_json_encode( $item_refs ) );
		update_post_meta( $batch_id, self::META_EXPORT_OPTS, wp_json_encode( $export ) );

		foreach ( $item_refs as $ref ) {
			$item = WC_GPD_Production_Jobs::get_item( $ref['order_id'], $ref['item_id'] );
			if ( $item ) {
				$order = wc_get_order( absint( $ref['order_id'] ) );
				WC_GPD_Production_Jobs::set_status( $item, WC_GPD_Production_Jobs::STATUS_IN_BATCH, $order );
				$item->update_meta_data( WC_GPD_Production_Jobs::META_BATCH_ID, (string) $batch_id );
				$item->save();
			}
		}

		return $batch_id;
	}

	/**
	 * @param int $batch_id Batch ID.
	 * @return array|null
	 */
	public static function get( $batch_id ) {
		$post = get_post( absint( $batch_id ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$layout = json_decode( (string) get_post_meta( $batch_id, self::META_LAYOUT, true ), true );
		$bed    = json_decode( (string) get_post_meta( $batch_id, self::META_BED, true ), true );
		$items  = json_decode( (string) get_post_meta( $batch_id, self::META_ITEMS, true ), true );
		$export = json_decode( (string) get_post_meta( $batch_id, self::META_EXPORT_OPTS, true ), true );

		return array(
			'id'             => $batch_id,
			'title'          => $post->post_title,
			'layout'         => is_array( $layout ) ? $layout : array(),
			'bed'            => is_array( $bed ) ? $bed : self::default_bed(),
			'item_refs'      => is_array( $items ) ? $items : array(),
			'export_options' => is_array( $export ) ? $export : WC_GPD_Settings::export_defaults(),
		);
	}

	/**
	 * @param int   $batch_id Batch ID.
	 * @param array $layout   Layout rows.
	 * @param array $bed      Bed config.
	 * @return bool|WP_Error
	 */
	public static function save_layout( $batch_id, array $layout, array $bed = array() ) {
		$batch = self::get( $batch_id );
		if ( ! $batch ) {
			return new WP_Error( 'wc_gpd_batch_missing', __( 'Batch not found.', 'wc-generic-product-designer' ) );
		}

		$clean = array();
		foreach ( $layout as $row ) {
			if ( empty( $row['order_id'] ) || empty( $row['item_id'] ) ) {
				continue;
			}
			$clean[] = array(
				'order_id' => absint( $row['order_id'] ),
				'item_id'  => absint( $row['item_id'] ),
				'x'        => (float) ( $row['x'] ?? 0 ),
				'y'        => (float) ( $row['y'] ?? 0 ),
				'scale'    => max( 0.01, (float) ( $row['scale'] ?? 1 ) ),
				'rotation' => (float) ( $row['rotation'] ?? 0 ),
				'width'    => (float) ( $row['width'] ?? 0 ),
				'height'   => (float) ( $row['height'] ?? 0 ),
			);
		}

		update_post_meta( $batch_id, self::META_LAYOUT, wp_json_encode( $clean ) );
		if ( ! empty( $bed ) ) {
			self::save_bed( $batch_id, $bed );
		}

		return true;
	}

	/**
	 * @param int   $batch_id Batch ID.
	 * @param array $bed      Bed config.
	 * @return bool|WP_Error
	 */
	public static function save_bed( $batch_id, array $bed ) {
		$batch = self::get( $batch_id );
		if ( ! $batch ) {
			return new WP_Error( 'wc_gpd_batch_missing', __( 'Batch not found.', 'wc-generic-product-designer' ) );
		}
		$bed_clean = self::bed_with_pixels(
			(float) ( $bed['width'] ?? 24 ),
			(float) ( $bed['height'] ?? 18 ),
			(string) ( $bed['unit'] ?? 'in' ),
			absint( $bed['dpi'] ?? 96 )
		);
		update_post_meta( $batch_id, self::META_BED, wp_json_encode( $bed_clean ) );
		return true;
	}

	/**
	 * @param int   $batch_id Batch ID.
	 * @param array $options  Export options.
	 * @return bool|WP_Error
	 */
	public static function save_export_options( $batch_id, array $options ) {
		$batch = self::get( $batch_id );
		if ( ! $batch ) {
			return new WP_Error( 'wc_gpd_batch_missing', __( 'Batch not found.', 'wc-generic-product-designer' ) );
		}
		$clean = WC_GPD_Export::normalize_options( $options );
		update_post_meta( $batch_id, self::META_EXPORT_OPTS, wp_json_encode( $clean ) );
		return true;
	}

	/**
	 * Save layout, bed, and export options together.
	 *
	 * @param int   $batch_id Batch ID.
	 * @param array $layout   Layout rows.
	 * @param array $bed      Bed config.
	 * @param array $options  Export options.
	 * @return bool|WP_Error
	 */
	public static function save_batch_state( $batch_id, array $layout, array $bed = array(), array $options = array() ) {
		$result = self::save_layout( $batch_id, $layout, $bed );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! empty( $options ) ) {
			$opt_result = self::save_export_options( $batch_id, $options );
			if ( is_wp_error( $opt_result ) ) {
				return $opt_result;
			}
		}
		return true;
	}

	/**
	 * @return array<int,array{id:int,title:string,date:string,count:int}>
	 */
	public static function list_batches() {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$list = array();
		foreach ( $query->posts as $post ) {
			$batch = self::get( $post->ID );
			$list[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
				'date'  => get_the_date( '', $post ),
				'count' => $batch ? count( $batch['layout'] ) : 0,
			);
		}
		return $list;
	}

	/**
	 * Remove a job from a batch and mark it ready again.
	 *
	 * @param int $batch_id  Batch ID.
	 * @param int $order_id  Order ID.
	 * @param int $item_id   Item ID.
	 * @return bool|WP_Error
	 */
	public static function remove_item( $batch_id, $order_id, $item_id ) {
		$batch = self::get( $batch_id );
		if ( ! $batch ) {
			return new WP_Error( 'wc_gpd_batch_missing', __( 'Batch not found.', 'wc-generic-product-designer' ) );
		}

		$order_id = absint( $order_id );
		$item_id  = absint( $item_id );

		$layout = array_values(
			array_filter(
				$batch['layout'],
				function ( $row ) use ( $order_id, $item_id ) {
					return absint( $row['order_id'] ?? 0 ) !== $order_id || absint( $row['item_id'] ?? 0 ) !== $item_id;
				}
			)
		);

		$item_refs = array_values(
			array_filter(
				$batch['item_refs'],
				function ( $row ) use ( $order_id, $item_id ) {
					return absint( $row['order_id'] ?? 0 ) !== $order_id || absint( $row['item_id'] ?? 0 ) !== $item_id;
				}
			)
		);

		update_post_meta( $batch_id, self::META_LAYOUT, wp_json_encode( $layout ) );
		update_post_meta( $batch_id, self::META_ITEMS, wp_json_encode( $item_refs ) );

		$item = WC_GPD_Production_Jobs::get_item( $order_id, $item_id );
		if ( $item ) {
			$order = wc_get_order( $order_id );
			WC_GPD_Production_Jobs::set_status( $item, WC_GPD_Production_Jobs::STATUS_READY, $order );
			$item->delete_meta_data( WC_GPD_Production_Jobs::META_BATCH_ID );
			$item->save();
		}

		return true;
	}
}
