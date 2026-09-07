<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX handlers — Integrations (API credentials)
 */

// ── List all integrations ──────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_integrations', function () {
    seo_dash_verify_admin_ajax();
    $integrations = SEO_Dash_Database::get_integrations();
    foreach ( $integrations as &$row ) {
        unset( $row['credentials'] );
    }
    seo_dash_json_success( $integrations );
} );

// ── Save (create / update) an integration ─────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_integration', function () {
    seo_dash_verify_admin_ajax();

    $id    = intval( $_POST['integration_id'] ?? 0 );
    $label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
    $type  = sanitize_key( wp_unslash( $_POST['type']  ?? '' ) );

    if ( empty( $label ) ) seo_dash_json_error( 'Label is required.' );

    $allowed_types = [ 'google_analytics', 'search_console', 'gmb', 'groq', 'psi' ];
    if ( ! in_array( $type, $allowed_types, true ) ) seo_dash_json_error( 'Invalid integration type.' );

    $creds = seo_dash_build_credentials( $type );

    $data = [
        'id'          => $id ?: null,
        'label'       => $label,
        'type'        => $type,
        'credentials' => $creds,
    ];

    $saved_id = SEO_Dash_Database::save_integration( $data );
    if ( ! $saved_id ) {
        SEO_Dash_Database::log_activity( 'integration_save_failed', 'error', "Label: \"{$label}\", Type: {$type}.", 'integration' );
        seo_dash_json_error( 'Failed to save integration.' );
    }

    SEO_Dash_Database::log_activity(
        $id ? 'integration_updated' : 'integration_created',
        'success',
        "Integration \"{$label}\" ({$type}) " . ( $id ? 'updated' : 'created' ) . ".",
        'integration', $saved_id, $label
    );
    seo_dash_json_success( [ 'integration_id' => $saved_id ], $id ? 'Integration updated.' : 'Integration created.' );
} );

// ── Delete an integration ──────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_delete_integration', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['integration_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing integration ID.' );
    $intg = SEO_Dash_Database::get_integration( $id );
    SEO_Dash_Database::delete_integration( $id );
    SEO_Dash_Database::log_activity(
        'integration_deleted', 'warning',
        "Integration deleted.",
        'integration', $id, $intg['label'] ?? "ID {$id}"
    );
    seo_dash_json_success( null, 'Integration deleted.' );
} );

// ── Assign integration to a report for a scope ────────────────────────────
add_action( 'wp_ajax_seo_dash_assign_integration', function () {
    seo_dash_verify_admin_ajax();
    $report_id      = intval( $_POST['report_id']      ?? 0 );
    $integration_id = intval( $_POST['integration_id'] ?? 0 );
    $scope          = sanitize_key( $_POST['scope']    ?? '' );
    if ( ! $report_id || ! $integration_id || ! $scope ) seo_dash_json_error( 'Missing parameters.' );

    SEO_Dash_Database::assign_integration( $report_id, $integration_id, $scope );
    SEO_Dash_Database::log_activity(
        'integration_assigned', 'info',
        "Integration ID {$integration_id} assigned to report ID {$report_id} for scope \"{$scope}\".",
        'report', $report_id
    );
    seo_dash_json_success( null, 'Integration assigned.' );
} );

// ── Remove integration assignment from a report ────────────────────────────
add_action( 'wp_ajax_seo_dash_unassign_integration', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $scope     = sanitize_key( $_POST['scope'] ?? '' );
    if ( ! $report_id || ! $scope ) seo_dash_json_error( 'Missing parameters.' );

    global $wpdb;
    $wpdb->delete(
        SEO_Dash_Database::$report_integrations,
        [ 'report_id' => $report_id, 'scope' => $scope ],
        [ '%d', '%s' ]
    );
    SEO_Dash_Database::log_activity(
        'integration_unassigned', 'info',
        "Scope \"{$scope}\" unassigned from report ID {$report_id}.",
        'report', $report_id
    );
    seo_dash_json_success( null, 'Integration unassigned.' );
} );

// ── Get integrations assigned to a report ─────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_report_integrations', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_GET['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT ri.scope, ri.integration_id, i.label, i.type
         FROM " . SEO_Dash_Database::$report_integrations . " ri
         JOIN " . SEO_Dash_Database::$integrations . " i ON i.id = ri.integration_id
         WHERE ri.report_id = %d",
        $report_id
    ), ARRAY_A ) ?: [];
    seo_dash_json_success( $rows );
} );

