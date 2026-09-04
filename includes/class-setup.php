<?php
/**
 * One-click setup and payment-provider resource center.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides safe setup automation without storing third-party credentials outside WordPress. */
final class Setup {
	/** Register hooks. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 8 );
		add_action( 'admin_post_membexa_setup_pages', array( $this, 'setup_pages' ) );
	}

	/** Add Setup submenu. */
	public function menu() {
		add_submenu_page( 'membexa', __( 'Membexa Setup', 'membexa' ), __( 'Setup', 'membexa' ), 'manage_options', 'membexa-setup', array( $this, 'page' ) );
	}

	/** Process one-click page setup. */
	public function setup_pages() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to configure Membexa.', 'membexa' ) );
		}
		check_admin_referer( 'membexa_setup_pages' );
		Pages::ensure();
		wp_safe_redirect( add_query_arg( 'membexa_setup', 'pages_ready', admin_url( 'admin.php?page=membexa-setup' ) ) );
		exit;
	}

	/** Render setup center. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = Pages::status();
		?>
		<div class="wrap membexa-admin">
			<h1><?php esc_html_e( 'Membexa Setup', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'Create the required member pages, connect payment providers, and verify the essentials from one place.', 'membexa' ); ?></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag after a nonce-protected action. ?>
			<?php if ( isset( $_GET['membexa_setup'] ) && 'pages_ready' === sanitize_key( wp_unslash( $_GET['membexa_setup'] ) ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Membership pages were created/repaired and assigned successfully.', 'membexa' ); ?></p></div>
			<?php endif; ?>

			<div class="card membexa-card">
				<h2><?php esc_html_e( '1. Membership pages', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'Membexa creates these pages automatically on activation. Use the button below at any time to recreate missing pages and reconnect the page settings.', 'membexa' ); ?></p>
				<table class="widefat striped"><tbody>
				<?php foreach ( $status as $item ) : ?>
					<tr><td><strong><?php echo esc_html( $item['healthy'] ? '✓' : '!' ); ?></strong></td><td><?php echo esc_html( $item['title'] ); ?></td><td><code><?php echo esc_html( $item['content'] ); ?></code></td><td><?php echo esc_html( $item['healthy'] ? __( 'Ready', 'membexa' ) : __( 'Needs setup', 'membexa' ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=membexa_setup_pages' ), 'membexa_setup_pages' ) ); ?>"><?php esc_html_e( 'Create / Repair Pages', 'membexa' ); ?></a></p>
			</div>

			<div class="card membexa-card">
				<h2><?php esc_html_e( '2. Payment provider setup', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'Open the official provider portals below. Credentials remain between your WordPress site and the provider; Membexa does not send them to the plugin author.', 'membexa' ); ?></p>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Provider', 'membexa' ); ?></th><th><?php esc_html_e( 'Official portal', 'membexa' ); ?></th><th><?php esc_html_e( 'Configure Membexa', 'membexa' ); ?></th></tr></thead>
					<tbody>
						<tr><td><strong>Stripe</strong></td><td><a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Stripe API keys', 'membexa' ); ?> ↗</a></td><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-settings&tab=payments' ) ); ?>"><?php esc_html_e( 'Payment settings', 'membexa' ); ?></a></td></tr>
						<tr><td><strong>PayPal</strong></td><td><a class="button" href="https://developer.paypal.com/dashboard/applications/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Set up PayPal', 'membexa' ); ?> ↗</a><br><small><?php esc_html_e( 'Log in with your PayPal account, create/select a REST app, then copy its Client ID and Secret into Membexa.', 'membexa' ); ?></small></td><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-settings&tab=payments' ) ); ?>"><?php esc_html_e( 'Payment settings', 'membexa' ); ?></a></td></tr>
						<tr><td><strong>bKash</strong></td><td><a href="https://www.bkash.com/en/business" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'bKash Business', 'membexa' ); ?> ↗</a></td><td><a href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-settings&tab=payments' ) ); ?>"><?php esc_html_e( 'Payment settings', 'membexa' ); ?></a></td></tr>
					</tbody>
				</table>
			</div>

			<div class="card membexa-card">
				<h2><?php esc_html_e( 'About automatic PayPal connection', 'membexa' ); ?></h2>
				<p><?php esc_html_e( 'A normal PayPal Business login cannot securely expose a REST app Client Secret to an arbitrary WordPress plugin. PayPal automatic seller credential sharing is a separate Partner Referrals onboarding product and requires the software provider to be an approved PayPal partner. Membexa therefore does not imitate this flow or ask users to paste PayPal passwords.', 'membexa' ); ?></p>
				<p><?php esc_html_e( 'The Set up PayPal button opens the official PayPal Developer portal. Automatic Connect with PayPal can be enabled in a future release after Membexa receives PayPal partner approval and can use PayPal’s approved onboarding flow.', 'membexa' ); ?></p>
			</div>
		</div>
		<?php
	}
}
