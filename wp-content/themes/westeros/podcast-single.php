<?php
defined('ABSPATH') || exit;
get_header();
?>
<div class="content-wrap"><main class="content ws-main" aria-label="شنیدن رادیو وستروس">
<?php while (have_posts()) : the_post();
    $id=get_the_ID();$episodes=westeros_post_podcast_episodes($id);$episode=$episodes[0]??array('title'=>get_the_title(),'image'=>'','duration'=>'','published'=>(int)get_post_time('U',true),'season'=>null);westeros_record_view($id);
    $ordered=westeros_podcast_unique_episodes();$position=array_search($id,array_column($ordered,'post_id'),true);
?>
    <nav class="ws-reader-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url(home_url('/')); ?>">وستروس</a><span>/</span><a href="<?php echo esc_url(westeros_podcast_url()); ?>">رادیو وستروس</a></nav>
    <article id="the-post" <?php post_class('ws-reader ws-podcast-episode'); ?> data-reader>
        <header class="ws-podcast-episode-header">
            <?php if ($episode['image']) : ?><img class="ws-podcast-artwork" src="<?php echo esc_url($episode['image']); ?>" width="260" height="260" alt="" decoding="async"><?php endif; ?>
            <div><p class="ws-podcast-kicker">رادیو وستروس<?php if ($episode['season']!==null) { echo ' · فصل '.esc_html(number_format_i18n($episode['season'])); } ?></p><h1><?php echo esc_html($episode['title']); ?></h1><div class="ws-podcast-meta"><time datetime="<?php echo esc_attr(gmdate('c',$episode['published'])); ?>"><?php echo esc_html(wp_date(get_option('date_format'),$episode['published'])); ?></time><?php if ($episode['duration']) : ?><span><?php echo westeros_icon('headphones'); ?><bdi><?php echo esc_html($episode['duration']); ?></bdi></span><?php endif; ?><a href="#comments"><?php echo esc_html(number_format_i18n(get_comments_number())); ?> دیدگاه</a></div></div>
        </header>
        <div class="ws-reading-toolbar ws-js-control" role="group" aria-label="ابزارهای این قسمت"><button class="ws-tool-button" type="button" data-save-post data-post-id="<?php the_ID(); ?>" data-post-title="<?php echo esc_attr($episode['title']); ?>" data-post-url="<?php echo esc_url(get_permalink()); ?>" aria-pressed="false"><?php echo westeros_icon('bookmark'); ?><span data-save-label>نشان کردن</span></button></div><p class="ws-sr-only" data-reader-status role="status"></p>
        <?php if ($episodes) : ?>
        <?php westeros_podcast_player($episode); ?>
        <section class="entry ws-podcast-description" aria-labelledby="ws-podcast-description-title"><h2 id="ws-podcast-description-title">دربارهٔ این قسمت</h2><?php echo wp_kses_post(wpautop($episode['description'])); ?></section>
        <?php else : ?><p class="ws-podcast-empty">این قسمت در فید فعلی موجود نیست و پلیر Acast برای آن در دسترس نیست. یادداشت انتشار و دیدگاه‌های قبلی در همین صفحه حفظ شده‌اند.</p><div class="entry ws-podcast-description"><?php echo westeros_podcast_legacy_notes(get_the_content()); ?></div><?php endif; ?>
        <?php if (count($episodes)>1) : ?><details class="ws-podcast-alternates"><summary>ثبت دیگر این قسمت در فید</summary><p>این عنوان بیش از یک بار در فید ثبت شده است؛ هر دو به همین صفحه و دیدگاه‌ها متصل‌اند.</p><?php foreach (array_slice($episodes,1) as $alternate) { westeros_podcast_player($alternate); } ?></details><?php endif; ?>
        <?php if ($episodes && get_post_meta($id,'_ws_podcast_imported',true)!=='1' && trim(get_the_content())!=='') : ?><details class="ws-podcast-original"><summary>یادداشت انتشار در سایت</summary><div class="entry"><?php echo westeros_podcast_legacy_notes(get_the_content()); ?></div></details><?php endif; ?>
        <?php edit_post_link('ویرایش نوشتهٔ این قسمت','<p class="ws-reader-edit">','</p>'); ?>
    </article>
    <?php if ($position!==false) : ?><nav class="ws-podcast-neighbours" aria-label="قسمت‌های دیگر پادکست"><?php foreach (array(-1=>'قسمت جدیدتر',1=>'قسمت قدیمی‌تر') as $offset=>$label) : if (!isset($ordered[$position+$offset])) {continue;} $other=$ordered[$position+$offset]; ?><a href="<?php echo esc_url(get_permalink($other['post_id'])); ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($other['title']); ?></strong></a><?php endforeach; ?></nav><?php endif; ?>
    <section class="ws-reader-comments" aria-label="دیدگاه‌های این قسمت"><?php comments_template(); ?></section>
<?php endwhile; ?>
</main><?php get_sidebar();get_footer(); ?>
