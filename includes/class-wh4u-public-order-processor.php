<?php
/**
 * Processes approved public orders through the DomainsReseller API.
 *
 * Listens on the wh4u_public_order_status_changed hook and triggers
 * domain registration or transfer when a public order is approved.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WH4U_Public_Order_Processor {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function init_hooks() {
		add_action( 'wh4u_public_order_status_changed', array( __CLASS__, 'handle_status_change' ), 10, 3 );
	}

	/**
	 * Handle public order status change.
	 *
	 * When an order is approved, builds the API payload from post meta
	 * and dispatches to the DomainsReseller API using the current admin's
	 * reseller credentials.
	 *
	 * @param int    $post_id    Post ID of the public order CPT.
	 * @param string $new_status New status (e.g. wh4u-approved).
	 * @param string $old_status Previous status.
	 * @return void
	 */
	public static function handle_status_change( $post_id, $new_status, $old_status ) {
		if ( $new_status !== 'wh4u-approved' ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'wh4u_public_order' ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			update_post_meta( $post_id, '_wh4u_api_status', 'failed' );
			update_post_meta( $post_id, '_wh4u_api_error', __( 'No authenticated user found to process the API call.', 'wh4u-domains' ) );
			return;
		}

		$order_type = get_post_meta( $post_id, '_wh4u_order_type', true );
		if ( ! in_array( $order_type, array( 'register', 'transfer' ), true ) ) {
			$order_type = 'register';
		}

		$domain     = get_post_meta( $post_id, '_wh4u_domain', true );
		$regperiod  = (int) get_post_meta( $post_id, '_wh4u_reg_period', true );

		if ( empty( $domain ) || $regperiod < 1 ) {
			update_post_meta( $post_id, '_wh4u_api_status', 'failed' );
			update_post_meta( $post_id, '_wh4u_api_error', __( 'Missing domain or registration period.', 'wh4u-domains' ) );
			return;
		}

		$contact = array(
			'firstname'   => get_post_meta( $post_id, '_wh4u_first_name', true ),
			'lastname'    => get_post_meta( $post_id, '_wh4u_last_name', true ),
			'email'       => get_post_meta( $post_id, '_wh4u_email', true ),
			'address1'    => get_post_meta( $post_id, '_wh4u_address', true ),
			'city'        => get_post_meta( $post_id, '_wh4u_city', true ),
			'state'       => get_post_meta( $post_id, '_wh4u_state', true ),
			'postcode'    => get_post_meta( $post_id, '_wh4u_zip', true ),
			'country'     => get_post_meta( $post_id, '_wh4u_country', true ),
			'phonenumber' => get_post_meta( $post_id, '_wh4u_phone', true ),
		);

		$company = get_post_meta( $post_id, '_wh4u_company', true );
		if ( ! empty( $company ) ) {
			$contact['companyname'] = $company;
		}

		$nameservers = self::get_nameservers_for_user( $user_id );
		if ( is_wp_error( $nameservers ) ) {
			update_post_meta( $post_id, '_wh4u_api_status', 'failed' );
			update_post_meta( $post_id, '_wh4u_api_error', $nameservers->get_error_message() );
			return;
		}

		if ( $order_type === 'transfer' ) {
			$api_endpoint = '/order/domains/transfer';
			$api_params   = array(
				'domain'      => $domain,
				'regperiod'   => (string) $regperiod,
				'eppcode'     => get_post_meta( $post_id, '_wh4u_eppcode', true ),
				'contacts'    => array(
					'registrant' => $contact,
					'admin'      => $contact,
					'tech'       => $contact,
					'billing'    => $contact,
				),
				'nameservers' => $nameservers,
			);
		} else {
			$api_endpoint = '/order/domains/register';
			$api_params   = array(
				'domain'      => $domain,
				'regperiod'   => (string) $regperiod,
				'contacts'    => array(
					'registrant' => $contact,
					'admin'      => $contact,
					'tech'       => $contact,
					'billing'    => $contact,
				),
				'nameservers' => $nameservers,
				'addons'      => array(
					'dnsmanagement'   => 0,
					'emailforwarding' => 0,
					'idprotection'    => 0,
				),
			);
		}

		$client = WH4U_Api_Client::from_user( $user_id );
		if ( is_wp_error( $client ) ) {
			update_post_meta( $post_id, '_wh4u_api_status', 'failed' );
			update_post_meta( $post_id, '_wh4u_api_error', $client->get_error_message() );
			return;
		}

		update_post_meta( $post_id, '_wh4u_api_status', 'processing' );

		$response = $client->post( $api_endpoint, $api_params );

		if ( is_wp_error( $response ) ) {
			if ( WH4U_Api_Client::is_retryable( $response ) ) {
				$order_id = self::create_internal_order( $post_id, $user_id, $domain, $regperiod, $contact, $nameservers, $order_type, 'processing' );
				if ( $order_id ) {
					WH4U_Queue::enqueue( $order_id, $api_endpoint, $api_params );
					update_post_meta( $post_id, '_wh4u_api_status', 'queued' );
					update_post_meta( $post_id, '_wh4u_api_error', $response->get_error_message() );
					update_post_meta( $post_id, '_wh4u_internal_order_id', $order_id );
					return;
				}
			}

			update_post_meta( $post_id, '_wh4u_api_status', 'failed' );
			update_post_meta( $post_id, '_wh4u_api_error', $response->get_error_message() );
			return;
		}

		update_post_meta( $post_id, '_wh4u_api_status', 'completed' );
		update_post_meta( $post_id, '_wh4u_api_response', wp_json_encode( WH4U_Logger::mask_secrets( $response ) ) );

		$internal_order_id = self::create_internal_order( $post_id, $user_id, $domain, $regperiod, $contact, $nameservers, $order_type, 'completed', $response );
		if ( $internal_order_id ) {
			update_post_meta( $post_id, '_wh4u_internal_order_id', $internal_order_id );
			WH4U_Notifications::send_order_notification( $internal_order_id, 'completed' );
		}
	}

	/**
	 * Retrieve the admin user's default nameservers from reseller settings.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|WP_Error Nameservers array or error if ns1/ns2 missing.
	 */
	private static function get_nameservers_for_user( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wh4u_reseller_settings';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table from $wpdb->prefix
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT default_nameservers FROM {$table} WHERE user_id = %d", $user_id ) );

		if ( ! $row || empty( $row->default_nameservers ) ) {
			return new WP_Error(
				'wh4u_no_nameservers',
				__( 'No default nameservers configured. Please set them under Domains > Settings > Credentials.', 'wh4u-domains' )
			);
		}

		$ns = json_decode( $row->default_nameservers, true );
		if ( ! is_array( $ns ) || empty( $ns['ns1'] ) || empty( $ns['ns2'] ) ) {
			return new WP_Error(
				'wh4u_insufficient_nameservers',
				__( 'At least NS1 and NS2 must be configured in your reseller settings.', 'wh4u-domains' )
			);
		}

		$nameservers = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$key = 'ns' . $i;
			if ( ! empty( $ns[ $key ] ) ) {
				$nameservers[ $key ] = sanitize_text_field( $ns[ $key ] );
			}
		}

		return $nameservers;
	}

	/**
	 * Create an internal order row in wh4u_orders so the order appears
	 * in the standard order history and can be tracked/retried.
	 *
	 * @param int         $post_id      Public order CPT post ID.
	 * @param int         $user_id      Admin user ID who approved.
	 * @param string      $domain       Domain name.
	 * @param int         $regperiod    Registration period in years.
	 * @param array       $contact      Contact details array.
	 * @param array       $nameservers  Nameserver array.
	 * @param string      $order_type   Order type: register or transfer.
	 * @param string      $status       Order status (default 'processing').
	 * @param array|null  $api_response API response if completed.
	 * @return int|false  Order ID or false on failure.
	 */
	private static function create_internal_order( $post_id, $user_id, $domain, $regperiod, $contact, $nameservers, $order_type = 'register', $status = 'processing', $api_response = null ) {
		global $wpdb;

		$contacts_encrypted = WH4U_Encryption::encrypt( wp_json_encode( array(
			'registrant' => $contact,
			'admin'      => $contact,
			'tech'       => $contact,
			'billing'    => $contact,
		) ) );

		$data = array(
			'user_id'      => $user_id,
			'domain'       => sanitize_text_field( $domain ),
			'order_type'   => sanitize_key( $order_type ),
			'status'       => sanitize_text_field( $status ),
			'reg_period'   => absint( $regperiod ),
			'contacts'     => $contacts_encrypted,
			'nameservers'  => wp_json_encode( $nameservers ),
			'addons'       => '',
			'eppcode'      => '',
			'created_at'   => current_time( 'mysql', true ),
			'updated_at'   => current_time( 'mysql', true ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $api_response !== null ) {
			$data['api_response'] = wp_json_encode( WH4U_Logger::mask_secrets( $api_response ) );
			$format[] = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->insert(
			$wpdb->prefix . 'wh4u_orders',
			$data,
			$format
		);

		if ( $result === false ) {
			return false;
		}

		return $wpdb->insert_id;
	}
}
