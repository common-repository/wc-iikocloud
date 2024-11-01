<?php

namespace WPWC\iikoCloud\Export;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\API_Requests\Export_API_Requests;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\ExportTrait;
use WPWC\iikoCloud\Traits\WCActionsTrait;

class Manual_Order_Actions {

	use ExportTrait;
	use WCActionsTrait;

	/**
	 * Export order manually (from the orders list in admin area).
	 * Uses with the action 'wp_ajax_wc_iikocloud_export_order' (class Admin).
	 *
	 * @throws \Exception
	 */
	public function export_order_manually() {

		$this->print_styles();
		$this->print_wrapper_start();
		$this->print_back_button();

		if (
			current_user_can( 'manage_woocommerce' )
			&& check_admin_referer( 'wc-iikocloud-export-order' )
			&& isset( $_GET['status'], $_GET['order_id'] )
		) {
			// $status = wc_clean( wp_unslash( $_GET['status'] ) );
			$order_id = absint( wp_unslash( $_GET['order_id'] ) );

			if ( ! $order = wc_get_order( $order_id ) ) {
				echo 'Order not found.';
			}

			$exported_order = $this->export_delivery_manually( $order_id, $order, true );

			$this->print_response( $exported_order, $order_id );

		} else {
			echo 'You are not allowed to see this page.';
		}

		$this->print_back_button();
		$this->print_wrapper_end();

		wp_die();
	}

	/**
	 * Check created delivery (from the orders list in admin area).
	 * Uses with the action 'wp_ajax_wc_iikocloud_check_created_delivery' (class Admin).
	 *
	 * @throws \Exception
	 */
	public function check_created_delivery_manually() {

		$this->print_styles();
		$this->print_wrapper_start();
		$this->print_back_button();

		if (
			current_user_can( 'manage_woocommerce' )
			&& check_admin_referer( 'wc-iikocloud-check-created-delivery' )
			&& isset( $_GET['order_id'] )
		) {
			$order_id         = absint( wp_unslash( $_GET['order_id'] ) );
			$iiko_delivery_id = self::get_iiko_order_meta_id( $order_id, 'order_id' );

			if ( ! empty( $iiko_delivery_id ) ) {
				$export_api_requests = new Export_API_Requests();

				$this->print_response(
					$export_api_requests->retrieve_delivery_by_id( $iiko_delivery_id, $order_id ),
					$order_id
				);

			} else {
				$message = sprintf( esc_html__( 'Order #%s does not have iiko ID', 'wc-iikocloud' ),
					$order_id
				);

				Logs::add_wc_log( $message, 'check-delivery', 'error' );
				echo sanitize_text_field( $message );
			}

		} else {
			echo 'You are not allowed to see this page.';
		}

		$this->print_back_button();
		$this->print_wrapper_end();

		wp_die();
	}

	/**
	 * Print styles.
	 */
	private function print_styles() {

		echo '<style>
			.wpwc-wrapper {
				padding: 10px 30px;
			}
			
            .wpwc-back-btn a {
                display: inline-block;
			    text-decoration: none;
			    font-size: 13px;
			    font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
			    line-height: 2.15384615;
			    min-height: 30px;
			    margin: 0;
			    padding: 0 10px;
			    cursor: pointer;
			    border-width: 1px;
			    border-style: solid;
			    border-radius: 3px;
			    white-space: nowrap;
			    box-sizing: border-box;
			    background: #2271b1;
			    border-color: #2271b1;
			    color: #fff;
			    text-shadow: none;
			    vertical-align: baseline;
            }
            
            .wpwc-back-btn a:hover {
                background: #135e96;
    			border-color: #135e96;
            }
            
            .wpwc-wrapper hr {
				max-width: 480px;
    			margin: initial;
			}
            </style>';
	}

	/**
	 * Print wrapper opening tag.
	 */
	private function print_wrapper_start() {
		echo '<div class="wpwc-wrapper">';
	}

	/**
	 * Print back button.
	 */
	private function print_back_button() {
		echo '<p class="wpwc-back-btn"><a href="' . esc_url( admin_url( 'edit.php?post_type=shop_order' ) ) . '"><strong>';
		echo esc_html_x( 'Back to the orders list', 'Button', 'wc-iikocloud' );
		echo '</strong></a></p>';
	}

	/**
	 * Print response.
	 *
	 * @param $data
	 * @param string $order_id
	 *
	 * @throws \Exception
	 */
	private function print_response( $data, string $order_id ) {
		echo '<hr/>';
		echo '<p><strong>WooCommerce order ID:</strong> ' . absint( $order_id ) . '</p>';
		echo '<p><strong>iiko delivery ID:</strong> ' . sanitize_key( self::get_iiko_order_meta_id( $order_id, 'order_id' ) ) . '</p>';
		echo '<p><strong>iiko delivery correlation ID:</strong> ' . sanitize_key( self::get_iiko_order_meta_id( $order_id, 'order_correlation_id' ) ) . '</p>';
		echo '<hr/>';
		echo '<pre style="white-space: pre-wrap;">';
		echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		echo '</pre>';
	}

	/**
	 * Print wrapper closing tag.
	 */
	private function print_wrapper_end() {
		echo '</div>';
	}
}