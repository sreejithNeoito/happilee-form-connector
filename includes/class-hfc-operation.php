<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happilee_HFC_Operation' ) ) {

	class Happilee_HFC_Operation {

		private $table_name;
		private $form_configurations = array();

		public function __construct() {
			global $wpdb;
			$this->table_name = $wpdb->prefix . 'hfc_forms_data';

			// Initialize dynamic hooks
			add_action( 'init', array( $this, 'wphfc_dynamic_hooks' ) );
		}

		/*
		 * Dynamic Hook Selection
		 * This function fetches active hooks from the database and attaches them
		 */
		public function wphfc_dynamic_hooks() {
			global $wpdb;

			// Get ALL configurations, not just distinct hooks
			$configurations = $wpdb->get_results(
				"SELECT id, form_id, active_hook, form_type FROM {$this->table_name} WHERE is_enabled = 1",
				ARRAY_A
			);

			if ( empty( $configurations ) ) {
				return;
			}

			// Store configurations for later reference
			foreach ( $configurations as $config ) {
				$key                               = $config['form_type'] . '_' . $config['form_id'];
				$this->form_configurations[ $key ] = $config;
			}

			// Track which hooks we've already added to avoid duplicate hook registrations
			$added_hooks = array();

			foreach ( $configurations as $config ) {
				$hook_key = $config['form_type'] . '_' . $config['active_hook'];

				// Skip if we've already added this hook for this form type
				if ( isset( $added_hooks[ $hook_key ] ) ) {
					continue;
				}

				switch ( $config['form_type'] ) {

					case 'cf7':
						add_action(
							$config['active_hook'],
							array( $this, 'wphfc_handle_cf7' ),
							10,
							1
						);
						$added_hooks[ $hook_key ] = true;
						break;

					case 'wpforms':
						add_action(
							$config['active_hook'],
							array( $this, 'wphfc_handle_wpforms' ),
							10,
							4
						);
						$added_hooks[ $hook_key ] = true;
						break;
				}
			}
		}

		private function get_form_configuration( $form_id, $form_type, $current_hook ) {
			global $wpdb;

			// Check if configuration exists in memory first
			$key = $form_type . '_' . $form_id;
			if ( isset( $this->form_configurations[ $key ] ) ) {
				$config = $this->form_configurations[ $key ];
				if ( $config['active_hook'] === $current_hook ) {
					return $config;
				}
			}

			// Fallback: Query database for specific form + hook combination
			$config = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, form_id, active_hook, form_type FROM {$this->table_name}
					WHERE form_id = %s
					AND form_type = %s
					AND active_hook = %s
					AND is_enabled = 1
					LIMIT 1",
					$form_id,
					$form_type,
					$current_hook
				),
				ARRAY_A
			);

			return $config;
		}

		/*
		 * Handle Contact Form 7 Submission
		 */
		public function wphfc_handle_cf7( $contact_form ) {

			$form_id      = $contact_form->id();
			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'cf7', $current_hook );

			if ( empty( $config ) ) {
				return;
			}

			$submission = WPCF7_Submission::get_instance();
			if ( ! $submission ) {
				return;
			}

			$this->wphfc_send_to_api(
				$contact_form,
				$submission->get_posted_data(),
				$submission->uploaded_files(),
				'cf7'
			);

		}

		/*
		 * Handle WPForms Submission
		 */
		public function wphfc_handle_wpforms( $fields, $entry, $form_data, $entry_id ) {

			$form_id      = $form_data['id'];
			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'wpforms', $current_hook );

			if ( empty( $config ) ) {
				return;
			}

			$this->wphfc_send_to_api(
				$form_data,
				$fields,
				array(),
				'wpforms'
			);
		}

		/*
		 * Handle API Communication
		 */
		private function wphfc_send_to_api( $form_object, $posted_data, $uploaded_files, $form_type ) {

			$api_endpoint = get_option( 'wphfc_api_endpoint', '' );
			$api_key      = get_option( 'wphfc_api_key', '' );

			if ( empty( $api_endpoint ) ) {
				error_log( 'HFC API Error: API endpoint is not configured' );
				return;
			}

			// Prepare form details based on form type
			if ( $form_type === 'cf7' ) {
				$form_id   = $form_object->id();
				$form_name = $form_object->title();
			} else {
				$form_id   = $form_object['id'];
				$form_name = $form_object['settings']['form_title'];
			}

			// Prepare payload
			$payload = array(
				'form_id'   => $form_id,
				'form_name' => $form_name,
				'fields'    => $posted_data,
				'files'     => $uploaded_files,
				'source'    => $form_type,
				'site_url'  => get_site_url(),
			);

			$args = array(
				'method'  => 'POST',
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			);

			// Add API key if provided
			if ( ! empty( $api_key ) ) {
				$args['headers']['Authorization'] = 'Bearer ' . $api_key;
			}

			$response = wp_remote_post( $api_endpoint, $args );

			// Log API errors
			if ( is_wp_error( $response ) ) {
				error_log( 'HFC API Error: ' . $response->get_error_message() );
			} else {
				$response_code = wp_remote_retrieve_response_code( $response );
				if ( $response_code !== 200 ) {
					error_log( 'HFC API Error: Response code ' . $response_code );
					error_log( 'HFC API Response: ' . wp_remote_retrieve_body( $response ) );
				}
			}
		}
	}
}
