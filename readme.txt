=== Translation Watchdog for TranslatePress ===
Contributors: patrickobrien
Tags: translatepress, translation, multilingual, quality, untranslated
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.3.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Finds text that was never translated on your TranslatePress site — for any language combination.

== Description ==

Adds a **Watchdog** tab to the TranslatePress settings. It compares every public page with each of its translations, as a logged-out visitor sees them, and lists text that is still identical. TranslatePress' string tables then classify each finding: not translated, translated but not shown, or not in TranslatePress at all. Deliberately identical translations are hidden.

* Scans only when you open the tab or click Refresh.
* Results appear while the scan runs.
* Links to each translated page and straight into the TranslatePress editor.
* Sitewide strings grouped once; Skip, Undo, allowlist and Retry.

== Installation ==

1. Install and activate TranslatePress.
2. Upload the plugin zip under Plugins → Add New → Upload Plugin and activate it.
3. Open Settings → TranslatePress → Watchdog.

== Changelog ==

= 0.3.7 =
* Fix: "Check again" on Dashboard → Updates finds new releases immediately.
* Fix: updates to the screen's look and behaviour take effect without a hard reload.

= 0.3.6 =
* Content aligned with the TranslatePress header and tabs; no line under the re-check icon.

= 0.3.5 =
* Plugin icon, shown on Dashboard → Updates.

= 0.3.4 =
* Re-check icon without underline.

= 0.3.3 =
* Re-check button next to each page: checks that one page again after you fixed it in the translator.

= 0.3.2 =
* No element label on image findings.

= 0.3.1 =
* The element is only shown when it has a class or ID, prefixed with "in".

= 0.3.0 =
* Each finding shows the element it is in; new setting "Ignore elements" (CSS selectors) to stop checking whole components.

= 0.2.0 =
* Language-independent detection (page comparison + TranslatePress string tables), several languages per scan.

= 0.1.0 =
* First standalone version.
