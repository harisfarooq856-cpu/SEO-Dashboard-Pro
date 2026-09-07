<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Miscellaneous utility helpers used across the plugin.
 */

/**
 * Send a JSON success response and exit.
 *
 * @param mixed  $data
 * @param string $message  Optional human-readable message.
 */
function seo_dash_json_success( $data = null, string $message = '' ): void {
    // Merge $data fields directly into the top-level response so JS reads
    // r.data.inserted, r.data.message etc. without an extra .data nesting.
    $payload = [ 'message' => $message ];
    if ( is_array( $data ) ) {
        $payload = array_merge( $payload, $data );
    } elseif ( $data !== null ) {
        $payload['data'] = $data;
    }
    wp_send_json_success( $payload );
}

/**
 * Send a JSON error response and exit.
 *
 * @param string $message
 * @param int    $code     HTTP status code (default 200 to allow JS to parse response).
 */
function seo_dash_json_error( string $message, int $code = 200 ): void {
    wp_send_json_error( [ 'message' => $message ], $code );
}

/**
 * Verify the admin AJAX nonce and capability, then die with JSON error on failure.
 * Uses wp_verify_nonce() so failures return parseable JSON (not raw -1).
 *
 * @param string $cap  WP capability required. Default 'manage_options'.
 */
function seo_dash_verify_admin_ajax( string $cap = 'manage_options' ): void {
    $nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'seo_dash_admin' ) ) {
        // Return a special flag so JS can auto-refresh the nonce and retry once.
        wp_send_json_error( [ 'message' => 'Security check failed.', 'nonce_expired' => true ], 200 );
        wp_die();
    }
    if ( ! current_user_can( $cap ) ) {
        seo_dash_json_error( 'Permission denied.', 200 );
        wp_die();
    }
}

function seo_dash_verify_frontend_ajax(): void {
    $nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'seo_dash_frontend' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.', 'nonce_expired' => true ], 200 );
        wp_die();
    }
    if ( ! is_user_logged_in() ) {
        seo_dash_json_error( 'Not logged in.', 200 );
        wp_die();
    }
}

/**
 * AJAX endpoint to issue a fresh nonce.
 * Called automatically by JS when it receives nonce_expired:true.
 * Requires the user to be logged in — safe to expose on nopriv because
 * it checks login itself and only returns a nonce for valid sessions.
 */
add_action( 'wp_ajax_seo_dash_refresh_nonce',        'seo_dash_ajax_refresh_nonce' );
add_action( 'wp_ajax_nopriv_seo_dash_refresh_nonce', 'seo_dash_ajax_refresh_nonce' );
function seo_dash_ajax_refresh_nonce(): void {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Not logged in.' ], 200 );
        wp_die();
    }
    wp_send_json_success( [
        'nonce'          => wp_create_nonce( 'seo_dash_admin' ),
        'nonce_frontend' => wp_create_nonce( 'seo_dash_frontend' ),
    ] );
}

/**
 * Get the current admin page slug from $_GET['page'].
 *
 * @return string
 */
function seo_dash_current_page(): string {
    return sanitize_key( $_GET['page'] ?? '' );
}

/**
 * Return a short date label for a month key like "2025-03" → "Mar 2025".
 *
 * @param string $month_key  YYYY-MM
 * @return string
 */
function seo_dash_month_label( string $month_key ): string {
    if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_key ) ) return $month_key;
    return date_i18n( 'M Y', strtotime( $month_key . '-01' ) );
}

/**
 * Format a large integer with commas: 1234567 → "1,234,567".
 *
 * @param int $n
 * @return string
 */
function seo_dash_num( int $n ): string {
    return number_format( $n );
}

/**
 * Format a percentage: 0.0523 → "5.2%".
 *
 * @param float $ratio
 * @param int   $decimals
 * @return string
 */
function seo_dash_pct( float $ratio, int $decimals = 1 ): string {
    return number_format( $ratio * 100, $decimals ) . '%';
}

