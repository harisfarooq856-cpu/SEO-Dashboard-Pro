<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX Handlers — Document Approval
 *
 * Actions:
 *   seo_dash_doc_add          — Admin: add a new document (file upload or URL)
 *   seo_dash_doc_delete       — Admin: permanently delete a document
 *   seo_dash_doc_list         — Admin: list documents for a report
 *   seo_dash_doc_send_email   — Admin: send/resend notification email to client
 *   seo_dash_doc_client_list  — Client: list documents sent to them
 *   seo_dash_doc_client_action — Client: approve / reject + add notes
 */

// ── Admin: Add document ────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_doc_add', function () {
    seo_dash_verify_admin_ajax();

    $report_id  = intval( $_POST['report_id'] ?? 0 );
    $client_id  = intval( $_POST['client_id']  ?? 0 );
    $title      = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    $file_type   = in_array( $_POST['file_type'] ?? '', ['file','url'], true ) ? $_POST['file_type'] : 'file';
    $sent_mail   = sanitize_email( wp_unslash( $_POST['sent_to_mail'] ?? '' ) );
    $admin_notes = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );
    $notify      = intval( $_POST['notify_client'] ?? 0 );

    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );
    if ( ! $title )     seo_dash_json_error( 'Document title is required.' );

    $file_url = '';
    $file_name = '';

    if ( $file_type === 'file' ) {
        if ( ! empty( $_FILES['doc_file']['name'] ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            
            $uploaded_file = wp_handle_upload( $_FILES['doc_file'], [ 'test_form' => false ] );
            if ( isset( $uploaded_file['error'] ) ) {
                seo_dash_json_error( 'File upload error: ' . $uploaded_file['error'] );
            }
            if ( ! isset( $uploaded_file['url'] ) || ! isset( $uploaded_file['file'] ) ) {
                seo_dash_json_error( 'File upload failed. Unknown error.' );
            }
            
            $file_url  = $uploaded_file['url'];
            $file_name = sanitize_file_name( $_FILES['doc_file']['name'] );

            // Optional: insert into media library
            $attachment = [
                'post_mime_type' => $uploaded_file['type'],
                'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_name ),
                'post_content'   => '',
                'post_status'    => 'inherit'
            ];
            $attach_id = wp_insert_attachment( $attachment, $uploaded_file['file'] );
            if ( ! is_wp_error( $attach_id ) ) {
                $attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded_file['file'] );
                wp_update_attachment_metadata( $attach_id, $attach_data );
            }
        } else {
            seo_dash_json_error( 'Please select a file to upload.' );
        }
    } else {
        $file_url = esc_url_raw( wp_unslash( $_POST['file_url'] ?? '' ) );
        if ( ! $file_url ) seo_dash_json_error( 'Document URL is required.' );
    }

    global $wpdb;

    // NUCLEAR FIX: Force create table using raw SQL to bypass any dbDelta parsing issues with comments/spacing.
    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . SEO_Dash_Database::$data_documents . "'" ) != SEO_Dash_Database::$data_documents ) {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query( "CREATE TABLE " . SEO_Dash_Database::$data_documents . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id BIGINT(20) UNSIGNED NOT NULL,
            client_id BIGINT(20) UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            file_type VARCHAR(20) NOT NULL DEFAULT 'file',
            file_url VARCHAR(2000) DEFAULT NULL,
            file_name VARCHAR(255) DEFAULT NULL,
            sent_to_mail VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            client_notes TEXT DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            notified_at DATETIME DEFAULT NULL,
            actioned_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY client_id (client_id),
            KEY status (status)
        ) $charset;" );
    }
    
    $insert_data = [
        'report_id'    => $report_id,
        'title'        => $title,
        'file_type'    => $file_type,
        'file_url'     => $file_url,
        'file_name'    => $file_name ?: basename( $file_url ),
        'admin_notes'  => $admin_notes,
        'status'       => 'pending',
    ];

    if ( $client_id ) {
        $insert_data['client_id'] = $client_id;
    }
    if ( $sent_mail ) {
        $insert_data['sent_to_mail'] = $sent_mail;
    }

    $inserted = $wpdb->insert( SEO_Dash_Database::$data_documents, $insert_data );

    if ( ! $inserted ) {
        // Log to an actual file to see what failed if it still fails
        file_put_contents( __DIR__ . '/nuclear_error_log.txt', date('Y-m-d H:i:s') . ' - Insert failed: ' . $wpdb->last_error . "\n", FILE_APPEND );
        seo_dash_json_error( 'Database insert failed. Details: ' . $wpdb->last_error );
    }

    $new_id = $wpdb->insert_id;

    SEO_Dash_Database::log_activity( 'doc_added', 'success', "Document \"{$title}\" added.", 'report', $report_id );

    // Auto-send notification email only if requested
    $mail_sent = false;
    if ( $notify && ($client_id || $sent_mail) ) {
        $mail_sent = seo_dash_doc_send_notification( $new_id );
    }

    seo_dash_json_success( [ 'id' => $new_id, 'mail_sent' => $mail_sent ], 'Document added.' );
} );

