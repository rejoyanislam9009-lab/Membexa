=== Membexa – Membership & Subscriptions ===
Contributors: wpzenora
Tags: membership, subscriptions, stripe, paypal, bkash
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create memberships, protected content, recurring subscriptions, and global payments with Stripe, PayPal, and bKash.

== Description ==

Membexa is a WordPress-native membership and subscription solution. It uses WordPress users for member accounts, native editing screens for membership plans and access rules, and dedicated tables for subscription and transaction records.

= Core features =

* Unlimited free, one-time, monthly, yearly, and lifetime membership plans.
* Member registration, login, pricing, and account shortcodes.
* Per-post, per-page, and public-post-type content restrictions by membership plan.
* Stripe-hosted Checkout for one-time and recurring plans.
* PayPal hosted checkout for one-time/lifetime payments and PayPal Subscriptions for monthly/yearly plans.
* bKash Tokenized Checkout for Bangladesh BDT one-time and lifetime plans.
* Server-side payment verification before membership activation.
* Signed Stripe webhooks and verified PayPal webhooks for payment/subscription lifecycle updates.
* Member self-service cancellation with gateway-aware cancellation handling.
* Per-plan currency, Stripe Price ID, and PayPal Plan ID configuration.
* WordPress-native administration screens for subscriptions, members, and settings.
* Built-in Help & Setup Center with English and Bangla guides, annotated visual diagrams, payment setup, testing, troubleshooting, and go-live checklists.
* Membership activation and cancellation emails.
* WordPress personal-data export and erasure integration.
* Optional full data cleanup on uninstall.
* No user tracking or telemetry.

Free memberships do not require any payment provider. Payment gateways are optional and disabled by default.

= External service: Stripe =

Membexa can optionally connect to Stripe when the site administrator enables Stripe under Membexa > Settings > Payments and supplies Stripe credentials. A Stripe account is required only for Stripe payments.

When a member starts a Stripe checkout, Membexa sends the member's email address, selected Stripe Price ID, local WordPress user ID, local membership plan ID, local subscription record ID, and success/cancel return URLs to Stripe to create a hosted Checkout Session. Stripe processes payment details on Stripe-hosted pages. Membexa receives signed webhook events containing payment and subscription status needed to activate, update, or cancel local membership access.

* Stripe service: https://stripe.com/
* Stripe Services Agreement: https://stripe.com/legal
* Stripe Privacy Policy: https://stripe.com/privacy

Stripe is a third-party service and is not operated by the Membexa author.

= External service: PayPal =

Membexa can optionally connect to PayPal when the site administrator enables PayPal and supplies REST API credentials. A PayPal business/developer account is required for PayPal payments.

For one-time or lifetime payments, Membexa sends the order amount, currency, local subscription identifier, checkout return/cancel URLs, and relevant order metadata to PayPal. The customer completes approval on PayPal. After return, Membexa captures the approved order server-side and activates membership only after PayPal reports a completed capture.

For monthly or yearly memberships, Membexa sends the configured PayPal Plan ID, member email address, local subscription identifier, and return/cancel URLs to PayPal Subscriptions. Verified PayPal webhooks are used to process activation, updates, payment failures, cancellation, expiration, and successful recurring payments. PayPal webhook signatures are verified through PayPal's verification API using the configured Webhook ID.

* PayPal service: https://www.paypal.com/
* PayPal Developer platform: https://developer.paypal.com/
* PayPal legal agreements: https://www.paypal.com/legalhub/
* PayPal Privacy Statement: https://www.paypal.com/legalhub/privacy-full

PayPal is a third-party service and is not operated by the Membexa author.

= External service: bKash =

Membexa can optionally connect to bKash Tokenized Checkout for Bangladesh when the site administrator enables bKash and supplies merchant Payment Gateway credentials provided through bKash merchant onboarding.

Membexa currently offers bKash only for plans whose currency is BDT and whose billing type is One-time or Lifetime. Monthly and yearly recurring memberships are intentionally not offered through bKash in this release.

