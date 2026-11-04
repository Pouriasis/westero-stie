<?php
defined('ABSPATH') || exit;
$is_magazine = is_home() && !is_paged() && !westeros_has_listing_filter() && westeros_option('on_home') === 'boxes';
get_header();
if ($is_magazine && westeros_option('slider')) {
    ob_start();
    get_template_part('template-parts/featured');
    $lead = ob_get_clean();
    if (trim($lead) !== '') {
        echo '<div class="ws-home-lead">' . $lead . '</div>';
    }
}
?>
<div class="content-wrap">
    <main class="content ws-main" aria-label="مطالب وستروس">
        <?php if ($is_magazine) : ?>
            <?php if (westeros_spoiler_filter_enabled()) { westeros_content_filters(); } ?>
            <?php westeros_home_group(westeros_home_sections()); westeros_home_dossiers(); ?>
        <?php else : ?>
            <?php get_template_part('template-parts/collection-header'); ?>
            <?php westeros_listing(); ?>
        <?php endif; ?>
    </main>
<?php get_sidebar(is_home() && !is_paged() ? 'home1' : null); ?>
<?php get_footer(); ?>
