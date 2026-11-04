<?php
/** Curated reading paths and category-backed content discovery. */
defined('ABSPATH') || exit;

add_action('init', function () {
    register_post_type('ws_dossier', array(
        'labels'=>array('name'=>'پرونده‌های وستروس','singular_name'=>'پرونده','add_new_item'=>'افزودن پرونده','edit_item'=>'ویرایش پرونده','all_items'=>'همهٔ پرونده‌ها','not_found'=>'پرونده‌ای پیدا نشد.'),
        'public'=>true, 'has_archive'=>'dossiers', 'rewrite'=>array('slug'=>'dossiers','with_front'=>false),
        'exclude_from_search'=>true, 'show_in_rest'=>false, 'menu_icon'=>'dashicons-book-alt',
        'supports'=>array('title','excerpt','thumbnail','page-attributes'), 'map_meta_cap'=>true,
    ));
});
add_action('init', function () {
    if ((int)get_option('westeros_discovery_rewrite_version') !== 1) {
        flush_rewrite_rules(false);
        update_option('westeros_discovery_rewrite_version', 1, false);
    }
}, 30);
add_filter('query_vars', function ($vars) { return array_merge($vars, array('ws_kind','ws_path')); });

function westeros_discovery_defaults() {
    return array('show_home'=>true, 'types'=>array('news'=>array(1),'articles'=>array(18),'podcasts'=>array(652),'interviews'=>array(597),'downloads'=>array(13)));
}
function westeros_discovery_options() {
    return wp_parse_args((array)get_option('westeros_discovery_options', array()), westeros_discovery_defaults());
}
function westeros_content_kinds() {
    return array('news'=>'خبر','articles'=>'مقاله','podcasts'=>'پادکست','interviews'=>'مصاحبه','downloads'=>'دانلود');
}
function westeros_kind_categories($kind) {
    $types=westeros_discovery_options()['types'];
    return isset($types[$kind])&&is_array($types[$kind]) ? array_values(array_filter(array_map('absint', $types[$kind]))) : array();
}
function westeros_selected_content_kind($value=null) {
    $value=$value ?? get_query_var('ws_kind', '');
    return is_string($value)&&isset(westeros_content_kinds()[$value])&&westeros_kind_categories($value) ? $value : '';
}
function westeros_filter_content_query($query) {
    if (is_admin() || !$query->is_main_query() || $query->is_feed() || $query->is_embed() || (defined('REST_REQUEST')&&REST_REQUEST)) { return; }
    if (!($query->is_home()||$query->is_search()||$query->is_category()||$query->is_tag()||$query->is_author()||$query->is_date())) { return; }
    $kind=westeros_selected_content_kind($query->get('ws_kind'));
    if (!$kind) { return; }
    $clauses=array('relation'=>'AND');
    if ($existing=$query->get('tax_query')) { $clauses[]=$existing; }
    $clauses[]=array('taxonomy'=>'category','field'=>'term_id','terms'=>westeros_kind_categories($kind),'include_children'=>true);
    $query->set('tax_query', $clauses);
    $query->set('post_type', 'post');
}
add_action('pre_get_posts', 'westeros_filter_content_query');

function westeros_content_filters() {
    if (!(is_home()||is_search()||is_category()||is_tag()||is_author()||is_date())) { return; }
    $base=remove_query_arg(array('paged','page','ws_kind','ws_spoiler'), get_pagenum_link(1, false));
    $parts=wp_parse_url($base); $args=array();
    if (!empty($parts['query'])) { parse_str($parts['query'], $args); }
    $action=strtok($base, '?');
    echo '<form class="ws-kind-filter" method="get" action="'.esc_url($action).'">';
    foreach (array('s','cat','tag','author','year','monthnum','day','m','post_type') as $key) {
        if (isset($args[$key])&&is_scalar($args[$key])) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($args[$key]).'">'; }
    }
    echo '<label for="ws-content-kind">نوع مطلب</label><select id="ws-content-kind" name="ws_kind"><option value="">همهٔ مطالب</option>';
    $selected=westeros_selected_content_kind();
    foreach (westeros_content_kinds() as $key=>$label) {
        if (westeros_kind_categories($key)) { echo '<option value="'.esc_attr($key).'" '.selected($selected,$key,false).'>'.esc_html($label).'</option>'; }
    }
    echo '</select>';
    westeros_spoiler_filter_control();
    echo '<button type="submit">نمایش</button><a class="ws-dossier-directory-link" href="'.esc_url(get_post_type_archive_link('ws_dossier')).'">پرونده‌های موضوعی '.westeros_icon('arrow').'</a>';
    if (westeros_spoiler_filter_enabled()) { echo '<p id="ws-spoiler-filter-note" class="ws-spoiler-filter-note">فیلتر بر اساس برچسب تحریریه است؛ با انتخاب آن، مطالب نامشخص نمایش داده نمی‌شوند. دیدگاه‌ها وضعیت جداگانه دارند.</p>'; }
    echo '</form>';
}

