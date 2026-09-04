=== Membexa – Membership & Subscriptions ===
Contributors: wpzenora
Tags: membership, subscriptions, woocommerce, access control, members
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create memberships, protected content, recurring access, WooCommerce entitlements, and modular payment integrations in WordPress.

== Description ==

Membexa is a WordPress-native membership and subscription system. Membexa Core manages plans, members, subscriptions, gated content, account routing, WooCommerce entitlements, privacy tools, and transaction records.

Starting with version 1.5.0, payment providers are modular. Membexa Core does not require a payment gateway and does not expose provider credentials as part of the core payment setup flow. Install only the separate Membexa gateway add-ons that a site actually needs.

Version 1.6.0 adds a unified Membexa Payments hub. Administrators can see installed Membexa add-ons, every payment method currently registered by WooCommerce, automatically detected manually installed WooCommerce payment add-ons, and a WordPress.org discovery screen for installing and activating compatible gateway plugins without leaving Membexa.

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
* Payments hub with Add-ons, WooCommerce Gateways, and Discover tabs.
* Lists all payment gateways currently registered by WooCommerce, including disabled methods.
* Detects manually installed WooCommerce payment add-ons when their plugin metadata or active gateway registration identifies them.
* One-click install and activation for compatible payment gateway plugins returned by the official WordPress.org plugin API.
* Payment add-on manager showing installed Membexa gateway extensions and setup status.
* Public gateway registration API so additional payment add-ons can integrate without modifying Membexa Core.
* Gateway-specific plan fields appear only while the matching add-on is active.
* Built-in Help & Setup Center with English and Bangla guides.
* Membership activation and cancellation emails.
* WordPress personal-data export and erasure integration.
* Optional full data cleanup on uninstall.
* No user tracking or telemetry.

== Modular payments ==

Membexa 1.5.0 separated payment providers from the core plugin. Membexa 1.6.0 keeps that modular architecture and adds a single administration hub for payment add-ons and WooCommerce gateways.

Standalone Membexa plans can use separately installed Membexa gateway add-ons. The release suite includes independent add-ons for:

* Membexa Stripe Gateway
* Membexa PayPal Gateway
* Membexa bKash Gateway

When a gateway add-on is inactive, it is not offered at Membexa checkout and its plan-specific fields are not shown.

WooCommerce-connected products continue to use WooCommerce checkout. That means compatible WooCommerce payment gateway plugins can be used for WooCommerce product purchases without being bundled into Membexa Core.

Existing Membexa 1.4.x payment credentials are preserved during upgrade. Previously enabled gateway flags are staged for migration and restored automatically when the matching gateway add-on is installed and activated.

== Automatic page setup ==

Membexa creates and connects the standard front-end pages automatically when they are missing:

* Membership Plans — `[membexa_pricing]`
* Join Membership — `[membexa_register]`
* Member Login — `[membexa_login]`
* My Membership — `[membexa_account]`

Administrators can run the process again from Membexa > Setup > Create / Repair Membexa Pages.

== Payments hub ==

Open Membexa > Payments.

= Add-ons =

The Add-ons tab shows the separate Membexa Stripe, PayPal, and bKash add-ons, plus detected WooCommerce payment add-on plugins installed on the WordPress site. If a compatible gateway plugin is installed manually through Plugins > Add New and activated, its registered WooCommerce payment methods appear automatically in the WooCommerce Gateways tab. Inactive installed payment add-ons are also detected when their plugin metadata clearly identifies them as WooCommerce payment gateways.

= WooCommerce Gateways =

This tab reads WooCommerce's live payment gateway registry and lists every currently registered method, including WooCommerce built-in methods and gateways registered by active third-party plugins. It shows gateway ID, source plugin, enabled/disabled status, and a direct Configure button to the matching WooCommerce payment settings section.

= Discover =

The Discover tab uses the official WordPress.org plugin API to search for WooCommerce payment gateway plugins. Administrators with WordPress plugin-install permissions can install and activate a compatible result directly from Membexa. WordPress performs the normal capability, nonce, package download, installation, and activation checks.

Membexa does not download executable payment plugins from arbitrary third-party servers. One-click discovery installation is limited to packages returned by the official WordPress.org plugin API. Gateway ZIPs from other sources can still be uploaded manually through the standard WordPress plugin uploader, after which Membexa detects them when possible.

== Payment add-on setup ==

1. Install and activate Membexa Core.
2. Open Membexa > Payments > Add-ons.
3. For standalone Membexa billing, upload and activate only the Membexa gateway add-on or add-ons needed by the site.
4. Each active Membexa gateway adds its own settings page under the Membexa menu.
5. For WooCommerce-based memberships, open Payments > WooCommerce Gateways to see every registered WooCommerce payment method.
6. Open Payments > Discover to search WordPress.org and install a compatible WooCommerce payment gateway directly from Membexa.
7. A WooCommerce gateway installed manually through WordPress is automatically reflected after activation because Membexa reads WooCommerce's gateway registry.
8. Test payment providers with sandbox/test credentials before going live.

