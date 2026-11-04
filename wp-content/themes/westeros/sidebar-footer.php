<?php defined('ABSPATH') || exit;
$areas=array('first-footer-widget-area','second-footer-widget-area','third-footer-widget-area','fourth-footer-widget-area');$layout=westeros_option('footer_widgets');preg_match('/([1-4])c$/',(string)$layout,$match);$columns=(int)($match[1]??3);
?>
<div class="ws-footer-widgets" style="--ws-footer-columns:<?php echo esc_attr($columns); ?>" aria-label="ابزارک‌های پانوشت">
<?php foreach(array_slice($areas,0,$columns)as$area){if(is_active_sidebar($area)){echo'<div>';dynamic_sidebar($area);echo'</div>';}} ?>
</div>
