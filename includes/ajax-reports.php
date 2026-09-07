<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX handlers — Reports
 *
 * Actions registered here:
 *   seo_dash_get_reports       — list all reports
 *   seo_dash_save_report       — create or update a report
 *   seo_dash_trash_report      — soft-delete
 *   seo_dash_restore_report    — restore from trash
 *   seo_dash_delete_report     — permanent delete
 *   seo_dash_get_report_data   — fetch all panel data for a report (frontend)
 *   seo_dash_save_report_meta  — save misc meta (summary, prompt, etc.)
 */

// ── Get reports paged (Load More) ─────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_reports_paged', function () {
    seo_dash_verify_admin_ajax();
    $limit  = min( 25, max( 1, intval( $_POST['limit']  ?? 5 ) ) );
    $offset = max( 0, intval( $_POST['offset'] ?? 0 ) );
    $search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );

    $reports = SEO_Dash_Database::get_reports_paged( $limit, $offset, $search );
    foreach ( $reports as &$r ) {
        $r['client_count'] = count( SEO_Dash_Database::get_report_client_ids( intval( $r['id'] ) ) );
        $r['ga_months']    = count( SEO_Dash_Database::get_months( SEO_Dash_Database::$data_ga, intval( $r['id'] ) ) );
        $r['meta']         = is_string( $r['meta'] ) ? ( json_decode( $r['meta'], true ) ?: [] ) : ( $r['meta'] ?: [] );
    }
    seo_dash_json_success( [
        'reports' => $reports,
        'total'   => SEO_Dash_Database::count_reports( $search ),
        'offset'  => $offset + count( $reports ),
        'search'  => $search,
    ] );
} );

// ── Get all reports ────────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_reports', function () {
    seo_dash_verify_admin_ajax();
    try {
        $reports = SEO_Dash_Database::get_reports();
        foreach ( $reports as &$r ) {
            $r['client_count'] = count( SEO_Dash_Database::get_report_client_ids( intval( $r['id'] ) ) );
            $r['meta']         = is_string( $r['meta'] ) ? ( json_decode( $r['meta'], true ) ?: [] ) : ( $r['meta'] ?: [] );
        }
        seo_dash_json_success( $reports );
    } catch ( \Throwable $e ) {
        SEO_Dash_Database::log_activity( 'reports_list_error', 'error', $e->getMessage() );
        seo_dash_json_error( 'Failed to load reports: ' . $e->getMessage() );
    }
} );

// ── Create / update a report ───────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_report', function () {
    seo_dash_verify_admin_ajax();

    $id    = intval( $_POST['report_id'] ?? 0 );
    $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );

    if ( empty( $title ) ) {
        SEO_Dash_Database::log_activity( 'report_save_failed', 'warning', 'Empty title submitted.' );
        seo_dash_json_error( 'Report title is required.' );
    }

    // Ensure tables exist before attempting insert.
    global $wpdb;
    SEO_Dash_Database::init();

    $table_check = $wpdb->get_var( "SHOW TABLES LIKE '" . SEO_Dash_Database::$reports . "'" );
    if ( ! $table_check ) {
        SEO_Dash_Database::log_activity( 'db_tables_missing', 'error', 'Attempted report save but tables missing — running create_tables().' );
        SEO_Dash_Database::create_tables();
        $table_check = $wpdb->get_var( "SHOW TABLES LIKE '" . SEO_Dash_Database::$reports . "'" );
        if ( ! $table_check ) {
            seo_dash_json_error( 'Database tables are missing. Please deactivate and reactivate the plugin, then try again.' );
        }
    }

    // Hide DB errors to prevent polluting JSON response.
    $wpdb->hide_errors();
    $wpdb->suppress_errors( true );

    $meta = [];
    if ( ! empty( $_POST['meta'] ) && is_array( $_POST['meta'] ) ) {
        $meta = seo_dash_sanitize_recursive( wp_unslash( $_POST['meta'] ) );
    }

    try {
        if ( $id ) {
            $ok = SEO_Dash_Database::update_report( $id, [ 'title' => $title, 'meta' => $meta ] );
            SEO_Dash_Database::log_activity(
                'report_updated', 'success',
                "Report title set to \"{$title}\".",
                'report', $id, $title
            );
            seo_dash_json_success( [ 'report_id' => $id ], $ok ? 'Report updated.' : 'Nothing changed.' );
        } else {

            $new_id = SEO_Dash_Database::insert_report( [
                'title'      => $title,
                'status'     => 'publish',
                'created_by' => get_current_user_id(),
                'meta'       => $meta,
            ] );

            if ( ! $new_id ) {
                $db_err = $wpdb->last_error ?: 'DB insert returned false. Check database user permissions (INSERT privilege required).';
                SEO_Dash_Database::log_activity(
                    'report_create_failed', 'error',
                    "Title: \"{$title}\". DB error: {$db_err}"
                );
                seo_dash_json_error( $db_err );
            }

            SEO_Dash_Database::log_activity(
                'report_created', 'success',
                "New report \"{$title}\" created (ID: {$new_id}).",
                'report', $new_id, $title
            );
            seo_dash_json_success( [ 'report_id' => $new_id ], 'Report created.' );
        }
    } catch ( \Throwable $e ) {
        SEO_Dash_Database::log_activity(
            'report_save_exception', 'error',
            "Title: \"{$title}\". Exception: " . $e->getMessage()
        );
        seo_dash_json_error( 'Server exception: ' . $e->getMessage() );
    }
} );

// ── Save report meta only (summary, prompt, etc.) ─────────────────────────
add_action( 'wp_ajax_seo_dash_save_report_meta', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    $report = SEO_Dash_Database::get_report( $report_id );
    if ( ! $report ) seo_dash_json_error( 'Report not found.' );

    $existing_meta = is_array( $report['meta'] ) ? $report['meta'] : [];

    $allowed_meta = [
        'summary', 'groq_prompt', 'groq_key',
        'show_overview', 'show_service', 'show_blog', 'show_gmb',
        'show_technical', 'show_backlinks', 'show_leads', 'show_chatbot',
        'sc_monthly', 'sc_overall', 'ga_monthly', 'ga_overall'
    ];
    $changed = [];
    foreach ( $allowed_meta as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            $raw = wp_unslash( $_POST[ $field ] );
            // 'summary' comes from the classic (TinyMCE) editor and is allowed
            // to contain safe rich-text HTML (headings, paragraphs, lists,
            // links, bold/italic). Every other meta field stays plain text.
            $existing_meta[ $field ] = $field === 'summary' ? wp_kses_post( $raw ) : sanitize_textarea_field( $raw );
            $changed[] = $field;
        }
    }

    $ok = SEO_Dash_Database::update_report( $report_id, [ 'meta' => $existing_meta ] );
    SEO_Dash_Database::log_activity(
        'report_meta_saved', 'info',
        'Fields: ' . implode( ', ', $changed ),
        'report', $report_id, $report['title'] ?? ''
    );
    seo_dash_json_success( null, $ok ? 'Saved.' : 'Nothing changed.' );
} );

// ── Save Overview tab data (overall + monthly traffic) ─────────────────────
// Persists the two options the Overview tab reads on load:
//   seo_dash_overview_overall_{id}  => ['from','to','traffic']
//   seo_dash_overview_monthly_{id}  => [ ['month','traffic'], ... ]
// Previously the Overview inputs only showed a fake "Auto-saved" toast and
// never hit the server, so all values were lost on reload. This is the real
// save endpoint behind the new Save buttons.
add_action( 'wp_ajax_seo_dash_save_overview', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    $report = SEO_Dash_Database::get_report( $report_id );
    if ( ! $report ) seo_dash_json_error( 'Report not found.' );

    // ── Overall traffic: date range + total ────────────────────────────────
    $valid_date = static function ( $v ): string {
        $v = preg_replace( '/[^\x20-\x7E]/', '', (string) $v ); // strip non-ASCII / mojibake
        $v = sanitize_text_field( $v );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
    };
    $overall = [
        'from'    => $valid_date( wp_unslash( $_POST['overall_from'] ?? '' ) ),
        'to'      => $valid_date( wp_unslash( $_POST['overall_to'] ?? '' ) ),
        'traffic' => preg_replace( '/[^0-9]/', '', (string) wp_unslash( $_POST['overall_traffic'] ?? '' ) ),
    ];
    update_option( "seo_dash_overview_overall_{$report_id}", $overall, false );

    // ── Monthly traffic: JSON array of { month, traffic } ──────────────────
    $monthly_in  = json_decode( (string) wp_unslash( $_POST['monthly'] ?? '' ), true );
    $monthly_out = [];
    if ( is_array( $monthly_in ) ) {
        foreach ( $monthly_in as $row ) {
            if ( ! is_array( $row ) ) continue;
            $month   = seo_dash_sanitize_month( (string) ( $row['month'] ?? '' ) );
            $traffic = preg_replace( '/[^0-9]/', '', (string) ( $row['traffic'] ?? '' ) );
            // Skip rows that are completely empty (no month and no traffic).
            if ( $month === '' && $traffic === '' ) continue;
            $monthly_out[] = [ 'month' => $month, 'traffic' => $traffic ];
        }
    }
    update_option( "seo_dash_overview_monthly_{$report_id}", $monthly_out, false );

    SEO_Dash_Database::log_activity(
        'overview_saved', 'info',
        'Overall traffic + ' . count( $monthly_out ) . ' monthly row(s).',
        'report', $report_id, $report['title'] ?? ''
    );

    seo_dash_json_success(
        [ 'monthly_count' => count( $monthly_out ) ],
        'Overview saved.'
    );
} );

// ── Trash a report ─────────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_trash_report', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing report ID.' );
    $report = SEO_Dash_Database::get_report( $id );
    SEO_Dash_Database::trash_report( $id );
    SEO_Dash_Database::log_activity(
        'report_trashed', 'warning',
        "Report trashed.",
        'report', $id, $report['title'] ?? "ID {$id}"
    );
    seo_dash_json_success( null, 'Report moved to trash.' );
} );

// ── Restore a report ───────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_restore_report', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing report ID.' );
    SEO_Dash_Database::update_report( $id, [ 'status' => 'publish' ] );
    SEO_Dash_Database::log_activity( 'report_restored', 'success', '', 'report', $id );
    seo_dash_json_success( null, 'Report restored.' );
} );

// ── Permanently delete a report ────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_delete_report', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing report ID.' );
    $report = SEO_Dash_Database::get_report( $id );
    SEO_Dash_Database::delete_report( $id );
    SEO_Dash_Database::log_activity(
        'report_deleted', 'warning',
        'Permanently deleted.',
        'report', $id, $report['title'] ?? "ID {$id}"
    );
    seo_dash_json_success( null, 'Report permanently deleted.' );
} );

