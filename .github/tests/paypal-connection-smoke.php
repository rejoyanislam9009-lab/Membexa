<?php
/**
 * PayPal connection verification URL regression smoke test.
 *
 * @package Membexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$method = new ReflectionMethod( '\\Membexa\\PayPal_Connection', 'verify_action_url' );
$method->setAccessible( true );
$url = $method->invoke( null );

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

echo "Membexa PayPal verification nonce smoke test passed.\n";
