# WordPress.org submission checklist: PN Scripts Tabcrest

Status 2026-10-10: not submitted yet. 1.0.4 renames the plugin to **PN Scripts Tabcrest – Custom Product Tabs & FAQ**, slug and text domain `pnscripts-tabcrest` (1.0.0–1.0.2 on GitHub: "PN Scripts Product Tabs – Custom Tabs & FAQ" / `pnscripts-product-tabs`; the interim 1.0.3 name was never tagged or published). Reasons: the WordPress.org review of a sibling plugin (PN Omnibus, now PN Scripts Pricetrail) rejected a name close to an existing product and asked for a distinctive leading term; "Product Tabs" collides with an existing plugin, and the 1.0.3 term is also the name of browser tab-manager extensions. "Tabcrest" had no WordPress.org results and no product of that name was found on the web (2026-10-10).

Form: https://wordpress.org/plugins/developers/add/ (log in first; the account needs 2FA).

## Before uploading

1. Create or pick the WordPress.org account that will own the plugin and turn on two-factor authentication.
2. Put that account's username in `readme.txt` → `Contributors:` (it says `pnscripts`, and no WordPress.org profile `pnscripts` exists on 2026-10-05). Commit, then rebuild the zip.
3. Build the zip: `bin/build-zip.sh` → `build/pnscripts-tabcrest-1.0.4.zip`
   (absolute: `/media/petar/c8fc2986-4b79-4d7b-9a8c-e6db653915ac/DEV/Projects/pnscripts/marketplace/woocommerce-product-tabs/build/pnscripts-tabcrest-1.0.4.zip`).
   Check that the header `Version` and readme `Stable tag` match (the script does not compare them).
4. Plugin Check 2.1.0 on WordPress 7.1.2 + WooCommerce 11.1.2 with that zip installed: `wp plugin check pnscripts-tabcrest --include-experimental --include-low-severity-errors --include-low-severity-warnings` must print "No errors found" (it did on 2026-10-10 for 1.0.4).
5. The name in `Plugin Name:` and in the readme's `=== … ===` line must be identical and must not contain restricted terms such as "WooCommerce" or "WordPress" (the form rejected "PN Product Tabs for WooCommerce" on 2026-10-05; the review of a sibling plugin on 2026-10-06 also asked for a distinctive term at the start, so the name starts with "PN Scripts Tabcrest", not with a generic term such as "Product Tabs"). "WooCommerce" in the description, tags and body text is fine.

## On the form

| Field | What to enter |
|-------|---------------|
| Plugin zip | `build/pnscripts-tabcrest-1.0.4.zip` (about 140 KB; one folder, `pnscripts-tabcrest/`) |
| Checkboxes | Read each one and tick them yourself (permission to upload, guidelines, GPL licence, Plugin Check). They are the owner's statements. |

## Right after uploading

- The page shows the slug WordPress.org derived from `Plugin Name` ("PN Scripts Tabcrest – Custom Product Tabs & FAQ"): expect `pn-scripts-tabcrest-custom-product-tabs-faq`. **Use the one-time "change slug" link on that page and request `pnscripts-tabcrest`.** The text domain, folder and main file all use `pnscripts-tabcrest`; a slug that does not match the text domain is a review finding. If the link is gone, reply to the review email (or write to plugins@wordpress.org) before approval; it cannot change after approval.

## Notes for the review email (paste if asked)

- Name: "Tabcrest" is our own distinctive term, placed after our brand "PN Scripts". The plugin is not affiliated with WooCommerce or with YIKES; "Custom Product Tabs" in the name describes the feature, and YIKES is named only in the description because the plugin can import its data.
- The code prefix `pnscripts_product_tabs` / `Pnscripts\ProductTabs` (and the `wp/v2/pnscripts-product-tabs` REST base) predates the rename to Tabcrest and is kept so that existing installs from GitHub keep their data; every prefix is unique to PN Scripts. The text domain is `pnscripts-tabcrest`.
- No external requests, tracking or licence checks; nothing is locked. The YIKES importer only reads `yikes_woo_*` data in the same database and never writes or deletes it.
- `unserialize()` in `src/Import/YikesMapper.php` reads YIKES' stored arrays with `allowed_classes => false`, matching YIKES' own reader.
- Tab content posted from the product and global-tab screens is unslashed as an array after the nonce and `edit_post` checks, then sanitised field by field in `TabSanitizer` / `GlobalTabs::save_settings()` (`wp_kses_post()` unless the user has `unfiltered_html`), the same trust model as post content. Rendered tab content goes through the core content filters like post content.
- The global-tab post type is readable over REST only for users who can `edit_products`.
- Bundled translations (bg_BG, de_DE, pl_PL) are loaded with `load_textdomain()` only when no translate.wordpress.org language pack is installed.

## After approval (SVN)

- `trunk/` = contents of the zip folder; `tags/1.0.4/` = copy of trunk.
- `assets/`: `assets/screenshots/screenshot-1.png` … `screenshot-6.png` from this repo (captions in `readme.txt` → Screenshots). Banner and icon do not exist yet; optional.
