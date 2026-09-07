<?php if ( ! defined('ABSPATH') ) exit;

global $wpdb;
SEO_Dash_Database::init();

// ── DB status ──────────────────────────────────────────────────────────────
$reports_table = SEO_Dash_Database::$reports;
$table_exists  = $wpdb->get_var( "SHOW TABLES LIKE '$reports_table'" );
$db_version    = get_option( 'seo_dash_db_version', 'not set' );
$admin_pid     = intval( get_option( 'seo_dash_admin_page_id' ) );
$client_pid    = intval( get_option( 'seo_dash_client_page_id' ) );
$admin_page    = $admin_pid  ? get_post( $admin_pid )  : null;
$client_page   = $client_pid ? get_post( $client_pid ) : null;
$total_reports = $table_exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table" ) : 0;

// ── Activity log ───────────────────────────────────────────────────────────
$act_table  = SEO_Dash_Database::$activity_log;
$act_exists = $wpdb->get_var( "SHOW TABLES LIKE '$act_table'" );

// Filters
$filter_level  = sanitize_key( $_GET['log_level']  ?? '' );
$filter_action = sanitize_text_field( $_GET['log_action'] ?? '' );
$filter_type   = sanitize_key( $_GET['log_type']   ?? '' );
$per_page      = 50;
$current_page  = max( 1, intval( $_GET['log_page'] ?? 1 ) );
$offset        = ( $current_page - 1 ) * $per_page;

$act_rows  = [];
$act_total = 0;

