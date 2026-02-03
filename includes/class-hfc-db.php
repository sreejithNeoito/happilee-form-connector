<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'happilee_HFC_DB' ) ) {

	class happilee_HFC_DB {

		private $table_name;
		private $db_version = '1.0';

		public function __construct() {
			global $wpdb;
			$this->table_name = $wpdb->prefix . 'hfc_forms_data';
		}

		/**
		 * Check if the table exists in the database
		 *
		 * @return bool True if table exists, false otherwise
		 */
		private function is_table_exists() {
			global $wpdb;
			$table = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" );
			return ( $table === $this->table_name );
		}

		/**
		 * Check if the table exists, and if not, create it
		 */
		public function hfc_check_and_create_dataTable() {
			if ( ! $this->is_table_exists() ) {
				$this->hfc_create_dataTable();
			}
		}

		/**
		 * Create the table if it does not exist
		 */
		public function hfc_create_dataTable() {
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

			require_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );

			// Store the database version
			add_option( 'hfc_db_version', $this->db_version );
		}

		/**
		 * Delete the table from the database
		 */
		public function hfc_delete_dataTable() {
			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );
			delete_option( 'hfc_db_version' );
		}

	}

}