// ── Get all data for one report panel (used by frontend JS) ───────────────
add_action( 'wp_ajax_seo_dash_get_report_data', 'seo_dash_get_report_data_handler' );
function seo_dash_get_report_data_handler(): void {
    seo_dash_verify_frontend_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $scope     = sanitize_key( $_POST['scope'] ?? '' );
    $month     = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );

    // Pagination parameters
    $page      = max( 1, intval( $_POST['page'] ?? 1 ) );
    // per_page: JS sends the real page size (20 for table display, or 99999 legacy).
    // Cap at 100000 to prevent absurd values. After Step 2 JS migration this will be
    // tightened to a small cap (e.g. 200) since all non-grouped scopes are now server-paginated.
    $per_page  = min( 100000, max( 1, intval( $_POST['per_page'] ?? 50 ) ) );
    $offset    = ( $page - 1 ) * $per_page;

    if ( ! $report_id || ! $scope ) seo_dash_json_error( 'Missing parameters.' );

    $user_id = get_current_user_id();
    if ( ! seo_dash_can_user_access_report( $user_id, $report_id ) ) {
        seo_dash_json_error( 'Access denied.', 403 );
    }

    $table_map = [
        'ga'        => SEO_Dash_Database::$data_ga,
        'sc'        => SEO_Dash_Database::$data_sc,
        'service_sc'=> SEO_Dash_Database::$data_sc,
        'blog_sc'   => SEO_Dash_Database::$data_sc,
        'leads'          => SEO_Dash_Database::$data_leads,
        'click_tracking' => SEO_Dash_Database::$data_click_tracking,
        'backlinks'      => SEO_Dash_Database::$data_backlinks,
        'gmb'       => SEO_Dash_Database::$data_gmb,
        'keywords'  => SEO_Dash_Database::$data_keywords,
        'technical' => SEO_Dash_Database::$data_technical,
        'service'   => SEO_Dash_Database::$data_ga,
        'blog'      => SEO_Dash_Database::$data_ga,
    ];

    if ( ! isset( $table_map[ $scope ] ) ) seo_dash_json_error( 'Unknown scope.' );

    $is_grouped_scope = in_array( $scope, [ 'ga', 'service', 'blog', 'sc', 'service_sc', 'blog_sc' ], true );

    // ── Auto-resolve month for GA/SC scopes ──────────────────────────────
    // When no month is specified, use the admin-set active month, or fall back
    // to the latest month in the database. This ensures the client always sees
    // exact data from one consistent snapshot — not a MAX() mix across all months.
    if ( $is_grouped_scope && $month === '' ) {
        $is_sc_table = in_array( $scope, [ 'sc', 'service_sc', 'blog_sc' ], true );
        $db_type_key = $is_sc_table ? 'sc' : 'ga';
        $active = get_option( "seo_dash_active_month_{$report_id}_{$db_type_key}", '' );
        if ( $active ) {
            $month = $active;
        } else {
            // No active month set — use latest month in the database
            $all_months = SEO_Dash_Database::get_months( $table_map[ $scope ], $report_id );
            $month = ! empty( $all_months ) ? $all_months[0] : '';
        }
    }

    if ( $is_grouped_scope ) {
        // ── Type detection & scope filtering ─────────────────────────────
        // We need $type_map BEFORE the DB query so we can decide whether to
        // fetch all rows (for merge) or just the requested page (fast path).
        $is_sc   = in_array( $scope, [ 'sc', 'service_sc', 'blog_sc' ], true );
        $map_key = $is_sc ? 'sc' : 'ga';
        $type_map = get_option( "seo_dash_sitemap_types_{$report_id}_{$map_key}", [] );
        if ( ! is_array( $type_map ) ) $type_map = [];

        $has_type_map = ! empty( $type_map );

        // When we have a type map we fetch ALL rows (no limit) so we can merge
        // zero-data stubs for missing URLs and paginate the combined set in PHP.
        // Without a type map we use the DB-level limit for efficiency.
        $db_limit  = $has_type_map ? 0 : $per_page;  // 0 = no LIMIT in get_grouped_page_rows
        $db_offset = $has_type_map ? 0 : $offset;

        $rows = SEO_Dash_Database::get_grouped_page_rows(
            $table_map[ $scope ],
            $report_id,
            $month,
            $db_limit,
            $db_offset,
            $scope
        );
        $total_rows = SEO_Dash_Database::get_grouped_page_rows_count(
            $table_map[ $scope ],
            $report_id,
            $month
        );
        $total_pages = $per_page > 0 ? ceil( $total_rows / $per_page ) : 1;

        if ( ! function_exists( 'seo_dash_ga_type_v2_ajax' ) ) {
            function seo_dash_ga_type_v2_ajax( array $row, array $map ): string {
                $url = trim( $row['url'] ?? $row['page_url'] ?? '' );
                if ( $url ) {
                    if ( isset( $map[ $url ] ) ) return $map[ $url ];
                    if ( isset( $map[ trailingslashit( $url ) ] ) ) return $map[ trailingslashit( $url ) ];
                    if ( isset( $map[ untrailingslashit( $url ) ] ) ) return $map[ untrailingslashit( $url ) ];
                    $path = parse_url( $url, PHP_URL_PATH );
                    if ( $path ) {
                        if ( isset( $map[ $path ] ) ) return $map[ $path ];
                        if ( isset( $map[ trailingslashit( $path ) ] ) ) return $map[ trailingslashit( $path ) ];
                        if ( isset( $map[ untrailingslashit( $path ) ] ) ) return $map[ untrailingslashit( $path ) ];
                    }
                }
                $pt = trim( $row['title'] ?? $row['page_title'] ?? '' );
                if ( $pt ) {
                    if ( preg_match( '/^\[sitemap:([a-z0-9_-]+)\]/i', $pt, $m ) ) return strtolower( $m[1] );
                    if ( preg_match( '/^\[([a-z0-9_-]+)\]$/i', $pt, $m ) ) return strtolower( $m[1] );
                }
                return 'other';
            }
        }

        // Blog types — URLs with these types go to the Blog Posts tab.
        // Everything else (pages, services, products, authors, locations, etc.)
        // goes to the Service Pages tab. Using a blacklist instead of a whitelist
        // means any new sitemap type is automatically included in the right tab.
        $blog_types = [ 'post', 'blog', 'category', 'article', 'news', 'tag' ];

        // Types excluded from ALL client tabs (internal/GMB post data)
        $excluded_types = [ 'gmb_posts', 'gmb_post' ];

        $filtered_rows = [];
        foreach ( $rows as $r ) {
            $t = seo_dash_ga_type_v2_ajax( $r, $type_map );
            $r['type'] = $t;

            // Drop internal types from all client views
            if ( in_array( $t, $excluded_types, true ) ) continue;

            // Analytics (ga/sc): show ALL types — no filtering
            // Service tab: show everything that is NOT a blog type
            // Blog tab: show only blog types
            if ( in_array( $scope, [ 'service', 'service_sc' ], true ) && in_array( $t, $blog_types, true ) ) {
                continue;
            }
            if ( in_array( $scope, [ 'blog', 'blog_sc' ], true ) && ! in_array( $t, $blog_types, true ) ) {
                continue;
            }

            $filtered_rows[] = $r;
        }
        $rows = $filtered_rows;

        // ── Merge missing sitemap URLs as zero-data stubs ─────────────────
        // The DB query only returns URLs that have a row for $month, but the
        // sitemap type map contains ALL known URLs (e.g. 245). URLs with zero
        // traffic in that month are absent from the DB, causing the client to
        // see fewer rows (e.g. 207). We inject stub rows so every sitemap URL
        // always appears in the table — matching the admin count.
        if ( ! empty( $type_map ) && in_array( $scope, [ 'ga', 'sc', 'service', 'service_sc', 'blog', 'blog_sc' ], true ) ) {
            // Index existing rows by URL for O(1) lookup
            $existing_urls = [];
            foreach ( $rows as $r ) {
                $existing_urls[ $r['url'] ] = true;
            }

            // Build zero-metric period skeleton matching get_grouped_page_rows output
            $is_sc_stub    = in_array( $scope, [ 'sc', 'service_sc', 'blog_sc' ], true );
            $stub_metrics  = $is_sc_stub
                ? [ 'clicks', 'impressions', 'ctr', 'position' ]
                : [ 'sessions', 'users', 'pageviews', 'bounces', 'goal_completions' ];
            $stub_periods  = [ 'monthly', '7d', '30d', '90d', 'overall' ];

            $stub_data_template = [];
            foreach ( $stub_periods as $period ) {
                $period_data = [ 'period_type' => $period, 'page_url' => '' ];
                if ( ! $is_sc_stub ) $period_data['page_title'] = '';
                foreach ( $stub_metrics as $m ) $period_data[ $m ] = 0;
                $stub_data_template[ $period ] = $period_data;
            }

            $stub_rows = [];
            foreach ( $type_map as $url => $url_type ) {
                if ( isset( $existing_urls[ $url ] ) ) continue;   // already in result
                if ( in_array( $url_type, $excluded_types, true ) ) continue;

                // Apply the same scope-filter logic as for DB rows
                if ( in_array( $scope, [ 'service', 'service_sc' ], true ) && in_array( $url_type, $blog_types, true ) ) continue;
                if ( in_array( $scope, [ 'blog', 'blog_sc' ], true ) && ! in_array( $url_type, $blog_types, true ) ) continue;

                // Clone the template and stamp the URL into each period
                $stub_data = [];
                foreach ( $stub_data_template as $period => $pd ) {
                    $pd['page_url'] = $url;
                    if ( ! $is_sc_stub ) $pd['page_title'] = $url;
                    $stub_data[ $period ] = $pd;
                }

                $stub_rows[] = [
                    'url'   => $url,
                    'title' => $url,
                    'type'  => $url_type,
                    'data'  => $stub_data,
                ];
            }

            // Append stubs; sort combined list by URL for consistent ordering
            $all_rows = ! empty( $stub_rows ) ? array_merge( $rows, $stub_rows ) : $rows;
            usort( $all_rows, fn( $a, $b ) => strcmp( $a['url'], $b['url'] ) );

            // Apply pagination over the merged+sorted set
            $total_rows  = count( $all_rows );
            $total_pages = $per_page > 0 ? (int) ceil( $total_rows / $per_page ) : 1;
            $rows        = array_slice( $all_rows, $offset, $per_page );
        } elseif ( $has_type_map ) {
            // type_map present but empty — just paginate the already-full DB result set
            usort( $rows, fn( $a, $b ) => strcmp( $a['url'], $b['url'] ) );
            $total_rows  = count( $rows );
            $total_pages = $per_page > 0 ? (int) ceil( $total_rows / $per_page ) : 1;
            $rows        = array_slice( $rows, $offset, $per_page );
        }

    } else {
        // Server-side pagination: DB returns only the requested page of rows.
        // Total count comes from a lightweight COUNT(*) query — no rows loaded into PHP memory.
        // KPI and chart aggregate data is served by seo_dash_get_report_meta (separate endpoint).
        $total_rows  = SEO_Dash_Database::get_data_rows_count( $table_map[ $scope ], $report_id, $month );
        $total_pages = $per_page > 0 ? (int) ceil( $total_rows / $per_page ) : 1;
        $row_order   = ( $scope === 'backlinks' ) ? 'DESC' : 'ASC';
        $rows        = SEO_Dash_Database::get_data_rows( $table_map[ $scope ], $report_id, $month, false, $per_page, $offset, $row_order );
    }

    $months = SEO_Dash_Database::get_months( $table_map[ $scope ], $report_id );
    seo_dash_json_success( [ 
        'rows' => $rows, 
        'months' => $months,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total_rows' => $total_rows,
            'total_pages' => $total_pages
        ]
    ] );
}

// ── Aggregated meta for a report scope (KPI totals + type counts) ────────
// Called once per tab load. Returns totals computed entirely in SQL — no rows
// are fetched into PHP. The JS uses this to populate KPIs and chart data for
// tabs that previously needed all rows loaded (backlinks, leads, technical).
//
// POST params: report_id, scope, month_key (optional)
// Returns:
//   total_rows      — COUNT(*) across all rows for this scope/month
//   type_counts     — { type_value: count, ... } from GROUP BY on the type column
//   month_totals    — { YYYY-MM: count, ... } for monthly trend charts
//   kpi_aggregates  — scope-specific SUM/AVG fields (e.g. avg domain_rating for backlinks)
add_action( 'wp_ajax_seo_dash_get_report_meta', 'seo_dash_get_report_meta_handler' );
function seo_dash_get_report_meta_handler(): void {
    seo_dash_verify_frontend_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $scope     = sanitize_key( $_POST['scope'] ?? '' );
    $month     = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );

    if ( ! $report_id || ! $scope ) seo_dash_json_error( 'Missing parameters.' );

    if ( ! seo_dash_can_user_access_report( get_current_user_id(), $report_id ) ) {
        seo_dash_json_error( 'Access denied.', 403 );
    }

    $table_map = [
        'backlinks'      => SEO_Dash_Database::$data_backlinks,
        'leads'          => SEO_Dash_Database::$data_leads,
        'click_tracking' => SEO_Dash_Database::$data_click_tracking,
        'technical' => SEO_Dash_Database::$data_technical,
        'gmb'       => SEO_Dash_Database::$data_gmb,
        'keywords'  => SEO_Dash_Database::$data_keywords,
    ];

    if ( ! isset( $table_map[ $scope ] ) ) {
        seo_dash_json_error( 'Scope not supported for meta endpoint. Use: ' . implode( ', ', array_keys( $table_map ) ) );
    }

    global $wpdb;
    $table = $table_map[ $scope ];

    // Base WHERE clause reused across all queries below
    $where_parts  = [ 'report_id = %d', 'trashed = 0' ];
    $where_params = [ $report_id ];
    if ( $month !== '' ) {
        $where_parts[]  = 'month_key = %s';
        $where_params[] = $month;
    }
    $where = implode( ' AND ', $where_parts );

    // ── Total row count ───────────────────────────────────────────────────
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $total_rows = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$where_params
    ) );

    // ── Type counts (GROUP BY the type column per scope) ──────────────────
    $type_counts  = [];
    $type_col_map = [
        'backlinks' => 'link_type',
        'leads'     => 'source',
        'technical' => 'status',
        'gmb'       => null,
        'keywords'  => null,
    ];
    $type_col = $type_col_map[ $scope ] ?? null;
    if ( $type_col ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows_by_type = $wpdb->get_results( $wpdb->prepare(
            "SELECT {$type_col} AS type_val, COUNT(*) AS cnt
             FROM {$table}
             WHERE {$where}
             GROUP BY {$type_col}",
            ...$where_params
        ), ARRAY_A );
        foreach ( $rows_by_type as $r ) {
            $type_counts[ $r['type_val'] ?: 'unknown' ] = (int) $r['cnt'];
        }
    }

    // ── Monthly trend counts (GROUP BY month_key) ────────────────────────
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $month_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT month_key, COUNT(*) AS cnt
         FROM {$table}
         WHERE {$where}
         GROUP BY month_key
         ORDER BY month_key ASC",
        ...$where_params
    ), ARRAY_A );
    $month_totals = [];
    foreach ( $month_rows as $r ) {
        $month_totals[ $r['month_key'] ] = (int) $r['cnt'];
    }

    // ── Scope-specific KPI aggregates ────────────────────────────────────
    $kpi_aggregates = [];
    if ( $scope === 'backlinks' ) {
        $agg = $wpdb->get_row( $wpdb->prepare(
            "SELECT AVG(domain_rating) AS avg_dr, AVG(spam_score) AS avg_spam,
                    SUM(CASE WHEN status = 'live' THEN 1 ELSE 0 END) AS live_count,
                    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) AS lost_count
             FROM {$table}
             WHERE {$where}",
            ...$where_params
        ), ARRAY_A );
        $kpi_aggregates = [
            'avg_domain_rating' => round( (float) ( $agg['avg_dr']   ?? 0 ), 1 ),
            'avg_spam_score'    => round( (float) ( $agg['avg_spam'] ?? 0 ), 1 ),
            'live_count'        => (int) ( $agg['live_count'] ?? 0 ),
            'lost_count'        => (int) ( $agg['lost_count'] ?? 0 ),
        ];
    } elseif ( $scope === 'leads' ) {
        $agg = $wpdb->get_results( $wpdb->prepare(
            "SELECT status, COUNT(*) AS cnt
             FROM {$table}
             WHERE {$where}
             GROUP BY status",
            ...$where_params
        ), ARRAY_A );
        foreach ( $agg as $r ) {
            $kpi_aggregates[ 'status_' . ( $r['status'] ?: 'unknown' ) ] = (int) $r['cnt'];
        }
    } elseif ( $scope === 'technical' ) {
        $agg = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                SUM(CASE WHEN status = 'pass'    THEN 1 ELSE 0 END) AS pass_count,
                SUM(CASE WHEN status = 'fail'    THEN 1 ELSE 0 END) AS fail_count,
                SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) AS warning_count,
                SUM(CASE WHEN status = 'n/a'     THEN 1 ELSE 0 END) AS na_count
             FROM {$table}
             WHERE {$where}",
            ...$where_params
        ), ARRAY_A );
        $kpi_aggregates = [
            'pass_count'    => (int) ( $agg['pass_count']    ?? 0 ),
            'fail_count'    => (int) ( $agg['fail_count']    ?? 0 ),
            'warning_count' => (int) ( $agg['warning_count'] ?? 0 ),
            'na_count'      => (int) ( $agg['na_count']      ?? 0 ),
        ];
    }

    // ── Available months list (for month dropdown) ───────────────────────
    $months = SEO_Dash_Database::get_months( $table, $report_id );

    seo_dash_json_success( [
        'scope'          => $scope,
        'total_rows'     => $total_rows,
        'type_counts'    => $type_counts,
        'month_totals'   => $month_totals,
        'kpi_aggregates' => $kpi_aggregates,
        'months'         => $months,
    ] );
}

