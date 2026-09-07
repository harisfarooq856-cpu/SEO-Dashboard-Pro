<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Search Console live fetch ──────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_sc_fetch', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month_key = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
    $date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
    $date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? '' ) );
    $site_url  = esc_url_raw( wp_unslash( $_POST['site_url'] ?? '' ) );
    if ( ! $report_id || ! $month_key ) seo_dash_json_error( 'Missing parameters.' );

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'sc' );
    $sc_creds    = null;

    if ( $integration ) {
        $sc_creds = $integration['credentials'];
    } else {
        // Fallback: use global integration pack (new system).
        if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
            require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
        }
        $global_assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
        $global_intg_id     = $global_assignments['global'] ?? '';
        if ( $global_intg_id ) {
            $global_intg = seo_dash_get_global_integration_by_id( $global_intg_id );
            if ( $global_intg && ! empty( $global_intg['gsc_json_key'] ) ) {
                $raw_json = function_exists( 'seo_dash_sec_decrypt' )
                    ? seo_dash_sec_decrypt( $global_intg['gsc_json_key'] )
                    : $global_intg['gsc_json_key'];
                $sc_creds = json_decode( $raw_json, true );
                // If site_url not provided in POST, pull from the pack.
                if ( ! $site_url && ! empty( $global_intg['gsc_site_url'] ) ) {
                    $site_url = esc_url_raw( $global_intg['gsc_site_url'] );
                }
            }
        }
    }

    if ( ! $sc_creds ) {
        SEO_Dash_Database::log_activity( 'sc_fetch_failed', 'error', 'No SC integration assigned.', 'report', $report_id );
        seo_dash_json_error( 'No Search Console integration assigned to this report.' );
    }

    if ( ! $site_url ) seo_dash_json_error( 'Missing site URL. Set it in the integration pack or provide it in the request.' );

    $token = seo_dash_get_google_token( $sc_creds, 'https://www.googleapis.com/auth/webmasters.readonly' );
    if ( ! $token ) {
        SEO_Dash_Database::log_activity( 'sc_fetch_failed', 'error', 'Google SC auth failed.', 'report', $report_id );
        seo_dash_json_error( 'Authentication failed.' );
    }

    $encoded = rawurlencode( $site_url );
    $url     = "https://searchconsole.googleapis.com/webmasters/v3/sites/{$encoded}/searchAnalytics/query";
    $body    = [ 'startDate' => $date_from, 'endDate' => $date_to, 'dimensions' => ['page'], 'rowLimit' => 2000 ];
    $data    = seo_dash_google_post( $url, $body, $token );
    if ( ! $data ) {
        SEO_Dash_Database::log_activity( 'sc_fetch_failed', 'error', "SC API returned no data for {$month_key}.", 'report', $report_id );
        seo_dash_json_error( 'Failed to fetch from Search Console.' );
    }

    $rows = [];
    foreach ( $data['rows'] ?? [] as $r ) {
        $rows[] = [
            'report_id'   => $report_id,
            'period_type' => 'monthly',
            'month_key'   => $month_key,
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'query'       => '',
            'page_url'    => sanitize_text_field( $r['keys'][0] ?? '' ),
            'clicks'      => intval( $r['clicks']      ?? 0 ),
            'impressions' => intval( $r['impressions'] ?? 0 ),
            'ctr'         => round( floatval( $r['ctr'] ?? 0 ) * 100, 4 ),
            'position'    => round( floatval( $r['position'] ?? 0 ), 2 ),
        ];
    }

    SEO_Dash_Database::clear_month( SEO_Dash_Database::$data_sc, $report_id, $month_key );
    $inserted = SEO_Dash_Database::insert_data_rows( SEO_Dash_Database::$data_sc, $rows );
    
    // Update titles for existing pages only (no auto-add)
    $sync_data = [];
    foreach ( $rows as $row ) {
        $sync_data[ $row['page_url'] ] = ''; // SC doesn't provide titles
    }
    seo_dash_auto_sync_fetched_urls( $report_id, $sync_data );
    // Pending URLs for admin confirmation
    $pending_sc1 = seo_dash_get_pending_page_urls( $report_id, $sync_data );

    SEO_Dash_Database::log_activity( 'sc_fetch_ok', 'success', "SC live fetch: {$inserted} rows for {$month_key}.", 'report', $report_id );
    seo_dash_json_success( [
        'inserted'    => $inserted,
        'pending_urls' => ( isset($pending_sc1) && ( ! empty( $pending_sc1['service'] ) || ! empty( $pending_sc1['blog'] ) ) ) ? $pending_sc1 : null,
    ], "Saved {$inserted} rows." );
} );