/**
 * Clean a page title: strip protocol, trailing slash, etc.
 * Used when auto-generating titles from URLs.
 *
 * @param string $title
 * @param string $url    Optional URL to fall back to.
 * @return string
 */
function seo_dash_clean_title( string $title, string $url = '' ): string {
    if ( $title ) return $title;
    if ( ! $url ) return '';
    $path = trim( wp_parse_url( $url, PHP_URL_PATH ) ?? '', '/' );
    $slug = basename( $path );
    return ucwords( str_replace( [ '-', '_' ], ' ', $slug ) ) ?: $url;
}

/**
 * Determine whether tables exist and show an admin notice if not.
 * Hooks itself — call once from the main loader.
 */
function seo_dash_maybe_show_db_notice(): void {
    add_action( 'admin_notices', function () {
        if ( ! current_user_can( 'manage_options' ) ) return;
        // Inline table existence check (no Installer class in v5).
        global $wpdb;
        if ( ! empty( SEO_Dash_Database::$reports ) ) {
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '" . SEO_Dash_Database::$reports . "'" );
            if ( $exists ) return;
        }
        echo '<div class="notice notice-error is-dismissible"><p>'
            . '<strong>SEO Dashboard:</strong> Database tables are missing. '
            . '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Deactivate and reactivate the plugin</a> to create them.'
            . '</p></div>';
    } );
}
add_action( 'plugins_loaded', 'seo_dash_maybe_show_db_notice', 20 );

/**
 * Check if a WP user has permission to view a specific report.
 * Works for Admins and Clients.
 *
 * @param int $user_id
 * @param int $report_id
 * @return bool
 */
function seo_dash_can_user_access_report( int $user_id, int $report_id ): bool {
    if ( ! $user_id || ! $report_id ) return false;
    
    // Admins see everything
    if ( user_can( $user_id, 'manage_options' ) ) return true;

    // Try Client Access
    $client = SEO_Dash_Database::get_client_by_user( $user_id );
    if ( $client ) {
        $assigned = SEO_Dash_Database::get_client_report_ids( intval( $client['id'] ) );
        if ( in_array( $report_id, $assigned, false ) ) return true;
    }

    return false;
}

// ── Import dedup helpers (Service Pages, Blog Posts, GMB, GMB Posts, Technical, Leads) ──
//
// Shared rule for CSV import / Google Sheets sync on these tabs:
//   - If an incoming row is identical (across the relevant columns) to an
//     existing row, ignore it — don't insert a duplicate.
//   - If an incoming row matches an existing row's "identity" (e.g. same
//     name/title/issue/email) but one or more OTHER columns differ (e.g. the
//     URL changed), UPDATE that existing row in place rather than inserting
//     a new one.
//   - If no existing row matches the identity at all, insert a new row.
//
// seo_dash_norm_val() normalizes a single value for comparison (trims
// whitespace, lowercases strings, normalizes numeric strings) so trivial
// formatting differences ("3" vs "3.0", "  Foo  " vs "foo") don't cause a
// real duplicate to be treated as "changed".
if ( ! function_exists( 'seo_dash_norm_val' ) ) {
function seo_dash_norm_val( $v ) {
    if ( is_bool( $v ) ) return $v ? '1' : '0';
    if ( $v === null ) return '';
    $v = (string) $v;
    $v = trim( $v );
    if ( $v !== '' && is_numeric( $v ) ) {
        // Normalize "3", "3.0", "03" etc. to the same comparable form.
        $f = (float) $v;
        return rtrim( rtrim( sprintf( '%.6F', $f ), '0' ), '.' );
    }
    return function_exists( 'mb_strtolower' ) ? mb_strtolower( $v ) : strtolower( $v );
}
}

