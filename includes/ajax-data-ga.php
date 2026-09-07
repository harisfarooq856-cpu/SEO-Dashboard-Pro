<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Guess content type from the page URL path.
 * Defined here so it's available without depending on ajax-sitemap.php load order.
 * The canonical copy lives in ajax-sitemap.php — keep in sync.
 */
if ( ! function_exists( 'seo_dash_guess_type_from_page_url' ) ) {
    function seo_dash_guess_type_from_page_url( string $url ): string {
        $path = strtolower( parse_url( $url, PHP_URL_PATH ) ?? '/' );
        $hints = [
            '/product/'   => 'product',
            '/shop/'      => 'product',
            '/blog/'      => 'post',
            '/news/'      => 'post',
            '/article/'   => 'post',
            '/category/'  => 'category',
            '/tag/'       => 'tag',
            '/author/'    => 'author',
            '/city/'      => 'city',
            '/location/'  => 'city',
            '/area/'      => 'city',
            '/service/'   => 'service',
            '/services/'  => 'service',
            '/portfolio/'  => 'portfolio',
        ];
        foreach ( $hints as $seg => $type ) {
            if ( strpos( $path, $seg ) !== false ) return $type;
        }
        $parts = array_filter( explode( '/', trim( $path, '/' ) ) );
        if ( count( $parts ) === 0 ) return 'home';
        if ( count( $parts ) === 1 ) return 'page';
        return 'post';
    }
}

/**
 * AJAX handlers — Google Analytics data
 */

/**
 * Strip non-ASCII / mojibake characters from a date string, then sanitize.
 * Ensures yyyy-MM-dd values coming from POST are clean before use.
 */
if ( ! function_exists( 'seo_dash_sanitize_date_field' ) ) {
    function seo_dash_sanitize_date_field( string $v ): string {
        $v = preg_replace( '/[^\x20-\x7E]/', '', $v ); // strip non-ASCII
        $v = sanitize_text_field( $v );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
    }
}

// ── Live-fetch from GA API ─────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_ga_fetch', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month_key = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
    $date_from = seo_dash_sanitize_date_field( (string) wp_unslash( $_POST['date_from'] ?? '' ) );
    $date_to   = seo_dash_sanitize_date_field( (string) wp_unslash( $_POST['date_to'] ?? '' ) );
    $property  = sanitize_text_field( wp_unslash( $_POST['property']  ?? '' ) );

    if ( ! $report_id || ! $month_key ) seo_dash_json_error( 'Missing report ID or month.' );
    if ( ! $date_from || ! $date_to )   seo_dash_json_error( 'Missing date range.' );

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'ga' );

    // Fallback: try global integration pack (new system).
    if ( ! $integration ) {
        if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
            require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
        }
        $global_assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
        $global_intg_id     = $global_assignments['global'] ?? '';
        if ( $global_intg_id ) {
            $global_intg = seo_dash_get_global_integration_by_id( $global_intg_id );
            if ( $global_intg && ! empty( $global_intg['ga4_json_key'] ) ) {
                $raw_json = function_exists( 'seo_dash_sec_decrypt' )
                    ? seo_dash_sec_decrypt( $global_intg['ga4_json_key'] )
                    : $global_intg['ga4_json_key'];
                $creds_array = json_decode( $raw_json, true );
                if ( ! $property && ! empty( $global_intg['ga4_property_id'] ) ) {
                    $property = sanitize_text_field( $global_intg['ga4_property_id'] );
                }
                $integration = [ 'credentials' => $creds_array ];
            }
        }
    }

    if ( ! $integration ) {
        SEO_Dash_Database::log_activity( 'ga_fetch_failed', 'error', 'No GA integration assigned.', 'report', $report_id );
        seo_dash_json_error( 'No Google Analytics integration assigned to this report.' );
    }

    $creds = is_array( $integration['credentials'] ) ? $integration['credentials'] : [];

    if ( empty( $creds ) ) seo_dash_json_error( 'Empty credentials for GA integration.' );

    $token = seo_dash_get_google_token( $creds, 'https://www.googleapis.com/auth/analytics.readonly' );
    if ( ! $token ) {
        SEO_Dash_Database::log_activity( 'ga_fetch_failed', 'error', 'Google auth failed.', 'report', $report_id );
        seo_dash_json_error( 'Could not authenticate with Google Analytics.' );
    }

    if ( ! $property ) seo_dash_json_error( 'Missing GA4 property ID. Set it in the integration pack or provide it in the request.' );

    // Save property ID for later background KPI fetches if it was provided
    update_option( "seo_dash_report_property_{$report_id}_ga", $property );
    $property = preg_replace('/^properties\//', '', $property);

    $url  = "https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport";
    $body = [
        'dateRanges'  => [ [ 'startDate' => $date_from, 'endDate' => $date_to ] ],
        'dimensions'  => [ [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ],
        'metrics'     => [
            [ 'name' => 'sessions' ], [ 'name' => 'activeUsers' ],
            [ 'name' => 'screenPageViews' ], [ 'name' => 'bounceRate' ],
        ],
        'limit'    => 500,
        'orderBys' => [ [ 'metric' => [ 'metricName' => 'sessions' ], 'desc' => true ] ],
    ];

    $data = seo_dash_google_post( $url, $body, $token );
    if ( ! $data ) {
        SEO_Dash_Database::log_activity( 'ga_fetch_failed', 'error', "GA API returned no data for {$month_key}.", 'report', $report_id );
        seo_dash_json_error( 'Failed to fetch data from Google Analytics.' );
    }

    $aggregated = [];
    foreach ( $data['rows'] ?? [] as $row ) {
        $dims    = $row['dimensionValues'] ?? [];
        $metrics = $row['metricValues']    ?? [];
        
        $u = $dims[0]['value'] ?? '';
        $t = $dims[1]['value'] ?? '';
        
        if (!isset($aggregated[$u])) {
            $aggregated[$u] = [
                'report_id'   => $report_id,
                'period_type' => 'monthly',
                'month_key'   => $month_key,
                'date_from'   => $date_from,
                'date_to'     => $date_to,
                'page_url'    => $u,
                'page_title'  => $t,
                'sessions'    => 0,
                'users'       => 0,
                'pageviews'   => 0,
                'bounces'     => 0,
            ];
        }
        $aggregated[$u]['sessions'] += intval( $metrics[0]['value'] ?? 0 );
        $aggregated[$u]['users']    += intval( $metrics[1]['value'] ?? 0 );
        $aggregated[$u]['pageviews']+= intval( $metrics[2]['value'] ?? 0 );
    }
    $rows = array_values($aggregated);

    SEO_Dash_Database::clear_month( SEO_Dash_Database::$data_ga, $report_id, $month_key );
    $inserted = SEO_Dash_Database::insert_data_rows( SEO_Dash_Database::$data_ga, $rows );
    
    // Update titles for existing pages (no auto-add)
    $sync_data = [];
    foreach ( $rows as $row ) {
        $sync_data[ $row['page_url'] ] = $row['page_title'];
    }
    seo_dash_auto_sync_fetched_urls( $report_id, $sync_data );

    // Collect NEW pending URLs for admin confirmation
    $pending = seo_dash_get_pending_page_urls( $report_id, $sync_data );
    $has_pending = ! empty( $pending['service'] ) || ! empty( $pending['blog'] );

    SEO_Dash_Database::log_activity(
        'ga_fetch_ok', 'success',
        "GA live fetch: {$inserted} rows saved for {$month_key}.",
        'report', $report_id
    );
    seo_dash_json_success( [
        'inserted'     => $inserted,
        'pending_urls' => $has_pending ? $pending : null,
    ], "Fetched and saved {$inserted} rows." );
} );

// ── Live-fetch OVERALL TRAFFIC from GA API (for Overview tab) ──────────────
add_action( 'wp_ajax_seo_dash_ga_fetch_overall', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $date_from = seo_dash_sanitize_date_field( (string) wp_unslash( $_POST['date_from'] ?? '' ) );
    $date_to   = seo_dash_sanitize_date_field( (string) wp_unslash( $_POST['date_to'] ?? '' ) );

    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
    if ( ! $date_from || ! $date_to ) seo_dash_json_error( 'Missing date range.' );

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'ga' );

    // Fallback: try global integration pack
    if ( ! $integration ) {
        if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
            require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
        }
        $global_assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
        $global_intg_id     = $global_assignments['global'] ?? '';
        if ( $global_intg_id ) {
            $global_intg = seo_dash_get_global_integration_by_id( $global_intg_id );
            if ( $global_intg && ! empty( $global_intg['ga4_json_key'] ) ) {
                $raw_json = function_exists( 'seo_dash_sec_decrypt' )
                    ? seo_dash_sec_decrypt( $global_intg['ga4_json_key'] )
                    : $global_intg['ga4_json_key'];
                $creds_array = json_decode( $raw_json, true );
                $property = sanitize_text_field( $global_intg['ga4_property_id'] );
                $integration = [ 'credentials' => $creds_array, 'property_id' => $property ];
            }
        }
    }

    if ( ! $integration ) seo_dash_json_error( 'No Google Analytics integration assigned to this report.' );

    $creds = is_array( $integration['credentials'] ) ? $integration['credentials'] : [];
    if ( empty( $creds ) ) seo_dash_json_error( 'Empty credentials for GA integration.' );

    $token = seo_dash_get_google_token( $creds, 'https://www.googleapis.com/auth/analytics.readonly' );
    if ( ! $token ) seo_dash_json_error( 'Could not authenticate with Google Analytics.' );

    $property = $integration['property_id'] ?? get_option( "seo_dash_report_property_{$report_id}_ga", '' );
    if ( ! $property ) seo_dash_json_error( 'Missing GA4 property ID.' );
    $property = preg_replace('/^properties\//', '', $property);

    $granularity_mode = sanitize_key( $_POST['granularity_mode'] ?? 'overall_months' );
    // 'overview' source = called from Overview tab → fetch totals only, no DB writes.
    $source = sanitize_key( $_POST['source'] ?? '' );
    $overview_only = ( $source === 'overview' );
    global $wpdb;
    $tbl = SEO_Dash_Database::$data_ga;
    $total_users = 0;
    $inserted_rows = 0;
    $today = date( 'Y-m-d' );
    // Use the end-date's month as the bucket for all preset period rows — matches
    // how the Database tab groups data (e.g. fetching Jan–Aug 2026 stores under 2026-08).
    $target_month = substr( $date_to, 0, 7 ); // yyyy-MM from date_to

    // 1. Fetch standard preset periods (7d, 30d, 90d, overall) for Admin UI table columns
    $preset_periods = [
        '7d'      => [ 'start' => date( 'Y-m-d', strtotime( '-7 days' ) ),  'end' => $today ],
        '30d'     => [ 'start' => date( 'Y-m-d', strtotime( '-30 days' ) ), 'end' => $today ],
        '90d'     => [ 'start' => date( 'Y-m-d', strtotime( '-90 days' ) ), 'end' => $today ],
        'overall' => [ 'start' => $date_from,                               'end' => $date_to ],
    ];

    $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport";

    $property_kpis = [];
    $ga_api_tested = false; // track whether we have confirmed connectivity
    foreach ( $preset_periods as $pk => $p_dates ) {
        // Site-wide Overview Query (No page dimension for exact site-wide deduplicated users)
        $overview_body = [
            'dateRanges' => [ [ 'startDate' => $p_dates['start'], 'endDate' => $p_dates['end'] ] ],
            'metrics'    => [ [ 'name' => 'activeUsers' ], [ 'name' => 'sessions' ] ],
        ];
        $ov_data = seo_dash_google_post( $url, $overview_body, $token );
        if ( ! $ga_api_tested ) {
            $ga_api_tested = true;
            if ( $ov_data === null ) {
                $api_err = function_exists( 'seo_dash_google_last_error' ) ? seo_dash_google_last_error() : '';
                seo_dash_json_error( 'Google Analytics API request failed' . ( $api_err ? ': ' . $api_err : '. Check your GA4 credentials and property ID.' ) );
            }
        }
        if ( ! empty( $ov_data['rows'][0]['metricValues'] ) ) {
            $ov_mets = $ov_data['rows'][0]['metricValues'];
            $property_kpis[$pk] = [
                'users'    => intval( $ov_mets[0]['value'] ?? 0 ),
                'sessions' => intval( $ov_mets[1]['value'] ?? 0 ),
            ];
        }

        // Overview-only mode: we already have the site-wide total from the overview query above.
        // Skip the per-page breakdown query and all DB writes entirely.
        if ( $overview_only ) {
            if ( $pk === 'overall' && ! empty( $property_kpis['overall']['users'] ) ) {
                $total_users = $property_kpis['overall']['users'];
            }
            continue;
        }

        $body = [
            'dateRanges' => [ [ 'startDate' => $p_dates['start'], 'endDate' => $p_dates['end'] ] ],
            'dimensions' => [ [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ],
            'metrics'    => [ [ 'name' => 'sessions' ], [ 'name' => 'activeUsers' ], [ 'name' => 'screenPageViews' ] ],
            'limit'      => 10000,
        ];
        $data = seo_dash_google_post( $url, $body, $token );
        if ( ! $data || empty( $data['rows'] ) ) continue;

        foreach ( $data['rows'] as $r ) {
            $dims = $r['dimensionValues'] ?? [];
            $mets = $r['metricValues'] ?? [];

            $p_path = $dims[0]['value'] ?? '';
            $p_ttl  = $dims[1]['value'] ?? '';
            if ( ! $p_path ) continue;

            $s_val = intval( $mets[0]['value'] ?? 0 );
            $u_val = intval( $mets[1]['value'] ?? 0 );
            $v_val = intval( $mets[2]['value'] ?? 0 );
            if ( $pk === 'overall' ) $total_users += $u_val;

            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$tbl} WHERE report_id = %d AND page_url = %s AND period_type = %s AND month_key = %s",
                $report_id, $p_path, $pk, $target_month
            ) );

            $row_data = [
                'report_id'   => $report_id,
                'period_type' => $pk,
                'month_key'   => $target_month,
                'date_from'   => $p_dates['start'],
                'date_to'     => $p_dates['end'],
                'page_url'    => $p_path,
                'page_title'  => $p_ttl ?: $p_path,
                'sessions'    => $s_val,
                'users'       => $u_val,
                'pageviews'   => $v_val,
                'imported_at' => current_time( 'mysql' ),
            ];

            if ( $existing_id ) {
                $wpdb->update( $tbl, $row_data, [ 'id' => $existing_id ] );
            } else {
                $wpdb->insert( $tbl, $row_data );
            }
        }
    }

    if ( ! empty( $property_kpis ) ) {
        update_option( "seo_dash_ga_kpis_{$report_id}", $property_kpis );
    }

    // Overview-only: return total users immediately, no granularity DB writes.
    if ( $overview_only ) {
        seo_dash_json_success( [ 'users' => $total_users, 'rows_saved' => 0 ], 'Fetched overview traffic total.' );
    }

    // 2. Fetch Month-by-Month or Day-by-Day data for backend AI chatbot storage
    if ( $granularity_mode === 'overall_months' || $granularity_mode === 'overall_days' ) {
        $dim_name = ( $granularity_mode === 'overall_days' ) ? 'date' : 'yearMonth';
        $body = [
            'dateRanges' => [ [ 'startDate' => $date_from, 'endDate' => $date_to ] ],
            'dimensions' => [ [ 'name' => $dim_name ], [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ],
            'metrics'    => [ [ 'name' => 'sessions' ], [ 'name' => 'activeUsers' ], [ 'name' => 'screenPageViews' ] ],
            'limit'      => 10000,
        ];
        $data = seo_dash_google_post( $url, $body, $token );

        foreach ( $data['rows'] ?? [] as $row ) {
            $dims = $row['dimensionValues'] ?? [];
            $mets = $row['metricValues'] ?? [];

            $raw_dim = $dims[0]['value'] ?? '';
            $p_url   = $dims[1]['value'] ?? '';
            $p_ttl   = $dims[2]['value'] ?? '';
            if ( ! $p_url || ! $raw_dim ) continue;

            $s_val = intval( $mets[0]['value'] ?? 0 );
            $u_val = intval( $mets[1]['value'] ?? 0 );
            $v_val = intval( $mets[2]['value'] ?? 0 );

            if ( $granularity_mode === 'overall_days' && strlen( $raw_dim ) === 8 ) {
                $d_from = substr( $raw_dim, 0, 4 ) . '-' . substr( $raw_dim, 4, 2 ) . '-' . substr( $raw_dim, 6, 2 );
                $d_to   = $d_from;
                $m_key  = substr( $raw_dim, 0, 4 ) . '-' . substr( $raw_dim, 4, 2 );
                $p_type = 'daily';
            } else {
                $m_key  = strlen( $raw_dim ) === 6 ? substr( $raw_dim, 0, 4 ) . '-' . substr( $raw_dim, 4, 2 ) : date( 'Y-m' );
                $d_from = "{$m_key}-01";
                $d_to   = date( 'Y-m-t', strtotime( $d_from ) );
                $p_type = 'monthly';
            }

            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$tbl} WHERE report_id = %d AND page_url = %s AND period_type = %s AND date_from = %s AND date_to = %s",
                $report_id, $p_url, $p_type, $d_from, $d_to
            ) );

            $row_data = [
                'report_id'   => $report_id,
                'period_type' => $p_type,
                'month_key'   => $m_key,
                'date_from'   => $d_from,
                'date_to'     => $d_to,
                'page_url'    => $p_url,
                'page_title'  => $p_ttl ?: $p_url,
                'sessions'    => $s_val,
                'users'       => $u_val,
                'pageviews'   => $v_val,
                'imported_at' => current_time( 'mysql' ),
            ];

            if ( $existing_id ) {
                $wpdb->update( $tbl, $row_data, [ 'id' => $existing_id ] );
            } else {
                $wpdb->insert( $tbl, $row_data );
            }
            $inserted_rows++;
        }
    }

    SEO_Dash_Database::log_activity( 'ga_fetch_overall_ok', 'success', "Fetched {$inserted_rows} rows across overall period ({$date_from} to {$date_to}).", 'report', $report_id );

    seo_dash_json_success( [ 'users' => $total_users, 'rows_saved' => $inserted_rows ], "Fetched and saved overall and monthly data across {$date_from} to {$date_to}." );
} );

