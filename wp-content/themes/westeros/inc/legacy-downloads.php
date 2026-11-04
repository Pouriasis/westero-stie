<?php
/** Exact legacy aliases for two files already present in Download Monitor. */
defined('ABSPATH') || exit;

function westeros_legacy_download_map() {
    return array(9734=>13091, 9735=>13001);
}
function westeros_legacy_downloads($attributes = array(), $content = '', $tag = '') {
    if (!shortcode_exists('download')) { return ''; }
    $map = westeros_legacy_download_map();
    $attributes = is_array($attributes) ? $attributes : array();
    $ids = array_keys($map);
    if ($tag === 'wpdm_button_template') {
        $id = absint($attributes['id'] ?? 0);
        $ids = isset($map[$id]) ? array($id) : array();
    } elseif ($tag === 'wpdm_category' && ($attributes['id'] ?? '') !== 'books-persian') {
        return '';
    } elseif (!empty($attributes['categories']) && !in_array('books-persian', array_filter(explode(',', $attributes['categories'])), true)) {
        return '';
    }
    $links = array();
    foreach ($ids as $old) {
        $post = get_post($map[$old]);
        // Always delegate the link and download access check to the current plugin.
        if (!$post || $post->post_type !== 'dlm_download' || $post->post_status !== 'publish' || post_password_required($post)) { continue; }
        $links[] = '<li>' . do_shortcode('[download id="' . (int)$post->ID . '"]') . '</li>';
    }
    return $links ? '<ul class="ws-legacy-downloads">' . implode('', $links) . '</ul>' : '';
}
add_action('init', function () {
    foreach (array('wpdm_tree','wpdm_category','wpdm-all-packages','wpdm_button_template') as $tag) {
        if (!shortcode_exists($tag)) { add_shortcode($tag, 'westeros_legacy_downloads'); }
    }
}, 40);