When a member starts bKash checkout, Membexa obtains a grant token server-side and sends the payment amount, BDT currency, a merchant invoice number, a payer reference, and a callback URL to bKash. The member completes payment on a bKash-hosted checkout page. On the callback, Membexa verifies the local user/subscription request and executes the payment server-side using the returned payment ID. Membership is activated only after the execute response contains a successful transaction, matching BDT currency, and the expected amount. Merchant API credentials are never sent to the browser by Membexa.

* bKash service: https://www.bkash.com/
* bKash Tokenized Checkout terms: https://www.bkash.com/en/page/tokenized_checkout
* bKash Privacy Notice: https://www.bkash.com/en/page/privacy-notice

bKash is a third-party service and is not operated by the Membexa author. Availability and production credentials depend on bKash merchant approval and applicable bKash terms.

== Installation ==

1. Upload the `membexa` folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate Membexa.
3. Open Membexa > Help & Setup for the English or Bangla visual setup guide.
4. Go to Membexa > Plans and publish one or more membership plans.
5. Create a pricing page with `[membexa_pricing register_url="/join/"]`.
6. Create a registration page with `[membexa_register]` and an account page with `[membexa_account]`.
7. Select the pricing and account pages under Membexa > Settings > General.
8. Configure one or more payment methods under Membexa > Settings > Payments.
9. Restrict posts or pages from the Membexa Access panel in the editor.

= Built-in Help & Setup Center =

Membexa 1.2.0 includes a WordPress-native Help & Setup page under Membexa > Help & Setup. Site administrators can switch between English and Bangla without changing the WordPress locale. The guide includes annotated visual diagrams and step-by-step instructions for:

* Required pages and shortcodes.
* General settings and page assignment.
* Membership plan fields and gateway compatibility.
* Stripe, PayPal, and bKash setup.
* Content restriction with the Membexa Access panel.
* Sandbox/test checkout verification.
* Member and subscription management.
* Common troubleshooting cases.
* Production go-live checks.

The visual guide images are bundled locally with the plugin and do not load documentation images from a third-party server.

= Stripe setup =

1. Enable Stripe and enter the Secret Key and Webhook Signing Secret.
2. Add a Stripe Price ID to each plan that should be sold with Stripe.
3. Add the displayed Stripe webhook endpoint to Stripe.
4. Subscribe it to `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.paid`, and `invoice.payment_failed`.

For production sites, Stripe secrets can be defined in `wp-config.php` using `MEMBEXA_STRIPE_SECRET_KEY` and `MEMBEXA_STRIPE_WEBHOOK_SECRET`.

= PayPal setup =

1. Create a PayPal REST application and copy its Client ID and Client Secret.
2. Enable PayPal under Membexa > Settings > Payments. Keep Sandbox enabled while testing and disable Sandbox only when using live credentials.
3. For one-time or lifetime plans, no PayPal Plan ID is required.
4. For monthly or yearly memberships, create the recurring product/plan in PayPal and place its `P-...` Plan ID in the Membexa plan.
5. Create a PayPal webhook using the displayed Membexa PayPal webhook URL.
6. Subscribe the webhook to `BILLING.SUBSCRIPTION.ACTIVATED`, `BILLING.SUBSCRIPTION.UPDATED`, `BILLING.SUBSCRIPTION.CANCELLED`, `BILLING.SUBSCRIPTION.EXPIRED`, `BILLING.SUBSCRIPTION.SUSPENDED`, `BILLING.SUBSCRIPTION.PAYMENT.FAILED`, and `PAYMENT.SALE.COMPLETED`.
7. Copy the PayPal Webhook ID into Membexa > Settings > Payments so incoming events can be verified.

Production credentials can be defined in `wp-config.php` with `MEMBEXA_PAYPAL_CLIENT_ID`, `MEMBEXA_PAYPAL_CLIENT_SECRET`, and `MEMBEXA_PAYPAL_WEBHOOK_ID`.

= bKash setup for Bangladesh =

1. Complete bKash Payment Gateway merchant onboarding and obtain the merchant API username, password, App Key, and App Secret.
2. Under Membexa > Settings > Payments, enable bKash and enter those credentials. Keep Sandbox enabled while testing with bKash sandbox credentials.
3. Create a Membexa plan using `BDT` as the currency and choose either `One-time payment` or `Lifetime` billing.
4. Membexa will automatically offer bKash for that compatible plan. Monthly/yearly bKash billing is not exposed.
5. Complete sandbox tests before switching to live merchant credentials.

