<?php

namespace WPWC\iikoCloud\API_Requests;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\Import\Import_Nomenclature;
use WPWC\iikoCloud\Logs;

class AJAX_API_Requests extends Common_API_Requests {

	/**
	 * Add error while the plugin settings update.
	 */
	protected function add_terminal_and_wc_log_error( $message ) {
		Logs::add_error( $message );
		Logs::add_wc_log( $message, 'update-plugin-settings', 'error' );
	}

	/**
	 * Remove access token.
	 */
	public function remove_access_token_ajax() {

		$this->check_plugin_ajax_referer();

		if ( delete_transient( WC_IIKOCLOUD_PREFIX . 'access_token' ) ) {
			wp_send_json_success( [ 'message' => esc_html__( 'Access token was removed successfully', 'wc-iikocloud' ) ] );

		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Error while access token removing', 'wc-iikocloud' ) ] );
		}
	}

	/**
	 * Get organizations from iiko.
	 */
	public function get_organizations_ajax() {

		$this->check_plugin_ajax_referer();

		$organizations = $this->get_organizations();

		$this->check_ajax_response( $organizations, esc_html__( 'Organizations', 'wc-iikocloud' ) );

		if ( ! empty( $organizations['organizations'] ) ) {
			wp_send_json_success( $organizations );

		} else {
			Logs::add_error( esc_html__( 'Response does not contain organizations', 'wc-iikocloud' ) );
			wp_send_json_error( Logs::get_logs() );
		}
	}

	/**
	 * Save organization for import.
	 */
	public function save_organization_import_ajax() {

		$this->check_plugin_ajax_referer();

		$organization_id   = $this->sanitize_required_id( $_POST['organizationId'], esc_html__( 'Organization', 'wc-iikocloud' ) );
		$organization_name = $this->sanitize_and_check_name( $_POST['organizationName'], esc_html__( 'Organization', 'wc-iikocloud' ), false );

		$this->check_ajax_parameter( $organization_id, esc_html__( 'Error while adding organization ID in plugin settings. Organization ID', 'wc-iikocloud' ) );

		if ( empty( $organization_name ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Error while adding organization name in plugin settings. No organization selected', 'wc-iikocloud' ) );
		}

		delete_option( WC_IIKOCLOUD_PREFIX . 'organization_id_import' );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'organization_id_import', $organization_id ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Cannot save import organization ID in plugin settings', 'wc-iikocloud' ) );
			wp_send_json_error( Logs::get_logs() );
		}

		delete_option( WC_IIKOCLOUD_PREFIX . 'organization_name_import' );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'organization_name_import', $organization_name ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Cannot save organization name in plugin settings', 'wc-iikocloud' ) );
			wp_send_json_error( Logs::get_logs() );
		}

