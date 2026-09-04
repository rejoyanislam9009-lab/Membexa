<?php
/**
 * Modular payment gateway registry.
 *
 * @package Membexa
 */

namespace Membexa;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates payment gateway add-ons without bundling gateway configuration into core.
 */
final class Gateways {
	/**
	 * Registered gateways.
	 *
	 * @var array
	 */
	private static $registered = array();

	/**
	 * Register a payment gateway add-on.
	 *
	 * @param string $key  Stable gateway key.
	 * @param array  $args Gateway callbacks and metadata.
	 * @return bool
	 */
	public static function register( $key, $args ) {
		$key  = sanitize_key( $key );
		$args = is_array( $args ) ? $args : array();
		if ( ! $key || empty( $args['label'] ) || empty( $args['checkout_callback'] ) || ! is_callable( $args['checkout_callback'] ) ) {
			return false;
		}

		self::$registered[ $key ] = wp_parse_args(
			$args,
			array(
				'label'              => $key,
				'enabled_callback'   => '__return_true',
				'available_callback' => '__return_true',
				'checkout_callback'  => null,
				'cancel_callback'    => null,
				'settings_url'       => '',
				'addon_version'      => '',
				'supports_recurring' => false,
			)
		);
		return true;
	}

	/** Return all registered gateway definitions. */
	public static function all() {
		return self::$registered;
	}

	/** Determine whether one add-on gateway is registered. */
	public static function is_registered( $key ) {
		return isset( self::$registered[ sanitize_key( $key ) ] );
	}

	/** Return registered gateway labels. */
	public static function labels() {
		$labels = array();
		foreach ( self::$registered as $key => $gateway ) {
			$labels[ $key ] = (string) $gateway['label'];
		}
		return $labels;
	}

	/**
	 * Return gateways that can process a specific paid plan.
	 *
	 * @param array $plan Membership plan data.
	 * @return array
	 */
	public static function available_for_plan( $plan ) {
		$available = array();
		if ( ! is_array( $plan ) || 'free' === $plan['billing'] || 0.0 === (float) $plan['price'] ) {
			return $available;
		}

		foreach ( self::$registered as $key => $gateway ) {
			$enabled = is_callable( $gateway['enabled_callback'] ) ? (bool) call_user_func( $gateway['enabled_callback'] ) : true;
			if ( ! $enabled ) {
				continue;
			}
			$compatible = is_callable( $gateway['available_callback'] ) ? (bool) call_user_func( $gateway['available_callback'], $plan ) : true;
			if ( $compatible ) {
				$available[ $key ] = (string) $gateway['label'];
			}
		}
		return $available;
	}

	/** Return enabled registered gateway labels, regardless of plan compatibility. */
	public static function enabled() {
		$enabled = array();
		foreach ( self::$registered as $key => $gateway ) {
			$is_enabled = is_callable( $gateway['enabled_callback'] ) ? (bool) call_user_func( $gateway['enabled_callback'] ) : true;
			if ( $is_enabled ) {
				$enabled[ $key ] = (string) $gateway['label'];
			}
		}
		return $enabled;
	}

	/**
	 * Start checkout using a registered and compatible gateway.
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

		$gateway = sanitize_key( $gateway );
		if ( ! isset( self::$registered[ $gateway ] ) ) {
			return new WP_Error( 'membexa_gateway_missing_addon', __( 'This payment method requires its Membexa gateway add-on to be installed and active.', 'membexa' ) );
		}

		$available = self::available_for_plan( $plan );
		if ( ! isset( $available[ $gateway ] ) ) {
			return new WP_Error( 'membexa_gateway_unavailable', __( 'The selected payment method is not available for this plan.', 'membexa' ) );
		}

		return call_user_func( self::$registered[ $gateway ]['checkout_callback'], $user_id, $plan_id );
	}

	/**
	 * Cancel through the owning gateway add-on when supported.
	 *
	 * @param object $subscription Local subscription record.
	 * @return string|WP_Error Result code: scheduled or cancelled.
	 */
	public static function cancel( $subscription ) {
		if ( ! $subscription ) {
			return new WP_Error( 'membexa_invalid_subscription', __( 'The selected subscription could not be found.', 'membexa' ) );
		}

		if ( 'woocommerce_subscription' === $subscription->gateway ) {
			return Commerce::cancel_woocommerce_subscription( $subscription );
		}

		$key = sanitize_key( $subscription->gateway );
		if ( isset( self::$registered[ $key ] ) && is_callable( self::$registered[ $key ]['cancel_callback'] ) ) {
			$result = call_user_func( self::$registered[ $key ]['cancel_callback'], $subscription );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( in_array( $result, array( 'scheduled', 'cancelled' ), true ) ) {
				return $result;
			}
		}

		Subscriptions::cancel_local( $subscription->id );
		return 'cancelled';
	}

	/** Determine whether a billing model is recurring. */
	public static function is_recurring( $billing ) {
		return in_array( $billing, array( 'monthly', 'yearly' ), true );
	}
}
