<?php

namespace WPWC\iikoCloud\Async_Actions;

defined( 'ABSPATH' ) || exit;

use WP_Async_Request;
use WPWC\iikoCloud\API_Requests\Export_API_Requests;
use WPWC\iikoCloud\Logs;

class Close_Order_Async extends WP_Async_Request {

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
	protected $action = WC_IIKOCLOUD_PREFIX . 'close_order';

	/**
	 * Close order during the async request.
	 */
	protected function handle() {

		sleep( 5 );

		$export_api_requests = new Export_API_Requests();
		$closed_order        = $export_api_requests->close_order( sanitize_key( $_POST['order_iiko_id'] ) );

		if ( isset( $closed_order['errorDescription'] ) ) {
			Logs::add_wc_log( $closed_order['error'] . ': ' . $closed_order['errorDescription'], 'close-order', 'error' );
		}
	}
}