// ── Overview KPI summary ──────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_kpis', function () {
    seo_dash_verify_frontend_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    if ( ! seo_dash_can_user_access_report( get_current_user_id(), $report_id ) ) {
        seo_dash_json_error( 'Access denied.', 403 );
    }

    global $wpdb;

    $ga_month = $wpdb->get_var( $wpdb->prepare(
        "SELECT month_key FROM " . SEO_Dash_Database::$data_ga . "
         WHERE report_id = %d AND trashed = 0 ORDER BY month_key DESC LIMIT 1",
        $report_id
    ) );
    $sessions = 0;
    if ( $ga_month ) {
        $sessions = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(sessions) FROM " . SEO_Dash_Database::$data_ga . "
             WHERE report_id = %d AND month_key = %s AND trashed = 0",
            $report_id, $ga_month
        ) );
    }

    $sc_month = $wpdb->get_var( $wpdb->prepare(
        "SELECT month_key FROM " . SEO_Dash_Database::$data_sc . "
         WHERE report_id = %d AND trashed = 0 ORDER BY month_key DESC LIMIT 1",
        $report_id
    ) );
    $clicks = 0;
    if ( $sc_month ) {
        $clicks = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(clicks) FROM " . SEO_Dash_Database::$data_sc . "
             WHERE report_id = %d AND month_key = %s AND trashed = 0",
            $report_id, $sc_month
        ) );
    }

    $leads = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_leads . "
         WHERE report_id = %d AND trashed = 0",
        $report_id
    ) );

    $backlinks = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_backlinks . "
         WHERE report_id = %d AND trashed = 0",
        $report_id
    ) );

    seo_dash_json_success( [
        'sessions'  => $sessions,
        'clicks'    => $clicks,
        'leads'     => $leads,
        'backlinks' => $backlinks,
        'ga_month'  => $ga_month,
        'sc_month'  => $sc_month,
    ] );
} );

// ── DB health check (admin only) ──────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_db_check', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;
    SEO_Dash_Database::init();
    $exists  = $wpdb->get_var( "SHOW TABLES LIKE '" . SEO_Dash_Database::$reports . "'" );
    $version = get_option( 'seo_dash_db_version', 'none' );
    if ( ! $exists ) {
        SEO_Dash_Database::create_tables();
        SEO_Dash_Database::log_activity( 'db_setup_ran', 'info', 'Admin triggered DB table creation.' );
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '" . SEO_Dash_Database::$reports . "'" );
    }
    seo_dash_json_success( [
        'tables_exist'   => ! empty( $exists ),
        'db_version'     => $version,
        'plugin_version' => SEO_DASH_VERSION,
        'last_error'     => $wpdb->last_error,
        'reports_table'  => SEO_Dash_Database::$reports,
    ] );
} );

// ── Clear activity log ─────────────────────────────────────────────────────
// ── Activity Log Cleanup (Smart Options) ─────────────────────────────────
add_action( 'wp_ajax_seo_dash_clear_activity_log', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;
    SEO_Dash_Database::init();
    $wpdb->query( "TRUNCATE TABLE " . SEO_Dash_Database::$activity_log );
    seo_dash_json_success( null, 'Activity log cleared completely.' );
} );

// Clean up logs by age (smart cleanup)
add_action( 'wp_ajax_seo_dash_cleanup_activity_log_by_age', function () {
    seo_dash_verify_admin_ajax();
    
    $days = intval( $_POST['days'] ?? 60 );
    $days = max( 1, min( 365, $days ) ); // Between 1-365 days
    
    $deleted = SEO_Dash_Database::cleanup_activity_log( $days );
    
    seo_dash_json_success( 
        [ 'deleted' => $deleted ],
        "Deleted {$deleted} log entries older than {$days} days."
    );
} );

// Clean up logs by level
add_action( 'wp_ajax_seo_dash_cleanup_activity_log_by_level', function () {
    seo_dash_verify_admin_ajax();
    
    $level = sanitize_key( $_POST['level'] ?? 'info' );
    $days = intval( $_POST['days'] ?? 30 );
    $days = max( 1, min( 365, $days ) );
    
    if ( ! in_array( $level, [ 'info', 'success', 'warning', 'error' ], true ) ) {
        seo_dash_json_error( 'Invalid level.' );
    }
    
    $deleted = SEO_Dash_Database::cleanup_activity_log_by_level( $level, $days );
    
    seo_dash_json_success( 
        [ 'deleted' => $deleted ],
        "Deleted {$deleted} '{$level}' log entries older than {$days} days."
    );
} );

// Get activity log statistics
add_action( 'wp_ajax_seo_dash_get_activity_log_stats', function () {
    seo_dash_verify_admin_ajax();
    
    $stats = SEO_Dash_Database::get_activity_log_stats();
    
    seo_dash_json_success( $stats );
} );

// Update log retention settings
add_action( 'wp_ajax_seo_dash_update_log_retention', function () {
    seo_dash_verify_admin_ajax();
    
    $days = intval( $_POST['days'] ?? 60 );
    $days = max( 0, min( 365, $days ) ); // 0 = disabled, max 365 days
    
    update_option( 'seo_dash_log_retention_days', $days );
    
    $message = $days > 0 
        ? "Automatic cleanup will keep the last {$days} days of logs."
        : "Automatic cleanup disabled.";
    
    seo_dash_json_success( [ 'retention_days' => $days ], $message );
} );

