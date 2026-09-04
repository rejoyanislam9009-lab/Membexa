=== Membexa – Membership & Subscriptions ===
Contributors: wpzenora
Tags: membership, subscriptions, stripe, paypal, bkash
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create memberships, protected content, recurring subscriptions, WooCommerce entitlements, and global payments in WordPress.

== Description ==

Membexa is a WordPress-native membership and subscription solution. It uses WordPress users for member accounts, native editing screens for membership plans and access rules, and dedicated tables for subscription and transaction records.

= Core features =

* Unlimited free, one-time, monthly, yearly, and lifetime membership plans.
* Automatic creation of required Pricing, Join, Login, and Account pages.
* One-click Create / Repair Membexa Pages setup tool that preserves existing selected pages.
* Member registration, login, pricing, and account shortcodes for custom layouts.
* Per-post, per-page, and public-post-type content restrictions by membership plan.
* Stripe-hosted Checkout for one-time and recurring plans.
* PayPal hosted checkout for one-time/lifetime payments and PayPal Subscriptions for monthly/yearly plans.
* PayPal connection verification with a visible Connected / needs-attention status in Payments settings.
* bKash Tokenized Checkout for Bangladesh BDT one-time and lifetime plans.
* Official provider setup links directly inside the Membexa Payments screen.
* PayPal Setup Assistant with standard setup guidance and a partner-ready automatic onboarding entry point.
* Server-side payment verification before membership activation.
* Signed Stripe webhooks and verified PayPal webhooks for payment/subscription lifecycle updates.
* Member self-service cancellation with gateway-aware cancellation handling.
* Per-plan currency, Stripe Price ID, and PayPal Plan ID configuration.
* WordPress-native administration screens for subscriptions, members, setup, and settings.
* Smart Account mode: WooCommerce My Account becomes the member hub when WooCommerce is active, with a Membexa Memberships tab and automatic standalone fallback when WooCommerce is absent.
* WooCommerce product and product-category entitlement rules: grant plans after purchase and restrict product visibility or purchase by active membership plan.
* WooCommerce order lifecycle integration with account-required membership purchases and optional refund/cancellation revocation limited to memberships created by the original order.
* WooCommerce Subscriptions lifecycle synchronization for subscription products when WooCommerce Subscriptions is installed.
* Compatible with WooCommerce simple, variable, virtual, and downloadable products without duplicating WooCommerce product, cart, order, tax, inventory, or download systems.
* Built-in Help & Setup Center with English and Bangla guides, annotated visual diagrams, payment setup, testing, troubleshooting, and go-live checklists.
* Membership activation and cancellation emails.
* WordPress personal-data export and erasure integration.
* Optional full data cleanup on uninstall.
* No user tracking or telemetry.

Free memberships do not require any payment provider. Payment gateways are optional and disabled by default.

== Automatic page setup ==

On a fresh activation, and when upgrading from a version older than 1.4.0, Membexa prepares the required front-end pages on the next administrator request if they are missing.

The default pages are:

* Membership Plans — `[membexa_pricing]`
* Join Membership — `[membexa_register]`
* Member Login — `[membexa_login]`
* My Membership — `[membexa_account]`

Membexa links the generated pages to the matching General and Accounts & Commerce settings. Existing selected WordPress pages are preserved and are not overwritten. Pages that were originally created by Membexa can have a missing Membexa shortcode repaired safely.

Administrators can run the process again at any time from Membexa > Setup > Create / Repair Membexa Pages.

== External service: Stripe ==

Membexa can optionally connect to Stripe when the site administrator enables Stripe under Membexa > Settings > Payments and supplies Stripe credentials. A Stripe account is required only for Stripe payments.

When a member starts a Stripe checkout, Membexa sends the member's email address, selected Stripe Price ID, local WordPress user ID, local membership plan ID, local subscription record ID, and success/cancel return URLs to Stripe to create a hosted Checkout Session. Stripe processes payment details on Stripe-hosted pages. Membexa receives signed webhook events containing payment and subscription status needed to activate, update, or cancel local membership access.

* Stripe service: https://stripe.com/
* Stripe API keys: https://dashboard.stripe.com/apikeys
* Stripe Services Agreement: https://stripe.com/legal
* Stripe Privacy Policy: https://stripe.com/privacy

Stripe is a third-party service and is not operated by the Membexa author.

== External service: PayPal ==

Membexa can optionally connect to PayPal when the site administrator enables PayPal and supplies REST API credentials. A PayPal Business account is required for production REST API integrations.

For one-time or lifetime payments, Membexa sends the order amount, currency, local subscription identifier, checkout return/cancel URLs, and relevant order metadata to PayPal. The customer completes approval on PayPal. After return, Membexa captures the approved order server-side and activates membership only after PayPal reports a completed capture.

