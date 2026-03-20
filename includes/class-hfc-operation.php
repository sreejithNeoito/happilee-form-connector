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

			$cache_key   = 'hfc_active_configurations';
			$cache_group = 'happilee_hfc';

			$configurations = wp_cache_get( $cache_key, $cache_group );

			if ( false === $configurations ) {
				$table_name = $this->table_name;

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name derived from $wpdb->prefix, results are cached.
				$configurations = $wpdb->get_results(
					"SELECT id, form_id, active_hook, form_type, connected_fields FROM {$table_name} WHERE is_enabled = 1",
					ARRAY_A
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				wp_cache_set( $cache_key, $configurations, $cache_group, 300 );
			}

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
						if ( $config['active_hook'] === 'wpforms_process_before' ) {
							add_action(
								$config['active_hook'],
								array( $this, 'wphfc_handle_wpforms_2_params' ),
								10,
								2
							);
						} elseif ( $config['active_hook'] === 'wpforms_process_after' ) {
							add_action(
								$config['active_hook'],
								array( $this, 'wphfc_handle_wpforms_3_params' ),
								10,
								3
							);
						} else {
							add_action(
								$config['active_hook'],
								array( $this, 'wphfc_handle_wpforms' ),
								10,
								4
							);
						}
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
			$key = $form_type . '_' . $form_id . '_' . $current_hook;

			if ( isset( $this->form_configurations[ $key ] ) ) {
				return $this->form_configurations[ $key ];
			}

			$cache_key   = 'form_config_' . md5( $key );
			$cache_group = 'my_plugin_forms';

			$config = wp_cache_get( $cache_key, $cache_group );

			if ( false === $config ) {
				$table  = $this->table_name;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$config = $wpdb->get_row(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and escaped.
						"SELECT * FROM {$table}
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

				wp_cache_set( $cache_key, $config, $cache_group, 300 );
			}
			// Store in memory cache for this request
			$this->form_configurations[ $key ] = $config;

			return $config;
		}

		/**
		 * Resolve country code from visitor IP
		 */
		private function resolve_country_code_from_ip() {
			$visitor_ip = '';

			// Handle proxies / load balancers
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ips        = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
				$visitor_ip = trim( $ips[0] );
			} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
				$visitor_ip = $_SERVER['REMOTE_ADDR'];
			}

			// Fallback for localhost / dev environment
			if ( empty( $visitor_ip ) || in_array( $visitor_ip, array( '127.0.0.1', '::1' ), true ) ) {
				return '+1';
			}

			$response = wp_remote_get(
				"https://ipapi.co/{$visitor_ip}/country_calling_code/",
				array( 'timeout' => 5 )
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$code = trim( wp_remote_retrieve_body( $response ) );
				if ( ! empty( $code ) && str_starts_with( $code, '+' ) ) {
					return sanitize_text_field( $code );
				}
			}

			Happilee_Forms_Connect::log_message( 'Failed to detect country code from IP: ' . $visitor_ip, 'warning' );
			return '+1'; // last resort fallback
		}

		/*
		 * Handle API Communication
		 *
		 * Sends form submission data to the createContact endpoint
		 * using the x-api-key header (decrypted from stored settings).
		 */
		private function wphfc_send_to_api( $api_data ) {

			$api_instance = Happilee_HFC_Api::get_instance();

			// Use the dedicated createContact endpoint
			$api_endpoint = $api_instance->get_create_contact_endpoint();

			// Retrieve and decrypt the stored API key
			$api_key = $api_instance->get_api_key();

			if ( empty( $api_endpoint ) ) {
				Happilee_Forms_Connect::log_message( 'API endpoint is not configured', 'error' );
				return false;
			}

			if ( empty( $api_key ) ) {
				Happilee_Forms_Connect::log_message( 'API key is not configured', 'error' );
				return false;
			}

			// if missing or set to "country-code", detect from visitor IP
			if ( empty( $api_data['country_code'] ) || $api_data['country_code'] === 'country-code' ) {
				$api_data['country_code'] = $this->resolve_country_code_from_ip();
				Happilee_Forms_Connect::log_message( 'Auto-detected country code: ' . $api_data['country_code'], 'info' );
			}

			$response = wp_remote_post( $api_endpoint, array(
				'headers' => array(
					'x-api-key'    => $api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $api_data ),
				'timeout' => 30,
			) );

			// Handle response
			if ( is_wp_error( $response ) ) {
				Happilee_Forms_Connect::log_message( 'API request error: ' . $response->get_error_message(), 'error' );
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( $response_code !== 200 ) {
				Happilee_Forms_Connect::log_message(
					sprintf( 'API error - Status %d: %s', $response_code, $response_body ),
					'error'
				);
				return false;
			}

			return true;
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
				Happilee_Forms_Connect::log_message( 'No configuration found for CF7 form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = $config['connected_fields'];
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happilee_Forms_Connect::log_message( 'No configuration found for CF7 form ID ' . $form_id, 'error' );
				return;
			}

			$submission = WPCF7_Submission::get_instance();

			if ( ! $submission ) {
				return;
			}

			$posted_data = $submission->get_posted_data();

			$api_data = [];

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

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happilee_Forms_Connect::log_message( 'No mapped field data found for CF7 form ID ' . $form_id, 'error' );
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
				Happilee_Forms_Connect::log_message( 'No configuration found for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happilee_Forms_Connect::log_message( 'No field mappings for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$api_data     = array();
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

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happilee_Forms_Connect::log_message( 'No mapped field data found for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$this->wphfc_send_to_api( $api_data );
		}

		// WPForm two parameters
		public function wphfc_handle_wpforms_2_params( $entry, $form_data ) {
			$form_id      = isset( $form_data['id'] ) ? $form_data['id'] : 0;
			$form_name    = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : 'WPForm #' . $form_id;
			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'wpforms', $current_hook );

			if ( empty( $config ) ) {
				Happilee_Forms_Connect::log_message( 'No configuration found for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happilee_Forms_Connect::log_message( 'No field mappings for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			// Get fields from form_data instead
			$fields = isset( $form_data['fields'] ) ? $form_data['fields'] : array();

			if ( empty( $fields ) ) {
				Happilee_Forms_Connect::log_message( 'No fields found for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$api_data     = array();
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

			foreach ( $mapping as $happilee_field => $wpforms_field_id ) {

				if ( ! isset( $fields[ $wpforms_field_id ] ) ) {
					continue;
				}

				$field = $fields[ $wpforms_field_id ];

				if ( isset( $field['type'] ) && in_array( $field['type'], $ignore_types, true ) ) {
					continue;
				}

				$field_value = isset( $entry['fields'][ $wpforms_field_id ] ) ? $entry['fields'][ $wpforms_field_id ] : '';

				if ( is_array( $field_value ) && $happilee_field == 'First Name' ) {
					$first       = isset( $field_value['first'] ) ? sanitize_text_field( $field_value['first'] ) : '';
					$middle      = isset( $field_value['middle'] ) ? sanitize_text_field( $field_value['middle'] ) : '';
					$field_value = trim( $first . ' ' . $middle );
				}

				if ( is_array( $field_value ) && $happilee_field == 'Last Name' ) {
					$last        = isset( $field_value['last'] ) ? sanitize_text_field( $field_value['last'] ) : '';
					$field_value = $last;
				}

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

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happilee_Forms_Connect::log_message( 'No mapped field data found for WPForms form ID ' . $form_id, 'warning' );
				return;
			}

			$this->wphfc_send_to_api( $api_data );
		}

		// WPForms three parameters (for wpforms_process_after)
		public function wphfc_handle_wpforms_3_params( $fields, $entry, $form_data ) {
			$form_id      = isset( $form_data['id'] ) ? $form_data['id'] : 0;
			$form_name    = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : 'WPForm #' . $form_id;
			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'wpforms', $current_hook );

			if ( empty( $config ) ) {
				Happilee_Forms_Connect::log_message( 'No configuration found for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happilee_Forms_Connect::log_message( 'No field mappings for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$api_data     = array();
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

			foreach ( $mapping as $happilee_field => $wpforms_field_id ) {

				if ( ! isset( $fields[ $wpforms_field_id ] ) ) {
					continue;
				}

				$field = $fields[ $wpforms_field_id ];

				if ( isset( $field['type'] ) && in_array( $field['type'], $ignore_types, true ) ) {
					continue;
				}

				if ( isset( $field['name'] ) && $field['name'] === 'Name' && $happilee_field === 'First Name' ) {
					$first                       = isset( $field['first'] ) ? sanitize_text_field( $field['first'] ) : '';
					$middle                      = isset( $field['middle'] ) ? sanitize_text_field( $field['middle'] ) : '';
					$api_data[ $happilee_field ] = trim( $first . ' ' . $middle );
					continue;
				} elseif ( isset( $field['name'] ) && $field['name'] === 'Name' && $happilee_field === 'Last Name' ) {
					$api_data[ $happilee_field ] = isset( $field['last'] ) ? sanitize_text_field( $field['last'] ) : '';
					continue;
				}

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

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happilee_Forms_Connect::log_message( 'No mapped field data found for WPForms form ID ' . $form_id, 'warning' );
				return;
			}

			$this->wphfc_send_to_api( $api_data );
		}

		/*
		 * Handle Ninja Forms Submission
		 */

		public function wphfc_handle_ninja_forms( $form_data ) {

			if ( isset( $form_data['id'] ) ) {
				$form_id = $form_data['id'];
			} elseif ( isset( $form_data['form_id'] ) ) {
				$form_id = $form_data['form_id'];
			} else {
				Happilee_Forms_Connect::log_message( 'Invalid form ID for Ninja Forms', 'error' );
				return;
			}

			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'ninja_forms', $current_hook );

			if ( empty( $config ) ) {
				Happilee_Forms_Connect::log_message( 'No configuration found for Ninja Forms form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happilee_Forms_Connect::log_message( 'No field mappings for Ninja Forms form ID ' . $form_id, 'error' );
				return;
			}

			$submitted_fields = isset( $form_data['fields'] ) ? $form_data['fields'] : array();

			if ( empty( $submitted_fields ) ) {
				Happilee_Forms_Connect::log_message( 'No submitted fields for Ninja Forms form ID ' . $form_id, 'error' );
				return;
			}
			$form_title = '';

			// Get form title: Method 1
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
					Happilee_Forms_Connect::log_message( 'Error getting Ninja Forms title: ' . $e->getMessage(), 'error' );
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

			$api_data = array();
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

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happilee_Forms_Connect::log_message( 'No mapped field data found for Ninja Forms form ID ' . $form_id, 'warning' );
				return;
			}

			$this->wphfc_send_to_api( $api_data );
		}

		/*
		 * Handle Forminator Submission
		 */
		public function wphfc_handle_forminator( $form_id, $response = null ) {

			$current_hook = current_action();

			if ( is_object( $form_id ) ) {
				$entry   = $form_id;
				$form_id = isset( $entry->entry_id ) ? $entry->form_id : 0;
			} elseif ( is_array( $form_id ) ) {
				$form_id = isset( $form_id['form_id'] ) ? $form_id['form_id'] : 0;
			}

			if ( empty( $form_id ) ) {
				Happilee_Forms_Connect::log_message( 'Invalid form ID for Forminator', 'error' );
				return;
			}

			$config = $this->get_form_configuration( $form_id, 'forminator', $current_hook );

			if ( empty( $config ) ) {
				Happilee_Forms_Connect::log_message( 'No configuration found for Forminator form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happilee_Forms_Connect::log_message( 'No field mappings for Forminator form ID ' . $form_id, 'error' );
				return;
			}

			$form_model = Forminator_Form_Model::model()->load( (int) $form_id );

			if ( is_wp_error( $form_model ) || empty( $form_model ) ) {
				Happilee_Forms_Connect::log_message( 'Failed to load Forminator form model for ID ' . $form_id, 'error' );
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Forminator handles nonce verification before this hook fires
			$submitted_data = isset( $_POST ) ? $_POST : [];

			$api_data = [];

			foreach ( $mapping as $lablel => $field_key ) {
				if ( isset( $submitted_data[ $field_key ] ) ) {
					$field_value = $submitted_data[ $field_key ];

					// Handle array values (checkboxes, multi-select, etc.)
					if ( is_array( $field_value ) ) {
						$field_value = implode( ', ', array_map( 'sanitize_text_field', $field_value ) );
					} else {
						$field_value = sanitize_text_field( $field_value );
					}
					$api_data[ $lablel ] = sanitize_text_field( $field_value );
				}

			}

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happilee_Forms_Connect::log_message( 'No mapped field data found for Forminator form ID ' . $form_id, 'warning' );
				return;
			}

			$this->wphfc_send_to_api( $api_data );
		}

	}
}