// ── Manual save ────────────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_sc_save', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month_key = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
    $raw       = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : [];
    if ( ! $report_id || ! $month_key ) seo_dash_json_error( 'Missing parameters.' );

    $rows = [];
    foreach ( $raw as $r ) {
        $rows[] = [
            'report_id'   => $report_id,
            'period_type' => 'monthly',
            'month_key'   => $month_key,
            'query'       => sanitize_text_field( $r['query']       ?? '' ),
            'page_url'    => sanitize_text_field( $r['page_url']    ?? '' ),
            'clicks'      => absint( $r['clicks']      ?? 0 ),
            'impressions' => absint( $r['impressions'] ?? 0 ),
            'ctr'         => seo_dash_float( $r['ctr']      ?? 0, 4 ),
            'position'    => seo_dash_float( $r['position'] ?? 0, 2 ),
        ];
    }
    if ( empty( $rows ) ) seo_dash_json_error( 'No rows.' );
    if ( ! empty( $_POST['replace'] ) ) SEO_Dash_Database::clear_month( SEO_Dash_Database::$data_sc, $report_id, $month_key );
    $n = SEO_Dash_Database::insert_data_rows( SEO_Dash_Database::$data_sc, $rows );
    SEO_Dash_Database::log_activity( 'sc_manual_save', 'success', "SC manual save: {$n} rows for {$month_key}.", 'report', $report_id );
    seo_dash_json_success( [ 'inserted' => $n ], "Saved {$n} rows." );
} );

// ── Clear month ────────────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_sc_clear_month', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month_key = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
    if ( ! $report_id || ! $month_key ) seo_dash_json_error( 'Missing data.' );
    SEO_Dash_Database::clear_month( SEO_Dash_Database::$data_sc, $report_id, $month_key );
    SEO_Dash_Database::log_activity( 'sc_month_cleared', 'warning', "SC data cleared for {$month_key}.", 'report', $report_id );
    seo_dash_json_success( null, 'Month cleared.' );
} );

function seo_dash_upsert_sc_row( array $row ): bool {
    global $wpdb;
    $tbl = SEO_Dash_Database::$data_sc;
    $existing_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$tbl} WHERE report_id=%d AND month_key=%s AND page_url=%s AND period_type=%s LIMIT 1",
        $row['report_id'], $row['month_key'], $row['page_url'], $row['period_type']
    ) );
    if ( $existing_id ) {
        return (bool) $wpdb->update( $tbl, [
            'clicks'      => $row['clicks'],
            'impressions' => $row['impressions'],
            'ctr'         => $row['ctr'],
            'position'    => $row['position'],
            'query'       => $row['query'],
            'date_from'   => $row['date_from'],
            'date_to'     => $row['date_to'],
        ], [ 'id' => intval( $existing_id ) ], [ '%d','%d','%f','%f','%s','%s','%s' ], [ '%d' ] );
    }
    return (bool) $wpdb->insert( $tbl, $row );
}

