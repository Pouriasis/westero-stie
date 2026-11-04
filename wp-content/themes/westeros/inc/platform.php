<?php
defined('ABSPATH') || exit;

add_action('after_setup_theme',function(){
    add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('automatic-feed-links');add_theme_support('responsive-embeds');add_theme_support('html5',array('search-form','comment-form','comment-list','gallery','caption','style','script'));add_theme_support('editor-styles');add_editor_style(array('style.css','assets/editor.css'));
    register_nav_menus(array('primary'=>'منوی اصلی وستروس','top-menu'=>'منوی بالای سایت'));
    add_image_size('tie-small',70,70,true);add_image_size('tie-large',300,160,true);add_image_size('slider',620,330,true);
    global$content_width;$content_width=870;
},50);

add_action('widgets_init',function(){
    $areas=array('primary-widget-area'=>'ستون اصلی برگه‌ها و مطالب','homepage-normal-widget-area1'=>'ستون صفحهٔ اصلی','homepage-normal-widget-area2'=>'ستون دوم صفحهٔ اصلی','narrow-primary-widget-area'=>'ستون باریک مطالب','homepage-narrow-widget-area1'=>'ستون باریک صفحهٔ اصلی','homepage-narrow-widget-area2'=>'ستون باریک دوم صفحهٔ اصلی','first-footer-widget-area'=>'پانوشت ۱','second-footer-widget-area'=>'پانوشت ۲','third-footer-widget-area'=>'پانوشت ۳','fourth-footer-widget-area'=>'پانوشت ۴');
    foreach((array)westeros_option('sidebars')as$name){$id=sanitize_title($name);if($id&&!isset($areas[$id]))$areas[$id]=$name;}
    foreach($areas as$id=>$name)register_sidebar(array('id'=>$id,'name'=>$name,'before_widget'=>'<section id="%1$s" class="widget %2$s"><div class="widget-container">','after_widget'=>'</div></section>','before_title'=>'<div class="widget-top"><h2>','after_title'=>'</h2></div>'));
},50);

function westeros_purge_theme_cache(){do_action('westeros_purge_cache');}
add_action('update_option_westeros_options','westeros_purge_theme_cache');add_action('update_option_westeros_home_sections','westeros_purge_theme_cache');

/** Remove the unused, self-contained iframe helper; retain other administrator code. */
function westeros_integration_html($html) {
    return preg_replace_callback('~<script\b[^>]*>(.*?)</script>~is', static function ($match) {
        // Contact Form 7 owns CAPTCHA loading and its key on pages that contain forms.
        if (preg_match('~\bsrc\s*=\s*[\x22\x27]https?://(?:www\.)?(?:google\.com|recaptcha\.net)/recaptcha/api\.js(?:\?[^\x22\x27]*)?[\x22\x27]~i', $match[0])) { return ''; }
        if (!str_contains($match[1], 'responsiveIframes') || !str_contains($match[1], 'shenotoIframe2')) { return $match[0]; }
        // The archived helper only defines a jQuery plugin; no selector calls it.
        if (!preg_match('~\)\s*\.responsiveIframes\s*\(~', $match[1])) { return ''; }
        $script = str_replace('$(document).ready()', 'jQuery(document).ready()', $match[0]);
        // The old helper replaced every root class, erasing menu/night-mode state.
        return preg_replace('~document\s*\.\s*getElementsByTagName\s*\(\s*[\x22\x27]html[\x22\x27]\s*\)\s*\[\s*0\s*\]\s*\.\s*className\s*=\s*[\x22\x27]js[\x22\x27]~', 'document.documentElement.classList.add("js")', $script);
    }, $html);
}
add_filter('the_content','westeros_integration_html',20);
add_action('wp_head',function(){
    if(!get_option('site_icon')&&westeros_option('favicon'))echo'<link rel="icon" href="'.esc_url(westeros_option('favicon')).'">';
    echo westeros_integration_html((string)westeros_option('header_code')); // Administrator-managed, capability-checked code.
});
add_action('wp_footer',function(){echo westeros_integration_html((string)westeros_option('footer_code'));});

// The only parent-owned shortcode used by this site's stored content.
add_action('init',function(){add_shortcode('divider',static fn()=>'<div class="ws-clear"></div><hr class="ws-divider">');},30);

function westeros_decode_legacy_array($value){if(is_array($value))return$value;if(is_string($value)&&is_serialized($value)){$decoded=@unserialize($value,array('allowed_classes'=>false));return is_array($decoded)?$decoded:array();}return array();}

function westeros_record_view($id){
    if(!westeros_option('post_views')||is_preview()||post_password_required($id)||get_post_status($id)!=='publish')return;
    if(!defined('WP_CACHE')||!WP_CACHE)update_post_meta($id,'tie_views',max(0,(int)get_post_meta($id,'tie_views',true))+1);
}
add_action('wp_enqueue_scripts',function(){
    if(is_single()&&westeros_option('post_views')&&defined('WP_CACHE')&&WP_CACHE&&!post_password_required()){
        wp_enqueue_script('westeros-views',get_stylesheet_directory_uri().'/assets/views.js',array(),filemtime(__DIR__.'/../assets/views.js'),true);
        wp_localize_script('westeros-views','westerosViews',array('url'=>admin_url('admin-ajax.php'),'id'=>get_queried_object_id(),'nonce'=>wp_create_nonce('westeros-view-'.get_queried_object_id())));
    }
});
function westeros_increment_view(){
    $id=absint($_POST['post_id']??0);
    if(!westeros_option('post_views')||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce']??'')),'westeros-view-'.$id)||get_post_status($id)!=='publish'||post_password_required($id)){wp_send_json_error(null,403);}
    update_post_meta($id,'tie_views',max(0,(int)get_post_meta($id,'tie_views',true))+1);wp_send_json_success();
}
add_action('wp_ajax_westeros_view','westeros_increment_view');add_action('wp_ajax_nopriv_westeros_view','westeros_increment_view');

function westeros_review($position='review-top'){
    $id=get_the_ID();if(post_password_required($id))return;$criteria=westeros_decode_legacy_array(get_post_meta($id,'tie_review_criteria',true));
    if(!$criteria)return;$style=get_post_meta($id,'tie_review_style',true);$total=0;$count=0;
    echo'<section class="ws-review '.esc_attr($position).'" aria-label="ارزیابی مطلب"><h2>ارزیابی</h2>';
    foreach($criteria as$item){if(!is_array($item)||!isset($item['name'],$item['score'])||!is_numeric($item['score']))continue;$score=max(0,min(100,(float)$item['score']));$total+=$score;$count++;$label=$style==='points'?number_format_i18n($score/10,1).' / ۱۰':number_format_i18n($score).'٪';echo'<div class="ws-review-item"><span>'.esc_html($item['name']).'</span><strong>'.esc_html($label).'</strong><meter min="0" max="100" value="'.esc_attr($score).'" aria-label="'.esc_attr($item['name']).'">'.esc_html($label).'</meter></div>';}
    if($count)echo'<p class="ws-review-total">امتیاز میانگین: '.esc_html(number_format_i18n($total/$count/10,1)).' / ۱۰</p>';
    $summary=get_post_meta($id,'tie_review_summary',true);if($summary)echo'<div>'.wp_kses_post(htmlspecialchars_decode($summary)).'</div>';echo'</section>';
}

function westeros_banner($name,$before='',$after=''){ $html=westeros_option($name);if(is_string($html)&&$html!=='')echo$before.do_shortcode($html).$after; }
