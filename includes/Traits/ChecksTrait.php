<?php

namespace WPWC\iikoCloud\Traits;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\Logs;

trait ChecksTrait {

	/**
	 * Check remote response for AJAX request.
	 * Log error, echo error in the plugin terminal and stop script if the response is false.
	 *
	 * @param $response
	 * @param  string  $title  Title for error message
	 */
	protected function check_ajax_response( $response, $title ) {

		if ( false === $response ) {
			Logs::add_error( sprintf( esc_html__( '%s request failed', 'wc-iikocloud' ), $title ) );
			wp_send_json_error( Logs::get_logs() );
		}
	}

	/**
	 * Check the plugin page admin AJAX referer.
	 */
	protected function check_plugin_ajax_referer(): void {
		check_ajax_referer( WC_IIKOCLOUD_PREFIX . 'action', WC_IIKOCLOUD_PREFIX . 'nonce' );
	}

	/**
	 * Check delivery zones public (checkout and shortcode pages) AJAX referer.
	 */
	private static function check_dz_ajax_referer(): void {
		check_ajax_referer( WC_IIKOCLOUD_PREFIX . 'dz_action', WC_IIKOCLOUD_PREFIX . 'dz_nonce' );
	}

	/**
	 * Check WC checkout public AJAX referer.
	 */
	protected function check_wc_checkout_ajax_referer(): void {
		check_ajax_referer( 'woocommerce-process_checkout', 'woocommerce_nonce' );
	}

	/**
	 * Check AJAX parameter.
	 * Log error, echo error in the plugin terminal and stop script if the parameter is empty.
	 *
	 * @param $parameter
	 * @param  string  $title  Title for error message
	 */
	protected function check_ajax_parameter( $parameter, $title ) {
		if ( empty( $parameter ) ) {
			Logs::add_error( sprintf( esc_html__( '%s empty', 'wc-iikocloud' ), $title ) );
			wp_send_json_error( Logs::get_logs() );
		}
	}

	/**
	 * Check ID parameter.
	 *
	 * @param  mixed  $id
	 * @param  string  $title  Title for error message
	 *
	 * @return false|string False if the ID is required and empty. Sanitized ID otherwise.
	 */
	protected function sanitize_required_id( $id, string $title ) {

		$id = sanitize_key( $id );

		if ( empty( $id ) ) {
			Logs::add_error( sprintf( esc_html__( '%s ID is empty', 'wc-iikocloud' ), $title ) );

			return false;
		}

		return $id;
	}

	/**
	 * Check name parameter.
	 */
	protected function sanitize_and_check_name( $name, $title, $required = true ) {

		$name = sanitize_text_field( $name );

		if ( empty( $name ) && $required ) {
			Logs::add_error( sprintf( esc_html__( '%s name is empty', 'wc-iikocloud' ), $title ) );

			return false;
		}

		return $name;
	}

	/**
	 * Check array.
	 *
	 * @param  mixed  $array  Array to check.
	 * @param  string  $message  Message to log.
	 * @param  string  $level  Log level.
	 * @param  bool  $add_plugin_log  Add plugin log.
	 * @param  bool  $add_wc_notice  Add WooCommerce notice.
	 * @param  bool  $add_wc_log  Add WooCommerce log.
	 *
	 * @return array|false Array if $array is an array, and it is not empty. False otherwise and logs the error.
	 */
	protected static function is_empty_array(
		$array,
		string $message,
		string $level = 'error',
		bool $add_plugin_log = true,
		bool $add_wc_notice = false,
		bool $add_wc_log = true
	) {

		if ( ! is_array( $array ) || empty( $array ) ) {

			if ( ! empty( $message ) ) {

				if ( $add_plugin_log ) {
					if ( 'error' === $level ) {
						Logs::add_error( $message );
					} else {
						Logs::add_notice( $message );
					}
				}

				if ( $add_wc_notice ) {
					wc_add_notice( $message, $level );
				}

				if ( $add_wc_log ) {
					Logs::add_wc_log( $message, 'check-data', $level );
				}
			}

			return false;
		}

		return $array;
	}

	/**
	 * Check object.
	 *
	 * @param $object
	 * @param $message
	 *
	 * @return bool
	 */
	protected function is_empty_object( $object, $message ): bool {

		if ( ! is_object( $object ) || empty( $object ) ) {
			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'check-data', 'error' );

			return false;
		}

		return true;
	}

	/**
	 * Sanitize and check if empty each array ID.
	 *
	 * TODO - compare with wc_clean()
	 *
	 * @param  array  $items
	 * @param  string  $message
	 *
	 * @return array
	 */
	protected function sanitize_ids( array $items, string $message ): array {

		$prepared_ids = [];

		foreach ( $items as $item ) {

			if ( empty( $item ) ) {
				continue;
			}

			if ( is_array( $item ) ) {

				$id = $this->sanitize_required_id( $item['id'], $message );

				if ( ! empty( $id ) ) {
					$prepared_ids[ $id ] = sanitize_text_field( $item['name'] );
				}

			} else {

				$id = $this->sanitize_required_id( $item, $message );

				if ( ! empty( $id ) ) {
					$prepared_ids[] = $id;
				}
			}
		}

		return $prepared_ids;
	}
}
