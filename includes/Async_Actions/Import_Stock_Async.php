<?php

namespace WPWC\iikoCloud\Async_Actions;

defined( 'ABSPATH' ) || exit;

use WP_Background_Process;
use WPWC\iikoCloud\Import\Import_Products;
use WPWC\iikoCloud\Logs;
use WPWC\iikoCloud\Traits\ImportTrait;
use WPWC\iikoCloud\Traits\WCActionsTrait;

class Import_Stock_Async extends WP_Background_Process {

	use ImportTrait;
	use WCActionsTrait;

	/**
	 * Prefix
	 *
	 * @var string
	 */
	protected $prefix = WC_IIKOCLOUD_PREFIX;

	/**
	 * Action
	 *
	 * @var string
	 */
	protected $action = WC_IIKOCLOUD_PREFIX . 'import_stock';

	/**
	 * Product import statistic.
	 *
	 * @var array
	 */
	private array $product_import_statistic = [];

	/**
	 * Get product import statistic.
	 */
	private function get_product_import_statistic(): array {

		if ( empty( $this->product_import_statistic ) ) {

			$this->product_import_statistic = [
				'importedProducts' => 0,
				'excludedProducts' => [],
				'insertedProducts' => [],
				'updatedProducts'  => [],
				'product_ids'      => [],
			];
		}

		return $this->product_import_statistic;
	}

	/**
	 * Task
	 *
	 * Perform any actions required on each queue item.
	 * Return the modified item for further processing in the next pass through.
	 * Or, return false to remove the item from the queue.
	 *
	 * @param  mixed  $item  Queue item to iterate over
	 *
	 * @return false
	 * @throws \WC_Data_Exception
	 */
	protected function task( $item ): bool {

		if ( 'external_menu' === $item['import_source'] ) {

			Import_Products::import_external_menu_product( $item['product_info'], $item['term_id'] );

			// TODO - $this->product_import_statistic

		} else {

			$imported_product = Import_Products::import_product(
				$item['product_info'],
				$item['term_id'],
				$item['modifiers'],
				$item['sizes'],
				$item['modifier_groups_list']
			);

			// TODO - use on the plugin page
			$this->product_import_statistic = $this->update_imported_product_stat( $imported_product, $this->get_product_import_statistic() );
		}

		return false;
	}

	/**
	 * Async import statistic and after import actions.
	 */
	protected function complete() {

		parent::complete();

		Logs::add_wc_log( 'Async import statistic: ' . var_export( $this->get_product_import_statistic(), true ), 'async-import' );

		// Delete all products that are not in the current stock list.
		if (
			! empty( $this->get_product_import_statistic()['product_ids'] )
			&& isset( self::get_import_settings()['delete_old_products'] )
			&& 'yes' === self::get_import_settings()['delete_old_products']
		) {
			$this->delete_old_products( $this->get_product_import_statistic()['product_ids'] );
		}

		// Get and handle stop list for the current organization.
		if (
			isset( self::get_import_settings()['update_stop_list'] )
			&& 'yes' === self::get_import_settings()['update_stop_list']
			&& ! empty( $organization_id_import = sanitize_key( get_option( WC_IIKOCLOUD_PREFIX . 'organization_id_import' ) ) )
		) {
			self::update_products_status_by_stop_list( $organization_id_import );
		}
	}
}