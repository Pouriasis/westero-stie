<?php
defined('ABSPATH') || exit;

function westeros_clean_discovery_options($input) {
    $current=westeros_discovery_options();
    if (!is_array($input)) { return $current; }
    $input['types']=isset($input['types'])&&is_array($input['types'])?$input['types']:array();
    $current['show_home']=!empty($input['show_home']);
    foreach (westeros_content_kinds() as $kind=>$label) {
        $values=$input['types'][$kind]??array();$ids=westeros_path_ids($values);$valid=array();
        foreach ($ids as $id) { $term=get_term($id,'category');if ($term&&!is_wp_error($term)) { $valid[]=$id; } }
        $current['types'][$kind]=$valid;
    }
    return $current;
}
add_action('admin_init',function(){register_setting('westeros_discovery','westeros_discovery_options',array('sanitize_callback'=>'westeros_clean_discovery_options'));});
add_filter('option_page_capability_westeros_discovery',static fn()=>'edit_theme_options');
add_action('admin_menu',function(){add_submenu_page('edit.php?post_type=ws_dossier','تنظیمات کشف محتوا','تنظیمات کشف محتوا','edit_theme_options','westeros-discovery','westeros_discovery_settings_screen');});
function westeros_discovery_settings_screen() {
    if (!current_user_can('edit_theme_options')) { return; }
    $options=westeros_discovery_options();$categories=get_categories(array('hide_empty'=>false));
    echo '<div class="wrap"><h1>تنظیمات کشف محتوا</h1>';settings_errors();echo '<form action="options.php" method="post">';settings_fields('westeros_discovery');
    echo '<table class="form-table"><tr><th>صفحهٔ اصلی</th><td><input type="hidden" name="westeros_discovery_options[show_home]" value="0"><label><input type="checkbox" name="westeros_discovery_options[show_home]" value="1" '.checked((bool)$options['show_home'],true,false).'> نمایش پرونده‌های منتخب در انتهای بخش‌های مجله</label></td></tr>';
    foreach (westeros_content_kinds() as $kind=>$label) {
        echo '<tr><th><label for="ws-kind-'.$kind.'">'.esc_html($label).'</label></th><td><input type="hidden" name="westeros_discovery_options[types]['.$kind.'][]" value="0"><select multiple size="6" id="ws-kind-'.$kind.'" name="westeros_discovery_options[types]['.$kind.'][]">';
        foreach ($categories as $category) { echo '<option value="'.esc_attr($category->term_id).'" '.selected(in_array((int)$category->term_id,westeros_kind_categories($kind),true),true,false).'>'.esc_html($category->name).'</option>'; }
        echo '</select><p class="description">دسته‌های این نوع مطلب؛ زیر‌دسته‌ها هم در فیلتر محاسبه می‌شوند. برای پنهان‌کردن این نوع، انتخاب‌ها را پاک کنید.</p></td></tr>';
    }
    echo '</table>';submit_button('ذخیرهٔ تنظیمات کشف محتوا');echo '</form></div>';
}

