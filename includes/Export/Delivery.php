<?php

namespace WPWC\iikoCloud\Export;

defined( 'ABSPATH' ) || exit;

use JsonSerializable;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\CommonTrait;
use WPWC\iikoCloud\Traits\ExportTrait;
use WPWC\iikoCloud\Traits\WCActionsTrait;

class Delivery implements JsonSerializable {

	use CommonTrait;
	use ExportTrait;
	use WCActionsTrait;

	/**
	 * @var string|null id
	 * string <uuid>
	 * Nullable
	 * Order ID. Must be unique.
	 * If sent null, it generates automatically on iikoTransport side.
	 */
	protected ?string $id;

	/**
	 * @var string|null externalNumber
	 * string [ 0 .. 50 ] characters
	 * Nullable
	 * Order external number.
	 * Allowed from version 8.0.6.
	 */
	protected ?string $externalNumber;

	/**
	 * @var string|null completeBefore
	 * string <yyyy-MM-dd HH:mm:ss.fff>
	 * Nullable
	 * Order fulfillment date.
	 * Date and time must be local for delivery terminal, without time zone (take a look at example).
	 * If null, order is urgent and time is calculated based on customer settings, i.e. the shortest delivery time possible.
	 * Permissible values: from current day and 60 days on.
	 */
	private ?string $complete_before;

	/**
	 * @var string|null phone
	 * ### Required for deliveries ###
	 * ### NOT required for orders ###
	 * string [ 8 .. 40 ] characters
	 * Nullable
	 * Telephone number.
	 * Must begin with symbol "+" and must be at least 8 digits.
	 */
	protected ?string $phone;

	/**
	 * @var string|null orderTypeId
	 * string <uuid>
	 * Nullable
	 * Order type ID.
	 * Can be obtained by /api/1/deliveries/order_types operation.
	 * One of the fields required: orderTypeId or orderServiceType.
	 */
	protected ?string $order_type_id = null;

	/**
	 * @var string|null orderServiceType
	 * string
	 * Nullable
	 * Enum: "DeliveryByCourier" "DeliveryByClient"
	 * Order service type.
	 * One of the fields required: orderTypeId or orderServiceType.
	 * Allowed from version 7.0.3.
	 */
	private ?string $order_service_type;

	/**
	 * @var array|null deliveryPoint
	 * object
	 * Nullable
	 * Delivery point details.
	 * Not required in case of customer pickup. Otherwise, required.
	 */
	private ?array $delivery_point;

	/**
	 * @var string|null comment
	 * string
	 * Nullable
	 * Order comment.
	 * It cannot be greater than 500 symbols.
	 */
	protected ?string $comment;

	/**
	 * @var array|null customer
	 * object
	 * Nullable
	 * Customer.
	 *
	 * 'Regular' customer:
	 * - can be used only if a customer agrees to take part in the store's loyalty programs
	 * - customer details will be added (updated) to the store's customer database
	 * - benefits (accumulation of rewards, etc.) of active loyalty programs will be made available to the customer
	 *
	 * One-time' customer:
	 * - should be used if a customer does not agree to take part in the store's loyalty programs or an aggregator (external system) does not provide customer details
	 * - customer details will NOT be added to the store's customer database and will be used ONLY to complete the current order
	 */
	protected ?array $customer;

	/**
	 * @var array|null guests
	 * object
	 * Nullable
	 * Guest details. Not equal to the customer who makes an order.
	 */
	protected ?array $guests;

	/**
	 * @var string|null marketingSourceId
	 * string <uuid>
	 * Nullable
	 * Marketing source (advertisement) ID.
	 * Can be obtained by /api/1/marketing_sources operation.
	 */
	private ?string $marketing_source_id = null;

	/**
	 * @var string|null operatorId
	 * string <uuid>
	 * Nullable
	 * Operator ID.
	 * Allowed from version 7.6.3.
	 */
	private ?string $operator_id = null;

	/**
	 * @var array|null items
	 * ### Required ###
	 * Array of objects (iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.OrderItem)
	 * Order items.
	 */
	protected ?array $items;

	/**
	 * @var array|null combos
	 * Array of objects (iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.Combo)
	 * Nullable
	 * Combos included in order.
	 */
	protected ?array $combos = null;

	/**
	 * @var array|null payments
	 * Array of objects (iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.Payment)
	 * Nullable
	 * Order payment components.
	 * Type IikoCard allowed from version 7.1.5.
	 */
	protected $payments;

