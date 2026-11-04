<?php
defined('ABSPATH') || exit;

function westeros_boolean_options() {
    return array('slider','post_nav','post_featured','post_tags','post_authorbio','author_bio','related','share_post','post_views','category_desc','category_rss','tag_rss','author_rss');
}

function westeros_option_defaults() {
    return array('on_home'=>'boxes','logo'=>'','favicon'=>'','slider'=>true,'slider_query'=>'category','slider_number'=>5,'slider_cat'=>array(),'slider_posts'=>'','slider_tag'=>'','slider_custom'=>0,'post_nav'=>true,'post_featured'=>true,'post_tags'=>true,'post_authorbio'=>false,'author_bio'=>false,'related'=>true,'share_post'=>false,'post_views'=>false,'category_desc'=>false,'category_rss'=>true,'tag_rss'=>false,'author_rss'=>false,'footer_widgets'=>'footer-3c','footer_one'=>'تمامی حقوق برای وبگاه وستروس محفوظ است. © %year%','header_code'=>'','footer_code'=>'','sidebar_page'=>'','sidebar_post'=>'','sidebar_archive'=>'','sidebars'=>array(),'banner_above'=>'','banner_below'=>'');
}

function westeros_option($key, $default = null) {
    $options = get_option('westeros_options', array());
    if (is_array($options) && array_key_exists($key, $options)) { return $options[$key]; }
    $defaults = westeros_option_defaults();
    return $default ?? ($defaults[$key] ?? false);
}

function westeros_home_sections() { return (array) get_option('westeros_home_sections', array()); }
function westeros_category_settings($id) { $all=(array)get_option('westeros_category_options',array());return isset($all[(int)$id])&&is_array($all[(int)$id])?$all[(int)$id]:array(); }

/** One-way configuration import; original options and all post content stay intact. */
function westeros_import_legacy_settings() {
    if (is_array(get_option('westeros_options', null))) { return; }
    $legacy = get_option('tie_options', null);
    $options = westeros_option_defaults();
    if (is_array($legacy)) {
        foreach ($options as $key=>$value) { if (array_key_exists($key,$legacy)) { $options[$key] = $legacy[$key]; } }
        foreach (westeros_boolean_options() as $key) { $options[$key] = !empty($legacy[$key]); }
        foreach (array('header_code','footer_code') as $key) { $options[$key] = htmlspecialchars_decode(stripslashes((string) ($legacy[$key] ?? ''))); }
        foreach (array('banner_above','banner_below') as $key) {
            $html = '';
            if (!empty($legacy[$key])) {
                if (!empty($legacy[$key.'_img'])) {
                    $image='<img src="'.esc_url($legacy[$key.'_img']).'" alt="'.esc_attr($legacy[$key.'_alt']??'').'">';
                    $html=!empty($legacy[$key.'_url'])?'<a href="'.esc_url($legacy[$key.'_url']).'">'.$image.'</a>':$image;
                } else { $html = htmlspecialchars_decode((string) ($legacy[$key.'_adsense'] ?? '')); }
            }
            $options[$key]=$html;
        }
    }
    $options['slider_cat']=array_values(array_filter(array_map('absint',(array)$options['slider_cat'])));
    $options['sidebars']=array_values(array_filter((array)$options['sidebars'],'is_string'));
    $sections=array();
    foreach(array('tie_home_cats1','tie_home_cats2','tie_home_cats3','tie_home_cats4') as $key){foreach((array)get_option($key,array())as$block){if(is_array($block))$sections[]=$block;}}
    $categories=array();
    foreach(get_categories(array('hide_empty'=>false))as$category){$settings=get_option('tie_cat_'.$category->term_id,array());if(is_array($settings)&&$settings)$categories[$category->term_id]=$settings;}
    add_option('westeros_options',$options,'',false);
    add_option('westeros_home_sections',$sections,'',false);
    add_option('westeros_category_options',$categories,'',false);
    add_option('westeros_configuration_version',1,'',false);
}
add_action('after_setup_theme','westeros_import_legacy_settings',5);

