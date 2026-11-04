<?php
defined('ABSPATH') || exit;
get_header();
?>
<div class="content-wrap">
<main class="content ws-main" aria-label="مطالعهٔ روایت">
<?php while (have_posts()) : the_post();
    global $get_meta;
    $get_meta = get_post_custom(get_the_ID());
    westeros_record_view(get_the_ID());
    $protected = post_password_required();
    $filtered = apply_filters('the_content', get_the_content());
    $outline = $protected ? array('html'=>$filtered, 'headings'=>array()) : westeros_content_outline($filtered);
    $review_position = $get_meta['tie_review_position'][0] ?? '';
?>
    <nav class="ws-reader-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url(home_url('/')); ?>">وستروس</a><span aria-hidden="true">/</span><span><?php echo esc_html(get_the_title()); ?></span></nav>
    <?php westeros_reader_banner('above', get_the_ID()); ?>
    <article id="the-post" <?php post_class('ws-reader'); ?> data-reader>
        <header class="ws-reader-header">
            <?php westeros_post_kicker(get_the_ID()); ?>
            <?php if (!$protected) { westeros_spoiler_notice(get_the_ID()); } ?>
            <h1 class="ws-reader-title"><?php the_title(); ?></h1>
            <?php if (has_excerpt() && !$protected) : ?><p class="ws-reader-deck"><?php echo esc_html(wp_strip_all_tags(get_the_excerpt())); ?></p><?php endif; ?>
            <div class="ws-reader-byline">
                <?php westeros_meta(get_the_ID()); ?>
                <?php if (!$protected) : ?><span class="ws-reading-duration"><?php echo westeros_icon('clock'); ?><?php echo esc_html(number_format_i18n(westeros_reading_minutes(get_the_content()))); ?> دقیقه مطالعه</span><?php endif; ?>
            </div>
            <?php if (get_the_modified_time('U') > get_the_time('U') + DAY_IN_SECONDS) : ?>
                <p class="ws-reader-updated">آخرین به‌روزرسانی: <time datetime="<?php echo esc_attr(get_the_modified_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_modified_date()); ?></time></p>
            <?php endif; ?>
        </header>
        <div class="ws-reading-toolbar ws-js-control" role="group" aria-label="ابزارهای مطالعه">
            <span class="ws-reading-toolbar-label"><?php echo westeros_icon('book'); ?> همراهِ این روایت</span>
            <button class="ws-tool-button" type="button" data-reading-theme aria-pressed="false"><?php echo westeros_icon('moon'); ?><span data-theme-label>حالت شب</span></button>
            <button class="ws-tool-button" type="button" data-save-post data-post-id="<?php the_ID(); ?>" data-post-title="<?php echo esc_attr(get_the_title()); ?>" data-post-url="<?php echo esc_url(get_permalink()); ?>" aria-pressed="false"><?php echo westeros_icon('bookmark'); ?><span data-save-label>نشان کردن</span></button>
        </div>
        <p class="ws-sr-only" data-reader-status role="status" aria-live="polite"></p>
        <?php if (!$protected) { westeros_reader_media(get_the_ID()); } ?>
        <?php if (count($outline['headings']) >= 2) : ?>
        <details class="ws-toc">
            <summary><?php echo westeros_icon('book'); ?> در این صفحه <span><?php echo esc_html(number_format_i18n(count($outline['headings']))); ?> بخش</span><?php echo westeros_icon('down'); ?></summary>
            <nav aria-label="فهرست بخش‌های این صفحه"><ol>
                <?php foreach ($outline['headings'] as $heading) : ?>
                <li class="ws-toc-level-<?php echo esc_attr($heading['level']); ?>"><a href="#<?php echo esc_attr(rawurlencode($heading['id'])); ?>"><?php echo esc_html($heading['title']); ?></a></li>
                <?php endforeach; ?>
            </ol></nav>
        </details>
        <?php endif; ?>
        <div class="entry ws-article-content" data-reading-content>
            <?php if (!$protected && in_array($review_position, array('top','both'), true) && function_exists('westeros_review')) { westeros_review('review-top'); } ?>
            <?php echo $outline['html']; // WordPress-filtered article HTML, including existing plugin content. ?>
            <?php wp_link_pages(array('before'=>'<nav class="ws-article-pages" aria-label="صفحه‌های مطلب">صفحه‌ها: ', 'after'=>'</nav>')); ?>
            <?php if (!$protected && in_array($review_position, array('bottom','both'), true) && function_exists('westeros_review')) { westeros_review('review-bottom'); } ?>
        </div>
        <?php if (!$protected && westeros_option('post_tags')) { the_tags('<div class="ws-reader-tags"><span>در این روایت:</span>', '', '</div>'); } ?>
        <?php if (!$protected && ((westeros_option('share_post') && empty($get_meta['tie_hide_share'][0])) || ($get_meta['tie_hide_share'][0] ?? '') === 'no')) { get_template_part('includes/post-share'); } ?>
        <?php edit_post_link('ویرایش مطلب', '<p class="ws-reader-edit">', '</p>'); ?>
    </article>
    <?php westeros_reader_path(get_the_ID()); ?>
    <?php westeros_reader_banner('below', get_the_ID()); ?>
    <div class="ws-reading-progress ws-js-control" role="progressbar" aria-label="پیشرفت مطالعه" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-reading-progress><span></span></div>
    <?php if (westeros_option('post_nav')) { the_post_navigation(array('prev_text'=>'<span>روایت قبلی</span><strong>%title</strong>','next_text'=>'<span>روایت بعدی</span><strong>%title</strong>')); } ?>
    <?php if (!$protected && ((westeros_option('post_authorbio') && empty($get_meta['tie_hide_author'][0])) || ($get_meta['tie_hide_author'][0] ?? '') === 'no') && get_the_author_meta('description')) : ?>
    <section class="ws-reader-author" aria-label="دربارهٔ نویسنده"><?php echo get_avatar(get_the_author_meta('ID'), 56); ?><div><h2><?php echo esc_html(get_the_author()); ?></h2><p><?php echo wp_kses_post(get_the_author_meta('description')); ?></p></div></section>
    <?php endif; ?>
    <?php westeros_reader_related(get_the_ID()); ?>
    <section class="ws-reader-comments" aria-label="دیدگاه‌های این مطلب"><?php comments_template(); ?></section>
<?php endwhile; ?>
</main>
<?php get_sidebar(); ?>
<?php get_footer(); ?>
