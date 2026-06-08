<?php
/**
 * Etsy Open API v3 client.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minimal Etsy API wrapper.
 */
class WC_GPD_Etsy_Client {

	const API_BASE = 'https://openapi.etsy.com/v3/application';

	/**
	 * @return bool
	 */
	public static function is_configured() {
		$key    = trim( (string) WC_GPD_Settings::get( 'etsy_api_key', '' ) );
		$token  = trim( (string) WC_GPD_Settings::get( 'etsy_refresh_token', '' ) );
		$shop   = trim( (string) WC_GPD_Settings::get( 'etsy_shop_id', '' ) );
		return '' !== $key && '' !== $token && '' !== $shop;
	}

	/**
	 * @return string|WP_Error
	 */
	public static function get_access_token() {
		$cached = get_transient( 'wc_gpd_etsy_access_token' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$key           = trim( (string) WC_GPD_Settings::get( 'etsy_api_key', '' ) );
		$shared_secret = trim( (string) WC_GPD_Settings::get( 'etsy_shared_secret', '' ) );
		$refresh       = trim( (string) WC_GPD_Settings::get( 'etsy_refresh_token', '' ) );

		if ( ! $key || ! $refresh ) {
			return new WP_Error( 'wc_gpd_etsy_config', __( 'Etsy API credentials are not configured.', 'wc-generic-product-designer' ) );
		}

		$response = wp_remote_post(
			'https://api.etsy.com/v3/public/oauth/token',
			array(
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => $key,
					'refresh_token' => $refresh,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || empty( $body['access_token'] ) ) {
			$message = ! empty( $body['error_description'] ) ? $body['error_description'] : __( 'Etsy token refresh failed.', 'wc-generic-product-designer' );
			return new WP_Error( 'wc_gpd_etsy_token', $message );
		}

		$expires = ! empty( $body['expires_in'] ) ? absint( $body['expires_in'] ) - 60 : 3000;
		set_transient( 'wc_gpd_etsy_access_token', $body['access_token'], max( 60, $expires ) );

		return $body['access_token'];
	}

	/**
	 * @param string $path   API path.
	 * @param array  $query  Query args.
	 * @return array|WP_Error
	 */
	public static function request( $path, array $query = array() ) {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$key = trim( (string) WC_GPD_Settings::get( 'etsy_api_key', '' ) );
		$url = trailingslashit( self::API_BASE ) . ltrim( $path, '/' );
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'x-api-key'       => $key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wc_gpd_etsy_api', __( 'Etsy API request failed.', 'wc-generic-product-designer' ), $body );
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * @param int $limit Limit.
	 * @return array|WP_Error
	 */
	public static function get_shop_receipts( $limit = 25 ) {
		$shop_id = trim( (string) WC_GPD_Settings::get( 'etsy_shop_id', '' ) );
		if ( ! $shop_id ) {
			return new WP_Error( 'wc_gpd_etsy_shop', __( 'Etsy shop ID is not configured.', 'wc-generic-product-designer' ) );
		}

		return self::request(
			'shops/' . rawurlencode( $shop_id ) . '/receipts',
			array(
				'limit'  => min( 100, max( 1, absint( $limit ) ) ),
				'was_paid' => 'true',
			)
		);
	}

	/**
	 * @return array
	 */
	public static function get_listing_map() {
		$map = WC_GPD_Settings::get( 'etsy_listing_map', array() );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * @param string $listing_id Etsy listing ID.
	 * @return array|null
	 */
	public static function get_map_for_listing( $listing_id ) {
		$map = self::get_listing_map();
		$listing_id = (string) $listing_id;
		return isset( $map[ $listing_id ] ) && is_array( $map[ $listing_id ] ) ? $map[ $listing_id ] : null;
	}
}
