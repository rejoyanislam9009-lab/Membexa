<?php
/**
 * WordPress privacy integration.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates Membexa data with WordPress privacy tools.
 */
final class Privacy {
	/**
	 * Register privacy hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_init', array( $this, 'policy_text' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	/**
	 * Add suggested privacy policy text.
	 *
	 * @return void
	 */
	public function policy_text() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				'Membexa',
				wp_kses_post( wpautop( __( 'Membexa stores membership plan assignments, subscription status, payment gateway references, and transaction metadata required to provide membership access. When Stripe is enabled, checkout occurs on Stripe and the site receives payment and subscription status through signed webhooks. Site owners should describe their payment provider and retention policy in their privacy policy.', 'membexa' ) ) )
			);
		}
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function exporters( $exporters ) {
		$exporters['membexa'] = array(
			'exporter_friendly_name' => __( 'Membexa membership data', 'membexa' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function erasers( $erasers ) {
		$erasers['membexa'] = array(
			'eraser_friendly_name' => __( 'Membexa membership data', 'membexa' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export a member's Membexa data.
	 *
	 * Membexa returns all of its relatively small per-user dataset on the first
	 * exporter page, then reports completion for subsequent page requests.
	 *
	 * @param string $email_address Requested email address.
	 * @param int    $page          Exporter page number.
	 * @return array
	 */
	public function export( $email_address, $page = 1 ) {
		global $wpdb;

		if ( 1 !== max( 1, absint( $page ) ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data = array();
		foreach ( Subscriptions::for_user( $user->ID ) as $subscription ) {
			$plan   = Plan::get( $subscription->plan_id );
			$data[] = array(
				'group_id'    => 'membexa-memberships',
				'group_label' => __( 'Memberships', 'membexa' ),
				'item_id'     => 'membexa-subscription-' . $subscription->id,
				'data'        => array(
					array(
						'name'  => __( 'Plan', 'membexa' ),
						'value' => $plan ? $plan['name'] : (string) $subscription->plan_id,
					),
					array(
						'name'  => __( 'Status', 'membexa' ),
						'value' => $subscription->status,
					),
					array(
						'name'  => __( 'Gateway', 'membexa' ),
						'value' => $subscription->gateway,
					),
					array(
						'name'  => __( 'Started', 'membexa' ),
						'value' => (string) $subscription->started_at,
					),
					array(
						'name'  => __( 'Ends', 'membexa' ),
						'value' => (string) $subscription->ends_at,
					),
				),
			);
		}

		$table = DB::transactions_table();
		// Dedicated Membexa table lookup for WordPress's authenticated privacy exporter.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$transactions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id ASC", $user->ID ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $transactions as $transaction ) {
			$data[] = array(
				'group_id'    => 'membexa-transactions',
				'group_label' => __( 'Membership transactions', 'membexa' ),
				'item_id'     => 'membexa-transaction-' . $transaction->id,
				'data'        => array(
					array(
						'name'  => __( 'Gateway', 'membexa' ),
						'value' => $transaction->gateway,
					),
					array(
						'name'  => __( 'Amount', 'membexa' ),
						'value' => $transaction->currency . ' ' . $transaction->amount,
					),
					array(
						'name'  => __( 'Status', 'membexa' ),
						'value' => $transaction->status,
					),
					array(
						'name'  => __( 'Date', 'membexa' ),
						'value' => $transaction->created_at,
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Anonymize a member's foreign-key references in Membexa data.
	 *
	 * Transaction records remain available for legitimate accounting and
	 * subscription audit needs, but are detached from the erased WordPress user.
	 *
	 * @param string $email_address Requested email address.
	 * @param int    $page          Eraser page number.
	 * @return array
	 */
	public function erase( $email_address, $page = 1 ) {
		global $wpdb;

		if ( 1 !== max( 1, absint( $page ) ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		// Dedicated Membexa table updates requested through WordPress privacy tools.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( DB::subscriptions_table(), array( 'user_id' => 0 ), array( 'user_id' => $user->ID ) );
		$wpdb->update( DB::transactions_table(), array( 'user_id' => 0 ), array( 'user_id' => $user->ID ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'items_removed'  => true,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
