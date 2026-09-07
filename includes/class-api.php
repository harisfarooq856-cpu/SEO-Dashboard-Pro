<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO_Dash_API
 * Loads all AJAX handler files. Each file self-registers its own
 * add_action( 'wp_ajax_*' ) calls so nothing needs to be wired here.
 */
class SEO_Dash_API {

    public static function init(): void {
        $dir = SEO_DASH_PATH . 'includes/';
        require_once $dir . 'ajax-reports.php';
        require_once $dir . 'ajax-clients.php';
        require_once $dir . 'ajax-data-ga.php';
        require_once $dir . 'ajax-custom-pages.php';
        require_once $dir . 'ajax-data-sc.php';
        require_once $dir . 'ajax-data-leads.php';
        require_once $dir . 'ajax-data-backlinks.php';
        require_once $dir . 'ajax-data-documents.php';
        require_once $dir . 'ajax-ai.php';
        require_once $dir . 'ajax-integrations.php';
        require_once $dir . 'ajax-global-integrations.php';
        require_once $dir . 'ajax-settings.php';
        require_once $dir . 'ajax-gmail-oauth.php';
        require_once $dir . 'ajax-sitemap.php';
        require_once $dir . 'ajax-gsheets.php';
        require_once $dir . 'ajax-job-queue.php';

        // Extra AJAX handlers specific to v5 (pages, gmb, technical).
        add_action( 'wp_ajax_seo_dash_save_pages',        [ __CLASS__, 'save_pages' ] );
        add_action( 'wp_ajax_seo_dash_save_gmb',          [ __CLASS__, 'save_gmb' ] );
        add_action( 'wp_ajax_seo_dash_save_gmb_details',  [ __CLASS__, 'save_gmb_details' ] );
        add_action( 'wp_ajax_seo_dash_save_technical',    [ __CLASS__, 'save_technical' ] );

        // Nonce refresh for cached pages.
        add_action( 'wp_ajax_seo_dash_refresh_nonce',        [ __CLASS__, 'refresh_nonce' ] );
        add_action( 'wp_ajax_nopriv_seo_dash_refresh_nonce', [ __CLASS__, 'refresh_nonce' ] );
    }

    // ── Save pages (service / blog) ────────────────────────────────────────
    public static function save_pages(): void {
        seo_dash_verify_admin_ajax();
        $rid  = intval( $_POST['report_id'] ?? 0 );
        $raw  = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : [];
        if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

        $rows = [];
        foreach ( $raw as $r ) {
            $rows[] = [
                'report_id'  => $rid,
                'month_key'  => seo_dash_sanitize_month( $r['month_key'] ?? '' ),
                'page_type'  => in_array( $r['page_type'] ?? '', ['service','blog'], true ) ? $r['page_type'] : 'service',
                'url'        => sanitize_text_field( $r['url'] ?? '' ),
                'title'      => sanitize_text_field( $r['title'] ?? '' ),
                'sessions'   => absint( $r['sessions'] ?? 0 ),
                'clicks'     => absint( $r['clicks'] ?? 0 ),
                'impressions'=> absint( $r['impressions'] ?? 0 ),
                'position'   => round( floatval( $r['position'] ?? 0 ), 2 ),
            ];
        }
        if ( empty( $rows ) ) seo_dash_json_error( 'No rows.' );
        $n = SEO_Dash_Database::insert_data_rows( SEO_Dash_Database::$data_pages, $rows );
        SEO_Dash_Database::log_activity( 'pages_saved', 'success', "{$n} page rows saved.", 'report', $rid );
        seo_dash_json_success( ['inserted' => $n], "Saved {$n} rows." );
    }

    // ── Save GMB row ───────────────────────────────────────────────────────
    public static function save_gmb(): void {
        seo_dash_verify_admin_ajax();
        $rid       = intval( $_POST['report_id'] ?? 0 );
        $month_key = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
        if ( ! $rid || ! $month_key ) seo_dash_json_error( 'Missing parameters.' );

        $row = [
            'report_id'        => $rid,
            'month_key'        => $month_key,
            'views_search'     => absint( $_POST['views_search']     ?? 0 ),
            'views_maps'       => absint( $_POST['views_maps']       ?? 0 ),
            'clicks_website'   => absint( $_POST['clicks_website']   ?? 0 ),
            'clicks_directions'=> absint( $_POST['clicks_directions'] ?? 0 ),
            'calls'            => absint( $_POST['calls']            ?? 0 ),
            'bookings'         => absint( $_POST['bookings']         ?? 0 ),
        ];
        SEO_Dash_Database::insert_data_rows( SEO_Dash_Database::$data_gmb, [$row] );
        SEO_Dash_Database::log_activity( 'gmb_saved', 'success', "GMB data saved for {$month_key}.", 'report', $rid );
        seo_dash_json_success( null, 'GMB data saved.' );
    }

