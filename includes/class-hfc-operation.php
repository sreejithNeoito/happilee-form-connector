<?php
/**
 * Form submission handling.
 *
 * @package Happilee_Forms_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happfoco_Operation' ) ) {

	/**
	 * Listens to supported form plugins and forwards submissions to Happilee.
	 */
	class Happfoco_Operation {

		/**
		 * Form settings table name.
		 *
		 * @var string
		 */
		private $table_name;

		/**
		 * Per-request cache of form configurations.
		 *
		 * @var array
		 */
		private $form_configurations = array();

		/**
		 * Per-request cache of the visitor's country calling code.
		 *
		 * @var string|null
		 */
		private $country_code = null;

		/**
		 * Set the table name and register the init hook.
		 */
		public function __construct() {
			global $wpdb;
			$this->table_name = $wpdb->prefix . 'happfoco_forms_data';

			add_action( 'init', array( $this, 'happfoco_dynamic_hooks' ) );
		}

		/**
		 * Attach a submission handler to each hook used by an enabled form.
		 *
		 * @return void
		 */
		public function happfoco_dynamic_hooks() {
			global $wpdb;

			$cache_key   = Happfoco_Main::cache_key( 'active_configurations' );
			$cache_group = Happfoco_Main::CACHE_GROUP;

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

			// Several forms can share a hook; register each form type + hook pair once.
			$added_hooks = array();

			foreach ( $configurations as $config ) {
				$hook_key = $config['form_type'] . '_' . $config['active_hook'];

				if ( isset( $added_hooks[ $hook_key ] ) ) {
					continue;
				}

				switch ( $config['form_type'] ) {

					case 'cf7':
						add_action(
							$config['active_hook'],
							array( $this, 'happfoco_handle_cf7' ),
							10,
							1
						);
						$added_hooks[ $hook_key ] = true;
						break;

					case 'wpforms':
						if ( 'wpforms_process_before' === $config['active_hook'] ) {
							add_action(
								$config['active_hook'],
								array( $this, 'happfoco_handle_wpforms_2_params' ),
								10,
								2
							);
						} elseif ( 'wpforms_process_after' === $config['active_hook'] ) {
							add_action(
								$config['active_hook'],
								array( $this, 'happfoco_handle_wpforms_3_params' ),
								10,
								3
							);
						} else {
							add_action(
								$config['active_hook'],
								array( $this, 'happfoco_handle_wpforms' ),
								10,
								3
							);
						}
						$added_hooks[ $hook_key ] = true;
						break;

					case 'ninja_forms':
						add_action(
							$config['active_hook'],
							array( $this, 'happfoco_handle_ninja_forms' ),
							10,
							1
						);
						$added_hooks[ $hook_key ] = true;
						break;

					case 'forminator':
						add_action(
							$config['active_hook'],
							array( $this, 'happfoco_handle_forminator' ),
							10,
							1
						);
						$added_hooks[ $hook_key ] = true;
						break;
				}
			}
		}

		/**
		 * Retrieve the stored configuration for a specific form.
		 *
		 * Checks in-memory cache first, then object cache, then database.
		 *
		 * @param int|string $form_id      The form ID.
		 * @param string     $form_type    The form plugin type (e.g. 'cf7').
		 * @param string     $current_hook The current WordPress action hook.
		 * @return array|null Configuration row from DB, or null if not found.
		 */
		private function get_form_configuration( $form_id, $form_type, $current_hook ) {
			global $wpdb;

			$key = $form_type . '_' . $form_id . '_' . $current_hook;

			if ( isset( $this->form_configurations[ $key ] ) ) {
				return $this->form_configurations[ $key ];
			}

			$cache_key   = Happfoco_Main::cache_key( 'form_config_' . md5( $key ) );
			$cache_group = Happfoco_Main::CACHE_GROUP;

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

			$this->form_configurations[ $key ] = $config;

			return $config;
		}

		/**
		 * Fetch the saved template settings for a form.
		 *
		 * @param int|string $form_id   The form ID.
		 * @param string     $form_type The form type (cf7, wpforms, etc).
		 * @return array|null Row with template_id and param_mappings, or null if none is saved.
		 */
		private function get_template_configuration( $form_id, $form_type ) {
			global $wpdb;

			$cache_key   = Happfoco_Main::cache_key( 'template_config_' . md5( $form_id . '_' . $form_type ) );
			$cache_group = Happfoco_Main::CACHE_GROUP;

			$config = wp_cache_get( $cache_key, $cache_group );

			if ( false === $config ) {
				$table  = esc_sql( $wpdb->prefix . 'happfoco_template_data' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$config = $wpdb->get_row(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						"SELECT template_id, param_mappings FROM {$table} WHERE form_id = %s AND form_type = %s LIMIT 1",
						(string) $form_id,
						$form_type
					),
					ARRAY_A
				);

				wp_cache_set( $cache_key, $config, $cache_group, 300 );
			}

			return $config;
		}

		/**
		 * Resolve the visitor's country calling code from their IP address.
		 *
		 * The result is cached for the request so a submission triggers at
		 * most one lookup.
		 *
		 * @return string Country calling code, e.g. "+91".
		 */
		private function resolve_country_code_from_ip() {
			if ( null === $this->country_code ) {
				$this->country_code = $this->lookup_country_code();
			}

			return $this->country_code;
		}

		/**
		 * Look up the visitor's country calling code via ipapi.co.
		 *
		 * @return string Country calling code, e.g. "+91".
		 */
		private function lookup_country_code() {
			$visitor_ip = '';

			// Behind a proxy or load balancer the client IP is the first forwarded address.
			$forwarded_for = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
			$remote_addr   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

			if ( ! empty( $forwarded_for ) ) {
				$ips        = explode( ',', $forwarded_for );
				$visitor_ip = sanitize_text_field( trim( $ips[0] ) );
			} elseif ( ! empty( $remote_addr ) ) {
				$visitor_ip = $remote_addr;
			}

			// Local requests have no public IP to look up.
			if ( empty( $visitor_ip ) || in_array( $visitor_ip, array( '127.0.0.1', '::1' ), true ) ) {
				return '+91';
			}

			$response = wp_remote_get(
				esc_url_raw( 'https://ipapi.co/' . rawurlencode( $visitor_ip ) . '/country_calling_code/' ),
				array( 'timeout' => 5 )
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$code = trim( wp_remote_retrieve_body( $response ) );
				if ( ! empty( $code ) && '+' === substr( $code, 0, 1 ) ) {
					return sanitize_text_field( $code );
				}
			}

			Happfoco_Main::log_message( 'Failed to detect country code from IP: ' . $visitor_ip, 'warning' );
			return '+1';
		}

		/**
		 * Apply the "tags" mapping to $api_data.
		 *
		 * Unlike the other mapped fields, "tags" is a static comma-separated
		 * value entered by the admin (not a reference to a submitted form
		 * field), and the API expects it as an array of strings.
		 *
		 * Backward compatibility: up to 1.0.8, "tags" was mapped to a form
		 * field. If the stored value matches a submitted field key, it is
		 * treated as that legacy mapping and the visitor's value is used.
		 *
		 * @param array $mapping        Decoded connected_fields mapping.
		 * @param array $api_data       Data to send to the API, passed by reference.
		 * @param array $submitted_data Submitted form data keyed by field key/ID.
		 */
		private function apply_tags_mapping( $mapping, &$api_data, $submitted_data = array() ) {
			if ( empty( $mapping['tags'] ) || ! is_scalar( $mapping['tags'] ) ) {
				return;
			}

			$tags_value = (string) $mapping['tags'];

			// Legacy field mapping — use the visitor's value (already set by the mapping loop, if not empty).
			if ( is_array( $submitted_data ) && array_key_exists( $tags_value, $submitted_data ) ) {
				$tags_value = isset( $api_data['tags'] ) ? (string) $api_data['tags'] : '';
			}

			unset( $api_data['tags'] );

			$tags = array_filter( array_map( 'trim', explode( ',', $tags_value ) ) );

			if ( ! empty( $tags ) ) {
				$api_data['tags'] = array_values( array_map( 'sanitize_text_field', $tags ) );
			}
		}

		/**
		 * Send the mapped submission data to the Happilee createContact endpoint.
		 *
		 * The form ID, form name and a lead source of "{form name}-Happilee web"
		 * are added so the contact can be traced back to the submitting form.
		 *
		 * @param array $api_data Mapped field values from the form submission.
		 * @param array $config   Stored configuration row of the submitted form.
		 * @return bool True on success, false on failure.
		 */
		private function happfoco_send_to_api( $api_data, $config ) {
			$api_key = Happfoco_Api::get_instance()->get_api_key();

			if ( empty( $api_key ) ) {
				Happfoco_Main::log_message( 'API key is not configured', 'error' );
				return false;
			}

			if ( empty( $api_data['country_code'] ) || 'country-code' === $api_data['country_code'] ) {
				$api_data['country_code'] = $this->resolve_country_code_from_ip();
			}

			$form_id   = isset( $config['form_id'] ) ? (string) $config['form_id'] : '';
			$form_name = ! empty( $config['form_name'] ) ? sanitize_text_field( $config['form_name'] ) : $form_id;

			$api_data['form_id']     = $form_id;
			$api_data['form_name']   = $form_name;
			$api_data['lead_source'] = $form_name . '-Happilee web';

			$response = wp_remote_post(
				esc_url_raw( Happfoco_Api::API_ENDPOINT_CREATE_CONTACT ),
				array(
					'headers'     => array(
						'x-api-key'    => $api_key,
						'Content-Type' => 'application/json',
					),
					'body'        => wp_json_encode( $api_data ),
					'redirection' => 0,
					'sslverify'   => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				Happfoco_Main::log_message( 'API request error: ' . $response->get_error_message(), 'error' );
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( 200 !== $response_code ) {
				Happfoco_Main::log_message(
					sprintf( 'API error - Status %d: %s', $response_code, $response_body ),
					'error'
				);
				return false;
			}

			return true;
		}

		/**
		 * Send the form's WhatsApp template message via the sendMessage API.
		 *
		 * Template parameters are filled from the submitted values using the
		 * saved parameter mappings.
		 *
		 * @param array  $api_data  Mapped field values; must contain 'phone_number'.
		 * @param string $form_id   The form ID.
		 * @param string $form_type The form type.
		 * @param array  $raw_data  Submitted values keyed by the form plugin's field key.
		 * @return void
		 */
		private function happfoco_send_template_message( $api_data, $form_id, $form_type, $raw_data = array() ) {
			$template_config = $this->get_template_configuration( $form_id, $form_type );

			if ( empty( $template_config ) || empty( $template_config['template_id'] ) ) {
				return;
			}

			$template_id      = $template_config['template_id'];
			$decoded_mappings = json_decode( $template_config['param_mappings'], true );

			$body_value = array();
			if ( ! empty( $decoded_mappings ) && is_array( $decoded_mappings ) ) {
				foreach ( $decoded_mappings as $template_param => $form_field_key ) {
					if ( ! empty( $raw_data ) && isset( $raw_data[ $form_field_key ] ) ) {
						$value                         = $raw_data[ $form_field_key ];
						$body_value[ $template_param ] = is_array( $value )
							? implode( ', ', array_map( 'sanitize_text_field', $value ) )
							: sanitize_text_field( $value );
					} elseif ( isset( $api_data[ $form_field_key ] ) ) {
						$body_value[ $template_param ] = $api_data[ $form_field_key ];
					} else {
						$body_value[ $template_param ] = '';
					}
				}
			}

			$phone_number = isset( $api_data['phone_number'] ) ? $api_data['phone_number'] : '';
			if ( empty( $phone_number ) ) {
				Happfoco_Main::log_message( 'Phone number missing — cannot send template message', 'error' );
				return;
			}

			$country_code = isset( $api_data['country_code'] ) ? $api_data['country_code'] : '';
			if ( empty( $country_code ) || 'country-code' === $country_code ) {
				$country_code = $this->resolve_country_code_from_ip();
			}

			$full_phone = ltrim( $country_code, '+' ) . ltrim( $phone_number, '0' );

			$payload = array(
				'candidate_details'   => array( 'phone_number' => $full_phone ),
				'template_message_id' => $template_id,
				'template_params'     => array(
					array(
						'name'  => 'body',
						'value' => $body_value,
					),
				),
			);

			$api_instance = Happfoco_Api::get_instance();
			$api_key      = $api_instance->get_api_key();

			if ( empty( $api_key ) ) {
				Happfoco_Main::log_message( 'API key not configured for sendMessage', 'error' );
				return;
			}

			$response = wp_remote_post(
				esc_url_raw( Happfoco_Api::API_ENDPOINT_SEND_MESSAGE ),
				array(
					'headers'     => array(
						'x-api-key'    => $api_key,
						'Content-Type' => 'application/json',
					),
					'body'        => wp_json_encode( $payload ),
					'redirection' => 0,
					'sslverify'   => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				Happfoco_Main::log_message( 'sendMessage API error: ' . $response->get_error_message(), 'error' );
				return;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( 200 !== $code ) {
				Happfoco_Main::log_message(
					sprintf( 'sendMessage failed — Status %d: %s', $code, $body ),
					'error'
				);
				return;
			}

			$decoded_response = json_decode( $body, true );
			if ( ! empty( $decoded_response['error'] ) ) {
				Happfoco_Main::log_message(
					'sendMessage API error: ' . ( $decoded_response['message'] ?? $body ),
					'error'
				);
				return;
			}
		}

		/**
		 * Handle a Contact Form 7 submission.
		 *
		 * @param WPCF7_ContactForm $contact_form The submitted form.
		 * @return void
		 */
		public function happfoco_handle_cf7( $contact_form ) {
			static $processed_submissions = array();

			$submission = WPCF7_Submission::get_instance();

			if ( ! $submission ) {
				return;
			}

			// wpcf7_submit also fires for spam, invalid and aborted submissions; skip those.
			if ( ! in_array( $submission->get_status(), array( 'init', 'mail_sent', 'mail_failed' ), true ) ) {
				return;
			}

			// CF7 can fire the same hook more than once per submission (e.g. Mail and Mail 2).
			$hash = spl_object_hash( $submission );
			if ( isset( $processed_submissions[ $hash ] ) ) {
				return;
			}
			$processed_submissions[ $hash ] = true;

			$form_id      = $contact_form->id();
			$current_hook = current_action();
			$config       = $this->get_form_configuration( $form_id, 'cf7', $current_hook );

			if ( empty( $config ) ) {
				Happfoco_Main::log_message( 'No configuration found for CF7 form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = $config['connected_fields'];
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happfoco_Main::log_message( 'No configuration found for CF7 form ID ' . $form_id, 'error' );
				return;
			}

			$posted_data = $submission->get_posted_data();

			$api_data = array();

			foreach ( $mapping as $label => $field_key ) {

				if ( isset( $posted_data[ $field_key ] ) ) {
					$field_value = $posted_data[ $field_key ];

					if ( is_array( $field_value ) ) {
						$field_value = implode( ', ', $field_value );
					}
					$api_data[ $label ] = sanitize_text_field( $field_value );
				}
			}

			$this->apply_tags_mapping( $mapping, $api_data, $posted_data );

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happfoco_Main::log_message( 'No mapped field data found for CF7 form ID ' . $form_id, 'error' );
				return;
			}

			$this->happfoco_send_to_api( $api_data, $config );
			$this->happfoco_send_template_message( $api_data, $form_id, 'cf7', $posted_data );
		}

		/**
		 * Handle a WPForms submission for hooks that pass fields, entry and form data.
		 *
		 * @param array $fields    Processed field data.
		 * @param array $entry     The submitted entry data.
		 * @param array $form_data The form configuration data.
		 * @return void
		 */
		public function happfoco_handle_wpforms( $fields, $entry, $form_data ) {
			$form_id      = isset( $form_data['id'] ) ? $form_data['id'] : 0;
			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'wpforms', $current_hook );

			if ( empty( $config ) ) {
				Happfoco_Main::log_message( 'No configuration found for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happfoco_Main::log_message( 'No field mappings for WPForms form ID ' . $form_id, 'error' );
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

			foreach ( $mapping as $happilee_field => $wpforms_value ) {

				if ( ! isset( $fields[ $wpforms_value ] ) ) {
					continue;
				}
				$field = $fields[ $wpforms_value ];

				if ( isset( $field['type'] ) && in_array( $field['type'], $ignore_types, true ) ) {
					continue;
				}

				if ( isset( $field['type'] ) && 'name' === $field['type'] && 'first_name' === $happilee_field ) {
					$first                       = isset( $field['first'] ) ? sanitize_text_field( $field['first'] ) : '';
					$middle                      = isset( $field['middle'] ) ? sanitize_text_field( $field['middle'] ) : '';
					$api_data[ $happilee_field ] = trim( $first . ' ' . $middle );

					// A "simple" format Name field has a single input, so use the full value.
					if ( '' === $api_data[ $happilee_field ] && isset( $field['value'] ) ) {
						$api_data[ $happilee_field ] = sanitize_text_field( $field['value'] );
					}
				} elseif ( isset( $field['type'] ) && 'name' === $field['type'] && 'last_name' === $happilee_field ) {
					$api_data[ $happilee_field ] = isset( $field['last'] ) ? sanitize_text_field( $field['last'] ) : '';
				} else {
					$field_value = isset( $field['value'] ) ? $field['value'] : '';

					if ( empty( $field_value ) && '0' !== $field_value ) {
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

			$raw_data = array();
			foreach ( $fields as $field_id => $field ) {
				$val = isset( $field['value'] ) ? $field['value'] : '';
				if ( empty( $val ) && ( isset( $field['first'] ) || isset( $field['last'] ) ) ) {
					$first = isset( $field['first'] ) ? $field['first'] : '';
					$last  = isset( $field['last'] ) ? $field['last'] : '';
					$val   = trim( $first . ' ' . $last );
				}
				$raw_data[ $field_id ] = $val;
			}

			$this->apply_tags_mapping( $mapping, $api_data, $raw_data );

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happfoco_Main::log_message( 'No mapped field data found for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$this->happfoco_send_to_api( $api_data, $config );
			$this->happfoco_send_template_message( $api_data, $form_id, 'wpforms', $raw_data );
		}

		/**
		 * Handle a WPForms submission for hooks that pass two parameters.
		 *
		 * @param array $entry     The submitted entry data.
		 * @param array $form_data The form configuration data.
		 * @return void
		 */
		public function happfoco_handle_wpforms_2_params( $entry, $form_data ) {
			$form_id      = isset( $form_data['id'] ) ? $form_data['id'] : 0;
			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'wpforms', $current_hook );

			if ( empty( $config ) ) {
				Happfoco_Main::log_message( 'No configuration found for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happfoco_Main::log_message( 'No field mappings for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$fields = isset( $form_data['fields'] ) ? $form_data['fields'] : array();

			if ( empty( $fields ) ) {
				Happfoco_Main::log_message( 'No fields found for WPForms form ID ' . $form_id, 'error' );
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

				if ( is_array( $field_value ) && 'first_name' === $happilee_field ) {
					$first       = isset( $field_value['first'] ) ? sanitize_text_field( $field_value['first'] ) : '';
					$middle      = isset( $field_value['middle'] ) ? sanitize_text_field( $field_value['middle'] ) : '';
					$field_value = trim( $first . ' ' . $middle );
				}

				if ( is_array( $field_value ) && 'last_name' === $happilee_field ) {
					$last        = isset( $field_value['last'] ) ? sanitize_text_field( $field_value['last'] ) : '';
					$field_value = $last;
				}

				if ( empty( $field_value ) && '0' !== $field_value ) {
					continue;
				}

				if ( is_array( $field_value ) ) {
					$field_value = implode( ', ', array_map( 'sanitize_text_field', $field_value ) );
				} else {
					$field_value = sanitize_text_field( $field_value );
				}

				$api_data[ $happilee_field ] = $field_value;
			}

			$raw_data     = array();
			$entry_fields = isset( $entry['fields'] ) ? $entry['fields'] : array();
			foreach ( $entry_fields as $field_id => $field_val ) {
				if ( is_array( $field_val ) && ( isset( $field_val['first'] ) || isset( $field_val['last'] ) ) ) {
					$first                 = isset( $field_val['first'] ) ? $field_val['first'] : '';
					$last                  = isset( $field_val['last'] ) ? $field_val['last'] : '';
					$raw_data[ $field_id ] = trim( $first . ' ' . $last );
				} else {
					$raw_data[ $field_id ] = $field_val;
				}
			}

			$this->apply_tags_mapping( $mapping, $api_data, $raw_data );

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happfoco_Main::log_message( 'No mapped field data found for WPForms form ID ' . $form_id, 'warning' );
				return;
			}

			$this->happfoco_send_to_api( $api_data, $config );
			$this->happfoco_send_template_message( $api_data, $form_id, 'wpforms', $raw_data );
		}

		/**
		 * Handle a WPForms submission for hooks that pass three parameters.
		 *
		 * @param array $fields    Processed field data.
		 * @param array $entry     The submitted entry data.
		 * @param array $form_data The form configuration data.
		 * @return void
		 */
		public function happfoco_handle_wpforms_3_params( $fields, $entry, $form_data ) {
			$form_id      = isset( $form_data['id'] ) ? $form_data['id'] : 0;
			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'wpforms', $current_hook );

			if ( empty( $config ) ) {
				Happfoco_Main::log_message( 'No configuration found for WPForms form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happfoco_Main::log_message( 'No field mappings for WPForms form ID ' . $form_id, 'error' );
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

				if ( isset( $field['type'] ) && 'name' === $field['type'] && 'first_name' === $happilee_field ) {
					$first                       = isset( $field['first'] ) ? sanitize_text_field( $field['first'] ) : '';
					$middle                      = isset( $field['middle'] ) ? sanitize_text_field( $field['middle'] ) : '';
					$api_data[ $happilee_field ] = trim( $first . ' ' . $middle );

					// A "simple" format Name field has a single input, so use the full value.
					if ( '' === $api_data[ $happilee_field ] && isset( $field['value'] ) ) {
						$api_data[ $happilee_field ] = sanitize_text_field( $field['value'] );
					}
					continue;
				} elseif ( isset( $field['type'] ) && 'name' === $field['type'] && 'last_name' === $happilee_field ) {
					$api_data[ $happilee_field ] = isset( $field['last'] ) ? sanitize_text_field( $field['last'] ) : '';
					continue;
				}

				$field_value = isset( $field['value'] ) ? $field['value'] : '';

				if ( empty( $field_value ) && '0' !== $field_value ) {
					continue;
				}
				if ( is_array( $field_value ) ) {
					$field_value = implode( ', ', array_map( 'sanitize_text_field', $field_value ) );
				} else {
					$field_value = sanitize_text_field( $field_value );
				}

				$api_data[ $happilee_field ] = $field_value;
			}

			$raw_data = array();
			foreach ( $fields as $field_id => $field ) {
				$val = isset( $field['value'] ) ? $field['value'] : '';
				if ( empty( $val ) && ( isset( $field['first'] ) || isset( $field['last'] ) ) ) {
					$first = isset( $field['first'] ) ? $field['first'] : '';
					$last  = isset( $field['last'] ) ? $field['last'] : '';
					$val   = trim( $first . ' ' . $last );
				}
				$raw_data[ $field_id ] = $val;
			}

			$this->apply_tags_mapping( $mapping, $api_data, $raw_data );

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happfoco_Main::log_message( 'No mapped field data found for WPForms form ID ' . $form_id, 'warning' );
				return;
			}

			$this->happfoco_send_to_api( $api_data, $config );
			$this->happfoco_send_template_message( $api_data, $form_id, 'wpforms', $raw_data );
		}

		/**
		 * Handle a Ninja Forms submission.
		 *
		 * @param array $form_data Submission data passed by Ninja Forms.
		 * @return void
		 */
		public function happfoco_handle_ninja_forms( $form_data ) {
			if ( isset( $form_data['id'] ) ) {
				$form_id = $form_data['id'];
			} elseif ( isset( $form_data['form_id'] ) ) {
				$form_id = $form_data['form_id'];
			} else {
				Happfoco_Main::log_message( 'Invalid form ID for Ninja Forms', 'error' );
				return;
			}

			$current_hook = current_action();

			$config = $this->get_form_configuration( $form_id, 'ninja_forms', $current_hook );

			if ( empty( $config ) ) {
				Happfoco_Main::log_message( 'No configuration found for Ninja Forms form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happfoco_Main::log_message( 'No field mappings for Ninja Forms form ID ' . $form_id, 'error' );
				return;
			}

			$submitted_fields = isset( $form_data['fields'] ) ? $form_data['fields'] : array();

			if ( empty( $submitted_fields ) ) {
				Happfoco_Main::log_message( 'No submitted fields for Ninja Forms form ID ' . $form_id, 'error' );
				return;
			}
			$api_data = array();
			foreach ( $mapping as $happilee_field => $ninja_field_key ) {

				foreach ( $submitted_fields as $field ) {

					if ( isset( $field['key'] ) && $field['key'] === $ninja_field_key ) {
						$field_value = isset( $field['value'] ) ? $field['value'] : '';

						if ( empty( $field_value ) && '0' !== $field_value ) {
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

			$raw_data = array();
			foreach ( $submitted_fields as $field ) {
				if ( isset( $field['key'] ) ) {
					$raw_data[ $field['key'] ] = isset( $field['value'] ) ? $field['value'] : '';
				}
			}

			$this->apply_tags_mapping( $mapping, $api_data, $raw_data );

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happfoco_Main::log_message( 'No mapped field data found for Ninja Forms form ID ' . $form_id, 'warning' );
				return;
			}

			$this->happfoco_send_to_api( $api_data, $config );
			$this->happfoco_send_template_message( $api_data, $form_id, 'ninja_forms', $raw_data );
		}

		/**
		 * Handle a Forminator submission.
		 *
		 * @param int|array|object $form_id Form ID, or the entry/data passed by the hook.
		 * @return void
		 */
		public function happfoco_handle_forminator( $form_id ) {
			$current_hook = current_action();

			if ( is_object( $form_id ) ) {
				$entry   = $form_id;
				$form_id = isset( $entry->entry_id ) ? $entry->form_id : 0;
			} elseif ( is_array( $form_id ) ) {
				$form_id = isset( $form_id['form_id'] ) ? $form_id['form_id'] : 0;
			}

			if ( empty( $form_id ) ) {
				Happfoco_Main::log_message( 'Invalid form ID for Forminator', 'error' );
				return;
			}

			$config = $this->get_form_configuration( $form_id, 'forminator', $current_hook );

			if ( empty( $config ) ) {
				Happfoco_Main::log_message( 'No configuration found for Forminator form ID ' . $form_id, 'error' );
				return;
			}

			$connected_fields = isset( $config['connected_fields'] ) ? $config['connected_fields'] : '';
			$mapping          = json_decode( $connected_fields, true );

			if ( empty( $mapping ) ) {
				Happfoco_Main::log_message( 'No field mappings for Forminator form ID ' . $form_id, 'error' );
				return;
			}

			$form_model = Forminator_Form_Model::model()->load( (int) $form_id );

			if ( is_wp_error( $form_model ) || empty( $form_model ) ) {
				Happfoco_Main::log_message( 'Failed to load Forminator form model for ID ' . $form_id, 'error' );
				return;
			}

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by Forminator before this hook fires.
			if ( empty( $_POST ) ) {
				return;
			}
			$submitted_data = wp_unslash( $_POST );
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			$api_data = array();

			foreach ( $mapping as $label => $field_key ) {
				if ( isset( $submitted_data[ $field_key ] ) ) {
					$field_value = $submitted_data[ $field_key ];

					if ( is_array( $field_value ) ) {
						$field_value = implode( ', ', array_map( 'sanitize_text_field', $field_value ) );
					} else {
						$field_value = sanitize_text_field( $field_value );
					}
					$api_data[ $label ] = $field_value;
				}
			}

			$this->apply_tags_mapping( $mapping, $api_data, $submitted_data );

			if ( empty( $api_data ) || empty( $api_data['phone_number'] ) ) {
				Happfoco_Main::log_message( 'No mapped field data found for Forminator form ID ' . $form_id, 'warning' );
				return;
			}

			$this->happfoco_send_to_api( $api_data, $config );
			$this->happfoco_send_template_message( $api_data, $form_id, 'forminator', $submitted_data );
		}
	}
}
