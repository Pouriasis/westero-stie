<?php
/** Final reading-room polish: earlier font discovery and realistic image slots. */
defined('ABSPATH') || exit;

add_action('wp_head', function () {
    // Preload only the body face; bold and black remain demand-loaded via CSS.
    echo '<link rel="preload" href="' . esc_url(get_stylesheet_directory_uri() . '/assets/fonts/IRANSansWeb.woff2') . '" as="font" type="font/woff2" crossorigin>' . "\n";
}, 2);

/** Conservative slot widths; WordPress still supplies the existing image candidates. */
function westeros_image_sizes($context = 'card') {
    if ($context === 'small') { return '80px'; }
    if ($context === 'shelf') {
        return '(max-width: 400px) 82vw, (max-width: 767px) 44vw, (max-width: 960px) 30vw, 280px';
    }
    if ($context === 'hero') {
        $full = is_singular() && get_post_meta(get_queried_object_id(), 'tie_sidebar_pos', true) === 'full';
        return $full
            ? '(max-width: 767px) calc(100vw - 32px), (max-width: 1150px) calc(100vw - 78px), 1254px'
            : '(max-width: 767px) calc(100vw - 32px), (max-width: 960px) calc(100vw - 311px), (max-width: 1150px) calc(100vw - 347px), 932px';
    }
    return '(max-width: 767px) calc(100vw - 32px), (max-width: 960px) calc(100vw - 310px), 440px';
}

/** Use only registered attachment variants, retaining arbitrary configured logos. */
function westeros_masthead_image($url) {
    $attributes = array('alt' => '', 'aria-hidden' => 'true', 'fetchpriority' => 'high', 'loading' => 'eager', 'decoding' => 'async', 'data-no-lazy' => '1', 'class' => 'skip-lazy');
    $attachment = attachment_url_to_postid($url);
    if ($attachment) {
        // At small widths the original banner is cropped to a fixed 164px height.
        $attributes['sizes'] = '(max-width: 675px) 675px, (max-width: 767px) 100vw, (max-width: 1150px) calc(100vw - 32px), (max-width: 1384px) calc(100vw - 64px), 1320px';
        $image = wp_get_attachment_image($attachment, 'full', false, $attributes);
        if ($image) { echo $image; return; }
    }
    echo '<img src="' . esc_url($url) . '" width="1440" height="350" alt="" aria-hidden="true" fetchpriority="high" loading="eager" decoding="async" data-no-lazy="1" class="skip-lazy">';
}
