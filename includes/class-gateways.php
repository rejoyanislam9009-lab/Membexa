<?php
/**
 * Modular payment gateway bridge.
 *
 * @package Membexa
 */

namespace Membexa;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delegates paid membership checkout to WooCommerce and its installed gateways.
 *
 * Membexa intentionally does not own payment credentials. Stripe, PayPal, bKash,
 * or any other payment method is installed/configured as a WooCommerce gateway.
 */
final class Gateways {
	/** Return enabled WooCommerce payment gateways. */
	public static function enabled() {
		if ( ! self::woocommerce_ready() || ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}
		$available = array();
		foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $id => $gateway ) {
			$available[ sanitize_key( $id ) ] = wp_strip_all_tags( $gateway->get_title() );
		}
		return $available;
	}

	/** Paid plans use WooCommerce checkout, so compatibility is product based. */
	public static function available_for_plan( $plan ) {
		if ( ! is_array( $plan ) || 'free' === $plan['billing'] || 0.0 === (float) $plan['price'] ) {
			return array();
		}
		if ( ! self::woocommerce_ready() || empty( $plan['woocommerce_product_id'] ) || ! wc_get_product( $plan['woocommerce_product_id'] ) ) {
			return array();
		}
		return self::enabled();
	}

	/**
	 * Start checkout by placing the plan's linked WooCommerce product in the cart.
	 *
	 * @param string $gateway Ignored; gateway selection happens at WooCommerce checkout.
	 * @param int    $user_id WordPress user ID.
	 * @param int    $plan_id Membership plan ID.
	 * @return string|WP_Error
	 */
	public static function start_checkout( $gateway, $user_id, $plan_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$plan = Plan::get( $plan_id );
		if ( ! $plan ) {
			return new WP_Error( 'membexa_invalid_plan', __( 'The selected membership plan is not available.', 'membexa' ) );
		}
		if ( ! self::woocommerce_ready() ) {
			return new WP_Error( 'membexa_woocommerce_required', __( 'WooCommerce must be active to process paid Membexa plans.', 'membexa' ) );
		}
		$product_id = absint( $plan['woocommerce_product_id'] );
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product || ! $product->is_purchasable() ) {
			return new WP_Error( 'membexa_payment_product_missing', __( 'This plan is not linked to a purchasable WooCommerce product.', 'membexa' ) );
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return new WP_Error( 'membexa_cart_unavailable', __( 'WooCommerce cart is not available.', 'membexa' ) );
		}

		// Keep checkout deterministic: the selected membership product is the purchase being started.
		WC()->cart->empty_cart();
		$added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'membexa_plan_id' => absint( $plan_id ) ) );
		if ( ! $added ) {
			return new WP_Error( 'membexa_cart_add_failed', __( 'The membership product could not be added to the WooCommerce cart.', 'membexa' ) );
		}
		return wc_get_checkout_url();
	}

	/** Cancel an entitlement through WooCommerce Subscriptions when applicable. */
	public static function cancel( $subscription ) {
		if ( ! $subscription ) {
			return new WP_Error( 'membexa_invalid_subscription', __( 'The selected subscription could not be found.', 'membexa' ) );
		}
		if ( 'woocommerce_subscription' === $subscription->gateway ) {
			return Commerce::cancel_woocommerce_subscription( $subscription );
		}
		Subscriptions::cancel_local( $subscription->id );
		return 'cancelled';
	}

	/** Determine whether a billing model is recurring. */
	public static function is_recurring( $billing ) {
		return in_array( $billing, array( 'monthly', 'yearly' ), true );
	}

	/** Whether WooCommerce is available for paid checkout. */
	public static function woocommerce_ready() {
		return Account::woocommerce_active() && function_exists( 'wc_get_product' ) && function_exists( 'wc_get_checkout_url' );
	}
}
