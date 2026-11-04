<?php defined('ABSPATH') || exit; $url=get_permalink();$title=get_the_title(); ?>
<nav class="ws-native-share" aria-label="اشتراک‌گذاری مطلب"><span>هم‌رسانی این روایت</span>
<a href="<?php echo esc_url(add_query_arg(array('url'=>$url,'text'=>$title),'https://t.me/share/url')); ?>" target="_blank" rel="noopener noreferrer">تلگرام</a>
<a href="<?php echo esc_url(add_query_arg(array('url'=>$url,'text'=>$title),'https://twitter.com/intent/tweet')); ?>" target="_blank" rel="noopener noreferrer">توییتر</a>
<a href="<?php echo esc_url(add_query_arg('u',$url,'https://www.facebook.com/sharer/sharer.php')); ?>" target="_blank" rel="noopener noreferrer">فیسبوک</a>
</nav>
