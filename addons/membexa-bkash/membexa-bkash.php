<?php
/**
 * Plugin Name:       Membexa bKash Gateway
 * Description:       bKash Tokenized Checkout gateway add-on for Membexa Bangladesh memberships.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  membexa
 * Author:            wpzenora
 * License:           GPL v2 or later
 * Text Domain:       membexa-bkash
 */

namespace Membexa\Bkash_Addon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEMBEXA_BKASH_ADDON_VERSION', '1.0.0' );

function bootstrap() {
	if ( ! defined( 'MEMBEXA_VERSION' ) || version_compare( MEMBEXA_VERSION, '1.5.0', '<' ) || ! class_exists( '\\Membexa\\Gateways' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\dependency_notice' );
		return;
	}

	require_once __DIR__ . '/includes/class-bkash.php';
	maybe_migrate();
	( new \Membexa\Bkash() )->hooks();

	\Membexa\Gateways::register(
		'bkash',
		array(
			'label'              => __( 'bKash', 'membexa-bkash' ),
			'addon_version'      => MEMBEXA_BKASH_ADDON_VERSION,
			'settings_url'       => admin_url( 'admin.php?page=membexa-bkash-gateway' ),
			'enabled_callback'   => array( '\\Membexa\\Bkash', 'enabled' ),
			'available_callback' => __NAMESPACE__ . '\\available_for_plan',
			'checkout_callback'  => array( '\\Membexa\\Bkash', 'start_checkout' ),
		)
	);
	add_action( 'admin_menu', __NAMESPACE__ . '\\menu', 40 );
	add_action( 'admin_post_membexa_save_bkash_addon', __NAMESPACE__ . '\\save_settings' );
	add_action( 'membexa_plan_payment_fields', __NAMESPACE__ . '\\plan_field' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 20 );

function dependency_notice() {
	if ( current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Membexa bKash Gateway requires Membexa Core 1.5.0 or newer.', 'membexa-bkash' ) . '</p></div>';
	}
}

function maybe_migrate() {
	$migration = get_option( 'membexa_payment_addon_migration', array() );
	if ( empty( $migration['bkash'] ) ) {
		return;
	}
	$payments = get_option( 'membexa_payments', array() );
	$payments['bkash_enabled'] = 1;
	update_option( 'membexa_payments', $payments, false );
	$migration['bkash'] = 0;
	update_option( 'membexa_payment_addon_migration', $migration, false );
}

function available_for_plan( $plan ) {
	return \Membexa\Bkash::enabled() && 'BDT' === strtoupper( (string) $plan['currency'] ) && in_array( $plan['billing'], array( 'one_time', 'lifetime' ), true );
}

function menu() {
	add_submenu_page( 'membexa', __( 'bKash Gateway', 'membexa-bkash' ), __( 'bKash Gateway', 'membexa-bkash' ), 'manage_options', 'membexa-bkash-gateway', __NAMESPACE__ . '\\settings_page' );
}

function save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage bKash.', 'membexa-bkash' ) );
	}
	check_admin_referer( 'membexa_save_bkash_addon' );
	$payments = get_option( 'membexa_payments', array() );
	$payments['bkash_enabled']    = empty( $_POST['bkash_enabled'] ) ? 0 : 1;
	$payments['bkash_sandbox']    = empty( $_POST['bkash_sandbox'] ) ? 0 : 1;
	$payments['bkash_username']   = isset( $_POST['bkash_username'] ) ? sanitize_text_field( wp_unslash( $_POST['bkash_username'] ) ) : '';
	$payments['bkash_password']   = isset( $_POST['bkash_password'] ) ? sanitize_text_field( wp_unslash( $_POST['bkash_password'] ) ) : '';
	$payments['bkash_app_key']    = isset( $_POST['bkash_app_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bkash_app_key'] ) ) : '';
	$payments['bkash_app_secret'] = isset( $_POST['bkash_app_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['bkash_app_secret'] ) ) : '';
	update_option( 'membexa_payments', $payments, false );
	wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=membexa-bkash-gateway' ) ) );
	exit;
}

function settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = \Membexa\Settings::payments();
	?>
	<div class="wrap membexa-admin">
		<h1><?php esc_html_e( 'Membexa bKash Gateway', 'membexa-bkash' ); ?></h1>
		<p><?php esc_html_e( 'Separate bKash Tokenized Checkout add-on for BDT one-time and lifetime Membexa plans.', 'membexa-bkash' ); ?></p>
		<p><a href="https://www.bkash.com/en/business" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open bKash Business', 'membexa-bkash' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="membexa_save_bkash_addon">
			<?php wp_nonce_field( 'membexa_save_bkash_addon' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'bKash Checkout', 'membexa-bkash' ); ?></th><td><label><input type="checkbox" name="bkash_enabled" value="1" <?php checked( $settings['bkash_enabled'] ); ?>> <?php esc_html_e( 'Enable bKash', 'membexa-bkash' ); ?></label><br><label><input type="checkbox" name="bkash_sandbox" value="1" <?php checked( $settings['bkash_sandbox'] ); ?>> <?php esc_html_e( 'Sandbox / test mode', 'membexa-bkash' ); ?></label></td></tr>
				<tr><th><label for="membexa-bkash-addon-user"><?php esc_html_e( 'Merchant username', 'membexa-bkash' ); ?></label></th><td><input id="membexa-bkash-addon-user" class="regular-text code" name="bkash_username" value="<?php echo esc_attr( $settings['bkash_username'] ); ?>"></td></tr>
				<tr><th><label for="membexa-bkash-addon-pass"><?php esc_html_e( 'Merchant password', 'membexa-bkash' ); ?></label></th><td><input id="membexa-bkash-addon-pass" class="regular-text code" type="password" autocomplete="new-password" name="bkash_password" value="<?php echo esc_attr( $settings['bkash_password'] ); ?>"></td></tr>
				<tr><th><label for="membexa-bkash-addon-key"><?php esc_html_e( 'App Key', 'membexa-bkash' ); ?></label></th><td><input id="membexa-bkash-addon-key" class="regular-text code" name="bkash_app_key" value="<?php echo esc_attr( $settings['bkash_app_key'] ); ?>"></td></tr>
				<tr><th><label for="membexa-bkash-addon-secret"><?php esc_html_e( 'App Secret', 'membexa-bkash' ); ?></label></th><td><input id="membexa-bkash-addon-secret" class="regular-text code" type="password" autocomplete="new-password" name="bkash_app_secret" value="<?php echo esc_attr( $settings['bkash_app_secret'] ); ?>"></td></tr>
			</table>
			<?php submit_button( __( 'Save bKash Settings', 'membexa-bkash' ) ); ?>
		</form>
	</div>
	<?php
}

function plan_field() {
	?>
	<tr><th><?php esc_html_e( 'bKash compatibility', 'membexa-bkash' ); ?></th><td><p class="description"><?php esc_html_e( 'bKash is offered automatically for BDT plans using One-time payment or Lifetime billing.', 'membexa-bkash' ); ?></p></td></tr>
	<?php
}