function westeros_clean_sections($input) {
    if (!is_array($input)) { return array(); }
    $out=array();
    foreach(array_slice($input,0,20)as$row){
        if(!is_array($row))continue;
        $row=array_filter($row,'is_scalar');
        $id=absint($row['id']??0);$term=get_term($id,'category');
        if(!$term||is_wp_error($term))continue;
        $style=in_array($row['style']??'',array('2c','1c','list'),true)?$row['style']:'1c';
        $out[]=array('id'=>(string)$id,'title'=>sanitize_text_field($row['title']??''),'number'=>(string)max(1,min(24,(int)($row['number']??5))),'offset'=>(string)max(0,(int)($row['offset']??0)),'order'=>($row['order']??'')==='rand'?'rand':'latest','type'=>($row['type']??'')==='s'?'s':'n','style'=>$style);
    }
    return $out;
}

function westeros_clean_options($input) {
    $current=(array)get_option('westeros_options',westeros_option_defaults());
    if(!is_array($input))return$current;
    $input=array_filter($input,static fn($value,$key)=>is_scalar($value)||in_array($key,array('slider_cat','sidebars'),true),ARRAY_FILTER_USE_BOTH);
    foreach(westeros_boolean_options()as$key){if(array_key_exists($key,$input))$current[$key]=!empty($input[$key]);}
    foreach(array('logo','favicon')as$key){if(isset($input[$key]))$current[$key]=esc_url_raw($input[$key],array('http','https'));}
    foreach(array('on_home'=>array('boxes','list'),'slider_query'=>array('latest','category','post','tag','custom'),'footer_widgets'=>array('disable','footer-1c','footer-2c','footer-3c','footer-4c'))as$key=>$allowed){if(isset($input[$key])&&in_array($input[$key],$allowed,true))$current[$key]=$input[$key];}
    if(isset($input['slider_number']))$current['slider_number']=max(1,min(10,(int)$input['slider_number']));
    if(isset($input['slider_posts']))$current['slider_posts']=implode(',',wp_parse_id_list($input['slider_posts']));
    if(isset($input['slider_tag']))$current['slider_tag']=sanitize_text_field($input['slider_tag']);
    if(isset($input['slider_custom']))$current['slider_custom']=absint($input['slider_custom']);
    if(array_key_exists('slider_cat',$input))$current['slider_cat']=array_values(array_filter(array_map('absint',array_filter((array)$input['slider_cat'],'is_scalar'))));
    foreach(array('sidebar_page','sidebar_post','sidebar_archive')as$key){if(isset($input[$key]))$current[$key]=sanitize_title($input[$key]);}
    if(isset($input['sidebars'])){$names=is_array($input['sidebars'])?$input['sidebars']:preg_split('/\R/u',(string)$input['sidebars']);$current['sidebars']=array_slice(array_values(array_unique(array_filter(array_map('sanitize_text_field',array_filter($names,'is_string'))))),0,20);}
    if(isset($input['footer_one']))$current['footer_one']=wp_kses_post($input['footer_one']);
    if(current_user_can('unfiltered_html')){foreach(array('header_code','footer_code','banner_above','banner_below')as$key){if(isset($input[$key])&&is_string($input[$key]))$current[$key]=$input[$key];}}
    return $current;
}

add_action('admin_init',function(){
    register_setting('westeros_settings','westeros_options',array('sanitize_callback'=>'westeros_clean_options'));
    register_setting('westeros_settings','westeros_home_sections',array('sanitize_callback'=>'westeros_clean_sections'));
});
add_filter('option_page_capability_westeros_settings',static fn()=>'edit_theme_options');
add_action('admin_menu',function(){add_theme_page('تنظیمات وستروس','تنظیمات وستروس','edit_theme_options','westeros-settings','westeros_settings_screen');});
add_action('admin_enqueue_scripts',function($hook){if($hook==='appearance_page_westeros-settings'){wp_enqueue_media();wp_enqueue_script('westeros-admin',get_stylesheet_directory_uri().'/assets/admin.js',array(),filemtime(__DIR__.'/../assets/admin.js'),true);wp_enqueue_style('westeros-admin',get_stylesheet_directory_uri().'/assets/admin.css',array(),filemtime(__DIR__.'/../assets/admin.css'));}});

