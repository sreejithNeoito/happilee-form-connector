<?php
/**
 * Removes the plugin's tables and options when the plugin is deleted.
 *
 * @package Happilee_Forms_Connector
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Drop the plugin tables and delete its options for the current site.
 *
 * @return void
 */
function happfoco_uninstall_site() {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'happfoco_forms_data',
		$wpdb->prefix . 'happfoco_template_data',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
	}

	delete_option( 'happfoco_api_key' );
	delete_option( 'happfoco_encryption_key_hash' );
	delete_option( 'happfoco_db_version' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $happfoco_site_id ) {
		switch_to_blog( $happfoco_site_id );
		happfoco_uninstall_site();
		restore_current_blog();
	}
} else {
	happfoco_uninstall_site();
}
