<?php

namespace WPWC\iikoCloud\API_Requests;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\HTTP_Request;
use WPWC\iikoCloud\Logs;

class Stock_API_Requests extends Common_API_Requests {

	/**
	 * Update nomenclature cache (transients).
	 *
	 * @param $nomenclature
	 * @param $nomenclature_dishes
	 * @param $nomenclature_goods
	 * @param $nomenclature_services
	 * @param $nomenclature_modifiers
	 *
	 * @return bool
	 */
	protected function update_nomenclature_cache(
		$nomenclature,
		$nomenclature_dishes,
		$nomenclature_goods,
		$nomenclature_services,
		$nomenclature_modifiers
	): bool {

		// Delete old nomenclature cache.
		delete_transient( WC_IIKOCLOUD_PREFIX . 'groups' );
		// delete_transient( WC_IIKOCLOUD_PREFIX . 'groups_list' );
		delete_transient( WC_IIKOCLOUD_PREFIX . 'sizes' );
		delete_transient( WC_IIKOCLOUD_PREFIX . 'services' );
		delete_transient( WC_IIKOCLOUD_PREFIX . 'modifiers' );
		delete_transient( WC_IIKOCLOUD_PREFIX . 'modifier_groups_list' );

		// Save cache nomenclature.
		$set_groups_transient = set_transient( WC_IIKOCLOUD_PREFIX . 'groups', $nomenclature['groups'], self::TRANSIENT_EXPIRATION );
		// set_transient( WC_IIKOCLOUD_PREFIX . 'groups_list', $nomenclature['groups_list'], self::TRANSIENT_EXPIRATION );
		set_transient( WC_IIKOCLOUD_PREFIX . 'sizes', $nomenclature['sizes'], self::TRANSIENT_EXPIRATION );
		$this->set_cache_by_chunks( $nomenclature_dishes, 'dishes' );
		$this->set_cache_by_chunks( $nomenclature_goods, 'goods' );
		set_transient( WC_IIKOCLOUD_PREFIX . 'services', $nomenclature_services, self::TRANSIENT_EXPIRATION );
		set_transient( WC_IIKOCLOUD_PREFIX . 'modifiers', $nomenclature_modifiers, self::TRANSIENT_EXPIRATION );
		set_transient( WC_IIKOCLOUD_PREFIX . 'modifier_groups_list', $nomenclature['modifier_groups_list'], self::TRANSIENT_EXPIRATION );

		// Check nomenclature groups caching.
		if ( false === $set_groups_transient ) {
			Logs::add_error( esc_html__( 'Error while caching iiko nomenclature', 'wc-iikocloud' ) );

			return false;
		}

		return true;
	}

