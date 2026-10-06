# AGENTS.md

This repository is part of the DEV workspace and uses the shared **AI Brain** (`DEV/ai-brain`).

1. Read the brain router first: [`../../../../ai-brain/AGENTS.md`](../../../../ai-brain/AGENTS.md)
2. Then the WordPress pack: [`../../../../ai-brain/.skills/platforms/wordpress/README.md`](../../../../ai-brain/.skills/platforms/wordpress/README.md) (security, i18n, directory submission, WooCommerce).
3. A repo profile (`ai-brain/projects/marketplace/woocommerce-product-tabs.md`) does not exist yet; this file and `README.md` are the source of truth until it does.
4. Rules written in this repository are **project rules** and override generic brain knowledge.

## Project rules

- Plugin name: "PN Scripts Tabwise – Custom Product Tabs & FAQ". Slug, folder, main file and text domain: `pnscripts-tabwise` (renamed from `pnscripts-product-tabs` in 1.0.3; the WP-CLI alias `pnscripts-product-tabs` and the old settings page slug redirect stay for 1.0.x users). Code prefix unchanged: namespace `Pnscripts\ProductTabs`; constants `PNSCRIPTS_PRODUCT_TABS_*`; hooks, options and meta prefixed `pnscripts_product_tabs` (`_pnscripts_product_tabs_*` for private meta); CPT `pnscripts_ptab`.
- The free plugin never locks features (WP.org guideline 5). Pro hooks in through filters/actions and `LicenseInterface`; nothing in this repo calls home.
- YIKES data is read-only for this plugin: import, re-import and undo must never write or delete `yikes_woo_*` data.
- Storefront rendering must not add queries per tab: global tabs come from the cached index option.
- Before committing: `composer lint`, `composer analyse`, `composer test`, `composer test:integration` (needs `WP_DIR=.cache/wp-test bin/setup-wp.sh` once).
- Never push, tag or submit to WordPress.org without the owner.
