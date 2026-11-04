<?php
/** Persian matching for the public main search, without rewriting stored content. */
defined('ABSPATH') || exit;

function westeros_normalize_search_text($text) {
    $text = strtr($text, array('ي'=>'ی', 'ك'=>'ک', "\u{200C}"=>' ', "\u{00A0}"=>' '));
    // This established site title also appears as تاج‌وتخت, with و joined to تخت.
    return str_replace('تاج وتخت', 'تاج و تخت', $text);
}

/** Keep internal state off public query variables and release it with the query. */
function westeros_search_contexts() {
    static $contexts;
    if (!$contexts) { $contexts = new WeakMap(); }
    return $contexts;
}

add_action('pre_get_posts', function ($query) {
    $contexts = westeros_search_contexts();
    unset($contexts[$query]);
    if (is_admin() || !$query->is_main_query() || !$query->is_search() || $query->is_feed() || $query->is_embed()
        || $query->get('suppress_filters') || (defined('REST_REQUEST') && REST_REQUEST)) { return; }
    $text = $query->get('s');
    if (!is_string($text) || !preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{00A0}]/u', $text)) { return; }
    $normalized = westeros_normalize_search_text($text);
    $contexts[$query] = array(
        // Mirror core's removal of request slashes/newlines when restoring the display value.
        'original'=>str_replace(array("\r", "\n"), '', stripslashes($text)),
        'blank'=>trim($normalized) === '',
        'columns'=>array('post_title', 'post_excerpt', 'post_content'),
    );
    // Core still owns tokenization, quoted phrases, exclusions, stopwords and term limits.
    // Restore s after SQL construction so forms, pagination and templates retain the user's text.
    $query->set('s', $normalized);
}, 20);

add_filter('post_search_columns', function ($columns, $text, $query) {
    $contexts = westeros_search_contexts();
    if (isset($contexts[$query])) {
        $context = $contexts[$query];
        $allowed = array('post_title', 'post_excerpt', 'post_content');
        $context['columns'] = array_values(array_intersect((array)$columns, $allowed)) ?: $allowed;
        $contexts[$query] = $context;
    }
    return $columns;
}, PHP_INT_MAX, 3);

/** Identifiers are fixed; values are always prepared, including LIKE wildcards. */
function westeros_search_column($column) {
    global $wpdb;
    if (!in_array($column, array('post_title','post_excerpt','post_content'), true)) { return ''; }
    $expression = $wpdb->prepare(
        "REPLACE(REPLACE(REPLACE(REPLACE({$wpdb->posts}.{$column}, %s, %s), %s, %s), %s, %s), %s, %s)",
        'ي', 'ی', 'ك', 'ک', "\u{200C}", ' ', "\u{00A0}", ' '
    );
    return $wpdb->prepare("REPLACE({$expression}, %s, %s)", 'تاج وتخت', 'تاج و تخت');
}

add_filter('posts_search', function ($search, $query) {
    global $wpdb;
    $contexts = westeros_search_contexts();
    if (!isset($contexts[$query])) { return $search; }
    $context = $contexts[$query];
    if ($context['blank']) { return ' AND 1=0 '; }
    $parts = array();
    $wildcard = $query->get('exact') ? '' : '%';
    $prefix = apply_filters('wp_query_search_exclusion_prefix', '-');
    foreach ((array)$query->get('search_terms') as $term) {
        $exclude = $prefix && str_starts_with($term, $prefix);
        if ($exclude) { $term = substr($term, strlen($prefix)); }
        $like = $wildcard . $wpdb->esc_like($term) . $wildcard;
        $columns = array();
        foreach ($context['columns'] as $column) {
            $columns[] = $wpdb->prepare(westeros_search_column($column) . ($exclude ? ' NOT LIKE %s' : ' LIKE %s'), $like);
        }
        $parts[] = '(' . implode($exclude ? ' AND ' : ' OR ', $columns) . ')';
    }
    if (!$parts) { return $search; }
    $search = ' AND (' . implode(' AND ', $parts) . ') ';
    if (!is_user_logged_in()) { $search .= " AND ({$wpdb->posts}.post_password = '') "; }
    // Status, taxonomy, author and other access constraints remain owned by WP_Query.
    return $search;
}, 10, 2);

add_filter('posts_search_orderby', function ($orderby, $query) {
    global $wpdb;
    if (!isset(westeros_search_contexts()[$query])) { return $orderby; }
    $prefix = apply_filters('wp_query_search_exclusion_prefix', '-');
    $titles = array();
    foreach ((array)$query->get('search_terms') as $term) {
        if ($prefix && str_starts_with($term, $prefix)) { continue; }
        $titles[] = $wpdb->prepare(westeros_search_column('post_title') . ' LIKE %s', '%' . $wpdb->esc_like($term) . '%');
    }
    if (!$titles) { return ''; }
    if ((int)$query->get('search_terms_count') <= 1) { return $titles[0] . ' DESC'; }
    $cases = array();
    $text = $query->get('s');
    $has_exclusion = $prefix && preg_match('/(?:\s|^)' . preg_quote($prefix, '/') . '/u', $text);
    $like = '%' . $wpdb->esc_like($text) . '%';
    if (!$has_exclusion) { $cases[] = $wpdb->prepare('WHEN ' . westeros_search_column('post_title') . ' LIKE %s THEN 1', $like); }
    if (count($titles) < 7) {
        $cases[] = 'WHEN ' . implode(' AND ', $titles) . ' THEN 2';
        if (count($titles) > 1) { $cases[] = 'WHEN ' . implode(' OR ', $titles) . ' THEN 3'; }
    }
    if (!$has_exclusion) {
        foreach (array('post_excerpt'=>4, 'post_content'=>5) as $column=>$rank) {
            $cases[] = $wpdb->prepare('WHEN ' . westeros_search_column($column) . ' LIKE %s THEN ' . $rank, $like);
        }
    }
    return '(CASE ' . implode(' ', $cases) . ' ELSE 6 END)';
}, 10, 2);

add_filter('posts_orderby', function ($orderby, $query) {
    global $wpdb;
    if (isset(westeros_search_contexts()[$query]) && (!$query->get('orderby') || $query->get('orderby') === 'relevance')) {
        $orderby .= ($orderby ? ', ' : '') . "{$wpdb->posts}.ID DESC";
    }
    return $orderby;
}, 20, 2);

add_filter('posts_request', function ($request, $query) {
    $contexts = westeros_search_contexts();
    if (isset($contexts[$query])) {
        $query->set('s', $contexts[$query]['original']);
        unset($contexts[$query]);
    }
    return $request;
}, PHP_INT_MAX, 2);
