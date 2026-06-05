<?php
/**
 * Site-wide graphic libraries for customer pick areas.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Graphic library storage and helpers.
 */
class WC_GPD_Graphic_Libraries {

	const OPTION_KEY = 'wc_gpd_site_graphic_libraries';

	/**
	 * @return array<int,array{id:string,name:string,ids:int[]}>
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return self::normalize_libraries_array( $stored );
	}

	/**
	 * @param array $libraries Libraries payload.
	 */
	public static function save_all( array $libraries ) {
		update_option( self::OPTION_KEY, wp_json_encode( self::normalize_libraries_array( $libraries ) ) );
	}

	/**
	 * @param string $json JSON libraries.
	 * @return array<int,array{id:string,name:string,ids:int[]}>
	 */
	public static function sanitize_from_json( $json ) {
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
		return $clean;
	}

	/**
	 * All attachment IDs across libraries.
	 *
	 * @return int[]
	 */
	public static function all_attachment_ids() {
		$ids = array();
		foreach ( self::get_all() as $library ) {
			if ( ! empty( $library['ids'] ) ) {
				$ids = array_merge( $ids, $library['ids'] );
			}
		}
		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Flat graphic list for storefront designer.
	 *
	 * @return array<int,array{id:int,url:string,title:string}>
	 */
	public static function flat_for_frontend() {
		$items = array();
		foreach ( self::all_attachment_ids() as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( ! $url ) {
				continue;
			}
			$items[] = array(
				'id'    => $attachment_id,
				'url'   => $url,
				'title' => get_the_title( $attachment_id ),
			);
		}
		return $items;
	}
}
