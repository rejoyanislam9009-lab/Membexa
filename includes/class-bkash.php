<?php
/**
 * bKash Tokenized Checkout gateway.
 *
 * @package Membexa
 */

namespace Membexa;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Bangladesh bKash Tokenized Checkout payments.
 */
final class Bkash {
	const SANDBOX_API = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta';
	const LIVE_API    = 'https://tokenized.pay.bka.sh/v1.2.0-beta';

	/**
	 * Register bKash callback hook.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'template_redirect', array( $this, 'maybe_handle_return' ) );
	}

	/**
	 * Determine whether bKash is configured.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$settings = Settings::payments();
		return ! empty( $settings['bkash_enabled'] )
			&& Settings::bkash_username()
			&& Settings::bkash_password()
			&& Settings::bkash_app_key()
			&& Settings::bkash_app_secret();
	}

	/**
	 * Start a hosted bKash payment.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $plan_id Membership plan ID.
	 * @return string|WP_Error
	 */
	public static function start_checkout( $user_id, $plan_id ) {
		$plan = Plan::get( $plan_id );
		$user = get_userdata( $user_id );
		if ( ! $plan || ! $user ) {
			return new WP_Error( 'membexa_bkash_invalid', __( 'The bKash checkout could not be prepared.', 'membexa' ) );
		}
		if ( ! self::enabled() ) {
			return new WP_Error( 'membexa_bkash_disabled', __( 'bKash is not configured yet.', 'membexa' ) );
		}
		if ( 'BDT' !== strtoupper( (string) $plan['currency'] ) || ! in_array( $plan['billing'], array( 'one_time', 'lifetime' ), true ) ) {
			return new WP_Error( 'membexa_bkash_plan', __( 'bKash is available only for BDT one-time or lifetime plans.', 'membexa' ) );
		}

		$subscription_id = Subscriptions::create( $user_id, $plan_id, 'pending', 'bkash', '' );
		if ( ! $subscription_id ) {
			return new WP_Error( 'membexa_subscription_create', __( 'The subscription record could not be created.', 'membexa' ) );
		}

		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			Subscriptions::cancel_local( $subscription_id );
			return $token;
		}

		$callback_url = wp_nonce_url(
			add_query_arg(
				array(
					'membexa_bkash_return' => '1',
					'membexa_subscription' => $subscription_id,
				),
				self::account_url()
			),
			'membexa_bkash_return_' . $subscription_id
		);

		$body = array(
			'mode'                  => '0011',
			'payerReference'        => 'MBX' . absint( $user_id ),
			'callbackURL'           => $callback_url,
			'amount'                => number_format( (float) $plan['price'], 2, '.', '' ),
			'currency'              => 'BDT',
			'intent'                => 'sale',
			'merchantInvoiceNumber' => 'MBX' . $subscription_id . time(),
		);
		$response = self::api_request( '/tokenized/checkout/create', $body, $token );
		if ( is_wp_error( $response ) ) {
			Subscriptions::cancel_local( $subscription_id );
			return $response;
		}
		if ( empty( $response['paymentID'] ) || empty( $response['bkashURL'] ) || ( isset( $response['statusCode'] ) && '0000' !== (string) $response['statusCode'] ) ) {
			Subscriptions::cancel_local( $subscription_id );
			return new WP_Error( 'membexa_bkash_response', __( 'bKash returned an incomplete payment response.', 'membexa' ) );
		}
		if ( ! self::is_bkash_url( $response['bkashURL'] ) ) {
			Subscriptions::cancel_local( $subscription_id );
			return new WP_Error( 'membexa_bkash_url', __( 'bKash returned an unexpected payment URL.', 'membexa' ) );
		}

