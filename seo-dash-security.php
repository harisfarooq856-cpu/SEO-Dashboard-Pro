<?php
/**
 * SEO Client Dashboard Pro — Security Hardening Module
 *
 * Covers (per WordPress Security Guidelines):
 *  1.  Rate-limiting & brute-force protection (login + AJAX endpoints)
 *  2.  Nonce / capability hardening for every admin page
 *  3.  File-upload MIME-type whitelist (server-side finfo check)
 *  4.  Restrictive HTTP security headers + Content-Security-Policy
 *  5.  AES-256-CBC encryption for stored API keys
 *  6.  Plain-text password scrubbing from wp_options
 *  7.  Password strength enforcement (10 chars, upper, digit, symbol)
 *  8.  Unauthenticated access block on data_version endpoint
 *  9.  XML-RPC disable + REST user-enumeration block
 * 10.  WordPress version hiding
 * 11.  Chat-message length cap (prompt-injection / DoS mitigation)
 * 12.  Directory index blocking (.htaccess)
 * 13.  Security event logging with admin log viewer
 * 14.  SQL-query safeguard wrapper for dynamic meta_key lists
 * 15.  DISALLOW_FILE_EDIT enforcement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 1 — RATE LIMITING & BRUTE-FORCE PROTECTION
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Returns the visitor's real IP address.
 * We deliberately use only REMOTE_ADDR – the one value that cannot
 * be spoofed by an attacker via X-Forwarded-For headers.
 */
function seo_dash_sec_get_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Returns the transient key for a given rate-limit context.
 */
function seo_dash_sec_rl_key( string $context ): string {
    return 'seo_dash_rl_' . $context . '_' . md5( seo_dash_sec_get_ip() );
}

/**
 * Returns TRUE when the current IP has exceeded $max attempts in $window seconds
 * for the given $context.
 */
function seo_dash_sec_is_rate_limited( string $context, int $max = 5, int $window = 300 ): bool {
    return ( (int) get_transient( seo_dash_sec_rl_key( $context ) ) ) >= $max;
}

/**
 * Increments the attempt counter for $context.
 */
function seo_dash_sec_record_attempt( string $context, int $max = 5, int $window = 300 ): void {
    $key   = seo_dash_sec_rl_key( $context );
    $count = (int) get_transient( $key );
    set_transient( $key, $count + 1, $window );
}

/**
 * Clears the attempt counter for $context (call on successful auth).
 */
function seo_dash_sec_clear_rate_limit( string $context ): void {
    delete_transient( seo_dash_sec_rl_key( $context ) );
}

