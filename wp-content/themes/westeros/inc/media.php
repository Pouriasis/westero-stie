<?php
defined('ABSPATH') || exit;

function westeros_embed_media($url,$title='رسانه'){
    $url=esc_url_raw($url,array('http','https'));if(!$url)return'';$host=strtolower((string)wp_parse_url($url,PHP_URL_HOST));$path=(string)wp_parse_url($url,PHP_URL_PATH);$src='';
    if(in_array($host,array('youtube.com','www.youtube.com','m.youtube.com','youtu.be','www.youtu.be'),true)){
        parse_str((string)wp_parse_url($url,PHP_URL_QUERY),$query);$id=str_contains($host,'youtu.be')?trim($path,'/'):($query['v']??'');if(!$id&&preg_match('~/embed/([A-Za-z0-9_-]+)~',$path,$m))$id=$m[1];if(preg_match('/^[A-Za-z0-9_-]{6,20}$/',$id))$src='https://www.youtube.com/embed/'.rawurlencode($id);
    }elseif(in_array($host,array('vimeo.com','www.vimeo.com'),true)&&preg_match('~/(\d+)~',$path,$m))$src='https://player.vimeo.com/video/'.$m[1];
    elseif(in_array($host,array('dailymotion.com','www.dailymotion.com'),true)&&preg_match('~/video/([A-Za-z0-9]+)~',$path,$m))$src='https://www.dailymotion.com/embed/video/'.$m[1];
    elseif(in_array($host,array('soundcloud.com','www.soundcloud.com'),true))$src=add_query_arg(array('url'=>$url,'auto_play'=>'false'),'https://w.soundcloud.com/player/');
    elseif(in_array($host,array('maps.google.com','maps.google.co.uk','www.google.com','google.com'),true)&&($host==='maps.google.com'||str_contains($path,'maps')))$src=add_query_arg('output','embed',$url);
    if($src)return'<div class="ws-native-embed"><iframe src="'.esc_url($src).'" title="'.esc_attr($title).'" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
    $embed=wp_oembed_get($url);return$embed?:'<p><a href="'.esc_url($url).'">'.esc_html($title).'</a></p>';
}

function westeros_special_post_media($id,$kind){
    if(post_password_required($id))return;
    if($kind==='audio'){
        $attrs=array();foreach(array('tie_audio_mp3'=>'mp3','tie_audio_m4a'=>'m4a','tie_audio_oga'=>'ogg')as$key=>$format){$url=esc_url_raw(get_post_meta($id,$key,true));if($url)$attrs[$format]=$url;}
        if(has_post_thumbnail($id))echo'<figure class="ws-reader-media">'.get_the_post_thumbnail($id,'full',array('loading'=>'eager')).'</figure>';
        if($attrs)echo wp_audio_shortcode($attrs);
    }elseif($kind==='video'){
        $url=get_post_meta($id,'tie_video_url',true);$code=get_post_meta($id,'tie_embed_code',true);
        if($url)echo westeros_embed_media($url,get_the_title($id));elseif($code)echo'<div class="ws-native-embed">'.htmlspecialchars_decode($code).'</div>';
    }elseif($kind==='soundcloud'){echo westeros_embed_media(get_post_meta($id,'tie_audio_soundcloud',true),get_the_title($id));}
    elseif($kind==='map'){echo westeros_embed_media(get_post_meta($id,'tie_googlemap_url',true),'نقشهٔ مرتبط');}
    elseif($kind==='slider'){westeros_render_slides(westeros_slider_slides((int)get_post_meta($id,'tie_post_slider',true)),'تصاویر مطلب');}
}

function westeros_slider_slides($id){
    $slides=array();
    if(metadata_exists('post',$id,'_westeros_slide_ids')){
        foreach(wp_parse_id_list(get_post_meta($id,'_westeros_slide_ids',true))as$image){$url=wp_get_attachment_image_url($image,'full');if($url)$slides[]=array('image'=>$url,'title'=>get_the_title($image),'caption'=>wp_get_attachment_caption($image),'link'=>$url);}
    }else{
        foreach(westeros_decode_legacy_array(get_post_meta($id,'custom_slider',true))as$item){if(!is_array($item))continue;$url=wp_get_attachment_image_url(absint($item['id']??0),'full');if($url)$slides[]=array('image'=>$url,'title'=>$item['title']??'','caption'=>$item['caption']??'','link'=>$item['link']??$url);}
    }
    return$slides;
}