For monthly or yearly memberships, Membexa sends the configured PayPal Plan ID, member email address, local subscription identifier, and return/cancel URLs to PayPal Subscriptions. Verified PayPal webhooks are used to process activation, updates, payment failures, cancellation, expiration, and successful recurring payments. PayPal webhook signatures are verified through PayPal's verification API using the configured Webhook ID.

Membexa includes a PayPal Setup Assistant. Standard setup opens the official PayPal Developer Dashboard so the administrator can create or select a REST app and enter the Client ID, Client Secret, and Webhook ID in Membexa.

Membexa 1.4.1 adds an explicit Verify PayPal Connection action. The check authenticates to the selected PayPal Sandbox or Live REST API using the saved application credentials, retrieves the saved webhook configuration from PayPal, confirms that its URL matches this WordPress site's Membexa PayPal endpoint, and verifies that all webhook events required by Membexa are subscribed. The resulting status is stored without storing OAuth access tokens and is invalidated automatically when the effective PayPal credentials, Webhook ID, or Sandbox/Live mode changes.

PayPal also documents an approved Partner Referrals / software-onboarding flow that can share a consenting seller's REST API credentials with downloadable ecommerce software after PayPal login and approval. Membexa does not ship a PayPal partner secret inside the plugin. Automatic partner onboarding is shown only when the distributor/site owner has separately configured an approved secure partner onboarding URL through `MEMBEXA_PAYPAL_PARTNER_CONNECT_URL` or the `membexa_paypal_partner_connect_url` filter. Without that approved partner setup, Membexa safely uses the standard Developer Dashboard flow and does not scrape or infer credentials from a PayPal login session.

* PayPal service: https://www.paypal.com/
* PayPal Developer Dashboard: https://developer.paypal.com/dashboard/
* PayPal developer documentation: https://developer.paypal.com/
* PayPal legal agreements: https://www.paypal.com/legalhub/
* PayPal Privacy Statement: https://www.paypal.com/legalhub/privacy-full

PayPal is a third-party service and is not operated by the Membexa author. Any separately configured partner-onboarding service is also external to WordPress and must be disclosed by the party operating that service.

== External service: bKash ==

Membexa can optionally connect to bKash Tokenized Checkout for Bangladesh when the site administrator enables bKash and supplies merchant Payment Gateway credentials provided through bKash merchant onboarding.

Membexa currently offers bKash only for plans whose currency is BDT and whose billing type is One-time or Lifetime. Monthly and yearly recurring memberships are intentionally not offered through bKash in this release.

When a member starts bKash checkout, Membexa obtains a grant token server-side and sends the payment amount, BDT currency, a merchant invoice number, a payer reference, and a callback URL to bKash. The member completes payment on a bKash-hosted checkout page. On the callback, Membexa verifies the local user/subscription request and executes the payment server-side using the returned payment ID. Membership is activated only after the execute response contains a successful transaction, matching BDT currency, and the expected amount. Merchant API credentials are never sent to the browser by Membexa.

* bKash service: https://www.bkash.com/
* bKash Business: https://www.bkash.com/en/business
* bKash Tokenized Checkout information: https://www.bkash.com/en/page/tokenized_checkout
* bKash Privacy Notice: https://www.bkash.com/en/page/privacy-notice

bKash is a third-party service and is not operated by the Membexa author. Availability and production credentials depend on bKash merchant approval and applicable bKash terms.

== Installation ==

1. Upload the `membexa` folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate Membexa.
3. Open Membexa > Setup. Membexa will create and connect any missing required member pages automatically.
4. If pages were deleted or changed later, use Create / Repair Membexa Pages.
5. Open Membexa > Plans and publish one or more membership plans.
6. Open Membexa > Settings > Payments and configure one or more optional payment methods.
7. Restrict posts or pages from the Membexa Access panel in the editor.
8. Optional: if WooCommerce is active, open Membexa > Accounts & Commerce, keep Auto / Smart account mode, then connect products or product categories to plans from the Membexa Membership Entitlements controls.
9. Open Membexa > Help & Setup for the English or Bangla visual setup and testing guide.

== Shortcodes ==

Membexa creates the standard shortcode pages automatically, but the shortcodes remain available for custom pages and page builders.

* `[membexa_pricing]` displays published plans and compatible enabled payment methods. Optional: `register_url="/join/"`. When omitted, Membexa uses Smart Account routing automatically.
* `[membexa_register]` displays registration, plan selection, and enabled payment methods. In WooCommerce account mode with My Account registration enabled, it routes users to the WooCommerce account experience instead of creating a competing registration surface.
* `[membexa_login]` displays the Membexa/WordPress login experience or routes to WooCommerce My Account when Smart Account uses WooCommerce.
* `[membexa_account]` displays the signed-in member's subscriptions and cancellation controls.

