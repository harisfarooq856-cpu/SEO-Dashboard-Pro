<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_seo_dash_save_leads', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $month_key = seo_dash_sanitize_month( wp_unslash( $_POST['month_key'] ?? '' ) );
    $raw       = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : [];
    if ( ! $report_id || ! $month_key ) seo_dash_json_error( 'Missing parameters.' );

    $statuses = [ 'new', 'contacted', 'converted', 'lost' ];
    $rows = [];
    foreach ( $raw as $r ) {
        $rows[] = [
            'report_id' => $report_id,
            'month_key' => $month_key,
            'source'    => sanitize_text_field( $r['source']  ?? '' ),
            'name'      => sanitize_text_field( $r['name']    ?? '' ),
            'email'     => sanitize_email( $r['email']        ?? '' ),
            'phone'     => sanitize_text_field( $r['phone']   ?? '' ),
            'message'   => sanitize_textarea_field( $r['message'] ?? '' ),
            'status'    => seo_dash_sanitize_status( $r['status'] ?? 'new', $statuses, 'new' ),
            'lead_date' => sanitize_text_field( $r['lead_date'] ?? '' ),
        ];
    }
    if ( empty( $rows ) ) seo_dash_json_error( 'No rows.' );
    $n = SEO_Dash_Database::insert_data_rows( SEO_Dash_Database::$data_leads, $rows );
    SEO_Dash_Database::log_activity( 'leads_saved', 'success', "{$n} leads saved for {$month_key}.", 'report', $report_id );
    seo_dash_json_success( [ 'inserted' => $n ], "Saved {$n} leads." );
} );

add_action( 'wp_ajax_seo_dash_update_lead', function () {
    seo_dash_verify_admin_ajax();
    $id     = intval( $_POST['row_id'] ?? 0 );
    $status = sanitize_key( $_POST['status'] ?? '' );
    $notes  = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
    if ( ! $id ) seo_dash_json_error( 'Missing lead ID.' );
    global $wpdb;
    $wpdb->update( SEO_Dash_Database::$data_leads, [ 'status' => $status, 'notes' => $notes ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );
    SEO_Dash_Database::log_activity( 'lead_updated', 'info', "Lead {$id} status set to \"{$status}\"." );
    seo_dash_json_success( null, 'Lead updated.' );
} );

add_action( 'wp_ajax_seo_dash_delete_lead', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['row_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing lead ID.' );
    SEO_Dash_Database::delete_row( SEO_Dash_Database::$data_leads, $id );
    SEO_Dash_Database::log_activity( 'lead_deleted', 'warning', "Lead row {$id} permanently deleted." );
    seo_dash_json_success( null, 'Lead deleted.' );
} );


add_action( 'wp_ajax_seo_dash_add_lead', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    global $wpdb;
    $wpdb->insert( SEO_Dash_Database::$data_leads, [
        'report_id' => $report_id,
        'month_key' => date('Y-m'),
        'lead_date' => date('Y-m-d'),
        'status'    => 'new'
    ], [ '%d', '%s', '%s', '%s' ] );

    seo_dash_json_success( [ 'id' => $wpdb->insert_id ], 'Lead added.' );
} );

add_action( 'wp_ajax_seo_dash_save_lead_field', function () {
    seo_dash_verify_admin_ajax();
    $id    = intval( $_POST['row_id'] ?? 0 );
    $field = sanitize_key( $_POST['field'] ?? '' );
    $val   = wp_unslash( $_POST['val'] ?? '' );
    if ( ! $id || ! $field ) seo_dash_json_error( 'Missing data.' );

    $allowed = ['name', 'phone', 'email', 'zip', 'message', 'lead_date', 'lead_time', 'source', 'page_url', 'status', 'notes'];
    if ( ! in_array( $field, $allowed, true ) ) seo_dash_json_error( 'Invalid field.' );

    global $wpdb;
    $wpdb->update( SEO_Dash_Database::$data_leads, [ $field => $val ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
    seo_dash_json_success( null, 'Saved.' );
} );

// ── Admin: poll current lead statuses (picks up client-side status changes) ──
add_action( 'wp_ajax_seo_dash_get_lead_statuses', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );

    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, status, trashed FROM " . SEO_Dash_Database::$data_leads . " WHERE report_id = %d",
        $rid
    ), ARRAY_A );

    $out = [];
    foreach ( (array) $rows as $r ) {
        $out[ (int) $r['id'] ] = [
            'status'  => $r['status'] ?: 'new',
            'trashed' => (bool) $r['trashed'],
        ];
    }
    seo_dash_json_success( $out );
} );

