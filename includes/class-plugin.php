<?php
/**
 * Main plugin coordinator.
 *
 * @package Membexa
 */
namespace Membexa;
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Coordinates Membexa services and hooks. */
final class Plugin {
	/** @var Plugin|null */
	private static $instance;
	/** Get singleton. */
	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	/** Prevent direct construction. */
	private function __construct() {}
	/** Bootstrap services. */
	public function run() {
		$this->maybe_upgrade();
		( new Settings() )->hooks();
		( new Account() )->hooks();
		( new Plan() )->hooks();
		( new Subscriptions() )->hooks();
		( new Access() )->hooks();
		( new Commerce() )->hooks();
		( new Commerce_Lifecycle() )->hooks();
		( new Payment_Integrations() )->hooks();
		( new Shortcodes() )->hooks();
		( new Privacy() )->hooks();
		if ( is_admin() ) {
			( new Admin() )->hooks();
			( new Integrations_Admin() )->hooks();
			( new Setup() )->hooks();
			( new Help() )->hooks();
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'public_assets' ) );
	}
	/** Apply upgrades. */
	private function maybe_upgrade() {
		$installed = (string) get_option( 'membexa_version', '' );
		if ( MEMBEXA_VERSION !== $installed ) {
			DB::install();
			if ( ! $installed || version_compare( $installed, '1.4.0', '<' ) ) { update_option( 'membexa_setup_pages_pending', 1, false ); }
			if ( $installed && version_compare( $installed, '1.5.0', '<' ) ) {
				$legacy = get_option( 'membexa_payments', array() );
				if ( is_array( $legacy ) ) {
					$legacy['stripe_enabled'] = 0;
					$legacy['paypal_enabled'] = 0;
					$legacy['bkash_enabled'] = 0;
					update_option( 'membexa_payments', $legacy, false );
				}
			}
			update_option( 'membexa_flush_rewrite_rules', 1, false );
			update_option( 'membexa_version', MEMBEXA_VERSION );
		}
		if ( ! wp_next_scheduled( 'membexa_daily_maintenance' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'membexa_daily_maintenance' ); }
	}
	/** Load public stylesheet. */
	public function public_assets() {
		wp_register_style( 'membexa-public', MEMBEXA_URL . 'assets/css/public.css', array(), MEMBEXA_VERSION );
		if ( is_singular() || is_home() || is_archive() || is_search() || is_feed() ) { wp_enqueue_style( 'membexa-public' ); }
	}
}
