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
				"SELECT id, form_id, active_hook, form_type, connected_fields FROM {$this->table_name} WHERE is_enabled = 1",
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

					case 'ninja_forms':
						add_action(
							$config['active_hook'],
							array( $this, 'wphfc_handle_ninja_forms' ),
							10,
							1
						);
						$added_hooks[ $hook_key ] = true;
						break;

					case 'forminator':
						add_action(
							$config['active_hook'],
							array( $this, 'wphfc_handle_forminator' ),
							10,
							1
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

			$config = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->table_name}
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
			$form_name    = get_the_title( $form_id );
			$current_hook = current_action();
			$config       = $this->get_form_configuration( $form_id, 'cf7', $current_hook );

			if ( empty( $config ) ) {
				error_log( 'HFC: No configuration found for CF7 form ID ' . $form_id );
				return;
			}

			$connected_fields = $config['connected_fields'];
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				error_log( 'HFC: No field mappings configured for CF7 form ID ' . $form_id );
				return;
			}

			$submission = WPCF7_Submission::get_instance();

			if ( ! $submission ) {
				return;
			}

			$posted_data = $submission->get_posted_data();

			$api_data                = [];
			$api_data['form_name']   = sanitize_text_field( $form_name );
			$api_data['submit_time'] = current_time( 'Y-m-d H:i:s' );
			foreach ( $mapping as $label => $field_key ) {

				if ( isset( $posted_data[ $field_key ] ) ) {
					$field_value = $posted_data[ $field_key ];

					// Handle CF7 fields like radio / checkbox (arrays)
					if ( is_array( $field_value ) ) {
						$field_value = implode( ', ', $field_value );
					}
					$api_data[ $label ] = sanitize_text_field( $field_value );
				}
			}

			if ( empty( $api_data ) || count( $api_data ) <= 2 ) {
				error_log( 'HFC: No mapped field data found for CF7 form ID ' . $form_id );
				return;
			}

			$this->wphfc_send_to_api( $api_data );

		}

		/*
		 * Handle WPForms Submission
		 */

		public function wphfc_handle_wpforms( $fields, $entry, $form_data, $entry_id ) {
			$form_id      = isset( $form_data['id'] ) ? $form_data['id'] : 0;
			$form_name    = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : 'WPForm #' . $form_id;
			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'wpforms', $current_hook );

			if ( empty( $config ) ) {
				error_log( 'HFC: No configuration found for WPForms form ID ' . $form_id );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				error_log( 'HFC: No field mappings for WPForms form ID ' . $form_id );
				return;
			}

			$api_data                = array();
			$api_data['form_name']   = sanitize_text_field( $form_name );
			$api_data['submit_time'] = current_time( 'Y-m-d H:i:s' );

			// Define field types to ignore
			$ignore_types = array(
				'recaptcha',
				'captcha',
				'hcaptcha',
				'honeypot',
				'html',
				'divider',
				'pagebreak',
				'file-upload',
				'payment-single',
				'payment-multiple',
				'payment-checkbox',
				'payment-select',
				'credit-card',
				'richtext',
				'content',
				'layout',
				'entry-preview',
			);

			foreach ( $mapping as $happilee_field => $wpforms_value ) {

				if ( ! isset( $fields[ $wpforms_value ] ) ) {
					continue;
				}
				$field = $fields[ $wpforms_value ];

				if ( isset( $field['type'] ) && in_array( $field['type'], $ignore_types, true ) ) {
					continue;
				}

				if ( isset( $field['name'] ) && $field['name'] === 'Name' && $happilee_field === 'First Name' ) {
					$first                       = isset( $field['first'] ) ? sanitize_text_field( $field['first'] ) : '';
					$middle                      = isset( $field['middle'] ) ? sanitize_text_field( $field['middle'] ) : '';
					$api_data[ $happilee_field ] = trim( $first . ' ' . $middle );
				} elseif ( isset( $field['name'] ) && $field['name'] === 'Name' && $happilee_field === 'Last Name' ) {
					$api_data[ $happilee_field ] = isset( $field['last'] ) ? sanitize_text_field( $field['last'] ) : '';
				} else {
					$field_value = isset( $field['value'] ) ? $field['value'] : '';

					if ( empty( $field_value ) && $field_value !== '0' ) {
						continue;
					}

					if ( is_array( $field_value ) ) {
						$field_value = implode( ', ', array_map( 'sanitize_text_field', $field_value ) );
					} else {
						$field_value = sanitize_text_field( $field_value );
					}

					$api_data[ $happilee_field ] = $field_value;
				}
			}

			if ( count( $api_data ) <= 2 ) {
				error_log( 'HFC: No mapped field data found for WPForms form ID ' . $form_id );
				return;
			}

			$this->wphfc_send_to_api( $api_data );
		}

		/*
		 * Handle Ninja Forms Submission
		 */

		public function wphfc_handle_ninja_forms( $form_data ) {
			$form_id      = isset( $form_data['form_id'] ) ? $form_data['form_id'] : 0;
			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'ninja_forms', $current_hook );

			if ( empty( $config ) ) {
				error_log( 'HFC: No configuration found for Ninja Forms form ID ' . $form_id );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				error_log( 'HFC: No field mappings for Ninja Forms form ID ' . $form_id );
				return;
			}

			$submitted_fields = isset( $form_data['fields'] ) ? $form_data['fields'] : array();

			if ( empty( $submitted_fields ) ) {
				error_log( 'HFC: No submitted fields for Ninja Forms form ID ' . $form_id );
				return;
			}
			$form_title = '';

			// Method 1
			if ( isset( $form_data['settings']['title'] ) && ! empty( $form_data['settings']['title'] ) ) {
				$form_title = $form_data['settings']['title'];
			}

			// Method 2:
			if ( empty( $form_title ) && class_exists( 'Ninja_Forms' ) ) {
				try {
					$form = Ninja_Forms()->form( $form_id )->get();
					if ( $form && method_exists( $form, 'get_setting' ) ) {
						$title = $form->get_setting( 'title' );
						if ( ! empty( $title ) ) {
							$form_title = $title;
						}
					}
				} catch ( Exception $e ) {
					error_log( 'HFC: Error getting Ninja Forms title: ' . $e->getMessage() );
				}
			}

			// Method 3:
			if ( empty( $form_title ) && isset( $config['form_name'] ) && ! empty( $config['form_name'] ) ) {
				$form_title = $config['form_name'];
			}

			// Fallback
			if ( empty( $form_title ) ) {
				$form_title = 'Ninja Form #' . $form_id;
			}

			$api_data                = array();
			$api_data['form_name']   = sanitize_text_field( $form_title );
			$api_data['submit_time'] = current_time( 'Y-m-d H:i:s' );

			foreach ( $mapping as $happilee_field => $ninja_field_key ) {

				foreach ( $submitted_fields as $field ) {

					if ( isset( $field['key'] ) && $field['key'] === $ninja_field_key ) {
						$field_value = isset( $field['value'] ) ? $field['value'] : '';

						if ( empty( $field_value ) && $field_value !== '0' ) {
							break;
						}
						if ( is_array( $field_value ) ) {
							$field_value = implode( ', ', array_map( 'sanitize_text_field', $field_value ) );
						} else {
							$field_value = sanitize_text_field( $field_value );
						}

						$api_data[ $happilee_field ] = $field_value;
						break;
					}
				}
			}

			if ( count( $api_data ) <= 2 ) {
				error_log( 'HFC: No mapped field data found for Ninja Forms form ID ' . $form_id );
				return;
			}

			$this->wphfc_send_to_api( $api_data );
		}

		/*
		 * Handle Forminator Submission
		 */
		public function wphfc_handle_forminator( $form_id ) {

			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'forminator', $current_hook );

			if ( empty( $config ) ) {
				error_log( 'HFC: No configuration found for Forminator form ID ' . $form_id );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				error_log( 'HFC: No field mappings for Forminator form ID ' . $form_id );
				return;
			}

			$form_model = Forminator_Form_Model::model()->load( (int) $form_id );

			if ( is_wp_error( $form_model ) || empty( $form_model ) ) {
				error_log( 'HFC: Failed to load Forminator form model for ID ' . $form_id );
				return;
			}

			$submitted_data = isset( $_POST ) ? $_POST : [];

			$api_data                = [];
			$api_data['form_name']   = $form_model->name;
			$api_data['submit_time'] = current_time( 'Y-m-d H:i:s' );

			foreach ( $mapping as $lablel => $field_key ) {
				if ( isset( $submitted_data[ $field_key ] ) ) {
					$field_value = $submitted_data[ $field_key ];

					// Handle array values (checkboxes, multi-select, etc.)
					if ( is_array( $field_value ) ) {
						$field_value = implode( ', ', $field_value );
					}
					$api_data[ $lablel ] = sanitize_text_field( $field_value );
				}

			}

			if ( empty( $api_data ) || count( $api_data ) <= 2 ) {
				error_log( 'HFC: No mapped field data found for Forminator form ID ' . $form_id );
				return;
			}

			$this->wphfc_send_to_api( $api_data );
		}

		/*
		 * Handle API Communication
		 */
		private function wphfc_send_to_api( $api_data ) {

			$api_instance = Happilee_HFC_Api::get_instance();
			$api_endpoint = $api_instance->get_api_endpoint();

			$api_key = get_option( 'wphfc_api_key', '' );

			if ( empty( $api_endpoint ) ) {
				error_log( 'HFC API Error: API endpoint is not configured' );
				return;
			}

			if ( empty( $api_key ) ) {
				error_log( 'HFC API Error: API key is not configured' );
				return;
			}

			$response = wp_remote_post( $api_endpoint, array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $api_data ),
				'timeout' => 30,
			) );

			// Handle response
			if ( is_wp_error( $response ) ) {
				error_log( 'HFC API Error: ' . $response->get_error_message() );
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( $response_code !== 200 ) {
				error_log( sprintf(
					'HFC API Error: Status %d - %s',
					$response_code,
					$response_body
				) );
				return false;
			}
			return true;
		}
	}
}
