<?php

namespace WPWC\iikoCloud\Async_Actions;

defined( 'ABSPATH' ) || exit;

class Async_Actions_Init {

	/**
	 * Initialization.
	 */
	public static function init() {
		add_action( 'plugins_loaded', [ __CLASS__, 'create_async_objects' ] );
	}

	/**
	 * Create new async objects.
	 *
	 * @return void
	 */
	public static function create_async_objects(): void {
		new Import_Stock_Async();
		new Close_Order_Async();
		new Check_Order_Async();
	}
}