// ── Manual save (paste / CSV import) ──────────────────────────────────────
add_action( 'wp_ajax_seo_dash_ga_save', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month_key = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
    $raw_rows  = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : [];

    if ( ! $report_id || ! $month_key ) seo_dash_json_error( 'Missing report ID or month.' );

    $rows = [];
    foreach ( $raw_rows as $r ) {
        $rows[] = [
            'report_id'   => $report_id,
            'period_type' => 'monthly',
            'month_key'   => $month_key,
            'date_from'   => sanitize_text_field( $r['date_from']  ?? '' ),
            'date_to'     => sanitize_text_field( $r['date_to']    ?? '' ),
            'page_url'    => sanitize_text_field( $r['page_url']   ?? '' ),
            'page_title'  => sanitize_text_field( $r['page_title'] ?? '' ),
            'sessions'    => absint( $r['sessions']  ?? 0 ),
            'users'       => absint( $r['users']     ?? 0 ),
            'pageviews'   => absint( $r['pageviews'] ?? 0 ),
            'bounces'     => absint( $r['bounces']   ?? 0 ),
        ];
    }

    if ( empty( $rows ) ) seo_dash_json_error( 'No rows provided.' );
    if ( ! empty( $_POST['replace'] ) ) SEO_Dash_Database::clear_month( SEO_Dash_Database::$data_ga, $report_id, $month_key );

    $inserted = SEO_Dash_Database::insert_data_rows( SEO_Dash_Database::$data_ga, $rows );
    SEO_Dash_Database::log_activity(
        'ga_manual_save', 'success',
        "GA manual save: {$inserted} rows for {$month_key}.",
        'report', $report_id
    );
    seo_dash_json_success( [ 'inserted' => $inserted ], "Saved {$inserted} rows." );
} );

// ── Trash / restore / delete individual rows ──────────────────────────────
add_action( 'wp_ajax_seo_dash_ga_row_action', function () {
    seo_dash_verify_admin_ajax();

    $action = sanitize_key( $_POST['row_action'] ?? '' );
    $id     = intval( $_POST['row_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing row ID.' );

    switch ( $action ) {
        case 'trash':
            SEO_Dash_Database::trash_row( SEO_Dash_Database::$data_ga, $id );
            SEO_Dash_Database::log_activity( 'ga_row_trashed', 'info', "GA row {$id} trashed." );
            break;
        case 'restore':
            SEO_Dash_Database::restore_row( SEO_Dash_Database::$data_ga, $id );
            SEO_Dash_Database::log_activity( 'ga_row_restored', 'info', "GA row {$id} restored." );
            break;
        case 'delete':
            SEO_Dash_Database::delete_row( SEO_Dash_Database::$data_ga, $id );
            SEO_Dash_Database::log_activity( 'ga_row_deleted', 'warning', "GA row {$id} permanently deleted." );
            break;
        default:
            seo_dash_json_error( 'Unknown action.' );
    }

    seo_dash_json_success( null, 'Done.' );
} );

// ── Clear all rows for a month ─────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_ga_clear_month', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month_key = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
    if ( ! $report_id || ! $month_key ) seo_dash_json_error( 'Missing data.' );
    SEO_Dash_Database::clear_month( SEO_Dash_Database::$data_ga, $report_id, $month_key );
    SEO_Dash_Database::log_activity( 'ga_month_cleared', 'warning', "GA data cleared for {$month_key}.", 'report', $report_id );
    seo_dash_json_success( null, 'Month cleared.' );
} );

// ── Fetch GA data for specific URLs via Overview panel ─────────────────────
// Strategy: fetch ALL pages for the date range then filter in PHP.
// This avoids inListFilter trailing-slash mismatches and redirect issues.
add_action( 'wp_ajax_seo_dash_ga_fetch_urls', function () {
    seo_dash_verify_admin_ajax();

    global $wpdb;

    $report_id   = intval( $_POST['report_id'] ?? 0 );
    $month_key   = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
    $date_from   = seo_dash_sanitize_date_field( (string) wp_unslash( $_POST['date_from'] ?? '' ) );
    $date_to     = seo_dash_sanitize_date_field( (string) wp_unslash( $_POST['date_to'] ?? '' ) );
    $property    = sanitize_text_field( wp_unslash( $_POST['property']  ?? '' ) );
    $raw_urls    = isset( $_POST['urls'] ) && is_array( $_POST['urls'] )
                   ? array_map( 'sanitize_text_field', wp_unslash( $_POST['urls'] ) )
                   : [];
    $raw_metrics = isset( $_POST['metrics'] ) && is_array( $_POST['metrics'] )
                   ? array_map( 'sanitize_key', wp_unslash( $_POST['metrics'] ) )
                   : [];

    if ( ! $report_id || ! $month_key ) seo_dash_json_error( 'Missing report ID or month.' );
    if ( ! $date_from || ! $date_to )   seo_dash_json_error( 'Missing date range.' );
    if ( empty( $raw_urls ) )           seo_dash_json_error( 'No URLs selected.' );

    /* ── Resolve integration ── */
    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'ga' );
    if ( ! $integration ) {
        if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
            require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
        }
        $global_assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
        $global_intg_id     = $global_assignments['global'] ?? '';
        if ( $global_intg_id ) {
            $g = seo_dash_get_global_integration_by_id( $global_intg_id );
            if ( $g && ! empty( $g['ga4_json_key'] ) ) {
                $raw_json    = function_exists( 'seo_dash_sec_decrypt' )
                               ? seo_dash_sec_decrypt( $g['ga4_json_key'] )
                               : $g['ga4_json_key'];
                $creds_array = json_decode( $raw_json, true );
                if ( ! $property && ! empty( $g['ga4_property_id'] ) ) {
                    $property = sanitize_text_field( $g['ga4_property_id'] );
                }
                $integration = [ 'credentials' => $creds_array ];
            }
        }
    }
    if ( ! $integration ) seo_dash_json_error( 'No Google Analytics integration assigned to this report.' );

    $creds = is_array( $integration['credentials'] ) ? $integration['credentials'] : [];
    if ( empty( $creds ) ) seo_dash_json_error( 'Empty credentials for GA integration.' );

    $token = seo_dash_get_google_token( $creds, 'https://www.googleapis.com/auth/analytics.readonly' );
    if ( ! $token ) seo_dash_json_error( 'Could not authenticate with Google Analytics.' );
    if ( ! $property ) seo_dash_json_error( 'Missing GA4 property ID.' );
    $property = preg_replace('/^properties\//', '', $property);

    /* ── Build path → full-URL map (all slash/case variants for robust matching) ── */
    $path_to_full = [];
    foreach ( $raw_urls as $full_url ) {
        if ( ! $full_url ) continue;
        $path       = parse_url( $full_url, PHP_URL_PATH ) ?: '/';
        $no_slash   = rtrim( $path, '/' ) ?: '/';
        $with_slash = ( $no_slash === '/' ) ? '/' : $no_slash . '/';

        // Register exact, no-trailing-slash, with-trailing-slash, and lowercase variants.
        $path_to_full[ $path ]                         = $full_url;
        $path_to_full[ $no_slash ]                     = $full_url;
        $path_to_full[ $with_slash ]                   = $full_url;
        $path_to_full[ strtolower( $path ) ]           = $full_url;
        $path_to_full[ strtolower( $no_slash ) ]       = $full_url;
        $path_to_full[ strtolower( $with_slash ) ]     = $full_url;
    }

    /* ── Build GA4 metrics array ── */
    $allowed = [
        'sessions'    => 'sessions',
        'activeusers' => 'activeUsers',
        'pageviews'   => 'screenPageViews',
    ];
    $api_metrics = [];
    foreach ( $raw_metrics as $m ) {
        if ( isset( $allowed[ $m ] ) ) {
            $api_metrics[] = [ 'name' => $allowed[ $m ] ];
        }
    }
    if ( empty( $api_metrics ) ) {
        $api_metrics = [
            [ 'name' => 'sessions' ],
            [ 'name' => 'activeUsers' ],
            [ 'name' => 'screenPageViews' ],
        ];
    }

    /* ── Fetch ALL pages for the date range, then filter in PHP ── */
    $api_url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport";
    $body    = [
        'dateRanges' => [ [ 'startDate' => $date_from, 'endDate' => $date_to ] ],
        'dimensions' => [ [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ],
        'metrics'    => $api_metrics,
        'limit'      => 50000,
        'orderBys'   => [ [ 'metric' => [ 'metricName' => $api_metrics[0]['name'] ], 'desc' => true ] ],
    ];

    $data = seo_dash_google_post( $api_url, $body, $token );

    if ( ! $data ) {
        SEO_Dash_Database::log_activity( 'ga_fetch_urls_failed', 'error',
            "GA API returned no data for {$month_key}.", 'report', $report_id );
        seo_dash_json_error( 'Failed to reach Google Analytics API. Check integration credentials and property ID.' );
    }
    if ( ! empty( $data['error'] ) ) {
        $err = is_array( $data['error'] ) ? ( $data['error']['message'] ?? 'GA API error' ) : (string) $data['error'];
        seo_dash_json_error( "GA API error: {$err}" );
    }

    $total_ga_rows = count( $data['rows'] ?? [] );

    // Debug log: shows what GA returned vs what paths we're looking for.
    SEO_Dash_Database::log_activity( 'ga_debug', 'info',
        "GA returned {$total_ga_rows} rows. Sample GA path: " . ( $data['rows'][0]['dimensionValues'][0]['value'] ?? 'none' ) .
        " | Tracked paths sample: " . implode( ', ', array_slice( array_keys( $path_to_full ), 0, 3 ) ),
        'report', $report_id
    );

    /* ── Filter to only URLs we care about ── */
    $aggregated = [];
    foreach ( $data['rows'] ?? [] as $api_row ) {
        $dims        = $api_row['dimensionValues'] ?? [];
        $metrics     = $api_row['metricValues']    ?? [];
        $ga_path_raw = $dims[0]['value'] ?? '';

        // GA returns pagePath (e.g. /my-page/) but may occasionally return a full URL.
        $ga_path = parse_url( $ga_path_raw, PHP_URL_PATH ) ?: '/';

        // Build all normalised variants of the GA path for lookup.
        $ga_norm       = strtolower( rtrim( $ga_path, '/' ) ) ?: '/';
        $ga_with_slash = ( $ga_norm === '/' ) ? '/' : $ga_norm . '/';

        // Try exact raw path first, then normalised variants.
        $full_url = $path_to_full[ $ga_path ]        // exact path as returned by GA
                 ?? $path_to_full[ $ga_norm ]         // lowercase, no trailing slash
                 ?? $path_to_full[ $ga_with_slash ]   // lowercase, with trailing slash
                 ?? null;

        if ( ! $full_url ) continue;

        $sessions = 0; $users = 0; $pageviews = 0;
        foreach ( $api_metrics as $idx => $mdef ) {
            $val = intval( $metrics[ $idx ]['value'] ?? 0 );
            switch ( $mdef['name'] ) {
                case 'sessions':        $sessions  = $val; break;
                case 'activeUsers':     $users     = $val; break;
                case 'screenPageViews': $pageviews = $val; break;
            }
        }

        if (!isset($aggregated[$full_url])) {
            $aggregated[$full_url] = [
                'report_id'   => $report_id,
                'period_type' => 'monthly',
                'month_key'   => $month_key,
                'date_from'   => $date_from,
                'date_to'     => $date_to,
                'page_url'    => $full_url,
                'page_title'  => $dims[1]['value'] ?? '',
                'sessions'    => 0,
                'users'       => 0,
                'pageviews'   => 0,
                'bounces'     => 0,
            ];
        }
        $aggregated[$full_url]['sessions']  += $sessions;
        $aggregated[$full_url]['users']     += $users;
        $aggregated[$full_url]['pageviews'] += $pageviews;
    }
    $rows = array_values($aggregated);

    /* ── Upsert: delete placeholder rows, then insert real data ── */
    $table = SEO_Dash_Database::$data_ga;
    foreach ( $raw_urls as $full_url ) {
        if ( ! $full_url ) continue;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE report_id=%d AND month_key=%s AND page_url=%s",
            $report_id, $month_key, $full_url
        ) );
    }
    $inserted = SEO_Dash_Database::insert_data_rows( $table, $rows );

    // Update titles for existing pages (no auto-add)
    $sync_data = [];
    foreach ( $rows as $row ) {
        $sync_data[ $row['page_url'] ] = $row['page_title'];
    }
    seo_dash_auto_sync_fetched_urls( $report_id, $sync_data );

    // Collect NEW pending URLs for admin confirmation
    $pending = seo_dash_get_pending_page_urls( $report_id, $sync_data );
    $has_pending = ! empty( $pending['service'] ) || ! empty( $pending['blog'] );

    SEO_Dash_Database::log_activity(
        'ga_fetch_urls_ok', 'success',
        "GA URL-fetch: {$inserted} matched+saved | {$total_ga_rows} total GA rows | {$month_key} ({$date_from} to {$date_to}).",
        'report', $report_id
    );

    seo_dash_json_success( [
        'inserted'    => $inserted,
        'matched'     => count( $rows ),
        'total_in_ga' => $total_ga_rows,
        'selected'    => count( $raw_urls ),
        'not_found'   => count( $raw_urls ) - count( $rows ),
        'pending_urls' => $has_pending ? $pending : null,
    ], "Fetched {$inserted} rows (Total in GA API across site: {$total_ga_rows})." );
} );

// ── Bulk action on GA rows ─────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_ga_bulk_action', function () {
    seo_dash_verify_admin_ajax();

    $action = sanitize_key( $_POST['bulk_action'] ?? '' );
    $ids    = array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) );
    $table  = SEO_Dash_Database::$data_ga;

    if ( ! $action || empty( $ids ) ) seo_dash_json_error( 'Missing action or IDs.' );
    if ( ! in_array( $action, [ 'trash', 'restore', 'delete' ], true ) ) seo_dash_json_error( 'Unknown action.' );

    $done = 0;
    foreach ( $ids as $id ) {
        if ( ! $id ) continue;
        switch ( $action ) {
            case 'trash':   SEO_Dash_Database::trash_row( $table, $id );   $done++; break;
            case 'restore': SEO_Dash_Database::restore_row( $table, $id ); $done++; break;
            case 'delete':  SEO_Dash_Database::delete_row( $table, $id );  $done++; break;
        }
    }
    SEO_Dash_Database::log_activity( "ga_bulk_{$action}", 'info', "GA bulk {$action}: {$done} rows." );
    seo_dash_json_success( [ 'done' => $done ], "{$done} rows updated." );
} );

// ── Bulk action on SC rows ─────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_sc_bulk_action', function () {
    seo_dash_verify_admin_ajax();

    $action = sanitize_key( $_POST['bulk_action'] ?? '' );
    $ids    = array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) );
    $table  = SEO_Dash_Database::$data_sc;

    if ( ! $action || empty( $ids ) ) seo_dash_json_error( 'Missing action or IDs.' );
    if ( ! in_array( $action, [ 'trash', 'restore', 'delete' ], true ) ) seo_dash_json_error( 'Unknown action.' );

    $done = 0;
    foreach ( $ids as $id ) {
        if ( ! $id ) continue;
        switch ( $action ) {
            case 'trash':   SEO_Dash_Database::trash_row( $table, $id );   $done++; break;
            case 'restore': SEO_Dash_Database::restore_row( $table, $id ); $done++; break;
            case 'delete':  SEO_Dash_Database::delete_row( $table, $id );  $done++; break;
        }
    }
    SEO_Dash_Database::log_activity( "sc_bulk_{$action}", 'info', "SC bulk {$action}: {$done} rows." );
    seo_dash_json_success( [ 'done' => $done ], "{$done} rows updated." );
} );

// ── Generic single row delete / trash / restore (all data tables) ──────────
add_action( 'wp_ajax_seo_dash_delete_data_row', function () {
    seo_dash_verify_admin_ajax();

    $table_key = sanitize_key( $_POST['table'] ?? '' );
    $action    = sanitize_key( $_POST['row_action'] ?? 'delete' );
    $id        = intval( $_POST['row_id'] ?? 0 );

    if ( ! $id ) seo_dash_json_error( 'Missing row ID.' );
    if ( ! in_array( $action, [ 'delete', 'trash', 'restore' ], true ) ) seo_dash_json_error( 'Unknown action.' );

    // Safe whitelist: map table_key → actual table property
    $table_map = [
        'ga'         => SEO_Dash_Database::$data_ga,
        'sc'         => SEO_Dash_Database::$data_sc,
        'gmb'        => SEO_Dash_Database::$data_gmb,
        'technical'  => SEO_Dash_Database::$data_technical,
        'backlinks'  => SEO_Dash_Database::$data_backlinks,
        'leads'      => SEO_Dash_Database::$data_leads,
        'pages'      => SEO_Dash_Database::$data_pages,
    ];

    if ( ! isset( $table_map[ $table_key ] ) ) seo_dash_json_error( 'Unknown table.' );
    $table = $table_map[ $table_key ];

    switch ( $action ) {
        case 'delete':  SEO_Dash_Database::delete_row( $table, $id );  break;
        case 'trash':   SEO_Dash_Database::trash_row( $table, $id );   break;
        case 'restore': SEO_Dash_Database::restore_row( $table, $id ); break;
    }

    SEO_Dash_Database::log_activity( "{$table_key}_row_{$action}", 'info', "Row {$id} {$action}d in {$table_key}." );
    seo_dash_json_success( null, 'Done.' );
} );

