<?php

namespace WPWC\iikoCloud\Import;

defined( 'ABSPATH' ) || exit;

use WC_Product_Factory;
use WC_Product_Simple;
use WC_Product_Variable;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\CommonTrait;
use WPWC\iikoCloud\Traits\ImportTrait;

if ( trait_exists( '\WPWC\iikoCloud\Modules\Import\AdvancedImport' ) ) {

	class Import_Products_Main {
		use \WPWC\iikoCloud\Modules\Import\AdvancedImport;
	}

} else {

	class Import_Products_Main {
	}
}

class Import_Products extends Import_Products_Main {

	use CommonTrait;
	use ImportTrait;

	/**
	 * Import product.
	 *
	 * @param  array  $product_info  Product data.
	 * @param  int  $product_category_id  Product category ID.
	 * @param  array  $modifiers  All products modifiers.
	 * @param  array  $sizes  All products sizes.
	 * @param  array  $modifier_groups_list  All modifier groups list. modifier_group_id => group_name.
	 *
	 * @return bool|array False if we cannot import the product or type of operation 'insertedProduct', 'updatedProduct', 'excludedProduct'.
	 * Array with import information otherwise.
	 * @throws \WC_Data_Exception
	 */
	public static function import_product(
		array $product_info,
		int $product_category_id,
		array $modifiers,
		array $sizes,
		array $modifier_groups_list
	) {

		$is_advanced_import = trait_exists( '\WPWC\iikoCloud\Modules\Import\AdvancedImport' );

		// code
		// string Nullable
		// SKU.
		$product_sku = sanitize_text_field( $product_info['code'] );

		// name
		// required
		// string
		// Name.
		$product_name = sanitize_text_field( $product_info['name'] );

		// id
		// required
		// string <uuid>
		// ID.
		$product_iiko_id = sanitize_key( $product_info['id'] );

		if ( empty( $product_sku ) || empty( $product_name ) || empty( $product_iiko_id ) ) {
			$message = esc_html__( "Product doesn't have required data", 'wc-iikocloud' );

			Logs::add_notice( $message );
			Logs::add_wc_log( $message, 'import', 'error' );

			return false;
		}

		// isDeleted
		// boolean
		// Is-Deleted attribute.
		// Skip deleted product.
		if ( $product_info['isDeleted'] ) {
			$message = sprintf( esc_html__( 'Product %s is deleted in iiko', 'wc-iikocloud' ), $product_name );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'import', 'error' );

			return [ 'excludedProduct' => $product_name ];
		}

		// TODO - improve
		// Product KBZHU.
		// fatAmount
		// proteinsAmount
		// carbohydratesAmount
		// energyAmount
		// fatFullAmount
		// proteinsFullAmount
		// carbohydratesFullAmount
		// energyFullAmount
		// Each value type is:
		// number <double> Nullable
		$product_kbzhu = [
			'per_100g' => [
				'energy'        => isset( $product_info['energyAmount'] ) ? floatval( $product_info['energyAmount'] ) : 0.0,
				'proteins'      => isset( $product_info['proteinsAmount'] ) ? floatval( $product_info['proteinsAmount'] ) : 0.0,
				'fat'           => isset( $product_info['fatAmount'] ) ? floatval( $product_info['fatAmount'] ) : 0.0,
				'carbohydrates' => isset( $product_info['carbohydratesAmount'] ) ? floatval( $product_info['carbohydratesAmount'] ) : 0.0,
			],
			'per_item' => [
				'energy'        => isset( $product_info['energyFullAmount'] ) ? floatval( $product_info['energyFullAmount'] ) : 0.0,
				'proteins'      => isset( $product_info['proteinsFullAmount'] ) ? floatval( $product_info['proteinsFullAmount'] ) : 0.0,
				'fat'           => isset( $product_info['fatFullAmount'] ) ? floatval( $product_info['fatFullAmount'] ) : 0.0,
				'carbohydrates' => isset( $product_info['carbohydratesFullAmount'] ) ? floatval( $product_info['carbohydratesFullAmount'] ) : 0.0,
			],
		];

		/* Product weight, group, type... */
		// weight
		// number <double> Nullable
		// Item weight.
		$product_weight = isset( $product_info['weight'] ) ? round( floatval( $product_info['weight'] ) * 1000 ) : 0.0;

		/* Product sizes. */
		$product_sizes_title = self::get_product_sizes_title();

		// sizePrices
		// Array of objects (iikoTransport.PublicApi.Contracts.Nomenclature.SizePrice)
		// Prices.
		$product_sizes   = &$product_info['sizePrices'];
		$product_sizes   = is_array( $product_sizes ) && ! empty( $product_sizes ) ? $product_sizes : null;
		$are_there_sizes = isset( $product_sizes[0]['sizeId'] );

		// currentPrice
		// required
		// number <double>
		// Current price.
		$product_price = isset( $product_sizes[0]['price']['currentPrice'] ) ? floatval( $product_sizes[0]['price']['currentPrice'] ) : 0.0;

		// isIncludedInMenu
		// required
		// boolean
		// Is on the menu.
		$is_included_in_menu = true === $product_sizes[0]['price']['isIncludedInMenu'];

		// nextPrice
		// number <double> Nullable
		// New price
		$product_sale_price = isset( $product_sizes[0]['price']['nextPrice'] ) ? floatval( $product_sizes[0]['price']['nextPrice'] ) : 0.0;

		// TODO
		/* Product modifiers. */
		// modifiers
		// Array of objects (iikoTransport.PublicApi.Contracts.Nomenclature.SimpleModifierInfo)
		// Modifiers.
		// $product_simple_modifiers        = is_array( $product_info['modifiers'] ) && ! empty( $product_info['modifiers'] ) ? $product_info['modifiers'] : null;
		// $product_simple_modifiers_title  = esc_attr__( 'Modifier', 'wc-iikocloud' );
		// $is_import_only_simple_modifiers = isset( self::get_import_settings()['only_simple_modifiers'] ) && 'yes' === self::get_import_settings()['only_simple_modifiers'];

		// groupModifiers
		// Array of objects (iikoTransport.PublicApi.Contracts.Nomenclature.GroupModifierInfo)
		// Modifier groups.
		$product_group_modifiers = &$product_info['groupModifiers'];
		$product_group_modifiers = $is_advanced_import && is_array( $product_group_modifiers ) && ! empty( $product_group_modifiers )
			? self::filter_group_modifiers( $product_group_modifiers, $modifier_groups_list )
			: null;

		if (
			isset( $product_group_modifiers )
			&& $is_advanced_import
			&& isset( self::get_import_settings()['gms_as_product_cfs'] )
			&& 'yes' === self::get_import_settings()['gms_as_product_cfs']
		) {
			$product_group_modifiers_as_cf = self::prepare_group_modifiers_as_product_cf( $product_group_modifiers, $modifier_groups_list, $modifiers );
			$product_group_modifiers       = null;
		}

