<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO_Dash_WP_Admin
 *
 * The ONLY thing in the WordPress backend.
 * One menu item → one page → shows the URL of the frontend admin dashboard
 * and an "Open Dashboard" button.  All actual management is on the frontend.
 */
class SEO_Dash_WP_Admin {

    public static function init(): void {
        add_action( 'admin_menu',    [ __CLASS__, 'menu' ] );
        add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar' ], 100 );
        add_action( 'admin_init',    [ __CLASS__, 'handle_force_check' ] );
    }

    /**
     * Handle the "Check for Updates" link — clears our own cache AND forces
     * WordPress to fully recompute its site-wide update_plugins transient
     * right now (normally that only happens via WP-Cron twice daily, or
     * when visiting Dashboard → Updates). Forcing it here means the
     * standard WordPress "update now" row appears on the Plugins screen
     * immediately, the same way it does for any other plugin.
     */
    public static function handle_force_check(): void {
        if ( empty( $_GET['seo_dash_force_check'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        delete_site_transient( 'update_plugins' );
        wp_update_plugins();

        wp_redirect( remove_query_arg( 'seo_dash_force_check' ) );
        exit;
    }

    public static function menu(): void {
        add_menu_page(
            'SEO Dashboard',
            'SEO Dashboard',
            'manage_options',
            'seo-dashboard',
            [ __CLASS__, 'page' ],
            'dashicons-chart-line',
            25
        );

        // Submenu: Overview (same as parent)
        add_submenu_page(
            'seo-dashboard',
            'SEO Dashboard Overview',
            'Overview',
            'manage_options',
            'seo-dashboard',
            [ __CLASS__, 'page' ]
        );

        // Submenu: Documentation
        add_submenu_page(
            'seo-dashboard',
            'SEO Dashboard Documentation',
            '📚 Documentation',
            'manage_options',
            'seo-dashboard-docs',
            [ __CLASS__, 'page_docs' ]
        );

    }

    // ── Admin Bar ──────────────────────────────────────────────────────────
    public static function admin_bar( \WP_Admin_Bar $bar ): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $admin_pid = (int) get_option( 'seo_dash_admin_page_id' );
        $admin_url = $admin_pid ? get_permalink( $admin_pid ) : '';

        // Top-level "SEO Dashboard" node in admin bar
        $bar->add_node( [
            'id'    => 'seo-dashboard-bar',
            'title' => '<span class="ab-icon dashicons dashicons-chart-line" style="top:2px;"></span> SEO Dashboard',
            'href'  => $admin_url ?: admin_url( 'admin.php?page=seo-dashboard' ),
            'meta'  => [ 'target' => $admin_url ? '_blank' : '', 'title' => 'SEO Dashboard Pro' ],
        ] );

        // Sub-node: Open Dashboard
        if ( $admin_url ) {
            $bar->add_node( [
                'parent' => 'seo-dashboard-bar',
                'id'     => 'seo-dashboard-bar-open',
                'title'  => '🖥️ Open Admin Dashboard',
                'href'   => $admin_url,
                'meta'   => [ 'target' => '_blank' ],
            ] );
        }

        // Sub-node: Documentation (wp-admin page)
        $bar->add_node( [
            'parent' => 'seo-dashboard-bar',
            'id'     => 'seo-dashboard-bar-docs',
            'title'  => '📚 Documentation',
            'href'   => admin_url( 'admin.php?page=seo-dashboard-docs' ),
        ] );

        // Sub-node: WP Admin Settings
        $bar->add_node( [
            'parent' => 'seo-dashboard-bar',
            'id'     => 'seo-dashboard-bar-admin',
            'title'  => '⚙️ WP Admin Panel',
            'href'   => admin_url( 'admin.php?page=seo-dashboard' ),
        ] );

    }

    // ── WP Admin: Main Page ────────────────────────────────────────────────
    public static function page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $admin_pid  = (int) get_option( 'seo_dash_admin_page_id' );
        $admin_url  = $admin_pid  ? get_permalink( $admin_pid )  : '';

        // Handle "Recreate" form.
        if ( isset( $_POST['seo_recreate_admin'] ) && check_admin_referer( 'seo_recreate' ) ) {
            $new = wp_insert_post( [
                'post_title'   => 'SEO Admin Dashboard',
                'post_name'    => 'seo-admin-' . time(),
                'post_content' => '[seo_admin_dashboard]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id(),
            ] );
            update_option( 'seo_dash_admin_page_id', $new );
            $admin_url = get_permalink( $new );
            echo '<div class="notice notice-success is-dismissible"><p>Admin dashboard page created!</p></div>';
        }

        ?>
        <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:680px;">
            <h1 style="display:flex;align-items:center;gap:10px;">📊 SEO Dashboard Pro <span style="font-size:13px;font-weight:400;color:#666;">v<?php echo esc_html( SEO_DASH_VERSION ); ?></span></h1>

            <!-- Admin Dashboard launcher -->
            <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:24px;margin:20px 0;">
                <h2 style="margin:0 0 6px;font-size:16px;">🖥️ Admin Dashboard</h2>
                <p style="color:#555;margin:0 0 16px;font-size:13px;">Your full admin panel lives on the frontend — create reports, manage clients, configure integrations, and customise the design.</p>
                <?php if ( $admin_url ) : ?>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <input type="text" value="<?php echo esc_url( $admin_url ); ?>" readonly onclick="this.select()"
                               style="flex:1;min-width:240px;padding:8px 12px;font-family:monospace;font-size:13px;border:1px solid #ccc;border-radius:6px;">
                        <a href="<?php echo esc_url( $admin_url ); ?>" target="_blank"
                           class="button button-primary" style="white-space:nowrap;">Open Admin Dashboard →</a>
                    </div>
                <?php else : ?>
                    <form method="post"><?php wp_nonce_field( 'seo_recreate' ); ?>
                        <input type="hidden" name="seo_recreate_admin" value="1">
                        <button class="button button-primary">Create Admin Dashboard Page</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Quick links -->
            <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:24px;margin:0 0 20px;">
                <h2 style="margin:0 0 14px;font-size:16px;">🔗 Quick Links</h2>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-dashboard-docs' ) ); ?>" class="button">
                        📚 Documentation
                    </a>
                    <?php if ( $admin_url ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'seo_page', 'documentation', $admin_url ) ); ?>" target="_blank" class="button">
                        📖 In-Dashboard Docs
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info table -->
            <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:24px;">
                <h2 style="margin:0 0 12px;font-size:16px;">ℹ️ System Info</h2>
                <table class="widefat" style="border-radius:6px;overflow:hidden;">
                    <tr><th>Version</th><td><?php echo esc_html( SEO_DASH_VERSION ); ?></td></tr>
                    <tr><th>PHP Version</th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
                    <tr><th>Database Tables</th><td><?php
                        global $wpdb;
                        SEO_Dash_Database::init();
                        $exists = $wpdb->get_var( "SHOW TABLES LIKE '" . SEO_Dash_Database::$reports . "'" );
                        echo $exists ? '✅ Present' : '❌ Missing — deactivate and reactivate plugin';
                    ?></td></tr>
                    <tr><th>OpenSSL (encryption)</th><td><?php echo function_exists( 'openssl_encrypt' ) ? '✅ Available' : '❌ Not available'; ?></td></tr>
                    <tr><th>License Status</th><td>✅ Active</td></tr>
                    <tr><th>Admin Dashboard Page</th><td><?php echo $admin_url  ? '✅ <a href="' . esc_url( $admin_url  ) . '" target="_blank">Active</a>' : '❌ Missing'; ?></td></tr>
                    <tr><th>Total Reports</th><td><?php echo count( SEO_Dash_Database::get_reports() ); ?></td></tr>
                    <tr><th>Total Clients</th><td><?php echo count( SEO_Dash_Database::get_clients() ); ?></td></tr>
                </table>
            </div>


        </div>
        <?php
    }

