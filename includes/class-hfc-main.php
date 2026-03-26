<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happfoco_Main' ) ) {

	class Happfoco_Main {

		private static $instance = null;

		/**
		 * Get or create the singleton instance.
		 *
		 * @return self
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor — registers all WordPress hooks.
		 */
		private function __construct() {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'happfoco_load_scripts' ) );
			add_action( 'plugins_loaded', array( $this, 'load_api_class' ), 20 );
			add_action( 'plugins_loaded', array( $this, 'init_operation_class' ), 20 );

			// Add settings link.
			add_filter(
				'plugin_action_links_' . plugin_basename( HAPPILEE_FORMS_PLUGIN_FILE ),
				array( $this, 'add_plugin_action_links' )
			);
		}

		/**
		 * Add a Settings link to the plugin action links on the Plugins screen.
		 *
		 * @param array $links Existing plugin action links.
		 * @return array Modified plugin action links.
		 */
		public function add_plugin_action_links( $links ) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'options-general.php?page=happilee-forms-connector' ),
				__( 'Settings', 'happilee-forms-connector' )
			);
			array_unshift( $links, $settings_link );

			return $links;
		}

		/**
		 * Load the API class on plugins_loaded.
		 *
		 * @return void
		 */
		public function load_api_class() {
			require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-api.php';
			Happfoco_Api::get_instance();
		}

		/**
		 * Instantiate the Operation class on plugins_loaded.
		 *
		 * @return void
		 */
		public function init_operation_class() {
			require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-operation.php';
			new Happfoco_Operation();
		}

		/**
		 * Register the plugin settings page under the Settings menu.
		 *
		 * @return void
		 */
		public function add_admin_menu() {
			add_options_page(
				__( 'Happilee Forms Connector', 'happilee-forms-connector' ),
				__( 'Happilee Forms Connector', 'happilee-forms-connector' ),
				'manage_options',
				'happilee-forms-connector',
				array( $this, 'happfoco_settings_page' )
			);
		}

		/**
		 * Enqueue plugin scripts and styles — only on the plugin settings page.
		 *
		 * @param string $hook Current admin page hook suffix.
		 * @return void
		 */
		public function happfoco_load_scripts( $hook ) {
			// Only load on the plugin's settings page.
			if ( 'settings_page_happilee-forms-connector' !== $hook ) {
				return;
			}

			$js_file  = HAPPILEE_FORMS_PLUGIN_URL . 'assets/js/bundle.js';
			$css_file = HAPPILEE_FORMS_PLUGIN_URL . 'assets/css/main.css';

			if ( file_exists( HAPPILEE_FORMS_PLUGIN_DIR . 'assets/css/main.css' ) ) {
				wp_enqueue_style( 'happfoco-backend-style', $css_file, array(), HAPPILEE_FORMS_VERSION );
			}

			if ( file_exists( HAPPILEE_FORMS_PLUGIN_DIR . 'assets/js/bundle.js' ) ) {
				wp_enqueue_script(
					'happfoco-backend-js',
					$js_file,
					array( 'jquery', 'wp-element', 'wp-i18n' ),
					HAPPILEE_FORMS_VERSION,
					true
				);

				// Set up translations for React/JavaScript.
				if ( function_exists( 'wp_set_script_translations' ) ) {
					wp_set_script_translations(
						'happfoco-backend-js',
						'happilee-forms-connector',
						HAPPILEE_FORMS_PLUGIN_DIR . 'languages'
					);
				}

				// Localize script must be called AFTER wp_enqueue_script.
				wp_localize_script(
					'happfoco-backend-js',
					'happileeConnect',
					array(
						'rest_url'       => esc_url_raw( rest_url( 'happfoco/v1/' ) ),
						'happfoco_nonce' => wp_create_nonce( 'wp_rest' ),
						'plugin_url'     => HAPPILEE_FORMS_PLUGIN_URL,
					)
				);
			}
		}

		/**
		 * Custom logging function that respects WordPress debug settings.
		 *
		 * @param string $message The message to log.
		 * @param string $level   Log level: 'info', 'warning', or 'error'.
		 * @return void
		 */
		public static function log_message( $message, $level = 'info' ) {
			if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
				if ( defined( 'WP_DEBUG_LOG' ) && true === WP_DEBUG_LOG ) {
					// @codingStandardsIgnoreLine
					error_log( sprintf( 'Happilee Forms Connect [%s]: %s', strtoupper( $level ), $message ) );
				}
			}
			do_action( 'happfoco_log_message', $message, $level );
		}

		/**
		 * Render the plugin settings page markup.
		 *
		 * @return void
		 */
		public function happfoco_settings_page() {
			?>
			<div class="wrap">
				<div id="wp-hfc-main"></div>
			</div>
			<?php
		}

	}

}
