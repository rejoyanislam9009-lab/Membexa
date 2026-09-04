<?php
/**
 * Plugin Name:       Membexa Stripe Gateway
 * Description:       Stripe Checkout gateway add-on for Membexa memberships and subscriptions.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  membexa
 * Author:            wpzenora
 * License:           GPL v2 or later
 * Text Domain:       membexa-stripe
 */

namespace Membexa\Stripe_Addon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEMBEXA_STRIPE_ADDON_VERSION', '1.0.0' );

function bootstrap() {
	if ( ! defined( 'MEMBEXA_VERSION' ) || version_compare( MEMBEXA_VERSION, '1.5.0', '<' ) || ! class_exists( '\\Membexa\\Gateways' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\dependency_notice' );
		return;
	}

	maybe_migrate();
	( new \Membexa\Stripe() )->hooks();

	\Membexa\Gateways::register(
		'stripe',
		array(
			'label'              => __( 'Stripe', 'membexa-stripe' ),
			'addon_version'      => MEMBEXA_STRIPE_ADDON_VERSION,
			'settings_url'       => admin_url( 'admin.php?page=membexa-stripe-gateway' ),
			'enabled_callback'   => array( '\\Membexa\\Stripe', 'enabled' ),
			'available_callback' => __NAMESPACE__ . '\\available_for_plan',
			'checkout_callback'  => array( '\\Membexa\\Stripe', 'start_checkout' ),
			'cancel_callback'    => __NAMESPACE__ . '\\cancel_subscription',
			'supports_recurring' => true,
		)
	);

	add_action( 'admin_menu', __NAMESPACE__ . '\\menu', 40 );
	add_action( 'admin_post_membexa_save_stripe_addon', __NAMESPACE__ . '\\save_settings' );
	add_action( 'membexa_plan_payment_fields', __NAMESPACE__ . '\\plan_field' );
	add_action( 'membexa_save_plan_payment_fields', __NAMESPACE__ . '\\save_plan_field' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 20 );

function dependency_notice() {
	if ( current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Membexa Stripe Gateway requires Membexa Core 1.5.0 or newer.', 'membexa-stripe' ) . '</p></div>';
	}
}

function maybe_migrate() {
	$migration = get_option( 'membexa_payment_addon_migration', array() );
	if ( empty( $migration['stripe'] ) ) {
		return;
	}
	$payments = get_option( 'membexa_payments', array() );
	$payments['stripe_enabled'] = 1;
	update_option( 'membexa_payments', $payments, false );
	$migration['stripe'] = 0;
	update_option( 'membexa_payment_addon_migration', $migration, false );
}

function available_for_plan( $plan ) {
	return \Membexa\Stripe::enabled() && ! empty( $plan['stripe_price_id'] );
}

function cancel_subscription( $subscription ) {
	$plan = \Membexa\Plan::get( $subscription->plan_id );
	if ( $plan && \Membexa\Gateways::is_recurring( $plan['billing'] ) ) {
		$result = \Membexa\Stripe::cancel_at_period_end( $subscription );
		return is_wp_error( $result ) ? $result : 'scheduled';
	}
	return null;
}

function menu() {
	add_submenu_page( 'membexa', __( 'Stripe Gateway', 'membexa-stripe' ), __( 'Stripe Gateway', 'membexa-stripe' ), 'manage_options', 'membexa-stripe-gateway', __NAMESPACE__ . '\\settings_page' );
}

function save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage Stripe.', 'membexa-stripe' ) );
	}
	check_admin_referer( 'membexa_save_stripe_addon' );
	$payments = get_option( 'membexa_payments', array() );
	$payments['stripe_enabled']        = empty( $_POST['stripe_enabled'] ) ? 0 : 1;
	$payments['stripe_secret_key']     = isset( $_POST['stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_secret_key'] ) ) : '';
	$payments['stripe_webhook_secret'] = isset( $_POST['stripe_webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_webhook_secret'] ) ) : '';
	update_option( 'membexa_payments', $payments, false );
	wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=membexa-stripe-gateway' ) ) );
	exit;
}

function settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = \Membexa\Settings::payments();
	?>
	<div class="wrap membexa-admin">
		<h1><?php esc_html_e( 'Membexa Stripe Gateway', 'membexa-stripe' ); ?></h1>
		<p><?php esc_html_e( 'This gateway is installed separately from Membexa Core. It provides hosted Stripe Checkout for standalone Membexa plans.', 'membexa-stripe' ); ?></p>
		<p><a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Stripe API keys', 'membexa-stripe' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="membexa_save_stripe_addon">
			<?php wp_nonce_field( 'membexa_save_stripe_addon' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Stripe Checkout', 'membexa-stripe' ); ?></th><td><label><input type="checkbox" name="stripe_enabled" value="1" <?php checked( $settings['stripe_enabled'] ); ?>> <?php esc_html_e( 'Enable Stripe', 'membexa-stripe' ); ?></label></td></tr>
				<tr><th><label for="membexa-stripe-addon-key"><?php esc_html_e( 'Secret key', 'membexa-stripe' ); ?></label></th><td><input id="membexa-stripe-addon-key" class="regular-text code" type="password" autocomplete="new-password" name="stripe_secret_key" value="<?php echo esc_attr( $settings['stripe_secret_key'] ); ?>"></td></tr>
				<tr><th><label for="membexa-stripe-addon-webhook"><?php esc_html_e( 'Webhook signing secret', 'membexa-stripe' ); ?></label></th><td><input id="membexa-stripe-addon-webhook" class="regular-text code" type="password" autocomplete="new-password" name="stripe_webhook_secret" value="<?php echo esc_attr( $settings['stripe_webhook_secret'] ); ?>"><p class="description"><?php echo esc_html( rest_url( 'membexa/v1/stripe/webhook' ) ); ?></p></td></tr>
			</table>
			<?php submit_button( __( 'Save Stripe Settings', 'membexa-stripe' ) ); ?>
		</form>
	</div>
	<?php
}

function plan_field( $post ) {
	$value = get_post_meta( $post->ID, '_membexa_stripe_price_id', true );
	?>
	<tr><th><label for="membexa_stripe_price_id"><?php esc_html_e( 'Stripe Price ID', 'membexa-stripe' ); ?></label></th><td><input class="regular-text code" id="membexa_stripe_price_id" name="membexa_stripe_price_id" value="<?php echo esc_attr( $value ); ?>" placeholder="price_..."><p class="description"><?php esc_html_e( 'Required for plans sold through the Membexa Stripe Gateway add-on.', 'membexa-stripe' ); ?></p></td></tr>
	<?php
}

function save_plan_field( $post_id ) {
	update_post_meta( $post_id, '_membexa_stripe_price_id', isset( $_POST['membexa_stripe_price_id'] ) ? sanitize_text_field( wp_unslash( $_POST['membexa_stripe_price_id'] ) ) : '' );
}
