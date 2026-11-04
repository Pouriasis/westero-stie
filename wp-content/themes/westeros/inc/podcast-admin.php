<?php
defined('ABSPATH') || exit;
add_action('admin_menu',function(){add_theme_page('رادیو وستروس','رادیو وستروس','edit_theme_options','westeros-podcast','westeros_podcast_settings_screen');});
add_action('admin_post_westeros_podcast_refresh',function(){
    if (!current_user_can('edit_theme_options') || !current_user_can('publish_posts')) { wp_die('اجازهٔ به‌روزرسانی پادکست را ندارید.',403); }
    check_admin_referer('westeros_podcast_refresh');
    westeros_refresh_podcast_feed(true);
    wp_safe_redirect(admin_url('themes.php?page=westeros-podcast'));exit;
});
function westeros_podcast_settings_screen() {
    if (!current_user_can('edit_theme_options')) {return;}
    $cache=westeros_podcast_cache();$status=(array)get_option('westeros_podcast_feed_status',array());
    echo '<div class="wrap"><h1>رادیو وستروس</h1><p>منبع: <a href="'.esc_url(westeros_podcast_feed_url()).'">فید Acast رادیو وستروس</a></p><p>شناسهٔ RSS، هویت هر قسمت است. نوشته‌های قدیمی و دیدگاه‌ها حفظ می‌شوند؛ فقط برای قسمت‌های تازهٔ فید، نوشتهٔ جدید ساخته می‌شود. متن نوشته‌های قبلی بازنویسی نمی‌شود.</p>';
    if (!empty($cache['fetched_at'])) { echo '<p>آخرین دریافت موفق: '.esc_html(westeros_jalali_date($cache['fetched_at'], 'Y/m/d H:i')).'؛ '.esc_html(number_format_i18n(count($cache['episodes']))).' مورد در فید.</p>'; }
    if (!empty($status['error'])) { echo '<div class="notice notice-warning inline"><p>'.esc_html($status['error']).' دادهٔ آخرین دریافت موفق حفظ شده است.</p></div>'; }
    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) { echo '<p>WP-Cron در این محیط غیرفعال است؛ به‌روزرسانی ساعتی هنگام بازدید خانه یا صفحه‌های پادکست هم انجام می‌شود.</p>'; }
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="westeros_podcast_refresh">';wp_nonce_field('westeros_podcast_refresh');submit_button('دریافت دوبارهٔ فید');echo '</form><table class="widefat striped"><thead><tr><th>قسمت فید</th><th>صفحه و دیدگاه‌ها</th><th>شناسهٔ RSS</th></tr></thead><tbody>';
    foreach ($cache['episodes'] as $episode) { echo '<tr><td>'.esc_html($episode['title']).'</td><td><a href="'.esc_url(get_permalink($episode['post_id'])).'">نوشتهٔ '.esc_html($episode['post_id']).'</a> · '.esc_html(number_format_i18n(get_comments_number($episode['post_id']))).' دیدگاه</td><td><code>'.esc_html($episode['guid']).'</code></td></tr>'; }
    echo '</tbody></table></div>';
}
