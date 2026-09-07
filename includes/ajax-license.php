<?php
/**
 * AJAX handlers — License (license system removed; handlers kept for compatibility)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_seo_dash_license_activate', function () {
    seo_dash_verify_admin_ajax();
    seo_dash_json_success( SEO_Dash_License::get_cached_status(), '✅ License active.' );
} );

add_action( 'wp_ajax_seo_dash_license_deactivate', function () {
    seo_dash_verify_admin_ajax();
    seo_dash_json_success( null, 'License active.' );
} );

add_action( 'wp_ajax_seo_dash_license_status', function () {
    seo_dash_verify_admin_ajax();
    seo_dash_json_success( SEO_Dash_License::get_cached_status() );
} );

add_action( 'wp_ajax_seo_dash_license_revalidate', function () {
    seo_dash_verify_admin_ajax();
    seo_dash_json_success( SEO_Dash_License::get_cached_status(), 'License active.' );
} );

add_action( 'wp_ajax_seo_dash_license_check_update', function () {
    seo_dash_verify_admin_ajax();
    seo_dash_json_success( null, 'You are running the latest version.' );
} );
