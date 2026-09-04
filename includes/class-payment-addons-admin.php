<?php
/**
 * Payment add-on manager.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps gateway credentials out of core and routes payment setup to add-on plugins.
 */
final class Payment_Addons_Admin {
	/** Register hooks. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_init', array( $this, 'redirect_legacy_payment_tab' ) );
	}

	/** Add the add-on manager under Membexa. */
	public function menu() {
		add_submenu_page(
			'membexa',
			__( 'Payment Add-ons', 'membexa' ),
			__( 'Payment Add-ons', 'membexa' ),
			'manage_options',
			'membexa-payment-addons',
			array( $this, 'page' )
		);
	}

	/** Redirect the old core Payments tab so credentials are never edited in core. */
	public function redirect_legacy_payment_tab() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation-only redirect.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation-only redirect.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( 'membexa-settings' === $page && 'payments' === $tab ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect from PayPal verification result.
			$paypal_verified = isset( $_GET['membexa_paypal_verified'] );
			$target = $paypal_verified && Gateways::is_registered( 'paypal' )
				? admin_url( 'admin.php?page=membexa-paypal-gateway' )
				: admin_url( 'admin.php?page=membexa-payment-addons' );
			wp_safe_redirect( $target );
			exit;
		}
	}

	/** Render installed/active gateway add-ons. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$gateways = Gateways::all();
		?>
		<div class="wrap membexa-admin">
			<h1><?php esc_html_e( 'Membexa Payment Add-ons', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'Membexa Core does not configure payment providers. Install only the separate gateway add-ons you need. WooCommerce-connected products continue to use the payment gateways configured in WooCommerce checkout.', 'membexa' ); ?></p>
			<?php if ( empty( $gateways ) ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'No Membexa payment gateway add-on is active. Free memberships and WooCommerce-linked memberships can still work normally.', 'membexa' ); ?></p></div>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'Add-on', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Setup', 'membexa' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $gateways as $gateway ) : ?>
						<?php $enabled = is_callable( $gateway['enabled_callback'] ) ? (bool) call_user_func( $gateway['enabled_callback'] ) : true; ?>
						<tr>
							<td><strong><?php echo esc_html( $gateway['label'] ); ?></strong></td>
							<td><?php echo esc_html( $gateway['addon_version'] ? sprintf( 'v%s', $gateway['addon_version'] ) : __( 'Active', 'membexa' ) ); ?></td>
							<td><?php echo esc_html( $enabled ? __( 'Configured', 'membexa' ) : __( 'Needs setup', 'membexa' ) ); ?></td>
							<td><?php if ( ! empty( $gateway['settings_url'] ) ) : ?><a class="button button-secondary" href="<?php echo esc_url( $gateway['settings_url'] ); ?>"><?php esc_html_e( 'Open settings', 'membexa' ); ?></a><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<div class="card membexa-card">
				<h2><?php esc_html_e( 'How payments work now', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'Standalone Membexa plans use active Membexa gateway add-ons. Products connected through WooCommerce use WooCommerce checkout, so any compatible WooCommerce payment gateway plugin can be used there without being bundled into Membexa Core.', 'membexa' ); ?></p>
			</div>
		</div>
		<?php
	}
}
