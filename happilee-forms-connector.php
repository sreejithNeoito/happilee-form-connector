<?php
/**
 * Plugin Name: Happilee Forms Connector
 * Plugin URI: https://happilee.io
 * Description: Connect your WordPress forms to Happilee API seamlessly
 * Version: 1.0.2
 * Author: Neoito
 * Author URI: https://neoito.com
 * License: GPL v2 or later
 * Text Domain: happilee-forms-connector
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAPPILEE_FORMS_VERSION', '1.0.2' );
define( 'HAPPILEE_FORMS_PLUGIN_FILE', __FILE__ );
define( 'HAPPILEE_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HAPPILEE_FORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HAPPFOCO_WP_VERSION', get_bloginfo( 'version' ) );

/*
 * Include main class.
 */
require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-main.php';

// Activation hook
function happfoco_activate() {
	require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-db.php';
	$db = new Happfoco_DB();
	$db->happfoco_create_table();
}
register_activation_hook( HAPPILEE_FORMS_PLUGIN_FILE, 'happfoco_activate' );

// Initialize the plugin
Happfoco_Main::get_instance();
