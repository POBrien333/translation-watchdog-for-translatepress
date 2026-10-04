# Changelog

## 0.3.4

- Re-check icon: no underline (WordPress underlines link-style buttons, and the line spun with the icon).

## 0.3.3

- Re-check button (↻) next to each page link: checks that one page again and replaces its card — "✓ Nothing left to fix" when done. Also on pages that could not be fetched. Updates the saved result.

## 0.3.2

- No element label on image findings: page builders give every image the same class, so it adds nothing; the alt text identifies the image.

## 0.3.1

- The element under a finding is only shown when it has a class or ID (bare tags such as `strong` or `a` are useless for ignoring and read like part of the text), and is prefixed with "in".

## 0.3.0

- Each finding shows the element it is in (e.g. `button.breakdance-menu-close-button`).
- Fix: a batch could exceed the PHP time limit when pages are fetched one by one (local sites).
- Fix: the script and stylesheet are reloaded whenever they change (an old cached script made Skip fail with "Unknown string").
- New setting **Ignore elements**: CSS selectors (tag, .class, #id, [attribute] and combinations) whose text is not checked. Unsupported selectors are reported, not saved.

## 0.2.0

- Detection works for any language pair: each translated page is compared with its original; identical text is checked against TranslatePress' string tables (not translated / translated but not shown / not in TranslatePress). Deliberately identical translations are hidden.
- Several translation languages can be checked in one scan.
- Meta descriptions are checked too.
- Code-like identifiers (cookie names such as `_ga_…`, camelCase keys, file names) are ignored.
- Updates from GitHub releases.
- Replaces the German word list and its filters (`trwatch_source_words`, `trwatch_target_words`).
- Local sites (self-signed certificates) are fetched one page at a time instead of with direct cURL calls; `trwatch_sslverify` filter.

## 0.1.0

- First standalone version (previously a must-use plugin on citationstyler.com; installed as "1.0.0").
