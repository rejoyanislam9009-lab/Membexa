<?php
/**
 * WooCommerce integration smoke test for GitHub Actions.
 *
 * @package Membexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( ! class_exists( 'WooCommerce' ) || ! class_exists( '\Membexa\Commerce' ) ) {
	fwrite( STDERR, "WooCommerce or Membexa Commerce is unavailable.\n" );
	exit( 1 );
}

if ( 'woocommerce' !== \Membexa\Account::mode() ) {
	fwrite( STDERR, "Smart Account did not resolve to WooCommerce while WooCommerce is active.\n" );
	exit( 1 );
}

$plan_id = wp_insert_post(
	array(
		'post_type'   => \Membexa\Plan::POST_TYPE,
		'post_status' => 'publish',
		'post_title'  => 'CI Membership',
	)
);
if ( is_wp_error( $plan_id ) || ! $plan_id ) {
	fwrite( STDERR, "Could not create membership plan.\n" );
	exit( 1 );
}
update_post_meta( $plan_id, '_membexa_price', '0.00' );
update_post_meta( $plan_id, '_membexa_currency', 'USD' );
update_post_meta( $plan_id, '_membexa_billing', 'free' );

$product = new WC_Product_Simple();
$product->set_name( 'Membexa CI Product' );
$product->set_status( 'publish' );
$product->set_regular_price( '10.00' );
$product_id = $product->save();
if ( ! $product_id ) {
	fwrite( STDERR, "Could not create WooCommerce product.\n" );
	exit( 1 );
}
update_post_meta( $product_id, \Membexa\Commerce::META_GRANT_PLANS, array( $plan_id ) );
update_post_meta( $product_id, \Membexa\Commerce::META_REQUIRE_PLANS, array( $plan_id ) );
update_post_meta( $product_id, \Membexa\Commerce::META_RESTRICT_PURCHASE, 1 );

$grant_plans = \Membexa\Commerce::grant_plans_for_product( $product_id );
if ( array( $plan_id ) !== $grant_plans ) {
	fwrite( STDERR, "Product grant rule resolution failed.\n" );
	exit( 1 );
}

$user_id = wp_create_user( 'membexa_ci_member', wp_generate_password( 24 ), 'membexa-ci@example.test' );
if ( is_wp_error( $user_id ) ) {
	fwrite( STDERR, "Could not create customer.\n" );
	exit( 1 );
}

wp_set_current_user( 0 );
if ( \Membexa\Commerce::user_can_access_product( $product_id ) ) {
	fwrite( STDERR, "Restricted product unexpectedly allowed a guest.\n" );
	exit( 1 );
}

$order = wc_create_order( array( 'customer_id' => $user_id ) );
if ( is_wp_error( $order ) ) {
	fwrite( STDERR, "Could not create WooCommerce order.\n" );
	exit( 1 );
}
$order->add_product( wc_get_product( $product_id ), 1 );
$order->calculate_totals();
$order->update_status( 'processing' );

if ( ! \Membexa\Subscriptions::user_has_plan( $user_id, array( $plan_id ) ) ) {
	fwrite( STDERR, "Paid-order membership grant failed.\n" );
	exit( 1 );
}

wp_set_current_user( $user_id );
if ( ! \Membexa\Commerce::user_can_access_product( $product_id ) ) {
	fwrite( STDERR, "Granted member cannot access restricted product.\n" );
	exit( 1 );
}

$order->update_status( 'refunded' );
if ( \Membexa\Subscriptions::user_has_plan( $user_id, array( $plan_id ) ) ) {
	fwrite( STDERR, "Refunded order did not revoke its granted membership.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Membexa WooCommerce smoke test passed.\n" );
