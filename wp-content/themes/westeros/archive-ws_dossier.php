<?php defined('ABSPATH') || exit; get_header(); ?>
<div class="content-wrap">
<main class="content ws-main" aria-label="پرونده‌های وستروس">
    <header class="ws-collection-header">
        <a class="ws-collection-home" href="<?php echo esc_url(home_url('/')); ?>">وستروس <span aria-hidden="true">/</span> کتابخانه</a>
        <div class="ws-collection-heading"><span class="ws-collection-emblem" aria-hidden="true"><?php echo westeros_icon('book'); ?></span><h1>پرونده‌های وستروس</h1></div>
        <p class="ws-dossier-intro">مجموعه‌های منتخب از آرشیو؛ از یک مطلب شروع کنید و مسیر یک موضوع را دنبال کنید.</p>
    </header>
    <?php if (have_posts()) : ?>
    <div class="ws-dossier-grid">
        <?php while (have_posts()) : the_post(); westeros_render_dossier_card(get_the_ID()); endwhile; ?>
    </div>
    <?php westeros_listing_pagination(); else : ?>
    <section class="ws-listing-empty"><h2>پرونده‌ای برای نمایش نیست</h2><p>تا آماده‌شدن مجموعه‌ها، روایت‌های وستروس را از دسته‌های سایت بخوانید.</p><a class="ws-more" href="<?php echo esc_url(home_url('/')); ?>">بازگشت به خانه <?php echo westeros_icon('arrow'); ?></a></section>
    <?php endif; ?>
</main>
<?php get_sidebar(); get_footer(); ?>