/** Strict IDs, bounded queries; do not let an empty post__in return every post. */
function westeros_public_path_posts($ids) {
    $ids=westeros_path_ids($ids);
    if (!$ids) { return array(); }
    return get_posts(array('post_type'=>'post','post_status'=>'publish','has_password'=>false,'post__in'=>$ids,'orderby'=>'post__in','posts_per_page'=>40,'ignore_sticky_posts'=>true,'no_found_rows'=>true));
}
function westeros_path_ids($input) {
    if (is_string($input)) { $input=preg_split('/[\s,]+/', $input); }
    if (!is_array($input)) { return array(); }
    $ids=array();
    foreach ($input as $value) {
        if ((is_int($value)||is_string($value))&&preg_match('/^[1-9][0-9]*$/', (string)$value)) { $ids[]=absint($value); }
    }
    return array_slice(array_values(array_unique($ids)), 0, 40);
}
function westeros_update_dossier_items($id, $input) {
    if (get_post_type($id)!=='ws_dossier') { return; }
    $ids=array_map(static fn($p)=>(int)$p->ID, westeros_public_path_posts($input));
    update_post_meta($id, '_ws_dossier_post_ids', $ids);
    delete_post_meta($id, '_ws_dossier_member');
    foreach ($ids as $member) { add_post_meta($id, '_ws_dossier_member', $member); }
}
function westeros_dossier_items($id) {
    if (get_post_type($id)!=='ws_dossier') { return array(); }
    return westeros_public_path_posts(get_post_meta($id, '_ws_dossier_post_ids', true));
}
function westeros_dossier_image_id($id, $items=null) {
    if (has_post_thumbnail($id)) { return get_post_thumbnail_id($id); }
    $items=$items ?? westeros_dossier_items($id);
    foreach ($items as $item) { if (has_post_thumbnail($item->ID)) { return get_post_thumbnail_id($item->ID); } }
    return 0;
}
function westeros_render_dossier_card($id) {
    $protected=post_password_required($id);$items=$protected?array():westeros_dossier_items($id); $image=$protected?0:westeros_dossier_image_id($id,$items);
    echo '<article class="ws-dossier-card"><a class="ws-dossier-cover" href="'.esc_url(get_permalink($id)).'" tabindex="-1" aria-hidden="true">';
    if ($image) { echo wp_get_attachment_image($image,'slider',false,array('loading'=>'lazy','decoding'=>'async','alt'=>'')); }
    else { echo '<span class="ws-placeholder">'.westeros_icon('book').'</span>'; }
    echo '</a><div class="ws-dossier-copy"><span class="ws-dossier-kicker">'.westeros_icon('book').' '.($protected?'دارای گذرواژه':esc_html(number_format_i18n(count($items))).' مطلب در مسیر مطالعه').'</span><h2><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></h2>';
    $excerpt=$protected?'':get_post_field('post_excerpt',$id);
    if ($excerpt) { echo '<p>'.esc_html(wp_trim_words(wp_strip_all_tags($excerpt),28)).'</p>'; }
    echo '<a class="ws-more" href="'.esc_url(get_permalink($id)).'">گشودن پرونده '.westeros_icon('arrow').'</a></div></article>';
}
function westeros_home_dossiers() {
    if (!westeros_discovery_options()['show_home']) { return; }
    $dossiers=get_posts(array('post_type'=>'ws_dossier','post_status'=>'publish','has_password'=>false,'posts_per_page'=>3,'meta_key'=>'_ws_dossier_featured','meta_value'=>'1','orderby'=>array('menu_order'=>'ASC','title'=>'ASC','ID'=>'ASC'),'no_found_rows'=>true));
    $dossiers=array_filter($dossiers,static fn($d)=>count(westeros_dossier_items($d->ID))>0);
    if (!$dossiers) { return; }
    echo '<section class="ws-section ws-home-dossiers" aria-labelledby="ws-dossiers-heading"><div class="ws-section-heading"><h2 id="ws-dossiers-heading"><span class="ws-section-seal" aria-hidden="true">'.westeros_icon('book').'</span>پرونده‌های وستروس</h2><a class="ws-archive-link" href="'.esc_url(get_post_type_archive_link('ws_dossier')).'">همهٔ پرونده‌ها '.westeros_icon('arrow').'</a></div><p class="ws-dossier-intro">مجموعه‌های منتخب از آرشیو؛ یک موضوع، یک مسیر مطالعه</p><div class="ws-dossier-grid ws-dossier-grid-home">';
    foreach ($dossiers as $dossier) { westeros_render_dossier_card($dossier->ID); }
    echo '</div></section>';
}
function westeros_dossier_read_url($post_id,$dossier_id) {
    return add_query_arg('ws_path',absint($dossier_id),get_permalink($post_id));
}
function westeros_post_reading_path($post_id) {
    if (get_post_status($post_id)!=='publish'||post_password_required($post_id)) { return array(); }
    $requested=get_query_var('ws_path',0);
    $requested=is_scalar($requested)?absint($requested):0;
    $args=array('post_type'=>'ws_dossier','post_status'=>'publish','has_password'=>false,'posts_per_page'=>1,'meta_key'=>'_ws_dossier_member','meta_value'=>absint($post_id),'orderby'=>array('menu_order'=>'ASC','title'=>'ASC','ID'=>'ASC'),'no_found_rows'=>true);
    if ($requested) {
        $selected=get_posts(array_merge($args,array('p'=>$requested)));
        if (!$selected) { $selected=get_posts($args); }
    } else { $selected=get_posts($args); }
    if (!$selected) { return array(); }
    $dossier=$selected[0];$items=westeros_dossier_items($dossier->ID);$ids=array_map(static fn($p)=>(int)$p->ID,$items);
    $position=array_search((int)$post_id,$ids,true);
    return $position===false?array():array('dossier'=>$dossier,'items'=>$items,'position'=>$position);
}
function westeros_reader_path($post_id) {
    $path=westeros_post_reading_path($post_id);
    if (!$path) { return; }
    $id=$path['dossier']->ID;$items=$path['items'];$position=$path['position'];
    echo '<section class="ws-reading-path" aria-label="مسیر مطالعهٔ این مجموعه"><div class="ws-path-heading"><div><span class="ws-dossier-kicker">این مطلب در پروندهٔ</span><h2><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></h2></div><span class="ws-path-position">'.esc_html(number_format_i18n($position+1).' از '.number_format_i18n(count($items))).'</span></div><nav class="ws-path-neighbours" aria-label="مطالب قبلی و بعدی این پرونده">';
    foreach (array(-1=>'مطلب قبلی در پرونده',1=>'مطلب بعدی در پرونده') as $offset=>$label) {
        if (!isset($items[$position+$offset])) { continue; }
        $item=$items[$position+$offset];
        echo '<a href="'.esc_url(westeros_dossier_read_url($item->ID,$id)).'"><span>'.esc_html($label).'</span><strong>'.esc_html(get_the_title($item)).'</strong>'; westeros_spoiler_badge($item->ID); echo '</a>';
    }
    echo '</nav><details class="ws-path-outline"><summary>فهرست این مسیر مطالعه</summary><ol>';
    foreach ($items as $item) {
        echo '<li><a href="'.esc_url(westeros_dossier_read_url($item->ID,$id)).'"'.((int)$item->ID===(int)$post_id?' aria-current="step"':'').'>'.esc_html(get_the_title($item)).'</a>'; westeros_spoiler_badge($item->ID); echo '</li>';
    }
    echo '</ol></details></section>';
}

add_action('wp_enqueue_scripts', function () {
    if (is_home()||is_archive()||is_search()||is_single()) {
        wp_enqueue_style('westeros-discovery',get_stylesheet_directory_uri().'/assets/discovery.css',array('westeros-library'),(string)filemtime(__DIR__.'/../assets/discovery.css'));
    }
}, 25);
add_action('update_option_westeros_discovery_options','westeros_purge_theme_cache');
add_action('save_post_ws_dossier','westeros_purge_theme_cache',30);
add_action('save_post_post',function($id){
    if (wp_is_post_revision($id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)) { return; }
    $member=get_posts(array('post_type'=>'ws_dossier','post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_ws_dossier_member','meta_value'=>absint($id),'no_found_rows'=>true));
    if ($member) { westeros_purge_theme_cache(); }
},40);
add_action('deleted_post',function($id,$post){
    if ($post->post_type==='ws_dossier') { westeros_purge_theme_cache();return; }
    if ($post->post_type!=='post') { return; }
    $member=get_posts(array('post_type'=>'ws_dossier','post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_ws_dossier_member','meta_value'=>absint($id),'no_found_rows'=>true));
    if ($member) { westeros_purge_theme_cache(); }
},40,2);
