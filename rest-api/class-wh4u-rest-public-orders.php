<?php
/**
 * REST endpoints for public (anonymous) domain registration and transfer requests.
 *
 * Stores orders as a WordPress Custom Post Type (wh4u_public_order) using
 * wp_insert_post() and post meta. Fires wh4u_new_public_order action so
 * notification delivery is handled through WordPress hooks.
 *
 * Security layers: rate limiting, honeypot, input validation/sanitization,
 * nonce (CSRF), and IP hashing for abuse tracking.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WH4U_REST_Public_Orders extends WH4U_REST_Controller {

	/**
	 * Postmeta keys holding PII that must be encrypted at rest.
	 *
	 * @var string[]
	 */
	private static $pii_meta_keys = array(
		'_wh4u_first_name',
		'_wh4u_last_name',
		'_wh4u_email',
		'_wh4u_phone',
		'_wh4u_company',
		'_wh4u_address',
		'_wh4u_city',
		'_wh4u_state',
		'_wh4u_country',
		'_wh4u_zip',
		'_wh4u_eppcode',
	);

	/**
	 * Return the list of postmeta keys that store encrypted PII.
	 *
	 * @return string[]
	 */
	public static function get_pii_meta_keys() {
		return self::$pii_meta_keys;
	}

	/**
	 * Produce an encrypted copy of a meta array for storage.
	 *
	 * Keys listed in self::$pii_meta_keys are passed through
	 * WH4U_Encryption::encrypt(); other keys (domain, order type, period,
	 * ip_hash) are stored plaintext because they are operational metadata,
	 * not registrant PII.
	 *
	 * @param array $meta Plaintext meta array.
	 * @return array Meta array with PII fields encrypted.
	 */
	private static function encrypt_pii_meta( $meta ) {
		$encrypted = $meta;
		foreach ( self::$pii_meta_keys as $key ) {
			if ( isset( $encrypted[ $key ] ) && $encrypted[ $key ] !== '' ) {
				$encrypted[ $key ] = WH4U_Encryption::encrypt( (string) $encrypted[ $key ] );
			}
		}
		return $encrypted;
	}

	/**
	 * Register public order route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/orders/public-register', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_public_order' ),
			'permission_callback' => array( $this, 'check_public_order_permission' ),
			'args'                => $this->get_public_order_args(),
		) );

		register_rest_route( $this->namespace, '/orders/public-transfer', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_public_transfer_order' ),
			'permission_callback' => array( $this, 'check_public_order_permission' ),
			'args'                => $this->get_public_transfer_args(),
		) );

		register_rest_route( $this->namespace, '/orders/public/(?P<id>\d+)/status', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_public_order_status' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'id'     => array(
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			'status' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return in_array( $value, array( 'wh4u-approved', 'wh4u-rejected' ), true );
				},
			),
			),
		) );
	}

	/**
	 * Permission callback: public access with stricter rate limiting.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_public_order_permission( $request ) {
		$user_id = get_current_user_id();

		if ( WH4U_Rate_Limiter::is_limited( 'public_order', $user_id ) ) {
			return new WP_Error(
				'wh4u_rate_limited',
				__( 'Too many submissions. Please try again later.', 'wh4u-domains' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Argument schema for public order creation.
	 *
	 * @return array
	 */
	private function get_public_order_args() {
		return array(
			'domain'    => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value )
						&& strlen( $value ) >= 3
						&& strlen( $value ) <= 253
						&& preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9.-]+\.[a-zA-Z][a-zA-Z0-9-]*[a-zA-Z0-9]$/', $value ) === 1;
				},
			),
			'regperiod' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 10;
				},
			),
			'firstName' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value ) && strlen( trim( $value ) ) >= 1 && strlen( $value ) <= 100;
				},
			),
			'lastName'  => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value ) && strlen( trim( $value ) ) >= 1 && strlen( $value ) <= 100;
				},
			),
			'email'     => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => 'is_email',
			),
			'phone'     => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value ) && preg_match( '/^[+0-9\s()-]{5,50}$/', $value ) === 1;
				},
			),
			'company'   => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'address'   => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value ) && strlen( trim( $value ) ) >= 2 && strlen( $value ) <= 255;
				},
			),
			'city'      => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value ) && strlen( trim( $value ) ) >= 1 && strlen( $value ) <= 100;
				},
			),
			'state'     => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value ) && strlen( trim( $value ) ) >= 1 && strlen( $value ) <= 100;
				},
			),
			'country'   => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value ) && preg_match( '/^[A-Z]{2}$/', strtoupper( trim( $value ) ) ) === 1;
				},
			),
			'zip'       => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return is_string( $value ) && strlen( trim( $value ) ) >= 2 && strlen( $value ) <= 20;
				},
			),
			'wh4u_hp_check' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'cf-turnstile-response' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
		);
	}

	/**
	 * Handle public domain registration request.
	 *
	 * Uses wp_insert_post() to store the order as a CPT and fires
	 * the wh4u_new_public_order action for notification dispatch.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_public_order( $request ) {
		if ( ! empty( $request->get_param( 'wh4u_hp_check' ) ) ) {
			return new WP_REST_Response( array(
				'success' => true,
				'message' => __( 'Your request has been submitted.', 'wh4u-domains' ),
			), 201 );
		}

		if ( class_exists( 'WH4U_Admin_Settings' ) && WH4U_Admin_Settings::is_turnstile_enabled() ) {
			$token = $request->get_param( 'cf-turnstile-response' );
			if ( ! WH4U_Admin_Settings::verify_turnstile_token( $token ) ) {
				return new WP_Error(
					'wh4u_turnstile_failed',
					__( 'Security verification failed. Please try again.', 'wh4u-domains' ),
					array( 'status' => 403 )
				);
			}
		}

		$ip      = self::get_client_ip();
		$ip_hash = hash( 'sha256', $ip . wp_salt( 'auth' ) );
		$domain  = $request->get_param( 'domain' );

		$meta = array(
			'_wh4u_domain'     => $domain,
			'_wh4u_order_type' => 'register',
			'_wh4u_reg_period' => absint( $request->get_param( 'regperiod' ) ),
			'_wh4u_first_name' => $request->get_param( 'firstName' ),
			'_wh4u_last_name'  => $request->get_param( 'lastName' ),
			'_wh4u_email'      => $request->get_param( 'email' ),
			'_wh4u_phone'      => $request->get_param( 'phone' ),
			'_wh4u_company'    => $request->get_param( 'company' ),
			'_wh4u_address'    => $request->get_param( 'address' ),
			'_wh4u_city'       => $request->get_param( 'city' ),
			'_wh4u_state'      => $request->get_param( 'state' ),
			'_wh4u_country'    => strtoupper( $request->get_param( 'country' ) ),
			'_wh4u_zip'        => $request->get_param( 'zip' ),
			'_wh4u_ip_hash'    => $ip_hash,
		);

		$post_id = wp_insert_post( array(
			'post_type'   => 'wh4u_public_order',
			'post_title'  => sanitize_text_field( $domain ),
			'post_status' => 'wh4u-pending',
			'meta_input'  => self::encrypt_pii_meta( $meta ),
		), true );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'wh4u_save_failed',
				__( 'Failed to save your request. Please try again.', 'wh4u-domains' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a new public domain registration order is stored.
		 *
		 * Hooked by WH4U_Notifications to dispatch admin email. The $meta
		 * payload is the plaintext values; postmeta itself is encrypted at
		 * rest. Handlers that need to persist data must encrypt it themselves.
		 *
		 * @param int   $post_id WP post ID of the public order.
		 * @param array $meta    Sanitized plaintext order meta values.
		 */
		do_action( 'wh4u_new_public_order', $post_id, $meta );

		return new WP_REST_Response( array(
			'success'  => true,
			'order_id' => $post_id,
			'message'  => __( 'Your domain registration request has been submitted successfully. We will contact you shortly.', 'wh4u-domains' ),
		), 201 );
	}

	/**
	 * Argument schema for public transfer order creation.
	 *
	 * Same contact fields as registration, plus an EPP code.
	 *
	 * @return array
	 */
	private function get_public_transfer_args() {
		$args = $this->get_public_order_args();

		$args['eppcode'] = array(
			'required'          => false,
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		);

		return $args;
	}

	/**
	 * Handle public domain transfer request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_public_transfer_order( $request ) {
		if ( ! empty( $request->get_param( 'wh4u_hp_check' ) ) ) {
			return new WP_REST_Response( array(
				'success' => true,
				'message' => __( 'Your request has been submitted.', 'wh4u-domains' ),
			), 201 );
		}

		if ( class_exists( 'WH4U_Admin_Settings' ) && WH4U_Admin_Settings::is_turnstile_enabled() ) {
			$token = $request->get_param( 'cf-turnstile-response' );
			if ( ! WH4U_Admin_Settings::verify_turnstile_token( $token ) ) {
				return new WP_Error(
					'wh4u_turnstile_failed',
					__( 'Security verification failed. Please try again.', 'wh4u-domains' ),
					array( 'status' => 403 )
				);
			}
		}

		$ip      = self::get_client_ip();
		$ip_hash = hash( 'sha256', $ip . wp_salt( 'auth' ) );
		$domain  = $request->get_param( 'domain' );

		$meta = array(
			'_wh4u_domain'     => $domain,
			'_wh4u_order_type' => 'transfer',
			'_wh4u_reg_period' => absint( $request->get_param( 'regperiod' ) ),
			'_wh4u_eppcode'    => $request->get_param( 'eppcode' ),
			'_wh4u_first_name' => $request->get_param( 'firstName' ),
			'_wh4u_last_name'  => $request->get_param( 'lastName' ),
			'_wh4u_email'      => $request->get_param( 'email' ),
			'_wh4u_phone'      => $request->get_param( 'phone' ),
			'_wh4u_company'    => $request->get_param( 'company' ),
			'_wh4u_address'    => $request->get_param( 'address' ),
			'_wh4u_city'       => $request->get_param( 'city' ),
			'_wh4u_state'      => $request->get_param( 'state' ),
			'_wh4u_country'    => strtoupper( $request->get_param( 'country' ) ),
			'_wh4u_zip'        => $request->get_param( 'zip' ),
			'_wh4u_ip_hash'    => $ip_hash,
		);

		$post_id = wp_insert_post( array(
			'post_type'   => 'wh4u_public_order',
			'post_title'  => sanitize_text_field( $domain ),
			'post_status' => 'wh4u-pending',
			'meta_input'  => self::encrypt_pii_meta( $meta ),
		), true );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'wh4u_save_failed',
				__( 'Failed to save your request. Please try again.', 'wh4u-domains' ),
				array( 'status' => 500 )
			);
		}

		/** This action is documented in create_public_order. */
		do_action( 'wh4u_new_public_order', $post_id, $meta );

		return new WP_REST_Response( array(
			'success'  => true,
			'order_id' => $post_id,
			'message'  => __( 'Your domain transfer request has been submitted successfully. We will contact you shortly.', 'wh4u-domains' ),
		), 201 );
	}

	/**
	 * Update the status of a public order (approve / reject).
	 *
	 * Uses wp_update_post() and fires wh4u_public_order_status_changed
	 * so notifications or other side-effects can hook in.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_public_order_status( $request ) {
		$post_id    = $request->get_param( 'id' );
		$new_status = sanitize_text_field( $request->get_param( 'status' ) );

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'wh4u_public_order' ) {
			return new WP_Error(
				'wh4u_not_found',
				__( 'Public order not found.', 'wh4u-domains' ),
				array( 'status' => 404 )
			);
		}

		$old_status = $post->post_status;

		$result = wp_update_post( array(
			'ID'          => $post_id,
			'post_status' => $new_status,
		), true );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'wh4u_update_failed',
				__( 'Failed to update order status.', 'wh4u-domains' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after a public order status is changed by an admin.
		 *
		 * @param int    $post_id    Post ID of the public order.
		 * @param string $new_status New status (e.g. wh4u-approved).
		 * @param string $old_status Previous status.
		 */
		do_action( 'wh4u_public_order_status_changed', $post_id, $new_status, $old_status );

		$status_labels = array(
			'wh4u-approved' => __( 'Approved', 'wh4u-domains' ),
			'wh4u-rejected' => __( 'Rejected', 'wh4u-domains' ),
		);

		$response_data = array(
			'success' => true,
			'status'  => $new_status,
			'label'   => isset( $status_labels[ $new_status ] ) ? $status_labels[ $new_status ] : $new_status,
			'message' => sprintf(
				/* translators: %s: new status label */
				__( 'Order status updated to %s.', 'wh4u-domains' ),
				isset( $status_labels[ $new_status ] ) ? $status_labels[ $new_status ] : $new_status
			),
		);

		if ( $new_status === 'wh4u-approved' ) {
			$api_status = get_post_meta( $post_id, '_wh4u_api_status', true );
			$api_error  = get_post_meta( $post_id, '_wh4u_api_error', true );
			$order_type = get_post_meta( $post_id, '_wh4u_order_type', true );
			$is_transfer = ( $order_type === 'transfer' );

			$response_data['api_status']  = $api_status;
			$response_data['order_type']  = $order_type ? $order_type : 'register';

			if ( $api_status === 'completed' ) {
				$response_data['message'] = $is_transfer
					? __( 'Order approved and domain transfer completed successfully.', 'wh4u-domains' )
					: __( 'Order approved and domain registration completed successfully.', 'wh4u-domains' );
			} elseif ( $api_status === 'queued' ) {
				$response_data['message'] = $is_transfer
					? __( 'Order approved. Transfer queued for retry due to a temporary API issue.', 'wh4u-domains' )
					: __( 'Order approved. Registration queued for retry due to a temporary API issue.', 'wh4u-domains' );
			} elseif ( $api_status === 'failed' ) {
				$response_data['message'] = $is_transfer
					? sprintf(
						/* translators: %s: error message */
						__( 'Order approved but transfer failed: %s', 'wh4u-domains' ),
						$api_error
					)
					: sprintf(
						/* translators: %s: error message */
						__( 'Order approved but registration failed: %s', 'wh4u-domains' ),
						$api_error
					);
			}
		}

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Get client IP address safely.
	 *
	 * @return string IP address.
	 */
	private static function get_client_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '0.0.0.0';
	}
}