// ── Generic bulk action (all data tables) ─────────────────────────────────
add_action( 'wp_ajax_seo_dash_bulk_data_action', function () {
    seo_dash_verify_admin_ajax();

    $table_key = sanitize_key( $_POST['table'] ?? '' );
    $action    = sanitize_key( $_POST['bulk_action'] ?? '' );
    $subtype   = sanitize_key( $_POST['subtype'] ?? '' );
    $ids       = array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) );
    $urls      = isset($_POST['urls']) ? array_map( 'esc_url_raw', (array) $_POST['urls'] ) : [];
    $report_id = intval( $_POST['report_id'] ?? 0 );

    if ( ! $action || ( empty( $ids ) && empty( $urls ) && ! in_array( $action, [ 'delete_all', 'delete_all_subtype' ], true ) ) ) {
        seo_dash_json_error( 'Missing action, IDs, or URLs.' );
    }
    if ( ! in_array( $action, [ 'delete', 'trash', 'restore', 'add_service', 'add_blog', 'delete_all', 'delete_all_subtype' ], true ) ) {
        seo_dash_json_error( 'Unknown action.' );
    }

    $table_map = [
        'ga'             => SEO_Dash_Database::$data_ga,
        'sc'             => SEO_Dash_Database::$data_sc,
        'gmb'            => SEO_Dash_Database::$data_gmb,
        'technical'      => SEO_Dash_Database::$data_technical,
        'backlinks'      => SEO_Dash_Database::$data_backlinks,
        'leads'          => SEO_Dash_Database::$data_leads,
        'pages'          => SEO_Dash_Database::$data_pages,
        'click_tracking' => SEO_Dash_Database::$data_click_tracking,
    ];
    if ( ! isset( $table_map[ $table_key ] ) ) seo_dash_json_error( 'Unknown table.' );
    $table = $table_map[ $table_key ];

    // Handle Delete All in Subtype (e.g. City, Post, Page, Category, Author, etc.)
    if ( $action === 'delete_all_subtype' ) {
        if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
        if ( ! $subtype ) seo_dash_json_error( 'Missing sub-tab / subtype name.' );

        global $wpdb;
        $map_key = "seo_dash_sitemap_types_{$report_id}_{$table_key}";
        $map = get_option( $map_key, [] );
        if ( ! is_array( $map ) ) $map = [];

        // Collect all URLs belonging to this subtype
        $target_urls = [];
        foreach ( $map as $u => $t ) {
            if ( $t === $subtype || ( $subtype === 'other' && ! $t ) ) {
                $target_urls[] = $u;
                unset( $map[$u] );
            }
        }
        update_option( $map_key, $map, false );

        // If URLs were passed explicitly, merge them
        if ( ! empty( $urls ) ) {
            $target_urls = array_unique( array_merge( $target_urls, $urls ) );
        }

        $deleted_count = 0;
        if ( ! empty( $target_urls ) ) {
            $chunks = array_chunk( $target_urls, 300 );
            foreach ( $chunks as $chunk ) {
                $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
                $sql = $wpdb->prepare(
                    "DELETE FROM {$table} WHERE report_id = %d AND page_url IN ({$placeholders})",
                    array_merge( [ $report_id ], $chunk )
                );
                $res = $wpdb->query( $sql );
                if ( $res ) $deleted_count += $res;
            }
        }

        // Also delete any rows tagged with [sitemap:{$subtype}] in title or query
        $tag_like = '%[sitemap:' . $wpdb->esc_like( $subtype ) . ']%';
        if ( $table_key === 'ga' ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE report_id = %d AND page_title LIKE %s", $report_id, $tag_like ) );
        } elseif ( $table_key === 'sc' ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE report_id = %d AND query LIKE %s", $report_id, $tag_like ) );
        }

        SEO_Dash_Database::log_activity(
            "{$table_key}_delete_subtype", 'warning',
            "Deleted all URLs in subtype '{$subtype}' for report {$report_id} in {$table_key}."
        );
        seo_dash_json_success( [ 'done' => max( $deleted_count, count( $target_urls ) ) ], 'All ' . ucfirst( str_replace( '_', ' ', $subtype ) ) . ' pages deleted permanently.' );
    }

    // Handle Delete All (All types/sub-tabs)
    if ( $action === 'delete_all' ) {
        if ( ! $report_id ) seo_dash_json_error( 'Missing report ID for delete_all.' );
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE report_id = %d", $report_id ) );
        delete_option( "seo_dash_sitemap_types_{$report_id}_{$table_key}" );
        SEO_Dash_Database::log_activity( "{$table_key}_bulk_delete_all", 'warning', "All rows deleted for report {$report_id} in {$table_key}." );
        seo_dash_json_success( [ 'done' => 1 ], 'All pages deleted permanently.' );
    }

    // Handle Custom Pages Assignment (add_service / add_blog)
    if ( in_array( $action, ['add_service', 'add_blog'], true ) ) {
        if ( ! $report_id ) seo_dash_json_error( 'Missing report ID for custom pages mapping.' );
        $type = $action === 'add_service' ? 'service' : 'blog';
        $custom_map = get_option("seo_dash_custom_pages_{$report_id}_{$type}", []);
        if (!is_array($custom_map)) $custom_map = [];

        global $wpdb;
        $added = 0;
        
        // Add URLs passed explicitly
        foreach ( $urls as $u ) {
            if ( ! isset( $custom_map[$u] ) ) {
                // Try to find title in GA/SC tables
                $title = $wpdb->get_var( $wpdb->prepare( "SELECT page_title FROM " . SEO_Dash_Database::$data_ga . " WHERE report_id=%d AND page_url=%s AND page_title != '' LIMIT 1", $report_id, $u ) );
                if ( ! $title ) {
                    $title = $wpdb->get_var( $wpdb->prepare( "SELECT page_title FROM " . SEO_Dash_Database::$data_sc . " WHERE report_id=%d AND page_url=%s AND page_title != '' LIMIT 1", $report_id, $u ) );
                }
                $custom_map[$u] = [
                    'url' => $u,
                    'title' => $title ?: $u,
                    'keyword' => '',
                    'ranked_page' => '',
                    'ai_overview' => 0,
                    'month' => '',
                    'publish_date' => ''
                ];
                $added++;
            }
        }

        // Fetch URLs from IDs
        if ( ! empty( $ids ) ) {
            $id_list = implode(',', $ids);
            $rows_info = $wpdb->get_results( "SELECT page_url, page_title FROM {$table} WHERE id IN ({$id_list})" );
            foreach ($rows_info as $ri) {
                $u = $ri->page_url;
                if ( $u && ! isset( $custom_map[$u] ) ) {
                    $custom_map[$u] = [
                        'url' => $u,
                        'title' => $ri->page_title ?: $u,
                        'keyword' => '',
                        'ranked_page' => '',
                        'ai_overview' => 0,
                        'month' => '',
                        'publish_date' => ''
                    ];
                    $added++;
                    // Add to URLs array so it gets mapped below
                    if (!in_array($u, $urls)) $urls[] = $u;
                }
            }
        }

        if ( $added > 0 ) {
            update_option("seo_dash_custom_pages_{$report_id}_{$type}", $custom_map);
            
            // Nuclear fix: force mapping so they don't default to 'other'
            $ga_map = get_option("seo_dash_sitemap_types_{$report_id}_ga", []);
            if (!is_array($ga_map)) $ga_map = [];
            $sc_map = get_option("seo_dash_sitemap_types_{$report_id}_sc", []);
            if (!is_array($sc_map)) $sc_map = [];
            
            foreach ($urls as $u) {
                if (empty($ga_map[$u])) $ga_map[$u] = $type;
                if (empty($sc_map[$u])) $sc_map[$u] = $type;
            }
            update_option("seo_dash_sitemap_types_{$report_id}_ga", $ga_map);
            update_option("seo_dash_sitemap_types_{$report_id}_sc", $sc_map);
            
            seo_dash_json_success( "{$added} URL(s) added to {$type}." );
            seo_dash_json_success( [ 'done' => $added ], "Added {$added} URL(s) to {$type} pages." );
        } else {
            seo_dash_json_success( [ 'done' => 0 ], "Selected URLs are already in {$type} pages." );
        }
    }

    // Nuclear URL processing: if urls are passed, delete/trash/restore ALL their rows directly
    if ( in_array($table_key, ['ga', 'sc'], true) && !empty($urls) && current_user_can('manage_options') ) {
        global $wpdb;
        foreach ($urls as $u) {
            if ($action === 'delete') {
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE report_id=%d AND page_url=%s", $report_id, $u));
            } else if ($action === 'trash') {
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET trashed=1 WHERE report_id=%d AND page_url=%s", $report_id, $u));
            } else if ($action === 'restore') {
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET trashed=0 WHERE report_id=%d AND page_url=%s", $report_id, $u));
            }
        }
    }
    
    // Pre-fetch report_id and page_url to clean up sitemap map if completely deleted or trashed
    $cleanup_check = [];
    if ( in_array($table_key, ['ga', 'sc'], true) && in_array($action, ['delete', 'trash'], true) ) {
        global $wpdb;
        
        // Add explicitly passed URLs first
        if ( !empty( $urls ) && $report_id ) {
            $cleanup_check[$report_id] = $urls;
        }

        if ( !empty( $ids ) ) {
            $id_list = implode(',', $ids);
            $rows_info = $wpdb->get_results( "SELECT report_id, page_url FROM {$table} WHERE id IN ({$id_list})" );
            foreach ($rows_info as $ri) {
                $cleanup_check[$ri->report_id][] = $ri->page_url;
            }
        }
    }

    $done = 0;
    foreach ( $ids as $id ) {
        if ( ! $id ) continue;
        switch ( $action ) {
            case 'delete':  SEO_Dash_Database::delete_row( $table, $id );  $done++; break;
            case 'trash':   SEO_Dash_Database::trash_row( $table, $id );   $done++; break;
            case 'restore': SEO_Dash_Database::restore_row( $table, $id ); $done++; break;
        }
    }

    // Run cleanup
    if ( ! empty( $cleanup_check ) ) {
        global $wpdb;
        foreach ($cleanup_check as $rep_id => $urls) {
            $urls = array_unique($urls);
            $map_key = "seo_dash_sitemap_types_{$rep_id}_{$table_key}";
            $map = get_option( $map_key, [] );
            if (!is_array($map)) $map = [];
            $changed = false;
            foreach ($urls as $u) {
                // Any rows (trashed or active) left for this URL?
                $left = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE report_id=%d AND page_url=%s LIMIT 1", $rep_id, $u));
                if (!$left && isset($map[$u])) {
                    unset($map[$u]);
                    $changed = true;
                }
            }
            if ($changed) {
                update_option( $map_key, $map );
            }
        }
    }
    SEO_Dash_Database::log_activity( "{$table_key}_bulk_{$action}", 'info', "Bulk {$action}: {$done} rows in {$table_key}." );
    seo_dash_json_success( [ 'done' => $done ], "{$done} rows {$action}d." );
} );

function seo_dash_upsert_ga_batch( array $rows_batch ): int {
    if ( empty( $rows_batch ) ) return 0;
    global $wpdb;
    $tbl = SEO_Dash_Database::$data_ga;
    
    $total_done = 0;
    foreach ( $rows_batch as $r ) {
        $existing_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$tbl} WHERE report_id = %d AND period_type = %s AND page_url = %s AND date_from = %s AND date_to = %s LIMIT 1",
            intval( $r['report_id'] ),
            sanitize_text_field( $r['period_type'] ),
            esc_url_raw( $r['page_url'] ),
            sanitize_text_field( $r['date_from'] ),
            sanitize_text_field( $r['date_to'] )
        ) );

        if ( $existing_id ) {
            $res = $wpdb->update( $tbl, [
                'sessions'   => intval( $r['sessions'] ),
                'users'      => intval( $r['users'] ),
                'pageviews'  => intval( $r['pageviews'] ),
                'page_title' => sanitize_text_field( $r['page_title'] ),
                'month_key'  => sanitize_text_field( $r['month_key'] ),
            ], [ 'id' => intval( $existing_id ) ] );
            if ( false !== $res ) $total_done++;
        } else {
            $res = $wpdb->insert( $tbl, [
                'report_id'   => intval( $r['report_id'] ),
                'period_type' => sanitize_text_field( $r['period_type'] ),
                'month_key'   => sanitize_text_field( $r['month_key'] ),
                'date_from'   => sanitize_text_field( $r['date_from'] ),
                'date_to'     => sanitize_text_field( $r['date_to'] ),
                'page_url'    => esc_url_raw( $r['page_url'] ),
                'page_title'  => sanitize_text_field( $r['page_title'] ),
                'sessions'    => intval( $r['sessions'] ),
                'users'       => intval( $r['users'] ),
                'pageviews'   => intval( $r['pageviews'] ),
                'imported_at' => ! empty( $r['imported_at'] ) ? $r['imported_at'] : current_time( 'mysql' ),
            ] );
            if ( false !== $res ) $total_done++;
        }
    }
    return $total_done;
}

// ── Helper: upsert one GA row (update metrics if URL+period exists) ─────────
function seo_dash_upsert_ga_row( array $row ): bool {
    global $wpdb;
    $tbl = SEO_Dash_Database::$data_ga;

    // Look for existing
    $existing = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, sessions, users, pageviews FROM {$tbl} WHERE report_id=%d AND month_key=%s AND period_type=%s AND page_url=%s",
        $row['report_id'], $row['month_key'], $row['period_type'], $row['page_url']
    ), ARRAY_A );

    if ( $existing ) {
        return (bool) $wpdb->update( $tbl, [
            'sessions'   => $row['sessions'],
            'users'      => $row['users'],
            'pageviews'  => $row['pageviews'],
            'page_title' => $row['page_title'],
        ], [ 'id' => intval( $existing['id'] ) ], [ '%d', '%d', '%d', '%s' ], [ '%d' ] );
    }
    return (bool) $wpdb->insert( $tbl, $row );
}

// ── Helper: upsert one pages row (update sessions if URL+period exists) ──────
function seo_dash_upsert_pages_row( array $row ): bool {
    global $wpdb;
    $tbl = SEO_Dash_Database::$data_pages;

    $existing_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$tbl} WHERE report_id=%d AND month_key=%s AND url=%s AND page_type=%s LIMIT 1",
        $row['report_id'], $row['month_key'], $row['url'], $row['page_type']
    ) );

    if ( $existing_id ) {
        return (bool) $wpdb->update( $tbl, [
            'sessions' => $row['sessions'],
            'title'    => $row['title'],
        ], [ 'id' => intval( $existing_id ) ], [ '%d', '%s' ], [ '%d' ] );
    }
    return (bool) $wpdb->insert( $tbl, $row );
}