		$are_there_modifiers = isset( $product_group_modifiers );

		/* Main product data. */
		// imageLinks
		// Array of strings
		// Links to images.
		$product_thumb_urls = $product_info['imageLinks'];

		// order
		// integer <int32>
		// Product's order (priority) in menu.
		$product_iiko_menu_order = sanitize_key( $product_info['order'] );

		// productCategoryId
		// string <uuid>
		// Nullable
		// Product category in RMS.
		$product_iiko_category_id = sanitize_key( $product_info['productCategoryId'] );

		// description
		// string Nullable
		//  Description.
		$product_desc = isset( $product_info['description'] ) ? wp_strip_all_tags( $product_info['description'] ) : '';

		// additionalInfo
		// string Nullable
		// Additional information.
		$product_excerpt = $product_info['additionalInfo'] ?? '';

		// tags
		// Array of strings Nullable
		// Tags.
		$product_tags = $product_info['tags'];

		// seoDescription
		// string Nullable
		// SEO description for client.
		//
		// seoText
		// string Nullable
		// SEO text for robots.
		$product_seo_desc = isset( $product_info['seoText'] ) ? sanitize_text_field( $product_info['seoText'] ) : null;

		// seoKeywords
		// string Nullable
		// SEO key words.
		//
		// seoTitle
		// string Nullable
		// SEO header.
		$product_seo_title = isset( $product_info['seoTitle'] ) ? sanitize_text_field( $product_info['seoTitle'] ) : null;

		// TODO - simplify.
		if (
			$are_there_modifiers
			&& 1 === count( $product_group_modifiers )
		) {

			$product_first_group_modifier_iiko_id = isset( $product_group_modifiers[0]['id'] ) ? sanitize_key( $product_group_modifiers[0]['id'] ) : null;
			$product_first_group_modifier_items   = is_array( $product_group_modifiers[0]['childModifiers'] ) && ! empty( $product_group_modifiers[0]['childModifiers'] )
				? $product_group_modifiers[0]['childModifiers']
				: null;
			$product_first_group_modifier_title   = isset( $modifier_groups_list[ $product_first_group_modifier_iiko_id ] )
				? sanitize_text_field( $modifier_groups_list[ $product_first_group_modifier_iiko_id ] )
				: esc_attr__( 'Modifier', 'wc-iikocloud' );
		}

		$is_simple_product = ! $is_advanced_import
		                     || ( isset( self::get_import_settings()['as_simple_products'] ) && 'yes' === self::get_import_settings()['as_simple_products'] )
		                     || ! $are_there_sizes && ! $are_there_modifiers;

		// Update the product if a product with iiko SKU already exists.
		if ( ! empty( $product_id = wc_get_product_id_by_sku( $product_sku ) ) ) {

			$import_type   = 'update';
			$import_result = null;

			if ( ! $product = wc_get_product( $product_id ) ) {
				$import_result = 0;
			}

			$product->set_name( $product_name );
			$product->set_status( 'publish' );
			$product->set_description( self::get_product_desc( $product_desc, 'content', $product ) );
			$product->set_short_description( self::get_product_desc( $product_excerpt, 'excerpt', $product ) );
			$product->set_menu_order( $product_iiko_menu_order );

			// Create the new product if it doesn't exist.
		} else {

			$import_type   = 'insert';
			$import_result = null;

			$product = $is_simple_product ? new WC_Product_Simple() : new WC_Product_Variable();

			$product->set_name( $product_name );
			$product->set_status( 'publish' );
			$product->set_description( self::get_product_desc( $product_desc, 'content' ) );
			$product->set_short_description( self::get_product_desc( $product_excerpt, 'excerpt' ) );
			$product->set_menu_order( $product_iiko_menu_order );
			$product_id = $product->save();
		}

		// Check the product import.
		if ( false === self::check_import(
				$product_name,
				$product_id,
				$import_type,
				'Product',
				$import_result
			)
		) {
			return false;
		}

		if ( false === self::import_product_sku(
				$product,
				$product_id,
				$product_sku,
				$product_name
			)
		) {
			return false;
		}

		if ( isset( self::get_import_settings()['delete_product_attrs_vars'] ) && 'yes' === self::get_import_settings()['delete_product_attrs_vars'] ) {
			self::delete_product_attributes_variations( $product );
		}

