<?php
defined('ABSPATH') || exit;
if (!westeros_option('slider')) { return; }
// Preserve administrator-created custom sliders with the native renderer.
if (westeros_option('slider_query') === 'custom') {
    westeros_render_slides(westeros_slider_slides((int) westeros_option('slider_custom')), 'روایت‌های برگزیده');
    return;
}
$featured = westeros_featured_posts();
if (!$featured) { return; }
$briefs = array_slice($featured, 1, 3);
?>
<div class="ws-feature-composition<?php echo $briefs ? ' ws-has-briefs' : ''; ?>">
<section class="ws-feature-slider" data-feature-slider role="region" aria-roledescription="اسلایدر" aria-label="خبرهای برگزیده">
    <div class="ws-feature-slides">
        <?php foreach ($featured as $index => $item) : $category = get_the_category($item->ID); ?>
        <article class="ws-feature-slide" <?php echo $index ? 'hidden' : ''; ?> role="group" aria-roledescription="اسلاید" aria-label="<?php echo esc_attr(sprintf('%s از %s', number_format_i18n($index + 1), number_format_i18n(count($featured)))); ?>">
            <a class="ws-feature-link" href="<?php echo esc_url(get_permalink($item)); ?>" aria-labelledby="ws-feature-title-<?php echo esc_attr($index); ?>">
                <?php westeros_image($item->ID, 'full', $index === 0); ?>
            </a>
            <div class="ws-feature-caption">
                <?php if ($category) : ?><span class="ws-category-label"><?php echo esc_html($category[0]->name); ?></span><?php endif; ?>
                <h2 id="ws-feature-title-<?php echo esc_attr($index); ?>"><?php echo esc_html(get_the_title($item)); ?></h2>
                <?php westeros_meta($item->ID); ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php if (count($featured) > 1) : ?>
    <div class="ws-feature-controls">
        <button class="ws-control ws-js-control" type="button" data-feature-prev aria-label="خبر قبلی"><?php echo westeros_icon('right'); ?></button>
        <button class="ws-control ws-js-control" type="button" data-feature-next aria-label="خبر بعدی"><?php echo westeros_icon('left'); ?></button>
    </div>
    <div class="ws-feature-dots">
        <?php foreach ($featured as $index => $item) : ?>
        <button type="button" class="ws-js-control" data-feature-index="<?php echo esc_attr($index); ?>" aria-label="<?php echo esc_attr('نمایش خبر: ' . get_the_title($item)); ?>" <?php echo !$index ? 'aria-current="true"' : ''; ?>></button>
        <?php endforeach; ?>
    </div>
    <p class="ws-sr-only" aria-live="polite" aria-atomic="true" data-feature-status></p>
    <?php endif; ?>
</section>
<?php if ($briefs) : ?>
<section class="ws-feature-briefs" aria-labelledby="ws-briefs-title">
    <header class="ws-briefs-heading"><span aria-hidden="true"><?php echo westeros_icon('quill'); ?></span><h2 id="ws-briefs-title">تازه‌ترین روایت‌ها</h2></header>
    <div class="ws-feature-brief-list" tabindex="0" role="group" aria-label="خبرهای کوتاه">
        <?php foreach ($briefs as $item) : ?>
        <article class="ws-feature-brief">
            <a class="ws-card-image" href="<?php echo esc_url(get_permalink($item)); ?>" tabindex="-1" aria-hidden="true"><?php westeros_image($item->ID, 'tie-small'); ?></a>
            <div><h3><a href="<?php echo esc_url(get_permalink($item)); ?>"><?php echo esc_html(get_the_title($item)); ?></a></h3><?php westeros_meta($item->ID, false); ?></div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php $news = get_category_by_slug('news'); if ($news) : ?>
    <a class="ws-briefs-archive" href="<?php echo esc_url(get_category_link($news)); ?>">آرشیو خبرها <?php echo westeros_icon('arrow'); ?></a>
    <?php endif; ?>
</section>
<?php endif; ?>
</div>
