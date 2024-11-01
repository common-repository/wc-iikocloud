<?php

namespace WPWC\iikoCloud\Traits;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\Logs;

trait ImportTrait {

	/**
	 * Import settings.
	 */
	protected static $import_settings;

	/**
	 * Get the plugin import settings.
	 *
	 * @return array
	 */
	protected static function get_import_settings(): array {

		self::$import_settings = is_array( self::$import_settings ) && ! empty( self::$import_settings )
			? self::$import_settings
			: get_option( WC_IIKOCLOUD_PREFIX . 'import' );

		return self::$import_settings ?: [];
	}

	/**
	 * Process wp_update/wp_insert result.
	 *
	 * @param  string  $name
	 * @param  int  $id
	 * @param  string  $action
	 * @param  string  $type
	 * @param $result
	 *
	 * @return bool True if no errors, false otherwise.
	 */
	private static function check_import(
		string $name,
		int $id,
		string $action,
		string $type,
		$result
	): bool {

		$error_message = sprintf( esc_html__( '%1$s %2$s %3$s error', 'wc-iikocloud' ), $type, $name, $action );

		if ( $result === 0 ) {
			Logs::add_error( $error_message );
			Logs::add_wc_log( $error_message, 'import', 'error' );

			return false;

		} elseif ( is_wp_error( $result ) ) {
			Logs::log_wp_error( $result, $error_message );

			return false;

		} else {
			$action_past_tense = 'update' === $action ? esc_html__( 'updated', 'wc-iikocloud' ) : ( 'insert' === $action ? esc_html__( 'inserted', 'wc-iikocloud' ) : '' );
			$success_message   = sprintf( esc_html__( '%1$s %2$s has been successfully %3$s (ID: %4$d)', 'wc-iikocloud' ),
				$type,
				$name,
				$action_past_tense,
				$id
			);

			Logs::add_notice( $success_message );
			Logs::add_wc_log( $success_message, 'import', 'notice' );

			return true;
		}
	}

	/**
	 * Set product SKU if it is unique.
	 *
	 * @param  object  $product  Product object
	 * @param  int  $product_id
	 * @param  string  $product_sku
	 * @param  string  $product_name
	 *
	 * @return bool
	 */
	private static function import_product_sku(
		object $product,
		int $product_id,
		string $product_sku,
		string $product_name
	): bool {

		if ( wc_product_has_unique_sku( $product_id, $product_sku ) ) {

			$product->set_sku( $product_sku );

			return true;

		} else {

			$message = sprintf( esc_html__( 'Product %s: SKU %s is not unique', 'wc-iikocloud' ), $product_name, $product_sku );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'import', 'error' );

			return false;
		}
	}

	/**
	 * Import iiko group, product, product category, size or modifier IDs.
	 *
	 * @param  object  $product  Product object (product, variation).
	 * @param  string|array  $iiko_ids
	 * @param  string  $type  Possible values: product, product_size, product_modifier.
	 * @param  string|null  $iiko_product_category_id
	 *
	 * @return void
	 */
	private static function import_product_iiko_ids(
		object $product,
		$iiko_ids,
		string $type,
		string $iiko_product_category_id = null
	): void {

		if ( empty( $iiko_ids ) ) {
			return;
		}

		switch ( $type ) {
			case 'product':
				$product->update_meta_data( WC_IIKOCLOUD_PREFIX . 'product_id', $iiko_ids );

				if ( ! is_null( $iiko_product_category_id ) ) {
					$product->update_meta_data( WC_IIKOCLOUD_PREFIX . 'product_category_id', $iiko_product_category_id );
				}
				break;

			case 'product_size':
				$product->update_meta_data( WC_IIKOCLOUD_PREFIX . 'product_size_id', $iiko_ids );
				break;

			case 'product_modifier':
				$product->update_meta_data( WC_IIKOCLOUD_PREFIX . 'product_modifier_ids', $iiko_ids );
				break;
		}
	}
}