<?php
/**
 * PayPal Orders and Subscriptions gateway.
 *
 * @package Membexa
 */

namespace Membexa;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles PayPal one-time payments, recurring subscriptions, and webhooks.
 */
final class PayPal {
	const SANDBOX_API = 'https://api-m.sandbox.paypal.com';
	const LIVE_API    = 'https://api-m.paypal.com';

	/** Register PayPal hooks. */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_return' ) );
	}

	/** Register the PayPal webhook endpoint. */
	public function register_routes() {
		register_rest_route(
			'membexa/v1',
			'/paypal/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Determine whether PayPal is configured. */
	public static function enabled() {
		$settings = Settings::payments();
		return ! empty( $settings['paypal_enabled'] ) && Settings::paypal_client_id() && Settings::paypal_client_secret();
	}

	/**
	 * Start a PayPal checkout.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $plan_id Membership plan ID.
	 * @return string|WP_Error
	 */
	public static function start_checkout( $user_id, $plan_id ) {
		$plan = Plan::get( $plan_id );
		$user = get_userdata( $user_id );
		if ( ! $plan || ! $user ) {
			return new WP_Error( 'membexa_paypal_invalid', __( 'The PayPal checkout could not be prepared.', 'membexa' ) );
		}
		if ( ! self::enabled() ) {
			return new WP_Error( 'membexa_paypal_disabled', __( 'PayPal is not configured yet.', 'membexa' ) );
		}

		$subscription_id = Subscriptions::create( $user_id, $plan_id, 'pending', 'paypal', '' );
		if ( ! $subscription_id ) {
			return new WP_Error( 'membexa_subscription_create', __( 'The subscription record could not be created.', 'membexa' ) );
		}

		$account_url = self::account_url();
		$return_url  = wp_nonce_url(
			add_query_arg(
				array(
					'membexa_paypal_return' => '1',
					'membexa_subscription'  => $subscription_id,
				),
				$account_url
			),
			'membexa_paypal_return_' . $subscription_id
		);
		$cancel_url = add_query_arg( 'membexa_notice', 'payment_cancelled', $account_url );

		if ( Gateways::is_recurring( $plan['billing'] ) ) {
			$response = self::create_subscription( $plan, $user, $subscription_id, $return_url, $cancel_url );
		} else {
			$response = self::create_order( $plan, $subscription_id, $return_url, $cancel_url );
		}

		if ( is_wp_error( $response ) ) {
			Subscriptions::cancel_local( $subscription_id );
			return $response;
		}
		if ( empty( $response['id'] ) ) {
			Subscriptions::cancel_local( $subscription_id );
			return new WP_Error( 'membexa_paypal_response', __( 'PayPal returned an incomplete checkout response.', 'membexa' ) );
		}

		Subscriptions::set_external_id( $subscription_id, $response['id'] );
		$approval_url = self::approval_url( isset( $response['links'] ) ? $response['links'] : array() );
		if ( ! $approval_url || ! self::is_paypal_url( $approval_url ) ) {
			Subscriptions::cancel_local( $subscription_id );
			return new WP_Error( 'membexa_paypal_url', __( 'PayPal returned an unexpected checkout URL.', 'membexa' ) );
		}
		return esc_url_raw( $approval_url );
	}

	/**
	 * Create a PayPal recurring subscription.
	 *
	 * @param array    $plan            Membership plan.
	 * @param \WP_User $user            Member.
	 * @param int      $subscription_id Local subscription ID.
	 * @param string   $return_url      Return URL.
	 * @param string   $cancel_url      Cancel URL.
	 * @return array|WP_Error
	 */
	private static function create_subscription( $plan, $user, $subscription_id, $return_url, $cancel_url ) {
		if ( empty( $plan['paypal_plan_id'] ) ) {
			return new WP_Error( 'membexa_paypal_plan', __( 'This recurring plan does not have a PayPal Plan ID.', 'membexa' ) );
		}

		$body = array(
			'plan_id'             => $plan['paypal_plan_id'],
			'quantity'            => '1',
			'custom_id'           => (string) $subscription_id,
			'subscriber'          => array(
				'email_address' => $user->user_email,
			),
			'application_context' => array(
				'brand_name'          => wp_strip_all_tags( get_bloginfo( 'name' ) ),
				'user_action'         => 'SUBSCRIBE_NOW',
				'return_url'          => $return_url,
				'cancel_url'          => $cancel_url,
				'shipping_preference' => 'NO_SHIPPING',
			),
		);
		return self::api_request( 'POST', '/v1/billing/subscriptions', $body, 'membexa-sub-' . $subscription_id );
	}

	/**
	 * Create a PayPal one-time order.
	 *
	 * @param array  $plan            Membership plan.
	 * @param int    $subscription_id Local subscription ID.
	 * @param string $return_url      Return URL.
	 * @param string $cancel_url      Cancel URL.
	 * @return array|WP_Error
	 */
	private static function create_order( $plan, $subscription_id, $return_url, $cancel_url ) {
		$body = array(
			'intent'         => 'CAPTURE',
			'payment_source' => array(
				'paypal' => array(
					'experience_context' => array(
						'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
						'user_action'               => 'PAY_NOW',
						'shipping_preference'       => 'NO_SHIPPING',
						'return_url'                => $return_url,
						'cancel_url'                => $cancel_url,
					),
				),
			),
			'purchase_units' => array(
				array(
					'custom_id'   => (string) $subscription_id,
					'invoice_id'  => 'MBX-' . $subscription_id . '-' . time(),
					'description' => wp_html_excerpt( wp_strip_all_tags( $plan['name'] ), 120, '' ),
					'amount'      => array(
						'currency_code' => strtoupper( $plan['currency'] ),
						'value'         => number_format( (float) $plan['price'], 2, '.', '' ),
					),
				),
			),
		);
		return self::api_request( 'POST', '/v2/checkout/orders', $body, 'membexa-order-' . $subscription_id );
	}

	/** Handle return from a PayPal checkout. */
	public function maybe_handle_return() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only; nonce is verified below.
		if ( empty( $_GET['membexa_paypal_return'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$subscription_id = isset( $_GET['membexa_subscription'] ) ? absint( $_GET['membexa_subscription'] ) : 0;
		$nonce           = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $subscription_id || ! wp_verify_nonce( $nonce, 'membexa_paypal_return_' . $subscription_id ) ) {
			self::redirect_account( 'security' );
		}

		$subscription = Subscriptions::get( $subscription_id );
		if ( ! $subscription || 'paypal' !== $subscription->gateway || get_current_user_id() !== (int) $subscription->user_id ) {
			self::redirect_account( 'invalid_subscription' );
		}

		$plan = Plan::get( $subscription->plan_id );
		if ( $plan && Gateways::is_recurring( $plan['billing'] ) ) {
			self::redirect_account( 'payment_pending' );
		}
		if ( ! $plan ) {
			self::redirect_account( 'payment_failed' );
		}

		$order_id = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( ! $order_id || ( $subscription->gateway_external_id && ! hash_equals( (string) $subscription->gateway_external_id, $order_id ) ) ) {
			self::redirect_account( 'payment_failed' );
		}

		$response = self::api_request( 'POST', '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture', array(), 'membexa-capture-' . $subscription_id );
		if ( is_wp_error( $response ) || 'COMPLETED' !== ( isset( $response['status'] ) ? $response['status'] : '' ) ) {
			self::redirect_account( 'payment_failed' );
		}

		$capture  = isset( $response['purchase_units'][0]['payments']['captures'][0] ) ? $response['purchase_units'][0]['payments']['captures'][0] : array();
		$amount   = isset( $capture['amount']['value'] ) ? (float) $capture['amount']['value'] : 0;
		$currency = isset( $capture['amount']['currency_code'] ) ? strtoupper( sanitize_text_field( $capture['amount']['currency_code'] ) ) : '';
		if (
			empty( $capture['id'] )
			|| 'COMPLETED' !== ( isset( $capture['status'] ) ? $capture['status'] : '' )
			|| abs( (float) $plan['price'] - $amount ) >= 0.01
			|| strtoupper( $plan['currency'] ) !== $currency
		) {
			self::redirect_account( 'payment_failed' );
		}

		Subscriptions::activate( $subscription_id, $order_id );
		Subscriptions::log_transaction(
			array(
				'user_id'         => $subscription->user_id,
				'subscription_id' => $subscription_id,
				'gateway'         => 'paypal',
				'external_id'     => sanitize_text_field( $capture['id'] ),
				'type'            => 'capture',
				'amount'          => $amount,
				'currency'        => $currency,
				'status'          => 'paid',
			)
		);
		self::redirect_account( 'payment_success' );
	}

	/**
	 * Cancel a recurring PayPal subscription immediately.
	 *
	 * @param object $subscription Local subscription record.
	 * @return true|WP_Error
	 */
	public static function cancel_subscription( $subscription ) {
		if ( ! $subscription || 'paypal' !== $subscription->gateway || ! $subscription->gateway_external_id ) {
			return new WP_Error( 'membexa_paypal_cancel', __( 'This PayPal subscription cannot be canceled.', 'membexa' ) );
		}

		$response = self::api_request(
			'POST',
			'/v1/billing/subscriptions/' . rawurlencode( $subscription->gateway_external_id ) . '/cancel',
			array( 'reason' => __( 'Canceled by the member from the Membexa account page.', 'membexa' ) ),
			'membexa-cancel-' . $subscription->id
		);
		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Process and verify PayPal webhooks.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function webhook( WP_REST_Request $request ) {
		$webhook_id = Settings::paypal_webhook_id();
		$event      = json_decode( $request->get_body(), true );
		if ( ! $webhook_id || ! is_array( $event ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_webhook' ), 400 );
		}

		$verification = array(
			'auth_algo'         => $request->get_header( 'paypal-auth-algo' ),
			'cert_url'          => $request->get_header( 'paypal-cert-url' ),
			'transmission_id'   => $request->get_header( 'paypal-transmission-id' ),
			'transmission_sig'  => $request->get_header( 'paypal-transmission-sig' ),
			'transmission_time' => $request->get_header( 'paypal-transmission-time' ),
			'webhook_id'        => $webhook_id,
			'webhook_event'     => $event,
		);
		$verified = self::api_request( 'POST', '/v1/notifications/verify-webhook-signature', $verification );
		if ( is_wp_error( $verified ) || 'SUCCESS' !== ( isset( $verified['verification_status'] ) ? $verified['verification_status'] : '' ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
		}

		$event_id = isset( $event['id'] ) ? sanitize_text_field( $event['id'] ) : '';
		if ( ! $event_id ) {
			return new WP_REST_Response( array( 'error' => 'missing_event_id' ), 400 );
		}
		$key = 'membexa_paypal_evt_' . md5( $event_id );
		if ( get_transient( $key ) ) {
			return new WP_REST_Response(
				array(
					'received'  => true,
					'duplicate' => true,
				),
				200
			);
		}

		$type     = isset( $event['event_type'] ) ? sanitize_text_field( $event['event_type'] ) : '';
		$resource = isset( $event['resource'] ) && is_array( $event['resource'] ) ? $event['resource'] : array();
		$this->handle_event( $type, $resource );
		set_transient( $key, 1, DAY_IN_SECONDS );
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Map a verified PayPal event to local membership state.
	 *
	 * @param string $type     Event type.
	 * @param array  $resource Event resource.
	 * @return void
	 */
	private function handle_event( $type, $resource ) {
		$external_id = isset( $resource['id'] ) ? sanitize_text_field( $resource['id'] ) : '';
		$local_id    = isset( $resource['custom_id'] ) ? absint( $resource['custom_id'] ) : 0;

		if ( 'BILLING.SUBSCRIPTION.ACTIVATED' === $type ) {
			if ( $local_id && $external_id ) {
				Subscriptions::set_external_id( $local_id, $external_id );
				Subscriptions::activate( $local_id, $external_id );
			}
			return;
		}

		if ( in_array( $type, array( 'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED' ), true ) && $external_id ) {
			Subscriptions::update_status_by_external_id( $external_id, 'BILLING.SUBSCRIPTION.EXPIRED' === $type ? 'expired' : 'canceled' );
			return;
		}

		if ( in_array( $type, array( 'BILLING.SUBSCRIPTION.SUSPENDED', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' ), true ) && $external_id ) {
			Subscriptions::update_status_by_external_id( $external_id, 'past_due' );
			return;
		}

		if ( 'BILLING.SUBSCRIPTION.UPDATED' === $type && $external_id ) {
			$status = isset( $resource['status'] ) ? strtoupper( sanitize_text_field( $resource['status'] ) ) : '';
			$map    = array(
				'ACTIVE'           => 'active',
				'APPROVAL_PENDING' => 'pending',
				'SUSPENDED'        => 'past_due',
				'CANCELLED'        => 'canceled',
				'EXPIRED'          => 'expired',
			);
			if ( isset( $map[ $status ] ) ) {
				Subscriptions::update_status_by_external_id( $external_id, $map[ $status ] );
			}
			return;
		}

		if ( 'PAYMENT.SALE.COMPLETED' === $type ) {
			$this->handle_sale_completed( $resource );
		}
	}

	/**
	 * Record a verified PayPal recurring payment.
	 *
	 * @param array $resource PayPal sale resource.
	 * @return void
	 */
	private function handle_sale_completed( $resource ) {
		$agreement_id = isset( $resource['billing_agreement_id'] ) ? sanitize_text_field( $resource['billing_agreement_id'] ) : '';
		$subscription = $agreement_id ? Subscriptions::get_by_external_id( $agreement_id ) : null;
		if ( ! $subscription ) {
			return;
		}

		$external_id = isset( $resource['id'] ) ? sanitize_text_field( $resource['id'] ) : '';
		Subscriptions::activate( $subscription->id, $agreement_id );
		Subscriptions::log_transaction(
			array(
				'user_id'         => $subscription->user_id,
				'subscription_id' => $subscription->id,
				'gateway'         => 'paypal',
				'external_id'     => $external_id,
				'type'            => 'renewal',
				'amount'          => isset( $resource['amount']['total'] ) ? (float) $resource['amount']['total'] : 0,
				'currency'        => isset( $resource['amount']['currency'] ) ? $resource['amount']['currency'] : '',
				'status'          => 'paid',
			)
		);
	}

	/**
	 * Send an authenticated PayPal REST API request.
	 *
	 * @param string $method     HTTP method.
	 * @param string $endpoint   API path.
	 * @param array  $body       JSON body.
	 * @param string $request_id Optional idempotency request ID.
	 * @return array|WP_Error
	 */
	private static function api_request( $method, $endpoint, $body = array(), $request_id = '' ) {
		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		);
		if ( $request_id ) {
			$headers['PayPal-Request-Id'] = sanitize_text_field( $request_id );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 30,
			'headers' => $headers,
		);
		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::api_base() . $endpoint, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['message'] ) ? sanitize_text_field( $data['message'] ) : __( 'PayPal rejected the request.', 'membexa' );
			return new WP_Error( 'membexa_paypal_api', $message );
		}
		return is_array( $data ) ? $data : array();
	}

	/** Get and cache a PayPal OAuth access token. */
	private static function access_token() {
		$client_id     = Settings::paypal_client_id();
		$client_secret = Settings::paypal_client_secret();
		if ( ! $client_id || ! $client_secret ) {
			return new WP_Error( 'membexa_paypal_credentials', __( 'PayPal API credentials are missing.', 'membexa' ) );
		}

		$cache_key = 'membexa_paypal_token_' . md5( self::api_base() . $client_id );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && $cached ) {
			return $cached;
		}

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
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 || empty( $data['access_token'] ) ) {
			return new WP_Error( 'membexa_paypal_auth', __( 'PayPal authentication failed.', 'membexa' ) );
		}
		$ttl = isset( $data['expires_in'] ) ? max( 300, absint( $data['expires_in'] ) - 300 ) : 3000;
		set_transient( $cache_key, sanitize_text_field( $data['access_token'] ), $ttl );
		return sanitize_text_field( $data['access_token'] );
	}

	/** Find a PayPal approval URL in an API response. */
	private static function approval_url( $links ) {
		foreach ( (array) $links as $link ) {
			if ( ! is_array( $link ) || empty( $link['href'] ) || empty( $link['rel'] ) ) {
				continue;
			}
			if ( in_array( $link['rel'], array( 'approve', 'payer-action' ), true ) ) {
				return (string) $link['href'];
			}
		}
		return '';
	}

	/** Validate that a hosted approval URL belongs to PayPal. */
	private static function is_paypal_url( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return 'paypal.com' === $host || ( strlen( $host ) > 11 && '.paypal.com' === substr( $host, -11 ) );
	}

	/** API base URL for the selected environment. */
	private static function api_base() {
		$settings = Settings::payments();
		return ! empty( $settings['paypal_sandbox'] ) ? self::SANDBOX_API : self::LIVE_API;
	}

	/** Configured account page URL. */
	private static function account_url() {
		$general = Settings::general();
		return $general['account_page_id'] ? get_permalink( $general['account_page_id'] ) : home_url( '/' );
	}

	/** Redirect back to the account page with a notice. */
	private static function redirect_account( $notice ) {
		wp_safe_redirect( add_query_arg( 'membexa_notice', sanitize_key( $notice ), self::account_url() ) );
		exit;
	}
}
