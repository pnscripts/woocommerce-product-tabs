# Changelog

All notable changes to PN Product Tabs. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

## [Unreleased]

Before the first WordPress.org submission (owner):

- WordPress.org account with 2FA; set the real username in `readme.txt` → `Contributors`.
- Submit the zip from `bin/build-zip.sh` and request the slug `pnscripts-product-tabs` (WordPress.org derives `pn-product-tabs-custom-tabs-faq` from the plugin name otherwise; the slug can only be changed before approval).
- After approval: upload `assets/screenshots/*.png` (and a banner/icon) to the SVN `assets/` folder.

## [1.0.1] - 2026-10-05

### Changed

- Plugin name is now "PN Product Tabs – Custom Tabs & FAQ" (was "PN Product Tabs for WooCommerce"): WordPress.org does not allow the term "WooCommerce" in a plugin's name or permalink. Slug, text domain, folder and code are unchanged.
- Bundled translations (bg_BG, de_DE, pl_PL) are loaded with `load_textdomain()` only when no language pack from translate.wordpress.org is installed, replacing the discouraged `load_plugin_textdomain()` (Plugin Check warning).
- The FAQPage JSON-LD is printed with `wp_print_inline_script_tag()` instead of a hand-written script tag.

## [1.0.0] - 2026-10-04

### Added

- Per-product tabs (rich text or FAQ) with drag-and-drop order, on/off switch and removal.
- Global tabs (block editor) for all products, categories (including subcategories), tags, or manual placement; per-product hiding and linking.
- Default tab manager: rename (Reviews title supports `%d`), reorder, hide store-wide or per product, keep Reviews last.
- FAQ tabs with optional, merged FAQPage JSON-LD.
- Block theme support for the tabbed and the accordion Product Details block.
- YIKES Custom Product Tabs importer: dry run, batches, re-runnable, undo, read-only on YIKES data, identical storefront; WP-CLI `import-yikes` and `tabs` commands.
- HPOS and cart/checkout blocks compatibility declarations.
- Translations: Bulgarian, German, Polish.
- Opt-in data removal on uninstall (multisite aware).
- Add-on hooks and a no-op `LicenseInterface` for a future Pro add-on.