// ── Generic CSV Import ───────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_import_csv', function() {
    seo_dash_verify_admin_ajax();
    $report_id = intval($_POST['report_id'] ?? 0);
    $type      = sanitize_key($_POST['table_type'] ?? '');
    $csv_data  = wp_unslash($_POST['csv_data'] ?? '');
    
    if (!$report_id || !$type || empty($csv_data)) {
        seo_dash_json_error('Missing data for import.');
    }
    
    // Parse CSV
    $csv_data = str_replace("\r\n", "\n", str_replace("\r", "\n", $csv_data));
    $lines = explode("\n", trim($csv_data));
    if (count($lines) < 2) {
        seo_dash_json_error('No data found in CSV.');
    }
    
    $headers = str_getcsv(array_shift($lines));
    $headers = array_map(function($h) { return strtolower(trim($h)); }, $headers);
    
    $parsed_rows = [];
    foreach ($lines as $line) {
        if (!trim($line)) continue;
        $cols = str_getcsv($line);
        $row = [];
        foreach ($headers as $idx => $h) {
            $row[$h] = isset($cols[$idx]) ? trim($cols[$idx]) : '';
        }
        $parsed_rows[] = $row;
    }
    
    if (empty($parsed_rows)) {
        seo_dash_json_error('No valid rows found.');
    }
    
    // Process based on type
    $inserted = 0;
    if ($type === 'ga') {
        $db_rows = [];
        foreach ($parsed_rows as $r) {
            $db_rows[] = [
                'report_id'   => $report_id,
                'period_type' => sanitize_text_field($r['period_type'] ?? 'monthly'),
                'month_key'   => sanitize_text_field($r['month_key'] ?? ''),
                'date_from'   => sanitize_text_field($r['date_from'] ?? ''),
                'date_to'     => sanitize_text_field($r['date_to'] ?? ''),
                'page_url'    => sanitize_text_field($r['page_url'] ?? ''),
                'page_title'  => sanitize_text_field($r['page_title'] ?? ''),
                'sessions'    => absint($r['sessions'] ?? 0),
                'users'       => absint($r['users'] ?? 0),
                'views'       => absint($r['views'] ?? 0),
                'bounce_rate' => floatval($r['bounce_rate'] ?? 0),
                'avg_duration'=> absint($r['avg_duration'] ?? 0),
            ];
        }
        $inserted = SEO_Dash_Database::insert_data_rows(SEO_Dash_Database::$data_ga, $db_rows);
    } 
    else if ($type === 'sc') {
        $db_rows = [];
        foreach ($parsed_rows as $r) {
            $db_rows[] = [
                'report_id'   => $report_id,
                'period_type' => sanitize_text_field($r['period_type'] ?? 'monthly'),
                'month_key'   => sanitize_text_field($r['month_key'] ?? ''),
                'date_from'   => sanitize_text_field($r['date_from'] ?? ''),
                'date_to'     => sanitize_text_field($r['date_to'] ?? ''),
                'page_url'    => sanitize_text_field($r['page_url'] ?? ''),
                'page_title'  => sanitize_text_field($r['page_title'] ?? ''),
                'clicks'      => absint($r['clicks'] ?? 0),
                'impressions' => absint($r['impressions'] ?? 0),
                'ctr'         => floatval($r['ctr'] ?? 0),
                'position'    => floatval($r['position'] ?? 0),
            ];
        }
        $inserted = SEO_Dash_Database::insert_data_rows(SEO_Dash_Database::$data_sc, $db_rows);
    }
    else if ($type === 'gmb') {
        global $wpdb;
        $gmb_table = SEO_Dash_Database::$data_gmb;
        $skipped_dupes = 0;
        $updated = 0;
        $compare_cols = ['month_key','views_search','views_maps','clicks_website','clicks_directions','calls','bookings'];
        foreach ($parsed_rows as $r) {
            $month_key = sanitize_text_field($r['month'] ?? $r['month_key'] ?? '');
            if (!$month_key) continue;

            $row = [
                'report_id'         => $report_id,
                'month_key'         => $month_key,
                'views_search'      => 0,
                'views_maps'        => 0,
                'clicks_website'    => absint($r['website clicks'] ?? $r['clicks_website'] ?? 0),
                'clicks_directions' => absint($r['directions'] ?? 0),
                'calls'             => absint($r['calls'] ?? 0),
                'bookings'          => absint($r['bookings'] ?? 0),
            ];

            // One row per month — match the existing row (if any) by month_key.
            // Identical data for that month is ignored; changed data updates
            // the existing row instead of adding a duplicate month entry.
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$gmb_table} WHERE report_id=%d AND month_key=%s AND trashed=0 LIMIT 1",
                $report_id, $month_key
            ), ARRAY_A);

            if ($existing) {
                if (seo_dash_rows_equal($existing, $row, $compare_cols)) {
                    $skipped_dupes++;
                    continue;
                }
                $wpdb->update($gmb_table, $row, ['id' => $existing['id']]);
                $updated++;
            } else {
                $wpdb->insert($gmb_table, $row);
                $inserted++;
            }
        }
        $skip_msg = $skipped_dupes > 0 ? " ({$skipped_dupes} duplicate(s) skipped)" : '';
        $upd_msg  = $updated > 0 ? ", {$updated} month(s) updated" : '';
        SEO_Dash_Database::log_activity(
            'csv_import_gmb', 'success',
            "CSV import (gmb): {$inserted} inserted, {$updated} updated, {$skipped_dupes} duplicate(s) skipped.",
            'report', $report_id
        );
        seo_dash_json_success(['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped_dupes], "✅ Imported {$inserted} GMB month(s){$upd_msg}{$skip_msg}.");
    }
    else if ($type === 'gmb_posts') {
        global $wpdb;
        $gp_table = SEO_Dash_Database::$data_gmb_posts;
        $skipped_dupes = 0;
        $updated = 0;
        $compare_cols = ['month_key','title','post_url','status'];

        // Existing active rows, indexed by normalized title and by post_url
        // so an incoming row can be matched to an existing post even if its
        // URL (or title) changed.
        $existing_rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$gp_table} WHERE report_id=%d AND trashed=0", $report_id
        ), ARRAY_A) ?: [];
        $by_title = []; $by_url = [];
        foreach ($existing_rows as $er) {
            $t = seo_dash_norm_val($er['title'] ?? '');
            $u = seo_dash_norm_val($er['post_url'] ?? '');
            if ($t !== '' && !isset($by_title[$t])) $by_title[$t] = $er;
            if ($u !== '' && !isset($by_url[$u]))   $by_url[$u]   = $er;
        }

        foreach ($parsed_rows as $r) {
            // Robust URL detection
            $raw_url = '';
            foreach (['post url', 'post_url', 'url', 'link', 'post link', 'google post url', 'gmb url', 'post_link'] as $col) {
                if (!empty($r[$col])) { $raw_url = trim($r[$col]); break; }
            }
            if (!$raw_url) continue;

            // Robust title detection
            $title = '';
            foreach (['post title', 'post_title', 'title', 'name', 'post name', 'subject'] as $col) {
                if (!empty($r[$col])) { $title = sanitize_text_field($r[$col]); break; }
            }

            $month = sanitize_text_field($r['month'] ?? $r['date'] ?? $r['month key'] ?? '');

            $row = [
                'report_id' => $report_id,
                'month_key' => $month,
                'title'     => $title,
                'post_url'  => esc_url_raw($raw_url),
                'status'    => sanitize_text_field($r['status'] ?? $r['state'] ?? 'Published'),
                'trashed'   => 0,
            ];

            // Identity: same title (preferred), else same post_url.
            $t_norm = seo_dash_norm_val($title);
            $u_norm = seo_dash_norm_val($raw_url);
            $match  = ($t_norm !== '' && isset($by_title[$t_norm])) ? $by_title[$t_norm]
                    : ( ($u_norm !== '' && isset($by_url[$u_norm])) ? $by_url[$u_norm] : null );

            if ($match) {
                if (seo_dash_rows_equal($match, $row, $compare_cols)) {
                    $skipped_dupes++;
                    continue;
                }
                $wpdb->update($gp_table, $row, ['id' => $match['id']]);
                // Keep lookups in sync in case the same identity reappears later in this import.
                if ($t_norm !== '') $by_title[$t_norm] = $row + ['id' => $match['id']];
                if ($u_norm !== '') $by_url[$u_norm]   = $row + ['id' => $match['id']];
                $updated++;
                continue;
            }

            $wpdb->insert($gp_table, $row);
            if ($t_norm !== '') $by_title[$t_norm] = $row + ['id' => $wpdb->insert_id];
            if ($u_norm !== '') $by_url[$u_norm]   = $row + ['id' => $wpdb->insert_id];
            $inserted++;
        }

        if ($inserted === 0 && $updated === 0 && $skipped_dupes === 0) {
            seo_dash_json_error('No valid rows found. Make sure your CSV has a "post url" column. Download the format template for the correct layout.');
        }
        $skip_msg = $skipped_dupes > 0 ? " ({$skipped_dupes} duplicate(s) skipped)" : '';
        $upd_msg  = $updated > 0 ? ", {$updated} updated" : '';
        SEO_Dash_Database::log_activity(
            'csv_import_gmb_posts', 'success',
            "CSV import (gmb_posts): {$inserted} inserted, {$updated} updated, {$skipped_dupes} duplicate(s) skipped.",
            'report', $report_id
        );
        seo_dash_json_success(['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped_dupes], "✅ Imported {$inserted} GMB post(s){$upd_msg}{$skip_msg}.");
    }
    else if ($type === 'technical') {
        global $wpdb;
        $tech_table = SEO_Dash_Database::$data_technical;
        // Map any incoming status value to a valid DB status
        $status_map = [
            'pass'    => 'pass',    'passed'  => 'pass',   'ok'      => 'pass',   'fixed'   => 'pass',
            'warning' => 'warning', 'warn'    => 'warning','caution' => 'warning',
            'fail'    => 'fail',    'failed'  => 'fail',   'error'   => 'fail',   'open'    => 'fail',
            'n/a'     => 'n/a',     'na'      => 'n/a',    'ignored' => 'n/a',    'skip'    => 'n/a',
        ];

        $skipped_dupes = 0;
        $updated = 0;
        $compare_cols = ['month_key','issue_type','severity','url','description','status'];

        // Existing active rows, indexed by normalized issue_type so an
        // incoming row can be matched to an existing audit item even if its
        // URL/severity/description/status changed.
        $existing_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tech_table} WHERE report_id=%d AND trashed=0", $report_id
        ), ARRAY_A) ?: [];
        $by_issue = [];
        foreach ($existing_rows as $er) {
            $i = seo_dash_norm_val($er['issue_type'] ?? '');
            if ($i !== '' && !isset($by_issue[$i])) $by_issue[$i] = $er;
        }

        foreach ($parsed_rows as $r) {
            // '#' column is a serial number — safely ignored
            // Primary: "Audit Item" (matches Download Format + Export CSV)
            // Fallback: "issue_type", "audit_item", "issue", "title"
            $issue = sanitize_text_field(
                $r['audit item'] ?? $r['issue_type'] ?? $r['audit_item'] ?? $r['issue'] ?? $r['title'] ?? ''
            );
            // Primary: "Notes" (matches Download Format + Export CSV)
            // Fallback: "description", "note"
            $desc = sanitize_text_field(
                $r['notes'] ?? $r['description'] ?? $r['note'] ?? ''
            );
            $raw_status = strtolower(trim($r['status'] ?? 'fail'));
            $status = $status_map[$raw_status] ?? 'fail';

            // Skip completely empty rows
            if (!$issue && !$desc) continue;

            $row = [
                'report_id'   => $report_id,
                'month_key'   => sanitize_text_field($r['month_key'] ?? $r['month'] ?? ''),
                'issue_type'  => $issue,
                'severity'    => sanitize_text_field($r['severity'] ?? 'medium'),
                'url'         => sanitize_text_field($r['url'] ?? ''),
                'description' => $desc,
                'status'      => $status,
            ];

            $i_norm = seo_dash_norm_val($issue);
            $match  = ($i_norm !== '' && isset($by_issue[$i_norm])) ? $by_issue[$i_norm] : null;

            if ($match) {
                if (seo_dash_rows_equal($match, $row, $compare_cols)) {
                    $skipped_dupes++;
                    continue;
                }
                $wpdb->update($tech_table, $row, ['id' => $match['id']]);
                if ($i_norm !== '') $by_issue[$i_norm] = $row + ['id' => $match['id']];
                $updated++;
                continue;
            }

            $wpdb->insert($tech_table, $row);
            if ($i_norm !== '') $by_issue[$i_norm] = $row + ['id' => $wpdb->insert_id];
            $inserted++;
        }

        $skip_msg = $skipped_dupes > 0 ? " ({$skipped_dupes} duplicate(s) skipped)" : '';
        $upd_msg  = $updated > 0 ? ", {$updated} updated" : '';
        SEO_Dash_Database::log_activity(
            'csv_import_technical', 'success',
            "CSV import (technical): {$inserted} inserted, {$updated} updated, {$skipped_dupes} duplicate(s) skipped.",
            'report', $report_id
        );
        seo_dash_json_success(['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped_dupes], "✅ Imported {$inserted} audit item(s){$upd_msg}{$skip_msg}.");
    }
    else if ($type === 'backlinks') {
        global $wpdb;
        $bk_table = SEO_Dash_Database::$data_backlinks;
        $db_rows = [];
        $skipped_dupes = 0;
        $type_keywords = [
            'guest' => 'guest_post', 'guest_post' => 'guest_post',
            'directory' => 'directory', 'dir' => 'directory',
            'social' => 'social', 'facebook' => 'social', 'twitter' => 'social', 'linkedin' => 'social', 'instagram' => 'social',
            'forum' => 'forum', 'reddit' => 'forum', 'quora' => 'forum',
            'citation' => 'citation', 'yelp' => 'citation', 'bing' => 'citation',
            'press' => 'press_release', 'pr' => 'press_release', 'prweb' => 'press_release',
            'infographic' => 'infographic',
            'broken' => 'broken_link',
            'nofollow' => 'nofollow', 'sponsored' => 'sponsored', 'ugc' => 'ugc',
            'dofollow' => 'dofollow',
        ];

        // Columns that together define a "row is identical to an existing one"
        // — per the requested rule: only an EXACT match across every one of
        // these fields counts as a duplicate. If even one field differs
        // (e.g. a different target_url for the same source domain), the row
        // is treated as unique and imported.
        $dupe_cols = [
            'month_key', 'link_type', 'source_url', 'domain_rating',
            'page_authority', 'spam_score', 'live_link', 'anchor_text',
            'target_url', 'found_date', 'status',
        ];
        $fingerprint = function( array $row ) use ( $dupe_cols ) {
            $parts = [];
            foreach ( $dupe_cols as $c ) {
                $v = $row[ $c ] ?? '';
                // Normalize so trivial casing/whitespace differences in URLs
                // and text fields don't cause false "unique" rows.
                $parts[] = is_string( $v ) ? strtolower( trim( $v ) ) : (string) $v;
            }
            return implode( '|', $parts );
        };

        // Existing fingerprints for this report (active rows only).
        $existing_fp = [];
        $existing = $wpdb->get_results( $wpdb->prepare(
            "SELECT " . implode( ',', $dupe_cols ) . " FROM {$bk_table} WHERE report_id = %d AND trashed = 0",
            $report_id
        ), ARRAY_A );
        foreach ( (array) $existing as $erow ) {
            $existing_fp[ $fingerprint( $erow ) ] = true;
        }

        foreach ($parsed_rows as $r) {
            // Auto-detect link type from CSV column or source URL
            $lt = strtolower(trim($r['link_type'] ?? $r['type'] ?? ''));
            if (!$lt) {
                $src = strtolower($r['source_url'] ?? $r['website url'] ?? '');
                foreach ($type_keywords as $kw => $mapped) {
                    if (strpos($src, $kw) !== false) { $lt = $mapped; break; }
                }
            }
            $valid_types = ['dofollow','nofollow','sponsored','ugc','guest_post','directory','social','forum','citation','press_release','infographic','broken_link','other'];
            if (!in_array($lt, $valid_types)) $lt = 'dofollow';

            // Parse the found date from whatever format the CSV uses (DD/MM/YYYY,
            // MM/DD/YYYY, Excel serial numbers, etc.) into MySQL's Y-m-d. Fall back
            // to today's date only if the column is missing/empty/unparseable.
            $raw_date   = $r['date'] ?? $r['found_date'] ?? $r['found date'] ?? '';
            $found_date = seo_dash_parse_date_to_mysql($raw_date);
            if ($found_date === '') $found_date = current_time('Y-m-d');

            // Derive month_key from the actual link date so monthly KPIs/filters
            // line up correctly, unless the CSV explicitly provides a month_key.
            $month_key = sanitize_text_field($r['month_key'] ?? '');
            if (!seo_dash_sanitize_month($month_key)) {
                $month_key = substr($found_date, 0, 7);
            }

            $row = [
                'report_id'      => $report_id,
                'month_key'      => $month_key,
                'link_type'      => $lt,
                'source_url'     => esc_url_raw($r['website url'] ?? $r['source_url'] ?? $r['website_url'] ?? ''),
                'domain_rating'  => absint($r['da'] ?? $r['domain_rating'] ?? 0),
                'page_authority' => absint($r['pa'] ?? $r['page_authority'] ?? 0),
                'spam_score'     => absint($r['spam%'] ?? $r['spam'] ?? $r['spam_score'] ?? 0),
                'live_link'      => esc_url_raw($r['live link'] ?? $r['live_link'] ?? ''),
                'anchor_text'    => sanitize_text_field($r['keyword'] ?? $r['anchor_text'] ?? ''),
                'target_url'     => esc_url_raw($r['target url'] ?? $r['target_url'] ?? ''),
                'found_date'     => $found_date,
                'status'         => sanitize_text_field($r['status'] ?? 'live'),
            ];

            $fp = $fingerprint( $row );
            if ( isset( $existing_fp[ $fp ] ) ) {
                // Identical row already exists (in DB or earlier in this same
                // CSV) — skip it. Any single differing column (e.g. target_url)
                // makes the row unique and lets it through.
                $skipped_dupes++;
                continue;
            }
            $existing_fp[ $fp ] = true; // also dedupe within this import batch

            $db_rows[] = $row;
        }
        $inserted = SEO_Dash_Database::insert_data_rows(SEO_Dash_Database::$data_backlinks, $db_rows);
        $skip_msg = $skipped_dupes > 0 ? " ({$skipped_dupes} duplicate(s) skipped)" : '';
        SEO_Dash_Database::log_activity(
            'csv_import_backlinks', 'success',
            "CSV import (backlinks): {$inserted} rows inserted, {$skipped_dupes} duplicate(s) skipped.",
            'report', $report_id
        );
        seo_dash_json_success( [ 'inserted' => $inserted, 'skipped' => $skipped_dupes ], "✅ Imported {$inserted} backlink(s){$skip_msg}." );
    }
    else if ($type === 'leads') {
        global $wpdb;
        $leads_table = SEO_Dash_Database::$data_leads;
        $skipped_dupes = 0;
        $updated = 0;
        $compare_cols = ['name','email','phone','zip','source','message','status','notes','lead_date','lead_time','page_url','month_key'];

        // Existing active leads for this report, indexed by email, phone, and name
        $existing_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$leads_table} WHERE report_id=%d AND trashed=0", $report_id
        ), ARRAY_A) ?: [];
        $by_email = []; $by_phone = []; $by_name = [];
        foreach ($existing_rows as $er) {
            $e = seo_dash_norm_val($er['email'] ?? '');
            $p = preg_replace('/[^0-9]/', '', $er['phone'] ?? '');
            $n = seo_dash_norm_val($er['name'] ?? '');
            if ($e !== '' && !isset($by_email[$e])) $by_email[$e] = $er;
            if (strlen($p) >= 7 && !isset($by_phone[$p])) $by_phone[$p] = $er;
            if ($n !== '' && !isset($by_name[$n])) $by_name[$n] = $er;
        }

        foreach ($parsed_rows as $r) {
            $email = sanitize_email($r['email'] ?? '');
            $name  = sanitize_text_field($r['name'] ?? '');
            $phone = sanitize_text_field($r['phone'] ?? '');
            $phone_digits = preg_replace('/[^0-9]/', '', $phone);

            $raw_status = sanitize_text_field($r['status'] ?? 'new');
            $status     = seo_dash_ensure_custom_lead_status($report_id, $raw_status);

            $row = [
                'report_id'  => $report_id,
                'month_key'  => sanitize_text_field($r['month_key'] ?? $r['month'] ?? date('Y-m')),
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'zip'        => sanitize_text_field($r['zip'] ?? ''),
                'source'     => sanitize_text_field($r['form'] ?? $r['source'] ?? ''),
                'message'    => sanitize_textarea_field($r['message'] ?? ''),
                'status'     => $status,
                'notes'      => sanitize_textarea_field($r['notes'] ?? ''),
                'lead_date'  => (function() use ($r) {
                    $dt = $r['date & time'] ?? $r['date_time'] ?? $r['datetime'] ?? '';
                    if ($dt) { $parts = explode(' ', trim($dt), 2); return sanitize_text_field($parts[0]); }
                    return sanitize_text_field($r['date'] ?? $r['lead_date'] ?? $r['date_received'] ?? current_time('Y-m-d'));
                })(),
                'lead_time'  => (function() use ($r) {
                    $dt = $r['date & time'] ?? $r['date_time'] ?? $r['datetime'] ?? '';
                    if ($dt) { $parts = explode(' ', trim($dt), 2); return sanitize_text_field($parts[1] ?? ''); }
                    return sanitize_text_field($r['time'] ?? $r['lead_time'] ?? '');
                })(),
                'page_url'   => esc_url_raw($r['page url'] ?? $r['page_url'] ?? ''),
            ];

            // Identity lookup: match by email, else phone, else name
            $e_norm = seo_dash_norm_val($email);
            $n_norm = seo_dash_norm_val($name);
            $match  = null;
            if ($e_norm !== '' && isset($by_email[$e_norm])) {
                $match = $by_email[$e_norm];
            } elseif (strlen($phone_digits) >= 7 && isset($by_phone[$phone_digits])) {
                $match = $by_phone[$phone_digits];
            } elseif ($n_norm !== '' && isset($by_name[$n_norm])) {
                $match = $by_name[$n_norm];
            }

            // Option A: If email/phone/name matches, but submission date OR message is different,
            // treat as a NEW distinct submission (new lead row) for that person instead of overwriting history.
            if ( $match && (
                    seo_dash_norm_val( $match['lead_date'] ?? '' ) !== seo_dash_norm_val( $row['lead_date'] ?? '' )
                 || seo_dash_norm_val( $match['message']   ?? '' ) !== seo_dash_norm_val( $row['message']   ?? '' )
            ) ) {
                $match = null;
            }

            if ($match) {
                // If ALL columns match exactly, mark as duplicate & skip
                if (seo_dash_rows_equal($match, $row, $compare_cols)) {
                    $skipped_dupes++;
                    continue;
                }
                // If 1 or more columns (status, notes, etc.) changed, UPDATE existing row
                $wpdb->update($leads_table, $row, ['id' => $match['id']]);
                if ($e_norm !== '') $by_email[$e_norm] = $row + ['id' => $match['id']];
                if (strlen($phone_digits) >= 7) $by_phone[$phone_digits] = $row + ['id' => $match['id']];
                if ($n_norm !== '') $by_name[$n_norm] = $row + ['id' => $match['id']];
                $updated++;
                continue;
            }

            // Completely new lead submission -> Insert new row
            $wpdb->insert($leads_table, $row);
            $new_id = $wpdb->insert_id;
            if ($e_norm !== '') $by_email[$e_norm] = $row + ['id' => $new_id];
            if (strlen($phone_digits) >= 7) $by_phone[$phone_digits] = $row + ['id' => $new_id];
            if ($n_norm !== '') $by_name[$n_norm] = $row + ['id' => $new_id];
            $inserted++;
        }
        $skip_msg = $skipped_dupes > 0 ? " ({$skipped_dupes} duplicate(s) skipped)" : '';
        $upd_msg  = $updated > 0 ? ", {$updated} updated" : '';
        seo_dash_json_success( [ 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped_dupes ], "✅ Imported {$inserted} new lead(s){$upd_msg}{$skip_msg}." );
    }
    else if ($type === 'click_tracking') {
        global $wpdb;
        $table = SEO_Dash_Database::$data_click_tracking;
        $inserted = 0;
        $skipped_dupes = 0;
        $existing_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0", $report_id
        ), ARRAY_A ) ?: [];

        foreach ($parsed_rows as $r) {
            $kw       = '';
            $src      = '';
            $type_val = 'link_click';
            $st       = 'new';
            $raw_date = '';
            $raw_time = '';

            foreach ($r as $k => $v) {
                $k_clean = preg_replace('/[^a-z0-9]/', '', strtolower($k));
                if (in_array($k_clean, ['textkeyword', 'keywordtext', 'keyword', 'text', 'anchor', 'ctatext', 'buttontext'])) {
                    if (!$kw) $kw = sanitize_text_field($v);
                } elseif (in_array($k_clean, ['sourcepage', 'sourceurl', 'page', 'url', 'source', 'pageurl', 'websiteurl'])) {
                    if (!$src) $src = esc_url_raw($v);
                } elseif (in_array($k_clean, ['clicktype', 'type', 'eventtype', 'action'])) {
                    if ($v !== '') $type_val = sanitize_text_field($v);
                } elseif (in_array($k_clean, ['status', 'state'])) {
                    if ($v !== '') $st = sanitize_key($v);
                } elseif (in_array($k_clean, ['time', 'clicktime', 'submittedtime'])) {
                    if ($v !== '') $raw_time = trim($v);
                } elseif (in_array($k_clean, ['submitteddate', 'date', 'clickdate', 'submitted', 'datetime', 'dateandtime', 'timestamp'])) {
                    if ($v !== '') $raw_date = trim($v);
                }
            }

            if (!$kw && !$src) continue;

            $dt_info   = seo_dash_parse_click_datetime( $raw_date, $raw_time );
            $date_val  = $dt_info['date'];
            $time_val  = $dt_info['time'];
            $month_key = sanitize_text_field($r['month_key'] ?? $r['month'] ?? (substr($date_val, 0, 7) ?: current_time('Y-m')));

            $row = [
                'report_id'    => $report_id,
                'month_key'    => $month_key,
                'keyword_text' => $kw,
                'source_page'  => $src,
                'click_type'   => $type_val,
                'status'       => $st ?: 'new',
                'click_date'   => $date_val,
                'click_time'   => $time_val,
            ];

            // Check exact duplicate across all columns (including date and time)
            $is_dupe = false;
            foreach ($existing_rows as $er) {
                if (
                    seo_dash_norm_val( $er['keyword_text'] ?? '' ) === seo_dash_norm_val( $row['keyword_text'] ?? '' ) &&
                    seo_dash_norm_val( $er['source_page'] ?? '' )  === seo_dash_norm_val( $row['source_page'] ?? '' ) &&
                    seo_dash_norm_val( $er['click_type'] ?? '' )   === seo_dash_norm_val( $row['click_type'] ?? '' ) &&
                    seo_dash_norm_val( $er['click_date'] ?? '' )   === seo_dash_norm_val( $row['click_date'] ?? '' ) &&
                    seo_dash_norm_val( $er['click_time'] ?? '' )   === seo_dash_norm_val( $row['click_time'] ?? '' )
                ) {
                    $is_dupe = true;
                    break;
                }
            }

            if ($is_dupe) {
                $skipped_dupes++;
                continue;
            }

            $wpdb->insert($table, $row);
            $row['id'] = $wpdb->insert_id;
            $existing_rows[] = $row;
            $inserted++;
        }
        $skip_msg = $skipped_dupes > 0 ? " ({$skipped_dupes} duplicate(s) skipped)" : '';
        seo_dash_json_success( [ 'inserted' => $inserted, 'skipped' => $skipped_dupes ], "✅ Imported {$inserted} click tracking record(s){$skip_msg}." );
    }
    else if ($type === 'documents') {
        global $wpdb;
        $table = SEO_Dash_Database::$data_documents;

        foreach ($parsed_rows as $r) {
            $title = sanitize_text_field( $r['title'] ?? '' );
            if ( ! $title ) continue;

            $client_email = sanitize_email( $r['client email'] ?? $r['client_email'] ?? '' );
            $client_id = null;
            if ( $client_email ) {
                $client_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . SEO_Dash_Database::$data_clients . " WHERE email = %s", $client_email ) );
            }
            
            $file_url = esc_url_raw( $r['file url'] ?? $r['file_url'] ?? '' );
            $file_type = sanitize_key( $r['file type'] ?? $r['file_type'] ?? 'url' );

            $data = [
                'report_id'    => $report_id,
                'title'        => $title,
                'client_id'    => $client_id,
                'sent_to_mail' => $client_id ? null : $client_email,
                'file_type'    => $file_type === 'file' ? 'file' : 'url',
                'file_url'     => $file_url,
                'file_name'    => basename( $file_url ),
                'status'       => sanitize_text_field( $r['status'] ?? 'pending' ),
                'admin_notes'  => sanitize_textarea_field( $r['admin notes'] ?? $r['admin_notes'] ?? '' ),
            ];

            $wpdb->insert( $table, $data );
            $inserted++;
        }
    }
    else if ($type === 'service' || $type === 'blog') {
        $custom_map = get_option("seo_dash_custom_pages_{$report_id}_{$type}", []);
        if (!is_array($custom_map)) $custom_map = [];

        $ga_map = get_option("seo_dash_sitemap_types_{$report_id}_ga", []);
        if (!is_array($ga_map)) $ga_map = [];
        $sc_map = get_option("seo_dash_sitemap_types_{$report_id}_sc", []);
        if (!is_array($sc_map)) $sc_map = [];

        // Use active sub-tab type from frontend so URLs go to the correct sub-tab
        $active_sub_type     = sanitize_key( wp_unslash( $_POST['active_sub_type'] ?? '' ) );
        $valid_service_types = [ 'page', 'service', 'location', 'city', 'product', 'portfolio' ];
        $valid_blog_types    = [ 'post', 'blog', 'category' ];
        if ( $type === 'service' ) {
            $sub_type = in_array( $active_sub_type, $valid_service_types, true ) ? $active_sub_type : 'page';
        } else {
            $sub_type = in_array( $active_sub_type, $valid_blog_types, true ) ? $active_sub_type : 'post';
        }

        // Normalise URL key so near-duplicates (trailing slash, case) don't create dupes
        $normalise_url = function(string $u): string {
            $u = trim($u);
            if (preg_match('/^(https?:\/\/[^\/]+)(.*)/i', $u, $m)) {
                $u = strtolower($m[1]) . $m[2];
            }
            if (strlen($u) > 1) $u = rtrim($u, '/');
            return $u;
        };

        // Build a lookup of normalized title -> URL key for existing entries,
        // so an incoming row can be matched to an existing row by name/title
        // even if its URL has changed (see seo_dash_rows_equal helper notes).
        $norm_title = function ($s) { return seo_dash_norm_val($s); };
        $title_index = [];
        foreach ($custom_map as $existing_u => $existing_cdata) {
            if (!empty($existing_cdata['trashed'])) continue;
            $t = $norm_title($existing_cdata['title'] ?? '');
            if ($t !== '') $title_index[$t] = $existing_u;
        }
        $skipped_dupes = 0;
        $compare_cols  = ['url','title','keyword','ranked_page','ai_overview','month','publish_date'];

        foreach ($parsed_rows as $r) {
            // Accept common URL column aliases
            $raw_u = sanitize_text_field(
                $r['url'] ?? $r['page url'] ?? $r['page_url'] ??
                $r['visit'] ?? $r['link'] ?? $r['address'] ??
                $r['service url'] ?? $r['service_url'] ??
                $r['post url'] ?? $r['post_url'] ?? ''
            );
            $u = $normalise_url($raw_u);
            if (!$u) continue;

            // Accept common title/name column aliases
            $title_val = $r['title'] ?? $r['page_title'] ?? $r['service name'] ?? $r['service_name'] ??
                         $r['page name'] ?? $r['name'] ?? $r['article title'] ?? $r['post title'] ?? '';

            // Accept common keyword aliases
            $kw_val = $r['keyword'] ?? $r['keywords'] ?? $r['target keyword'] ?? $r['target_keyword'] ?? $r['kw'] ?? '';

            // Accept ranked_page aliases
            $rp_val = $r['ranked_page'] ?? $r['rank'] ?? $r['ranking'] ?? $r['position'] ?? $r['page rank'] ?? '';

            // Accept month aliases
            $month_val = $r['month'] ?? $r['period'] ?? $r['month key'] ?? '';

            // Accept publish_date aliases
            $pd_val = $r['publish_date'] ?? $r['published'] ?? $r['publish date'] ?? $r['date published'] ?? $r['date'] ?? '';

            // Identity match: same URL, or (if URL is new) same title as an
            // existing row — in which case treat this as an update to that
            // row (possibly with a changed URL) rather than a new entry.
            $title_norm = $norm_title($title_val);
            $match_url  = null;
            if (isset($custom_map[$u])) {
                $match_url = $u;
            } elseif ($title_norm !== '' && isset($title_index[$title_norm])) {
                $match_url = $title_index[$title_norm];
            }
            $existing = ($match_url !== null) ? ($custom_map[$match_url] ?? []) : [];

            $new_row = [
                'url'              => $u,
                'title'            => sanitize_text_field($title_val ?: ($existing['title'] ?? '')),
                'keyword'          => sanitize_text_field($kw_val ?: ($existing['keyword'] ?? '')),
                'ranked_page'      => sanitize_text_field($rp_val ?: ($existing['ranked_page'] ?? '')),
                'ai_overview'      => intval($r['ai_overview'] ?? $r['ai overview'] ?? ($existing['ai_overview'] ?? 0)),
                'month'            => sanitize_text_field($month_val ?: ($existing['month'] ?? '')),
                'publish_date'     => sanitize_text_field($pd_val ?: ($existing['publish_date'] ?? '')),
                'show_on_overview' => (isset($_POST['context']) && $_POST['context'] === 'overview') ? 1 : intval($existing['show_on_overview'] ?? 0),
            ];

            // Exact duplicate (same URL and every other column unchanged) — ignore.
            if ($match_url === $u && !empty($existing) && seo_dash_rows_equal($existing, $new_row, $compare_cols)) {
                $skipped_dupes++;
                continue;
            }

            // Row identifies an existing entry under a DIFFERENT URL (same
            // title) — move it to the new URL instead of creating a duplicate.
            if ($match_url !== null && $match_url !== $u) {
                unset($custom_map[$match_url]);
                if (isset($ga_map[$match_url]) && !isset($ga_map[$u])) { $ga_map[$u] = $ga_map[$match_url]; }
                if (isset($sc_map[$match_url]) && !isset($sc_map[$u])) { $sc_map[$u] = $sc_map[$match_url]; }
                unset($ga_map[$match_url], $sc_map[$match_url]);
            }

            $custom_map[$u] = $new_row;
            if ($title_norm !== '') $title_index[$title_norm] = $u;

            // Tag under the active sub-tab; only overwrite if URL was previously untagged/other
            if ( !isset($ga_map[$u]) || $ga_map[$u] === 'other' ) $ga_map[$u] = $sub_type;
            if ( !isset($sc_map[$u]) || $sc_map[$u] === 'other' ) $sc_map[$u] = $sub_type;

            $inserted++;
        }

        update_option("seo_dash_custom_pages_{$report_id}_{$type}", $custom_map);
        update_option("seo_dash_sitemap_types_{$report_id}_ga", $ga_map);
        update_option("seo_dash_sitemap_types_{$report_id}_sc", $sc_map);

        // ── Upsert 30d / overall traffic into data_ga from CSV columns ──
        global $wpdb;
        $ga_tbl     = SEO_Dash_Database::$data_ga;
        $month_now  = date('Y-m');
        $traffic_rows = 0;
        foreach ($parsed_rows as $r) {
            $raw_page_url = sanitize_text_field(
                $r['url'] ?? $r['page url'] ?? $r['page_url'] ??
                $r['visit'] ?? $r['link'] ?? $r['address'] ??
                $r['service url'] ?? $r['service_url'] ??
                $r['post url'] ?? $r['post_url'] ?? ''
            );
            $page_url = isset($normalise_url) ? $normalise_url($raw_page_url) : trim($raw_page_url);
            if (!$page_url) continue;

            $val_30d = (int) str_replace([',', ' '], '',
                $r['30 days'] ?? $r['30days'] ?? $r['30d'] ?? $r['visit'] ?? $r['visits'] ?? $r['monthly'] ?? $r['monthly traffic'] ?? ''
            );
            $val_ov = (int) str_replace([',', ' '], '',
                $r['overall'] ?? $r['total'] ?? $r['all time'] ?? $r['total traffic'] ?? $r['all_time'] ?? $r['overall traffic'] ?? ''
            );

            foreach (['30d' => $val_30d, 'overall' => $val_ov] as $ptype => $val) {
                if ($val <= 0) continue;
                $existing_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$ga_tbl} WHERE report_id=%d AND page_url=%s AND period_type=%s AND trashed=0 LIMIT 1",
                    $report_id, $page_url, $ptype
                ));
                if ($existing_id) {
                    $wpdb->update($ga_tbl, ['users' => $val, 'sessions' => $val], ['id' => $existing_id]);
                } else {
                    $wpdb->insert($ga_tbl, [
                        'report_id'   => $report_id,
                        'period_type' => $ptype,
                        'month_key'   => $month_now,
                        'page_url'    => $page_url,
                        'users'       => $val,
                        'sessions'    => $val,
                        'pageviews'   => 0,
                    ]);
                }
                $traffic_rows++;
            }
        }

        $skip_msg = $skipped_dupes > 0 ? " ({$skipped_dupes} duplicate(s) skipped)" : '';
        SEO_Dash_Database::log_activity(
            'csv_import_pages', 'success',
            "CSV import ({$type}): {$inserted} rows upserted, {$skipped_dupes} duplicate(s) skipped. Sub-type: {$sub_type}. Traffic rows: {$traffic_rows}.",
            'report', $report_id
        );
        seo_dash_json_success(['inserted' => $inserted, 'skipped' => $skipped_dupes, 'traffic_rows' => $traffic_rows, 'type' => $type, 'sub_type' => $sub_type], "✅ Imported {$inserted} rows into " . ($type === 'blog' ? 'Blog Posts' : 'Service Pages') . "{$skip_msg}" . ($traffic_rows ? " + {$traffic_rows} traffic row(s) updated." : "."));
    }
    else {
        seo_dash_json_error("Unknown table type: {$type}");
    }
    
    seo_dash_json_success(['inserted' => $inserted], "✅ Successfully imported {$inserted} rows.");
});

