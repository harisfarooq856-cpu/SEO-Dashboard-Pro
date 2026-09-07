<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX handlers — Clients & Assignments
 *
 * Actions:
 *   seo_dash_get_clients           — list all clients
 *   seo_dash_get_clients_paged     — list clients in batches (Load More)
 *   seo_dash_save_client           — create or update client
 *   seo_dash_delete_client         — soft-delete client
 *   seo_dash_create_client_user    — create WP user + client record in one go
 *   seo_dash_assign_client         — assign client to report
 *   seo_dash_unassign_client       — remove client from report
 *   seo_dash_unassign_all_clients  — remove all clients from report
 *   seo_dash_get_report_clients    — list clients assigned to a report
 *   seo_dash_send_assigned_email   — send "report assigned" email
 */

// ── List all clients ───────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_clients', function () {
    seo_dash_verify_admin_ajax();
    seo_dash_json_success( SEO_Dash_Database::get_clients() );
} );

// ── Get clients paged (Load More) ──────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_clients_paged', function () {
    seo_dash_verify_admin_ajax();
    $limit  = min( 5, max( 1, intval( $_POST['limit']  ?? 5 ) ) );
    $offset = max( 0, intval( $_POST['offset'] ?? 0 ) );
    $clients = SEO_Dash_Database::get_clients_paged( $limit, $offset );

    $client_page_url = get_permalink( intval( get_option( 'seo_dash_client_page_id' ) ) );

    foreach ( $clients as &$c ) {
        $c['pass_display']  = '';
        $c['client_link']   = $c['dashboard_url'] ? $c['dashboard_url'] : ( $client_page_url ?: '' );
        $c['has_dashboard'] = (bool) $c['dashboard_url'];
    }

    seo_dash_json_success( [
        'clients' => $clients,
        'total'   => SEO_Dash_Database::count_clients(),
        'offset'  => $offset + count( $clients ),
    ] );
} );

// ── Save (create / update) a client ───────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_client', function () {
    seo_dash_verify_admin_ajax();

    $id = intval( $_POST['client_id'] ?? 0 );
    $data = [
        'name'    => sanitize_text_field( wp_unslash( $_POST['name']    ?? '' ) ),
        'email'   => sanitize_email( wp_unslash( $_POST['email']   ?? '' ) ),
        'company' => sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) ),
        'phone'   => sanitize_text_field( wp_unslash( $_POST['phone']   ?? '' ) ),
        'notes'   => sanitize_textarea_field( wp_unslash( $_POST['notes']   ?? '' ) ),
    ];

    if ( empty( $data['name'] ) ) seo_dash_json_error( 'Client name is required.' );

    if ( $id ) {
        SEO_Dash_Database::update_client( $id, $data );
        SEO_Dash_Database::log_activity( 'client_updated', 'success', "Client \"{$data['name']}\" updated.", 'client', $id, $data['name'] );
        seo_dash_json_success( [ 'client_id' => $id ], 'Client updated.' );
    } else {
        $new_id = SEO_Dash_Database::insert_client( $data );
        if ( ! $new_id ) seo_dash_json_error( 'Failed to save client.' );
        SEO_Dash_Database::log_activity( 'client_created', 'success', "New client \"{$data['name']}\" created.", 'client', $new_id, $data['name'] );
        seo_dash_json_success( [ 'client_id' => $new_id ], 'Client created.' );
    }
} );

// ── Delete a client ────────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_delete_client', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['client_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing client ID.' );

    $client = SEO_Dash_Database::get_client( $id );
    if ( ! $client ) seo_dash_json_error( 'Client not found.' );

    $name   = $client['name'] ?? "ID {$id}";
    $wp_uid = intval( $client['wp_user_id'] ?? 0 );

    // 1. Remove from ALL report assignments (both tables).
    global $wpdb;
    $wpdb->delete( SEO_Dash_Database::$report_clients, [ 'client_id' => $id ], [ '%d' ] );

    // 2. Delete the client's dedicated dashboard page.
    $client_page_id = intval( $client['wp_page_id'] ?? 0 );
    if ( $client_page_id ) {
        SEO_Dash_Frontend::delete_client_page( $client_page_id );
    }

    // 3. Delete the plugin client record.
    SEO_Dash_Database::delete_client( $id );

    // 4. Hard-delete the WordPress user account completely.
    if ( $wp_uid && get_userdata( $wp_uid ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        // Reassign any posts to admin (user ID 1) before deleting.
        wp_delete_user( $wp_uid, 1 );
    }

    SEO_Dash_Database::log_activity( 'client_deleted', 'warning', "Client \"{$name}\" and WP user deleted completely.", 'client', $id, $name );
    seo_dash_json_success( null, 'Client deleted completely.' );
} );

