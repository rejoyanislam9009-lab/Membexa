<?php
/**
 * Activation and deactivation routines.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation and deactivation.
 */
final class Activator {
	/**
	 * Install schema, defaults, and maintenance scheduling.
	 *
	 * @return void
	 */
	public static function activate() {
		DB::install();

		if ( false === get_option( 'membexa_general', false ) ) {
			add_option(
				'membexa_general',
				array(
					'default_currency' => 'USD',
					'pricing_page_id'  => 0,
					'account_page_id'  => 0,
					'access_message'   => __( 'This content is available to members with an eligible plan.', 'membexa' ),
				)
			);
		}

		if ( false === get_option( 'membexa_payments', false ) ) {
			add_option(
				'membexa_payments',
				array(
					'stripe_enabled'        => 0,
					'stripe_secret_key'     => '',
					'stripe_webhook_secret' => '',
					'paypal_enabled'        => 0,
					'paypal_sandbox'        => 1,
					'paypal_client_id'      => '',
					'paypal_client_secret'  => '',
					'paypal_webhook_id'     => '',
					'bkash_enabled'         => 0,
					'bkash_sandbox'         => 1,
					'bkash_username'        => '',
					'bkash_password'        => '',
					'bkash_app_key'         => '',
					'bkash_app_secret'      => '',
				),
				'',
				false
			);
		}

		if ( false === get_option( 'membexa_emails', false ) ) {
			add_option(
				'membexa_emails',
				array(
					'from_name'          => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'from_email'         => get_option( 'admin_email' ),
					'activation_enabled' => 1,
					'cancel_enabled'     => 1,
				)
			);
		}

		if ( false === get_option( 'membexa_data', false ) ) {
			add_option(
				'membexa_data',
				array(
					'delete_on_uninstall' => 0,
				)
			);
		}

		update_option( 'membexa_version', MEMBEXA_VERSION );

		if ( ! wp_next_scheduled( 'membexa_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'membexa_daily_maintenance' );
		}
	}

	/**
	 * Remove the scheduled maintenance event on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'membexa_daily_maintenance' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'membexa_daily_maintenance' );
		}
	}
}
