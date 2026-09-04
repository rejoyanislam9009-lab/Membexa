=== Membexa – Membership & Subscriptions ===
Contributors: wpzenora
Tags: membership, subscriptions, woocommerce, access control, members
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create memberships, protected content, recurring access, WooCommerce entitlements, and modular payment integrations in WordPress.

== Description ==

Membexa is a WordPress-native membership and subscription system. Membexa Core manages plans, members, subscriptions, gated content, account routing, WooCommerce entitlements, privacy tools, and transaction records.

Starting with version 1.5.0, payment providers are modular. Membexa Core does not require a payment gateway and does not expose provider credentials as part of the core payment setup flow. Install only the separate Membexa gateway add-ons that a site actually needs.

= Core features =

* Unlimited free, one-time, monthly, yearly, and lifetime membership plans.
* Automatic creation of Pricing, Join, Login, and Account pages.
* One-click Create / Repair Membexa Pages tool.
* Member registration, login, pricing, and account shortcodes.
* Content restrictions by membership plan.
* Smart Account mode with WooCommerce My Account integration.
* WooCommerce product and product-category membership grants and restrictions.
* WooCommerce order lifecycle and optional refund/cancellation revocation.
* WooCommerce Subscriptions lifecycle synchronization when that extension is installed.
* Compatible with simple, variable, virtual, and downloadable WooCommerce products.
* Payment Add-ons manager showing installed Membexa gateway extensions and setup status.
* Public gateway registration API so additional payment add-ons can integrate without modifying Membexa Core.
* Gateway-specific plan fields appear only while the matching add-on is active.
* Built-in Help & Setup Center with English and Bangla guides.
* Membership activation and cancellation emails.
* WordPress personal-data export and erasure integration.
* Optional full data cleanup on uninstall.
* No user tracking or telemetry.

== Modular payments ==

Membexa 1.5.0 separates payment providers from the core plugin.

Standalone Membexa plans can use separately installed Membexa gateway add-ons. The release suite includes independent add-ons for:

* Membexa Stripe Gateway
* Membexa PayPal Gateway
* Membexa bKash Gateway

When a gateway add-on is inactive, it is not offered at Membexa checkout and its plan-specific fields are not shown.

WooCommerce-connected products continue to use WooCommerce checkout. That means compatible WooCommerce payment gateway plugins can be used for WooCommerce product purchases without being bundled into Membexa Core.

Existing Membexa 1.4.x payment credentials are preserved during upgrade. Previously enabled gateway flags are staged for migration and restored automatically when the matching 1.5.0 gateway add-on is installed and activated.

== Automatic page setup ==

Membexa creates and connects the standard front-end pages automatically when they are missing:

* Membership Plans — `[membexa_pricing]`
* Join Membership — `[membexa_register]`
* Member Login — `[membexa_login]`
* My Membership — `[membexa_account]`

Administrators can run the process again from Membexa > Setup > Create / Repair Membexa Pages.

== Payment add-on setup ==

1. Install and activate Membexa Core.
2. Open Membexa > Payment Add-ons.
3. Install only the gateway add-on or add-ons needed by the site.
4. Each active gateway adds its own settings page under the Membexa menu.
5. Configure that provider on its add-on settings page.
6. Edit a membership plan. Gateway-specific plan fields are added by the active add-on only when needed.
7. Test with provider sandbox/test credentials before going live.

The legacy Membexa > Settings > Payments screen redirects to the Payment Add-ons manager in version 1.5.0.

== Stripe add-on ==

The separate Membexa Stripe Gateway add-on provides hosted Stripe Checkout for standalone Membexa plans. Stripe-specific credentials and plan Price IDs are configured by that add-on.

For Stripe payments, the add-on can send the member email, selected Stripe Price ID, local WordPress user/plan/subscription identifiers, and return URLs to Stripe. Stripe processes payment details on Stripe-hosted pages. Signed webhook events update local membership status.

* Stripe service: https://stripe.com/
* Stripe API keys: https://dashboard.stripe.com/apikeys
* Stripe Services Agreement: https://stripe.com/legal
* Stripe Privacy Policy: https://stripe.com/privacy

Stripe is a third-party service and is not operated by the Membexa author.

== PayPal add-on ==

The separate Membexa PayPal Gateway add-on provides PayPal Orders for one-time/lifetime memberships and PayPal Subscriptions for monthly/yearly memberships.

The add-on includes PayPal REST credential configuration, Webhook ID configuration, and Verify PayPal Connection status. The verifier accepts either the explicit Membexa-required PayPal webhook events or PayPal's `*` All Events wildcard.

For payment and subscription processing, the add-on can send order amount, currency, local subscription metadata, member email, PayPal Plan ID, and return URLs to PayPal. Verified PayPal webhooks update local membership lifecycle state.

* PayPal service: https://www.paypal.com/
* PayPal Developer Dashboard: https://developer.paypal.com/dashboard/
* PayPal legal agreements: https://www.paypal.com/legalhub/
* PayPal Privacy Statement: https://www.paypal.com/legalhub/privacy-full

PayPal is a third-party service and is not operated by the Membexa author.

== bKash add-on ==

The separate Membexa bKash Gateway add-on provides bKash Tokenized Checkout for compatible Bangladesh memberships. It is offered only for BDT one-time and lifetime plans in this release.