// ── Create WP user + client record in one go ───────────────────────────────
add_action( 'wp_ajax_seo_dash_create_client_user', function () {
    seo_dash_verify_admin_ajax();

    $first_name  = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
    $last_name   = sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) );
    $email       = sanitize_email( wp_unslash( $_POST['email']      ?? '' ) );
    $password    = wp_unslash( $_POST['password'] ?? '' );
    $company     = sanitize_text_field( wp_unslash( $_POST['company']    ?? '' ) );
    $report_id   = intval( $_POST['report_id'] ?? 0 );

    if ( ! $email || ! is_email( $email ) ) seo_dash_json_error( 'Valid email required.' );
    if ( email_exists( $email ) ) seo_dash_json_error( 'A user with this email already exists.' );

    $display_name = trim( $first_name . ' ' . $last_name ) ?: $email;
    $username     = sanitize_user( strtolower( str_replace( ' ', '.', $display_name ) ) );

    // Ensure unique username.
    $base = $username;
    $i    = 1;
    while ( username_exists( $username ) ) {
        $username = $base . $i++;
    }

    if ( empty( $password ) ) {
        $password = wp_generate_password( 12, false );
    }

    $pwd_generated = empty( wp_unslash( $_POST['password'] ?? '' ) );
    $wp_user_id = wp_create_user( $username, $password, $email );
    if ( is_wp_error( $wp_user_id ) ) {
        seo_dash_json_error( $wp_user_id->get_error_message() );
    }
    // Cache the temporary plain password in memory so welcome email can include it.
    $GLOBALS['_seo_dash_temp_plain_password'] = $password;

    // Assign seo_client role.
    $user = new WP_User( $wp_user_id );
    $user->set_role( 'seo_client' );
    wp_update_user( [
        'ID'           => $wp_user_id,
        'display_name' => $display_name,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
    ] );

    // Create a dedicated WP page for this client.
    $page_data     = SEO_Dash_Frontend::create_client_page( $display_name, $username );
    $dashboard_url = $page_data['page_url'] ?: SEO_Dash_Frontend::client_url( $username );
    $wp_page_id    = $page_data['page_id'];
    $shortcode     = '[seo_dashboard user="' . esc_attr( $username ) . '"]';

    // Create client record.
    $client_id = SEO_Dash_Database::insert_client( [
        'wp_user_id'    => $wp_user_id,
        'wp_page_id'    => $wp_page_id,
        'name'          => $display_name,
        'email'         => $email,
        'company'       => $company,
        'dashboard_url' => $dashboard_url,
        'shortcode'     => $shortcode,
    ] );

    // Optionally assign to a report.
    if ( $report_id && $client_id ) {
        SEO_Dash_Database::assign_client( $report_id, $client_id );
    }

    seo_dash_json_success( [
        'client_id'     => $client_id,
        'wp_user_id'    => $wp_user_id,
        'username'      => $username,
        'dashboard_url' => $dashboard_url,
        'shortcode'     => $shortcode,
        'password'      => $pwd_generated ? $password : '',
    ], 'Client user created.' );
    SEO_Dash_Database::log_activity(
        'client_user_created', 'success',
        "WP user \"{$username}\" ({$email}) created and linked to client ID {$client_id}.",
        'client', $client_id, $display_name
    );
} );

// ── Assign a client to a report ───────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_assign_client', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $client_id = intval( $_POST['client_id'] ?? 0 );
    if ( ! $report_id || ! $client_id ) seo_dash_json_error( 'Missing IDs.' );

    SEO_Dash_Database::assign_client( $report_id, $client_id );
    SEO_Dash_Database::log_activity( 'client_assigned', 'info', "Client ID {$client_id} assigned to report ID {$report_id}.", 'report', $report_id );
    seo_dash_json_success( null, 'Client assigned.' );
} );

