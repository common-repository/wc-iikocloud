<?php

namespace WPWC\iikoCloud\API_Requests;

defined( 'ABSPATH' ) || exit;

use WC_Order;
use WPWC\iikoCloud\Async_Actions\Check_Order_Async;
use WPWC\iikoCloud\Async_Actions\Close_Order_Async;
use WPWC\iikoCloud\HTTP_Request;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\ExportTrait;

class Export_API_Requests extends Common_API_Requests {

	use ExportTrait;

	/**
	 * Export WooCommerce deliveries/orders to iiko.
	 *
	 * @param $export_order
	 * @param string $export_type
	 * @param bool $is_orders_export_shipping_type
	 * @param WC_Order $WC_order Order object.
	 * @param int $order_id
	 * @param string|null $organization_id
	 * @param string|null $terminal_id
	 *
	 * @return bool|array
	 * @throws \Exception
	 */
	public function create_delivery_in_iiko(
		$export_order,
		string $export_type,
		bool $is_orders_export_shipping_type,
		WC_Order $WC_order,
		int $order_id,
		?string $organization_id = null,
		?string $terminal_id = null
	) {

		$access_token = $this->get_access_token();

		if ( false === $access_token
		     || empty( $export_order )
		     || false === $this->is_empty_object( $export_order, esc_html__( 'Order is empty', 'wc-iikocloud' ) )
		) {
			return false;
		}

		$chosen_shipping_method_organization_iiko_id = sanitize_key( self::get_iiko_shipping_method_param( 'iiko_organization_id' ) );
		$chosen_shipping_method_terminal_iiko_id     = sanitize_key( self::get_iiko_shipping_method_param( 'iiko_terminal_id' ) );

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) ) {
			if ( ! empty( $chosen_shipping_method_organization_iiko_id ) ) {
				$organization_id = $chosen_shipping_method_organization_iiko_id;

			} elseif ( ! empty( $this->organization_id_export ) ) {
				$organization_id = $this->organization_id_export;

			} else {
				Logs::add_wc_log( 'There is not an organization ID', 'create-delivery', 'error' );

				return false;
			}
		}

		// Take terminal ID from settings if parameter is empty.
		if ( empty( $terminal_id ) ) {
			if ( ! empty( $chosen_shipping_method_terminal_iiko_id ) ) {
				$terminal_id = $chosen_shipping_method_terminal_iiko_id;

			} elseif ( ! empty( $this->terminal_id ) ) {
				$terminal_id = $this->terminal_id;

			} else {
				Logs::add_wc_log( 'There is not an terminal ID', 'create-delivery', 'error' );

				return false;
			}
		}

		switch ( $export_type ) {

			case 'deliveries':
				$url = 'deliveries/create';
				break;

			case 'orders':
				$url = 'order/create';
				break;

			case 'both':
				$url = 'yes' === self::get_iiko_shipping_method_param( 'export_orders' )
					? 'order/create'
					: 'deliveries/create';
				break;

			case 'none':
				return false;

			default:
				Logs::add_wc_log( 'Export type is incorrect', 'create-delivery', 'error' );

				return false;
		}

		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [
			'organizationId'  => $organization_id,
			'terminalGroupId' => apply_filters( WC_IIKOCLOUD_PREFIX . 'order_export_terminal_id', $terminal_id, $order_id ),
			'order'           => $export_order,
		];

		$created_delivery = HTTP_Request::remote_post( $url, $headers, $body );

		// Add action to handle iiko response.
		do_action( WC_IIKOCLOUD_PREFIX . 'created_delivery', $created_delivery, $order_id, $organization_id );

		if ( false === $created_delivery ) {
			$error_subject = sprintf( esc_html__( 'Iiko export error. Order %s', 'wc-iikocloud' ), $order_id );
			$error_message = wp_kses_post( sprintf( esc_html__( 'Check %sWooCommerce logs%s on your website.', 'wc-iikocloud' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '">',
				'</a>'
			) );

			Logs::add_wc_log( $error_subject, 'create-delivery', 'error' );
			Logs::send_email( $error_subject, $error_message );

			$WC_order->update_status( 'failed', sprintf( esc_html__( '%s Check WooCommerce logs on your website.', 'wc-iikocloud' ), $error_subject ) );

			return false;
		}

		// Check order async.
		if (
			'yes' === self::get_export_settings()['check_orders']
			&& isset( $created_delivery['orderInfo']['id'] )
		) {
			$check_order_async = new Check_Order_Async();

			$check_order_async->data( [
				'iiko_delivery_id' => $created_delivery['orderInfo']['id'],
				'order_id'         => $order_id,
				'organization_id'  => $organization_id,
			] )->dispatch();
		}

		// Close order async.
		if (
			'yes' === self::get_export_settings()['close_orders']
			&& ( 'orders' === $export_type || ( 'both' === $export_type && $is_orders_export_shipping_type ) )
		) {
			$close_order_async = new Close_Order_Async();

			$close_order_async->data( [ 'order_iiko_id' => $export_order->get_id() ] )->dispatch();
		}

		// Save correlationId into order meta for debugging.
		if (
			isset( $created_delivery['correlationId'] )
			&& ( $order = wc_get_order( $order_id ) )
		) {
			$order->update_meta_data( WC_IIKOCLOUD_PREFIX . 'order_correlation_id', sanitize_key( $created_delivery['correlationId'] ) );
			$order->save();
		}

		return $created_delivery;
	}

	/**
	 * Close order in iiko.
	 *
	 * @param $order_id
	 * @param string|null $organization_id
	 *
	 * @return boolean|array
	 */
	public function close_order( $order_id, ?string $organization_id = null ) {

		$access_token = $this->get_access_token();

		if ( false === $access_token || false === $this->sanitize_required_id( $order_id, esc_html__( 'Order', 'wc-iikocloud' ) ) ) {
			return false;
		}

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id_export ) ) {
			$organization_id = $this->organization_id_export;

		} else {
			Logs::add_wc_log( 'There is not an organization ID in the plugin settings', 'create-delivery', 'error' );

			return false;
		}

		$url     = 'order/close';
		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [
			'organizationId' => $organization_id,
			'orderId'        => $order_id,
		];

		return HTTP_Request::remote_post( $url, $headers, $body );
	}

	/**
	 * Retrieve iiko order by ID.
	 *
	 * @param string $iiko_delivery_id
	 * @param string $order_id
	 * @param string|null $organization_id
	 *
	 * @return boolean|array
	 */
	public function retrieve_delivery_by_id(
		string $iiko_delivery_id,
		string $order_id,
		string $organization_id = null
	) {

		$access_token = $this->get_access_token();

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id_export ) ) {
			$organization_id = $this->organization_id_export;
		}

		if ( false === $access_token || empty( $organization_id ) || empty( $iiko_delivery_id ) ) {
			return false;
		}

		$url_delivery  = 'deliveries/by_id';
		$url_order     = 'order/by_id';
		$body_delivery = [
			'organizationId' => $this->organization_id_export,
			'orderIds'       => [ $iiko_delivery_id ],
		];
		$body_order    = [
			'organizationIds' => [ $this->organization_id_export ],
			'orderIds'        => [ $iiko_delivery_id ],
		];
		$headers       = [
			'Authorization' => $access_token,
		];

		switch ( self::get_export_settings()['type'] ) {

			case 'deliveries':
				$url  = $url_delivery;
				$body = $body_delivery;
				break;

			case 'orders':
				$url  = $url_order;
				$body = $body_order;
				break;

			case 'both':
				$is_orders_export_shipping_type = 'yes' === self::get_iiko_shipping_method_param(
						'export_orders',
						self::get_order_shipping_method( $order_id )
					);

				$url  = $is_orders_export_shipping_type ? $url_order : $url_delivery;
				$body = $is_orders_export_shipping_type ? $body_order : $body_delivery;
				break;

			case 'none':
				return false;

			default:
				Logs::add_wc_log( 'Export type is incorrect', 'check-delivery', 'error' );

				return false;
		}

		return HTTP_Request::remote_post( $url, $headers, $body );
	}
}