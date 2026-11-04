<?php
/** Human-facing Solar Hijri dates. Storage and machine-readable dates stay Gregorian. */
defined('ABSPATH') || exit;

function westeros_persian_digits($value) {
    return strtr((string) $value, array('0'=>'۰', '1'=>'۱', '2'=>'۲', '3'=>'۳', '4'=>'۴', '5'=>'۵', '6'=>'۶', '7'=>'۷', '8'=>'۸', '9'=>'۹'));
}

/**
 * Gregorian-to-Iranian conversion, adapted from MediaWiki Language::tsToIranian.
 * Original algorithm: Roozbeh Pournader and Mohammad Toossi (LGPL);
 * PHP adaptation: Niklas Laxström. MediaWiki adaptation: GPL-2.0-or-later.
 * See wiki/includes/language/Language.php in this repository.
 */
function westeros_jalali_parts(DateTimeInterface $date) {
    $year = (int) $date->format('Y');
    $gy = $year - 1600;
    $gm = (int) $date->format('n') - 1;
    $days = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $day_number = 365 * $gy + (int) floor(($gy + 3) / 4) - (int) floor(($gy + 99) / 100) + (int) floor(($gy + 399) / 400);
    for ($i = 0; $i < $gm; $i++) { $day_number += $days[$i]; }
    if ($gm > 1 && ($year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0))) { $day_number++; }
    $day_number += (int) $date->format('j') - 80;

    $cycles = (int) floor($day_number / 12053);
    $day_number -= $cycles * 12053;
    $jy = 979 + 33 * $cycles + 4 * intdiv($day_number, 1461);
    $day_number %= 1461;
    if ($day_number >= 366) {
        $jy += intdiv($day_number - 1, 365);
        $day_number = ($day_number - 1) % 365;
    }
    $month_days = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
    for ($month = 0; $month < 11 && $day_number >= $month_days[$month]; $month++) {
        $day_number -= $month_days[$month];
    }
    return array($jy, $month + 1, $day_number + 1);
}

/**
 * Format a real Unix timestamp in the site's timezone; never use offset timestamps.
 * Display formats: j F Y (default), Y, or Y/m/d H:i.
 */
function westeros_jalali_date($timestamp = null, $format = 'j F Y', $timezone = null) {
    $date = (new DateTimeImmutable('@' . ($timestamp ?? time())))->setTimezone($timezone ?? wp_timezone());
    list($year, $month, $day) = westeros_jalali_parts($date);
    $months = array(1=>'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند');
    // Only the display formats used by this theme; no global wp_date/date_i18n filter.
    if ($format === 'Y') { return westeros_persian_digits($year); }
    if ($format === 'Y/m/d H:i') {
        return westeros_persian_digits(sprintf('%04d/%02d/%02d ', $year, $month, $day) . $date->format('H:i'));
    }
    return westeros_persian_digits($day . ' ' . $months[$month] . ' ' . $year);
}

function westeros_post_date($post = null, $field = 'date') {
    $date = get_post_datetime($post, $field);
    return $date ? westeros_jalali_date($date->getTimestamp()) : '';
}

/** Installed only while rendering the theme's native comment list. */
function westeros_comment_date($value, $format, $comment) {
    if ($format !== '' || !$comment) { return $value; }
    $date = date_create_immutable_from_format('!Y-m-d H:i:s', $comment->comment_date, wp_timezone());
    return $date ? westeros_jalali_date($date->getTimestamp()) : $value;
}

function westeros_comment_time($value, $format, $gmt, $translate) {
    return $format === '' && $translate ? westeros_persian_digits($value) : $value;
}

/** Existing date-archive URLs represent Gregorian ranges, displayed as Solar Hijri endpoints. */
function westeros_date_archive_title() {
    $compact = (string) get_query_var('m');
    $year = (int) get_query_var('year') ?: (int) substr($compact, 0, 4);
    $month = (int) get_query_var('monthnum') ?: (int) substr($compact, 4, 2);
    $day = (int) get_query_var('day') ?: (int) substr($compact, 6, 2);
    if (!checkdate($month ?: 1, $day ?: 1, $year)) { return 'آرشیو روایت‌ها'; }
    $start = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month ?: 1, $day ?: 1), wp_timezone());
    $label = westeros_jalali_date($start->getTimestamp());
    if (!$day) {
        $end = $start->modify($month ? '+1 month -1 day' : '+1 year -1 day');
        $label .= ' تا ' . westeros_jalali_date($end->getTimestamp());
    }
    return 'آرشیو ' . $label;
}
