<?php defined('ABSPATH') || exit; ?>
    </div><!-- #main-content -->
    <?php
    $has_footer_widgets = false;
    foreach (array('first-footer-widget-area', 'second-footer-widget-area', 'third-footer-widget-area', 'fourth-footer-widget-area') as $area) {
        $has_footer_widgets = $has_footer_widgets || is_active_sidebar($area);
    }
    if ($has_footer_widgets && westeros_option('footer_widgets') !== 'disable') { get_sidebar('footer'); }
    ?>
    <footer class="ws-footer">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="ws-footer-brand"><?php bloginfo('name'); ?></a>
        <p class="ws-footer-copyright"><?php
            $copyright = westeros_option('footer_one');
            if ($copyright) {
                echo wp_kses_post(str_replace(array('%year%', '%site%', '%url%'), array(wp_date('Y'), get_bloginfo('name'), home_url('/')), htmlspecialchars_decode($copyright)));
            } else {
                echo esc_html('تمامی حقوق برای وبگاه وستروس محفوظ است. © ' . wp_date('Y'));
            }
        ?></p>
        <div class="ws-footer-tools">
            <a href="<?php echo esc_url(get_feed_link()); ?>" aria-label="خوراک مطالب وستروس"><?php echo westeros_icon('rss'); ?></a>
            <a class="ws-back-top" href="#top" aria-label="بازگشت به بالای صفحه"><?php echo westeros_icon('up'); ?></a>
        </div>
    </footer>
</div><!-- .ws-shell -->
<dialog class="ws-saved-dialog" id="ws-saved-dialog" aria-labelledby="ws-saved-title" data-saved-dialog>
    <div class="ws-dialog-heading"><h2 id="ws-saved-title"><?php echo westeros_icon('bookmark'); ?> نشان‌شده‌های من</h2><button type="button" class="ws-tool-button" data-close-saved aria-label="بستن نشان‌شده‌ها"><?php echo westeros_icon('close'); ?></button></div>
    <p class="ws-dialog-note">این فهرست فقط در همین مرورگر نگهداری می‌شود.</p>
    <ul class="ws-saved-list" data-saved-list></ul>
    <p class="ws-saved-empty" data-saved-empty>هنوز روایتی نشان نکرده‌اید. در صفحهٔ هر مطلب، «نشان کردن» را بزنید.</p>
    <p class="ws-dialog-feedback" data-saved-feedback role="status" aria-live="polite"></p>
</dialog>
<?php wp_footer(); ?>
</body>
</html>