    // ── WP Admin: Documentation Page ──────────────────────────────────────
    public static function page_docs(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $admin_pid = (int) get_option( 'seo_dash_admin_page_id' );
        $admin_url = $admin_pid ? get_permalink( $admin_pid ) : '';
        $docs_frontend_url = $admin_url ? add_query_arg( 'seo_page', 'documentation', $admin_url ) : '';
        ?>
        <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
            <h1 style="display:flex;align-items:center;gap:10px;">📚 Documentation <span style="font-size:13px;font-weight:400;color:#666;">SEO Dashboard Pro v<?php echo esc_html( SEO_DASH_VERSION ); ?></span></h1>

            <?php if ( $docs_frontend_url ) : ?>
            <div style="background:#f0f6fc;border:1px solid #c3d9f0;border-radius:8px;padding:14px 18px;margin:0 0 24px;display:flex;align-items:center;gap:14px;">
                <span style="font-size:20px;">💡</span>
                <div style="flex:1;">
                    <strong>Better experience available:</strong> View the full interactive documentation inside the Dashboard.
                </div>
                <a href="<?php echo esc_url( $docs_frontend_url ); ?>" target="_blank" class="button button-primary">Open In-Dashboard Docs →</a>
            </div>
            <?php endif; ?>

            <style>
            .seo-wp-docs-section { background:#fff; border:1px solid #ddd; border-radius:10px; padding:28px; margin-bottom:22px; }
            .seo-wp-docs-section h2 { margin:0 0 8px; font-size:17px; color:#1d2327; }
            .seo-wp-docs-section .desc { color:#666; font-size:13.5px; margin:0 0 18px; }
            .seo-wp-docs-section ol { margin:0; padding-left:20px; }
            .seo-wp-docs-section li { margin-bottom:10px; font-size:13.5px; line-height:1.65; color:#333; }
            .seo-wp-docs-section li strong { color:#1d2327; }
            .seo-wp-docs-section code { background:#f0f0f0; padding:2px 6px; border-radius:3px; font-size:12px; }
            .seo-wp-docs-tip { background:#eaf4fb; border-left:4px solid #2271b1; padding:10px 14px; border-radius:0 6px 6px 0; margin-top:12px; font-size:13px; color:#333; }
            .seo-wp-docs-warn { background:#fff8e1; border-left:4px solid #dba617; padding:10px 14px; border-radius:0 6px 6px 0; margin-top:12px; font-size:13px; color:#555; }
            </style>

            <!-- First Setup -->
            <div class="seo-wp-docs-section">
                <h2>🚀 First-Time Setup</h2>
                <p class="desc">Get the plugin running in under 5 minutes.</p>
                <ol>
                    <li><strong>Activate the plugin</strong> — It automatically creates an <em>SEO Admin Dashboard</em> page and a <em>Client Portal</em> page on your site.</li>
                    <li><strong>Open the Admin Dashboard</strong> — Click <em>Open Admin Dashboard →</em> above (or use the button at the top of this admin screen). All management happens there.</li>
                    <li><strong>Set your brand</strong> — Inside the Dashboard, go to <strong>Settings</strong> to set your agency name and logo.</li>
                    <li><strong>Check System Info</strong> — Make sure all rows show ✅ in the System Info table below. If Database Tables shows ❌, deactivate and reactivate the plugin.</li>
                </ol>
            </div>

            <!-- Reports -->
            <div class="seo-wp-docs-section">
                <h2>📊 Creating Reports</h2>
                <p class="desc">Reports hold all data for each client website.</p>
                <ol>
                    <li>In the Admin Dashboard, click <strong>Reports</strong> in the top menu.</li>
                    <li>Click <strong>+ New Report</strong> and fill in the client's website name and domain.</li>
                    <li>Open the report and fill in the tabs: <em>Overview, Analytics, Search Console, Backlinks, Leads, Documents</em>, etc.</li>
                    <li>Assign a client to the report in the <strong>Clients</strong> tab so they can see it in their portal.</li>
                </ol>
            </div>

            <!-- Sitemap URLs -->
            <div class="seo-wp-docs-section">
                <h2>🗺️ Adding Sitemap URLs</h2>
                <p class="desc">Import the client's pages from their XML sitemap.</p>
                <ol>
                    <li>Open a Report → go to the <strong>Technical</strong> tab.</li>
                    <li>Find the <strong>Sitemap URL</strong> field and enter the full URL, e.g. <code>https://example.com/sitemap.xml</code></li>
                    <li>Common sitemap locations: <code>/sitemap.xml</code>, <code>/sitemap_index.xml</code>, <code>/page-sitemap.xml</code></li>
                    <li>Click <strong>Fetch Sitemap</strong> — all URLs from the sitemap will be imported into the report.</li>
                    <li>Review the imported URLs and remove any you don't need, then save.</li>
                </ol>
                <div class="seo-wp-docs-warn">⚠️ The sitemap must be publicly accessible. If the site is password-protected, the fetch will fail.</div>
            </div>

            <!-- Clients -->
            <div class="seo-wp-docs-section">
                <h2>👥 Managing Clients</h2>
                <p class="desc">Add clients and give them access to their private portal.</p>
                <ol>
                    <li>In the Admin Dashboard, click <strong>Clients</strong>.</li>
                    <li>Click <strong>+ Add Client</strong>, enter their name and email. The plugin creates a WordPress user account automatically.</li>
                    <li>Assign the client to a report so they can view it in their portal.</li>
                    <li>Send the client their portal URL (shown in the Clients list), username, and a password reset link.</li>
                </ol>
            </div>

            <!-- Google Integrations -->
            <div class="seo-wp-docs-section">
                <h2>🔗 Google Analytics &amp; Search Console</h2>
                <p class="desc">Connect Google services for live data in reports.</p>
                <ol>
                    <li>Go to <a href="https://console.cloud.google.com" target="_blank">console.cloud.google.com</a> → create a project → enable the <em>Google Analytics Data API</em> and <em>Google Search Console API</em>.</li>
                    <li>Create OAuth 2.0 credentials (Web Application type). Add the redirect URI shown in the plugin's <strong>Integrations</strong> page.</li>
                    <li>In the Admin Dashboard → <strong>Integrations → + New Integration</strong>, paste your Client ID and Client Secret and authorise.</li>
                    <li>Inside each report, assign the integration and click <strong>Fetch Data</strong>.</li>
                </ol>
                <div class="seo-wp-docs-tip">💡 You can connect multiple Google accounts — one per client if needed.</div>
            </div>

            <!-- AI -->
            <div class="seo-wp-docs-section">
                <h2>🤖 AI Features (Groq)</h2>
                <p class="desc">Generate AI-powered SEO insights with a free Groq API key.</p>
                <ol>
                    <li>Get a free API key at <a href="https://console.groq.com" target="_blank">console.groq.com</a>.</li>
                    <li>In the Admin Dashboard → <strong>Settings</strong>, paste the key in the <strong>Groq API Key</strong> field and save.</li>
                    <li>Inside reports, use the <strong>AI Generate</strong> buttons to create summaries, recommendations, and content briefs.</li>
                </ol>
            </div>

            <!-- Troubleshooting -->
            <div class="seo-wp-docs-section">
                <h2>🛠️ Troubleshooting</h2>
                <p class="desc">Quick fixes for the most common issues.</p>
                <ol>
                    <li><strong>Database missing:</strong> Deactivate then reactivate the plugin to rebuild tables.</li>
                    <li><strong>404 on dashboard pages:</strong> Go to <em>Settings → Permalinks</em> and click Save Changes.</li>
                    <li><strong>Google API errors:</strong> Re-check the redirect URI matches exactly and the correct API is enabled. Re-authorise in Integrations.</li>
                    <li><strong>Sitemap not fetching:</strong> Open the sitemap URL in an incognito browser to confirm it's publicly accessible.</li>
                    <li><strong>Client can't log in:</strong> Confirm they're using the portal URL (not wp-admin). Send a password reset from <em>WordPress Admin → Users</em>.</li>
                    <li><strong>Check the Log:</strong> In the Admin Dashboard, click <strong>Log</strong> — it shows all errors and API activity.</li>
                </ol>
            </div>

            <p style="color:#999;font-size:12px;margin-top:8px;">
                SEO Client Reporting Dashboard Pro · v<?php echo esc_html( SEO_DASH_VERSION ); ?> · For the full interactive guide, open the Documentation tab inside the Admin Dashboard.
            </p>
        </div>
        <?php
    }

}
