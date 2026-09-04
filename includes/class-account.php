<?php
/**
 * Account routing and WooCommerce My Account integration.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps one WordPress identity while adapting the member-facing account UI.
 */
final class Account {
	const ENDPOINT = 'membexa-memberships';

	/** Register account hooks. */
	public function hooks() {
		add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ), 20 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'endpoint_content' ) );
		add_action( 'activated_plugin', array( $this, 'maybe_refresh_rewrites' ) );
		add_action( 'deactivated_plugin', array( $this, 'maybe_refresh_rewrites' ) );
		add_action( 'woocommerce_register_form', array( $this, 'registration_plan_field' ) );
		add_action( 'woocommerce_login_form', array( $this, 'login_plan_field' ) );
		add_action( 'woocommerce_created_customer', array( $this, 'created_customer' ), 20, 1 );
		add_filter( 'woocommerce_registration_redirect', array( $this, 'registration_redirect' ), 20 );
		add_filter( 'woocommerce_login_redirect', array( $this, 'login_redirect' ), 20, 2 );
	}

	/** Register the WooCommerce My Account endpoint when WooCommerce is active. */
	public static function register_endpoint() {
		if ( self::woocommerce_active() ) {
			add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
		}
	}

	/** Whether WooCommerce is active. */
	public static function woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' );
	}

	/** Whether WooCommerce Subscriptions is active. */
	public static function subscriptions_active() {
		return class_exists( 'WC_Subscriptions' ) || function_exists( 'wcs_get_subscription' );
	}

	/** Resolve the configured account experience. */
	public static function mode() {
		$settings = Settings::integrations();
		$mode     = isset( $settings['account_mode'] ) ? sanitize_key( $settings['account_mode'] ) : 'auto';

		if ( 'auto' === $mode ) {
			return self::woocommerce_active() ? 'woocommerce' : 'membexa';
		}
		if ( 'woocommerce' === $mode && ! self::woocommerce_active() ) {
			return 'membexa';
		}
		return in_array( $mode, array( 'membexa', 'woocommerce', 'custom' ), true ) ? $mode : 'membexa';
	}

	/** Whether the member-facing account should use WooCommerce My Account. */
	public static function uses_woocommerce() {
		return 'woocommerce' === self::mode();
	}

	/** Get the account URL for the active account experience. */
	public static function account_url() {
		if ( self::uses_woocommerce() ) {
			$base = wc_get_page_permalink( 'myaccount' );
			return function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( self::ENDPOINT ) : $base;
		}

		$general = Settings::general();
		$page_id = absint( $general['account_page_id'] );
		return $page_id ? get_permalink( $page_id ) : home_url( '/' );
	}

	/**
	 * Get the preferred login URL.
	 *
	 * @param string $redirect Optional post-login destination.
	 * @return string
	 */
	public static function login_url( $redirect = '' ) {
		if ( self::uses_woocommerce() ) {
			$url = wc_get_page_permalink( 'myaccount' );
			return $redirect ? add_query_arg( 'redirect_to', $redirect, $url ) : $url;
		}

		$settings = Settings::integrations();
		$page_id  = absint( $settings['login_page_id'] );
		if ( $page_id ) {
			$url = get_permalink( $page_id );
			return $redirect ? add_query_arg( 'redirect_to', $redirect, $url ) : $url;
		}
		return wp_login_url( $redirect );
	}

	/**
	 * Get the preferred registration URL.
	 *
	 * @param int $plan_id Optional preselected plan.
	 * @return string
	 */
	public static function register_url( $plan_id = 0 ) {
		$settings = Settings::integrations();
		if ( self::uses_woocommerce() && self::woo_registration_enabled() ) {
			$url = wc_get_page_permalink( 'myaccount' );
		} else {
			$page_id = absint( $settings['join_page_id'] );
			$url     = $page_id ? get_permalink( $page_id ) : ( self::woocommerce_active() ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' ) );
		}
		return $plan_id ? add_query_arg( 'membexa_plan', absint( $plan_id ), $url ) : $url;
	}

	/** Whether WooCommerce My Account registration is enabled. */
	public static function woo_registration_enabled() {
		return self::woocommerce_active() && 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'no' );
	}

	/** Refresh endpoint rewrite rules when WooCommerce is activated or deactivated. */
	public function maybe_refresh_rewrites( $plugin ) {
		if ( 'woocommerce/woocommerce.php' !== (string) $plugin ) {
			return;
		}
		self::register_endpoint();
		flush_rewrite_rules( false );
	}

	/** Add a selected Membexa plan to the WooCommerce registration form. */
	public function registration_plan_field() {
		$plan_id = self::requested_plan_id();
		if ( ! $plan_id ) {
			return;
		}
		$plan = Plan::get( $plan_id );
		if ( ! $plan ) {
			return;
		}
		wp_nonce_field( 'membexa_woo_account_plan', 'membexa_woo_plan_nonce' );
		?>
		<input type="hidden" name="membexa_plan" value="<?php echo esc_attr( $plan_id ); ?>">
		<p class="membexa-notice"><?php /* translators: %s: membership plan name. */ echo esc_html( sprintf( __( 'Selected membership: %s. After account creation, Membexa will continue this membership flow.', 'membexa' ), $plan['name'] ) ); ?></p>
		<?php
	}

	/** Preserve a selected Membexa plan through WooCommerce login. */
	public function login_plan_field() {
		$plan_id = self::requested_plan_id();
		if ( ! $plan_id ) {
			return;
		}
		wp_nonce_field( 'membexa_woo_account_plan', 'membexa_woo_plan_nonce' );
		?>
		<input type="hidden" name="membexa_plan" value="<?php echo esc_attr( $plan_id ); ?>">
		<?php
	}

	/** Activate a selected free plan after WooCommerce creates the customer. */
	public function created_customer( $customer_id ) {
		$plan_id = self::posted_plan_id();
		$plan    = $plan_id ? Plan::get( $plan_id ) : null;
		if ( ! $plan || ( 'free' !== $plan['billing'] && 0.0 !== (float) $plan['price'] ) ) {
			return;
		}
		if ( ! Subscriptions::user_has_plan( $customer_id, array( $plan_id ) ) ) {
			Subscriptions::create( $customer_id, $plan_id, 'active', 'free', 'woo-register:' . absint( $customer_id ) . ':plan:' . absint( $plan_id ) );
		}
	}

	/** Continue the selected Membexa plan after WooCommerce registration. */
	public function registration_redirect( $redirect ) {
		$plan_id = self::posted_plan_id();
		if ( ! $plan_id ) {
			return $redirect;
		}
		$plan = Plan::get( $plan_id );
		if ( $plan && ( 'free' === $plan['billing'] || 0.0 === (float) $plan['price'] ) ) {
			return add_query_arg( 'membexa_notice', 'membership_active', self::account_url() );
		}
		return self::continue_plan_url( $plan_id );
	}

	/** Continue the selected Membexa plan after WooCommerce login. */
	public function login_redirect( $redirect, $user ) {
		unset( $user );
		$plan_id = self::posted_plan_id();
		return $plan_id ? self::continue_plan_url( $plan_id ) : $redirect;
	}

	/** Build the pricing continuation URL for a selected plan. */
	private static function continue_plan_url( $plan_id ) {
		$general = Settings::general();
		$url     = $general['pricing_page_id'] ? get_permalink( $general['pricing_page_id'] ) : home_url( '/' );
		return add_query_arg(
			array(
				'membexa_plan'   => absint( $plan_id ),
				'membexa_notice' => 'account_ready',
			),
			$url
		);
	}

	/** Read and validate a plan from the current account page query string. */
	private static function requested_plan_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only plan preselection used to render WooCommerce account forms.
		$plan_id = isset( $_GET['membexa_plan'] ) ? absint( $_GET['membexa_plan'] ) : 0;
		return $plan_id && Plan::get( $plan_id ) ? $plan_id : 0;
	}

	/** Read a plan posted through the Membexa nonce embedded in WooCommerce account forms. */
	private static function posted_plan_id() {
		if ( ! isset( $_POST['membexa_woo_plan_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_woo_plan_nonce'] ) ), 'membexa_woo_account_plan' ) ) {
			return 0;
		}
		$plan_id = isset( $_POST['membexa_plan'] ) ? absint( $_POST['membexa_plan'] ) : 0;
		return $plan_id && Plan::get( $plan_id ) ? $plan_id : 0;
	}

	/** Add the Membexa memberships tab to WooCommerce My Account. */
	public function account_menu_items( $items ) {
		if ( ! self::uses_woocommerce() || ! is_array( $items ) ) {
			return $items;
		}

		$new = array();
		foreach ( $items as $key => $label ) {
			if ( 'edit-account' === $key || 'customer-logout' === $key ) {
				$new[ self::ENDPOINT ] = __( 'Memberships', 'membexa' );
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = __( 'Memberships', 'membexa' );
		}
		return $new;
	}

	/** Render Membexa memberships inside WooCommerce My Account. */
	public function endpoint_content() {
		echo do_shortcode( '[membexa_account]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode performs its own contextual escaping.
	}
}
