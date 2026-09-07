<?php
/**
 * Plugin Name: SEO Client Reporting Dashboard Pro
 * Description: Frontend SEO reporting dashboard — full admin panel on the frontend, client portals, Groq AI, Google integrations.
 * Version: 7.0.2
 * Author: Your Agency
 * Requires PHP: 7.4
 * Text Domain: seo-dashboard
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SEO_DASH_VERSION', '7.0.2' );
define( 'SEO_DASH_PATH',     plugin_dir_path( __FILE__ ) );
define( 'SEO_DASH_URL',      plugin_dir_url( __FILE__ ) );
define( 'SEO_DASH_BASENAME', plugin_basename( __FILE__ ) );

// ── Boot on plugins_loaded ─────────────────────────────────────────────────
// All requires are deferred until plugins_loaded so that:
//  a) all other plugins are loaded before our hooks are registered, and
//  b) WordPress conditional query tags (is_404, is_feed, is_search, etc.)
//     are never called before the main WP_Query is initialised — avoiding
//     the "_doing_it_wrong" notices those functions emit when invoked early.
add_action( 'plugins_loaded', function () {

    require_once SEO_DASH_PATH . 'includes/class-database.php';
    require_once SEO_DASH_PATH . 'includes/class-crypto.php';
    require_once SEO_DASH_PATH . 'includes/class-job-queue.php';
    require_once SEO_DASH_PATH . 'includes/helpers.php';
    require_once SEO_DASH_PATH . 'includes/class-license.php';
    require_once SEO_DASH_PATH . 'seo-dash-security.php';
    require_once SEO_DASH_PATH . 'includes/ajax-license.php';
    require_once SEO_DASH_PATH . 'includes/ajax-gmail-oauth.php';

    // Auto-upgrade DB tables when version bumps or tables are missing.
    SEO_Dash_Database::init();
    if ( get_option( 'seo_dash_db_version' ) !== SEO_DASH_VERSION ) {
        SEO_Dash_Database::create_tables();
        update_option( 'seo_dash_db_version', SEO_DASH_VERSION );
        // Flag that rewrite rules need flushing after init registers them.
        update_option( 'seo_dash_flush_rewrites', '1' );
    } else {
        // Also check tables exist even if version matches (e.g. manual DB restore).
        global $wpdb;
        $tbl = SEO_Dash_Database::$reports;
        $log_tbl = SEO_Dash_Database::$activity_log;
        if ( $tbl && ( ! $wpdb->get_var( "SHOW TABLES LIKE '$tbl'" ) || ! $wpdb->get_var( "SHOW TABLES LIKE '$log_tbl'" ) ) ) {
            SEO_Dash_Database::create_tables();
        }
    }

    // ── Migrate existing clients: create individual pages if missing ──────
    // Runs once after plugin update (version bump triggers this block).
    if ( get_option( 'seo_dash_db_version' ) !== SEO_DASH_VERSION ) {
        add_action( 'init', function () {
            global $wpdb;
            $clients_table = SEO_Dash_Database::$clients;
            // Find clients with no wp_page_id yet.
            $clients = $wpdb->get_results(
                "SELECT id, name, wp_user_id, wp_page_id FROM {$clients_table} WHERE wp_page_id IS NULL OR wp_page_id = 0",
                ARRAY_A
            );
            foreach ( (array) $clients as $c ) {
                $wp_user = $c['wp_user_id'] ? get_userdata( (int) $c['wp_user_id'] ) : null;
                $username = $wp_user ? $wp_user->user_login : 'client-' . $c['id'];
                $page_data = SEO_Dash_Frontend::create_client_page( $c['name'], $username );
                if ( $page_data['page_id'] ) {
                    SEO_Dash_Database::update_client( (int) $c['id'], [
                        'wp_page_id'    => $page_data['page_id'],
                        'dashboard_url' => $page_data['page_url'],
                    ] );
                }
            }
        }, 20 );
    }

    // Auto-create/repair the client portal page if missing.
    $client_pid = (int) get_option( 'seo_dash_client_page_id' );
    if ( ! $client_pid || ! get_post( $client_pid ) || get_post_status( $client_pid ) !== 'publish' ) {
        $new_pid = wp_insert_post( [
            'post_title'     => 'SEO Dashboard',
            'post_name'      => 'seo-dashboard',
            'post_content'   => '[seo_dashboard]',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => 1,
            'comment_status' => 'closed',
        ] );
        if ( $new_pid && ! is_wp_error( $new_pid ) ) {
            update_option( 'seo_dash_client_page_id', $new_pid );
            update_option( 'seo_dash_flush_rewrites', '1' );
        }
    }

    require_once SEO_DASH_PATH . 'includes/class-roles.php';
    require_once SEO_DASH_PATH . 'includes/class-frontend.php';
    require_once SEO_DASH_PATH . 'includes/class-frontend-render.php';
    require_once SEO_DASH_PATH . 'includes/class-frontend-admin.php';
    require_once SEO_DASH_PATH . 'includes/class-wp-admin.php';
    require_once SEO_DASH_PATH . 'includes/class-api.php';

    SEO_Dash_Roles::boot();
    SEO_Dash_Frontend::init();
    SEO_Dash_Frontend_Admin::init();
    SEO_Dash_WP_Admin::init();
    SEO_Dash_API::init();
    SEO_Dash_Job_Queue::init();
    SEO_Dash_License::init();
} );

// ── Activation ─────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, function () {
    // The activation hook runs in a request where THIS plugin's own
    // plugins_loaded callback has not fired yet, so its normal require_once
    // calls have not run. Load the class explicitly here — otherwise
    // SEO_Dash_Database is undefined and activation dies with
    // "Class \"SEO_Dash_Database\" not found".
    require_once SEO_DASH_PATH . 'includes/class-database.php';

    SEO_Dash_Database::init();
    SEO_Dash_Database::create_tables();

    // Ensure seo_client role exists.
    if ( ! get_role( 'seo_client' ) ) {
        add_role( 'seo_client', 'SEO Client', [ 'read' => true ] );
    }

    // Auto-create admin dashboard page.
    $admin_pid = (int) get_option( 'seo_dash_admin_page_id' );
    if ( ! $admin_pid || ! get_post( $admin_pid ) ) {
        $admin_pid = wp_insert_post( [
            'post_title'     => 'SEO Admin Dashboard',
            'post_name'      => 'seo-admin-dashboard',
            'post_content'   => '[seo_admin_dashboard]',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => get_current_user_id() ?: 1,
            'comment_status' => 'closed',
        ] );
        update_option( 'seo_dash_admin_page_id', $admin_pid );
    }

    // Auto-create client portal page.
    $client_pid = (int) get_option( 'seo_dash_client_page_id' );
    if ( ! $client_pid || ! get_post( $client_pid ) ) {
        $client_pid = wp_insert_post( [
            'post_title'     => 'SEO Dashboard',
            'post_name'      => 'seo-dashboard',
            'post_content'   => '[seo_dashboard]',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => get_current_user_id() ?: 1,
            'comment_status' => 'closed',
        ] );
        update_option( 'seo_dash_client_page_id', $client_pid );
    }

    update_option( 'seo_dash_db_version', SEO_DASH_VERSION );
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

// ── Plugin list shortcut ───────────────────────────────────────────────────
add_filter( 'plugin_action_links_' . SEO_DASH_BASENAME, function ( $links ) {
    $pid = (int) get_option( 'seo_dash_admin_page_id' );
    $url = $pid ? get_permalink( $pid ) : admin_url( 'admin.php?page=seo-dashboard' );
    array_unshift( $links, '<a href="' . esc_url( $url ) . '" target="_blank">Open Dashboard</a>' );
    return $links;
} );