// ── Admin: Delete document ─────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_doc_delete', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['doc_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing document ID.' );

    global $wpdb;
    $doc = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM " . SEO_Dash_Database::$data_documents . " WHERE id = %d LIMIT 1", $id
    ), ARRAY_A );

    if ( ! $doc ) seo_dash_json_error( 'Document not found.' );

    // Delete WP media attachment if it was a file upload
    if ( $doc['file_type'] === 'file' && $doc['file_url'] ) {
        $attach_id = attachment_url_to_postid( $doc['file_url'] );
        if ( $attach_id ) {
            wp_delete_attachment( $attach_id, true );
        }
    }

    $wpdb->delete( SEO_Dash_Database::$data_documents, [ 'id' => $id ], [ '%d' ] );
    SEO_Dash_Database::log_activity( 'doc_deleted', 'warning', "Document {$id} deleted." );
    seo_dash_json_success( null, 'Document deleted.' );
} );

// ── Admin: Bulk Delete documents ───────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_bulk_docs_delete', function () {
    seo_dash_verify_admin_ajax();
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    if ( empty($ids) ) seo_dash_json_error( 'No documents selected.' );

    global $wpdb;
    $id_list = implode(',', $ids);
    
    $docs = $wpdb->get_results( "SELECT id, file_type, file_url FROM " . SEO_Dash_Database::$data_documents . " WHERE id IN ($id_list)", ARRAY_A );
    foreach ( $docs as $d ) {
        if ( $d['file_type'] === 'file' && $d['file_url'] ) {
            $attach_id = attachment_url_to_postid( $d['file_url'] );
            if ( $attach_id ) wp_delete_attachment( $attach_id, true );
        }
    }
    
    $wpdb->query( "DELETE FROM " . SEO_Dash_Database::$data_documents . " WHERE id IN ($id_list)" );
    SEO_Dash_Database::log_activity( 'docs_bulk_deleted', 'warning', count($ids) . " documents deleted." );
    seo_dash_json_success( null, 'Documents deleted.' );
} );

// ── Admin: Bulk Status Update ──────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_bulk_docs_status', function () {
    seo_dash_verify_admin_ajax();
    $status = sanitize_key( $_POST['status'] ?? '' );
    $ids    = array_map('intval', (array)($_POST['ids'] ?? []));
    if ( ! $status || empty($ids) ) seo_dash_json_error( 'Missing data.' );

    $allowed = ['pending', 'approved', 'rejected'];
    if ( ! in_array( $status, $allowed, true ) ) seo_dash_json_error( 'Invalid status.' );

    global $wpdb;
    $id_list = implode(',', $ids);
    $wpdb->query( $wpdb->prepare( "UPDATE " . SEO_Dash_Database::$data_documents . " SET status = %s WHERE id IN ($id_list)", $status ) );
    
    seo_dash_json_success( null, 'Status updated.' );
} );

