<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Happilee_Forms_Connect')) {

	class Happilee_Forms_Connect
	{

		private static $instance = null;

		public static function get_instance()
		{
			if (self::$instance === null) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct()
		{
			add_action('admin_menu', array($this, 'add_admin_menu'));
			add_action('admin_enqueue_scripts', array($this, 'load_happilee_forms_connect_scripts'));
			add_action('plugins_loaded', array($this, 'load_api_class'), 20);
			add_action('plugins_loaded', array($this, 'init_operation_class'), 20);

			// Load textdomain for backward compatibility (WordPress < 4.6)
			add_action('plugins_loaded', array($this, 'happilee_forms_connect_load_textdomain'));
		}

		/**
		 * Load plugin textdomain
		 * For WordPress < 4.6 compatibility
		 */
		public function happilee_forms_connect_load_textdomain()
		{
			load_plugin_textdomain(
				'happilee-forms-connector',
				false,
				dirname(plugin_basename(HAPPILEE_FORMS_PLUGIN_FILE)) . '/languages'
			);
		}

		public function load_api_class()
		{
			require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-api.php';
			Happilee_HFC_Api::get_instance();
		}

		public function init_operation_class()
		{
			require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-operation.php';
			$operation = new Happilee_HFC_Operation();
		}

		/**
		 * Add admin menu
		 */
		public function add_admin_menu()
		{
			add_options_page(
				__('Happilee Forms Connector', 'happilee-forms-connector'),
				__('Happilee Forms Connector', 'happilee-forms-connector'),
				'manage_options',
				'happilee-forms-connector',
				array($this, 'hfc_settings_page')
			);
		}

		public function load_happilee_forms_connect_scripts($hook)
		{
			// Only load on your plugin's settings page
			if ($hook !== 'settings_page_happilee-forms-connector') {
				return;
			}

			$js_file = HAPPILEE_FORMS_PLUGIN_URL . 'assets/js/bundle.js';
			$css_file = HAPPILEE_FORMS_PLUGIN_URL . 'assets/css/main.css';

			if (file_exists(HAPPILEE_FORMS_PLUGIN_DIR . 'assets/css/main.css')) {
				wp_enqueue_style('wphfc-backend-style', $css_file, array(), HAPPILEE_FORMS_VERSION);
			}

			if (file_exists(HAPPILEE_FORMS_PLUGIN_DIR . 'assets/js/bundle.js')) {
				// Add wp-i18n as a dependency for translations
				wp_enqueue_script(
					'wphfc-backend-js',
					$js_file,
					array('jquery', 'wp-element', 'wp-i18n'),
					HAPPILEE_FORMS_VERSION,
					true
				);

				// Set up translations for React/JavaScript
				if (function_exists('wp_set_script_translations')) {
					wp_set_script_translations(
						'wphfc-backend-js', // Must match the script handle above
						'happilee-forms-connector',
						HAPPILEE_FORMS_PLUGIN_DIR . 'languages'
					);
				}

				// Localize script must be called AFTER wp_enqueue_script
				wp_localize_script('wphfc-backend-js', 'happileeConnect', array(
					'rest_url' => esc_url_raw(rest_url('wphfc/v1/')),
					'wphfc_nonce' => wp_create_nonce('wp_rest'),
					'plugin_url' => HAPPILEE_FORMS_PLUGIN_URL,
				));
			}
		}

		public function hfc_settings_page()
		{
			?>
			<div class="wrap">
				<div id="wp-hfc-main"></div>
			</div>
			<?php
		}

	}

}
