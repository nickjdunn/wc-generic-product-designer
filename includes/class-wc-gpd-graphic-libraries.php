<?php
/**
 * Site-wide media and icon libraries for customer designer.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Library storage and helpers.
 */
class WC_GPD_Graphic_Libraries {

	const OPTION_KEY         = 'wc_gpd_site_graphic_libraries';
	const TYPE_GRAPHIC       = 'graphic';
	const TYPE_PHOTO         = 'photo';
	const TYPE_ICON          = 'icon';
	const ALL_ICONS_ID       = 'bootstrap_all';

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( is_string( $stored ) && '' !== trim( $stored ) ) {
			$decoded = json_decode( $stored, true );
			$stored  = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$libraries = self::normalize_libraries_array( $stored );
		return self::ensure_default_icon_library( $libraries );
	}

	/**
	 * @param array $libraries Libraries payload.
	 */
	public static function save_all( array $libraries ) {
		$normalized = self::normalize_libraries_array( $libraries );
		update_option( self::OPTION_KEY, wp_json_encode( self::ensure_default_icon_library( $normalized ) ) );
	}

	/**
	 * @param string $json JSON libraries.
	 * @return array<int,array<string,mixed>>
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
	 * @param string $type Library type.
	 * @return string
	 */
	public static function sanitize_type( $type ) {
		$type = sanitize_key( (string) $type );
		if ( in_array( $type, array( self::TYPE_GRAPHIC, self::TYPE_PHOTO, self::TYPE_ICON ), true ) ) {
			return $type;
		}
		return self::TYPE_GRAPHIC;
	}

	/**
	 * @param array $libraries Raw libraries.
	 * @return array<int,array<string,mixed>>
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
			$type = self::sanitize_type( $library['type'] ?? self::TYPE_GRAPHIC );
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
			$icon_slugs = array();
			if ( ! empty( $library['icon_slugs'] ) && is_array( $library['icon_slugs'] ) ) {
				foreach ( $library['icon_slugs'] as $slug ) {
					$slug = sanitize_title( (string) $slug );
					if ( $slug && WC_GPD_Bootstrap_Icons::is_valid_slug( $slug ) ) {
						$icon_slugs[] = $slug;
					}
				}
			}
			$all_icons = ! empty( $library['all_icons'] ) || self::ALL_ICONS_ID === $id;
			$clean[]     = array(
				'id'         => $id,
				'name'       => $name,
				'type'       => $type,
				'ids'        => array_values( array_unique( $ids ) ),
				'icon_slugs' => array_values( array_unique( $icon_slugs ) ),
				'all_icons'  => $all_icons,
			);
		}
		return $clean;
	}

	/**
	 * Ensure the virtual "all icons" library exists.
	 *
	 * @param array $libraries Libraries.
	 * @return array
	 */
	private static function ensure_default_icon_library( array $libraries ) {
		foreach ( $libraries as $library ) {
			if ( self::ALL_ICONS_ID === ( $library['id'] ?? '' ) ) {
				return $libraries;
			}
		}
		array_unshift(
			$libraries,
			array(
				'id'         => self::ALL_ICONS_ID,
				'name'       => __( 'All Bootstrap Icons', 'wc-generic-product-designer' ),
				'type'       => self::TYPE_ICON,
				'ids'        => array(),
				'icon_slugs' => array(),
				'all_icons'  => true,
			)
		);
		return $libraries;
	}

	/**
	 * @param string $type Library type.
	 * @return array<int,array<string,mixed>>
	 */
	public static function libraries_for_type( $type ) {
		$type = self::sanitize_type( $type );
		return array_values(
			array_filter(
				self::get_all(),
				static function ( $library ) use ( $type ) {
					return ( $library['type'] ?? self::TYPE_GRAPHIC ) === $type;
				}
			)
		);
	}

	/**
	 * @param string $library_id Library ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_by_id( $library_id ) {
		$library_id = sanitize_key( (string) $library_id );
		foreach ( self::get_all() as $library ) {
			if ( ( $library['id'] ?? '' ) === $library_id ) {
				return $library;
			}
		}
		return null;
	}

	/**
	 * @param string[] $library_ids Library IDs.
	 * @param string   $type        Expected type.
	 * @return int[]
	 */
	public static function attachment_ids_for_libraries( array $library_ids, $type ) {
		$type    = self::sanitize_type( $type );
		$allowed = array_flip( array_map( 'sanitize_key', $library_ids ) );
		$ids     = array();
		foreach ( self::get_all() as $library ) {
			if ( ( $library['type'] ?? self::TYPE_GRAPHIC ) !== $type ) {
				continue;
			}
			if ( ! empty( $allowed ) && ! isset( $allowed[ $library['id'] ?? '' ] ) ) {
				continue;
			}
			if ( ! empty( $library['ids'] ) ) {
				$ids = array_merge( $ids, $library['ids'] );
			}
		}
		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * @param string[] $library_ids Library IDs.
	 * @return array<int,array{id:int,url:string,title:string}>
	 */
	public static function media_items_for_libraries( array $library_ids, $type ) {
		$items = array();
		foreach ( self::attachment_ids_for_libraries( $library_ids, $type ) as $attachment_id ) {
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

	/**
	 * @param string[] $library_ids Library IDs.
	 * @return string[]
	 */
	public static function icon_slugs_for_libraries( array $library_ids ) {
		$allowed = array_flip( array_map( 'sanitize_key', $library_ids ) );
		$slugs   = array();
		foreach ( self::get_all() as $library ) {
			if ( self::TYPE_ICON !== ( $library['type'] ?? '' ) ) {
				continue;
			}
			if ( ! empty( $allowed ) && ! isset( $allowed[ $library['id'] ?? '' ] ) ) {
				continue;
			}
			if ( ! empty( $library['all_icons'] ) ) {
				return WC_GPD_Bootstrap_Icons::all_slugs();
			}
			if ( ! empty( $library['icon_slugs'] ) ) {
				$slugs = array_merge( $slugs, $library['icon_slugs'] );
			}
		}
		return array_values( array_unique( $slugs ) );
	}

	/**
	 * All attachment IDs across graphic/photo libraries.
	 *
	 * @return int[]
	 */
	public static function all_attachment_ids() {
		$ids = array();
		foreach ( self::get_all() as $library ) {
			$type = $library['type'] ?? self::TYPE_GRAPHIC;
			if ( self::TYPE_ICON === $type ) {
				continue;
			}
			if ( ! empty( $library['ids'] ) ) {
				$ids = array_merge( $ids, $library['ids'] );
			}
		}
		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * @deprecated Use media_items_for_libraries().
	 * @return array<int,array{id:int,url:string,title:string}>
	 */
	public static function flat_for_frontend() {
		return self::media_items_for_libraries( array(), self::TYPE_GRAPHIC );
	}
}
