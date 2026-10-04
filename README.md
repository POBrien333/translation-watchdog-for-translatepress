# Translation Watchdog for TranslatePress

Finds text that was never translated on a [TranslatePress](https://wordpress.org/plugins/translatepress-multilingual/) site — for **any language combination**.

It adds a **Watchdog** tab to the TranslatePress settings. On demand, it fetches every public page twice — the original and each translation, as a logged-out visitor sees them — and lists every piece of text that is still identical. TranslatePress' own string tables then tell it *why*:

| Badge | Meaning |
|---|---|
| **Not translated** | TranslatePress knows the text but has no translation. |
| **Translated, not shown** | A translation exists, but the page still shows the original — usually a spelling difference (curly quotes, dashes, HTML entities) between the stored string and the page. |
| **Not in TranslatePress** | The text isn't in TranslatePress' string list — it may come from JavaScript, an image or a plugin TranslatePress doesn't see. |

Text that was deliberately translated to the same words (brand names, "FAQ") is recognised and hidden automatically.

> **Status:** 0.x — in testing. Expect changes.

## Features

- Checks visible text, image `alt`, `title`, `placeholder`, `aria-label`, the page title and the meta description.
- Runs only when you open the tab or click **Refresh** — nothing in the background.
- Results appear while the scan runs; requests are made in parallel.
- Every finding links to the translated page and straight into the TranslatePress editor.
- Header/footer/popup strings are grouped once under **Sitewide**.
- **Skip** hides false positives, **Undo** brings them back; an allowlist ignores brand and product names.
- **Retry** rescans only the pages that could not be fetched.
- Several translation languages: choose which ones to check.

## Requirements

WordPress 6.5+, PHP 8.0+, TranslatePress (free or paid).

## Installation

Download the zip from the [latest release](../../releases/latest), then *Plugins → Add New → Upload Plugin*. Open *Settings → TranslatePress → Watchdog*.

Updates appear in the WordPress admin like any other plugin (from GitHub releases).

## Filters

| Filter | Purpose |
|---|---|
| `trwatch_urls` | Array of source-language URLs to check. |
| `trwatch_sslverify` | Whether to verify SSL certificates when fetching your own pages (default: off on local hosts such as `*.test`, on everywhere else). |

## Development

```bash
composer install
composer lint     # PHP syntax
composer phpcs    # WordPress security/i18n sniffs
```

Releases: bump the version in the plugin header and `TRWATCH_VERSION`, add a `CHANGELOG.md` entry, then push a tag `vX.Y.Z`. The release workflow builds the zip (with the `.pot` file) and attaches it to a GitHub release.

## License

GPL-2.0-or-later.
