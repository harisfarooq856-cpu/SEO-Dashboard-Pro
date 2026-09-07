<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO_Dash_Database
 *
 * Central class for:
 *  - Defining all custom table names
 *  - Creating / upgrading tables (called on activation and version bump)
 *  - Providing low-level CRUD helpers used by the rest of the plugin
 *
 * Table naming convention: {$wpdb->prefix}seodash_{name}
 */
class SEO_Dash_Database {

    // ── Table name constants ───────────────────────────────────────────────

    /** @var string Core report records */
    public static $reports = '';

    /** @var string Client/contact records */
    public static $clients = '';

    /** @var string Many-to-many: reports ↔ clients */
    public static $report_clients = '';

    /** @var string API credential sets (Google, Groq, etc.) */
    public static $integrations = '';

    /** @var string Report ↔ integration assignments */
    public static $report_integrations = '';

    /** @var string Google Analytics imported data rows */
    public static $data_ga = '';

    /** @var string Google Search Console imported data rows */
    public static $data_sc = '';

    /** @var string Leads / enquiries */
    public static $data_leads = '';

    /** @var string Click tracking (Leads sub-tab) */
    public static $data_click_tracking = '';

    /** @var string Backlink records */
    public static $data_backlinks = '';

    /** @var string Google Business Profile data */
    public static $data_gmb = '';

    /** @var string Google Business Profile posts */
    public static $data_gmb_posts = '';

    /** @var string Keyword rank tracking rows */
    public static $data_keywords = '';

    /** @var string Technical audit issue rows */
    public static $data_technical = '';

    /** @var string Service / blog page performance rows */
    public static $data_pages = '';

    /** @var string Plugin-wide settings (replaces wp_options for plugin keys) */
    public static $settings = '';

    /** @var string Security event log */
    public static $security_log = '';

    /** @var string Plugin activity log (CRUD, AJAX, errors) */
    public static $activity_log = '';
    
    /** @var string Background job queue for async processing */
    public static $job_queue = '';

    /** @var string Document approval records */
    public static $data_documents = '';


    // ── Boot (called once on plugin load) ─────────────────────────────────

    /**
     * Initialise static table-name properties.
     * Must be called before any DB work.  The main loader calls this via
     * the 'plugins_loaded' hook so $wpdb->prefix is already available.
     */
    public static function init(): void {
        global $wpdb;
        $p = $wpdb->prefix . 'seodash_';

        self::$reports             = $p . 'reports';
        self::$clients             = $p . 'clients';
        self::$report_clients      = $p . 'report_clients';
        self::$integrations        = $p . 'integrations';
        self::$report_integrations = $p . 'report_integrations';
        self::$data_ga             = $p . 'data_ga';
        self::$data_sc             = $p . 'data_sc';
        self::$data_leads          = $p . 'data_leads';
        self::$data_click_tracking = $p . 'data_click_tracking';
        self::$data_backlinks      = $p . 'data_backlinks';
        self::$data_gmb            = $p . 'data_gmb';
        self::$data_gmb_posts      = $p . 'data_gmb_posts';
        self::$data_keywords       = $p . 'data_keywords';
        self::$data_technical      = $p . 'data_technical';
        self::$data_pages          = $p . 'data_pages';
        self::$settings            = $p . 'settings';
        self::$security_log        = $p . 'security_log';
        self::$activity_log        = $p . 'activity_log';
        self::$job_queue           = $p . 'job_queue';
        self::$data_documents      = $p . 'data_documents';
    }

    // ── Table creation / upgrade ───────────────────────────────────────────

    /**
     * Create or upgrade all plugin tables.
     * Uses dbDelta — safe to call repeatedly; it only makes changes when needed.
     */
    public static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        // ── 1. Reports ─────────────────────────────────────────────────────
        // One row per client report (replaces the seo_reports CPT).
        dbDelta( "CREATE TABLE " . self::$reports . " (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title         VARCHAR(255)        NOT NULL DEFAULT '',
            status        VARCHAR(20)         NOT NULL DEFAULT 'publish',
            created_by    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            meta          LONGTEXT                     DEFAULT NULL COMMENT 'JSON blob for misc report meta',
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset;" );

        // ── 2. Clients ────────────────────────────────────────────────────
        // Contact/client directory.
        dbDelta( "CREATE TABLE " . self::$clients . " (
            id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id           BIGINT(20) UNSIGNED          DEFAULT NULL COMMENT 'Linked WP user, if any',
            name                 VARCHAR(255)        NOT NULL DEFAULT '',
            email                VARCHAR(255)                 DEFAULT NULL,
            company              VARCHAR(255)                 DEFAULT NULL,
            phone                VARCHAR(50)                  DEFAULT NULL,
            notes                TEXT                         DEFAULT NULL,
            password             VARCHAR(255)                 DEFAULT NULL COMMENT 'Plaintext password for display (admin only)',
            status               VARCHAR(20)         NOT NULL DEFAULT 'active',
            wp_page_id           BIGINT(20) UNSIGNED          DEFAULT NULL COMMENT 'Individual WP page created for this client',
            dashboard_url        VARCHAR(500)                 DEFAULT NULL COMMENT 'Auto-generated frontend page URL',
            shortcode            VARCHAR(255)                 DEFAULT NULL,
            allow_name_change    TINYINT(1)          NOT NULL DEFAULT 1,
            allow_email_change   TINYINT(1)          NOT NULL DEFAULT 1,
            allow_password_change TINYINT(1)         NOT NULL DEFAULT 1,
            allow_avatar_change  TINYINT(1)          NOT NULL DEFAULT 0,
            created_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY wp_user_id (wp_user_id),
            KEY email (email),
            KEY status (status)
        ) $charset;" );

        // ── 3. Report ↔ Client assignments ───────────────────────────────
        dbDelta( "CREATE TABLE " . self::$report_clients . " (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id  BIGINT(20) UNSIGNED NOT NULL,
            client_id  BIGINT(20) UNSIGNED NOT NULL,
            assigned_at DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY report_client (report_id, client_id),
            KEY report_id (report_id),
            KEY client_id (client_id)
        ) $charset;" );

        // ── 4. Integrations (API credentials) ────────────────────────────
        // Each row is a named credential set (e.g. "Client A – Google").
        dbDelta( "CREATE TABLE " . self::$integrations . " (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            label        VARCHAR(255)        NOT NULL DEFAULT '',
            type         VARCHAR(50)         NOT NULL DEFAULT '' COMMENT 'google_analytics | search_console | gmb | groq | psi',
            credentials  LONGTEXT                     DEFAULT NULL COMMENT 'AES-256 encrypted JSON of keys/tokens',
            created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type)
        ) $charset;" );

        // ── 5. Report ↔ Integration assignments ──────────────────────────
        dbDelta( "CREATE TABLE " . self::$report_integrations . " (
            id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id      BIGINT(20) UNSIGNED NOT NULL,
            integration_id BIGINT(20) UNSIGNED NOT NULL,
            scope          VARCHAR(50)         NOT NULL DEFAULT '' COMMENT 'ga | sc | gmb | groq | psi',
            PRIMARY KEY (id),
            UNIQUE KEY report_scope (report_id, scope),
            KEY report_id (report_id),
            KEY integration_id (integration_id)
        ) $charset;" );