    // ── Save GMB Business Details ──────────────────────────────────────────
    public static function save_gmb_details(): void {
        seo_dash_verify_admin_ajax();
        $rid = intval( $_POST['report_id'] ?? 0 );
        if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

        $allowed_days = [ 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' ];
        $hours = [];
        $raw_hours = isset( $_POST['hours'] ) && is_array( $_POST['hours'] ) ? $_POST['hours'] : [];
        foreach ( $allowed_days as $day ) {
            $h = $raw_hours[ $day ] ?? [];
            $hours[ $day ] = [
                'closed' => ! empty( $h['closed'] ),
                'open'   => sanitize_text_field( $h['open']   ?? '' ),
                'close'  => sanitize_text_field( $h['close']  ?? '' ),
            ];
        }

        $details = [
            'business_name'    => sanitize_text_field( wp_unslash( $_POST['business_name']    ?? '' ) ),
            'address'          => sanitize_text_field( wp_unslash( $_POST['address']          ?? '' ) ),
            'phone'            => sanitize_text_field( wp_unslash( $_POST['phone']            ?? '' ) ),
            'website_url'      => esc_url_raw( wp_unslash( $_POST['website_url']      ?? '' ) ),
            'primary_category' => sanitize_text_field( wp_unslash( $_POST['primary_category'] ?? '' ) ),
            'description'      => sanitize_textarea_field( wp_unslash( $_POST['description']  ?? '' ) ),
            'profile_url'      => esc_url_raw( wp_unslash( $_POST['profile_url']      ?? '' ) ),
            'hours'            => $hours,
            'updated_at'       => current_time( 'Y-m-d H:i:s' ),
        ];

        update_option( "seo_dash_gmb_details_{$rid}", $details );
        SEO_Dash_Database::log_activity( 'gmb_details_saved', 'success', 'GMB business details updated.', 'report', $rid );
        seo_dash_json_success( $details, 'Business details saved.' );
    }

    // ── Save technical issues ──────────────────────────────────────────────
    public static function save_technical(): void {
        seo_dash_verify_admin_ajax();
        $rid = intval( $_POST['report_id'] ?? 0 );
        
        // Support either single item or array of rows
        $raw = [];
        if ( isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ) {
            $raw = wp_unslash( $_POST['rows'] );
        } elseif ( isset( $_POST['issue_type'] ) ) {
            $raw[] = wp_unslash( $_POST );
        }

        if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

        $rows = [];
        foreach ( $raw as $r ) {
            $rows[] = [
                'report_id'   => $rid,
                'month_key'   => seo_dash_sanitize_month( $r['month_key'] ?? date('Y-m') ),
                'issue_type'  => sanitize_text_field( $r['issue_type']  ?? '' ),
                'severity'    => sanitize_key( $r['severity'] ?? 'medium' ),
                'url'         => sanitize_text_field( $r['url']         ?? '' ),
                'description' => sanitize_textarea_field( $r['description'] ?? '' ),
                'status'      => sanitize_key( $r['status'] ?? 'warning' ),
            ];
        }
        if ( empty( $rows ) ) seo_dash_json_error( 'No rows provided.' );
        
        $n = SEO_Dash_Database::insert_data_rows( SEO_Dash_Database::$data_technical, $rows );
        SEO_Dash_Database::log_activity( 'technical_saved', 'success', "{$n} technical issues saved.", 'report', $rid );
        seo_dash_json_success( ['inserted' => $n], "Saved {$n} issues." );
    }


    // ── Nonce refresh ──────────────────────────────────────────────────────
    public static function refresh_nonce(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Not logged in.' ], 200 );
            wp_die();
        }
        wp_send_json_success( [
            'nonce'          => wp_create_nonce( 'seo_dash_admin' ),
            'nonce_frontend' => wp_create_nonce( 'seo_dash_frontend' ),
        ] );
    }
}