// ── Admin: List documents ──────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_doc_list', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    global $wpdb;

    // Auto-create table if it doesn't exist
    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . SEO_Dash_Database::$data_documents . "'" ) != SEO_Dash_Database::$data_documents ) {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS " . SEO_Dash_Database::$data_documents . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id BIGINT(20) UNSIGNED NOT NULL,
            client_id BIGINT(20) UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            file_type VARCHAR(20) NOT NULL DEFAULT 'file',
            file_url VARCHAR(2000) DEFAULT NULL,
            file_name VARCHAR(255) DEFAULT NULL,
            sent_to_mail VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            client_notes TEXT DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            notified_at DATETIME DEFAULT NULL,
            actioned_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id)
        ) $charset;" );
        // Table just created — must be empty
        seo_dash_json_success( [ 'rows' => [] ] );
        return;
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT d.*, c.name AS client_name, c.email AS client_email
         FROM " . SEO_Dash_Database::$data_documents . " d
         LEFT JOIN " . SEO_Dash_Database::$clients . " c ON c.id = d.client_id
         WHERE d.report_id = %d
         ORDER BY d.created_at DESC",
        $report_id
    ), ARRAY_A );

    // If query failed (table issue), return empty rather than crash
    if ( $rows === null ) {
        seo_dash_json_success( [ 'rows' => [] ] );
        return;
    }

    seo_dash_json_success( [ 'rows' => $rows ?: [] ] );
} );

// ── Admin: Update a single document field (inline edit) ──────────────────
add_action( 'wp_ajax_seo_dash_doc_update_field', function () {
    seo_dash_verify_admin_ajax();
    $doc_id = intval( $_POST['doc_id'] ?? 0 );
    $field  = sanitize_key( $_POST['field'] ?? '' );
    $value  = wp_unslash( $_POST['value'] ?? '' );

    if ( ! $doc_id || ! $field ) seo_dash_json_error( 'Missing data.' );

    $allowed = [ 'title', 'client_id', 'status', 'sent_to_mail', 'admin_notes' ];
    if ( ! in_array( $field, $allowed, true ) ) seo_dash_json_error( 'Field not allowed.' );

    global $wpdb;

    $sanitized = match( $field ) {
        'title'        => sanitize_text_field( $value ),
        'client_id'    => intval( $value ) ?: null,
        'status'       => in_array( $value, ['pending','approved','rejected'], true ) ? $value : 'pending',
        'sent_to_mail' => sanitize_email( $value ) ?: null,
        'admin_notes'  => sanitize_textarea_field( $value ),
        default        => sanitize_text_field( $value ),
    };

    $updated = $wpdb->update(
        SEO_Dash_Database::$data_documents,
        [ $field => $sanitized ],
        [ 'id'   => $doc_id ],
        null,
        [ '%d' ]
    );

    if ( $updated === false ) seo_dash_json_error( 'Update failed: ' . $wpdb->last_error );
    seo_dash_json_success( null, ucfirst( str_replace('_',' ',$field) ) . ' updated.' );
} );

// ── Admin: Send/resend notification email ──────────────────────────────────
add_action( 'wp_ajax_seo_dash_doc_send_email', function () {
    seo_dash_verify_admin_ajax();
    $id = intval( $_POST['doc_id'] ?? 0 );
    if ( ! $id ) seo_dash_json_error( 'Missing document ID.' );

    $result = seo_dash_doc_send_notification( $id );
    if ( $result ) {
        seo_dash_json_success( null, 'Email sent successfully.' );
    } else {
        seo_dash_json_error( 'Failed to send email. Check client email address.' );
    }
} );

// ── Admin: Update admin notes on a document ────────────────────────────────
add_action( 'wp_ajax_seo_dash_doc_save_notes', function () {
    seo_dash_verify_admin_ajax();
    $id    = intval( $_POST['doc_id'] ?? 0 );
    $notes = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );
    if ( ! $id ) seo_dash_json_error( 'Missing document ID.' );

    global $wpdb;
    $wpdb->update( SEO_Dash_Database::$data_documents, [ 'admin_notes' => $notes ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
    seo_dash_json_success( null, 'Notes saved.' );
} );

// ── Client: List documents sent to them ────────────────────────────────────
add_action( 'wp_ajax_seo_dash_doc_client_list', function () {
    if ( ! is_user_logged_in() ) seo_dash_json_error( 'Not authenticated.' );
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    $user_id = get_current_user_id();
    if ( ! seo_dash_can_user_access_report( $user_id, $report_id ) ) {
        seo_dash_json_error( 'Access denied.', 403 );
    }

    $client = SEO_Dash_Database::get_client_by_user( $user_id );
    $client_id = $client ? intval( $client['id'] ) : 0;

    global $wpdb;
    if ( $client_id ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, file_type, file_url, file_name, status, client_notes, admin_notes, created_at, actioned_at
             FROM " . SEO_Dash_Database::$data_documents . "
             WHERE report_id = %d AND (client_id = %d OR client_id IS NULL)
             ORDER BY created_at DESC",
            $report_id, $client_id
        ), ARRAY_A );
    } else {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, title, file_type, file_url, file_name, status, client_notes, admin_notes, created_at, actioned_at
             FROM " . SEO_Dash_Database::$data_documents . "
             WHERE report_id = %d
             ORDER BY created_at DESC",
            $report_id
        ), ARRAY_A );
    }

    seo_dash_json_success( $rows ?: [] );
} );
add_action( 'wp_ajax_nopriv_seo_dash_doc_client_list', function() { seo_dash_json_error('Not authenticated.'); } );

