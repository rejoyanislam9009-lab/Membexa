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

	public function public_assets() {
		wp_register_style( 'membexa-public', MEMBEXA_URL . 'assets/css/public.css', array(), MEMBEXA_VERSION );
		if ( is_singular() || has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'membexa_pricing' ) || has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'membexa_account' ) || has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'membexa_register' ) ) {
			wp_enqueue_style( 'membexa-public' );
		}
	}
}
