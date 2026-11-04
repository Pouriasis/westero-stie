<?php
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_theme_page('مدیریت اسپویل', 'مدیریت اسپویل', 'edit_theme_options', 'westeros-spoilers', 'westeros_spoiler_settings_screen');
});
add_action('admin_init', function () {
    register_setting('westeros_spoilers', 'westeros_spoiler_filter_ready', array('sanitize_callback'=>'westeros_clean_spoiler_readiness'));
});
add_filter('option_page_capability_westeros_spoilers', static fn()=>'edit_theme_options');
function westeros_clean_spoiler_readiness($value) {
    if ($value !== '1') { return '0'; }
    if (!westeros_spoiler_inventory()['labelled']) {
        add_settings_error('westeros_spoiler_filter_ready', 'ws-no-labelled-posts', 'هنوز مطلب عمومیِ برچسب‌خورده‌ای وجود ندارد؛ فیلتر فعال نشد.');
        return '0';
    }
    return '1';
}
function westeros_spoiler_settings_screen() {
    if (!current_user_can('edit_theme_options')) { return; }
    $stats = westeros_spoiler_inventory();
    echo '<div class="wrap"><h1>مدیریت اسپویل</h1><p>سطح و محدودهٔ هر مطلب را در بخش «اطلاعات تحریریهٔ وستروس» در ویرایش نوشته مشخص کنید. دسته‌بندی یا عنوان مطلب، مبنای تشخیص خودکار نیست.</p><p><strong>'.esc_html(number_format_i18n($stats['labelled'])).'</strong> مطلب برچسب‌خورده از '.esc_html(number_format_i18n($stats['total'])).' مطلب عمومی و بدون گذرواژه؛ '.esc_html(number_format_i18n($stats['unknown'])).' مطلب با وضعیت نامشخص.</p><p>برای اصلاح دیدگاه، از صفحهٔ ویرایش همان دیدگاه استفاده کنید. «بدون اسپویل» بودن مقاله، به دیدگاه‌ها منتقل نمی‌شود.</p>';
    settings_errors();
    echo '<form action="options.php" method="post">'; settings_fields('westeros_spoilers');
    echo '<input type="hidden" name="westeros_spoiler_filter_ready" value="0"><label><input type="checkbox" name="westeros_spoiler_filter_ready" value="1" '.checked(westeros_spoiler_filter_enabled(),true,false).'> تحریریه تأیید می‌کند که پوشش برچسب‌گذاری برای استفادهٔ خوانندگان کافی است؛ فیلتر اسپویل فعال شود.</label><p class="description">فیلتر فقط مطالب دارای برچسب صریح را نشان می‌دهد. مطالب نامشخص از نتیجهٔ فیلتر کنار می‌روند؛ حالت «همهٔ مطالب» همچنان آن‌ها را نمایش می‌دهد. این فیلتر بر اساس برچسب تحریریه است و تضمین بررسی متن، عنوان، تصویر یا فایل نیست.</p>';
    submit_button('ذخیرهٔ تنظیمات اسپویل'); echo '</form></div>';
}
add_filter('manage_post_posts_columns', function ($columns) { $columns['ws_spoiler'] = 'اسپویل'; return $columns; });
add_action('manage_post_posts_custom_column', function ($column, $id) {
    if ($column !== 'ws_spoiler') { return; }
    $data = westeros_spoiler_data($id);
    echo esc_html($data['label']);
    if ($data['scope']) { echo '<br><small>'.esc_html($data['scope']).'</small>'; }
}, 10, 2);

add_action('restrict_manage_posts', function ($type) {
    if ($type !== 'post') { return; }
    $selected = $_GET['ws_editorial_spoiler'] ?? 'all';
    echo '<label class="screen-reader-text" for="ws-editorial-spoiler">وضعیت اسپویل</label><select id="ws-editorial-spoiler" name="ws_editorial_spoiler"><option value="all">همهٔ وضعیت‌های اسپویل</option>';
    foreach (array('unknown'=>'تعیین نشده') + array_filter(westeros_spoiler_labels()) as $value=>$label) {
        if ($value === '') { continue; }
        echo '<option value="'.esc_attr($value).'" '.selected(is_string($selected)?$selected:'all',$value,false).'>'.esc_html($label).'</option>';
    }
    echo '</select>';
});
add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'post') { return; }
    $value = $_GET['ws_editorial_spoiler'] ?? 'all';
    if (!is_string($value) || ($value !== 'unknown' && !in_array($value, array('none','series','books','both'), true))) { return; }
    $clauses = array('relation'=>'AND');
    if ($existing = $query->get('meta_query')) { $clauses[] = $existing; }
    $clauses[] = $value === 'unknown' ? array('relation'=>'OR', array('key'=>'_westeros_spoiler_level','compare'=>'NOT EXISTS'), array('key'=>'_westeros_spoiler_level','value'=>array('none','series','books','both'),'compare'=>'NOT IN')) : array('key'=>'_westeros_spoiler_level','value'=>$value);
    $query->set('meta_query', $clauses);
}, 20);
