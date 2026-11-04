<?php
/** Editorial spoiler labels. Unknown content is never classified as safe. */
defined('ABSPATH') || exit;

function westeros_clean_spoiler_level($value) {
    return is_string($value) && array_key_exists($value, westeros_spoiler_labels()) ? $value : '';
}

function westeros_spoiler_data($id, $comment = false) {
    $getter = $comment ? 'get_comment_meta' : 'get_post_meta';
    $level = westeros_clean_spoiler_level($getter($id, '_westeros_spoiler_level', true));
    $scope = $getter($id, '_westeros_spoiler_scope', true);
    return array('level'=>$level, 'label'=>westeros_spoiler_labels()[$level], 'scope'=>$level !== '' && $level !== 'none' && is_string($scope) ? $scope : '');
}

function westeros_spoiler_badge($id) {
    if (get_post_type($id) !== 'post' || post_password_required($id)) { return; }
    $data = westeros_spoiler_data($id);
    if ($data['level'] === '') { return; }
    echo '<span class="ws-spoiler-badge ws-spoiler-level-'.esc_attr($data['level'] ?: 'unknown').'">'.westeros_icon('shield').'<span>اسپویل: '.esc_html($data['label']);
    if ($data['scope']) { echo '؛ '.esc_html($data['scope']); }
    echo '</span></span>';
}

/** Use the native walker, replies, moderation and pagination without filtering feeds/REST. */
function westeros_list_spoiler_comments() {
    add_filter('comment_text', 'westeros_comment_spoiler_text', 99, 2);
    add_filter('get_comment_date', 'westeros_comment_date', 10, 3);
    add_filter('get_comment_time', 'westeros_comment_time', 10, 4);
    try { wp_list_comments(array('style'=>'ol', 'short_ping'=>true, 'avatar_size'=>44)); }
    finally {
        remove_filter('comment_text', 'westeros_comment_spoiler_text', 99);
        remove_filter('get_comment_date', 'westeros_comment_date', 10);
        remove_filter('get_comment_time', 'westeros_comment_time', 10);
    }
}

function westeros_comment_spoiler_text($text, $comment = null) {
    $comment = get_comment($comment);
    if (!$comment || !in_array($comment->comment_type, array('', 'comment'), true)) { return $text; }
    $data = westeros_spoiler_data($comment->comment_ID, true);
    if ($data['level'] === '') { return $text; }
    $label = 'اسپویل دیدگاه: '.$data['label'];
    if ($data['scope']) { $label .= '؛ '.$data['scope']; }
    if ($data['level'] === 'none') {
        return '<p class="ws-comment-spoiler-label">'.esc_html($label).'</p>'.$text;
    }
    return '<details class="ws-spoiler ws-comment-spoiler"><summary>'.esc_html($label.' — نمایش دیدگاه').'</summary><div class="ws-spoiler-body">'.$text.'</div></details>';
}

function westeros_spoiler_fields($level = '', $scope = '', $prefix = 'ws-comment') {
    echo '<fieldset class="ws-comment-spoiler-fields"><legend>وضعیت اسپویل دیدگاه</legend><p><label for="'.esc_attr($prefix).'-level">سطح اسپویل</label><select id="'.esc_attr($prefix).'-level" name="westeros_spoiler_level">';
    foreach (westeros_spoiler_labels() as $value=>$label) {
        echo '<option value="'.esc_attr($value).'" '.selected($level,$value,false).'>'.esc_html($label).'</option>';
    }
    echo '</select></p><p><label for="'.esc_attr($prefix).'-scope">محدودهٔ رویدادها (اختیاری)</label><input type="text" id="'.esc_attr($prefix).'-scope" name="westeros_spoiler_scope" maxlength="160" value="'.esc_attr($scope).'" placeholder="مثلاً تا پایان فصل دوم"></p><p class="description">برچسب، اظهار نویسندهٔ دیدگاه است و مدیر می‌تواند آن را اصلاح کند. دیدگاه‌های دارای اسپویل، با انتخاب خواننده باز می‌شوند. پاسخ‌ها را هم جداگانه برچسب بزنید.</p></fieldset>';
}

function westeros_comment_spoiler_form() {
    wp_nonce_field('westeros_comment_spoiler', 'westeros_comment_spoiler_nonce', false);
    westeros_spoiler_fields();
}
add_action('comment_form_after_fields', 'westeros_comment_spoiler_form');
add_action('comment_form_logged_in_after', 'westeros_comment_spoiler_form');

