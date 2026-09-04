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

/**
 * Manages local membership subscriptions and transactions.
 */
final class Subscriptions {
	/** Register subscription hooks. */
	public function hooks() {
		add_action( 'membexa_daily_maintenance', array( $this, 'daily_maintenance' ) );
	}

	/**
	 * Create a subscription record.
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param int    $plan_id     Membership plan ID.
	 * @param string $status      Initial status.
	 * @param string $gateway     Gateway identifier.
	 * @param string $external_id External gateway identifier.
	 * @return int
	 */
	public static function create( $user_id, $plan_id, $status = 'pending', $gateway = 'free', $external_id = '' ) {
		global $wpdb;

		$allowed_statuses = array( 'pending', 'active', 'trialing', 'past_due', 'canceled', 'expired' );
		$status           = in_array( $status, $allowed_statuses, true ) ? $status : 'pending';
		$now              = current_time( 'mysql', true );

		// Dedicated Membexa subscription table write.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			DB::subscriptions_table(),
			array(
				'user_id'             => absint( $user_id ),
				'plan_id'             => absint( $plan_id ),
				'status'              => $status,
				'gateway'             => sanitize_key( $gateway ),
				'gateway_external_id' => sanitize_text_field( $external_id ),
				'started_at'          => in_array( $status, array( 'active', 'trialing' ), true ) ? $now : null,
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$id = (int) $wpdb->insert_id;
		if ( $id && in_array( $status, array( 'active', 'trialing' ), true ) ) {
			Emails::membership_activated( $user_id, $plan_id );
		}
		return $id;
	}

	/** Get a subscription by local ID. */
	public static function get( $subscription_id ) {
		global $wpdb;
		$table = DB::subscriptions_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $subscription_id ) ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row;
	}

	/** Get a subscription by gateway external ID. */
	public static function get_by_external_id( $external_id ) {
		global $wpdb;
		$table = DB::subscriptions_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE gateway_external_id = %s ORDER BY id DESC LIMIT 1", sanitize_text_field( $external_id ) ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row;
	}

	/**
	 * Update a subscription's external gateway identifier.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $external_id     External gateway ID.
	 * @return bool
	 */
	public static function set_external_id( $subscription_id, $external_id ) {
		global $wpdb;
		if ( ! $subscription_id || ! $external_id ) {
			return false;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			DB::subscriptions_table(),
			array(
				'gateway_external_id' => sanitize_text_field( $external_id ),
				'updated_at'          => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $subscription_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $updated;
	}

	/** Get subscriptions for a WordPress user. */
	public static function for_user( $user_id, $active_only = false ) {
		global $wpdb;
		$table = DB::subscriptions_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $active_only ) {
			$now  = current_time( 'mysql', true );
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d AND status IN ('active','trialing') AND (ends_at IS NULL OR ends_at >= %s) ORDER BY id DESC",
					absint( $user_id ),
					$now
				)
			);
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC", absint( $user_id ) ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $rows;
	}

	/** Activate a local subscription. */
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update( DB::subscriptions_table(), $data, array( 'id' => absint( $subscription_id ) ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false !== $updated && ! in_array( $subscription->status, array( 'active', 'trialing' ), true ) ) {
			Emails::membership_activated( $subscription->user_id, $subscription->plan_id );
		}
		return false !== $updated;
	}

	/** Update a subscription status using its gateway ID. */
	public static function update_status_by_external_id( $external_id, $status, $ends_at = null, $cancel_at_period_end = null ) {
		global $wpdb;
		$allowed      = array( 'pending', 'active', 'trialing', 'past_due', 'canceled', 'expired' );
		$status       = in_array( $status, $allowed, true ) ? $status : 'pending';
		$external_id  = sanitize_text_field( $external_id );
		$subscription = self::get_by_external_id( $external_id );
		if ( ! $subscription ) {
			return false;
		}

		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( $ends_at ) {
			$data['ends_at'] = gmdate( 'Y-m-d H:i:s', absint( $ends_at ) );
		}
		if ( null !== $cancel_at_period_end ) {
			$data['cancel_at_period_end'] = $cancel_at_period_end ? 1 : 0;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update( DB::subscriptions_table(), $data, array( 'id' => (int) $subscription->id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $updated ) {
			return false;
		}

		$was_active = in_array( $subscription->status, array( 'active', 'trialing' ), true );
		$is_active  = in_array( $status, array( 'active', 'trialing' ), true );
		if ( ! $was_active && $is_active ) {
			Emails::membership_activated( $subscription->user_id, $subscription->plan_id );
		} elseif ( $was_active && 'canceled' === $status ) {
			Emails::membership_canceled( $subscription->user_id, $subscription->plan_id );
		}
		return true;
	}

	/** Cancel a local subscription immediately. */
	public static function cancel_local( $subscription_id ) {
		global $wpdb;
		$subscription = self::get( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			DB::subscriptions_table(),
			array(
				'status'     => 'canceled',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $subscription_id ) )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false !== $updated && in_array( $subscription->status, array( 'active', 'trialing' ), true ) ) {
			Emails::membership_canceled( $subscription->user_id, $subscription->plan_id );
		}
		return false !== $updated;
	}

	/** Set or clear cancellation at period end. */
	public static function set_cancel_at_period_end( $subscription_id, $value ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			DB::subscriptions_table(),
			array(
				'cancel_at_period_end' => $value ? 1 : 0,
				'updated_at'           => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $subscription_id ) )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $updated;
	}

	/** Determine whether a user has any requested active plan. */
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

	/** Store a payment or billing transaction if it has not already been logged. */
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
		$data        = wp_parse_args( $data, $defaults );
		$gateway     = sanitize_key( $data['gateway'] );
		$external_id = sanitize_text_field( $data['external_id'] );
		$type        = sanitize_key( $data['type'] );
		$table       = DB::transactions_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $external_id ) {
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE gateway = %s AND external_id = %s AND type = %s",
					$gateway,
					$external_id,
					$type
				)
			);
			if ( $exists ) {
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return true;
			}
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'         => absint( $data['user_id'] ),
				'subscription_id' => absint( $data['subscription_id'] ),
				'gateway'         => $gateway,
				'external_id'     => $external_id,
				'type'            => $type,
				'amount'          => (float) $data['amount'],
				'currency'        => strtoupper( sanitize_text_field( $data['currency'] ) ),
				'status'          => sanitize_key( $data['status'] ),
				'created_at'      => current_time( 'mysql', true ),
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $inserted;
	}

	/** Expire ended memberships and abandoned hosted-checkout records. */
	public function daily_maintenance() {
		global $wpdb;
		$table  = DB::subscriptions_table();
		$now    = current_time( 'mysql', true );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'expired', updated_at = %s WHERE status IN ('active','trialing') AND ends_at IS NOT NULL AND ends_at < %s", $now, $now ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'expired', updated_at = %s WHERE status = 'pending' AND gateway IN ('stripe','paypal','bkash') AND created_at < %s", $now, $cutoff ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
