<?php
/** Standalone Persian editorial theme for Westeros. */
defined('ABSPATH') || exit;
require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/platform.php';
require_once __DIR__ . '/inc/dates.php';
require_once __DIR__ . '/inc/media.php';
require_once __DIR__ . '/inc/category-settings.php';
require_once __DIR__ . '/inc/reader.php';
require_once __DIR__ . '/inc/collections.php';
require_once __DIR__ . '/inc/discovery.php';
require_once __DIR__ . '/inc/spoilers.php';
require_once __DIR__ . '/inc/search.php';
require_once __DIR__ . '/inc/assets.php';
require_once __DIR__ . '/inc/polish.php';
require_once __DIR__ . '/inc/legacy-downloads.php';
require_once __DIR__ . '/inc/downloads.php';
require_once __DIR__ . '/inc/podcast.php';
if (is_admin()) { require_once __DIR__ . '/inc/discovery-admin.php'; }

add_action('wp_enqueue_scripts', function () {
    $uri=get_stylesheet_directory_uri(); $dir=get_stylesheet_directory();
    wp_enqueue_style('westeros-foundation',$uri.'/assets/foundation.css',array(),(string)filemtime($dir.'/assets/foundation.css'));
    wp_enqueue_style('westeros',get_stylesheet_uri(),array('westeros-foundation'),(string)filemtime($dir.'/style.css'));
    wp_enqueue_style('westeros-library',$uri.'/assets/library.css',array('westeros'),(string)filemtime($dir.'/assets/library.css'));
    wp_enqueue_style('westeros-appearance',$uri.'/assets/appearance.css',array('westeros-library'),(string)filemtime($dir.'/assets/appearance.css'));
    if(is_archive()||is_search()||is_page()||is_singular('ws_dossier')||(is_home()&&(is_paged()||westeros_has_listing_filter()||westeros_option('on_home')!=='boxes'))){
        wp_enqueue_style('westeros-collections',$uri.'/assets/collections.css',array('westeros-library'),(string)filemtime($dir.'/assets/collections.css'));
    }
    wp_enqueue_script('westeros',$uri.'/assets/site.js',array(),(string)filemtime($dir.'/assets/site.js'),true);
    wp_enqueue_script('westeros-reader',$uri.'/assets/reader.js',array(),(string)filemtime($dir.'/assets/reader.js'),true);
    wp_enqueue_style('westeros-spoilers',$uri.'/assets/spoilers.css',array('westeros-appearance'),(string)filemtime($dir.'/assets/spoilers.css'));
    if(is_singular()&&comments_open()&&get_option('thread_comments'))wp_enqueue_script('comment-reply');
},20);

// Keep native controls and WordPress jQuery available before delayed plugin scripts.
add_filter('script_loader_tag',function($tag,$handle){
    if ($handle === 'westeros') {
        $fallback = "document.querySelector('.ws-nav')?.classList.add('has-failed');document.documentElement.classList.add('ws-site-script-failed')";
        $tag = str_replace('<script ', '<script onerror="' . esc_attr($fallback) . '" ', $tag);
    }
    return in_array($handle,array('westeros','westeros-reader','jquery-core','jquery-migrate'),true)?str_replace('<script ','<script data-no-optimize="1" data-no-defer="1" ',$tag):$tag;
},10,2);

add_filter('body_class', function ($classes) {
    $classes[] = 'ws-site';
    if (is_home() && !is_paged() && !westeros_has_listing_filter() && westeros_option('on_home') === 'boxes') {
        $classes[] = 'ws-home';
    }
    if (is_singular() && get_post_meta(get_queried_object_id(), 'tie_sidebar_pos', true) === 'full') {
        $classes[] = 'ws-full-width';
    }
    return $classes;
});

