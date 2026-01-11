<?php
/**
 * Plugin Name: Happilee Forms Connect
 * Plugin URI: https://happilee.io
 * Description: Connect your WordPress forms to Happilee API seamlessly
 * Version: 1.0.0
 * Author: Happilee
 * Author URI: https://happilee.io
 * License: GPL v2 or later
 * Text Domain: happilee-forms-connect
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAPPILEE_FORMS_VERSION', '1.0.0' );
define( 'HFC_END_POINT', 'https://webhook.site/b069e489-2801-47b5-9b5b-598c61e9bca1' );
define( 'HAPPILEE_FORMS_PLUGIN_FILE', __FILE__ );
define( 'HAPPILEE_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HAPPILEE_FORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HFC_Wordpress_Version', get_bloginfo( 'version' ) );

/*
 * Include main class.
 */
require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-main.php';

// Activation hook
function happilee_forms_connect_activate() {
	require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-db.php';
	$db = new happilee_HFC_DB();
	$db->hfc_create_dataTable();
}
register_activation_hook( HAPPILEE_FORMS_PLUGIN_FILE, 'happilee_forms_connect_activate' );

// Uninstall hook
function happilee_forms_connect_uninstall() {
	require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-db.php';
	$db = new happilee_HFC_DB();
	$db->hfc_delete_dataTable();
}
register_uninstall_hook( HAPPILEE_FORMS_PLUGIN_FILE, 'happilee_forms_connect_uninstall' );

// Initialize the plugin
Happilee_Forms_Connect::get_instance();