add_action('wp_ajax_seo_dash_add_db_map_url', function(){
    seo_dash_verify_admin_ajax();
    $report_id = absint($_POST['report_id'] ?? 0);
    $db_type = sanitize_text_field($_POST['db_type'] ?? '');
    $urls = isset($_POST['urls']) && is_array($_POST['urls']) ? $_POST['urls'] : [];
    if (!empty($_POST['url'])) $urls[] = $_POST['url'];
    
    $page_type = sanitize_text_field($_POST['page_type'] ?? 'other');
    
    if (!$report_id || !$db_type || empty($urls)) seo_dash_json_error('Missing required fields.');
    
    $opt = "seo_dash_sitemap_types_{$report_id}_{$db_type}";
    $map = get_option($opt, []);
    if (!is_array($map)) $map = [];
    
    foreach ($urls as $u) {
        $u = sanitize_text_field($u);
        if ($u) $map[$u] = $page_type;
    }
    update_option($opt, $map);
    
    seo_dash_json_success('URL added to map.');
});

add_action('wp_ajax_seo_dash_upload_screenshot', function(){
    seo_dash_verify_admin_ajax();
    if (empty($_FILES['image'])) seo_dash_json_error('No file uploaded.');
    
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Allow documents (PDF, Word, Excel, CSV, etc.) in addition to images
    $allow_extra_mimes = function( $mimes ) {
        $mimes['pdf']  = 'application/pdf';
        $mimes['doc']  = 'application/msword';
        $mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $mimes['xls']  = 'application/vnd.ms-excel';
        $mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $mimes['csv']  = 'text/csv';
        $mimes['txt']  = 'text/plain';
        return $mimes;
    };
    add_filter( 'upload_mimes', $allow_extra_mimes );
    add_filter( 'wp_check_filetype_and_ext', function( $data, $file, $filename, $mimes ) {
        if ( ! $data['type'] ) {
            $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
            $ext_map = [
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'doc'  => 'application/msword',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'xls'  => 'application/vnd.ms-excel',
                'csv'  => 'text/csv',
                'pdf'  => 'application/pdf',
                'txt'  => 'text/plain',
            ];
            if ( isset( $ext_map[ $ext ] ) ) {
                $data['ext']             = $ext;
                $data['type']            = $ext_map[ $ext ];
                $data['proper_filename'] = $filename;
            }
        }
        return $data;
    }, 10, 4 );

    $attachment_id = media_handle_upload('image', 0);
    if (is_wp_error($attachment_id)) {
        seo_dash_json_error($attachment_id->get_error_message());
    }
    
    $url = wp_get_attachment_url($attachment_id);
    seo_dash_json_success(['url' => $url]);
});
add_action('wp_ajax_seo_dash_save_tech_speed', function(){
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    if (!$rid) seo_dash_json_error('Missing report ID.');
    
    $data = [
        'mobile'     => sanitize_text_field($_POST['mobile'] ?? ''),
        'desktop'    => sanitize_text_field($_POST['desktop'] ?? ''),
        'date'       => sanitize_text_field($_POST['date'] ?? ''),
        'report_url' => esc_url_raw($_POST['report_url'] ?? '')
    ];
    
    update_option("seo_dash_tech_speed_{$rid}", $data);
    SEO_Dash_Database::log_activity('tech_speed_saved', 'info', "Website speed data updated.", 'report', $rid);
    seo_dash_json_success(null, 'Saved successfully.');
});

