<?php
defined('ABSPATH') || exit;
$id = get_the_ID();
$protected = post_password_required($id);
$type = get_post_type($id);
if ($type === 'post' && !$protected && has_category(westeros_podcast_category_id(),$id)) {
    $episodes=westeros_post_podcast_episodes($id);
    if ($episodes) { westeros_podcast_card($episodes[0]); return; }
}
$object = get_post_type_object($type);
$categories = $type === 'post' ? get_the_category($id) : array();
$type_label = $type === 'post' ? 'نوشته' : ($type === 'page' ? 'صفحه' : ($object->labels->singular_name ?? 'مطلب'));
?>
<article <?php post_class('ws-listing-card'); ?> aria-labelledby="ws-listing-title-<?php echo esc_attr($id); ?>">
    <a class="ws-listing-image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
        <?php if ($protected) : ?><span class="ws-placeholder"><?php echo westeros_icon('shield'); ?></span><?php else : westeros_image($id, 'slider'); endif; ?>
    </a>
    <div class="ws-listing-copy">
        <div class="ws-listing-kicker">
            <?php if ($categories) : ?><a href="<?php echo esc_url(get_category_link($categories[0])); ?>"><?php echo esc_html($categories[0]->name); ?></a>
            <?php else : ?><span><?php echo esc_html($type_label); ?></span><?php endif; ?>
            <?php if ($protected) : ?><span class="ws-listing-protected"><?php echo westeros_icon('shield'); ?> دارای گذرواژه</span><?php endif; ?>
        </div>
        <h2 id="ws-listing-title-<?php echo esc_attr($id); ?>"><a href="<?php the_permalink(); ?>"><?php echo esc_html(get_the_title()); ?></a></h2>
        <p class="ws-listing-excerpt"><?php if ($protected) { echo 'برای خواندن این مطلب، گذرواژه لازم است.'; } else { westeros_excerpt($id, 28); } ?></p>
        <div class="ws-listing-footer">
            <?php westeros_meta($id, $type === 'post'); ?>
            <a class="ws-listing-more" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr('خواندن ' . get_the_title()); ?>">خواندن <?php echo westeros_icon('arrow'); ?></a>
        </div>
    </div>
</article>
