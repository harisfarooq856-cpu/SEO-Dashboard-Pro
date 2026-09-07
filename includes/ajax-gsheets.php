<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Google Sheets Integration — AJAX Handlers
 *
 * Uses the same Service Account JSON key stored per integration (GA4 key works
 * for Sheets API too, provided the sheet is shared with the service account email).
 *
 * Actions:
 *   seo_dash_get_intgs        — list all global integrations (id + name)
 *   seo_dash_gsheet_list      — list all spreadsheets accessible to the service account
 *   seo_dash_gsheet_list_tabs — list sheet tabs/ranges within a spreadsheet
 *   seo_dash_gsheet_sync      — fetch rows from a sheet and upsert into DB / custom pages
 *   seo_dash_gsheet_save_link — save sheet_id + tab_name per integration per report
 *   seo_dash_gsheet_get_links — get saved sheet links for a report
 */

// ── 0. List all global integrations (id + name) ───────────────────────────────
add_action( 'wp_ajax_seo_dash_get_intgs', function () {
    seo_dash_verify_admin_ajax();
    $all = function_exists( 'seo_dash_get_global_integrations' ) ? seo_dash_get_global_integrations() : [];
    $result = array_map( function ( $i ) {
        return [ 'id' => $i['id'] ?? '', 'name' => $i['name'] ?? '' ];
    }, is_array( $all ) ? $all : [] );
    seo_dash_json_success( [ 'integrations' => array_values( $result ) ] );
} );

// ── Helper: get a Google OAuth2 token for a service account JSON ─────────────
if ( ! function_exists( 'seo_dash_gsheet_get_token' ) ) {
    function seo_dash_gsheet_get_token( string $json_raw ): string {
        $creds = json_decode( $json_raw, true );
        if ( ! $creds || empty( $creds['private_key'] ) || empty( $creds['client_email'] ) ) {
            return '';
        }

        $now = time();
        $header  = base64_encode( json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
        // Full (read+write) spreadsheets scope so we can both import FROM and
        // export/update TO the linked sheet. Drive scope stays read-only —
        // we only need it to list files, never to create/delete them.
        $payload = base64_encode( json_encode( [
            'iss'   => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ] ) );

        // URL-safe base64.
        $header  = rtrim( strtr( $header,  '+/', '-_' ), '=' );
        $payload = rtrim( strtr( $payload, '+/', '-_' ), '=' );

        $signing_input = $header . '.' . $payload;
        $private_key   = openssl_pkey_get_private( $creds['private_key'] );
        if ( ! $private_key ) return '';

        $signature = '';
        openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 );
        $signature = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );

        $jwt = $signing_input . '.' . $signature;

        $resp = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body'    => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
        ] );

        if ( is_wp_error( $resp ) ) return '';
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        return $body['access_token'] ?? '';
    }
}

// ── Helper: get decrypted service account JSON for an integration ─────────────
if ( ! function_exists( 'seo_dash_gsheet_get_json' ) ) {
    function seo_dash_gsheet_get_json( string $intg_id ): string {
        if ( ! function_exists( 'seo_dash_get_global_integration_by_id' ) ) {
            require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
        }
        $intg = seo_dash_get_global_integration_by_id( $intg_id );
        if ( ! $intg ) return '';

        $enc = $intg['ga4_json_key'] ?? '';
        if ( ! $enc ) return '';

        // Decrypt if encryption is available.
        $raw = function_exists( 'seo_dash_sec_decrypt' ) ? seo_dash_sec_decrypt( $enc ) : $enc;
        return $raw ?: '';
    }
}

// ── 1. List spreadsheets accessible to the service account ────────────────────
add_action( 'wp_ajax_seo_dash_gsheet_list', function () {
    seo_dash_verify_admin_ajax();

    $intg_id = sanitize_key( wp_unslash( $_POST['intg_id'] ?? '' ) );
    if ( ! $intg_id ) seo_dash_json_error( 'Missing integration ID.' );

    $json_raw = seo_dash_gsheet_get_json( $intg_id );
    if ( ! $json_raw ) seo_dash_json_error( 'No service account key found for this integration.' );

    $token = seo_dash_gsheet_get_token( $json_raw );
    if ( ! $token ) seo_dash_json_error( 'Failed to authenticate with Google. Check your service account key.' );

    // Use Drive API to list spreadsheets owned/shared with this service account.
    $resp = wp_remote_get(
        'https://www.googleapis.com/drive/v3/files?q=mimeType%3D%22application%2Fvnd.google-apps.spreadsheet%22&fields=files(id,name)&pageSize=50',
        [
            'timeout' => 15,
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
        ]
    );

    if ( is_wp_error( $resp ) ) seo_dash_json_error( 'Drive API error: ' . $resp->get_error_message() );

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    $files = $body['files'] ?? [];

    $hidden_ids   = get_option( "seo_dash_gsheet_hidden_{$intg_id}", [] );
    if ( ! is_array( $hidden_ids ) ) $hidden_ids = [];
    $show_hidden  = ! empty( $_POST['show_hidden'] );

    $sheets = [];
    $hidden_count = 0;
    foreach ( $files as $f ) {
        $is_hidden = in_array( $f['id'], $hidden_ids, true );
        if ( $is_hidden ) $hidden_count++;
        if ( $is_hidden && ! $show_hidden ) continue;
        $sheets[] = [ 'id' => $f['id'], 'name' => $f['name'], 'hidden' => $is_hidden ];
    }

    seo_dash_json_success( [ 'sheets' => $sheets, 'hidden_count' => $hidden_count ], count( $sheets ) . ' spreadsheet(s) found.' );
} );

// ── 1b. Hide / unhide a spreadsheet from the picker list ──────────────────────
// This does NOT revoke the service account's access in Google Drive — it only
// removes the entry from this plugin's "Load My Sheets" dropdown so old or
// irrelevant spreadsheets stop cluttering the picker. To fully revoke access,
// remove the service account's email from the sheet's Share settings in Google.
add_action( 'wp_ajax_seo_dash_gsheet_hide', function () {
    seo_dash_verify_admin_ajax();

    $intg_id        = sanitize_key( wp_unslash( $_POST['intg_id'] ?? '' ) );
    $spreadsheet_id = sanitize_text_field( wp_unslash( $_POST['spreadsheet_id'] ?? '' ) );
    $hide           = ! empty( $_POST['hide'] ); // true = hide, false = unhide

    if ( ! $intg_id || ! $spreadsheet_id ) seo_dash_json_error( 'Missing parameters.' );

    $opt_key = "seo_dash_gsheet_hidden_{$intg_id}";
    $hidden  = get_option( $opt_key, [] );
    if ( ! is_array( $hidden ) ) $hidden = [];

    if ( $hide ) {
        if ( ! in_array( $spreadsheet_id, $hidden, true ) ) $hidden[] = $spreadsheet_id;
    } else {
        $hidden = array_values( array_diff( $hidden, [ $spreadsheet_id ] ) );
    }

    update_option( $opt_key, $hidden );
    seo_dash_json_success( [ 'hidden' => $hidden ], $hide ? 'Sheet hidden from list.' : 'Sheet restored to list.' );
} );

