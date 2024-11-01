<?php

namespace WPWC\iikoCloud\Admin;

defined( 'ABSPATH' ) || exit;

class Orders {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_admin_order_actions', [ $this, 'export_order_to_iiko_button' ], 10, 2 );
		add_filter( 'woocommerce_admin_order_actions', [ $this, 'check_created_delivery_from_iiko_button' ], 10, 2 );
	}

	/**
	 * Export button in admin orders list.
	 *
	 * @param $actions
	 * @param $order
	 *
	 * @return mixed
	 */
	function export_order_to_iiko_button( $actions, $order ) {

		if ( ! $order->has_status( [ 'completed' ] ) ) {

			$status   = method_exists( $order, 'get_status' ) ? $order->get_status() : $order->status;
			$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

			$actions['wc-iikocloud-export-order'] = [
				'url'    => wp_nonce_url( admin_url(
					'admin-ajax.php?action=wc_iikocloud_export_order&status=' . $status . '&order_id=' . $order_id ),
					'wc-iikocloud-export-order'
				),
				'name'   => esc_attr__( 'Export order to iiko', 'wc-iikocloud' ),
				'title'  => esc_attr__( 'Export order to iiko', 'wc-iikocloud' ),
				'action' => 'wc-iikocloud-export-order',
			];
		}

		return $actions;
	}

	/**
	 * Check created delivery button in admin orders list.
	 *
	 * @param $actions
	 * @param $order
	 *
	 * @return mixed
	 */
	function check_created_delivery_from_iiko_button( $actions, $order ) {

		$status   = method_exists( $order, 'get_status' ) ? $order->get_status() : $order->status;
		$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

		$actions['wc-iikocloud-check-created-delivery'] = [
			'url'    => wp_nonce_url( admin_url(
				'admin-ajax.php?action=wc_iikocloud_check_created_delivery&status=' . $status . '&order_id=' . $order_id ),
				'wc-iikocloud-check-created-delivery'
			),
			'name'   => esc_attr__( 'Check created delivery from iiko', 'wc-iikocloud' ),
			'title'  => esc_attr__( 'Check created delivery from iiko', 'wc-iikocloud' ),
			'action' => 'wc-iikocloud-check-created-delivery',
		];

		return $actions;
	}
}
