<?php
/**
 * Database schema management.
 *
 * @package Happilee_Forms_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happfoco_DB' ) ) {

	/**
	 * Creates and upgrades the plugin's custom tables.
	 */
	class Happfoco_DB {

		/**
		 * Form settings table name.
		 *
		 * @var string
		 */
		private $table_name;

		/**
		 * Template settings table name.
		 *
		 * @var string
		 */
		private $template_table_name;

		/**
		 * Current database schema version.
		 */
		const DB_VERSION = '1.1';

		/**
		 * Current database schema version.
		 *
		 * @var string
		 */
		private $db_version = self::DB_VERSION;

		/**
		 * Set the table names using the WordPress table prefix.
		 */
		public function __construct() {
			global $wpdb;
			$this->table_name          = $wpdb->prefix . 'happfoco_forms_data';
			$this->template_table_name = $wpdb->prefix . 'happfoco_template_data';
		}

		/**
		 * Check whether a table exists in the database.
		 *
		 * @param string $check_table_name Table name. Defaults to the form settings table.
		 * @return bool True if the table exists, false otherwise.
		 */
		private function is_table_exists( $check_table_name = '' ) {
			global $wpdb;

			if ( empty( $check_table_name ) ) {
				$check_table_name = $this->table_name;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$check_table_name
				)
			);

			return ( $check_table_name === $table );
		}

		/**
		 * Create any missing tables and run pending upgrades.
		 *
		 * @return void
		 */
		public function happfoco_check_and_create_table() {
			if ( ! $this->is_table_exists( $this->table_name ) ) {
				$this->happfoco_create_table();
			}

			if ( ! $this->is_table_exists( $this->template_table_name ) ) {
				$this->happfoco_create_template_table();
			}

			$this->maybe_upgrade();
		}

		/**
		 * Create the form settings table and store the schema version.
		 *
		 * @return void
		 */
		public function happfoco_create_table() {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$this->table_name} (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				form_id varchar(100) NOT NULL,
				form_name varchar(255) NOT NULL,
				form_type varchar(100) NOT NULL,
				is_enabled tinyint(1) DEFAULT 0 NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				active_hook varchar(255) DEFAULT '' NOT NULL,
				connected_fields longtext NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			add_option( 'happfoco_db_version', $this->db_version );
		}

		/**
		 * Create the template settings table.
		 *
		 * @return void
		 */
		public function happfoco_create_template_table() {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$this->template_table_name} (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				form_id varchar(100) NOT NULL,
				form_type varchar(100) NOT NULL,
				template_id varchar(100) NOT NULL,
				template_name varchar(255) NOT NULL,
				template_type varchar(100) DEFAULT '' NOT NULL,
				param_mappings longtext NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY form_template_unique (form_id, form_type)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * Bring the schema up to date when the stored version is older.
		 *
		 * Only creates new tables; existing tables are never altered.
		 *
		 * @return void
		 */
		private function maybe_upgrade() {
			$installed_version = get_option( 'happfoco_db_version', '1.0' );

			if ( version_compare( $installed_version, $this->db_version, '<' ) ) {
				if ( ! $this->is_table_exists( $this->template_table_name ) ) {
					$this->happfoco_create_template_table();
				}

				update_option( 'happfoco_db_version', $this->db_version );
			}
		}
	}
}
