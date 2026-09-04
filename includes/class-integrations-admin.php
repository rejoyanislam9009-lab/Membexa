<?php
/**
 * Account and commerce integration administration.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a focused integration settings screen without coupling it to core admin reports.
 */
final class Integrations_Admin {
	/** Register admin hooks. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 24 );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/** Register the Accounts & Commerce submenu. */
	public function menu() {
		add_submenu_page(
			'membexa',
			__( 'Membexa Accounts & Commerce', 'membexa' ),
			__( 'Accounts & Commerce', 'membexa' ),
			'manage_options',
			'membexa-integrations',
			array( $this, 'page' )
		);
	}

	/** Render integration settings. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Membexa integrations.', 'membexa' ) );
		}

		$settings = Settings::integrations();
		?>
		<div class="wrap membexa-admin">
			<h1><?php esc_html_e( 'Accounts & Commerce', 'membexa' ); ?></h1>
			<p class="membexa-lead"><?php esc_html_e( 'Choose one customer account experience while keeping WordPress as the single user identity source.', 'membexa' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'membexa_integrations_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="membexa-account-mode"><?php esc_html_e( 'Account experience', 'membexa' ); ?></label></th>
						<td>
							<select id="membexa-account-mode" name="membexa_integrations[account_mode]">
								<option value="auto" <?php selected( $settings['account_mode'], 'auto' ); ?>><?php esc_html_e( 'Auto / Smart (recommended)', 'membexa' ); ?></option>
								<option value="membexa" <?php selected( $settings['account_mode'], 'membexa' ); ?>><?php esc_html_e( 'Membexa standalone account', 'membexa' ); ?></option>
								<option value="woocommerce" <?php selected( $settings['account_mode'], 'woocommerce' ); ?>><?php esc_html_e( 'WooCommerce My Account', 'membexa' ); ?></option>
								<option value="custom" <?php selected( $settings['account_mode'], 'custom' ); ?>><?php esc_html_e( 'Custom / selected pages', 'membexa' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Auto uses WooCommerce My Account when WooCommerce is active; otherwise Membexa uses its standalone account page. WordPress wp-login.php is never disabled.', 'membexa' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="membexa-join-page"><?php esc_html_e( 'Membexa Join / Register fallback page', 'membexa' ); ?></label></th>
						<td><?php $this->page_dropdown( 'membexa_integrations[join_page_id]', 'membexa-join-page', $settings['join_page_id'] ); ?><p class="description"><?php esc_html_e( 'Use a page containing [membexa_register]. In Smart mode it is the fallback when WooCommerce My Account registration is disabled.', 'membexa' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="membexa-login-page"><?php esc_html_e( 'Membexa Login fallback page', 'membexa' ); ?></label></th>
						<td><?php $this->page_dropdown( 'membexa_integrations[login_page_id]', 'membexa-login-page', $settings['login_page_id'] ); ?><p class="description"><?php esc_html_e( 'Optional page containing [membexa_login]. If empty, core WordPress login remains the safe fallback outside WooCommerce mode.', 'membexa' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Order lifecycle', 'membexa' ); ?></th>
						<td><label><input type="checkbox" name="membexa_integrations[revoke_on_refund]" value="1" <?php checked( $settings['revoke_on_refund'] ); ?>> <?php esc_html_e( 'Revoke only the memberships granted by a WooCommerce order when that order is refunded or cancelled.', 'membexa' ); ?></label><p class="description"><?php esc_html_e( 'Memberships acquired independently from another order or standalone Membexa checkout are not removed.', 'membexa' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<div class="card membexa-card">
				<h2><?php esc_html_e( 'Current routing', 'membexa' ); ?></h2>
				<p><strong><?php esc_html_e( 'Resolved account mode:', 'membexa' ); ?></strong> <?php echo esc_html( ucfirst( Account::mode() ) ); ?></p>
				<p><strong><?php esc_html_e( 'WooCommerce:', 'membexa' ); ?></strong> <?php echo esc_html( Account::woocommerce_active() ? __( 'Active', 'membexa' ) : __( 'Not active', 'membexa' ) ); ?></p>
				<?php if ( Account::woocommerce_active() ) : ?>
					<p><strong><?php esc_html_e( 'My Account registration:', 'membexa' ); ?></strong> <?php echo esc_html( Account::woo_registration_enabled() ? __( 'Enabled', 'membexa' ) : __( 'Disabled', 'membexa' ) ); ?></p>
					<p><?php esc_html_e( 'Membexa adds a Memberships endpoint inside WooCommerce My Account. Orders, Downloads, Addresses, Payment Methods, and Account Details stay owned by WooCommerce.', 'membexa' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** Render a WordPress page selector. */
	private function page_dropdown( $name, $id, $selected ) {
		// Core generates and escapes the complete select markup.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_dropdown_pages(
			array(
				'name'             => $name,
				'id'               => $id,
				'selected'         => absint( $selected ),
				'show_option_none' => esc_html__( '— Select —', 'membexa' ),
				'echo'             => false,
			)
		);
	}

	/** Show only actionable integration warnings on Membexa screens. */
	public function notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'membexa' ) ) {
			return;
		}
		$settings = Settings::integrations();
		if ( 'woocommerce' === $settings['account_mode'] && ! Account::woocommerce_active() ) {
			$this->warning( __( 'WooCommerce My Account is selected, but WooCommerce is not active. Membexa is safely falling back to its standalone account.', 'membexa' ) );
			return;
		}
		if ( Account::uses_woocommerce() && ! Account::woo_registration_enabled() && ! absint( $settings['join_page_id'] ) ) {
			$this->warning( __( 'Smart Account is using WooCommerce My Account, but My Account registration is disabled and no Membexa Join fallback page is selected. Enable WooCommerce My Account registration or select a fallback Join page.', 'membexa' ) );
		}
	}

	/** Render an admin warning. */
	private function warning( $message ) {
		?>
		<div class="notice notice-warning"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}
}
