<?php

namespace WPWC\iikoCloud\Traits;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\API_Requests\Stock_API_Requests;
use WPWC\iikoCloud\Logs;
use WP_REST_Response;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

trait WCActionsTrait {

	/**
	 * Get products IDs.
	 *
	 * @param array $status Array of statuses to search.
	 *
	 * @return array|object
	 */
	protected static function get_products_ids( array $status ) {

		// [ 'draft', 'pending', 'private', 'publish' ]
		$args = [
			'limit'  => - 1,
			'status' => $status,
			'return' => 'ids',
		];

		return wc_get_products( $args );
	}

	/**
	 * Get all out of stock published products IDs.
	 *
	 * @return array
	 */
	protected static function get_out_of_stock_products_ids(): array {

		$args = [
			'limit'      => - 1,
			'status'     => 'publish',
			'meta_key'   => '_stock_status',
			'meta_value' => 'outofstock',
			'return'     => 'ids',
		];

		return wc_get_products( $args );
	}

	/**
	 * Change products stock status.
	 * Possible stock statuses: instock, outofstock, onbackorder
	 *
	 * @param int $product_id If 0 status will be changed for all published products.
	 * @param string $status Stock status.
	 */
	protected static function change_products_stock_status( int $product_id, string $status ) {

		$products_ids = ( 0 === $product_id ) ? self::get_products_ids( [ 'publish' ] ) : [ $product_id ];

		foreach ( $products_ids as $product_id ) {
			if ( $product = wc_get_product( $product_id ) ) {
				$product->set_stock_status( $status );
				$product->save();
			}
		}
	}

