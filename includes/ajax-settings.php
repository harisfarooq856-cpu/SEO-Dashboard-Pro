<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX handlers — Settings
 */

// ── Get all settings ───────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_settings', function () {
    seo_dash_verify_admin_ajax();

    $keys = [
        'brand_name', 'agency_url', 'support_email',
        'footer_text', 'admin_notify_emails',
        'frontend_page_id', 'visible_tabs',
        'plugin_version', 'install_date',
    ];
    $settings = [];
    foreach ( $keys as $key ) {
        $settings[ $key ] = SEO_Dash_Database::get_setting( $key, '' );
    }
    $client_pid = intval( get_option( 'seo_dash_client_page_id' ) );
    $admin_pid  = intval( get_option( 'seo_dash_admin_page_id' ) );
    $settings['frontend_page_url']   = $client_pid ? get_permalink( $client_pid ) : '';
    $settings['admin_dashboard_url'] = $admin_pid  ? get_permalink( $admin_pid )  : '';
    seo_dash_json_success( $settings );
} );

// ── Save general settings ─────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_settings', function () {
    seo_dash_verify_admin_ajax();

    $allowed = [
        'brand_name'          => 'text',
        'agency_url'          => 'url',
        'support_email'       => 'email',
        'footer_text'         => 'textarea',
        'admin_notify_emails' => 'text',
        'visible_tabs'        => 'text',
        'chatbot_model'       => 'text',
        'deepseek_model'      => 'text',
        'gemini_model'        => 'text',
        'groq_model'          => 'text',
        'cerebras_model'      => 'text',
        'active_provider'     => 'text',
    ];

    $saved_keys = [];
    foreach ( $allowed as $key => $type ) {
        if ( ! isset( $_POST[ $key ] ) ) continue;
        $raw = wp_unslash( $_POST[ $key ] );
        switch ( $type ) {
            case 'url':      $val = esc_url_raw( $raw );              break;
            case 'email':    $val = sanitize_email( $raw );           break;
            case 'textarea': $val = sanitize_textarea_field( $raw );  break;
            default:         $val = sanitize_text_field( $raw );      break;
        }
        SEO_Dash_Database::set_setting( $key, $val );
        $saved_keys[] = $key;
    }

    if ( isset( $_POST['auto_monthly_sync'] ) ) {
        $val = sanitize_text_field( $_POST['auto_monthly_sync'] ) === '1' ? '1' : '0';
        update_option( 'seo_dash_auto_monthly_sync_enabled', $val );
        $saved_keys[] = 'auto_monthly_sync';
    }

    if ( isset( $_POST['auto_sitemap_recrawl'] ) ) {
        $val = sanitize_text_field( $_POST['auto_sitemap_recrawl'] ) === '1' ? '1' : '0';
        update_option( 'seo_dash_auto_sitemap_recrawl_enabled', $val );
        $saved_keys[] = 'auto_sitemap_recrawl';
    }

    if ( isset( $_POST['auto_sitemap_freq'] ) ) {
        $freq = sanitize_key( $_POST['auto_sitemap_freq'] );
        if ( in_array( $freq, [ 'daily', 'weekly', 'monthly' ], true ) ) {
            update_option( 'seo_dash_auto_sitemap_recrawl_freq', $freq );
            // Reschedule cron if needed
            wp_clear_scheduled_hook( 'seo_dash_scheduled_sitemap_recrawl' );
            $sched = ( $freq === 'daily' ) ? 'daily' : ( ( $freq === 'monthly' ) ? 'seo_dash_monthly' : 'seo_dash_weekly' );
            wp_schedule_event( time(), $sched, 'seo_dash_scheduled_sitemap_recrawl' );
        }
        $saved_keys[] = 'auto_sitemap_freq';
    }

    if ( ! empty( $_POST['deepseek_api_key'] ) ) {
        $dskey = sanitize_text_field( wp_unslash( $_POST['deepseek_api_key'] ) );
        SEO_Dash_Database::set_setting( 'deepseek_api_key', seo_dash_sec_encrypt( $dskey ) );
        $saved_keys[] = 'deepseek_api_key (encrypted)';
    }

    if ( ! empty( $_POST['groq_api_key'] ) ) {
        $key = sanitize_text_field( wp_unslash( $_POST['groq_api_key'] ) );
        SEO_Dash_Database::set_setting( 'groq_api_key', seo_dash_sec_encrypt( $key ) );
        $saved_keys[] = 'groq_api_key (encrypted)';
    }

    if ( ! empty( $_POST['cerebras_api_key'] ) ) {
        $ckey = sanitize_text_field( wp_unslash( $_POST['cerebras_api_key'] ) );
        SEO_Dash_Database::set_setting( 'cerebras_api_key', seo_dash_sec_encrypt( $ckey ) );
        $saved_keys[] = 'cerebras_api_key (encrypted)';
    }

    if ( ! empty( $_POST['gemini_api_key'] ) ) {
        $gkey = sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ) );
        SEO_Dash_Database::set_setting( 'gemini_api_key', seo_dash_sec_encrypt( $gkey ) );
        $saved_keys[] = 'gemini_api_key (encrypted)';
    }


    if ( ! empty( $_POST['frontend_page_id'] ) ) {
        $page_id = intval( $_POST['frontend_page_id'] );
        if ( get_post( $page_id ) ) {
            SEO_Dash_Database::set_setting( 'frontend_page_id', $page_id );
            update_option( 'seo_dash_client_page_id', $page_id );
            update_option( 'seo_dash_flush_rewrites', '1' );
            $saved_keys[] = 'frontend_page_id';
        }
    }

    if ( isset( $_POST['brand_logo'] ) ) {
        SEO_Dash_Database::set_setting( 'brand_logo', esc_url_raw( wp_unslash( $_POST['brand_logo'] ) ) );
        $saved_keys[] = 'brand_logo';
    }
    if ( isset( $_POST['brand_logo_dark'] ) ) {
        SEO_Dash_Database::set_setting( 'brand_logo_dark', esc_url_raw( wp_unslash( $_POST['brand_logo_dark'] ) ) );
        $saved_keys[] = 'brand_logo_dark';
    }

    SEO_Dash_Database::log_activity(
        'settings_saved', 'success',
        'Keys updated: ' . ( $saved_keys ? implode( ', ', $saved_keys ) : 'none' ) . '.'
    );
    seo_dash_json_success( null, 'Settings saved.' );
} );

