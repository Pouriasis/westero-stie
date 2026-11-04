<?php defined('ABSPATH') || exit; get_header(); ?>
<div class="content-wrap">
    <main class="content" aria-label="صفحه پیدا نشد">
        <article class="ws-empty">
            <span class="ws-empty-number" aria-hidden="true">۴۰۴</span>
            <h1 class="post-title">این روایت را پیدا نکردیم.</h1>
            <p>ممکن است نشانی صفحه تغییر کرده باشد. عنوان مطلب را جست‌وجو کنید یا به صفحهٔ اصلی برگردید.</p>
            <?php get_search_form(); ?>
            <a class="ws-more" href="<?php echo esc_url(home_url('/')); ?>">بازگشت به صفحهٔ اصلی <?php echo westeros_icon('arrow'); ?></a>
        </article>
    </main>
<?php get_sidebar(); ?>
<?php get_footer(); ?>
