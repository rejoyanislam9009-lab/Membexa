=== Membexa – Membership & Subscriptions ===
Contributors: wpzenora
Tags: membership, subscriptions, woocommerce, access control, members
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create memberships, protected content, recurring access, WooCommerce entitlements, and modular payment integrations in WordPress.

== Description ==

Membexa is a WordPress-native membership and subscription system. Membexa Core manages plans, members, subscriptions, gated content, account routing, WooCommerce entitlements, privacy tools, and transaction records.

Starting with version 1.5.0, payment providers are modular. Membexa Core does not require a payment gateway and does not expose provider credentials as part of the core payment setup flow. Install only the separate Membexa gateway add-ons that a site actually needs.

Version 1.7.0 rebuilds the Payments hub as a live WooCommerce payment workspace. The screen now shows a real WooCommerce connection state, reads registered gateway methods directly from WooCommerce, searches the official WordPress.org Plugins API, displays real plugin metadata, installs and activates gateway plugins through WordPress core, and then returns to the live WooCommerce gateway list for configuration.

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
* Live Payments hub with Add-ons, WooCommerce Gateways, and Discover tabs.
* Real WooCommerce connection indicator with the installed WooCommerce version.
* Live gateway counts and enabled/disabled state read from WooCommerce.
* Sequential WooCommerce gateway list with source-plugin identification and Configure links.
* Real WordPress.org gateway search with plugin name, description, icon, rating, active installs, compatibility metadata, and last update date.
* One-click install and activation through WordPress core, followed by a live WooCommerce gateway refresh.
* Stricter installed-gateway detection that excludes Membexa Core and unrelated WooCommerce checkout utilities.
* Fault containment so a broken third-party gateway cannot crash the entire Payments screen.
* Payment add-on manager showing installed Membexa gateway extensions and setup status.
* Public gateway registration API so additional payment add-ons can integrate without modifying Membexa Core.
* Gateway-specific plan fields appear only while the matching add-on is active.
* Built-in Help & Setup Center with English and Bangla guides.
* Membership activation and cancellation emails.
* WordPress personal-data export and erasure integration.
* Optional full data cleanup on uninstall.
* No user tracking or telemetry.

== Modular payments ==

Membexa keeps payment providers separate from the membership core. Standalone Membexa plans can use separately installed Membexa gateway add-ons. The release suite includes independent add-ons for:

* Membexa Stripe Gateway
* Membexa PayPal Gateway
* Membexa bKash Gateway

When a gateway add-on is inactive, it is not offered at Membexa checkout and its plan-specific fields are not shown.

WooCommerce-connected products continue to use WooCommerce checkout. Compatible WooCommerce payment gateway plugins can therefore be used for WooCommerce product purchases without being bundled into Membexa Core.

Existing Membexa 1.4.x payment credentials are preserved during upgrade. Previously enabled gateway flags are staged for migration and restored automatically when the matching Membexa gateway add-on is installed and activated.

== Automatic page setup ==

Membexa creates and connects the standard front-end pages automatically when they are missing:

* Membership Plans — `[membexa_pricing]`
* Join Membership — `[membexa_register]`
* Member Login — `[membexa_login]`
* My Membership — `[membexa_account]`

Administrators can run the process again from Membexa > Setup > Create / Repair Membexa Pages.

== Payments hub ==

Open Membexa > Payments.

The connection panel at the top reports whether WooCommerce is active and shows the WooCommerce version. The status is taken from the current WordPress installation; it is not a simulated value.

= Add-ons =

The Add-ons tab shows the separate Membexa Stripe, PayPal, and bKash add-ons plus installed WooCommerce payment gateway plugins that can be identified safely from plugin metadata.

Detection is intentionally stricter than a general text search. Membexa Core itself and unrelated WooCommerce tools are excluded even if their descriptions mention checkout or payments. Opening this tab does not instantiate every third-party WooCommerce gateway class.

= WooCommerce Gateways =

This tab reads WooCommerce's live payment gateway registry. It shows the number of registered, enabled, and disabled gateway methods, then lists the methods in sequence with gateway title, ID, source plugin, live enabled/disabled state, and a direct Configure button.

A payment plugin may be Active in WordPress while its WooCommerce gateway is Disabled. That is normal until the gateway is configured and enabled in WooCommerce. Membexa does not automatically enable an unconfigured payment method because many providers require credentials, merchant approval, webhooks, or other setup before they are safe to offer at checkout.

Unexpected third-party gateway errors are contained and displayed as an admin notice instead of causing an uncaught fatal error on the Membexa Payments page.

= Discover =

The Discover tab uses WordPress core `plugins_api()` against the official WordPress.org Plugins API. Search results display real plugin metadata returned by WordPress.org, including name, short description, plugin icon when available, active-install count, rating, tested WordPress version, PHP requirement, and last update date.

Search results are filtered to plugins whose identity clearly indicates a WooCommerce payment gateway or known payment provider. Each result has a WordPress.org details link and one of these actions:

* Install & activate — downloads through WordPress core and activates the installed plugin.
* Activate in WordPress — for an already installed but inactive gateway plugin.
* View live gateways — for an already active gateway plugin.

After a successful installation or activation, Membexa redirects to WooCommerce Gateways so the real methods registered by that plugin can be seen immediately. Provider credentials and gateway enablement are then handled from the normal WooCommerce Configure screen.

Membexa does not download executable payment plugins from arbitrary third-party servers. One-click discovery installation is limited to packages returned by the official WordPress.org plugin API. Gateway ZIPs from other sources can still be uploaded manually through the standard WordPress plugin uploader.

== Payment add-on setup ==

