<?php if ( ! defined('ABSPATH') ) exit;
// Variables from class-frontend-admin.php:
// $report, $tab, $tab_urls, $reports_url, $meta,
// $assigned_client_ids, $all_clients, $all_integrations, $report_intgs
$rid = intval($report['id']);

// Fetch assigned sheets for toolbars
global $gsheet_links;
$gsheet_links = [
    'ga'             => get_option("seo_dash_gsheet_link_{$rid}_ga", []),
    'service'        => get_option("seo_dash_gsheet_link_{$rid}_service", []),
    'blog'           => get_option("seo_dash_gsheet_link_{$rid}_blog", []),
    'gmb'            => get_option("seo_dash_gsheet_link_{$rid}_gmb", []),
    'gmb_posts'      => get_option("seo_dash_gsheet_link_{$rid}_gmb_posts", []),
    'technical'      => get_option("seo_dash_gsheet_link_{$rid}_technical", []),
    'backlinks'      => get_option("seo_dash_gsheet_link_{$rid}_backlinks", []),
    'leads'          => get_option("seo_dash_gsheet_link_{$rid}_leads", []),
    'click_tracking' => get_option("seo_dash_gsheet_link_{$rid}_click_tracking", []),
];
?>

<!-- seoJQ: run a callback as soon as jQuery is available. This makes every
     inline handler block below resilient to host optimizers/WAFs that defer
     or relocate the enqueued jQuery <script src>, which otherwise causes
     "jQuery is not defined" and kills Apply / Select-All on every tab. -->
<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
window.seoJQ = window.seoJQ || (function(){
    var q = [];
    var ready = false;
    function flush(){
        ready = true;
        var jq = window.jQuery;
        while (q.length){
            var cb = q.shift();
            try { cb(jq); } catch(e){ if (window.console) console.error('seoJQ callback error:', e); }
        }
    }
    function check(){
        if (window.jQuery){ flush(); return true; }
        return false;
    }
    // If jQuery is still missing after a short grace period, the host optimizer
    // likely stripped/blocked WordPress's own enqueued copy. Inject the bundled
    // same-origin jQuery (allowed by CSP 'self') as a last-resort fallback.
    var fallbackInjected = false;
    function injectFallback(){
        if (fallbackInjected || window.jQuery) return;
        fallbackInjected = true;
        var s = document.createElement('script');
        s.src = '/wp-includes/js/jquery/jquery.min.js';
        s.async = false;
        s.setAttribute('data-cfasync', 'false');
        s.setAttribute('data-no-optimize', '1');
        s.onload = check;
        (document.head || document.documentElement).appendChild(s);
    }

    // Poll for jQuery (covers the case where its <script> loads late/out of order).
    var tries = 0;
    var timer = setInterval(function(){
        tries++;
        if (tries === 20 && !window.jQuery){ injectFallback(); } // ~1s: try fallback
        if (check() || tries > 600){ clearInterval(timer); } // ~30s safety cap
    }, 50);
    // Also try on DOMContentLoaded / load as fast paths.
    if (document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', check);
    }
    window.addEventListener('load', check);
    check();
    return function(cb){
        if (typeof cb !== 'function') return;
        if (ready && window.jQuery){ try { cb(window.jQuery); } catch(e){ if(window.console) console.error('seoJQ callback error:', e); } }
        else { q.push(cb); check(); }
    };
})();
</script>

<!-- Report hero header -->
<div class="seo-report-hero">
    <div class="seo-report-hero-left">
        <a href="<?php echo esc_url($reports_url); ?>" class="seo-back-link">Reports</a>
        <div>
            <div class="seo-report-hero-title"><?php echo esc_html($report['title']); ?></div>
            <div class="seo-report-hero-meta">
                ID #<?php echo $rid; ?> ·
                <?php echo count($assigned_client_ids); ?> client<?php echo count($assigned_client_ids)!==1?'s':''; ?> ·
                Created <?php echo esc_html(date_i18n('M j, Y', strtotime($report['created_at']))); ?>
            </div>
        </div>
    </div>

</div>

<!-- Tab navigation -->
<?php
$tabs_def = [
    'overview'     => ['📊','Overview'],
    'database'     => ['🗄️','Database'],
    // 'analytics'    => ['📈','Analytics'], // Legacy
    // 'sc'           => ['🔍','Search Console'], // Legacy
    'service'      => ['🏆','Service Pages'],
    'blog'         => ['📍','Blog Posts'],
    'gmb'          => ['📍','Google Business'],
    'technical'    => ['⚙️','Technical Audit'],
    'backlinks'    => ['🔗','Backlinks'],
    'leads'        => ['💼','Leads'],
    'integrations' => ['🔌','Integrations'],
    'client_dashboard' => ['🖥️','Client Dashboard'],
];
?>
<div class="seo-tabs-wrap">
    <?php foreach ($tabs_def as $slug => [$ico,$lbl]) : ?>
    <a href="<?php echo esc_url($tab_urls[$slug]); ?>"
       class="seo-tab <?php echo $tab===$slug?'seo-tab-active':''; ?>">
        <?php echo $ico; ?> <?php echo esc_html($lbl); ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="seo-tab-body">
<?php

// ── OVERVIEW ──────────────────────────────────────────────────────────────
if ($tab === 'overview') :
        $groq_key_set = !empty(SEO_Dash_Database::get_setting('groq_api_key')) || !empty($meta['groq_key'] ?? '');
?>

<div id="ov-panel-data">


    
    <?php
    $overview_overall = get_option("seo_dash_overview_overall_{$rid}", []);

    // Sanitize: ensure from/to are valid yyyy-MM-dd dates (guard against corrupted/mojibake DB values).
    $seo_dash_valid_date = static function( $v, $fallback ): string {
        $v = preg_replace( '/[^\x20-\x7E]/', '', (string) $v ); // strip non-ASCII
        $v = sanitize_text_field( $v );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : $fallback;
    };
    $overview_overall['from']    = $seo_dash_valid_date( $overview_overall['from']    ?? '', date( 'Y-m-d', strtotime( '-1 year' ) ) );
    $overview_overall['to']      = $seo_dash_valid_date( $overview_overall['to']      ?? '', date( 'Y-m-d' ) );
    $overview_overall['traffic'] = preg_replace( '/[^0-9]/', '', (string) ( $overview_overall['traffic'] ?? '10785' ) );

    $overview_monthly_raw = get_option("seo_dash_overview_monthly_{$rid}", [
        ['month' => date('Y-m'), 'traffic' => '542']
    ]);
    // Sanitize each stored month value — strips mojibake/non-ASCII that causes browser yyyy-MM errors.
    $overview_monthly = [];
    foreach ( (array) $overview_monthly_raw as $m_row ) {
        $clean_month   = seo_dash_sanitize_month( (string) ( $m_row['month']   ?? '' ) );
        $clean_traffic = preg_replace( '/[^0-9]/', '', (string) ( $m_row['traffic'] ?? '' ) );
        if ( $clean_month === '' && $clean_traffic === '' ) continue;
        $overview_monthly[] = [ 'month' => $clean_month, 'traffic' => $clean_traffic ];
    }
    if ( empty( $overview_monthly ) ) {
        $overview_monthly = [ [ 'month' => date( 'Y-m' ), 'traffic' => '542' ] ];
    }
    ?>

    <!-- TOTAL TRAFFIC -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd"><h2>TOTAL TRAFFIC <span style="font-size:12px;color:var(--c-muted);font-weight:400;text-transform:uppercase;">(Overall — Date Range Selector)</span></h2></div>
        <div class="seo-panel-body" style="display:flex;align-items:center;gap:16px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;">From: 
                <input type="date" class="seo-in seo-overview-input" data-field="overall_from" value="<?php echo esc_attr($overview_overall['from'] ?? ''); ?>" style="width:140px;">
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;">To: 
                <input type="date" class="seo-in seo-overview-input" data-field="overall_to" value="<?php echo esc_attr($overview_overall['to'] ?? ''); ?>" style="width:140px;">
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;">Overall Traffic: 
                <input type="number" class="seo-in seo-overview-input" id="seo-overview-overall-traffic" data-field="overall_traffic" value="<?php echo esc_attr($overview_overall['traffic'] ?? ''); ?>" style="width:120px;">
            </label>
            <button class="seo-btn seo-btn-sm" style="background:var(--c-primary);color:#fff;border:none;" onclick="seoDashFetchOverallGA()">📈 Auto-Fill from GA4</button>
            <button class="seo-btn seo-btn-sm seo-overview-save-btn" onclick="seoDashSaveOverview(this)" style="background:var(--c-green);color:#fff;border:none;font-weight:700;">💾 Save</button>
        </div>
        <div style="padding:0 20px 20px;font-size:12px;color:var(--c-muted);display:flex;align-items:center;gap:6px;">
            <input type="checkbox" checked disabled> Charts auto-generate from monthly traffic data below.
        </div>
    </div>

    <!-- MONTHLY TRAFFIC -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd"><h2>MONTHLY TRAFFIC</h2></div>
        <div style="padding:16px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--c-border);flex-wrap:wrap;">
            <button class="seo-btn seo-btn-primary" onclick="seoDashAddMonthRow()" style="background:var(--c-primary);color:#fff;border:none;">＋ Add Month</button>
            <button class="seo-btn seo-overview-save-btn" onclick="seoDashSaveOverview(this)" style="background:var(--c-green);color:#fff;border:none;font-weight:700;">💾 Save Monthly Traffic</button>
            <button class="seo-btn seo-btn-ghost seo-btn-sm" style="color:var(--c-primary);border:1px solid var(--c-primary);background:var(--c-primary-alpha);">⬇️ Export CSV</button>
            <button class="seo-btn seo-btn-ghost seo-btn-sm" style="color:var(--c-primary);border:1px solid var(--c-primary);background:var(--c-primary-alpha);">⬇️ Download Format</button>
            <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;color:var(--c-primary);cursor:pointer;border:1px solid var(--c-primary);background:var(--c-primary-alpha);">
                ⬆️ Import CSV <input type="file" style="display:none;" onchange="seoToast('File selected. Use standard import handler.','info')">
            </label>
            
            <div style="display:flex;align-items:center;gap:8px;margin-left:auto;flex-wrap:wrap;">
                <input type="month" class="seo-in" id="seo-overview-fetch-month" style="width:160px;height:36px;color:var(--c-primary);border:1px solid var(--c-primary);background:var(--c-primary-alpha);">
                <button class="seo-btn" style="background:var(--c-primary);color:#fff;border:none;height:36px;" onclick="seoDashFetchMonthlyGA()">📅 Fetch Monthly from GA4</button>
            </div>
        </div>
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th style="width:200px;">MONTH</th>
                        <th>TRAFFIC</th>
                        <th style="width:100px;text-align:center;">REMOVE</th>
                    </tr>
                </thead>
                <tbody id="seo-overview-monthly-tbody">
                    <?php if (empty($overview_monthly)): ?>
                    <tr id="seo-overview-monthly-empty"><td colspan="3" style="text-align:center;color:var(--c-muted);padding:30px;">No monthly data added yet.</td></tr>
                    <?php else: foreach ($overview_monthly as $idx => $m): ?>
                    <tr>
                        <td><input type="month" class="seo-in seo-overview-monthly-input" data-idx="<?php echo $idx; ?>" data-field="month" value="<?php echo esc_attr($m['month']); ?>" style="width:180px;"></td>
                        <td><input type="number" class="seo-in seo-overview-monthly-input" data-idx="<?php echo $idx; ?>" data-field="traffic" value="<?php echo esc_attr($m['traffic']); ?>" style="width:180px;"></td>
                        <td style="text-align:center;"><button class="seo-icon-btn seo-icon-btn-d seo-overview-monthly-remove-btn" data-idx="<?php echo $idx; ?>" style="color:var(--c-red);background:var(--c-surf2);border:1px solid var(--c-border);padding:4px 8px;border-radius:4px;font-size:12px;font-weight:700;">✕</button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TOTAL PAGES RANKED -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd" style="display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:12px;">
                <h2 style="margin:0;">TOTAL PAGES RANKED</h2>
            </div>
        </div>
        
        <div style="display:flex;flex-wrap:wrap;gap:0;border-bottom:2px solid var(--c-border);padding:0 20px;background:var(--c-surf2);">
            <button class="db-type-tab db-type-tab-active" onclick="jQuery('.seo-ov-page-tab').hide();jQuery('#seo-ov-page-service').show();jQuery(this).addClass('db-type-tab-active').siblings().removeClass('db-type-tab-active');" style="padding:10px 16px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;border-bottom:3px solid var(--c-primary);margin-bottom:-2px;color:var(--c-primary);">🏆 Service Pages</button>
            <button class="db-type-tab" onclick="jQuery('.seo-ov-page-tab').hide();jQuery('#seo-ov-page-blog').show();jQuery(this).addClass('db-type-tab-active').siblings().removeClass('db-type-tab-active');" style="padding:10px 16px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:var(--c-muted);">📍 Blog Posts</button>
        </div>

        <?php 
        $active_month = get_option("seo_dash_active_month_{$rid}_ga", '');
        // NUCLEAR FIX: Load 30d and overall from ALL months (no filter) so they always display
        global $wpdb;
        $ov_rows_all = $wpdb->get_results( $wpdb->prepare(
            "SELECT page_url, period_type, users FROM " . SEO_Dash_Database::$data_ga .
            " WHERE report_id = %d AND period_type IN ('30d','overall') AND trashed = 0",
            $rid
        ), ARRAY_A ) ?: [];
        $traffic = [];
        foreach ($ov_rows_all as $r) {
            $u = trim($r['page_url']);
            if (!$u) continue;
            if (!isset($traffic[$u])) $traffic[$u] = ['monthly' => 0, 'overall' => 0];
            if ($r['period_type'] === '30d') $traffic[$u]['monthly'] += (int)$r['users'];
            if ($r['period_type'] === 'overall') $traffic[$u]['overall'] += (int)$r['users'];
        }
        foreach (['service' => 'Service Pages', 'blog' => 'Blog Posts'] as $map_type => $map_label): ?>
        <?php
        $map = get_option("seo_dash_custom_pages_{$rid}_{$map_type}", []);
        $overview_pages = [];
        $p1=0; $p2=0; $p3=0; $p4=0; $p5=0; $pai=0;
        if (is_array($map)) {
            foreach ($map as $u => $cdata) {
                if (!empty($cdata['show_on_overview'])) {
                    $cdata['_type'] = $map_type;
                    $overview_pages[] = $cdata;
                    $r = intval($cdata['ranked_page'] ?? 0);
                    if ($r === 1) $p1++;
                    elseif ($r === 2) $p2++;
                    elseif ($r === 3) $p3++;
                    elseif ($r === 4) $p4++;
                    elseif ($r >= 5) $p5++;
                    if (!empty($cdata['ai_overview'])) $pai++;
                }
            }
        }
        ?>
        <div id="seo-ov-page-<?php echo $map_type; ?>" class="seo-ov-page-tab" style="<?php echo $map_type === 'blog' ? 'display:none;' : ''; ?>">
            <div style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;background:var(--c-surf);flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span style="background:var(--c-primary-alpha);color:var(--c-primary);padding:2px 8px;border-radius:12px;font-size:12px;font-weight:700;">Total: <?php echo count($overview_pages); ?></span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <div class="db-rp-filter db-rp-filter-active" data-filter="all" onclick="dbRpFilterToggle(this, '<?php echo $map_type; ?>')" style="padding:4px 12px;background:var(--c-primary);border:1px solid var(--c-primary);border-radius:20px;font-size:12px;color:#fff;font-weight:600;cursor:pointer;">All <span style="background:rgba(255,255,255,0.2);padding:2px 6px;border-radius:8px;margin-left:4px;"><?php echo count($overview_pages); ?></span></div>
                    <div class="db-rp-filter" data-filter="1" onclick="dbRpFilterToggle(this, '<?php echo $map_type; ?>')" style="padding:4px 12px;background:var(--c-primary-alpha);border:1px solid var(--c-primary-alpha);border-radius:20px;font-size:12px;color:var(--c-primary);font-weight:600;cursor:pointer;">Page 1 <span style="background:rgba(255,255,255,0.5);padding:2px 6px;border-radius:8px;margin-left:4px;"><?php echo $p1; ?></span></div>
                    <div class="db-rp-filter" data-filter="2" onclick="dbRpFilterToggle(this, '<?php echo $map_type; ?>')" style="padding:4px 12px;background:var(--c-primary-alpha);border:1px solid var(--c-primary-alpha);border-radius:20px;font-size:12px;color:var(--c-primary);font-weight:600;cursor:pointer;">Page 2 <span style="background:rgba(255,255,255,0.5);padding:2px 6px;border-radius:8px;margin-left:4px;"><?php echo $p2; ?></span></div>
                    <div class="db-rp-filter" data-filter="3" onclick="dbRpFilterToggle(this, '<?php echo $map_type; ?>')" style="padding:4px 12px;background:var(--c-primary-alpha);border:1px solid var(--c-primary-alpha);border-radius:20px;font-size:12px;color:var(--c-primary);font-weight:600;cursor:pointer;">Page 3+ <span style="background:rgba(255,255,255,0.5);padding:2px 6px;border-radius:8px;margin-left:4px;"><?php echo ($p3+$p4+$p5); ?></span></div>
                    <div class="db-rp-filter" data-filter="ai" onclick="dbRpFilterToggle(this, '<?php echo $map_type; ?>')" style="padding:4px 12px;background:rgba(236,72,153,.1);border:1px solid rgba(236,72,153,.2);border-radius:20px;font-size:12px;color:#ec4899;font-weight:600;cursor:pointer;">✨ AI Overview <span style="background:rgba(236,72,153,.2);padding:2px 6px;border-radius:8px;margin-left:4px;"><?php echo $pai; ?></span></div>
                </div>
            </div>
            

            <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); border-top:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <select class="seo-in seo-in-sm seo-custom-page-bulk-action-sel" data-type="<?php echo $map_type; ?>" style="width:auto;padding:4px 8px;font-size:12px;">
                        <option value="">Bulk Actions</option>
                        <option value="trash">Move Selected to Trash</option>
                        <option value="restore">Restore Selected from Trash</option>
                        <option value="remove">Delete Permanently</option>
                        <option value="remove_all">Delete All</option>
                    </select>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-custom-page-bulk-action-btn" data-type="<?php echo $map_type; ?>">Apply</button>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="seo-ov-pages-tbl-<?php echo $map_type; ?>">⬇️ Export CSV</button>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="<?php echo $map_type; ?>">⬇️ Download Format</button>
                    <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">
                        ⬆️ Import CSV
                        <input type="file" class="seo-import-csv-input" data-type="<?php echo $map_type; ?>" data-context="overview" accept=".csv" style="display:none;">
                    </label>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-custom-page-view-trash-btn" data-type="<?php echo $map_type; ?>">🗑️ View Trash</button>
                </div>
            </div>
            
            <div class="seo-table-wrap" style="max-height:400px;overflow-x:auto;overflow-y:auto;position:relative;">
                <table class="seo-table" id="seo-ov-pages-tbl-<?php echo $map_type; ?>" style="position:relative;min-width:1200px;">
                    <thead style="position:sticky;top:0;z-index:2;background:var(--c-surf);box-shadow:0 1px 0 var(--c-border);">
                        <tr>
                            <th style="width:40px;text-align:center;"><input type="checkbox" class="seo-custom-page-check-all" data-type="<?php echo $map_type; ?>"></th>
                            <th style="width:40px;text-align:center;">SR</th>
                            <th><?php echo $map_type === 'blog' ? 'Article Title' : 'Service Name'; ?></th>
                            <th style="width:60px;text-align:center;">Visit</th>
                            <th><?php echo $map_type === 'blog' ? 'Article Keyword' : 'Keyword'; ?></th>
                            <th style="text-align:right;">Monthly Traffic</th>
                            <th style="text-align:right;">Total Traffic</th>
                            <th>Ranked Page</th>
                            <th style="text-align:center;">AI Overview</th>
                            <?php if ($map_type === 'service'): ?>
                            <th>Month</th>
                            <?php else: ?>
                            <th>Publish Date</th>
                            <?php endif; ?>
                            <th style="text-align:center;width:60px;">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($overview_pages)): ?>
                        <tr class="seo-admin-rp-row-<?php echo $map_type; ?>" data-rank="all" data-ai="0"><td colspan="11" style="text-align:center;color:var(--c-muted);padding:30px;">No ranked pages added. Go to <?php echo $map_label; ?> to add them here.</td></tr>
                        <?php else: foreach ($overview_pages as $idx => $p): 
                            $u = $p['url'];
                            $u_esc = esc_attr($u);
                            $type = $map_type;
                            $t_monthly = isset($traffic[$u]) ? number_format($traffic[$u]['monthly']) : '—';
                            $t_overall = isset($traffic[$u]) ? number_format($traffic[$u]['overall']) : '—';
                        ?>
                        <tr class="seo-admin-rp-row-<?php echo $map_type; ?>" data-rank="<?php echo esc_attr($p['ranked_page'] ?? ''); ?>" data-ai="<?php echo !empty($p['ai_overview']) ? '1' : '0'; ?>">
                            <td style="text-align:center;">
                                <input type="checkbox" class="seo-custom-page-chk" value="<?php echo esc_url($u); ?>" data-type="<?php echo $type; ?>">
                            </td>
                            <td style="text-align:center;font-size:11px;color:var(--c-muted);"><?php echo $idx + 1; ?></td>
                            <td>
                                <input type="text" class="seo-in seo-custom-page-input" data-field="title" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($p['title'] ?? ''); ?>" style="width:100%;font-size:12px;padding:4px 8px;border:1px solid transparent;background:transparent;" onfocus="this.style.border='1px solid var(--c-border)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid transparent';this.style.background='transparent';">
                            </td>
                            <td style="text-align:center;">
                                <a href="<?php echo esc_url($u); ?>" target="_blank" style="color:var(--c-primary);text-decoration:none;font-size:14px;">↗</a>
                            </td>
                            <td>
                                <input type="text" class="seo-in seo-custom-page-input" data-field="keyword" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($p['keyword'] ?? ''); ?>" placeholder="comma separated..." style="width:100%;font-size:12px;padding:4px 8px;">
                            </td>
                            <td style="text-align:right;font-size:12px;font-weight:600;"><?php echo $t_monthly; ?></td>
                            <td style="text-align:right;font-size:12px;font-weight:600;"><?php echo $t_overall; ?></td>
                            
                            <td>
                                <input type="text" class="seo-in seo-custom-page-input" data-field="ranked_page" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($p['ranked_page'] ?? ''); ?>" style="width:100%;font-size:12px;padding:4px 8px;">
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" class="seo-custom-page-input" data-field="ai_overview" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="1" <?php checked(!empty($p['ai_overview'])); ?>>
                            </td>
                            
                            <?php if ($type === 'service'): ?>
                            <td>
                                <input type="month" class="seo-in seo-custom-page-input" data-field="month" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($p['month'] ?? ''); ?>" style="width:130px;font-size:12px;padding:4px 8px;">
                            </td>
                            <?php else: ?>
                            <td>
                                <input type="date" class="seo-in seo-custom-page-input" data-field="publish_date" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($p['publish_date'] ?? ''); ?>" style="width:130px;font-size:12px;padding:4px 8px;">
                            </td>
                            <?php endif; ?>
                            <td style="text-align:center;"><button class="seo-icon-btn seo-icon-btn-d" onclick="jQuery(this).closest('tr').fadeOut(function(){jQuery(this).remove();seoToast('Removed from Overview.','ok');}); jQuery.post(seoDash.ajax, {action:'seo_dash_save_custom_page_field', nonce:seoDash.nonce, report_id:<?php echo $rid; ?>, page_type:'<?php echo esc_js($p['_type']); ?>', url:'<?php echo esc_js($p['url']); ?>', field:'show_on_overview', value:0});" style="color:var(--c-red);background:var(--c-surf2);border:1px solid var(--c-border);padding:4px 8px;border-radius:4px;font-size:12px;font-weight:700;">✕</button></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <div style="padding:12px 20px;background:var(--c-surf2);border-top:1px solid var(--c-border);font-size:13px;color:var(--c-text);">
                    📊 <strong>Summary:</strong> Page 1: <?php echo $p1; ?> pages <span style="margin:0 8px;color:var(--c-border);">|</span> Page 2: <?php echo $p2; ?> pages <span style="margin:0 8px;color:var(--c-border);">|</span> Page 3: <?php echo $p3; ?> pages <span style="margin:0 8px;color:var(--c-border);">|</span> Page 4: <?php echo $p4; ?> pages <span style="margin:0 8px;color:var(--c-border);">|</span> Page 5+: <?php echo $p5; ?> pages
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- REPORT SUMMARY -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <h2 style="margin:0;">📝 Report Summary <span style="font-size:12px;color:var(--c-muted);font-weight:400;text-transform:none;">Shown on the client's Overview tab</span></h2>
            <div style="display:flex;gap:8px;align-items:center;">
                <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-summary-ai-btn" data-rid="<?php echo $rid; ?>">✨ Generate with AI</button>
                <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-summary-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Summary</button>
            </div>
        </div>
        <div class="seo-panel-body">
            <?php
            wp_editor(
                isset( $meta['summary'] ) ? $meta['summary'] : '',
                'seo_report_summary',
                [
                    'textarea_name' => 'seo_report_summary',
                    'media_buttons' => false,
                    'textarea_rows' => 10,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]
            );
            ?>
            <div id="seo-summary-status" style="margin-top:10px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>
    <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
    function seoDashGetSummaryEditorContent() {
        if (typeof tinymce !== 'undefined' && tinymce.get('seo_report_summary') && !tinymce.get('seo_report_summary').isHidden()) {
            tinymce.get('seo_report_summary').save(); // sync TinyMCE content back into the textarea
        }
        return jQuery('#seo_report_summary').val();
    }
    function seoDashSetSummaryEditorContent(html) {
        jQuery('#seo_report_summary').val(html);
        if (typeof tinymce !== 'undefined' && tinymce.get('seo_report_summary')) {
            tinymce.get('seo_report_summary').setContent(html);
        }
    }
    seoJQ(function($){
        $('#seo-summary-save-btn').on('click', function(){
            var $btn = $(this).text('Saving…').prop('disabled', true);
            var rid  = $btn.data('rid');
            $.post(seoDash.ajax, {
                action: 'seo_dash_save_report_meta',
                nonce: seoDash.nonce,
                report_id: rid,
                summary: seoDashGetSummaryEditorContent()
            }, function(r){
                $btn.text('💾 Save Summary').prop('disabled', false);
                if (r.success) {
                    seoToast('Summary saved.', 'ok');
                    $('#seo-summary-status').text('✓ Saved').css('color', 'var(--c-green)');
                    setTimeout(function(){ $('#seo-summary-status').text(''); }, 3000);
                } else {
                    seoToast(r.data && r.data.message ? r.data.message : 'Failed to save summary.', 'err');
                }
            }).fail(function(){
                $btn.text('💾 Save Summary').prop('disabled', false);
                seoToast('Server error saving summary.', 'err');
            });
        });

        $('#seo-summary-ai-btn').on('click', function(){
            var $btn = $(this);
            var rid  = $btn.data('rid');
            var hasContent = $.trim(seoDashGetSummaryEditorContent().replace(/<[^>]*>/g, '')).length > 0;
            if (hasContent && !confirm('This will replace the current summary with an AI-generated one. Continue?')) return;

            $btn.text('✨ Generating…').prop('disabled', true);
            $('#seo-summary-status').css('color', 'var(--c-muted)').text('Analyzing report data and writing summary — this can take up to a minute…');

            $.post(seoDash.ajax, {
                action: 'seo_dash_generate_summary',
                nonce: seoDash.nonce,
                report_id: rid
            }, function(r){
                $btn.text('✨ Generate with AI').prop('disabled', false);
                if (r.success && r.data && r.data.summary) {
                    seoDashSetSummaryEditorContent(r.data.summary);
                    seoToast('Summary generated. Review it, then click "Save Summary".', 'ok');
                    $('#seo-summary-status').css('color', 'var(--c-green)').text('✓ Generated — review and click "Save Summary" to publish.');
                } else {
                    var msg = (r.data && r.data.message) ? r.data.message : 'Failed to generate summary.';
                    seoToast(msg, 'err');
                    $('#seo-summary-status').css('color', 'var(--c-red)').text(msg);
                }
            }).fail(function(){
                $btn.text('✨ Generate with AI').prop('disabled', false);
                seoToast('Server error generating summary.', 'err');
                $('#seo-summary-status').css('color', 'var(--c-red)').text('Server error generating summary.');
            });
        });
    });
    </script>

    <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
    function dbRpFilterToggle(el, type) {
        var container = el.parentElement;
        var toggles = container.querySelectorAll('.db-rp-filter');
        toggles.forEach(function(t) {
            t.classList.remove('db-rp-filter-active');
            t.style.background = (t.getAttribute('data-filter')==='ai') ? 'rgba(236,72,153,.1)' : 'var(--c-primary-alpha)';
            t.style.color = (t.getAttribute('data-filter')==='ai') ? '#ec4899' : 'var(--c-primary)';
            t.style.borderColor = (t.getAttribute('data-filter')==='ai') ? 'rgba(236,72,153,.2)' : 'var(--c-primary-alpha)';
            var sp = t.querySelector('span');
            if(sp) sp.style.background = (t.getAttribute('data-filter')==='ai') ? 'rgba(236,72,153,.2)' : 'rgba(255,255,255,0.5)';
        });
        
        el.classList.add('db-rp-filter-active');
        el.style.background = (el.getAttribute('data-filter')==='ai') ? '#ec4899' : 'var(--c-primary)';
        el.style.color = '#fff';
        el.style.borderColor = (el.getAttribute('data-filter')==='ai') ? '#ec4899' : 'var(--c-primary)';
        var sp = el.querySelector('span');
        if(sp) sp.style.background = 'rgba(255,255,255,0.2)';
        
        var filter = el.getAttribute('data-filter');
        var rows = document.querySelectorAll('.seo-admin-rp-row-' + type);
        rows.forEach(function(r) {
            if (filter === 'all') {
                r.style.display = '';
            } else if (filter === 'ai') {
                r.style.display = (r.getAttribute('data-ai') === '1') ? '' : 'none';
            } else {
                var rVal = r.getAttribute('data-rank').toString().trim();
                if (filter === '1') {
                    r.style.display = (rVal === '1' || rVal === '0' || rVal.toLowerCase() === 'ai') ? '' : 'none';
                } else if (filter === '2') {
                    r.style.display = (rVal === '2') ? '' : 'none';
                } else if (filter === '3') {
                    var rValInt = parseInt(rVal);
                    r.style.display = (!isNaN(rValInt) && rValInt >= 3) ? '' : 'none';
                }
            }
        });
    }
    </script>

    <!-- SEARCH CONSOLE & ANALYTICS SCREENSHOTS -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd" style="display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:16px;">📸</span>
                <h2 style="margin:0;font-size:14px;color:var(--c-text);text-transform:uppercase;">SEARCH CONSOLE & ANALYTICS SCREENSHOTS</h2>
            </div>
            <button class="seo-btn seo-btn-primary" onclick="seoDashSaveScreenshots(this)" style="background:var(--c-primary);color:#fff;border:none;">💾 Save Screenshots</button>
        </div>
        <div style="padding:16px 20px;">
            <div style="background:var(--c-primary-alpha);border:1px solid var(--c-primary);padding:12px 16px;border-radius:8px;color:var(--c-primary);font-size:13px;display:flex;align-items:center;gap:8px;margin-bottom:20px;">
                <span style="background:var(--c-primary);color:#fff;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;">✓</span>
                <span>Screenshots are saved independently — click <em>Save Screenshots</em> after uploading. They will never be removed when you update the report.</span>
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:16px;margin-bottom:20px;">
                <div style="position:relative;">
                    <div style="font-weight:600;font-size:12px;color:var(--c-text);margin-bottom:8px;">Search Console — Monthly</div>
                    <?php $sc_m = $report['meta']['sc_monthly'] ?? ''; ?>
                    <div class="seo-screenshot-preview" id="sc-monthly-preview" style="height:100px;border:1px dashed var(--c-border);border-radius:6px;margin-bottom:8px;background-color:var(--c-surf2);<?php echo $sc_m ? "background-image:url('".esc_url($sc_m)."');" : ''; ?>background-size:cover;background-position:center;position:relative;">
                        <button class="seo-screenshot-remove" onclick="seoDashRemoveImage('sc-monthly-preview', 'sc_monthly_input')" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:var(--c-red);color:#fff;border:none;border-radius:50%;font-size:10px;cursor:pointer;display:<?php echo $sc_m ? 'block' : 'none'; ?>;">✕</button>
                    </div>
                    <label class="seo-btn seo-btn-ghost seo-btn-sm" style="display:inline-block;width:100%;text-align:center;cursor:pointer;border:1px solid var(--c-border);color:var(--c-text);background:var(--c-surf2);font-size:11px;box-sizing:border-box;">
                        📷 Upload / Change
                        <input type="file" onchange="seoDashDirectUpload(this, 'sc-monthly-preview', 'sc_monthly_input')" style="display:none;" accept="image/*">
                    </label>
                    <input type="hidden" class="seo-screenshot-input" id="sc_monthly_input" data-field="sc_monthly" value="<?php echo esc_attr($sc_m); ?>">
                </div>
                <div style="position:relative;">
                    <div style="font-weight:600;font-size:12px;color:var(--c-text);margin-bottom:8px;">Search Console — Overall</div>
                    <?php $sc_o = $report['meta']['sc_overall'] ?? ''; ?>
                    <div class="seo-screenshot-preview" id="sc-overall-preview" style="height:100px;border:1px dashed var(--c-border);border-radius:6px;margin-bottom:8px;background-color:var(--c-surf2);<?php echo $sc_o ? "background-image:url('".esc_url($sc_o)."');" : ''; ?>background-size:cover;background-position:center;position:relative;">
                        <button class="seo-screenshot-remove" onclick="seoDashRemoveImage('sc-overall-preview', 'sc_overall_input')" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:var(--c-red);color:#fff;border:none;border-radius:50%;font-size:10px;cursor:pointer;display:<?php echo $sc_o ? 'block' : 'none'; ?>;">✕</button>
                    </div>
                    <label class="seo-btn seo-btn-ghost seo-btn-sm" style="display:inline-block;width:100%;text-align:center;cursor:pointer;border:1px solid var(--c-border);color:var(--c-text);background:var(--c-surf2);font-size:11px;box-sizing:border-box;">
                        📷 Upload / Change
                        <input type="file" onchange="seoDashDirectUpload(this, 'sc-overall-preview', 'sc_overall_input')" style="display:none;" accept="image/*">
                    </label>
                    <input type="hidden" class="seo-screenshot-input" id="sc_overall_input" data-field="sc_overall" value="<?php echo esc_attr($sc_o); ?>">
                </div>
                <div style="position:relative;">
                    <div style="font-weight:600;font-size:12px;color:var(--c-text);margin-bottom:8px;">Analytics — Monthly</div>
                    <?php $ga_m = $report['meta']['ga_monthly'] ?? ''; ?>
                    <div class="seo-screenshot-preview" id="ga-monthly-preview" style="height:100px;border:1px dashed var(--c-border);border-radius:6px;margin-bottom:8px;background-color:var(--c-surf2);<?php echo $ga_m ? "background-image:url('".esc_url($ga_m)."');" : ''; ?>background-size:cover;background-position:center;position:relative;">
                        <button class="seo-screenshot-remove" onclick="seoDashRemoveImage('ga-monthly-preview', 'ga_monthly_input')" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:var(--c-red);color:#fff;border:none;border-radius:50%;font-size:10px;cursor:pointer;display:<?php echo $ga_m ? 'block' : 'none'; ?>;">✕</button>
                    </div>
                    <label class="seo-btn seo-btn-ghost seo-btn-sm" style="display:inline-block;width:100%;text-align:center;cursor:pointer;border:1px solid var(--c-border);color:var(--c-text);background:var(--c-surf2);font-size:11px;box-sizing:border-box;">
                        📷 Upload / Change
                        <input type="file" onchange="seoDashDirectUpload(this, 'ga-monthly-preview', 'ga_monthly_input')" style="display:none;" accept="image/*">
                    </label>
                    <input type="hidden" class="seo-screenshot-input" id="ga_monthly_input" data-field="ga_monthly" value="<?php echo esc_attr($ga_m); ?>">
                </div>
                <div style="position:relative;">
                    <div style="font-weight:600;font-size:12px;color:var(--c-text);margin-bottom:8px;">Analytics — Overall</div>
                    <?php $ga_o = $report['meta']['ga_overall'] ?? ''; ?>
                    <div class="seo-screenshot-preview" id="ga-overall-preview" style="height:100px;border:1px dashed var(--c-border);border-radius:6px;margin-bottom:8px;background-color:var(--c-surf2);<?php echo $ga_o ? "background-image:url('".esc_url($ga_o)."');" : ''; ?>background-size:cover;background-position:center;position:relative;">
                        <button class="seo-screenshot-remove" onclick="seoDashRemoveImage('ga-overall-preview', 'ga_overall_input')" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:var(--c-red);color:#fff;border:none;border-radius:50%;font-size:10px;cursor:pointer;display:<?php echo $ga_o ? 'block' : 'none'; ?>;">✕</button>
                    </div>
                    <label class="seo-btn seo-btn-ghost seo-btn-sm" style="display:inline-block;width:100%;text-align:center;cursor:pointer;border:1px solid var(--c-border);color:var(--c-text);background:var(--c-surf2);font-size:11px;box-sizing:border-box;">
                        📷 Upload / Change
                        <input type="file" onchange="seoDashDirectUpload(this, 'ga-overall-preview', 'ga_overall_input')" style="display:none;" accept="image/*">
                    </label>
                    <input type="hidden" class="seo-screenshot-input" id="ga_overall_input" data-field="ga_overall" value="<?php echo esc_attr($ga_o); ?>">
                </div>
            </div>
            </div>
            

        </div>
    </div>
    
    <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
    jQuery(document).on('change', '.seo-overview-input', function(){
        // No longer a fake "auto-save". Real persistence happens via the
        // Save buttons (seoDashSaveOverview). Just flag unsaved changes here.
        seoOverviewMarkDirty();
    });

    // ── Overview save: dirty-state tracking ────────────────────────────────
    var seoOverviewDirty = false;
    function seoOverviewMarkDirty() {
        seoOverviewDirty = true;
        jQuery('.seo-overview-save-btn').css('box-shadow', '0 0 0 3px rgba(63,185,80,.40)');
    }
    function seoOverviewMarkClean() {
        seoOverviewDirty = false;
        jQuery('.seo-overview-save-btn').css('box-shadow', 'none');
    }

    // Mark unsaved whenever a monthly row field is edited too.
    jQuery(document).on('input change', '.seo-overview-monthly-input', function(){
        seoOverviewMarkDirty();
    });

    // The server-rendered monthly "✕" buttons had no click handler at all,
    // so deletions never worked. Wire them up (JS-added rows use inline
    // onclick, which still works alongside this delegated handler).
    jQuery(document).on('click', '.seo-overview-monthly-remove-btn', function(){
        jQuery(this).closest('tr').remove();
        var tbody = jQuery('#seo-overview-monthly-tbody');
        if (tbody.find('tr').length === 0) {
            tbody.html('<tr id="seo-overview-monthly-empty"><td colspan="3" style="text-align:center;color:var(--c-muted);padding:30px;">No monthly data added yet.</td></tr>');
        }
        seoOverviewMarkDirty();
    });

    // ── Global GA error extractor — always shows the real server message ────
    function seoGaErrMsg(res) {
        if (!res) return 'No response from server.';
        if (res.data && res.data.message) return res.data.message;
        if (typeof res.data === 'string' && res.data) return res.data;
        if (res.message) return res.message;
        try { return JSON.stringify(res).substring(0, 200); } catch(e) { return 'Unexpected server response.'; }
    }
    function seoHttpErrMsg(xhr, status, err) {
        var raw = '';
        if (xhr && xhr.responseText) {
            raw = xhr.responseText.replace(/<[^>]+>/g,'').replace(/\s+/g,' ').trim().substring(0, 250);
        }
        return raw || err || status || 'Network/server error.';
    }

    // Warn before navigating away with unsaved Overview edits.
    jQuery(window).on('beforeunload', function(){
        if (seoOverviewDirty) { return 'You have unsaved Overview changes.'; }
    });

    // ── Overview save: collect everything and persist to the server ────────
    function seoDashSaveOverview(btn) {
        var overall_from    = jQuery('.seo-overview-input[data-field="overall_from"]').val() || '';
        var overall_to      = jQuery('.seo-overview-input[data-field="overall_to"]').val() || '';
        var overall_traffic = jQuery('#seo-overview-overall-traffic').val() || '';

        // Read every monthly row currently in the table (skip the empty-state
        // placeholder and fully blank rows).
        var monthly = [];
        jQuery('#seo-overview-monthly-tbody tr').each(function(){
            var $tr = jQuery(this);
            if ($tr.attr('id') === 'seo-overview-monthly-empty') return;
            var month   = $tr.find('input[data-field="month"]').val() || '';
            var traffic = $tr.find('input[data-field="traffic"]').val() || '';
            if (month === '' && traffic === '') return;
            monthly.push({ month: month, traffic: traffic });
        });

        // Disable both Save buttons and show progress on whichever was clicked.
        var $allBtns = jQuery('.seo-overview-save-btn');
        var savedLabels = $allBtns.map(function(){ return jQuery(this).html(); }).get();
        $allBtns.prop('disabled', true).css('opacity', '0.7');
        jQuery(btn).html('⏳ Saving...');

        jQuery.post(seoDash.ajax, {
            action: 'seo_dash_save_overview',
            nonce: seoDash.nonce,
            report_id: <?php echo (int) $rid; ?>,
            overall_from: overall_from,
            overall_to: overall_to,
            overall_traffic: overall_traffic,
            monthly: JSON.stringify(monthly)
        }, function(r){
            $allBtns.prop('disabled', false).css('opacity', '1').each(function(i){
                jQuery(this).html(savedLabels[i]);
            });
            if (r && r.success) {
                seoOverviewMarkClean();
                seoToast((r.data && r.data.message) ? r.data.message : 'Overview saved.', 'ok');
            } else {
                seoToast((r && r.data && r.data.message) ? r.data.message : 'Failed to save overview.', 'err');
            }
        }).fail(function(){
            $allBtns.prop('disabled', false).css('opacity', '1').each(function(i){
                jQuery(this).html(savedLabels[i]);
            });
            seoToast('Server error while saving overview.', 'err');
        });
    }
    
    // Wire up Direct Image Uploader
    function seoDashRemoveImage(previewId, inputId) {
        jQuery('#' + previewId).css('background-image', 'none');
        jQuery('#' + inputId).val('');
        jQuery('#' + previewId).find('.seo-screenshot-remove').hide();
        seoToast('Image removed. Remember to click Save Screenshots.', 'info');
    }
    
    function seoDashDirectUpload(inputEl, previewId, inputId) {
        if (!inputEl.files || !inputEl.files[0]) return;
        var file = inputEl.files[0];
        
        var formData = new FormData();
        formData.append('action', 'seo_dash_upload_screenshot');
        formData.append('nonce', seoDash.nonce);
        formData.append('image', file);
        
        var $btnLabel = jQuery(inputEl).closest('label');
        var originalText = $btnLabel.html();
        $btnLabel.html('⏳ Uploading...').css('opacity', '0.7').css('pointer-events', 'none');
        
        jQuery.ajax({
            url: seoDash.ajax,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $btnLabel.html(originalText).css('opacity', '1').css('pointer-events', 'auto');
                if (res.success && res.data && res.data.url) {
                    jQuery('#' + previewId).css('background-image', 'url(' + res.data.url + ')');
                    jQuery('#' + inputId).val(res.data.url);
                    jQuery('#' + previewId).find('.seo-screenshot-remove').show();
                    seoToast('Image uploaded! Click Save Screenshots.', 'ok');
                } else {
                    var errMsg = (res.data && res.data.message) ? res.data.message : 'Upload failed';
                    seoToast(errMsg, 'error');
                }
            },
            error: function() {
                $btnLabel.html(originalText).css('opacity', '1').css('pointer-events', 'auto');
                seoToast('Upload error occurred', 'error');
            }
        });
        
        // Reset file input so same file can be selected again if needed
        inputEl.value = '';
    }
    
    function seoDashSaveScreenshots(btn) {
        var sc_monthly = jQuery('#sc_monthly_input').val();
        var sc_overall = jQuery('#sc_overall_input').val();
        var ga_monthly = jQuery('#ga_monthly_input').val();
        var ga_overall = jQuery('#ga_overall_input').val();
        
        var $btn = jQuery(btn);
        $btn.text('Saving...').prop('disabled', true);
        
        jQuery.post(seoDash.ajax, {
            action: 'seo_dash_save_report_meta',
            nonce: seoDash.nonce,
            report_id: <?php echo $rid; ?>,
            sc_monthly: sc_monthly,
            sc_overall: sc_overall,
            ga_monthly: ga_monthly,
            ga_overall: ga_overall
        }, function(r) {
            $btn.text('💾 Save Screenshots').prop('disabled', false);
            if(r.success) {
                seoToast('Screenshots saved successfully.', 'ok');
            } else {
                seoToast('Failed to save screenshots.', 'err');
            }
        }).fail(function(){
            $btn.text('💾 Save Screenshots').prop('disabled', false);
            seoToast('Server error.', 'err');
        });
    }
    
    // Add Month Row
    function seoDashAddMonthRow() {
        var tbody = jQuery('#seo-overview-monthly-tbody');
        var idx = tbody.find('tr').length;
        if(tbody.find('#seo-overview-monthly-empty').length > 0) {
            tbody.empty();
            idx = 0;
        }
        var tr = jQuery('<tr>' +
            '<td><input type="month" class="seo-in seo-overview-monthly-input" data-idx="' + idx + '" data-field="month" style="width:180px;"></td>' +
            '<td><input type="number" class="seo-in seo-overview-monthly-input" data-idx="' + idx + '" data-field="traffic" style="width:180px;"></td>' +
            '<td style="text-align:center;"><button class="seo-icon-btn seo-icon-btn-d seo-overview-monthly-remove-btn" data-idx="' + idx + '" style="color:#ef4444;background:#fef2f2;border:1px solid #fecaca;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:700;">✕</button></td>' +
        '</tr>');
        tbody.append(tr);
        seoOverviewMarkDirty();
        seoToast('New month row added — remember to Save.', 'ok');
    }
    
    // Fetch GA data (Overview/Overall)
    function seoDashFetchOverallGA() {
        var start = jQuery('.seo-overview-input[data-field="overall_from"]').val();
        var end = jQuery('.seo-overview-input[data-field="overall_to"]').val();
        if(!start || !end) {
            seoToast("Please select From and To dates.", "error");
            return;
        }
        // Validate yyyy-MM-dd format (guards against corrupted stored values).
        var dateRe = /^\d{4}-\d{2}-\d{2}$/;
        if(!dateRe.test(start) || !dateRe.test(end)) {
            seoToast("Invalid date format. Please re-select the From and To dates.", "error");
            // Reset the fields so the user can pick valid dates.
            jQuery('.seo-overview-input[data-field="overall_from"]').val('');
            jQuery('.seo-overview-input[data-field="overall_to"]').val('');
            return;
        }
        seoToast('Fetching overall traffic from GA...', 'info');
        jQuery.ajax({
            url: seoDash.ajax,
            type: 'POST',
            dataType: 'text', // receive raw text so we always see what the server sent
            data: {
                action: 'seo_dash_ga_fetch_overall',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                date_from: start,
                date_to: end,
                source: 'overview'
            },
            success: function(rawText) {
                console.log('[GA Fetch Overall] Raw server response:', rawText);
                var res;
                try { res = JSON.parse(rawText); } catch(e) {
                    var preview = rawText.replace(/<[^>]+>/g,'').replace(/\s+/g,' ').trim().substring(0,400);
                    seoToast('GA Error: Server did not return valid JSON. Raw: ' + preview, 'error');
                    return;
                }
                if(res.success && res.data && res.data.users !== undefined) {
                    jQuery('#seo-overview-overall-traffic').val(res.data.users).trigger('change');
                    seoToast('Fetched successfully: ' + res.data.users + ' users', 'ok');
                } else {
                    var msg = seoGaErrMsg(res);
                    seoToast('GA Error: ' + msg, 'error');
                    console.error('[GA Fetch Overall] Error response:', res);
                }
            },
            error: function(xhr, status, err) {
                var raw = xhr.responseText ? xhr.responseText.replace(/<[^>]+>/g,'').replace(/\s+/g,' ').trim().substring(0,400) : '';
                seoToast('GA Fetch failed (' + status + '): ' + (raw || err), 'error');
                console.error('[GA Fetch Overall] HTTP error:', status, err, xhr.responseText);
            }
        });
    }

    // Fetch GA data (Monthly)
    function seoDashFetchMonthlyGA() {
        var monthVal = jQuery('#seo-overview-fetch-month').val(); // YYYY-MM
        if(!monthVal) {
            seoToast('Select a month first.', 'error');
            return;
        }
        // Validate YYYY-MM format — guards against corrupted stored values.
        if(!/^\d{4}-\d{2}$/.test(monthVal)) {
            seoToast('Invalid month format. Please re-select the month.', 'error');
            jQuery('#seo-overview-fetch-month').val('');
            return;
        }
        seoToast('Fetching traffic for ' + monthVal + ' from GA...', 'info');
        
        // Calculate start/end of that month
        var parts = monthVal.split('-');
        var year = parts[0];
        var month = parts[1];
        var startDate = year + '-' + month + '-01';
        var lastDay = new Date(year, month, 0).getDate();
        var endDate = year + '-' + month + '-' + lastDay;
        
        jQuery.ajax({
            url: seoDash.ajax,
            type: 'POST',
            dataType: 'text',
            data: {
                action: 'seo_dash_ga_fetch_overall',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                date_from: startDate,
                date_to: endDate,
                source: 'overview'
            },
            success: function(rawText) {
                console.log('[GA Fetch Monthly] Raw server response:', rawText);
                var res;
                try { res = JSON.parse(rawText); } catch(e) {
                    var preview = rawText.replace(/<[^>]+>/g,'').replace(/\s+/g,' ').trim().substring(0,400);
                    seoToast('GA Error: Server did not return valid JSON. Raw: ' + preview, 'error');
                    return;
                }
                if(res.success && res.data && res.data.users !== undefined) {
                    var tbody = jQuery('#seo-overview-monthly-tbody');
                    if(tbody.find('#seo-overview-monthly-empty').length > 0) { tbody.empty(); }
                    var exists = false;
                    tbody.find('tr').each(function(){
                        var mInput = jQuery(this).find('input[data-field="month"]');
                        if(mInput.val() === monthVal) {
                            jQuery(this).find('input[data-field="traffic"]').val(res.data.users).trigger('change');
                            exists = true;
                        }
                    });
                    if(!exists) {
                        var idx = tbody.find('tr').length;
                        var tr = jQuery('<tr>' +
                            '<td><input type="month" class="seo-in seo-overview-monthly-input" data-idx="' + idx + '" data-field="month" value="' + monthVal + '" style="width:180px;"></td>' +
                            '<td><input type="number" class="seo-in seo-overview-monthly-input" data-idx="' + idx + '" data-field="traffic" value="' + res.data.users + '" style="width:180px;"></td>' +
                            '<td style="text-align:center;"><button class="seo-icon-btn seo-icon-btn-d seo-overview-monthly-remove-btn" data-idx="' + idx + '" style="color:#ef4444;background:#fef2f2;border:1px solid #fecaca;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:700;">✕</button></td>' +
                        '</tr>');
                        tbody.append(tr);
                    }
                    seoOverviewMarkDirty();
                    seoToast('Fetched monthly traffic: ' + res.data.users + ' — remember to Save.', 'ok');
                } else {
                    var msg = seoGaErrMsg(res);
                    seoToast('GA Error: ' + msg, 'error');
                    console.error('[GA Fetch Monthly] Error response:', res);
                }
            },
            error: function(xhr, status, err) {
                var raw = xhr.responseText ? xhr.responseText.replace(/<[^>]+>/g,'').replace(/\s+/g,' ').trim().substring(0,400) : '';
                seoToast('GA Fetch failed (' + status + '): ' + (raw || err), 'error');
                console.error('[GA Fetch Monthly] HTTP error:', status, err, xhr.responseText);
            }
        });
    }
    </script>

</div><!-- /#ov-panel-data -->

<?php // ── CLIENT DASHBOARD ──────────────────────────────────────────────────
elseif ($tab === 'client_dashboard') :
?>
<!-- ════════════════════════════════════════════════════════════════════
     Front Tabs — controls what appears on the CLIENT Overview tab
     ════════════════════════════════════════════════════════════════════ -->
<?php
$report_meta_raw_ov = $report['meta'] ?? '{}';
$report_meta_ov     = is_array($report_meta_raw_ov) ? $report_meta_raw_ov : (json_decode($report_meta_raw_ov, true) ?: []);

$ov_section_defs = [
    'ov_kpis'        => ['icon'=>'🔢','label'=>'KPI Cards',        'desc'=>'The 5 stat cards at the top (Blog Posts, Mobile Speed, etc.)'],
    'ov_charts'      => ['icon'=>'📈','label'=>'Charts Row',       'desc'=>'Monthly Traffic, Pages Ranked & Backlinks charts'],
    'ov_table'       => ['icon'=>'📊','label'=>'Ranked Pages Table','desc'=>'The table listing pages and their ranking position'],
    'ov_screenshots' => ['icon'=>'📸','label'=>'Screenshots',      'desc'=>'Search Console & Analytics screenshot gallery'],
    'ov_summary'     => ['icon'=>'📍','label'=>'Report Summary',   'desc'=>'The written summary panel at the bottom'],
];

// KPI card settings
$ov_kpi_opt = get_option("seo_dash_overview_kpis_{$rid}", []);
$ov_kpi_defs = [
    'blog'    => ['icon'=>'📝','color'=>'#6366f1','label'=>'BLOG POSTS',    'desc'=>'Total published'],
    'mobile'  => ['icon'=>'📱','color'=>'#f59e0b','label'=>'MOBILE SPEED',  'desc'=>'PageSpeed score'],
    'desktop' => ['icon'=>'🖥️','color'=>'#06b6d4','label'=>'DESKTOP SPEED', 'desc'=>'PageSpeed score'],
    'ranked'  => ['icon'=>'📊','color'=>'#10b981','label'=>'RANKED PAGES',  'desc'=>'Pages indexed'],
    'leads'   => ['icon'=>'🎯','color'=>'#8b5cf6','label'=>'TOTAL LEADS',   'desc'=>'Enquiries received'],
];
$ov_kpi_merged = [];
foreach ($ov_kpi_defs as $kk => $kv) {
    $saved = is_array($ov_kpi_opt[$kk] ?? null) ? $ov_kpi_opt[$kk] : [];
    $ov_kpi_merged[$kk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $kv['label'],
        'desc'  => ($saved['desc']  ?? '') !== '' ? $saved['desc']  : $kv['desc'],
    ];
}

// Ranked Pages table column settings
$ov_tbl_opt = get_option("seo_dash_overview_table_cols_{$rid}", []);
$ov_tbl_defs = [
    'num'   => 'Row Number (#)',
    'title' => 'Page Title',
    'url'   => 'URL',
    'rank'  => 'Rank Badge',
];
$ov_tbl_merged = [];
foreach ($ov_tbl_defs as $tk => $tl) {
    $ov_tbl_merged[$tk] = isset($ov_tbl_opt[$tk]) ? (bool)$ov_tbl_opt[$tk] : true;
}

// Screenshots settings
$ov_ss_opt = get_option("seo_dash_overview_screenshots_{$rid}", []);
$ov_ss_meta = $report['meta'] ?? [];
$ov_ss_defs = [
    'sc_monthly' => ['icon'=>'📊','dot'=>'#6366f1','label'=>'Search Console — Monthly', 'url'=>$ov_ss_meta['sc_monthly'] ?? ''],
    'sc_overall' => ['icon'=>'🔍','dot'=>'#22d3ee','label'=>'Search Console — Overall', 'url'=>$ov_ss_meta['sc_overall'] ?? ''],
    'ga_monthly' => ['icon'=>'📈','dot'=>'#10b981','label'=>'Analytics — Monthly',      'url'=>$ov_ss_meta['ga_monthly'] ?? ''],
    'ga_overall' => ['icon'=>'📉','dot'=>'#eab308','label'=>'Analytics — Overall',      'url'=>$ov_ss_meta['ga_overall'] ?? ''],
];
$ov_ss_merged = [];
foreach ($ov_ss_defs as $sk => $sv) {
    $saved = is_array($ov_ss_opt[$sk] ?? null) ? $ov_ss_opt[$sk] : [];
    $ov_ss_merged[$sk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $sv['label'],
    ];
}

// ── Client Dashboard sub-tabs ───────────────────────────────────────────
// Each sub-tab maps a front-end client tab to its admin settings panel.
// To add a new sub-tab later (e.g. Analytics), add an entry here AND a
// matching <div class="cd-subpanel" id="cd-subpanel-{slug}"> block below.
$cd_subtabs = [
    'overview'  => ['📊','Overview'],
    'analytics' => ['📈','Analytics'],
    'sc'        => ['🔍','Search Console'],
    'service'   => ['🏆','Service Pages'],
    'blog'      => ['📝','Blog Posts'],
    'gmb'       => ['📍','Google Business'],
    'backlinks' => ['🔗','Backlinks'],
    'leads'     => ['🎯','Leads'],
    'technical' => ['⚙️','Technical'],
    'chatbot'   => ['🤖','Chatbot'],
    'account'   => ['👤','Account'],
];
$cd_first = array_key_first($cd_subtabs);
?>
<!-- Client Dashboard sub-tab bar -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--c-border);margin-bottom:22px;">
    <?php foreach ($cd_subtabs as $cd_slug => $cd_meta) : ?>
    <button type="button" class="cd-subtab" data-cd-target="<?php echo esc_attr($cd_slug); ?>"
            style="padding:10px 22px;font-size:13px;font-weight:700;background:none;border:none;
                   border-bottom:3px solid <?php echo $cd_slug===$cd_first?'var(--c-primary)':'transparent'; ?>;
                   margin-bottom:-2px;color:<?php echo $cd_slug===$cd_first?'var(--c-primary)':'var(--c-muted)'; ?>;
                   cursor:pointer;">
        <?php echo $cd_meta[0]; ?> <?php echo esc_html($cd_meta[1]); ?>
    </button>
    <?php endforeach; ?>
</div>

<?php
if (!function_exists('seo_dash_render_admin_tab_header_panel')) {
    function seo_dash_render_admin_tab_header_panel($tab, $tab_label, $default_title, $default_sub, $rid) {
        $hdr_opt = get_option("seo_dash_tab_hdr_{$tab}_{$rid}", []);
        $title = ($hdr_opt['title'] ?? '') !== '' ? $hdr_opt['title'] : '';
        $sub   = ($hdr_opt['sub']   ?? '') !== '' ? $hdr_opt['sub']   : '';
        ?>
        <div class="seo-panel" style="margin-top:20px;">
            <div class="seo-panel-hd">
                <h2 style="display:flex;align-items:center;gap:10px;">
                    <span>💬</span> <?php echo esc_html($tab_label); ?> Header Text
                    <span style="font-size:12px;font-weight:400;color:var(--c-muted);margin-left:4px;">Customize title and subtitle text shown at top of client dashboard</span>
                </h2>
                <button class="seo-btn seo-btn-primary seo-btn-sm seo-tab-hdr-save-btn" data-tab="<?php echo esc_attr($tab); ?>" data-rid="<?php echo intval($rid); ?>">💾 Save Header Text</button>
            </div>
            <div class="seo-panel-body" style="padding:20px 24px;">
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                    <div>
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Tab Header Title Text</div>
                        <input type="text" class="seo-in seo-tabhdr-title" data-tab="<?php echo esc_attr($tab); ?>" value="<?php echo esc_attr($title); ?>" placeholder="<?php echo esc_attr($default_title); ?>" style="width:100%;font-size:12px;">
                    </div>
                    <div>
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Tab Subtitle Text</div>
                        <input type="text" class="seo-in seo-tabhdr-sub" data-tab="<?php echo esc_attr($tab); ?>" value="<?php echo esc_attr($sub); ?>" placeholder="<?php echo esc_attr($default_sub); ?>" style="width:100%;font-size:12px;">
                    </div>
                </div>
                <div class="seo-tabhdr-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('seo_dash_render_admin_tab_kpis_panel')) {
    function seo_dash_render_admin_tab_kpis_panel($tab, $tab_label, $kpis_defs, $rid) {
        $saved_opt = get_option("seo_dash_kpis_{$tab}_{$rid}", get_option("seo_dash_{$tab}_kpis_{$rid}", []));
        ?>
        <div class="seo-panel" style="margin-top:20px;">
            <div class="seo-panel-hd">
                <h2 style="display:flex;align-items:center;gap:10px;">
                    <span>🔢</span> <?php echo esc_html($tab_label); ?> KPI Cards
                    <span style="font-size:12px;font-weight:400;color:var(--c-muted);margin-left:4px;">Show/hide each card, customize label &amp; description</span>
                </h2>
                <button class="seo-btn seo-btn-primary seo-btn-sm seo-tabkpi-save-btn" data-tab="<?php echo esc_attr($tab); ?>" data-rid="<?php echo intval($rid); ?>">💾 Save KPI Cards</button>
            </div>
            <div class="seo-panel-body" style="padding:20px 24px;">
                <p style="font-size:12px;color:var(--c-muted);margin:0 0 16px;">Hidden cards will not appear. Remaining cards auto-stretch to fit the row.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
                    <?php foreach ($kpis_defs as $kk => $kd) :
                        $saved = is_array($saved_opt[$kk] ?? null) ? $saved_opt[$kk] : [];
                        $is_show = isset($saved['show']) ? (bool)$saved['show'] : true;
                        $lbl     = ($saved['label'] ?? '') !== '' ? $saved['label'] : ($kd['label'] ?? $kd['default_label'] ?? strtoupper($kk));
                        $desc    = ($saved['desc']  ?? '') !== '' ? $saved['desc']  : ($kd['desc']  ?? $kd['default_desc']  ?? '');
                        $icon    = $kd['icon'] ?? '📊';
                    ?>
                    <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <span style="font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $icon; ?> <?php echo esc_html($kd['label'] ?? $kd['default_label'] ?? $kk); ?></span>
                            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-text);cursor:pointer;">
                                <input type="checkbox" class="seo-tabkpi-show" data-key="<?php echo esc_attr($kk); ?>" <?php checked($is_show); ?> style="accent-color:var(--c-primary);width:14px;height:14px;"> Show
                            </label>
                        </div>
                        <div style="margin-bottom:12px;">
                            <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Label</div>
                            <input type="text" class="seo-in seo-tabkpi-label" data-key="<?php echo esc_attr($kk); ?>" value="<?php echo esc_attr($lbl); ?>" style="width:100%;font-size:12px;">
                        </div>
                        <div>
                            <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Description / Subtext</div>
                            <input type="text" class="seo-in seo-tabkpi-desc" data-key="<?php echo esc_attr($kk); ?>" value="<?php echo esc_attr($desc); ?>" style="width:100%;font-size:12px;">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="seo-tabkpi-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('seo_dash_render_admin_tab_charts_panel')) {
    function seo_dash_render_admin_tab_charts_panel($tab, $tab_label, $charts_defs, $rid) {
        $saved_opt = get_option("seo_dash_charts_{$tab}_{$rid}", []);
        ?>
        <div class="seo-panel" style="margin-top:20px;">
            <div class="seo-panel-hd">
                <h2 style="display:flex;align-items:center;gap:12px;">
                    📊 <?php echo esc_html($tab_label); ?> Chart Settings
                    <span style="font-size:12px;font-weight:400;color:var(--c-muted);">Show/hide individual charts &amp; customize section title text</span>
                </h2>
                <button class="seo-btn seo-btn-primary seo-btn-sm seo-tabchart-save-btn" data-tab="<?php echo esc_attr($tab); ?>" data-rid="<?php echo intval($rid); ?>">💾 Save Chart Settings</button>
            </div>
            <div class="seo-panel-body" style="padding:20px 24px;">
                <p style="font-size:12px;color:var(--c-muted);margin:0 0 16px;">Hidden charts will not appear on client dashboard. Remaining charts auto-stretch to fit.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                    <?php foreach ($charts_defs as $ck => $cd) :
                        $saved   = is_array($saved_opt[$ck] ?? null) ? $saved_opt[$ck] : (is_string($saved_opt[$ck] ?? null) ? ['type' => $saved_opt[$ck]] : []);
                        $is_show = isset($saved['show']) ? (bool)$saved['show'] : true;
                        $title   = ($saved['title'] ?? '') !== '' ? $saved['title'] : ($cd['default_title'] ?? $cd['title'] ?? 'Chart');
                        $icon    = $cd['icon'] ?? '📈';
                    ?>
                    <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <span style="font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $icon; ?> <?php echo esc_html($cd['default_title'] ?? $title); ?></span>
                            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-text);cursor:pointer;">
                                <input type="checkbox" class="seo-tabchart-show" data-key="<?php echo esc_attr($ck); ?>" <?php checked($is_show); ?> style="accent-color:var(--c-primary);width:14px;height:14px;"> Show
                            </label>
                        </div>
                        <div>
                            <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Chart Section Title</div>
                            <input type="text" class="seo-in seo-tabchart-title" data-key="<?php echo esc_attr($ck); ?>" value="<?php echo esc_attr($title); ?>" style="width:100%;font-size:12px;">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="seo-tabchart-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
            </div>
        </div>
        <?php
    }
}
?>

<!-- ═══ Sub-panel: Overview ═══ -->
<div class="cd-subpanel" id="cd-subpanel-overview">
<div id="ov-panel-fronttabs">

    <!-- Hide Overview Tab entirely -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Overview Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ovtab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $ov_tab_vis = !(isset($report_meta_ov['show_overview']) && !$report_meta_ov['show_overview']); ?>
                <input type="checkbox" id="seo-ovtab-chk" <?php checked($ov_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Overview Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Overview tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-ovtab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 1. Section Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                🗂️ Overview Sections
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide this entire section from clients">
                    <?php $ovsec_vis = !(isset($report_meta_ov['show_ov_sections']) && !$report_meta_ov['show_ov_sections']); ?>
                    <input type="checkbox" class="seo-section-vis-chk" data-key="ov_sections" <?php checked($ovsec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ovsec-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Sections</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 20px;">Control which sections of the Overview tab clients can see. Unchecked sections will be hidden entirely.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                <?php foreach ($ov_section_defs as $skey => $sdef) :
                    $is_vis = !(isset($report_meta_ov['show_'.$skey]) && !$report_meta_ov['show_'.$skey]);
                ?>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-ovsec-chk" data-key="<?php echo esc_attr($skey); ?>" <?php checked($is_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?></span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;"><?php echo esc_html($sdef['desc']); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-ovsec-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 2. Hero Header & Top KPI Cards Settings -->
    <?php
    $hero_kpi_opt = get_option("seo_dash_hero_kpis_{$rid}", []);
    $hero_kpi_defs_admin = [
        'overall'   => ['icon' => '📈', 'default_label' => 'OVERALL TRAFFIC',      'default_desc' => 'All-time visitors'],
        'latest'    => ['icon' => '📊', 'default_label' => 'LATEST MONTH TRAFFIC', 'default_desc' => 'Most recent month'],
        'backlinks' => ['icon' => '🔗', 'default_label' => 'NEW BACKLINKS',         'default_desc' => 'All time'],
    ];
    $hero_kpi_merged = [];
    foreach ($hero_kpi_defs_admin as $hk => $hd) {
        $saved = is_array($hero_kpi_opt[$hk] ?? null) ? $hero_kpi_opt[$hk] : [];
        $hero_kpi_merged[$hk] = [
            'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
            'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $hd['default_label'],
            'desc'  => ($saved['desc'] ?? '') !== '' ? $saved['desc'] : $hd['default_desc'],
        ];
    }
    $hero_custom_title = $hero_kpi_opt['title'] ?? '';
    $hero_custom_sub   = $hero_kpi_opt['sub']   ?? '';
    ?>
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:10px;">
                <span>👑</span> Hero Header &amp; Top KPI Cards
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);margin-left:4px;">Customize top welcome header text &amp; top stat cards (Overall Traffic, Latest Month, New Backlinks)</span>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-hero-kpi-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Hero Header</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;margin-bottom:20px;">
                <div style="font-size:13px;font-weight:700;color:var(--c-text);margin-bottom:12px;">💬 Hero Welcome Header Text</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
                    <div>
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Header Title Text</div>
                        <input type="text" id="seo-hero-title-in" value="<?php echo esc_attr($hero_custom_title); ?>" placeholder="Your SEO Performance Dashboard" style="width:100%;font-size:12px;" class="seo-in">
                    </div>
                    <div>
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Header Subtitle Text</div>
                        <input type="text" id="seo-hero-sub-in" value="<?php echo esc_attr($hero_custom_sub); ?>" placeholder="Real-time insights across traffic, rankings, backlinks &amp; more" style="width:100%;font-size:12px;" class="seo-in">
                    </div>
                </div>
            </div>

            <div style="font-size:13px;font-weight:700;color:var(--c-text);margin-bottom:12px;">📊 Top Header KPI Cards</div>
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 16px;">Show or hide top header stat cards. If any card is hidden, remaining cards will auto-stretch to fill the space seamlessly.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
                <?php foreach ($hero_kpi_merged as $hk => $hv) : $hd = $hero_kpi_defs_admin[$hk]; ?>
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <span style="font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $hd['icon']; ?> <?php echo esc_html($hd['default_label']); ?></span>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-text);cursor:pointer;">
                            <input type="checkbox" class="seo-herokpi-show" data-key="<?php echo esc_attr($hk); ?>" <?php checked($hv['show']); ?> style="accent-color:var(--c-primary);width:14px;height:14px;"> Show
                        </label>
                    </div>
                    <div style="margin-bottom:12px;">
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Title / Label</div>
                        <input type="text" class="seo-in seo-herokpi-label" data-key="<?php echo esc_attr($hk); ?>" value="<?php echo esc_attr($hv['label']); ?>" style="width:100%;font-size:12px;">
                    </div>
                    <div>
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Subtitle / Description</div>
                        <input type="text" class="seo-in seo-herokpi-desc" data-key="<?php echo esc_attr($hk); ?>" value="<?php echo esc_attr($hv['desc']); ?>" style="width:100%;font-size:12px;">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="seo-herokpi-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 3. Main Body KPI Cards Settings -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:10px;">
                <span>🔢</span> Main Body KPI Cards
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);margin-left:4px;">Show/hide body cards (Blog Posts, Mobile Speed, Desktop Speed, Ranked Pages, Leads) &amp; customize labels</span>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide this entire section from clients">
                    <?php $ovkpi_sec_vis = !(isset($report_meta_ov['show_ov_kpi_section']) && !$report_meta_ov['show_ov_kpi_section']); ?>
                    <input type="checkbox" class="seo-section-vis-chk" data-key="ov_kpi_section" <?php checked($ovkpi_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ovkpi-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Main KPIs</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
                <?php foreach ($ov_kpi_merged as $kk => $kv) : $kdef = $ov_kpi_defs[$kk]; ?>
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <span style="font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $kdef['icon']; ?> <?php echo esc_html($kdef['label']); ?></span>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-text);cursor:pointer;">
                            <input type="checkbox" class="seo-ovkpi-show" data-key="<?php echo esc_attr($kk); ?>" <?php checked($kv['show']); ?> style="accent-color:var(--c-primary);width:14px;height:14px;"> Show
                        </label>
                    </div>
                    <div style="margin-bottom:12px;">
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Label</div>
                        <input type="text" class="seo-in seo-ovkpi-label" data-key="<?php echo esc_attr($kk); ?>" value="<?php echo esc_attr($kv['label']); ?>" style="width:100%;font-size:12px;">
                    </div>
                    <div>
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Description</div>
                        <input type="text" class="seo-in seo-ovkpi-desc" data-key="<?php echo esc_attr($kk); ?>" value="<?php echo esc_attr($kv['desc']); ?>" style="width:100%;font-size:12px;">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="seo-ovkpi-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 4. Overview Chart Settings -->
    <?php
    if (!function_exists('seo_dash_render_chart_type_options')) {
        function seo_dash_render_chart_type_options($current_val = '') {
            $options = [
                'bar'           => '📊 Bar Chart',
                'horizontalBar' => '↔️ Horizontal Bar',
                'groupedBar'    => '📶 Grouped Bar',
                'stackedBar'    => '🧱 Stacked Bar',
                'pie'           => '🥧 Pie Chart (Active Data)',
                'doughnut'      => '🍩 Doughnut Ring (Active Data)',
                'area'          => '🌊 Smooth Area Cloud',
                'line'          => '📈 Line Trend',
            ];
            foreach ($options as $val => $label) {
                echo '<option value="' . esc_attr($val) . '" ' . selected($current_val, $val, false) . '>' . esc_html($label) . '</option>';
            }
        }
    }
    $ov_charts_saved = get_option("seo_dash_charts_overview_{$rid}", []);
    $ov_chart_defs_admin = [
        'traffic'   => ['icon' => '📈', 'default_title' => 'Monthly Traffic Trend',   'default_type' => 'bar'],
        'ranked'    => ['icon' => '📊', 'default_title' => 'Pages Ranked — Summary', 'default_type' => 'bar'],
        'backlinks' => ['icon' => '🔗', 'default_title' => 'Backlinks by Month',     'default_type' => 'bar'],
    ];
    $ov_charts_merged = [];
    foreach ($ov_chart_defs_admin as $ck => $cd) {
        $saved = is_array($ov_charts_saved[$ck] ?? null) ? $ov_charts_saved[$ck] : [];
        $ov_charts_merged[$ck] = [
            'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
            'title' => ($saved['title'] ?? '') !== '' ? $saved['title'] : $cd['default_title'],
            'type'  => ($saved['type']  ?? '') !== '' ? $saved['type']  : $cd['default_type'],
        ];
    }
    ?>
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                📊 Overview Charts Settings
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);">Show/hide individual charts, customize title text &amp; select chart types</span>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ovcharts-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Overview Charts</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 16px;">Hidden charts will not appear on the client dashboard. Remaining charts auto-stretch to fit the row.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                <?php foreach ($ov_charts_merged as $ck => $cv) : $cd = $ov_chart_defs_admin[$ck]; ?>
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <span style="font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $cd['icon']; ?> <?php echo esc_html($cd['default_title']); ?></span>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-text);cursor:pointer;">
                            <input type="checkbox" class="seo-ovchart-show" data-key="<?php echo esc_attr($ck); ?>" <?php checked($cv['show']); ?> style="accent-color:var(--c-primary);width:14px;height:14px;"> Show
                        </label>
                    </div>
                    <div>
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--c-muted);margin-bottom:5px;">Chart Title Text</div>
                        <input type="text" class="seo-in seo-ovchart-title" data-key="<?php echo esc_attr($ck); ?>" value="<?php echo esc_attr($cv['title']); ?>" style="width:100%;font-size:12px;">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="seo-ovcharts-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 5. Ranked Pages Table Columns -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                📋 Ranked Pages — Table Columns
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide this entire section from clients">
                    <?php $ovtbl_sec_vis = !(isset($report_meta_ov['show_ov_table_section']) && !$report_meta_ov['show_ov_table_section']); ?>
                    <input type="checkbox" class="seo-section-vis-chk" data-key="ov_table_section" <?php checked($ovtbl_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ovtbl-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Columns</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 16px;">Choose which columns appear in the Ranked Pages table on the Overview tab.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                <?php foreach ($ov_tbl_defs as $tk => $tl) : ?>
                <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--c-text);transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-ovtbl-chk" data-key="<?php echo esc_attr($tk); ?>" <?php checked($ov_tbl_merged[$tk]); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                    <?php echo esc_html($tl); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-ovtbl-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 6. Screenshots -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                📸 Screenshots
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide this entire section from clients">
                    <?php $ovss_sec_vis = !(isset($report_meta_ov['show_ov_screenshots_section']) && !$report_meta_ov['show_ov_screenshots_section']); ?>
                    <input type="checkbox" class="seo-section-vis-chk" data-key="ov_screenshots_section" <?php checked($ovss_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ovss-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Screenshots</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 16px;">Show/hide each screenshot card and customize its label. Screenshots with no image uploaded (Overview tab → screenshot fields) won't appear regardless of these settings.</p>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ($ov_ss_merged as $sk => $sv) : $sdef = $ov_ss_defs[$sk]; ?>
                <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:var(--c-text);cursor:pointer;flex-shrink:0;">
                        <input type="checkbox" class="seo-ovss-show" data-key="<?php echo esc_attr($sk); ?>" <?php checked($sv['show']); ?> style="accent-color:var(--c-primary);width:14px;height:14px;"> Show
                    </label>
                    <span style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:var(--c-text);min-width:170px;">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr($sdef['dot']); ?>;display:inline-block;"></span>
                        <?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?>
                    </span>
                    <input type="text" class="seo-in seo-ovss-label" data-key="<?php echo esc_attr($sk); ?>" value="<?php echo esc_attr($sv['label']); ?>" placeholder="<?php echo esc_attr($sdef['label']); ?>" style="flex:1;min-width:200px;font-size:12px;">
                    <span style="font-size:11px;color:var(--c-muted);flex-shrink:0;"><?php echo $sdef['url'] ? '✅ Image set' : '— No image'; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="seo-ovss-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 7. Report Summary -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                📍 Report Summary
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide the Report Summary section from clients">
                    <?php $ovsum_sec_vis = !(isset($report_meta_ov['show_ov_summary_section']) && !$report_meta_ov['show_ov_summary_section']); ?>
                    <input type="checkbox" id="seo-ovsum-chk" <?php checked($ovsum_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ovsum-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Summary</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0;">The written summary panel shown at the bottom of the client&#39;s Overview tab. Uncheck above and save to hide it from clients.</p>
            <div id="seo-ovsum-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#ov-panel-fronttabs -->
</div><!-- /#cd-subpanel-overview -->

<!-- ═══ Sub-panel: Analytics ═══ -->
<?php
// ── Analytics sub-panel setup ───────────────────────────────────────────
// Section definitions for the Analytics tab (mirrors the client Analytics tab)
$an_section_defs = [
    'an_kpis'       => ['icon'=>'🔢','label'=>'KPI Cards',   'desc'=>'Active Users, Sessions, Page Views & Total URLs stat cards'],
    'an_chart'      => ['icon'=>'📈','label'=>'Traffic Chart','desc'=>'The traffic chart (type, metric & color set below)'],
    'an_table'      => ['icon'=>'📋','label'=>'All Pages Table','desc'=>'The full per-page analytics data table'],
    'an_pagedetail' => ['icon'=>'🔎','label'=>'Page Detail',  'desc'=>'The per-page drill-down search & stats section'],
];

// Analytics KPI card settings
$an_kpi_opt = get_option("seo_dash_analytics_kpis_{$rid}", []);
$an_kpi_defs_admin = [
    'users'    => ['icon'=>'🔄','color'=>'#6366f1','label'=>'ACTIVE USERS'],
    'sessions' => ['icon'=>'📄','color'=>'#10b981','label'=>'SESSIONS'],
    'views'    => ['icon'=>'📊','color'=>'#f59e0b','label'=>'PAGE VIEWS'],
    'urls'     => ['icon'=>'🔗','color'=>'#ec4899','label'=>'TOTAL URLS'],
];
$an_kpi_merged = [];
foreach ($an_kpi_defs_admin as $akk => $akv) {
    $saved = is_array($an_kpi_opt[$akk] ?? null) ? $an_kpi_opt[$akk] : [];
    $an_kpi_merged[$akk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $akv['label'],
    ];
}

?>
<div class="cd-subpanel" id="cd-subpanel-analytics" style="display:none;">

    <!-- 1. Hide Analytics Tab entirely -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Analytics Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-antab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $an_tab_vis = !(isset($report_meta_ov['show_analytics']) && !$report_meta_ov['show_analytics']); ?>
                <input type="checkbox" id="seo-antab-chk" <?php checked($an_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Analytics Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Analytics tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-antab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 2. Section Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                🗂️ Analytics Sections
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide every Analytics section from clients">
                    <?php $ansec_vis = !(isset($report_meta_ov['show_an_sections']) && !$report_meta_ov['show_an_sections']); ?>
                    <input type="checkbox" class="seo-an-section-vis-chk" data-key="an_sections" <?php checked($ansec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ansec-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Sections</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 20px;">Control which sections of the Analytics tab clients can see. Unchecked sections will be hidden entirely.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                <?php foreach ($an_section_defs as $skey => $sdef) :
                    $is_vis = !(isset($report_meta_ov['show_'.$skey]) && !$report_meta_ov['show_'.$skey]);
                ?>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-ansec-chk" data-key="<?php echo esc_attr($skey); ?>" <?php checked($is_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?></span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;"><?php echo esc_html($sdef['desc']); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-ansec-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 3. Tab Header Text -->
    <?php seo_dash_render_admin_tab_header_panel('analytics', 'Analytics', 'Google Analytics Performance', 'Track your website traffic and user engagement', $rid); ?>

    <!-- 4. KPI Cards Settings -->
    <?php seo_dash_render_admin_tab_kpis_panel('analytics', 'Analytics', $an_kpi_defs_admin, $rid); ?>

    <!-- 5. Analytics Chart Settings -->
    <?php seo_dash_render_admin_tab_charts_panel('analytics', 'Analytics', [
        'trend_type'  => ['icon' => '📈', 'default_title' => 'Traffic Trend',     'default_type' => 'area'],
        'device_type' => ['icon' => '🍩', 'default_title' => 'Device Breakdown', 'default_type' => 'doughnut'],
    ], $rid); ?>

    <!-- 6. Data Table & Page Detail -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:10px;">
                <span>📋</span> Data Table &amp; Page Detail
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);margin-left:4px;">Toggle the lower sections of the Analytics tab</span>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-antd-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;">
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;">
                    <?php $antbl_sec_vis = !(isset($report_meta_ov['show_an_table_section']) && !$report_meta_ov['show_an_table_section']); ?>
                    <input type="checkbox" class="seo-an-section-vis-chk" data-key="an_table_section" <?php checked($antbl_sec_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">📋 All Pages Table</span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;">The full per-page analytics data table with all time periods.</span>
                    </span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;">
                    <?php $anpd_sec_vis = !(isset($report_meta_ov['show_an_pagedetail_section']) && !$report_meta_ov['show_an_pagedetail_section']); ?>
                    <input type="checkbox" class="seo-an-section-vis-chk" data-key="an_pagedetail_section" <?php checked($anpd_sec_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">🔎 Page Detail</span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;">The per-page drill-down search box and stat cards.</span>
                    </span>
                </label>
            </div>
            <div id="seo-antd-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#cd-subpanel-analytics -->

<!-- ═══ Sub-panel: Search Console ═══ -->
<?php
// ── Search Console sub-panel setup ──────────────────────────────────────
$scn_section_defs = [
    'scn_kpis'       => ['icon'=>'🔢','label'=>'KPI Cards',     'desc'=>'Clicks, Impressions, Avg CTR & Total URLs stat cards'],
    'scn_chart'      => ['icon'=>'📊','label'=>'Performance Chart','desc'=>'The performance chart (type, metric & color set below)'],
    'scn_table'      => ['icon'=>'📋','label'=>'All Pages Table','desc'=>'The full per-page Search Console data table'],
    'scn_pagedetail' => ['icon'=>'🔎','label'=>'Page Detail',    'desc'=>'The per-page drill-down search & query stats section'],
];

// SC KPI card settings
$scn_kpi_opt = get_option("seo_dash_sc_kpis_{$rid}", []);
$scn_kpi_defs_admin = [
    'clicks'      => ['icon'=>'🖱️','color'=>'#6366f1','label'=>'CLICKS'],
    'impressions' => ['icon'=>'👁️','color'=>'#10b981','label'=>'IMPRESSIONS'],
    'ctr'         => ['icon'=>'📈','color'=>'#f59e0b','label'=>'AVG CTR'],
    'urls'        => ['icon'=>'🔗','color'=>'#ec4899','label'=>'TOTAL URLS'],
];
$scn_kpi_merged = [];
foreach ($scn_kpi_defs_admin as $skk => $skv) {
    $saved = is_array($scn_kpi_opt[$skk] ?? null) ? $scn_kpi_opt[$skk] : [];
    $scn_kpi_merged[$skk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $skv['label'],
    ];
}

?>
<div class="cd-subpanel" id="cd-subpanel-sc" style="display:none;">

    <!-- 1. Hide Search Console Tab entirely -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Search Console Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-scntab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $scn_tab_vis = !(isset($report_meta_ov['show_sc']) && !$report_meta_ov['show_sc']); ?>
                <input type="checkbox" id="seo-scntab-chk" <?php checked($scn_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Search Console Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Search Console tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-scntab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 2. Section Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                🗂️ Search Console Sections
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide every Search Console section from clients">
                    <?php $scnsec_vis = !(isset($report_meta_ov['show_scn_sections']) && !$report_meta_ov['show_scn_sections']); ?>
                    <input type="checkbox" class="seo-scn-section-vis-chk" data-key="scn_sections" <?php checked($scnsec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-scnsec-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Sections</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 20px;">Control which sections of the Search Console tab clients can see. Unchecked sections will be hidden entirely.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                <?php foreach ($scn_section_defs as $skey => $sdef) :
                    $is_vis = !(isset($report_meta_ov['show_'.$skey]) && !$report_meta_ov['show_'.$skey]);
                ?>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-scnsec-chk" data-key="<?php echo esc_attr($skey); ?>" <?php checked($is_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?></span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;"><?php echo esc_html($sdef['desc']); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-scnsec-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 3. Tab Header Text -->
    <?php seo_dash_render_admin_tab_header_panel('sc', 'Search Console', 'Google Search Performance', 'Monitor your Google Search performance and indexing', $rid); ?>

    <!-- 4. KPI Cards Settings -->
    <?php seo_dash_render_admin_tab_kpis_panel('sc', 'Search Console', [
        'clicks'      => ['icon' => '🖱️', 'default_label' => 'TOTAL CLICKS',      'default_desc' => 'Search results clicks'],
        'impressions' => ['icon' => '👁️', 'default_label' => 'TOTAL IMPRESSIONS', 'default_desc' => 'Search results views'],
        'ctr'         => ['icon' => '🎯', 'default_label' => 'AVERAGE CTR',       'default_desc' => 'Click-through rate'],
        'position'    => ['icon' => '📍', 'default_label' => 'AVERAGE POSITION',  'default_desc' => 'Average search rank'],
    ], $rid); ?>

    <!-- 5. Chart Settings -->
    <?php seo_dash_render_admin_tab_charts_panel('sc', 'Search Console', [
        'clicks'      => ['icon' => '🖱️', 'default_title' => 'Search Clicks Trend',      'default_type' => 'area'],
        'impressions' => ['icon' => '👁️', 'default_title' => 'Search Impressions Trend', 'default_type' => 'bar'],
    ], $rid); ?>

    <!-- 6. Data Table & Page Detail -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:10px;">
                <span>📋</span> Data Table &amp; Page Detail
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);margin-left:4px;">Toggle the lower sections of the Search Console tab</span>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-scntd-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;">
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;">
                    <?php $scntbl_sec_vis = !(isset($report_meta_ov['show_scn_table_section']) && !$report_meta_ov['show_scn_table_section']); ?>
                    <input type="checkbox" class="seo-scn-section-vis-chk" data-key="scn_table_section" <?php checked($scntbl_sec_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">📋 All Pages Table</span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;">The full per-page Search Console data table with all time periods.</span>
                    </span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;">
                    <?php $scnpd_sec_vis = !(isset($report_meta_ov['show_scn_pagedetail_section']) && !$report_meta_ov['show_scn_pagedetail_section']); ?>
                    <input type="checkbox" class="seo-scn-section-vis-chk" data-key="scn_pagedetail_section" <?php checked($scnpd_sec_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">🔎 Page Detail</span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;">The per-page drill-down search box and query stats.</span>
                    </span>
                </label>
            </div>
            <div id="seo-scntd-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#cd-subpanel-sc -->

<!-- ═══ Sub-panel: Service Pages ═══ -->
<?php
// ── Service Pages sub-panel setup ───────────────────────────────────────
$sp_section_defs = [
    'sp_kpis'       => ['icon'=>'🔢','label'=>'KPI Cards',     'desc'=>'Total Pages, Page 1/2/3+, AI Overview & Total Traffic stat cards'],
    'sp_chart'      => ['icon'=>'📊','label'=>'Traffic Chart', 'desc'=>'The traffic chart (type, metric & color set below)'],
    'sp_table'      => ['icon'=>'📋','label'=>'Service Pages Table','desc'=>'The full per-page service data table'],
    'sp_pagedetail' => ['icon'=>'🔎','label'=>'Page Details',  'desc'=>'The per-page drill-down side panel'],
];

// SP KPI card settings
$sp_kpi_opt = get_option("seo_dash_sp_kpis_{$rid}", []);
$sp_kpi_defs_admin = [
    'total'   => ['icon'=>'🏆','label'=>'TOTAL PAGES'],
    'p1'      => ['icon'=>'🥇','label'=>'PAGE 1'],
    'p2'      => ['icon'=>'🥈','label'=>'PAGE 2'],
    'p3plus'  => ['icon'=>'📊','label'=>'PAGE 3+'],
    'ai'      => ['icon'=>'🤖','label'=>'AI OVERVIEW'],
    'traffic' => ['icon'=>'📈','label'=>'TOTAL TRAFFIC'],
];
$sp_kpi_merged = [];
foreach ($sp_kpi_defs_admin as $pkk => $pkv) {
    $saved = is_array($sp_kpi_opt[$pkk] ?? null) ? $sp_kpi_opt[$pkk] : [];
    $sp_kpi_merged[$pkk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $pkv['label'],
    ];
}

?>
<div class="cd-subpanel" id="cd-subpanel-service" style="display:none;">

    <!-- 1. Hide Service Pages Tab entirely -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Service Pages Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-sptab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $sp_tab_vis = !(isset($report_meta_ov['show_service']) && !$report_meta_ov['show_service']); ?>
                <input type="checkbox" id="seo-sptab-chk" <?php checked($sp_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Service Pages Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Service Pages tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-sptab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 2. Section Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                🗂️ Service Pages Sections
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide every Service Pages section from clients">
                    <?php $spsec_vis = !(isset($report_meta_ov['show_sp_sections']) && !$report_meta_ov['show_sp_sections']); ?>
                    <input type="checkbox" class="seo-sp-section-vis-chk" data-key="sp_sections" <?php checked($spsec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-spsec-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Sections</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 20px;">Control which sections of the Service Pages tab clients can see. Unchecked sections will be hidden entirely.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                <?php foreach ($sp_section_defs as $skey => $sdef) :
                    $is_vis = !(isset($report_meta_ov['show_'.$skey]) && !$report_meta_ov['show_'.$skey]);
                ?>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-spsec-chk" data-key="<?php echo esc_attr($skey); ?>" <?php checked($is_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?></span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;"><?php echo esc_html($sdef['desc']); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-spsec-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 3. Tab Header Text -->
    <?php seo_dash_render_admin_tab_header_panel('service', 'Service Pages', 'Service Pages Performance', 'Performance metrics for your core service pages', $rid); ?>

    <!-- 4. KPI Cards Settings -->
    <?php seo_dash_render_admin_tab_kpis_panel('service', 'Service Pages', [
        'total'   => ['icon' => '🏆', 'default_label' => 'TOTAL SERVICE PAGES', 'default_desc' => 'Published pages'],
        'p1'      => ['icon' => '🥇', 'default_label' => 'PAGE 1 RANKED',      'default_desc' => 'Position 1–10'],
        'p2'      => ['icon' => '🥈', 'default_label' => 'PAGE 2 RANKED',      'default_desc' => 'Position 11–20'],
        'p3plus'  => ['icon' => '🥉', 'default_label' => 'PAGE 3+ RANKED',     'default_desc' => 'Position 21+'],
        'ai'      => ['icon' => '🤖', 'default_label' => 'AI OVERVIEW',        'default_desc' => 'AI citations'],
        'traffic' => ['icon' => '📈', 'default_label' => 'TOTAL TRAFFIC',      'default_desc' => 'All visitors'],
    ], $rid); ?>

    <!-- 5. Chart Settings -->
    <?php seo_dash_render_admin_tab_charts_panel('service', 'Service Pages', [
        'ranking' => ['icon' => '📊', 'default_title' => 'Rankings Distribution',           'default_type' => 'doughnut'],
        'traffic' => ['icon' => '📈', 'default_title' => 'Service Pages Traffic Trend',    'default_type' => 'bar'],
    ], $rid); ?>

    <!-- 6. Table & Page Details -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:10px;">
                <span>📋</span> Table &amp; Page Details
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);margin-left:4px;">Toggle the lower sections of the Service Pages tab</span>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-sptd-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;">
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;">
                    <?php $sptbl_sec_vis = !(isset($report_meta_ov['show_sp_table_section']) && !$report_meta_ov['show_sp_table_section']); ?>
                    <input type="checkbox" class="seo-sp-section-vis-chk" data-key="sp_table_section" <?php checked($sptbl_sec_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">📋 Service Pages Table</span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;">The full per-page service data table with all time periods.</span>
                    </span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;">
                    <?php $sppd_sec_vis = !(isset($report_meta_ov['show_sp_pagedetail_section']) && !$report_meta_ov['show_sp_pagedetail_section']); ?>
                    <input type="checkbox" class="seo-sp-section-vis-chk" data-key="sp_pagedetail_section" <?php checked($sppd_sec_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">🔎 Page Details</span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;">The per-page drill-down side panel with stats.</span>
                    </span>
                </label>
            </div>
            <div id="seo-sptd-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#cd-subpanel-service -->

<!-- ═══ Sub-panel: Blog Posts ═══ -->
<?php
// ── Blog Posts sub-panel setup ──────────────────────────────────────────
$bl_section_defs = [
    'bl_kpis'       => ['icon'=>'🔢','label'=>'KPI Cards',     'desc'=>'Total Blog Posts, Total Traffic & Categories stat cards'],
    'bl_chart'      => ['icon'=>'📊','label'=>'Traffic Chart', 'desc'=>'The traffic chart (type, metric & color set below)'],
    'bl_table'      => ['icon'=>'📋','label'=>'Blog Posts Table','desc'=>'The full per-post blog data table'],
    'bl_pagedetail' => ['icon'=>'🔎','label'=>'Page Details',  'desc'=>'The per-post drill-down side panel'],
];

// Blog KPI card settings
$bl_kpi_opt = get_option("seo_dash_blog_kpis_{$rid}", []);
$bl_kpi_defs_admin = [
    'posts'   => ['icon'=>'📝','label'=>'TOTAL BLOG POSTS'],
    'traffic' => ['icon'=>'📈','label'=>'TOTAL TRAFFIC'],
    'cats'    => ['icon'=>'🗂️','label'=>'CATEGORIES'],
];
$bl_kpi_merged = [];
foreach ($bl_kpi_defs_admin as $bkk => $bkv) {
    $saved = is_array($bl_kpi_opt[$bkk] ?? null) ? $bl_kpi_opt[$bkk] : [];
    $bl_kpi_merged[$bkk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $bkv['label'],
    ];
}

?>
<div class="cd-subpanel" id="cd-subpanel-blog" style="display:none;">

    <!-- 1. Hide Blog Posts Tab entirely -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Blog Posts Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-bltab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $bl_tab_vis = !(isset($report_meta_ov['show_blog']) && !$report_meta_ov['show_blog']); ?>
                <input type="checkbox" id="seo-bltab-chk" <?php checked($bl_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Blog Posts Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Blog Posts tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-bltab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 2. Section Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                🗂️ Blog Posts Sections
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide every Blog Posts section from clients">
                    <?php $blsec_vis = !(isset($report_meta_ov['show_bl_sections']) && !$report_meta_ov['show_bl_sections']); ?>
                    <input type="checkbox" class="seo-bl-section-vis-chk" data-key="bl_sections" <?php checked($blsec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-blsec-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Sections</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 20px;">Control which sections of the Blog Posts tab clients can see. Unchecked sections will be hidden entirely.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                <?php foreach ($bl_section_defs as $skey => $sdef) :
                    $is_vis = !(isset($report_meta_ov['show_'.$skey]) && !$report_meta_ov['show_'.$skey]);
                ?>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-blsec-chk" data-key="<?php echo esc_attr($skey); ?>" <?php checked($is_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?></span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;"><?php echo esc_html($sdef['desc']); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-blsec-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 3. Tab Header Text -->
    <?php seo_dash_render_admin_tab_header_panel('blog', 'Blog Posts', 'Blog Posts Performance', 'Traffic and engagement data for your blog content', $rid); ?>

    <!-- 4. KPI Cards Settings -->
    <?php seo_dash_render_admin_tab_kpis_panel('blog', 'Blog Posts', [
        'posts'   => ['icon' => '📝', 'default_label' => 'TOTAL BLOG POSTS', 'default_desc' => 'Published posts'],
        'traffic' => ['icon' => '📈', 'default_label' => 'TOTAL TRAFFIC',    'default_desc' => 'Total readers'],
        'cats'    => ['icon' => '🗂️', 'default_label' => 'CATEGORIES',       'default_desc' => 'Active categories'],
    ], $rid); ?>

    <!-- 5. Chart Settings -->
    <?php seo_dash_render_admin_tab_charts_panel('blog', 'Blog Posts', [
        'ranking' => ['icon' => '📊', 'default_title' => 'Rankings Distribution',     'default_type' => 'doughnut'],
        'traffic' => ['icon' => '📈', 'default_title' => 'Blog Traffic Trend',       'default_type' => 'bar'],
    ], $rid); ?>

    <!-- 6. Table & Page Details -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:10px;">
                <span>📋</span> Table &amp; Page Details
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);margin-left:4px;">Toggle the lower sections of the Blog Posts tab</span>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-bltd-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;">
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;">
                    <?php $bltbl_sec_vis = !(isset($report_meta_ov['show_bl_table_section']) && !$report_meta_ov['show_bl_table_section']); ?>
                    <input type="checkbox" class="seo-bl-section-vis-chk" data-key="bl_table_section" <?php checked($bltbl_sec_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">📋 Blog Posts Table</span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;">The full per-post blog data table with all time periods.</span>
                    </span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;">
                    <?php $blpd_sec_vis = !(isset($report_meta_ov['show_bl_pagedetail_section']) && !$report_meta_ov['show_bl_pagedetail_section']); ?>
                    <input type="checkbox" class="seo-bl-section-vis-chk" data-key="bl_pagedetail_section" <?php checked($blpd_sec_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">🔎 Page Details</span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;">The per-post drill-down side panel with stats.</span>
                    </span>
                </label>
            </div>
            <div id="seo-bltd-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#cd-subpanel-blog -->

<!-- ═══ Sub-panel: Google Business ═══ -->
<?php
// ── Google Business (GMB) sub-panel setup ───────────────────────────────
$gmb_section_defs = [
    'gmb_kpis'        => ['icon'=>'🔢','label'=>'KPI Cards',        'desc'=>'GMB Posts, Calls, Directions, Bookings, Website Clicks & Months Tracked'],
    'gmb_details'     => ['icon'=>'🏢','label'=>'Business Details',  'desc'=>'The business name, address, phone, category & description block'],
    'gmb_perf_chart'  => ['icon'=>'📊','label'=>'Monthly Performance Chart','desc'=>'The monthly performance chart (calls, directions, etc.)'],
    'gmb_perf_table'  => ['icon'=>'📋','label'=>'Monthly Performance Table','desc'=>'The monthly performance data table'],
    'gmb_posts_chart' => ['icon'=>'📝','label'=>'GMB Posts Chart',  'desc'=>'The posts-by-month / status breakdown chart'],
    'gmb_posts_table' => ['icon'=>'📋','label'=>'GMB Posts Table',  'desc'=>'The full GMB posts data table'],
];

// GMB KPI card settings
$gmb_kpi_opt = get_option("seo_dash_gmb_kpis_{$rid}", []);
$gmb_kpi_defs_admin = [
    'posts'      => ['icon'=>'📍','label'=>'GMB POSTS'],
    'calls'      => ['icon'=>'📞','label'=>'TOTAL CALLS'],
    'directions' => ['icon'=>'🗺️','label'=>'DIRECTIONS'],
    'bookings'   => ['icon'=>'🎟️','label'=>'BOOKINGS'],
    'website'    => ['icon'=>'🖱️','label'=>'WEBSITE CLICKS'],
    'months'     => ['icon'=>'🗓️','label'=>'MONTHS TRACKED'],
];
$gmb_kpi_merged = [];
foreach ($gmb_kpi_defs_admin as $gkk => $gkv) {
    $saved = is_array($gmb_kpi_opt[$gkk] ?? null) ? $gmb_kpi_opt[$gkk] : [];
    $gmb_kpi_merged[$gkk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $gkv['label'],
    ];
}

?>
<div class="cd-subpanel" id="cd-subpanel-gmb" style="display:none;">

    <!-- 1. Hide Google Business Tab entirely -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Google Business Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-gmbtab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $gmb_tab_vis = !(isset($report_meta_ov['show_gmb']) && !$report_meta_ov['show_gmb']); ?>
                <input type="checkbox" id="seo-gmbtab-chk" <?php checked($gmb_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Google Business Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Google Business tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-gmbtab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 2. Section Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                🗂️ Google Business Sections
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide every Google Business section from clients">
                    <?php $gmbsec_vis = !(isset($report_meta_ov['show_gmb_sections']) && !$report_meta_ov['show_gmb_sections']); ?>
                    <input type="checkbox" class="seo-gmb-section-vis-chk" data-key="gmb_sections" <?php checked($gmbsec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-gmbsec-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Sections</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 20px;">Control which sections of the Google Business tab clients can see. Unchecked sections will be hidden entirely.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                <?php foreach ($gmb_section_defs as $skey => $sdef) :
                    $is_vis = !(isset($report_meta_ov['show_'.$skey]) && !$report_meta_ov['show_'.$skey]);
                ?>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-gmbsec-chk" data-key="<?php echo esc_attr($skey); ?>" <?php checked($is_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?></span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;"><?php echo esc_html($sdef['desc']); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-gmbsec-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 3. Tab Header Text -->
    <?php seo_dash_render_admin_tab_header_panel('gmb', 'Google Business Profile', 'Google Business Profile Overview', 'Insights for your Google Business Profile', $rid); ?>

    <!-- 4. KPI Cards Settings -->
    <?php seo_dash_render_admin_tab_kpis_panel('gmb', 'Google Business Profile', [
        'views_search'   => ['icon' => '🔍', 'default_label' => 'SEARCH VIEWS',   'default_desc' => 'Views on Google Search'],
        'views_maps'     => ['icon' => '🗺️', 'default_label' => 'MAPS VIEWS',     'default_desc' => 'Views on Google Maps'],
        'clicks_website' => ['icon' => '🌐', 'default_label' => 'WEBSITE CLICKS', 'default_desc' => 'Visits to website'],
        'calls'          => ['icon' => '📞', 'default_label' => 'PHONE CALLS',    'default_desc' => 'Customer calls initiated'],
    ], $rid); ?>

    <!-- 5. Chart Settings -->
    <?php seo_dash_render_admin_tab_charts_panel('gmb', 'Google Business Profile', [
        'perf'  => ['icon' => '📊', 'default_title' => 'GMB Performance Trend', 'default_type' => 'bar'],
        'posts' => ['icon' => '📌', 'default_title' => 'GMB Posts Activity',     'default_type' => 'bar'],
    ], $rid); ?>

    <!-- 6. GMB Performance Table Column Visibility -->
    <?php
    $gmb_perf_front_opt = get_option("seo_dash_gmb_perf_front_{$rid}", [
        'cols' => ['row_num', 'month', 'calls', 'directions', 'bookings', 'clicks_website']
    ]);
    $gmb_perf_avail_cols = [
        'row_num'        => '# (Row Number)',
        'month'          => 'Month',
        'calls'          => 'Calls',
        'directions'     => 'Directions',
        'bookings'       => 'Bookings',
        'clicks_website' => 'Website Clicks',
    ];
    $gmb_perf_saved_cols = is_array($gmb_perf_front_opt['cols'] ?? null) ? $gmb_perf_front_opt['cols'] : ['row_num', 'month', 'calls', 'directions', 'bookings', 'clicks_website'];
    ?>
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                📋 Monthly Performance Table Columns
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);">Choose which columns appear in the client-facing GMB Monthly Performance table</span>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide the table from clients">
                    <?php $gmbperftbl_sec_vis = !(isset($report_meta_ov['show_gmb_perf_table']) && !$report_meta_ov['show_gmb_perf_table']); ?>
                    <input type="checkbox" class="seo-gmb-section-vis-chk" data-key="gmb_perf_table" <?php checked($gmbperftbl_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-gmbperftbl-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Columns</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                <?php foreach ($gmb_perf_avail_cols as $ck => $clbl) :
                    $is_on = in_array($ck, $gmb_perf_saved_cols);
                ?>
                <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--c-text);transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-gmbperftbl-col-chk" data-key="<?php echo esc_attr($ck); ?>" <?php checked($is_on); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                    <?php echo esc_html($clbl); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-gmbperftbl-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 7. GMB Posts Table Column Visibility -->
    <?php
    $gmb_front_opt = get_option("seo_dash_gmb_front_{$rid}", [
        'cols' => ['row_num', 'title', 'link', 'status', 'month']
    ]);
    $gmb_avail_cols = [
        'row_num' => '# (Row Number)',
        'title'   => 'Title',
        'link'    => 'Link',
        'status'  => 'Status',
        'month'   => 'Month',
    ];
    $gmb_saved_cols = is_array($gmb_front_opt['cols'] ?? null) ? $gmb_front_opt['cols'] : ['row_num', 'title', 'link', 'status', 'month'];
    ?>
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                📝 Posts Table Columns
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);">Choose which columns appear in the client-facing GMB Posts table</span>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide the table from clients">
                    <?php $gmbtbl_sec_vis = !(isset($report_meta_ov['show_gmb_posts_table']) && !$report_meta_ov['show_gmb_posts_table']); ?>
                    <input type="checkbox" class="seo-gmb-section-vis-chk" data-key="gmb_posts_table" <?php checked($gmbtbl_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-gmbtbl-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Columns</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                <?php foreach ($gmb_avail_cols as $ck => $clbl) :
                    $is_on = in_array($ck, $gmb_saved_cols);
                ?>
                <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--c-text);transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-gmbtbl-col-chk" data-key="<?php echo esc_attr($ck); ?>" <?php checked($is_on); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                    <?php echo esc_html($clbl); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-gmbtbl-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#cd-subpanel-gmb -->

<!-- ═══ Sub-panel: Backlinks ═══ -->
<?php
// ── Backlinks sub-panel setup ────────────────────────────────────────────
$bk_cd_section_defs = [
    'bk_kpis'   => ['icon'=>'🔢','label'=>'KPI Cards',       'desc'=>'Total Backlinks, Last Month, Link Types & Type Overview stat cards'],
    'bk_charts' => ['icon'=>'📊','label'=>'Charts Row',       'desc'=>'Type Distribution, Monthly Trend & Type×Month charts'],
    'bk_table'  => ['icon'=>'📋','label'=>'Backlinks Table',  'desc'=>'The full paginated backlinks data table'],
];

// KPI card settings
$bk_kpi_opt = get_option("seo_dash_bk_kpis_{$rid}", []);
$bk_kpi_defs_admin = [
    'total'      => ['icon'=>'🔗','label'=>'TOTAL BACKLINKS'],
    'last_month' => ['icon'=>'📅','label'=>'LAST MONTH'],
    'types'      => ['icon'=>'📊','label'=>'LINK TYPES'],
    'overview'   => ['icon'=>'📌','label'=>'TYPE OVERVIEW'],
];
$bk_kpi_merged = [];
foreach ($bk_kpi_defs_admin as $bkk => $bkv) {
    $saved = is_array($bk_kpi_opt[$bkk] ?? null) ? $bk_kpi_opt[$bkk] : [];
    $bk_kpi_merged[$bkk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $bkv['label'],
    ];
}

// Table column settings (mirror of existing bk_front option)
$bk_front_opt = get_option("seo_dash_bk_front_{$rid}", [
    'cols' => ['type','website','da','pa','spam','live_link','keyword','target_url','date','status']
]);
$bk_avail_cols = [
    'row_num'    => '# (Row Number)',
    'type'       => 'Type',
    'website'    => 'Website URL',
    'da'         => 'DA',
    'pa'         => 'PA',
    'spam'       => 'Spam %',
    'live_link'  => 'Live Link',
    'keyword'    => 'Target Keyword',
    'target_url' => 'Target URL',
    'date'       => 'Date',
    'month'      => 'Month',
    'year'       => 'Year',
    'status'     => 'Status',
];
$bk_saved_cols = is_array($bk_front_opt['cols'] ?? null) ? $bk_front_opt['cols'] : ['type','website','da','pa','spam','live_link','keyword','target_url','date','status'];
?>
<div class="cd-subpanel" id="cd-subpanel-backlinks" style="display:none;">

    <!-- 1. Backlinks Tab Visibility -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Backlinks Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-bktab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $bk_tab_vis = !(isset($report_meta_ov['show_backlinks']) && !$report_meta_ov['show_backlinks']); ?>
                <input type="checkbox" id="seo-bktab-chk" <?php checked($bk_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Backlinks Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Backlinks tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-bktab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 2. Section Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                🗂️ Backlinks Sections
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide every Backlinks section from clients">
                    <?php $bksec_vis = !(isset($report_meta_ov['show_bk_sections']) && !$report_meta_ov['show_bk_sections']); ?>
                    <input type="checkbox" class="seo-bk-section-vis-chk" data-key="bk_sections" <?php checked($bksec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-bksec-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Sections</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 20px;">Control which sections of the Backlinks tab clients can see. Unchecked sections will be hidden entirely.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                <?php foreach ($bk_cd_section_defs as $skey => $sdef) :
                    $is_vis = !(isset($report_meta_ov['show_'.$skey]) && !$report_meta_ov['show_'.$skey]);
                ?>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-bksec-chk" data-key="<?php echo esc_attr($skey); ?>" <?php checked($is_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?></span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;"><?php echo esc_html($sdef['desc']); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-bksec-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 3. Tab Header Text -->
    <?php seo_dash_render_admin_tab_header_panel('backlinks', 'Backlinks', 'Backlinks Overview', 'Monitor your inbound links and domain authority', $rid); ?>

    <!-- 4. KPI Cards Settings -->
    <?php seo_dash_render_admin_tab_kpis_panel('backlinks', 'Backlinks', [
        'total'      => ['icon' => '🔗', 'default_label' => 'TOTAL BACKLINKS', 'default_desc' => 'All time'],
        'last_month' => ['icon' => '📅', 'default_label' => 'LAST MONTH',       'default_desc' => 'New links'],
        'types'      => ['icon' => '📊', 'default_label' => 'LINK TYPES',       'default_desc' => 'Unique categories'],
        'overview'   => ['icon' => '📌', 'default_label' => 'TYPE OVERVIEW',    'default_desc' => 'Categories breakdown'],
    ], $rid); ?>

    <!-- 5. Chart Settings -->
    <?php seo_dash_render_admin_tab_charts_panel('backlinks', 'Backlinks', [
        'dist_type'  => ['icon' => '📊', 'default_title' => 'Type Distribution', 'default_type' => 'doughnut'],
        'trend_type' => ['icon' => '📈', 'default_title' => 'Monthly Trend',     'default_type' => 'bar'],
    ], $rid); ?>

    <!-- 6. Table Column Visibility -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                📋 Table Columns
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);">Choose which columns appear in the client-facing backlinks table</span>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide the table from clients">
                    <?php $bktbl_sec_vis = !(isset($report_meta_ov['show_bk_table_section']) && !$report_meta_ov['show_bk_table_section']); ?>
                    <input type="checkbox" class="seo-bk-section-vis-chk" data-key="bk_table_section" <?php checked($bktbl_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-bktbl-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Columns</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                <?php foreach ($bk_avail_cols as $ck => $clbl) :
                    $is_on = in_array($ck, $bk_saved_cols);
                ?>
                <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--c-text);transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-bktbl-col-chk" data-key="<?php echo esc_attr($ck); ?>" <?php checked($is_on); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                    <?php echo esc_html($clbl); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-bktbl-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#cd-subpanel-backlinks -->

<!-- ═══ Sub-panel: Leads ═══ -->
<?php
// ── Leads sub-panel setup ────────────────────────────────────────────────
$ld_cd_section_defs = [
    'ld_kpis'   => ['icon'=>'🔢','label'=>'KPI Cards',  'desc'=>'Total Leads, New, Contacted, Qualified, Converted & Lost stat cards'],
    'ld_charts' => ['icon'=>'📊','label'=>'Charts Row',  'desc'=>'Leads by Status donut & Status Breakdown bar chart'],
    'ld_table'  => ['icon'=>'📋','label'=>'Leads Table', 'desc'=>'The full paginated leads data table'],
];

// KPI card settings
$ld_kpi_opt = get_option("seo_dash_ld_kpis_{$rid}", []);
$ld_kpi_defs_admin = [
    'total'      => ['icon'=>'💼','label'=>'TOTAL LEADS'],
    'new'        => ['icon'=>'🔠','label'=>'NEW'],
    'contacted'  => ['icon'=>'📞','label'=>'CONTACTED'],
    'qualified'  => ['icon'=>'✅','label'=>'QUALIFIED'],
    'converted'  => ['icon'=>'🎉','label'=>'CONVERTED'],
    'lost'       => ['icon'=>'❌','label'=>'LOST'],
];
$ld_kpi_merged = [];
foreach ($ld_kpi_defs_admin as $lkk => $lkv) {
    $saved = is_array($ld_kpi_opt[$lkk] ?? null) ? $ld_kpi_opt[$lkk] : [];
    $ld_kpi_merged[$lkk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $lkv['label'],
    ];
}

// Table column settings
$ld_tbl_opt = get_option("seo_dash_ld_table_cols_{$rid}", []);
$ld_tbl_defs = [
    'num'      => 'Row Number (#)',
    'name'     => 'Name',
    'phone'    => 'Phone',
    'email'    => 'Email',
    'message'  => 'Message',
    'status'   => 'Status',
    'notes'    => 'Notes',
    'strength' => 'Lead Strength',
];
$ld_tbl_merged = [];
foreach ($ld_tbl_defs as $ltk => $ltl) {
    $ld_tbl_merged[$ltk] = isset($ld_tbl_opt[$ltk]) ? (bool)$ld_tbl_opt[$ltk] : true;
}
?>
<div class="cd-subpanel" id="cd-subpanel-leads" style="display:none;">

    <!-- 1. Leads Tab Visibility -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Leads Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ldtab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $ld_tab_vis = !(isset($report_meta_ov['show_leads']) && !$report_meta_ov['show_leads']); ?>
                <input type="checkbox" id="seo-ldtab-chk" <?php checked($ld_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Leads Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Leads tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-ldtab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 2. Section Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                🗂️ Leads Sections
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide every Leads section from clients">
                    <?php $ldsec_vis = !(isset($report_meta_ov['show_ld_sections']) && !$report_meta_ov['show_ld_sections']); ?>
                    <input type="checkbox" class="seo-ld-section-vis-chk" data-key="ld_sections" <?php checked($ldsec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ldsec-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Sections</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 20px;">Control which sections of the Leads tab clients can see. Unchecked sections will be hidden entirely.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                <?php foreach ($ld_cd_section_defs as $skey => $sdef) :
                    $is_vis = !(isset($report_meta_ov['show_'.$skey]) && !$report_meta_ov['show_'.$skey]);
                ?>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-ldsec-chk" data-key="<?php echo esc_attr($skey); ?>" <?php checked($is_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?></span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;"><?php echo esc_html($sdef['desc']); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-ldsec-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 3. Tab Header Text -->
    <?php seo_dash_render_admin_tab_header_panel('leads', 'Leads', 'Leads Management Overview', 'Track and manage your incoming leads', $rid); ?>

    <!-- 4. KPI Cards Settings -->
    <?php seo_dash_render_admin_tab_kpis_panel('leads', 'Leads', [
        'total'     => ['icon' => '💼', 'default_label' => 'TOTAL LEADS', 'default_desc' => 'All enquiries'],
        'new'       => ['icon' => '🔠', 'default_label' => 'NEW',         'default_desc' => 'Unprocessed'],
        'contacted' => ['icon' => '📞', 'default_label' => 'CONTACTED',   'default_desc' => 'Reached out'],
        'qualified' => ['icon' => '✅', 'default_label' => 'QUALIFIED',   'default_desc' => 'Sales qualified'],
        'converted' => ['icon' => '🎉', 'default_label' => 'CONVERTED',   'default_desc' => 'Won clients'],
        'lost'      => ['icon' => '❌', 'default_label' => 'LOST',        'default_desc' => 'Closed lost'],
    ], $rid); ?>

    <!-- 5. Chart Settings -->
    <?php seo_dash_render_admin_tab_charts_panel('leads', 'Leads', [
        'status'    => ['icon' => '🍩', 'default_title' => 'Leads by Status',   'default_type' => 'doughnut'],
        'breakdown' => ['icon' => '📊', 'default_title' => 'Status Breakdown',  'default_type' => 'bar'],
    ], $rid); ?>

    <!-- 6. Table Column Visibility -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                📋 Table Columns
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide this entire section from clients">
                    <?php $ldtbl_sec_vis = !(isset($report_meta_ov['show_ld_table_section']) && !$report_meta_ov['show_ld_table_section']); ?>
                    <input type="checkbox" class="seo-ld-section-vis-chk" data-key="ld_table_section" <?php checked($ldtbl_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-ldtbl-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Columns</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 16px;">Choose which columns appear in the Leads table on the client&rsquo;s dashboard.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                <?php foreach ($ld_tbl_defs as $ltk => $ltl) : ?>
                <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--c-text);transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-ldtbl-chk" data-key="<?php echo esc_attr($ltk); ?>" <?php checked($ld_tbl_merged[$ltk]); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                    <?php echo esc_html($ltl); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-ldtbl-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#cd-subpanel-leads -->

<!-- ═══ Sub-panel: Technical ═══ -->
<?php
// ── Technical sub-panel setup ───────────────────────────────────────────────
$tc_cd_section_defs = [
    'tc_kpis'   => ['icon'=>'🔢','label'=>'KPI Cards',        'desc'=>'Mobile Speed, Desktop Speed, Passed, Warnings, Health Score & Last Audit cards'],
    'tc_charts' => ['icon'=>'📊','label'=>'Charts Row',        'desc'=>'Status Overview donut, Item Scores, Performance Scores, Pass Rate, Category & Trend charts'],
    'tc_table'  => ['icon'=>'📋','label'=>'Issues Table',      'desc'=>'The full audit issues table with search and status filter'],
];

// KPI card settings
$tc_kpi_opt = get_option( "seo_dash_tc_kpis_{$rid}", [] );
$tc_kpi_defs_admin = [
    'mobile'      => ['icon'=>'📱','label'=>'MOBILE SPEED'],
    'desktop'     => ['icon'=>'💻','label'=>'DESKTOP SPEED'],
    'passed'      => ['icon'=>'✅','label'=>'PASSED'],
    'warnings'    => ['icon'=>'⚠️','label'=>'WARNINGS'],
    'health'      => ['icon'=>'🛡️','label'=>'HEALTH SCORE'],
    'last_audit'  => ['icon'=>'📅','label'=>'LAST AUDIT'],
];
$tc_kpi_merged = [];
foreach ( $tc_kpi_defs_admin as $tkk => $tkv ) {
    $saved = is_array( $tc_kpi_opt[$tkk] ?? null ) ? $tc_kpi_opt[$tkk] : [];
    $tc_kpi_merged[$tkk] = [
        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $tkv['label'],
    ];
}

// Issues table column settings
$tc_table_opt = get_option( "seo_dash_tc_table_{$rid}", [] );
$tc_avail_cols = [
    'type'        => 'Issue Type',
    'description' => 'Description',
    'status'      => 'Status',
    'severity'    => 'Severity',
    'url'         => 'URL',
];
$tc_saved_cols = is_array( $tc_table_opt['cols'] ?? null )
    ? $tc_table_opt['cols']
    : ['type','description','status','severity','url'];
?>
<div class="cd-subpanel" id="cd-subpanel-technical" style="display:none;">

    <!-- 1. Technical Tab Visibility -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Technical Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-tctab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $tc_tab_vis = !(isset($report_meta_ov['show_technical']) && !$report_meta_ov['show_technical']); ?>
                <input type="checkbox" id="seo-tctab-chk" <?php checked($tc_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Technical Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Technical Audit tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-tctab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 2. Section Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                🗂️ Technical Sections
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide every Technical section from clients">
                    <?php $tcsec_vis = !(isset($report_meta_ov['show_tc_sections']) && !$report_meta_ov['show_tc_sections']); ?>
                    <input type="checkbox" class="seo-tc-section-vis-chk" data-key="tc_sections" <?php checked($tcsec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-tcsec-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Sections</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 20px;">Control which sections of the Technical Audit tab clients can see. Unchecked sections will be hidden entirely.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                <?php foreach ($tc_cd_section_defs as $skey => $sdef) :
                    $is_vis = !(isset($report_meta_ov['show_'.$skey]) && !$report_meta_ov['show_'.$skey]);
                ?>
                <label style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-tcsec-chk" data-key="<?php echo esc_attr($skey); ?>" <?php checked($is_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;margin-top:2px;">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);"><?php echo $sdef['icon']; ?> <?php echo esc_html($sdef['label']); ?></span>
                        <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;line-height:1.4;"><?php echo esc_html($sdef['desc']); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-tcsec-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- 3. Tab Header Text -->
    <?php seo_dash_render_admin_tab_header_panel('technical', 'Technical SEO', 'Technical Audit Overview', 'Website health, errors, and technical issues', $rid); ?>

    <!-- 4. KPI Cards Settings -->
    <?php seo_dash_render_admin_tab_kpis_panel('technical', 'Technical SEO', [
        'mobile'     => ['icon' => '📱', 'default_label' => 'MOBILE SPEED',      'default_desc' => 'Mobile performance score'],
        'desktop'    => ['icon' => '💻', 'default_label' => 'DESKTOP SPEED',     'default_desc' => 'Desktop performance score'],
        'passed'     => ['icon' => '✅', 'default_label' => 'PASSED CHECKS',     'default_desc' => 'Healthy audit checks'],
        'warnings'   => ['icon' => '⚠️', 'default_label' => 'WARNINGS',          'default_desc' => 'Caution items'],
        'health'     => ['icon' => '🛡️', 'default_label' => 'SITE HEALTH SCORE', 'default_desc' => 'Overall site audit score'],
        'last_audit' => ['icon' => '📅', 'default_label' => 'LAST AUDIT',        'default_desc' => 'Audit execution date'],
    ], $rid); ?>

    <!-- 5. Chart Settings -->
    <?php seo_dash_render_admin_tab_charts_panel('technical', 'Technical SEO', [
        'status'   => ['icon' => '🍩', 'default_title' => 'Issue Status Breakdown',     'default_type' => 'doughnut'],
        'severity' => ['icon' => '📶', 'default_title' => 'Issue Severity Distribution', 'default_type' => 'bar'],
    ], $rid); ?>

    <!-- 6. Issues Table Column Visibility -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                📋 Issues Table Columns
                <span style="font-size:12px;font-weight:400;color:var(--c-muted);">Choose which columns appear in the client-facing audit issues table</span>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide the table from clients">
                    <?php $tctbl_sec_vis = !(isset($report_meta_ov['show_tc_table_section']) && !$report_meta_ov['show_tc_table_section']); ?>
                    <input type="checkbox" class="seo-tc-section-vis-chk" data-key="tc_table_section" <?php checked($tctbl_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-tctbl-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Columns</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                <?php foreach ($tc_avail_cols as $ck => $clbl) :
                    $is_on = in_array($ck, $tc_saved_cols);
                ?>
                <label style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--c-text);transition:background .15s;"
                       onmouseover="this.style.background='var(--c-surf3)'" onmouseout="this.style.background='var(--c-surf2)'">
                    <input type="checkbox" class="seo-tctbl-col-chk" data-key="<?php echo esc_attr($ck); ?>" <?php checked($is_on); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                    <?php echo esc_html($clbl); ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div id="seo-tctbl-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>



</div><!-- /#cd-subpanel-technical -->

<!-- ═══ Sub-panel: Chatbot ═══ -->
<?php
// ── Chatbot sub-panel setup ─────────────────────────────────────────────────
// Default built-in "Quick Questions" shown in the client-side AI Chatbot tab.
// Kept in sync with the fallback list in includes/views/client/dashboard.php
// so behavior is unchanged for any report that hasn't customized prompts yet.
$cbq_prompts_default = [
    ['label' => 'Traffic',   'qs' => [
        'How many sessions last 30 days?',
        'How many users visited overall?',
        'What is my traffic trend?',
    ]],
    ['label' => 'Search',    'qs' => [
        'Top 5 pages by clicks?',
        'Which page has the highest CTR?',
        'Which pages have lowest position?',
    ]],
    ['label' => 'Rankings',  'qs' => [
        'Which keywords rank on Page 1?',
        'Top service pages by traffic?',
        'Which blog posts get most traffic?',
    ]],
    ['label' => 'Backlinks', 'qs' => [
        'How many backlinks total?',
        'What types of backlinks do I have?',
        'Any lost or broken backlinks?',
    ]],
    ['label' => 'Leads',     'qs' => [
        'How many leads this month?',
        'What is my conversion rate?',
        'How many new leads are waiting?',
    ]],
    ['label' => 'GMB',       'qs' => [
        'How many calls from Google Business?',
        'How many direction requests?',
        'How many GMB posts published?',
    ]],
    ['label' => 'Technical', 'qs' => [
        'Any critical technical issues?',
        'Mobile and desktop speed scores?',
        'How is my site health score?',
    ]],
    ['label' => 'Strategy',  'qs' => [
        'What should I focus on for SEO?',
        'Give me a full SEO summary.',
    ]],
];
$cbq_prompts_saved = get_option( "seo_dash_ai_prompts_{$rid}", null );
$cbq_prompts = ( is_array( $cbq_prompts_saved ) && ! empty( $cbq_prompts_saved ) ) ? $cbq_prompts_saved : $cbq_prompts_default;
?>
<div class="cd-subpanel" id="cd-subpanel-chatbot" style="display:none;">

    <!-- Chatbot Tab Visibility -->
    <div class="seo-panel" style="margin-bottom:20px;">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Chatbot Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-cbqtab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $cbq_tab_vis = !(isset($report_meta_ov['show_ai']) && !$report_meta_ov['show_ai']); ?>
                <input type="checkbox" id="seo-cbqtab-chk" <?php checked($cbq_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Chatbot Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the AI Chatbot tab completely from the client&rsquo;s front-end dashboard.</span>
                </span>
            </label>
            <div id="seo-cbqtab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

    <!-- Built-in Prompts (Quick Questions) -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                💬 Built-in Prompts
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--c-muted);cursor:pointer;margin-left:4px;" title="Uncheck to hide the Quick Questions panel from clients">
                    <?php $cbq_sec_vis = !(isset($report_meta_ov['show_ai_prompts_section']) && !$report_meta_ov['show_ai_prompts_section']); ?>
                    <input type="checkbox" id="seo-cbq-section-vis-chk" <?php checked($cbq_sec_vis); ?> style="accent-color:var(--c-primary);width:15px;height:15px;cursor:pointer;"> Show Section
                </label>
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-cbqprompts-save-btn" data-rid="<?php echo $rid; ?>">💾 Save Prompts</button>
        </div>
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 18px;">Edit the categories and quick questions clients can click in the AI Chatbot&rsquo;s &ldquo;Quick Questions&rdquo; panel. Each category needs a name and at least one question.</p>
            <div id="seo-cbq-cats-wrap">
                <?php foreach ($cbq_prompts as $cbq_cat) :
                    $cbq_label = $cbq_cat['label'] ?? '';
                    $cbq_qs    = is_array($cbq_cat['qs'] ?? null) ? $cbq_cat['qs'] : [];
                ?>
                <div class="seo-cbq-cat" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:14px 16px;margin-bottom:14px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                        <input type="text" class="seo-in seo-cbq-cat-label" value="<?php echo esc_attr($cbq_label); ?>" placeholder="Category name" style="flex:1;font-size:13px;font-weight:700;">
                        <button type="button" class="seo-btn seo-btn-ghost seo-btn-sm seo-cbq-cat-remove" title="Remove category">🗑️</button>
                    </div>
                    <div class="seo-cbq-qs-wrap">
                        <?php foreach ($cbq_qs as $cbq_q) : ?>
                        <div class="seo-cbq-q-row" style="display:flex;gap:6px;margin-bottom:6px;">
                            <input type="text" class="seo-in seo-cbq-q-input" value="<?php echo esc_attr($cbq_q); ?>" placeholder="Type a question…" style="flex:1;font-size:12px;">
                            <button type="button" class="seo-btn seo-btn-ghost seo-cbq-q-remove" title="Remove question" style="padding:4px 8px;">✕</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="seo-btn seo-btn-ghost seo-btn-sm seo-cbq-q-add" style="margin-top:4px;">+ Add Question</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-cbq-cat-add-btn" style="margin-top:4px;">+ Add Category</button>
            <div id="seo-cbqprompts-status" style="margin-top:16px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#cd-subpanel-chatbot -->

<!-- ═══ Sub-panel: Account ═══ -->
<div class="cd-subpanel" id="cd-subpanel-account" style="display:none;">

    <!-- Account Tab Visibility -->
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2 style="display:flex;align-items:center;gap:12px;">
                👁️ Account Tab Visibility
            </h2>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-acctab-save-btn" data-rid="<?php echo $rid; ?>">💾 Save</button>
        </div>
        <div class="seo-panel-body" style="padding:16px 24px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <?php $acc_tab_vis = !(isset($report_meta_ov['show_account']) && !$report_meta_ov['show_account']); ?>
                <input type="checkbox" id="seo-acctab-chk" <?php checked($acc_tab_vis); ?> style="accent-color:var(--c-primary);width:16px;height:16px;cursor:pointer;">
                <span>
                    <span style="display:block;font-size:13px;font-weight:700;color:var(--c-text);">Show Account Tab</span>
                    <span style="display:block;font-size:11.5px;color:var(--c-muted);margin-top:2px;">Uncheck to hide the Account tab completely from the client&rsquo;s front-end dashboard. Everything else about this tab — name, email, password and avatar permissions — is controlled per-client on the <strong>Clients</strong> page.</span>
                </span>
            </label>
            <div id="seo-acctab-status" style="margin-top:12px;font-size:12px;color:var(--c-muted);"></div>
        </div>
    </div>

</div><!-- /#cd-subpanel-account -->




<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
seoJQ(function($){
    /* ── Client Dashboard sub-tab switcher (generic, scales to N tabs) ── */
    $(document).on('click', '.cd-subtab', function(){
        var target = $(this).data('cd-target');
        $('.cd-subtab').css({'border-bottom-color':'transparent','color':'var(--c-muted)'});
        $(this).css({'border-bottom-color':'var(--c-primary)','color':'var(--c-primary)'});
        $('.cd-subpanel').hide();
        $('#cd-subpanel-' + target).show();
    });

    /* ══ ANALYTICS sub-tab save handlers ══════════════════════════════ */

    /* Analytics chart color hex sync */

    /* Save: Analytics Tab Visibility */
    $(document).on('click', '#seo-antab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { analytics: $('#seo-antab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-antab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-antab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Analytics Section Visibility (section cards + every "Show Section" toggle) */
    $('#seo-ansec-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-ansec-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $('.seo-an-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Sections').prop('disabled', false);
            if (r.success) {
                $('#seo-ansec-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ansec-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Sections').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Analytics KPI Cards */
    $('#seo-ankpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-ankpi-show').each(function(){
            var k = $(this).data('key');
            kpis[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-ankpi-label[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_analytics_kpis', nonce:seoDash.nonce, report_id:rid, kpis:kpis }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $('#seo-ankpi-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ankpi-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save KPI Cards').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Analytics Data Table & Page Detail toggles */
    $('#seo-antd-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-an-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-antd-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-antd-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ══ SEARCH CONSOLE sub-tab save handlers ═════════════════════════ */

    /* SC chart color hex sync */

    /* Save: Search Console Tab Visibility */
    $(document).on('click', '#seo-scntab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { sc: $('#seo-scntab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-scntab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-scntab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Search Console Section Visibility */
    $('#seo-scnsec-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-scnsec-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $('.seo-scn-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Sections').prop('disabled', false);
            if (r.success) {
                $('#seo-scnsec-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-scnsec-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Sections').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Search Console KPI Cards */
    $('#seo-scnkpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-scnkpi-show').each(function(){
            var k = $(this).data('key');
            kpis[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-scnkpi-label[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_sc_kpis', nonce:seoDash.nonce, report_id:rid, kpis:kpis }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $('#seo-scnkpi-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-scnkpi-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save KPI Cards').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Search Console Data Table & Page Detail toggles */
    $('#seo-scntd-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-scn-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-scntd-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-scntd-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ══ SERVICE PAGES sub-tab save handlers ══════════════════════════ */

    /* SP chart color hex sync */

    /* Save: Service Pages Tab Visibility */
    $(document).on('click', '#seo-sptab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { service: $('#seo-sptab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-sptab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-sptab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Service Pages Section Visibility */
    $('#seo-spsec-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-spsec-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $('.seo-sp-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Sections').prop('disabled', false);
            if (r.success) {
                $('#seo-spsec-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-spsec-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Sections').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Service Pages KPI Cards */
    $('#seo-spkpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-spkpi-show').each(function(){
            var k = $(this).data('key');
            kpis[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-spkpi-label[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_sp_kpis', nonce:seoDash.nonce, report_id:rid, kpis:kpis }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $('#seo-spkpi-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-spkpi-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save KPI Cards').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Service Pages Table & Page Details toggles */
    $('#seo-sptd-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-sp-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-sptd-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-sptd-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ══ BLOG POSTS sub-tab save handlers ═════════════════════════════ */

    /* Blog chart color hex sync */

    /* Save: Blog Posts Tab Visibility */
    $(document).on('click', '#seo-bltab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { blog: $('#seo-bltab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-bltab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-bltab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Blog Posts Section Visibility */
    $('#seo-blsec-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-blsec-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $('.seo-bl-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Sections').prop('disabled', false);
            if (r.success) {
                $('#seo-blsec-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-blsec-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Sections').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Blog Posts KPI Cards */
    $('#seo-blkpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-blkpi-show').each(function(){
            var k = $(this).data('key');
            kpis[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-blkpi-label[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_blog_kpis', nonce:seoDash.nonce, report_id:rid, kpis:kpis }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $('#seo-blkpi-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-blkpi-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save KPI Cards').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Blog Posts Table & Page Details toggles */
    $('#seo-bltd-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-bl-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-bltd-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-bltd-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ══ GOOGLE BUSINESS sub-tab save handlers ════════════════════════ */

    /* Save: Google Business Tab Visibility */
    $(document).on('click', '#seo-gmbtab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { gmb: $('#seo-gmbtab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-gmbtab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-gmbtab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Google Business Section Visibility */
    $('#seo-gmbsec-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-gmbsec-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $('.seo-gmb-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Sections').prop('disabled', false);
            if (r.success) {
                $('#seo-gmbsec-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-gmbsec-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Sections').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Google Business KPI Cards */
    $('#seo-gmbkpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-gmbkpi-show').each(function(){
            var k = $(this).data('key');
            kpis[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-gmbkpi-label[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_gmb_kpis', nonce:seoDash.nonce, report_id:rid, kpis:kpis }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $('#seo-gmbkpi-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-gmbkpi-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save KPI Cards').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Google Business Monthly Performance Table Columns */
    $('#seo-gmbperftbl-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var cols = [];
        $('.seo-gmbperftbl-col-chk:checked').each(function(){ cols.push($(this).data('key')); });
        var tabs = {};
        $('.seo-gmb-section-vis-chk[data-key="gmb_perf_table"]').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_gmb_perf_front_settings', nonce:seoDash.nonce, report_id:rid, cols:cols, tabs:tabs }, function(r){
            $btn.text('💾 Save Columns').prop('disabled', false);
            if (r.success) {
                $('#seo-gmbperftbl-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-gmbperftbl-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Columns').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Google Business Posts Table Columns */
    $('#seo-gmbtbl-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var cols = [];
        $('.seo-gmbtbl-col-chk:checked').each(function(){ cols.push($(this).data('key')); });
        var tabs = {};
        $('.seo-gmb-section-vis-chk[data-key="gmb_posts_table"]').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_gmb_front_settings', nonce:seoDash.nonce, report_id:rid, cols:cols, tabs:tabs }, function(r){
            $btn.text('💾 Save Columns').prop('disabled', false);
            if (r.success) {
                $('#seo-gmbtbl-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-gmbtbl-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Columns').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* GMB chart color hex sync */

    /* ══ BACKLINKS sub-tab save handlers ══════════════════════════════ */

    /* Save: Backlinks Tab Visibility */
    $(document).on('click', '#seo-bktab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { backlinks: $('#seo-bktab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-bktab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-bktab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Backlinks Section Visibility */
    $('#seo-bksec-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-bksec-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $('.seo-bk-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Sections').prop('disabled', false);
            if (r.success) {
                $('#seo-bksec-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-bksec-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Sections').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Backlinks KPI Cards */
    $('#seo-bkkpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-bkkpi-show').each(function(){
            var k = $(this).data('key');
            kpis[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-bkkpi-label[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_bk_kpis', nonce:seoDash.nonce, report_id:rid, kpis:kpis }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $('#seo-bkkpi-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-bkkpi-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save KPI Cards').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Backlinks chart color hex sync */

    /* Save: Backlinks Table Columns */
    $('#seo-bktbl-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var cols = [];
        $('.seo-bktbl-col-chk:checked').each(function(){ cols.push($(this).data('key')); });
        var tabs = {};
        $('.seo-bk-section-vis-chk[data-key="bk_table_section"]').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_bk_front_settings', nonce:seoDash.nonce, report_id:rid, cols:cols, tabs:tabs }, function(r){
            $btn.text('💾 Save Columns').prop('disabled', false);
            if (r.success) {
                $('#seo-bktbl-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-bktbl-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Columns').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ══ LEADS sub-tab save handlers ═══════════════════════════════════ */

    /* Save: Leads Tab Visibility */
    $(document).on('click', '#seo-ldtab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { leads: $('#seo-ldtab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-ldtab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ldtab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Leads Section Visibility */
    $('#seo-ldsec-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-ldsec-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $('.seo-ld-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Sections').prop('disabled', false);
            if (r.success) {
                $('#seo-ldsec-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ldsec-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Sections').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Leads KPI Cards */
    $('#seo-ldkpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-ldkpi-show').each(function(){
            var k = $(this).data('key');
            kpis[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-ldkpi-label[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_ld_kpis', nonce:seoDash.nonce, report_id:rid, kpis:kpis }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $('#seo-ldkpi-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ldkpi-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save KPI Cards').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Leads chart color hex sync */

    /* Save: Leads Table Columns */
    $('#seo-ldtbl-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var cols = {};
        $('.seo-ldtbl-chk').each(function(){
            cols[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        var tabs = {};
        $('.seo-ld-section-vis-chk[data-key="ld_table_section"]').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_ld_table', nonce:seoDash.nonce, report_id:rid, cols:cols, tabs:tabs }, function(r){
            $btn.text('💾 Save Columns').prop('disabled', false);
            if (r.success) {
                $('#seo-ldtbl-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ldtbl-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Columns').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Leads Chart Types */
    $('#seo-ldcharts-save-btn').on('click', function(e){
        e.preventDefault();
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        $.post(seoDash.ajax, {
            action: 'seo_dash_save_ld_charts_settings',
            nonce: seoDash.nonce,
            report_id: rid,
            status_type: $('#seo-ld-status-chart-type-admin').val(),
            bar_type: $('#seo-ld-bar-chart-type-admin').val()
        }, function(r){
            $btn.text('💾 Save Chart Types').prop('disabled', false);
            if (r.success) {
                $('#seo-ldcharts-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ldcharts-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Chart Types').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: GMB Chart Types */
    $('#seo-gmbcharts-save-btn').on('click', function(e){
        e.preventDefault();
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        $.post(seoDash.ajax, {
            action: 'seo_dash_save_gmb_charts_settings',
            nonce: seoDash.nonce,
            report_id: rid,
            perf_type: $('#seo-gmb-perf-chart-type-admin').val(),
            posts_type: $('#seo-gmb-posts-chart-type-admin').val()
        }, function(r){
            $btn.text('💾 Save Chart Types').prop('disabled', false);
            if (r.success) {
                $('#seo-gmbcharts-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-gmbcharts-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Chart Types').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Hero Top Header KPI Cards */
    $('#seo-hero-kpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-herokpi-show').each(function(){
            var key = $(this).data('key');
            kpis[key] = {
                show: $(this).is(':checked') ? 1 : 0,
                label: $('.seo-herokpi-label[data-key="'+key+'"]').val(),
                desc: $('.seo-herokpi-desc[data-key="'+key+'"]').val()
            };
        });
        var title = $('#seo-hero-title-in').val();
        var sub   = $('#seo-hero-sub-in').val();
        $.post(seoDash.ajax, { action:'seo_dash_save_hero_kpis', nonce:seoDash.nonce, report_id:rid, title:title, sub:sub, kpis:kpis }, function(r){
            $btn.text('💾 Save Hero Header').prop('disabled', false);
            if (r.success) {
                $('#seo-herokpi-status').text('✅ Saved hero header settings!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-herokpi-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Hero Header').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Overview Chart Settings */
    $('#seo-ovcharts-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var charts = {};
        $('.seo-ovchart-show').each(function(){
            var key = $(this).data('key');
            charts[key] = {
                show: $(this).is(':checked') ? 1 : 0,
                title: $('.seo-ovchart-title[data-key="'+key+'"]').val(),
                type: $('.seo-ovchart-type[data-key="'+key+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_overview_charts', nonce:seoDash.nonce, report_id:rid, charts:charts }, function(r){
            $btn.text('💾 Save Overview Charts').prop('disabled', false);
            if (r.success) {
                $('#seo-ovcharts-status').text('✅ Saved overview chart settings!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ovcharts-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Overview Charts').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ── Save: Tab Header Text (Title & Subtitle) ────────────────────── */
    $(document).on('click', '.seo-tab-hdr-save-btn', function(e){
        e.preventDefault();
        var $btn = $(this);
        var rid = $btn.data('rid');
        var tab = $btn.data('tab');
        var $panel = $btn.closest('.seo-panel');
        var $status = $panel.find('.seo-tabhdr-status');
        var title = $panel.find('.seo-tabhdr-title').val();
        var sub   = $panel.find('.seo-tabhdr-sub').val();

        $btn.text('Saving…').prop('disabled', true);
        $.post(seoDash.ajax, {
            action: 'seo_dash_save_tab_header',
            nonce: seoDash.nonce,
            report_id: rid,
            tab: tab,
            title: title,
            sub: sub
        }, function(r){
            $btn.text('💾 Save Header Text').prop('disabled', false);
            if (r.success) {
                $status.text('✅ Saved header text successfully!').css('color', 'var(--c-green)');
                setTimeout(function(){ $status.text(''); }, 3000);
            } else {
                seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err');
            }
        }).fail(function(){
            $btn.text('💾 Save Header Text').prop('disabled', false);
            seoToast('Network error.', 'err');
        });
    });

    /* ── Save: Tab KPI Cards Settings ────────────────────────────────── */
    $(document).on('click', '.seo-tabkpi-save-btn', function(e){
        e.preventDefault();
        var $btn = $(this);
        var rid = $btn.data('rid');
        var tab = $btn.data('tab');
        var $panel = $btn.closest('.seo-panel');
        var $status = $panel.find('.seo-tabkpi-status');
        var kpis = {};
        $panel.find('.seo-tabkpi-show').each(function(){
            var key = $(this).data('key');
            kpis[key] = {
                show:  $(this).is(':checked') ? 1 : 0,
                label: $panel.find('.seo-tabkpi-label[data-key="'+key+'"]').val(),
                desc:  $panel.find('.seo-tabkpi-desc[data-key="'+key+'"]').val()
            };
        });
        $btn.text('Saving…').prop('disabled', true);
        $.post(seoDash.ajax, {
            action: 'seo_dash_save_tab_kpis',
            nonce: seoDash.nonce,
            report_id: rid,
            tab: tab,
            kpis: kpis
        }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $status.text('✅ Saved KPI cards successfully!').css('color', 'var(--c-green)');
                setTimeout(function(){ $status.text(''); }, 3000);
            } else {
                seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err');
            }
        }).fail(function(){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            seoToast('Network error.', 'err');
        });
    });

    /* ── Save: Tab Chart Settings (Show/Hide, Title, Display Type) ───── */
    $(document).on('click', '.seo-tabchart-save-btn', function(e){
        e.preventDefault();
        var $btn = $(this);
        var rid = $btn.data('rid');
        var tab = $btn.data('tab');
        var $panel = $btn.closest('.seo-panel');
        var $status = $panel.find('.seo-tabchart-status');
        var charts = {};
        $panel.find('.seo-tabchart-show').each(function(){
            var key = $(this).data('key');
            charts[key] = {
                show:  $(this).is(':checked') ? 1 : 0,
                title: $panel.find('.seo-tabchart-title[data-key="'+key+'"]').val(),
                type:  $panel.find('.seo-tabchart-type[data-key="'+key+'"]').val()
            };
        });
        $btn.text('Saving…').prop('disabled', true);
        $.post(seoDash.ajax, {
            action: 'seo_dash_save_tab_chart_settings',
            nonce: seoDash.nonce,
            report_id: rid,
            tab: tab,
            charts: charts
        }, function(r){
            $btn.text('💾 Save Chart Settings').prop('disabled', false);
            if (r.success) {
                $status.text('✅ Saved chart settings successfully!').css('color', 'var(--c-green)');
                setTimeout(function(){ $status.text(''); }, 3000);
            } else {
                seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err');
            }
        }).fail(function(){
            $btn.text('💾 Save Chart Settings').prop('disabled', false);
            seoToast('Network error.', 'err');
        });
    });

    /* ── Save: Overview Tab Visibility ──────────────────────────────── */
    $(document).on('click', '#seo-ovtab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { overview: $('#seo-ovtab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-ovtab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ovtab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ── Save: Report Summary visibility ────────────────────────────── */
    $('#seo-ovsum-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { ov_summary_section: $('#seo-ovsum-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Summary').prop('disabled', false);
            if (r.success) {
                $('#seo-ovsum-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ovsum-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Summary').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ── Save: Section Visibility ────────────────────────────────────── */
    $('#seo-ovsec-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-ovsec-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Sections').prop('disabled', false);
            if (r.success) {
                $('#seo-ovsec-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ovsec-status').text(''); }, 3000);
            } else {
                seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err');
            }
        }).fail(function(){ $btn.text('💾 Save Sections').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ── Save: KPI Cards ──────────────────────────────────────────────── */
    $('#seo-ovkpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-ovkpi-show').each(function(){
            var k = $(this).data('key');
            kpis[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-ovkpi-label[data-key="'+k+'"]').val(),
                desc:  $('.seo-ovkpi-desc[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_overview_kpis', nonce:seoDash.nonce, report_id:rid, kpis:kpis }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $('#seo-ovkpi-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ovkpi-status').text(''); }, 3000);
            } else {
                seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err');
            }
        }).fail(function(){ $btn.text('💾 Save KPI Cards').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ── Save: Ranked Pages Table Columns ────────────────────────────── */
    $('#seo-ovtbl-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var cols = {};
        $('.seo-ovtbl-chk').each(function(){
            cols[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_overview_table', nonce:seoDash.nonce, report_id:rid, cols:cols }, function(r){
            $btn.text('💾 Save Columns').prop('disabled', false);
            if (r.success) {
                $('#seo-ovtbl-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ovtbl-status').text(''); }, 3000);
            } else {
                seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err');
            }
        }).fail(function(){ $btn.text('💾 Save Columns').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ══ TECHNICAL sub-tab save handlers ══════════════════════════════ */

    /* Save: Technical Tab Visibility */
    $(document).on('click', '#seo-tctab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { technical: $('#seo-tctab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-tctab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-tctab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Technical Section Visibility */
    $('#seo-tcsec-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = {};
        $('.seo-tcsec-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $('.seo-tc-section-vis-chk').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save Sections').prop('disabled', false);
            if (r.success) {
                $('#seo-tcsec-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-tcsec-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Sections').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Save: Technical KPI Cards */
    $('#seo-tckpi-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var kpis = {};
        $('.seo-tckpi-show').each(function(){
            var k = $(this).data('key');
            kpis[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-tckpi-label[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_tc_kpis', nonce:seoDash.nonce, report_id:rid, kpis:kpis }, function(r){
            $btn.text('💾 Save KPI Cards').prop('disabled', false);
            if (r.success) {
                $('#seo-tckpi-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-tckpi-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save KPI Cards').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Technical chart color hex sync */

    /* Save: Technical Issues Table Columns */
    $('#seo-tctbl-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var cols = [];
        $('.seo-tctbl-col-chk:checked').each(function(){ cols.push($(this).data('key')); });
        var tabs = {};
        $('.seo-tc-section-vis-chk[data-key="tc_table_section"]').each(function(){
            tabs[$(this).data('key')] = $(this).is(':checked') ? '1' : '0';
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_tc_table', nonce:seoDash.nonce, report_id:rid, cols:cols, tabs:tabs }, function(r){
            $btn.text('💾 Save Columns').prop('disabled', false);
            if (r.success) {
                $('#seo-tctbl-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-tctbl-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Columns').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ── Save: Screenshots ────────────────────────────────────────────── */
    $('#seo-ovss-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var shots = {};
        $('.seo-ovss-show').each(function(){
            var k = $(this).data('key');
            shots[k] = {
                show:  $(this).is(':checked') ? '1' : '0',
                label: $('.seo-ovss-label[data-key="'+k+'"]').val()
            };
        });
        $.post(seoDash.ajax, { action:'seo_dash_save_overview_screenshots', nonce:seoDash.nonce, report_id:rid, shots:shots }, function(r){
            $btn.text('💾 Save Screenshots').prop('disabled', false);
            if (r.success) {
                $('#seo-ovss-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-ovss-status').text(''); }, 3000);
            } else {
                seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err');
            }
        }).fail(function(){ $btn.text('💾 Save Screenshots').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ══ CHATBOT sub-tab save handlers ══════════════════════════════ */

    /* Save: Chatbot Tab Visibility */
    $(document).on('click', '#seo-cbqtab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { ai: $('#seo-cbqtab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-cbqtab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-cbqtab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* Chatbot: Add Category */
    $(document).on('click', '#seo-cbq-cat-add-btn', function(){
        var html = ''
            + '<div class="seo-cbq-cat" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:14px 16px;margin-bottom:14px;">'
            +   '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">'
            +     '<input type="text" class="seo-in seo-cbq-cat-label" value="" placeholder="Category name" style="flex:1;font-size:13px;font-weight:700;">'
            +     '<button type="button" class="seo-btn seo-btn-ghost seo-btn-sm seo-cbq-cat-remove" title="Remove category">🗑️</button>'
            +   '</div>'
            +   '<div class="seo-cbq-qs-wrap"></div>'
            +   '<button type="button" class="seo-btn seo-btn-ghost seo-btn-sm seo-cbq-q-add" style="margin-top:4px;">+ Add Question</button>'
            + '</div>';
        $('#seo-cbq-cats-wrap').append(html);
    });

    /* Chatbot: Remove Category (always keep at least one) */
    $(document).on('click', '.seo-cbq-cat-remove', function(){
        if ($('.seo-cbq-cat').length <= 1) { seoToast('At least one category is required.', 'err'); return; }
        $(this).closest('.seo-cbq-cat').remove();
    });

    /* Chatbot: Add Question */
    $(document).on('click', '.seo-cbq-q-add', function(){
        var html = '<div class="seo-cbq-q-row" style="display:flex;gap:6px;margin-bottom:6px;">'
            + '<input type="text" class="seo-in seo-cbq-q-input" value="" placeholder="Type a question…" style="flex:1;font-size:12px;">'
            + '<button type="button" class="seo-btn seo-btn-ghost seo-cbq-q-remove" title="Remove question" style="padding:4px 8px;">✕</button>'
            + '</div>';
        $(this).closest('.seo-cbq-cat').find('.seo-cbq-qs-wrap').append(html);
    });

    /* Chatbot: Remove Question */
    $(document).on('click', '.seo-cbq-q-remove', function(){
        $(this).closest('.seo-cbq-q-row').remove();
    });

    /* Save: Chatbot Built-in Prompts */
    $('#seo-cbqprompts-save-btn').on('click', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var cats = [];
        $('.seo-cbq-cat').each(function(){
            var label = $(this).find('.seo-cbq-cat-label').val();
            var qs = [];
            $(this).find('.seo-cbq-q-input').each(function(){
                var v = $(this).val();
                if (v && v.trim() !== '') qs.push(v);
            });
            if (label && label.trim() !== '' && qs.length) {
                cats.push({ label: label, qs: qs });
            }
        });
        var tabs = { ai_prompts_section: $('#seo-cbq-section-vis-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_ai_prompts', nonce:seoDash.nonce, report_id:rid, prompts:cats, tabs:tabs }, function(r){
            $btn.text('💾 Save Prompts').prop('disabled', false);
            if (r.success) {
                $('#seo-cbqprompts-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-cbqprompts-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save Prompts').prop('disabled', false); seoToast('Network error.', 'err'); });
    });

    /* ══ ACCOUNT sub-tab save handler ══════════════════════════════ */

    /* Save: Account Tab Visibility */
    $(document).on('click', '#seo-acctab-save-btn', function(){
        var $btn = $(this), rid = $btn.data('rid');
        $btn.text('Saving…').prop('disabled', true);
        var tabs = { account: $('#seo-acctab-chk').is(':checked') ? '1' : '0' };
        $.post(seoDash.ajax, { action:'seo_dash_save_report_front_tabs', nonce:seoDash.nonce, report_id:rid, tabs:tabs }, function(r){
            $btn.text('💾 Save').prop('disabled', false);
            if (r.success) {
                $('#seo-acctab-status').text('✅ Saved!').css('color','var(--c-green)');
                setTimeout(function(){ $('#seo-acctab-status').text(''); }, 3000);
            } else { seoToast(r.data && r.data.message ? r.data.message : 'Save failed.', 'err'); }
        }).fail(function(){ $btn.text('💾 Save').prop('disabled', false); seoToast('Network error.', 'err'); });
    });
});
</script>

<?php // ── DATABASE ───────────────────────────────────────────────────────────
elseif ($tab === 'database') :
    // Get all months and data for GA
    $ga_months = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_ga, $rid);
    $ga_rows   = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_ga, $rid);
    
    // Get all months and data for SC
    $sc_months = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_sc, $rid);
    $sc_rows   = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_sc, $rid);
?>
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2>🗄️ Database</h2>
            <div style="display:flex;gap:8px;">
                <button class="seo-btn seo-btn-ghost seo-btn-sm" onclick="location.reload()">🔄 Refresh</button>
            </div>
        </div>

        <!-- Database Sub Tabs -->
        <div style="display:flex;flex-wrap:wrap;gap:0;border-bottom:2px solid var(--c-border);padding:0 20px;background:var(--c-surf2);margin-bottom:20px;">
            <button class="db-type-tab db-type-tab-active" data-dbtype="ga"
                    style="padding:10px 16px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;
                           border-bottom:3px solid var(--c-primary);margin-bottom:-2px;color:var(--c-primary);white-space:nowrap;">
                📈 Analytics (GA4) <span style="font-size:10px;opacity:.7;">(<?php echo count($ga_months); ?>)</span>
            </button>
            <button class="db-type-tab" data-dbtype="sc"
                    style="padding:10px 16px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;
                           border-bottom:3px solid transparent;margin-bottom:-2px;color:var(--c-muted);white-space:nowrap;">
                🔍 Search Console <span style="font-size:10px;opacity:.7;">(<?php echo count($sc_months); ?>)</span>
            </button>
            <button class="db-type-tab" data-dbtype="sitemap"
                    style="padding:10px 16px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;
                           border-bottom:3px solid transparent;margin-bottom:-2px;color:var(--c-muted);white-space:nowrap;">
                🗺️ Sitemap
            </button>
        </div>

        <!-- GA Database Panel -->
        <div class="db-type-panel" data-dbtype="ga" style="display:block;">
            <!-- Step 1: Select Month -->
            <div style="border:1px solid var(--c-border); border-radius:6px; margin:0 20px 20px; background:var(--c-surf); overflow:hidden;">
                <div style="padding:12px 20px; font-weight:600; color:var(--c-primary); border-bottom:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center;">
                    <span style="margin-right:8px;">🗓️</span> Step 1 — Select Year & Month to View
                </div>
                <div style="padding:20px; display:flex; gap:16px; align-items:flex-end; border-bottom:1px solid var(--c-border);">
                    <div class="seo-field" style="margin-bottom:0;">
                        <label style="font-size:11px;">Year</label>
                        <input type="number" id="db-ga-year" class="seo-in seo-in-sm" value="<?php echo date('Y'); ?>" style="width:100px;">
                    </div>
                    <div class="seo-field" style="margin-bottom:0;">
                        <label style="font-size:11px;">Month</label>
                        <select id="db-ga-month" class="seo-in seo-in-sm">
                            <?php for($m=1; $m<=12; $m++): $mv = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?php echo $mv; ?>" <?php selected(date('m'), $mv); ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button class="seo-btn seo-btn-primary db-open-month-btn" data-dbtype="ga" style="height:36px;padding:0 16px;">▶ Open Month</button>
                    <div style="margin-left:16px; color:var(--c-subtle); font-size:13px;">Working in: <span id="db-ga-working-msg" style="font-weight:600;">None</span></div>
                </div>
                <div style="padding:16px 20px; display:flex; align-items:center; gap:12px; border-top:1px solid var(--c-border);">
                    <div style="font-size:11px; font-weight:700; color:var(--c-primary); text-transform:uppercase; white-space:nowrap;">MONTHS WITH DATA:</div>
                    <?php if(!empty($ga_months)): ?>
                    <select id="db-ga-month-select" class="seo-in seo-in-sm" style="min-width:180px; height:32px;">
                        <option value="">— Select a month —</option>
                        <?php foreach ($ga_months as $m):
                            $m_label = date_i18n('F Y', strtotime($m.'-01'));
                        ?>
                        <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html($m_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="db-ga-month-select-open" class="seo-btn seo-btn-sm" style="background:var(--c-primary);color:#fff;border:none;height:32px;padding:0 14px;">Open</button>
                    <?php else: ?>
                    <span style="color:var(--c-subtle);font-size:13px;">No months found.</span>
                    <?php endif; ?>
                    <span style="font-size:11px; color:var(--c-subtle); margin-left:4px;">Select to set which month clients see on the frontend dashboard.</span>
                </div>
            </div>

            <!-- Active Frontend Alert -->
            <?php $active_ga_month = get_option("seo_dash_active_month_{$rid}_ga", 'None'); ?>
            <div style="margin:0 20px 20px; padding:12px 20px; background:#e8f5e9; border:1px solid #a5d6a7; border-radius:6px; display:flex; align-items:center; justify-content:space-between;">
                <div style="color:#2e7d32; font-weight:600; font-size:14px;">
                    ✅ Frontend showing: <span id="db-ga-active-frontend-msg"><?php echo esc_html($active_ga_month); ?></span>
                    <span style="font-weight:400; font-size:12px; margin-left:10px; color:#558b2f;">Clients see data from the active month's period columns</span>
                </div>
                <button class="seo-btn seo-btn-ghost seo-btn-sm" style="color:#d32f2f;" onclick="seoDashSetActiveMonth('ga', '')">✕ Clear Active</button>
            </div>

            <!-- Working Workspace -->
            <div id="db-ga-workspace-wrap" style="display:none; margin:0 20px; border:1px solid var(--c-border); border-radius:6px; background:var(--c-surf); overflow:hidden;">
                <div style="padding:16px 20px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--c-border); background:var(--c-surf2);">
                    <div style="font-size:16px; font-weight:700; color:var(--c-primary); display:flex; align-items:center; gap:10px;">
                        📅 <span id="db-ga-workspace-title"></span>
                        <span id="db-ga-workspace-active-badge" class="seo-badge" style="background:#2e7d32;color:#fff;display:none;">ACTIVE ON FRONT</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <label style="font-size:13px; font-weight:600; display:flex; align-items:center; gap:6px; margin:0; cursor:pointer;">
                            Show on Frontend 
                            <input type="checkbox" id="db-ga-frontend-toggle" style="margin:0;">
                        </label>
                        <button class="seo-btn seo-btn-primary seo-btn-sm seo-db-add-url-btn" data-dbtype="ga">＋ Add URL</button>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm db-trash-month-btn" data-dbtype="ga" style="color:#d32f2f; border:1px solid #ef9a9a;">🗑️ Trash Month</button>
                    </div>
                </div>
                
                <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <select class="seo-in seo-in-sm db-bulk-action-sel" data-dbtype="ga" style="width:auto;padding:4px 8px;font-size:12px;">
                            <option value="">Bulk Actions</option>
                            <option value="add_service">Add Selected to Service Pages</option>
                            <option value="add_blog">Add Selected to Blog Posts</option>
                            <option value="trash">Move Selected to Trash</option>
                            <option value="restore">Restore Selected from Trash</option>
                            <option value="delete">Delete Selected Permanently</option>
                            <option value="delete_all_subtype">🗑️ Delete All in Current Sub-tab Permanently</option>
                            <option value="delete_all">💥 Delete All Pages (All Sub-tabs) Permanently</option>
                        </select>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm db-bulk-action-btn" data-dbtype="ga">Apply</button>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="db-ga-workspace-content">⬇️ Export CSV</button>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="ga">⬇️ Download Format</button>
                        <!-- Fetch Granularity Mode Toggle -->
                        <div style="display:flex;flex-direction:column;align-items:flex-start;border-left:1px solid var(--c-border);padding-left:10px;">
                            <label style="font-size:10px;color:#0284c7;font-weight:700;margin-bottom:4px;line-height:1;">📊 Fetch Granularity Mode</label>
                            <select class="db-granularity-mode" data-dbtype="ga" style="font-size:12px;padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;height:32px;background:var(--c-surf);color:var(--c-text);font-weight:600;cursor:pointer;">
                                <option value="overall_months" selected>📅 Overall + Every Month</option>
                                <option value="overall_days">📆 Overall + Every Month + Each Day</option>
                                <option value="overall_only">⚡ Overall Summary Only</option>
                            </select>
                        </div>
                        <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">
                            ⬆️ Import CSV
                            <input type="file" class="seo-import-csv-input" data-type="ga" accept=".csv" style="display:none;">
                        </label>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm db-view-trash-btn" data-dbtype="ga">🗑️ View Trash</button>
                        <span style="display:inline-block;width:1px;height:20px;background:var(--c-border);margin:0 2px;"></span>
                        <div id="gsheet-sync-bar-ga" style="display:flex;align-items:center;gap:6px;">
                            <?php $link = $gsheet_links['ga'] ?? []; if ( !empty($link['spreadsheet_id']) ) : ?>
                            <span style="font-size:11px;color:var(--c-muted);white-space:nowrap;">📊 <?php echo esc_html( $link['spreadsheet_name'] ?? 'Linked Sheet' ); ?> / <?php echo esc_html( $link['tab_name'] ?? '' ); ?></span>
                            <button class="seo-btn seo-btn-sm gsheet-sync-btn" data-tabtype="ga" style="height:28px;font-size:11px;background:var(--c-primary);color:#fff;">⬆ Update</button>
                            <button class="seo-btn seo-btn-sm gsheet-export-btn" data-tabtype="ga" style="height:28px;font-size:11px;background:var(--c-surf);color:var(--c-primary);border:1px solid var(--c-primary);">⬇ Export to Sheet</button>
                            <span class="gsheet-sync-status" data-tabtype="ga" style="font-size:11px;color:var(--c-muted);"></span>
                            <?php else: ?>
                            <span style="font-size:11px;color:var(--c-muted);">⚠️ No sheet linked. Go to Integrations tab to link.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="padding:16px 20px; border-bottom:1px solid var(--c-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="font-size:13px; color:var(--c-muted);">Fetch fresh data from Google Analytics into this month snapshot.</div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                        <!-- Quick Preset Buttons -->
                        <div style="display:flex;gap:6px;align-items:center;">
                            <span style="font-size:10px;color:var(--c-subtle);font-weight:600;">Quick Fetch:</span>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm db-preset-fetch-btn" data-dbtype="ga" data-days="7" style="font-size:11px;padding:3px 10px;height:26px;border-radius:12px;">⚡ 7 Days</button>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm db-preset-fetch-btn" data-dbtype="ga" data-days="30" style="font-size:11px;padding:3px 10px;height:26px;border-radius:12px;">⚡ 30 Days</button>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm db-preset-fetch-btn" data-dbtype="ga" data-days="90" style="font-size:11px;padding:3px 10px;height:26px;border-radius:12px;">⚡ 90 Days</button>
                        </div>
                        <!-- Date Range + Main Buttons -->
                        <div style="display:flex;gap:12px; align-items:flex-end; flex-wrap:wrap;">
                            <div style="display:flex;flex-direction:column;align-items:flex-start;">
                                <label style="font-size:10px;color:var(--c-subtle);font-weight:600;margin-bottom:4px;line-height:1;">"Overall" Data From</label>
                                <input type="date" class="db-overall-date-from" data-dbtype="ga" value="<?php echo date('Y-m-d', strtotime('-365 days')); ?>" style="font-size:12px;padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;height:32px;">
                            </div>
                            <div style="display:flex;flex-direction:column;align-items:flex-start;">
                                <label style="font-size:10px;color:var(--c-subtle);font-weight:600;margin-bottom:4px;line-height:1;">To</label>
                                <input type="date" class="db-overall-date-to" data-dbtype="ga" value="<?php echo date('Y-m-d'); ?>" style="font-size:12px;padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;height:32px;">
                            </div>
                            <!-- Custom Range Selector -->
                            <div style="display:flex;flex-direction:column;align-items:flex-start;border-left:1px solid var(--c-border);padding-left:10px;">
                                <label style="font-size:10px;color:#4f46e5;font-weight:700;margin-bottom:4px;line-height:1;">📅 Custom Range Selector</label>
                                <div style="display:flex;gap:4px;align-items:center;">
                                    <input type="date" class="db-custom-date-from" data-dbtype="ga" value="<?php echo date('Y-m-d', strtotime('-60 days')); ?>" style="font-size:12px;padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;height:32px;">
                                    <span style="font-size:11px;color:var(--c-muted);">to</span>
                                    <input type="date" class="db-custom-date-to" data-dbtype="ga" value="<?php echo date('Y-m-d'); ?>" style="font-size:12px;padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;height:32px;">
                                    <button class="seo-btn seo-btn-sm db-fetch-custom-btn" data-dbtype="ga" style="background:#4f46e5;color:#fff;border-radius:4px;height:32px;font-size:11px;padding:0 10px;">⚡ Fetch Custom Range</button>
                                </div>
                            </div>
                            <button class="seo-btn seo-btn-ghost db-fetch-selected-btn" data-dbtype="ga" style="color:var(--c-primary);border:1px solid var(--c-primary);height:32px;">⬇️ Fetch Selected URLs</button>
                            <button class="seo-btn seo-btn-primary db-fetch-btn" data-dbtype="ga" style="height:32px;">⬇️ Fetch All Data into Month</button>
                        </div>
                    </div>
                </div>


                <!-- Workspace content injected via AJAX -->
                <div id="db-ga-workspace-content" style="min-height:200px;position:relative;"></div>
            </div>

        </div>

        <!-- SC Database Panel -->
        <div class="db-type-panel" data-dbtype="sc" style="display:none;">
            <!-- Step 1: Select Month -->
            <div style="border:1px solid var(--c-border); border-radius:6px; margin:0 20px 20px; background:var(--c-surf); overflow:hidden;">
                <div style="padding:12px 20px; font-weight:600; color:var(--c-primary); border-bottom:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center;">
                    <span style="margin-right:8px;">🗓️</span> Step 1 — Select Year & Month to View
                </div>
                <div style="padding:20px; display:flex; gap:16px; align-items:flex-end; border-bottom:1px solid var(--c-border);">
                    <div class="seo-field" style="margin-bottom:0;">
                        <label style="font-size:11px;">Year</label>
                        <input type="number" id="db-sc-year" class="seo-in seo-in-sm" value="<?php echo date('Y'); ?>" style="width:100px;">
                    </div>
                    <div class="seo-field" style="margin-bottom:0;">
                        <label style="font-size:11px;">Month</label>
                        <select id="db-sc-month" class="seo-in seo-in-sm">
                            <?php for($m=1; $m<=12; $m++): $mv = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?php echo $mv; ?>" <?php selected(date('m'), $mv); ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button class="seo-btn seo-btn-primary db-open-month-btn" data-dbtype="sc" style="height:36px;padding:0 16px;">▶ Open Month</button>
                    <div style="margin-left:16px; color:var(--c-subtle); font-size:13px;">Working in: <span id="db-sc-working-msg" style="font-weight:600;">None</span></div>
                </div>
                <div style="padding:16px 20px; display:flex; align-items:center; gap:12px; border-top:1px solid var(--c-border);">
                    <div style="font-size:11px; font-weight:700; color:var(--c-primary); text-transform:uppercase; white-space:nowrap;">MONTHS WITH DATA:</div>
                    <?php if(!empty($sc_months)): ?>
                    <select id="db-sc-month-select" class="seo-in seo-in-sm" style="min-width:180px; height:32px;">
                        <option value="">— Select a month —</option>
                        <?php foreach ($sc_months as $m):
                            $m_label = date_i18n('F Y', strtotime($m.'-01'));
                        ?>
                        <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html($m_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="db-sc-month-select-open" class="seo-btn seo-btn-sm" style="background:var(--c-primary);color:#fff;border:none;height:32px;padding:0 14px;">Open</button>
                    <?php else: ?>
                    <span style="color:var(--c-subtle);font-size:13px;">No months found.</span>
                    <?php endif; ?>
                    <span style="font-size:11px; color:var(--c-subtle); margin-left:4px;">Select to set which month clients see on the frontend dashboard.</span>
                </div>
            </div>

            <!-- Active Frontend Alert -->
            <?php $active_sc_month = get_option("seo_dash_active_month_{$rid}_sc", 'None'); ?>
            <div style="margin:0 20px 20px; padding:12px 20px; background:#e8f5e9; border:1px solid #a5d6a7; border-radius:6px; display:flex; align-items:center; justify-content:space-between;">
                <div style="color:#2e7d32; font-weight:600; font-size:14px;">
                    ✅ Frontend showing: <span id="db-sc-active-frontend-msg"><?php echo esc_html($active_sc_month); ?></span>
                </div>
                <button class="seo-btn seo-btn-ghost seo-btn-sm" style="color:#d32f2f;" onclick="seoDashSetActiveMonth('sc', '')">✕ Clear Active</button>
            </div>

            <!-- Working Workspace -->
            <div id="db-sc-workspace-wrap" style="display:none; margin:0 20px; border:1px solid var(--c-border); border-radius:6px; background:var(--c-surf); overflow:hidden;">
                <div style="padding:16px 20px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--c-border); background:var(--c-surf2);">
                    <div style="font-size:16px; font-weight:700; color:var(--c-primary); display:flex; align-items:center; gap:10px;">
                        📅 <span id="db-sc-workspace-title"></span>
                        <span id="db-sc-workspace-active-badge" class="seo-badge" style="background:#2e7d32;color:#fff;display:none;">ACTIVE ON FRONT</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <label style="font-size:13px; font-weight:600; display:flex; align-items:center; gap:6px; margin:0; cursor:pointer;">
                            Show on Frontend 
                            <input type="checkbox" id="db-sc-frontend-toggle" style="margin:0;">
                        </label>
                        <button class="seo-btn seo-btn-primary seo-btn-sm seo-db-add-url-btn" data-dbtype="sc">＋ Add URL</button>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm db-trash-month-btn" data-dbtype="sc" style="color:#d32f2f; border:1px solid #ef9a9a;">🗑️ Trash Month</button>
                    </div>
                </div>
                
                <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <select class="seo-in seo-in-sm db-bulk-action-sel" data-dbtype="sc" style="width:auto;padding:4px 8px;font-size:12px;">
                            <option value="">Bulk Actions</option>
                            <option value="add_service">Add Selected to Service Pages</option>
                            <option value="add_blog">Add Selected to Blog Posts</option>
                            <option value="trash">Move Selected to Trash</option>
                            <option value="restore">Restore Selected from Trash</option>
                            <option value="delete">Delete Selected Permanently</option>
                            <option value="delete_all_subtype">🗑️ Delete All in Current Sub-tab Permanently</option>
                            <option value="delete_all">💥 Delete All Pages (All Sub-tabs) Permanently</option>
                        </select>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm db-bulk-action-btn" data-dbtype="sc">Apply</button>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="db-sc-workspace-content">⬇️ Export CSV</button>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="sc">⬇️ Download Format</button>
                        <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">
                            ⬆️ Import CSV
                            <input type="file" class="seo-import-csv-input" data-type="sc" accept=".csv" style="display:none;">
                        </label>
                        <!-- Fetch Granularity Mode Toggle -->
                        <div style="display:flex;flex-direction:column;align-items:flex-start;border-left:1px solid var(--c-border);padding-left:10px;">
                            <label style="font-size:10px;color:#0284c7;font-weight:700;margin-bottom:4px;line-height:1;">📊 Fetch Granularity Mode</label>
                            <select class="db-granularity-mode" data-dbtype="sc" style="font-size:12px;padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;height:32px;background:var(--c-surf);color:var(--c-text);font-weight:600;cursor:pointer;">
                                <option value="overall_months" selected>📅 Overall + Every Month</option>
                                <option value="overall_days">📆 Overall + Every Month + Each Day</option>
                                <option value="overall_only">⚡ Overall Summary Only</option>
                            </select>
                        </div>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm db-view-trash-btn" data-dbtype="sc">🗑️ View Trash</button>
                    </div>
                </div>
                
                <div style="padding:16px 20px; border-bottom:1px solid var(--c-border); display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="font-size:13px; margin-left:12px; color:var(--c-muted);">Fetch fresh data from Search Console into this month snapshot.</div>
                    </div>
                    <div style="display:flex;gap:12px; align-items:flex-end; flex-wrap:wrap;">
                        <div style="display:flex;flex-direction:column;align-items:flex-start;">
                            <label style="font-size:10px;color:var(--c-subtle);font-weight:600;margin-bottom:4px;line-height:1;">"Overall" Data From</label>
                            <input type="date" class="db-overall-date-from" data-dbtype="sc" value="2020-01-01" style="font-size:12px;padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;height:32px;">
                        </div>
                        <!-- Custom Range Selector -->
                        <div style="display:flex;flex-direction:column;align-items:flex-start;border-left:1px solid var(--c-border);padding-left:10px;">
                            <label style="font-size:10px;color:#4f46e5;font-weight:700;margin-bottom:4px;line-height:1;">📅 Custom Range Selector</label>
                            <div style="display:flex;gap:4px;align-items:center;">
                                <input type="date" class="db-custom-date-from" data-dbtype="sc" value="<?php echo date('Y-m-d', strtotime('-60 days')); ?>" style="font-size:12px;padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;height:32px;">
                                <span style="font-size:11px;color:var(--c-muted);">to</span>
                                <input type="date" class="db-custom-date-to" data-dbtype="sc" value="<?php echo date('Y-m-d'); ?>" style="font-size:12px;padding:4px 8px;border:1px solid var(--c-border);border-radius:4px;height:32px;">
                                <button class="seo-btn seo-btn-sm db-fetch-custom-btn" data-dbtype="sc" style="background:#4f46e5;color:#fff;border-radius:4px;height:32px;font-size:11px;padding:0 10px;">⚡ Fetch Custom Range</button>
                            </div>
                        </div>
                        <button class="seo-btn seo-btn-ghost db-fetch-selected-btn" data-dbtype="sc" style="color:var(--c-primary);border:1px solid var(--c-primary);height:32px;">⬇️ Fetch Selected URLs</button>
                        <button class="seo-btn seo-btn-primary db-fetch-btn" data-dbtype="sc" style="height:32px;">⬇️ Fetch All Data into Month</button>
                    </div>
                </div>
                <!-- Workspace content injected via AJAX -->
                <div id="db-sc-workspace-content" style="min-height:200px;position:relative;"></div>
            </div>
        </div>
        <!-- Sitemap Database Panel -->
        <div class="db-type-panel" data-dbtype="sitemap" style="display:none;">
        <div class="seo-panel-body" style="padding:20px 24px;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 14px;">
                Paste any sitemap URL — regular or index file. Types are auto-detected from the filename
                (<code>post-sitemap.xml</code> → <strong>Post</strong>, <code>city-sitemap.xml</code> → <strong>City</strong>).
                After import, URLs are automatically pushed to the Analytics and Search Console tabs.
            </p>

            <!-- URL input & Rules button -->
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
                <input type="url" id="seo-sitemap-url" class="seo-in" autocomplete="off"
                       placeholder="https://example.com/sitemap.xml"
                       style="flex:1;min-width:260px;">
                <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-sitemap-import-btn" data-rid="<?php echo $rid; ?>">
                    🔍 Import Sitemap
                </button>
                <button type="button" class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-open-url-rules-btn">
                    🎯 URL Routing Rules
                </button>
            </div>

            <!-- Modal: URL Routing & Classification Rules -->
            <div id="seo-url-rules-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">
                <div style="background:var(--c-surf);border-radius:12px;border:1px solid var(--c-border);max-width:720px;width:90%;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
                    <div style="padding:16px 20px;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center;">
                        <h3 style="margin:0;font-size:16px;font-weight:700;color:var(--c-text);">🎯 Custom URL Routing &amp; Classification Rules</h3>
                        <button type="button" class="seo-icon-btn seo-close-url-rules-modal" style="font-size:18px;">✕</button>
                    </div>
                    <div style="padding:20px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:16px;">
                        <p style="font-size:12.5px;color:var(--c-muted);margin:0;">
                            Define pattern rules to automatically classify URLs into specific sub-tabs (City, Service, Post, Page, etc.) during sitemap imports and analytics fetching. Rules support wildcards (e.g. <code>*/locations/*</code>) or regex.
                        </p>

                        <!-- Rules Table -->
                        <div style="border:1px solid var(--c-border);border-radius:8px;overflow:hidden;">
                            <table class="seo-table" id="seo-url-rules-table" style="margin:0;width:100%;">
                                <thead style="background:var(--c-surf2);">
                                    <tr>
                                        <th style="padding:8px 12px;font-size:11px;">URL PATTERN / WILDCARD</th>
                                        <th style="padding:8px 12px;font-size:11px;width:160px;">TARGET SUB-TAB</th>
                                        <th style="padding:8px 12px;font-size:11px;width:50px;text-align:center;">DEL</th>
                                    </tr>
                                </thead>
                                <tbody id="seo-url-rules-tbody">
                                    <!-- Populated via JS -->
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <button type="button" class="seo-btn seo-btn-ghost seo-btn-xs" id="seo-add-url-rule-btn">➕ Add New Rule</button>
                        </div>

                        <!-- Live Rule Tester -->
                        <div style="border:1px solid var(--c-border);border-radius:8px;padding:14px;background:var(--c-surf2);">
                            <div style="font-size:12px;font-weight:700;color:var(--c-text);margin-bottom:8px;">🧪 Live Rule Tester</div>
                            <div style="display:flex;gap:8px;">
                                <input type="text" id="seo-test-url-input" class="seo-in" placeholder="Enter sample URL e.g. https://site.com/locations/dallas/" style="flex:1;font-size:12px;">
                                <button type="button" class="seo-btn seo-btn-primary seo-btn-sm" id="seo-run-test-url-btn">Test Rule</button>
                            </div>
                            <div id="seo-test-url-result" style="font-size:12px;margin-top:8px;min-height:18px;"></div>
                        </div>
                    </div>
                    <div style="padding:14px 20px;border-top:1px solid var(--c-border);display:flex;justify-content:flex-end;gap:10px;background:var(--c-surf2);">
                        <button type="button" class="seo-btn seo-btn-ghost seo-close-url-rules-modal">Cancel</button>
                        <button type="button" class="seo-btn seo-btn-primary" id="seo-save-url-rules-btn">💾 Save Routing Rules</button>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div id="seo-sitemap-status" style="font-size:12px;min-height:20px;margin-bottom:10px;"></div>

            <!-- Results (hidden until import succeeds) -->
            <div id="seo-sitemap-result" style="display:none;">

                <!-- Summary bar -->
                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;
                            padding:12px 16px;background:var(--c-surf2);border-radius:var(--r);border:1px solid var(--c-border);">
                    <span id="seo-sitemap-count" style="font-size:13px;font-weight:800;color:var(--c-text);white-space:nowrap;"></span>
                    <div id="seo-sitemap-type-badges" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
                </div>

                <!-- Action buttons (hidden, auto-push handles this) -->
                <div style="display:none;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-sitemap-add-ga-btn" data-rid="<?php echo $rid; ?>">
                        📈 Add All to Analytics
                    </button>
                    <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-sitemap-add-sc-btn" data-rid="<?php echo $rid; ?>">
                        🔍 Add All to Search Console
                    </button>
                </div>

                <!-- Send status -->
                <div id="seo-sitemap-send-status" style="font-size:12px;min-height:18px;margin-bottom:12px;"></div>

                <!-- Scrollable URL table -->
                <div style="max-height:460px;overflow-y:auto;border:1px solid var(--c-border);border-radius:var(--r);">
                    <table class="seo-table" id="seo-sitemap-tbl" style="margin:0;">
                        <thead style="position:sticky;top:0;z-index:2;background:var(--c-surf2);">
                            <tr>
                                <th style="padding:9px 10px;width:44px;">#</th>
                                <th style="padding:9px 10px;width:120px;">Type</th>
                                <th style="padding:9px 10px;">URL</th>
                            </tr>
                        </thead>
                        <tbody id="seo-sitemap-tbody">
                            <tr><td colspan="3" style="text-align:center;padding:28px;color:var(--c-subtle);">Import a sitemap above to see URLs here.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div><!-- /.db-type-panel sitemap -->

    </div>
    
    <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
    var currentDbMonth = { ga: null, sc: null };
    
    function seoDashSetActiveMonth(dbType, month) {
        jQuery.post(seoDash.ajax, {
            action: 'seo_dash_set_active_month',
            nonce: seoDash.nonce,
            report_id: <?php echo $rid; ?>,
            db_type: dbType,
            target_month: month
        }, function(r){
            if (r.success) {
                jQuery('#db-' + dbType + '-active-frontend-msg').text(month || 'None');
                if (currentDbMonth[dbType] === month && month !== '') {
                    jQuery('#db-' + dbType + '-workspace-active-badge').show();
                    jQuery('#db-' + dbType + '-frontend-toggle').prop('checked', true);
                } else {
                    jQuery('#db-' + dbType + '-workspace-active-badge').hide();
                    jQuery('#db-' + dbType + '-frontend-toggle').prop('checked', false);
                }
            }
        });
    }

    function seoDashInitWorkspacePagination(dbType) {
        var $workspace = jQuery('#db-' + dbType + '-workspace-content');
        $workspace.find('.db-type-subpane').each(function() {
            var $pane = jQuery(this);
            var subtype = $pane.data('type');
            var $table = $pane.find('table.seo-table');
            var $tbody = $table.find('tbody');

            var perPage = 50;
            var curPage = 1;

            $pane.find('.db-workspace-pagination-bar').remove();

            var $pagBar = jQuery(
                '<div class="db-workspace-pagination-bar" style="display:flex;justify-content:space-between;align-items:center;padding:10px 20px;background:var(--c-surf2);border:1px solid var(--c-border);border-top:none;border-radius:0 0 4px 4px;font-size:12px;margin:0 20px 20px;">' +
                    '<div style="display:flex;align-items:center;gap:12px;color:var(--c-muted);">' +
                        '<span class="db-pag-info"></span>' +
                        '<label style="display:flex;align-items:center;gap:4px;margin:0;font-size:11px;">' +
                            'Rows per page: ' +
                            '<select class="seo-in db-pag-size" style="padding:2px 6px;font-size:11px;height:26px;width:auto;">' +
                                '<option value="50" selected>50</option>' +
                                '<option value="100">100</option>' +
                                '<option value="250">250</option>' +
                                '<option value="500">500</option>' +
                                '<option value="all">All</option>' +
                            '</select>' +
                        '</label>' +
                    '</div>' +
                    '<div class="db-pag-controls" style="display:flex;align-items:center;gap:4px;"></div>' +
                '</div>'
            );
            $pane.append($pagBar);

            function updatePage(page) {
                var isTrashView = $pane.closest('.seo-panel').find('.db-view-trash-btn[data-dbtype="'+dbType+'"]').hasClass('viewing-trash') ? 1 : 0;
                var searchVal = jQuery.trim($workspace.find('.db-table-search').val() || '');
                var targetMonth = currentDbMonth[dbType] || jQuery('#db-' + dbType + '-working-msg').text();

                $pagBar.find('.db-pag-info').html('<span style="color:var(--c-muted);">Loading...</span>');

                jQuery.post(seoDash.ajax, {
                    action: 'seo_dash_get_workspace_page',
                    nonce: seoDash.nonce,
                    report_id: <?php echo $rid; ?>,
                    db_type: dbType,
                    target_month: targetMonth,
                    subtype: subtype,
                    page: page,
                    per_page: perPage,
                    search: searchVal,
                    trash_view: isTrashView
                }, function(r){
                    if (r.success) {
                        $tbody.html(r.data.html);
                        var total = r.data.total || 0;
                        var totalPages = r.data.total_pages || 1;
                        curPage = r.data.page || 1;

                        if (total === 0) {
                            $pagBar.find('.db-pag-info').text('0 items found');
                            $pagBar.find('.db-pag-controls').empty();
                            return;
                        }

                        $pagBar.find('.db-pag-info').html('Showing <b>' + r.data.start + '–' + r.data.end + '</b> of <b>' + total.toLocaleString() + '</b>');

                        var ctrlHtml = '';
                        if (totalPages > 1) {
                            ctrlHtml += '<button class="seo-btn seo-btn-ghost seo-btn-xs db-pag-btn" data-page="1" ' + (curPage === 1 ? 'disabled' : '') + ' style="padding:3px 8px;font-size:11px;">«</button>';
                            ctrlHtml += '<button class="seo-btn seo-btn-ghost seo-btn-xs db-pag-btn" data-page="' + (curPage - 1) + '" ' + (curPage === 1 ? 'disabled' : '') + ' style="padding:3px 8px;font-size:11px;">‹</button>';

                            var pStart = Math.max(1, curPage - 2);
                            var pEnd   = Math.min(totalPages, curPage + 2);
                            for (var p = pStart; p <= pEnd; p++) {
                                var act = (p === curPage) ? 'background:var(--c-primary);color:#fff;border-color:var(--c-primary);' : 'background:transparent;';
                                ctrlHtml += '<button class="seo-btn seo-btn-ghost seo-btn-xs db-pag-btn" data-page="' + p + '" style="padding:3px 9px;font-size:11px;' + act + '">' + p + '</button>';
                            }

                            ctrlHtml += '<button class="seo-btn seo-btn-ghost seo-btn-xs db-pag-btn" data-page="' + (curPage + 1) + '" ' + (curPage === totalPages ? 'disabled' : '') + ' style="padding:3px 8px;font-size:11px;">›</button>';
                            ctrlHtml += '<button class="seo-btn seo-btn-ghost seo-btn-xs db-pag-btn" data-page="' + totalPages + '" ' + (curPage === totalPages ? 'disabled' : '') + ' style="padding:3px 8px;font-size:11px;">»</button>';
                        }
                        $pagBar.find('.db-pag-controls').html(ctrlHtml);
                    } else {
                        $tbody.html('<tr><td colspan="20" style="text-align:center;padding:20px;color:red;">Failed to load data.</td></tr>');
                    }
                });
            }

            $pane.data('refreshPagination', function() {
                updatePage(curPage);
            });

            $pagBar.on('click', '.db-pag-btn', function(e) {
                e.preventDefault();
                var p = parseInt(jQuery(this).data('page'), 10);
                if (!isNaN(p)) updatePage(p);
            });

            $pagBar.on('change', '.db-pag-size', function() {
                var sz = jQuery(this).val();
                perPage = (sz === 'all') ? 'all' : parseInt(sz, 10);
                updatePage(1);
            });

            if ($pane.is(':visible')) {
                updatePage(1);
            }
        });
    }

    function seoDashOpenMonth(dbType, month) {
        currentDbMonth[dbType] = month;
        jQuery('#db-' + dbType + '-working-msg').text(month);
        jQuery('#db-' + dbType + '-workspace-title').text(month);
        jQuery('#db-' + dbType + '-workspace-wrap').show();
        
        var activeFront = jQuery('#db-' + dbType + '-active-frontend-msg').text();
        if (activeFront === month) {
            jQuery('#db-' + dbType + '-workspace-active-badge').show();
            jQuery('#db-' + dbType + '-frontend-toggle').prop('checked', true);
        } else {
            jQuery('#db-' + dbType + '-workspace-active-badge').hide();
            jQuery('#db-' + dbType + '-frontend-toggle').prop('checked', false);
        }

        jQuery('#db-' + dbType + '-workspace-content').html('<div style="padding:40px;text-align:center;color:var(--c-muted);">⏳ Loading workspace...</div>');
        
        jQuery.post(seoDash.ajax, {
            action: 'seo_dash_render_month_workspace',
            nonce: seoDash.nonce,
            report_id: <?php echo $rid; ?>,
            db_type: dbType,
            target_month: month
        }, function(r){
            if (r.success) {
                jQuery('#db-' + dbType + '-workspace-content').html(r.data.html);
                if (typeof seoDashInitTableFilters === 'function') seoDashInitTableFilters();
                
                // Initialize fast pagination
                seoDashInitWorkspacePagination(dbType);

                // Bind sub-tabs
                jQuery('#db-' + dbType + '-workspace-content .db-type-subtab').on('click', function(){
                    var type = jQuery(this).data('type');
                    jQuery('#db-' + dbType + '-workspace-content .db-type-subtab').css({'border-bottom-color':'transparent', 'color':'var(--c-muted)'}).removeClass('db-type-subtab-active');
                    jQuery(this).css({'border-bottom-color':'var(--c-primary)', 'color':'var(--c-primary)'}).addClass('db-type-subtab-active');
                    jQuery('#db-' + dbType + '-workspace-content .db-type-subpane').hide();
                    var $activePane = jQuery('#db-' + dbType + '-workspace-content .db-type-subpane[data-type="'+type+'"]').show();
                    
                    if (typeof $activePane.data('refreshPagination') === 'function') {
                        $activePane.data('refreshPagination')();
                    }
                });
            } else {
                jQuery('#db-' + dbType + '-workspace-content').html('<div style="padding:20px;color:red;">Error: ' + seoGaErrMsg(r) + '</div>');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
     seoJQ(function($){
        // Main Tab switching
        document.querySelectorAll('.db-type-tab').forEach(btn => {
            btn.addEventListener('click', function() {
                var t = this.dataset.dbtype;
                document.querySelectorAll('.db-type-tab').forEach(b => {
                    b.classList.remove('db-type-tab-active');
                    b.style.borderBottomColor = 'transparent';
                    b.style.color = 'var(--c-muted)';
                });
                this.classList.add('db-type-tab-active');
                this.style.borderBottomColor = 'var(--c-primary)';
                this.style.color = 'var(--c-primary)';
                document.querySelectorAll('.db-type-panel').forEach(p => p.style.display = 'none');
                var p = document.querySelector('.db-type-panel[data-dbtype="'+t+'"]');
                if (p) {
                    p.style.display = 'block';
                    if (t === 'ga' || t === 'sc') {
                        var workMsg = jQuery('#db-' + t + '-working-msg').text();
                        if (!workMsg || workMsg === 'None') {
                            var y = jQuery('#db-' + t + '-year').val() || (new Date()).getFullYear();
                            var m = jQuery('#db-' + t + '-month').val() || ('0' + ((new Date()).getMonth() + 1)).slice(-2);
                            seoDashOpenMonth(t, y + '-' + m);
                        }
                    }
                }
            });
        });

        // Sub-tabs for Service Pages
        jQuery(document).on('click', '.pages-type-subtab', function(){
            var $btn = jQuery(this);
            var ptype = $btn.data('ptype');
            var context = $btn.data('context');
            var $container = $btn.closest('.seo-pages-period-pane');
            
            $btn.siblings().css({'border-bottom-color':'transparent', 'color':'var(--c-muted)'}).removeClass('pages-type-subtab-active');
            $btn.css({'border-bottom-color':'var(--c-primary)', 'color':'var(--c-primary)'}).addClass('pages-type-subtab-active');
            
            // Hide all rows first, then show only those matching ptype
            $container.find('tbody tr').hide();
            
            var isTrashView = $container.find('.seo-custom-page-view-trash-btn').hasClass('viewing-trash');
            var rowClass = isTrashView ? '.db-custom-trashed-row' : '.db-custom-active-row';
            
            $container.find(rowClass + '.pages-row-type-' + ptype).show();
            
            // Re-trigger filter logic to ensure search bar still works
            var $search = $container.closest('.seo-panel').find('.seo-custom-page-search');
            if ($search.length && $search.val()) {
                $search.trigger('input');
            }
        });

        // Initialize sub-tabs display
        jQuery('.pages-type-subtab-active').each(function(){
            var ptype = jQuery(this).data('ptype');
            var $container = jQuery(this).closest('.seo-pages-period-pane');
            $container.find('tbody tr').hide();
            $container.find('.db-custom-active-row.pages-row-type-' + ptype).show();
        });

        // Open Month via form
        jQuery('.db-open-month-btn').on('click', function(){
            var type = jQuery(this).data('dbtype');
            var y = jQuery('#db-' + type + '-year').val();
            var m = jQuery('#db-' + type + '-month').val();
            if(y && m) seoDashOpenMonth(type, y + '-' + m);
        });

        // Open Month via dropdown
        jQuery('#db-ga-month-select-open').on('click', function(){
            var month = jQuery('#db-ga-month-select').val();
            if (!month) return;
            var parts = month.split('-');
            jQuery('#db-ga-year').val(parts[0]);
            jQuery('#db-ga-month').val(parts[1]);
            seoDashOpenMonth('ga', month);
        });
        // Also open on double-click / Enter on the select itself
        jQuery('#db-ga-month-select').on('dblclick change', function(){
            var month = jQuery(this).val();
            if (!month) return;
            var parts = month.split('-');
            jQuery('#db-ga-year').val(parts[0]);
            jQuery('#db-ga-month').val(parts[1]);
            seoDashOpenMonth('ga', month);
        });
        // Keep legacy pill handler for SC (Search Console still uses pills)
        jQuery('.db-sc-month-pill').on('click', function(){
            var month = jQuery(this).data('month');
            var parts = month.split('-');
            jQuery('#db-sc-year').val(parts[0]);
            jQuery('#db-sc-month').val(parts[1]);
            seoDashOpenMonth('sc', month);
        });
        // SC dropdown Open button
        jQuery('#db-sc-month-select-open').on('click', function(){
            var month = jQuery('#db-sc-month-select').val();
            if (!month) return;
            var parts = month.split('-');
            jQuery('#db-sc-year').val(parts[0]);
            jQuery('#db-sc-month').val(parts[1]);
            seoDashOpenMonth('sc', month);
        });
        jQuery('#db-sc-month-select').on('dblclick change', function(){
            var month = jQuery(this).val();
            if (!month) return;
            var parts = month.split('-');
            jQuery('#db-sc-year').val(parts[0]);
            jQuery('#db-sc-month').val(parts[1]);
            seoDashOpenMonth('sc', month);
        });

        // Toggle Frontend Checkbox
        jQuery('#db-ga-frontend-toggle').on('change', function(){
            if(this.checked) seoDashSetActiveMonth('ga', currentDbMonth['ga']);
            else seoDashSetActiveMonth('ga', '');
        });
        jQuery('#db-sc-frontend-toggle').on('change', function(){
            if(this.checked) seoDashSetActiveMonth('sc', currentDbMonth['sc']);
            else seoDashSetActiveMonth('sc', '');
        });

        // Helper: Date Chunker function for large date ranges
        function generateSeoDateChunks(startDateStr, endDateStr, monthsPerChunk) {
            monthsPerChunk = monthsPerChunk || 4; // 4 months per chunk for lightning speed
            var chunks = [];
            var s = new Date(startDateStr);
            var e = new Date(endDateStr);
            if (isNaN(s.getTime()) || isNaN(e.getTime()) || s >= e) {
                return [{ start: startDateStr, end: endDateStr }];
            }
            var curr = new Date(s);
            while (curr < e) {
                var chunkStart = curr.toISOString().slice(0, 10);
                var next = new Date(curr);
                next.setMonth(next.getMonth() + monthsPerChunk);
                next.setDate(next.getDate() - 1);
                
                if (next >= e) {
                    chunks.push({ start: chunkStart, end: endDateStr });
                    break;
                } else {
                    chunks.push({ start: chunkStart, end: next.toISOString().slice(0, 10) });
                    curr = new Date(next);
                    curr.setDate(curr.getDate() + 1);
                }
            }
            return chunks.length ? chunks : [{ start: startDateStr, end: endDateStr }];
        }

        // Fetch Button with Chunking Support
        jQuery('.db-fetch-btn').on('click', function(){
            var type = jQuery(this).data('dbtype');
            var month = currentDbMonth[type];
            if (!month) return;
            var $btn = jQuery(this);
            $btn.prop('disabled', true).text('Initializing Data Chunking...');
            
            var overallStart = jQuery('.db-overall-date-from[data-dbtype="'+type+'"]').val();
            var overallEnd   = jQuery('.db-overall-date-to[data-dbtype="'+type+'"]').val() || new Date().toISOString().slice(0,10);
            var granMode     = jQuery('.db-granularity-mode[data-dbtype="'+type+'"]').val() || 'overall_months';

            // Validate yyyy-MM-dd format before sending to server.
            var dateRe = /^\d{4}-\d{2}-\d{2}$/;
            if(!overallStart || !dateRe.test(overallStart) || !dateRe.test(overallEnd)) {
                if (typeof seoToast === 'function') seoToast('Invalid date range. Please re-select the From and To dates.', 'error');
                $btn.prop('disabled', false).text('⬇️ Fetch Data into Month');
                return;
            }

            // Always store under the "To" date's month — cumulative total up to that month.
            var targetMonth = overallEnd.substring(0, 7); // yyyy-MM from date_to

            var chunks = generateSeoDateChunks(overallStart, overallEnd, granMode === 'overall_days' ? 2 : 6);
            var totalChunks = chunks.length;
            var currentChunkIndex = 0;
            var accumulatedRows = 0;
            var lastPendingUrls = null;

            function finishFetchAll() {
                $btn.prop('disabled', false).text('⬇️ Fetch Data into Month');
                if (typeof seoToast === 'function') {
                    seoToast('All ' + totalChunks + ' chunks fetched successfully! (' + accumulatedRows + ' rows)', 'ok');
                }
                seoDashOpenMonth(type, month); // refresh workspace
                if (lastPendingUrls && typeof window.seoDashShowAddToPagesModal === 'function') {
                    setTimeout(function(){ window.seoDashShowAddToPagesModal(lastPendingUrls); }, 800);
                }
            }

            function fetchGranularityData() {
                if (type === 'ga') {
                    $btn.text('⚡ Fetching Granularity Data (' + granMode + ')...');
                    jQuery.ajax({
                        url: seoDash.ajax,
                        type: 'POST',
                        timeout: 300000,
                        data: {
                            action: 'seo_dash_ga_fetch_all_periods',
                            nonce: seoDash.nonce,
                            report_id: <?php echo $rid; ?>,
                            filter_type: 'all',
                            target_month: targetMonth,
                            overall_start: overallStart,
                            overall_end: overallEnd,
                            granularity_mode: granMode,
                            fetch_presets: '0',
                            fetch_granularity: '1'
                        },
                        success: function(r){
                            if (r.success && r.data) {
                                var fetchedCount = r.data.rows_saved || r.data.inserted || r.data.upserted || 0;
                                accumulatedRows += fetchedCount;
                            }
                            finishFetchAll();
                        },
                        error: function(){
                            finishFetchAll();
                        }
                    });
                } else {
                    finishFetchAll();
                }
            }

            function processNextChunk() {
                if (currentChunkIndex >= totalChunks) {
                    fetchGranularityData();
                    return;
                }

                var chunk = chunks[currentChunkIndex];
                $btn.text('⚡ Chunk ' + (currentChunkIndex + 1) + ' of ' + totalChunks + ' (' + chunk.start + ' to ' + chunk.end + ')...');

                jQuery.ajax({
                    url: seoDash.ajax,
                    type: 'POST',
                    timeout: 300000, // 5 minutes timeout per chunk
                    data: {
                        action: 'seo_dash_' + type + '_fetch_all_periods',
                        nonce: seoDash.nonce,
                        report_id: <?php echo $rid; ?>,
                        filter_type: 'all',
                        target_month: targetMonth,
                        overall_start: chunk.start,
                        overall_end: chunk.end,
                        granularity_mode: granMode,
                        fetch_presets: '1',
                        fetch_granularity: '0'
                    },
                    success: function(r){
                        if (r.success) {
                            var fetchedCount = r.data ? (r.data.inserted || r.data.upserted || 0) : 0;
                            accumulatedRows += fetchedCount;
                            if (r.data && r.data.pending_urls) lastPendingUrls = r.data.pending_urls;
                            currentChunkIndex++;
                            processNextChunk(); // Starts next chunk IMMEDIATELY without waiting!
                        } else {
                            $btn.prop('disabled', false).text('⬇️ Fetch Data into Month');
                            var errMsg = seoGaErrMsg(r);
                            alert('Error on Chunk ' + (currentChunkIndex + 1) + ' (' + chunk.start + ' to ' + chunk.end + '):\n\n' + errMsg);
                        }
                    },
                    error: function(xhr, status, error){
                        $btn.prop('disabled', false).text('⬇️ Fetch Data into Month');
                        var rawText = xhr.responseText ? xhr.responseText.replace(/<[^>]+>/g, '').trim() : '';
                        var detail  = rawText.substring(0, 350) || error || status || 'Server Timeout / Disconnected';
                        alert('Server Error on Chunk ' + (currentChunkIndex + 1) + ' (' + chunk.start + ' to ' + chunk.end + '):\n\n' + detail);
                    }
                });
            }

            processNextChunk();
        });

        // Fetch Selected Button
        jQuery('.db-fetch-selected-btn').on('click', function(){
            var type = jQuery(this).data('dbtype');
            var month = currentDbMonth[type];
            if (!month) return;
            
            var selectedUrls = [];
            jQuery('#db-' + type + '-workspace-content .db-' + type + '-row-chk:checked').each(function(){
                selectedUrls.push(jQuery(this).val());
            });
            
            if (selectedUrls.length === 0) {
                alert('Please select at least one URL using the checkboxes on the left.');
                return;
            }
            
            var $btn = jQuery(this);
            var origText = $btn.text();
            $btn.prop('disabled', true).text('Fetching ' + selectedUrls.length + ' URLs...');
            
            var overallStart = jQuery('.db-overall-date-from[data-dbtype="'+type+'"]').val();
            var overallEnd   = jQuery('.db-overall-date-to[data-dbtype="'+type+'"]').val() || new Date().toISOString().slice(0,10);
            var granMode     = jQuery('.db-granularity-mode[data-dbtype="'+type+'"]').val() || 'overall_months';
            // Store under the "To" date's month — cumulative total up to that month.
            var targetMonth  = overallEnd.substring(0, 7);
            
            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_' + type + '_fetch_all_periods',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                filter_type: 'all',
                target_month: targetMonth,
                selected_urls: selectedUrls,
                overall_start: overallStart,
                overall_end: overallEnd,
                granularity_mode: granMode
            }, function(r){
                $btn.prop('disabled', false).text(origText);
                if (r.success) {
                    var fetchedCount = r.data ? (r.data.inserted || r.data.upserted || 0) : 0;
                    if(typeof seoToast === 'function') seoToast('Selected data fetched! (' + fetchedCount + ' rows)', 'ok');
                    seoDashOpenMonth(type, targetMonth); // refresh workspace // refresh workspace
                    if (r.data && r.data.pending_urls && typeof window.seoDashShowAddToPagesModal === 'function') {
                        setTimeout(function(){ window.seoDashShowAddToPagesModal(r.data.pending_urls); }, 800);
                    }
                } else {
                    var errMsg = (r.data && typeof r.data === 'object') ? (r.data.message || JSON.stringify(r.data)) : (r.data || 'Unknown error');
                    alert('Error: ' + errMsg);
                }
            }).fail(function(){
                $btn.prop('disabled', false).text(origText);
                alert('Server error.');
            });
        });

        // Quick Preset Fetch Buttons (7d / 30d / 90d)
        jQuery('.db-preset-fetch-btn').on('click', function(){
            var type = jQuery(this).data('dbtype');
            var month = currentDbMonth[type];
            if (!month) { alert('Please select a month first.'); return; }
            var days = parseInt(jQuery(this).data('days'));
            var $btn = jQuery(this);
            var origText = $btn.text();
            $btn.prop('disabled', true).text('Fetching...');

            var today = new Date();
            var from  = new Date(today);
            from.setDate(today.getDate() - days);
            var fmt = function(d){ return d.toISOString().slice(0,10); };

            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_' + type + '_fetch_all_periods',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                filter_type: 'all',
                target_month: fmt(today).substring(0, 7),
                overall_start: fmt(from),
                overall_end: fmt(today),
                preset_days: days
            }, function(r){
                $btn.prop('disabled', false).text(origText);
                if (r.success) {
                    var fetchedCount = r.data ? (r.data.inserted || r.data.upserted || 0) : 0;
                    if(typeof seoToast === 'function') seoToast('⚡ ' + days + '-day data fetched! (' + fetchedCount + ' rows)', 'ok');
                    seoDashOpenMonth(type, fmt(today).substring(0, 7));
                    if (r.data && r.data.pending_urls && typeof window.seoDashShowAddToPagesModal === 'function') {
                        setTimeout(function(){ window.seoDashShowAddToPagesModal(r.data.pending_urls); }, 800);
                    }
                } else {
                    var errMsg = (r.data && typeof r.data === 'object') ? (r.data.message || JSON.stringify(r.data)) : (r.data || 'Unknown error');
                    alert('Error: ' + errMsg);
                }
            }).fail(function(){
                $btn.prop('disabled', false).text(origText);
                alert('Server error.');
            });
        });

        // Fetch Custom Range Button Handler
        jQuery(document).on('click', '.db-fetch-custom-btn', function(){
            var type = jQuery(this).data('dbtype');
            var month = currentDbMonth[type];
            if (!month) { alert('Please select a year & month first.'); return; }
            var cFrom = jQuery('.db-custom-date-from[data-dbtype="'+type+'"]').val();
            var cTo   = jQuery('.db-custom-date-to[data-dbtype="'+type+'"]').val();
            if (!cFrom || !cTo) { alert('Please select both Start Date and End Date for the custom range.'); return; }
            
            var $btn = jQuery(this);
            var origText = $btn.text();
            $btn.prop('disabled', true).text('Fetching Custom Range...');
            
            var mode = jQuery('.db-granularity-mode[data-dbtype="'+type+'"]').val() || 'overall_months';
            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_' + type + '_fetch_custom_range',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                target_month: cTo.substring(0, 7),
                custom_from: cFrom,
                custom_to: cTo,
                granularity_mode: mode
            }, function(r){
                $btn.prop('disabled', false).text(origText);
                if (r.success) {
                    var fetchedCount = r.data ? (r.data.inserted || r.data.upserted || 0) : 0;
                    if(typeof seoToast === 'function') seoToast('📅 Custom range data (' + cFrom + ' to ' + cTo + ') fetched! (' + fetchedCount + ' rows)', 'ok');
                    seoDashOpenMonth(type, cTo.substring(0, 7)); // open the to-date month // refresh workspace
                } else {
                    var errMsg = (r.data && typeof r.data === 'object') ? (r.data.message || JSON.stringify(r.data)) : (r.data || 'Unknown error');
                    alert('Error: ' + errMsg);
                }
            }).fail(function(){
                $btn.prop('disabled', false).text(origText);
                alert('Server error.');
            });
        });

        // Database Table Search Filter with Debounce
        var _dbSearchTimer = null;
        jQuery(document).on('input', '.db-table-search', function(){
            var dbType = jQuery(this).data('dbtype');
            clearTimeout(_dbSearchTimer);
            _dbSearchTimer = setTimeout(function(){
                var $workspace = jQuery('#db-' + dbType + '-workspace-content');
                var activeType = $workspace.find('.db-type-subtab-active').data('type');
                if(!activeType) return;
                var $activePane = $workspace.find('.db-type-subpane[data-type="'+activeType+'"]');
                if ($activePane.length && typeof $activePane.data('refreshPagination') === 'function') {
                    $activePane.data('refreshPagination')();
                }
            }, 100);
        });

        // Custom Page Search Filter
        jQuery(document).on('input', '.seo-custom-page-search', function(){
            var q = jQuery(this).val().toLowerCase();
            var type = jQuery(this).data('type');
            var $container = jQuery('.seo-pages-period-pane[data-type="'+type+'"]');
            
            var activePtype = '';
            var isService = type === 'service';
            if (isService) {
                activePtype = $container.closest('.seo-panel').find('.pages-type-subtab-active').data('ptype');
            }
            
            var isTrashView = $container.find('.seo-custom-page-view-trash-btn').hasClass('viewing-trash');
            var rowClass = isTrashView ? '.db-custom-trashed-row' : '.db-custom-active-row';
            
            $container.find('tbody tr').hide();
            
            $container.find(rowClass).each(function(){
                var $row = jQuery(this);
                if (isService && activePtype && !$row.hasClass('pages-row-type-' + activePtype)) {
                    return; // skip
                }
                var text = $row.find('.seo-custom-page-input[data-field="title"]').val() || '';
                var url = $row.find('.seo-custom-page-chk').val() || '';
                var keyword = $row.find('.seo-custom-page-input[data-field="keyword"]').val() || '';
                
                if (!q || text.toLowerCase().indexOf(q) > -1 || url.toLowerCase().indexOf(q) > -1 || keyword.toLowerCase().indexOf(q) > -1) {
                    $row.show();
                }
            });
        });

        // Checkbox behaviors (delegated since table is loaded via AJAX)
        jQuery(document).on('change', '.db-ga-select-all-chk, .db-sc-select-all-chk', function() {
            var subType = jQuery(this).data('type');
            var isChecked = jQuery(this).prop('checked');
            var cls = jQuery(this).hasClass('db-ga-select-all-chk') ? 'db-ga' : 'db-sc';
            jQuery('.' + cls + '-row-chk[data-type="' + subType + '"]').prop('checked', isChecked);
        });
        
        jQuery(document).on('change', '.db-ga-row-chk, .db-sc-row-chk', function() {
            var subType = jQuery(this).data('type');
            var cls = jQuery(this).hasClass('db-ga-row-chk') ? 'db-ga' : 'db-sc';
            var allChecked = jQuery('.' + cls + '-row-chk[data-type="' + subType + '"]:not(:checked)').length === 0;
            jQuery('.' + cls + '-select-all-chk[data-type="' + subType + '"]').prop('checked', allChecked);
        });

        // Trash Month
        jQuery('.db-trash-month-btn').on('click', function(){
            var type = jQuery(this).data('dbtype');
            var month = currentDbMonth[type];
            if (!month) return;
            if(!confirm('Are you sure you want to delete all data for ' + month + '?')) return;
            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_' + type + '_clear_month',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                month_key: month
            }, function(r){
                if (r.success) location.reload();
            });
        });

        // Bulk Actions
        jQuery('.db-bulk-action-btn').on('click', function(){
            var type = jQuery(this).data('dbtype');
            var action = jQuery('.db-bulk-action-sel[data-dbtype="'+type+'"]').val();
            if (!action) {
                alert('Please select a bulk action.');
                return;
            }
            
            var $workspace = jQuery('#db-' + type + '-workspace-content');
            var activeSubtype = $workspace.find('.db-type-subtab-active').data('type') || '';

            // 1. Delete All in Current Sub-tab Permanently
            if (action === 'delete_all_subtype') {
                if (!activeSubtype) {
                    alert('No active sub-tab found.');
                    return;
                }
                var subName = activeSubtype.charAt(0).toUpperCase() + activeSubtype.slice(1).replace(/_/g, ' ');
                if (!confirm('⚠️ Are you sure you want to permanently delete ALL URLs in the "' + subName + '" sub-tab?\n\nThis will remove all rows for this subtype from the database permanently and cannot be undone.')) {
                    return;
                }

                var $btn = jQuery(this);
                var origText = $btn.text();
                $btn.prop('disabled', true).text('Deleting...');

                jQuery.post(seoDash.ajax, {
                    action: 'seo_dash_bulk_data_action',
                    nonce: seoDash.nonce,
                    table: type,
                    report_id: <?php echo $rid; ?>,
                    bulk_action: 'delete_all_subtype',
                    subtype: activeSubtype
                }, function(r){
                    $btn.prop('disabled', false).text(origText);
                    if (r.success) {
                        if (typeof seoToast === 'function') seoToast('All ' + subName + ' pages deleted permanently.', 'ok');
                        seoDashOpenMonth(type, currentDbMonth[type]);
                    } else {
                        alert('Error: ' + seoGaErrMsg(r));
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(origText);
                    alert('Server error.');
                });
                return;
            }

            // 2. Delete All in All Sub-tabs Permanently
            if (action === 'delete_all') {
                if (!confirm('⚠️ DANGER: Are you sure you want to permanently delete ALL pages across ALL sub-tabs for this report?\n\nThis will completely clear this database table and cannot be undone.')) {
                    return;
                }

                var $btn = jQuery(this);
                var origText = $btn.text();
                $btn.prop('disabled', true).text('Deleting All...');

                jQuery.post(seoDash.ajax, {
                    action: 'seo_dash_bulk_data_action',
                    nonce: seoDash.nonce,
                    table: type,
                    report_id: <?php echo $rid; ?>,
                    bulk_action: 'delete_all'
                }, function(r){
                    $btn.prop('disabled', false).text(origText);
                    if (r.success) {
                        if (typeof seoToast === 'function') seoToast('All pages deleted permanently.', 'ok');
                        seoDashOpenMonth(type, currentDbMonth[type]);
                    } else {
                        alert('Error: ' + seoGaErrMsg(r));
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text(origText);
                    alert('Server error.');
                });
                return;
            }

            // 3. Row-selection actions (trash, restore, delete, add_service, add_blog)
            var selectedIds = [];
            var selectedUrls = [];
            $workspace.find('.db-' + type + '-row-chk:checked').each(function(){
                var u = jQuery(this).val();
                if(u) selectedUrls.push(u);

                var ids = jQuery(this).closest('tr').find('.seo-del-url-btn').data('ids');
                if (ids) {
                    var idsArr = String(ids).split(',').filter(Boolean);
                    selectedIds = selectedIds.concat(idsArr);
                }
            });
            
            if (selectedIds.length === 0 && selectedUrls.length === 0) {
                alert('Please select at least one row.');
                return;
            }
            
            if (action === 'delete' && !confirm('Are you sure you want to permanently delete the selected items?')) {
                return;
            }
            
            var $btn = jQuery(this);
            var origText = $btn.text();
            $btn.prop('disabled', true).text('Processing...');
            
            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_bulk_data_action',
                nonce: seoDash.nonce,
                table: type,
                report_id: <?php echo $rid; ?>,
                bulk_action: action,
                ids: selectedIds,
                urls: selectedUrls
            }, function(r){
                $btn.prop('disabled', false).text(origText);
                if (r.success) {
                    if(typeof seoToast === 'function') seoToast((r.data ? (r.data.done || selectedUrls.length) : selectedUrls.length) + ' rows updated.', 'ok');
                    seoDashOpenMonth(type, currentDbMonth[type]); // refresh workspace
                } else {
                    alert('Error: ' + seoGaErrMsg(r));
                }
            }).fail(function(){
                $btn.prop('disabled', false).text(origText);
                alert('Server error.');
            });
        });
        
        // View Trash Toggle for GA/SC
        jQuery(document).on('click', '.db-view-trash-btn', function(){
            var type = jQuery(this).data('dbtype');
            var isTrashView = jQuery(this).hasClass('viewing-trash');
            if (isTrashView) {
                // Switch to active view
                jQuery('#db-'+type+'-workspace-content .db-ga-trashed-row').hide();
                jQuery('#db-'+type+'-workspace-content .db-ga-active-row').show();
                jQuery(this).removeClass('viewing-trash').html('🗑️ View Trash');
                jQuery('.db-bulk-action-sel[data-dbtype="'+type+'"]').val('');
            } else {
                // Switch to trash view
                jQuery('#db-'+type+'-workspace-content .db-ga-active-row').hide();
                jQuery('#db-'+type+'-workspace-content .db-ga-trashed-row').show();
                jQuery(this).addClass('viewing-trash').html('🔙 View Active');
                jQuery('.db-bulk-action-sel[data-dbtype="'+type+'"]').val('restore');
            }
            // Uncheck all when switching views
            jQuery('.db-'+type+'-select-all-chk').prop('checked', false).trigger('change');

            // Refresh pagination for current subpane
            var activeSubtype = jQuery('#db-'+type+'-workspace-content .db-type-subtab-active').data('type');
            var $activePane = jQuery('#db-'+type+'-workspace-content .db-type-subpane[data-type="'+activeSubtype+'"]');
            if ($activePane.length && typeof $activePane.data('refreshPagination') === 'function') {
                $activePane.data('refreshPagination')();
            }
        });
        
        // Generic Checkbox Handlers for other tabs (gmb, technical, backlinks, leads)
        jQuery(document).on('change', '.db-generic-check-all', function() {
            var type = jQuery(this).data('type');
            jQuery('.db-generic-chk[data-type="'+type+'"]').prop('checked', jQuery(this).is(':checked'));
        });
        
        jQuery(document).on('change', '.db-generic-chk', function() {
            var type = jQuery(this).data('type');
            var allChecked = jQuery('.db-generic-chk[data-type="'+type+'"]:not(:checked)').length === 0;
            jQuery('.db-generic-check-all[data-type="'+type+'"]').prop('checked', allChecked);
        });

        // Generic Bulk Actions Handler
        jQuery(document).on('click', '.db-generic-bulk-action-btn', function(){
            var type = jQuery(this).data('type');
            var action = jQuery('.db-generic-bulk-action-sel[data-type="'+type+'"]').val();
            
            if (!action) {
                alert('Please select a bulk action.');
                return;
            }
            
            var selectedIds = [];
            jQuery('.db-generic-chk[data-type="'+type+'"]:checked').each(function(){
                selectedIds.push(jQuery(this).val());
            });
            
            if (selectedIds.length === 0) {
                alert('Please select at least one row.');
                return;
            }
            
            if (action === 'delete' && !confirm('Are you sure you want to permanently delete the selected items?')) {
                return;
            }
            
            var $btn = jQuery(this);
            var origText = $btn.text();
            $btn.prop('disabled', true).text('Processing...');
            
            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_bulk_data_action',
                nonce: seoDash.nonce,
                table: type,
                report_id: <?php echo $rid; ?>,
                bulk_action: action,
                ids: selectedIds
            }, function(r){
                $btn.prop('disabled', false).text(origText);
                if (r.success) {
                    if(typeof seoToast === 'function') seoToast(r.data.done + ' rows updated.', 'ok');
                    location.reload();
                } else {
                    alert('Error: ' + seoGaErrMsg(r));
                }
            }).fail(function(){
                $btn.prop('disabled', false).text(origText);
                alert('Server error.');
            });
        });

        // View Trash Toggle
        jQuery(document).on('click', '.db-generic-view-trash-btn', function(){
            var type = jQuery(this).data('type');
            var isTrashView = jQuery(this).hasClass('viewing-trash');
            if (isTrashView) {
                // Switch to active view
                jQuery('.db-row-type-'+type+'.db-generic-trashed-row').hide();
                jQuery('.db-row-type-'+type+'.db-generic-active-row').show();
                jQuery(this).removeClass('viewing-trash').html('🗑️ View Trash');
                jQuery('.db-generic-bulk-action-sel[data-type="'+type+'"]').val('');
            } else {
                // Switch to trash view
                jQuery('.db-row-type-'+type+'.db-generic-active-row').hide();
                jQuery('.db-row-type-'+type+'.db-generic-trashed-row').show();
                jQuery(this).addClass('viewing-trash').html('🔙 View Active');
                jQuery('.db-generic-bulk-action-sel[data-type="'+type+'"]').val('restore');
            }
            // Uncheck all when switching views
            jQuery('.db-generic-check-all[data-type="'+type+'"]').prop('checked', false).trigger('change');
        });

     });
    });
    </script>

<?php // ── ANALYTICS ──────────────────────────────────────────────────────────
elseif ($tab === 'analytics') :
    $months  = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_ga, $rid);
    $ga_rows = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_ga, $rid);

    // Load the sitemap-push url→type map saved by seo_dash_sitemap_push
    $ga_type_map = get_option( "seo_dash_sitemap_types_{$rid}_ga", [] );
    if ( ! is_array( $ga_type_map ) ) $ga_type_map = [];

    function seo_dash_ga_type_v2(array $row, array $map): string {
        $url = trim($row['page_url'] ?? '');
        if ($url) {
            if (isset($map[$url])) return $map[$url];
            if (isset($map[trailingslashit($url)])) return $map[trailingslashit($url)];
            if (isset($map[untrailingslashit($url)])) return $map[untrailingslashit($url)];
            $path = parse_url($url, PHP_URL_PATH);
            if ($path) {
                if (isset($map[$path])) return $map[$path];
                if (isset($map[trailingslashit($path)])) return $map[trailingslashit($path)];
                if (isset($map[untrailingslashit($path)])) return $map[untrailingslashit($path)];
            }
        }
        $pt = trim($row['page_title'] ?? '');
        if ($pt) {
            if (preg_match('/^\[sitemap:([a-z0-9_-]+)\]/i', $pt, $m)) return strtolower($m[1]);
            if (preg_match('/^\[([a-z0-9_-]+)\]$/i', $pt, $m)) return strtolower($m[1]);
        }
        return 'other';
    }
    $ga_groups = [];
    $excluded_ga_types_rd = [ 'gmb_posts', 'gmb_post' ];
    foreach ($ga_rows as $row) {
        $t = seo_dash_ga_type_v2($row, $ga_type_map);
        if ( in_array( $t, $excluded_ga_types_rd, true ) ) continue;
        $ga_groups[$t][] = $row;
    }
    ksort($ga_groups);
    $ga_first_type = !empty($ga_groups) ? array_key_first($ga_groups) : '';

    // Build URLs-by-type for inline JS (used by the Overview panel).
    // Merge BOTH already-saved rows AND the sitemap type map so the
    // Overview panel is populated even on a fresh report with no rows yet.
    $ga_urls_by_type = [];
    foreach ($ga_groups as $t => $trows) {
        $ga_urls_by_type[$t] = array_values(array_unique(array_column($trows, 'page_url')));
    }
    // Pull in URLs from the sitemap push map (covers the first-fetch case).
    foreach ($ga_type_map as $_sm_url => $_sm_type) {
        if ( $_sm_url && $_sm_type ) {
            $ga_urls_by_type[$_sm_type][] = $_sm_url;
        }
    }
    // Re-deduplicate after merging.
    foreach ($ga_urls_by_type as $_t => &$_urls) {
        $_urls = array_values(array_unique($_urls));
    }
    unset($_urls);
    ksort($ga_urls_by_type);

    // Resolve the GA4 property ID from the assigned global integration
    // so we can pass it to the JS fetch call.
    $_ga_property_id = '';
    if ( function_exists('seo_dash_get_global_integration_by_id') ) {
        $_ga_assignments = get_option( 'seo_dash_report_global_intg_' . $rid, [] );
        $_ga_global_id   = is_array($_ga_assignments) ? ( $_ga_assignments['global'] ?? '' ) : '';
        if ( $_ga_global_id ) {
            $_ga_intg = seo_dash_get_global_integration_by_id( $_ga_global_id );
            $_ga_property_id = $_ga_intg['ga4_property_id'] ?? '';
        }
    }
?>
    <!-- Inline JS data for Overview panel -->
    <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
    window.gaOverviewData = window.gaOverviewData || {};
    window.gaOverviewData[<?php echo intval($rid); ?>] = <?php echo wp_json_encode($ga_urls_by_type); ?>;
    window.gaReportProperty = window.gaReportProperty || {};
    window.gaReportProperty[<?php echo intval($rid); ?>] = <?php echo wp_json_encode($_ga_property_id); ?>;
    </script>

    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2>📈 Google Analytics (Legacy View)</h2>
            <div style="display:flex;gap:8px;">
                <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-ga-manual-btn">＋ Manual Import</button>
            </div>
        </div>

        <div style="margin:20px; padding:16px 20px; background:#fff3cd; border:1px solid #ffe69c; border-radius:6px; color:#664d03;">
            <strong>⚠Legacy View:</strong> This tab displays raw database records. Since the new system fetches 4 different time periods (7d, 30d, 90d, overall) plus a monthly placeholder, <strong>each URL will appear up to 5 times here</strong>. <br><br>
            Please use the new <strong><a href="<?php echo esc_url($tab_urls['database']); ?>" style="color:#052c65; font-weight:bold; text-decoration:underline;">Database Tab</a></strong> to manage your data without duplicates!
        </div>

        <?php if (empty($ga_groups)) : ?>
        <div style="padding:40px;text-align:center;color:var(--c-muted);font-size:13px;">
            No Analytics data yet. Add URLs via the
            <strong>Integrations → Sitemap</strong> tab, then use the <strong>Overview</strong> tab to fetch data.
        </div>
        <?php else : ?>

        <!-- Tab bar: Overview + per-type tabs -->
        <div id="ga-type-tabs" style="display:flex;flex-wrap:wrap;gap:0;border-bottom:2px solid var(--c-border);padding:0 20px;background:var(--c-surf2);">
            <!-- Overview always first -->
            <button class="ga-type-tab ga-type-tab-active" data-gtype="__overview__"
                    style="padding:10px 16px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;
                           border-bottom:3px solid var(--c-primary);margin-bottom:-2px;color:var(--c-primary);white-space:nowrap;">
                 Overview
            </button>
            <?php foreach ($ga_groups as $gtype => $grows) : ?>
            <button class="ga-type-tab"
                    data-gtype="<?php echo esc_attr($gtype); ?>"
                    style="padding:10px 16px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;
                           border-bottom:3px solid transparent;margin-bottom:-2px;color:var(--c-muted);white-space:nowrap;">
                <?php echo esc_html(ucfirst($gtype)); ?>
                <span style="font-size:10px;opacity:.7;"> (<?php echo count($grows); ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- OVERVIEW PANEL -->
        <div class="ga-type-panel" data-gtype="__overview__" style="display:block;">
            <div style="padding:20px 24px;display:flex;flex-direction:column;gap:20px;">

                <!-- ── Section 1: Date Range ── -->
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:var(--r);padding:18px 20px;">
                    <div style="font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--c-muted);margin-bottom:12px;">📅 Date Range</div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                        <button class="ga-ov-preset seo-btn seo-btn-ghost seo-btn-sm" data-days="7"
                                style="border-radius:20px;">Last 7 Days</button>
                        <button class="ga-ov-preset seo-btn seo-btn-ghost seo-btn-sm" data-days="30"
                                style="border-radius:20px;">Last 30 Days</button>
                        <button class="ga-ov-preset seo-btn seo-btn-ghost seo-btn-sm" data-days="90"
                                style="border-radius:20px;">Last 90 Days</button>
                        <button class="ga-ov-preset seo-btn seo-btn-ghost seo-btn-sm" data-days="0"
                                style="border-radius:20px;">Custom</button>
                    </div>
                    <div id="ga-ov-date-row" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                        <div class="seo-field" style="margin:0;">
                            <label style="font-size:11px;">From</label>
                            <input type="date" id="ga-ov-from" class="seo-in seo-in-sm">
                        </div>
                        <div class="seo-field" style="margin:0;">
                            <label style="font-size:11px;">To</label>
                            <input type="date" id="ga-ov-to" class="seo-in seo-in-sm">
                        </div>
                        <div class="seo-field" style="margin:0;">
                            <label style="font-size:11px;">Month key</label>
                            <input type="month" id="ga-ov-month" class="seo-in seo-in-sm" value="<?php echo date('Y-m'); ?>">
                        </div>
                    </div>
                    <div id="ga-ov-preset-label" style="font-size:11px;color:var(--c-muted);margin-top:8px;"></div>
                </div>

                <!-- ── Section 1.5: Metrics to Fetch ── -->
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:var(--r);padding:18px 20px;">
                    <div style="font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--c-muted);margin-bottom:12px;">📊 METRICS TO FETCH</div>
                    <p style="font-size:12px;color:var(--c-muted);margin:0 0 12px;">GA4 fetches Active Users, Sessions, and Page Views for each tracked URL.</p>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border-radius:8px;border:1px solid #d8b4fe;background:#f3e8ff;transition:all .15s;">
                            <input type="checkbox" class="ga-metric-chk" value="activeUsers" checked style="width:15px;height:15px;accent-color:#22c55e;">
                            <span style="font-size:12px;font-weight:700;color:#334155;">👥 Active Users</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border-radius:8px;border:1px solid #d8b4fe;background:#f3e8ff;transition:all .15s;">
                            <input type="checkbox" class="ga-metric-chk" value="sessions" checked style="width:15px;height:15px;accent-color:#22c55e;">
                            <span style="font-size:12px;font-weight:700;color:#334155;">🔄 Sessions</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border-radius:8px;border:1px solid #d8b4fe;background:#f3e8ff;transition:all .15s;">
                            <input type="checkbox" class="ga-metric-chk" value="pageviews" checked style="width:15px;height:15px;accent-color:#22c55e;">
                            <span style="font-size:12px;font-weight:700;color:#334155;">📄 Page Views</span>
                        </label>
                    </div>
                </div>

                <!-- ── Section 2: URL Selection ── -->
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:var(--r);padding:18px 20px;">
                    <div style="font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--c-muted);margin-bottom:14px;">🔗 URL Selection</div>

                    <!-- Mode toggle -->
                    <div style="display:flex;background:var(--c-surf);border:1px solid var(--c-border);border-radius:20px;padding:2px;width:fit-content;margin-bottom:18px;">
                        <button id="ga-mode-auto" class="ga-ov-mode-btn seo-btn seo-btn-sm"
                                style="border-radius:18px;background:var(--c-primary);color:#fff;border:none;padding:5px 16px;font-size:12px;font-weight:700;">
                            ⚡ Auto by Type
                        </button>
                        <button id="ga-mode-manual" class="ga-ov-mode-btn seo-btn seo-btn-sm"
                                style="border-radius:18px;background:none;color:var(--c-muted);border:none;padding:5px 16px;font-size:12px;font-weight:700;cursor:pointer;">
                            ✋ Select Manually
                        </button>
                    </div>

                    <!-- AUTO section: type checkboxes -->
                    <div id="ga-sel-auto">
                        <p style="font-size:12px;color:var(--c-muted);margin:0 0 12px;">Select content types — all URLs in those types will be fetched:</p>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                            <?php foreach ($ga_groups as $gtype => $grows) :
                                $colors = ['post'=>'#6366f1','page'=>'#0ea5e9','city'=>'#10b981','other'=>'#94a3b8',
                                           'product'=>'#f59e0b','category'=>'#8b5cf6','service'=>'#ec4899',
                                           'tag'=>'#84cc16','author'=>'#f97316','portfolio'=>'#06b6d4'];
                                $col = $colors[$gtype] ?? '#6366f1';
                            ?>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                                          padding:8px 14px;border-radius:20px;
                                          border:2px solid <?php echo $col; ?>33;
                                          background:<?php echo $col; ?>11;
                                          transition:all .15s;" class="ga-type-badge-chk-wrap">
                                <input type="checkbox" class="ga-type-auto-chk"
                                       data-gtype="<?php echo esc_attr($gtype); ?>"
                                       style="width:15px;height:15px;accent-color:<?php echo $col; ?>;">
                                <span style="font-size:12px;font-weight:700;color:<?php echo $col; ?>;">
                                    <?php echo esc_html(ucfirst($gtype)); ?>
                                </span>
                                <span style="font-size:11px;color:var(--c-muted);opacity:.8;">(<?php echo count($grows); ?>)</span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- MANUAL section: grouped URL list -->
                    <div id="ga-sel-manual" style="display:none;">
                        <p style="font-size:12px;color:var(--c-muted);margin:0 0 12px;">Select individual URLs to fetch data for:</p>
                        <?php foreach ($ga_groups as $gtype => $grows) :
                            $colors = ['post'=>'#6366f1','page'=>'#0ea5e9','city'=>'#10b981','other'=>'#94a3b8',
                                       'product'=>'#f59e0b','category'=>'#8b5cf6','service'=>'#ec4899',
                                       'tag'=>'#84cc16','author'=>'#f97316','portfolio'=>'#06b6d4'];
                            $col = $colors[$gtype] ?? '#6366f1';
                        ?>
                        <div style="margin-bottom:12px;border:1px solid var(--c-border);border-radius:var(--r);overflow:hidden;">
                            <!-- Type group header -->
                            <div style="padding:10px 14px;background:var(--c-surf2);display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--c-border);">
                                <input type="checkbox" class="ga-manual-type-all" data-gtype="<?php echo esc_attr($gtype); ?>"
                                       style="width:15px;height:15px;accent-color:<?php echo $col; ?>;">
                                <span style="font-size:12px;font-weight:700;color:<?php echo $col; ?>;"><?php echo esc_html(ucfirst($gtype)); ?></span>
                                <span style="font-size:11px;opacity:.6;color:var(--c-muted);">(<?php echo count($grows); ?> URLs)</span>
                            </div>
                            <!-- URL rows -->
                            <div style="max-height:200px;overflow-y:auto;">
                                <?php foreach ($grows as $row) : ?>
                                <label style="display:flex;align-items:center;gap:8px;padding:6px 14px;cursor:pointer;
                                              border-bottom:1px solid var(--c-border);transition:background .1s;"
                                       class="ga-manual-url-row"
                                       onmouseover="this.style.background='var(--c-surf2)'" onmouseout="this.style.background=''">
                                    <input type="checkbox" class="ga-url-manual-chk"
                                           data-gtype="<?php echo esc_attr($gtype); ?>"
                                           value="<?php echo esc_attr($row['page_url']); ?>"
                                           style="width:13px;height:13px;accent-color:<?php echo $col; ?>;">
                                    <span style="font-size:11px;color:var(--c-text);word-break:break-all;"><?php echo esc_html($row['page_url']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── Section 3: Fetch ── -->
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                    <div id="ga-ov-sel-count"
                         style="font-size:13px;font-weight:700;color:var(--c-muted);min-width:120px;">
                        0 URLs selected
                    </div>
                    <button id="ga-ov-fetch-btn"
                            data-rid="<?php echo $rid; ?>"
                            class="seo-btn seo-btn-primary"
                            disabled
                            style="min-width:200px;gap:6px;">
                        📊 Fetch Analytics Data
                    </button>
                    <div id="ga-ov-fetch-status" style="font-size:12px;min-width:180px;"></div>
                </div>

                <!-- ── Section 4: Auto-Fetch All Periods ── -->
                <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:2px solid #86efac;
                            border-radius:var(--r);padding:20px;">
                    <div style="font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
                                color:#166534;margin-bottom:6px;">⚡ Auto-Fetch All Periods → Database</div>
                    <p style="font-size:12px;color:#15803d;margin:0 0 16px;">Fetches all Pages &amp; Blogs across
                        <strong>7 Days · 30 Days · 90 Days · Overall</strong> and saves each period
                        directly to the Database in one click.</p>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <button class="seo-all-periods-btn" data-filter="all" data-target="ga"
                                data-rid="<?php echo $rid; ?>"
                                style="display:inline-flex;align-items:center;gap:8px;
                                       padding:10px 22px;border-radius:8px;font-size:13px;font-weight:800;
                                       background:#16a34a;color:#fff;border:none;cursor:pointer;
                                       box-shadow:0 2px 8px #16a34a55;transition:all .15s;">
                            ⚡ Fetch All &amp; Save to DB
                        </button>
                        <button class="seo-all-periods-btn" data-filter="service" data-target="service"
                                data-rid="<?php echo $rid; ?>"
                                style="display:inline-flex;align-items:center;gap:8px;
                                       padding:10px 22px;border-radius:8px;font-size:13px;font-weight:800;
                                       background:#6366f1;color:#fff;border:none;cursor:pointer;
                                       box-shadow:0 2px 8px #6366f155;transition:all .15s;">
                            📄 Pages Only
                        </button>
                        <button class="seo-all-periods-btn" data-filter="blog" data-target="blog"
                                data-rid="<?php echo $rid; ?>"
                                style="display:inline-flex;align-items:center;gap:8px;
                                       padding:10px 22px;border-radius:8px;font-size:13px;font-weight:800;
                                       background:#0ea5e9;color:#fff;border:none;cursor:pointer;
                                       box-shadow:0 2px 8px #0ea5e955;transition:all .15s;">
                            📍 Blogs Only
                        </button>
                    </div>
                    <div id="ga-all-periods-status" style="font-size:12px;color:#166534;margin-top:12px;min-height:18px;"></div>
                </div>

            </div>
        </div><!-- /.ga-type-panel[__overview__] -->

        <!-- Month filter row (shown only when a content-type tab is active) -->
        <div id="ga-type-month-bar" style="display:none;padding:12px 20px;gap:10px;align-items:center;border-bottom:1px solid var(--c-border);">
            <select class="seo-month-sel" id="seo-ga-month-filter" data-rid="<?php echo $rid; ?>" data-scope="ga">
                <option value="">All months</option>
                <?php foreach ($months as $m) : ?>
                <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html(date_i18n('F Y', strtotime($m.'-01'))); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="seo-btn seo-btn-ghost seo-btn-xs seo-export-csv-btn" data-table="seo-ga-tbl-active">⬇️ CSV</button>
        </div>

        <!-- Bulk action toolbar for GA (hidden until rows selected) -->
        <div id="ga-bulk-bar" style="display:none;padding:10px 20px;background:var(--c-surf2);border-bottom:1px solid var(--c-border);display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span id="ga-bulk-count" style="font-size:12px;font-weight:700;color:var(--c-text);min-width:80px;">0 selected</span>
            <button class="seo-btn seo-btn-ghost seo-btn-sm seo-ga-bulk-btn" data-action="trash" style="border-color:var(--c-yellow);color:var(--c-yellow);">🗑️ Move to Trash</button>
            <button class="seo-btn seo-btn-danger seo-btn-sm seo-ga-bulk-btn" data-action="delete">✕ Delete Permanently</button>
            <button class="seo-btn seo-btn-ghost seo-btn-sm" id="ga-bulk-clear" style="margin-left:auto;">✕ Clear Selection</button>
        </div>

        <!-- Clients Table -->
        <table class="seo-table" id="seo-clients-full-tbl" style="min-width:960px;">
            <thead style="position:sticky;top:0;z-index:2;background:var(--c-surf2);">
                <tr>
                    <th style="width:36px;padding:10px 12px;">
                        <input type="checkbox" id="seo-select-all-clients" style="accent-color:var(--c-primary);width:15px;height:15px;" title="Select all">
                    </th>
                    <th style="padding:10px 12px;">#</th>
                    <th style="padding:10px 12px;">Client Name</th>
                    <th style="padding:10px 12px;">Email</th>
                    <th style="padding:10px 12px;">Password</th>
                    <th style="padding:10px 12px;">Dashboard</th>
                    <th style="padding:10px 12px;">Assigned</th>
                    <th style="padding:10px 12px;">Permissions</th>
                    <th style="padding:10px 12px;text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($all_clients as $idx => $c) :
                $cid         = intval($c['id']);
                $is_assigned = in_array($cid, $assigned_client_ids, false);
                $pwd_val     = '';
                $has_pwd     = false;
            ?>
                <tr data-id="<?php echo $cid; ?>">
                    <td style="padding:10px 12px;"><input type="checkbox" class="seo-client-chk" value="<?php echo $cid; ?>"></td>
                    <td style="padding:10px 12px;"><?php echo $idx + 1; ?></td>
                    <td style="padding:10px 12px;"><input type="text" class="seo-in seo-client-edit" data-field="name" value="<?php echo esc_attr($c['name'] ?? ''); ?>" disabled></td>
                    <td style="padding:10px 12px;"><input type="text" class="seo-in seo-client-edit" data-field="email" value="<?php echo esc_attr($c['email'] ?? ''); ?>" disabled></td>
                    <td style="padding:10px 12px;">
                        <div style="display:flex;align-items:center;gap:5px;">
                            <input type="password" class="seo-in seo-client-edit" data-field="password" value="" disabled placeholder="••••••••">
                        </div>
                    </td>
                    <td style="padding:10px 12px;"><input type="text" class="seo-in seo-client-edit" data-field="dashboard" value="<?php echo esc_attr($c['dashboard'] ?? ''); ?>" disabled></td>
                    <td style="padding:10px 12px;"><?php echo $is_assigned ? '✅' : '❌'; ?></td>
                    <td style="padding:10px 12px;"><input type="text" class="seo-in seo-client-edit" data-field="permissions" value="<?php echo esc_attr($c['permissions'] ?? ''); ?>" disabled></td>
                    <td style="padding:10px 12px;text-align:center;">
                        <button class="seo-btn seo-btn-sm seo-client-edit-btn">Edit</button>
                        <button class="seo-btn seo-btn-primary seo-btn-sm seo-client-save-btn" style="display:none;">Save</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- One scrollable table panel per type -->
        <?php foreach ($ga_groups as $gtype => $grows) : ?>
        <div class="ga-type-panel" data-gtype="<?php echo esc_attr($gtype); ?>" style="display:none;">
            <div class="seo-table-wrap" style="max-height:520px;overflow-y:auto;">
                <table class="seo-table ga-type-tbl" id="seo-ga-tbl-<?php echo esc_attr($gtype); ?>">
                    <thead style="position:sticky;top:0;z-index:2;background:var(--c-surf2);">
                        <tr>
                            <th style="width:34px;padding:8px 10px;">
                                <input type="checkbox" class="ga-select-all-chk" title="Select all"
                                       data-gtype="<?php echo esc_attr($gtype); ?>"
                                       style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                            </th>
                            <th>Page URL</th>
                            <th style="text-align:right;">Sessions</th>
                            <th style="text-align:right;">Users</th>
                            <th style="text-align:right;">Pageviews</th>
                            <th>Month</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($grows as $row) : ?>
                    <tr data-month="<?php echo esc_attr($row['month_key']??''); ?>" data-id="<?php echo intval($row['id']); ?>">
                        <td style="padding:8px 10px;">
                            <input type="checkbox" class="ga-row-chk"
                                   value="<?php echo intval($row['id']); ?>"
                                   data-gtype="<?php echo esc_attr($gtype); ?>"
                                   style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                        </td>
                        <td style="font-size:12px;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="<?php echo esc_attr($row['page_url']); ?>">
                            <a href="<?php echo esc_url($row['page_url']); ?>" target="_blank"
                               style="color:var(--c-primary);text-decoration:none;"><?php echo esc_html($row['page_url']); ?></a>
                        </td>
                        <td style="text-align:right;"><?php echo number_format(intval($row['sessions'])); ?></td>
                        <td style="text-align:right;"><?php echo number_format(intval($row['users'])); ?></td>
                        <td style="text-align:right;"><?php echo number_format(intval($row['pageviews'])); ?></td>
                        <td style="font-size:11px;color:var(--c-subtle);"><?php echo esc_html($row['month_key']??''); ?></td>
                        <td><button class="seo-icon-btn seo-icon-btn-d seo-del-row-btn" data-table="ga" data-id="<?php echo intval($row['id']); ?>">🗑️</button></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>

<?php // ── SEARCH CONSOLE ────────────────────────────────────────────────────────────
elseif ($tab === 'sc') :
    $months  = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_sc, $rid);
    $sc_rows = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_sc, $rid);

    // Load the sitemap-push url→type map for SC
    $sc_type_map = get_option( "seo_dash_sitemap_types_{$rid}_sc", [] );
    if ( ! is_array( $sc_type_map ) ) $sc_type_map = [];

    function seo_dash_sc_type_v2(array $row, array $map): string {
        $url = trim($row['page_url'] ?? '');
        if ($url) {
            if (isset($map[$url])) return $map[$url];
            if (isset($map[trailingslashit($url)])) return $map[trailingslashit($url)];
            if (isset($map[untrailingslashit($url)])) return $map[untrailingslashit($url)];
            $path = parse_url($url, PHP_URL_PATH);
            if ($path) {
                if (isset($map[$path])) return $map[$path];
                if (isset($map[trailingslashit($path)])) return $map[trailingslashit($path)];
                if (isset($map[untrailingslashit($path)])) return $map[untrailingslashit($path)];
            }
        }
        $q = trim($row['query'] ?? '');
        if ($q) {
            if (preg_match('/^\[sitemap:([a-z0-9_-]+)\]/i', $q, $m)) return strtolower($m[1]);
            if (preg_match('/^\[([a-z0-9_-]+)\]$/i', $q, $m)) return strtolower($m[1]);
        }
        return 'other';
    }
    $sc_groups = [];
    $excluded_sc_types_rd = [ 'gmb_posts', 'gmb_post' ];
    foreach ($sc_rows as $row) {
        $t = seo_dash_sc_type_v2($row, $sc_type_map);
        if ( in_array( $t, $excluded_sc_types_rd, true ) ) continue;
        $sc_groups[$t][] = $row;
    }
    ksort($sc_groups);
    $sc_first_type = !empty($sc_groups) ? array_key_first($sc_groups) : '';
?>
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2>🔍 Search Console</h2>
            <div style="display:flex;gap:8px;">
                <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-sc-fetch-toggle" data-rid="<?php echo $rid; ?>">🔄 Fetch from SC</button>
                <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-sc-manual-btn">＋ Manual Import</button>
            </div>
        </div>

        <?php if (empty($sc_groups)) : ?>
        <div style="padding:40px;text-align:center;color:var(--c-muted);font-size:13px;">
            No Search Console data yet. Use <strong>Fetch from SC</strong>, <strong>Manual Import</strong>, or add URLs via the
            <strong>Integrations → Sitemap</strong> tab.
        </div>
        <?php else : ?>

        <!-- Type sub-tab bar -->
        <div id="sc-type-tabs" style="display:flex;flex-wrap:wrap;gap:0;border-bottom:2px solid var(--c-border);padding:0 20px;background:var(--c-surf2);">
            <?php foreach ($sc_groups as $sctype => $scrows) : ?>
            <button class="sc-type-tab<?php echo $sctype===$sc_first_type?' sc-type-tab-active':''; ?>"
                    data-sctype="<?php echo esc_attr($sctype); ?>"
                    style="padding:10px 16px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;
                           border-bottom:3px solid <?php echo $sctype===$sc_first_type?'var(--c-primary)':'transparent'; ?>;
                           margin-bottom:-2px;color:<?php echo $sctype===$sc_first_type?'var(--c-primary)':'var(--c-muted)'; ?>;white-space:nowrap;">
                <?php echo esc_html(ucfirst($sctype)); ?>
                <span style="font-size:10px;opacity:.7;"> (<?php echo count($scrows); ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Month filter row -->
        <div style="padding:12px 20px;display:flex;gap:10px;align-items:center;border-bottom:1px solid var(--c-border);">
            <select class="seo-month-sel" id="seo-sc-month-filter" data-rid="<?php echo $rid; ?>" data-scope="sc">
                <option value="">All months</option>
                <?php foreach ($months as $m) : ?>
                <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html(date_i18n('F Y', strtotime($m.'-01'))); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="seo-btn seo-btn-ghost seo-btn-xs seo-export-csv-btn" data-table="seo-sc-tbl-active">⬇️ CSV</button>
        </div>

        <!-- Bulk action toolbar for SC -->
        <div id="sc-bulk-bar" style="display:none;padding:10px 20px;background:var(--c-surf2);border-bottom:1px solid var(--c-border);display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span id="sc-bulk-count" style="font-size:12px;font-weight:700;color:var(--c-text);min-width:80px;">0 selected</span>
            <button class="seo-btn seo-btn-ghost seo-btn-sm seo-sc-bulk-btn" data-action="trash" style="border-color:var(--c-yellow);color:var(--c-yellow);">🗑️ Move to Trash</button>
            <button class="seo-btn seo-btn-danger seo-btn-sm seo-sc-bulk-btn" data-action="delete">✕ Delete Permanently</button>
            <button class="seo-btn seo-btn-ghost seo-btn-sm" id="sc-bulk-clear" style="margin-left:auto;">✕ Clear Selection</button>
        </div>

        <!-- One scrollable table panel per type -->
        <?php foreach ($sc_groups as $sctype => $scrows) : ?>
        <div class="sc-type-panel" data-sctype="<?php echo esc_attr($sctype); ?>"
             style="display:<?php echo $sctype===$sc_first_type?'block':'none'; ?>;">
            <div class="seo-table-wrap" style="max-height:520px;overflow-y:auto;">
                <table class="seo-table sc-type-tbl" id="seo-sc-tbl-<?php echo esc_attr($sctype); ?>">
                    <thead style="position:sticky;top:0;z-index:2;background:var(--c-surf2);">
                        <tr>
                            <th style="width:34px;padding:8px 10px;">
                                <input type="checkbox" class="sc-select-all-chk" title="Select all"
                                       data-sctype="<?php echo esc_attr($sctype); ?>"
                                       style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                            </th>
                            <th>Page URL</th>
                            <th style="text-align:right;">Clicks</th>
                            <th style="text-align:right;">Impressions</th>
                            <th style="text-align:right;">CTR</th>
                            <th style="text-align:right;">Position</th>
                            <th>Month</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($scrows as $row) : ?>
                    <tr data-month="<?php echo esc_attr($row['month_key']??''); ?>" data-id="<?php echo intval($row['id']); ?>">
                        <td style="padding:8px 10px;">
                            <input type="checkbox" class="sc-row-chk"
                                   value="<?php echo intval($row['id']); ?>"
                                   data-sctype="<?php echo esc_attr($sctype); ?>"
                                   style="accent-color:var(--c-primary);width:14px;height:14px;cursor:pointer;">
                        </td>
                        <td style="font-size:12px;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="<?php echo esc_attr($row['page_url']); ?>">
                            <a href="<?php echo esc_url($row['page_url']); ?>" target="_blank"
                               style="color:var(--c-primary);text-decoration:none;"><?php echo esc_html($row['page_url']); ?></a>
                        </td>
                        <td style="text-align:right;"><?php echo number_format(intval($row['clicks'])); ?></td>
                        <td style="text-align:right;"><?php echo number_format(intval($row['impressions'])); ?></td>
                        <td style="text-align:right;"><?php echo number_format(floatval($row['ctr']),2); ?>%</td>
                        <td style="text-align:right;"><?php echo number_format(floatval($row['position']),1); ?></td>
                        <td style="font-size:11px;color:var(--c-subtle);"><?php echo esc_html($row['month_key']??''); ?></td>
                        <td><button class="seo-icon-btn seo-icon-btn-d seo-del-row-btn" data-table="sc" data-id="<?php echo intval($row['id']); ?>">🗑️</button></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>

<?php // ── SERVICE PAGES ────────────────────────────────────────────────────
elseif ($tab === 'service') :
    $months = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_pages, $rid);
?>
    <?php echo seo_dash_pages_panel_html($rid,'service','Service Pages',$months); ?>

<?php // ── BLOG POSTS ───────────────────────────────────────────────────────
elseif ($tab === 'blog') :
    $months = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_pages, $rid);
?>
    <?php echo seo_dash_pages_panel_html($rid,'blog','Blog Posts',$months); ?>

<?php // ── GOOGLE BUSINESS ─────────────────────────────────────────────────
elseif ($tab === 'gmb') :
    $months      = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_gmb, $rid);
    $gmb_details = get_option("seo_dash_gmb_details_{$rid}", []);
    if (!is_array($gmb_details)) $gmb_details = [];
    $gmb_hours   = $gmb_details['hours'] ?? [];
    $days_list   = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday'];
?>
        <div class="seo-panel">
        <div class="seo-panel-hd"><h2>📍 Google Business Profile</h2></div>

        <!-- BUSINESS DETAILS FORM -->
        <div style="border-bottom:2px solid var(--c-border);">

            <div id="gmb-details-hd" style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:var(--c-surf2);cursor:pointer;border-bottom:1px solid var(--c-border);user-select:none;" onclick="(function(){var b=document.getElementById('gmb-details-body');var open=b.style.display!=='none';b.style.display=open?'none':'block';document.querySelector('.gmb-chev').style.transform=open?'rotate(0deg)':'rotate(180deg)';})()">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:14px;font-weight:700;color:var(--c-primary);">🏢 BUSINESS DETAILS</span>
                    <?php if (!empty($gmb_details['updated_at'])): ?>
                    <span style="font-size:11px;color:var(--c-subtle);background:var(--c-surf);padding:2px 8px;border-radius:10px;border:1px solid var(--c-border);">Last saved: <?php echo esc_html($gmb_details['updated_at']); ?></span>
                    <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <button class="seo-btn seo-btn-primary seo-btn-sm" id="gmb-details-save-btn" data-rid="<?php echo intval($rid); ?>" onclick="event.stopPropagation();" style="height:30px;font-size:12px;">💾 Save Details</button>
                    <span class="gmb-chev" style="font-size:13px;color:var(--c-muted);transition:transform 0.25s;display:inline-block;">▼</span>
                </div>
            </div>

            <div id="gmb-details-body" style="display:block;background:var(--c-surf);">
                <div style="display:grid;grid-template-columns:220px 1fr;border-bottom:1px solid var(--c-border);">
                    <div style="padding:14px 20px;font-weight:600;font-size:13px;color:var(--c-text);background:var(--c-surf2);border-right:1px solid var(--c-border);display:flex;align-items:center;">Business Name</div>
                    <div style="padding:10px 20px;"><input type="text" id="gmb-d-name" class="seo-in" placeholder="e.g. Richmond Air" value="<?php echo esc_attr($gmb_details['business_name'] ?? ''); ?>" style="width:100%;max-width:580px;"></div>
                </div>
                <div style="display:grid;grid-template-columns:220px 1fr;border-bottom:1px solid var(--c-border);">
                    <div style="padding:14px 20px;font-weight:600;font-size:13px;color:var(--c-text);background:var(--c-surf2);border-right:1px solid var(--c-border);display:flex;align-items:center;">Current Business Address</div>
                    <div style="padding:10px 20px;"><input type="text" id="gmb-d-address" class="seo-in" placeholder="e.g. 4238 Oakley Ct, Richmond, VA 23223" value="<?php echo esc_attr($gmb_details['address'] ?? ''); ?>" style="width:100%;max-width:580px;"></div>
                </div>
                <div style="display:grid;grid-template-columns:220px 1fr;border-bottom:1px solid var(--c-border);">
                    <div style="padding:14px 20px;font-weight:600;font-size:13px;color:var(--c-text);background:var(--c-surf2);border-right:1px solid var(--c-border);display:flex;align-items:center;">Current Business Phone Number</div>
                    <div style="padding:10px 20px;"><input type="text" id="gmb-d-phone" class="seo-in" placeholder="e.g. (804) 277-4328" value="<?php echo esc_attr($gmb_details['phone'] ?? ''); ?>" style="width:100%;max-width:580px;"></div>
                </div>
                <div style="display:grid;grid-template-columns:220px 1fr;border-bottom:1px solid var(--c-border);">
                    <div style="padding:14px 20px;font-weight:600;font-size:13px;color:var(--c-text);background:var(--c-surf2);border-right:1px solid var(--c-border);">Hours of Operation</div>
                    <div style="padding:12px 20px;">
                        <div style="display:grid;gap:6px;max-width:520px;">
                            <?php foreach ($days_list as $day_key => $day_label):
                                $dh      = $gmb_hours[$day_key] ?? [];
                                $closed  = !empty($dh['closed']);
                                $open_t  = esc_attr($dh['open']  ?? '09:00');
                                $close_t = esc_attr($dh['close'] ?? '17:00');
                            ?>
                            <div class="gmb-hour-row" style="display:flex;align-items:center;gap:10px;padding:7px 12px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;">
                                <span style="width:95px;font-size:12px;font-weight:600;color:var(--c-text);"><?php echo $day_label; ?></span>
                                <label style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--c-muted);cursor:pointer;white-space:nowrap;">
                                    <input type="checkbox" class="gmb-closed-chk" data-day="<?php echo $day_key; ?>" <?php checked($closed); ?> style="width:14px;height:14px;accent-color:var(--c-primary);"> Closed
                                </label>
                                <div class="gmb-time-fields" style="display:flex;align-items:center;gap:8px;<?php echo $closed ? 'opacity:0.35;pointer-events:none;' : ''; ?>">
                                    <input type="time" class="seo-in seo-in-sm gmb-open-time" data-day="<?php echo $day_key; ?>" value="<?php echo $open_t; ?>" style="width:115px;font-size:12px;padding:4px 8px;">
                                    <span style="font-size:12px;color:var(--c-muted);">–</span>
                                    <input type="time" class="seo-in seo-in-sm gmb-close-time" data-day="<?php echo $day_key; ?>" value="<?php echo $close_t; ?>" style="width:115px;font-size:12px;padding:4px 8px;">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:220px 1fr;border-bottom:1px solid var(--c-border);">
                    <div style="padding:14px 20px;font-weight:600;font-size:13px;color:var(--c-text);background:var(--c-surf2);border-right:1px solid var(--c-border);display:flex;align-items:center;">Current Business URL</div>
                    <div style="padding:10px 20px;"><input type="url" id="gmb-d-website" class="seo-in" placeholder="https://example.com/" value="<?php echo esc_attr($gmb_details['website_url'] ?? ''); ?>" style="width:100%;max-width:580px;"></div>
                </div>
                <div style="display:grid;grid-template-columns:220px 1fr;border-bottom:1px solid var(--c-border);">
                    <div style="padding:14px 20px;font-weight:600;font-size:13px;color:var(--c-text);background:var(--c-surf2);border-right:1px solid var(--c-border);display:flex;align-items:center;">Primary Category</div>
                    <div style="padding:10px 20px;"><input type="text" id="gmb-d-category" class="seo-in" placeholder="e.g. HVAC" value="<?php echo esc_attr($gmb_details['primary_category'] ?? ''); ?>" style="width:100%;max-width:580px;"></div>
                </div>
                <div style="display:grid;grid-template-columns:220px 1fr;border-bottom:1px solid var(--c-border);">
                    <div style="padding:14px 20px;font-weight:600;font-size:13px;color:var(--c-text);background:var(--c-surf2);border-right:1px solid var(--c-border);">Business Description</div>
                    <div style="padding:10px 20px;"><textarea id="gmb-d-description" class="seo-in" rows="5" placeholder="Describe your business..." style="width:100%;max-width:580px;resize:vertical;font-size:13px;"><?php echo esc_textarea($gmb_details['description'] ?? ''); ?></textarea></div>
                </div>
                <div style="display:grid;grid-template-columns:220px 1fr;">
                    <div style="padding:14px 20px;font-weight:600;font-size:13px;color:var(--c-text);background:var(--c-surf2);border-right:1px solid var(--c-border);display:flex;align-items:center;">Google Business Profile URL</div>
                    <div style="padding:10px 20px;"><input type="url" id="gmb-d-profile-url" class="seo-in" placeholder="https://maps.app.goo.gl/..." value="<?php echo esc_attr($gmb_details['profile_url'] ?? ''); ?>" style="width:100%;max-width:580px;"></div>
                </div>
                <div style="padding:10px 20px;background:var(--c-surf2);border-top:1px solid var(--c-border);">
                    <span id="gmb-details-status" style="font-size:12px;"></span>
                </div>
            </div>
        </div>

        <!-- STATS OVERVIEW (MONTHLY) -->
        <div style="border-top:2px solid var(--c-border);margin-top:0;">

            <!-- KPI Cards -->
            <div style="padding:20px 20px 0 20px;display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
                <?php
                $gmb_stats = $wpdb->get_row($wpdb->prepare(
                    "SELECT SUM(calls) as t_calls, SUM(bookings) as t_bookings, SUM(clicks_directions) as t_dirs, SUM(clicks_website) as t_web
                     FROM ".SEO_Dash_Database::$data_gmb." WHERE report_id=%d AND trashed=0", $rid), ARRAY_A);
                $kpi_items = [
                    ['label'=>'Total Calls',     'val'=>intval($gmb_stats['t_calls']   ?? 0), 'icon'=>'📞', 'color'=>'--c-primary'],
                    ['label'=>'Total Bookings',  'val'=>intval($gmb_stats['t_bookings']?? 0), 'icon'=>'📅', 'color'=>'--c-green'],
                    ['label'=>'Directions',      'val'=>intval($gmb_stats['t_dirs']    ?? 0), 'icon'=>'🗺️', 'color'=>'--c-yellow'],
                    ['label'=>'Website Clicks',  'val'=>intval($gmb_stats['t_web']     ?? 0), 'icon'=>'🖱️', 'color'=>'--c-blue'],
                ];
                foreach ($kpi_items as $k): ?>
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid var(<?php echo $k['color']; ?>);">
                    <div style="font-size:20px;"><?php echo $k['icon']; ?></div>
                    <div style="font-size:22px;font-weight:700;color:var(--c-text);"><?php echo number_format($k['val']); ?></div>
                    <div style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;"><?php echo $k['label']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <!-- MONTHLY PERFORMANCE TABLE -->
        <div class="seo-pages-period-pane">
            <div class="seo-panel-hd" style="padding-top:16px;">
                <h2 style="font-size:14px;">📊 Monthly Performance</h2>
                <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-gmb-add-btn">＋ Add Month</button>
            </div>
            <div id="seo-gmb-form" style="display:none;background:var(--c-surf2);padding:16px 20px;border-bottom:1px solid var(--c-border);">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr auto;gap:10px;align-items:end;">
                    <div class="seo-field"><label>Month</label><input type="month" id="gmb-month" class="seo-in seo-in-sm" value="<?php echo date('Y-m'); ?>"></div>
                    <div class="seo-field"><label>Calls</label><input type="number" id="gmb-calls" class="seo-in seo-in-sm" value="0" min="0"></div>
                    <div class="seo-field"><label>Bookings</label><input type="number" id="gmb-bookings" class="seo-in seo-in-sm" value="0" min="0"></div>
                    <div class="seo-field"><label>Directions</label><input type="number" id="gmb-directions" class="seo-in seo-in-sm" value="0" min="0"></div>
                    <div class="seo-field"><label>Website Clicks</label><input type="number" id="gmb-clicks-w" class="seo-in seo-in-sm" value="0" min="0"></div>
                    <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-gmb-save-btn" data-rid="<?php echo $rid; ?>">Save</button>
                </div>
                <div style="margin-top:8px;"><span id="gmb-form-status" style="font-size:12px;"></span></div>
            </div>
            
            <!-- Toolbar above Monthly Performance -->
            <div style="padding:12px 20px;border-bottom:1px solid var(--c-border);background:var(--c-surf2);display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <select id="seo-gmb-bulk-sel" class="seo-in seo-in-sm" style="width:auto;padding:4px 8px;font-size:12px;">
                        <option value="">Bulk Actions</option>
                        <option value="trash">Move Selected to Trash</option>
                        <option value="restore">Restore Selected from Trash</option>
                        <option value="delete">Delete Permanently</option>
                    </select>
                    <button id="seo-gmb-bulk-btn" class="seo-btn seo-btn-ghost seo-btn-sm">Apply</button>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="text" class="seo-in seo-in-sm db-generic-search" data-target="seo-gmb-tbody" placeholder="Search..." style="width:140px;">
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="seo-gmb-table">⬇️ Export CSV</button>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="gmb">⬇️ Download Format</button>
                    <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">⬆️ Import CSV<input type="file" class="seo-import-csv-input" data-type="gmb" accept=".csv" style="display:none;"></label>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm db-generic-view-trash-btn" data-type="gmb">🗑️ View Trash</button>
                    <span style="display:inline-block;width:1px;height:20px;background:var(--c-border);margin:0 2px;"></span>
                    <div id="gsheet-sync-bar-gmb" style="display:flex;align-items:center;gap:6px;">
                        <?php $link = $gsheet_links['gmb'] ?? []; if ( !empty($link['spreadsheet_id']) ) : ?>
                        <span style="font-size:11px;color:var(--c-muted);white-space:nowrap;">📊 <?php echo esc_html( $link['spreadsheet_name'] ?? 'Linked Sheet' ); ?> / <?php echo esc_html( $link['tab_name'] ?? '' ); ?></span>
                        <button class="seo-btn seo-btn-sm gsheet-sync-btn" data-tabtype="gmb" style="height:28px;font-size:11px;background:var(--c-primary);color:#fff;">⬆ Update</button>
                        <button class="seo-btn seo-btn-sm gsheet-export-btn" data-tabtype="gmb" style="height:28px;font-size:11px;background:var(--c-surf);color:var(--c-primary);border:1px solid var(--c-primary);">⬇ Export to Sheet</button>
                        <span class="gsheet-sync-status" data-tabtype="gmb" style="font-size:11px;color:var(--c-muted);"></span>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--c-muted);">⚠️ No sheet linked. Go to Integrations tab.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="seo-table-wrap" style="overflow-x:auto;">
                <table class="seo-table" id="seo-gmb-table" style="min-width:600px;">
                    <thead><tr>
                        <th style="width:36px;text-align:center;"><input type="checkbox" id="seo-gmb-check-all"></th>
                        <th style="width:36px;text-align:center;">#</th>
                        <th>Month</th><th style="text-align:right;">Calls</th><th style="text-align:right;">Bookings</th><th style="text-align:right;">Directions</th><th style="text-align:right;">Website Clicks</th><th style="width:50px;text-align:center;">Remove</th>
                    </tr></thead>
                    <tbody id="seo-gmb-tbody">
                        <?php
                        $all_gmb_months = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT month_key FROM ".SEO_Dash_Database::$data_gmb." WHERE report_id=%d ORDER BY month_key DESC", $rid));
                        $gmb_n = 0;
                        foreach ($all_gmb_months as $m) :
                            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".SEO_Dash_Database::$data_gmb." WHERE report_id=%d AND month_key=%s LIMIT 1",$rid,$m),ARRAY_A);
                            if (!$row) continue;
                            $is_trashed = !empty($row['trashed']);
                            if (!$is_trashed) $gmb_n++;
                        ?>
                        <tr class="<?php echo $is_trashed ? 'db-generic-trashed-row db-row-type-gmb' : 'db-generic-active-row db-row-type-gmb'; ?>" style="<?php echo $is_trashed ? 'display:none;background:rgba(239,68,68,0.15);' : ''; ?>">
                            <td style="text-align:center;"><input type="checkbox" class="seo-gmb-chk" value="<?php echo intval($row['id']); ?>"></td>
                            <td style="text-align:center;color:var(--c-muted);font-size:12px;"><?php echo $is_trashed ? '-' : $gmb_n; ?></td>
                            <td style="font-weight:600;"><?php echo esc_html(date_i18n('F Y',strtotime($m.'-01'))); ?></td>
                            <td style="text-align:right;"><input type="number" class="seo-in seo-gmb-inline-input" data-field="calls" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['calls']); ?>" style="width:100%;text-align:right;font-size:12px;padding:4px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.03);border-radius:4px;" onfocus="this.style.border='1px solid var(--c-primary)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid rgba(255,255,255,0.1)';this.style.background='rgba(255,255,255,0.03)';"></td>
                            <td style="text-align:right;"><input type="number" class="seo-in seo-gmb-inline-input" data-field="bookings" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['bookings'] ?? 0); ?>" style="width:100%;text-align:right;font-size:12px;padding:4px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.03);border-radius:4px;" onfocus="this.style.border='1px solid var(--c-primary)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid rgba(255,255,255,0.1)';this.style.background='rgba(255,255,255,0.03)';"></td>
                            <td style="text-align:right;"><input type="number" class="seo-in seo-gmb-inline-input" data-field="clicks_directions" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['clicks_directions']); ?>" style="width:100%;text-align:right;font-size:12px;padding:4px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.03);border-radius:4px;" onfocus="this.style.border='1px solid var(--c-primary)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid rgba(255,255,255,0.1)';this.style.background='rgba(255,255,255,0.03)';"></td>
                            <td style="text-align:right;"><input type="number" class="seo-in seo-gmb-inline-input" data-field="clicks_website" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['clicks_website']); ?>" style="width:100%;text-align:right;font-size:12px;padding:4px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.03);border-radius:4px;" onfocus="this.style.border='1px solid var(--c-primary)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid rgba(255,255,255,0.1)';this.style.background='rgba(255,255,255,0.03)';"></td>
                            <td style="text-align:center;display:flex;gap:4px;justify-content:center;">
                                <button class="seo-icon-btn seo-gmb-edit-btn" title="Edit row" onclick="jQuery(this).closest('tr').find('input[type=number]').first().focus();return false;">✏️</button>
                                <button class="seo-icon-btn seo-icon-btn-d seo-del-row-btn" data-table="gmb" data-id="<?php echo intval($row['id']); ?>">🗑️</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($all_gmb_months)) : ?>
                        <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--c-subtle);">No GMB data yet. Click "+ Add Month" to start.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- GMB POSTS SECTION -->
        <?php
        $gmb_posts_rows    = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_gmb_posts, $rid, '', true);
        $gmb_posts_active  = array_filter($gmb_posts_rows, function($p){ return empty($p['trashed']); });
        $gmb_posts_trashed = array_filter($gmb_posts_rows, function($p){ return !empty($p['trashed']); });
        ?>
        <div class="seo-pages-period-pane" style="margin-top:0;border-top:1px solid var(--c-border);">
            <div class="seo-panel-hd" style="padding-top:16px;">
                <h2 style="font-size:14px;">📝 GMB Posts</h2>
                <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-gmb-posts-add-btn">＋ Add Post</button>
            </div>
            <div id="seo-gmb-posts-form" style="display:none;background:var(--c-surf2);padding:16px 20px;border-bottom:1px solid var(--c-border);">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end;">
                    <div class="seo-field"><label>Post Title</label><input type="text" id="gmb-post-title" class="seo-in seo-in-sm" placeholder="e.g. Special Offer"></div>
                    <div class="seo-field"><label>Post URL</label><input type="url" id="gmb-post-url" class="seo-in seo-in-sm" placeholder="https://g.page/r/..."></div>
                    <div class="seo-field"><label>Month</label><input type="month" id="gmb-post-month" class="seo-in seo-in-sm" value="<?php echo date('Y-m'); ?>"></div>
                    <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-gmb-posts-save-btn" data-rid="<?php echo $rid; ?>">Save</button>
                </div>
                <div style="margin-top:8px;"><span id="gmb-posts-form-status" style="font-size:12px;"></span></div>
            </div>

            <!-- Toolbar -->
            <div style="padding:12px 20px;border-bottom:1px solid var(--c-border);background:var(--c-surf2);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <select id="seo-gmb-posts-bulk-sel" class="seo-in seo-in-sm" style="width:auto;padding:4px 8px;font-size:12px;">
                        <option value="">Bulk Actions</option>
                        <option value="trash">Move Selected to Trash</option>
                        <option value="restore">Restore Selected from Trash</option>
                        <option value="delete">Delete Permanently</option>
                    </select>
                    <button id="seo-gmb-posts-bulk-btn" class="seo-btn seo-btn-ghost seo-btn-sm">Apply</button>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="text" class="seo-in seo-in-sm db-generic-search" data-target="seo-gmb-posts-tbody" placeholder="Search..." style="width:140px;">
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="seo-gmb-posts-table">⬇️ Export CSV</button>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="gmb_posts">⬇️ Download Format</button>
                    <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">⬆️ Import CSV<input type="file" class="seo-import-csv-input" data-type="gmb_posts" accept=".csv" style="display:none;"></label>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm db-generic-view-trash-btn" data-type="gmb_posts">🗑️ View Trash</button>
                    <span style="display:inline-block;width:1px;height:20px;background:var(--c-border);margin:0 2px;"></span>
                    <div id="gsheet-sync-bar-gmb-posts" style="display:flex;align-items:center;gap:6px;">
                        <?php $link_gp = $gsheet_links['gmb_posts'] ?? []; if ( !empty($link_gp['spreadsheet_id']) ) : ?>
                        <span style="font-size:11px;color:var(--c-muted);white-space:nowrap;">📊 <?php echo esc_html( $link_gp['spreadsheet_name'] ?? 'Linked Sheet' ); ?> / <?php echo esc_html( $link_gp['tab_name'] ?? '' ); ?></span>
                        <button class="seo-btn seo-btn-sm gsheet-sync-btn" data-tabtype="gmb_posts" style="height:28px;font-size:11px;background:var(--c-primary);color:#fff;">⬆ Update</button>
                        <button class="seo-btn seo-btn-sm gsheet-export-btn" data-tabtype="gmb_posts" style="height:28px;font-size:11px;background:var(--c-surf);color:var(--c-primary);border:1px solid var(--c-primary);">⬇ Export to Sheet</button>
                        <span class="gsheet-sync-status" data-tabtype="gmb_posts" style="font-size:11px;color:var(--c-muted);"></span>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--c-muted);">⚠️ No sheet linked. Go to Integrations tab.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="seo-table-wrap" style="overflow-x:auto;">
                <table class="seo-table" id="seo-gmb-posts-table" style="min-width:600px;">
                    <thead><tr>
                        <th style="width:36px;text-align:center;"><input type="checkbox" id="seo-gmb-posts-check-all"></th>
                        <th style="width:36px;text-align:center;">#</th>
                        <th>Post Title</th>
                        <th>Post URL</th>
                        <th>Month</th>
                        <th style="width:80px;text-align:center;">Actions</th>
                    </tr></thead>
                    <tbody id="seo-gmb-posts-tbody">
                        <?php
                        $gp_n = 0;
                        foreach ($gmb_posts_rows as $gp_row) :
                            $gp_trashed = !empty($gp_row['trashed']);
                            if (!$gp_trashed) $gp_n++;
                            $gp_id = intval($gp_row['id']);
                        ?>
                        <tr class="<?php echo $gp_trashed ? 'db-generic-trashed-row db-row-type-gmb_posts' : 'db-generic-active-row db-row-type-gmb_posts'; ?>" style="<?php echo $gp_trashed ? 'display:none;background:rgba(239,68,68,0.15);' : ''; ?>">
                            <td style="text-align:center;"><input type="checkbox" class="seo-gmb-posts-chk" value="<?php echo $gp_id; ?>"></td>
                            <td style="text-align:center;color:var(--c-muted);font-size:12px;"><?php echo $gp_trashed ? '-' : $gp_n; ?></td>
                            <td style="font-weight:600;"><?php echo esc_html($gp_row['title'] ?? ''); ?></td>
                            <td style="font-size:12px;"><a href="<?php echo esc_url($gp_row['post_url'] ?? ''); ?>" target="_blank" style="color:var(--c-primary);word-break:break-all;"><?php echo esc_html($gp_row['post_url'] ?? ''); ?></a></td>
                            <td style="font-size:12px;"><?php echo esc_html($gp_row['month_key'] ?? ''); ?></td>
                            <td style="text-align:center;display:flex;gap:4px;justify-content:center;">
                                <button class="seo-icon-btn seo-icon-btn-d seo-gmb-posts-del-btn" data-rid="<?php echo $rid; ?>" data-id="<?php echo $gp_id; ?>">🗑️</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($gmb_posts_rows)) : ?>
                        <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--c-subtle);">No GMB posts yet. Click "＋ Add Post" or import a CSV.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
        // GMB Posts Section Scripts
        seoJQ(function($){
            // Toggle add form
            $('#seo-gmb-posts-add-btn').on('click', function(){
                $('#seo-gmb-posts-form').slideToggle(180);
            });

            // Save new post via AJAX
            $('#seo-gmb-posts-save-btn').on('click', function(){
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var $st  = $('#gmb-posts-form-status');
                var url  = $('#gmb-post-url').val().trim();
                var title = $('#gmb-post-title').val().trim();
                var month = $('#gmb-post-month').val();
                if (!url) { $st.css('color','var(--c-red)').text('Post URL is required.'); $btn.prop('disabled',false).text('Save'); return; }
                $.post(seoDash.ajax, {
                    action: 'seo_dash_save_gmb_post',
                    nonce: seoDash.nonce,
                    report_id: <?php echo intval($rid); ?>,
                    url: url,
                    title: title,
                    month: month,
                    status: 'Published'
                }, function(r){
                    $btn.prop('disabled',false).text('Save');
                    if (r.success) {
                        $st.css('color','var(--c-green)').text('Saved!');
                        setTimeout(function(){ location.reload(); }, 600);
                    } else {
                        $st.css('color','var(--c-red)').text((r.data && r.data.message) ? r.data.message : (r.data || 'Failed.'));
                    }
                }).fail(function(){ $btn.prop('disabled',false).text('Save'); $st.css('color','var(--c-red)').text('Network error.'); });
            });

            // Check all
            $('#seo-gmb-posts-check-all').on('change', function(){
                $('.seo-gmb-posts-chk').prop('checked', $(this).is(':checked'));
            });
            $(document).on('change', '.seo-gmb-posts-chk', function(){
                var allChecked = $('.seo-gmb-posts-chk:not(:checked)').length === 0;
                $('#seo-gmb-posts-check-all').prop('checked', allChecked);
            });

            // Delete single post (numeric DB id)
            $(document).on('click', '.seo-gmb-posts-del-btn', function(){
                if (!confirm('Move this post to trash?')) return;
                var $btn = $(this).prop('disabled', true);
                var id = parseInt($(this).data('id'), 10);
                $.post(seoDash.ajax, {
                    action: 'seo_dash_gmb_post_action',
                    nonce: seoDash.nonce,
                    report_id: <?php echo intval($rid); ?>,
                    bulk_action: 'trash',
                    ids: [id]
                }, function(r){
                    if (r.success) { location.reload(); }
                    else { alert((r.data && r.data.message) ? r.data.message : (r.data || 'Error')); $btn.prop('disabled', false); }
                }).fail(function(){ alert('Network error.'); $btn.prop('disabled', false); });
            });

            // Bulk apply
            $('#seo-gmb-posts-bulk-btn').on('click', function(e){
                e.preventDefault();
                var action = $('#seo-gmb-posts-bulk-sel').val();
                if (!action) { alert('Please select a bulk action.'); return; }
                var ids = [];
                $('.seo-gmb-posts-chk:checked').each(function(){ ids.push(parseInt($(this).val(), 10)); });
                if (ids.length === 0) { alert('Please select at least one row.'); return; }
                if (!confirm('Are you sure you want to apply this action to ' + ids.length + ' item(s)?')) return;
                var $btn = $(this).prop('disabled', true).text('...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_gmb_post_action',
                    nonce: seoDash.nonce,
                    report_id: <?php echo intval($rid); ?>,
                    bulk_action: action,
                    ids: ids
                }, function(r){
                    if (r.success) { location.reload(); }
                    else { alert((r.data && r.data.message) ? r.data.message : (r.data || 'Error')); $btn.prop('disabled', false).text('Apply'); }
                }).fail(function(){ alert('Network error.'); $btn.prop('disabled', false).text('Apply'); });
            });

            // View trash toggle (reuse generic handler)
            // The db-generic-view-trash-btn handler is already global, so it works automatically.
        });
        </script>

        <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
        // Nuclear Fix for GMB and GMB Posts Bulk Selectors
        seoJQ(function($){
            // GMB Table Selection
            $('#seo-gmb-check-all').on('change', function(){
                $('.seo-gmb-chk').prop('checked', $(this).is(':checked'));
            });
            $('.seo-gmb-chk').on('change', function(){
                var allChecked = $('.seo-gmb-chk:not(:checked)').length === 0;
                $('#seo-gmb-check-all').prop('checked', allChecked);
            });
            
            // GMB Bulk Apply
            $('#seo-gmb-bulk-btn').on('click', function(e){
                e.preventDefault();
                var action = $('#seo-gmb-bulk-sel').val();
                if(!action){ alert('Please select a bulk action.'); return; }
                var ids = [];
                $('.seo-gmb-chk:checked').each(function(){ ids.push($(this).val()); });
                if(ids.length === 0){ alert('Please select at least one row.'); return; }
                if(!confirm('Are you sure you want to apply this action to ' + ids.length + ' item(s)?')) return;
                
                var $btn = $(this).prop('disabled', true).text('...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_bulk_data_action',
                    nonce: seoDash.nonce,
                    table: 'gmb',
                    report_id: <?php echo intval($rid); ?>,
                    bulk_action: action,
                    ids: ids
                }, function(r){
                    if(r.success) { location.reload(); }
                    else { alert(seoGaErrMsg(r)); $btn.prop('disabled', false).text('Apply'); }
                }).fail(function(){ alert('Network error.'); $btn.prop('disabled', false).text('Apply'); });
            });

        });
        </script>
        

        <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
        seoJQ(function($){
            // GMB Monthly Performance script
            $('#seo-gmb-add-btn').on('click', function(){
                $('#seo-gmb-form').slideToggle(180);
            });

            $('#seo-gmb-save-btn').on('click', function(){
                var $btn = $(this).prop('disabled',true).text('Saving...');
                var $st  = $('#gmb-form-status');
                $.post(seoDash.ajax, {
                    action:            'seo_dash_save_gmb',
                    nonce:             seoDash.nonce,
                    report_id:         <?php echo intval($rid); ?>,
                    month_key:         $('#gmb-month').val(),
                    calls:             $('#gmb-calls').val(),
                    bookings:          $('#gmb-bookings').val(),
                    clicks_directions: $('#gmb-directions').val(),
                    clicks_website:    $('#gmb-clicks-w').val(),
                    views_search:      0,
                    views_maps:        0
                }, function(r){
                    $btn.prop('disabled',false).text('Save');
                    if (r.success) {
                        $st.css('color','var(--c-green)').text('Saved!');
                        setTimeout(function(){ location.reload(); }, 600);
                    } else {
                        $st.css('color','var(--c-red)').text(seoGaErrMsg(r));
                    }
                }).fail(function(){
                    $btn.prop('disabled',false).text('Save');
                    $st.css('color','var(--c-red)').text('Network error.');
                });
            });
            
            // GMB Inline Input Save
            $(document).on('change', '.seo-gmb-inline-input', function(){
                var $el = $(this);
                $.post(seoDash.ajax, {
                    action: 'seo_dash_save_gmb_field',
                    nonce: seoDash.nonce,
                    row_id: $el.data('id'),
                    field: $el.data('field'),
                    val: $el.val()
                }, function(r){
                    if (!r.success) alert(seoGaErrMsg(r));
                });
            });




            // Custom search script for db-generic and custom-page tables
            $(document).on('keyup', '.db-generic-search', function(){
                var val = $(this).val().toLowerCase();
                var target = $(this).data('target');
                $('#' + target + ' tr.db-generic-active-row').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
                });
            });
            $(document).on('keyup', '.seo-custom-page-search', function(){
                var val = $(this).val().toLowerCase();
                var target = $(this).data('target');
                $('#' + target + ' tbody tr.seo-custom-page-active-row').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
                });
            });
        });
        </script>
    </div>

    <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
    seoJQ(function($){
        $(document).on('change', '.gmb-closed-chk', function(){
            var $tf = $(this).closest('.gmb-hour-row').find('.gmb-time-fields');
            $tf.css($(this).is(':checked') ? {opacity:'0.35','pointer-events':'none'} : {opacity:'1','pointer-events':'auto'});
        });
        $('#gmb-details-save-btn').on('click', function(){
            var $btn = $(this).prop('disabled',true).text('Saving...');
            var hours = {};
            $('.gmb-hour-row').each(function(){
                var day = $(this).find('.gmb-closed-chk').data('day');
                hours[day] = { closed: $(this).find('.gmb-closed-chk').is(':checked') ? 1 : 0, open: $(this).find('.gmb-open-time').val(), close: $(this).find('.gmb-close-time').val() };
            });
            $.post(seoDash.ajax, {
                action: 'seo_dash_save_gmb_details', nonce: seoDash.nonce, report_id: <?php echo intval($rid); ?>,
                business_name: $('#gmb-d-name').val(), address: $('#gmb-d-address').val(), phone: $('#gmb-d-phone').val(),
                website_url: $('#gmb-d-website').val(), primary_category: $('#gmb-d-category').val(),
                description: $('#gmb-d-description').val(), profile_url: $('#gmb-d-profile-url').val(), hours: hours
            }, function(r){
                $btn.prop('disabled',false).text('💾 Save Details');
                var $s = $('#gmb-details-status');
                if (r.success) { $s.css('color','var(--c-green)').text('✅ Saved!'); if(typeof seoToast==='function') seoToast('Business details saved!','ok'); }
                else { $s.css('color','var(--c-red)').text('❌ ' + (r.data||'Save failed.')); }
                setTimeout(function(){ $s.text(''); }, 4000);
            }).fail(function(){ $btn.prop('disabled',false).text('💾 Save Details'); $('#gmb-details-status').css('color','var(--c-red)').text('❌ Network error.'); });
        });
    });
    </script>
<?php // ── TECHNICAL ────────────────────────────────────────────────────────
elseif ($tab === 'technical') :
    $months = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_technical, $rid);
    $tech_speed = get_option("seo_dash_tech_speed_{$rid}", []);
?>
    <!-- PageSpeed Insights Auto Run -->
    <div style="background:rgba(139,92,246,0.05); border:1px solid var(--c-primary); border-radius:8px; padding:20px; margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:8px;color:var(--c-primary);font-weight:700;font-size:13px;text-transform:uppercase;margin-bottom:10px;">
            ⚡ PAGESPEED INSIGHTS — AUTO RUN
        </div>
        <p style="color:var(--c-primary);opacity:0.8;font-size:13px;margin:0 0 16px;">Enter a URL below and click Run to automatically fetch live PageSpeed scores from Google. Results auto-fill the Mobile & Desktop speed fields below.</p>
        
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;flex-wrap:wrap;">
            <input type="url" id="tech-pagespeed-url" class="seo-in" placeholder="https://..." style="max-width:400px;flex:1;">
            <button id="tech-pagespeed-btn" class="seo-btn" style="background:var(--c-primary);color:#fff;border:none;">⚡ Run PageSpeed Test</button>
            <span id="tech-pagespeed-status" style="font-size:13px;color:#0cce6b;display:none;font-weight:600;">✅ PageSpeed test complete!</span>
        </div>
        
        <div id="tech-pagespeed-results" style="display:none; gap:20px; margin-top:20px; flex-wrap:wrap;">
            <!-- Mobile -->
            <div style="flex:1; min-width:300px; background:var(--c-surf); border:1px solid var(--c-border); border-radius:12px; padding:20px;">
                <h4 style="margin:0 0 16px; font-size:16px; display:flex; align-items:center; gap:8px;">📱 Mobile</h4>
                <div style="display:flex; justify-content:space-around; margin-bottom:24px; text-align:center;">
                    <div><div id="tech-ps-mobile-perf"></div><div style="font-size:11px;color:var(--c-muted);">Performance</div></div>
                    <div><div id="tech-ps-mobile-acc"></div><div style="font-size:11px;color:var(--c-muted);">Accessibility</div></div>
                    <div><div id="tech-ps-mobile-bp"></div><div style="font-size:11px;color:var(--c-muted);">Best Practices</div></div>
                    <div><div id="tech-ps-mobile-seo"></div><div style="font-size:11px;color:var(--c-muted);">SEO</div></div>
                </div>
                <h5 style="font-size:11px; color:var(--c-muted); text-transform:uppercase; margin:0 0 12px; border-bottom:1px solid var(--c-border); padding-bottom:4px;">Core Web Vitals</h5>
                <div id="tech-ps-mobile-vitals" style="display:grid; grid-template-columns:1fr auto; gap:8px; font-size:12px; color:var(--c-muted);">
                    <!-- Vitals -->
                </div>
            </div>
            <!-- Desktop -->
            <div style="flex:1; min-width:300px; background:var(--c-surf); border:1px solid var(--c-border); border-radius:12px; padding:20px;">
                <h4 style="margin:0 0 16px; font-size:16px; display:flex; align-items:center; gap:8px;">💻 Desktop</h4>
                <div style="display:flex; justify-content:space-around; margin-bottom:24px; text-align:center;">
                    <div><div id="tech-ps-desktop-perf"></div><div style="font-size:11px;color:var(--c-muted);">Performance</div></div>
                    <div><div id="tech-ps-desktop-acc"></div><div style="font-size:11px;color:var(--c-muted);">Accessibility</div></div>
                    <div><div id="tech-ps-desktop-bp"></div><div style="font-size:11px;color:var(--c-muted);">Best Practices</div></div>
                    <div><div id="tech-ps-desktop-seo"></div><div style="font-size:11px;color:var(--c-muted);">SEO</div></div>
                </div>
                <h5 style="font-size:11px; color:var(--c-muted); text-transform:uppercase; margin:0 0 12px; border-bottom:1px solid var(--c-border); padding-bottom:4px;">Core Web Vitals</h5>
                <div id="tech-ps-desktop-vitals" style="display:grid; grid-template-columns:1fr auto; gap:8px; font-size:12px; color:var(--c-muted);">
                    <!-- Vitals -->
                </div>
            </div>
        </div>
        <div id="tech-pagespeed-msg" style="display:none; font-size:13px; color:#0cce6b; margin-top:16px; font-weight:600;">✅ Scores auto-filled in Website Speed fields below. Save the post to persist.</div>
    </div>

    <!-- WEBSITE SPEED PANEL -->
    <div class="seo-panel" style="margin-bottom:24px;">
        <div class="seo-panel-hd"><h2>WEBSITE SPEED</h2></div>
        <div class="seo-panel-body" style="padding:24px 20px;">
            <div style="display:grid; grid-template-columns:250px 1fr; gap:20px; align-items:center; margin-bottom:16px;">
                <label style="font-size:13px; font-weight:700; color:var(--c-text);">Speed Score — Mobile</label>
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="number" id="tech-speed-mobile" class="seo-in" style="width:80px;" value="<?php echo esc_attr($tech_speed['mobile'] ?? ''); ?>"> <span style="color:var(--c-muted);">/100</span>
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns:250px 1fr; gap:20px; align-items:center; margin-bottom:16px; border-top:1px solid var(--c-border); padding-top:16px;">
                <label style="font-size:13px; font-weight:700; color:var(--c-text);">Speed Score — Desktop</label>
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="number" id="tech-speed-desktop" class="seo-in" style="width:80px;" value="<?php echo esc_attr($tech_speed['desktop'] ?? ''); ?>"> <span style="color:var(--c-muted);">/100</span>
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns:250px 1fr; gap:20px; align-items:center; margin-bottom:16px; border-top:1px solid var(--c-border); padding-top:16px;">
                <label style="font-size:13px; font-weight:700; color:var(--c-text);">Last Audit Date</label>
                <div>
                    <input type="date" id="tech-speed-date" class="seo-in" style="width:160px;" value="<?php echo esc_attr($tech_speed['date'] ?? ''); ?>">
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns:250px 1fr; gap:20px; align-items:center; border-top:1px solid var(--c-border); padding-top:16px; margin-bottom:24px;">
                <label style="font-size:13px; font-weight:700; color:var(--c-text);">Upload Audit Report (PDF/Image)</label>
                <div style="display:flex; align-items:center; gap:12px;">
                    <input type="hidden" id="tech-speed-report-url" value="<?php echo esc_attr($tech_speed['report_url'] ?? ''); ?>">
                    <label class="seo-btn seo-btn-ghost" style="margin:0;cursor:pointer;color:var(--c-primary);border-color:var(--c-primary-alpha);background:var(--c-surf);">
                        📎 Upload Audit Report
                        <input type="file" id="tech-speed-upload-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.gif" style="display:none;">
                    </label>
                    <span id="tech-speed-report-name" style="font-size:12px; color:var(--c-primary);"><?php 
                        if (!empty($tech_speed['report_url'])) {
                            echo '<a href="'.esc_url($tech_speed['report_url']).'" target="_blank">View Current Report</a>';
                        }
                    ?></span>
                </div>
            </div>
            
            <div style="display:flex; align-items:center;">
                <button id="tech-speed-save-btn" class="seo-btn seo-btn-primary">💾 Save Website Speed</button>
                <span id="tech-speed-save-status" style="margin-left:12px; font-size:13px;"></span>
            </div>
        </div>
    </div>

    <div class="seo-panel">
        <div class="seo-panel-hd"><h2>Technical Audit Issues</h2><button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-tech-add-btn">＋ Add Issue</button></div>
        <div id="seo-tech-form" style="display:none;background:var(--c-surf2);padding:16px 20px;border-bottom:1px solid var(--c-border);">
            <div class="seo-grid-3" style="margin-bottom:10px;">
                <div class="seo-field"><label>Audit Item</label><input type="text" id="tech-type" class="seo-in seo-in-sm" autocomplete="nope" placeholder="e.g. Broken Links, Missing Meta..."></div>
                <div class="seo-field"><label>Status</label>
                    <select id="tech-status" class="seo-in seo-in-sm">
                        <option value="fail">Fail</option>
                        <option value="warning" selected>Warning</option>
                        <option value="pass">Pass</option>
                        <option value="n/a">N/A</option>
                    </select>
                </div>
                <div class="seo-field"><label>Notes</label><input type="text" id="tech-desc" class="seo-in seo-in-sm" placeholder="Any additional notes..."></div>
            </div>
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-tech-save-btn" data-rid="<?php echo $rid; ?>">Save Issue</button>
        </div>
        <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
        seoJQ(function($){
            // PageSpeed Auto Run via Server AJAX
            $(document).on('click', '#tech-pagespeed-btn', function(e){
                e.preventDefault();
                var url = $('#tech-pagespeed-url').val().trim();
                if (!url) { alert('Please enter a valid URL.'); return; }
                if (!url.match(/^https?:\/\//)) url = 'https://' + url;
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('⏳ Running... (~30s)');
                $('#tech-pagespeed-status, #tech-pagespeed-msg').hide();
                $('#tech-pagespeed-results').hide();
                
                $.post(seoDash.ajax, {
                    action: 'seo_dash_psi_run',
                    nonce: seoDash.nonce,
                    report_id: <?php echo intval($rid); ?>,
                    url: url
                }, function(r) {
                    if (r.success) {
                        try {
                            var dataM = r.data.mobile;
                            var dataD = r.data.desktop;
                            
                            if (dataM && dataM.categories) renderLighthouse(dataM, 'mobile');
                            if (dataD && dataD.categories) renderLighthouse(dataD, 'desktop');
                            
                            $('#tech-pagespeed-results').css('display', 'flex');
                            $btn.prop('disabled', false).text('⚡ Run PageSpeed Test');
                            $('#tech-pagespeed-status, #tech-pagespeed-msg').show();
                            
                            if (dataM && dataM.categories) {
                                var mScore = Math.round(dataM.categories.performance.score * 100);
                                $('#tech-speed-mobile').val(mScore);
                            }
                            if (dataD && dataD.categories) {
                                var dScore = Math.round(dataD.categories.performance.score * 100);
                                $('#tech-speed-desktop').val(dScore);
                            }
                            
                            var today = new Date().toISOString().split('T')[0];
                            $('#tech-speed-date').val(today);
                        } catch(e) {
                            alert('Error parsing PageSpeed data: ' + e);
                            $btn.prop('disabled', false).text('⚡ Run PageSpeed Test');
                        }
                    } else {
                        var errMsg = (r.data && r.data.message) ? r.data.message : (r.data || 'Failed to fetch PageSpeed results.');
                        if (typeof errMsg === 'object') errMsg = JSON.stringify(errMsg);
                        alert(errMsg);
                        $btn.prop('disabled', false).text('⚡ Run PageSpeed Test');
                    }
                }).fail(function() {
                    alert('Network error communicating with the server. Please check the URL.');
                    $btn.prop('disabled', false).text('⚡ Run PageSpeed Test');
                });
            });

            function renderLighthouse(lh, type) {
                if (!lh || !lh.categories) return;
                var cats = lh.categories;
                var perf = Math.round((cats.performance || {score:0}).score * 100);
                var acc = Math.round((cats.accessibility || {score:0}).score * 100);
                var bp = Math.round((cats['best-practices'] || cats['best_practices'] || {score:0}).score * 100);
                var seo = Math.round((cats.seo || {score:0}).score * 100);
                
                $('#tech-ps-'+type+'-perf').html(getCircleHtml(perf));
                $('#tech-ps-'+type+'-acc').html(getCircleHtml(acc));
                $('#tech-ps-'+type+'-bp').html(getCircleHtml(bp));
                $('#tech-ps-'+type+'-seo').html(getCircleHtml(seo));
                
                var audits = lh.audits || {};
                
                function getVitalHtml(name, audit) {
                    var val = (audit || {}).displayValue || '-';
                    var score = (audit || {}).score || 0;
                    var color = '#ff4e42'; // red
                    if (val === '-') color = '#9ca3af';
                    else if (score >= 0.9) color = '#0cce6b'; // green
                    else if (score >= 0.5) color = '#ffa400'; // orange
                    
                    return '<div style="display:flex;align-items:center;gap:8px;">' +
                           '<div style="width:10px;height:10px;border-radius:2px;background:'+color+';flex-shrink:0;"></div>' +
                           '<div style="color:var(--c-text);">' + name + '</div>' +
                           '</div>' +
                           '<div style="font-weight:bold;text-align:right;color:'+color+';font-size:14px;">' + val + '</div>';
                }
                
                $('#tech-ps-'+type+'-vitals').html(
                    getVitalHtml('First Contentful Paint', audits['first-contentful-paint']) +
                    getVitalHtml('Largest Contentful Paint', audits['largest-contentful-paint']) +
                    getVitalHtml('Cumulative Layout Shift', audits['cumulative-layout-shift']) +
                    getVitalHtml('Total Blocking Time', audits['total-blocking-time']) +
                    getVitalHtml('Speed Index', audits['speed-index'])
                );
            }

            function getCircleHtml(score) {
                var color = '#ff4e42';
                if (score >= 90) color = '#0cce6b';
                else if (score >= 50) color = '#ffa400';
                
                return '<div style="width:60px;height:60px;border-radius:50%;background:conic-gradient('+color+' '+score+'%, #e5e7eb 0);display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">' +
                    '<div style="width:50px;height:50px;border-radius:50%;background:white;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:16px;color:'+color+';">'+score+'</div>' +
                '</div>';
            }

            // Save Website Speed
            $(document).on('click', '#tech-speed-save-btn', function(e){
                e.preventDefault();
                var $btn = $(this).prop('disabled', true).text('Saving...');
                var $st = $('#tech-speed-save-status').text('').css('color', '');
                
                $.post(seoDash.ajax, {
                    action: 'seo_dash_save_tech_speed',
                    nonce: seoDash.nonce,
                    report_id: <?php echo intval($rid); ?>,
                    mobile: $('#tech-speed-mobile').val(),
                    desktop: $('#tech-speed-desktop').val(),
                    date: $('#tech-speed-date').val(),
                    report_url: $('#tech-speed-report-url').val()
                }, function(r){
                    $btn.prop('disabled', false).text('💾 Save Website Speed');
                    if (r.success) {
                        $st.css('color', 'var(--c-green)').text('✅ Saved successfully!');
                        setTimeout(function(){ $st.text(''); }, 3000);
                    } else {
                        var errMsg = (r.data && r.data.message) ? r.data.message : (r.data || 'Error saving.');
                        if (typeof errMsg === 'object') errMsg = JSON.stringify(errMsg);
                        $st.css('color', 'var(--c-red)').text('❌ ' + errMsg);
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text('💾 Save Website Speed');
                    $st.css('color', 'var(--c-red)').text('❌ Network error.');
                });
            });

            // Upload Audit Report — direct AJAX upload
            $(document).on('change', '#tech-speed-upload-file', function(){
                var file = this.files[0];
                if (!file) return;
                var $status = $('#tech-speed-report-name');
                $status.html('<span style="color:var(--c-muted);">⏳ Uploading...</span>');
                var fd = new FormData();
                fd.append('action', 'seo_dash_upload_screenshot');
                fd.append('nonce', seoDash.nonce);
                fd.append('image', file);
                $.ajax({
                    url: seoDash.ajax, type: 'POST',
                    data: fd, processData: false, contentType: false,
                    success: function(r) {
                        if (r && r.success) {
                            var url = r.data.url;
                            $('#tech-speed-report-url').val(url);
                            $status.html('<a href="'+url+'" target="_blank">✅ View Report</a>');
                            setTimeout(function(){ $('#tech-speed-save-btn').trigger('click'); }, 100);
                        } else {
                            var msg = (r && r.data) ? (typeof r.data === 'string' ? r.data : (r.data.message || 'Upload failed')) : 'Upload failed';
                            $status.html('<span style="color:var(--c-red);">❌ ' + msg + '</span>');
                        }
                    },
                    error: function() {
                        $status.html('<span style="color:var(--c-red);">❌ Network error</span>');
                    }
                });
                $(this).val('');
            });

            // Technical Issue script
            $(document).on('click', '#seo-tech-add-btn', function(){
                $('#seo-tech-form').slideToggle(180);
            });
            
            // Save Technical Issue
            $(document).on('click', '#seo-tech-save-btn', function(){
                var $btn = $(this);
                var rid = $btn.data('rid');
                var type = $('#tech-type').val().trim();
                var status = $('#tech-status').val();
                var desc = $('#tech-desc').val().trim();
                
                if (!type) { alert('Please enter an Audit Item.'); return; }
                
                $btn.prop('disabled', true).text('⏳...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_save_technical', // assuming generic or specific endpoint
                    nonce: seoDash.nonce,
                    report_id: rid,
                    issue_type: type,
                    status: status,
                    description: desc
                }, function(r){
                    if (r.success) window.location.reload();
                    else { alert(seoGaErrMsg(r)); $btn.prop('disabled', false).text('Save Issue'); }
                }).fail(function(){
                    alert('Network error.');
                    $btn.prop('disabled', false).text('Save Issue');
                });
            });

            // Update Status inline — use seo_dash_delete_data_row-style direct DB update
            $(document).on('change', '.tech-audit-status-sel', function(){
                var $sel = $(this);
                var id = $sel.data('id');
                var val = $sel.val();
                
                var colors = {'pass': 'var(--c-green)', 'fail': 'var(--c-red)', 'warning': 'var(--c-orange)', 'n/a': 'var(--c-muted)'};
                $sel.css('color', colors[val] || 'var(--c-muted)');
                
                $.post(seoDash.ajax, {
                    action: 'seo_dash_save_technical_status',
                    nonce: seoDash.nonce,
                    row_id: id,
                    status: val
                }, function(r){
                    if(r.success) seoToast('Status updated', 'ok');
                    else seoToast(seoGaErrMsg(r), 'err');
                });
            });

            // Search bar
            $(document).on('keyup', '#tech-search-input', function(){
                var val = $(this).val().toLowerCase();
                $('#seo-tech-tbody tr.tech-active-row').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
                });
            });

            // Check All
            $(document).on('change', '#tech-audit-check-all', function(){
                $('.tech-audit-chk:visible').prop('checked', $(this).prop('checked'));
            });

            // Bulk Action — uses seo_dash_bulk_data_action (existing backend)
            $(document).on('click', '#tech-audit-bulk-btn', function(){
                var action = $('#tech-audit-bulk-sel').val();
                if (!action) return;
                var ids = [];
                $('.tech-audit-chk:checked:visible').each(function(){ ids.push($(this).val()); });
                if (!ids.length) { alert('No rows selected.'); return; }
                
                if (action === 'delete' && !confirm('Permanently delete selected issues?')) return;
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('⏳...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_bulk_data_action',
                    nonce: seoDash.nonce,
                    table: 'technical',
                    bulk_action: action,
                    ids: ids
                }, function(r){
                    if (r.success) window.location.reload();
                    else { alert(r.data && r.data.message ? r.data.message : (r.data || 'Error.')); $btn.prop('disabled', false).text('Apply'); }
                }).fail(function(){ alert('Network error.'); $btn.prop('disabled', false).text('Apply'); });
            });

            // Delete single row — uses seo_dash_delete_data_row (existing backend)
            $(document).on('click', '.tech-audit-del-btn', function(){
                if (!confirm('Move to trash?')) return;
                var id = $(this).data('id');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_delete_data_row',
                    nonce: seoDash.nonce,
                    table: 'technical',
                    row_action: 'trash',
                    row_id: id
                }, function(r){
                    if (r.success) window.location.reload();
                    else alert(r.data && r.data.message ? r.data.message : 'Error.');
                }).fail(function(){ alert('Network error.'); });
            });
        });
        </script>
        <div class="seo-table-wrap">
            <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <select class="seo-in seo-in-sm" id="tech-audit-bulk-sel" style="width:auto;padding:4px 8px;font-size:12px;">
                        <option value="">Bulk Actions</option>
                        <option value="trash">Move Selected to Trash</option>
                        <option value="restore">Restore Selected from Trash</option>
                        <option value="delete">Delete Permanently</option>
                    </select>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm" id="tech-audit-bulk-btn">Apply</button>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="text" class="seo-in seo-in-sm" id="tech-search-input" placeholder="Search issues..." style="width:160px;">
                    
                    <div id="gsheet-sync-bar-technical" style="display:flex;align-items:center;gap:6px;">
                        <?php $link = $gsheet_links['technical'] ?? []; if ( !empty($link['spreadsheet_id']) ) : ?>
                        <span style="font-size:11px;color:var(--c-muted);white-space:nowrap;">📊 <?php echo esc_html( $link['spreadsheet_name'] ?? 'Linked Sheet' ); ?> / <?php echo esc_html( $link['tab_name'] ?? '' ); ?></span>
                        <button class="seo-btn seo-btn-sm gsheet-sync-btn" data-tabtype="technical" style="height:28px;font-size:11px;background:var(--c-primary);color:#fff;">⬇️ Update</button>
                        <button class="seo-btn seo-btn-sm gsheet-export-btn" data-tabtype="technical" style="height:28px;font-size:11px;background:var(--c-surf);color:var(--c-primary);border:1px solid var(--c-primary);">⬇ Export to Sheet</button>
                        <span class="gsheet-sync-status" data-tabtype="technical" style="font-size:11px;color:var(--c-muted);"></span>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--c-muted);margin-right:8px;">Google Sheet not linked</span>
                        <?php endif; ?>
                    </div>

                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="seo-tech-tbl">⬇️ Export CSV</button>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="technical">⬇️ Download Format</button>
                    <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">
                        ⬆️ Import CSV
                        <input type="file" class="seo-import-csv-input" data-type="technical" accept=".csv" style="display:none;">
                    </label>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm db-generic-view-trash-btn" data-type="technical">🗑️ View Trash</button>
                </div>
            </div>
            <table class="seo-table" id="seo-tech-tbl">
                <thead><tr>
                    <th style="width:36px;text-align:center;"><input type="checkbox" id="tech-audit-check-all"></th>
                    <th style="width:40px;">#</th><th>Audit Item</th><th style="width:150px;">Status</th><th>Notes</th><th style="width:80px;text-align:center;">Remove</th>
                </tr></thead>
                <tbody id="seo-tech-tbody">
                    <?php
                    $rows = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_technical, $rid, '', true);
                    if (empty($rows)) :
                    ?><tr><td colspan="6" style="text-align:center;padding:24px;color:var(--c-subtle);">No issues recorded.</td></tr>
                    <?php else : $i=1; foreach ($rows as $row) :
                        $is_trashed = !empty($row['trashed']);
                        $status_colors = ['pass'=>'var(--c-green)','fail'=>'var(--c-red)','warning'=>'var(--c-orange)','n/a'=>'var(--c-muted)'];
                        $sc = $status_colors[$row['status']] ?? 'var(--c-muted)';
                    ?>
                    <tr class="<?php echo $is_trashed ? 'tech-trashed-row db-row-type-technical db-generic-trashed-row' : 'tech-active-row db-row-type-technical db-generic-active-row'; ?>" style="<?php echo $is_trashed ? 'display:none;background:rgba(239,68,68,0.15);' : ''; ?>">
                        <td style="text-align:center;"><input type="checkbox" class="tech-audit-chk" value="<?php echo intval($row['id']); ?>"></td>
                        <td style="color:var(--c-muted);"><?php echo $i++; ?></td>
                        <td style="font-weight:600;font-size:13px;"><?php echo esc_html($row['issue_type']); ?></td>
                        <td>
                            <select class="seo-in seo-in-sm tech-audit-status-sel" data-id="<?php echo intval($row['id']); ?>" style="padding:2px 8px;font-size:12px;width:auto;border:none;background:var(--c-surf2);color:<?php echo $sc; ?>;font-weight:600;">
                                <option value="fail" <?php selected($row['status'], 'fail'); ?> style="color:var(--c-red);">Fail</option>
                                <option value="warning" <?php selected($row['status'], 'warning'); ?> style="color:var(--c-orange);">Warning</option>
                                <option value="pass" <?php selected($row['status'], 'pass'); ?> style="color:var(--c-green);">Pass</option>
                                <option value="n/a" <?php selected($row['status'], 'n/a'); ?> style="color:var(--c-muted);">N/A</option>
                            </select>
                        </td>
                        <td style="font-size:12px;color:var(--c-subtle);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($row['description']); ?></td>
                        <td style="text-align:center;"><button class="seo-icon-btn seo-icon-btn-d tech-audit-del-btn" data-id="<?php echo intval($row['id']); ?>">🗑️</button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php // ── BACKLINKS ────────────────────────────────────────────────────────
elseif ($tab === 'backlinks') :
    $months = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_backlinks, $rid);

    // Server-side pagination — fetching all rows at once (e.g. 9,000+) and rendering
    // each as an editable table row exhausts PHP memory on large link profiles.
    $bk_per_page    = 50;
    $bk_total_rows  = SEO_Dash_Database::get_data_rows_count( SEO_Dash_Database::$data_backlinks, $rid, '', true );
    $bk_total_pages = max( 1, (int) ceil( $bk_total_rows / $bk_per_page ) );
    $bk_page        = max( 1, intval( $_GET['bk_page'] ?? 1 ) );
    if ( $bk_page > $bk_total_pages ) $bk_page = $bk_total_pages;
    $bk_offset      = ( $bk_page - 1 ) * $bk_per_page;
    $rows   = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_backlinks, $rid, '', true, $bk_per_page, $bk_offset );
    $bk_page_base_url = remove_query_arg( 'bk_page' );
    
    $active_sub = sanitize_key($_GET['sub'] ?? 'all');
    $valid_subs = ['all', 'guest_post', 'directory', 'social', 'forum', 'citation', 'press_release', 'infographic', 'broken_link', 'other', 'front_settings'];
    if (!in_array($active_sub, $valid_subs)) $active_sub = 'all';

    // Front table settings
    $front_settings = get_option("seo_dash_bk_front_{$rid}", [
        'cols' => ['type', 'website', 'da', 'pa', 'spam', 'live_link', 'keyword', 'target_url', 'date', 'status']
    ]);
?>
    <!-- Top Tabs -->
    <div class="seo-tabs-wrap" style="margin-bottom:20px;">
        <a href="#" class="seo-tab bk-subtab-link seo-tab-active" data-bk-subtab="bk-subtab-main">
            🔗 Backlinks
        </a>
        <a href="#" class="seo-tab bk-subtab-link" data-bk-subtab="bk-subtab-front-settings">
            📋 Front Table
        </a>
    </div>
    <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
    seoJQ(function($){
        $('.bk-subtab-link').on('click', function(e){
            e.preventDefault();
            var target = $(this).data('bk-subtab');
            $('.bk-subtab-link').removeClass('seo-tab-active');
            $(this).addClass('seo-tab-active');
            $('.bk-subtab-panel').hide();
            if (target === 'bk-subtab-main') {
                $('#bk-subtab-main').show();
                $('#bk-subtab-backlinks-table').show();
            } else {
                $('#' + target).show();
            }
        });
    });
    </script>

    <div id="bk-subtab-main" class="bk-subtab-panel">
    <?php if (true) :
        $bk_kpis     = SEO_Dash_Database::get_backlinks_kpis($rid);
        $total_bk    = $bk_kpis['total'];
        $live_bk     = $bk_kpis['live'];
        $dofollow_bk = $bk_kpis['dofollow'];
        $avg_da      = $bk_kpis['avg_da'];

        $kpi_items = [
            ['label'=>'Total Backlinks', 'val'=>$total_bk, 'icon'=>'🔗', 'color'=>'--c-primary'],
            ['label'=>'Live Links',      'val'=>$live_bk,  'icon'=>'✅', 'color'=>'--c-green'],
            ['label'=>'Dofollow Links',  'val'=>$dofollow_bk, 'icon'=>'👍', 'color'=>'--c-yellow'],
            ['label'=>'Average DA',      'val'=>$avg_da,   'icon'=>'📈', 'color'=>'--c-blue'],
        ];
    ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-bottom:20px;">
        <?php foreach ($kpi_items as $k): ?>
        <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid var(<?php echo $k['color']; ?>);">
            <div style="font-size:20px;"><?php echo $k['icon']; ?></div>
            <div style="font-size:22px;font-weight:700;color:var(--c-text);"><?php echo number_format($k['val']); ?></div>
            <div style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;"><?php echo $k['label']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    </div><!-- /#bk-subtab-main -->

    <div id="bk-subtab-front-settings" class="bk-subtab-panel" style="display:none;">
    <?php if (true) : ?>
        <style>
        .bk-col-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--c-surf);
            border: 1px solid var(--c-border);
            border-radius: 8px;
            padding: 12px 16px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            color: var(--c-text);
        }
        .bk-col-label:hover {
            border-color: #a5b4fc;
        }
        .bk-col-label:has(input:checked) {
            border-color: #4f46e5;
            background: var(--c-surf2);
        }
        .bk-on-badge {
            display: none;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 6px;
        }
        .bk-col-label:has(input:checked) .bk-on-badge {
            display: block;
        }
        /* Fallback for browsers not supporting :has */
        .bk-col-label.is-checked {
            border-color: #4f46e5;
            background: var(--c-surf2);
        }
        .bk-col-label.is-checked .bk-on-badge {
            display: block;
        }
        </style>
        <div class="seo-panel">
            <div class="seo-panel-hd">
                <h2>📋 Front-End Table: Column Visibility</h2>
            </div>
            <div class="seo-panel-body" style="padding:24px;">
                <form id="bk-front-settings-form">
                    <p style="font-size:13px;color:var(--c-muted);margin:0 0 20px;">Choose which columns show in the client-facing backlinks table. Save the post to apply.</p>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
                        <?php 
                        $avail_cols = [
                            'row_num' => '# (Row Number)', 'type' => 'Type', 'website' => 'Website URL', 'da' => 'DA', 
                            'pa' => 'PA', 'spam' => 'Spam %', 'live_link' => 'Live Link', 'keyword' => 'Target Keyword', 
                            'target_url' => 'Target URL', 'date' => 'Date', 'month' => 'Month', 'year' => 'Year', 'status' => 'Status'
                        ];
                        $saved_cols = is_array($front_settings['cols'] ?? null) ? $front_settings['cols'] : ['type', 'website', 'da', 'pa', 'spam', 'live_link', 'keyword', 'target_url', 'date', 'status'];
                        foreach ($avail_cols as $ck => $clbl) : 
                            $checked = in_array($ck, $saved_cols) ? 'checked' : '';
                            $class = $checked ? ' is-checked' : '';
                        ?>
                        <label class="bk-col-label<?php echo $class; ?>">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="bk_cols[]" value="<?php echo esc_attr($ck); ?>" <?php echo $checked; ?> 
                                       style="accent-color:#4f46e5; width:16px; height:16px;" 
                                       onchange="this.parentElement.parentElement.classList.toggle('is-checked', this.checked)"> 
                                <span style="font-weight:500;"><?php echo esc_html($clbl); ?></span>
                            </div>
                            <span class="bk-on-badge">ON</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <p style="font-size:12px;color:var(--c-subtle);margin:0 0 24px;">ⓘ These settings also control which chart elements appear on the front-end backlinks tab.</p>
                    
                    <div style="display:flex;align-items:center;gap:12px;">
                        <button type="button" class="seo-btn seo-btn-primary" id="bk-save-front-settings" data-rid="<?php echo $rid; ?>" style="background:#4f46e5;border-color:#4f46e5;padding:8px 24px;border-radius:6px;font-weight:600;">💾 Save Settings</button>
                        <span id="bk-front-settings-msg" style="font-size:13px; font-weight:600;"></span>
                    </div>
                </form>
            </div>
        </div>
        <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
        seoJQ(function($){
            $('#bk-save-front-settings').on('click', function(e){
                e.preventDefault();
                var cols = [];
                $('input[name="bk_cols[]"]:checked').each(function(){ cols.push($(this).val()); });
                var $btn = $(this), $msg = $('#bk-front-settings-msg');
                $btn.prop('disabled', true).text('Saving...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_save_bk_front_settings',
                    nonce: seoDash.nonce,
                    report_id: $btn.data('rid'),
                    cols: cols
                }, function(res) {
                    $btn.prop('disabled', false).text('Save Settings');
                    $msg.text('✅ Saved!').show();
                    setTimeout(function(){ $msg.fadeOut(); }, 3000);
                });
            });
        });
        </script>
    <?php endif; ?>
    </div><!-- /#bk-subtab-front-settings -->

    <div id="bk-subtab-backlinks-table" class="bk-subtab-panel">

        <div class="seo-panel">
            <div class="seo-panel-hd" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <h2>🔗 Backlinks <?php echo $active_sub!=='all' ? '- '.ucwords(str_replace('_',' ',$active_sub)) : ''; ?></h2>
                </div>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <!-- Google Sheet Sync -->
                    <div id="gsheet-sync-bar-backlinks" style="display:flex;align-items:center;gap:6px;">
                        <?php $link = $gsheet_links['backlinks'] ?? []; if ( !empty($link['spreadsheet_id']) ) : ?>
                        <span style="font-size:11px;color:var(--c-muted);white-space:nowrap;">📅 <?php echo esc_html( $link['spreadsheet_name'] ?? 'Linked Sheet' ); ?> / <?php echo esc_html( $link['tab_name'] ?? '' ); ?></span>
                        <button class="seo-btn seo-btn-sm gsheet-sync-btn" data-tabtype="backlinks" style="height:28px;font-size:11px;background:var(--c-primary);color:#fff;">🔄 Update</button>
                        <button class="seo-btn seo-btn-sm gsheet-export-btn" data-tabtype="backlinks" style="height:28px;font-size:11px;background:var(--c-surf);color:var(--c-primary);border:1px solid var(--c-primary);">⬇ Export to Sheet</button>
                        <span class="gsheet-sync-status" data-tabtype="backlinks" style="font-size:11px;color:var(--c-muted);"></span>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--c-muted);">⚠️ No sheet linked. Go to Integrations tab.</span>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            
            <div class="seo-table-wrap">
                <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <select class="seo-in seo-in-sm" id="bk-bulk-sel" style="width:auto;padding:4px 8px;font-size:12px;">
                            <option value="">Bulk Actions</option>
                            <option value="trash">Move Selected to Trash</option>
                            <option value="restore">Restore Selected from Trash</option>
                            <option value="delete">Delete Permanently</option>
                            <option value="delete_all" style="color:var(--c-red);">Delete All</option>
                        </select>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm" id="bk-bulk-btn" data-type="backlinks">Apply</button>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <input type="text" id="bk-search" class="seo-in seo-in-sm" placeholder="Search backlinks..." style="width:150px;">
                        <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-add-backlink-btn">➕ Add Backlink</button>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="seo-bk-tbl">⬇️ Export</button>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="backlinks">⬇️ Format</button>
                        <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">
                            ⬆️ Import
                            <input type="file" class="seo-import-csv-input" data-type="backlinks" accept=".csv" style="display:none;">
                        </label>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm db-generic-view-trash-btn" data-type="backlinks">🗑️ Trash</button>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div id="bk-pagination-top" style="padding:10px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--c-border); background:var(--c-surf2);">
                    <div style="font-size:13px; color:var(--c-subtle);"><span id="bk-total-info"><?php echo number_format_i18n($bk_total_rows); ?> total</span> | Page <span id="bk-page-info"><?php echo $bk_page; ?> of <?php echo $bk_total_pages; ?></span></div>
                    <div id="bk-page-numbers" style="display:flex; gap:5px;">
                        <?php
                        $bk_p_start = max(1, $bk_page - 2);
                        $bk_p_end   = min($bk_total_pages, $bk_page + 2);
                        $bk_link = function($p, $label) use ($bk_page_base_url, $bk_page, $bk_total_pages) {
                            $disabled = ($p < 1 || $p > $bk_total_pages || $p === $bk_page);
                            $p = max(1, min($bk_total_pages, $p));
                            $style = 'min-width:32px;padding:4px;text-align:center;text-decoration:none;display:inline-block;';
                            if ($disabled) $style .= 'opacity:0.5;pointer-events:none;';
                            $url = add_query_arg('bk_page', $p, $bk_page_base_url);
                            return '<a href="'.esc_url($url).'" class="seo-btn seo-btn-ghost seo-btn-sm" style="'.$style.'">'.$label.'</a>';
                        };
                        echo $bk_link(1, '&laquo;');
                        echo $bk_link($bk_page - 1, '&lsaquo;');
                        for ($i = $bk_p_start; $i <= $bk_p_end; $i++) {
                            $active = $i === $bk_page ? 'background:var(--c-primary);color:#fff;border-color:var(--c-primary);' : '';
                            $url = add_query_arg('bk_page', $i, $bk_page_base_url);
                            echo '<a href="'.esc_url($url).'" class="seo-btn seo-btn-ghost seo-btn-sm" style="min-width:32px;padding:4px;text-align:center;text-decoration:none;display:inline-block;'.$active.'">'.$i.'</a>';
                        }
                        echo $bk_link($bk_page + 1, '&rsaquo;');
                        echo $bk_link($bk_total_pages, '&raquo;');
                        ?>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <style>
                    .bk-url-wrap{display:flex;align-items:center;gap:6px;}
                    .bk-url-open-btn:hover{background:var(--c-primary);color:#fff;border-color:var(--c-primary);}
                    .bk-url-edit-btn:hover{background:var(--c-surf2);color:var(--c-text);}
                    </style>
                    <table class="seo-table no-filter" id="seo-bk-tbl" style="min-width:1450px;">
                        <thead><tr>
                            <th style="width:36px;text-align:center;"><input type="checkbox" id="bk-check-all"></th>
                            <th style="width:40px;text-align:center;">Sr</th>
                            <th style="width:120px;">Type</th>
                            <th style="width:250px;">Website URL</th>
                            <th style="width:60px;text-align:center;">DA</th>
                            <th style="width:60px;text-align:center;">PA</th>
                            <th style="width:60px;text-align:center;">Spam%</th>
                            <th style="width:150px;text-align:center;">Live Link</th>
                            <th style="width:250px;">Keyword</th>
                            <th style="width:250px;">Target URL</th>
                            <th style="width:100px;">Date</th>
                            <th style="width:100px;">Status</th>
                            <th style="width:50px;text-align:center;">Del</th>
                        </tr></thead>
                        <tbody id="bk-tbody">
                            <?php 
                            $count = $bk_offset;
                            if (empty($rows)) : ?>
                            <tr class="bk-empty-row"><td colspan="12" style="text-align:center;padding:24px;color:var(--c-subtle);">No backlinks found.</td></tr>
                            <?php else : foreach ($rows as $row) : 
                                
                                $is_trashed = !empty($row['trashed']);
                                $count++;
                            ?>
                            <tr class="bk-row <?php echo $is_trashed ? 'db-generic-trashed-row' : 'db-generic-active-row'; ?>" data-id="<?php echo intval($row['id']); ?>" data-type="backlinks" style="<?php echo $is_trashed ? 'display:none;background:rgba(239,68,68,0.15);' : ''; ?>">
                                <td style="text-align:center;"><input type="checkbox" class="bk-chk" value="<?php echo intval($row['id']); ?>"></td>
                                <td style="text-align:center;color:var(--c-muted);font-size:12px;"><?php echo $count; ?></td>
                                <td>
                                    <select class="seo-in seo-bk-inline-input" data-field="link_type" data-id="<?php echo intval($row['id']); ?>" style="width:100%;font-size:11px;padding:2px 4px;">
                                        <option value="dofollow" <?php selected($row['link_type'], 'dofollow'); ?>>Dofollow</option>
                                        <option value="guest_post" <?php selected($row['link_type'], 'guest_post'); ?>>Guest Post</option>
                                        <option value="press_release" <?php selected($row['link_type'], 'press_release'); ?>>Press Release</option>
                                        <option value="directory" <?php selected($row['link_type'], 'directory'); ?>>Directory</option>
                                        <option value="citation" <?php selected($row['link_type'], 'citation'); ?>>Citation</option>
                                        <option value="other" <?php selected($row['link_type'], 'other'); ?>>Other</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="bk-url-wrap">
                                        <a href="<?php echo esc_url($row['source_url']); ?>" target="_blank" rel="noopener" class="seo-btn seo-btn-ghost seo-btn-sm bk-url-open-btn" style="display:<?php echo $row['source_url'] ? 'inline-flex' : 'none'; ?>;align-items:center;gap:4px;font-size:11px;text-decoration:none;white-space:nowrap;">🔗 Open</a>
                                        <button type="button" class="bk-url-edit-btn" title="Edit URL" style="display:<?php echo $row['source_url'] ? 'inline-flex' : 'none'; ?>;align-items:center;justify-content:center;background:transparent;border:1px solid var(--c-border);border-radius:6px;width:24px;height:24px;cursor:pointer;color:var(--c-muted);font-size:11px;">✏️</button>
                                        <input type="text" class="seo-in seo-bk-inline-input bk-url-input" data-field="source_url" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['source_url']); ?>" placeholder="https://example.com" style="<?php echo $row['source_url'] ? 'display:none;' : ''; ?>width:100%;font-size:12px;padding:2px 4px;border:1px solid var(--c-border);background:var(--c-surf);">
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <input type="number" class="seo-in seo-bk-inline-input" data-field="domain_rating" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['domain_rating'] ?? ''); ?>" style="width:100%;text-align:center;font-size:12px;padding:2px 4px;border:1px solid transparent;background:transparent;" onfocus="this.style.border='1px solid var(--c-border)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid transparent';this.style.background='transparent';">
                                </td>
                                <td style="text-align:center;">
                                    <input type="number" class="seo-in seo-bk-inline-input" data-field="page_authority" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['page_authority'] ?? ''); ?>" style="width:100%;text-align:center;font-size:12px;padding:2px 4px;border:1px solid transparent;background:transparent;" onfocus="this.style.border='1px solid var(--c-border)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid transparent';this.style.background='transparent';">
                                </td>
                                <td style="text-align:center;">
                                    <input type="number" class="seo-in seo-bk-inline-input" data-field="spam_score" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['spam_score'] ?? ''); ?>" style="width:100%;text-align:center;font-size:12px;padding:2px 4px;border:1px solid transparent;background:transparent;" onfocus="this.style.border='1px solid var(--c-border)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid transparent';this.style.background='transparent';">
                                </td>
                                <td>
                                    <div class="bk-url-wrap">
                                        <a href="<?php echo esc_url($row['live_link']); ?>" target="_blank" rel="noopener" class="seo-btn seo-btn-ghost seo-btn-sm bk-url-open-btn" style="display:<?php echo $row['live_link'] ? 'inline-flex' : 'none'; ?>;align-items:center;gap:4px;font-size:11px;text-decoration:none;white-space:nowrap;">🔗 Open</a>
                                        <button type="button" class="bk-url-edit-btn" title="Edit URL" style="display:<?php echo $row['live_link'] ? 'inline-flex' : 'none'; ?>;align-items:center;justify-content:center;background:transparent;border:1px solid var(--c-border);border-radius:6px;width:24px;height:24px;cursor:pointer;color:var(--c-muted);font-size:11px;">✏️</button>
                                        <input type="text" class="seo-in seo-bk-inline-input bk-url-input" data-field="live_link" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['live_link']); ?>" placeholder="https://example.com" style="<?php echo $row['live_link'] ? 'display:none;' : ''; ?>width:100%;font-size:12px;padding:2px 4px;border:1px solid var(--c-border);background:var(--c-surf);">
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="seo-in seo-bk-inline-input bk-searchable" data-field="anchor_text" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['anchor_text']); ?>" style="width:100%;font-size:12px;padding:2px 4px;border:1px solid transparent;background:transparent;" onfocus="this.style.border='1px solid var(--c-border)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid transparent';this.style.background='transparent';">
                                </td>
                                <td style="display:flex;align-items:center;gap:6px;">
                                    <?php if($row['target_url']) echo '<a href="'.esc_url($row['target_url']).'" target="_blank" style="color:var(--c-primary);font-size:14px;text-decoration:none;">↗</a>'; ?>
                                    <input type="text" class="seo-in seo-bk-inline-input bk-searchable" data-field="target_url" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['target_url']); ?>" style="flex:1;font-size:12px;padding:2px 4px;border:1px solid transparent;background:transparent;" onfocus="this.style.border='1px solid var(--c-border)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid transparent';this.style.background='transparent';">
                                </td>
                                <td>
                                    <input type="date" class="seo-in seo-bk-inline-input" data-field="found_date" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['found_date']); ?>" style="width:100%;font-size:11px;padding:2px 4px;border:1px solid transparent;background:transparent;" onfocus="this.style.border='1px solid var(--c-border)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid transparent';this.style.background='transparent';">
                                </td>
                                <td>
                                    <select class="seo-in seo-bk-inline-input bk-status-sel" data-field="status" data-id="<?php echo intval($row['id']); ?>" style="width:100%;padding:2px 4px;font-size:11px; <?php echo $row['status']==='live'?'color:#10b981;':($row['status']==='lost'?'color:#ef4444;':'color:#f59e0b;'); ?>">
                                        <option value="live" <?php selected($row['status'],'live'); ?>>Live</option>
                                        <option value="lost" <?php selected($row['status'],'lost'); ?>>Lost</option>
                                        <option value="broken" <?php selected($row['status'],'broken'); ?>>Broken</option>
                                    </select>
                                </td>
                                <td style="text-align:center;">
                                    <button class="seo-icon-btn seo-icon-btn-d seo-del-row-btn" data-table="backlinks" data-id="<?php echo intval($row['id']); ?>">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; 
                            endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        
        <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
        seoJQ(function($){
            // Search (filters the rows on the current server-paginated page only)
            $('#bk-search').on('keyup', function(){
                var term = $(this).val().toLowerCase();
                if(term) {
                    $('.bk-row.db-generic-active-row').each(function(){
                        var txt = $(this).find('.bk-searchable').text().toLowerCase();
                        $(this).toggle(txt.indexOf(term) > -1);
                    });
                } else {
                    $('.bk-row.db-generic-active-row').show();
                }
            });

            // Check All
            $('#bk-check-all').on('change', function(){
                $('.bk-chk:visible').prop('checked', $(this).prop('checked'));
            });

            // Bulk Actions
            $('#bk-bulk-btn').on('click', function(){
                var action = $('#bk-bulk-sel').val();
                var ids = [];
                $('.bk-chk:checked').each(function(){ ids.push($(this).val()); });
                
                if(!action) return alert('Select an action first');
                if(action !== 'delete_all' && ids.length === 0) return alert('Select rows first');
                if(action === 'delete_all' && !confirm('Are you sure you want to permanently delete ALL backlinks? This action cannot be undone.')) return;
                if(action === 'delete' && !confirm('Permanently delete selected backlinks?')) return;
                
                var $btn = $(this); $btn.prop('disabled', true).text('...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_bulk_data_action',
                    nonce: seoDash.nonce,
                    table: 'backlinks',
                    bulk_action: action,
                    ids: ids,
                    report_id: <?php echo $rid; ?>
                }, function(r){
                    if(r.success) location.reload();
                    else { alert(seoGaErrMsg(r)); $btn.prop('disabled', false).text('Apply'); }
                });
            });

            // Inline Input Save
            $(document).on('change', '.seo-bk-inline-input', function(){
                var $el = $(this);
                if ($el.hasClass('bk-status-sel')) {
                    var val = $el.val();
                    $el.css('color', val==='live' ? '#10b981' : (val==='lost' ? '#ef4444' : '#f59e0b'));
                }
                $.post(seoDash.ajax, {
                    action: 'seo_dash_save_bk_field',
                    nonce: seoDash.nonce,
                    row_id: $el.data('id'),
                    field: $el.data('field'),
                    val: $el.val()
                });
            });

            // Website URL / Live Link cells: show a clean "Open" button instead
            // of the raw URL. Click the pencil to edit; saving (blur) swaps
            // back to the Open button automatically.
            $(document).on('click', '.bk-url-edit-btn', function(){
                var $wrap = $(this).closest('.bk-url-wrap');
                $wrap.find('.bk-url-open-btn, .bk-url-edit-btn').hide();
                $wrap.find('.bk-url-input').show().trigger('focus').trigger('select');
            });
            $(document).on('blur', '.bk-url-input', function(){
                var $input = $(this);
                var $wrap  = $input.closest('.bk-url-wrap');
                var val    = $.trim($input.val());
                if (val) {
                    $wrap.find('.bk-url-open-btn').attr('href', val).show();
                    $wrap.find('.bk-url-edit-btn').show();
                    $input.hide();
                }
                // If left empty, keep the input visible so a URL can still be typed.
            });

            // Add Backlink
            $('#seo-add-backlink-btn').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text('Adding...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_add_backlink',
                    nonce: seoDash.nonce,
                    report_id: <?php echo $rid; ?>
                }, function(r){
                    if(r.success) {
                        location.reload();
                    } else {
                        alert(seoGaErrMsg(r));
                        $btn.prop('disabled', false).text('➕ Add Backlink');
                    }
                });
            });
        });
        </script>
    </div><!-- /#bk-subtab-backlinks-table -->

<?php // ── LEADS ────────────────────────────────────────────────────────────
elseif ($tab === 'leads') :
    SEO_Dash_Database::create_tables(); // Make sure DB schema is upgraded for new columns
    $months = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_leads, $rid);
    $rows   = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_leads, $rid);
?>
    <div class="seo-panel">
        <!-- Admin Leads Sub-tab Switcher -->
        <div style="display:flex;gap:12px;border-bottom:2px solid var(--c-border);margin-bottom:20px;padding:12px 20px 0;">
            <button type="button" class="seo-leads-subtab-btn active" data-pane="forms" style="padding:10px 18px;font-size:13px;font-weight:700;background:none;border:none;border-bottom:3px solid var(--c-primary);margin-bottom:-2px;color:var(--c-primary);cursor:pointer;">📋 Form Leads</button>
            <button type="button" class="seo-leads-subtab-btn" data-pane="clicks" style="padding:10px 18px;font-size:13px;font-weight:700;background:none;border:none;border-bottom:3px solid transparent;margin-bottom:-2px;color:var(--c-muted);cursor:pointer;">🖱️ Click Tracking</button>
        </div>

        <!-- ── Sub-Pane 1: Form Leads ───────────────────────────── -->
        <div id="seo-leads-pane-forms">
        <div class="seo-panel-hd" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <h2>🔗 Leads</h2>
                <span id="ld-live-indicator" style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--c-muted);font-weight:600;">
                    <span style="width:7px;height:7px;border-radius:50%;background:#10b981;display:inline-block;animation:ldLivePulse 1.8s ease-in-out infinite;"></span>
                    Live — auto-syncs client status changes
                </span>
                <style>@keyframes ldLivePulse{0%,100%{opacity:1;}50%{opacity:.35;}}</style>
            </div>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <!-- Google Sheet Sync -->
                <div id="gsheet-sync-bar-leads" style="display:flex;align-items:center;gap:6px;">
                    <?php $link = $gsheet_links['leads'] ?? []; if ( !empty($link['spreadsheet_id']) ) : ?>
                    <span style="font-size:11px;color:var(--c-muted);white-space:nowrap;">📅 <?php echo esc_html( $link['spreadsheet_name'] ?? 'Linked Sheet' ); ?> / <?php echo esc_html( $link['tab_name'] ?? '' ); ?></span>
                    <button class="seo-btn seo-btn-sm gsheet-sync-btn" data-tabtype="leads" style="height:28px;font-size:11px;background:var(--c-primary);color:#fff;">🔄 Update</button>
                    <button class="seo-btn seo-btn-sm gsheet-export-btn" data-tabtype="leads" style="height:28px;font-size:11px;background:var(--c-surf);color:var(--c-primary);border:1px solid var(--c-primary);">⬇ Export to Sheet</button>
                    <span class="gsheet-sync-status" data-tabtype="leads" style="font-size:11px;color:var(--c-muted);"></span>
                    <?php else: ?>
                    <span style="font-size:11px;color:var(--c-muted);">⚠️ No sheet linked. Go to Integrations tab.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        $rows = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_leads, $rid, '', true);
        $total_leads = 0;
        $kpi = ['new'=>0,'contacted'=>0,'checking'=>0,'qualified'=>0,'converted'=>0,'lost'=>0];
        if(!empty($rows)) {
            foreach($rows as $r) {
                if(empty($r['trashed'])) {
                    $total_leads++;
                    $st = strtolower($r['status'] ?: 'new');
                    if(isset($kpi[$st])) $kpi[$st]++;
                }
            }
        }
        ?>
        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; padding:0;" id="ld-kpi-row">
            <div class="seo-kpi-card" data-kpi="total" style="flex:1; min-width:120px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid var(--c-primary);">
                <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo $total_leads; ?></div>
                <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">Total Leads</div>
            </div>
            <div class="seo-kpi-card" data-kpi="new" style="flex:1; min-width:120px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #3b82f6;">
                <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo $kpi['new']; ?></div>
                <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">🔠 New</div>
            </div>
            <div class="seo-kpi-card" data-kpi="contacted" style="flex:1; min-width:120px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #f59e0b;">
                <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo $kpi['contacted']; ?></div>
                <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">📞 Contacted</div>
            </div>
            <div class="seo-kpi-card" data-kpi="checking" style="flex:1; min-width:120px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #8b5cf6;">
                <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo $kpi['checking']; ?></div>
                <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">🔍 Checking</div>
            </div>
            <div class="seo-kpi-card" data-kpi="qualified" style="flex:1; min-width:120px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #10b981;">
                <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo $kpi['qualified']; ?></div>
                <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">✅ Qualified</div>
            </div>
            <div class="seo-kpi-card" data-kpi="converted" style="flex:1; min-width:120px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #059669;">
                <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo $kpi['converted']; ?></div>
                <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">🎉 Converted</div>
            </div>
            <div class="seo-kpi-card" data-kpi="lost" style="flex:1; min-width:120px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #ef4444;">
                <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo $kpi['lost']; ?></div>
                <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">❌ Lost</div>
            </div>
        </div>

        <div class="seo-table-wrap">
            <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center; flex-wrap:wrap; gap:8px;">
                <select class="seo-in seo-in-sm" id="ld-bulk-sel" style="width:auto;padding:4px 8px;font-size:12px;">
                    <option value="">Bulk Actions</option>
                    <option value="trash">Move Selected to Trash</option>
                    <option value="restore">Restore Selected from Trash</option>
                    <option value="delete">Delete Permanently</option>
                    <option value="delete_all" style="color:var(--c-red);">Delete All</option>
                </select>
                <button class="seo-btn seo-btn-ghost seo-btn-sm" id="ld-bulk-btn" data-type="leads">Apply</button>
                
                <?php $ld_admin_statuses = seo_dash_get_custom_statuses($rid); ?>
                <span style="display:inline-block;width:1px;height:20px;background:var(--c-border);margin:0 4px;"></span>
                <select class="seo-in seo-in-sm" id="ld-bulk-status-sel" style="width:auto;padding:4px 8px;font-size:12px;">
                    <option value="">Change Status...</option>
                    <?php foreach ($ld_admin_statuses as $stk => $stv) : ?>
                    <option value="<?php echo esc_attr($stk); ?>"><?php echo esc_html(($stv['icon'] ?? '🏷️') . ' ' . $stv['label']); ?></option>
                    <?php endforeach; ?>
                    <option value="__add_custom__">➕ + Add Custom Status...</option>
                </select>
                <button class="seo-btn seo-btn-ghost seo-btn-sm" id="ld-bulk-status-btn" data-type="leads">Update Status</button>
                
                <span style="display:inline-block;width:1px;height:20px;background:var(--c-border);margin:0 4px;"></span>
                <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-add-lead-btn">➕ Add Lead</button>
                <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="seo-leads-tbl">⬇️ Export</button>
                <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="leads">⬇️ Format</button>
                <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">
                    ⬆️ Import
                    <input type="file" class="seo-import-csv-input" data-type="leads" accept=".csv" style="display:none;">
                </label>
                <button class="seo-btn seo-btn-ghost seo-btn-sm db-generic-view-trash-btn" data-type="leads">🗑️ Trash</button>
            </div>
            <div style="padding:10px 20px; border-bottom:1px solid var(--c-border); background:var(--c-surf2);">
                <input type="text" id="ld-search" class="seo-in seo-in-sm" placeholder="Search leads..." style="width:100%; max-width:400px; font-size:13px; padding:6px 10px;">
            </div>
            
            <!-- Pagination -->
            <div id="ld-pagination-top" style="padding:10px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--c-border); background:var(--c-surf2);">
                <div style="font-size:13px; color:var(--c-subtle);"><span id="ld-total-info">0 total</span> | Page <span id="ld-page-info">1 of 1</span></div>
                <div id="ld-page-numbers" style="display:flex; gap:5px;"></div>
            </div>

            <div style="overflow-x:auto;">
                <table class="seo-table no-filter" id="seo-leads-tbl" style="min-width:1900px;">
                    <thead><tr>
                        <th style="width:36px;text-align:center;"><input type="checkbox" id="ld-check-all"></th>
                        <th style="width:40px;text-align:center;">Sr</th>
                        <th style="width:160px;">Name</th>
                        <th style="width:180px;">Email</th>
                        <th style="width:120px;">Phone</th>
                        <th style="width:110px;">Message</th>
                        <th style="width:80px;">Zip</th>
                        <th style="width:160px;">Date &amp; Time</th>
                        <th style="width:190px;">Status</th>
                        <th style="width:140px;">Notes</th>
                        <th style="width:50px;text-align:center;">Del</th>
                    </tr></thead>
                    <tbody id="ld-tbody">
                        <?php 
                        $count = 0;
                        if (empty($rows)) : ?>
                        <tr class="ld-empty-row"><td colspan="10" style="text-align:center;padding:24px;color:var(--c-subtle);">No leads found.</td></tr>
                        <?php else : foreach ($rows as $row) : 
                            $is_trashed = !empty($row['trashed']);
                            $count++;
                        ?>
                        <tr class="ld-row db-row-type-leads <?php echo $is_trashed ? 'db-generic-trashed-row' : 'db-generic-active-row'; ?>" data-id="<?php echo intval($row['id']); ?>" data-type="leads" style="<?php echo $is_trashed ? 'display:none;background:rgba(239,68,68,0.15);' : ''; ?>">
                            <td style="text-align:center;"><input type="checkbox" class="ld-chk" value="<?php echo intval($row['id']); ?>"></td>
                            <td style="text-align:center;color:var(--c-muted);font-size:12px;"><?php echo $count; ?></td>
                            <td><input type="text" class="seo-in seo-ld-inline-input ld-searchable" data-field="name" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['name']); ?>" style="width:100%;font-size:11px;padding:2px 4px;"></td>
                            <td><input type="text" class="seo-in seo-ld-inline-input ld-searchable" data-field="email" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['email']); ?>" style="width:100%;font-size:11px;padding:2px 4px;"></td>
                            <td><input type="text" class="seo-in seo-ld-inline-input ld-searchable" data-field="phone" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['phone']); ?>" style="width:100%;font-size:11px;padding:2px 4px;"></td>
                            <td data-export-val="<?php echo esc_attr($row['message'] ?? ''); ?>">
                                <?php if (!empty($row['message'])) : ?>
                                <button type="button" class="seo-ld-admin-view-msg-btn" data-id="<?php echo intval($row['id']); ?>" data-msg="<?php echo esc_attr($row['message']); ?>" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:3px 9px;font-size:11px;color:var(--c-text);cursor:pointer;white-space:nowrap;">👁 View</button>
                                <?php else : ?>
                                <span style="color:var(--c-muted);font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><input type="text" class="seo-in seo-ld-inline-input" data-field="zip" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['zip']); ?>" style="width:100%;font-size:11px;padding:2px 4px;"></td>
                            <td data-export-val="<?php echo esc_attr(trim($row['lead_date']. ' ' .$row['lead_time'])); ?>">
                                <div style="display:flex;gap:4px;align-items:center;">
                                    <input type="date" class="seo-in seo-ld-inline-input" data-field="lead_date" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['lead_date']); ?>" style="flex:1;min-width:0;font-size:11px;padding:2px 4px;">
                                    <input type="time" class="seo-in seo-ld-inline-input" data-field="lead_time" data-id="<?php echo intval($row['id']); ?>" value="<?php echo esc_attr($row['lead_time']); ?>" style="flex:1;min-width:0;font-size:11px;padding:2px 4px;">
                                </div>
                            </td>
                            <td data-export-val="<?php echo esc_attr($row['status'] ?: 'new'); ?>">
                                <select class="seo-in seo-ld-inline-input ld-status-sel" data-field="status" data-id="<?php echo intval($row['id']); ?>" style="width:100%;min-width:175px;font-size:11px;padding:3px 6px;">
                                    <?php foreach ($ld_admin_statuses as $stk => $stv) : ?>
                                    <option value="<?php echo esc_attr($stk); ?>" <?php selected($row['status'], $stk); ?> data-color="<?php echo esc_attr($stv['color']); ?>"><?php echo esc_html(($stv['icon'] ?? '🏷️') . ' ' . $stv['label']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_custom__">➕ + Add Custom Status...</option>
                                </select>
                            </td>
                            <td data-export-val="<?php echo esc_attr($row['notes'] ?? ''); ?>">
                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                                <?php if (!empty($row['notes'])) : ?>
                                <button type="button" class="seo-ld-admin-note-btn seo-ld-admin-note-view-btn" data-id="<?php echo intval($row['id']); ?>" data-notes="<?php echo esc_attr($row['notes']); ?>" data-mode="view" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:3px 9px;font-size:11px;color:var(--c-text);cursor:pointer;white-space:nowrap;">👁 View</button>
                                <button type="button" class="seo-ld-admin-note-btn seo-ld-admin-note-edit-btn" data-id="<?php echo intval($row['id']); ?>" data-notes="<?php echo esc_attr($row['notes']); ?>" data-mode="edit" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:3px 9px;font-size:11px;color:var(--c-text);cursor:pointer;white-space:nowrap;">📝 Edit</button>
                                <?php else : ?>
                                <button type="button" class="seo-ld-admin-note-btn seo-ld-admin-note-edit-btn" data-id="<?php echo intval($row['id']); ?>" data-notes="" data-mode="edit" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:3px 9px;font-size:11px;color:var(--c-text);cursor:pointer;white-space:nowrap;">+ Add</button>
                                <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <button class="seo-icon-btn seo-icon-btn-d seo-del-row-btn" data-table="leads" data-id="<?php echo intval($row['id']); ?>">🗑️</button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Message View Modal -->
        <div id="seo-ld-admin-msg-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.85);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);">
            <div style="background:var(--c-surf);border:1px solid var(--c-border);border-radius:14px;width:100%;max-width:540px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center;background:var(--c-surf2);">
                    <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--c-text);">💬 Lead Message</h3>
                    <button type="button" onclick="document.getElementById('seo-ld-admin-msg-modal').style.display='none'" style="width:30px;height:30px;border-radius:50%;background:var(--c-surf);border:1px solid var(--c-border);color:var(--c-text);cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;">✕</button>
                </div>
                <div style="padding:22px 24px;">
                    <p id="seo-ld-admin-msg-body" style="margin:0;color:var(--c-text);font-size:14px;line-height:1.7;white-space:pre-wrap;word-break:break-word;"></p>
                </div>
            </div>
        </div>

        <!-- Notes View/Edit Modal -->
        <div id="seo-ld-admin-note-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.85);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);">
            <div style="background:var(--c-surf);border:1px solid var(--c-border);border-radius:14px;width:100%;max-width:500px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center;background:var(--c-surf2);">
                    <h3 id="seo-ld-admin-note-modal-title" style="margin:0;font-size:15px;font-weight:700;color:var(--c-text);">📝 Lead Notes</h3>
                    <button type="button" onclick="seoLdAdminCloseNoteModal()" style="width:30px;height:30px;border-radius:50%;background:var(--c-surf);border:1px solid var(--c-border);color:var(--c-text);cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;">✕</button>
                </div>
                <div style="padding:22px 24px;">
                    <div id="seo-ld-admin-note-view-body" style="display:none;background:var(--c-surf2);border-radius:10px;padding:12px 14px;font-size:14px;color:var(--c-text);line-height:1.7;white-space:pre-wrap;word-break:break-word;margin-bottom:0;"></div>
                    <textarea id="seo-ld-admin-note-input" rows="5" placeholder="Add notes here…" style="display:none;width:100%;box-sizing:border-box;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:12px 14px;font-size:13px;color:var(--c-text);resize:vertical;outline:none;font-family:inherit;line-height:1.6;"></textarea>
                    <div id="seo-ld-admin-note-edit-row" style="display:none;justify-content:flex-end;gap:10px;margin-top:14px;">
                        <button type="button" onclick="seoLdAdminCloseNoteModal()" style="padding:8px 18px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;font-size:13px;font-weight:600;color:var(--c-text);cursor:pointer;">Cancel</button>
                        <button type="button" id="seo-ld-admin-note-save-btn" onclick="seoLdAdminSaveNote()" style="padding:8px 20px;background:var(--c-primary,#6366f1);border:none;border-radius:8px;font-size:13px;font-weight:700;color:#fff;cursor:pointer;">Save Note</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
        seoJQ(function($){
            // Build the searchable text for one row (name, email, phone, zip,
            // message, notes and status — covers everything the admin can
            // search by).
            function ldRowText($row) {
                var txt = '';
                $row.find('.ld-searchable').each(function(){
                    var $f = $(this);
                    txt += (($f.is('input,textarea,select')) ? $f.val() : $f.text()) + ' ';
                });
                txt += ($row.find('input[data-field="zip"]').val() || '') + ' ';
                txt += ($row.find('.seo-ld-admin-view-msg-btn').data('msg') || '') + ' ';
                txt += ($row.find('.seo-ld-admin-note-btn').first().data('notes') || '') + ' ';
                txt += $row.find('.ld-status-sel option:selected').text();
                return txt.toLowerCase();
            }

            // Search — just resets to page 1 and lets ldShowPage() do the
            // actual filtering, so search and pagination never fight each other.
            $('#ld-search').on('input', function(){ ldShowPage(1); });

            // Message view modal
            $(document).on('click', '.seo-ld-admin-view-msg-btn', function(){
                $('#seo-ld-admin-msg-body').text($(this).data('msg') || '');
                $('#seo-ld-admin-msg-modal').css('display', 'flex');
            });
            $('#seo-ld-admin-msg-modal').on('click', function(e){
                if (e.target === this) $(this).hide();
            });

            // Notes view/edit modal
            var ldAdminNoteId = 0;
            $(document).on('click', '.seo-ld-admin-note-btn', function(){
                ldAdminNoteId = $(this).data('id');
                var mode  = $(this).data('mode') || 'edit';
                var notes = $(this).data('notes') || '';
                if (mode === 'view') {
                    $('#seo-ld-admin-note-modal-title').text('📝 View Note');
                    $('#seo-ld-admin-note-view-body').text(notes).show();
                    $('#seo-ld-admin-note-input').hide();
                    $('#seo-ld-admin-note-edit-row').hide();
                } else {
                    $('#seo-ld-admin-note-modal-title').text('📝 Lead Notes');
                    $('#seo-ld-admin-note-view-body').hide();
                    $('#seo-ld-admin-note-input').val(notes).show();
                    $('#seo-ld-admin-note-edit-row').css('display', 'flex');
                }
                $('#seo-ld-admin-note-modal').css('display', 'flex');
            });
            $('#seo-ld-admin-note-modal').on('click', function(e){
                if (e.target === this) seoLdAdminCloseNoteModal();
            });
            window.seoLdAdminCloseNoteModal = function(){
                $('#seo-ld-admin-note-modal').hide();
            };
            window.seoLdAdminSaveNote = function(){
                var notes = $('#seo-ld-admin-note-input').val();
                var $btn  = $('#seo-ld-admin-note-save-btn');
                $btn.prop('disabled', true).text('Saving…');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_save_lead_field',
                    nonce: seoDash.nonce,
                    row_id: ldAdminNoteId,
                    field: 'notes',
                    val: notes
                }, function(r){
                    $btn.prop('disabled', false).text('Save Note');
                    if (!r || !r.success) { seoToast('Failed to save note.', 'err'); return; }
                    var $row = $('.ld-row[data-id="' + ldAdminNoteId + '"]');
                    var $cell = $row.find('.seo-ld-admin-note-btn').first().closest('td');
                    $cell.attr('data-export-val', notes);
                    if (notes) {
                        $cell.html(
                            '<div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">' +
                            '<button type="button" class="seo-ld-admin-note-btn seo-ld-admin-note-view-btn" data-id="' + ldAdminNoteId + '" data-notes="' + notes.replace(/"/g,'&quot;') + '" data-mode="view" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:3px 9px;font-size:11px;color:var(--c-text);cursor:pointer;white-space:nowrap;">👁 View</button>' +
                            '<button type="button" class="seo-ld-admin-note-btn seo-ld-admin-note-edit-btn" data-id="' + ldAdminNoteId + '" data-notes="' + notes.replace(/"/g,'&quot;') + '" data-mode="edit" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:3px 9px;font-size:11px;color:var(--c-text);cursor:pointer;white-space:nowrap;">📝 Edit</button>' +
                            '</div>'
                        );
                    } else {
                        $cell.html(
                            '<div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">' +
                            '<button type="button" class="seo-ld-admin-note-btn seo-ld-admin-note-edit-btn" data-id="' + ldAdminNoteId + '" data-notes="" data-mode="edit" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:3px 9px;font-size:11px;color:var(--c-text);cursor:pointer;white-space:nowrap;">+ Add</button>' +
                            '</div>'
                        );
                    }
                    seoLdAdminCloseNoteModal();
                    seoToast('Note saved.', 'ok');
                }).fail(function(){
                    $btn.prop('disabled', false).text('Save Note');
                    seoToast('Network error.', 'err');
                });
            };

            // Pagination (50 per page) — filters first, then paginates the
            // filtered result, so search results are never hidden by a stale
            // page slice and the page buttons always reflect what's on screen.
            var perPage = 50;
            var curPage = 1;

            function ldShowPage(page) {
                var allRows  = $('#ld-tbody tr.ld-row.db-generic-active-row').toArray();
                var term     = $.trim($('#ld-search').val() || '').toLowerCase();
                var filtered = term ? allRows.filter(function(r){ return ldRowText($(r)).indexOf(term) > -1; }) : allRows;

                var totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
                if (page < 1) page = 1;
                if (page > totalPages) page = totalPages;
                curPage = page;

                // Hide everything, then reveal only the current page's slice
                // of the FILTERED set.
                $(allRows).hide();
                var start = (page - 1) * perPage;
                var end   = start + perPage;
                $(filtered.slice(start, end)).show();

                $('#ld-total-info').text(filtered.length + ' total');
                $('#ld-page-info').text((filtered.length ? page : 0) + ' of ' + totalPages);

                var html = '';
                html += '<button class="seo-btn seo-btn-ghost seo-btn-sm ld-page-btn" data-p="1" style="min-width:32px;padding:4px;" '+(page===1?'disabled':'')+'>«</button>';
                html += '<button class="seo-btn seo-btn-ghost seo-btn-sm ld-page-btn" data-p="'+(page-1)+'" style="min-width:32px;padding:4px;" '+(page===1?'disabled':'')+'>‹</button>';

                var pStart = Math.max(1, page - 2);
                var pEnd = Math.min(totalPages, page + 2);

                for(var i = pStart; i <= pEnd; i++) {
                    var style = i === page ? 'background:var(--c-primary);color:#fff;border-color:var(--c-primary);' : 'background:transparent;';
                    html += '<button class="seo-btn seo-btn-ghost seo-btn-sm ld-page-btn" data-p="'+i+'" style="min-width:32px;padding:4px;'+style+'">'+i+'</button>';
                }

                html += '<button class="seo-btn seo-btn-ghost seo-btn-sm ld-page-btn" data-p="'+(page+1)+'" style="min-width:32px;padding:4px;" '+(page===totalPages?'disabled':'')+'>›</button>';
                html += '<button class="seo-btn seo-btn-ghost seo-btn-sm ld-page-btn" data-p="'+totalPages+'" style="min-width:32px;padding:4px;" '+(page===totalPages?'disabled':'')+'>»</button>';

                $('#ld-page-numbers').html(html);
            }
            // Exposed so other handlers (add lead, delete row, bulk actions)
            // can refresh the table + pagination after changing row counts.
            window.seoLdRefreshLeadsTable = function(){ ldShowPage(curPage); };

            $(document).on('click', '.ld-page-btn', function(){
                var p = parseInt($(this).data('p'));
                if(!isNaN(p)) ldShowPage(p);
            });

            if($('#ld-tbody tr.ld-row.db-generic-active-row').length > 0) ldShowPage(1);

            // Check All
            $('#ld-check-all').on('change', function(){
                $('.ld-chk:visible').prop('checked', $(this).prop('checked'));
            });

            // Bulk Actions
            $('#ld-bulk-btn').on('click', function(){
                var action = $('#ld-bulk-sel').val();
                var ids = [];
                $('.ld-chk:checked').each(function(){ ids.push($(this).val()); });
                
                if(!action) return alert('Select an action first');
                if(action !== 'delete_all' && ids.length === 0) return alert('Select rows first');
                if(action === 'delete_all' && !confirm('Are you sure you want to permanently delete ALL leads? This action cannot be undone.')) return;
                if(action === 'delete' && !confirm('Permanently delete selected leads?')) return;
                
                var $btn = $(this); $btn.prop('disabled', true).text('...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_bulk_data_action',
                    nonce: seoDash.nonce,
                    table: 'leads',
                    bulk_action: action,
                    ids: ids,
                    report_id: <?php echo $rid; ?>
                }, function(r){
                    if(r.success) location.reload();
                    else { alert(seoGaErrMsg(r)); $btn.prop('disabled', false).text('Apply'); }
                });
            });

            // Custom Status prompt handler for Admin
            $(document).on('change', '#ld-bulk-status-sel, .ld-status-sel', function() {
                var $el = $(this);
                if ($el.val() === '__add_custom__') {
                    var newName = prompt('Enter New Custom Lead Status Name:');
                    if (!newName || !newName.trim()) {
                        $el.val('');
                        return;
                    }
                    $.post(seoDash.ajax, {
                        action: 'seo_dash_add_custom_lead_status',
                        nonce: seoDash.nonce,
                        report_id: <?php echo (int)$rid; ?>,
                        name: newName.trim()
                    }, function(res) {
                        if (res && res.success && res.data) {
                            var slug = res.data.slug;
                            var label = res.data.label;
                            var icon = res.data.icon || '🏷️';
                            var color = res.data.color || '#3b82f6';

                            $('#ld-bulk-status-sel, .ld-status-sel').each(function() {
                                var $s = $(this);
                                var $addOpt = $s.find('option[value="__add_custom__"]');
                                var $newOpt = $('<option>', { value: slug, text: (icon ? icon + ' ' : '') + label }).attr('data-color', color);
                                if ($addOpt.length) $newOpt.insertBefore($addOpt);
                                else $s.append($newOpt);
                            });

                            $el.val(slug);
                            if ($el.hasClass('ld-status-sel')) {
                                $.post(seoDash.ajax, {
                                    action: 'seo_dash_save_lead_field',
                                    nonce: seoDash.nonce,
                                    row_id: $el.data('id'),
                                    field: $el.data('field'),
                                    val: slug
                                });
                            }
                        } else {
                            $el.val('');
                        }
                    });
                }
            });

            // Bulk Status Change
            $('#ld-bulk-status-btn').on('click', function(){
                var stat = $('#ld-bulk-status-sel').val();
                var ids = [];
                $('.ld-chk:checked').each(function(){ ids.push($(this).val()); });
                if(!stat || ids.length === 0) return alert('Select a status and rows first');
                
                var $btn = $(this); $btn.prop('disabled', true).text('...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_bulk_leads_status',
                    nonce: seoDash.nonce,
                    status: stat,
                    ids: ids
                }, function(r){
                    if(r.success) location.reload();
                    else { alert(seoGaErrMsg(r)); $btn.prop('disabled', false).text('Update'); }
                });
            });

            // Inline Input Save
            $(document).on('change', '.seo-ld-inline-input', function(){
                var $el = $(this);
                $.post(seoDash.ajax, {
                    action: 'seo_dash_save_lead_field',
                    nonce: seoDash.nonce,
                    row_id: $el.data('id'),
                    field: $el.data('field'),
                    val: $el.val()
                });
                // Mark this row's status as "known" so the poller below won't
                // immediately stomp the admin's own just-made edit.
                if ($el.hasClass('ld-status-sel')) {
                    $el.data('known-status', $el.val());
                }
            });

            /* ══ Leads: auto-sync status changes made by the client ══════════
               Polls every 12s for the current status of every lead in this
               report and patches the admin table + KPI cards in place when
               a client (or anything else) changes a status elsewhere, so the
               admin never has to guess whether what they're looking at is
               stale — no manual refresh required. */
            var ldPollRid = <?php echo (int) $rid; ?>;
            var ldStatusLabels = {
                new: '🔠 New', contacted: '📞 Contacted', checking: '🔍 Checking',
                qualified: '✅ Qualified', converted: '🎉 Converted', lost: '❌ Lost'
            };
            var ldStatusOrder = ['new','contacted','checking','qualified','converted','lost'];
            var ldPollInFlight = false;
            var ldPollTimer = null;

            function ldRecountKpis() {
                var counts = { new:0, contacted:0, checking:0, qualified:0, converted:0, lost:0 };
                var total = 0;
                $('#ld-tbody tr.ld-row.db-generic-active-row').each(function(){
                    var sel = $(this).find('.ld-status-sel');
                    if (!sel.length) return;
                    var st = sel.val() || 'new';
                    if (counts[st] !== undefined) counts[st]++;
                    total++;
                });
                $('#ld-kpi-row .seo-kpi-card').each(function(){
                    var key = $(this).data('kpi');
                    var val = (key === 'total') ? total : (counts[key] !== undefined ? counts[key] : 0);
                    $(this).find('.seo-kpi-val').text(val);
                });
            }

            function ldPollStatuses() {
                if (ldPollInFlight || !ldPollRid) return;
                // Don't disrupt an admin who currently has a status dropdown
                // open/focused — wait for the next cycle instead.
                if ($('.ld-status-sel:focus').length) return;

                ldPollInFlight = true;
                $.post(seoDash.ajax, {
                    action: 'seo_dash_get_lead_statuses',
                    nonce: seoDash.nonce,
                    report_id: ldPollRid
                }, function(r){
                    ldPollInFlight = false;
                    if (!r || !r.success || !r.data) return;
                    var changed = false;

                    Object.keys(r.data).forEach(function(id){
                        var info = r.data[id];
                        var $row = $('#ld-tbody tr.ld-row[data-id="' + id + '"]');
                        if (!$row.length) return; // row deleted/added — handled on next manual reload

                        var $sel = $row.find('.ld-status-sel');
                        if ($sel.length && $sel.val() !== info.status && !$sel.is(':focus')) {
                            $sel.val(info.status);
                            $sel.data('known-status', info.status);
                            changed = true;
                            // Brief highlight so the admin notices what just moved.
                            $row.css('background-color', 'rgba(16,185,129,0.18)');
                            setTimeout(function(){
                                $row.css('background-color', $row.hasClass('db-generic-trashed-row') ? 'rgba(239,68,68,0.15)' : '');
                            }, 1600);
                        }

                        // Reflect trash state toggled elsewhere (rare, but keep in sync).
                        var isTrashedNow = $row.hasClass('db-generic-trashed-row');
                        if (info.trashed !== isTrashedNow) {
                            $row.toggleClass('db-generic-trashed-row', info.trashed)
                                .toggleClass('db-generic-active-row', !info.trashed)
                                .css('display', info.trashed ? 'none' : '');
                            changed = true;
                        }
                    });

                    if (changed) ldRecountKpis();
                });
            }

            if ($('#ld-tbody').length) {
                ldPollTimer = setInterval(ldPollStatuses, 12000);
                // Stop polling if the admin navigates away from this tab/page.
                $(window).on('beforeunload', function(){ if (ldPollTimer) clearInterval(ldPollTimer); });
            }

            // Add Lead
            $('#seo-add-lead-btn').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled', true).text('Adding...');
                $.post(seoDash.ajax, {
                    action: 'seo_dash_add_lead',
                    nonce: seoDash.nonce,
                    report_id: <?php echo $rid; ?>
                }, function(r){
                    if(r.success) {
                        var id = r.data.id;
                        var today = new Date().toISOString().split('T')[0];
                        var html = '<tr class="ld-row db-row-type-leads db-generic-active-row" data-id="'+id+'" data-type="leads">' +
                            '<td style="text-align:center;"><input type="checkbox" class="ld-chk" value="'+id+'"></td>' +
                            '<td style="text-align:center;color:var(--c-muted);font-size:12px;">New</td>' +
                            '<td><input type="text" class="seo-in seo-ld-inline-input ld-searchable" data-field="name" data-id="'+id+'" style="width:100%;font-size:11px;padding:2px 4px;"></td>' +
                            '<td><input type="text" class="seo-in seo-ld-inline-input ld-searchable" data-field="phone" data-id="'+id+'" style="width:100%;font-size:11px;padding:2px 4px;"></td>' +
                            '<td><input type="text" class="seo-in seo-ld-inline-input ld-searchable" data-field="email" data-id="'+id+'" style="width:100%;font-size:11px;padding:2px 4px;"></td>' +
                            '<td><input type="text" class="seo-in seo-ld-inline-input" data-field="zip" data-id="'+id+'" style="width:100%;font-size:11px;padding:2px 4px;"></td>' +
                            '<td data-export-val=""><span style="color:var(--c-muted);font-size:11px;">—</span></td>' +
                            '<td data-export-val=""><span style="color:var(--c-muted);font-size:11px;">—</span></td>' +
                            '<td><input type="date" class="seo-in seo-ld-inline-input" data-field="lead_date" data-id="'+id+'" value="'+today+'" style="width:100%;font-size:11px;padding:2px 4px;"></td>' +
                            '<td><input type="time" class="seo-in seo-ld-inline-input" data-field="lead_time" data-id="'+id+'" style="width:100%;font-size:11px;padding:2px 4px;"></td>' +
                            '<td><input type="text" class="seo-in seo-ld-inline-input" data-field="source" data-id="'+id+'" style="width:100%;font-size:11px;padding:2px 4px;"></td>' +
                            '<td><div style="display:flex;align-items:center;gap:4px;"><input type="text" class="seo-in seo-ld-inline-input" data-field="page_url" data-id="'+id+'" style="flex:1;font-size:11px;padding:2px 4px;"></div></td>' +
                            '<td data-export-val="new">' +
                                '<select class="seo-in seo-ld-inline-input ld-status-sel" data-field="status" data-id="'+id+'" style="width:100%;min-width:175px;padding:3px 6px;font-size:11px; color:#3b82f6;">' +
                                    <?php foreach ($ld_admin_statuses as $stk => $stv) : ?>
                                    '<option value="<?php echo esc_js($stk); ?>" data-color="<?php echo esc_js($stv['color']); ?>"><?php echo esc_js(($stv['icon'] ?? '🏷️') . ' ' . $stv['label']); ?></option>' +
                                    <?php endforeach; ?>
                                    '<option value="__add_custom__">➕ + Add Custom Status...</option>' +
                                '</select>' +
                            '</td>' +
                            '<td data-export-val=""><div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;"><button type="button" class="seo-ld-admin-note-btn seo-ld-admin-note-edit-btn" data-id="'+id+'" data-notes="" data-mode="edit" style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:3px 9px;font-size:11px;color:var(--c-text);cursor:pointer;white-space:nowrap;">+ Add</button></div></td>' +
                            '<td style="text-align:center;"><button class="seo-icon-btn seo-icon-btn-d seo-del-row-btn" data-table="leads" data-id="'+id+'">🗑️</button></td>' +
                        '</tr>';
                        $('#ld-tbody .ld-empty-row').remove();
                        $('#ld-tbody').prepend(html);
                        $btn.prop('disabled', false).text('➕ Add Lead');
                        if (typeof ldShowPage === 'function') {
                            ldShowPage(1);
                        }
                    } else {
                        alert(seoGaErrMsg(r));
                        $btn.prop('disabled', false).text('➕ Add Lead');
                    }
                });
            });
        });
        </script>
        </div><!-- /#seo-leads-pane-forms -->

        <!-- ── Sub-Pane 2: Click Tracking ─────────────────────────── -->
        <div id="seo-leads-pane-clicks" style="display:none;padding:0 20px 20px;">
            <div class="seo-panel-hd" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <h2>🖱️ Click Tracking</h2>
                    <span style="font-size:12px;color:var(--c-muted);">Track user clicks on buttons, keywords, phone numbers & CTAs</span>
                </div>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <!-- Google Sheet Sync -->
                    <div id="gsheet-sync-bar-click_tracking" style="display:flex;align-items:center;gap:6px;">
                        <?php $link = $gsheet_links['click_tracking'] ?? []; if ( !empty($link['spreadsheet_id']) ) : ?>
                        <span style="font-size:11px;color:var(--c-muted);white-space:nowrap;">📅 <?php echo esc_html( $link['spreadsheet_name'] ?? 'Linked Sheet' ); ?> / <?php echo esc_html( $link['tab_name'] ?? '' ); ?></span>
                        <button class="seo-btn seo-btn-sm gsheet-sync-btn" data-tabtype="click_tracking" style="height:28px;font-size:11px;background:var(--c-primary);color:#fff;">🔄 Update</button>
                        <button class="seo-btn seo-btn-sm gsheet-export-btn" data-tabtype="click_tracking" style="height:28px;font-size:11px;background:var(--c-surf);color:var(--c-primary);border:1px solid var(--c-primary);">⬇ Export to Sheet</button>
                        <span class="gsheet-sync-status" data-tabtype="click_tracking" style="font-size:11px;color:var(--c-muted);"></span>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--c-muted);">⚠️ No sheet linked. Go to Integrations tab.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php
            $ct_rows = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_click_tracking, $rid, '', true);
            $total_ct = 0;
            $ct_types = [];
            $ct_pages = [];
            if(!empty($ct_rows)) {
                foreach($ct_rows as $r) {
                    if(empty($r['trashed'])) {
                        $total_ct++;
                        $t = strtolower($r['click_type'] ?: 'other');
                        $ct_types[$t] = ($ct_types[$t] ?? 0) + 1;
                        $p = $r['source_page'] ?: 'Direct';
                        $ct_pages[$p] = ($ct_pages[$p] ?? 0) + 1;
                    }
                }
            }
            arsort($ct_types);
            $top_type = !empty($ct_types) ? array_key_first($ct_types) : 'None';
            ?>

            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; padding:0;" id="ct-kpi-row">
                <div class="seo-kpi-card" style="flex:1; min-width:140px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid var(--c-primary);">
                    <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo $total_ct; ?></div>
                    <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">Total Click Events</div>
                </div>
                <div class="seo-kpi-card" style="flex:1; min-width:140px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #3b82f6;">
                    <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo count($ct_pages); ?></div>
                    <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">Source Pages</div>
                </div>
                <div class="seo-kpi-card" style="flex:1; min-width:140px; background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #10b981;">
                    <div class="seo-kpi-val" style="font-size:24px;font-weight:700;color:var(--c-text);"><?php echo esc_html(ucwords(str_replace('_', ' ', $top_type))); ?></div>
                    <div class="seo-kpi-lbl" style="font-size:11px;color:var(--c-muted);font-weight:500;text-transform:uppercase;letter-spacing:0.5px;">Top Click Type</div>
                </div>
            </div>

            <div class="seo-table-wrap">
                <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center; flex-wrap:wrap; gap:8px;">
                    <select class="seo-in seo-in-sm" id="ct-bulk-sel" style="width:auto;padding:4px 8px;font-size:12px;">
                        <option value="">Bulk Actions</option>
                        <option value="trash">Move Selected to Trash</option>
                        <option value="restore">Restore Selected from Trash</option>
                        <option value="delete">Delete Permanently</option>
                        <option value="delete_all" style="color:var(--c-red);">Delete All</option>
                    </select>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm" id="ct-bulk-btn" data-type="click_tracking">Apply</button>
                    
                    <span style="display:inline-block;width:1px;height:20px;background:var(--c-border);margin:0 4px;"></span>
                    <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-add-ct-btn">➕ Add Click Record</button>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="seo-click-tracking-tbl">⬇️ Export</button>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="click_tracking">⬇️ Format</button>
                    <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">
                        ⬆️ Import
                        <input type="file" class="seo-import-csv-input" data-type="click_tracking" accept=".csv" style="display:none;">
                    </label>
                    <button class="seo-btn seo-btn-ghost seo-btn-sm db-generic-view-trash-btn" data-type="click_tracking">🗑️ Trash</button>
                </div>
                <div style="padding:10px 20px; border-bottom:1px solid var(--c-border); background:var(--c-surf2);">
                    <input type="text" id="ct-search" class="seo-in seo-in-sm" placeholder="Search click tracking..." style="width:100%; max-width:400px; font-size:13px; padding:6px 10px;">
                </div>

                <div style="overflow-x:auto;width:100%;">
                    <table class="seo-table no-filter" id="seo-click-tracking-tbl" style="min-width:1380px;">
                        <thead><tr>
                            <th style="width:36px;text-align:center;"><input type="checkbox" id="ct-check-all"></th>
                            <th style="width:40px;text-align:center;">Sr</th>
                            <th style="width:230px;">Text / Keyword</th>
                            <th style="width:300px;">Source Page</th>
                            <th style="width:150px;">Click Type</th>
                            <th style="width:150px;text-align:center;">Status</th>
                            <th style="width:140px;">Submitteddate</th>
                            <th style="width:150px;min-width:150px;">Time</th>
                            <th style="width:50px;text-align:center;">Del</th>
                        </tr></thead>
                        <tbody id="ct-tbody">
                            <?php 
                            $ct_count = 0;
                            if (empty($ct_rows)) : ?>
                            <tr class="ct-empty-row"><td colspan="9" style="text-align:center;padding:24px;color:var(--c-subtle);">No click tracking data found.</td></tr>
                            <?php else : foreach ($ct_rows as $crow) : 
                                $is_ct_trashed = !empty($crow['trashed']);
                                $ct_count++;
                                $c_st_val = strtolower($crow['status'] ?? 'new');
                                $c_st_info = $ld_admin_statuses[$c_st_val] ?? ['color'=>'#3b82f6','label'=>ucfirst($c_st_val),'icon'=>'🏷️'];
                            ?>
                            <tr class="ct-row db-row-type-click_tracking <?php echo $is_ct_trashed ? 'db-generic-trashed-row' : 'db-generic-active-row'; ?>" data-id="<?php echo intval($crow['id']); ?>" data-type="click_tracking" style="<?php echo $is_ct_trashed ? 'display:none;background:rgba(239,68,68,0.15);' : ''; ?>">
                                <td style="text-align:center;"><input type="checkbox" class="ct-chk" value="<?php echo intval($crow['id']); ?>"></td>
                                <td style="text-align:center;color:var(--c-muted);font-size:12px;"><?php echo $ct_count; ?></td>
                                <td><input type="text" class="seo-in seo-ct-inline-input ct-searchable" data-field="keyword_text" data-id="<?php echo intval($crow['id']); ?>" value="<?php echo esc_attr($crow['keyword_text'] ?? ''); ?>" style="width:100%;font-size:11.5px;padding:2px 6px;"></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <input type="text" class="seo-in seo-ct-inline-input ct-searchable" data-field="source_page" data-id="<?php echo intval($crow['id']); ?>" value="<?php echo esc_attr($crow['source_page'] ?? ''); ?>" placeholder="https://..." style="flex:1;font-size:11.5px;padding:2px 6px;">
                                        <?php if (!empty($crow['source_page'])) : ?>
                                        <a href="<?php echo esc_url($crow['source_page']); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;font-size:11px;font-weight:700;border-radius:6px;background:var(--c-surf2);border:1px solid var(--c-border);color:var(--c-primary);text-decoration:none;white-space:nowrap;">Visit ↗</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="seo-in seo-ct-inline-input ct-searchable" data-field="click_type" data-id="<?php echo intval($crow['id']); ?>" value="<?php echo esc_attr($crow['click_type'] ?? ''); ?>" placeholder="e.g. Phone Click, CTA" style="width:100%;font-size:11.5px;padding:2px 6px;">
                                </td>
                                <td>
                                    <select class="seo-in seo-ct-inline-input" data-field="status" data-id="<?php echo intval($crow['id']); ?>" style="width:100%;font-size:11.5px;padding:2px 4px;font-weight:600;color:<?php echo esc_attr($c_st_info['color']); ?>;">
                                        <?php foreach ($ld_admin_statuses as $stk => $stv) : ?>
                                        <option value="<?php echo esc_attr($stk); ?>" <?php selected($c_st_val, $stk); ?>><?php echo esc_html(($stv['icon'] ?? '🏷️') . ' ' . $stv['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="date" class="seo-in seo-ct-inline-input ct-searchable" data-field="click_date" data-id="<?php echo intval($crow['id']); ?>" value="<?php echo esc_attr($crow['click_date'] ?? date('Y-m-d')); ?>" style="width:100%;font-size:11.5px;padding:2px 6px;"></td>
                                <td><input type="text" class="seo-in seo-ct-inline-input ct-searchable" data-field="click_time" data-id="<?php echo intval($crow['id']); ?>" value="<?php echo esc_attr($crow['click_time'] ?? ''); ?>" placeholder="15:28:44" style="width:100%;min-width:120px;font-size:12px;padding:3px 8px;box-sizing:border-box;"></td>
                                <td style="text-align:center;"><button class="seo-icon-btn seo-icon-btn-d seo-del-ct-btn" data-id="<?php echo intval($crow['id']); ?>">🗑️</button></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($){
                // Inline Edit for Click Tracking
                $(document).on('change', '.seo-ct-inline-input', function(){
                    var $input = $(this);
                    var id = $input.data('id');
                    var field = $input.data('field');
                    var val = $input.val();
                    $.post(seoDash.ajax, {
                        action: 'seo_dash_save_click_tracking_field',
                        nonce: seoDash.nonce,
                        row_id: id,
                        field: field,
                        val: val
                    }, function(r){
                        if(r.success) {
                            if (typeof seoToast === 'function') seoToast('Saved', 'ok');
                        } else {
                            if (typeof seoToast === 'function') seoToast(seoGaErrMsg(r), 'err');
                        }
                    });
                });

                // Add Click Record
                $('#seo-add-ct-btn').on('click', function(){
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Adding...');
                    $.post(seoDash.ajax, {
                        action: 'seo_dash_add_click_tracking',
                        nonce: seoDash.nonce,
                        report_id: <?php echo $rid; ?>
                    }, function(r){
                        if(r.success && r.data && r.data.id) {
                            var id = r.data.id;
                            var timeVal = r.data.click_time || '';
                            var html = '<tr class="ct-row db-row-type-click_tracking db-generic-active-row" data-id="'+id+'" data-type="click_tracking">' +
                                '<td style="text-align:center;"><input type="checkbox" class="ct-chk" value="'+id+'"></td>' +
                                '<td style="text-align:center;color:var(--c-muted);font-size:12px;">+</td>' +
                                '<td><input type="text" class="seo-in seo-ct-inline-input ct-searchable" data-field="keyword_text" data-id="'+id+'" value="New Keyword / CTA" style="width:100%;font-size:11.5px;padding:2px 6px;"></td>' +
                                '<td><input type="text" class="seo-in seo-ct-inline-input ct-searchable" data-field="source_page" data-id="'+id+'" value="" placeholder="https://..." style="width:100%;font-size:11.5px;padding:2px 6px;"></td>' +
                                '<td><input type="text" class="seo-in seo-ct-inline-input ct-searchable" data-field="click_type" data-id="'+id+'" value="button_click" style="width:100%;font-size:11.5px;padding:2px 6px;"></td>' +
                                '<td><select class="seo-in seo-ct-inline-input" data-field="status" data-id="'+id+'" style="width:100%;font-size:11.5px;padding:2px 4px;font-weight:600;"><option value="new">🔠 New</option><option value="contacted">📞 Contacted</option><option value="converted">🎉 Converted</option><option value="lost">❌ Lost</option></select></td>' +
                                '<td><input type="date" class="seo-in seo-ct-inline-input ct-searchable" data-field="click_date" data-id="'+id+'" value="<?php echo date('Y-m-d'); ?>" style="width:100%;font-size:11.5px;padding:2px 6px;"></td>' +
                                '<td><input type="text" class="seo-in seo-ct-inline-input ct-searchable" data-field="click_time" data-id="'+id+'" value="'+timeVal+'" placeholder="15:28:44" style="width:100%;min-width:120px;font-size:12px;padding:3px 8px;box-sizing:border-box;"></td>' +
                                '<td style="text-align:center;"><button class="seo-icon-btn seo-icon-btn-d seo-del-ct-btn" data-id="'+id+'">🗑️</button></td>' +
                            '</tr>';
                            $('#ct-tbody .ct-empty-row').remove();
                            $('#ct-tbody').prepend(html);
                            $btn.prop('disabled', false).text('➕ Add Click Record');
                        } else {
                            alert(seoGaErrMsg(r));
                            $btn.prop('disabled', false).text('➕ Add Click Record');
                        }
                    });
                });

                // Delete Click Record
                $(document).on('click', '.seo-del-ct-btn', function(){
                    if(!confirm('Permanently delete this click tracking record?')) return;
                    var $btn = $(this);
                    var id = $btn.data('id');
                    var $row = $btn.closest('tr');
                    $.post(seoDash.ajax, {
                        action: 'seo_dash_delete_click_tracking',
                        nonce: seoDash.nonce,
                        row_id: id
                    }, function(r){
                        if(r.success) {
                            $row.fadeOut(200, function(){ $(this).remove(); });
                            if (typeof seoToast === 'function') seoToast('Record deleted', 'ok');
                        } else {
                            if (typeof seoToast === 'function') seoToast(seoGaErrMsg(r), 'err');
                        }
                    });
                });

                // Click Tracking Search
                $('#ct-search').on('input', function(){
                    var q = $(this).val().toLowerCase().trim();
                    $('#ct-tbody tr.ct-row').each(function(){
                        if ($(this).hasClass('db-generic-trashed-row') && $(this).is(':hidden')) return;
                        var txt = $(this).text().toLowerCase();
                        $(this).find('input').each(function(){ txt += ' ' + $(this).val().toLowerCase(); });
                        if (q === '' || txt.indexOf(q) !== -1) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });

                // Check All for CT
                $('#ct-check-all').on('change', function(){
                    $('.ct-chk:visible').prop('checked', $(this).is(':checked'));
                });

                // Bulk Actions for Click Tracking
                $('#ct-bulk-btn').on('click', function(){
                    var action = $('#ct-bulk-sel').val();
                    if (!action) {
                        alert('Please select a bulk action.');
                        return;
                    }

                    var selectedIds = [];
                    $('.ct-chk:checked').each(function(){
                        var val = parseInt($(this).val(), 10);
                        if (val) selectedIds.push(val);
                    });

                    if (selectedIds.length === 0 && action !== 'delete_all') {
                        alert('Please select at least one record.');
                        return;
                    }

                    if ((action === 'delete' || action === 'delete_all') && !confirm('Are you sure you want to ' + (action === 'delete_all' ? 'permanently delete ALL click tracking records?' : 'permanently delete the selected click tracking records?'))) {
                        return;
                    }

                    var $btn = $(this);
                    var origText = $btn.text();
                    $btn.prop('disabled', true).text('Processing...');

                    $.post(seoDash.ajax, {
                        action: 'seo_dash_bulk_data_action',
                        nonce: seoDash.nonce,
                        table: 'click_tracking',
                        report_id: <?php echo $rid; ?>,
                        bulk_action: action,
                        ids: selectedIds
                    }, function(r){
                        $btn.prop('disabled', false).text(origText);
                        if (r.success) {
                            if (typeof seoToast === 'function') seoToast(r.data && r.data.message ? r.data.message : 'Bulk action completed.', 'ok');
                            location.reload();
                        } else {
                            alert('Error: ' + seoGaErrMsg(r));
                        }
                    }).fail(function(){
                        $btn.prop('disabled', false).text(origText);
                        alert('Server error.');
                    });
                });
            });
            </script>
        </div><!-- /#seo-leads-pane-clicks -->

        <!-- Sub-tab Switcher Handler -->
        <script>
        jQuery(document).ready(function($){
            $(document).on('click', '.seo-leads-subtab-btn', function(){
                var pane = $(this).data('pane');
                $('.seo-leads-subtab-btn').css({'border-bottom-color':'transparent','color':'var(--c-muted)'}).removeClass('active');
                $(this).css({'border-bottom-color':'var(--c-primary)','color':'var(--c-primary)'}).addClass('active');

                if (pane === 'forms') {
                    $('#seo-leads-pane-forms').show();
                    $('#seo-leads-pane-clicks').hide();
                } else if (pane === 'clicks') {
                    $('#seo-leads-pane-forms').hide();
                    $('#seo-leads-pane-clicks').show();
                }
            });
        });
        </script>
    </div>

<?php // ── INTEGRATIONS (with Sitemap sub-tab) ─────────────────────────────
elseif ($tab === 'integrations') :
    $cur_global_id = $report_global_intgs['global'] ?? '';
?>

<!-- ── Sub-tab bar ────────────────────────────────────────────────────── -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--c-border);margin-bottom:22px;">
    <button id="intg-stab-settings"
            style="padding:10px 22px;font-size:13px;font-weight:700;background:none;border:none;
                   border-bottom:3px solid var(--c-primary);margin-bottom:-2px;
                   color:var(--c-primary);cursor:pointer;">
        Integration Settings
    </button>
  
    <button id="intg-stab-gsheets"
            style="padding:10px 22px;font-size:13px;font-weight:700;background:none;border:none;
                   border-bottom:3px solid transparent;margin-bottom:-2px;
                   color:var(--c-muted);cursor:pointer;">
        📊 Google Sheets
    </button>
    <button id="intg-stab-chatbot"
            style="padding:10px 22px;font-size:13px;font-weight:700;background:none;border:none;
                   border-bottom:3px solid transparent;margin-bottom:-2px;
                   color:var(--c-muted);cursor:pointer;">
        🤖 Chatbot
    </button>
</div>

<!-- 
     PANEL A — Integration Settings
     -->
<div id="intg-panel-settings">

<!-- Integration assignment -->
<div class="seo-panel" style="margin-bottom:20px;">
    <div class="seo-panel-hd"><h2>🔌 Integration</h2></div>
    <div class="seo-panel-body" style="padding:24px;">
        <div class="seo-field" style="max-width:480px;">
            <label style="font-size:13px;font-weight:700;margin-bottom:8px;display:block;">Assigned Integration</label>
            <?php if (empty($all_global_integrations)) : ?>
            <p style="color:var(--c-muted);font-size:13px;">
                No integrations found.
                <a href="<?php echo esc_url(add_query_arg('seo_page','integrations',$base)); ?>" style="color:var(--c-primary);font-weight:600;">Create one →</a>
            </p>
            <?php else : ?>
            <div style="display:flex;gap:10px;align-items:center;">
                <select class="seo-in seo-rpt-global-intg-sel" id="seo-intg-dropdown" data-rid="<?php echo $rid; ?>" data-scope="global" style="flex:1;">
                    <option value="">— None —</option>
                    <?php foreach ($all_global_integrations as $intg) :
                        $intg_id = $intg['id'] ?? '';
                        if (!$intg_id) continue;
                    ?>
                    <option value="<?php echo esc_attr($intg_id); ?>" <?php selected($cur_global_id, $intg_id); ?>><?php echo esc_html($intg['name'] ?? 'Unnamed'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-rpt-save-intg-btn" data-rid="<?php echo $rid; ?>">&#128190; Save Integration</button>
            </div>
            <span id="intg-status-global" style="font-size:12px;color:var(--c-muted);margin-top:8px;display:block;"></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Clients table -->
<div class="seo-panel">
    <div class="seo-panel-hd" style="padding:16px 20px;">
        <h2>&#128101; Clients</h2>
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" class="seo-in seo-in-sm" id="seo-client-intg-search" placeholder="&#128269; Search clients&hellip;"
                   style="width:220px;" autocomplete="off" autocorrect="off" spellcheck="false">
            <button class="seo-btn seo-btn-primary seo-btn-sm" id="seo-assign-clients-btn" data-rid="<?php echo $rid; ?>">&#9989; Save Assignments</button>
        </div>
    </div>

    <?php if (empty($all_clients)) : ?>
    <div style="padding:40px;text-align:center;color:var(--c-muted);font-size:13px;">
        No clients found. <a href="<?php echo esc_url(add_query_arg('seo_page','clients',$base)); ?>" style="color:var(--c-primary);font-weight:600;">Add a client &rarr;</a>
    </div>
    <?php else : ?>
    <div style="overflow-x:auto;overflow-y:visible;">
        <div style="max-height:520px;overflow-y:auto;overflow-x:visible;">
            <table class="seo-table" id="seo-clients-full-tbl" style="min-width:960px;">
                <thead style="position:sticky;top:0;z-index:2;background:var(--c-surf2);">
                    <tr>
                        <th style="width:36px;padding:10px 12px;">
                            <input type="checkbox" id="seo-select-all-clients" style="accent-color:var(--c-primary);width:15px;height:15px;" title="Select all">
                        </th>
                        <th style="padding:10px 12px;">#</th>
                        <th style="padding:10px 12px;">Client Name</th>
                        <th style="padding:10px 12px;">Email</th>
                        <th style="padding:10px 12px;">Password</th>
                        <th style="padding:10px 12px;">Dashboard</th>
                        <th style="padding:10px 12px;">Assigned</th>
                        <th style="padding:10px 12px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all_clients as $idx => $c) :
                    $cid         = intval($c['id']);
                    $is_assigned = in_array($cid, $assigned_client_ids, false);
                    $pwd_val     = '';
                    $has_pwd     = false;
                    $wp_uid = intval($c['wp_user_id'] ?? 0);
                ?>
                <tr class="seo-client-full-row" data-cid="<?php echo $cid; ?>" data-wp-uid="<?php echo $wp_uid; ?>" data-name="<?php echo esc_attr($c['name']); ?>" data-search="<?php echo esc_attr(strtolower($c['name'].' '.($c['email']??''))); ?>">
                    <td style="padding:10px 12px;text-align:center;">

                        <input type="checkbox" class="seo-client-intg-chk" value="<?php echo $cid; ?>"
                               <?php checked($is_assigned); ?>
                               style="width:15px;height:15px;accent-color:var(--c-primary);">
                    </td>
                    <td style="padding:10px 12px;font-size:12px;color:var(--c-muted);"><?php echo $idx + 1; ?></td>

                    <!-- Name -->
                    <td style="padding:10px 12px;">
                        <span class="rcl-view-name" style="font-weight:700;font-size:13px;"><?php echo esc_html($c['name']); ?></span>
                        <input type="text" class="seo-in seo-in-sm rcl-edit-name" value="<?php echo esc_attr($c['name']); ?>" style="display:none;min-width:130px;" autocomplete="nope">
                    </td>

                    <!-- Email -->
                    <td style="padding:10px 12px;">
                        <span class="rcl-view-email" style="font-size:12px;color:var(--c-muted);"><?php echo esc_html($c['email'] ?? '—'); ?></span>
                        <input type="email" class="seo-in seo-in-sm rcl-edit-email" value="<?php echo esc_attr($c['email'] ?? ''); ?>" style="display:none;min-width:160px;" autocomplete="nope">
                    </td>

                    <!-- Password -->
                    <td style="padding:10px 12px;">
                        <div class="rcl-view-pwd" style="display:flex;align-items:center;gap:5px;">
                            <span class="rcl-pwd-stars" style="font-family:monospace;font-size:13px;letter-spacing:2px;">—</span>
                        </div>
                        <input type="text" class="seo-in seo-in-sm rcl-edit-pwd" value="" style="display:none;min-width:130px;" autocomplete="new-password" placeholder="Leave blank to keep">
                    </td>

                    <!-- Dashboard -->
                    <td style="padding:10px 12px;">
                        <?php if (!empty($c['dashboard_url'])) : ?>
                        <a href="<?php echo esc_url($c['dashboard_url']); ?>" target="_blank" style="font-size:12px;color:var(--c-blue);font-weight:600;">View →</a>
                        <?php else : ?>
                        <span style="color:var(--c-muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Assigned dropdown -->
                    <td style="padding:10px 12px;position:relative;overflow:visible;">
                        <button type="button" class="seo-btn seo-btn-ghost seo-btn-xs seo-assign-dropdown-btn" style="width:100%; display:flex; justify-content:space-between; align-items:center;">
                            <?php echo $is_assigned ? '✓ Assigned' : 'Assigned'; ?> <span style="font-size:8px;">▼</span>
                        </button>
                        <div class="seo-assign-dropdown" style="display:none; position:absolute; top:100%; left:0; min-width:220px; background:var(--c-surf2,#1e1e2e); border:1px solid var(--c-border,#333); z-index:9999; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,0.4); max-height:240px; overflow-y:auto; text-align:left; padding:6px;" onclick="event.stopPropagation();">
                            <div style="font-size:10px; font-weight:700; color:var(--c-muted); text-transform:uppercase; padding:4px 6px; border-bottom:1px solid var(--c-border); margin-bottom:4px;">Select Reports</div>
                            <?php 
                            if (!isset($all_reports)) $all_reports = SEO_Dash_Database::get_reports();
                            $c_rids = SEO_Dash_Database::get_client_report_ids(intval($cid));
                            if (empty($all_reports)): ?>
                                <div style="font-size:11px; padding:4px 6px; color:var(--c-muted);">No reports found.</div>
                            <?php else: foreach ($all_reports as $ar): 
                                $is_c_assigned = in_array($ar['id'], $c_rids);
                            ?>
                            <label style="display:flex; align-items:flex-start; gap:8px; padding:6px; font-size:12px; cursor:pointer; border-radius:4px;" onmouseover="this.style.background='var(--c-surf2)'" onmouseout="this.style.background='transparent'">
                                <input type="checkbox" class="seo-assign-multi-chk" data-cid="<?php echo $cid; ?>" data-rid="<?php echo $ar['id']; ?>" <?php checked($is_c_assigned); ?> style="accent-color:var(--c-primary); margin-top:2px;">
                                <div style="flex:1;">
                                    <div style="font-weight:600; color:var(--c-text);"><?php echo esc_html($ar['title']); ?></div>
                                    <div style="font-size:10px; color:var(--c-muted);">ID #<?php echo $ar['id']; ?></div>
                                </div>
                            </label>
                            <?php endforeach; endif; ?>
                        </div>
                    </td>


                    <!-- Actions -->
                    <td style="padding:10px 12px;">
                        <div style="display:flex;gap:5px;align-items:center;justify-content:center;flex-wrap:wrap;">
                            <button type="button" class="seo-btn seo-btn-ghost seo-btn-xs seo-send-mail-btn"
                                    data-rid="<?php echo $rid; ?>" data-cid="<?php echo $cid; ?>"
                                    title="Send dashboard email">📧 Mail</button>
                            <button type="button" class="seo-btn seo-btn-ghost seo-btn-xs rcl-edit-btn">✏ Edit</button>
                            <button type="button" class="seo-btn seo-btn-primary seo-btn-xs rcl-save-btn" style="display:none;" data-cid="<?php echo $cid; ?>">💾 Save</button>
                            <?php if ($is_assigned) : ?>
                            <button type="button" class="seo-btn seo-btn-danger seo-btn-xs seo-unassign-client-btn"
                                    data-rid="<?php echo $rid; ?>" data-cid="<?php echo $cid; ?>">✕ Remove</button>
                            <?php endif; ?>
                            <button type="button" class="seo-btn seo-btn-danger seo-btn-xs rcl-delete-btn"
                                    data-cid="<?php echo $cid; ?>" data-wp-uid="<?php echo $wp_uid; ?>" data-name="<?php echo esc_attr($c['name']); ?>"
                                    title="Delete client permanently">🗑 Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div style="padding:10px 20px;border-top:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center;">
        <span id="seo-assign-status" style="font-size:12px;color:var(--c-muted);"></span>
        <span style="font-size:12px;color:var(--c-muted);"><?php echo count($all_clients); ?> client<?php echo count($all_clients)!==1?'s':''; ?> total &middot; <?php echo count($assigned_client_ids); ?> assigned</span>
    </div>
    <?php endif; ?>
</div>

</div><!-- /#intg-panel-settings -->






<!-- PANEL C — Google Sheets -->
<div id="intg-panel-gsheets" style="display:none;">
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2>📊 Assigned Google Sheets</h2>
            <div style="display:flex;gap:10px;">
                <?php if ($cur_global_id): ?>
                <button class="seo-btn seo-btn-sm seo-btn-ghost" id="gsheet-reload-btn" data-intg="<?php echo esc_attr($cur_global_id); ?>">🔄 Load Google Sheets</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="seo-panel-body" style="padding:20px;">
            <?php if (!$cur_global_id): ?>
            <div style="padding:40px;text-align:center;color:var(--c-muted);font-size:14px;">
                ⚠️ You must assign a Global Integration in the "Integration Settings" tab first before you can fetch Google Sheets.
            </div>
            <?php else: ?>
            <div style="font-size:13px;color:var(--c-muted);margin-bottom:20px;">
                Select which Google Sheet is synced with each tab. When you link a sheet, the "Update" button in that tab will pull data from it.
            </div>
            
            <table class="seo-table" style="width:100%;text-align:left;font-size:13px;">
                <thead>
                    <tr>
                        <th>Dashboard Tab</th>
                        <th>Linked Google Sheet</th>
                        <th>Sheet Tab Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $tabs_def = [
                        'ga' => ['name' => 'Database (GA)'],
                        'service' => ['name' => 'Service Pages'],
                        'blog' => ['name' => 'Blog Posts'],
                        'gmb' => ['name' => 'Monthly Performance'],
                        'gmb_posts' => ['name' => 'GMB Posts'],
                        'technical' => ['name' => 'Technical Audit Issues'],
                        'backlinks'      => ['name' => 'Backlinks'],
                        'leads'          => ['name' => 'Leads'],
                        'click_tracking' => ['name' => 'Click Tracking (Leads)'],
                    ];
                    foreach ($tabs_def as $t_key => $t_info) : 
                        $link = $gsheet_links[$t_key] ?? [];
                    ?>
                    <tr class="gsheet-row" data-tabtype="<?php echo esc_attr($t_key); ?>">
                        <td style="font-weight:600;color:var(--c-text);"><?php echo esc_html($t_info['name']); ?></td>
                        <td>
                            <select class="seo-in gsheet-file-sel" style="max-width:250px;font-size:13px;">
                                <?php if (!empty($link['spreadsheet_id'])): ?>
                                <option value="<?php echo esc_attr($link['spreadsheet_id']); ?>" selected><?php echo esc_html($link['spreadsheet_name']); ?></option>
                                <?php else: ?>
                                <option value="">— Click Load Sheets —</option>
                                <?php endif; ?>
                            </select>
                        </td>
                        <td>
                            <select class="seo-in gsheet-tab-name-sel" style="max-width:150px;font-size:13px;">
                                <?php if (!empty($link['tab_name'])): ?>
                                <option value="<?php echo esc_attr($link['tab_id'] ?? $link['tab_name']); ?>" selected><?php echo esc_html($link['tab_name']); ?></option>
                                <?php else: ?>
                                <option value="">— Tab —</option>
                                <?php endif; ?>
                            </select>
                        </td>
                        <td>
                            <button class="seo-btn seo-btn-sm gsheet-save-link-btn" style="background:var(--c-primary);color:#fff;" data-rid="<?php echo $rid; ?>" data-intg="<?php echo esc_attr($cur_global_id); ?>">💾 Save</button>
                            <span class="gsheet-save-status" style="margin-left:8px;font-size:11px;color:var(--c-muted);"></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div><!-- /#intg-panel-gsheets -->

<!-- 
     PANEL D — Chatbot Configuration
     -->
<div id="intg-panel-chatbot" style="display:none;">
<style>
/* ── Chatbot panel ── */
.cb-provider-block { border:1px solid var(--c-border); border-radius:12px; overflow:hidden; margin-bottom:16px; }
.cb-provider-header { display:flex; align-items:center; gap:10px; padding:12px 16px; background:var(--c-surf2); border-bottom:1px solid var(--c-border); flex-wrap:wrap; }
.cb-provider-badge { font-size:10px; font-weight:700; padding:2px 8px; border-radius:4px; color:#fff; letter-spacing:.3px; flex-shrink:0; }
.cb-provider-title { font-size:14px; font-weight:700; color:var(--c-text); flex:1; min-width:60px; }
.cb-use-toggle { display:flex; align-items:center; gap:7px; margin-left:auto; }
.cb-use-toggle input[type="checkbox"] { width:15px; height:15px; accent-color:var(--c-primary); margin:0; flex-shrink:0; }
.cb-use-toggle label { font-size:12px; font-weight:600; color:var(--c-text); cursor:pointer; margin:0; white-space:nowrap; }
.cb-provider-body { padding:14px 16px; display:flex; flex-direction:column; gap:12px; background:var(--c-surf); }
.cb-provider-body.hidden { display:none; }
.cb-field { display:flex; flex-direction:column; gap:5px; }
.cb-field label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--c-muted); display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.cb-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.cb-row input { flex:1; min-width:0; }
.cb-status-pill { font-size:11px; font-weight:600; padding:2px 9px; border-radius:20px; }
.cb-status-pill.saved { background:rgba(16,185,129,.12); color:#10b981; border:1px solid rgba(16,185,129,.25); }
.cb-status-pill.global { background:rgba(99,102,241,.1); color:var(--c-primary); border:1px solid rgba(99,102,241,.2); }
.cb-prov-selector { display:flex; gap:8px; flex-wrap:wrap; }
.cb-prov-selector label { flex:1; min-width:100px; }
@media(max-width:640px){
    .cb-provider-header { flex-direction:column; align-items:flex-start; }
    .cb-use-toggle { margin-left:0; }
    .cb-prov-selector { flex-direction:column; }
}
</style>
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2>Chatbot Settings</h2>
        </div>
        <div style="padding:20px;">
            <?php
            $override_cb   = get_option( "seo_dash_chatbot_override_{$rid}", '0' );
            $rep_model_sel = get_option( "seo_dash_chatbot_model_{$rid}", '' );

            $global_deepseek_key   = SEO_Dash_Database::get_setting( 'deepseek_api_key', '' );
            $global_groq_key       = SEO_Dash_Database::get_setting( 'groq_api_key', '' );
            $global_cerebras_key   = SEO_Dash_Database::get_setting( 'cerebras_api_key', '' );
            $global_gemini_key     = SEO_Dash_Database::get_setting( 'gemini_api_key', '' );
            $global_deepseek_model = SEO_Dash_Database::get_setting( 'deepseek_model', 'deepseek-v4-pro' );
            $global_groq_model     = SEO_Dash_Database::get_setting( 'groq_model',     'meta-llama/llama-4-scout-17b-16e-instruct' );
            $global_cerebras_model = SEO_Dash_Database::get_setting( 'cerebras_model', 'gpt-oss-120b' );
            $global_gemini_model   = SEO_Dash_Database::get_setting( 'gemini_model',   'gemini-2.5-flash-preview-05-20' );

            $rep_deepseek_key_saved = get_option( "seo_dash_chatbot_deepseek_{$rid}", '' );
            $rep_groq_key_saved     = get_option( "seo_dash_chatbot_groq_{$rid}", '' );
            $rep_cerebras_key_saved = get_option( "seo_dash_chatbot_cerebras_{$rid}", '' );
            $rep_gemini_key_saved   = get_option( "seo_dash_chatbot_gemini_{$rid}", '' );
            $rep_deepseek_set = !empty( $rep_deepseek_key_saved );
            $rep_groq_set     = !empty( $rep_groq_key_saved );
            $rep_cerebras_set = !empty( $rep_cerebras_key_saved );
            $rep_gemini_set   = !empty( $rep_gemini_key_saved );

            $rep_deepseek_model_val = get_option( "seo_dash_chatbot_deepseek_model_{$rid}", '' );
            $rep_groq_model_val     = get_option( "seo_dash_chatbot_groq_model_{$rid}", '' );
            $rep_cerebras_model_val = get_option( "seo_dash_chatbot_cerebras_model_{$rid}", '' );
            $rep_gemini_model_val   = get_option( "seo_dash_chatbot_gemini_model_{$rid}", '' );

            $use_deepseek = get_option( "seo_dash_chatbot_use_deepseek_{$rid}", '0' ) === '1';
            $use_groq     = get_option( "seo_dash_chatbot_use_groq_{$rid}",     '0' ) === '1';
            $use_cerebras = get_option( "seo_dash_chatbot_use_cerebras_{$rid}", '0' ) === '1';
            $use_gemini   = get_option( "seo_dash_chatbot_use_gemini_{$rid}",   '0' ) === '1';
            ?>

            <!-- ── Info bar ── -->
            <div style="padding:11px 14px;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:10px;margin-bottom:20px;font-size:13px;color:var(--c-muted);line-height:1.6;">
                By default this report uses the <strong style="color:var(--c-text);">global API keys</strong> from Settings. Configure report-specific keys below and enable <strong style="color:var(--c-text);">Use This Connection</strong> to override per provider.
            </div>

            <!-- ── DEEPSEEK ── -->
            <div class="cb-provider-block">
                <div class="cb-provider-header">
                    <span class="cb-provider-badge" style="background:#8b5cf6;">DEEPSEEK</span>
                    <span class="cb-provider-title">DeepSeek</span>
                    <?php if ($global_deepseek_key): ?><span class="cb-status-pill global">Global key active</span><?php endif; ?>
                    <?php if ($rep_deepseek_set): ?><span class="cb-status-pill saved">✓ Report key saved</span><?php endif; ?>
                    <div class="cb-use-toggle">
                        <input type="checkbox" id="seo-cb-use-deepseek" <?php checked($use_deepseek); ?> onchange="cbToggleProvider('deepseek', this.checked)">
                        <label for="seo-cb-use-deepseek">Use this connection</label>
                    </div>
                </div>
                <div class="cb-provider-body<?php echo !$use_deepseek ? ' hidden' : ''; ?>" id="cb-body-deepseek">
                    <div class="cb-field">
                        <label>API Key <?php if ($rep_deepseek_set): ?><span class="cb-status-pill saved">✓ Saved</span><?php endif; ?></label>
                        <div class="cb-row">
                            <?php if ($rep_deepseek_set): ?>
                            <input type="password" id="seo-cb-deepseek" class="seo-in" autocomplete="new-password" placeholder="••••••••••••••••••••••••  (saved — enter new key to update)" style="flex:1;color:var(--c-muted);">
                            <?php else: ?>
                            <input type="password" id="seo-cb-deepseek" class="seo-in" autocomplete="new-password" placeholder="sk-..." style="flex:1;">
                            <?php endif; ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" onclick="var f=document.getElementById('seo-cb-deepseek');f.type=f.type==='password'?'text':'password';return false;">Show</button>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-test-rep-deepseek-btn">Test</button>
                            <?php if ($rep_deepseek_set): ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm seo-cb-remove-key-btn" data-provider="deepseek" data-action="seo_dash_remove_rep_deepseek_key" style="color:var(--c-red,#ef4444);border-color:var(--c-red,#ef4444);">✕ Remove</button>
                            <?php endif; ?>
                        </div>
                        <span id="seo-rep-deepseek-test-result" style="font-size:12px;"></span>
                    </div>
                    <div class="cb-field">
                        <label>Model</label>
                        <select id="seo-cb-deepseek-model" class="seo-in">
                            <option value="">— Use global (<?php echo esc_html($global_deepseek_model); ?>) —</option>
                            <option value="deepseek-v4-flash" <?php selected($rep_deepseek_model_val, 'deepseek-v4-flash'); ?>>DeepSeek V4 Flash</option>
                            <option value="deepseek-v4-pro"   <?php selected($rep_deepseek_model_val, 'deepseek-v4-pro'); ?>>DeepSeek V4 Pro</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ── GROQ ── -->
            <div class="cb-provider-block">
                <div class="cb-provider-header">
                    <span class="cb-provider-badge" style="background:#f97316;">GROQ</span>
                    <span class="cb-provider-title">Groq</span>
                    <?php if ($global_groq_key): ?><span class="cb-status-pill global">Global key active</span><?php endif; ?>
                    <?php if ($rep_groq_set): ?><span class="cb-status-pill saved">✓ Report key saved</span><?php endif; ?>
                    <div class="cb-use-toggle">
                        <input type="checkbox" id="seo-cb-use-groq" <?php checked($use_groq); ?> onchange="cbToggleProvider('groq', this.checked)">
                        <label for="seo-cb-use-groq">Use this connection</label>
                    </div>
                </div>
                <div class="cb-provider-body<?php echo !$use_groq ? ' hidden' : ''; ?>" id="cb-body-groq">
                    <div class="cb-field">
                        <label>API Key <?php if ($rep_groq_set): ?><span class="cb-status-pill saved">✓ Saved</span><?php endif; ?></label>
                        <div class="cb-row">
                            <?php if ($rep_groq_set): ?>
                            <input type="password" id="seo-cb-groq" class="seo-in" autocomplete="new-password" placeholder="••••••••••••••••••••••••  (saved — enter new key to update)" style="flex:1;color:var(--c-muted);">
                            <?php else: ?>
                            <input type="password" id="seo-cb-groq" class="seo-in" autocomplete="new-password" placeholder="gsk_..." style="flex:1;">
                            <?php endif; ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" onclick="var f=document.getElementById('seo-cb-groq');f.type=f.type==='password'?'text':'password';return false;">Show</button>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-test-rep-groq-btn">Test</button>
                            <?php if ($rep_groq_set): ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm seo-cb-remove-key-btn" data-provider="groq" data-action="seo_dash_remove_rep_groq_key" style="color:var(--c-red,#ef4444);border-color:var(--c-red,#ef4444);">✕ Remove</button>
                            <?php endif; ?>
                        </div>
                        <span id="seo-rep-groq-test-result" style="font-size:12px;"></span>
                    </div>
                    <div class="cb-field">
                        <label>Model</label>
                        <select id="seo-cb-groq-model" class="seo-in">
                            <option value="">— Use global (<?php echo esc_html($global_groq_model); ?>) —</option>
                            <option value="meta-llama/llama-4-scout-17b-16e-instruct" <?php selected($rep_groq_model_val, 'meta-llama/llama-4-scout-17b-16e-instruct'); ?>>Llama 4 Scout 17B — Best Free</option>
                            <option value="llama-3.3-70b-versatile"                   <?php selected($rep_groq_model_val, 'llama-3.3-70b-versatile'); ?>>Llama 3.3 70B Versatile</option>
                            <option value="groq/compound"                             <?php selected($rep_groq_model_val, 'groq/compound'); ?>>Groq Compound</option>
                            <option value="groq/compound-mini"                        <?php selected($rep_groq_model_val, 'groq/compound-mini'); ?>>Groq Compound Mini</option>
                            <option value="llama-3.1-8b-instant"                      <?php selected($rep_groq_model_val, 'llama-3.1-8b-instant'); ?>>Llama 3.1 8B — Fastest</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ── CEREBRAS ── -->
            <div class="cb-provider-block">
                <div class="cb-provider-header">
                    <span class="cb-provider-badge" style="background:#06b6d4;">CEREBRAS</span>
                    <span class="cb-provider-title">Cerebras</span>
                    <?php if ($global_cerebras_key): ?><span class="cb-status-pill global">Global key active</span><?php endif; ?>
                    <?php if ($rep_cerebras_set): ?><span class="cb-status-pill saved">✓ Report key saved</span><?php endif; ?>
                    <div class="cb-use-toggle">
                        <input type="checkbox" id="seo-cb-use-cerebras" <?php checked($use_cerebras); ?> onchange="cbToggleProvider('cerebras', this.checked)">
                        <label for="seo-cb-use-cerebras">Use this connection</label>
                    </div>
                </div>
                <div class="cb-provider-body<?php echo !$use_cerebras ? ' hidden' : ''; ?>" id="cb-body-cerebras">
                    <div class="cb-field">
                        <label>API Key <?php if ($rep_cerebras_set): ?><span class="cb-status-pill saved">✓ Saved</span><?php endif; ?></label>
                        <div class="cb-row">
                            <?php if ($rep_cerebras_set): ?>
                            <input type="password" id="seo-cb-cerebras" class="seo-in" autocomplete="new-password" placeholder="••••••••••••••••••••••••  (saved — enter new key to update)" style="flex:1;color:var(--c-muted);">
                            <?php else: ?>
                            <input type="password" id="seo-cb-cerebras" class="seo-in" autocomplete="new-password" placeholder="csk_..." style="flex:1;">
                            <?php endif; ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" onclick="var f=document.getElementById('seo-cb-cerebras');f.type=f.type==='password'?'text':'password';return false;">Show</button>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-test-rep-cerebras-btn">Test</button>
                            <?php if ($rep_cerebras_set): ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm seo-cb-remove-key-btn" data-provider="cerebras" data-action="seo_dash_remove_rep_cerebras_key" style="color:var(--c-red,#ef4444);border-color:var(--c-red,#ef4444);">✕ Remove</button>
                            <?php endif; ?>
                        </div>
                        <span id="seo-rep-cerebras-test-result" style="font-size:12px;"></span>
                    </div>
                    <div class="cb-field">
                        <label>Model</label>
                        <select id="seo-cb-cerebras-model" class="seo-in">
                            <option value="">— Use global (<?php echo esc_html($global_cerebras_model); ?>) —</option>
                            <option value="gpt-oss-120b" <?php selected($rep_cerebras_model_val, 'gpt-oss-120b'); ?>>GPT-OSS 120B — Best Free</option>
                            <option value="llama3.1-8b"  <?php selected($rep_cerebras_model_val, 'llama3.1-8b'); ?>>Llama 3.1 8B — Fastest</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ── GEMINI ── -->
            <div class="cb-provider-block">
                <div class="cb-provider-header">
                    <span class="cb-provider-badge" style="background:#10b981;">GOOGLE</span>
                    <span class="cb-provider-title">Gemini</span>
                    <?php if ($global_gemini_key): ?><span class="cb-status-pill global">Global key active</span><?php endif; ?>
                    <?php if ($rep_gemini_set): ?><span class="cb-status-pill saved">✓ Report key saved</span><?php endif; ?>
                    <div class="cb-use-toggle">
                        <input type="checkbox" id="seo-cb-use-gemini" <?php checked($use_gemini); ?> onchange="cbToggleProvider('gemini', this.checked)">
                        <label for="seo-cb-use-gemini">Use this connection</label>
                    </div>
                </div>
                <div class="cb-provider-body<?php echo !$use_gemini ? ' hidden' : ''; ?>" id="cb-body-gemini">
                    <div class="cb-field">
                        <label>API Key <?php if ($rep_gemini_set): ?><span class="cb-status-pill saved">✓ Saved</span><?php endif; ?></label>
                        <div class="cb-row">
                            <?php if ($rep_gemini_set): ?>
                            <input type="password" id="seo-cb-gemini" class="seo-in" autocomplete="new-password" placeholder="••••••••••••••••••••••••  (saved — enter new key to update)" style="flex:1;color:var(--c-muted);">
                            <?php else: ?>
                            <input type="password" id="seo-cb-gemini" class="seo-in" autocomplete="new-password" placeholder="AIza..." style="flex:1;">
                            <?php endif; ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" onclick="var f=document.getElementById('seo-cb-gemini');f.type=f.type==='password'?'text':'password';return false;">Show</button>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-test-rep-gemini-btn">Test</button>
                            <?php if ($rep_gemini_set): ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm seo-cb-remove-key-btn" data-provider="gemini" data-action="seo_dash_remove_rep_gemini_key" style="color:var(--c-red,#ef4444);border-color:var(--c-red,#ef4444);">✕ Remove</button>
                            <?php endif; ?>
                        </div>
                        <span id="seo-rep-gemini-test-result" style="font-size:12px;"></span>
                    </div>
                    <div class="cb-field">
                        <label>Model</label>
                        <select id="seo-cb-gemini-model" class="seo-in">
                            <option value="">— Use global (<?php echo esc_html($global_gemini_model); ?>) —</option>
                            <option value="gemini-2.5-flash"                   <?php selected($rep_gemini_model_val, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash — Best</option>
                            <option value="gemini-2.0-flash"                   <?php selected($rep_gemini_model_val, 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash</option>
                            <option value="gemini-2.0-flash-lite"              <?php selected($rep_gemini_model_val, 'gemini-2.0-flash-lite'); ?>>Gemini 2.0 Flash Lite</option>
                            <option value="gemini-1.5-flash"                   <?php selected($rep_gemini_model_val, 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash</option>
                            <option value="gemini-1.5-flash-8b"                <?php selected($rep_gemini_model_val, 'gemini-1.5-flash-8b'); ?>>Gemini 1.5 Flash 8B</option>
                            <option value="gemini-2.5-flash-preview-05-20"     <?php selected($rep_gemini_model_val, 'gemini-2.5-flash-preview-05-20'); ?>>Gemini 2.5 Flash Preview</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ── Active provider selector ── -->
            <div style="border:1px solid var(--c-border);border-radius:10px;padding:14px;margin-bottom:20px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--c-muted);margin-bottom:4px;">Active Provider for This Report</div>
                <div style="font-size:12px;color:var(--c-muted);margin-bottom:12px;">If multiple connections are enabled, select which provider this report uses.</div>
                <div class="cb-prov-selector" id="seo-cb-model-cards">
                    <?php
                    $all_prov = [
                        'deepseek' => ['label'=>'DeepSeek', 'badge'=>'#8b5cf6','badge_label'=>'DEEPSEEK'],
                        'gemini'   => ['label'=>'Gemini',   'badge'=>'#10b981','badge_label'=>'GOOGLE'],
                        'cerebras' => ['label'=>'Cerebras', 'badge'=>'#06b6d4','badge_label'=>'CEREBRAS'],
                        'groq'     => ['label'=>'Groq',     'badge'=>'#f97316','badge_label'=>'GROQ'],
                    ];
                    $use_flags = ['deepseek'=>$use_deepseek,'gemini'=>$use_gemini,'cerebras'=>$use_cerebras,'groq'=>$use_groq];
                    foreach ($all_prov as $pval => $pd):
                        $is_avail = $use_flags[$pval]
                            || ($pval==='deepseek' && ($global_deepseek_key || $rep_deepseek_set))
                            || ($pval==='gemini'   && ($global_gemini_key   || $rep_gemini_set))
                            || ($pval==='cerebras' && ($global_cerebras_key || $rep_cerebras_set))
                            || ($pval==='groq'     && ($global_groq_key     || $rep_groq_set));
                        $is_sel   = ($rep_model_sel === $pval);
                    ?>
                    <label style="display:flex;align-items:center;gap:8px;padding:9px 14px;border:2px solid <?php echo $is_sel ? 'var(--c-primary)' : 'var(--c-border)'; ?>;border-radius:8px;background:<?php echo $is_sel ? 'rgba(99,102,241,.06)' : 'var(--c-surf)'; ?>;<?php echo !$is_avail ? 'opacity:.4;cursor:not-allowed;pointer-events:none;' : 'cursor:pointer;'; ?>;transition:all .15s;"
                           id="seo-cb-prov-label-<?php echo $pval; ?>">
                        <input type="radio" name="seo_cb_model" value="<?php echo esc_attr($pval); ?>" id="seo-cb-radio-<?php echo esc_attr($pval); ?>" <?php checked($is_sel); ?> <?php echo !$is_avail ? 'disabled' : ''; ?> style="accent-color:var(--c-primary);width:14px;height:14px;margin:0;flex-shrink:0;" onchange="seoCbSelectModel('<?php echo esc_js($pval); ?>')">
                        <span style="font-size:13px;font-weight:600;color:var(--c-text);"><?php echo $pd['label']; ?></span>
                        <span style="background:<?php echo $pd['badge']; ?>;color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;"><?php echo $pd['badge_label']; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="seo-cb-model" value="<?php echo esc_attr($rep_model_sel); ?>">
            </div>

            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <button class="seo-btn seo-btn-primary" id="seo-cb-save-btn" data-rid="<?php echo $rid; ?>">Save Chatbot Settings</button>
                <span id="seo-cb-save-result" style="font-size:12px;"></span>
            </div>
        </div>
    </div>
</div><!-- /#intg-panel-chatbot -->





<?php endif; ?>

</div><!-- /.seo-tab-body -->

<!-- ════════════════════════════════════════════════════════════════════════
     GOOGLE SHEETS SYNC + INTEGRATIONS PANEL  (top-level — runs on EVERY tab)

     This script was previously emitted from inside seo_dash_pages_panel_html(),
     which is only called on the Service Pages / Blog Posts tabs. As a result
     window.initGsheetPanel (Load Sheets / Save) and the per-tab "Update" sync
     handlers were never defined on the Integrations / Database / GMB / Technical
     / Backlinks / Leads tabs, so those buttons did nothing when clicked.
     Moving it to top-level template scope makes the handlers available on all
     tabs. Handlers use event delegation / ID lookups, so missing elements on a
     given tab are simply skipped.
     ════════════════════════════════════════════════════════════════════════ -->
<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
if (!window.seoGsheetSyncBound) {
    window.seoGsheetSyncBound = true;
    seoJQ(function($){
        var rid   = <?php echo (int)$rid; ?>;
        var nonce = seoDash.nonce;
        var ajax  = seoDash.ajax;

        var globalIntgId = "";
        var globalSheetId = "";

        // Will be populated via AJAX
        var savedLinks = {};

        function boot() {
            $.post(ajax, { action:'seo_dash_gsheet_get_links', nonce:nonce, report_id:rid }, function(r2){
                if (r2.success) savedLinks = r2.data.links || {};
                ['ga','service','blog','gmb','gmb_posts','technical','backlinks','leads','click_tracking'].forEach(function(t){ initGsheetBar(t); });
            });
        }

        function initGsheetBar(t) {
            // The per-tab "Update" (sync) button is now handled by the NUCLEAR
            // pure-vanilla, capture-phase, document-delegated listener in the
            // <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer> block below, so it works with or without jQuery on every
            // tab. We intentionally do NOT bind a jQuery click handler here to
            // avoid double-firing the sync request.
            return;
        }

        /* ── Integrations tab: Google Sheets panel ──────────────────────────
           initGsheetPanel() is called when the user clicks the "Google Sheets"
           sub-tab. It wires up:
             • #gsheet-reload-btn  → loads spreadsheet list for this integration
             • .gsheet-file-sel    → selecting a file loads its tabs
             • .gsheet-save-link-btn → saves the link (spreadsheet + tab) per tab type
        ───────────────────────────────────────────────────────────────────── */
        window.initGsheetPanel = (function(){
            var _inited = false;
            return function(){
                if (_inited) return;
                _inited = true;

                var $reloadBtn = $('#gsheet-reload-btn');
                var intgId     = $reloadBtn.data('intg') || '';

                // Shared loaded-sheets cache (spreadsheet_id → tabs[])
                var _sheetCache = {};

                // ── Load spreadsheet list ──
                // NOTE: The Load button click is now handled by the NUCLEAR
                // pure-vanilla, capture-phase, document-delegated listener defined
                // in its own <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer> block below. We intentionally do NOT bind a
                // jQuery click handler here, to avoid double-firing the request.

                // ── Spreadsheet selection → load tabs, and Save link ──
                // These are now handled by the NUCLEAR pure-vanilla, capture-phase,
                // document-delegated listeners in the <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer> block below, so they
                // work with or without jQuery. We do NOT bind jQuery handlers here,
                // to avoid double-firing.
            };
        })();

        boot();

        // Bind the Google-Sheets panel handlers (spreadsheet-select → load tabs,
        // and the per-row Save button) unconditionally. initGsheetPanel() is
        // idempotent and its handlers are document-delegated, so calling it now —
        // regardless of which sub-tab is visible — guarantees they are always
        // active without waiting for a sub-tab click.
        if (typeof window.initGsheetPanel === 'function') window.initGsheetPanel();

    });
}
</script>

<!-- ════════════════════════════════════════════════════════════════════════
     NUCLEAR "Load Google Sheets" handler.

     Pure vanilla JS. No jQuery. No init function. No sub-tab event. Capture-phase
     listener bound directly to document, so a click on #gsheet-reload-btn is caught
     no matter:
       • whether jQuery loaded (or loaded late / not at all),
       • whether window.initGsheetPanel ever ran,
       • whether the Integrations sub-tab switcher bound,
       • whether the button was re-rendered after page load,
       • whether some other handler tries to stopPropagation().
     It talks to admin-ajax with XHR, refreshes an expired nonce and retries once,
     then populates every .gsheet-file-sel via raw DOM. This is the bulletproof path.
     ════════════════════════════════════════════════════════════════════════ -->
<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
(function(){
    if (window.__seoGsheetLoadNuclear) return;   // bind only once per page
    window.__seoGsheetLoadNuclear = true;

    function ajaxUrl(){ return (window.seoDash && seoDash.ajax) ? seoDash.ajax : (window.ajaxurl || '/wp-admin/admin-ajax.php'); }
    function curNonce(){ return (window.seoDash && seoDash.nonce) ? seoDash.nonce : ''; }
    var DEFAULT_LABEL = '🔄 Load Google Sheets';
    var SEO_GS_RID = <?php echo (int) $rid; ?>;
    function syncLabel(t){
        if (t === 'technical') return '⬇️ Update';
        if (t === 'backlinks' || t === 'leads') return '🔄 Update';
        return '⬆ Update';
    }

    function note(msg, type){
        if (typeof window.seoToast === 'function') { try { window.seoToast(msg, type); return; } catch(e){} }
        try { (type === 'err' ? console.error : console.log)('[Google Sheets] ' + msg); } catch(e){}
    }

    function postForm(params, cb){
        var x = new XMLHttpRequest();
        try { x.open('POST', ajaxUrl(), true); } catch(e){ cb(null, 0); return; }
        x.withCredentials = true;
        x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        x.onreadystatechange = function(){
            if (x.readyState !== 4) return;
            var res = null;
            try { res = JSON.parse(x.responseText); } catch(e){ res = null; }
            cb(res, x.status);
        };
        var body = [];
        for (var k in params){ if (params.hasOwnProperty(k)) body.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k])); }
        try { x.send(body.join('&')); } catch(e){ cb(null, 0); }
    }

    function findBtn(start){
        var el = start;
        while (el && el !== document){
            if (el.id === 'gsheet-reload-btn' || (el.classList && el.classList.contains('gsheet-reload-btn'))) return el;
            el = el.parentNode;
        }
        return null;
    }

    function populateSelects(sheets){
        var sels = document.querySelectorAll('.gsheet-file-sel');
        for (var i = 0; i < sels.length; i++){
            var sel = sels[i];
            var curVal = sel.value;
            sel.innerHTML = '<option value="">— Select a spreadsheet —</option>';
            for (var j = 0; j < sheets.length; j++){
                var o = document.createElement('option');
                o.value = sheets[j].id;
                o.textContent = sheets[j].name;
                o.setAttribute('data-name', sheets[j].name);
                if (sheets[j].id === curVal) o.selected = true;
                sel.appendChild(o);
            }
        }
    }

    function loadSheets(btn, retried){
        var intgId = btn.getAttribute('data-intg') || '';
        if (!intgId){ note('No integration linked. Set one in Integration Settings first.', 'err'); return; }

        btn.disabled = true;
        btn.textContent = 'Loading…';

        postForm({ action: 'seo_dash_gsheet_list', nonce: curNonce(), intg_id: intgId }, function(res, status){
            // Expired nonce → fetch a fresh one and retry exactly once.
            if (!retried && res && res.success === false && res.data && res.data.nonce_expired){
                postForm({ action: 'seo_dash_refresh_nonce' }, function(r2){
                    if (r2 && r2.success && r2.data && r2.data.nonce && window.seoDash){ seoDash.nonce = r2.data.nonce; }
                    loadSheets(btn, true);
                });
                return;
            }

            btn.disabled = false;
            btn.textContent = DEFAULT_LABEL;

            if (status === 0){ note('Network error loading sheets.', 'err'); return; }
            if (!res || !res.success){
                var em = 'Failed to load sheets.';
                if (res && res.data){
                    if (typeof res.data === 'string') em = res.data;
                    else if (res.data.message) em = res.data.message;
                }
                note(em, 'err');
                return;
            }
            var sheets = (res.data && res.data.sheets) || [];
            populateSelects(sheets);
            note(sheets.length + ' spreadsheet(s) loaded.', 'ok');
        });
    }

    // ── Vanilla helpers for the rest of the panel (tabs + save) ──────────────
    var _tabCache = {};

    function closestRow(el){
        while (el && el !== document){
            if (el.classList && el.classList.contains('gsheet-row')) return el;
            el = el.parentNode;
        }
        return null;
    }
    function findByClass(start, cls){
        var el = start;
        while (el && el !== document){
            if (el.classList && el.classList.contains(cls)) return el;
            el = el.parentNode;
        }
        return null;
    }
    function getIntgId(){
        var rb = document.getElementById('gsheet-reload-btn');
        if (rb && rb.getAttribute('data-intg')) return rb.getAttribute('data-intg');
        var sb = document.querySelector('.gsheet-save-link-btn[data-intg]');
        if (sb && sb.getAttribute('data-intg')) return sb.getAttribute('data-intg');
        return '';
    }
    function setTabOptions(tabSel, tabs, selectedId){
        tabSel.innerHTML = '<option value="">— Select tab —</option>';
        for (var i = 0; i < tabs.length; i++){
            var o = document.createElement('option');
            o.value = tabs[i].id;
            o.textContent = tabs[i].title;
            if (String(tabs[i].id) === String(selectedId)) o.selected = true;
            tabSel.appendChild(o);
        }
    }
    function selectedText(sel){
        if (sel && sel.selectedIndex >= 0 && sel.options[sel.selectedIndex]) return sel.options[sel.selectedIndex].textContent || '';
        return '';
    }

    function loadTabs(fileSel, retried){
        var spreadsheetId = fileSel.value;
        var row = closestRow(fileSel);
        if (!row) return;
        var tabSel = row.querySelector('.gsheet-tab-name-sel');
        if (!tabSel) return;
        if (!spreadsheetId){ tabSel.innerHTML = '<option value="">— Tab —</option>'; return; }
        if (_tabCache[spreadsheetId]){ setTabOptions(tabSel, _tabCache[spreadsheetId], ''); return; }

        tabSel.innerHTML = '<option value="">Loading tabs…</option>';
        postForm({ action: 'seo_dash_gsheet_list_tabs', nonce: curNonce(), intg_id: getIntgId(), spreadsheet_id: spreadsheetId }, function(res, status){
            if (!retried && res && res.success === false && res.data && res.data.nonce_expired){
                postForm({ action: 'seo_dash_refresh_nonce' }, function(r2){
                    if (r2 && r2.success && r2.data && r2.data.nonce && window.seoDash){ seoDash.nonce = r2.data.nonce; }
                    loadTabs(fileSel, true);
                });
                return;
            }
            if (status === 0 || !res || !res.success){
                tabSel.innerHTML = '<option value="">— Tab —</option>';
                var em = (res && res.data && (res.data.message || (typeof res.data === 'string' ? res.data : ''))) || 'Failed to load tabs.';
                note(em, 'err');
                return;
            }
            var tabs = (res.data && res.data.tabs) || [];
            _tabCache[spreadsheetId] = tabs;
            setTabOptions(tabSel, tabs, '');
        });
    }

    function saveLink(btn, retried){
        var row = closestRow(btn);
        if (!row){ note('Could not find the row to save.', 'err'); return; }
        var fileSel  = row.querySelector('.gsheet-file-sel');
        var tabSel   = row.querySelector('.gsheet-tab-name-sel');
        var statusEl = row.querySelector('.gsheet-save-status');
        var tabType  = row.getAttribute('data-tabtype') || '';
        var spreadId = fileSel ? fileSel.value : '';

        if (!spreadId){
            if (statusEl){ statusEl.style.color = 'var(--c-red)'; statusEl.textContent = 'Select a spreadsheet first.'; }
            note('Select a spreadsheet first.', 'err');
            return;
        }

        var spreadName = selectedText(fileSel);
        var tabId      = tabSel ? tabSel.value : '';
        var tabName    = selectedText(tabSel);
        var intgId     = btn.getAttribute('data-intg') || getIntgId();
        var rid        = btn.getAttribute('data-rid') || '';

        if (!retried){
            btn.disabled = true; btn.textContent = 'Saving…';
            if (statusEl){ statusEl.style.color = 'var(--c-muted)'; statusEl.textContent = ''; }
        }

        postForm({
            action: 'seo_dash_gsheet_save_link', nonce: curNonce(), report_id: rid, intg_id: intgId,
            tab_type: tabType, spreadsheet_id: spreadId, spreadsheet_name: spreadName, tab_id: tabId, tab_name: tabName
        }, function(res, status){
            if (!retried && res && res.success === false && res.data && res.data.nonce_expired){
                postForm({ action: 'seo_dash_refresh_nonce' }, function(r2){
                    if (r2 && r2.success && r2.data && r2.data.nonce && window.seoDash){ seoDash.nonce = r2.data.nonce; }
                    saveLink(btn, true);
                });
                return;
            }
            btn.disabled = false; btn.textContent = '💾 Save';
            if (status === 0){ if (statusEl){ statusEl.style.color = 'var(--c-red)'; statusEl.textContent = 'Network error.'; } note('Network error.', 'err'); return; }
            if (res && res.success){
                if (statusEl){ statusEl.style.color = 'var(--c-green)'; statusEl.textContent = '✅ Saved!'; }
                note('Sheet link saved.', 'ok');
            } else {
                var msg = (res && res.data && (typeof res.data === 'string' ? res.data : res.data.message)) || 'Save failed.';
                if (statusEl){ statusEl.style.color = 'var(--c-red)'; statusEl.textContent = msg; }
                note(msg, 'err');
            }
        });
    }

    // Capture phase = fires before any bubble-phase handler and before anything
    // can stopPropagation() it.
    document.addEventListener('click', function(ev){
        var btn = findBtn(ev.target);
        if (!btn) return;
        ev.preventDefault();
        ev.stopPropagation();
        loadSheets(btn, false);
    }, true);

    // Spreadsheet dropdown changed → load that sheet's tabs (vanilla).
    document.addEventListener('change', function(ev){
        var t = ev.target;
        if (t && t.classList && t.classList.contains('gsheet-file-sel')){
            loadTabs(t, false);
        }
    }, true);

    // Save button clicked → persist the link (vanilla).
    document.addEventListener('click', function(ev){
        var sb = findByClass(ev.target, 'gsheet-save-link-btn');
        if (!sb) return;
        ev.preventDefault();
        ev.stopPropagation();
        saveLink(sb, false);
    }, true);

    // Per-tab "Update" (sync) button → import data from the linked sheet.
    function syncUpdate(btn, retried){
        var tabType = btn.getAttribute('data-tabtype') || '';
        var bar = btn.parentNode;
        var statusEl = bar ? bar.querySelector('.gsheet-sync-status') : null;

        // active sub-type (only meaningful on Service/Blog pages panes)
        var activeSubType = '';
        var pane = findByClass(btn, 'seo-pages-period-pane');
        if (pane){
            var act = pane.querySelector('.pages-type-subtab-active');
            if (act) activeSubType = act.getAttribute('data-ptype') || '';
        }

        if (!retried){
            btn.disabled = true; btn.textContent = 'Syncing…';
            if (statusEl){ statusEl.style.color = 'var(--c-muted)'; statusEl.textContent = 'Fetching…'; }
        }

        postForm({
            action: 'seo_dash_gsheet_sync', nonce: curNonce(), report_id: SEO_GS_RID,
            tab_type: tabType, active_sub_type: activeSubType
        }, function(res, status){
            if (!retried && res && res.success === false && res.data && res.data.nonce_expired){
                postForm({ action: 'seo_dash_refresh_nonce' }, function(r2){
                    if (r2 && r2.success && r2.data && r2.data.nonce && window.seoDash){ seoDash.nonce = r2.data.nonce; }
                    syncUpdate(btn, true);
                });
                return;
            }

            btn.disabled = false; btn.textContent = syncLabel(tabType);

            if (status === 0){ if (statusEl){ statusEl.style.color = 'var(--c-red)'; statusEl.textContent = 'Network error.'; } note('Network error.', 'err'); return; }

            if (res && res.success){
                var msg = (res.data && res.data.message) ? res.data.message : '✅ Synced!';
                if (statusEl){ statusEl.style.color = 'var(--c-green)'; statusEl.textContent = msg; }
                note(msg, 'ok');
                // Reload so the freshly-imported rows render in the tab.
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                var em = 'Sync failed.';
                if (res && res.data){
                    if (typeof res.data === 'string') em = res.data;
                    else em = res.data.message || res.data.error || 'Sync failed. Check the sheet link in Global Integrations.';
                }
                if (statusEl){ statusEl.style.color = 'var(--c-red)'; statusEl.textContent = em; }
                note(em, 'err');
            }
        });
    }

    // Update button clicked → run the sync (vanilla, works on every tab).
    document.addEventListener('click', function(ev){
        var ub = findByClass(ev.target, 'gsheet-sync-btn');
        if (!ub) return;
        ev.preventDefault();
        ev.stopPropagation();
        syncUpdate(ub, false);
    }, true);

    // Per-tab "Export to Sheet" button → push current plugin data INTO the
    // linked Google Sheet tab (overwrites that tab's contents).
    function exportToSheet(btn, retried){
        var tabType = btn.getAttribute('data-tabtype') || '';
        var bar = btn.parentNode;
        var statusEl = bar ? bar.querySelector('.gsheet-sync-status') : null;

        if (!retried){
            if (!confirm('This will overwrite the linked Google Sheet tab with the current data from this dashboard. Continue?')) return;
            btn.disabled = true; btn.textContent = 'Exporting…';
            if (statusEl){ statusEl.style.color = 'var(--c-muted)'; statusEl.textContent = 'Writing to sheet…'; }
        }

        postForm({
            action: 'seo_dash_gsheet_export', nonce: curNonce(), report_id: SEO_GS_RID,
            tab_type: tabType
        }, function(res, status){
            if (!retried && res && res.success === false && res.data && res.data.nonce_expired){
                postForm({ action: 'seo_dash_refresh_nonce' }, function(r2){
                    if (r2 && r2.success && r2.data && r2.data.nonce && window.seoDash){ seoDash.nonce = r2.data.nonce; }
                    exportToSheet(btn, true);
                });
                return;
            }

            btn.disabled = false; btn.textContent = '⬇ Export to Sheet';

            if (status === 0){ if (statusEl){ statusEl.style.color = 'var(--c-red)'; statusEl.textContent = 'Network error.'; } note('Network error.', 'err'); return; }

            if (res && res.success){
                var msg = (res.data && res.data.message) ? res.data.message : '✅ Exported!';
                if (statusEl){ statusEl.style.color = 'var(--c-green)'; statusEl.textContent = msg; }
                note(msg, 'ok');
            } else {
                var em = 'Export failed.';
                if (res && res.data){
                    if (typeof res.data === 'string') em = res.data;
                    else em = res.data.message || res.data.error || 'Export failed. Check the sheet link in Global Integrations.';
                }
                if (statusEl){ statusEl.style.color = 'var(--c-red)'; statusEl.textContent = em; }
                note(em, 'err');
            }
        });
    }

    document.addEventListener('click', function(ev){
        var eb = findByClass(ev.target, 'gsheet-export-btn');
        if (!eb) return;
        ev.preventDefault();
        ev.stopPropagation();
        exportToSheet(eb, false);
    }, true);
})();

</script>


<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
document.addEventListener('DOMContentLoaded', function(){
seoJQ(function($){

    /* ── Password toggle ─────────────────────────────────────────────── */
    $(document).on('click', '.seo-toggle-pwd', function(e){
        e.preventDefault();
        var $mask = $(this).siblings('.seo-pwd-mask');
        if($mask.text() === '••••••••'){
            $mask.text($mask.data('pwd'));
            $(this).text('🙈');
        } else {
            $mask.text('••••••••');
            $(this).text('👁️');
        }
    });

    /* ── Report client table: reveal pwd / inline edit / save ─────────── */
    $(document).on('click','.rcl-reveal-pwd',function(){
        var $s=$(this).siblings('.rcl-pwd-stars'),p=$s.data('pwd');
        if($s.text()==='••••••••'){$s.text(p);$(this).text('🙈');}
        else{$s.text('••••••••');$(this).text('👁');}
    });
    $(document).on('click','.rcl-edit-btn',function(){
        var $tr=$(this).closest('tr');
        $tr.find('.rcl-view-name,.rcl-view-email,.rcl-view-pwd').hide();
        $tr.find('.rcl-edit-name,.rcl-edit-email,.rcl-edit-pwd').show();
        $(this).hide(); $tr.find('.rcl-save-btn').show();
    });
    $(document).on('click','.rcl-save-btn',function(){
        var $btn=$(this),$tr=$btn.closest('tr');
        var cid=$btn.data('cid'),name=$tr.find('.rcl-edit-name').val().trim();
        var email=$tr.find('.rcl-edit-email').val().trim(),pwd=$tr.find('.rcl-edit-pwd').val().trim();
        if(!name){seoToast('Name is required.','err');return;}
        $btn.text('Saving…').prop('disabled',true);
        var pl={action:'seo_dash_save_client_v2',nonce:seoDash.nonce,client_id:cid,name:name,email:email};
        if(pwd)pl.password=pwd;
        $.post(seoDash.ajax,pl,function(r){
            $btn.text('💾 Save').prop('disabled',false);
            if(r.success){
                $tr.find('.rcl-view-name').text(name).show();
                $tr.find('.rcl-view-email').text(email).show();
                var rp=(r.data&&r.data.client&&r.data.client.password)?r.data.client.password:pwd;
                if(rp){$tr.find('.rcl-pwd-stars').data('pwd',rp).text('••••••••');$tr.find('.rcl-reveal-pwd').text('👁');}
                $tr.find('.rcl-view-pwd').show();
                $tr.find('.rcl-edit-name,.rcl-edit-email,.rcl-edit-pwd').hide();
                $btn.hide();$tr.find('.rcl-edit-btn').show();
                seoToast('Client saved.','ok');
            }else{seoToast(r.data&&r.data.message?r.data.message:'Save failed.','err');}
        }).fail(function(){$btn.text('💾 Save').prop('disabled',false);seoToast('Network error.','err');});
    });

    /* ── Report client table: Delete client (+ WP account) ─────────────── */
    $(document).on('click', '.rcl-delete-btn', function(){
        var $btn  = $(this);
        var $tr   = $btn.closest('tr');
        var cid   = $btn.data('cid');
        var wpUid = $btn.data('wpUid');
        var name  = $btn.data('name');
        var msg   = 'Delete client "' + name + '" permanently?\n\nThis will remove them from the dashboard.';
        if (wpUid) msg += '\n\nTheir WordPress login account will also be deleted.';
        msg += '\n\nThis cannot be undone.';
        if (!confirm(msg)) return;
        $btn.prop('disabled', true).text('Deleting…');
        $.post(seoDash.ajax, {
            action: 'seo_dash_delete_client',
            nonce:  seoDash.nonce,
            client_id: cid
        }, function(r){
            if (r.success){
                $tr.fadeOut(300, function(){ $(this).remove(); });
                seoToast('Client deleted.', 'ok');
            } else {
                $btn.prop('disabled', false).text('🗑 Delete');
                seoToast(r.data && r.data.message ? r.data.message : 'Delete failed.', 'err');
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('🗑 Delete');
            seoToast('Network error.', 'err');
        });
    });

    /* ── Integrations sub-tab switcher ───────────────────────────────── */
    (function(){
        var $btnSettings  = $('#intg-stab-settings');
        var $btnGSheets   = $('#intg-stab-gsheets');
        var $btnChatbot   = $('#intg-stab-chatbot');
        var $panelSettings  = $('#intg-panel-settings');
        var $panelGSheets   = $('#intg-panel-gsheets');
        var $panelChatbot   = $('#intg-panel-chatbot');
        if (!$btnSettings.length) return; // not on integrations tab

        function activateSubTab(which) {
            [$btnSettings,$btnGSheets,$btnChatbot].forEach(function($b){
                $b.css({'border-bottom-color':'transparent','color':'var(--c-muted)'});
            });
            $panelSettings.hide(); $panelGSheets.hide(); $panelChatbot.hide();

            if (which === 'gsheets') {
                $btnGSheets.css({'border-bottom-color':'var(--c-primary)','color':'var(--c-primary)'});
                $panelGSheets.show();
                if(typeof window.initGsheetPanel === 'function') window.initGsheetPanel();
            } else if (which === 'chatbot') {
                $btnChatbot.css({'border-bottom-color':'var(--c-primary)','color':'var(--c-primary)'});
                $panelChatbot.show();
            } else {
                $btnSettings.css({'border-bottom-color':'var(--c-primary)','color':'var(--c-primary)'});
                $panelSettings.show();
            }
        }
        $btnSettings.on('click',  function(){ activateSubTab('settings');   });
        $btnGSheets.on('click',   function(){ activateSubTab('gsheets');    });
        $btnChatbot.on('click',   function(){ activateSubTab('chatbot');    });

        // cbToggleProvider — show/hide provider body when "Use this connection" is toggled
        window.cbToggleProvider = function(provider, enabled) {
            var $body = $('#cb-body-' + provider);
            if (enabled) $body.slideDown(180);
            else $body.slideUp(180);
            // Also enable/disable the active-provider radio so it can be selected
            var $radio = $('#seo-cb-radio-' + provider);
            var $label = $('#seo-cb-prov-label-' + provider);
            if (enabled) {
                $radio.prop('disabled', false);
                $label.css({'opacity':'1','cursor':'pointer','pointer-events':'auto'});
            } else {
                $radio.prop('disabled', true).prop('checked', false);
                $label.css({'opacity':'.4','cursor':'not-allowed','pointer-events':'none',
                            'border-color':'var(--c-border)','background':'var(--c-surf)'});
                // If this provider was selected, clear the hidden field
                if ($('#seo-cb-model').val() === provider) {
                    $('#seo-cb-model').val('');
                }
            }
        };

        // Model pill selector
        window.seoCbSelectModel = function(val) {
            $('#seo-cb-model').val(val);
            $('#seo-cb-model-cards label').each(function(){
                $(this).css({'border-color':'var(--c-border)','background':'var(--c-surf)'});
            });
            $('#seo-cb-radio-' + val).prop('checked', true);
            $('#seo-cb-prov-label-' + val).css({'border-color':'var(--c-primary)','background':'rgba(99,102,241,.06)'});
        };

        // Save Chatbot Settings
        $('#seo-cb-save-btn').on('click', function() {
            var $btn = $(this);
            var $res = $('#seo-cb-save-result');
            $btn.text('Saving...').prop('disabled', true);
            $res.text('').css('color','inherit');

            var selectedModel = $('#seo-cb-model').val() || $('input[name="seo_cb_model"]:checked').val() || '';
            var data = {
                action: 'seo_dash_save_chatbot_settings',
                nonce: seoDash.nonce,
                report_id: $btn.data('rid'),
                override: '1',
                model: selectedModel,
                use_deepseek: $('#seo-cb-use-deepseek').is(':checked') ? '1' : '0',
                use_groq:     $('#seo-cb-use-groq').is(':checked')     ? '1' : '0',
                use_cerebras: $('#seo-cb-use-cerebras').is(':checked') ? '1' : '0',
                use_gemini:   $('#seo-cb-use-gemini').is(':checked')   ? '1' : '0'
            };

            var dsk = $('#seo-cb-deepseek').val().trim();
            if (dsk) data.deepseek_key = dsk;
            var gk = $('#seo-cb-groq').val().trim();
            if (gk) data.groq_key = gk;
            var ck = $('#seo-cb-cerebras').val().trim();
            if (ck) data.cerebras_key = ck;
            var gmk = $('#seo-cb-gemini').val().trim();
            if (gmk) data.gemini_key = gmk;

            var dsm = $('#seo-cb-deepseek-model').val();
            if (dsm) data.deepseek_model_override = dsm;
            var gm = $('#seo-cb-groq-model').val();
            if (gm) data.groq_model_override = gm;
            var cm = $('#seo-cb-cerebras-model').val();
            if (cm) data.cerebras_model_override = cm;
            var gem = $('#seo-cb-gemini-model').val();
            if (gem) data.gemini_model_override = gem;

            $.post(seoDash.ajax, data, function(r) {
                if (r.success) {
                    seoToast('Chatbot settings saved.', 'ok');
                    $res.text('Saved').css('color','var(--c-green)');
                    $('#seo-cb-deepseek').attr('placeholder', 'Saved — enter to update').val('');
                    $('#seo-cb-groq').attr('placeholder', 'Saved — enter to update').val('');
                    $('#seo-cb-cerebras').attr('placeholder', 'Saved — enter to update').val('');
                    $('#seo-cb-gemini').attr('placeholder', 'Saved — enter to update').val('');
                    setTimeout(function(){ $res.text(''); }, 3000);
                } else {
                    seoToast('Failed to save settings.', 'err');
                    $res.text('Failed').css('color','var(--c-red)');
                }
                $btn.text('Save Chatbot Settings').prop('disabled', false);
            }).fail(function() {
                seoToast('Network error.', 'err');
                $btn.text('Save Chatbot Settings').prop('disabled', false);
            });
        });

        // Test API Keys for Report Override
        $('#seo-test-rep-deepseek-btn').on('click', function() {
            var key = $('#seo-cb-deepseek').val().trim();
            var model = $('#seo-cb-deepseek-model').val();
            var $r = $('#seo-rep-deepseek-test-result').text('Testing…').css('color','inherit');
            $.post(seoDash.ajax, { action: 'seo_dash_test_deepseek', nonce: seoDash.nonce, api_key: key, report_id: <?php echo $rid; ?>, test_model: model }, function(r) {
                var msg = (r.data && r.data.message) ? r.data.message : (r.success ? 'Connected' : 'Invalid key');
                $r.text(r.success ? '✅ ' + msg : '❌ ' + msg).css('color', r.success ? 'var(--c-green)' : 'var(--c-red)');
            });
        });

        $('#seo-test-rep-groq-btn').on('click', function() {
            var key = $('#seo-cb-groq').val().trim();
            var $r = $('#seo-rep-groq-test-result').text('Testing…').css('color','inherit');
            $.post(seoDash.ajax, { action: 'seo_dash_test_groq', nonce: seoDash.nonce, api_key: key, report_id: <?php echo $rid; ?> }, function(r) {
                var msg = (r.data && r.data.message) ? r.data.message : (r.success ? 'Connected' : 'Invalid key');
                $r.text(r.success ? '✅ ' + msg : '❌ ' + msg).css('color', r.success ? 'var(--c-green)' : 'var(--c-red)');
            });
        });

        $('#seo-test-rep-cerebras-btn').on('click', function() {
            var key = $('#seo-cb-cerebras').val().trim();
            var $r = $('#seo-rep-cerebras-test-result').text('Testing…').css('color','inherit');
            $.post(seoDash.ajax, { action: 'seo_dash_test_cerebras', nonce: seoDash.nonce, api_key: key, report_id: <?php echo $rid; ?> }, function(r) {
                var msg = (r.data && r.data.message) ? r.data.message : (r.success ? 'Connected' : 'Invalid key');
                $r.text(r.success ? '✅ ' + msg : '❌ ' + msg).css('color', r.success ? 'var(--c-green)' : 'var(--c-red)');
            });
        });

        $('#seo-test-rep-gemini-btn').on('click', function() {
            var key = $('#seo-cb-gemini').val().trim();
            var model = $('#seo-cb-gemini-model').val();
            var $r = $('#seo-rep-gemini-test-result').text('Testing…').css('color','inherit');
            $.post(seoDash.ajax, { action: 'seo_dash_test_gemini', nonce: seoDash.nonce, api_key: key, report_id: <?php echo $rid; ?>, test_model: model }, function(r) {
                var msg = (r.data && r.data.message) ? r.data.message : (r.success ? 'Connected' : 'Invalid key');
                $r.text(r.success ? '✅ ' + msg : '❌ ' + msg).css('color', r.success ? 'var(--c-green)' : 'var(--c-red)');
            });
        });



        /* ---------- tab switching ---------- */
        function activateGaTab(type) {
            $('.ga-type-tab').each(function(){
                var me = $(this).data('gtype') === type;
                $(this).css({'border-bottom-color': me ? 'var(--c-primary)' : 'transparent',
                             'color':               me ? 'var(--c-primary)' : 'var(--c-muted)'});
            });
            $('.ga-type-panel').each(function(){
                $(this).css('display', $(this).data('gtype') === type ? 'block' : 'none');
            });
            // Show/hide the month filter bar (only for content-type panels)
            if (type === '__overview__') {
                $('#ga-type-month-bar').hide();
            } else {
                $('#ga-type-month-bar').css('display','flex');
            }
        }
        // Default: Overview
        activateGaTab('__overview__');

        $(document).on('click', '.ga-type-tab', function(){
            activateGaTab($(this).data('gtype'));
        });

        // Month filter for type panels
        $('#seo-ga-month-filter').on('change', function(){
            var m = $(this).val();
            $('.ga-type-panel:visible tbody tr').each(function(){
                $(this).toggle(!m || $(this).data('month') === m);
            });
        });

        /* ---------- Date range presets ---------- */
        function padZ(n){ return n < 10 ? '0'+n : ''+n; }
        function fmtDate(d){ return d.getFullYear()+'-'+padZ(d.getMonth()+1)+'-'+padZ(d.getDate()); }

        function applyPreset(days) {
            var today = new Date();
            var from  = new Date();
            from.setDate(today.getDate() - (days - 1));
            $('#ga-ov-from').val(fmtDate(from));
            $('#ga-ov-to').val(fmtDate(today));
            // Month key = start month
            $('#ga-ov-month').val(from.getFullYear()+'-'+padZ(from.getMonth()+1));
            var labels = {7:'Last 7 days ('+fmtDate(from)+' → '+fmtDate(today)+')',
                          30:'Last 30 days ('+fmtDate(from)+' → '+fmtDate(today)+')',
                          90:'Last 90 days ('+fmtDate(from)+' → '+fmtDate(today)+')'};
            $('#ga-ov-preset-label').text(labels[days] || '');
            // Style active preset
            $('.ga-ov-preset').css({background:'',color:''});
            $('.ga-ov-preset[data-days="'+days+'"]').css({
                background:'var(--c-primary)', color:'#fff'
            });
        }

        $(document).on('click', '.ga-ov-preset', function(){
            var days = parseInt($(this).data('days'));
            if (days === 0) {
                // Custom: clear preset style, let user type
                $('.ga-ov-preset').css({background:'',color:''});
                $(this).css({background:'var(--c-primary)',color:'#fff'});
                $('#ga-ov-preset-label').text('Enter custom date range below.');
                $('#ga-ov-from,#ga-ov-to').val('');
            } else {
                applyPreset(days);
            }
        });

        // Auto-update month key when from-date changes
        $('#ga-ov-from').on('change', function(){
            var v = $(this).val();
            if (v) $('#ga-ov-month').val(v.substring(0,7));
        });

        /* ---------- Selection mode ---------- */
        $('#ga-mode-auto').on('click', function(){
            $(this).css({background:'var(--c-primary)',color:'#fff'});
            $('#ga-mode-manual').css({background:'none',color:'var(--c-muted)'});
            $('#ga-sel-auto').show();
            $('#ga-sel-manual').hide();
            updateSelCount();
        });
        $('#ga-mode-manual').on('click', function(){
            $(this).css({background:'var(--c-primary)',color:'#fff'});
            $('#ga-mode-auto').css({background:'none',color:'var(--c-muted)'});
            $('#ga-sel-manual').show();
            $('#ga-sel-auto').hide();
            updateSelCount();
        });

        /* ---------- Auto mode: type checkbox → select all URLs ---------- */
        var ovData = (window.gaOverviewData && window.gaOverviewData[rid]) ? window.gaOverviewData[rid] : {};

        $(document).on('change', '.ga-type-auto-chk', function(){
            updateSelCount();
        });

        /* ---------- Manual mode: type-all header checkbox ---------- */
        $(document).on('change', '.ga-manual-type-all', function(){
            var type = $(this).data('gtype');
            var chk  = $(this).prop('checked');
            $('.ga-url-manual-chk[data-gtype="'+type+'"]').prop('checked', chk);
            updateSelCount();
        });
        $(document).on('change', '.ga-url-manual-chk', function(){
            updateSelCount();
        });

        /* ---------- Count helper ---------- */
        function getSelectedUrls() {
            var urls = [];
            var autoActive = $('#ga-sel-auto').is(':visible');
            if (autoActive) {
                // Collect all URLs from checked types
                $('.ga-type-auto-chk:checked').each(function(){
                    var t = $(this).data('gtype');
                    if (ovData[t]) {
                        $.each(ovData[t], function(i, u){ urls.push(u); });
                    }
                });
            } else {
                // Manual mode
                $('.ga-url-manual-chk:checked').each(function(){
                    urls.push($(this).val());
                });
            }
            // Deduplicate
            return $.uniqueSort ? $.uniqueSort(urls) : urls.filter(function(v,i,a){return a.indexOf(v)===i;});
        }

        function updateSelCount() {
            var urls = getSelectedUrls();
            var n = urls.length;
            $('#ga-ov-sel-count').text(n + ' URL' + (n===1?'':'s') + ' selected');
            $('#ga-ov-fetch-btn').prop('disabled', n === 0);
        }

        /* ---------- Fetch button ---------- */
        $(document).on('click', '#ga-ov-fetch-btn', function(){
            var $btn    = $(this);
            var $status = $('#ga-ov-fetch-status');
            var dateFrom= $('#ga-ov-from').val();
            var dateTo  = $('#ga-ov-to').val();
            var monthKey= $('#ga-ov-month').val();

            if (!dateFrom || !dateTo) {
                $status.css('color','var(--c-red)').text('Please select a date range first.');
                return;
            }
            // Validate yyyy-MM-dd format — guards against corrupted input values.
            if(!/^\d{4}-\d{2}-\d{2}$/.test(dateFrom) || !/^\d{4}-\d{2}-\d{2}$/.test(dateTo)) {
                $status.css('color','var(--c-red)').text('Invalid date format. Please re-select the date range.');
                $('#ga-ov-from,#ga-ov-to').val('');
                return;
            }
            var urls = getSelectedUrls();
            if (!urls.length) {
                $status.css('color','var(--c-red)').text('No URLs selected.');
                return;
            }

            var metrics = [];
            $('.ga-metric-chk:checked').each(function(){ metrics.push($(this).val()); });

            if (!metrics.length) {
                $status.css('color','var(--c-red)').text('Please select at least one metric.');
                return;
            }

            $btn.text('Fetching…').prop('disabled', true);
            $status.css('color','var(--c-muted)').text('Contacting Google Analytics API…');

            $.post(seoDash.ajax, {
                action    : 'seo_dash_ga_fetch_urls',
                nonce     : seoDash.nonce,
                report_id : rid,
                month_key : monthKey,
                date_from : dateFrom,
                date_to   : dateTo,
                urls      : urls,
                metrics   : metrics,
                property  : (window.gaReportProperty && window.gaReportProperty[rid]) ? window.gaReportProperty[rid] : ''
            }, function(r){
                $btn.text('📊 Fetch from Analytics').prop('disabled', false);
                if (r.success) {
                    var ins = r.data.inserted || 0;
                    var nf  = r.data.not_found || 0;
                    var msg = '✅ ' + ins + ' row' + (ins===1?'':'s') + ' fetched';
                    if (nf > 0) msg += ' (' + nf + ' URLs had no GA data)';
                    msg += '. Reload to see updated data in the type tabs.';
                    $status.css('color','var(--c-green)').text(msg);
                    seoToast(ins + ' rows fetched from Analytics!', 'ok');
                } else {
                    var err = (r.data && r.data.message) ? r.data.message : 'Fetch failed.';
                    $status.css('color','var(--c-red)').text('' + err);
                    seoToast(err, 'err');
                }
            }).fail(function(xhr, status, err){
                $btn.text('📊 Fetch from Analytics').prop('disabled', false);
                var msg = seoHttpErrMsg(xhr, status, err);
                $status.css('color','var(--c-red)').text('Server error: ' + msg);
                seoToast('GA fetch failed: ' + msg, 'err');
                console.error('[GA Fetch URLs] HTTP error:', status, err, xhr && xhr.responseText);
            });
        });

    })(); // end GA overview IIFE

    /* ── SC type sub-tabs ────────────────────────────────────────────── */
    (function(){
        if (!$('.sc-type-tab').length) return;
        function activateScTab(type) {
            $('.sc-type-tab').each(function(){
                var me = $(this).data('sctype') === type;
                $(this).css({'border-bottom-color': me?'var(--c-primary)':'transparent',
                             'color':               me?'var(--c-primary)':'var(--c-muted)'});
            });
            $('.sc-type-panel').each(function(){
                $(this).css('display', $(this).data('sctype')===type ? 'block' : 'none');
            });
        }
        $(document).on('click', '.sc-type-tab', function(){
            activateScTab($(this).data('sctype'));
        });
        // Month filter
        $('#seo-sc-month-filter').on('change', function(){
            var m = $(this).val();
            $('.sc-type-panel:visible tbody tr').each(function(){
                $(this).toggle(!m || $(this).data('month') === m);
            });
        });
    })();

    /* ── Search / filter clients table ──────────────────────────────── */
    $('#seo-client-intg-search').on('input', function(){
        var q = $(this).val().toLowerCase();
        $('.seo-client-full-row').each(function(){
            $(this).toggle(!q || ($(this).data('name')||'').indexOf(q) > -1);
        });
    });

    /* ── Select all checkbox ─────────────────────────────────────────── */
    $('#seo-select-all-clients').on('change', function(){
        $('.seo-client-intg-chk').prop('checked', $(this).prop('checked'));
    });

    /* ── Save Assignments (checkboxes → assign_clients_to_report) ────── */
    /* FIX: previously this only waited on ONE request (the bulk save)    */
    /* then reloaded after a fixed 700ms timer, while a SEPARATE handler  */
    /* further down fired additional fire-and-forget requests for the    */
    /* per-client "Assigned" dropdown (multi-report) checkboxes. If those */
    /* extra requests hadn't finished by the time the page reloaded,      */
    /* whatever they were saving (e.g. a 2nd report assigned to the same  */
    /* client) silently disappeared. Also removed the duplicate           */
    /* 'client_ids[]' + 'client_ids' POST keys — sending the same array   */
    /* under two keys made jQuery serialize it as nested arrays, which    */
    /* PHP's intval() on an array silently turns into 1, injecting a      */
    /* phantom client ID of 1 into every save. Now: a single key, and ALL */
    /* requests (bulk + per-client multi-report ones) are collected and  */
    /* the page reloads only once every one of them has completed.       */
    $('#seo-assign-clients-btn').on('click', function(){
        var $btn = $(this).text('Saving…').prop('disabled', true);
        var rid  = $btn.data('rid');
        var ids  = [];
        $('.seo-client-intg-chk:checked').each(function(){ ids.push($(this).val()); });

        var requests = [];

        // 1) Bulk save for the CURRENT report's client checklist.
        requests.push($.post(seoDash.ajax, {
            action     : 'seo_dash_assign_clients_to_report',
            nonce      : seoDash.nonce,
            report_id  : rid,
            client_ids : ids
        }));

        // 2) Any pending changes from the per-client "Assigned" dropdown
        //    for OTHER reports (assigning e.g. a 2nd/3rd report to a client).
        $('.seo-assign-multi-chk').each(function(){
            var mrid = parseInt($(this).data('rid'));
            if (mrid === parseInt(rid)) return; // current report handled above
            var cid       = $(this).data('cid');
            var isChecked = $(this).is(':checked');
            requests.push($.post(seoDash.ajax, {
                action     : isChecked ? 'seo_dash_assign_client' : 'seo_dash_unassign_client',
                nonce      : seoDash.nonce,
                client_id  : cid,
                report_id  : mrid
            }));
        });

        $.when.apply($, requests).always(function(){
            $btn.text('✅ Save Assignments').prop('disabled', false);
            seoToast('Clients updated.', 'ok');
            location.reload();
        });
    });


    /* ── Unassign single client (Remove button) ──────────────────────── */
    $(document).on('click', '.seo-unassign-client-btn', function(){
        var $btn = $(this);
        var rid  = $btn.data('rid');
        var cid  = $btn.data('cid');
        var name = $btn.closest('tr').find('td:nth-child(3)').text().trim() || 'this client';

        var choice = confirm(
            'Remove "' + name + '" from this report?\n\n' +
            'Click OK to REMOVE FROM REPORT ONLY.\n' +
            'To DELETE COMPLETELY go to the Clients page.'
        );
        if ( ! choice ) return;

        $btn.text('…').prop('disabled', true);
        $.post(seoDash.ajax, {action:'seo_dash_unassign_client', nonce:seoDash.nonce, report_id:rid, client_id:cid}, function(r){
            if(r.success){
                seoToast('Client removed from report.','ok');
                setTimeout(function(){ location.reload(); }, 700);
            } else {
                $btn.text('✕ Remove').prop('disabled', false);
                seoToast(r.data&&r.data.message?r.data.message:'Failed.','err');
            }
        });
    });


    /* ── Save Integration (report-level dropdown) ────────────────────── */
    $('#seo-rpt-save-intg-btn').on('click', function(){
        var $btn   = $(this).text('Saving…').prop('disabled', true);
        var rid    = $btn.data('rid');
        var intgId = $('#seo-intg-dropdown').val();
        // Always call assign_global_integration — passing empty intg_id clears it
        $.post(seoDash.ajax, {
            action         : 'seo_dash_assign_global_integration',
            nonce          : seoDash.nonce,
            report_id      : rid,
            integration_id : intgId,
            scope          : 'global'
        }, function(r){
            $btn.text('💾 Save Integration').prop('disabled', false);
            if(r.success) seoToast('Integration saved.','ok');
            else seoToast(r.data&&r.data.message?r.data.message:'Failed.','err');
        });
    });

    /* ── Send Mail ───────────────────────────────────────────────────── */
    $(document).on('click', '.seo-send-mail-btn', function(e){
        e.preventDefault();
        var $btn = $(this).text('Sending…').prop('disabled', true);
        var rid  = $btn.data('rid');
        var cid  = $btn.data('cid');
        $.post(seoDash.ajax, {action:'seo_dash_send_assigned_email', nonce:seoDash.nonce, report_id:rid, client_ids:[cid]}, function(r){
            $btn.text('📧 Mail').prop('disabled', false);
            if(r.success) seoToast('Email sent.','ok');
            else seoToast(r.data&&r.data.message?r.data.message:'Failed to send.','err');
        });
    });

    /* 
       SITEMAP TAB — Import, display, push to GA / SC
       */

    // Cache of imported URL objects: [{url:'…', type:'post'}, …]
    var _sitemapUrls = [];

    // Type colour map for badges
    var _typeColors = {
        post     : '#6366f1',
        page     : '#0ea5e9',
        city     : '#10b981',
        product  : '#f59e0b',
        category : '#8b5cf6',
        tag      : '#ec4899',
        author   : '#f97316',
        service  : '#14b8a6',
        home     : '#64748b'
    };
    function typeColor(t) { return _typeColors[t] || '#64748b'; }

    /* ── Import button ───────────────────────────────────────────────── */
    $('#seo-sitemap-import-btn').on('click', function(){
        var $btn     = $(this);
        var sitemapUrl = $('#seo-sitemap-url').val().trim();
        var $status  = $('#seo-sitemap-status');
        var $result  = $('#seo-sitemap-result');

        if (!sitemapUrl) {
            $status.css('color','var(--c-red)').text('⚠ Please enter a sitemap URL.');
            return;
        }

        $btn.text('Fetching…').prop('disabled', true);
        $status.css('color','var(--c-muted)').text('Fetching and parsing sitemap, please wait…');
        $result.hide();
        $('#seo-sitemap-send-status').text('');
        _sitemapUrls = [];

        $.post(seoDash.ajax, {
            action      : 'seo_dash_import_sitemap',
            nonce       : seoDash.nonce,
            sitemap_url : sitemapUrl
        }, function(r){
            $btn.text('🔍 Import Sitemap').prop('disabled', false);

            if (!r.success || !r.data) {
                var msg = (r.data && r.data.message) ? r.data.message : 'Import failed — check the URL and try again.';
                $status.css('color','var(--c-red)').text('' + msg);
                return;
            }

            var urls  = r.data.urls  || [];
            var types = r.data.types || {};
            var count = r.data.count || urls.length;
            _sitemapUrls = urls;

            $status.css('color','var(--c-green)')
                   .text('✅ ' + count + ' URL' + (count === 1 ? '' : 's') + ' extracted successfully.');

            // Summary bar
            $('#seo-sitemap-count').text('📋 ' + count + ' URL' + (count === 1 ? '' : 's') + ' found');

            var $badges = $('#seo-sitemap-type-badges').empty();
            $.each(types, function(type, n){
                var label = type.charAt(0).toUpperCase() + type.slice(1);
                var color = typeColor(type);
                $badges.append(
                    '<span style="display:inline-flex;align-items:center;gap:4px;' +
                    'background:' + color + '22;color:' + color + ';' +
                    'border:1px solid ' + color + '55;border-radius:20px;' +
                    'padding:3px 10px;font-size:11px;font-weight:700;white-space:nowrap;">' +
                    label + ' <span style="opacity:.7;">(' + n + ')</span></span>'
                );
            });

            // Build table
            var $tbody = $('#seo-sitemap-tbody').empty();
            if (urls.length === 0) {
                $tbody.append('<tr><td colspan="3" style="text-align:center;padding:24px;color:var(--c-subtle);">No URLs found in this sitemap.</td></tr>');
            } else {
                $.each(urls, function(i, entry){
                    var u    = typeof entry === 'object' ? entry.url  : entry;
                    var t    = typeof entry === 'object' ? (entry.type || 'page') : 'page';
                    var cl   = typeColor(t);
                    var label = t.charAt(0).toUpperCase() + t.slice(1);
                    var safe = $('<span>').text(u).html();
                    $tbody.append(
                        '<tr>' +
                        '<td style="padding:7px 10px;font-size:11px;color:var(--c-muted);">' + (i+1) + '</td>' +
                        '<td style="padding:7px 10px;">' +
                          '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;' +
                          'background:' + cl + '22;color:' + cl + ';border:1px solid ' + cl + '44;white-space:nowrap;">' +
                          label + '</span>' +
                        '</td>' +
                        '<td style="padding:7px 10px;font-size:12px;word-break:break-all;">' +
                          '<a href="' + safe + '" target="_blank" style="color:var(--c-primary);text-decoration:none;">' + safe + '</a>' +
                        '</td>' +
                        '</tr>'
                    );
                });
            }

            $result.show();
            
            // Automatically push to GA and SC
            setTimeout(function() {
                sendSitemapUrls('ga', true);
                setTimeout(function() { sendSitemapUrls('sc', true); }, 500);
            }, 100);

        }).fail(function(xhr){
            $btn.text('🔍 Import Sitemap').prop('disabled', false);
            $status.css('color','var(--c-red)').text('Network error. Please try again.');
        });
    });

    /* ── Push sitemap URLs to GA or SC (uses dedicated push handler) ── */
    function sendSitemapUrls(scope, isAuto) {
        if (!_sitemapUrls.length) {
            seoToast('No URLs to send. Please import a sitemap first.', 'err');
            return;
        }
        var $gaBtn    = $('#seo-sitemap-add-ga-btn');
        var $scBtn    = $('#seo-sitemap-add-sc-btn');
        var $statusEl = $('#seo-sitemap-send-status');
        var rid       = $gaBtn.data('rid');
        var $btn      = scope === 'ga' ? $gaBtn : $scBtn;
        var origHtml  = $btn.html();

        $btn.text('Saving…').prop('disabled', true);
        $statusEl.css('color','var(--c-muted)')
                 .text('Registering URLs in Database (' + (scope === 'ga' ? 'Analytics' : 'Search Console') + ')…');

        $.post(seoDash.ajax, {
            action    : 'seo_dash_sitemap_push',
            nonce     : seoDash.nonce,
            report_id : rid,
            scope     : scope,
            urls      : JSON.stringify(_sitemapUrls)          // Send JSON string to bypass max_input_vars
        }, function(r){
            $btn.html(origHtml).prop('disabled', false);
            if (r.success) {
                var ins  = r.data.db_inserted !== undefined ? r.data.db_inserted : (r.data.inserted || 0);
                var skip = r.data.skipped || 0;
                var dest = scope === 'ga' ? 'Analytics' : 'Search Console';
                var msg  = '';
                if (ins > 0) {
                    msg = '✅ ' + ins + ' new URL' + (ins===1?'':'s') + ' registered in Database (' + dest + ')';
                    if (skip > 0) msg += ' (' + skip + ' already existed / skipped duplicates)';
                } else {
                    msg = '✅ All URLs already up-to-date in Database (' + dest + ') — ' + skip + ' existing URLs skipped (no duplicates).';
                }
                
                var existingText = $statusEl.text();
                if (existingText && !existingText.includes('Saving')) {
                    $statusEl.css('color','var(--c-green)').html(existingText + '<br>' + msg);
                } else {
                    $statusEl.css('color','var(--c-green)').text(msg);
                }
                seoToast(ins > 0 ? (ins + ' new URLs added to ' + dest + '!') : ('All ' + dest + ' URLs already up-to-date!'), 'ok');
                
                // Resolve target active month (Active Frontend Month -> Latest Month with Data -> Current Month)
                var activeFront = $('#db-ga-active-frontend-msg').text().trim();
                var firstDataMonth = $('#db-ga-month-select option[value!=""]').first().val();
                var targetMonth = (activeFront && activeFront !== 'None') ? activeFront : (firstDataMonth || ((new Date()).getFullYear() + '-' + ('0' + ((new Date()).getMonth() + 1)).slice(-2)));

                // Live-update the database workspace without needing a page refresh
                if (typeof seoDashOpenMonth === 'function') {
                    seoDashOpenMonth(scope, targetMonth);
                }

                if (!isAuto || scope === 'sc') {
                    // Only clear the list when the final auto-push finishes
                    _sitemapUrls = [];

                    // Auto-land on Database GA tab showing the active month
                    setTimeout(function(){
                        $('.db-type-tab[data-dbtype="ga"]').trigger('click');
                        var parts = targetMonth.split('-');
                        if (parts.length === 2) {
                            $('#db-ga-year').val(parts[0]);
                            $('#db-ga-month').val(parts[1]);
                        }
                        seoDashOpenMonth('ga', targetMonth);
                    }, 1200);
                }
            } else {
                var errMsg = (r.data && r.data.message) ? r.data.message : 'Failed to save.';
                $statusEl.css('color','var(--c-red)').text('' + errMsg);
                seoToast(errMsg, 'err');
            }
        }).fail(function(){
            $btn.html(origHtml).prop('disabled', false);
            $statusEl.css('color','var(--c-red)').text('Network error.');
        });
    }

    $('#seo-sitemap-add-ga-btn').on('click', function(){ sendSitemapUrls('ga'); });
    $('#seo-sitemap-add-sc-btn').on('click', function(){ sendSitemapUrls('sc'); });

    /* ── Custom URL Routing Rules Modal Handlers ────────────────────── */
    var availableUrlTypes = [
        { value: 'city', label: 'City / Location' },
        { value: 'service', label: 'Service Page' },
        { value: 'post', label: 'Blog Post' },
        { value: 'page', label: 'Standard Page' },
        { value: 'category', label: 'Category' },
        { value: 'tag', label: 'Tag' },
        { value: 'author', label: 'Author' },
        { value: 'product', label: 'Product / Shop' },
        { value: 'other', label: 'Other' }
    ];

    function renderUrlRuleRow(pattern, type) {
        var opts = '';
        availableUrlTypes.forEach(function(t) {
            opts += '<option value="' + t.value + '" ' + (t.value === type ? 'selected' : '') + '>' + t.label + '</option>';
        });
        return '<tr>' +
            '<td style="padding:6px 10px;">' +
                '<input type="text" class="seo-in rule-pattern-input" value="' + $('<span>').text(pattern || '').html() + '" placeholder="e.g. */locations/* or /locations/" style="width:100%;font-size:12px;padding:4px 8px;">' +
            '</td>' +
            '<td style="padding:6px 10px;">' +
                '<select class="seo-in rule-type-select" style="width:100%;font-size:12px;padding:4px 8px;">' + opts + '</select>' +
            '</td>' +
            '<td style="padding:6px 10px;text-align:center;">' +
                '<button type="button" class="seo-icon-btn seo-icon-btn-d seo-del-rule-row-btn" style="color:var(--c-red);">🗑</button>' +
            '</td>' +
        '</tr>';
    }

    $('#seo-open-url-rules-btn').on('click', function(e) {
        e.preventDefault();
        var $modal = $('#seo-url-rules-modal');
        var $tbody = $('#seo-url-rules-tbody').html('<tr><td colspan="3" style="text-align:center;padding:20px;color:var(--c-muted);">Loading rules…</td></tr>');
        $modal.css('display', 'flex');

        $.post(seoDash.ajax, {
            action: 'seo_dash_get_url_rules',
            nonce: seoDash.nonce,
            report_id: <?php echo $rid; ?>
        }, function(r) {
            $tbody.empty();
            if (r.success && r.data && r.data.rules && r.data.rules.length) {
                r.data.rules.forEach(function(rule) {
                    $tbody.append(renderUrlRuleRow(rule.pattern, rule.type));
                });
            } else {
                $tbody.append(renderUrlRuleRow('*/locations/*', 'city'));
                $tbody.append(renderUrlRuleRow('*/services/*', 'service'));
                $tbody.append(renderUrlRuleRow('*/blog/*', 'post'));
            }
        });
    });

    $('.seo-close-url-rules-modal').on('click', function() {
        $('#seo-url-rules-modal').hide();
    });

    $('#seo-add-url-rule-btn').on('click', function() {
        $('#seo-url-rules-tbody').append(renderUrlRuleRow('', 'page'));
    });

    $(document).on('click', '.seo-del-rule-row-btn', function() {
        $(this).closest('tr').remove();
    });

    $('#seo-save-url-rules-btn').on('click', function() {
        var $btn = $(this).text('Saving…').prop('disabled', true);
        var rules = [];
        $('#seo-url-rules-tbody tr').each(function() {
            var pat = $.trim($(this).find('.rule-pattern-input').val());
            var typ = $(this).find('.rule-type-select').val();
            if (pat && typ) {
                rules.push({ pattern: pat, type: typ });
            }
        });

        $.post(seoDash.ajax, {
            action: 'seo_dash_save_url_rules',
            nonce: seoDash.nonce,
            report_id: <?php echo $rid; ?>,
            rules: rules
        }, function(r) {
            $btn.text('💾 Save Routing Rules').prop('disabled', false);
            if (r.success) {
                seoToast('Routing rules saved successfully!', 'ok');
                $('#seo-url-rules-modal').hide();
            } else {
                seoToast('Failed to save rules.', 'err');
            }
        }).fail(function() {
            $btn.text('💾 Save Routing Rules').prop('disabled', false);
            seoToast('Request failed.', 'err');
        });
    });

    $('#seo-run-test-url-btn').on('click', function() {
        var testUrl = $.trim($('#seo-test-url-input').val());
        var $res = $('#seo-test-url-result');
        if (!testUrl) {
            $res.html('<span style="color:var(--c-red);">Please enter a URL to test.</span>');
            return;
        }
        $res.html('<span style="color:var(--c-muted);">Testing URL…</span>');

        $.post(seoDash.ajax, {
            action: 'seo_dash_test_url_rule',
            nonce: seoDash.nonce,
            report_id: <?php echo $rid; ?>,
            test_url: testUrl
        }, function(r) {
            if (r.success) {
                var ruleText = r.data.matched_rule ? ' (Matched pattern: <code>' + r.data.matched_rule + '</code>)' : ' (Default fallback)';
                $res.html('<span style="color:var(--c-green);font-weight:700;">✅ Target Sub-tab: ' + r.data.final_type.toUpperCase() + '</span>' + ruleText);
            } else {
                $res.html('<span style="color:var(--c-red);">❌ ' + (r.data && r.data.message ? r.data.message : 'Error') + '</span>');
            }
        }).fail(function() {
            $res.html('<span style="color:var(--c-red);">❌ Request failed</span>');
        });
    });

    /* 
       INDIVIDUAL ROW DELETE — works for all data tables
       */
       
    $(document).on('click', '.seo-del-url-btn', function(e){
        e.preventDefault();
        var $btn  = $(this);
        var tbl   = $btn.data('table');   // ga | sc
        var url   = $btn.data('url');
        var ids   = $btn.data('ids');
        
        if (!confirm('Move this URL to Trash?')) return;
        
        var idsArr = ids ? String(ids).split(',').filter(Boolean) : [];
        var urlsArr = url ? [url] : [];
        
        $btn.prop('disabled', true).text('…');
        $.post(seoDash.ajax, {
            action     : 'seo_dash_bulk_data_action',
            nonce      : seoDash.nonce,
            table      : tbl,
            report_id  : <?php echo $rid; ?>,
            bulk_action: 'trash',
            ids        : idsArr,
            urls       : urlsArr
        }, function(r){
            if (r.success) {
                if (typeof seoToast === 'function') seoToast('URL moved to trash.', 'ok');
                var $row = $btn.closest('tr');
                $row.removeClass('db-ga-active-row').addClass('db-ga-trashed-row');
                if (!$('.db-view-trash-btn[data-dbtype="'+tbl+'"]').hasClass('viewing-trash')) {
                    $row.fadeOut(300);
                }
            } else {
                alert('Error: ' + seoGaErrMsg(r));
            }
            $btn.prop('disabled', false).text('🗑️');
        }).fail(function(){
            $btn.prop('disabled', false).text('🗑️');
            alert('Server error.');
        });
    });
    $(document).on('click', '.seo-del-row-btn', function(e){
        e.preventDefault();
        var $btn  = $(this);
        var tbl   = $btn.data('table');   // ga | sc | gmb | technical | backlinks | leads | pages
        var id    = parseInt($btn.data('id'), 10);
        if (!id) return;
        if (!confirm('Delete this row permanently? This cannot be undone.')) return;
        $btn.prop('disabled', true).text('…');
        $.post(seoDash.ajax, {
            action     : 'seo_dash_delete_data_row',
            nonce      : seoDash.nonce,
            table      : tbl,
            row_id     : id,
            row_action : 'delete'
        }, function(r){
            if (r.success) {
                var $row = $btn.closest('tr');
                $row.css({ opacity: 0, transition: 'opacity .25s' });
                setTimeout(function(){
                    $row.remove();
                    if (tbl === 'leads' && typeof window.seoLdRefreshLeadsTable === 'function') {
                        window.seoLdRefreshLeadsTable();
                    }
                }, 280);
                seoToast('Row deleted.', 'ok');
                _gaUpdateBulkBar();
                _scUpdateBulkBar();
            } else {
                $btn.prop('disabled', false).text('🗑️');
                seoToast((r.data && r.data.message) ? r.data.message : 'Delete failed.', 'err');
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('🗑️');
            seoToast('Network error.', 'err');
        });
    });

    /* 
       GA BULK ACTIONS
       */
    function _gaGetCheckedIds() {
        var ids = [];
        $('.ga-row-chk:checked').each(function(){ ids.push(parseInt($(this).val(), 10)); });
        return ids;
    }
    function _gaUpdateBulkBar() {
        var ids = _gaGetCheckedIds();
        var n   = ids.length;
        if (n > 0) {
            $('#ga-bulk-bar').css('display','flex');
            $('#ga-bulk-count').text(n + ' row' + (n===1?'':'s') + ' selected');
        } else {
            $('#ga-bulk-bar').hide();
        }
        // sync header checkboxes
        $('.ga-select-all-chk').each(function(){
            var gtype = $(this).data('gtype');
            var total = $('.ga-row-chk[data-gtype="'+gtype+'"]').length;
            var checked = $('.ga-row-chk[data-gtype="'+gtype+'"]:checked').length;
            $(this).prop('checked', total > 0 && checked === total)
                   .prop('indeterminate', checked > 0 && checked < total);
        });
    }

    // Select-all header checkbox per GA type
    $(document).on('change', '.ga-select-all-chk', function(){
        var gtype = $(this).data('gtype');
        var chk   = $(this).prop('checked');
        $('.ga-row-chk[data-gtype="'+gtype+'"]').prop('checked', chk);
        _gaUpdateBulkBar();
    });
    $(document).on('change', '.ga-row-chk', function(){
        _gaUpdateBulkBar();
    });

    // Clear GA selection
    $(document).on('click', '#ga-bulk-clear', function(){
        $('.ga-row-chk').prop('checked', false);
        $('.ga-select-all-chk').prop('checked', false).prop('indeterminate', false);
        _gaUpdateBulkBar();
    });

    // GA bulk action buttons
    $(document).on('click', '.seo-ga-bulk-btn', function(){
        var action = $(this).data('action');  // trash | delete
        var ids    = _gaGetCheckedIds();
        if (!ids.length) { seoToast('No rows selected.', 'err'); return; }
        var label  = action === 'delete' ? 'permanently delete' : 'move to trash';
        if (!confirm('Are you sure you want to ' + label + ' ' + ids.length + ' selected row(s)?')) return;
        var $bar = $('#ga-bulk-bar');
        $bar.find('button').prop('disabled', true);
        $.post(seoDash.ajax, {
            action      : 'seo_dash_bulk_data_action',
            nonce       : seoDash.nonce,
            table       : 'ga',
            bulk_action : action,
            ids         : ids
        }, function(r){
            $bar.find('button').prop('disabled', false);
            if (r.success) {
                var done = (r.data && r.data.done) ? r.data.done : ids.length;
                if (action === 'delete') {
                    // Remove rows from DOM
                    ids.forEach(function(id){
                        $('input.ga-row-chk[value="'+id+'"]').closest('tr').remove();
                    });
                } else {
                    // Mark trashed rows visually
                    ids.forEach(function(id){
                        $('input.ga-row-chk[value="'+id+'"]').closest('tr')
                            .css({opacity:0.4, fontStyle:'italic'})
                            .find('.ga-row-chk').prop('checked', false);
                    });
                }
                seoToast(done + ' row(s) ' + (action==='delete'?'deleted':'moved to trash') + '.', 'ok');
                _gaUpdateBulkBar();
            } else {
                seoToast((r.data && r.data.message) ? r.data.message : 'Bulk action failed.', 'err');
            }
        }).fail(function(){ seoToast('Network error.', 'err'); $bar.find('button').prop('disabled', false); });
    });

    /* 
       SC BULK ACTIONS
       */
    function _scGetCheckedIds() {
        var ids = [];
        $('.sc-row-chk:checked').each(function(){ ids.push(parseInt($(this).val(), 10)); });
        return ids;
    }
    function _scUpdateBulkBar() {
        var ids = _scGetCheckedIds();
        var n   = ids.length;
        if (n > 0) {
            $('#sc-bulk-bar').css('display','flex');
            $('#sc-bulk-count').text(n + ' row' + (n===1?'':'s') + ' selected');
        } else {
            $('#sc-bulk-bar').hide();
        }
        $('.sc-select-all-chk').each(function(){
            var sctype  = $(this).data('sctype');
            var total   = $('.sc-row-chk[data-sctype="'+sctype+'"]').length;
            var checked = $('.sc-row-chk[data-sctype="'+sctype+'"]:checked').length;
            $(this).prop('checked', total > 0 && checked === total)
                   .prop('indeterminate', checked > 0 && checked < total);
        });
    }

    $(document).on('change', '.sc-select-all-chk', function(){
        var sctype = $(this).data('sctype');
        var chk    = $(this).prop('checked');
        $('.sc-row-chk[data-sctype="'+sctype+'"]').prop('checked', chk);
        _scUpdateBulkBar();
    });
    $(document).on('change', '.sc-row-chk', function(){
        _scUpdateBulkBar();
    });

    $(document).on('click', '#sc-bulk-clear', function(){
        $('.sc-row-chk').prop('checked', false);
        $('.sc-select-all-chk').prop('checked', false).prop('indeterminate', false);
        _scUpdateBulkBar();
    });

    $(document).on('click', '.seo-sc-bulk-btn', function(){
        var action = $(this).data('action');
        var ids    = _scGetCheckedIds();
        if (!ids.length) { seoToast('No rows selected.', 'err'); return; }
        var label  = action === 'delete' ? 'permanently delete' : 'move to trash';
        if (!confirm('Are you sure you want to ' + label + ' ' + ids.length + ' selected row(s)?')) return;
        var $bar = $('#sc-bulk-bar');
        $bar.find('button').prop('disabled', true);
        $.post(seoDash.ajax, {
            action      : 'seo_dash_bulk_data_action',
            nonce       : seoDash.nonce,
            table       : 'sc',
            bulk_action : action,
            ids         : ids
        }, function(r){
            $bar.find('button').prop('disabled', false);
            if (r.success) {
                var done = (r.data && r.data.done) ? r.data.done : ids.length;
                if (action === 'delete') {
                    ids.forEach(function(id){
                        $('input.sc-row-chk[value="'+id+'"]').closest('tr').remove();
                    });
                } else {
                    ids.forEach(function(id){
                        $('input.sc-row-chk[value="'+id+'"]').closest('tr')
                            .css({opacity:0.4, fontStyle:'italic'})
                            .find('.sc-row-chk').prop('checked', false);
                    });
                }
                seoToast(done + ' row(s) ' + (action==='delete'?'deleted':'moved to trash') + '.', 'ok');
                _scUpdateBulkBar();
            } else {
                seoToast((r.data && r.data.message) ? r.data.message : 'Bulk action failed.', 'err');
            }
        }).fail(function(){ seoToast('Network error.', 'err'); $bar.find('button').prop('disabled', false); });
    });

    // Initialise bar states
    _gaUpdateBulkBar();
    _scUpdateBulkBar();

    /* 
       AUTO-FETCH ALL PERIODS
       */
    $(document).on('click', '.seo-all-periods-btn', function(){
        var $btn       = $(this);
        var rid        = $btn.data('rid');
        var filterType = $btn.data('filter'); // all | service | blog
        var target     = $btn.data('target'); // ga | service | blog
        var $status    = $('#ga-all-periods-status');
        var origHtml   = $btn.html();

        // Show loading state
        $('.seo-all-periods-btn').prop('disabled', true);
        $btn.html('Fetching…');
        $status.css('color','#15803d').html(
            '<span class="seo-periods-prog">Connecting to GA4…</span>');

        var labels = {
            all     : 'All Types (Pages + Blogs)',
            service : 'Service Pages',
            blog    : 'Blog Posts'
        };

        var steps = ['7 Days','30 Days','90 Days','Overall'];
        var stepIdx = 0;
        var stepTimer = setInterval(function(){
            if (stepIdx < steps.length) {
                $status.find('.seo-periods-prog').text(
                    'Fetching ' + steps[stepIdx] + '… (' + (stepIdx+1) + '/4)');
                stepIdx++;
            } else {
                clearInterval(stepTimer);
            }
        }, 8000);

        $.post(seoDash.ajax, {
            action      : 'seo_dash_ga_fetch_all_periods',
            nonce       : seoDash.nonce,
            report_id   : rid,
            filter_type : filterType,
            target      : target
        }, function(r){
            clearInterval(stepTimer);
            $('.seo-all-periods-btn').prop('disabled', false);
            $btn.html(origHtml);

            if (r.success) {
                var d   = r.data || {};
                var ins = d.upserted || 0;
                var pc  = d.period_counts || {};
                var msg = '✅ Saved <strong>' + ins + '</strong> rows for <strong>' +
                          (labels[filterType] || filterType) + '</strong>:<br>';
                $.each(pc, function(k, v){
                    msg += '&nbsp;&nbsp;• ' + k + ': ' + v + ' rows ';
                });
                msg += '<br><span style="font-size:12px;color:var(--c-muted);">ℹ️ Fetched URLs have been automatically synced to Blog/Service tabs based on sitemap mapping.</span>';

                // Add action buttons to copy to Pages/Blogs
                msg += '<div style="margin-top:10px;display:flex;gap:8px;">';
                if (filterType === 'all' || filterType === 'service') {
                    msg += '<button class="seo-btn seo-btn-sm seo-add-to-pages-btn" style="background:#6366f1;color:#fff;" data-rid="' + rid + '" data-target="service">Add to Service Pages</button>';
                }
                if (filterType === 'all' || filterType === 'blog') {
                    msg += '<button class="seo-btn seo-btn-sm seo-add-to-pages-btn" style="background:#0ea5e9;color:#fff;" data-rid="' + rid + '" data-target="blog">Add to Blog Posts</button>';
                }
                msg += '</div>';

                $status.html(msg);
                seoToast(ins + ' rows fetched and updated!', 'ok');
            } else {
                var errMsg = (r.data && r.data.message) ? r.data.message : 'Fetch failed.';
                $status.css('color','#dc2626').text('' + errMsg);
                seoToast(errMsg, 'err');
            }
        }).fail(function(){
            clearInterval(stepTimer);
            $('.seo-all-periods-btn').prop('disabled', false);
            $btn.html(origHtml);
            $status.css('color','#dc2626').text('Network error.');
            seoToast('Network error.', 'err');
        });
    });

    /* 
       COPY GA DATA TO PAGES/BLOGS
       */
    $(document).on('click', '.seo-add-to-pages-btn', function(){
        var $btn   = $(this);
        var rid    = $btn.data('rid');
        var target = $btn.data('target');
        var orig   = $btn.html();

        $btn.prop('disabled', true).html('Adding...');

        $.post(seoDash.ajax, {
            action      : 'seo_dash_ga_to_pages',
            nonce       : seoDash.nonce,
            report_id   : rid,
            page_target : target
        }, function(r){
            if (r.success) {
                var d = r.data || {};
                var done = d.done || 0;
                $btn.html('✅ Added ' + done + ' to ' + (target==='service'?'Pages':'Blogs'));
                seoToast(done + ' URLs added/updated in ' + target + ' tab. Please reload the page to view changes.', 'ok');
            } else {
                $btn.prop('disabled', false).html(orig);
                seoToast(r.data && r.data.message ? r.data.message : 'Failed to add', 'err');
            }
        }).fail(function(){
            $btn.prop('disabled', false).html(orig);
            seoToast('Network error', 'err');
        });
    });

        // Generic CSV Format Download
        $(document).on('click', '.seo-download-format-btn', function(){
            var type = $(this).data('type');
            var csv = '';

            if (type === 'service') {
                // Columns: url (required), title, keyword, 30 days, overall, rank, ai overview, month
                csv  = 'url,title,keyword,30 days,overall,rank,ai overview,month\n';
                csv += 'https://example.com/hvac,HVAC Services in Richmond VA,hvac richmond va,1200,8500,1,0,2025-04\n';
                csv += 'https://example.com/ac-repair,AC Repair Services,ac repair richmond,850,5200,2,1,2025-04\n';
                csv += 'https://example.com/heating,Heating Services,,320,2100,,,\n';
            } else if (type === 'blog') {
                // Columns: url (required), title, keyword, 30 days, overall, rank, ai overview, publish date
                csv  = 'url,title,keyword,30 days,overall,rank,ai overview,publish date\n';
                csv += 'https://example.com/blog/hvac-guide,How to Choose the Best HVAC System,best hvac system,850,4200,2,0,2025-01-15\n';
                csv += 'https://example.com/blog/ac-tips,5 AC Maintenance Tips,ac maintenance tips,430,1800,3,1,2025-02-10\n';
                csv += 'https://example.com/blog/heat-pump,Heat Pump vs Furnace Guide,,,,,0,\n';
            } else if (type === 'ga') {
                csv  = '"PAGE / PAGE TITLE","VISIT","7 DAYS Sess.","7 DAYS Users","7 DAYS Views","30 DAYS Sess.","30 DAYS Users","30 DAYS Views","90 DAYS Sess.","90 DAYS Users","90 DAYS Views","OVERALL Sess.","OVERALL Users","OVERALL Views"\n';
                csv += '"Home Page - Example Site","https://example.com/","120","95","210","540","430","910","1800","1450","2900","7200","5800","11600"\n';
            } else if (type === 'sc') {
                csv  = 'period_type,month_key,date_from,date_to,page_url,page_title,clicks,impressions,ctr,position\n';
                csv += '30d,2025-04,2025-03-25,2025-04-24,https://example.com/,Home Page,150,3200,4.69,12.5\n';
            } else if (type === 'gmb') {
                csv  = 'month,calls,bookings,directions,website clicks\n';
                csv += '2025-04,45,12,30,150\n';
            } else if (type === 'gmb_posts') {
                csv  = 'post title,post url,status,month\n';
                csv += 'Special Offer,https://g.page/r/example,Published,2025-04\n';
            } else if (type === 'technical') {
                csv  = '#,Audit Item,Status,Notes\n';
                csv += '1,XML Sitemap,pass,Sitemap found and valid\n';
                csv += '2,Missing Meta Description,warning,3 pages missing meta description\n';
                csv += '3,Broken Internal Links,fail,Found 2 broken links on homepage\n';
            } else if (type === 'backlinks') {
                csv  = 'type,website url,da,pa,spam%,live link,keyword,target url,date,status\n';
                csv += 'guest_post,https://example-blog.com/,45,30,2%,https://example-blog.com/post,my keyword,https://mysite.com/,2025-04-15,live\n';
            } else if (type === 'leads') {
                csv  = 'name,email,phone,message,zip,date & time,status,notes\n';
                csv += 'John Smith,john@example.com,555-0100,Interested in HVAC services,90210,2025-04-15 10:30 AM,new,Follow up soon\n';
                csv += 'Jane Doe,jane@example.com,,,,,,new,\n';
            } else if (type === 'click_tracking') {
                csv  = 'Text / Keyword,Source Page,Click Type,Submitted\n';
                csv += '(804)277-4328,https://richmondair.us/,Call Button,2026-07-11 15:28:44\n';
                csv += '(804)277-4328,https://richmondair.us/,Call Button,2026-07-11 15:29:24\n';
            } else {
                return;
            }

            var a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
            a.download = type + '_import_format.csv';
            a.click();
        });


        // Generic CSV Import
        $(document).on('change', '.seo-import-csv-input', function(e){
            var file = e.target.files[0];
            if (!file) return;
            
            var type = $(this).data('type');
            var reader = new FileReader();
            
            var btnLbl = $(this).closest('label');
            var origHtml = btnLbl.html();
            btnLbl.html('Reading file...').css('pointer-events', 'none');
            
            var $pane = $(this).closest('.seo-pages-period-pane');
            var activeSubType = $pane.length ? $pane.find('.pages-type-subtab-active').data('ptype') || '' : '';

            reader.onload = function(evt) {
                var csv = String(evt.target.result || '');
                csv = csv.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

                var lines = csv.split('\n');
                while (lines.length && lines[lines.length - 1].trim() === '') lines.pop();

                if (lines.length < 2) {
                    btnLbl.html(origHtml).css('pointer-events', 'auto');
                    seoToast('No data found in CSV.', 'err');
                    return;
                }

                var header    = lines[0];
                var dataLines = lines.slice(1);

                // Send rows in chunks so very large files (e.g. 9,000+ rows) never
                // hit PHP execution-time / memory / post-size limits in one request.
                var CHUNK_SIZE = 500;
                var chunks = [];
                for (var i = 0; i < dataLines.length; i += CHUNK_SIZE) {
                    chunks.push(dataLines.slice(i, i + CHUNK_SIZE));
                }
                var totalChunks   = chunks.length;
                var totalInserted = 0;
                var chunkIdx      = 0;

                function sendChunk() {
                    if (chunkIdx >= totalChunks) {
                        seoToast('✅ Imported ' + totalInserted + ' row(s). Reloading...', 'ok');
                        setTimeout(function(){ location.reload(); }, 1000);
                        return;
                    }

                    var csvChunk = header + '\n' + chunks[chunkIdx].join('\n');
                    if (totalChunks > 1) {
                        btnLbl.html('Importing ' + (chunkIdx + 1) + ' / ' + totalChunks + '...');
                    } else {
                        btnLbl.html('Importing...');
                    }

                    $.post(seoDash.ajax, {
                        action: 'seo_dash_import_csv',
                        nonce: seoDash.nonce,
                        report_id: <?php echo $rid; ?>,
                        table_type: type,
                        csv_data: csvChunk,
                        active_sub_type: activeSubType,
                        chunk_index: chunkIdx,
                        total_chunks: totalChunks
                    }, function(r){
                        if (r.success) {
                            totalInserted += (r.data && r.data.inserted) ? parseInt(r.data.inserted, 10) || 0 : 0;
                            chunkIdx++;
                            sendChunk();
                        } else {
                            btnLbl.html(origHtml).css('pointer-events', 'auto');
                            var errStr = 'Import failed.';
                            if (r.data) {
                                if (typeof r.data === 'string') errStr = r.data;
                                else if (typeof r.data === 'object') errStr = r.data.message || r.data.error || 'Import failed.';
                            }
                            if (totalChunks > 1) {
                                errStr = 'Batch ' + (chunkIdx + 1) + '/' + totalChunks + ' failed: ' + errStr + ' (' + totalInserted + ' row(s) imported before the error.)';
                            }
                            seoToast(errStr, 'err');
                        }
                    }).fail(function(){
                        btnLbl.html(origHtml).css('pointer-events', 'auto');
                        seoToast('Network error during import (batch ' + (chunkIdx + 1) + '/' + totalChunks + '). ' + totalInserted + ' row(s) imported so far.', 'err');
                    });
                }

                sendChunk();
            };
            reader.readAsText(file);
            $(this).val(''); // Reset
        });

        // Generic CSV Export

        $(document).on('click', '.seo-export-csv-btn', function(){
            var tableId = $(this).data('table');
            var type = $(this).data('type') || '';
            var table = null;

            if (tableId === 'db-ga-workspace-content') {
                // Find the active GA sub-pane table
                table = $('.db-type-subpane[data-type]:visible table').get(0);
                if (!table) { seoToast('No active table found to export.', 'err'); return; }
                tableId = 'ga_export';
            } else if (tableId === 'db-sc-workspace-content') {
                // Find the active SC sub-pane table
                table = $('.sc-type-subpane[data-type]:visible table').get(0);
                if (!table) { seoToast('No active table found to export.', 'err'); return; }
                tableId = 'sc_export';
            } else {
                table = document.getElementById(tableId);
            }
            
            if (!table) { seoToast('Table not found.', 'err'); return; }

            var rows = table.querySelectorAll('tr');
            var csv = Array.from(rows).map(function(row) {
                // Only export visible rows (handles trash toggling)
                if (window.getComputedStyle(row).display === 'none') return null;

                var cols = row.querySelectorAll('th, td');
                var rowData = Array.from(cols).map(function(col) {
                    // Skip checkbox columns
                    if (col.querySelector('input[type="checkbox"]')) return null;
                    // Skip icon-only columns (Remove / Visit button columns with no text data)
                    var hasLink  = col.querySelector('a');
                    var hasBtn   = col.querySelector('button');
                    var inputs   = col.querySelectorAll('input:not([type="checkbox"]), select, textarea');

                    var text = '';

                    // data-export-val takes highest priority (used for URL cells like Visit)
                    if (col.hasAttribute('data-export-val')) {
                        text = col.getAttribute('data-export-val') || '';
                        return '"' + text.replace(/"/g, '""').trim() + '"';
                    }

                    if (inputs.length > 0) {
                        // Prefer the first meaningful input value
                        inputs.forEach(function(inp) {
                            if (!text && inp.value && inp.value.trim()) {
                                if (inp.type === 'checkbox') {
                                    text = inp.checked ? '1' : '0';
                                } else {
                                    text = inp.value.trim();
                                }
                            }
                        });
                    }
                    // If no input value found, fall back to innerText (for plain-text cells and headers)
                    if (!text) {
                        // For link-only cells (Visit / Remove), use the href or skip
                        if (hasLink && !col.innerText.trim()) {
                            text = hasLink.href || '';
                        } else if (hasBtn && !col.innerText.trim()) {
                            return null; // skip pure-button cells
                        } else {
                            text = col.innerText || '';
                        }
                    }
                    return '"' + text.replace(/"/g, '""').trim() + '"';
                }).filter(function(c) { return c !== null; });

                if (rowData.length === 0) return null;
                return rowData.join(',');
            }).filter(function(r) { return r !== null; }).join('\n');

            var a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
            a.download = tableId + '.csv';
            a.click();
        });

        
        // Database Tab Add URL
        $(document).on('click', '.seo-db-add-url-btn', function(){
            var dbType = $(this).data('dbtype');
            if ($('#seo-db-add-url-modal').length === 0) {
                var modalHtml = `
                <div id="seo-db-add-url-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.7);z-index:9999;align-items:center;justify-content:center;">
                    <div style="background:var(--c-surf);width:400px;border-radius:8px;box-shadow:var(--shadow);border:1px solid var(--c-border);overflow:hidden;">
                        <div style="padding:16px 20px;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center;background:var(--c-surf2);">
                            <h3 style="margin:0;font-size:16px;color:var(--c-primary);" id="seo-db-add-title">Add URL to Database</h3>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" style="padding:4px;" onclick="jQuery('#seo-db-add-url-modal').fadeOut('fast');">✕</button>
                        </div>
                        <div style="padding:20px;">
                            <input type="hidden" id="seo-db-add-type">
                            <div style="margin-bottom:12px;">
                                <label style="display:block;font-size:12px;margin-bottom:4px;color:var(--c-muted);">URL <span style="color:#ef4444">*</span></label>
                                <input type="text" id="seo-db-add-url" class="seo-in" placeholder="https://..." style="width:100%;">
                            </div>
                            <div style="margin-bottom:12px;">
                                <label style="display:block;font-size:12px;margin-bottom:4px;color:var(--c-muted);">Page Type</label>
                                <select id="seo-db-add-pagetype" class="seo-in" style="width:100%;">
                                    <option value="service">Service Page</option>
                                    <option value="blog">Blog Post</option>
                                    <option value="location">Location Page</option>
                                    <option value="other">Other</option>
                                    <option value="custom">Custom Name...</option>
                                </select>
                                <input type="text" id="seo-db-add-customtype" class="seo-in" placeholder="Enter custom type..." style="width:100%;margin-top:8px;display:none;">
                            </div>
                        </div>
                        <div style="padding:16px 20px;border-top:1px solid var(--c-border);display:flex;justify-content:flex-end;gap:10px;background:var(--c-surf2);">
                            <button class="seo-btn seo-btn-ghost" onclick="jQuery('#seo-db-add-url-modal').fadeOut('fast');">Cancel</button>
                            <button class="seo-btn seo-btn-primary" id="seo-db-add-save">Save URL</button>
                        </div>
                    </div>
                </div>`;
                $('body').append(modalHtml);
                
                $('#seo-db-add-pagetype').on('change', function(){
                    if ($(this).val() === 'custom') {
                        $('#seo-db-add-customtype').show();
                    } else {
                        $('#seo-db-add-customtype').hide();
                    }
                });
                
                $('#seo-db-add-save').on('click', function(){
                    var cType = $('#seo-db-add-type').val();
                    var url = $('#seo-db-add-url').val();
                    var pType = $('#seo-db-add-pagetype').val();
                    if (pType === 'custom') {
                        pType = $('#seo-db-add-customtype').val();
                    }
                    
                    if (!url || !url.trim()) { seoToast('URL is required.', 'err'); return; }
                    
                    var $btn = $(this);
                    $btn.text('Saving...').prop('disabled', true);
                    
                    $.post(seoDash.ajax, {
                        action: 'seo_dash_add_db_map_url',
                        nonce: seoDash.nonce,
                        report_id: <?php echo $rid; ?>,
                        db_type: cType,
                        url: url,
                        page_type: pType
                    }, function(r){
                        if (r.success) {
                            location.reload();
                        } else {
                            $btn.text('Save URL').prop('disabled', false);
                            seoToast('Failed to add URL.', 'err');
                        }
                    }).fail(function(){
                        $btn.text('Save URL').prop('disabled', false);
                        seoToast('Network error.', 'err');
                    });
                });
            }
            
            $('#seo-db-add-type').val(dbType);
            $('#seo-db-add-title').text('Add URL to ' + (dbType === 'ga' ? 'Analytics' : 'Search Console'));
            $('#seo-db-add-url').val('');
            $('#seo-db-add-pagetype').val('service');
            
            $('#seo-db-add-url-modal').css('display', 'flex').hide().fadeIn('fast');
        });
        
        // Custom Page Add Single URL
        $(document).on('click', '.seo-custom-page-add-btn', function(){
            var type = $(this).data('type');
            var context = $(this).data('context') || '';
            
            if ($('#seo-custom-page-modal').length === 0) {
                var modalHtml = `
                <div id="seo-custom-page-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.7);z-index:9999;align-items:center;justify-content:center;">
                    <div style="background:var(--c-surf);width:500px;border-radius:8px;box-shadow:var(--shadow);border:1px solid var(--c-border);overflow:hidden;">
                        <div style="padding:16px 20px;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center;background:var(--c-surf2);">
                            <h3 style="margin:0;font-size:16px;color:var(--c-primary);" id="seo-cpm-title">Add URL</h3>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" style="padding:4px;" onclick="jQuery('#seo-custom-page-modal').fadeOut('fast');">✕</button>
                        </div>
                        <div style="padding:20px;">
                            <input type="hidden" id="seo-cpm-type">
                            <input type="hidden" id="seo-cpm-context">
                            <div style="margin-bottom:12px;">
                                <label style="display:block;font-size:12px;margin-bottom:4px;color:var(--c-muted);">URL <span style="color:#ef4444">*</span></label>
                                <input type="text" id="seo-cpm-url" class="seo-in" placeholder="https://..." style="width:100%;">
                            </div>
                            <div style="margin-bottom:12px;">
                                <label style="display:block;font-size:12px;margin-bottom:4px;color:var(--c-muted);">Title</label>
                                <input type="text" id="seo-cpm-title-val" class="seo-in" style="width:100%;">
                            </div>
                            <div style="display:flex;gap:12px;margin-bottom:12px;">
                                <div style="flex:1;">
                                    <label style="display:block;font-size:12px;margin-bottom:4px;color:var(--c-muted);">Keyword</label>
                                    <input type="text" id="seo-cpm-keyword" class="seo-in" style="width:100%;">
                                </div>
                                <div style="flex:1;">
                                    <label style="display:block;font-size:12px;margin-bottom:4px;color:var(--c-muted);">Ranked Page</label>
                                    <input type="text" id="seo-cpm-ranked" class="seo-in" style="width:100%;">
                                </div>
                            </div>
                            <div style="display:flex;gap:12px;margin-bottom:12px;">
                                <div style="flex:1;">
                                    <label style="display:block;font-size:12px;margin-bottom:4px;color:var(--c-muted);">Month</label>
                                    <input type="text" id="seo-cpm-month" class="seo-in" style="width:100%;">
                                </div>
                                <div style="flex:1;">
                                    <label style="display:block;font-size:12px;margin-bottom:4px;color:var(--c-muted);">Publish Date</label>
                                    <input type="date" id="seo-cpm-pubdate" class="seo-in" style="width:100%;">
                                </div>
                            </div>
                        </div>
                        <div style="padding:16px 20px;border-top:1px solid var(--c-border);display:flex;justify-content:flex-end;gap:10px;background:var(--c-surf2);">
                            <button class="seo-btn seo-btn-ghost" onclick="jQuery('#seo-custom-page-modal').fadeOut('fast');">Cancel</button>
                            <button class="seo-btn seo-btn-primary" id="seo-cpm-save">Save URL</button>
                        </div>
                    </div>
                </div>`;
                $('body').append(modalHtml);
                
                $('#seo-cpm-save').on('click', function(){
                    var cType = $('#seo-cpm-type').val();
                    var url = $('#seo-cpm-url').val();
                    var title = $('#seo-cpm-title-val').val();
                    var keyword = $('#seo-cpm-keyword').val();
                    var ranked = $('#seo-cpm-ranked').val();
                    var month = $('#seo-cpm-month').val();
                    var pubdate = $('#seo-cpm-pubdate').val();
                    
                    if (!url || !url.trim()) { seoToast('URL is required.', 'err'); return; }
                    
                    var escapeCsv = function(str) {
                        return '"' + (str || '').replace(/"/g, '""') + '"';
                    };
                    
                    var isOverview = $('#seo-cpm-context').val() === 'overview' ? 1 : 0;
                    var csvLine = [url, title, keyword, ranked, '', month, pubdate, isOverview].map(escapeCsv).join(',');
                    var csv = "url,title,keyword,ranked_page,ai_overview,month,publish_date,show_on_overview\n" + csvLine;
                    
                    var $btn = $(this);
                    $btn.text('Saving...').prop('disabled', true);
                    
                    $.post(seoDash.ajax, {
                        action: 'seo_dash_import_csv',
                        nonce: seoDash.nonce,
                        report_id: <?php echo $rid; ?>,
                        table_type: cType,
                        csv_data: csv
                    }, function(r){
                        if (r.success) {
                            location.reload();
                        } else {
                            $btn.text('Save URL').prop('disabled', false);
                            seoToast('Failed to add URL.', 'err');
                        }
                    }).fail(function(){
                        $btn.text('Save URL').prop('disabled', false);
                        seoToast('Network error.', 'err');
                    });
                });
            }
            
            $('#seo-cpm-type').val(type);
            $('#seo-cpm-context').val(context);
            $('#seo-cpm-title').text('Add URL to ' + (type === 'blog' ? 'Blog Posts' : 'Service Pages'));
            $('#seo-cpm-url').val('');
            $('#seo-cpm-title-val').val('');
            $('#seo-cpm-keyword').val('');
            $('#seo-cpm-ranked').val('');
            $('#seo-cpm-month').val('');
            $('#seo-cpm-pubdate').val('');
            
            $('#seo-custom-page-modal').css('display', 'flex').hide().fadeIn('fast');
        });

    /* ── Hide dropdown on outside click ─────────────────────── */
    $(document).on('click', function(){
        $('.seo-assign-dropdown').hide();
    });

    /* ── Multi-assign dropdown action ───────────────────────── */
    /* ── Assigned dropdown: reposition to fixed so it escapes overflow containers ── */
    $(document).on('click', '.seo-assign-dropdown-btn', function(e){
        e.stopPropagation();
        var $btn = $(this);
        var $drop = $btn.next('.seo-assign-dropdown');
        var isVisible = $drop.is(':visible');
        // Hide all
        $('.seo-assign-dropdown').hide().css({position:'absolute',top:'100%',left:'0'});
        if (isVisible) return;
        // Position as fixed relative to button
        var rect = this.getBoundingClientRect();
        var isDark = document.body.classList.contains('seo-dark') || document.documentElement.classList.contains('dark') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        $drop.css({
            display: 'block',
            position: 'fixed',
            top: (rect.bottom + 2) + 'px',
            left: rect.left + 'px',
            zIndex: 99999,
            background: isDark ? '#1e1e2e' : '#ffffff',
            border: '1px solid ' + (isDark ? '#444' : '#ddd'),
            color: isDark ? '#e0e0e0' : '#1a1a1a'
        });
    });
    $(document).on('click', function(){ 
        $('.seo-assign-dropdown').hide(); 
    });
    $(document).on('click', '.seo-assign-dropdown', function(e){ e.stopPropagation(); });

    /* ── Multi-report dropdown checkboxes: only update UI state, no AJAX ── */
    /* Actual save happens when "Save Assignments" button is clicked         */
    $(document).on('change', '.seo-assign-multi-chk', function(){
        var $chk      = $(this);
        var cid       = $chk.data('cid');
        var rid       = parseInt($chk.data('rid'));
        var isChecked = $chk.is(':checked');
        var currentRid = <?php echo intval($rid); ?>;

        // If this checkbox is for the current report, sync the main row checkbox too
        if (rid === currentRid) {
            $('tr.seo-client-full-row[data-cid="' + cid + '"] .seo-client-intg-chk').prop('checked', isChecked);
        }

        // Update the dropdown button label to reflect pending state
        var $drop = $chk.closest('.seo-assign-dropdown');
        var $btn  = $drop.prev('.seo-assign-dropdown-btn');
        var anyChecked = $drop.find('.seo-assign-multi-chk').is(':checked');
        $btn.html((anyChecked ? '✓ Assigned' : 'Assign') + ' <span style="font-size:8px;">▼</span>');
    });

    /* NOTE: the "save pending multi-report dropdown selections" logic     */
    /* that used to live here as a SEPARATE click handler on               */
    /* #seo-assign-clients-btn has been merged into the main handler above */
    /* (search "Save Assignments"). Having two independent handlers each   */
    /* firing their own uncoordinated AJAX calls — with the page reloading */
    /* on a fixed timer set by the first one — was the root cause of       */
    /* assignments intermittently going missing after Save + reload.       */

});
});
</script>

<?php
// ── Helper: pages panel (service / blog) with grouped periods ──────────────
function seo_dash_pages_panel_html(int $rid, string $type, string $label, array $months): string {
    global $gsheet_links;
    ob_start();

    // ── SCOPE FIX: resolve variables that only exist in the outer tab scope ──
    // $overview_overall is defined only inside the 'overview' tab block; when
    // this function is called for 'service' or 'blog' tabs it is undefined,
    // causing `var startYear = ;` → SyntaxError in the browser.
    $overview_overall = get_option(
        "seo_dash_overview_overall_{$rid}",
        [ 'from' => date( 'Y-m-d', strtotime( '-1 year' ) ), 'to' => date( 'Y-m-d' ), 'traffic' => '10785' ]
    );

    // $_linked_intg_id / $_linked_gsheet_id are never defined anywhere in the
    // file; derive them from the already-loaded $gsheet_links for this tab type.
    $_current_link     = isset( $gsheet_links[ $type ] ) && is_array( $gsheet_links[ $type ] ) ? $gsheet_links[ $type ] : [];
    $_linked_intg_id   = $_current_link['intg_id']        ?? '';
    $_linked_gsheet_id = $_current_link['spreadsheet_id'] ?? '';
    // ────────────────────────────────────────────────────────────────────────
    
    // Get custom mapped URLs
    $custom_map = get_option("seo_dash_custom_pages_{$rid}_{$type}", []);
    if (!is_array($custom_map)) $custom_map = [];

    // Get the active frontend month to calculate traffic
    $active_month = get_option("seo_dash_active_month_{$rid}_ga", '');
    if (!$active_month && !empty($months)) $active_month = $months[0];
    
    // Load month-specific rows (for 7d/90d and URL auto-detection)
    $rows_monthly = $active_month ? SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_ga, $rid, $active_month) : [];
    
    // NUCLEAR FIX: Also load ALL rows (no month filter) so 30d and overall always display.
    // The KPI fetch may store them under a different month_key so we must scan everything.
    global $wpdb;
    $rows_all_periods = $wpdb->get_results( $wpdb->prepare(
        "SELECT page_url, period_type, users, sessions FROM " . SEO_Dash_Database::$data_ga .
        " WHERE report_id = %d AND period_type IN ('30d','overall') AND trashed = 0 ORDER BY id ASC",
        $rid
    ), ARRAY_A ) ?: [];
    
    $ga_type_map = get_option("seo_dash_sitemap_types_{$rid}_ga", []);
    if (!is_array($ga_type_map)) $ga_type_map = [];
    
    if (!function_exists('seo_dash_ga_type_admin_panel')) {
        function seo_dash_ga_type_admin_panel(array $row, array $map): string {
            $url = trim($row['page_url'] ?? '');
            if ($url) {
                if (isset($map[$url])) return $map[$url];
                if (isset($map[trailingslashit($url)])) return $map[trailingslashit($url)];
                if (isset($map[untrailingslashit($url)])) return $map[untrailingslashit($url)];
                $path = parse_url($url, PHP_URL_PATH);
                if ($path) {
                    if (isset($map[$path])) return $map[$path];
                    if (isset($map[trailingslashit($path)])) return $map[trailingslashit($path)];
                    if (isset($map[untrailingslashit($path)])) return $map[untrailingslashit($path)];
                }
            }
            $pt = trim($row['page_title'] ?? '');
            if ($pt) {
                if (preg_match('/^\[sitemap:([a-z0-9_-]+)\]/i', $pt, $m)) return strtolower($m[1]);
                if (preg_match('/^\[([a-z0-9_-]+)\]$/i', $pt, $m)) return strtolower($m[1]);
            }
            return 'other';
        }
    }
    
    // Auto-detect URLs for this type
    $auto_urls = [];
    foreach ($rows_monthly as $r) {
        $t = seo_dash_ga_type_admin_panel($r, $ga_type_map);
        if ($type === 'service' && !in_array($t, ['page','service','location','city','product','portfolio'])) continue;
        if ($type === 'blog' && !in_array($t, ['post','blog','category'])) continue;
        
        $u = trim($r['page_url']);
        if ($u) {
            $raw_title = $r['page_title'] ?: $u;
            // Clean up [sitemap:...] tags from the title
            $clean_title = preg_replace('/^\[(?:sitemap:)?[a-z0-9_-]+\]\s*/i', '', $raw_title);
            
            // If the title is still just a URL, format it into a readable string
            if (preg_match('/^https?:\/\//i', $clean_title) || preg_match('/^www\./i', $clean_title)) {
                $path = parse_url($clean_title, PHP_URL_PATH);
                $path = trim($path ?? '', '/');
                if (!$path) {
                    $clean_title = 'Home Page';
                } else {
                    $parts = explode('/', $path);
                    $last = end($parts);
                    $clean_title = ucwords(str_replace(['-', '_'], ' ', $last));
                }
            }
            $auto_urls[$u] = $clean_title;
        }
    }
    
    // Create a fast lookup map for All periods and sessions
    $traffic = [];
    $kpis = [
        '7d'      => ['users' => 0, 'sessions' => 0],
        '30d'     => ['users' => 0, 'sessions' => 0],
        '90d'     => ['users' => 0, 'sessions' => 0],
        'overall' => ['users' => 0, 'sessions' => 0]
    ];
    
    // First: load 7d and 90d from the active month rows
    foreach ($rows_monthly as $r) {
        $u = trim($r['page_url']);
        if (!$u) continue;
        if (!isset($traffic[$u])) {
            $traffic[$u] = [
                '7d' => 0, '30d' => 0, '90d' => 0, 'overall' => 0,
                '7d_s' => 0, '30d_s' => 0, '90d_s' => 0, 'overall_s' => 0
            ];
        }
        $p = $r['period_type'] ?: '7d';
        if ($p === 'monthly') $p = '30d';
        // Only accumulate 7d and 90d from the month-filtered rows here
        if (in_array($p, ['7d', '90d'])) {
            $traffic[$u][$p] += (int)$r['users'];
            $traffic[$u][$p.'_s'] += (int)$r['sessions'];
        }
    }
    
    // Second: load 30d and overall from ALL rows (no month filter) — NUCLEAR FIX
    foreach ($rows_all_periods as $r) {
        $u = trim($r['page_url']);
        if (!$u) continue;
        if (!isset($traffic[$u])) {
            $traffic[$u] = [
                '7d' => 0, '30d' => 0, '90d' => 0, 'overall' => 0,
                '7d_s' => 0, '30d_s' => 0, '90d_s' => 0, 'overall_s' => 0
            ];
        }
        $p = $r['period_type'];
        if ($p === '30d' || $p === 'overall') {
            $traffic[$u][$p] += (int)$r['users'];
            $traffic[$u][$p.'_s'] += (int)$r['sessions'];
        }
    }

    // Merge auto URLs into custom_map so they all render
    foreach ($auto_urls as $u => $title) {
        if (!isset($custom_map[$u])) {
            $custom_map[$u] = [
                'url' => $u,
                'title' => $title,
                'keyword' => '',
                'ranked_page' => '',
                'ai_overview' => 0,
                'month' => '',
                'publish_date' => ''
            ];
        } else if (empty($custom_map[$u]['title']) || $custom_map[$u]['title'] === $u || preg_match('/^\[(?:sitemap:)?[a-z0-9_-]+\]/i', $custom_map[$u]['title']) || preg_match('/^https?:\/\//i', $custom_map[$u]['title'])) {
            // Update the title if it's just the URL or has a sitemap tag
            $custom_map[$u]['title'] = $title;
        }
    }

    // Calculate aggregate KPIs for URLs present in this tab
    foreach ($custom_map as $u => $cdata) {
        if (!empty($cdata['trashed'])) continue;
        if (isset($traffic[$u])) {
            foreach (['7d', '30d', '90d', 'overall'] as $p) {
                $kpis[$p]['users'] += $traffic[$u][$p];
                $kpis[$p]['sessions'] += $traffic[$u][$p.'_s'];
            }
        }
    }

    // Group by sub-type for Service Pages
    $groups = [];
    if ($type === 'service') {
        foreach ($custom_map as $u => $cdata) {
            $st = $ga_type_map[$u] ?? 'other';
            $groups[$st][$u] = $cdata;
        }
        ksort($groups);
    }

    ?>
    <div class="seo-panel">
        <div class="seo-panel-hd">
            <h2><?php echo esc_html($label); ?></h2>
            <div style="display:flex;gap:8px;">
                <button class="seo-btn seo-btn-primary seo-btn-sm seo-custom-page-add-btn" data-type="<?php echo $type; ?>">＋ Add URL</button>
                <button class="seo-btn seo-btn-sm seo-bulk-add-btn" data-type="<?php echo $type; ?>" style="background:#f59e0b;color:#fff;border:none;">⚡ Bulk Add</button>
            </div>
        </div>

        <!-- KPI Cards -->
        <?php 
        $colors = [
            '7d'      => ['bd' => '#0ea5e9', 'val' => '#0284c7'],
            '30d'     => ['bd' => '#8b5cf6', 'val' => '#7c3aed'],
            '90d'     => ['bd' => '#10b981', 'val' => '#059669'],
            'overall' => ['bd' => '#f59e0b', 'val' => '#d97706']
        ];
        $kpi_labels = ['7d' => '7 DAYS', '30d' => '30 DAYS', '90d' => '90 DAYS', 'overall' => 'OVERALL'];
        ?>
        <div style="display:flex;gap:12px;margin:16px 20px;">
            <?php foreach (['7d', '30d', '90d', 'overall'] as $k): ?>
            <div style="flex:1;background:var(--c-surf);border:1px solid var(--c-border);border-top:3px solid <?php echo $colors[$k]['bd']; ?>;border-radius:6px;padding:12px 16px;box-shadow:0 1px 3px rgba(15,23,42,0.05);">
                <div style="font-size:11px;font-weight:700;color:var(--c-muted);margin-bottom:8px;"><?php echo $kpi_labels[$k]; ?></div>
                <div style="font-size:20px;font-weight:800;color:<?php echo $colors[$k]['val']; ?>;margin-bottom:4px;"><?php echo number_format($kpis[$k]['users']); ?></div>
                <div style="font-size:11px;color:var(--c-subtle);">Users · <?php echo number_format($kpis[$k]['sessions']); ?> sess</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="seo-pages-period-pane" data-type="<?php echo $type; ?>">
            <div style="margin:20px;display:flex;flex-direction:column;gap:12px;">
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;padding:16px;">
                    <div style="display:flex;align-items:center;gap:16px;">
                        <span style="font-weight:700;color:var(--c-text);font-size:14px;">🏆 Add to Overview Ranked Pages:</span>
                        <button class="seo-btn seo-btn-sm seo-add-overview-btn" data-type="<?php echo $type; ?>" data-target="selected" style="background:var(--c-surf);color:var(--c-primary);border:1px solid var(--c-border);">✅ Add Selected (<span class="seo-sel-count">0</span>)</button>
                        <button class="seo-btn seo-btn-sm seo-add-overview-btn" data-type="<?php echo $type; ?>" data-target="all" style="background:var(--c-primary);color:#fff;border:none;">➕ Add All Pages</button>
                    </div>
                    <div style="font-size:12px;color:var(--c-muted);margin-top:8px;">Select rows using checkboxes then click Add Selected, or click Add All to add every page.</div>
                </div>
                <div style="background:var(--c-surf2);border:1px solid var(--c-border);border-radius:8px;padding:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span style="font-weight:700;color:var(--c-text);font-size:14px;">🎯 Bulk Set Rank:</span>
                    <button class="seo-btn seo-btn-sm seo-bulk-rank-btn" data-val="ai_ov" data-type="<?php echo $type; ?>" style="background:var(--c-surf);color:var(--c-text);border:1px solid var(--c-border);">🤖 AI Ov</button>
                    <button class="seo-btn seo-btn-sm seo-bulk-rank-btn" data-val="1" data-type="<?php echo $type; ?>" style="background:var(--c-surf);color:var(--c-text);border:1px solid var(--c-border);">P1</button>
                    <button class="seo-btn seo-btn-sm seo-bulk-rank-btn" data-val="2" data-type="<?php echo $type; ?>" style="background:var(--c-surf);color:var(--c-text);border:1px solid var(--c-border);">P2</button>
                    <button class="seo-btn seo-btn-sm seo-bulk-rank-btn" data-val="3" data-type="<?php echo $type; ?>" style="background:var(--c-surf);color:var(--c-text);border:1px solid var(--c-border);">P3</button>
                    <button class="seo-btn seo-btn-sm seo-bulk-rank-btn" data-val="4" data-type="<?php echo $type; ?>" style="background:var(--c-surf);color:var(--c-text);border:1px solid var(--c-border);">P4</button>
                    <button class="seo-btn seo-btn-sm seo-bulk-rank-btn" data-val="5+" data-type="<?php echo $type; ?>" style="background:var(--c-surf);color:var(--c-text);border:1px solid var(--c-border);">P5+</button>
                    <button class="seo-btn seo-btn-sm seo-bulk-rank-btn" data-val="ai_uncheck" data-type="<?php echo $type; ?>" style="background:var(--c-surf);color:var(--c-text);border:1px solid var(--c-border);">X Uncheck AI Ov</button>
                    <div style="border-left:1px solid var(--c-border);padding-left:12px;display:flex;gap:12px;font-size:12px;color:var(--c-text);">
                        <label style="display:flex;align-items:center;gap:4px;"><input type="radio" name="seo_bulk_rank_target_<?php echo $type; ?>" value="selected" checked style="accent-color:var(--c-primary);margin:0;"> Selected</label>
                        <label style="display:flex;align-items:center;gap:4px;"><input type="radio" name="seo_bulk_rank_target_<?php echo $type; ?>" value="visible" style="accent-color:var(--c-primary);margin:0;"> All Visible</label>
                    </div>
                </div>
            </div>
            
            <div class="seo-table-wrap" style="max-height:600px;overflow-x:auto;overflow-y:auto;border:1px solid var(--c-border);border-radius:4px;">
                <div style="padding:12px 20px; border-bottom:1px solid var(--c-border); background:var(--c-surf2); display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <select class="seo-in seo-in-sm seo-custom-page-bulk-action-sel" data-type="<?php echo $type; ?>" style="width:auto;padding:4px 8px;font-size:12px;">
                            <option value="">Bulk Actions</option>
                            <option value="trash">Move Selected to Trash</option>
                            <option value="restore">Restore Selected from Trash</option>
                            <option value="remove">Delete Permanently</option>
                            <option value="remove_all">Delete All</option>
                        </select>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-custom-page-bulk-action-btn" data-type="<?php echo $type; ?>">Apply</button>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-export-csv-btn" data-table="seo-pages-tbl-<?php echo $type; ?>">⬇️ Export CSV</button>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-download-format-btn" data-type="<?php echo $type; ?>">⬇️ Download Format</button>
                        <label class="seo-btn seo-btn-ghost seo-btn-sm" style="margin:0;cursor:pointer;">
                            ⬆️ Import CSV
                            <input type="file" class="seo-import-csv-input" data-type="<?php echo $type; ?>" accept=".csv" style="display:none;">
                        </label>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm seo-custom-page-view-trash-btn" data-type="<?php echo $type; ?>">🗑️ View Trash</button>
                        <span style="display:inline-block;width:1px;height:20px;background:var(--c-border);margin:0 2px;"></span>
                        <div id="gsheet-sync-bar-<?php echo $type; ?>" style="display:flex;align-items:center;gap:6px;">
                            <?php $link = $gsheet_links[$type] ?? []; if ( !empty($link['spreadsheet_id']) ) : ?>
                            <span style="font-size:11px;color:var(--c-muted);white-space:nowrap;">📊 <?php echo esc_html( $link['spreadsheet_name'] ?? 'Linked Sheet' ); ?> / <?php echo esc_html( $link['tab_name'] ?? '' ); ?></span>
                            <button class="seo-btn seo-btn-sm gsheet-sync-btn" data-tabtype="<?php echo $type; ?>" style="height:28px;font-size:11px;background:var(--c-primary);color:#fff;">⬆ Update</button>
                            <button class="seo-btn seo-btn-sm gsheet-export-btn" data-tabtype="<?php echo $type; ?>" style="height:28px;font-size:11px;background:var(--c-surf);color:var(--c-primary);border:1px solid var(--c-primary);">⬇ Export to Sheet</button>
                            <span class="gsheet-sync-status" data-tabtype="<?php echo $type; ?>" style="font-size:11px;color:var(--c-muted);"></span>
                            <?php else: ?>
                            <span style="font-size:11px;color:var(--c-muted);">⚠️ No sheet linked. Go to Integrations tab to link.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($type === 'service' && !empty($groups)): ?>
                <!-- Sub-tabs for Types (Service Pages only) -->
                <div class="pages-subtabs-wrapper" style="display:flex;gap:0;border-bottom:2px solid var(--c-border);background:var(--c-surf2);padding:0 20px;">
                    <?php 
                    $first = true;
                    foreach ($groups as $st => $urls): 
                        $active_count = 0;
                        foreach ($urls as $u => $cdata) { if (empty($cdata['trashed'])) $active_count++; }
                        $active_cls = $first ? 'pages-type-subtab-active' : '';
                        $color_sty  = $first ? 'border-bottom:3px solid var(--c-primary);color:var(--c-primary);' : 'border-bottom:3px solid transparent;color:var(--c-muted);';
                    ?>
                    <button class="pages-type-subtab <?php echo $active_cls; ?>" data-ptype="<?php echo esc_attr($st); ?>" data-context="<?php echo $type; ?>"
                            style="padding:10px 18px;font-size:12px;font-weight:700;background:none;border:none;cursor:pointer;margin-bottom:-2px;white-space:nowrap;<?php echo $color_sty; ?>">
                        <?php echo esc_html(ucfirst($st)); ?> <span style="font-size:10px;opacity:.7;">(<?php echo $active_count; ?>)</span>
                    </button>
                    <?php $first = false; endforeach; ?>
                </div>
                <?php endif; ?>
                <table class="seo-table" id="seo-pages-tbl-<?php echo $type; ?>" style="table-layout:fixed;width:auto;min-width:100%;">
                    <thead style="position:sticky;top:0;z-index:2;background:var(--c-surf2);">
                        <tr>
                            <th style="width:40px;text-align:center;"><input type="checkbox" class="seo-custom-page-check-all" data-type="<?php echo $type; ?>"></th>
                            <th style="width:40px;text-align:center;">SR</th>
                            <th style="width:550px;"><?php echo $type === 'blog' ? 'Article Title' : 'Service Name'; ?></th>
                            <th style="width:40px;text-align:center;">Visit</th>
                            <th style="width:180px;"><?php echo $type === 'blog' ? 'Article Keyword' : 'Keyword'; ?></th>
                            <th style="text-align:center;width:70px;line-height:1.2;">30 Days</th>
                            <th style="text-align:center;width:70px;line-height:1.2;">Overall</th>
                            <th style="text-align:center;width:50px;line-height:1.2;">Rank</th>
                            <th style="text-align:center;width:60px;">AI Overview</th>
                            <?php if ($type === 'service'): ?>
                            <th style="width:100px;">Month</th>
                            <?php else: ?>
                            <th style="width:100px;">Publish Date</th>
                            <?php endif; ?>
                            <th style="text-align:center;width:60px;">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($custom_map)) : ?>
                    <tr><td colspan="12" style="text-align:center;padding:32px;color:var(--c-subtle);">No URLs found. Fetch GA data or add URLs manually.</td></tr>
                    <?php else: 
                        $sr = 1;
                        foreach ($custom_map as $u => $cdata) : 
                            $u_esc = esc_attr($u);
                            $t_monthly = isset($traffic[$u]) ? number_format($traffic[$u]['30d']) : '—';
                            $t_overall = isset($traffic[$u]) ? number_format($traffic[$u]['overall']) : '—';
                            $is_trashed = !empty($cdata['trashed']);
                            $st = $ga_type_map[$u] ?? 'other';
                    ?>
                    <tr class="<?php echo $is_trashed ? 'db-custom-trashed-row' : 'db-custom-active-row'; ?> pages-row-type-<?php echo esc_attr($st); ?>" 
                        style="<?php echo $is_trashed ? 'display:none;background:rgba(239,68,68,0.15);' : ''; ?>">
                        <td style="text-align:center;">
                            <input type="checkbox" class="seo-custom-page-chk" value="<?php echo esc_url($u); ?>" data-type="<?php echo $type; ?>">
                        </td>
                        <td style="text-align:center;border-right:1px solid var(--c-border);"><?php echo $sr++; ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?php 
                                $disp_title = $cdata['title'] ?? '';
                                $disp_title = preg_replace('/^\[(?:sitemap:)?[a-z0-9_-]+\]\s*/i', '', $disp_title);
                                if (preg_match('/^https?:\/\//i', $disp_title) || preg_match('/^www\./i', $disp_title)) {
                                    $path = parse_url($disp_title, PHP_URL_PATH);
                                    $path = trim($path ?? '', '/');
                                    if (!$path) {
                                        $disp_title = 'Home Page';
                                    } else {
                                        $parts = explode('/', $path);
                                        $last = end($parts);
                                        $disp_title = ucwords(str_replace(['-', '_'], ' ', $last));
                                    }
                                }
                            ?>
                            <input type="text" class="seo-in seo-custom-page-input" data-field="title" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($disp_title); ?>" style="width:100%;font-size:12px;padding:4px 8px;border:1px solid transparent;background:transparent;text-overflow:ellipsis;" onfocus="this.style.border='1px solid var(--c-border)';this.style.background='var(--c-surf)';" onblur="this.style.border='1px solid transparent';this.style.background='transparent';">
                        </td>
                        <td style="text-align:center;" data-export-val="<?php echo esc_attr($u); ?>">
                            <a href="<?php echo esc_url($u); ?>" target="_blank" style="color:var(--c-primary);text-decoration:none;font-size:14px;">↗</a>
                        </td>
                        <td>
                            <input type="text" class="seo-in seo-custom-page-input" data-field="keyword" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($cdata['keyword'] ?? ''); ?>" placeholder="comma separated..." style="width:100%;font-size:12px;padding:4px 8px;">
                        </td>
                        <td style="text-align:center;font-size:12px;font-weight:600;"><?php echo $t_monthly; ?></td>
                        <td style="text-align:center;font-size:12px;font-weight:600;"><?php echo $t_overall; ?></td>
                        
                        <td style="text-align:center;padding-left:4px;padding-right:4px;">
                            <input type="text" class="seo-in seo-custom-page-input" data-field="ranked_page" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($cdata['ranked_page'] ?? ''); ?>" style="width:36px !important;min-width:0;max-width:100%;font-size:12px;padding:4px 2px;text-align:center;">
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" class="seo-custom-page-input" data-field="ai_overview" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="1" <?php checked(!empty($cdata['ai_overview'])); ?>>
                        </td>
                        
                        <?php if ($type === 'service'): ?>
                        <td style="padding-left:4px;padding-right:4px;">
                            <input type="month" class="seo-in seo-custom-page-input" data-field="month" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($cdata['month'] ?? ''); ?>" style="width:100%;font-size:12px;padding:4px;">
                        </td>
                        <?php else: ?>
                        <td style="padding-left:4px;padding-right:4px;">
                            <input type="date" class="seo-in seo-custom-page-input" data-field="publish_date" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" value="<?php echo esc_attr($cdata['publish_date'] ?? ''); ?>" style="width:100%;font-size:12px;padding:4px;">
                        </td>
                        <?php endif; ?>
                        
                        <td style="text-align:center;">
                            <button class="seo-icon-btn seo-icon-btn-d seo-custom-page-remove-btn" data-url="<?php echo $u_esc; ?>" data-type="<?php echo $type; ?>" title="Remove URL">✕</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div><!-- /.seo-table-wrap -->

        </div><!-- /.seo-pages-period-pane -->
    </div>
    <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
    if (!window.seoCustomPagesBound) {
        window.seoCustomPagesBound = true;
        seoJQ(function($){

        // Sub-tabs for sitemap type groups (e.g. "Page" / "Product" on the
        // Service Pages tab). This used to live only in the Overview tab's
        // script, which never loads when viewing the Service Pages / Blog
        // Posts tabs directly — so the buttons rendered here (data-ptype)
        // had no click handler at all and clicking them did nothing.
        jQuery(document).on('click', '.pages-type-subtab', function(){
            var $btn = jQuery(this);
            var ptype = $btn.data('ptype');
            var $container = $btn.closest('.seo-pages-period-pane');

            $btn.siblings().css({'border-bottom-color':'transparent', 'color':'var(--c-muted)'}).removeClass('pages-type-subtab-active');
            $btn.css({'border-bottom-color':'var(--c-primary)', 'color':'var(--c-primary)'}).addClass('pages-type-subtab-active');

            // Hide all rows first, then show only those matching ptype
            $container.find('tbody tr').hide();

            var isTrashView = $container.find('.seo-custom-page-view-trash-btn').hasClass('viewing-trash');
            var rowClass = isTrashView ? '.db-custom-trashed-row' : '.db-custom-active-row';

            $container.find(rowClass + '.pages-row-type-' + ptype).show();

            // Re-trigger filter logic to ensure search bar still works
            var $search = $container.closest('.seo-panel').find('.seo-custom-page-search');
            if ($search.length && $search.val()) {
                $search.trigger('input');
            }
        });

        // Initialize sub-tabs display
        jQuery('.pages-type-subtab-active').each(function(){
            var ptype = jQuery(this).data('ptype');
            var $container = jQuery(this).closest('.seo-pages-period-pane');
            $container.find('tbody tr').hide();
            $container.find('.db-custom-active-row.pages-row-type-' + ptype).show();
        });

        // Auto save inputs
        jQuery(document).on('change', '.seo-custom-page-input', function(){
            var $el = jQuery(this);
            var isChk = $el.is(':checkbox');
            var val = isChk ? ($el.is(':checked') ? 1 : 0) : $el.val();
            
            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_save_custom_page_field',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                page_type: $el.data('type'),
                url: $el.data('url'),
                field: $el.data('field'),
                value: val
            }, function(r){
                if (!r.success) console.error(r.data);
            });
        });

        // Single Remove
        jQuery(document).on('click', '.seo-custom-page-remove-btn', function(){
            if (!confirm('Remove this entry permanently?')) return;
            var $btn = jQuery(this);
            var $row = $btn.closest('tr');
            var url  = $btn.data('url');
            var type = $btn.data('type');
            $btn.prop('disabled', true).text('…');
            
            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_remove_custom_page',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                page_type: type,
                urls: [url]
            }, function(r){
                if (r.success) {
                    $row.css({opacity:0, transition:'opacity .25s'});
                    setTimeout(function(){ $row.remove(); }, 280);
                } else {
                    $btn.prop('disabled', false).text('🗑️');
                    alert('Remove failed: ' + (r.data || 'Unknown error'));
                }
            }).fail(function(){
                $btn.prop('disabled', false).text('🗑️');
                alert('Network error on remove.');
            });
        });

        // View Trash Toggle for Custom Pages
        jQuery(document).on('click', '.seo-custom-page-view-trash-btn', function(){
            var $btn = jQuery(this);
            var type = $btn.data('type');
            var $container = $btn.closest('.seo-pages-period-pane');
            
            var isTrashView = $btn.hasClass('viewing-trash');
            if (isTrashView) {
                // Switch to active view
                $container.find('.db-custom-trashed-row').hide();
                $container.find('.db-custom-active-row').show();
                $btn.removeClass('viewing-trash').text('🗑️ View Trash');
            } else {
                // Switch to trash view
                $container.find('.db-custom-active-row').hide();
                $container.find('.db-custom-trashed-row').show();
                $btn.addClass('viewing-trash').text('⬅ Back to Active');
            }
        });

        // Bulk Actions
        jQuery(document).on('click', '.seo-custom-page-bulk-action-btn', function(){
            var $btn = jQuery(this);
            var type = $btn.data('type');
            var $container = $btn.closest('.seo-pages-period-pane, .seo-ov-page-tab');
            
            var action = $container.find('.seo-custom-page-bulk-action-sel').first().val();
            
            if (!action) {
                seoToast('Please select a bulk action.', 'error');
                return;
            }
            
            var urls = [];
            
            if (action === 'remove_all') {
                $container.find('.seo-custom-page-chk:visible').each(function(){
                    urls.push(jQuery(this).val());
                });
            } else {
                $container.find('.seo-custom-page-chk:checked:visible').each(function(){
                    urls.push(jQuery(this).val());
                });
            }
            
            if (!urls.length) { seoToast('No URLs to process.', 'error'); return; }
            
            if ((action === 'remove' || action === 'remove_all') && !confirm('Permanently remove ' + urls.length + ' URLs?')) return;
            if (action === 'trash' && !confirm('Move ' + urls.length + ' URLs to trash?')) return;
            
            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_bulk_custom_pages',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                page_type: type,
                bulk_action: action,
                urls: urls
            }, function(r){
                if (r.success) {
                    location.reload();
                }
            });
        });

        // Check All
        jQuery(document).on('change', '.seo-custom-page-check-all', function(){
            var isChecked = jQuery(this).is(':checked');
            var $table = jQuery(this).closest('table');
            $table.find('tbody tr:visible .seo-custom-page-chk').prop('checked', isChecked).trigger('change');
        });
        
        // Update sel count dynamically for the UI
        jQuery(document).on('change', '.seo-custom-page-chk, .seo-custom-page-check-all', function(){
            var $btn = jQuery(this);
            var type = $btn.data('type');
            var $container = $btn.closest('.seo-pages-period-pane, .seo-ov-page-tab');
            
            var c = $container.find('.seo-custom-page-chk:checked:visible').length;
            $container.find('.seo-add-overview-btn[data-target="selected"] .seo-sel-count').text(c);
        });

        // Bulk Rank handlers
        jQuery(document).on('click', '.seo-bulk-rank-btn, .seo-add-overview-btn', function(){
            var $btn = jQuery(this);
            var type = $btn.data('type');
            var isRank = $btn.hasClass('seo-bulk-rank-btn');
            var val = $btn.data('val');
            var isOverview = $btn.hasClass('seo-add-overview-btn');
            
            var $container = $btn.closest('.seo-pages-period-pane');
            var target = isOverview ? $btn.data('target') : $container.find('input[name^="seo_bulk_rank_target_"]:checked').val();
            
            var isTrashView = $container.find('.seo-custom-page-view-trash-btn').hasClass('viewing-trash');
            var urls = [];
            
            if (target === 'selected') {
                var selector = isTrashView ? '.db-custom-trashed-row .seo-custom-page-chk:checked' : '.db-custom-active-row .seo-custom-page-chk:checked';
                $container.find(selector).each(function(){
                    if (jQuery(this).closest('tr').css('display') !== 'none') urls.push(jQuery(this).val());
                });
            } else {
                var rowSel = isTrashView ? '.db-custom-trashed-row' : '.db-custom-active-row';
                $container.find(rowSel).each(function(){
                    if (jQuery(this).css('display') !== 'none') {
                        urls.push(jQuery(this).find('.seo-custom-page-chk').val());
                    }
                });
            }
            
            if (!urls.length) { seoToast('No URLs selected or visible.', 'err'); return; }
            
            var action = isOverview ? 'add_overview' : (val === 'ai_ov' ? 'ai_ov' : (val === 'ai_uncheck' ? 'ai_uncheck' : 'set_rank'));
            
            $btn.text('...').prop('disabled', true);
            
            jQuery.post(seoDash.ajax, {
                action: 'seo_dash_bulk_custom_pages',
                nonce: seoDash.nonce,
                report_id: <?php echo $rid; ?>,
                page_type: type,
                bulk_action: action,
                urls: urls,
                rank_val: val
            }, function(r){
                if (r.success) {
                    location.reload();
                } else {
                    seoToast('Failed to apply bulk update.', 'err');
                }
            });
        });

        // 2. BULK ADD MODAL LOGIC
        jQuery(document).on('click', '.seo-bulk-add-btn', function(){
            var type = jQuery(this).data('type');
            var ctx = jQuery(this).data('context') || '';
            if (jQuery('#seo-bulk-add-modal').length === 0) {
                var modalHtml = `
                <div id="seo-bulk-add-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.7);z-index:9999;align-items:center;justify-content:center;">
                    <div style="background:var(--c-surf);width:700px;border-radius:8px;box-shadow:var(--shadow);border:1px solid var(--c-border);overflow:hidden;">
                        <div style="padding:16px 20px;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center;background:var(--c-surf2);">
                            <h3 style="margin:0;font-size:16px;color:var(--c-primary);" id="seo-bam-title">⚡ Bulk Add</h3>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" style="padding:4px;" onclick="jQuery('#seo-bulk-add-modal').fadeOut('fast');">✕</button>
                        </div>
                        <div style="padding:20px;">
                            <input type="hidden" id="seo-bam-type">
                            <input type="hidden" id="seo-bam-context">
                            <div id="seo-bam-page-type-wrap" style="margin-bottom:12px;display:none;">
                                <label style="display:block;font-size:12px;margin-bottom:4px;color:var(--c-muted);">Page Type (URL-only import)</label>
                                <select id="seo-bam-pagetype" class="seo-in" style="width:100%;">
                                    <option value="service">Service Page</option><option value="blog">Blog Post</option><option value="location">Location Page</option><option value="other">Other</option><option value="custom">Custom Name...</option>
                                </select>
                                <input type="text" id="seo-bam-customtype" class="seo-in" placeholder="Enter custom type..." style="width:100%;margin-top:8px;display:none;">
                            </div>
                            <textarea id="seo-bam-text" style="width:100%;height:200px;font-family:monospace;font-size:12px;padding:12px;border:1px solid var(--c-primary);border-radius:4px;resize:vertical;" placeholder="Paste tab-separated rows here..."></textarea>
                        </div>
                        <div style="padding:16px 20px;border-top:1px solid var(--c-border);display:flex;justify-content:flex-end;gap:10px;background:var(--c-surf2);">
                            <button class="seo-btn seo-btn-ghost" onclick="jQuery('#seo-bulk-add-modal').fadeOut('fast');">Cancel</button>
                            <button class="seo-btn seo-btn-primary" id="seo-bam-save" style="background:#6366f1;">⚡ Add Rows</button>
                        </div>
                    </div>
                </div>`;
                jQuery('body').append(modalHtml);
                jQuery('#seo-bam-pagetype').on('change', function(){ jQuery('#seo-bam-customtype').toggle(jQuery(this).val() === 'custom'); });
                
                jQuery('#seo-bam-save').on('click', function(){
                    var type = jQuery('#seo-bam-type').val(), ctx = jQuery('#seo-bam-context').val(), text = jQuery('#seo-bam-text').val().trim();
                    if (!text) return seoToast('Paste data', 'err');
                    var lines = text.split('\n'), isUrlOnly = lines.length > 0 && lines[0].indexOf('\t') === -1;
                    if (isUrlOnly && ['ga','sc','ahrefs'].includes(type)) {
                        var pType = jQuery('#seo-bam-pagetype').val();
                        if (pType === 'custom') pType = jQuery('#seo-bam-customtype').val();
                        var urls = lines.map(l => l.trim()).filter(l => l);
                        var $btn = jQuery(this); $btn.text('...').prop('disabled', true);
                        jQuery.post(seoDash.ajax, { action: 'seo_dash_add_db_map_url', nonce: seoDash.nonce, report_id: <?php echo $rid; ?>, db_type: type, urls: urls, page_type: pType }, function(r){ if (r.success) location.reload(); });
                        return;
                    }
                    var csvLines = [], headers = "";
                    if (type === 'service' || type === 'blog') headers = "url,title,keyword,ranked_page,ai_overview,month,publish_date";
                    else if (type === 'ga') headers = "period_type,month_key,date_from,date_to,page_url,page_title,sessions,users,views,bounce_rate,avg_duration";
                    else if (type === 'sc') headers = "period_type,month_key,date_from,date_to,page_url,page_title,clicks,impressions,ctr,position";
                    else if (type === 'gmb') headers = "month_key,views_search,views_maps,clicks_website,calls";
                    else if (type === 'technical') headers = "date_str,issue_title,issue_status";
                    else if (type === 'backlinks') headers = "link_type,website_url,da,pa,spam_score,live_link,keyword,target_url,date,status";
                    else if (type === 'leads') headers = "#,Name,Email,Phone,Message,Zip,Date & Time,Status,Notes";
                    csvLines.push(headers);
                    for (var i=0; i<lines.length; i++) {
                        var cols = lines[i].trim().split('\t'); if(!cols[0]) continue;
                        csvLines.push(cols.map(c => '"' + c.replace(/"/g, '""').trim() + '"').join(','));
                    }
                    var $btn = jQuery(this); $btn.text('...').prop('disabled', true);
                    jQuery.post(seoDash.ajax, { action: 'seo_dash_import_csv', nonce: seoDash.nonce, report_id: <?php echo $rid; ?>, table_type: type, context: ctx, csv_data: csvLines.join('\n') }, function(r){ if (r.success) location.reload(); });
                });
            }
            jQuery('#seo-bam-type').val(type); jQuery('#seo-bam-context').val(ctx);
            jQuery('#seo-bam-text').val('');
            jQuery('#seo-bulk-add-modal').css('display', 'flex').hide().fadeIn('fast');
        });

        // 3. GLOBAL SEARCH/FILTER BAR
        window.seoDashInitTableFilters = function() {
            jQuery('.seo-table').each(function(){
                var $tbl = jQuery(this); 
                if ($tbl.hasClass('no-filter') || $tbl.prev('.seo-table-filters').length || ($tbl.prev('.pages-subtabs-wrapper').length && $tbl.prev('.pages-subtabs-wrapper').prev('.seo-table-filters').length)) return;
                
                var currentYear = new Date().getFullYear();
                var startYear = <?php echo isset($overview_overall['from']) && strtotime($overview_overall['from']) ? (int)date('Y', strtotime($overview_overall['from'])) : 2020; ?>;
                var yearOptions = '<option value="">All Years</option>';
                for (var y = currentYear; y >= startYear; y--) {
                    yearOptions += `<option value="${y}">${y}</option>`;
                }

                var filterHtml = `<div class="seo-table-filters" style="padding:10px 20px; background:var(--c-surf2); border-bottom:1px solid var(--c-border); display:flex; gap:20px; align-items:center;">
                    <div style="display:flex;align-items:center;gap:12px;flex:1;">
                        <span style="color:var(--c-muted);font-size:16px;">🔍</span>
                        <div style="position:relative;flex:1;max-width:300px;display:flex;align-items:center;gap:8px;">
                            <input type="text" class="seo-in seo-in-sm seo-global-search" placeholder="Search URLs, Names, Keywords..." style="width:100%;">
                            <span class="seo-global-search-clear" style="cursor:pointer;color:var(--c-muted);font-size:14px;padding:2px 6px;border-radius:4px;background:var(--c-surf);border:1px solid var(--c-border);">✕</span>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <label style="font-size:12px;color:var(--c-muted);">Month:</label><input type="month" class="seo-in seo-in-sm seo-global-month-filter">
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <label style="font-size:12px;color:var(--c-muted);">Year:</label>
                        <select class="seo-in seo-in-sm seo-global-year-filter">
                            ${yearOptions}
                        </select>
                    </div>
                </div>`;
                var $target = $tbl;
                if ($tbl.prev('.pages-subtabs-wrapper').length) {
                    $target = $tbl.prev('.pages-subtabs-wrapper');
                }
                $target.before(filterHtml);
            });
        };

        seoDashInitTableFilters();

        // 4. GLOBAL FILTER LOGIC
        jQuery(document).on('click', '.seo-global-search-clear', function(){
            var $wrap = jQuery(this).closest('.seo-table-filters');
            $wrap.find('.seo-global-search').val('').trigger('keyup');
        });

        jQuery(document).on('keyup change', '.seo-global-search, .seo-global-month-filter, .seo-global-year-filter', function(){
            var $filters = jQuery(this).closest('.seo-table-filters');
            var $tbl = $filters.nextAll('table.seo-table').first();
            var search = $filters.find('.seo-global-search').val().toLowerCase();
            var month = $filters.find('.seo-global-month-filter').val();
            var year = $filters.find('.seo-global-year-filter').val();
            
            $tbl.find('tbody tr').each(function(){
                var $tr = jQuery(this);
                if ($tr.find('td').length <= 1) return;
                
                // If it's trashed and trash isn't being viewed, it should already be hidden
                if ($tr.hasClass('db-custom-trashed-row') && !jQuery($tr.closest('.seo-pages-period-pane').find('.seo-custom-page-view-trash-btn')).hasClass('viewing-trash')) {
                    $tr.hide();
                    return;
                }
                
                var text = $tr.text().toLowerCase();
                var dates = [];
                $tr.find('input, select, textarea').each(function(){
                    var v = jQuery(this).val();
                    if (v) {
                        text += ' ' + v.toLowerCase();
                        if (jQuery(this).attr('type') === 'month' || jQuery(this).attr('type') === 'date') dates.push(v);
                    }
                });
                
                // Also check links (URLs)
                $tr.find('a').each(function(){
                    text += ' ' + jQuery(this).attr('href').toLowerCase() + ' ' + jQuery(this).text().toLowerCase();
                });
                
                var matchSearch = search === '' || text.indexOf(search) !== -1;
                var matchMonth = month === '';
                var matchYear = year === '';
                
                if (month !== '' || year !== '') {
                    for (var i=0; i<dates.length; i++) {
                        if (month && dates[i] && dates[i].indexOf(month) !== -1) matchMonth = true;
                        if (year && dates[i] && dates[i].indexOf(year) !== -1) matchYear = true;
                    }
                }
                
                if (matchSearch && matchMonth && matchYear) {
                    $tr.show();
                } else {
                    $tr.hide();
                }
            });
            // Update selected count
            var tblId = $tbl.attr('id') || '';
            var type = tblId.replace('seo-pages-tbl-', '');
            var isTrashView = jQuery('.seo-custom-page-view-trash-btn[data-type="'+type+'"]').hasClass('viewing-trash');
            var selector = isTrashView ? '.db-custom-trashed-row .seo-custom-page-chk:checked' : '.db-custom-active-row .seo-custom-page-chk:checked';
            var c = 0;
            $tbl.find(selector).each(function(){
                if (jQuery(this).closest('tr').css('display') !== 'none') c++;
            });
            jQuery('.seo-add-overview-btn[data-type="'+type+'"][data-target="selected"] .seo-sel-count').text(c);
        });
        });
    }

    /* Google Sheets sync + integrations panel JS was moved OUT of this
       function to top-level template scope (just after .seo-tab-body) so
       the Load Sheets / Save / Update handlers load on every tab, not only
       on Service Pages / Blog Posts. See the script below .seo-tab-body. */
    </script>

    <!-- ── Add-to-Pages Confirmation Modal ──────────────────────────────────── -->
    <div id="seo-add-to-pages-modal" class="seo-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.65);align-items:center;justify-content:center;">
        <div style="background:var(--c-surf);border:1px solid var(--c-border);border-radius:12px;width:100%;max-width:520px;padding:28px 32px;box-shadow:0 24px 60px rgba(0,0,0,.5);position:relative;">
            <button onclick="seoCloseModal('seo-add-to-pages-modal')" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:18px;cursor:pointer;color:var(--c-muted);">✕</button>
            <h3 style="margin:0 0 6px;font-size:16px;color:var(--c-text);">📋 Add Fetched URLs to Tabs?</h3>
            <p style="margin:0 0 18px;font-size:13px;color:var(--c-muted);">New URLs were detected. Choose which ones to add to your Service Pages and Blog Posts tabs.</p>

            <div id="seo-atp-service-section" style="display:none;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <input type="checkbox" id="seo-atp-service-all" checked style="width:14px;height:14px;cursor:pointer;">
                    <label for="seo-atp-service-all" style="font-size:13px;font-weight:700;color:var(--c-text);cursor:pointer;">🗂 Service Pages</label>
                    <span id="seo-atp-service-count" style="font-size:11px;color:var(--c-muted);"></span>
                </div>
                <div id="seo-atp-service-list" style="max-height:160px;overflow-y:auto;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:8px 10px;display:flex;flex-direction:column;gap:5px;"></div>
            </div>

            <div id="seo-atp-blog-section" style="display:none;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <input type="checkbox" id="seo-atp-blog-all" checked style="width:14px;height:14px;cursor:pointer;">
                    <label for="seo-atp-blog-all" style="font-size:13px;font-weight:700;color:var(--c-text);cursor:pointer;">✍️ Blog Posts</label>
                    <span id="seo-atp-blog-count" style="font-size:11px;color:var(--c-muted);"></span>
                </div>
                <div id="seo-atp-blog-list" style="max-height:160px;overflow-y:auto;background:var(--c-surf2);border:1px solid var(--c-border);border-radius:6px;padding:8px 10px;display:flex;flex-direction:column;gap:5px;"></div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button onclick="seoCloseModal('seo-add-to-pages-modal')" class="seo-btn seo-btn-ghost" style="height:36px;font-size:13px;">Skip</button>
                <button id="seo-atp-confirm-btn" class="seo-btn seo-btn-primary" style="height:36px;font-size:13px;">✅ Add Selected</button>
            </div>
        </div>
    </div>

    <script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
    /* ── Add-to-Pages Modal Logic ─────────────────────────────────────── */
    seoJQ(function($){
        var _rid = '<?php echo esc_js($rid); ?>';
        var _pending = null;

        // Called after any GA/SC fetch that returns pending_urls
        window.seoDashShowAddToPagesModal = function(pending) {
            if (!pending) return;
            var hasService = pending.service && Object.keys(pending.service).length > 0;
            var hasBlog    = pending.blog    && Object.keys(pending.blog).length    > 0;
            if (!hasService && !hasBlog) return;

            _pending = pending;

            // Populate service list
            var $svcSec  = $('#seo-atp-service-section');
            var $svcList = $('#seo-atp-service-list');
            $svcList.empty();
            if (hasService) {
                var svcKeys = Object.keys(pending.service);
                $('#seo-atp-service-count').text('(' + svcKeys.length + ' new)');
                $.each(pending.service, function(url, title) {
                    var shortUrl = url.replace(/^https?:\/\/[^\/]+/, '') || url;
                    $svcList.append(
                        '<label style="display:flex;align-items:flex-start;gap:7px;font-size:12px;color:var(--c-text);cursor:pointer;">' +
                        '<input type="checkbox" class="seo-atp-url-chk" data-tab="service" data-url="' + $('<div>').text(url).html() + '" data-title="' + $('<div>').text(title||'').html() + '" checked style="margin-top:2px;flex-shrink:0;">' +
                        '<span><span style="color:var(--c-primary);">' + $('<div>').text(title || shortUrl).html() + '</span><br><span style="color:var(--c-muted);font-size:11px;">' + $('<div>').text(shortUrl).html() + '</span></span>' +
                        '</label>'
                    );
                });
                $svcSec.show();
            } else {
                $svcSec.hide();
            }

            // Populate blog list
            var $blogSec  = $('#seo-atp-blog-section');
            var $blogList = $('#seo-atp-blog-list');
            $blogList.empty();
            if (hasBlog) {
                var blogKeys = Object.keys(pending.blog);
                $('#seo-atp-blog-count').text('(' + blogKeys.length + ' new)');
                $.each(pending.blog, function(url, title) {
                    var shortUrl = url.replace(/^https?:\/\/[^\/]+/, '') || url;
                    $blogList.append(
                        '<label style="display:flex;align-items:flex-start;gap:7px;font-size:12px;color:var(--c-text);cursor:pointer;">' +
                        '<input type="checkbox" class="seo-atp-url-chk" data-tab="blog" data-url="' + $('<div>').text(url).html() + '" data-title="' + $('<div>').text(title||'').html() + '" checked style="margin-top:2px;flex-shrink:0;">' +
                        '<span><span style="color:var(--c-primary);">' + $('<div>').text(title || shortUrl).html() + '</span><br><span style="color:var(--c-muted);font-size:11px;">' + $('<div>').text(shortUrl).html() + '</span></span>' +
                        '</label>'
                    );
                });
                $blogSec.show();
            } else {
                $blogSec.hide();
            }

            $('#seo-add-to-pages-modal').css('display','flex').hide().fadeIn(180);
        };

        // Select-all checkboxes
        $(document).on('change', '#seo-atp-service-all', function(){
            $('#seo-atp-service-list .seo-atp-url-chk').prop('checked', this.checked);
        });
        $(document).on('change', '#seo-atp-blog-all', function(){
            $('#seo-atp-blog-list .seo-atp-url-chk').prop('checked', this.checked);
        });

        // Confirm button
        $(document).on('click', '#seo-atp-confirm-btn', function(){
            var $btn = $(this).text('Adding…').prop('disabled', true);
            var serviceUrls = {}, blogUrls = {};

            $('#seo-atp-service-list .seo-atp-url-chk:checked').each(function(){
                serviceUrls[$(this).data('url')] = $(this).data('title') || '';
            });
            $('#seo-atp-blog-list .seo-atp-url-chk:checked').each(function(){
                blogUrls[$(this).data('url')] = $(this).data('title') || '';
            });

            if (!Object.keys(serviceUrls).length && !Object.keys(blogUrls).length) {
                seoCloseModal('seo-add-to-pages-modal');
                return;
            }

            $.post(seoDash.ajax, {
                action      : 'seo_dash_confirm_add_to_pages',
                nonce       : seoDash.nonce,
                report_id   : _rid,
                service_urls: serviceUrls,
                blog_urls   : blogUrls
            }, function(r){
                $btn.text('✅ Add Selected').prop('disabled', false);
                if (r.success) {
                    var msg = (r.data && r.data.message) ? r.data.message : '✅ URLs added!';
                    seoToast(msg, 'ok');
                    seoCloseModal('seo-add-to-pages-modal');
                    setTimeout(function(){ location.reload(); }, 1200);
                } else {
                    var err = (r.data && r.data.message) ? r.data.message : 'Failed to add URLs.';
                    seoToast(err, 'err');
                    $btn.prop('disabled', false);
                }
            }).fail(function(){
                $btn.text('✅ Add Selected').prop('disabled', false);
                seoToast('Network error.', 'err');
            });
        });

    });
    </script>
    <?php
    return ob_get_clean();
}