// seo_dash_rows_equal(): true if $a and $b have the same normalized value
// for every column in $cols.
if ( ! function_exists( 'seo_dash_rows_equal' ) ) {
function seo_dash_rows_equal( array $a, array $b, array $cols ): bool {
    foreach ( $cols as $c ) {
        if ( seo_dash_norm_val( $a[ $c ] ?? '' ) !== seo_dash_norm_val( $b[ $c ] ?? '' ) ) {
            return false;
        }
    }
    return true;
}
}

/**
 * Get all default and custom lead statuses for a report.
 *
 * @param int $report_id
 * @return array
 */
function seo_dash_get_custom_statuses( int $report_id = 0 ): array {
    $default = [
        'new'       => [ 'slug' => 'new',       'label' => 'New',       'icon' => '🔠', 'color' => '#8b5cf6' ],
        'contacted' => [ 'slug' => 'contacted', 'label' => 'Contacted', 'icon' => '📞', 'color' => '#06b6d4' ],
        'checking'  => [ 'slug' => 'checking',  'label' => 'Checking',  'icon' => '🔍', 'color' => '#f59e0b' ],
        'qualified' => [ 'slug' => 'qualified', 'label' => 'Qualified', 'icon' => '✅', 'color' => '#10b981' ],
        'converted' => [ 'slug' => 'converted', 'label' => 'Converted', 'icon' => '🎉', 'color' => '#059669' ],
        'lost'      => [ 'slug' => 'lost',      'label' => 'Lost',      'icon' => '❌', 'color' => '#ef4444' ],
    ];
    if ( ! $report_id ) return $default;
    $custom = get_option( "seo_dash_custom_lead_statuses_{$report_id}", [] );
    if ( is_array( $custom ) && ! empty( $custom ) ) {
        foreach ( $custom as $cs ) {
            if ( isset( $cs['slug'] ) ) {
                $default[sanitize_key($cs['slug'])] = [
                    'slug'  => sanitize_key($cs['slug']),
                    'label' => sanitize_text_field($cs['label'] ?? $cs['slug']),
                    'icon'  => sanitize_text_field($cs['icon'] ?? '🏷️'),
                    'color' => sanitize_text_field($cs['color'] ?? '#3b82f6'),
                ];
            }
        }
    }
    return $default;
}

/**
 * Ensure a raw status string (e.g. from CSV or Google Sheets) is mapped to a slug,
 * registering a new custom status automatically if needed.
 *
 * @param int    $report_id
 * @param string $raw_status
 * @return string
 */
function seo_dash_ensure_custom_lead_status( int $report_id, string $raw_status ): string {
    $raw_status = trim($raw_status);
    if ( empty($raw_status) ) return 'new';

    $slug = sanitize_key( str_replace( ' ', '_', strtolower($raw_status) ) );
    if ( empty($slug) ) return 'new';

    $statuses = seo_dash_get_custom_statuses( $report_id );
    if ( isset( $statuses[$slug] ) ) {
        return $slug;
    }

    // Register new custom status
    $custom = get_option( "seo_dash_custom_lead_statuses_{$report_id}", [] );
    if ( ! is_array( $custom ) ) $custom = [];

    $custom[] = [
        'slug'  => $slug,
        'label' => ucwords($raw_status),
        'icon'  => '🏷️',
        'color' => '#3b82f6',
    ];
    update_option( "seo_dash_custom_lead_statuses_{$report_id}", $custom );
    return $slug;
}

/**
 * Safe wrapper for wp_remote_get that prevents SSRF, DNS Rebinding,
 * and restricts requests to public HTTP/HTTPS endpoints with limits.
 *
 * @param string $url            The URL to request.
 * @param array  $args           Optional arguments to merge.
 * @param int    $redirect_count Number of redirects followed so far.
 * @return array|WP_Error        The response array or WP_Error.
 */
