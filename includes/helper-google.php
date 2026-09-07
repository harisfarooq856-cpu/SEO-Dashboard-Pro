<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Google API helper.
 *
 * Fetches a short-lived OAuth2 access token from a service-account
 * JSON credential set.  Tokens are cached in a WP transient keyed by
 * the MD5 of the service-account email + scope so concurrent requests
 * don't burn through Google's rate limits.
 */

/**
 * Get a valid Google OAuth2 access token for the given scope.
 *
 * @param array  $creds  Decoded JSON service-account credentials array.
 *                       Must contain: client_email, private_key.
 * @param string $scope  Full Google API scope URI, e.g.
 *                       'https://www.googleapis.com/auth/analytics.readonly'
 * @return string  Access token, or empty string on failure.
 */
function seo_dash_get_google_token( array $creds, string $scope ): string {

    if ( empty( $creds['client_email'] ) || empty( $creds['private_key'] ) ) {
        return '';
    }

    // ── Cache ──────────────────────────────────────────────────────────────
    $cache_key = 'seo_dash_gtoken_' . md5( $creds['client_email'] . $scope );
    $cached    = get_transient( $cache_key );
    if ( $cached ) {
        return $cached;
    }

    // ── Build JWT ──────────────────────────────────────────────────────────
    $now = time();
    $header = seo_dash_base64url_encode( wp_json_encode( [
        'alg' => 'RS256',
        'typ' => 'JWT',
    ] ) );

    $claim = seo_dash_base64url_encode( wp_json_encode( [
        'iss'   => $creds['client_email'],
        'scope' => $scope,
        'aud'   => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now,
    ] ) );

    $signing_input = $header . '.' . $claim;
    $signature     = '';

    if ( ! openssl_sign( $signing_input, $signature, $creds['private_key'], 'SHA256' ) ) {
        return '';
    }

    $jwt = $signing_input . '.' . seo_dash_base64url_encode( $signature );

    // ── Exchange JWT for access token ──────────────────────────────────────
    $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
        'timeout' => 15,
        'body'    => [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        return '';
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $token = $body['access_token'] ?? '';

    if ( $token ) {
        // Cache for slightly less than the 1-hour expiry.
        set_transient( $cache_key, $token, 3500 );
    }

    return $token;
}

/**
 * Base64url encode (no padding) — required for JWT.
 *
 * @param string $data
 * @return string
 */
function seo_dash_base64url_encode( string $data ): string {
    return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

/**
 * Make an authenticated GET request to a Google API endpoint.
 *
 * @param string $url     Full endpoint URL (with query string).
 * @param string $token   Access token from seo_dash_get_google_token().
 * @return array|null     Decoded JSON body, or null on error.
 */
function seo_dash_google_get( string $url, string $token ): ?array {
    $response = wp_remote_get( $url, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        return null;
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code !== 200 ) {
        return null;
    }

    return json_decode( wp_remote_retrieve_body( $response ), true ) ?: null;
}

/**
 * Make an authenticated POST request to a Google API endpoint.
 *
 * @param string $url
 * @param array  $body   Array to JSON-encode as request body.
 * @param string $token
 * @return array|null
 */
function seo_dash_google_post( string $url, array $body, string $token ): ?array {
    $response = wp_remote_post( $url, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body' => wp_json_encode( $body ),
    ] );

    if ( is_wp_error( $response ) ) {
        $GLOBALS['seo_dash_google_last_error'] = 'HTTP request failed: ' . $response->get_error_message();
        return null;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $raw  = wp_remote_retrieve_body( $response );

    if ( $code < 200 || $code >= 300 ) {
        // Try to extract the Google API error message from the response body.
        $decoded = json_decode( $raw, true );
        $api_msg = $decoded['error']['message'] ?? $decoded['error']['status'] ?? '';
        $GLOBALS['seo_dash_google_last_error'] = "Google API error {$code}" . ( $api_msg ? ": {$api_msg}" : " — {$raw}" );
        return null;
    }

    return json_decode( $raw, true ) ?: null;
}

/**
 * Return and clear the last Google API error recorded by seo_dash_google_post().
 */
function seo_dash_google_last_error(): string {
    $err = $GLOBALS['seo_dash_google_last_error'] ?? '';
    unset( $GLOBALS['seo_dash_google_last_error'] );
    return $err;
}
