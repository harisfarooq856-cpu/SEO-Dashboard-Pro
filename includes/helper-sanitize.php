<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sanitize helpers used across the plugin.
 */

/**
 * Recursively sanitize an array of text fields.
 *
 * @param mixed $data
 * @return mixed
 */
function seo_dash_sanitize_recursive( $data ) {
    if ( is_array( $data ) ) {
        return array_map( 'seo_dash_sanitize_recursive', $data );
    }
    return is_string( $data ) ? sanitize_text_field( $data ) : $data;
}

/**
 * Sanitize a month key — must be YYYY-MM format.
 *
 * @param string $month_key
 * @return string  Empty string if invalid.
 */
function seo_dash_sanitize_month( string $month_key ): string {
    $month_key = preg_replace( '/[^\x20-\x7E]/', '', $month_key ); // strip non-ASCII / mojibake
    $month_key = trim( sanitize_text_field( $month_key ) );
    return preg_match( '/^\d{4}-\d{2}$/', $month_key ) ? $month_key : '';
}

/**
 * Parse a date value coming from a user-uploaded CSV (many possible formats,
 * including Excel serial date numbers) into a MySQL-safe 'Y-m-d' string.
 *
 * Returns '' if the value is empty or cannot be parsed (caller can decide
 * whether to fall back to today's date or leave the column NULL).
 *
 * @param mixed $raw
 * @return string  'Y-m-d' or ''.
 */
function seo_dash_parse_date_to_mysql( $raw ): string {
    $raw = trim( (string) $raw );
    if ( $raw === '' ) return '';

    // Already in MySQL format (allow optional time component).
    if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $raw, $m ) ) {
        $ts = strtotime( $m[1] );
        return $ts ? date( 'Y-m-d', $ts ) : '';
    }

    // Excel serial date number (e.g. 45292 = 2024-01-01). Excel's epoch is
    // 1899-12-30; only treat plausible ranges (years ~1970-2100) as serials.
    if ( ctype_digit( $raw ) && (int) $raw > 20000 && (int) $raw < 80000 ) {
        $ts = ( (int) $raw - 25569 ) * 86400; // 25569 = days between 1899-12-30 and 1970-01-01
        return date( 'Y-m-d', $ts );
    }

    // Common explicit formats — try day-first and month-first separately
    // so ambiguous dates like 03/04/2026 are handled consistently (day-first,
    // the most common convention outside the US, used by most SEO tool exports).
    $formats = [
        'd/m/Y', 'd-m-Y', 'd.m.Y',
        'm/d/Y', 'm-d-Y',
        'Y/m/d',
        'd M Y', 'd F Y',
        'M d, Y', 'M d Y', 'F d, Y', 'F d Y',
        'd/m/y', 'm/d/y', 'd-m-y', 'm-d-y',
    ];
    foreach ( $formats as $fmt ) {
        $dt = DateTime::createFromFormat( $fmt, $raw );
        if ( $dt instanceof DateTime ) {
            $errors = DateTime::getLastErrors();
            if ( empty( $errors['warning_count'] ) && empty( $errors['error_count'] ) ) {
                return $dt->format( 'Y-m-d' );
            }
        }
    }

    // Last resort — let PHP try to make sense of it.
    $ts = strtotime( $raw );
    return $ts ? date( 'Y-m-d', $ts ) : '';
}

/**
 * Sanitize a URL and return empty string if invalid.
 *
 * @param string $url
 * @return string
 */
function seo_dash_sanitize_url( string $url ): string {
    $clean = esc_url_raw( $url );
    return filter_var( $clean, FILTER_VALIDATE_URL ) ? $clean : '';
}

/**
 * Cast to a non-negative integer, return 0 if negative or non-numeric.
 *
 * @param mixed $val
 * @return int
 */
function seo_dash_absint( $val ): int {
    return absint( $val );
}

/**
 * Sanitize a decimal / float value.
 *
 * @param mixed $val
 * @param int   $decimals
 * @return float
 */
function seo_dash_float( $val, int $decimals = 2 ): float {
    return round( (float) $val, $decimals );
}

/**
 * Sanitize a status string against an allowlist.
 *
 * @param string   $value
 * @param string[] $allowed
 * @param string   $default
 * @return string
 */
function seo_dash_sanitize_status( string $value, array $allowed, string $default = '' ): string {
    return in_array( $value, $allowed, true ) ? $value : $default;
}

/**
 * Helper to parse date and time specifically for Click Tracking.
 * Handles combined strings like "2026-07-11 15:28:44", "07/11/2026 15:28:44",
 * as well as separate date and time parameters.
 *
 * @param string $raw_date
 * @param string $raw_time
 * @return array ['date' => 'YYYY-MM-DD', 'time' => 'HH:mm:ss']
 */
function seo_dash_parse_click_datetime( string $raw_date, string $raw_time = '' ): array {
    $raw_date = trim( $raw_date );
    $raw_time = trim( $raw_time );

    $date_val = '';
    $time_val = '';

    // If raw_date contains both date and time (e.g. "2026-07-11 15:28:44" or "2026-07-11T15:28:44")
    if ( $raw_date !== '' && ( strpos( $raw_date, ' ' ) !== false || strpos( $raw_date, 'T' ) !== false ) ) {
        $parts = preg_split( '/[ T]+/', $raw_date, 2 );
        $date_part = $parts[0] ?? '';
        $time_part = $parts[1] ?? '';

        $parsed_d = seo_dash_parse_date_to_mysql( $date_part );
        if ( $parsed_d ) {
            $date_val = $parsed_d;
        }
        if ( $time_part !== '' && $time_val === '' ) {
            $time_val = $time_part;
        }
    }

    if ( empty( $date_val ) && $raw_date !== '' ) {
        $date_val = seo_dash_parse_date_to_mysql( $raw_date );
    }

    if ( empty( $date_val ) ) {
        $date_val = current_time( 'Y-m-d' );
    }

    if ( $raw_time !== '' ) {
        $time_val = $raw_time;
    }

    if ( $time_val !== '' ) {
        $ts = strtotime( $time_val );
        if ( $ts !== false ) {
            $time_val = date( 'H:i:s', $ts );
        } else {
            $time_val = sanitize_text_field( $time_val );
        }
    }

    return [
        'date' => $date_val,
        'time' => $time_val,
    ];
}