// ── Fetch ALL periods (7d/30d/90d/overall) — upsert, no duplicates ──────────
add_action( 'wp_ajax_seo_dash_ga_fetch_all_periods', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;

    $report_id    = intval( $_POST['report_id']   ?? 0 );
    $filter_type  = sanitize_key( $_POST['filter_type'] ?? 'all' ); // all | service | blog
    $target_month = seo_dash_sanitize_month( wp_unslash( $_POST['target_month'] ?? '' ) ); // e.g. 2026-04
    // target is now only 'ga' — pages are added via separate ga_to_pages action
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
    
    // Fallback for requests from old UI panels
    if ( ! $target_month ) {
        $target_month = get_option( "seo_dash_active_month_{$report_id}_ga", '' );
        if ( ! $target_month || $target_month === 'None' ) {
            $target_month = date('Y-m'); // Default to current month
        }
    }
    // Blog types = whitelist. Service = everything else (includes author, and any future types).
    $blog_types    = [ 'post', 'blog', 'article', 'news', 'category', 'tag' ];
    $service_types = $blog_types; // used below only as reference; actual logic uses NOT-blog

    $ga_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_ga", [] );
    if ( ! is_array( $ga_type_map ) ) $ga_type_map = [];

    if ( empty( $ga_type_map ) ) {
        // Rebuild type map from the pages table (source of truth for url→type).
        $pages_tbl = SEO_Dash_Database::$pages;
        $rows_ex = $wpdb->get_results( $wpdb->prepare(
            "SELECT page_url, page_type FROM {$pages_tbl} WHERE report_id=%d AND trashed=0",
            $report_id
        ), ARRAY_A );
        foreach ( $rows_ex as $r ) {
            if ( $r['page_url'] ) $ga_type_map[ $r['page_url'] ] = $r['page_type'] ?: 'other';
        }
        // If no pages table entries exist, fall back to data_ga urls with guess from URL path.
        if ( empty( $ga_type_map ) ) {
            $rows_ex2 = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT page_url FROM " . SEO_Dash_Database::$data_ga . " WHERE report_id=%d LIMIT 5000",
                $report_id
            ), ARRAY_A );
            foreach ( $rows_ex2 as $r ) {
                if ( $r['page_url'] ) {
                    $ga_type_map[ $r['page_url'] ] = seo_dash_guess_type_from_page_url( $r['page_url'] );
                }
            }
        }
        // Persist so next call doesn't rebuild.
        if ( ! empty( $ga_type_map ) ) {
            update_option( "seo_dash_sitemap_types_{$report_id}_ga", $ga_type_map, false );
        }
    }
    if ( empty( $ga_type_map ) ) {
        seo_dash_json_error( 'No URLs found. Import a sitemap first via Integrations → Sitemap → Add to Analytics.' );
    }

    $selected_urls = isset($_POST['selected_urls']) && is_array($_POST['selected_urls']) ? array_map('esc_url_raw', $_POST['selected_urls']) : [];

    $filtered = [];
    foreach ( $ga_type_map as $url => $type ) {
        if ( ! $url ) continue;
        if ( !empty($selected_urls) && !in_array($url, $selected_urls, true) ) continue;
        if ( $filter_type === 'service' && ! in_array( $type, $service_types, true ) ) continue;
        if ( $filter_type === 'blog'    && ! in_array( $type, $blog_types,    true ) ) continue;
        $filtered[ $url ] = $type;
    }
    if ( empty( $filtered ) ) seo_dash_json_error( 'No matching URLs for filter: ' . $filter_type . (empty($selected_urls) ? '' : ' (or specific URLs selected)') );

    // Resolve integration + property
    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'ga' );
    $property    = '';
    if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
        require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
    }
    $global_assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
    $global_id          = is_array( $global_assignments ) ? ( $global_assignments['global'] ?? '' ) : '';
    if ( $global_id ) {
        $g = seo_dash_get_global_integration_by_id( $global_id );
        if ( $g ) {
            $property = $g['ga4_property_id'] ?? '';
            if ( ! $integration && ! empty( $g['ga4_json_key'] ) ) {
                $raw         = function_exists( 'seo_dash_sec_decrypt' ) ? seo_dash_sec_decrypt( $g['ga4_json_key'] ) : $g['ga4_json_key'];
                $integration = [ 'credentials' => json_decode( $raw, true ) ];
            }
        }
    }
    if ( ! $integration ) seo_dash_json_error( 'No GA integration assigned.' );
    $creds = is_array( $integration['credentials'] ) ? $integration['credentials'] : [];
    if ( empty( $creds ) ) seo_dash_json_error( 'Empty GA credentials.' );

    $token = seo_dash_get_google_token( $creds, 'https://www.googleapis.com/auth/analytics.readonly' );
    if ( ! $token ) seo_dash_json_error( 'Google authentication failed.' );
    $property = preg_replace( '/^properties\//', '', $property );
    if ( ! $property ) seo_dash_json_error( 'Missing GA4 property ID.' );

    // Save property ID for later background KPI fetches
    update_option( "seo_dash_report_property_{$report_id}_ga", $property );

    // Build path→URL map
    $path_map = [];
    foreach ( array_keys( $filtered ) as $full_url ) {
        $path     = parse_url( $full_url, PHP_URL_PATH ) ?: '/';
        $no_slash = rtrim( $path, '/' ) ?: '/';
        $w_slash  = ( $no_slash === '/' ) ? '/' : $no_slash . '/';
        foreach ( [ $path, $no_slash, $w_slash, strtolower($path), strtolower($no_slash), strtolower($w_slash) ] as $v ) {
            $path_map[ $v ] = $full_url;
        }
    }

    $overall_start = sanitize_text_field( wp_unslash( $_POST['overall_start'] ?? '' ) );
    if ( ! $overall_start || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $overall_start ) ) {
        $overall_start = date( 'Y-m-d', strtotime( '-365 days' ) );
    }
    $overall_end = sanitize_text_field( wp_unslash( $_POST['overall_end'] ?? '' ) );
    if ( ! $overall_end || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $overall_end ) ) {
        $overall_end = date( 'Y-m-d' );
    }
    $preset_days = intval( $_POST['preset_days'] ?? 0 );
    // Override: always store under overall_end's month — cumulative fetch Jan→Aug stores under Aug.
    if ( $preset_days === 0 && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $overall_end ) ) {
        $target_month = substr( $overall_end, 0, 7 );
    }

    $today   = date( 'Y-m-d' );
    $periods = [
        '7d'      => [ 'start' => date( 'Y-m-d', strtotime( '-7 days' ) ),   'end' => $today ],
        '30d'     => [ 'start' => date( 'Y-m-d', strtotime( '-30 days' ) ),  'end' => $today ],
        '90d'     => [ 'start' => date( 'Y-m-d', strtotime( '-90 days' ) ),  'end' => $today ],
        'overall' => [ 'start' => $overall_start, 'end' => $overall_end ],
    ];

    // If a preset was used, only fetch that specific period
    if ( $preset_days > 0 ) {
        $preset_key = $preset_days . 'd';
        $preset_start = date( 'Y-m-d', strtotime( '-' . $preset_days . ' days' ) );
        $periods = [
            $preset_key => [ 'start' => $preset_start, 'end' => $today ],
        ];
    }

    $api_url        = "https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport";
    $total_upserted = 0;
    $period_counts  = [];
    $all_fetched_urls_titles = []; // For auto-syncing to Blog/Service tabs

    $report_obj = SEO_Dash_Database::get_report( $report_id );
    $site_base  = rtrim( $report_obj['site_url'] ?? '', '/' );

    $should_fetch_presets     = ( string )( $_POST['fetch_presets'] ?? '1' ) !== '0';
    $should_fetch_granularity = ( string )( $_POST['fetch_granularity'] ?? '0' ) === '1';
    $property_kpis = [];

    if ( $should_fetch_presets ) {
        foreach ( $periods as $period_key => $dates ) {
        // Site-wide Overview Query (No page dimension for exact site-wide deduplicated users)
        $overview_body = [
            'dateRanges' => [ [ 'startDate' => $dates['start'], 'endDate' => $dates['end'] ] ],
            'metrics'    => [ [ 'name' => 'activeUsers' ], [ 'name' => 'sessions' ] ],
        ];
        $ov_data = seo_dash_google_post( $api_url, $overview_body, $token );
        if ( ! empty( $ov_data['rows'][0]['metricValues'] ) ) {
            $ov_mets = $ov_data['rows'][0]['metricValues'];
            $property_kpis[$period_key] = [
                'users'    => intval( $ov_mets[0]['value'] ?? 0 ),
                'sessions' => intval( $ov_mets[1]['value'] ?? 0 ),
            ];
        }

        $body = [
            'dateRanges' => [ [ 'startDate' => $dates['start'], 'endDate' => $dates['end'] ] ],
            'dimensions' => [ [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ],
            'metrics'    => [ [ 'name' => 'sessions' ], [ 'name' => 'activeUsers' ], [ 'name' => 'screenPageViews' ] ],
            'limit'      => 100000,
            'orderBys'   => [ [ 'metric' => [ 'metricName' => 'sessions' ], 'desc' => true ] ],
        ];
        $data = seo_dash_google_post( $api_url, $body, $token );
        if ( ! $data || ! empty( $data['error'] ) ) { $period_counts[$period_key] = 0; continue; }

        $done = 0;
        $aggregated = [];
        foreach ( $data['rows'] ?? [] as $api_row ) {
            $dims    = $api_row['dimensionValues'] ?? [];
            $metrics = $api_row['metricValues']    ?? [];
            $ga_path = parse_url( $dims[0]['value'] ?? '', PHP_URL_PATH ) ?: '/';
            $ga_norm = strtolower( rtrim( $ga_path, '/' ) ) ?: '/';
            $ga_wsl   = ( $ga_norm === '/' ) ? '/' : $ga_norm . '/';
            $full_url = $path_map[$ga_path] ?? $path_map[$ga_norm] ?? $path_map[$ga_wsl] ?? null;
            if ( ! $full_url ) {
                $full_url = $site_base ? ( $site_base . $ga_path ) : $ga_path;
            }

            if (!isset($aggregated[$full_url])) {
                $aggregated[$full_url] = [
                    'report_id'   => $report_id, 'period_type' => $period_key,
                    'month_key'   => $target_month, 'date_from'   => $dates['start'],
                    'date_to'     => $dates['end'], 'page_url'  => $full_url,
                    'page_title'  => sanitize_text_field( $dims[1]['value'] ?? '' ),
                    'sessions'    => 0,
                    'users'       => 0,
                    'pageviews'   => 0,
                    'bounces'     => 0,
                ];
            }
            $aggregated[$full_url]['sessions'] += intval( $metrics[0]['value'] ?? 0 );
            $aggregated[$full_url]['users'] += intval( $metrics[1]['value'] ?? 0 );
            $aggregated[$full_url]['pageviews'] += intval( $metrics[2]['value'] ?? 0 );

            // Track for auto-syncing
            if ( ! isset( $all_fetched_urls_titles[$full_url] ) ) {
                $all_fetched_urls_titles[$full_url] = sanitize_text_field( $dims[1]['value'] ?? '' );
            }
        }
        
        foreach ( $aggregated as $row ) {
            // First we check if this is the first API chunk inserting it, 
            // but we aggregated it locally so we only do 1 DB upsert per URL.
            // We use the upsert function so we don't delete other periods.
            if ( seo_dash_upsert_ga_row( $row ) ) {
                $done++;
            }
        }
        $total_upserted            += $done;
        $period_counts[$period_key] = $done;
    }
    } // End if ($should_fetch_presets)

    if ( ! empty( $property_kpis ) ) {
        update_option( "seo_dash_ga_kpis_{$report_id}", $property_kpis );
    }

    // 2. Fetch Month-by-Month or Day-by-Day data for backend AI chatbot storage
    $granularity_mode = sanitize_key( $_POST['granularity_mode'] ?? 'overall_months' );
    if ( $should_fetch_granularity && ( $granularity_mode === 'overall_months' || $granularity_mode === 'overall_days' ) ) {
        $dim_name   = ( $granularity_mode === 'overall_days' ) ? 'date' : 'yearMonth';
        $page_limit = 25000;
        $offset     = 0;
        $more_rows  = true;

        while ( $more_rows ) {
            $m_body = [
                'dateRanges' => [ [ 'startDate' => $overall_start, 'endDate' => $overall_end ] ],
                'dimensions' => [ [ 'name' => $dim_name ], [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ],
                'metrics'    => [ [ 'name' => 'sessions' ], [ 'name' => 'activeUsers' ], [ 'name' => 'screenPageViews' ] ],
                'limit'      => $page_limit,
                'offset'     => $offset,
            ];
            $m_data = seo_dash_google_post( $api_url, $m_body, $token );

            if ( ! empty( $m_data['error'] ) ) {
                $err_msg = is_array( $m_data['error'] ) ? ( $m_data['error']['message'] ?? json_encode( $m_data['error'] ) ) : $m_data['error'];
                SEO_Dash_Database::log_activity( 'ga_granularity_error', 'warning', "Granularity fetch error at offset {$offset}: " . $err_msg, 'report', $report_id );
                $more_rows = false;
                break;
            }

            $rows = $m_data['rows'] ?? [];
            if ( empty( $rows ) ) {
                $more_rows = false;
                break;
            }

            $m_batch = [];
            foreach ( $rows as $row ) {
                $dims = $row['dimensionValues'] ?? [];
                $mets = $row['metricValues'] ?? [];
                $raw_dim = $dims[0]['value'] ?? '';
                $p_url   = $dims[1]['value'] ?? '';
                $p_ttl   = $dims[2]['value'] ?? '';
                if ( ! $p_url || ! $raw_dim ) continue;

                $ga_path  = parse_url( $p_url, PHP_URL_PATH ) ?: '/';
                $ga_norm  = strtolower( rtrim( $ga_path, '/' ) ) ?: '/';
                $ga_wsl   = ( $ga_norm === '/' ) ? '/' : $ga_norm . '/';
                $full_url = $path_map[$ga_path] ?? $path_map[$ga_norm] ?? $path_map[$ga_wsl] ?? null;
                if ( ! $full_url ) {
                    $full_url = $site_base ? ( $site_base . $ga_path ) : $ga_path;
                }

                $s_val = intval( $mets[0]['value'] ?? 0 );
                $u_val = intval( $mets[1]['value'] ?? 0 );
                $v_val = intval( $mets[2]['value'] ?? 0 );

                if ( $granularity_mode === 'overall_days' && strlen( $raw_dim ) === 8 ) {
                    $d_from = substr( $raw_dim, 0, 4 ) . '-' . substr( $raw_dim, 4, 2 ) . '-' . substr( $raw_dim, 6, 2 );
                    $d_to   = $d_from;
                    $m_key  = substr( $raw_dim, 0, 4 ) . '-' . substr( $raw_dim, 4, 2 );
                    $p_type = 'daily';
                } else {
                    $m_key  = strlen( $raw_dim ) === 6 ? substr( $raw_dim, 0, 4 ) . '-' . substr( $raw_dim, 4, 2 ) : date( 'Y-m' );
                    $d_from = "{$m_key}-01";
                    $d_to   = date( 'Y-m-t', strtotime( $d_from ) );
                    $p_type = 'monthly';
                }

                $m_batch[] = [
                    'report_id'   => $report_id,
                    'period_type' => $p_type,
                    'month_key'   => $m_key,
                    'date_from'   => $d_from,
                    'date_to'     => $d_to,
                    'page_url'    => $full_url,
                    'page_title'  => $p_ttl ?: $full_url,
                    'sessions'    => $s_val,
                    'users'       => $u_val,
                    'pageviews'   => $v_val,
                    'imported_at' => current_time( 'mysql' ),
                ];
            }

            if ( ! empty( $m_batch ) ) {
                $total_upserted += seo_dash_upsert_ga_batch( $m_batch );
            }

            if ( count( $rows ) < $page_limit ) {
                $more_rows = false;
            } else {
                $offset += $page_limit;
            }
        }
    }

    // Update titles for existing pages only (no auto-add new URLs)
    if ( ! empty( $all_fetched_urls_titles ) ) {
        $should_fetch_kpis = ( $preset_days === 0 || $preset_days >= 30 );
        seo_dash_auto_sync_fetched_urls( $report_id, $all_fetched_urls_titles, $should_fetch_kpis );
    }

    // Collect NEW pending URLs for admin confirmation prompt
    $pending     = ! empty( $all_fetched_urls_titles ) ? seo_dash_get_pending_page_urls( $report_id, $all_fetched_urls_titles ) : [ 'service' => [], 'blog' => [] ];
    $has_pending = ! empty( $pending['service'] ) || ! empty( $pending['blog'] );

    SEO_Dash_Database::log_activity( 'ga_fetch_all_periods', 'success',
        "Fetch-all ({$filter_type}): {$total_upserted} rows upserted.", 'report', $report_id );
    seo_dash_json_success( [
        'upserted'      => $total_upserted,
        'period_counts' => $period_counts,
        'filter_type'   => $filter_type,
        'pending_urls'  => $has_pending ? $pending : null,
    ], "Updated/saved {$total_upserted} rows across 4 periods." );
} );

// ── Copy GA period data into data_pages (service or blog) ─────────────────
add_action( 'wp_ajax_seo_dash_ga_to_pages', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;

    $report_id   = intval( $_POST['report_id']   ?? 0 );
    $page_target = sanitize_key( $_POST['page_target'] ?? '' ); // service | blog
    if ( ! $report_id || ! in_array( $page_target, [ 'service', 'blog' ], true ) ) {
        seo_dash_json_error( 'Missing parameters.' );
    }

    $service_types = [ 'service','page','city','product','portfolio','location' ];
    $blog_types    = [ 'post','blog','article','news','category','tag' ];

    // Get sitemap type map to know which URLs belong to which type
    $ga_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_ga", [] );
    if ( ! is_array( $ga_type_map ) ) $ga_type_map = [];

    $period_keys = [ '7d', '30d', '90d', 'overall' ];
    $tbl_ga      = SEO_Dash_Database::$data_ga;
    $done        = 0;
    $period_counts = [];

    foreach ( $period_keys as $period_key ) {
        // Load GA rows for this period
        $ga_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT page_url, page_title, sessions FROM {$tbl_ga}
             WHERE report_id=%d AND month_key=%s AND trashed=0",
            $report_id, $period_key
        ), ARRAY_A );

        $p_done = 0;
        foreach ( $ga_rows as $ga_row ) {
            $url     = $ga_row['page_url'];
            $url_type = $ga_type_map[$url] ?? seo_dash_guess_type_from_page_url($url);

            // Determine pages type
            if ( in_array( $url_type, $service_types, true ) ) {
                $p_type = 'service';
            } elseif ( in_array( $url_type, $blog_types, true ) ) {
                $p_type = 'blog';
            } else {
                // Fallback: if no type map, assign to requested target
                $p_type = $page_target;
            }

            // Only add if it matches the target
            if ( $p_type !== $page_target ) continue;

            $pages_row = [
                'report_id'   => $report_id,
                'month_key'   => $period_key,
                'page_type'   => $p_type,
                'url'         => $url,
                'title'       => sanitize_text_field( $ga_row['page_title'] ?? '' ),
                'sessions'    => intval( $ga_row['sessions'] ),
                'clicks'      => 0,
                'impressions' => 0,
                'position'    => null,
            ];
            if ( seo_dash_upsert_pages_row( $pages_row ) ) $p_done++;
        }
        $done                      += $p_done;
        $period_counts[$period_key] = $p_done;
    }

    SEO_Dash_Database::log_activity( 'ga_to_pages', 'success',
        "Copied {$done} GA rows to {$page_target} pages.", 'report', $report_id );
    seo_dash_json_success( [
        'done'          => $done,
        'period_counts' => $period_counts,
        'page_target'   => $page_target,
    ], "Added {$done} rows to {$page_target} pages." );
} );

