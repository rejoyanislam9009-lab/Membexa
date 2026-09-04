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

final class Plugin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function run() {
		$this->maybe_upgrade();

		( new Settings() )->hooks();
		( new Plan() )->hooks();
		( new Subscriptions() )->hooks();
		( new Stripe() )->hooks();
		( new Access() )->hooks();
		( new Shortcodes() )->hooks();
		( new Privacy() )->hooks();

		if ( is_admin() ) {
			( new Admin() )->hooks();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'public_assets' ) );
	}

	private function maybe_upgrade() {
		$installed = (string) get_option( 'membexa_version', '' );
		if ( MEMBEXA_VERSION !== $installed ) {
			DB::install();
			update_option( 'membexa_version', MEMBEXA_VERSION );
		}
		if ( ! wp_next_scheduled( 'membexa_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'membexa_daily_maintenance' );
		}
	}

	public function public_assets() {
		wp_register_style( 'membexa-public', MEMBEXA_URL . 'assets/css/public.css', array(), MEMBEXA_VERSION );
		$content = is_singular() ? (string) get_post_field( 'post_content', get_queried_object_id() ) : '';
		if ( is_singular() || has_shortcode( $content, 'membexa_pricing' ) || has_shortcode( $content, 'membexa_account' ) || has_shortcode( $content, 'membexa_register' ) || has_shortcode( $content, 'membexa_login' ) ) {
			wp_enqueue_style( 'membexa-public' );
		}
	}
}
