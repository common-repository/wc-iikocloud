<?php
/**
 * Plugin Name: iikoCloud integration for WooCommerce
 * Requires Plugins: woocommerce
 * Plugin URI: https://wpwc.ru
 * Description: Integration of the basic functionality of the iikoCloud API into WooCommerce: import of categories and products (sizes and modifiers) and export of orders.
 * Version: 2.5.3
 * Author: WPWC
 * Author URI: https://profiles.wordpress.org/makspostal/
 * Text Domain: wc-iikocloud
 * Domain Path: /languages
 *
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * WC requires at least: 6.0
 * WC tested up to: 9.3
 *
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WPWC\iikoCloud
 */

namespace WPWC\iikoCloud;

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/vendor/autoload.php';

use WPWC\iikoCloud\Admin\{
	MetaFields\ID,
	MetaFields\KBZHU,
	Admin,
	Inactive,
	Orders,
	Page,
	Settings
};
use WPWC\iikoCloud\Async_Actions\Async_Actions_Init;
use WPWC\iikoCloud\Export\Export;
use WPWC\iikoCloud\Frontend\Shortcodes;

final class WPWC_iikoCloud {

	/**
	 * Plugin version
	 */
	const VERSION = '2.5.3';

	/**
	 * Plugin name
	 */
	const PLUGIN_NAME = 'iikoCloud integration for WooCommerce';

	/**
	 * Minimum PHP Version
	 *
	 * @since 2.5.0
	 * @var string Minimum PHP version required to run the plugin.
	 */
	const MINIMUM_PHP_VERSION = '7.4.0';

	/**
	 * Instance
	 *
	 * @since 2.5.0
	 * @var WPWC_iikoCloud The single instance of the class.
	 */
	private static ?WPWC_iikoCloud $_instance = null;

	/**
	 * Instance
	 *
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return WPWC_iikoCloud An instance of the class.
	 * @since 2.5.0
	 */
	public static function instance(): ?WPWC_iikoCloud {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * Perform some compatibility checks to make sure basic requirements are meet.
	 * If all compatibility checks pass, initialize the functionality.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		! defined( 'WC_IIKOCLOUD_PREFIX' ) && define( 'WC_IIKOCLOUD_PREFIX', 'wc_iikocloud_' );
		! defined( 'WC_IIKOCLOUD_FILE' ) && define( 'WC_IIKOCLOUD_FILE', __FILE__ );
		! defined( 'WC_IIKOCLOUD_SLUG' ) && define( 'WC_IIKOCLOUD_SLUG', dirname( plugin_basename( WC_IIKOCLOUD_FILE ) ) );
		! defined( 'WC_IIKOCLOUD_VERSION' ) && define( 'WC_IIKOCLOUD_VERSION', self::VERSION );
		! defined( 'WC_IIKOCLOUD_DOMAIN' ) && define( 'WC_IIKOCLOUD_DOMAIN', esc_html__( 'wpwc.ru', 'wc-iikocloud' ) );
		! defined( 'WC_IIKOCLOUD_ALLOWED_HTML' )
		&& define( 'WC_IIKOCLOUD_ALLOWED_HTML',
			array_merge_recursive(
				wp_kses_allowed_html( 'post' ),
				[
					'a'        => [ 'type' => true ],
					'form'     => [
						'id'     => true,
						'class'  => true,
						'method' => true,
						'action' => true,
					],
					'input'    => [
						'id'          => true,
						'class'       => true,
						'name'        => true,
						'type'        => true,
						'placeholder' => true,
						'value'       => true,
						'min'         => true,
						'max'         => true,
						'step'        => true,
						'readonly'    => true,
						'disabled'    => true,
						'checked'     => true,
					],
					'select'   => [
						'id'       => true,
						'class'    => true,
						'name'     => true,
						'multiple' => true,
						'size'     => true,
					],
					'datalist' => [ 'id' => true ],
					'option'   => [
						'value'         => true,
						'data-streetid' => true,
					],
					'style'    => true,
				]
			)
		);

		add_action( 'init', [ $this, 'i18n' ] );

		if ( $this->is_compatible() ) {
			add_action( 'woocommerce_loaded', [ $this, 'init' ] );
		}
	}

	/**
	 * Load plugin textdomain
	 *
	 * @return void
	 */
	public function i18n() {
		load_plugin_textdomain( 'wc-iikocloud', false, WC_IIKOCLOUD_SLUG . '/languages' );
	}

	/**
	 * Compatibility Checks
	 *
	 * Checks whether the site meets the plugin requirement.
	 *
	 * @return bool
	 * @since 2.5.0
	 */
	public function is_compatible(): bool {

		$wc_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';

		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			return false;
		}

		if ( ! function_exists( 'wp_get_active_network_plugins' ) ) {
			require_once( ABSPATH . 'wp-includes/ms-load.php' );
		}

		if (
			! ( in_array( $wc_path, wp_get_active_and_valid_plugins() )
			    || in_array( $wc_path, wp_get_active_network_plugins() ) )
		) {
			new Inactive( self::PLUGIN_NAME, 'Woocommerce' );

			return false;
		}

		return true;
	}

	/**
	 * Initialize
	 *
	 * @since 2.5.0
	 */
	public function init() {

		add_action( 'before_woocommerce_init', function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WC_IIKOCLOUD_FILE, true );
			}
		} );

		Logs::init();
		Async_Actions_Init::init();
		Shortcodes::init();
		new Export();

		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', function ( $settings ) {
				$settings[] = new Settings();

				return $settings;
			} );

			Admin::init();
			new Page();
			new ID();
			new KBZHU();
			new Orders();
		}

		do_action( 'wc_iikocloud_loaded' );
	}
}

WPWC_iikoCloud::instance();