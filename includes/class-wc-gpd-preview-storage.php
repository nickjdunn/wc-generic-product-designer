<?php
/**
 * Save design preview files to uploads (SVG/PNG) as media attachments.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preview file storage.
 */
class WC_GPD_Preview_Storage {

	const MAX_BYTES = 1048576; // 1 MB.
	const SUBDIR    = 'wc-gpd-previews';

	/**
	 * Build preview from design SVG on the server (reliable; no canvas export needed).
	 *
	 * @param string $svg        Sanitized design SVG.
	 * @param array  $settings   Product designer settings.
	 * @param int    $product_id Product ID for attachment parent.
	 * @return array{url:string,id:int}
	 */
	public static function save_design_preview( $svg, $settings, $product_id = 0 ) {
		$composite = WC_GPD_Preview::build_composite_svg_document( $svg, $settings );
		if ( ! $composite ) {
			return array(
				'url' => '',
				'id'  => 0,
			);
		}

		$filename = 'preview-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.svg';
		$result   = self::write_file( $filename, $composite, 'image/svg+xml', $product_id );

		if ( empty( $result['url'] ) ) {
			WC_GPD_Logger::warning( 'Server-side SVG preview save failed' );
		} else {
			WC_GPD_Logger::info( 'Design preview file saved', array( 'url' => $result['url'] ) );
		}

		return $result;
	}

	/**
	 * Save a base64 PNG data URL as a media attachment.
	 *
	 * @param string $data_url   data:image/png;base64,... string.
	 * @param int    $product_id Product ID.
	 * @return array{url:string,id:int}
	 */
	public static function save_from_data_url( $data_url, $product_id = 0 ) {
		if ( ! is_string( $data_url ) || 0 !== strpos( $data_url, 'data:image/png;base64,' ) ) {
			return array(
				'url' => '',
				'id'  => 0,
			);
		}

		$encoded = substr( $data_url, strlen( 'data:image/png;base64,' ) );
		$binary  = base64_decode( $encoded, true );

		if ( false === $binary || strlen( $binary ) > self::MAX_BYTES || strlen( $binary ) < 100 ) {
			return array(
				'url' => '',
				'id'  => 0,
			);
		}

		$filename = 'preview-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.png';
		return self::write_file( $filename, $binary, 'image/png', $product_id );
	}

	/**
	 * Write bytes to uploads and register a media attachment.
	 *
	 * @param string $filename    File name.
	 * @param string $contents    File contents.
	 * @param string $mime_type   MIME type.
	 * @param int    $product_id  Parent post ID.
	 * @return array{url:string,id:int}
	 */
	private static function write_file( $filename, $contents, $mime_type, $product_id = 0 ) {
		if ( ! function_exists( 'wp_upload_bits' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		self::ensure_upload_dir();

		$upload = wp_upload_bits( $filename, null, $contents );

		if ( ! empty( $upload['error'] ) || empty( $upload['url'] ) || empty( $upload['file'] ) ) {
			return array(
				'url' => '',
				'id'  => 0,
			);
		}

		$attachment_id = self::create_attachment( $upload['file'], $upload['url'], $mime_type, $product_id );

		return array(
			'url' => esc_url_raw( $upload['url'] ),
			'id'  => $attachment_id,
		);
	}

	/**
	 * Register file as a WordPress attachment (enables WC thumbnail APIs).
	 *
	 * @param string $file_path   Absolute path.
	 * @param string $url         Public URL.
	 * @param string $mime_type   MIME type.
	 * @param int    $product_id  Parent product ID.
	 * @return int Attachment ID.
	 */
	private static function create_attachment( $file_path, $url, $mime_type, $product_id = 0 ) {
		if ( ! function_exists( 'wp_insert_attachment' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_file_name( wp_basename( $file_path ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		if ( $product_id ) {
			$attachment['post_parent'] = absint( $product_id );
		}

		$attachment_id = wp_insert_attachment( $attachment, $file_path );
		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			return 0;
		}

		if ( 'image/png' === $mime_type && function_exists( 'wp_generate_attachment_metadata' ) ) {
			$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
			if ( $metadata ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}

		return (int) $attachment_id;
	}

	/**
	 * Ensure uploads subdirectory exists.
	 */
	private static function ensure_upload_dir() {
		$upload = wp_upload_dir();
		if ( empty( $upload['basedir'] ) ) {
			return;
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::SUBDIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}

		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}
}
