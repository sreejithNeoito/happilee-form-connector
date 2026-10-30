<?php
/**
 * Plugin Name:       Happilee Forms Connector
 * Plugin URI:        https://happilee.io
 * Description:       Connect your WordPress forms to Happilee API seamlessly.
 * Version:           1.0.10
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Neoito
 * Author URI:        https://neoito.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       happilee-forms-connector
 * Domain Path:       /languages
 *
 * @package Happilee_Forms_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAPPILEE_FORMS_VERSION', '1.0.10' );
define( 'HAPPILEE_FORMS_PLUGIN_FILE', __FILE__ );
define( 'HAPPILEE_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HAPPILEE_FORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-main.php';

/**
 * Create the plugin database tables on activation.
 *
 * @return void
 */
function happfoco_activate() {
	require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-db.php';
	$db = new Happfoco_DB();
	$db->happfoco_check_and_create_table();
}
register_activation_hook( HAPPILEE_FORMS_PLUGIN_FILE, 'happfoco_activate' );

Happfoco_Main::get_instance();
