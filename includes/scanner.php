<?php
/**
 * Scanning: which pages, fetching them, and comparing each translation with its original.
 */

if (!defined('ABSPATH')) exit;

/* ---------- URL list ---------- */

/** Source-language URLs of every public page, post, product and archive. */
function trwatch_source_urls() {
    $urls = [home_url('/')];
    $posts = get_posts([
        'post_type'      => array_values(array_diff(get_post_types(['public' => true]), ['attachment'])),
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'has_password'   => false,
        'no_found_rows'  => true,
    ]);
    foreach ($posts as $post) $urls[] = get_permalink($post);

    $taxonomies = array_values(array_diff(get_taxonomies(['public' => true, 'publicly_queryable' => true]), ['post_format']));
    $terms = $taxonomies ? get_terms(['taxonomy' => $taxonomies, 'hide_empty' => true]) : [];
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) $urls[] = get_term_link($term);
    }

    /** Filters the source-language URLs to check. */
    $urls = (array) apply_filters('trwatch_urls', $urls);
    return array_values(array_unique(array_filter($urls, 'is_string')));
}

/* ---------- allowlist / skipped ---------- */

function trwatch_allowlist() {
    return array_filter(array_map('trim', explode("\n", (string) get_option(TRWATCH_OPT_ALLOW, ''))));
}

function trwatch_skipped() {
    return (array) get_option(TRWATCH_OPT_SKIP, []);
}

function trwatch_filter_skipped(array $strings) {
    $skipped = trwatch_skipped();
    return array_values(array_filter($strings, fn($s) => !in_array($s['text'], $skipped, true)));
}

/* ---------- fetching ---------- */

/** Local dev hosts use self-signed certificates. */
function trwatch_is_local() {
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    return in_array(wp_get_environment_type(), ['local', 'development'], true)
        || (bool) preg_match('/(^localhost|\.test|\.localhost|\.local)$/', $host);
}

/**
 * Fetch URLs. In parallel (WordPress' bundled Requests library) when certificates are verified;
 * one by one through the HTTP API when they are not (local sites), because Requests' parallel mode
 * cannot switch verification off.
 *
 * @return array url => ['html' => string|null, 'error' => string|null]
 */
function trwatch_fetch(array $urls) {
    // a batch waits up to TRWATCH_TIMEOUT per request (more for redirects); shared hosts often stop scripts at 30 s
    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
    if (function_exists('set_time_limit')) set_time_limit(TRWATCH_TIMEOUT * 3);
    /** Filters whether SSL certificates are verified when fetching your own pages. */
    $verify = (bool) apply_filters('trwatch_sslverify', !trwatch_is_local());
    $ua = 'Translation-Watchdog/' . TRWATCH_VERSION;
    $out = [];

    if ($verify && class_exists('\WpOrg\Requests\Requests') && count($urls) > 1) {
        $reqs = [];
        foreach ($urls as $u) $reqs[$u] = ['url' => $u, 'type' => 'GET', 'headers' => ['User-Agent' => $ua]];
        $responses = \WpOrg\Requests\Requests::request_multiple($reqs, ['timeout' => TRWATCH_TIMEOUT, 'connect_timeout' => 10, 'redirects' => 3]);
        foreach ($urls as $u) {
            $r = $responses[$u] ?? null;
            if ($r instanceof \WpOrg\Requests\Response) {
                $out[$u] = (int) $r->status_code === 200 ? ['html' => $r->body, 'error' => null] : ['html' => null, 'error' => sprintf('HTTP %d', $r->status_code)];
            } else {
                $out[$u] = ['html' => null, 'error' => $r instanceof \Exception ? $r->getMessage() : 'No response'];
            }
        }
        return $out;
    }

    foreach ($urls as $u) {
        $res = wp_remote_get($u, ['timeout' => TRWATCH_TIMEOUT, 'sslverify' => $verify, 'redirection' => 3, 'headers' => ['User-Agent' => $ua]]);
        if (is_wp_error($res)) { $out[$u] = ['html' => null, 'error' => $res->get_error_message()]; continue; }
        $code = (int) wp_remote_retrieve_response_code($res);
        $out[$u] = $code === 200 ? ['html' => wp_remote_retrieve_body($res), 'error' => null] : ['html' => null, 'error' => sprintf('HTTP %d', $code)];
    }
    return $out;
}

/* ---------- extraction & comparison ---------- */