function westeros_setting_field($key,$label,$type='text',$choices=array()) {
    $value=westeros_option($key);$name='westeros_options['.$key.']';$id='ws-setting-'.$key;
    echo '<div class="ws-setting"><label for="'.esc_attr($id).'">'.esc_html($label).'</label>';
    if($type==='checkbox'){echo '<input type="hidden" name="'.esc_attr($name).'" value="0"><input id="'.esc_attr($id).'" type="checkbox" name="'.esc_attr($name).'" value="1" '.checked((bool)$value,true,false).'>';}
    elseif($type==='select'){echo '<select id="'.esc_attr($id).'" name="'.esc_attr($name).'">';foreach($choices as$k=>$v)echo'<option value="'.esc_attr($k).'" '.selected($value,$k,false).'>'.esc_html($v).'</option>';echo'</select>';}
    elseif($type==='textarea'){if(is_array($value))$value=implode("\n",$value);echo'<textarea id="'.esc_attr($id).'" name="'.esc_attr($name).'" rows="5">'.esc_textarea((string)$value).'</textarea>';}
    else{echo'<input id="'.esc_attr($id).'" type="'.esc_attr($type==='media'?'url':$type).'" name="'.esc_attr($name).'" value="'.esc_attr((string)$value).'">';if($type==='media')echo'<button type="button" class="button" data-ws-media="'.esc_attr($id).'">انتخاب تصویر</button>';}
    echo'</div>';
}

function westeros_section_fields($index,$row,$categories) {
    echo'<div class="ws-section-editor" data-ws-section><h3 data-ws-section-title>بخش '.esc_html(is_numeric($index)?(string)((int)$index+1):'').'</h3><div class="ws-section-editor-fields">';
    $base='westeros_home_sections['.$index.']';
    echo'<label>دسته<select name="'.esc_attr($base.'[id]').'">';foreach($categories as$category)echo'<option value="'.esc_attr($category->term_id).'" '.selected($row['id']??'',$category->term_id,false).'>'.esc_html($category->name).'</option>';echo'</select></label>';
    echo'<label>عنوان دلخواه<input name="'.esc_attr($base.'[title]').'" value="'.esc_attr($row['title']??'').'"></label>';
    echo'<label>نمایش<select name="'.esc_attr($base.'[type]').'">';foreach(array('n'=>'خبر و مقاله','s'=>'ردیف اسکرولی')as$k=>$v)echo'<option value="'.$k.'" '.selected($row['type']??'n',$k,false).'>'.$v.'</option>';echo'</select></label>';
    echo'<label>چیدمان<select name="'.esc_attr($base.'[style]').'">';foreach(array('2c'=>'دو بخش کنار هم','1c'=>'مطلب شاخص عریض','list'=>'فهرست مطالب')as$k=>$v)echo'<option value="'.$k.'" '.selected($row['style']??'1c',$k,false).'>'.$v.'</option>';echo'</select></label>';
    echo'<label>تعداد<input type="number" min="1" max="24" name="'.esc_attr($base.'[number]').'" value="'.esc_attr($row['number']??5).'"></label><label>نادیده گرفتن ابتدای فهرست<input type="number" min="0" name="'.esc_attr($base.'[offset]').'" value="'.esc_attr($row['offset']??0).'"></label>';
    echo'<label>ترتیب<select name="'.esc_attr($base.'[order]').'"><option value="latest">تازه‌ترین</option><option value="rand" '.selected($row['order']??'','rand',false).'>تصادفی</option></select></label></div>';
    echo'<div class="ws-section-editor-actions"><button type="button" class="button" data-ws-move="up" aria-label="جابجایی بخش به بالا">بالا</button><button type="button" class="button" data-ws-move="down" aria-label="جابجایی بخش به پایین">پایین</button><button type="button" class="button-link-delete" data-ws-remove>حذف بخش</button></div></div>';
}