function westeros_icon($name) {
    $paths = array(
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
        'left' => '<path d="m14 5-7 7 7 7"/>',
        'right' => '<path d="m10 5 7 7-7 7"/>',
        'arrow' => '<path d="M20 12H4m6-6-6 6 6 6"/>',
        'up' => '<path d="m6 15 6-6 6 6"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M8 3v4m8-4v4"/>',
        'user' => '<circle cx="12" cy="8" r="3"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/>',
        'play' => '<path d="m8 5 11 7-11 7Z"/>',
        'book' => '<path d="M3 4h6a4 4 0 0 1 3 1 4 4 0 0 1 3-1h6v15h-6a4 4 0 0 0-3 1 4 4 0 0 0-3-1H3Zm9 1v15"/>',
        'rss' => '<path d="M5 11a8 8 0 0 1 8 8M5 5a14 14 0 0 1 14 14"/><circle cx="5" cy="19" r="1.5"/>',
        'bookmark' => '<path d="M6 3h12v18l-6-4-6 4Z"/>',
        'moon' => '<path d="M21 13a9 9 0 0 1-10-10 9 9 0 1 0 10 10Z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="M12 7v10M8 12h8"/>',
        'quill' => '<path d="M4 21 18 4c3-3 5-1 3 3L10 19l-6 2Zm5-5 1 3m3-8 1 4M4 21l-1 1"/>',
        'headphones' => '<path d="M4 14v-3a8 8 0 0 1 16 0v3"/><rect x="3" y="12" width="4" height="8" rx="2"/><rect x="17" y="12" width="4" height="8" rx="2"/>',
        'crown' => '<path d="m3 7 4 3 5-6 5 6 4-3-2 11H5Zm2 14h14"/>',
    );
    $path = $paths[$name] ?? $paths['book'];
    return '<svg class="ws-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

// Omit the forum link from the header navigation on desktop and mobile.
add_filter('wp_nav_menu_objects', function ($items, $args) {
    if (($args->theme_location ?? '') !== 'primary') { return $items; }
    return array_values(array_filter($items, function ($item) {
        return trim((string) wp_parse_url($item->url, PHP_URL_PATH), '/') !== 'forums';
    }));
}, 10, 2);

/** Respect the existing primary menu and use native, keyboard-operable disclosure buttons. */
class Westeros_Menu_Walker extends Walker_Nav_Menu {
    private $submenu_ids = array();

    public function start_lvl(&$output, $depth = 0, $args = null) {
        $start = strlen($output);
        parent::start_lvl($output, $depth, $args);
        $output = substr($output, 0, $start) . preg_replace('/<ul\b/', '<ul id="' . esc_attr($this->submenu_ids[$depth]) . '"', substr($output, $start), 1);
    }

    public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0) {
        parent::start_el($output, $data_object, $depth, $args, $current_object_id);
        if (in_array('menu-item-has-children', (array) $data_object->classes, true)) {
            $this->submenu_ids[$depth] = wp_unique_id('ws-submenu-');
            $output .= '<button class="ws-submenu-toggle" type="button" aria-expanded="false" aria-controls="' . esc_attr($this->submenu_ids[$depth]) . '" aria-label="' . esc_attr('باز و بسته کردن زیرمنوی ' . wp_strip_all_tags($data_object->title)) . '">' . westeros_icon('down') . '</button>';
        }
    }
}

function westeros_menu_fallback() {
    echo '<ul class="ws-menu" id="ws-menu"><li><a href="' . esc_url(home_url('/')) . '">خانه</a></li>';
    wp_list_pages(array('title_li' => '', 'depth' => 1));
    echo '</ul>';
}

function westeros_meta($id, $author = true) {
    echo '<div class="ws-meta">';
    if ($author) {
        echo '<span class="ws-meta-author">' . westeros_icon('user') . esc_html(get_the_author_meta('display_name', (int) get_post_field('post_author', $id))) . '</span>';
    }
    echo '<time datetime="' . esc_attr(get_the_date(DATE_W3C, $id)) . '">' . westeros_icon('calendar') . esc_html(westeros_post_date($id)) . '</time></div>';
    // The reader has a full notice; previews all share this explicit badge.
    if (!(is_singular('post') && (int)get_queried_object_id() === (int)$id)) { westeros_spoiler_badge($id); }
}

function westeros_image($id, $size = 'slider', $hero = false, $context = 'card') {
    if (has_post_thumbnail($id)) {
        $attributes = array('class' => $hero ? 'ws-image skip-lazy' : 'ws-image', 'loading' => $hero ? 'eager' : 'lazy', 'decoding' => 'async');
        $attributes['sizes'] = westeros_image_sizes($hero ? 'hero' : ($size === 'tie-small' ? 'small' : $context));
        if ($hero) {
            $attributes['fetchpriority'] = 'high';
            $attributes['data-no-lazy'] = '1';
        }
        echo get_the_post_thumbnail($id, $size, $attributes); // Escaped by WordPress.
    } else {
        echo '<span class="ws-placeholder" aria-hidden="true">' . westeros_icon('book') . '</span>';
    }
}