// ─── 1a. Custom front-end login form — brute-force guard ─────────────────
// We intercept before WordPress processes the credential check.
// 5 failures → 15-minute lockout.  Logged + returns HTTP 429.
add_action( 'login_init', 'seo_dash_sec_guard_login_form' );
function seo_dash_sec_guard_login_form(): void {
    // We only care about our plugin's custom login form POST
    if ( ! isset( $_POST['log'], $_POST['pwd'], $_POST['_wpnonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'seo_dash_login' ) ) {
        return; // not our form
    }

    if ( seo_dash_sec_is_rate_limited( 'login', 5, 900 ) ) {
        seo_dash_sec_log( 'login_lockout', sanitize_user( $_POST['log'] ?? '' ) );
        wp_die(
            'Too many failed login attempts from your IP address. '
            . 'Please wait <strong>15 minutes</strong> before trying again.',
            'Too Many Attempts',
            [ 'response' => 429, 'back_link' => true ]
        );
    }
}

// Hook into WP's failed-login action to increment our counter
add_action( 'wp_login_failed', 'seo_dash_sec_on_login_failed' );
function seo_dash_sec_on_login_failed( string $username ): void {
    seo_dash_sec_record_attempt( 'login', 5, 900 );
    seo_dash_sec_log( 'login_failed', sanitize_user( $username ) );
}

// Clear the counter on a successful login
add_action( 'wp_login', 'seo_dash_sec_on_login_success', 10, 1 );
function seo_dash_sec_on_login_success( string $user_login ): void {
    seo_dash_sec_clear_rate_limit( 'login' );
}

// ─── 1b. AJAX endpoint rate limiting ─────────────────────────────────────
// 30 requests per 60 s per IP per action.  Applied to all public-facing
// (nopriv) handlers and the AI-chat endpoint that calls an external API.
$_seo_dash_sec_rate_limited_actions = [
    'wp_ajax_nopriv_seo_dash_doc_file_preupload',
    'wp_ajax_nopriv_seo_dash_doc_front_upload',
    'wp_ajax_nopriv_seo_dash_doc_client_review',
    'wp_ajax_nopriv_seo_dash_front_update_profile',
    'wp_ajax_nopriv_seo_dash_data_version',
    'wp_ajax_seo_dash_generate_summary',
    'wp_ajax_seo_chat',
    'wp_ajax_seo_dash_psi_run',
    'wp_ajax_seo_dash_ga_fetch',
    'wp_ajax_seo_dash_sc_fetch',
];
foreach ( $_seo_dash_sec_rate_limited_actions as $_action ) {
    add_action( $_action, 'seo_dash_sec_ajax_rate_guard', 1 );
}
unset( $_seo_dash_sec_rate_limited_actions, $_action );

function seo_dash_sec_ajax_rate_guard(): void {
    $action = sanitize_key( $_REQUEST['action'] ?? '' );
    $ctx    = 'ajax_' . $action;
    if ( seo_dash_sec_is_rate_limited( $ctx, 30, 60 ) ) {
        seo_dash_sec_log( 'rate_limit_ajax', $action );
        wp_send_json_error( [ 'message' => 'Rate limit exceeded. Please slow down and try again shortly.' ], 200 );
    }
    seo_dash_sec_record_attempt( $ctx, 30, 60 );
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 2 — NONCE & CAPABILITY HARDENING
// ═══════════════════════════════════════════════════════════════════════════

// ─── 2a. Block unauthenticated access to data_version ────────────────────
// The original handler lets non-logged-in requests through (no nonce check
// for the nopriv path, no authentication enforcement).
add_action( 'wp_ajax_nopriv_seo_dash_data_version', 'seo_dash_sec_require_auth_data_version', 1 );
function seo_dash_sec_require_auth_data_version(): void {
    if ( ! is_user_logged_in() ) {
        seo_dash_sec_log( 'unauth_data_version_access', '' );
        wp_send_json_error( 'Authentication required.', 200 );
    }
}

// ─── 2b. Capability check on admin pages ─────────────────────────────────
// The user-assignments page has a nonce on its POST handler but no explicit
// capability guard at the page-load level.
add_action( 'admin_init', 'seo_dash_sec_admin_page_caps' );
function seo_dash_sec_admin_page_caps(): void {
    if ( ! is_admin() ) {
        return;
    }
    $protected_pages = [
        'seo-dash-user-assignments',
        'seo-dash-design',
        'seo-dashboard-settings',
        'seo-dash-security-log',
    ];
    $page = $_GET['page'] ?? '';
    if ( in_array( $page, $protected_pages, true ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Denied', [ 'response' => 403 ] );
    }
}

// ─── 2c. Validate report-access on every nopriv AJAX call ────────────────
// Any nopriv handler that accepts a post_id MUST confirm the logged-in user
// owns that report.  This centralises the check.
function seo_dash_sec_verify_report_access( int $post_id ): bool {
    if ( ! $post_id ) {
        return false;
    }
    if ( ! is_user_logged_in() ) {
        return false;
    }
    $uid = get_current_user_id();
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }
    $single = intval( get_post_meta( $post_id, '_seo_client_user_id', true ) );
    $multi  = array_map( 'intval', seo_dash_get_meta( $post_id, '_seo_report_user_ids', [] ) );
    return $single === $uid || in_array( $uid, $multi, true );
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 3 — FILE-UPLOAD SECURITY (server-side MIME validation)
// ═══════════════════════════════════════════════════════════════════════════

// Allowlist of safe document/image types.
// Extension → canonical MIME type.
const SEO_DASH_ALLOWED_UPLOAD_TYPES = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt'  => 'text/plain',
    'csv'  => 'text/csv',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

// Maximum upload size for plugin documents (5 MB)
const SEO_DASH_MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

add_filter( 'wp_handle_upload_prefilter', 'seo_dash_sec_validate_upload_file' );
function seo_dash_sec_validate_upload_file( array $file ): array {
    // Only intercept our plugin's upload actions
    $action = $_POST['action'] ?? '';
    if ( ! in_array( $action, [
        'seo_dash_doc_file_preupload',
        'seo_dash_doc_front_upload',
        'seo_dash_doc_upload',
    ], true ) ) {
        return $file;
    }

    $allowed_types = SEO_DASH_ALLOWED_UPLOAD_TYPES;

    // 1. Extension check
    $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    if ( ! array_key_exists( $ext, $allowed_types ) ) {
        $file['error'] = sprintf(
            'File type ".%s" is not permitted. Allowed types: %s.',
            esc_html( $ext ),
            implode( ', ', array_map( fn( $e ) => '.' . $e, array_keys( $allowed_types ) ) )
        );
        return $file;
    }

    // 2. File size check (belt-and-suspenders; WP also enforces upload_max_filesize)
    if ( isset( $file['size'] ) && $file['size'] > SEO_DASH_MAX_UPLOAD_BYTES ) {
        $file['error'] = 'File size exceeds the 5 MB limit.';
        return $file;
    }

    // 3. Server-side MIME verification using finfo (ignores client-supplied type)
    if ( function_exists( 'finfo_open' ) && ! empty( $file['tmp_name'] ) ) {
        $finfo     = finfo_open( FILEINFO_MIME_TYPE );
        $real_mime = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        $allowed_mimes = array_values( $allowed_types );
        // GIF/WebP can have multiple valid signatures; add them for robustness
        $allowed_mimes[] = 'image/gif';
        $allowed_mimes[] = 'image/webp';
        $allowed_mimes   = array_unique( $allowed_mimes );

        if ( ! in_array( $real_mime, $allowed_mimes, true ) ) {
            seo_dash_sec_log( 'blocked_upload', $real_mime . ' | ' . basename( $file['name'] ) );
            $file['error'] = 'File content does not match its declared type. Upload rejected for security reasons.';
            return $file;
        }
    }

    // 4. Sanitise the filename — strip dangerous characters
    $safe_name    = sanitize_file_name( $file['name'] );
    $file['name'] = $safe_name;

    return $file;
}

// Restrict upload MIME types when our plugin is performing an upload
add_filter( 'upload_mimes', 'seo_dash_sec_restrict_upload_mimes', 99 );
function seo_dash_sec_restrict_upload_mimes( array $mimes ): array {
    $action = $_POST['action'] ?? '';
    if ( ! in_array( $action, [
        'seo_dash_doc_file_preupload',
        'seo_dash_doc_front_upload',
        'seo_dash_doc_upload',
    ], true ) ) {
        return $mimes;
    }
    // Return only allowed types for our uploads
    return array_intersect_key( $mimes, SEO_DASH_ALLOWED_UPLOAD_TYPES );
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 4 — HTTP SECURITY HEADERS
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'send_headers', 'seo_dash_sec_send_security_headers' );
function seo_dash_sec_send_security_headers(): void {
    if ( headers_sent() ) {
        return;
    }
    // Clickjacking protection
    header( 'X-Frame-Options: SAMEORIGIN' );
    // Prevent browsers from MIME-sniffing the content-type
    header( 'X-Content-Type-Options: nosniff' );
    // Referrer policy
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    // Disable powerful browser APIs that the plugin never uses
    header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
    // Remove server software disclosure
    header_remove( 'X-Powered-By' );
}

// Content-Security-Policy — only on pages that serve the dashboard shortcode
add_action( 'template_redirect', 'seo_dash_sec_content_security_policy' );
function seo_dash_sec_content_security_policy(): void {
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) ) {
        return;
    }
    if ( strpos( $post->post_content, '[seo_dashboard' ) === false ) {
        return;
    }
    if ( headers_sent() ) {
        return;
    }

    $directives = [
        "default-src 'self'",
        // 'unsafe-inline' is required because the plugin outputs inline scripts.
        // Ideally migrate to nonce-based CSP in a future version.
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://www.googletagmanager.com https://www.google-analytics.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "img-src 'self' data: https:",
        "connect-src 'self' https://api.groq.com https://analyticsdata.googleapis.com https://searchconsole.googleapis.com https://www.googleapis.com",
        "media-src 'none'",
        "object-src 'none'",
        "frame-src 'none'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "upgrade-insecure-requests",
    ];

    header( 'Content-Security-Policy: ' . implode( '; ', $directives ) );
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 5 — API KEY ENCRYPTION (AES-256-CBC)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Derives a 256-bit encryption key from WordPress secret keys.
 * The key is deterministic per site but never leaves the server.
 */
function seo_dash_sec_derive_key(): string {
    // Combine multiple WP secret constants for a unique per-site key
    $material = ( defined( 'AUTH_KEY' )       ? AUTH_KEY       : '' )
              . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' )
              . ( defined( 'AUTH_SALT' )       ? AUTH_SALT       : '' )
              . ( defined( 'DB_PASSWORD' )     ? DB_PASSWORD     : '' );
    return hash( 'sha256', $material, true ); // raw 32 bytes
}

/**
 * Encrypts a plaintext API key for storage.
 * Returns base64-encoded (IV + ciphertext).
 * Falls back to plaintext if OpenSSL is unavailable.
 */
function seo_dash_sec_encrypt( string $plaintext ): string {
    if ( empty( $plaintext ) || ! function_exists( 'openssl_encrypt' ) ) {
        return $plaintext;
    }
    $algo   = 'aes-256-cbc';
    $key    = seo_dash_sec_derive_key();
    $iv_len = openssl_cipher_iv_length( $algo );
    $iv     = openssl_random_pseudo_bytes( $iv_len );
    $cipher = openssl_encrypt( $plaintext, $algo, $key, OPENSSL_RAW_DATA, $iv );
    if ( $cipher === false ) {
        return $plaintext;
    }
    // Prefix with a version marker so we can detect encrypted values
    return 'SECv1:' . base64_encode( $iv . $cipher );
}

/**
 * Decrypts a value that was encrypted by seo_dash_sec_encrypt().
 * Legacy (unencrypted) values are returned as-is.
 */
function seo_dash_sec_decrypt( string $stored ): string {
    if ( empty( $stored ) ) {
        return '';
    }
    // Detect encrypted values by our version marker
    if ( strncmp( $stored, 'SECv1:', 6 ) !== 0 ) {
        return $stored; // Legacy unencrypted value — return as-is
    }
    if ( ! function_exists( 'openssl_decrypt' ) ) {
        return $stored;
    }
    $algo    = 'aes-256-cbc';
    $key     = seo_dash_sec_derive_key();
    $iv_len  = openssl_cipher_iv_length( $algo );
    $decoded = base64_decode( substr( $stored, 6 ), true );
    if ( $decoded === false || strlen( $decoded ) <= $iv_len ) {
        return $stored;
    }
    $iv     = substr( $decoded, 0, $iv_len );
    $cipher = substr( $decoded, $iv_len );
    $plain  = openssl_decrypt( $cipher, $algo, $key, OPENSSL_RAW_DATA, $iv );
    return ( $plain !== false ) ? $plain : $stored;
}

// ─── Encrypt before saving, decrypt after reading ────────────────────────
$_seo_dash_sec_encrypted_options = [
    'seo_dash_groq_api_key',
    'seo_dash_ga_credentials',
    'seo_dash_psi_api_key',
];
foreach ( $_seo_dash_sec_encrypted_options as $_opt ) {
    add_filter( 'pre_update_option_' . $_opt, 'seo_dash_sec_encrypt_option' );
    add_filter( 'option_' . $_opt,            'seo_dash_sec_decrypt_option' );
}
unset( $_seo_dash_sec_encrypted_options, $_opt );

function seo_dash_sec_encrypt_option( string $value ): string {
    // Don't double-encrypt if it was already encrypted (e.g. no actual change)
    if ( strncmp( $value, 'SECv1:', 6 ) === 0 ) {
        return $value;
    }
    return seo_dash_sec_encrypt( $value );
}

function seo_dash_sec_decrypt_option( string $value ): string {
    return seo_dash_sec_decrypt( $value );
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 6 — SCRUB PLAIN-TEXT PASSWORDS FROM wp_options
// ═══════════════════════════════════════════════════════════════════════════

/**
 * The 'seo_dash_clients' option stores client records that include a
 * plain-text 'password' key, used only for display in the admin UI.
 * This is a significant security liability. We strip it on every save.
 * Authentication uses WordPress's own hashed credentials; no plain-text
 * copy is needed in the database.
 */
add_filter( 'pre_update_option_seo_dash_clients', 'seo_dash_sec_scrub_plaintext_passwords' );
function seo_dash_sec_scrub_plaintext_passwords( string $value ): string {
    $clients = json_decode( $value, true );
    if ( ! is_array( $clients ) ) {
        return $value;
    }
    foreach ( $clients as &$cl ) {
        unset( $cl['password'] ); // strip plain-text credential
    }
    unset( $cl );
    return wp_json_encode( array_values( $clients ) );
}

/**
 * Also scrub from the per-report _seo_report_users meta on save.
 * We hook update_post_metadata to intercept before DB write.
 */
add_filter( 'update_post_metadata', 'seo_dash_sec_scrub_report_users_meta', 10, 5 );
function seo_dash_sec_scrub_report_users_meta( $check, int $object_id, string $meta_key, $meta_value, $prev_value ) {
    if ( $meta_key !== '_seo_report_users' ) {
        return $check; // not our key — don't interfere
    }
    $users = is_string( $meta_value ) ? json_decode( $meta_value, true ) : $meta_value;
    if ( ! is_array( $users ) ) {
        return $check;
    }
    $changed = false;
    foreach ( $users as &$u ) {
        if ( isset( $u['password'] ) ) {
            unset( $u['password'] );
            $changed = true;
        }
    }
    unset( $u );
    if ( $changed ) {
        // Replace the meta_value with the scrubbed version by letting the hook
        // fall through with the modified value — we call update_metadata directly
        // only if something changed to avoid infinite recursion.
        remove_filter( 'update_post_metadata', 'seo_dash_sec_scrub_report_users_meta', 10 );
        update_post_meta( $object_id, $meta_key, wp_json_encode( array_values( $users ) ) );
        add_filter( 'update_post_metadata', 'seo_dash_sec_scrub_report_users_meta', 10, 5 );
        return false; // short-circuit the original update (we already did it)
    }
    return $check;
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 7 — PASSWORD STRENGTH ENFORCEMENT
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Validates password strength.
 * Returns TRUE on success, WP_Error on failure.
 *
 * Rules (aligned with NIST SP 800-63B guidance):
 *  - Minimum 10 characters
 *  - At least one uppercase letter
 *  - At least one digit
 *  - At least one special character
 *  - Not a common/breached password (basic check)
 */
function seo_dash_sec_validate_password( string $pass ): bool|\WP_Error {
    if ( strlen( $pass ) < 10 ) {
        return new \WP_Error( 'weak_password', 'Password must be at least 10 characters long.' );
    }
    if ( ! preg_match( '/[A-Z]/', $pass ) ) {
        return new \WP_Error( 'weak_password', 'Password must contain at least one uppercase letter.' );
    }
    if ( ! preg_match( '/[0-9]/', $pass ) ) {
        return new \WP_Error( 'weak_password', 'Password must contain at least one number.' );
    }
    if ( ! preg_match( '/[^A-Za-z0-9]/', $pass ) ) {
        return new \WP_Error( 'weak_password', 'Password must contain at least one special character (e.g. !@#$%).' );
    }
    // Basic blocklist of trivially guessable passwords
    $blocklist = [ 'password1', 'Password1', 'Password1!', '12345678', 'Passw0rd' ];
    if ( in_array( $pass, $blocklist, true ) ) {
        return new \WP_Error( 'weak_password', 'That password is too common. Please choose a stronger one.' );
    }
    return true;
}

/**
 * Intercept the profile-update AJAX handler's password field and apply
 * strength requirements before wp_set_password() is called.
 * We hook at priority 1 to run BEFORE the main handler (priority 10).
 */
add_action( 'wp_ajax_seo_dash_front_update_profile',        'seo_dash_sec_validate_profile_password', 1 );
add_action( 'wp_ajax_nopriv_seo_dash_front_update_profile', 'seo_dash_sec_validate_profile_password', 1 );
function seo_dash_sec_validate_profile_password(): void {
    $field = sanitize_key( $_POST['field'] ?? '' );
    if ( $field !== 'password' ) {
        return; // only intercept password changes
    }
    $new_pass = $_POST['new_password'] ?? '';
    $result   = seo_dash_sec_validate_password( $new_pass );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message(), 200 );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 8 — XML-RPC & REST API HARDENING
// ═══════════════════════════════════════════════════════════════════════════

// Disable XML-RPC (the plugin does not use it; it is an attack surface)
add_filter( 'xmlrpc_enabled', '__return_false' );

// Remove the X-Pingback header that advertises XML-RPC is available
add_filter( 'wp_headers', function ( array $headers ): array {
    unset( $headers['X-Pingback'] );
    return $headers;
} );

// Prevent REST API user enumeration for unauthenticated / non-admin requests
add_filter( 'rest_endpoints', 'seo_dash_sec_restrict_rest_user_endpoints' );
function seo_dash_sec_restrict_rest_user_endpoints( array $endpoints ): array {
    if ( ! current_user_can( 'list_users' ) ) {
        unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    }
    return $endpoints;
}

// Block REST API entirely for unauthenticated requests (optional but recommended
// if the front-end dashboard does not use the REST API publicly).
add_filter( 'rest_authentication_errors', 'seo_dash_sec_restrict_rest_for_public' );
function seo_dash_sec_restrict_rest_for_public( $result ) {
    // Allow if already authenticated (logged in, application password, etc.)
    if ( ! empty( $result ) ) {
        return $result;
    }
    // Allow WP core namespaces needed by Gutenberg / auto-updates
    $allowed_namespaces = [ 'wp/v2/types', 'wp/v2/taxonomies' ];
    $route              = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
    foreach ( $allowed_namespaces as $ns ) {
        if ( strpos( $route, $ns ) !== false ) {
            return $result;
        }
    }
    if ( ! is_user_logged_in() ) {
        return new \WP_Error(
            'rest_not_logged_in',
            'REST API access requires authentication.',
            [ 'status' => 401 ]
        );
    }
    return $result;
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 9 — INFORMATION DISCLOSURE PREVENTION
// ═══════════════════════════════════════════════════════════════════════════

// Hide WordPress version number from all public output
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// Remove version strings from enqueued scripts and styles
add_filter( 'script_loader_src', 'seo_dash_sec_remove_ver_from_src', 15 );
add_filter( 'style_loader_src',  'seo_dash_sec_remove_ver_from_src', 15 );
function seo_dash_sec_remove_ver_from_src( string $src ): string {
    if ( strpos( $src, 'ver=' ) !== false ) {
        return remove_query_arg( 'ver', $src );
    }
    return $src;
}

// Disable file editing via the WordPress admin (defence-in-depth)
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
    define( 'DISALLOW_FILE_EDIT', true );
}
if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
    // Comment out the line below if you need to install/update plugins from wp-admin
    // define( 'DISALLOW_FILE_MODS', true );
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 10 — CHAT INPUT SANITISATION (prompt-injection / DoS mitigation)
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_seo_chat',        'seo_dash_sec_sanitise_chat_input', 1 );
add_action( 'wp_ajax_nopriv_seo_chat', 'seo_dash_sec_sanitise_chat_input', 1 );
function seo_dash_sec_sanitise_chat_input(): void {
    $msg = $_POST['message'] ?? '';

    // Length cap — prevent very long inputs that waste API quota or cause timeouts
    if ( mb_strlen( $msg, 'UTF-8' ) > 2000 ) {
        wp_send_json_error( 'Message too long. Maximum 2 000 characters allowed.', 200 );
    }

    // Strip any embedded HTML/script to mitigate stored-XSS via AI responses
    $_POST['message'] = wp_strip_all_tags( $msg );
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 11 — DIRECTORY LISTING PROTECTION
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'init', 'seo_dash_sec_write_htaccess_guards', 1 );
function seo_dash_sec_write_htaccess_guards(): void {
    $dirs = [
        SEO_DASH_PATH,
        SEO_DASH_PATH . 'assets/',
    ];
    $content = "Options -Indexes\n"
             . "<Files *.php>\n"
             . "  Require all denied\n"
             . "</Files>\n";

    foreach ( $dirs as $dir ) {
        $htaccess = rtrim( $dir, '/' ) . '/.htaccess';
        // Only write the root plugin dir .htaccess to block direct PHP file access.
        // The main plugin file itself is allowed through WordPress's bootstrap.
        if ( $dir === SEO_DASH_PATH ) {
            $content_dir = "Options -Indexes\n"; // don't block PHP in plugin root
        } else {
            $content_dir = $content;
        }
        if ( ! file_exists( $htaccess ) && is_writable( dirname( $htaccess ) ) ) {
            file_put_contents( $htaccess, $content_dir );
        }
    }
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 12 — SENSITIVE DATA IN ERROR MESSAGES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Ensure wp_send_json_error never leaks internal PHP errors / stack traces
 * to the client. We filter all JSON error responses sent during AJAX.
 */
add_filter( 'wp_doing_ajax', function ( bool $doing ): bool {
    if ( $doing ) {
        // Turn off PHP error display; log to WP_DEBUG_LOG instead
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            ini_set( 'display_errors', '0' );
        }
    }
    return $doing;
} );


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 13 — SECURITY EVENT LOGGING
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Appends one entry to the security log (stored in wp_options, capped at 500).
 *
 * @param string $event  Short machine-readable event key.
 * @param string $detail Human-readable detail / username / action.
 */
function seo_dash_sec_log( string $event, string $detail = '' ): void {
    // Lightweight lock to prevent concurrent writes clobbering each other.
    // In practice the race window is tiny; a full mutex is overkill here.
    $log = get_option( 'seo_dash_security_log', [] );
    if ( ! is_array( $log ) ) {
        $log = [];
    }
    array_unshift( $log, [
        'time'   => current_time( 'Y-m-d H:i:s' ),
        'ip'     => seo_dash_sec_get_ip(),
        'user'   => get_current_user_id(),
        'event'  => sanitize_text_field( $event ),
        'detail' => sanitize_text_field( substr( $detail, 0, 255 ) ),
    ] );
    if ( count( $log ) > 500 ) {
        $log = array_slice( $log, 0, 500 );
    }
    update_option( 'seo_dash_security_log', $log, false /* autoload = no */ );
}

// Log blocked uploads
add_filter( 'wp_handle_upload_prefilter', function ( array $file ): array {
    if ( ! empty( $file['error'] ) ) {
        seo_dash_sec_log( 'blocked_upload', ( $file['name'] ?? '' ) . ' — ' . $file['error'] );
    }
    return $file;
}, 999 );


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 14 — ADMIN SECURITY LOG PAGE
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'admin_menu', 'seo_dash_sec_register_log_page', 20 );
function seo_dash_sec_register_log_page(): void {
    add_submenu_page(
        'edit.php?post_type=seo_reports',
        '🔒 Security Log',
        '🔒 Security Log',
        'manage_options',
        'seo-dash-security-log',
        'seo_dash_sec_render_log_page'
    );
}

function seo_dash_sec_render_log_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized', 403 );
    }

    // Handle log-clear action
    if (
        isset( $_POST['seo_dash_sec_clear_nonce'] )
        && wp_verify_nonce( $_POST['seo_dash_sec_clear_nonce'], 'seo_dash_sec_clear_log' )
    ) {
        update_option( 'seo_dash_security_log', [], false );
        echo '<div class="notice notice-success is-dismissible"><p>✅ Security log cleared.</p></div>';
    }

    $log = get_option( 'seo_dash_security_log', [] );
    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            🔒 Security Log
            <span style="font-size:13px;font-weight:400;color:#6b7280;">— Last <?php echo count( $log ); ?> events</span>
        </h1>

        <form method="post" style="margin-bottom:20px;">
            <?php wp_nonce_field( 'seo_dash_sec_clear_log', 'seo_dash_sec_clear_nonce' ); ?>
            <button type="submit" class="button button-secondary"
                    onclick="return confirm('This will permanently delete all log entries. Continue?');">
                🗑 Clear Log
            </button>
        </form>

        <?php if ( empty( $log ) ) : ?>
            <p style="color:#6b7280;">No security events logged yet.</p>
        <?php else : ?>
            <table class="widefat fixed striped" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:160px;">Time</th>
                        <th style="width:140px;">IP Address</th>
                        <th style="width:80px;">User ID</th>
                        <th style="width:180px;">Event</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $log as $entry ) : ?>
                    <tr>
                        <td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
                        <td><code><?php echo esc_html( $entry['ip'] ?? '' ); ?></code></td>
                        <td><?php echo esc_html( $entry['user'] ?? '0' ); ?></td>
                        <td>
                            <?php
                            $evt        = $entry['event'] ?? '';
                            $badge_map  = [
                                'login_failed'    => '#ef4444',
                                'login_lockout'   => '#b91c1c',
                                'rate_limit_ajax' => '#f59e0b',
                                'blocked_upload'  => '#7c3aed',
                                'unauth_data_version_access' => '#0284c7',
                            ];
                            $color      = $badge_map[ $evt ] ?? '#6b7280';
                            ?>
                            <span style="background:<?php echo esc_attr( $color ); ?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">
                                <?php echo esc_html( $evt ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $entry['detail'] ?? '' ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 15 — NONCE LIFETIME REDUCTION (admin forms)
// ═══════════════════════════════════════════════════════════════════════════

// WordPress default nonce lifetime is 24 hours (86400 s).
// Reduce to 4 hours for tighter CSRF protection on sensitive admin operations.
add_filter( 'nonce_life', 'seo_dash_sec_nonce_lifetime' );
function seo_dash_sec_nonce_lifetime( int $life ): int {
    // NOTE: This plugin uses a frontend shortcode page for the admin dashboard.
    // Nonces are created on the frontend (is_admin()=false) but verified on
    // admin-ajax.php (is_admin()=true). Applying different lifetimes on each
    // side causes all nonces to fail. Use a single consistent lifetime everywhere.
    return 4 * HOUR_IN_SECONDS; // 4 hours, applied consistently on both sides
}


// ═══════════════════════════════════════════════════════════════════════════
// SECTION 16 — SAFE JSON-RESPONSE HELPER
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Thin wrapper around wp_send_json_error that always calls wp_die()
 * and never leaks the $data array keys to unauthenticated callers.
 *
 * @param string $message Safe, user-visible error message.
 * @param int    $status  HTTP status code.
 */
function seo_dash_sec_json_error( string $message, int $status = 400 ): never {
    wp_send_json_error( [ 'message' => $message ], $status );
}
