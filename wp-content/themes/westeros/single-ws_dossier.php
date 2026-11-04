<?php defined('ABSPATH') || exit; get_header(); ?>
<div class="content-wrap">
<main class="content ws-main" aria-label="مسیر مطالعهٔ پرونده">
<?php while (have_posts()) : the_post(); $id=get_the_ID(); $protected=post_password_required(); $items=$protected?array():westeros_dossier_items($id); ?>
    <nav class="ws-document-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url(home_url('/')); ?>">وستروس</a><span aria-hidden="true">/</span><a href="<?php echo esc_url(get_post_type_archive_link('ws_dossier')); ?>">پرونده‌ها</a><span aria-hidden="true">/</span><span aria-current="page"><?php echo esc_html(get_the_title()); ?></span></nav>
    <article <?php post_class('ws-dossier'); ?>>
        <header class="ws-dossier-header"><span class="ws-dossier-kicker"><?php echo westeros_icon('book'); ?> پروندهٔ موضوعی</span><h1><?php echo esc_html(get_the_title()); ?></h1>
        <?php if (!$protected) : ?>
            <?php if (get_post_field('post_excerpt',$id)) : ?><div class="ws-dossier-description"><?php echo wp_kses_post(wpautop(get_post_field('post_excerpt',$id))); ?></div><?php endif; ?>
            <p class="ws-dossier-total"><?php echo esc_html(number_format_i18n(count($items))); ?> مطلب در این مسیر</p>
            <p class="ws-comments-spoiler-note">وضعیت اسپویل هر مطلب جداگانه مشخص شده است؛ عضویت در پرونده به معنی بدون اسپویل بودن نیست.</p>
        <?php endif; ?></header>
        <?php if ($protected) : echo get_the_password_form(); elseif ($items) : ?>
        <a class="ws-path-start" href="<?php echo esc_url(westeros_dossier_read_url($items[0]->ID,$id)); ?>">شروع از نخستین مطلب <?php echo westeros_icon('arrow'); ?></a>
        <ol class="ws-dossier-reading-list">
        <?php foreach ($items as $index=>$item) : ?>
            <li><span class="ws-dossier-step" aria-hidden="true"><?php echo esc_html(number_format_i18n($index+1)); ?></span><div><h2><a href="<?php echo esc_url(westeros_dossier_read_url($item->ID,$id)); ?>"><?php echo esc_html(get_the_title($item)); ?></a></h2><?php westeros_meta($item->ID); ?><p><?php westeros_excerpt($item->ID,22); ?></p></div></li>
        <?php endforeach; ?>
        </ol>
        <?php else : ?><p class="ws-dossier-empty">هنوز مطلبی در این پرونده قرار نگرفته است.</p><?php endif; ?>
        <?php edit_post_link('ویرایش پرونده','<p class="ws-reader-edit">','</p>'); ?>
    </article>
<?php endwhile; ?>
</main>
<?php get_sidebar(); get_footer(); ?>