// ── Shared email template builder ─────────────────────────────────────────────
function seo_dash_build_email_html( array $args ): string {
    $primary    = $args['primary']    ?? '#6366f1';
    $brand_esc  = $args['brand_esc']  ?? '';
    $name_esc   = $args['name_esc']   ?? '';
    $intro      = $args['intro']      ?? '';
    $url        = $args['url']        ?? '';
    $btn_label  = $args['btn_label']  ?? 'View Dashboard';
    $year       = date('Y');
    $rows_html  = $args['rows_html']  ?? '';   // pre-built <tr>…</tr> HTML
    $extra_html = $args['extra_html'] ?? '';   // any extra block after table

    $table_html = '';
    if ( $rows_html ) {
        $table_html = <<<HTML
      <div style="margin:24px 0;padding:0;border-radius:10px;border:1px solid #e2e8f0;overflow:hidden;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:14px;">
          <tbody>
            {$rows_html}
          </tbody>
        </table>
      </div>
HTML;
    }

    $btn_html = '';
    if ( $url ) {
        $btn_html = <<<HTML
      <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:28px 0 20px;">
        <tr>
          <td align="center">
            <a href="{$url}" target="_blank"
               style="display:inline-block;padding:14px 36px;background-color:{$primary};color:#ffffff !important;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;letter-spacing:0.3px;">
              {$btn_label}
            </a>
          </td>
        </tr>
      </table>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{$brand_esc}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f1f5f9;padding:40px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;">

          <!-- Header -->
          <tr>
            <td style="background:{$primary};border-radius:12px 12px 0 0;padding:28px 32px;text-align:center;">
              <h1 style="margin:0;color:#ffffff;font-size:26px;font-weight:800;letter-spacing:-0.5px;">{$brand_esc}</h1>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="background:#ffffff;padding:36px 40px 28px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">
              <p style="margin:0 0 6px;font-size:17px;font-weight:600;color:#1e293b;">Hello {$name_esc},</p>
              <p style="margin:10px 0 0;font-size:15px;color:#475569;line-height:1.7;">{$intro}</p>
              {$table_html}
              {$btn_html}
              {$extra_html}
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;padding:18px 32px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#94a3b8;">&copy; {$year} {$brand_esc}. All rights reserved.</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

// ── Helper: one table row ──────────────────────────────────────────────────────
function seo_dash_email_row( string $label, string $value, bool $last = false ): string {
    $border = $last ? '' : 'border-bottom:1px solid #e2e8f0;';
    return <<<HTML
<tr>
  <td style="width:38%;padding:12px 16px;{$border}background:#f8fafc;color:#64748b;font-weight:600;font-size:13px;white-space:nowrap;vertical-align:top;">{$label}</td>
  <td style="padding:12px 16px;{$border}background:#ffffff;color:#0f172a;font-size:14px;word-break:break-all;vertical-align:top;">{$value}</td>
</tr>
HTML;
}

// ── Helper: security note shown instead of printing credentials in email ──────
function seo_dash_email_security_note(): string {
    $wp_user = isset( $GLOBALS['_seo_dash_email_user_id'] ) ? get_userdata( (int) $GLOBALS['_seo_dash_email_user_id'] ) : null;
    $uname   = $wp_user ? esc_html( $wp_user->user_login ) : '';
    $ptext   = '';
    if ( $uname ) {
        $ptext = '<div style="margin-top:18px;padding:13px 16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;font-size:13px;color:#0369a1;line-height:1.7;">'
               . '<strong>Your login details:</strong><br>'
               . 'Username: <code style="background:#e0f2fe;padding:1px 5px;border-radius:3px;">' . $uname . '</code><br>'
               . '<span style="font-size:12px;color:#0284c7;">You can access your account instantly using the direct link above, or change your password any time from Account Settings inside your dashboard.</span>'
               . '</div>';
    }
    return $ptext;
}

// ── Helper to send client welcome email ───────────────────────────────────────
function seo_dash_send_client_welcome_email( int $client_id ): bool {
    global $wpdb;
    $client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . SEO_Dash_Database::$clients . " WHERE id = %d", $client_id ), ARRAY_A );
    if ( ! $client || ! $client['email'] || ! is_email( $client['email'] ) ) return false;

    $brand_name    = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
    $primary       = SEO_Dash_Database::get_setting( 'company_brand_color', '#6366f1' );
    $admin_from    = get_option( 'admin_email' );
    $brand_esc     = esc_html( $brand_name );
    $name_esc      = esc_html( $client['name'] );
    $base_dashboard_url = $client['dashboard_url'] ?: admin_url();
    // Wrap with a one-time auto-login token so the button logs them straight in.
    $dashboard_url = ! empty( $client['wp_user_id'] )
        ? SEO_Dash_Frontend::generate_login_link( intval( $client['wp_user_id'] ), $base_dashboard_url )
        : $base_dashboard_url;
    $dashboard_url = esc_url( $dashboard_url );

    // Fetch assigned reports for this client
    $rids = SEO_Dash_Database::get_client_report_ids( $client_id );
    $report_rows = '';
    if ( ! empty( $rids ) ) {
        foreach ( $rids as $rid ) {
            $r = SEO_Dash_Database::get_report( intval( $rid ) );
            if ( $r ) $report_rows .= seo_dash_email_row( 'Report', esc_html( $r['title'] ) );
        }
    }

    // Strip last border from last row via str_replace trick
    $rows_html = preg_replace( '/border-bottom:1px solid #e2e8f0;(?=(?:(?!border-bottom).)*$)/s', '', $report_rows );

    $GLOBALS['_seo_dash_email_user_id'] = $client['wp_user_id'] ?? 0;
    $body = seo_dash_build_email_html( [
        'primary'    => $primary,
        'brand_esc'  => $brand_esc,
        'name_esc'   => $name_esc,
        'intro'      => 'Your SEO dashboard account has been created. Click the button below to access your dashboard instantly.',
        'url'        => $dashboard_url,
        'btn_label'  => 'Go to My Dashboard',
        'rows_html'  => $rows_html,
        'extra_html' => seo_dash_email_security_note(),
    ] );
    unset( $GLOBALS['_seo_dash_email_user_id'] );

    $subject = "Welcome to {$brand_esc} — Your Dashboard is Ready";
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $brand_esc . ' <' . $admin_from . '>',
        'Reply-To: ' . $admin_from,
    ];

    return wp_mail( $client['email'], $subject, $body, $headers );
}

