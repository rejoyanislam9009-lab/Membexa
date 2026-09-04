<?php
/**
 * Membexa uninstall routine.
 *
 * @package Membexa
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$membexa_data = get_option( 'membexa_data', array() );
if ( empty( $membexa_data['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
$membexa_subscriptions = $wpdb->prefix . 'membexa_subscriptions';
$membexa_transactions  = $wpdb->prefix . 'membexa_transactions';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifier is a trusted WordPress-prefixed table name created by Membexa.
$wpdb->query( "DROP TABLE IF EXISTS {$membexa_subscriptions}" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifier is a trusted WordPress-prefixed table name created by Membexa.
$wpdb->query( "DROP TABLE IF EXISTS {$membexa_transactions}" );

$membexa_plan_ids = get_posts(
	array(
		'post_type'      => 'membexa_plan',
		'post_status'    => 'any',
		'fields'         => 'ids',
		'posts_per_page' => -1,
	)
);
foreach ( $membexa_plan_ids as $membexa_plan_id ) {
	wp_delete_post( $membexa_plan_id, true );
}

delete_post_meta_by_key( '_membexa_restricted' );
delete_post_meta_by_key( '_membexa_plan_ids' );
delete_post_meta_by_key( '_membexa_grant_plans' );
delete_post_meta_by_key( '_membexa_require_plans' );
delete_post_meta_by_key( '_membexa_restrict_view' );
delete_post_meta_by_key( '_membexa_restrict_purchase' );
delete_post_meta_by_key( '_membexa_granted_subscription_ids' );

delete_metadata( 'term', 0, '_membexa_grant_plans', '', true );
delete_metadata( 'term', 0, '_membexa_require_plans', '', true );
delete_metadata( 'term', 0, '_membexa_restrict_view', '', true );
delete_metadata( 'term', 0, '_membexa_restrict_purchase', '', true );

delete_option( 'membexa_general' );
delete_option( 'membexa_payments' );
delete_option( 'membexa_emails' );
delete_option( 'membexa_data' );
delete_option( 'membexa_integrations' );
delete_option( 'membexa_paypal_connection_status' );
delete_option( 'membexa_version' );
delete_option( 'membexa_flush_rewrite_rules' );
wp_clear_scheduled_hook( 'membexa_daily_maintenance' );