	/**
	 * @var array|null tips
	 * Array of objects (iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.TipsPayment)
	 * Nullable
	 * Order tips components.
	 */
	protected ?array $tips = null;

	/**
	 * @var string|null sourceKey
	 * string
	 * Nullable
	 * The string key (marker) of the source (partner - api user) that created the order. Needed to limit the visibility of orders for external integration.
	 */
	protected ?string $source_key = null;

	/**
	 * @var array|null discountsInfo
	 * object
	 * Nullable
	 * Discounts/surcharges.
	 */
	protected ?array $discounts_info = null;

	/**
	 * @var array|null loyaltyInfo
	 * object
	 * Nullable
	 * Information about Loyalty app.
	 */
	protected ?array $loyalty_info = null;

	/**
	 * Constructor.
	 *
	 * @param string $order_id
	 * @param string|null $iiko_delivery_id
	 *
	 * @throws \Exception
	 */
	public function __construct( string $order_id, string $iiko_delivery_id = null ) {

		if ( ! $order = wc_get_order( $order_id ) ) {
			Logs::add_wc_log( 'Order could not found', 'create-delivery', 'error' );

			return;
		}

		$this->id              = self::generate_iiko_id( $order, $iiko_delivery_id ) ?: null;
		$this->externalNumber  = self::trim_string( absint( $order_id ), 50 ) ?: null;
		$this->complete_before = apply_filters( WC_IIKOCLOUD_PREFIX . 'order_complete_before', null, $order_id );

		$this->phone = '+' . apply_filters( WC_IIKOCLOUD_PREFIX . 'order_phone', preg_replace( '/\D/', '', $order->get_billing_phone() ) );

		if ( empty( $this->phone ) ) {
			Logs::add_wc_log( 'User phone is empty', 'create-delivery', 'error' );

			return;
		} else {
			$this->phone = self::trim_string( $this->phone, 40 );
		}

		$this->order_service_type = self::is_pickup( $order_id ) ? 'DeliveryByClient' : 'DeliveryByCourier';
		$this->comment            = ''; // To avoid PHP Fatal error: Uncaught Error: Typed property WPWC\iikoCloud\Export\Delivery::$comment must not be accessed before initialization
		$this->delivery_point     = apply_filters( WC_IIKOCLOUD_PREFIX . 'order_delivery_point', $this->delivery_point( $order_id, $order ), $order_id );
		$this->comment            = apply_filters( WC_IIKOCLOUD_PREFIX . 'order_comment', $this->comment( $order_id, $order ), $order_id );
		$this->comment            = self::trim_string( $this->comment, 500 );
		$this->customer           = apply_filters( WC_IIKOCLOUD_PREFIX . 'order_customer', $this->customer( $order ), $order );
		$this->guests             = apply_filters( WC_IIKOCLOUD_PREFIX . 'order_guests', $this->guests(), $order_id );

		$this->items = $this->order_items( $order );
		if ( empty( $this->items ) ) {
			Logs::add_wc_log( 'There are no order items', 'create-delivery', 'error' );

			return;
		}

		$this->discounts_info = apply_filters( WC_IIKOCLOUD_PREFIX . 'order_discounts', null, $order );
		$this->payments       = apply_filters( WC_IIKOCLOUD_PREFIX . 'order_payments', null, $order );
	}

