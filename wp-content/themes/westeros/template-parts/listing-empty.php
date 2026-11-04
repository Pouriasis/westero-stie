<?php defined('ABSPATH') || exit; $search = is_search(); $blank = $search && trim(get_search_query(false)) === ''; ?>
<section class="ws-listing-empty" aria-labelledby="ws-empty-listing-title">
    <span class="ws-listing-empty-mark" aria-hidden="true"><?php echo westeros_icon($search ? 'search' : 'book'); ?></span>
    <h2 id="ws-empty-listing-title"><?php echo $blank ? 'دنبال کدام روایت می‌گردید؟' : ($search ? 'روایتی با این واژه‌ها پیدا نشد.' : 'هنوز مطلبی در این بخش منتشر نشده است.'); ?></h2>
    <p><?php echo $blank ? 'نام شخصیت، عنوان کتاب یا چند واژه از مطلب را در کادر بالا وارد کنید.' : ($search ? 'با واژه‌های کوتاه‌تر یا عنوان دیگری دوباره جست‌وجو کنید.' : 'می‌توانید روایت‌های دیگر را در صفحهٔ اصلی دنبال کنید.'); ?></p>
    <a class="ws-more" href="<?php echo esc_url(home_url('/')); ?>">بازگشت به صفحهٔ اصلی <?php echo westeros_icon('arrow'); ?></a>
</section>