	/**
	 * Change products sale price.
	 *
	 * @param string $product_cat_name iiko product category name.
	 * @param int $discount_percent Discount percentage.
	 *
	 * @return bool|int
	 */
	protected static function change_products_sale_price( string $product_cat_name, int $discount_percent ) {

		$iiko_product_categories = wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'iiko_product_categories' ) );

		if ( ! is_array( $iiko_product_categories ) ) {
			return false;
		}

		$iiko_product_categories = array_column( $iiko_product_categories, 'id', 'name' );

		if ( empty( $iiko_product_categories ) ) {
			return false;
		}

		$args = [
			'limit'      => - 1,
			'meta_key'   => WC_IIKOCLOUD_PREFIX . 'product_category_id',
			'meta_value' => sanitize_key( $iiko_product_categories[ $product_cat_name ] ),
			'return'     => 'ids',
		];

		$product_ids = wc_get_products( $args );

		if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
			return false;
		}

		foreach ( $product_ids as $product_id ) {

			if ( ! $product = wc_get_product( $product_id ) ) {
				return false;
			}

			if ( $product->is_type( 'variable' ) ) {
				$variations = $product->get_available_variations();

				if ( ! empty( $variations ) ) {
					$variation_ids = array_column( $variations, 'variation_id' );

					foreach ( $variation_ids as $variation_id ) {

						if ( ! $variation = wc_get_product( $variation_id ) ) {
							return false;
						}

						$variation_regular_price = absint( $variation->get_regular_price() );
						$variation->set_sale_price( absint( $variation_regular_price - ( $variation_regular_price * $discount_percent / 100 ) ) );
						$variation->save();
					}
				}

			} else {
				$product_regular_price = absint( $product->get_regular_price() );
				$product->set_sale_price( absint( $product_regular_price - ( $product_regular_price * $discount_percent / 100 ) ) );
			}

			$product->save();
		}

		return count( $product_ids );
	}

	/**
	 * Get iiko order meta ID by key.
	 *
	 * @param string $order_id
	 * @param string $meta_key
	 *
	 * @return string|null
	 */
	protected static function get_iiko_order_meta_id( string $order_id, string $meta_key ): ?string {

		$order      = wc_get_order( $order_id );
		$order_meta = $order ? sanitize_key( $order->get_meta( WC_IIKOCLOUD_PREFIX . $meta_key ) ) : null;

		return is_string( $order_meta ) && ! empty( $order_meta ) ? $order_meta : null;
	}

	/**
	 * Stop list processing.
	 *
	 * @param array $stop_list
	 *
	 * @return void
	 */
	protected static function handle_stop_list( array $stop_list ): void {

		global $wpdb;

		Logs::add_wc_log( print_r( $stop_list, true ), 'stop-list' );

		foreach ( $stop_list as $stop_list_key => $stop_list_balance ) {

			$query       = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '" . WC_IIKOCLOUD_PREFIX . "product_id' AND meta_value = '" . sanitize_key( $stop_list_key ) . "';" );
			$product_ids = $wpdb->get_col( $query );

			if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
				Logs::add_wc_log( 'Stop list item key: ' . $stop_list_key, 'stop-list-miss' );
				continue;
			}

			foreach ( $product_ids as $product_id ) {
				$product_status = ( $stop_list_balance <= 0 ) ? 'outofstock' : 'instock';

				self::change_products_stock_status( $product_id, $product_status );

				$updated_product_info = 'Stop list item key: ' . $stop_list_key . PHP_EOL;
				$updated_product_info .= 'Product ID: ' . $product_id . PHP_EOL;
				$updated_product_info .= 'Product status: ' . $product_status . PHP_EOL;

				Logs::add_wc_log( $updated_product_info, 'stop-list-success' );
			}
		}
	}

	/**
	 * Stop list processing.
	 *
	 * @param string $organization_id
	 *
	 * @return bool|WP_REST_Response
	 */
	protected static function update_products_status_by_stop_list( string $organization_id ) {

		$all_out_of_stock_products_ids = self::get_out_of_stock_products_ids();

		// Set all 'outofstock' products' statuses as 'instock'.
		if ( ! empty( $all_out_of_stock_products_ids ) ) {
			foreach ( $all_out_of_stock_products_ids as $out_of_stock_product_id ) {
				self::change_products_stock_status( $out_of_stock_product_id, 'instock' );
			}
		}

		// Get stop list and apply it.
		$stock_api_requests = new Stock_API_Requests();
		$stop_lists         = $stock_api_requests->out_of_stock_items( $organization_id );

		if ( empty( $stop_lists['terminalGroupStopLists'] ) ) {
			Logs::add_wc_log( 'Stop list is empty', 'stop-list' );

			return new WP_REST_Response( 'OK', 200 );
		}

		$merged_stop_list = [];

		foreach ( $stop_lists['terminalGroupStopLists'] as $organization ) {
			foreach ( $organization['items'] as $terminal ) {
				foreach ( $terminal['items'] as $item ) {

					if ( ! array_key_exists( $item['productId'], $merged_stop_list ) ) {
						$merged_stop_list[ $item['productId'] ] = $item['balance'];

					} else {
						if ( $item['balance'] < $merged_stop_list[ $item['productId'] ] ) {
							$merged_stop_list[ $item['productId'] ] = $item['balance'];
						}
					}
				}
			}
		}

		self::handle_stop_list( $merged_stop_list );

		// TODO - add last stop list statistic to the plugin settings.

		return true;
	}

	/**
	 * Get chosen shipping methods.
	 */
	protected static function get_chosen_shipping_method() {
		if ( empty( WC()->session ) ) {
			return null;
		}

		return WC()->session->get( 'chosen_shipping_methods' )[0] ?? null;
	}

	/**
	 * Get order shipping method.
	 *
	 * @param $order_id
	 *
	 * @return null|string
	 */
	protected static function get_order_shipping_method( $order_id ): ?string {

		if ( ! $order = wc_get_order( $order_id ) ) {
			return null;
		}

		if ( empty( $shipping_methods = $order->get_items( 'shipping' ) ) ) {
			return null;
		}

		foreach ( $shipping_methods as $shipping_method_id => $shipping_method ) {
			return $shipping_method->get_method_id() . ':' . $shipping_method->get_instance_id();
		}

		return null;
	}

	/**
	 * Get chosen iiko shipping method key.
	 *
	 * @param string $key Option key of the chosen shipping method.
	 * @param ?string $chosen_shipping_method Option name of the chosen shipping method.
	 *
	 * @return null|string Null if the current shipping method's ID is not iiko shipping methods.
	 * Chosen shipping method key otherwise.
	 */
	protected static function get_iiko_shipping_method_param( string $key, ?string $chosen_shipping_method = null ): ?string {

		// Get shipping method ID and instance ID:
		$chosen_shipping_method = ! is_null( $chosen_shipping_method ) ? $chosen_shipping_method : self::get_chosen_shipping_method();

		if ( is_null( $chosen_shipping_method ) ) {
			return null;
		}

		$chosen_shipping_method             = explode( ':', $chosen_shipping_method );
		$chosen_shipping_method_id          = $chosen_shipping_method[0];
		$chosen_shipping_method_instance_id = absint( $chosen_shipping_method[1] );

		$iiko_shipping_methods_ids = [ 'iiko_local_pickup', 'iiko_courier' ];

		// Return null if current shipping method is not an iiko_local_pickup or iiko_courier. Or shipping method instance is empty.
		if (
			! in_array( $chosen_shipping_method_id, $iiko_shipping_methods_ids, true )
			|| empty( $chosen_shipping_method_instance_id )
		) {
			return null;
		}

		// Get all existing shipping zones IDs.
		$shipping_zones_ids = array_keys( [ '' ] + WC_Shipping_Zones::get_zones() );

		foreach ( $shipping_zones_ids as $shipping_zones_id ) {

			// Get the shipping zone object.
			$shipping_zone = new WC_Shipping_Zone( $shipping_zones_id );

			// Get all shipping method values for the shipping zone.
			$shipping_methods = $shipping_zone->get_shipping_methods( true, 'values' );

			if ( empty( $shipping_methods ) ) {
				continue;
			}

			foreach ( $shipping_methods as $instance_id => $shipping_method ) {
				if (
					in_array( $shipping_method->id, $iiko_shipping_methods_ids, true )
					&& 'yes' === $shipping_method->enabled
					&& $chosen_shipping_method_instance_id === $instance_id
				) {
					return ! empty( $shipping_method->$key ) ? $shipping_method->$key : null;
				}
			}
		}

		return null;
	}

	/**
	 * Update product import statistic.
	 *
	 * @param $imported_product
	 * @param array $product_import_statistics Product import statistics.
	 *
	 * @return array
	 */
	protected static function update_imported_product_stat( $imported_product, array $product_import_statistics ): array {

		if ( false !== $imported_product ) {

			$product_import_statistics['importedProducts'] ++;

			if ( isset( $imported_product['excludedProduct'] ) ) {
				$product_import_statistics['excludedProducts'][] = $imported_product['excludedProduct'];

			} elseif ( isset( $imported_product['insertedProduct'] ) ) {
				$product_import_statistics['insertedProducts'][] = $imported_product['insertedProduct'];
				$product_import_statistics['product_ids'][]      = $imported_product['product_id'] ?? null;

			} elseif ( isset( $imported_product['updatedProduct'] ) ) {
				$product_import_statistics['updatedProducts'][] = $imported_product['updatedProduct'];
				$product_import_statistics['product_ids'][]     = $imported_product['product_id'] ?? null;
			}
		}

		return $product_import_statistics;
	}

	/**
	 * Delete old products.
	 *
	 * @param ?array $imported_product_ids
	 */
	protected static function delete_old_products( ?array $imported_product_ids ) {

		if ( empty( $imported_product_ids ) ) {
			return;
		}

		$published_products_ids = self::get_products_ids( [ 'draft', 'pending', 'private', 'publish' ] );
		$old_products_ids       = array_diff( $published_products_ids, $imported_product_ids );

		foreach ( $old_products_ids as $old_product_id ) {

			if ( ! $product = wc_get_product( $old_product_id ) ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {

				foreach ( $product->get_children() as $child_product_id ) {

					if ( ! $child_product = wc_get_product( $child_product_id ) ) {
						continue;
					}

					$child_product->delete();
				}
			}

			$product->delete();
			$is_deleted = 'trash' === $product->get_status();

			if ( ! $is_deleted ) {
				Logs::add_wc_log( "Cannot delete product $old_product_id", 'import', 'error' );
			}

			wc_delete_product_transients( $old_product_id );
		}
	}

	/**
	 * Check if the shipping method is pickup.
	 *
	 * @param string|null $order_id
	 *
	 * @return null|bool
	 */
	protected static function is_pickup( ?string $order_id = null ): ?bool {

		$chosen_shipping_method = is_null( $order_id ) ? self::get_chosen_shipping_method() : self::get_order_shipping_method( $order_id );

		if ( is_null( $chosen_shipping_method ) ) {
			return null;
		}

		// Check if is a local_pickup method.
		$is_local_pickup = 0 === strpos( $chosen_shipping_method, 'local_pickup' );
		// Check if is an iiko_local_pickup shipping method.
		$is_iiko_local_pickup = 0 === strpos( $chosen_shipping_method, 'iiko_local_pickup' );

		return $is_local_pickup || $is_iiko_local_pickup;
	}
}
