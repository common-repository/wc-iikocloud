<?php

namespace WPWC\iikoCloud\Export;

defined( 'ABSPATH' ) || exit;

use WC_Order;
use WPWC\iikoCloud\API_Requests\Export_API_Requests;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\ExportTrait;
use WPWC\iikoCloud\Traits\WCActionsTrait;

class Export {

	use ExportTrait;
	use WCActionsTrait;

	/**
	 * @var null|string
	 */
	private ?string $online_payment_method;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->online_payment_method = sanitize_title( self::get_export_settings()['online_payment_method'] ) ?: null;

		/*
		 * Offline payments.
		 * export the delivery to iiko when an order are created.
		 */
		add_action( 'woocommerce_checkout_order_created', [ $this, 'checkout_order_created_export' ] );

		/*
		 * Online payment methods.
		 *
		 * Supports by:
		 * PayKeeper 'paykeeper', 'rbspayment' (Sberbank, Alfabank)
		 * AlfabankBY 'alfabankby'
		 * Cloud Payments 'wc_cloudpayments_gateway'
		*/
		if (
			'paykeeper' === $this->online_payment_method
			|| 'rbspayment' === $this->online_payment_method
			|| 'alfabankby' === $this->online_payment_method
			|| 'wc_cloudpayments_gateway' === $this->online_payment_method
		) {
			add_action( 'woocommerce_order_status_changed', [ $this, 'order_status_changed_export' ], 99, 4 );

			/*
			* Supports by YooKassa (?Robokassa).
			* Export the delivery to iiko when an order are paid.
			* woocommerce_payment_complete fired if an order has one of these statuses: on-hold, pending, failed, cancelled.
			*/
		} else {
			add_action( 'woocommerce_payment_complete', [ $this, 'payment_complete_export' ], 99, 2 );
		}

		if ( 'yes' === self::get_export_settings()['check_orders'] ) {
			add_action( WC_IIKOCLOUD_PREFIX . 'cron_export_order', [ $this, 'export_delivery_manually' ] );
		}

