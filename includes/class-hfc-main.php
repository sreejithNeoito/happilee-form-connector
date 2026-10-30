<?php
/**
 * Plugin bootstrap: admin page, assets and class loading.
 *
 * @package Happilee_Forms_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happfoco_Main' ) ) {

	/**
	 * Main plugin class.
	 */
	class Happfoco_Main {

		/**
		 * Object cache group shared by the plugin.
		 */
		const CACHE_GROUP = 'happfoco';

		/**
		 * Singleton instance.
		 *
		 * @var Happfoco_Main|null
		 */
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
			add_action( 'plugins_loaded', array( $this, 'maybe_create_table' ), 10 );
			add_action( 'plugins_loaded', array( $this, 'load_api_class' ), 20 );
			add_action( 'plugins_loaded', array( $this, 'init_operation_class' ), 20 );

			add_filter(
				'plugin_action_links_' . plugin_basename( HAPPILEE_FORMS_PLUGIN_FILE ),
				array( $this, 'add_plugin_action_links' )
			);
		}

		/**
		 * Ensure the plugin database tables exist.
		 *
		 * Runs on admin requests, and on any request while the stored schema
		 * version is out of date, so tables are created after an update or a
		 * database reset without adding queries to every front-end page load.
		 *
		 * @return void
		 */
		public function maybe_create_table() {
			require_once HAPPILEE_FORMS_PLUGIN_DIR . 'includes/class-hfc-db.php';

			$is_current = version_compare( (string) get_option( 'happfoco_db_version', '0' ), Happfoco_DB::DB_VERSION, '>=' );
			if ( $is_current && ! is_admin() ) {
				return;
			}

			$db = new Happfoco_DB();
			$db->happfoco_check_and_create_table();
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
			if ( 'settings_page_happilee-forms-connector' !== $hook ) {
				return;
			}

			$js_file  = HAPPILEE_FORMS_PLUGIN_URL . 'assets/js/bundle.js';
			$css_file = HAPPILEE_FORMS_PLUGIN_URL . 'assets/css/main.css';

			if ( file_exists( HAPPILEE_FORMS_PLUGIN_DIR . 'assets/css/main.css' ) ) {
				wp_enqueue_style( 'happfoco-backend-style', $css_file, array(), HAPPILEE_FORMS_VERSION . '.' . filemtime( HAPPILEE_FORMS_PLUGIN_DIR . 'assets/css/main.css' ) );
			}

			if ( file_exists( HAPPILEE_FORMS_PLUGIN_DIR . 'assets/js/bundle.js' ) ) {
				wp_enqueue_script(
					'happfoco-backend-js',
					$js_file,
					array( 'jquery', 'wp-element', 'wp-i18n' ),
					HAPPILEE_FORMS_VERSION . '.' . filemtime( HAPPILEE_FORMS_PLUGIN_DIR . 'assets/js/bundle.js' ),
					true
				);

				if ( function_exists( 'wp_set_script_translations' ) ) {
					wp_set_script_translations(
						'happfoco-backend-js',
						'happilee-forms-connector',
						HAPPILEE_FORMS_PLUGIN_DIR . 'languages'
					);
				}

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
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( 'Happilee Forms Connect [%s]: %s', strtoupper( $level ), $message ) );
				}
			}
			do_action( 'happfoco_log_message', $message, $level );
		}

		/**
		 * Invalidate cached form and template settings.
		 *
		 * Cache keys are salted with the group's "last_changed" value, so
		 * resetting it makes every previously cached entry unreachable.
		 *
		 * @return void
		 */
		public static function flush_settings_cache() {
			wp_cache_delete( 'last_changed', self::CACHE_GROUP );
		}

		/**
		 * Build a cache key that is invalidated by flush_settings_cache().
		 *
		 * @param string $key Base cache key.
		 * @return string Salted cache key.
		 */
		public static function cache_key( $key ) {
			return $key . ':' . wp_cache_get_last_changed( self::CACHE_GROUP );
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
