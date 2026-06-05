<?php
/**
 * Bundled Bootstrap Icons (MIT) for template shape picker.
 *
 * @see https://icons.getbootstrap.com/
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap Icons catalog and SVG loader.
 */
class WC_GPD_Bootstrap_Icons {

	const ICONS_DIR      = 'assets/vendor/bootstrap-icons/icons';
	const AJAX_SEARCH    = 'wc_gpd_search_bootstrap_icons';
	const AJAX_GET_ICON  = 'wc_gpd_get_bootstrap_icon';
	const NONCE_ACTION   = 'wc_gpd_bootstrap_icons';

	/**
	 * @var string[]|null
	 */
	private static $slug_cache = null;

	/**
	 * Register AJAX handlers.
	 */
	public static function register_ajax() {
		add_action( 'wp_ajax_' . self::AJAX_SEARCH, array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_' . self::AJAX_GET_ICON, array( __CLASS__, 'ajax_get_icon' ) );
	}

	/**
	 * Absolute path to icons directory.
	 *
	 * @return string
	 */
	public static function icons_dir() {
		return trailingslashit( WC_GPD_PLUGIN_DIR ) . self::ICONS_DIR;
	}

	/**
	 * @return string[]
	 */
	public static function all_slugs() {
		if ( null !== self::$slug_cache ) {
			return self::$slug_cache;
		}

		$dir = self::icons_dir();
		if ( ! is_dir( $dir ) ) {
			self::$slug_cache = array();
			return self::$slug_cache;
		}

		$files = glob( $dir . '/*.svg' );
		if ( ! is_array( $files ) ) {
			self::$slug_cache = array();
			return self::$slug_cache;
		}

		$slugs = array();
		foreach ( $files as $file ) {
			$slug = basename( $file, '.svg' );
			if ( self::is_valid_slug( $slug ) ) {
				$slugs[] = $slug;
			}
		}
		sort( $slugs );
		self::$slug_cache = $slugs;
		return self::$slug_cache;
	}

	/**
	 * @param string $slug Icon slug.
	 * @return bool
	 */
	public static function is_valid_slug( $slug ) {
		return (bool) preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $slug );
	}

	/**
	 * @param string $query  Search query.
	 * @param int    $limit  Page size.
	 * @param int    $offset Offset.
	 * @return array{icons:string[],total:int,offset:int,limit:int}
	 */
	public static function search( $query = '', $limit = 60, $offset = 0 ) {
		$all    = self::all_slugs();
		$query  = strtolower( trim( (string) $query ) );
		$limit  = min( 200, max( 12, absint( $limit ) ) );
		$offset = max( 0, absint( $offset ) );

		if ( $query ) {
			$matched = array_values(
				array_filter(
					$all,
					static function ( $slug ) use ( $query ) {
						return false !== strpos( $slug, $query );
					}
				)
			);
		} else {
			$matched = $all;
		}

		$total = count( $matched );
		$page  = array_slice( $matched, $offset, $limit );

		return array(
			'icons'  => $page,
			'total'  => $total,
			'offset' => $offset,
			'limit'  => $limit,
		);
	}

	/**
	 * Featured icon slugs for quick access in the shapes panel.
	 *
	 * @return string[]
	 */
	public static function featured_slugs() {
		$wanted = array(
			'heart-fill',
			'star-fill',
			'flower1',
			'award-fill',
			'gem',
			'shield-fill',
			'music-note-beamed',
			'tree-fill',
			'balloon-heart',
			'plus-lg',
			'infinity',
			'suit-heart-fill',
		);
		$all    = array_flip( self::all_slugs() );
		$found  = array();
		foreach ( $wanted as $slug ) {
			if ( isset( $all[ $slug ] ) ) {
				$found[] = $slug;
			}
		}
		return $found;
	}

	/**
	 * @param string $slug Icon slug.
	 * @return string|false
	 */
	public static function get_svg( $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( ! self::is_valid_slug( $slug ) ) {
			return false;
		}

		$path = self::icons_dir() . '/' . $slug . '.svg';
		if ( ! is_readable( $path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$svg = file_get_contents( $path );
		return is_string( $svg ) && '' !== trim( $svg ) ? $svg : false;
	}

	/**
	 * Public URL for inline preview img/src (admin only).
	 *
	 * @param string $slug Icon slug.
	 * @return string|false
	 */
	public static function icon_url( $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( ! self::is_valid_slug( $slug ) ) {
			return false;
		}
		$path = self::icons_dir() . '/' . $slug . '.svg';
		if ( ! is_readable( $path ) ) {
			return false;
		}
		return WC_GPD_PLUGIN_URL . self::ICONS_DIR . '/' . $slug . '.svg';
	}

	/**
	 * AJAX: search icon slugs.
	 */
	public static function ajax_search() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-generic-product-designer' ) ), 403 );
		}

		$query  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$limit  = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 60;
		$offset = isset( $_GET['offset'] ) ? absint( $_GET['offset'] ) : 0;

		$results = self::search( $query, $limit, $offset );
		wp_send_json_success(
			array_merge(
				$results,
				array(
					'featured' => self::featured_slugs(),
					'source'   => 'Bootstrap Icons',
					'license'  => 'MIT',
				)
			)
		);
	}

	/**
	 * AJAX: return raw SVG markup for canvas import.
	 */
	public static function ajax_get_icon() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-generic-product-designer' ) ), 403 );
		}

		$slug = isset( $_GET['icon'] ) ? sanitize_title( wp_unslash( $_GET['icon'] ) ) : '';
		$svg  = self::get_svg( $slug );
		if ( ! $svg ) {
			wp_send_json_error( array( 'message' => __( 'Icon not found.', 'wc-generic-product-designer' ) ), 404 );
		}

		wp_send_json_success(
			array(
				'slug' => $slug,
				'svg'  => $svg,
				'url'  => self::icon_url( $slug ),
			)
		);
	}
}
