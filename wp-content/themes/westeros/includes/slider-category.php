<?php defined('ABSPATH') || exit;
$id=get_queried_object_id();$settings=westeros_category_settings($id);if(empty($settings['cat_slider']))return;
$query=new WP_Query(array('cat'=>$id,'posts_per_page'=>max(1,min(10,(int)westeros_option('slider_number'))),'post_status'=>'publish','no_found_rows'=>true,'ignore_sticky_posts'=>true));$slides=array();
foreach($query->posts as$item){$image=get_the_post_thumbnail_url($item,'full');if($image)$slides[]=array('image'=>$image,'title'=>get_the_title($item),'link'=>get_permalink($item));}
westeros_render_slides($slides,'برگزیده‌های این دسته');