if (!function_exists('seo_dash_fetch_single_pagespeed')) {
    function seo_dash_fetch_single_pagespeed($url, $strategy, $psi_key) {
        $api_base   = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed";
        $cats_full  = "&category=performance&category=accessibility&category=best-practices&category=seo";
        $cats_light = "&category=performance&category=accessibility&category=seo";

        $endpoint_url = "$api_base?url=" . urlencode($url) . "&strategy=$strategy&key=$psi_key";

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            // Attempt 1: All 4 categories; Attempt 2: Light categories (faster & less prone to Lighthouse timeouts)
            $req_url = $endpoint_url . ($attempt === 1 ? $cats_full : $cats_light);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $req_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 75);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SEO-Client-Dashboard');
            $raw = curl_exec($ch);
            curl_close($ch);

            $body = json_decode($raw, true) ?: [];

            if (!empty($body['lighthouseResult'])) {
                return $body;
            }

            // If attempt 1 failed with a temporary Lighthouse glitch or 500 error, pause briefly and retry
            if ($attempt < 2) {
                sleep(2);
            }
        }

        return $body ?? [];
    }
}

add_action('wp_ajax_seo_dash_psi_run', function() {
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    $url = esc_url_raw($_POST['url'] ?? '');
    if (!$rid) seo_dash_json_error('Missing report ID.');
    if (!$url) seo_dash_json_error('Missing URL.');

    $global_assignments = get_option('seo_dash_report_global_intg_' . $rid, []);
    $global_intg_id     = is_array($global_assignments) ? ($global_assignments['global'] ?? '') : '';
    
    $integrations = json_decode(get_option('seo_dash_global_integrations', '[]'), true);
    if (!is_array($integrations)) $integrations = [];
    
    $psi_key = '';
    if ($global_intg_id) {
        foreach ($integrations as $intg) {
            if (($intg['id'] ?? '') === $global_intg_id) {
                $psi_key = $intg['psi_api_key'] ?? '';
                break;
            }
        }
    }
    
    // Fallback: Grab the first available key
    if (!$psi_key) {
        foreach ($integrations as $intg) {
            if (!empty($intg['psi_api_key'])) {
                $psi_key = $intg['psi_api_key'];
                break;
            }
        }
    }
    
    if (!$psi_key) seo_dash_json_error('No PageSpeed Insights API Key found in Global Integrations.');

    // Fetch Mobile & Desktop sequentially with retry fallback to prevent Lighthouse runner collisions & timeouts
    $body_mobile  = seo_dash_fetch_single_pagespeed($url, 'mobile', $psi_key);
    $body_desktop = seo_dash_fetch_single_pagespeed($url, 'desktop', $psi_key);

    $lh_mobile  = $body_mobile['lighthouseResult'] ?? [];
    $lh_desktop = $body_desktop['lighthouseResult'] ?? [];

    if (empty($lh_mobile) && empty($lh_desktop)) {
        $err_m = $body_mobile['error']['message'] ?? '';
        $err_d = $body_desktop['error']['message'] ?? '';
        $err = $err_m ?: ($err_d ?: 'Google PageSpeed API returned no results. Please check your URL or try again in a few moments.');
        seo_dash_json_error('Google API Error: ' . $err);
    }

    $results = [
        'mobile'  => !empty($lh_mobile) ? $lh_mobile : $lh_desktop,
        'desktop' => !empty($lh_desktop) ? $lh_desktop : $lh_mobile
    ];

    // Extract scores for quick save
    $mScore = isset($lh_mobile['categories']['performance']['score']) ? round($lh_mobile['categories']['performance']['score'] * 100) : (isset($lh_desktop['categories']['performance']['score']) ? round($lh_desktop['categories']['performance']['score'] * 100) : 0);
    $dScore = isset($lh_desktop['categories']['performance']['score']) ? round($lh_desktop['categories']['performance']['score'] * 100) : (isset($lh_mobile['categories']['performance']['score']) ? round($lh_mobile['categories']['performance']['score'] * 100) : 0);
    
    $tech_speed = get_option("seo_dash_tech_speed_{$rid}", []);
    $tech_speed['mobile']  = $mScore ?: ($tech_speed['mobile'] ?? '');
    $tech_speed['desktop'] = $dScore ?: ($tech_speed['desktop'] ?? '');
    $tech_speed['date']    = date('Y-m-d');
    
    update_option("seo_dash_tech_speed_{$rid}", $tech_speed);

    seo_dash_json_success($results, 'PageSpeed test completed.');
});

// ── Update a single technical audit row status ──────────────────────────────
add_action( 'wp_ajax_seo_dash_save_technical_status', function () {
    seo_dash_verify_admin_ajax();
    $id     = intval( $_POST['row_id'] ?? 0 );
    $status = sanitize_key( $_POST['status'] ?? '' );

    if ( ! $id ) seo_dash_json_error( 'Missing row ID.' );
    if ( ! in_array( $status, [ 'pass', 'fail', 'warning', 'n/a' ], true ) ) seo_dash_json_error( 'Invalid status.' );

    global $wpdb;
    $table = SEO_Dash_Database::$data_technical;
    $updated = $wpdb->update( $table, [ 'status' => $status ], [ 'id' => $id ] );
    if ( $updated === false ) seo_dash_json_error( 'DB error: ' . $wpdb->last_error );

    seo_dash_json_success( null, 'Status updated.' );
} );

// ── Backlinks Handlers ──────────────────────────────────────────────────
add_action('wp_ajax_seo_dash_save_bk_status', function(){
    seo_dash_verify_admin_ajax();
    $id = intval($_POST['row_id'] ?? 0);
    $status = sanitize_key($_POST['status'] ?? '');
    if (!$id) seo_dash_json_error('Missing row ID.');
    
    global $wpdb;
    $updated = $wpdb->update(SEO_Dash_Database::$data_backlinks, ['status' => $status], ['id' => $id]);
    if ($updated === false) seo_dash_json_error('Database error.');
    seo_dash_json_success('Saved.');
});

add_action('wp_ajax_seo_dash_save_bk_field', function(){
    seo_dash_verify_admin_ajax();
    $id = intval($_POST['row_id'] ?? 0);
    $field = sanitize_key($_POST['field'] ?? '');
    $val = wp_unslash($_POST['val'] ?? '');
    if (!$id || !$field) seo_dash_json_error('Missing ID or field.');
    
    $allowed_fields = ['link_type', 'source_url', 'domain_rating', 'page_authority', 'spam_score', 'live_link', 'anchor_text', 'target_url', 'found_date', 'status'];
    if (!in_array($field, $allowed_fields)) seo_dash_json_error('Invalid field.');
    
    global $wpdb;
    $updated = $wpdb->update(SEO_Dash_Database::$data_backlinks, [$field => $val], ['id' => $id]);
    if ($updated === false) seo_dash_json_error('Database error.');
    seo_dash_json_success('Saved.');
});

add_action('wp_ajax_seo_dash_save_gmb_field', function(){
    seo_dash_verify_admin_ajax();
    $id = intval($_POST['row_id'] ?? 0);
    $field = sanitize_key($_POST['field'] ?? '');
    $val = wp_unslash($_POST['val'] ?? '');
    if (!$id || !$field) seo_dash_json_error('Missing ID or field.');
    
    $allowed_fields = ['calls', 'bookings', 'clicks_directions', 'clicks_website'];
    if (!in_array($field, $allowed_fields)) seo_dash_json_error('Invalid field.');
    
    global $wpdb;
    $updated = $wpdb->update(SEO_Dash_Database::$data_gmb, [$field => absint($val)], ['id' => $id]);
    if ($updated === false) seo_dash_json_error('Database error.');
    seo_dash_json_success('Saved.');
});

add_action('wp_ajax_seo_dash_add_backlink', function(){
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    if (!$rid) seo_dash_json_error('Missing report ID.');
    
    global $wpdb;
    $wpdb->insert(SEO_Dash_Database::$data_backlinks, [
        'report_id' => $rid,
        'link_type' => 'other',
        'source_url' => '',
        'domain_rating' => 0,
        'page_authority' => 0,
        'spam_score' => 0,
        'live_link' => '',
        'anchor_text' => '',
        'target_url' => '',
        'found_date' => date('Y-m-d'),
        'status' => 'live',
        'trashed' => 0,
    ]);
    
    seo_dash_json_success(['id' => $wpdb->insert_id]);
});

add_action('wp_ajax_seo_dash_save_gmb_front_settings', function(){
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    $cols = isset($_POST['cols']) && is_array($_POST['cols']) ? array_map('sanitize_key', $_POST['cols']) : [];
    if (!$rid) seo_dash_json_error('Missing report ID.');

    update_option("seo_dash_gmb_front_{$rid}", ['cols' => $cols]);

    // Persist any section-visibility toggles sent alongside cols
    $tabs = wp_unslash( $_POST['tabs'] ?? [] );
    if ( is_array( $tabs ) && ! empty( $tabs ) ) {
        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, meta FROM " . SEO_Dash_Database::$reports . " WHERE id = %d", $rid
        ), ARRAY_A );
        if ( $report ) {
            $meta = $report['meta'] ? ( json_decode( $report['meta'], true ) ?: [] ) : [];
            $allowed_vis = [ 'gmb_posts_table' ];
            foreach ( $tabs as $k => $v ) {
                $k = sanitize_key( $k );
                if ( in_array( $k, $allowed_vis, true ) ) {
                    $meta[ 'show_' . $k ] = (bool) intval( $v );
                }
            }
            $wpdb->update( SEO_Dash_Database::$reports, [ 'meta' => wp_json_encode( $meta ) ], [ 'id' => $rid ], [ '%s' ], [ '%d' ] );
        }
    }

    seo_dash_json_success('Saved.');
});

add_action('wp_ajax_seo_dash_save_gmb_perf_front_settings', function(){
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    $cols = isset($_POST['cols']) && is_array($_POST['cols']) ? array_map('sanitize_key', $_POST['cols']) : [];
    if (!$rid) seo_dash_json_error('Missing report ID.');

    update_option("seo_dash_gmb_perf_front_{$rid}", ['cols' => $cols]);

    // Persist any section-visibility toggles sent alongside cols
    $tabs = wp_unslash( $_POST['tabs'] ?? [] );
    if ( is_array( $tabs ) && ! empty( $tabs ) ) {
        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, meta FROM " . SEO_Dash_Database::$reports . " WHERE id = %d", $rid
        ), ARRAY_A );
        if ( $report ) {
            $meta = $report['meta'] ? ( json_decode( $report['meta'], true ) ?: [] ) : [];
            $allowed_vis = [ 'gmb_perf_table' ];
            foreach ( $tabs as $k => $v ) {
                $k = sanitize_key( $k );
                if ( in_array( $k, $allowed_vis, true ) ) {
                    $meta[ 'show_' . $k ] = (bool) intval( $v );
                }
            }
            $wpdb->update( SEO_Dash_Database::$reports, [ 'meta' => wp_json_encode( $meta ) ], [ 'id' => $rid ], [ '%s' ], [ '%d' ] );
        }
    }

    seo_dash_json_success('Saved.');
});

