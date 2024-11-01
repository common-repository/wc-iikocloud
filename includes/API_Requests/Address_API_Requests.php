<?php

namespace WPWC\iikoCloud\API_Requests;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\HTTP_Request;
use WPWC\iikoCloud\Logs;

class Address_API_Requests extends Common_API_Requests {

	/**
	 * @var null|array City IDs.
	 */
	protected ?array $city_ids;

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$city_ids       = wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'chosen_city_ids' ) );
		$this->city_ids = ! empty( $city_ids ) ? $city_ids : null;
	}

	/**
	 * Update city streets and the streets amount in the plugin settings.
	 *
	 * @param  string  $city_id
	 * @param  array  $streets
	 *
	 * @return bool
	 */
	protected function update_city_streets( string $city_id, array $streets ): bool {

		delete_option( WC_IIKOCLOUD_PREFIX . 'streets_' . $city_id );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'streets_' . $city_id, $streets ) ) {
			Logs::add_error( sprintf( esc_html__( 'Cannot add streets for the city %s to the plugin options.', 'wc-iikocloud' ), $city_id ) );

			return false;
		}

		delete_option( WC_IIKOCLOUD_PREFIX . 'streets_amount_' . $city_id );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'streets_amount_' . $city_id, count( $streets ) ) ) {
			Logs::add_error( sprintf( esc_html__( 'Cannot add streets amount for the city %s to the plugin options.', 'wc-iikocloud' ), $city_id ) );
		}

		return true;
	}

	/**
	 * Update chosen organization's cities IDs in plugin settings.
	 *
	 * @param  array  $chosen_city_ids
	 *
	 * @return bool
	 */
	protected function update_chosen_cities_ids( array $chosen_city_ids ): bool {

		delete_option( WC_IIKOCLOUD_PREFIX . 'chosen_city_ids' );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'chosen_city_ids', $chosen_city_ids ) ) {
			Logs::add_error( esc_html__( 'Cannot add chosen cities IDs to the plugin options', 'wc-iikocloud' ) );

			return false;
		}

		delete_option( WC_IIKOCLOUD_PREFIX . 'export[default_city_id]' );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'export[default_city_id]', $chosen_city_ids[0] ) ) {
			Logs::add_error( esc_html__( 'Cannot add default city ID to the plugin options', 'wc-iikocloud' ) );

			return false;
		}

		return true;
	}

	/**
	 * Get cities from iiko and save all organization's cities to the plugin options.
	 *
	 * @param ?string  $organization_id
	 *
	 * @return array|bool
	 */
	public function get_cities( string $organization_id = null ) {

		$access_token = $this->get_access_token();

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id_export ) ) {
			$organization_id = $this->organization_id_export;
		}

		if ( false === $access_token || empty( $organization_id ) ) {
			return false;
		}

		$url     = 'cities';
		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [
			'organizationIds' => [ $organization_id ],
		];

		$cities = HTTP_Request::remote_post( $url, $headers, $body );

		if ( ! empty( $cities['cities'] ) ) {

			// Change keys onto organization ID and remove doubled IDs.
			$cities = array_column( $cities['cities'], null, 'organizationId' );

			// Clear from deleted cities.
			$cities = array_filter( $cities[ $organization_id ]['items'], function ( $city_info, $index ) {
				return $city_info['isDeleted'] === false;
			}, ARRAY_FILTER_USE_BOTH );

			// Create a simple array with only basic data for output on admin page and remove doubled IDs.
			$cities = array_column( $cities, 'name', 'id' );

			// Update all organization's cities in plugin settings.
			if ( ! empty( $cities ) ) {

				delete_option( WC_IIKOCLOUD_PREFIX . 'all_cities' );

				if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'all_cities', $cities ) ) {
					Logs::add_error( esc_html__( 'Cannot add cities to the plugin options', 'wc-iikocloud' ) );

					return false;
				}
			}

		} else {
			Logs::add_error( esc_html__( 'Response does not contain cities', 'wc-iikocloud' ) );

			return false;
		}

		return $cities;
	}

	/**
	 * Get streets from iiko and save chosen organization's cities and their streets to the plugin options.
	 *
	 * @param ?string  $organization_id
	 * @param ?array  $chosen_city_ids
	 *
	 * @return array|bool
	 */
	public function get_streets( string $organization_id = null, array $chosen_city_ids = null ) {

		$access_token = $this->get_access_token();

		$url     = 'streets/by_city';
		$headers = [
			'Authorization' => $access_token,
		];

		// Take organization ID from settings if $organization_id is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id_export ) ) {
			$organization_id = $this->organization_id_export;
		}

		// Take city IDs from settings if $city_ids array is empty.
		if ( empty( $chosen_city_ids ) ) {
			$chosen_city_ids = $this->city_ids;
		}

		if ( false === $access_token || empty( $organization_id ) || empty( $chosen_city_ids ) ) {
			return false;
		}

		$i = 0;
		// [ 'uuid' => 'name', ... ]
		$all_cities       = wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'all_cities' ) );
		$imported_streets = [];

		foreach ( $chosen_city_ids as $chosen_city_id ) {

			$body = [
				'organizationId' => $organization_id,
				'cityId'         => $chosen_city_id,
			];

			$streets = HTTP_Request::remote_post( $url, $headers, $body );

			if ( ! empty( $streets['streets'] ) ) {

				// Clear from deleted cities.
				$streets = array_filter( $streets['streets'], function ( $street, $index ) {
					return $street['isDeleted'] === false;
				}, ARRAY_FILTER_USE_BOTH );

				// Create a simple array with only basic data for output on admin page and remove doubled IDs.
				// [ 'uuid' => 'name', ... ]
				$streets = array_column( $streets, 'name', 'id' );

				// Update city streets in plugin settings.
				$this->update_city_streets( $chosen_city_id, $streets );

				$imported_city                      = $all_cities[ $chosen_city_id ] ? $all_cities[ $chosen_city_id ] . ' - ' . $chosen_city_id : $i;
				$imported_streets[ $imported_city ] = $streets;

			} else {
				Logs::add_error( esc_html__( 'City ' . $all_cities[ $chosen_city_id ] . ' does not have streets', 'wc-iikocloud' ) );
			}

			$i ++;
		}

		// Update city streets in plugin settings.
		$this->update_chosen_cities_ids( $chosen_city_ids );

		return $imported_streets;
	}
}
