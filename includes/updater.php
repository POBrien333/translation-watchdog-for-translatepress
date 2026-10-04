<?php
/**
 * Updates from GitHub releases.
 *
 * Inactive until the plugin header has an "Update URI: https://github.com/<owner>/<repo>" line.
 * WordPress then asks this filter (named after the URI's host) instead of wordpress.org.
 * The release must have the plugin zip attached — the release workflow does that.
 *
 * Remove this file for a wordpress.org submission: directory plugins may not update themselves.
 */

if (!defined('ABSPATH')) exit;

add_filter('update_plugins_github.com', function ($update, $plugin_data, $plugin_file) {
    if ($plugin_file !== plugin_basename(TRWATCH_FILE)) return $update;
    if (!preg_match('~^https://github\.com/([\w.-]+/[\w.-]+?)/?$~', (string) ($plugin_data['UpdateURI'] ?? ''), $m)) return $update;

    $release = get_transient('trwatch_release');
    if ($release === false) {
        $release = [];
        $res = wp_remote_get('https://api.github.com/repos/' . $m[1] . '/releases/latest', [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/vnd.github+json'],
        ]);
        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
            $json = json_decode(wp_remote_retrieve_body($res), true);
            foreach ((array) ($json['assets'] ?? []) as $asset) {
                if (str_ends_with((string) ($asset['name'] ?? ''), '.zip')) {
                    $release = [
                        'version' => ltrim((string) $json['tag_name'], 'v'),
                        'url'     => (string) $json['html_url'],
                        'package' => (string) $asset['browser_download_url'],
                    ];
                    break;
                }
            }
        }
        // cache misses too, so a GitHub outage doesn't slow down every admin page
        set_transient('trwatch_release', $release, 6 * HOUR_IN_SECONDS);
    }
    if (!$release) return $update;

    return [
        'slug'    => TRWATCH_SLUG,
        'version' => $release['version'],
        'url'     => $release['url'],
        'package' => $release['package'],
    ];
}, 10, 3);

// forget the cached release after an update, so the next check sees the new state
add_action('upgrader_process_complete', function () {
    delete_transient('trwatch_release');
});
