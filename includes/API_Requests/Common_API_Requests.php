<?php

namespace WPWC\iikoCloud\API_Requests;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\HTTP_Request;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\CacheTrait;
use WPWC\iikoCloud\Traits\ChecksTrait;
use WPWC\iikoCloud\Traits\ExportTrait;
use WPWC\iikoCloud\Traits\ImportTrait;
use WPWC\iikoCloud\Traits\WCActionsTrait;

class Common_API_Requests {

	use CacheTrait;
	use ChecksTrait;
	use ExportTrait;
	use ImportTrait;
	use WCActionsTrait;

	/**
	 * Time until expiration in seconds (one hour).
	 */
	const TRANSIENT_EXPIRATION = 3600;

	/**
	 * @var string API-key (API-login)
	 */
	protected string $api_login;

	/**
	 * @var string Default organization ID for import requests.
	 */
	protected string $organization_id_import;

	/**
	 * @var string Default organization ID for export requests.
	 */
	protected string $organization_id_export;

	/**
	 * @var null|string Default terminal ID for import and export requests.
	 */
	protected ?string $terminal_id;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->api_login              = sanitize_key( get_option( WC_IIKOCLOUD_PREFIX . 'apiLogin', '' ) );
		$this->organization_id_import = sanitize_key( get_option( WC_IIKOCLOUD_PREFIX . 'organization_id_import', '' ) );
		$this->organization_id_export = sanitize_key( get_option( WC_IIKOCLOUD_PREFIX . 'organization_id_export', '' ) );
		$chosen_terminals             = wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'chosen_terminals' ) );
		$this->terminal_id            = is_array( $chosen_terminals ) && ! empty( array_key_first( $chosen_terminals ) ) ? array_key_first( $chosen_terminals ) : null;
	}

	/**
	 * Get access token from iiko.
	 * Retrieve session key for API user.
	 *
	 * @return false|string Authentication token or false if there is an error. The standard token lifetime is 1 hour.
	 */
	protected function get_access_token() {

		if ( empty( $this->api_login ) ) {
			Logs::add_error( esc_html__( 'iiko API-key is not set. Check the API-key in plugin settings', 'wc-iikocloud' ) );

			return false;
		}

		$token = get_transient( WC_IIKOCLOUD_PREFIX . 'access_token' );

		if ( ! empty( $token ) ) {
			return 'Bearer ' . $token;

		} else {

			$url     = 'access_token';
			$headers = [];
			$body    = [
				'apiLogin' => $this->api_login,
			];

			$token_response = HTTP_Request::remote_post( $url, $headers, $body );

			if ( false === $token_response ) {
				Logs::add_error( esc_html__( 'Token request failed. Check the API-key in plugin settings', 'wc-iikocloud' ) );

				return false;
			}

			if ( empty( $token_response['token'] ) ) {
				Logs::add_error( esc_html__( 'Response does not contain token', 'wc-iikocloud' ) );

				return false;

			} else {

				if ( false === set_transient( WC_IIKOCLOUD_PREFIX . 'access_token', $token_response['token'], self::TRANSIENT_EXPIRATION ) ) {
					Logs::add_error( esc_html__( 'Cannot set access token transient', 'wc-iikocloud' ) );
				}

				return 'Bearer ' . $token_response['token'];
			}
		}
	}

	/**
	 * Get organizations from iiko.
	 * Returns organizations available to api-login user.
	 *
	 * @return array|false List of organizations.
	 */
	public function get_organizations() {

		$access_token = $this->get_access_token();

		// Required data for remote post.
		if ( false === $access_token ) {
			return false;
		}

		$url     = 'organizations';
		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [
			'organizationIds'      => null,
			'returnAdditionalInfo' => true,
			'includeDisabled'      => false,
		];

		return HTTP_Request::remote_post( $url, $headers, $body );
	}

	/**
	 * Get terminals from iiko.
	 * Method that returns information on groups of delivery terminals.
	 *
	 * @param string|null $organization_id Organizations IDs for which information is requested.
	 *
	 * @return array|bool List of terminal groups broken down by organizations.
	 */
	public function get_terminals( ?string $organization_id = null ) {

		$access_token = $this->get_access_token();

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id_import ) ) {
			$organization_id = $this->organization_id_import;
		}

		if ( false === $access_token || empty( $organization_id ) ) {
			return false;
		}

		$url     = 'terminal_groups';
		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [
			'organizationIds' => [ $organization_id ],
			'includeDisabled' => false,
		];

		$terminals = HTTP_Request::remote_post( $url, $headers, $body );

		if ( empty( $terminals['terminalGroups'] ) ) {

			Logs::add_error( esc_html__( 'Terminals are empty', 'wc-iikocloud' ) );

			return false;
		}

		return $terminals;
	}

	/**
	 * Get status of command.
	 *
	 * Currently used commands:
	 * order/create
	 * deliveries/create
	 *
	 * in method Export_API_Requests->create_delivery()
	 *
	 * @param string $correlation_id Operation ID obtained from any command supporting operations.
	 * @param string|null $organization_id Organization ID which 'correlationId' belongs to.
	 *
	 * @return array|bool
	 * @since  2.4.9
	 */
	public function get_command_status( string $correlation_id, ?string $organization_id = null ) {

		if ( empty( $correlation_id ) ) {
			Logs::add_wc_log( 'Correlation ID is empty.', 'command-status', 'error' );

			return false;
		}

		$access_token = $this->get_access_token();

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id_import ) ) {
			$organization_id = $this->organization_id_import;
		}

		if ( false === $access_token || empty( $organization_id ) ) {
			Logs::add_wc_log( 'Access token or organization ID are empty.', 'command-status', 'error' );

			return false;
		}

		$url     = 'commands/status';
		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [
			'organizationId' => $organization_id,
			'correlationId'  => $correlation_id,
		];

		$command_status = HTTP_Request::remote_post( $url, $headers, $body, 1, true );

		switch ( $command_status['response_code'] ) {
			case 200:
				switch ( $command_status['state'] ) {

					case 'Success':
					case 'InProgress':
						return $command_status['state'];

					case 'Error':
						if ( null !== $command_status['exception'] ) {
							Logs::add_wc_log( $command_status['exception'], 'command-status', 'error' );
						}

						return false;

					default:
						Logs::add_wc_log( 'Unexpected command status', 'command-status', 'error' );

						return false;
				}

			case 400:
				$state = 'Bad Request (400)';
				break;

			case 401:
				$state = 'Unauthorized (401)';
				break;

			case 403:
				$state = 'Forbidden (403)';
				break;

			case 408:
				$state = 'Request Timeout (408)';
				break;

			case 500:
				$state = 'Server Error (500)';
				break;

			default:
				$state = 'Unknown state';
		}

		Logs::add_wc_log( $state, 'command-status', 'error' );

		if ( null !== $command_status['error'] ) {
			Logs::add_wc_log( $command_status['error'], 'command-status', 'error' );
		}

		Logs::add_wc_log( $command_status['errorDescription'], 'command-status', 'error' );

		return false;
	}
}
