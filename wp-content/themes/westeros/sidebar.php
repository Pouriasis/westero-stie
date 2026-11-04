<?php
defined('ABSPATH') || exit;
?></div><!-- .content-wrap --><?php
if (is_singular() && get_post_meta(get_queried_object_id(), 'tie_sidebar_pos', true) === 'full') {
    return;
}
$sidebar = 'primary-widget-area';
$custom = '';
if (is_singular()) {
    $custom = get_post_meta(get_queried_object_id(), 'tie_sidebar_post', true);
    $custom = $custom ?: westeros_option(is_page() ? 'sidebar_page' : 'sidebar_post');
} elseif (is_category()) {
    $settings = westeros_category_settings(get_queried_object_id());
    $custom = $settings['cat_sidebar'] ?? '';
    $custom = $custom ?: westeros_option('sidebar_archive');
} else {
    $custom = westeros_option('sidebar_archive');
}
if ($custom && is_registered_sidebar(sanitize_title($custom))) {
    $sidebar = sanitize_title($custom);
}
?>
<aside class="sidebar" aria-label="ستون کناری">
    <?php dynamic_sidebar($sidebar); ?>
</aside>