// ── Test an integration's credentials ─────────────────────────────────────
add_action( 'wp_ajax_seo_dash_test_integration', function () {
    seo_dash_verify_admin_ajax();
    $id   = intval( $_POST['integration_id'] ?? 0 );
    $type = sanitize_key( $_POST['type']     ?? '' );

    if ( $id ) {
        $integration = SEO_Dash_Database::get_integration( $id );
        if ( ! $integration ) seo_dash_json_error( 'Integration not found.' );
        $creds = $integration['credentials'];
        $type  = $integration['type'];
    } else {
        $creds = seo_dash_build_credentials( $type );
    }

    switch ( $type ) {
        case 'google_analytics':
        case 'search_console':
        case 'gmb':
            $scope = 'https://www.googleapis.com/auth/analytics.readonly';
            if ( $type === 'search_console' ) $scope = 'https://www.googleapis.com/auth/webmasters.readonly';
            if ( $type === 'gmb' )            $scope = 'https://www.googleapis.com/auth/business.manage';
            $token = seo_dash_get_google_token( $creds, $scope );
            if ( $token ) {
                SEO_Dash_Database::log_activity( 'integration_test_ok', 'success', "Google ({$type}) auth test passed.", 'integration', $id );
                seo_dash_json_success( null, 'Google authentication successful.' );
            } else {
                SEO_Dash_Database::log_activity( 'integration_test_failed', 'error', "Google ({$type}) auth test failed.", 'integration', $id );
                seo_dash_json_error( 'Could not authenticate. Check your service account JSON.' );
            }
            break;

        case 'groq':
            $key   = $creds['api_key'] ?? '';
            $reply = seo_dash_groq_chat( $key, [ [ 'role' => 'user', 'content' => 'Say OK' ] ], 5 );
            if ( $reply ) {
                SEO_Dash_Database::log_activity( 'integration_test_ok', 'success', 'Groq API key test passed.', 'integration', $id );
                seo_dash_json_success( null, 'Groq API key is valid.' );
            } else {
                SEO_Dash_Database::log_activity( 'integration_test_failed', 'error', 'Groq API key test failed.', 'integration', $id );
                seo_dash_json_error( 'Invalid Groq API key.' );
            }
            break;

        case 'psi':
            $api_key  = $creds['api_key'] ?? '';
            $test_url = 'https://www.google.com';
            $url      = add_query_arg( [ 'url' => $test_url, 'strategy' => 'desktop', 'key' => $api_key ],
                                       'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' );
            $res  = wp_remote_get( $url, [ 'timeout' => 15 ] );
            $code = wp_remote_retrieve_response_code( $res );
            if ( $code === 200 ) {
                SEO_Dash_Database::log_activity( 'integration_test_ok', 'success', 'PSI API key test passed.', 'integration', $id );
                seo_dash_json_success( null, 'PageSpeed Insights API key is valid.' );
            } else {
                SEO_Dash_Database::log_activity( 'integration_test_failed', 'error', "PSI API key test failed (HTTP {$code}).", 'integration', $id );
                seo_dash_json_error( 'Invalid PSI API key.' );
            }
            break;

        default:
            seo_dash_json_error( 'Unknown integration type.' );
    }
} );

// ── PageSpeed Insights run ─────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_psi_run', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $test_url  = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
    $strategy  = in_array( $_POST['strategy'] ?? 'mobile', [ 'mobile', 'desktop' ], true ) ? $_POST['strategy'] : 'mobile';
    if ( ! $report_id || ! $test_url ) seo_dash_json_error( 'Missing parameters.' );

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'psi' );
    if ( ! $integration ) seo_dash_json_error( 'No PSI integration assigned to this report.' );

    $api_key  = $integration['credentials']['api_key'] ?? '';
    $url      = add_query_arg( [
        'url'      => $test_url,
        'strategy' => $strategy,
        'key'      => $api_key,
        'category' => [ 'performance', 'accessibility', 'seo', 'best-practices' ],
    ], 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' );

    $response = wp_remote_get( $url, [ 'timeout' => 30 ] );
    if ( is_wp_error( $response ) ) {
        SEO_Dash_Database::log_activity( 'psi_run_failed', 'error', "PSI request failed for {$test_url}.", 'report', $report_id );
        seo_dash_json_error( 'PSI request failed.' );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['lighthouseResult'] ) ) {
        SEO_Dash_Database::log_activity( 'psi_run_failed', 'error', "Invalid PSI response for {$test_url}.", 'report', $report_id );
        seo_dash_json_error( 'Invalid PSI response.' );
    }

    $cats   = $body['lighthouseResult']['categories'] ?? [];
    $result = [
        'url'            => $test_url,
        'strategy'       => $strategy,
        'performance'    => round( ( $cats['performance']['score']    ?? 0 ) * 100 ),
        'accessibility'  => round( ( $cats['accessibility']['score']  ?? 0 ) * 100 ),
        'seo'            => round( ( $cats['seo']['score']            ?? 0 ) * 100 ),
        'best_practices' => round( ( $cats['best-practices']['score'] ?? 0 ) * 100 ),
        'fcp'            => $body['lighthouseResult']['audits']['first-contentful-paint']['displayValue']   ?? '',
        'lcp'            => $body['lighthouseResult']['audits']['largest-contentful-paint']['displayValue'] ?? '',
        'cls'            => $body['lighthouseResult']['audits']['cumulative-layout-shift']['displayValue']  ?? '',
        'tbt'            => $body['lighthouseResult']['audits']['total-blocking-time']['displayValue']      ?? '',
    ];

    SEO_Dash_Database::log_activity(
        'psi_run_ok', 'success',
        "PSI ({$strategy}) for {$test_url}: perf={$result['performance']}, seo={$result['seo']}.",
        'report', $report_id
    );
    seo_dash_json_success( $result );
} );

