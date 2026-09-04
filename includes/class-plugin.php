<?php
/**
 * Main plugin coordinator.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates Membexa services and hooks.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance;

	/**
	 * Get the plugin singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Prevent direct construction. */
	private function __construct() {
	}

	/**
	 * Bootstrap plugin services.
	 *
	 * @return void
	 */
	public function run() {
		$this->maybe_upgrade();

		( new Settings() )->hooks();
		( new Account() )->hooks();
		( new Plan() )->hooks();
		( new Subscriptions() )->hooks();
		( new Stripe() )->hooks();
		( new PayPal() )->hooks();
		( new Bkash() )->hooks();
		( new Access() )->hooks();
		( new Commerce() )->hooks();
		( new Commerce_Lifecycle() )->hooks();
		( new Shortcodes() )->hooks();
		( new Privacy() )->hooks();

		if ( is_admin() ) {
			( new Admin() )->hooks();
			( new Integrations_Admin() )->hooks();
			( new Help() )->hooks();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'public_assets' ) );
	}

	/** Apply schema upgrades and ensure scheduled maintenance exists. */
	private function maybe_upgrade() {
		$installed = (string) get_option( 'membexa_version', '' );
		if ( MEMBEXA_VERSION !== $installed ) {
			DB::install();
			update_option( 'membexa_flush_rewrite_rules', 1, false );
			update_option( 'membexa_version', MEMBEXA_VERSION );
		}

		if ( ! wp_next_scheduled( 'membexa_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'membexa_daily_maintenance' );
		}
	}

	/** Load the small public stylesheet on standard content views. */
	public function public_assets() {
		wp_register_style( 'membexa-public', MEMBEXA_URL . 'assets/css/public.css', array(), MEMBEXA_VERSION );
		if ( is_singular() || is_home() || is_archive() || is_search() || is_feed() ) {
			wp_enqueue_style( 'membexa-public' );
		}
	}
}