if ( $act_exists ) {
    $where_parts = [ '1=1' ];
    $where_vals  = [];

    if ( $filter_level ) {
        $where_parts[] = 'level = %s';
        $where_vals[]  = $filter_level;
    }
    if ( $filter_action ) {
        $where_parts[] = 'action LIKE %s';
        $where_vals[]  = '%' . $wpdb->esc_like( $filter_action ) . '%';
    }
    if ( $filter_type ) {
        $where_parts[] = 'object_type = %s';
        $where_vals[]  = $filter_type;
    }

    $where_sql = implode( ' AND ', $where_parts );

    if ( $where_vals ) {
        $count_sql = $wpdb->prepare( "SELECT COUNT(*) FROM $act_table WHERE $where_sql", ...$where_vals );
        $rows_sql  = $wpdb->prepare( "SELECT * FROM $act_table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...array_merge( $where_vals, [ $per_page, $offset ] ) );
    } else {
        $count_sql = "SELECT COUNT(*) FROM $act_table";
        $rows_sql  = $wpdb->prepare( "SELECT * FROM $act_table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset );
    }

    $act_total = (int) $wpdb->get_var( $count_sql );
    $act_rows  = $wpdb->get_results( $rows_sql, ARRAY_A ) ?: [];
}

$act_pages = $act_total ? ceil( $act_total / $per_page ) : 1;

// ── Security log ───────────────────────────────────────────────────────────
$sec_table  = SEO_Dash_Database::$security_log;
$sec_exists = $wpdb->get_var( "SHOW TABLES LIKE '$sec_table'" );
$sec_rows   = $sec_exists ? $wpdb->get_results(
    "SELECT * FROM $sec_table ORDER BY created_at DESC LIMIT 50", ARRAY_A
) : [];

// ── WP Debug log ───────────────────────────────────────────────────────────
$wp_log_path = WP_CONTENT_DIR . '/debug.log';
$log_lines   = [];
if ( file_exists( $wp_log_path ) && is_readable( $wp_log_path ) ) {
    $all_lines    = file( $wp_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    $plugin_lines = array_filter( $all_lines, fn($l) =>
        stripos( $l, 'seo-client-dashboard' ) !== false || stripos( $l, 'seo_dash' ) !== false
    );
    $log_lines = array_reverse( array_values( array_slice( $plugin_lines ?: array_slice( $all_lines, -30 ), -60 ) ) );
}

// ── Level colour/icon helpers ──────────────────────────────────────────────
function seo_log_level_class( string $level ): string {
    return match( $level ) {
        'success' => 'seo-badge-green',
        'warning' => 'seo-badge-orange',
        'error'   => 'seo-badge-red',
        default   => 'seo-badge-muted',
    };
}
function seo_log_level_icon( string $level ): string {
    return match( $level ) {
        'success' => '✓',
        'warning' => '⚠',
        'error'   => '✕',
        default   => 'ℹ',
    };
}
function seo_log_row_bg( string $level ): string {
    return match( $level ) {
        'error'   => 'rgba(248,81,73,.06)',
        'warning' => 'rgba(255,168,0,.06)',
        'success' => 'rgba(63,185,80,.04)',
        default   => 'transparent',
    };
}
?>

<style>
.log-tabs { display:flex; gap:4px; margin-bottom:20px; border-bottom:2px solid var(--c-border); padding-bottom:0; }
.log-tab  { padding:8px 20px; font-size:13px; font-weight:600; cursor:pointer; border:none; background:none;
             color:var(--c-muted); border-bottom:2px solid transparent; margin-bottom:-2px; transition:.15s; }
.log-tab:hover  { color:var(--c-text); }
.log-tab.active { color:var(--c-primary); border-bottom-color:var(--c-primary); }
.log-pane { display:none; }
.log-pane.active { display:block; }
.log-filter-bar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
.log-filter-bar select, .log-filter-bar input {
    padding:6px 10px; font-size:12px; border:1px solid var(--c-border);
    border-radius:var(--r-sm); background:var(--c-surf); color:var(--c-text);
}
.log-filter-bar button { padding:6px 14px; font-size:12px; }
.act-row td { padding:9px 12px; font-size:12px; vertical-align:top; }
.act-row td:first-child { width:70px; }
.act-count { font-size:11px; background:var(--c-surf2); color:var(--c-muted);
             padding:2px 8px; border-radius:10px; margin-left:6px; }
.log-pager { display:flex; gap:6px; align-items:center; justify-content:flex-end; margin-top:12px; font-size:12px; }
.log-pager a { padding:4px 10px; border:1px solid var(--c-border); border-radius:var(--r-sm); color:var(--c-text); text-decoration:none; }
.log-pager a:hover { background:var(--c-surf2); }
.log-pager .current { padding:4px 10px; background:var(--c-primary); color:#fff; border-radius:var(--r-sm); }
.stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px; margin-bottom:20px; }
.stat-box { background:var(--c-surf); border:1px solid var(--c-border); border-radius:var(--r-sm);
            padding:14px 16px; text-align:center; }
.stat-box .num { font-size:22px; font-weight:700; color:var(--c-text); }
.stat-box .lbl { font-size:11px; color:var(--c-muted); margin-top:2px; }
.stat-box.err  .num { color:var(--c-red); }
.stat-box.warn .num { color:var(--c-orange, #f59e0b); }
.stat-box.ok   .num { color:var(--c-green); }
</style>

<div class="seo-page">
    <div class="seo-page-hd">
        <div>
            <h1 class="seo-page-title">System Log</h1>
            <p class="seo-page-subtitle">Activity log, security events, database status and debug information</p>
        </div>
        <div class="seo-page-actions">
            <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-log-refresh-btn">↺ Refresh</button>
        </div>
    </div>

    <!-- Tab nav -->
    <div class="log-tabs">
        <button class="log-tab active" data-tab="activity">Activity Log <span class="act-count"><?php echo $act_total; ?></span></button>
        <button class="log-tab" data-tab="db">Database Status</button>
        <button class="log-tab" data-tab="security">Security Events <span class="act-count"><?php echo count($sec_rows); ?></span></button>
        <button class="log-tab" data-tab="debug">Debug Log <span class="act-count"><?php echo count($log_lines); ?></span></button>
    </div>

    <!-- ═══════ ACTIVITY LOG ═══════ -->
    <div class="log-pane active" id="log-pane-activity">

        <?php if ( $act_exists ) :
            // Stats
            $err_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $act_table WHERE level='error'" );
            $warn_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $act_table WHERE level='warning'" );
            $ok_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $act_table WHERE level='success'" );
            $types_raw  = $wpdb->get_col( "SELECT DISTINCT object_type FROM $act_table WHERE object_type IS NOT NULL ORDER BY object_type" ) ?: [];
        ?>
        <div class="stat-grid">
            <div class="stat-box"><div class="num"><?php echo $act_total; ?></div><div class="lbl">Total Events</div></div>
            <div class="stat-box err"><div class="num"><?php echo $err_count; ?></div><div class="lbl">Errors</div></div>
            <div class="stat-box warn"><div class="num"><?php echo $warn_count; ?></div><div class="lbl">Warnings</div></div>
            <div class="stat-box ok"><div class="num"><?php echo $ok_count; ?></div><div class="lbl">Successes</div></div>
        </div>

        <!-- Filters -->
        <div class="log-filter-bar">
            <form method="get" style="display:contents;" id="log-filter-form">
                <?php
                // Preserve current URL params except log-specific ones
                foreach ( $_GET as $k => $v ) {
                    if ( ! in_array( $k, ['log_level','log_action','log_type','log_page'], true ) ) {
                        echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
                    }
                }
                ?>
                <input type="hidden" name="log_page" value="1">
                <select name="log_level" onchange="this.form.submit()">
                    <option value="">All Levels</option>
                    <?php foreach ( ['info','success','warning','error'] as $lv ) : ?>
                    <option value="<?php echo $lv; ?>" <?php selected( $filter_level, $lv ); ?>><?php echo ucfirst($lv); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="log_type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach ( $types_raw as $t ) : ?>
                    <option value="<?php echo esc_attr($t); ?>" <?php selected( $filter_type, $t ); ?>><?php echo esc_html($t); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="log_action" autocomplete="nope" placeholder="Search action…" value="<?php echo esc_attr($filter_action); ?>" style="width:180px;">
                <button type="submit" class="seo-btn seo-btn-ghost seo-btn-sm">Filter</button>
                <?php if ( $filter_level || $filter_action || $filter_type ) : ?>
                <a href="?" class="seo-btn seo-btn-ghost seo-btn-sm" style="text-decoration:none;">✕ Clear</a>
                <?php endif; ?>
            </form>
            <div style="margin-left:auto;">
                <button class="seo-btn seo-btn-danger seo-btn-sm" id="seo-clear-act-log-btn">🗑 Clear Log</button>
            </div>
        </div>

        <?php if ( empty($act_rows) ) : ?>
        <div style="text-align:center;padding:48px 20px;color:var(--c-subtle);">
            <div style="font-size:32px;margin-bottom:12px;">📋</div>
            <div style="font-size:14px;">No activity log entries<?php echo ($filter_level||$filter_action||$filter_type) ? ' matching filters' : ' yet'; ?>.</div>
            <?php if (!$filter_level && !$filter_action && !$filter_type) : ?>
            <div style="font-size:12px;color:var(--c-subtle);margin-top:6px;">Events will appear here as you use the plugin.</div>
            <?php endif; ?>
        </div>
        <?php else : ?>
        <div class="seo-panel" style="overflow:hidden;">
            <div class="seo-table-wrap" style="max-height:620px;overflow-y:auto;">
                <table class="seo-table" style="table-layout:fixed;width:100%;">
                    <colgroup>
                        <col style="width:60px;">  <!-- level -->
                        <col style="width:160px;"> <!-- action -->
                        <col style="width:90px;">  <!-- type -->
                        <col>                      <!-- detail -->
                        <col style="width:130px;"> <!-- object -->
                        <col style="width:110px;"> <!-- user -->
                        <col style="width:130px;"> <!-- time -->
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Level</th>
                            <th>Action</th>
                            <th>Type</th>
                            <th>Detail</th>
                            <th>Object</th>
                            <th>User</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $act_rows as $row ) :
                        $level = $row['level'] ?? 'info';
                        $bg    = seo_log_row_bg( $level );
                    ?>
                    <tr class="act-row" style="background:<?php echo $bg; ?>;">
                        <td>
                            <span class="seo-badge <?php echo seo_log_level_class($level); ?>" style="font-size:10px;">
                                <?php echo seo_log_level_icon($level); ?> <?php echo esc_html($level); ?>
                            </span>
                        </td>
                        <td style="font-family:monospace;font-size:11px;word-break:break-all;color:var(--c-text);">
                            <?php echo esc_html( $row['action'] ); ?>
                        </td>
                        <td>
                            <?php if ( $row['object_type'] ) : ?>
                            <span style="font-size:11px;background:var(--c-surf2);padding:2px 7px;border-radius:10px;color:var(--c-muted);">
                                <?php echo esc_html( $row['object_type'] ); ?>
                            </span>
                            <?php else : ?><span style="color:var(--c-subtle);font-size:11px;">—</span><?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:var(--c-muted);word-break:break-word;">
                            <?php echo esc_html( $row['detail'] ?: '—' ); ?>
                        </td>
                        <td style="font-size:11px;color:var(--c-text);">
                            <?php if ( $row['object_name'] ) : ?>
                                <span title="ID: <?php echo esc_attr($row['object_id']); ?>">
                                    <?php echo esc_html( mb_strimwidth( $row['object_name'], 0, 28, '…' ) ); ?>
                                </span>
                            <?php elseif ( $row['object_id'] ) : ?>
                                <span style="color:var(--c-muted);">ID <?php echo $row['object_id']; ?></span>
                            <?php else : ?>
                                <span style="color:var(--c-subtle);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:11px;color:var(--c-muted);">
                            <?php echo esc_html( $row['user_name'] ?: ( $row['user_id'] ? "User #{$row['user_id']}" : 'System' ) ); ?>
                        </td>
                        <td style="font-size:11px;color:var(--c-subtle);white-space:nowrap;">
                            <?php echo esc_html( date_i18n( 'M j, H:i:s', strtotime( $row['created_at'] ) ) ); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ( $act_pages > 1 ) :
            $base_url = add_query_arg( array_filter([
                'log_level'  => $filter_level,
                'log_action' => $filter_action,
                'log_type'   => $filter_type,
            ]) );
        ?>
        <div class="log-pager">
            <span style="color:var(--c-muted);">
                Showing <?php echo ($offset+1); ?>–<?php echo min($offset+$per_page, $act_total); ?> of <?php echo $act_total; ?>
            </span>
            <?php if ( $current_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg('log_page', $current_page-1, $base_url) ); ?>">‹ Prev</a>
            <?php endif; ?>
            <?php
            $range = range( max(1, $current_page-2), min($act_pages, $current_page+2) );
            foreach ( $range as $p ) :
                if ( $p === $current_page ) :
            ?>
                <span class="current"><?php echo $p; ?></span>
            <?php else : ?>
                <a href="<?php echo esc_url( add_query_arg('log_page', $p, $base_url) ); ?>"><?php echo $p; ?></a>
            <?php endif; endforeach; ?>
            <?php if ( $current_page < $act_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg('log_page', $current_page+1, $base_url) ); ?>">Next ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; // end empty check ?>

        <?php else : // activity_log table missing ?>
        <div style="padding:20px;background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.25);border-radius:var(--r-sm);color:var(--c-red);font-size:13px;">
            ⚠ Activity log table is missing.
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════ DATABASE STATUS ═══════ -->
    <div class="log-pane" id="log-pane-db">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="seo-panel">
                <div class="seo-panel-hd"><h2>Table Status</h2></div>
                <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:0;">
                    <?php
                    $tables_to_check = [
                        'Reports'           => SEO_Dash_Database::$reports,
                        'Clients'           => SEO_Dash_Database::$clients,
                        'Report ↔ Clients'  => SEO_Dash_Database::$report_clients,
                        'Integrations'      => SEO_Dash_Database::$integrations,
                        'Report ↔ Intgrtns' => SEO_Dash_Database::$report_integrations,
                        'GA Data'           => SEO_Dash_Database::$data_ga,
                        'SC Data'           => SEO_Dash_Database::$data_sc,
                        'Leads'             => SEO_Dash_Database::$data_leads,
                        'Backlinks'         => SEO_Dash_Database::$data_backlinks,
                        'GMB'               => SEO_Dash_Database::$data_gmb,
                        'Keywords'          => SEO_Dash_Database::$data_keywords,
                        'Technical'         => SEO_Dash_Database::$data_technical,
                        'Pages'             => SEO_Dash_Database::$data_pages,
                        'Settings'          => SEO_Dash_Database::$settings,
                        'Security Log'      => SEO_Dash_Database::$security_log,
                        'Activity Log'      => SEO_Dash_Database::$activity_log,
                    ];
                    $all_ok = true;
                    foreach ( $tables_to_check as $label => $table ) :
                        $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
                        $count  = $exists ? (int)$wpdb->get_var("SELECT COUNT(*) FROM $table") : null;
                        if ( ! $exists ) $all_ok = false;
                    ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--c-border);">
                        <span style="font-size:12px;color:var(--c-text);"><?php echo esc_html($label); ?></span>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php if ( $exists && $count !== null ) : ?>
                            <span style="font-size:11px;color:var(--c-muted);"><?php echo number_format($count); ?> rows</span>
                            <?php endif; ?>
                            <span style="font-size:11px;font-weight:700;<?php echo $exists ? 'color:var(--c-green);' : 'color:var(--c-red);'; ?>">
                                <?php echo $exists ? '✓ OK' : '✕ Missing'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top:12px;padding:10px 12px;border-radius:var(--r-sm);font-size:13px;
                                background:<?php echo $all_ok ? 'rgba(63,185,80,.1)' : 'rgba(248,81,73,.1)'; ?>;
                                color:<?php echo $all_ok ? 'var(--c-green)' : 'var(--c-red)'; ?>;
                                border:1px solid <?php echo $all_ok ? 'rgba(63,185,80,.25)' : 'rgba(248,81,73,.25)'; ?>;">
                        <?php echo $all_ok ? '✓ All 16 database tables are present and healthy.' : '⚠ Some tables are missing.'; ?>
                    </div>
                </div>
            </div>

            <div class="seo-panel">
                <div class="seo-panel-hd"><h2>Environment</h2></div>
                <div class="seo-panel-body">
                    <?php
                    $env = [
                        'PHP Version'          => PHP_VERSION,
                        'WordPress Version'    => get_bloginfo('version'),
                        'Plugin Version'       => SEO_DASH_VERSION,
                        'DB Version Stored'    => $db_version,
                        'OpenSSL'              => function_exists('openssl_encrypt') ? '✓ Available' : '✕ Not available',
                        'WP_DEBUG'             => defined('WP_DEBUG') && WP_DEBUG ? '🟡 ON' : 'OFF',
                        'WP_DEBUG_LOG'         => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? '✓ ON → /wp-content/debug.log' : 'OFF',
                        'AJAX URL'             => admin_url('admin-ajax.php'),
                        'Admin Dashboard URL'  => $admin_page  ? '<a href="'.esc_url(get_permalink($admin_pid)).'" target="_blank">'.esc_url(get_permalink($admin_pid)).'</a>' : '✕ Page missing',
                        'Client Portal URL'    => $client_page ? '<a href="'.esc_url(get_permalink($client_pid)).'" target="_blank">'.esc_url(get_permalink($client_pid)).'</a>' : '✕ Page missing',
                        'Total Reports'        => $total_reports,
                        'Total Clients'        => $act_exists ? (int)$wpdb->get_var("SELECT COUNT(*) FROM ".SEO_Dash_Database::$clients) : '—',
                        'Active Theme'         => wp_get_theme()->get('Name'),
                        'Memory Limit'         => WP_MEMORY_LIMIT,
                        'Max Upload'           => ini_get('upload_max_filesize'),
                        'Max Execution Time'   => ini_get('max_execution_time') . 's',
                    ];
                    foreach ($env as $k => $v) : ?>
                    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--c-border);font-size:12px;gap:10px;">
                        <span style="color:var(--c-muted);flex-shrink:0;"><?php echo esc_html($k); ?></span>
                        <span style="color:var(--c-text);text-align:right;word-break:break-all;"><?php echo $v; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════ SECURITY EVENTS ═══════ -->
    <div class="log-pane" id="log-pane-security">
        <div class="seo-panel">
            <div class="seo-panel-hd">
                <h2>Security Events <span class="act-count"><?php echo count($sec_rows); ?></span></h2>
                <?php if (!empty($sec_rows)) : ?>
                <button class="seo-btn seo-btn-danger seo-btn-sm" id="seo-clear-sec-log-btn">🗑 Clear</button>
                <?php endif; ?>
            </div>
            <?php if (empty($sec_rows)) : ?>
            <div style="text-align:center;padding:48px 20px;color:var(--c-subtle);font-size:13px;">
                <div style="font-size:28px;margin-bottom:8px;">🛡</div>
                No security events recorded.
            </div>
            <?php else : ?>
            <div class="seo-table-wrap">
                <table class="seo-table">
                    <thead><tr><th>Event</th><th>Detail</th><th>IP</th><th>User</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php foreach ($sec_rows as $row) :
                            $is_warn = in_array($row['event'], ['login_lockout','rate_limit_ajax','unauth_data_version_access'], true);
                        ?>
                        <tr>
                            <td><span class="seo-badge <?php echo $is_warn ? 'seo-badge-red' : 'seo-badge-muted'; ?>"><?php echo esc_html($row['event']); ?></span></td>
                            <td style="font-size:12px;color:var(--c-muted);"><?php echo esc_html($row['detail'] ?? '—'); ?></td>
                            <td style="font-size:12px;font-family:monospace;"><?php echo esc_html($row['ip'] ?? '—'); ?></td>
                            <td style="font-size:12px;">
                                <?php if ($row['user_id']) : $u = get_userdata($row['user_id']); echo esc_html($u ? $u->display_name : "User #{$row['user_id']}"); else : echo '<span style="color:var(--c-subtle);">—</span>'; endif; ?>
                            </td>
                            <td style="font-size:11px;color:var(--c-subtle);white-space:nowrap;"><?php echo esc_html(date_i18n('M j, H:i:s', strtotime($row['created_at']))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════ DEBUG LOG ═══════ -->
    <div class="log-pane" id="log-pane-debug">
        <div class="seo-panel">
            <div class="seo-panel-hd">
                <h2>WP Debug Log <span style="font-size:11px;font-weight:400;color:var(--c-subtle);margin-left:6px;">/wp-content/debug.log — plugin-related entries</span></h2>
                <span class="act-count"><?php echo count($log_lines); ?> entries</span>
            </div>
            <?php if (empty($log_lines)) : ?>
            <div style="text-align:center;padding:48px 20px;color:var(--c-subtle);font-size:13px;">
                <?php if (!file_exists($wp_log_path)) : ?>
                    <div style="font-size:28px;margin-bottom:8px;">📄</div>
                    debug.log not found.<br>
                    <span style="font-size:11px;">Enable <code>WP_DEBUG</code> and <code>WP_DEBUG_LOG</code> in wp-config.php to start logging.</span>
                <?php else : ?>
                    No plugin-related entries found in debug.log.
                <?php endif; ?>
            </div>
            <?php else : ?>
            <div style="padding:16px 20px;">
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:var(--r-sm);padding:16px;max-height:500px;overflow-y:auto;" id="debug-log-box">
                    <?php foreach ($log_lines as $line) :
                        $is_fatal = stripos($line,'fatal') !== false || stripos($line,'uncaught') !== false;
                        $is_warn  = stripos($line,'warning') !== false || stripos($line,'deprecated') !== false;
                        $color    = $is_fatal ? 'var(--c-red)' : ($is_warn ? 'var(--c-orange, #f59e0b)' : 'var(--c-text)');
                    ?>
                    <div style="font-family:monospace;font-size:11px;color:<?php echo $color; ?>;padding:3px 0;border-bottom:1px solid var(--c-border);word-break:break-all;line-height:1.5;">
                        <?php echo esc_html($line); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- .seo-page -->

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    // Tab switching
    $('.log-tab').on('click', function(){
        var tab = $(this).data('tab');
        $('.log-tab').removeClass('active');
        $(this).addClass('active');
        $('.log-pane').removeClass('active');
        $('#log-pane-' + tab).addClass('active');
        // Persist tab in URL hash
        history.replaceState(null, '', location.href.split('#')[0] + '#log-' + tab);
    });

    // Restore tab from hash
    var hash = location.hash.replace('#log-', '');
    if (hash && $('#log-pane-' + hash).length) {
        $('[data-tab="' + hash + '"]').trigger('click');
    }

    // Refresh
    $('#seo-log-refresh-btn').on('click', function(){ location.reload(); });

    // Clear activity log
    $('#seo-clear-act-log-btn').on('click', function(){
        if (!confirm('Clear all activity log entries? This cannot be undone.')) return;
        var $btn = $(this).text('Clearing…').prop('disabled', true);
        $.post(seoDash.ajax, {action:'seo_dash_clear_activity_log', nonce:seoDash.nonce}, function(r){
            if (r.success) { seoToast('Activity log cleared.','ok'); setTimeout(function(){ location.reload(); }, 800); }
            else { seoToast('Failed.','err'); $btn.text('🗑 Clear Log').prop('disabled', false); }
        });
    });

    // Clear security log
    $('#seo-clear-sec-log-btn').on('click', function(){
        if (!confirm('Clear all security log entries?')) return;
        $.post(seoDash.ajax, {action:'seo_dash_clear_sec_log', nonce:seoDash.nonce}, function(r){
            if (r.success) { seoToast('Security log cleared.','ok'); setTimeout(function(){ location.reload(); }, 800); }
            else seoToast('Failed.','err');
        });
    });

    // Auto-scroll debug log to top (newest first)
    var dbx = document.getElementById('debug-log-box');
    if (dbx) dbx.scrollTop = 0;
})(jQuery);
});
</script>