// ── 1c. Rename a connected Google Sheet ────────────────────────────────────────
// Renames the actual spreadsheet file in Google Drive (so it shows the new
// name everywhere — Drive, Sheets, and this plugin's picker), and refreshes
// the cached name on every saved report link that points at this spreadsheet.
add_action( 'wp_ajax_seo_dash_gsheet_rename', function () {
    seo_dash_verify_admin_ajax();

    $intg_id        = sanitize_key( wp_unslash( $_POST['intg_id'] ?? '' ) );
    $spreadsheet_id = sanitize_text_field( wp_unslash( $_POST['spreadsheet_id'] ?? '' ) );
    $new_name       = sanitize_text_field( wp_unslash( $_POST['new_name'] ?? '' ) );

    if ( ! $intg_id || ! $spreadsheet_id || ! $new_name ) seo_dash_json_error( 'Missing parameters.' );

    $json_raw = seo_dash_gsheet_get_json( $intg_id );
    if ( ! $json_raw ) seo_dash_json_error( 'No service account key found for this integration.' );

    $token = seo_dash_gsheet_get_token( $json_raw );
    if ( ! $token ) seo_dash_json_error( 'Failed to authenticate with Google. Check your service account key.' );

    $resp = wp_remote_request(
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $spreadsheet_id ) . '?fields=id,name',
        [
            'method'  => 'PATCH',
            'timeout' => 15,
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
            'body'    => json_encode( [ 'name' => $new_name ] ),
        ]
    );

    if ( is_wp_error( $resp ) ) seo_dash_json_error( 'Drive API error: ' . $resp->get_error_message() );

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( isset( $body['error'] ) ) {
        seo_dash_json_error( $body['error']['message'] ?? 'Drive API error while renaming. Make sure the service account has Editor access to this sheet.' );
    }

    // Refresh the cached name on any report links that point at this spreadsheet,
    // so reports already linked to it show the new name without re-linking.
    global $wpdb;
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            'seo_dash_gsheet_link_%'
        )
    );
    foreach ( (array) $rows as $row ) {
        $link = maybe_unserialize( $row->option_value );
        if ( is_array( $link ) && ( $link['spreadsheet_id'] ?? '' ) === $spreadsheet_id ) {
            $link['spreadsheet_name'] = $new_name;
            update_option( $row->option_name, $link );
        }
    }

    seo_dash_json_success( [ 'id' => $body['id'] ?? $spreadsheet_id, 'name' => $body['name'] ?? $new_name ], 'Sheet renamed.' );
} );

// ── 2. List tabs/ranges within a spreadsheet ──────────────────────────────────
add_action( 'wp_ajax_seo_dash_gsheet_list_tabs', function () {
    seo_dash_verify_admin_ajax();

    $intg_id       = sanitize_key( wp_unslash( $_POST['intg_id'] ?? '' ) );
    $spreadsheet_id = sanitize_text_field( wp_unslash( $_POST['spreadsheet_id'] ?? '' ) );

    if ( ! $intg_id || ! $spreadsheet_id ) seo_dash_json_error( 'Missing parameters.' );

    $json_raw = seo_dash_gsheet_get_json( $intg_id );
    if ( ! $json_raw ) seo_dash_json_error( 'No service account key found.' );

    $token = seo_dash_gsheet_get_token( $json_raw );
    if ( ! $token ) seo_dash_json_error( 'Failed to authenticate with Google.' );

    $resp = wp_remote_get(
        'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '?fields=sheets.properties(title,sheetId)',
        [
            'timeout' => 15,
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
        ]
    );

    if ( is_wp_error( $resp ) ) seo_dash_json_error( 'Sheets API error: ' . $resp->get_error_message() );

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( isset( $body['error'] ) ) {
        seo_dash_json_error( $body['error']['message'] ?? 'Sheets API error.' );
    }

    $tabs = [];
    foreach ( $body['sheets'] ?? [] as $s ) {
        $tabs[] = [ 'id' => $s['properties']['sheetId'], 'title' => $s['properties']['title'] ];
    }

    seo_dash_json_success( [ 'tabs' => $tabs ] );
} );

// ── 3. Save sheet link (spreadsheet_id + tab) per integration per report ──────
add_action( 'wp_ajax_seo_dash_gsheet_save_link', function () {
    seo_dash_verify_admin_ajax();

    $report_id      = intval( wp_unslash( $_POST['report_id'] ?? 0 ) );
    $intg_id        = sanitize_key( wp_unslash( $_POST['intg_id'] ?? '' ) );
    $tab_type       = sanitize_key( wp_unslash( $_POST['tab_type'] ?? '' ) ); // ga|service|blog
    $spreadsheet_id = sanitize_text_field( wp_unslash( $_POST['spreadsheet_id'] ?? '' ) );
    $spreadsheet_name = sanitize_text_field( wp_unslash( $_POST['spreadsheet_name'] ?? '' ) );
    $tab_name       = sanitize_text_field( wp_unslash( $_POST['tab_name'] ?? '' ) );
    $tab_id         = sanitize_text_field( wp_unslash( $_POST['tab_id'] ?? '' ) );

    if ( ! $report_id || ! $intg_id || ! $tab_type ) seo_dash_json_error( 'Missing parameters.' );

    $opt_key = "seo_dash_gsheet_link_{$report_id}_{$tab_type}";
    $link    = [
        'intg_id'          => $intg_id,
        'spreadsheet_id'   => $spreadsheet_id,
        'spreadsheet_name' => $spreadsheet_name,
        'tab_id'           => $tab_id,
        'tab_name'         => $tab_name,
        'updated_at'       => current_time( 'Y-m-d H:i:s' ),
    ];

    update_option( $opt_key, $link );
    seo_dash_json_success( $link, 'Sheet link saved.' );
} );

// ── 4. Get saved sheet links for a report ─────────────────────────────────────
add_action( 'wp_ajax_seo_dash_gsheet_get_links', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( wp_unslash( $_POST['report_id'] ?? 0 ) );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    $links = [];
    foreach ( [ 'ga', 'service', 'blog', 'gmb', 'gmb_posts', 'technical', 'backlinks', 'leads', 'click_tracking', 'documents' ] as $t ) {
        $links[ $t ] = get_option( "seo_dash_gsheet_link_{$report_id}_{$t}", null );
    }

    seo_dash_json_success( [ 'links' => $links ] );
} );

