<?php
/** Download library presentation; Download Monitor continues to own file delivery and access. */
defined('ABSPATH') || exit;

function westeros_download_languages() {
    return array('fa'=>'فارسی', 'en'=>'انگلیسی', 'multi'=>'چندزبانه', 'unknown'=>'ثبت نشده');
}

/** Only the audited old site root and the site's download directories can be relocated. */
function westeros_download_source($source) {
    $result = array('state'=>'invalid', 'path'=>'', 'format'=>'', 'size'=>0);
    if (!is_string($source) || $source === '' || preg_match('/[\x00-\x1f\\\\]/', $source)) { return $result; }
    $source = rawurldecode($source);
    if (preg_match('~(?:^|/)\.\.(?:/|$)|[\x00-\x1f\\\\]~', $source)) { return $result; }
    $parts = wp_parse_url($source);
    if ($parts === false) { return $result; }
    $path = $parts['path'] ?? '';
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $allowed = array('pdf','epub','mobi','azw3','rar','zip','7z','cbr','cbz','mp3','mp4','flv','m4a','ogg','wav','srt','ass','txt','doc','docx','jpg','jpeg','png');
    $result['format'] = in_array($extension, $allowed, true) ? $extension : '';
    if (isset($parts['scheme'])) {
        if (!in_array(strtolower($parts['scheme']), array('http','https'), true) || !empty($parts['user']) || !empty($parts['pass'])) { return $result; }
        $host = strtolower($parts['host'] ?? '');
        $local_hosts = array(strtolower((string)wp_parse_url(home_url(), PHP_URL_HOST)), 'westeros.ir', 'www.westeros.ir');
        if (!in_array($host, $local_hosts, true)) { $result['state'] = 'remote'; return $result; }
    } elseif (str_starts_with($source, '/home/nginx/domains/westeros.ir/public/')) {
        $path = substr($source, strlen('/home/nginx/domains/westeros.ir/public/'));
    } elseif (str_starts_with(wp_normalize_path($source), wp_normalize_path(ABSPATH))) {
        $path = substr(wp_normalize_path($source), strlen(wp_normalize_path(ABSPATH)));
    } elseif (str_starts_with($source, '//')) { return $result; }
    $relative = ltrim($path, '/');
    if (!$result['format'] || !preg_match('~^(?:wp-content/uploads/(?:(?:downloads|dlm_uploads)/|[0-9]{4}/[0-9]{2}/)|downloads/|dl/)~', $relative)) { return $result; }
    $result['state'] = 'missing';
    $candidate = realpath(ABSPATH . $relative);
    if (!$candidate || !is_file($candidate) || !is_readable($candidate)) { return $result; }
    $candidate = wp_normalize_path($candidate);
    foreach (array('wp-content/uploads/', 'downloads/', 'dl/') as $directory) {
        $root = realpath(ABSPATH . $directory);
        if ($root && str_starts_with($candidate, trailingslashit(wp_normalize_path($root)))) {
            $result['state'] = 'local'; $result['path'] = $candidate;
            $result['size'] = (int)filesize($candidate);
            return $result;
        }
    }
    $result['state'] = 'invalid';
    return $result;
}

