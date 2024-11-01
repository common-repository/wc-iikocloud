<?php

namespace WPWC\iikoCloud\Admin;

defined( 'ABSPATH' ) || exit;

use WC_Admin_Settings;
use WC_Settings_Page;
use WPWC\iikoCloud\Traits\ImportTrait;
use WPWC\iikoCloud\Traits\PageElementsTrait;

class Settings extends WC_Settings_Page {

	use ImportTrait;
	use PageElementsTrait;

	/**
	 * Import method.
	 *
	 * @var string
	 */
	private string $import_method;

	/**
	 * Is premium features available.
	 *
	 * @var bool
	 */
	private bool $is_premium_available;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                   = WC_IIKOCLOUD_PREFIX . 'settings';
		$this->label                = 'iikoCloud';
		$this->import_method        = isset( self::get_import_settings()['method'] ) && 'external_menu' === self::get_import_settings()['method']
			? 'external_menu'
			: 'uploading';
		$this->is_premium_available = defined( 'WC_IIKOCLOUD_MODULES_FILE' );

		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections(): array {
		$sections = [
			''               => esc_html__( 'General', 'wc-iikocloud' ),
			'import'         => esc_html_x( 'Import', 'Tab name', 'wc-iikocloud' ),
			'export'         => esc_html__( 'Export', 'wc-iikocloud' ),
			'cron'           => esc_html__( 'Auto import', 'wc-iikocloud' ),
			'payments'       => esc_html__( 'Payments', 'wc-iikocloud' ),
			'webhooks'       => esc_html__( 'Webhooks', 'wc-iikocloud' ),
			'loyalty'        => esc_html__( 'Loyalty', 'wc-iikocloud' ),
			'delivery-zones' => esc_html__( 'Delivery Zones', 'wc-iikocloud' ),
			'checkout'       => esc_html__( 'Checkout', 'wc-iikocloud' ),
		];

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Output the settings.
	 */
	public function output() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save settings.
	 */
	public function save() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );

