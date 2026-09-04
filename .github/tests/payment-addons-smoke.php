<?php
/**
 * Modular payment add-on registry smoke test.
 *
 * @package Membexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$registered = \Membexa\Gateways::all();
foreach ( array( 'stripe', 'paypal', 'bkash' ) as $gateway ) {
	if ( empty( $registered[ $gateway ] ) ) {
		fwrite( STDERR, "Expected payment add-on was not registered: {$gateway}\n" );
		exit( 1 );
	}
}

update_option(
	'membexa_payments',
	array(
		'stripe_enabled'        => 1,
		'stripe_secret_key'     => 'sk_test_membexa_ci',
		'stripe_webhook_secret' => 'whsec_membexa_ci',
		'paypal_enabled'        => 1,
		'paypal_sandbox'        => 1,
		'paypal_client_id'      => 'membexa-ci-client',
		'paypal_client_secret'  => 'membexa-ci-secret',
		'paypal_webhook_id'     => 'WH-MEMBEXA-CI',
		'bkash_enabled'         => 1,
		'bkash_sandbox'         => 1,
		'bkash_username'        => 'membexa-ci-user',
		'bkash_password'        => 'membexa-ci-password',
		'bkash_app_key'         => 'membexa-ci-key',
		'bkash_app_secret'      => 'membexa-ci-secret',
	),
	false
);

$plan_id = wp_insert_post(
	array(
		'post_type'   => \Membexa\Plan::POST_TYPE,
		'post_status' => 'publish',
		'post_title'  => 'Modular Gateway Test',
	)
);
if ( ! $plan_id || is_wp_error( $plan_id ) ) {
	fwrite( STDERR, "Could not create payment add-on test plan.\n" );
	exit( 1 );
}

update_post_meta( $plan_id, '_membexa_price', '10.00' );
update_post_meta( $plan_id, '_membexa_currency', 'BDT' );
update_post_meta( $plan_id, '_membexa_billing', 'one_time' );
update_post_meta( $plan_id, '_membexa_stripe_price_id', 'price_membexa_ci' );
update_post_meta( $plan_id, '_membexa_paypal_plan_id', '' );

$plan      = \Membexa\Plan::get( $plan_id );
$available = \Membexa\Gateways::available_for_plan( $plan );
foreach ( array( 'stripe', 'paypal', 'bkash' ) as $gateway ) {
	if ( empty( $available[ $gateway ] ) ) {
		fwrite( STDERR, "Registered gateway was not available for compatible plan: {$gateway}\n" );
		exit( 1 );
	}
}

if ( 3 !== count( \Membexa\Gateways::enabled() ) ) {
	fwrite( STDERR, "Expected all three modular gateways to report enabled after configuration.\n" );
	exit( 1 );
}

wp_delete_post( $plan_id, true );
echo "Membexa modular payment add-on smoke test passed.\n";
