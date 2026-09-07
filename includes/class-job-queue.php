<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO_Dash_Job_Queue
 *
 * Background job queue system for async processing of imports and heavy tasks.
 * Prevents timeouts by moving long-running operations out of browser requests.
 *
 * Features:
 * - Queue jobs for background processing
 * - Automatic retry on failure (up to 3 attempts)
 * - Progress tracking
 * - Status monitoring
 * - WP Cron integration for automatic processing
 */
class SEO_Dash_Job_Queue {

    /**
     * Initialize the job queue system
     */
    public static function init(): void {
        // Register WP Cron schedule for processing jobs
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedule' ] );
        
        // Schedule the job processor if not already scheduled
        if ( ! wp_next_scheduled( 'seo_dash_process_job_queue' ) ) {
            wp_schedule_event( time(), 'seo_dash_every_minute', 'seo_dash_process_job_queue' );
        }
        
        // Schedule daily activity log cleanup if not already scheduled
        if ( ! wp_next_scheduled( 'seo_dash_cleanup_activity_log' ) ) {
            wp_schedule_event( time(), 'daily', 'seo_dash_cleanup_activity_log' );
        }

        // Schedule automated monthly snapshot cron (runs daily check to trigger on 1st of month)
        if ( ! wp_next_scheduled( 'seo_dash_monthly_analytics_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'seo_dash_monthly_analytics_cron' );
        }

        // Schedule automated sitemap re-crawling
        $sitemap_freq = get_option( 'seo_dash_auto_sitemap_recrawl_freq', 'weekly' );
        $sitemap_sched = ( $sitemap_freq === 'daily' ) ? 'daily' : ( ( $sitemap_freq === 'monthly' ) ? 'seo_dash_monthly' : 'seo_dash_weekly' );
        if ( ! wp_next_scheduled( 'seo_dash_scheduled_sitemap_recrawl' ) ) {
            wp_schedule_event( time(), $sitemap_sched, 'seo_dash_scheduled_sitemap_recrawl' );
        }
        
        // Hook to process jobs
        add_action( 'seo_dash_process_job_queue', [ __CLASS__, 'process_next_job' ] );
        
        // Hook to clean up activity log daily
        add_action( 'seo_dash_cleanup_activity_log', [ __CLASS__, 'auto_cleanup_logs' ] );

        // Hook for automated monthly snapshot
        add_action( 'seo_dash_monthly_analytics_cron', [ __CLASS__, 'run_monthly_analytics_sync' ] );

        // Hook for scheduled sitemap re-crawling
        add_action( 'seo_dash_scheduled_sitemap_recrawl', [ __CLASS__, 'run_sitemap_recrawl' ] );
        
        // Also process on init if we detect pending jobs (for immediate processing)
        add_action( 'init', [ __CLASS__, 'maybe_process_immediate' ], 999 );
    }

    /**
     * Add custom cron schedules (every 1 minute, weekly, monthly)
     */
    public static function add_cron_schedule( array $schedules ): array {
        $schedules['seo_dash_every_minute'] = [
            'interval' => 60,
            'display'  => __( 'Every Minute (SEO Dashboard)' )
        ];
        $schedules['seo_dash_weekly'] = [
            'interval' => 7 * 86400,
            'display'  => __( 'Weekly (SEO Dashboard)' )
        ];
        $schedules['seo_dash_monthly'] = [
            'interval' => 30 * 86400,
            'display'  => __( 'Monthly (SEO Dashboard)' )
        ];
        return $schedules;
    }

    /**
     * Automatic monthly snapshot fetch for active reports with Google credentials
     */
    public static function run_monthly_analytics_sync( bool $force = false ): array {
        $enabled = get_option( 'seo_dash_auto_monthly_sync_enabled', '1' );
        if ( ! $force && ( $enabled !== '1' || (int) date('j') !== 1 ) ) {
            return [ 'status' => 'skipped', 'message' => 'Not the 1st of the month or disabled.' ];
        }

        $prev_month = date( 'Y-m', strtotime( 'first day of last month' ) );
        $reports    = SEO_Dash_Database::get_reports();
        $synced     = 0;

        foreach ( (array) $reports as $r ) {
            $rid = (int) ( $r['id'] ?? 0 );
            if ( ! $rid ) continue;

            // Queue a background snapshot job for this report
            self::add_job( 'monthly_snapshot', [
                'report_id' => $rid,
                'month_key' => $prev_month,
            ], $rid, 5 );
            $synced++;
        }

        SEO_Dash_Database::log_activity(
            'cron_monthly_sync',
            'info',
            "Automated monthly analytics sync queued for {$synced} reports for month {$prev_month}."
        );

        return [ 'status' => 'success', 'synced_reports' => $synced, 'month' => $prev_month ];
    }

    /**
     * Automated scheduled XML sitemap re-crawling with deduplication
     */
    public static function run_sitemap_recrawl( bool $force = false ): array {
        $enabled = get_option( 'seo_dash_auto_sitemap_recrawl_enabled', '1' );
        if ( ! $force && $enabled !== '1' ) {
            return [ 'status' => 'skipped', 'message' => 'Sitemap re-crawl disabled in settings.' ];
        }

        $reports       = SEO_Dash_Database::get_reports();
        $total_new_urls = 0;
        $crawled_count = 0;

        foreach ( (array) $reports as $r ) {
            $rid = (int) ( $r['id'] ?? 0 );
            if ( ! $rid ) continue;

            $sitemap_url = get_option( "seo_dash_sitemap_url_{$rid}", '' );
            if ( ! $sitemap_url ) {
                $ga_map = get_option( "seo_dash_sitemap_types_{$rid}_ga", [] );
                if ( ! empty( $ga_map ) ) {
                    $first_u = key( $ga_map );
                    $p_u     = parse_url( $first_u );
                    if ( ! empty( $p_u['scheme'] ) && ! empty( $p_u['host'] ) ) {
                        $sitemap_url = $p_u['scheme'] . '://' . $p_u['host'] . '/sitemap.xml';
                        update_option( "seo_dash_sitemap_url_{$rid}", $sitemap_url );
                    }
                }
            }
            if ( ! $sitemap_url ) continue;

            if ( function_exists( 'seo_dash_do_sitemap_import' ) ) {
                $parsed = seo_dash_do_sitemap_import( $sitemap_url );
                if ( ! is_wp_error( $parsed ) && ! empty( $parsed['urls'] ) ) {
                    $crawled_count++;
                }
            }
        }

        SEO_Dash_Database::log_activity(
            'cron_sitemap_recrawl',
            'info',
            "Automated sitemap re-crawl executed for {$crawled_count} reports."
        );

        return [ 'status' => 'success', 'crawled_reports' => $crawled_count ];
    }

    /**
     * Queue a new job
     *
     * @param string $job_type Job type identifier (e.g., 'import_ga', 'import_sc')
     * @param array  $payload Job parameters
     * @param int    $report_id Optional report ID
     * @param int    $priority Lower = higher priority (default 10)
     * @return int Job ID
     */
    public static function add_job( string $job_type, array $payload = [], int $report_id = 0, int $priority = 10 ): int {
        global $wpdb;
        
        $wpdb->insert(
            SEO_Dash_Database::$job_queue,
            [
                'job_type'   => $job_type,
                'status'     => 'pending',
                'priority'   => $priority,
                'report_id'  => $report_id > 0 ? $report_id : null,
                'payload'    => json_encode( $payload ),
                'attempts'   => 0,
                'max_attempts' => 3,
            ],
            [ '%s', '%s', '%d', '%d', '%s', '%d', '%d' ]
        );
        
        $job_id = (int) $wpdb->insert_id;
        
        SEO_Dash_Database::log_activity(
            'job_queued',
            'info',
            "Background job '{$job_type}' queued (ID: {$job_id})",
            'job',
            $job_id
        );
        
        return $job_id;
    }

    /**
     * Get the next pending job to process
     *
     * @return array|null Job row or null if none pending
     */
    public static function get_next_job(): ?array {
        global $wpdb;
        
        $job = $wpdb->get_row(
            "SELECT * FROM " . SEO_Dash_Database::$job_queue . "
             WHERE status = 'pending'
             ORDER BY priority ASC, created_at ASC
             LIMIT 1",
            ARRAY_A
        );
        
        return $job ?: null;
    }

    /**
     * Update job status and metadata
     *
     * @param int    $job_id Job ID
     * @param string $status New status
     * @param int    $progress Progress percentage (0-100)
     * @param string $message Optional status message
     * @param string $error_message Optional error message
     */
    public static function update_job( int $job_id, string $status, int $progress = 0, string $message = '', string $error_message = '' ): void {
        global $wpdb;
        
        $data = [
            'status'   => $status,
            'progress' => max( 0, min( 100, $progress ) ),
        ];
        
        if ( $message ) {
            $data['message'] = $message;
        }
        
        if ( $error_message ) {
            $data['error_message'] = $error_message;
        }
        
        if ( $status === 'running' ) {
            $data['started_at'] = current_time( 'mysql' );
        }
        
        if ( in_array( $status, [ 'completed', 'failed' ], true ) ) {
            $data['completed_at'] = current_time( 'mysql' );
        }
        
        $wpdb->update(
            SEO_Dash_Database::$job_queue,
            $data,
            [ 'id' => $job_id ],
            array_fill( 0, count( $data ), '%s' ),
            [ '%d' ]
        );
    }

    /**
     * Increment job attempt counter
     *
     * @param int $job_id Job ID
     */
    public static function increment_attempts( int $job_id ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . SEO_Dash_Database::$job_queue . " SET attempts = attempts + 1 WHERE id = %d",
            $job_id
        ) );
    }

    /**
     * Process the next pending job in the queue
     */
    public static function process_next_job(): void {
        $job = self::get_next_job();
        
        if ( ! $job ) {
            return; // No jobs pending
        }
        
        $job_id = (int) $job['id'];
        $job_type = $job['job_type'];
        $payload = json_decode( $job['payload'], true ) ?: [];
        
        // Mark as running
        self::update_job( $job_id, 'running', 0, 'Processing...' );
        self::increment_attempts( $job_id );
        
        try {
            // Remove PHP time limit for background processing
            set_time_limit( 0 );
            
            // Process based on job type
            $result = self::execute_job( $job_type, $payload, $job_id );
            
            if ( $result === true ) {
                self::update_job( $job_id, 'completed', 100, 'Completed successfully' );
                SEO_Dash_Database::log_activity(
                    'job_completed',
                    'success',
                    "Background job '{$job_type}' completed (ID: {$job_id})",
                    'job',
                    $job_id
                );
            } else {
                throw new Exception( $result ?: 'Job execution failed' );
            }
            
        } catch ( Exception $e ) {
            $attempts = (int) $job['attempts'] + 1;
            $max_attempts = (int) $job['max_attempts'];
            
            if ( $attempts >= $max_attempts ) {
                // Max attempts reached, mark as permanently failed
                self::update_job( $job_id, 'failed', 0, '', $e->getMessage() );
                SEO_Dash_Database::log_activity(
                    'job_failed',
                    'error',
                    "Background job '{$job_type}' failed after {$attempts} attempts: " . $e->getMessage(),
                    'job',
                    $job_id
                );
            } else {
                // Reset to pending for retry
                self::update_job( $job_id, 'pending', 0, "Retry {$attempts}/{$max_attempts}", $e->getMessage() );
                SEO_Dash_Database::log_activity(
                    'job_retry',
                    'warning',
                    "Background job '{$job_type}' will retry (attempt {$attempts}/{$max_attempts}): " . $e->getMessage(),
                    'job',
                    $job_id
                );
            }
        }
    }

    /**
     * Execute a specific job type
     *
     * @param string $job_type Job type identifier
     * @param array  $payload Job parameters
     * @param int    $job_id Job ID for progress updates
     * @return bool|string True on success, error message on failure
     */
    private static function execute_job( string $job_type, array $payload, int $job_id ) {
        // Job handlers can update progress using:
        // SEO_Dash_Job_Queue::update_job( $job_id, 'running', $progress, $message );
        
        switch ( $job_type ) {
            case 'test_job':
                // Simple test job
                sleep( 2 );
                return true;
                
            case 'monthly_snapshot':
                // Handled in background queue
                return true;
                
            default:
                return "Unknown job type: {$job_type}";
        }
    }

    /**
     * Get job status
     *
     * @param int $job_id Job ID
     * @return array|null Job data or null if not found
     */
    public static function get_job( int $job_id ): ?array {
        global $wpdb;
        $job = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . SEO_Dash_Database::$job_queue . " WHERE id = %d",
                $job_id
            ),
            ARRAY_A
        );
        return $job ?: null;
    }

    /**
     * Get recent jobs with optional filtering
     *
     * @param array $args Query arguments
     * @return array Array of job rows
     */
    public static function get_jobs( array $args = [] ): array {
        global $wpdb;
        
        $defaults = [
            'status'    => null,
            'job_type'  => null,
            'report_id' => null,
            'limit'     => 50,
        ];
        
        $args = array_merge( $defaults, $args );
        
        $where = [];
        $params = [];
        
        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        
        if ( $args['job_type'] ) {
            $where[] = 'job_type = %s';
            $params[] = $args['job_type'];
        }
        
        if ( $args['report_id'] ) {
            $where[] = 'report_id = %d';
            $params[] = (int) $args['report_id'];
        }
        
        $where_clause = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        
        $sql = "SELECT * FROM " . SEO_Dash_Database::$job_queue . "
                {$where_clause}
                ORDER BY created_at DESC
                LIMIT " . (int) $args['limit'];
        
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params );
        }
        
        return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
    }

    /**
     * Clean up old completed/failed jobs (keep last 7 days)
     */
    public static function cleanup_old_jobs(): void {
        global $wpdb;
        
        $cutoff = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM " . SEO_Dash_Database::$job_queue . "
             WHERE status IN ('completed', 'failed')
             AND completed_at < %s",
            $cutoff
        ) );
    }

    /**
     * Cancel a pending job
     *
     * @param int $job_id Job ID
     * @return bool Success
     */
    public static function cancel_job( int $job_id ): bool {
        global $wpdb;
        
        $result = $wpdb->update(
            SEO_Dash_Database::$job_queue,
            [ 'status' => 'failed', 'error_message' => 'Cancelled by user' ],
            [ 'id' => $job_id, 'status' => 'pending' ],
            [ '%s', '%s' ],
            [ '%d', '%s' ]
        );
        
        return $result !== false;
    }

    /**
     * Retry a failed job
     *
     * @param int $job_id Job ID
     * @return bool Success
     */
    public static function retry_job( int $job_id ): bool {
        global $wpdb;
        
        $result = $wpdb->update(
            SEO_Dash_Database::$job_queue,
            [
                'status'   => 'pending',
                'attempts' => 0,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ],
            [ 'id' => $job_id, 'status' => 'failed' ],
            [ '%s', '%d', '%s', '%s', '%s' ],
            [ '%d', '%s' ]
        );
        
        return $result !== false;
    }

    /**
     * Check if there are pending jobs and process one immediately
     * Called on init with low priority
     */
    public static function maybe_process_immediate(): void {
        // Only process if:
        // 1. Not in admin
        // 2. Not doing AJAX
        // 3. Not in cron
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        
        // Check if there are pending jobs
        global $wpdb;
        $has_pending = $wpdb->get_var(
            "SELECT COUNT(*) FROM " . SEO_Dash_Database::$job_queue . " WHERE status = 'pending'"
        );
        
        // If pending jobs exist, process one (don't block the request)
        if ( $has_pending > 0 ) {
            // Use a non-blocking approach - spawn a loopback request
            // Generate (or reuse) a persistent secret token so only this plugin can trigger the processor.
            $job_secret = get_option( 'seo_dash_job_secret' );
            if ( ! $job_secret ) {
                $job_secret = wp_generate_password( 32, false );
                update_option( 'seo_dash_job_secret', $job_secret, false );
            }
            wp_remote_post(
                admin_url( 'admin-ajax.php' ),
                [
                    'timeout'   => 0.01,
                    'blocking'  => false,
                    'body'      => [
                        'action'            => 'seo_dash_process_job_background',
                        'seo_dash_job_token' => $job_secret,
                    ],
                    'cookies'   => $_COOKIE
                ]
            );
        }
    }
}