        // ── 6. Google Analytics data ──────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_ga . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id   BIGINT(20) UNSIGNED NOT NULL,
            period_type VARCHAR(20)         NOT NULL DEFAULT 'monthly' COMMENT 'monthly | weekly | custom',
            month_key   VARCHAR(10)         NOT NULL DEFAULT '' COMMENT 'YYYY-MM',
            date_from   DATE                         DEFAULT NULL,
            date_to     DATE                         DEFAULT NULL,
            page_url    VARCHAR(1000)                DEFAULT NULL,
            page_title  VARCHAR(500)                 DEFAULT NULL,
            sessions    INT UNSIGNED        NOT NULL DEFAULT 0,
            users       INT UNSIGNED        NOT NULL DEFAULT 0,
            pageviews   INT UNSIGNED        NOT NULL DEFAULT 0,
            bounces     INT UNSIGNED        NOT NULL DEFAULT 0,
            goal_completions INT UNSIGNED   NOT NULL DEFAULT 0,
            trashed     TINYINT(1)          NOT NULL DEFAULT 0,
            imported_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key),
            KEY rep_month_url (report_id, month_key, page_url(191)),
            KEY rep_trashed (report_id, trashed),
            KEY rep_period_month (report_id, period_type, month_key),
            KEY rep_url (report_id, page_url(191)),
            KEY trashed (trashed)
        ) $charset;" );

        // ── 7. Google Search Console data ─────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_sc . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id   BIGINT(20) UNSIGNED NOT NULL,
            period_type VARCHAR(20)         NOT NULL DEFAULT 'monthly',
            month_key   VARCHAR(10)         NOT NULL DEFAULT '',
            date_from   DATE                         DEFAULT NULL,
            date_to     DATE                         DEFAULT NULL,
            query       VARCHAR(1000)                DEFAULT NULL COMMENT 'Search keyword',
            page_url    VARCHAR(1000)                DEFAULT NULL,
            clicks      INT UNSIGNED        NOT NULL DEFAULT 0,
            impressions INT UNSIGNED        NOT NULL DEFAULT 0,
            ctr         DECIMAL(8,4)        NOT NULL DEFAULT 0.0000,
            position    DECIMAL(8,2)        NOT NULL DEFAULT 0.00,
            trashed     TINYINT(1)          NOT NULL DEFAULT 0,
            imported_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key),
            KEY rep_month_url (report_id, month_key, page_url(191)),
            KEY rep_trashed (report_id, trashed),
            KEY rep_period_month (report_id, period_type, month_key),
            KEY rep_url (report_id, page_url(191)),
            KEY trashed (trashed)
        ) $charset;" );

        // ── 8. Leads ──────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_leads . " (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id    BIGINT(20) UNSIGNED NOT NULL,
            month_key    VARCHAR(10)         NOT NULL DEFAULT '',
            source       VARCHAR(100)                 DEFAULT NULL,
            name         VARCHAR(255)                 DEFAULT NULL,
            email        VARCHAR(255)                 DEFAULT NULL,
            phone        VARCHAR(50)                  DEFAULT NULL,
            zip          VARCHAR(50)                  DEFAULT NULL,
            message      TEXT                         DEFAULT NULL,
            status       VARCHAR(30)         NOT NULL DEFAULT 'new' COMMENT 'new | contacted | converted | lost',
            notes        TEXT                         DEFAULT NULL,
            trashed      TINYINT(1)          NOT NULL DEFAULT 0,
            lead_date    DATE                         DEFAULT NULL,
            lead_time    VARCHAR(20)                  DEFAULT NULL,
            page_url     VARCHAR(255)                 DEFAULT NULL,
            created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key),
            KEY status (status),
            KEY trashed (trashed)
        ) $charset;" );

        // ── 8b. Click Tracking (Leads sub-tab) ────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_click_tracking . " (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id    BIGINT(20) UNSIGNED NOT NULL,
            month_key    VARCHAR(10)         NOT NULL DEFAULT '',
            keyword_text VARCHAR(255)                 DEFAULT NULL COMMENT 'Text / Keyword',
            source_page  VARCHAR(1000)                DEFAULT NULL COMMENT 'Source Page',
            click_type   VARCHAR(100)                 DEFAULT NULL COMMENT 'Click Type',
            status       VARCHAR(30)         NOT NULL DEFAULT 'new' COMMENT 'new | contacted | converted | lost',
            click_date   DATE                         DEFAULT NULL COMMENT 'Submitteddate',
            click_time   VARCHAR(20)                  DEFAULT NULL,
            trashed      TINYINT(1)          NOT NULL DEFAULT 0,
            created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key),
            KEY status (status),
            KEY trashed (trashed)
        ) $charset;" );

        // ── 9. Backlinks ──────────────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_backlinks . " (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id     BIGINT(20) UNSIGNED NOT NULL,
            month_key     VARCHAR(10)         NOT NULL DEFAULT '',
            link_type     VARCHAR(20)         NOT NULL DEFAULT 'dofollow' COMMENT 'dofollow | nofollow | sponsored | ugc | guest_post | directory | social | forum | citation | press_release | infographic | broken_link | other',
            source_url    VARCHAR(1000)                DEFAULT NULL,
            domain_rating SMALLINT UNSIGNED             DEFAULT NULL COMMENT 'DA',
            page_authority SMALLINT UNSIGNED            DEFAULT NULL COMMENT 'PA',
            spam_score    TINYINT UNSIGNED              DEFAULT NULL COMMENT 'Spam %',
            live_link     VARCHAR(1000)                DEFAULT NULL COMMENT 'Live Link URL',
            anchor_text   VARCHAR(500)                 DEFAULT NULL COMMENT 'Keyword',
            target_url    VARCHAR(1000)                DEFAULT NULL COMMENT 'Target URL',
            found_date    DATE                         DEFAULT NULL,
            status        VARCHAR(20)         NOT NULL DEFAULT 'live' COMMENT 'live | lost | broken',
            trashed       TINYINT(1)          NOT NULL DEFAULT 0,
            created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key),
            KEY trashed (trashed),
            KEY link_type (link_type)
        ) $charset;" );

        // ── 10. Google Business Profile ────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_gmb . " (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id       BIGINT(20) UNSIGNED NOT NULL,
            month_key       VARCHAR(10)         NOT NULL DEFAULT '',
            views_search    INT UNSIGNED        NOT NULL DEFAULT 0,
            views_maps      INT UNSIGNED        NOT NULL DEFAULT 0,
            clicks_website  INT UNSIGNED        NOT NULL DEFAULT 0,
            clicks_directions INT UNSIGNED      NOT NULL DEFAULT 0,
            calls           INT UNSIGNED        NOT NULL DEFAULT 0,
            bookings        INT UNSIGNED        NOT NULL DEFAULT 0,
            posts_published INT UNSIGNED        NOT NULL DEFAULT 0,
            reviews_count   INT UNSIGNED        NOT NULL DEFAULT 0,
            reviews_avg     DECIMAL(3,2)        NOT NULL DEFAULT 0.00,
            trashed         TINYINT(1)          NOT NULL DEFAULT 0,
            imported_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key)
        ) $charset;" );

        // ── 10b. Google Business Profile Posts ────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_gmb_posts . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id   BIGINT(20) UNSIGNED NOT NULL,
            month_key   VARCHAR(10)         NOT NULL DEFAULT '',
            title       VARCHAR(500)                 DEFAULT NULL,
            post_url    VARCHAR(2000)                DEFAULT NULL,
            status      VARCHAR(20)         NOT NULL DEFAULT 'Published',
            trashed     TINYINT(1)          NOT NULL DEFAULT 0,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key),
            KEY trashed (trashed)
        ) $charset;" );

        // ── 11. Keywords ──────────────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_keywords . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id   BIGINT(20) UNSIGNED NOT NULL,
            month_key   VARCHAR(10)         NOT NULL DEFAULT '',
            keyword     VARCHAR(500)        NOT NULL DEFAULT '',
            url         VARCHAR(1000)                DEFAULT NULL,
            position    DECIMAL(8,2)                 DEFAULT NULL,
            prev_position DECIMAL(8,2)               DEFAULT NULL,
            search_volume INT UNSIGNED                DEFAULT NULL,
            trashed     TINYINT(1)          NOT NULL DEFAULT 0,
            recorded_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key),
            KEY trashed (trashed)
        ) $charset;" );

        // ── 12. Technical Audit ───────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_technical . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id   BIGINT(20) UNSIGNED NOT NULL,
            month_key   VARCHAR(10)         NOT NULL DEFAULT '',
            issue_type  VARCHAR(100)                 DEFAULT NULL COMMENT 'broken_link | missing_meta | slow_page | etc.',
            severity    VARCHAR(20)         NOT NULL DEFAULT 'medium' COMMENT 'low | medium | high | critical',
            url         VARCHAR(1000)                DEFAULT NULL,
            description TEXT                         DEFAULT NULL,
            status      VARCHAR(20)         NOT NULL DEFAULT 'open' COMMENT 'open | fixed | ignored',
            trashed     TINYINT(1)          NOT NULL DEFAULT 0,
            found_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key),
            KEY severity (severity),
            KEY status (status)
        ) $charset;" );

        // ── 13. Service / Blog Pages ──────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_pages . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id   BIGINT(20) UNSIGNED NOT NULL,
            month_key   VARCHAR(10)         NOT NULL DEFAULT '',
            page_type   VARCHAR(20)         NOT NULL DEFAULT 'service' COMMENT 'service | blog',
            url         VARCHAR(1000)                DEFAULT NULL,
            title       VARCHAR(500)                 DEFAULT NULL,
            sessions    INT UNSIGNED        NOT NULL DEFAULT 0,
            clicks      INT UNSIGNED        NOT NULL DEFAULT 0,
            impressions INT UNSIGNED        NOT NULL DEFAULT 0,
            position    DECIMAL(8,2)                 DEFAULT NULL,
            trashed     TINYINT(1)          NOT NULL DEFAULT 0,
            imported_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_month (report_id, month_key),
            KEY page_type (page_type)
        ) $charset;" );

        // ── 14. Plugin Settings ───────────────────────────────────────────
        // Flat key→value store; replaces scattered wp_options entries.
        dbDelta( "CREATE TABLE " . self::$settings . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(191)        NOT NULL,
            setting_val LONGTEXT                     DEFAULT NULL,
            autoload    TINYINT(1)          NOT NULL DEFAULT 1,
            updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset;" );

        // ── 15. Security Log ──────────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$security_log . " (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event      VARCHAR(100)        NOT NULL DEFAULT '',
            detail     VARCHAR(500)                 DEFAULT NULL,
            ip         VARCHAR(45)                  DEFAULT NULL,
            user_id    BIGINT(20) UNSIGNED           DEFAULT NULL,
            created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event (event),
            KEY created_at (created_at)
        ) $charset;" );

        // ── 16. Activity Log ──────────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$activity_log . " (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            level       VARCHAR(20)         NOT NULL DEFAULT 'info' COMMENT 'info|success|warning|error',
            action      VARCHAR(100)        NOT NULL DEFAULT '',
            object_type VARCHAR(50)                  DEFAULT NULL COMMENT 'report|client|integration|setting|data_ga|...',
            object_id   BIGINT(20) UNSIGNED           DEFAULT NULL,
            object_name VARCHAR(255)                  DEFAULT NULL,
            detail      TEXT                          DEFAULT NULL,
            user_id     BIGINT(20) UNSIGNED            DEFAULT NULL,
            user_name   VARCHAR(255)                   DEFAULT NULL,
            ip          VARCHAR(45)                    DEFAULT NULL,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY action (action),
            KEY object_type (object_type),
            KEY created_at (created_at)
        ) $charset;" );

        // ── 16b. Job Queue (Background Processing) ────────────────────────
        dbDelta( "CREATE TABLE " . self::$job_queue . " (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_type      VARCHAR(50)         NOT NULL COMMENT 'import_ga|import_sc|import_csv|etc',
            status        VARCHAR(20)         NOT NULL DEFAULT 'pending' COMMENT 'pending|running|completed|failed',
            priority      INT(11)             NOT NULL DEFAULT 10 COMMENT 'Lower = higher priority',
            report_id     BIGINT(20) UNSIGNED          DEFAULT NULL,
            payload       TEXT                         DEFAULT NULL COMMENT 'JSON parameters for the job',
            progress      INT(11)             NOT NULL DEFAULT 0 COMMENT 'Percentage 0-100',
            message       VARCHAR(500)                 DEFAULT NULL COMMENT 'Current status message',
            error_message TEXT                         DEFAULT NULL COMMENT 'Error details if failed',
            attempts      INT(11)             NOT NULL DEFAULT 0 COMMENT 'How many times job has been attempted',
            max_attempts  INT(11)             NOT NULL DEFAULT 3 COMMENT 'Max retry attempts',
            started_at    DATETIME                     DEFAULT NULL,
            completed_at  DATETIME                     DEFAULT NULL,
            created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY job_type (job_type),
            KEY report_id (report_id),
            KEY priority (priority),
            KEY created_at (created_at)
        ) $charset;" );

        // ── 17. Document Approvals ────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::$data_documents . " (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id    BIGINT(20) UNSIGNED NOT NULL,
            client_id    BIGINT(20) UNSIGNED          DEFAULT NULL COMMENT 'Target client ID',
            title        VARCHAR(255)        NOT NULL DEFAULT '' COMMENT 'Document display name',
            file_type    VARCHAR(20)         NOT NULL DEFAULT 'file' COMMENT 'file | url',
            file_url     VARCHAR(2000)                DEFAULT NULL COMMENT 'Uploaded file URL or external URL',
            file_name    VARCHAR(255)                 DEFAULT NULL COMMENT 'Original file name',
            sent_to_mail VARCHAR(255)                 DEFAULT NULL COMMENT 'Override email for notification',
            status       VARCHAR(20)         NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
            client_notes TEXT                         DEFAULT NULL COMMENT 'Client notes / feedback',
            admin_notes  TEXT                         DEFAULT NULL COMMENT 'Internal admin notes',
            notified_at  DATETIME                     DEFAULT NULL COMMENT 'When email was sent',
            actioned_at  DATETIME                     DEFAULT NULL COMMENT 'When client approved/rejected',
            created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY client_id (client_id),
            KEY status (status)
        ) $charset;" );

        // ── Column migrations for existing installs ──────────────────────────
        // dbDelta won't add missing columns to existing tables reliably,
        // so we do it explicitly. These are safe to run repeatedly.
        $clients_table = self::$clients;
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$clients_table}`" );
        if ( ! in_array( 'allow_name_change', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE `{$clients_table}` ADD COLUMN `allow_name_change` TINYINT(1) NOT NULL DEFAULT 1" );
        }
        if ( ! in_array( 'allow_email_change', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE `{$clients_table}` ADD COLUMN `allow_email_change` TINYINT(1) NOT NULL DEFAULT 1" );
        }
        if ( ! in_array( 'allow_password_change', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE `{$clients_table}` ADD COLUMN `allow_password_change` TINYINT(1) NOT NULL DEFAULT 1" );
        }
        if ( ! in_array( 'allow_avatar_change', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE `{$clients_table}` ADD COLUMN `allow_avatar_change` TINYINT(1) NOT NULL DEFAULT 0" );
        }

        // Ensure compound indices exist on data tables
        self::ensure_compound_indices();

        // Store the DB schema version so future upgrades can run migrations.
        update_option( 'seo_dash_db_version', SEO_DASH_VERSION );
    }

    /**
     * Ensure compound indices exist on performance-critical data tables.
     */
    public static function ensure_compound_indices(): void {
        global $wpdb;
        $tables = [
            self::$data_ga => [
                'rep_month_url'    => '(report_id, month_key, page_url(191))',
                'rep_trashed'      => '(report_id, trashed)',
                'rep_period_month' => '(report_id, period_type, month_key)',
                'rep_url'          => '(report_id, page_url(191))',
            ],
            self::$data_sc => [
                'rep_month_url'    => '(report_id, month_key, page_url(191))',
                'rep_trashed'      => '(report_id, trashed)',
                'rep_period_month' => '(report_id, period_type, month_key)',
                'rep_url'          => '(report_id, page_url(191))',
            ],
        ];

        foreach ( $tables as $tbl => $indices ) {
            if ( ! $tbl ) continue;
            // Check if table exists
            $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl ) );
            if ( ! $table_exists ) continue;

            $existing = $wpdb->get_col( "SHOW INDEX FROM `{$tbl}`" );
            $existing = array_unique( (array) $existing );
            foreach ( $indices as $idx_name => $idx_cols ) {
                if ( ! in_array( $idx_name, $existing, true ) ) {
                    $wpdb->query( "ALTER TABLE `{$tbl}` ADD INDEX `{$idx_name}` {$idx_cols}" );
                }
            }
        }
    }

    // ── Generic query helpers ──────────────────────────────────────────────

    /**
     * Get a single plugin setting value.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get_setting( string $key, $default = null ) {
        global $wpdb;
        $val = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_val FROM " . self::$settings . " WHERE setting_key = %s LIMIT 1",
            $key
        ) );
        return $val !== null ? $val : $default;
    }

    /**
     * Set (insert or update) a plugin setting value.
     *
     * @param string $key
     * @param mixed  $value  Scalars stored as-is; arrays/objects JSON-encoded.
     * @param bool   $autoload
     */
    public static function set_setting( string $key, $value, bool $autoload = true ): void {
        global $wpdb;
        if ( is_array( $value ) || is_object( $value ) ) {
            $value = wp_json_encode( $value );
        }
        $wpdb->replace(
            self::$settings,
            [
                'setting_key' => $key,
                'setting_val' => $value,
                'autoload'    => $autoload ? 1 : 0,
            ],
            [ '%s', '%s', '%d' ]
        );
    }

    /**
     * Delete a plugin setting.
     *
     * @param string $key
     */
    public static function delete_setting( string $key ): void {
        global $wpdb;
        $wpdb->delete( self::$settings, [ 'setting_key' => $key ], [ '%s' ] );
    }

    // ── Reports ────────────────────────────────────────────────────────────

    /**
     * Get all reports (non-trashed).
     *
     * @return array
     */
    public static function get_reports(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, title, status, created_by, created_at, updated_at, meta 
             FROM " . self::$reports . " WHERE status != 'trash' ORDER BY created_at DESC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get reports with pagination — used by the Reports list page.
     *
     * @param int    $limit   Number of rows to fetch.
     * @param int    $offset  Row offset.
     * @param string $search  Optional title search string.
     * @return array
     */
    public static function get_reports_paged( int $limit = 25, int $offset = 0, string $search = '' ): array {
        global $wpdb;
        if ( $search !== '' ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, title, status, created_by, created_at, updated_at, meta 
                     FROM " . self::$reports . " WHERE status != 'trash' AND title LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    '%' . $wpdb->esc_like( $search ) . '%',
                    $limit,
                    $offset
                ),
                ARRAY_A
            ) ?: [];
        }
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, status, created_by, created_at, updated_at, meta 
                 FROM " . self::$reports . " WHERE status != 'trash' ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Count all non-trashed reports, optionally filtered by title search.
     *
     * @param string $search  Optional title search string.
     * @return int
     */
    public static function count_reports( string $search = '' ): int {
        global $wpdb;
        if ( $search !== '' ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM " . self::$reports . " WHERE status != 'trash' AND title LIKE %s",
                    '%' . $wpdb->esc_like( $search ) . '%'
                )
            );
        }
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . self::$reports . " WHERE status != 'trash'"
        );
    }

    /**
     * Get a single report by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function get_report( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, title, status, created_by, created_at, updated_at, meta 
             FROM " . self::$reports . " WHERE id = %d LIMIT 1",
            $id
        ), ARRAY_A );
        if ( $row && ! empty( $row['meta'] ) ) {
            $row['meta'] = json_decode( $row['meta'], true ) ?: [];
        }
        return $row ?: null;
    }

    /**
     * Insert a new report.
     *
     * @param array $data  Keys: title, status, created_by, meta (array)
     * @return int|false  New row ID or false on failure.
     */
    public static function insert_report( array $data ) {
        global $wpdb;
        if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
            $data['meta'] = wp_json_encode( $data['meta'] );
        }
        $result = $wpdb->insert(
            self::$reports,
            [
                'title'      => sanitize_text_field( $data['title'] ?? '' ),
                'status'     => sanitize_key( $data['status'] ?? 'publish' ),
                'created_by' => intval( $data['created_by'] ?? get_current_user_id() ),
                'meta'       => $data['meta'] ?? null,
            ],
            [ '%s', '%s', '%d', '%s' ]
        );
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing report.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update_report( int $id, array $data ): bool {
        global $wpdb;
        if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
            $data['meta'] = wp_json_encode( $data['meta'] );
        }
        $fields  = [];
        $formats = [];
        $allowed = [ 'title' => '%s', 'status' => '%s', 'meta' => '%s' ];
        foreach ( $allowed as $col => $fmt ) {
            if ( array_key_exists( $col, $data ) ) {
                $fields[ $col ] = $data[ $col ];
                $formats[]      = $fmt;
            }
        }
        if ( empty( $fields ) ) return false;
        return (bool) $wpdb->update( self::$reports, $fields, [ 'id' => $id ], $formats, [ '%d' ] );
    }

    /**
     * Soft-delete (trash) a report.
     *
     * @param int $id
     * @return bool
     */
    public static function trash_report( int $id ): bool {
        return self::update_report( $id, [ 'status' => 'trash' ] );
    }

    /**
     * Permanently delete a report and all related data.
     *
     * @param int $id
     */
    public static function delete_report( int $id ): void {
        global $wpdb;
        $tables_with_report_id = [
            self::$report_clients,
            self::$report_integrations,
            self::$data_ga,
            self::$data_sc,
            self::$data_leads,
            self::$data_click_tracking,
            self::$data_backlinks,
            self::$data_gmb,
            self::$data_gmb_posts,
            self::$data_keywords,
            self::$data_technical,
            self::$data_pages,
        ];
        foreach ( $tables_with_report_id as $table ) {
            $wpdb->delete( $table, [ 'report_id' => $id ], [ '%d' ] );
        }
        $wpdb->delete( self::$reports, [ 'id' => $id ], [ '%d' ] );
    }

    // ── Clients ────────────────────────────────────────────────────────────

    /**
     * Get all active clients.
     *
     * @return array
     */
    public static function get_clients(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, wp_user_id, name, email, company, phone, notes, password, status, 
                    wp_page_id, dashboard_url, shortcode, allow_name_change, allow_email_change, 
                    allow_password_change, allow_avatar_change, created_at, updated_at 
             FROM " . self::$clients . " WHERE status != 'deleted' ORDER BY name ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get clients with pagination — used by the Clients list page.
     *
     * @param int $limit   Number of rows to fetch.
     * @param int $offset  Row offset.
     * @return array
     */
    public static function get_clients_paged( int $limit = 5, int $offset = 0 ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, wp_user_id, name, email, company, phone, notes, password, status, 
                        wp_page_id, dashboard_url, shortcode, allow_name_change, allow_email_change, 
                        allow_password_change, allow_avatar_change, created_at, updated_at 
                 FROM " . self::$clients . " WHERE status != 'deleted' ORDER BY name ASC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Count all active (non-deleted) clients.
     *
     * @return int
     */
    public static function count_clients(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . self::$clients . " WHERE status != 'deleted'"
        );
    }

    /**
     * Get a single client by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function get_client( int $id ): ?array {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT id, wp_user_id, name, email, company, phone, notes, password, status, 
                    wp_page_id, dashboard_url, shortcode, allow_name_change, allow_email_change, 
                    allow_password_change, allow_avatar_change, created_at, updated_at 
             FROM " . self::$clients . " WHERE id = %d LIMIT 1",
            $id
        ), ARRAY_A ) ?: null;
    }

    /**
     * Get client by WP user ID.
     *
     * @param int $wp_user_id
     * @return array|null
     */
    public static function get_client_by_user( int $wp_user_id ): ?array {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT id, wp_user_id, name, email, company, phone, notes, password, status, 
                    wp_page_id, dashboard_url, shortcode, allow_name_change, allow_email_change, 
                    allow_password_change, allow_avatar_change, created_at, updated_at 
             FROM " . self::$clients . " WHERE wp_user_id = %d LIMIT 1",
            $wp_user_id
        ), ARRAY_A ) ?: null;
    }

    /**
     * Insert a new client.
     *
     * @param array $data
     * @return int|false
     */
    public static function insert_client( array $data ) {
        global $wpdb;
        $result = $wpdb->insert(
            self::$clients,
            [
                'wp_user_id'    => intval( $data['wp_user_id'] ?? 0 ) ?: null,
                'wp_page_id'    => intval( $data['wp_page_id'] ?? 0 ) ?: null,
                'name'          => sanitize_text_field( $data['name'] ?? '' ),
                'email'         => sanitize_email( $data['email'] ?? '' ),
                'company'       => sanitize_text_field( $data['company'] ?? '' ),
                'phone'         => sanitize_text_field( $data['phone'] ?? '' ),
                'notes'         => sanitize_textarea_field( $data['notes'] ?? '' ),
                'status'        => sanitize_key( $data['status'] ?? 'active' ),
                'dashboard_url' => esc_url_raw( $data['dashboard_url'] ?? '' ),
                'shortcode'     => sanitize_text_field( $data['shortcode'] ?? '' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing client.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update_client( int $id, array $data ): bool {
        global $wpdb;
        $allowed = [
            'wp_user_id'            => '%d',
            'wp_page_id'            => '%d',
            'name'                  => '%s',
            'email'                 => '%s',
            'company'               => '%s',
            'phone'                 => '%s',
            'notes'                 => '%s',
            'status'                => '%s',
            'dashboard_url'         => '%s',
            'shortcode'             => '%s',
            'password'              => '%s',
            'allow_name_change'     => '%d',
            'allow_email_change'    => '%d',
            'allow_password_change' => '%d',
            'allow_avatar_change'   => '%d',
        ];
        $fields  = [];
        $formats = [];
        foreach ( $allowed as $col => $fmt ) {
            if ( array_key_exists( $col, $data ) ) {
                $fields[ $col ] = $data[ $col ];
                $formats[]      = $fmt;
            }
        }
        if ( empty( $fields ) ) return false;
        return (bool) $wpdb->update( self::$clients, $fields, [ 'id' => $id ], $formats, [ '%d' ] );
    }

    /**
     * Soft-delete a client.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_client( int $id ): bool {
        return self::update_client( $id, [ 'status' => 'deleted' ] );
    }

    // ── Report ↔ Client assignments ────────────────────────────────────────

    /**
     * Get all client IDs assigned to a report.
     *
     * @param int $report_id
     * @return int[]
     */
    public static function get_report_client_ids( int $report_id ): array {
        global $wpdb;
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT client_id FROM " . self::$report_clients . " WHERE report_id = %d",
            $report_id
        ) ) );
    }

    /**
     * Get all reports assigned to a client.
     *
     * @param int $client_id
     * @return array
     */
    public static function get_client_report_ids( int $client_id ): array {
        global $wpdb;
        // Exclude trashed reports — a report that was soft-deleted (status='trash')
        // should no longer appear as an assigned report for the client, even though
        // the report_clients link row still exists. Without this filter, trashed
        // "ghost" reports can become the client's default/active report (report_ids[0])
        // and show up in the SWITCH REPORT list as an empty duplicate.
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT rc.report_id FROM " . self::$report_clients . " rc
             INNER JOIN " . self::$reports . " r ON r.id = rc.report_id
             WHERE rc.client_id = %d AND r.status != 'trash'",
            $client_id
        ) ) );
    }

    /**
     * Assign a client to a report (idempotent).
     *
     * @param int $report_id
     * @param int $client_id
     * @return bool
     */
    public static function assign_client( int $report_id, int $client_id ): bool {
        global $wpdb;
        // Use INSERT IGNORE so duplicate doesn't throw an error.
        return (bool) $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO " . self::$report_clients . " (report_id, client_id) VALUES (%d, %d)",
            $report_id,
            $client_id
        ) );
    }

    /**
     * Remove a client from a report.
     *
     * @param int $report_id
     * @param int $client_id
     * @return bool
     */
    public static function unassign_client( int $report_id, int $client_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            self::$report_clients,
            [ 'report_id' => $report_id, 'client_id' => $client_id ],
            [ '%d', '%d' ]
        );
    }

    /**
     * Remove all clients from a report.
     *
     * @param int $report_id
     * @return bool
     */
    public static function unassign_all_clients( int $report_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::$report_clients, [ 'report_id' => $report_id ], [ '%d' ] );
    }

    // ── Integrations ───────────────────────────────────────────────────────

    /**
     * Get all integrations.
     *
     * @return array
     */
    public static function get_integrations(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, label, type, credentials, created_at, updated_at 
             FROM " . self::$integrations . " ORDER BY label ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get a single integration by ID (decrypts credentials).
     *
     * @param int $id
     * @return array|null
     */
    public static function get_integration( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, label, type, credentials, created_at, updated_at 
             FROM " . self::$integrations . " WHERE id = %d LIMIT 1",
            $id
        ), ARRAY_A );
        if ( ! $row ) return null;
        if ( ! empty( $row['credentials'] ) ) {
            $decrypted = seo_dash_sec_decrypt( $row['credentials'] );
            $row['credentials'] = json_decode( $decrypted, true ) ?: [];
        }
        return $row;
    }

    /**
     * Get the integration assigned to a report for a given scope.
     *
     * @param int    $report_id
     * @param string $scope  e.g. 'ga', 'sc', 'groq'
     * @return array|null  Full integration row with decrypted credentials.
     */
    public static function get_report_integration( int $report_id, string $scope ): ?array {
        global $wpdb;
        $integration_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT integration_id FROM " . self::$report_integrations . "
             WHERE report_id = %d AND scope = %s LIMIT 1",
            $report_id,
            $scope
        ) );
        if ( ! $integration_id ) return null;
        return self::get_integration( intval( $integration_id ) );
    }

    /**
     * Get all integrations assigned to a report.
     *
     * @param int $report_id
     * @return array
     */
    public static function get_report_integrations( int $report_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT integration_id, scope FROM " . self::$report_integrations . " WHERE report_id = %d",
            $report_id
        ), ARRAY_A );
        if ( empty( $rows ) ) return [];

        $integrations = [];
        foreach ( $rows as $r ) {
            $iid = intval( $r['integration_id'] );
            $int = self::get_integration( $iid );
            if ( $int ) {
                $int['assigned_scope'] = $r['scope'];
                $integrations[] = $int;
            }
        }
        return $integrations;
    }

    /**
     * Save (insert or update) an integration.
     *
     * @param array $data  Keys: id (optional), label, type, credentials (plain array)
     * @return int|false
     */
    public static function save_integration( array $data ) {
        global $wpdb;
        $credentials = $data['credentials'] ?? [];
        if ( is_array( $credentials ) ) {
            $credentials = wp_json_encode( $credentials );
        }
        $credentials_enc = seo_dash_sec_encrypt( $credentials );

        if ( ! empty( $data['id'] ) ) {
            $wpdb->update(
                self::$integrations,
                [
                    'label'       => sanitize_text_field( $data['label'] ?? '' ),
                    'type'        => sanitize_key( $data['type'] ?? '' ),
                    'credentials' => $credentials_enc,
                ],
                [ 'id' => intval( $data['id'] ) ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
            return intval( $data['id'] );
        }

        $result = $wpdb->insert(
            self::$integrations,
            [
                'label'       => sanitize_text_field( $data['label'] ?? '' ),
                'type'        => sanitize_key( $data['type'] ?? '' ),
                'credentials' => $credentials_enc,
            ],
            [ '%s', '%s', '%s' ]
        );
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Delete an integration and its report assignments.
     *
     * @param int $id
     */
    public static function delete_integration( int $id ): void {
        global $wpdb;
        $wpdb->delete( self::$report_integrations, [ 'integration_id' => $id ], [ '%d' ] );
        $wpdb->delete( self::$integrations,         [ 'id'             => $id ], [ '%d' ] );
    }

    /**
     * Assign an integration to a report for a specific scope.
     *
     * @param int    $report_id
     * @param int    $integration_id
     * @param string $scope
     */
    public static function assign_integration( int $report_id, int $integration_id, string $scope ): void {
        global $wpdb;
        $wpdb->replace(
            self::$report_integrations,
            [
                'report_id'      => $report_id,
                'integration_id' => $integration_id,
                'scope'          => sanitize_key( $scope ),
            ],
            [ '%d', '%d', '%s' ]
        );
    }

    // ── Generic data table helpers ─────────────────────────────────────────

    /**
     * Get rows from any data table for a report + month.
     *
     * @param string $table   One of self::$data_ga, self::$data_sc, etc.
     * @param int    $report_id
     * @param string $month_key  'YYYY-MM' or '' for all months.
     * @param bool   $include_trashed
     * @return array
     */
    public static function get_data_rows( string $table, int $report_id, string $month_key = '', bool $include_trashed = false, int $limit = 0, int $offset = 0, string $order = 'ASC' ): array {
        global $wpdb;
        
        // Determine which columns to select based on table
        // Only fetch columns actually used by the frontend
        $columns = self::get_table_columns( $table );
        $select_clause = $columns ? implode( ', ', $columns ) : '*';
        
        $sql    = "SELECT {$select_clause} FROM {$table} WHERE report_id = %d";
        $params = [ $report_id ];
        if ( $month_key !== '' ) {
            $sql      .= " AND month_key = %s";
            $params[]  = $month_key;
        }
        if ( ! $include_trashed ) {
            $sql .= " AND trashed = 0";
        }
        $order = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY id {$order}";
        
        // Add pagination if limit is specified
        if ( $limit > 0 ) {
            $sql .= " LIMIT %d";
            $params[] = $limit;
            if ( $offset > 0 ) {
                $sql .= " OFFSET %d";
                $params[] = $offset;
            }
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ) ?: [];
    }
    
    /**
     * Get specific columns for a data table (avoids SELECT *)
     *
     * @param string $table Table name
     * @return array Column names to select
     */
    private static function get_table_columns( string $table ): array {
        if ( empty( self::$data_ga ) ) self::init();
        
        // Map tables to their essential columns (excludes internal/unused columns)
        switch ( $table ) {
            case self::$data_ga:
                return [
                    'id', 'report_id', 'period_type', 'month_key', 'date_from', 'date_to',
                    'page_url', 'page_title', 'sessions', 'users', 'pageviews', 
                    'bounces', 'goal_completions', 'trashed'
                    // Excludes: imported_at (not shown to clients)
                ];
                
            case self::$data_sc:
                return [
                    'id', 'report_id', 'period_type', 'month_key', 'date_from', 'date_to',
                    'query', 'page_url', 'clicks', 'impressions', 'ctr', 'position', 'trashed'
                    // Excludes: imported_at
                ];
                
            case self::$data_leads:
                return [
                    'id', 'report_id', 'month_key', 'source', 'name', 'email', 'phone',
                    'zip', 'message', 'status', 'notes', 'trashed', 'lead_date', 
                    'lead_time', 'page_url', 'created_at', 'updated_at'
                ];
                
            case self::$data_click_tracking:
                return [
                    'id', 'report_id', 'month_key', 'keyword_text', 'source_page', 'click_type', 'status',
                    'click_date', 'click_time', 'trashed', 'created_at', 'updated_at'
                ];
                
            case self::$data_backlinks:
                return [
                    'id', 'report_id', 'month_key', 'link_type', 'source_url',
                    'domain_rating', 'page_authority', 'spam_score', 'live_link',
                    'anchor_text', 'target_url', 'found_date', 'status', 'trashed', 'created_at'
                    // Actual schema: link_type, source_url, domain_rating, page_authority, etc.
                ];
                
            case self::$data_gmb:
                return [
                    'id', 'report_id', 'month_key',
                    'calls', 'bookings', 'clicks_website', 'clicks_directions',
                    'views_search', 'views_maps', 'trashed'
                ];
                
            case self::$data_gmb_posts:
                return [
                    'id', 'report_id', 'month_key', 'title', 'post_url', 
                    'status', 'trashed', 'created_at'
                    // Actual schema: title, post_url, status (not post_title, post_content, post_type, published_at)
                ];
                
            case self::$data_keywords:
                return [
                    'id', 'report_id', 'month_key', 'keyword', 'url',
                    'position', 'prev_position', 'search_volume', 'trashed', 'recorded_at'
                    // Actual schema: keyword, url, position, prev_position, search_volume, recorded_at
                ];
                
            case self::$data_technical:
                return [
                    'id', 'report_id', 'month_key', 'issue_type', 'severity',
                    'url', 'description', 'status', 'trashed', 'found_at'
                    // Actual schema: issue_type, severity, url, description, status, found_at
                ];
                
            case self::$data_pages:
                return [
                    'id', 'report_id', 'page_type', 'page_url', 'page_title', 'trashed'
                ];
                
            case self::$data_documents:
                return [
                    'id', 'report_id', 'client_id', 'title', 'file_type', 'file_url',
                    'file_name', 'sent_to_mail', 'status', 'client_notes', 'admin_notes',
                    'created_at', 'updated_at'
                ];
                
            default:
                // For unknown tables, fall back to SELECT * (safety)
                return [];
        }
    }
    
    /**
     * Get total count of rows from any data table for a report + month.
     * Used for pagination calculations.
     *
     * @param string $table   One of self::$data_ga, self::$data_sc, etc.
     * @param int    $report_id
     * @param string $month_key  'YYYY-MM' or '' for all months.
     * @param bool   $include_trashed
     * @return int
     */
    public static function get_data_rows_count( string $table, int $report_id, string $month_key = '', bool $include_trashed = false ): int {
        global $wpdb;
        $sql    = "SELECT COUNT(*) FROM {$table} WHERE report_id = %d";
        $params = [ $report_id ];
        if ( $month_key !== '' ) {
            $sql      .= " AND month_key = %s";
            $params[]  = $month_key;
        }
        if ( ! $include_trashed ) {
            $sql .= " AND trashed = 0";
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
    }

    /**
     * Get aggregate KPI totals for the backlinks table using SQL-level aggregation
     * (COUNT/SUM/AVG), so the numbers reflect the entire dataset regardless of how
     * many rows are currently displayed on a paginated admin/client table.
     *
     * @param int $report_id
     * @return array{total:int, live:int, dofollow:int, avg_da:int}
     */
    public static function get_backlinks_kpis( int $report_id ): array {
        global $wpdb;
        $table = self::$data_backlinks;

        // Link types counted toward the "Dofollow Links" KPI (passes link equity).
        $dofollow_types = [ 'dofollow', 'guest_post', 'press_release', 'directory', 'citation' ];
        $type_placeholders = implode( ', ', array_fill( 0, count( $dofollow_types ), '%s' ) );

        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'live' THEN 1 ELSE 0 END) AS live,
                    SUM(CASE WHEN link_type IN ({$type_placeholders}) THEN 1 ELSE 0 END) AS dofollow,
                    AVG(domain_rating) AS avg_da
                FROM {$table}
                WHERE report_id = %d AND trashed = 0";

        $params = array_merge( $dofollow_types, [ $report_id ] );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( $sql, ...$params ), ARRAY_A );

        return [
            'total'    => (int) ( $row['total'] ?? 0 ),
            'live'     => (int) ( $row['live'] ?? 0 ),
            'dofollow' => (int) ( $row['dofollow'] ?? 0 ),
            'avg_da'   => isset( $row['avg_da'] ) && $row['avg_da'] !== null ? (int) round( (float) $row['avg_da'] ) : 0,
        ];
    }

    /**
     * Get grouped page rows for GA or SC tables with SQL-level aggregation.
     * Groups by page_url and aggregates metrics for each period_type into a single row per URL.
     * This avoids loading thousands of raw rows into PHP memory.
     *
     * @param string $table       One of self::$data_ga or self::$data_sc
     * @param int    $report_id   Report ID
     * @param string $month_key   'YYYY-MM' or '' for all months
     * @param int    $limit       Number of URLs to return (pagination)
     * @param int    $offset      Offset for pagination
     * @param string $scope       'ga', 'service', 'blog', 'sc', 'service_sc', or 'blog_sc'
     * @return array Array of grouped rows with period data aggregated
     */
    public static function get_grouped_page_rows( string $table, int $report_id, string $month_key = '', int $limit = 50, int $offset = 0, string $scope = 'ga' ): array {
        global $wpdb;
        
        // Determine if this is GA or SC based on scope
        $is_sc = in_array( $scope, [ 'sc', 'service_sc', 'blog_sc' ], true );
        
        // Define the period types we need to aggregate
        $period_types = [ 'monthly', '7d', '30d', '90d', 'overall' ];
        
        // Build conditional aggregation for each metric and period type
        if ( $is_sc ) {
            // SC metrics: clicks, impressions, ctr, position
            $metrics = [ 'clicks', 'impressions', 'ctr', 'position' ];
        } else {
            // GA metrics: sessions, users, pageviews, bounces, goal_completions
            $metrics = [ 'sessions', 'users', 'pageviews', 'bounces', 'goal_completions' ];
        }
        
        // Build SELECT clause with conditional aggregation for each metric + period combination
        $select_parts = [
            'page_url',
            'month_key',
            'report_id'
        ];
        
        // SC tables don't have page_title, they use the query field
        if ( $is_sc ) {
            $select_parts[] = 'MAX(query) as page_title';
        } else {
            $select_parts[] = 'MAX(page_title) as page_title';
        }
        
        foreach ( $period_types as $period ) {
            $period_match = "period_type = '{$period}'";
            if ( $period === '30d' ) {
                $period_match = "period_type IN ('30d', 'monthly', 'month')";
            } elseif ( $period === 'monthly' ) {
                $period_match = "period_type IN ('monthly', '30d', 'month')";
            } elseif ( $period === '7d' ) {
                $period_match = "period_type IN ('7d', '7days')";
            } elseif ( $period === '90d' ) {
                $period_match = "period_type IN ('90d', '90days')";
            } elseif ( $period === 'overall' ) {
                $period_match = "period_type IN ('overall', 'all', 'total')";
            }
            foreach ( $metrics as $metric ) {
                $select_parts[] = "MAX(CASE WHEN {$period_match} THEN {$metric} ELSE 0 END) as {$period}_{$metric}";
            }
        }
        
        $select_clause = implode( ', ', $select_parts );
        
        // Build WHERE clause
        $sql = "SELECT {$select_clause} FROM {$table} WHERE report_id = %d AND trashed = 0";
        $params = [ $report_id ];
        
        if ( $month_key !== '' ) {
            // Specific month filter — include month_key in GROUP BY so we get that month's snapshot data
            $sql .= " AND month_key = %s";
            $params[] = $month_key;
            $sql .= " GROUP BY page_url, month_key, report_id";
        } else {
            // No month filter — group by page_url ONLY so each URL returns exactly ONE row
            // regardless of how many months of data exist. Without this, 245 URLs × 50 months
            // = 12,250 grouped rows which overflows the LIMIT and silently drops URLs.
            // MAX() across all months gives the best (most recent/highest) snapshot value per period.
            $sql .= " GROUP BY page_url, report_id";
        }
        
        // Order by page_url for consistent results
        $sql .= " ORDER BY page_url ASC";
        
        // Add pagination
        if ( $limit > 0 ) {
            $sql .= " LIMIT %d";
            $params[] = $limit;
            if ( $offset > 0 ) {
                $sql .= " OFFSET %d";
                $params[] = $offset;
            }
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $grouped_rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ) ?: [];
        
        // Transform the flat aggregated rows into the nested structure expected by the frontend
        // Each row should have: url, title, type (added later), data => [period_type => [metrics]]
        $result = [];
        foreach ( $grouped_rows as $row ) {
            $url = $row['page_url'];
            
            // For SC tables, page_title contains the query; for GA tables it's the actual page title
            $title = ! empty( $row['page_title'] ) ? $row['page_title'] : $url;
            
            $result_row = [
                'url' => $url,
                'title' => $title,
                'type' => 'other', // Will be set by the caller
                'data' => []
            ];
            
            // Reconstruct the period data structure
            foreach ( $period_types as $period ) {
                $period_data = [
                    'period_type' => $period,
                    'page_url' => $url
                ];
                
                // Add page_title for compatibility (for SC this will be the query)
                if ( ! $is_sc ) {
                    $period_data['page_title'] = $row['page_title'];
                }
                
                foreach ( $metrics as $metric ) {
                    $period_data[ $metric ] = $row[ "{$period}_{$metric}" ] ?? 0;
                }
                
                $result_row['data'][ $period ] = $period_data;
            }
            
            $result[] = $result_row;
        }
        
        return $result;
    }

    /**
     * Get total count of distinct page URLs for grouped page rows.
     * Used for pagination calculations.
     *
     * @param string $table      One of self::$data_ga or self::$data_sc
     * @param int    $report_id  Report ID
     * @param string $month_key  'YYYY-MM' or '' for all months
     * @return int Total number of distinct page URLs
     */
    public static function get_grouped_page_rows_count( string $table, int $report_id, string $month_key = '' ): int {
        global $wpdb;
        
        $sql = "SELECT COUNT(DISTINCT page_url) FROM {$table} WHERE report_id = %d AND trashed = 0";
        $params = [ $report_id ];
        
        if ( $month_key !== '' ) {
            $sql .= " AND month_key = %s";
            $params[] = $month_key;
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
    }

    /**
     * Insert a batch of rows into a data table using bulk INSERT for performance.
     * Each row must already be sanitized.
     *
     * Uses batching to handle large datasets safely:
     * - Processes rows in chunks of 500 (configurable)
     * - Single INSERT query per batch (30-50x faster than individual inserts)
     * - Handles 50,000+ rows without timeouts
     * - Safe within MySQL max_allowed_packet limits
     *
     * @param string $table
     * @param array  $rows    Array of associative arrays (same keys for every row).
     * @param int    $batch_size Number of rows per batch (default 500).
     * @return int  Number of rows inserted.
     */
    public static function insert_data_rows( string $table, array $rows, int $batch_size = 500 ): int {
        global $wpdb;
        if ( empty( $rows ) ) return 0;
        
        $total_inserted = 0;
        
        // Split rows into batches to stay within MySQL limits
        $batches = array_chunk( $rows, $batch_size );
        
        foreach ( $batches as $batch ) {
            // Get column names from first row
            $first_row = reset( $batch );
            if ( ! is_array( $first_row ) || empty( $first_row ) ) continue;
            
            $columns = array_keys( $first_row );
            $column_count = count( $columns );
            
            // Build the bulk INSERT query
            $placeholders = [];
            $values = [];
            
            foreach ( $batch as $row ) {
                // Create placeholders for this row: (%s, %s, %s, ...)
                $row_placeholders = array_fill( 0, $column_count, '%s' );
                $placeholders[] = '(' . implode( ', ', $row_placeholders ) . ')';
                
                // Add row values in same order as columns
                foreach ( $columns as $col ) {
                    $values[] = $row[ $col ] ?? null;
                }
            }
            
            // Build final query: INSERT INTO table (col1, col2, ...) VALUES (?, ?), (?, ?), ...
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES %s",
                $table,
                implode( ', ', array_map( 'esc_sql', $columns ) ),
                implode( ', ', $placeholders )
            );
            
            // Execute bulk insert
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->query( $wpdb->prepare( $sql, $values ) );
            
            if ( $result !== false ) {
                $total_inserted += $result;
            }
        }
        
        return $total_inserted;
    }

    /**
     * Soft-delete (trash) a single row by ID.
     *
     * @param string $table
     * @param int    $id
     * @return bool
     */
    public static function trash_row( string $table, int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->update( $table, [ 'trashed' => 1 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
    }

    /**
     * Restore a trashed row.
     *
     * @param string $table
     * @param int    $id
     * @return bool
     */
    public static function restore_row( string $table, int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->update( $table, [ 'trashed' => 0 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
    }

    /**
     * Permanently delete a single row.
     *
     * @param string $table
     * @param int    $id
     * @return bool
     */
    public static function delete_row( string $table, int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Delete all rows for a report + month from a data table.
     *
     * @param string $table
     * @param int    $report_id
     * @param string $month_key
     * @return bool
     */
    public static function clear_month( string $table, int $report_id, string $month_key ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $table,
            [ 'report_id' => $report_id, 'month_key' => $month_key ],
            [ '%d', '%s' ]
        );
    }

    /**
     * Get distinct month_keys available for a report in a data table.
     *
     * @param string $table
     * @param int    $report_id
     * @return string[]  e.g. ['2025-01', '2025-02']
     */
    public static function get_months( string $table, int $report_id ): array {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT month_key FROM {$table} WHERE report_id = %d AND trashed = 0 ORDER BY month_key DESC",
            $report_id
        ) ) ?: [];
    }

    // ── Security log ───────────────────────────────────────────────────────

    /**
     * Append an entry to the security log.
     *
     * @param string $event
     * @param string $detail
     */
    public static function log_security_event( string $event, string $detail = '' ): void {
        global $wpdb;
        $wpdb->insert(
            self::$security_log,
            [
                'event'   => sanitize_key( $event ),
                'detail'  => sanitize_text_field( $detail ),
                'ip'      => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
                'user_id' => get_current_user_id() ?: null,
            ],
            [ '%s', '%s', '%s', '%d' ]
        );
    }

    /**
     * Write a plugin activity log entry.
     *
     * @param string      $action       Short slug, e.g. 'report_created'.
     * @param string      $level        'info' | 'success' | 'warning' | 'error'
     * @param string      $detail       Human-readable description.
     * @param string|null $object_type  e.g. 'report', 'client', 'integration'
     * @param int|null    $object_id
     * @param string|null $object_name  e.g. report title
     */
    public static function log_activity(
        string  $action,
        string  $level       = 'info',
        string  $detail      = '',
        ?string $object_type = null,
        ?int    $object_id   = null,
        ?string $object_name = null
    ): void {
        global $wpdb;
        if ( empty( self::$activity_log ) ) self::init();

        $user_id   = get_current_user_id() ?: null;
        $user_name = null;
        if ( $user_id ) {
            $u = get_userdata( $user_id );
            $user_name = $u ? $u->display_name : null;
        }

        $wpdb->insert(
            self::$activity_log,
            [
                'level'       => in_array( $level, ['info','success','warning','error'], true ) ? $level : 'info',
                'action'      => sanitize_key( $action ),
                'object_type' => $object_type ? sanitize_key( $object_type ) : null,
                'object_id'   => $object_id,
                'object_name' => $object_name ? sanitize_text_field( $object_name ) : null,
                'detail'      => sanitize_textarea_field( $detail ),
                'user_id'     => $user_id,
                'user_name'   => $user_name ? sanitize_text_field( $user_name ) : null,
                'ip'          => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ]
        );
    }

    // ── Activity Log Cleanup ───────────────────────────────────────────────

    /**
     * Clean up old activity log entries based on retention period
     *
     * @param int $days Number of days to keep (default 60)
     * @return int Number of rows deleted
     */
    public static function cleanup_activity_log( int $days = 60 ): int {
        global $wpdb;
        if ( empty( self::$activity_log ) ) self::init();

        $cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $result = $wpdb->query( $wpdb->prepare(
            "DELETE FROM " . self::$activity_log . " WHERE created_at < %s",
            $cutoff
        ) );

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Clean up activity log entries by level (e.g., delete all 'info' entries older than X days)
     *
     * @param string $level Log level: 'info', 'success', 'warning', 'error'
     * @param int    $days Number of days to keep
     * @return int Number of rows deleted
     */
    public static function cleanup_activity_log_by_level( string $level, int $days = 30 ): int {
        global $wpdb;
        if ( empty( self::$activity_log ) ) self::init();

        $cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $result = $wpdb->query( $wpdb->prepare(
            "DELETE FROM " . self::$activity_log . " WHERE level = %s AND created_at < %s",
            $level,
            $cutoff
        ) );

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Get activity log row count and size statistics
     *
     * @return array Statistics including count, oldest entry, table size
     */
    public static function get_activity_log_stats(): array {
        global $wpdb;
        if ( empty( self::$activity_log ) ) self::init();

        // Get row count
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::$activity_log );

        // Get oldest entry date
        $oldest = $wpdb->get_var( "SELECT MIN(created_at) FROM " . self::$activity_log );

        // Get table size (approximate)
        $table_name = str_replace( $wpdb->prefix, '', self::$activity_log );
        $size_query = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                table_rows
             FROM information_schema.TABLES 
             WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            self::$activity_log
        ), ARRAY_A );

        return [
            'total_rows'  => $count,
            'oldest_date' => $oldest,
            'size_mb'     => $size_query['size_mb'] ?? 0,
            'table_rows'  => $size_query['table_rows'] ?? 0,
        ];
    }
}

// ── Initialise table names as soon as the plugin is loaded ────────────────
add_action( 'plugins_loaded', [ 'SEO_Dash_Database', 'init' ], 1 );
