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

/**
 * Registers public shortcodes and handles member form actions.
 */
final class Shortcodes {
	/** Register hooks. */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'template_redirect', array( $this, 'process_actions' ) );
	}

	/** Register plugin shortcodes. */
	public function register() {
		add_shortcode( 'membexa_pricing', array( $this, 'pricing' ) );
		add_shortcode( 'membexa_register', array( $this, 'register_form' ) );
		add_shortcode( 'membexa_login', array( $this, 'login_form' ) );
		add_shortcode( 'membexa_account', array( $this, 'account' ) );
	}

	/** Process Membexa front-end form submissions. */
	public function process_actions() {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== strtoupper( $request_method ) || empty( $_POST['membexa_action'] ) ) {
			return;
		}
		if ( ! isset( $_POST['membexa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['membexa_nonce'] ) ), 'membexa_frontend_action' ) ) {
			$this->redirect_notice( 'security' );
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

	/** Create a WordPress account and start the selected membership. */
	private function process_registration() {
		if ( is_user_logged_in() ) {
			$this->redirect_notice( 'already_logged_in' );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be altered before WordPress hashes them.
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$plan_id  = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$gateway  = isset( $_POST['payment_gateway'] ) ? sanitize_key( wp_unslash( $_POST['payment_gateway'] ) ) : '';
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
		$this->begin_plan( $user_id, $plan, $gateway );
	}

	/** Start checkout for an existing WordPress user. */
	private function process_checkout() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$plan    = Plan::get( isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0 );
		$gateway = isset( $_POST['payment_gateway'] ) ? sanitize_key( wp_unslash( $_POST['payment_gateway'] ) ) : '';
		if ( ! $plan ) {
			$this->redirect_notice( 'invalid_plan' );
		}
		$this->begin_plan( get_current_user_id(), $plan, $gateway );
	}

	/**
	 * Activate a free plan or redirect to a hosted payment gateway.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param array  $plan    Membership plan data.
	 * @param string $gateway Selected gateway.
	 * @return void
	 */
	private function begin_plan( $user_id, $plan, $gateway = '' ) {
		if ( Subscriptions::user_has_plan( $user_id, array( $plan['id'] ) ) ) {
			$this->redirect_notice( 'already_member' );
		}
		if ( 'free' === $plan['billing'] || 0.0 === (float) $plan['price'] ) {
			Subscriptions::create( $user_id, $plan['id'], 'active', 'free', '' );
			$this->redirect_account( 'membership_active' );
		}

		$available = Gateways::available_for_plan( $plan );
		if ( ! $gateway && ! empty( $available ) ) {
			$keys    = array_keys( $available );
			$gateway = reset( $keys );
		}
		if ( ! $gateway || ! isset( $available[ $gateway ] ) ) {
			$this->redirect_notice( 'gateway_unavailable' );
		}

		$url = Gateways::start_checkout( $gateway, $user_id, $plan['id'] );
		if ( is_wp_error( $url ) ) {
			$this->redirect_notice( 'checkout_error' );
		}
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Each gateway validates its external hosted checkout URL before returning it.
		exit;
	}

	/** Cancel a member-owned subscription. */
	private function process_cancel() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		$subscription = Subscriptions::get( isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0 );
		if ( ! $subscription || get_current_user_id() !== (int) $subscription->user_id ) {
			$this->redirect_account( 'invalid_subscription' );
		}

		$result = Gateways::cancel( $subscription );
		if ( is_wp_error( $result ) ) {
			$this->redirect_account( 'cancel_error' );
		}
		$this->redirect_account( 'scheduled' === $result ? 'cancel_scheduled' : 'cancelled' );
	}

	/**
	 * Render the pricing grid.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function pricing( $atts ) {
		$atts = shortcode_atts( array( 'register_url' => '' ), $atts, 'membexa_pricing' );
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
				<?php $gateways = Gateways::available_for_plan( $plan ); ?>
				<article class="membexa-plan-card">
					<h3><?php echo esc_html( $plan['name'] ); ?></h3>
					<div class="membexa-price"><?php echo esc_html( $this->format_price( $plan ) ); ?></div>
					<?php if ( $plan['description'] ) : ?>
						<div class="membexa-plan-description"><?php echo wp_kses_post( wpautop( $plan['description'] ) ); ?></div>
					<?php endif; ?>
					<?php if ( $plan['features'] ) : ?>
						<ul class="membexa-features">
							<?php foreach ( array_filter( array_map( 'trim', explode( "\n", $plan['features'] ) ) ) as $feature ) : ?>
								<li><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( is_user_logged_in() ) : ?>
						<?php $this->pricing_checkout_form( $plan, $gateways ); ?>
					<?php else : ?>
						<a class="membexa-button" href="<?php echo esc_url( add_query_arg( 'membexa_plan', $plan['id'], $register_url ) ); ?>"><?php esc_html_e( 'Join now', 'membexa' ); ?></a>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the logged-in checkout form for one pricing card.
	 *
	 * @param array $plan     Plan data.
	 * @param array $gateways Compatible gateways.
	 * @return void
	 */
	private function pricing_checkout_form( $plan, $gateways ) {
		$is_paid = 'free' !== $plan['billing'] && 0.0 !== (float) $plan['price'];
		if ( $is_paid && empty( $gateways ) ) {
			?>
			<p class="membexa-notice"><?php esc_html_e( 'No payment method is currently configured for this plan.', 'membexa' ); ?></p>
			<?php
			return;
		}
		?>
		<form method="post" class="membexa-checkout-form">
			<?php wp_nonce_field( 'membexa_frontend_action', 'membexa_nonce' ); ?>
			<input type="hidden" name="membexa_action" value="checkout">
			<input type="hidden" name="plan_id" value="<?php echo esc_attr( $plan['id'] ); ?>">
			<?php if ( ! empty( $gateways ) ) : ?>
				<label for="membexa-gateway-<?php echo esc_attr( $plan['id'] ); ?>"><?php esc_html_e( 'Pay with', 'membexa' ); ?></label>
				<select id="membexa-gateway-<?php echo esc_attr( $plan['id'] ); ?>" name="payment_gateway">
					<?php foreach ( $gateways as $gateway_key => $gateway_label ) : ?>
						<option value="<?php echo esc_attr( $gateway_key ); ?>"><?php echo esc_html( $gateway_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<button class="membexa-button" type="submit"><?php esc_html_e( 'Choose plan', 'membexa' ); ?></button>
		</form>
		<?php
	}

	/** Render the registration form. */
	public function register_form() {
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already signed in.', 'membexa' ) . '</p>';
		}
		$plans = Plan::all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only plan preselection from a public pricing link.
		$selected_id = isset( $_GET['membexa_plan'] ) ? absint( $_GET['membexa_plan'] ) : 0;
		$gateways    = Gateways::enabled();
		ob_start();
		$this->render_notice();
		?>
		<form class="membexa-form" method="post">
			<?php wp_nonce_field( 'membexa_frontend_action', 'membexa_nonce' ); ?>
			<input type="hidden" name="membexa_action" value="register">
			<p>
				<label for="membexa-email"><?php esc_html_e( 'Email address', 'membexa' ); ?></label>
				<input id="membexa-email" name="email" type="email" required autocomplete="email">
			</p>
			<p>
				<label for="membexa-password"><?php esc_html_e( 'Password', 'membexa' ); ?></label>
				<input id="membexa-password" name="password" type="password" minlength="8" required autocomplete="new-password">
				<small><?php esc_html_e( 'Use at least 8 characters.', 'membexa' ); ?></small>
			</p>
			<p>
				<label for="membexa-plan"><?php esc_html_e( 'Membership plan', 'membexa' ); ?></label>
				<select id="membexa-plan" name="plan_id" required>
					<option value=""><?php esc_html_e( 'Select a plan', 'membexa' ); ?></option>
					<?php foreach ( $plans as $plan ) : ?>
						<option value="<?php echo esc_attr( $plan['id'] ); ?>" <?php selected( $selected_id, $plan['id'] ); ?>><?php echo esc_html( $plan['name'] . ' — ' . $this->format_price( $plan ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php if ( ! empty( $gateways ) ) : ?>
				<p>
					<label for="membexa-payment-gateway"><?php esc_html_e( 'Payment method', 'membexa' ); ?></label>
					<select id="membexa-payment-gateway" name="payment_gateway">
						<?php foreach ( $gateways as $gateway_key => $gateway_label ) : ?>
							<option value="<?php echo esc_attr( $gateway_key ); ?>"><?php echo esc_html( $gateway_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<small><?php esc_html_e( 'Only payment methods compatible with the selected plan are accepted. bKash requires BDT one-time or lifetime billing.', 'membexa' ); ?></small>
				</p>
			<?php endif; ?>
			<p><button class="membexa-button" type="submit"><?php esc_html_e( 'Create account', 'membexa' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	/** Render the WordPress login form. */
	public function login_form() {
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already signed in.', 'membexa' ) . '</p>';
		}
		ob_start();
		wp_login_form( array( 'echo' => true ) );
		return ob_get_clean();
	}

	/** Render the current member account view. */
	public function account() {
		if ( ! is_user_logged_in() ) {
			/* translators: %s: WordPress login URL. */
			$message = sprintf( __( 'Please <a href="%s">sign in</a> to view your membership account.', 'membexa' ), esc_url( wp_login_url( get_permalink() ) ) );
			return '<p>' . wp_kses_post( $message ) . '</p>';
		}

		$user          = wp_get_current_user();
		$subscriptions = Subscriptions::for_user( $user->ID );
		ob_start();
		$this->render_notice();
		?>
		<div class="membexa-account">
			<?php /* translators: %s: member display name. */ ?>
			<h3><?php echo esc_html( sprintf( __( 'Welcome, %s', 'membexa' ), $user->display_name ) ); ?></h3>
			<?php if ( empty( $subscriptions ) ) : ?>
				<p><?php esc_html_e( 'You do not have a membership yet.', 'membexa' ); ?></p>
			<?php else : ?>
				<table class="membexa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Plan', 'membexa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'membexa' ); ?></th>
							<th><?php esc_html_e( 'Renewal / End', 'membexa' ); ?></th>
							<th><?php esc_html_e( 'Action', 'membexa' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $subscriptions as $subscription ) : ?>
							<?php $plan = Plan::get( $subscription->plan_id ); ?>
							<tr>
								<td><?php echo esc_html( $plan ? $plan['name'] : __( 'Deleted plan', 'membexa' ) ); ?></td>
								<td>
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $subscription->status ) ) ); ?>
									<?php if ( $subscription->cancel_at_period_end ) : ?>
										<small><?php esc_html_e( '(cancels at period end)', 'membexa' ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo $subscription->ends_at ? esc_html( get_date_from_gmt( $subscription->ends_at, get_option( 'date_format' ) ) ) : '—'; ?></td>
								<td>
									<?php if ( in_array( $subscription->status, array( 'active', 'trialing' ), true ) && ! $subscription->cancel_at_period_end ) : ?>
										<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Cancel this membership?', 'membexa' ) ); ?>');">
											<?php wp_nonce_field( 'membexa_frontend_action', 'membexa_nonce' ); ?>
											<input type="hidden" name="membexa_action" value="cancel">
											<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $subscription->id ); ?>">
											<button type="submit" class="membexa-link-button"><?php esc_html_e( 'Cancel', 'membexa' ); ?></button>
										</form>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Format a membership plan price for display. */
	private function format_price( $plan ) {
		if ( 'free' === $plan['billing'] || 0.0 === (float) $plan['price'] ) {
			return __( 'Free', 'membexa' );
		}
		$suffix = array(
			'monthly'  => __( '/ month', 'membexa' ),
			'yearly'   => __( '/ year', 'membexa' ),
			'lifetime' => __( ' lifetime', 'membexa' ),
			'one_time' => __( ' one-time', 'membexa' ),
		);
		return $plan['currency'] . ' ' . number_format_i18n( $plan['price'], 2 ) . ( isset( $suffix[ $plan['billing'] ] ) ? $suffix[ $plan['billing'] ] : '' );
	}

	/** Redirect to the referring page with a notice code. */
	private function redirect_notice( $code ) {
		$url = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'membexa_notice', sanitize_key( $code ), $url ) );
		exit;
	}

	/** Redirect to the configured account page with a notice code. */
	private function redirect_account( $code ) {
		$general = Settings::general();
		$url     = $general['account_page_id'] ? get_permalink( $general['account_page_id'] ) : ( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
		wp_safe_redirect( add_query_arg( 'membexa_notice', sanitize_key( $code ), $url ) );
		exit;
	}

	/** Render a front-end status notice from an allow-listed code. */
	private function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only display notice selected from a fixed allow-list.
		$code     = isset( $_GET['membexa_notice'] ) ? sanitize_key( wp_unslash( $_GET['membexa_notice'] ) ) : '';
		$messages = array(
			'payment_success'      => __( 'Payment confirmed. Your membership access is being updated.', 'membexa' ),
			'payment_pending'      => __( 'Your payment approval was received. Membership access will activate after the payment provider confirms the subscription.', 'membexa' ),
			'payment_failed'       => __( 'The payment could not be confirmed. No membership access was activated.', 'membexa' ),
			'payment_cancelled'    => __( 'Checkout was canceled. No membership access was activated.', 'membexa' ),
			'membership_active'    => __( 'Your membership is active.', 'membexa' ),
			'already_member'       => __( 'You already have access through this plan.', 'membexa' ),
			'already_logged_in'    => __( 'You are already signed in.', 'membexa' ),
			'invalid_registration' => __( 'Please enter a valid email, a password of at least 8 characters, and a valid plan.', 'membexa' ),
			'email_exists'         => __( 'An account already exists for this email. Please sign in instead.', 'membexa' ),
			'registration_failed'  => __( 'The account could not be created.', 'membexa' ),
			'invalid_plan'         => __( 'The selected membership plan is not available.', 'membexa' ),
			'gateway_unavailable'  => __( 'The selected payment method is not available for this plan. Please choose another payment method.', 'membexa' ),
			'checkout_error'       => __( 'Checkout could not be started. Please verify the payment settings or choose another payment method.', 'membexa' ),
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