function westeros_render_slides($slides,$label){
    $slides=array_values(array_filter($slides,static fn($slide)=>is_array($slide)&&!empty($slide['image'])));if(!$slides)return;$prefix=wp_unique_id('ws-media-');
    echo'<section class="ws-feature-slider" data-feature-slider role="region" aria-roledescription="اسلایدر" aria-label="'.esc_attr($label).'"><div class="ws-feature-slides">';
    foreach($slides as$i=>$slide){$title=wp_strip_all_tags($slide['title']??'');$caption=wp_strip_all_tags($slide['caption']??'');$link=esc_url($slide['link']??$slide['image']);
        echo'<article class="ws-feature-slide" '.($i?'hidden':'').' role="group" aria-label="'.esc_attr(($i+1).' از '.count($slides)).'"><a class="ws-feature-link" href="'.$link.'" aria-label="'.esc_attr($title?:'نمایش تصویر').'"><img src="'.esc_url($slide['image']).'" alt="'.esc_attr($title).'" loading="'.($i?'lazy':'eager').'" decoding="async"></a><div class="ws-feature-caption"><h2 id="'.esc_attr($prefix.$i).'">'.esc_html($title).'</h2>';if($caption)echo'<p>'.esc_html($caption).'</p>';echo'</div></article>';
    }
    echo'</div>';
    if(count($slides)>1){echo'<div class="ws-feature-controls"><button class="ws-control ws-js-control" data-feature-prev type="button" aria-label="تصویر قبلی">'.westeros_icon('right').'</button><button class="ws-control ws-js-control" data-feature-next type="button" aria-label="تصویر بعدی">'.westeros_icon('left').'</button></div><div class="ws-feature-dots">';foreach($slides as$i=>$slide)echo'<button type="button" class="ws-js-control" data-feature-index="'.esc_attr($i).'" aria-label="'.esc_attr('تصویر '.($i+1)).'" '.(!$i?'aria-current="true"':'').'></button>';echo'</div><p class="ws-sr-only" aria-live="polite" data-feature-status></p>';}
    echo'</section>';
}

