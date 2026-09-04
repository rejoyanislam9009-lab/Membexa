<?php
/**
 * Membexa uninstall routine.
 *
 * @package Membexa
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$data = get_option( 'membexa_data', array() );
if ( empty( $data['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
$subscriptions = $wpdb->prefix . 'membexa_subscriptions';
$transactions  = $wpdb->prefix . 'membexa_transactions';
$wpdb->query( "DROP TABLE IF EXISTS {$subscriptions}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$transactions}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$plan_ids = get_posts( array( 'post_type' => 'membexa_plan', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) );
foreach ( $plan_ids as $plan_id ) {
	wp_delete_post( $plan_id, true );
}

$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_membexa_restricted' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_membexa_plan_ids' ) );

delete_option( 'membexa_general' );
delete_option( 'membexa_payments' );
delete_option( 'membexa_emails' );
delete_option( 'membexa_data' );
delete_option( 'membexa_version' );
wp_clear_scheduled_hook( 'membexa_daily_maintenance' );
