<?php
/**
 * Bilingual in-plugin help and setup guide.
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Membexa Help & Setup Center.
 */
final class Help {
	/** Register Help Center hooks. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 20 );
	}

	/** Register the Help & Setup submenu. */
	public function menu() {
		add_submenu_page(
			'membexa',
			__( 'Membexa Help & Setup', 'membexa' ),
			__( 'Help & Setup', 'membexa' ),
			'manage_options',
			'membexa-help',
			array( $this, 'page' )
		);
	}

	/**
	 * Load Help Center styles only on the Help page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( $hook ) {
		if ( 'membexa_page_membexa-help' !== (string) $hook ) {
			return;
		}

		wp_enqueue_style( 'membexa-help', MEMBEXA_URL . 'assets/css/help.css', array( 'membexa-admin' ), MEMBEXA_VERSION );
	}

	/** Render the Help Center page. */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view Membexa help.', 'membexa' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only language switcher.
		$lang = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : 'en';
		$lang = 'bn' === $lang ? 'bn' : 'en';
		?>
		<div class="wrap membexa-admin membexa-help" lang="<?php echo esc_attr( $lang ); ?>">
			<div class="membexa-help-hero">
				<h1><?php echo 'bn' === $lang ? esc_html__( 'Membexa হেল্প ও সেটআপ গাইড', 'membexa' ) : esc_html__( 'Membexa Help & Setup Guide', 'membexa' ); ?></h1>
				<p><?php echo 'bn' === $lang ? esc_html__( 'শুরু থেকে লাইভ সাইট পর্যন্ত Membexa সেটআপ করার ধাপে ধাপে নির্দেশনা। পেজ, প্ল্যান, Stripe, PayPal, bKash, কনটেন্ট রেস্ট্রিকশন, টেস্টিং এবং ট্রাবলশুটিং—সব এক জায়গায়।', 'membexa' ) : esc_html__( 'A complete step-by-step guide for configuring Membexa from first activation to a production-ready membership site, including pages, plans, Stripe, PayPal, bKash, content restriction, testing, and troubleshooting.', 'membexa' ); ?></p>
				<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Help language', 'membexa' ); ?>">
					<a class="nav-tab <?php echo 'en' === $lang ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-help&lang=en' ) ); ?>">English</a>
					<a class="nav-tab <?php echo 'bn' === $lang ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-help&lang=bn' ) ); ?>">বাংলা</a>
				</nav>
			</div>