function westeros_excerpt($id, $words = 28) {
    $post = get_post($id);
    if (!$post || post_password_required($post)) {
        return;
    }
    // Do not execute embeds, downloads or interactive shortcodes in homepage previews.
    $text = $post->post_excerpt ?: $post->post_content;
    $text = preg_replace('/<!--.*?-->/s', '', $text);
    $text = wp_strip_all_tags(strip_shortcodes($text));
    echo esc_html(wp_trim_words($text, $words, '…'));
}

function westeros_category_query($block) {
    $args = array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => max(1, min(24, (int) ($block['number'] ?? 5))), 'ignore_sticky_posts' => true, 'no_found_rows' => true, 'offset' => max(0, (int) ($block['offset'] ?? 0)));
    if (!empty($block['id'])) {
        $args['cat'] = (int) $block['id'];
    }
    if (($block['order'] ?? '') === 'rand') {
        $args['orderby'] = 'rand';
    }
    return new WP_Query($args);
}

function westeros_section_heading($title, $url, $shelf = false, $icon = 'book') {
    $title = str_replace(array('پادکست ها', 'مصاحبه ها'), array('پادکست‌ها', 'مصاحبه‌ها'), $title);
    echo '<div class="ws-section-heading"><h2><span class="ws-section-seal" aria-hidden="true">' . westeros_icon($icon) . '</span><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></h2><div class="ws-section-tools">';
    if ($shelf) {
        echo '<button type="button" class="ws-control ws-js-control" data-shelf-prev aria-label="موارد قبلی">' . westeros_icon('right') . '</button><button type="button" class="ws-control ws-js-control" data-shelf-next aria-label="موارد بعدی">' . westeros_icon('left') . '</button>';
    }
    echo '<a class="ws-archive-link" href="' . esc_url($url) . '">مشاهدهٔ همه ' . westeros_icon('arrow') . '</a></div></div>';
}

function westeros_card($id, $podcast = false) {
    echo '<article class="ws-card"><a class="ws-card-image" href="' . esc_url(get_permalink($id)) . '" tabindex="-1" aria-hidden="true">';
    westeros_image($id, 'slider', false, 'shelf');
    if ($podcast) {
        echo '<span class="ws-play-mark" aria-hidden="true">' . westeros_icon('play') . '</span>';
    }
    echo '</a><h3><a href="' . esc_url(get_permalink($id)) . '">' . esc_html(get_the_title($id)) . '</a></h3>';
    westeros_meta($id, false);
    echo '</article>';
}

function westeros_story_row($id) {
    echo '<li class="ws-story-row"><a class="ws-card-image" href="' . esc_url(get_permalink($id)) . '" tabindex="-1" aria-hidden="true">';
    westeros_image($id, 'tie-small');
    echo '</a><div><h3><a href="' . esc_url(get_permalink($id)) . '">' . esc_html(get_the_title($id)) . '</a></h3>';
    westeros_meta($id, false);
    echo '</div></li>';
}

