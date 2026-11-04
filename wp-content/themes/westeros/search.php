<?php defined('ABSPATH') || exit; get_header(); ?>
<div class="content-wrap">
    <main class="content ws-main ws-collection" aria-label="جست‌وجوی مطالب">
        <?php get_template_part('template-parts/collection-header'); ?>
        <?php westeros_listing(); ?>
    </main>
<?php get_sidebar(); ?>
<?php get_footer(); ?>