		if ( $current_section ) {
			do_action( 'woocommerce_update_options_' . $this->id . '_' . $current_section );
		}
	}

	/**
	 * Prepare terminals options.
	 *
	 * @return array
	 */
	protected function prepare_terminals_options(): array {

		$chosen_terminals = wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'chosen_terminals' ) );
		$settings         = [];

		if ( empty( $chosen_terminals ) ) {
			return $settings;
		}

		$i = 1;
		foreach ( $chosen_terminals as $chosen_terminal_id => $chosen_terminal_name ) {

			if ( empty( $chosen_terminal_id ) ) {
				continue;
			}

			$chosen_terminal_name = ! empty( $chosen_terminal_name ) ? $chosen_terminal_name : 'NOT SET';

			$settings[] = [
				'title'             => sprintf( esc_html__( 'Terminal #%s', 'wc-iikocloud' ), $i ),
				'desc'              => sprintf( esc_html__( 'Terminal #%s ID - %s%s%s', 'wc-iikocloud' ),
					$i,
					'<code>',
					$chosen_terminal_id,
					'</code>'
				),
				'desc_tip'          => esc_html__( "Updated automatically when you press 'Get Nomenclature' button.", 'wc-iikocloud' ),
				'id'                => WC_IIKOCLOUD_PREFIX . 'terminal_id_' . $chosen_terminal_id,
				'type'              => 'text',
				'autoload'          => false,
				'custom_attributes' => [
					'disabled' => 'disabled',
				],
				'default'           => $chosen_terminal_name,
			];

			$i ++;
		}

		return $settings;
	}

	/**
	 * Check import method.
	 *
	 * @return array
	 */
	protected function is_external_menu_import(): array {
		return 'external_menu' === $this->import_method ? [ 'disabled' => 'disabled' ] : [];
	}

	/**
	 * Check premium feature.
	 *
	 * @return array
	 */
	protected function is_premium_feature(): array {
		return ! $this->is_premium_available ? [ 'disabled' => 'disabled' ] : [];
	}

	/**
	 * Premium feature postfix.
	 *
	 * @return string
	 */
	protected function premium_feature_postfix(): string {
		return ! $this->is_premium_available ? ' <sup><b>' . esc_html__( '(PREMIUM FEATURE)', 'wc-iikocloud' ) . '</b></sup>' : '';
	}

	/**
	 * Prepare cities options for select.
	 *
	 * @return array
	 */
	protected function prepare_cities_options(): array {

		$chosen_cities = [];

		// [ 'uuid' => 'name', ... ]
		$all_cities = wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'all_cities' ) );

		// [ i => 'uuid', ... ]
		$chosen_city_ids = wc_clean( get_option( WC_IIKOCLOUD_PREFIX . 'chosen_city_ids' ) );

		if ( is_array( $chosen_city_ids ) && ! empty( $chosen_city_ids ) ) {

			foreach ( $chosen_city_ids as $chosen_city_id ) {
				$streets_amount                   = absint( get_option( WC_IIKOCLOUD_PREFIX . 'streets_amount_' . $chosen_city_id ) );
				$chosen_cities[ $chosen_city_id ] = $all_cities[ $chosen_city_id ] . ' (' . $streets_amount . ')';
			}
		}

		return $chosen_cities;
	}

	/**
	 * Get option ID in order to show in the option description.
	 * Special for organization, terminal and city.
	 *
	 * @param $option_id
	 *
	 * @return string
	 */
	protected function get_option_id( $option_id ): string {

		$option_id = get_option( $option_id );

		if ( empty( $option_id ) ) {
			return 'NOT SET';
		}

		return sanitize_key( $option_id );
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Current section name.
	 *
	 * @return array
	 */
	public function get_settings( string $current_section = '' ): array {

		$auto_settings_desc = sprintf( esc_html__( 'Some of these settings are updated automatically on %splugin the page%s.', 'wc-iikocloud' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc_iikocloud' ) ) . '" target="_blank">',
			'</a>'
		);
		$premium_settings   = [ 'cron', 'payments', 'webhooks', 'loyalty', 'delivery-zones', 'checkout' ];

		// Premium settings
		if ( in_array( $current_section, $premium_settings ) ) {
			$settings = [
				[
					'title' => esc_html__( 'Premium options', 'wc-iikocloud' ),
					'desc'  => static::plugin_subheader( false ),
					'id'    => WC_IIKOCLOUD_PREFIX . 'premium_options',
					'type'  => 'title',
				],

				[
					'type' => 'sectionend',
					'id'   => WC_IIKOCLOUD_PREFIX . 'premium_options',
				],
			];

			// Import section.
		} elseif ( 'import' === $current_section ) {

			$settings = [
				[
					'title' => esc_html__( 'Import', 'wc-iikocloud' ),
					'desc'  => $auto_settings_desc,
					'id'    => WC_IIKOCLOUD_PREFIX . 'import_options',
					'type'  => 'title',
				],

				[
					'title'    => esc_html__( 'Import method', 'wc-iikocloud' ),
					'desc_tip' => true,
					'id'       => WC_IIKOCLOUD_PREFIX . 'import[method]',
					'type'     => 'radio',
					'options'  => [
						'uploading'     => esc_html__( 'Unloading from iikoOffice', 'wc-iikocloud' ),
						'external_menu' => esc_html__( 'External menu', 'wc-iikocloud' ),
					],
					'autoload' => false,
					'default'  => 'uploading',
				],

				[
					'title'             => esc_html__( 'Organization for import', 'wc-iikocloud' ),
					'desc'              => sprintf( esc_html__( 'Organization ID - %s%s%s', 'wc-iikocloud' ),
						'<code>',
						$this->get_option_id( WC_IIKOCLOUD_PREFIX . 'organization_id_import' ),
						'</code>'
					),
					'desc_tip'          => esc_html__( "Updated automatically when you press 'Save Organization for Import' button.", 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'organization_name_import',
					'type'              => 'text',
					'autoload'          => false,
					'custom_attributes' => [
						'disabled' => 'disabled',
					],
				],

				[
					'title'             => esc_html__( 'Nomenclature revision', 'wc-iikocloud' ),
					'desc_tip'          => esc_html__( "Updated automatically when you press 'Get Nomenclature' button.", 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[nomenclature_revision]',
					'type'              => 'text',
					'custom_attributes' => [
						'disabled' => 'disabled',
					],
				],

				[
					'title'             => esc_html__( 'Update stop list', 'wc-iikocloud' ),
					'desc'              => esc_html__( 'Update products by iiko stop list after import', 'wc-iikocloud' ) . $this->premium_feature_postfix(),
					'desc_tip'          => esc_html__( 'This option is useful when you use stop lists.', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[update_stop_list]',
					'type'              => 'checkbox',
					'custom_attributes' => $this->is_premium_feature(),
					'default'           => 'no',
				],

				[
					'title'             => esc_html__( 'Import product into multiple categories', 'wc-iikocloud' ),
					'desc'              => esc_html__( 'Each product can be contained in several categories', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[product_to_multiple_cats]',
					'type'              => 'checkbox',
					'custom_attributes' => $this->is_external_menu_import(),
					'default'           => 'no',
				],

				[
					'title'   => esc_html__( 'Async import', 'wc-iikocloud' ),
					'desc'    => esc_html__( 'Import products in the background', 'wc-iikocloud' ),
					'id'      => WC_IIKOCLOUD_PREFIX . 'import[async]',
					'type'    => 'checkbox',
					'default' => 'no',
				],

				[
					'title'   => esc_html__( 'Reverse groups import', 'wc-iikocloud' ),
					'desc'    => esc_html__( 'Import groups in the reverse order', 'wc-iikocloud' ),
					'id'      => WC_IIKOCLOUD_PREFIX . 'import[reverse_groups]',
					'type'    => 'checkbox',
					'default' => 'no',
				],

				[
					'title'             => esc_html__( 'Import only simple products', 'wc-iikocloud' ),
					'desc'              => esc_html__( 'Import all iiko products as simple WooCommerce products', 'wc-iikocloud' ) . $this->premium_feature_postfix(),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[as_simple_products]',
					'type'              => 'checkbox',
					'custom_attributes' => $this->is_premium_feature(),
					'default'           => 'no',
				],

				[
					'title'             => esc_html__( 'Modifiers', 'wc-iikocloud' ),
					'desc'              => esc_html__( 'Skip unrequired modifiers', 'wc-iikocloud' ) . $this->premium_feature_postfix(),
					'desc_tip'          => esc_html__( 'Do not import unrequired group modifiers', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[skip_unrequired_gm]',
					'type'              => 'checkbox',
					'custom_attributes' => $this->is_premium_feature(),
					'default'           => 'no',
				],

				[
					'desc'              => esc_html__( 'Skip these group modifiers', 'wc-iikocloud' ) . $this->premium_feature_postfix(),
					'desc_tip'          => esc_html__( 'Separate group modifier names with a pipe character - |', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[skip_custom_gms]',
					'type'              => 'text',
					'custom_attributes' => $this->is_premium_feature(),
				],

				// TODO
				/*[
					'desc'     => esc_html__( 'Import only simple modifiers', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Do not import group modifiers', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'import[only_simple_modifiers]',
					'type'     => 'checkbox',
					'default'  => 'no',
				],*/

				[
					'desc'              => esc_html__( 'Import group modifiers as product custom fields', 'wc-iikocloud' ) . $this->premium_feature_postfix(),
					'desc_tip'          => esc_html__( 'Do not import group modifiers as product variations', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[gms_as_product_cfs]',
					'type'              => 'checkbox',
					'custom_attributes' => $this->is_premium_feature(),
					'default'           => 'no',
					'checkboxgroup'     => 'start',
					'show_if_checked'   => 'option',
				],

				[
					'desc'              => esc_html__( 'Show modifiers description', 'wc-iikocloud' ) . $this->premium_feature_postfix(),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[show_modifiers_desc]',
					'type'              => 'checkbox',
					'custom_attributes' => $this->is_premium_feature(),
					'default'           => 'no',
					'checkboxgroup'     => 'end',
					'show_if_checked'   => 'yes',
				],

				[
					'title'    => esc_html__( 'To import', 'wc-iikocloud' ),
					'desc'     => esc_html__( 'Images', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Import products and categories images from iiko', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'import[images]',
					'type'     => 'checkbox',
					'default'  => 'yes',
				],

				[
					'desc'     => esc_html__( 'Descriptions', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Import products and categories descriptions from iiko', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'import[descriptions]',
					'type'     => 'checkbox',
					'default'  => 'yes',
				],

				[
					'desc'     => esc_html__( 'Tags', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Import products tags from iiko', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'import[tags]',
					'type'     => 'checkbox',
					'default'  => 'yes',
				],

				[
					'desc'     => esc_html__( 'SEO', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Import products and categories SEO titles and descriptions from iiko (works only with Yoast SEO plugin)', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'import[seo]',
					'type'     => 'checkbox',
					'default'  => 'yes',
				],

				[
					'desc'     => esc_html__( 'Sale prices', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Import sale prices from iiko', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'import[sale_prices]',
					'type'     => 'checkbox',
					'default'  => 'yes',
				],

				[
					'title'   => esc_html__( 'Enable reviews', 'wc-iikocloud' ),
					'desc'    => esc_html__( 'When importing products', 'wc-iikocloud' ),
					'id'      => WC_IIKOCLOUD_PREFIX . 'import[products_reviews]',
					'type'    => 'checkbox',
					'default' => 'no',
				],

				[
					'title'    => esc_html__( 'Hide all old products', 'wc-iikocloud' ),
					'desc'     => sprintf( esc_html__( 'Set status %sOut of stock%s for all products that are not in the current iiko stock list', 'wc-iikocloud' ),
						'<code>',
						'</code>'
					),
					'id'       => WC_IIKOCLOUD_PREFIX . 'import[set_out_of_stock_status]',
					'type'     => 'checkbox',
					'default'  => 'no',
					'autoload' => false,
				],

				[
					'title'    => esc_html__( 'Delete old products', 'wc-iikocloud' ),
					'desc'     => sprintf( esc_html__( 'Leave %sonly products from selected groups%s. The rest of the items will be removed into Trash', 'wc-iikocloud' ),
						'<b>',
						'</b>'
					),
					'id'       => WC_IIKOCLOUD_PREFIX . 'import[delete_old_products]',
					'type'     => 'checkbox',
					'default'  => 'no',
					'autoload' => false,
				],

				[
					'title'             => esc_html__( 'Delete attributes and variations', 'wc-iikocloud' ),
					'desc'              => esc_html__( 'Delete all attributes and variations for variable WooCommerce products', 'wc-iikocloud' ) . $this->premium_feature_postfix(),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[delete_product_attrs_vars]',
					'type'              => 'checkbox',
					'custom_attributes' => $this->is_premium_feature(),
					'default'           => 'yes',
				],

				[
					'title'             => esc_html__( 'Variations limit', 'wc-iikocloud' ),
					'desc'              => esc_html__( 'Limit the number of created variations.', 'wc-iikocloud' ) . $this->premium_feature_postfix(),
					'desc_tip'          => esc_html__( 'Default limit is 50 variation. Use 0 in order to create all possible variations.', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'import[vars_limit]',
					'type'              => 'number',
					'css'               => 'width: 60px;',
					'custom_attributes' => $this->is_premium_available
						? [
							'min'  => 0,
							'step' => 1,
						]
						: $this->is_premium_feature(),
					'default'           => '50',
				],

				[
					'title'   => esc_html__( 'Delete old product photos', 'wc-iikocloud' ),
					'desc'    => sprintf( esc_html__( 'Delete %sALL old photos%s for each product', 'wc-iikocloud' ),
						'<b>',
						'</b>'
					),
					'id'      => WC_IIKOCLOUD_PREFIX . 'import[delete_product_imgs]',
					'type'    => 'checkbox',
					'default' => 'no',
				],

				[
					'title'   => esc_html__( 'Delete old product categories photos', 'wc-iikocloud' ),
					'desc'    => sprintf( esc_html__( 'Delete %sold photo%s for each product category', 'wc-iikocloud' ),
						'<b>',
						'</b>'
					),
					'id'      => WC_IIKOCLOUD_PREFIX . 'import[delete_product_cat_imgs]',
					'type'    => 'checkbox',
					'default' => 'no',
				],

				[
					'type' => 'sectionend',
					'id'   => WC_IIKOCLOUD_PREFIX . 'import_options',
				],
			];

			// Export section.
		} elseif ( 'export' === $current_section ) {

			$settings = [
				[
					'title' => esc_html__( 'Export', 'wc-iikocloud' ),
					'type'  => 'title',
					'desc'  => $auto_settings_desc,
					'id'    => WC_IIKOCLOUD_PREFIX . 'export_options',
				],

				[
					'title'    => esc_html__( 'Export orders', 'wc-iikocloud' ),
					'desc'     => esc_html__( 'Export orders instead of deliveries.', 'wc-iikocloud' ),
					'desc_tip' => true,
					'id'       => WC_IIKOCLOUD_PREFIX . 'export[type]',
					'class'    => 'wc-enhanced-select',
					'type'     => 'select',
					'options'  => [
						'deliveries' => esc_html__( 'Deliveries', 'wc-iikocloud' ),
						'orders'     => esc_html__( 'Orders for the kitchen', 'wc-iikocloud' ),
						'both'       => esc_html__( 'Deliveries and Orders for the kitchen', 'wc-iikocloud' ),
						'none'       => esc_html__( 'Turn off export', 'wc-iikocloud' ),
					],
					'default'  => 'deliveries',

				],

				[
					'desc'    => esc_html__( 'Check orders after they are exported', 'wc-iikocloud' ),
					'id'      => WC_IIKOCLOUD_PREFIX . 'export[check_orders]',
					'type'    => 'checkbox',
					'default' => 'yes',
				],

				[
					'desc'     => esc_html__( 'Close orders after they are exported', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Only for exporting kitchen orders.', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'export[close_orders]',
					'type'     => 'checkbox',
					'default'  => 'no',
				],

				[
					'desc'     => esc_html__( 'Whether paper cheque should be printed', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Only for exporting kitchen orders.', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'export[print_receipt]',
					'type'     => 'checkbox',
					'default'  => 'no',
				],

				[
					'title'    => esc_html__( 'Check online payment method', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Use payment method slug.', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'export[online_payment_method]',
					'type'     => 'text',
				],

				[
					'title'             => esc_html__( 'Organization for export', 'wc-iikocloud' ),
					'desc'              => sprintf( esc_html__( 'Organization ID - %s%s%s', 'wc-iikocloud' ),
						'<code>',
						$this->get_option_id( WC_IIKOCLOUD_PREFIX . 'organization_id_export' ),
						'</code>'
					),
					'desc_tip'          => esc_html__( "Updated when you press 'Save Organization and Terminals for Export' button.", 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'organization_name_export',
					'type'              => 'text',
					'autoload'          => false,
					'custom_attributes' => [
						'disabled' => 'disabled',
					],
				],

				[
					'title'    => esc_html__( 'Default city', 'wc-iikocloud' ),
					'desc'     => esc_html__( 'This list contains the cities that you last imported and that will be used on the checkout page.', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( "Updated automatically when you press 'Get Streets' button.", 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'export[default_city_id]',
					'class'    => 'wc-enhanced-select',
					'type'     => 'select',
					'options'  => $this->prepare_cities_options(),
					'default'  => '',
				],

				[
					'title' => esc_html__( 'Default iiko street ID', 'wc-iikocloud' ),
					'desc'  => esc_html__( 'This street will be used, if a customer chooses his own street during a checkout process.', 'wc-iikocloud' ),
					'id'    => WC_IIKOCLOUD_PREFIX . 'export[default_street_id]',
					'type'  => 'text',
				],

				[
					'title'    => esc_html__( 'To export', 'wc-iikocloud' ),
					'desc'     => esc_html__( 'Prices', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'Export product prices to iiko (sale prices if they are or regular prices instead)', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'export[prices]',
					'type'     => 'checkbox',
					'default'  => 'yes',
				],

				[
					'title'             => esc_html__( 'Shipping as product ID', 'wc-iikocloud' ),
					'desc'              => esc_html__( 'Move shipping costs to a separate product.', 'wc-iikocloud' ),
					'desc_tip'          => esc_html__( 'The ID of the product to transfer the shipping cost.', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'export[shipping_as_product_id]',
					'type'              => 'number',
					'css'               => 'width: 100px;',
					'custom_attributes' => [
						'min'  => 1,
						'step' => 1,
					],
				],

				[
					'type' => 'sectionend',
					'id'   => WC_IIKOCLOUD_PREFIX . 'export_options',
				],
			];

			// Insert terminals settings after organization ID.
			array_splice( $settings, 7, 0, $this->prepare_terminals_options() );

			// General section.
		} else {

			$settings = [
				[
					'title' => esc_html__( 'IikoCloud Settings', 'wc-iikocloud' ) . ' v' . WC_IIKOCLOUD_VERSION,
					'type'  => 'title',
					'id'    => WC_IIKOCLOUD_PREFIX . 'version_options',
				],

				[
					'type' => 'sectionend',
					'id'   => WC_IIKOCLOUD_PREFIX . 'version_options',
				],

				[
					'title' => esc_html__( 'General', 'wc-iikocloud' ),
					'type'  => 'title',
					'id'    => WC_IIKOCLOUD_PREFIX . 'general_options',
				],

				[
					'title'    => esc_html__( 'API-key', 'wc-iikocloud' ),
					'desc'     => sprintf( esc_html__( 'Unique identifier of the store, issued by the iiko. %sSee documentation%s or contact your personal manager for more details.', 'wc-iikocloud' ),
						'<a href="https://ru.iiko.help/articles/#!api-documentations/connect-to-iiko-cloud"
							rel="noopener noreferrer nofollow"
							target="_blank">',
						'</a>'
					),
					'desc_tip' => esc_html__( 'If the API-key field is empty, then the plugin will not work.', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'apiLogin',
					'type'     => 'text',
				],

				[
					'title'             => esc_html__( 'Timeout in seconds', 'wc-iikocloud' ),
					'desc'              => esc_html__( 'Time limit for API requests.', 'wc-iikocloud' ),
					'desc_tip'          => esc_html__( 'Default iikoCloud API value is 15 second.', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'timeout',
					'type'              => 'number',
					'css'               => 'width: 60px;',
					'custom_attributes' => [
						'min'  => 1,
						'step' => 1,
					],
					'default'           => '15',
				],

				[
					'title'    => esc_html__( 'API Server', 'wc-iikocloud' ),
					'desc'     => esc_html__( 'Use European iiko API Server', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'If unchecked the Russian iiko API server will be used.', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'european_server',
					'type'     => 'checkbox',
					'default'  => 'no',
				],

				[
					'type' => 'sectionend',
					'id'   => WC_IIKOCLOUD_PREFIX . 'general_options',
				],

				[
					'title' => esc_html__( 'Debug', 'wc-iikocloud' ),
					'type'  => 'title',
					'id'    => WC_IIKOCLOUD_PREFIX . 'debug_options',
				],

				[
					'title'    => esc_html__( 'Debug mode', 'wc-iikocloud' ),
					'desc'     => esc_html__( 'Turn on debug mode', 'wc-iikocloud' ),
					'desc_tip' => sprintf( esc_html__( 'See logs in %sWooCommerce > System Status > Logs%s.', 'wc-iikocloud' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '" target="_blank">',
						'</a>'
					),
					'id'       => WC_IIKOCLOUD_PREFIX . 'debug_mode',
					'type'     => 'checkbox',
					'default'  => 'yes',
				],

				[
					'title'       => esc_html__( 'Email for errors', 'wc-iikocloud' ),
					'desc'        => esc_html__( 'Comma-separated list of email addresses to send error messages', 'wc-iikocloud' ),
					'desc_tip'    => esc_html__( 'If this field is empty, the site administrator\'s email will be used.', 'wc-iikocloud' ),
					'id'          => WC_IIKOCLOUD_PREFIX . 'debug_emails',
					'type'        => 'text',
					'placeholder' => 'name@domain.com',
				],

				[
					'title'    => esc_html__( 'Telegram bot token', 'wc-iikocloud' ),
					'desc'     => sprintf( esc_html__( 'See this %sinstruction%s to get it.', 'wc-iikocloud' ),
						'<a href="https://github.com/php-telegram-bot/core#create-your-first-bot" target="_blank">',
						'</a>'
					),
					'desc_tip' => esc_html__( 'If this field is empty, messages to Telegram won\'t be sent.', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'telegram[bot_token]',
					'type'     => 'text',
					'autoload' => false,
				],

				[
					'title'    => esc_html__( 'Telegram bot user name', 'wc-iikocloud' ),
					'desc_tip' => esc_html__( 'If this field is empty, messages to Telegram won\'t be sent.', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'telegram[bot_user_name]',
					'type'     => 'text',
					'autoload' => false,
				],

				[
					'title'    => esc_html__( 'Telegram chat ID', 'wc-iikocloud' ),
					'desc'     => sprintf( esc_html__( "Use the URL %shttps://api.telegram.org/bot<YourBOTToken>/getUpdates%s to get it.", 'wc-iikocloud' ),
						'<code>',
						'</code>'
					),
					'desc_tip' => esc_html__( 'If this field is empty, messages to Telegram won\'t be sent.', 'wc-iikocloud' ),
					'id'       => WC_IIKOCLOUD_PREFIX . 'telegram[chat_id]',
					'type'     => 'text',
					'autoload' => false,
				],

				[
					'type' => 'sectionend',
					'id'   => WC_IIKOCLOUD_PREFIX . 'debug_options',
				],

				[
					'title' => esc_html__( 'Localization', 'wc-iikocloud' ),
					'type'  => 'title',
					'id'    => WC_IIKOCLOUD_PREFIX . 'localization_options',
				],

				[
					'title'       => esc_html__( 'Locale', 'wc-iikocloud' ),
					'desc_tip'    => sprintf( esc_html__( 'Default value is %s.', 'wc-iikocloud' ),
						'<code>ru</code>'
					),
					'id'          => WC_IIKOCLOUD_PREFIX . 'locale',
					'type'        => 'text',
					'default'     => 'ru',
					'placeholder' => 'ru',
					'css'         => 'width: 60px;',
				],

				[
					'title'       => esc_html__( 'Time zone', 'wc-iikocloud' ),
					'desc_tip'    => sprintf( esc_html__( 'Default value is %s.', 'wc-iikocloud' ),
						'<code>Europe/Moscow</code>'
					),
					'id'          => WC_IIKOCLOUD_PREFIX . 'timezone',
					'type'        => 'text',
					'default'     => 'Europe/Moscow',
					'placeholder' => 'Europe/Moscow',
				],

				[
					'title'             => esc_html__( 'Opening hour', 'wc-iikocloud' ),
					'desc_tip'          => esc_html__( 'Opening time of the establishment. Possible values: 1 - 24', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'local[opening_hour]',
					'type'              => 'number',
					'css'               => 'width: 60px;',
					'custom_attributes' => [
						'min'  => 1,
						'max'  => 24,
						'step' => 1,
					],
				],

				[
					'title'             => esc_html__( 'Closing hour', 'wc-iikocloud' ),
					'desc_tip'          => esc_html__( 'Closing time of the establishment. Possible values: 1 - 24', 'wc-iikocloud' ),
					'id'                => WC_IIKOCLOUD_PREFIX . 'local[closing_hour]',
					'type'              => 'number',
					'css'               => 'width: 60px;',
					'custom_attributes' => [
						'min'  => 1,
						'max'  => 24,
						'step' => 1,
					],
				],

				[
					'type' => 'sectionend',
					'id'   => WC_IIKOCLOUD_PREFIX . 'localization_options',
				],

				[
					'title' => esc_html__( 'Other', 'wc-iikocloud' ),
					'type'  => 'title',
					'id'    => WC_IIKOCLOUD_PREFIX . 'plugin_remove_options',
				],

				[
					'title'   => esc_html__( 'Plugin Uninstallation', 'wc-iikocloud' ),
					'desc'    => esc_html__( 'Remove settings after plugin uninstallation', 'wc-iikocloud' ),
					'id'      => WC_IIKOCLOUD_PREFIX . 'remove_plugin_settings',
					'type'    => 'checkbox',
					'default' => 'no',
				],

				[
					'type' => 'sectionend',
					'id'   => WC_IIKOCLOUD_PREFIX . 'plugin_remove_options',
				],
			];
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}
}
