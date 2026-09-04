<?php
/**
 * Database helpers.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DB {
	public static function subscriptions_table() {
		global $wpdb;
		return $wpdb->prefix . 'membexa_subscriptions';
	}

	public static function transactions_table() {
		global $wpdb;
		return $wpdb->prefix . 'membexa_transactions';
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$subscriptions   = self::subscriptions_table();
		$transactions    = self::transactions_table();

		$sql_subscriptions = "CREATE TABLE {$subscriptions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			plan_id bigint(20) unsigned NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'pending',
			gateway varchar(30) NOT NULL DEFAULT 'free',
			gateway_external_id varchar(191) NOT NULL DEFAULT '',
			cancel_at_period_end tinyint(1) NOT NULL DEFAULT 0,
			started_at datetime NULL,
			ends_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY plan_id (plan_id),
			KEY status (status),
			KEY gateway_external_id (gateway_external_id)
		) {$charset_collate};";

		$sql_transactions = "CREATE TABLE {$transactions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
			gateway varchar(30) NOT NULL DEFAULT '',
			external_id varchar(191) NOT NULL DEFAULT '',
			type varchar(30) NOT NULL DEFAULT 'payment',
			amount decimal(18,6) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY subscription_id (subscription_id),
			KEY external_id (external_id)
		) {$charset_collate};";

		dbDelta( $sql_subscriptions );
		dbDelta( $sql_transactions );
	}
}
