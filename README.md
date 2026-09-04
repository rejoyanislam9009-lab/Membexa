# Membexa

Membexa is a WordPress-native membership and subscription platform with plan-based content access, member accounts, modular standalone payment add-ons, and optional WooCommerce commerce entitlements.

## Status

Current development release: **1.6.0**. Changes are developed on feature branches, validated by CI and WordPress Plugin Check, then merged to `main`.

## Highlights

- Standalone free, one-time, monthly, yearly, and lifetime membership plans.
- Separate Stripe, PayPal, and bKash gateway add-ons for standalone Membexa plans.
- Unified **Membexa > Payments** hub with Add-ons, WooCommerce Gateways, and Discover tabs.
- Live visibility into every payment gateway currently registered by WooCommerce.
- Automatic detection of manually installed WooCommerce payment add-ons when possible.
- WordPress.org gateway discovery with capability- and nonce-protected install/activation through WordPress core APIs.
- Smart Account routing with WooCommerce My Account integration.
- WooCommerce product and product-category membership grants and access restrictions.
- WooCommerce order lifecycle synchronization and optional refund/cancellation revocation.
- WooCommerce Subscriptions lifecycle synchronization when that extension is installed.
- English/Bangla in-plugin Help & Setup Center with local annotated diagrams.

WooCommerce is optional. Membexa does not duplicate WooCommerce cart, tax, order, inventory, downloadable-product, or payment-processing systems.

## Requirements

- WordPress 6.4+
- Tested with WordPress 7.1
- PHP 7.4+

## Development

Runtime code has no Composer dependency. Composer is used only for development tooling.

```bash
composer install
composer lint
composer phpcs
```

CI runs PHP 7.4/8.2/8.4 quality checks, WordPress Plugin Check for Core and each bundled release add-on, Payments hub smoke coverage, modular payment smoke tests, and WooCommerce integration smoke tests.

## Security

Please report security issues privately to the maintainer rather than opening a public exploit report.

## License

GPL-2.0-or-later.
