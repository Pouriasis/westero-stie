<?php defined('ABSPATH') || exit; get_header(); ?>
<div class="content-wrap">
    <main class="content ws-main ws-collection" aria-label="آرشیو مطالب">
        <?php get_template_part('template-parts/collection-header'); ?>
        <?php if (is_category()) : $settings = westeros_category_settings(get_queried_object_id()); if (!empty($settings['cat_slider'])) : ?>
            <div class="ws-collection-legacy-slider"><?php get_template_part('includes/slider-category'); ?></div>
        <?php endif; endif; ?>
        <?php westeros_listing(); ?>
    </main>
<?php get_sidebar(); ?>
<?php get_footer(); ?>
