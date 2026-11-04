<?php
/** Collection/page presentation. The WordPress main query remains authoritative. */
defined('ABSPATH') || exit;

function westeros_collection_context() {
    $context = array('title'=>'روایت‌های وستروس', 'eyebrow'=>'از کتابخانهٔ وستروس', 'description'=>'', 'icon'=>'book', 'feed'=>'', 'author'=>0);
    if (is_search()) {
        $query = trim(get_search_query(false));
        $context['title'] = $query !== '' ? 'نتیجهٔ جست‌وجو برای «' . $query . '»' : 'جست‌وجو در وستروس';
        $context['eyebrow'] = 'در میان روایت‌ها';
        $context['icon'] = 'search';
    } elseif (is_category() || is_tag() || is_tax()) {
        $context['title'] = single_term_title('', false);
        $context['eyebrow'] = is_tag() ? 'روایت‌هایی با این برچسب' : 'آرشیو موضوعی';
        $context['icon'] = is_category() ? 'shield' : 'quill';
        if (!is_category() || westeros_option('category_desc')) { $context['description'] = term_description(); }
        $term = get_queried_object();
        if ($term instanceof WP_Term && ((is_category() && westeros_option('category_rss')) || (is_tag() && westeros_option('tag_rss')))) {
            $context['feed'] = get_term_feed_link($term->term_id, $term->taxonomy);
        }
    } elseif (is_author()) {
        $author = get_queried_object();
        if ($author instanceof WP_User) {
            $context['title'] = 'روایت‌های ' . $author->display_name;
            $context['eyebrow'] = 'از قلم نویسنده';
            $context['icon'] = 'quill';
            if (westeros_option('author_bio')) {
                $context['description'] = wpautop(get_the_author_meta('description', $author->ID));
                $context['author'] = $author->ID;
            }
            if (westeros_option('author_rss')) { $context['feed'] = get_author_feed_link($author->ID); }
        }
    } elseif (is_archive()) {
        $context['title'] = wp_strip_all_tags(get_the_archive_title());
        $context['eyebrow'] = is_date() ? 'از آرشیو روایت‌ها' : 'در کتابخانهٔ وستروس';
        $context['description'] = get_the_archive_description();
    }
    return $context;
}

function westeros_collection_count($query = null) {
    $query = $query ?: $GLOBALS['wp_query'];
    return number_format_i18n((int) $query->found_posts) . (is_search() ? ' نتیجه' : ' مطلب');
}

function westeros_collection_terms() {
    if (!is_category()) { return; }
    $current = get_queried_object();
    if (!$current instanceof WP_Term) { return; }
    $children = get_terms(array('taxonomy'=>'category','parent'=>$current->term_id,'hide_empty'=>true,'number'=>10));
    if (is_wp_error($children)) { return; }
    $links = array();
    if ($children) {
        $links[] = $current;
        $links = array_merge($links, $children);
    } elseif ($current->parent) {
        $parent = get_term($current->parent, 'category');
        if ($parent && !is_wp_error($parent)) { $links[] = $parent; }
        $siblings = get_terms(array('taxonomy'=>'category','parent'=>$current->parent,'hide_empty'=>true,'number'=>10,'exclude'=>array($current->term_id)));
        $links[] = $current;
        if (!is_wp_error($siblings)) { $links = array_merge($links, $siblings); }
    }
    if (count($links) < 2) { return; }
    echo '<nav class="ws-topic-links" aria-label="دسته‌های مرتبط">';
    foreach ($links as $term) {
        $url = get_term_link($term);
        if (is_wp_error($url)) { continue; }
        $active = (int) $term->term_id === (int) $current->term_id;
        echo '<a href="' . esc_url($url) . '"' . ($active ? ' aria-current="page"' : '') . '>' . esc_html($term->name) . '</a>';
    }
    echo '</nav>';
}

function westeros_author_public_links($author_id) {
    $providers = array('url'=>'وب‌سایت','twitter'=>'توییتر','facebook'=>'فیسبوک','google'=>'گوگل','linkedin'=>'لینکدین','flickr'=>'فلیکر','youtube'=>'یوتیوب','instagram'=>'اینستاگرام','behance'=>'بیهنس','pinterest'=>'پینترست');
    $links = array();
    foreach ($providers as $field => $label) {
        $value = trim((string) get_the_author_meta($field, $author_id));
        if (!$value) { continue; }
        if ($field === 'twitter' && !preg_match('~^https?://~i', $value)) { $value = 'https://twitter.com/' . rawurlencode(ltrim($value, '@')); }
        $url = esc_url($value, array('http','https'));
        if ($url) { $links[] = '<a href="' . $url . '" rel="me">' . esc_html($label) . '</a>'; }
    }
    if ($links) { echo '<nav class="ws-author-public-links" aria-label="پیوندهای عمومی نویسنده">' . implode('', $links) . '</nav>'; }
}

function westeros_listing_pagination($query = null) {
    $query = $query ?: $GLOBALS['wp_query'];
    if ((int) $query->max_num_pages < 2) { return; }
    $filter_args = array();
    if (westeros_selected_content_kind()) { $filter_args['ws_kind'] = westeros_selected_content_kind(); }
    if (westeros_selected_spoiler_filter()) { $filter_args['ws_spoiler'] = westeros_selected_spoiler_filter(); }
    $links = paginate_links(array('current'=>max(1, (int) get_query_var('paged')), 'total'=>(int) $query->max_num_pages, 'type'=>'array', 'add_args'=>$filter_args, 'mid_size'=>1, 'end_size'=>1, 'prev_text'=>'صفحهٔ قبل', 'next_text'=>'صفحهٔ بعد', 'before_page_number'=>'<span class="ws-sr-only">صفحهٔ </span>', 'aria_current'=>'page'));
    if (!$links) { return; }
    echo '<nav class="ws-listing-pagination" aria-label="صفحه‌های مطالب"><ul>';
    foreach ($links as $link) { echo '<li>' . $link . '</li>'; } // WordPress-escaped pagination markup.
    echo '</ul></nav>';
}

function westeros_listing() {
    $has_search_text = !is_search() || trim(get_search_query(false)) !== '';
    if ($has_search_text && have_posts()) {
        echo '<div class="ws-listing-grid">';
        while (have_posts()) { the_post(); get_template_part('template-parts/listing-card'); }
        echo '</div>';
        westeros_listing_pagination();
    } else {
        get_template_part('template-parts/listing-empty');
    }
}

function westeros_page_breadcrumbs($id) {
    echo '<nav class="ws-document-breadcrumb" aria-label="مسیر صفحه"><a href="' . esc_url(home_url('/')) . '">وستروس</a>';
    foreach (array_reverse(get_post_ancestors($id)) as $ancestor) {
        // Do not expose titles of unpublished/private parent pages to other visitors.
        if (get_post_status($ancestor) !== 'publish' && !current_user_can('read_post', $ancestor)) { continue; }
        echo '<span aria-hidden="true">/</span><a href="' . esc_url(get_permalink($ancestor)) . '">' . esc_html(get_the_title($ancestor)) . '</a>';
    }
    echo '<span aria-hidden="true">/</span><span aria-current="page">' . esc_html(get_the_title($id)) . '</span></nav>';
}
