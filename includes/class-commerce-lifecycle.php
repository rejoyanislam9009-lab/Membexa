<?php
/**
 * WooCommerce entitlement lifecycle hardening.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps order grants independent and extends category rules through ancestors.
 */
final class Commerce_Lifecycle {
	/** Register lifecycle hooks. */
	public function hooks() {
		if ( ! Account::woocommerce_active() ) {
			return;
		}

		add_filter( 'membexa_commerce_grant_plans', array( $this, 'inherit_category_plans' ), 10, 2 );
		add_filter( 'membexa_commerce_required_plans', array( $this, 'inherit_category_plans' ), 10, 2 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'grant_independent_order_entitlements' ), 5 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'grant_independent_order_entitlements' ), 5 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'revoke_failed_order_entitlements' ), 10 );
		add_action( 'template_redirect', array( $this, 'protect_parent_category_view' ), 15 );
	}

	/**
	 * Include parent product-category plan rules.
	 *
	 * @param array $plans      Plans already resolved by Commerce.
	 * @param int   $product_id WooCommerce product or variation ID.
	 * @return array
	 */
	public function inherit_category_plans( $plans, $product_id ) {
		$product_id = absint( $product_id );
		$parent_id  = wp_get_post_parent_id( $product_id );
		if ( $parent_id && 'product' === get_post_type( $parent_id ) ) {
			$product_id = $parent_id;
		}
		if ( ! $product_id ) {
			return $this->clean_ids( $plans );
		}

		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) ) {
			return $this->clean_ids( $plans );
		}

		$meta_key = current_filter() === 'membexa_commerce_grant_plans' ? Commerce::META_GRANT_PLANS : Commerce::META_REQUIRE_PLANS;
		$term_ids = array_map( 'absint', $terms );
		foreach ( $term_ids as $term_id ) {
			$term_ids = array_merge( $term_ids, array_map( 'absint', get_ancestors( $term_id, 'product_cat', 'taxonomy' ) ) );
		}
		foreach ( $this->clean_ids( $term_ids ) as $term_id ) {
			$plans = array_merge( (array) $plans, (array) get_term_meta( $term_id, $meta_key, true ) );
		}
		return $this->clean_ids( $plans );
	}

	/**
	 * Grant an order-owned membership record even when another active record already grants the same plan.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function grant_independent_order_entitlements( $order_id ) {
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			return;
		}
		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_user_id() ) {
			return;
		}

		$user_id = absint( $order->get_user_id() );
		$stored  = $this->clean_ids( $order->get_meta( Commerce::ORDER_GRANTED_META, true ) );
		$created = $stored;
		foreach ( $this->order_plan_ids( $order ) as $plan_id ) {
			$external_id = 'wc-order:' . absint( $order_id ) . ':plan:' . absint( $plan_id );
			if ( Subscriptions::get_by_external_id( $external_id ) ) {
				continue;
			}
			$subscription_id = Subscriptions::create( $user_id, $plan_id, 'active', 'woocommerce', $external_id );
			if ( $subscription_id ) {
				$created[] = $subscription_id;
				do_action( 'membexa_woocommerce_membership_granted', $subscription_id, $plan_id, absint( $order_id ), $user_id );
			}
		}

		$created = $this->clean_ids( $created );
		if ( $created !== $stored ) {
			$order->update_meta_data( Commerce::ORDER_GRANTED_META, $created );
			$order->save();
		}
	}

	/** Revoke grants when a previously qualifying order enters failed status. */
	public function revoke_failed_order_entitlements( $order_id ) {
		$settings = Settings::integrations();
		if ( empty( $settings['revoke_on_refund'] ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $this->clean_ids( $order->get_meta( Commerce::ORDER_GRANTED_META, true ) ) as $subscription_id ) {
			if ( Subscriptions::cancel_local( $subscription_id ) ) {
				do_action( 'membexa_woocommerce_membership_revoked', $subscription_id, absint( $order_id ) );
			}
		}
	}

	/** Protect a product-category archive when a parent category carries a Membexa view rule. */
	public function protect_parent_category_view() {
		if ( is_admin() || wp_doing_ajax() || current_user_can( 'manage_woocommerce' ) || ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return;
		}
		$term = get_queried_object();
		if ( ! $term || is_wp_error( $term ) || empty( $term->term_id ) ) {
			return;
		}

		$term_ids = array_merge( array( absint( $term->term_id ) ), array_map( 'absint', get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) );
		$required = array();
		$restrict = false;
		foreach ( $this->clean_ids( $term_ids ) as $term_id ) {
			$required = array_merge( $required, (array) get_term_meta( $term_id, Commerce::META_REQUIRE_PLANS, true ) );
			$restrict = $restrict || (bool) get_term_meta( $term_id, Commerce::META_RESTRICT_VIEW, true );
		}
		if ( ! $restrict ) {
			return;
		}

		$required = $this->clean_ids( $required );
		$allowed  = ! empty( $required ) && is_user_logged_in() && Subscriptions::user_has_plan( get_current_user_id(), $required );
		if ( $allowed ) {
			return;
		}

		$term_link = get_term_link( $term );
		$return    = is_wp_error( $term_link ) ? '' : $term_link;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Account::login_url( $return ) );
			exit;
		}
		$general = Settings::general();
		$target  = $general['pricing_page_id'] ? get_permalink( $general['pricing_page_id'] ) : Account::account_url();
		wp_safe_redirect( $target );
		exit;
	}

	/** Get all plan grants represented by an order's products. */
	private function order_plan_ids( $order ) {
		$plans = array();
		foreach ( $order->get_items() as $item ) {
			$product_id = method_exists( $item, 'get_variation_id' ) && $item->get_variation_id() ? absint( $item->get_variation_id() ) : absint( $item->get_product_id() );
			$plans      = array_merge( $plans, Commerce::grant_plans_for_product( $product_id ) );
		}
		return $this->clean_ids( $plans );
	}

	/** Normalize integer IDs. */
	private function clean_ids( $ids ) {
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	}
}
