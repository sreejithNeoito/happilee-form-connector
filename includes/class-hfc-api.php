<?php
/** Registers REST API routes for getting and saving plugin settings. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happilee_HFC_Api' ) ) {

	class Happilee_HFC_Api {
		private static $instance = null;
		private $table_name;
		private $encryption_key;
		const API_ENDPOINT = 'https://devapi.happilee.io/api/v1/createContact';

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			global $wpdb;
			$this->table_name     = $wpdb->prefix . 'hfc_forms_data';
			$this->encryption_key = $this->get_encryption_key();

			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}
		private function get_encryption_key() {
			$stored_key = get_option( 'wphfc_encryption_key_hash' );

			if ( ! empty( $stored_key ) ) {
				return base64_decode( $stored_key );
			}
			$existing_api_key = get_option( 'wphfc_api_key', '' );

			if ( ! empty( $existing_api_key ) ) {
				$this->encryption_key = '';

				try {
					$decrypted = $this->decrypt( $existing_api_key );

					if ( ! empty( $decrypted ) ) {
						$new_key              = $this->generate_new_encryption_key();
						$this->encryption_key = $new_key;
						$re_encrypted         = $this->encrypt( $decrypted );
						update_option( 'wphfc_api_key', $re_encrypted, false );

						update_option( 'wphfc_encryption_key_hash', base64_encode( $new_key ), false );

						Happilee_Forms_Connect::log_message( 'Successfully migrated to new encryption key', 'info' );

						return $new_key;
					}
				} catch ( Exception $e ) {
					Happilee_Forms_Connect::log_message( 'Migration failed - ' . $e->getMessage(), 'error' );
				}
			}

			// No existing data or migration failed - generate new key
			$new_key = $this->generate_new_encryption_key();
			update_option( 'wphfc_encryption_key_hash', base64_encode( $new_key ), false );

			return $new_key;
		}

		/**
		 * Generate a new encryption key
		 */
		private function generate_new_encryption_key() {
			if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
				return hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
			}

			if ( function_exists( 'random_bytes' ) ) {
				return random_bytes( 32 );
			}

			return openssl_random_pseudo_bytes( 32 );
		}

		/**
		 * Encrypt API key
		 */
		private function encrypt( $data ) {

			if ( empty( $data ) ) {
				return '';
			}

			if ( ! function_exists( 'openssl_encrypt' ) ) {
				Happilee_Forms_Connect::log_message( 'OpenSSL not available, storing without encryption', 'warning' );
				return base64_encode( $data );
			}

			$method    = 'AES-256-CBC';
			$iv_length = openssl_cipher_iv_length( $method );
			$iv        = openssl_random_pseudo_bytes( $iv_length );

			$encrypted = openssl_encrypt( $data, $method, $this->encryption_key, 0, $iv );

			if ( false === $encrypted ) {
				Happilee_Forms_Connect::log_message( 'Encryption failed', 'error' );
				return '';
			}

			return base64_encode( $iv . '::' . $encrypted );
		}

		/**
		 * Decrypt API key
		 */
		private function decrypt( $data ) {
			if ( empty( $data ) ) {
				return '';
			}

			$decoded = base64_decode( $data, true );

			if ( false === $decoded ) {
				return '';
			}

			if ( ! function_exists( 'openssl_decrypt' ) ) {
				return $decoded;
			}

			if ( false === strpos( $decoded, '::' ) ) {
				return $decoded;
			}

			$method    = 'AES-256-CBC';
			$iv_length = openssl_cipher_iv_length( $method );

			list( $iv, $encrypted ) = explode( '::', $decoded, 2 );

			if ( strlen( $iv ) !== $iv_length ) {
				Happilee_Forms_Connect::log_message( 'Invalid IV length during decryption', 'error' );
				return '';
			}

			if ( empty( $this->encryption_key ) ) {
				throw new Exception( 'Encryption key is missing' );
			}

			$decrypted = openssl_decrypt( $encrypted, $method, $this->encryption_key, 0, $iv );

			if ( false === $decrypted ) {
				Happilee_Forms_Connect::log_message( 'Decryption failed', 'error' );
				return '';
			}

			return $decrypted;
		}

		/**
		 * Get API endpoint
		 */
		public function get_api_endpoint() {
			return apply_filters( 'wphfc_api_endpoint', self::API_ENDPOINT );
		}

		/**
		 * Get decrypted API key
		 */
		public function get_api_key() {
			$encrypted_key = get_option( 'wphfc_api_key', '' );
			return ! empty( $encrypted_key ) ? $this->decrypt( $encrypted_key ) : '';
		}

		public function register_routes() {

			register_rest_route( 'wphfc/v1', '/save-api-config', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'wphfc_save_api_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
			) );

			register_rest_route( 'wphfc/v1', '/save-form-settings', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'wphfc_save_form_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
			) );

			register_rest_route( 'wphfc/v1', '/fetch-form-fields', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'wphfc_get_form_fields' ),
				'permission_callback' => array( $this, 'check_permission' ),
			) );

			register_rest_route( 'wphfc/v1', '/get-api-config', array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'wphfc_get_api_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
			) );

			register_rest_route( 'wphfc/v1', '/fetch-forms', array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'wphfc_get_forms' ),
				'permission_callback' => array( $this, 'check_permission' ),
			) );

			register_rest_route( 'wphfc/v1', '/fetch-form-data', array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'wphfc_fetch_form_data' ),
				'permission_callback' => array( $this, 'check_permission' ),
			) );

		}

		/**
		 * Permission Callback
		 */
		public function check_permission() {
			return current_user_can( 'administrator' ) || current_user_can( 'manage_woocommerce' );
		}

		/**
		 * Save API Settings
		 */
		public function wphfc_save_api_settings( WP_REST_Request $request ) {

			$api_key = $request->get_param( 'apiKey' );

			if ( empty( $api_key ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'API Key is required', 'happilee-forms-connector' )
				), 400 );
			}

			// Rate limiting
			$transient_key = 'wphfc_api_verify_' . get_current_user_id();
			if ( get_transient( $transient_key ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Please wait before verifying again', 'happilee-forms-connector' )
				), 429 );
			}

			$api_endpoint = $this->get_api_endpoint();

			$response = wp_remote_get( $api_endpoint . '/auth/check', [
				'headers'     => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'Happilee-Forms-Connector/' . HAPPILEE_FORMS_VERSION . '; ' . get_bloginfo( 'url' ),
				],
				'timeout'     => 15,
				'redirection' => 0,
				'sslverify'   => true,
				'httpversion' => '1.1',
			] );

			set_transient( $transient_key, true, 10 );

			if ( is_wp_error( $response ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => sprintf(
						__( 'API request error: %s', 'happilee-forms-connector' ),
						esc_html( $response->get_error_message() )
					)
				), 500 );
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Invalid API key', 'happilee-forms-connector' )
				), 401 );
			}

			$encrypted_key = $this->encrypt( $api_key );

			if ( empty( $encrypted_key ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Failed to encrypt API key', 'happilee-forms-connector' )
				), 500 );
			}

			update_option( 'wphfc_api_key', $encrypted_key, false );

			return new WP_REST_Response( array(
				'success' => true,
				'message' => __( 'Settings saved successfully', 'happilee-forms-connector' )
			), 200 );

		}

		/**
		 * Get API settings with decryption
		 */
		public function wphfc_get_api_settings( WP_REST_Request $request ) {
			$decrypted_key = $this->get_api_key();
			return new WP_REST_Response(
				array(
					'success'     => true,
					'apiEndpoint' => $this->get_api_endpoint(),
					'apiKey'      => $decrypted_key,
				),
				200
			);
		}

		public function wphfc_get_forms( WP_REST_Request $request ) {

			$form_plugins = [];

			// Contact Form 7
			if ( class_exists( 'WPCF7' ) ) {
				$cf7_forms = WPCF7_ContactForm::find();
				$cf7_list  = [];

				if ( ! empty( $cf7_forms ) ) {
					foreach ( $cf7_forms as $form ) {
						$cf7_list[] = [
							'id'   => absint( $form->id() ),
							'name' => sanitize_text_field( $form->title() ),
						];
					}
				}

				$form_plugins[] = [
					'type'        => 'cf7',
					'displayName' => 'Contact Form 7',
					'forms'       => $cf7_list,
					'count'       => count( $cf7_list ),
					'defaultHook' => 'wpcf7_mail_sent',
				];
			}

			// Forminator
			if ( class_exists( 'Forminator_API' ) ) {
				$forminator_forms = Forminator_API::get_forms();

				$forminator_list = [];

				if ( ! empty( $forminator_forms ) ) {
					foreach ( $forminator_forms as $form ) {
						$forminator_list[] = [
							'id'   => absint( $form->id ),
							'name' => sanitize_text_field( $form->name ),
						];
					}
				}

				$form_plugins[] = [
					'type'        => 'forminator',
					'displayName' => 'Forminator',
					'forms'       => $forminator_list,
					'count'       => count( $forminator_list )
				];
			}

			// Ninja Forms
			if ( class_exists( 'Ninja_Forms' ) ) {
				$ninja_forms = Ninja_Forms()->form()->get_forms();
				$ninja_list  = [];

				if ( ! empty( $ninja_forms ) ) {
					foreach ( $ninja_forms as $form ) {
						$ninja_list[] = [
							'id'   => absint( $form->get_id() ),
							'name' => sanitize_text_field( $form->get_setting( 'title' ) ),
						];
					}
				}

				$form_plugins[] = [
					'type'        => 'ninja_forms',
					'displayName' => 'Ninja Forms',
					'forms'       => $ninja_list,
					'count'       => count( $ninja_list )
				];
			}

			// WPForms
			if ( function_exists( 'wpforms' ) ) {
				$wpforms_forms = wpforms()->form->get( '', [ 'orderby' => 'title' ] );
				$wpforms_list  = [];

				if ( ! empty( $wpforms_forms ) ) {
					foreach ( $wpforms_forms as $form ) {
						$wpforms_list[] = [
							'id'   => absint( $form->ID ),
							'name' => sanitize_text_field( $form->post_title ),
						];
					}
				}

				$form_plugins[] = [
					'type'        => 'wpforms',
					'displayName' => 'WPForms',
					'forms'       => $wpforms_list,
					'count'       => count( $wpforms_list )
				];
			}

			if ( empty( $form_plugins ) ) {
				return new WP_REST_Response(
					[ 'message' => __( 'No supported form plugins found', 'happilee-forms-connector' ) ],
					404
				);
			}

			return new WP_REST_Response(
				[
					'plugins' => $form_plugins,
					'message' => __( 'Forms fetched successfully', 'happilee-forms-connector' )
				],
				200
			);
		}

		public function wphfc_get_form_fields( WP_REST_Request $request ) {
			$form_id   = $request->get_param( 'form_id' );
			$form_type = $request->get_param( 'form_type' );

			if ( empty( $form_id ) || empty( $form_type ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Form ID and Form Type are required', 'happilee-forms-connector' )
				), 400 );
			}

			if ( $form_type == 'cf7' ) {
				$form_id      = absint( $form_id );
				$contact_form = WPCF7_ContactForm::get_instance( $form_id );

				if ( ! $contact_form ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'Form not found', 'happilee-forms-connector' )
					), 404 );
				}

				$form_tags = $contact_form->scan_form_tags();
				$fields    = [];

				foreach ( $form_tags as $tag ) {
					// Skip tags without names (like submit buttons)
					if ( empty( $tag->name ) ) {
						continue;
					}

					$fields[] = array(
						'name' => sanitize_text_field( $tag->name ),
					);
				}

				return new WP_REST_Response( array(
					'success' => true,
					'fields'  => $fields,
					'message' => __( 'Form fields fetched successfully', 'happilee-forms-connector' )
				), 200 );
			}

			// ----------------- wpform ----------------
			if ( $form_type == 'wpforms' ) {

				$form_id = absint( $form_id );
				$wpform  = wpforms()->form->get( $form_id );

				if ( ! $wpform ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'Form not found', 'happilee-forms-connector' )
					), 404 );
				}

				$form_data = json_decode( $wpform->post_content, true );

				if ( empty( $form_data['fields'] ) ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'No fields found in this form', 'happilee-forms-connector' )
					), 404 );
				}

				$fields = [];

				foreach ( $form_data['fields'] as $field ) {
					$skip_types = array( 'pagebreak', 'divider', 'html', 'captcha' );

					if ( in_array( $field['type'], $skip_types ) ) {
						continue;
					}

					$fields[] = array(
						'id'    => sanitize_text_field( $field['id'] ),
						'name'  => sanitize_text_field( $field['id'] ),
						'label' => sanitize_text_field( $field['label'] ),
						'type'  => sanitize_text_field( $field['type'] ),
					);
				}

				return new WP_REST_Response( array(
					'success' => true,
					'fields'  => $fields,
					'message' => __( 'Form fields fetched successfully', 'happilee-forms-connector' )
				), 200 );
			}

			// ----------------- Forminator ----------------------

			if ( $form_type == 'forminator' ) {
				$form_id    = absint( $form_id );
				$forminator = Forminator_API::get_form( $form_id );

				if ( ! $forminator ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'Form not found', 'happilee-forms-connector' )
					), 404 );
				}

				// Get form fields
				$form_fields = $forminator->get_fields();
				if ( empty( $form_fields ) ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'No fields found in this form', 'happilee-forms-connector' )
					), 404 );
				}

				$fields = [];

				// Skip these field types
				$skip_types = array( 'section', 'page', 'html', 'captcha', 'stripe', 'paypal', 'upload' );

				foreach ( $form_fields as $field ) {
					$field_type  = $field->__get( 'type' );
					$element_id  = $field->slug;
					$field_label = $field->__get( 'field_label' );

					// Skip unwanted field types or empty IDs
					if ( in_array( $field_type, $skip_types ) || empty( $element_id ) ) {
						continue;
					}

					// Handle Name field - split into first and last name
					if ( $field_type === 'name' ) {
						$fields[] = array(
							'id'    => sanitize_text_field( $element_id ),
							'name'  => sanitize_text_field( $element_id ),
							'label' => sanitize_text_field( $field_label ),
							'type'  => 'name',
						);
					}
					// Regular fields (email, phone, textarea, text, number, select, radio, checkbox, etc.)
					else {
						$fields[] = array(
							'id'    => sanitize_text_field( $element_id ),
							'name'  => sanitize_text_field( $element_id ),
							'label' => sanitize_text_field( $field_label ),
							'type'  => sanitize_text_field( $field_type ),
						);
					}
				}

				return new WP_REST_Response( array(
					'success' => true,
					'fields'  => $fields,
					'message' => __( 'Form fields fetched successfully', 'happilee-forms-connector' )
				), 200 );
			}

			// ----------------- Ninja Forms ----------------------

			if ( $form_type == 'ninja_forms' ) {
				$form_id    = absint( $form_id );
				$ninja_form = Ninja_Forms()->form( $form_id )->get_fields();

				if ( ! $ninja_form ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'Form not found', 'happilee-forms-connector' )
					), 404 );
				}

				$fields = [];

				// Skip field types
				$skip_types = array(
					'submit',
					'hr',
					'html',
					'divider',
					'recaptcha',
					'spam',
					'hidden',
					'creditcard',
					'creditcardcvc',
					'creditcardexpiration',
					'creditcardfullname',
					'creditcardnumber',
					'creditcardzip',
					'product',
					'quantity',
					'shipping',
					'total',
					'password',
					'password_confirm',
					'save',
					'note',
					'starrating' // optional - remove if you want to include ratings
				);

				foreach ( $ninja_form as $field ) {
					$field_type  = $field->get_setting( 'type' );
					$field_key   = $field->get_setting( 'key' );
					$field_label = $field->get_setting( 'label' );

					// Skip unwanted field types or fields without labels
					if ( in_array( $field_type, $skip_types ) || empty( $field_label ) ) {
						continue;
					}

					$fields[] = array(
						'id'    => $field->get_id(),
						'name'  => $field_key,
						'label' => sanitize_text_field( $field_label ),
						'type'  => sanitize_text_field( $field_type ),
					);
				}

				if ( empty( $fields ) ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'No usable fields found in this form', 'happilee-forms-connector' )
					), 404 );
				}

				return new WP_REST_Response( array(
					'success' => true,
					'fields'  => $fields,
					'message' => __( 'Form fields fetched successfully', 'happilee-forms-connector' )
				), 200 );
			}

			return new WP_REST_Response( array(
				'success' => false,
				'message' => __( 'Unsupported form type', 'happilee-forms-connector' )
			), 400 );
		}

		public function wphfc_save_form_settings( WP_REST_Request $request ) {

			global $wpdb;
			$form_id    = sanitize_text_field( $request->get_param( 'form_id' ) );
			$form_name  = sanitize_text_field( $request->get_param( 'form_name' ) );
			$form_type  = sanitize_text_field( $request->get_param( 'form_type' ) );
			$activeHook = sanitize_text_field( $request->get_param( 'active_hook' ) );
			$is_enabled = intval( $request->get_param( 'is_enabled' ) );
			$form_field = $request->get_param( 'form_field' );

			if ( empty( $form_id ) || empty( $form_type ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Form ID and Form Type are required', 'happilee-forms-connector' )
				), 400 );
			}

			$table    = esc_sql( $this->table_name );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$table} WHERE form_id = %s AND form_type = %s",
					$form_id,
					$form_type
				)
			);

			$form_field_json = null;
			if ( $form_field !== null ) {
				if ( is_array( $form_field ) && ! empty( $form_field ) ) {
					$sanitized_form_field = [];
					foreach ( $form_field as $key => $value ) {
						$sanitized_key                          = sanitize_text_field( $key );
						$sanitized_value                        = sanitize_text_field( $value );
						$sanitized_form_field[ $sanitized_key ] = $sanitized_value;
					}
					$form_field_json = wp_json_encode( $sanitized_form_field );
				} else {
					$form_field_json = '{}';
				}
			}

			if ( $existing ) {
				$update_data = array(
					'form_name'   => $form_name,
					'is_enabled'  => $is_enabled,
					'active_hook' => $activeHook,
				);

				$update_format = array( '%s', '%d', '%s' );

				if ( $form_field_json !== null ) {
					$update_data['connected_fields'] = $form_field_json;
					$update_format[]                 = '%s';
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$updated = $wpdb->update(
					$this->table_name,
					$update_data,
					array(
						'form_id'   => $form_id,
						'form_type' => $form_type,
					),
					$update_format,
					array( '%s', '%s' )
				);

				if ( $updated === false ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'Failed to update form settings', 'happilee-forms-connector' )
					), 500 );
				}

				return new WP_REST_Response( array(
					'success' => true,
					'message' => __( 'Form settings updated successfully', 'happilee-forms-connector' ),
					'action'  => 'updated'
				), 200 );

			} else {
				if ( $form_field_json === null ) {
					$form_field_json = '{}';
				}

				// Insert new record
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$inserted = $wpdb->insert(
					$this->table_name,
					array(
						'form_id'          => $form_id,
						'form_name'        => $form_name,
						'form_type'        => $form_type,
						'is_enabled'       => $is_enabled,
						'active_hook'      => $activeHook,
						'connected_fields' => $form_field_json,
						'created_at'       => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
				);

				if ( $inserted === false ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'Failed to insert form settings', 'happilee-forms-connector' )
					), 500 );
				}

				return new WP_REST_Response( array(
					'success' => true,
					'message' => __( 'Form settings saved successfully', 'happilee-forms-connector' ),
					'action'  => 'inserted'
				), 200 );
			}
		}

		public function wphfc_fetch_form_data( WP_REST_Request $request ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$form_data = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table_name}",
				ARRAY_A
			);

			if ( $wpdb->last_error ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Failed to fetch form data', 'happilee-forms-connector' )
				), 500 );
			}

			return new WP_REST_Response( array(
				'success'   => true,
				'form_data' => $form_data,
				'message'   => __( 'Form data fetched successfully', 'happilee-forms-connector' )
			), 200 );
		}
	}
}
