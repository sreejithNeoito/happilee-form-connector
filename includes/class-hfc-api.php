<?php
/** Registers REST API routes for getting and saving plugin settings. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happfoco_Api' ) ) {

	class Happfoco_Api {

		private static $instance = null;
		private $table_name;
		private $encryption_key;

		// Endpoint used to validate the API key and fetch project details
		const API_ENDPOINT_VALIDATE = 'https://api.happilee.io/api/v1/getProjectDetails';

		// Endpoint used to send form submission data
		const API_ENDPOINT_CREATE_CONTACT = 'https://api.happilee.io/api/v1/createContact';

		// Demo API key for testing without a real Happilee account
		const DEMO_API_KEY = 'demo-test-key-12345';

		// Demo endpoint for validating the demo API key (always returns 200)
		// Replace the token below with your own from https://webhook.site
		const API_ENDPOINT_DEMO_VALIDATE = 'https://webhook.site/03c999e5-2459-4121-b9e2-a913db06e1d7';

		// Demo endpoint for receiving form submissions in testing mode
		// Replace the token below with your own from https://webhook.site
		const API_ENDPOINT_DEMO_CREATE_CONTACT = 'https://webhook.site/03c999e5-2459-4121-b9e2-a913db06e1d7';

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
		 * Constructor — sets up table name, encryption key, and REST hook.
		 */
		private function __construct() {
			global $wpdb;
			$this->table_name     = $wpdb->prefix . 'happfoco_forms_data';
			$this->encryption_key = $this->get_encryption_key();

			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Retrieve (or generate) the encryption key used for the stored API key.
		 *
		 * On first run a new key is generated and persisted. If a legacy key
		 * exists it is migrated to the new format transparently.
		 *
		 * @return string Raw binary encryption key (32 bytes).
		 */
		private function get_encryption_key() {
			$stored_key = get_option( 'happfoco_encryption_key_hash' );

			if ( ! empty( $stored_key ) ) {
				return base64_decode( $stored_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			}

			$existing_api_key = get_option( 'happfoco_api_key', '' );

			if ( ! empty( $existing_api_key ) ) {
				$this->encryption_key = '';

				try {
					$decrypted = $this->decrypt( $existing_api_key );

					if ( ! empty( $decrypted ) ) {
						$new_key              = $this->generate_new_encryption_key();
						$this->encryption_key = $new_key;
						$re_encrypted         = $this->encrypt( $decrypted );
						update_option( 'happfoco_api_key', $re_encrypted, false );
						update_option( 'happfoco_encryption_key_hash', base64_encode( $new_key ), false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

						Happfoco_Main::log_message( 'Successfully migrated to new encryption key', 'info' );

						return $new_key;
					}
				} catch ( Exception $e ) {
					Happfoco_Main::log_message( 'Migration failed - ' . $e->getMessage(), 'error' );
				}
			}

			// No existing data or migration failed — generate new key.
			$new_key = $this->generate_new_encryption_key();
			update_option( 'happfoco_encryption_key_hash', base64_encode( $new_key ), false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

			return $new_key;
		}

		/**
		 * Generate a new 32-byte encryption key derived from WordPress secrets
		 * or a cryptographically secure random source.
		 *
		 * @return string Raw binary key (32 bytes).
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
		 * Encrypt a string using AES-256-CBC.
		 *
		 * @param string $data Plain-text value to encrypt.
		 * @return string Base64-encoded cipher text, or empty string on failure.
		 */
		private function encrypt( $data ) {
			if ( empty( $data ) ) {
				return '';
			}

			if ( ! function_exists( 'openssl_encrypt' ) ) {
				Happfoco_Main::log_message( 'OpenSSL not available, storing without encryption', 'warning' );
				return base64_encode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}

			$method    = 'AES-256-CBC';
			$iv_length = openssl_cipher_iv_length( $method );
			$iv        = openssl_random_pseudo_bytes( $iv_length );

			$encrypted = openssl_encrypt( $data, $method, $this->encryption_key, 0, $iv );

			if ( false === $encrypted ) {
				Happfoco_Main::log_message( 'Encryption failed', 'error' );
				return '';
			}

			return base64_encode( $iv . '::' . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		/**
		 * Decrypt a string previously encrypted by encrypt().
		 *
		 * @param string $data Base64-encoded cipher text.
		 * @return string Plain-text value, or empty string on failure.
		 * @throws Exception When the encryption key is missing during decryption.
		 */
		private function decrypt( $data ) {
			if ( empty( $data ) ) {
				return '';
			}

			$decoded = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

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
				Happfoco_Main::log_message( 'Invalid IV length during decryption', 'error' );
				return '';
			}

			if ( empty( $this->encryption_key ) ) {
				throw new Exception( 'Encryption key is missing' );
			}

			$decrypted = openssl_decrypt( $encrypted, $method, $this->encryption_key, 0, $iv );

			if ( false === $decrypted ) {
				Happfoco_Main::log_message( 'Decryption failed', 'error' );
				return '';
			}

			return $decrypted;
		}

		/**
		 * Check whether the plugin is running in demo/test mode.
		 *
		 * Demo mode is active when the stored API key matches DEMO_API_KEY.
		 *
		 * @param string|null $api_key Optional plain-text API key to test. Uses stored key when null.
		 * @return bool True when in demo mode.
		 */
		public function is_demo_mode( $api_key = null ) {
			if ( null === $api_key ) {
				$api_key = $this->get_api_key();
			}
			return ( self::DEMO_API_KEY === $api_key );
		}

		/**
		 * Get the API endpoint used for validating the key / fetching project details.
		 *
		 * In demo mode (API key = 'demo-test-key-12345'), returns the public webhook.site
		 * demo endpoint which always responds HTTP 200. This lets plugin reviewers and
		 * new users verify every feature without a real Happilee account.
		 *
		 * To use your own webhook.site URL:
		 *   add_filter( 'happfoco_api_demo_validate_endpoint', fn() => 'https://webhook.site/your-token' );
		 *
		 * @param string|null $api_key Optional plain-text API key. Uses stored key when null.
		 * @return string Endpoint URL.
		 */
		public function get_validate_endpoint( $api_key = null ) {
			if ( $this->is_demo_mode( $api_key ) ) {
				return apply_filters( 'happfoco_api_demo_validate_endpoint', self::API_ENDPOINT_DEMO_VALIDATE );
			}
			return apply_filters( 'happfoco_api_validate_endpoint', self::API_ENDPOINT_VALIDATE );
		}

		/**
		 * Get the API endpoint used for sending form submission data (createContact).
		 *
		 * In demo mode, returns the webhook.site demo endpoint so form submissions can be
		 * inspected in real time at https://webhook.site without a Happilee account.
		 *
		 * To use your own webhook.site URL:
		 *   add_filter( 'happfoco_api_demo_create_contact_endpoint', fn() => 'https://webhook.site/your-token' );
		 *
		 * @return string Endpoint URL.
		 */
		public function get_create_contact_endpoint() {
			if ( $this->is_demo_mode() ) {
				return apply_filters( 'happfoco_api_demo_create_contact_endpoint', self::API_ENDPOINT_DEMO_CREATE_CONTACT );
			}
			return apply_filters( 'happfoco_api_create_contact_endpoint', self::API_ENDPOINT_CREATE_CONTACT );
		}

		/**
		 * Get the decrypted API key from storage.
		 *
		 * @return string Plain-text API key, or empty string if not set.
		 */
		public function get_api_key() {
			$encrypted_key = get_option( 'happfoco_api_key', '' );
			return ! empty( $encrypted_key ) ? $this->decrypt( $encrypted_key ) : '';
		}

		/**
		 * Register all REST API routes for this plugin.
		 *
		 * @return void
		 */
		public function register_routes() {

			register_rest_route(
				'happfoco/v1',
				'/save-api-config',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'happfoco_save_api_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'apiKey' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $value ) {
								return is_string( $value ) && '' !== trim( $value );
							},
						),
					),
				)
			);

			register_rest_route(
				'happfoco/v1',
				'/save-form-settings',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'happfoco_save_form_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'form_id'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $value ) {
								return is_string( $value ) && '' !== trim( $value );
							},
						),
						'form_type'   => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $value ) {
								return in_array( $value, array( 'cf7', 'wpforms', 'ninja_forms', 'forminator' ), true );
							},
						),
						'form_name'   => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'active_hook' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'is_enabled'  => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $value ) {
								return in_array( (int) $value, array( 0, 1 ), true );
							},
						),
					),
				)
			);

			register_rest_route(
				'happfoco/v1',
				'/fetch-form-fields',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'happfoco_get_form_fields' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'form_id'   => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $value ) {
								return is_string( $value ) && '' !== trim( $value );
							},
						),
						'form_type' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $value ) {
								return in_array( $value, array( 'cf7', 'wpforms', 'ninja_forms', 'forminator' ), true );
							},
						),
					),
				)
			);

			register_rest_route(
				'happfoco/v1',
				'/get-api-config',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'happfoco_get_api_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				)
			);

			register_rest_route(
				'happfoco/v1',
				'/fetch-forms',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'happfoco_get_forms' ),
					'permission_callback' => array( $this, 'check_permission' ),
				)
			);

			register_rest_route(
				'happfoco/v1',
				'/fetch-form-data',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'happfoco_fetch_form_data' ),
					'permission_callback' => array( $this, 'check_permission' ),
				)
			);
		}

		/**
		 * Permission Callback.
		 *
		 * Uses the 'manage_options' capability, which is the standard WordPress
		 * capability for admin-level access. Roles (e.g. 'administrator') are not
		 * valid arguments for current_user_can() and should never be used here.
		 *
		 * @return bool True if the current user has the manage_options capability.
		 */
		public function check_permission() {
			return current_user_can( 'manage_options' );
		}

		/**
		 * Save API Settings.
		 *
		 * Validates the provided API key against the getProjectDetails endpoint,
		 * then encrypts and persists it on success.
		 *
		 * @param WP_REST_Request $request Full request object.
		 * @return WP_REST_Response Response containing success status and message.
		 */
		public function happfoco_save_api_settings( WP_REST_Request $request ) {

			$api_key = $request->get_param( 'apiKey' );

			if ( empty( $api_key ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'API Key is required', 'happilee-forms-connector' ),
					),
					400
				);
			}

			// Rate limiting.
			$transient_key = 'happfoco_api_verify_' . get_current_user_id();
			if ( get_transient( $transient_key ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Please wait before verifying again', 'happilee-forms-connector' ),
					),
					429
				);
			}

			// Validate the API key by fetching project details.
			// Passing $api_key here lets demo mode be detected before the key is encrypted.
			$validate_endpoint = $this->get_validate_endpoint( $api_key );

			$response = wp_remote_get(
				esc_url_raw( $validate_endpoint ),
				array(
					'headers'     => array(
						'x-api-key'    => $api_key,
						'Content-Type' => 'application/json',
						'User-Agent'   => 'Happilee-Forms-Connector/' . HAPPILEE_FORMS_VERSION . '; ' . get_bloginfo( 'url' ),
					),
					'timeout'     => 15,
					'redirection' => 0,
					'sslverify'   => true,
					'httpversion' => '1.1',
				)
			);

			set_transient( $transient_key, true, 10 );

			if ( is_wp_error( $response ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => sprintf(
							/* translators: %s: error message from the remote API request */
							__( 'API request error: %s', 'happilee-forms-connector' ),
							esc_html( $response->get_error_message() )
						),
					),
					500
				);
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid API key', 'happilee-forms-connector' ),
					),
					401
				);
			}

			$encrypted_key = $this->encrypt( $api_key );

			if ( empty( $encrypted_key ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Failed to encrypt API key', 'happilee-forms-connector' ),
					),
					500
				);
			}

			update_option( 'happfoco_api_key', $encrypted_key, false );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Settings saved successfully', 'happilee-forms-connector' ),
				),
				200
			);
		}

		/**
		 * Get API settings with decryption.
		 *
		 * Returns the decrypted API key along with the active endpoint URLs.
		 *
		 * @param WP_REST_Request $request Full request object.
		 * @return WP_REST_Response Response containing the API key and endpoint URLs.
		 */
		public function happfoco_get_api_settings( WP_REST_Request $request ) {
			$decrypted_key = $this->get_api_key();
			return new WP_REST_Response(
				array(
					'success'                  => true,
					'apiValidateEndpoint'      => $this->get_validate_endpoint(),
					'apiCreateContactEndpoint' => $this->get_create_contact_endpoint(),
					'apiKey'                   => $decrypted_key,
				),
				200
			);
		}

		/**
		 * Fetch all forms from every installed and supported form plugin.
		 *
		 * Supports Contact Form 7, Forminator, Ninja Forms, and WPForms.
		 *
		 * @param WP_REST_Request $request Full request object.
		 * @return WP_REST_Response List of form plugins and their forms, or 404 if none found.
		 */
		public function happfoco_get_forms( WP_REST_Request $request ) {

			$form_plugins = array();

			// Contact Form 7.
			if ( class_exists( 'WPCF7' ) ) {
				$cf7_forms = WPCF7_ContactForm::find();
				$cf7_list  = array();

				if ( ! empty( $cf7_forms ) ) {
					foreach ( $cf7_forms as $form ) {
						$cf7_list[] = array(
							'id'   => absint( $form->id() ),
							'name' => sanitize_text_field( $form->title() ),
						);
					}
				}

				$form_plugins[] = array(
					'type'        => 'cf7',
					'displayName' => 'Contact Form 7',
					'forms'       => $cf7_list,
					'count'       => count( $cf7_list ),
					'defaultHook' => 'wpcf7_mail_sent',
				);
			}

			// Forminator.
			if ( class_exists( 'Forminator_API' ) ) {
				$forminator_forms = Forminator_API::get_forms();
				$forminator_list  = array();

				if ( ! empty( $forminator_forms ) ) {
					foreach ( $forminator_forms as $form ) {
						$forminator_list[] = array(
							'id'   => absint( $form->id ),
							'name' => sanitize_text_field( $form->name ),
						);
					}
				}

				$form_plugins[] = array(
					'type'        => 'forminator',
					'displayName' => 'Forminator',
					'forms'       => $forminator_list,
					'count'       => count( $forminator_list ),
				);
			}

			// Ninja Forms.
			if ( class_exists( 'Ninja_Forms' ) ) {
				$ninja_forms = Ninja_Forms()->form()->get_forms();
				$ninja_list  = array();

				if ( ! empty( $ninja_forms ) ) {
					foreach ( $ninja_forms as $form ) {
						$ninja_list[] = array(
							'id'   => absint( $form->get_id() ),
							'name' => sanitize_text_field( $form->get_setting( 'title' ) ),
						);
					}
				}

				$form_plugins[] = array(
					'type'        => 'ninja_forms',
					'displayName' => 'Ninja Forms',
					'forms'       => $ninja_list,
					'count'       => count( $ninja_list ),
				);
			}

			// WPForms.
			if ( function_exists( 'wpforms' ) ) {
				$wpforms_forms = wpforms()->form->get( '', array( 'orderby' => 'title' ) );
				$wpforms_list  = array();

				if ( ! empty( $wpforms_forms ) ) {
					foreach ( $wpforms_forms as $form ) {
						$wpforms_list[] = array(
							'id'   => absint( $form->ID ),
							'name' => sanitize_text_field( $form->post_title ),
						);
					}
				}

				$form_plugins[] = array(
					'type'        => 'wpforms',
					'displayName' => 'WPForms',
					'forms'       => $wpforms_list,
					'count'       => count( $wpforms_list ),
				);
			}

			if ( empty( $form_plugins ) ) {
				return new WP_REST_Response(
					array( 'message' => __( 'No supported form plugins found', 'happilee-forms-connector' ) ),
					404
				);
			}

			return new WP_REST_Response(
				array(
					'plugins' => $form_plugins,
					'message' => __( 'Forms fetched successfully', 'happilee-forms-connector' ),
				),
				200
			);
		}

		/**
		 * Fetch the list of fields for a specific form.
		 *
		 * Supports Contact Form 7, WPForms, Forminator, and Ninja Forms.
		 *
		 * @param WP_REST_Request $request Full request object. Expects 'form_id' and 'form_type'.
		 * @return WP_REST_Response List of form fields or an error response.
		 */
		public function happfoco_get_form_fields( WP_REST_Request $request ) {
			$form_id   = $request->get_param( 'form_id' );
			$form_type = $request->get_param( 'form_type' );

			if ( empty( $form_id ) || empty( $form_type ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Form ID and Form Type are required', 'happilee-forms-connector' ),
					),
					400
				);
			}

			// ----------------- Contact Form 7 -----------------
			if ( 'cf7' === $form_type ) {
				$form_id      = absint( $form_id );
				$contact_form = WPCF7_ContactForm::get_instance( $form_id );

				if ( ! $contact_form ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'Form not found', 'happilee-forms-connector' ),
						),
						404
					);
				}

				$form_tags = $contact_form->scan_form_tags();
				$fields    = array();

				foreach ( $form_tags as $tag ) {
					// Skip tags without names (like submit buttons).
					if ( empty( $tag->name ) ) {
						continue;
					}

					$fields[] = array(
						'name' => sanitize_text_field( $tag->name ),
					);
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'fields'  => $fields,
						'message' => __( 'Form fields fetched successfully', 'happilee-forms-connector' ),
					),
					200
				);
			}

			// ----------------- WPForms -----------------
			if ( 'wpforms' === $form_type ) {
				$form_id = absint( $form_id );
				$wpform  = wpforms()->form->get( $form_id );

				if ( ! $wpform ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'Form not found', 'happilee-forms-connector' ),
						),
						404
					);
				}

				$form_data = json_decode( $wpform->post_content, true );

				if ( empty( $form_data['fields'] ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'No fields found in this form', 'happilee-forms-connector' ),
						),
						404
					);
				}

				$fields     = array();
				$skip_types = array( 'pagebreak', 'divider', 'html', 'captcha' );

				foreach ( $form_data['fields'] as $field ) {
					if ( in_array( $field['type'], $skip_types, true ) ) {
						continue;
					}

					$fields[] = array(
						'id'    => sanitize_text_field( $field['id'] ),
						'name'  => sanitize_text_field( $field['id'] ),
						'label' => sanitize_text_field( $field['label'] ),
						'type'  => sanitize_text_field( $field['type'] ),
					);
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'fields'  => $fields,
						'message' => __( 'Form fields fetched successfully', 'happilee-forms-connector' ),
					),
					200
				);
			}

			// ----------------- Forminator -----------------
			if ( 'forminator' === $form_type ) {
				$form_id    = absint( $form_id );
				$forminator = Forminator_API::get_form( $form_id );

				if ( ! $forminator ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'Form not found', 'happilee-forms-connector' ),
						),
						404
					);
				}

				$form_fields = $forminator->get_fields();

				if ( empty( $form_fields ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'No fields found in this form', 'happilee-forms-connector' ),
						),
						404
					);
				}

				$fields     = array();
				$skip_types = array( 'section', 'page', 'html', 'captcha', 'stripe', 'paypal', 'upload' );

				foreach ( $form_fields as $field ) {
					$field_type  = $field->__get( 'type' );
					$element_id  = $field->slug;
					$field_label = $field->__get( 'field_label' );

					if ( in_array( $field_type, $skip_types, true ) || empty( $element_id ) ) {
						continue;
					}

					// Handle Name field — split into first and last name.
					if ( 'name' === $field_type ) {
						$fields[] = array(
							'id'    => sanitize_text_field( $element_id ),
							'name'  => sanitize_text_field( $element_id ),
							'label' => sanitize_text_field( $field_label ),
							'type'  => 'name',
						);
					} else {
						$fields[] = array(
							'id'    => sanitize_text_field( $element_id ),
							'name'  => sanitize_text_field( $element_id ),
							'label' => sanitize_text_field( $field_label ),
							'type'  => sanitize_text_field( $field_type ),
						);
					}
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'fields'  => $fields,
						'message' => __( 'Form fields fetched successfully', 'happilee-forms-connector' ),
					),
					200
				);
			}

			// ----------------- Ninja Forms -----------------
			if ( 'ninja_forms' === $form_type ) {
				$form_id    = absint( $form_id );
				$ninja_form = Ninja_Forms()->form( $form_id )->get_fields();

				if ( ! $ninja_form ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'Form not found', 'happilee-forms-connector' ),
						),
						404
					);
				}

				$fields     = array();
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
					'starrating',
				);

				foreach ( $ninja_form as $field ) {
					$field_type  = $field->get_setting( 'type' );
					$field_key   = $field->get_setting( 'key' );
					$field_label = $field->get_setting( 'label' );

					if ( in_array( $field_type, $skip_types, true ) || empty( $field_label ) ) {
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
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'No usable fields found in this form', 'happilee-forms-connector' ),
						),
						404
					);
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'fields'  => $fields,
						'message' => __( 'Form fields fetched successfully', 'happilee-forms-connector' ),
					),
					200
				);
			}

			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Unsupported form type', 'happilee-forms-connector' ),
				),
				400
			);
		}

		/**
		 * Save or update form settings in the database.
		 *
		 * Inserts a new record if none exists for the given form_id + form_type,
		 * otherwise updates the existing record.
		 *
		 * @param WP_REST_Request $request Full request object.
		 * @return WP_REST_Response Success or error response.
		 */
		public function happfoco_save_form_settings( WP_REST_Request $request ) {

			global $wpdb;

			$form_id     = sanitize_text_field( $request->get_param( 'form_id' ) );
			$form_name   = sanitize_text_field( $request->get_param( 'form_name' ) );
			$form_type   = sanitize_text_field( $request->get_param( 'form_type' ) );
			$active_hook = sanitize_text_field( $request->get_param( 'active_hook' ) );
			$is_enabled  = intval( $request->get_param( 'is_enabled' ) );
			$form_field  = $request->get_param( 'form_field' );

			if ( empty( $form_id ) || empty( $form_type ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Form ID and Form Type are required', 'happilee-forms-connector' ),
					),
					400
				);
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
			if ( null !== $form_field ) {
				if ( is_array( $form_field ) && ! empty( $form_field ) ) {
					$sanitized_form_field = array();
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
				$update_data   = array(
					'form_name'   => $form_name,
					'is_enabled'  => $is_enabled,
					'active_hook' => $active_hook,
				);
				$update_format = array( '%s', '%d', '%s' );

				if ( null !== $form_field_json ) {
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

				if ( false === $updated ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'Failed to update form settings', 'happilee-forms-connector' ),
						),
						500
					);
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Form settings updated successfully', 'happilee-forms-connector' ),
						'action'  => 'updated',
					),
					200
				);

			} else {
				if ( null === $form_field_json ) {
					$form_field_json = '{}';
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$inserted = $wpdb->insert(
					$this->table_name,
					array(
						'form_id'          => $form_id,
						'form_name'        => $form_name,
						'form_type'        => $form_type,
						'is_enabled'       => $is_enabled,
						'active_hook'      => $active_hook,
						'connected_fields' => $form_field_json,
						'created_at'       => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
				);

				if ( false === $inserted ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'Failed to insert form settings', 'happilee-forms-connector' ),
						),
						500
					);
				}

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Form settings saved successfully', 'happilee-forms-connector' ),
						'action'  => 'inserted',
					),
					200
				);
			}
		}

		/**
		 * Fetch all stored form configuration records from the database.
		 *
		 * @param WP_REST_Request $request Full request object.
		 * @return WP_REST_Response All form data rows or an error response.
		 */
		public function happfoco_fetch_form_data( WP_REST_Request $request ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$form_data = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table_name}",
				ARRAY_A
			);

			if ( $wpdb->last_error ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Failed to fetch form data', 'happilee-forms-connector' ),
					),
					500
				);
			}

			return new WP_REST_Response(
				array(
					'success'   => true,
					'form_data' => $form_data,
					'message'   => __( 'Form data fetched successfully', 'happilee-forms-connector' ),
				),
				200
			);
		}
	}
}