// ── 5. Sync: fetch sheet rows → upsert into DB ───────────────────────────────
add_action( 'wp_ajax_seo_dash_gsheet_sync', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( wp_unslash( $_POST['report_id'] ?? 0 ) );
    $tab_type  = sanitize_key( wp_unslash( $_POST['tab_type'] ?? '' ) ); // ga | service | blog

    if ( ! $report_id || ! $tab_type ) seo_dash_json_error( 'Missing parameters.' );

    // Accept direct params (from integration-linked sheet) OR fall back to saved per-tab link.
    $intg_id        = sanitize_key( wp_unslash( $_POST['intg_id'] ?? '' ) );
    $spreadsheet_id = sanitize_text_field( wp_unslash( $_POST['spreadsheet_id'] ?? '' ) );
    $tab_name       = sanitize_text_field( wp_unslash( $_POST['tab_name'] ?? '' ) );

    if ( ! $intg_id || ! $spreadsheet_id || ! $tab_name ) {
        // Fall back to saved per-tab link.
        $link = get_option( "seo_dash_gsheet_link_{$report_id}_{$tab_type}", null );
        if ( ! $link || empty( $link['spreadsheet_id'] ) ) {
            seo_dash_json_error( 'No Google Sheet linked. Link a sheet in the Global Integrations tab.' );
        }
        $intg_id        = $link['intg_id'];
        $spreadsheet_id = $link['spreadsheet_id'];
        $tab_name       = $link['tab_name'] ?: 'Sheet1';
    }

    // Auth.
    $json_raw = seo_dash_gsheet_get_json( $intg_id );
    if ( ! $json_raw ) seo_dash_json_error( 'No service account key found for linked integration.' );

    $token = seo_dash_gsheet_get_token( $json_raw );
    if ( ! $token ) seo_dash_json_error( 'Google authentication failed.' );

    // Internal function to perform the fetch.
    $do_fetch = function($t_name) use ($spreadsheet_id, $token) {
        $range = rawurlencode( $t_name . '!A:Z' );
        $url   = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}";
        return wp_remote_get( $url, [
            'timeout' => 20,
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
        ] );
    };

    $resp = $do_fetch($tab_name);

    if ( is_wp_error( $resp ) ) seo_dash_json_error( 'Sheets API error: ' . $resp->get_error_message() );

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    
    // Auto-heal logic: if Tab was renamed in Google Sheets (400 Unable to parse range)
    if ( isset( $body['error'] ) && $body['error']['code'] == 400 && strpos( $body['error']['message'], 'Unable to parse range' ) !== false ) {
        if ( isset($link) && !empty($link['tab_id']) ) {
            // Fetch tab list to find the new name using the immutable tab_id
            $meta_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}?fields=sheets.properties(title,sheetId)";
            $meta_resp = wp_remote_get( $meta_url, [
                'timeout' => 15,
                'headers' => [ 'Authorization' => 'Bearer ' . $token ],
            ] );
            if ( ! is_wp_error( $meta_resp ) ) {
                $meta_body = json_decode( wp_remote_retrieve_body( $meta_resp ), true );
                foreach ( $meta_body['sheets'] ?? [] as $s ) {
                    if ( (string)$s['properties']['sheetId'] === (string)$link['tab_id'] ) {
                        // Found the new name!
                        $tab_name = $s['properties']['title'];
                        // Update the database so we don't have to heal again
                        $link['tab_name'] = $tab_name;
                        update_option( "seo_dash_gsheet_link_{$report_id}_{$tab_type}", $link );
                        // Retry fetch with new name
                        $resp = $do_fetch($tab_name);
                        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
                        break;
                    }
                }
            }
        }
    }

    if ( isset( $body['error'] ) ) {
        seo_dash_json_error( $body['error']['message'] ?? 'Sheets API error. Make sure the sheet is shared with the service account email.' );
    }

    $values = $body['values'] ?? [];
    if ( count( $values ) < 2 ) seo_dash_json_error( 'Sheet is empty or has no data rows.' );

    // Parse headers from first row.
    $headers = array_map( function($h) { return strtolower(trim($h)); }, $values[0] );
    $parsed_rows = [];
    for ( $i = 1; $i < count( $values ); $i++ ) {
        $row_vals = $values[ $i ];
        $row = [];
        foreach ( $headers as $idx => $h ) {
            $row[ $h ] = isset( $row_vals[ $idx ] ) ? trim( $row_vals[ $idx ] ) : '';
        }
        $parsed_rows[] = $row;
    }

    if ( empty( $parsed_rows ) ) seo_dash_json_error( 'No data rows found in sheet.' );

    // ── Format validation: check headers match the expected tab format ─────
    $expected_headers = [];
    if ( $tab_type === 'service' || $tab_type === 'blog' ) {
        // Only require a URL-like column — all other columns are optional
        $url_aliases = ['url', 'page url', 'page_url', 'visit', 'link', 'address', 'service url', 'service_url', 'post url', 'post_url'];
        $has_url_col = false;
        foreach ( $url_aliases as $alias ) {
            if ( in_array( strtolower($alias), array_map('strtolower', array_map('trim', $headers)), true ) ) {
                $has_url_col = true;
                break;
            }
        }
        if ( ! $has_url_col ) {
            seo_dash_json_error(
                'Sheet must have a URL column. Accepted column names: url, page url, service url, post url, link, address. ' .
                'All other columns (title, keyword, ranked_page, ai_overview, month, publish_date) are optional.'
            );
        }
        // Skip the generic expected_headers check below
        $expected_headers = [];
    } elseif ( $tab_type === 'ga' ) {
        $expected_headers = ['page_url', 'period_type', 'month_key', 'sessions', 'users'];
    } elseif ( $tab_type === 'gmb' ) {
        $expected_headers = ['month', 'calls', 'bookings', 'directions', 'website clicks'];
    } elseif ( $tab_type === 'gmb_posts' ) {
        // Just require a URL column; others can be inferred or fall back
        $expected_headers = [];
    } elseif ( $tab_type === 'technical' ) {
        $expected_headers = ['audit item', 'status', 'notes'];
    } elseif ( $tab_type === 'backlinks' ) {
        // No columns are strictly required — any subset of the recognized
        // columns (type, website url, da, pa, spam%, live link, keyword,
        // target url, date, status) will be imported; missing ones are
        // simply left blank/default during the upsert below.
        $expected_headers = [];
    } elseif ( $tab_type === 'leads' ) {
        // Only email is required; all other columns are optional.
        $expected_headers = ['email'];
    } elseif ( $tab_type === 'click_tracking' ) {
        $expected_headers = [];
    } elseif ( $tab_type === 'documents' ) {
        $expected_headers = ['title', 'client email', 'file type', 'file url', 'status', 'admin notes'];
    }
    if ( ! empty( $expected_headers ) ) {
        $headers_lower = array_map( 'strtolower', array_map( 'trim', $headers ) );
        $missing = [];
        foreach ( $expected_headers as $req ) {
            if ( ! in_array( strtolower($req), $headers_lower, true ) ) {
                $missing[] = $req;
            }
        }
        if ( ! empty( $missing ) ) {
            if ( $tab_type === 'leads' ) {
                seo_dash_json_error(
                    'Sheet format mismatch. Missing required column: ' . implode(', ', $missing) .
                    '. Leads sheet requires at minimum an "email" column. Optional columns: name, phone, message, zip, date & time, status, notes.' .
                    ' Download the format template for the correct layout.'
                );
            } else {
                seo_dash_json_error(
                    'Sheet format mismatch. Missing required columns: ' . implode(', ', $missing) .
                    '. Expected columns for ' . $tab_type . ': ' . implode(', ', $expected_headers) .
                    '. Download the format template for the correct layout.'
                );
            }
        }
    }

    // ── Upsert logic (mirrors existing CSV import handler) ─────────────────
    $upserted = 0;

    if ( $tab_type === 'ga' ) {
        // Database (GA4) tab — upsert by page_url + month_key + period_type.
        $month_key = sanitize_text_field( wp_unslash( $_POST['month_key'] ?? '' ) );
        if ( ! $month_key ) {
            // Use current year-month if not provided.
            $month_key = date( 'Y-m' );
        }

        global $wpdb;
        $table = SEO_Dash_Database::$data_ga;

        foreach ( $parsed_rows as $r ) {
            $page_url    = sanitize_text_field( $r['page_url'] ?? $r['url'] ?? '' );
            $period_type = sanitize_text_field( $r['period_type'] ?? 'monthly' );
            if ( ! $page_url ) continue;

            $mk = sanitize_text_field( $r['month_key'] ?? $month_key );

            // Check existing.
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE report_id=%d AND page_url=%s AND month_key=%s AND period_type=%s AND trashed=0",
                $report_id, $page_url, $mk, $period_type
            ) );

            $data = [
                'report_id'    => $report_id,
                'period_type'  => $period_type,
                'month_key'    => $mk,
                'date_from'    => sanitize_text_field( $r['date_from'] ?? '' ),
                'date_to'      => sanitize_text_field( $r['date_to'] ?? '' ),
                'page_url'     => $page_url,
                'page_title'   => sanitize_text_field( $r['page_title'] ?? $r['title'] ?? '' ),
                'sessions'     => absint( $r['sessions'] ?? 0 ),
                'users'        => absint( $r['users'] ?? 0 ),
                'views'        => absint( $r['views'] ?? 0 ),
                'bounce_rate'  => floatval( $r['bounce_rate'] ?? 0 ),
                'avg_duration' => absint( $r['avg_duration'] ?? 0 ),
            ];

            if ( $existing_id ) {
                $wpdb->update( $table, $data, [ 'id' => $existing_id ] );
            } else {
                $wpdb->insert( $table, $data );
            }
            $upserted++;
        }

        // Auto-sync to Blog Posts / Service Pages
        $sync_data = [];
        foreach ( $parsed_rows as $r ) {
            $page_url = sanitize_text_field( $r['page_url'] ?? $r['url'] ?? '' );
            if ( $page_url ) {
                $sync_data[ $page_url ] = sanitize_text_field( $r['page_title'] ?? $r['title'] ?? '' );
            }
        }
        if ( ! empty( $sync_data ) && function_exists( 'seo_dash_auto_sync_fetched_urls' ) ) {
            seo_dash_auto_sync_fetched_urls( $report_id, $sync_data );
        }

    } elseif ( $tab_type === 'service' || $tab_type === 'blog' ) {
        // Service Pages / Blog Posts — upsert by URL (keyed array in wp_options).
        $custom_map = get_option( "seo_dash_custom_pages_{$report_id}_{$tab_type}", [] );
        if ( ! is_array( $custom_map ) ) $custom_map = [];

        $ga_map = get_option( "seo_dash_sitemap_types_{$report_id}_ga", [] );
        if ( ! is_array( $ga_map ) ) $ga_map = [];
        $sc_map = get_option( "seo_dash_sitemap_types_{$report_id}_sc", [] );
        if ( ! is_array( $sc_map ) ) $sc_map = [];

        // Use the active sub-tab type sent from the frontend (e.g. 'page', 'location', 'city', 'post')
        // Falls back to 'page' for service tab, 'post' for blog tab
        $active_sub_type = sanitize_key( wp_unslash( $_POST['active_sub_type'] ?? '' ) );
        $valid_service_types = [ 'page', 'service', 'location', 'city', 'product', 'portfolio' ];
        $valid_blog_types    = [ 'post', 'blog', 'category' ];
        if ( $tab_type === 'service' ) {
            $sub_type = in_array( $active_sub_type, $valid_service_types, true ) ? $active_sub_type : 'page';
        } else {
            $sub_type = in_array( $active_sub_type, $valid_blog_types, true ) ? $active_sub_type : 'post';
        }

        // Helper: normalise URL so https://site.com/page/ and https://site.com/page are the same key
        $normalise_url = function( string $u ): string {
            $u = trim( $u );
            // Lowercase scheme+host
            if ( preg_match( '/^(https?:\/\/[^\/]+)(.*)/i', $u, $m ) ) {
                $u = strtolower( $m[1] ) . $m[2];
            }
            // Strip trailing slash (except root "/")
            if ( strlen( $u ) > 1 ) $u = rtrim( $u, '/' );
            return $u;
        };

        // Build a lookup of normalized title -> URL key for existing entries,
        // so an incoming row can be matched to an existing row by name/title
        // even if its URL has changed.
        $title_index = [];
        foreach ( $custom_map as $existing_u => $existing_cdata ) {
            if ( ! empty( $existing_cdata['trashed'] ) ) continue;
            $t = seo_dash_norm_val( $existing_cdata['title'] ?? '' );
            if ( $t !== '' ) $title_index[ $t ] = $existing_u;
        }
        $skipped = 0;
        $compare_cols = ['url','title','keyword','ranked_page','ai_overview','month','publish_date'];

        foreach ( $parsed_rows as $r ) {
            // Accept common URL column aliases
            $raw_u = sanitize_text_field(
                $r['url'] ?? $r['page url'] ?? $r['page_url'] ??
                $r['visit'] ?? $r['link'] ?? $r['address'] ??
                $r['service url'] ?? $r['service_url'] ??
                $r['post url'] ?? $r['post_url'] ?? ''
            );
            $u = $normalise_url( $raw_u );
            if ( ! $u ) continue;

            // Accept common title/name column aliases
            $title_val = $r['title'] ?? $r['page_title'] ?? $r['service name'] ?? $r['service_name'] ??
                         $r['page name'] ?? $r['page_name'] ?? $r['name'] ?? $r['article title'] ?? $r['post title'] ?? '';

            // Accept common keyword aliases
            $kw_val = $r['keyword'] ?? $r['keywords'] ?? $r['target keyword'] ?? $r['target_keyword'] ?? $r['kw'] ?? '';

            // Accept ranked_page aliases
            $rp_val = $r['ranked_page'] ?? $r['rank'] ?? $r['ranking'] ?? $r['position'] ?? $r['page rank'] ?? '';

            // Accept month aliases
            $month_val = $r['month'] ?? $r['date'] ?? $r['period'] ?? $r['month key'] ?? '';

            // Accept publish_date aliases
            $pd_val = $r['publish_date'] ?? $r['published'] ?? $r['publish date'] ?? $r['date published'] ?? $r['date'] ?? '';

            // Identity match: same URL, or (if URL is new) same title as an
            // existing row — treat this as an update to that row (possibly
            // with a changed URL) rather than a new entry.
            $title_norm = seo_dash_norm_val( $title_val );
            $match_url  = null;
            if ( isset( $custom_map[ $u ] ) ) {
                $match_url = $u;
            } elseif ( $title_norm !== '' && isset( $title_index[ $title_norm ] ) ) {
                $match_url = $title_index[ $title_norm ];
            }
            $existing = ( $match_url !== null ) ? ( $custom_map[ $match_url ] ?? [] ) : [];

            $new_row = [
                'url'              => $u,
                'title'            => sanitize_text_field( $title_val ?: ( $existing['title'] ?? '' ) ),
                'keyword'          => sanitize_text_field( $kw_val ?: ( $existing['keyword'] ?? '' ) ),
                'ranked_page'      => sanitize_text_field( $rp_val ?: ( $existing['ranked_page'] ?? '' ) ),
                'ai_overview'      => intval( $r['ai_overview'] ?? $r['ai overview'] ?? ( $existing['ai_overview'] ?? 0 ) ),
                'month'            => sanitize_text_field( $month_val ?: ( $existing['month'] ?? '' ) ),
                'publish_date'     => sanitize_text_field( $pd_val ?: ( $existing['publish_date'] ?? '' ) ),
                'show_on_overview' => intval( $existing['show_on_overview'] ?? 0 ),
            ];

            // Exact duplicate (same URL and every other column unchanged) — ignore.
            if ( $match_url === $u && ! empty( $existing ) && seo_dash_rows_equal( $existing, $new_row, $compare_cols ) ) {
                $skipped++;
                continue;
            }

            // Row identifies an existing entry under a DIFFERENT URL (same
            // title) — move it to the new URL instead of creating a duplicate.
            if ( $match_url !== null && $match_url !== $u ) {
                unset( $custom_map[ $match_url ] );
                if ( isset( $ga_map[ $match_url ] ) && ! isset( $ga_map[ $u ] ) ) { $ga_map[ $u ] = $ga_map[ $match_url ]; }
                if ( isset( $sc_map[ $match_url ] ) && ! isset( $sc_map[ $u ] ) ) { $sc_map[ $u ] = $sc_map[ $match_url ]; }
                unset( $ga_map[ $match_url ], $sc_map[ $match_url ] );
            }

            $custom_map[ $u ] = $new_row;
            if ( $title_norm !== '' ) $title_index[ $title_norm ] = $u;

            // Tag under the active sub-tab; only update if not already correctly tagged
            if ( ! isset( $ga_map[ $u ] ) || $ga_map[ $u ] === 'other' ) $ga_map[ $u ] = $sub_type;
            if ( ! isset( $sc_map[ $u ] ) || $sc_map[ $u ] === 'other' ) $sc_map[ $u ] = $sub_type;

            $upserted++;
        }

        update_option( "seo_dash_custom_pages_{$report_id}_{$tab_type}", $custom_map );
        update_option( "seo_dash_sitemap_types_{$report_id}_ga", $ga_map );
        update_option( "seo_dash_sitemap_types_{$report_id}_sc", $sc_map );

        // ── Upsert 30d / overall traffic into data_ga from sheet columns ──
        global $wpdb;
        $ga_table   = SEO_Dash_Database::$data_ga;
        $month_now  = date( 'Y-m' );
        $traffic_upserted = 0;
        foreach ( $parsed_rows as $r ) {
            $raw_page_url = sanitize_text_field(
                $r['url'] ?? $r['page url'] ?? $r['page_url'] ??
                $r['visit'] ?? $r['link'] ?? $r['address'] ??
                $r['service url'] ?? $r['service_url'] ??
                $r['post url'] ?? $r['post_url'] ?? ''
            );
            $page_url = isset($normalise_url) ? $normalise_url($raw_page_url) : trim($raw_page_url);
            if ( ! $page_url ) continue;

            // Accept "30 days", "30days", "visit", "visits", "monthly", "30d"
            $val_30d = (int) str_replace( [',', ' '], '', 
                $r['30 days'] ?? $r['30days'] ?? $r['30d'] ?? $r['visit'] ?? $r['visits'] ?? $r['monthly'] ?? $r['monthly traffic'] ?? ''
            );
            // Accept "overall", "total", "all time", "total traffic"
            $val_ov  = (int) str_replace( [',', ' '], '',
                $r['overall'] ?? $r['total'] ?? $r['all time'] ?? $r['total traffic'] ?? $r['all_time'] ?? $r['overall traffic'] ?? ''
            );

            foreach ( [ '30d' => $val_30d, 'overall' => $val_ov ] as $ptype => $val ) {
                if ( $val <= 0 ) continue;
                $existing_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$ga_table} WHERE report_id=%d AND page_url=%s AND period_type=%s AND trashed=0 LIMIT 1",
                    $report_id, $page_url, $ptype
                ) );
                $row_data = [
                    'report_id'   => $report_id,
                    'period_type' => $ptype,
                    'month_key'   => $month_now,
                    'page_url'    => $page_url,
                    'users'       => $val,
                    'sessions'    => $val,
                    'pageviews'   => 0,
                ];
                if ( $existing_id ) {
                    $wpdb->update( $ga_table, [ 'users' => $val, 'sessions' => $val ], [ 'id' => $existing_id ] );
                } else {
                    $wpdb->insert( $ga_table, $row_data );
                }
                $traffic_upserted++;
            }
        }

        $skip_note_sp = $skipped > 0 ? " ({$skipped} duplicate(s) skipped)" : '';
        SEO_Dash_Database::log_activity(
            'gsheet_import_pages', 'success',
            "GSheet sync ({$tab_type}): {$upserted} rows upserted, {$skipped} duplicate(s) skipped. Sub-type: {$sub_type}. Traffic rows: {$traffic_upserted}.",
            'report', $report_id
        );
        seo_dash_json_success( [ 'upserted' => $upserted, 'skipped' => $skipped, 'traffic_rows' => $traffic_upserted, 'type' => $tab_type, 'sub_type' => $sub_type ], "✅ Synced {$upserted} rows into " . ($tab_type === 'blog' ? 'Blog Posts' : 'Service Pages') . "{$skip_note_sp}" . ( $traffic_upserted ? " + {$traffic_upserted} traffic row(s) updated." : "." ) );
    } elseif ( $tab_type === 'gmb' ) {
        global $wpdb;
        $table = SEO_Dash_Database::$data_gmb;
        $skipped = 0;
        $gmb_compare_cols = ['month_key','clicks_website','clicks_directions','calls','bookings'];

        foreach ( $parsed_rows as $r ) {
            $month_key = sanitize_text_field( $r['month'] ?? '' );
            if ( ! $month_key ) continue;

            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE report_id=%d AND month_key=%s AND trashed=0 LIMIT 1",
                $report_id, $month_key
            ), ARRAY_A );

            $data = [
                'report_id'         => $report_id,
                'month_key'         => $month_key,
                'calls'             => absint( $r['calls'] ?? 0 ),
                'bookings'          => absint( $r['bookings'] ?? 0 ),
                'clicks_directions' => absint( $r['directions'] ?? 0 ),
                'clicks_website'    => absint( $r['website clicks'] ?? 0 ),
            ];

            if ( $existing ) {
                // Same month, identical data — nothing to do.
                if ( seo_dash_rows_equal( $existing, $data, $gmb_compare_cols ) ) {
                    $skipped++;
                    continue;
                }
                $wpdb->update( $table, $data, [ 'id' => $existing['id'] ] );
            } else {
                $wpdb->insert( $table, $data );
            }
            $upserted++;
        }
    } elseif ( $tab_type === 'gmb_posts' ) {
        // Log what headers we actually found for debugging
        SEO_Dash_Database::log_activity(
            'gsheet_gmb_posts_headers', 'info',
            'GMB Posts sheet headers found: ' . implode(', ', $headers) . ' | Row count: ' . count($parsed_rows),
            'report', $report_id
        );

        global $wpdb;
        $gp_table = SEO_Dash_Database::$data_gmb_posts;
        $skipped = 0;
        $gp_compare_cols = ['month_key','title','post_url','status'];

        $existing_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$gp_table} WHERE report_id=%d AND trashed=0", $report_id
        ), ARRAY_A ) ?: [];
        $by_title = []; $by_url = [];
        foreach ( $existing_rows as $er ) {
            $t = seo_dash_norm_val( $er['title'] ?? '' );
            $u = seo_dash_norm_val( $er['post_url'] ?? '' );
            if ( $t !== '' && ! isset( $by_title[ $t ] ) ) $by_title[ $t ] = $er;
            if ( $u !== '' && ! isset( $by_url[ $u ] ) )   $by_url[ $u ]   = $er;
        }

        foreach ( $parsed_rows as $r ) {
            // Robust URL detection
            $raw_url = '';
            foreach (['post url', 'post_url', 'url', 'link', 'post link', 'google post url', 'gmb url', 'post_link'] as $col) {
                if (!empty($r[$col])) { $raw_url = trim($r[$col]); break; }
            }
            if ( ! $raw_url ) continue;

            // Robust title detection
            $title = '';
            foreach (['post title', 'post_title', 'title', 'name', 'post name', 'subject'] as $col) {
                if (!empty($r[$col])) { $title = sanitize_text_field($r[$col]); break; }
            }

            $month = sanitize_text_field( $r['month'] ?? $r['date'] ?? $r['month key'] ?? '' );

            $row = [
                'report_id' => $report_id,
                'month_key' => $month,
                'title'     => $title,
                'post_url'  => esc_url_raw( $raw_url ),
                'status'    => sanitize_text_field( $r['status'] ?? $r['state'] ?? 'Published' ),
                'trashed'   => 0,
            ];

            // Identity: same title (preferred), else same post_url.
            $t_norm = seo_dash_norm_val( $title );
            $u_norm = seo_dash_norm_val( $raw_url );
            $match  = ( $t_norm !== '' && isset( $by_title[ $t_norm ] ) ) ? $by_title[ $t_norm ]
                    : ( ( $u_norm !== '' && isset( $by_url[ $u_norm ] ) ) ? $by_url[ $u_norm ] : null );

            if ( $match ) {
                if ( seo_dash_rows_equal( $match, $row, $gp_compare_cols ) ) {
                    $skipped++;
                    continue;
                }
                $wpdb->update( $gp_table, $row, [ 'id' => $match['id'] ] );
                if ( $t_norm !== '' ) $by_title[ $t_norm ] = $row + [ 'id' => $match['id'] ];
                if ( $u_norm !== '' ) $by_url[ $u_norm ]   = $row + [ 'id' => $match['id'] ];
                $upserted++;
                continue;
            }

            $wpdb->insert( $gp_table, $row );
            if ( $t_norm !== '' ) $by_title[ $t_norm ] = $row + [ 'id' => $wpdb->insert_id ];
            if ( $u_norm !== '' ) $by_url[ $u_norm ]   = $row + [ 'id' => $wpdb->insert_id ];
            $upserted++;
        }

        if ( $upserted === 0 && $skipped === 0 ) {
            seo_dash_json_error( 'No valid rows found in sheet. Make sure the sheet has a "post url" column with valid URLs.' );
        }
    } elseif ( $tab_type === 'technical' ) {
        global $wpdb;
        $table = SEO_Dash_Database::$data_technical;
        $skipped = 0;
        $tech_compare_cols = ['month_key','issue_type','severity','url','description','status'];

        // Existing active rows, indexed by normalized issue_type so an
        // incoming row can be matched to an existing audit item even if its
        // status/description/month changed. The sheet has no severity/url
        // columns, so those are preserved from the matched existing row.
        $existing_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0", $report_id
        ), ARRAY_A ) ?: [];
        $by_issue = [];
        foreach ( $existing_rows as $er ) {
            $i = seo_dash_norm_val( $er['issue_type'] ?? '' );
            if ( $i !== '' && ! isset( $by_issue[ $i ] ) ) $by_issue[ $i ] = $er;
        }

        foreach ( $parsed_rows as $r ) {
            $issue = sanitize_text_field( $r['audit item'] ?? $r['audit_item'] ?? '' );
            if ( ! $issue ) continue;

            $status = strtolower(sanitize_text_field( $r['status'] ?? 'warning' ));
            if (!in_array($status, ['pass','fail','warning','n/a'])) $status = 'warning';

            $i_norm = seo_dash_norm_val( $issue );
            $match  = ( $i_norm !== '' && isset( $by_issue[ $i_norm ] ) ) ? $by_issue[ $i_norm ] : null;

            $data = [
                'report_id'   => $report_id,
                'issue_type'  => $issue,
                'status'      => $status,
                'description' => sanitize_textarea_field( $r['notes'] ?? '' ),
                'month_key'   => date('Y-m'),
                // Not present in this sheet format — keep whatever the
                // matched existing row already has so an update doesn't wipe it.
                'severity'    => $match['severity'] ?? 'medium',
                'url'         => $match['url'] ?? '',
            ];

            if ( $match ) {
                if ( seo_dash_rows_equal( $match, $data, $tech_compare_cols ) ) {
                    $skipped++;
                    continue;
                }
                $wpdb->update( $table, $data, [ 'id' => $match['id'] ] );
                if ( $i_norm !== '' ) $by_issue[ $i_norm ] = $data + [ 'id' => $match['id'] ];
                $upserted++;
                continue;
            }

            $wpdb->insert( $table, $data );
            if ( $i_norm !== '' ) $by_issue[ $i_norm ] = $data + [ 'id' => $wpdb->insert_id ];
            $upserted++;
        }
    } elseif ( $tab_type === 'backlinks' ) {
        global $wpdb;
        $table = SEO_Dash_Database::$data_backlinks;
        $skipped = 0;
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

        // Columns that together define an "identical row" — same rule as the
        // CSV importer. If even one of these differs from every existing row,
        // the row is unique and gets inserted; only an exact match across all
        // of them is skipped as a duplicate. This makes re-syncing the same
        // Google Sheet idempotent instead of re-inserting every row each time.
        $dupe_cols = [
            'month_key', 'link_type', 'source_url', 'domain_rating',
            'page_authority', 'spam_score', 'live_link', 'anchor_text',
            'target_url', 'found_date', 'status',
        ];
        $fingerprint = function( array $row ) use ( $dupe_cols ) {
            $parts = [];
            foreach ( $dupe_cols as $c ) {
                $v = $row[ $c ] ?? '';
                $parts[] = is_string( $v ) ? strtolower( trim( $v ) ) : (string) $v;
            }
            return implode( '|', $parts );
        };
        $existing_fp = [];
        $existing = $wpdb->get_results( $wpdb->prepare(
            "SELECT " . implode( ',', $dupe_cols ) . " FROM {$table} WHERE report_id = %d AND trashed = 0",
            $report_id
        ), ARRAY_A );
        foreach ( (array) $existing as $erow ) {
            $existing_fp[ $fingerprint( $erow ) ] = true;
        }

        foreach ( $parsed_rows as $r ) {
            $src = esc_url_raw($r['source_url'] ?? $r['website url'] ?? $r['website_url'] ?? '');
            // Do NOT skip rows with an empty website url — a row is still valid
            // if it has a live link, keyword, target url, or any other column filled.
            // Only skip if every meaningful column is blank (truly empty row).
            $live_link_raw  = trim($r['live link'] ?? $r['live_link'] ?? '');
            $target_url_raw = trim($r['target url'] ?? $r['target_url'] ?? '');
            $anchor_raw     = trim($r['keyword'] ?? $r['anchor_text'] ?? '');
            if ( !$src && !$live_link_raw && !$target_url_raw && !$anchor_raw ) continue;

            $lt = strtolower(trim($r['link_type'] ?? $r['type'] ?? ''));
            if (!$lt) {
                foreach ($type_keywords as $kw => $mapped) {
                    if (strpos($src, $kw) !== false) { $lt = $mapped; break; }
                }
            }
            $valid_types = ['dofollow','nofollow','sponsored','ugc','guest_post','directory','social','forum','citation','press_release','infographic','broken_link','other'];
            if (!in_array($lt, $valid_types)) $lt = 'dofollow';

            $raw_date   = $r['date'] ?? $r['found_date'] ?? $r['found date'] ?? '';
            $found_date = seo_dash_parse_date_to_mysql($raw_date);
            if ($found_date === '') $found_date = current_time('Y-m-d');

            $month_key = sanitize_text_field($r['month_key'] ?? '');
            if (!seo_dash_sanitize_month($month_key)) {
                $month_key = substr($found_date, 0, 7);
            }

            $data = [
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

            $fp = $fingerprint( $data );
            if ( isset( $existing_fp[ $fp ] ) ) {
                $skipped++;
                continue;
            }
            $existing_fp[ $fp ] = true;

            $wpdb->insert( $table, $data );
            $upserted++;
        }
    } elseif ( $tab_type === 'leads' ) {
        global $wpdb;
        $table = SEO_Dash_Database::$data_leads;
        $skipped = 0;
        $leads_compare_cols = ['name','email','phone','zip','source','message','status','notes','lead_date','lead_time','page_url','month_key'];

        // Existing active leads for this report, indexed by email, phone, and name
        $existing_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0", $report_id
        ), ARRAY_A ) ?: [];
        $by_email = []; $by_phone = []; $by_name = [];
        foreach ( $existing_rows as $er ) {
            $e = seo_dash_norm_val( $er['email'] ?? '' );
            $p = preg_replace('/[^0-9]/', '', $er['phone'] ?? '');
            $n = seo_dash_norm_val( $er['name'] ?? '' );
            if ( $e !== '' && ! isset( $by_email[ $e ] ) ) $by_email[ $e ] = $er;
            if ( strlen($p) >= 7 && ! isset( $by_phone[ $p ] ) ) $by_phone[ $p ] = $er;
            if ( $n !== '' && ! isset( $by_name[ $n ] ) )  $by_name[ $n ]  = $er;
        }

        foreach ( $parsed_rows as $r ) {
            $email = sanitize_text_field( $r['email'] ?? '' );
            $name  = sanitize_text_field( $r['name']  ?? '' );
            $phone = sanitize_text_field( $r['phone'] ?? '' );
            $phone_digits = preg_replace('/[^0-9]/', '', $phone);

            $data = [
                'report_id'  => $report_id,
                'month_key'  => sanitize_text_field($r['month_key'] ?? $r['month'] ?? date('Y-m')),
                'name'       => $name,
                'email'      => $email,
                'phone'      => $phone,
                'zip'        => sanitize_text_field($r['zip'] ?? ''),
                'source'     => sanitize_text_field($r['form'] ?? $r['source'] ?? ''),
                'message'    => sanitize_textarea_field($r['message'] ?? ''),
                'status'     => seo_dash_ensure_custom_lead_status($report_id, sanitize_text_field($r['status'] ?? 'new')),
                'notes'      => sanitize_text_field($r['notes'] ?? ''),
                'lead_date'  => (function() use ($r) {
                    $dt = $r['date & time'] ?? $r['date_time'] ?? $r['datetime'] ?? '';
                    if ($dt) { $parts = explode(' ', trim($dt), 2); return sanitize_text_field($parts[0]); }
                    return sanitize_text_field($r['date'] ?? $r['date_received'] ?? current_time('Y-m-d'));
                })(),
                'lead_time'  => (function() use ($r) {
                    $dt = $r['date & time'] ?? $r['date_time'] ?? $r['datetime'] ?? '';
                    if ($dt) { $parts = explode(' ', trim($dt), 2); return sanitize_text_field($parts[1] ?? ''); }
                    return sanitize_text_field($r['time'] ?? '');
                })(),
                'page_url'   => esc_url_raw($r['page url'] ?? $r['page_url'] ?? ''),
            ];

            // Identity lookup: match by email, else phone, else name
            $e_norm = seo_dash_norm_val( $email );
            $n_norm = seo_dash_norm_val( $name );
            $match  = null;
            if ( $e_norm !== '' && isset( $by_email[ $e_norm ] ) ) {
                $match = $by_email[ $e_norm ];
            } elseif ( strlen($phone_digits) >= 7 && isset( $by_phone[ $phone_digits ] ) ) {
                $match = $by_phone[ $phone_digits ];
            } elseif ( $n_norm !== '' && isset( $by_name[ $n_norm ] ) ) {
                $match = $by_name[ $n_norm ];
            }

            // Option A: If email/phone/name matches, but submission date OR message is different,
            // treat as a NEW distinct submission (new lead row) for that person instead of overwriting history.
            if ( $match && (
                    seo_dash_norm_val( $match['lead_date'] ?? '' ) !== seo_dash_norm_val( $data['lead_date'] ?? '' )
                 || seo_dash_norm_val( $match['message']   ?? '' ) !== seo_dash_norm_val( $data['message']   ?? '' )
            ) ) {
                $match = null;
            }

            if ( $match ) {
                // If ALL columns match exactly, mark as duplicate & skip
                if ( seo_dash_rows_equal( $match, $data, $leads_compare_cols ) ) {
                    $skipped++;
                    continue;
                }
                // If 1 or more columns (status, notes, etc.) changed for the same submission, UPDATE existing row
                $wpdb->update( $table, $data, [ 'id' => $match['id'] ] );
                if ( $e_norm !== '' ) $by_email[ $e_norm ] = $data + [ 'id' => $match['id'] ];
                if ( strlen($phone_digits) >= 7 ) $by_phone[ $phone_digits ] = $data + [ 'id' => $match['id'] ];
                if ( $n_norm !== '' ) $by_name[ $n_norm ]  = $data + [ 'id' => $match['id'] ];
                $upserted++;
                continue;
            }

            // Completely new lead -> Insert new row
            $wpdb->insert( $table, $data );
            $new_id = $wpdb->insert_id;
            if ( $e_norm !== '' ) $by_email[ $e_norm ] = $data + [ 'id' => $new_id ];
            if ( strlen($phone_digits) >= 7 ) $by_phone[ $phone_digits ] = $data + [ 'id' => $new_id ];
            if ( $n_norm !== '' ) $by_name[ $n_norm ]  = $data + [ 'id' => $new_id ];
            $upserted++;
        }
    } elseif ( $tab_type === 'click_tracking' ) {
        global $wpdb;
        $table = SEO_Dash_Database::$data_click_tracking;
        $skipped = 0;
        $existing_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0", $report_id
        ), ARRAY_A ) ?: [];

        foreach ( $parsed_rows as $r ) {
            $kw       = '';
            $src      = '';
            $type_val = 'button_click';
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

            $data = [
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
                    seo_dash_norm_val( $er['keyword_text'] ?? '' ) === seo_dash_norm_val( $data['keyword_text'] ?? '' ) &&
                    seo_dash_norm_val( $er['source_page'] ?? '' )  === seo_dash_norm_val( $data['source_page'] ?? '' ) &&
                    seo_dash_norm_val( $er['click_type'] ?? '' )   === seo_dash_norm_val( $data['click_type'] ?? '' ) &&
                    seo_dash_norm_val( $er['click_date'] ?? '' )   === seo_dash_norm_val( $data['click_date'] ?? '' ) &&
                    seo_dash_norm_val( $er['click_time'] ?? '' )   === seo_dash_norm_val( $data['click_time'] ?? '' )
                ) {
                    $is_dupe = true;
                    break;
                }
            }

            if ($is_dupe) {
                $skipped++;
                continue;
            }

            // Insert as new lead/click record
            $wpdb->insert($table, $data);
            $data['id'] = $wpdb->insert_id;
            $existing_rows[] = $data;
            $upserted++;
        }
    } elseif ( $tab_type === 'documents' ) {
        global $wpdb;
        $table = SEO_Dash_Database::$data_documents;
        foreach ( $parsed_rows as $r ) {
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
            $upserted++;
        }
    } else {
        seo_dash_json_error( "Unsupported tab type: {$tab_type}" );
    }

    $skipped = isset( $skipped ) ? $skipped : 0;
    $skip_note = ( $skipped > 0 ) ? " ({$skipped} duplicate(s) skipped)" : '';

    SEO_Dash_Database::log_activity(
        'gsheet_synced', 'success',
        "Synced {$upserted} rows from Google Sheet into {$tab_type} tab." . ( $skipped > 0 ? " {$skipped} duplicate(s) skipped." : '' ),
        'report', $report_id
    );

    seo_dash_json_success( [ 'upserted' => $upserted, 'skipped' => $skipped ], "✅ Synced {$upserted} rows successfully{$skip_note}." );
} );

