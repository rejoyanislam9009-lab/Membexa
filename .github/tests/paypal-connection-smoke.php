<?php
/**
 * PayPal connection verification regression smoke tests.
 *
 * @package Membexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$url_method = new ReflectionMethod( '\\Membexa\\PayPal_Connection', 'verify_action_url' );
$url_method->setAccessible( true );
$url = $url_method->invoke( null );

$query = wp_parse_url( $url, PHP_URL_QUERY );
parse_str( (string) $query, $args );

if ( empty( $args['action'] ) || 'membexa_verify_paypal' !== $args['action'] ) {
	fwrite( STDERR, "PayPal verification action is missing from the generated URL.\n" );
	exit( 1 );
}

if ( empty( $args['_wpnonce'] ) || ! wp_verify_nonce( $args['_wpnonce'], 'membexa_verify_paypal' ) ) {
	fwrite( STDERR, "PayPal verification nonce is missing or invalid in the generated URL.\n" );
	exit( 1 );
}

if ( false !== strpos( $url, '&amp;' ) || isset( $args['amp;_wpnonce'] ) ) {
	fwrite( STDERR, "PayPal verification URL contains an HTML-escaped query separator.\n" );
	exit( 1 );
}

$missing_method = new ReflectionMethod( '\\Membexa\\PayPal_Connection', 'missing_required_events' );
$missing_method->setAccessible( true );

$wildcard_missing = $missing_method->invoke( null, array( '*' ) );
if ( ! empty( $wildcard_missing ) ) {
	fwrite( STDERR, "PayPal wildcard webhook subscription was incorrectly reported as missing required events.\n" );
	exit( 1 );
}

$explicit_events = \Membexa\PayPal_Connection::REQUIRED_EVENTS;
$explicit_missing = $missing_method->invoke( null, $explicit_events );
if ( ! empty( $explicit_missing ) ) {
	fwrite( STDERR, "Explicit PayPal required events were incorrectly reported as missing.\n" );
	exit( 1 );
}

$partial_events = array( 'BILLING.SUBSCRIPTION.ACTIVATED' );
$partial_missing = $missing_method->invoke( null, $partial_events );
if ( count( $partial_missing ) !== count( \Membexa\PayPal_Connection::REQUIRED_EVENTS ) - 1 ) {
	fwrite( STDERR, "Partial PayPal event subscriptions are not being detected correctly.\n" );
	exit( 1 );
}

echo "Membexa PayPal connection smoke tests passed.\n";