add_action('wp_ajax_seo_dash_save_bk_front_settings', function(){
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    $cols = isset($_POST['cols']) && is_array($_POST['cols']) ? array_map('sanitize_key', $_POST['cols']) : [];
    if (!$rid) seo_dash_json_error('Missing report ID.');

    update_option("seo_dash_bk_front_{$rid}", ['cols' => $cols]);

    // Persist any section-visibility toggles sent alongside cols
    $tabs = wp_unslash( $_POST['tabs'] ?? [] );
    if ( is_array( $tabs ) && ! empty( $tabs ) ) {
        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, meta FROM " . SEO_Dash_Database::$reports . " WHERE id = %d", $rid
        ), ARRAY_A );
        if ( $report ) {
            $meta = $report['meta'] ? ( json_decode( $report['meta'], true ) ?: [] ) : [];
            $allowed_vis = [ 'bk_table_section' ];
            foreach ( $tabs as $k => $v ) {
                $k = sanitize_key( $k );
                if ( in_array( $k, $allowed_vis, true ) ) {
                    $meta[ 'show_' . $k ] = (bool) intval( $v );
                }
            }
            $wpdb->update( SEO_Dash_Database::$reports, [ 'meta' => wp_json_encode( $meta ) ], [ 'id' => $rid ], [ '%s' ], [ '%d' ] );
        }
    }

    seo_dash_json_success('Saved.');
});

add_action('wp_ajax_seo_dash_save_ld_charts_settings', function(){
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    $status_type = sanitize_key($_POST['status_type'] ?? 'doughnut');
    $bar_type    = sanitize_key($_POST['bar_type'] ?? 'bar');
    if (!$rid) seo_dash_json_error('Missing report ID.');

    update_option("seo_dash_ld_charts_{$rid}", [
        'status_type' => $status_type,
        'bar_type'    => $bar_type
    ]);

    seo_dash_json_success('Leads chart settings saved.');
});

add_action('wp_ajax_seo_dash_save_gmb_charts_settings', function(){
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    $perf_type  = sanitize_key($_POST['perf_type'] ?? 'horizontalBar');
    $posts_type = sanitize_key($_POST['posts_type'] ?? 'bar');
    if (!$rid) seo_dash_json_error('Missing report ID.');

    update_option("seo_dash_gmb_charts_{$rid}", [
        'perf_type'  => $perf_type,
        'posts_type' => $posts_type
    ]);

    seo_dash_json_success('GMB chart settings saved.');
});

add_action('wp_ajax_seo_dash_save_tab_chart_settings', function(){
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    $tab = sanitize_key($_POST['tab'] ?? '');
    $charts_raw = isset($_POST['charts']) && is_array($_POST['charts']) ? wp_unslash($_POST['charts']) : [];
    if (!$rid || !$tab) seo_dash_json_error('Missing parameters.');

    $clean = [];
    foreach ($charts_raw as $k => $v) {
        if (is_array($v)) {
            $clean_sub = [];
            foreach ($v as $sub_k => $sub_v) {
                if ($sub_k === 'show') {
                    $clean_sub[$sub_k] = !empty($sub_v) && $sub_v !== 'false' && $sub_v !== '0';
                } else {
                    $clean_sub[sanitize_key($sub_k)] = sanitize_text_field($sub_v);
                }
            }
            $clean[sanitize_key($k)] = $clean_sub;
        } else {
            $clean[sanitize_key($k)] = sanitize_text_field($v);
        }
    }

    update_option("seo_dash_charts_{$tab}_{$rid}", $clean);

    seo_dash_json_success('Chart settings saved.');
});

// ── Save Hero Top Header KPI Cards Settings ──────────────────────────────────
add_action('wp_ajax_seo_dash_save_hero_kpis', function() {
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    if (!$rid) seo_dash_json_error('Missing report ID.');
    
    $kpis_raw = isset($_POST['kpis']) && is_array($_POST['kpis']) ? wp_unslash($_POST['kpis']) : [];
    $clean = [
        'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
        'sub'   => sanitize_text_field(wp_unslash($_POST['sub']   ?? '')),
    ];
    $keys = ['overall', 'latest', 'backlinks'];
    foreach ($keys as $k) {
        $item = $kpis_raw[$k] ?? [];
        $clean[$k] = [
            'show'  => !empty($item['show']) && $item['show'] !== 'false' && $item['show'] !== '0',
            'label' => sanitize_text_field($item['label'] ?? ''),
            'desc'  => sanitize_text_field($item['desc'] ?? ''),
        ];
    }
    update_option("seo_dash_hero_kpis_{$rid}", $clean);
    seo_dash_json_success('Hero Header & KPI card settings saved.');
});

// ── Save Generic Tab Header Settings (Title & Subtitle) ──────────────────────
add_action('wp_ajax_seo_dash_save_tab_header', function() {
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    $tab = sanitize_key($_POST['tab'] ?? '');
    if (!$rid || !$tab) seo_dash_json_error('Missing parameters.');

    $clean = [
        'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
        'sub'   => sanitize_text_field(wp_unslash($_POST['sub']   ?? '')),
    ];

    update_option("seo_dash_tab_hdr_{$tab}_{$rid}", $clean);
    seo_dash_json_success('Header settings saved.');
});

// ── Save Generic Tab KPI Cards Settings ──────────────────────────────────────
add_action('wp_ajax_seo_dash_save_tab_kpis', function() {
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    $tab = sanitize_key($_POST['tab'] ?? '');
    $kpis_raw = isset($_POST['kpis']) && is_array($_POST['kpis']) ? wp_unslash($_POST['kpis']) : [];
    if (!$rid || !$tab) seo_dash_json_error('Missing parameters.');

    $clean = [];
    foreach ($kpis_raw as $k => $item) {
        if (!is_array($item)) continue;
        $clean[sanitize_key($k)] = [
            'show'  => !empty($item['show']) && $item['show'] !== 'false' && $item['show'] !== '0',
            'label' => sanitize_text_field($item['label'] ?? ''),
            'desc'  => sanitize_text_field($item['desc'] ?? ''),
        ];
    }

    update_option("seo_dash_kpis_{$tab}_{$rid}", $clean);
    update_option("seo_dash_{$tab}_kpis_{$rid}", $clean);

    seo_dash_json_success('KPI card settings saved.');
});

// ── Save Overview Chart Settings ─────────────────────────────────────────────
add_action('wp_ajax_seo_dash_save_overview_charts', function() {
    seo_dash_verify_admin_ajax();
    $rid = intval($_POST['report_id'] ?? 0);
    if (!$rid) seo_dash_json_error('Missing report ID.');
    
    $charts_raw = isset($_POST['charts']) && is_array($_POST['charts']) ? wp_unslash($_POST['charts']) : [];
    $clean = [];
    $keys = ['traffic', 'ranked', 'backlinks'];
    foreach ($keys as $k) {
        $item = $charts_raw[$k] ?? [];
        $clean[$k] = [
            'show'  => !empty($item['show']) && $item['show'] !== 'false' && $item['show'] !== '0',
            'title' => sanitize_text_field($item['title'] ?? ''),
            'type'  => sanitize_text_field($item['type'] ?? 'bar'),
        ];
    }
    update_option("seo_dash_charts_overview_{$rid}", $clean);
    seo_dash_json_success('Overview chart settings saved.');
});

// ── Save Backlinks KPI Card Settings ────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_bk_kpis', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $kpis = wp_unslash( $_POST['kpis'] ?? [] );
    if ( ! is_array( $kpis ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'total', 'last_month', 'types', 'overview' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $kpis[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
        ];
    }

    update_option( "seo_dash_bk_kpis_{$rid}", $clean );
    seo_dash_json_success( null, 'Backlinks KPI card settings saved.' );
} );

// ── Save Technical KPI Card Settings ────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_tc_kpis', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $kpis = wp_unslash( $_POST['kpis'] ?? [] );
    if ( ! is_array( $kpis ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'mobile', 'desktop', 'passed', 'warnings', 'health', 'last_audit' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $kpis[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
        ];
    }

    update_option( "seo_dash_tc_kpis_{$rid}", $clean );
    seo_dash_json_success( null, 'Technical KPI card settings saved.' );
} );

// ── Save Technical Issues Table Settings ────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_tc_table', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $cols = array_map( 'sanitize_key', (array) ( $_POST['cols'] ?? [] ) );
    $tabs = wp_unslash( $_POST['tabs'] ?? [] );

    $allowed = [ 'type', 'description', 'status', 'severity', 'url' ];
    $clean   = array_values( array_intersect( $cols, $allowed ) );
    if ( empty( $clean ) ) $clean = $allowed;

    update_option( "seo_dash_tc_table_{$rid}", [ 'cols' => $clean ] );

    if ( is_array( $tabs ) && ! empty( $tabs ) ) {
        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, meta FROM " . SEO_Dash_Database::$reports . " WHERE id = %d", $rid
        ), ARRAY_A );
        if ( $report ) {
            $meta = $report['meta'] ? ( json_decode( $report['meta'], true ) ?: [] ) : [];
            foreach ( $tabs as $k => $v ) {
                $k = sanitize_key( $k );
                if ( $k === 'tc_table_section' ) {
                    $meta[ 'show_' . $k ] = (bool) intval( $v );
                }
            }
            $wpdb->update( SEO_Dash_Database::$reports, [ 'meta' => wp_json_encode( $meta ) ], [ 'id' => $rid ], [ '%s' ], [ '%d' ] );
        }
    }

    seo_dash_json_success( null, 'Technical table settings saved.' );
} );

// ── Save Chatbot Built-in Prompts (Quick Questions) ─────────────────────────
add_action( 'wp_ajax_seo_dash_save_ai_prompts', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $raw  = wp_unslash( $_POST['prompts'] ?? [] );
    $tabs = wp_unslash( $_POST['tabs'] ?? [] );

    $clean = [];
    if ( is_array( $raw ) ) {
        foreach ( $raw as $cat ) {
            if ( ! is_array( $cat ) ) continue;
            $label = sanitize_text_field( $cat['label'] ?? '' );
            if ( $label === '' ) continue;
            $qs = [];
            if ( is_array( $cat['qs'] ?? null ) ) {
                foreach ( $cat['qs'] as $q ) {
                    $q = sanitize_text_field( $q );
                    if ( $q !== '' ) $qs[] = $q;
                }
            }
            if ( empty( $qs ) ) continue;
            $clean[] = [ 'label' => $label, 'qs' => array_slice( $qs, 0, 20 ) ];
        }
    }
    $clean = array_slice( $clean, 0, 30 );

    update_option( "seo_dash_ai_prompts_{$rid}", $clean );

    if ( is_array( $tabs ) && ! empty( $tabs ) ) {
        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, meta FROM " . SEO_Dash_Database::$reports . " WHERE id = %d", $rid
        ), ARRAY_A );
        if ( $report ) {
            $meta = $report['meta'] ? ( json_decode( $report['meta'], true ) ?: [] ) : [];
            foreach ( $tabs as $k => $v ) {
                $k = sanitize_key( $k );
                if ( $k === 'ai_prompts_section' ) {
                    $meta[ 'show_' . $k ] = (bool) intval( $v );
                }
            }
            $wpdb->update( SEO_Dash_Database::$reports, [ 'meta' => wp_json_encode( $meta ) ], [ 'id' => $rid ], [ '%s' ], [ '%d' ] );
        }
    }

    seo_dash_json_success( null, 'Chatbot prompts saved.' );
} );

// ── Save Leads KPI Card Settings ────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_ld_kpis', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $kpis = wp_unslash( $_POST['kpis'] ?? [] );
    if ( ! is_array( $kpis ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'total', 'new', 'contacted', 'qualified', 'converted', 'lost' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $kpis[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
        ];
    }

    update_option( "seo_dash_ld_kpis_{$rid}", $clean );
    seo_dash_json_success( null, 'Leads KPI card settings saved.' );
} );

// ── Save Leads Table Column Settings ────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_ld_table', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $cols = wp_unslash( $_POST['cols'] ?? [] );
    if ( ! is_array( $cols ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'num', 'name', 'phone', 'email', 'message', 'status', 'notes' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $clean[ $k ] = ! empty( $cols[ $k ] );
    }
    update_option( "seo_dash_ld_table_cols_{$rid}", $clean );

    // Persist section-visibility toggle
    $tabs = wp_unslash( $_POST['tabs'] ?? [] );
    if ( is_array( $tabs ) && ! empty( $tabs ) ) {
        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, meta FROM " . SEO_Dash_Database::$reports . " WHERE id = %d", $rid
        ), ARRAY_A );
        if ( $report ) {
            $meta = $report['meta'] ? ( json_decode( $report['meta'], true ) ?: [] ) : [];
            if ( isset( $tabs['ld_table_section'] ) ) {
                $meta['show_ld_table_section'] = (bool) intval( $tabs['ld_table_section'] );
            }
            $wpdb->update( SEO_Dash_Database::$reports, [ 'meta' => wp_json_encode( $meta ) ], [ 'id' => $rid ], [ '%s' ], [ '%d' ] );
        }
    }

    seo_dash_json_success( null, 'Leads table column settings saved.' );
} );

// ── Save front tab visibility for a report (stored in report meta) ─────────
add_action( 'wp_ajax_seo_dash_save_report_front_tabs', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $raw  = $_POST['tabs'] ?? [];
    $allowed = ['overview','analytics','sc','service','blog','gmb','technical','backlinks','leads','ai','account',
                // Overview-tab section visibility
                'ov_kpis','ov_charts','ov_table','ov_screenshots','ov_summary',
                // Per-panel section show/hide toggles
                'ov_sections','ov_kpi_section','ov_charts_section','ov_table_section','ov_screenshots_section','ov_summary_section',
                // Analytics-tab section visibility
                'an_kpis','an_chart','an_table','an_pagedetail',
                // Analytics per-panel section show/hide toggles
                'an_sections','an_kpi_section','an_chart_section','an_table_section','an_pagedetail_section',
                // Search Console-tab section visibility
                'scn_kpis','scn_chart','scn_table','scn_pagedetail',
                // Search Console per-panel section show/hide toggles
                'scn_sections','scn_kpi_section','scn_chart_section','scn_table_section','scn_pagedetail_section',
                // Service Pages-tab section visibility
                'sp_kpis','sp_chart','sp_table','sp_pagedetail',
                // Service Pages per-panel section show/hide toggles
                'sp_sections','sp_kpi_section','sp_chart_section','sp_table_section','sp_pagedetail_section',
                // Blog Posts-tab section visibility
                'bl_kpis','bl_chart','bl_table','bl_pagedetail',
                // Blog Posts per-panel section show/hide toggles
                'bl_sections','bl_kpi_section','bl_chart_section','bl_table_section','bl_pagedetail_section',
                // Google Business (GMB)-tab section visibility
                'gmb_kpis','gmb_details','gmb_perf_chart','gmb_perf_table','gmb_posts_chart','gmb_posts_table',
                // GMB per-panel section show/hide toggles
                'gmb_sections','gmb_kpi_section','gmb_details_section','gmb_perf_chart_section','gmb_perf_table_section','gmb_posts_chart_section','gmb_posts_table_section',
                // Backlinks-tab section visibility
                'bk_kpis','bk_charts','bk_table',
                // Backlinks per-panel section show/hide toggles
                'bk_sections','bk_kpi_section','bk_charts_section','bk_dist_chart_section','bk_trend_chart_section','bk_table_section',
                // Leads-tab section visibility
                'ld_kpis','ld_charts','ld_table',
                // Leads per-panel section show/hide toggles
                'ld_sections','ld_kpi_section','ld_status_chart_section','ld_bar_chart_section','ld_table_section'];
    $tab_data = [];
    if ( is_array( $raw ) ) {
        foreach ( $raw as $k => $v ) {
            $k = sanitize_key( $k );
            if ( in_array( $k, $allowed, true ) ) {
                $tab_data[ 'show_' . $k ] = (bool) intval( $v );
            }
        }
    }

    global $wpdb;
    $report = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, meta FROM " . SEO_Dash_Database::$reports . " WHERE id = %d", $rid
    ), ARRAY_A );
    if ( ! $report ) seo_dash_json_error( 'Report not found.' );

    $meta = $report['meta'] ? ( json_decode( $report['meta'], true ) ?: [] ) : [];
    foreach ( $tab_data as $key => $val ) {
        $meta[ $key ] = $val;
    }

    $wpdb->update(
        SEO_Dash_Database::$reports,
        [ 'meta' => wp_json_encode( $meta ) ],
        [ 'id'   => $rid ],
        [ '%s' ],
        [ '%d' ]
    );

    seo_dash_json_success( null, 'Front tab visibility saved.' );
} );

