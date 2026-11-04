<?php
/** Load feature assets only where their public markup is present. */
defined('ABSPATH') || exit;

// Current browsers display native Unicode emoji without the frontend detection/polyfill.
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
remove_action('wp_print_styles', 'print_emoji_styles');

function westeros_html_has_download($html) {
    $endpoint = get_option('dlm_download_endpoint_value', 'download');
    $endpoint = is_string($endpoint) ? preg_quote(trim($endpoint, '/'), '~') : 'download';
    return (bool)preg_match('~(?:\b(?:download-link|download-button|dlm-download|download-monitor)\b|/(?:download|' . $endpoint . ')/[0-9]+|[?&](?:download_id|download)=\d+)~i', $html);
}
function westeros_contact_assets() {
    if (function_exists('wpcf7_enqueue_scripts') && !wp_script_is('contact-form-7', 'enqueued')) { wpcf7_enqueue_scripts(); }
    if (function_exists('wpcf7_enqueue_styles') && !wp_style_is('contact-form-7', 'enqueued')) { wpcf7_enqueue_styles(); }
    // The plugin has already registered its active CAPTCHA integration and its inline data.
    if (wp_script_is('wpcf7-recaptcha', 'registered')) { wp_enqueue_script('wpcf7-recaptcha'); }
}
function westeros_download_assets() {
    $GLOBALS['westeros_download_markup'] = true;
    if (wp_script_is('dlm-xhr', 'registered')) { wp_enqueue_script('dlm-xhr'); }
}
add_action('wp_enqueue_scripts', function () {
    $post = is_singular() ? get_queried_object() : null;
    $content = $post instanceof WP_Post ? $post->post_content : '';
    $contact = has_shortcode($content, 'contact-form-7') || has_block('contact-form-7/contact-form-selector', $content);
    if (!$contact) {
        foreach (array('contact-form-7','wpcf7-recaptcha','google-recaptcha') as $handle) { wp_dequeue_script($handle); }
        foreach (array('contact-form-7','contact-form-7-rtl') as $handle) { wp_dequeue_style($handle); }
    }
    $download = westeros_html_has_download($content);
    foreach (array('download','downloads','download_page','westeros_download_library','download_data','dlm_no_access','wpdm_tree','wpdm_category','wpdm-all-packages','wpdm_button_template') as $tag) {
        $download = $download || has_shortcode($content, $tag);
    }
    if ($download) { westeros_download_assets(); }
    else { wp_dequeue_script('dlm-xhr'); }
}, 100);

// Late-rendered shortcodes/widgets can still request their complete dependency chain.
add_filter('do_shortcode_tag', function ($output, $tag) {
    if (is_admin()) { return $output; }
    if ($tag === 'contact-form-7') { westeros_contact_assets(); }
    if (in_array($tag,array('download','downloads','download_data','dlm_no_access'),true)) { westeros_download_assets(); }
    return $output;
}, 20, 2);
function westeros_content_assets($html) {
    if (is_admin()) { return $html; }
    if (str_contains($html, 'class="wpcf7') || str_contains($html, "class='wpcf7")) { westeros_contact_assets(); }
    if (westeros_html_has_download($html)) { westeros_download_assets(); }
    return $html;
}
add_filter('the_content', 'westeros_content_assets', 30);
add_filter('widget_text_content', 'westeros_content_assets', 30);
add_filter('widget_custom_html_content', 'westeros_content_assets', 30);
add_filter('widget_display_callback', function ($instance, $widget) {
    if (str_starts_with($widget->id_base, 'dlm_')) { westeros_download_assets(); }
    return $instance;
}, 20, 2);
add_action('wp_print_footer_scripts', function () {
    if (empty($GLOBALS['westeros_download_markup'])) { wp_dequeue_script('dlm-xhr'); }
}, 1);