// ── Fetch GA Custom Date Range ──────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_ga_fetch_custom_range', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;

    $report_id    = intval( $_POST['report_id']   ?? 0 );
    $target_month = seo_dash_sanitize_month( wp_unslash( $_POST['target_month'] ?? '' ) );
    $custom_from  = sanitize_text_field( wp_unslash( $_POST['custom_from'] ?? '' ) );
    $custom_to    = sanitize_text_field( wp_unslash( $_POST['custom_to']   ?? '' ) );

    if ( ! $report_id || ! $custom_from || ! $custom_to ) {
        seo_dash_json_error( 'Missing parameters.' );
    }
    if ( ! $target_month ) $target_month = date('Y-m');

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'ga' );
    $property    = '';
    if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
        require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
    }
    $global_assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
    $global_id          = is_array( $global_assignments ) ? ( $global_assignments['global'] ?? '' ) : '';
    if ( $global_id ) {
        $g = seo_dash_get_global_integration_by_id( $global_id );
        if ( $g ) {
            $property = $g['ga4_property_id'] ?? '';
            if ( ! $integration && ! empty( $g['ga4_json_key'] ) ) {
                $raw         = function_exists( 'seo_dash_sec_decrypt' ) ? seo_dash_sec_decrypt( $g['ga4_json_key'] ) : $g['ga4_json_key'];
                $integration = [ 'credentials' => json_decode( $raw, true ) ];
            }
        }
    }
    if ( ! $integration ) seo_dash_json_error( 'No GA integration assigned.' );
    $creds = is_array( $integration['credentials'] ) ? $integration['credentials'] : [];
    if ( empty( $creds ) ) seo_dash_json_error( 'Empty GA credentials.' );

    $token = seo_dash_get_google_token( $creds, 'https://www.googleapis.com/auth/analytics.readonly' );
    if ( ! $token ) seo_dash_json_error( 'Google authentication failed.' );
    $property = preg_replace( '/^properties\//', '', $property );
    if ( ! $property ) seo_dash_json_error( 'Missing GA4 property ID.' );

    $granularity_mode = sanitize_key( $_POST['granularity_mode'] ?? 'overall_months' );

    $dimensions = [ [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ];
    if ( $granularity_mode === 'overall_days' ) {
        $dimensions = [ [ 'name' => 'date' ], [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ];
    } elseif ( $granularity_mode === 'overall_months' ) {
        $dimensions = [ [ 'name' => 'yearMonth' ], [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ];
    }

    $endpoint = 'https://analyticsdata.googleapis.com/v1beta/properties/' . $property . ':runReport';
    $request_body = [
        'dateRanges' => [ [ 'startDate' => $custom_from, 'endDate' => $custom_to ] ],
        'dimensions' => $dimensions,
        'metrics'    => [ [ 'name' => 'activeUsers' ], [ 'name' => 'sessions' ], [ 'name' => 'screenPageViews' ] ],
        'limit'      => 10000,
    ];

    $response = wp_remote_post( $endpoint, [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( $request_body ),
    ] );

    if ( is_wp_error( $response ) ) {
        seo_dash_json_error( 'API error: ' . $response->get_error_message() );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $rows = $data['rows'] ?? [];

    $tbl = SEO_Dash_Database::$data_ga;
    $upserted = 0;

    $report = SEO_Dash_Database::get_report( $report_id );
    $site_url = rtrim( $report['site_url'] ?? '', '/' );

    foreach ( $rows as $r ) {
        $dims = $r['dimensionValues'] ?? [];
        $mets = $r['metricValues'] ?? [];

        $d_from = $custom_from;
        $d_to   = $custom_to;
        $m_key  = $target_month;
        $p_type = 'custom';

        if ( $granularity_mode === 'overall_days' ) {
            $raw_d = $dims[0]['value'] ?? ''; // e.g. 20260515
            $path  = $dims[1]['value'] ?? '/';
            $title = $dims[2]['value'] ?? '';
            if ( strlen( $raw_d ) === 8 ) {
                $d_from = substr( $raw_d, 0, 4 ) . '-' . substr( $raw_d, 4, 2 ) . '-' . substr( $raw_d, 6, 2 );
                $d_to   = $d_from;
                $m_key  = substr( $raw_d, 0, 4 ) . '-' . substr( $raw_d, 4, 2 );
                $p_type = 'daily';
            }
        } elseif ( $granularity_mode === 'overall_months' ) {
            $raw_m = $dims[0]['value'] ?? ''; // e.g. 202605
            $path  = $dims[1]['value'] ?? '/';
            $title = $dims[2]['value'] ?? '';
            if ( strlen( $raw_m ) === 6 ) {
                $m_key  = substr( $raw_m, 0, 4 ) . '-' . substr( $raw_m, 4, 2 );
                $d_from = "{$m_key}-01";
                $d_to   = date( 'Y-m-t', strtotime( $d_from ) );
                $p_type = 'monthly';
            }
        } else {
            $path  = $dims[0]['value'] ?? '/';
            $title = $dims[1]['value'] ?? '';
        }

        $users = intval( $mets[0]['value'] ?? 0 );
        $sess  = intval( $mets[1]['value'] ?? 0 );
        $views = intval( $mets[2]['value'] ?? 0 );

        $full_url = $site_url ? $site_url . $path : $path;

        $existing_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$tbl} WHERE report_id=%d AND page_url=%s AND period_type=%s AND date_from=%s AND date_to=%s",
            $report_id, $full_url, $p_type, $d_from, $d_to
        ) );

        $row_data = [
            'report_id'   => $report_id,
            'period_type' => $p_type,
            'month_key'   => $m_key,
            'date_from'   => $d_from,
            'date_to'     => $d_to,
            'page_url'    => $full_url,
            'page_title'  => $title,
            'users'       => $users,
            'sessions'    => $sess,
            'pageviews'   => $views,
            'bounces'     => 0,
            'trashed'     => 0,
            'imported_at' => current_time( 'mysql' ),
        ];

        if ( $existing_id ) {
            $wpdb->update( $tbl, $row_data, [ 'id' => $existing_id ] );
        } else {
            $wpdb->insert( $tbl, $row_data );
        }
        $upserted++;
    }

    seo_dash_json_success( [ 'inserted' => $upserted ], "Saved {$upserted} custom range rows." );
} );

// ── Fetch Search Console Custom Date Range ──────────────────────────────────
add_action( 'wp_ajax_seo_dash_sc_fetch_custom_range', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;

    $report_id    = intval( $_POST['report_id']   ?? 0 );
    $target_month = seo_dash_sanitize_month( wp_unslash( $_POST['target_month'] ?? '' ) );
    $custom_from  = sanitize_text_field( wp_unslash( $_POST['custom_from'] ?? '' ) );
    $custom_to    = sanitize_text_field( wp_unslash( $_POST['custom_to']   ?? '' ) );

    if ( ! $report_id || ! $custom_from || ! $custom_to ) {
        seo_dash_json_error( 'Missing parameters.' );
    }
    if ( ! $target_month ) $target_month = date('Y-m');

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'sc' );
    $site_url    = '';
    if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
        require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
    }
    $global_assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
    $global_id          = is_array( $global_assignments ) ? ( $global_assignments['global'] ?? '' ) : '';
    if ( $global_id ) {
        $g = seo_dash_get_global_integration_by_id( $global_id );
        if ( $g ) {
            $site_url = $g['sc_site_url'] ?? '';
            if ( ! $integration && ! empty( $g['sc_oauth_token'] ) ) {
                $raw         = function_exists( 'seo_dash_sec_decrypt' ) ? seo_dash_sec_decrypt( $g['sc_oauth_token'] ) : $g['sc_oauth_token'];
                $integration = [ 'credentials' => json_decode( $raw, true ) ];
            }
        }
    }
    if ( ! $integration ) seo_dash_json_error( 'No Search Console integration assigned.' );
    $creds = is_array( $integration['credentials'] ) ? $integration['credentials'] : [];
    if ( empty( $creds ) ) seo_dash_json_error( 'Empty Search Console credentials.' );

    if ( empty( $site_url ) ) {
        $report   = SEO_Dash_Database::get_report( $report_id );
        $site_url = $report['site_url'] ?? '';
    }
    if ( ! $site_url ) seo_dash_json_error( 'Missing site URL for Search Console.' );

    $access_token = seo_dash_get_sc_token( $creds );
    if ( ! $access_token ) seo_dash_json_error( 'Search Console authentication failed.' );

    $granularity_mode = sanitize_key( $_POST['granularity_mode'] ?? 'overall_months' );
    $dimensions = [ 'page' ];
    if ( $granularity_mode === 'overall_days' ) {
        $dimensions = [ 'date', 'page' ];
    }

    $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode( $site_url ) . '/searchAnalytics/query';
    $request_body = [
        'startDate'  => $custom_from,
        'endDate'    => $custom_to,
        'dimensions' => $dimensions,
        'rowLimit'   => 10000,
    ];

    $response = wp_remote_post( $endpoint, [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( $request_body ),
    ] );

    if ( is_wp_error( $response ) ) {
        seo_dash_json_error( 'API error: ' . $response->get_error_message() );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $rows = $data['rows'] ?? [];

    $tbl = SEO_Dash_Database::$data_sc;
    $upserted = 0;

    foreach ( $rows as $r ) {
        if ( $granularity_mode === 'overall_days' && count( $r['keys'] ?? [] ) >= 2 ) {
            $raw_d    = $r['keys'][0] ?? ''; // e.g. 2026-05-15
            $page_url = $r['keys'][1] ?? '';
            $d_from   = $raw_d;
            $d_to     = $raw_d;
            $m_key    = substr( $raw_d, 0, 7 );
            $p_type   = 'daily';
        } else {
            $page_url = $r['keys'][0] ?? '';
            $d_from   = $custom_from;
            $d_to     = $custom_to;
            $m_key    = $target_month;
            $p_type   = 'custom';
        }
        if ( ! $page_url ) continue;

        $clicks      = intval( $r['clicks'] ?? 0 );
        $impressions = intval( $r['impressions'] ?? 0 );
        $ctr         = floatval( $r['ctr'] ?? 0 );
        $position    = floatval( $r['position'] ?? 0 );

        $existing_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$tbl} WHERE report_id=%d AND page_url=%s AND period_type=%s AND date_from=%s AND date_to=%s",
            $report_id, $page_url, $p_type, $d_from, $d_to
        ) );

        $row_data = [
            'report_id'   => $report_id,
            'period_type' => $p_type,
            'month_key'   => $m_key,
            'date_from'   => $d_from,
            'date_to'     => $d_to,
            'page_url'    => $page_url,
            'clicks'      => $clicks,
            'impressions' => $impressions,
            'ctr'         => $ctr,
            'position'    => $position,
            'trashed'     => 0,
        ];

        if ( $existing_id ) {
            $wpdb->update( $tbl, $row_data, [ 'id' => $existing_id ] );
        } else {
            $wpdb->insert( $tbl, $row_data );
        }
        $upserted++;
    }

    seo_dash_json_success( [ 'inserted' => $upserted ], "Saved {$upserted} SC custom range rows." );
} );

