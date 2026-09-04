<?php
/**
 * Payment gateway coordinator.
 *
 * @package Membexa
 */

namespace Membexa;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a single interface for supported payment gateways.
 */
final class Gateways {
	/**
	 * Gateway labels.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'stripe' => __( 'Stripe', 'membexa' ),
			'paypal' => __( 'PayPal', 'membexa' ),
			'bkash'  => __( 'bKash', 'membexa' ),
		);
	}

	/**
	 * Return gateways that can process a specific paid plan.
	 *
	 * @param array $plan Membership plan data.
	 * @return array
	 */
	public static function available_for_plan( $plan ) {
		$available = array();
		$labels    = self::labels();

		if ( ! is_array( $plan ) || 'free' === $plan['billing'] || 0.0 === (float) $plan['price'] ) {
			return $available;
		}

		if ( Stripe::enabled() && ! empty( $plan['stripe_price_id'] ) ) {
			$available['stripe'] = $labels['stripe'];
		}

		$paypal_recurring = self::is_recurring( $plan['billing'] );
		if ( PayPal::enabled() && ( ! $paypal_recurring || ! empty( $plan['paypal_plan_id'] ) ) ) {
			$available['paypal'] = $labels['paypal'];
		}

		if (
			Bkash::enabled()
			&& 'BDT' === strtoupper( (string) $plan['currency'] )
			&& in_array( $plan['billing'], array( 'one_time', 'lifetime' ), true )
		) {
			$available['bkash'] = $labels['bkash'];
		}

		return $available;
	}

	/**
	 * Return enabled gateway labels, regardless of plan compatibility.
	 *
	 * @return array
	 */
	public static function enabled() {
		$labels  = self::labels();
		$enabled = array();

		if ( Stripe::enabled() ) {
			$enabled['stripe'] = $labels['stripe'];
		}
		if ( PayPal::enabled() ) {
			$enabled['paypal'] = $labels['paypal'];
		}
		if ( Bkash::enabled() ) {
			$enabled['bkash'] = $labels['bkash'];
		}
		return $enabled;
	}

	/**
	 * Start checkout using an allowed gateway.
	 *
	 * @param string $gateway Gateway key.
	 * @param int    $user_id WordPress user ID.
	 * @param int    $plan_id Membership plan ID.
	 * @return string|WP_Error Hosted checkout URL or error.
	 */
	public static function start_checkout( $gateway, $user_id, $plan_id ) {
		$plan = Plan::get( $plan_id );
		if ( ! $plan ) {
			return new WP_Error( 'membexa_invalid_plan', __( 'The selected membership plan is not available.', 'membexa' ) );
		}

		$available = self::available_for_plan( $plan );
		$gateway   = sanitize_key( $gateway );
		if ( ! isset( $available[ $gateway ] ) ) {
			return new WP_Error( 'membexa_gateway_unavailable', __( 'The selected payment method is not available for this plan.', 'membexa' ) );
		}

		switch ( $gateway ) {
			case 'paypal':
				return PayPal::start_checkout( $user_id, $plan_id );
			case 'bkash':
				return Bkash::start_checkout( $user_id, $plan_id );
			case 'stripe':
			default:
				return Stripe::start_checkout( $user_id, $plan_id );
		}
	}

	/**
	 * Cancel a member subscription through its owning gateway when needed.
	 *
	 * @param object $subscription Local subscription record.
	 * @return string|WP_Error Result code: scheduled or cancelled.
	 */
	public static function cancel( $subscription ) {
		if ( ! $subscription ) {
			return new WP_Error( 'membexa_invalid_subscription', __( 'The selected subscription could not be found.', 'membexa' ) );
		}

		$plan = Plan::get( $subscription->plan_id );
		if ( 'stripe' === $subscription->gateway && $plan && self::is_recurring( $plan['billing'] ) ) {
			$result = Stripe::cancel_at_period_end( $subscription );
			return is_wp_error( $result ) ? $result : 'scheduled';
		}

		if ( 'paypal' === $subscription->gateway && $plan && self::is_recurring( $plan['billing'] ) ) {
			$result = PayPal::cancel_subscription( $subscription );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		Subscriptions::cancel_local( $subscription->id );
		return 'cancelled';
	}

	/**
	 * Determine whether a billing model is recurring.
	 *
	 * @param string $billing Billing model.
	 * @return bool
	 */
	public static function is_recurring( $billing ) {
		return in_array( $billing, array( 'monthly', 'yearly' ), true );
	}
}
