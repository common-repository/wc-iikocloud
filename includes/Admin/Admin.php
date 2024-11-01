<?php

namespace WPWC\iikoCloud\Admin;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\API_Requests\AJAX_API_Requests;
use WPWC\iikoCloud\Export\Manual_Order_Actions;

class Admin {

	public static string $plugin_basename;

	/**
	 * Initialization.
	 */
	public static function init() {

		self::$plugin_basename = plugin_basename( WC_IIKOCLOUD_FILE );

		register_activation_hook( WC_IIKOCLOUD_FILE, [ __CLASS__, 'activation' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'add_admin_styles_scripts' ] );
		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar_menu' ], 100 );
		add_filter( 'plugin_action_links_' . self::$plugin_basename, [ __CLASS__, 'add_plugin_action_links' ] );
		add_filter( 'plugin_row_meta', [ __CLASS__, 'add_plugin_meta_links' ], 10, 2 );
		// TODO - "network_admin_plugin_action_links_{$plugin_basename}"
		add_action( 'init', [ __CLASS__, 'register_ajax' ] );
		add_action( 'in_plugin_update_message-' . self::$plugin_basename, function ( $plugin_data ) {
			self::version_update_warning( WC_IIKOCLOUD_VERSION, $plugin_data['new_version'] );
		} );
	}

	/**
	 * Plugin activation.
	 */
	public static function activation() {
		add_option( WC_IIKOCLOUD_PREFIX . 'timeout', 15 );
		add_option( WC_IIKOCLOUD_PREFIX . 'debug_mode', 'yes' );
		add_option( WC_IIKOCLOUD_PREFIX . 'import[method]', 'uploading' );
		add_option( WC_IIKOCLOUD_PREFIX . 'import[images]', 'yes' );
		add_option( WC_IIKOCLOUD_PREFIX . 'import[descriptions]', 'yes' );
		add_option( WC_IIKOCLOUD_PREFIX . 'import[seo]', 'yes' );
		add_option( WC_IIKOCLOUD_PREFIX . 'import[sale_prices]', 'yes' );
		add_option( WC_IIKOCLOUD_PREFIX . 'import[vars_limit]', 50 );
		add_option( WC_IIKOCLOUD_PREFIX . 'import[delete_product_attrs_vars]', 'yes' );
		add_option( WC_IIKOCLOUD_PREFIX . 'export[type]', 'deliveries' );
		add_option( WC_IIKOCLOUD_PREFIX . 'export[check_orders]', 'yes' );
		add_option( WC_IIKOCLOUD_PREFIX . 'export[prices]', 'yes' );
	}

	/**
	 * Enqueue admin styles and scripts.
	 */
	public static function add_admin_styles_scripts() {

		wp_enqueue_style(
			WC_IIKOCLOUD_SLUG . '-admin',
			plugin_dir_url( WC_IIKOCLOUD_FILE ) . 'assets/css/admin.css',
			[],
			WC_IIKOCLOUD_VERSION
		);

		wp_enqueue_script(
			WC_IIKOCLOUD_SLUG . '-admin',
			plugin_dir_url( WC_IIKOCLOUD_FILE ) . 'assets/js/admin.js',
			[ 'jquery' ],
			WC_IIKOCLOUD_VERSION,
			true
		);

		wp_localize_script(
			WC_IIKOCLOUD_SLUG . '-admin',
			'wc_iikocloud',
			[
				'organization_title' => esc_html__( 'ORGANIZATIONS', 'wc-iikocloud' ),
				'chose_organization' => esc_html__( 'Chose organization', 'wc-iikocloud' ),
				'terminals_title'    => esc_html__( 'TERMINALS', 'wc-iikocloud' ),
				'chose_terminals'    => esc_html__( 'Chose terminals', 'wc-iikocloud' ),
				'menu_title'         => esc_html__( 'EXTERNAL MENUS', 'wc-iikocloud' ),
				'chose_menu'         => esc_html__( 'Chose menu', 'wc-iikocloud' ),
				'chose_groups'       => esc_html__( 'Chose groups', 'wc-iikocloud' ),
				'groups_title'       => esc_html__( 'GROUPS', 'wc-iikocloud' ),
				'dishes_title'       => esc_html__( 'DISHES', 'wc-iikocloud' ),
				'goods_title'        => esc_html__( 'GOODS', 'wc-iikocloud' ),
				'services_title'     => esc_html__( 'SERVICES', 'wc-iikocloud' ),
				'modifiers_title'    => esc_html__( 'MODIFIERS', 'wc-iikocloud' ),
				'sizes_title'        => esc_html__( 'SIZES', 'wc-iikocloud' ),
				'categories_title'   => esc_html__( 'CATEGORIES', 'wc-iikocloud' ),
				'cities_title'       => esc_html__( 'CITIES', 'wc-iikocloud' ),
				'chose_cities'       => esc_html__( 'Chose cities', 'wc-iikocloud' ),
			]
		);
	}

