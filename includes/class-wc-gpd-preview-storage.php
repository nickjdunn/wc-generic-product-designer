<?php
/**
 * Save design preview PNG files to uploads.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preview image storage.
 */
class WC_GPD_Preview_Storage {

	const MAX_BYTES = 1048576; // 1 MB.
	const SUBDIR    = 'wc-gpd-previews';

	/**
	 * Save a base64 PNG data URL as an uploaded file.
	 *
	 * @param string $data_url data:image/png;base64,... string.
	 * @return string Public URL or empty string on failure.
	 */
	public static function save_from_data_url( $data_url ) {
		if ( ! is_string( $data_url ) || 0 !== strpos( $data_url, 'data:image/png;base64,' ) ) {
			return '';
		}

		$encoded = substr( $data_url, strlen( 'data:image/png;base64,' ) );
		$binary  = base64_decode( $encoded, true );

		if ( false === $binary || strlen( $binary ) > self::MAX_BYTES || strlen( $binary ) < 100 ) {
			return '';
		}

		if ( ! function_exists( 'wp_upload_bits' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		self::ensure_upload_dir();

		$filename = 'preview-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false ) . '.png';
		$upload   = wp_upload_bits( $filename, null, $binary );

		if ( ! empty( $upload['error'] ) || empty( $upload['url'] ) ) {
			WC_GPD_Logger::warning( 'Preview image upload failed', array( 'error' => $upload['error'] ?? 'unknown' ) );
			return '';
		}

		return esc_url_raw( $upload['url'] );
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

		// Protect directory listing.
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}
}
