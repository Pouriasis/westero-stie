<?php defined('ABSPATH') || exit; ?>
<form class="ws-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="ws-sr-only" for="<?php echo esc_attr($search_id = wp_unique_id('ws-search-')); ?>">جست‌وجو در وستروس</label>
    <input id="<?php echo esc_attr($search_id); ?>" type="search" name="s" placeholder="جست‌وجو در وستروس…" value="<?php echo esc_attr(get_search_query(false)); ?>">
    <?php if (is_search() && ($kind = westeros_selected_content_kind())) : ?>
    <input type="hidden" name="ws_kind" value="<?php echo esc_attr($kind); ?>">
    <?php endif; ?>
    <?php if (is_search() && ($spoiler = westeros_selected_spoiler_filter())) : ?>
    <input type="hidden" name="ws_spoiler" value="<?php echo esc_attr($spoiler); ?>">
    <?php endif; ?>
    <button type="submit" aria-label="جست‌وجو"><?php echo westeros_icon('search'); ?></button>
</form>