Production bKash credentials can be stored in `wp-config.php` as `MEMBEXA_BKASH_USERNAME`, `MEMBEXA_BKASH_PASSWORD`, `MEMBEXA_BKASH_APP_KEY`, and `MEMBEXA_BKASH_APP_SECRET`.

== Shortcodes ==

* `[membexa_pricing]` displays published plans and compatible enabled payment methods. Optional: `register_url="/join/"`.
* `[membexa_register]` displays registration, plan selection, and enabled payment methods.
* `[membexa_login]` displays the standard WordPress login form.
* `[membexa_account]` displays the signed-in member's subscriptions and cancellation controls.

== Frequently Asked Questions ==

= Does Membexa include setup documentation inside WordPress? =

Yes. Version 1.2.0 includes Membexa > Help & Setup with English and Bangla guides plus locally bundled annotated visual diagrams.

= Does Membexa store card numbers or bKash PINs? =

No. Membexa uses hosted payment-provider checkout pages and server-to-server APIs. It does not collect card numbers, PayPal passwords, bKash PINs, or bKash OTPs.

= Can I create free plans? =

Yes. A free plan activates immediately after registration or selection and requires no payment provider.

= Which payment methods are included? =

Version 1.2.0 includes Stripe, PayPal, and bKash. Only gateways that are enabled, configured, and compatible with a plan are offered at checkout.

= Does PayPal support recurring memberships? =

Yes. Monthly and yearly Membexa plans can use PayPal Subscriptions when the plan has a valid PayPal Plan ID and PayPal webhooks are configured.

= Does bKash support recurring memberships in Membexa? =

Not in version 1.2.0. bKash is intentionally limited to BDT one-time and lifetime memberships so Membexa does not simulate or assume unsupported recurring billing behavior.

= Does Membexa support multiple currencies? =

Each plan has its own three-letter currency code. Stripe and PayPal compatibility depends on the payment provider and your merchant account. bKash plans in this release must use BDT.

= Does uninstalling Membexa delete membership data? =

Not by default. Enable permanent uninstall cleanup under Membexa > Settings > Privacy & Data before uninstalling if you intentionally want all Membexa data removed.

== Privacy ==

Membexa stores WordPress user IDs, membership plan IDs, subscription status, payment gateway references, transaction amounts/currencies/status, and timestamps required to provide membership access. Membexa itself does not send telemetry or analytics to the plugin author.

When a payment gateway is configured and selected by a member, payment-related data described in the relevant External service section above is sent to that third-party provider. Site owners are responsible for disclosing their selected payment providers, legal basis, and retention practices in their own privacy policy.

== Changelog ==

= 1.2.0 =
* Added a WordPress-native Help & Setup page under the Membexa admin menu.
* Added complete English and Bangla setup guides without requiring a WordPress locale change.
* Added five locally bundled annotated visual diagrams for setup order, pages/shortcodes, plans, payments, and content restriction.
* Added payment compatibility guidance for Stripe, PayPal, and bKash.
* Added sandbox/testing workflow, member-management guidance, troubleshooting table, and production go-live checklist.
* Added direct Help Center links to relevant Membexa admin screens.

= 1.1.0 =
* Added PayPal one-time/lifetime checkout using PayPal Orders.
* Added PayPal monthly/yearly recurring memberships using PayPal Subscriptions.
* Added verified PayPal webhook lifecycle handling and gateway cancellation.
* Added bKash Tokenized Checkout for BDT one-time and lifetime memberships in Bangladesh.
* Added server-side bKash grant-token, create-payment, execute-payment, amount/currency verification, and credential handling.
* Added gateway-aware checkout selection and plan compatibility validation.
* Added PayPal and bKash settings, credential warnings, plan fields, setup documentation, and third-party service disclosures.

= 1.0.0 =
* Initial stable release.
* Membership plans and WordPress-native admin screens.
* Registration, pricing, login, and account shortcodes.
* Content restrictions by plan.
* Stripe Checkout with delayed-payment, renewal, failure, and signed webhook lifecycle handling.
* Privacy exporter/eraser and uninstall controls.