add_action('init',function(){register_post_type('tie_slider',array('labels'=>array('name'=>'گالری‌های وستروس','singular_name'=>'گالری وستروس','add_new_item'=>'افزودن گالری'),'public'=>false,'show_ui'=>true,'show_in_menu'=>'themes.php','supports'=>array('title'),'capability_type'=>'post','map_meta_cap'=>true));});
add_action('add_meta_boxes_tie_slider',function(){add_meta_box('westeros-gallery','تصاویر گالری',function($post){wp_nonce_field('westeros_gallery','westeros_gallery_nonce');$ids=wp_parse_id_list(get_post_meta($post->ID,'_westeros_slide_ids',true));echo'<p>تصاویر را به ترتیب دلخواه انتخاب کنید. عنوان و زیرنویس هر تصویر از کتابخانهٔ رسانه خوانده می‌شود.</p><input type="hidden" id="ws-gallery-ids" name="westeros_slide_ids" value="'.esc_attr(implode(',',$ids)).'"><button class="button" type="button" data-ws-gallery="ws-gallery-ids">انتخاب تصاویر</button><p data-ws-gallery-count>'.esc_html(count($ids)).' تصویر انتخاب‌شده</p>';},'tie_slider','normal');});
add_action('save_post_tie_slider',function($id){if(!isset($_POST['westeros_gallery_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['westeros_gallery_nonce'])),'westeros_gallery')||!current_user_can('edit_post',$id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($id))return;$ids=wp_parse_id_list(wp_unslash($_POST['westeros_slide_ids']??''));$ids=array_values(array_filter($ids,'wp_attachment_is_image'));update_post_meta($id,'_westeros_slide_ids',array_slice($ids,0,30));});

add_action('add_meta_boxes',function(){foreach(array('post','page')as$type)add_meta_box('westeros-presentation','نمایش در وستروس','westeros_presentation_fields',$type,'side');});
function westeros_presentation_fields($post){
    wp_nonce_field('westeros_presentation','westeros_presentation_nonce');$media=get_post_meta($post->ID,'tie_post_head',true);$sidebar=get_post_meta($post->ID,'tie_sidebar_pos',true);
    echo'<p><label for="ws-post-media">رسانهٔ ابتدای مطلب</label></p><select class="widefat" id="ws-post-media" name="ws_post_media">';foreach(array(''=>'پیش‌فرض سایت','none'=>'بدون رسانه','thumb'=>'تصویر شاخص','lightbox'=>'تصویر بزرگ‌شونده','video'=>'ویدئو','audio'=>'فایل صوتی','soundcloud'=>'ساندکلاد','map'=>'نقشه','slider'=>'گالری ذخیره‌شده')as$k=>$v)echo'<option value="'.esc_attr($k).'" '.selected($media,$k,false).'>'.esc_html($v).'</option>';echo'</select>';
    foreach(array('tie_video_url'=>'نشانی ویدئو','tie_audio_mp3'=>'نشانی فایل MP3','tie_audio_m4a'=>'نشانی فایل M4A','tie_audio_oga'=>'نشانی فایل OGG','tie_audio_soundcloud'=>'نشانی ساندکلاد','tie_googlemap_url'=>'نشانی نقشه')as$key=>$label)echo'<p><label>'.esc_html($label).'<input type="url" class="widefat" name="ws_media['.esc_attr($key).']" value="'.esc_attr(get_post_meta($post->ID,$key,true)).'"></label></p>';
    echo'<p><label>گالری ذخیره‌شده<select class="widefat" name="ws_post_slider"><option value="0">انتخاب نشده</option>';foreach(get_posts(array('post_type'=>'tie_slider','posts_per_page'=>100))as$slider)echo'<option value="'.esc_attr($slider->ID).'" '.selected(get_post_meta($post->ID,'tie_post_slider',true),$slider->ID,false).'>'.esc_html($slider->post_title).'</option>';echo'</select></label></p>';
    echo'<p><label>ستون کناری<select class="widefat" name="ws_post_sidebar_pos">';foreach(array(''=>'پیش‌فرض سایت','left'=>'نمایش ستون','full'=>'تمام‌عرض')as$k=>$v)echo'<option value="'.$k.'" '.selected($sidebar,$k,false).'>'.$v.'</option>';echo'</select></label></p>';
    global$wp_registered_sidebars;echo'<p><label>انتخاب ابزارک‌ها<select class="widefat" name="ws_post_sidebar"><option value="">ستون پیش‌فرض</option>';foreach($wp_registered_sidebars as$id=>$area)echo'<option value="'.esc_attr($id).'" '.selected(sanitize_title(get_post_meta($post->ID,'tie_sidebar_post',true)),$id,false).'>'.esc_html($area['name']).'</option>';echo'</select></label></p>';
    $position=get_post_meta($post->ID,'tie_review_position',true);echo'<p><label>نمایش ارزیابی موجود<select name="ws_review_position" class="widefat">';foreach(array(''=>'نمایش ندادن','top'=>'بالای متن','bottom'=>'پایین متن','both'=>'بالا و پایین')as$k=>$v)echo'<option value="'.$k.'" '.selected($position,$k,false).'>'.$v.'</option>';echo'</select></label></p>';
}
add_action('save_post',function($id){
    if(!isset($_POST['westeros_presentation_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['westeros_presentation_nonce'])),'westeros_presentation')||!current_user_can('edit_post',$id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($id))return;
    foreach(array('ws_post_media'=>array('tie_post_head',array('','none','thumb','lightbox','video','audio','soundcloud','map','slider')),'ws_post_sidebar_pos'=>array('tie_sidebar_pos',array('','left','full')),'ws_review_position'=>array('tie_review_position',array('','top','bottom','both')))as$field=>$rule){$value=sanitize_key(wp_unslash($_POST[$field]??''));if(in_array($value,$rule[1],true))update_post_meta($id,$rule[0],$value);}
    foreach((array)($_POST['ws_media']??array())as$key=>$value){if(in_array($key,array('tie_video_url','tie_audio_mp3','tie_audio_m4a','tie_audio_oga','tie_audio_soundcloud','tie_googlemap_url'),true)&&is_string($value))update_post_meta($id,$key,esc_url_raw(wp_unslash($value),array('http','https')));}
    if(isset($_POST['ws_post_slider'])&&is_scalar($_POST['ws_post_slider']))update_post_meta($id,'tie_post_slider',absint($_POST['ws_post_slider']));
    if(isset($_POST['ws_post_sidebar'])&&is_string($_POST['ws_post_sidebar']))update_post_meta($id,'tie_sidebar_post',sanitize_title(wp_unslash($_POST['ws_post_sidebar'])));
});

add_action('admin_enqueue_scripts',function($hook){if(in_array($hook,array('post.php','post-new.php'),true)){wp_enqueue_media();wp_enqueue_script('westeros-admin',get_stylesheet_directory_uri().'/assets/admin.js',array(),filemtime(__DIR__.'/../assets/admin.js'),true);}});