function seo_dash_safe_remote_get( string $url, array $args = [], int $redirect_count = 0 ) {
    // 7. Add reasonable request limits: redirect limit
    if ( $redirect_count > 5 ) {
        return new WP_Error( 'too_many_redirects', 'Too many redirects.' );
    }

    $parsed_url = wp_parse_url( $url );
    if ( ! $parsed_url || empty( $parsed_url['scheme'] ) || empty( $parsed_url['host'] ) ) {
        return new WP_Error( 'invalid_url', 'Invalid URL structure.' );
    }

    // 1. Only allow http:// and https://
    $scheme = strtolower( $parsed_url['scheme'] );
    if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
        return new WP_Error( 'invalid_scheme', 'Only HTTP and HTTPS protocols are allowed.' );
    }

    $host = $parsed_url['host'];
    // Prevent requests to loopback/localhost directly by hostname check
    if ( strcasecmp( $host, 'localhost' ) === 0 || preg_match( '/\.localhost$/i', $host ) ) {
        return new WP_Error( 'invalid_host', 'Requests to localhost are not allowed.' );
    }

    // 6. Restrict ports to 80 and 443
    $port = isset( $parsed_url['port'] ) ? (int) $parsed_url['port'] : ( $scheme === 'https' ? 443 : 80 );
    if ( ! in_array( $port, [ 80, 443 ], true ) ) {
        return new WP_Error( 'invalid_port', 'Only standard HTTP (80) and HTTPS (443) ports are allowed.' );
    }

    $normalized_host = trim( $host, '[]' );
    $ips = [];

    if ( filter_var( $normalized_host, FILTER_VALIDATE_IP ) ) {
        $ips[] = $normalized_host;
    } else {
        // Resolve host to IP addresses (both IPv4 and IPv6 to prevent IPv6 bypass)
        if ( function_exists( 'dns_get_record' ) ) {
            $records_a = @dns_get_record( $normalized_host, DNS_A );
            if ( is_array( $records_a ) ) {
                foreach ( $records_a as $record ) {
                    if ( isset( $record['ip'] ) ) {
                        $ips[] = $record['ip'];
                    }
                }
            }
            $records_aaaa = @dns_get_record( $normalized_host, DNS_AAAA );
            if ( is_array( $records_aaaa ) ) {
                foreach ( $records_aaaa as $record ) {
                    if ( isset( $record['ipv6'] ) ) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }
        if ( empty( $ips ) ) {
            $ip = @gethostbyname( $normalized_host );
            if ( $ip && $ip !== $normalized_host ) {
                $ips[] = $ip;
            }
        }
    }

    if ( empty( $ips ) ) {
        return new WP_Error( 'dns_error', 'Could not resolve host IP address.' );
    }

    // 2. Prevent requests to private/internal addresses.
    // Verify ALL resolved IP addresses to block DNS Rebinding targeting private IPs.
    foreach ( $ips as $ip ) {
        if ( ! seo_dash_is_ip_safe( $ip ) ) {
            return new WP_Error( 'unsafe_destination', 'Access to private or reserved IP address is blocked.' );
        }
    }

    $target_ip = $ips[0];

    // Build safety-focused request arguments
    $default_args = [
        'timeout'     => 15,          // 7. Add reasonable overall timeout
        'redirection' => 0,           // 4. Protect redirects: disable automatic redirects
        'sslverify'   => true,        // 5. Enable TLS verification
        'headers'     => [],
    ];

    $merged_args = array_merge( $default_args, $args );

    // Enforce safety parameters
    $merged_args['redirection'] = 0;
    $merged_args['sslverify']   = true;

    // 3. Protect against DNS rebinding.
    // Pin curl requests to the validated IP address while preserving the Host header.
    $curl_callback = function( $handle, $r, $request_url ) use ( $normalized_host, $port, $target_ip ) {
        $parsed = wp_parse_url( $request_url );
        $req_host = isset( $parsed['host'] ) ? trim( $parsed['host'], '[]' ) : '';
        $req_port = isset( $parsed['port'] ) ? (int) $parsed['port'] : ( ( $parsed['scheme'] ?? '' ) === 'https' ? 443 : 80 );

        if ( strcasecmp( $req_host, $normalized_host ) === 0 && $req_port === $port ) {
            // Pin the hostname to the resolved and verified IP address
            curl_setopt( $handle, CURLOPT_RESOLVE, [ "{$normalized_host}:{$port}:{$target_ip}" ] );

            // 7. Add reasonable request limits: maximum response size limit (2 MB)
            // Aborts download immediately if size limit is exceeded during chunked or normal transfers.
            curl_setopt( $handle, CURLOPT_NOPROGRESS, false );
            curl_setopt( $handle, CURLOPT_PROGRESSFUNCTION, function( $resource, $download_size, $downloaded, $upload_size, $uploaded ) {
                $max_size = 2 * 1024 * 1024;
                if ( $downloaded > $max_size ) {
                    return 1; // Non-zero return value aborts transfer
                }
                return 0;
            } );
        }
    };

    add_action( 'http_api_curl', $curl_callback, 10, 3 );
    $response = wp_remote_get( $url, $merged_args );
    remove_action( 'http_api_curl', $curl_callback, 10 );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $http_code = (int) wp_remote_retrieve_response_code( $response );

    // Double check Content-Length header limit before reading response body
    $content_length = wp_remote_retrieve_header( $response, 'content-length' );
    if ( $content_length && (int) $content_length > 2 * 1024 * 1024 ) {
        return new WP_Error( 'file_too_large', 'The remote file size exceeds the limit of 2MB.' );
    }

    // 4. Protect redirects.
    // Inspect every redirect destination and validate it recursively.
    if ( $http_code >= 300 && $http_code < 400 ) {
        $location = wp_remote_retrieve_header( $response, 'location' );
        if ( ! $location ) {
            return new WP_Error( 'invalid_redirect', 'Redirect location header missing.' );
        }

        $absolute_location = seo_dash_resolve_relative_url( $url, $location );
        if ( ! $absolute_location ) {
            return new WP_Error( 'invalid_redirect_url', 'Could not resolve redirect location URL.' );
        }

        return seo_dash_safe_remote_get( $absolute_location, $args, $redirect_count + 1 );
    }

    return $response;
}

/**
 * Check if the given IP address is public and safe.
 *
 * @param string $ip The IP address to check.
 * @return bool True if safe, false if private/reserved/loopback/multicast.
 */
function seo_dash_is_ip_safe( string $ip ): bool {
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return false;
    }

    // Exclude RFC private and reserved address blocks
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
        return false;
    }

    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        return seo_dash_is_ipv4_safe( $ip );
    } elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        return seo_dash_is_ipv6_safe( $ip );
    }

    return false;
}

