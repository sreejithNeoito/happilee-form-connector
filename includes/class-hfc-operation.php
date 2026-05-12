<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Happfoco_Operation')) {

	class Happfoco_Operation
	{

		private $table_name;
		private $form_configurations = array();
		private $processed_form_submissions = array();

		/**
		 * Constructor — sets up table name and registers the init hook.
		 */
		public function __construct()
		{
			global $wpdb;
			$this->table_name = $wpdb->prefix . 'happfoco_forms_data';

			// Initialize dynamic hooks
			add_action('init', array($this, 'happfoco_dynamic_hooks'));
		}

		/*
		 * Dynamic Hook Selection
		 * This function fetches active hooks from the database and attaches them
		 */
		public function happfoco_dynamic_hooks()
		{
			global $wpdb;

			$cache_key = 'happfoco_active_configurations';
			$cache_group = 'happfoco';

			$configurations = wp_cache_get($cache_key, $cache_group);

			if (false === $configurations) {
				$table_name = $this->table_name;

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name derived from $wpdb->prefix, results are cached.
				$configurations = $wpdb->get_results(
					"SELECT id, form_id, active_hook, form_type, connected_fields FROM {$table_name} WHERE is_enabled = 1",
					ARRAY_A
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				wp_cache_set($cache_key, $configurations, $cache_group, 300);
			}

			if (empty($configurations)) {
				return;
			}

			// Store configurations for later reference
			foreach ($configurations as $config) {
				$key = $config['form_type'] . '_' . $config['form_id'];
				$this->form_configurations[$key] = $config;
			}

			// Track which hooks we've already added to avoid duplicate hook registrations
			$added_hooks = array();

			foreach ($configurations as $config) {
				$hook_key = $config['form_type'] . '_' . $config['active_hook'];

				// Skip if we've already added this hook for this form type
				if (isset($added_hooks[$hook_key])) {
					continue;
				}

				switch ($config['form_type']) {

					case 'cf7':
						add_action(
							$config['active_hook'],
							array($this, 'happfoco_handle_cf7'),
							10,
							1
						);
						$added_hooks[$hook_key] = true;
						break;

					case 'wpforms':
						if ($config['active_hook'] === 'wpforms_process_before') {
							add_action(
								$config['active_hook'],
								array($this, 'happfoco_handle_wpforms_2_params'),
								10,
								2
							);
						} elseif ($config['active_hook'] === 'wpforms_process_after') {
							add_action(
								$config['active_hook'],
								array($this, 'happfoco_handle_wpforms_3_params'),
								10,
								3
							);
						} else {
							add_action(
								$config['active_hook'],
								array($this, 'happfoco_handle_wpforms'),
								10,
								4
							);
						}
						$added_hooks[$hook_key] = true;
						break;

					case 'ninja_forms':
						add_action(
							$config['active_hook'],
							array($this, 'happfoco_handle_ninja_forms'),
							10,
							1
						);
						$added_hooks[$hook_key] = true;
						break;

					case 'forminator':
						add_action(
							$config['active_hook'],
							array($this, 'happfoco_handle_forminator'),
							10,
							1
						);
						$added_hooks[$hook_key] = true;
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
		private function get_form_configuration($form_id, $form_type, $current_hook)
		{
			global $wpdb;

			// Check if configuration exists in memory first
			$key = $form_type . '_' . $form_id . '_' . $current_hook;

			if (isset($this->form_configurations[$key])) {
				return $this->form_configurations[$key];
			}

			$cache_key = 'form_config_' . md5($key);
			$cache_group = 'my_plugin_forms';

			$config = wp_cache_get($cache_key, $cache_group);

			if (false === $config) {
				$table = $this->table_name;
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

				wp_cache_set($cache_key, $config, $cache_group, 300);
			}
			// Store in memory cache for this request
			$this->form_configurations[$key] = $config;

			return $config;
		}

		/**
		 * Fetch saved template settings for a specific form.
		 * Returns template_id and param_mappings from happfoco_template_data.
		 *
		 * @param int|string $form_id   The form ID.
		 * @param string     $form_type The form type (cf7, wpforms, etc).
		 * @return array|null
		 */
		private function get_template_configuration($form_id, $form_type)
		{
			global $wpdb;

			$cache_key = 'happfoco_template_config_' . md5($form_id . '_' . $form_type);
			$cache_group = 'happfoco';

			$config = wp_cache_get($cache_key, $cache_group);

			if (false === $config) {
				$table = $wpdb->prefix . 'happfoco_template_data';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$config = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT template_id, param_mappings FROM {$table} WHERE form_id = %s AND form_type = %s LIMIT 1",
						(string) $form_id,
						$form_type
					),
					ARRAY_A
				);

				wp_cache_set($cache_key, $config, $cache_group, 300);
			}

			return $config;
		}


		/**
		 * Resolve country code from visitor IP
		 */
		private function resolve_country_code_from_ip()
		{
			$visitor_ip = '';

			// Handle proxies / load balancers
			$forwarded_for = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])) : '';
			$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

			if (!empty($forwarded_for)) {
				$ips = explode(',', $forwarded_for);
				$visitor_ip = sanitize_text_field(trim($ips[0]));
			} elseif (!empty($remote_addr)) {
				$visitor_ip = $remote_addr;
			}

			// Fallback for localhost / dev environment
			if (empty($visitor_ip) || in_array($visitor_ip, array('127.0.0.1', '::1'), true)) {
				return '+91';
			}

			$response = wp_remote_get(
				esc_url_raw('https://ipapi.co/' . rawurlencode($visitor_ip) . '/country_calling_code/'),
				array('timeout' => 5)
			);

			if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
				$code = trim(wp_remote_retrieve_body($response));
				if (!empty($code) && str_starts_with($code, '+')) {
					return sanitize_text_field($code);
				}
			}

			Happfoco_Main::log_message('Failed to detect country code from IP: ' . $visitor_ip, 'warning');
			return '+1'; // last resort fallback
		}

		/*
		 * Handle API Communication
		 *
		 * Sends form submission data to the createContact endpoint
		 * using the x-api-key header (decrypted from stored settings).
		 */
		private function happfoco_send_to_api($api_data)
		{

			$api_instance = Happfoco_Api::get_instance();

			// Use the dedicated createContact endpoint
			$api_endpoint = Happfoco_Api::API_ENDPOINT_CREATE_CONTACT;

			// Retrieve and decrypt the stored API key
			$api_key = $api_instance->get_api_key();

			if (empty($api_endpoint)) {
				Happfoco_Main::log_message('API endpoint is not configured', 'error');
				return false;
			}

			if (empty($api_key)) {
				Happfoco_Main::log_message('API key is not configured', 'error');
				return false;
			}

			// if missing or set to "country-code", detect from visitor IP
			if (empty($api_data['country_code']) || $api_data['country_code'] === 'country-code') {
				$api_data['country_code'] = $this->resolve_country_code_from_ip();
				// Happfoco_Main::log_message('Auto-detected country code: ' . $api_data['country_code'], 'info');
			}

			$response = wp_remote_post(esc_url_raw($api_endpoint), array(
				'headers' => array(
					'x-api-key' => $api_key,
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode($api_data),
			));

			// Handle response
			if (is_wp_error($response)) {
				Happfoco_Main::log_message('API request error: ' . $response->get_error_message(), 'error');
				return false;
			}

			$response_code = wp_remote_retrieve_response_code($response);
			$response_body = wp_remote_retrieve_body($response);

			if (200 !== $response_code) {
				Happfoco_Main::log_message(
					sprintf('API error - Status %d: %s', $response_code, $response_body),
					'error'
				);
				return false;
			}

			return true;
		}


		/**
		 * Send a WhatsApp template message via the sendMessage API.
		 *
		 * Builds the payload expected by /sendMessage:
		 * - candidate_details.phone_number from api_data
		 * - template_message_id from saved template settings
		 * - template_params.body.value mapped from form submission values
		 *
		 * @param array  $api_data         Mapped field values from form submission.
		 *                                 Must contain 'phone_number'.
		 * @param string $form_id          The form ID.
		 * @param string $form_type        The form type.
		 * @return void
		 */
		private function happfoco_send_template_message($api_data, $form_id, $form_type, $raw_data = array())
		{
			$template_config = $this->get_template_configuration($form_id, $form_type);

			if (empty($template_config) || empty($template_config['template_id'])) {
				return;
			}

			$template_id = $template_config['template_id'];
			$decoded_mappings = json_decode($template_config['param_mappings'], true);

			$body_value = array();
			if (!empty($decoded_mappings) && is_array($decoded_mappings)) {
				foreach ($decoded_mappings as $template_param => $form_field_key) {
					if (!empty($raw_data) && isset($raw_data[$form_field_key])) {
						$value = $raw_data[$form_field_key];
						$body_value[$template_param] = is_array($value)
							? implode(', ', array_map('sanitize_text_field', $value))
							: sanitize_text_field($value);
					} elseif (isset($api_data[$form_field_key])) {
						$body_value[$template_param] = $api_data[$form_field_key];
					} else {
						$body_value[$template_param] = '';
					}
				}
			}

			$phone_number = isset($api_data['phone_number']) ? $api_data['phone_number'] : '';
			if (empty($phone_number)) {
				Happfoco_Main::log_message('Phone number missing — cannot send template message', 'error');
				return;
			}

			$country_code = isset($api_data['country_code']) ? $api_data['country_code'] : '';
			if (empty($country_code) || 'country-code' === $country_code) {
				$country_code = $this->resolve_country_code_from_ip();
			}

			$full_phone = ltrim($country_code, '+') . ltrim($phone_number, '0');

			$payload = array(
				'candidate_details' => array('phone_number' => $full_phone),
				'template_message_id' => $template_id,
				'template_params' => array(
					array('name' => 'body', 'value' => $body_value),
				),
			);

			$api_instance = Happfoco_Api::get_instance();
			$api_key = $api_instance->get_api_key();

			if (empty($api_key)) {
				Happfoco_Main::log_message('API key not configured for sendMessage', 'error');
				return;
			}

			$response = wp_remote_post(
				esc_url_raw(Happfoco_Api::API_ENDPOINT_SAVE_TEMPLATES),
				array(
					'headers' => array(
						'x-api-key' => $api_key,
						'Content-Type' => 'application/json',
					),
					'body' => wp_json_encode($payload),
				)
			);

			if (is_wp_error($response)) {
				Happfoco_Main::log_message('sendMessage API error: ' . $response->get_error_message(), 'error');
				return;
			}

			$code = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);

			if (200 !== $code) {
				Happfoco_Main::log_message(
					sprintf('sendMessage failed — Status %d: %s', $code, $body),
					'error'
				);
				return;
			}

			$decoded_response = json_decode($body, true);
			if (!empty($decoded_response['error'])) {
				Happfoco_Main::log_message(
					'sendMessage API error: ' . ($decoded_response['message'] ?? $body),
					'error'
				);
				return;
			}
		}

		/*
		 * Handle Contact Form 7 Submission
		 */
		public function happfoco_handle_cf7($contact_form)
		{
			static $processed_submissions = array();

			$submission = WPCF7_Submission::get_instance();

			if (!$submission) {
				return;
			}

			// Prevent duplicate processing if CF7 fires the hook multiple times (e.g., for Mail and Mail 2)
			$hash = spl_object_hash($submission);
			if (isset($processed_submissions[$hash])) {
				return;
			}
			$processed_submissions[$hash] = true;

			$form_id = $contact_form->id();
			$current_hook = current_action();
			$config = $this->get_form_configuration($form_id, 'cf7', $current_hook);

			if (empty($config)) {
				Happfoco_Main::log_message('No configuration found for CF7 form ID ' . $form_id, 'error');
				return;
			}

			$connected_fields = $config['connected_fields'];
			$mapping = json_decode($connected_fields, true);

			if (empty($mapping)) {
				Happfoco_Main::log_message('No configuration found for CF7 form ID ' . $form_id, 'error');
				return;
			}

			$posted_data = $submission->get_posted_data();

			$api_data = array();

			foreach ($mapping as $label => $field_key) {

				if (isset($posted_data[$field_key])) {
					$field_value = $posted_data[$field_key];

					// Handle CF7 fields like radio / checkbox (arrays)
					if (is_array($field_value)) {
						$field_value = implode(', ', $field_value);
					}
					$api_data[$label] = sanitize_text_field($field_value);
				}
			}

			if (empty($api_data) || empty($api_data['phone_number'])) {
				Happfoco_Main::log_message('No mapped field data found for CF7 form ID ' . $form_id, 'error');
				return;
			}

			$this->happfoco_send_to_api($api_data);

			// Pass $posted_data as raw_data so template param lookup uses CF7 field names.
			$this->happfoco_send_template_message($api_data, $form_id, 'cf7', $posted_data);

		}

		/*
		 * Handle WPForms Submission
		 */

		public function happfoco_handle_wpforms($fields, $entry, $form_data, $entry_id)
		{
			$form_id = isset($form_data['id']) ? $form_data['id'] : 0;
			$current_hook = current_action();

			$config = $this->get_form_configuration($form_id, 'wpforms', $current_hook);

			if (empty($config)) {
				Happfoco_Main::log_message('No configuration found for WPForms form ID ' . $form_id, 'error');
				return;
			}

			$connected_fields = isset($config['connected_fields']) ? $config['connected_fields'] : '';
			$mapping = json_decode($connected_fields, true);

			if (empty($mapping)) {
				Happfoco_Main::log_message('No field mappings for WPForms form ID ' . $form_id, 'error');
				return;
			}

			$api_data = array();
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

			foreach ($mapping as $happilee_field => $wpforms_value) {

				if (!isset($fields[$wpforms_value])) {
					continue;
				}
				$field = $fields[$wpforms_value];

				if (isset($field['type']) && in_array($field['type'], $ignore_types, true)) {
					continue;
				}

				if (isset($field['name']) && $field['name'] === 'Name' && $happilee_field === 'First Name') {
					$first = isset($field['first']) ? sanitize_text_field($field['first']) : '';
					$middle = isset($field['middle']) ? sanitize_text_field($field['middle']) : '';
					$api_data[$happilee_field] = trim($first . ' ' . $middle);
				} elseif (isset($field['name']) && $field['name'] === 'Name' && $happilee_field === 'Last Name') {
					$api_data[$happilee_field] = isset($field['last']) ? sanitize_text_field($field['last']) : '';
				} else {
					$field_value = isset($field['value']) ? $field['value'] : '';

					if (empty($field_value) && $field_value !== '0') {
						continue;
					}

					if (is_array($field_value)) {
						$field_value = implode(', ', array_map('sanitize_text_field', $field_value));
					} else {
						$field_value = sanitize_text_field($field_value);
					}

					$api_data[$happilee_field] = $field_value;
				}
			}

			$raw_data = array();
			foreach ($fields as $field_id => $field) {
				$val = isset($field['value']) ? $field['value'] : '';
				if (empty($val) && (isset($field['first']) || isset($field['last']))) {
					$first = isset($field['first']) ? $field['first'] : '';
					$last = isset($field['last']) ? $field['last'] : '';
					$val = trim($first . ' ' . $last);
				}
				$raw_data[$field_id] = $val;
			}

			if (empty($api_data) || empty($api_data['phone_number'])) {
				Happfoco_Main::log_message('No mapped field data found for WPForms form ID ' . $form_id, 'error');
				return;
			}

			$this->happfoco_send_to_api($api_data);
			$this->happfoco_send_template_message($api_data, $form_id, 'wpforms', $raw_data);
		}

		/**
		 * Handle a WPForms submission for hooks that pass two parameters.
		 *
		 * @param array $entry     The submitted entry data.
		 * @param array $form_data The form configuration data.
		 * @return void
		 */
		public function happfoco_handle_wpforms_2_params($entry, $form_data)
		{
			$form_id = isset($form_data['id']) ? $form_data['id'] : 0;
			$current_hook = current_action();

			$config = $this->get_form_configuration($form_id, 'wpforms', $current_hook);

			if (empty($config)) {
				Happfoco_Main::log_message('No configuration found for WPForms form ID ' . $form_id, 'error');
				return;
			}

			$connected_fields = isset($config['connected_fields']) ? $config['connected_fields'] : '';
			$mapping = json_decode($connected_fields, true);

			if (empty($mapping)) {
				Happfoco_Main::log_message('No field mappings for WPForms form ID ' . $form_id, 'error');
				return;
			}

			// Get fields from form_data instead
			$fields = isset($form_data['fields']) ? $form_data['fields'] : array();

			if (empty($fields)) {
				Happfoco_Main::log_message('No fields found for WPForms form ID ' . $form_id, 'error');
				return;
			}

			$api_data = array();
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

			foreach ($mapping as $happilee_field => $wpforms_field_id) {

				if (!isset($fields[$wpforms_field_id])) {
					continue;
				}

				$field = $fields[$wpforms_field_id];

				if (isset($field['type']) && in_array($field['type'], $ignore_types, true)) {
					continue;
				}

				$field_value = isset($entry['fields'][$wpforms_field_id]) ? $entry['fields'][$wpforms_field_id] : '';

				if (is_array($field_value) && 'First Name' === $happilee_field) {
					$first = isset($field_value['first']) ? sanitize_text_field($field_value['first']) : '';
					$middle = isset($field_value['middle']) ? sanitize_text_field($field_value['middle']) : '';
					$field_value = trim($first . ' ' . $middle);
				}

				if (is_array($field_value) && 'Last Name' === $happilee_field) {
					$last = isset($field_value['last']) ? sanitize_text_field($field_value['last']) : '';
					$field_value = $last;
				}

				if (empty($field_value) && $field_value !== '0') {
					continue;
				}

				if (is_array($field_value)) {
					$field_value = implode(', ', array_map('sanitize_text_field', $field_value));
				} else {
					$field_value = sanitize_text_field($field_value);
				}

				$api_data[$happilee_field] = $field_value;
			}

			$raw_data = array();
			$entry_fields = isset($entry['fields']) ? $entry['fields'] : array();
			foreach ($entry_fields as $field_id => $field_val) {
				if (is_array($field_val) && (isset($field_val['first']) || isset($field_val['last']))) {
					$first = isset($field_val['first']) ? $field_val['first'] : '';
					$last = isset($field_val['last']) ? $field_val['last'] : '';
					$raw_data[$field_id] = trim($first . ' ' . $last);
				} else {
					$raw_data[$field_id] = $field_val;
				}
			}

			if (empty($api_data) || empty($api_data['phone_number'])) {
				Happfoco_Main::log_message('No mapped field data found for WPForms form ID ' . $form_id, 'warning');
				return;
			}

			$this->happfoco_send_to_api($api_data);
			$this->happfoco_send_template_message($api_data, $form_id, 'wpforms', $raw_data);
		}

		/**
		 * Handle a WPForms submission for hooks that pass three parameters.
		 *
		 * @param array $fields    Processed field data.
		 * @param array $entry     The submitted entry data.
		 * @param array $form_data The form configuration data.
		 * @return void
		 */
		public function happfoco_handle_wpforms_3_params($fields, $entry, $form_data)
		{
			$form_id = isset($form_data['id']) ? $form_data['id'] : 0;
			$current_hook = current_action();

			$config = $this->get_form_configuration($form_id, 'wpforms', $current_hook);

			if (empty($config)) {
				Happfoco_Main::log_message('No configuration found for WPForms form ID ' . $form_id, 'error');
				return;
			}

			$connected_fields = isset($config['connected_fields']) ? $config['connected_fields'] : '';
			$mapping = json_decode($connected_fields, true);

			if (empty($mapping)) {
				Happfoco_Main::log_message('No field mappings for WPForms form ID ' . $form_id, 'error');
				return;
			}

			$api_data = array();
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

			foreach ($mapping as $happilee_field => $wpforms_field_id) {

				if (!isset($fields[$wpforms_field_id])) {
					continue;
				}

				$field = $fields[$wpforms_field_id];

				if (isset($field['type']) && in_array($field['type'], $ignore_types, true)) {
					continue;
				}

				if (isset($field['name']) && $field['name'] === 'Name' && $happilee_field === 'First Name') {
					$first = isset($field['first']) ? sanitize_text_field($field['first']) : '';
					$middle = isset($field['middle']) ? sanitize_text_field($field['middle']) : '';
					$api_data[$happilee_field] = trim($first . ' ' . $middle);
					continue;
				} elseif (isset($field['name']) && $field['name'] === 'Name' && $happilee_field === 'Last Name') {
					$api_data[$happilee_field] = isset($field['last']) ? sanitize_text_field($field['last']) : '';
					continue;
				}

				$field_value = isset($field['value']) ? $field['value'] : '';

				if (empty($field_value) && $field_value !== '0') {
					continue;
				}
				if (is_array($field_value)) {
					$field_value = implode(', ', array_map('sanitize_text_field', $field_value));
				} else {
					$field_value = sanitize_text_field($field_value);
				}

				$api_data[$happilee_field] = $field_value;
			}

			$raw_data = array();
			foreach ($fields as $field_id => $field) {
				$val = isset($field['value']) ? $field['value'] : '';
				if (empty($val) && (isset($field['first']) || isset($field['last']))) {
					$first = isset($field['first']) ? $field['first'] : '';
					$last = isset($field['last']) ? $field['last'] : '';
					$val = trim($first . ' ' . $last);
				}
				$raw_data[$field_id] = $val;
			}

			if (empty($api_data) || empty($api_data['phone_number'])) {
				Happfoco_Main::log_message('No mapped field data found for WPForms form ID ' . $form_id, 'warning');
				return;
			}

			$this->happfoco_send_to_api($api_data);
			$this->happfoco_send_template_message($api_data, $form_id, 'wpforms', $raw_data);
		}

		/*
		 * Handle Ninja Forms Submission
		 */

		public function happfoco_handle_ninja_forms($form_data)
		{
			if (isset($form_data['id'])) {
				$form_id = $form_data['id'];
			} elseif (isset($form_data['form_id'])) {
				$form_id = $form_data['form_id'];
			} else {
				Happfoco_Main::log_message('Invalid form ID for Ninja Forms', 'error');
				return;
			}

			$current_hook = current_action();

			$config = $this->get_form_configuration($form_id, 'ninja_forms', $current_hook);

			if (empty($config)) {
				Happfoco_Main::log_message('No configuration found for Ninja Forms form ID ' . $form_id, 'error');
				return;
			}

			$connected_fields = isset($config['connected_fields']) ? $config['connected_fields'] : '';
			$mapping = json_decode($connected_fields, true);

			if (empty($mapping)) {
				Happfoco_Main::log_message('No field mappings for Ninja Forms form ID ' . $form_id, 'error');
				return;
			}

			$submitted_fields = isset($form_data['fields']) ? $form_data['fields'] : array();

			if (empty($submitted_fields)) {
				Happfoco_Main::log_message('No submitted fields for Ninja Forms form ID ' . $form_id, 'error');
				return;
			}
			$form_title = '';

			// Get form title: Method 1
			if (isset($form_data['settings']['title']) && !empty($form_data['settings']['title'])) {
				$form_title = $form_data['settings']['title'];
			}

			// Method 2:
			if (empty($form_title) && class_exists('Ninja_Forms')) {
				try {
					$form = Ninja_Forms()->form($form_id)->get();
					if ($form && method_exists($form, 'get_setting')) {
						$title = $form->get_setting('title');
						if (!empty($title)) {
							$form_title = $title;
						}
					}
				} catch (Exception $e) {
					Happfoco_Main::log_message('Error getting Ninja Forms title: ' . $e->getMessage(), 'error');
				}
			}

			// Method 3:
			if (empty($form_title) && isset($config['form_name']) && !empty($config['form_name'])) {
				$form_title = $config['form_name'];
			}

			// Fallback
			if (empty($form_title)) {
				$form_title = 'Ninja Form #' . $form_id;
			}

			$api_data = array();
			foreach ($mapping as $happilee_field => $ninja_field_key) {

				foreach ($submitted_fields as $field) {

					if (isset($field['key']) && $field['key'] === $ninja_field_key) {
						$field_value = isset($field['value']) ? $field['value'] : '';

						if (empty($field_value) && $field_value !== '0') {
							break;
						}
						if (is_array($field_value)) {
							$field_value = implode(', ', array_map('sanitize_text_field', $field_value));
						} else {
							$field_value = sanitize_text_field($field_value);
						}

						$api_data[$happilee_field] = $field_value;
						break;
					}
				}
			}

			$raw_data = array();
			foreach ($submitted_fields as $field) {
				if (isset($field['key'])) {
					$raw_data[$field['key']] = isset($field['value']) ? $field['value'] : '';
				}
			}

			if (empty($api_data) || empty($api_data['phone_number'])) {
				Happfoco_Main::log_message('No mapped field data found for Ninja Forms form ID ' . $form_id, 'warning');
				return;
			}

			$this->happfoco_send_to_api($api_data);
			$this->happfoco_send_template_message($api_data, $form_id, 'ninja_forms', $raw_data);
		}

		/*
		 * Handle Forminator Submission
		 */
		public function happfoco_handle_forminator($form_id, $response = null)
		{

			$current_hook = current_action();

			if (is_object($form_id)) {
				$entry = $form_id;
				$form_id = isset($entry->entry_id) ? $entry->form_id : 0;
			} elseif (is_array($form_id)) {
				$form_id = isset($form_id['form_id']) ? $form_id['form_id'] : 0;
			}

			if (empty($form_id)) {
				Happfoco_Main::log_message('Invalid form ID for Forminator', 'error');
				return;
			}

			$config = $this->get_form_configuration($form_id, 'forminator', $current_hook);

			if (empty($config)) {
				Happfoco_Main::log_message('No configuration found for Forminator form ID ' . $form_id, 'error');
				return;
			}

			$connected_fields = isset($config['connected_fields']) ? $config['connected_fields'] : '';
			$mapping = json_decode($connected_fields, true);

			if (empty($mapping)) {
				Happfoco_Main::log_message('No field mappings for Forminator form ID ' . $form_id, 'error');
				return;
			}

			$form_model = Forminator_Form_Model::model()->load((int) $form_id);

			if (is_wp_error($form_model) || empty($form_model)) {
				Happfoco_Main::log_message('Failed to load Forminator form model for ID ' . $form_id, 'error');
				return;
			}

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by Forminator before this hook fires.
			if (empty($_POST)) {
				return;
			}
			$submitted_data = wp_unslash($_POST);
			// phpcs:enable WordPress.Security.NonceVerification.Missing  ← rule back on from here

			$api_data = array();

			foreach ($mapping as $label => $field_key) {
				if (isset($submitted_data[$field_key])) {
					$field_value = $submitted_data[$field_key];

					// Handle array values (checkboxes, multi-select, etc.)
					if (is_array($field_value)) {
						$field_value = implode(', ', array_map('sanitize_text_field', $field_value));
					} else {
						$field_value = sanitize_text_field($field_value);
					}
					$api_data[$label] = $field_value;
				}

			}
			if (empty($api_data) || empty($api_data['phone_number'])) {
				Happfoco_Main::log_message('No mapped field data found for Forminator form ID ' . $form_id, 'warning');
				return;
			}

			$this->happfoco_send_to_api($api_data);
			$this->happfoco_send_template_message($api_data, $form_id, 'forminator', $submitted_data);

		}

	}
}