/** Render configured homepage blocks; the stored order and category IDs stay authoritative. */
function westeros_home_section($block) {
    if ((int)($block['id'] ?? 0) === westeros_podcast_category_id() && westeros_home_podcast_section($block)) { return; }
    $term = get_category((int) ($block['id'] ?? 0));
    if (!$term || is_wp_error($term)) {
        return;
    }
    $query = westeros_category_query($block);
    if (!$query->posts) {
        return;
    }
    $title = !empty($block['title']) ? $block['title'] : $term->name;
    $url = get_category_link($term);
    $shelf = ($block['type'] ?? '') === 's';
    $wide = ($block['style'] ?? '') === '1c';
    $podcast = (int) $term->term_id === 652;
    echo '<section class="ws-section' . ($shelf ? ' ws-shelf' : '') . '" data-category="' . esc_attr($term->term_id) . '"' . ($shelf ? ' data-shelf' : '') . ' aria-label="' . esc_attr($title) . '">';
    $icon = $podcast ? 'headphones' : ($shelf ? 'quill' : ($wide ? 'book' : 'shield'));
    westeros_section_heading($title, $url, $shelf, $icon);
    if ($podcast) {
        echo '<p class="ws-section-note">از آرشیو رادیو وستروس؛ همراهِ روایت‌ها و گفت‌وگوها</p>';
    }
    if ($shelf) {
        echo '<div class="ws-shelf-track" tabindex="0" role="group" aria-label="' . esc_attr($title) . '">';
        foreach ($query->posts as $item) {
            westeros_card($item->ID, $podcast);
        }
        echo '</div>';
    } else {
        $lead = array_shift($query->posts);
        echo '<article class="ws-card' . ($wide ? ' ws-article-lead' : '') . '"><a class="ws-card-image" href="' . esc_url(get_permalink($lead)) . '" tabindex="-1" aria-hidden="true">';
        westeros_image($lead->ID);
        echo '</a><div><h3><a href="' . esc_url(get_permalink($lead)) . '">' . esc_html(get_the_title($lead)) . '</a></h3>';
        westeros_meta($lead->ID);
        echo '<p>'; westeros_excerpt($lead->ID, $wide ? 34 : 26); echo '</p><a class="ws-more" href="' . esc_url(get_permalink($lead)) . '">ادامهٔ مطلب ' . westeros_icon('arrow') . '</a></div></article>';
        echo '<ul class="ws-story-list' . ($wide ? ' ws-article-grid' : '') . '">';
        foreach ($query->posts as $item) {
            westeros_story_row($item->ID);
        }
        echo '</ul>';
    }
    echo '</section>';
}

function westeros_home_group($blocks) {
    $pair_open = false;
    $pair_count = 0;
    $blocks = array_values(array_filter((array) $blocks, 'is_array'));
    foreach ($blocks as $block_index => $block) {
        if (!is_array($block)) {
            continue;
        }
        $paired = ($block['type'] ?? '') === 'n' && ($block['style'] ?? '') === '2c';
        if (!$paired && $pair_open) {
            echo '</div>';
            $pair_open = false;
        }
        if ($paired && !$pair_open) {
            // Share the header, lead and short-story tracks across adjacent panels.
            $pair_posts = max(1, min(24, (int) ($block['number'] ?? 5)));
            $next = $blocks[$block_index + 1] ?? array();
            if (($next['type'] ?? '') === 'n' && ($next['style'] ?? '') === '2c') {
                $pair_posts = max($pair_posts, max(1, min(24, (int) ($next['number'] ?? 5))));
            }
            echo '<div class="ws-paired-sections" style="--ws-pair-rows:' . (int) max(3, $pair_posts + 1) . '">';
            $pair_open = true;
            $pair_count = 0;
        }
        westeros_home_section($block);
        if ($paired && ++$pair_count === 2) {
            echo '</div>';
            $pair_open = false;
        }
    }
    if ($pair_open) {
        echo '</div>';
    }
}

function westeros_featured_posts() {
    $args = array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => max(1, min(10, (int) (westeros_option('slider_number') ?: 5))), 'ignore_sticky_posts' => true, 'no_found_rows' => true);
    $type = westeros_option('slider_query');
    if ($type === 'category') {
        $args['category__in'] = array_filter(array_map('absint', (array) westeros_option('slider_cat')));
    } elseif ($type === 'post' || $type === 'page') {
        $ids = wp_parse_id_list(westeros_option($type === 'post' ? 'slider_posts' : 'slider_pages'));
        if (!$ids) { return array(); }
        $args['post_type'] = $type;
        $args['post__in'] = $ids;
    } elseif ($type === 'tag') {
        $tags = array_filter(array_map('trim', explode(',', (string) westeros_option('slider_tag'))));
        $slugs = array();
        foreach ($tags as $tag) {
            $term = get_term_by('name', $tag, 'post_tag');
            if ($term) { $slugs[] = $term->slug; }
        }
        if (!$slugs) { return array(); }
        $args['tag_slug__in'] = $slugs;
    }
    return (new WP_Query($args))->posts;
}
