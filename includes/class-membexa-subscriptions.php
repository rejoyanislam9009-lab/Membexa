<?php
/**
 * Subscription domain service.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Subscriptions {
	public function hooks() {
		add_action( 'membexa_daily_maintenance', array( $this, 'expire_due_subscriptions' ) );
	}

	public static function create( $user_id, $plan_id, $status = 'pending', $gateway = 'free', $external_id = '' ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			DB::subscriptions_table(),
			array(
				'user_id'             => absint( $user_id ),
				'plan_id'             => absint( $plan_id ),
				'status'              => sanitize_key( $status ),
				'gateway'             => sanitize_key( $gateway ),
				'gateway_external_id' => sanitize_text_field( $external_id ),
				'started_at'          => in_array( $status, array( 'active', 'trialing' ), true ) ? $now : null,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		if ( $id && in_array( $status, array( 'active', 'trialing' ), true ) ) {
			Emails::membership_activated( $user_id, $plan_id );
		}
		return $id;
	}

	public static function get( $subscription_id ) {
		global $wpdb;
		$table = DB::subscriptions_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $subscription_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_by_external_id( $external_id ) {
		global $wpdb;
		$table = DB::subscriptions_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE gateway_external_id = %s ORDER BY id DESC LIMIT 1", sanitize_text_field( $external_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function for_user( $user_id, $active_only = false ) {
		global $wpdb;
		$table = DB::subscriptions_table();
		if ( $active_only ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND status IN ('active','trialing') ORDER BY id DESC", absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC", absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function activate( $subscription_id, $external_id = '', $ends_at = null ) {
		global $wpdb;
		$subscription = self::get( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}
		$data = array(
			'status'     => 'active',
			'started_at' => $subscription->started_at ? $subscription->started_at : current_time( 'mysql', true ),
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( $external_id ) {
			$data['gateway_external_id'] = sanitize_text_field( $external_id );
		}
		if ( $ends_at ) {
			$data['ends_at'] = gmdate( 'Y-m-d H:i:s', absint( $ends_at ) );
		}
		$updated = $wpdb->update( DB::subscriptions_table(), $data, array( 'id' => absint( $subscription_id ) ) );
		if ( false !== $updated && 'active' !== $subscription->status ) {
			Emails::membership_activated( $subscription->user_id, $subscription->plan_id );
		}
		return false !== $updated;
	}

	public static function update_status_by_external_id( $external_id, $status, $ends_at = null, $cancel_at_period_end = null ) {
		global $wpdb;
		$allowed = array( 'pending', 'active', 'trialing', 'past_due', 'canceled', 'expired' );
		$status  = in_array( $status, $allowed, true ) ? $status : 'pending';
		$data    = array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) );
		if ( $ends_at ) {
			$data['ends_at'] = gmdate( 'Y-m-d H:i:s', absint( $ends_at ) );
		}
		if ( null !== $cancel_at_period_end ) {
			$data['cancel_at_period_end'] = $cancel_at_period_end ? 1 : 0;
		}
		return $wpdb->update( DB::subscriptions_table(), $data, array( 'gateway_external_id' => sanitize_text_field( $external_id ) ) );
	}

	public static function cancel_local( $subscription_id ) {
		global $wpdb;
		$subscription = self::get( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}
		$updated = $wpdb->update(
			DB::subscriptions_table(),
			array( 'status' => 'canceled', 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $subscription_id ) )
		);
		if ( false !== $updated && 'canceled' !== $subscription->status ) {
			Emails::membership_canceled( $subscription->user_id, $subscription->plan_id );
		}
		return false !== $updated;
	}

	public static function set_cancel_at_period_end( $subscription_id, $value ) {
		global $wpdb;
		return false !== $wpdb->update(
			DB::subscriptions_table(),
			array( 'cancel_at_period_end' => $value ? 1 : 0, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $subscription_id ) )
		);
	}

	public static function user_has_plan( $user_id, $plan_ids ) {
		$plan_ids = array_values( array_filter( array_map( 'absint', (array) $plan_ids ) ) );
		if ( empty( $plan_ids ) ) {
			return false;
		}
		foreach ( self::for_user( $user_id, true ) as $subscription ) {
			if ( in_array( (int) $subscription->plan_id, $plan_ids, true ) ) {
				return true;
			}
		}
		return false;
	}

	public static function log_transaction( $data ) {
		global $wpdb;
		$defaults = array(
			'user_id'         => 0,
			'subscription_id' => 0,
			'gateway'         => '',
			'external_id'     => '',
			'type'            => 'payment',
			'amount'          => 0,
			'currency'        => '',
			'status'          => '',
		);
		$data = wp_parse_args( $data, $defaults );
		return (bool) $wpdb->insert(
			DB::transactions_table(),
			array(
				'user_id'         => absint( $data['user_id'] ),
				'subscription_id' => absint( $data['subscription_id'] ),
				'gateway'         => sanitize_key( $data['gateway'] ),
				'external_id'     => sanitize_text_field( $data['external_id'] ),
				'type'            => sanitize_key( $data['type'] ),
				'amount'          => (float) $data['amount'],
				'currency'        => strtoupper( sanitize_text_field( $data['currency'] ) ),
				'status'          => sanitize_key( $data['status'] ),
				'created_at'      => current_time( 'mysql', true ),
			)
		);
	}

	public function expire_due_subscriptions() {
		global $wpdb;
		$table = DB::subscriptions_table();
		$now   = current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'expired', updated_at = %s WHERE status IN ('active','trialing') AND ends_at IS NOT NULL AND ends_at < %s", $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