		wp_send_json_success( [ 'result' => esc_html__( 'Organization saved successfully', 'wc-iikocloud' ) ] );
	}

	/**
	 * Get terminals from iiko.
	 */
	public function get_terminals_ajax() {

		$this->check_plugin_ajax_referer();

		$organization_id = $this->sanitize_required_id( $_POST['organizationId'], esc_html__( 'Organization', 'wc-iikocloud' ) );

		$this->check_ajax_parameter( $organization_id, esc_html__( 'Organization ID', 'wc-iikocloud' ) );

		$terminals = $this->get_terminals( $organization_id );

		$this->check_ajax_response( $terminals, esc_html__( 'Terminals', 'wc-iikocloud' ) );

		if ( ! empty( $terminals['terminalGroups'][0]['items'] ) ) {
			wp_send_json_success( $terminals );

		} else {
			Logs::add_error( esc_html__( 'Organization does not have terminals', 'wc-iikocloud' ) );
			wp_send_json_error( Logs::get_logs() );
		}
	}

	/**
	 * Save organization and terminals for export.
	 */
	public function save_organization_terminals_export_ajax() {

		$this->check_plugin_ajax_referer();

		$organization_id   = $this->sanitize_required_id( $_POST['organizationId'], esc_html__( 'Organization', 'wc-iikocloud' ) );
		$organization_name = $this->sanitize_and_check_name( $_POST['organizationName'], esc_html__( 'Organization', 'wc-iikocloud' ), false );
		$terminals         = self::is_empty_array( $_POST['chosenTerminals'], esc_html__( 'No terminal IDs', 'wc-iikocloud' ) );
		$terminals         = $this->sanitize_ids( $terminals, esc_html__( 'Terminal', 'wc-iikocloud' ) );

		$this->check_ajax_parameter( $organization_id, esc_html__( 'Organization ID', 'wc-iikocloud' ) );
		$this->check_ajax_parameter( $terminals, esc_html__( 'Terminal IDs', 'wc-iikocloud' ) );

		if ( empty( $organization_name ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Error while adding organization name in plugin settings. No organization selected', 'wc-iikocloud' ) );
		}

		delete_option( WC_IIKOCLOUD_PREFIX . 'organization_id_export' );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'organization_id_export', $organization_id ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Cannot save export organization ID in plugin settings', 'wc-iikocloud' ) );
			wp_send_json_error( Logs::get_logs() );
		}

		delete_option( WC_IIKOCLOUD_PREFIX . 'chosen_terminals' );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'chosen_terminals', $terminals ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Cannot save terminals in plugin settings', 'wc-iikocloud' ) );
			wp_send_json_error( Logs::get_logs() );
		}

		delete_option( WC_IIKOCLOUD_PREFIX . 'organization_name_export' );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'organization_name_export', $organization_name ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Cannot save organization name in plugin settings', 'wc-iikocloud' ) );
			wp_send_json_error( Logs::get_logs() );
		}

		wp_send_json_success( [ 'result' => esc_html__( 'Organization and terminals saved successfully', 'wc-iikocloud' ) ] );
	}

	/**
	 * Get nomenclature from iiko.
	 */
	public function get_nomenclature_ajax() {

		$this->check_plugin_ajax_referer();

		$organization_id = $this->sanitize_required_id( $_POST['organizationId'], esc_html__( 'Organization', 'wc-iikocloud' ) );

		$this->check_ajax_parameter( $organization_id, esc_html__( 'Organization ID', 'wc-iikocloud' ) );

		$stock        = new Stock_API_Requests();
		$nomenclature = $stock->get_nomenclature( $organization_id );

		$this->check_ajax_response( $nomenclature, esc_html__( 'Nomenclature', 'wc-iikocloud' ) );

		wp_send_json_success( $nomenclature );
	}

	/**
	 * Get external menus from iiko.
	 */
	public function get_menus_ajax() {

		$this->check_plugin_ajax_referer();

		$stock = new Stock_API_Requests();
		$menus = $stock->get_menus();

		$this->check_ajax_response( $menus, esc_html__( 'Menus', 'wc-iikocloud' ) );

		if ( ! empty( $menus['externalMenus'] ) ) {
			wp_send_json_success( $menus );

		} else {
			Logs::add_error( esc_html__( 'Response does not contain menus', 'wc-iikocloud' ) );
			wp_send_json_error( Logs::get_logs() );
		}
	}

	/**
	 * Get external menu nomenclature from iiko.
	 */
	public function get_menu_nomenclature_ajax() {

		$this->check_plugin_ajax_referer();

		$menu_id           = $this->sanitize_required_id( $_POST['menuId'], esc_html__( 'Menu', 'wc-iikocloud' ) );
		$organization_id   = $this->sanitize_required_id( $_POST['organizationId'], esc_html__( 'Organization', 'wc-iikocloud' ) );
		$price_category_id = sanitize_key( $_POST['priceCategoryId'] ) ?: null;

		$this->check_ajax_parameter( $menu_id, esc_html__( 'External menu ID', 'wc-iikocloud' ) );
		$this->check_ajax_parameter( $organization_id, esc_html__( 'Organization ID', 'wc-iikocloud' ) );

		$stock        = new Stock_API_Requests();
		$nomenclature = $stock->get_menu_nomenclature( $menu_id, $price_category_id, $organization_id );

		$this->check_ajax_response( $nomenclature, esc_html__( 'Nomenclature', 'wc-iikocloud' ) );

		wp_send_json_success( $nomenclature );
	}

	/**
	 * Get cities from iiko.
	 */
	public function get_cities_ajax() {

		$this->check_plugin_ajax_referer();

		$organization_id = $this->sanitize_required_id( $_POST['organizationId'], esc_html__( 'Organization', 'wc-iikocloud' ) );

		$this->check_ajax_parameter( $organization_id, esc_html__( 'Organization ID', 'wc-iikocloud' ) );

		$address = new Address_API_Requests();
		$cities  = $address->get_cities( $organization_id );

		$this->check_ajax_response( $cities, esc_html__( 'Cities', 'wc-iikocloud' ) );

		wp_send_json_success( [ 'cities' => $cities ] );
	}

	/**
	 * Get streets from iiko.
	 */
	public function get_streets_ajax() {

		$this->check_plugin_ajax_referer();

		$organization_id = $this->sanitize_required_id( $_POST['organizationId'], esc_html__( 'Organization', 'wc-iikocloud' ) );
		$city_ids        = self::is_empty_array( $_POST['cityIds'], esc_html__( 'No city IDs', 'wc-iikocloud' ) );
		$city_ids        = $this->sanitize_ids( $city_ids, esc_html__( 'City', 'wc-iikocloud' ) );

		$this->check_ajax_parameter( $organization_id, esc_html__( 'Organization ID', 'wc-iikocloud' ) );

		$address = new Address_API_Requests();
		$streets = $address->get_streets( $organization_id, $city_ids );

		$this->check_ajax_response( $streets, esc_html__( 'Streets', 'wc-iikocloud' ) );

		wp_send_json( Logs::get_logs() + [ 'streets' => $streets ] );
	}

	/**
	 * Import nomenclature (groups and products) to WooCommerce.
	 */
	public function import_nomenclature_ajax() {

		$this->check_plugin_ajax_referer();

		// [ 'uuid' => 'name', ... ]
		$groups = self::is_empty_array( $_POST['chosenGroups'], esc_html__( 'No group IDs', 'wc-iikocloud' ) );
		$groups = $this->sanitize_ids( $groups, esc_html__( 'Group', 'wc-iikocloud' ) );

		$this->check_ajax_parameter( $groups, esc_html__( 'Group IDs', 'wc-iikocloud' ) );

		if ( empty( $groups ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Error while import chosen groups. No groups selected', 'wc-iikocloud' ) );
		}

		$import   = new Import_Nomenclature();
		$response = $import->import_nomenclature( $groups );

		if ( ! empty( $response ) ) {
			wp_send_json_success( Logs::get_logs( $response ) );
		}
	}

	/**
	 * Save groups for auto import.
	 */
	public function save_groups_ajax() {

		$this->check_plugin_ajax_referer();

		// [ 'uuid' => 'name', ... ]
		$groups = self::is_empty_array( $_POST['chosenGroups'], esc_html__( 'No group IDs', 'wc-iikocloud' ) );
		$groups = $this->sanitize_ids( $groups, esc_html__( 'Group', 'wc-iikocloud' ) );

		$this->check_ajax_parameter( $groups, esc_html__( 'Group IDs', 'wc-iikocloud' ) );

		if ( empty( $groups ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Error while saving chosen groups in plugin settings. No groups selected', 'wc-iikocloud' ) );
		}

		delete_option( WC_IIKOCLOUD_PREFIX . 'chosen_groups' );

		if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'chosen_groups', $groups ) ) {
			$this->add_terminal_and_wc_log_error( esc_html__( 'Cannot save chosen groups in plugin settings', 'wc-iikocloud' ) );
			wp_send_json_error( Logs::get_logs() );
		}

		if (
			isset( self::get_import_settings()['method'] )
			&& 'external_menu' === self::get_import_settings()['method']
		) {

			$menu_id           = sanitize_key( $_POST['menuId'] ) ?: null;
			$price_category_id = sanitize_key( $_POST['priceCategoryId'] ) ?: null;

			$this->check_ajax_parameter( $menu_id, esc_html__( 'External menu ID', 'wc-iikocloud' ) );

			if ( empty( $menu_id ) ) {
				$this->add_terminal_and_wc_log_error( esc_html__( 'Error while saving menu ID in plugin settings. No menu ID selected', 'wc-iikocloud' ) );
			}

			delete_option( WC_IIKOCLOUD_PREFIX . 'menu_id' );
			delete_option( WC_IIKOCLOUD_PREFIX . 'price_category_id' );

			if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'menu_id', $menu_id ) ) {
				$this->add_terminal_and_wc_log_error( esc_html__( 'Cannot save menu ID in plugin settings', 'wc-iikocloud' ) );
				wp_send_json_error( Logs::get_logs() );
			}

			if ( ! update_option( WC_IIKOCLOUD_PREFIX . 'price_category_id', $price_category_id ) ) {
				Logs::add_wc_log( 'Cannot save price category ID in plugin settings', 'update-plugin-settings', 'notice' );
			}
		}

		wp_send_json_success( [ 'result' => esc_html__( 'Groups saved successfully', 'wc-iikocloud' ) ] );
	}
}
