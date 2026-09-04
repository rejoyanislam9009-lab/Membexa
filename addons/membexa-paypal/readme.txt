=== Membexa PayPal Gateway ===
Contributors: wpzenora
Tags: membexa, membership, paypal, subscriptions
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

PayPal Orders and Subscriptions payment gateway add-on for Membexa Core 1.5.0 or newer.

== Description ==

Membexa PayPal Gateway is installed separately from Membexa Core. It adds PayPal Orders for one-time/lifetime Membexa memberships and PayPal Subscriptions for monthly/yearly plans.

The add-on includes its own PayPal settings page and server-side connection verification. Membexa Core remains usable without this add-on.

== Setup ==

1. Install and activate Membexa Core 1.5.0 or newer.
2. Install and activate Membexa PayPal Gateway.
3. Open Membexa > PayPal Gateway.
4. Enter the PayPal Client ID, Client Secret, and Webhook ID; keep Sandbox enabled while testing.
5. Configure the displayed webhook URL in the matching PayPal application.
6. Subscribe the webhook to the Membexa-required events or PayPal All Events.
7. Click Verify PayPal Connection.
8. For monthly/yearly plans, add the PayPal P-... Plan ID in the Membexa plan editor.
9. Test before switching to live mode.

== External service: PayPal ==

This add-on connects to PayPal only when the site administrator enables and configures it. It can send order amount, currency, member email, local subscription metadata, PayPal Plan ID, and checkout return/cancel URLs to PayPal. PayPal-hosted approval and verified PayPal webhooks are used to manage payment and subscription lifecycle events.

* PayPal: https://www.paypal.com/
* Developer Dashboard: https://developer.paypal.com/dashboard/
* Legal: https://www.paypal.com/legalhub/
* Privacy: https://www.paypal.com/legalhub/privacy-full

PayPal is a third-party service and is not operated by the Membexa author.

== Changelog ==

= 1.0.0 =
* Initial modular PayPal gateway release for Membexa 1.5.0.
