=== Membexa – Membership & Subscription Plugin ===
Contributors: wpzenora
Tags: membership, subscriptions, content restriction, stripe, members
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create membership plans, member accounts, protected content, and Stripe Checkout subscriptions with a WordPress-native workflow.

== Description ==

Membexa is a membership and subscription plugin designed to feel at home inside WordPress. It uses WordPress users for member accounts, native editing screens for membership plans and access rules, and dedicated tables for subscription and transaction records.

= Core features =

* Unlimited free, one-time, monthly, yearly, and lifetime membership plans.
* Member registration, login, pricing, and account shortcodes.
* Per-post and per-page content restrictions by membership plan.
* Stripe-hosted Checkout for paid and recurring plans.
* Signed Stripe webhook handling for activation, renewal status, failed payments, and cancellations.
* Member self-service cancellation for Stripe subscriptions.
* Per-plan currency and Stripe Price IDs for international pricing.
* WordPress-native administration screens for subscriptions, members, and settings.
* Membership activation and cancellation emails.
* WordPress personal-data export and erasure integration.
* Optional full data cleanup on uninstall.
* No user tracking or telemetry.

Membexa does not process or store card numbers. When Stripe is enabled, payment details are entered on Stripe-hosted Checkout pages. Stripe availability, supported countries, currencies, taxes, and fees are governed by your Stripe account and Stripe's terms.

= External service: Stripe =

Membexa can optionally connect to Stripe when the site administrator enables Stripe under Membexa > Settings > Payments and supplies Stripe credentials. A Stripe account is required only for paid Stripe plans; free memberships work without Stripe.

When a member starts a paid checkout, Membexa sends the member's email address, the selected Stripe Price ID, the local WordPress user ID, the local membership plan ID, the local subscription record ID, and success/cancel return URLs to Stripe to create a hosted Checkout Session. Stripe then processes the payment on its own hosted Checkout page. Membexa receives signed webhook events from Stripe containing payment and subscription status information needed to activate, update, or cancel local membership access.

* Stripe service: https://stripe.com/
* Stripe legal / Services Agreement: https://stripe.com/legal
* Stripe Privacy Policy: https://stripe.com/privacy

Stripe is a third-party service and is not operated by the Membexa plugin author. Site owners are responsible for configuring Stripe and for ensuring their own use of Stripe is appropriate for their jurisdiction and business.

== Installation ==

1. Upload the `membexa` folder to `/wp-content/plugins/` or install the plugin ZIP from Plugins > Add New > Upload Plugin.
2. Activate Membexa.
3. Go to Membexa > Plans and publish one or more membership plans.
4. Create a pricing page with `[membexa_pricing register_url="/join/"]`.
5. Create a registration page with `[membexa_register]` and an account page with `[membexa_account]`.
6. Select the pricing and account pages under Membexa > Settings > General.
7. For paid plans, configure Stripe under Membexa > Settings > Payments and add the Stripe Price ID to each paid plan.
8. Add the displayed webhook endpoint to Stripe and subscribe it to `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, and `invoice.payment_failed`.
9. Restrict posts or pages from the Membexa Access panel in the editor.

For production sites, Stripe secrets can be defined in `wp-config.php` using `MEMBEXA_STRIPE_SECRET_KEY` and `MEMBEXA_STRIPE_WEBHOOK_SECRET`.

== Shortcodes ==

* `[membexa_pricing]` displays published plans. Optional: `register_url="/join/"`.
* `[membexa_register]` displays the registration and plan selection form.
* `[membexa_login]` displays the standard WordPress login form.
* `[membexa_account]` displays the signed-in member's subscriptions and cancellation controls.

== Frequently Asked Questions ==

= Does Membexa store credit-card numbers? =

No. Paid checkout is hosted by Stripe.

= Can I create free plans? =

Yes. A free plan activates immediately after registration or selection.

= Does Membexa support multiple currencies? =

Each plan has its own three-letter currency code. For Stripe plans, the currency and billing interval configured in Stripe should match the plan shown in WordPress.

= Does Membexa include PayPal? =

Not in version 1.0.0. The first stable release focuses on a complete Stripe Checkout flow and free memberships instead of shipping an incomplete second gateway.

= Does uninstalling the plugin delete membership data? =

Not by default. Enable permanent uninstall cleanup under Membexa > Settings > Privacy & Data before uninstalling if you intentionally want all Membexa data removed.

== Privacy ==

Membexa stores WordPress user IDs, membership plan IDs, subscription status, payment gateway references, transaction amounts/currencies/status, and timestamps required to provide membership access. Membexa itself does not send telemetry or analytics to the plugin author.

When Stripe is configured, checkout requests are sent to Stripe and subscription/payment status is received from Stripe through signed webhooks. Site owners are responsible for disclosing their payment provider and retention practices in their privacy policy. See the External service: Stripe section above for the service, legal, and privacy links.

== Changelog ==

= 1.0.0 =
* Initial stable release.
* Membership plans and WordPress-native admin screens.
* Registration, pricing, login, and account shortcodes.
* Content restrictions by plan.
* Stripe Checkout and signed webhook lifecycle handling.
* Privacy exporter/eraser and uninstall controls.
