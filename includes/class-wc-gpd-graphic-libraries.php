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
	 * One-time seed of deletable demo libraries (graphic, photo, icon).
	 */
	public static function maybe_seed_demo_libraries() {
		if ( get_option( 'wc_gpd_demo_libraries_seeded' ) ) {
			return;
		}

		$stored = get_option( self::OPTION_KEY, array() );
		if ( is_string( $stored ) && '' !== trim( $stored ) ) {
			$decoded = json_decode( $stored, true );
			$stored  = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$libraries = self::normalize_libraries_array( $stored );
		$libraries = self::ensure_default_icon_library( $libraries );
		$libraries = self::append_demo_libraries( $libraries );
		update_option( self::OPTION_KEY, wp_json_encode( $libraries ) );
		update_option( 'wc_gpd_demo_libraries_seeded', 1 );
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
			if ( self::ALL_ICONS_ID === $id ) {
				$type      = self::TYPE_ICON;
				$all_icons = true;
			} else {
				$all_icons = ! empty( $library['all_icons'] );
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
			$icon_slugs = array();
			if ( ! empty( $library['icon_slugs'] ) && is_array( $library['icon_slugs'] ) ) {
				foreach ( $library['icon_slugs'] as $slug ) {
					$slug = sanitize_title( (string) $slug );
					if ( $slug && WC_GPD_Bootstrap_Icons::is_valid_slug( $slug ) ) {
						$icon_slugs[] = $slug;
					}
				}
			}
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
		foreach ( $libraries as $index => $library ) {
			if ( self::ALL_ICONS_ID === ( $library['id'] ?? '' ) ) {
				$libraries[ $index ]['type']       = self::TYPE_ICON;
				$libraries[ $index ]['all_icons']  = true;
				$libraries[ $index ]['ids']         = array();
				$libraries[ $index ]['icon_slugs']  = array();
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

	const DEMO_GRAPHIC_ID = 'demo_graphic';
	const DEMO_PHOTO_ID   = 'demo_photo';
	const DEMO_ICON_ID    = 'demo_icon';
	const DEMO_ATTACHMENT_OPTION = 'wc_gpd_demo_library_attachment';

	/**
	 * Seed deletable demo libraries for each type.
	 *
	 * @param array $libraries Libraries.
	 * @return array
	 */
	private static function append_demo_libraries( array $libraries ) {
		$existing = array();
		foreach ( $libraries as $library ) {
			if ( ! empty( $library['id'] ) ) {
				$existing[ $library['id'] ] = true;
			}
		}

		$demo_attachment = self::get_or_create_demo_attachment();

		if ( empty( $existing[ self::DEMO_GRAPHIC_ID ] ) ) {
			$libraries[] = array(
				'id'         => self::DEMO_GRAPHIC_ID,
				'name'       => __( 'Demo graphics', 'wc-generic-product-designer' ),
				'type'       => self::TYPE_GRAPHIC,
				'ids'        => $demo_attachment ? array( $demo_attachment ) : array(),
				'icon_slugs' => array(),
				'all_icons'  => false,
			);
		}

		if ( empty( $existing[ self::DEMO_PHOTO_ID ] ) ) {
			$libraries[] = array(
				'id'         => self::DEMO_PHOTO_ID,
				'name'       => __( 'Demo photos', 'wc-generic-product-designer' ),
				'type'       => self::TYPE_PHOTO,
				'ids'        => $demo_attachment ? array( $demo_attachment ) : array(),
				'icon_slugs' => array(),
				'all_icons'  => false,
			);
		}

		if ( empty( $existing[ self::DEMO_ICON_ID ] ) ) {
			$libraries[] = array(
				'id'         => self::DEMO_ICON_ID,
				'name'       => __( 'Demo icons', 'wc-generic-product-designer' ),
				'type'       => self::TYPE_ICON,
				'ids'        => array(),
				'icon_slugs' => WC_GPD_Bootstrap_Icons::featured_slugs(),
				'all_icons'  => false,
			);
		}

		return $libraries;
	}

	/**
	 * @return int Attachment ID or 0.
	 */
	private static function get_or_create_demo_attachment() {
		$stored = absint( get_option( self::DEMO_ATTACHMENT_OPTION, 0 ) );
		if ( $stored && wp_get_attachment_url( $stored ) ) {
			return $stored;
		}

		$source = WC_GPD_PLUGIN_DIR . 'assets/demo/gpd-demo-graphic.svg';
		if ( ! is_readable( $source ) ) {
			return 0;
		}

		if ( ! function_exists( 'wp_upload_dir' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return 0;
		}

		$filename = 'gpd-demo-graphic.svg';
		$dest     = trailingslashit( $upload_dir['path'] ) . $filename;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( ! copy( $source, $dest ) ) {
			return 0;
		}

		$filetype = wp_check_filetype( $filename, null );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/svg+xml',
				'post_title'     => __( 'GPD demo graphic', 'wc-generic-product-designer' ),
				'post_status'    => 'inherit',
			),
			$dest
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		wp_generate_attachment_metadata( $attachment_id, $dest );
		update_option( self::DEMO_ATTACHMENT_OPTION, $attachment_id );

		return absint( $attachment_id );
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
				'mime'  => get_post_mime_type( $attachment_id ) ? get_post_mime_type( $attachment_id ) : '',
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
