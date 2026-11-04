<?php defined('ABSPATH') || exit; $context = westeros_collection_context(); ?>
<header class="ws-collection-header">
    <a class="ws-collection-home" href="<?php echo esc_url(home_url('/')); ?>">وستروس <span aria-hidden="true">/</span> <?php echo esc_html($context['eyebrow']); ?></a>
    <div class="ws-collection-heading">
        <span class="ws-collection-emblem" aria-hidden="true"><?php echo westeros_icon($context['icon']); ?></span>
        <h1><?php echo esc_html($context['title']); ?></h1>
    </div>
    <?php if (!is_search() || trim(get_search_query(false)) !== '') : ?>
    <div class="ws-collection-info"><span><?php echo esc_html(westeros_collection_count()); ?></span>
        <?php if (is_paged()) : ?><span>صفحهٔ <?php echo esc_html(number_format_i18n(max(1, (int) get_query_var('paged')))); ?></span><?php endif; ?>
        <?php if ($context['feed'] && !is_wp_error($context['feed'])) : ?><a href="<?php echo esc_url($context['feed']); ?>"><?php echo westeros_icon('rss'); ?> خوراک مطالب</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($context['description'] || $context['author']) : ?>
        <div class="ws-collection-description<?php echo $context['author'] ? ' ws-collection-author' : ''; ?>">
            <?php if ($context['author']) { echo get_avatar($context['author'], 56, '', '', array('class'=>'ws-collection-avatar')); } ?>
            <div><?php echo wp_kses_post($context['description']); ?><?php if ($context['author']) { westeros_author_public_links($context['author']); } ?></div>
        </div>
    <?php endif; ?>
    <?php if (is_search()) : ?><div class="ws-collection-search"><?php get_search_form(); ?></div><?php endif; ?>
    <?php westeros_collection_terms(); westeros_content_filters(); ?>
</header>