function westeros_settings_screen() {
    if(!current_user_can('edit_theme_options'))return;
    $categories=get_categories(array('hide_empty'=>false));
    echo'<div class="wrap ws-settings"><h1>تنظیمات وستروس</h1><p>ظاهر، بخش‌های صفحهٔ اصلی و تجربهٔ مطالعه را از اینجا تنظیم کنید.</p>';settings_errors();echo'<form method="post" action="options.php">';settings_fields('westeros_settings');
    echo'<section class="ws-settings-panel"><h2>هویت سایت</h2>';westeros_setting_field('logo','بنر بالای سایت','media');westeros_setting_field('favicon','نشان کوچک سایت، در صورت نداشتن آیکون وردپرس','media');echo'</section>';
    echo'<section class="ws-settings-panel"><h2>خبرهای برگزیده</h2>';westeros_setting_field('slider','نمایش ردیف خبرهای برگزیده','checkbox');westeros_setting_field('slider_query','انتخاب مطالب','select',array('latest'=>'تازه‌ترین مطالب','category'=>'از دسته‌ها','post'=>'مطالب انتخابی','tag'=>'از برچسب‌ها','custom'=>'اسلایدر ذخیره‌شده'));westeros_setting_field('slider_number','تعداد خبرها','number');
    echo'<div class="ws-setting"><label for="ws-slider-categories">دسته‌های خبر برگزیده</label><input type="hidden" name="westeros_options[slider_cat][]" value="0"><select id="ws-slider-categories" name="westeros_options[slider_cat][]" multiple size="7">';foreach($categories as$category)echo'<option value="'.esc_attr($category->term_id).'" '.selected(in_array((int)$category->term_id,array_map('intval',(array)westeros_option('slider_cat')),true),true,false).'>'.esc_html($category->name).'</option>';echo'</select></div>';
    westeros_setting_field('slider_posts','شناسهٔ مطالب انتخابی، جداشده با ویرگول');westeros_setting_field('slider_tag','نام برچسب‌ها، جداشده با ویرگول');westeros_setting_field('slider_custom','شناسهٔ اسلایدر ذخیره‌شده','number');echo'</section>';
    echo'<section class="ws-settings-panel"><h2>بخش‌های صفحهٔ اصلی</h2>';westeros_setting_field('on_home','نوع صفحهٔ اصلی','select',array('boxes'=>'مجله‌ای','list'=>'فهرست مطالب'));echo'<p>دو بخش با چیدمان «دو بخش کنار هم» را پشت سر هم قرار دهید.</p><input type="hidden" name="westeros_home_sections[empty]" value=""><div data-ws-sections>';foreach(westeros_home_sections()as$i=>$row)westeros_section_fields($i,$row,$categories);echo'</div><button type="button" class="button" data-ws-add-section>افزودن بخش</button><template id="ws-section-template">';westeros_section_fields('__INDEX__',array(),$categories);echo'</template></section>';
    echo'<section class="ws-settings-panel"><h2>مطالعه و آرشیو</h2>';foreach(array('post_featured'=>'نمایش تصویر شاخص مطلب','post_nav'=>'پیوند روایت قبل و بعد','post_tags'=>'نمایش برچسب‌ها','related'=>'مطالب مرتبط','share_post'=>'پیوندهای اشتراک‌گذاری','post_authorbio'=>'معرفی نویسنده زیر مطلب','author_bio'=>'معرفی نویسنده در آرشیو','post_views'=>'شمارش بازدید مطلب','category_desc'=>'توضیح دسته‌ها','category_rss'=>'خوراک دسته','tag_rss'=>'خوراک برچسب','author_rss'=>'خوراک نویسنده')as$key=>$label)westeros_setting_field($key,$label,'checkbox');echo'</section>';
    global$wp_registered_sidebars;$choices=array(''=>'ستون پیش‌فرض');foreach($wp_registered_sidebars as$id=>$sidebar)$choices[$id]=$sidebar['name'];
    echo'<section class="ws-settings-panel"><h2>ستون‌ها و پانوشت</h2>';westeros_setting_field('sidebars','ستون‌های سفارشی؛ نام هر ستون در یک خط','textarea');foreach(array('sidebar_post'=>'ستون مطالب','sidebar_page'=>'ستون برگه‌ها','sidebar_archive'=>'ستون آرشیو')as$key=>$label)westeros_setting_field($key,$label,'select',$choices);westeros_setting_field('footer_widgets','ستون‌های ابزارک پانوشت','select',array('disable'=>'بدون ابزارک','footer-1c'=>'یک ستون','footer-2c'=>'دو ستون','footer-3c'=>'سه ستون','footer-4c'=>'چهار ستون'));westeros_setting_field('footer_one','متن پانوشت؛ %year% برای سال','textarea');echo'</section>';
    if(current_user_can('unfiltered_html')){echo'<details class="ws-settings-panel"><summary>کدها و بنرهای مدیریت‌شده</summary>';westeros_setting_field('header_code','کد داخل سرصفحه','textarea');westeros_setting_field('footer_code','کد انتهای صفحه','textarea');westeros_setting_field('banner_above','محتوای بنر بالای مطلب','textarea');westeros_setting_field('banner_below','محتوای بنر پایین مطلب','textarea');echo'</details>';}
    submit_button('ذخیرهٔ تنظیمات وستروس');echo'</form></div>';
}
