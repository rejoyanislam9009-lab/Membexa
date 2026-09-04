<?php
/**
 * Stripe Checkout gateway.
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

final class Stripe {
	const API_BASE = 'https://api.stripe.com/v1/';

	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'membexa/v1',
			'/stripe/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function enabled() {
		$settings = Settings::payments();
		return ! empty( $settings['stripe_enabled'] ) && Settings::stripe_secret_key();
	}

	public static function start_checkout( $user_id, $plan_id ) {
		$plan = Plan::get( $plan_id );
		$user = get_userdata( $user_id );
		if ( ! $plan || ! $user ) {
			return new WP_Error( 'membexa_invalid_checkout', __( 'The selected membership plan could not be loaded.', 'membexa' ) );
		}
		if ( ! self::enabled() ) {
			return new WP_Error( 'membexa_stripe_disabled', __( 'Online payments are not configured yet.', 'membexa' ) );
		}
		if ( empty( $plan['stripe_price_id'] ) || 0 >= $plan['price'] ) {
			return new WP_Error( 'membexa_stripe_price', __( 'This paid plan does not have a valid Stripe Price ID.', 'membexa' ) );
		}

		$mode            = in_array( $plan['billing'], array( 'monthly', 'yearly' ), true ) ? 'subscription' : 'payment';
		$subscription_id = Subscriptions::create( $user_id, $plan_id, 'pending', 'stripe', '' );
		if ( ! $subscription_id ) {
			return new WP_Error( 'membexa_subscription_create', __( 'The subscription record could not be created.', 'membexa' ) );
		}

		$general     = Settings::general();
		$account_url = $general['account_page_id'] ? get_permalink( $general['account_page_id'] ) : home_url( '/' );
		$body        = array(
			'mode'                            => $mode,
			'client_reference_id'             => (string) $user_id,
			'customer_email'                  => $user->user_email,
			'line_items[0][price]'            => $plan['stripe_price_id'],
			'line_items[0][quantity]'         => '1',
			'metadata[local_subscription_id]' => (string) $subscription_id,
			'metadata[plan_id]'               => (string) $plan_id,
			'metadata[user_id]'               => (string) $user_id,
			'success_url'                     => add_query_arg( 'membexa_notice', 'payment_success', $account_url ),
			'cancel_url'                      => add_query_arg( 'membexa_notice', 'payment_cancelled', $account_url ),
		);
		if ( 'subscription' === $mode && $plan['trial_days'] > 0 ) {
			$body['subscription_data[trial_period_days]'] = (string) $plan['trial_days'];
		}

		$response = self::api_request( 'checkout/sessions', $body );
		if ( is_wp_error( $response ) ) {
			Subscriptions::cancel_local( $subscription_id );
			return $response;
		}
		if ( empty( $response['url'] ) || empty( $response['id'] ) ) {
			Subscriptions::cancel_local( $subscription_id );
			return new WP_Error( 'membexa_stripe_response', __( 'Stripe returned an incomplete Checkout response.', 'membexa' ) );
		}
		$host = wp_parse_url( $response['url'], PHP_URL_HOST );
		if ( 'checkout.stripe.com' !== $host ) {
			Subscriptions::cancel_local( $subscription_id );
			return new WP_Error( 'membexa_stripe_url', __( 'Stripe returned an unexpected Checkout URL.', 'membexa' ) );
		}

		self::set_external_id( $subscription_id, $response['id'] );
		return esc_url_raw( $response['url'] );
	}

	public static function cancel_at_period_end( $subscription ) {
		if ( ! $subscription || 'stripe' !== $subscription->gateway || 0 !== strpos( $subscription->gateway_external_id, 'sub_' ) ) {
			return new WP_Error( 'membexa_not_recurring', __( 'This subscription cannot be scheduled for Stripe cancellation.', 'membexa' ) );
		}
		$response = self::api_request( 'subscriptions/' . rawurlencode( $subscription->gateway_external_id ), array( 'cancel_at_period_end' => 'true' ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		Subscriptions::set_cancel_at_period_end( $subscription->id, true );
		return true;
	}

	private static function api_request( $endpoint, $body ) {
		$secret = Settings::stripe_secret_key();
		if ( ! $secret ) {
			return new WP_Error( 'membexa_stripe_key', __( 'Stripe secret key is missing.', 'membexa' ) );
		}
		$response = wp_remote_post(
			self::API_BASE . ltrim( $endpoint, '/' ),
			array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . $secret ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['error']['message'] ) ? sanitize_text_field( $data['error']['message'] ) : __( 'Stripe rejected the request.', 'membexa' );
			return new WP_Error( 'membexa_stripe_api', $message );
		}
		return is_array( $data ) ? $data : array();
	}

	public function webhook( WP_REST_Request $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe-signature' );
		$secret    = Settings::stripe_webhook_secret();
		if ( ! $secret || ! $this->verify_signature( $payload, $signature, $secret ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
		}
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_event' ), 400 );
		}
		$event_key = 'membexa_stripe_evt_' . md5( $event['id'] );
		if ( get_transient( $event_key ) ) {
			return new WP_REST_Response( array( 'received' => true, 'duplicate' => true ), 200 );
		}

		$object = isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ? $event['data']['object'] : array();
		switch ( $event['type'] ) {
			case 'checkout.session.completed':
				$this->handle_checkout_completed( $object );
				break;
			case 'checkout.session.async_payment_succeeded':
				$this->handle_async_checkout( $object, true );
				break;
			case 'checkout.session.async_payment_failed':
				$this->handle_async_checkout( $object, false );
				break;
			case 'customer.subscription.updated':
				$this->handle_subscription_updated( $object );
				break;
			case 'customer.subscription.deleted':
				if ( ! empty( $object['id'] ) ) {
					Subscriptions::update_status_by_external_id( $object['id'], 'canceled' );
				}
				break;
			case 'invoice.paid':
				$this->handle_invoice( $object, true );
				break;
			case 'invoice.payment_failed':
				$this->handle_invoice( $object, false );
				break;
		}
		set_transient( $event_key, 1, DAY_IN_SECONDS );
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	private function handle_checkout_completed( $session ) {
		$local_subscription_id = $this->local_subscription_id( $session );
		if ( ! $local_subscription_id ) {
			return;
		}

		$external_id = ! empty( $session['subscription'] ) ? $session['subscription'] : ( ! empty( $session['payment_intent'] ) ? $session['payment_intent'] : $session['id'] );
		if ( $external_id ) {
			self::set_external_id( $local_subscription_id, $external_id );
		}

		$payment_status = isset( $session['payment_status'] ) ? sanitize_key( $session['payment_status'] ) : '';
		$is_paid        = in_array( $payment_status, array( 'paid', 'no_payment_required' ), true );
		if ( ! $is_paid ) {
			return;
		}

		Subscriptions::activate( $local_subscription_id, $external_id );
		$subscription = Subscriptions::get( $local_subscription_id );
		Subscriptions::log_transaction(
			array(
				'user_id'         => $subscription ? $subscription->user_id : 0,
				'subscription_id' => $local_subscription_id,
				'gateway'         => 'stripe',
				'external_id'     => ! empty( $session['payment_intent'] ) ? $session['payment_intent'] : $session['id'],
				'type'            => 'checkout',
				'amount'          => isset( $session['amount_total'] ) ? ( (float) $session['amount_total'] / 100 ) : 0,
				'currency'        => isset( $session['currency'] ) ? $session['currency'] : '',
				'status'          => $payment_status,
			)
		);
	}

	private function handle_async_checkout( $session, $succeeded ) {
		$local_subscription_id = $this->local_subscription_id( $session );
		if ( ! $local_subscription_id ) {
			return;
		}
		$external_id = ! empty( $session['payment_intent'] ) ? $session['payment_intent'] : ( ! empty( $session['id'] ) ? $session['id'] : '' );
		$subscription = Subscriptions::get( $local_subscription_id );
		if ( $succeeded ) {
			Subscriptions::activate( $local_subscription_id, $external_id );
		} else {
			Subscriptions::cancel_local( $local_subscription_id );
		}
		Subscriptions::log_transaction(
			array(
				'user_id'         => $subscription ? $subscription->user_id : 0,
				'subscription_id' => $local_subscription_id,
				'gateway'         => 'stripe',
				'external_id'     => $external_id,
				'type'            => $succeeded ? 'async_payment' : 'async_payment_failed',
				'amount'          => isset( $session['amount_total'] ) ? ( (float) $session['amount_total'] / 100 ) : 0,
				'currency'        => isset( $session['currency'] ) ? $session['currency'] : '',
				'status'          => $succeeded ? 'paid' : 'failed',
			)
		);
	}

	private function handle_subscription_updated( $subscription ) {
		if ( empty( $subscription['id'] ) ) {
			return;
		}
		$map = array(
			'active'     => 'active',
			'trialing'   => 'trialing',
			'past_due'   => 'past_due',
			'unpaid'     => 'past_due',
			'canceled'   => 'canceled',
			'incomplete' => 'pending',
		);
		$status = isset( $subscription['status'], $map[ $subscription['status'] ] ) ? $map[ $subscription['status'] ] : 'pending';
		Subscriptions::update_status_by_external_id(
			$subscription['id'],
			$status,
			isset( $subscription['current_period_end'] ) ? absint( $subscription['current_period_end'] ) : null,
			! empty( $subscription['cancel_at_period_end'] )
		);
	}

	private function handle_invoice( $invoice, $paid ) {
		$external_subscription_id = $this->invoice_subscription_id( $invoice );
		if ( ! $external_subscription_id ) {
			return;
		}
		$subscription = Subscriptions::get_by_external_id( $external_subscription_id );
		if ( ! $subscription ) {
			return;
		}
		Subscriptions::update_status_by_external_id( $external_subscription_id, $paid ? 'active' : 'past_due' );
		Subscriptions::log_transaction(
			array(
				'user_id'         => $subscription->user_id,
				'subscription_id' => $subscription->id,
				'gateway'         => 'stripe',
				'external_id'     => isset( $invoice['id'] ) ? $invoice['id'] : '',
				'type'            => $paid ? 'invoice_paid' : 'invoice_failed',
				'amount'          => isset( $invoice[ $paid ? 'amount_paid' : 'amount_due' ] ) ? ( (float) $invoice[ $paid ? 'amount_paid' : 'amount_due' ] / 100 ) : 0,
				'currency'        => isset( $invoice['currency'] ) ? $invoice['currency'] : '',
				'status'          => $paid ? 'paid' : 'failed',
			)
		);
	}

	private function local_subscription_id( $session ) {
		$metadata = isset( $session['metadata'] ) && is_array( $session['metadata'] ) ? $session['metadata'] : array();
		return isset( $metadata['local_subscription_id'] ) ? absint( $metadata['local_subscription_id'] ) : 0;
	}

	private function invoice_subscription_id( $invoice ) {
		if ( ! empty( $invoice['subscription'] ) && is_string( $invoice['subscription'] ) ) {
			return sanitize_text_field( $invoice['subscription'] );
		}
		if ( ! empty( $invoice['parent']['subscription_details']['subscription'] ) && is_string( $invoice['parent']['subscription_details']['subscription'] ) ) {
			return sanitize_text_field( $invoice['parent']['subscription_details']['subscription'] );
		}
		return '';
	}

	private static function set_external_id( $subscription_id, $external_id ) {
		global $wpdb;
		return false !== $wpdb->update(
			DB::subscriptions_table(),
			array(
				'gateway_external_id' => sanitize_text_field( $external_id ),
				'updated_at'          => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $subscription_id ) )
		);
	}

	private function verify_signature( $payload, $header, $secret ) {
		if ( ! $payload || ! $header ) {
			return false;
		}
		$timestamp  = 0;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = array_map( 'trim', explode( '=', $part, 2 ) );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = absint( $pair[1] );
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}
		if ( ! $timestamp || abs( time() - $timestamp ) > 300 || empty( $signatures ) ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}
}
