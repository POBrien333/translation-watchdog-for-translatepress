<?php
/**
 * Everything that talks to TranslatePress: languages, URL conversion, the string tables.
 */

if (!defined('ABSPATH')) exit;

function trwatch_trp() {
    return class_exists('TRP_Translate_Press') ? TRP_Translate_Press::get_trp_instance() : null;
}

function trwatch_trp_component($name) {
    $trp = trwatch_trp();
    return $trp ? $trp->get_component($name) : null;
}

function trwatch_trp_settings() {
    $settings = trwatch_trp_component('settings');
    return $settings ? (array) $settings->get_settings() : [];
}

function trwatch_default_language() {
    return (string) (trwatch_trp_settings()['default-language'] ?? '');
}

/** Translation languages (code => name), without the default language. */
function trwatch_target_languages() {
    $settings = trwatch_trp_settings();
    $codes = array_values(array_diff((array) ($settings['translation-languages'] ?? []), [trwatch_default_language()]));
    $names = [];
    $languages = trwatch_trp_component('languages');
    if ($languages && method_exists($languages, 'get_language_names')) $names = (array) $languages->get_language_names($codes);
    $out = [];
    foreach ($codes as $code) $out[$code] = $names[$code] ?? $code;
    return $out;
}

/** Languages chosen in the settings (default: all translation languages). */
function trwatch_languages() {
    $targets = trwatch_target_languages();
    $saved = array_values(array_intersect((array) get_option(TRWATCH_OPT_LANGS, []), array_keys($targets)));
    return $saved ?: array_keys($targets);
}

function trwatch_translated_url($url, $lang) {
    $conv = trwatch_trp_component('url_converter');
    return $conv ? $conv->get_url_for_language($lang, $url, '') : $url;
}

/* ---------- string tables ---------- */

/** TranslatePress stores originals HTML-encoded (&#8211;, &amp;, &nbsp;…) — compare decoded. */
function trwatch_normalize($text) {
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\u{00A0}", ' ', $text);
    return trim(preg_replace('/\s+/u', ' ', $text));
}

/** Spellings a page text may have in the tables. */
function trwatch_db_variants($text) {
    static $typo = ['–' => '&#8211;', '—' => '&#8212;', '‘' => '&#8216;', '’' => '&#8217;', '“' => '&#8220;',
                    '”' => '&#8221;', '„' => '&#8222;', '…' => '&#8230;', '″' => '&#8243;', '×' => '&#215;'];
    $amp = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8', false);
    return array_values(array_unique([$text, $amp, strtr($text, $typo), strtr($amp, $typo)]));
}

/** Existing TranslatePress tables for one target language. */
function trwatch_tables($lang) {
    static $cache = [];
    if (isset($cache[$lang])) return $cache[$lang];
    global $wpdb;
    $query = trwatch_trp_component('query');
    $tables = [];
    if ($query) {
        $candidates = [];
        if (method_exists($query, 'get_table_name')) $candidates[] = $query->get_table_name($lang);
        if (method_exists($query, 'get_gettext_table_name')) $candidates[] = $query->get_gettext_table_name($lang);
        foreach ($candidates as $table) {
            if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- checking a TranslatePress table exists
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) $tables[] = $table;
        }
    }
    return $cache[$lang] = $tables;
}

/**
 * Classify texts that look identical on the original and the translated page.
 *
 * @return array text => 'missing' (known, no translation) | 'not_shown' (translation exists, page shows the original)
 *               | 'identical' (translated to the same text on purpose) | 'unknown' (not in TranslatePress' tables)
 */
function trwatch_classify(array $texts, $lang) {
    global $wpdb;
    $out = array_fill_keys($texts, 'unknown');
    $tables = trwatch_tables($lang);
    if (!$texts || !$tables) return $out;

    $lookup = [];   // variant => page text
    foreach ($texts as $t) foreach (trwatch_db_variants($t) as $v) $lookup[$v] = $t;

    $rows = [];
    foreach ($tables as $table) {
        foreach (array_chunk(array_keys($lookup), 100) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '%s'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from TranslatePress, validated to [A-Za-z0-9_] and confirmed to exist; values prepared
            $found = $wpdb->get_results($wpdb->prepare("SELECT original, translated, status FROM `$table` WHERE original IN ($in)", $chunk), ARRAY_A);
            foreach ((array) $found as $row) $rows[] = $row;
        }
    }

    $verdict = [];
    foreach ($rows as $row) {
        $text = trwatch_normalize($row['original']);
        if (!isset($out[$text])) continue;   // collation matched a different case/spelling
        $translated = trwatch_normalize($row['translated'] ?? '');
        $state = $translated === '' || (int) $row['status'] === 0 ? 'missing'
            : ($translated === $text ? 'identical' : 'not_shown');
        // strongest finding wins: identical (intentional) > not_shown > missing
        $rank = ['missing' => 1, 'not_shown' => 2, 'identical' => 3];
        if (!isset($verdict[$text]) || $rank[$state] > $rank[$verdict[$text]]) $verdict[$text] = $state;
    }
    return array_merge($out, $verdict);
}
