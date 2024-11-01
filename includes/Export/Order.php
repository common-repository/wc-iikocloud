<?php

namespace WPWC\iikoCloud\Export;

defined( 'ABSPATH' ) || exit;

use JsonSerializable;
use WPWC\iikoCloud\Traits\ExportTrait;

class Order extends Delivery implements JsonSerializable {

	use ExportTrait;

	/**
	 * @var array|null tableIds
	 * Array of strings <uuid>
	 * Nullable
	 * Table IDs.
	 * Can be obtained by /api/1/reserve/available_restaurant_sections operation.
	 */
	protected $table_ids;

	/**
	 * @var string|null tabName
	 * string
	 * Nullable
	 * Tab name (only for fastfood terminals group in tab mode).
	 * Allowed from version 7.6.1.
	 */
	protected ?string $tab_name = null;

	/**
	 * @var string|null menuId
	 * string
	 * Nullable
	 * External menu ID.
	 */
	protected ?string $menu_id = null;

	/**
	 * @var array|null chequeAdditionalInfo
	 * object
	 * Nullable
	 * Cheque additional information.
	 */
	protected ?array $cheque_additional_info = null;

	/**
	 * @var array|null externalData
	 * Array of objects (iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.ExternalData)
	 * Nullable
	 * Order external data.
	 * Allowed from version 8.0.6.
	 */
	protected ?array $external_data = null;

	/**
	 * Constructor.
	 *
	 * @param string $order_id
	 * @param string|null $iiko_order_id
	 *
	 * @throws \Exception
	 */
	public function __construct( string $order_id, string $iiko_order_id = null ) {

		parent::__construct( $order_id, $iiko_order_id );

		$this->table_ids              = apply_filters( WC_IIKOCLOUD_PREFIX . 'order_table_ids', null, $order_id );
		$this->customer['comment']    = $this->comment;
		$this->phone                  = $this->phone ?: null;
		$this->cheque_additional_info = apply_filters( WC_IIKOCLOUD_PREFIX . 'order_cheque_additional_info', $this->cheque_additional_info(), $order_id );
	}

	/**
	 * Cheque additional info.
	 *
	 * @return ?array
	 */
	private function cheque_additional_info(): ?array {

		if ( 'yes' === self::get_export_settings()['print_receipt'] ) {
			return [
				// needReceipt
				// Required.
				// boolean
				// Whether paper cheque should be printed.
				'needReceipt'     => true,

				// email
				// string [ 0 .. 255 ] characters
				// Nullable
				// Email to send cheque information or null if the cheque shouldn't be sent by email.
				'email'           => null,

				// settlementPlace
				// string [ 0 .. 500 ] characters
				// Nullable
				// Settlement place.
				'settlementPlace' => null,

				// phone
				// string [ 8 .. 40 ] characters
				// Nullable
				// Phone to send cheque information (by sms) or null if the cheque shouldn't be sent by sms.
				'phone'           => null,
			];

		} else {
			return null;
		}
	}

	/**
	 * Return JSON object representation.
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id'                   => $this->id,
			'externalNumber'       => $this->externalNumber,
			'tableIds'             => $this->table_ids,
			'customer'             => $this->customer,
			'phone'                => $this->phone,
			'guests'               => $this->guests,
			'tabName'              => $this->tab_name,
			'menuId'               => $this->menu_id,
			'items'                => $this->items,
			'combos'               => $this->combos,
			'payments'             => $this->payments,
			'tips'                 => $this->tips,
			'sourceKey'            => $this->source_key,
			'discountsInfo'        => $this->discounts_info,
			'loyaltyInfo'          => $this->loyalty_info,
			'orderTypeId'          => $this->order_type_id,
			'chequeAdditionalInfo' => $this->cheque_additional_info,
			'externalData'         => $this->external_data,
		];
	}
}