// ── Render Database Month Workspace HTML ─────────────────────────────────────
add_action( 'wp_ajax_seo_dash_render_month_workspace', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month     = sanitize_text_field( wp_unslash( $_POST['target_month'] ?? '' ) );
    $db_type   = sanitize_key( $_POST['db_type'] ?? 'ga' );

    if ( ! $report_id || ! $month ) seo_dash_json_error( 'Missing report ID or month.' );

    ob_start();

    // ── GA ────────────────────────────────────────────────────────
    if ( $db_type === 'ga' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . SEO_Dash_Database::$data_ga . " 
             WHERE report_id = %d AND (month_key = %s OR month_key != '') AND period_type NOT IN ('monthly', 'custom', 'daily') AND trashed = 0",
            $report_id, $month
        ), ARRAY_A );

        $ga_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_ga", [] );
        if ( ! is_array( $ga_type_map ) ) $ga_type_map = [];

        if (!function_exists('seo_dash_ga_type_v2_ajax')) {
            function seo_dash_ga_type_v2_ajax(array $row, array $map): string {
                $url = trim($row['page_url'] ?? '');
                if ($url) {
                    if (isset($map[$url])) return $map[$url];
                    if (isset($map[trailingslashit($url)])) return $map[trailingslashit($url)];
                    if (isset($map[untrailingslashit($url)])) return $map[untrailingslashit($url)];
                    $path = parse_url($url, PHP_URL_PATH);
                    if ($path) {
                        if (isset($map[$path])) return $map[$path];
                        if (isset($map[trailingslashit($path)])) return $map[trailingslashit($path)];
                        if (isset($map[untrailingslashit($path)])) return $map[untrailingslashit($path)];
                    }
                }
                $pt = trim($row['page_title'] ?? '');
                if ($pt) {
                    if (preg_match('/^\[sitemap:([a-z0-9_-]+)\]/i', $pt, $m)) return strtolower($m[1]);
                    if (preg_match('/^\[([a-z0-9_-]+)\]$/i', $pt, $m)) return strtolower($m[1]);
                }
                return 'other';
            }
        }

        // Group rows by type, then by URL
        $excluded_ga_types = [ 'gmb_posts', 'gmb_post' ];

        $groups = [];
        foreach ( $rows as $row ) {
            $t = seo_dash_ga_type_v2_ajax($row, $ga_type_map);
            if ( in_array( $t, $excluded_ga_types, true ) ) continue;
            $u = trim($row['page_url']);
            if (!$u) continue;

            $is_custom = ( $row['period_type'] === 'custom' || strpos( (string)$row['period_type'], 'custom' ) === 0 );
            $t_dest    = $is_custom ? 'custom_ranges' : $t;

            if ( ! isset($groups[$t_dest]) ) $groups[$t_dest] = [];
            if ( ! isset($groups[$t_dest][$u]) ) {
                $groups[$t_dest][$u] = [
                    'title' => $row['page_title'] ?: $u,
                    'url'   => $u,
                    'data'  => []
                ];
            }

            // Derive dynamic period key
            if ( in_array( $row['period_type'], ['7d', '30d', '90d', 'overall'], true ) ) {
                $period = $row['period_type'];
            } elseif ( $is_custom && ! empty( $row['date_from'] ) && ! empty( $row['date_to'] ) ) {
                $period = ( $row['date_from'] === $row['date_to'] ) ? $row['date_from'] : $row['date_from'] . ' to ' . $row['date_to'];
            } elseif ( ! empty( $row['month_key'] ) && strlen( $row['month_key'] ) === 7 ) {
                $period = $row['month_key'];
            } else {
                $period = $row['period_type'] ?: 'monthly';
            }

            if (!isset($groups[$t_dest][$u]['data'][$period])) {
                $groups[$t_dest][$u]['data'][$period] = $row;
            } else {
                $groups[$t_dest][$u]['data'][$period]['sessions'] += (int)$row['sessions'];
                $groups[$t_dest][$u]['data'][$period]['users'] += (int)$row['users'];
                $groups[$t_dest][$u]['data'][$period]['pageviews'] += (int)$row['pageviews'];
            }
        }
        
        foreach ($ga_type_map as $u => $t) {
            $u = trim((string)$u);
            if (!$u || !filter_var($u, FILTER_VALIDATE_URL)) continue;
            if ( in_array( $t, $excluded_ga_types, true ) ) continue;
            if (!isset($groups[$t])) $groups[$t] = [];
            if (!isset($groups[$t][$u])) {
                $groups[$t][$u] = [
                    'title' => $u,
                    'url'   => $u,
                    'data'  => []
                ];
            }
        }
        
        ksort($groups);

        $period_keys = ['7d', '30d', '90d', 'overall'];
        
        $ga_kpis = [
            '7d'      => ['users' => 0, 'sessions' => 0],
            '30d'     => ['users' => 0, 'sessions' => 0],
            '90d'     => ['users' => 0, 'sessions' => 0],
            'overall' => ['users' => 0, 'sessions' => 0]
        ];
        foreach ($groups as $type => $urls) {
            foreach ($urls as $u => $udata) {
                $is_trashed = false;
                foreach ($udata['data'] as $d) {
                    if (!empty($d['trashed'])) $is_trashed = true;
                }
                if ($is_trashed) continue;

                foreach ($period_keys as $pk) {
                    $d = $udata['data'][$pk] ?? null;
                    if ($d) {
                        $ga_kpis[$pk]['users']    += (int)($d['users'] ?? 0);
                        $ga_kpis[$pk]['sessions'] += (int)($d['sessions'] ?? 0);
                    }
                }
            }
        }

        $saved_property_kpis = get_option( "seo_dash_ga_kpis_{$report_id}", [] );
        if ( is_array( $saved_property_kpis ) && ! empty( $saved_property_kpis ) ) {
            foreach ( $period_keys as $k ) {
                if ( isset( $saved_property_kpis[$k]['users'] ) ) {
                    $ga_kpis[$k]['users']    = (int) $saved_property_kpis[$k]['users'];
                    $ga_kpis[$k]['sessions'] = (int) $saved_property_kpis[$k]['sessions'];
                }
            }
        }
        
        $colors = [
            '7d'      => ['bd' => '#0ea5e9', 'val' => '#0284c7'],
            '30d'     => ['bd' => '#8b5cf6', 'val' => '#7c3aed'],
            '90d'     => ['bd' => '#10b981', 'val' => '#059669'],
            'overall' => ['bd' => '#f59e0b', 'val' => '#d97706']
        ];
        $labels = [
            '7d'      => '7 DAYS',
            '30d'     => '30 DAYS',
            '90d'     => '90 DAYS',
            'overall' => 'OVERALL'
        ];
        ?>
        <!-- KPI Cards -->
        <div style="display:flex;gap:12px;margin:16px 20px;">
            <?php foreach ($period_keys as $k): ?>
            <div style="flex:1;background:var(--c-surf);border:1px solid var(--c-border);border-top:3px solid <?php echo $colors[$k]['bd']; ?>;border-radius:6px;padding:12px 16px;box-shadow:0 1px 3px rgba(15,23,42,0.05);">
                <div style="font-size:11px;font-weight:700;color:var(--c-muted);margin-bottom:8px;"><?php echo $labels[$k]; ?></div>
                <div style="font-size:20px;font-weight:800;color:<?php echo $colors[$k]['val']; ?>;margin-bottom:4px;"><?php echo number_format($ga_kpis[$k]['users']); ?></div>
                <div style="font-size:11px;color:var(--c-subtle);">Users · <?php echo number_format($ga_kpis[$k]['sessions']); ?> sess</div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Search Bar -->
        <div style="margin:0 20px 16px 20px;">
            <input type="text" class="seo-in db-table-search" data-dbtype="ga" placeholder="Search pages or URLs..." style="width:100%;max-width:100%;padding:8px 12px;font-size:14px;">
        </div>
        <!-- Sub-tabs for Types -->
        <div style="display:flex;gap:0;border-bottom:2px solid var(--c-border);margin-bottom:16px;background:var(--c-surf2);padding:0 20px;">
            <?php 
            $first = true;
            foreach ($groups as $type => $urls): 
                $active_count = 0;
                foreach ($urls as $u => $udata) {
                    $is_trashed = false;
                    foreach ($udata['data'] as $d) { if (!empty($d['trashed'])) $is_trashed = true; }
                    if (!$is_trashed) $active_count++;
                }
                $active_cls = $first ? 'db-type-subtab-active' : '';
                $color_sty  = $first ? 'border-bottom:3px solid var(--c-primary);color:var(--c-primary);' : 'border-bottom:3px solid transparent;color:var(--c-muted);';
            ?>
            <button class="db-type-subtab <?php echo $active_cls; ?>" data-type="<?php echo esc_attr($type); ?>"
                    style="padding:10px 18px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;margin-bottom:-2px;white-space:nowrap;<?php echo $color_sty; ?>">
                <?php echo esc_html($type === 'custom_ranges' ? '📅 Custom Ranges Data' : ucfirst(str_replace('_', ' ', $type))); ?> <span style="font-size:10px;opacity:.7;">(<?php echo $active_count; ?>)</span>
            </button>
            <?php $first = false; endforeach; ?>
            <?php if (empty($groups)): ?>
                <div style="padding:10px 18px;font-size:12px;color:var(--c-subtle);">No data fetched yet for this month.</div>
            <?php endif; ?>
        </div>

        <!-- Content for Types -->
        <?php 
        $first = true;
        foreach ($groups as $type => $urls): 
            $is_custom_type = ($type === 'custom_ranges');
            $dynamic_cols = [];
            if ($is_custom_type) {
                foreach ($urls as $u => $udata) {
                    foreach (array_keys($udata['data'] ?? []) as $pk) {
                        if (!in_array($pk, $dynamic_cols, true)) $dynamic_cols[] = $pk;
                    }
                }
                sort($dynamic_cols);
            }
            $render_period_keys = $is_custom_type ? $dynamic_cols : ['7d', '30d', '90d', 'overall'];
        ?>
        <div class="db-type-subpane" data-type="<?php echo esc_attr($type); ?>" style="<?php echo $first ? 'display:block;' : 'display:none;'; ?>">
            <div class="seo-table-wrap" style="max-height:600px;overflow-y:auto;margin:0 20px;border:1px solid var(--c-border);border-radius:4px;">
                <table class="seo-table no-filter" id="db-ga-<?php echo esc_attr($type); ?>-tbl" style="table-layout:fixed;width:100%;">
                    <thead style="position:sticky;top:0;z-index:2;background:var(--c-surf2);">
                        <tr>
                            <th rowspan="2" style="vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--c-border);width:34px;text-align:center;">
                                <input type="checkbox" class="db-ga-select-all-chk" data-type="<?php echo esc_attr($type); ?>" style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                            </th>
                            <th rowspan="2" style="vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--c-border);width:auto;">PAGE / <?php echo strtoupper(esc_html($is_custom_type ? 'Custom Ranges' : $type)); ?></th>
                            <th rowspan="2" style="vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--c-border);width:40px;text-align:center;">VISIT</th>
                            <?php if (empty($render_period_keys)): ?>
                                <th colspan="3" style="text-align:center;padding:12px;color:var(--c-muted);">No Custom Date Range Fetched Yet</th>
                            <?php else: ?>
                                <?php foreach ($render_period_keys as $pk): 
                                    $hdr = $is_custom_type ? '📅 ' . $pk : ($pk === '7d' ? '7 DAYS' : ($pk === '30d' ? '30 DAYS' : ($pk === '90d' ? '90 DAYS' : 'OVERALL')));
                                ?>
                                <th colspan="3" style="text-align:center;border-right:1px solid var(--c-border);border-bottom:1px solid var(--c-border);min-width:160px;<?php echo $is_custom_type ? 'color:#4f46e5;font-weight:700;' : ''; ?>"><?php echo esc_html($hdr); ?></th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <th rowspan="2" style="vertical-align:bottom;padding-bottom:12px;text-align:center;width:60px;">Actions</th>
                        </tr>
                        <tr>
                            <?php for ($i=0; $i<max(1, count($render_period_keys)); $i++): ?>
                            <th style="font-size:10px;text-align:right;width:53px;">Sess.</th>
                            <th style="font-size:10px;text-align:right;width:53px;">Users</th>
                            <th style="font-size:10px;text-align:right;border-right:1px solid var(--c-border);width:54px;">Views</th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($urls as $u => $udata) : 
                        $is_trashed = false;
                        foreach ($udata['data'] as $d) { if (!empty($d['trashed'])) $is_trashed = true; }
                    ?>
                    <tr class="<?php echo $is_trashed ? 'db-ga-trashed-row' : 'db-ga-active-row'; ?>" style="<?php echo $is_trashed ? 'display:none;background:rgba(239,68,68,0.15);' : ''; ?>">
                        <td style="text-align:center;border-right:1px solid var(--c-border);">
                            <input type="checkbox" class="db-ga-row-chk" value="<?php echo esc_attr($u); ?>" data-type="<?php echo esc_attr($type); ?>" style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                        </td>
                        <?php 
                            $disp_title = $udata['title'];
                            $disp_title = preg_replace('/^\[(?:sitemap:)?[a-z0-9_-]+\]\s*/i', '', $disp_title);
                            if (preg_match('/^https?:\/\//i', $disp_title) || preg_match('/^www\./i', $disp_title)) {
                                $path = parse_url($disp_title, PHP_URL_PATH);
                                $path = trim($path ?? '', '/');
                                if (!$path) {
                                    $disp_title = 'Home Page';
                                } else {
                                    $parts = explode('/', $path);
                                    $last = end($parts);
                                    $disp_title = ucwords(str_replace(['-', '_'], ' ', $last));
                                }
                            }
                        ?>
                        <td style="font-size:12px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-right:1px solid var(--c-border);"
                            title="<?php echo esc_attr($u); ?>">
                            <?php echo esc_html($disp_title); ?>
                        </td>
                        <td style="text-align:center;border-right:1px solid var(--c-border);">
                            <a href="<?php echo esc_url($u); ?>" target="_blank" style="color:var(--c-primary);text-decoration:none;font-size:14px;" title="Open URL">↗</a>
                        </td>
                        <?php if (empty($render_period_keys)): ?>
                            <td colspan="3" style="text-align:center;font-size:11px;color:var(--c-muted);">—</td>
                        <?php else: ?>
                            <?php foreach ($render_period_keys as $pk) : 
                                $d = $udata['data'][$pk] ?? null;
                                if ( ! $d && ! $is_custom_type ) {
                                    if ( $pk === '30d' ) {
                                        $d = $udata['data']['monthly'] ?? $udata['data'][$month] ?? null;
                                        if ( ! $d && ! empty( $udata['data'] ) ) {
                                            $d = reset( $udata['data'] );
                                        }
                                    } elseif ( $pk === 'overall' ) {
                                        $sum_s = 0; $sum_u = 0; $sum_v = 0; $found = false;
                                        foreach ( $udata['data'] as $sub_d ) {
                                            if ( is_array( $sub_d ) && ( ! empty( $sub_d['sessions'] ) || ! empty( $sub_d['users'] ) || ! empty( $sub_d['pageviews'] ) ) ) {
                                                $sum_s += (int)( $sub_d['sessions'] ?? 0 );
                                                $sum_u += (int)( $sub_d['users'] ?? 0 );
                                                $sum_v += (int)( $sub_d['pageviews'] ?? 0 );
                                                $found = true;
                                            }
                                        }
                                        if ( $found ) {
                                            $d = [ 'sessions' => $sum_s, 'users' => $sum_u, 'pageviews' => $sum_v ];
                                        }
                                    }
                                }
                                $sess  = $d ? number_format((int)($d['sessions'] ?? 0)) : '—';
                                $users = $d ? number_format((int)($d['users'] ?? 0)) : '—';
                                $views = $d ? number_format((int)($d['pageviews'] ?? 0)) : '—';
                            ?>
                            <td style="text-align:right;font-size:11px;"><?php echo $sess; ?></td>
                            <td style="text-align:right;font-size:11px;"><?php echo $users; ?></td>
                            <td style="text-align:right;font-size:11px;border-right:1px solid var(--c-border);padding-right:10px;"><?php echo $views; ?></td>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <td style="text-align:center;">
                            <?php $ids_to_delete = array_filter(array_column($udata['data'] ?? [], 'id')); ?>
                            <button class="seo-icon-btn seo-icon-btn-d seo-del-url-btn" data-table="ga" data-url="<?php echo esc_attr($u); ?>" data-ids="<?php echo implode(',', $ids_to_delete); ?>" title="Delete URL">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php $first = false; endforeach; ?>
        <?php
    } elseif ( $db_type === 'sc' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . SEO_Dash_Database::$data_sc . " 
             WHERE report_id = %d AND (month_key = %s OR month_key != '') AND period_type NOT IN ('monthly', 'custom', 'daily') AND trashed = 0",
            $report_id, $month
        ), ARRAY_A );

        $sc_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_sc", [] );
        if ( ! is_array( $sc_type_map ) ) $sc_type_map = [];

        // Group rows by type, then by URL
        // gmb_posts is a separate data type managed in its own section — exclude from SC tabs
        $excluded_sc_types = [ 'gmb_posts', 'gmb_post' ];

        $groups = [];
        foreach ( $rows as $row ) {
            $u = trim($row['page_url']);
            if (!$u) continue;
            $t = 'other';
            if (isset($sc_type_map[$u])) {
                $t = $sc_type_map[$u];
            } elseif (isset($sc_type_map[trailingslashit($u)])) {
                $t = $sc_type_map[trailingslashit($u)];
            } elseif (isset($sc_type_map[untrailingslashit($u)])) {
                $t = $sc_type_map[untrailingslashit($u)];
            } else {
                $u_path = parse_url($u, PHP_URL_PATH);
                if ($u_path && isset($sc_type_map[$u_path])) {
                    $t = $sc_type_map[$u_path];
                } elseif ($u_path && isset($sc_type_map[trailingslashit($u_path)])) {
                    $t = $sc_type_map[trailingslashit($u_path)];
                } elseif ($u_path && isset($sc_type_map[untrailingslashit($u_path)])) {
                    $t = $sc_type_map[untrailingslashit($u_path)];
                } else {
                    $t = seo_dash_guess_type_from_page_url($u);
                }
            }
            if ( in_array( $t, $excluded_sc_types, true ) ) continue;

            $is_custom = ( $row['period_type'] === 'custom' || strpos( (string)$row['period_type'], 'custom' ) === 0 );
            $t_dest    = $is_custom ? 'custom_ranges' : $t;

            if ( ! isset($groups[$t_dest]) ) $groups[$t_dest] = [];
            if ( ! isset($groups[$t_dest][$u]) ) {
                $groups[$t_dest][$u] = [
                    'title' => $u,
                    'url'   => $u,
                    'data'  => []
                ];
            }
            
            // Derive dynamic period key
            if ( in_array( $row['period_type'], ['7d', '30d', '90d', 'overall'], true ) ) {
                $period = $row['period_type'];
            } elseif ( $is_custom && ! empty( $row['date_from'] ) && ! empty( $row['date_to'] ) ) {
                $period = ( $row['date_from'] === $row['date_to'] ) ? $row['date_from'] : $row['date_from'] . ' to ' . $row['date_to'];
            } elseif ( ! empty( $row['month_key'] ) && strlen( $row['month_key'] ) === 7 ) {
                $period = $row['month_key'];
            } else {
                $period = $row['period_type'] ?: 'monthly';
            }

            $groups[$t_dest][$u]['data'][$period] = $row;
        }
        
        // Also include URLs from the sitemap that haven't been fetched yet
        // Skip gmb_posts entries — they are managed in their own dedicated section
        foreach ($sc_type_map as $u => $t) {
            $u = trim((string)$u);
            if (!$u || !filter_var($u, FILTER_VALIDATE_URL)) continue;
            if ( in_array( $t, $excluded_sc_types, true ) ) continue;
            if (!isset($groups[$t])) $groups[$t] = [];
            if (!isset($groups[$t][$u])) {
                $groups[$t][$u] = [
                    'title' => $u,
                    'url'   => $u,
                    'data'  => []
                ];
            }
        }
        
        ksort($groups);

        $period_keys = ['7d', '30d', '90d', 'overall'];
        
        $sc_kpis = [
            '7d'      => ['clicks' => 0, 'impressions' => 0],
            '30d'     => ['clicks' => 0, 'impressions' => 0],
            '90d'     => ['clicks' => 0, 'impressions' => 0],
            'overall' => ['clicks' => 0, 'impressions' => 0]
        ];
        foreach ($groups as $type => $urls) {
            foreach ($urls as $u => $udata) {
                $is_trashed = false;
                foreach ($udata['data'] as $d) {
                    if (!empty($d['trashed'])) $is_trashed = true;
                }
                if ($is_trashed) continue;

                foreach ($period_keys as $pk) {
                    $d = $udata['data'][$pk] ?? null;
                    if (!$d && $pk === '30d') {
                        $d = $udata['data']['monthly'] ?? null;
                    }
                    if ($d) {
                        $sc_kpis[$pk]['clicks']      += (int)($d['clicks'] ?? 0);
                        $sc_kpis[$pk]['impressions'] += (int)($d['impressions'] ?? 0);
                    }
                }
            }
        }
        
        $colors = [
            '7d'      => ['bd' => '#0ea5e9', 'val' => '#0284c7'],
            '30d'     => ['bd' => '#8b5cf6', 'val' => '#7c3aed'],
            '90d'     => ['bd' => '#10b981', 'val' => '#059669'],
            'overall' => ['bd' => '#f59e0b', 'val' => '#d97706']
        ];
        $labels = [
            '7d'      => '7 DAYS',
            '30d'     => '30 DAYS',
            '90d'     => '90 DAYS',
            'overall' => 'OVERALL'
        ];
        ?>
        <!-- KPI Cards -->
        <div style="display:flex;gap:12px;margin:16px 20px;">
            <?php foreach ($period_keys as $k): ?>
            <div style="flex:1;background:var(--c-surf);border:1px solid var(--c-border);border-top:3px solid <?php echo $colors[$k]['bd']; ?>;border-radius:6px;padding:12px 16px;box-shadow:0 1px 3px rgba(15,23,42,0.05);">
                <div style="font-size:11px;font-weight:700;color:var(--c-muted);margin-bottom:8px;"><?php echo $labels[$k]; ?></div>
                <div style="font-size:20px;font-weight:800;color:<?php echo $colors[$k]['val']; ?>;margin-bottom:4px;"><?php echo number_format($sc_kpis[$k]['clicks']); ?></div>
                <div style="font-size:11px;color:var(--c-subtle);">Clicks · <?php echo number_format($sc_kpis[$k]['impressions']); ?> imp</div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Search Bar -->
        <div style="margin:0 20px 16px 20px;">
            <input type="text" class="seo-in db-table-search" data-dbtype="sc" placeholder="Search pages or URLs..." style="width:100%;max-width:100%;padding:8px 12px;font-size:14px;">
        </div>
        <!-- Sub-tabs for Types -->
        <div style="display:flex;gap:0;border-bottom:2px solid var(--c-border);margin-bottom:16px;background:var(--c-surf2);padding:0 20px;">
            <?php 
            $first = true;
            foreach ($groups as $type => $urls): 
                $active_count = 0;
                foreach ($urls as $u => $udata) {
                    $is_trashed = false;
                    foreach ($udata['data'] as $d) { if (!empty($d['trashed'])) $is_trashed = true; }
                    if (!$is_trashed) $active_count++;
                }
                $active_cls = $first ? 'db-type-subtab-active' : '';
                $color_sty  = $first ? 'border-bottom:3px solid var(--c-primary);color:var(--c-primary);' : 'border-bottom:3px solid transparent;color:var(--c-muted);';
            ?>
            <button class="db-type-subtab <?php echo $active_cls; ?>" data-type="<?php echo esc_attr($type); ?>"
                    style="padding:10px 18px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;margin-bottom:-2px;white-space:nowrap;<?php echo $color_sty; ?>">
                <?php echo esc_html($type === 'custom_ranges' ? '📅 Custom Ranges Data' : ucfirst(str_replace('_', ' ', $type))); ?> <span style="font-size:10px;opacity:.7;">(<?php echo $active_count; ?>)</span>
            </button>
            <?php $first = false; endforeach; ?>
            <?php if (empty($groups)): ?>
                <div style="padding:10px 18px;font-size:12px;color:var(--c-subtle);">No data fetched yet for this month.</div>
            <?php endif; ?>
        </div>

        <!-- Content for Types -->
        <?php 
        $first = true;
        foreach ($groups as $type => $urls): 
            $is_custom_type = ($type === 'custom_ranges');
            $dynamic_cols = [];
            if ($is_custom_type) {
                foreach ($urls as $u => $udata) {
                    foreach (array_keys($udata['data'] ?? []) as $pk) {
                        if (!in_array($pk, $dynamic_cols, true)) $dynamic_cols[] = $pk;
                    }
                }
                sort($dynamic_cols);
            }
            $render_period_keys = $is_custom_type ? $dynamic_cols : ['7d', '30d', '90d', 'overall'];
        ?>
        <div class="db-type-subpane" data-type="<?php echo esc_attr($type); ?>" style="<?php echo $first ? 'display:block;' : 'display:none;'; ?>">
            <div style="overflow-x:auto;margin:0 20px;border:1px solid var(--c-border);border-radius:4px;">
                <table class="seo-table no-filter" style="width:100%;table-layout:fixed;">
                    <thead>
                    <tr style="background:var(--c-surf2);border-bottom:1px solid var(--c-border);text-align:left;">
                        <th style="padding:10px;text-align:center;width:40px;border-right:1px solid var(--c-border);">
                            <input type="checkbox" class="db-sc-select-all-chk" data-type="<?php echo esc_attr($type); ?>" style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                        </th>
                        <th style="padding:10px;color:var(--c-muted);font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:0.5px;border-right:1px solid var(--c-border);width:auto;">Page / <?php echo esc_html(ucfirst($is_custom_type ? 'Custom Ranges' : $type)); ?></th>
                        <th style="padding:10px;color:var(--c-muted);font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:0.5px;border-right:1px solid var(--c-border);width:40px;text-align:center;">Visit</th>
                        <?php if (empty($render_period_keys)): ?>
                            <th colspan="4" style="text-align:center;padding:6px;color:var(--c-muted);font-size:10px;font-weight:700;">No Custom Date Range Fetched Yet</th>
                        <?php else: ?>
                            <?php foreach ($render_period_keys as $pk) : 
                                $hdr = $is_custom_type ? '📅 ' . $pk : strtoupper($pk);
                            ?>
                                <th colspan="4" style="text-align:center;padding:6px;border-right:1px solid var(--c-border);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;min-width:160px;<?php echo $is_custom_type ? 'color:#4f46e5;' : 'color:var(--c-muted);'; ?>"><?php echo esc_html($hdr); ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <th style="padding:10px;text-align:center;color:var(--c-muted);font-weight:700;font-size:10px;letter-spacing:0.5px;width:60px;">Actions</th>
                    </tr>
                    <tr style="background:var(--c-surf2);border-bottom:1px solid var(--c-border);text-align:right;">
                        <th style="border-right:1px solid var(--c-border);"></th>
                        <th style="border-right:1px solid var(--c-border);"></th>
                        <th style="border-right:1px solid var(--c-border);"></th>
                        <?php for ($i=0; $i<max(1, count($render_period_keys)); $i++): ?>
                            <th style="padding:6px;font-size:9px;color:var(--c-muted);font-weight:600;width:40px;">Clk</th>
                            <th style="padding:6px;font-size:9px;color:var(--c-muted);font-weight:600;width:40px;">Imp</th>
                            <th style="padding:6px;font-size:9px;color:var(--c-muted);font-weight:600;width:40px;">CTR</th>
                            <th style="padding:6px;font-size:9px;color:var(--c-muted);font-weight:600;border-right:1px solid var(--c-border);width:40px;">Pos</th>
                        <?php endfor; ?>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($urls)): ?>
                    <tr><td colspan="<?php echo 3 + (max(1, count($render_period_keys))*4); ?>" style="padding:20px;text-align:center;color:var(--c-muted);">No data in this group.</td></tr>
                    <?php else: ?>
                    <?php foreach ($urls as $u => $udata) : 
                        $is_trashed = false;
                        foreach ($udata['data'] as $d) { if (!empty($d['trashed'])) $is_trashed = true; }
                    ?>
                    <tr class="<?php echo $is_trashed ? 'db-ga-trashed-row' : 'db-ga-active-row'; ?>" style="<?php echo $is_trashed ? 'display:none;background:rgba(239,68,68,0.15);' : ''; ?>">
                        <td style="text-align:center;border-right:1px solid var(--c-border);">
                            <input type="checkbox" class="db-sc-row-chk" value="<?php echo esc_attr($u); ?>" data-type="<?php echo esc_attr($type); ?>" style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                        </td>
                        <?php 
                            $disp_title = $udata['title'];
                            $disp_title = preg_replace('/^\[(?:sitemap:)?[a-z0-9_-]+\]\s*/i', '', $disp_title);
                            if (preg_match('/^https?:\/\//i', $disp_title) || preg_match('/^www\./i', $disp_title)) {
                                $path = parse_url($disp_title, PHP_URL_PATH);
                                $path = trim($path ?? '', '/');
                                if (!$path) {
                                    $disp_title = 'Home Page';
                                } else {
                                    $parts = explode('/', $path);
                                    $last = end($parts);
                                    $disp_title = ucwords(str_replace(['-', '_'], ' ', $last));
                                }
                            }
                        ?>
                        <td style="font-size:12px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-right:1px solid var(--c-border);"
                            title="<?php echo esc_attr($u); ?>">
                            <?php echo esc_html($disp_title); ?>
                        </td>
                        <td style="text-align:center;border-right:1px solid var(--c-border);">
                            <a href="<?php echo esc_url($u); ?>" target="_blank" style="color:var(--c-primary);text-decoration:none;font-size:14px;" title="Open URL">↗</a>
                        </td>
                        <?php if (empty($render_period_keys)): ?>
                            <td colspan="4" style="text-align:center;font-size:11px;color:var(--c-muted);">—</td>
                        <?php else: ?>
                            <?php foreach ($render_period_keys as $pk) : 
                                $d = $udata['data'][$pk] ?? null;
                                if ( ! $d && ! $is_custom_type ) {
                                    if ( $pk === '30d' ) {
                                        $d = $udata['data']['monthly'] ?? $udata['data'][$month] ?? null;
                                        if ( ! $d && ! empty( $udata['data'] ) ) {
                                            $d = reset( $udata['data'] );
                                        }
                                    } elseif ( $pk === 'overall' ) {
                                        $sum_c = 0; $sum_i = 0; $found = false;
                                        foreach ( $udata['data'] as $sub_d ) {
                                            if ( is_array( $sub_d ) && ( ! empty( $sub_d['clicks'] ) || ! empty( $sub_d['impressions'] ) ) ) {
                                                $sum_c += (int)( $sub_d['clicks'] ?? 0 );
                                                $sum_i += (int)( $sub_d['impressions'] ?? 0 );
                                                $found = true;
                                            }
                                        }
                                        if ( $found ) {
                                            $ctr_calc = $sum_i > 0 ? ( $sum_c / $sum_i ) * 100 : 0;
                                            $d = [ 'clicks' => $sum_c, 'impressions' => $sum_i, 'ctr' => $ctr_calc, 'position' => 0 ];
                                        }
                                    }
                                }
                                $clk = $d ? number_format((int)($d['clicks'] ?? 0)) : '—';
                                $imp = $d ? number_format((int)($d['impressions'] ?? 0)) : '—';
                                $ctr = $d ? (number_format((float)($d['ctr'] ?? 0), 1) . '%') : '—';
                                $pos = $d ? number_format((float)($d['position'] ?? 0), 1) : '—';
                            ?>
                            <td style="text-align:right;font-size:11px;"><?php echo $clk; ?></td>
                            <td style="text-align:right;font-size:11px;"><?php echo $imp; ?></td>
                            <td style="text-align:right;font-size:11px;"><?php echo $ctr; ?></td>
                            <td style="text-align:right;font-size:11px;border-right:1px solid var(--c-border);padding-right:10px;"><?php echo $pos; ?></td>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <td style="text-align:center;">
                            <?php $ids_to_delete = array_filter(array_column($udata['data'] ?? [], 'id')); ?>
                            <button class="seo-icon-btn seo-icon-btn-d seo-del-url-btn" data-table="sc" data-url="<?php echo esc_attr($u); ?>" data-ids="<?php echo implode(',', $ids_to_delete); ?>" title="Delete URL">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php $first = false; endforeach; ?>
        <?php
    }

    $html = ob_get_clean();
    wp_send_json_success( [ 'html' => $html ] );
} );

