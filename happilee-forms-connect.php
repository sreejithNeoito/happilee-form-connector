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
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAPPILEE_FORMS_VERSION', '1.0.0' );
define( 'HAPPILEE_FORMS_PLUGIN_FILE', __FILE__ );
define( 'HAPPILEE_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HAPPILEE_FORMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** Include main class. */
require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-main.php';
