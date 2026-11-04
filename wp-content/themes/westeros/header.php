<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script data-no-optimize="1" data-no-defer="1">document.documentElement.classList.add('ws-js');</script>
    <script data-no-optimize="1" data-no-defer="1">try{if(localStorage.getItem('westeros-reading-theme')==='night')document.documentElement.classList.add('ws-night-reading')}catch(e){}</script>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?> id="top">
<?php wp_body_open(); ?>
<a class="ws-skip-link" href="#main-content">رفتن به محتوای اصلی</a>
<div class="wrapper ws-shell">
    <header class="ws-header">
        <div class="ws-utility">
            <p class="ws-utility-note"><?php echo westeros_icon('quill'); ?> کتابخانهٔ وستروس <span>روایت‌های جهان یخ و آتش</span></p>
            <div class="ws-utility-actions">
                <button class="ws-appearance-toggle ws-js-control" type="button" data-reading-theme aria-pressed="false"><?php echo westeros_icon('moon'); ?><span data-theme-label>حالت شب</span></button>
                <button class="ws-saved-trigger ws-js-control" type="button" data-open-saved aria-haspopup="dialog" aria-controls="ws-saved-dialog"><?php echo westeros_icon('bookmark'); ?><span>نشان‌شده‌ها</span><span class="ws-saved-count" data-saved-count hidden></span></button>
                <?php get_search_form(); ?>
            </div>
        </div>
        <?php $logo = westeros_option('logo'); ?>
        <a class="ws-masthead" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name') . '؛ صفحهٔ اصلی'); ?>">
            <?php if ($logo) : ?>
                <?php westeros_masthead_image($logo); ?>
            <?php endif; ?>
            <span class="ws-brand-lockup">
                <span class="ws-brand-emblem" aria-hidden="true"><?php echo westeros_icon('crown'); ?></span>
                <span class="ws-brand-title"><?php bloginfo('name'); ?></span>
                <span class="ws-brand-latin" lang="en" dir="ltr">WESTEROS</span>
                <span class="ws-brand-description"><?php bloginfo('description'); ?></span>
            </span>
        </a>
        <?php if (is_home() && !is_paged() && !westeros_has_listing_filter() && westeros_option('on_home') === 'boxes') : ?><h1 class="ws-sr-only"><?php bloginfo('name'); ?> — <?php bloginfo('description'); ?></h1><?php endif; ?>
        <nav class="ws-nav" aria-label="منوی اصلی">
            <button class="ws-mobile-toggle" type="button" aria-expanded="false" aria-controls="ws-menu"><span>فهرست سایت</span><?php echo westeros_icon('menu'); ?></button>
            <?php wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'menu_id' => 'ws-menu', 'menu_class' => 'ws-menu', 'depth' => 3, 'fallback_cb' => 'westeros_menu_fallback', 'walker' => new Westeros_Menu_Walker())); ?>
        </nav>
    </header>
    <div id="main-content" class="container" tabindex="-1">