// ── Save design / theme options ────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_design', function () {
    seo_dash_verify_admin_ajax();

    $raw = isset( $_POST['design'] ) && is_array( $_POST['design'] )
           ? wp_unslash( $_POST['design'] )
           : [];

    $allowed = [
        'page_bg'           => 'color', 'header_bg'         => 'color', 'header_text'       => 'color',
        'nav_bg'            => 'color', 'nav_text'          => 'color', 'nav_active'        => 'color',
        'card_bg'           => 'color', 'card_border'       => 'color', 'primary_color'     => 'color',
        'table_header_bg'   => 'color', 'table_header_text' => 'color', 'table_row_bg'      => 'color',
        'table_row_text'    => 'color', 'table_row_hover'   => 'color', 'table_border'      => 'color',
        'footer_bg'         => 'color', 'footer_color'      => 'color',
        'card_radius'       => 'int',   'table_radius'      => 'int',   'table_font_size'   => 'int',
        'table_max_height'  => 'int',
        'font_family'       => 'text',  'card_shadow'       => 'text',
    ];

    $clean = [];
    foreach ( $allowed as $key => $type ) {
        if ( ! isset( $raw[ $key ] ) ) continue;
        $val = $raw[ $key ];
        switch ( $type ) {
            case 'color': $clean[ $key ] = seo_dash_sanitize_color( $val ); break;
            case 'int':   $clean[ $key ] = intval( $val );                  break;
            default:      $clean[ $key ] = sanitize_text_field( $val );     break;
        }
    }

    SEO_Dash_Database::set_setting( 'design_options', wp_json_encode( $clean ) );
    SEO_Dash_Database::log_activity( 'design_saved', 'success', count($clean) . ' design properties updated.' );
    seo_dash_json_success( $clean, 'Design saved.' );
} );

// ── Reset design to defaults ───────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_reset_design', function () {
    seo_dash_verify_admin_ajax();
    SEO_Dash_Database::delete_setting( 'design_options' );
    SEO_Dash_Database::log_activity( 'design_reset', 'warning', 'Dashboard design reset to defaults.' );
    seo_dash_json_success( null, 'Design reset to defaults.' );
} );