		Subscriptions::set_external_id( $subscription_id, $response['paymentID'] );
		return esc_url_raw( $response['bkashURL'] );
	}

	/**
	 * Handle the hosted bKash callback and execute an approved payment.
	 *
	 * @return void
	 */
	public function maybe_handle_return() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only; nonce is verified below.
		if ( empty( $_GET['membexa_bkash_return'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$subscription_id = isset( $_GET['membexa_subscription'] ) ? absint( $_GET['membexa_subscription'] ) : 0;
		$nonce           = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $subscription_id || ! wp_verify_nonce( $nonce, 'membexa_bkash_return_' . $subscription_id ) ) {
			self::redirect_account( 'security' );
		}

		$subscription = Subscriptions::get( $subscription_id );
		if ( ! $subscription || 'bkash' !== $subscription->gateway || get_current_user_id() !== (int) $subscription->user_id ) {
			self::redirect_account( 'invalid_subscription' );
		}

		$status     = isset( $_GET['status'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['status'] ) ) ) : '';
		$payment_id = isset( $_GET['paymentID'] ) ? sanitize_text_field( wp_unslash( $_GET['paymentID'] ) ) : '';
		if ( 'success' !== $status || ! $payment_id || ! hash_equals( (string) $subscription->gateway_external_id, $payment_id ) ) {
			Subscriptions::cancel_local( $subscription_id );
			self::redirect_account( 'payment_cancelled' );
		}

		$token = self::access_token();
		if ( is_wp_error( $token ) ) {
			self::redirect_account( 'payment_failed' );
		}
		$response = self::api_request(
			'/tokenized/checkout/execute',
			array( 'paymentID' => $payment_id ),
			$token
		);
		if ( is_wp_error( $response ) ) {
			self::redirect_account( 'payment_failed' );
		}

		$plan               = Plan::get( $subscription->plan_id );
		$transaction_status = isset( $response['transactionStatus'] ) ? strtolower( sanitize_text_field( $response['transactionStatus'] ) ) : '';
		$amount             = isset( $response['amount'] ) ? (float) $response['amount'] : 0;
		$currency           = isset( $response['currency'] ) ? strtoupper( sanitize_text_field( $response['currency'] ) ) : '';
		$valid_amount       = $plan && abs( (float) $plan['price'] - $amount ) < 0.01;
		if (
			empty( $response['trxID'] )
			|| ( isset( $response['statusCode'] ) && '0000' !== (string) $response['statusCode'] )
			|| ( $transaction_status && 'completed' !== $transaction_status )
			|| ! $valid_amount
			|| 'BDT' !== $currency
		) {
			self::redirect_account( 'payment_failed' );
		}

		Subscriptions::activate( $subscription_id, $payment_id );
		Subscriptions::log_transaction(
			array(
				'user_id'         => $subscription->user_id,
				'subscription_id' => $subscription_id,
				'gateway'         => 'bkash',
				'external_id'     => sanitize_text_field( $response['trxID'] ),
				'type'            => 'payment',
				'amount'          => $amount,
				'currency'        => 'BDT',
				'status'          => 'paid',
			)
		);
		self::redirect_account( 'payment_success' );
	}

	/**
	 * Obtain and cache a bKash grant token.
	 *
	 * @return string|WP_Error
	 */
	private static function access_token() {
		$username   = Settings::bkash_username();
		$password   = Settings::bkash_password();
		$app_key    = Settings::bkash_app_key();
		$app_secret = Settings::bkash_app_secret();
		if ( ! $username || ! $password || ! $app_key || ! $app_secret ) {
			return new WP_Error( 'membexa_bkash_credentials', __( 'bKash API credentials are missing.', 'membexa' ) );
		}

		$cache_key = 'membexa_bkash_token_' . md5( self::api_base() . $username . $app_key );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			self::api_base() . '/tokenized/checkout/token/grant',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'username'     => $username,
					'password'     => $password,
				),
				'body'    => wp_json_encode(
					array(
						'app_key'    => $app_key,
						'app_secret' => $app_secret,
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 || empty( $data['id_token'] ) || ( isset( $data['statusCode'] ) && '0000' !== (string) $data['statusCode'] ) ) {
			$message = isset( $data['statusMessage'] ) ? sanitize_text_field( $data['statusMessage'] ) : __( 'bKash authentication failed.', 'membexa' );
			return new WP_Error( 'membexa_bkash_auth', $message );
		}

		$ttl = isset( $data['expires_in'] ) ? max( 300, absint( $data['expires_in'] ) - 300 ) : 3000;
		set_transient( $cache_key, sanitize_text_field( $data['id_token'] ), $ttl );
		return sanitize_text_field( $data['id_token'] );
	}

	/**
	 * Send a tokenized bKash API request.
	 *
	 * @param string $endpoint Relative API endpoint.
	 * @param array  $body     JSON body.
	 * @param string $token    Grant token.
	 * @return array|WP_Error
	 */
	private static function api_request( $endpoint, $body, $token ) {
		$response = wp_remote_post(
			self::api_base() . $endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'authorization' => $token,
					'x-app-key'     => Settings::bkash_app_key(),
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['statusMessage'] ) ? sanitize_text_field( $data['statusMessage'] ) : __( 'bKash rejected the request.', 'membexa' );
			return new WP_Error( 'membexa_bkash_api', $message );
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Get the bKash API base URL.
	 *
	 * @return string
	 */
	private static function api_base() {
		if ( defined( 'MEMBEXA_BKASH_API_BASE' ) && MEMBEXA_BKASH_API_BASE ) {
			return untrailingslashit( esc_url_raw( MEMBEXA_BKASH_API_BASE ) );
		}
		$settings = Settings::payments();
		return ! empty( $settings['bkash_sandbox'] ) ? self::SANDBOX_API : self::LIVE_API;
	}

	/**
	 * Validate a hosted bKash payment URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_bkash_url( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return 'bkash.com' === $host || ( strlen( $host ) > 10 && '.bkash.com' === substr( $host, -10 ) );
	}

	/**
	 * Account page URL.
	 *
	 * @return string
	 */
	private static function account_url() {
		$general = Settings::general();
		return $general['account_page_id'] ? get_permalink( $general['account_page_id'] ) : home_url( '/' );
	}

	/**
	 * Redirect back to account page with a notice.
	 *
	 * @param string $notice Notice code.
	 * @return void
	 */
	private static function redirect_account( $notice ) {
		wp_safe_redirect( add_query_arg( 'membexa_notice', sanitize_key( $notice ), self::account_url() ) );
		exit;
	}
}
