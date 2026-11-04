<?php
/** Feed-backed Radio Westeros; WordPress IDs remain the owners of comments. */
defined('ABSPATH') || exit;

function westeros_podcast_feed_url() { return 'https://feeds.acast.com/public/shows/radiowesteros1'; }
function westeros_podcast_category_id() { return 652; }
function westeros_podcast_url() { return get_category_link(westeros_podcast_category_id()); }
function westeros_podcast_cache() {
    $data=get_option('westeros_podcast_feed',array());
    return is_array($data) && isset($data['episodes']) && is_array($data['episodes']) ? $data : array('episodes'=>array());
}
function westeros_podcast_https_url($value) {
    $url=esc_url_raw(trim((string)$value),array('https'));
    $parts=wp_parse_url($url);
    return $url && !empty($parts['host']) && empty($parts['user']) && empty($parts['pass']) && (!isset($parts['port']) || $parts['port']===443) ? $url : '';
}
function westeros_parse_podcast_feed($xml) {
    if (!is_string($xml) || strlen($xml)>2*MB_IN_BYTES || preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) { return new WP_Error('ws_podcast_xml','ساختار فید معتبر نیست.'); }
    $previous=libxml_use_internal_errors(true);
    try { $rss=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA); }
    finally { libxml_clear_errors();libxml_use_internal_errors($previous); }
    if (!$rss || $rss->getName()!=='rss' || !isset($rss->channel) || count($rss->channel->item)<1 || count($rss->channel->item)>500) { return new WP_Error('ws_podcast_xml','فید خالی یا نامعتبر است.'); }
    $channel=$rss->channel;$acast=$channel->children('https://schema.acast.com/1.0/');$itunes=$channel->children('http://www.itunes.com/dtds/podcast-1.0.dtd');
    $show=(string)$acast->showId;
    if ($show!=='62ee070027916500170f2b70') { return new WP_Error('ws_podcast_show','شناسهٔ برنامه با رادیو وستروس مطابقت ندارد.'); }
    $cover=westeros_podcast_https_url((string)($itunes->image->attributes()['href']??''));
    $episodes=array();
    foreach ($channel->item as $item) {
        $a=$item->children('https://schema.acast.com/1.0/');$i=$item->children('http://www.itunes.com/dtds/podcast-1.0.dtd');
        $guid=trim((string)$item->guid);$title=sanitize_text_field((string)$item->title);$episode_id=(string)$a->episodeId;
        $audio=westeros_podcast_https_url((string)$item->enclosure['url']);$date=strtotime((string)$item->pubDate);
        if (!$guid || strlen($guid)>255 || !$title || !$audio || !$date || (string)$a->showId!==$show || !preg_match('/^[a-f0-9]{24}$/',$episode_id) || isset($episodes[$guid])) { return new WP_Error('ws_podcast_item','یک قسمت فید شناسه یا اطلاعات پخش معتبر ندارد.'); }
        $mime=sanitize_mime_type((string)$item->enclosure['type']);
        if (!str_starts_with($mime,'audio/')) { return new WP_Error('ws_podcast_audio','نوع فایل صوتی معتبر نیست.'); }
        $duration=trim((string)$i->duration);$seconds=0;
        if (preg_match('/^\d+(?::[0-5]\d){0,2}$/',$duration)) { foreach (explode(':',$duration) as $part) { $seconds=$seconds*60+(int)$part; } }
        $episodes[$guid]=array('guid'=>$guid,'title'=>$title,'description'=>wp_kses_post((string)$item->description),'published'=>$date,'date_gmt'=>gmdate('Y-m-d H:i:s',$date),'duration'=>$duration,'seconds'=>$seconds,'season'=>ctype_digit((string)$i->season)?(int)$i->season:null,'number'=>ctype_digit((string)$i->episode)?(int)$i->episode:null,'image'=>westeros_podcast_https_url((string)($i->image->attributes()['href']??'')) ?: $cover,'audio'=>$audio,'mime'=>$mime,'bytes'=>ctype_digit((string)$item->enclosure['length'])?(int)$item->enclosure['length']:0,'link'=>westeros_podcast_https_url((string)$item->link),'show_id'=>$show,'episode_id'=>$episode_id);
    }
    // Feed order is authoritative, including legacy irregular episode numbering.
    return array('title'=>sanitize_text_field((string)$channel->title),'description'=>wp_kses_post((string)$channel->description),'image'=>$cover,'show_id'=>$show,'episodes'=>$episodes);
}