// ── Client: Approve / Reject + add notes ──────────────────────────────────
add_action( 'wp_ajax_seo_dash_doc_client_action', function () {
    seo_dash_verify_frontend_ajax();
    if ( ! is_user_logged_in() ) seo_dash_json_error( 'Not authenticated.' );

    $id     = intval( $_POST['doc_id'] ?? 0 );
    $action = sanitize_key( $_POST['doc_action'] ?? '' );
    if ( ! $id ) seo_dash_json_error( 'Missing document ID.' );

    $allowed_statuses = [ 'approved', 'rejected', 'pending', 'disapproved', 'needs_changes' ];
    if ( ! in_array( $action, $allowed_statuses, true ) ) seo_dash_json_error( 'Invalid action.' );

    global $wpdb;
    $doc = $wpdb->get_row( $wpdb->prepare(
        "SELECT d.*, r.title AS report_title, c.name AS client_name
         FROM " . SEO_Dash_Database::$data_documents . " d
         LEFT JOIN " . SEO_Dash_Database::$reports . " r ON r.id = d.report_id
         LEFT JOIN " . SEO_Dash_Database::$clients . " c ON c.id = d.client_id
         WHERE d.id = %d LIMIT 1", $id
    ), ARRAY_A );

    if ( ! $doc || ! seo_dash_can_user_access_report( get_current_user_id(), intval( $doc['report_id'] ) ) ) {
        seo_dash_json_error( 'Access denied.' );
    }

    $wpdb->update( SEO_Dash_Database::$data_documents, [
        'status'      => $action,
        'actioned_at' => current_time( 'mysql' ),
    ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );

    SEO_Dash_Database::log_activity( 'doc_client_action', 'info', "Client set document {$id} to {$action}." );

    // ── Notify admin ─────────────────────────────────────────────────────
    $status_labels = [
        'approved'      => 'Approved',
        'disapproved'   => 'Disapproved',
        'needs_changes' => 'Needs Changes',
        'pending'       => 'Pending Review',
        'rejected'      => 'Rejected',
    ];
    $status_label  = $status_labels[ $action ] ?? ucfirst( $action );
    $admin_email   = get_option( 'admin_email' );
    $brand_name    = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
    $notify_raw    = SEO_Dash_Database::get_setting( 'admin_notify_emails', '' );
    $notify_list   = array_filter( array_map( 'sanitize_email', explode( ',', $notify_raw ) ) );
    if ( empty( $notify_list ) ) $notify_list = [ $admin_email ];

    $client_name   = $doc['client_name'] ?: ( wp_get_current_user()->display_name ?: 'Client' );
    $doc_title     = esc_html( $doc['title'] ?? 'Document' );
    $report_title  = esc_html( $doc['report_title'] ?? '' );
    $report_link   = admin_url( 'admin.php?page=seo-dashboard-reports' );
    $year          = date( 'Y' );
    $brand_esc     = esc_html( $brand_name );

    $subject = "[{$brand_name}] Client Action on Document: {$doc['title']} \xe2\x80\x94 {$status_label}";
    $body    = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;'>"
             . "<table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:40px 0;'><tr><td align='center'>"
             . "<table width='580' cellpadding='0' cellspacing='0' style='max-width:580px;background:#fff;border-radius:12px;overflow:hidden;'>"
             . "<tr><td style='background:#4f46e5;padding:28px 36px;'><h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>{$brand_esc}</h1>"
             . "<p style='margin:6px 0 0;color:rgba(255,255,255,.8);font-size:12px;'>Document Status Update</p></td></tr>"
             . "<tr><td style='padding:28px 36px;'>"
             . "<p style='margin:0 0 18px;font-size:15px;color:#111827;'>A client has updated the status of a document.</p>"
             . "<table width='100%' cellpadding='0' cellspacing='0' style='background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:20px;'>"
             . "<tr><td style='padding:14px 20px;border-bottom:1px solid #e5e7eb;'><span style='font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:600;'>Document</span><br><span style='font-size:15px;color:#111827;font-weight:700;'>{$doc_title}</span></td></tr>"
             . "<tr><td style='padding:14px 20px;border-bottom:1px solid #e5e7eb;'><span style='font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:600;'>Report</span><br><span style='font-size:14px;color:#374151;'>{$report_title}</span></td></tr>"
             . "<tr><td style='padding:14px 20px;border-bottom:1px solid #e5e7eb;'><span style='font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:600;'>Client</span><br><span style='font-size:14px;color:#374151;'>" . esc_html( $client_name ) . "</span></td></tr>"
             . "<tr><td style='padding:14px 20px;'><span style='font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:600;'>New Status</span><br><span style='font-size:14px;font-weight:700;color:#4f46e5;'>{$status_label}</span></td></tr>"
             . "</table>"
             . "<a href='" . esc_url( $report_link ) . "' style='display:inline-block;background:#4f46e5;color:#fff;padding:12px 28px;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;'>View in Dashboard</a>"
             . "</td></tr>"
             . "<tr><td style='background:#f8fafc;border-top:1px solid #f3f4f6;padding:16px 36px;text-align:center;'><p style='margin:0;font-size:12px;color:#9ca3af;'>&copy; {$year} {$brand_esc}</p></td></tr>"
             . "</table></td></tr></table></body></html>";

    $headers = [ 'Content-Type: text/html; charset=UTF-8', 'From: ' . $brand_esc . ' <' . $admin_email . '>' ];
    foreach ( $notify_list as $to ) { wp_mail( $to, $subject, $body, $headers ); }

    seo_dash_json_success( null, 'Status updated.' );
} );
add_action( 'wp_ajax_nopriv_seo_dash_doc_client_action', function() { seo_dash_json_error('Not authenticated.'); } );

// ── Client: Save notes only ────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_doc_client_save_notes', function () {
    seo_dash_verify_frontend_ajax();
    if ( ! is_user_logged_in() ) seo_dash_json_error( 'Not authenticated.' );

    $id    = intval( $_POST['doc_id'] ?? 0 );
    $notes = sanitize_textarea_field( wp_unslash( $_POST['client_notes'] ?? '' ) );
    if ( ! $id ) seo_dash_json_error( 'Missing document ID.' );

    global $wpdb;
    $doc = $wpdb->get_row( $wpdb->prepare(
        "SELECT d.*, r.title AS report_title, c.name AS client_name
         FROM " . SEO_Dash_Database::$data_documents . " d
         LEFT JOIN " . SEO_Dash_Database::$reports . " r ON r.id = d.report_id
         LEFT JOIN " . SEO_Dash_Database::$clients . " c ON c.id = d.client_id
         WHERE d.id = %d LIMIT 1", $id
    ), ARRAY_A );

    if ( ! $doc || ! seo_dash_can_user_access_report( get_current_user_id(), intval( $doc['report_id'] ) ) ) {
        seo_dash_json_error( 'Access denied.' );
    }

    $wpdb->update( SEO_Dash_Database::$data_documents, [
        'client_notes' => $notes,
        'actioned_at'  => current_time( 'mysql' ),
    ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );

    SEO_Dash_Database::log_activity( 'doc_client_notes', 'info', "Client updated notes on document {$id}." );

    // Notify admin if notes were added
    if ( trim( $notes ) ) {
        $admin_email  = get_option( 'admin_email' );
        $brand_name   = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
        $notify_raw   = SEO_Dash_Database::get_setting( 'admin_notify_emails', '' );
        $notify_list  = array_filter( array_map( 'sanitize_email', explode( ',', $notify_raw ) ) );
        if ( empty( $notify_list ) ) $notify_list = [ $admin_email ];

        $client_name  = $doc['client_name'] ?: ( wp_get_current_user()->display_name ?: 'Client' );
        $doc_title    = esc_html( $doc['title'] ?? 'Document' );
        $report_link  = admin_url( 'admin.php?page=seo-dashboard-reports' );
        $notes_esc    = esc_html( $notes );
        $year         = date( 'Y' );
        $brand_esc    = esc_html( $brand_name );

        $subject = "[{$brand_name}] Client Added a Note on: {$doc['title']}";
        $body    = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;'>"
                 . "<table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:40px 0;'><tr><td align='center'>"
                 . "<table width='580' cellpadding='0' cellspacing='0' style='max-width:580px;background:#fff;border-radius:12px;overflow:hidden;'>"
                 . "<tr><td style='background:#4f46e5;padding:28px 36px;'><h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>{$brand_esc}</h1>"
                 . "<p style='margin:6px 0 0;color:rgba(255,255,255,.8);font-size:12px;'>New Client Note on Document</p></td></tr>"
                 . "<tr><td style='padding:28px 36px;'>"
                 . "<p style='margin:0 0 18px;font-size:15px;color:#111827;'>A client has added a note to a document.</p>"
                 . "<table width='100%' cellpadding='0' cellspacing='0' style='background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:20px;'>"
                 . "<tr><td style='padding:14px 20px;border-bottom:1px solid #e5e7eb;'><span style='font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:600;'>Document</span><br><span style='font-size:15px;color:#111827;font-weight:700;'>{$doc_title}</span></td></tr>"
                 . "<tr><td style='padding:14px 20px;border-bottom:1px solid #e5e7eb;'><span style='font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:600;'>Client</span><br><span style='font-size:14px;color:#374151;'>" . esc_html( $client_name ) . "</span></td></tr>"
                 . "<tr><td style='padding:14px 20px;'><span style='font-size:11px;text-transform:uppercase;color:#9ca3af;font-weight:600;'>Note</span><br><p style='margin:8px 0 0;font-size:14px;color:#374151;line-height:1.6;background:#fffbeb;border-left:3px solid #f59e0b;padding:10px 14px;border-radius:0 6px 6px 0;'>{$notes_esc}</p></td></tr>"
                 . "</table>"
                 . "<a href='" . esc_url( $report_link ) . "' style='display:inline-block;background:#4f46e5;color:#fff;padding:12px 28px;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;'>View in Dashboard</a>"
                 . "</td></tr>"
                 . "<tr><td style='background:#f8fafc;border-top:1px solid #f3f4f6;padding:16px 36px;text-align:center;'><p style='margin:0;font-size:12px;color:#9ca3af;'>&copy; {$year} {$brand_esc}</p></td></tr>"
                 . "</table></td></tr></table></body></html>";

        $headers = [ 'Content-Type: text/html; charset=UTF-8', 'From: ' . $brand_esc . ' <' . $admin_email . '>' ];
        foreach ( $notify_list as $to ) { wp_mail( $to, $subject, $body, $headers ); }
    }

    seo_dash_json_success( null, 'Notes saved.' );
} );
add_action( 'wp_ajax_nopriv_seo_dash_doc_client_save_notes', function() { seo_dash_json_error('Not authenticated.'); } );