// ── Recreate the frontend page if it was deleted ───────────────────────────
add_action( 'wp_ajax_seo_dash_recreate_page', function () {
    seo_dash_verify_admin_ajax();

    $page_id = wp_insert_post( [
        'post_title'     => 'SEO Dashboard',
        'post_name'      => 'seo-dashboard-' . time(),
        'post_content'   => '[seo_dashboard]',
        'post_status'    => 'publish',
        'post_type'      => 'page',
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
    ] );

    if ( is_wp_error( $page_id ) ) {
        SEO_Dash_Database::log_activity( 'page_recreate_failed', 'error', $page_id->get_error_message() );
        seo_dash_json_error( $page_id->get_error_message() );
    }

    SEO_Dash_Database::set_setting( 'frontend_page_id', $page_id );
    update_option( 'seo_dash_client_page_id', $page_id );
    update_option( 'seo_dash_flush_rewrites', '1' );
    update_post_meta( $page_id, '_seo_dash_system_page', '1' );
    SEO_Dash_Database::log_activity( 'page_recreated', 'success', "Client portal page recreated (ID: {$page_id})." );
    seo_dash_json_success( [
        'page_id'  => $page_id,
        'page_url' => get_permalink( $page_id ),
    ], 'Dashboard page recreated.' );
} );

add_action( 'wp_ajax_seo_dash_remove_deepseek_key', function () {
    seo_dash_verify_admin_ajax();
    SEO_Dash_Database::set_setting( 'deepseek_api_key', '' );
    SEO_Dash_Database::log_activity( 'deepseek_key_removed', 'warning', 'DeepSeek API key was removed by admin.' );
    seo_dash_json_success( null, 'DeepSeek API key removed.' );
} );

add_action( 'wp_ajax_seo_dash_remove_gemini_key', function () {
    seo_dash_verify_admin_ajax();
    SEO_Dash_Database::set_setting( 'gemini_api_key', '' );
    SEO_Dash_Database::log_activity( 'gemini_key_removed', 'warning', 'Gemini API key was removed by admin.' );
    seo_dash_json_success( null, 'Gemini API key removed.' );
} );

add_action( 'wp_ajax_seo_dash_remove_groq_key', function () {
    seo_dash_verify_admin_ajax();
    SEO_Dash_Database::set_setting( 'groq_api_key', '' );
    SEO_Dash_Database::log_activity( 'groq_key_removed', 'warning', 'Groq API key was removed by admin.' );
    seo_dash_json_success( null, 'Groq API key removed.' );
} );

add_action( 'wp_ajax_seo_dash_remove_cerebras_key', function () {
    seo_dash_verify_admin_ajax();
    SEO_Dash_Database::set_setting( 'cerebras_api_key', '' );
    SEO_Dash_Database::log_activity( 'cerebras_key_removed', 'warning', 'Cerebras API key was removed by admin.' );
    seo_dash_json_success( null, 'Cerebras API key removed.' );
} );

add_action( 'wp_ajax_seo_dash_remove_rep_deepseek_key', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );
    delete_option( "seo_dash_chatbot_deepseek_{$rid}" );
    SEO_Dash_Database::log_activity( 'rep_deepseek_key_removed', 'warning', "DeepSeek API key removed for report {$rid}." );
    seo_dash_json_success( null, 'DeepSeek API key removed.' );
} );

add_action( 'wp_ajax_seo_dash_remove_rep_groq_key', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );
    delete_option( "seo_dash_chatbot_groq_{$rid}" );
    SEO_Dash_Database::log_activity( 'rep_groq_key_removed', 'warning', "Groq API key removed for report {$rid}." );
    seo_dash_json_success( null, 'Groq API key removed.' );
} );

add_action( 'wp_ajax_seo_dash_remove_rep_cerebras_key', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );
    delete_option( "seo_dash_chatbot_cerebras_{$rid}" );
    SEO_Dash_Database::log_activity( 'rep_cerebras_key_removed', 'warning', "Cerebras API key removed for report {$rid}." );
    seo_dash_json_success( null, 'Cerebras API key removed.' );
} );

