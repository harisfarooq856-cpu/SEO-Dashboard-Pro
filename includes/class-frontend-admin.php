<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO_Dash_Frontend_Admin
 *
 * Renders the full SEO management dashboard on the frontend.
 * Shortcode: [seo_admin_dashboard]
 *
 * URL routing (query params):
 *   ?seo_page=reports              → reports list
 *   ?seo_page=report&id=5          → single report (with ?seo_tab=ga|sc|leads…)
 *   ?seo_page=clients              → clients list
 *   ?seo_page=integrations         → API credentials
 *   ?seo_page=settings             → global settings
 *   (no param)                     → home/overview
 */
class SEO_Dash_Frontend_Admin {

    private static string $base_url = '';

    public static function init(): void {
        add_shortcode( 'seo_admin_dashboard', [ __CLASS__, 'render' ] );
        add_action( 'wp_enqueue_scripts',     [ __CLASS__, 'enqueue' ] );
        add_filter( 'template_include',       [ __CLASS__, 'template' ] );
        add_action( 'template_redirect',      [ __CLASS__, 'no_cache' ] );
    }

    // ── Template takeover ──────────────────────────────────────────────────

    public static function template( string $tpl ): string {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'seo_admin_dashboard' ) ) {
            return $tpl;
        }
        // Strip theme stylesheets.
        add_action( 'wp_print_styles', function () {
            global $wp_styles;
            if ( ! $wp_styles ) return;
            $whitelist = [ 'seo-dash-', 'admin-bar', 'media', 'dashicons', 'buttons' ];
            foreach ( $wp_styles->queue as $h ) {
                $keep = false;
                foreach ( $whitelist as $w ) {
                    if ( strpos( $h, $w ) !== false ) {
                        $keep = true;
                        break;
                    }
                }
                if ( ! $keep ) {
                    wp_dequeue_style( $h );
                } else {
                    error_log('SEO DASH KEEPING STYLE: ' . $h);
                }
            }
        }, 99 );
        return SEO_DASH_PATH . 'includes/template-portal.php';
    }

    public static function no_cache(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'seo_admin_dashboard' ) ) return;
        nocache_headers();
        if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
    }

    // ── Assets ─────────────────────────────────────────────────────────────

    public static function enqueue(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'seo_admin_dashboard' ) ) return;

        wp_enqueue_media();
        wp_enqueue_style( 'media-views' );
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'buttons' );

        wp_enqueue_style( 'seo-dash-fonts', 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap', [], null );
        wp_enqueue_style( 'seo-dash-admin-app', SEO_DASH_URL . 'assets/css/admin-app.css', [], filemtime( SEO_DASH_PATH . 'assets/css/admin-app.css' ) );

        // Move jQuery + migrate to <head> so inline scripts in the page body
        // can use jQuery/$ without a "jQuery is not defined" error.
        // WP registers jQuery with in_footer=true by default; we override that here.
        wp_scripts()->add_data( 'jquery',         'group', 0 );
        wp_scripts()->add_data( 'jquery-core',    'group', 0 );
        wp_scripts()->add_data( 'jquery-migrate', 'group', 0 );
        wp_enqueue_script( 'jquery' );

        wp_enqueue_script( 'seo-dash-admin-app', SEO_DASH_URL . 'assets/js/admin-app.js', [ 'jquery' ], filemtime( SEO_DASH_PATH . 'assets/js/admin-app.js' ), true );

        wp_localize_script( 'seo-dash-admin-app', 'seoDash', [
            'ajax'      => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'seo_dash_admin' ),
            'base_url'  => get_permalink( get_option( 'seo_dash_admin_page_id' ) ),
            'client_url'=> get_permalink( get_option( 'seo_dash_client_page_id' ) ),
            'page'      => sanitize_key( $_GET['seo_page'] ?? '' ),
            'report_id' => intval( $_GET['id'] ?? 0 ),
            'tab'       => sanitize_key( $_GET['seo_tab'] ?? 'overview' ),
        ] );

        // Output seoDash in the <head> so page inline scripts can access it
        // before footer scripts (admin-app.js) load.
        $inline_obj = wp_json_encode( [
            'ajax'      => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'seo_dash_admin' ),
            'base_url'  => get_permalink( get_option( 'seo_dash_admin_page_id' ) ),
            'client_url'=> get_permalink( get_option( 'seo_dash_client_page_id' ) ),
            'page'      => sanitize_key( $_GET['seo_page'] ?? '' ),
            'report_id' => intval( $_GET['id'] ?? 0 ),
            'tab'       => sanitize_key( $_GET['seo_tab'] ?? 'overview' ),
        ] );
        add_action( 'wp_head', function() use ( $inline_obj ) {
            echo '<script>var seoDash=' . $inline_obj . ';</script>' . "\n";
            // Inline theme script — runs instantly before paint to prevent flash
            echo '<script>(function(){try{var t=localStorage.getItem("seo_dash_admin_theme")||"dark";if(t==="light"){document.documentElement.setAttribute("data-seo-theme","light");}}catch(e){}})();</script>' . "\n";
        }, 5 );

        // Auto nonce-refresh: silently refresh nonce and retry if any AJAX call returns nonce_expired.
        add_action( 'wp_footer', function() {
            echo '<script>(function($){$(document).ajaxSuccess(function(ev,xhr,settings,r){if(r&&r.success===false&&r.data&&r.data.nonce_expired){var orig=settings;$.post(seoDash.ajax,{action:"seo_dash_refresh_nonce"},function(res){if(res&&res.success&&res.data&&res.data.nonce){seoDash.nonce=res.data.nonce;var d=orig.data||"";if(typeof d==="string"){d=d.replace(/nonce=[^&]*/,"nonce="+encodeURIComponent(seoDash.nonce));}else if(typeof d==="object"&&d!==null){d.nonce=seoDash.nonce;}$.ajax($.extend({},orig,{data:d}));}});}});})(jQuery);</script>' . "\n";
        }, 1 );
    }

    // ── Shortcode entry ────────────────────────────────────────────────────

    public static function render(): string {
        // Must be logged in as admin.
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return self::render_access_denied();
        }

        self::$base_url = get_permalink( get_option( 'seo_dash_admin_page_id' ) ) ?: get_permalink();

        $page = sanitize_key( $_GET['seo_page'] ?? '' );

        ob_start();

        // jQuery is enqueued in <head> via enqueue() above (group=0).
        // The Load More button uses a pure-vanilla onclick (window.__seoLoadMoreHandler)
        // that needs no jQuery, so it works regardless of when jQuery loads.

        echo '<div class="seo-app" id="seo-app">';
        self::render_navbar( $page );

        if ( $page === 'report' && ! empty( $_GET['id'] ) ) {
            self::render_report_detail( intval( $_GET['id'] ) );
        } elseif ( $page === 'reports' ) {
            self::render_page_reports();
        } elseif ( $page === 'clients' ) {
            self::render_page_clients();
        } elseif ( $page === 'integrations' ) {
            self::render_page_integrations();
        } elseif ( $page === 'settings' ) {
            self::render_page_settings();
        } elseif ( $page === 'log' ) {
            self::render_page_log();
        } elseif ( $page === 'documentation' ) {
            self::render_page_documentation();
        } elseif ( $page === 'license' ) {
            self::render_page_license();
        } else {
            self::render_page_home();
        }

        // Global modals (always present in DOM).
        self::render_modal_new_report();
        self::render_modal_new_client();
        self::render_modal_new_integration();

        echo '</div>';
        echo '<div id="seo-toast"></div>';

        return ob_get_clean();
    }

    // ── Navbar ─────────────────────────────────────────────────────────────

    private static function render_navbar( string $page ): void {
        $user        = wp_get_current_user();
        $base        = self::$base_url;
        $client_url  = get_permalink( get_option( 'seo_dash_client_page_id' ) );
        $brand       = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
        $logo        = SEO_Dash_Database::get_setting( 'brand_logo', '' );
        $logo_dark   = SEO_Dash_Database::get_setting( 'brand_logo_dark', '' );
        $nav_items = [
            ''              => [ 'icon' => '', 'label' => 'Home'         ],
            'reports'       => [ 'icon' => '', 'label' => 'Reports'      ],
            'clients'       => [ 'icon' => '👥', 'label' => 'Clients'   ],
            'integrations'  => [ 'icon' => '', 'label' => 'Integrations' ],
            'settings'      => [ 'icon' => '', 'label' => 'Settings'     ],
            'log'           => [ 'icon' => '', 'label' => 'Log'           ],
            'documentation' => [ 'icon' => '📚', 'label' => 'Docs'      ],
            'license'       => [ 'icon' => '🔑', 'label' => 'License'   ],
        ];

        // Normalise active key: report detail → reports.
        $active = ( $page === 'report' ) ? 'reports' : $page;
        ?>
        <nav class="seo-navbar">
            <div class="seo-nav-brand">
                <?php if ( $logo ) : ?>
                    <img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $brand ); ?>" class="seo-brand-logo">
                <?php else : ?>
                    <span class="seo-brand-icon" style="font-size:20px;">&#128200;</span>
                <?php endif; ?>
                <div>
                    <div class="seo-brand-name"><?php echo esc_html( $brand ); ?></div>
                    <div class="seo-brand-sub">SEO Admin Dashboard</div>
                </div>
            </div>

            <div class="seo-nav-links">
                <?php foreach ( $nav_items as $key => $item ) :
                    $url = $key ? add_query_arg( 'seo_page', $key, $base ) : $base;
                    $cls = ( $active === $key ) ? 'seo-nav-link seo-nav-link-active' : 'seo-nav-link';
                ?>
                <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $cls; ?>">
                    <span><?php echo $item['icon']; ?></span>
                    <span><?php echo esc_html( $item['label'] ); ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="seo-nav-right">
                <?php if ( $client_url ) : ?>
                <a href="<?php echo esc_url( $client_url ); ?>" target="_blank" class="seo-btn seo-btn-ghost seo-btn-sm">
                    Client Portal
                </a>
                <?php endif; ?>
                <div class="seo-user-chip">
                    <div class="seo-user-av"><?php echo esc_html( strtoupper( substr( $user->display_name, 0, 1 ) ) ); ?></div>
                    <span><?php echo esc_html( $user->display_name ); ?></span>
                </div>
                <button class="seo-theme-toggle" id="seo-theme-btn" title="Toggle light/dark">&#127769;</button>
            </div>
        </nav>
        <?php
    }

    // ══════════════════════════════════════════════════════════════════════
    // PAGE: HOME
    // ══════════════════════════════════════════════════════════════════════

    private static function render_page_home(): void {
        $reports  = SEO_Dash_Database::get_reports();
        $clients  = SEO_Dash_Database::get_clients();
        $base     = self::$base_url;

        global $wpdb;
        $total_leads     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_leads     . " WHERE trashed=0" );
        $total_backlinks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_backlinks . " WHERE trashed=0" );
        $total_intgs     = count( SEO_Dash_Database::get_integrations() );

        include SEO_DASH_PATH . 'includes/views/admin/page-home.php';
    }

    // ══════════════════════════════════════════════════════════════════════
    // PAGE: REPORTS LIST
    // ══════════════════════════════════════════════════════════════════════

    private static function render_page_reports(): void {
        $reports       = SEO_Dash_Database::get_reports_paged( 5, 0 );
        $reports_total = SEO_Dash_Database::count_reports();
        $base          = self::$base_url;
        include SEO_DASH_PATH . 'includes/views/admin/page-reports.php';
    }

    // ══════════════════════════════════════════════════════════════════════
    // PAGE: SINGLE REPORT DETAIL (tabbed)
    // ══════════════════════════════════════════════════════════════════════

    private static function render_report_detail( int $report_id ): void {
        $report = SEO_Dash_Database::get_report( $report_id );
        if ( ! $report ) {
            echo '<div class="seo-not-found"><p>Report not found. <a href="' . esc_url( add_query_arg( 'seo_page', 'reports', self::$base_url ) ) . '">← Back to Reports</a></p></div>';
            return;
        }

        $tab  = sanitize_key( $_GET['seo_tab'] ?? 'overview' );
        $base = self::$base_url;
        $meta = is_array( $report['meta'] ) ? $report['meta'] : ( json_decode( $report['meta'] ?? '{}', true ) ?: [] );

        $tab_urls = [];
        $tabs     = [ 'overview', 'client_dashboard', 'database', 'analytics', 'sc', 'service', 'blog', 'gmb', 'technical', 'backlinks', 'leads', 'clients', 'integrations' ];
        foreach ( $tabs as $t ) {
            $tab_urls[ $t ] = add_query_arg( [ 'seo_page' => 'report', 'id' => $report_id, 'seo_tab' => $t ], $base );
        }
        $reports_url = add_query_arg( 'seo_page', 'reports', $base );

        // Assigned clients & integrations.
        $assigned_client_ids = SEO_Dash_Database::get_report_client_ids( $report_id );
        $all_clients         = SEO_Dash_Database::get_clients();
        $all_integrations    = SEO_Dash_Database::get_integrations();

        // Load global integrations (new system).
        if ( ! function_exists('seo_dash_get_global_integrations') ) {
            require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
        }
        $all_global_integrations = seo_dash_get_global_integrations();

        // Load global integration assignments for this report.
        $report_global_intgs = get_option( 'seo_dash_report_global_intg_' . $report_id, [] );
        if ( ! is_array( $report_global_intgs ) ) $report_global_intgs = [];

        global $wpdb;
        $ri_rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT scope, integration_id FROM " . SEO_Dash_Database::$report_integrations . " WHERE report_id=%d",
            $report_id
        ), ARRAY_A ) ?: [];
        $report_intgs = [];
        foreach ( $ri_rows as $ri ) $report_intgs[ $ri['scope'] ] = intval( $ri['integration_id'] );

        include SEO_DASH_PATH . 'includes/views/admin/page-report-detail.php';
    }

    // ══════════════════════════════════════════════════════════════════════
    // PAGE: CLIENTS
    // ══════════════════════════════════════════════════════════════════════

    private static function render_page_clients(): void {
        $clients       = SEO_Dash_Database::get_clients_paged( 5, 0 );
        $clients_total = SEO_Dash_Database::count_clients();
        $reports = SEO_Dash_Database::get_reports();
        $base    = self::$base_url;
        include SEO_DASH_PATH . 'includes/views/admin/page-clients.php';
    }


    // ══════════════════════════════════════════════════════════════════════
    // PAGE: INTEGRATIONS
    // ══════════════════════════════════════════════════════════════════════

    private static function render_page_integrations(): void {
        // Global integrations are stored in wp_options (same structure as old plugin).
        // The helper function is defined in ajax-global-integrations.php which is
        // loaded by class-api.php before rendering, so it's always available.
        if ( ! function_exists('seo_dash_get_global_integrations') ) {
            require_once SEO_DASH_PATH . 'includes/ajax-global-integrations.php';
        }
        include SEO_DASH_PATH . 'includes/views/admin/page-integrations.php';
    }

    // ══════════════════════════════════════════════════════════════════════
    // PAGE: LOG
    // ══════════════════════════════════════════════════════════════════════

    private static function render_page_log(): void {
        include SEO_DASH_PATH . 'includes/views/admin/page-log.php';
    }

    // ══════════════════════════════════════════════════════════════════════
    // PAGE: SETTINGS
    // ══════════════════════════════════════════════════════════════════════

    private static function render_page_settings(): void {
        include SEO_DASH_PATH . 'includes/views/admin/page-settings.php';
    }

    // ══════════════════════════════════════════════════════════════════════
    // PAGE: DOCUMENTATION
    // ══════════════════════════════════════════════════════════════════════

    private static function render_page_documentation(): void {
        include SEO_DASH_PATH . 'includes/views/admin/page-documentation.php';
    }

    // ══════════════════════════════════════════════════════════════════════
    // LICENSE PAGE (frontend admin tab)
    // ══════════════════════════════════════════════════════════════════════

    private static function render_page_license(): void {
        $status      = SEO_Dash_License::get_cached_status();
        $is_licensed = ! empty( $status['valid'] );
        $key_stored  = SEO_Dash_License::get_key() !== '';
        $base        = self::$base_url;
        ?>
        <div class="seo-page">
            <div class="seo-page-hd">
                <div>
                    <h1 class="seo-page-title">🔑 License</h1>
                    <p class="seo-page-subtitle">Manage your SEO Dashboard Pro license</p>
                </div>
            </div>

            <!-- Status card -->
            <?php if ( $is_licensed ) : ?>
            <div style="display:flex;align-items:center;gap:16px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:14px;padding:20px 24px;margin-bottom:24px;">
                <div style="width:48px;height:48px;background:#22c55e;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">✅</div>
                <div>
                    <div style="font-weight:700;font-size:16px;color:#14532d;">License Active</div>
                    <div style="font-size:13px;color:#166534;margin-top:2px;"><?php echo esc_html( $status['message'] ?? 'Your license is valid.' ); ?></div>
                    <?php if ( ! empty( $status['expires_at'] ) ) : ?>
                        <div style="font-size:12px;color:#15803d;margin-top:4px;">Expires: <?php echo esc_html( $status['expires_at'] ); ?></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $status['key_display'] ) ) : ?>
                        <div style="font-size:12px;color:#166534;margin-top:4px;font-family:monospace;letter-spacing:1px;"><?php echo esc_html( $status['key_display'] ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ( $key_stored && $status ) : ?>
            <div style="display:flex;align-items:center;gap:16px;background:linear-gradient(135deg,#fef2f2,#fee2e2);border:1px solid #fca5a5;border-radius:14px;padding:20px 24px;margin-bottom:24px;">
                <div style="width:48px;height:48px;background:#ef4444;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">❌</div>
                <div>
                    <div style="font-weight:700;font-size:16px;color:#7f1d1d;">License Invalid</div>
                    <div style="font-size:13px;color:#dc2626;margin-top:2px;"><?php echo esc_html( $status['message'] ?? 'Could not validate your license.' ); ?></div>
                </div>
            </div>
            <?php else : ?>
            <div style="display:flex;align-items:center;gap:16px;background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;border-radius:14px;padding:20px 24px;margin-bottom:24px;">
                <div style="width:48px;height:48px;background:#f59e0b;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🔓</div>
                <div>
                    <div style="font-weight:700;font-size:16px;color:#713f12;">Free Mode</div>
                    <div style="font-size:13px;color:#92400e;margin-top:2px;">Enter your license key below to unlock all features and auto-updates.</div>
                </div>
            </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

                <!-- Left: Activate / manage -->
                <div style="display:flex;flex-direction:column;gap:20px;">

                    <!-- Key input panel -->
                    <div class="seo-panel">
                        <div class="seo-panel-hd"><h2>🔑 License Key</h2></div>
                        <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:16px;">
                            <div class="seo-field">
                                <label>Enter License Key</label>
                                <div style="display:flex;gap:8px;">
                                    <div style="position:relative;flex:1;display:flex;align-items:center;">
                                        <input type="text" id="seo-lic-key-input" class="seo-in"
                                               placeholder="XXXX-XXXX-XXXX-XXXX"
                                               style="font-family:monospace;font-size:14px;letter-spacing:1px;width:100%;<?php echo $is_licensed ? 'padding-right:34px;' : ''; ?>"
                                               value="<?php echo $key_stored ? esc_attr( SEO_Dash_License::get_key() ) : ''; ?>"
                                               autocomplete="off" spellcheck="false">
                                        <span id="seo-lic-key-icon" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#22c55e;font-size:16px;line-height:1;<?php echo $is_licensed ? '' : 'display:none;'; ?>">✅</span>
                                    </div>
                                </div>
                                <span class="seo-field-hint">Paste the license key you received after purchase.</span>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button class="seo-btn seo-btn-primary" id="seo-lic-activate-btn">
                                    ✅ Validate &amp; Activate
                                </button>
                                <?php if ( $key_stored ) : ?>
                                <button class="seo-btn seo-btn-ghost" id="seo-lic-deactivate-btn"
                                        style="color:var(--c-red,#ef4444);border-color:var(--c-red,#ef4444);">
                                    🗑 Remove License
                                </button>
                                <?php endif; ?>
                            </div>
                            <div id="seo-lic-activate-msg" style="font-size:13px;"></div>
                        </div>
                    </div>

                    <!-- Update check panel -->
                    <div class="seo-panel">
                        <div class="seo-panel-hd"><h2>🔄 Plugin Updates</h2></div>
                        <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:12px;">
                            <p style="margin:0;font-size:13px;color:var(--c-muted);">
                                With an active license, updates appear automatically in <strong>WP Admin → Dashboard → Updates</strong>.<br>
                                Current version: <strong><?php echo esc_html( SEO_DASH_VERSION ); ?></strong>
                            </p>
                            <div>
                                <button class="seo-btn seo-btn-ghost" id="seo-lic-check-update-btn">
                                    Check for Updates Now
                                </button>
                            </div>
                            <div id="seo-lic-update-msg" style="font-size:13px;"></div>
                        </div>
                    </div>

                </div><!-- /left -->

                <!-- Right: System info + how it works -->
                <div style="display:flex;flex-direction:column;gap:20px;">

                    <div class="seo-panel">
                        <div class="seo-panel-hd"><h2>ℹ️ License Info</h2></div>
                        <div class="seo-panel-body">
                            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                                <?php
                                $rows = [
                                    [ 'Plugin Version', SEO_DASH_VERSION ],
                                    [ 'License Status', $is_licensed ? '✅ Active' : ( $key_stored ? '❌ Invalid' : '🔓 Free Mode' ) ],
                                    [ 'Domain',  parse_url( home_url(), PHP_URL_HOST ) ],
                                    [ 'Total Reports', SEO_Dash_Database::count_reports() ],
                                    [ 'Free Mode Limit', $is_licensed ? 'Unlimited' : '1 report · Overview tab only' ],
                                ];
                                foreach ( $rows as [ $k, $v ] ) : ?>
                                <tr style="border-bottom:1px solid var(--c-border);">
                                    <td style="padding:9px 0;color:var(--c-muted);font-weight:600;width:140px;"><?php echo esc_html( $k ); ?></td>
                                    <td style="padding:9px 0;"><?php echo esc_html( $v ); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>

                    <div class="seo-panel">
                        <div class="seo-panel-hd"><h2>💡 How it Works</h2></div>
                        <div class="seo-panel-body" style="font-size:13px;color:var(--c-muted);line-height:1.8;">
                            <ol style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;">
                                <li>Purchase a license to receive your unique license key.</li>
                                <li>Paste the key above and click <strong>Activate License</strong>.</li>
                                <li>All features unlock instantly — clients, integrations, settings, logs, and unlimited reports.</li>
                                <li>Plugin updates appear automatically in <strong>WP Admin → Dashboard → Updates</strong>.</li>
                            </ol>
                            <div style="margin-top:16px;padding:12px;background:var(--c-surf2,rgba(0,0,0,.03));border-radius:8px;font-size:12px;">
                                <strong>Free Mode includes:</strong> Overview tab (hero stats only) + maximum 1 report.
                            </div>
                        </div>
                    </div>

                </div><!-- /right -->
            </div><!-- /grid -->
        </div>

        <script>
        (function($){
            var ajaxUrl = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
            var nonce   = '<?php echo esc_js( wp_create_nonce('seo_dash_admin') ); ?>';

            function licMsg( msg, ok ) {
                var color = ok ? 'var(--c-green,#10b981)' : 'var(--c-red,#ef4444)';
                $('#seo-lic-activate-msg').html('<span style="color:'+color+';">'+msg+'</span>');
            }
            function updMsg( msg ) {
                $('#seo-lic-update-msg').html('<span style="color:var(--c-green,#10b981);">'+msg+'</span>');
            }
            function showKeyIcon() {
                $('#seo-lic-key-icon').show();
                $('#seo-lic-key-input').css('padding-right', '34px');
            }
            function hideKeyIcon() {
                $('#seo-lic-key-icon').hide();
                $('#seo-lic-key-input').css('padding-right', '');
            }

            // Hide the saved/valid checkmark as soon as the user starts
            // editing the key again — it's only meaningful for the key
            // that was actually last validated, not whatever is currently
            // typed in the box.
            $('#seo-lic-key-input').on('input', function(){
                hideKeyIcon();
            });

            // Validate & Activate — saves the key and validates it against
            // the SLM server in a single step.
            $('#seo-lic-activate-btn').on('click', function(){
                var key = $.trim( $('#seo-lic-key-input').val() );
                if (!key) { licMsg('Please enter your license key.', false); hideKeyIcon(); return; }
                var $btn = $(this).prop('disabled', true).text('Validating & activating…');
                $.post( ajaxUrl, { action:'seo_dash_license_activate', license_key:key, nonce:nonce })
                 .done(function(r){
                     var msg = (r.data && r.data.message) ? r.data.message : (r.success ? '✅ License validated and activated!' : '❌ ' + (r.data || 'Failed.'));
                     licMsg(msg, r.success);
                     if (r.success) {
                         showKeyIcon();
                         setTimeout(function(){ location.reload(); }, 1000);
                     } else {
                         hideKeyIcon();
                     }
                 })
                 .fail(function(){ licMsg('❌ Request failed. Please try again.', false); hideKeyIcon(); })
                 .always(function(){ $btn.prop('disabled', false).text('✅ Validate & Activate'); });
            });

            // Remove
            $('#seo-lic-deactivate-btn').on('click', function(){
                if (!confirm('Remove license key? The plugin will switch to Free Mode.')) return;
                $.post( ajaxUrl, { action:'seo_dash_license_deactivate', nonce:nonce })
                 .done(function(){ location.reload(); });
            });

            // Check updates
            $('#seo-lic-check-update-btn').on('click', function(){
                var $btn = $(this).prop('disabled', true).text('Checking…');
                $.post( ajaxUrl, { action:'seo_dash_license_check_update', nonce:nonce })
                 .done(function(r){
                     var msg = (r.data && r.data.message) ? r.data.message : (r.data && r.data.new_version ? '🆕 Update available: v'+r.data.new_version : '✅ You are up to date.');
                     updMsg(msg);
                 })
                 .fail(function(){ updMsg('❌ Could not check. Try again later.'); })
                 .always(function(){ $btn.prop('disabled',false).text('Check for Updates Now'); });
            });
        })(jQuery);
        </script>
        <?php
    }


    // ══════════════════════════════════════════════════════════════════════
    // LICENSE GATE
    // ══════════════════════════════════════════════════════════════════════

    private static function render_license_gate( string $section, string $reason, string $current_page = '' ): void {
        $license_url = add_query_arg( 'seo_page', 'license', self::$base_url );
        ?>
        <div class="seo-page" style="display:flex;align-items:center;justify-content:center;min-height:60vh;">
            <div style="text-align:center;max-width:480px;padding:40px 32px;background:var(--c-card,#fff);border:1px solid var(--c-border,#e5e7eb);border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.07);">
                <div style="font-size:56px;margin-bottom:16px;">🔒</div>
                <h2 style="margin:0 0 10px;font-size:22px;color:var(--c-text,#111);">License Required</h2>
                <p style="margin:0 0 8px;color:var(--c-muted,#555);font-size:14px;line-height:1.6;">
                    <strong><?php echo esc_html( $section ); ?></strong> requires an active license.
                </p>
                <p style="margin:0 0 28px;color:var(--c-subtle,#888);font-size:13px;"><?php echo esc_html( $reason ); ?></p>
                <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                    <a href="<?php echo esc_url( $license_url ); ?>"
                       style="display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:var(--c-primary,#6366f1);color:#fff;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;">
                        🔑 Activate License
                    </a>
                    <a href="<?php echo esc_url( self::$base_url ); ?>"
                       style="display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:var(--c-surf,#f3f4f6);color:var(--c-text,#111);border:1px solid var(--c-border,#e5e7eb);border-radius:8px;font-weight:600;font-size:14px;text-decoration:none;">
                        ← Dashboard Home
                    </a>
                </div>
                <p style="margin:24px 0 0;font-size:12px;color:var(--c-subtle,#aaa);">
                    Free Mode: Overview tab (hero section) + 1 report · <a href="<?php echo esc_url( $license_url ); ?>" style="color:var(--c-primary,#6366f1);">Upgrade →</a>
                </p>
            </div>
        </div>
        <?php
    }

    private static function render_modal_new_report(): void {
        include SEO_DASH_PATH . 'includes/views/admin/modals/modal-new-report.php';
    }

    private static function render_modal_new_client(): void {
        $reports = SEO_Dash_Database::get_reports();
        include SEO_DASH_PATH . 'includes/views/admin/modals/modal-new-client.php';
    }

    private static function render_modal_new_integration(): void {
        include SEO_DASH_PATH . 'includes/views/admin/modals/modal-new-integration.php';
    }

    // ══════════════════════════════════════════════════════════════════════
    // ACCESS DENIED
    // ══════════════════════════════════════════════════════════════════════

    private static function render_access_denied(): string {
        $login_url = wp_login_url( get_permalink() );
        return '<div class="seo-access-denied">
            <div class="seo-denied-box">
                <div class="seo-denied-icon">🔒</div>
                <h2>Admin Access Required</h2>
                <p>You need to be logged in as an administrator to view this page.</p>
                <a href="' . esc_url( $login_url ) . '" class="seo-btn seo-btn-primary">Log In</a>
            </div>
        </div>';
    }
}