// ── Save Overview KPI Card Settings ─────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_overview_kpis', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $kpis = wp_unslash( $_POST['kpis'] ?? [] );
    if ( ! is_array( $kpis ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'blog', 'mobile', 'desktop', 'ranked', 'leads' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $kpis[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
            'desc'  => sanitize_text_field( $c['desc']  ?? '' ),
        ];
    }

    update_option( "seo_dash_overview_kpis_{$rid}", $clean );
    seo_dash_json_success( null, 'KPI card settings saved.' );
} );

// ── Save Overview Ranked Pages Table Column Settings ────────────────────────
add_action( 'wp_ajax_seo_dash_save_overview_table', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $cols = wp_unslash( $_POST['cols'] ?? [] );
    if ( ! is_array( $cols ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'num', 'title', 'url', 'rank' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $clean[ $k ] = ! empty( $cols[ $k ] );
    }

    update_option( "seo_dash_overview_table_cols_{$rid}", $clean );
    seo_dash_json_success( null, 'Table column settings saved.' );
} );

// ── Save Overview Screenshots Settings ──────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_overview_screenshots', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $shots = wp_unslash( $_POST['shots'] ?? [] );
    if ( ! is_array( $shots ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'sc_monthly', 'sc_overall', 'ga_monthly', 'ga_overall' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $shots[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
        ];
    }

    update_option( "seo_dash_overview_screenshots_{$rid}", $clean );
    seo_dash_json_success( null, 'Screenshot settings saved.' );
} );

// ── Save Analytics KPI Card Settings ────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_analytics_kpis', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $kpis = wp_unslash( $_POST['kpis'] ?? [] );
    if ( ! is_array( $kpis ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'users', 'sessions', 'views', 'urls' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $kpis[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
        ];
    }

    update_option( "seo_dash_analytics_kpis_{$rid}", $clean );
    seo_dash_json_success( null, 'Analytics KPI card settings saved.' );
} );

// ── Save Search Console KPI Card Settings ───────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_sc_kpis', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $kpis = wp_unslash( $_POST['kpis'] ?? [] );
    if ( ! is_array( $kpis ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'clicks', 'impressions', 'ctr', 'urls' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $kpis[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
        ];
    }

    update_option( "seo_dash_sc_kpis_{$rid}", $clean );
    seo_dash_json_success( null, 'Search Console KPI card settings saved.' );
} );

// ── Save Service Pages KPI Card Settings ────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_sp_kpis', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $kpis = wp_unslash( $_POST['kpis'] ?? [] );
    if ( ! is_array( $kpis ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'total', 'p1', 'p2', 'p3plus', 'ai', 'traffic' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $kpis[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
        ];
    }

    update_option( "seo_dash_sp_kpis_{$rid}", $clean );
    seo_dash_json_success( null, 'Service Pages KPI card settings saved.' );
} );

// ── Save Blog Posts KPI Card Settings ───────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_blog_kpis', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $kpis = wp_unslash( $_POST['kpis'] ?? [] );
    if ( ! is_array( $kpis ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'posts', 'traffic', 'cats' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $kpis[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
        ];
    }

    update_option( "seo_dash_blog_kpis_{$rid}", $clean );
    seo_dash_json_success( null, 'Blog Posts KPI card settings saved.' );
} );

// ── Save Google Business (GMB) KPI Card Settings ────────────────────────────
add_action( 'wp_ajax_seo_dash_save_gmb_kpis', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    $kpis = wp_unslash( $_POST['kpis'] ?? [] );
    if ( ! is_array( $kpis ) ) seo_dash_json_error( 'Invalid data.' );

    $keys  = [ 'posts', 'calls', 'directions', 'bookings', 'website', 'months' ];
    $clean = [];
    foreach ( $keys as $k ) {
        $c = $kpis[ $k ] ?? [];
        $clean[ $k ] = [
            'show'  => ! empty( $c['show'] ),
            'label' => sanitize_text_field( $c['label'] ?? '' ),
        ];
    }

    update_option( "seo_dash_gmb_kpis_{$rid}", $clean );
    seo_dash_json_success( null, 'Google Business KPI card settings saved.' );
} );

/**
 * AJAX Handlers for Page Detail Section
 */

// Get list of pages and blogs for dropdown
add_action( 'wp_ajax_seo_dash_get_pages_dropdown', function () {
    seo_dash_verify_frontend_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
    
    if ( ! seo_dash_can_user_access_report( get_current_user_id(), $report_id ) ) {
        seo_dash_json_error( 'Access denied.', 403 );
    }

    global $wpdb;
    // Get unique urls and titles from data_ga for this report
    // Limit to 500 most recent URLs to prevent memory issues with large datasets
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT page_url, page_title 
         FROM " . SEO_Dash_Database::$data_ga . " 
         WHERE report_id = %d AND trashed = 0 
         GROUP BY page_url, page_title
         ORDER BY MAX(id) DESC
         LIMIT 500",
        $report_id
    ), ARRAY_A );

    $ga_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_ga", [] );
    if (!is_array($ga_type_map)) $ga_type_map = [];

    $pages = [];
    $blogs = [];

    foreach ($rows as $r) {
        $url = trim($r['page_url']);
        if (!$url) continue;
        
        $title = !empty($r['page_title']) ? $r['page_title'] : $url;
        
        // Use same logic as main analytics fetch
        $t = 'other';
        if (isset($ga_type_map[$url])) {
            $t = $ga_type_map[$url];
        } else {
            $pt = trim($r['page_title'] ?? '');
            if (preg_match('/^\[sitemap:([a-z0-9_-]+)\]/i', $pt, $m)) $t = strtolower($m[1]);
            else if (preg_match('/^\[([a-z0-9_-]+)\]$/i', $pt, $m)) $t = strtolower($m[1]);
        }

        $item = ['url' => $url, 'title' => $title];
        
        if (in_array($t, ['page','service','location'])) {
            $pages[] = $item;
        } else if (in_array($t, ['post','blog','category'])) {
            $blogs[] = $item;
        }
    }

    // Sort alphabetically
    usort($pages, fn($a, $b) => strcasecmp($a['title'], $b['title']));
    usort($blogs, fn($a, $b) => strcasecmp($a['title'], $b['title']));

    seo_dash_json_success([ 'pages' => $pages, 'blogs' => $blogs ]);
} );

// ── All-types dropdown — returns every sitemap type as separate bucket ────────
// Used by the Page Detail section to build dynamic type-filter buttons.
add_action( 'wp_ajax_seo_dash_get_pages_dropdown_all', function () {
    seo_dash_verify_frontend_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    if ( ! seo_dash_can_user_access_report( get_current_user_id(), $report_id ) ) {
        seo_dash_json_error( 'Access denied.', 403 );
    }

    global $wpdb;

    // Fetch all distinct URLs + titles from GA table
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT page_url, page_title
         FROM " . SEO_Dash_Database::$data_ga . "
         WHERE report_id = %d AND trashed = 0
         GROUP BY page_url, page_title
         ORDER BY MAX(id) DESC
         LIMIT 1000",
        $report_id
    ), ARRAY_A );

    $ga_type_map = get_option( "seo_dash_sitemap_types_{$report_id}_ga", [] );
    if ( ! is_array( $ga_type_map ) ) $ga_type_map = [];

    // Also pull any URLs in the type map that have no GA rows yet
    $url_index = [];
    foreach ( $rows as $r ) {
        $url_index[ trim( $r['page_url'] ) ] = trim( $r['page_title'] ?? '' );
    }
    foreach ( $ga_type_map as $url => $_ ) {
        if ( ! isset( $url_index[ $url ] ) ) {
            $url_index[ $url ] = '';  // no title yet
        }
    }

    $excluded_types = [ 'gmb_posts', 'gmb_post' ];
    $by_type = [];

    foreach ( $url_index as $url => $title ) {
        if ( ! $url ) continue;

        // Resolve type — map first, then page_title tag, fallback to 'other'
        $t = 'other';
        if ( isset( $ga_type_map[ $url ] ) ) {
            $t = $ga_type_map[ $url ];
        } else {
            $pt = trim( $title );
            if ( preg_match( '/^\[sitemap:([a-z0-9_-]+)\]/i', $pt, $m ) ) $t = strtolower( $m[1] );
            elseif ( preg_match( '/^\[([a-z0-9_-]+)\]$/i', $pt, $m ) )   $t = strtolower( $m[1] );
        }

        if ( in_array( $t, $excluded_types, true ) ) continue;

        $label = ! empty( $title ) ? $title : $url;
        if ( ! isset( $by_type[ $t ] ) ) $by_type[ $t ] = [];
        $by_type[ $t ][] = [ 'url' => $url, 'title' => $label ];
    }

    // Sort each bucket alphabetically
    foreach ( $by_type as $t => $items ) {
        usort( $by_type[ $t ], fn( $a, $b ) => strcasecmp( $a['title'], $b['title'] ) );
    }
    ksort( $by_type );

    seo_dash_json_success( [ 'byType' => $by_type ] );
} );

// Get stats for a single page URL
add_action( 'wp_ajax_seo_dash_get_page_detail_stats', function () {
    seo_dash_verify_frontend_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $url       = trim($_POST['url'] ?? '');
    if ( ! $report_id || ! $url ) seo_dash_json_error( 'Missing parameters.' );
    
    if ( ! seo_dash_can_user_access_report( get_current_user_id(), $report_id ) ) {
        seo_dash_json_error( 'Access denied.', 403 );
    }

    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT period_type, sessions, users, pageviews FROM " . SEO_Dash_Database::$data_ga . " 
         WHERE report_id = %d AND page_url = %s AND trashed = 0",
        $report_id, $url
    ), ARRAY_A );

    $stats = [
        '7d'      => ['sessions' => 0, 'users' => 0, 'views' => 0],
        '30d'     => ['sessions' => 0, 'users' => 0, 'views' => 0],
        '90d'     => ['sessions' => 0, 'users' => 0, 'views' => 0],
        'overall' => ['sessions' => 0, 'users' => 0, 'views' => 0],
    ];

    foreach ($rows as $r) {
        $p = $r['period_type'] ?: 'monthly';
        if (isset($stats[$p])) {
            $stats[$p]['sessions'] = intval($r['sessions']);
            $stats[$p]['users']    = intval($r['users']);
            $stats[$p]['views']    = intval($r['pageviews']);
        }
    }

    seo_dash_json_success($stats);
} );



// ── Helper: force-write a wp_options row, bypassing update_option's same-value guard ──
if ( ! function_exists('seo_dash_force_save_option') ) {
    function seo_dash_force_save_option( string $key, $value ): void {
        global $wpdb;
        $serialized = maybe_serialize( $value );
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $key
        ) );
        if ( $exists ) {
            $wpdb->update( $wpdb->options, [ 'option_value' => $serialized ], [ 'option_name' => $key ] );
        } else {
            $wpdb->insert( $wpdb->options, [ 'option_name' => $key, 'option_value' => $serialized, 'autoload' => 'no' ] );
        }
        wp_cache_delete( $key, 'options' );
        wp_cache_delete( 'alloptions', 'options' );
    }
}

// ── GMB Posts: Save single post ─────────────────────────────────────────────
add_action('wp_ajax_seo_dash_save_gmb_post', function(){
    seo_dash_verify_admin_ajax();
    $rid   = intval($_POST['report_id'] ?? 0);
    $url   = esc_url_raw(wp_unslash($_POST['url'] ?? ''));
    $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
    $month = sanitize_text_field(wp_unslash($_POST['month'] ?? ''));
    if (!$rid || !$url) seo_dash_json_error('Missing required fields.');

    global $wpdb;
    $wpdb->insert(
        SEO_Dash_Database::$data_gmb_posts,
        [
            'report_id' => $rid,
            'month_key' => $month,
            'title'     => $title,
            'post_url'  => $url,
            'status'    => 'Published',
            'trashed'   => 0,
        ],
        [ '%d', '%s', '%s', '%s', '%s', '%d' ]
    );
    seo_dash_json_success(['id' => $wpdb->insert_id], 'Post saved.');
});

// ── GMB Posts: Bulk / single action (trash, restore, delete) ────────────────
add_action('wp_ajax_seo_dash_gmb_post_action', function(){
    seo_dash_verify_admin_ajax();
    $rid    = intval($_POST['report_id'] ?? 0);
    $action = sanitize_key($_POST['bulk_action'] ?? '');
    $ids    = array_map('intval', (array)($_POST['ids'] ?? []));
    if (!$rid || !$action || empty($ids)) seo_dash_json_error('Missing params.');

    $allowed = ['trash','restore','delete','delete_all'];
    if (!in_array($action, $allowed)) seo_dash_json_error('Invalid action.');

    global $wpdb;
    $table = SEO_Dash_Database::$data_gmb_posts;

    if ($action === 'delete_all') {
        $wpdb->delete($table, ['report_id' => $rid], ['%d']);
        seo_dash_json_success('All deleted.');
    }

    foreach ($ids as $id) {
        if (!$id) continue;
        if ($action === 'trash')   { SEO_Dash_Database::trash_row($table, $id); }
        if ($action === 'restore') { SEO_Dash_Database::restore_row($table, $id); }
        if ($action === 'delete')  { SEO_Dash_Database::delete_row($table, $id); }
    }
    seo_dash_json_success('Done.');
});
