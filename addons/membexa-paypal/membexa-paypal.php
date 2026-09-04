<?php
/**
 * Plugin Name:       Membexa PayPal Gateway
 * Description:       PayPal Orders and Subscriptions gateway add-on for Membexa.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  membexa
 * Author:            wpzenora
 * License:           GPL v2 or later
 * Text Domain:       membexa-paypal
 */

namespace Membexa\PayPal_Addon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEMBEXA_PAYPAL_ADDON_VERSION', '1.0.0' );

function bootstrap() {
	if ( ! defined( 'MEMBEXA_VERSION' ) || version_compare( MEMBEXA_VERSION, '1.5.0', '<' ) || ! class_exists( '\\Membexa\\Gateways' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\dependency_notice' );
		return;
	}

	require_once __DIR__ . '/includes/class-paypal.php';
	require_once __DIR__ . '/includes/class-paypal-connection.php';
	maybe_migrate();
	( new \Membexa\PayPal() )->hooks();
	if ( is_admin() ) {
		( new \Membexa\PayPal_Connection() )->hooks();
	}

	\Membexa\Gateways::register(
		'paypal',
		array(
			'label'              => __( 'PayPal', 'membexa-paypal' ),
			'addon_version'      => MEMBEXA_PAYPAL_ADDON_VERSION,
			'settings_url'       => admin_url( 'admin.php?page=membexa-paypal-gateway' ),
			'enabled_callback'   => array( '\\Membexa\\PayPal', 'enabled' ),
			'available_callback' => __NAMESPACE__ . '\\available_for_plan',
			'checkout_callback'  => array( '\\Membexa\\PayPal', 'start_checkout' ),
			'cancel_callback'    => __NAMESPACE__ . '\\cancel_subscription',
			'supports_recurring' => true,
		)
	);
	add_action( 'admin_menu', __NAMESPACE__ . '\\menu', 40 );
	add_action( 'admin_post_membexa_save_paypal_addon', __NAMESPACE__ . '\\save_settings' );
	add_action( 'membexa_plan_payment_fields', __NAMESPACE__ . '\\plan_field' );
	add_action( 'membexa_save_plan_payment_fields', __NAMESPACE__ . '\\save_plan_field' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 20 );

function dependency_notice() {
	if ( current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Membexa PayPal Gateway requires Membexa Core 1.5.0 or newer.', 'membexa-paypal' ) . '</p></div>';
	}
}

function maybe_migrate() {
	$migration = get_option( 'membexa_payment_addon_migration', array() );
	if ( empty( $migration['paypal'] ) ) {
		return;
	}
	$payments = get_option( 'membexa_payments', array() );
	$payments['paypal_enabled'] = 1;
	update_option( 'membexa_payments', $payments, false );
	$migration['paypal'] = 0;
	update_option( 'membexa_payment_addon_migration', $migration, false );
}

function available_for_plan( $plan ) {
	$recurring = \Membexa\Gateways::is_recurring( $plan['billing'] );
	return \Membexa\PayPal::enabled() && ( ! $recurring || ! empty( $plan['paypal_plan_id'] ) );
}

function cancel_subscription( $subscription ) {
	$plan = \Membexa\Plan::get( $subscription->plan_id );
	if ( $plan && \Membexa\Gateways::is_recurring( $plan['billing'] ) ) {
		$result = \Membexa\PayPal::cancel_subscription( $subscription );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		\Membexa\Subscriptions::cancel_local( $subscription->id );
		return 'cancelled';
	}
	return null;
}

function menu() {
	add_submenu_page( 'membexa', __( 'PayPal Gateway', 'membexa-paypal' ), __( 'PayPal Gateway', 'membexa-paypal' ), 'manage_options', 'membexa-paypal-gateway', __NAMESPACE__ . '\\settings_page' );
}

function save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage PayPal.', 'membexa-paypal' ) );
	}
	check_admin_referer( 'membexa_save_paypal_addon' );
	$payments = get_option( 'membexa_payments', array() );
	$payments['paypal_enabled']       = empty( $_POST['paypal_enabled'] ) ? 0 : 1;
	$payments['paypal_sandbox']       = empty( $_POST['paypal_sandbox'] ) ? 0 : 1;
	$payments['paypal_client_id']     = isset( $_POST['paypal_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal_client_id'] ) ) : '';
	$payments['paypal_client_secret'] = isset( $_POST['paypal_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal_client_secret'] ) ) : '';
	$payments['paypal_webhook_id']    = isset( $_POST['paypal_webhook_id'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal_webhook_id'] ) ) : '';
	update_option( 'membexa_payments', $payments, false );
	delete_option( \Membexa\PayPal_Connection::OPTION );
	wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=membexa-paypal-gateway' ) ) );
	exit;
}

function settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = \Membexa\Settings::payments();
	$status   = \Membexa\PayPal_Connection::current_status();
	$verify   = add_query_arg( array( 'action' => 'membexa_verify_paypal', '_wpnonce' => wp_create_nonce( 'membexa_verify_paypal' ) ), admin_url( 'admin-post.php' ) );
	?>
	<div class="wrap membexa-admin">
		<h1><?php esc_html_e( 'Membexa PayPal Gateway', 'membexa-paypal' ); ?></h1>
		<p><a href="https://developer.paypal.com/dashboard/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open PayPal Developer Dashboard', 'membexa-paypal' ); ?></a></p>
		<div class="notice inline <?php echo 'connected' === $status['state'] ? 'notice-success' : ( 'failed' === $status['state'] ? 'notice-error' : 'notice-warning' ); ?>"><p><strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status['state'] ) ) ); ?></strong> — <?php echo esc_html( $status['message'] ); ?></p><?php if ( ! empty( $status['missing_events'] ) ) : ?><p><?php echo esc_html( implode( ', ', $status['missing_events'] ) ); ?></p><?php endif; ?><p><a class="button button-secondary" href="<?php echo esc_url( $verify ); ?>"><?php esc_html_e( 'Verify PayPal Connection', 'membexa-paypal' ); ?></a></p></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="membexa_save_paypal_addon">
			<?php wp_nonce_field( 'membexa_save_paypal_addon' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'PayPal Checkout', 'membexa-paypal' ); ?></th><td><label><input type="checkbox" name="paypal_enabled" value="1" <?php checked( $settings['paypal_enabled'] ); ?>> <?php esc_html_e( 'Enable PayPal', 'membexa-paypal' ); ?></label><br><label><input type="checkbox" name="paypal_sandbox" value="1" <?php checked( $settings['paypal_sandbox'] ); ?>> <?php esc_html_e( 'Sandbox / test mode', 'membexa-paypal' ); ?></label></td></tr>
				<tr><th><label for="membexa-paypal-addon-id"><?php esc_html_e( 'Client ID', 'membexa-paypal' ); ?></label></th><td><input id="membexa-paypal-addon-id" class="regular-text code" name="paypal_client_id" value="<?php echo esc_attr( $settings['paypal_client_id'] ); ?>"></td></tr>
				<tr><th><label for="membexa-paypal-addon-secret"><?php esc_html_e( 'Client Secret', 'membexa-paypal' ); ?></label></th><td><input id="membexa-paypal-addon-secret" class="regular-text code" type="password" autocomplete="new-password" name="paypal_client_secret" value="<?php echo esc_attr( $settings['paypal_client_secret'] ); ?>"></td></tr>
				<tr><th><label for="membexa-paypal-addon-webhook"><?php esc_html_e( 'Webhook ID', 'membexa-paypal' ); ?></label></th><td><input id="membexa-paypal-addon-webhook" class="regular-text code" name="paypal_webhook_id" value="<?php echo esc_attr( $settings['paypal_webhook_id'] ); ?>"><p class="description"><?php echo esc_html( rest_url( 'membexa/v1/paypal/webhook' ) ); ?></p></td></tr>
			</table>
			<?php submit_button( __( 'Save PayPal Settings', 'membexa-paypal' ) ); ?>
		</form>
	</div>
	<?php
}

function plan_field( $post ) {
	$value = get_post_meta( $post->ID, '_membexa_paypal_plan_id', true );
	?>
	<tr><th><label for="membexa_paypal_plan_id"><?php esc_html_e( 'PayPal Plan ID', 'membexa-paypal' ); ?></label></th><td><input class="regular-text code" id="membexa_paypal_plan_id" name="membexa_paypal_plan_id" value="<?php echo esc_attr( $value ); ?>" placeholder="P-..."><p class="description"><?php esc_html_e( 'Required only for monthly/yearly PayPal subscriptions.', 'membexa-paypal' ); ?></p></td></tr>
	<?php
}

function save_plan_field( $post_id ) {
	update_post_meta( $post_id, '_membexa_paypal_plan_id', isset( $_POST['membexa_paypal_plan_id'] ) ? sanitize_text_field( wp_unslash( $_POST['membexa_paypal_plan_id'] ) ) : '' );
}
