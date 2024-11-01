<?php

namespace WPWC\iikoCloud\Async_Actions;

defined( 'ABSPATH' ) || exit;

use WP_Async_Request;
use WPWC\iikoCloud\API_Requests\Export_API_Requests;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\ExportTrait;

class Check_Order_Async extends WP_Async_Request {

	use ExportTrait;

	/**
	 * Prefix
	 *
	 * @var string
	 */
	protected $prefix = WC_IIKOCLOUD_PREFIX;

	/**
	 * Action
	 *
	 * @var string
	 */
	protected $action = WC_IIKOCLOUD_PREFIX . 'check_order';

	/**
	 * Close order during the async request.
	 */
	protected function handle() {

		sleep( 60 );

		$iiko_delivery_id    = sanitize_key( $_POST['iiko_delivery_id'] );
		$order_id            = sanitize_key( $_POST['order_id'] );
		$organization_id     = sanitize_key( $_POST['organization_id'] );
		$export_api_requests = new Export_API_Requests();
		$order_status        = $export_api_requests->retrieve_delivery_by_id(
			$iiko_delivery_id,
			$order_id,
			$organization_id
		);

		if ( ! $order = wc_get_order( $order_id ) ) {
			return;
		}

		$error_subject = sprintf( esc_html__( 'Iiko delivery creation error. Order %s', 'wc-iikocloud' ), $order_id );

		// Code 200.
		if ( 200 === $order_status['response_code'] ) {

			// Enum: "Success" "InProgress" "Error"
			// Order creation status.
			// In case of asynchronous creation, it allows to track the instance an order was validated/created in iikoFront.
			$creation_status = $order_status['orders'][0]['creationStatus'];

			$order->add_order_note( sprintf( esc_html__( 'iiko creation status is %s. ID: %s', 'wc-iikocloud' ), $creation_status, $iiko_delivery_id ) );

			switch ( $creation_status ) {

				case 'Success':
					break;

				case 'InProgress':
					$this->data( [
						'iiko_delivery_id' => $iiko_delivery_id,
						'order_id'         => $order_id,
						'organization_id'  => $organization_id,
					] )->dispatch();

					break;

				case 'Error':
					$error = wc_clean( $order_status['orders'][0]['errorInfo'] );

					// Duplicated order ID error.
					if ( 'DuplicatedOrderId' === $error['code'] || 192 == $error['code'] ) {
						Logs::add_wc_log( "Order #$order_id has duplicated iiko ID", 'check-order' );

						$this->log_errors( $error, $order, $error_subject );
						$order->update_status( 'failed' );

						break;
					}

					// Creation timeout expired error.
					if ( 'Creation timeout expired, order automatically transited to error creation status' === $error['description'] ) {

						Logs::add_wc_log( "Order #$order_id creation timeout expired", 'check-order' );

						$working_hours = wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'local' ) );
						$opening_hour  = isset( $working_hours['opening_hour'] ) ? absint( $working_hours['opening_hour'] ) : false;
						$closing_hour  = isset( $working_hours['closing_hour'] ) ? absint( $working_hours['closing_hour'] ) : false;

						if ( ! $opening_hour || ! $closing_hour ) {
							Logs::add_wc_log( 'Check opening and closing hours in the plugin settings (tab General)', 'check-order', 'error' );

							$this->log_errors( $error, $order, $error_subject );
							$order->update_status( 'failed' );

							break;
						}

						$timezone = sanitize_text_field( get_option( WC_IIKOCLOUD_PREFIX . 'timezone' ) );
						$timezone = ! empty( $timezone ) ? $timezone : 'Europe/Moscow';

						date_default_timezone_set( $timezone );

						$current_hour  = absint( date( 'G', time() ) );
						$current_hour  = 0 === $current_hour ? 24 : $current_hour;
						$working_hours = $closing_hour > $opening_hour
							? range( $opening_hour, $closing_hour - 1 )
							: array_merge( range( $opening_hour, 24 ), range( 1, $closing_hour - 1 ) );

						// If is a working hour now - try to export the order every 2 minutes.
						if ( in_array( $current_hour, $working_hours ) ) {

							Logs::add_wc_log( 'Added async task to reexport the order', 'check-order' );

							sleep( 60 );

							$this->export_delivery_manually( $order_id, $order );

							// If is a non-working hour - create WP Cron task to export the order on opening hour.
						} else {

							if ( ! wp_next_scheduled( WC_IIKOCLOUD_PREFIX . 'cron_export_order', [ $order_id ] ) ) {

								Logs::add_wc_log( 'Added WP Cron task to reexport the order', 'check-order' );

								$current_hour     = (int) date( 'G', time() );
								$opening_time_obj = new \DateTime( date( 'Y-m-d H:i:s', strtotime( date( 'Y-m-d' ) . ' ' . $opening_hour . ':00:00' ) ) );

								if ( $current_hour >= $opening_hour ) {
									$opening_time_obj = $opening_time_obj->modify( '+1 day' );
								}

								wp_schedule_single_event( $opening_time_obj->getTimestamp(), WC_IIKOCLOUD_PREFIX . 'cron_export_order', [ $order_id ] );
							}
						}

						break;
					}

					// Other errors.
					$this->log_errors( $error, $order, $error_subject );
					$order->update_status( 'failed' );

					break;
			}

			// Codes 400, 401, 403, 408, 500.
		} else {

			$failure_states = [
				400 => 'Bad Request (400)',
				401 => 'Unauthorized (401)',
				403 => 'Forbidden (403)',
				408 => 'Request Timeout (408)',
				500 => 'Server Error (500)',
			];

			$error['state']       = $failure_states[ $order_status['response_code'] ] ?: '';
			$error['error']       = sanitize_text_field( $order_status['orders'][0]['error'] ) ?: '';
			$error['description'] = sanitize_text_field( $order_status['orders'][0]['errorDescription'] ) ?: '';

			$this->log_errors( $error, $order, $error_subject );
			$order->update_status( 'failed' );
		}

		$order->save();
	}

	/**
	 * Add errors information to the order notes, WooCommerce logs and send to email.
	 *
	 * @param array $error
	 * @param $order
	 * @param string $error_subject
	 *
	 * @return void
	 */
	protected function log_errors( array $error, $order, string $error_subject ): void {

		foreach ( $error as $error_key => $error_val ) {
			if ( ! empty( $error_val ) ) {
				$order->add_order_note( "Error $error_key: $error_val" );
			}
		}

		$error_message = implode( PHP_EOL, $error );

		Logs::add_wc_log( $error_subject, 'check-order', 'error' );
		Logs::add_wc_log( $error_message, 'check-order', 'error' );
		Logs::send_email( $error_subject, $error_message );
		Logs::send_to_telegram_bot( $error_subject );
		Logs::send_to_telegram_bot( $error_message );
	}
}