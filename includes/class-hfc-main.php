<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happilee_Forms_Connect' ) ) {

	class Happilee_Forms_Connect {

		private static $instance = null;

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_happilee_forms_connect_scripts' ) );
			// $this->load_api_class();
			add_action( 'plugins_loaded', array( $this, 'load_api_class' ), 20 );
			add_action( 'plugins_loaded', array( $this, 'init_operation_class' ), 20 );
		}

		public function load_api_class() {
			require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-api.php';
			new Happilee_HFC_Api();
		}

		public function init_operation_class() {
			require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-operation.php';
			$operation = new Happilee_HFC_Operation();
			// $operation->hfc_fetch_dbData();
		}

		public function add_admin_menu() {
			add_options_page(
				'Happilee Forms Connect',
				'Happilee Forms',
				'manage_options',
				'happilee-forms-connect',
				array( $this, 'hfc_settings_page' )
			);
		}

		public function load_happilee_forms_connect_scripts( $hook ) {
			// Only load on your plugin's settings page
			if ( $hook !== 'settings_page_happilee-forms-connect' ) {
				return;
			}

			$js_file  = HAPPILEE_FORMS_PLUGIN_URL . 'assets/js/bundle.js';
			$css_file = HAPPILEE_FORMS_PLUGIN_URL . 'assets/css/main.css';

			if ( file_exists( HAPPILEE_FORMS_PLUGIN_DIR . 'assets/css/main.css' ) ) {
				wp_enqueue_style( 'wphfc-backend-style', $css_file, array(), HAPPILEE_FORMS_VERSION );
			}

			if ( file_exists( HAPPILEE_FORMS_PLUGIN_DIR . 'assets/js/bundle.js' ) ) {
				wp_enqueue_script( 'wphfc-backend-js', $js_file, array( 'jquery' ), HAPPILEE_FORMS_VERSION, true );

				// wp_localize_script must be called AFTER wp_enqueue_script
				wp_localize_script( 'wphfc-backend-js', 'happileeConnect', array(
					'rest_url'    => esc_url_raw( rest_url( 'wphfc/v1/' ) ),
					'wphfc_nonce' => wp_create_nonce( 'wp_rest' ),
					'plugin_url'  => HAPPILEE_FORMS_PLUGIN_URL,
				) );
			}
		}

		public function hfc_settings_page() {
			?>
			<div class="wrap">
				<div id="wp-hfc-main"></div>
			</div>
			<?php
		}

	}

}

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