// ── 6. Export / push plugin data BACK into the linked Google Sheet ────────────
// Mirrors seo_dash_gsheet_sync (which pulls FROM the sheet) but in reverse:
// it overwrites the linked tab with the current data from the dashboard, so
// e.g. freshly-fetched/edited Service Pages rows show up in the client's
// Google Sheet without manual copy/paste.
if ( ! function_exists( 'seo_dash_gsheet_write_values' ) ) {
    function seo_dash_gsheet_write_values( string $token, string $spreadsheet_id, string $tab_name, array $rows ) {
        // Clear the existing tab content first so stale rows below the new
        // data set don't linger, then write the fresh header + data rows.
        $clear_range = rawurlencode( $tab_name . '!A:Z' );
        wp_remote_post( "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$clear_range}:clear", [
            'timeout' => 20,
            'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
            'body'    => '{}',
        ] );

        $update_range = rawurlencode( $tab_name . '!A1' );
        $resp = wp_remote_request(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$update_range}?valueInputOption=RAW",
            [
                'method'  => 'PUT',
                'timeout' => 30,
                'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
                'body'    => json_encode( [ 'values' => $rows ] ),
            ]
        );
        return $resp;
    }
}

add_action( 'wp_ajax_seo_dash_gsheet_export', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( wp_unslash( $_POST['report_id'] ?? 0 ) );
    $tab_type  = sanitize_key( wp_unslash( $_POST['tab_type'] ?? '' ) );
    if ( ! $report_id || ! $tab_type ) seo_dash_json_error( 'Missing parameters.' );

    $intg_id        = sanitize_key( wp_unslash( $_POST['intg_id'] ?? '' ) );
    $spreadsheet_id = sanitize_text_field( wp_unslash( $_POST['spreadsheet_id'] ?? '' ) );
    $tab_name       = sanitize_text_field( wp_unslash( $_POST['tab_name'] ?? '' ) );

    $link = null;
    if ( ! $intg_id || ! $spreadsheet_id || ! $tab_name ) {
        $link = get_option( "seo_dash_gsheet_link_{$report_id}_{$tab_type}", null );
        if ( ! $link || empty( $link['spreadsheet_id'] ) ) {
            seo_dash_json_error( 'No Google Sheet linked. Link a sheet in the Global Integrations tab.' );
        }
        $intg_id        = $link['intg_id'];
        $spreadsheet_id = $link['spreadsheet_id'];
        $tab_name       = $link['tab_name'] ?: 'Sheet1';
    }

    $json_raw = seo_dash_gsheet_get_json( $intg_id );
    if ( ! $json_raw ) seo_dash_json_error( 'No service account key found for linked integration.' );
    $token = seo_dash_gsheet_get_token( $json_raw );
    if ( ! $token ) seo_dash_json_error( 'Google authentication failed.' );

    global $wpdb;
    $rows = []; // [ [header...], [row...], ... ]

    if ( $tab_type === 'service' || $tab_type === 'blog' ) {
        $custom_map = get_option( "seo_dash_custom_pages_{$report_id}_{$tab_type}", [] );
        if ( ! is_array( $custom_map ) ) $custom_map = [];
        $rows[] = [ 'url', 'title', 'keyword', 'ranked_page', 'ai_overview', 'month', 'publish_date' ];
        foreach ( $custom_map as $url => $r ) {
            if ( ! empty( $r['trashed'] ) ) continue;
            $rows[] = [
                $url,
                $r['title'] ?? '', $r['keyword'] ?? '', $r['ranked_page'] ?? '',
                ! empty( $r['ai_overview'] ) ? '1' : '0',
                $r['month'] ?? '', $r['publish_date'] ?? '',
            ];
        }
    } elseif ( $tab_type === 'ga' ) {
        $table = SEO_Dash_Database::$data_ga;
        $rows[] = [ 'page_url', 'page_title', 'period_type', 'month_key', 'sessions', 'users', 'views', 'bounce_rate', 'avg_duration' ];
        $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0 ORDER BY month_key DESC", $report_id ), ARRAY_A ) ?: [];
        foreach ( $res as $r ) {
            $rows[] = [ $r['page_url'], $r['page_title'] ?? '', $r['period_type'], $r['month_key'], $r['sessions'], $r['users'], $r['views'] ?? 0, $r['bounce_rate'] ?? 0, $r['avg_duration'] ?? 0 ];
        }
    } elseif ( $tab_type === 'gmb' ) {
        $table = SEO_Dash_Database::$data_gmb;
        $rows[] = [ 'month', 'calls', 'bookings', 'directions', 'website clicks' ];
        $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0 ORDER BY month_key DESC", $report_id ), ARRAY_A ) ?: [];
        foreach ( $res as $r ) {
            $rows[] = [ $r['month_key'], $r['calls'] ?? 0, $r['bookings'] ?? 0, $r['clicks_directions'] ?? 0, $r['clicks_website'] ?? 0 ];
        }
    } elseif ( $tab_type === 'gmb_posts' ) {
        $table = SEO_Dash_Database::$data_gmb_posts;
        $rows[] = [ 'post title', 'post url', 'month', 'status' ];
        $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0", $report_id ), ARRAY_A ) ?: [];
        foreach ( $res as $r ) {
            $rows[] = [ $r['title'] ?? '', $r['post_url'] ?? '', $r['month_key'] ?? '', $r['status'] ?? '' ];
        }
    } elseif ( $tab_type === 'technical' ) {
        $table = SEO_Dash_Database::$data_technical;
        $rows[] = [ 'audit item', 'status', 'notes' ];
        $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0", $report_id ), ARRAY_A ) ?: [];
        foreach ( $res as $r ) {
            $rows[] = [ $r['issue_type'] ?? '', $r['status'] ?? '', $r['description'] ?? '' ];
        }
    } elseif ( $tab_type === 'backlinks' ) {
        $table = SEO_Dash_Database::$data_backlinks;
        $rows[] = [ 'type', 'website url', 'da', 'pa', 'spam%', 'live link', 'keyword', 'target url', 'date', 'status', 'month_key' ];
        $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0 ORDER BY found_date DESC", $report_id ), ARRAY_A ) ?: [];
        foreach ( $res as $r ) {
            $rows[] = [
                $r['link_type'] ?? '', $r['source_url'] ?? '', $r['domain_rating'] ?? 0, $r['page_authority'] ?? 0,
                $r['spam_score'] ?? 0, $r['live_link'] ?? '', $r['anchor_text'] ?? '', $r['target_url'] ?? '',
                $r['found_date'] ?? '', $r['status'] ?? '', $r['month_key'] ?? '',
            ];
        }
    } elseif ( $tab_type === 'leads' ) {
        $table = SEO_Dash_Database::$data_leads;
        $rows[] = [ 'name', 'email', 'phone', 'zip', 'form', 'message', 'status', 'notes', 'date & time', 'page url', 'month_key' ];
        $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0 ORDER BY lead_date DESC", $report_id ), ARRAY_A ) ?: [];
        foreach ( $res as $r ) {
            $dt = trim( ( $r['lead_date'] ?? '' ) . ' ' . ( $r['lead_time'] ?? '' ) );
            $rows[] = [
                $r['name'] ?? '', $r['email'] ?? '', $r['phone'] ?? '', $r['zip'] ?? '', $r['source'] ?? '',
                $r['message'] ?? '', $r['status'] ?? '', $r['notes'] ?? '', $dt, $r['page_url'] ?? '', $r['month_key'] ?? '',
            ];
        }
    } elseif ( $tab_type === 'click_tracking' ) {
        $table = SEO_Dash_Database::$data_click_tracking;
        $rows[] = [ 'Text / Keyword', 'Source Page', 'Click Type', 'Status', 'Submitteddate', 'month_key' ];
        $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d AND trashed=0 ORDER BY click_date DESC", $report_id ), ARRAY_A ) ?: [];
        foreach ( $res as $r ) {
            $rows[] = [
                $r['keyword_text'] ?? '', $r['source_page'] ?? '', $r['click_type'] ?? '', $r['status'] ?? 'new', $r['click_date'] ?? '', $r['month_key'] ?? ''
            ];
        }
    } elseif ( $tab_type === 'documents' ) {
        $table  = SEO_Dash_Database::$data_documents;
        $rows[] = [ 'title', 'client email', 'file type', 'file url', 'status', 'admin notes' ];
        $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d", $report_id ), ARRAY_A ) ?: [];
        foreach ( $res as $r ) {
            $client_email = $r['sent_to_mail'] ?? '';
            if ( ! $client_email && ! empty( $r['client_id'] ) ) {
                $client_email = $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ' . SEO_Dash_Database::$data_clients . ' WHERE id=%d', $r['client_id'] ) );
            }
            $rows[] = [ $r['title'] ?? '', $client_email ?: '', $r['file_type'] ?? '', $r['file_url'] ?? '', $r['status'] ?? '', $r['admin_notes'] ?? '' ];
        }
    } else {
        seo_dash_json_error( "Unsupported tab type: {$tab_type}" );
    }

    if ( count( $rows ) <= 1 ) seo_dash_json_error( 'No data to export for this tab yet.' );

    $resp = seo_dash_gsheet_write_values( $token, $spreadsheet_id, $tab_name, $rows );
    if ( is_wp_error( $resp ) ) seo_dash_json_error( 'Sheets API error: ' . $resp->get_error_message() );

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( isset( $body['error'] ) ) {
        seo_dash_json_error( $body['error']['message'] ?? 'Sheets API error while writing to the sheet.' );
    }

    $row_count = count( $rows ) - 1;
    SEO_Dash_Database::log_activity(
        'gsheet_exported', 'success',
        "Exported {$row_count} rows from {$tab_type} tab into Google Sheet.",
        'report', $report_id
    );

    seo_dash_json_success( [ 'exported' => $row_count ], "✅ Exported {$row_count} row(s) to Google Sheet (\"{$tab_name}\")." );
} );