add_action( 'wp_ajax_seo_dash_remove_rep_gemini_key', function () {
    seo_dash_verify_admin_ajax();
    $rid = intval( $_POST['report_id'] ?? 0 );
    if ( ! $rid ) seo_dash_json_error( 'Missing report ID.' );
    delete_option( "seo_dash_chatbot_gemini_{$rid}" );
    SEO_Dash_Database::log_activity( 'rep_gemini_key_removed', 'warning', "Gemini API key removed for report {$rid}." );
    seo_dash_json_success( null, 'Gemini API key removed.' );
} );

// ── Helper: sanitize a CSS color value ────────────────────────────────────
function seo_dash_sanitize_color( string $val ): string {
    $val = trim( $val );
    if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $val ) ) return $val;
    if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+(\s*,\s*[\d.]+)?\s*\)$/', $val ) ) return $val;
    if ( preg_match( '/^hsla?\(\s*\d+\s*,\s*\d+%\s*,\s*\d+%(\s*,\s*[\d.]+)?\s*\)$/', $val ) ) return $val;
    return '';
}

// ── Clear security log ─────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_clear_sec_log', function () {
    seo_dash_verify_admin_ajax();
    global $wpdb;
    SEO_Dash_Database::init();
    $wpdb->query( "TRUNCATE TABLE " . SEO_Dash_Database::$security_log );
    SEO_Dash_Database::log_activity( 'security_log_cleared', 'warning', 'Security event log was cleared by admin.' );
    seo_dash_json_success( null, 'Security log cleared.' );
} );

// ── Email / SMTP configuration ──────────────────────────────────────────────
//
// Settings keys used:
//   smtp_mode       'gmail' | 'other'
//   smtp_host       SMTP server hostname
//   smtp_port       SMTP port (587/465/25)
//   smtp_username   SMTP auth username (Gmail address for the Gmail tab)
//   smtp_password   Encrypted SMTP auth password / app password
//   smtp_from_name  "From" display name for outgoing emails
//   smtp_from_email "From" email address for outgoing emails
//
// Persist whatever SMTP fields were posted. Shared by the "Save Email
// Settings" and "Send Test Email" handlers (the test button saves the
// current form first, per the on-screen note).
if ( ! function_exists( 'seo_dash_persist_smtp_settings_from_post' ) ) {
function seo_dash_persist_smtp_settings_from_post(): void {
    $mode = sanitize_text_field( wp_unslash( $_POST['smtp_mode'] ?? 'gmail' ) );
    if ( ! in_array( $mode, [ 'gmail', 'brevo', 'other', 'gmail_oauth' ], true ) ) $mode = 'gmail';
    SEO_Dash_Database::set_setting( 'smtp_mode', $mode );

    if ( $mode === 'gmail' ) {
        // Gmail's host/port are fixed regardless of what's posted.
        SEO_Dash_Database::set_setting( 'smtp_host', 'smtp.gmail.com' );
        SEO_Dash_Database::set_setting( 'smtp_port', 587 );
    } elseif ( $mode === 'brevo' ) {
        // Brevo's relay host/port are fixed too — one less thing for a
        // non-technical user to get wrong.
        SEO_Dash_Database::set_setting( 'smtp_host', 'smtp-relay.brevo.com' );
        SEO_Dash_Database::set_setting( 'smtp_port', 587 );
    } else {
        if ( isset( $_POST['smtp_host'] ) ) {
            SEO_Dash_Database::set_setting( 'smtp_host', sanitize_text_field( wp_unslash( $_POST['smtp_host'] ) ) );
        }
        if ( isset( $_POST['smtp_port'] ) ) {
            $port = intval( $_POST['smtp_port'] );
            if ( ! in_array( $port, [ 587, 465, 25 ], true ) ) $port = 587;
            SEO_Dash_Database::set_setting( 'smtp_port', $port );
        }
    }

    if ( isset( $_POST['smtp_username'] ) ) {
        SEO_Dash_Database::set_setting( 'smtp_username', sanitize_text_field( wp_unslash( $_POST['smtp_username'] ) ) );
    }
    if ( ! empty( $_POST['smtp_password'] ) ) {
        $pass = sanitize_text_field( wp_unslash( $_POST['smtp_password'] ) );
        SEO_Dash_Database::set_setting( 'smtp_password', seo_dash_sec_encrypt( $pass ) );
    }
    if ( isset( $_POST['smtp_from_name'] ) ) {
        SEO_Dash_Database::set_setting( 'smtp_from_name', sanitize_text_field( wp_unslash( $_POST['smtp_from_name'] ) ) );
    }
    if ( isset( $_POST['smtp_from_email'] ) ) {
        SEO_Dash_Database::set_setting( 'smtp_from_email', sanitize_email( wp_unslash( $_POST['smtp_from_email'] ) ) );
    }
    if ( isset( $_POST['oauth_test_email'] ) ) {
        SEO_Dash_Database::set_setting( 'oauth_test_email', sanitize_email( wp_unslash( $_POST['oauth_test_email'] ) ) );
    }
    if ( isset( $_POST['admin_notify_emails'] ) ) {
        SEO_Dash_Database::set_setting( 'admin_notify_emails', sanitize_text_field( wp_unslash( $_POST['admin_notify_emails'] ) ) );
    }
    if ( isset( $_POST['smtp_override_enabled'] ) ) {
        SEO_Dash_Database::set_setting( 'smtp_override_enabled', $_POST['smtp_override_enabled'] === '1' ? true : false );
    }
}
}

