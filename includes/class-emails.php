<?php
/**
 * Transactional email helpers.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends membership lifecycle emails.
 */
final class Emails {
	/**
	 * Send the membership activation email.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $plan_id Membership plan ID.
	 * @return void
	 */
	public static function membership_activated( $user_id, $plan_id ) {
		$settings = Settings::emails();
		if ( empty( $settings['activation_enabled'] ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		$plan = Plan::get( $plan_id );
		if ( ! $user || ! $plan ) {
			return;
		}
		/* translators: %s: membership plan name. */
		$subject = sprintf( __( 'Your %s membership is active', 'membexa' ), $plan['name'] );
		/* translators: 1: member display name, 2: membership plan name. */
		$message = sprintf( __( "Hello %1\$s,\n\nYour membership for %2\$s is now active.\n\nThank you.", 'membexa' ), $user->display_name, $plan['name'] );
		self::send( $user->user_email, $subject, $message );
	}

	/**
	 * Send the membership cancellation email.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $plan_id Membership plan ID.
	 * @return void
	 */
	public static function membership_canceled( $user_id, $plan_id ) {
		$settings = Settings::emails();
		if ( empty( $settings['cancel_enabled'] ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		$plan = Plan::get( $plan_id );
		if ( ! $user || ! $plan ) {
			return;
		}
		/* translators: %s: membership plan name. */
		$subject = sprintf( __( 'Your %s membership was canceled', 'membexa' ), $plan['name'] );
		/* translators: 1: member display name, 2: membership plan name. */
		$message = sprintf( __( "Hello %1\$s,\n\nYour membership for %2\$s has been canceled.\n\nThank you.", 'membexa' ), $user->display_name, $plan['name'] );
		self::send( $user->user_email, $subject, $message );
	}

	/**
	 * Send a plain-text email using WordPress mail handling.
	 *
	 * @param string $to      Recipient email address.
	 * @param string $subject Email subject.
	 * @param string $message Email body.
	 * @return void
	 */
	private static function send( $to, $subject, $message ) {
		$settings = Settings::emails();
		$headers  = array();
		if ( ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
			$from_name = $settings['from_name'] ? $settings['from_name'] : get_bloginfo( 'name' );
			$headers[] = 'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $settings['from_email'] ) . '>';
		}
		wp_mail( $to, wp_strip_all_tags( $subject ), $message, $headers );
	}
}