add_action( 'wp_ajax_seo_dash_sc_fetch_all_periods', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;

    $report_id    = intval( $_POST['report_id']   ?? 0 );
    $target_month = seo_dash_sanitize_month( wp_unslash( $_POST['target_month'] ?? '' ) );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
    
    if ( ! $target_month ) {
        $target_month = get_option( "seo_dash_active_month_{$report_id}_sc", '' );
        if ( ! $target_month || $target_month === 'None' ) {
            $target_month = date('Y-m');
        }
    }

    $sc_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_sc", [] );
    if ( ! is_array( $sc_type_map ) ) $sc_type_map = [];

    $selected_urls = isset($_POST['selected_urls']) && is_array($_POST['selected_urls']) ? array_map('esc_url_raw', $_POST['selected_urls']) : [];

    $filtered = [];
    foreach ( $sc_type_map as $url => $type ) {
        if ( ! $url ) continue;
        if ( !empty($selected_urls) && !in_array($url, $selected_urls, true) ) continue;
        $filtered[ $url ] = $type;
    }
    if ( empty( $filtered ) ) seo_dash_json_error( 'No valid SC URLs mapped. Add them via sitemap or pass valid URLs.' );

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'sc' );
    $sc_creds    = null;
    $site_url    = '';

    if ( $integration ) {
        $sc_creds = $integration['credentials'];
    } else {
        if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
            require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
        }
        $global_assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
        $global_intg_id     = $global_assignments['global'] ?? '';
        if ( $global_intg_id ) {
            $global_intg = seo_dash_get_global_integration_by_id( $global_intg_id );
            if ( $global_intg && ! empty( $global_intg['gsc_json_key'] ) ) {
                $raw_json = function_exists( 'seo_dash_sec_decrypt' ) ? seo_dash_sec_decrypt( $global_intg['gsc_json_key'] ) : $global_intg['gsc_json_key'];
                $sc_creds = json_decode( $raw_json, true );
                if ( ! empty( $global_intg['gsc_site_url'] ) ) {
                    $site_url = esc_url_raw( $global_intg['gsc_site_url'] );
                }
            }
        }
    }

    if ( ! $sc_creds ) seo_dash_json_error( 'No SC integration assigned to this report.' );
    if ( ! $site_url ) seo_dash_json_error( 'Missing site URL.' );

    $token = seo_dash_get_google_token( $sc_creds, 'https://www.googleapis.com/auth/webmasters.readonly' );
    if ( ! $token ) seo_dash_json_error( 'Authentication failed.' );

    $date_parts = explode('-', $target_month);
    $y = intval($date_parts[0]);
    $m = intval($date_parts[1]);
    $d1 = date('Y-m-01', mktime(0,0,0,$m,1,$y));
    $d_end = date('Y-m-t', mktime(0,0,0,$m,1,$y));

    if ( strtotime($d_end) > time() ) {
        $d_end = date('Y-m-d', time() - 2*86400); // SC lags behind
    }

    $overall_start = sanitize_text_field( wp_unslash( $_POST['overall_start'] ?? '' ) );
    if ( ! $overall_start || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $overall_start ) ) {
        $overall_start = '2020-01-01';
    }

    $periods = [
        '7d'      => [ 'start' => date('Y-m-d', strtotime($d_end . ' - 6 days')),  'end' => $d_end ],
        '30d'     => [ 'start' => date('Y-m-d', strtotime($d_end . ' - 29 days')), 'end' => $d_end ],
        '90d'     => [ 'start' => date('Y-m-d', strtotime($d_end . ' - 89 days')), 'end' => $d_end ],
        'overall' => [ 'start' => $overall_start, 'end' => $d_end ],
    ];

    $total_upserted = 0;
    $period_counts  = [];

    $encoded = rawurlencode( $site_url );
    $url     = "https://searchconsole.googleapis.com/webmasters/v3/sites/{$encoded}/searchAnalytics/query";

    foreach ( $periods as $period_key => $dates ) {
        $body    = [ 'startDate' => $dates['start'], 'endDate' => $dates['end'], 'dimensions' => ['page'], 'rowLimit' => 5000 ];
        $data    = seo_dash_google_post( $url, $body, $token );

        $done = 0;
        foreach ( $data['rows'] ?? [] as $r ) {
            $page_url = sanitize_text_field( $r['keys'][0] ?? '' );
            if ( ! $page_url ) continue;

            $row = [
                'report_id'   => $report_id,
                'period_type' => $period_key,
                'month_key'   => $target_month,
                'date_from'   => $dates['start'],
                'date_to'     => $dates['end'],
                'query'       => '',
                'page_url'    => $page_url,
                'clicks'      => intval( $r['clicks']      ?? 0 ),
                'impressions' => intval( $r['impressions'] ?? 0 ),
                'ctr'         => round( floatval( $r['ctr'] ?? 0 ) * 100, 4 ),
                'position'    => round( floatval( $r['position'] ?? 0 ), 2 ),
            ];
            if ( seo_dash_upsert_sc_row( $row ) ) $done++;
        }
        $total_upserted += $done;
        $period_counts[$period_key] = $done;
    }

    // Update titles for existing pages only (no auto-add new ones)
    if ( ! empty( $filtered ) ) {
        $sync_data2 = [];
        foreach ( $filtered as $url => $type ) {
            $sync_data2[$url] = ''; // Title not available in SC fetch
        }
        seo_dash_auto_sync_fetched_urls( $report_id, $sync_data2 );
        $pending_sc2 = seo_dash_get_pending_page_urls( $report_id, $sync_data2 );
    }

    $pending_sc2    = $pending_sc2 ?? [ 'service' => [], 'blog' => [] ];
    $has_pending_sc = ! empty( $pending_sc2['service'] ) || ! empty( $pending_sc2['blog'] );

    seo_dash_json_success( [
        'upserted'      => $total_upserted,
        'period_counts' => $period_counts,
        'pending_urls'  => $has_pending_sc ? $pending_sc2 : null,
    ], "Updated/saved {$total_upserted} rows." );
} );