== Stripe setup ==

1. Open Membexa > Settings > Payments and use the official Stripe link shown under the Stripe heading.
2. Enable Stripe and enter the Secret Key and Webhook Signing Secret.
3. Add a Stripe Price ID to each plan that should be sold with Stripe.
4. Add the displayed Stripe webhook endpoint to Stripe.
5. Subscribe it to `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.paid`, and `invoice.payment_failed`.

For production sites, Stripe secrets can be defined in `wp-config.php` using `MEMBEXA_STRIPE_SECRET_KEY` and `MEMBEXA_STRIPE_WEBHOOK_SECRET`.

== PayPal setup ==

1. Open Membexa > Settings > Payments and select Setup PayPal, or open Membexa > Setup > PayPal Setup Assistant.
2. Standard setup opens the official PayPal Developer Dashboard.
3. Create or open a REST application and copy its Client ID and Client Secret.
4. Enable PayPal under Membexa > Settings > Payments. Keep Sandbox enabled while testing and disable Sandbox only when using live credentials.
5. For one-time or lifetime plans, no PayPal Plan ID is required.
6. For monthly or yearly memberships, create the recurring product/plan in PayPal and place its `P-...` Plan ID in the Membexa plan.
7. Create a PayPal webhook using the displayed Membexa PayPal webhook URL.
8. Subscribe the webhook to `BILLING.SUBSCRIPTION.ACTIVATED`, `BILLING.SUBSCRIPTION.UPDATED`, `BILLING.SUBSCRIPTION.CANCELLED`, `BILLING.SUBSCRIPTION.EXPIRED`, `BILLING.SUBSCRIPTION.SUSPENDED`, `BILLING.SUBSCRIPTION.PAYMENT.FAILED`, and `PAYMENT.SALE.COMPLETED`.
9. Copy the PayPal Webhook ID into Membexa > Settings > Payments and save the settings.
10. Click Verify PayPal Connection. A green Connected status means the REST credentials, Webhook ID, webhook URL, and required event subscriptions all passed server-side verification.

Production credentials can be defined in `wp-config.php` with `MEMBEXA_PAYPAL_CLIENT_ID`, `MEMBEXA_PAYPAL_CLIENT_SECRET`, and `MEMBEXA_PAYPAL_WEBHOOK_ID`.

Automatic PayPal credential connection requires separate PayPal Partner approval and a secure partner onboarding service. Membexa never places PayPal partner secrets in browser JavaScript or in the downloadable plugin package.

== bKash setup for Bangladesh ==

1. Open Membexa > Settings > Payments and use the official bKash Business link shown under the bKash heading.
2. Complete bKash Payment Gateway merchant onboarding and obtain the merchant API username, password, App Key, and App Secret.
3. Enable bKash and enter those credentials. Keep Sandbox enabled while testing with bKash sandbox credentials.
4. Create a Membexa plan using `BDT` as the currency and choose either `One-time payment` or `Lifetime` billing.
5. Membexa will automatically offer bKash for that compatible plan. Monthly/yearly bKash billing is not exposed.
6. Complete sandbox tests before switching to live merchant credentials.

Production bKash credentials can be stored in `wp-config.php` as `MEMBEXA_BKASH_USERNAME`, `MEMBEXA_BKASH_PASSWORD`, `MEMBEXA_BKASH_APP_KEY`, and `MEMBEXA_BKASH_APP_SECRET`.

== Frequently Asked Questions ==

= Do I still have to create shortcode pages manually? =

No. Version 1.4.0 and later create missing Pricing, Join, Login, and Account pages automatically and link them to the correct settings. The shortcodes remain available if you want custom pages.

= What does Create / Repair Membexa Pages do? =

It creates missing Membexa system pages, reconnects the matching settings, and repairs a missing shortcode only on pages that Membexa itself created. It does not overwrite an existing page selected by the administrator.

= How do I know whether PayPal is really connected? =

Save the PayPal Client ID, Client Secret, Webhook ID, and correct Sandbox/Live mode, then click Verify PayPal Connection in Membexa > Settings > Payments. Membexa displays Connected only after PayPal accepts the REST credentials and returns a webhook whose ID, URL, and required event subscriptions match the current Membexa configuration. If settings change later, the previous status becomes Needs verification automatically.

= Can Membexa automatically read my PayPal credentials after I log in? =

Only through PayPal's approved Partner Referrals / software-onboarding flow. A normal PayPal login or Log in with PayPal session does not give a WordPress plugin permission to read another REST app's Client Secret. Membexa therefore provides a safe standard setup flow by default and exposes automatic onboarding only when an approved partner connection has been separately configured.

