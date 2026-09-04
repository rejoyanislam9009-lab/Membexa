<?php
/**
 * Membexa automatic page setup smoke test.
 *
 * Run inside wp-env with Membexa active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$admin_id = wp_insert_user(
	array(
		'user_login' => 'membexa_setup_admin',
		'user_pass'  => wp_generate_password( 24, true, true ),
		'user_email' => 'membexa-setup@example.test',
		'role'       => 'administrator',
	)
);

if ( is_wp_error( $admin_id ) ) {
	throw new RuntimeException( 'Could not create setup-test administrator.' );
}

wp_set_current_user( $admin_id );
$setup = new \Membexa\Setup();
update_option( 'membexa_setup_pages_pending', 1, false );
$setup->maybe_auto_setup();

$general      = \Membexa\Settings::general();
$integrations = \Membexa\Settings::integrations();
$expected     = array(
	'pricing_page_id' => array( $general['pricing_page_id'], 'membexa_pricing', 'pricing' ),
	'account_page_id' => array( $general['account_page_id'], 'membexa_account', 'account' ),
	'join_page_id'    => array( $integrations['join_page_id'], 'membexa_register', 'join' ),
	'login_page_id'   => array( $integrations['login_page_id'], 'membexa_login', 'login' ),
);

foreach ( $expected as $label => $values ) {
	$page_id   = absint( $values[0] );
	$shortcode = $values[1];
	$key       = $values[2];
	$page      = $page_id ? get_post( $page_id ) : null;

	if ( ! $page || 'page' !== $page->post_type || ! has_shortcode( $page->post_content, $shortcode ) ) {
		throw new RuntimeException( 'Automatic page setup failed for ' . $label . '.' );
	}
	if ( $key !== get_post_meta( $page_id, \Membexa\Setup::PAGE_META, true ) ) {
		throw new RuntimeException( 'Membexa system page ownership marker missing for ' . $label . '.' );
	}
}

$old_pricing_id = absint( $general['pricing_page_id'] );
wp_delete_post( $old_pricing_id, true );
$general['pricing_page_id'] = 0;
update_option( 'membexa_general', $general, false );
update_option( 'membexa_setup_pages_pending', 1, false );
$setup->maybe_auto_setup();
$repaired = \Membexa\Settings::general();

if ( ! absint( $repaired['pricing_page_id'] ) || $old_pricing_id === absint( $repaired['pricing_page_id'] ) ) {
	throw new RuntimeException( 'Deleted Pricing page was not recreated.' );
}

$custom_login_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Existing Customer Login',
		'post_content' => 'Keep this administrator-managed content unchanged.',
	),
	true
);

if ( is_wp_error( $custom_login_id ) ) {
	throw new RuntimeException( 'Could not create custom login page.' );
}

$integrations                  = \Membexa\Settings::integrations();
$integrations['login_page_id'] = absint( $custom_login_id );
update_option( 'membexa_integrations', $integrations, false );
update_option( 'membexa_setup_pages_pending', 1, false );
$setup->maybe_auto_setup();
$after = \Membexa\Settings::integrations();
$page  = get_post( $custom_login_id );

if ( absint( $after['login_page_id'] ) !== absint( $custom_login_id ) ) {
	throw new RuntimeException( 'Existing administrator-selected Login page was replaced.' );
}
if ( ! $page || 'Keep this administrator-managed content unchanged.' !== $page->post_content ) {
	throw new RuntimeException( 'Existing administrator-selected Login page was modified.' );
}

WP_CLI::success( 'Membexa automatic page setup smoke test passed.' );