// ── Save Email / SMTP settings ──────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_email_settings', function () {
    seo_dash_verify_admin_ajax();
    seo_dash_persist_smtp_settings_from_post();
    SEO_Dash_Database::log_activity( 'smtp_settings_saved', 'success', 'Email / SMTP settings updated.' );
    seo_dash_json_success( null, '✅ Email settings saved.' );
} );

// ── Send a test email using the (just-saved) SMTP settings ─────────────────
add_action( 'wp_ajax_seo_dash_send_test_email', function () {
    seo_dash_verify_admin_ajax();

    // "Saves current SMTP settings first" — persist whatever is in the form
    // before attempting to send, so the test reflects what's about to be saved.
    seo_dash_persist_smtp_settings_from_post();

    $host = trim( (string) SEO_Dash_Database::get_setting( 'smtp_host', '' ) );
    if ( ! $host ) {
        seo_dash_json_error( 'Please enter your SMTP host details first.' );
    }

    // Pick a destination: first admin notification email, else support email,
    // else the From address, else the current user's email.
    $to = '';
    $notify = (string) SEO_Dash_Database::get_setting( 'admin_notify_emails', '' );
    if ( $notify ) {
        foreach ( array_map( 'trim', explode( ',', $notify ) ) as $candidate ) {
            if ( is_email( $candidate ) ) { $to = $candidate; break; }
        }
    }
    if ( ! $to ) {
        $support = (string) SEO_Dash_Database::get_setting( 'support_email', '' );
        if ( is_email( $support ) ) $to = $support;
    }
    if ( ! $to ) {
        $from = (string) SEO_Dash_Database::get_setting( 'smtp_from_email', '' );
        if ( is_email( $from ) ) $to = $from;
    }
    if ( ! $to ) {
        $current = wp_get_current_user();
        if ( $current && is_email( $current->user_email ) ) $to = $current->user_email;
    }
    if ( ! $to ) $to = get_option( 'admin_email' );

    if ( ! is_email( $to ) ) {
        seo_dash_json_error( 'No valid destination email found. Add an address under "Admin Notification Emails" or "Support Email" in Settings.' );
    }

    $brand   = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
    $subject = "✅ {$brand} — SMTP Test Email";
    $body    = "This is a test email from your {$brand} SEO Dashboard.\n\n"
             . "If you're reading this, your SMTP configuration (host: {$host}) is working correctly.";

    $mail_error  = '';
    $catch_error = function ( $wp_error ) use ( &$mail_error ) {
        $mail_error = $wp_error->get_error_message();
    };
    add_action( 'wp_mail_failed', $catch_error );
    $sent = wp_mail( $to, $subject, $body );
    remove_action( 'wp_mail_failed', $catch_error );

    if ( $sent ) {
        SEO_Dash_Database::log_activity( 'smtp_test_email', 'success', "Test email sent to {$to} via {$host}." );
        seo_dash_json_success( [ 'to' => $to ], "✅ Test email sent to {$to}. Check the inbox (and spam folder)." );
    } else {
        SEO_Dash_Database::log_activity( 'smtp_test_email', 'error', "Test email to {$to} via {$host} failed: " . ( $mail_error ?: 'unknown error' ) );
        seo_dash_json_error( '❌ Failed to send test email.' . ( $mail_error ? ' ' . $mail_error : ' Check your SMTP host, port, username and password.' ) );
    }
} );