/** Compare-and-delete prevents a worker from releasing a newer worker's lock. */
function westeros_podcast_release_lock($token) {
    global $wpdb;
    $deleted=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",'westeros_podcast_sync_lock',$token));
    if ($deleted) { wp_cache_delete('westeros_podcast_sync_lock','options'); }
}
function westeros_podcast_acquire_lock() {
    $old=get_option('westeros_podcast_sync_lock','');
    if (is_string($old) && $old && (int)$old<time()-120) { westeros_podcast_release_lock($old); }
    $token=time().'|'.wp_generate_uuid4();
    return add_option('westeros_podcast_sync_lock',$token,'',false) ? $token : false;
}

function westeros_podcast_find_post($guid, $legacy) {
    // Native metadata is the durable identity; titles/dates are never identity keys.
    $ids=get_posts(array('post_type'=>'post','post_status'=>array_keys(get_post_stati()),'fields'=>'ids','meta_key'=>'_ws_podcast_guid','meta_value'=>$guid,'meta_type'=>'BINARY','posts_per_page'=>2,'no_found_rows'=>true));
    if (count($ids)>1) { return new WP_Error('ws_podcast_conflict','شناسهٔ قسمت به چند نوشته متصل شده است.'); }
    if ($ids) { return (int)$ids[0]; }
    if (isset($legacy[$guid])) {
        $entry=$legacy[$guid];$post=get_post($entry['id']);
        if (!$post || $post->post_type!=='post' || $post->post_title!==$entry['title'] || !has_category(westeros_podcast_category_id(),$post)) { return new WP_Error('ws_podcast_legacy','نوشتهٔ قدیمی با نگاشت بررسی‌شده مطابقت ندارد.'); }
        return (int)$post->ID;
    }
    $old=westeros_podcast_cache()['episodes'][$guid]['post_id']??0;
    if ($old) { return new WP_Error('ws_podcast_missing','نوشتهٔ متصل به یک قسمت حذف شده است؛ اتصال آن را بررسی کنید.'); }
    $slug='radio-westeros-'.substr(hash('sha256',$guid),0,20);
    $post=get_page_by_path($slug,OBJECT,'post');
    if ($post) {
        // An interrupted import may have created the shell before its GUID metadata.
        if (get_post_meta($post->ID,'_ws_podcast_imported',true)==='1' && !get_post_meta($post->ID,'_ws_podcast_guid',false)) { return (int)$post->ID; }
        return new WP_Error('ws_podcast_slug','نشانی قسمت از قبل استفاده شده است.');
    }
    return 0;
}

function westeros_sync_podcast_data($data) {
    if (!is_array($data) || empty($data['episodes'])) { return new WP_Error('ws_podcast_empty','فید معتبر نیست.'); }
    $legacy=require __DIR__.'/podcast-legacy-map.php';$created=0;$mapped=array();
    // Resolve all existing identities before creating anything. A conflict keeps last-good data.
    foreach ($data['episodes'] as $guid=>$episode) {
        $id=westeros_podcast_find_post($guid,$legacy);
        if (is_wp_error($id)) { return $id; }
        $mapped[$guid]=$id;
    }
    foreach ($data['episodes'] as $guid=>&$episode) {
        $id=$mapped[$guid];
        if (!$id) {
            $id=wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_title'=>$episode['title'],'post_name'=>'radio-westeros-'.substr(hash('sha256',$guid),0,20),'post_content'=>$episode['description'],'post_date_gmt'=>$episode['date_gmt'],'post_date'=>get_date_from_gmt($episode['date_gmt']),'post_category'=>array(westeros_podcast_category_id()),'comment_status'=>'open','ping_status'=>'closed','meta_input'=>array('_ws_podcast_imported'=>'1','_ws_podcast_guid'=>$guid)),true);
            if (is_wp_error($id)) { return $id; }
            $created++;
        }
        $guids=get_post_meta($id,'_ws_podcast_guid',false);
        if (!in_array($guid,$guids,true)) { add_post_meta($id,'_ws_podcast_guid',$guid); }
        $episode['post_id']=(int)$id;
    }
    unset($episode);
    $data['fetched_at']=time();$data['source']=westeros_podcast_feed_url();
    update_option('westeros_podcast_feed',$data,false);
    update_option('westeros_podcast_feed_status',array('checked_at'=>time(),'error'=>'','created'=>$created),false);
    westeros_purge_theme_cache();
    return $data;
}