// ── Resend client welcome email ─────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_resend_client_welcome_mail', function () {
    seo_dash_verify_admin_ajax();
    $client_id = intval($_POST['client_id'] ?? 0);
    if (!$client_id) seo_dash_json_error('Invalid client ID.');
    if (seo_dash_send_client_welcome_email($client_id)) {
        seo_dash_json_success(null, 'Welcome email sent.');
    } else {
        seo_dash_json_error('Failed to send welcome email.');
    }
});

// ── Remove a single client from a report ──────────────────────────────────
add_action( 'wp_ajax_seo_dash_unassign_client', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $client_id = intval( $_POST['client_id'] ?? 0 );
    if ( ! $report_id || ! $client_id ) seo_dash_json_error( 'Missing IDs.' );

    SEO_Dash_Database::unassign_client( $report_id, $client_id );
    seo_dash_json_success( null, 'Client removed from report.' );
} );

// ── Remove all clients from a report ─────────────────────────────────────
add_action( 'wp_ajax_seo_dash_unassign_all_clients', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
    SEO_Dash_Database::unassign_all_clients( $report_id );
    seo_dash_json_success( null, 'All clients removed.' );
} );

// ── Get clients assigned to a report ──────────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_report_clients', function () {
    seo_dash_verify_admin_ajax();
    $report_id  = intval( $_GET['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    $client_ids = SEO_Dash_Database::get_report_client_ids( $report_id );
    $clients    = [];
    foreach ( $client_ids as $cid ) {
        $c = SEO_Dash_Database::get_client( $cid );
        if ( $c ) $clients[] = $c;
    }
    seo_dash_json_success( $clients );
} );