// ── Email Authentication Health Check (SPF / DKIM / DMARC) ─────────────────
// Looks up the sending domain's own DNS records live and reports back in
// plain English. This is what lets a non-technical user see "what's missing"
// without ever hearing the word "DKIM" explained to them from scratch, and
// without needing an external tool like mail-tester.com.
//
// Honesty notes baked into the logic:
//  - DKIM selectors aren't standardized, so we check a list of the common
//    ones (Google Workspace, Microsoft 365, Brevo, and a few generic
//    defaults). A "not found" result means none of those common selectors
//    exist — not a 100% guarantee no DKIM exists anywhere, just the best a
//    generic checker can do without knowing the provider's exact selector.
//  - We never invent record values we can't actually know (e.g. a real DKIM
//    public key has to come from the provider) — we only suggest content for
//    SPF/DMARC, which is provider-agnostic enough to compute safely.
add_action( 'wp_ajax_seo_dash_check_email_auth', function () {
    seo_dash_verify_admin_ajax();

    $smtp_mode = (string) SEO_Dash_Database::get_setting( 'smtp_mode', 'gmail' );
    $from      = (string) SEO_Dash_Database::get_setting( 'smtp_from_email', '' );
    if ( ! $from || ! is_email( $from ) ) $from = (string) get_option( 'admin_email' );

    $domain = is_email( $from ) ? substr( strrchr( $from, '@' ), 1 ) : '';
    $domain = strtolower( trim( $domain ) );

    if ( ! $domain ) {
        seo_dash_json_error( 'Set a "From Email Address" in your SMTP settings above first, then run this check.' );
    }

    if ( ! function_exists( 'dns_get_record' ) ) {
        seo_dash_json_success( [
            'domain'           => $domain,
            'dns_unavailable'  => true,
        ], 'Your server doesn\'t allow live DNS lookups. Use mail-tester.com to check this domain instead.' );
        return;
    }

    // ── SPF: a TXT record at the root domain starting with v=spf1 ──────────
    $spf_found  = false;
    $spf_record = '';
    $spf_rows   = @dns_get_record( $domain, DNS_TXT );
    if ( is_array( $spf_rows ) ) {
        foreach ( $spf_rows as $row ) {
            $txt = $row['txt'] ?? '';
            if ( stripos( $txt, 'v=spf1' ) === 0 ) {
                $spf_found  = true;
                $spf_record = $txt;
                break;
            }
        }
    }

    // ── DMARC: a TXT record at _dmarc.domain starting with v=DMARC1 ────────
    $dmarc_found  = false;
    $dmarc_record = '';
    $dmarc_policy = '';
    $dmarc_rows   = @dns_get_record( '_dmarc.' . $domain, DNS_TXT );
    if ( is_array( $dmarc_rows ) ) {
        foreach ( $dmarc_rows as $row ) {
            $txt = $row['txt'] ?? '';
            if ( stripos( $txt, 'v=DMARC1' ) === 0 ) {
                $dmarc_found  = true;
                $dmarc_record = $txt;
                if ( preg_match( '/p=([a-z]+)/i', $txt, $m ) ) $dmarc_policy = strtolower( $m[1] );
                break;
            }
        }
    }

    // ── DKIM: check a list of common selectors (TXT or CNAME) ──────────────
    $dkim_selectors = [ 'google', 'selector1', 'selector2', 'default', 'mail', 'mail2', 'k1', 's1', 's2', 'dkim', 'brevo' ];
    $dkim_found     = false;
    $dkim_selector  = '';
    foreach ( $dkim_selectors as $sel ) {
        $host = $sel . '._domainkey.' . $domain;
        $txt_rows = @dns_get_record( $host, DNS_TXT );
        if ( is_array( $txt_rows ) && ! empty( $txt_rows ) ) {
            $dkim_found    = true;
            $dkim_selector = $sel;
            break;
        }
        $cname_rows = @dns_get_record( $host, DNS_CNAME );
        if ( is_array( $cname_rows ) && ! empty( $cname_rows ) ) {
            $dkim_found    = true;
            $dkim_selector = $sel;
            break;
        }
    }

    // ── Suggested SPF content (only when we can actually know it) ──────────
    $spf_suggestion = '';
    if ( ! $spf_found ) {
        if ( $smtp_mode === 'gmail' ) {
            $spf_suggestion = 'v=spf1 include:_spf.google.com ~all';
        } elseif ( $smtp_mode === 'other' ) {
            $spf_suggestion = ''; // depends on the provider — can't guess safely
        }
        // Brevo doesn't require an SPF include (its envelope sender is its own
        // domain), so we deliberately don't suggest one for 'brevo' mode.
    }

    $dmarc_suggestion = ! $dmarc_found
        ? 'v=DMARC1; p=none; rua=mailto:' . $from
        : '';

    seo_dash_json_success( [
        'domain'           => $domain,
        'smtp_mode'        => $smtp_mode,
        'spf'              => [ 'found' => $spf_found,   'record' => $spf_record,   'suggestion' => $spf_suggestion ],
        'dkim'             => [ 'found' => $dkim_found,   'selector' => $dkim_selector ],
        'dmarc'            => [ 'found' => $dmarc_found,  'record' => $dmarc_record, 'policy' => $dmarc_policy, 'suggestion' => $dmarc_suggestion ],
    ] );
} );