The legacy Membexa > Settings > Payments and Membexa > Payment Add-ons screens redirect to the Payments hub.

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

The Payments > WooCommerce Gateways tab is a management view of the payment methods that WooCommerce actually registers on the site. Installing a compatible gateway from Payments > Discover or installing/activating it manually through WordPress produces the same integration result: WooCommerce owns checkout, and Membexa consumes the resulting commerce lifecycle for linked memberships.

== Installation ==

1. Upload the Membexa Core ZIP from Plugins > Add New > Upload Plugin.
2. Activate Membexa.
3. Open Membexa > Setup and create/repair required member pages if needed.
4. Create and publish one or more membership plans.
5. For free memberships, no payment add-on is required.
6. For standalone paid plans, install the desired Membexa payment gateway add-on.
7. For WooCommerce-based memberships, connect products/categories under the Membexa entitlement controls and use WooCommerce checkout gateways.
8. Open Membexa > Payments to manage add-ons and WooCommerce gateways from one place.
9. Open Membexa > Help & Setup for setup and testing guidance.

== Shortcodes ==

* `[membexa_pricing]` displays published plans and compatible active payment add-ons.
* `[membexa_register]` displays the standalone registration flow when Smart Account does not route to WooCommerce.
* `[membexa_login]` displays the Membexa/WordPress login experience or Smart Account route.
* `[membexa_account]` displays the signed-in member's memberships and cancellation controls.

== Frequently Asked Questions ==

= Does Membexa Core include Stripe, PayPal, or bKash settings? =

No. Version 1.5.0 and newer use separate gateway add-on plugins. Core provides the membership and gateway registry framework, while each provider add-on owns its own setup UI.

= Can I install WooCommerce payment gateway plugins from inside Membexa? =

Yes. Open Membexa > Payments > Discover. Membexa searches the official WordPress.org plugin API, and administrators with the required WordPress capabilities can install and activate compatible WooCommerce payment gateway plugins directly from that screen.

= What if I install a gateway plugin manually? =

That also works. After activation, any payment method the plugin registers with WooCommerce appears automatically under Membexa > Payments > WooCommerce Gateways. Inactive installed plugins are also shown under Add-ons when their metadata clearly identifies them as WooCommerce payment add-ons.

= Does Membexa show all WooCommerce gateways? =

The WooCommerce Gateways tab lists every payment gateway currently registered by WooCommerce on that WordPress site, including disabled gateway methods. Inactive plugins cannot register runtime gateway classes until WordPress activates them, so they are detected separately from plugin metadata when possible.

= What happens to payment settings from Membexa 1.4.x? =

Stored credentials are preserved. Previously enabled providers are marked for migration during the 1.5.0 upgrade. When the matching gateway add-on is installed, its prior enabled state is restored automatically.

= Can I use a different payment gateway plugin with WooCommerce? =

Yes. WooCommerce-connected membership products use WooCommerce checkout. Compatible WooCommerce gateway plugins continue to work through WooCommerce's payment system.

= Can third-party developers build more Membexa payment add-ons? =

Yes. Membexa includes a public payment gateway registry with callbacks for availability, checkout, cancellation, settings links, and metadata. Add-ons can register without editing Membexa Core.

= Do I need a payment add-on for free plans? =

No.

= Does bKash support recurring memberships in the included add-on? =

Not in version 1.0.0 of the bKash add-on. It is intentionally limited to BDT one-time and lifetime memberships.

= Does Membexa store card numbers, PayPal passwords, or bKash PINs? =

No. Payment-provider credentials are administrative API credentials and payment approval happens through provider-hosted flows. Membexa does not collect card numbers, PayPal passwords, bKash PINs, or bKash OTPs.

== Privacy ==

Membexa stores WordPress user IDs, plan IDs, subscription status, gateway references, transaction amounts/currencies/status, and timestamps needed to provide membership access. Membexa itself does not send telemetry to the plugin author.

When a separate payment add-on is installed and selected, data required for that provider is sent to the corresponding third-party service as described above. Site owners are responsible for disclosing their selected payment providers and retention practices in their own privacy policy.

The Payments > Discover screen contacts the official WordPress.org plugin API only when an administrator opens that discovery interface or starts an installation. WordPress.org returns plugin metadata and official package download information. Membexa does not send member or payment transaction data during gateway discovery.

== Changelog ==

= 1.6.0 =
* Replaced the single Payment Add-ons screen with Membexa > Payments.
* Added Add-ons, WooCommerce Gateways, and Discover tabs.
* Added live listing of every WooCommerce payment gateway registered on the site, including disabled methods and source-plugin identification.
* Added automatic detection of manually installed WooCommerce payment add-on plugins when active gateway registration or plugin metadata identifies them.
* Added nonce- and capability-protected activation for detected inactive payment add-ons.
* Added WordPress.org payment gateway discovery with one-click install and activation through WordPress core upgrader APIs.
* Added direct Configure links from Membexa to each WooCommerce gateway settings section.
* Added legacy redirects from the old Payment Add-ons route to the new Payments hub.
* Added Payments hub smoke testing while preserving Core/add-on isolation.

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