// ── AJAX: Fast Server-Side Pagination Slice for Workspace Sub-Tab ───────────
add_action( 'wp_ajax_seo_dash_get_workspace_page', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;

    $report_id   = intval( $_POST['report_id'] ?? 0 );
    $month       = sanitize_text_field( wp_unslash( $_POST['target_month'] ?? '' ) );
    $db_type     = sanitize_key( $_POST['db_type'] ?? 'ga' );
    $subtype     = sanitize_key( $_POST['subtype'] ?? '' );
    $page        = max( 1, intval( $_POST['page'] ?? 1 ) );
    $per_page    = sanitize_text_field( $_POST['per_page'] ?? '50' );
    $search      = trim( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ) );
    $trash_view  = intval( $_POST['trash_view'] ?? 0 );

    if ( ! $report_id || ! $month || ! $subtype ) {
        seo_dash_json_error( 'Missing parameters.' );
    }

    $limit = ( $per_page === 'all' ) ? 999999 : max( 10, intval( $per_page ) );

    // ── GA Handling ──────────────────────────────────────────────────────────
    if ( $db_type === 'ga' ) {
        $table = SEO_Dash_Database::$data_ga;
        $map   = get_option( "seo_dash_sitemap_types_{$report_id}_ga", [] );
        if ( ! is_array( $map ) ) $map = [];

        // Collect all matching URLs for this subtype
        $all_candidate_urls = [];
        foreach ( $map as $u => $t ) {
            if ( $t === $subtype || ( $subtype === 'other' && ! $t ) ) {
                if ( ! in_array( $u, $all_candidate_urls, true ) && filter_var( $u, FILTER_VALIDATE_URL ) ) {
                    $all_candidate_urls[] = $u;
                }
            }
        }

        // Also query DB for URLs tagged with [sitemap:{$subtype}] or belonging to this report
        if ( $subtype === 'custom_ranges' ) {
            $db_urls = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT page_url FROM {$table} WHERE report_id = %d AND (period_type = 'custom' OR period_type LIKE 'custom%%') AND trashed = %d",
                $report_id, $trash_view
            ) );
            $all_candidate_urls = array_unique( array_merge( $all_candidate_urls, (array)$db_urls ) );
        } else {
            $tag_like = '%[sitemap:' . $wpdb->esc_like( $subtype ) . ']%';
            $tagged_urls = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT page_url FROM {$table} WHERE report_id = %d AND page_title LIKE %s AND trashed = %d",
                $report_id, $tag_like, $trash_view
            ) );
            if ( ! empty( $tagged_urls ) ) {
                $all_candidate_urls = array_unique( array_merge( $all_candidate_urls, (array)$tagged_urls ) );
            }
        }

        // Apply search filtering
        $filtered_urls = [];
        if ( $search !== '' ) {
            $s_low = strtolower( $search );
            foreach ( $all_candidate_urls as $u ) {
                if ( strpos( strtolower( $u ), $s_low ) !== false ) {
                    $filtered_urls[] = $u;
                }
            }
        } else {
            $filtered_urls = $all_candidate_urls;
        }

        $total_rows  = count( $filtered_urls );
        $total_pages = max( 1, (int) ceil( $total_rows / $limit ) );
        if ( $page > $total_pages ) $page = $total_pages;
        $offset = ( $page - 1 ) * $limit;
        $page_urls = array_slice( $filtered_urls, $offset, $limit );

        // Fetch data rows for this page slice
        $rows_by_url = [];
        if ( ! empty( $page_urls ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $page_urls ), '%s' ) );
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE report_id = %d AND page_url IN ({$placeholders})",
                array_merge( [ $report_id ], $page_urls )
            );
            $db_data = $wpdb->get_results( $sql, ARRAY_A );
            foreach ( (array) $db_data as $row ) {
                $u = trim( $row['page_url'] );
                if ( ! isset( $rows_by_url[ $u ] ) ) $rows_by_url[ $u ] = [];
                $pk = $row['period_type'] ?: 'monthly';
                if ( ! isset( $rows_by_url[ $u ][ $pk ] ) ) {
                    $rows_by_url[ $u ][ $pk ] = $row;
                } else {
                    $rows_by_url[ $u ][ $pk ]['sessions']  += (int) $row['sessions'];
                    $rows_by_url[ $u ][ $pk ]['users']     += (int) $row['users'];
                    $rows_by_url[ $u ][ $pk ]['pageviews'] += (int) $row['pageviews'];
                }
            }
        }

        $render_period_keys = [ '7d', '30d', '90d', 'overall' ];

        ob_start();
        if ( empty( $page_urls ) ) {
            ?>
            <tr>
                <td colspan="16" style="text-align:center;padding:30px;color:var(--c-muted);font-size:13px;">
                    No pages found matching criteria.
                </td>
            </tr>
            <?php
        } else {
            foreach ( $page_urls as $u ) {
                $udata = $rows_by_url[ $u ] ?? [];
                $disp_title = '';
                foreach ( $udata as $sub_d ) {
                    if ( ! empty( $sub_d['page_title'] ) ) {
                        $disp_title = $sub_d['page_title'];
                        break;
                    }
                }
                if ( ! $disp_title ) $disp_title = $u;
                $disp_title = preg_replace('/^\[(?:sitemap:)?[a-z0-9_-]+\]\s*/i', '', $disp_title);
                if ( preg_match('/^https?:\/\//i', $disp_title) || preg_match('/^www\./i', $disp_title) ) {
                    $path = trim( parse_url($disp_title, PHP_URL_PATH) ?? '', '/' );
                    if ( ! $path ) {
                        $disp_title = 'Home Page';
                    } else {
                        $parts = explode('/', $path);
                        $disp_title = ucwords(str_replace(['-', '_'], ' ', end($parts)));
                    }
                }
                ?>
                <tr class="<?php echo $trash_view ? 'db-ga-trashed-row' : 'db-ga-active-row'; ?>">
                    <td style="text-align:center;border-right:1px solid var(--c-border);">
                        <input type="checkbox" class="db-ga-row-chk" value="<?php echo esc_attr($u); ?>" data-type="<?php echo esc_attr($subtype); ?>" style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                    </td>
                    <td style="font-size:12px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-right:1px solid var(--c-border);" title="<?php echo esc_attr($u); ?>">
                        <?php echo esc_html($disp_title); ?>
                    </td>
                    <td style="text-align:center;border-right:1px solid var(--c-border);">
                        <a href="<?php echo esc_url($u); ?>" target="_blank" style="color:var(--c-primary);text-decoration:none;font-size:14px;" title="Open URL">↗</a>
                    </td>
                    <?php foreach ($render_period_keys as $pk) :
                        $d = $udata[$pk] ?? null;
                        if ( ! $d ) {
                            if ( $pk === '30d' ) {
                                $d = $udata['monthly'] ?? $udata[$month] ?? reset($udata) ?: null;
                            } elseif ( $pk === 'overall' ) {
                                $sum_s = 0; $sum_u = 0; $sum_v = 0; $found = false;
                                foreach ($udata as $sub_d) {
                                    if ( is_array($sub_d) && (!empty($sub_d['sessions']) || !empty($sub_d['users']) || !empty($sub_d['pageviews'])) ) {
                                        $sum_s += (int)($sub_d['sessions'] ?? 0);
                                        $sum_u += (int)($sub_d['users'] ?? 0);
                                        $sum_v += (int)($sub_d['pageviews'] ?? 0);
                                        $found = true;
                                    }
                                }
                                if ($found) $d = ['sessions' => $sum_s, 'users' => $sum_u, 'pageviews' => $sum_v];
                            }
                        }
                        $sess  = $d ? number_format((int)($d['sessions'] ?? 0)) : '—';
                        $users = $d ? number_format((int)($d['users'] ?? 0)) : '—';
                        $views = $d ? number_format((int)($d['pageviews'] ?? 0)) : '—';
                    ?>
                    <td style="text-align:right;font-size:11px;"><?php echo $sess; ?></td>
                    <td style="text-align:right;font-size:11px;"><?php echo $users; ?></td>
                    <td style="text-align:right;font-size:11px;border-right:1px solid var(--c-border);padding-right:10px;"><?php echo $views; ?></td>
                    <?php endforeach; ?>
                    <td style="text-align:center;">
                        <?php $ids_to_delete = array_filter(array_column($udata, 'id')); ?>
                        <button class="seo-icon-btn seo-icon-btn-d seo-del-url-btn" data-table="ga" data-url="<?php echo esc_attr($u); ?>" data-ids="<?php echo implode(',', $ids_to_delete); ?>" title="Delete URL">🗑</button>
                    </td>
                </tr>
                <?php
            }
        }
        $rows_html = ob_get_clean();

        wp_send_json_success([
            'html'        => $rows_html,
            'total'       => $total_rows,
            'page'        => $page,
            'total_pages' => $total_pages,
            'start'       => $total_rows ? $offset + 1 : 0,
            'end'         => min($total_rows, $offset + count($page_urls))
        ]);
    }

    // ── SC Handling ──────────────────────────────────────────────────────────
    if ( $db_type === 'sc' ) {
        $table = SEO_Dash_Database::$data_sc;
        $map   = get_option( "seo_dash_sitemap_types_{$report_id}_sc", [] );
        if ( ! is_array( $map ) ) $map = [];

        $all_candidate_urls = [];
        foreach ( $map as $u => $t ) {
            if ( $t === $subtype || ( $subtype === 'other' && ! $t ) ) {
                if ( ! in_array( $u, $all_candidate_urls, true ) && filter_var( $u, FILTER_VALIDATE_URL ) ) {
                    $all_candidate_urls[] = $u;
                }
            }
        }

        if ( $subtype === 'custom_ranges' ) {
            $db_urls = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT page_url FROM {$table} WHERE report_id = %d AND (period_type = 'custom' OR period_type LIKE 'custom%%') AND trashed = %d",
                $report_id, $trash_view
            ) );
            $all_candidate_urls = array_unique( array_merge( $all_candidate_urls, (array)$db_urls ) );
        } else {
            $tag_like = '%[sitemap:' . $wpdb->esc_like( $subtype ) . ']%';
            $tagged_urls = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT page_url FROM {$table} WHERE report_id = %d AND query LIKE %s AND trashed = %d",
                $report_id, $tag_like, $trash_view
            ) );
            if ( ! empty( $tagged_urls ) ) {
                $all_candidate_urls = array_unique( array_merge( $all_candidate_urls, (array)$tagged_urls ) );
            }
        }

        $filtered_urls = [];
        if ( $search !== '' ) {
            $s_low = strtolower( $search );
            foreach ( $all_candidate_urls as $u ) {
                if ( strpos( strtolower( $u ), $s_low ) !== false ) {
                    $filtered_urls[] = $u;
                }
            }
        } else {
            $filtered_urls = $all_candidate_urls;
        }

        $total_rows  = count( $filtered_urls );
        $total_pages = max( 1, (int) ceil( $total_rows / $limit ) );
        if ( $page > $total_pages ) $page = $total_pages;
        $offset = ( $page - 1 ) * $limit;
        $page_urls = array_slice( $filtered_urls, $offset, $limit );

        $rows_by_url = [];
        if ( ! empty( $page_urls ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $page_urls ), '%s' ) );
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE report_id = %d AND page_url IN ({$placeholders})",
                array_merge( [ $report_id ], $page_urls )
            );
            $db_data = $wpdb->get_results( $sql, ARRAY_A );
            foreach ( (array) $db_data as $row ) {
                $u = trim( $row['page_url'] );
                if ( ! isset( $rows_by_url[ $u ] ) ) $rows_by_url[ $u ] = [];
                $pk = $row['period_type'] ?: 'monthly';
                if ( ! isset( $rows_by_url[ $u ][ $pk ] ) ) {
                    $rows_by_url[ $u ][ $pk ] = $row;
                } else {
                    $rows_by_url[ $u ][ $pk ]['clicks']      += (int) $row['clicks'];
                    $rows_by_url[ $u ][ $pk ]['impressions'] += (int) $row['impressions'];
                }
            }
        }

        $render_period_keys = [ '7d', '30d', '90d', 'overall' ];

        ob_start();
        if ( empty( $page_urls ) ) {
            ?>
            <tr>
                <td colspan="20" style="text-align:center;padding:30px;color:var(--c-muted);font-size:13px;">
                    No pages found matching criteria.
                </td>
            </tr>
            <?php
        } else {
            foreach ( $page_urls as $u ) {
                $udata = $rows_by_url[ $u ] ?? [];
                $disp_title = $u;
                $disp_title = preg_replace('/^\[(?:sitemap:)?[a-z0-9_-]+\]\s*/i', '', $disp_title);
                if ( preg_match('/^https?:\/\//i', $disp_title) || preg_match('/^www\./i', $disp_title) ) {
                    $path = trim( parse_url($disp_title, PHP_URL_PATH) ?? '', '/' );
                    if ( ! $path ) {
                        $disp_title = 'Home Page';
                    } else {
                        $parts = explode('/', $path);
                        $disp_title = ucwords(str_replace(['-', '_'], ' ', end($parts)));
                    }
                }
                ?>
                <tr class="<?php echo $trash_view ? 'db-ga-trashed-row' : 'db-ga-active-row'; ?>">
                    <td style="padding:10px;text-align:center;border-right:1px solid var(--c-border);">
                        <input type="checkbox" class="db-sc-row-chk" value="<?php echo esc_attr($u); ?>" data-type="<?php echo esc_attr($subtype); ?>" style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                    </td>
                    <td style="padding:10px;font-size:12px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-right:1px solid var(--c-border);" title="<?php echo esc_attr($u); ?>">
                        <?php echo esc_html($disp_title); ?>
                    </td>
                    <td style="padding:10px;text-align:center;border-right:1px solid var(--c-border);">
                        <a href="<?php echo esc_url($u); ?>" target="_blank" style="color:var(--c-primary);text-decoration:none;font-size:14px;" title="Open URL">↗</a>
                    </td>
                    <?php foreach ($render_period_keys as $pk) :
                        $d = $udata[$pk] ?? null;
                        if ( ! $d && $pk === '30d' ) {
                            $d = $udata['monthly'] ?? $udata[$month] ?? reset($udata) ?: null;
                        }
                        $clk = $d ? number_format((int)($d['clicks'] ?? 0)) : '—';
                        $imp = $d ? number_format((int)($d['impressions'] ?? 0)) : '—';
                        $ctr = $d ? (number_format((float)($d['ctr'] ?? 0), 1) . '%') : '—';
                        $pos = $d ? number_format((float)($d['position'] ?? 0), 1) : '—';
                    ?>
                    <td style="text-align:right;font-size:11px;"><?php echo $clk; ?></td>
                    <td style="text-align:right;font-size:11px;"><?php echo $imp; ?></td>
                    <td style="text-align:right;font-size:11px;"><?php echo $ctr; ?></td>
                    <td style="text-align:right;font-size:11px;border-right:1px solid var(--c-border);padding-right:10px;"><?php echo $pos; ?></td>
                    <?php endforeach; ?>
                    <td style="text-align:center;">
                        <?php $ids_to_delete = array_filter(array_column($udata, 'id')); ?>
                        <button class="seo-icon-btn seo-icon-btn-d seo-del-url-btn" data-table="sc" data-url="<?php echo esc_attr($u); ?>" data-ids="<?php echo implode(',', $ids_to_delete); ?>" title="Delete URL">🗑</button>
                    </td>
                </tr>
                <?php
            }
        }
        $rows_html = ob_get_clean();

        wp_send_json_success([
            'html'        => $rows_html,
            'total'       => $total_rows,
            'page'        => $page,
            'total_pages' => $total_pages,
            'start'       => $total_rows ? $offset + 1 : 0,
            'end'         => min($total_rows, $offset + count($page_urls))
        ]);
    }
} );