add_action( 'wp_ajax_seo_dash_bulk_leads_status', function () {
    seo_dash_verify_admin_ajax();
    $status = sanitize_key( $_POST['status'] ?? '' );
    $ids    = array_map('intval', (array)($_POST['ids'] ?? []));
    if ( ! $status || empty($ids) ) seo_dash_json_error( 'Missing data.' );

    $allowed_statuses = ['new', 'contacted', 'checking', 'qualified', 'converted', 'lost'];
    if ( ! in_array( $status, $allowed_statuses, true ) ) seo_dash_json_error( 'Invalid status.' );

    global $wpdb;
    $id_list = implode(',', $ids);
    $wpdb->query( $wpdb->prepare( "UPDATE " . SEO_Dash_Database::$data_leads . " SET status = %s WHERE id IN ($id_list)", $status ) );
    
    seo_dash_json_success( null, 'Status updated.' );
} );

// Client: update lead status (logged-in non-admin)
add_action( 'wp_ajax_seo_dash_client_update_lead_status', function () {
    seo_dash_verify_frontend_ajax();
    if ( ! is_user_logged_in() ) seo_dash_json_error( 'Not authenticated.' );

    $id        = intval( $_POST['row_id'] ?? 0 );
    $status    = sanitize_key( $_POST['status'] ?? '' );
    $report_id = intval( $_POST['report_id'] ?? 0 );

    if ( ! $id || ! $status || ! $report_id ) seo_dash_json_error( 'Missing data.' );

    $custom_map = seo_dash_get_custom_statuses( $report_id );
    $allowed_statuses = array_keys( $custom_map );
    if ( ! in_array( $status, $allowed_statuses, true ) ) {
        // Auto-register if text format passed
        $status = seo_dash_ensure_custom_lead_status( $report_id, $status );
    }

    if ( ! seo_dash_can_user_access_report( get_current_user_id(), $report_id ) ) {
        seo_dash_json_error( 'Access denied.' );
    }

    global $wpdb;
    $lead_exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_leads . " WHERE id = %d AND report_id = %d",
        $id,
        $report_id
    ) );
    if ( ! $lead_exists ) {
        seo_dash_json_error( 'Access denied.' );
    }

    $wpdb->update( SEO_Dash_Database::$data_leads, [ 'status' => $status ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
    SEO_Dash_Database::log_activity( 'lead_status_client', 'info', "Lead {$id} status set to \"{$status}\" by client." );
    seo_dash_json_success( null, 'Status updated.' );
} );
add_action( 'wp_ajax_nopriv_seo_dash_client_update_lead_status', function () {
    seo_dash_json_error( 'Not authenticated.' );
} );

// Add custom lead status (admin or client)
add_action( 'wp_ajax_seo_dash_add_custom_lead_status', function () {
    seo_dash_verify_frontend_ajax();
    if ( ! is_user_logged_in() ) seo_dash_json_error( 'Not authenticated.' );
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $name      = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $icon      = sanitize_text_field( wp_unslash( $_POST['icon'] ?? '🏷️' ) );
    $color     = sanitize_text_field( wp_unslash( $_POST['color'] ?? '#3b82f6' ) );
    if ( ! $report_id || empty( $name ) ) seo_dash_json_error( 'Missing status name.' );

    if ( ! seo_dash_can_user_access_report( get_current_user_id(), $report_id ) ) {
        seo_dash_json_error( 'Access denied.' );
    }

    $slug = sanitize_key( str_replace( ' ', '_', strtolower( $name ) ) );
    if ( empty( $slug ) ) $slug = 'custom_' . time();

    $custom = get_option( "seo_dash_custom_lead_statuses_{$report_id}", [] );
    if ( ! is_array( $custom ) ) $custom = [];

    $exists = false;
    foreach ( $custom as $c ) {
        if ( isset( $c['slug'] ) && $c['slug'] === $slug ) {
            $exists = true;
            break;
        }
    }
    if ( ! $exists ) {
        $custom[] = [
            'slug'  => $slug,
            'label' => $name,
            'icon'  => $icon ?: '🏷️',
            'color' => $color ?: '#3b82f6',
        ];
        update_option( "seo_dash_custom_lead_statuses_{$report_id}", $custom );
    }

    seo_dash_json_success( [
        'slug'  => $slug,
        'label' => $name,
        'icon'  => $icon ?: '🏷️',
        'color' => $color ?: '#3b82f6',
        'all'   => array_values( seo_dash_get_custom_statuses( $report_id ) ),
    ], 'Custom lead status created.' );
} );