	/**
	 * Add menu items to the admin bar.
	 */
	public static function admin_bar_menu( $wp_admin_bar ) {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$wp_admin_bar->add_menu( [
			'id'    => 'wc-iikocloud',
			'title' => esc_html__( 'iikoCloud', 'wc-iikocloud' ),
			'href'  => esc_url( admin_url( 'admin.php?page=wc_iikocloud' ) ),
		] );

		$wp_admin_bar->add_menu( [
			'parent' => 'wc-iikocloud',
			'id'     => 'wc-iikocloud-settings',
			'title'  => esc_html__( 'Settings', 'wc-iikocloud' ),
			'href'   => esc_url( admin_url( 'admin.php?page=wc-settings&tab=wc_iikocloud_settings' ) ),
		] );

		$wp_admin_bar->add_menu( [
			'parent' => 'wc-iikocloud-settings',
			'id'     => 'wc-iikocloud-settings-general',
			'title'  => esc_html__( 'General', 'wc-iikocloud' ),
			'href'   => esc_url( admin_url( 'admin.php?page=wc-settings&tab=wc_iikocloud_settings' ) ),
		] );

		$wp_admin_bar->add_menu( [
			'parent' => 'wc-iikocloud-settings',
			'id'     => 'wc-iikocloud-settings-import',
			'title'  => esc_html_x( 'Import', 'Tab name', 'wc-iikocloud' ),
			'href'   => esc_url( admin_url( 'admin.php?page=wc-settings&tab=wc_iikocloud_settings&section=import' ) ),
		] );

		$wp_admin_bar->add_menu( [
			'parent' => 'wc-iikocloud-settings',
			'id'     => 'wc-iikocloud-settings-export',
			'title'  => esc_html__( 'Export', 'wc-iikocloud' ),
			'href'   => esc_url( admin_url( 'admin.php?page=wc-settings&tab=wc_iikocloud_settings&section=export' ) ),
		] );

		$wp_admin_bar->add_menu( [
			'parent' => 'wc-iikocloud',
			'id'     => 'wc-iikocloud-logs',
			'title'  => esc_html__( 'Logs', 'wc-iikocloud' ),
			'href'   => esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ),
		] );
	}

	/**
	 * Return plugin action links.
	 *
	 * @return array
	 */
	public static function plugin_action_links(): array {

		if ( ! defined( 'WC_IIKOCLOUD_MODULES_FILE' ) ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank"><b>%s</b></a>',
				esc_url( 'https://' . WC_IIKOCLOUD_DOMAIN ),
				esc_html__( 'Get Premium', 'wc-iikocloud' )
			);
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc_iikocloud' ) ),
			esc_html__( 'Control', 'wc-iikocloud' )
		);
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=wc_iikocloud_settings' ) ),
			esc_html__( 'Settings', 'wc-iikocloud' )
		);
		$links[] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( 'https://docs.' . WC_IIKOCLOUD_DOMAIN ),
			esc_html__( 'Documentation', 'wc-iikocloud' )
		);

		return $links;
	}

	/**
	 * Return plugin meta links.
	 *
	 * @return array
	 */
	public static function plugin_meta_links(): array {

		$link = ! defined( 'WC_IIKOCLOUD_MODULES_FILE' )
			? esc_url( 'https://wordpress.org/support/plugin/wc-iikocloud/' )
			: 'mailto:hi@' . WC_IIKOCLOUD_DOMAIN . '?subject=iikoCloud plugin v.' . WC_IIKOCLOUD_VERSION;

		return [
			sprintf(
				'<a href="%s" target="_blank"><span class="dashicons dashicons-star-filled" aria-hidden="true" style="font-size:14px;line-height:1.3"></span>%s</a>',
				$link,
				esc_html__( 'Support', 'wc-iikocloud' )
			)
		];
	}

	/**
	 * Add plugin action links.
	 *
	 * @param $links
	 *
	 * @return array
	 */
	public static function add_plugin_action_links( $links ): array {
		return array_merge( self::plugin_action_links(), $links );
	}

	/**
	 * Add plugin meta links.
	 *
	 * @param array $plugin_meta
	 * @param $plugin_file
	 *
	 * @return array
	 */
	public static function add_plugin_meta_links( array $plugin_meta, $plugin_file ): array {
		return self::$plugin_basename === $plugin_file ? array_merge( $plugin_meta, self::plugin_meta_links() ) : $plugin_meta;
	}

	/**
	 * Register admin AJAX.
	 */
	public static function register_ajax() {
		if ( is_admin() ) {
			$api_requests         = new AJAX_API_Requests();
			$manual_order_actions = new Manual_Order_Actions();

			// Remove access token.
			add_action( 'wp_ajax_wc_iikocloud__remove_access_token_ajax', [ $api_requests, 'remove_access_token_ajax' ] );

			// Get organizations from iiko.
			add_action( 'wp_ajax_wc_iikocloud__get_organizations_ajax', [ $api_requests, 'get_organizations_ajax' ] );

			// Save organization for import.
			add_action( 'wp_ajax_wc_iikocloud__save_organization_import_ajax', [ $api_requests, 'save_organization_import_ajax' ] );

			// Get terminals from iiko.
			add_action( 'wp_ajax_wc_iikocloud__get_terminals_ajax', [ $api_requests, 'get_terminals_ajax' ] );

			// Save organization and terminals for export.
			add_action( 'wp_ajax_wc_iikocloud__save_organization_terminals_export_ajax', [ $api_requests, 'save_organization_terminals_export_ajax' ] );

			// Get nomenclature from iiko.
			add_action( 'wp_ajax_wc_iikocloud__get_nomenclature_ajax', [ $api_requests, 'get_nomenclature_ajax' ] );

			// Get menus from iiko.
			add_action( 'wp_ajax_wc_iikocloud__get_menus_ajax', [ $api_requests, 'get_menus_ajax' ] );

			// Get menu nomenclature from iiko.
			add_action( 'wp_ajax_wc_iikocloud__get_menu_nomenclature_ajax', [ $api_requests, 'get_menu_nomenclature_ajax' ] );

			// Import groups and products to WooCommerce.
			add_action( 'wp_ajax_wc_iikocloud__import_nomenclature_ajax', [ $api_requests, 'import_nomenclature_ajax' ] );

			// Save groups for auto import to WooCommerce.
			add_action( 'wp_ajax_wc_iikocloud__save_groups_ajax', [ $api_requests, 'save_groups_ajax' ] );

			// Get cities from iiko.
			add_action( 'wp_ajax_wc_iikocloud__get_cities_ajax', [ $api_requests, 'get_cities_ajax' ] );

			// Get streets from iiko.
			add_action( 'wp_ajax_wc_iikocloud__get_streets_ajax', [ $api_requests, 'get_streets_ajax' ] );

			// Export order to iiko manually.
			add_action( 'wp_ajax_wc_iikocloud_export_order', [ $manual_order_actions, 'export_order_manually' ] );

			// Check created delivery.
			add_action( 'wp_ajax_wc_iikocloud_check_created_delivery', [ $manual_order_actions, 'check_created_delivery_manually' ] );
		}
	}

	/**
	 * Add update message.
	 */
	public static function version_update_warning( $current_version, $new_version ) {
		$current_version_minor_part = explode( '.', $current_version )[2];
		$new_version_minor_part     = explode( '.', $new_version )[2];

		if ( $current_version_minor_part === $new_version_minor_part ) {
			return;
		}
		?>

        <hr class="wpwc-major-update-warning__separator"/>
        <div class="wpwc-major-update-warning">
            <div class="wpwc-major-update-warning__icon">
                <i class="eicon-info-circle"></i>
            </div>
            <div>
                <div class="wpwc-major-update-warning__title">
					<?php
					echo esc_html__( 'Heads up, Please backup before upgrade!', 'wc-iikocloud' ); ?>
                </div>
                <div class="wpwc-major-update-warning__text">
					<?php
					printf(
					/* translators: %1$s Link open tag, %2$s: Link close tag. */
						esc_html__( 'The latest update includes some substantial changes across different areas of the plugin. We highly recommend you %1$sbackup your site before upgrading%2$s, and make sure you first update in a staging environment.',
							'wc-iikocloud' ),
						'<b>',
						'</b>'
					);
					?>
                </div>
            </div>
        </div>

		<?php
	}
}