// Runs on every request; if no SMTP host has been configured, PHPMailer is
// left untouched and WordPress falls back to its default mail transport.
// NOTE: $phpmailer MUST be passed by reference (&$phpmailer) so that the
// property assignments below mutate the actual PHPMailer instance that
// WordPress will use to send the email. Without the &, changes are made to
// a local copy and silently discarded — causing WordPress to fall back to
// the server's default php mail() transport (the "via inceptial.team" bug).
add_action( 'phpmailer_init', function ( &$phpmailer ) {
    // If Gmail OAuth is connected, the ajax-gmail-oauth.php hook handles this — skip SMTP
    if ( function_exists( 'seo_dash_oauth_is_connected' ) && seo_dash_oauth_is_connected() ) return;

    $host = trim( (string) SEO_Dash_Database::get_setting( 'smtp_host', '' ) );
    if ( ! $host ) return;

    // Extract just the integer port number. The UI <option> values are plain
    // integers (587, 465, 25) but we defensively strip any trailing text
    // (e.g. "587 — TLS (Recommended)") in case the saved value ever contains it.
    $port_raw = (string) SEO_Dash_Database::get_setting( 'smtp_port', 587 );
    $port     = intval( $port_raw ); // intval() stops at the first non-numeric char
    if ( ! $port ) $port = 587;

    $username     = (string) SEO_Dash_Database::get_setting( 'smtp_username', '' );
    $password_enc = (string) SEO_Dash_Database::get_setting( 'smtp_password', '' );
    $password     = $password_enc !== '' ? seo_dash_sec_decrypt( $password_enc ) : '';
    $from_email   = (string) SEO_Dash_Database::get_setting( 'smtp_from_email', '' );
    $from_name    = (string) SEO_Dash_Database::get_setting( 'smtp_from_name', '' );

    $phpmailer->isSMTP();
    $phpmailer->Host     = $host;
    $phpmailer->Port     = $port;
    // Only enable SMTPAuth when BOTH username and password are present.
    $phpmailer->SMTPAuth = ( $username !== '' && $password !== '' );
    if ( $username !== '' ) $phpmailer->Username = $username;
    if ( $password !== '' ) $phpmailer->Password = $password;

    // Force the correct encryption protocol based on the port number.
    if ( $port === 465 ) {
        $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; // 'ssl'
    } elseif ( $port === 25 ) {
        $phpmailer->SMTPSecure  = '';
        $phpmailer->SMTPAutoTLS = false;
    } else {
        // 587 (and any other port) → STARTTLS
        $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // 'tls'
    }

    if ( $from_email && is_email( $from_email ) ) {
        // Align the envelope sender (Return-Path) with the From address so
        // SPF passes and Gmail does not add a "via …" impersonation warning.
        $phpmailer->From     = $from_email;
        $phpmailer->FromName = $from_name ?: $from_email;
    }
} );