			<div class="membexa-help-layout">
				<main class="membexa-help-main">
					<?php
					if ( 'bn' === $lang ) {
						$this->bangla();
					} else {
						$this->english();
					}
					?>
				</main>
				<?php $this->side_nav( $lang ); ?>
			</div>
		</div>
		<?php
	}

	/** Render section navigation. */
	private function side_nav( $lang ) {
		$items = 'bn' === $lang
			? array(
				'overview'        => 'Membexa কীভাবে কাজ করে',
				'quickstart'      => 'দ্রুত সেটআপ',
				'pages'           => 'পেজ ও শর্টকোড',
				'plans'           => 'মেম্বারশিপ প্ল্যান',
				'payments'        => 'পেমেন্ট সেটআপ',
				'content'         => 'কনটেন্ট রেস্ট্রিকশন',
				'testing'         => 'টেস্টিং',
				'manage'          => 'মেম্বার ম্যানেজমেন্ট',
				'troubleshooting' => 'সমস্যা সমাধান',
				'launch'          => 'লাইভ চেকলিস্ট',
			)
			: array(
				'overview'        => 'How Membexa works',
				'quickstart'      => 'Quick start',
				'pages'           => 'Pages & shortcodes',
				'plans'           => 'Membership plans',
				'payments'        => 'Payment setup',
				'content'         => 'Content restriction',
				'testing'         => 'Testing',
				'manage'          => 'Member management',
				'troubleshooting' => 'Troubleshooting',
				'launch'          => 'Go-live checklist',
			);
		?>
		<aside class="membexa-help-nav" aria-label="<?php esc_attr_e( 'Help sections', 'membexa' ); ?>">
			<strong><?php echo 'bn' === $lang ? esc_html__( 'এই গাইডে', 'membexa' ) : esc_html__( 'In this guide', 'membexa' ); ?></strong>
			<?php foreach ( $items as $id => $label ) : ?>
				<a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</aside>
		<?php
	}

	/** Render the English guide. */
	private function english() {
		?>
		<section id="overview" class="membexa-help-section">
			<h2><?php esc_html_e( '1. How Membexa works', 'membexa' ); ?></h2>
			<p><?php esc_html_e( 'Membexa connects a WordPress user to one or more membership subscriptions. A published membership plan defines the price, currency, billing type, and optional payment-provider IDs. After a free plan is selected or a paid checkout is confirmed by the provider, Membexa creates or activates the local subscription. Protected posts and pages check that subscription before showing restricted content.', 'membexa' ); ?></p>
			<div class="membexa-help-callout"><strong><?php esc_html_e( 'Simple flow:', 'membexa' ); ?></strong> <?php esc_html_e( 'Visitor → Register → Choose plan → Pay if required → Membership activates → Eligible protected content becomes available.', 'membexa' ); ?></div>
		</section>

		<section id="quickstart" class="membexa-help-section">
			<h2><?php esc_html_e( '2. Quick start', 'membexa' ); ?></h2>
			<?php $this->figure( '01-admin-map.svg', __( 'Annotated Membexa admin setup order', 'membexa' ), __( 'Follow the numbered order for the cleanest first-time setup.', 'membexa' ) ); ?>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'Create your Pricing, Join/Register, and Account pages.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Create and publish at least one membership plan.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Configure one or more payment gateways if you sell paid plans.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Restrict posts, pages, or other public content to eligible plans.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Complete a full sandbox/test checkout before using live payment credentials.', 'membexa' ); ?></li>
			</ol>
			<div class="membexa-help-actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Create a page', 'membexa' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Plan::POST_TYPE ) ); ?>"><?php esc_html_e( 'Create a plan', 'membexa' ); ?></a>
			</div>
		</section>

		<section id="pages" class="membexa-help-section">
			<h2><?php esc_html_e( '3. Pages and shortcodes', 'membexa' ); ?></h2>
			<?php $this->figure( '02-pages-shortcodes.svg', __( 'Annotated Membexa pages and shortcodes', 'membexa' ), __( 'Create these pages, then assign the Pricing and Account pages under General Settings.', 'membexa' ) ); ?>
			<table class="membexa-help-table">
				<thead><tr><th><?php esc_html_e( 'Page', 'membexa' ); ?></th><th><?php esc_html_e( 'Shortcode', 'membexa' ); ?></th><th><?php esc_html_e( 'Purpose', 'membexa' ); ?></th></tr></thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Pricing', 'membexa' ); ?></td><td><code>[membexa_pricing register_url="/join/"]</code></td><td><?php esc_html_e( 'Displays published plans and compatible enabled payment methods. Change /join/ to your registration page URL.', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Join / Register', 'membexa' ); ?></td><td><code>[membexa_register]</code></td><td><?php esc_html_e( 'Creates the WordPress user account and lets the visitor select a plan and payment method.', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Account', 'membexa' ); ?></td><td><code>[membexa_account]</code></td><td><?php esc_html_e( 'Shows the signed-in member’s memberships, status, renewal/end date, and cancellation controls.', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Login (optional)', 'membexa' ); ?></td><td><code>[membexa_login]</code></td><td><?php esc_html_e( 'Displays the standard WordPress login form.', 'membexa' ); ?></td></tr>
				</tbody>
			</table>
			<h3><?php esc_html_e( 'General Settings', 'membexa' ); ?></h3>
			<p><?php esc_html_e( 'Go to Membexa → Settings → General. Choose the default currency, select your Pricing page and Account page, and customize the Restricted Content Message. Save Changes.', 'membexa' ); ?></p>
			<div class="membexa-help-actions"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-settings&tab=general' ) ); ?>"><?php esc_html_e( 'Open General Settings', 'membexa' ); ?></a></div>
		</section>

		<section id="plans" class="membexa-help-section">
			<h2><?php esc_html_e( '4. Create membership plans', 'membexa' ); ?></h2>
			<?php $this->figure( '03-plan-settings.svg', __( 'Annotated Membexa plan fields', 'membexa' ), __( 'Gateway-specific IDs are only required when that gateway and billing model needs them.', 'membexa' ) ); ?>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'Go to Membexa → Plans → Add New Plan. Enter the plan name and description.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Set Price and a three-letter ISO Currency code such as USD, EUR, GBP, SAR, AED, or BDT.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Choose Billing: Free, One-time payment, Monthly recurring, Yearly recurring, or Lifetime.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'For Stripe, add the matching Stripe Price ID (price_...) if the plan should be sold through Stripe.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'For monthly/yearly PayPal, add the PayPal Plan ID (P-...). One-time and lifetime PayPal payments do not need a PayPal Plan ID.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'For bKash, use BDT currency and One-time or Lifetime billing. Membexa will offer bKash automatically when the gateway is enabled and configured.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Enter one pricing feature per line, then Publish the plan.', 'membexa' ); ?></li>
			</ol>
			<table class="membexa-help-table">
				<thead><tr><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'One-time / Lifetime', 'membexa' ); ?></th><th><?php esc_html_e( 'Monthly / Yearly', 'membexa' ); ?></th></tr></thead>
				<tbody>
					<tr><td>Stripe</td><td><?php esc_html_e( 'Supported with Stripe Price ID', 'membexa' ); ?></td><td><?php esc_html_e( 'Supported with recurring Stripe Price ID', 'membexa' ); ?></td></tr>
					<tr><td>PayPal</td><td><?php esc_html_e( 'Supported; no PayPal Plan ID required', 'membexa' ); ?></td><td><?php esc_html_e( 'Supported; PayPal Plan ID required', 'membexa' ); ?></td></tr>
					<tr><td>bKash</td><td><?php esc_html_e( 'Supported only when currency is BDT', 'membexa' ); ?></td><td><?php esc_html_e( 'Not offered in this release', 'membexa' ); ?></td></tr>
			</tbody>
			</table>
		</section>

		<section id="payments" class="membexa-help-section">
			<h2><?php esc_html_e( '5. Configure payments', 'membexa' ); ?></h2>
			<?php $this->figure( '04-payments.svg', __( 'Annotated Membexa payment gateway setup', 'membexa' ), __( 'Enable only the gateways you intend to offer and test them before going live.', 'membexa' ) ); ?>
			<div class="membexa-help-warning"><strong><?php esc_html_e( 'Important:', 'membexa' ); ?></strong> <?php esc_html_e( 'A payment gateway is shown at checkout only when it is enabled, fully configured, and compatible with the selected plan.', 'membexa' ); ?></div>

			<h3>Stripe</h3>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'Enable Stripe Checkout under Membexa → Settings → Payments.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Enter your Stripe Secret Key and Webhook Signing Secret.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Create the matching price in Stripe and paste its price_... ID into the Membexa plan.', 'membexa' ); ?></li>
				<li><?php /* translators: %s: Stripe webhook URL. */ echo esc_html( sprintf( __( 'Configure this webhook endpoint in Stripe: %s', 'membexa' ), rest_url( 'membexa/v1/stripe/webhook' ) ) ); ?></li>
			</ol>

			<h3>PayPal</h3>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'Create a PayPal REST application and copy its Client ID and Client Secret.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Enable PayPal and keep Sandbox / test mode enabled while testing.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'For monthly/yearly plans, create the PayPal recurring product/plan and add its P-... Plan ID to the Membexa plan.', 'membexa' ); ?></li>
				<li><?php /* translators: %s: PayPal webhook URL. */ echo esc_html( sprintf( __( 'Create a PayPal webhook for: %s', 'membexa' ), rest_url( 'membexa/v1/paypal/webhook' ) ) ); ?></li>
				<li><?php esc_html_e( 'Copy the PayPal Webhook ID back into Membexa so recurring events can be verified.', 'membexa' ); ?></li>
			</ol>

			<h3>bKash (Bangladesh)</h3>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'Complete bKash Payment Gateway merchant onboarding. A personal bKash account/number is not a merchant API credential set.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Obtain the merchant API Username, Password, App Key, and App Secret.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Enable bKash and keep Sandbox enabled while using sandbox credentials.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Use a BDT plan with One-time or Lifetime billing.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'After a successful sandbox checkout, switch to live merchant credentials and disable Sandbox.', 'membexa' ); ?></li>
			</ol>
			<div class="membexa-help-callout"><strong><?php esc_html_e( 'Production secret storage:', 'membexa' ); ?></strong> <?php esc_html_e( 'For advanced setups, payment secrets can be defined in wp-config.php using the MEMBEXA_STRIPE_*, MEMBEXA_PAYPAL_*, and MEMBEXA_BKASH_* constants documented in the plugin readme.', 'membexa' ); ?></div>
			<div class="membexa-help-actions"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-settings&tab=payments' ) ); ?>"><?php esc_html_e( 'Open Payment Settings', 'membexa' ); ?></a></div>
		</section>

		<section id="content" class="membexa-help-section">
			<h2><?php esc_html_e( '6. Protect member-only content', 'membexa' ); ?></h2>
			<?php $this->figure( '05-content-access.svg', __( 'Annotated Membexa Access panel', 'membexa' ), __( 'The Membexa Access panel is available on public content types. Select at least one allowed plan for member access.', 'membexa' ) ); ?>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'Edit the post, page, or supported public content item you want to protect.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'In the Membexa Access panel, enable “Restrict this content to members”.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Select one or more Allowed Plans.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Update or Publish the content.', 'membexa' ); ?></li>
			</ol>
			<div class="membexa-help-danger"><strong><?php esc_html_e( 'Do not leave Allowed Plans empty by mistake.', 'membexa' ); ?></strong> <?php esc_html_e( 'When content is restricted and no plan is selected, visitors without edit permission cannot view it.', 'membexa' ); ?></div>
		</section>

		<section id="testing" class="membexa-help-section">
			<h2><?php esc_html_e( '7. Test the full customer journey', 'membexa' ); ?></h2>
			<ul class="membexa-help-checklist">
				<li><?php esc_html_e( 'Open the Pricing page in a private/incognito browser.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Choose a plan and register a new test member.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Confirm only compatible enabled gateways are offered.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Complete the provider sandbox/test checkout.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Return to the Account page and confirm membership is active.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Open a protected page and confirm the member can view it.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Log out and confirm protected content is hidden.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'For recurring Stripe/PayPal, confirm webhook events reach your site.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Test member cancellation from the Account page when applicable.', 'membexa' ); ?></li>
			</ul>
		</section>

		<section id="manage" class="membexa-help-section">
			<h2><?php esc_html_e( '8. Manage members and subscriptions', 'membexa' ); ?></h2>
			<p><?php esc_html_e( 'Use Membexa → Subscriptions to review membership status, gateway, start date, and renewal/end date. Use Membexa → Members for a member-level summary. The member-facing Account page shows each signed-in user their own membership status and cancellation controls.', 'membexa' ); ?></p>
			<div class="membexa-help-actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-subscriptions' ) ); ?>"><?php esc_html_e( 'View Subscriptions', 'membexa' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-members' ) ); ?>"><?php esc_html_e( 'View Members', 'membexa' ); ?></a>
			</div>
		</section>

		<section id="troubleshooting" class="membexa-help-section">
			<h2><?php esc_html_e( '9. Troubleshooting', 'membexa' ); ?></h2>
			<table class="membexa-help-table">
				<thead><tr><th><?php esc_html_e( 'Problem', 'membexa' ); ?></th><th><?php esc_html_e( 'What to check', 'membexa' ); ?></th></tr></thead>
				<tbody>
					<tr><td><?php esc_html_e( 'No paid gateway appears', 'membexa' ); ?></td><td><?php esc_html_e( 'Confirm the gateway is enabled, all required credentials are saved, and the selected plan is compatible.', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Stripe checkout does not start', 'membexa' ); ?></td><td><?php esc_html_e( 'Check the Secret Key, plan Stripe Price ID, and whether the Stripe price matches the intended billing model.', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'PayPal recurring does not appear', 'membexa' ); ?></td><td><?php esc_html_e( 'Monthly/yearly PayPal requires a valid P-... PayPal Plan ID on that Membexa plan.', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'PayPal renewals/status do not update', 'membexa' ); ?></td><td><?php esc_html_e( 'Verify the PayPal webhook endpoint, event subscriptions, and saved Webhook ID.', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'bKash does not appear', 'membexa' ); ?></td><td><?php esc_html_e( 'Confirm merchant credentials are complete and the plan uses BDT with One-time or Lifetime billing.', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Protected content is still hidden', 'membexa' ); ?></td><td><?php esc_html_e( 'Confirm the member subscription is active/trialing and the protected content has that exact plan selected under Allowed Plans.', 'membexa' ); ?></td></tr>
				</tbody>
			</table>
		</section>

		<section id="launch" class="membexa-help-section">
			<h2><?php esc_html_e( '10. Go-live checklist', 'membexa' ); ?></h2>
			<ul class="membexa-help-checklist">
				<li><?php esc_html_e( 'Pricing, Join, Account, and optional Login pages are published.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Pricing and Account pages are assigned in General Settings.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Every paid plan has the correct currency, billing type, and required gateway IDs.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'One complete sandbox/test purchase has succeeded per enabled gateway.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Stripe and PayPal webhooks are configured and verified.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Live credentials are saved securely; test credentials are removed from production.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Protected content was tested as both a logged-out visitor and an active member.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Your site privacy policy and terms disclose the payment providers you use.', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'A backup is available before major production changes.', 'membexa' ); ?></li>
			</ul>
			<div class="membexa-help-callout"><strong><?php esc_html_e( 'Ready:', 'membexa' ); ?></strong> <?php esc_html_e( 'When every item above is complete, run one small real transaction and verify the member receives access before promoting the membership site publicly.', 'membexa' ); ?></div>
		</section>
		<?php
	}

	/** Render the Bangla guide. */
	private function bangla() {
		?>
		<section id="overview" class="membexa-help-section">
			<h2><?php esc_html_e( '১. Membexa কীভাবে কাজ করে', 'membexa' ); ?></h2>
			<p><?php esc_html_e( 'Membexa একটি WordPress user-কে তার membership subscription-এর সাথে যুক্ত করে। Published Plan-এর মধ্যে মূল্য, currency, billing type এবং প্রয়োজন হলে payment provider ID থাকে। Free plan নিলে অথবা paid checkout payment provider থেকে সফলভাবে confirm হলে Membexa local subscription active করে। এরপর যেসব post/page Membexa দিয়ে restricted করা আছে, সেগুলো দেখানোর আগে active membership যাচাই করা হয়।', 'membexa' ); ?></p>
			<div class="membexa-help-callout"><strong><?php esc_html_e( 'সহজ flow:', 'membexa' ); ?></strong> <?php esc_html_e( 'Visitor → Register → Plan নির্বাচন → প্রয়োজন হলে Payment → Membership Active → অনুমোদিত protected content দেখা যাবে।', 'membexa' ); ?></div>
		</section>

		<section id="quickstart" class="membexa-help-section">
			<h2><?php esc_html_e( '২. দ্রুত সেটআপ', 'membexa' ); ?></h2>
			<?php $this->figure( '01-admin-map.svg', __( 'Membexa admin setup order', 'membexa' ), __( 'সবচেয়ে সহজ ও নিরাপদ সেটআপের জন্য ১ থেকে ৫ নম্বর ক্রম অনুসরণ করুন।', 'membexa' ) ); ?>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'Pricing, Join/Register এবং Account page তৈরি করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'কমপক্ষে একটি membership plan তৈরি করে Publish করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Paid plan থাকলে Stripe, PayPal এবং/অথবা bKash সেটআপ করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'যে post/page শুধু member-দের জন্য, সেখানে Membexa Access দিয়ে plan নির্বাচন করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Live payment চালু করার আগে sandbox/test checkout সম্পূর্ণ করুন।', 'membexa' ); ?></li>
			</ol>
			<div class="membexa-help-actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( 'নতুন Page তৈরি', 'membexa' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Plan::POST_TYPE ) ); ?>"><?php esc_html_e( 'নতুন Plan তৈরি', 'membexa' ); ?></a>
			</div>
		</section>

		<section id="pages" class="membexa-help-section">
			<h2><?php esc_html_e( '৩. পেজ ও শর্টকোড সেটআপ', 'membexa' ); ?></h2>
			<?php $this->figure( '02-pages-shortcodes.svg', __( 'Membexa pages and shortcodes', 'membexa' ), __( 'এই page-গুলো তৈরি করে General Settings-এ Pricing এবং Account page assign করুন।', 'membexa' ) ); ?>
			<table class="membexa-help-table">
				<thead><tr><th><?php esc_html_e( 'Page', 'membexa' ); ?></th><th><?php esc_html_e( 'Shortcode', 'membexa' ); ?></th><th><?php esc_html_e( 'কাজ', 'membexa' ); ?></th></tr></thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Pricing', 'membexa' ); ?></td><td><code>[membexa_pricing register_url="/join/"]</code></td><td><?php esc_html_e( 'Published plan এবং compatible payment method দেখাবে। /join/ এর জায়গায় আপনার Join page URL দিন।', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Join / Register', 'membexa' ); ?></td><td><code>[membexa_register]</code></td><td><?php esc_html_e( 'নতুন WordPress account, plan এবং payment method নির্বাচন করার form দেখাবে।', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Account', 'membexa' ); ?></td><td><code>[membexa_account]</code></td><td><?php esc_html_e( 'Member-এর membership status, renewal/end date এবং cancellation control দেখাবে।', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Login (ঐচ্ছিক)', 'membexa' ); ?></td><td><code>[membexa_login]</code></td><td><?php esc_html_e( 'WordPress login form দেখাবে।', 'membexa' ); ?></td></tr>
				</tbody>
			</table>
			<h3><?php esc_html_e( 'General Settings', 'membexa' ); ?></h3>
			<p><?php esc_html_e( 'Membexa → Settings → General এ যান। Default currency সেট করুন, Pricing page ও Account page নির্বাচন করুন এবং Restricted Content Message লিখুন। এরপর Save Changes চাপুন।', 'membexa' ); ?></p>
			<div class="membexa-help-actions"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-settings&tab=general' ) ); ?>"><?php esc_html_e( 'General Settings খুলুন', 'membexa' ); ?></a></div>
		</section>

		<section id="plans" class="membexa-help-section">
			<h2><?php esc_html_e( '৪. Membership Plan তৈরি', 'membexa' ); ?></h2>
			<?php $this->figure( '03-plan-settings.svg', __( 'Membexa plan fields', 'membexa' ), __( 'যে gateway ও billing model-এর জন্য ID দরকার শুধু সেই ক্ষেত্রেই gateway-specific ID দিন।', 'membexa' ) ); ?>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'Membexa → Plans → Add New Plan এ গিয়ে Plan name ও description লিখুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Price দিন এবং তিন অক্ষরের ISO Currency code ব্যবহার করুন: USD, EUR, GBP, SAR, AED, BDT ইত্যাদি।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Billing নির্বাচন করুন: Free, One-time payment, Monthly recurring, Yearly recurring অথবা Lifetime।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Stripe দিয়ে বিক্রি করলে matching Stripe Price ID (price_...) দিন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'PayPal Monthly/Yearly plan হলে PayPal Plan ID (P-...) দিন। PayPal One-time/Lifetime-এর জন্য Plan ID দরকার নেই।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'bKash-এর জন্য Currency BDT এবং Billing One-time অথবা Lifetime রাখুন। Gateway configured থাকলে bKash নিজে থেকেই checkout-এ available হবে।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Features box-এ প্রতি লাইনে একটি feature লিখে Plan Publish করুন।', 'membexa' ); ?></li>
			</ol>
			<table class="membexa-help-table">
				<thead><tr><th><?php esc_html_e( 'Gateway', 'membexa' ); ?></th><th><?php esc_html_e( 'One-time / Lifetime', 'membexa' ); ?></th><th><?php esc_html_e( 'Monthly / Yearly', 'membexa' ); ?></th></tr></thead>
				<tbody>
					<tr><td>Stripe</td><td><?php esc_html_e( 'Stripe Price ID দিয়ে supported', 'membexa' ); ?></td><td><?php esc_html_e( 'Recurring Stripe Price ID দিয়ে supported', 'membexa' ); ?></td></tr>
					<tr><td>PayPal</td><td><?php esc_html_e( 'Supported; PayPal Plan ID লাগে না', 'membexa' ); ?></td><td><?php esc_html_e( 'Supported; PayPal Plan ID লাগবে', 'membexa' ); ?></td></tr>
					<tr><td>bKash</td><td><?php esc_html_e( 'শুধু BDT হলে supported', 'membexa' ); ?></td><td><?php esc_html_e( 'এই version-এ নেই', 'membexa' ); ?></td></tr>
				</tbody>
			</table>
		</section>

		<section id="payments" class="membexa-help-section">
			<h2><?php esc_html_e( '৫. পেমেন্ট সেটআপ', 'membexa' ); ?></h2>
			<?php $this->figure( '04-payments.svg', __( 'Membexa payment gateway setup', 'membexa' ), __( 'শুধু প্রয়োজনীয় gateway enable করুন এবং live করার আগে test করুন।', 'membexa' ) ); ?>
			<div class="membexa-help-warning"><strong><?php esc_html_e( 'গুরুত্বপূর্ণ:', 'membexa' ); ?></strong> <?php esc_html_e( 'কোনো gateway checkout-এ তখনই দেখাবে যখন সেটি enabled, সম্পূর্ণ configured এবং নির্বাচিত plan-এর সাথে compatible।', 'membexa' ); ?></div>

			<h3>Stripe</h3>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'Membexa → Settings → Payments থেকে Stripe Checkout enable করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Stripe Secret Key এবং Webhook Signing Secret দিন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Stripe-এ price তৈরি করে তার price_... ID সংশ্লিষ্ট Membexa plan-এ দিন।', 'membexa' ); ?></li>
				<li><?php /* translators: %s: Stripe webhook URL. */ echo esc_html( sprintf( __( 'Stripe-এ এই webhook endpoint দিন: %s', 'membexa' ), rest_url( 'membexa/v1/stripe/webhook' ) ) ); ?></li>
			</ol>

			<h3>PayPal</h3>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'PayPal REST application তৈরি করে Client ID এবং Client Secret নিন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'PayPal enable করুন এবং testing-এর সময় Sandbox / test mode চালু রাখুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Monthly/Yearly হলে PayPal recurring product/plan তৈরি করে P-... Plan ID Membexa plan-এ দিন।', 'membexa' ); ?></li>
				<li><?php /* translators: %s: PayPal webhook URL. */ echo esc_html( sprintf( __( 'PayPal webhook এই URL-এর জন্য তৈরি করুন: %s', 'membexa' ), rest_url( 'membexa/v1/paypal/webhook' ) ) ); ?></li>
				<li><?php esc_html_e( 'PayPal-এর Webhook ID কপি করে Membexa Payment Settings-এ দিন।', 'membexa' ); ?></li>
			</ol>

			<h3>bKash (Bangladesh)</h3>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'bKash Payment Gateway merchant onboarding সম্পন্ন করুন। ব্যক্তিগত bKash নম্বর merchant API credential নয়।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Merchant API Username, Password, App Key এবং App Secret সংগ্রহ করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'bKash enable করুন এবং sandbox credential ব্যবহার করলে Sandbox চালু রাখুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Plan-এর Currency BDT এবং Billing One-time অথবা Lifetime দিন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Sandbox payment সফল হলে live merchant credential দিয়ে Sandbox বন্ধ করুন।', 'membexa' ); ?></li>
			</ol>
			<div class="membexa-help-callout"><strong><?php esc_html_e( 'Production secret storage:', 'membexa' ); ?></strong> <?php esc_html_e( 'Advanced site-এ wp-config.php ব্যবহার করে MEMBEXA_STRIPE_*, MEMBEXA_PAYPAL_* এবং MEMBEXA_BKASH_* constants-এর মাধ্যমে secret রাখা যায়। বিস্তারিত plugin readme-এ আছে।', 'membexa' ); ?></div>
			<div class="membexa-help-actions"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-settings&tab=payments' ) ); ?>"><?php esc_html_e( 'Payment Settings খুলুন', 'membexa' ); ?></a></div>
		</section>

		<section id="content" class="membexa-help-section">
			<h2><?php esc_html_e( '৬. Member-only কনটেন্ট লক করা', 'membexa' ); ?></h2>
			<?php $this->figure( '05-content-access.svg', __( 'Membexa Access panel', 'membexa' ), __( 'Public content type-এর editor-এ Membexa Access panel থেকে Allowed Plans নির্বাচন করুন।', 'membexa' ) ); ?>
			<ol class="membexa-help-steps">
				<li><?php esc_html_e( 'যে Post/Page বা supported public content protect করতে চান সেটি Edit করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Membexa Access panel থেকে “Restrict this content to members” checkbox চালু করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Allowed Plans থেকে এক বা একাধিক plan নির্বাচন করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Post/Page Update অথবা Publish করুন।', 'membexa' ); ?></li>
			</ol>
			<div class="membexa-help-danger"><strong><?php esc_html_e( 'Allowed Plans ভুল করে খালি রাখবেন না।', 'membexa' ); ?></strong> <?php esc_html_e( 'Content restricted কিন্তু কোনো plan selected না থাকলে edit permission ছাড়া visitor সেই content দেখতে পারবে না।', 'membexa' ); ?></div>
		</section>

		<section id="testing" class="membexa-help-section">
			<h2><?php esc_html_e( '৭. সম্পূর্ণ Customer Journey টেস্ট করুন', 'membexa' ); ?></h2>
			<ul class="membexa-help-checklist">
				<li><?php esc_html_e( 'Private/Incognito browser-এ Pricing page খুলুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Plan নির্বাচন করে নতুন test member register করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'শুধু compatible enabled gateway দেখাচ্ছে কিনা দেখুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Sandbox/test payment সম্পন্ন করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Account page-এ membership Active হয়েছে কিনা দেখুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Protected page active member হিসেবে খুলে দেখুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Logout করে protected content hidden কিনা দেখুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Recurring Stripe/PayPal হলে webhook site-এ আসছে কিনা যাচাই করুন।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'যেখানে প্রযোজ্য Account page থেকে cancellation test করুন।', 'membexa' ); ?></li>
			</ul>
		</section>

		<section id="manage" class="membexa-help-section">
			<h2><?php esc_html_e( '৮. Member ও Subscription ম্যানেজ করা', 'membexa' ); ?></h2>
			<p><?php esc_html_e( 'Membexa → Subscriptions থেকে membership status, gateway, start date এবং renewal/end date দেখা যাবে। Membexa → Members-এ member অনুযায়ী summary থাকবে। Frontend Account page-এ প্রতিটি signed-in member তার নিজের membership এবং cancellation control দেখতে পারবে।', 'membexa' ); ?></p>
			<div class="membexa-help-actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-subscriptions' ) ); ?>"><?php esc_html_e( 'Subscriptions দেখুন', 'membexa' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=membexa-members' ) ); ?>"><?php esc_html_e( 'Members দেখুন', 'membexa' ); ?></a>
			</div>
		</section>

		<section id="troubleshooting" class="membexa-help-section">
			<h2><?php esc_html_e( '৯. সমস্যা হলে কী দেখবেন', 'membexa' ); ?></h2>
			<table class="membexa-help-table">
				<thead><tr><th><?php esc_html_e( 'সমস্যা', 'membexa' ); ?></th><th><?php esc_html_e( 'যা যাচাই করবেন', 'membexa' ); ?></th></tr></thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Paid gateway দেখাচ্ছে না', 'membexa' ); ?></td><td><?php esc_html_e( 'Gateway enabled কিনা, required credential পূর্ণ কিনা এবং plan compatible কিনা দেখুন।', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Stripe checkout শুরু হচ্ছে না', 'membexa' ); ?></td><td><?php esc_html_e( 'Secret Key, plan-এর Stripe Price ID এবং Stripe price-এর billing model ঠিক আছে কিনা দেখুন।', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'PayPal recurring দেখাচ্ছে না', 'membexa' ); ?></td><td><?php esc_html_e( 'Monthly/Yearly PayPal-এর জন্য plan-এ valid P-... PayPal Plan ID লাগবে।', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'PayPal renewal/status update হচ্ছে না', 'membexa' ); ?></td><td><?php esc_html_e( 'PayPal webhook endpoint, subscribed events এবং saved Webhook ID যাচাই করুন।', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'bKash দেখাচ্ছে না', 'membexa' ); ?></td><td><?php esc_html_e( 'Merchant credentials সম্পূর্ণ কিনা এবং Plan BDT + One-time/Lifetime কিনা দেখুন।', 'membexa' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Active member content দেখতে পারছে না', 'membexa' ); ?></td><td><?php esc_html_e( 'Subscription active/trialing কিনা এবং protected content-এর Allowed Plans-এ একই plan selected কিনা দেখুন।', 'membexa' ); ?></td></tr>
				</tbody>
			</table>
		</section>

		<section id="launch" class="membexa-help-section">
			<h2><?php esc_html_e( '১০. Live করার আগে Final Checklist', 'membexa' ); ?></h2>
			<ul class="membexa-help-checklist">
				<li><?php esc_html_e( 'Pricing, Join, Account এবং optional Login page Publish করা হয়েছে।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'General Settings-এ Pricing ও Account page assign করা হয়েছে।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'প্রতিটি paid plan-এর currency, billing এবং required gateway ID সঠিক।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'প্রতিটি enabled gateway-এ কমপক্ষে একটি sandbox/test payment সফল হয়েছে।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Stripe ও PayPal webhook সঠিকভাবে configured।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Production-এ live credential নিরাপদভাবে রাখা হয়েছে এবং test credential সরানো হয়েছে।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Logged-out visitor ও active member—দুই অবস্থায় protected content test করা হয়েছে।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'Site Privacy Policy ও Terms-এ ব্যবহৃত payment provider disclosure আছে।', 'membexa' ); ?></li>
				<li><?php esc_html_e( 'বড় production change-এর আগে backup আছে।', 'membexa' ); ?></li>
			</ul>
			<div class="membexa-help-callout"><strong><?php esc_html_e( 'শেষ ধাপ:', 'membexa' ); ?></strong> <?php esc_html_e( 'উপরের সবকিছু ঠিক থাকলে ছোট একটি real transaction করে membership access verify করুন। তারপর public promotion শুরু করুন।', 'membexa' ); ?></div>
		</section>
		<?php
	}

	/**
	 * Render one bundled annotated help figure.
	 *
	 * @param string $file    SVG file name.
	 * @param string $alt     Image alt text.
	 * @param string $caption Figure caption.
	 */
	private function figure( $file, $alt, $caption ) {
		$url = MEMBEXA_URL . 'assets/help/' . sanitize_file_name( $file );
		?>
		<figure class="membexa-help-figure">
			<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy">
			<figcaption class="membexa-help-caption"><?php echo esc_html( $caption ); ?></figcaption>
		</figure>
		<?php
	}
}
