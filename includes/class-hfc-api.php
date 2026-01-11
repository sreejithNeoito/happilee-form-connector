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

		const API_ENDPOINT = 'https://webhook.site/ff3aabd0-cf77-4043-a2b4-9075ad225557';

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			global $wpdb;
			$this->table_name = $wpdb->prefix . 'hfc_forms_data';
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Encrypt API key
		 */
		private function encrypt( $data ) {

			if ( empty( $data ) ) {
				return '';
			}

			if ( ! function_exists( 'openssl_encrypt' ) ) {
				error_log( 'Happilee Forms Connect: OpenSSL not available, storing without encryption' );
				return base64_encode( $data );
			}

			$method    = 'AES-256-CBC';
			$iv_length = openssl_cipher_iv_length( $method );
			$iv        = openssl_random_pseudo_bytes( $iv_length );

			$encrypted = openssl_encrypt( $data, $method, $this->encryption_key, 0, $iv );

			if ( false === $encrypted ) {
				error_log( 'Happilee Forms Connect: Encryption failed' );
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
				error_log( 'Happilee Forms Connect: Invalid IV length during decryption' );
				return '';
			}

			$decrypted = openssl_decrypt( $encrypted, $method, $this->encryption_key, 0, $iv );

			if ( false === $decrypted ) {
				error_log( 'Happilee Forms Connect: Decryption failed' );
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
					'message' => __( 'API Key is required', 'happilee-forms-connect' )
				), 400 );
			}

			// Rate limiting
			$transient_key = 'wphfc_api_verify_' . get_current_user_id();
			if ( get_transient( $transient_key ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Please wait before verifying again', 'happilee-forms-connect' )
				), 429 );
			}

			$api_endpoint = $this->get_api_endpoint();

			$response = wp_remote_get( $api_endpoint . '/auth/check', [
				'headers'     => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'Happilee-Forms-Connect/' . HAPPILEE_FORMS_VERSION . '; ' . get_bloginfo( 'url' ),
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
						__( 'API request error: %s', 'happilee-forms-connect' ),
						esc_html( $response->get_error_message() )
					)
				), 500 );
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Invalid API key', 'happilee-forms-connect' )
				), 401 );
			}

			$encrypted_key = $this->encrypt( $api_key );

			if ( empty( $encrypted_key ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Failed to encrypt API key', 'happilee-forms-connect' )
				), 500 );
			}

			update_option( 'wphfc_api_key', $encrypted_key, false );

			return new WP_REST_Response( array(
				'success' => true,
				'message' => __( 'Settings saved successfully', 'happilee-forms-connect' )
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

			if ( empty( $form_plugins ) ) {
				return new WP_REST_Response(
					[ 'message' => __( 'No supported form plugins found', 'happilee-forms-connect' ) ],
					404
				);
			}

			return new WP_REST_Response(
				[
					'plugins' => $form_plugins,
					'message' => __( 'Forms fetched successfully', 'happilee-forms-connect' )
				],
				200
			);
		}

		public function wphfc_save_form_settings( WP_REST_Request $request ) {
			global $wpdb;

			$form_id    = sanitize_text_field( $request->get_param( 'form_id' ) );
			$form_name  = sanitize_text_field( $request->get_param( 'form_name' ) );
			$form_type  = sanitize_text_field( $request->get_param( 'form_type' ) );
			$activeHook = sanitize_text_field( $request->get_param( 'active_hook' ) );
			$is_enabled = intval( $request->get_param( 'is_enabled' ) );

			if ( empty( $form_id ) || empty( $form_type ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Form ID and Form Type are required', 'happilee-forms-connect' )
				), 400 );
			}

			// Check if record exists
			$existing = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE form_id = %s AND form_type = %s",
				$form_id,
				$form_type
			) );

			if ( $existing ) {
				$updated = $wpdb->update(
					$this->table_name,
					array(
						'form_name'   => $form_name,
						'is_enabled'  => $is_enabled,
						'active_hook' => $activeHook,
					),
					array(
						'form_id'   => $form_id,
						'form_type' => $form_type,
					),
					array( '%s', '%d', '%s' ),
					array( '%s', '%s' )
				);

				if ( $updated === false ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'Failed to update form settings', 'happilee-forms-connect' )
					), 500 );
				}

				return new WP_REST_Response( array(
					'success' => true,
					'message' => __( 'Form settings updated successfully', 'happilee-forms-connect' ),
					'action'  => 'updated'
				), 200 );

			} else {
				// Insert new record
				$inserted = $wpdb->insert(
					$this->table_name,
					array(
						'form_id'     => $form_id,
						'form_name'   => $form_name,
						'form_type'   => $form_type,
						'is_enabled'  => $is_enabled,
						'active_hook' => $activeHook,
						'created_at'  => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s', '%d', '%s', '%s' )
				);

				if ( $inserted === false ) {
					return new WP_REST_Response( array(
						'success' => false,
						'message' => __( 'Failed to insert form settings', 'happilee-forms-connect' )
					), 500 );
				}

				return new WP_REST_Response( array(
					'success' => true,
					'message' => __( 'Form settings saved successfully', 'happilee-forms-connect' ),
					'action'  => 'inserted'
				), 200 );
			}
		}

		public function wphfc_fetch_form_data( WP_REST_Request $request ) {
			global $wpdb;

			$form_data = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE 1=%d", 1 ),
				ARRAY_A
			);

			if ( $wpdb->last_error ) {
				error_log( 'Happilee Forms Connect DB Error: ' . $wpdb->last_error );
				return new WP_REST_Response( array(
					'success' => false,
					'message' => __( 'Failed to fetch form data', 'happilee-forms-connect' )
				), 500 );
			}

			return new WP_REST_Response( array(
				'success'   => true,
				'form_data' => $form_data,
				'message'   => __( 'Form data fetched successfully', 'happilee-forms-connect' )
			), 200 );
		}
	}
}