		/**
		 * Import simple product.
		 */
		// There is only one size without sizeId and there are no modifiers.
		// If it is a simple product or is the free plugin version or the option 'Variable as a simple product' is activated.
		if ( $is_simple_product ) {

			if ( 'update' === $import_type && 'variable' === $product->get_type() ) {
				$product_classname = WC_Product_Factory::get_product_classname( $product_id, 'simple' );
				$product           = new $product_classname( $product_id );
			}

			self::import_simple_product_metadata( $product, (string) $product_price, (string) $product_sale_price, $product_weight );

			/**
			 * Import variable product.
			 */
		} else {

			if ( 'update' === $import_type && 'simple' === $product->get_type() ) {
				$product_classname = WC_Product_Factory::get_product_classname( $product_id, 'variable' );
				$product           = new $product_classname( $product_id );
			}

			/*
			 * 1. The product has only sizes without group modifiers.
			 */
			// Add sizes to the product as attributes with their prices.
			if ( $are_there_sizes && ! $are_there_modifiers ) {

				// Prepare product attributes.
				$all_attributes = self::prepare_product_attributes( $product_sizes, $sizes, $product_sizes_title, 'size' );

				if ( empty( $all_attributes ) ) {
					return false;
				}

				// Insert product attributes.
				self::insert_product_attributes( $product, $all_attributes );

				// Insert product variations.
				$i = 1;
				foreach ( $product_sizes as $product_size ) {

					$variation_sku  = $product_sku . "-$i";
					$variation_data = self::prepare_product_variation_data(
						$sizes,
						$product_size,
						$product_sizes_title,
						$product_name,
						$product_price,
						$variation_sku,
						$product_weight,
						'size'
					);

					self::create_product_variation( $product_id, $variation_data );

					$i ++;
				}

				$message = sprintf( esc_html__( 'Product %s has sizes. Created %s variation(s)', 'wc-iikocloud' ), $product_name, $i );

				Logs::add_notice( $message );
				Logs::add_wc_log( $message, 'import', 'notice' );
			}

			/*
			 *  2. The product doesn't have sizes and has ONLY ONE group modifier.
			 * TODO - combine with case 3.
			 */
			// Add modifiers to the product as attributes with the product price and modifiers prices.
			elseif ( ! $are_there_sizes && isset( $product_first_group_modifier_items ) ) {

				// Prepare product attributes.
				$all_attributes = self::prepare_product_attributes( $product_first_group_modifier_items, $modifiers, $product_first_group_modifier_title, 'modifier' );

				if ( empty( $all_attributes ) ) {
					return false;
				}

				// Insert product attributes.
				self::insert_product_attributes( $product, $all_attributes );

				// Insert product variations.
				$i = 1;
				foreach ( $product_first_group_modifier_items as $product_modifier ) {

					$variation_sku  = $product_sku . "-$i";
					$variation_data = self::prepare_product_variation_data(
						$modifiers,
						$product_modifier,
						$product_first_group_modifier_title,
						$product_name,
						$product_price,
						$variation_sku,
						$product_weight,
						'modifier',
						$product_first_group_modifier_iiko_id
					);

					self::create_product_variation( $product_id, $variation_data );

					$i ++;
				}

				$message = sprintf( esc_html__( 'Product %s has modifiers. Created %s variation(s)', 'wc-iikocloud' ), $product_name, $i );

				Logs::add_notice( $message );
				Logs::add_wc_log( $message, 'import', 'notice' );
			}

			/*
			 *  3. The product doesn't have sizes and has group modifiers.
			 * TODO - combine with case 2.
			 */
			// Add modifiers to the product as attributes with the product price.
			elseif ( ! $are_there_sizes && $are_there_modifiers ) {

				$all_attributes                     = [];
				$product_group_modifiers_ids_titles = [];

				$i = 1;
				foreach ( $product_group_modifiers as $product_modifier_group ) {

					$product_modifier_group_iiko_id = isset( $product_modifier_group['id'] ) ? sanitize_key( $product_modifier_group['id'] ) : null;
					// [ i => [ id, defaultAmount, minAmount, maxAmount, required, hideIfDefaultAmount, splittable, freeOfChargeAmount ], ... ]
					$product_modifier_group_items = is_array( $product_modifier_group['childModifiers'] ) && ! empty( $product_modifier_group['childModifiers'] )
						? $product_modifier_group['childModifiers']
						: null;
					$product_modifier_group_title = isset( $modifier_groups_list[ $product_modifier_group_iiko_id ] )
						? sanitize_text_field( $modifier_groups_list[ $product_modifier_group_iiko_id ] )
						: sprintf( esc_html__( 'Modifier %s', 'wc-iikocloud' ), $i );

					if ( null === $product_modifier_group_iiko_id || null === $product_modifier_group_items ) {
						$message = sprintf( esc_html__( 'Product %s has modifiers, but modifier %s doesn\'t have iiko ID or empty', 'wc-iikocloud' ), $product_name, $product_modifier_group_title );

						Logs::add_error( $message );
						Logs::add_wc_log( $message, 'import', 'error' );

						continue;
					}

					// [ 'modifier_iiko_id' => 'modifier_slug', ... ]
					$product_group_modifiers_ids_titles[ $product_modifier_group_iiko_id ] = sanitize_title( $product_modifier_group_title );

					// Prepare product attributes.
					$all_attributes = array_merge( $all_attributes, self::prepare_product_attributes( $product_modifier_group_items, $modifiers, $product_modifier_group_title, 'modifier' ) );

					if ( empty( $all_attributes ) ) {
						return false;
					}

					// Insert product attributes.
					self::insert_product_attributes( $product, $all_attributes );

					$i ++;
				}

				$variations_limit = isset( self::get_import_settings()['vars_limit'] ) ? absint( self::get_import_settings()['vars_limit'] ) : 50;
				$data_store       = $product->get_data_store();
				$variations_count = self::create_all_product_variations(
					$product,
					$product_sku,
					(string) $product_price,
					(string) $product_sale_price,
					$product_weight,
					$all_attributes,
					'',
					$product_group_modifiers_ids_titles,
					$variations_limit
				);

				$data_store->sort_all_product_variations( $product_id );

				$message = sprintf( esc_html__( 'Product %s has modifiers. Created %s variation(s)', 'wc-iikocloud' ), $product_name, $variations_count );

				Logs::add_notice( $message );
				Logs::add_wc_log( $message, 'import', 'notice' );
			}

			/*
			 *  4. The product has both sizes and group modifiers.
			 */
			// Use only the first modifiers group.
			// Add sizes and modifiers to the product as attributes combinations with the sizes prices.
			elseif ( $are_there_sizes && isset( $product_first_group_modifier_items ) ) {

				// Prepare product attributes.
				$attributes_sizes     = self::prepare_product_attributes( $product_sizes, $sizes, $product_sizes_title, 'size' );
				$attributes_modifiers = self::prepare_product_attributes( $product_first_group_modifier_items, $modifiers, $product_first_group_modifier_title, 'modifier' );
				$all_attributes       = array_merge( $attributes_sizes, $attributes_modifiers );

				if ( empty( $all_attributes ) ) {
					return false;
				}

				// Insert product attributes.
				self::insert_product_attributes( $product, $all_attributes );

				// Insert product variations.
				$i = 1;
				foreach ( $product_sizes as $product_size ) {

					$variation_sku        = $product_sku . "-$i";
					$sizes_variation_data = self::prepare_product_variation_data(
						$sizes,
						$product_size,
						$product_sizes_title,
						$product_name,
						$product_price,
						$variation_sku,
						$product_weight,
						'size'
					);

					$j = 1;
					foreach ( $product_first_group_modifier_items as $product_modifier ) {

						$variation_sku            = $product_sku . "-$i-$j";
						$modifiers_variation_data = self::prepare_product_variation_data(
							$modifiers,
							$product_modifier,
							$product_first_group_modifier_title,
							$product_name,
							$product_size['price']['currentPrice'],
							$variation_sku,
							$product_weight,
							'modifier',
							$product_first_group_modifier_iiko_id
						);

						$variation_data = self::array_merge_recursive_distinct( $sizes_variation_data, $modifiers_variation_data );

						self::create_product_variation( $product_id, $variation_data );

						$j ++;
					}

					$i ++;
				}

				$message = sprintf( esc_html__( 'Product %s has sizes and modifiers. Created %s variation(s)', 'wc-iikocloud' ), $product_name, $i * $j );

				Logs::add_notice( $message );
				Logs::add_wc_log( $message, 'import', 'notice' );
			}
		}

		if ( ! empty( $product_category_id ) ) {
			$product->set_category_ids( [ $product_category_id ] );
		}

		self::import_product_iiko_ids( $product, $product_iiko_id, 'product', $product_iiko_category_id );
		self::import_product_images( $product, $product_name, $product_thumb_urls, 'product' );
		self::import_product_tags( $product, $product_tags );
		self::import_product_seo_data( $product, $product_seo_title, $product_seo_desc, $product_category_id );
		self::enable_reviews( $product );
		self::hide_excluded_product( $product, $product_price, $is_included_in_menu );

