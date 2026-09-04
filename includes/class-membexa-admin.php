<?php
/**
 * WordPress-native administration screens.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'configuration_notice' ) );
	}

	public function menu() {
		add_menu_page( __( 'Membexa', 'membexa' ), __( 'Membexa', 'membexa' ), 'manage_options', 'membexa', array( $this, 'dashboard' ), 'dashicons-groups', 56 );
		add_submenu_page( 'membexa', __( 'Membexa Dashboard', 'membexa' ), __( 'Dashboard', 'membexa' ), 'manage_options', 'membexa', array( $this, 'dashboard' ) );
		add_submenu_page( 'membexa', __( 'Subscriptions', 'membexa' ), __( 'Subscriptions', 'membexa' ), 'manage_options', 'membexa-subscriptions', array( $this, 'subscriptions' ) );
		add_submenu_page( 'membexa', __( 'Members', 'membexa' ), __( 'Members', 'membexa' ), 'manage_options', 'membexa-members', array( $this, 'members' ) );
		add_submenu_page( 'membexa', __( 'Settings', 'membexa' ), __( 'Settings', 'membexa' ), 'manage_options', 'membexa-settings', array( $this, 'settings' ) );
	}

	public function assets( $hook ) {
		$screen = get_current_screen();
		if ( ( $screen && Plan::POST_TYPE === $screen->post_type ) || false !== strpos( (string) $hook, 'membexa' ) ) {
			wp_enqueue_style( 'membexa-admin', MEMBEXA_URL . 'assets/css/admin.css', array(), MEMBEXA_VERSION );
		}
	}

	public function configuration_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ( false === strpos( $screen->id, 'membexa' ) && Plan::POST_TYPE !== $screen->post_type ) ) {
			return;
		}
		$payments = Settings::payments();
		if ( ! empty( $payments['stripe_enabled'] ) && ( ! Settings::stripe_secret_key() || ! Settings::stripe_webhook_secret() ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Membexa Stripe is enabled, but a secret key or webhook signing secret is missing.', 'membexa' ) . '</p></div>';
		}
	}

	public function dashboard() {
		global $wpdb;
		$this->guard();
		$subscriptions = DB::subscriptions_table();
		$transactions  = DB::transactions_table();
		$active        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$subscriptions} WHERE status IN ('active','trialing')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$past_due      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$subscriptions} WHERE status = 'past_due'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_members = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$subscriptions} WHERE user_id > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$payments      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$transactions} WHERE status IN ('paid','complete','succeeded')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap membexa-admin"><h1><?php esc_html_e( 'Membexa', 'membexa' ); ?></h1><p class="membexa-lead"><?php esc_html_e( 'Memberships, subscriptions, gated content, and member billing in one WordPress-native workspace.', 'membexa' ); ?></p>
		<div class="membexa-stat-grid"><div class="membexa-stat"><strong><?php echo esc_html( number_format_i18n( $active ) ); ?></strong><span><?php esc_html_e( 'Active subscriptions', 'membexa' ); ?></span></div><div class="membexa-stat"><strong><?php echo esc_html( number_format_i18n( $total_members ) ); ?></strong><span><?php esc_html_e( 'Members', 'membexa' ); ?></span></div><div class="membexa-stat"><strong><?php echo esc_html( number_format_i18n( $past_due ) ); ?></strong><span><?php esc_html_e( 'Past due', 'membexa' ); ?></span></div><div class="membexa-stat"><strong><?php echo esc_html( number_format_i18n( $payments ) ); ?></strong><span><?php esc_html_e( 'Recorded payments', 'membexa' ); ?></span></div></div>
		<div class="card membexa-card"><h2><?php esc_html_e( 'Getting started', 'membexa' ); ?></h2><ol><li><?php esc_html_e( 'Create and publish at least one membership plan.', 'membexa' ); ?></li><li><?php esc_html_e( 'Add [membexa_pricing], [membexa_register], and [membexa_account] to your site pages.', 'membexa' ); ?></li><li><?php esc_html_e( 'Select the account and pricing pages in Settings.', 'membexa' ); ?></li><li><?php esc_html_e( 'For paid plans, configure Stripe and add each plan’s Stripe Price ID.', 'membexa' ); ?></li><li><?php esc_html_e( 'Restrict content using the Membexa Access panel in the editor.', 'membexa' ); ?></li></ol></div></div>
		<?php
	}

	public function subscriptions() {
		global $wpdb;
		$this->guard();
		$table      = DB::subscriptions_table();
		$page       = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$per_page   = 30;
		$offset     = ( $page - 1 ) * $per_page;
		$status     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$allowed    = array( 'pending', 'active', 'trialing', 'past_due', 'canceled', 'expired' );
		$where      = '';
		$query_args = array();
		if ( in_array( $status, $allowed, true ) ) {
			$where        = ' WHERE status = %s';
			$query_args[] = $status;
		}
		$count_sql = "SELECT COUNT(*) FROM {$table}{$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total     = $query_args ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $query_args ) ) : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$list_sql  = "SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$args      = array_merge( $query_args, array( $per_page, $offset ) );
		$rows      = $wpdb->get_results( $wpdb->prepare( $list_sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<div class="wrap membexa-admin"><h1><?php esc_html_e( 'Subscriptions', 'membexa' ); ?></h1><form method="get" class="membexa-filter"><input type="hidden" name="page" value="membexa-subscriptions"><label class="screen-reader-text" for="membexa-status"><?php esc_html_e( 'Filter by status', 'membexa' ); ?></label><select id="membexa-status" name="status"><option value=""><?php esc_html_e( 'All statuses', 'membexa' ); ?></option><?php foreach ( $allowed as $item ) : ?><option value="<?php echo esc_attr( $item ); ?>" <?php selected( $status, $item ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $item ) ) ); ?></option><?php endforeach; ?></select><?php submit_button( __( 'Filter', 'membexa' ), 'secondary', '', false ); ?></form>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'ID', 'membexa' ); ?></th><th><?php esc_html_e( 'Member', 'membexa' ); ?></th><th><?php esc_html_e( 'Plan', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'Started', 'membexa' ); ?></th><th><?php esc_html_e( 'End / Renewal', 'membexa' ); ?></th></tr></thead><tbody><?php if ( ! $rows ) : ?><tr><td colspan="7"><?php esc_html_e( 'No subscriptions found.', 'membexa' ); ?></td></tr><?php else : foreach ( $rows as $row ) : $user = get_userdata( $row->user_id ); $plan = Plan::get( $row->plan_id ); ?><tr><td><?php echo esc_html( $row->id ); ?></td><td><?php echo esc_html( $user ? $user->display_name . ' (' . $user->user_email . ')' : __( 'Deleted user', 'membexa' ) ); ?></td><td><?php echo esc_html( $plan ? $plan['name'] : __( 'Deleted plan', 'membexa' ) ); ?></td><td><span class="membexa-status membexa-status-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row->status ) ) ); ?></span></td><td><?php echo esc_html( ucfirst( $row->gateway ) ); ?></td><td><?php echo $row->started_at ? esc_html( get_date_from_gmt( $row->started_at ) ) : '—'; ?></td><td><?php echo $row->ends_at ? esc_html( get_date_from_gmt( $row->ends_at ) ) : '—'; ?></td></tr><?php endforeach; endif; ?></tbody></table>
		<?php $pages = (int) ceil( $total / $per_page ); if ( $pages > 1 ) : ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $page, 'total' => $pages ) ) ); ?></div></div><?php endif; ?></div>
		<?php
	}

	public function members() {
		global $wpdb;
		$this->guard();
		$table = DB::subscriptions_table();
		$users = $wpdb->users;
		$rows  = $wpdb->get_results( "SELECT u.ID, u.display_name, u.user_email, COUNT(s.id) AS memberships, SUM(CASE WHEN s.status IN ('active','trialing') THEN 1 ELSE 0 END) AS active_memberships FROM {$users} u INNER JOIN {$table} s ON s.user_id = u.ID GROUP BY u.ID, u.display_name, u.user_email ORDER BY u.ID DESC LIMIT 200" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap membexa-admin"><h1><?php esc_html_e( 'Members', 'membexa' ); ?></h1><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Member', 'membexa' ); ?></th><th><?php esc_html_e( 'Email', 'membexa' ); ?></th><th><?php esc_html_e( 'Memberships', 'membexa' ); ?></th><th><?php esc_html_e( 'Active', 'membexa' ); ?></th></tr></thead><tbody><?php if ( ! $rows ) : ?><tr><td colspan="4"><?php esc_html_e( 'No members found.', 'membexa' ); ?></td></tr><?php else : foreach ( $rows as $row ) : ?><tr><td><a href="<?php echo esc_url( get_edit_user_link( $row->ID ) ); ?>"><?php echo esc_html( $row->display_name ); ?></a></td><td><?php echo esc_html( $row->user_email ); ?></td><td><?php echo esc_html( $row->memberships ); ?></td><td><?php echo esc_html( $row->active_memberships ); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
		<?php
	}

	public function settings() {
		$this->guard();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs = array( 'general' => __( 'General', 'membexa' ), 'payments' => __( 'Payments', 'membexa' ), 'emails' => __( 'Emails', 'membexa' ), 'data' => __( 'Privacy & Data', 'membexa' ) );
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'general';
		?>
		<div class="wrap membexa-admin"><h1><?php esc_html_e( 'Membexa Settings', 'membexa' ); ?></h1><nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'membexa' ); ?>"><?php foreach ( $tabs as $slug => $label ) : ?><a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-settings&tab=' . $slug ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav><form method="post" action="options.php"><?php $this->settings_tab( $tab ); submit_button(); ?></form></div>
		<?php
	}

	private function settings_tab( $tab ) {
		if ( 'payments' === $tab ) {
			$settings = Settings::payments(); settings_fields( 'membexa_payments_group' );
			?> <table class="form-table" role="presentation"><tr><th><?php esc_html_e( 'Stripe Checkout', 'membexa' ); ?></th><td><label><input type="checkbox" name="membexa_payments[stripe_enabled]" value="1" <?php checked( $settings['stripe_enabled'] ); ?>> <?php esc_html_e( 'Enable Stripe Checkout', 'membexa' ); ?></label><p class="description"><?php esc_html_e( 'Payments are collected on Stripe-hosted Checkout pages; card details are not processed by your WordPress site.', 'membexa' ); ?></p></td></tr><tr><th><label for="membexa-stripe-key"><?php esc_html_e( 'Secret key', 'membexa' ); ?></label></th><td><input id="membexa-stripe-key" class="regular-text code" type="password" autocomplete="new-password" name="membexa_payments[stripe_secret_key]" value="<?php echo esc_attr( $settings['stripe_secret_key'] ); ?>"><p class="description"><?php esc_html_e( 'For production, you can define MEMBEXA_STRIPE_SECRET_KEY in wp-config.php instead of storing the key in the database.', 'membexa' ); ?></p></td></tr><tr><th><label for="membexa-webhook-secret"><?php esc_html_e( 'Webhook signing secret', 'membexa' ); ?></label></th><td><input id="membexa-webhook-secret" class="regular-text code" type="password" autocomplete="new-password" name="membexa_payments[stripe_webhook_secret]" value="<?php echo esc_attr( $settings['stripe_webhook_secret'] ); ?>"><p class="description"><?php echo esc_html( sprintf( __( 'Webhook endpoint: %s', 'membexa' ), rest_url( 'membexa/v1/stripe/webhook' ) ) ); ?></p></td></tr></table> <?php
		} elseif ( 'emails' === $tab ) {
			$settings = Settings::emails(); settings_fields( 'membexa_emails_group' );
			?> <table class="form-table" role="presentation"><tr><th><label for="membexa-from-name"><?php esc_html_e( 'From name', 'membexa' ); ?></label></th><td><input id="membexa-from-name" class="regular-text" name="membexa_emails[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>"></td></tr><tr><th><label for="membexa-from-email"><?php esc_html_e( 'From email', 'membexa' ); ?></label></th><td><input id="membexa-from-email" class="regular-text" type="email" name="membexa_emails[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>"></td></tr><tr><th><?php esc_html_e( 'Notifications', 'membexa' ); ?></th><td><label><input type="checkbox" name="membexa_emails[activation_enabled]" value="1" <?php checked( $settings['activation_enabled'] ); ?>> <?php esc_html_e( 'Membership activated', 'membexa' ); ?></label><br><label><input type="checkbox" name="membexa_emails[cancel_enabled]" value="1" <?php checked( $settings['cancel_enabled'] ); ?>> <?php esc_html_e( 'Membership canceled', 'membexa' ); ?></label></td></tr></table> <?php
		} elseif ( 'data' === $tab ) {
			$settings = Settings::data(); settings_fields( 'membexa_data_group' );
			?> <table class="form-table" role="presentation"><tr><th><?php esc_html_e( 'Uninstall cleanup', 'membexa' ); ?></th><td><label><input type="checkbox" name="membexa_data[delete_on_uninstall]" value="1" <?php checked( $settings['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Permanently delete Membexa plans, access rules, options, subscriptions, and transactions when the plugin is uninstalled.', 'membexa' ); ?></label><p class="description"><strong><?php esc_html_e( 'This cannot be undone.', 'membexa' ); ?></strong></p></td></tr></table> <?php
		} else {
			$settings = Settings::general(); settings_fields( 'membexa_general_group' );
			?> <table class="form-table" role="presentation"><tr><th><label for="membexa-currency"><?php esc_html_e( 'Default currency', 'membexa' ); ?></label></th><td><input id="membexa-currency" class="small-text" maxlength="3" name="membexa_general[default_currency]" value="<?php echo esc_attr( $settings['default_currency'] ); ?>"><p class="description"><?php esc_html_e( 'Three-letter ISO currency code.', 'membexa' ); ?></p></td></tr><tr><th><label for="membexa-pricing-page"><?php esc_html_e( 'Pricing page', 'membexa' ); ?></label></th><td><?php wp_dropdown_pages( array( 'name' => 'membexa_general[pricing_page_id]', 'id' => 'membexa-pricing-page', 'selected' => $settings['pricing_page_id'], 'show_option_none' => __( '— Select —', 'membexa' ) ) ); ?></td></tr><tr><th><label for="membexa-account-page"><?php esc_html_e( 'Account page', 'membexa' ); ?></label></th><td><?php wp_dropdown_pages( array( 'name' => 'membexa_general[account_page_id]', 'id' => 'membexa-account-page', 'selected' => $settings['account_page_id'], 'show_option_none' => __( '— Select —', 'membexa' ) ) ); ?></td></tr><tr><th><label for="membexa-access-message"><?php esc_html_e( 'Restricted content message', 'membexa' ); ?></label></th><td><textarea id="membexa-access-message" class="large-text" rows="4" name="membexa_general[access_message]"><?php echo esc_textarea( $settings['access_message'] ); ?></textarea></td></tr></table> <?php
		}
	}

	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Membexa.', 'membexa' ) );
		}
	}
}
