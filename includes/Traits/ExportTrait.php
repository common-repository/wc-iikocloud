<?php

namespace WPWC\iikoCloud\Traits;

use WC_Order;
use WPWC\iikoCloud\Export\Export;

defined( 'ABSPATH' ) || exit;

trait ExportTrait {

	use WCActionsTrait;

	/**
	 * Export settings.
	 */
	protected static $export_settings;

	/**
	 * Get the plugin export settings.
	 *
	 * @return array
	 */
	protected static function get_export_settings(): array {

		self::$export_settings = is_array( self::$export_settings ) && ! empty( self::$export_settings )
			? self::$export_settings
			: get_option( WC_IIKOCLOUD_PREFIX . 'export' );

		$export_settings = self::$export_settings;

		return $export_settings ?: [];
	}

	/**
	 * Generate iiko ID.
	 *
	 * @param  $order
	 * @param string|null $iiko_delivery_id
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected static function generate_iiko_id( $order, ?string $iiko_delivery_id ): string {

		if ( empty( $iiko_delivery_id ) ) {

			$iiko_delivery_id = wp_generate_uuid4();

			$order->update_meta_data( WC_IIKOCLOUD_PREFIX . 'order_id', $iiko_delivery_id );
			$order->save();

			return $iiko_delivery_id;
		}

		return sanitize_key( $iiko_delivery_id );
	}


	/**
	 * Export order with a new ID to iiko.
	 *
	 * @param int $order_id
	 * @param WC_Order|null $order
	 * @param bool $reexport Reexport the order - use the saved iiko delivery ID.
	 *
	 * @return array|bool
	 */
	public function export_delivery_manually( int $order_id, ?WC_Order $order = null, bool $reexport = false ) {

		$order                                       = $order ?? wc_get_order( $order_id );
		$order_shipping_method                       = self::get_order_shipping_method( $order_id );
		$chosen_shipping_method_organization_iiko_id = self::get_iiko_shipping_method_param( 'iiko_organization_id', $order_shipping_method );
		$chosen_shipping_method_terminal_iiko_id     = self::get_iiko_shipping_method_param( 'iiko_terminal_id', $order_shipping_method );
		$export                                      = new Export();

		return $export->export_delivery(
			$order,
			$reexport,
			$chosen_shipping_method_organization_iiko_id,
			$chosen_shipping_method_terminal_iiko_id
		);
	}
}