	/**
	 * Delivery point.
	 *
	 * @param int $order_id
	 * @param $order
	 *
	 * @return array|null Address array if is a delivery by courier. Null if is a local pickup.
	 */
	private function delivery_point( int $order_id, $order ): ?array {

		if ( 'DeliveryByClient' === $this->order_service_type ) {
			return null;
		}

		// coordinates
		// object
		// Nullable
		// Delivery address coordinates.
		// Allowed from version 7.7.3.
		$coordinates = null;

		// latitude
		// Required.
		// number <double>
		// Latitude
		// $coordinates['latitude'];

		// longitude
		// Required.
		// number <double>
		// Longitude
		// $coordinates['longitude'];

		// address
		// object
		// Nullable
		// Order delivery address.
		$address = [];

		// street
		// Required.
		// object
		// Street.
		$address['street'] = [];

		/**
		 * It's required specify only "classifierId" or "id" or "name" and "city".
		 */
		// classifierId
		// string [ 0 .. 255 ] characters
		// Nullable
		// Street ID in classifier, e.g., address database.
		// For using in the Russian Federation only.
		$address['street']['classifierId'] = null;

		// id
		// string <uuid>
		// Nullable
		// ID.
		// $address['street']['id'];

		// name
		// string [ 0 .. 60 ] characters Nullable
		// Name.
		$address['street']['name'] = null;

		// city
		// string [ 0 .. 60 ] characters Nullable
		// City name.
		$address['street']['city'] = null;

		// comment
		// string [ 0 .. 500 ] characters
		// Nullable
		// Additional information.
		$comment   = apply_filters( WC_IIKOCLOUD_PREFIX . 'delivery_point_comment', null, $order_id );
		$street_id = sanitize_key( $order->get_meta( '_billing_iiko_street_id' ) );

		if ( ! empty( $street_id ) ) {

			$address['street']['id'] = $street_id;

		} else {

			// Street name is arbitrary because $street_id is empty.
			$street_name = $order->get_billing_address_1();

			// A few cities.
			if ( class_exists( \WPWC\iikoCloud\Modules\Checkout\Checkout_Field_Address::class ) ) {

				$chosen_city_id = sanitize_key( $order->get_billing_city() );
				$all_cities     = wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'all_cities' ) );

				if ( ! empty( $street_name ) && ! empty( $chosen_city_id ) && isset( $all_cities[ $chosen_city_id ] ) ) {
					$address['street']['name'] = self::trim_string( $street_name, 60 ) ?: null;
					$address['street']['city'] = self::trim_string( $all_cities[ $chosen_city_id ], 60 ) ?: null;

				} else {
					$comment                 = wp_slash( $street_name );
					$address['street']['id'] = sanitize_key( self::get_export_settings()['default_street_id'] ) ?: null;
				}

				// One city.
			} else {

				$chosen_city_name = sanitize_text_field( $order->get_billing_city() );

				if ( ! empty( $street_name ) && ! empty( $chosen_city_name ) ) {
					$address['street']['name'] = self::trim_string( $street_name, 60 ) ?: null;
					$address['street']['city'] = self::trim_string( $chosen_city_name, 60 ) ?: null;

				} else {
					$comment                 = wp_slash( $street_name );
					$address['street']['id'] = sanitize_key( self::get_export_settings()['default_street_id'] ) ?: null;
				}
			}
		}

		// index
		// string [ 0 .. 10 ] characters
		// Nullable
		// Postcode.
		$address['index'] = self::trim_string( $order->get_billing_postcode(), 10 ) ?: null;

		// house
		// Required.
		// string [ 0 .. 100 ] characters
		// House.
		// In case useUaeAddressingSystem enabled max length - 100, otherwise - 10
		$address['house'] = self::trim_string( $order->get_billing_address_2(), 10 ) ?: 'HOUSEEMPTY';

		// building
		// string [ 0 .. 10 ] characters
		// Nullable
		// Building.
		$address['building'] = null;

		// flat
		// string [ 0 .. 100 ] characters
		// Nullable
		// Apartment.
		// In case useUaeAddressingSystem enabled max length - 100, otherwise - 10
		$address['flat'] = null;

		// entrance
		// string [ 0 .. 10 ] characters
		// Nullable
		// Entrance.
		$address['entrance'] = null;

		// floor
		// string [ 0 .. 10 ] characters
		// Nullable
		// Floor.
		$address['floor'] = null;

		// doorphone
		// string [ 0 .. 10 ] characters
		// Nullable
		// Intercom.
		$address['doorphone'] = null;

		// regionId
		// string <uuid>
		// Nullable
		// Delivery area ID.
		$address['regionId'] = null;

		// externalCartographyId
		// string [ 0 .. 100 ] characters
		// Nullable
		// Delivery location custom code in customer's API system.
		$external_cartography_id = null;

		return [
			'coordinates'           => $coordinates,
			'address'               => $address,
			'externalCartographyId' => $external_cartography_id,
			'comment'               => $comment,
		];
	}

	/**
	 * Comment.
	 *
	 * @param $order_id
	 * @param $order
	 *
	 * @return string
	 */
	private function comment( $order_id, $order ): string {

		$comment_strings[] = $order_id;
		$comment_strings[] = $this->comment ?: '';
		$comment_strings[] = $order->get_customer_note() ?: '';
		$comment_strings[] = $order->get_shipping_method();
		$comment_strings   = array_filter( $comment_strings );

		$i       = 1;
		$j       = count( $comment_strings );
		$comment = '';

		foreach ( $comment_strings as $comment_string ) {
			$string_end = $i ++ === $j ? '' : PHP_EOL;
			$comment    .= $comment_string . $string_end;
		}

		return $comment;
	}

	/**
	 * Customer.
	 *
	 * @param $order
	 *
	 * @return array
	 */
	private function customer( $order ): array {

		return [
			// string <uuid> Nullable
			// Existing customer ID in RMS.
			'id'                            => null,

			// string [ 0 .. 60 ] characters
			// Nullable
			// Name of customer.
			// Required for new customers (i.e. if "id" == null) Not required if "id" specified.
			'name'                          => self::trim_string( $order->get_billing_first_name(), 60 ) ?: 'NAME EMPTY',

			// string [ 0 .. 60 ] characters
			// Nullable
			// Last name.
			'surname'                       => self::trim_string( $order->get_billing_last_name(), 60 ) ?: null,

			// string [ 0 .. 60 ] characters
			// Nullable
			// Comment.
			'comment'                       => null,

			// string <yyyy-MM-dd HH:mm:ss.fff>
			// Nullable
			// Date of birth.
			'birthdate'                     => null,

			// string Nullable
			// Email.
			'email'                         => sanitize_email( $order->get_billing_email() ) ?: null,

			// boolean
			// Whether user is included in promotional mailing list.
			'shouldReceivePromoActionsInfo' => false,

			// string
			// Enum: "NotSpecified" "Male" "Female"
			// Gender.
			'gender'                        => 'NotSpecified',
		];
	}

	/**
	 * Guests.
	 *
	 * @return array
	 */
	private function guests(): array {

		return [
			// Required.
			// integer <int32>
			// Number of persons in order. This field defines the number of cutlery sets
			'count'               => 1,

			// Required.
			// boolean
			// Attribute that shows whether order must be split among guests.
			'splitBetweenPersons' => false,
		];
	}

	/**
	 * Create order items array.
	 *
	 * @param $order
	 *
	 * @return array Array of order items. Null if the cart is empty.
	 */
	private function order_items( $order ): ?array {

		$order_items    = $order->get_items();
		$delivery_items = [];

		if ( empty( $order_items ) ) {
			Logs::add_wc_log( 'No products in cart', 'create-delivery', 'error' );

			return null;
		}

		foreach ( $order_items as $order_item ) {

			$size_iiko_id      = null;
			$product_modifiers = [];
			$product_name      = $order_item->get_name();
			$product           = $order_item->get_product();
			$is_variation      = $product->is_type( 'variation' );

			if ( empty( $product ) ) {
				Logs::add_wc_log( "Order item $product_name error. Skip it.", 'create-delivery', 'error' );

				continue;
			}

			// Get product sale price if it exists or regular price otherwise.
			if ( 'yes' === self::get_export_settings()['prices'] ) {
				$product_sale_price = $product->get_sale_price();
				$product_price      = ! empty( $product_sale_price ) ? $product_sale_price : $product->get_price();

			} else {
				$product_price = null;
			}

			// Get product iiko ID.
			if ( $is_variation ) {
				$parent_product  = wc_get_product( $product->get_parent_id() );
				$product_iiko_id = sanitize_key( $parent_product->get_meta( WC_IIKOCLOUD_PREFIX . 'product_id' ) );

			} else {
				$product_iiko_id = sanitize_key( $product->get_meta( WC_IIKOCLOUD_PREFIX . 'product_id' ) );
			}

			// Exclude products from export without iiko ID.
			if ( empty( $product_iiko_id ) ) {
				Logs::add_wc_log( "Product $product_name does not have iiko ID", 'create-delivery', 'error' );

				continue;
			}

			// Variation information.
			if ( $is_variation ) {

				$size_iiko_id         = sanitize_key( $product->get_meta( WC_IIKOCLOUD_PREFIX . 'product_size_id' ) ) ?: null;
				$product_modifier_ids = $product->get_meta( WC_IIKOCLOUD_PREFIX . 'product_modifier_ids' );

				// Exclude from export variations without iiko size and modifier IDs.
				if ( empty( $size_iiko_id ) && empty( $product_modifier_ids ) ) {
					Logs::add_wc_log( "Variation $product_name does not have iiko size ID and iiko modifier IDs", 'create-delivery', 'error' );

					continue;
				}

				if ( ! empty( $product_modifier_ids ) ) {

					foreach ( $product_modifier_ids as $modifier_group_id => $modifier_id ) {

						$product_modifiers[] = [
							// Required.
							// string <uuid>
							// Modifier item ID.
							'productId'      => sanitize_key( $modifier_id ),

							// Required.
							// number <double>
							// Quantity.
							'amount'         => 1,

							// Required for a group modifier.
							// string <uuid>
							// Nullable
							// Modifiers group ID (for group modifier).
							'productGroupId' => sanitize_key( $modifier_group_id ),

							// number <double>
							// Nullable
							// Unit price.
							'price'          => 0,

							// string <uuid>
							// Nullable
							// Unique identifier of the item in the order. MUST be unique for the whole system.
							// Therefore, it must be generated with Guid.NewGuid().
							// If sent null, it generates automatically on iikoTransport side.
							'positionId'     => null,
						];
					}
				}
			}

			$delivery_items[] = [
				// Required.
				// string <uuid>
				// ID of menu item.
				'productId'        => $product_iiko_id, // Already sanitized

				// Array of objects
				// (iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.Modifier)
				// Nullable
				// Modifiers.
				'modifiers'        => apply_filters( WC_IIKOCLOUD_PREFIX . 'export_delivery_modifiers', $product_modifiers, $order_item ),

				// number <double>
				// Nullable
				// Price per item unit.
				'price'            => $product_price,

				// string <uuid>
				// Nullable
				// Unique identifier of the item in the order. MUST be unique for the whole system.
				// Therefore, it must be generated with Guid.NewGuid().
				// If sent null, it generates automatically on iikoTransport side.
				'positionId'       => null,

				// string
				'type'             => 'Product',

				// Required.
				// number <double> [ 0 .. 999.999 ]
				// Quantity.
				'amount'           => floatval( $order_item->get_quantity() ),

				// string <uuid>
				// Nullable
				// Size ID. Required if a stock list item has a size scale.
				'productSizeId'    => $size_iiko_id, // Already sanitized

				// object
				// Nullable
				// Combo details if combo includes order item.
				'comboInformation' => null,

				// string [ 0 .. 255 ] characters
				// Nullable
				// Comment.
				'comment'          => null,
			];
		}

		// Add shipping cost as a product.
		if ( empty( $shipping_product_id = absint( self::get_export_settings()['shipping_as_product_id'] ) ) ) {
			return $delivery_items;
		}

		$shipping_total = $order->get_shipping_total();
		$shipping_tax   = $order->get_shipping_tax();

		if ( 0 !== $shipping_total && ! empty( $shipping_product_id ) && ! self::is_pickup() ) {

			if ( ! $shipping_product = wc_get_product( $shipping_product_id ) ) {
				return $delivery_items;
			}

			$shipping_product_iiko_id = sanitize_key( $shipping_product->get_meta( WC_IIKOCLOUD_PREFIX . 'product_id' ) );

			if ( ! empty( $shipping_product_iiko_id ) ) {

				$delivery_items[] = [
					'productId'        => $shipping_product_iiko_id,
					'modifiers'        => [],
					'price'            => $shipping_total + $shipping_tax,
					'positionId'       => null,
					'type'             => 'Product',
					'amount'           => 1,
					'productSizeId'    => null,
					'comboInformation' => null,
					'comment'          => null,
				];
			}
		}

		return $delivery_items;
	}

	/**
	 * Get ID.
	 *
	 * @return string Delivery ID in <uuid> format.
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * JSON delivery representation.
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id'                => $this->id,
			'externalNumber'    => $this->externalNumber,
			'completeBefore'    => $this->complete_before,
			'phone'             => $this->phone,
			'orderTypeId'       => $this->order_type_id,
			'orderServiceType'  => $this->order_service_type,
			'deliveryPoint'     => $this->delivery_point,
			'comment'           => $this->comment,
			'customer'          => $this->customer,
			'guests'            => $this->guests,
			'marketingSourceId' => $this->marketing_source_id,
			'operatorId'        => $this->operator_id,
			'items'             => $this->items,
			'combos'            => $this->combos,
			'payments'          => $this->payments,
			'tips'              => $this->tips,
			'sourceKey'         => $this->source_key,
			'discountsInfo'     => $this->discounts_info,
			'loyaltyInfo'       => $this->loyalty_info,
		];
	}
}
