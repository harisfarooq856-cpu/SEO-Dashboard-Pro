<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_seo_dash_save_backlinks', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month_key = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
    $raw       = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : [];
    if ( ! $report_id || ! $month_key ) seo_dash_json_error( 'Missing parameters.' );

    $link_types = [ 'dofollow', 'nofollow', 'sponsored', 'ugc' ];
    $rows = [];
    foreach ( $raw as $r ) {
        $rows[] = [
            'report_id'     => $report_id,
            'month_key'     => $month_key,
            'source_url'    => sanitize_text_field( $r['source_url']  ?? '' ),
            'target_url'    => sanitize_text_field( $r['target_url']  ?? '' ),
            'anchor_text'   => sanitize_text_field( $r['anchor_text'] ?? '' ),
            'domain_rating' => absint( $r['domain_rating'] ?? 0 ) ?: null,
            'link_type'     => seo_dash_sanitize_status( $r['link_type'] ?? 'dofollow', $link_types, 'dofollow' ),
            'status'        => sanitize_key( $r['status'] ?? 'live' ),
            'found_date'    => sanitize_text_field( $r['found_date'] ?? '' ),
        ];
    }
    if ( empty( $rows ) ) seo_dash_json_error( 'No rows.' );
    if ( ! empty( $_POST['replace'] ) ) SEO_Dash_Database::clear_month( SEO_Dash_Database::$data_backlinks, $report_id, $month_key );
    $n = SEO_Dash_Database::insert_data_rows( SEO_Dash_Database::$data_backlinks, $rows );
    SEO_Dash_Database::log_activity( 'backlinks_saved', 'success', "{$n} backlinks saved for {$month_key}.", 'report', $report_id );
    seo_dash_json_success( [ 'inserted' => $n ], "Saved {$n} backlinks." );
} );

add_action( 'wp_ajax_seo_dash_delete_backlink', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['row_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing ID.' );
    SEO_Dash_Database::delete_row( SEO_Dash_Database::$data_backlinks, $id );
    SEO_Dash_Database::log_activity( 'backlink_deleted', 'warning', "Backlink row {$id} deleted." );
    seo_dash_json_success( null, 'Backlink deleted.' );
} );
