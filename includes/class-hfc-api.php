<?php
/** Registers REST API routes for getting and saving plugin settings. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Happilee_HFC_Api' ) ) {

	class Happilee_HFC_Api {
		private $table_name;
		public function __construct() {
			global $wpdb;
			$this->table_name = $wpdb->prefix . 'hfc_forms_data';
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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

			$endpoint = sanitize_text_field( $request['apiEndpoint'] );
			$api_key  = sanitize_text_field( $request['apiKey'] );

			if ( ! $endpoint || ! $api_key ) {
				return new WP_REST_Response( [ 'message' => 'Missing fields' ], 400 );
			}

			$response = wp_remote_get( $endpoint . '/auth/check', [
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
				],
				'timeout' => 15, // Added timeout
			] );

			if ( is_wp_error( $response ) ) {
				return new WP_REST_Response( [
					'message' => 'API request error: ' . $response->get_error_message()
				], 500 );
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code !== 200 ) {
				return new WP_REST_Response( [ 'message' => 'Invalid API key' ], 401 );
			}

			// If success — save the values
			update_option( 'wphfc_api_endpoint', $endpoint );
			update_option( 'wphfc_api_key', $api_key );

			return new WP_REST_Response( [ 'message' => 'Settings saved successfully' ], 200 );

		}

		public function wphfc_get_api_settings( WP_REST_Request $request ) {
			return new WP_REST_Response(
				array(
					'apiEndpoint' => get_option( 'wphfc_api_endpoint', '' ),
					'apiKey'      => get_option( 'wphfc_api_key', '' ),
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
							'id'   => $form->id(),
							'name' => $form->title(),
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
			if ( class_exists( 'Forminator' ) ) {
				$forminator_forms = Forminator_Form_Model::model()->get_all_models();
				$forminator_list  = [];

				if ( ! empty( $forminator_forms ) ) {
					foreach ( $forminator_forms as $form ) {
						$forminator_list[] = [
							'id'   => $form->id,
							'name' => $form->name,
						];
					}
				}

				$form_plugins[] = [
					'type'        => 'forminator',
					'displayName' => 'Forminator Forms',
					'forms'       => $forminator_list,
					'count'       => count( $forminator_list )
				];
			}

			// WPForms
			if ( function_exists( 'wpforms' ) ) {
				$wpforms_forms = wpforms()->form->get( '', [ 'orderby' => 'title' ] );
				$wpforms_list  = [];

				if ( ! empty( $wpforms_forms ) ) {
					foreach ( $wpforms_forms as $form ) {
						$wpforms_list[] = [
							'id'   => $form->ID,
							'name' => $form->post_title,
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

			// Gravity Forms
			if ( class_exists( 'GFForms' ) ) {
				$gf_forms = GFAPI::get_forms();
				$gf_list  = [];

				if ( ! empty( $gf_forms ) ) {
					foreach ( $gf_forms as $form ) {
						$gf_list[] = [
							'id'   => $form['id'],
							'name' => $form['title'],
						];
					}
				}

				$form_plugins[] = [
					'type'        => 'gf',
					'displayName' => 'Gravity Forms',
					'forms'       => $gf_list,
					'count'       => count( $gf_list )
				];
			}

			// Ninja Forms
			if ( class_exists( 'Ninja_Forms' ) ) {
				$ninja_forms = Ninja_Forms()->form()->get_forms();
				$ninja_list  = [];

				if ( ! empty( $ninja_forms ) ) {
					foreach ( $ninja_forms as $form ) {
						$ninja_list[] = [
							'id'   => $form->get_id(),
							'name' => $form->get_setting( 'title' ),
						];
					}
				}

				$form_plugins[] = [
					'type'        => 'ninja',
					'displayName' => 'Ninja Forms',
					'forms'       => $ninja_list,
					'count'       => count( $ninja_list )
				];
			}

			if ( empty( $form_plugins ) ) {
				return new WP_REST_Response(
					[ 'message' => 'No supported form plugins found' ],
					404
				);
			}

			return new WP_REST_Response(
				[
					'plugins' => $form_plugins,
					'message' => 'Forms fetched successfully'
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
					'message' => 'Form ID and Form Type are required'
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
						'message' => 'Failed to update form settings'
					), 500 );
				}

				return new WP_REST_Response( array(
					'success' => true,
					'message' => 'Form settings updated successfully',
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
						'message' => 'Failed to insert form settings'
					), 500 );
				}

				return new WP_REST_Response( array(
					'success' => true,
					'message' => 'Form settings saved successfully',
					'action'  => 'inserted'
				), 200 );
			}
		}

		public function wphfc_fetch_form_data( WP_REST_Request $request ) {
			global $wpdb;

			$form_data = $wpdb->get_results( "SELECT * FROM {$this->table_name}", ARRAY_A );
			return new WP_REST_Response( array(
				'success'   => true,
				'form_data' => $form_data,
				'message'   => 'Form data fetched successfully'
			), 200 );
		}

	}
}