// ── Send "report assigned" email ───────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_send_assigned_email', function () {
    seo_dash_verify_admin_ajax();

    $report_id  = intval( $_POST['report_id']  ?? 0 );
    $client_ids = array_map( 'intval', (array) ( $_POST['client_ids'] ?? [] ) );
    if ( ! $report_id || empty( $client_ids ) ) seo_dash_json_error( 'Missing data.' );

    $report     = SEO_Dash_Database::get_report( $report_id );
    $brand_name = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
    $primary    = SEO_Dash_Database::get_setting( 'company_brand_color', '#6366f1' );
    $admin_from = get_option( 'admin_email' );
    $brand_esc  = esc_html( $brand_name );
    $report_title = esc_html( $report['title'] ?? 'Your SEO Report' );
    $sent   = [];
    $failed = [];

    foreach ( $client_ids as $cid ) {
        $client = SEO_Dash_Database::get_client( $cid );
        if ( ! $client || empty( $client['email'] ) ) continue;

        // Dashboard URL
        $dashboard_url = $client['dashboard_url'];
        if ( ! $dashboard_url && ! empty( $client['wp_user_id'] ) ) {
            $wp_user_obj = get_userdata( intval( $client['wp_user_id'] ) );
            if ( $wp_user_obj ) {
                $dashboard_url = SEO_Dash_Frontend::client_url( $wp_user_obj->user_login );
            }
        }
        $dashboard_url = $dashboard_url ?: admin_url();
        // Wrap with a one-time auto-login token so the button logs them straight in.
        if ( ! empty( $client['wp_user_id'] ) ) {
            $dashboard_url = SEO_Dash_Frontend::generate_login_link( intval( $client['wp_user_id'] ), $dashboard_url );
        }
        $url_esc   = esc_url( $dashboard_url );
        $name_esc  = esc_html( $client['name'] );

        // Build rows: just the report name — no credentials in the email body.
        $rows_html = seo_dash_email_row( 'Report', $report_title, true );

        $body = seo_dash_build_email_html( [
            'primary'    => $primary,
            'brand_esc'  => $brand_esc,
            'name_esc'   => $name_esc,
            'intro'      => "Your SEO report <strong>{$report_title}</strong> has been updated with the latest data. Click the button below to access your dashboard instantly.",
            'url'        => $url_esc,
            'btn_label'  => 'View My Dashboard',
            'rows_html'  => $rows_html,
            'extra_html' => seo_dash_email_security_note(),
        ] );

        $subject = "Your SEO Report from {$brand_esc} is Ready — {$report_title}";
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $brand_esc . ' <' . $admin_from . '>',
            'Reply-To: ' . $admin_from,
        ];

        $ok = wp_mail( $client['email'], $subject, $body, $headers );
        if ( $ok ) {
            $sent[]   = $client['email'];
        } else {
            $failed[] = $client['email'];
        }
    }

    seo_dash_json_success( [ 'sent' => $sent, 'failed' => $failed ],
        empty( $failed ) ? 'Email sent successfully.' : 'Some emails failed to send.' );
} );

// ── Frontend: update client profile ───────────────────────────────────────
add_action( 'wp_ajax_seo_dash_update_profile', function () {
    seo_dash_verify_frontend_ajax();

    $user_id = get_current_user_id();
    $client  = SEO_Dash_Database::get_client_by_user( $user_id );

    // Permission gates
    $perm_name   = $client ? !empty( $client['allow_name_change'] )     : true;
    $perm_email  = $client ? !empty( $client['allow_email_change'] )    : true;
    $perm_pwd    = $client ? !empty( $client['allow_password_change'] )  : true;

    $name  = $perm_name  ? sanitize_text_field( wp_unslash( $_POST['name']  ?? '' ) ) : '';
    $email = $perm_email ? sanitize_email( wp_unslash( $_POST['email'] ?? '' ) )       : '';
    $pwd   = $perm_pwd   ? wp_unslash( $_POST['password'] ?? '' )                      : '';

    $update = [ 'ID' => $user_id ];
    if ( $name )  $update['display_name'] = $name;
    if ( $email && is_email( $email ) ) $update['user_email'] = $email;
    if ( $pwd )   $update['user_pass']  = $pwd;

    if ( count( $update ) > 1 ) {
        $result = wp_update_user( $update );
        if ( is_wp_error( $result ) ) {
            seo_dash_json_error( $result->get_error_message() );
        }
    }

    // Update client record name/email too.
    if ( $client ) {
        $client_update = [];
        if ( $name )  $client_update['name']  = $name;
        if ( $email ) $client_update['email'] = $email;
        if ( ! empty( $client_update ) ) {
            SEO_Dash_Database::update_client( intval( $client['id'] ), $client_update );
        }
    }

    seo_dash_json_success( null, 'Profile updated.' );
} );

