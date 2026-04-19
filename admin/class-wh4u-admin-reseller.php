<?php
/**
 * Per-user reseller credentials management.
 *
 * @package WH4U_Domains
 * @license GPL-2.0-or-later
 * @link    https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WH4U_Admin_Reseller {

	/**
	 * Render the Credentials tab (per-user reseller settings).
	 *
	 * @param bool   $saved Whether settings were just saved.
	 * @param string $error Error message if save failed.
	 * @return void
	 */
	public static function render_credentials_tab( $saved, $error ) {
		$user_id  = get_current_user_id();
		$settings = self::get_reseller_settings( $user_id );
		?>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Credentials saved.', 'wh4u-domains' ); ?></p></div>
		<?php endif; ?>

		<?php if ( $error ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<form method="post" class="wh4u-settings-form">
			<?php wp_nonce_field( 'wh4u_reseller_settings_nonce', '_wh4u_nonce' ); ?>
			<input type="hidden" name="tab" value="credentials" />

			<h2 class="wh4u-section-title"><?php esc_html_e( 'API Credentials', 'wh4u-domains' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="reseller_email"><?php esc_html_e( 'Reseller Email', 'wh4u-domains' ); ?></label></th>
					<td>
						<input type="email" id="reseller_email" name="reseller_email"
							   value="<?php echo esc_attr( $settings['reseller_email'] ); ?>"
							   class="regular-text" required />
						<p class="description"><?php esc_html_e( 'Your email registered with the domain registrar.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="api_key"><?php esc_html_e( 'API Key', 'wh4u-domains' ); ?></label></th>
					<td>
						<input type="password" id="api_key" name="api_key"
							   value="" class="regular-text"
							   placeholder="<?php echo $settings['has_api_key'] ? esc_attr__( '(stored, enter new to change)', 'wh4u-domains' ) : ''; ?>"
							   autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Stored encrypted. Leave blank to keep existing key.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
			</table>

			<h2 class="wh4u-section-title"><?php esc_html_e( 'Domain Defaults', 'wh4u-domains' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="default_ns1"><?php esc_html_e( 'Default Nameservers', 'wh4u-domains' ); ?></label></th>
					<td>
						<?php for ( $i = 1; $i <= 5; $i++ ) :
							$ns_key = 'ns' . $i;
							$ns_val = isset( $settings['nameservers'][ $ns_key ] ) ? $settings['nameservers'][ $ns_key ] : '';
						?>
							<input type="text" name="nameservers[<?php echo esc_attr( $ns_key ); ?>]"
								   value="<?php echo esc_attr( $ns_val ); ?>"
								   class="regular-text" placeholder="<?php echo esc_attr( 'ns' . $i . '.example.com' ); ?>"
								   /><br />
						<?php endfor; ?>
						<p class="description"><?php esc_html_e( 'Default nameservers for new registrations. NS1 and NS2 are required by most registrars.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="allowed_tlds"><?php esc_html_e( 'Allowed TLDs', 'wh4u-domains' ); ?></label></th>
					<td>
						<textarea id="allowed_tlds" name="allowed_tlds" rows="4" class="large-text"><?php echo esc_textarea( $settings['allowed_tlds_text'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Comma-separated list of allowed TLDs (e.g. .com,.net,.org). Leave empty for all TLDs.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
			</table>

			<h2 class="wh4u-section-title"><?php esc_html_e( 'Webhooks', 'wh4u-domains' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="webhook_url"><?php esc_html_e( 'Webhook URL', 'wh4u-domains' ); ?></label></th>
					<td>
						<input type="url" id="webhook_url" name="webhook_url"
							   value="<?php echo esc_attr( $settings['webhook_url'] ); ?>"
							   class="regular-text" />
						<p class="description"><?php esc_html_e( 'Optional endpoint for order notifications.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="webhook_secret"><?php esc_html_e( 'Webhook Secret', 'wh4u-domains' ); ?></label></th>
					<td>
						<input type="password" id="webhook_secret" name="webhook_secret"
							   value="" class="regular-text"
							   placeholder="<?php echo $settings['has_webhook_secret'] ? esc_attr__( '(stored, enter new to change)', 'wh4u-domains' ) : ''; ?>"
							   autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Shared secret for HMAC-signing webhook payloads.', 'wh4u-domains' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Credentials', 'wh4u-domains' ), 'primary', 'wh4u_reseller_save' ); ?>
		</form>
		<?php
	}

	/**
	 * Get current user's reseller settings for the form.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array Settings data.
	 */
	public static function get_reseller_settings( $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'wh4u_reseller_settings WHERE user_id = %d', $user_id ) );

		$defaults = array(
			'reseller_email'     => '',
			'has_api_key'        => false,
			'nameservers'        => array(),
			'allowed_tlds_text'  => '',
			'webhook_url'        => '',
			'has_webhook_secret' => false,
		);

		if ( ! $row ) {
			$user = get_userdata( $user_id );
			$defaults['reseller_email'] = $user ? $user->user_email : '';
			return $defaults;
		}

		$nameservers  = json_decode( $row->default_nameservers, true );
		$allowed_tlds = json_decode( $row->allowed_tlds, true );

		return array(
			'reseller_email'     => $row->reseller_email,
			'has_api_key'        => ! empty( $row->api_key_encrypted ),
			'nameservers'        => is_array( $nameservers ) ? $nameservers : array(),
			'allowed_tlds_text'  => is_array( $allowed_tlds ) ? implode( ',', $allowed_tlds ) : '',
			'webhook_url'        => $row->webhook_url,
			'has_webhook_secret' => ! empty( $row->webhook_secret_encrypted ),
		);
	}

	/**
	 * Save reseller settings from POST data.
	 *
	 * Nonce is re-verified here as defense-in-depth: the caller
	 * (WH4U_Admin_Settings::render_page) also checks it, but this guards
	 * against future callers that might forget.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return true|WP_Error
	 */
	public static function save_reseller_settings( $user_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified on next line
		$nonce = isset( $_POST['_wh4u_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wh4u_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wh4u_reseller_settings_nonce' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'wh4u-domains' ) );
		}

		global $wpdb;

		$email = isset( $_POST['reseller_email'] ) ? sanitize_email( wp_unslash( $_POST['reseller_email'] ) ) : '';
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'wh4u-domains' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'wh4u_reseller_settings WHERE user_id = %d', $user_id ) );

		$api_key_raw = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$api_key_encrypted = '';
		if ( ! empty( $api_key_raw ) ) {
			$api_key_encrypted = WH4U_Encryption::encrypt( $api_key_raw );
		} elseif ( $existing ) {
			$api_key_encrypted = $existing->api_key_encrypted;
		}

		$nameservers_raw = isset( $_POST['nameservers'] ) && is_array( $_POST['nameservers'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['nameservers'] ) )
			: array();
		$allowed_ns_keys = array( 'ns1', 'ns2', 'ns3', 'ns4', 'ns5' );
		$nameservers     = array();
		foreach ( $nameservers_raw as $key => $value ) {
			$clean_key = sanitize_text_field( $key );
			if ( ! in_array( $clean_key, $allowed_ns_keys, true ) ) {
				continue;
			}
			$clean_val = sanitize_text_field( $value );
			if ( ! empty( $clean_val ) ) {
				$nameservers[ $clean_key ] = $clean_val;
			}
		}

		$allowed_tlds_raw = isset( $_POST['allowed_tlds'] ) ? sanitize_text_field( wp_unslash( $_POST['allowed_tlds'] ) ) : '';
		$allowed_tlds     = null;
		if ( ! empty( $allowed_tlds_raw ) ) {
			$allowed_tlds = array_filter( array_map( 'trim', explode( ',', $allowed_tlds_raw ) ) );
			$allowed_tlds = array_map( 'sanitize_text_field', $allowed_tlds );
		}

		$webhook_url = isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ), array( 'https', 'http' ) ) : '';

		$webhook_secret_raw = isset( $_POST['webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$webhook_secret_encrypted = '';
		if ( ! empty( $webhook_secret_raw ) ) {
			$webhook_secret_encrypted = WH4U_Encryption::encrypt( $webhook_secret_raw );
		} elseif ( $existing ) {
			$webhook_secret_encrypted = $existing->webhook_secret_encrypted;
		}

		$data = array(
			'user_id'                  => $user_id,
			'reseller_email'           => $email,
			'api_key_encrypted'        => $api_key_encrypted,
			'default_nameservers'      => wp_json_encode( $nameservers ),
			'allowed_tlds'             => $allowed_tlds !== null ? wp_json_encode( $allowed_tlds ) : null,
			'webhook_url'              => $webhook_url,
			'webhook_secret_encrypted' => $webhook_secret_encrypted,
			'updated_at'               => current_time( 'mysql', true ),
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->prefix . 'wh4u_reseller_settings', $data, array( 'user_id' => $user_id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$data['created_at'] = current_time( 'mysql', true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $wpdb->prefix . 'wh4u_reseller_settings', $data,
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		delete_transient( 'wh4u_api_status_u' . $user_id );
		delete_transient( 'wh4u_credits_u' . $user_id );

		return true;
	}
}
