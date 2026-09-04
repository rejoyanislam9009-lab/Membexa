<?php
/**
 * Settings registration and accessors.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers, sanitizes, and reads Membexa settings.
 */
final class Settings {
	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register settings with the WordPress Settings API.
	 *
	 * @return void
	 */
	public function register() {
		register_setting( 'membexa_general_group', 'membexa_general', array( 'sanitize_callback' => array( $this, 'sanitize_general' ) ) );
		register_setting( 'membexa_payments_group', 'membexa_payments', array( 'sanitize_callback' => array( $this, 'sanitize_payments' ) ) );
		register_setting( 'membexa_emails_group', 'membexa_emails', array( 'sanitize_callback' => array( $this, 'sanitize_emails' ) ) );
		register_setting( 'membexa_data_group', 'membexa_data', array( 'sanitize_callback' => array( $this, 'sanitize_data' ) ) );
	}

	/**
	 * Sanitize general settings.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public function sanitize_general( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$currency = isset( $input['default_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $input['default_currency'] ) ) ) : 'USD';
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$currency = 'USD';
		}

		return array(
			'default_currency' => $currency,
			'pricing_page_id'  => isset( $input['pricing_page_id'] ) ? absint( $input['pricing_page_id'] ) : 0,
			'account_page_id'  => isset( $input['account_page_id'] ) ? absint( $input['account_page_id'] ) : 0,
			'access_message'   => isset( $input['access_message'] ) ? sanitize_textarea_field( wp_unslash( $input['access_message'] ) ) : '',
		);
	}

	/**
	 * Sanitize payment settings.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public function sanitize_payments( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'stripe_enabled'        => empty( $input['stripe_enabled'] ) ? 0 : 1,
			'stripe_secret_key'     => isset( $input['stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $input['stripe_secret_key'] ) ) : '',
			'stripe_webhook_secret' => isset( $input['stripe_webhook_secret'] ) ? sanitize_text_field( wp_unslash( $input['stripe_webhook_secret'] ) ) : '',
			'paypal_enabled'        => empty( $input['paypal_enabled'] ) ? 0 : 1,
			'paypal_sandbox'        => empty( $input['paypal_sandbox'] ) ? 0 : 1,
			'paypal_client_id'      => isset( $input['paypal_client_id'] ) ? sanitize_text_field( wp_unslash( $input['paypal_client_id'] ) ) : '',
			'paypal_client_secret'  => isset( $input['paypal_client_secret'] ) ? sanitize_text_field( wp_unslash( $input['paypal_client_secret'] ) ) : '',
			'paypal_webhook_id'     => isset( $input['paypal_webhook_id'] ) ? sanitize_text_field( wp_unslash( $input['paypal_webhook_id'] ) ) : '',
			'bkash_enabled'         => empty( $input['bkash_enabled'] ) ? 0 : 1,
			'bkash_sandbox'         => empty( $input['bkash_sandbox'] ) ? 0 : 1,
			'bkash_username'        => isset( $input['bkash_username'] ) ? sanitize_text_field( wp_unslash( $input['bkash_username'] ) ) : '',
			'bkash_password'        => isset( $input['bkash_password'] ) ? sanitize_text_field( wp_unslash( $input['bkash_password'] ) ) : '',
			'bkash_app_key'         => isset( $input['bkash_app_key'] ) ? sanitize_text_field( wp_unslash( $input['bkash_app_key'] ) ) : '',
			'bkash_app_secret'      => isset( $input['bkash_app_secret'] ) ? sanitize_text_field( wp_unslash( $input['bkash_app_secret'] ) ) : '',
		);
	}

	/**
	 * Sanitize email settings.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public function sanitize_emails( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'from_name'          => isset( $input['from_name'] ) ? sanitize_text_field( wp_unslash( $input['from_name'] ) ) : '',
			'from_email'         => isset( $input['from_email'] ) ? sanitize_email( wp_unslash( $input['from_email'] ) ) : '',
			'activation_enabled' => empty( $input['activation_enabled'] ) ? 0 : 1,
			'cancel_enabled'     => empty( $input['cancel_enabled'] ) ? 0 : 1,
		);
	}

	/**
	 * Sanitize data-retention settings.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public function sanitize_data( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array( 'delete_on_uninstall' => empty( $input['delete_on_uninstall'] ) ? 0 : 1 );
	}

	/**
	 * Get general settings with defaults.
	 *
	 * @return array
	 */
	public static function general() {
		return wp_parse_args(
			get_option( 'membexa_general', array() ),
			array(
				'default_currency' => 'USD',
				'pricing_page_id'  => 0,
				'account_page_id'  => 0,
				'access_message'   => __( 'This content is available to members with an eligible plan.', 'membexa' ),
			)
		);
	}