// ── Private: build credentials array from POST fields by type ─────────────
function seo_dash_build_credentials( string $type ): array {
    switch ( $type ) {
        case 'google_analytics':
        case 'search_console':
        case 'gmb':
            $json_raw = wp_unslash( $_POST['service_account_json'] ?? '' );
            if ( $json_raw ) {
                $parsed = json_decode( $json_raw, true );
                if ( is_array( $parsed ) ) return $parsed;
            }
            return [
                'type'            => 'service_account',
                'client_email'    => sanitize_email( wp_unslash( $_POST['client_email'] ?? '' ) ),
                'private_key'     => wp_unslash( $_POST['private_key'] ?? '' ),
                'project_id'      => sanitize_text_field( wp_unslash( $_POST['project_id'] ?? '' ) ),
                'ga4_property_id' => sanitize_text_field( wp_unslash( $_POST['ga4_property_id'] ?? '' ) ),
                'sc_site_url'     => esc_url_raw( wp_unslash( $_POST['sc_site_url'] ?? '' ) ),
            ];
        case 'groq':
            return [ 'api_key' => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ) ];
        case 'psi':
            return [ 'api_key' => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ) ];
        default:
            return [];
    }
}

// ── Save Chatbot Settings per report ───────────────────────────────────────
add_action('wp_ajax_seo_dash_save_chatbot_settings', function() {
    seo_dash_verify_admin_ajax();

    $rid = intval($_POST['report_id'] ?? 0);
    if (!$rid) seo_dash_json_error('Invalid report ID.');

    $override = sanitize_text_field($_POST['override'] ?? '0');
    $model    = sanitize_text_field($_POST['model']    ?? ''); // provider name

    update_option("seo_dash_chatbot_override_{$rid}", $override);
    update_option("seo_dash_chatbot_model_{$rid}",    $model);

    // "Use this connection" flags per provider
    update_option("seo_dash_chatbot_use_deepseek_{$rid}", sanitize_text_field($_POST['use_deepseek'] ?? '0'));
    update_option("seo_dash_chatbot_use_groq_{$rid}",     sanitize_text_field($_POST['use_groq']     ?? '0'));
    update_option("seo_dash_chatbot_use_cerebras_{$rid}", sanitize_text_field($_POST['use_cerebras'] ?? '0'));
    update_option("seo_dash_chatbot_use_gemini_{$rid}",   sanitize_text_field($_POST['use_gemini']   ?? '0'));

    // Per-report API key overrides
    if (!empty($_POST['deepseek_key'])) {
        update_option("seo_dash_chatbot_deepseek_{$rid}", seo_dash_sec_encrypt(sanitize_text_field($_POST['deepseek_key'])));
    }
    if (!empty($_POST['groq_key'])) {
        update_option("seo_dash_chatbot_groq_{$rid}", seo_dash_sec_encrypt(sanitize_text_field($_POST['groq_key'])));
    }
    if (!empty($_POST['cerebras_key'])) {
        update_option("seo_dash_chatbot_cerebras_{$rid}", seo_dash_sec_encrypt(sanitize_text_field($_POST['cerebras_key'])));
    }
    if (!empty($_POST['gemini_key'])) {
        update_option("seo_dash_chatbot_gemini_{$rid}", seo_dash_sec_encrypt(sanitize_text_field($_POST['gemini_key'])));
    }

    // Per-report model overrides per provider
    if (!empty($_POST['deepseek_model_override'])) {
        update_option("seo_dash_chatbot_deepseek_model_{$rid}", sanitize_text_field($_POST['deepseek_model_override']));
    }
    if (!empty($_POST['groq_model_override'])) {
        update_option("seo_dash_chatbot_groq_model_{$rid}", sanitize_text_field($_POST['groq_model_override']));
    }
    if (!empty($_POST['cerebras_model_override'])) {
        update_option("seo_dash_chatbot_cerebras_model_{$rid}", sanitize_text_field($_POST['cerebras_model_override']));
    }
    if (!empty($_POST['gemini_model_override'])) {
        update_option("seo_dash_chatbot_gemini_model_{$rid}", sanitize_text_field($_POST['gemini_model_override']));
    }

    SEO_Dash_Database::log_activity('chatbot_settings_updated', 'info', "Chatbot settings updated for report (provider: {$model}).", 'report', $rid);

    seo_dash_json_success(null, 'Chatbot settings saved.');
});