function westeros_refresh_podcast_feed($force=false) {
    $cache=westeros_podcast_cache();$status=(array)get_option('westeros_podcast_feed_status',array());
    if (!$force && max((int)($cache['fetched_at']??0)+HOUR_IN_SECONDS,(int)($status['retry_after']??0))>time()) { return $cache; }
    $lock=westeros_podcast_acquire_lock();
    if (!$lock) { return $cache; }
    try {
        $response=wp_safe_remote_get(westeros_podcast_feed_url(),array('timeout'=>10,'redirection'=>0,'limit_response_size'=>2*MB_IN_BYTES,'headers'=>array('Accept'=>'application/rss+xml, application/xml')));
        $data=is_wp_error($response) ? $response : (wp_remote_retrieve_response_code($response)===200 ? westeros_parse_podcast_feed(wp_remote_retrieve_body($response)) : new WP_Error('ws_podcast_http','دریافت فید موقتاً ممکن نیست.'));
        if (!is_wp_error($data)) { $data=westeros_sync_podcast_data($data); }
        if (is_wp_error($data)) {
            update_option('westeros_podcast_feed_status',array('checked_at'=>time(),'retry_after'=>time()+15*MINUTE_IN_SECONDS,'error'=>$data->get_error_message()),false);
            return $data;
        }
        return $data;
    } finally { westeros_podcast_release_lock($lock); }
}
add_action('westeros_podcast_refresh', 'westeros_refresh_podcast_feed');
add_action('init',function(){ if (!wp_next_scheduled('westeros_podcast_refresh')) { wp_schedule_event(time()+HOUR_IN_SECONDS,'hourly','westeros_podcast_refresh'); } });
add_action('template_redirect',function(){
    if (is_feed() || is_embed() || is_preview()) { return; }
    if (is_home() || is_category(westeros_podcast_category_id()) || is_singular('post') && has_category(westeros_podcast_category_id(),get_queried_object_id())) { westeros_refresh_podcast_feed(); }
}, 5);

