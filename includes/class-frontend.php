<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO_Dash_Frontend
 *
 * Handles the [seo_dashboard] shortcode and the full-page
 * takeover for the client-facing dashboard:
 *
 *  - Intercepts the dashboard page request via template_redirect.
 *  - Renders a custom login form for unauthenticated users.
 *  - Renders the full dashboard for authenticated seo_client users.
 *  - Dequeues the active theme on dashboard pages (clean canvas).
 *  - Disables caching plugins on the dashboard page.
 */
class SEO_Dash_Frontend {

    public static function init(): void {
        self::boot();
    }

    public static function boot(): void {
        // Register the shortcode.
        add_shortcode( 'seo_dashboard', [ __CLASS__, 'shortcode' ] );

        // Register pretty-URL rewrite rules: /seo-dashboard/{username}/
        add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );

        // Take over the page template on the dashboard page.
        add_action( 'template_redirect', [ __CLASS__, 'maybe_takeover' ] );

        // Strip the active theme's assets from the dashboard page.
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_strip_theme' ], 999 );

        // Enqueue client portal assets.
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_client_assets' ] );
    }

    /**
     * Register rewrite rule so /seo-dashboard/{slug}/ maps to the portal page
     * and passes the client slug as the custom query var seo_dash_client.
     */
    public static function register_rewrite_rules(): void {
        $page_id = intval( get_option( 'seo_dash_client_page_id' ) );
        if ( ! $page_id ) return;

        $page_slug = get_post_field( 'post_name', $page_id );
        if ( ! $page_slug ) return;

        $rule_pattern = $page_slug . '/([a-z0-9_-]+)/?$';
        $rule_target  = 'index.php?pagename=' . $page_slug . '&seo_dash_client=$matches[1]';

        add_rewrite_rule( $rule_pattern, $rule_target, 'top' );

        // Flush if explicitly flagged (settings save / page recreate).
        $needs_flush = (bool) get_option( 'seo_dash_flush_rewrites' );

        // Self-healing: also flush if our rule is not yet in WordPress's
        // registered rewrite rules (covers fresh installs, migrations,
        // permalink setting changes, or any scenario where flush was missed).
        if ( ! $needs_flush ) {
            $registered = get_option( 'rewrite_rules', [] );
            if ( ! is_array( $registered ) || ! isset( $registered[ $rule_pattern ] ) ) {
                $needs_flush = true;
            }
        }

        if ( $needs_flush ) {
            flush_rewrite_rules( false );
            delete_option( 'seo_dash_flush_rewrites' );
        }
    }

    public static function add_query_vars( array $vars ): array {
        $vars[] = 'seo_dash_client';
        return $vars;
    }

    /**
     * Build the pretty dashboard URL for a given WP username.
     * e.g. https://example.com/seo-dashboard/john-smith/
     */
    public static function client_url( string $username ): string {
        $page_id = intval( get_option( 'seo_dash_client_page_id' ) );
        if ( ! $page_id ) return '';
        $base = trailingslashit( get_permalink( $page_id ) );
        return $base . sanitize_title( $username ) . '/';
    }

    /**
     * Generate a one-time, time-limited auto-login link for emails.
     *
     * Appends a random, high-entropy token as a query arg. The token is stored
     * as a transient mapped to the WP user ID (no schema change needed) and is
     * consumed — deleted — the first time it's used, in maybe_takeover(). This
     * lets clients click "View My Dashboard" in an email and land already
     * logged in, instead of having to copy/paste their password.
     *
     * @param int    $user_id  WP user ID to log in as when the link is opened.
     * @param string $base_url The dashboard URL to attach the token to.
     * @param int    $ttl      Token lifetime in seconds (default 3 days).
     * @return string The dashboard URL with the login token appended.
     */
    public static function generate_login_link( int $user_id, string $base_url, int $ttl = 259200 ): string {
        if ( ! $user_id || ! $base_url ) return $base_url;
        $token = wp_generate_password( 40, false, false ); // alnum only, URL-safe
        set_transient( 'seo_dash_login_token_' . $token, $user_id, $ttl );
        return add_query_arg( 'sdtoken', $token, $base_url );
    }

    /**
     * Create a dedicated WP page for a client and return its ID + URL.
     * The page uses the [seo_dashboard] shortcode so it works as a
     * standalone dashboard without depending on rewrite rules.
     *
     * @param string $client_name  Display name (used in page title).
     * @param string $username     WP login (used in slug + shortcode).
     * @return array{ page_id: int, page_url: string }
     */
    public static function create_client_page( string $client_name, string $username ): array {
        // Build a unique slug: seo-dashboard-{username}
        $base_slug = 'seo-dashboard-' . sanitize_title( $username );
        $slug      = $base_slug;
        $counter   = 1;
        while ( get_page_by_path( $slug ) ) {
            $slug = $base_slug . '-' . $counter++;
        }

        $page_id = wp_insert_post( [
            'post_title'     => esc_html( $client_name ) . ' — SEO Dashboard',
            'post_name'      => $slug,
            'post_content'   => '[seo_dashboard user="' . esc_attr( $username ) . '"]',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => 1,
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ] );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            return [ 'page_id' => 0, 'page_url' => '' ];
        }

        // Mark as a system page so it doesn't appear in menus/searches.
        update_post_meta( $page_id, '_seo_dash_client_page', '1' );
        update_post_meta( $page_id, '_seo_dash_client_username', $username );

        $page_url = get_permalink( $page_id );

        return [ 'page_id' => $page_id, 'page_url' => (string) $page_url ];
    }

    /**
     * Delete the dedicated WP page for a client (called on client delete).
     *
     * @param int $wp_page_id
     */
    public static function delete_client_page( int $wp_page_id ): void {
        if ( $wp_page_id && get_post( $wp_page_id ) ) {
            // Only delete pages we created (has our meta marker).
            if ( get_post_meta( $wp_page_id, '_seo_dash_client_page', true ) ) {
                wp_delete_post( $wp_page_id, true ); // true = force delete, skip trash
            }
        }
    }

    public static function enqueue_client_assets(): void {
        // Fire on the shared portal page, pretty sub-paths, OR any per-client dedicated page.
        $shared_page_id  = intval( get_option( 'seo_dash_client_page_id' ) );
        $is_client_page  = is_page() && get_post_meta( get_the_ID(), '_seo_dash_client_page', true );
        $is_shared       = $shared_page_id && ( is_page( $shared_page_id ) || get_query_var( 'seo_dash_client' ) );

        if ( ! $is_client_page && ! $is_shared ) return;

        wp_enqueue_style( 'seo-dash-fonts', 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap', [], null );
        wp_enqueue_style( 'seo-dash-client', SEO_DASH_URL . 'assets/css/client-app.css', [], filemtime( SEO_DASH_PATH . 'assets/css/client-app.css' ) );

        wp_register_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js', [], '4.4.2', true );
        wp_register_script( 'chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js', ['chartjs'], '2.2.0', true );
        wp_enqueue_script( 'seo-dash-client', SEO_DASH_URL . 'assets/js/client-app.js', ['jquery','chartjs','chartjs-datalabels'], filemtime( SEO_DASH_PATH . 'assets/js/client-app.js' ), true );

        $report_id = 0;
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            
            // 1. Try Client
            $client = SEO_Dash_Database::get_client_by_user( $user_id );
            if ( $client ) {
                $rids = SEO_Dash_Database::get_client_report_ids( intval( $client['id'] ) );
                if ( ! empty( $rids ) ) {
                    // Respect the "Switch Report" selection (?report_id=N) — must match
                    // the resolution logic in the page renderer, otherwise the localized
                    // seoDashClient.report_id (used by ALL AJAX calls) points at the
                    // client's default report while the page itself shows a different
                    // (switched-to) report, causing every tab to show "No data".
                    $report_id = $rids[0];
                    if ( isset( $_GET['report_id'] ) ) {
                        $requested = intval( $_GET['report_id'] );
                        if ( in_array( $requested, $rids, false ) ) {
                            $report_id = $requested;
                        }
                    }
                }
            }
            
            // 2. Admin Fallback
            if ( ! $report_id && current_user_can( 'manage_options' ) ) {
                $reports   = SEO_Dash_Database::get_reports();
                $report_id = $reports[0]['id'] ?? 0;
                if ( isset( $_GET['report_id'] ) ) $report_id = intval( $_GET['report_id'] );
            }
        }

        wp_localize_script( 'seo-dash-client', 'seoDashClient', [
            'ajax'      => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'seo_dash_frontend' ),
            'report_id' => $report_id,
        ] );
    }

    // ── Shortcode ─────────────────────────────────────────────────────────

    /**
     * [seo_dashboard user="login_name"]
     *
     * When rendered inside the full-page takeover this shortcode is never
     * actually executed (the takeover exits before WP renders the page).
     * It acts as a marker so template_redirect knows which page to intercept.
     *
     * If someone embeds the shortcode in a normal page/post (not full takeover)
     * we still render a minimal inline version.
     *
     * @param array $atts
     * @return string
     */
    public static function shortcode( array $atts = [] ): string {
        // Full-page takeover already handled in template_redirect.
        // This path runs only when shortcode is used in a normal content context.
        $atts = shortcode_atts( [ 'user' => '' ], $atts, 'seo_dashboard' );

        if ( ! is_user_logged_in() ) {
            return self::inline_login_form();
        }

        $user    = wp_get_current_user();
        $client  = SEO_Dash_Database::get_client_by_user( $user->ID );
        if ( ! $client ) {
            return '<p>' . esc_html__( 'No dashboard found for your account.', 'seo-dashboard' ) . '</p>';
        }

        $report_ids = SEO_Dash_Database::get_client_report_ids( intval( $client['id'] ) );
        if ( empty( $report_ids ) ) {
            return '<p>' . esc_html__( 'No reports have been assigned to your account yet.', 'seo-dashboard' ) . '</p>';
        }

        $report = SEO_Dash_Database::get_report( $report_ids[0] );
        ob_start();
        SEO_Dash_Frontend_Render::render( $report, $report_ids, $user->ID );
        return ob_get_clean();
    }

    // ── Full-page takeover ────────────────────────────────────────────────

    /**
     * Intercept the dashboard page and render it as a completely custom page
     * — bypassing the theme header/footer entirely.
     */
    public static function maybe_takeover(): void {

        // ── Per-client dedicated page detection ───────────────────────────
        $current_post    = get_post();
        $client_username = '';
        if ( $current_post && is_page() ) {
            $meta_flag = get_post_meta( $current_post->ID, '_seo_dash_client_page', true );
            if ( $meta_flag ) {
                $client_username = (string) get_post_meta( $current_post->ID, '_seo_dash_client_username', true );
            }
        }

        // ── Shared portal page / pretty-URL fallback ──────────────────────
        $page_id      = intval( get_option( 'seo_dash_client_page_id' ) );
        $client_slug  = sanitize_title( get_query_var( 'seo_dash_client' ) );
        $page_slug    = $page_id ? get_post_field( 'post_name', $page_id ) : '';
        $is_shared    = $page_id && ( is_page( $page_id ) || $client_slug || get_query_var( 'pagename' ) === $page_slug );

        // Not a dashboard page at all — bail.
        if ( ! $client_username && ! $is_shared ) {
            return;
        }

        // ── Magic-link auto-login (one-time token from an emailed dashboard link) ──
        // Lets a client click "View My Dashboard" / "Go to My Dashboard" in an
        // email and land already logged in, instead of copy-pasting a password.
        // The token is single-use (deleted immediately) and time-limited (see
        // generate_login_link()). Invalid/expired tokens are silently ignored
        // and the request just falls through to the normal login form below.
        $sd_token = isset( $_GET['sdtoken'] ) ? sanitize_text_field( wp_unslash( $_GET['sdtoken'] ) ) : '';
        if ( $sd_token !== '' ) {
            $sd_user_id = get_transient( 'seo_dash_login_token_' . $sd_token );
            $sd_user    = $sd_user_id ? get_userdata( intval( $sd_user_id ) ) : false;
            if ( $sd_user && in_array( 'seo_client', (array) $sd_user->roles, true ) ) {
                delete_transient( 'seo_dash_login_token_' . $sd_token ); // single-use
                wp_clear_auth_cookie();
                wp_set_auth_cookie( $sd_user->ID, true );
                wp_set_current_user( $sd_user->ID );
                wp_safe_redirect( remove_query_arg( 'sdtoken' ) );
                exit;
            }
            // Invalid/expired/wrong-role token — drop it and continue normally.
        }

        // If we landed on a per-client page, set client_slug from its meta.
        if ( $client_username && ! $client_slug ) {
            $client_slug = sanitize_title( $client_username );
        }

        // Disable caching on this page.
        self::disable_caching();

        // Not logged in → show custom login page.
        if ( ! is_user_logged_in() ) {
            // Redirect back to the current page URL after login.
            $login_redirect = get_permalink();
            if ( ! $login_redirect && $page_id ) {
                $login_redirect = get_permalink( $page_id );
            }

            // Prefill the username field with the actual WP user_login (not the
            // sanitize_title slug) so clients can copy-paste it straight from
            // their welcome email without a mismatch.
            $prefill_login = $client_slug;
            if ( $client_username ) {
                $wp_user_obj = get_user_by( 'login', $client_username );
                if ( ! $wp_user_obj ) {
                    // Fall back: look up via client DB record.
                    global $wpdb;
                    $client_row = $wpdb->get_row(
                        $wpdb->prepare(
                            'SELECT wp_user_id FROM ' . SEO_Dash_Database::$clients . ' WHERE wp_user_id != 0 ORDER BY id DESC LIMIT 1'
                        ),
                        ARRAY_A
                    );
                    // Try matching by meta stored on the page.
                    $page_uid = $current_post ? (int) get_post_meta( $current_post->ID, '_seo_dash_wp_user_id', true ) : 0;
                    if ( $page_uid ) {
                        $wp_user_obj = get_userdata( $page_uid );
                    }
                }
                if ( $wp_user_obj ) {
                    $prefill_login = $wp_user_obj->user_login;
                }
            }

            self::render_login_page( $page_id ?: 0, $prefill_login, (string) $login_redirect );
            exit;
        }

        $user = wp_get_current_user();

        // ── Resolve which client this page belongs to ─────────────────────
        // Priority 1: per-client page — look up by the page's wp_page_id.
        //             This works regardless of who is viewing (admin, client, etc).
        // Priority 2: logged-in user's own client record (normal client login).
        $client         = null;
        $page_client_id = 0;

        if ( $current_post && $current_post->ID ) {
            global $wpdb;
            $page_client = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . SEO_Dash_Database::$clients . " WHERE wp_page_id = %d LIMIT 1",
                $current_post->ID
            ), ARRAY_A );
            if ( $page_client ) {
                $client         = $page_client;
                $page_client_id = intval( $page_client['id'] );
            }
        }

        // If not found by page, fall back to the logged-in user's own client record.
        if ( ! $client ) {
            $client = SEO_Dash_Database::get_client_by_user( $user->ID );
        }

        // Sync wp_user_id if it was missing on the client record.
        if ( $client && empty( $client['wp_user_id'] ) && $client_username ) {
            $page_user = get_user_by( 'login', $client_username );
            if ( $page_user ) {
                SEO_Dash_Database::update_client( intval( $client['id'] ), [ 'wp_user_id' => $page_user->ID ] );
                $client['wp_user_id'] = $page_user->ID;
            }
        }

        // ── Access control ────────────────────────────────────────────────
        $is_admin       = current_user_can( 'manage_options' );
        $is_client      = in_array( 'seo_client',      (array) $user->roles, true );

        if ( ! $is_admin && ! $is_client ) {
            self::render_error_page(
                __( 'Access Denied', 'seo-dashboard' ),
                __( 'This dashboard is for SEO clients only.', 'seo-dashboard' )
            );
            exit;
        }

        // Non-admin client visiting someone else's page — deny.
        if ( ! $is_admin && $client && intval( $client['wp_user_id'] ) !== $user->ID ) {
            self::render_error_page(
                __( 'Access Denied', 'seo-dashboard' ),
                __( 'You do not have permission to view this dashboard.', 'seo-dashboard' )
            );
            exit;
        }

        // Admin with no client record for this page — show admin preview fallback.
        if ( $is_admin && ! $client ) {
            $reports = SEO_Dash_Database::get_reports();
            if ( empty( $reports ) ) {
                self::render_error_page(
                    __( 'No Reports Yet', 'seo-dashboard' ),
                    __( 'Create a report in the SEO Dashboard admin first.', 'seo-dashboard' )
                );
                exit;
            }
            $report     = $reports[0];
            $report_ids = array_column( $reports, 'id' );
            self::render_fullpage( $report, $report_ids, $user->ID );
            exit;
        }

        // No client record found for this user at all.
        if ( ! $client ) {
            self::render_error_page(
                __( 'No Dashboard Found', 'seo-dashboard' ),
                __( 'No dashboard has been set up for your account. Please contact your agency.', 'seo-dashboard' )
            );
            exit;
        }

        $report_ids = SEO_Dash_Database::get_client_report_ids( intval( $client['id'] ) );
        if ( empty( $report_ids ) ) {
            self::render_error_page(
                __( 'No Reports Assigned', 'seo-dashboard' ),
                __( 'No reports have been assigned to your account yet.', 'seo-dashboard' )
            );
            exit;
        }

        // Determine which report to show (default: first, or ?report_id=N).
        $active_id = isset( $_GET['report_id'] ) ? intval( $_GET['report_id'] ) : $report_ids[0];
        if ( ! in_array( $active_id, $report_ids, false ) ) {
            $active_id = $report_ids[0]; // security: only show assigned reports
        }

        $report = SEO_Dash_Database::get_report( $active_id );
        if ( ! $report ) {
            self::render_error_page( __( 'Report Not Found', 'seo-dashboard' ), '' );
            exit;
        }

        self::render_fullpage( $report, $report_ids, $user->ID );
        exit;
    }

    // ── Theme asset stripping ─────────────────────────────────────────────

    /**
     * On the dashboard page, remove the active theme's stylesheet and
     * most plugin scripts to avoid conflicts with our custom UI.
     */
    public static function maybe_strip_theme(): void {
        $shared_page_id = intval( get_option( 'seo_dash_client_page_id' ) );
        $is_client_page = is_page() && get_post_meta( get_the_ID(), '_seo_dash_client_page', true );
        $is_shared      = $shared_page_id && is_page( $shared_page_id );
        if ( ! $is_client_page && ! $is_shared ) {
            return;
        }
        // Dequeue common theme style handles.
        $theme_handles = [ 'style', 'theme-styles', 'main-css', wp_get_theme()->get_stylesheet() . '-style' ];
        foreach ( $theme_handles as $handle ) {
            wp_dequeue_style( $handle );
            wp_deregister_style( $handle );
        }
    }

    // ── Caching ───────────────────────────────────────────────────────────

    private static function disable_caching(): void {
        // WP Rocket.
        if ( function_exists( 'rocket_cache_reject_uri' ) ) {
            add_filter( 'rocket_cache_reject_uri', '__return_true' );
        }
        // W3 Total Cache.
        if ( function_exists( 'w3tc_flush_post' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        // WP Super Cache.
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        // Set no-cache headers.
        nocache_headers();
    }

    // ── Page renderers ────────────────────────────────────────────────────

    /**
     * Render the full client dashboard page (bypasses theme).
     */
    private static function render_fullpage( array $report, array $report_ids, int $user_id, array $override_show = [], string $template = 'dashboard.php' ): void {
        // Strip theme stylesheets so they cannot constrain our layout width.
        add_action( 'wp_print_styles', function () {
            global $wp_styles;
            if ( ! $wp_styles ) return;
            $whitelist = [ 'seo-dash-', 'dashicons', 'buttons', 'media' ];
            foreach ( $wp_styles->queue as $h ) {
                $keep = false;
                foreach ( $whitelist as $w ) {
                    if ( strpos( $h, $w ) !== false ) { $keep = true; break; }
                }
                if ( ! $keep ) wp_dequeue_style( $h );
            }
        }, 99 );
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( $report['title'] ?? __( 'SEO Dashboard', 'seo-dashboard' ) ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="seo-dash-fullpage">
<?php SEO_Dash_Frontend_Render::render( $report, $report_ids, $user_id, $override_show, $template ); ?>
<?php wp_footer(); ?>
</body>
</html>
        <?php
    }

    /**
     * Render a custom login form (no WP theme wrapper).
     *
     * @param int $page_id  Redirect target after login.
     */
    private static function render_login_page( int $page_id, string $prefill_user = '', string $redirect_url = '' ): void {
        if ( ! $redirect_url ) {
            $redirect_url = get_permalink( $page_id );
        }

        $brand   = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
        $logo      = SEO_Dash_Database::get_setting( 'brand_logo', '' );
        $logo_dark = SEO_Dash_Database::get_setting( 'brand_logo_dark', '' );
        $primary = '#6366f1';

        $error = get_transient( 'seo_dash_login_error_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        if ( $error ) delete_transient( 'seo_dash_login_error_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo esc_html( $brand ); ?> — Client Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%;font-family:'Outfit',system-ui,sans-serif;}
    body{
        display:flex;align-items:center;justify-content:center;min-height:100vh;
        background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#0f172a 100%);
        position:relative;overflow:hidden;
    }
    /* Animated background orbs */
    body::before,body::after{
        content:'';position:fixed;border-radius:50%;filter:blur(80px);opacity:.35;pointer-events:none;
        animation:orb 12s ease-in-out infinite alternate;
    }
    body::before{width:600px;height:600px;background:radial-gradient(circle,#6366f1,transparent);top:-200px;left:-200px;}
    body::after{width:500px;height:500px;background:radial-gradient(circle,#7c3aed,transparent);bottom:-150px;right:-150px;animation-delay:-6s;}
    @keyframes orb{from{transform:translate(0,0) scale(1);}to{transform:translate(40px,30px) scale(1.1);}}

    .login-wrap{
        position:relative;z-index:10;width:100%;max-width:420px;padding:16px;
    }
    .login-card{
        background:rgba(255,255,255,.06);
        backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
        border:1px solid rgba(255,255,255,.12);
        border-radius:24px;padding:44px 40px 40px;
        box-shadow:0 32px 64px rgba(15,23,42,.4),0 0 0 1px rgba(255,255,255,.04);
    }
    .login-logo{text-align:center;margin-bottom:32px;}
    .login-logo img{height:48px;object-fit:contain;margin-bottom:12px;}
    .login-logo-icon{
        width:64px;height:64px;border-radius:18px;
        background:linear-gradient(135deg,#6366f1,#7c3aed);
        display:inline-flex;align-items:center;justify-content:center;
        font-size:28px;margin-bottom:12px;
        box-shadow:0 8px 24px rgba(99,102,241,.4);
    }
    .login-title{color:#f1f5f9;font-size:22px;font-weight:800;line-height:1.2;}
    .login-sub{color:rgba(255,255,255,.45);font-size:13px;margin-top:5px;}

    .login-label{display:block;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:7px;}
    .login-field{margin-bottom:18px;}
    .login-input{
        width:100%;padding:12px 16px;
        background:rgba(255,255,255,.07);
        border:1px solid rgba(255,255,255,.12);
        border-radius:12px;
        color:#f1f5f9;font-size:15px;font-family:'Outfit',sans-serif;
        transition:border .2s,background .2s;outline:none;
    }
    .login-input::placeholder{color:rgba(255,255,255,.25);}
    .login-input:focus{border-color:rgba(99,102,241,.7);background:rgba(99,102,241,.1);}
    .login-input:-webkit-autofill{-webkit-box-shadow:0 0 0 100px #1e1b4b inset;-webkit-text-fill-color:#f1f5f9;}

    .login-btn{
        width:100%;padding:14px;margin-top:6px;
        background:linear-gradient(135deg,#6366f1,#7c3aed);
        color:#fff;border:none;border-radius:12px;
        font-size:15px;font-weight:700;font-family:'Outfit',sans-serif;
        cursor:pointer;transition:transform .15s,box-shadow .15s;
        box-shadow:0 4px 16px rgba(99,102,241,.4);
    }
    .login-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(99,102,241,.5);}
    .login-btn:active{transform:translateY(0);}

    .login-error{
        background:rgba(220,38,38,.15);border:1px solid rgba(220,38,38,.3);
        color:#fca5a5;padding:11px 15px;border-radius:10px;
        font-size:13px;margin-bottom:18px;display:flex;gap:8px;align-items:center;
    }
    .login-footer{text-align:center;margin-top:28px;color:rgba(255,255,255,.2);font-size:12px;}
    </style>
    <?php
    // Only output what's essential — wp_head() on a non-logged-in custom page
    // causes 3rd-party plugins to fire AJAX/session calls that can break nonces.
    wp_print_styles( 'dashicons' ); // safe minimal output
    ?>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-logo">
            <?php if ( $logo ) : ?>
                <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($brand); ?>">
            <?php else : ?>
                <div class="login-logo-icon">📊</div>
            <?php endif; ?>
            <div class="login-title"><?php echo esc_html($brand); ?></div>
            <div class="login-sub">Sign in to your SEO Dashboard</div>
        </div>

        <?php if ( $error ) : ?>
        <div class="login-error">⚠ <?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <input type="hidden" name="action" value="seo_dash_login">
            <input type="hidden" name="_seo_token" value="<?php echo esc_attr( md5( AUTH_KEY . 'seo_login' . wp_date('YmdH') ) ); ?>">
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_url); ?>">

            <div class="login-field">
                <label class="login-label" for="seo_log">Username</label>
                <input class="login-input" type="text" id="seo_log" name="log"
                       value="<?php echo esc_attr($prefill_user); ?>"
                       autocomplete="username" placeholder="Enter your username" required>
            </div>
            <div class="login-field">
                <label class="login-label" for="seo_pwd">Password</label>
                <input class="login-input" type="password" id="seo_pwd" name="pwd"
                       autocomplete="current-password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="login-btn">Sign In →</button>
        </form>

        <div style="text-align:center;margin-top:18px;">
            <a href="<?php echo esc_url( wp_lostpassword_url( get_permalink() ) ); ?>"
               style="color:rgba(255,255,255,.45);font-size:13px;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.2);padding-bottom:1px;">
                Forgot your password?
            </a>
        </div>

        <div class="login-footer">Protected client portal &mdash; <?php echo esc_html($brand); ?></div>
    </div>
</div>
</body>
</html>
        <?php
    }

    /**
     * Render a simple error/notice page (no theme).
     *
     * @param string $title
     * @param string $message
     */
    private static function render_error_page( string $title, string $message ): void {
        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $title ); ?></title>
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-family:-apple-system,sans-serif;}
        .box{background:#fff;padding:40px;border-radius:16px;max-width:480px;text-align:center;box-shadow:0 4px 24px rgba(15,23,42,.08);}
        h1{color:#1e293b;font-size:22px;margin:0 0 12px;}
        p{color:#64748b;font-size:15px;}
    </style>
</head>
<body>
<div class="box">
    <h1><?php echo esc_html( $title ); ?></h1>
    <?php if ( $message ): ?>
        <p><?php echo esc_html( $message ); ?></p>
    <?php endif; ?>
</div>
</body>
</html>
        <?php
    }

    /**
     * Render a minimal inline login form for shortcode-in-content use.
     *
     * @return string
     */
    private static function inline_login_form(): string {
        $redirect = get_permalink();
        ob_start();
        ?>
        <div class="seo-dash-inline-login" style="max-width:380px;padding:24px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
            <p style="margin:0 0 16px;font-weight:700;color:#1e293b;"><?php esc_html_e( 'Please log in to view your dashboard.', 'seo-dashboard' ); ?></p>
            <?php echo wp_login_form( [ 'redirect' => $redirect, 'echo' => false ] ); ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// ── Custom login form POST handler ────────────────────────────────────────
add_action( 'admin_post_seo_dash_login',        'seo_dash_handle_frontend_login' );
add_action( 'admin_post_nopriv_seo_dash_login', 'seo_dash_handle_frontend_login' );

function seo_dash_handle_frontend_login(): void {
    $nonce_key   = 'seo_dash_login_error_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
    $redirect_to = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? home_url() ) );

    // Verify time-based token (valid for current hour and previous hour to handle clock edge).
    // This is reliable for non-logged-in users unlike wp_nonce which can fail on custom pages.
    $token_sent = sanitize_text_field( wp_unslash( $_POST['_seo_token'] ?? '' ) );
    $valid_now  = md5( AUTH_KEY . 'seo_login' . wp_date('YmdH') );
    $valid_prev = md5( AUTH_KEY . 'seo_login' . wp_date('YmdH', time() - 3600) );
    if ( $token_sent !== $valid_now && $token_sent !== $valid_prev ) {
        set_transient( $nonce_key, __( 'Security check failed. Please try again.', 'seo-dashboard' ), 60 );
        wp_safe_redirect( $redirect_to );
        exit;
    }

    // Rate limiting (uses existing security module).
    if ( function_exists( 'seo_dash_sec_is_rate_limited' ) && seo_dash_sec_is_rate_limited( 'login', 5, 900 ) ) {
        set_transient( $nonce_key, __( 'Too many login attempts. Please wait 15 minutes.', 'seo-dashboard' ), 60 );
        wp_safe_redirect( $redirect_to );
        exit;
    }

    // Use trim() only — sanitize_user() can strip dots, hyphens and other
    // characters that are valid in WP usernames and would cause a mismatch
    // when the client copies their username straight from the welcome email.
    $username    = trim( wp_unslash( $_POST['log'] ?? '' ) );
    $password    = wp_unslash( $_POST['pwd'] ?? '' );

    // Detect HTTPS even behind a reverse proxy/CDN where is_ssl() returns false.
    // Without this, WordPress sets a non-secure cookie which the browser silently
    // drops on HTTPS connections — making a successful login appear as a failure.
    $is_https = is_ssl()
        || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) === 'https' )
        || ( isset( $_SERVER['HTTP_X_FORWARDED_SSL'] )   && $_SERVER['HTTP_X_FORWARDED_SSL']   === 'on' )
        || ( isset( $_SERVER['HTTP_FRONT_END_HTTPS'] )   && strtolower( $_SERVER['HTTP_FRONT_END_HTTPS'] ) === 'on' );

    $user = wp_signon( [ 'user_login' => $username, 'user_password' => $password, 'remember' => true ], $is_https );

    if ( is_wp_error( $user ) ) {
        if ( function_exists( 'seo_dash_sec_log' ) ) {
            seo_dash_sec_log( 'login_failed', $username );
        }
        set_transient( $nonce_key, __( 'Incorrect username or password.', 'seo-dashboard' ), 60 );
        wp_safe_redirect( $redirect_to );
        exit;
    }

    if ( function_exists( 'seo_dash_sec_clear_rate_limit' ) ) {
        seo_dash_sec_clear_rate_limit( 'login' );
    }

    wp_safe_redirect( $redirect_to );
    exit;
}

SEO_Dash_Frontend::boot();
