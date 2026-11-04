<?php defined('ABSPATH') || exit; get_header(); ?>
<div class="content-wrap">
    <main class="content ws-main ws-page-main" aria-label="محتوای صفحه">
        <?php while (have_posts()) : the_post();
            global $get_meta;
            $get_meta = get_post_custom(get_the_ID());
            $protected = post_password_required();
            $review_position = $get_meta['tie_review_position'][0] ?? '';
            westeros_page_breadcrumbs(get_the_ID());
            westeros_reader_banner('above', get_the_ID());
        ?>
        <article id="page-<?php the_ID(); ?>" <?php post_class('ws-document'); ?>>
            <header class="ws-document-header">
                <h1><?php echo esc_html(get_the_title()); ?></h1>
                <?php if (has_excerpt() && !$protected) : ?><p><?php echo esc_html(wp_strip_all_tags(get_the_excerpt())); ?></p><?php endif; ?>
            </header>
            <?php if (!$protected) { westeros_reader_media(get_the_ID()); } ?>
            <div class="entry ws-document-content">
                <?php if (!$protected && in_array($review_position, array('top','both'), true) && function_exists('westeros_review')) { westeros_review('review-top'); } ?>
                <?php the_content(); ?>
                <?php wp_link_pages(array('before'=>'<nav class="ws-document-pages" aria-label="صفحه‌های برگه">صفحه‌ها: ', 'after'=>'</nav>')); ?>
                <?php if (!$protected && in_array($review_position, array('bottom','both'), true) && function_exists('westeros_review')) { westeros_review('review-bottom'); } ?>
            </div>
            <?php edit_post_link('ویرایش برگه', '<p class="ws-document-edit">', '</p>'); ?>
        </article>
        <?php westeros_reader_banner('below', get_the_ID()); ?>
        <?php if ((comments_open() || get_comments_number()) && (!function_exists('bp_current_component') || !bp_current_component())) : ?><section class="ws-reader-comments" aria-label="دیدگاه‌های برگه"><?php comments_template(); ?></section><?php endif; ?>
        <?php endwhile; ?>
    </main>
<?php get_sidebar(); ?>
<?php get_footer(); ?>
