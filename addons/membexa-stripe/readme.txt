=== Membexa Stripe Gateway ===
Contributors: wpzenora
Tags: membexa, membership, stripe, subscriptions
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stripe Checkout payment gateway add-on for Membexa Core 1.5.0 or newer.

== Description ==

Membexa Stripe Gateway is installed separately from Membexa Core. It adds Stripe-hosted Checkout for standalone paid Membexa membership plans, including recurring Stripe subscriptions when a compatible Stripe Price ID is configured.

Membexa Core remains usable without this add-on for free memberships and WooCommerce-connected memberships.

== Setup ==

1. Install and activate Membexa Core 1.5.0 or newer.
2. Install and activate Membexa Stripe Gateway.
3. Open Membexa > Stripe Gateway.
4. Enter the Stripe Secret Key and Webhook Signing Secret, then enable Stripe.
5. Add the displayed webhook endpoint in Stripe.
6. Edit a Membexa plan and enter its Stripe Price ID.
7. Test before going live.

== External service: Stripe ==

This add-on connects to Stripe only when the site administrator enables and configures it. During checkout it can send the member email address, Stripe Price ID, local WordPress user/plan/subscription identifiers, and return URLs to Stripe. Stripe handles payment details on Stripe-hosted pages. Signed webhook events update the local membership lifecycle.

* Stripe: https://stripe.com/
* API keys: https://dashboard.stripe.com/apikeys
* Legal: https://stripe.com/legal
* Privacy: https://stripe.com/privacy

Stripe is a third-party service and is not operated by the Membexa author.

== Changelog ==

= 1.0.0 =
* Initial modular Stripe gateway release for Membexa 1.5.0.
