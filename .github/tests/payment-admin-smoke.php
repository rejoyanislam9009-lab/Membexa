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

$false_positive = $detect_method->invoke(
	$hub,
	'flow-store-check/flow-store-check.php',
	array(
		'Name'        => 'Flow Store Check for WooCommerce',
		'Description' => 'Checks checkout and payment flows for a WooCommerce store.',
		'TextDomain'  => 'flow-store-check',
	)
);
if ( $false_positive ) {
	fwrite( STDERR, "Non-gateway WooCommerce checkout utility was detected as a payment gateway plugin.\n" );
	exit( 1 );
}

$metadata_method = new ReflectionMethod( \Membexa\Payment_Hub_Admin::class, 'installed_payment_plugins_from_metadata' );
$metadata_method->setAccessible( true );
$metadata = $metadata_method->invoke(
	$hub,
	array(
		plugin_basename( MEMBEXA_FILE ) => array(
			'Name'        => 'Membexa – Membership & Subscriptions',
			'Description' => 'WooCommerce membership and modular payment integrations.',
			'TextDomain'  => 'membexa',
		),
		'example-woocommerce-gateway/example.php' => array(
			'Name'            => 'Example WooCommerce Payment Gateway',
			'Description'     => 'Adds an example payment gateway to WooCommerce.',
			'TextDomain'      => 'example-woocommerce-gateway',
			'RequiresPlugins' => 'woocommerce',
		),
		'flow-store-check/flow-store-check.php' => array(
			'Name'        => 'Flow Store Check for WooCommerce',
			'Description' => 'Checks checkout and payment flows for a WooCommerce store.',
			'TextDomain'  => 'flow-store-check',
		),
	)
);
if ( ! isset( $metadata['example-woocommerce-gateway/example.php'] ) ) {
	fwrite( STDERR, "Metadata-only payment gateway listing failed.\n" );
	exit( 1 );
}
if ( isset( $metadata[ plugin_basename( MEMBEXA_FILE ) ] ) || isset( $metadata['flow-store-check/flow-store-check.php'] ) ) {
	fwrite( STDERR, "Payments hub did not exclude Membexa Core or a non-gateway utility.\n" );
	exit( 1 );
}

$api_plugins_method = new ReflectionMethod( \Membexa\Payment_Hub_Admin::class, 'api_plugins' );
$api_plugins_method->setAccessible( true );
$api_plugins = $api_plugins_method->invoke(
	$hub,
	(object) array(
		'plugins' => array(
			array(
				'name'              => 'Example PayPal for WooCommerce',
				'slug'              => 'example-paypal-woocommerce',
				'short_description' => 'A PayPal payment gateway for WooCommerce.',
			),
		),
	)
);
if ( 1 !== count( $api_plugins ) ) {
	fwrite( STDERR, "WordPress.org API result normalization failed.\n" );
	exit( 1 );
}

$candidate_method = new ReflectionMethod( \Membexa\Payment_Hub_Admin::class, 'is_repository_payment_candidate' );
$candidate_method->setAccessible( true );
if ( ! $candidate_method->invoke( $hub, $api_plugins[0] ) ) {
	fwrite( STDERR, "Valid WordPress.org WooCommerce gateway result was rejected.\n" );
	exit( 1 );
}
if ( $candidate_method->invoke(
	$hub,
	array(
		'name'              => 'WooCommerce Store Helper',
		'slug'              => 'woocommerce-store-helper',
		'short_description' => 'A WooCommerce administration utility.',
	)
) ) {
	fwrite( STDERR, "Unrelated WordPress.org WooCommerce plugin was accepted as a payment gateway.\n" );
	exit( 1 );
}

$value_method = new ReflectionMethod( \Membexa\Payment_Hub_Admin::class, 'plugin_api_value' );
$value_method->setAccessible( true );
if ( 'example-paypal-woocommerce' !== $value_method->invoke( $hub, $api_plugins[0], 'slug', '' ) ) {
	fwrite( STDERR, "Array-shaped WordPress.org plugin data could not be read.\n" );
	exit( 1 );
}
if ( 'object-gateway' !== $value_method->invoke( $hub, (object) array( 'slug' => 'object-gateway' ), 'slug', '' ) ) {
	fwrite( STDERR, "Object-shaped WordPress.org plugin data could not be read.\n" );
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
	if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['source'] ) || ! array_key_exists( 'description', $row ) ) {
		fwrite( STDERR, "Safe live WooCommerce gateway row generation failed.\n" );
		exit( 1 );
	}
}

echo "Membexa live Payments marketplace smoke test passed.\n";