// ── Set Active Frontend Month ────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_set_active_month', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month     = sanitize_text_field( wp_unslash( $_POST['target_month'] ?? '' ) );
    $db_type   = sanitize_key( $_POST['db_type'] ?? 'ga' );
    
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
    
    update_option( "seo_dash_active_month_{$report_id}_{$db_type}", $month );
    wp_send_json_success( null, 'Active month updated.' );
} );

/**
 * Automatically sync fetched URLs to Blog Posts or Service Pages tabs
 * based on their detected type in the sitemap mapping.
 */
/**
 * Build a list of NEW URLs (not already in service/blog pages) from a fetch result.
 * Returns an array: [ 'service' => [url=>title,...], 'blog' => [url=>title,...] ]
 * Does NOT auto-save anything — the admin must confirm via seo_dash_confirm_add_to_pages.
 */
function seo_dash_get_pending_page_urls( $report_id, $urls_with_titles ) {
    $service_types = [ 'page', 'service' ]; // ONLY page and service types belong in Service Pages
    $blog_types    = [ 'post', 'blog', 'article', 'news', 'category', 'tag' ];

    $ga_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_ga", [] );
    $sc_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_sc", [] );
    $type_map    = array_merge( (array)$ga_type_map, (array)$sc_type_map );

    $service_map = get_option( "seo_dash_custom_pages_{$report_id}_service", [] );
    $blog_map    = get_option( "seo_dash_custom_pages_{$report_id}_blog", [] );
    if ( ! is_array( $service_map ) ) $service_map = [];
    if ( ! is_array( $blog_map ) )    $blog_map    = [];

    $pending = [ 'service' => [], 'blog' => [] ];

    foreach ( $urls_with_titles as $url => $title ) {
        if ( ! $url ) continue;

        $url_type = '';
        if ( isset( $type_map[ $url ] ) ) {
            $url_type = $type_map[ $url ];
        } elseif ( isset( $type_map[ trailingslashit( $url ) ] ) ) {
            $url_type = $type_map[ trailingslashit( $url ) ];
        } elseif ( isset( $type_map[ untrailingslashit( $url ) ] ) ) {
            $url_type = $type_map[ untrailingslashit( $url ) ];
        } else {
            $path = parse_url( $url, PHP_URL_PATH );
            if ( $path && isset( $type_map[ $path ] ) ) {
                $url_type = $type_map[ $path ];
            } elseif ( $path && isset( $type_map[ trailingslashit( $path ) ] ) ) {
                $url_type = $type_map[ trailingslashit( $path ) ];
            } elseif ( $path && isset( $type_map[ untrailingslashit( $path ) ] ) ) {
                $url_type = $type_map[ untrailingslashit( $path ) ];
            }
        }

        if ( ! $url_type && $title ) {
            if ( preg_match( '/^\[(?:sitemap:)?([a-z0-9_-]+)\]/i', $title, $m ) ) {
                $url_type = strtolower( $m[1] );
            }
        }
        $url_type = $url_type ?: 'other';

        // Clean title
        $clean_title = preg_replace( '/^\[(?:sitemap:)?[a-z0-9_-]+\]\s*/i', '', $title );
        $clean_title = $clean_title ?: $url;

        if ( in_array( $url_type, $blog_types, true ) ) {
            // Blog type — add to blog pages if not already there
            if ( ! isset( $blog_map[ $url ] ) ) {
                $pending['blog'][ $url ] = $clean_title;
            }
        } elseif ( in_array( $url_type, $service_types, true ) ) {
            // ONLY page and service types go to Service Pages
            if ( ! isset( $service_map[ $url ] ) ) {
                $pending['service'][ $url ] = $clean_title;
            }
        }
        // Specific types like city, author, product, other are NOT added to Service Pages
    }

    return $pending;
}

/**
 * Actually save confirmed URLs to service/blog pages.
 * Called by seo_dash_confirm_add_to_pages AJAX action.
 */
function seo_dash_save_confirmed_page_urls( $report_id, $service_urls, $blog_urls ) {
    $service_map = get_option( "seo_dash_custom_pages_{$report_id}_service", [] );
    $blog_map    = get_option( "seo_dash_custom_pages_{$report_id}_blog", [] );
    $ga_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_ga", [] );
    $sc_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_sc", [] );
    if ( ! is_array( $service_map ) ) $service_map = [];
    if ( ! is_array( $blog_map ) )    $blog_map    = [];
    if ( ! is_array( $ga_type_map ) ) $ga_type_map = [];
    if ( ! is_array( $sc_type_map ) ) $sc_type_map = [];

    $added_service = 0;
    $added_blog    = 0;

    foreach ( $service_urls as $url => $title ) {
        $url = sanitize_text_field( $url );
        if ( ! $url ) continue;
        if ( ! isset( $service_map[ $url ] ) ) {
            $service_map[ $url ] = [
                'url'          => $url,
                'title'        => sanitize_text_field( $title ?: $url ),
                'keyword'      => '',
                'ranked_page'  => '',
                'ai_overview'  => 0,
                'month'        => '',
                'publish_date' => '',
            ];
            $added_service++;
        }
        if ( ! isset( $ga_type_map[ $url ] ) || $ga_type_map[ $url ] === 'other' ) $ga_type_map[ $url ] = 'page';
        if ( ! isset( $sc_type_map[ $url ] ) || $sc_type_map[ $url ] === 'other' ) $sc_type_map[ $url ] = 'page';
    }

    foreach ( $blog_urls as $url => $title ) {
        $url = sanitize_text_field( $url );
        if ( ! $url ) continue;
        if ( ! isset( $blog_map[ $url ] ) ) {
            $blog_map[ $url ] = [
                'url'          => $url,
                'title'        => sanitize_text_field( $title ?: $url ),
                'keyword'      => '',
                'ranked_page'  => '',
                'ai_overview'  => 0,
                'month'        => '',
                'publish_date' => '',
            ];
            $added_blog++;
        }
        if ( ! isset( $ga_type_map[ $url ] ) || $ga_type_map[ $url ] === 'other' ) $ga_type_map[ $url ] = 'post';
        if ( ! isset( $sc_type_map[ $url ] ) || $sc_type_map[ $url ] === 'other' ) $sc_type_map[ $url ] = 'post';
    }

    update_option( "seo_dash_custom_pages_{$report_id}_service", $service_map );
    update_option( "seo_dash_custom_pages_{$report_id}_blog",    $blog_map );
    update_option( "seo_dash_sitemap_types_{$report_id}_ga",     $ga_type_map );
    update_option( "seo_dash_sitemap_types_{$report_id}_sc",     $sc_type_map );

    return [ 'service' => $added_service, 'blog' => $added_blog ];
}

// Keep the old function signature for any other callers but make it a no-op for auto-add.
// It now just updates titles of EXISTING entries without adding new ones.
function seo_dash_auto_sync_fetched_urls( $report_id, $urls_with_titles, $fetch_kpis = true ) {
    $service_map = get_option( "seo_dash_custom_pages_{$report_id}_service", [] );
    $blog_map    = get_option( "seo_dash_custom_pages_{$report_id}_blog", [] );
    if ( ! is_array( $service_map ) ) $service_map = [];
    if ( ! is_array( $blog_map ) )    $blog_map    = [];

    $changed_service = false;
    $changed_blog    = false;
    $sync_kpis_urls  = [];

    // Only UPDATE titles for already-existing entries — never auto-add new ones.
    foreach ( $urls_with_titles as $url => $title ) {
        if ( ! $url ) continue;
        $clean = preg_replace( '/^\[(?:sitemap:)?[a-z0-9_-]+\]\s*/i', '', (string)$title );

        if ( isset( $service_map[ $url ] ) ) {
            if ( $clean && ( empty( $service_map[$url]['title'] ) || $service_map[$url]['title'] === $url ) ) {
                $service_map[$url]['title'] = $clean;
                $changed_service = true;
            }
            $sync_kpis_urls[] = $url;
        } elseif ( isset( $blog_map[ $url ] ) ) {
            if ( $clean && ( empty( $blog_map[$url]['title'] ) || $blog_map[$url]['title'] === $url ) ) {
                $blog_map[$url]['title'] = $clean;
                $changed_blog = true;
            }
            $sync_kpis_urls[] = $url;
        }
    }

    if ( $changed_service ) update_option( "seo_dash_custom_pages_{$report_id}_service", $service_map );
    if ( $changed_blog )    update_option( "seo_dash_custom_pages_{$report_id}_blog",    $blog_map );

    if ( $fetch_kpis && ! empty( $sync_kpis_urls ) ) {
        seo_dash_fetch_kpis_for_urls( $report_id, $sync_kpis_urls );
    }
}


// ── Confirm add fetched URLs to Service Pages / Blog Posts ──────────────────
add_action( 'wp_ajax_seo_dash_confirm_add_to_pages', function () {
    seo_dash_verify_admin_ajax();

    $report_id    = intval( wp_unslash( $_POST['report_id'] ?? 0 ) );
    $service_urls = isset( $_POST['service_urls'] ) && is_array( $_POST['service_urls'] ) ? $_POST['service_urls'] : [];
    $blog_urls    = isset( $_POST['blog_urls'] )    && is_array( $_POST['blog_urls'] )    ? $_POST['blog_urls']    : [];

    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
    if ( empty( $service_urls ) && empty( $blog_urls ) ) {
        seo_dash_json_error( 'No URLs provided.' );
    }

    // Sanitize input: each item is url=>title
    $clean_service = [];
    foreach ( $service_urls as $url => $title ) {
        $u = sanitize_text_field( wp_unslash( $url ) );
        if ( $u ) $clean_service[ $u ] = sanitize_text_field( wp_unslash( $title ) );
    }
    $clean_blog = [];
    foreach ( $blog_urls as $url => $title ) {
        $u = sanitize_text_field( wp_unslash( $url ) );
        if ( $u ) $clean_blog[ $u ] = sanitize_text_field( wp_unslash( $title ) );
    }

    $result = seo_dash_save_confirmed_page_urls( $report_id, $clean_service, $clean_blog );

    SEO_Dash_Database::log_activity(
        'pages_confirmed_add', 'success',
        "Admin confirmed: added {$result['service']} service page(s) and {$result['blog']} blog post(s).",
        'report', $report_id
    );

    seo_dash_json_success( $result, "✅ Added {$result['service']} service page(s) and {$result['blog']} blog post(s)." );
} );


/**
 * Fetch and upsert 30d and overall traffic KPIs from GA for a specific list of URLs.
 * This ensures newly added Service Pages / Blog Posts have traffic data immediately.
 */
function seo_dash_fetch_kpis_for_urls( $report_id, $urls ) {
    if ( empty( $urls ) ) return;
    $urls = array_unique($urls);

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'ga' );
    $property    = sanitize_text_field( wp_unslash( $_POST['property'] ?? $_POST['ga_property'] ?? '' ) );
    if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
        require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
    }
    $global_assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
    $global_id          = is_array( $global_assignments ) ? ( $global_assignments['global'] ?? '' ) : '';
    if ( $global_id ) {
        $g = seo_dash_get_global_integration_by_id( $global_id );
        if ( $g ) {
            if ( ! $property ) $property = $g['ga4_property_id'] ?? '';
            if ( ! $integration && ! empty( $g['ga4_json_key'] ) ) {
                $raw         = function_exists( 'seo_dash_sec_decrypt' ) ? seo_dash_sec_decrypt( $g['ga4_json_key'] ) : $g['ga4_json_key'];
                $integration = [ 'credentials' => json_decode( $raw, true ) ];
            }
        }
    }
    
    if ( ! $property ) {
        $property = get_option( "seo_dash_report_property_{$report_id}_ga", '' );
    }

    if ( ! $integration ) return;
    $creds = is_array( $integration['credentials'] ) ? $integration['credentials'] : [];
    if ( empty( $creds ) ) return;

    $token = seo_dash_get_google_token( $creds, 'https://www.googleapis.com/auth/analytics.readonly' );
    if ( ! $token ) {
        SEO_Dash_Database::log_activity( 'kpi_fetch_failed', 'error', 'Could not obtain GA token in KPI fetch.', 'report', $report_id );
        return;
    }
    $property = preg_replace( '/^properties\//', '', $property );
    if ( ! $property ) {
        SEO_Dash_Database::log_activity( 'kpi_fetch_failed', 'error', 'Property ID is missing in KPI fetch.', 'report', $report_id );
        return;
    }

    $target_month = sanitize_text_field( wp_unslash( $_POST['month_key'] ?? '' ) );
    if ( ! $target_month ) {
        $target_month = get_option( "seo_dash_active_month_{$report_id}_ga", '' );
    }
    if ( ! $target_month || $target_month === 'None' ) {
        // Find the latest available month in the DB, just like the UI does
        global $wpdb;
        $latest_month = $wpdb->get_var( $wpdb->prepare(
            "SELECT month_key FROM " . SEO_Dash_Database::$data_ga . " WHERE report_id=%d ORDER BY month_key DESC LIMIT 1",
            $report_id
        ) );
        $target_month = $latest_month ?: date('Y-m'); 
    }

    $overall_start = date( 'Y-m-d', strtotime( '-365 days' ) );
    $today         = date( 'Y-m-d' );
    $periods = [
        '30d'     => [ 'start' => date( 'Y-m-d', strtotime( '-30 days' ) ),  'end' => $today ],
        'overall' => [ 'start' => $overall_start, 'end' => $today ],
    ];

    $path_map = [];
    foreach ( $urls as $full_url ) {
        $path     = parse_url( $full_url, PHP_URL_PATH ) ?: '/';
        $no_slash = rtrim( $path, '/' ) ?: '/';
        $w_slash  = ( $no_slash === '/' ) ? '/' : $no_slash . '/';
        foreach ( [ $path, $no_slash, $w_slash, strtolower($path), strtolower($no_slash), strtolower($w_slash) ] as $v ) {
            $path_map[ $v ] = $full_url;
        }
    }

    $api_url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport";

    foreach ( $periods as $period_key => $dates ) {
        $body = [
            'dateRanges' => [ [ 'startDate' => $dates['start'], 'endDate' => $dates['end'] ] ],
            'dimensions' => [ [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ],
            'metrics'    => [ [ 'name' => 'sessions' ], [ 'name' => 'activeUsers' ], [ 'name' => 'screenPageViews' ] ],
            'limit'      => 50000,
            'orderBys'   => [ [ 'metric' => [ 'metricName' => 'sessions' ], 'desc' => true ] ],
        ];
        $data = seo_dash_google_post( $api_url, $body, $token );
        if ( ! $data || ! empty( $data['error'] ) ) continue;

        $aggregated = [];
        foreach ( $data['rows'] ?? [] as $api_row ) {
            $dims    = $api_row['dimensionValues'] ?? [];
            $metrics = $api_row['metricValues']    ?? [];
            $ga_path = parse_url( $dims[0]['value'] ?? '', PHP_URL_PATH ) ?: '/';
            $ga_norm = strtolower( rtrim( $ga_path, '/' ) ) ?: '/';
            $ga_wsl  = ( $ga_norm === '/' ) ? '/' : $ga_norm . '/';
            $full_url = $path_map[$ga_path] ?? $path_map[$ga_norm] ?? $path_map[$ga_wsl] ?? null;
            if ( ! $full_url ) continue;

            if (!isset($aggregated[$full_url])) {
                $aggregated[$full_url] = [
                    'report_id'   => $report_id, 'period_type' => $period_key,
                    'month_key'   => $target_month, 'date_from'   => $dates['start'],
                    'date_to'     => $dates['end'], 'page_url'  => $full_url,
                    'page_title'  => sanitize_text_field( $dims[1]['value'] ?? '' ),
                    'sessions'    => 0,
                    'users'       => 0,
                    'pageviews'   => 0,
                    'bounces'     => 0,
                ];
            }
            $aggregated[$full_url]['sessions'] += intval( $metrics[0]['value'] ?? 0 );
            $aggregated[$full_url]['users']    += intval( $metrics[1]['value'] ?? 0 );
            $aggregated[$full_url]['pageviews']+= intval( $metrics[2]['value'] ?? 0 );
        }
        
        $upserted_count = 0;
        foreach ( $aggregated as $row ) {
            if ( seo_dash_upsert_ga_row( $row ) ) {
                $upserted_count++;
            }
        }
        SEO_Dash_Database::log_activity( 'kpi_fetch_success', 'success', "Fetched and upserted $upserted_count rows for period: $period_key (month: $target_month).", 'report', $report_id );
    }
}
