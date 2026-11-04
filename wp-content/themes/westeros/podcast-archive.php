<?php
defined('ABSPATH') || exit;
get_header();
$feed=westeros_podcast_cache();$episodes=westeros_podcast_unique_episodes();
$seasons=array();foreach ($episodes as $episode) { if ($episode['season']!==null) { $seasons[$episode['season']]=true; } }
krsort($seasons);
$season=get_query_var('ws_podcast_season','');$season=is_scalar($season)?(string)$season:'';
$filtered=$season!=='' && isset($seasons[$season]) ? array_values(array_filter($episodes,static fn($e)=>(string)$e['season']===$season)) : $episodes;
$page=max(1,(int)get_query_var('paged'));$per_page=12;$pages=max(1,(int)ceil(count($filtered)/$per_page));
$items=array_slice($filtered,($page-1)*$per_page,$per_page);
?>
<div class="content-wrap"><main class="content ws-main ws-podcast-archive" aria-label="قسمت‌های رادیو وستروس">
    <header class="ws-podcast-intro">
        <?php if (!empty($feed['image'])) { westeros_podcast_image($feed['image'], array('width' => 180, 'height' => 180, 'alt' => 'رادیو وستروس')); } ?>
        <div><p class="ws-podcast-kicker">پادکست وستروس</p><h1><?php echo esc_html($feed['title']??'رادیو وستروس'); ?></h1><p>همراه روایت‌ها و گفت‌وگوهای جهان نغمهٔ یخ و آتش</p><p class="ws-podcast-meta"><?php echo esc_html(number_format_i18n(count($episodes))); ?> قسمت در آرشیو پادکست</p><a class="ws-more" href="<?php echo esc_url(westeros_podcast_feed_url()); ?>"><?php echo westeros_icon('rss'); ?> دنبال‌کردن با فید RSS</a></div>
    </header>
    <?php if ($seasons) : ?><form class="ws-podcast-filter" method="get" action="<?php echo esc_url(westeros_podcast_url()); ?>"><label for="ws-podcast-season">فصل ثبت‌شده در فید</label><select id="ws-podcast-season" name="ws_podcast_season"><option value="">همهٔ قسمت‌ها</option><?php foreach ($seasons as $key=>$unused) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($season,(string)$key); ?>>فصل <?php echo esc_html(number_format_i18n($key)); ?></option><?php endforeach; ?></select><button type="submit">نمایش</button><p>برخی قسمت‌های قدیمی در فید، شمارهٔ فصل ندارند؛ آن‌ها را در «همهٔ قسمت‌ها» ببینید.</p></form><?php endif; ?>
    <?php if ($items) : ?><div class="ws-podcast-grid"><?php foreach ($items as $episode) { westeros_podcast_card($episode); } ?></div>
    <?php else : ?><p class="ws-podcast-empty">در این صفحه قسمتی پیدا نشد. <a href="<?php echo esc_url(westeros_podcast_url()); ?>">همهٔ قسمت‌ها</a></p><?php endif; ?>
    <?php if ($pages>1) { echo '<nav class="ws-listing-pagination" aria-label="صفحه‌های پادکست">'.paginate_links(array('current'=>$page,'total'=>$pages,'type'=>'list','prev_text'=>'قسمت‌های جدیدتر','next_text'=>'قسمت‌های قدیمی‌تر','add_args'=>$season!==''?array('ws_podcast_season'=>$season):array())).'</nav>'; } ?>
    <?php
    $mapped=array_map(static fn($e)=>(int)$e['post_id'],$episodes);
    $legacy=get_posts(array('post_type'=>'post','post_status'=>'publish','has_password'=>false,'category'=>westeros_podcast_category_id(),'post__not_in'=>$mapped,'posts_per_page'=>100,'no_found_rows'=>true));
    if ($page===1 && $season==='' && $legacy) : ?>
    <section class="ws-podcast-legacy"><h2>از آرشیو سایت</h2><p>این قسمت‌ها در فید فعلی موجود نیستند؛ صفحه و دیدگاه‌های آن‌ها در سایت حفظ شده است.</p><ul><?php foreach ($legacy as $old) : ?><li><a href="<?php echo esc_url(get_permalink($old)); ?>"><?php echo esc_html(get_the_title($old)); ?></a></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
</main><?php get_sidebar();get_footer(); ?>