/** Resolve verified local mirrors at runtime, including percent-encoded names and the old root. */
add_filter('get_post_metadata', function ($value, $id, $key, $single) {
    if ($value !== null || is_admin()) { return $value; }
    if (!in_array($key,array('_files','_filesize'),true) || get_post_type($id) !== 'dlm_download_version') { return $value; }
    $meta = get_metadata_raw('post', $id); // No key: does not re-enter this alias.
    $stored = $meta['_files'][0] ?? null;
    if (!is_string($stored)) { return $value; }
    $mirrors = json_decode($stored, true);
    if (!is_array($mirrors)) { return $value; }
    if ($key === '_filesize') {
        if (count($mirrors) !== 1) { return $value; }
        $source = westeros_download_source(reset($mirrors));
        if ($source['state'] !== 'local') { return $value; }
        return $single ? (string)$source['size'] : array((string)$source['size']);
    }
    $changed = false;
    foreach ($mirrors as &$mirror) {
        if (!is_string($mirror)) { continue; }
        $source = westeros_download_source($mirror);
        if ($source['state'] === 'local' && $mirror !== $source['path']) { $mirror = $source['path']; $changed = true; }
    }
    unset($mirror);
    if (!$changed) { return $value; }
    $encoded = wp_json_encode($mirrors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $single ? $encoded : array($encoded);
}, 10, 4);

function westeros_download_language($id, $terms) {
    $explicit = get_post_meta($id, '_westeros_download_language', true);
    if (isset(westeros_download_languages()[$explicit])) { return $explicit; }
    // These are explicit language categories in the existing archive, never title guesses.
    $found = array();
    foreach ($terms as $term) {
        $slug = rawurldecode($term->slug);
        if (in_array($slug, array('کتابهای-فارسی','books-persian'), true)) { $found['fa'] = true; }
        if (in_array($slug, array('کتابهای-انگلیسی','en-pdf'), true)) { $found['en'] = true; }
    }
    return count($found) > 1 ? 'multi' : (key($found) ?: 'unknown');
}

/** Batched native queries, no remote probes or file hashing on public page requests. */
function westeros_download_catalogue() {
    $posts = get_posts(array('post_type'=>'dlm_download','post_status'=>'publish','has_password'=>false,'posts_per_page'=>-1,'orderby'=>array('date'=>'DESC','ID'=>'DESC')));
    if (!$posts) { return array(); }
    $ids = wp_list_pluck($posts, 'ID');
    $versions = get_posts(array('post_type'=>'dlm_download_version','post_status'=>'publish','post_parent__in'=>$ids,'posts_per_page'=>-1,'orderby'=>array('menu_order'=>'ASC','ID'=>'DESC')));
    $latest = array();
    foreach ($versions as $version) { if (!isset($latest[$version->post_parent])) { $latest[$version->post_parent] = $version; } }
    $rows = array();
    foreach ($posts as $post) {
        $terms = get_the_terms($post->ID, 'dlm_download_category');
        $terms = $terms && !is_wp_error($terms) ? $terms : array();
        $version = $latest[$post->ID] ?? null;
        $files = $version ? get_post_meta($version->ID, '_files', true) : array();
        if (is_string($files)) { $files = json_decode($files, true); }
        $files = is_array($files) ? array_filter($files, 'is_string') : array();
        $sources = array_map('westeros_download_source', $files);
        $states = array_column($sources, 'state');
        // Download Monitor randomly selects a mirror; promise availability only if every mirror is local.
        $state = $states && count(array_unique($states)) === 1 ? reset($states) : (in_array('remote', $states, true) ? 'remote' : 'missing');
        $formats = array_values(array_unique(array_filter(array_column($sources, 'format'))));
        $size = count($sources) === 1 && $state === 'local' ? reset($sources)['size'] : 0;
        $recorded_size = $version ? (int)get_post_meta($version->ID, '_filesize', true) : 0;
        $legacy_size = get_post_meta($post->ID,'filesize',true);
        $legacy_size = is_string($legacy_size) && preg_match('/^[0-9]+(?:\.[0-9]+)?\s*(?:B|kB|KB|MB|GB|TB)$/i',trim($legacy_size)) ? trim($legacy_size) : '';
        $rows[] = array('id'=>$post->ID,'title'=>$post->post_title,'description'=>wp_strip_all_tags(strip_shortcodes($post->post_excerpt ?: $post->post_content)), 'terms'=>$terms,'language'=>westeros_download_language($post->ID,$terms),'formats'=>$formats,'size'=>$size,'recorded_size'=>max(0,$recorded_size),'legacy_size'=>$legacy_size,'state'=>$state,'members'=>get_post_meta($post->ID,'_members_only',true)==='yes','purchasable'=>get_post_meta($post->ID,'_is_purchasable',true)==='1');
    }
    return $rows;
}

function westeros_download_request($key) {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
}

function westeros_download_selection($rows, $filters) {
    $words = preg_split('/\s+/u', trim(westeros_normalize_search_text($filters['q'])), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_filter($rows, function ($row) use ($filters, $words) {
        if ($filters['category'] && !in_array((int)$filters['category'], array_map('intval',wp_list_pluck($row['terms'],'term_id')), true)) { return false; }
        if ($filters['language'] && $filters['language'] !== $row['language']) { return false; }
        if ($filters['format'] && !in_array($filters['format'], $row['formats'], true)) { return false; }
        if ($filters['ready'] && $row['state'] !== 'local') { return false; }
        $haystack = westeros_normalize_search_text($row['title'].' '.$row['description']);
        foreach ($words as $word) { if (mb_stripos($haystack, $word, 0, 'UTF-8') === false) { return false; } }
        return true;
    }));
}

function westeros_download_card($row) {
    $languages = westeros_download_languages();
    echo '<article class="ws-download-card" data-download-id="'.(int)$row['id'].'"><div class="ws-download-card-top"><span class="ws-download-seal" aria-hidden="true">'.westeros_icon('book').'</span><div><p class="ws-download-format"><bdi>'.esc_html($row['formats'] ? strtoupper(implode(' / ', $row['formats'])) : 'فرمت ثبت نشده').'</bdi></p><h3><bdi>'.esc_html($row['title']).'</bdi></h3></div></div>';
    if ($row['description']) { echo '<p class="ws-download-description">'.esc_html(wp_trim_words($row['description'], 30, '…')).'</p>'; }
    $size_label = ($row['size'] ?: $row['recorded_size']) > 0 ? size_format($row['size'] ?: $row['recorded_size'], 2) : ($row['legacy_size'] ?: 'ثبت نشده');
    echo '<dl class="ws-download-meta"><div><dt>زبان</dt><dd>'.esc_html($languages[$row['language']]).'</dd></div><div><dt>'.($row['size'] > 0 ? 'حجم فایل' : 'حجم ثبت‌شده').'</dt><dd><bdi>'.esc_html($size_label).'</bdi></dd></div></dl>';
    if ($row['terms']) {
        echo '<p class="ws-download-categories">';
        foreach ($row['terms'] as $term) { echo '<span>'.esc_html($term->name).'</span>'; }
        echo '</p>';
    }
    echo '<div class="ws-download-action">';
    if (in_array($row['state'],array('missing','invalid'),true)) {
        echo '<p class="ws-download-unavailable">دریافت این فایل فعلاً در دسترس نیست.</p>';
    } else {
        if ($row['members']) { echo '<p>دریافت ویژهٔ اعضا</p>'; }
        if ($row['purchasable']) { echo '<p>شرایط دریافت در صفحهٔ دانلود</p>'; }
        if ($row['state'] === 'remote') { echo '<p>منبع بیرونی؛ دسترسی بررسی نشده است.</p>'; }
        echo do_shortcode('[download id="'.(int)$row['id'].'" template="westeros-library"]');
    }
    echo '</div></article>';
}

function westeros_download_library($attributes = array()) {
    $GLOBALS['westeros_download_library_rendered'] = true;
    if (!function_exists('download_monitor') || !post_type_exists('dlm_download')) { return '<p>کتابخانهٔ دانلود فعلاً در دسترس نیست.</p>'; }
    $rows = westeros_download_catalogue();
    $categories = array(); $formats = array();
    foreach ($rows as $row) {
        foreach ($row['terms'] as $term) { $categories[$term->term_id] = $term->name; }
        foreach ($row['formats'] as $format) { $formats[$format] = strtoupper($format); }
    }
    asort($categories); ksort($formats);
    $filters = array('q'=>mb_substr(westeros_download_request('ws_dl_q'),0,120),'category'=>absint(westeros_download_request('ws_dl_cat')),'language'=>westeros_download_request('ws_dl_lang'),'format'=>westeros_download_request('ws_dl_format'),'ready'=>westeros_download_request('ws_dl_ready')==='1');
    if (!isset($categories[$filters['category']])) { $filters['category'] = 0; }
    if (!isset(westeros_download_languages()[$filters['language']])) { $filters['language'] = ''; }
    if (!isset($formats[$filters['format']])) { $filters['format'] = ''; }
    $selected = westeros_download_selection($rows,$filters);
    $per_page = 12;
    $pages = max(1,(int)ceil(count($selected)/$per_page));
    $page = min($pages,max(1,absint(westeros_download_request('ws_dl_page'))));
    $base = get_permalink(get_queried_object_id());
    if (!$base) { $base = home_url('/download/'); }
    $instance = wp_unique_id('ws-download-library-');
    ob_start();
    echo '<section class="ws-download-library" id="'.esc_attr($instance).'" aria-label="کتابخانهٔ دانلود"><div class="ws-download-intro"><p class="ws-download-eyebrow">از آرشیو وستروس</p><h2>کتابخانهٔ دانلود</h2><p>کتاب‌ها، کمیک‌ها، فایل‌های صوتی و ویدئوهای آرشیو را بر اساس دسته، زبان و فرمت پیدا کنید.</p><p class="ws-download-total">'.esc_html(number_format_i18n(count($rows))).' عنوان در آرشیو</p></div>';
    echo '<form class="ws-download-filters" method="get" action="'.esc_url($base).'">';
    // Keep plain-permalink installations on the same page too.
    $base_query = wp_parse_url($base, PHP_URL_QUERY);
    if ($base_query) { parse_str($base_query,$base_args); foreach ($base_args as $key=>$value) { if (is_scalar($value)) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">'; } } }
    echo '<div class="ws-download-search"><label for="'.esc_attr($instance.'q').'">جست‌وجو در فایل‌ها</label><input type="search" id="'.esc_attr($instance.'q').'" name="ws_dl_q" maxlength="120" value="'.esc_attr($filters['q']).'" placeholder="نام کتاب یا فایل…"></div>';
    foreach (array('category'=>array('ws_dl_cat','دسته',$categories),'language'=>array('ws_dl_lang','زبان',westeros_download_languages()),'format'=>array('ws_dl_format','فرمت',$formats)) as $key=>$field) {
        echo '<div><label for="'.esc_attr($instance.$key).'">'.esc_html($field[1]).'</label><select id="'.esc_attr($instance.$key).'" name="'.esc_attr($field[0]).'"><option value="">همه</option>';
        foreach ($field[2] as $value=>$label) { echo '<option value="'.esc_attr($value).'"'.selected((string)$filters[$key],(string)$value,false).'>'.esc_html($label).'</option>'; }
        echo '</select></div>';
    }
    echo '<label class="ws-download-ready"><input type="checkbox" name="ws_dl_ready" value="1"'.checked($filters['ready'],true,false).'> فقط فایل‌های موجود</label><div class="ws-download-filter-actions"><button type="submit">نمایش فایل‌ها</button><a href="'.esc_url($base).'">پاک‌کردن فیلترها</a></div></form>';
    echo '<p class="ws-download-results" role="status">'.esc_html(number_format_i18n(count($selected))).' عنوان'.($pages > 1 ? '؛ صفحهٔ '.esc_html(number_format_i18n($page)).' از '.esc_html(number_format_i18n($pages)) : '').'</p>';
    if ($selected) {
        echo '<div class="ws-download-grid">';
        foreach (array_slice($selected,($page-1)*$per_page,$per_page) as $row) { westeros_download_card($row); }
        echo '</div>';
    } else { echo '<div class="ws-download-empty"><h3>فایلی با این مشخصات پیدا نشد.</h3><p>عبارت جست‌وجو یا فیلترها را تغییر دهید.</p><a href="'.esc_url($base).'">دیدن همهٔ فایل‌ها</a></div>'; }
    if ($pages > 1) {
        $args = array('ws_dl_q'=>$filters['q'],'ws_dl_cat'=>$filters['category'] ?: '', 'ws_dl_lang'=>$filters['language'],'ws_dl_format'=>$filters['format'],'ws_dl_ready'=>$filters['ready'] ? '1' : '');
        $links = paginate_links(array('base'=>add_query_arg('ws_dl_page','%#%',$base),'format'=>'','current'=>$page,'total'=>$pages,'type'=>'array','add_args'=>array_filter($args,'strlen'),'mid_size'=>1,'end_size'=>1,'prev_text'=>'صفحهٔ قبل','next_text'=>'صفحهٔ بعد'));
        if ($links) { echo '<nav class="ws-listing-pagination ws-download-pagination" aria-label="صفحه‌های دانلود"><ul>'; foreach ($links as $link) { echo '<li>'.$link.'</li>'; } echo '</ul></nav>'; }
    }
    echo '</section>';
    return ob_get_clean();
}

add_action('init',function () {
    add_shortcode('westeros_download_library','westeros_download_library');
    if (!shortcode_exists('download_page')) { add_shortcode('download_page','westeros_download_library'); }
}, 45);

add_action('wp_enqueue_scripts',function () {
    $post = is_singular() ? get_queried_object() : null;
    if ($post instanceof WP_Post && (has_shortcode($post->post_content,'download_page') || has_shortcode($post->post_content,'westeros_download_library'))) {
        wp_enqueue_style('westeros-downloads',get_stylesheet_directory_uri().'/assets/downloads.css',array('westeros-appearance'),(string)filemtime(get_stylesheet_directory().'/assets/downloads.css'));
    }
},30);

// Native endpoint navigation works without JS and does not buffer large files as browser blobs.
add_action('wp_print_footer_scripts',function () {
    if (!empty($GLOBALS['westeros_download_library_rendered'])) { wp_dequeue_script('dlm-xhr'); }
},2);

add_action('add_meta_boxes_dlm_download', function () {
    add_meta_box('westeros-download-language','زبان فایل',function ($post) {
        wp_nonce_field('westeros_download_language','westeros_download_language_nonce');
        $value = get_post_meta($post->ID,'_westeros_download_language',true);
        echo '<label for="ws-download-language">زبان محتوای فایل</label><select class="widefat" id="ws-download-language" name="westeros_download_language"><option value="">از دستهٔ زبان موجود</option>';
        foreach (westeros_download_languages() as $key=>$label) { echo '<option value="'.esc_attr($key).'"'.selected($value,$key,false).'>'.esc_html($label).'</option>'; }
        echo '</select><p>اگر زبان مشخص نیست، «ثبت نشده» را انتخاب کنید. زبان از عنوان حدس زده نمی‌شود.</p>';
    },'dlm_download','side');
});
add_action('save_post_dlm_download',function ($id) {
    if (wp_is_post_revision($id) || wp_is_post_autosave($id) || !current_user_can('edit_post',$id)
        || empty($_POST['westeros_download_language_nonce']) || !is_string($_POST['westeros_download_language_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['westeros_download_language_nonce'])),'westeros_download_language')) { return; }
    $value = isset($_POST['westeros_download_language']) && is_string($_POST['westeros_download_language']) ? sanitize_key(wp_unslash($_POST['westeros_download_language'])) : '';
    if ($value === '') { delete_post_meta($id,'_westeros_download_language'); }
    elseif (isset(westeros_download_languages()[$value])) { update_post_meta($id,'_westeros_download_language',$value); }
});

add_action('admin_menu',function () {
    add_theme_page('کتابخانهٔ دانلود','کتابخانهٔ دانلود','manage_options','westeros-download-library','westeros_download_library_admin');
});

function westeros_download_library_admin() {
    if (!current_user_can('manage_options')) { return; }
    echo '<div class="wrap"><h1>کتابخانهٔ دانلود</h1>';
    if (!function_exists('download_monitor')) { echo '<p>افزونهٔ Download Monitor در دسترس نیست.</p></div>'; return; }
    $rows = westeros_download_catalogue();
    $states = array_count_values(array_column($rows,'state'));
    $unknown = count(array_filter($rows,static fn($row)=>$row['language']==='unknown'));
    echo '<p>موجودبودن فایل‌ها در همین سرور بررسی می‌شود. منبع بیرونی بدون درخواست شبکه «بررسی نشده» می‌ماند. حجم ثبت‌شدهٔ آرشیو، تأیید موجودبودن فایل نیست.</p><p><strong>'.esc_html(number_format_i18n(count($rows))).'</strong> عنوان؛ <strong>'.esc_html(number_format_i18n($states['local']??0)).'</strong> فایل موجود؛ <strong>'.esc_html(number_format_i18n(($states['missing']??0)+($states['invalid']??0))).'</strong> فایل مفقود یا مسیر نامعتبر؛ <strong>'.esc_html(number_format_i18n($states['remote']??0)).'</strong> منبع بیرونی؛ <strong>'.esc_html(number_format_i18n($unknown)).'</strong> زبان ثبت‌نشده.</p>';
    $scope = westeros_download_request('ws_dl_audit_state');
    $scopes = array('issues'=>'مسیرهای نیازمند بررسی','unknown'=>'زبان ثبت‌نشده','local'=>'فایل‌های موجود','remote'=>'منابع بیرونی','all'=>'همهٔ عنوان‌ها');
    if (!isset($scopes[$scope])) { $scope = 'issues'; }
    echo '<form method="get" action="'.esc_url(admin_url('themes.php')).'"><input type="hidden" name="page" value="westeros-download-library"><label for="ws-dl-audit-state">نمایش </label><select id="ws-dl-audit-state" name="ws_dl_audit_state">';
    foreach ($scopes as $value=>$label) { echo '<option value="'.esc_attr($value).'"'.selected($scope,$value,false).'>'.esc_html($label).'</option>'; }
    echo '</select> <button type="submit" class="button">نمایش</button></form><p>برای اصلاح نشانی یا زبان، «ویرایش فایل» را باز کنید. دسته‌ها و نسخه‌های فایل در مدیریت دانلود قابل ویرایش‌اند؛ زبان فایل در کادر «زبان فایل» تنظیم می‌شود.</p>';
    $filtered = array_values(array_filter($rows,static function ($row) use ($scope) {
        if ($scope === 'all') { return true; }
        if ($scope === 'unknown') { return $row['language'] === 'unknown'; }
        if ($scope === 'issues') { return in_array($row['state'],array('missing','invalid','remote'),true); }
        return $row['state'] === $scope;
    }));
    $pages = max(1,(int)ceil(count($filtered)/25));
    $page = min($pages,max(1,absint(westeros_download_request('ws_dl_audit_page'))));
    $labels = array('local'=>'فایل موجود','missing'=>'فایل موجود نیست','invalid'=>'مسیر نامعتبر','remote'=>'منبع بیرونی؛ بررسی نشده');
    echo '<table class="widefat striped"><thead><tr><th scope="col">عنوان</th><th scope="col">وضعیت فایل</th><th scope="col">زبان</th><th scope="col">ویرایش</th></tr></thead><tbody>';
    foreach (array_slice($filtered,($page-1)*25,25) as $row) {
        echo '<tr><td><bdi>'.esc_html($row['title']).'</bdi></td><td>'.esc_html($labels[$row['state']]).'</td><td>'.esc_html(westeros_download_languages()[$row['language']]).'</td><td><a href="'.esc_url(get_edit_post_link($row['id'],'raw')).'">ویرایش فایل</a></td></tr>';
    }
    if (!$filtered) { echo '<tr><td colspan="4">موردی در این فهرست نیست.</td></tr>'; }
    echo '</tbody></table>';
    if ($pages > 1) { echo '<div class="tablenav"><div class="tablenav-pages">'.wp_kses_post(paginate_links(array('base'=>add_query_arg(array('page'=>'westeros-download-library','ws_dl_audit_state'=>$scope,'ws_dl_audit_page'=>'%#%'),admin_url('themes.php')),'format'=>'','current'=>$page,'total'=>$pages,'prev_text'=>'صفحهٔ قبل','next_text'=>'صفحهٔ بعد'))).'</div></div>'; }
    echo '</div>';
}