= Does Membexa store card numbers or bKash PINs? =

No. Membexa uses hosted payment-provider checkout pages and server-to-server APIs. It does not collect card numbers, PayPal passwords, bKash PINs, or bKash OTPs.

= Can I create free plans? =

Yes. A free plan activates immediately after registration or selection and requires no payment provider.

= Which payment methods are included? =

Version 1.4.1 includes Stripe, PayPal, and bKash. Only gateways that are enabled, configured, and compatible with a plan are offered at checkout.

= Does PayPal support recurring memberships? =

Yes. Monthly and yearly Membexa plans can use PayPal Subscriptions when the plan has a valid PayPal Plan ID and PayPal webhooks are configured.

= Does bKash support recurring memberships in Membexa? =

Not in version 1.4.1. bKash is intentionally limited to BDT one-time and lifetime memberships so Membexa does not simulate or assume unsupported recurring billing behavior.

= Does Membexa replace WooCommerce? =

No. WooCommerce remains responsible for products, cart, checkout, orders, taxes, inventory, and downloadable-product permissions. Membexa connects those commerce events to membership plans and access rules. WooCommerce product purchases use payment gateways configured for WooCommerce; Membexa's built-in Stripe, PayPal, and bKash gateways remain available for standalone Membexa plan checkout.

= How are WooCommerce and Membexa login/registration pages handled? =

Membexa uses one WordPress user identity. Auto / Smart account mode uses WooCommerce My Account when WooCommerce is active and adds a Memberships tab. The automatically created Membexa Join page remains a safe fallback when WooCommerce My Account registration is disabled. Membexa never disables the core WordPress wp-login.php screen.

= Does uninstalling Membexa delete membership data? =

Not by default. Enable permanent uninstall cleanup under Membexa > Settings > Privacy & Data before uninstalling if you intentionally want all Membexa data removed.

== Privacy ==

Membexa stores WordPress user IDs, membership plan IDs, subscription status, payment gateway references, transaction amounts/currencies/status, and timestamps required to provide membership access. Membexa itself does not send telemetry or analytics to the plugin author.

When a payment gateway is configured and selected by a member, payment-related data described in the relevant External service section above is sent to that third-party provider. Site owners are responsible for disclosing their selected payment providers, legal basis, and retention practices in their own privacy policy.

== Changelog ==

= 1.4.1 =
* Added a Verify PayPal Connection action to the Payments settings screen.
* Added visible Connected, needs-verification, connection-failed, and webhook-attention states.
* PayPal verification authenticates against the selected Sandbox/Live API before reporting a successful connection.
* Added server-side Webhook ID and webhook URL verification through the PayPal Webhooks API.
* Added verification of all seven PayPal webhook events required by Membexa.
* Connection state is invalidated automatically when effective PayPal credentials, Webhook ID, or Sandbox/Live mode changes.
* No PayPal OAuth access token is persisted by the connection-status feature.

= 1.4.0 =
* Added automatic creation and assignment of Pricing, Join, Login, and Account pages.
* Added Membexa > Setup with one-click Create / Repair Membexa Pages.
* Existing selected pages are preserved and never overwritten by automatic setup.
* Added official Stripe, PayPal, and bKash setup links directly under the payment settings sections.
* Added PayPal Setup Assistant with credential-status checks and standard Developer Dashboard onboarding.
* Added partner-ready PayPal automatic onboarding entry point without shipping or exposing PayPal partner secrets.
* Documented the PayPal-approved Partner Referrals requirement for automatic seller credential sharing.

= 1.3.0 =
* Added Smart Account routing with Auto, Membexa, WooCommerce, and custom account modes.
* Added a Membexa Memberships endpoint inside WooCommerce My Account while keeping one WordPress user identity.
* Added safe registration fallback when WooCommerce My Account registration is disabled; core wp-login.php remains available.
* Added Membexa > Commerce integration overview.
* Added WooCommerce product-level membership grant, view restriction, and purchase restriction rules.
* Added WooCommerce product-category membership grant and access-rule inheritance.
* Added account-required checkout when a WooCommerce cart item grants membership.
* Added idempotent membership grants on qualifying WooCommerce processing/completed orders.
* Added optional refund/cancellation revocation scoped only to Membexa memberships created by that exact order.
* Added WooCommerce Subscriptions lifecycle synchronization.

= 1.2.0 =
* Added a WordPress-native Help & Setup page with English and Bangla guides and five local annotated visual diagrams.

= 1.1.0 =
* Added PayPal one-time/lifetime checkout, PayPal recurring subscriptions, and bKash Tokenized Checkout.

= 1.0.0 =
* Initial stable release with plans, registration, content restrictions, Stripe Checkout, privacy tools, and administration screens.
