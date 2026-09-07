<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Job Queue AJAX Handlers
 */

// Background job processor (non-blocking trigger)
add_action( 'wp_ajax_seo_dash_process_job_background', function () {
    // Process one job then exit
    SEO_Dash_Job_Queue::process_next_job();
    wp_die();
} );

add_action( 'wp_ajax_nopriv_seo_dash_process_job_background', function () {
    // Verify internal secret token before processing — blocks unauthenticated public requests.
    $expected = get_option( 'seo_dash_job_secret' );
    $received  = $_POST['seo_dash_job_token'] ?? '';
    if ( ! $expected || ! hash_equals( (string) $expected, (string) $received ) ) {
        wp_die( 'Forbidden', 403 );
    }
    SEO_Dash_Job_Queue::process_next_job();
    wp_die();
} );

// Get job status
add_action( 'wp_ajax_seo_dash_get_job_status', function () {
    seo_dash_verify_admin_ajax();
    
    $job_id = intval( $_POST['job_id'] ?? 0 );
    if ( ! $job_id ) seo_dash_json_error( 'Missing job ID.' );
    
    $job = SEO_Dash_Job_Queue::get_job( $job_id );
    if ( ! $job ) seo_dash_json_error( 'Job not found.' );
    
    seo_dash_json_success( $job );
} );

// Get all jobs
add_action( 'wp_ajax_seo_dash_get_jobs', function () {
    seo_dash_verify_admin_ajax();
    
    $status = sanitize_key( $_POST['status'] ?? '' );
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $limit = intval( $_POST['limit'] ?? 50 );
    
    $args = [
        'limit' => min( 100, $limit )
    ];
    
    if ( $status ) {
        $args['status'] = $status;
    }
    
    if ( $report_id ) {
        $args['report_id'] = $report_id;
    }
    
    $jobs = SEO_Dash_Job_Queue::get_jobs( $args );
    seo_dash_json_success( [ 'jobs' => $jobs ] );
} );

// Cancel a job
add_action( 'wp_ajax_seo_dash_cancel_job', function () {
    seo_dash_verify_admin_ajax();
    
    $job_id = intval( $_POST['job_id'] ?? 0 );
    if ( ! $job_id ) seo_dash_json_error( 'Missing job ID.' );
    
    $success = SEO_Dash_Job_Queue::cancel_job( $job_id );
    
    if ( $success ) {
        seo_dash_json_success( null, 'Job cancelled.' );
    } else {
        seo_dash_json_error( 'Could not cancel job. It may already be running or completed.' );
    }
} );

// Retry a failed job
add_action( 'wp_ajax_seo_dash_retry_job', function () {
    seo_dash_verify_admin_ajax();
    
    $job_id = intval( $_POST['job_id'] ?? 0 );
    if ( ! $job_id ) seo_dash_json_error( 'Missing job ID.' );
    
    $success = SEO_Dash_Job_Queue::retry_job( $job_id );
    
    if ( $success ) {
        seo_dash_json_success( null, 'Job queued for retry.' );
    } else {
        seo_dash_json_error( 'Could not retry job. It may not be in failed status.' );
    }
} );

// Queue a test job (for testing the system)
add_action( 'wp_ajax_seo_dash_queue_test_job', function () {
    seo_dash_verify_admin_ajax();
    
    $job_id = SEO_Dash_Job_Queue::add_job( 'test_job', [ 'test' => true ] );
    
    seo_dash_json_success( 
        [ 'job_id' => $job_id ],
        "Test job queued (ID: {$job_id}). It will process in the background."
    );
} );

// Clean up old jobs
add_action( 'wp_ajax_seo_dash_cleanup_jobs', function () {
    seo_dash_verify_admin_ajax();
    
    SEO_Dash_Job_Queue::cleanup_old_jobs();
    
    seo_dash_json_success( null, 'Old completed/failed jobs cleaned up (7+ days old).' );
} );

// Trigger manual run of monthly analytics snapshot sync
add_action( 'wp_ajax_seo_dash_trigger_monthly_sync', function () {
    seo_dash_verify_admin_ajax();
    $res = SEO_Dash_Job_Queue::run_monthly_analytics_sync( true );
    seo_dash_json_success( $res, "Monthly analytics snapshot sync queued for {$res['synced_reports']} reports." );
} );

// Trigger manual run of sitemap re-crawling
add_action( 'wp_ajax_seo_dash_trigger_sitemap_recrawl', function () {
    seo_dash_verify_admin_ajax();
    $res = SEO_Dash_Job_Queue::run_sitemap_recrawl( true );
    seo_dash_json_success( $res, "Sitemap re-crawl completed across {$res['crawled_reports']} reports." );
} );
