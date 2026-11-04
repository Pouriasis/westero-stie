<?php
defined('ABSPATH') || exit;

add_action('category_edit_form_fields',function($term){
    if(!current_user_can('edit_theme_options'))return;
    $settings=westeros_category_settings($term->term_id);
    wp_nonce_field('westeros_category','westeros_category_nonce');
    echo'<tr class="form-field"><th scope="row"><label for="ws-category-sidebar">ستون وستروس</label></th><td><select id="ws-category-sidebar" name="westeros_category_sidebar"><option value="">پیش‌فرض آرشیو</option>';
    global$wp_registered_sidebars;
    foreach($wp_registered_sidebars as$id=>$area)echo'<option value="'.esc_attr($id).'" '.selected(sanitize_title($settings['cat_sidebar']??''),$id,false).'>'.esc_html($area['name']).'</option>';
    echo'</select></td></tr><tr class="form-field"><th scope="row">اسلایدر دسته</th><td><label><input type="checkbox" name="westeros_category_slider" value="1" '.checked(!empty($settings['cat_slider']),true,false).'> نمایش آخرین مطالب این دسته در اسلایدر</label></td></tr>';
});
add_action('edited_category',function($id){
    $nonce=$_POST['westeros_category_nonce']??'';
    if(!is_string($nonce)||!wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)),'westeros_category')||!current_user_can('edit_theme_options')||!current_user_can('edit_term',$id))return;
    $sidebar=$_POST['westeros_category_sidebar']??'';
    if(!is_string($sidebar))return;
    $all=(array)get_option('westeros_category_options',array());$settings=westeros_category_settings($id);
    $settings['cat_sidebar']=sanitize_title(wp_unslash($sidebar));$settings['cat_slider']=!empty($_POST['westeros_category_slider']);
    $all[$id]=$settings;update_option('westeros_category_options',$all,false);westeros_purge_theme_cache();
});