// ── Client account save (permission-aware, called from new account tab) ────
add_action( 'wp_ajax_seo_dash_client_account_save', function () {
    seo_dash_verify_frontend_ajax();
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in.' );

    $user_id = get_current_user_id();
    $client  = SEO_Dash_Database::get_client_by_user( $user_id );

    $perm_name   = $client ? !empty( $client['allow_name_change'] )     : true;
    $perm_email  = $client ? !empty( $client['allow_email_change'] )    : true;
    $perm_pwd    = $client ? !empty( $client['allow_password_change'] )  : true;
    $perm_avatar = $client ? !empty( $client['allow_avatar_change'] )    : false;

    $name  = $perm_name  ? sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ) : '';
    $email = $perm_email ? sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) )         : '';
    $pwd   = $perm_pwd   ? wp_unslash( $_POST['user_pass'] ?? '' )                             : '';

    $update = [ 'ID' => $user_id ];
    if ( $name )  $update['display_name'] = $name;
    if ( $email && is_email( $email ) ) $update['user_email'] = $email;

    if ( count( $update ) > 1 ) {
        $result = wp_update_user( $update );
        if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); }
    }

    // Update password separately so it is always applied correctly and session cookies are refreshed
    if ( $pwd ) {
        wp_set_password( $pwd, $user_id );

        // Re-authenticate the user so they are not logged out after password reset
        $user_obj = get_user_by( 'id', $user_id );
        if ( $user_obj ) {
            wp_clear_auth_cookie();
            wp_set_auth_cookie( $user_id, true );
        }
    }

    // Avatar handling
    if ( $perm_avatar ) {
        if ( ! empty( $_POST['avatar_remove'] ) ) {
            $old_id = get_user_meta( $user_id, '_seo_dash_avatar_id', true );
            if ( $old_id ) { wp_delete_attachment( intval( $old_id ), true ); }
            delete_user_meta( $user_id, '_seo_dash_avatar_id' );
        } elseif ( ! empty( $_POST['avatar_id'] ) ) {
            update_user_meta( $user_id, '_seo_dash_avatar_id', intval( $_POST['avatar_id'] ) );
        }
    }

    // Sync client record (name / email only — password is handled above)
    if ( $client ) {
        $cu = [];
        if ( $name )  $cu['name']  = $name;
        if ( $email ) $cu['email'] = $email;
        if ( ! empty( $cu ) ) SEO_Dash_Database::update_client( intval( $client['id'] ), $cu );
    }

    wp_send_json_success( 'Saved successfully.' );
} );


// ── Avatar upload handler ──────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_upload_avatar', function () {
    seo_dash_verify_frontend_ajax();
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in.' );

    $user_id = get_current_user_id();
    $client  = SEO_Dash_Database::get_client_by_user( $user_id );
    if ( ! $client || empty( $client['allow_avatar_change'] ) ) { wp_send_json_error( 'Not allowed.' ); }

    if ( empty( $_FILES['avatar'] ) ) { wp_send_json_error( 'No file.' ); }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    // Delete old avatar
    $old_id = get_user_meta( $user_id, '_seo_dash_avatar_id', true );
    if ( $old_id ) { wp_delete_attachment( intval( $old_id ), true ); }

    // Upload new
    $_FILES['avatar']['name'] = 'avatar-' . $user_id . '-' . time() . '.' . pathinfo( $_FILES['avatar']['name'], PATHINFO_EXTENSION );
    $attachment_id = media_handle_upload( 'avatar', 0 );
    if ( is_wp_error( $attachment_id ) ) { wp_send_json_error( $attachment_id->get_error_message() ); }

    update_user_meta( $user_id, '_seo_dash_avatar_id', $attachment_id );
    wp_send_json_success( [ 'attachment_id' => $attachment_id, 'url' => wp_get_attachment_url( $attachment_id ) ] );
} );



