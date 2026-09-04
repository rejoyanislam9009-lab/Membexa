<?php
/**
 * PayPal connection verification and administrator status UI.
 *
 * @package Membexa
 */

namespace Membexa;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies saved PayPal credentials and webhook configuration on demand.
 */
final class PayPal_Connection {
	const OPTION = 'membexa_paypal_connection_status';

	const REQUIRED_EVENTS = array(
		'BILLING.SUBSCRIPTION.ACTIVATED',
		'BILLING.SUBSCRIPTION.UPDATED',
		'BILLING.SUBSCRIPTION.CANCELLED',
		'BILLING.SUBSCRIPTION.EXPIRED',
		'BILLING.SUBSCRIPTION.SUSPENDED',
		'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
		'PAYMENT.SALE.COMPLETED',
	);

	/** Register administrator hooks. */
	public function hooks() {
		add_action( 'admin_post_membexa_verify_paypal', array( $this, 'handle_verify' ) );
		add_action( 'admin_footer', array( $this, 'render_payment_status' ), 20 );
	}

	/** Run a nonce-protected PayPal connection test. */
	public function handle_verify() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to verify PayPal.', 'membexa' ) );
		}
		check_admin_referer( 'membexa_verify_paypal' );

		$status = self::verify();
		update_option( self::OPTION, $status, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                    => 'membexa-settings',
					'tab'                     => 'payments',
					'membexa_paypal_verified' => 'connected' === $status['state'] ? '1' : '0',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Return the current connection status, invalidating stale checks automatically.
	 *
	 * @return array
	 */
	public static function current_status() {
		if ( ! Settings::paypal_client_id() || ! Settings::paypal_client_secret() ) {
			return self::status( 'missing', __( 'Add and save a PayPal Client ID and Client Secret first.', 'membexa' ) );
		}

		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) || empty( $stored['fingerprint'] ) ) {
			return self::status( 'unverified', __( 'Credentials are saved, but this connection has not been verified yet.', 'membexa' ) );
		}

		$fingerprint = self::fingerprint();
		if ( ! is_string( $stored['fingerprint'] ) || ! hash_equals( $stored['fingerprint'], $fingerprint ) ) {
			return self::status( 'stale', __( 'PayPal settings changed after the last verification. Verify the connection again.', 'membexa' ) );
		}

		return wp_parse_args(
			$stored,
			array(
				'state'            => 'unverified',
				'message'          => '',
				'mode'             => self::mode(),
				'verified_at'      => '',
				'webhook_verified' => false,
				'expected_url'     => rest_url( 'membexa/v1/paypal/webhook' ),
				'webhook_url'      => '',
				'missing_events'   => array(),
			)
		);
	}

	/**
	 * Verify OAuth credentials, Webhook ID/URL, and required webhook events.
	 *
	 * @return array
	 */
	private static function verify() {
		$client_id     = Settings::paypal_client_id();
		$client_secret = Settings::paypal_client_secret();
		$webhook_id    = Settings::paypal_webhook_id();

		if ( ! $client_id || ! $client_secret ) {
			return self::status( 'missing', __( 'PayPal Client ID or Client Secret is missing.', 'membexa' ) );
		}

		$token = self::access_token( $client_id, $client_secret );
		if ( is_wp_error( $token ) ) {
			return self::status( 'failed', $token->get_error_message() );
		}

		if ( ! $webhook_id ) {
			return self::status( 'partial', __( 'PayPal API credentials are connected, but a Webhook ID is not configured yet.', 'membexa' ) );
		}

		$webhook = self::api_get( '/v1/notifications/webhooks/' . rawurlencode( $webhook_id ), $token );
		if ( is_wp_error( $webhook ) ) {
			return self::status( 'partial', $webhook->get_error_message() );
		}

		$returned_id  = isset( $webhook['id'] ) ? sanitize_text_field( $webhook['id'] ) : '';
		$returned_url = isset( $webhook['url'] ) ? esc_url_raw( $webhook['url'] ) : '';
		$expected_url = rest_url( 'membexa/v1/paypal/webhook' );

		if ( ! $returned_id || ! hash_equals( (string) $webhook_id, $returned_id ) ) {
			return self::status( 'partial', __( 'PayPal API is connected, but the saved Webhook ID did not match the webhook returned by PayPal.', 'membexa' ) );
		}

		if ( ! $returned_url || self::normalize_url( $returned_url ) !== self::normalize_url( $expected_url ) ) {
			$status                = self::status( 'partial', __( 'PayPal API is connected, but the PayPal webhook URL does not match this WordPress site.', 'membexa' ) );
			$status['webhook_url'] = $returned_url;
			return $status;
		}

		$subscribed_events = self::webhook_event_names( isset( $webhook['event_types'] ) ? $webhook['event_types'] : array() );
		$missing_events    = self::missing_required_events( $subscribed_events );
		if ( ! empty( $missing_events ) ) {
			$status                   = self::status( 'partial', __( 'PayPal API and webhook URL are connected, but one or more required webhook events are not subscribed.', 'membexa' ) );
			$status['webhook_url']    = $returned_url;
			$status['missing_events'] = $missing_events;
			return $status;
		}

		$status                     = self::status( 'connected', __( 'PayPal API credentials, webhook URL, and required webhook events are verified successfully.', 'membexa' ) );
		$status['webhook_verified'] = true;
		$status['webhook_url']      = $returned_url;
		return $status;
	}

	/**
	 * Request an OAuth access token with the saved REST application credentials.
	 *
	 * @param string $client_id     PayPal Client ID.
	 * @param string $client_secret PayPal Client Secret.
	 * @return string|WP_Error
	 */
	private static function access_token( $client_id, $client_secret ) {
		// PayPal OAuth client-credentials authentication requires an HTTP Basic header.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$basic = base64_encode( $client_id . ':' . $client_secret );
		$response = wp_remote_post(
			self::api_base() . '/v1/oauth2/token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . $basic,
					'Accept'        => 'application/json',
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'membexa_paypal_connection_http', __( 'PayPal could not be reached from this WordPress server.', 'membexa' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $data['access_token'] ) ) {
			/* translators: %s: PayPal environment name. */
			return new WP_Error( 'membexa_paypal_connection_auth', sprintf( __( 'PayPal authentication failed in %s mode. Check that the Client ID/Secret belong to the same Sandbox or Live environment selected in Membexa.', 'membexa' ), self::mode() ) );
		}

		return sanitize_text_field( $data['access_token'] );
	}

	/**
	 * Send one authenticated PayPal GET request.
	 *
	 * @param string $endpoint PayPal API path.
	 * @param string $token    OAuth token.
	 * @return array|WP_Error
	 */
	private static function api_get( $endpoint, $token ) {
		$response = wp_remote_get(
			self::api_base() . $endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'membexa_paypal_webhook_http', __( 'PayPal credentials connected, but the Webhook ID could not be checked because PayPal was unreachable.', 'membexa' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'membexa_paypal_webhook_invalid', __( 'PayPal credentials connected, but the saved Webhook ID could not be verified. Confirm that the Webhook ID belongs to the selected Sandbox/Live application.', 'membexa' ) );
		}
		return $data;
	}

	/** Extract subscribed event names from a PayPal webhook response. */
	private static function webhook_event_names( $events ) {
		$names = array();
		foreach ( (array) $events as $event ) {
			if ( is_array( $event ) && ! empty( $event['name'] ) ) {
				$names[] = sanitize_text_field( $event['name'] );
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Return required events that are not covered by the PayPal webhook.
	 *
	 * PayPal uses an asterisk event name to represent a webhook subscribed to all
	 * present and future event types. A wildcard therefore covers every Membexa
	 * required event and must not be reported as missing.
	 *
	 * @param array $subscribed_events Event names returned by PayPal.
	 * @return array
	 */
	private static function missing_required_events( $subscribed_events ) {
		if ( in_array( '*', (array) $subscribed_events, true ) ) {
			return array();
		}

		return array_values( array_diff( self::REQUIRED_EVENTS, (array) $subscribed_events ) );
	}

	/** Build a keyed fingerprint so saved verification cannot survive credential changes. */
	private static function fingerprint() {
		$material = implode(
			'|',
			array(
				self::mode(),
				Settings::paypal_client_id(),
				Settings::paypal_client_secret(),
				Settings::paypal_webhook_id(),
			)
		);
		return hash_hmac( 'sha256', $material, wp_salt( 'auth' ) );
	}

	/** PayPal API base for the configured environment. */
	private static function api_base() {
		$settings = Settings::payments();
		return ! empty( $settings['paypal_sandbox'] ) ? PayPal::SANDBOX_API : PayPal::LIVE_API;
	}

	/** Human-readable environment name. */
	private static function mode() {
		$settings = Settings::payments();
		return ! empty( $settings['paypal_sandbox'] ) ? 'Sandbox' : 'Live';
	}

	/** Normalize webhook URLs for an exact scheme/host/path comparison. */
	private static function normalize_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$host   = strtolower( $parts['host'] );
		$path   = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';
		return $scheme . '://' . $host . $path;
	}

	/** Build a stored status record. */
	private static function status( $state, $message ) {
		return array(
			'state'            => sanitize_key( $state ),
			'message'          => sanitize_text_field( $message ),
			'mode'             => self::mode(),
			'verified_at'      => current_time( 'mysql' ),
			'webhook_verified' => false,
			'expected_url'     => rest_url( 'membexa/v1/paypal/webhook' ),
			'webhook_url'      => '',
			'missing_events'   => array(),
			'fingerprint'      => self::fingerprint(),
		);
	}

	/** Build an unescaped admin verification URL for JavaScript/JSON transport. */
	private static function verify_action_url() {
		return add_query_arg(
			array(
				'action'   => 'membexa_verify_paypal',
				'_wpnonce' => wp_create_nonce( 'membexa_verify_paypal' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/** Render PayPal connection state inside the Payments settings page. */
	public function render_payment_status() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'membexa-settings' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only settings tab selection.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		if ( 'payments' !== $tab ) {
			return;
		}

		$status     = self::current_status();
		$verify_url = self::verify_action_url();
		$labels     = array(
			'connected'  => __( 'Connected', 'membexa' ),
			'partial'    => __( 'API Connected — Webhook needs attention', 'membexa' ),
			'failed'     => __( 'Connection failed', 'membexa' ),
			'stale'      => __( 'Needs verification', 'membexa' ),
			'unverified' => __( 'Not verified', 'membexa' ),
			'missing'    => __( 'Not configured', 'membexa' ),
		);
		$classes    = array(
			'connected'  => 'notice-success',
			'partial'    => 'notice-warning',
			'failed'     => 'notice-error',
			'stale'      => 'notice-warning',
			'unverified' => 'notice-info',
			'missing'    => 'notice-info',
		);
		$state      = isset( $labels[ $status['state'] ] ) ? $status['state'] : 'unverified';
		$data       = array(
			'label'         => $labels[ $state ],
			'className'     => $classes[ $state ],
			'message'       => $status['message'],
			'mode'          => $status['mode'],
			'verifiedAt'    => in_array( $state, array( 'connected', 'partial', 'failed' ), true ) ? $status['verified_at'] : '',
			'expectedUrl'   => $status['expected_url'],
			'webhookUrl'    => $status['webhook_url'],
			'missingEvents' => array_values( (array) $status['missing_events'] ),
			'verifyUrl'     => $verify_url,
			'button'        => __( 'Verify PayPal Connection', 'membexa' ),
			'modeLabel'     => __( 'Mode', 'membexa' ),
			'checkedLabel'  => __( 'Last checked', 'membexa' ),
			'expectedLabel' => __( 'Expected webhook', 'membexa' ),
			'foundLabel'    => __( 'PayPal webhook', 'membexa' ),
			'missingLabel'  => __( 'Missing events', 'membexa' ),
		);
		?>
		<script>
		(function () {
			'use strict';
			var data = <?php echo wp_json_encode( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode safely serializes fixed admin status data. ?>;
			var clientId = document.getElementById('membexa-paypal-client-id');
			var table = clientId ? clientId.closest('table') : null;
			if (!table || document.getElementById('membexa-paypal-connection-status')) {
				return;
			}
			var box = document.createElement('div');
			box.id = 'membexa-paypal-connection-status';
			box.className = 'notice inline ' + data.className;
			box.style.padding = '10px 12px';
			box.style.margin = '10px 0 14px';
			var title = document.createElement('p');
			var strong = document.createElement('strong');
			strong.textContent = data.label;
			title.appendChild(strong);
			title.appendChild(document.createTextNode(' — ' + data.message));
			box.appendChild(title);
			var details = document.createElement('p');
			details.className = 'description';
			details.appendChild(document.createTextNode(data.modeLabel + ': ' + data.mode));
			if (data.verifiedAt) {
				details.appendChild(document.createTextNode(' · ' + data.checkedLabel + ': ' + data.verifiedAt));
			}
			box.appendChild(details);
			if (data.expectedUrl) {
				var expected = document.createElement('p');
				expected.className = 'description';
				expected.textContent = data.expectedLabel + ': ' + data.expectedUrl;
				box.appendChild(expected);
			}
			if (data.webhookUrl && data.webhookUrl !== data.expectedUrl) {
				var found = document.createElement('p');
				found.className = 'description';
				found.textContent = data.foundLabel + ': ' + data.webhookUrl;
				box.appendChild(found);
			}
			if (data.missingEvents.length) {
				var missing = document.createElement('p');
				missing.className = 'description';
				missing.textContent = data.missingLabel + ': ' + data.missingEvents.join(', ');
				box.appendChild(missing);
			}
			var action = document.createElement('p');
			var button = document.createElement('a');
			button.href = data.verifyUrl;
			button.className = 'button button-secondary';
			button.textContent = data.button;
			action.appendChild(button);
			box.appendChild(action);
			table.parentNode.insertBefore(box, table);
		}());
		</script>
		<?php
	}
}
