# Membexa

Membexa is a WordPress-native membership and subscription platform with plan-based content access, member accounts, Stripe/PayPal/bKash checkout, and optional WooCommerce commerce entitlements.

## Status

Current development release: **1.3.0**. Changes are developed on feature branches, validated by CI and WordPress Plugin Check, then merged to `main`.

## Highlights

- Standalone free, one-time, monthly, yearly, and lifetime membership plans.
- Stripe, PayPal, and bKash payment integrations for standalone Membexa plans.
- Smart Account routing with WooCommerce My Account integration.
- WooCommerce product and product-category membership grants and access restrictions.
- WooCommerce order lifecycle synchronization and optional refund/cancellation revocation.
- WooCommerce Subscriptions lifecycle synchronization when that extension is installed.
- English/Bangla in-plugin Help & Setup Center with local annotated diagrams.

WooCommerce is optional. Membexa does not duplicate WooCommerce cart, tax, order, inventory, or downloadable-product systems.

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

CI also runs WordPress Plugin Check and a WooCommerce integration smoke test.

## Security

Please report security issues privately to the maintainer rather than opening a public exploit report.

## License

GPL-2.0-or-later.