// ── v2 Save client — with welcome email support ────────────────────────────
// Handles both add (no client_id) and inline-edit (with client_id)
add_action( 'wp_ajax_seo_dash_save_client_v2', function () {
    seo_dash_verify_admin_ajax();

    $id         = intval( $_POST['client_id'] ?? 0 );
    $name       = sanitize_text_field( wp_unslash( $_POST['name']       ?? '' ) );
    $email      = sanitize_email( wp_unslash( $_POST['email']           ?? '' ) );
    $password   = wp_unslash( $_POST['password']   ?? '' );
    $send_email = ( !empty( $_POST['send_email'] ) && $_POST['send_email'] === '1' );

    if ( ! $name ) seo_dash_json_error( 'Name is required.' );
    if ( ! $id && ! $password ) seo_dash_json_error( 'Password is required.' );

    // ── UPDATE EXISTING ──────────────────────────────────────────────────────
    if ( $id ) {
        $existing = SEO_Dash_Database::get_client( $id );
        if ( ! $existing ) seo_dash_json_error( 'Client not found.' );

        $data_update = [ 'name' => $name, 'email' => $email ];

        SEO_Dash_Database::update_client( $id, $data_update );

        // Sync to WP user.
        $wp_uid = intval( $existing['wp_user_id'] ?? 0 );
        $wp_user_obj = $wp_uid ? get_userdata( $wp_uid ) : false;
        // Safety: only sync to accounts that are actually client accounts.
        // Never overwrite an admin/staff account, even if a client record
        // was previously (incorrectly) linked to one.
        if ( $wp_user_obj && in_array( 'seo_client', (array) $wp_user_obj->roles, true ) ) {
            wp_update_user( array_filter( [
                'ID'           => $wp_uid,
                'display_name' => $name,
                'user_email'   => $email ?: null,
            ] ) );
            if ( $password ) {
                wp_set_password( $password, $wp_uid );
                $GLOBALS['_seo_dash_temp_plain_password'] = $password;
            }
        }

        seo_dash_json_success( [
            'client_id' => $id,
            'client'    => [ 'id' => $id, 'name' => $name, 'email' => $email, 'password' => '' ],
        ], 'Client updated.' );
    }

    // ── ADD NEW ──────────────────────────────────────────────────────────────
    // Auto-create WP user.
    $base_login     = sanitize_user( sanitize_title( $name ), true );
    if ( empty( $base_login ) ) $base_login = 'client_' . substr( uniqid(), -6 );
    $login_try      = $base_login; $sfx = 1;
    while ( username_exists( $login_try ) ) $login_try = $base_login . $sfx++;

    $existing_by_email = $email ? get_user_by( 'email', $email ) : false;

    if ( $existing_by_email ) {
        // Safety check: never hijack an existing WordPress account that isn't
        // already a client (e.g. the site admin or another staff account that
        // happens to share the same email domain). Resetting that account's
        // password / details would lock the real owner out of the site.
        if ( ! in_array( 'seo_client', (array) $existing_by_email->roles, true ) ) {
            seo_dash_json_error( 'A WordPress account with this email already exists and is not a client account (it belongs to an admin or staff user). Please use a different email address for this client.' );
        }
        $wp_uid = $existing_by_email->ID;
        $wp_login = $existing_by_email->user_login;
        if ( $password ) {
            wp_set_password( $password, $wp_uid );
            $GLOBALS['_seo_dash_temp_plain_password'] = $password;
        }
    } else {
        $wp_uid = wp_create_user( $login_try, $password, $email ?: '' );
        if ( is_wp_error( $wp_uid ) ) seo_dash_json_error( $wp_uid->get_error_message() );
        $wp_login = $login_try;
        $u = new WP_User( $wp_uid );
        $u->set_role( 'seo_client' );
        wp_update_user( [ 'ID' => $wp_uid, 'display_name' => $name, 'first_name' => $name ] );
        $GLOBALS['_seo_dash_temp_plain_password'] = $password;
    }

    // Create a dedicated WP page for this client.
    $page_data     = SEO_Dash_Frontend::create_client_page( $name, $wp_login );
    $dashboard_url = $page_data['page_url'] ?: SEO_Dash_Frontend::client_url( $wp_login );
    $wp_page_id    = $page_data['page_id'];

    $client_id = SEO_Dash_Database::insert_client( [
        'wp_user_id'    => $wp_uid,
        'wp_page_id'    => $wp_page_id,
        'name'          => $name,
        'email'         => $email,
        'dashboard_url' => $dashboard_url,
    ] );

    if ( ! $client_id ) seo_dash_json_error( 'Failed to create client record.' );

    // ── Send welcome email ───────────────────────────────────────────────────
    $email_sent   = false;
    $email_failed = false;

    if ( $send_email && $email && is_email( $email ) ) {
        if (seo_dash_send_client_welcome_email($client_id)) {
            $email_sent = true;
        } else {
            $email_failed = true;
        }

        // Notify admin emails.
        $brand_name  = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
        $admin_from  = get_option( 'admin_email' );
        $admin_name  = wp_get_current_user()->display_name ?: $brand_name;
        $notify_raw = SEO_Dash_Database::get_setting( 'admin_notify_emails', '' );
        $notify_list = array_filter( array_map( 'sanitize_email', explode( ',', $notify_raw ) ) );
        foreach ( $notify_list as $admin_em ) {
            wp_mail(
                $admin_em,
                "[{$brand_name}] New Client Added — {$name}",
                "New client: {$name} ({$email})\nDashboard: {$dashboard_url}",
                [ 'Content-Type: text/plain; charset=UTF-8', "From: {$admin_name} <{$admin_from}>" ]
            );
        }
    }

    seo_dash_json_success( [
        'client_id'    => $client_id,
        'email_sent'   => $email_sent,
        'email_failed' => $email_failed,
    ], 'Client added.' );
} );

