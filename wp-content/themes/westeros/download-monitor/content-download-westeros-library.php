<?php
/** Download Monitor supplies the object and retains all access checks at its endpoint. */
defined('ABSPATH') || exit;
if (!isset($dlm_download) || !$dlm_download instanceof DLM_Download) { return; }
$attributes['link_attributes']['class'][] = 'ws-download-button';
$attributes['link_attributes']['class'][] = 'dlm-no-xhr-download';
$attributes['link_attributes']['aria-label'] = 'دریافت '.$dlm_download->get_title();
do_action('dlm_template_content_before_link', $dlm_download, $attributes, __FILE__);
?>
<a <?php echo DLM_Utils::generate_attributes($attributes['link_attributes']); ?>>دریافت فایل <?php echo westeros_icon('arrow'); ?></a>
<?php do_action('dlm_template_content_after_link', $dlm_download, $attributes, __FILE__); ?>
