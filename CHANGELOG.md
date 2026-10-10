# Changelog

All notable changes to PN Scripts Tabcrest (formerly PN Scripts Product Tabs). Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

## [Unreleased]

Before the first WordPress.org submission (owner):

- WordPress.org account with 2FA; set the real username in `readme.txt` → `Contributors`.
- Submit the zip from `bin/build-zip.sh` and request the slug `pnscripts-tabcrest` (WordPress.org derives `pn-scripts-tabcrest-custom-product-tabs-faq` from the plugin name otherwise; the slug can only be changed before approval).
- After approval: upload `assets/screenshots/*.png` (and a banner/icon) to the SVN `assets/` folder.

## [1.0.4] - 2026-10-10

### Changed

- Renamed to "PN Scripts Tabcrest – Custom Product Tabs & FAQ" (was "PN Scripts Tabwise – Custom Product Tabs & FAQ" in 1.0.3, which was never tagged or published): "Tabwise" is also the name of existing browser tab-manager extensions, so it risked the same naming rejection on WordPress.org. Slug, folder, main file, text domain and bundled translations are now `pnscripts-tabcrest`; Plugin URI is https://pnscripts.com/marketplace/pnscripts-tabcrest; the GitHub repository moved to https://github.com/pnscripts/pnscripts-tabcrest (the old URL redirects) and the Composer package is `pnscripts/pnscripts-tabcrest`.
- Settings page slug is `pnscripts-tabcrest` (Products → Tab settings); links to the 1.0.x `page=pnscripts-product-tabs` still redirect to it.
- WP-CLI command is `wp pnscripts-tabcrest`; the 1.0.x `wp pnscripts-product-tabs` keeps working as an alias.
- Screenshots regenerated with the new name and command.
- Namespace, constants, hooks, option names, meta keys, the `pnscripts_ptab` post type, its REST base, CSS classes and asset handles are unchanged.

### Upgrade note

- The main file name changed. A site running 1.0.x from GitHub must activate the plugin again after replacing the `pnscripts-product-tabs` folder with `pnscripts-tabcrest`.

## [1.0.3] - 2026-10-06

### Changed

- Renamed to "PN Scripts Tabwise – Custom Product Tabs & FAQ" (was "PN Scripts Product Tabs – Custom Tabs & FAQ") before the WordPress.org submission: "Product Tabs" is too close to an existing plugin, and the review of a sibling plugin asked for a distinctive leading term. Slug, folder, main file (`pnscripts-tabwise.php`) and text domain are now `pnscripts-tabwise`; bundled translations renamed and recompiled.
- Settings page slug is `pnscripts-tabwise` (Products → Tab settings); links to the 1.0.x `page=pnscripts-product-tabs` redirect to it.
- WP-CLI command is `wp pnscripts-tabwise`; `wp pnscripts-product-tabs` keeps working as an alias.
- Namespace, constants, hooks, option names, meta keys, the `pnscripts_ptab` post type, its REST base, CSS classes and asset handles are unchanged, so tabs, settings and import bookkeeping are kept. The YIKES importer is unchanged.

### Upgrade note

- The main file name changed. A site running 1.0.x from GitHub must activate the plugin again after replacing the `pnscripts-product-tabs` folder with `pnscripts-tabwise`.

## [1.0.2] - 2026-10-06

### Changed

- Plugin name is now "PN Scripts Product Tabs – Custom Tabs & FAQ" (was "PN Product Tabs – Custom Tabs & FAQ"): the WordPress.org review of a sibling plugin (2026-10-06) asked for a distinctive term at the start of the name; "PN" alone is not. Slug, text domain (`pnscripts-product-tabs`), folder and code are unchanged; bundled translations of the name are updated.

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
