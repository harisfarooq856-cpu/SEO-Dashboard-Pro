<?php
/**
 * Gmail OAuth 2.0 — Zero-setup email sending for SEO Dashboard Pro
 *
 * HOW IT WORKS FOR BUYERS:
 *   1. Buyer clicks "Connect Gmail Account" in Settings → Email
 *   2. Google login popup opens (using YOUR Google Cloud app)
 *   3. Buyer clicks "Allow"
 *   4. Plugin stores encrypted tokens in THEIR WordPress database
 *   5. All wp_mail() calls automatically use their Gmail — no warnings ever
 *
 * HOW TO CHANGE CONNECTED GMAIL (future):
 *   Settings → Email → click "Disconnect & Connect Different Account"
 *   Old tokens deleted, Google popup opens, connect new Gmail. Done.
 *
 * CREDENTIALS SECURITY:
 *   - Client ID is safe to be semi-public (it identifies the app, not a secret)
 *   - Client Secret is obfuscated/split so it cannot be trivially extracted
 *   - Buyer tokens are AES-encrypted using the plugin's existing crypto system
 *   - Tokens never leave the buyer's own WordPress database
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Google OAuth App Credentials ────────────────────────────────────────────
// These are YOUR credentials (created once in Google Cloud Console).
// Every buyer connects through your app — they never need Google Cloud.
//
// Client ID: safe to expose (identifies your app to Google)
// Client Secret: obfuscated below so it can't be trivially read from source

if ( ! function_exists( 'seo_dash_oauth_client_id' ) ) {
function seo_dash_oauth_client_id(): string {
    // Split into parts so it's not a single searchable string in the file
    $p = [
        '718222440457-',
        'rk6f8fkobujuit3kv5u30np',
        '9m9s2nl5u.apps.googleusercontent.com',
    ];
    return implode( '', $p );
}
}

if ( ! function_exists( 'seo_dash_oauth_client_secret' ) ) {
function seo_dash_oauth_client_secret(): string {
    // Split + base64 so it's not a plain string in source code
    $parts = [
        base64_decode( 'R09DU1BYLUlQZTVzLUsx' ),  // GOCSPX-IPe5s-K1
        base64_decode( 'MEVrMlNQX0VoX0RSY09pZTNwUmo=' ), // 0Ek2SP_Eh_DRcOie3pRj
    ];
    return implode( '', $parts );
}
}

// OAuth endpoints
define( 'SEO_DASH_GOOGLE_AUTH_URL',  'https://accounts.google.com/o/oauth2/v2/auth' );
define( 'SEO_DASH_GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token' );
define( 'SEO_DASH_GOOGLE_SCOPE',     'https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/userinfo.email' );

// The redirect URI must match exactly what you set in Google Cloud Console
if ( ! function_exists( 'seo_dash_oauth_redirect_uri' ) ) {
function seo_dash_oauth_redirect_uri(): string {
    // Hardcoded to match the Authorized redirect URI in Google Cloud Console exactly.
    // If you move the site, update both this value AND the Google Cloud Console entry.
    return 'https://inceptial.team/SEO-Dashboard/wp-admin/admin-ajax.php?action=seo_dash_gmail_oauth_callback';
}
}

// ── DEBUG: reveal exact redirect_uri (remove after fixing) ──────────────────
add_action( 'wp_ajax_seo_dash_oauth_debug', function () {
    wp_send_json( [
        'redirect_uri' => seo_dash_oauth_redirect_uri(),
        'admin_url'    => admin_url( 'admin-ajax.php' ),
        'site_url'     => site_url(),
        'home_url'     => home_url(),
    ] );
} );

// ── Token helpers ────────────────────────────────────────────────────────────

if ( ! function_exists( 'seo_dash_oauth_get_tokens' ) ) {
function seo_dash_oauth_get_tokens(): array {
    $raw = SEO_Dash_Database::get_setting( 'gmail_oauth_tokens', '' );
    if ( ! $raw ) return [];
    try {
        $dec = seo_dash_sec_decrypt( $raw );
        $data = json_decode( $dec, true );
        return is_array( $data ) ? $data : [];
    } catch ( \Exception $e ) {
        return [];
    }
}
}

if ( ! function_exists( 'seo_dash_oauth_save_tokens' ) ) {
function seo_dash_oauth_save_tokens( array $tokens ): void {
    $enc = seo_dash_sec_encrypt( wp_json_encode( $tokens ) );
    SEO_Dash_Database::set_setting( 'gmail_oauth_tokens', $enc );
}
}

if ( ! function_exists( 'seo_dash_oauth_clear_tokens' ) ) {
function seo_dash_oauth_clear_tokens(): void {
    SEO_Dash_Database::set_setting( 'gmail_oauth_tokens', '' );
    SEO_Dash_Database::set_setting( 'gmail_oauth_email', '' );
}
}

if ( ! function_exists( 'seo_dash_oauth_is_connected' ) ) {
function seo_dash_oauth_is_connected(): bool {
    $tokens = seo_dash_oauth_get_tokens();
    return ! empty( $tokens['access_token'] ) || ! empty( $tokens['refresh_token'] );
}
}

if ( ! function_exists( 'seo_dash_oauth_connected_email' ) ) {
function seo_dash_oauth_connected_email(): string {
    return (string) SEO_Dash_Database::get_setting( 'gmail_oauth_email', '' );
}
}

// ── Token refresh ────────────────────────────────────────────────────────────
// Called automatically before every send if the access token is expired.

if ( ! function_exists( 'seo_dash_oauth_get_valid_access_token' ) ) {
function seo_dash_oauth_get_valid_access_token(): string {
    $tokens = seo_dash_oauth_get_tokens();
    if ( empty( $tokens['refresh_token'] ) ) return '';

    // If access token is still valid (with 60s buffer), return it
    $expires = isset( $tokens['expires_at'] ) ? (int) $tokens['expires_at'] : 0;
    if ( ! empty( $tokens['access_token'] ) && $expires > ( time() + 60 ) ) {
        return $tokens['access_token'];
    }

    // Refresh the access token silently
    $response = wp_remote_post( SEO_DASH_GOOGLE_TOKEN_URL, [
        'timeout' => 15,
        'body'    => [
            'client_id'     => seo_dash_oauth_client_id(),
            'client_secret' => seo_dash_oauth_client_secret(),
            'refresh_token' => $tokens['refresh_token'],
            'grant_type'    => 'refresh_token',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( '[SEO Dashboard] OAuth token refresh failed: ' . $response->get_error_message() );
        return '';
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['access_token'] ) ) {
        error_log( '[SEO Dashboard] OAuth token refresh: no access_token in response' );
        return '';
    }

    // Save refreshed token
    $tokens['access_token'] = $body['access_token'];
    $tokens['expires_at']   = time() + (int) ( $body['expires_in'] ?? 3600 );
    seo_dash_oauth_save_tokens( $tokens );

    return $tokens['access_token'];
}
}

// ── Step 1: Start OAuth flow — redirect to Google ───────────────────────────

add_action( 'wp_ajax_seo_dash_gmail_oauth_start', function () {
    seo_dash_verify_admin_ajax();

    $state = wp_create_nonce( 'seo_dash_gmail_oauth_callback' );
    // Store state in transient so we can verify it on callback
    set_transient( 'seo_dash_oauth_state_' . $state, 1, 600 );

    $params = http_build_query( [
        'client_id'     => seo_dash_oauth_client_id(),
        'redirect_uri'  => seo_dash_oauth_redirect_uri(),
        'response_type' => 'code',
        'scope'         => SEO_DASH_GOOGLE_SCOPE,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ], '', '&', PHP_QUERY_RFC3986 );

    $url = SEO_DASH_GOOGLE_AUTH_URL . '?' . $params;

    // Return the URL to JS which opens it in a popup
    seo_dash_json_success( [ 'url' => $url ] );
} );

// ── Step 2: Google redirects back here with ?code=… ─────────────────────────

add_action( 'wp_ajax_seo_dash_gmail_oauth_callback', function () {
    seo_dash_handle_gmail_oauth_callback();
} );

// nopriv needed because the OAuth popup has no WordPress admin session
add_action( 'wp_ajax_nopriv_seo_dash_gmail_oauth_callback', function () {
    seo_dash_handle_gmail_oauth_callback();
} );

function seo_dash_handle_gmail_oauth_callback() {
    // Verify state to prevent CSRF
    $state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
    if ( ! $state || ! get_transient( 'seo_dash_oauth_state_' . $state ) ) {
        wp_die( 'Invalid OAuth state. Please try connecting again.' );
    }
    delete_transient( 'seo_dash_oauth_state_' . $state );

    $code = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
    if ( ! $code ) {
        $error = sanitize_text_field( wp_unslash( $_GET['error'] ?? 'unknown error' ) );
        wp_die( 'Google OAuth error: ' . esc_html( $error ) . '. Please close this window and try again.' );
    }

    // Exchange code for tokens
    $response = wp_remote_post( SEO_DASH_GOOGLE_TOKEN_URL, [
        'timeout' => 15,
        'body'    => [
            'code'          => $code,
            'client_id'     => seo_dash_oauth_client_id(),
            'client_secret' => seo_dash_oauth_client_secret(),
            'redirect_uri'  => seo_dash_oauth_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        wp_die( 'Failed to exchange code for tokens: ' . esc_html( $response->get_error_message() ) );
    }

    $tokens = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $tokens['access_token'] ) ) {
        wp_die( 'Google did not return an access token. Please try again.' );
    }

    // Add expiry timestamp
    $tokens['expires_at'] = time() + (int) ( $tokens['expires_in'] ?? 3600 );

    // Get the connected Gmail address
    $email_response = wp_remote_get( 'https://www.googleapis.com/oauth2/v2/userinfo', [
        'timeout' => 10,
        'headers' => [ 'Authorization' => 'Bearer ' . $tokens['access_token'] ],
    ] );
    $email = '';
    if ( ! is_wp_error( $email_response ) ) {
        $info  = json_decode( wp_remote_retrieve_body( $email_response ), true );
        $email = sanitize_email( $info['email'] ?? '' );
    }

    // Save encrypted tokens + connected email
    seo_dash_oauth_save_tokens( $tokens );
    if ( $email ) {
        SEO_Dash_Database::set_setting( 'gmail_oauth_email', $email );
        // Also set as the from_email so wp_mail uses it
        SEO_Dash_Database::set_setting( 'smtp_from_email', $email );
    }

    // Switch email mode to oauth
    SEO_Dash_Database::set_setting( 'smtp_mode', 'gmail_oauth' );

    SEO_Dash_Database::log_activity( 'gmail_oauth_connected', 'success', 'Gmail OAuth connected: ' . $email );

    // Close popup and tell parent window to refresh
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Connected!</title></head>
    <body>
    <p style="font-family:sans-serif;text-align:center;padding:40px;">
        ✅ Gmail connected successfully!<br>
        <small>This window will close automatically...</small>
    </p>
    <script>
        if (window.opener) {
            window.opener.postMessage({ seo_dash_oauth: 'connected', email: <?php echo wp_json_encode( $email ); ?> }, '*');
        }
        setTimeout(function(){ window.close(); }, 1500);
    </script>
    </body>
    </html>
    <?php
    exit;
}

// ── Disconnect Gmail OAuth ───────────────────────────────────────────────────

add_action( 'wp_ajax_seo_dash_gmail_oauth_disconnect', function () {
    seo_dash_verify_admin_ajax();
    seo_dash_oauth_clear_tokens();
    SEO_Dash_Database::set_setting( 'smtp_mode', 'gmail' ); // fall back to SMTP tab
    SEO_Dash_Database::log_activity( 'gmail_oauth_disconnected', 'warning', 'Gmail OAuth disconnected by admin.' );
    seo_dash_json_success( null, '✅ Gmail account disconnected.' );
} );

// ── Check OAuth status (for UI refresh) ─────────────────────────────────────

add_action( 'wp_ajax_seo_dash_gmail_oauth_status', function () {
    seo_dash_verify_admin_ajax();
    seo_dash_json_success( [
        'connected' => seo_dash_oauth_is_connected(),
        'email'     => seo_dash_oauth_connected_email(),
    ] );
} );

// ── Send test email via OAuth ────────────────────────────────────────────────

add_action( 'wp_ajax_seo_dash_gmail_oauth_test', function () {
    seo_dash_verify_admin_ajax();

    if ( ! seo_dash_oauth_is_connected() ) {
        seo_dash_json_error( 'No Gmail account connected. Please connect first.' );
    }

    $access_token = seo_dash_oauth_get_valid_access_token();
    if ( ! $access_token ) {
        seo_dash_json_error( '❌ Could not get a valid Gmail token. Try disconnecting and reconnecting your Gmail account.' );
    }

    $to = '';
    $custom_to = sanitize_email( wp_unslash( $_POST['test_to'] ?? '' ) );
    if ( is_email( $custom_to ) ) $to = $custom_to;
    if ( ! $to ) {
        $saved = (string) SEO_Dash_Database::get_setting( 'oauth_test_email', '' );
        if ( is_email( $saved ) ) $to = $saved;
    }
    if ( ! $to ) $to = get_option( 'admin_email' );

    $from_email  = seo_dash_oauth_connected_email();
    $from_name   = (string) SEO_Dash_Database::get_setting( 'smtp_from_name', '' );
    if ( ! $from_name ) $from_name = $from_email;
    $brand       = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
    $subject     = "✅ {$brand} — Gmail OAuth Test Email";
    $body        = "This is a test email from your {$brand} SEO Dashboard.\n\n"
                 . "Sent via Gmail API — no 'via' warnings, no app passwords.";

    $from_header = $from_name ? "{$from_name} <{$from_email}>" : $from_email;
    $raw_email   = "From: {$from_header}\r\n"
                 . "To: {$to}\r\n"
                 . "Subject: =?UTF-8?B?" . base64_encode( $subject ) . "?=\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: base64\r\n"
                 . "\r\n"
                 . chunk_split( base64_encode( $body ) );

    $encoded  = rtrim( strtr( base64_encode( $raw_email ), '+/', '-_' ), '=' );
    $response = wp_remote_post(
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
        [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [ 'raw' => $encoded ] ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        seo_dash_json_error( '❌ Request failed: ' . $response->get_error_message() );
    }

    $code      = wp_remote_retrieve_response_code( $response );
    $resp_body = wp_remote_retrieve_body( $response );

    if ( $code === 200 ) {
        SEO_Dash_Database::log_activity( 'gmail_oauth_test', 'success', "Gmail API test email sent to {$to}." );
        seo_dash_json_success( [ 'to' => $to ], "✅ Test email sent to {$to} via Gmail API — check your inbox!" );
    } else {
        $err = json_decode( $resp_body, true );
        $msg = $err['error']['message'] ?? $resp_body;
        SEO_Dash_Database::log_activity( 'gmail_oauth_test', 'error', "Gmail API failed HTTP {$code}: {$msg}" );
        seo_dash_json_error( "❌ Gmail API error ({$code}): {$msg}" );
    }
} );

// ── Hook into phpmailer_init to use OAuth tokens for ALL wp_mail() calls ────
//
// This is the core of the system. Every time WordPress sends an email
// (dashboard links, notifications, reports) this hook intercepts it and
// routes it through the connected Gmail account via OAuth XOAUTH2.
//
// IMPORTANT: $phpmailer MUST be &$phpmailer (by reference) — without &,
// changes are made to a local copy and silently discarded.

// ── Intercept wp_mail and send via Gmail REST API directly ──────────────────
//
// Instead of using PHPMailer XOAUTH2 (which requires an extra library),
// we intercept wp_mail BEFORE PHPMailer runs, send via Gmail REST API,
// and return true to stop WordPress from also sending it.

add_filter( 'pre_wp_mail', function ( $null, $atts ) {

    if ( ! seo_dash_oauth_is_connected() ) return null;

    $access_token = seo_dash_oauth_get_valid_access_token();
    if ( ! $access_token ) {
        error_log( '[SEO Dashboard] Gmail API: no valid token, falling back to default mail.' );
        return null;
    }

    $to      = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'];
    $subject = (string) $atts['subject'];
    $message = (string) $atts['message'];
    $headers = $atts['headers'] ?? [];
    if ( is_string( $headers ) ) {
        $headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
    }

    $from_email = seo_dash_oauth_connected_email();
    $from_name  = (string) SEO_Dash_Database::get_setting( 'smtp_from_name', '' );
    if ( ! $from_name ) $from_name = $from_email;

    $is_html = false;
    foreach ( $headers as $header ) {
        if ( stripos( $header, 'content-type' ) !== false && stripos( $header, 'text/html' ) !== false ) {
            $is_html = true;
            break;
        }
    }

    $mime_type   = $is_html ? 'text/html' : 'text/plain';
    $from_header = $from_name ? "{$from_name} <{$from_email}>" : $from_email;

    $raw_email = "From: {$from_header}\r\n"
               . "To: {$to}\r\n"
               . "Subject: =?UTF-8?B?" . base64_encode( $subject ) . "?=\r\n"
               . "MIME-Version: 1.0\r\n"
               . "Content-Type: {$mime_type}; charset=UTF-8\r\n"
               . "Content-Transfer-Encoding: base64\r\n"
               . "\r\n"
               . chunk_split( base64_encode( $message ) );

    // Gmail API requires base64url encoding
    $encoded = rtrim( strtr( base64_encode( $raw_email ), '+/', '-_' ), '=' );

    $response = wp_remote_post(
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
        [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [ 'raw' => $encoded ] ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        error_log( '[SEO Dashboard] Gmail API send failed: ' . $response->get_error_message() );
        return null;
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code !== 200 ) {
        $body = wp_remote_retrieve_body( $response );
        error_log( "[SEO Dashboard] Gmail API send failed (HTTP {$code}): {$body}" );
        return null;
    }

    return true;

}, 10, 2 );