1. Install and activate Membexa Core.
2. Open Membexa > Payments.
3. Confirm the top connection panel says WooCommerce connected if WooCommerce-based billing is required.
4. For standalone Membexa billing, upload and activate only the Membexa gateway add-on or add-ons needed by the site.
5. For WooCommerce billing, open Discover and search for the provider name, for example Stripe, PayPal, bKash, SSLCommerz, Paystack, or Razorpay.
6. Click Install & activate. WordPress performs the normal capability, nonce, package download, installation, and activation checks.
7. Membexa returns to WooCommerce Gateways and displays the live gateway methods registered by the new plugin.
8. Click Configure beside the required gateway and complete the provider credentials/settings in WooCommerce.
9. Enable the gateway only after its required credentials and provider setup are complete.
10. Test payments with sandbox/test credentials before going live.

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

Installing a compatible gateway from Payments > Discover or installing/activating it manually through WordPress produces the same integration result: WooCommerce owns checkout, and Membexa consumes the resulting commerce lifecycle for linked memberships.

== Installation ==

1. Upload the Membexa Core ZIP from Plugins > Add New > Upload Plugin.
2. Activate Membexa.
3. Open Membexa > Setup and create/repair required member pages if needed.
4. Create and publish one or more membership plans.
5. For free memberships, no payment add-on is required.
6. For standalone paid plans, install the desired Membexa payment gateway add-on.
7. For WooCommerce-based memberships, connect products/categories under the Membexa entitlement controls and use WooCommerce checkout gateways.
8. Open Membexa > Payments to install, activate, inspect, and configure payment gateways.
9. Open Membexa > Help & Setup for setup and testing guidance.

== Shortcodes ==

* `[membexa_pricing]` displays published plans and compatible active payment add-ons.
* `[membexa_register]` displays the standalone registration flow when Smart Account does not route to WooCommerce.
* `[membexa_login]` displays the Membexa/WordPress login experience or Smart Account route.
* `[membexa_account]` displays the signed-in member's memberships and cancellation controls.

== Frequently Asked Questions ==

= Does Membexa Core include Stripe, PayPal, or bKash settings? =

No. Version 1.5.0 and newer use separate Membexa gateway add-on plugins. Core provides the membership and gateway registry framework, while each provider add-on owns its own setup UI.

= Can I install WooCommerce payment gateway plugins from inside Membexa? =

Yes. Open Membexa > Payments > Discover. Search results come from the official WordPress.org Plugins API. Administrators with the required WordPress capabilities can install and activate a compatible gateway plugin directly from that screen.

= Are the Discover results real plugins? =

Yes. Membexa uses WordPress core's plugin installer API and displays metadata returned by WordPress.org. It does not maintain a fake local list of gateway names.

= Why is a WordPress payment plugin Active but its gateway says Disabled? =

Plugin activation and gateway enablement are different states. Activating a plugin loads its code and lets it register payment methods. The individual WooCommerce gateway normally remains disabled until its merchant credentials/settings are configured and the site owner enables it.

= What if I install a gateway plugin manually? =

That also works. Clearly identified WooCommerce gateway plugins are shown under Add-ons. After activation, any payment method the plugin successfully registers with WooCommerce appears under WooCommerce Gateways.

= What if a third-party WooCommerce gateway is broken? =

Membexa isolates the Add-ons screen from WooCommerce gateway initialization and catches unexpected gateway-loading errors on the live WooCommerce Gateways screen. A broken third-party gateway should not produce an uncaught Membexa Payments page fatal error.

= What happens to payment settings from Membexa 1.4.x? =

Stored credentials are preserved. Previously enabled providers are marked for migration during the 1.5.0 upgrade. When the matching Membexa gateway add-on is installed, its prior enabled state is restored automatically.

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

The Payments > Discover screen contacts the official WordPress.org plugin API when an administrator opens that discovery interface, searches, paginates, or starts an installation. WordPress.org returns plugin metadata and official package download information. Membexa does not send member or payment transaction data during gateway discovery.

== Changelog ==

= 1.7.0 =
* Rebuilt Membexa > Payments as a live WooCommerce payment workspace.
* Added a real WooCommerce connected/disconnected indicator and WooCommerce version display.
* Added live Registered, Enabled, and Disabled gateway counters.
* Added sequential live WooCommerce gateway rows with description, source plugin, state, and Configure action.
* Rebuilt Discover as a real WordPress.org marketplace view with plugin icons, descriptions, active installs, ratings, compatibility metadata, last update dates, and WordPress.org detail links.
* Added robust support for both object-shaped and array-shaped WordPress.org plugin API results to prevent blank plugin rows.
* Added real search pagination and provider-name search.
* Install & activate now returns directly to the live WooCommerce gateway list after WordPress activates the plugin.
* Tightened payment-plugin detection so Membexa Core and unrelated WooCommerce checkout utilities are not misidentified as gateways.
* Expanded regression tests for API normalization, false-positive filtering, WordPress.org candidate validation, and live WooCommerce gateway rows.

= 1.6.1 =
* Fixed a critical error that could occur while opening Membexa > Payments on sites with third-party WooCommerce payment extensions.
* Made the Add-ons tab detect installed payment plugins from plugin metadata without initializing WooCommerce gateway classes.
* Added fault containment around WooCommerce gateway manager loading and individual gateway row inspection.
* Added regression coverage for malformed gateways and metadata-only payment add-on discovery.

= 1.6.0 =
* Replaced the single Payment Add-ons screen with Membexa > Payments.
* Added Add-ons, WooCommerce Gateways, and Discover tabs.
* Added live listing of WooCommerce payment gateways registered on the site, including disabled methods and source-plugin identification.
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