/**
 * Check if the given IPv4 address is in standard public IP ranges.
 *
 * @param string $ip
 * @return bool
 */
function seo_dash_is_ipv4_safe( string $ip ): bool {
    $ip_long = ip2long( $ip );
    if ( $ip_long === false ) {
        return false;
    }

    // Explicit CIDR blacklist ranges to cover all RFC requirements
    $unsafe_blocks = [
        '0.0.0.0/8',       // Current network (RFC 1700)
        '10.0.0.0/8',      // Private-Use Networks (RFC 1918)
        '100.64.0.0/10',   // Shared Address Space (RFC 6598)
        '127.0.0.0/8',     // Loopback (RFC 1122)
        '169.254.0.0/16',  // Link-Local (RFC 3927)
        '172.16.0.0/12',   // Private-Use Networks (RFC 1918)
        '192.0.0.0/24',    // IETF Protocol Assignments (RFC 6890)
        '192.0.2.0/24',    // Test-Net-1 (RFC 5737)
        '192.88.99.0/24',  // 6to4 Relay Anycast (RFC 3068)
        '192.168.0.0/16',  // Private-Use Networks (RFC 1918)
        '198.18.0.0/15',   // Network Benchmark Devices (RFC 2544)
        '198.51.100.0/24', // Test-Net-2 (RFC 5737)
        '203.0.113.0/24',  // Test-Net-3 (RFC 5737)
        '224.0.0.0/4',     // Multicast (RFC 1112)
        '240.0.0.0/4',     // Reserved (RFC 1112)
        '255.255.255.255/32', // Broadcast
    ];

    foreach ( $unsafe_blocks as $block ) {
        list( $subnet, $mask ) = explode( '/', $block );
        $subnet_long = ip2long( $subnet );
        $mask_dec = ~ ( ( 1 << ( 32 - $mask ) ) - 1 );
        if ( ( $ip_long & $mask_dec ) === ( $subnet_long & $mask_dec ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Check if the given IPv6 address is in standard public IP ranges.
 *
 * @param string $ip
 * @return bool
 */
function seo_dash_is_ipv6_safe( string $ip ): bool {
    $ip_packed = @inet_pton( $ip );
    if ( $ip_packed === false || strlen( $ip_packed ) !== 16 ) {
        return false;
    }

    // Explicit IPv6 CIDR blacklist ranges to cover all RFC requirements
    $unsafe_blocks = [
        '::/128',             // Unspecified
        '::1/128',            // Loopback
        '::ffff:0:0/96',      // IPv4-mapped addresses
        '100::/64',           // Discard-Only Address Block
        '2001:db8::/32',      // Documentation
        '2002::/16',          // 6to4 relay
        'fc00::/7',           // Unique Local Addresses (ULA)
        'fe80::/10',          // Link-Local Addresses
        'ff00::/8',           // Multicast
    ];

    foreach ( $unsafe_blocks as $block ) {
        list( $subnet, $mask ) = explode( '/', $block );
        $subnet_packed = inet_pton( $subnet );
        if ( $subnet_packed === false ) {
            continue;
        }

        if ( seo_dash_match_ipv6_packed( $ip_packed, $subnet_packed, (int) $mask ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Match a packed IPv6 address against a packed IPv6 subnet.
 *
 * @param string $ip_packed
 * @param string $subnet_packed
 * @param int    $mask
 * @return bool
 */
function seo_dash_match_ipv6_packed( string $ip_packed, string $subnet_packed, int $mask ): bool {
    $bytes = (int) ( $mask / 8 );
    $bits  = $mask % 8;

    for ( $i = 0; $i < $bytes; $i++ ) {
        if ( $ip_packed[ $i ] !== $subnet_packed[ $i ] ) {
            return false;
        }
    }

    if ( $bits > 0 ) {
        $ip_byte     = ord( $ip_packed[ $bytes ] );
        $subnet_byte = ord( $subnet_packed[ $bytes ] );
        $mask_byte   = ( 0xFF << ( 8 - $bits ) ) & 0xFF;
        if ( ( $ip_byte & $mask_byte ) !== ( $subnet_byte & $mask_byte ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Resolve a relative URL to an absolute URL based on a base URL.
 *
 * @param string $base
 * @param string $rel
 * @return string
 */
function seo_dash_resolve_relative_url( string $base, string $rel ): string {
    if ( empty( $rel ) ) {
        return $base;
    }

    if ( parse_url( $rel, PHP_URL_SCHEME ) != '' ) {
        return $rel;
    }

    if ( $rel[0] === '#' || $rel[0] === '?' ) {
        return $base . $rel;
    }

    $parsed = parse_url( $base );
    if ( ! $parsed ) {
        return $rel;
    }

    $scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
    $host   = isset( $parsed['host'] ) ? $parsed['host'] : '';
    $port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
    $path   = isset( $parsed['path'] ) ? $parsed['path'] : '';

    if ( $rel[0] === '/' ) {
        return $scheme . $host . $port . $rel;
    }

    $dir = dirname( $path );
    $dir = str_replace( '\\', '/', $dir );
    if ( $dir === '.' || $dir === '/' ) {
        $dir = '';
    }
    $path = '/' . ltrim( $dir, '/' );
    if ( substr( $path, -1 ) !== '/' ) {
        $path .= '/';
    }
    return $scheme . $host . $port . $path . ltrim( $rel, '/' );
}
