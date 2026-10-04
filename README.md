# PN Product Tabs for WooCommerce

Custom product tabs for WooCommerce: per-product tabs, reusable global tabs by category or tag, FAQ tabs with
optional FAQPage schema, a manager for the default Description / Additional information / Reviews tabs, and a
one-click importer from **Custom Product Tabs for WooCommerce by YIKES** (80,000+ installs, last release 2025-04).

- WordPress.org slug / text domain: `pnscripts-product-tabs` (not submitted yet)
- Requires PHP 8.1+, WordPress 6.5+, WooCommerce 9.0+; tested with WordPress 7.1.2 and WooCommerce 11.1.2
- Licence: GPL-2.0-or-later. Copyright © 2026 ПН СКРИПТС ЕООД (PN Scripts)

## Features (all free)

| Area | What it does |
|------|--------------|
| Product tabs | Product data → Custom tabs: rich text (classic editor) or FAQ tabs, drag to reorder, switch off, link global tabs, hide global or default tabs on one product |
| Global tabs | Products → Product tabs (block editor): all products, categories (with subcategories) or tags, or manual; own priority |
| Default tabs | Products → Tab settings: rename (Reviews title supports `%d`), reorder, hide; keep Reviews last |
| FAQ | Native `<details>` list, one merged FAQPage JSON-LD per product page (per-tab and store-wide switches) |
| Themes | Classic templates, legacy Product Details block, and the accordion Product Details block (WooCommerce 10+): custom tabs are placed by priority and default tabs renamed/hidden there too |
| YIKES import | Dry run, batches of 100, re-runnable (hash per product), undo, YIKES data never written; storefront identical after import; YIKES copies hidden while both are active; `wp pnscripts-product-tabs import-yikes [--dry-run|--undo]` |
| Compatibility | HPOS and cart/checkout blocks declared; no queries per tab (one cached index option); no storefront JavaScript |
| i18n | `.pot` plus bg_BG, de_DE, pl_PL (`.po`, `.mo`, `.l10n.php`) |
| Uninstall | Keeps data unless "Delete all tabs and settings…" is on; multisite aware |

## Data model

| Data | Where |
|------|-------|
| Product tabs | product meta `_pnscripts_product_tabs` (list of `{id, type: content|faq|global, title, content, faq, schema, global_id, enabled, origin}`) |
| Hidden global / default tabs per product | `_pnscripts_product_tabs_hidden_globals`, `_pnscripts_product_tabs_hidden_defaults` |
| Global tabs | CPT `pnscripts_ptab` (block editor), meta `_pnscripts_product_tabs_{type,faq,schema,scope,categories,tags,priority}` |
| Storefront index of global tabs | option `pnscripts_product_tabs_index` (not autoloaded; rebuilt when a global tab changes) |
| Settings | option `pnscripts_product_tabs_settings` |
| Import bookkeeping | `_pnscripts_product_tabs_yikes_hash`, `_pnscripts_product_tabs_yikes_defaults` (product), `_pnscripts_product_tabs_yikes_id` (global tab), option `pnscripts_product_tabs_yikes_last_run` |

YIKES 1.8.6 data that the importer reads (verified in its source and with data written by its own UI):
post meta `yikes_woo_products_tabs` (`[{title, id, content}]`, `id` = WooCommerce tab key), option
`yikes_woo_reusable_products_tabs` (`[id => {tab_title, tab_name, tab_content, tab_id, tab_slug, taxonomies, global_tab}]`)
and option `yikes_woo_reusable_products_tabs_applied` (`[product_id => [saved_id => {post_id, reusable_tab_id, tab_id}]]`).

## Hooks for add-ons (Pro)

| Hook | Use |
|------|-----|
| `pnscripts_product_tabs_license` (filter) | Return a `Licensing\LicenseInterface`; the free plugin uses `FreeLicense` (no remote calls, nothing locked) |
| `pnscripts_product_tabs_global_tab_applies` (filter) | Advanced conditions and schedules: `(bool $applies, array $global_tab, array $context)` |
| `pnscripts_product_tabs_index_entry` (filter) | Store add-on rule data under `extra` in the cached index |
| `pnscripts_product_tabs_global_tab_settings` / `_global_tab_saved` (actions) | Extra fields in the global tab box and saving them |
| `pnscripts_product_tabs_resolved` (filter) | Final list of custom tabs for a product |
| `pnscripts_product_tabs_content`, `_heading`, `_faq_schema`, `_use_the_content`, `_accordion_integration` | Rendering |
| `pnscripts_product_tabs_loaded` (action) | Receives the service container (`Plugin`) |

## Development

```bash
composer install && npm install          # dev tools only; the plugin has no runtime dependencies
bin/setup-wp.sh                          # WordPress + WooCommerce + YIKES on SQLite in .cache/wp (no Docker/MySQL)
bin/serve.sh                             # http://127.0.0.1:8795 (admin/admin, local only)
WP_DIR=$PWD/.cache/wp-test bin/setup-wp.sh   # pristine site for integration tests

composer lint                            # WPCS 3 + PHPCompatibilityWP
composer analyse                         # PHPStan level 8 with WordPress and WooCommerce stubs
composer test                            # unit tests (Brain Monkey)
WP_DIR=$PWD/.cache/wp-test composer test:integration   # real WordPress + WooCommerce + YIKES 1.8.6
bin/i18n/update.sh                       # .pot, bundled .po/.mo/.l10n.php
bin/build-zip.sh                         # build/pnscripts-product-tabs-<version>.zip (honours .distignore)
GLOBAL_TAB=<id> npm run screenshots      # assets/screenshots/screenshot-N.png from the dev site
```

Minimum versions: `WP_DIR=$PWD/.cache/wp-min WP_VERSION=6.5.5 WC_VERSION=9.0.2 bin/setup-wp.sh`, then run the
integration suite with that `WP_DIR`.

## Release (owner)

See the checklist in `CHANGELOG.md` → Unreleased notes and the WordPress.org pack in the AI Brain
(`ai-brain/.skills/platforms/wordpress/directory-submission.md`). Nothing has been pushed, tagged or submitted.

## Trademarks

WooCommerce is a trademark of Automattic Inc.; YIKES and Custom Product Tabs for WooCommerce belong to their owners.
This plugin is not affiliated with them. "PN Scripts" and "PN Product Tabs" are trademarks of ПН СКРИПТС ЕООД and
are not covered by the code licence.
