<?php
// Exit if not called by WordPress uninstall process
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$happfoco_tables = array(
	$wpdb->prefix . 'happfoco_forms_data',
);

$happfoco_options = array(
	'happfoco_api_key',
);

// Drop tables
foreach ( $happfoco_tables as $happfoco_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $happfoco_table ) . '`' );
}

// Delete options
foreach ( $happfoco_options as $happfoco_option ) {
	delete_option( $happfoco_option );
}

// Multisite cleanup
if ( is_multisite() ) {
	$happfoco_sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $happfoco_sites as $happfoco_site_id ) {
		switch_to_blog( $happfoco_site_id );
		foreach ( $happfoco_tables as $happfoco_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $happfoco_table ) . '`' );
		}
		foreach ( $happfoco_options as $happfoco_option ) {
			delete_option( $happfoco_option );
		}
		restore_current_blog();
	}
}
