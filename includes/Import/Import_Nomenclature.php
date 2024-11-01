<?php

namespace WPWC\iikoCloud\Import;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\API_Requests\Stock_API_Requests;
use WPWC\iikoCloud\Async_Actions\Import_Stock_Async;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\ImportTrait;

class Import_Nomenclature extends Stock_API_Requests {

	use ImportTrait;

	/**
	 * Product import statistic.
	 *
	 * @var
	 */
	private $product_import_statistic;

	/**
	 * Import method.
	 *
	 * @var string
	 */
	private string $import_method;

	/**
	 * Async import.
	 *
	 * @var bool
	 */
	private bool $is_async_import;

	/**
	 * Reverse import.
	 *
	 * @var bool
	 */
	private bool $is_reverse_groups_import;

	/**
	 * Set out of stock status for products which weren't imported.
	 *
	 * @var bool
	 */
	private bool $set_out_of_stock_status;

	/**
	 * Update stop list.
	 *
	 * @var bool
	 */
	private bool $update_stop_list;

	/**
	 * Delete old products.
	 *
	 * @var bool
	 */
	private bool $delete_old_products;

	/**
	 * Set product into multiple cats.
	 *
	 * @var bool
	 */
	private bool $product_to_multiple_cats;

	/**
	 * @var array
	 */
	private array $error_messages;