	/**
	 * Get payment settings with defaults.
	 *
	 * @return array
	 */
	public static function payments() {
		return wp_parse_args(
			get_option( 'membexa_payments', array() ),
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
			)
		);
	}

	/**
	 * Get email settings with defaults.
	 *
	 * @return array
	 */
	public static function emails() {
		return wp_parse_args(
			get_option( 'membexa_emails', array() ),
			array(
				'from_name'          => get_bloginfo( 'name' ),
				'from_email'         => get_option( 'admin_email' ),
				'activation_enabled' => 1,
				'cancel_enabled'     => 1,
			)
		);
	}

	/**
	 * Get data-retention settings with defaults.
	 *
	 * @return array
	 */
	public static function data() {
		return wp_parse_args( get_option( 'membexa_data', array() ), array( 'delete_on_uninstall' => 0 ) );
	}

	/** Get Stripe secret key, preferring wp-config.php. */
	public static function stripe_secret_key() {
		if ( defined( 'MEMBEXA_STRIPE_SECRET_KEY' ) && MEMBEXA_STRIPE_SECRET_KEY ) {
			return (string) MEMBEXA_STRIPE_SECRET_KEY;
		}
		$settings = self::payments();
		return (string) $settings['stripe_secret_key'];
	}

	/** Get Stripe webhook secret, preferring wp-config.php. */
	public static function stripe_webhook_secret() {
		if ( defined( 'MEMBEXA_STRIPE_WEBHOOK_SECRET' ) && MEMBEXA_STRIPE_WEBHOOK_SECRET ) {
			return (string) MEMBEXA_STRIPE_WEBHOOK_SECRET;
		}
		$settings = self::payments();
		return (string) $settings['stripe_webhook_secret'];
	}

	/** Get PayPal client ID, preferring wp-config.php. */
	public static function paypal_client_id() {
		if ( defined( 'MEMBEXA_PAYPAL_CLIENT_ID' ) && MEMBEXA_PAYPAL_CLIENT_ID ) {
			return (string) MEMBEXA_PAYPAL_CLIENT_ID;
		}
		$settings = self::payments();
		return (string) $settings['paypal_client_id'];
	}

	/** Get PayPal client secret, preferring wp-config.php. */
	public static function paypal_client_secret() {
		if ( defined( 'MEMBEXA_PAYPAL_CLIENT_SECRET' ) && MEMBEXA_PAYPAL_CLIENT_SECRET ) {
			return (string) MEMBEXA_PAYPAL_CLIENT_SECRET;
		}
		$settings = self::payments();
		return (string) $settings['paypal_client_secret'];
	}

	/** Get PayPal webhook ID, preferring wp-config.php. */
	public static function paypal_webhook_id() {
		if ( defined( 'MEMBEXA_PAYPAL_WEBHOOK_ID' ) && MEMBEXA_PAYPAL_WEBHOOK_ID ) {
			return (string) MEMBEXA_PAYPAL_WEBHOOK_ID;
		}
		$settings = self::payments();
		return (string) $settings['paypal_webhook_id'];
	}

	/** Get bKash merchant username, preferring wp-config.php. */
	public static function bkash_username() {
		if ( defined( 'MEMBEXA_BKASH_USERNAME' ) && MEMBEXA_BKASH_USERNAME ) {
			return (string) MEMBEXA_BKASH_USERNAME;
		}
		$settings = self::payments();
		return (string) $settings['bkash_username'];
	}

	/** Get bKash merchant password, preferring wp-config.php. */
	public static function bkash_password() {
		if ( defined( 'MEMBEXA_BKASH_PASSWORD' ) && MEMBEXA_BKASH_PASSWORD ) {
			return (string) MEMBEXA_BKASH_PASSWORD;
		}
		$settings = self::payments();
		return (string) $settings['bkash_password'];
	}

	/** Get bKash app key, preferring wp-config.php. */
	public static function bkash_app_key() {
		if ( defined( 'MEMBEXA_BKASH_APP_KEY' ) && MEMBEXA_BKASH_APP_KEY ) {
			return (string) MEMBEXA_BKASH_APP_KEY;
		}
		$settings = self::payments();
		return (string) $settings['bkash_app_key'];
	}

	/** Get bKash app secret, preferring wp-config.php. */
	public static function bkash_app_secret() {
		if ( defined( 'MEMBEXA_BKASH_APP_SECRET' ) && MEMBEXA_BKASH_APP_SECRET ) {
			return (string) MEMBEXA_BKASH_APP_SECRET;
		}
		$settings = self::payments();
		return (string) $settings['bkash_app_secret'];
	}
}