// ── Helper: send notification email ───────────────────────────────────────
function seo_dash_doc_send_notification( int $doc_id ): bool {
    global $wpdb;
    $doc = $wpdb->get_row( $wpdb->prepare(
        "SELECT d.*, c.name AS client_name, c.email AS client_email, c.dashboard_url AS client_dash_url,
                r.title AS report_title
         FROM " . SEO_Dash_Database::$data_documents . " d
         LEFT JOIN " . SEO_Dash_Database::$clients . " c ON c.id = d.client_id
         LEFT JOIN " . SEO_Dash_Database::$reports . " r ON r.id = d.report_id
         WHERE d.id = %d LIMIT 1",
        $doc_id
    ), ARRAY_A );

    if ( ! $doc ) return false;

    // Determine recipient email: override → client email → nothing
    $to_email = $doc['sent_to_mail'] ?: $doc['client_email'] ?? '';
    if ( ! $to_email || ! is_email( $to_email ) ) return false;

    // Company/brand info from Settings (never falls back to site name in emails)
    $company      = SEO_Dash_Database::get_setting( 'brand_name', '' ) ?: get_option( 'blogname' );
    $support_email= SEO_Dash_Database::get_setting( 'support_email', get_option( 'admin_email' ) );
    $from_email   = is_email( $support_email ) ? $support_email : get_option( 'admin_email' );

    $dash_url     = $doc['client_dash_url'] ?: get_permalink( get_option( 'seo_dash_client_page_id' ) ) ?: '';
    $client_name  = esc_html( $doc['client_name'] ?: 'Client' );
    $doc_title    = esc_html( $doc['title'] );
    $report_title = esc_html( $doc['report_title'] ?: 'Your Report' );
    $file_url     = esc_url( $doc['file_url'] ?? '' );
    $admin_notes  = trim( $doc['admin_notes'] ?? '' );
    $year         = date( 'Y' );
    $company_esc  = esc_html( $company );

    $subject = "[{$company}] A new document requires your review: {$doc['title']}";

    // ── Professional HTML Email Body ──────────────────────────────────────
    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">

        <!-- Header -->
        <tr>
          <td style="background:#4f46e5;padding:36px 40px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;"><a href="{$site_url}" style="color:#ffffff;text-decoration:none;">{$company_esc}</a></h1>
            <p style="margin:8px 0 0;color:rgba(255,255,255,0.8);font-size:13px;">Document Review Request</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:36px 40px;">

            <p style="margin:0 0 8px;font-size:16px;color:#111827;font-weight:600;">Hi {$client_name},</p>
            <p style="margin:0 0 24px;font-size:14px;color:#6b7280;line-height:1.6;">
              A new document has been shared with you and is waiting for your review.
            </p>

            <!-- Document Card -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:24px;">
              <tr>
                <td style="padding:20px 24px;">
                  <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#9ca3af;font-weight:600;">Document</p>
                  <p style="margin:0 0 14px;font-size:16px;color:#111827;font-weight:700;">{$doc_title}</p>
                  <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#9ca3af;font-weight:600;">Report</p>
                  <p style="margin:0;font-size:14px;color:#374151;">{$report_title}</p>
                </td>
              </tr>
HTML;

    if ( $file_url ) {
        $body .= <<<HTML
              <tr>
                <td style="padding:0 24px 20px;">
                  <a href="{$file_url}" target="_blank"
                     style="display:inline-block;background:#eef2ff;color:#4f46e5;font-size:13px;font-weight:600;padding:10px 18px;border-radius:7px;text-decoration:none;border:1px solid #c7d2fe;">
                    Open Document
                  </a>
                </td>
              </tr>
HTML;
    }

    if ( $admin_notes ) {
        $notes_esc = esc_html( $admin_notes );
        $body .= <<<HTML
              <tr>
                <td style="padding:0 24px 20px;">
                  <p style="margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#9ca3af;font-weight:600;">Notes from {$company_esc}</p>
                  <p style="margin:0;font-size:13px;color:#374151;line-height:1.6;background:#fffbeb;border-left:3px solid #f59e0b;padding:10px 14px;border-radius:0 6px 6px 0;">{$notes_esc}</p>
                </td>
              </tr>
HTML;
    }

    $body .= <<<HTML
            </table>
HTML;

    if ( $dash_url ) {
        $dash_url_esc = esc_url( $dash_url );
        $body .= <<<HTML
            <!-- CTA Button -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
              <tr>
                <td align="center">
                  <a href="{$dash_url_esc}" target="_blank"
                     style="display:inline-block;background:#4f46e5;color:#ffffff;font-size:15px;font-weight:700;padding:14px 36px;border-radius:8px;text-decoration:none;">
                    Review and Approve Document
                  </a>
                </td>
              </tr>
            </table>
HTML;
    }

    $body .= <<<HTML
            <!-- Divider -->
            <hr style="border:none;border-top:1px solid #f3f4f6;margin:0 0 20px;">
            <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.6;">
              If you have any questions, reply to this email.<br>
              This is an automated message from <strong>{$company_esc}</strong>.
            </p>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;border-top:1px solid #f3f4f6;padding:20px 40px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;">&copy; {$year} {$company_esc}. All rights reserved.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
HTML;

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $company . ' <' . $from_email . '>',
    ];

    $sent = wp_mail( $to_email, $subject, $body, $headers );

    if ( $sent ) {
        $wpdb->update( SEO_Dash_Database::$data_documents, [
            'notified_at' => current_time( 'mysql' ),
        ], [ 'id' => $doc_id ], [ '%s' ], [ '%d' ] );
    }

    return $sent;
}

