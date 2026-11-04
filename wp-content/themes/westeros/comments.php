<?php defined('ABSPATH') || exit; if(post_password_required())return; ?>
<div id="comments" class="ws-native-comments">
<?php if(have_comments()): ?>
    <h2 id="comments-title"><?php echo esc_html(number_format_i18n(get_comments_number())); ?> دیدگاه</h2>
    <p class="ws-comments-spoiler-note">دیدگاه‌های دارای برچسب اسپویل را فقط در صورت تمایل باز کنید.</p>
    <ol class="comment-list"><?php westeros_list_spoiler_comments(); ?></ol>
    <?php the_comments_pagination(array('prev_text'=>'دیدگاه‌های قبلی','next_text'=>'دیدگاه‌های بعدی')); ?>
<?php endif; ?>
<?php if(!comments_open()&&get_comments_number()): ?><p class="ws-comments-closed">ثبت دیدگاه تازه برای این مطلب بسته شده است.</p><?php endif; ?>
<?php comment_form(array('title_reply'=>'دیدگاه شما','title_reply_to'=>'پاسخ به %s','cancel_reply_link'=>'لغو پاسخ','label_submit'=>'ثبت دیدگاه','comment_notes_before'=>'<p class="comment-notes">نشانی ایمیل شما منتشر نمی‌شود. فیلدهای ضروری مشخص شده‌اند.</p>','comment_field'=>'<p class="comment-form-comment"><label for="comment">دیدگاه <span class="required">*</span></label><textarea id="comment" name="comment" rows="6" required></textarea></p><div class="ws-comment-preview-tools ws-js-control"><button type="button" class="ws-tool-button" data-comment-preview aria-expanded="false" aria-controls="ws-comment-preview">پیش‌نمایش دیدگاه</button><p id="ws-comment-preview" class="ws-comment-preview" hidden></p></div>')); ?>
</div>