	/**
	 * Prepare nomenclature for the import.
	 *
	 * @param $nomenclature
	 *
	 * @return array|bool
	 */
	protected function prepare_nomenclature( $nomenclature ) {

		// Prepare groups tree for the frontend.
		$nomenclature['groupsTree'] = $this->categories_tree( $nomenclature['groups'] );

		if ( empty( $nomenclature['groupsTree'] ) ) {
			Logs::add_error( esc_html__( 'There are not dish groups', 'wc-iikocloud' ) );
		}

		if ( ! is_array( $nomenclature['products'] ) || empty( $nomenclature['products'] ) ) {
			Logs::add_error( esc_html__( 'There are not products', 'wc-iikocloud' ) );
		}

		// Prepare products.
		$nomenclature_dishes    = [];
		$nomenclature_goods     = [];
		$nomenclature_services  = [];
		$nomenclature_modifiers = [];

		foreach ( $nomenclature['products'] as $product ) {

			switch ( $product['type'] ) {
				case 'Dish':
					$nomenclature_dishes[] = $product;
					break;

				case 'Good':
					$nomenclature_goods[] = $product;
					break;

				case 'Service':
					$nomenclature_services[] = $product;
					break;

				case 'Modifier':
					$nomenclature_modifiers[] = $product;
					break;
			}
		}

		// Create simple arrays with only basic data for output on admin page and remove doubled IDs.
		$nomenclature['groups_list']          = array_filter( $nomenclature['groups'], function ( $group, $index ) {
			return $group['isGroupModifier'] === false;
		}, ARRAY_FILTER_USE_BOTH );
		$nomenclature['groups_list']          = array_column( $nomenclature['groups_list'], 'name', 'id' );
		$nomenclature['simple_dishes']        = array_column( $nomenclature_dishes, 'name', 'id' );
		$nomenclature['simple_goods']         = array_column( $nomenclature_goods, 'name', 'id' );
		$nomenclature['simple_services']      = array_column( $nomenclature_services, 'name', 'id' );
		$nomenclature['simple_modifiers']     = array_column( $nomenclature_modifiers, 'name', 'id' );
		$nomenclature['simple_sizes']         = array_column( $nomenclature['sizes'], 'name', 'id' );
		$nomenclature['modifier_groups_list'] = array_filter( $nomenclature['groups'], function ( $group, $index ) {
			return $group['isGroupModifier'] === true;
		}, ARRAY_FILTER_USE_BOTH );
		$nomenclature['modifier_groups_list'] = array_column( $nomenclature['modifier_groups_list'], 'name', 'id' );

		// Save nomenclature cache.
		if (
			false === $this->update_nomenclature_cache(
				$nomenclature,
				$nomenclature_dishes,
				$nomenclature_goods,
				$nomenclature_services,
				$nomenclature_modifiers
			)
		) {
			return false;
		}

		// Save iiko product categories (used for loyalty programs).
		if ( ! empty( $nomenclature['productCategories'] ) ) {

			delete_option( WC_IIKOCLOUD_PREFIX . 'iiko_product_categories' );

			if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'iiko_product_categories', $nomenclature['productCategories'] ) ) {
				Logs::add_error( esc_html__( 'Cannot add iiko product categories to the plugin options', 'wc-iikocloud' ) );
			}
		}

		// Save nomenclature revision.
		if ( ! empty( $nomenclature['revision'] ) ) {

			delete_option( WC_IIKOCLOUD_PREFIX . 'import[nomenclature_revision]' );

			if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'import[nomenclature_revision]', $nomenclature['revision'] ) ) {
				Logs::add_error( esc_html__( 'Cannot add nomenclature revision to the plugin options', 'wc-iikocloud' ) );
			}
		}

		return $nomenclature;
	}

	/**
	 * Prepare menu nomenclature for the import.
	 *
	 * @param $nomenclature
	 *
	 * @return array|bool
	 */
	protected function prepare_menu_nomenclature( $nomenclature ) {

		// Make categories list to output in the plugin page.
		// [ 'uuid' => 'name', ... ]
		$nomenclature_stats['categories'] = array_column( $nomenclature['itemCategories'], 'name', 'id' );

		// Make items list to output in the plugin page.
		foreach ( $nomenclature['itemCategories'] as $category ) {

			if ( empty( $category ) ) {
				continue;
			}

			$nomenclature_stats['items'][ $category['id'] ] = array_column( $category['items'], 'name', 'itemId' );
		}

		// Save cache nomenclature.
		$set_categories_transient = $this->set_cache_by_chunks( $nomenclature['itemCategories'], 'categories', 1 );

		// Check nomenclature categories caching.
		if ( false === $set_categories_transient ) {
			Logs::add_error( esc_html__( 'Error while caching iiko external menu nomenclature', 'wc-iikocloud' ) );

			return false;
		}

		return $nomenclature_stats;
	}

	/**
	 * Build categories (iiko groups) tree.
	 *
	 * @param $categories
	 *
	 * @return array
	 */
	protected function categories_tree( $categories ): array {

		if ( ! is_array( $categories ) || empty( $categories ) ) {
			return [];
		}

		// Sort categories by order field.
		usort( $categories, function ( $a, $b ) {
			return $a['order'] <=> $b['order'];
		} );

		// Make categories tree.
		$child_groups = [];

		foreach ( $categories as &$category ) {

			// Exclude group modifiers.
			if ( true === $category['isGroupModifier'] ) {
				continue;
			}

			if ( is_null( $category['parentGroup'] ) ) {
				$child_groups[0][] = &$category;

			} else {
				$child_groups[ $category['parentGroup'] ][] = &$category;
			}
		}

		unset( $category );

		foreach ( $categories as &$category ) {

			if ( isset( $child_groups[ $category['id'] ] ) ) {
				$category['childGroups'] = $child_groups[ $category['id'] ];

			} else {
				$category['childGroups'] = null;
			}
		}

		return $child_groups[0] ?: [];
	}

	/**
	 * Get nomenclature from iiko.
	 * Save nomenclature revision to the plugin options.
	 * Save nomenclature data to transients.
	 *
	 * @return array|bool
	 */
	public function get_nomenclature( $organization_id = null ) {

		$access_token = $this->get_access_token();

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id_import ) ) {
			$organization_id = $this->organization_id_import;
		}

		if ( false === $access_token || empty( $organization_id ) ) {
			return false;
		}

		$url     = 'nomenclature';
		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [
			'organizationId' => $organization_id,
			'startRevision'  => 0,
		];

		$nomenclature = HTTP_Request::remote_post( $url, $headers, $body );

		if ( ! empty( $nomenclature['groups'] ) ) {
			$nomenclature = $this->prepare_nomenclature( $nomenclature );

		} else {
			Logs::add_error( esc_html__( 'Organization does not have nomenclature groups', 'wc-iikocloud' ) );

			return false;
		}

		return $nomenclature;
	}

	/**
	 * Get external menus from iiko.
	 *
	 * @return array|false
	 */
	public function get_menus() {

		$access_token = $this->get_access_token();

		// Required data for remote post.
		if ( false === $access_token ) {
			return false;
		}

		$url     = 'menu';
		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [];

		return HTTP_Request::remote_post( $url, $headers, $body, '2' );
	}

	/**
	 * Get external menu nomenclature from iiko.
	 * There is no a revision for external menus nomenclature.
	 * Save nomenclature data to transients.
	 *
	 * @return array|bool
	 */
	public function get_menu_nomenclature( $menu_id, $price_category_id = null, $organization_id = null ) {

		$access_token = $this->get_access_token();

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) && ! empty( $this->organization_id_import ) ) {
			$organization_id = $this->organization_id_import;
		}

		if ( false === $access_token || empty( $menu_id ) || empty( $organization_id ) ) {
			return false;
		}

		$url     = 'menu/by_id';
		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [
			// externalMenuId
			// required
			// string
			// External menu id
			// Can be obtained by api/2/menu operation.
			'externalMenuId'  => $menu_id,

			// organizationIds
			// required
			// Array of strings <uuid>
			// Organization IDs.
			// Can be obtained by /api/1/organizations operation.
			'organizationIds' => [ $organization_id ],

			// priceCategoryId
			// string Nullable
			// Price category id.
			// Can be obtained by api/2/menu operation.
			'priceCategoryId' => $price_category_id,

			// version
			// integer <int32> Nullable
			// Version of the result data model.
			'version'         => null,

			// language
			// string Nullable
			// Language of the external menu.
			'language'        => null,

			// startRevision
			// integer <int64> Nullable
			// Start revision.
			'startRevision'   => null,
		];

		$nomenclature = HTTP_Request::remote_post( $url, $headers, $body, '2' );

		if ( ! empty( $nomenclature['itemCategories'] ) ) {
			$nomenclature_stats = $this->prepare_menu_nomenclature( $nomenclature );

		} else {
			Logs::add_error( ! empty( $nomenclature['description'] ) ? $nomenclature['description'] : esc_html__( 'The external menu does not have categories', 'wc-iikocloud' ) );

			return false;
		}

		return $nomenclature_stats;
	}

	/**
	 * Export WooCommerce deliveries/orders to iiko.
	 *
	 * @param  string|null  $organization_id
	 *
	 * @return boolean|array
	 */
	public function out_of_stock_items( ?string $organization_id = null ) {

		$access_token = $this->get_access_token();

		if ( false === $access_token ) {
			return false;
		}

		// Take organization ID from settings if parameter is empty.
		if ( empty( $organization_id ) ) {
			Logs::add_wc_log( 'The organization ID is empty.', 'stop-list', 'error' );

			return false;
		}

		$url     = 'stop_lists';
		$headers = [
			'Authorization' => $access_token,
		];
		$body    = [
			'organizationIds' => [ $organization_id ],
		];

		return HTTP_Request::remote_post( $url, $headers, $body );
	}
}