<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happfoco_DB' ) ) {

	class Happfoco_DB {

		private $table_name;
		private $db_version = '1.0';

		/**
		 * Constructor — sets the custom table name using the WordPress prefix.
		 */
		public function __construct() {
			global $wpdb;
			$this->table_name = $wpdb->prefix . 'happfoco_forms_data';
		}

		/**
		 * Check if the plugin's custom table exists in the database.
		 *
		 * @return bool True if the table exists, false otherwise.
		 */
		private function is_table_exists() {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$this->table_name
				)
			);

			return ( $this->table_name === $table );
		}

		/**
		 * Check if the table exists and create it if it does not.
		 *
		 * @return void
		 */
		public function happfoco_check_and_create_table() {
			if ( ! $this->is_table_exists() ) {
				$this->happfoco_create_table();
			}
		}

		/**
		 * Create the plugin's custom database table using dbDelta.
		 *
		 * Also stores the current database schema version as a WordPress option.
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

			// Store the database version.
			add_option( 'happfoco_db_version', $this->db_version );
		}
	}
}
