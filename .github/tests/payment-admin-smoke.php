<?php
/**
 * Payments hub smoke test.
 *
 * @package Membexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$hub = new \Membexa\Payment_Hub_Admin();
$hub->hooks();

if ( ! has_action( 'admin_post_membexa_install_payment_addon', array( $hub, 'install_payment_addon' ) ) ) {
	fwrite( STDERR, "Payments hub install action is not registered.\n" );
	exit( 1 );
}
if ( ! has_action( 'admin_post_membexa_activate_payment_addon', array( $hub, 'activate_payment_addon' ) ) ) {
	fwrite( STDERR, "Payments hub activation action is not registered.\n" );
	exit( 1 );
}

$detect_method = new ReflectionMethod( \Membexa\Payment_Hub_Admin::class, 'is_payment_plugin_data' );
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

$metadata_method = new ReflectionMethod( \Membexa\Payment_Hub_Admin::class, 'installed_payment_plugins_from_metadata' );
$metadata_method->setAccessible( true );
$metadata = $metadata_method->invoke(
	$hub,
	array(
		'example-woocommerce-gateway/example.php' => array(
			'Name'            => 'Example WooCommerce Payment Gateway',
			'Description'     => 'Adds an example payment gateway to WooCommerce.',
			'TextDomain'      => 'example-woocommerce-gateway',
			'RequiresPlugins' => 'woocommerce',
		),
	)
);
if ( ! isset( $metadata['example-woocommerce-gateway/example.php'] ) ) {
	fwrite( STDERR, "Metadata-only payment add-on listing failed.\n" );
	exit( 1 );
}

$slug_method = new ReflectionMethod( \Membexa\Payment_Hub_Admin::class, 'plugin_file_for_slug' );
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

$row_method = new ReflectionMethod( \Membexa\Payment_Hub_Admin::class, 'gateway_row' );
$row_method->setAccessible( true );
if ( false !== $row_method->invoke( $hub, null ) ) {
	fwrite( STDERR, "Malformed gateway objects are not being rejected safely.\n" );
	exit( 1 );
}

if ( class_exists( '\\WooCommerce' ) && function_exists( 'WC' ) && WC() && WC()->payment_gateways() ) {
	$gateways = WC()->payment_gateways()->payment_gateways();
	if ( empty( $gateways ) ) {
		fwrite( STDERR, "WooCommerce did not register its built-in gateways.\n" );
		exit( 1 );
	}
	$row = $row_method->invoke( $hub, reset( $gateways ) );
	if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['source'] ) ) {
		fwrite( STDERR, "Safe WooCommerce gateway row generation failed.\n" );
		exit( 1 );
	}
}

echo "Membexa fault-tolerant Payments hub smoke test passed.\n";