// Client: save lead notes
add_action( 'wp_ajax_seo_dash_client_save_lead_notes', function () {
    seo_dash_verify_frontend_ajax();
    if ( ! is_user_logged_in() ) seo_dash_json_error( 'Not authenticated.' );

    $id        = intval( $_POST['row_id'] ?? 0 );
    $notes     = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
    $report_id = intval( $_POST['report_id'] ?? 0 );

    if ( ! $id || ! $report_id ) seo_dash_json_error( 'Missing data.' );

    if ( ! seo_dash_can_user_access_report( get_current_user_id(), $report_id ) ) {
        seo_dash_json_error( 'Access denied.' );
    }

    global $wpdb;
    $lead_exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_leads . " WHERE id = %d AND report_id = %d",
        $id,
        $report_id
    ) );
    if ( ! $lead_exists ) {
        seo_dash_json_error( 'Access denied.' );
    }

    $wpdb->update( SEO_Dash_Database::$data_leads, [ 'notes' => $notes ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
    SEO_Dash_Database::log_activity( 'lead_notes_client', 'info', "Lead {$id} notes updated by client." );
    seo_dash_json_success( [ 'notes' => $notes ], 'Notes saved.' );
} );
add_action( 'wp_ajax_nopriv_seo_dash_client_save_lead_notes', function () {
    seo_dash_json_error( 'Not authenticated.' );
} );

// ── Click Tracking AJAX handlers ──
add_action( 'wp_ajax_seo_dash_add_click_tracking', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    global $wpdb;
    $wpdb->insert( SEO_Dash_Database::$data_click_tracking, [
        'report_id'    => $report_id,
        'month_key'    => date('Y-m'),
        'keyword_text' => 'New Keyword / CTA',
        'source_page'  => '',
        'click_type'   => 'button_click',
        'click_date'   => date('Y-m-d'),
        'click_time'   => date('H:i:s'),
    ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ] );

    seo_dash_json_success( [ 'id' => $wpdb->insert_id, 'click_time' => date('H:i:s') ], 'Click tracking record added.' );
} );

add_action( 'wp_ajax_seo_dash_save_click_tracking_field', function () {
    seo_dash_verify_admin_ajax();
    $id    = intval( $_POST['row_id'] ?? 0 );
    $field = sanitize_key( $_POST['field'] ?? '' );
    $val   = wp_unslash( $_POST['val'] ?? '' );
    if ( ! $id || ! $field ) seo_dash_json_error( 'Missing data.' );

    $allowed = ['keyword_text', 'source_page', 'click_type', 'click_date', 'click_time', 'status'];
    if ( ! in_array( $field, $allowed, true ) ) seo_dash_json_error( 'Invalid field.' );

    global $wpdb;
    $wpdb->update( SEO_Dash_Database::$data_click_tracking, [ $field => $val ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
    seo_dash_json_success( null, 'Saved.' );
} );

add_action( 'wp_ajax_seo_dash_delete_click_tracking', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['row_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing record ID.' );
    SEO_Dash_Database::delete_row( SEO_Dash_Database::$data_click_tracking, $id );
    seo_dash_json_success( null, 'Click tracking record deleted.' );
} );

// Client: update click tracking status (logged-in non-admin)
add_action( 'wp_ajax_seo_dash_client_update_click_tracking_status', function () {
    seo_dash_verify_frontend_ajax();
    if ( ! is_user_logged_in() ) seo_dash_json_error( 'Not authenticated.' );

    $id        = intval( $_POST['row_id'] ?? 0 );
    $status    = sanitize_key( $_POST['status'] ?? '' );
    $report_id = intval( $_POST['report_id'] ?? 0 );

    if ( ! $id || ! $status || ! $report_id ) seo_dash_json_error( 'Missing data.' );

    $custom_map = seo_dash_get_custom_statuses( $report_id );
    $allowed_statuses = array_keys( $custom_map );
    if ( ! in_array( $status, $allowed_statuses, true ) ) {
        $status = seo_dash_ensure_custom_lead_status( $report_id, $status );
    }

    if ( ! seo_dash_can_user_access_report( get_current_user_id(), $report_id ) ) {
        seo_dash_json_error( 'Access denied.' );
    }

    global $wpdb;
    $click_exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_click_tracking . " WHERE id = %d AND report_id = %d",
        $id,
        $report_id
    ) );
    if ( ! $click_exists ) {
        seo_dash_json_error( 'Access denied.' );
    }

    $wpdb->update( SEO_Dash_Database::$data_click_tracking, [ 'status' => $status ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
    SEO_Dash_Database::log_activity( 'click_tracking_status_client', 'info', "Click tracking {$id} status set to \"{$status}\" by client." );
    seo_dash_json_success( null, 'Status updated.' );
} );
add_action( 'wp_ajax_nopriv_seo_dash_client_update_click_tracking_status', function () {
    seo_dash_json_error( 'Not authenticated.' );
} );

