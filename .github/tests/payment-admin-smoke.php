<?php
/**
 * Payments hub smoke test.
 *
 * @package Membexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( ! class_exists( '\\WooCommerce' ) || ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
	fwrite( STDERR, "WooCommerce payment gateway manager is unavailable.\n" );
	exit( 1 );
}

$hub = new \Membexa\Payment_Addons_Admin();
$hub->hooks();

if ( ! has_action( 'admin_post_membexa_install_payment_addon', array( $hub, 'install_payment_addon' ) ) ) {
	fwrite( STDERR, "Payments hub install action is not registered.\n" );
	exit( 1 );
}
if ( ! has_action( 'admin_post_membexa_activate_payment_addon', array( $hub, 'activate_payment_addon' ) ) ) {
	fwrite( STDERR, "Payments hub activation action is not registered.\n" );
	exit( 1 );
}

$gateways = WC()->payment_gateways()->payment_gateways();
if ( empty( $gateways ) ) {
	fwrite( STDERR, "WooCommerce did not register its built-in gateways.\n" );
	exit( 1 );
}

$source_method = new ReflectionMethod( \Membexa\Payment_Addons_Admin::class, 'gateway_source_name' );
$source_method->setAccessible( true );
$source = $source_method->invoke( $hub, reset( $gateways ) );
if ( ! is_string( $source ) || '' === $source ) {
	fwrite( STDERR, "Payments hub could not identify a WooCommerce gateway source.\n" );
	exit( 1 );
}

$detect_method = new ReflectionMethod( \Membexa\Payment_Addons_Admin::class, 'is_payment_plugin_data' );
$detect_method->setAccessible( true );
$detected = $detect_method->invoke(
	$hub,
	'example-woocommerce-gateway/example.php',
	array(
		'Name'            => 'Example WooCommerce Payment Gateway',
		'Description'     => 'Adds an example payment gateway to WooCommerce.',
		'TextDomain'      => 'example-woocommerce-gateway',
		'RequiresPlugins' => 'woocommerce',
	)
);
if ( ! $detected ) {
	fwrite( STDERR, "Manually installed WooCommerce payment add-on detection failed.\n" );
	exit( 1 );
}

$slug_method = new ReflectionMethod( \Membexa\Payment_Addons_Admin::class, 'plugin_file_for_slug' );
$slug_method->setAccessible( true );
$plugin_file = $slug_method->invoke(
	$hub,
	'example-woocommerce-gateway',
	array(
		'example-woocommerce-gateway/example.php' => array(
			'TextDomain' => 'example-woocommerce-gateway',
		),
	)
);
if ( 'example-woocommerce-gateway/example.php' !== $plugin_file ) {
	fwrite( STDERR, "WordPress.org slug to installed plugin mapping failed.\n" );
	exit( 1 );
}

echo "Membexa Payments add-on hub smoke test passed.\n";
