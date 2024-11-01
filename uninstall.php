<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require __DIR__ . '/vendor/autoload.php';

use WPWC\iikoCloud\Logs;

global $wpdb;

! defined( 'WC_IIKOCLOUD_PREFIX' ) && define( 'WC_IIKOCLOUD_PREFIX', 'wc_iikocloud_' );

// Clear plugin options helper.
function wc_iikocloud_delete_options( $options, $id = null ) {

	if ( empty( $options ) ) {
		return;
	}

	foreach ( $options as $option ) {

		if ( is_null( $id ) ) {
			if ( ! delete_option( $option->option_name ) ) {
				Logs::add_wc_log( 'Failed to remove option: ' . $option->option_name );
			}

		} else {
			if ( ! delete_blog_option( $id, $option->option_name ) ) {
				Logs::add_wc_log( 'Failed to remove option: ' . $option->option_name . '. Blog ID: ' . $id );
			}
		}
	}
}

// Clear the plugin's CRON task.
wp_clear_scheduled_hook( WC_IIKOCLOUD_PREFIX . 'cron_import_nomenclature' );
wp_clear_scheduled_hook( WC_IIKOCLOUD_PREFIX . 'cron_export_order' );

// Clear plugin options.
if ( is_multisite() ) {

	$sites_ids = array_column( get_sites(), 'id' );

	foreach ( $sites_ids as $site_id ) {

		if ( 'yes' !== get_blog_option( $site_id, WC_IIKOCLOUD_PREFIX . 'remove_plugin_settings' ) ) {
			continue;
		}

		$query   = $wpdb->prepare( "SELECT * FROM $wpdb->prefix" . ( 1 === $site_id ? '' : absint( $site_id ) . '_' ) . "options WHERE option_name LIKE '%" . WC_IIKOCLOUD_PREFIX . "%'" );
		$options = $wpdb->get_results( $query );

		wc_iikocloud_delete_options( $options, $site_id );
	}

} else {

	if ( 'yes' === get_option( WC_IIKOCLOUD_PREFIX . 'remove_plugin_settings' ) ) {

		$query   = $wpdb->prepare( "SELECT * FROM $wpdb->options WHERE option_name LIKE '%" . WC_IIKOCLOUD_PREFIX . "%'" );
		$options = $wpdb->get_results( $query );

		wc_iikocloud_delete_options( $options );
	}
}

// Clear any cached data that has been removed.
wp_cache_flush();