# Security policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |

## Reporting a vulnerability

Please report security issues privately through GitHub's **private vulnerability reporting** for this
repository: open the **Security** tab and choose **Report a vulnerability**
(https://github.com/pnscripts/pnscripts-tabcrest/security/advisories/new). Do not open a public issue or
post in the WordPress.org support forum.

Include the plugin version, WordPress and WooCommerce versions, the steps to reproduce and the impact. We
acknowledge reports within 3 working days and aim to release a fix within 14 days for high-severity issues;
we will agree on a disclosure date with you and credit you unless you prefer otherwise.

You can also report through the [Wordfence](https://www.wordfence.com/threat-intel/) or
[Patchstack](https://patchstack.com/database/) programmes, which coordinate with plugin authors.

## Scope notes

- Tab content is stored like post content: users with `unfiltered_html` keep their HTML; other users' input is
  filtered with `wp_kses_post()`. Only users who can edit products can change tabs.
- The YIKES importer requires `manage_woocommerce` and a nonce, and never writes YIKES data.
- The plugin makes no external HTTP requests.