/** Every piece of text a visitor sees or a screen reader announces: text => where it was found. */
function trwatch_extract($html) {
    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $xp = new DOMXPath($dom);

    $skip = "ancestor::script or ancestor::style or ancestor::noscript or ancestor::template or ancestor::svg"
          . " or ancestor::*[@data-no-translation] or ancestor::*[@translate='no']"
          . " or ancestor::*[@id='wpadminbar'] or ancestor::*[contains(@class,'trp-language-switcher')]"
          . " or ancestor::*[contains(@class,'trp-floater-ls')]";
    $found = [];
    $add = function ($text, $where) use (&$found) {
        $t = trwatch_normalize($text);
        if ($t !== '' && !isset($found[$t])) $found[$t] = $where;
    };
    foreach ($xp->query("//body//text()[normalize-space() and not($skip)]") as $n) $add($n->nodeValue, 'text');
    foreach ($xp->query("//body//*[(@alt or @title or @placeholder or @aria-label) and not($skip)]") as $el) {
        foreach (['alt', 'title', 'placeholder', 'aria-label'] as $attr) {
            if ($el->hasAttribute($attr)) $add($el->getAttribute($attr), $attr);
        }
    }
    foreach ($xp->query('//title') as $el) $add($el->textContent, 'title tag');
    foreach ($xp->query("//meta[@name='description']/@content") as $a) $add($a->value, 'meta description');
    return $found;
}

/** Whether identical text on both pages is worth reporting (not a number, URL, brand-like token…). */
function trwatch_is_meaningful($text, array $allow) {
    // code-like identifiers: cookie names (_ga_M91EW3L52D, _clck), CSS classes, keys, file names
    if (!preg_match('/\s/u', $text) && preg_match('/[_\d]|^[^\p{L}]|\p{Ll}\p{Lu}|\.\p{L}{2,4}$/u', $text)) return false;
    foreach ($allow as $a) $text = str_ireplace($a, ' ', $text);
    $text = preg_replace(['~https?://\S+~u', '~\S+@\S+\.\S+~u'], ' ', $text);
    preg_match_all('/\p{L}+/u', $text, $m);
    $words = $m[0];
    if (!$words || mb_strlen(implode('', $words)) < 3) return false;           // numbers, prices, symbols
    if (count($words) === 1 && !preg_match('/\p{Ll}/u', $words[0])) return false;   // CSL, FAQ, DHBW
    return true;
}

/**
 * Compare a translated page with its original.
 *
 * @return array list of ['text', 'where', 'status'] — texts identical on both pages, classified by TranslatePress' tables
 */
function trwatch_compare($sourceHtml, $targetHtml, $lang, array $allow) {
    $source = trwatch_extract($sourceHtml);
    $target = trwatch_extract($targetHtml);
    $same = array_filter(array_intersect_key($target, $source), fn($where, $text) => trwatch_is_meaningful($text, $allow), ARRAY_FILTER_USE_BOTH);
    if (!$same) return [];

    $status = trwatch_classify(array_keys($same), $lang);
    $out = [];
    foreach ($same as $text => $where) {
        if ($status[$text] === 'identical') continue;   // translated to the same text on purpose
        $out[] = ['text' => mb_substr($text, 0, 300), 'where' => $where, 'status' => $status[$text]];
    }
    return $out;
}

/**
 * Scan source pages against every chosen language.
 *
 * @param array $pairs list of ['source' => url, 'lang' => code, 'target' => url]
 * @return array target url => ['lang', 'source', 'error', 'strings']
 */
function trwatch_scan_pairs(array $pairs, array $allow) {
    $urls = [];
    foreach ($pairs as $p) { $urls[$p['source']] = true; $urls[$p['target']] = true; }
    $pages = trwatch_fetch(array_keys($urls));

    $out = [];
    foreach ($pairs as $p) {
        $src = $pages[$p['source']];
        $tgt = $pages[$p['target']];
        $error = $tgt['error'] ?? null;
        if (!$error && $src['error']) {
            /* translators: %s: error message */
            $error = sprintf(__('Original page could not be fetched: %s', 'translation-watchdog-for-translatepress'), $src['error']);
        }
        $out[$p['target']] = [
            'lang'    => $p['lang'],
            'source'  => $p['source'],
            'error'   => $error,
            'strings' => $error ? [] : trwatch_compare($src['html'], $tgt['html'], $p['lang'], $allow),
        ];
    }
    return $out;
}

/** Source pages per batch, so each batch stays at about TRWATCH_BATCH requests. */
function trwatch_sources_per_batch($langCount) {
    return max(1, intdiv(TRWATCH_BATCH, 1 + max(1, $langCount)));
}

function trwatch_pairs_for(array $sources, array $langs) {
    $pairs = [];
    foreach ($sources as $s) foreach ($langs as $l) $pairs[] = ['source' => $s, 'lang' => $l, 'target' => trwatch_translated_url($s, $l)];
    return $pairs;
}