		// Deprecated
		// Old method (a customer may not return to the shop).
		// add_action( 'template_redirect', [ $this, 'online_payments_handler' ], 99 );
		// PayKeeper.
		// Add custom action to PayKeeper module - do_action( 'wc_iikocloud_paykeeper_handler', $_POST );
		// add_action( WC_IIKOCLOUD_PREFIX . 'paykeeper_handler', [ $this, 'paykeeper_handler' ], 10 );
	}

	/**
	 * Export the delivery for offline payment methods.
	 *
	 * @param WC_Order $order
	 *
	 * @return array|bool
	 * @throws \Exception
	 */
	public function checkout_order_created_export( WC_Order $order ) {

		return $order->get_payment_method() === $this->online_payment_method
			? false
			: $this->export_delivery( $order );
	}

	/**
	 * Export the delivery for online payment methods.
	 * The order status has to be 'processing'.
	 *
	 * @return array|bool
	 * @throws \Exception
	 */
	public function order_status_changed_export( $order_id, $status_transition_from, $status_transition_to, $order ) {

		if ( $order->get_status() !== 'processing' ) {
			return false;
		}

		$order_payment_method = $order->get_payment_method();
		$message              = sprintf( esc_html__( 'Order #%s has payment method: %s', 'wc-iikocloud' ), $order_id, $order_payment_method );

		Logs::add_wc_log( $message, 'create-delivery' );

		return $order_payment_method === $this->online_payment_method
			? $this->export_delivery( $order )
			: false;
	}

	/**
	 * Export the delivery for online payment methods.
	 *
	 * @return array|bool
	 * @throws \Exception
	 */
	public function payment_complete_export( $order_id, $transaction_id ) {

		if ( ! $order = wc_get_order( $order_id ) ) {
			Logs::add_wc_log( 'Order could not found', 'create-delivery', 'error' );

			return false;
		}

		$order_payment_method = $order->get_payment_method();
		$message              = sprintf( esc_html__( 'Order #%s has payment method: %s', 'wc-iikocloud' ), $order_id, $order_payment_method );

		Logs::add_wc_log( $message, 'create-delivery' );

		return $order_payment_method === $this->online_payment_method
			? $this->export_delivery( $order )
			: false;
	}

	/**
	 * Export order process.
	 *
	 * @param WC_Order $WC_order Order object.
	 * @param bool $use_saved_iiko_id Use the saved iiko delivery ID.
	 * @param string|null $organization_id
	 * @param string|null $terminal_id
	 *
	 * @return array|bool
	 * @throws \Exception
	 */
	public function export_delivery(
		WC_Order $WC_order,
		bool $use_saved_iiko_id = false,
		?string $organization_id = null,
		?string $terminal_id = null
	) {

		$order_id                       = $WC_order->get_id();
		$export_type                    = sanitize_title( self::get_export_settings()['type'] );
		$is_orders_export_shipping_type = 'yes' === self::get_iiko_shipping_method_param(
				'export_orders',
				self::get_order_shipping_method( $order_id )
			);

		Logs::send_to_telegram_bot( sprintf( esc_html__( 'New order #%s.', 'wc-iikocloud' ), $order_id ) );

		$iiko_delivery_id = $use_saved_iiko_id ? self::get_iiko_order_meta_id( $order_id, 'order_id' ) : null;

		// Create iiko delivery/order.
		switch ( $export_type ) {

			case 'deliveries':
				$export_order = new Delivery( $order_id, $iiko_delivery_id );
				break;

			case 'orders':
				$export_order = new Order( $order_id, $iiko_delivery_id );
				break;

			case 'both':
				$export_order = $is_orders_export_shipping_type
					? new Order( $order_id, $iiko_delivery_id )
					: new Delivery( $order_id, $iiko_delivery_id );
				break;

			case 'none':
				return false;

			default:
				Logs::add_wc_log( 'Export type is incorrect', 'create-delivery', 'error' );

				return false;
		}

		// Add iiko delivery ID to WooCommerce order notice.
		$export_type_label = $is_orders_export_shipping_type ? 'ORDER' : 'DELIVERY';

		$WC_order->add_order_note( 'Iiko ' . $export_type_label . ' ID: ' . $export_order->get_id() );
		$WC_order->save();

		// Export the delivery to iiko.
		$export_api_requests = new Export_API_Requests();

		return $export_api_requests->create_delivery_in_iiko(
			$export_order,
			$export_type,
			$is_orders_export_shipping_type,
			$WC_order,
			$order_id,
			$organization_id,
			$terminal_id
		);
	}

	/**
	 * Deprecated.
	 */
	public function online_payments_handler() {

		// If the action is fired only the first time.
		if ( did_action( 'template_redirect' ) >= 2 ) {
			return false;
		}

		// Skip PayKeeper.
		if ( 'paykeeper' === $this->online_payment_method ) {
			return false;
		}

		// is_wc_endpoint_url( 'order-received' )
		if ( ! is_order_received_page() ) {
			return false;
		}

		if ( ! $order_key = sanitize_key( $_GET['key'] ) ) {
			Logs::add_wc_log( 'Order key is empty', 'create-delivery', 'error' );

			return false;
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );

		// if ( ! ( $order instanceof WC_Order ) )
		if ( ! $order = wc_get_order( $order_id ) ) {
			Logs::add_wc_log( 'Order could not found', 'create-delivery', 'error' );

			return false;
		}

		return $order->get_payment_method() === $this->online_payment_method
			? $this->export_delivery( $order )
			: false;
	}

	/**
	 * Deprecated.
	 */
	public function paykeeper_handler() {

		if ( ! $order_id = absint( $_POST['orderid'] ) ) {
			Logs::add_wc_log( 'PayKeeper: order ID is empty', 'create-delivery', 'error' );

			return false;
		}

		if ( ! $order = wc_get_order( $order_id ) ) {
			return false;
		}

		return (
			       $order->get_payment_method() === $this->online_payment_method )
		       && ( $order->has_status( 'processing' ) || $order->has_status( 'completed' )
		       )
			? $this->export_delivery( $order )
			: false;
	}
}