	/**
	 * @var null|Import_Stock_Async
	 */
	protected ?Import_Stock_Async $import_products_async;

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->product_import_statistic = [
			'importedProducts' => 0,
			'excludedProducts' => [],
			'insertedProducts' => [],
			'updatedProducts'  => [],
			'product_ids'      => [],
		];
		$this->import_method            = isset( self::get_import_settings()['method'] ) && 'external_menu' === self::get_import_settings()['method']
			? 'external_menu'
			: 'uploading';
		$this->is_async_import          = isset( self::get_import_settings()['async'] ) && 'yes' === self::get_import_settings()['async'];
		$this->is_reverse_groups_import = isset( self::get_import_settings()['reverse_groups'] ) && 'yes' === self::get_import_settings()['reverse_groups'];
		$this->set_out_of_stock_status  = isset( self::get_import_settings()['set_out_of_stock_status'] ) && 'yes' === self::get_import_settings()['set_out_of_stock_status'];
		$this->update_stop_list         = isset( self::get_import_settings()['update_stop_list'] ) && 'yes' === self::get_import_settings()['update_stop_list'];
		$this->delete_old_products      = isset( self::get_import_settings()['delete_old_products'] ) && 'yes' === self::get_import_settings()['delete_old_products'];
		$this->product_to_multiple_cats = isset( self::get_import_settings()['product_to_multiple_cats'] ) && 'yes' === self::get_import_settings()['product_to_multiple_cats'];
		$this->error_messages           = [ 'empty_groups' => esc_html__( 'No groups to import', 'wc-iikocloud' ) ];
		// 1. Async import.
		$this->import_products_async = $this->is_async_import ? new Import_Stock_Async() : null;
	}

	/**
	 * Import iiko nomenclature (groups, dishes, products etc.) to WooCommerce.
	 *
	 * @param array $groups iiko groups to import.
	 * [ i => 'uuid', ... ]
	 *
	 * @return array Count of imported groups and products.
	 * @throws \WC_Data_Exception
	 */
	public function import_nomenclature( array $groups = [] ): array {

		// Get all groups from the cache.
		$all_groups = 'external_menu' === $this->import_method
			? $this->get_cache_by_chunks( 'categories' )
			: $this->get_cache( WC_IIKOCLOUD_PREFIX . 'groups', 'groups' );

		// Import groups.
		$imported_groups = $this->import_groups( $groups, $all_groups );

		if (
			false === $imported_groups
			|| false === self::is_empty_array( $imported_groups['ids'], esc_html__( 'No imported groups', 'wc-iikocloud' ) )
		) {
			return [
				'importedGroups'   => 0,
				'importedProducts' => 0,
				'excludedProducts' => 0,
				'insertedProducts' => 0,
				'updatedProducts'  => 0,
			];
		}

		if ( 'external_menu' === $this->import_method ) {
			return [
				'importedGroups'   => isset( $imported_groups['ids'] ) ? count( $imported_groups['ids'] ) : 0,
				'importedProducts' => $imported_groups['importedProducts'] ?? '',
				'excludedProducts' => $imported_groups['excludedProducts'] ?? '',
				'insertedProducts' => $imported_groups['insertedProducts'] ?? '',
				'updatedProducts'  => $imported_groups['updatedProducts'] ?? '',
			];

		} else {

			if ( ! isset( $imported_groups['ids'] ) ) {
				return [
					'importedGroups'   => 0,
					'importedProducts' => 0,
					'excludedProducts' => 0,
					'insertedProducts' => 0,
					'updatedProducts'  => 0,
				];
			}

			$import_start_time = microtime( true );

			$product_import_statistic = $this->import_products( $imported_groups['ids'] );

			Logs::add_wc_log( 'Products import time: ' . sprintf( '%.6f sec.', microtime( true ) - $import_start_time ), 'import' );

			return [
				'importedGroups'   => count( $imported_groups['ids'] ),
				'importedProducts' => $product_import_statistic['importedProducts'] ?? '',
				'excludedProducts' => $product_import_statistic['excludedProducts'] ?? '',
				'insertedProducts' => $product_import_statistic['insertedProducts'] ?? '',
				'updatedProducts'  => $product_import_statistic['updatedProducts'] ?? '',
			];
		}
	}

	/**
	 * Import iiko groups as WooCommerce product categories.
	 *
	 * @param array $groups iiko groups to import [ i => 'uuid', ... ]
	 * @param false|array $all_groups all iiko groups (categories for external menu).
	 *
	 * @return false|array Array with product category IDs as keys and iiko group IDs as corresponding values:
	 * [ 'term_id' => 'iiko_group_id', ... ].
	 * False if there are no groups in the cache.
	 */
	protected function import_groups( array $groups, $all_groups ) {

		$imported_groups = [];

		// Check groups from cache.
		if ( false === self::is_empty_array( $all_groups,
				esc_html__( 'Nomenclature cache is empty. Get the nomenclature from iiko again or check import method in the plugin settings', 'wc-iikocloud' ) )
		) {
			return false;
		}

		// Change $groups array keys onto iiko groups ids.
		// [ 'iiko_group_id' => iiko_group[], ... ]
		$all_groups = array_column( $all_groups, null, 'id' );

		if ( empty( $this->check_groups_for_import( $groups ) ) ) {
			return false;
		}

		// Set status 'Out of stock' for all products.
		if ( 'external_menu' === $this->import_method && $this->set_out_of_stock_status ) {
			self::change_products_stock_status( 0, 'outofstock' );
		}

		// Import groups.
		foreach ( $groups as $group_id ) {

			// Get and check full iiko group data.
			$group_data = $this->prepare_iiko_groups( $all_groups[ $group_id ] );

			if ( false === $group_data ) {
				continue;
			}

			$imported_product_cat = Import_Categories::import_product_category( $group_data );

			if ( false === $imported_product_cat ) {
				$message = sprintf( esc_html__( 'Cannot import product category %s', 'wc-iikocloud' ), sanitize_text_field( $group_data['name'] ) );

				Logs::add_error( $message );
				Logs::add_wc_log( $message, 'import', 'error' );

				continue;
			}

			// [ 'term_id' => 'iiko_group_id', ... ]
			$imported_groups['ids'][ $imported_product_cat['term_id'] ] = $imported_product_cat['iiko_id'];

			// Import external menu products.
			if ( 'external_menu' === $this->import_method ) {

				$import_start_time = microtime( true );

				$product_import_statistic = $this->import_external_menu_products( $all_groups[ $group_id ]['items'], $imported_product_cat['term_id'] );

				Logs::add_wc_log( 'Products import time: ' . sprintf( '%.6f sec.', microtime( true ) - $import_start_time ), 'import' );

				$imported_groups = array_merge( $imported_groups, $product_import_statistic );
			}
		}

		if ( 'external_menu' === $this->import_method ) {

			//  Delete all products that are not in the current stock list.
			if ( $this->delete_old_products ) {
				$this->delete_old_products( $this->product_import_statistic['product_ids'] );
			}

			// Get and handle stop list for the current organization.
			if ( $this->update_stop_list ) {
				self::update_products_status_by_stop_list( $this->organization_id_import );
			}
		}

		return $imported_groups;
	}

	/**
	 * Import iiko dishes, goods, modifiers and sizes as WooCommerce products.
	 *
	 * @param array $groups iiko groups to import.
	 *
	 * @return false|array Imported products count.
	 * @throws \WC_Data_Exception
	 */
	protected function import_products( array $groups ) {

		// Get all products from the cache.
		// $groups_list          = $this->get_cache( WC_IIKOCLOUD_PREFIX . 'groups_list', 'groups list' );
		$dishes               = $this->get_cache_by_chunks( 'dishes' );
		$goods                = $this->get_cache_by_chunks( 'goods' );
		$services             = $this->get_cache( WC_IIKOCLOUD_PREFIX . 'services', 'services' );
		$modifiers            = $this->get_cache( WC_IIKOCLOUD_PREFIX . 'modifiers', 'modifiers' );
		$modifier_groups_list = $this->get_cache( WC_IIKOCLOUD_PREFIX . 'modifier_groups_list', 'modifier groups list' );
		$sizes                = $this->get_cache( WC_IIKOCLOUD_PREFIX . 'sizes', 'sizes' );

		// Check nomenclature.
		// $groups_list          = false !== self::is_empty_array( $groups_list, esc_html__( 'Simple groups cache is empty', 'wc-iikocloud' ) ) ? $groups_list : [];
		$modifier_groups_list = false !== self::is_empty_array( $modifier_groups_list, esc_html__( 'Simple modifier groups cache is empty', 'wc-iikocloud' ) ) ? $modifier_groups_list : [];
		$dishes               = false !== self::is_empty_array( $dishes, esc_html__( 'Dishes cache is empty', 'wc-iikocloud' ) ) ? $dishes : [];
		$goods                = false !== self::is_empty_array( $goods, esc_html__( 'Goods cache is empty', 'wc-iikocloud' ), 'notice' ) ? $goods : [];
		$services             = false !== self::is_empty_array( $services, esc_html__( 'Services cache is empty', 'wc-iikocloud' ), 'notice' ) ? $services : [];

		// Change keys onto iiko IDs and remove doubled IDs.
		$modifiers = false !== self::is_empty_array( $modifiers, esc_html__( 'Modifiers cache is empty', 'wc-iikocloud' ), 'notice', false ) ? array_column( $modifiers, null, 'id' ) : [];
		$sizes     = false !== self::is_empty_array( $sizes, esc_html__( 'Sizes cache is empty', 'wc-iikocloud' ), 'notice', false ) ? array_column( $sizes, null, 'id' ) : [];

		// Import dishes, goods and services.
		$products = array_merge( $dishes, $goods, $services );

		// Check products.
		if ( false === self::is_empty_array( $products, esc_html__( 'Products array is empty', 'wc-iikocloud' ) ) ) {
			return false;
		}

		// Set status 'Out of stock' for all products. Before import if stop list is not used.
		if (
			( ! $this->update_stop_list )
			&& $this->set_out_of_stock_status

		) {
			self::change_products_stock_status( 0, 'outofstock' );
		}

		/*
		 * Simple method.
		 * Each product is related to a single group.
		 */
		if ( ! $this->product_to_multiple_cats ) {
			// Create array with all product IDs and the relevant groups ID.
			// If a product contains in several groups it will be left only for the last group.
			// [ 'product_iiko_id' => 'group_iiko_id', ... ]
			$product_group_iiko_ids = array_column( $products, 'parentGroup', 'id' );

			// Reindex products array in order to replace indexes by iiko IDs and remove doubles.
			// [ 'product_iiko_id' => [], ... ]
			$products = array_column( $products, null, 'id' );

			// Import groups.
			foreach ( $groups as $term_id => $group_iiko_id ) {

				// Create array of related to the group products.
				// [ i => 'product_iiko_id', ... ]
				$related_products_iiko_ids = array_keys( $product_group_iiko_ids, $group_iiko_id );

				// Add related to each group products into WooCommerce.
				$i = 1;
				foreach ( $related_products_iiko_ids as $related_product_iiko_id ) {

					$related_product = $products[ $related_product_iiko_id ];

					if ( ! is_array( $related_product ) || empty( $related_product ) ) {
						$message = esc_html__( 'Product information is empty', 'wc-iikocloud' );

						Logs::add_error( $message );
						Logs::add_wc_log( $message, 'import', 'error' );

						continue;
					}

					$this->product_import_statistic = $this->import_product(
						$related_product,
						$term_id,
						$modifiers,
						$sizes,
						$modifier_groups_list
					);

					// 3. Async import.
					$this->async_import_dispatch( $i );

					$i ++;
				}
			}

			/*
			 * Advanced method.
			 * Each product can be contained in several groups.
			 */
		} else {
			// Create array with all product IDs and the relevant groups IDs.
			// [ 'product_iiko_id' => [ 'group_iiko_1_id', 'group_iiko_2_id', ... ], ... ]
			$products_groups_iiko_ids = $this->find_related_groups( $products );

			// Import groups.
			foreach ( $groups as $term_id => $group_iiko_id ) {

				// Find products for each imported group.
				$i = 1;
				foreach ( $products_groups_iiko_ids as $product_iiko_id => $groups_iiko_ids ) {

					if ( ! in_array( $group_iiko_id, $groups_iiko_ids ) ) {
						continue;
					}

					// Find (the first) product in $products array with the required ID.
					$related_product = $products[ array_search( $product_iiko_id, array_column( $products, 'id' ) ) ];

					$this->product_import_statistic = $this->import_product(
						$related_product,
						$term_id,
						$modifiers,
						$sizes,
						$modifier_groups_list
					);

					// 3. Async import.
					$this->async_import_dispatch( $i );

					$i ++;
				}
			}
		}

		// 4. Async import.
		// Save and dispatch async process.
		// The Last dispatch for the last batch that less then the batch size.
		if ( $this->is_async_import ) {
			$this->import_products_async->save()->dispatch();

			// After import actions (for direct import only).
			// For async import the method Import_Stock_Async->complete() is used;
		} else {
			// Delete all iiko products that are not in the current iiko stock list.
			if ( isset( $this->product_import_statistic['product_ids'] ) && $this->delete_old_products ) {
				$this->delete_old_products( $this->product_import_statistic['product_ids'] );
			}

			// Get and handle stop list for the current organization.
			// Set status 'Out of stock' for all products doesn't have any sense because of
			// update_products_status_by_stop_list() method witch enable all disabled products first.
			if ( $this->update_stop_list ) {
				self::update_products_status_by_stop_list( $this->organization_id_import );
			}
		}

		return $this->product_import_statistic;
	}

	/**
	 * Import external menu products as WooCommerce products.
	 *
	 * @param array $products iiko products to import.
	 * @param int $product_category_id Product category ID.
	 *
	 * @return array Imported products count.
	 * @throws \WC_Data_Exception
	 */
	protected function import_external_menu_products( array $products, int $product_category_id ): array {

		// Reindex products array in order to replace indexes by iiko IDs and remove doubles.
		// [ 'product_iiko_id' => [], ... ]
		$products = array_column( $products, null, 'id' );

		$i = 1;
		foreach ( $products as $product_info ) {

			if ( ! is_array( $product_info ) || empty( $product_info ) ) {
				$message = esc_html__( 'Product information is empty', 'wc-iikocloud' );

				Logs::add_error( $message );
				Logs::add_wc_log( $message, 'import', 'error' );

				continue;
			}

			// 2e. Async import.
			if ( $this->is_async_import ) {

				$this->import_products_async->push_to_queue( [
					'import_source'        => 'external_menu',
					'product_info'         => $product_info,
					'term_id'              => $product_category_id,
					'modifiers'            => null,
					'sizes'                => null,
					'modifier_groups_list' => null,
				] );

			} else {
				$imported_product               = Import_Products::import_external_menu_product( $product_info, $product_category_id );
				$this->product_import_statistic = $this->update_imported_product_stat( $imported_product, $this->product_import_statistic );
			}

			// 3e. Async import.
			$this->async_import_dispatch( $i );

			$i ++;
		}

		// 4e. Async import.
		// Save and dispatch async process.
		if ( $this->is_async_import ) {
			$this->import_products_async->save()->dispatch();
		}

		return $this->product_import_statistic;
	}

	/**
	 * Check groups for import.
	 *
	 * @param array $groups iiko groups to import.
	 *
	 * @return false|array Checked groups.
	 */
	private function check_groups_for_import( array $groups ) {

		// Check transferred array of groups to import.
		if ( false === self::is_empty_array( $groups, $this->error_messages['empty_groups'] ) ) {
			// Get last chosen groups from the plugin options.
			// [ 'iiko_group_name' => 'uuid', ... ]
			$groups = array_flip( wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'chosen_groups' ) ) );
		}

		// Check saved array of groups to import.
		if ( false === self::is_empty_array( $groups, $this->error_messages['empty_groups'] ) ) {
			Logs::add_error( $this->error_messages['empty_groups'] );
			Logs::add_wc_log( $this->error_messages['empty_groups'], 'import', 'error' );

			return false;
		}

		// Sanitize IDs of groups to import.
		// [ i => 'uuid', ...]
		$this->sanitize_ids( $groups, 'Group' );

		if ( $this->is_reverse_groups_import ) {
			$groups = array_reverse( $groups );
		}

		return $groups;
	}

	/**
	 * Check group data.
	 *
	 * @param $group
	 *
	 * @return false|array Checked group data array with keys:
	 * id - group iiko ID
	 * name - group name
	 * desc - group description
	 * thumb_urls - array of group images
	 * seo_title - group seo title
	 * seo_desc - group seo description
	 */
	private function prepare_iiko_groups( $group ) {

		if ( ! is_array( $group ) || empty( $group ) ) {
			$message = esc_html__( 'Product category information is empty', 'wc-iikocloud' );

			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'import', 'error' );

			return false;
		}

		// Product category main data.
		$group_data['id']         = sanitize_key( $group['id'] );
		$group_data['name']       = sanitize_text_field( $group['name'] );
		$group_data['desc']       = isset( $group['description'] ) ? wp_strip_all_tags( $group['description'] ) : '';
		$group_data['thumb_urls'] = isset( $group['buttonImageUrl'] ) ? [ esc_url( $group['buttonImageUrl'] ) ] : [];
		$group_data['seo_title']  = '';
		$group_data['seo_desc']   = '';

		// Special data and checks.
		if ( 'external_menu' !== $this->import_method ) {

			$group_data['thumb_urls'] = $group['imageLinks']; // TODO - check esc_url()
			$group_data['seo_title']  = isset( $group['seoTitle'] ) ? sanitize_text_field( $group['seoTitle'] ) : null;
			$group_data['seo_desc']   = isset( $group['seoText'] ) ? sanitize_text_field( $group['seoText'] ) : null;

			// Skip the excluded group.
			if ( true !== $group['isIncludedInMenu'] ) {
				$message = sprintf( esc_html__( 'Product category %s is excluded in iiko', 'wc-iikocloud' ), $group_data['name'] );

				Logs::add_error( $message );
				Logs::add_wc_log( $message, 'import', 'error' );

				return false;
			}

			// Skip the modifier groups.
			if ( true === $group['isGroupModifier'] ) {
				$message = sprintf( esc_html__( 'Product category %s is a group modifier', 'wc-iikocloud' ), $group_data['name'] );

				Logs::add_error( $message );
				Logs::add_wc_log( $message, 'import', 'error' );

				return false;
			}

			// Skip the deleted group.
			if ( true === $group['isDeleted'] ) {
				$message = sprintf( esc_html__( 'Product category %s is deleted in iiko', 'wc-iikocloud' ), $group_data['name'] );

				Logs::add_error( $message );
				Logs::add_wc_log( $message, 'import', 'error' );

				return false;
			}

		} else {

			if ( ! is_array( $group['items'] ) || empty( $group['items'] ) ) {
				$message = sprintf( esc_html__( 'Product category %s does not contain products', 'wc-iikocloud' ), $group_data['name'] );

				Logs::add_error( $message );
				Logs::add_wc_log( $message, 'import', 'error' );

				return false;
			}
		}

		return $group_data;
	}

	/**
	 * Create array of products iiko IDs and their related groups iiko IDs.
	 *
	 * @param array $products
	 *
	 * @return array Multidimensional array [ 'iiko_product_id' => [ 'iiko_group_1_id', ... ], ... ]
	 */
	private function find_related_groups( array $products ): array {

		$products_groups = [];

		foreach ( $products as $current_product ) {

			if ( ! is_array( $current_product ) || ! isset( $current_product['id'] ) ) {
				continue;
			}

			$current_product_id = $current_product['id'];

			foreach ( $products as $product ) {

				if ( $current_product_id === $product['id'] ) {

					if ( isset( $products_groups[ $current_product_id ] ) && is_array( $products_groups[ $current_product_id ] ) ) {
						$products_groups[ $current_product_id ][] = $product['parentGroup'];

					} else {
						$products_groups[ $current_product_id ] = [ $product['parentGroup'] ];
					}
				}
			}
		}

		return array_map( 'array_unique', $products_groups );
	}

	/**
	 * Import a product.
	 *
	 * @param array $product_info Product data.
	 * @param int $product_category_id Product category ID.
	 * @param array $modifiers All products modifiers.
	 * @param array $sizes All products sizes.
	 * @param array $modifier_groups_list All modifier groups list. modifier_group_id => group_name.
	 *
	 * @return false|array
	 * @throws \WC_Data_Exception
	 */
	private function import_product(
		array $product_info,
		$product_category_id,
		array $modifiers,
		array $sizes,
		array $modifier_groups_list
	) {

		// 2. Async import.
		if ( $this->is_async_import ) {

			$this->import_products_async->push_to_queue( [
				'import_source'        => 'unloading',
				'product_info'         => $product_info,
				'term_id'              => $product_category_id,
				'modifiers'            => $modifiers,
				'sizes'                => $sizes,
				'modifier_groups_list' => $modifier_groups_list,
			] );

			return false;
		}

		// Normal import.
		$imported_product = Import_Products::import_product(
			$product_info,
			$product_category_id,
			$modifiers,
			$sizes,
			$modifier_groups_list
		);

		return $this->update_imported_product_stat( $imported_product, $this->product_import_statistic );
	}

	/**
	 * Async import dispatch.
	 *
	 * @param int $i Number of current item.
	 * @param int $batch_size A batch size. Break the process into batches of $batch_size products. Default 5.
	 *
	 * @return void
	 */
	private function async_import_dispatch( int $i, int $batch_size = 5 ): void {

		if ( $this->is_async_import && ( $i % $batch_size === 0 ) ) {

			$this->import_products_async->save()->dispatch();

			unset( $this->import_products_async );

			$this->import_products_async = new Import_Stock_Async();
		}
	}
}
