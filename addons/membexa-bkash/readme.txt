=== Membexa bKash Gateway ===
Contributors: wpzenora
Tags: membexa, membership, bkash, bangladesh
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

bKash Tokenized Checkout payment gateway add-on for Membexa Core 1.5.0 or newer.

== Description ==

Membexa bKash Gateway is installed separately from Membexa Core. It adds bKash Tokenized Checkout for BDT one-time and lifetime Membexa membership plans.

The add-on requires bKash Payment Gateway merchant onboarding and merchant API credentials. Membexa Core remains usable without this add-on.

== Setup ==

1. Install and activate Membexa Core 1.5.0 or newer.
2. Install and activate Membexa bKash Gateway.
3. Open Membexa > bKash Gateway.
4. Enter the bKash merchant username, merchant password, App Key, and App Secret.
5. Keep Sandbox enabled while testing with sandbox credentials.
6. Create a Membexa plan using BDT with One-time or Lifetime billing.
7. Test a complete payment before switching to live credentials.

== External service: bKash ==

This add-on connects to bKash only when the site administrator enables and configures it. It sends the payment amount, BDT currency, merchant invoice number, payer reference, and callback information to bKash. Merchant credentials remain server-side. Membership is activated only after the bKash payment result is successfully executed and verified.

* bKash: https://www.bkash.com/
* bKash Business: https://www.bkash.com/en/business
* Tokenized Checkout: https://www.bkash.com/en/page/tokenized_checkout
* Privacy Notice: https://www.bkash.com/en/page/privacy-notice

bKash is a third-party service and is not operated by the Membexa author.

== Changelog ==

= 1.0.0 =
* Initial modular bKash gateway release for Membexa 1.5.0.