		if ( ! empty( $product_kbzhu['per_100g'] ) || ! empty( $product_kbzhu['per_item'] ) ) {
			$product->update_meta_data( WC_IIKOCLOUD_PREFIX . 'product_kbzhu', $product_kbzhu );
		}

		if ( isset( $product_group_modifiers_as_cf ) ) {
			$product->update_meta_data( WC_IIKOCLOUD_PREFIX . 'group_modifiers', $product_group_modifiers_as_cf );
		} else {
			$product->delete_meta_data( WC_IIKOCLOUD_PREFIX . 'group_modifiers' );
		}

		$product->save();

		return 'update' === $import_type
			? [
				'updatedProduct' => $product_name,
				'product_id'     => $product_id,
			]
			: [
				'insertedProduct' => $product_name,
				'product_id'      => $product_id,
			];
	}

	/**
	 * Import external menu product.
	 *
	 * @param  array  $product_info  Product data.
	 * @param  int  $product_category_id  Product categories.
	 *
	 * @return bool|array False if we cannot import the product or type of operation 'insertedProduct', 'updatedProduct', 'excludedProduct'.
	 * Array with import information otherwise.
	 * @throws \WC_Data_Exception
	 */
	public static function import_external_menu_product( array $product_info, int $product_category_id ) {

		$is_advanced_import = trait_exists( '\WPWC\iikoCloud\Modules\Import\AdvancedImport' );

		// sku
		// string
		// Product code
		$product_sku = isset( $product_info['sku'] ) ? sanitize_text_field( $product_info['sku'] ) : null;

		// name
		// string
		// Product name
		$product_name = isset( $product_info['name'] ) ? sanitize_text_field( $product_info['name'] ) : null;

		// itemId
		// string <uuid> (itemId)
		// Product ID
		$product_iiko_id = isset( $product_info['itemId'] ) ? sanitize_key( $product_info['itemId'] ) : null;

		if ( empty( $product_sku ) || empty( $product_name ) || empty( $product_iiko_id ) ) {
			$message = esc_html__( "Product doesn't have required data", 'wc-iikocloud' );

			Logs::add_notice( $message );
			Logs::add_wc_log( $message, 'import', 'error' );

			return false;
		}

		// description
		// string
		// Product description
		$product_desc = isset( $product_info['description'] ) ? wp_strip_all_tags( $product_info['description'] ) : '';

		// productCategoryId
		// string <uuid>
		// Nullable
		// Product category in RMS.
		$product_iiko_category_id = sanitize_key( $product_info['productCategoryId'] );

		// itemSizes
		// Array of objects (TransportItemSizeDto)
		$product_sizes = &$product_info['itemSizes'];

		if ( ! is_array( $product_sizes ) || empty( $product_sizes ) ) {
			$message = esc_html__( "Product $product_name doesn't have sizes", 'wc-iikocloud' );

			Logs::add_notice( $message );
			Logs::add_wc_log( $message, 'import', 'error' );

			return false;
		}

		// TODO - improve
		// Product KBZHU.
		$product_kbzhu['per_100g'] = ! empty( $product_sizes[0]['nutritionPerHundredGrams'] ) ? [
			'energy'        => isset( $product_sizes[0]['nutritionPerHundredGrams']['energy'] ) ? floatval( $product_sizes[0]['nutritionPerHundredGrams']['energy'] ) : 0.0,
			'proteins'      => isset( $product_sizes[0]['nutritionPerHundredGrams']['proteins'] ) ? floatval( $product_sizes[0]['nutritionPerHundredGrams']['proteins'] ) : 0.0,
			'fat'           => isset( $product_sizes[0]['nutritionPerHundredGrams']['fats'] ) ? floatval( $product_sizes[0]['nutritionPerHundredGrams']['fats'] ) : 0.0,
			'carbohydrates' => isset( $product_sizes[0]['nutritionPerHundredGrams']['carbs'] ) ? floatval( $product_sizes[0]['nutritionPerHundredGrams']['carbs'] ) : 0.0,
		] : null;
		$product_kbzhu['per_item'] = ! empty( $product_sizes[0]['nutritions'] ) ? [
			'energy'        => isset( $product_sizes[0]['nutritions'][0]['energy'] ) ? floatval( $product_sizes[0]['nutritions'][0]['energy'] ) : 0.0,
			'proteins'      => isset( $product_sizes[0]['nutritions'][0]['proteins'] ) ? floatval( $product_sizes[0]['nutritions'][0]['proteins'] ) : 0.0,
			'fat'           => isset( $product_sizes[0]['nutritions'][0]['fats'] ) ? floatval( $product_sizes[0]['nutritions'][0]['fats'] ) : 0.0,
			'carbohydrates' => isset( $product_sizes[0]['nutritions'][0]['carbs'] ) ? floatval( $product_sizes[0]['nutritions'][0]['carbs'] ) : 0.0,
		] : null;

		// We suppose that all sizes modifier groups are the same and use the first one.
		if (
			! empty( $product_sizes[0]['itemModifierGroups'] )
			&& $is_advanced_import
			&& isset( self::get_import_settings()['gms_as_product_cfs'] )
			&& 'yes' === self::get_import_settings()['gms_as_product_cfs']
		) {

			$product_group_modifiers_as_cf = self::prepare_menu_group_modifiers_as_product_cf( $product_sizes[0]['itemModifierGroups'] );

			// We remove all sizes modifiers groups in order to not create modifiers variations (there will be only sizes variations).
			foreach ( $product_sizes as &$product_size ) {
				$product_size ['itemModifierGroups'] = [];
			}
		}

		// There is no sale prices in external menus.
		$product_sale_price = '0';

		$is_simple_product = ! $is_advanced_import
		                     || ( isset( self::get_import_settings()['as_simple_products'] ) && 'yes' === self::get_import_settings()['as_simple_products'] )
		                     || 'simple' === self::define_product_type( $product_sizes );

		// Update the product if a product with iiko SKU already exists.
		if ( ! empty( $product_id = wc_get_product_id_by_sku( $product_sku ) ) ) {

			$import_type   = 'update';
			$import_result = null;

			if ( ! $product = wc_get_product( $product_id ) ) {
				$import_result = 0;
			}

			$product->set_name( $product_name );
			$product->set_status( 'publish' );
			$product->set_description( self::get_product_desc( $product_desc, 'content', $product ) );

			// Create the new product if it doesn't exist.
		} else {

			$import_type   = 'insert';
			$import_result = null;

			$product = $is_simple_product ? new WC_Product_Simple() : new WC_Product_Variable();

			$product->set_name( $product_name );
			$product->set_status( 'publish' );
			$product->set_description( self::get_product_desc( $product_desc, 'content' ) );
			$product_id = $product->save();
		}

		// Check the product import.
		if ( false === self::check_import(
				$product_name,
				$product_id,
				$import_type,
				'Product',
				$import_result
			)
		) {
			return false;
		}

		if ( false === self::import_product_sku(
				$product,
				$product_id,
				$product_sku,
				$product_name
			)
		) {
			return false;
		}

		if ( ! empty( $product_category_id ) ) {
			$product->set_category_ids( [ $product_category_id ] );
		}

		self::import_product_iiko_ids( $product, $product_iiko_id, 'product', $product_iiko_category_id );
		self::enable_reviews( $product );

		if ( ! empty( $product_kbzhu['per_100g'] ) || ! empty( $product_kbzhu['per_item'] ) ) {
			$product->update_meta_data( WC_IIKOCLOUD_PREFIX . 'product_kbzhu', $product_kbzhu );
		}

		if ( isset( $product_group_modifiers_as_cf ) ) {
			$product->update_meta_data( WC_IIKOCLOUD_PREFIX . 'group_modifiers', $product_group_modifiers_as_cf );
		} else {
			$product->delete_meta_data( WC_IIKOCLOUD_PREFIX . 'group_modifiers' );
		}

		if ( isset( self::get_import_settings()['delete_product_attrs_vars'] ) && 'yes' === self::get_import_settings()['delete_product_attrs_vars'] ) {
			self::delete_product_attributes_variations( $product );
		}

		/**
		 * Import simple product.
		 */
		if ( $is_simple_product ) {

			if ( 'update' === $import_type && 'variable' === $product->get_type() ) {
				$product_classname = WC_Product_Factory::get_product_classname( $product_id, 'simple' );
				$product           = new $product_classname( $product_id );
			}

			$product_weight = isset( $product_sizes[0]['portionWeightGrams'] ) ? floatval( $product_sizes[0]['portionWeightGrams'] ) : 0.0;
			$product_price  = isset( $product_sizes[0]['prices'][0]['price'] ) ? floatval( $product_sizes[0]['prices'][0]['price'] ) : 0.0;

			if ( is_string( $product_sizes[0]['buttonImageUrl'] ) ) {
				$product_thumb_urls = [ esc_url( $product_sizes[0]['buttonImageUrl'] ) ];
			} elseif ( is_array( $product_sizes[0]['buttonImageUrl'] ) ) {
				$product_thumb_urls = $product_sizes[0]['buttonImageUrl'];
			} else {
				$product_thumb_urls = null;
			}

			self::import_simple_product_metadata( $product, (string) $product_price, $product_sale_price, $product_weight );
			self::import_product_images( $product, $product_name, $product_thumb_urls, 'product' );

			$product->save();

			return 'update' === $import_type
				? [
					'updatedProduct' => $product_name,
					'product_id'     => $product_id,
				]
				: [
					'insertedProduct' => $product_name,
					'product_id'      => $product_id,
				];

			/**
			 * Import variable product.
			 */
		} else {

			if ( 'update' === $import_type && 'simple' === $product->get_type() ) {
				$product_classname = WC_Product_Factory::get_product_classname( $product_id, 'variable' );
				$product           = new $product_classname( $product_id );
			}

			// Prepare iiko attributes (sizes and modifiers).
			$iiko_attributes = self::prepare_iiko_attributes( $product_sizes, $product_name );
			$first_size      = &$iiko_attributes[ array_key_first( $iiko_attributes ) ];

			// Prepare sizes.
			$attributes_sizes = count( $iiko_attributes ) > 1
				? self::prepare_sizes( $iiko_attributes, self::get_product_sizes_title() )
				: [];

			// Prepare modifiers.
			$attributes_modifiers = self::prepare_modifiers( $iiko_attributes ) ?: [];

			// Prepare product attributes.
			$all_attributes = array_merge( $attributes_sizes, $attributes_modifiers );

			if ( empty( $all_attributes ) ) {
				Logs::add_wc_log( "Product $product_name doesn't have attributes", 'import', 'notice' );

				return false;
			}

			// Insert product attributes.
			self::insert_product_attributes( $product, $all_attributes );

			// Prepare group modifiers.
			// [ 'modifier_iiko_id' => 'modifier_slug', ... ]
			$modifier_groups = self::prepare_group_modifiers( $iiko_attributes ) ?: [];

			$variations_limit = isset( self::get_import_settings()['vars_limit'] ) ? absint( self::get_import_settings()['vars_limit'] ) : 50;
			$data_store       = $product->get_data_store();

			if ( 1 === count( $iiko_attributes ) ) {

				// If the product has one size, then the size code will be equal to the product code.
				$first_size['size_id'] = $product_iiko_id;

				$variations_count = self::create_all_product_variations(
					$product,
					$product_sku,
					(string) $first_size['size_price'],
					$product_sale_price,
					$first_size['size_weight'],
					$all_attributes,
					null,
					$modifier_groups,
					$variations_limit
				);

			} else {

				$variations_count = 0;

				foreach ( $iiko_attributes as $iiko_size_sku => $iiko_size_data ) {

					$size_attribute  = self::prepare_sizes( [ $iiko_size_sku => $iiko_size_data ], self::get_product_sizes_title() );
					$size_modifiers  = self::prepare_modifiers( [ $iiko_size_sku => $iiko_size_data ] ) ?: [];
					$size_attributes = array_merge( $size_attribute, $size_modifiers );

					$variations_count += self::create_all_product_variations(
						$product,
						$iiko_size_sku,
						(string) $iiko_size_data['size_price'],
						$product_sale_price,
						$iiko_size_data['size_weight'],
						$size_attributes,
						$iiko_size_data['size_id'],
						$modifier_groups,
						$variations_limit,
						true
					);
				}
			}

			$data_store->sort_all_product_variations( $product_id );

			$message = sprintf( esc_html__( 'Created %s variation(s) for %s', 'wc-iikocloud' ), $variations_count, $product_name );

			Logs::add_notice( $message );
			Logs::add_wc_log( $message, 'import', 'notice' );

			self::import_product_images( $product, $product_name, $first_size['size_thumb_urls'], 'product' );

			$product->save();

			return 'update' === $import_type
				? [
					'updatedProduct' => $product_name,
					'product_id'     => $product_id,
				]
				: [
					'insertedProduct' => $product_name,
					'product_id'      => $product_id,
				];
		}
	}

	/**
	 * Return translated product sizes title.
	 *
	 * @return string
	 */
	private static function get_product_sizes_title(): string {
		return esc_attr__( 'Size', 'wc-iikocloud' );
	}

	/**
	 * Define product type.
	 *
	 * @param  array  $product_sizes
	 *
	 * @return string Possible values: simple, variable.
	 */
	private static function define_product_type( array $product_sizes ): string {

		if ( 1 === count( $product_sizes ) && empty( $product_sizes[0]['itemModifierGroups'] ) ) {
			return 'simple';
		} else {
			return 'variable';
		}
	}

	/**
	 * Prepare sizes for variable products.
	 *
	 * @param  array  $product_sizes
	 * @param  string  $product_name  Product name
	 *
	 * @return array.
	 */
	private static function prepare_iiko_attributes( array $product_sizes, string $product_name ): array {

		$attributes             = [];
		$modifier_default_title = esc_attr__( 'Modifier', 'wc-iikocloud' );

		foreach ( $product_sizes as $product_size ) {
			// sku
			// string
			// Unique size code, consists of the product code and the name of the size, if the product has one size, then the size code will be equal to the product code.
			$size_sku = isset( $product_size['sku'] ) ? sanitize_text_field( $product_size['sku'] ) : null;

			if ( empty( $size_sku ) ) {
				$message = sprintf( esc_html__( 'Product %s doesn\'t have size SKU', 'wc-iikocloud' ), $product_name );

				Logs::add_error( $message );
				Logs::add_wc_log( $message, 'import', 'error' );

				continue;
			}

			$attributes[ $size_sku ] = [];
			$attribute_size          = &$attributes[ $size_sku ];

			// sizeId
			// string <uuid> (sizeId)
			// ID size, can be empty if the default size is selected and it is the only size in the list.
			$attribute_size['size_id'] = isset( $product_size['sizeId'] ) ? sanitize_key( $product_size['sizeId'] ) : null;

			// sizeName
			// string
			// Name of the product size, the name can be empty if there is only one size in the list
			$attribute_size['size_name'] = isset( $product_size['sizeName'] ) ? sanitize_text_field( $product_size['sizeName'] ) : self::get_product_sizes_title();

			// isDefault
			//boolean
			//Whether it is a default size of the product. If the product has one size, then the parameter will be true, if the product has several sizes, none of them can be default.

			// portionWeightGrams
			// number <float>
			// Size's weight
			$attribute_size['size_weight'] = isset( $product_size['portionWeightGrams'] ) ? floatval( $product_size['portionWeightGrams'] ) : 0.0;

			// price
			// number <float>
			// Product size prices for the organization,
			// If the value is null, then the product/size is not for sale,
			// The price always belongs to the price category that was selected at the time of the request
			$attribute_size['size_price'] = isset( $product_size['prices'][0]['price'] ) ? floatval( $product_size['prices'][0]['price'] ) : 0.0;

			// nutritionPerHundredGrams
			// object (NutritionInfoDto)
			$attribute_size['size_kbzhu']['per_100g'] = ! empty( $product_sizes[0]['nutritionPerHundredGrams'] ) ? [
				'energy'        => isset( $product_sizes[0]['nutritionPerHundredGrams']['energy'] ) ? floatval( $product_sizes[0]['nutritionPerHundredGrams']['energy'] ) : 0.0,
				'proteins'      => isset( $product_sizes[0]['nutritionPerHundredGrams']['proteins'] ) ? floatval( $product_sizes[0]['nutritionPerHundredGrams']['proteins'] ) : 0.0,
				'fat'           => isset( $product_sizes[0]['nutritionPerHundredGrams']['fats'] ) ? floatval( $product_sizes[0]['nutritionPerHundredGrams']['fats'] ) : 0.0,
				'carbohydrates' => isset( $product_sizes[0]['nutritionPerHundredGrams']['carbs'] ) ? floatval( $product_sizes[0]['nutritionPerHundredGrams']['carbs'] ) : 0.0,
			] : null;
			$attribute_size['size_kbzhu']['per_item'] = ! empty( $product_sizes[0]['nutritions'] ) ? [
				'energy'        => isset( $product_sizes[0]['nutritions'][0]['energy'] ) ? floatval( $product_sizes[0]['nutritions'][0]['energy'] ) : 0.0,
				'proteins'      => isset( $product_sizes[0]['nutritions'][0]['proteins'] ) ? floatval( $product_sizes[0]['nutritions'][0]['proteins'] ) : 0.0,
				'fat'           => isset( $product_sizes[0]['nutritions'][0]['fats'] ) ? floatval( $product_sizes[0]['nutritions'][0]['fats'] ) : 0.0,
				'carbohydrates' => isset( $product_sizes[0]['nutritions'][0]['carbs'] ) ? floatval( $product_sizes[0]['nutritions'][0]['carbs'] ) : 0.0,
			] : null;

			if ( isset( $product_size['buttonImageUrl'] ) && is_string( $product_size['buttonImageUrl'] ) ) {
				$attribute_size['size_thumb_urls'] = [ esc_url( $product_size['buttonImageUrl'] ) ];
			} elseif ( isset( $product_size['buttonImageUrl'] ) && is_array( $product_size['buttonImageUrl'] ) ) {
				$attribute_size['size_thumb_urls'] = array_map( 'esc_url', $product_size['buttonImageUrl'] );
			} else {
				$attribute_size['size_thumb_urls'] = null;
			}

			/**
			 * Modifier groups.
			 */
			$attribute_size['modifier_groups'] = [];

			// itemModifierGroups
			// Array of objects (TransportModifierGroupDto)
			if ( is_array( $product_size['itemModifierGroups'] ) && ! empty( $product_size['itemModifierGroups'] ) ) {

				$i = 1;
				foreach ( $product_size['itemModifierGroups'] as $modifier_group ) {

					// If itemGroupId = null modifiers are simple (single).
					// Don't import simple modifiers.
					if ( ! isset( $modifier_group['itemGroupId'] ) ) {
						Logs::add_wc_log( "Product $product_name has non group modifier {$modifier_group['name']}", 'import', 'error' );

						continue;
					}

					// sku
					// string
					// Modifiers group code
					$modifier_group_sku = isset( $modifier_group['sku'] ) ? sanitize_key( $modifier_group['sku'] ) : null;

					if ( empty( $modifier_group_sku ) ) {
						$message = sprintf( esc_html__( 'Product %s doesn\'t have modifier group SKU', 'wc-iikocloud' ), $product_name );

						Logs::add_error( $message );
						Logs::add_wc_log( $message, 'import', 'error' );

						continue;
					}

					$attribute_group_modifier = &$attribute_size['modifier_groups'][ $modifier_group_sku ];

					// string
					// <uuid> (itemGroupId)
					// Modifiers group id
					$attribute_group_modifier['modifier_group_id'] = isset( $modifier_group['itemGroupId'] ) ? sanitize_key( $modifier_group['itemGroupId'] ) : null;

					// name
					// string
					// Modifiers group name
					$attribute_group_modifier['modifier_group_name'] = isset( $modifier_group['name'] ) ? sanitize_text_field( $modifier_group['name'] ) : $modifier_default_title . "-$i";

					// description
					// string
					// Modifiers group description
					$attribute_group_modifier['modifier_group_description'] = isset( $modifier_group['description'] ) ? wp_strip_all_tags( $modifier_group['description'] ) : '';

					/**
					 * Modifiers.
					 */
					$attribute_group_modifier['modifier_group_items'] = [];

					// items
					// Array of objects (TransportModifierItemDto)
					if ( is_array( $modifier_group['items'] ) && ! empty( $modifier_group['items'] ) ) {

						$j = 1;
						foreach ( $modifier_group['items'] as $modifier ) {

							// sku
							// string
							// Modifier's code
							$modifier_sku = isset( $modifier['sku'] ) ? sanitize_key( $modifier['sku'] ) : null;

							if ( empty( $modifier_sku ) ) {
								$message = sprintf( esc_html__( 'Product %s doesn\'t have modifier SKU', 'wc-iikocloud' ), $product_name );

								Logs::add_error( $message );
								Logs::add_wc_log( $message, 'import', 'error' );

								continue;
							}

							$attribute_modifier = &$attribute_group_modifier['modifier_group_items'][ $modifier_sku ];

							// itemId
							// string <uuid> (itemId)
							// Modifier's Id
							$attribute_modifier['modifier_id'] = isset( $modifier['itemId'] ) ? sanitize_key( $modifier['itemId'] ) : null;

							// name
							// string
							// Modifier's name
							$attribute_modifier['modifier_name'] = isset( $modifier['name'] ) ? sanitize_text_field( $modifier['name'] ) : $modifier_default_title . "-$i-$j";

							// description
							// string
							// Modifier's description
							$attribute_modifier['modifier_description'] = isset( $modifier['description'] ) ? wp_strip_all_tags( $modifier['description'] ) : '';

							// buttonImage
							// string
							// Links to images
							$attribute_modifier['modifier_image_url'] = isset( $modifier['buttonImageUrl'] ) ? esc_url( $modifier['buttonImageUrl'] ) : '';

							// portionWeightGrams
							// number <float>
							// Modifier's weight in gramms
							$attribute_modifier['modifier_weight'] = isset( $modifier['portionWeightGrams'] ) ? floatval( $modifier['portionWeightGrams'] ) : 0.0;

							// tags
							// Array of objects (TagDto)
							$attribute_modifier['modifier_tags'] = isset( $modifier['tags'] ) ? wc_clean( $modifier['tags'] ) : [];

							// prices
							// Array of objects (TransportPriceDto)
							// price
							// number <float>
							// Product size prices for the organization, if the value is null, then the product/size is not for sale, the price always belongs to the price category that was selected at the time of the request
							$attribute_modifier['modifier_price'] = isset( $modifier['prices'][0]['price'] ) ? floatval( $modifier['prices'][0]['price'] ) : 0.0;

							$j ++;
						}
					}

					$i ++;
				}
			}
		}

		unset( $i, $j, $attribute_size, $attribute_group_modifier, $attribute_modifier, $modifier_group, $modifier, $size_sku, $modifier_group_sku, $modifier_sku, $modifier_default_title );

		return $attributes;
	}

	/**
	 * Import product image.
	 *
	 * @param  object  $product  Product object
	 * @param  string  $thumb_desc
	 * @param $thumb_urls
	 *
	 * @return true|false|void True on successful, false on failure.
	 */
	private static function import_product_images(
		object $product,
		string $thumb_desc,
		$thumb_urls
	) {

		if ( ! isset( self::get_import_settings()['images'] ) || 'yes' !== self::get_import_settings()['images'] ) {
			return;
		}

		if ( ! is_array( $thumb_urls ) || empty( $thumb_urls ) ) {
			$message = sprintf( esc_html__( 'There is no image for product %s', 'wc-iikocloud' ), $thumb_desc );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'import', 'error' );

			return false;
		}

		// Delete old attachments.
		if ( isset( self::get_import_settings()['delete_product_imgs'] ) && 'yes' === self::get_import_settings()['delete_product_imgs'] ) {
			self::delete_product_images( $product );
		}

		$first_thumb = esc_url( array_shift( $thumb_urls ) );
		$product_id  = $product->get_id();

		// Load media_sideload_image function and dependencies in front for CRON jobs.
		if ( ! is_admin() ) {
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}

		/*
		 * Add main thumb.
		 */
		// Downloads the image from the specified URL, saves it as an attachment.
		$thumb_id = media_sideload_image( $first_thumb, $product_id, $thumb_desc, 'id' );

		// Return WP_Error when fail to import product thumbnail.
		if ( is_wp_error( $thumb_id ) ) {
			Logs::log_wp_error( $thumb_id, "Error while import product attachment for '$thumb_desc'" );

			return false;

		} else {

			$error_message = sprintf( esc_html__( 'Error while relate product thumbnail for %s', 'wc-iikocloud' ), $thumb_desc );

			$related_thumb = set_post_thumbnail( $product_id, $thumb_id );

			if ( false === $related_thumb ) {
				Logs::add_error( $error_message );
				Logs::add_wc_log( $error_message, 'import', 'error' );

				return false;
			}

			/*
			 * Add product gallery.
			 */
			if ( ! empty( $thumb_urls ) ) {

				$thumb_ids  = [];
				$thumb_urls = array_map( function ( $value ) {
					return esc_url( $value );
				}, $thumb_urls );

				foreach ( $thumb_urls as $thumb_url ) {
					$thumb_id = media_sideload_image( $thumb_url, $product_id, $thumb_desc, 'id' );

					if ( is_wp_error( $thumb_id ) ) {
						Logs::log_wp_error( $thumb_id, "Error while import product attachment for '$thumb_desc'" );

						continue;
					}

					$thumb_ids[] = $thumb_id;
				}

				if ( empty( $thumb_ids ) ) {
					return false;
				}

				$related_thumb = $product->update_meta_data( '_product_image_gallery', implode( ',', $thumb_ids ) );


				if ( ! $related_thumb ) {
					$message = sprintf( esc_html__( 'Error while relate product image gallery for %s', 'wc-iikocloud' ), $thumb_desc );;

					Logs::add_error( $message );
					Logs::add_wc_log( $message, 'import', 'error' );

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Delete all product images.
	 *
	 * @param  object  $product
	 *
	 * @return void
	 */
	private static function delete_product_images( object $product ): void {

		if ( ! empty( $featured_image_id = $product->get_image_id() ) ) {
			wp_delete_attachment( $featured_image_id, true );
		}

		if ( ! empty( $image_galleries_id = $product->get_gallery_image_ids() ) ) {
			foreach ( $image_galleries_id as $single_image_id ) {
				wp_delete_attachment( $single_image_id, true );
			}
		}
	}

	/**
	 * Import product tags.
	 *
	 * @param  object  $product  Product object.
	 * @param  array  $product_tags  Tag IDs.
	 *
	 * @return void
	 */
	private static function import_product_tags( object $product, array $product_tags ): void {

		if ( ! isset( self::get_import_settings()['tags'] ) || 'yes' !== self::get_import_settings()['tags'] ) {
			return;
		}

		$product_tag_ids = wp_set_object_terms( $product->get_id(), $product_tags, 'product_tag' );

		if ( is_wp_error( $product_tag_ids ) ) {
			Logs::log_wp_error( $product_tag_ids, 'Error while import tags of product' . $product->get_name() );

			return;
		}

		$product->set_tag_ids( $product_tag_ids );
	}

	/**
	 * Hide disabled products and products without price.
	 *
	 * @param  object  $product  Product object.
	 * @param  int|float  $price
	 * @param  bool  $is_included_in_menu
	 *
	 * @return void
	 */
	private static function hide_excluded_product( object $product, $price, bool $is_included_in_menu ): void {

		if ( true !== $is_included_in_menu || ( $product->is_type( 'simple' ) && empty( $price ) ) ) {
			$product->set_catalog_visibility( 'hidden' );
		} else {
			$product->set_catalog_visibility( 'visible' );
		}
	}

	/**
	 * Import product metadata.
	 *
	 * @param  object  $product
	 * @param  string  $price
	 * @param  string  $sale_price
	 * @param  float  $weight
	 *
	 * @return void
	 * @throws \WC_Data_Exception
	 */
	private static function import_simple_product_metadata(
		object $product,
		string $price,
		string $sale_price,
		float $weight
	): void {

		// $product->set_name( '' ); // Used by self::import_product()
		// $product->set_slug( '' );
		// $product->set_date_created( '' );
		// $product->set_date_modified( '' );
		// $product->set_status( '' ); // Used by self::import_product()
		// $product->set_featured( false );
		// $product->set_catalog_visibility( 'visible' ); // Used by self::hide_excluded_product()
		// $product->set_description( '' ); // Used by self::import_product()
		// $product->set_short_description( '' ); // Used by self::import_product()
		// $product->set_sku( $sku ); // Used by self::import_product()
		$product->set_price( $price );
		$product->set_regular_price( $price );

		// If sale prices import is turned OFF.
		if ( ! isset( self::get_import_settings()['sale_prices'] ) || 'yes' !== self::get_import_settings()['sale_prices'] ) {
			// Save the current product sale price if it is not empty.
			if ( ! empty( $current_sale_price = $product->get_sale_price() ) ) {
				$product->set_price( $current_sale_price );
				$product->set_sale_price( $current_sale_price );
			}

			// If sale prices import is turned ON and the new sale price is not empty, import it. Reset sale price otherwise.
		} else {
			if ( ! empty( $sale_price ) ) {
				$product->set_price( $sale_price );
				$product->set_sale_price( $sale_price );

			} else {
				$product->set_sale_price( '' );
			}
		}

		// $product->set_date_on_sale_from( '' );
		// $product->set_date_on_sale_to( '' );
		// $product->set_total_sales( 0 );
		// $product->set_tax_status( '' );
		// $product->set_tax_class( '' );
		// $product->set_manage_stock( false );
		// $product->set_stock_quantity( null );
		$product->set_stock_status( 'instock' );
		// $product->set_backorders( 'no' );
		// $product->set_low_stock_amount( '' );
		// $product->set_sold_individually( false );
		$product->set_weight( $weight );
		// $product->set_length( '' );
		// $product->set_width( '' );
		// $product->set_height( '' );
		// $product->set_upsell_ids( [] );
		// $product->set_cross_sell_ids( [] );
		// $product->set_parent_id( 0 );
		// $product->set_reviews_allowed( true ); // Used by self::enable_reviews()
		// $product->set_purchase_note( '' );
		// $product->set_attributes( [] );
		// $product->set_default_attributes( [] );
		// $product->set_menu_order( 0 ); // Used by self::import_product()
		// $product->set_post_password( 0 );
		// $product->set_category_ids( [] );
		// $product->set_tag_ids( [] ); // Used by self::import_product_tags()
		$product->set_virtual( false );
		// $product->set_shipping_class_id( 0 );
		$product->set_downloadable( false );
		// $product->set_downloads( [] );
		// $product->set_download_limit( 0 );
		// $product->set_download_expiry( 0 );
		// $product->set_gallery_image_ids( [] ); // Used by self::import_product_images()
		// $product->set_image_id( 0 ); // Used by self::import_product_images()
		// $product->set_rating_counts( [] );
		// $product->set_average_rating( 5 );
		// $product->set_review_count( 0 );

		// wc_delete_product_transients( $product_id );
	}

	/**
	 * Enable reviews.
	 *
	 * @param  object  $product
	 *
	 * @return void
	 */
	private static function enable_reviews( object $product ): void {

		if ( isset( self::get_import_settings()['products_reviews'] ) && 'yes' === self::get_import_settings()['products_reviews'] ) {
			$product->set_reviews_allowed( true );
		}
	}

	/**
	 * Get the current product description.
	 *
	 * @param  string  $desc  New product description.
	 * @param  string  $type  Type of the product description.
	 * @param  object|null  $product  Product objecg. Default is null.
	 *
	 * @return string Description if import option is switched on. An empty string if it is a product insertion,
	 *  or previous description if it is a product updating.
	 */
	private static function get_product_desc( string $desc, string $type, ?object $product = null ): string {

		// If import of descriptions is turned ON.
		if ( isset( self::get_import_settings()['descriptions'] ) && 'yes' === self::get_import_settings()['descriptions'] ) {
			return $desc;
		}

		// If import of descriptions is turned OFF, and it is a product insertion.
		if ( null === $product ) {
			return '';
		}

		// If import of descriptions is turned OFF, and it is a product updating.
		// Return the old description.
		return 'content' === $type ? $product->get_description() : $product->get_short_description();
	}

	/**
	 * Import product SEO data.
	 *
	 * Work only with Yoast SEO plugin.
	 * $seo_title and $seo_desc should be already sanitized.
	 *
	 * @param  object  $product  Object ID (product category, product)
	 * @param  string|null  $seo_title
	 * @param  string|null  $seo_desc
	 * @param  string|null  $product_category_id
	 *
	 * @return void
	 */
	private static function import_product_seo_data(
		object $product,
		?string $seo_title,
		?string $seo_desc,
		?string $product_category_id
	): void {

		if (
			! is_plugin_active( 'wordpress-seo/wp-seo.php' )
			|| ! isset( self::get_import_settings()['seo'] )
			|| 'yes' !== self::get_import_settings()['seo']
		) {
			return;
		}

		if ( ! empty( $seo_title ) ) {
			$product->update_meta_data( '_yoast_wpseo_title', $seo_title );
		}

		if ( ! empty( $seo_desc ) ) {
			$product->update_meta_data( '_yoast_wpseo_metadesc', $seo_desc );
		}

		if ( ! empty( $product_category_id ) ) {
			$product->update_meta_data( '_yoast_wpseo_primary_product_cat', $product_category_id );
		}
	}
}