function westeros_podcast_episodes() {
    $episodes=westeros_podcast_cache()['episodes'];$visible=array();
    if (!$episodes) { return $visible; }
    $ids=array_unique(array_map(static fn($e)=>(int)($e['post_id']??0),$episodes));
    $posts=get_posts(array('post_type'=>'post','post_status'=>'publish','has_password'=>false,'post__in'=>$ids,'posts_per_page'=>500,'no_found_rows'=>true));
    $public=array_fill_keys(array_map(static fn($p)=>(int)$p->ID,$posts),true);
    foreach ($episodes as $guid=>$episode) { if (isset($public[$episode['post_id']??0])) { $visible[$guid]=$episode; } }
    return $visible;
}
function westeros_post_podcast_episodes($id) {
    return array_values(array_filter(westeros_podcast_episodes(),static fn($e)=>(int)$e['post_id']===(int)$id));
}
function westeros_podcast_unique_episodes() {
    $seen=array();$episodes=array();
    foreach (westeros_podcast_episodes() as $episode) {
        if (isset($seen[$episode['post_id']])) { continue; }
        $seen[$episode['post_id']]=true;$episodes[]=$episode;
    }
    return $episodes;
}
add_filter('query_vars',function($vars){$vars[]='ws_podcast_season';return $vars;});
add_action('pre_get_posts',function($query){
    if (is_admin() || !$query->is_main_query() || !$query->is_category(westeros_podcast_category_id()) || $query->is_feed() || $query->is_embed() || (defined('REST_REQUEST') && REST_REQUEST)) {return;}
    $episodes=westeros_podcast_unique_episodes();
    if (!$episodes) {return;}
    $season=$query->get('ws_podcast_season');
    $season=is_scalar($season)?(string)$season:'';
    $known=array_filter(array_column($episodes,'season'),static fn($value)=>$value!==null);
    if ($season!=='' && in_array($season,array_map('strval',$known),true)) { $episodes=array_values(array_filter($episodes,static fn($e)=>(string)$e['season']===$season)); }
    $query->set('post__in',array_column($episodes,'post_id') ?: array(0));
    $query->set('posts_per_page',12);$query->set('orderby','post__in');$query->set('has_password',false);
},20);
function westeros_podcast_player($episode) {
    $src='https://embed.acast.com/'.$episode['show_id'].'/'.$episode['episode_id'];
    echo '<section class="ws-podcast-player" aria-label="'.esc_attr('پخش '.$episode['title']).'"><iframe src="'.esc_url($src).'" title="'.esc_attr('پلیر Acast: '.$episode['title']).'" loading="lazy" allow="autoplay" referrerpolicy="strict-origin-when-cross-origin"></iframe><details class="ws-podcast-fallback"><summary>پلیر باز نمی‌شود؟ پخش مستقیم فایل صوتی</summary><audio controls preload="none" src="'.esc_url($episode['audio']).'" aria-label="'.esc_attr($episode['title']).'"></audio><p><a href="'.esc_url($episode['audio']).'">باز کردن فایل صوتی</a></p><p class="ws-podcast-audio-error" hidden>دریافت فایل صوتی ممکن نشد؛ اتصال را بررسی کنید یا صفحهٔ Acast را باز کنید.</p></details><a class="ws-podcast-acast-link" href="'.esc_url($episode['link']).'" target="_blank" rel="noopener noreferrer">شنیدن این قسمت در Acast '.westeros_icon('arrow').'</a></section>';
}
function westeros_podcast_card($episode) {
    $url=get_permalink($episode['post_id']);
    echo '<article class="ws-podcast-card"><a class="ws-podcast-cover" href="'.esc_url($url).'" tabindex="-1" aria-hidden="true">';
    if ($episode['image']) { echo '<img src="'.esc_url($episode['image']).'" alt="" loading="lazy" decoding="async">'; }
    else { echo '<span class="ws-placeholder">'.westeros_icon('headphones').'</span>'; }
    echo '<span class="ws-play-mark">'.westeros_icon('play').'</span></a><div class="ws-podcast-copy"><p class="ws-podcast-kicker">رادیو وستروس';
    if ($episode['season']!==null) { echo ' · فصل '.esc_html(number_format_i18n($episode['season'])); }
    echo '</p><h3><a href="'.esc_url($url).'">'.esc_html($episode['title']).'</a></h3><div class="ws-podcast-meta"><time datetime="'.esc_attr(gmdate('c',$episode['published'])).'">'.esc_html(wp_date(get_option('date_format'),$episode['published'])).'</time>';
    if ($episode['duration']) { echo '<span>'.westeros_icon('headphones').'<bdi>'.esc_html($episode['duration']).'</bdi></span>'; }
    echo '<a href="'.esc_url($url.'#comments').'">'.esc_html(number_format_i18n((int)get_comments_number($episode['post_id']))).' دیدگاه</a></div><p class="ws-podcast-excerpt">'.esc_html(wp_trim_words(wp_strip_all_tags($episode['description']),26,'…')).'</p><a class="ws-more" href="'.esc_url($url).'">شنیدن قسمت '.westeros_icon('play').'</a></div></article>';
}
function westeros_home_podcast_section($block) {
    $episodes=westeros_podcast_unique_episodes();
    if (!$episodes) { return false; }
    $number=max(1,min(24,(int)($block['number']??5)));$offset=max(0,(int)($block['offset']??0));$episodes=array_slice($episodes,$offset,$number);
    echo '<section class="ws-section ws-podcast-home ws-shelf" data-shelf data-category="652" aria-label="رادیو وستروس">';
    westeros_section_heading('رادیو وستروس',westeros_podcast_url(),true,'headphones');
    echo '<p class="ws-section-note">از آرشیو رادیو وستروس؛ قسمت‌ها و پخش از Acast</p><div class="ws-podcast-grid ws-podcast-home-grid ws-shelf-track" tabindex="0" role="group" aria-label="قسمت‌های رادیو وستروس">';
    foreach ($episodes as $episode) { westeros_podcast_card($episode); } echo '</div></section>';
    return true;
}
function westeros_podcast_legacy_notes($content) {
    // Display the original editorial note without starting retired players or their scripts.
    $content=preg_replace('~<(script|style|iframe|audio)\b[^>]*>.*?</\1\s*>~is','',$content);
    $content=preg_replace('~https?://[^\s<>"\']+\.(?:mp3|ogg|m4a)(?:\?[^\s<>"\']*)?~i','',$content);
    return wp_kses_post(wpautop(do_shortcode($content)));
}
add_filter('template_include',function($template){
    if (is_category(westeros_podcast_category_id()) && !is_feed() && !is_embed()) { return get_stylesheet_directory().'/podcast-archive.php'; }
    if (is_singular('post') && !is_embed() && !is_feed() && !is_preview() && has_category(westeros_podcast_category_id(),get_queried_object_id()) && !post_password_required()) { return get_stylesheet_directory().'/podcast-single.php'; }
    return $template;
}, 40);
add_action('wp_enqueue_scripts',function(){
    if (is_home() || is_archive() || is_search() || is_singular('post') && has_category(westeros_podcast_category_id(),get_queried_object_id())) {
        wp_enqueue_style('westeros-podcast',get_stylesheet_directory_uri().'/assets/podcast.css',array('westeros-appearance'),(string)filemtime(__DIR__.'/../assets/podcast.css'));
        if (is_singular('post')) { wp_enqueue_script('westeros-podcast',get_stylesheet_directory_uri().'/assets/podcast.js',array(),(string)filemtime(__DIR__.'/../assets/podcast.js'),true); }
    }
}, 30);
if (is_admin()) { require_once __DIR__.'/podcast-admin.php'; }
