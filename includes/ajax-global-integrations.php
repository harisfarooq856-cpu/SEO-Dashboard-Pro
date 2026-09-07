<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Global Integrations — stored in wp_options as seo_dash_global_integrations
 * Each record: id, name, notes, ga4_property_id, ga4_json_key,
 *              gsc_site_url, gsc_json_key, psi_api_key, updated_at
 */

// ── Helper: get all global integrations ────────────────────────────────────
if ( ! function_exists('seo_dash_get_global_integrations') ) {
    function seo_dash_get_global_integrations(): array {
        $raw     = get_option( 'seo_dash_global_integrations', '[]' );
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }
}

// ── Helper: get one global integration by ID ───────────────────────────────
if ( ! function_exists('seo_dash_get_global_integration_by_id') ) {
    function seo_dash_get_global_integration_by_id( string $id ): ?array {
        foreach ( seo_dash_get_global_integrations() as $intg ) {
            if ( ( $intg['id'] ?? '' ) === $id ) return $intg;
        }
        return null;
    }
}

// ── Save (create / update) a global integration ────────────────────────────
add_action( 'wp_ajax_seo_dash_save_global_integration', function () {
    seo_dash_verify_admin_ajax();

    $id   = sanitize_key( wp_unslash( $_POST['id']   ?? '' ) );
    $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    if ( ! $name ) seo_dash_json_error( 'Integration name is required.' );

    $integrations = seo_dash_get_global_integrations();

    // Preserve existing JSON keys when editing if none supplied.
    $existing = [];
    if ( $id ) {
        foreach ( $integrations as $intg ) {
            if ( ( $intg['id'] ?? '' ) === $id ) { $existing = $intg; break; }
        }
    }

    $raw_ga4_json = trim( wp_unslash( $_POST['ga4_json_key'] ?? '' ) );
    $raw_gsc_json = trim( wp_unslash( $_POST['gsc_json_key'] ?? '' ) );

    // Validate JSON if supplied.
    foreach ( [ ['ga4', $raw_ga4_json], ['gsc', $raw_gsc_json] ] as [ $svc, $raw ] ) {
        if ( $raw ) {
            $p = json_decode( $raw, true );
            if ( ! $p ) seo_dash_json_error( strtoupper( $svc ) . ' JSON is not valid: ' . json_last_error_msg() );
            if ( empty( $p['private_key'] ) )   seo_dash_json_error( strtoupper( $svc ) . ' JSON missing private_key.' );
            if ( empty( $p['client_email'] ) )  seo_dash_json_error( strtoupper( $svc ) . ' JSON missing client_email.' );
        }
    }

    // Encrypt JSON keys before storage.
    $ga4_json_enc = '';
    if ( $raw_ga4_json ) {
        $ga4_json_enc = function_exists('seo_dash_sec_encrypt') ? seo_dash_sec_encrypt( $raw_ga4_json ) : $raw_ga4_json;
    } elseif ( !empty( $existing['ga4_json_key'] ) ) {
        $ga4_json_enc = $existing['ga4_json_key'];
    }

    $gsc_json_enc = '';
    if ( $raw_gsc_json ) {
        $gsc_json_enc = function_exists('seo_dash_sec_encrypt') ? seo_dash_sec_encrypt( $raw_gsc_json ) : $raw_gsc_json;
    } elseif ( !empty( $existing['gsc_json_key'] ) ) {
        $gsc_json_enc = $existing['gsc_json_key'];
    }

    $record = [
        'id'              => $id ?: ( 'intg_' . uniqid() ),
        'name'            => $name,
        'notes'           => sanitize_text_field( wp_unslash( $_POST['notes'] ?? '' ) ),
        'ga4_property_id' => sanitize_text_field( wp_unslash( $_POST['ga4_property_id'] ?? '' ) ) ?: ( $existing['ga4_property_id'] ?? '' ),
        'ga4_json_key'    => $ga4_json_enc,
        'gsc_site_url'    => esc_url_raw( trim( wp_unslash( $_POST['gsc_site_url'] ?? '' ) ) )     ?: ( $existing['gsc_site_url']    ?? '' ),
        'gsc_json_key'    => $gsc_json_enc,
        'psi_api_key'     => sanitize_text_field( wp_unslash( $_POST['psi_api_key'] ?? '' ) )      ?: ( $existing['psi_api_key']      ?? '' ),
        'gsheet_id'       => sanitize_text_field( wp_unslash( $_POST['gsheet_id']   ?? '' ) )      ?: ( $existing['gsheet_id']        ?? '' ),
        'gsheet_name'     => sanitize_text_field( wp_unslash( $_POST['gsheet_name'] ?? '' ) )      ?: ( $existing['gsheet_name']      ?? '' ),
        'updated_at'      => current_time( 'Y-m-d H:i:s' ),
    ];

    if ( $id ) {
        foreach ( $integrations as &$intg ) {
            if ( ( $intg['id'] ?? '' ) === $id ) { $intg = $record; break; }
        }
        unset( $intg );
    } else {
        $integrations[] = $record;
    }

    update_option( 'seo_dash_global_integrations', wp_json_encode( $integrations ) );
    seo_dash_json_success( [ 'id' => $record['id'] ], 'Integration saved: ' . $name );
} );

// ── Delete a global integration ────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_delete_global_integration', function () {
    seo_dash_verify_admin_ajax();

    $id = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) );
    if ( ! $id ) seo_dash_json_error( 'Missing integration ID.' );

    $integrations = seo_dash_get_global_integrations();
    $filtered     = array_values( array_filter( $integrations, fn($i) => ( $i['id'] ?? '' ) !== $id ) );
    update_option( 'seo_dash_global_integrations', wp_json_encode( $filtered ) );

    seo_dash_json_success( null, 'Integration deleted.' );
} );

// ── Assign a global integration to a report scope ─────────────────────────
add_action( 'wp_ajax_seo_dash_assign_global_integration', function () {
    seo_dash_verify_admin_ajax();

    $report_id   = intval( $_POST['report_id'] ?? 0 );
    $scope       = sanitize_key( wp_unslash( $_POST['scope'] ?? '' ) );
    $intg_id     = sanitize_key( wp_unslash( $_POST['integration_id'] ?? '' ) );

    if ( ! $report_id || ! $scope ) seo_dash_json_error( 'Missing parameters.' );

    $assignments = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
    if ( ! is_array( $assignments ) ) $assignments = [];

    if ( $intg_id ) {
        $assignments[ $scope ] = $intg_id;
    } else {
        unset( $assignments[ $scope ] );
    }

    update_option( 'seo_dash_report_global_intg_' . $report_id, $assignments );
    seo_dash_json_success( null, 'Integration assigned.' );
} );