// ── Save client permissions ────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_client_perms', function () {
    seo_dash_verify_admin_ajax();

    $id = intval( $_POST['client_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing client ID.' );

    // Ensure all 4 perm columns are written directly via $wpdb to bypass
    // any allowlist issues, and also handle installs where allow_avatar_change
    // column may not yet exist.
    global $wpdb;
    $table = SEO_Dash_Database::$clients;

    // Add allow_avatar_change column if missing (safe on repeated calls).
    $cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
    if ( ! in_array( 'allow_avatar_change', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `allow_avatar_change` TINYINT(1) NOT NULL DEFAULT 0" );
    }

    $wpdb->update(
        $table,
        [
            'allow_name_change'     => intval( $_POST['allow_name_change']     ?? 0 ),
            'allow_email_change'    => intval( $_POST['allow_email_change']    ?? 0 ),
            'allow_password_change' => intval( $_POST['allow_password_change'] ?? 0 ),
            'allow_avatar_change'   => intval( $_POST['allow_avatar_change']   ?? 0 ),
        ],
        [ 'id' => $id ],
        [ '%d', '%d', '%d', '%d' ],
        [ '%d' ]
    );

    seo_dash_json_success( null, 'Permissions saved.' );
} );

// ── Bulk assign clients to a report (from integrations tab) ───────────────
add_action( 'wp_ajax_seo_dash_assign_clients_to_report', function () {
    seo_dash_verify_admin_ajax();

    $report_id  = intval( $_POST['report_id'] ?? 0 );
    // Accept both client_ids[] and client_ids (jQuery sends both forms)
    $raw        = $_POST['client_ids'] ?? [];
    if ( ! is_array( $raw ) ) $raw = [ $raw ];
    // Defensive: flatten out any stray nested arrays before intval(). PHP's
    // intval() silently casts a non-empty array to 1 instead of erroring,
    // which previously let a malformed/duplicated POST key inject a phantom
    // "client ID 1" into every save. Only keep scalar entries.
    $raw        = array_filter( $raw, function ( $v ) { return ! is_array( $v ); } );
    $client_ids = array_filter( array_map( 'intval', $raw ) );

    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    // Remove all current assignments then re-add selected ones.
    global $wpdb;
    $wpdb->delete( SEO_Dash_Database::$report_clients, [ 'report_id' => $report_id ], [ '%d' ] );

    foreach ( $client_ids as $cid ) {
        if ( ! $cid ) continue;
        $wpdb->replace(
            SEO_Dash_Database::$report_clients,
            [ 'report_id' => $report_id, 'client_id' => $cid ],
            [ '%d', '%d' ]
        );

        // If this client has no dedicated page yet, create one now.
        // Never overwrite an existing dashboard_url — it's already a real page URL.
        $client = SEO_Dash_Database::get_client( $cid );
        if ( $client && empty( $client['wp_page_id'] ) ) {
            $wp_uid = intval( $client['wp_user_id'] ?? 0 );
            $wp_u   = $wp_uid ? get_userdata( $wp_uid ) : null;
            $uname  = $wp_u ? $wp_u->user_login : 'client-' . $cid;
            $cname  = $client['name'] ?: $uname;
            $page_data = SEO_Dash_Frontend::create_client_page( $cname, $uname );
            if ( $page_data['page_id'] ) {
                SEO_Dash_Database::update_client( $cid, [
                    'wp_page_id'    => $page_data['page_id'],
                    'dashboard_url' => $page_data['page_url'],
                ] );
            }
        }
    }

    SEO_Dash_Database::log_activity( 'clients_assigned', 'info', count($client_ids) . " client(s) assigned to report ID {$report_id}.", 'report', $report_id );
    seo_dash_json_success( [ 'count' => count($client_ids) ], count($client_ids) . ' client(s) assigned.' );
} );

// ── Log wp_mail failures to help debug email delivery issues ──────────────
add_action( 'wp_mail_failed', function( $wp_error ) {
    if ( is_wp_error( $wp_error ) ) {
        error_log( '[SEO Dashboard] wp_mail failed: ' . $wp_error->get_error_message() );
        if ( class_exists('SEO_Dash_Database') ) {
            SEO_Dash_Database::log_activity( 'email_failed', 'error', 'wp_mail error: ' . $wp_error->get_error_message() );
        }
    }
} );