add_action('add_meta_boxes_ws_dossier',function(){
    add_meta_box('ws-dossier-order','مطالب و ترتیب مطالعه','westeros_dossier_editor','ws_dossier','normal','high');
    add_meta_box('ws-dossier-featured','نمایش در صفحهٔ اصلی',function($post){echo '<label><input type="checkbox" name="ws_dossier_featured" value="1" '.checked(get_post_meta($post->ID,'_ws_dossier_featured',true),'1',false).'> پروندهٔ منتخب</label><p>حداکثر سه پروندهٔ منتخب بر اساس ترتیبِ تعیین‌شده در «ویژگی‌های پرونده» نمایش داده می‌شوند.</p>';},'ws_dossier','side');
});
function westeros_dossier_editor($post) {
    wp_nonce_field('westeros_dossier','westeros_dossier_nonce');
    echo '<div class="ws-dossier-editor" data-dossier-editor><p>مطالب منتشرشده و بدون گذرواژه را انتخاب کنید؛ ترتیب فهرست، مسیر مطالعهٔ خواننده است. حداکثر ۴۰ مطلب.</p><label for="ws-dossier-search">جست‌وجوی مطلب</label><div class="ws-dossier-search"><input id="ws-dossier-search" type="search" autocomplete="off" placeholder="عنوان مطلب"><button type="button" class="button" data-dossier-search>جست‌وجو</button></div><p role="status" aria-live="polite" data-dossier-status></p><ul class="ws-dossier-results" data-dossier-results></ul><ol class="ws-dossier-picked" data-dossier-picked>';
    // Keep stored unavailable IDs in the editor until the author deliberately saves.
    foreach (westeros_path_ids(get_post_meta($post->ID,'_ws_dossier_post_ids',true)) as $id) {
        $item=get_post($id);$public=$item&&$item->post_type==='post'&&$item->post_status==='publish'&&$item->post_password==='';
        $title=$item&&current_user_can('read_post',$id)?get_the_title($id):'مطلب در دسترس نیست';
        echo '<li data-post-id="'.esc_attr($id).'"><input type="hidden" name="ws_dossier_ids[]" value="'.esc_attr($id).'"><span>'.esc_html($title).($public?'':' — در مسیر عمومی نمایش داده نمی‌شود').'</span><div><button type="button" class="button" data-dossier-move="up" aria-label="جابجایی به بالا">بالا</button> <button type="button" class="button" data-dossier-move="down" aria-label="جابجایی به پایین">پایین</button> <button type="button" class="button-link-delete" data-dossier-remove>حذف</button></div></li>';
    }
    echo '</ol><p data-dossier-empty>اگر فهرست خالی است، از جست‌وجوی بالا مطلب اضافه کنید.</p><input type="hidden" name="ws_dossier_ids[]" value="0"></div>';
}
function westeros_save_dossier($id) {
    $nonce=$_POST['westeros_dossier_nonce']??'';
    if (!is_string($nonce)||!wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)),'westeros_dossier')||!current_user_can('edit_post',$id)||wp_is_post_revision($id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)) { return; }
    westeros_update_dossier_items($id, wp_unslash($_POST['ws_dossier_ids']??array()));
    update_post_meta($id,'_ws_dossier_featured',empty($_POST['ws_dossier_featured'])?'0':'1');
}
add_action('save_post_ws_dossier','westeros_save_dossier',20);

function westeros_dossier_search() {
    if (!current_user_can('edit_posts')) { wp_send_json_error(array('message'=>'اجازهٔ ویرایش ندارید.'),403); }
    check_ajax_referer('westeros_dossier_search','nonce');
    $raw=$_GET['q']??'';$query=is_string($raw)?sanitize_text_field(wp_unslash($raw)):'';
    if (mb_strlen($query)<2||mb_strlen($query)>100) { wp_send_json_error(array('message'=>'بین ۲ تا ۱۰۰ نویسه وارد کنید.'),400); }
    $posts=get_posts(array('post_type'=>'post','post_status'=>'publish','has_password'=>false,'s'=>$query,'posts_per_page'=>12,'no_found_rows'=>true,'orderby'=>'relevance','suppress_filters'=>false));
    wp_send_json_success(array_map(static fn($p)=>array('id'=>(int)$p->ID,'title'=>get_the_title($p),'date'=>get_the_date('', $p)), $posts));
}
add_action('wp_ajax_westeros_dossier_search','westeros_dossier_search');
add_action('admin_enqueue_scripts',function($hook){
    if (!in_array($hook,array('post.php','post-new.php'),true)||get_current_screen()->post_type!=='ws_dossier') { return; }
    wp_enqueue_script('westeros-discovery-admin',get_stylesheet_directory_uri().'/assets/discovery-admin.js',array(),(string)filemtime(__DIR__.'/../assets/discovery-admin.js'),true);
    wp_localize_script('westeros-discovery-admin','westerosDossierEditor',array('url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('westeros_dossier_search')));
    wp_enqueue_style('westeros-discovery-admin',get_stylesheet_directory_uri().'/assets/discovery-admin.css',array(),(string)filemtime(__DIR__.'/../assets/discovery-admin.css'));
});
