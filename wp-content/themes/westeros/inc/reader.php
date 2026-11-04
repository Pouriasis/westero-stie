<?php
/** Reading tools and editorial metadata. All content stays in WordPress. */
defined('ABSPATH') || exit;

function westeros_reading_minutes($content) {
    $text = wp_strip_all_tags(strip_shortcodes($content));
    $count = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}\x{200c}]*/u', $text);
    return max(1, (int) ceil(($count ?: 0) / 200));
}

/** Add stable heading anchors without changing headings inside code/comments. */
function westeros_content_outline($html) {
    $headings = array();
    $used_ids = array();
    // Reserve every existing ID, including IDs on non-heading elements.
    $processor = new WP_HTML_Tag_Processor($html);
    while ($processor->next_tag()) {
        $id = $processor->get_attribute('id');
        if (is_string($id) && $id !== '') { $used_ids[$id] = true; }
    }
    // Disclosure headings must not reveal hidden spoiler text through the public outline.
    $disclosure_depth = 0;
    $parts = preg_split('/(<!--.*?-->|<(?:script|style|pre|code|textarea)\b[^>]*>.*?<\/(?:script|style|pre|code|textarea)>|<\/?details\b[^>]*>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $part_index => $part) {
        if ($part_index % 2) {
            if (preg_match('/^<details\b/i', $part)) { $disclosure_depth++; }
            elseif (preg_match('/^<\/details\b/i', $part)) { $disclosure_depth = max(0, $disclosure_depth - 1); }
            continue;
        }
        if ($disclosure_depth) { continue; }
        $parts[$part_index] = preg_replace_callback('/<h([23])\b[^>]*>.*?<\/h\1>/is', function ($match) use (&$headings, &$used_ids) {
            $text = trim(html_entity_decode(wp_strip_all_tags($match[0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text === '') { return $match[0]; }
            $heading = new WP_HTML_Tag_Processor($match[0]);
            if (!$heading->next_tag()) { return $match[0]; }
            $id = $heading->get_attribute('id');
            if (!is_string($id) || $id === '') {
                $base = 'ws-section-' . substr(hash('sha256', $text), 0, 10);
                $id = $base;
                $suffix = 2;
                while (isset($used_ids[$id])) { $id = $base . '-' . $suffix++; }
                $heading->set_attribute('id', $id);
                $used_ids[$id] = true;
            }
            $headings[] = array('id' => $id, 'title' => $text, 'level' => (int) $match[1]);
            return $heading->get_updated_html();
        }, $part);
    }
    return array('html' => implode('', $parts), 'headings' => $headings);
}

function westeros_post_kicker($id) {
    $categories = get_the_category($id);
    if (!$categories) { return; }
    echo '<div class="ws-reader-categories">';
    foreach (array_slice($categories, 0, 3) as $term) {
        echo '<a href="' . esc_url(get_category_link($term)) . '">' . esc_html($term->name) . '</a>';
    }
    echo '</div>';
}

function westeros_spoiler_labels() {
    return array('' => 'تعیین نشده', 'none' => 'بدون اسپویل', 'series' => 'شامل رویدادهای سریال', 'books' => 'شامل رویدادهای کتاب‌ها', 'both' => 'شامل رویدادهای کتاب‌ها و سریال');
}

add_action('add_meta_boxes_post', function () {
    add_meta_box('westeros-editorial', 'اطلاعات تحریریهٔ وستروس', function ($post) {
        wp_nonce_field('westeros_editorial', 'westeros_editorial_nonce');
        $level = get_post_meta($post->ID, '_westeros_spoiler_level', true);
        $scope = get_post_meta($post->ID, '_westeros_spoiler_scope', true);
        echo '<p><label for="ws-spoiler-level">سطح اسپویل</label></p><select name="westeros_spoiler_level" id="ws-spoiler-level" class="widefat">';
        foreach (westeros_spoiler_labels() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($level, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p><label for="ws-spoiler-scope">محدودهٔ رویدادها</label></p><input class="widefat" id="ws-spoiler-scope" name="westeros_spoiler_scope" maxlength="160" value="' . esc_attr($scope) . '" placeholder="مثلاً تا پایان فصل دوم"><p class="description">اگر سطح تعیین نشود، برچسبی روی مطلب نمایش داده نمی‌شود.</p>';
    }, 'post', 'side');
});

function westeros_save_spoiler_editorial($id) {
    $nonce = $_POST['westeros_editorial_nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'westeros_editorial')) { return; }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($id) || !current_user_can('edit_post', $id)) { return; }
    $level = westeros_clean_spoiler_level(wp_unslash($_POST['westeros_spoiler_level'] ?? ''));
    $raw = $_POST['westeros_spoiler_scope'] ?? '';
    $scope = is_string($raw) ? sanitize_text_field(wp_unslash($raw)) : '';
    update_post_meta($id, '_westeros_spoiler_level', $level);
    update_post_meta($id, '_westeros_spoiler_scope', $level && $level !== 'none' ? mb_substr($scope, 0, 160) : '');
}
add_action('save_post_post', 'westeros_save_spoiler_editorial');

function westeros_spoiler_notice($id) {
    $data = westeros_spoiler_data($id);
    $level = $data['level']; $scope = $data['scope'];
    if ($level === '') { return; }
    echo '<aside class="ws-spoiler-notice' . ($level === 'none' ? ' ws-spoiler-safe' : '') . '" aria-label="وضعیت اسپویل">' . westeros_icon('shield') . '<div><strong>اسپویل: ' . esc_html($data['label']) . '</strong>';
    if ($scope) { echo '<span>' . esc_html($scope) . '</span>'; }
    echo '</div></aside>';
}

add_shortcode('westeros_spoiler', function ($attributes, $content = '') {
    $attributes = shortcode_atts(array('title' => 'نمایش بخش دارای اسپویل'), $attributes, 'westeros_spoiler');
    return '<details class="ws-spoiler"><summary>' . esc_html($attributes['title']) . '</summary><div class="ws-spoiler-body">' . do_shortcode($content ?? '') . '</div></details>';
});

/** Compose the article introduction without rewriting its stored content. */
function westeros_reader_intro($id, $html) {
    $intro = array('html' => $html, 'cover' => '', 'caption' => '', 'media' => '');
    if (post_password_required($id)) { return $intro; }
    $kind = get_post_meta($id, 'tie_post_head', true);
    $image_kind = !$kind || in_array($kind, array('thumb', 'lightbox'), true);
    // Only lift a standalone opening image; never remove surrounding article text.
    $image = '(?:<a\b[^>]*>\s*)?<img\b[^>]*>\s*(?:</a>\s*)?';
    $pattern = '~\A\s*(?:<!--.*?-->\s*)*(?<image><p\b[^>]*>\s*' . $image . '</p>|<figure\b[^>]*>\s*' . $image . '(?:<figcaption\b[^>]*>.*?</figcaption>\s*)?</figure>|<div\b[^>]*class=["\'][^"\']*\bwp-caption\b[^"\']*["\'][^>]*>\s*' . $image . '(?:<p\b[^>]*>.*?</p>\s*)?</div>)\s*~is';
    if ($image_kind && preg_match($pattern, $html, $match)) {
        $image_html = new WP_HTML_Tag_Processor($match['image']);
        if ($image_html->next_tag('IMG')) {
            $image_html->set_attribute('loading', 'eager');
            $image_html->set_attribute('fetchpriority', 'high');
            $image_html->set_attribute('data-no-lazy', '1');
            // This image now occupies the reading cover rather than the article body.
            $image_html->set_attribute('sizes', westeros_image_sizes('hero'));
        }
        $intro['cover'] = $image_html->get_updated_html();
        $intro['html'] = substr($html, strlen($match[0]));
    } else {
        ob_start();
        westeros_reader_media($id);
        $intro[$image_kind ? 'cover' : 'media'] = ob_get_clean();
    }
    // Keep image credits outside the title overlay, including legacy caption markup.
    $caption_pattern = '~<(?:figcaption\b[^>]*|p\b[^>]*class=["\'][^"\']*\bwp-caption-text\b[^"\']*["\'][^>]*)>(.*?)</(?:figcaption|p)>~is';
    if ($intro['cover'] && preg_match($caption_pattern, $intro['cover'], $caption, PREG_OFFSET_CAPTURE)) {
        $intro['caption'] = $caption[1][0];
        $intro['cover'] = substr_replace($intro['cover'], '', $caption[0][1], strlen($caption[0][0]));
    }
    return $intro;
}

/** Render existing media formats and captioned featured images. */
function westeros_reader_media($id) {
    $kind = get_post_meta($id, 'tie_post_head', true);
    if ($kind === 'none') { return; }
    if ($kind && !in_array($kind, array('thumb', 'lightbox'), true)) {
        westeros_special_post_media($id, $kind);
        return;
    }
    if ((!$kind && !westeros_option('post_featured')) || !has_post_thumbnail($id) || post_password_required($id)) { return; }
    echo '<figure class="ws-reader-media">';
    if ($kind === 'lightbox') { echo '<a href="' . esc_url(wp_get_attachment_image_url(get_post_thumbnail_id($id), 'full')) . '" aria-label="نمایش تصویر در اندازهٔ اصلی">'; }
    westeros_image($id, 'full', true);
    if ($kind === 'lightbox') { echo '</a>'; }
    $caption = wp_get_attachment_caption(get_post_thumbnail_id($id));
    if ($caption) { echo '<figcaption>' . wp_kses_post($caption) . '</figcaption>'; }
    echo '</figure>';
}

function westeros_reader_banner($position, $id) {
    if (!in_array($position, array('above', 'below'), true) || get_post_meta($id, 'tie_hide_' . $position, true)) { return; }
    $code = get_post_meta($id, 'tie_banner_' . $position, true);
    if ($code) {
        // Existing administrator-managed banner HTML.
        echo '<div class="ws-reader-banner">' . htmlspecialchars_decode($code) . '</div>';
    } elseif (function_exists('westeros_banner')) {
        westeros_banner('banner_' . $position, '<div class="ws-reader-banner">', '</div>');
    }
}

function westeros_reader_related($id) {
    $hidden = get_post_meta($id, 'tie_hide_related', true);
    if (($hidden && $hidden !== 'no') || (!westeros_option('related') && $hidden !== 'no') || post_password_required($id)) { return; }
    $tags = wp_get_post_tags($id, array('fields' => 'ids'));
    $args = array('post_type' => 'post', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 3, 'post__not_in' => array($id), 'ignore_sticky_posts' => true, 'no_found_rows' => true);
    if ($tags) { $args['tag__in'] = $tags; }
    else { $args['category__in'] = wp_get_post_categories($id); }
    $related = new WP_Query($args);
    if (!$related->posts) { return; }
    echo '<section class="ws-section ws-reader-related" aria-labelledby="ws-related-title"><div class="ws-section-heading"><h2 id="ws-related-title"><span class="ws-section-seal" aria-hidden="true">' . westeros_icon('book') . '</span>روایت‌های مرتبط</h2></div><div class="ws-related-grid">';
    foreach ($related->posts as $item) { westeros_card($item->ID); }
    echo '</div></section>';
}
