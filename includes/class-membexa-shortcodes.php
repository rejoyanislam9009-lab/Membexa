<?php
/**
 * Front-end shortcodes and account actions.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'template_redirect', array( $this, 'process_actions' ) );
	}

	public function register() {
		add_shortcode( 'membexa_pricing', array( $this, 'pricing' ) );
		add_shortcode( 'membexa_register', array( $this, 'register_form' ) );
		add_shortcode( 'membexa_login', array( $this, 'login_form' ) );
		add_shortcode( 'membexa_account', array( $this, 'account' ) );
	}

	public function process_actions() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || empty( $_POST['membexa_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['membexa_action'] ) );
		if ( 'register' === $action ) {
			$this->process_registration();
		} elseif ( 'checkout' === $action ) {
			$this->process_checkout();
		} elseif ( 'cancel' === $action ) {
			$this->process_cancel();
		}
	}

	private function process_registration() {
		if ( is_user_logged_in() ) {
			$this->redirect_notice( 'already_logged_in' );
		}
		if ( ! isset( $_POST['membexa_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_register_nonce'] ) ), 'membexa_register' ) ) {
			$this->redirect_notice( 'security' );
		}
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$plan_id  = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$plan     = Plan::get( $plan_id );
		if ( ! is_email( $email ) || strlen( $password ) < 8 || ! $plan ) {
			$this->redirect_notice( 'invalid_registration' );
		}
		if ( email_exists( $email ) ) {
			$this->redirect_notice( 'email_exists' );
		}
		$email_name    = strstr( $email, '@', true );
		$username_base = sanitize_user( $email_name ? $email_name : 'member', true );
		$username_base = $username_base ? $username_base : 'member';
		$username      = $username_base;
		$counter       = 1;
		while ( username_exists( $username ) ) {
			$username = $username_base . $counter;
			++$counter;
		}
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			$this->redirect_notice( 'registration_failed' );
		}
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		$this->begin_plan( $user_id, $plan );
	}

	private function process_checkout() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! isset( $_POST['membexa_checkout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_checkout_nonce'] ) ), 'membexa_checkout' ) ) {
			$this->redirect_notice( 'security' );
		}
		$plan = Plan::get( isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0 );
		if ( ! $plan ) {
			$this->redirect_notice( 'invalid_plan' );
		}
		$this->begin_plan( get_current_user_id(), $plan );
	}

	private function begin_plan( $user_id, $plan ) {
		if ( Subscriptions::user_has_plan( $user_id, array( $plan['id'] ) ) ) {
			$this->redirect_notice( 'already_member' );
		}
		if ( 'free' === $plan['billing'] || 0.0 === (float) $plan['price'] ) {
			Subscriptions::create( $user_id, $plan['id'], 'active', 'free', '' );
			$this->redirect_account( 'membership_active' );
		}
		$url = Stripe::start_checkout( $user_id, $plan['id'] );
		if ( is_wp_error( $url ) ) {
			$this->redirect_notice( 'checkout_error' );
		}
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- URL is validated as a Stripe Checkout host by the gateway.
		exit;
	}

	private function process_cancel() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! isset( $_POST['membexa_cancel_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_cancel_nonce'] ) ), 'membexa_cancel' ) ) {
			$this->redirect_account( 'security' );
		}
		$subscription = Subscriptions::get( isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0 );
		if ( ! $subscription || (int) $subscription->user_id !== get_current_user_id() ) {
			$this->redirect_account( 'invalid_subscription' );
		}
		if ( 'stripe' === $subscription->gateway && 0 === strpos( $subscription->gateway_external_id, 'sub_' ) ) {
			$result = Stripe::cancel_at_period_end( $subscription );
			$this->redirect_account( is_wp_error( $result ) ? 'cancel_error' : 'cancel_scheduled' );
		}
		Subscriptions::cancel_local( $subscription->id );
		$this->redirect_account( 'cancelled' );
	}

	public function pricing( $atts ) {
		$atts         = shortcode_atts( array( 'register_url' => '' ), $atts, 'membexa_pricing' );
		$register_url = $atts['register_url'] ? esc_url_raw( $atts['register_url'] ) : get_permalink();
		$plans        = Plan::all();
		if ( empty( $plans ) ) {
			return '<div class="membexa-notice">' . esc_html__( 'No membership plans are available yet.', 'membexa' ) . '</div>';
		}
		ob_start();
		$this->render_notice();
		?>
		<div class="membexa-pricing-grid">
			<?php foreach ( $plans as $plan ) : ?>
				<article class="membexa-plan-card">
					<h3><?php echo esc_html( $plan['name'] ); ?></h3>
					<div class="membexa-price"><?php echo esc_html( $this->format_price( $plan ) ); ?></div>
					<?php if ( $plan['description'] ) : ?><div class="membexa-plan-description"><?php echo wp_kses_post( wpautop( $plan['description'] ) ); ?></div><?php endif; ?>
					<?php if ( $plan['features'] ) : ?>
						<ul class="membexa-features">
							<?php foreach ( array_filter( array_map( 'trim', explode( "\n", $plan['features'] ) ) ) as $feature ) : ?><li><?php echo esc_html( $feature ); ?></li><?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( is_user_logged_in() ) : ?>
						<form method="post">
							<?php wp_nonce_field( 'membexa_checkout', 'membexa_checkout_nonce' ); ?>
							<input type="hidden" name="membexa_action" value="checkout"><input type="hidden" name="plan_id" value="<?php echo esc_attr( $plan['id'] ); ?>">
							<button class="membexa-button" type="submit"><?php esc_html_e( 'Choose plan', 'membexa' ); ?></button>
						</form>
					<?php else : ?>
						<a class="membexa-button" href="<?php echo esc_url( add_query_arg( 'membexa_plan', $plan['id'], $register_url ) ); ?>"><?php esc_html_e( 'Join now', 'membexa' ); ?></a>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function register_form() {
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already signed in.', 'membexa' ) . '</p>';
		}
		$plans       = Plan::all();
		$selected_id = isset( $_GET['membexa_plan'] ) ? absint( $_GET['membexa_plan'] ) : 0;
		ob_start();
		$this->render_notice();
		?>
		<form class="membexa-form" method="post">
			<?php wp_nonce_field( 'membexa_register', 'membexa_register_nonce' ); ?>
			<input type="hidden" name="membexa_action" value="register">
			<p><label for="membexa-email"><?php esc_html_e( 'Email address', 'membexa' ); ?></label><input id="membexa-email" name="email" type="email" required autocomplete="email"></p>
			<p><label for="membexa-password"><?php esc_html_e( 'Password', 'membexa' ); ?></label><input id="membexa-password" name="password" type="password" minlength="8" required autocomplete="new-password"><small><?php esc_html_e( 'Use at least 8 characters.', 'membexa' ); ?></small></p>
			<p><label for="membexa-plan"><?php esc_html_e( 'Membership plan', 'membexa' ); ?></label><select id="membexa-plan" name="plan_id" required><option value=""><?php esc_html_e( 'Select a plan', 'membexa' ); ?></option><?php foreach ( $plans as $plan ) : ?><option value="<?php echo esc_attr( $plan['id'] ); ?>" <?php selected( $selected_id, $plan['id'] ); ?>><?php echo esc_html( $plan['name'] . ' — ' . $this->format_price( $plan ) ); ?></option><?php endforeach; ?></select></p>
			<p><button class="membexa-button" type="submit"><?php esc_html_e( 'Create account', 'membexa' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	public function login_form() {
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already signed in.', 'membexa' ) . '</p>';
		}
		ob_start();
		wp_login_form( array( 'echo' => true ) );
		return ob_get_clean();
	}

	public function account() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . sprintf( wp_kses_post( __( 'Please <a href="%s">sign in</a> to view your membership account.', 'membexa' ) ), esc_url( wp_login_url( get_permalink() ) ) ) . '</p>';
		}
		$user          = wp_get_current_user();
		$subscriptions = Subscriptions::for_user( $user->ID );
		ob_start();
		$this->render_notice();
		?>
		<div class="membexa-account">
			<h3><?php echo esc_html( sprintf( __( 'Welcome, %s', 'membexa' ), $user->display_name ) ); ?></h3>
			<?php if ( empty( $subscriptions ) ) : ?><p><?php esc_html_e( 'You do not have a membership yet.', 'membexa' ); ?></p><?php else : ?>
			<table class="membexa-table"><thead><tr><th><?php esc_html_e( 'Plan', 'membexa' ); ?></th><th><?php esc_html_e( 'Status', 'membexa' ); ?></th><th><?php esc_html_e( 'Renewal / End', 'membexa' ); ?></th><th><?php esc_html_e( 'Action', 'membexa' ); ?></th></tr></thead><tbody>
			<?php foreach ( $subscriptions as $subscription ) : $plan = Plan::get( $subscription->plan_id ); ?>
			<tr><td><?php echo esc_html( $plan ? $plan['name'] : __( 'Deleted plan', 'membexa' ) ); ?></td><td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $subscription->status ) ) ); ?><?php if ( $subscription->cancel_at_period_end ) : ?> <small><?php esc_html_e( '(cancels at period end)', 'membexa' ); ?></small><?php endif; ?></td><td><?php echo $subscription->ends_at ? esc_html( get_date_from_gmt( $subscription->ends_at, get_option( 'date_format' ) ) ) : '—'; ?></td><td><?php if ( in_array( $subscription->status, array( 'active', 'trialing' ), true ) && ! $subscription->cancel_at_period_end ) : ?><form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Cancel this membership?', 'membexa' ) ); ?>');"><?php wp_nonce_field( 'membexa_cancel', 'membexa_cancel_nonce' ); ?><input type="hidden" name="membexa_action" value="cancel"><input type="hidden" name="subscription_id" value="<?php echo esc_attr( $subscription->id ); ?>"><button type="submit" class="membexa-link-button"><?php esc_html_e( 'Cancel', 'membexa' ); ?></button></form><?php else : ?>—<?php endif; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function format_price( $plan ) {
		if ( 'free' === $plan['billing'] || 0.0 === (float) $plan['price'] ) {
			return __( 'Free', 'membexa' );
		}
		$suffix = array( 'monthly' => __( '/ month', 'membexa' ), 'yearly' => __( '/ year', 'membexa' ), 'lifetime' => __( ' lifetime', 'membexa' ), 'one_time' => __( ' one-time', 'membexa' ) );
		return $plan['currency'] . ' ' . number_format_i18n( $plan['price'], 2 ) . ( isset( $suffix[ $plan['billing'] ] ) ? $suffix[ $plan['billing'] ] : '' );
	}

	private function redirect_notice( $code ) {
		$url = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'membexa_notice', sanitize_key( $code ), $url ) );
		exit;
	}

	private function redirect_account( $code ) {
		$general = Settings::general();
		$url     = $general['account_page_id'] ? get_permalink( $general['account_page_id'] ) : ( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
		wp_safe_redirect( add_query_arg( 'membexa_notice', sanitize_key( $code ), $url ) );
		exit;
	}

	private function render_notice() {
		$code = isset( $_GET['membexa_notice'] ) ? sanitize_key( wp_unslash( $_GET['membexa_notice'] ) ) : '';
		$messages = array(
			'payment_success'      => __( 'Payment received. Your membership will be activated as soon as the payment confirmation arrives.', 'membexa' ),
			'payment_cancelled'    => __( 'Checkout was canceled. No membership access was activated.', 'membexa' ),
			'membership_active'    => __( 'Your membership is active.', 'membexa' ),
			'already_member'       => __( 'You already have access through this plan.', 'membexa' ),
			'already_logged_in'    => __( 'You are already signed in.', 'membexa' ),
			'invalid_registration' => __( 'Please enter a valid email, a password of at least 8 characters, and a valid plan.', 'membexa' ),
			'email_exists'         => __( 'An account already exists for this email. Please sign in instead.', 'membexa' ),
			'registration_failed'  => __( 'The account could not be created.', 'membexa' ),
			'invalid_plan'         => __( 'The selected membership plan is not available.', 'membexa' ),
			'checkout_error'       => __( 'Checkout could not be started. Please contact the site administrator.', 'membexa' ),
			'security'             => __( 'The request could not be verified. Please try again.', 'membexa' ),
			'invalid_subscription' => __( 'The selected subscription could not be found.', 'membexa' ),
			'cancel_scheduled'     => __( 'Your subscription will cancel at the end of the current billing period.', 'membexa' ),
			'cancelled'            => __( 'Your membership has been canceled.', 'membexa' ),
			'cancel_error'         => __( 'The cancellation request could not be completed.', 'membexa' ),
		);
		if ( $code && isset( $messages[ $code ] ) ) {
			echo '<div class="membexa-notice" role="status">' . esc_html( $messages[ $code ] ) . '</div>';
		}
	}
}