function westeros_save_comment_spoiler($id) {
    $nonce = $_POST['westeros_comment_spoiler_nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce(wp_unslash($nonce), 'westeros_comment_spoiler')) { return; }
    $comment = get_comment($id);
    $post_id = $_POST['comment_post_ID'] ?? '';
    if (!$comment || !in_array($comment->comment_type, array('', 'comment'), true) || !is_scalar($post_id) || (int)$post_id !== (int)$comment->comment_post_ID) { return; }
    westeros_write_comment_spoiler($id);
}
add_action('comment_post', 'westeros_save_comment_spoiler');

function westeros_write_comment_spoiler($id) {
    $level = westeros_clean_spoiler_level(wp_unslash($_POST['westeros_spoiler_level'] ?? ''));
    $raw = $_POST['westeros_spoiler_scope'] ?? '';
    $scope = is_string($raw) ? mb_substr(sanitize_text_field(wp_unslash($raw)), 0, 160) : '';
    update_comment_meta($id, '_westeros_spoiler_level', $level);
    update_comment_meta($id, '_westeros_spoiler_scope', $level && $level !== 'none' ? $scope : '');
}

add_action('add_meta_boxes_comment', function () {
    add_meta_box('ws-comment-spoiler', 'اسپویل دیدگاه', function ($comment) {
        $data = westeros_spoiler_data($comment->comment_ID, true);
        wp_nonce_field('westeros_edit_comment_spoiler', 'westeros_edit_comment_spoiler_nonce');
        westeros_spoiler_fields($data['level'], $data['scope'], 'ws-edit-comment');
    }, 'comment', 'normal');
});
add_action('edit_comment', function ($id) {
    $nonce = $_POST['westeros_edit_comment_spoiler_nonce'] ?? '';
    if (!is_string($nonce) || !wp_verify_nonce(wp_unslash($nonce), 'westeros_edit_comment_spoiler') || !current_user_can('edit_comment', $id)) { return; }
    westeros_write_comment_spoiler($id);
});

function westeros_spoiler_filter_choices() {
    return array('none'=>'فقط بدون اسپویل', 'series'=>'بدون رویدادهای کتاب‌ها', 'books'=>'بدون رویدادهای سریال', 'labelled'=>'همهٔ مطالب برچسب‌خورده');
}
function westeros_spoiler_filter_enabled() {
    return get_option('westeros_spoiler_filter_ready', '0') === '1';
}
function westeros_selected_spoiler_filter($value = null) {
    $value = $value ?? get_query_var('ws_spoiler', '');
    return westeros_spoiler_filter_enabled() && is_string($value) && isset(westeros_spoiler_filter_choices()[$value]) ? $value : '';
}
function westeros_has_listing_filter() {
    return westeros_selected_content_kind() || westeros_selected_spoiler_filter();
}
add_filter('query_vars', function ($vars) { $vars[] = 'ws_spoiler'; return $vars; });

function westeros_filter_spoiler_query($query) {
    if (is_admin() || !$query->is_main_query() || $query->is_feed() || $query->is_embed() || $query->get('suppress_filters') || (defined('REST_REQUEST') && REST_REQUEST)) { return; }
    if (!($query->is_home() || $query->is_search() || $query->is_category() || $query->is_tag() || $query->is_author() || $query->is_date())) { return; }
    $selected = westeros_selected_spoiler_filter($query->get('ws_spoiler'));
    if (!$selected) { return; }
    $levels = array('none'=>array('none'), 'series'=>array('none','series'), 'books'=>array('none','books'), 'labelled'=>array('none','series','books','both'));
    $clauses = array('relation'=>'AND');
    if ($existing = $query->get('meta_query')) { $clauses[] = $existing; }
    $clauses[] = array('key'=>'_westeros_spoiler_level', 'value'=>$levels[$selected], 'compare'=>'IN');
    $query->set('meta_query', $clauses);
    $query->set('post_type', 'post');
    $query->set('has_password', false);
}
add_action('pre_get_posts', 'westeros_filter_spoiler_query', 15);

function westeros_spoiler_filter_control() {
    if (!westeros_spoiler_filter_enabled()) { return; }
    echo '<label for="ws-spoiler-filter">اسپویل</label><select id="ws-spoiler-filter" name="ws_spoiler" aria-describedby="ws-spoiler-filter-note"><option value="">همهٔ مطالب، شامل نامشخص‌ها</option>';
    foreach (westeros_spoiler_filter_choices() as $value=>$label) {
        echo '<option value="'.esc_attr($value).'" '.selected(westeros_selected_spoiler_filter(),$value,false).'>'.esc_html($label).'</option>';
    }
    echo '</select>';
}

/** A live, admin-only inventory; no public scan and no inferred labels. */
function westeros_spoiler_inventory() {
    global $wpdb;
    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' AND post_password=''");
    $labelled = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_type='post' AND p.post_status='publish' AND p.post_password='' AND m.meta_key=%s AND m.meta_value IN ('none','series','books','both')", '_westeros_spoiler_level'));
    return array('total'=>$total, 'labelled'=>$labelled, 'unknown'=>max(0,$total-$labelled));
}

if (is_admin()) { require_once __DIR__.'/spoilers-admin.php'; }

function westeros_adjacent_spoiler_badge($output, $format, $link, $post) {
    if (!is_singular('post') || !$post instanceof WP_Post) { return $output; }
    ob_start(); westeros_spoiler_badge($post->ID); $badge = ob_get_clean();
    return str_replace('</a>', $badge.'</a>', $output);
}
add_filter('previous_post_link', 'westeros_adjacent_spoiler_badge', 10, 4);
add_filter('next_post_link', 'westeros_adjacent_spoiler_badge', 10, 4);