The add-on uses merchant Payment Gateway credentials provided through bKash onboarding. Merchant credentials remain server-side. Membership is activated only after the payment response is successfully verified.

* bKash service: https://www.bkash.com/
* bKash Business: https://www.bkash.com/en/business
* bKash Tokenized Checkout: https://www.bkash.com/en/page/tokenized_checkout
* bKash Privacy Notice: https://www.bkash.com/en/page/privacy-notice

bKash is a third-party service and is not operated by the Membexa author.

== WooCommerce payments ==

Membexa does not replace WooCommerce cart, checkout, orders, taxes, inventory, downloads, or payment gateways.

When a WooCommerce product or category grants a Membexa membership, WooCommerce remains the commerce source of truth. The customer pays through the payment gateways configured for WooCommerce. Membexa listens to the resulting order/subscription lifecycle and manages membership entitlement.

This lets store owners use suitable WooCommerce payment gateway plugins for WooCommerce-based membership products while keeping standalone Membexa gateways modular as separate add-ons.

== Installation ==

1. Upload the Membexa Core ZIP from Plugins > Add New > Upload Plugin.
2. Activate Membexa.
3. Open Membexa > Setup and create/repair required member pages if needed.
4. Create and publish one or more membership plans.
5. For free memberships, no payment add-on is required.
6. For standalone paid plans, install the desired Membexa payment gateway add-on.
7. For WooCommerce-based memberships, connect products/categories under the Membexa entitlement controls and use WooCommerce checkout gateways.
8. Open Membexa > Help & Setup for setup and testing guidance.

== Shortcodes ==

* `[membexa_pricing]` displays published plans and compatible active payment add-ons.
* `[membexa_register]` displays the standalone registration flow when Smart Account does not route to WooCommerce.
* `[membexa_login]` displays the Membexa/WordPress login experience or Smart Account route.
* `[membexa_account]` displays the signed-in member's memberships and cancellation controls.

== Frequently Asked Questions ==

= Does Membexa Core include Stripe, PayPal, or bKash settings? =

No. Version 1.5.0 uses separate gateway add-on plugins. Core provides the membership and gateway registry framework, while each provider add-on owns its own setup UI.

= What happens to payment settings from Membexa 1.4.x? =

Stored credentials are preserved. Previously enabled providers are marked for migration during the 1.5.0 upgrade. When the matching gateway add-on is installed, its prior enabled state is restored automatically.

= Can I use a different payment gateway plugin with WooCommerce? =

Yes. WooCommerce-connected membership products use WooCommerce checkout. Compatible WooCommerce gateway plugins continue to work through WooCommerce's payment system.

= Can third-party developers build more Membexa payment add-ons? =

Yes. Membexa 1.5.0 includes a public payment gateway registry with callbacks for availability, checkout, cancellation, settings links, and metadata. Add-ons can register without editing Membexa Core.

= Do I need a payment add-on for free plans? =

No.

= Does bKash support recurring memberships in the included add-on? =

Not in version 1.0.0 of the bKash add-on. It is intentionally limited to BDT one-time and lifetime memberships.

= Does Membexa store card numbers, PayPal passwords, or bKash PINs? =

No. Payment-provider credentials are administrative API credentials and payment approval happens through provider-hosted flows. Membexa does not collect card numbers, PayPal passwords, bKash PINs, or bKash OTPs.

== Privacy ==

Membexa stores WordPress user IDs, plan IDs, subscription status, gateway references, transaction amounts/currencies/status, and timestamps needed to provide membership access. Membexa itself does not send telemetry to the plugin author.

When a separate payment add-on is installed and selected, data required for that provider is sent to the corresponding third-party service as described above. Site owners are responsible for disclosing their selected payment providers and retention practices in their own privacy policy.

== Changelog ==

= 1.5.0 =
* Rebuilt payments as a modular add-on architecture.
* Membexa Core no longer activates Stripe, PayPal, or bKash checkout by itself.
* Added a public payment gateway registry for separate extensions.
* Added Membexa > Payment Add-ons with installed gateway status and setup links.
* Legacy Settings > Payments and legacy PayPal setup routes now redirect to the modular add-on experience.
* Moved gateway-specific plan fields behind add-on hooks so they appear only while the matching add-on is active.
* Added upgrade migration that preserves existing 1.4.x credentials and restores prior enabled state when the matching add-on is activated.
* Added separate Stripe, PayPal, and bKash gateway plugin packages.
* Added modular payment registry smoke testing and separate release ZIP packaging.
* WooCommerce-linked memberships continue using WooCommerce checkout and compatible WooCommerce gateway plugins.

= 1.4.3 =
* Accepted PayPal `*` All Events webhook subscriptions during connection verification.

= 1.4.2 =
* Fixed the PayPal Verify Connection nonce URL regression.

= 1.4.1 =
* Added PayPal API/webhook connection verification and visible status.

= 1.4.0 =
* Added automatic front-end page creation/repair and payment setup helpers.

= 1.3.0 =
* Added Smart Account and WooCommerce product/category membership entitlements.

= 1.2.0 =
* Added English/Bangla visual Help & Setup Center.

= 1.1.0 =
* Added PayPal and bKash gateway support.

= 1.0.0 =
* Initial stable release.
