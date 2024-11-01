<?php

namespace WPWC\iikoCloud;

defined( 'ABSPATH' ) || exit;

class HTTP_Request {

	/**
	 * Submit request.
	 *
	 * @param string $resource
	 * @param array $add_headers
	 * @param array $body
	 * @param string $api_version
	 * @param bool $ignore_iiko_errors
	 *
	 * @return array|false Array with the api answer or false if there are any errors.
	 */
	public static function remote_post( string $resource, array $add_headers = [], array $body = [], string $api_version = '1', bool $ignore_iiko_errors = false ) {

		if ( empty( $resource ) ) {
			$message = esc_html__( 'API request resource is empty', 'wc-iikocloud' );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'remote-post', 'error' );

			return false;
		}

		$url           = 'yes' === get_option( WC_IIKOCLOUD_PREFIX . 'european_server' ) ?
			'https://api-eu.syrve.live/api/' :
			'https://api-ru.iiko.services/api/';
		$url           = esc_url( $url . $api_version . '/' . $resource );
		$timeout       = absint( get_option( WC_IIKOCLOUD_PREFIX . 'timeout' ) );
		$timeout       = $timeout > 0 ? $timeout : 15;
		$headers       = [
			'Content-Type' => 'application/json; charset=utf-8',
			'Timeout'      => $timeout,
		];
		$headers       = is_array( $add_headers ) && ! empty( $add_headers ) ? array_merge( $headers, $add_headers ) : $headers;
		$body          = is_array( $body ) ? $body : [];
		$args          = [
			'method'      => 'POST',
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => $headers,
			'body'        => wp_json_encode( $body ),
			'data_format' => 'body',
		];
		$response      = wp_safe_remote_post( $url, $args );
		$response_body = wp_remote_retrieve_body( $response );

		// Save request URL to log.
		Logs::add_wc_log( mb_strtoupper( 'Remote post URL' ), 'remote-post' );
		Logs::add_wc_log( $url, 'remote-post' );

		// Save request body to log.
		Logs::add_wc_log( mb_strtoupper( 'Remote post body' ), 'remote-post' );
		Logs::add_wc_log( wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), 'remote-post' );

		// Save iiko response to log.
		Logs::add_wc_log( mb_strtoupper( 'Remote post response' ), 'remote-post' );
		Logs::add_wc_log( $response_body, 'remote-post' );

		// WP_Error.
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_code    = $response->get_error_code();
			$message       = sprintf( esc_html__( 'Request failed. WP_Error: %s - %s', 'wc-iikocloud' ), $error_code, $error_message );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'remote-post', 'error' );

			return false;
		}

		// Wrong response code error.
		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );

		if ( 200 !== $response_code ) {
			$message = sprintf( esc_html__( 'Request failed. %s - %s', 'wc-iikocloud' ), $response_code, $response_message );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'remote-post', 'error' );
			// No return because we can have iiko errors.
		}

		// Decode JSON response body to an associative array.
		$response = json_decode( $response_body, true );

		// JSON decode error.
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$message = esc_html__( 'Response body is not a correct JSON', 'wc-iikocloud' );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'remote-post', 'error' );
		}

		// Response body is empty error.
		if ( ! is_array( $response ) || empty( $response ) ) {
			$message = esc_html__( 'Response is not an array or is empty', 'wc-iikocloud' );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'remote-post', 'error' );

			return false;
		}

		// Iiko error.
		if ( ! $ignore_iiko_errors && array_key_exists( 'errorDescription', $response ) ) {
			$error_number = $response['error'] ?? '';
			$message      = sprintf( esc_html__( 'Iiko response contains the error: %s - %s', 'wc-iikocloud' ), $error_number, $response['errorDescription'] );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'remote-post', 'error' );

			return false;
		}

		// Add response code to the response.
		$response['response_code'] = $response_code;

		return $response;
	}
}
