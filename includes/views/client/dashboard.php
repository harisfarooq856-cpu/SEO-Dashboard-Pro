<?php if ( ! defined('ABSPATH') ) exit;
// License system removed — always false (no free-mode restrictions).
$_seo_free_overview = false;
// Variables: $report,$rid,$meta,$user,$brand,$logo,$agency,$footer,$support,$primary,
// $show,$tab_labels,$logout_url,$months_ga,$months_sc,$months_sv,$months_gmb,$months_bk,$months_ld,$months_tc,$summary

$nav_items = [
    'overview'  => ['icon'=>'📊','label'=>'Overview'],
    'analytics' => ['icon'=>'📈','label'=>'Analytics'],
    'sc'        => ['icon'=>'🔍','label'=>'Search Console'],
    'service'   => ['icon'=>'📄','label'=>'Service Pages'],
    'blog'      => ['icon'=>'','label'=>'Blog Posts'],
    'gmb'       => ['icon'=>'📍','label'=>'Google Business'],
    'technical' => ['icon'=>'','label'=>'Technical Audit'],
    'backlinks' => ['icon'=>'🔗','label'=>'Backlinks'],
    'leads'     => ['icon'=>'🎯','label'=>'Leads'],
    'ai'        => ['icon'=>'🤖','label'=>'AI Assistant'],
    'account'   => ['icon'=>'👤','label'=>'Account'],
];
$avatar_letter = strtoupper(substr($user ? $user->display_name : 'C', 0, 1));
$first_visible = '';
foreach ($show as $k=>$v) { if ($v) { $first_visible=$k; break; } }

// ── Shared helper: count distinct active URLs in data_ga for a type group ──
// Defined here (top of template, outside every $show[...] panel guard) so it is
// ALWAYS available. It used to live inside the Overview panel block, which meant
// that disabling the Overview tab left it undefined while the Blog panel still
// called it — causing a fatal "Call to undefined function" that aborted the page
// render (killing tab switching + all data loading on the client dashboard).
if (!function_exists('seo_dash_get_chart_type_saved')) {
    function seo_dash_get_chart_type_saved($saved_opt, $key, $default = 'bar') {
        if (!is_array($saved_opt)) return $default;
        if (isset($saved_opt[$key])) {
            if (is_array($saved_opt[$key]) && !empty($saved_opt[$key]['type'])) {
                return $saved_opt[$key]['type'];
            } elseif (is_string($saved_opt[$key]) && !empty($saved_opt[$key])) {
                return $saved_opt[$key];
            }
        }
        if (!empty($saved_opt['type'])) return $saved_opt['type'];
        if (!empty($saved_opt['trend_type'])) return $saved_opt['trend_type'];
        if (!empty($saved_opt['index_type'])) return $saved_opt['index_type'];
        if (!empty($saved_opt['dist_type'])) return $saved_opt['dist_type'];
        if (!empty($saved_opt['perf_type'])) return $saved_opt['perf_type'];
        if (!empty($saved_opt['posts_type'])) return $saved_opt['posts_type'];
        if (!empty($saved_opt['status_type'])) return $saved_opt['status_type'];
        if (!empty($saved_opt['bar_type'])) return $saved_opt['bar_type'];
        return $default;
    }
}

if (!function_exists('seo_dash_get_chart_title_saved')) {
    function seo_dash_get_chart_title_saved($saved_opt, $key, $default = '📊 Chart') {
        if (is_array($saved_opt) && isset($saved_opt[$key]) && is_array($saved_opt[$key]) && !empty($saved_opt[$key]['title'])) {
            return $saved_opt[$key]['title'];
        }
        return $default;
    }
}

if (!function_exists('seo_dash_get_chart_show_saved')) {
    function seo_dash_get_chart_show_saved($saved_opt, $key, $default = true) {
        if (is_array($saved_opt) && isset($saved_opt[$key]) && is_array($saved_opt[$key]) && isset($saved_opt[$key]['show'])) {
            return (bool)$saved_opt[$key]['show'];
        }
        return $default;
    }
}

if (!function_exists('seo_dash_count_ga_type_urls')) {
    /**
     * Count distinct active URLs in data_ga for a given type group.
     * Pass $type_group=[] to count ALL types (no filter).
     * Pass $exclude=true to count URLs whose type is NOT in $type_group.
     */
    function seo_dash_count_ga_type_urls(int $report_id, array $type_group, bool $exclude = false): int {
        global $wpdb;
        $tbl = SEO_Dash_Database::$data_ga;
        $type_map = get_option("seo_dash_sitemap_types_{$report_id}_ga", []);
        if (!is_array($type_map) || empty($type_map)) {
            // No type map — count all URLs in data_ga
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT page_url) FROM {$tbl} WHERE report_id=%d AND trashed=0",
                $report_id
            ));
        }
        if (empty($type_group)) {
            // No filter — count everything
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT page_url) FROM {$tbl} WHERE report_id=%d AND trashed=0",
                $report_id
            ));
        }
        // Build URL list based on include or exclude mode
        $urls_matched = array_keys(array_filter($type_map, fn($t) => in_array($t, $type_group, true)));
        if ($exclude) {
            // Exclude mode: all URLs NOT in the type_group
            $urls_in_type = array_keys(array_filter($type_map, fn($t) => !in_array($t, $type_group, true)));
        } else {
            $urls_in_type = $urls_matched;
        }
        if (empty($urls_in_type)) return 0;
        $placeholders = implode(',', array_fill(0, count($urls_in_type), '%s'));
        $params = array_merge([$report_id], $urls_in_type);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT page_url) FROM {$tbl} WHERE report_id=%d AND trashed=0 AND page_url IN ({$placeholders})",
            ...$params
        ));
    }
}
// Shared sitemap-type groups (also used to be defined inside the Overview block).
if (!isset($seo_dash_service_types)) {
    // Blog types are the whitelist — everything else is a service/page type.
    // This means author, product, page, service, location, city, portfolio,
    // other, and any future sitemap types all count as service pages.
    $seo_dash_blog_types    = ['post','blog','category','article','news','tag'];
    // Service types = NOT blog types. We keep the array for count helper use.
    $seo_dash_service_types = ['page','service','location','city','product','portfolio','other','author'];
}

// Analytics & Search Console charts now always render with fixed defaults —
// per-report chart configuration (type/color/metric/show) has been removed.
$ac_opt = [
    'show'  => true,
    'type'  => 'horizontalBar',
    'color' => '#8b5cf6',
    'metric'=> 'sessions'
];

$sc_opt = [
    'show'  => true,
    'type'  => 'bar',
    'color' => '#8b5cf6',
    'metric'=> 'clicks'
];

?>
<div class="seo-client-app" id="seo-client-app">

    <!-- Activate chartjs-plugin-datalabels (already loaded as a script
         dependency) and set sensible global defaults so EVERY chart on
         this dashboard shows its actual values directly on the chart,
         not just on hover. Individual charts can still opt out by setting
         their own plugins.datalabels.display = false. -->
    <script>
    (function(){
        var _seoDLDone = false;
        function seoEnableChartDataLabels(n){
            if (_seoDLDone) return;
            if (typeof Chart === 'undefined' || typeof ChartDataLabels === 'undefined') {
                if ((n||0) > 150) return; // ~15s — give up quietly, charts still work without labels
                setTimeout(function(){ seoEnableChartDataLabels((n||0)+1); }, 100);
                return;
            }
            _seoDLDone = true;
            Chart.register(ChartDataLabels);

            function isArcType(t){ return t === 'pie' || t === 'doughnut' || t === 'polarArea'; }

            Chart.defaults.set('plugins.datalabels', {
                display: 'auto', // auto-hides labels that would overlap/clutter
                clamp: true,
                anchor: function(ctx){ return isArcType(ctx.chart.config.type) ? 'center' : 'end'; },
                align: function(ctx){
                    if (isArcType(ctx.chart.config.type)) return 'center';
                    return (ctx.chart.options.indexAxis === 'y') ? 'right' : 'top';
                },
                offset: 4,
                font: { size: 10, weight: '600', family: "'Outfit',sans-serif" },
                color: function(ctx){
                    if (isArcType(ctx.chart.config.type)) return '#fff';
                    var ds = ctx.dataset || {};
                    return ds.borderColor || ds.backgroundColor || '#94a3b8';
                },
                textStrokeColor: function(ctx){ return isArcType(ctx.chart.config.type) ? 'rgba(0,0,0,.55)' : 'transparent'; },
                textStrokeWidth: function(ctx){ return isArcType(ctx.chart.config.type) ? 3 : 0; },
                formatter: function(value){
                    if (value === null || value === undefined || value === '') return '';
                    var num = Number(value);
                    if (isNaN(num)) return value;
                    return Number.isInteger(num) ? num.toLocaleString() : num.toFixed(1);
                }
            });

            // Charts built before the plugin finished loading need a nudge
            // to pick up labels without requiring a page refresh.
            if (Chart.instances) {
                Object.keys(Chart.instances).forEach(function(k){
                    try { Chart.instances[k].update(); } catch(e){}
                });
            }
        }
        seoEnableChartDataLabels(0);
    })();
    </script>

    <!-- Mobile top bar -->
    <div class="seo-cl-mobile-bar">
        <?php if ($logo || (isset($logo_dark) && $logo_dark)) : ?>
            <?php if ($logo) : ?>
                <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($brand); ?>"
                    class="seo-logo-light" style="height:36px;max-width:120px;object-fit:contain;<?php echo (isset($logo_dark) && $logo_dark) ? '' : ''; ?>">
            <?php endif; ?>
            <?php if (isset($logo_dark) && $logo_dark) : ?>
                <img src="<?php echo esc_url($logo_dark); ?>" alt="<?php echo esc_attr($brand); ?>"
                    class="seo-logo-dark" style="height:36px;max-width:120px;object-fit:contain;display:none;">
            <?php endif; ?>
        <?php else : ?>
            <span class="seo-cl-mobile-brand"><?php echo esc_html($brand); ?></span>
        <?php endif; ?>
        <button class="seo-cl-hamburger" id="seo-hamburger" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>
    <div class="seo-cl-sidebar-overlay" id="seo-sidebar-overlay"></div>

    <!-- ── SIDEBAR ──────────────────────────────────────────── -->
    <aside class="seo-cl-sidebar" id="seo-sidebar">

        <!-- Brand -->
        <div class="seo-cl-brand">
            <?php if ($logo || (isset($logo_dark) && $logo_dark)) : ?>
                <?php if ($logo) : ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($brand); ?>"
                        class="seo-cl-brand-logo seo-logo-light">
                <?php endif; ?>
                <?php if (isset($logo_dark) && $logo_dark) : ?>
                    <img src="<?php echo esc_url($logo_dark); ?>" alt="<?php echo esc_attr($brand); ?>"
                        class="seo-cl-brand-logo seo-logo-dark" style="display:none;">
                <?php endif; ?>
            <?php else : ?>
                <div>
                    <div class="seo-cl-brand-name"><?php echo esc_html($brand); ?></div>
                    <div class="seo-cl-brand-sub">SEO Dashboard</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <nav class="seo-cl-nav">

            <?php
            $nav_groups = [
                'ANALYTICS' => [
                    'overview'  => ['label'=>'Overview',        'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>'],
                    'analytics' => ['label'=>'Analytics',       'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
                    'sc'        => ['label'=>'Search Console',  'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'],
                    'service'   => ['label'=>'Service Pages',   'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
                    'blog'      => ['label'=>'Blog Posts',      'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'],
                ],
                'PRESENCE' => [
                    'gmb'       => ['label'=>'Google Business', 'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="10" r="3"/><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 13 8 13s8-7.75 8-13a8 8 0 0 0-8-8z"/></svg>'],
                    'technical' => ['label'=>'Technical Audit', 'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>'],
                    'backlinks' => ['label'=>'Backlinks',       'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'],
                ],
                'CRM' => [
                    'leads'     => ['label'=>'Leads',           'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
                ],
                'AI TOOLS' => [
                    'ai'        => ['label'=>'AI Assistant',    'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'],
                ],
                'MY ACCOUNT' => [
                    'account'   => ['label'=>'Account Settings', 'svg'=>'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'],
                ],
            ];
            foreach ($nav_groups as $group_label => $items) :
                $has_visible = false;
                foreach ($items as $key => $item) {
                    if ($show[$key] ?? false) { $has_visible = true; break; }
                }
                if (!$has_visible) continue;
            ?>
            <div class="seo-cl-nav-section"><?php echo $group_label; ?></div>
            <?php foreach ($items as $key => $cfg) :
                if (!($show[$key] ?? false)) continue;
                $is_active = ($key === $first_visible);
            ?>
            <button class="seo-cl-nav-btn<?php echo $is_active ? ' active' : ''; ?>" data-tab="<?php echo esc_attr($key); ?>">
                <span class="nav-icon"><?php echo $cfg['svg']; ?></span>
                <?php echo esc_html($cfg['label']); ?>
            </button>
            <?php endforeach; ?>
            <?php endforeach; ?>

            <?php if (!empty($assigned_reports_list)) : ?>
            <div class="seo-cl-nav-section seo-cl-nav-section--switch">SWITCH REPORT</div>
            <?php foreach ($assigned_reports_list as $ar) :
                $is_current = ($ar['id'] == $report['id']);
            ?>
            <a href="?report_id=<?php echo intval($ar['id']); ?>" class="seo-cl-nav-btn seo-cl-report-switch<?php echo $is_current ? ' seo-cl-report-switch--active' : ''; ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($ar['title']); ?></span>
                <?php if ($is_current) : ?><span class="seo-cl-report-now">NOW</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>

        </nav>

        <!-- User / logout -->
        <div class="seo-cl-sidebar-foot">
            <?php if ($user) : ?>
            <div class="seo-cl-user-chip">
                <?php
                $sidebar_avatar_id  = get_user_meta($user_id, '_seo_dash_avatar_id', true);
                $sidebar_avatar_url = $sidebar_avatar_id ? wp_get_attachment_url(intval($sidebar_avatar_id)) : '';
                ?>
                <div class="seo-cl-avatar" style="<?php echo $sidebar_avatar_url ? 'padding:0;overflow:hidden;' : ''; ?>">
                    <?php if ($sidebar_avatar_url) : ?>
                        <img src="<?php echo esc_url($sidebar_avatar_url); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="">
                    <?php else : ?>
                        <?php echo esc_html($avatar_letter); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="seo-cl-user-name"><?php echo esc_html($user->display_name); ?></div>
                    <div class="seo-cl-user-role">SEO Client</div>
                </div>
            </div>
            <?php endif; ?>
            <a href="<?php echo esc_url($logout_url); ?>" class="seo-cl-logout-btn">🚪 Sign Out</a>
        </div>
    </aside>

    <!-- ── MAIN CONTENT ─────────────────────────────────────── -->
    <div class="seo-cl-main">

        <?php
        if (!function_exists('seo_fmt_num')) {
            function seo_fmt_num($n) {
                if ($n >= 1000000) return round($n/1000000,1).'M';
                if ($n >= 1000)    return round($n/1000,1).'k';
                return number_format($n);
            }
        }
        $ov_opt    = get_option("seo_dash_overview_overall_{$rid}", []);
        $date_from = $ov_opt['from'] ?? '';
        $date_to   = $ov_opt['to']   ?? '';
        $user_name = $user ? $user->display_name : '';
        ?>

        <!-- Shared Hero Header for all tabs -->
        <div class="seo-ov3-hero<?php echo $first_visible!=='overview'?' seo-ov3-hero--compact':''; ?>" id="seo-main-topbar">
            <div class="seo-ov3-blob seo-ov3-blob1"></div>
            <div class="seo-ov3-blob seo-ov3-blob2"></div>
            <div class="seo-ov3-blob seo-ov3-blob3"></div>
            <div class="seo-ov3-hero-bleed"></div>

            <div style="position:absolute; top:0; right:20px; z-index:999999; display:flex; gap:10px; align-items:flex-start; padding-top:20px; padding-bottom:20px;">
                <?php if ( !empty($assigned_reports_list) ) : ?>
                    <?php
                    $current_title = '';
                    foreach ($assigned_reports_list as $ar) {
                        if ($ar['id'] == $report['id']) { $current_title = $ar['title']; break; }
                    }
                    ?>
                    <div class="seo-rpt-drop" id="seo-rpt-drop">
                        <button class="seo-rpt-drop-btn" id="seo-rpt-drop-btn" type="button" onclick="(function(){var d=document.getElementById('seo-rpt-drop');d.classList.toggle('open');})()">
                            <span>📋 <?php echo esc_html($current_title); ?></span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div class="seo-rpt-drop-menu">
                            <?php foreach ($assigned_reports_list as $ar) : ?>
                            <a class="seo-rpt-drop-item<?php echo ($ar['id'] == $report['id']) ? ' active' : ''; ?>" href="?report_id=<?php echo intval($ar['id']); ?>">
                                📋 <?php echo esc_html($ar['title']); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <script>
                    document.addEventListener('click', function(e) {
                        var d = document.getElementById('seo-rpt-drop');
                        if (d && !d.contains(e.target)) d.classList.remove('open');
                    });
                    </script>
                <?php else : ?>
                    <span class="seo-cl-report-badge">📋 <?php echo esc_html($report['title'] ?? 'Report'); ?></span>
                <?php endif; ?>
                <button id="seo-cl-theme-btn" type="button" title="Toggle dark/light mode" class="seo-theme-btn" onclick="window.seoDashToggleTheme && window.seoDashToggleTheme();">
                    <svg class="seo-theme-icon-dark" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="seo-theme-icon-light" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </button>
                <script>
                /* ── Dark / light mode toggle — NUCLEAR, SELF-CONTAINED ──────────────
                   This used to live in a big shared <script> tag near the bottom of
                   the page, where the one-time init call also rebuilt the Analytics/
                   SC/Service/Blog charts (seoUpdateAnaChart etc) BEFORE their AJAX
                   data had loaded. If any of those threw (very likely this early),
                   the uncaught error halted the REST of that <script> tag — including
                   the click listener that was supposed to be attached right after —
                   so the toggle button silently did nothing.
                   This block is fully independent: every step is wrapped so nothing
                   here can throw, it defines window.seoDashToggleTheme immediately
                   (the button's onclick calls it directly, no addEventListener race),
                   and it recolors Chart.js instances generically via Chart.instances
                   rather than calling per-tab rebuild functions. */
                (function(){
                    var KEY = 'seo_dash_client_theme';

                    function syncIcons(isDark){
                        try {
                            document.querySelectorAll('.seo-theme-icon-dark').forEach(function(el){ el.style.display = isDark ? 'none' : ''; });
                            document.querySelectorAll('.seo-theme-icon-light').forEach(function(el){ el.style.display = isDark ? '' : 'none'; });
                        } catch(e) {}
                        // Swap logos based on dark/light mode
                        try {
                            var hasLight = document.querySelectorAll('.seo-logo-light').length > 0;
                            var hasDark  = document.querySelectorAll('.seo-logo-dark').length > 0;
                            if (hasLight || hasDark) {
                                // If both logos exist, swap them
                                if (hasLight && hasDark) {
                                    document.querySelectorAll('.seo-logo-light').forEach(function(el){ el.style.display = isDark ? 'none' : ''; });
                                    document.querySelectorAll('.seo-logo-dark').forEach(function(el){ el.style.display = isDark ? '' : 'none'; });
                                }
                                // If only dark logo exists, always show it
                                // If only light logo exists, always show it
                                // (single logo shows regardless of mode — existing behaviour)
                            }
                        } catch(e) {}
                    }

                    function recolorCharts(isDark){
                        try {
                            if (typeof Chart === 'undefined' || !Chart.instances) return;
                            var gridC = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
                            var txtC  = isDark ? 'rgba(255,255,255,.55)' : '#64748b';
                            var tipBg = isDark ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.97)';
                            var tipTi = isDark ? '#e2e8f0' : '#1e293b';
                            var tipBo = isDark ? '#94a3b8' : '#475569';
                            var tipBr = isDark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.08)';
                            Object.keys(Chart.instances).forEach(function(key){
                                var chart = Chart.instances[key];
                                try {
                                    var sc = chart.options && chart.options.scales;
                                    if (sc) {
                                        ['x','y','r'].forEach(function(ax){
                                            if (!sc[ax]) return;
                                            if (sc[ax].grid) sc[ax].grid.color = gridC;
                                            if (sc[ax].ticks) sc[ax].ticks.color = txtC;
                                            if (sc[ax].pointLabels) sc[ax].pointLabels.color = txtC;
                                        });
                                    }
                                    var pl = chart.options && chart.options.plugins;
                                    if (pl) {
                                        if (pl.legend && pl.legend.labels) pl.legend.labels.color = txtC;
                                        if (pl.tooltip) {
                                            pl.tooltip.backgroundColor = tipBg;
                                            pl.tooltip.titleColor = tipTi;
                                            pl.tooltip.bodyColor = tipBo;
                                            pl.tooltip.borderColor = tipBr;
                                        }
                                    }
                                    chart.update();
                                } catch(e) {}
                            });
                        } catch(e) {}
                    }

                    function applyTheme(theme){
                        try {
                            var app = document.getElementById('seo-client-app');
                            if (!app) return;
                            var isDark = theme === 'dark';
                            app.classList.toggle('seo-dark', isDark);
                            syncIcons(isDark);
                            try { localStorage.setItem(KEY, theme); } catch(e) {}
                            recolorCharts(isDark);
                        } catch(e) {}
                    }

                    window.seoDashToggleTheme = function(){
                        try {
                            var app = document.getElementById('seo-client-app');
                            var isDark = !!(app && app.classList.contains('seo-dark'));
                            applyTheme(isDark ? 'light' : 'dark');
                        } catch(e) {}
                    };

                    // Apply the saved preference immediately (before any chart is
                    // built later in the page), so isDark() checks inside chart
                    // -building code see the correct state from the start.
                    var saved = 'light';
                    try { saved = localStorage.getItem(KEY) || 'light'; } catch(e) {}
                    applyTheme(saved);
                })();
                </script>
            </div>

            <div class="seo-ov3-body">
                <div class="seo-ov3-left">
                    <div class="seo-ov3-greeting" id="seo-hero-greeting" style="<?php echo $first_visible!=='overview'?'display:none;':''; ?>">
                        <span class="seo-ov3-fire">🔥</span>
                        <span class="seo-ov3-hello">HELLO,</span>
                        <?php if ($user_name) : ?>
                        <span class="seo-ov3-name-pill"><?php echo esc_html(strtoupper($user_name)); ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="seo-ov3-title" id="seo-topbar-title">
                        <?php 
                        $_ov_hdr = get_option("seo_dash_hero_kpis_{$rid}", []);
                        $custom_tab_titles = [
                            'overview'  => !empty($_ov_hdr['title']) ? $_ov_hdr['title'] : 'Your SEO Performance Dashboard',
                            'analytics' => 'Google Analytics Performance',
                            'sc'        => 'Google Search Performance',
                            'service'   => 'Service Pages Performance',
                            'blog'      => 'Blog Posts Performance',
                            'gmb'       => 'Google Business Profile Overview',
                            'technical' => 'Technical Audit Overview',
                            'backlinks' => 'Backlinks Overview',
                            'leads'     => 'Leads Management Overview',
                            'ai'        => 'AI Assistant',
                            'account'   => 'Account Settings',
                        ];
                        $custom_tab_subs = [
                            'overview'  => !empty($_ov_hdr['sub']) ? $_ov_hdr['sub'] : 'Real-time insights across traffic, rankings, backlinks & more',
                            'analytics' => 'Track your website traffic and user engagement',
                            'sc'        => 'Monitor your Google Search performance and indexing',
                            'service'   => 'Performance metrics for your core service pages',
                            'blog'      => 'Traffic and engagement data for your blog content',
                            'gmb'       => 'Insights for your Google Business Profile',
                            'technical' => 'Website health, errors, and technical issues',
                            'backlinks' => 'Monitor your inbound links and domain authority',
                            'leads'     => 'Track and manage your incoming leads',
                            'ai'        => 'Your smart SEO co-pilot — instant insights, recommendations & answers',
                            'account'   => 'Manage your profile, preferences and account settings',
                        ];

                        $default_hdr_tabs = ['analytics', 'sc', 'service', 'blog', 'gmb', 'technical', 'backlinks', 'leads'];
                        foreach ($default_hdr_tabs as $_dt) {
                            $_t_hdr = get_option("seo_dash_tab_hdr_{$_dt}_{$rid}", []);
                            if (!empty($_t_hdr['title'])) $custom_tab_titles[$_dt] = $_t_hdr['title'];
                            if (!empty($_t_hdr['sub']))   $custom_tab_subs[$_dt]   = $_t_hdr['sub'];
                        }

                        if ($first_visible === 'overview') {
                            echo esc_html($custom_tab_titles['overview']);
                        } else {
                            echo esc_html($nav_items[$first_visible]['icon'] ?? '') . ' ' . esc_html($custom_tab_titles[$first_visible] ?? $nav_items[$first_visible]['label'] ?? 'Dashboard');
                        }
                        ?>
                    </h2>
                    <p class="seo-ov3-sub" id="seo-hero-sub">
                        <?php 
                        echo esc_html($custom_tab_subs[$first_visible] ?? 'Real-time insights across traffic, rankings, backlinks & more');
                        ?>
                    </p>
                    <?php if ($date_from && $date_to) : ?>
                    <div class="seo-ov3-daterange" id="seo-overview-daterange" style="<?php echo $first_visible!=='overview'?'display:none;':''; ?>">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo esc_html($date_from); ?> – <?php echo esc_html($date_to); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php
                // ── Hero KPI Cards Config ────────────────────────────────────────────────
                $hero_kpi_opt = get_option("seo_dash_hero_kpis_{$rid}", []);
                $hero_kpi_defs = [
                    'overall'   => ['label' => 'OVERALL TRAFFIC',      'desc' => 'All-time visitors', 'val' => $kpi_overall_traffic > 0 ? seo_fmt_num($kpi_overall_traffic) : '—'],
                    'latest'    => ['label' => 'LATEST MONTH TRAFFIC', 'desc' => 'Most recent month', 'val' => $kpi_30d_traffic > 0 ? seo_fmt_num($kpi_30d_traffic) : '—'],
                    'backlinks' => ['label' => 'NEW BACKLINKS',         'desc' => 'All time',          'val' => $kpi_backlinks > 0 ? seo_fmt_num($kpi_backlinks) : '—'],
                ];
                $hero_kpi_merged = [];
                foreach ($hero_kpi_defs as $hk => $hd) {
                    $saved = is_array($hero_kpi_opt[$hk] ?? null) ? $hero_kpi_opt[$hk] : [];
                    $hero_kpi_merged[$hk] = [
                        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
                        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $hd['label'],
                        'desc'  => ($saved['desc'] ?? '') !== '' ? $saved['desc'] : $hd['desc'],
                        'val'   => $hd['val'],
                    ];
                }
                $visible_hero_kpis = array_filter($hero_kpi_merged, function($v) { return $v['show']; });
                ?>

                <?php if (!empty($visible_hero_kpis)) : ?>
                <div class="seo-ov3-stats" id="seo-hero-stats" style="<?php echo $first_visible!=='overview'?'display:none;':'display:flex;'; ?>align-items:stretch;gap:14px;flex-wrap:wrap;">
                    <?php foreach ($visible_hero_kpis as $hk => $hv) : ?>
                    <div class="seo-ov3-stat" style="flex:1 1 0%;min-width:140px;">
                        <div class="seo-ov3-stat-label"><?php echo esc_html($hv['label']); ?></div>
                        <div class="seo-ov3-stat-val"><?php echo esc_html($hv['val']); ?></div>
                        <div class="seo-ov3-stat-desc"><?php echo esc_html($hv['desc']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab panels -->
        <div class="seo-cl-content">

            <!-- ── Overview ──────────────────────────────── -->
            <?php if ($show['overview']) : ?>
            <div class="seo-cl-panel-tab" data-tab="overview" <?php echo $first_visible!=='overview'?'style="display:none;"':''; ?>>



                <?php

                // ── 5 KPI cards data ──────────────────────────────────────────
                // NOTE: seo_dash_count_ga_type_urls() and the $seo_dash_blog_types /
                // $seo_dash_service_types arrays are now defined once at the top of
                // this template (outside every $show[...] guard) so they remain
                // available even when the Overview tab is hidden. See top of file.

                // Blog Posts — count from data_ga (same source the Blog tab table uses)
                $blog_map = get_option("seo_dash_custom_pages_{$rid}_blog", []);
                $kpi_blog_posts = seo_dash_count_ga_type_urls($rid, $seo_dash_blog_types);
                // Also count any custom_pages blog entries not yet in the type_map
                if (is_array($blog_map)) {
                    $_ga_type_map_ov = get_option("seo_dash_sitemap_types_{$rid}_ga", []);
                    foreach ($blog_map as $_bp) {
                        if (empty($_bp['url']) || !empty($_bp['trashed'])) continue;
                        if (!isset($_ga_type_map_ov[$_bp['url']])) $kpi_blog_posts++;
                    }
                    unset($_ga_type_map_ov, $_bp);
                }

                // Mobile & Desktop Speed
                $tech_speed        = get_option("seo_dash_tech_speed_{$rid}", []);
                $kpi_mobile_speed  = intval($tech_speed['mobile']  ?? 0);
                $kpi_desktop_speed = intval($tech_speed['desktop'] ?? 0);

                // Ranked Pages (show_on_overview)
                $svc_map = get_option("seo_dash_custom_pages_{$rid}_service", []);
                $kpi_ranked = 0;
                foreach (array_merge(is_array($svc_map)?$svc_map:[], is_array($blog_map)?$blog_map:[]) as $p) {
                    if (!empty($p['show_on_overview']) && empty($p['trashed'])) $kpi_ranked++;
                }

                // Total Leads
                global $wpdb;
                $kpi_leads = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_leads . " WHERE report_id = %d AND trashed = 0",
                    $rid
                ));
                // ─────────────────────────────────────────────────────────────
                ?>

                <?php
                // ── KPI card definitions: defaults merged with admin overrides ──
                $ov_kpi_defaults = [
                    'blog'    => ['icon'=>'📝','color'=>'#6366f1','label'=>'BLOG POSTS',    'desc'=>'Total published',   'val'=>$kpi_blog_posts > 0 ? $kpi_blog_posts : '—'],
                    'mobile'  => ['icon'=>'📱','color'=>'#f59e0b','label'=>'MOBILE SPEED',  'desc'=>'PageSpeed score',    'val'=>$kpi_mobile_speed > 0 ? $kpi_mobile_speed.'/100' : '—'],
                    'desktop' => ['icon'=>'🖥️','color'=>'#06b6d4','label'=>'DESKTOP SPEED', 'desc'=>'PageSpeed score',    'val'=>$kpi_desktop_speed > 0 ? $kpi_desktop_speed.'/100' : '—'],
                    'ranked'  => ['icon'=>'📊','color'=>'#10b981','label'=>'RANKED PAGES',  'desc'=>'Pages indexed',      'val'=>$kpi_ranked > 0 ? $kpi_ranked : '—'],
                    'leads'   => ['icon'=>'🎯','color'=>'#8b5cf6','label'=>'TOTAL LEADS',   'desc'=>'Enquiries received', 'val'=>$kpi_leads > 0 ? $kpi_leads : '—'],
                ];
                $ov_kpi_merged = [];
                foreach ($ov_kpi_defaults as $kk => $kv) {
                    $saved = is_array($ov_kpi_cfg[$kk] ?? null) ? $ov_kpi_cfg[$kk] : [];
                    $ov_kpi_merged[$kk] = [
                        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
                        'icon'  => $kv['icon'],
                        'color' => $kv['color'],
                        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $kv['label'],
                        'desc'  => ($saved['desc']  ?? '') !== '' ? $saved['desc']  : $kv['desc'],
                        'val'   => $kv['val'],
                    ];
                }
                $any_kpi = false;
                foreach ($ov_kpi_merged as $kv) { if ($kv['show']) { $any_kpi = true; break; } }
                ?>

                <?php if (!$_seo_free_overview && $ov_show['kpis'] && $any_kpi) : ?>
                <!-- ── KPI CARDS ── -->
                <div class="seo-ov3-kpi-row">
                <?php foreach ($ov_kpi_merged as $kv) : if (!$kv['show']) continue; ?>
                    <div class="seo-ov3-kpi" style="--kc:<?php echo esc_attr($kv['color']); ?>;">
                        <div class="seo-ov3-kpi-icon" style="--kc:<?php echo esc_attr($kv['color']); ?>;"><?php echo $kv['icon']; ?></div>
                        <div class="seo-ov3-kpi-label"><?php echo esc_html($kv['label']); ?></div>
                        <div class="seo-ov3-kpi-val"><?php echo esc_html($kv['val']); ?></div>
                        <div class="seo-ov3-kpi-desc"><?php echo esc_html($kv['desc']); ?></div>
                        <div class="seo-ov3-kpi-bar" style="--kc:<?php echo esc_attr($kv['color']); ?>;"></div>
                    </div>
                <?php endforeach; ?>
                </div><!-- /.seo-ov3-kpi-row -->
                <?php endif; ?>

                <?php
                // ── Overview Charts Data & Admin Configuration ───────────────────
                $ov_charts_opt = get_option("seo_dash_charts_overview_{$rid}", []);
                $ov_charts_defs = [
                    'traffic'   => ['title' => 'Monthly Traffic Trend',   'type' => 'bar', 'color' => '#6366f1'],
                    'ranked'    => ['title' => 'Pages Ranked — Summary', 'type' => 'bar', 'color' => '#10b981'],
                    'backlinks' => ['title' => 'Backlinks by Month',     'type' => 'bar', 'color' => '#22d3ee'],
                ];
                $chart_cfg = [];
                foreach ($ov_charts_defs as $ck => $cd) {
                    $saved = is_array($ov_charts_opt[$ck] ?? null) ? $ov_charts_opt[$ck] : [];
                    $chart_cfg[$ck] = [
                        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
                        'title' => ($saved['title'] ?? '') !== '' ? $saved['title'] : $cd['title'],
                        'type'  => ($saved['type']  ?? '') !== '' ? $saved['type']  : $cd['type'],
                        'color' => $cd['color'],
                    ];
                }

                // 1) Monthly Traffic Trend — SUM(users) per month_key from period_type='30d'
                //    Matches the "Monthly Traffic" column in the admin Overview tab.
                $ga_monthly_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT month_key, SUM(users) AS total FROM " . SEO_Dash_Database::$data_ga .
                    " WHERE report_id=%d AND period_type='30d' AND trashed=0" .
                    " GROUP BY month_key ORDER BY month_key ASC LIMIT 12",
                    $rid
                ), ARRAY_A) ?: [];
                $chart_traffic_labels = [];
                $chart_traffic_data   = [];
                foreach ($ga_monthly_rows as $row) {
                    if (!$row['month_key']) continue;
                    $chart_traffic_labels[] = date_i18n('M Y', strtotime($row['month_key'].'-01'));
                    $chart_traffic_data[]   = (int)$row['total'];
                }
                if (empty($chart_traffic_labels)) {
                    $chart_traffic_labels[] = date_i18n('M Y'); $chart_traffic_data[] = 0;
                }

                // 2) Pages Ranked — Summary
                //    Mirrors the admin Overview tab: counts pages with show_on_overview=1
                //    grouped by their ranked_page value (Page 1 / Page 2 / Page 3 / Page 4 / Page 5+)
                $p1=$p2=$p3=$p4=$p5=0;
                foreach (['service','blog'] as $_map_type) {
                    $_map = get_option("seo_dash_custom_pages_{$rid}_{$_map_type}", []);
                    if (!is_array($_map)) continue;
                    foreach ($_map as $_p) {
                        if (empty($_p['show_on_overview']) || !empty($_p['trashed'])) continue;
                        $r = intval($_p['ranked_page'] ?? 0);
                        if ($r === 1) $p1++;
                        elseif ($r === 2) $p2++;
                        elseif ($r === 3) $p3++;
                        elseif ($r === 4) $p4++;
                        elseif ($r >= 5) $p5++;
                        else $p1++; // treat unset/0 as "ranked" - place in Page 1 bucket
                    }
                }
                $chart_ranked_labels = ['Page 1','Page 2','Page 3','Page 4','Page 5+'];
                $chart_ranked_data   = [$p1,$p2,$p3,$p4,$p5];

                // 3) Backlinks by Month — COUNT(*) per month_key from backlinks table
                $bk_monthly_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT month_key, COUNT(*) AS total FROM " . SEO_Dash_Database::$data_backlinks .
                    " WHERE report_id=%d AND trashed=0 GROUP BY month_key ORDER BY month_key ASC LIMIT 12",
                    $rid
                ), ARRAY_A) ?: [];
                $chart_bk_labels = [];
                $chart_bk_data   = [];
                foreach ($bk_monthly_rows as $row) {
                    if (!$row['month_key']) continue;
                    $chart_bk_labels[] = date_i18n('M Y', strtotime($row['month_key'].'-01'));
                    $chart_bk_data[]   = (int)$row['total'];
                }
                if (empty($chart_bk_labels)) {
                    $chart_bk_labels[] = date_i18n('M Y'); $chart_bk_data[] = 0;
                }
                ?>

                <?php
                $any_chart = $ov_show['charts'] && ( $chart_cfg['traffic']['show'] || $chart_cfg['ranked']['show'] || $chart_cfg['backlinks']['show'] );
                if ($any_chart) :
                ?>
                <?php if (!$_seo_free_overview) : ?>
                <div class="seo-ov3-charts-row" id="seo-overview-charts" style="display:flex;gap:20px;margin-bottom:20px;flex-wrap:wrap;">
                <?php if ($chart_cfg['traffic']['show']) : ?>
                    <div class="seo-ov3-chart-card" style="flex:1 1 300px;min-width:280px;margin-bottom:0;">
                        <div class="seo-ov3-chart-hd">
                            <span class="seo-ov3-chart-dot" style="background:<?php echo esc_attr($chart_cfg['traffic']['color']); ?>"></span>
                            <span class="seo-ov3-chart-title"><?php echo esc_html($chart_cfg['traffic']['title']); ?></span>
                        </div>
                        <div class="seo-ov3-chart-wrap">
                            <canvas id="seo-chart-traffic"
                                data-type="<?php echo esc_attr($chart_cfg['traffic']['type']); ?>"
                                data-color="<?php echo esc_attr($chart_cfg['traffic']['color']); ?>"
                                data-labels="<?php echo esc_attr(wp_json_encode($chart_traffic_labels)); ?>"
                                data-values="<?php echo esc_attr(wp_json_encode($chart_traffic_data)); ?>"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($chart_cfg['ranked']['show']) : ?>
                    <div class="seo-ov3-chart-card" style="flex:1 1 300px;min-width:280px;margin-bottom:0;">
                        <div class="seo-ov3-chart-hd">
                            <span class="seo-ov3-chart-dot" style="background:<?php echo esc_attr($chart_cfg['ranked']['color']); ?>"></span>
                            <span class="seo-ov3-chart-title"><?php echo esc_html($chart_cfg['ranked']['title']); ?></span>
                        </div>
                        <div class="seo-ov3-chart-wrap">
                            <canvas id="seo-chart-ranked"
                                data-type="<?php echo esc_attr($chart_cfg['ranked']['type']); ?>"
                                data-color="<?php echo esc_attr($chart_cfg['ranked']['color']); ?>"
                                data-labels="<?php echo esc_attr(wp_json_encode($chart_ranked_labels)); ?>"
                                data-values="<?php echo esc_attr(wp_json_encode($chart_ranked_data)); ?>"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($chart_cfg['backlinks']['show']) : ?>
                    <div class="seo-ov3-chart-card" style="flex:1 1 300px;min-width:280px;margin-bottom:0;">
                        <div class="seo-ov3-chart-hd">
                            <span class="seo-ov3-chart-dot" style="background:<?php echo esc_attr($chart_cfg['backlinks']['color']); ?>"></span>
                            <span class="seo-ov3-chart-title"><?php echo esc_html($chart_cfg['backlinks']['title']); ?></span>
                        </div>
                        <div class="seo-ov3-chart-wrap">
                            <canvas id="seo-chart-backlinks"
                                data-type="<?php echo esc_attr($chart_cfg['backlinks']['type']); ?>"
                                data-color="<?php echo esc_attr($chart_cfg['backlinks']['color']); ?>"
                                data-labels="<?php echo esc_attr(wp_json_encode($chart_bk_labels)); ?>"
                                data-values="<?php echo esc_attr(wp_json_encode($chart_bk_data)); ?>"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
                <script>
                (function(){
                    var IDS = ['seo-chart-traffic','seo-chart-ranked','seo-chart-backlinks'];
                    function isDark(){ return !!(document.getElementById('seo-client-app')&&document.getElementById('seo-client-app').classList.contains('seo-dark')); }
                    function gridC(){ return isDark()?'rgba(255,255,255,.07)':'rgba(0,0,0,.06)'; }
                    function txtC(){  return isDark()?'rgba(255,255,255,.55)':'#334155'; }
                    function h2r(h){ return parseInt(h.slice(1,3),16)+','+parseInt(h.slice(3,5),16)+','+parseInt(h.slice(5,7),16); }
                    function mkChart(id){
                        var el=document.getElementById(id); if(!el||el._chartInited) return;
                        el._chartInited=true;
                        var rawType=el.dataset.type||'bar';
                        var color=el.dataset.color||'#6366f1';
                        var labels=JSON.parse(el.dataset.labels||'[]');
                        var values=JSON.parse(el.dataset.values||'[]');

                        var isArea     = rawType === 'area';
                        var isHoriz    = rawType === 'horizontalBar';
                        var isStacked  = rawType === 'stackedBar';
                        var isGrouped  = rawType === 'groupedBar';
                        var isPie      = rawType === 'pie';
                        var isDoughnut = rawType === 'doughnut';
                        var isPolar    = rawType === 'polarArea';
                        var isRadar    = rawType === 'radar';

                        var isCircular = isPie || isDoughnut || isPolar;
                        var isRadial   = isCircular || isRadar;

                        var chartType = 'bar';
                        if (isArea || rawType === 'line') chartType = 'line';
                        else if (isPie) chartType = 'pie';
                        else if (isDoughnut) chartType = 'doughnut';
                        else if (isPolar) chartType = 'polarArea';
                        else if (isRadar) chartType = 'radar';

                        var rgb=h2r(color);
                        var palette = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6', '#f97316'];
                        var dc = (isCircular || isGrouped || isRadar) ? values.map(function(_, i){ return palette[i % palette.length]; }) : null;
                        var chart;
                        try {
                        chart = new Chart(el,{
                            type:chartType,
                            data:{labels:labels,datasets:[{
                                data:values,
                                backgroundColor:isRadial||isGrouped?dc:(chartType==='bar'?'rgba('+rgb+',.65)':'rgba('+rgb+',.15)'),
                                borderColor:isRadial||isGrouped?dc:color,
                                borderWidth:chartType==='bar'?0:2.5,
                                borderRadius:chartType==='bar'?8:0,
                                fill:isArea||chartType==='radar',tension:0.42,
                                pointRadius:chartType==='line'||isArea||chartType==='radar'?4:0,
                                pointHoverRadius:6,
                                pointBackgroundColor:color
                            }]},
                            options:{
                                indexAxis: isHoriz ? 'y' : 'x',
                                responsive:true,maintainAspectRatio:false,
                                animation:{duration:800,easing:'easeInOutQuart'},
                                plugins:{
                                    datalabels:{display:false},
                                    legend:{display:isRadial,position:isRadial?'right':'top',labels:{color:txtC(),font:{size:11,family:"'Outfit',sans-serif"}}},
                                    tooltip:{
                                        mode:'index',intersect:false,
                                        backgroundColor:isDark()?'rgba(15,23,42,.95)':'rgba(255,255,255,.97)',
                                        titleColor:isDark()?'#e2e8f0':'#1e293b',
                                        bodyColor:isDark()?'#94a3b8':'#475569',
                                        borderColor:isDark()?'rgba(255,255,255,.1)':'rgba(0,0,0,.08)',
                                        borderWidth:1,padding:10,cornerRadius:8
                                    }
                                },
                                scales:isRadial?(isRadar?{r:{ticks:{display:false,backdropColor:'transparent'},grid:{color:gridC()},pointLabels:{color:txtC(),font:{size:11,family:"'Outfit',sans-serif"}}}}:{}):{
                                    x:{stacked:isStacked,grid:{color:gridC(),drawBorder:false},ticks:{color:txtC(),font:{size:11,family:"'Outfit',sans-serif"}}},
                                    y:{stacked:isStacked,grid:{color:gridC(),drawBorder:false},ticks:{color:txtC(),font:{size:11,family:"'Outfit',sans-serif"}},beginAtZero:true}
                                }
                            },
                            plugins: [{
                                id: 'persistentTooltip',
                                afterInit: function(chart) {
                                    setTimeout(function(){
                                        if(!chart.data.datasets.length) return;
                                        var meta = chart.getDatasetMeta(0);
                                        if(!meta.data.length) return;
                                        var idx = meta.data.length - 1;
                                        chart.tooltip.setActiveElements([{datasetIndex: 0, index: idx}], {x: meta.data[idx].x, y: meta.data[idx].y});
                                        chart.setActiveElements([{datasetIndex: 0, index: idx}]);
                                        chart.update();
                                    }, 100);
                                },
                                afterEvent: function(chart, args) {
                                    var e = args.event;
                                    if(e.type === 'mouseout' || !args.inChartArea) {
                                        var meta = chart.getDatasetMeta(0);
                                        if(!meta.data.length) return;
                                        var idx = meta.data.length - 1;
                                        chart.tooltip.setActiveElements([{datasetIndex: 0, index: idx}], {x: meta.data[idx].x, y: meta.data[idx].y});
                                        chart.setActiveElements([{datasetIndex: 0, index: idx}]);
                                        chart.update();
                                    }
                                }
                            }]
                        });
                        } catch(e) {
                            console.error('Overview chart init failed for #'+id+' (type='+type+'):', e);
                            el._chartInited = false; // allow a retry if initAll() runs again
                            return;
                        }
                        // The container's final layout size isn't always settled at the
                        // moment the chart is created (flex/grid columns still resolving,
                        // or this tab briefly hidden) — for circular chart types especially
                        // (doughnut/radar/polarArea) that leaves Chart.js with a near-zero
                        // width and the chart renders tiny/squashed into a corner. Forcing
                        // a resize on the next frame, and again shortly after, fixes this
                        // reliably without affecting correctly-sized charts.
                        var doResize = function(){ try { chart.resize(); } catch(e) {} };
                        if (typeof requestAnimationFrame === 'function') requestAnimationFrame(doResize);
                        setTimeout(doResize, 300);
                    }
                    function initAll(){
                        IDS.forEach(function(id){
                            try { mkChart(id); } catch(e) { console.error('initAll() error for #'+id, e); }
                        });
                    }
                    /* Poll until Chart.js is available (it loads in wp_footer after this inline script) */
                    function waitChart(n){
                        if(typeof Chart!=='undefined'){ initAll(); return; }
                        if((n||0)>150) return; // give up after 15 seconds
                        setTimeout(function(){ waitChart((n||0)+1); }, 100);
                    }
                    waitChart(0);
                    // Also re-run once after the window fully loads (images, fonts, etc.)
                    // in case layout shifted after the initial chart creation.
                    if (typeof window.addEventListener === 'function') {
                        window.addEventListener('load', function(){
                            setTimeout(function(){
                                if (typeof Chart === 'undefined') return;
                                Object.keys(Chart.instances || {}).forEach(function(key){
                                    try { Chart.instances[key].resize(); } catch(e) {}
                                });
                            }, 200);
                        });
                    }
                })();
                </script>
                <?php endif; ?>
                <?php endif; // any_chart ?>

                <?php
                // ── RANKED PAGES SUMMARY TABLE ──────────────────────────────
                $rp_all = [];
                $rp_counts = ['all'=>0, 'p1'=>0, 'p2'=>0, 'p3'=>0, 'ai'=>0];
                
                foreach (['service','blog'] as $_map_type) {
                    $_map = get_option("seo_dash_custom_pages_{$rid}_{$_map_type}", []);
                    if (!is_array($_map)) continue;
                    foreach ($_map as $_p) {
                        if (empty($_p['show_on_overview']) || !empty($_p['trashed'])) continue;
                        $r_raw = $_p['ranked_page'] ?? '';
                        $r = intval($r_raw);
                        $rank_label = 'Page 1';
                        $rank_class = 'p1';
                        $is_ai = !empty($_p['ai_overview']);
                        
                        if ($r_raw === 'ai') {
                            $r = 'ai';
                            $rank_label = 'AI Overview';
                            $rank_class = 'ai';
                            $is_ai = true;
                        } elseif ($r === 1 || $r === 0) {
                            $r = 1;
                            $rank_label = 'Page 1';
                            $rank_class = 'p1';
                        } elseif ($r === 2) {
                            $rank_label = 'Page 2';
                            $rank_class = 'p2';
                        } else {
                            $r = 3;
                            $rank_label = 'Page 3+';
                            $rank_class = 'p3';
                        }
                        
                        $rp_all[] = [
                            'title'      => $_p['title'] ?? '',
                            'url'        => $_p['url'] ?? '',
                            'rank'       => $r,
                            'rank_label' => $rank_label,
                            'rank_class' => $rank_class,
                            'is_ai'      => $is_ai
                        ];
                        
                        $rp_counts['all']++;
                        if ($rank_class === 'p1') $rp_counts['p1']++;
                        elseif ($rank_class === 'p2') $rp_counts['p2']++;
                        elseif ($rank_class === 'p3') $rp_counts['p3']++;
                        
                        if ($is_ai) $rp_counts['ai']++;
                    }
                }

                usort($rp_all, function($a, $b) {
                    $weights = ['ai' => 4, 1 => 1, 2 => 2, 3 => 3];
                    $wa = $weights[$a['rank']] ?? 1;
                    $wb = $weights[$b['rank']] ?? 1;
                    if ($wa === $wb) return strcasecmp($a['title'], $b['title']);
                    return $wa <=> $wb;
                });
                ?>
                <style>
                .seo-rp-toggle { display:flex;align-items:center;padding:4px 10px;border-radius:8px;cursor:pointer;transition:all 0.2s; }
                .seo-rp-toggle:hover { background:var(--cc-border); }
                .seo-rp-toggle.active { background:var(--cc-primary); color:#fff !important; }
                .seo-rp-toggle span { margin-left:6px;padding:2px 8px;border-radius:100px;font-size:11px;transition:all 0.2s; }
                .seo-rp-toggle.active span { background:#fff !important; color:var(--cc-primary) !important; }
                </style>
                <script>
                function seoRpFilterToggle(el) {
                    var container = el.parentElement;
                    var toggles = container.querySelectorAll('.seo-rp-toggle');
                    toggles.forEach(function(t) { t.classList.remove('active'); });
                    el.classList.add('active');
                    
                    var filter = el.getAttribute('data-filter');
                    var tbody = document.getElementById('seo-rp-tbody');
                    var rows = tbody.querySelectorAll('tr.seo-rp-row');
                    rows.forEach(function(r) {
                        if (filter === 'all') r.style.display = '';
                        else if (filter === 'ai') r.style.display = (r.getAttribute('data-ai') === '1') ? '' : 'none';
                        else r.style.display = (r.getAttribute('data-rank') === filter || (filter==='p1' && r.getAttribute('data-rank')==='ai')) ? '' : 'none';
                    });
                }
                </script>
                <?php
                $ov_tbl_defaults = ['num' => true, 'title' => true, 'url' => true, 'rank' => true];
                $ov_table_show = array_merge($ov_tbl_defaults, is_array($ov_table_cfg) ? $ov_table_cfg : []);
                $ov_tbl_colspan = max(1, count(array_filter($ov_table_show)));
                ?>
                <?php if (!$_seo_free_overview && $ov_show['table']) : ?>
                <div class="seo-cl-panel" style="margin-top:20px;">
                    <div class="seo-cl-panel-hd" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;flex-wrap:wrap;gap:12px;">
                        <h3 style="display:flex;align-items:center;gap:8px;font-size:16px;font-weight:700;margin:0;">
                            📊 Ranked Pages
                        </h3>
                        <div class="seo-rp-filters" style="display:flex;flex-wrap:wrap;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;padding:4px;font-size:12px;font-weight:600;color:var(--cc-text);gap:4px;">
                            <div class="seo-rp-toggle active" data-filter="all" onclick="seoRpFilterToggle(this)">
                                All <span style="background:var(--cc-border);color:var(--cc-text);"><?php echo $rp_counts['all']; ?></span>
                            </div>
                            <div class="seo-rp-toggle" data-filter="p1" onclick="seoRpFilterToggle(this)">
                                Page 1 <span style="background:rgba(99,102,241,.1);color:#6366f1;"><?php echo $rp_counts['p1']; ?></span>
                            </div>
                            <div class="seo-rp-toggle" data-filter="p2" onclick="seoRpFilterToggle(this)">
                                Page 2 <span style="background:rgba(99,102,241,.1);color:#6366f1;"><?php echo $rp_counts['p2']; ?></span>
                            </div>
                            <div class="seo-rp-toggle" data-filter="p3" onclick="seoRpFilterToggle(this)">
                                Page 3+ <span style="background:rgba(234,179,8,.1);color:#eab308;"><?php echo $rp_counts['p3']; ?></span>
                            </div>
                            <div class="seo-rp-toggle" data-filter="ai" onclick="seoRpFilterToggle(this)">
                                ✨ AI Overview <span style="background:rgba(236,72,153,.1);color:#ec4899;"><?php echo $rp_counts['ai']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="seo-cl-panel-body" style="padding:0;overflow-x:auto;max-height:400px;overflow-y:auto;">
                        <table style="width:100%;text-align:left;border-collapse:collapse;min-width:600px;position:relative;">
                            <thead style="position:sticky;top:0;z-index:2;background:var(--cc-surf);box-shadow:0 1px 0 var(--cc-border);">
                                <tr>
                                    <?php if ($ov_table_show['num'])   : ?><th style="padding:12px 20px;font-size:11px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;width:40px;">#</th><?php endif; ?>
                                    <?php if ($ov_table_show['title']) : ?><th style="padding:12px 20px;font-size:11px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;">PAGE TITLE</th><?php endif; ?>
                                    <?php if ($ov_table_show['url'])   : ?><th style="padding:12px 20px;font-size:11px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;text-align:right;">URL</th><?php endif; ?>
                                    <?php if ($ov_table_show['rank'])  : ?><th style="padding:12px 20px;font-size:11px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;text-align:right;">RANK</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="seo-rp-tbody">
                                <?php if (empty($rp_all)) : ?>
                                <tr class="seo-rp-row" data-rank="all" data-ai="0"><td colspan="<?php echo $ov_tbl_colspan; ?>" style="text-align:center;padding:24px;color:var(--cc-muted);">No ranked pages found.</td></tr>
                                <?php else : $i = 1; foreach ($rp_all as $rp) : ?>
                                <tr class="seo-rp-row" data-rank="<?php echo esc_attr($rp['rank_class']); ?>" data-ai="<?php echo $rp['is_ai'] ? '1' : '0'; ?>" style="border-bottom:1px solid var(--cc-border);">
                                    <?php if ($ov_table_show['num']) : ?>
                                    <td style="padding:14px 20px;font-size:13px;color:var(--cc-muted);"><?php echo $i++; ?></td>
                                    <?php endif; ?>
                                    <?php if ($ov_table_show['title']) : ?>
                                    <td style="padding:14px 20px;font-size:13px;font-weight:600;color:var(--cc-text);">
                                        <?php echo esc_html($rp['title']); ?>
                                        <?php if($rp['is_ai']): ?>
                                        <span style="display:inline-block;margin-left:8px;font-size:10px;background:rgba(236,72,153,.1);color:#ec4899;padding:2px 6px;border-radius:4px;font-weight:700;">✨ AI</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($ov_table_show['url']) : ?>
                                    <td style="padding:14px 20px;text-align:right;">
                                        <?php if(!empty($rp['url'])): ?>
                                        <a href="<?php echo esc_url($rp['url']); ?>" target="_blank" style="font-size:13px;color:#6366f1;text-decoration:none;font-weight:500;">View ↗</a>
                                        <?php else: ?>
                                        <span style="font-size:13px;color:var(--cc-muted);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($ov_table_show['rank']) : ?>
                                    <td style="padding:14px 20px;text-align:right;">
                                        <span style="display:inline-block;padding:4px 12px;background:#6366f1;color:#fff;border-radius:100px;font-size:12px;font-weight:700;"><?php echo esc_html($rp['rank_label']); ?></span>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="padding:14px 20px;font-size:12px;color:var(--cc-muted);border-top:1px solid var(--cc-border);">
                        <?php echo $rp_counts['all']; ?> total entries &middot; <?php echo $rp_counts['p1']; ?> on Page 1 &middot; <?php echo $rp_counts['p2']; ?> on Page 2 &middot; <?php echo $rp_counts['ai']; ?> AI Overview
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // ── SCREENSHOTS ─────────────────────────────────────────────
                $meta = $report['meta'] ?? [];
                $sc_monthly = $meta['sc_monthly'] ?? '';
                $sc_overall = $meta['sc_overall'] ?? '';
                $ga_monthly = $meta['ga_monthly'] ?? '';
                $ga_overall = $meta['ga_overall'] ?? '';

                $ov_ss_defaults = [
                    'sc_monthly' => ['label' => 'Search Console — Monthly', 'url' => $sc_monthly, 'dot' => '#6366f1', 'icon' => '📊'],
                    'sc_overall' => ['label' => 'Search Console — Overall', 'url' => $sc_overall, 'dot' => '#22d3ee', 'icon' => '🔍'],
                    'ga_monthly' => ['label' => 'Analytics — Monthly',      'url' => $ga_monthly, 'dot' => '#10b981', 'icon' => '📈'],
                    'ga_overall' => ['label' => 'Analytics — Overall',      'url' => $ga_overall, 'dot' => '#eab308', 'icon' => '📉'],
                ];
                $ss_arr = [];
                foreach ($ov_ss_defaults as $sk => $sv) {
                    $saved = is_array($ov_ss_cfg[$sk] ?? null) ? $ov_ss_cfg[$sk] : [];
                    $ss_show = isset($saved['show']) ? (bool)$saved['show'] : true;
                    if (!$ss_show) continue;
                    $ss_arr[] = [
                        'title' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $sv['label'],
                        'url'   => $sv['url'],
                        'dot'   => $sv['dot'],
                        'icon'  => $sv['icon'],
                    ];
                }
                $ss_count = 0;
                foreach($ss_arr as $s) { if($s['url']) $ss_count++; }
                
                if (!$_seo_free_overview && $ov_show['screenshots'] && $ss_count > 0) :
                ?>
                <div class="seo-cl-panel" style="margin-top:20px;">
                    <div class="seo-cl-panel-hd" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;">
                        <h3 style="display:flex;align-items:center;gap:8px;font-size:16px;font-weight:700;margin:0;">
                            📸 Search Console & Analytics Screenshots
                        </h3>
                        <div style="font-size:12px;color:var(--cc-muted);">
                            <?php echo $ss_count; ?> screenshot<?php echo $ss_count>1?'s':''; ?> &mdash; click to view full size
                        </div>
                    </div>
                    <div class="seo-cl-panel-body" style="padding:20px;background:var(--cc-surf2);">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;">
                            <?php foreach ($ss_arr as $s) : if(!$s['url']) continue; ?>
                            <div class="seo-ss-card" onclick="seoDashOpenSSModal('<?php echo esc_js($s['title']); ?>', '<?php echo esc_url($s['url']); ?>')" style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;overflow:hidden;cursor:pointer;transition:all 0.2s;display:flex;flex-direction:column;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                                <div style="height:120px;background-image:url('<?php echo esc_url($s['url']); ?>');background-size:cover;background-position:top center;border-bottom:1px solid var(--cc-border);position:relative;">
                                    <div class="seo-ss-overlay" style="position:absolute;inset:0;background:rgba(0,0,0,0);transition:background 0.2s;"></div>
                                </div>
                                <div style="padding:12px;display:flex;justify-content:space-between;align-items:center;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="font-size:20px;line-height:1;"><?php echo $s['icon']; ?></div>
                                        <div>
                                            <div style="font-size:12px;font-weight:700;color:var(--cc-text);margin-bottom:2px;"><?php echo esc_html($s['title']); ?></div>
                                            <div style="font-size:11px;color:var(--cc-muted);">Click to view</div>
                                        </div>
                                    </div>
                                    <div style="width:10px;height:10px;border-radius:50%;background:<?php echo $s['dot']; ?>;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div id="seo-ss-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.85);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);">
                    <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;width:100%;max-width:1000px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);animation: seoModalPop 0.2s ease-out;">
                        <div style="padding:16px 20px;border-bottom:1px solid var(--cc-border);display:flex;justify-content:space-between;align-items:center;background:#1e293b;">
                            <h3 id="seo-ss-modal-title" style="margin:0;font-size:16px;font-weight:700;color:#f8fafc;">Screenshot</h3>
                            <div style="display:flex;gap:12px;align-items:center;">
                                <a href="#" id="seo-ss-modal-link" target="_blank" style="padding:6px 12px;background:transparent;border:1px solid rgba(255,255,255,0.2);border-radius:6px;font-size:13px;color:#f8fafc;text-decoration:none;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">Open full size ↗</a>
                                <button onclick="document.getElementById('seo-ss-modal').style.display='none'" style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.1);border:none;color:#f8fafc;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">✕</button>
                            </div>
                        </div>
                        <div style="flex:1;overflow:auto;padding:20px;background:var(--cc-surf);display:flex;justify-content:center;">
                            <img id="seo-ss-modal-img" src="" style="max-width:100%;height:auto;border-radius:8px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);display:block;">
                        </div>
                    </div>
                </div>
                <script>
                function seoDashOpenSSModal(title, url) {
                    var modal = document.getElementById('seo-ss-modal');
                    // .seo-cl-content uses overflow-x:clip, which establishes a new
                    // containing block for position:fixed descendants in modern
                    // browsers — so the modal would render squeezed into the content
                    // area (next to/under the sidebar) instead of covering the full
                    // viewport. Reparenting to <body> guarantees true viewport-relative
                    // fixed positioning and proper centering.
                    if (modal && modal.parentElement !== document.body) {
                        document.body.appendChild(modal);
                    }
                    document.getElementById('seo-ss-modal-title').innerText = title;
                    document.getElementById('seo-ss-modal-link').href = url;
                    document.getElementById('seo-ss-modal-img').src = url;
                    document.getElementById('seo-ss-modal').style.display = 'flex';
                }
                </script>
                <style>
                .seo-ss-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important; border-color: var(--cc-primary) !important; }
                .seo-dark .seo-ss-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important; }
                .seo-ss-card:hover .seo-ss-overlay { background: rgba(0,0,0,0.2) !important; }
                @keyframes seoModalPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
                </style>
                <?php endif; ?>
                <?php if (!$_seo_free_overview && $ov_show['summary']) : ?>
                <?php if ($summary) : ?>
                <div class="seo-cl-panel" style="margin-top:20px;">
                    <div class="seo-cl-panel-hd"><h3>&#x1F4CD; Report Summary</h3></div>
                    <div class="seo-cl-panel-body">
                        <div class="seo-cl-summary"><?php echo $summary; ?></div>
                    </div>
                </div>
                <?php else : ?>
                <div class="seo-cl-panel" style="margin-top:20px;">
                    <div class="seo-cl-empty">
                        <div class="seo-cl-empty-icon">&#x1F4CD;</div>
                        <h4>Report Summary Coming Soon</h4>
                        <p>Your agency will add a summary for this reporting period soon.</p>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

            </div>
            <?php endif; ?>


            <!-- ── Analytics ─────────────────────────────── -->

            <?php if ($show['analytics']) : ?>
            <div class="seo-cl-panel-tab" data-tab="analytics" id="seo-cl-tab-analytics" <?php echo $first_visible!=='analytics'?'style="display:none;"':''; ?>>
                
                <!-- Timeperiod & Export Bar -->
                <div class="seo-cl-panel" style="margin-bottom: 20px;">
                    <div style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size:16px;">📅</span>
                                <button type="button" class="seo-tp-btn" data-period="7d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">7 Days</button>
                                <button type="button" class="seo-tp-btn" data-period="30d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">30 Days</button>
                                <button type="button" class="seo-tp-btn" data-period="90d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">90 Days</button>
                                <button type="button" class="seo-tp-btn active" data-period="overall" style="padding: 6px 14px; border: 1px solid var(--cc-primary); background: var(--cc-primary); color: #fff; border-radius: 100px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; min-width: 100px;">Overall</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards -->
                <?php
                // Analytics KPI card config (admin: Client Dashboard → Analytics → KPI Cards)
                $an_kpi_defs = [
                    'users'    => ['icon'=>'🔄','color'=>'#6366f1','label'=>'ACTIVE USERS','valid'=>'kpi-ana-users'],
                    'sessions' => ['icon'=>'📄','color'=>'#10b981','label'=>'SESSIONS',    'valid'=>'kpi-ana-sess'],
                    'views'    => ['icon'=>'📊','color'=>'#f59e0b','label'=>'PAGE VIEWS',   'valid'=>'kpi-ana-views'],
                    'urls'     => ['icon'=>'🔗','color'=>'#ec4899','label'=>'TOTAL URLS',   'valid'=>'kpi-ana-urls'],
                ];
                $an_kpi_show = [];
                foreach ($an_kpi_defs as $akk => $akv) {
                    $saved = is_array($an_kpi_cfg[$akk] ?? null) ? $an_kpi_cfg[$akk] : [];
                    $an_kpi_show[$akk] = [
                        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
                        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $akv['label'],
                    ];
                }
                ?>
                <?php if ($an_show['kpis']) : ?>
                <div class="seo-ov3-kpi-row">
                    <?php foreach ($an_kpi_defs as $akk => $akv) : if (!$an_kpi_show[$akk]['show']) continue; ?>
                    <div class="seo-ov3-kpi" style="--kc:<?php echo esc_attr($akv['color']); ?>;">
                        <div class="seo-ov3-kpi-icon" style="--kc:<?php echo esc_attr($akv['color']); ?>;"><?php echo $akv['icon']; ?></div>
                        <div class="seo-ov3-kpi-label"><?php echo esc_html($an_kpi_show[$akk]['label']); ?></div>
                        <div class="seo-ov3-kpi-val" id="<?php echo esc_attr($akv['valid']); ?>">--</div>
                        <div class="seo-ov3-kpi-desc seo-kpi-period-label">Overall</div>
                        <div class="seo-ov3-kpi-bar" style="--kc:<?php echo esc_attr($akv['color']); ?>;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                        <?php
                        $an_charts_saved = get_option("seo_dash_charts_analytics_{$rid}", []);
                        $an_chart_type  = seo_dash_get_chart_type_saved($an_charts_saved, 'traffic', 'area');
                        $an_chart_title = seo_dash_get_chart_title_saved($an_charts_saved, 'traffic', '📊 Traffic Chart');
                        $an_chart_show  = seo_dash_get_chart_show_saved($an_charts_saved, 'traffic', true);
                        ?>
                        <!-- Traffic Chart Section -->
                        <div class="seo-cl-panel" id="seo-ana-chart-container" style="margin-bottom: 20px; <?php echo ( empty($ac_opt['show']) || empty($an_show['chart']) || !$an_chart_show ) ? 'display:none;' : ''; ?>">
                            <div class="seo-cl-panel-hd" style="display:flex; justify-content:space-between; align-items:center;">
                                <h3 style="display:flex; align-items:center; gap:8px;"><?php echo esc_html($an_chart_title); ?></h3>
                                <div style="display:flex; gap:12px; align-items:center;">
                                    <div class="seo-chart-type-toggle-group" data-target="seo-ana-chart" style="display:inline-flex; background:var(--cc-surf2); border:1px solid var(--cc-border); border-radius:8px; padding:2px; gap:2px;">
                                        <button type="button" class="seo-ct-btn active" data-type="area" title="Curve Area (Image 1)" style="padding:4px 10px; border:none; background:var(--cc-primary); color:#fff; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">📈 Area</button>
                                        <button type="button" class="seo-ct-btn" data-type="bar" title="Vertical Bar" style="padding:4px 10px; border:none; background:transparent; color:var(--cc-muted); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">📊 Bar</button>
                                        <button type="button" class="seo-ct-btn" data-type="horizontalBar" title="Horizontal Bar" style="padding:4px 10px; border:none; background:transparent; color:var(--cc-muted); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">⏸️ Horiz</button>
                                    </div>
                                    <select id="seo-ana-chart-metric" style="padding:4px 8px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:12px; cursor:pointer;">
                                        <option value="sessions" <?php selected($ac_opt['metric'] ?? '', 'sessions'); ?>>Sessions</option>
                                        <option value="users" <?php selected($ac_opt['metric'] ?? '', 'users'); ?>>Active Users</option>
                                        <option value="views" <?php selected($ac_opt['metric'] ?? '', 'views'); ?>>Page Views</option>
                                    </select>
                                </div>
                            </div>
                            <div class="seo-cl-panel-body" style="padding:20px; height: 300px; position:relative;">
                                <canvas id="seo-ana-chart" data-color="<?php echo esc_attr($ac_opt['color'] ?? '#8b5cf6'); ?>" data-chart-type="<?php echo esc_attr($an_chart_type); ?>"></canvas>
                            </div>
                        </div>

                        <?php if ($an_show['table']) : ?>
                        <div class="seo-cl-panel" style="overflow:hidden;">
                            <div style="padding:16px 20px; border-bottom:1px solid var(--cc-border); display:flex; justify-content:space-between; align-items:center; background:var(--cc-surf);">
                            <div style="font-weight:700; color:var(--cc-text); font-size:14px; display:flex; align-items:center; gap:8px;">
                                📋 All Pages
                            </div>
                            <div style="display:flex; align-items:center;" id="seo-ana-type-filters">
                                <?php
                                // Build dynamic type filter dropdown from the sitemap type map
                                $_ana_type_map = get_option("seo_dash_sitemap_types_{$rid}_ga", []);
                                if (!is_array($_ana_type_map)) $_ana_type_map = [];
                                $_excluded_client_types = ['gmb_posts', 'gmb_post'];
                                $_ana_types = array_unique(array_values($_ana_type_map));
                                $_ana_types = array_filter($_ana_types, fn($t) => $t && !in_array($t, $_excluded_client_types, true));
                                sort($_ana_types);
                                // Count URLs per type (and grand total) from the sitemap type map
                                $_ana_type_counts = [];
                                foreach ($_ana_type_map as $_amap_url => $_amap_type) {
                                    if (in_array($_amap_type, $_excluded_client_types, true)) continue;
                                    $_ana_type_counts[$_amap_type] = ($_ana_type_counts[$_amap_type] ?? 0) + 1;
                                }
                                $_ana_total_count = array_sum($_ana_type_counts);
                                // Type icons map
                                $_type_icons = [
                                    'all'       => '🌍',
                                    'page'      => '📄',
                                    'service'   => '🛠️',
                                    'location'  => '📍',
                                    'city'      => '🏙️',
                                    'product'   => '🛍️',
                                    'portfolio' => '🖼️',
                                    'post'      => '✍️',
                                    'blog'      => '📰',
                                    'category'  => '🗂️',
                                    'article'   => '📝',
                                    'news'      => '📡',
                                    'tag'       => '🏷️',
                                    'author'    => '👤',
                                    'other'     => '📁',
                                ];
                                ?>
                                <select id="seo-ana-type-select" style="padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; background:var(--cc-surf2); color:var(--cc-text); border:1px solid var(--cc-border); outline:none; min-width:140px;">
                                    <option value="all">🌍 All Types (<?php echo intval($_ana_total_count); ?>)</option>
                                    <?php foreach ($_ana_types as $_atype) :
                                        $_icon = $_type_icons[$_atype] ?? '📁';
                                        $_label = ucfirst($_atype);
                                        $_count = $_ana_type_counts[$_atype] ?? 0;
                                    ?>
                                    <option value="<?php echo esc_attr($_atype); ?>"><?php echo $_icon . ' ' . esc_html($_label) . ' (' . intval($_count) . ')'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php
                            // Pass GA type map to JS for client-side filtering
                            $ana_type_map_json = wp_json_encode(array_diff_key($_ana_type_map, array_flip(array_map(fn($t) => $t, array_filter(array_keys($_ana_type_map), fn($url) => false)))));
                            ?>
                            <script>window.seoGATypeMap = window.seoGATypeMap || {}; window.seoGATypeMap[<?php echo intval($rid); ?>] = <?php echo wp_json_encode($_ana_type_map); ?>;</script>
                        </div>
                        <div class="seo-cl-table-wrap" style="border:none; overflow-x: auto; max-width: 100%;">
                            <table class="seo-cl-table" id="seo-cl-ga-table" style="width:100%;">
                                <thead style="background:var(--cc-surf); position: sticky; top: 0; z-index: 10;">
                                    <tr>
                                        <th rowspan="2" style="width:40px;text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">#</th>
                                        <th rowspan="2" style="width:180px;vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">PAGE</th>
                                        <th rowspan="2" style="width:40px;text-align:center;vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">🔗</th>
                                        <th colspan="3" data-col="7d" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);color:#0ea5e9;font-weight:800;font-size:10px;">7 DAYS</th>
                                        <th colspan="3" data-col="30d" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);color:#8b5cf6;font-weight:800;font-size:10px;">30 DAYS</th>
                                        <th colspan="3" data-col="90d" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);color:#10b981;font-weight:800;font-size:10px;">90 DAYS</th>
                                        <th colspan="3" data-col="overall" style="text-align:center;border-bottom:1px solid var(--cc-border);color:#f59e0b;font-weight:800;font-size:10px;">OVERALL</th>
                                    </tr>
                                    <tr>
                                        <th data-col="7d" style="font-size:9px;text-align:center;color:#0ea5e9;border-bottom:1px solid var(--cc-border);">USERS</th>
                                        <th data-col="7d" style="font-size:9px;text-align:center;color:#0ea5e9;border-bottom:1px solid var(--cc-border);">SESSIONS</th>
                                        <th data-col="7d" style="font-size:9px;text-align:center;color:#0ea5e9;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">VIEWS</th>
                                        
                                        <th data-col="30d" style="font-size:9px;text-align:center;color:#8b5cf6;border-bottom:1px solid var(--cc-border);">USERS</th>
                                        <th data-col="30d" style="font-size:9px;text-align:center;color:#8b5cf6;border-bottom:1px solid var(--cc-border);">SESSIONS</th>
                                        <th data-col="30d" style="font-size:9px;text-align:center;color:#8b5cf6;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">VIEWS</th>
                                        
                                        <th data-col="90d" style="font-size:9px;text-align:center;color:#10b981;border-bottom:1px solid var(--cc-border);">USERS</th>
                                        <th data-col="90d" style="font-size:9px;text-align:center;color:#10b981;border-bottom:1px solid var(--cc-border);">SESSIONS</th>
                                        <th data-col="90d" style="font-size:9px;text-align:center;color:#10b981;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">VIEWS</th>
                                        
                                        <th data-col="overall" style="font-size:9px;text-align:center;color:#f59e0b;border-bottom:1px solid var(--cc-border);">USERS</th>
                                        <th data-col="overall" style="font-size:9px;text-align:center;color:#f59e0b;border-bottom:1px solid var(--cc-border);">SESSIONS</th>
                                        <th data-col="overall" style="font-size:9px;text-align:center;color:#f59e0b;border-bottom:1px solid var(--cc-border);">VIEWS</th>
                                    </tr>
                                </thead>
                                <tbody class="seo-cl-tbody"><tr><td colspan="15" style="text-align:center;padding:32px;color:var(--cc-subtle);">Loading…</td></tr></tbody>
                            </table>
                        </div>
                        <div class="seo-cl-tab-pagination" style="display:none;padding:16px 20px;border-top:1px solid var(--cc-border);text-align:center;"></div>
                </div>
                        <?php endif; ?>

                        <?php if ($an_show['pagedetail']) : ?>
                        <!-- Page Detail Section -->
                        <div class="seo-cl-panel" style="margin-top: 24px;">
                            <div class="seo-cl-panel-hd" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <h3 style="display:flex; align-items:center; gap:8px;">🔎 Page Detail</h3>
                                <!-- Dynamic type filter dropdown — filled by JS -->
                                <div id="seo-pd-type-toggle"></div>
                            </div>
                            <div class="seo-cl-panel-body" style="padding:20px;">

                                <!-- Search + Select in one row -->
                                <div style="display:flex; gap:10px; align-items:center; margin-bottom:20px; flex-wrap:wrap;">
                                    <!-- Search input (50% width, live overlay) -->
                                    <div style="position:relative; flex:1 1 0; min-width:0;" id="seo-pd-search-wrap">
                                        <span style="position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:13px; pointer-events:none; z-index:1;">🔍</span>
                                        <input type="text" id="seo-pd-search" autocomplete="off" placeholder="Search..." style="width:100%; padding:10px 32px 10px 32px; border-radius:10px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:13px; outline:none; box-sizing:border-box; transition:border-color 0.2s;" />
                                        <span id="seo-pd-search-clear" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:15px; cursor:pointer; color:var(--cc-muted); display:none; line-height:1;">✕</span>
                                        <!-- Live results overlay — absolutely positioned so it NEVER pushes content down -->
                                        <div id="seo-pd-results" style="display:none; position:absolute; top:calc(100% + 4px); left:0; width:340px; background:var(--cc-surf, #1e2130); border:1px solid var(--cc-border); border-radius:10px; max-height:260px; overflow-y:auto; z-index:9999; box-shadow:0 8px 32px rgba(0,0,0,0.6); backdrop-filter:none; opacity:1;"></div>
                                    </div>
                                    <!-- Select dropdown (filtered by search, flex-grow to fill remaining space) -->
                                    <select id="seo-pd-url-select" style="flex:1 1 0; min-width:0; padding:10px 14px; border-radius:10px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:13px; font-weight:600; cursor:pointer; outline:none; transition:all 0.2s; box-sizing:border-box;">
                                        <option value="">Select a page...</option>
                                    </select>
                                </div>

                                <!-- Selected page URL display -->
                                <div id="seo-pd-selected-url" style="margin-bottom:16px; font-size:13px; color:var(--cc-muted); display:flex; align-items:center; gap:6px;">
                                    <span style="font-size:14px;">🔗</span> <a href="#" target="_blank" style="color:var(--cc-primary); text-decoration:none; font-weight:500;">---</a>
                                </div>

                                <!-- 4 stat cards in a single horizontal row -->
                                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:14px; margin-bottom:20px;">
                                    <div style="background:var(--cc-surf); border:1px solid var(--cc-border); border-top:3px solid #0ea5e9; border-radius:12px; padding:16px;">
                                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:12px;">
                                            <span style="width:8px; height:8px; border-radius:50%; background:#0ea5e9; display:inline-block;"></span>
                                            <span style="font-size:12px; font-weight:800; color:#0ea5e9; text-transform:uppercase; letter-spacing:.5px;">7 Days</span>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:8px;">
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Active Users</span><span style="font-weight:700; color:var(--cc-text);" id="pd-7d-users">0</span></div>
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Sessions</span><span style="font-weight:700; color:var(--cc-text);" id="pd-7d-sess">0</span></div>
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Page Views</span><span style="font-weight:700; color:var(--cc-text);" id="pd-7d-views">0</span></div>
                                        </div>
                                    </div>
                                    <div style="background:var(--cc-surf); border:1px solid var(--cc-border); border-top:3px solid #8b5cf6; border-radius:12px; padding:16px;">
                                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:12px;">
                                            <span style="width:8px; height:8px; border-radius:50%; background:#8b5cf6; display:inline-block;"></span>
                                            <span style="font-size:12px; font-weight:800; color:#8b5cf6; text-transform:uppercase; letter-spacing:.5px;">30 Days</span>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:8px;">
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Active Users</span><span style="font-weight:700; color:var(--cc-text);" id="pd-30d-users">0</span></div>
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Sessions</span><span style="font-weight:700; color:var(--cc-text);" id="pd-30d-sess">0</span></div>
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Page Views</span><span style="font-weight:700; color:var(--cc-text);" id="pd-30d-views">0</span></div>
                                        </div>
                                    </div>
                                    <div style="background:var(--cc-surf); border:1px solid var(--cc-border); border-top:3px solid #10b981; border-radius:12px; padding:16px;">
                                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:12px;">
                                            <span style="width:8px; height:8px; border-radius:50%; background:#10b981; display:inline-block;"></span>
                                            <span style="font-size:12px; font-weight:800; color:#10b981; text-transform:uppercase; letter-spacing:.5px;">90 Days</span>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:8px;">
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Active Users</span><span style="font-weight:700; color:var(--cc-text);" id="pd-90d-users">0</span></div>
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Sessions</span><span style="font-weight:700; color:var(--cc-text);" id="pd-90d-sess">0</span></div>
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Page Views</span><span style="font-weight:700; color:var(--cc-text);" id="pd-90d-views">0</span></div>
                                        </div>
                                    </div>
                                    <div style="background:var(--cc-surf); border:1px solid var(--cc-border); border-top:3px solid #f59e0b; border-radius:12px; padding:16px;">
                                        <div style="display:flex; align-items:center; gap:6px; margin-bottom:12px;">
                                            <span style="width:8px; height:8px; border-radius:50%; background:#f59e0b; display:inline-block;"></span>
                                            <span style="font-size:12px; font-weight:800; color:#f59e0b; text-transform:uppercase; letter-spacing:.5px;">Overall</span>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:8px;">
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Active Users</span><span style="font-weight:700; color:var(--cc-text);" id="pd-overall-users">0</span></div>
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Sessions</span><span style="font-weight:700; color:var(--cc-text);" id="pd-overall-sess">0</span></div>
                                            <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--cc-muted);">Page Views</span><span style="font-weight:700; color:var(--cc-text);" id="pd-overall-views">0</span></div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <script>
                        (function(){
                            // allItems: keyed by type, each value is array of {url, title}
                            var allItems = {};
                            var currentType = 'all';
                            var searchQuery = '';

                            // Type display config — icon + label for known types
                            var typeConfig = {
                                'all':      { icon: '🌐', label: 'All' },
                                'page':     { icon: '📄', label: 'Pages' },
                                'post':     { icon: '✍️',  label: 'Posts' },
                                'blog':     { icon: '📝', label: 'Blog' },
                                'product':  { icon: '🛍️', label: 'Products' },
                                'service':  { icon: '⚙️', label: 'Services' },
                                'category': { icon: '📂', label: 'Categories' },
                                'author':   { icon: '👤', label: 'Authors' },
                                'location': { icon: '📍', label: 'Locations' },
                                'tag':      { icon: '🏷️', label: 'Tags' },
                                'news':     { icon: '📰', label: 'News' },
                                'article':  { icon: '📰', label: 'Articles' },
                                'other':    { icon: '🔗', label: 'Other' },
                            };

                            function getTypeConfig(t) {
                                return typeConfig[t] || { icon: '🔗', label: t.charAt(0).toUpperCase() + t.slice(1) };
                            }

                            function buildTypeButtons(types) {
                                var $container = document.getElementById('seo-pd-type-toggle');
                                if (!$container) return;

                                var allTypes = ['all'].concat(types);
                                var totalCount = 0;
                                types.forEach(function(t){ totalCount += (allItems[t] || []).length; });

                                var sel = $container.querySelector('#seo-pd-type-select');
                                if (!sel) {
                                    sel = document.createElement('select');
                                    sel.id = 'seo-pd-type-select';
                                    sel.style.cssText = 'padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; background:var(--cc-surf2); color:var(--cc-text); border:1px solid var(--cc-border); outline:none; min-width:140px;';
                                    sel.addEventListener('change', function() {
                                        currentType = this.value;
                                        renderDropdown();
                                        var $s = document.getElementById('seo-pd-search');
                                        if ($s && $s.value) showLiveResults($s.value);
                                    });
                                    $container.innerHTML = '';
                                    $container.appendChild(sel);
                                }
                                var currentVal = sel.value || currentType;
                                sel.innerHTML = '';
                                allTypes.forEach(function(t) {
                                    var cfg = getTypeConfig(t);
                                    var count = t === 'all' ? totalCount : (allItems[t] || []).length;
                                    var opt = document.createElement('option');
                                    opt.value = t;
                                    opt.textContent = cfg.icon + ' ' + cfg.label + ' (' + count + ')';
                                    sel.appendChild(opt);
                                });
                                sel.value = allTypes.indexOf(currentVal) !== -1 ? currentVal : currentType;
                                currentType = sel.value;
                            }

                            function getFilteredItems(query) {
                                var items = [];
                                if (currentType === 'all') {
                                    Object.values(allItems).forEach(function(arr) { items = items.concat(arr); });
                                } else {
                                    items = (allItems[currentType] || []).slice();
                                }
                                var q = (query || '').trim().toLowerCase();
                                if (q) {
                                    items = items.filter(function(item) {
                                        return item.url.toLowerCase().indexOf(q) !== -1 ||
                                               item.title.toLowerCase().indexOf(q) !== -1;
                                    });
                                }
                                items.sort(function(a,b){ return a.title.localeCompare(b.title); });
                                return items;
                            }

                            // ── Populate select dropdown (filtered) ────────────
                            function renderDropdown() {
                                var $sel = document.getElementById('seo-pd-url-select');
                                if (!$sel) return;
                                var $search = document.getElementById('seo-pd-search');
                                var q = $search ? $search.value : '';
                                var items = getFilteredItems(q);
                                var cfg = getTypeConfig(currentType);
                                var singular = currentType === 'all' ? 'page' : cfg.label.toLowerCase().replace(/s$/, '');
                                var html = '<option value="">Select a ' + singular + '... (' + items.length + ' results)</option>';
                                items.forEach(function(item) {
                                    var label = item.title && item.title !== item.url ? item.title : item.url;
                                    html += '<option value="' + item.url.replace(/"/g, '&quot;') + '">' + label + '</option>';
                                });
                                $sel.innerHTML = html;
                            }

                            // ── Live search results panel ─────────────────────
                            function showLiveResults(query) {
                                var $results = document.getElementById('seo-pd-results');
                                var $clear   = document.getElementById('seo-pd-search-clear');
                                if (!$results) return;

                                var items = getFilteredItems(query);
                                $clear.style.display = query ? 'block' : 'none';

                                if (!query.trim()) {
                                    $results.style.display = 'none';
                                    return;
                                }

                                if (items.length === 0) {
                                    $results.style.display = 'block';
                                    $results.innerHTML = '<div style="padding:14px 16px; color:var(--cc-muted); font-size:13px;">No results for "' + escHtml(query) + '"</div>';
                                    return;
                                }

                                var html = '';
                                var limit = Math.min(items.length, 40);
                                for (var i = 0; i < limit; i++) {
                                    var item = items[i];
                                    var label = item.title && item.title !== item.url ? item.title : item.url;
                                    var urlShort = item.url.replace(/^https?:\/\/[^/]+/, '');
                                    var qLow = query.trim().toLowerCase();
                                    var labelHl = hlMatch(label, qLow);
                                    var urlHl   = hlMatch(urlShort, qLow);
                                    html += '<div class="seo-pd-result-row" data-url="' + escAttr(item.url) + '" style="padding:10px 16px; cursor:pointer; border-bottom:1px solid var(--cc-border); display:flex; flex-direction:column; gap:2px; transition:background 0.1s; background:var(--cc-surf, #1e2130);">'
                                          + '<span style="font-size:13px; font-weight:600; color:var(--cc-text);">' + labelHl + '</span>'
                                          + '<span style="font-size:11px; color:var(--cc-muted);">' + urlHl + '</span>'
                                          + '</div>';
                                }
                                if (items.length > 40) {
                                    html += '<div style="padding:10px 16px; font-size:12px; color:var(--cc-muted);">+ ' + (items.length - 40) + ' more — refine your search</div>';
                                }
                                $results.innerHTML = html;
                                $results.style.display = 'block';

                                // Hover effect + click handler
                                $results.querySelectorAll('.seo-pd-result-row').forEach(function(row) {
                                    row.addEventListener('mouseenter', function() { this.style.background = 'var(--cc-surf2)'; });
                                    row.addEventListener('mouseleave', function() { this.style.background = ''; });
                                    row.addEventListener('mousedown', function(e) {
                                        e.preventDefault(); // prevent blur hiding results before click fires
                                        selectPage(this.getAttribute('data-url'));
                                    });
                                });
                            }

                            function hlMatch(text, query) {
                                if (!query) return escHtml(text);
                                var idx = text.toLowerCase().indexOf(query);
                                if (idx === -1) return escHtml(text);
                                return escHtml(text.slice(0, idx))
                                     + '<mark style="background:rgba(139,92,246,0.25); color:var(--cc-text); border-radius:2px;">' + escHtml(text.slice(idx, idx + query.length)) + '</mark>'
                                     + escHtml(text.slice(idx + query.length));
                            }

                            function escHtml(s) {
                                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                            }
                            function escAttr(s) {
                                return String(s).replace(/"/g,'&quot;');
                            }

                            function selectPage(url) {
                                var $search  = document.getElementById('seo-pd-search');
                                var $results = document.getElementById('seo-pd-results');
                                var $clear   = document.getElementById('seo-pd-search-clear');
                                var $sel     = document.getElementById('seo-pd-url-select');
                                // Show the chosen URL in the search box
                                var item = null;
                                Object.values(allItems).forEach(function(arr) {
                                    arr.forEach(function(i) { if (i.url === url) item = i; });
                                });
                                if ($search) $search.value = item && item.title !== item.url ? item.title : url;
                                if ($results) $results.style.display = 'none';
                                if ($clear) $clear.style.display = 'block';
                                // Sync select — rebuild filtered list then set value
                                renderDropdown();
                                if ($sel) $sel.value = url;
                                loadPDStats(url);
                            }

                            function loadPDDropdown() {
                                var fd = new FormData();
                                fd.append('action', 'seo_dash_get_pages_dropdown_all');
                                fd.append('report_id', seoDashClient.report_id);
                                fetch(seoDashClient.ajax, { method:'POST', body:fd })
                                .then(r => r.json())
                                .then(r => {
                                    if (r.success) {
                                        allItems = r.data.byType || {};
                                        var types = Object.keys(allItems).sort();
                                        buildTypeButtons(types);
                                        renderDropdown();
                                    }
                                });
                            }

                            function loadPDStats(url) {
                                if (!url) { resetPDStats(); return; }
                                var $link = document.querySelector('#seo-pd-selected-url a');
                                if ($link) { $link.href = url; $link.innerText = url; }
                                var fd = new FormData();
                                fd.append('action', 'seo_dash_get_page_detail_stats');
                                fd.append('report_id', seoDashClient.report_id);
                                fd.append('url', url);
                                fetch(seoDashClient.ajax, { method:'POST', body:fd })
                                .then(r => r.json())
                                .then(r => {
                                    if (r.success) {
                                        updatePDStatsUI(r.data);
                                        updateTopQueries(r.data.top_queries || []);
                                    }
                                });
                            }

                            function updatePDStatsUI(stats) {
                                ['7d','30d','90d','overall'].forEach(function(p) {
                                    var $u = document.getElementById('pd-' + p + '-users');
                                    var $s = document.getElementById('pd-' + p + '-sess');
                                    var $v = document.getElementById('pd-' + p + '-views');
                                    if($u) $u.innerText = (stats[p] && stats[p].users    || 0).toLocaleString();
                                    if($s) $s.innerText = (stats[p] && stats[p].sessions || 0).toLocaleString();
                                    if($v) $v.innerText = (stats[p] && stats[p].views    || 0).toLocaleString();
                                });
                            }

                            function updateTopQueries(queries) {
                                var $el = document.getElementById('seo-pd-top-queries');
                                if (!$el) return;
                                if (!queries || queries.length === 0) {
                                    $el.innerHTML = '<div style="color:var(--cc-muted); font-size:13px;">No query data for this page.</div>';
                                    return;
                                }
                                var html = '';
                                queries.forEach(function(q) {
                                    html += '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--cc-border);">'
                                          + '<span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;">' + escHtml(q.query) + '</span>'
                                          + '<span style="font-weight:800; color:var(--cc-primary); flex-shrink:0; margin-left:8px;">' + (q.clicks || q.count || 0) + '</span>'
                                          + '</div>';
                                });
                                $el.innerHTML = html;
                            }

                            function resetPDStats() {
                                updatePDStatsUI({
                                    '7d':{users:0,sessions:0,views:0}, '30d':{users:0,sessions:0,views:0},
                                    '90d':{users:0,sessions:0,views:0}, 'overall':{users:0,sessions:0,views:0}
                                });
                                var $link = document.querySelector('#seo-pd-selected-url a');
                                if($link) { $link.href = '#'; $link.innerText = '---'; }
                                var $tq = document.getElementById('seo-pd-top-queries');
                                if($tq) $tq.innerHTML = '<div style="color:var(--cc-muted); font-size:13px;">Select a page to see top queries.</div>';
                            }

                            document.addEventListener('DOMContentLoaded', function(){
                                loadPDDropdown();

                                var $search  = document.getElementById('seo-pd-search');
                                var $results = document.getElementById('seo-pd-results');
                                var $clear   = document.getElementById('seo-pd-search-clear');
                                var searchTimer;

                                if ($search) {
                                    $search.addEventListener('input', function() {
                                        clearTimeout(searchTimer);
                                        var q = this.value;
                                        searchTimer = setTimeout(function() {
                                            showLiveResults(q);
                                            renderDropdown(); // keep select in sync with search text
                                        }, 150);
                                    });
                                    $search.addEventListener('focus', function() {
                                        this.style.borderColor = 'var(--cc-primary)';
                                        if (this.value) showLiveResults(this.value);
                                    });
                                    $search.addEventListener('blur', function() {
                                        this.style.borderColor = 'var(--cc-border)';
                                        // Delay hide so mousedown on result fires first
                                        setTimeout(function() {
                                            if ($results) $results.style.display = 'none';
                                        }, 180);
                                    });
                                    $search.addEventListener('keydown', function(e) {
                                        if (e.key === 'Escape') {
                                            $results.style.display = 'none';
                                            this.blur();
                                        }
                                    });
                                }

                                if ($clear) {
                                    $clear.addEventListener('click', function() {
                                        if ($search) { $search.value = ''; $search.focus(); }
                                        if ($results) $results.style.display = 'none';
                                        this.style.display = 'none';
                                        renderDropdown(); // reset dropdown to full list
                                        var $sel = document.getElementById('seo-pd-url-select');
                                        if ($sel) $sel.value = '';
                                        resetPDStats();
                                    });
                                }

                                // Wire select dropdown → load stats on change
                                var $sel = document.getElementById('seo-pd-url-select');
                                if ($sel) {
                                    $sel.addEventListener('change', function() {
                                        var url = this.value;
                                        if (!url) { resetPDStats(); return; }
                                        // Sync search box display
                                        var item = null;
                                        Object.values(allItems).forEach(function(arr) {
                                            arr.forEach(function(i) { if (i.url === url) item = i; });
                                        });
                                        if ($search) $search.value = item && item.title !== item.url ? item.title : url;
                                        if ($clear) $clear.style.display = 'block';
                                        loadPDStats(url);
                                    });
                                }

                                // Close results if clicking outside
                                document.addEventListener('click', function(e) {
                                    var wrap = document.getElementById('seo-pd-search-wrap');
                                    if (wrap && !wrap.contains(e.target) && $results) {
                                        $results.style.display = 'none';
                                    }
                                });
                            });
                        })();
                        </script>
                        <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Shared Analytics + Search Console chart/KPI script (moved OUTSIDE the
                 Analytics visibility guard so SC keeps working when Analytics is hidden) -->
                        <script>
                        function seoUpdateAnaKPIs(period) {
                            // Use the current filtered dataset — matches exactly what the table shows
                            var fullCache = window.seoTabFullDataCache || {};
                            var rows = fullCache['analytics_current'] || fullCache['analytics_ga'] || (window.seoTabRowCache && window.seoTabRowCache['analytics']) || [];
                            // TOTAL URLS always reflects ALL sitemap types, regardless of the active type filter
                            var allRows = fullCache['analytics_ga_all'] || (window.seoTabRowCache && window.seoTabRowCache['analytics']) || rows;
                            var sumUsers = 0, sumSess = 0, sumViews = 0;

                            rows.forEach(function(r) {
                                var d = r.data || {};
                                var p = d[period] || null;
                                if (!p && period === '30d') p = d['monthly'] || null;
                                if (!p) return;
                                sumUsers += parseInt(p.users || 0);
                                sumSess  += parseInt(p.sessions || 0);
                                sumViews += parseInt(p.pageviews || 0);
                            });

                            if (document.getElementById('kpi-ana-users')) document.getElementById('kpi-ana-users').innerText = sumUsers.toLocaleString();
                            if (document.getElementById('kpi-ana-sess')) document.getElementById('kpi-ana-sess').innerText = sumSess.toLocaleString();
                            if (document.getElementById('kpi-ana-views')) document.getElementById('kpi-ana-views').innerText = sumViews.toLocaleString();
                            if (document.getElementById('kpi-ana-urls')) document.getElementById('kpi-ana-urls').innerText = rows.length.toLocaleString();

                            var labels = document.querySelectorAll('.seo-kpi-period-label');
                            var periodName = period === 'overall' ? 'Overall' : (period === '7d' ? '7 Days' : (period === '30d' ? '30 Days' : '90 Days'));
                            labels.forEach(function(l) { l.innerText = periodName; });

                            // Highlight columns in visible table
                            var table = document.getElementById('seo-cl-ga-table');
                            if (table) {
                                var allCols = table.querySelectorAll('th[data-col], td[data-col]');
                                allCols.forEach(function(c) { c.style.background = ''; c.classList.remove('seo-col-highlight'); });
                                var activeCols = table.querySelectorAll('th[data-col="'+period+'"], td[data-col="'+period+'"]');
                                var highlightColor = 'var(--cc-surf2)';
                                if (period === '30d') highlightColor = 'rgba(139,92,246,0.05)';
                                else if (period === '7d') highlightColor = 'rgba(14,165,233,0.05)';
                                else if (period === '90d') highlightColor = 'rgba(16,185,129,0.05)';
                                else if (period === 'overall') highlightColor = 'rgba(245,158,11,0.05)';
                                activeCols.forEach(function(c) { c.style.background = highlightColor; c.classList.add('seo-col-highlight'); });
                            }
                        }

                        document.addEventListener('click', function(e) {
                            var btn = e.target.closest('.seo-ct-btn');
                            if (!btn) return;
                            var group = btn.closest('.seo-chart-type-toggle-group');
                            if (!group) return;
                            var targetId = group.getAttribute('data-target');
                            var canvas = document.getElementById(targetId);
                            if (!canvas) return;
                            
                            group.querySelectorAll('.seo-ct-btn').forEach(function(b) {
                                b.classList.remove('active');
                                b.style.background = 'transparent';
                                b.style.color = 'var(--cc-muted)';
                            });
                            btn.classList.add('active');
                            btn.style.background = 'var(--cc-primary)';
                            btn.style.color = '#fff';

                            var newType = btn.getAttribute('data-type') || 'area';
                            canvas.setAttribute('data-chart-type', newType);

                            if (targetId === 'seo-ana-chart' && typeof window.seoUpdateAnaChart === 'function') {
                                window.seoUpdateAnaChart();
                            } else if (targetId === 'seo-sc-chart' && typeof window.seoUpdateSCChart === 'function') {
                                window.seoUpdateSCChart();
                            } else if (targetId === 'seo-sp-chart' && typeof window.seoUpdateSPChart === 'function') {
                                window.seoUpdateSPChart();
                            } else if (targetId === 'seo-blog-chart' && typeof window.seoUpdateBlogChart === 'function') {
                                window.seoUpdateBlogChart();
                            }
                        });

                        window.seoBuildUniversalChart = function(canvasEl, opts) {
                            if (!canvasEl || typeof Chart === 'undefined') return null;
                            var rawType   = opts.type || 'bar';
                            var rawLabels = opts.labels || [];
                            var values    = opts.values || [];
                            var color     = opts.color || '#8b5cf6';
                            var labelName = opts.labelName || 'Metric';

                            var isDark = !!(document.getElementById('seo-client-app') && document.getElementById('seo-client-app').classList.contains('seo-dark'));
                            var gridC  = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
                            var txtC   = isDark ? 'rgba(255,255,255,.65)' : '#334155';

                            function hexToRgb(h) {
                                if (!h || h.charAt(0) !== '#') return '139,92,246';
                                var r = parseInt(h.slice(1,3),16), g = parseInt(h.slice(3,5),16), b = parseInt(h.slice(5,7),16);
                                return r+','+g+','+b;
                            }
                            var rgb = hexToRgb(color);

                            var isArea     = rawType === 'area';
                            var isLine     = rawType === 'line' || isArea;
                            var isHoriz    = rawType === 'horizontalBar';
                            var isStacked  = rawType === 'stackedBar';
                            var isGrouped  = rawType === 'groupedBar';
                            var isPie      = rawType === 'pie';
                            var isDoughnut = rawType === 'doughnut';
                            var isPolar    = rawType === 'polarArea';
                            var isRadar    = rawType === 'radar';

                            var isCircular = isPie || isDoughnut || isPolar;

                            var chartType = 'bar';
                            if (isLine) chartType = 'line';
                            else if (isPie) chartType = 'pie';
                            else if (isDoughnut) chartType = 'doughnut';
                            else if (isPolar) chartType = 'polarArea';
                            else if (isRadar) chartType = 'radar';

                            var totalVal = values.reduce(function(acc, v){ return acc + (parseInt(v)||0); }, 0);
                            var formattedLabels = rawLabels;
                            if (isCircular || isRadar) {
                                formattedLabels = rawLabels.map(function(lbl, i) {
                                    var val = parseInt(values[i]) || 0;
                                    var pct = totalVal > 0 ? Math.round((val / totalVal) * 100) : 0;
                                    return lbl + ' (' + val.toLocaleString() + (totalVal > 0 ? ' • ' + pct + '%' : '') + ')';
                                });
                            }

                            var palette = [
                                '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444',
                                '#6366f1', '#ec4899', '#14b8a6', '#f97316', '#84cc16'
                            ];
                            var bgColors;
                            if (isCircular || isGrouped || isRadar) {
                                bgColors = values.map(function(_, i){ return palette[i % palette.length]; });
                            } else if (isArea && canvasEl.getContext) {
                                var ctx2d = canvasEl.getContext('2d');
                                var grad = ctx2d.createLinearGradient(0, 0, 0, 300);
                                grad.addColorStop(0, 'rgba(' + rgb + ', 0.45)');
                                grad.addColorStop(1, 'rgba(' + rgb + ', 0.02)');
                                bgColors = grad;
                            } else if (isLine) {
                                bgColors = 'rgba(' + rgb + ', 0.1)';
                            } else {
                                bgColors = 'rgba(' + rgb + ', 0.85)';
                            }

                            var dataset = {
                                label: labelName,
                                data: values,
                                backgroundColor: bgColors,
                                borderColor: isLine ? color : (isCircular ? (isDark ? '#1e293b' : '#fff') : color),
                                borderWidth: isLine ? 3 : (isCircular ? 2 : 1),
                                borderRadius: (chartType === 'bar' && !isStacked) ? 6 : 0,
                                maxBarThickness: 42,
                                barPercentage: 0.8,
                                categoryPercentage: 0.85,
                                fill: isArea,
                                tension: isLine ? 0.45 : 0,
                                pointRadius: isLine ? 5 : 0,
                                pointHoverRadius: isLine ? 8 : 0,
                                pointBackgroundColor: color,
                                pointBorderColor: isDark ? '#0f172a' : '#ffffff',
                                pointBorderWidth: isLine ? 2 : 0
                            };

                            var options = {
                                responsive: true,
                                maintainAspectRatio: false,
                                indexAxis: isHoriz ? 'y' : 'x',
                                plugins: {
                                    legend: {
                                        display: (isCircular || isRadar),
                                        position: (isCircular || isRadar) ? 'right' : 'top',
                                        labels: { color: txtC, font: { size: 11, weight: '600' }, boxWidth: 12 }
                                    },
                                    tooltip: {
                                        backgroundColor: isDark ? 'rgba(15,23,42,0.95)' : 'rgba(255,255,255,0.97)',
                                        titleColor: isDark ? '#e2e8f0' : '#1e293b',
                                        bodyColor: isDark ? '#94a3b8' : '#475569',
                                        borderColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)',
                                        borderWidth: 1,
                                        padding: 10,
                                        cornerRadius: 8,
                                        callbacks: {
                                            title: function(items) {
                                                if (!items || !items.length) return '';
                                                var idx = items[0].dataIndex;
                                                return rawLabels[idx] || items[0].label;
                                            },
                                            label: function(ctx) {
                                                var val = ctx.raw || 0;
                                                var pct = totalVal > 0 ? Math.round((val / totalVal) * 100) : 0;
                                                return labelName + ': ' + val.toLocaleString() + (totalVal > 0 ? ' (' + pct + '%)' : '');
                                            }
                                        }
                                    }
                                }
                            };

                            if (!isCircular && !isRadar) {
                                options.scales = {
                                    x: {
                                        stacked: isStacked,
                                        grid: { color: gridC },
                                        ticks: {
                                            color: txtC,
                                            font: { size: 10 },
                                            maxRotation: isHoriz ? 0 : 20,
                                            minRotation: 0,
                                            autoSkip: false,
                                            callback: function(val, index) {
                                                var raw = rawLabels[index] || this.getLabelForValue(val) || '';
                                                if (typeof raw === 'string' && raw.length > 20 && !isHoriz) {
                                                    var clean = raw.split('|')[0].split('–')[0].trim();
                                                    return clean.length > 18 ? clean.substring(0, 16) + '…' : clean;
                                                }
                                                return raw;
                                            }
                                        }
                                    },
                                    y: {
                                        stacked: isStacked,
                                        grid: { color: gridC },
                                        ticks: { color: txtC, font: { size: 10 } },
                                        beginAtZero: true
                                    }
                                };
                            } else if (isRadar) {
                                options.scales = {
                                    r: {
                                        grid: { color: gridC },
                                        ticks: { display: false, backdropColor: 'transparent' },
                                        pointLabels: { color: txtC, font: { size: 11 } }
                                    }
                                };
                            } else if (isDoughnut) {
                                options.cutout = '65%';
                            }

                            var customDataLabelsPlugin = {
                                id: 'pointValueLabels',
                                afterDatasetsDraw: function(chart) {
                                    if (!isLine) return;
                                    var ctx = chart.ctx;
                                    chart.data.datasets.forEach(function(ds, i) {
                                        var meta = chart.getDatasetMeta(i);
                                        if (!meta || meta.hidden) return;
                                        meta.data.forEach(function(elem, idx) {
                                            var val = ds.data[idx];
                                            if (val === null || val === undefined) return;
                                            ctx.save();
                                            ctx.fillStyle = isDark ? '#c084fc' : '#7c3aed';
                                            ctx.font = '600 11px Inter, system-ui, sans-serif';
                                            ctx.textAlign = 'center';
                                            ctx.textBaseline = 'bottom';
                                            var pos = elem.tooltipPosition ? elem.tooltipPosition() : { x: elem.x, y: elem.y };
                                            ctx.fillText(Number(val).toLocaleString(), pos.x, pos.y - 7);
                                            ctx.restore();
                                        });
                                    });
                                }
                            };

                            return new Chart(canvasEl, {
                                type: chartType,
                                data: { labels: formattedLabels, datasets: [dataset] },
                                options: options,
                                plugins: [customDataLabelsPlugin]
                            });
                        };

                        var anaChart = null;
                        function seoUpdateAnaChart() {
                            var el = document.getElementById('seo-ana-chart');
                            if(!el) return;

                            var activeBtn = document.querySelector('.seo-tp-btn.active');
                            var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                            var $mSel = document.getElementById('seo-ana-chart-metric');
                            var metric = $mSel ? $mSel.value : 'sessions';
                            var type = el.dataset.chartType || 'area';
                            var color = el.dataset.color || '#8b5cf6';

                            var cacheKey = 'analytics';
                            var fullCache = window.seoTabFullDataCache || {};
                            var rows = fullCache['analytics_current'] || fullCache['analytics_ga'] || fullCache['analytics_ga_all'] || fullCache['analytics_all'] || (window.seoTabRowCache && (window.seoTabRowCache['analytics'] || window.seoTabRowCache['ga'])) || [];

                            var mKey = { 'users': 'users', 'sessions': 'sessions', 'views': 'pageviews' }[metric] || 'sessions';

                            var data = [];
                            rows.forEach(function(r) {
                                var d = r.data || {};
                                var p = (d && d[period]) ? d[period] : ((d && (d[mKey] !== undefined || d.sessions !== undefined)) ? d : r);
                                var val = parseInt((p && p[mKey] !== undefined) ? p[mKey] : (p ? (p.sessions || p.users || p.pageviews || 0) : 0));
                                var title = r.title || r.url || '';
                                if (!title || title === r.url) {
                                    try {
                                        var u = new URL(r.url);
                                        var path = u.pathname.replace(/\/$/, '').split('/').pop();
                                        title = path ? path.replace(/-/g, ' ') : 'Home';
                                        title = title.charAt(0).toUpperCase() + title.slice(1);
                                    } catch(e) { title = r.url || 'Page'; }
                                }
                                if (val > 0) data.push({ label: title, value: val });
                            });

                            if (data.length === 0 && rows.length > 0) {
                                rows.slice(0, 10).forEach(function(r) {
                                    var title = r.title || r.url || 'Page';
                                    if (title.indexOf('http') === 0) {
                                        try {
                                            var u = new URL(r.url);
                                            var path = u.pathname.replace(/\/$/, '').split('/').pop();
                                            title = path ? path.replace(/-/g, ' ') : 'Home';
                                            title = title.charAt(0).toUpperCase() + title.slice(1);
                                        } catch(e) { title = r.url; }
                                    }
                                    data.push({ label: title, value: 0 });
                                });
                            }

                            // Select top 10, then sort low to high so performance curve climbs UPWARDS from left to right
                            data.sort(function(a,b){ return b.value - a.value; });
                            data = data.slice(0, 10);
                            data.sort(function(a,b){ return a.value - b.value; });

                            var labels = data.map(function(d){ return d.label; });
                            var values = data.map(function(d){ return d.value; });

                            if(anaChart) anaChart.destroy();
                            anaChart = window.seoBuildUniversalChart(el, {
                                type: type,
                                labels: labels,
                                values: values,
                                color: color,
                                labelName: metric.charAt(0).toUpperCase() + metric.slice(1)
                            });
                        }

                        function seoUpdateSCKPIs(period) {
                            // Use current filtered dataset — matches what the table shows
                            var fullCache = window.seoTabFullDataCache || {};
                            var rows = fullCache['sc_current'] || fullCache['sc_sc'] || (window.seoTabRowCache && window.seoTabRowCache['sc']) || [];
                            // TOTAL URLS always reflects ALL sitemap types, regardless of the active type filter
                            var allRows = fullCache['sc_sc'] || (window.seoTabRowCache && window.seoTabRowCache['sc']) || rows;
                            var sumClicks = 0, sumImpr = 0, sumCtr = 0, rowCount = 0;

                            rows.forEach(function(r) {
                                var d = r.data || {};
                                var p = d[period] || null;
                                if (!p) return;
                                sumClicks += parseInt(p.clicks || 0);
                                sumImpr   += parseInt(p.impressions || 0);
                                sumCtr    += parseFloat(p.ctr || 0);
                                rowCount++;
                            });

                            var avgCtr = rowCount > 0 ? (sumCtr / rowCount).toFixed(1) : '0.0';

                            if (document.getElementById('kpi-sc-clicks')) document.getElementById('kpi-sc-clicks').innerText = sumClicks.toLocaleString();
                            if (document.getElementById('kpi-sc-impr')) document.getElementById('kpi-sc-impr').innerText = sumImpr.toLocaleString();
                            if (document.getElementById('kpi-sc-ctr')) document.getElementById('kpi-sc-ctr').innerText = avgCtr + '%';
                            if (document.getElementById('kpi-sc-urls')) document.getElementById('kpi-sc-urls').innerText = rows.length.toLocaleString();

                            var labels = document.querySelectorAll('.seo-sc-kpi-period-label');
                            var periodName = period === 'overall' ? 'Overall' : (period === '7d' ? '7 Days' : (period === '30d' ? '30 Days' : '90 Days'));
                            labels.forEach(function(l) { l.innerText = periodName; });

                            var table = document.getElementById('seo-cl-sc-table');
                            if (table) {
                                var allCols = table.querySelectorAll('th[data-col], td[data-col]');
                                allCols.forEach(function(c) { c.style.background = ''; c.classList.remove('seo-col-highlight'); });
                                var activeCols = table.querySelectorAll('th[data-col="'+period+'"], td[data-col="'+period+'"]');
                                var highlightColor = 'var(--cc-surf2)';
                                if (period === '30d') highlightColor = 'rgba(139,92,246,0.05)';
                                else if (period === '7d') highlightColor = 'rgba(14,165,233,0.05)';
                                else if (period === '90d') highlightColor = 'rgba(16,185,129,0.05)';
                                else if (period === 'overall') highlightColor = 'rgba(245,158,11,0.05)';
                                activeCols.forEach(function(c) { c.style.background = highlightColor; c.classList.add('seo-col-highlight'); });
                            }
                        }

                        var scChart = null;
                        function seoUpdateSCChart() {
                            var el = document.getElementById('seo-sc-chart');
                            if(!el) return;

                            var activeBtn = document.querySelector('.seo-sc-tp-btn.active');
                            var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                            var $mSel = document.getElementById('seo-sc-chart-metric');
                            var metric = $mSel ? $mSel.value : 'clicks';
                            var type = el.dataset.chartType || 'area';
                            var color = el.dataset.color || '#8b5cf6';

                            var scCacheKey = 'sc';
                            var fullCache = window.seoTabFullDataCache || {};
                            var rows = fullCache['sc_current'] || fullCache['sc_sc'] || fullCache['sc_all'] || (window.seoTabRowCache && (window.seoTabRowCache['sc'] || window.seoTabRowCache[scCacheKey])) || [];

                            var mKey = { 'clicks': 'clicks', 'impressions': 'impressions', 'ctr': 'ctr', 'position': 'position' }[metric] || 'clicks';

                            var data = [];
                            rows.forEach(function(r) {
                                var d = r.data || {};
                                var p = (d && d[period]) ? d[period] : ((d && (d[mKey] !== undefined || d.clicks !== undefined)) ? d : r);
                                var val = parseFloat((p && p[mKey] !== undefined) ? p[mKey] : (p ? (p.clicks || p.impressions || 0) : 0));
                                var title = r.title || r.url || '';
                                if (!title || title === r.url) {
                                    try {
                                        var u = new URL(r.url);
                                        var path = u.pathname.replace(/\/$/, '').split('/').pop();
                                        title = path ? path.replace(/-/g, ' ') : 'Home';
                                        title = title.charAt(0).toUpperCase() + title.slice(1);
                                    } catch(e) { title = r.url || 'Page'; }
                                }
                                if (val > 0) data.push({ label: title, value: val });
                            });

                            if (data.length === 0 && rows.length > 0) {
                                rows.slice(0, 10).forEach(function(r) {
                                    var title = r.title || r.url || 'Page';
                                    if (title.indexOf('http') === 0) {
                                        try {
                                            var u = new URL(r.url);
                                            var path = u.pathname.replace(/\/$/, '').split('/').pop();
                                            title = path ? path.replace(/-/g, ' ') : 'Home';
                                            title = title.charAt(0).toUpperCase() + title.slice(1);
                                        } catch(e) { title = r.url; }
                                    }
                                    data.push({ label: title, value: 0 });
                                });
                            }

                            // Select top 10, then sort low to high so performance curve climbs UPWARDS from left to right
                            data.sort(function(a,b){ return b.value - a.value; });
                            data = data.slice(0, 10);
                            data.sort(function(a,b){ return a.value - b.value; });

                            var labels = data.map(function(d){ return d.label; });
                            var values = data.map(function(d){ return d.value; });

                            if(scChart) scChart.destroy();
                            scChart = window.seoBuildUniversalChart(el, {
                                type: type,
                                labels: labels,
                                values: values,
                                color: color,
                                labelName: metric.charAt(0).toUpperCase() + metric.slice(1)
                            });
                        }

                        document.addEventListener('DOMContentLoaded', function() {
                            setTimeout(function() {
                                if (typeof seoUpdateAnaChart === 'function') seoUpdateAnaChart();
                                if (typeof seoUpdateSCChart === 'function') seoUpdateSCChart();
                                if (typeof seoUpdateSPChart === 'function') seoUpdateSPChart();
                                if (typeof seoUpdateBlogChart === 'function') seoUpdateBlogChart();
                            }, 200);

                            // Analytics Listeners
                            document.querySelectorAll('.seo-tp-btn').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    document.querySelectorAll('.seo-tp-btn').forEach(function(b) { 
                                        b.classList.remove('active'); 
                                        b.style.background = 'transparent';
                                        b.style.color = 'var(--cc-text)';
                                        b.style.borderColor = 'var(--cc-border)';
                                    });
                                    this.classList.add('active');
                                    this.style.background = 'var(--cc-primary)';
                                    this.style.color = '#fff';
                                    this.style.borderColor = 'var(--cc-primary)';
                                    
                                    var period = this.getAttribute('data-period');
                                    seoUpdateAnaKPIs(period);
                                    seoUpdateAnaChart();
                                });
                            });
                            
                            // Listen to full-data event from client-app.js
                            jQuery(document).on('seo:rowsLoaded', function(e, tab, rows) {
                                if (tab === 'analytics') {
                                    var activeBtn = document.querySelector('.seo-tp-btn.active');
                                    var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                                    seoUpdateAnaKPIs(period);
                                    seoUpdateAnaChart();
                                }
                            });

                            var $chartType = document.getElementById('seo-ana-chart-type');
                            var $chartMetric = document.getElementById('seo-ana-chart-metric');
                            if($chartType) $chartType.addEventListener('change', seoUpdateAnaChart);
                            if($chartMetric) $chartMetric.addEventListener('change', seoUpdateAnaChart);

                            // SC Listeners
                            document.querySelectorAll('.seo-sc-tp-btn').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    document.querySelectorAll('.seo-sc-tp-btn').forEach(function(b) { 
                                        b.classList.remove('active'); 
                                        b.style.background = 'transparent';
                                        b.style.color = 'var(--cc-text)';
                                        b.style.borderColor = 'var(--cc-border)';
                                    });
                                    this.classList.add('active');
                                    this.style.background = 'var(--cc-primary)';
                                    this.style.color = '#fff';
                                    this.style.borderColor = 'var(--cc-primary)';
                                    
                                    var period = this.getAttribute('data-period');
                                    seoUpdateSCKPIs(period);
                                    seoUpdateSCChart();
                                });
                            });
                            
                            // Listen to full-data event from client-app.js for SC
                            jQuery(document).on('seo:rowsLoaded', function(e, tab, rows) {
                                if (tab === 'sc') {
                                    var activeBtn = document.querySelector('.seo-sc-tp-btn.active');
                                    var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                                    seoUpdateSCKPIs(period);
                                    seoUpdateSCChart();
                                    loadSCPDDropdown();
                                }
                            });

                            var $scChartType = document.getElementById('seo-sc-chart-type');
                            var $scChartMetric = document.getElementById('seo-sc-chart-metric');
                            if($scChartType) $scChartType.addEventListener('change', seoUpdateSCChart);
                            if($scChartMetric) $scChartMetric.addEventListener('change', seoUpdateSCChart);

                            // SC Page Detail Logic — type filter + live search (mirrors Analytics Page Detail)
                            (function(){
                                var scAllItems = {};   // grouped by type: { type: [{url,title}] }
                                var scPdDataMap = {};  // url -> { url, title, stats }
                                var scCurrentType = 'all';

                                var scTypeConfig = {
                                    'all':      { icon: '🌐', label: 'All' },
                                    'page':     { icon: '📄', label: 'Pages' },
                                    'post':     { icon: '✍️',  label: 'Posts' },
                                    'blog':     { icon: '📝', label: 'Blog' },
                                    'product':  { icon: '🛍️', label: 'Products' },
                                    'service':  { icon: '⚙️', label: 'Services' },
                                    'category': { icon: '📂', label: 'Categories' },
                                    'author':   { icon: '👤', label: 'Authors' },
                                    'location': { icon: '📍', label: 'Locations' },
                                    'tag':      { icon: '🏷️', label: 'Tags' },
                                    'news':     { icon: '📰', label: 'News' },
                                    'article':  { icon: '📰', label: 'Articles' },
                                    'other':    { icon: '🔗', label: 'Other' },
                                };
                                function scGetTypeConfig(t) {
                                    return scTypeConfig[t] || { icon: '🔗', label: t.charAt(0).toUpperCase() + t.slice(1) };
                                }

                                function buildSCTypeButtons(types) {
                                    var $container = document.getElementById('seo-sc-pd-type-toggle');
                                    if (!$container) return;
                                    var allTypes = ['all'].concat(types);
                                    var totalCount = 0;
                                    types.forEach(function(t){ totalCount += (scAllItems[t] || []).length; });

                                    var sel = $container.querySelector('#seo-sc-pd-type-select');
                                    if (!sel) {
                                        sel = document.createElement('select');
                                        sel.id = 'seo-sc-pd-type-select';
                                        sel.style.cssText = 'padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; background:var(--cc-surf2); color:var(--cc-text); border:1px solid var(--cc-border); outline:none; min-width:140px;';
                                        sel.addEventListener('change', function() {
                                            scCurrentType = this.value;
                                            scRenderDropdown();
                                            var $s = document.getElementById('seo-sc-pd-search');
                                            if ($s && $s.value) scShowLiveResults($s.value);
                                        });
                                        $container.innerHTML = '';
                                        $container.appendChild(sel);
                                    }
                                    var currentVal = sel.value || scCurrentType;
                                    sel.innerHTML = '';
                                    allTypes.forEach(function(t) {
                                        var cfg = scGetTypeConfig(t);
                                        var count = t === 'all' ? totalCount : (scAllItems[t] || []).length;
                                        var opt = document.createElement('option');
                                        opt.value = t;
                                        opt.textContent = cfg.icon + ' ' + cfg.label + ' (' + count + ')';
                                        sel.appendChild(opt);
                                    });
                                    sel.value = allTypes.indexOf(currentVal) !== -1 ? currentVal : scCurrentType;
                                    scCurrentType = sel.value;
                                }

                                function scGetFilteredItems(query) {
                                    var items = [];
                                    if (scCurrentType === 'all') {
                                        Object.values(scAllItems).forEach(function(arr) { items = items.concat(arr); });
                                    } else {
                                        items = (scAllItems[scCurrentType] || []).slice();
                                    }
                                    var q = (query || '').trim().toLowerCase();
                                    if (q) {
                                        items = items.filter(function(item) {
                                            return item.url.toLowerCase().indexOf(q) !== -1 ||
                                                   item.title.toLowerCase().indexOf(q) !== -1;
                                        });
                                    }
                                    items.sort(function(a,b){ return a.title.localeCompare(b.title); });
                                    return items;
                                }

                                function scRenderDropdown() {
                                    var $sel = document.getElementById('seo-sc-pd-url-select');
                                    if (!$sel) return;
                                    var $search = document.getElementById('seo-sc-pd-search');
                                    var q = $search ? $search.value : '';
                                    var items = scGetFilteredItems(q);
                                    var cfg = scGetTypeConfig(scCurrentType);
                                    var singular = scCurrentType === 'all' ? 'page' : cfg.label.toLowerCase().replace(/s$/, '');
                                    var currentVal = $sel.value;
                                    var html = '<option value="">Select a ' + singular + '... (' + items.length + ' results)</option>';
                                    items.forEach(function(item) {
                                        var label = item.title && item.title !== item.url ? item.title : item.url;
                                        html += '<option value="' + item.url.replace(/"/g, '&quot;') + '">' + label + '</option>';
                                    });
                                    $sel.innerHTML = html;
                                    if (currentVal && items.some(function(i){ return i.url === currentVal; })) $sel.value = currentVal;
                                }

                                function scHlMatch(text, query) {
                                    if (!query) return scEscHtml(text);
                                    var idx = text.toLowerCase().indexOf(query);
                                    if (idx === -1) return scEscHtml(text);
                                    return scEscHtml(text.slice(0, idx))
                                         + '<mark style="background:rgba(139,92,246,0.25); color:var(--cc-text); border-radius:2px;">' + scEscHtml(text.slice(idx, idx + query.length)) + '</mark>'
                                         + scEscHtml(text.slice(idx + query.length));
                                }
                                function scEscHtml(s) {
                                    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                                }
                                function scEscAttr(s) {
                                    return String(s).replace(/"/g,'&quot;');
                                }

                                function scShowLiveResults(query) {
                                    var $results = document.getElementById('seo-sc-pd-results');
                                    var $clear   = document.getElementById('seo-sc-pd-search-clear');
                                    if (!$results) return;

                                    var items = scGetFilteredItems(query);
                                    if ($clear) $clear.style.display = query ? 'block' : 'none';

                                    if (!query.trim()) {
                                        $results.style.display = 'none';
                                        return;
                                    }

                                    if (items.length === 0) {
                                        $results.style.display = 'block';
                                        $results.innerHTML = '<div style="padding:14px 16px; color:var(--cc-muted); font-size:13px;">No results for "' + scEscHtml(query) + '"</div>';
                                        return;
                                    }

                                    var html = '';
                                    var limit = Math.min(items.length, 40);
                                    for (var i = 0; i < limit; i++) {
                                        var item = items[i];
                                        var label = item.title && item.title !== item.url ? item.title : item.url;
                                        var urlShort = item.url.replace(/^https?:\/\/[^/]+/, '');
                                        var qLow = query.trim().toLowerCase();
                                        var labelHl = scHlMatch(label, qLow);
                                        var urlHl   = scHlMatch(urlShort, qLow);
                                        html += '<div class="seo-sc-pd-result-row" data-url="' + scEscAttr(item.url) + '" style="padding:10px 16px; cursor:pointer; border-bottom:1px solid var(--cc-border); display:flex; flex-direction:column; gap:2px; transition:background 0.1s; background:var(--cc-surf, #1e2130);">'
                                              + '<span style="font-size:13px; font-weight:600; color:var(--cc-text);">' + labelHl + '</span>'
                                              + '<span style="font-size:11px; color:var(--cc-muted);">' + urlHl + '</span>'
                                              + '</div>';
                                    }
                                    if (items.length > 40) {
                                        html += '<div style="padding:10px 16px; font-size:12px; color:var(--cc-muted);">+ ' + (items.length - 40) + ' more — refine your search</div>';
                                    }
                                    $results.innerHTML = html;
                                    $results.style.display = 'block';

                                    $results.querySelectorAll('.seo-sc-pd-result-row').forEach(function(row) {
                                        row.addEventListener('mouseenter', function() { this.style.background = 'var(--cc-surf2)'; });
                                        row.addEventListener('mouseleave', function() { this.style.background = ''; });
                                        row.addEventListener('mousedown', function(e) {
                                            e.preventDefault();
                                            scSelectPage(this.getAttribute('data-url'));
                                        });
                                    });
                                }

                                function scSelectPage(url) {
                                    var $search  = document.getElementById('seo-sc-pd-search');
                                    var $results = document.getElementById('seo-sc-pd-results');
                                    var $clear   = document.getElementById('seo-sc-pd-search-clear');
                                    var $sel     = document.getElementById('seo-sc-pd-url-select');
                                    var item = scPdDataMap[url] || null;
                                    if ($search) $search.value = item && item.title !== item.url ? item.title : url;
                                    if ($results) $results.style.display = 'none';
                                    if ($clear) $clear.style.display = 'block';
                                    scRenderDropdown();
                                    if ($sel) $sel.value = url;
                                    scApplyStats(url);
                                }

                                function scApplyStats(url) {
                                    var item = scPdDataMap[url];
                                    var $link = document.querySelector('#seo-sc-pd-selected-url a');
                                    if (!item) {
                                        if($link) { $link.href = '#'; $link.innerText = '---'; }
                                        ['7d','30d','90d','overall'].forEach(function(p) {
                                            ['clicks','impr','pos'].forEach(function(k){
                                                var el = document.getElementById('sc-pd-'+p+'-'+k);
                                                if(el) el.innerText = '0';
                                            });
                                            var elc = document.getElementById('sc-pd-'+p+'-ctr');
                                            if(elc) elc.innerText = '0%';
                                        });
                                        return;
                                    }
                                    if($link) { $link.href = url; $link.innerText = url; }
                                    ['7d','30d','90d','overall'].forEach(function(p) {
                                        if(document.getElementById('sc-pd-'+p+'-clicks')) document.getElementById('sc-pd-'+p+'-clicks').innerText = item.stats[p].clicks;
                                        if(document.getElementById('sc-pd-'+p+'-impr')) document.getElementById('sc-pd-'+p+'-impr').innerText = item.stats[p].impr;
                                        if(document.getElementById('sc-pd-'+p+'-ctr')) document.getElementById('sc-pd-'+p+'-ctr').innerText = item.stats[p].ctr;
                                        if(document.getElementById('sc-pd-'+p+'-pos')) document.getElementById('sc-pd-'+p+'-pos').innerText = item.stats[p].pos;
                                    });
                                }

                                // Rebuild item map + type dropdown from the SC row cache
                                window.loadSCPDDropdown = function loadSCPDDropdown() {
                                    var rows = (window.seoTabRowCache && window.seoTabRowCache['sc']) || [];
                                    var $sel = document.getElementById('seo-sc-pd-url-select');
                                    if(!$sel) return;

                                    var currentVal = $sel.value;
                                    scAllItems = {};
                                    scPdDataMap = {};

                                    rows.forEach(function(r) {
                                        var type = r.type || 'other';
                                        var d = r.data || {};
                                        var stats = {
                                            '7d':      { clicks: (d['7d']||{}).clicks||'0',      impr: (d['7d']||{}).impressions||'0',      ctr: (d['7d']||{}).ctr||'0%',      pos: (d['7d']||{}).position||'0' },
                                            '30d':     { clicks: (d['30d']||{}).clicks||'0',     impr: (d['30d']||{}).impressions||'0',     ctr: (d['30d']||{}).ctr||'0%',     pos: (d['30d']||{}).position||'0' },
                                            '90d':     { clicks: (d['90d']||{}).clicks||'0',     impr: (d['90d']||{}).impressions||'0',     ctr: (d['90d']||{}).ctr||'0%',     pos: (d['90d']||{}).position||'0' },
                                            'overall': { clicks: (d['overall']||{}).clicks||'0', impr: (d['overall']||{}).impressions||'0', ctr: (d['overall']||{}).ctr||'0%', pos: (d['overall']||{}).position||'0' }
                                        };

                                        var title = r.title || r.url || '';
                                        if (!title || title === r.url) {
                                            try {
                                                var u = new URL(r.url);
                                                var path = u.pathname.replace(/\/$/, '').split('/').pop();
                                                title = path ? path.replace(/-/g, ' ') : 'Home';
                                                title = title.charAt(0).toUpperCase() + title.slice(1);
                                            } catch(e) { title = r.url; }
                                        }

                                        if (!scAllItems[type]) scAllItems[type] = [];
                                        scAllItems[type].push({ url: r.url, title: title });
                                        scPdDataMap[r.url] = { url: r.url, title: title, stats: stats };
                                    });

                                    var types = Object.keys(scAllItems).sort();
                                    buildSCTypeButtons(types);
                                    scRenderDropdown();

                                    if (currentVal && scPdDataMap[currentVal]) $sel.value = currentVal;
                                };

                                (function(){
                                    var $search  = document.getElementById('seo-sc-pd-search');
                                    var $results = document.getElementById('seo-sc-pd-results');
                                    var $clear   = document.getElementById('seo-sc-pd-search-clear');
                                    var $sel     = document.getElementById('seo-sc-pd-url-select');
                                    var searchTimer;

                                    if ($search) {
                                        $search.addEventListener('input', function() {
                                            clearTimeout(searchTimer);
                                            var q = this.value;
                                            searchTimer = setTimeout(function() {
                                                scShowLiveResults(q);
                                                scRenderDropdown();
                                            }, 150);
                                        });
                                        $search.addEventListener('focus', function() {
                                            this.style.borderColor = 'var(--cc-primary)';
                                            if (this.value) scShowLiveResults(this.value);
                                        });
                                        $search.addEventListener('blur', function() {
                                            this.style.borderColor = 'var(--cc-border)';
                                            setTimeout(function() {
                                                if ($results) $results.style.display = 'none';
                                            }, 180);
                                        });
                                        $search.addEventListener('keydown', function(e) {
                                            if (e.key === 'Escape') {
                                                if ($results) $results.style.display = 'none';
                                                this.blur();
                                            }
                                        });
                                    }

                                    if ($clear) {
                                        $clear.addEventListener('click', function() {
                                            if ($search) { $search.value = ''; $search.focus(); }
                                            if ($results) $results.style.display = 'none';
                                            this.style.display = 'none';
                                            scRenderDropdown();
                                            if ($sel) $sel.value = '';
                                            scApplyStats('');
                                        });
                                    }

                                    if ($sel) {
                                        $sel.addEventListener('change', function() {
                                            var url = this.value;
                                            if (!url) { scApplyStats(''); return; }
                                            var item = scPdDataMap[url];
                                            if ($search) $search.value = item && item.title !== item.url ? item.title : url;
                                            if ($clear) $clear.style.display = 'block';
                                            scApplyStats(url);
                                        });
                                    }

                                    document.addEventListener('click', function(e) {
                                        var wrap = document.getElementById('seo-sc-pd-search-wrap');
                                        if (wrap && !wrap.contains(e.target) && $results) {
                                            $results.style.display = 'none';
                                        }
                                    });
                                })();
                            })();

                            setTimeout(function(){
                                seoUpdateAnaChart();
                                seoUpdateSCChart();
                            }, 1000);
                        });
                        </script>


            <!-- ── Search Console ─────────────────────────── -->
            <?php if ($show['sc'] ?? true) : ?>
            <div class="seo-cl-panel-tab" data-tab="sc" id="seo-cl-tab-sc" <?php echo $first_visible!=='sc'?'style="display:none;"':''; ?>>
                
                <!-- Timeperiod & Export Bar -->
                <div class="seo-cl-panel" style="margin-bottom: 20px;">
                    <div style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size:16px;">📅</span>
                                <button type="button" class="seo-sc-tp-btn" data-period="7d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">7 Days</button>
                                <button type="button" class="seo-sc-tp-btn" data-period="30d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">30 Days</button>
                                <button type="button" class="seo-sc-tp-btn" data-period="90d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">90 Days</button>
                                <button type="button" class="seo-sc-tp-btn active" data-period="overall" style="padding: 6px 14px; border: 1px solid var(--cc-primary); background: var(--cc-primary); color: #fff; border-radius: 100px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; min-width: 100px;">Overall</button>
                            </div>
                        </div>
                        <button type="button" class="seo-cl-export-btn" data-table="seo-cl-sc-table" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: var(--cc-surf2); border-radius: 100px; font-size: 12px; font-weight: 700; color: var(--cc-text); cursor: pointer; display: flex; align-items: center; gap: 6px;">📥 Export CSV</button>
                    </div>
                </div>

                <!-- KPI Cards -->
                <?php
                // SC KPI card config (admin: Client Dashboard → Search Console → KPI Cards)
                $scn_kpi_defs = [
                    'clicks'      => ['icon'=>'🖱️','color'=>'#6366f1','label'=>'CLICKS',      'valid'=>'kpi-sc-clicks'],
                    'impressions' => ['icon'=>'👁️','color'=>'#10b981','label'=>'IMPRESSIONS', 'valid'=>'kpi-sc-impr'],
                    'ctr'         => ['icon'=>'📈','color'=>'#f59e0b','label'=>'AVG CTR',     'valid'=>'kpi-sc-ctr'],
                    'urls'        => ['icon'=>'🔗','color'=>'#ec4899','label'=>'TOTAL URLS',  'valid'=>'kpi-sc-urls'],
                ];
                $scn_kpi_show = [];
                foreach ($scn_kpi_defs as $skk => $skv) {
                    $saved = is_array($scn_kpi_cfg[$skk] ?? null) ? $scn_kpi_cfg[$skk] : [];
                    $scn_kpi_show[$skk] = [
                        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
                        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $skv['label'],
                    ];
                }
                ?>
                <?php if ($scn_show['kpis']) : ?>
                <div class="seo-ov3-kpi-row">
                    <?php foreach ($scn_kpi_defs as $skk => $skv) : if (!$scn_kpi_show[$skk]['show']) continue; ?>
                    <div class="seo-ov3-kpi" style="--kc:<?php echo esc_attr($skv['color']); ?>;">
                        <div class="seo-ov3-kpi-icon" style="--kc:<?php echo esc_attr($skv['color']); ?>;"><?php echo $skv['icon']; ?></div>
                        <div class="seo-ov3-kpi-label"><?php echo esc_html($scn_kpi_show[$skk]['label']); ?></div>
                        <div class="seo-ov3-kpi-val" id="<?php echo esc_attr($skv['valid']); ?>">--</div>
                        <div class="seo-ov3-kpi-desc seo-sc-kpi-period-label">Overall</div>
                        <div class="seo-ov3-kpi-bar" style="--kc:<?php echo esc_attr($skv['color']); ?>;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php
                $sc_charts_saved = get_option("seo_dash_charts_sc_{$rid}", []);
                $sc_chart_type  = seo_dash_get_chart_type_saved($sc_charts_saved, 'performance', 'area');
                $sc_chart_title = seo_dash_get_chart_title_saved($sc_charts_saved, 'performance', '📊 Performance Chart');
                $sc_chart_show  = seo_dash_get_chart_show_saved($sc_charts_saved, 'performance', true);
                ?>
                <!-- Performance Chart Section -->
                <div class="seo-cl-panel" id="seo-sc-chart-container" style="margin-bottom: 20px; <?php echo ( empty($sc_opt['show']) || empty($scn_show['chart']) || !$sc_chart_show ) ? 'display:none;' : ''; ?>">
                    <div class="seo-cl-panel-hd" style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="display:flex; align-items:center; gap:8px;"><?php echo esc_html($sc_chart_title); ?></h3>
                        <div style="display:flex; gap:12px; align-items:center;">
                            <div class="seo-chart-type-toggle-group" data-target="seo-sc-chart" style="display:inline-flex; background:var(--cc-surf2); border:1px solid var(--cc-border); border-radius:8px; padding:2px; gap:2px;">
                                <button type="button" class="seo-ct-btn active" data-type="area" title="Curve Area (Image 1)" style="padding:4px 10px; border:none; background:var(--cc-primary); color:#fff; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">📈 Area</button>
                                <button type="button" class="seo-ct-btn" data-type="bar" title="Vertical Bar" style="padding:4px 10px; border:none; background:transparent; color:var(--cc-muted); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">📊 Bar</button>
                                <button type="button" class="seo-ct-btn" data-type="horizontalBar" title="Horizontal Bar" style="padding:4px 10px; border:none; background:transparent; color:var(--cc-muted); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">⏸️ Horiz</button>
                            </div>
                            <select id="seo-sc-chart-metric" style="padding:4px 8px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:12px; cursor:pointer;">
                                <option value="clicks" <?php selected($sc_opt['metric'] ?? '', 'clicks'); ?>>Clicks</option>
                                <option value="impressions" <?php selected($sc_opt['metric'] ?? '', 'impressions'); ?>>Impressions</option>
                                <option value="ctr" <?php selected($sc_opt['metric'] ?? '', 'ctr'); ?>>CTR</option>
                            </select>
                        </div>
                    </div>
                    <div class="seo-cl-panel-body" style="padding:20px; height: 300px; position:relative;">
                        <canvas id="seo-sc-chart" data-color="<?php echo esc_attr($sc_opt['color'] ?? '#8b5cf6'); ?>" data-chart-type="<?php echo esc_attr($sc_chart_type); ?>"></canvas>
                    </div>
                </div>

                <?php if ($scn_show['table']) : ?>
                <div class="seo-cl-panel">
                    <div style="padding:16px 20px; border-bottom:1px solid var(--cc-border); display:flex; justify-content:space-between; align-items:center; background:var(--cc-surf);">
                        <div style="font-weight:700; color:var(--cc-text); font-size:14px; display:flex; align-items:center; gap:8px;">
                            📋 All Pages
                        </div>
                        <div style="display:flex; align-items:center;" id="seo-sc-type-filters">
                            <?php
                            // Build dynamic type filter dropdown from the sitemap type map (SC)
                            $_sc_type_map = get_option("seo_dash_sitemap_types_{$rid}_sc", []);
                            if (!is_array($_sc_type_map)) $_sc_type_map = [];
                            $_excluded_client_types_sc = ['gmb_posts', 'gmb_post'];
                            $_sc_types = array_unique(array_values($_sc_type_map));
                            $_sc_types = array_filter($_sc_types, fn($t) => $t && !in_array($t, $_excluded_client_types_sc, true));
                            sort($_sc_types);
                            // Count URLs per type (and grand total) from the sitemap type map
                            $_sc_type_counts = [];
                            foreach ($_sc_type_map as $_smap_url => $_smap_type) {
                                if (in_array($_smap_type, $_excluded_client_types_sc, true)) continue;
                                $_sc_type_counts[$_smap_type] = ($_sc_type_counts[$_smap_type] ?? 0) + 1;
                            }
                            $_sc_total_count = array_sum($_sc_type_counts);
                            if (!isset($_type_icons) || !is_array($_type_icons)) {
                                $_type_icons = [
                                    'all'       => '🌍',
                                    'page'      => '📄',
                                    'service'   => '🛠️',
                                    'location'  => '📍',
                                    'city'      => '🏙️',
                                    'product'   => '🛍️',
                                    'portfolio' => '🖼️',
                                    'post'      => '✍️',
                                    'blog'      => '📰',
                                    'category'  => '🗂️',
                                    'article'   => '📝',
                                    'news'      => '📡',
                                    'tag'       => '🏷️',
                                    'author'    => '👤',
                                    'other'     => '📁',
                                ];
                            }
                            ?>
                            <select id="seo-sc-type-select" style="padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; background:var(--cc-surf2); color:var(--cc-text); border:1px solid var(--cc-border); outline:none; min-width:140px;">
                                <option value="all">🌍 All Types (<?php echo intval($_sc_total_count); ?>)</option>
                                <?php foreach ($_sc_types as $_stype) :
                                    $_icon = $_type_icons[$_stype] ?? '📁';
                                    $_label = ucfirst($_stype);
                                    $_count = $_sc_type_counts[$_stype] ?? 0;
                                ?>
                                <option value="<?php echo esc_attr($_stype); ?>"><?php echo $_icon . ' ' . esc_html($_label) . ' (' . intval($_count) . ')'; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <script>window.seoSCTypeMap = window.seoSCTypeMap || {}; window.seoSCTypeMap[<?php echo intval($rid); ?>] = <?php echo wp_json_encode($_sc_type_map); ?>;</script>
                    </div>
                    <div class="seo-cl-table-wrap" style="border:none; overflow-x: auto; max-width: 100%;">
                        <table class="seo-cl-table" id="seo-cl-sc-table" style="min-width: 1200px; border-collapse: separate; border-spacing: 0;">
                            <thead style="background:var(--cc-surf2); position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th rowspan="2" style="width:40px;text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">#</th>
                                    <th rowspan="2" style="width:180px;vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">PAGE</th>
                                    <th rowspan="2" style="width:40px;text-align:center;vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">View</th>
                                    <th colspan="4" data-col="7d" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);color:#0ea5e9;font-weight:800;font-size:10px;">7 DAYS</th>
                                    <th colspan="4" data-col="30d" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);color:#8b5cf6;font-weight:800;font-size:10px;">30 DAYS</th>
                                    <th colspan="4" data-col="90d" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);color:#10b981;font-weight:800;font-size:10px;">90 DAYS</th>
                                    <th colspan="4" data-col="overall" style="text-align:center;border-bottom:1px solid var(--cc-border);color:#f59e0b;font-weight:800;font-size:10px;">OVERALL</th>
                                </tr>
                                <tr>
                                    <th data-col="7d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">CLICKS</th>
                                    <th data-col="7d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">IMPR</th>
                                    <th data-col="7d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">CTR</th>
                                    <th data-col="7d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;border-right:1px solid var(--cc-border);">POS</th>
                                    
                                    <th data-col="30d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">CLICKS</th>
                                    <th data-col="30d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">IMPR</th>
                                    <th data-col="30d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">CTR</th>
                                    <th data-col="30d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;border-right:1px solid var(--cc-border);">POS</th>

                                    <th data-col="90d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">CLICKS</th>
                                    <th data-col="90d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">IMPR</th>
                                    <th data-col="90d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">CTR</th>
                                    <th data-col="90d" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;border-right:1px solid var(--cc-border);">POS</th>

                                    <th data-col="overall" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">CLICKS</th>
                                    <th data-col="overall" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">IMPR</th>
                                    <th data-col="overall" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">CTR</th>
                                    <th data-col="overall" style="font-size:10px;text-align:right;border-bottom:1px solid var(--cc-border);padding:8px 4px;">POS</th>
                                </tr>
                            </thead>
                            <tbody class="seo-cl-tbody"><tr><td colspan="19" style="text-align:center;padding:32px;color:var(--cc-subtle);">Loading...</td></tr></tbody>
                        </table>
                    </div>
                    <div class="seo-cl-tab-pagination" style="display:none;padding:16px 20px;border-top:1px solid var(--cc-border);text-align:center;"></div>
                </div>
                <?php endif; ?>

                <?php if ($scn_show['pagedetail']) : ?>
                <!-- Page Detail Section for SC -->
                <div class="seo-cl-panel" id="seo-sc-page-detail" style="margin-top: 30px;">
                    <div class="seo-cl-panel-hd" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <h3 style="display:flex; align-items:center; gap:8px;">🔎 Page Detail</h3>
                        <!-- Dynamic type filter dropdown — filled by JS -->
                        <div id="seo-sc-pd-type-toggle"></div>
                    </div>
                    <div class="seo-cl-panel-body" style="padding:20px;">
                        <!-- Search + Select in one row -->
                        <div style="display:flex; gap:10px; align-items:center; margin-bottom:20px; flex-wrap:wrap;">
                            <!-- Search input (live overlay) -->
                            <div style="position:relative; flex:1 1 0; min-width:0;" id="seo-sc-pd-search-wrap">
                                <span style="position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:13px; pointer-events:none; z-index:1;">🔍</span>
                                <input type="text" id="seo-sc-pd-search" autocomplete="off" placeholder="Search..." style="width:100%; padding:10px 32px 10px 32px; border-radius:10px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:13px; outline:none; box-sizing:border-box; transition:border-color 0.2s;" />
                                <span id="seo-sc-pd-search-clear" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:15px; cursor:pointer; color:var(--cc-muted); display:none; line-height:1;">✕</span>
                                <!-- Live results overlay -->
                                <div id="seo-sc-pd-results" style="display:none; position:absolute; top:calc(100% + 4px); left:0; width:340px; background:var(--cc-surf, #1e2130); border:1px solid var(--cc-border); border-radius:10px; max-height:260px; overflow-y:auto; z-index:9999; box-shadow:0 8px 32px rgba(0,0,0,0.6); backdrop-filter:none; opacity:1;"></div>
                            </div>
                            <!-- Select dropdown (filtered by search) -->
                            <select id="seo-sc-pd-url-select" style="flex:1 1 0; min-width:0; padding:10px 14px; border-radius:10px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:13px; font-weight:600; cursor:pointer; outline:none; transition:all 0.2s; box-sizing:border-box;">
                                <option value="">Select a page...</option>
                            </select>
                        </div>
                        <div id="seo-sc-pd-selected-url" style="margin-bottom:20px; font-size:13px; display:flex; align-items:center; gap:6px; color:var(--cc-primary);">
                            <span style="font-size:16px;">🔗</span> <a href="#" target="_blank" style="color:inherit; text-decoration:none; font-weight:600;">---</a>
                        </div>
                        
                        <!-- SC PD Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                            <?php 
                            $sc_periods = [
                                '7d' => ['label' => '7 Days', 'icon' => '📅', 'color' => '#0ea5e9'],
                                '30d' => ['label' => '30 Days', 'icon' => '📅', 'color' => '#8b5cf6'],
                                '90d' => ['label' => '90 Days', 'icon' => '📅', 'color' => '#10b981'],
                                'overall' => ['label' => 'Overall', 'icon' => '📊', 'color' => '#f59e0b']
                            ];
                            foreach ($sc_periods as $pk => $pv): ?>
                            <div class="seo-pd-card" style="background:<?php echo $pv['color']; ?>0C; border:1px solid <?php echo $pv['color']; ?>; border-radius:12px; padding:20px;">
                                <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:16px;">
                                    <span style="font-weight:800; font-size:15px; color:<?php echo $pv['color']; ?>;"><span style="color:inherit; font-size:16px; margin-right:4px;"><?php echo $pv['icon']; ?></span> <?php echo $pv['label']; ?></span>
                                    <div style="font-size:12px; color:var(--cc-muted); display:flex; align-items:center; gap:6px;">
                                        <span style="color:<?php echo $pv['color']; ?>; font-size:14px;">📅</span> <span id="sc-pd-<?php echo $pk; ?>-date">YYYY-MM-DD &ndash; YYYY-MM-DD</span>
                                    </div>
                                </div>
                                <div style="display:flex; flex-direction:column; gap:12px; margin-bottom: 20px;">
                                    <div style="display:flex; justify-content:space-between; font-size:13px; padding-bottom: 8px; border-bottom: 1px solid <?php echo $pv['color']; ?>30;">
                                        <span style="color:var(--cc-muted);">Clicks</span>
                                        <span style="font-weight:800; color:<?php echo $pv['color']; ?>; font-size:15px;" id="sc-pd-<?php echo $pk; ?>-clicks">0</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:13px; padding-bottom: 8px; border-bottom: 1px solid <?php echo $pv['color']; ?>30;">
                                        <span style="color:var(--cc-muted);">Impressions</span>
                                        <span style="font-weight:800; color:<?php echo $pv['color']; ?>; font-size:15px;" id="sc-pd-<?php echo $pk; ?>-impr">0</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:13px; padding-bottom: 8px; border-bottom: 1px solid <?php echo $pv['color']; ?>30;">
                                        <span style="color:var(--cc-muted);">CTR</span>
                                        <span style="font-weight:800; color:<?php echo $pv['color']; ?>; font-size:15px;" id="sc-pd-<?php echo $pk; ?>-ctr">0%</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:13px; padding-bottom: 8px; border-bottom: 1px solid <?php echo $pv['color']; ?>30;">
                                        <span style="color:var(--cc-muted);">Avg Position</span>
                                        <span style="font-weight:800; color:<?php echo $pv['color']; ?>; font-size:15px;" id="sc-pd-<?php echo $pk; ?>-pos">0</span>
                                    </div>
                                </div>
                                <!-- Top Queries -->
                                <div style="font-size:14px; font-weight:800; color:var(--cc-text); margin-bottom:12px;">Top Queries</div>
                                <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;" id="sc-pd-<?php echo $pk; ?>-queries">
                                    <div style="display:flex; justify-content:space-between;">
                                        <span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;" title="how to add oil to ac compressor">how to add oil to ac com...</span>
                                        <span style="font-weight:800; color:<?php echo $pv['color']; ?>;">2</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;" title="new hot water heater broken">new hot water heater bro...</span>
                                        <span style="font-weight:800; color:<?php echo $pv['color']; ?>;">2</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;" title="what does a condenser look like">what does a condenser l...</span>
                                        <span style="font-weight:800; color:<?php echo $pv['color']; ?>;">2</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;" title="3.5 ton ac unit price">3.5 ton ac unit price</span>
                                        <span style="font-weight:800; color:<?php echo $pv['color']; ?>;">1</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;" title="ac companies near me">ac companies near me</span>
                                        <span style="font-weight:800; color:<?php echo $pv['color']; ?>;">1</span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ── Service Pages ──────────────────────────── -->
            <?php if ($show['service']) : ?>
            <div class="seo-cl-panel-tab" data-tab="service" id="seo-cl-tab-service" <?php echo $first_visible!=='service'?'style="display:none;"':''; ?>>
                <?php
                // Service Pages Data Extraction
                $svc_map = get_option("seo_dash_custom_pages_{$rid}_service", []);
                if (!isset($seo_dash_service_types)) {
                    // Blog types are the whitelist — everything else is a service/page type.
                    // This means author, product, page, service, location, city, portfolio,
                    // other, and any future sitemap types all count as service pages.
                    $seo_dash_blog_types    = ['post','blog','category','article','news','tag'];
                    // Service types = NOT blog types. We keep the array for count helper use.
                    $seo_dash_service_types = ['page','service','location','city','product','portfolio','other','author'];
                }
                // TOTAL: count all URLs that are NOT blog types (service = everything except blog)
                // This includes page, product, author, location, city, portfolio, other, etc.
                // Count directly from the sitemap type map so the total reflects every mapped
                // URL — including ones that don't have GA data rows yet.
                $_ga_type_map_sp = get_option("seo_dash_sitemap_types_{$rid}_ga", []);
                if (!is_array($_ga_type_map_sp)) $_ga_type_map_sp = [];
                $_excluded_total_types_sp = ['gmb_posts', 'gmb_post'];
                $kpi_sp_total = 0;
                foreach ($_ga_type_map_sp as $_sp_url => $_sp_type) {
                    if (in_array($_sp_type, $_excluded_total_types_sp, true)) continue;
                    if (!in_array($_sp_type, $seo_dash_blog_types, true)) $kpi_sp_total++;
                }
                // Also count custom_pages service entries not in the type_map
                if (is_array($svc_map)) {
                    foreach ($svc_map as $_sp) {
                        if (empty($_sp['url']) || !empty($_sp['trashed'])) continue;
                        if (!isset($_ga_type_map_sp[$_sp['url']])) $kpi_sp_total++;
                    }
                    unset($_sp);
                }
                // Ranked / AI: from custom_pages only (those fields live there)
                $kpi_sp_p1 = 0;
                $kpi_sp_p2 = 0;
                $kpi_sp_p3_plus = 0;
                $kpi_sp_ai = 0;

                if (is_array($svc_map)) {
                    foreach ($svc_map as $_p) {
                        if (!empty($_p['trashed'])) continue;
                        $r = intval($_p['ranked_page'] ?? 0);
                        if ($r === 1) $kpi_sp_p1++;
                        elseif ($r === 2) $kpi_sp_p2++;
                        elseif ($r >= 3) $kpi_sp_p3_plus++;
                        if (!empty($_p['ai_overview'])) $kpi_sp_ai++;
                    }
                }
                ?>
                
                <!-- Timeperiod Bar -->
                <div class="seo-cl-panel" style="margin-bottom: 20px;">
                    <div style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size:16px;">📅</span>
                                <button type="button" class="seo-sp-tp-btn" data-period="7d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">7 Days</button>
                                <button type="button" class="seo-sp-tp-btn" data-period="30d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">30 Days</button>
                                <button type="button" class="seo-sp-tp-btn" data-period="90d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">90 Days</button>
                                <button type="button" class="seo-sp-tp-btn active" data-period="overall" style="padding: 6px 14px; border: 1px solid var(--cc-primary); background: var(--cc-primary); color: #fff; border-radius: 100px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; min-width: 100px;">Overall</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards -->
                <?php
                // Service Pages KPI card config (admin: Client Dashboard → Service Pages → KPI Cards)
                $sp_kpi_defs = [
                    'total'   => ['icon'=>'🏆','color'=>'#6366f1','label'=>'TOTAL PAGES',  'desc'=>'All pages',        'val'=>$kpi_sp_total,    'valid'=>null],
                    'p1'      => ['icon'=>'🥇','color'=>'#10b981','label'=>'PAGE 1',       'desc'=>'Ranked page 1',    'val'=>$kpi_sp_p1,       'valid'=>null],
                    'p2'      => ['icon'=>'🥈','color'=>'#f59e0b','label'=>'PAGE 2',       'desc'=>'Ranked page 2',    'val'=>$kpi_sp_p2,       'valid'=>null],
                    'p3plus'  => ['icon'=>'📊','color'=>'#64748b','label'=>'PAGE 3+',      'desc'=>'Page 3 or beyond', 'val'=>$kpi_sp_p3_plus,  'valid'=>null],
                    'ai'      => ['icon'=>'🤖','color'=>'#ec4899','label'=>'AI OVERVIEW',  'desc'=>'AI featured',      'val'=>$kpi_sp_ai,       'valid'=>null],
                    'traffic' => ['icon'=>'📈','color'=>'#06b6d4','label'=>'TOTAL TRAFFIC','desc'=>'All-time visits',  'val'=>'--',             'valid'=>'kpi-sp-traffic'],
                ];
                $sp_kpi_show = [];
                foreach ($sp_kpi_defs as $pkk => $pkv) {
                    $saved = is_array($sp_kpi_cfg[$pkk] ?? null) ? $sp_kpi_cfg[$pkk] : [];
                    $sp_kpi_show[$pkk] = [
                        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
                        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $pkv['label'],
                    ];
                }
                ?>
                <?php if ($sp_show['kpis']) : ?>
                <div class="seo-ov3-kpi-row">
                    <?php foreach ($sp_kpi_defs as $pkk => $pkv) : 
                        if (!$sp_kpi_show[$pkk]['show']) continue;
                        $sp_is_zero_rank = (in_array($pkk, ['p1', 'p2', 'p3plus', 'ai'], true) && empty($pkv['val']));
                    ?>
                    <div class="seo-ov3-kpi" style="--kc:<?php echo esc_attr($pkv['color']); ?>;<?php echo $sp_is_zero_rank ? 'display:none;' : ''; ?>">
                        <div class="seo-ov3-kpi-icon" style="--kc:<?php echo esc_attr($pkv['color']); ?>;"><?php echo $pkv['icon']; ?></div>
                        <div class="seo-ov3-kpi-label"><?php echo esc_html($sp_kpi_show[$pkk]['label']); ?></div>
                        <div class="seo-ov3-kpi-val"<?php echo $pkv['valid'] ? ' id="'.esc_attr($pkv['valid']).'"' : ''; ?>><?php echo esc_html($pkv['val']); ?></div>
                        <div class="seo-ov3-kpi-desc"><?php echo esc_html($pkv['desc']); ?></div>
                        <div class="seo-ov3-kpi-bar" style="--kc:<?php echo esc_attr($pkv['color']); ?>;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php
                $sp_charts_saved = get_option("seo_dash_charts_service_{$rid}", []);
                $sp_chart_type  = seo_dash_get_chart_type_saved($sp_charts_saved, 'performance', 'area');
                $sp_chart_title = seo_dash_get_chart_title_saved($sp_charts_saved, 'performance', '📊 Traffic Chart');
                $sp_chart_show  = seo_dash_get_chart_show_saved($sp_charts_saved, 'performance', true);
                ?>
                <!-- Traffic Chart Section -->
                <div class="seo-cl-panel" id="seo-sp-chart-container" style="margin-bottom: 20px; <?php echo ( empty($ac_opt['show']) || empty($sp_show['chart']) || !$sp_chart_show ) ? 'display:none;' : ''; ?>">
                    <div class="seo-cl-panel-hd" style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="display:flex; align-items:center; gap:8px;"><?php echo esc_html($sp_chart_title); ?></h3>
                        <div style="display:flex; gap:12px; align-items:center;">
                            <div class="seo-chart-type-toggle-group" data-target="seo-sp-chart" style="display:inline-flex; background:var(--cc-surf2); border:1px solid var(--cc-border); border-radius:8px; padding:2px; gap:2px;">
                                <button type="button" class="seo-ct-btn active" data-type="area" title="Curve Area (Image 1)" style="padding:4px 10px; border:none; background:var(--cc-primary); color:#fff; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">📈 Area</button>
                                <button type="button" class="seo-ct-btn" data-type="bar" title="Vertical Bar" style="padding:4px 10px; border:none; background:transparent; color:var(--cc-muted); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">📊 Bar</button>
                                <button type="button" class="seo-ct-btn" data-type="horizontalBar" title="Horizontal Bar" style="padding:4px 10px; border:none; background:transparent; color:var(--cc-muted); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">⏸️ Horiz</button>
                            </div>
                            <select id="seo-sp-chart-metric" style="padding:4px 8px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:12px; cursor:pointer;">
                                <option value="sessions" <?php selected($ac_opt['metric'] ?? '', 'sessions'); ?>>Sessions</option>
                                <option value="users" <?php selected($ac_opt['metric'] ?? '', 'users'); ?>>Active Users</option>
                                <option value="views" <?php selected($ac_opt['metric'] ?? '', 'views'); ?>>Page Views</option>
                            </select>
                        </div>
                    </div>
                    <div class="seo-cl-panel-body" style="padding:20px; height: 300px; position:relative;">
                        <canvas id="seo-sp-chart" data-color="<?php echo esc_attr($ac_opt['color'] ?? '#8b5cf6'); ?>" data-chart-type="<?php echo esc_attr($sp_chart_type); ?>"></canvas>
                    </div>
                </div>

                <script>
                function seoUpdateSPKPIs(period) {
                    var fullCache = window.seoTabFullDataCache || {};
                    var rows = fullCache['service_current'] || (window.seoTabRowCache && window.seoTabRowCache['service']) || [];
                    var sumUsers = 0;

                    rows.forEach(function(r) {
                        var d = r.data || {};
                        var p = d[period] || null;
                        if (!p) return;
                        sumUsers += parseInt(p.users || 0);
                    });

                    if (document.getElementById('kpi-sp-traffic')) document.getElementById('kpi-sp-traffic').innerText = sumUsers.toLocaleString();

                    var table = document.getElementById('seo-cl-service-table');
                    if (table) {
                        var allCols = table.querySelectorAll('th[data-col], td[data-col]');
                        allCols.forEach(function(c) { c.style.background = ''; c.classList.remove('seo-col-highlight'); });
                        var activeCols = table.querySelectorAll('th[data-col="'+period+'"], td[data-col="'+period+'"]');
                        var highlightColor = 'var(--cc-surf2)';
                        if (period === '30d') highlightColor = 'rgba(139,92,246,0.05)';
                        else if (period === '7d') highlightColor = 'rgba(14,165,233,0.05)';
                        else if (period === '90d') highlightColor = 'rgba(16,185,129,0.05)';
                        else if (period === 'overall') highlightColor = 'rgba(245,158,11,0.05)';
                        activeCols.forEach(function(c) { c.style.background = highlightColor; c.classList.add('seo-col-highlight'); });
                    }
                }

                var spChart = null;
                function seoUpdateSPChart() {
                    var el = document.getElementById('seo-sp-chart');
                    if(!el) return;

                    var activeBtn = document.querySelector('.seo-sp-tp-btn.active');
                    var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                    var $mSel = document.getElementById('seo-sp-chart-metric');
                    var metric = $mSel ? $mSel.value : 'sessions';
                    var type = el.dataset.chartType || 'doughnut';
                    var color = el.dataset.color || '#8b5cf6';

                    var fullCache = window.seoTabFullDataCache || {};
                    var rows = fullCache['service_current'] || fullCache['service_service'] || fullCache['service_all'] || (window.seoTabRowCache && window.seoTabRowCache['service']) || [];
                    var mKey = { 'users': 'users', 'sessions': 'sessions', 'views': 'pageviews' }[metric] || 'sessions';

                    var data = [];
                    rows.forEach(function(r) {
                        var d = r.data || {};
                        var p = (d && d[period]) ? d[period] : ((d && (d[mKey] !== undefined || d.sessions !== undefined)) ? d : r);
                        var val = parseInt((p && p[mKey] !== undefined) ? p[mKey] : (p ? (p.sessions || p.users || p.pageviews || 0) : 0));
                        var title = r.title || r.url || '';
                        if (!title || title === r.url) {
                            try {
                                var u = new URL(r.url);
                                var path = u.pathname.replace(/\/$/, '').split('/').pop();
                                title = path ? path.replace(/-/g, ' ') : 'Home';
                                title = title.charAt(0).toUpperCase() + title.slice(1);
                            } catch(e) { title = r.url || 'Page'; }
                        }
                        if (val > 0) data.push({ label: title, value: val });
                    });

                    if (data.length === 0 && rows.length > 0) {
                        rows.slice(0, 10).forEach(function(r) {
                            var title = r.title || r.url || 'Page';
                            if (title.indexOf('http') === 0) {
                                try {
                                    var u = new URL(r.url);
                                    var path = u.pathname.replace(/\/$/, '').split('/').pop();
                                    title = path ? path.replace(/-/g, ' ') : 'Home';
                                    title = title.charAt(0).toUpperCase() + title.slice(1);
                                } catch(e) { title = r.url; }
                            }
                            data.push({ label: title, value: 0 });
                        });
                    }

                    // Select top 10, then sort low to high so performance curve climbs UPWARDS from left to right
                    data.sort(function(a,b){ return b.value - a.value; });
                    data = data.slice(0, 10);
                    data.sort(function(a,b){ return a.value - b.value; });

                    var labels = data.map(function(d){ return d.label; });
                    var values = data.map(function(d){ return d.value; });

                    if(spChart) spChart.destroy();
                    spChart = window.seoBuildUniversalChart(el, {
                        type: type,
                        labels: labels,
                        values: values,
                        color: color,
                        labelName: metric.charAt(0).toUpperCase() + metric.slice(1)
                    });
                }

                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.seo-sp-tp-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            document.querySelectorAll('.seo-sp-tp-btn').forEach(function(b){
                                b.classList.remove('active');
                                b.style.background = 'transparent';
                                b.style.color = 'var(--cc-text)';
                                b.style.borderColor = 'var(--cc-border)';
                            });
                            this.classList.add('active');
                            this.style.background = 'var(--cc-primary)';
                            this.style.color = '#fff';
                            this.style.borderColor = 'var(--cc-primary)';
                            
                            var period = this.getAttribute('data-period');
                            seoUpdateSPKPIs(period);
                            seoUpdateSPChart();
                        });
                    });
                    
                    // Listen to full-data event from client-app.js for Service Pages
                    jQuery(document).on('seo:rowsLoaded', function(e, tab, rows) {
                        if (tab === 'service') {
                            var activeBtn = document.querySelector('.seo-sp-tp-btn.active');
                            var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                            seoUpdateSPKPIs(period);
                            seoUpdateSPChart();
                            if(typeof loadSPPDDropdown === 'function') loadSPPDDropdown();
                        }
                    });

                    var $spChartMetric = document.getElementById('seo-sp-chart-metric');
                    if($spChartMetric) $spChartMetric.addEventListener('change', seoUpdateSPChart);
                });
                
                // SP Page Detail Logic — type filter + live search (mirrors Analytics Page Detail)
                var spPDFilter = null;
                document.addEventListener('DOMContentLoaded', function() {
                    spPDFilter = window.seoInitPageDetailFilter({
                        tab: 'service',
                        prefix: 'seo-sp-pd',
                        buildExtra: function(r) {
                            var d = r.data || {};
                            return {
                                '7d':      { u: (d['7d']||{}).users||'0',      s: (d['7d']||{}).sessions||'0',      v: (d['7d']||{}).pageviews||'0' },
                                '30d':     { u: (d['30d']||{}).users||'0',     s: (d['30d']||{}).sessions||'0',     v: (d['30d']||{}).pageviews||'0' },
                                '90d':     { u: (d['90d']||{}).users||'0',     s: (d['90d']||{}).sessions||'0',     v: (d['90d']||{}).pageviews||'0' },
                                'overall': { u: (d['overall']||{}).users||'0', s: (d['overall']||{}).sessions||'0', v: (d['overall']||{}).pageviews||'0' }
                            };
                        },
                        onSelect: function(url, item) {
                            if (url && item) seoOpenSPPD(url, item);
                            else seoCloseSPPD();
                        }
                    });
                });

                window.loadSPPDDropdown = function() { if (spPDFilter) spPDFilter.refresh(); };

                window.seoOpenSPPD = function(url, item) {
                    if (!item && spPDFilter) item = spPDFilter.getItem(url);
                    if (!item) return;
                    var found = { title: item.title, url: item.url, data: item.extra, row: item.row };
                    
                    document.getElementById('seo-sp-single-view').style.display = 'block';
                    var $sel = document.getElementById('seo-sp-pd-url-select');
                    if($sel) $sel.value = found.url;
                    
                    document.getElementById('seo-sp-pd-title').innerText = found.title;
                    document.getElementById('seo-sp-pd-link').innerText = found.url;
                    document.getElementById('seo-sp-pd-link').href = found.url;
                    
                    var activeBtn = document.querySelector('.seo-sp-tp-btn.active');
                    var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                    
                    var pLabel = period === 'overall' ? 'OVERALL' : (period === '7d' ? '7 DAYS' : (period === '30d' ? '30 DAYS' : '90 DAYS'));
                    document.getElementById('seo-sp-pd-period-label').innerText = pLabel;
                    
                    document.getElementById('seo-sp-pd-val-users').innerText = found.data[period].u;
                    document.getElementById('seo-sp-pd-val-sess').innerText = found.data[period].s;
                    document.getElementById('seo-sp-pd-val-views').innerText = found.data[period].v;
                    
                    var table = document.getElementById('seo-cl-service-table');
                    var rows = table.querySelectorAll('tbody.seo-cl-tbody tr');
                    rows.forEach(function(r){ r.style.background = ''; });
                    if(found.row) found.row.style.background = 'rgba(99,102,241,0.1)';
                };

                window.seoCloseSPPD = function() {
                    document.getElementById('seo-sp-single-view').style.display = 'none';
                    var table = document.getElementById('seo-cl-service-table');
                    if(table){
                        var rows = table.querySelectorAll('tbody.seo-cl-tbody tr');
                        rows.forEach(function(r){ r.style.background = ''; });
                    }
                    var $sel = document.getElementById('seo-sp-pd-url-select');
                    if($sel) $sel.value = '';
                };
                </script>

                <div style="display:flex; gap:20px; align-items:flex-start; margin-bottom: 20px;">
                    <?php if ($sp_show['table']) : ?>
                    <!-- Table Side -->
                    <div class="seo-cl-panel" style="flex:1; overflow:hidden;">
                        <div class="seo-cl-panel-hd" style="display:flex;justify-content:space-between;align-items:center;">
                            <h3>Service Pages Data</h3>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <div style="position:relative;">
                                    <span style="position:absolute; left:9px; top:50%; transform:translateY(-50%); font-size:12px; pointer-events:none; color:var(--cc-muted);">🔍</span>
                                    <input type="text" id="seo-sp-table-search" data-tab="service" autocomplete="off" placeholder="Search pages..." style="padding:5px 10px 5px 28px; border-radius:8px; font-size:12px; background:var(--cc-surf2); color:var(--cc-text); border:1px solid var(--cc-border); outline:none; min-width:160px;" />
                                </div>
                                <div id="seo-sp-subtype-filters" style="display:none;"></div>
                            <?php if (!empty($months_sv)) : ?>
                            <select class="seo-cl-month-sel" data-scope="service" style="padding:4px 8px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:12px; cursor:pointer;">
                                <option value="">All months</option>
                                <?php foreach ($months_sv as $m) : ?>
                                <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html(date_i18n('F Y', strtotime($m.'-01'))); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            </div>
                        </div>
                        <div class="seo-cl-table-wrap" style="max-height:600px;overflow-y:auto;overflow-x:auto;-webkit-overflow-scrolling:touch;">
                            <table class="seo-cl-table" id="seo-cl-service-table" style="width:100%; min-width:1200px;">
                                <thead style="background:var(--cc-surf); position: sticky; top: 0; z-index: 10;">
                                    <tr>
                                        <th rowspan="2" style="width:40px; border-right:1px solid var(--cc-border);"></th>
                                        <th rowspan="2" style="vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--cc-border);">Page</th>
                                        <th rowspan="2" style="width:40px; border-right:1px solid var(--cc-border);"></th>
                                        <th colspan="3" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">7 DAYS</th>
                                        <th colspan="3" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">30 DAYS</th>
                                        <th colspan="3" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">90 DAYS</th>
                                        <th colspan="3" style="text-align:center;border-bottom:1px solid var(--cc-border);">OVERALL</th>
                                    </tr>
                                    <tr>
                                        <?php for($i=0;$i<4;$i++): $br = $i<3 ? 'border-right:1px solid var(--cc-border);' : ''; ?>
                                        <th style="font-size:10px;text-align:center;" data-col="<?php echo ['7d','30d','90d','overall'][$i]; ?>">Users</th>
                                        <th style="font-size:10px;text-align:center;" data-col="<?php echo ['7d','30d','90d','overall'][$i]; ?>">Sess.</th>
                                        <th style="font-size:10px;text-align:center;<?php echo $br;?>" data-col="<?php echo ['7d','30d','90d','overall'][$i]; ?>">Views</th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody class="seo-cl-tbody"><tr><td colspan="15" style="text-align:center;padding:24px;color:var(--cc-subtle);">Loading...</td></tr></tbody>
                            </table>
                        </div>
                        <div class="seo-cl-tab-pagination" style="display:none;padding:16px 20px;border-top:1px solid var(--cc-border);text-align:center;"></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($sp_show['pagedetail']) : ?>
                    <!-- Single Page Side -->
                    <div id="seo-sp-single-view" class="seo-cl-panel" style="width: 380px; flex-shrink: 0; display: none;">
                        <div class="seo-cl-panel-hd" style="display:flex;justify-content:space-between;align-items:center;">
                            <h3 style="font-size:15px;">Page Details</h3>
                            <button type="button" onclick="seoCloseSPPD()" style="background:none;border:none;color:var(--cc-muted);cursor:pointer;padding:4px;">✕</button>
                        </div>
                        <div class="seo-cl-panel-body" style="padding: 20px;">
                            <div style="margin-bottom: 12px;">
                                <label style="display:block; font-size:11px; font-weight:600; color:var(--cc-muted); margin-bottom:8px; text-transform:uppercase;">Filter by Type</label>
                                <div id="seo-sp-pd-type-toggle"></div>
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label style="display:block; font-size:11px; font-weight:600; color:var(--cc-muted); margin-bottom:8px; text-transform:uppercase;">Select Page</label>
                                <div style="position:relative; margin-bottom:8px;" id="seo-sp-pd-search-wrap">
                                    <span style="position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:13px; pointer-events:none; z-index:1;">🔍</span>
                                    <input type="text" id="seo-sp-pd-search" autocomplete="off" placeholder="Search..." style="width:100%; padding:8px 32px 8px 32px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:13px; outline:none; box-sizing:border-box; transition:border-color 0.2s;" />
                                    <span id="seo-sp-pd-search-clear" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:14px; cursor:pointer; color:var(--cc-muted); display:none; line-height:1;">✕</span>
                                    <div id="seo-sp-pd-results" style="display:none; position:absolute; top:calc(100% + 4px); left:0; width:100%; background:var(--cc-surf, #1e2130); border:1px solid var(--cc-border); border-radius:8px; max-height:220px; overflow-y:auto; z-index:9999; box-shadow:0 8px 32px rgba(0,0,0,0.6);"></div>
                                </div>
                                <select id="seo-sp-pd-url-select" style="width:100%; padding:8px 12px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:13px;"></select>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <h4 id="seo-sp-pd-title" style="margin:0 0 4px 0; font-size:16px; color:var(--cc-text); line-height:1.3; word-break:break-word;"></h4>
                                <a id="seo-sp-pd-link" href="#" target="_blank" style="font-size:12px; color:var(--cc-primary); text-decoration:none; word-break:break-all;"></a>
                            </div>

                            <div style="background:var(--cc-surf2); border-radius:8px; padding:16px; margin-bottom:20px;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                                    <span style="font-size:12px; color:var(--cc-muted); font-weight:600;">METRIC</span>
                                    <span style="font-size:12px; color:var(--cc-text); font-weight:700; text-transform:uppercase;" id="seo-sp-pd-period-label">OVERALL</span>
                                </div>
                                <div style="display:flex; flex-direction:column; gap:12px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:#6366f1;"></div>
                                            <span style="font-size:13px; color:var(--cc-text);">Active Users</span>
                                        </div>
                                        <span style="font-size:14px; font-weight:700; color:var(--cc-text);" id="seo-sp-pd-val-users">0</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:#10b981;"></div>
                                            <span style="font-size:13px; color:var(--cc-text);">Sessions</span>
                                        </div>
                                        <span style="font-size:14px; font-weight:700; color:var(--cc-text);" id="seo-sp-pd-val-sess">0</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:#f59e0b;"></div>
                                            <span style="font-size:13px; color:var(--cc-text);">Page Views</span>
                                        </div>
                                        <span style="font-size:14px; font-weight:700; color:var(--cc-text);" id="seo-sp-pd-val-views">0</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Top Queries -->
                            <div style="font-size:14px; font-weight:800; color:var(--cc-text); margin-bottom:12px;">Top Queries</div>
                            <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;">how to add oil to ac com...</span>
                                    <span style="font-weight:800; color:var(--cc-primary);">2</span>
                                </div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;">new hot water heater bro...</span>
                                    <span style="font-weight:800; color:var(--cc-primary);">2</span>
                                </div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;">what does a condenser l...</span>
                                    <span style="font-weight:800; color:var(--cc-primary);">1</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Blog Posts ─────────────────────────────── -->
            <?php if ($show['blog']) : ?>
            <div class="seo-cl-panel-tab" data-tab="blog" id="seo-cl-tab-blog" <?php echo $first_visible!=='blog'?'style="display:none;"':''; ?>>
                <?php
                // Blog Posts Data Extraction
                if (!isset($seo_dash_blog_types)) {
                    $seo_dash_service_types = ['page','service','location','city','product','portfolio','other'];
                    $seo_dash_blog_types    = ['post','blog','category','article','news','tag'];
                }
                // TOTAL BLOG POSTS / CATEGORIES — count directly from the sitemap type map so
                // the totals reflect every mapped URL, including ones without GA data rows yet.
                $_ga_type_map_bl = get_option("seo_dash_sitemap_types_{$rid}_ga", []);
                if (!is_array($_ga_type_map_bl)) $_ga_type_map_bl = [];
                $kpi_blog_posts_only = 0;
                $kpi_blog_categories = 0;
                foreach ($_ga_type_map_bl as $_bl_url => $_bl_type) {
                    if (in_array($_bl_type, ['post','blog','article','news'], true)) $kpi_blog_posts_only++;
                    elseif (in_array($_bl_type, ['category','tag'], true)) $kpi_blog_categories++;
                }
                // TOTAL (posts + categories combined) for fallback
                $kpi_blog_total        = seo_dash_count_ga_type_urls($rid, $seo_dash_blog_types);
                // Also count custom_pages blog entries not in the type_map
                $blog_map = get_option("seo_dash_custom_pages_{$rid}_blog", []);
                if (is_array($blog_map)) {
                    foreach ($blog_map as $_bl) {
                        if (empty($_bl['url']) || !empty($_bl['trashed'])) continue;
                        if (!isset($_ga_type_map_bl[$_bl['url']])) $kpi_blog_posts_only++;
                    }
                    unset($_bl);
                }
                ?>
                
                <!-- Timeperiod Bar -->
                <div class="seo-cl-panel" style="margin-bottom: 20px;">
                    <div style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size:16px;">📅</span>
                                <button type="button" class="seo-blog-tp-btn" data-period="7d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">7 Days</button>
                                <button type="button" class="seo-blog-tp-btn" data-period="30d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">30 Days</button>
                                <button type="button" class="seo-blog-tp-btn" data-period="90d" style="padding: 6px 14px; border: 1px solid var(--cc-border); background: transparent; border-radius: 100px; font-size: 13px; font-weight: 600; color: var(--cc-text); cursor: pointer; transition: all 0.2s; min-width: 100px;">90 Days</button>
                                <button type="button" class="seo-blog-tp-btn active" data-period="overall" style="padding: 6px 14px; border: 1px solid var(--cc-primary); background: var(--cc-primary); color: #fff; border-radius: 100px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; min-width: 100px;">Overall</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards — 3 cards: Total Blog Posts | Total Traffic | Categories -->
                <?php
                // Blog Posts KPI card config (admin: Client Dashboard → Blog Posts → KPI Cards)
                $bl_kpi_defs = [
                    'posts'   => ['icon'=>'📝','color'=>'#6366f1','label'=>'TOTAL BLOG POSTS','desc'=>'All published posts','val'=>$kpi_blog_posts_only, 'valid'=>'kpi-blog-posts-total'],
                    'traffic' => ['icon'=>'📈','color'=>'#06b6d4','label'=>'TOTAL TRAFFIC',   'desc'=>'All-time visits',    'val'=>'--',                   'valid'=>'kpi-blog-traffic'],
                    'cats'    => ['icon'=>'🗂️','color'=>'#10b981','label'=>'CATEGORIES',      'desc'=>'Blog categories',    'val'=>$kpi_blog_categories,   'valid'=>'kpi-blog-cats-total'],
                ];
                $bl_kpi_show = [];
                foreach ($bl_kpi_defs as $bkk => $bkv) {
                    $saved = is_array($bl_kpi_cfg[$bkk] ?? null) ? $bl_kpi_cfg[$bkk] : [];
                    $bl_kpi_show[$bkk] = [
                        'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
                        'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $bkv['label'],
                    ];
                }
                ?>
                <?php if ($bl_show['kpis']) : ?>
                <div class="seo-ov3-kpi-row">
                    <?php foreach ($bl_kpi_defs as $bkk => $bkv) : 
                        if (!$bl_kpi_show[$bkk]['show']) continue;
                        $bl_is_zero = ($bkk === 'cats' && empty($bkv['val']));
                    ?>
                    <div class="seo-ov3-kpi" style="--kc:<?php echo esc_attr($bkv['color']); ?>;<?php echo $bl_is_zero ? 'display:none;' : ''; ?>">
                        <div class="seo-ov3-kpi-icon" style="--kc:<?php echo esc_attr($bkv['color']); ?>;"><?php echo $bkv['icon']; ?></div>
                        <div class="seo-ov3-kpi-label"><?php echo esc_html($bl_kpi_show[$bkk]['label']); ?></div>
                        <div class="seo-ov3-kpi-val" id="<?php echo esc_attr($bkv['valid']); ?>"><?php echo esc_html($bkv['val']); ?></div>
                        <div class="seo-ov3-kpi-desc"><?php echo esc_html($bkv['desc']); ?></div>
                        <div class="seo-ov3-kpi-bar" style="--kc:<?php echo esc_attr($bkv['color']); ?>;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php
                $blog_charts_saved = get_option("seo_dash_charts_blog_{$rid}", []);
                $blog_chart_type  = seo_dash_get_chart_type_saved($blog_charts_saved, 'performance', 'area');
                $blog_chart_title = seo_dash_get_chart_title_saved($blog_charts_saved, 'performance', '📊 Traffic Chart');
                $blog_chart_show  = seo_dash_get_chart_show_saved($blog_charts_saved, 'performance', true);
                ?>
                <!-- Traffic Chart Section -->
                <div class="seo-cl-panel" id="seo-blog-chart-container" style="margin-bottom: 20px; <?php echo ( empty($ac_opt['show']) || empty($bl_show['chart']) || !$blog_chart_show ) ? 'display:none;' : ''; ?>">
                    <div class="seo-cl-panel-hd" style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="display:flex; align-items:center; gap:8px;"><?php echo esc_html($blog_chart_title); ?></h3>
                        <div style="display:flex; gap:12px; align-items:center;">
                            <div class="seo-chart-type-toggle-group" data-target="seo-blog-chart" style="display:inline-flex; background:var(--cc-surf2); border:1px solid var(--cc-border); border-radius:8px; padding:2px; gap:2px;">
                                <button type="button" class="seo-ct-btn active" data-type="area" title="Curve Area (Image 1)" style="padding:4px 10px; border:none; background:var(--cc-primary); color:#fff; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">📈 Area</button>
                                <button type="button" class="seo-ct-btn" data-type="bar" title="Vertical Bar" style="padding:4px 10px; border:none; background:transparent; color:var(--cc-muted); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">📊 Bar</button>
                                <button type="button" class="seo-ct-btn" data-type="horizontalBar" title="Horizontal Bar" style="padding:4px 10px; border:none; background:transparent; color:var(--cc-muted); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">⏸️ Horiz</button>
                            </div>
                            <select id="seo-blog-chart-metric" style="padding:4px 8px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:12px; cursor:pointer;">
                                <option value="sessions" <?php selected($ac_opt['metric'] ?? '', 'sessions'); ?>>Sessions</option>
                                <option value="users" <?php selected($ac_opt['metric'] ?? '', 'users'); ?>>Active Users</option>
                                <option value="views" <?php selected($ac_opt['metric'] ?? '', 'views'); ?>>Page Views</option>
                            </select>
                        </div>
                    </div>
                    <div class="seo-cl-panel-body" style="padding:20px; height: 300px; position:relative;">
                        <canvas id="seo-blog-chart" data-color="<?php echo esc_attr($ac_opt['color'] ?? '#8b5cf6'); ?>" data-chart-type="<?php echo esc_attr($blog_chart_type); ?>"></canvas>
                    </div>
                </div>

                <script>
                function seoUpdateBlogKPIs(period) {
                    var fullCache = window.seoTabFullDataCache || {};
                    var rows = fullCache['blog_current'] || (window.seoTabRowCache && window.seoTabRowCache['blog']) || [];
                    var sumUsers = 0;
                    var postTypes  = ['post','blog','article','news'];
                    var catTypes   = ['category','tag'];
                    var totalPosts = 0;
                    var totalCats  = 0;

                    rows.forEach(function(r) {
                        var d = r.data || {};
                        var p = d[period] || null;
                        if (p) sumUsers += parseInt(p.users || 0);
                        // Count by type for the static cards
                        var t = r.type || 'other';
                        if (postTypes.indexOf(t) > -1) totalPosts++;
                        else if (catTypes.indexOf(t) > -1) totalCats++;
                    });

                    if (document.getElementById('kpi-blog-traffic'))
                        document.getElementById('kpi-blog-traffic').innerText = sumUsers.toLocaleString();
                    // Only update PHP-rendered counts if JS has data (avoids overwriting with 0 on first render)
                    if (rows.length > 0) {
                        if (document.getElementById('kpi-blog-posts-total'))
                            document.getElementById('kpi-blog-posts-total').innerText = totalPosts.toLocaleString();
                        if (document.getElementById('kpi-blog-cats-total'))
                            document.getElementById('kpi-blog-cats-total').innerText = totalCats.toLocaleString();
                    }

                    var table = document.getElementById('seo-cl-blog-table');
                    if (table) {
                        var allCols = table.querySelectorAll('th[data-col], td[data-col]');
                        allCols.forEach(function(c) { c.style.background = ''; c.classList.remove('seo-col-highlight'); });
                        var activeCols = table.querySelectorAll('th[data-col="'+period+'"], td[data-col="'+period+'"]');
                        var highlightColor = 'var(--cc-surf2)';
                        if (period === '30d') highlightColor = 'rgba(139,92,246,0.05)';
                        else if (period === '7d') highlightColor = 'rgba(14,165,233,0.05)';
                        else if (period === '90d') highlightColor = 'rgba(16,185,129,0.05)';
                        else if (period === 'overall') highlightColor = 'rgba(245,158,11,0.05)';
                        activeCols.forEach(function(c) { c.style.background = highlightColor; c.classList.add('seo-col-highlight'); });
                    }
                }

                var blogChart = null;
                function seoUpdateBlogChart() {
                    var el = document.getElementById('seo-blog-chart');
                    if(!el) return;

                    var activeBtn = document.querySelector('.seo-blog-tp-btn.active');
                    var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                    var $mSel = document.getElementById('seo-blog-chart-metric');
                    var metric = $mSel ? $mSel.value : 'sessions';
                    var type = el.dataset.chartType || 'bar';
                    var color = el.dataset.color || '#8b5cf6';

                    var fullCache = window.seoTabFullDataCache || {};
                    var rows = fullCache['blog_current'] || fullCache['blog_blog'] || fullCache['blog_all'] || (window.seoTabRowCache && window.seoTabRowCache['blog']) || [];
                    var mKey = { 'users': 'users', 'sessions': 'sessions', 'views': 'pageviews' }[metric] || 'sessions';

                    var data = [];
                    rows.forEach(function(r) {
                        var d = r.data || {};
                        var p = (d && d[period]) ? d[period] : ((d && (d[mKey] !== undefined || d.sessions !== undefined)) ? d : r);
                        var val = parseInt((p && p[mKey] !== undefined) ? p[mKey] : (p ? (p.sessions || p.users || p.pageviews || 0) : 0));
                        var title = r.title || r.url || '';
                        if (!title || title === r.url) {
                            try {
                                var u = new URL(r.url);
                                var path = u.pathname.replace(/\/$/, '').split('/').pop();
                                title = path ? path.replace(/-/g, ' ') : 'Home';
                                title = title.charAt(0).toUpperCase() + title.slice(1);
                            } catch(e) { title = r.url || 'Page'; }
                        }
                        if (val > 0) data.push({ label: title, value: val });
                    });

                    if (data.length === 0 && rows.length > 0) {
                        rows.slice(0, 10).forEach(function(r) {
                            var title = r.title || r.url || 'Page';
                            if (title.indexOf('http') === 0) {
                                try {
                                    var u = new URL(r.url);
                                    var path = u.pathname.replace(/\/$/, '').split('/').pop();
                                    title = path ? path.replace(/-/g, ' ') : 'Home';
                                    title = title.charAt(0).toUpperCase() + title.slice(1);
                                } catch(e) { title = r.url; }
                            }
                            data.push({ label: title, value: 0 });
                        });
                    }

                    // Select top 10, then sort low to high so performance curve climbs UPWARDS from left to right
                    data.sort(function(a,b){ return b.value - a.value; });
                    data = data.slice(0, 10);
                    data.sort(function(a,b){ return a.value - b.value; });

                    var labels = data.map(function(d){ return d.label; });
                    var values = data.map(function(d){ return d.value; });

                    if(blogChart) blogChart.destroy();
                    blogChart = window.seoBuildUniversalChart(el, {
                        type: type,
                        labels: labels,
                        values: values,
                        color: color,
                        labelName: metric.charAt(0).toUpperCase() + metric.slice(1)
                    });
                }

                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.seo-blog-tp-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            document.querySelectorAll('.seo-blog-tp-btn').forEach(function(b){
                                b.classList.remove('active');
                                b.style.background = 'transparent';
                                b.style.color = 'var(--cc-text)';
                                b.style.borderColor = 'var(--cc-border)';
                            });
                            this.classList.add('active');
                            this.style.background = 'var(--cc-primary)';
                            this.style.color = '#fff';
                            this.style.borderColor = 'var(--cc-primary)';
                            
                            var period = this.getAttribute('data-period');
                            seoUpdateBlogKPIs(period);
                            seoUpdateBlogChart();
                        });
                    });
                    
                    // Listen to full-data event from client-app.js for Blog
                    jQuery(document).on('seo:rowsLoaded', function(e, tab, rows) {
                        if (tab === 'blog') {
                            var activeBtn = document.querySelector('.seo-blog-tp-btn.active');
                            var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                            seoUpdateBlogKPIs(period);
                            seoUpdateBlogChart();
                            if(typeof loadBlogPDDropdown === 'function') loadBlogPDDropdown();
                        }
                    });

                    var $blogChartMetric = document.getElementById('seo-blog-chart-metric');
                    if($blogChartMetric) $blogChartMetric.addEventListener('change', seoUpdateBlogChart);
                });
                
                // Blog Page Detail Logic — type filter + live search (mirrors Analytics Page Detail)
                var blogPDFilter = null;
                document.addEventListener('DOMContentLoaded', function() {
                    blogPDFilter = window.seoInitPageDetailFilter({
                        tab: 'blog',
                        prefix: 'seo-blog-pd',
                        buildExtra: function(r) {
                            var d = r.data || {};
                            return {
                                '7d':      { u: (d['7d']||{}).users||'0',      s: (d['7d']||{}).sessions||'0',      v: (d['7d']||{}).pageviews||'0' },
                                '30d':     { u: (d['30d']||{}).users||'0',     s: (d['30d']||{}).sessions||'0',     v: (d['30d']||{}).pageviews||'0' },
                                '90d':     { u: (d['90d']||{}).users||'0',     s: (d['90d']||{}).sessions||'0',     v: (d['90d']||{}).pageviews||'0' },
                                'overall': { u: (d['overall']||{}).users||'0', s: (d['overall']||{}).sessions||'0', v: (d['overall']||{}).pageviews||'0' }
                            };
                        },
                        onSelect: function(url, item) {
                            if (url && item) seoOpenBlogPD(url, item);
                            else seoCloseBlogPD();
                        }
                    });
                });

                window.loadBlogPDDropdown = function() { if (blogPDFilter) blogPDFilter.refresh(); };

                window.seoOpenBlogPD = function(url, item) {
                    if (!item && blogPDFilter) item = blogPDFilter.getItem(url);
                    if (!item) return;
                    var found = { title: item.title, url: item.url, data: item.extra, row: item.row };
                    
                    document.getElementById('seo-blog-single-view').style.display = 'block';
                    var $sel = document.getElementById('seo-blog-pd-url-select');
                    if($sel) $sel.value = found.url;
                    
                    document.getElementById('seo-blog-pd-title').innerText = found.title;
                    document.getElementById('seo-blog-pd-link').innerText = found.url;
                    document.getElementById('seo-blog-pd-link').href = found.url;
                    
                    var activeBtn = document.querySelector('.seo-blog-tp-btn.active');
                    var period = activeBtn ? activeBtn.getAttribute('data-period') : 'overall';
                    
                    var pLabel = period === 'overall' ? 'OVERALL' : (period === '7d' ? '7 DAYS' : (period === '30d' ? '30 DAYS' : '90 DAYS'));
                    document.getElementById('seo-blog-pd-period-label').innerText = pLabel;
                    
                    document.getElementById('seo-blog-pd-val-users').innerText = found.data[period].u;
                    document.getElementById('seo-blog-pd-val-sess').innerText = found.data[period].s;
                    document.getElementById('seo-blog-pd-val-views').innerText = found.data[period].v;
                    
                    var table = document.getElementById('seo-cl-blog-table');
                    var rows = table.querySelectorAll('tbody.seo-cl-tbody tr');
                    rows.forEach(function(r){ r.style.background = ''; });
                    if(found.row) found.row.style.background = 'rgba(99,102,241,0.1)';
                };

                window.seoCloseBlogPD = function() {
                    document.getElementById('seo-blog-single-view').style.display = 'none';
                    var table = document.getElementById('seo-cl-blog-table');
                    if(table){
                        var rows = table.querySelectorAll('tbody.seo-cl-tbody tr');
                        rows.forEach(function(r){ r.style.background = ''; });
                    }
                    var $sel = document.getElementById('seo-blog-pd-url-select');
                    if($sel) $sel.value = '';
                };
                </script>

                <div style="display:flex; gap:20px; align-items:flex-start; margin-bottom: 20px;">
                    <?php if ($bl_show['table']) : ?>
                    <!-- Table Side -->
                    <div class="seo-cl-panel" style="flex:1; overflow:hidden;">
                        <div class="seo-cl-panel-hd" style="display:flex;justify-content:space-between;align-items:center;">
                            <h3>Blog Posts Data</h3>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <div style="position:relative;">
                                    <span style="position:absolute; left:9px; top:50%; transform:translateY(-50%); font-size:12px; pointer-events:none; color:var(--cc-muted);">🔍</span>
                                    <input type="text" id="seo-blog-table-search" data-tab="blog" autocomplete="off" placeholder="Search posts..." style="padding:5px 10px 5px 28px; border-radius:8px; font-size:12px; background:var(--cc-surf2); color:var(--cc-text); border:1px solid var(--cc-border); outline:none; min-width:160px;" />
                                </div>
                                <div id="seo-blog-subtype-filters" style="display:none;"></div>
                            <?php if (!empty($months_sv)) : ?>
                            <select class="seo-cl-month-sel" data-scope="blog" style="padding:4px 8px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:12px; cursor:pointer;">
                                <option value="">All months</option>
                                <?php foreach ($months_sv as $m) : ?>
                                <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html(date_i18n('F Y', strtotime($m.'-01'))); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            </div>
                        </div>
                        <div class="seo-cl-table-wrap" style="max-height:600px;overflow-y:auto;overflow-x:auto;-webkit-overflow-scrolling:touch;">
                            <table class="seo-cl-table" id="seo-cl-blog-table" style="width:100%; min-width:1200px;">
                                <thead style="background:var(--cc-surf); position: sticky; top: 0; z-index: 10;">
                                    <tr>
                                        <th rowspan="2" style="width:40px; border-right:1px solid var(--cc-border);"></th>
                                        <th rowspan="2" style="vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--cc-border);">Page</th>
                                        <th rowspan="2" style="width:40px; border-right:1px solid var(--cc-border);"></th>
                                        <th colspan="3" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">7 DAYS</th>
                                        <th colspan="3" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">30 DAYS</th>
                                        <th colspan="3" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">90 DAYS</th>
                                        <th colspan="3" style="text-align:center;border-bottom:1px solid var(--cc-border);">OVERALL</th>
                                    </tr>
                                    <tr>
                                        <?php for($i=0;$i<4;$i++): $br = $i<3 ? 'border-right:1px solid var(--cc-border);' : ''; ?>
                                        <th style="font-size:10px;text-align:center;" data-col="<?php echo ['7d','30d','90d','overall'][$i]; ?>">Users</th>
                                        <th style="font-size:10px;text-align:center;" data-col="<?php echo ['7d','30d','90d','overall'][$i]; ?>">Sess.</th>
                                        <th style="font-size:10px;text-align:center;<?php echo $br;?>" data-col="<?php echo ['7d','30d','90d','overall'][$i]; ?>">Views</th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody class="seo-cl-tbody"><tr><td colspan="15" style="text-align:center;padding:24px;color:var(--cc-subtle);">Loading...</td></tr></tbody>
                            </table>
                        </div>
                        <div class="seo-cl-tab-pagination" style="display:none;padding:16px 20px;border-top:1px solid var(--cc-border);text-align:center;"></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($bl_show['pagedetail']) : ?>
                    <!-- Single Page Side -->
                    <div id="seo-blog-single-view" class="seo-cl-panel" style="width: 380px; flex-shrink: 0; display: none;">
                        <div class="seo-cl-panel-hd" style="display:flex;justify-content:space-between;align-items:center;">
                            <h3 style="font-size:15px;">Page Details</h3>
                            <button type="button" onclick="seoCloseBlogPD()" style="background:none;border:none;color:var(--cc-muted);cursor:pointer;padding:4px;">✕</button>
                        </div>
                        <div class="seo-cl-panel-body" style="padding: 20px;">
                            <div style="margin-bottom: 12px;">
                                <label style="display:block; font-size:11px; font-weight:600; color:var(--cc-muted); margin-bottom:8px; text-transform:uppercase;">Filter by Type</label>
                                <div id="seo-blog-pd-type-toggle"></div>
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label style="display:block; font-size:11px; font-weight:600; color:var(--cc-muted); margin-bottom:8px; text-transform:uppercase;">Select Page</label>
                                <div style="position:relative; margin-bottom:8px;" id="seo-blog-pd-search-wrap">
                                    <span style="position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:13px; pointer-events:none; z-index:1;">🔍</span>
                                    <input type="text" id="seo-blog-pd-search" autocomplete="off" placeholder="Search..." style="width:100%; padding:8px 32px 8px 32px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:13px; outline:none; box-sizing:border-box; transition:border-color 0.2s;" />
                                    <span id="seo-blog-pd-search-clear" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:14px; cursor:pointer; color:var(--cc-muted); display:none; line-height:1;">✕</span>
                                    <div id="seo-blog-pd-results" style="display:none; position:absolute; top:calc(100% + 4px); left:0; width:100%; background:var(--cc-surf, #1e2130); border:1px solid var(--cc-border); border-radius:8px; max-height:220px; overflow-y:auto; z-index:9999; box-shadow:0 8px 32px rgba(0,0,0,0.6);"></div>
                                </div>
                                <select id="seo-blog-pd-url-select" style="width:100%; padding:8px 12px; border-radius:6px; background:var(--cc-surf); border:1px solid var(--cc-border); color:var(--cc-text); font-size:13px;"></select>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <h4 id="seo-blog-pd-title" style="margin:0 0 4px 0; font-size:16px; color:var(--cc-text); line-height:1.3; word-break:break-word;"></h4>
                                <a id="seo-blog-pd-link" href="#" target="_blank" style="font-size:12px; color:var(--cc-primary); text-decoration:none; word-break:break-all;"></a>
                            </div>

                            <div style="background:var(--cc-surf2); border-radius:8px; padding:16px; margin-bottom:20px;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
                                    <span style="font-size:12px; color:var(--cc-muted); font-weight:600;">METRIC</span>
                                    <span style="font-size:12px; color:var(--cc-text); font-weight:700; text-transform:uppercase;" id="seo-blog-pd-period-label">OVERALL</span>
                                </div>
                                <div style="display:flex; flex-direction:column; gap:12px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:#6366f1;"></div>
                                            <span style="font-size:13px; color:var(--cc-text);">Active Users</span>
                                        </div>
                                        <span style="font-size:14px; font-weight:700; color:var(--cc-text);" id="seo-blog-pd-val-users">0</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:#10b981;"></div>
                                            <span style="font-size:13px; color:var(--cc-text);">Sessions</span>
                                        </div>
                                        <span style="font-size:14px; font-weight:700; color:var(--cc-text);" id="seo-blog-pd-val-sess">0</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:#f59e0b;"></div>
                                            <span style="font-size:13px; color:var(--cc-text);">Page Views</span>
                                        </div>
                                        <span style="font-size:14px; font-weight:700; color:var(--cc-text);" id="seo-blog-pd-val-views">0</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Queries -->
                            <div style="font-size:14px; font-weight:800; color:var(--cc-text); margin-bottom:12px;">Top Queries</div>
                            <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="color:var(--cc-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:85%;">what does a condenser l...</span>
                                    <span style="font-weight:800; color:var(--cc-primary);">1</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Google Business ────────────────────────── -->
            <?php if ($show['gmb']) : ?>
            <div class="seo-cl-panel-tab" data-tab="gmb" <?php echo $first_visible!=='gmb'?'style="display:none;"':''; ?>>
                <div class="seo-cl-panel">
                    <div class="seo-cl-panel-hd">
                        <h3>📍  Google Business Profile</h3>
                        <?php if (!empty($months_gmb)) : ?>
                        <select class="seo-cl-month-sel" data-scope="gmb">
                            <option value="">All months</option>
                            <?php foreach ($months_gmb as $m) : ?>
                            <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html(date_i18n('F Y',strtotime($m.'-01'))); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <?php
                    // Use COUNT query instead of loading all rows
                    $kpi_gmb_posts = SEO_Dash_Database::get_data_rows_count(SEO_Dash_Database::$data_gmb_posts, $rid, '', false);
                    
                    global $wpdb;
                    $gmb_stats = $wpdb->get_row($wpdb->prepare(
                        "SELECT SUM(calls) as t_calls, SUM(bookings) as t_bookings, SUM(clicks_directions) as t_dirs, SUM(clicks_website) as t_web
                         FROM ".SEO_Dash_Database::$data_gmb." WHERE report_id=%d AND trashed=0", $rid), ARRAY_A);
                    
                    $kpi_total_calls = intval($gmb_stats['t_calls'] ?? 0);
                    $kpi_directions  = intval($gmb_stats['t_dirs'] ?? 0);
                    $kpi_bookings    = intval($gmb_stats['t_bookings'] ?? 0);
                    $kpi_website_clicks = intval($gmb_stats['t_web'] ?? 0);
                    $kpi_months_tracked = is_array($months_gmb) ? count($months_gmb) : 0;
                    
                    // Only load first row if needed, with LIMIT 1
                    $gmb_rows = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_gmb, $rid, '', false, 1);
                    if (empty($gmb_rows) && $kpi_gmb_posts === 0) : ?>
                    <div class="seo-cl-empty"><div class="seo-cl-empty-icon">📍</div><h4>No Data Yet</h4><p>Google Business data will appear here once your agency adds it.</p></div>
                    <?php else : $g=!empty($gmb_rows) ? $gmb_rows[0] : []; ?>
                    <div class="seo-cl-panel-body">
                        <?php
                        // GMB KPI card config (admin: Client Dashboard → Google Business → KPI Cards)
                        $gmb_kpi_defs = [
                            'posts'      => ['icon'=>'📍','color'=>'#f59e0b','label'=>'GMB POSTS',      'val'=>($kpi_gmb_posts > 0 ? $kpi_gmb_posts : '—')],
                            'calls'      => ['icon'=>'📞','color'=>'#10b981','label'=>'TOTAL CALLS',    'val'=>($kpi_total_calls > 0 ? number_format($kpi_total_calls) : '—')],
                            'directions' => ['icon'=>'🗺️','color'=>'#6366f1','label'=>'DIRECTIONS',     'val'=>($kpi_directions > 0 ? number_format($kpi_directions) : '—')],
                            'bookings'   => ['icon'=>'🎟️','color'=>'#8b5cf6','label'=>'BOOKINGS',       'val'=>($kpi_bookings > 0 ? number_format($kpi_bookings) : '—')],
                            'website'    => ['icon'=>'🖱️','color'=>'#0ea5e9','label'=>'WEBSITE CLICKS', 'val'=>($kpi_website_clicks > 0 ? number_format($kpi_website_clicks) : '—')],
                            'months'     => ['icon'=>'🗓️','color'=>'#64748b','label'=>'MONTHS TRACKED', 'val'=>($kpi_months_tracked > 0 ? $kpi_months_tracked : '—')],
                        ];
                        $gmb_kpi_show = [];
                        foreach ($gmb_kpi_defs as $gkk => $gkv) {
                            $saved = is_array($gmb_kpi_cfg[$gkk] ?? null) ? $gmb_kpi_cfg[$gkk] : [];
                            $gmb_kpi_show[$gkk] = [
                                'show'  => isset($saved['show']) ? (bool)$saved['show'] : true,
                                'label' => ($saved['label'] ?? '') !== '' ? $saved['label'] : $gkv['label'],
                            ];
                        }
                        ?>
                        <?php if ($gmb_show['kpis']) : ?>
                        <div class="seo-ov3-kpi-row" style="margin-bottom:24px;">
                            <?php foreach ($gmb_kpi_defs as $gkk => $gkv) : 
                                if (!$gmb_kpi_show[$gkk]['show']) continue;
                                $gmb_is_zero = ($gkv['val'] === '—' || empty($gkv['val']));
                            ?>
                            <div class="seo-ov3-kpi" style="<?php echo $gmb_is_zero ? 'display:none;' : ''; ?>">
                                <div class="seo-ov3-kpi-bar" style="--kc:<?php echo esc_attr($gkv['color']); ?>;"></div>
                                <div class="seo-ov3-kpi-icon"><?php echo $gkv['icon']; ?></div>
                                <div class="seo-ov3-kpi-label"><?php echo esc_html($gmb_kpi_show[$gkk]['label']); ?></div>
                                <div class="seo-ov3-kpi-val"><?php echo esc_html($gkv['val']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Business Details Section -->
                        <?php
                        $gmb_details = get_option("seo_dash_gmb_details_{$rid}", []);
                        if (!is_array($gmb_details)) $gmb_details = [];
                        $biz_name     = $gmb_details['business_name']    ?? '';
                        $biz_address  = $gmb_details['address']          ?? '';
                        $biz_phone    = $gmb_details['phone']             ?? '';
                        $biz_website  = $gmb_details['website_url']      ?? '';
                        $biz_category = $gmb_details['primary_category'] ?? '';
                        $biz_desc     = $gmb_details['description']      ?? '';
                        $biz_gmb_url  = $gmb_details['profile_url']      ?? '';
                        if ($gmb_show['details'] && ($biz_name || $biz_address || $biz_phone || $biz_website || $biz_category || $biz_desc || $biz_gmb_url)) :
                        ?>
                        <div style="margin-top:28px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px;">
                            <h3 style="font-size:16px;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">🏢 Business Details</h3>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                                <?php if ($biz_name) : ?>
                                <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;padding:16px 18px;">
                                    <div style="font-size:10px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px;display:flex;align-items:center;gap:5px;">🏷️ Business Name</div>
                                    <div style="font-size:14px;font-weight:700;color:var(--cc-text);"><?php echo esc_html($biz_name); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($biz_address) : ?>
                                <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;padding:16px 18px;">
                                    <div style="font-size:10px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px;display:flex;align-items:center;gap:5px;">📍 Address</div>
                                    <div style="font-size:13px;font-weight:600;color:var(--cc-text);line-height:1.5;"><?php echo esc_html($biz_address); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($biz_phone) : ?>
                                <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;padding:16px 18px;">
                                    <div style="font-size:10px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px;display:flex;align-items:center;gap:5px;">📞 Phone</div>
                                    <div style="font-size:14px;font-weight:700;color:var(--cc-text);"><?php echo esc_html($biz_phone); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($biz_website) : ?>
                                <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;padding:16px 18px;">
                                    <div style="font-size:10px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px;display:flex;align-items:center;gap:5px;">🌐 Website</div>
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                        <span style="font-size:12px;color:var(--cc-muted);word-break:break-all;flex:1;"><?php echo esc_html($biz_website); ?></span>
                                        <a href="<?php echo esc_url($biz_website); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;background:var(--cc-primary);color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;flex-shrink:0;">View ↗</a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($biz_category) : ?>
                                <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;padding:16px 18px;">
                                    <div style="font-size:10px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px;display:flex;align-items:center;gap:5px;">🏷️ Category</div>
                                    <div style="font-size:14px;font-weight:700;color:var(--cc-text);"><?php echo esc_html($biz_category); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($biz_gmb_url) : ?>
                                <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;padding:16px 18px;">
                                    <div style="font-size:10px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px;display:flex;align-items:center;gap:5px;">🔗 GMB URL</div>
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                        <span style="font-size:12px;color:var(--cc-muted);word-break:break-all;flex:1;"><?php echo esc_html($biz_gmb_url); ?></span>
                                        <a href="<?php echo esc_url($biz_gmb_url); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;background:#f59e0b;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;flex-shrink:0;">View ↗</a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($biz_desc) : ?>
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:12px;padding:16px 18px;margin-top:14px;">
                                <div style="font-size:10px;font-weight:700;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:8px;display:flex;align-items:center;gap:5px;">📄 Description</div>
                                <div style="font-size:13px;color:var(--cc-text);line-height:1.7;"><?php echo esc_html($biz_desc); ?></div>
                            </div>
                            <?php endif; ?>
                        </div><!-- /Business Details wrapper -->
                        <?php endif; // biz details ?>

                            <?php
                            // ── Monthly Performance Chart data (from GMB rows table) ──────────────
                            $gmb_perf_rows = $wpdb->get_results($wpdb->prepare(
                                "SELECT month_key, calls, clicks_directions, bookings, clicks_website
                                 FROM " . SEO_Dash_Database::$data_gmb . "
                                 WHERE report_id=%d AND trashed=0
                                 ORDER BY month_key ASC",
                                $rid
                            ), ARRAY_A) ?: [];
                            $gmb_perf_labels  = [];
                            $gmb_perf_calls   = [];
                            $gmb_perf_dirs    = [];
                            $gmb_perf_books   = [];
                            $gmb_perf_web     = [];
                            foreach ($gmb_perf_rows as $gpr) {
                                $gmb_perf_labels[] = !empty($gpr['month_key']) ? date_i18n('M Y', strtotime($gpr['month_key'].'-01')) : '—';
                                $gmb_perf_calls[]  = (int)$gpr['calls'];
                                $gmb_perf_dirs[]   = (int)$gpr['clicks_directions'];
                                $gmb_perf_books[]  = (int)$gpr['bookings'];
                                $gmb_perf_web[]    = (int)$gpr['clicks_website'];
                            }

                            // ── GMB Posts by Month chart data ──────────────────────────────────────
                            $gmb_posts_rows = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_gmb_posts, $rid, '', false, 120, 0 );
                            $gmb_posts_by_month = [];
                            foreach ($gmb_posts_rows as $pr) {

                                $mk = $pr['month_key'] ?? '';
                                if (!$mk) $mk = 'Unknown';
                                $gmb_posts_by_month[$mk] = ($gmb_posts_by_month[$mk] ?? 0) + 1;
                            }
                            ksort($gmb_posts_by_month);
                            $gmb_posts_chart_labels = [];
                            $gmb_posts_chart_data   = [];
                            foreach ($gmb_posts_by_month as $mk => $cnt) {
                                $gmb_posts_chart_labels[] = $mk !== 'Unknown' ? date_i18n('M Y', strtotime($mk.'-01')) : 'Unknown';
                                $gmb_posts_chart_data[]   = $cnt;
                            }
                            // Status breakdown for posts pie
                            $gmb_posts_published = 0; $gmb_posts_draft = 0;
                            foreach ($gmb_posts_rows as $pr) {

                                if (($pr['status'] ?? '') === 'Published') $gmb_posts_published++;
                                else $gmb_posts_draft++;
                            }
                            $gmb_charts_saved = get_option("seo_dash_charts_gmb_{$rid}", get_option("seo_dash_gmb_charts_{$rid}", []));
                            $gmb_perf_type  = seo_dash_get_chart_type_saved($gmb_charts_saved, 'perf', 'bar');
                            $gmb_perf_title = seo_dash_get_chart_title_saved($gmb_charts_saved, 'perf', '📊 Monthly Performance Chart');
                            $gmb_perf_show  = seo_dash_get_chart_show_saved($gmb_charts_saved, 'perf', true);
                            $gmb_posts_type  = seo_dash_get_chart_type_saved($gmb_charts_saved, 'posts', 'horizontalBar');
                            $gmb_posts_title = seo_dash_get_chart_title_saved($gmb_charts_saved, 'posts', '📝 GMB Posts Chart');
                            $gmb_posts_show  = seo_dash_get_chart_show_saved($gmb_charts_saved, 'posts', true);
                            ?>

                            <?php if (!empty($gmb_perf_rows)) : ?>
                            <?php if ($gmb_show['perf_chart'] && $gmb_perf_show) : ?>
                            <!-- Monthly Performance Chart -->
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px;margin-top:20px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                                    <h3 style="font-size:15px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px;"><?php echo esc_html($gmb_perf_title); ?></h3>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <select id="seo-gmb-perf-chart-metric" style="padding:5px 10px;border-radius:8px;border:1px solid var(--cc-border);background:var(--cc-surf);color:var(--cc-text);font-size:12px;font-weight:600;cursor:pointer;">
                                            <option value="all">All Metrics</option>
                                            <option value="calls">Calls</option>
                                            <option value="directions">Directions</option>
                                            <option value="bookings">Bookings</option>
                                            <option value="website">Website Clicks</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="position:relative;height:280px;">
                                    <canvas id="seo-gmb-perf-chart"
                                        data-chart-type="<?php echo esc_attr($gmb_perf_type); ?>"
                                        data-labels="<?php echo esc_attr(wp_json_encode($gmb_perf_labels)); ?>"
                                        data-calls="<?php echo esc_attr(wp_json_encode($gmb_perf_calls)); ?>"
                                        data-dirs="<?php echo esc_attr(wp_json_encode($gmb_perf_dirs)); ?>"
                                        data-books="<?php echo esc_attr(wp_json_encode($gmb_perf_books)); ?>"
                                        data-web="<?php echo esc_attr(wp_json_encode($gmb_perf_web)); ?>">
                                    </canvas>
                                </div>
                            </div>
                            <?php endif; ?>
                            <script>
                            (function(){
                                var gmbPerfChart = null;
                                function buildGmbPerfChart() {
                                    var el = document.getElementById('seo-gmb-perf-chart');
                                    if (!el || typeof Chart === 'undefined') return;
                                    var labels  = JSON.parse(el.dataset.labels  || '[]');
                                    var calls   = JSON.parse(el.dataset.calls   || '[]');
                                    var dirs    = JSON.parse(el.dataset.dirs    || '[]');
                                    var books   = JSON.parse(el.dataset.books   || '[]');
                                    var web     = JSON.parse(el.dataset.web     || '[]');
                                    var typeRaw = el.dataset.chartType || 'horizontalBar';
                                    var metric  = document.getElementById('seo-gmb-perf-chart-metric') ? document.getElementById('seo-gmb-perf-chart-metric').value : 'all';
                                    var isDark  = !!(document.getElementById('seo-client-app') && document.getElementById('seo-client-app').classList.contains('seo-dark'));
                                    var gridC   = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
                                    var txtC    = isDark ? 'rgba(255,255,255,.55)' : '#64748b';
                                    var isHoriz   = typeRaw === 'horizontalBar';
                                    var isArea    = typeRaw === 'area';
                                    var isStacked = typeRaw === 'stackedBar';
                                    var isPie     = typeRaw === 'pie';
                                    var isDoughnut = typeRaw === 'doughnut';
                                    var isPolar   = typeRaw === 'polarArea';
                                    var isRadar   = typeRaw === 'radar';

                                    var isCircular = isPie || isDoughnut || isPolar;
                                    var isRadial   = isCircular || isRadar;

                                    var chartType = 'bar';
                                    if (isArea || typeRaw === 'line') chartType = 'line';
                                    else if (isPie) chartType = 'pie';
                                    else if (isDoughnut) chartType = 'doughnut';
                                    else if (isPolar) chartType = 'polarArea';
                                    else if (isRadar) chartType = 'radar';

                                    var allDatasets = [
                                        { label:'Calls',          data:calls, backgroundColor:'#f59e0b', borderColor:'#f59e0b' },
                                        { label:'Directions',     data:dirs,  backgroundColor:'#6366f1', borderColor:'#6366f1' },
                                        { label:'Bookings',       data:books, backgroundColor:'#8b5cf6', borderColor:'#8b5cf6' },
                                        { label:'Website Clicks', data:web,   backgroundColor:'#06b6d4', borderColor:'#06b6d4' }
                                    ];
                                    var metricMap = { calls:0, directions:1, bookings:2, website:3 };
                                    var datasets;
                                    if (metric === 'all') {
                                        datasets = allDatasets.map(function(ds) {
                                            return Object.assign({}, ds, {
                                                backgroundColor: chartType === 'bar' ? ds.backgroundColor + 'CC' : (isArea ? ds.backgroundColor + '33' : ds.backgroundColor),
                                                borderWidth: chartType === 'bar' ? 0 : 2,
                                                borderRadius: chartType === 'bar' ? 6 : 0,
                                                maxBarThickness: 42,
                                                barPercentage: 0.8,
                                                categoryPercentage: 0.85,
                                                fill: isArea,
                                                tension: 0.4,
                                                pointRadius: (isRadial||isArea||chartType==='line') ? 3 : 0
                                            });
                                        });
                                    } else {
                                        var ds = allDatasets[metricMap[metric]];
                                        datasets = [Object.assign({}, ds, {
                                            backgroundColor: chartType === 'bar' ? ds.backgroundColor + 'CC' : (isArea ? ds.backgroundColor + '33' : ds.backgroundColor),
                                            borderWidth: chartType === 'bar' ? 0 : 2,
                                            borderRadius: chartType === 'bar' ? 6 : 0,
                                            fill: isArea,
                                            tension: 0.4,
                                            pointRadius: (isRadial||isArea||chartType==='line') ? 4 : 0
                                        })];
                                    }

                                    if (gmbPerfChart) gmbPerfChart.destroy();
                                    gmbPerfChart = new Chart(el, {
                                        type: chartType,
                                        data: { labels: labels, datasets: datasets },
                                        options: {
                                            indexAxis: isHoriz ? 'y' : 'x',
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            animation: { duration: 600, easing: 'easeInOutQuart' },
                                            plugins: {
                                                legend: { display: (metric === 'all' || isRadial), position: isRadial ? 'right' : 'bottom', labels: { color: txtC, font: { size: 12 }, boxWidth: 12, padding: 16 } },
                                                tooltip: {
                                                    mode: 'index', intersect: false,
                                                    backgroundColor: isDark ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.97)',
                                                    titleColor: isDark ? '#e2e8f0' : '#1e293b',
                                                    bodyColor: isDark ? '#94a3b8' : '#475569',
                                                    borderColor: isDark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.08)',
                                                    borderWidth: 1, padding: 10, cornerRadius: 8
                                                }
                                            },
                                            scales: isRadial ? (isRadar ? { r: { grid: { color: gridC }, ticks: { display: false }, pointLabels: { color: txtC, font: { size: 11 } } } } : {}) : {
                                                x: { stacked: isStacked, grid: { color: gridC }, ticks: { color: txtC, font: { size: 11 } }, beginAtZero: true },
                                                y: { stacked: isStacked, grid: { color: gridC }, ticks: { color: txtC, font: { size: 11 } }, beginAtZero: true }
                                            }
                                        }
                                    });
                                }
                                window.buildGmbPerfChart = buildGmbPerfChart;
                                function waitAndBuild(n) {
                                    if (typeof Chart !== 'undefined') { buildGmbPerfChart(); return; }
                                    if ((n||0) > 60) return;
                                    setTimeout(function(){ waitAndBuild((n||0)+1); }, 100);
                                }
                                waitAndBuild(0);
                                document.addEventListener('DOMContentLoaded', function(){
                                    var ms = document.getElementById('seo-gmb-perf-chart-metric');
                                    if (ms) ms.addEventListener('change', buildGmbPerfChart);
                                });
                                // Exposed so the tab-switch handler below can force a fresh,
                                // correctly-sized rebuild once this panel is actually visible
                                window.seoGmbEnsureChart = function() {
                                    if (typeof window.buildGmbPerfChart === 'function') window.buildGmbPerfChart();
                                    if (typeof window.buildGmbPostsChart === 'function') window.buildGmbPostsChart();
                                };
                            })();
                            </script>

                            <!-- GMB Monthly Performance Table -->
                            <?php if (!empty($gmb_perf_rows)) : ?>
                            <?php
                            $gmb_table_rows = [];
                            foreach ($gmb_perf_rows as $gpr) {
                                $gmb_table_rows[] = [
                                    'month'    => !empty($gpr['month_key']) ? date_i18n('M Y', strtotime($gpr['month_key'].'-01')) : '-',
                                    'calls'    => (int)$gpr['calls'],
                                    'dirs'     => (int)$gpr['clicks_directions'],
                                    'bookings' => (int)$gpr['bookings'],
                                    'web'      => (int)$gpr['clicks_website'],
                                ];
                            }
                            $gmb_table_json = wp_json_encode($gmb_table_rows);
                            $gmb_posts_table = [];
                            foreach ($gmb_posts_rows as $pr) {

                                $gmb_posts_table[] = [
                                    'title'  => $pr['title'] ?? '-',
                                    'url'    => $pr['post_url'] ?? '#',
                                    'status' => $pr['status'] ?? 'Published',
                                    'month'  => !empty($pr['month_key']) ? date_i18n('M Y', strtotime($pr['month_key'].'-01')) : '-',
                                ];
                            }
                            $gmb_posts_table_json = wp_json_encode($gmb_posts_table);
                            ?>
                            <!-- Perf Table Card -->
                            <?php if ($gmb_show['perf_table']) :
                                $gmb_perf_front = get_option("seo_dash_gmb_perf_front_{$rid}", ['cols' => ['row_num', 'month', 'calls', 'directions', 'bookings', 'clicks_website']]);
                                $gmb_perf_cols  = is_array($gmb_perf_front['cols'] ?? null) ? $gmb_perf_front['cols'] : ['row_num', 'month', 'calls', 'directions', 'bookings', 'clicks_website'];
                            ?>
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px;margin-top:16px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <h3 style="font-size:15px;font-weight:700;margin:0;">GMB Monthly Performance</h3>
                                        <span id="seo-gmb-perf-count" style="font-size:11px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:99px;padding:2px 10px;color:var(--cc-muted);"></span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                        <input id="seo-gmb-perf-search" type="text" placeholder="Search..." style="padding:6px 12px;font-size:12px;border-radius:6px;border:1px solid var(--cc-border);background:var(--cc-surf);color:var(--cc-text);outline:none;min-width:140px;" />
                                        <select id="seo-gmb-perf-month-filter" style="padding:6px 10px;font-size:12px;border-radius:6px;border:1px solid var(--cc-border);background:var(--cc-surf);color:var(--cc-text);outline:none;cursor:pointer;">
                                            <option value="">All Months</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="seo-gmb-tbl-pagination-top" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;"></div>
                                <div style="border:1px solid var(--cc-border);border-radius:12px;overflow:hidden;">
                                    <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;font-size:13px;" id="seo-gmb-perf-table" data-cols="<?php echo esc_attr(json_encode($gmb_perf_cols)); ?>">
                                        <thead>
                                            <tr style="position:sticky;top:0;z-index:2;background:var(--cc-surf);box-shadow:0 1px 0 var(--cc-border);">
                                                <?php if (in_array('row_num', $gmb_perf_cols)) : ?><th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);width:40px;">#</th><?php endif; ?>
                                                <?php if (in_array('month', $gmb_perf_cols)) : ?><th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);">Month</th><?php endif; ?>
                                                <?php if (in_array('calls', $gmb_perf_cols)) : ?><th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);white-space:nowrap;">Calls</th><?php endif; ?>
                                                <?php if (in_array('directions', $gmb_perf_cols)) : ?><th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);white-space:nowrap;">Directions</th><?php endif; ?>
                                                <?php if (in_array('bookings', $gmb_perf_cols)) : ?><th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);white-space:nowrap;">Bookings</th><?php endif; ?>
                                                <?php if (in_array('clicks_website', $gmb_perf_cols)) : ?><th style="padding:10px 12px;text-align:right;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);white-space:nowrap;">Website Clicks</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody id="seo-gmb-tbl-body"></tbody>
                                    </table>
                                    </div>
                                </div>
                                <div id="seo-gmb-tbl-pagination-bottom" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>
                            </div>
                            <?php endif; ?>


                            <?php if (!empty($gmb_posts_chart_labels)) : ?>
                            <?php if ($gmb_show['posts_chart'] && $gmb_posts_show) : ?>
                            <!-- GMB Posts Chart -->
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px;margin-top:16px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                                    <h3 style="font-size:15px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px;"><?php echo esc_html($gmb_posts_title); ?></h3>
                                </div>
                                <div style="position:relative;height:240px;">
                                    <canvas id="seo-gmb-posts-chart"
                                        data-chart-type="<?php echo esc_attr($gmb_posts_type); ?>"
                                        data-labels="<?php echo esc_attr(wp_json_encode($gmb_posts_chart_labels)); ?>"
                                        data-values="<?php echo esc_attr(wp_json_encode($gmb_posts_chart_data)); ?>"
                                        data-published="<?php echo intval($gmb_posts_published); ?>"
                                        data-draft="<?php echo intval($gmb_posts_draft); ?>">
                                    </canvas>
                                </div>
                            </div>
                            <?php endif; ?>
                            <script>
                            (function(){
                                var gmbPostsChart = null;
                                function buildGmbPostsChart() {
                                    var el = document.getElementById('seo-gmb-posts-chart');
                                    if (!el || typeof Chart === 'undefined') return;
                                    var labels    = JSON.parse(el.dataset.labels    || '[]');
                                    var values    = JSON.parse(el.dataset.values    || '[]');
                                    var published = parseInt(el.dataset.published   || '0');
                                    var draft     = parseInt(el.dataset.draft       || '0');
                                    var typeRaw   = el.dataset.chartType || 'bar';
                                    var isDark    = !!(document.getElementById('seo-client-app') && document.getElementById('seo-client-app').classList.contains('seo-dark'));
                                    var gridC     = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
                                    var txtC      = isDark ? 'rgba(255,255,255,.55)' : '#64748b';
                                    if (gmbPostsChart) gmbPostsChart.destroy();
                                    if (typeRaw === 'doughnut' || typeRaw === 'pie' || typeRaw === 'polarArea') {
                                        var pChartType = typeRaw === 'pie' ? 'pie' : (typeRaw === 'polarArea' ? 'polarArea' : 'doughnut');
                                        gmbPostsChart = new Chart(el, {
                                            type: pChartType,
                                            data: {
                                                labels: ['Published', 'Draft'],
                                                datasets: [{ data: [published, draft], backgroundColor: ['#10b981CC','#f59e0bCC'], borderWidth: 2, borderColor: 'transparent', hoverOffset: 6 }]
                                            },
                                            options: {
                                                cutout: pChartType === 'doughnut' ? '62%' : undefined,
                                                responsive: true, maintainAspectRatio: false,
                                                animation: { duration: 600 },
                                                plugins: {
                                                    legend: { display: true, position: 'bottom', labels: { color: txtC, font: { size: 12 }, boxWidth: 12, padding: 16 } },
                                                    tooltip: {
                                                        backgroundColor: isDark ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.97)',
                                                        titleColor: isDark ? '#e2e8f0' : '#1e293b',
                                                        bodyColor: isDark ? '#94a3b8' : '#475569',
                                                        borderColor: isDark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.08)',
                                                        borderWidth: 1, padding: 10, cornerRadius: 8
                                                    }
                                                }
                                            }
                                        });
                                    } else {
                                        var isHorizP = typeRaw === 'horizontalBar';
                                        var isAreaP  = typeRaw === 'area';
                                        var isLineP  = typeRaw === 'line' || isAreaP;
                                        var gmbPalette = ['#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#ec4899', '#14b8a6', '#f97316', '#84cc16'];
                                        var pBgColor = (isHorizP || (!isLineP && !isAreaP)) ? values.map(function(_, i){ return gmbPalette[i % gmbPalette.length]; }) : (isAreaP ? 'rgba(99,102,241,0.35)' : 'rgba(99,102,241,0.15)');
                                        if (isAreaP && el.getContext) {
                                            var c2dP = el.getContext('2d');
                                            var gradP = c2dP.createLinearGradient(0, 0, 0, 200);
                                            gradP.addColorStop(0, 'rgba(99,102,241,0.45)');
                                            gradP.addColorStop(1, 'rgba(99,102,241,0.02)');
                                            pBgColor = gradP;
                                        }
                                        gmbPostsChart = new Chart(el, {
                                            type: isLineP ? 'line' : 'bar',
                                            data: {
                                                labels: labels,
                                                datasets: [{
                                                    label: 'Posts Published',
                                                    data: values,
                                                    backgroundColor: pBgColor,
                                                    borderColor: isLineP ? '#6366f1' : (isHorizP ? 'transparent' : '#6366f1'),
                                                    borderWidth: isLineP ? 2 : 0,
                                                    borderRadius: isLineP ? 0 : 8,
                                                    fill: isAreaP,
                                                    tension: 0.45,
                                                    pointRadius: isLineP ? 5 : 0,
                                                    pointHoverRadius: 7,
                                                    pointBackgroundColor: '#6366f1'
                                                }]
                                            },
                                            options: {
                                                indexAxis: isHorizP ? 'y' : 'x',
                                                responsive: true, maintainAspectRatio: false,
                                                animation: { duration: 600 },
                                                plugins: {
                                                    legend: { display: false },
                                                    tooltip: {
                                                        backgroundColor: isDark ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.97)',
                                                        titleColor: isDark ? '#e2e8f0' : '#1e293b',
                                                        bodyColor: isDark ? '#94a3b8' : '#475569',
                                                        borderColor: isDark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.08)',
                                                        borderWidth: 1, padding: 10, cornerRadius: 8
                                                    }
                                                },
                                                scales: {
                                                    x: { grid: { color: gridC }, ticks: { color: txtC, font: { size: 11 } } },
                                                    y: { grid: { color: gridC }, ticks: { color: txtC, font: { size: 11 }, stepSize: 1 }, beginAtZero: true }
                                                }
                                            }
                                        });
                                    }
                                }
                                window.buildGmbPostsChart = buildGmbPostsChart;
                                function waitAndBuildPosts(n) {
                                    if (typeof Chart !== 'undefined') { buildGmbPostsChart(); return; }
                                    if ((n||0) > 60) return;
                                    setTimeout(function(){ waitAndBuildPosts((n||0)+1); }, 100);
                                }
                                waitAndBuildPosts(0);
                                document.addEventListener('DOMContentLoaded', function(){
                                    // Re-render when GMB posts search filters table
                                    var searchInput = document.getElementById('seo-cl-gmb-posts-search');
                                    if (searchInput) {
                                        searchInput.addEventListener('keyup', function(){
                                            var val = this.value.toLowerCase();
                                            var rows = document.querySelectorAll('#seo-cl-gmb-posts-tbody .seo-cl-gmb-post-row');
                                            var filteredByMonth = {};
                                            var pub = 0, dr = 0;
                                            rows.forEach(function(row){
                                                var visible = row.textContent.toLowerCase().indexOf(val) > -1;
                                                if (visible) {
                                                    var cells = row.querySelectorAll('td');
                                                    var monthText = cells[4] ? cells[4].innerText.trim() : 'Unknown';
                                                    filteredByMonth[monthText] = (filteredByMonth[monthText] || 0) + 1;
                                                    var statusText = cells[3] ? cells[3].innerText.trim() : '';
                                                    if (statusText.indexOf('Published') > -1) pub++; else dr++;
                                                }
                                            });
                                            var newLabels = Object.keys(filteredByMonth).sort();
                                            var newValues = newLabels.map(function(k){ return filteredByMonth[k]; });
                                            var canvas = document.getElementById('seo-gmb-posts-chart');
                                            if (canvas) {
                                                canvas.dataset.labels    = JSON.stringify(newLabels);
                                                canvas.dataset.values    = JSON.stringify(newValues);
                                                canvas.dataset.published = pub;
                                                canvas.dataset.draft     = dr;
                                                buildGmbPostsChart();
                                            }
                                        });
                                    }
                                });
                                window.seoGmbEnsureChart = function() {
                                    if (typeof window.buildGmbPerfChart === 'function') window.buildGmbPerfChart();
                                    if (typeof window.buildGmbPostsChart === 'function') window.buildGmbPostsChart();
                                };
                            })();
                            </script>
                            <?php endif; ?>

                            <!-- GMB Posts Table Card -->
                            <?php if ($gmb_show['posts_table']) :
                                $gmb_front = get_option("seo_dash_gmb_front_{$rid}", ['cols' => ['row_num', 'title', 'link', 'status', 'month']]);
                                $gmb_cols  = is_array($gmb_front['cols'] ?? null) ? $gmb_front['cols'] : ['row_num', 'title', 'link', 'status', 'month'];
                            ?>
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px;margin-top:16px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <h3 style="font-size:15px;font-weight:700;margin:0;">GMB Posts</h3>
                                        <span id="seo-gmb-posts-count" style="font-size:11px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:99px;padding:2px 10px;color:var(--cc-muted);"></span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                        <input id="seo-gmb-posts-search" type="text" placeholder="Search posts..." style="padding:6px 12px;font-size:12px;border-radius:6px;border:1px solid var(--cc-border);background:var(--cc-surf);color:var(--cc-text);outline:none;min-width:140px;" />
                                        <select id="seo-gmb-posts-month-filter" style="padding:6px 10px;font-size:12px;border-radius:6px;border:1px solid var(--cc-border);background:var(--cc-surf);color:var(--cc-text);outline:none;cursor:pointer;">
                                            <option value="">All Months</option>
                                        </select>
                                        <select id="seo-gmb-posts-status-filter" style="padding:6px 10px;font-size:12px;border-radius:6px;border:1px solid var(--cc-border);background:var(--cc-surf);color:var(--cc-text);outline:none;cursor:pointer;">
                                            <option value="">All Status</option>
                                            <option value="Published">Published</option>
                                            <option value="Draft">Draft</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="seo-gmb-posts-pagination-top" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;"></div>
                                <div style="border:1px solid var(--cc-border);border-radius:12px;overflow:hidden;">
                                    <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;font-size:13px;" id="seo-gmb-posts-table" data-cols="<?php echo esc_attr(json_encode($gmb_cols)); ?>">
                                        <thead>
                                            <tr style="position:sticky;top:0;z-index:2;background:var(--cc-surf);box-shadow:0 1px 0 var(--cc-border);">
                                                <?php if (in_array('row_num', $gmb_cols)) : ?><th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);width:40px;">#</th><?php endif; ?>
                                                <?php if (in_array('title', $gmb_cols)) : ?><th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);">Title</th><?php endif; ?>
                                                <?php if (in_array('link', $gmb_cols)) : ?><th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);white-space:nowrap;">Link</th><?php endif; ?>
                                                <?php if (in_array('status', $gmb_cols)) : ?><th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);white-space:nowrap;">Status</th><?php endif; ?>
                                                <?php if (in_array('month', $gmb_cols)) : ?><th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);white-space:nowrap;">Month</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody id="seo-gmb-posts-tbl-body"></tbody>
                                    </table>
                                    </div>
                                </div>
                                <div id="seo-gmb-posts-pagination-bottom" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>
                            </div>
                            <?php endif; ?>

                            <script>
                            (function(){
                                function makePager(cid, cur, total, filtered, psize, onPage) {
                                    var c = document.getElementById(cid); if(!c) return;
                                    var s=(cur-1)*psize+1, e=Math.min(cur*psize,filtered);
                                    var info=document.createElement('span'); info.style.cssText='font-size:12px;color:var(--cc-muted);';
                                    info.textContent = filtered===0 ? 'No results' : 'Showing '+s+'\u2013'+e+' of '+filtered;
                                    var nav=document.createElement('div'); nav.style.cssText='display:flex;gap:4px;align-items:center;';
                                    var bS='padding:5px 10px;border-radius:7px;border:1px solid var(--cc-border);background:var(--cc-surf);color:var(--cc-text);font-size:12px;font-weight:600;cursor:pointer;';
                                    function mkB(lbl,pg,dis,act){var b=document.createElement('button');b.textContent=lbl;b.style.cssText=act?bS+'background:#f59e0b;color:#fff;border-color:#f59e0b;':(dis?bS+'opacity:.4;cursor:default;':bS);if(!dis)b.addEventListener('click',function(){onPage(pg);});return b;}
                                    nav.appendChild(mkB('\u00ab',1,cur===1,false));
                                    nav.appendChild(mkB('\u2039',cur-1,cur===1,false));
                                    var sp=Math.max(1,cur-2),ep=Math.min(total,cur+2);
                                    for(var p=sp;p<=ep;p++) nav.appendChild(mkB(String(p),p,false,p===cur));
                                    nav.appendChild(mkB('\u203a',cur+1,cur===total,false));
                                    nav.appendChild(mkB('\u00bb',total,cur===total,false));
                                    c.innerHTML=''; c.appendChild(info); c.appendChild(nav);
                                }

                                /* ---- Performance Table ---- */
                                var PERF_ALL = <?php echo $gmb_table_json; ?>;
                                var PERF_PS = 20, perfPage = 1, perfFiltered = PERF_ALL.slice();
                                var perfMonthSel = document.getElementById('seo-gmb-perf-month-filter');
                                var perfMonths = {};
                                PERF_ALL.forEach(function(r){if(r.month&&!perfMonths[r.month]){perfMonths[r.month]=1;var o=document.createElement('option');o.value=r.month;o.textContent=r.month;perfMonthSel.appendChild(o);}});
                                function perfFilter(){
                                    var q=(document.getElementById('seo-gmb-perf-search').value||'').toLowerCase().trim();
                                    var mon=perfMonthSel.value||'';
                                    perfFiltered=PERF_ALL.filter(function(r){return(!mon||r.month===mon)&&(!q||r.month.toLowerCase().indexOf(q)>-1);});
                                    perfPage=1; perfRender();
                                }
                                function perfRender(){
                                    var tbody=document.getElementById('seo-gmb-tbl-body'); if(!tbody)return;
                                    tbody.innerHTML='';
                                    var off=(perfPage-1)*PERF_PS;
                                    var cntEl=document.getElementById('seo-gmb-perf-count');
                                    if(cntEl) cntEl.textContent=perfFiltered.length+' item'+(perfFiltered.length!==1?'s':'');

                                    var gmbPerfColsStr = document.getElementById('seo-gmb-perf-table') ? document.getElementById('seo-gmb-perf-table').getAttribute('data-cols') : '[]';
                                    var gmbPerfCols = [];
                                    try { gmbPerfCols = JSON.parse(gmbPerfColsStr || '[]'); } catch(e){}
                                    if (!gmbPerfCols || !gmbPerfCols.length) { gmbPerfCols = ['row_num', 'month', 'calls', 'directions', 'bookings', 'clicks_website']; }

                                    var slice = perfFiltered.slice(off,off+PERF_PS);
                                    if (!slice.length) {
                                        var tr=document.createElement('tr');
                                        tr.innerHTML='<td colspan="'+gmbPerfCols.length+'" style="text-align:center;padding:32px;color:var(--cc-muted);">No items found.</td>';
                                        tbody.appendChild(tr);
                                    } else slice.forEach(function(row,i){
                                        var tr=document.createElement('tr');
                                        tr.style.cssText='border-bottom:1px solid var(--cc-border);transition:background .15s;';
                                        tr.onmouseover=function(){this.style.filter='brightness(.97)';};
                                        tr.onmouseout=function(){this.style.filter='';};
                                        var cs='padding:9px 12px;',ns=cs+'text-align:right;font-variant-numeric:tabular-nums;';

                                        var html = '';
                                        if (gmbPerfCols.indexOf('row_num') > -1) html += '<td style="'+cs+'color:var(--cc-muted);font-size:11px;font-weight:600;">'+(off+i+1)+'</td>';
                                        if (gmbPerfCols.indexOf('month') > -1)   html += '<td style="'+cs+'font-weight:600;white-space:nowrap;color:var(--cc-text);">'+row.month+'</td>';
                                        if (gmbPerfCols.indexOf('calls') > -1)   html += '<td style="'+ns+'color:var(--cc-text);">'+row.calls.toLocaleString()+'</td>';
                                        if (gmbPerfCols.indexOf('directions') > -1) html += '<td style="'+ns+'color:var(--cc-text);">'+row.dirs.toLocaleString()+'</td>';
                                        if (gmbPerfCols.indexOf('bookings') > -1)   html += '<td style="'+ns+'color:var(--cc-text);">'+row.bookings.toLocaleString()+'</td>';
                                        if (gmbPerfCols.indexOf('clicks_website') > -1) html += '<td style="'+ns+'color:var(--cc-text);">'+row.web.toLocaleString()+'</td>';

                                        tr.innerHTML = html;
                                        tbody.appendChild(tr);
                                    });
                                    var tp=Math.max(1,Math.ceil(perfFiltered.length/PERF_PS));
                                    makePager('seo-gmb-tbl-pagination-top',   perfPage,tp,perfFiltered.length,PERF_PS,function(p){perfPage=p;perfRender();});
                                    makePager('seo-gmb-tbl-pagination-bottom',perfPage,tp,perfFiltered.length,PERF_PS,function(p){perfPage=p;perfRender();});
                                }
                                document.getElementById('seo-gmb-perf-search').addEventListener('input',perfFilter);
                                perfMonthSel.addEventListener('change',perfFilter);

                                /* ---- Posts Table ---- */
                                var POSTS_ALL = <?php echo $gmb_posts_table_json; ?>;
                                var POSTS_PS = 20, postsPage = 1, postsFiltered = POSTS_ALL.slice();
                                var postsMonthSel  = document.getElementById('seo-gmb-posts-month-filter');
                                var postsStatusSel = document.getElementById('seo-gmb-posts-status-filter');
                                var postsMonths={};
                                POSTS_ALL.forEach(function(r){if(r.month&&!postsMonths[r.month]){postsMonths[r.month]=1;var o=document.createElement('option');o.value=r.month;o.textContent=r.month;postsMonthSel.appendChild(o);}});
                                function postsFilter(){
                                    var q=(document.getElementById('seo-gmb-posts-search').value||'').toLowerCase().trim();
                                    var mon=postsMonthSel.value||'', st=postsStatusSel.value||'';
                                    postsFiltered=POSTS_ALL.filter(function(r){return(!mon||r.month===mon)&&(!st||r.status===st)&&(!q||r.title.toLowerCase().indexOf(q)>-1||r.month.toLowerCase().indexOf(q)>-1||r.status.toLowerCase().indexOf(q)>-1);});
                                    postsPage=1; postsRender();
                                }
                                function postsRender(){
                                    var tbody=document.getElementById('seo-gmb-posts-tbl-body'); if(!tbody)return;
                                    tbody.innerHTML='';
                                    var off=(postsPage-1)*POSTS_PS, slice=postsFiltered.slice(off,off+POSTS_PS);
                                    var cntEl=document.getElementById('seo-gmb-posts-count');
                                    if(cntEl) cntEl.textContent=postsFiltered.length+' post'+(postsFiltered.length!==1?'s':'');

                                    var gmbColsStr = document.getElementById('seo-gmb-posts-table') ? document.getElementById('seo-gmb-posts-table').getAttribute('data-cols') : '[]';
                                    var gmbCols = [];
                                    try { gmbCols = JSON.parse(gmbColsStr || '[]'); } catch(e){}
                                    if (!gmbCols || !gmbCols.length) { gmbCols = ['row_num', 'title', 'link', 'status', 'month']; }

                                    if(!slice.length){var tr=document.createElement('tr');tr.innerHTML='<td colspan="'+gmbCols.length+'" style="text-align:center;padding:32px;color:var(--cc-muted);">No posts found.</td>';tbody.appendChild(tr);}
                                    else slice.forEach(function(row,i){
                                        var tr=document.createElement('tr');
                                        tr.style.cssText='border-bottom:1px solid var(--cc-border);transition:background .15s;';
                                        tr.onmouseover=function(){this.style.filter='brightness(.97)';};
                                        tr.onmouseout=function(){this.style.filter='';};
                                        var isPub=row.status==='Published';
                                        var badge='<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;'+(isPub?'background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3);':'background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);')+'">'+( isPub?'\u2705 Published':'\uD83D\uDCDD Draft')+'</span>';
                                        var cs='padding:9px 12px;';

                                        var html = '';
                                        if (gmbCols.indexOf('row_num') > -1) html += '<td style="'+cs+'color:var(--cc-muted);font-size:11px;font-weight:600;">'+(off+i+1)+'</td>';
                                        if (gmbCols.indexOf('title') > -1)   html += '<td style="'+cs+'font-weight:600;color:var(--cc-text);">'+row.title+'</td>';
                                        if (gmbCols.indexOf('link') > -1)    html += '<td style="'+cs+'text-align:center;"><a href="'+row.url+'" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:8px;background:var(--cc-primary);color:#fff;text-decoration:none;font-size:11px;font-weight:700;">View \u2197</a></td>';
                                        if (gmbCols.indexOf('status') > -1)  html += '<td style="'+cs+'text-align:center;">'+badge+'</td>';
                                        if (gmbCols.indexOf('month') > -1)   html += '<td style="'+cs+'color:var(--cc-muted);">'+row.month+'</td>';

                                        tr.innerHTML = html;
                                        tbody.appendChild(tr);
                                    });
                                    var tp=Math.max(1,Math.ceil(postsFiltered.length/POSTS_PS));
                                    makePager('seo-gmb-posts-pagination-top',   postsPage,tp,postsFiltered.length,POSTS_PS,function(p){postsPage=p;postsRender();});
                                    makePager('seo-gmb-posts-pagination-bottom',postsPage,tp,postsFiltered.length,POSTS_PS,function(p){postsPage=p;postsRender();});
                                }
                                document.getElementById('seo-gmb-posts-search').addEventListener('input',postsFilter);
                                postsMonthSel.addEventListener('change',postsFilter);
                                postsStatusSel.addEventListener('change',postsFilter);

                                function init(){ perfRender(); postsRender(); }
                                if(document.readyState!=='loading') init();
                                else document.addEventListener('DOMContentLoaded',init);
                            })();
                            </script>
                            <?php endif; // posts ?>
                            <?php endif; // perf rows ?>

                    </div><!-- /.seo-cl-panel-body -->
                    <?php endif; // empty/else ?>
                </div><!-- /.seo-cl-panel -->
            </div><!-- /.seo-cl-panel-tab -->
            <?php endif; // show gmb ?>

            <!-- ── Technical ─────────────────────────────── -->
            <?php if ($show['technical']) : ?>
            <div class="seo-cl-panel-tab" data-tab="technical" <?php echo $first_visible!=='technical'?'style="display:none;"':''; ?>>
                <?php
                $tech_speed = get_option("seo_dash_tech_speed_{$rid}", []);
                $tech_mobile  = isset($tech_speed['mobile'])  && $tech_speed['mobile']  !== '' ? intval($tech_speed['mobile'])  : null;
                $tech_desktop = isset($tech_speed['desktop']) && $tech_speed['desktop'] !== '' ? intval($tech_speed['desktop']) : null;
                $tech_date    = !empty($tech_speed['date'])       ? $tech_speed['date']       : '';
                $tech_report  = !empty($tech_speed['report_url']) ? $tech_speed['report_url'] : '';

                // Count audit issue statuses using SQL aggregation instead of loading all rows
                global $wpdb;
                $tech_counts = $wpdb->get_row($wpdb->prepare(
                    "SELECT 
                        SUM(CASE WHEN status = 'pass' THEN 1 ELSE 0 END) as passed,
                        SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warnings,
                        SUM(CASE WHEN status = 'fail' THEN 1 ELSE 0 END) as failed
                     FROM " . SEO_Dash_Database::$data_technical . "
                     WHERE report_id = %d AND trashed = 0",
                    $rid
                ), ARRAY_A);
                
                $tech_passed   = intval($tech_counts['passed'] ?? 0);
                $tech_warnings = intval($tech_counts['warnings'] ?? 0);
                $tech_failed   = intval($tech_counts['failed'] ?? 0);
                $tech_total = $tech_passed + $tech_warnings + $tech_failed;
                if ($tech_total > 0) {
                    $tech_health = round(($tech_passed / $tech_total) * 100);
                } else {
                    $tech_health = ($tech_mobile !== null || $tech_desktop !== null) ? 100 : null;
                }
                $tech_health_label = $tech_health !== null ? ($tech_health >= 80 ? 'Good' : ($tech_health >= 50 ? 'Fair' : 'Poor')) : '—';
                $tech_health_color = $tech_health !== null ? ($tech_health >= 80 ? '#10b981' : ($tech_health >= 50 ? '#f59e0b' : '#ef4444')) : 'var(--cc-muted)';

                // Format last audit date
                $tech_date_display = '';
                if ($tech_date) {
                    $ts = strtotime($tech_date);
                    $tech_date_display = $ts ? date_i18n('M j, Y', $ts) : esc_html($tech_date);
                }

                // Fetch all technical audit rows (needed for chart data and issues table)
                $tech_rows_all = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_technical, $rid, '', false, 500, 0 );
                ?>
                <div class="seo-cl-panel">
                    <div class="seo-cl-panel-hd"><h3>⚙️ Technical Audit</h3></div>
                    <div class="seo-cl-panel-body" style="padding:24px 20px;">

                        <!-- KPI Row -->
                        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:28px;">

                            <!-- Mobile Speed -->
                            <div style="flex:1;min-width:140px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid #f59e0b;position:relative;overflow:hidden;">
                                <div style="font-size:22px;">📱</div>
                                <div style="font-size:30px;font-weight:800;color:#f59e0b;line-height:1;"><?php echo $tech_mobile !== null ? $tech_mobile : '—'; ?></div>
                                <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Mobile Speed</div>
                                <div style="font-size:11px;color:var(--cc-subtle);">out of 100</div>
                            </div>

                            <!-- Desktop Speed -->
                            <div style="flex:1;min-width:140px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid #6366f1;position:relative;overflow:hidden;">
                                <div style="font-size:22px;">💻</div>
                                <div style="font-size:30px;font-weight:800;color:#6366f1;line-height:1;"><?php echo $tech_desktop !== null ? $tech_desktop : '—'; ?></div>
                                <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Desktop Speed</div>
                                <div style="font-size:11px;color:var(--cc-subtle);">out of 100</div>
                            </div>

                            <!-- Passed -->
                            <div style="flex:1;min-width:140px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid #10b981;">
                                <div style="font-size:22px;">✅</div>
                                <div style="font-size:30px;font-weight:800;color:#10b981;line-height:1;"><?php echo $tech_passed; ?></div>
                                <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Passed</div>
                                <div style="font-size:11px;color:var(--cc-subtle);">of <?php echo $tech_total; ?> checks</div>
                            </div>

                            <!-- Warnings -->
                            <div style="flex:1;min-width:140px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid #f59e0b;<?php echo $tech_warnings === 0 ? 'display:none;' : ''; ?>">
                                <div style="font-size:22px;">⚠️</div>
                                <div style="font-size:30px;font-weight:800;color:#f59e0b;line-height:1;"><?php echo $tech_warnings; ?></div>
                                <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Warnings</div>
                                <div style="font-size:11px;color:var(--cc-subtle);">of <?php echo $tech_total; ?> checks</div>
                            </div>



                            <!-- Health Score -->
                            <div style="flex:1;min-width:140px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid <?php echo $tech_health_color; ?>;">
                                <div style="font-size:22px;">🛡️</div>
                                <div style="font-size:30px;font-weight:800;color:<?php echo $tech_health_color; ?>;line-height:1;"><?php echo $tech_health !== null ? $tech_health.'%' : '—'; ?></div>
                                <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Health Score</div>
                                <div style="font-size:11px;color:<?php echo $tech_health_color; ?>;font-weight:600;"><?php echo $tech_health_label; ?></div>
                            </div>

                            <!-- Last Audit -->
                            <div style="flex:1;min-width:140px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid #8b5cf6;">
                                <div style="font-size:22px;">📅</div>
                                <div style="font-size:18px;font-weight:800;color:#8b5cf6;line-height:1.2;"><?php echo $tech_date_display ? esc_html($tech_date_display) : '—'; ?></div>
                                <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Last Audit</div>
                                <?php if ($tech_report) : ?>
                                <div style="font-size:11px;"><a href="<?php echo esc_url($tech_report); ?>" target="_blank" style="color:#8b5cf6;text-decoration:none;font-weight:600;">📎 Report Date</a></div>
                                <?php else : ?>
                                <div style="font-size:11px;color:var(--cc-subtle);">Report Date</div>
                                <?php endif; ?>
                            </div>

                        </div>

                        <?php
                        /* ── Chart data prep ── */
                        $tc_items=[]; $tc_cats=[]; $tc_labels=[];
                        $tc_cumpass=[]; $tc_cumwarn=[]; $tc_cumfail=[]; $cp2=$cw2=$cf2=0;
                        foreach($tech_rows_all as $_tr){
                            $sc=$_tr['status']==='pass'?100:($_tr['status']==='warning'?50:($_tr['status']==='fail'?0:-1));
                            if($sc>=0)$tc_items[]=['l'=>substr($_tr['issue_type'],0,35),'s'=>$sc,'st'=>$_tr['status']];
                            $tc_labels[]='#'.count($tc_cumpass+[0]);
                            $_lc=strtolower($_tr['issue_type'].' '.($_tr['description']??''));
                            if(preg_match('/ssl|https|redirect|certif|header/i',$_lc))$_cat='Security';
                            elseif(preg_match('/meta|title|canonical|h1|sitemap|robots|noindex|schema/i',$_lc))$_cat='On-Page SEO';
                            elseif(preg_match('/link|crawl|broken|url|anchor/i',$_lc))$_cat='Links & Crawl';
                            elseif(preg_match('/speed|lcp|cls|fcp|tbt|load|perform/i',$_lc))$_cat='Performance';
                            else$_cat='General';
                            if(!isset($tc_cats[$_cat]))$tc_cats[$_cat]=['p'=>0,'w'=>0,'f'=>0];
                            if($_tr['status']==='pass')$tc_cats[$_cat]['p']++;
                            elseif($_tr['status']==='warning')$tc_cats[$_cat]['w']++;
                            elseif($_tr['status']==='fail')$tc_cats[$_cat]['f']++;
                            if($_tr['status']==='pass')$cp2++;elseif($_tr['status']==='warning')$cw2++;elseif($_tr['status']==='fail')$cf2++;
                            $tc_cumpass[]=$cp2;$tc_cumwarn[]=$cw2;$tc_cumfail[]=$cf2;
                        }
                        $tc_has_items=count($tc_items)>0;
                        $tc_has_speed=$tech_mobile!==null||$tech_desktop!==null;
                        $tc_has_cats=count($tc_cats)>0;
                        ?>

                        <?php
                        $tc_charts_saved = get_option("seo_dash_charts_technical_{$rid}", []);
                        $tc_status_title = seo_dash_get_chart_title_saved($tc_charts_saved, 'status', '📊 Status Overview');
                        $tc_items_title  = seo_dash_get_chart_title_saved($tc_charts_saved, 'items', '📋 Item Scores');
                        ?>

                        <?php if($tc_has_items||$tc_has_speed): ?>
                        <!-- Row 1: Status Overview + Item Scores -->
                        <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;margin-bottom:16px;">
                            <!-- Status Overview Donut -->
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:18px;">
                                <div style="font-size:12px;font-weight:700;color:var(--cc-text);margin-bottom:12px;"><?php echo esc_html($tc_status_title); ?></div>
                                <div style="position:relative;width:120px;height:120px;margin:0 auto 12px;">
                                    <canvas id="tc-donut-cl" width="120" height="120"></canvas>
                                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                                        <div style="font-size:18px;font-weight:800;color:var(--cc-text);"><?php echo $tech_total; ?></div>
                                        <div style="font-size:10px;color:var(--cc-muted);">checks</div>
                                    </div>
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;font-size:11px;">
                                    <span style="color:#10b981;">● Pass (<?php echo $tech_passed; ?>)</span>
                                    <span style="color:#f59e0b;">● Warn (<?php echo $tech_warnings; ?>)</span>
                                    <span style="color:#ef4444;">● Fail (<?php echo $tech_failed; ?>)</span>
                                </div>
                            </div>
                            <!-- Item Scores Horizontal Bar (scrollable) -->
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:18px;">
                                <div style="font-size:12px;font-weight:700;color:var(--cc-text);margin-bottom:10px;"><?php echo esc_html($tc_items_title); ?> <span style="font-weight:400;color:var(--cc-muted);font-size:10px;">(scroll to see all)</span></div>
                                <div style="overflow-y:auto;max-height:240px;border-radius:6px;">
                                    <div style="position:relative;height:<?php echo max(140, count($tc_items)*26); ?>px;min-height:140px;">
                                        <canvas id="tc-items-cl"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Performance Scores + Pass Rate -->
                        <?php if($tc_has_speed||$tc_has_items): ?>
                        <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px;">
                            <?php if($tc_has_speed): ?>
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:18px;">
                                <div style="font-size:12px;font-weight:700;color:var(--cc-text);margin-bottom:10px;">⚡ Performance Scores</div>
                                <div style="position:relative;height:90px;">
                                    <canvas id="tc-perf-cl"></canvas>
                                </div>
                            </div>
                            <?php else: ?><div></div><?php endif; ?>
                            <?php if($tc_has_items): ?>
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:18px;display:flex;flex-direction:column;align-items:center;">
                                <div style="font-size:12px;font-weight:700;color:var(--cc-text);margin-bottom:8px;align-self:flex-start;">🏆 Pass Rate</div>
                                <div style="position:relative;width:110px;height:110px;">
                                    <canvas id="tc-passrate-cl" width="110" height="110"></canvas>
                                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                                        <div style="font-size:18px;font-weight:800;color:<?php echo $tech_health_color; ?>;"><?php echo $tech_health!==null?$tech_health.'%':'—'; ?></div>
                                        <div style="font-size:10px;color:var(--cc-muted);">passed</div>
                                    </div>
                                </div>
                                <div style="font-size:12px;font-weight:700;color:<?php echo $tech_health_color; ?>;margin-top:6px;"><?php echo $tech_health_label; ?> 👍</div>
                            </div>
                            <?php else: ?><div></div><?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if($tc_has_cats): ?>
                        <!-- Row 3: Results by Category -->
                        <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:18px;margin-bottom:16px;">
                            <div style="font-size:12px;font-weight:700;color:var(--cc-text);margin-bottom:8px;">📁 Results by Category</div>
                            <div style="position:relative;height:140px;">
                                <canvas id="tc-cats-cl"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($tc_has_items): ?>
                        <!-- Row 4: Cumulative Trend + Score Distribution -->
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:18px;">
                                <div style="font-size:12px;font-weight:700;color:var(--cc-text);margin-bottom:10px;">📈 Cumulative Trend</div>
                                <div style="position:relative;height:150px;">
                                    <canvas id="tc-trend-cl"></canvas>
                                </div>
                            </div>
                            <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;padding:18px;">
                                <div style="font-size:12px;font-weight:700;color:var(--cc-text);margin-bottom:10px;">🎯 Score Distribution</div>
                                <div style="position:relative;height:200px;">
                                    <canvas id="tc-dist-cl"></canvas>
                                </div>
                                <div style="text-align:center;font-size:11px;font-weight:600;margin-top:8px;display:flex;justify-content:center;gap:14px;">
                                    <span style="color:#10b981;">Pass = 100pts</span>
                                    <span style="color:#f59e0b;">Warning = 50pts</span>
                                    <span style="color:#ef4444;">Fail = 0pts</span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <script>
                        function initTechChartsClient(){
                            if(window._techChartsClDone)return;
                            if(typeof Chart==='undefined'){
                                setTimeout(initTechChartsClient,100);
                                return;
                            }
                            window._techChartsClDone=true;
                            var isDark=document.getElementById('seo-client-app')&&document.getElementById('seo-client-app').classList.contains('seo-dark');
                            var txtC=isDark?'rgba(255,255,255,.65)':'#475569';
                            var gridC=isDark?'rgba(255,255,255,.08)':'rgba(0,0,0,.06)';
                            var C_PASS='#10b981',C_WARN='#f59e0b',C_FAIL='#ef4444',C_NA='#94a3b8';

                            <?php if($tc_has_items): ?>
                            /* taC1 — Status Overview Donut */
                            var tcTotal=<?php echo $tech_total; ?>;
                            new Chart(document.getElementById('tc-donut-cl'),{
                                type:'doughnut',
                                data:{labels:['Pass','Warning','Fail'],datasets:[{data:[<?php echo $tech_passed.','.$tech_warnings.','.$tech_failed; ?>],backgroundColor:[C_PASS,C_WARN,C_FAIL],borderWidth:2,borderColor:'transparent',hoverOffset:6,hoverBorderColor:'rgba(255,255,255,.5)'}]},
                                options:{cutout:'65%',animation:{duration:700,animateRotate:true},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){var pct=tcTotal>0?Math.round(c.raw/tcTotal*100):0;return c.label+': '+c.raw+' ('+pct+'%)';}}}},responsive:true,maintainAspectRatio:true}
                            });

                            /* taC2 — Item Scores horizontal bar (one bar per audit row) */
                            var tcItems=<?php echo json_encode(array_values($tc_items)); ?>;
                            var tcColors=tcItems.map(function(i){return i.st==='pass'?C_PASS:(i.st==='warning'?C_WARN:(i.st==='fail'?C_FAIL:C_NA));});
                            var tcItemCanvas=document.getElementById('tc-items-cl');
                            if(tcItemCanvas){new Chart(tcItemCanvas,{
                                type:'bar',
                                data:{labels:tcItems.map(function(i){return i.l;}),datasets:[{data:tcItems.map(function(i){return i.s;}),backgroundColor:tcColors,borderRadius:4,barThickness:18,maxBarThickness:22}]},
                                options:{indexAxis:'y',animation:{duration:600},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){var s=tcItems[c.dataIndex].st;return (s==='pass'?'✅ Pass':s==='warning'?'⚠️ Warning':s==='fail'?'❌ Fail':'— N/A');}}}},scales:{x:{min:0,max:100,grid:{color:gridC},ticks:{color:txtC,callback:function(v){return v;}}},y:{grid:{display:false},ticks:{color:txtC,font:{size:10},maxTicksLimit:999,autoSkip:false}}},responsive:true,maintainAspectRatio:false}
                            });}

                            /* taC4 — Pass Rate radial donut */
                            var prVal=<?php echo $tech_health!==null?$tech_health:0; ?>;
                            new Chart(document.getElementById('tc-passrate-cl'),{
                                type:'doughnut',
                                data:{datasets:[{data:[prVal,100-prVal],backgroundColor:['<?php echo $tech_health_color; ?>','rgba(148,163,184,.15)'],borderWidth:0}]},
                                options:{cutout:'72%',plugins:{legend:{display:false},tooltip:{display:false}},responsive:true,maintainAspectRatio:true}
                            });
                            <?php endif; ?>

                            <?php if($tc_has_speed): ?>
                            /* taC3 — Performance Scores bar */
                            var perfLabels=[],perfData=[],perfColors=[];
                            <?php if($tech_mobile!==null): ?>perfLabels.push('📱 Mobile');perfData.push(<?php echo $tech_mobile; ?>);perfColors.push('#f59e0b');<?php endif; ?>
                            <?php if($tech_desktop!==null): ?>perfLabels.push('💻 Desktop');perfData.push(<?php echo $tech_desktop; ?>);perfColors.push('#6366f1');<?php endif; ?>
                            new Chart(document.getElementById('tc-perf-cl'),{
                                type:'bar',
                                data:{labels:perfLabels,datasets:[{data:perfData,backgroundColor:perfColors,borderRadius:8,barThickness:28}]},
                                options:{indexAxis:'y',animation:{duration:600},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return c.raw+' / 100';}}}},scales:{x:{min:0,max:100,grid:{color:gridC},ticks:{color:txtC,callback:function(v){return v;}}},y:{grid:{display:false},ticks:{color:txtC,font:{size:13,weight:'600'}}}},responsive:true,maintainAspectRatio:false}
                            });
                            <?php endif; ?>

                            <?php if($tc_has_cats): ?>
                            /* taC5 — Results by Category stacked bar */
                            var catData=<?php echo json_encode(array_values($tc_cats)); ?>;
                            var catLabels=<?php echo json_encode(array_keys($tc_cats)); ?>;
                            new Chart(document.getElementById('tc-cats-cl'),{
                                type:'bar',
                                data:{labels:catLabels,datasets:[
                                    {label:'Pass',data:catData.map(function(c){return c.p;}),backgroundColor:C_PASS,borderRadius:4,stack:'s'},
                                    {label:'Warning',data:catData.map(function(c){return c.w;}),backgroundColor:C_WARN,stack:'s'},
                                    {label:'Fail',data:catData.map(function(c){return c.f;}),backgroundColor:C_FAIL,stack:'s'}
                                ]},
                                options:{animation:{duration:600},plugins:{legend:{labels:{color:txtC,font:{size:11},boxWidth:12}}},scales:{x:{grid:{color:gridC},ticks:{color:txtC}},y:{grid:{color:gridC},ticks:{color:txtC,stepSize:1},beginAtZero:true}},responsive:true,maintainAspectRatio:false}
                            });
                            <?php endif; ?>

                            <?php if($tc_has_items): ?>
                            /* taC6 — Cumulative Trend line */
                            var cumPass=<?php echo json_encode($tc_cumpass); ?>;
                            var cumWarn=<?php echo json_encode($tc_cumwarn); ?>;
                            var cumFail=<?php echo json_encode($tc_cumfail); ?>;
                            new Chart(document.getElementById('tc-trend-cl'),{
                                type:'line',
                                data:{labels:cumPass.map(function(_,i){return '#'+(i+1);}),datasets:[
                                    {label:'Pass',data:cumPass,borderColor:C_PASS,backgroundColor:'rgba(16,185,129,.12)',fill:true,tension:.35,pointRadius:2,borderWidth:2},
                                    {label:'Warning',data:cumWarn,borderColor:C_WARN,backgroundColor:'rgba(245,158,11,.08)',fill:true,tension:.35,pointRadius:2,borderWidth:2},
                                    {label:'Fail',data:cumFail,borderColor:C_FAIL,backgroundColor:'rgba(239,68,68,.08)',fill:true,tension:.35,pointRadius:2,borderWidth:2}
                                ]},
                                options:{animation:{duration:600},plugins:{legend:{display:true,position:'top',labels:{color:txtC,font:{size:11},boxWidth:10}}},scales:{x:{grid:{color:gridC},ticks:{color:txtC,font:{size:9},maxTicksLimit:12}},y:{grid:{color:gridC},ticks:{color:txtC,stepSize:1},beginAtZero:true}},responsive:true,maintainAspectRatio:false}
                            });
                            /* taC7 — Score Distribution bubble */
                            var distPass=[],distWarn=[],distFail=[];
                            tcItems.forEach(function(it){
                                var xBase=it.st==='pass'?3:(it.st==='warning'?2:1);
                                var pt={x:xBase+(Math.random()*0.5-0.25),y:Math.min(98,Math.max(2,it.s+(Math.random()*20-10))),r:12,label:it.l,status:it.st};
                                if(it.st==='pass')distPass.push(pt);
                                else if(it.st==='warning')distWarn.push(pt);
                                else distFail.push(pt);
                            });
                            new Chart(document.getElementById('tc-dist-cl'),{
                                type:'bubble',
                                data:{datasets:[
                                    {label:'Pass',data:distPass,backgroundColor:'rgba(16,185,129,.62)',borderColor:C_PASS,borderWidth:1},
                                    {label:'Warning',data:distWarn,backgroundColor:'rgba(245,158,11,.62)',borderColor:C_WARN,borderWidth:1},
                                    {label:'Fail',data:distFail,backgroundColor:'rgba(239,68,68,.62)',borderColor:C_FAIL,borderWidth:1}
                                ]},
                                options:{animation:{duration:600},plugins:{
                                    legend:{display:false},
                                    tooltip:{callbacks:{
                                        title:function(){return '';},
                                        label:function(c){return c.raw.label;},
                                        afterLabel:function(c){var s=c.raw.status;return s==='pass'?'\u2705 Pass \u2014 100pts':s==='warning'?'\u26a0\ufe0f Warning \u2014 50pts':'\u274c Fail \u2014 0pts';}
                                    }}
                                },scales:{
                                    x:{display:true,min:0.3,max:3.7,grid:{display:false},border:{display:false},
                                        ticks:{color:txtC,font:{size:11,weight:'600'},stepSize:1,
                                            callback:function(v){if(v===1)return 'Fail';if(v===2)return 'Warning';if(v===3)return 'Pass';return null;}
                                        }
                                    },
                                    y:{min:-5,max:105,grid:{color:gridC},ticks:{color:txtC,callback:function(v){return v+'%';}}}
                                },responsive:true,maintainAspectRatio:false}
                            });
                            <?php endif; ?>
                        }
                        </script>
                        <?php endif; /* end charts */ ?>

                        <?php if(!$tc_has_items && !$tc_has_speed): ?>
                        <!-- Empty state: no data yet -->
                        <div style="text-align:center;padding:48px 24px;border:1px dashed var(--cc-border);border-radius:14px;background:var(--cc-surf);margin-bottom:24px;">
                            <div style="font-size:48px;margin-bottom:16px;">⚙️</div>
                            <div style="font-size:16px;font-weight:700;color:var(--cc-text);margin-bottom:8px;">Technical Audit Coming Soon</div>
                            <div style="font-size:13px;color:var(--cc-muted);max-width:380px;margin:0 auto;">Your technical audit data is being prepared. Speed scores and audit checks will appear here once your specialist has run the analysis.</div>
                        </div>
                        <?php endif; ?>

                        <!-- Issues Table -->
                        <?php if ($tech_total > 0) : ?>
                        <div style="margin-top:12px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <h4 style="font-size:13px;font-weight:700;color:var(--cc-text);margin:0;">Audit Issues</h4>
                                    <span style="font-size:11px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:99px;padding:2px 10px;color:var(--cc-muted);"><?php echo $tech_total; ?> items</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="text" id="seo-cl-tech-search" placeholder="Search issues..." style="padding:6px 12px;font-size:12px;border-radius:6px;border:1px solid var(--cc-border);background:var(--cc-surf2);color:var(--cc-text);outline:none;width:250px;">
                                    <select id="seo-cl-tech-filter" style="padding:6px 10px;font-size:12px;border-radius:6px;border:1px solid var(--cc-border);background:var(--cc-surf2);color:var(--cc-text);outline:none;">
                                        <option value="">All Statuses</option>
                                        <option value="pass">Pass</option>
                                        <option value="warning">Warning</option>
                                        <option value="fail">Fail</option>
                                    </select>
                                </div>
                            </div>
                            <div style="border:1px solid var(--cc-border);border-radius:12px;overflow:hidden;">
                                <div style="overflow-y:auto;max-height:520px;">
                                <table style="width:100%;border-collapse:collapse;font-size:13px;" id="seo-cl-tech-table">
                                    <thead>
                                        <tr style="position:sticky;top:0;z-index:2;background:var(--cc-surf);box-shadow:0 1px 0 var(--cc-border);">
                                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);width:40px;">#</th>
                                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);">Audit Item</th>
                                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);width:110px;">Status</th>
                                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--cc-muted);border-bottom:1px solid var(--cc-border);">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $tn = 0; foreach ($tech_rows_all as $tr) :
                                            $tn++;
                                            $st_color = ['pass'=>'#10b981','warning'=>'#f59e0b','fail'=>'#ef4444','n/a'=>'#94a3b8'][$tr['status']] ?? '#94a3b8';
                                            $st_icon  = ['pass'=>'✅','warning'=>'⚠️','fail'=>'❌','n/a'=>'—'][$tr['status']] ?? '—';
                                            $row_bg   = ['pass'=>'rgba(16,185,129,.06)','warning'=>'rgba(245,158,11,.06)','fail'=>'rgba(239,68,68,.07)','n/a'=>'transparent'][$tr['status']] ?? 'transparent';
                                            $row_border = ['pass'=>'rgba(16,185,129,.15)','warning'=>'rgba(245,158,11,.15)','fail'=>'rgba(239,68,68,.15)','n/a'=>'var(--cc-border)'][$tr['status']] ?? 'var(--cc-border)';
                                        ?>
                                        <tr data-status="<?php echo esc_attr($tr['status']); ?>" style="background:<?php echo $row_bg; ?>;border-bottom:1px solid <?php echo $row_border; ?>;transition:background .15s;" onmouseover="this.style.filter='brightness(.97)'" onmouseout="this.style.filter=''">
                                            <td style="padding:9px 12px;color:var(--cc-muted);font-size:11px;font-weight:600;"><?php echo $tn; ?></td>
                                            <td style="padding:9px 12px;font-weight:600;color:var(--cc-text);"><?php echo esc_html($tr['issue_type']); ?></td>
                                            <td style="padding:9px 12px;">
                                                <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;background:<?php echo $st_color; ?>22;color:<?php echo $st_color; ?>;border:1px solid <?php echo $st_color; ?>44;">
                                                    <?php echo $st_icon; ?> <?php echo ucfirst($tr['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding:9px 12px;font-size:12px;color:var(--cc-muted);"><?php echo esc_html($tr['description'] ?? ''); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>
                        <div id="seo-cl-tech-pagination" style="padding:16px 20px;border-top:1px solid var(--cc-border);text-align:center;"></div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const searchInput = document.getElementById('seo-cl-tech-search');
                            const statusFilter = document.getElementById('seo-cl-tech-filter');
                            const table = document.getElementById('seo-cl-tech-table');
                            if (!searchInput || !statusFilter || !table) return;
                            
                            const tbody = table.querySelector('tbody');
                            const allRows = Array.from(tbody.querySelectorAll('tr'));
                            var techPage = 1;
                            var techPerPage = 20;
                            var techFiltered = [];

                            function filterTechTable() {
                                const searchTerm = searchInput.value.toLowerCase();
                                const statusTerm = statusFilter.value.toLowerCase();
                                techPage = 1;
                                techFiltered = allRows.filter(function(row) {
                                    const text = row.textContent.toLowerCase();
                                    const status = row.getAttribute('data-status') || '';
                                    const matchesSearch = text.includes(searchTerm);
                                    const matchesStatus = statusTerm === '' || status === statusTerm;
                                    return matchesSearch && matchesStatus;
                                });
                                renderTechPage();
                            }

                            function renderTechPage() {
                                var total = techFiltered.length;
                                var totalPages = Math.max(1, Math.ceil(total / techPerPage));
                                if (techPage > totalPages) techPage = totalPages;
                                var start = (techPage - 1) * techPerPage;
                                var end = start + techPerPage;
                                allRows.forEach(function(r) { r.style.display = 'none'; });
                                techFiltered.forEach(function(r, i) {
                                    r.style.display = (i >= start && i < end) ? '' : 'none';
                                });
                                renderTechPagination(total, totalPages);
                            }

                            function renderTechPagination(total, totalPages) {
                                var cont = document.getElementById('seo-cl-tech-pagination');
                                if (!cont) return;
                                if (totalPages <= 1) { cont.innerHTML = ''; return; }
                                var d1 = techPage === 1 ? ' disabled' : '';
                                var d2 = techPage === totalPages ? ' disabled' : '';
                                var h = '<div style="display:flex;align-items:center;gap:6px;justify-content:center;">';
                                h += '<span style="font-size:12px;color:var(--cc-text);margin-right:12px;">Page '+techPage+' of '+totalPages+' ('+total+' items)</span>';
                                h += '<button class="seo-bk-page-btn" onclick="techGoTo(1)"'+d1+'>«</button>';
                                h += '<button class="seo-bk-page-btn" onclick="techGoTo('+(techPage-1)+')"'+d1+'>‹</button>';
                                for (var p = Math.max(1, techPage-2); p <= Math.min(totalPages, techPage+2); p++) {
                                    h += p === techPage ? '<button class="seo-bk-page-btn active" disabled>'+p+'</button>' : '<button class="seo-bk-page-btn" onclick="techGoTo('+p+')">'+p+'</button>';
                                }
                                h += '<button class="seo-bk-page-btn" onclick="techGoTo('+(techPage+1)+')"'+d2+'>›</button>';
                                h += '<button class="seo-bk-page-btn" onclick="techGoTo('+totalPages+')"'+d2+'>»</button>';
                                h += '</div>';
                                cont.innerHTML = h;
                            }

                            window.techGoTo = function(p) {
                                techPage = p;
                                renderTechPage();
                                var tbl = document.getElementById('seo-cl-tech-table');
                                if (tbl) tbl.scrollIntoView({behavior:'smooth',block:'start'});
                            };

                            searchInput.addEventListener('input', filterTechTable);
                            statusFilter.addEventListener('change', filterTechTable);

                            // Initial render
                            techFiltered = allRows;
                            renderTechPage();
                        });
                        </script>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Backlinks ──────────────────────────────── -->
            <?php if ($show['backlinks']) : 
                $bk_front = get_option("seo_dash_bk_front_{$rid}", ['cols' => ['type', 'website', 'da', 'pa', 'spam', 'live_link', 'keyword', 'target_url', 'date', 'status']]);
                $bk_cols = $bk_front['cols'] ?? [];
                
                global $wpdb;
                $tbl_bk = SEO_Dash_Database::$data_backlinks;
                $kpi_bk_total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl_bk WHERE report_id = %d AND trashed = 0", $rid));
                $cur_month_key = current_time('Y-m');
                $kpi_bk_last_month = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl_bk WHERE report_id = %d AND trashed = 0 AND (DATE_FORMAT(found_date, '%%Y-%%m') = %s OR (found_date IS NULL AND month_key = %s))", $rid, $cur_month_key, $cur_month_key));
                $kpi_bk_types = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT link_type) FROM $tbl_bk WHERE report_id = %d AND trashed = 0", $rid));
            ?>
            <div class="seo-cl-panel-tab" data-tab="backlinks" <?php echo $first_visible!=='backlinks'?'style="display:none;"':''; ?>>
                
                <!-- Backlinks KPI Row -->
                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:20px;">
                    <!-- Total Backlinks -->
                    <div style="flex:1;min-width:140px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid #0ea5e9;">
                        <div style="font-size:22px;">🔗</div>
                        <div style="font-size:30px;font-weight:800;color:var(--cc-text);line-height:1;"><?php echo seo_fmt_num($kpi_bk_total); ?></div>
                        <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Total Backlinks</div>
                        <div style="font-size:11px;color:#0ea5e9;font-weight:500;">All Time</div>
                    </div>
                    <!-- Last Month -->
                    <div style="flex:1;min-width:140px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid #8b5cf6;<?php echo $kpi_bk_last_month === 0 ? 'display:none;' : ''; ?>">
                        <div style="font-size:22px;">📅</div>
                        <div style="font-size:30px;font-weight:800;color:var(--cc-text);line-height:1;"><?php echo $kpi_bk_last_month > 0 ? seo_fmt_num($kpi_bk_last_month) : '—'; ?></div>
                        <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Last Month</div>
                        <div style="font-size:11px;color:#8b5cf6;font-weight:500;"><?php echo esc_html(date_i18n('F Y', strtotime($cur_month_key . '-01'))); ?></div>
                    </div>
                    <!-- Link Types -->
                    <div style="flex:1;min-width:140px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid #10b981;<?php echo $kpi_bk_types === 0 ? 'display:none;' : ''; ?>">
                        <div style="font-size:22px;">📊</div>
                        <div style="font-size:30px;font-weight:800;color:var(--cc-text);line-height:1;"><?php echo $kpi_bk_types > 0 ? seo_fmt_num($kpi_bk_types) : '—'; ?></div>
                        <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Link Types</div>
                        <div style="font-size:11px;color:#10b981;font-weight:500;">Unique categories</div>
                    </div>
                    <!-- Type Overview -->
                    <div style="flex:1;min-width:140px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:20px 18px;display:flex;flex-direction:column;gap:8px;border-top:3px solid #6366f1;">
                        <div style="font-size:22px;">📌</div>
                        <div style="font-size:30px;font-weight:800;color:var(--cc-text);line-height:1;"><?php echo seo_fmt_num($kpi_bk_total); ?></div>
                        <div style="font-size:10px;font-weight:600;color:var(--cc-muted);text-transform:uppercase;letter-spacing:0.5px;">Type Overview</div>
                        <div style="font-size:11px;color:#6366f1;font-weight:500;">Click a type badge</div>
                    </div>
                </div>

                <!-- Backlinks Charts -->
                <?php
                $bk_charts_saved = get_option("seo_dash_charts_backlinks_{$rid}", []);
                $bk_dist_type   = seo_dash_get_chart_type_saved($bk_charts_saved, 'dist', 'polarArea');
                $bk_dist_title  = seo_dash_get_chart_title_saved($bk_charts_saved, 'dist', '📊 Type Distribution');
                $bk_trend_type  = seo_dash_get_chart_type_saved($bk_charts_saved, 'trend', 'area');
                $bk_trend_title = seo_dash_get_chart_title_saved($bk_charts_saved, 'trend', '📈 Monthly Trend');
                ?>
                <div style="display:flex; gap:20px; margin-bottom:20px; flex-wrap:wrap;" id="seo-bk-charts-row1">
                    <!-- Type Distribution -->
                    <div class="seo-cl-panel" style="flex:1; min-width:300px; margin-bottom:0;">
                        <div class="seo-cl-panel-hd" style="display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="font-size:15px; margin:0;"><?php echo esc_html($bk_dist_title); ?></h3>
                        </div>
                        <div class="seo-cl-panel-body" style="height:250px; position:relative; padding:16px;">
                            <canvas id="seo-bk-chart-canvas-dist" data-chart-type="<?php echo esc_attr($bk_dist_type); ?>"></canvas>
                        </div>
                    </div>
                    
                    <!-- Monthly Trend -->
                    <div class="seo-cl-panel" style="flex:2; min-width:300px; margin-bottom:0;">
                        <div class="seo-cl-panel-hd" style="display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="font-size:15px; margin:0;"><?php echo esc_html($bk_trend_title); ?></h3>
                        </div>
                        <div class="seo-cl-panel-body" style="height:250px; position:relative; padding:16px;">
                            <canvas id="seo-bk-chart-canvas-trend" data-chart-type="<?php echo esc_attr($bk_trend_type); ?>"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Type x Month -->
                <div class="seo-cl-panel" style="margin-bottom:20px;" id="seo-bk-charts-row2">
                    <div class="seo-cl-panel-hd" style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="font-size:15px; margin:0;">🧱 Type x Month</h3>
                    </div>
                    <div class="seo-cl-panel-body" style="height:250px; position:relative; padding:16px;">
                        <canvas id="seo-bk-chart-canvas-stacked"></canvas>
                    </div>
                </div>

                <div class="seo-cl-panel">
                    <style>
                    .seo-bk-page-btn {
                        display:inline-flex;align-items:center;justify-content:center;
                        min-width:32px;height:32px;padding:0 8px;
                        border-radius:6px;border:1px solid var(--cc-border);
                        background:var(--cc-surf);color:var(--cc-text);
                        font-size:13px;font-weight:600;cursor:pointer;
                        transition:all 0.2s ease;
                    }
                    .seo-bk-page-btn:hover:not(:disabled):not(.active) {
                        background:var(--cc-surf2);color:var(--cc-primary);border-color:var(--cc-primary);
                    }
                    .seo-bk-page-btn.active {
                        background:#0ea5e9;color:#fff;border-color:#0ea5e9;cursor:default;
                    }
                    .seo-bk-page-btn:disabled:not(.active) {
                        background:var(--cc-surf2);color:var(--cc-subtle);cursor:not-allowed;opacity:0.6;
                    }
                    </style>
                    <div class="seo-cl-panel-hd" style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center;">
                        <h3>🔗 Backlinks</h3>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="text" id="seo-bk-search" placeholder="Search backlinks..." style="padding:6px 12px;font-size:12px;border-radius:6px;border:1px solid var(--cc-border);background:var(--cc-surf2);color:var(--cc-text);outline:none;width:200px;">
                            <select id="seo-bk-type-filter" style="padding:6px 12px;font-size:12px;font-weight:600;border-radius:8px;border:1px solid var(--cc-border);background:var(--cc-surf2);color:var(--cc-text);outline:none;cursor:pointer;min-width:140px;">
                                <option value="">🌍 All Types</option>
                            </select>
                            <button class="seo-cl-export-btn" data-table="seo-cl-bk-table">⬇️ Export CSV</button>
                        </div>
                    </div>
                    <div id="seo-bk-pagination-top" style="display:flex;justify-content:flex-end;padding:12px 24px;border-bottom:1px solid var(--cc-border);"></div>
                    <div class="seo-cl-table-wrap">
                        <?php if(!in_array('row_num', $bk_cols)) array_unshift($bk_cols, 'row_num'); ?>
                        <table class="seo-cl-table" id="seo-cl-bk-table" data-cols="<?php echo esc_attr(json_encode($bk_cols)); ?>">
                            <thead>
                                <tr>
                                    <?php if(in_array('row_num', $bk_cols)) echo '<th style="width:40px;text-align:center;">S.No.</th>'; ?>
                                    <?php if(in_array('type', $bk_cols)) echo '<th>Type</th>'; ?>
                                    <?php if(in_array('website', $bk_cols)) echo '<th>Website URL</th>'; ?>
                                    <?php if(in_array('da', $bk_cols)) echo '<th style="text-align:center;">DA</th>'; ?>
                                    <?php if(in_array('pa', $bk_cols)) echo '<th style="text-align:center;">PA</th>'; ?>
                                    <?php if(in_array('spam', $bk_cols)) echo '<th style="text-align:center;">Spam%</th>'; ?>
                                    <?php if(in_array('live_link', $bk_cols)) echo '<th style="text-align:center;">Live Link</th>'; ?>
                                    <?php if(in_array('keyword', $bk_cols)) echo '<th>Keyword</th>'; ?>
                                    <?php if(in_array('target_url', $bk_cols)) echo '<th>Target URL</th>'; ?>
                                    <?php if(in_array('date', $bk_cols)) echo '<th>Date</th>'; ?>
                                    <?php if(in_array('month', $bk_cols)) echo '<th>Month</th>'; ?>
                                    <?php if(in_array('year', $bk_cols)) echo '<th>Year</th>'; ?>
                                    <?php if(in_array('status', $bk_cols)) echo '<th>Status</th>'; ?>
                                </tr>
                            </thead>
                            <tbody class="seo-cl-tbody"><tr><td colspan="<?php echo count($bk_cols); ?>" style="text-align:center;padding:32px;color:var(--cc-subtle);">Loading…</td></tr></tbody>
                        </table>
                    </div>
                    <div id="seo-bk-pagination" style="display:none;"></div>
                    <div class="seo-cl-tab-pagination" style="display:none;padding:16px 20px;border-top:1px solid var(--cc-border);text-align:center;"></div>
                </div>

                <script>
                var bkChartDist = null;
                var bkChartTrend = null;
                var bkChartStacked = null;
                var _lastBkRows = [];
                var _filteredBkRows = [];
                var _bkCurrentPage = 1;
                var _bkRowsPerPage = 20;
                var _bkActiveType = '';
                var _lastBkMeta = null; // stores meta from server (type_counts, month_totals, total_rows)

                // Helper: build type filter dropdown from a type_counts object {type: count, ...}
                function _bkBuildTypeFilter(typeCountsObj, totalCount) {
                    var typeFilter = document.getElementById('seo-bk-type-filter');
                    if (!typeFilter) return;
                    var bkTypeIcons = {
                        'dofollow': '🔗', 'nofollow': '🚫', 'sponsored': '💰', 'ugc': '👥',
                        'guest_post': '✍️', 'directory': '📂', 'social': '📱', 'forum': '💬',
                        'citation': '📑', 'press_release': '📰', 'infographic': '🖼️',
                        'broken_link': '❌', 'other': '📁'
                    };
                    var currVal = typeFilter.value;
                    var selectHtml = '<option value="">🌍 All Types (' + totalCount + ')</option>';
                    Object.keys(typeCountsObj).sort().forEach(function(t) {
                        var label = t.replace(/_/g, ' ').replace(/\b\w/g, function(l){ return l.toUpperCase(); });
                        var icon = bkTypeIcons[t] || '📁';
                        selectHtml += '<option value="' + t + '">' + icon + ' ' + label + ' (' + typeCountsObj[t] + ')</option>';
                    });
                    typeFilter.innerHTML = selectHtml;
                    typeFilter.value = currVal;
                }

                // rows  = full rows array (legacy / client-side mode), OR null for server-paged mode
                // meta  = metaResult from seo_dash_get_report_meta (server-paged mode)
                function renderBacklinksCharts(rows, meta) {
                    if (typeof Chart === 'undefined') {
                        var _retryRows = rows;
                        var _retryMeta = meta;
                        setTimeout(function(){ renderBacklinksCharts(_retryRows, _retryMeta); }, 100);
                        return;
                    }
                    
                    var colors = ['#0ea5e9', '#8b5cf6', '#10b981', '#f59e0b', '#ec4899', '#6366f1', '#14b8a6', '#f43f5e', '#84cc16'];

                    // ── SERVER-PAGED MODE: meta provided, rows is null ──────────────────
                    if (!rows && meta) {
                        _lastBkMeta = meta;
                        var typeCounts  = meta.type_counts  || {};
                        var monthTotals = meta.month_totals || {};
                        var totalRows   = meta.total_rows   || 0;

                        // Rebuild type filter from real server totals (all rows, not just page 1)
                        _bkBuildTypeFilter(typeCounts, totalRows);

                        // Build chart data from meta aggregates
                        var months = Object.keys(monthTotals).sort();
                        var monthLabels = months.map(function(m) {
                            var d = new Date(m + '-01');
                            return isNaN(d.getTime()) ? m : d.toLocaleString('default', { month: 'short', year: 'numeric' });
                        });
                        var trendData = months.map(function(m){ return monthTotals[m]; });

                        // Type distribution from type_counts
                        var types    = Object.keys(typeCounts).sort();
                        var distData = types.map(function(t){ return typeCounts[t]; });

                        // Stacked chart: we only have monthly totals (no per-type breakdown by month from meta),
                        // so render a simple single-series stacked bar using trendData
                        var stackedDatasets = [{ label: 'Backlinks', data: trendData, backgroundColor: '#0ea5e9', stack: 'Stack 0' }];

                        if (months.length === 1) {
                            months      = ['', months[0], ' '];
                            monthLabels = ['', monthLabels[0], ' '];
                            trendData   = [0, trendData[0], 0];
                            stackedDatasets[0].data = [0, stackedDatasets[0].data[0], 0];
                        }

                        var isDark = document.getElementById('seo-client-app') && document.getElementById('seo-client-app').classList.contains('seo-dark');
                        var txtC  = isDark ? 'rgba(255,255,255,.65)' : '#475569';
                        var gridC = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';

                        var distRaw = (document.getElementById('seo-bk-chart-type-dist') ? document.getElementById('seo-bk-chart-type-dist').value : '') || (document.getElementById('seo-bk-chart-canvas-dist') ? document.getElementById('seo-bk-chart-canvas-dist').dataset.chartType : '') || 'polarArea';
                        var distIsHoriz   = distRaw === 'horizontalBar';
                        var distIsArea    = distRaw === 'area';
                        var distIsStacked = distRaw === 'stackedBar';
                        var distIsPie     = distRaw === 'pie';
                        var distIsDoughnut = distRaw === 'doughnut';
                        var distIsPolar   = distRaw === 'polarArea';
                        var distIsRadar   = distRaw === 'radar';
                        var distIsCircular = distIsPie || distIsDoughnut || distIsPolar;
                        var distIsRadial   = distIsCircular || distIsRadar;
                        var distChartType  = 'bar';
                        if (distIsArea || distRaw === 'line') distChartType = 'line';
                        else if (distIsPie) distChartType = 'pie';
                        else if (distIsDoughnut) distChartType = 'doughnut';
                        else if (distIsPolar) distChartType = 'polarArea';
                        else if (distIsRadar) distChartType = 'radar';

                        if (bkChartDist) bkChartDist.destroy();
                        if (document.getElementById('seo-bk-chart-canvas-dist') && types.length) {
                            bkChartDist = new Chart(document.getElementById('seo-bk-chart-canvas-dist'), {
                                type: distChartType,
                                data: { labels: types, datasets: [{ data: distData, backgroundColor: colors.slice(0, types.length), borderWidth: distChartType === 'bar' ? 0 : 2, borderColor: '#ffffff' }] },
                                options: { maintainAspectRatio: false, indexAxis: distIsHoriz ? 'y' : 'x', plugins: { legend: { display: distIsRadial, position: distIsRadial ? 'right' : 'top', labels: { color: txtC, font: {size:11}, boxWidth:12 } } }, scales: distIsRadial ? (distIsRadar ? { r: { grid: { color: gridC }, ticks: { display: false }, pointLabels: { color: txtC } } } : {}) : { x: { stacked: distIsStacked, grid:{color:gridC}, ticks:{color:txtC} }, y: { stacked: distIsStacked, grid:{color:gridC}, ticks:{color:txtC,stepSize:1} } } }
                            });
                        }

                        var trendRaw = (document.getElementById('seo-bk-chart-type-trend') ? document.getElementById('seo-bk-chart-type-trend').value : '') || (document.getElementById('seo-bk-chart-canvas-trend') ? document.getElementById('seo-bk-chart-canvas-trend').dataset.chartType : '') || 'area';
                        var trendIsHoriz   = trendRaw === 'horizontalBar';
                        var trendIsArea    = trendRaw === 'area';
                        var trendIsStacked = trendRaw === 'stackedBar';
                        var trendIsPie     = trendRaw === 'pie';
                        var trendIsDoughnut = trendRaw === 'doughnut';
                        var trendIsPolar   = trendRaw === 'polarArea';
                        var trendIsRadar   = trendRaw === 'radar';
                        var trendIsCircular = trendIsPie || trendIsDoughnut || trendIsPolar;
                        var trendIsRadial   = trendIsCircular || trendIsRadar;
                        var trendChartType  = 'bar';
                        if (trendIsArea || trendRaw === 'line') trendChartType = 'line';
                        else if (trendIsPie) trendChartType = 'pie';
                        else if (trendIsDoughnut) trendChartType = 'doughnut';
                        else if (trendIsPolar) trendChartType = 'polarArea';
                        else if (trendIsRadar) trendChartType = 'radar';

                        if (bkChartTrend) bkChartTrend.destroy();
                        if (document.getElementById('seo-bk-chart-canvas-trend') && months.length) {
                            bkChartTrend = new Chart(document.getElementById('seo-bk-chart-canvas-trend'), {
                                type: trendChartType,
                                data: { labels: monthLabels, datasets: [{ label: 'Backlinks', data: trendData, borderColor: '#0ea5e9', backgroundColor: (trendIsRadial ? colors.slice(0, months.length) : (trendIsArea ? 'rgba(14,165,233,0.35)' : (trendChartType === 'line' ? 'rgba(14,165,233,0.15)' : '#0ea5e9'))), fill: trendIsArea, tension: 0.35, borderWidth: trendChartType === 'line' ? 2 : 0, borderRadius: trendChartType === 'bar' ? 4 : 0, pointRadius: trendChartType === 'line' ? 6 : 0, pointBackgroundColor: '#0ea5e9' }] },
                                options: { maintainAspectRatio: false, indexAxis: trendIsHoriz ? 'y' : 'x', plugins: { legend: { display: trendIsRadial, position: trendIsRadial ? 'right' : 'top' } }, scales: trendIsRadial ? {} : { x: { stacked: trendIsStacked, grid:{color:gridC}, ticks:{color:txtC} }, y: { stacked: trendIsStacked, grid:{color:gridC}, ticks:{color:txtC,stepSize:1}, beginAtZero:true } } }
                            });
                        }

                        var stackedType = document.getElementById('seo-bk-chart-type-stacked') ? document.getElementById('seo-bk-chart-type-stacked').value : 'stacked';
                        var isStacked = stackedType === 'stacked';
                        if (bkChartStacked) bkChartStacked.destroy();
                        var ds = JSON.parse(JSON.stringify(stackedDatasets));
                        if (!isStacked) ds.forEach(function(d){ delete d.stack; d.borderRadius = 4; });
                        if (document.getElementById('seo-bk-chart-canvas-stacked') && months.length) {
                            bkChartStacked = new Chart(document.getElementById('seo-bk-chart-canvas-stacked'), {
                                type: 'bar',
                                data: { labels: monthLabels, datasets: ds },
                                options: { maintainAspectRatio: false, plugins: { legend: { display: true, position: 'top', labels: { color: txtC, font:{size:11}, boxWidth:12 } } }, scales: { x: { stacked: isStacked, grid:{color:gridC}, ticks:{color:txtC} }, y: { stacked: isStacked, grid:{color:gridC}, ticks:{color:txtC,stepSize:1}, beginAtZero:true } } }
                            });
                        }
                        return;
                    }

                    // ── LEGACY MODE: full rows array passed (client-side, <10k rows) ──
                    if (rows) {
                        _lastBkRows = rows || [];
                        _filteredBkRows = [..._lastBkRows];
                        _bkCurrentPage = 1;
                        _bkActiveType = '';
                        
                        var typeCountsAll = {};
                        _lastBkRows.forEach(function(r) { 
                            if(r.link_type) typeCountsAll[r.link_type] = (typeCountsAll[r.link_type] || 0) + 1; 
                        });
                        _bkBuildTypeFilter(typeCountsAll, _lastBkRows.length);
                        setTimeout(function(){ filterBacklinksTable(); }, 10);
                        return;
                    }

                    var isDark = document.getElementById('seo-client-app') && document.getElementById('seo-client-app').classList.contains('seo-dark');
                    var txtC = isDark ? 'rgba(255,255,255,.65)' : '#475569';
                    var gridC = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
                    
                    var typeCounts = {};
                    var monthCounts = {};
                    var typeMonthCounts = {};

                    _filteredBkRows.forEach(function(r) {
                        var t = r.link_type ? r.link_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Other';
                        var m = r.month_key || (r.found_date ? r.found_date.substring(0, 7) : 'Unknown');
                        
                        typeCounts[t] = (typeCounts[t] || 0) + 1;
                        monthCounts[m] = (monthCounts[m] || 0) + 1;
                        
                        if(!typeMonthCounts[m]) typeMonthCounts[m] = {};
                        typeMonthCounts[m][t] = (typeMonthCounts[m][t] || 0) + 1;
                    });

                    // For dist, sort properly to align with colors if possible, but actually let's sort alphabetically to match pills
                    var types = Object.keys(typeCounts).sort();
                    var distData = types.map(t => typeCounts[t]);

                    var months = Object.keys(monthCounts).sort();
                    var monthLabels = months.map(m => {
                        if(m === 'Unknown') return m;
                        var d = new Date(m + '-01');
                        return isNaN(d.getTime()) ? m : d.toLocaleString('default', { month: 'short', year: 'numeric' });
                    });
                    var trendData = months.map(m => monthCounts[m]);

                    var stackedDatasets = types.map((t, i) => {
                        return {
                            label: t,
                            data: months.map(m => (typeMonthCounts[m] && typeMonthCounts[m][t]) ? typeMonthCounts[m][t] : 0),
                            backgroundColor: colors[i % colors.length],
                            stack: 'Stack 0'
                        };
                    });

                    if (months.length === 1) {
                        months = ['', months[0], ' '];
                        monthLabels = ['', monthLabels[0], ' '];
                        trendData = [0, trendData[0], 0];
                        stackedDatasets.forEach(ds => {
                            ds.data = [0, ds.data[0], 0];
                        });
                    }

                    var distCanvas = document.getElementById('seo-bk-chart-canvas-dist');
                    var distRaw = (distCanvas && distCanvas.dataset.chartType) ? distCanvas.dataset.chartType : 'polarArea';
                    var distIsHoriz   = distRaw === 'horizontalBar';
                    var distIsArea    = distRaw === 'area';
                    var distIsStacked = distRaw === 'stackedBar';
                    var distIsPie     = distRaw === 'pie';
                    var distIsDoughnut = distRaw === 'doughnut';
                    var distIsPolar   = distRaw === 'polarArea';
                    var distIsRadar   = distRaw === 'radar';
                    var distIsCircular = distIsPie || distIsDoughnut || distIsPolar;
                    var distIsRadial   = distIsCircular || distIsRadar;
                    var distChartType  = 'bar';
                    if (distIsArea || distRaw === 'line') distChartType = 'line';
                    else if (distIsPie) distChartType = 'pie';
                    else if (distIsDoughnut) distChartType = 'doughnut';
                    else if (distIsPolar) distChartType = 'polarArea';
                    else if (distIsRadar) distChartType = 'radar';

                    if(bkChartDist) bkChartDist.destroy();
                    if(distCanvas) {
                        bkChartDist = new Chart(distCanvas, {
                            type: distChartType,
                            data: {
                                labels: types,
                                datasets: [{
                                    data: distData,
                                    backgroundColor: colors.slice(0, types.length),
                                    borderWidth: 2,
                                    borderColor: '#ffffff'
                                }]
                            },
                            options: {
                                maintainAspectRatio: false,
                                indexAxis: distIsHoriz ? 'y' : 'x',
                                plugins: { legend: { display: distIsRadial, position: distIsRadial ? 'right' : 'top', labels: { color: txtC, font: {size:11}, boxWidth:12 } } },
                                scales: distIsRadial ? (distIsRadar ? { r: { grid: { color: gridC }, ticks: { display: false }, pointLabels: { color: txtC } } } : {}) : {
                                    x: { stacked: distIsStacked, grid: { color: gridC }, ticks: { color: txtC } },
                                    y: { stacked: distIsStacked, grid: { color: gridC }, ticks: { color: txtC, stepSize: 1 } }
                                }
                            }
                        });
                    }

                    var trendCanvas = document.getElementById('seo-bk-chart-canvas-trend');
                    var trendRaw = (trendCanvas && trendCanvas.dataset.chartType) ? trendCanvas.dataset.chartType : 'bar';
                    var trendIsHoriz   = trendRaw === 'horizontalBar';
                    var trendIsArea    = trendRaw === 'area';
                    var trendIsStacked = trendRaw === 'stackedBar';
                    var trendIsPie     = trendRaw === 'pie';
                    var trendIsDoughnut = trendRaw === 'doughnut';
                    var trendIsPolar   = trendRaw === 'polarArea';
                    var trendIsRadar   = trendRaw === 'radar';
                    var trendIsCircular = trendIsPie || trendIsDoughnut || trendIsPolar;
                    var trendIsRadial   = trendIsCircular || trendIsRadar;
                    var trendChartType  = 'bar';
                    if (trendIsArea || trendRaw === 'line') trendChartType = 'line';
                    else if (trendIsPie) trendChartType = 'pie';
                    else if (trendIsDoughnut) trendChartType = 'doughnut';
                    else if (trendIsPolar) trendChartType = 'polarArea';
                    else if (trendIsRadar) trendChartType = 'radar';

                    if(bkChartTrend) bkChartTrend.destroy();
                    if(trendCanvas) {
                        var bkBg = (trendIsRadial ? colors.slice(0, months.length) : (trendIsArea ? 'rgba(14,165,233,0.35)' : (trendChartType === 'line' ? 'rgba(14,165,233,0.15)' : '#0ea5e9')));
                        if (trendIsArea && trendCanvas.getContext) {
                            var ctxBk = trendCanvas.getContext('2d');
                            var gradBk = ctxBk.createLinearGradient(0, 0, 0, 200);
                            gradBk.addColorStop(0, 'rgba(14,165,233,0.45)');
                            gradBk.addColorStop(1, 'rgba(14,165,233,0.02)');
                            bkBg = gradBk;
                        }
                        bkChartTrend = new Chart(trendCanvas, {
                            type: trendChartType,
                            data: {
                                labels: monthLabels,
                                datasets: [{
                                    label: 'Backlinks',
                                    data: trendData,
                                    borderColor: '#0ea5e9',
                                    backgroundColor: bkBg,
                                    fill: trendIsArea,
                                    tension: 0.4,
                                    borderWidth: trendChartType === 'line' ? 2 : 0,
                                    borderRadius: trendChartType === 'bar' ? 4 : 0,
                                    pointRadius: trendChartType === 'line' ? 4 : 0,
                                    pointBackgroundColor: '#0ea5e9'
                                }]
                            },
                            options: {
                                maintainAspectRatio: false,
                                indexAxis: trendIsHoriz ? 'y' : 'x',
                                plugins: { legend: { display: trendIsRadial, position: trendIsRadial ? 'right' : 'top' } },
                                scales: trendIsRadial ? {} : {
                                    x: { stacked: trendIsStacked, grid: { color: gridC }, ticks: { color: txtC } },
                                    y: { stacked: trendIsStacked, grid: { color: gridC }, ticks: { color: txtC, stepSize: 1 }, beginAtZero: true }
                                }
                            }
                        });
                    }

                    var isStacked = true;
                    if(bkChartStacked) bkChartStacked.destroy();
                    
                    var ds = JSON.parse(JSON.stringify(stackedDatasets));
                    if (!isStacked) ds.forEach(d => { delete d.stack; d.borderRadius = 4; });

                    if(document.getElementById('seo-bk-chart-canvas-stacked')) {
                        bkChartStacked = new Chart(document.getElementById('seo-bk-chart-canvas-stacked'), {
                            type: 'bar',
                            data: { labels: monthLabels, datasets: ds },
                            options: {
                                maintainAspectRatio: false,
                                plugins: { legend: { display: true, position: 'top', labels: { color: txtC, font: {size:11}, boxWidth:12 } } },
                                scales: {
                                    x: { stacked: isStacked, grid: { color: gridC }, ticks: { color: txtC } },
                                    y: { stacked: isStacked, grid: { color: gridC }, ticks: { color: txtC, stepSize: 1 }, beginAtZero: true }
                                }
                            }
                        });
                    }
                }

                function filterBacklinksTable() {
                    var search = (document.getElementById('seo-bk-search') ? document.getElementById('seo-bk-search').value.toLowerCase() : '');
                    var type = _bkActiveType;

                    // ── SERVER-PAGED MODE: _lastBkRows is empty, filter visible tbody rows ──
                    if (_lastBkRows.length === 0) {
                        var tbody = document.querySelector('#seo-cl-bk-table tbody');
                        if (tbody) {
                            var trs = tbody.querySelectorAll('tr');
                            trs.forEach(function(tr) {
                                var text = tr.textContent.toLowerCase();
                                var matchSearch = search === '' || text.includes(search);
                                // For type filter in server-paged mode: match cell text since we don't have r.link_type
                                var matchType = type === '' || text.includes(type.replace(/_/g,' ').toLowerCase());
                                tr.style.display = (matchSearch && matchType) ? '' : 'none';
                            });
                        }
                        // Re-render charts using cached meta (type filter only affects visible rows, charts stay accurate)
                        if (_lastBkMeta) renderBacklinksCharts(null, _lastBkMeta);
                        return;
                    }

                    // ── LEGACY MODE: filter full _lastBkRows array ────────────────────────
                    _filteredBkRows = [];
                    var matchIndices = [];

                    _lastBkRows.forEach(function(r, i) {
                        var matchType = type === '' || r.link_type === type;
                        var text = [r.link_type, r.source_url, r.target_url, r.anchor_text, r.status, r.domain_rating, r.page_authority].join(' ').toLowerCase();
                        var matchSearch = search === '' || text.includes(search);
                        if (matchType && matchSearch) {
                            _filteredBkRows.push(r);
                            matchIndices.push(i);
                        }
                    });

                    var tbody = document.querySelector('#seo-cl-bk-table tbody');
                    if (tbody) {
                        var trs = tbody.querySelectorAll('tr');
                        if (trs.length === _lastBkRows.length) {
                            for(var j=0; j<trs.length; j++) trs[j].style.display = 'none';
                            
                            var start = (_bkCurrentPage - 1) * _bkRowsPerPage;
                            var end = start + _bkRowsPerPage;
                            for (var k = start; k < end && k < matchIndices.length; k++) {
                                trs[matchIndices[k]].style.display = '';
                            }
                        }
                    }
                    
                    renderBacklinksCharts();
                    renderBacklinksPaginationControls(matchIndices.length);
                }

                function renderBacklinksPaginationControls(totalMatches) {
                    var pagTop = document.getElementById('seo-bk-pagination-top');
                    var pagBot = document.getElementById('seo-bk-pagination');
                    var totalPages = Math.ceil(totalMatches / _bkRowsPerPage);
                    
                    if(totalPages <= 1) {
                        if(pagTop) pagTop.innerHTML = '';
                        if(pagBot) pagBot.innerHTML = '';
                        return;
                    }
                    
                    var html = '<div style="display:flex;align-items:center;gap:6px;">';
                    html += '<span style="font-size:12px;color:var(--cc-text);margin-right:12px;">Page '+_bkCurrentPage+' of '+totalPages+'</span>';
                    
                    // « First, ‹ Prev
                    if (_bkCurrentPage > 1) {
                        html += '<button class="seo-bk-page-btn" onclick="goToBkPage(1)">«</button>';
                        html += '<button class="seo-bk-page-btn" onclick="goToBkPage('+(_bkCurrentPage-1)+')">‹</button>';
                    } else {
                        html += '<button class="seo-bk-page-btn" disabled>«</button>';
                        html += '<button class="seo-bk-page-btn" disabled>‹</button>';
                    }
                    
                    // Pages: Show up to 5 pages around current
                    var startP = Math.max(1, _bkCurrentPage - 2);
                    var endP = Math.min(totalPages, _bkCurrentPage + 2);
                    
                    for (var p = startP; p <= endP; p++) {
                        if (p === _bkCurrentPage) {
                            html += '<button class="seo-bk-page-btn active" disabled>'+p+'</button>';
                        } else {
                            html += '<button class="seo-bk-page-btn" onclick="goToBkPage('+p+')">'+p+'</button>';
                        }
                    }
                    
                    // › Next, » Last
                    if (_bkCurrentPage < totalPages) {
                        html += '<button class="seo-bk-page-btn" onclick="goToBkPage('+(_bkCurrentPage+1)+')">›</button>';
                        html += '<button class="seo-bk-page-btn" onclick="goToBkPage('+totalPages+')">»</button>';
                    } else {
                        html += '<button class="seo-bk-page-btn" disabled>›</button>';
                        html += '<button class="seo-bk-page-btn" disabled>»</button>';
                    }
                    html += '</div>';

                    if(pagTop) pagTop.innerHTML = html;
                    if(pagBot) pagBot.innerHTML = html;
                }

                window.goToBkPage = function(page) {
                    _bkCurrentPage = page;
                    filterBacklinksTable();
                };

                document.addEventListener('DOMContentLoaded', function() {
                    var search = document.getElementById('seo-bk-search');
                    if(search) search.addEventListener('input', function() { _bkCurrentPage = 1; filterBacklinksTable(); });

                    var tf = document.getElementById('seo-bk-type-filter');
                    if(tf) {
                        tf.addEventListener('change', function() {
                            _bkActiveType = tf.value;
                            _bkCurrentPage = 1;
                            filterBacklinksTable();
                        });
                    }

                    // Listen for meta loaded from server-paged mode (fired by client-app.js)
                    // Updates type filter dropdown and charts from real server aggregates
                    if (typeof jQuery !== 'undefined') {
                        jQuery(document).on('seo:metaLoaded', function(e, tab, meta) {
                            if (tab !== 'backlinks' || !meta) return;
                            _lastBkMeta = meta;
                            renderBacklinksCharts(null, meta);
                        });
                    }
                });
                </script>
            </div>

            <?php endif; ?>

            <!-- ── Leads ──────────────────────────────────────── -->
            <?php if ($show['leads'] ?? true) : ?>
            <div class="seo-cl-panel-tab" data-tab="leads" style="display:none;">
                <?php
                global $wpdb;
                $ld_all = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM " . SEO_Dash_Database::$data_leads . " WHERE report_id = %d AND trashed = 0 ORDER BY id ASC",
                    $rid
                ), ARRAY_A);
                $ld_total = count($ld_all);
                $ld_kpi = ['new'=>0,'contacted'=>0,'checking'=>0,'qualified'=>0,'converted'=>0,'lost'=>0];
                foreach ($ld_all as $lr) {
                    $ls = strtolower($lr['status'] ?: 'new');
                    if (isset($ld_kpi[$ls])) $ld_kpi[$ls]++;
                }
                $ld_conv_pct = $ld_total > 0 ? round($ld_kpi['converted'] / $ld_total * 100) : 0;

                // ── Leads tab settings (admin-controlled, Client Dashboard → Leads) ──
                $ld_sections_vis = !(isset($meta['show_ld_sections']) && !$meta['show_ld_sections']);
                $ld_show_kpi_section    = $ld_sections_vis && !(isset($meta['show_ld_kpi_section']) && !$meta['show_ld_kpi_section']);
                $ld_show_status_chart_section = $ld_sections_vis && !(isset($meta['show_ld_status_chart_section']) && !$meta['show_ld_status_chart_section']);
                $ld_show_bar_chart_section    = $ld_sections_vis && !(isset($meta['show_ld_bar_chart_section']) && !$meta['show_ld_bar_chart_section']);
                $ld_show_table_section  = $ld_sections_vis && !(isset($meta['show_ld_table_section']) && !$meta['show_ld_table_section']);
                $ld_any_chart = $ld_show_status_chart_section || $ld_show_bar_chart_section;

                $ld_kpi_defs_front = [
                    'total'     => ['icon'=>'💼','sub'=>'All time',            'color'=>'#f97316','val'=>$ld_total,'badge'=>false,'label'=>'TOTAL LEADS'],
                    'new'       => ['icon'=>'🔠','sub'=>'Awaiting contact',    'color'=>'#8b5cf6','val'=>$ld_kpi['new'],'badge'=>true,'label'=>'NEW'],
                    'contacted' => ['icon'=>'📞','sub'=>'In progress',         'color'=>'#06b6d4','val'=>$ld_kpi['contacted'],'badge'=>false,'label'=>'CONTACTED'],
                    'qualified' => ['icon'=>'✅','sub'=>'Ready to convert',    'color'=>'#10b981','val'=>$ld_kpi['qualified'],'badge'=>false,'label'=>'QUALIFIED'],
                    'converted' => ['icon'=>'🎉','sub'=>$ld_conv_pct.'% of total','color'=>'#059669','val'=>$ld_kpi['converted'],'badge'=>false,'label'=>'CONVERTED'],
                    'lost'      => ['icon'=>'❌','sub'=>'Not converted',       'color'=>'#ef4444','val'=>$ld_kpi['lost'],'badge'=>false,'label'=>'LOST'],
                ];
                $ld_kpi_cfg = get_option("seo_dash_kpis_leads_{$rid}", get_option("seo_dash_ld_kpis_{$rid}", []));
                foreach ($ld_kpi_defs_front as $lkk => &$lkv) {
                    $saved = is_array($ld_kpi_cfg[$lkk] ?? null) ? $ld_kpi_cfg[$lkk] : [];
                    $lkv['show']  = isset($saved['show']) ? (bool)$saved['show'] : true;
                    $lkv['label'] = ($saved['label'] ?? '') !== '' ? $saved['label'] : $lkv['label'];
                }
                unset($lkv);

                // Load admin-configured chart types
                $ld_charts_saved = get_option("seo_dash_charts_leads_{$rid}", get_option("seo_dash_ld_charts_{$rid}", []));
                $ld_status_type    = seo_dash_get_chart_type_saved($ld_charts_saved, 'status', 'bar');
                $ld_status_title   = seo_dash_get_chart_title_saved($ld_charts_saved, 'status', 'Leads by Status');
                $ld_status_show    = seo_dash_get_chart_show_saved($ld_charts_saved, 'status', true);

                $ld_breakdown_type  = seo_dash_get_chart_type_saved($ld_charts_saved, 'breakdown', 'horizontalBar');
                $ld_breakdown_title = seo_dash_get_chart_title_saved($ld_charts_saved, 'breakdown', 'Status Breakdown');
                $ld_breakdown_show  = seo_dash_get_chart_show_saved($ld_charts_saved, 'breakdown', true);

                $ld_status_chart_cfg = ['show'=>$ld_status_show, 'title'=>$ld_status_title, 'type'=>$ld_status_type, 'color'=>'#8b5cf6'];
                $ld_bar_chart_cfg    = ['show'=>$ld_breakdown_show, 'title'=>$ld_breakdown_title, 'type'=>$ld_breakdown_type, 'color'=>'#06b6d4'];

                if (!function_exists('seo_dash_calc_lead_strength')) {
                    function seo_dash_calc_lead_strength($lr) {
                        $score = 0;
                        $name = trim($lr['name'] ?? '');
                        if (!empty($name) && strlen($name) >= 3) {
                            $score += 25;
                        }
                        $phone = preg_replace('/[^0-9]/', '', $lr['phone'] ?? '');
                        if (strlen($phone) >= 7) {
                            $score += 25;
                        }
                        $email = trim($lr['email'] ?? '');
                        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $score += 25;
                        }
                        $status = strtolower($lr['status'] ?? '');
                        $msg    = trim($lr['message'] ?? '');
                        if (!empty($msg) || in_array($status, ['qualified', 'converted'])) {
                            $score += 25;
                        }
                        if ($score >= 80) {
                            $label = 'High';
                            $color = '#10b981';
                        } elseif ($score >= 50) {
                            $label = 'Good';
                            $color = '#06b6d4';
                        } else {
                            $label = 'Weak';
                            $color = '#ef4444';
                        }
                        return [
                            'score' => $score,
                            'label' => $label,
                            'color' => $color,
                        ];
                    }
                }

                $ld_tbl_defs_front = ['num'=>true,'name'=>true,'phone'=>true,'email'=>true,'message'=>true,'status'=>true,'notes'=>true,'strength'=>true];
                $ld_tbl_cfg = get_option("seo_dash_ld_table_cols_{$rid}", []);
                $ld_tbl_show = [];
                foreach ($ld_tbl_defs_front as $ltk => $ltdef) {
                    $ld_tbl_show[$ltk] = isset($ld_tbl_cfg[$ltk]) ? (bool)$ld_tbl_cfg[$ltk] : true;
                }
                ?>
                <div class="seo-cl-panel">
                    <div class="seo-cl-panel-hd" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                        <h3>🎯 Leads</h3>
                        <div style="display:flex;gap:6px;background:var(--cc-surf2);padding:3px;border-radius:10px;border:1px solid var(--cc-border);">
                            <button type="button" class="seo-cl-leads-subtab-btn active" data-subtab="forms" style="padding:6px 14px;font-size:12px;font-weight:700;border-radius:8px;border:none;background:var(--cc-primary);color:#fff;cursor:pointer;transition:all .15s;">📋 Form Enquiries</button>
                            <button type="button" class="seo-cl-leads-subtab-btn" data-subtab="clicks" style="padding:6px 14px;font-size:12px;font-weight:700;border-radius:8px;border:none;background:transparent;color:var(--cc-muted);cursor:pointer;transition:all .15s;">🖱️ Click Tracking</button>
                        </div>
                    </div>

                    <!-- ── Sub-Pane 1: Form Enquiries (Client View) ──────── -->
                    <div id="seo-cl-leads-subpane-forms">

                    <!-- KPI Cards -->
                    <?php if ($ld_show_kpi_section) : ?>
                    <div style="padding:20px 20px 16px;">
                        <div style="display:flex;flex-wrap:wrap;gap:12px;" id="seo-ld-kpi-row">
                            <?php foreach ($ld_kpi_defs_front as $lkk => $lkv) :
                                if (empty($lkv['show'])) continue;
                                $ld_is_zero = ($lkk !== 'total' && empty($lkv['val']));
                            ?>
                            <div class="seo-ld-kpi-card" data-kpi="<?php echo esc_attr($lkk); ?>" style="flex:1;min-width:130px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:18px 20px;display:flex;flex-direction:column;align-items:flex-start;gap:6px;border-top:3px solid <?php echo esc_attr($lkv['color']); ?>;position:relative;<?php echo $ld_is_zero ? 'display:none;' : ''; ?>">
                                <?php if (!empty($lkv['badge'])) : ?>
                                <div style="position:absolute;top:12px;right:14px;background:<?php echo esc_attr($lkv['color']); ?>;color:#fff;font-size:9px;font-weight:800;padding:2px 7px;border-radius:20px;">NEW</div>
                                <?php endif; ?>
                                <div style="font-size:26px;"><?php echo $lkv['icon']; ?></div>
                                <div class="seo-ld-kpi-val" style="font-size:28px;font-weight:800;color:var(--cc-text);"><?php echo $lkv['val']; ?></div>
                                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--cc-subtle);"><?php echo esc_html($lkv['label']); ?></div>
                                <div class="seo-ld-kpi-sub" style="font-size:11px;color:<?php echo esc_attr($lkv['color']); ?>;font-weight:600;"><?php echo esc_html($lkv['sub']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Charts -->
                    <?php if ($ld_any_chart) : ?>
                    <div style="padding:0 20px 20px;display:flex;flex-wrap:wrap;gap:16px;">
                        <?php if ($ld_show_status_chart_section && !empty($ld_status_chart_cfg['show'])) : ?>
                        <div style="flex:1;min-width:240px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:18px 20px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
                                <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--cc-subtle);"><?php echo esc_html($ld_status_chart_cfg['title'] ?? 'Leads by Status'); ?></div>
                            </div>
                            <div style="height:220px;position:relative;">
                                <canvas id="seo-ld-chart-status" data-type="<?php echo esc_attr($ld_status_chart_cfg['type'] ?? 'doughnut'); ?>" data-color="<?php echo esc_attr($ld_status_chart_cfg['color'] ?? '#8b5cf6'); ?>"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($ld_show_bar_chart_section && !empty($ld_bar_chart_cfg['show'])) : ?>
                        <div style="flex:2;min-width:280px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:18px 20px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
                                <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--cc-subtle);"><?php echo esc_html($ld_bar_chart_cfg['title'] ?? 'Status Breakdown'); ?></div>
                            </div>
                            <div style="height:220px;position:relative;">
                                <canvas id="seo-ld-chart-bar" data-type="<?php echo esc_attr($ld_bar_chart_cfg['type'] ?? 'bar'); ?>" data-color="<?php echo esc_attr($ld_bar_chart_cfg['color'] ?? '#06b6d4'); ?>"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <script>
                    (function(){
                        var _ldDonut=null,_ldBar=null;
                        var _ldCounts = {
                            new:       <?php echo intval($ld_kpi['new']);?>,
                            contacted: <?php echo intval($ld_kpi['contacted']);?>,
                            checking:  <?php echo intval($ld_kpi['checking']);?>,
                            qualified: <?php echo intval($ld_kpi['qualified']);?>,
                            converted: <?php echo intval($ld_kpi['converted']);?>,
                            lost:      <?php echo intval($ld_kpi['lost']);?>
                        };
                        var _ldLabels = ['New','Contacted','Checking','Qualified','Converted','Lost'];
                        var _ldKeys   = ['new','contacted','checking','qualified','converted','lost'];
                        var _ldStatusColors = ['#8b5cf6','#06b6d4','#f59e0b','#10b981','#059669','#ef4444'];

                        function ldInitCharts(){
                            if (typeof Chart === 'undefined') return;
                            var ctxD = document.getElementById('seo-ld-chart-status');
                            var ctxB = document.getElementById('seo-ld-chart-bar');
                            if (!ctxD && !ctxB) return;
                            if (ctxD) { ctxD.removeAttribute('width'); ctxD.removeAttribute('height'); ctxD.style.width = '100%'; ctxD.style.height = '100%'; }
                            if (ctxB) { ctxB.removeAttribute('width'); ctxB.removeAttribute('height'); ctxB.style.width = '100%'; ctxB.style.height = '100%'; }
                            var vals = _ldKeys.map(function(k){ return _ldCounts[k]; });

                            var isDark = !!(document.getElementById('seo-client-app') && document.getElementById('seo-client-app').classList.contains('seo-dark'));
                            var txtC   = isDark ? 'rgba(255,255,255,.70)' : '#64748b';
                            var gridC  = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
                            var segBorderC = isDark ? 'rgba(30,41,59,0.8)' : '#ffffff';

                            if (ctxD) {
                                var selD = document.getElementById('seo-ld-chart-status-type');
                                var dTypeRaw = selD ? selD.value : (ctxD.dataset.type || 'doughnut');
                                var isHorizD    = dTypeRaw === 'horizontalBar';
                                var isAreaD     = dTypeRaw === 'area';
                                var isStackedD  = dTypeRaw === 'stackedBar';
                                var isPieD      = dTypeRaw === 'pie';
                                var isDoughnutD = dTypeRaw === 'doughnut';
                                var isPolarD    = dTypeRaw === 'polarArea';
                                var isRadarD    = dTypeRaw === 'radar';
                                var isCircularD = isPieD || isDoughnutD || isPolarD;
                                var isRadialD   = isCircularD || isRadarD;

                                var dType = 'bar';
                                if (isAreaD || dTypeRaw === 'line') dType = 'line';
                                else if (isPieD) dType = 'pie';
                                else if (isDoughnutD) dType = 'doughnut';
                                else if (isPolarD) dType = 'polarArea';
                                else if (isRadarD) dType = 'radar';

                                if (_ldDonut) { try { _ldDonut.destroy(); } catch(e){} _ldDonut = null; }

                                var dBgColor = (isRadialD || dType === 'bar' || isHorizD) ? _ldStatusColors : (isAreaD ? 'rgba(139,92,246,0.35)' : '#8b5cf6CC');
                                if (isAreaD && ctxD.getContext) {
                                    var c2dD = ctxD.getContext('2d');
                                    var gradD = c2dD.createLinearGradient(0, 0, 0, 200);
                                    gradD.addColorStop(0, 'rgba(139,92,246,0.45)');
                                    gradD.addColorStop(1, 'rgba(139,92,246,0.02)');
                                    dBgColor = gradD;
                                }

                                var totLd = vals.reduce(function(a,b){ return a+b; }, 0);
                                var ldChartLabels = isRadialD ? _ldLabels.map(function(lbl, i){
                                    var v = vals[i] || 0;
                                    var p = totLd > 0 ? Math.round((v / totLd) * 100) : 0;
                                    return lbl + ' (' + v.toLocaleString() + ' • ' + p + '%)';
                                }) : _ldLabels;

                                _ldDonut = new Chart(ctxD, {
                                    type: dType,
                                    data: {
                                        labels: ldChartLabels,
                                        datasets: [{
                                            label: 'Leads',
                                            data: vals,
                                            backgroundColor: dBgColor,
                                            borderColor: (isAreaD || dType === 'line') ? '#8b5cf6' : (isHorizD ? 'transparent' : segBorderC),
                                            borderWidth: (isAreaD || dType === 'line') ? 3 : (isHorizD ? 0 : 2),
                                            fill: isAreaD,
                                            tension: 0.45,
                                            pointRadius: (isAreaD || dType === 'line') ? 5 : 0,
                                            pointHoverRadius: 7,
                                            pointBackgroundColor: '#8b5cf6',
                                            hoverOffset: 6
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        indexAxis: isHorizD ? 'y' : 'x',
                                        cutout: dType === 'doughnut' ? '68%' : undefined,
                                        plugins: {
                                            legend: {
                                                display: isRadialD,
                                                position: 'right',
                                                labels: { color: txtC, font: { size: 11 }, boxWidth: 12, padding: 10 }
                                            },
                                            tooltip: {
                                                backgroundColor: isDark ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.97)',
                                                titleColor: isDark ? '#e2e8f0' : '#1e293b',
                                                bodyColor: isDark ? '#94a3b8' : '#475569',
                                                borderColor: isDark ? 'rgba(255,255,255,.15)' : 'rgba(0,0,0,.08)',
                                                borderWidth: 1, padding: 10, cornerRadius: 8
                                            }
                                        },
                                        scales: isRadialD ? (isRadarD ? { r: { grid: { color: gridC }, ticks: { display: false } } } : {}) : {
                                            x: { stacked: isStackedD, beginAtZero: true, ticks: { color: txtC, font: { size: 11 } }, grid: { color: gridC } },
                                            y: { stacked: isStackedD, beginAtZero: true, ticks: { color: txtC, font: { size: 11 }, stepSize: 1 }, grid: { color: gridC } }
                                        },
                                        animation: { duration: 500 }
                                    }
                                });
                            }

                            if (ctxB) {
                                var selB = document.getElementById('seo-ld-chart-bar-type');
                                var bTypeRaw = selB ? selB.value : (ctxB.dataset.type || 'bar');
                                var bColor = ctxB.dataset.color || '#06b6d4';
                                var isHorizB    = bTypeRaw === 'horizontalBar';
                                var isAreaB     = bTypeRaw === 'area';
                                var isStackedB  = bTypeRaw === 'stackedBar';
                                var isPieB      = bTypeRaw === 'pie';
                                var isDoughnutB = bTypeRaw === 'doughnut';
                                var isPolarB    = bTypeRaw === 'polarArea';
                                var isRadarB    = bTypeRaw === 'radar';
                                var isCircularB = isPieB || isDoughnutB || isPolarB;
                                var isRadialB   = isCircularB || isRadarB;

                                var bType = 'bar';
                                if (isAreaB || bTypeRaw === 'line') bType = 'line';
                                else if (isPieB) bType = 'pie';
                                else if (isDoughnutB) bType = 'doughnut';
                                else if (isPolarB) bType = 'polarArea';
                                else if (isRadarB) bType = 'radar';

                                if (_ldBar) { try { _ldBar.destroy(); } catch(e){} _ldBar = null; }

                                var bBgColor = (isRadialB || isHorizB || bType === 'bar') ? _ldStatusColors : (isAreaB ? 'rgba(6,182,212,0.35)' : bColor + 'CC');
                                if (isAreaB && ctxB.getContext) {
                                    var c2dB = ctxB.getContext('2d');
                                    var gradB = c2dB.createLinearGradient(0, 0, 0, 200);
                                    gradB.addColorStop(0, 'rgba(6,182,212,0.45)');
                                    gradB.addColorStop(1, 'rgba(6,182,212,0.02)');
                                    bBgColor = gradB;
                                }

                                _ldBar = new Chart(ctxB, {
                                    type: bType,
                                    data: {
                                        labels: _ldLabels,
                                        datasets: [{
                                            label: 'Leads',
                                            data: vals,
                                            backgroundColor: bBgColor,
                                            borderColor: (isAreaB || bType === 'line') ? '#06b6d4' : 'transparent',
                                            borderWidth: (isAreaB || bType === 'line') ? 3 : (bType === 'bar' ? 0 : 2),
                                            borderRadius: bType === 'bar' ? 8 : 0,
                                            borderSkipped: false,
                                            fill: isAreaB,
                                            tension: 0.45,
                                            pointRadius: (isAreaB || bType === 'line') ? 5 : 0,
                                            pointHoverRadius: 7,
                                            pointBackgroundColor: '#06b6d4',
                                            barThickness: bType === 'bar' ? 26 : undefined
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        indexAxis: isHorizB ? 'y' : 'x',
                                        cutout: bType === 'doughnut' ? '68%' : undefined,
                                        plugins: {
                                            legend: {
                                                display: isRadialB,
                                                position: 'right',
                                                labels: { color: txtC, font: { size: 11 }, boxWidth: 12, padding: 10 }
                                            },
                                            tooltip: {
                                                backgroundColor: isDark ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.97)',
                                                titleColor: isDark ? '#e2e8f0' : '#1e293b',
                                                bodyColor: isDark ? '#94a3b8' : '#475569',
                                                borderColor: isDark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.08)',
                                                borderWidth: 1, padding: 10, cornerRadius: 8
                                            }
                                        },
                                        scales: isRadialB ? (isRadarB ? { r: { grid: { color: gridC }, ticks: { display: false } } } : {}) : {
                                            x: { stacked: isStackedB, beginAtZero: true, ticks: { color: txtC, font: { size: 11 } }, grid: { color: gridC } },
                                            y: { stacked: isStackedB, beginAtZero: true, ticks: { color: txtC, font: { size: 11 }, stepSize: 1 }, grid: { color: gridC } }
                                        },
                                        animation: { duration: 500 }
                                    }
                                });
                            }
                        }

                        function ldTryInit(){
                            if (typeof Chart !== 'undefined') { ldInitCharts(); return; }
                            if (!document.getElementById('seo-ld-chartjs-cdn')) {
                                var s = document.createElement('script');
                                s.id = 'seo-ld-chartjs-cdn';
                                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
                                s.onload = function(){ ldInitCharts(); };
                                document.head.appendChild(s);
                            } else {
                                var t = setInterval(function(){ if (typeof Chart !== 'undefined') { clearInterval(t); ldInitCharts(); } }, 80);
                            }
                        }

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', function(){
                                ldTryInit();
                            });
                        } else {
                            ldTryInit();
                        }
                        document.addEventListener('change', function(e){
                            if (e.target && (e.target.id === 'seo-ld-chart-status-type' || e.target.id === 'seo-ld-chart-bar-type')) {
                                ldInitCharts();
                            }
                        });

                        // Exposed so the main tab switcher can (re)build / resize these
                        // charts when the Leads tab becomes visible — they are built on
                        // load while the panel is hidden, which leaves Chart.js with a
                        // 0x0 canvas until it is re-measured.
                        window.ldInitCharts = ldInitCharts;
                        window.seoLdEnsureCharts = ldInitCharts;

                        // Called when a lead's status changes (client dropdown or admin sync).
                        // Updates in-memory counts and refreshes both charts in place.
                        window.seoLdUpdateCharts = function(oldStatus, newStatus){
                            if (oldStatus === newStatus) return;
                            if (_ldCounts[oldStatus] !== undefined && _ldCounts[oldStatus] > 0) _ldCounts[oldStatus]--;
                            if (_ldCounts[newStatus] !== undefined) _ldCounts[newStatus]++;
                            var vals = _ldKeys.map(function(k){ return _ldCounts[k]; });
                            if (_ldDonut) { _ldDonut.data.datasets[0].data = vals; _ldDonut.update(); }
                            if (_ldBar)   { _ldBar.data.datasets[0].data   = vals; _ldBar.update(); }

                            _ldKeys.forEach(function(k){
                                var cnt = _ldCounts[k] || 0;
                                var cards = document.querySelectorAll('.seo-ld-kpi-card[data-kpi="'+k+'"]');
                                cards.forEach(function(card){
                                    var valEl = card.querySelector('.seo-ld-kpi-val');
                                    if (valEl) valEl.textContent = cnt;
                                    card.style.display = cnt > 0 ? 'flex' : 'none';
                                });
                            });
                        };
                        window.seoLdGetCounts = function(){ return _ldCounts; };
                    })();
                    </script>
                    <?php endif; ?>

                    <?php $ld_all_statuses = seo_dash_get_custom_statuses($rid); ?>
                    <!-- Pagination Top (with Search & Status Filter) -->
                    <div id="seo-ld-pagination-top" style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;padding:10px 24px;border-top:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">
                        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                            <input type="text" id="seo-ld-search" placeholder="Search leads..." style="padding:6px 12px;font-size:12px;border-radius:6px;border:1px solid var(--cc-border);background:var(--cc-surf2);color:var(--cc-text);outline:none;width:200px;">
                            <select id="seo-ld-status-filter" style="padding:6px 12px;font-size:12px;font-weight:600;border-radius:8px;border:1px solid var(--cc-border);background:var(--cc-surf2);color:var(--cc-text);outline:none;cursor:pointer;min-width:150px;">
                                <option value="">🌍 All Status (<?php echo intval($ld_total); ?>)</option>
                                <?php foreach ($ld_all_statuses as $stk => $stv) : ?>
                                <option value="<?php echo esc_attr($stk); ?>"><?php echo esc_html(($stv['icon'] ?? '🏷️') . ' ' . $stv['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="seo-ld-pagination-top-inner" style="display:flex;justify-content:flex-end;"></div>
                    </div>

                    <!-- Table -->
                    <?php if ($ld_show_table_section) : ?>
                    <div class="seo-cl-table-wrap">
                        <?php if (empty($ld_all)) : ?>
                        <div style="text-align:center;padding:48px;color:var(--cc-subtle);">
                            <div style="font-size:36px;margin-bottom:12px;">🎯</div>
                            <h4 style="margin:0 0 8px;color:var(--cc-text);">No Leads Yet</h4>
                            <p style="margin:0;font-size:13px;">Your agency will add your leads here.</p>
                        </div>
                        <?php else : ?>
                        <table class="seo-cl-table" id="seo-cl-ld-table" data-cols="<?php echo esc_attr(json_encode(array_keys(array_filter($ld_tbl_show)))); ?>">
                            <thead>
                                <tr>
                                    <?php if ($ld_tbl_show['num']) : ?><th style="width:40px;text-align:center;">#</th><?php endif; ?>
                                    <?php if ($ld_tbl_show['name']) : ?><th style="min-width:140px;max-width:180px;">Name</th><?php endif; ?>
                                    <?php if ($ld_tbl_show['phone']) : ?><th style="min-width:130px;">Phone</th><?php endif; ?>
                                    <?php if ($ld_tbl_show['email']) : ?><th style="min-width:180px;">Email</th><?php endif; ?>
                                    <?php if ($ld_tbl_show['message']) : ?><th style="width:80px;text-align:center;">Message</th><?php endif; ?>
                                    <?php if ($ld_tbl_show['status']) : ?><th style="width:160px;min-width:150px;text-align:center;">Status</th><?php endif; ?>
                                    <?php if ($ld_tbl_show['notes']) : ?><th style="width:110px;">Notes</th><?php endif; ?>
                                    <?php if (!empty($ld_tbl_show['strength'])) : ?><th style="width:125px;text-align:center;">Strength</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="seo-ld-tbody">
                            <?php $ld_ctr=0; foreach ($ld_all as $lr):
                                $ld_ctr++;
                                $ls = strtolower($lr['status'] ?: 'new');
                                $curr_st_info = $ld_all_statuses[$ls] ?? ['color'=>'#94a3b8','label'=>ucfirst($ls),'icon'=>'🏷️'];
                                $ls_color = $curr_st_info['color'];
                            ?>
                                <tr class="seo-ld-row" data-id="<?php echo intval($lr['id']); ?>" style="display:none;">
                                    <?php if ($ld_tbl_show['num']) : ?>
                                    <td style="text-align:center;color:var(--cc-subtle);font-size:12px;"><?php echo $ld_ctr; ?></td>
                                    <?php endif; ?>
                                    <?php if ($ld_tbl_show['name']) : ?>
                                    <td style="font-weight:600;color:var(--cc-text);min-width:140px;max-width:180px;word-break:break-word;line-height:1.4;"><?php echo esc_html($lr['name'] ?: '—'); ?></td>
                                    <?php endif; ?>
                                    <?php if ($ld_tbl_show['phone']) : ?>
                                    <td class="seo-ld-contact-cell"><?php echo esc_html($lr['phone'] ?: '—'); ?></td>
                                    <?php endif; ?>
                                    <?php if ($ld_tbl_show['email']) : ?>
                                    <td class="seo-ld-contact-cell"><?php echo esc_html($lr['email'] ?: '—'); ?></td>
                                    <?php endif; ?>
                                    <?php if ($ld_tbl_show['message']) : ?>
                                    <td>
                                        <?php if (!empty($lr['message'])) : ?>
                                        <button class="seo-ld-view-msg-btn" data-msg="<?php echo esc_attr($lr['message']); ?>" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;">👁 View</button>
                                        <?php else : ?>
                                        <span style="color:var(--cc-subtle);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($ld_tbl_show['status']) : ?>
                                    <td style="text-align:center;width:160px;">
                                        <div style="position:relative;display:inline-block;min-width:145px;max-width:155px;">
                                            <select class="seo-ld-status-sel" data-id="<?php echo intval($lr['id']); ?>" data-status="<?php echo esc_attr($ls); ?>" style="background:<?php echo $ls_color; ?>18;color:<?php echo $ls_color; ?>;border:1px solid <?php echo $ls_color; ?>55;border-radius:20px;padding:5px 24px 5px 10px;font-size:11.5px;font-weight:700;cursor:pointer;outline:none;appearance:none;-webkit-appearance:none;width:100%;min-width:145px;max-width:155px;white-space:nowrap;">
                                                <?php foreach ($ld_all_statuses as $stk => $stv) : ?>
                                                <option value="<?php echo esc_attr($stk); ?>" <?php selected($ls, $stk); ?> data-color="<?php echo esc_attr($stv['color']); ?>"><?php echo esc_html(($stv['icon'] ?? '🏷️') . ' ' . $stv['label']); ?></option>
                                                <?php endforeach; ?>
                                                <option value="__add_custom__">➕ + Add Custom Status...</option>
                                            </select>
                                            <span style="position:absolute;right:9px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:9px;color:<?php echo $ls_color; ?>;">▼</span>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($ld_tbl_show['notes']) : ?>
                                    <td>
                                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;">
                                        <?php if(!empty($lr['notes'])): ?>
                                        <button class="seo-ld-note-btn seo-ld-note-view-btn" data-id="<?php echo intval($lr['id']); ?>" data-notes="<?php echo esc_attr($lr['notes'] ?? ''); ?>" data-mode="view" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;display:inline-block;">👁 View</button>
                                        <button class="seo-ld-note-btn seo-ld-note-edit-btn" data-id="<?php echo intval($lr['id']); ?>" data-notes="<?php echo esc_attr($lr['notes'] ?? ''); ?>" data-mode="edit" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;display:inline-block;">📝 Edit</button>
                                        <?php else: ?>
                                        <button class="seo-ld-note-btn seo-ld-note-edit-btn" data-id="<?php echo intval($lr['id']); ?>" data-notes="" data-mode="edit" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;display:inline-block;">+ Add Note</button>
                                        <?php endif; ?>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                    <?php if (!empty($ld_tbl_show['strength'])) : 
                                        $st = seo_dash_calc_lead_strength($lr);
                                        $score = $st['score'];
                                        $color = $st['color'];
                                        $label = $st['label'];
                                        $dash  = ($score / 100) * 100;
                                    ?>
                                    <td>
                                        <div class="seo-ld-strength-cell" style="display:inline-flex;align-items:center;gap:8px;" title="Lead Strength: <?php echo $score; ?>%">
                                            <div style="position:relative;width:38px;height:38px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                <svg width="38" height="38" viewBox="0 0 36 36" style="transform:rotate(-90deg);">
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--cc-border)" stroke-width="2.5" />
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo $color; ?>" stroke-width="3" stroke-dasharray="<?php echo $dash; ?>, 100" stroke-linecap="round" />
                                                </svg>
                                                <span style="position:absolute;font-size:10px;font-weight:800;color:var(--cc-text);line-height:1;"><?php echo $score; ?>%</span>
                                            </div>
                                            <span style="font-size:11.5px;font-weight:700;color:<?php echo $color; ?>;"><?php echo $label; ?></span>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination Bottom -->
                    <div id="seo-ld-pagination-bot" style="display:flex;justify-content:center;align-items:center;gap:10px;padding:20px;border-top:1px solid var(--cc-border);"></div>
                    <?php endif; ?>
                    </div><!-- /#seo-cl-leads-subpane-forms -->

                    <!-- ── Sub-Pane 2: Click Tracking (Client View) ─────────── -->
                    <div id="seo-cl-leads-subpane-clicks" style="display:none;padding:20px;">
                        <?php
                        $cl_ct_all = SEO_Dash_Database::get_data_rows(SEO_Dash_Database::$data_click_tracking, $rid);
                        $cl_ct_total = count($cl_ct_all);
                        $cl_ct_types = [];
                        $cl_ct_pages = [];
                        foreach ($cl_ct_all as $cr) {
                            if (empty($cr['trashed'])) {
                                $t = strtolower($cr['click_type'] ?: 'other');
                                $cl_ct_types[$t] = ($cl_ct_types[$t] ?? 0) + 1;
                                $p = $cr['source_page'] ?: 'Direct';
                                $cl_ct_pages[$p] = ($cl_ct_pages[$p] ?? 0) + 1;
                            }
                        }
                        arsort($cl_ct_types);
                        $cl_top_type = !empty($cl_ct_types) ? array_key_first($cl_ct_types) : 'None';
                        ?>
                        <!-- KPI Row -->
                        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                            <div style="flex:1;min-width:140px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:18px 20px;display:flex;flex-direction:column;gap:6px;border-top:3px solid var(--cc-primary);">
                                <div style="font-size:24px;">🖱️</div>
                                <div style="font-size:26px;font-weight:800;color:var(--cc-text);"><?php echo $cl_ct_total; ?></div>
                                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--cc-subtle);">Total Clicks</div>
                            </div>
                            <div style="flex:1;min-width:140px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:18px 20px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #3b82f6;">
                                <div style="font-size:24px;">📄</div>
                                <div style="font-size:26px;font-weight:800;color:var(--cc-text);"><?php echo count($cl_ct_pages); ?></div>
                                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--cc-subtle);">Source Pages</div>
                            </div>
                            <div style="flex:1;min-width:140px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:14px;padding:18px 20px;display:flex;flex-direction:column;gap:6px;border-top:3px solid #10b981;">
                                <div style="font-size:24px;">🏷️</div>
                                <div style="font-size:26px;font-weight:800;color:var(--cc-text);"><?php echo esc_html(ucwords(str_replace('_', ' ', $cl_top_type))); ?></div>
                                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--cc-subtle);">Top Click Type</div>
                            </div>
                        </div>

                        <!-- Search Bar -->
                        <div style="margin-bottom:16px;">
                            <input type="text" id="seo-cl-ct-search" placeholder="Search click tracking records..." style="padding:8px 14px;font-size:13px;border-radius:8px;border:1px solid var(--cc-border);background:var(--cc-surf2);color:var(--cc-text);outline:none;width:100%;max-width:320px;">
                        </div>

                        <!-- Click Tracking Table -->
                        <div class="seo-cl-table-wrap">
                            <?php if (empty($cl_ct_all)) : ?>
                            <div style="text-align:center;padding:40px;color:var(--cc-subtle);">
                                <div style="font-size:32px;margin-bottom:8px;">🖱️</div>
                                <h4 style="margin:0 0 4px;color:var(--cc-text);">No Click Tracking Data</h4>
                                <p style="margin:0;font-size:13px;">Click tracking events will appear here once recorded.</p>
                            </div>
                            <?php else : ?>
                            <table class="seo-cl-table" id="seo-cl-ct-table">
                                <thead>
                                    <tr>
                                        <th style="min-width:200px;">Text / Keyword</th>
                                        <th style="min-width:140px;text-align:center;">Source Page</th>
                                        <th style="width:160px;">Click Type</th>
                                        <th style="width:160px;text-align:center;">Status</th>
                                        <th style="width:160px;">Submitted</th>
                                    </tr>
                                </thead>
                                <tbody id="seo-cl-ct-tbody">
                                    <?php foreach ($cl_ct_all as $cr) :
                                        if (!empty($cr['trashed'])) continue;
                                        $cs_val = strtolower($cr['status'] ?: 'new');
                                        $curr_st_info = $ld_all_statuses[$cs_val] ?? ['color'=>'#94a3b8','label'=>ucfirst($cs_val),'icon'=>'🏷️'];
                                        $cs_color = $curr_st_info['color'];
                                    ?>
                                    <tr class="seo-cl-ct-row">
                                        <td style="font-weight:600;color:var(--cc-text);"><?php echo esc_html($cr['keyword_text'] ?: '—'); ?></td>
                                        <td style="text-align:center;">
                                            <?php if (!empty($cr['source_page'])) : ?>
                                            <a href="<?php echo esc_url($cr['source_page']); ?>" target="_blank" title="<?php echo esc_attr($cr['source_page']); ?>" style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;font-size:11.5px;font-weight:700;border-radius:8px;background:var(--cc-surf2);border:1px solid var(--cc-border);color:var(--cc-primary);text-decoration:none;transition:all 0.15s ease;" onmouseover="this.style.background='var(--cc-primary)';this.style.color='#fff';" onmouseout="this.style.background='var(--cc-surf2)';this.style.color='var(--cc-primary)';">Visit ↗</a>
                                            <?php else : ?>
                                            <span style="color:var(--cc-subtle);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;background:var(--cc-surf2);border:1px solid var(--cc-border);font-size:11px;font-weight:700;color:var(--cc-text);text-transform:capitalize;">
                                                <?php echo esc_html(str_replace('_', ' ', $cr['click_type'] ?: 'click')); ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <div style="position:relative;display:inline-block;min-width:140px;max-width:150px;">
                                                <select class="seo-cl-ct-status-sel" data-id="<?php echo intval($cr['id']); ?>" data-status="<?php echo esc_attr($cs_val); ?>" style="background:<?php echo $cs_color; ?>18;color:<?php echo $cs_color; ?>;border:1px solid <?php echo $cs_color; ?>55;border-radius:20px;padding:5px 24px 5px 10px;font-size:11.5px;font-weight:700;cursor:pointer;outline:none;appearance:none;-webkit-appearance:none;width:100%;white-space:nowrap;">
                                                    <?php foreach ($ld_all_statuses as $stk => $stv) : ?>
                                                    <option value="<?php echo esc_attr($stk); ?>" <?php selected($cs_val, $stk); ?> data-color="<?php echo esc_attr($stv['color']); ?>"><?php echo esc_html(($stv['icon'] ?? '🏷️') . ' ' . $stv['label']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <span style="position:absolute;right:9px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:9px;color:<?php echo $cs_color; ?>;">▼</span>
                                            </div>
                                        </td>
                                        <td style="color:var(--cc-subtle);font-size:12px;"><?php 
                                            $cl_sub_val = esc_html($cr['click_date'] ?: '—');
                                            if (!empty($cr['click_time'])) {
                                                $cl_sub_val .= ' ' . esc_html($cr['click_time']);
                                            }
                                            echo $cl_sub_val;
                                        ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div><!-- /#seo-cl-leads-subpane-clicks -->

                    <script>
                    jQuery(document).ready(function($){
                        $(document).on('click', '.seo-cl-leads-subtab-btn', function(){
                            var subtab = $(this).data('subtab');
                            $('.seo-cl-leads-subtab-btn').css({'background':'transparent','color':'var(--cc-muted)'}).removeClass('active');
                            $(this).css({'background':'var(--cc-primary)','color':'#fff'}).addClass('active');

                            if (subtab === 'forms') {
                                $('#seo-cl-leads-subpane-forms').show();
                                $('#seo-cl-leads-subpane-clicks').hide();
                            } else if (subtab === 'clicks') {
                                $('#seo-cl-leads-subpane-forms').hide();
                                $('#seo-cl-leads-subpane-clicks').show();
                            }
                        });

                        $('#seo-cl-ct-search').on('input', function(){
                            var q = $(this).val().toLowerCase().trim();
                            $('#seo-cl-ct-tbody tr').each(function(){
                                var txt = $(this).text().toLowerCase();
                                $(this).toggle(q === '' || txt.indexOf(q) !== -1);
                            });
                        });

                        $(document).on('change', '.seo-cl-ct-status-sel', function(){
                            var $sel = $(this);
                            var id = $sel.data('id');
                            var val = $sel.val();
                            var $opt = $sel.find('option:selected');
                            var color = $opt.data('color') || '#94a3b8';

                            $sel.css({
                                'background': color + '18',
                                'color': color,
                                'border-color': color + '55'
                            });
                            $sel.siblings('span').css('color', color);

                            var ajaxUrl = (typeof seoDashClient !== 'undefined') ? seoDashClient.ajax : (typeof seoDash !== 'undefined' ? seoDash.ajax : '');
                            var nonceVal = (typeof seoDashClient !== 'undefined') ? seoDashClient.nonce : (typeof seoDash !== 'undefined' ? seoDash.nonce : '');
                            $.post(ajaxUrl, {
                                action: 'seo_dash_client_update_click_tracking_status',
                                nonce: nonceVal,
                                row_id: id,
                                status: val,
                                report_id: <?php echo $rid; ?>
                            }, function(r){
                                if(r.success) {
                                    if (typeof seoToast === 'function') seoToast('Status updated', 'ok');
                                } else {
                                    alert(r.data || 'Failed to update status');
                                }
                            });
                        });
                    });
                    </script>
                </div>

                <!-- Message Modal -->
                <div id="seo-ld-msg-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.85);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);">
                    <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;width:100%;max-width:540px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);animation:seoLdModalPop 0.2s ease-out;overflow:hidden;">
                        <div style="padding:16px 20px;border-bottom:1px solid var(--cc-border);display:flex;justify-content:space-between;align-items:center;background:var(--cc-surf2);">
                            <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--cc-text);">💬 Lead Message</h3>
                            <button onclick="document.getElementById('seo-ld-msg-modal').style.display='none'" style="width:30px;height:30px;border-radius:50%;background:var(--cc-surf);border:1px solid var(--cc-border);color:var(--cc-text);cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;">✕</button>
                        </div>
                        <div style="padding:22px 24px;">
                            <p id="seo-ld-msg-body" style="margin:0;color:var(--cc-text);font-size:14px;line-height:1.7;white-space:pre-wrap;word-break:break-word;"></p>
                        </div>
                    </div>
                </div>

                <!-- Notes Modal -->
                <div id="seo-ld-note-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.85);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px);">
                    <div style="background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:14px;width:100%;max-width:500px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);animation:seoLdModalPop 0.2s ease-out;overflow:hidden;">
                        <div style="padding:16px 20px;border-bottom:1px solid var(--cc-border);display:flex;justify-content:space-between;align-items:center;background:var(--cc-surf2);">
                            <h3 id="seo-ld-note-modal-title" style="margin:0;font-size:15px;font-weight:700;color:var(--cc-text);">📝 Lead Notes</h3>
                            <button onclick="seoLdCloseNoteModal()" style="width:30px;height:30px;border-radius:50%;background:var(--cc-surf);border:1px solid var(--cc-border);color:var(--cc-text);cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;">✕</button>
                        </div>
                        <div style="padding:22px 24px;">
                            <div id="seo-ld-note-view-body" style="display:none;background:var(--cc-surf2);border-radius:10px;padding:12px 14px;font-size:14px;color:var(--cc-text);line-height:1.7;white-space:pre-wrap;word-break:break-word;margin-bottom:0;"></div>
                            <textarea id="seo-ld-note-input" rows="5" placeholder="Add your notes here…" style="display:none;width:100%;box-sizing:border-box;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:10px;padding:12px 14px;font-size:13px;color:var(--cc-text);resize:vertical;outline:none;font-family:inherit;line-height:1.6;"></textarea>
                            <div id="seo-ld-note-edit-row" style="display:none;justify-content:flex-end;gap:10px;margin-top:14px;">
                                <button onclick="seoLdCloseNoteModal()" style="padding:8px 18px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;font-size:13px;font-weight:600;color:var(--cc-text);cursor:pointer;">Cancel</button>
                                <button id="seo-ld-note-save-btn" onclick="seoLdSaveNote()" style="padding:8px 20px;background:var(--cc-primary,#6366f1);border:none;border-radius:8px;font-size:13px;font-weight:700;color:#fff;cursor:pointer;">Save Note</button>
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                @keyframes seoLdModalPop { from { opacity:0;transform:scale(0.95); } to { opacity:1;transform:scale(1); } }
                .seo-ld-view-msg-btn:hover { background:var(--cc-primary,#6366f1)!important;color:#fff!important;border-color:var(--cc-primary,#6366f1)!important; }
                .seo-ld-note-btn:hover { background:var(--cc-primary,#6366f1)!important;color:#fff!important;border-color:var(--cc-primary,#6366f1)!important; }
                .seo-ld-contact-cell { color: var(--cc-subtle); }
                .seo-client-app.seo-dark .seo-ld-contact-cell { color: #ffffff !important; }
                </style>

                <script>
                (function(){
                    var _ldPage    = 1;
                    var _ldPerPage = 20;
                    var _ldRows    = [];
                    var _ldNoteId  = 0;

                    function ldInit() {
                        var tbody = document.getElementById('seo-ld-tbody');
                        if (!tbody) return;
                        _ldRows = Array.from(tbody.querySelectorAll('tr.seo-ld-row'));

                        var srch = document.getElementById('seo-ld-search');
                        if (srch && !srch._bound) {
                            srch._bound = true;
                            srch.addEventListener('input', function(){ _ldPage = 1; ldFilter(); });
                        }
                        var stf = document.getElementById('seo-ld-status-filter');
                        if (stf && !stf._bound) {
                            stf._bound = true;
                            stf.addEventListener('change', function(){ _ldPage = 1; ldFilter(); });
                        }

                        ldFilter();
                    }

                    function ldFilter() {
                        var srch = document.getElementById('seo-ld-search');
                        var stf  = document.getElementById('seo-ld-status-filter');
                        var search = srch ? srch.value.toLowerCase().trim() : '';
                        var status = stf ? stf.value : '';

                        var filtered = _ldRows.filter(function(tr){
                            if (status) {
                                var sel = tr.querySelector('.seo-ld-status-sel');
                                if (!sel || sel.value !== status) return false;
                            }
                            if (search) {
                                var txt = tr.textContent.toLowerCase();
                                if (txt.indexOf(search) === -1) return false;
                            }
                            return true;
                        });

                        var total = filtered.length;
                        var totalPages = Math.max(1, Math.ceil(total / _ldPerPage));
                        if (_ldPage > totalPages) _ldPage = totalPages;

                        _ldRows.forEach(function(tr){ tr.style.display = 'none'; });
                        filtered.forEach(function(tr, i) {
                            var s = (_ldPage-1)*_ldPerPage, e = s+_ldPerPage;
                            tr.style.display = (i >= s && i < e) ? '' : 'none';
                        });
                        ldRenderPag(total, totalPages);
                    }

                    function ldRenderPag(total, totalPages) {
                        var pT = document.getElementById('seo-ld-pagination-top-inner');
                        var pB = document.getElementById('seo-ld-pagination-bot');
                        if (!pT||!pB) return;
                        if (totalPages<=1) { pT.innerHTML=''; pB.innerHTML=''; return; }
                        function build() {
                            var h = '<div style="display:flex;align-items:center;gap:6px;">';
                            h += '<span style="font-size:12px;color:var(--cc-text);margin-right:12px;">Page '+_ldPage+' of '+totalPages+' &nbsp;('+total+' leads)</span>';
                            h += btn('«','seoLdGoPage(1)',_ldPage===1);
                            h += btn('‹','seoLdGoPage('+(_ldPage-1)+')',_ldPage===1);
                            var s=Math.max(1,_ldPage-2), e=Math.min(totalPages,_ldPage+2);
                            for(var p=s;p<=e;p++){
                                if(p===_ldPage) h+='<button class="seo-bk-page-btn active" disabled>'+p+'</button>';
                                else h+='<button class="seo-bk-page-btn" onclick="seoLdGoPage('+p+')">'+p+'</button>';
                            }
                            h += btn('›','seoLdGoPage('+(_ldPage+1)+')',_ldPage===totalPages);
                            h += btn('»','seoLdGoPage('+totalPages+')',_ldPage===totalPages);
                            h += '</div>';
                            return h;
                        }
                        function btn(label,fn,dis){return '<button class="seo-bk-page-btn" onclick="'+fn+'"'+(dis?' disabled':'')+'>'+label+'</button>';}
                        pT.innerHTML = build();
                        pB.innerHTML = build();
                    }

                    window.seoLdGoPage = function(p) {
                        _ldPage = p; ldFilter();
                        var t = document.getElementById('seo-cl-ld-table');
                        if(t) t.scrollIntoView({behavior:'smooth',block:'start'});
                    };

                    function updateSelStyle(sel) {
                        var selectedOpt = sel.options[sel.selectedIndex];
                        var c = selectedOpt ? selectedOpt.getAttribute('data-color') : null;
                        if (!c) {
                            var colors = {new:'#8b5cf6',contacted:'#06b6d4',checking:'#f59e0b',qualified:'#10b981',converted:'#059669',lost:'#ef4444'};
                            c = colors[sel.value] || '#94a3b8';
                        }
                        sel.style.background = c+'18';
                        sel.style.color = c;
                        sel.style.borderColor = c+'55';
                        var arrow = sel.nextElementSibling;
                        if(arrow) arrow.style.color = c;
                    }

                    document.addEventListener('change', function(e){
                        if(!e.target||!e.target.classList.contains('seo-ld-status-sel')) return;
                        var sel=e.target, lid=sel.getAttribute('data-id'), status=sel.value;
                        var oldStatus = sel.getAttribute('data-status') || 'new';
                        var ajax=(typeof seoDashClient!=='undefined')?seoDashClient.ajax:'';
                        var nonce=(typeof seoDashClient!=='undefined')?seoDashClient.nonce:'';
                        var rid=(typeof seoDashClient!=='undefined')?seoDashClient.report_id:0;
                        if(!ajax||!nonce) return;
                        if (status === '__add_custom__') {
                            var newName = prompt('Enter New Custom Lead Status Name:');
                            if (!newName || !newName.trim()) {
                                sel.value = oldStatus;
                                updateSelStyle(sel);
                                return;
                            }
                            var fdAdd = new FormData();
                            fdAdd.append('action', 'seo_dash_add_custom_lead_status');
                            fdAdd.append('nonce', nonce);
                            fdAdd.append('report_id', rid);
                            fdAdd.append('name', newName.trim());
                            fetch(ajax, { method: 'POST', body: fdAdd, credentials: 'same-origin' })
                            .then(function(res){ return res.json(); })
                            .then(function(res){
                                if (res && res.success && res.data) {
                                    var slug = res.data.slug;
                                    var label = res.data.label;
                                    var icon = res.data.icon;
                                    var color = res.data.color;

                                    document.querySelectorAll('.seo-ld-status-sel').forEach(function(s) {
                                        var opt = document.createElement('option');
                                        opt.value = slug;
                                        opt.text = (icon ? icon + ' ' : '') + label;
                                        opt.setAttribute('data-color', color);
                                        var addOpt = s.querySelector('option[value="__add_custom__"]');
                                        if (addOpt) s.insertBefore(opt, addOpt);
                                        else s.appendChild(opt);
                                    });

                                    sel.value = slug;
                                    status = slug;
                                    sel.setAttribute('data-status', slug);
                                    updateSelStyle(sel);
                                    ldFilter();
                                    seoLdApplyStatusChange(oldStatus, status);

                                    var fd = new FormData();
                                    fd.append('action', 'seo_dash_client_update_lead_status');
                                    fd.append('nonce', nonce);
                                    fd.append('row_id', lid);
                                    fd.append('status', slug);
                                    fd.append('report_id', rid);
                                    fetch(ajax, { method: 'POST', body: fd, credentials: 'same-origin' });
                                } else {
                                    sel.value = oldStatus;
                                    updateSelStyle(sel);
                                }
                            });
                            return;
                        }

                        if (oldStatus === status) return;
                        updateSelStyle(sel);
                        sel.setAttribute('data-status', status);
                        ldFilter();
                        seoLdApplyStatusChange(oldStatus, status);
                        var fd=new FormData();
                        fd.append('action','seo_dash_client_update_lead_status');
                        fd.append('nonce',nonce); fd.append('row_id',lid);
                        fd.append('status',status); fd.append('report_id',rid);
                        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
                        .then(function(r){return r.json();})
                        .then(function(r){
                            if(!r.success){
                                console.warn('Status update failed',r.data);
                                // Roll back the optimistic UI update on failure
                                seoLdApplyStatusChange(status, oldStatus);
                                sel.value = oldStatus;
                                sel.setAttribute('data-status', oldStatus);
                                updateSelStyle(sel);
                            }
                        })
                        .catch(function(){
                            seoLdApplyStatusChange(status, oldStatus);
                            sel.value = oldStatus;
                            sel.setAttribute('data-status', oldStatus);
                            updateSelStyle(sel);
                        });
                    });

                    // Updates charts + KPI cards in lockstep whenever a lead's status changes
                    // (used both for the optimistic client-side update and for any rollback).
                    var _ldKpiLabels = {
                        new:'Awaiting contact', contacted:'In progress', qualified:'Ready to convert',
                        converted:'% of total', lost:'Not converted'
                    };
                    function seoLdApplyStatusChange(oldStatus, newStatus){
                        if (typeof window.seoLdUpdateCharts === 'function') {
                            window.seoLdUpdateCharts(oldStatus, newStatus);
                        }
                        var counts = (typeof window.seoLdGetCounts === 'function') ? window.seoLdGetCounts() : null;
                        if (!counts) return;

                        // Update each visible KPI card from the recalculated counts
                        document.querySelectorAll('.seo-ld-kpi-card').forEach(function(card){
                            var key = card.getAttribute('data-kpi');
                            var valEl = card.querySelector('.seo-ld-kpi-val');
                            var subEl = card.querySelector('.seo-ld-kpi-sub');
                            if (!valEl) return;

                            if (key === 'total') {
                                // Total never changes on a status change, but keep it accurate
                                var total = 0;
                                Object.keys(counts).forEach(function(k){ total += counts[k]; });
                                valEl.textContent = total;
                            } else if (counts[key] !== undefined) {
                                valEl.textContent = counts[key];
                                if (subEl && key === 'converted') {
                                    var total2 = 0;
                                    Object.keys(counts).forEach(function(k){ total2 += counts[k]; });
                                    var pct = total2 > 0 ? Math.round((counts.converted / total2) * 100) : 0;
                                    subEl.textContent = pct + '% of total';
                                }
                            }
                        });
                    }

                    // Message modal
                    document.addEventListener('click', function(e){
                        if(!e.target||!e.target.classList.contains('seo-ld-view-msg-btn')) return;
                        document.getElementById('seo-ld-msg-body').textContent = e.target.getAttribute('data-msg');
                        document.getElementById('seo-ld-msg-modal').style.display = 'flex';
                    });
                    document.getElementById('seo-ld-msg-modal') && document.getElementById('seo-ld-msg-modal').addEventListener('click', function(e){
                        if(e.target===this) this.style.display='none';
                    });

                    // Notes modal
                    document.addEventListener('click', function(e){
                        var btn = e.target.closest('.seo-ld-note-btn');
                        if(!btn) return;
                        _ldNoteId = parseInt(btn.getAttribute('data-id'));
                        var mode = btn.getAttribute('data-mode') || 'edit';
                        var notes = btn.getAttribute('data-notes') || '';
                        var vb = document.getElementById('seo-ld-note-view-body');
                        var ta = document.getElementById('seo-ld-note-input');
                        var er = document.getElementById('seo-ld-note-edit-row');
                        var title = document.getElementById('seo-ld-note-modal-title');
                        if(mode === 'view'){
                            if(title) title.textContent = '📝 View Note';
                            if(vb){ vb.textContent = notes; vb.style.display = 'block'; }
                            if(ta) ta.style.display = 'none';
                            if(er) er.style.display = 'none';
                        } else {
                            if(title) title.textContent = '📝 Lead Notes';
                            if(vb) vb.style.display = 'none';
                            if(ta){ ta.value = notes; ta.style.display = 'block'; }
                            if(er) er.style.display = 'flex';
                        }
                        document.getElementById('seo-ld-note-modal').style.display = 'flex';
                        if(mode === 'edit') setTimeout(function(){ document.getElementById('seo-ld-note-input').focus(); }, 100);
                    });
                    document.getElementById('seo-ld-note-modal') && document.getElementById('seo-ld-note-modal').addEventListener('click', function(e){
                        if(e.target===this) seoLdCloseNoteModal();
                    });

                    window.seoLdCloseNoteModal = function(){
                        document.getElementById('seo-ld-note-modal').style.display='none';
                    };

                    window.seoLdSaveNote = function(){
                        var notes = document.getElementById('seo-ld-note-input').value;
                        var ajax=(typeof seoDashClient!=='undefined')?seoDashClient.ajax:'';
                        var nonce=(typeof seoDashClient!=='undefined')?seoDashClient.nonce:'';
                        var rid=(typeof seoDashClient!=='undefined')?seoDashClient.report_id:0;
                        if(!ajax||!nonce||!_ldNoteId) return;
                        var saveBtn = document.getElementById('seo-ld-note-save-btn');
                        saveBtn.textContent = 'Saving…'; saveBtn.disabled = true;
                        var fd=new FormData();
                        fd.append('action','seo_dash_client_save_lead_notes');
                        fd.append('nonce',nonce); fd.append('row_id',_ldNoteId);
                        fd.append('notes',notes); fd.append('report_id',rid);
                        fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'})
                        .then(function(r){return r.json();})
                        .then(function(r){
                            saveBtn.textContent='Save Note'; saveBtn.disabled=false;
                            if(r.success){
                                // Update the button text in table
                                var btn=document.querySelector('.seo-ld-note-btn[data-id="'+_ldNoteId+'"]');
                                var td=btn?btn.closest('td'):null;
                                if(td){
                                    if(notes.trim()){
                                        td.innerHTML='<div style="display:flex;gap:6px;align-items:center;flex-wrap:nowrap;"><button class="seo-ld-note-btn seo-ld-note-view-btn" data-id="'+_ldNoteId+'" data-notes="'+notes.replace(/"/g,'&quot;')+'" data-mode="view" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;display:inline-block;">👁 View</button><button class="seo-ld-note-btn seo-ld-note-edit-btn" data-id="'+_ldNoteId+'" data-notes="'+notes.replace(/"/g,'&quot;')+'" data-mode="edit" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;display:inline-block;">📝 Edit</button></div>';
                                    } else {
                                        td.innerHTML='<div style="display:flex;gap:6px;align-items:center;"><button class="seo-ld-note-btn seo-ld-note-edit-btn" data-id="'+_ldNoteId+'" data-notes="" data-mode="edit" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;display:inline-block;">+ Add Note</button></div>';
                                    }
                                }
                                seoLdCloseNoteModal();
                            } else {
                                alert('Failed to save note. Please try again.');
                            }
                        })
                        .catch(function(){ saveBtn.textContent='Save Note'; saveBtn.disabled=false; });
                    };

                    document.addEventListener('DOMContentLoaded', ldInit);
                    document.querySelectorAll('.seo-cl-nav-btn[data-tab="leads"]').forEach(function(btn){
                        btn.addEventListener('click', function(){ setTimeout(ldInit, 50); });
                    });
                })();
                </script>
            </div>
            <?php endif; ?>



            <!-- ── AI Assistant ───────────────────────────── -->
            <?php if ($show['ai']) : ?>
            <div class="seo-cl-panel-tab" data-tab="ai" <?php echo $first_visible!=='ai'?'style="display:none;"':''; ?>>

                <style>
                .seo-ai-wrap{display:flex;gap:16px;align-items:flex-start;}
                .seo-ai-prompts-col{width:215px;flex-shrink:0;}
                .seo-ai-chat-col{flex:1;min-width:0;}
                .seo-ai-prompt-btn{display:block;width:100%;text-align:left;padding:9px 12px;margin-bottom:6px;background:var(--cc-surf);border:1px solid var(--cc-border);border-radius:10px;font-size:12px;font-weight:600;color:var(--cc-text);cursor:pointer;line-height:1.4;transition:all .15s;}
                .seo-ai-prompt-btn:hover{border-color:var(--cc-primary);color:var(--cc-primary);background:var(--cc-surf2);}
                .seo-ai-prompts-scroll{max-height:520px;overflow-y:auto;padding-right:2px;scrollbar-width:thin;}
                .seo-ai-prompts-scroll::-webkit-scrollbar{width:3px;}
                .seo-ai-prompts-scroll::-webkit-scrollbar-thumb{background:var(--cc-border);border-radius:4px;}
                .seo-ai-cat-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--cc-muted);margin:10px 0 5px;padding-left:2px;display:block;}
                #seo-cl-chat-input{background:var(--cc-surf2)!important;color:var(--cc-text)!important;border-color:var(--cc-border)!important;}
                #seo-cl-chat-input::placeholder{color:var(--cc-muted)!important;}
                .seo-cl-msg-assistant .seo-cl-msg-bubble{max-width:96%;}
                @media(max-width:640px){.seo-ai-wrap{flex-direction:column;}.seo-ai-prompts-col{width:100%;}.seo-ai-prompts-scroll{max-height:140px;display:flex;flex-wrap:wrap;gap:5px;overflow-x:auto;overflow-y:hidden;}.seo-ai-prompt-btn{display:inline-block;width:auto;margin-bottom:0;white-space:nowrap;}}
                </style>

                <div class="seo-ai-wrap">

                    <?php $seo_ai_prompts_section_vis = !(isset($meta['show_ai_prompts_section']) && !$meta['show_ai_prompts_section']); ?>
                    <?php if ($seo_ai_prompts_section_vis) : ?>
                    <!-- Left: Prompt panel -->
                    <div class="seo-ai-prompts-col">
                        <div class="seo-cl-panel" style="margin-bottom:0;">
                            <div style="padding:12px 14px 10px;border-bottom:1px solid var(--cc-border);">
                                <div style="font-size:13px;font-weight:800;color:var(--cc-text);">💡 Quick Questions</div>
                                <div style="font-size:11px;color:var(--cc-muted);margin-top:2px;">Click any to ask instantly</div>
                            </div>
                            <div class="seo-ai-prompts-scroll" style="padding:10px 10px 14px;">
                                <?php
                                $seo_ai_prompts_default = [
                                    ['label' => 'Traffic',   'qs' => [
                                        'How many sessions last 30 days?',
                                        'How many users visited overall?',
                                        'What is my traffic trend?',
                                    ]],
                                    ['label' => 'GMB',       'qs' => [
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
                                $seo_ai_prompts_saved = get_option("seo_dash_ai_prompts_{$rid}", null);
                                $seo_ai_prompts = (is_array($seo_ai_prompts_saved) && !empty($seo_ai_prompts_saved)) ? $seo_ai_prompts_saved : $seo_ai_prompts_default;
                                foreach ($seo_ai_prompts as $seo_ai_cat) :
                                    $cat = $seo_ai_cat['label'] ?? '';
                                    $qs  = is_array($seo_ai_cat['qs'] ?? null) ? $seo_ai_cat['qs'] : [];
                                    if ($cat === '' || empty($qs)) continue;
                                ?>
                                <span class="seo-ai-cat-label"><?php echo esc_html($cat); ?></span>
                                <?php foreach ($qs as $q) : ?>
                                <button class="seo-ai-prompt-btn" onclick="seoDashRunPrompt(<?php echo json_encode($q); ?>)"><?php echo esc_html($q); ?></button>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Right: Chat -->
                    <div class="seo-ai-chat-col">
                        <div class="seo-cl-panel" style="margin-bottom:0;">
                            <div class="seo-cl-panel-hd" style="justify-content:space-between;">
                                <h3>🤖 AI SEO Assistant</h3>
                                <span style="font-size:11px;color:var(--cc-subtle);">Your personal SEO analyst</span>
                            </div>
                            <div class="seo-cl-panel-body" style="padding:0;">
                                <div id="seo-cl-chat-messages" class="seo-cl-chat-messages" style="min-height:380px;max-height:460px;border-radius:0;margin-bottom:0;border-bottom:1px solid var(--cc-border);">
                                    <div class="seo-cl-msg seo-cl-msg-assistant">
                                        <div class="seo-cl-msg-bubble">👋 Hi! I'm your SEO assistant. I have full access to your report data — analytics, rankings, backlinks, leads and more. Use the quick questions on the left or ask me anything below!</div>
                                    </div>
                                </div>
                                <div class="seo-cl-chat-input-row" style="padding:14px 16px;background:var(--cc-surf);border-radius:0 0 var(--cc-r-sm) var(--cc-r-sm);">
                                    <div class="seo-cl-chat-input-wrap">
                                        <textarea id="seo-cl-chat-input" class="seo-cl-chat-input" rows="2" placeholder="Ask about your SEO performance…"></textarea>
                                        <button id="seo-cl-chat-send" class="seo-cl-send-btn" title="Send">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M22 2L11 13" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.seo-ai-wrap -->

                <script>
                window.seoDashRunPrompt = function(text) {
                    // Switch to AI tab first
                    var aiBtn = document.querySelector('.seo-cl-nav-btn[data-tab="ai"]');
                    if (aiBtn) aiBtn.click();
                    // Wait for tab to render, then fill input and auto-send
                    setTimeout(function() {
                        var input = document.getElementById('seo-cl-chat-input');
                        var send  = document.getElementById('seo-cl-chat-send');
                        if (!input || !send) return;
                        input.value = text;
                        // Trigger input event so any listeners fire, then send
                        input.dispatchEvent(new Event('input'));
                        send.click();
                    }, 150);
                };
                </script>
            </div>
            <?php endif; ?>

            <!-- ── Account ────────────────────────────────── -->
            <?php if ($show['account']) : ?>
            <div class="seo-cl-panel-tab" data-tab="account" <?php echo $first_visible!=='account'?'style="display:none;"':''; ?>>
                <div class="seo-cl-panel" style="max-width:520px;">
                    <div class="seo-cl-panel-hd"><h3>👤 Account Settings</h3></div>
                    <div class="seo-cl-panel-body">
                        <div id="seo-cl-acct-msg" style="display:none;margin-bottom:14px;"></div>
                        <div class="seo-cl-acct-form">

                            <?php if ($perm_avatar) : ?>
                            <!-- Avatar upload -->
                            <div style="margin-bottom:20px;">
                                <label class="seo-cl-acct-label">Profile Photo</label>
                                <div style="display:flex;align-items:center;gap:16px;">
                                    <?php
                                    $avatar_attachment_id = get_user_meta($user_id, '_seo_dash_avatar_id', true);
                                    $avatar_url = $avatar_attachment_id ? wp_get_attachment_url(intval($avatar_attachment_id)) : '';
                                    ?>
                                    <div id="seo-acct-avatar-wrap" style="width:72px;height:72px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,var(--cc-primary),#7c3aed);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;color:#fff;flex-shrink:0;border:2px solid var(--cc-border);">
                                        <?php if ($avatar_url) : ?>
                                            <img id="seo-acct-avatar-img" src="<?php echo esc_url($avatar_url); ?>" style="width:100%;height:100%;object-fit:cover;" alt="Avatar">
                                        <?php else : ?>
                                            <span id="seo-acct-avatar-letter"><?php echo esc_html($avatar_letter); ?></span>
                                            <img id="seo-acct-avatar-img" src="" style="display:none;width:100%;height:100%;object-fit:cover;" alt="Avatar">
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:8px;">
                                        <label for="seo-acct-avatar-file" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;font-size:12px;font-weight:600;color:var(--cc-text);cursor:pointer;transition:background .15s;">
                                            📷 Choose Photo
                                        </label>
                                        <input type="file" id="seo-acct-avatar-file" accept="image/*" style="display:none;">
                                        <button type="button" id="seo-acct-avatar-remove" style="display:<?php echo $avatar_url ? 'inline-flex' : 'none'; ?>;align-items:center;gap:6px;padding:7px 14px;background:transparent;border:1px solid rgba(220,38,38,.3);border-radius:8px;font-size:12px;font-weight:600;color:#dc2626;cursor:pointer;">
                                            🗑 Remove
                                        </button>
                                        <span style="font-size:11px;color:var(--cc-subtle);">JPG, PNG or GIF · Max 2MB</span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($perm_name) : ?>
                            <div>
                                <label class="seo-cl-acct-label">Display Name</label>
                                <input type="text" id="seo-cl-acct-name" class="seo-cl-acct-in" autocomplete="name" value="<?php echo esc_attr($user ? $user->display_name : ''); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($perm_email) : ?>
                            <div>
                                <label class="seo-cl-acct-label">Email</label>
                                <input type="email" id="seo-cl-acct-email" class="seo-cl-acct-in" autocomplete="email" value="<?php echo esc_attr($user ? $user->user_email : ''); ?>">
                            </div>
                            <?php endif; ?>

                            <?php if ($perm_password) : ?>
                            <div style="margin-bottom:20px;">
                                <label class="seo-cl-acct-label">Your Username</label>
                                <input type="text" id="seo-cl-acct-myuser" class="seo-cl-acct-in" value="<?php echo esc_attr($user ? $user->user_login : ''); ?>" readonly style="background:var(--cc-surf2);cursor:default;">
                            </div>
                            <?php endif; ?>

                            <?php if ($perm_password) : ?>
                            <div>
                                <label class="seo-cl-acct-label">New Password <span style="font-weight:400;text-transform:none;">(leave blank to keep current)</span></label>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <input type="password" id="seo-cl-acct-pwd" class="seo-cl-acct-in" autocomplete="new-password" style="flex:1;margin-bottom:0;">
                                    <button type="button" id="seo-cl-acct-toggle-pwd" title="Show/Hide Password" style="padding:8px 10px;background:transparent;border:1px solid var(--cc-border,rgba(255,255,255,.12));border-radius:8px;cursor:pointer;font-size:14px;color:var(--cc-muted);white-space:nowrap;flex-shrink:0;" onclick="(function(){var i=document.getElementById('seo-cl-acct-pwd');if(!i)return;i.type=i.type==='password'?'text':'password';document.getElementById('seo-cl-acct-toggle-pwd').textContent=i.type==='password'?'👁':'🙈';})()">👁</button>
                                    <button type="button" id="seo-cl-acct-gen-pwd" title="Generate strong password" style="padding:8px 14px;background:var(--cc-accent,#6366f1);border:none;border-radius:8px;cursor:pointer;font-size:12px;font-weight:600;color:#fff;white-space:nowrap;flex-shrink:0;" onclick="(function(){var chars='ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%^&*';var pwd='';var arr=new Uint8Array(16);window.crypto.getRandomValues(arr);arr.forEach(function(b){pwd+=chars[b%chars.length];});var i=document.getElementById('seo-cl-acct-pwd');if(!i)return;i.type='text';i.value=pwd;document.getElementById('seo-cl-acct-toggle-pwd').textContent='🙈';var msg=document.getElementById('seo-cl-acct-gen-msg');if(msg){msg.style.display='block';setTimeout(function(){msg.style.display='none';},4000);}})()">⚡ Generate</button>
                                </div>
                                <div id="seo-cl-acct-gen-msg" style="display:none;margin-top:6px;font-size:11px;color:var(--cc-muted);padding:6px 10px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);border-radius:6px;">Password generated — click <strong>Save Changes</strong> to apply it.</div>
                            </div>
                            <?php endif; ?>

                            <?php if ($perm_name || $perm_email || $perm_password || $perm_avatar) : ?>
                            <button id="seo-cl-acct-save" class="seo-cl-save-btn"
                                data-user-id="<?php echo intval($user_id); ?>"
                                data-perm-name="<?php echo $perm_name ? '1' : '0'; ?>"
                                data-perm-email="<?php echo $perm_email ? '1' : '0'; ?>"
                                data-perm-pwd="<?php echo $perm_password ? '1' : '0'; ?>"
                                data-perm-avatar="<?php echo $perm_avatar ? '1' : '0'; ?>">Save Changes</button>
                            <?php else : ?>
                            <p style="color:var(--cc-muted);font-size:13px;">No account fields are available to edit. Contact your agency if you need to make changes.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($perm_avatar) : ?>
                <script>
                (function(){
                    var fileInput  = document.getElementById('seo-acct-avatar-file');
                    var imgEl      = document.getElementById('seo-acct-avatar-img');
                    var letterEl   = document.getElementById('seo-acct-avatar-letter');
                    var removeBtn  = document.getElementById('seo-acct-avatar-remove');
                    window._seoDashPendingFile   = null;
                    window._seoDashRemoveAvatar  = false;

                    if (fileInput) fileInput.addEventListener('change', function(){
                        var f = this.files[0];
                        if (!f) return;
                        if (f.size > 2 * 1024 * 1024) { alert('File too large — max 2MB'); this.value=''; return; }
                        window._seoDashPendingFile = f;
                        var reader = new FileReader();
                        reader.onload = function(e){
                            if (imgEl)    { imgEl.src = e.target.result; imgEl.style.display = 'block'; }
                            if (letterEl)  letterEl.style.display = 'none';
                            if (removeBtn) removeBtn.style.display = 'inline-flex';
                        };
                        reader.readAsDataURL(f);
                    });

                    if (removeBtn) removeBtn.addEventListener('click', function(){
                        window._seoDashPendingFile  = null;
                        window._seoDashRemoveAvatar = true;
                        if (fileInput)  fileInput.value = '';
                        if (imgEl)     { imgEl.src = ''; imgEl.style.display = 'none'; }
                        if (letterEl)   letterEl.style.display = '';
                        removeBtn.style.display = 'none';
                    });
                })();
                </script>
                <?php endif; ?>

                <script>
                (function(){
                    var saveBtn = document.getElementById('seo-cl-acct-save');
                    if (!saveBtn) return;

                    saveBtn.addEventListener('click', function(e){
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        var btn        = this;
                        var userId     = btn.getAttribute('data-user-id');
                        var msg        = document.getElementById('seo-cl-acct-msg');
                        var permName   = btn.getAttribute('data-perm-name')   === '1';
                        var permEmail  = btn.getAttribute('data-perm-email')  === '1';
                        var permPwd    = btn.getAttribute('data-perm-pwd')    === '1';
                        var permAvatar = btn.getAttribute('data-perm-avatar') === '1';

                        btn.disabled = true; btn.textContent = 'Saving…';

                        function showMsg(ok, text){
                            if (!msg) return;
                            msg.style.display = 'block';
                            msg.style.background = ok ? 'rgba(63,185,80,.12)' : 'rgba(220,38,38,.12)';
                            msg.style.border     = ok ? '1px solid rgba(63,185,80,.3)' : '1px solid rgba(220,38,38,.3)';
                            msg.style.color      = ok ? 'var(--cc-green,#3fb950)' : 'var(--cc-red,#f85149)';
                            msg.style.padding    = '10px 14px';
                            msg.style.borderRadius = '6px';
                            msg.style.fontSize   = '13px';
                            msg.textContent = text;
                            setTimeout(function(){ msg.style.display='none'; }, 4000);
                        }

                        function doSave(avatarId, removeAvatar){
                            var fd = new FormData();
                            fd.append('action',  'seo_dash_client_account_save');
                            fd.append('nonce',   '<?php echo wp_create_nonce("seo_dash_frontend"); ?>');
                            fd.append('user_id', userId);
                            var nameEl  = document.getElementById('seo-cl-acct-name');
                            var emailEl = document.getElementById('seo-cl-acct-email');
                            var pwdEl   = document.getElementById('seo-cl-acct-pwd');
                            if (permName  && nameEl)  fd.append('display_name', nameEl.value);
                            if (permEmail && emailEl) fd.append('user_email',   emailEl.value);
                            if (permPwd   && pwdEl)   fd.append('user_pass',    pwdEl.value);
                            if (permAvatar && avatarId)     fd.append('avatar_id',     avatarId);
                            if (permAvatar && removeAvatar) fd.append('avatar_remove', '1');

                            fetch('<?php echo esc_url(admin_url("admin-ajax.php")); ?>', {
                                method: 'POST', body: fd, credentials: 'same-origin'
                            })
                            .then(function(r){ return r.json(); })
                            .then(function(r){
                                btn.disabled = false; btn.textContent = 'Save Changes';
                                showMsg(r.success, r.data || (r.success ? 'Saved successfully!' : 'Error saving.'));
                                if (r.success && permName && nameEl){
                                    var letter = nameEl.value.charAt(0).toUpperCase();
                                    document.querySelectorAll('.seo-cl-avatar').forEach(function(el){
                                        el.textContent = letter;
                                    });
                                }
                                // Clear pending avatar state
                                window._seoDashPendingFile  = null;
                                window._seoDashRemoveAvatar = false;
                            })
                            .catch(function(){
                                btn.disabled = false; btn.textContent = 'Save Changes';
                                showMsg(false, 'Network error. Please try again.');
                            });
                        }

                        if (permAvatar && window._seoDashPendingFile){
                            var fd2 = new FormData();
                            fd2.append('action',  'seo_dash_upload_avatar');
                            fd2.append('nonce',   '<?php echo wp_create_nonce("seo_dash_frontend"); ?>');
                            fd2.append('user_id', userId);
                            fd2.append('avatar',  window._seoDashPendingFile);
                            fetch('<?php echo esc_url(admin_url("admin-ajax.php")); ?>', {
                                method: 'POST', body: fd2, credentials: 'same-origin'
                            })
                            .then(function(r){ return r.json(); })
                            .then(function(r){ doSave(r.success ? r.data.attachment_id : null, false); })
                            .catch(function(){ doSave(null, false); });
                        } else {
                            doSave(null, permAvatar && window._seoDashRemoveAvatar);
                        }
                    });
                })();
                </script>

            </div>
            <?php endif; ?>

        </div><!-- /.seo-cl-content -->

        <!-- Footer -->
        <footer class="seo-cl-footer">
            <?php if ($footer) : ?>
                <?php echo esc_html($footer); ?>
            <?php else : ?>
                Powered by <?php if ($agency) : ?><a href="<?php echo esc_url($agency); ?>" target="_blank"><?php echo esc_html($brand); ?></a><?php else : ?><?php echo esc_html($brand); ?><?php endif; ?>
                <?php if ($support) : ?> &middot; <a href="mailto:<?php echo esc_attr($support); ?>"><?php echo esc_html($support); ?></a><?php endif; ?>
            <?php endif; ?>
        </footer>
    </div><!-- /.seo-cl-main -->

</div><!-- /.seo-client-app -->

<script>
(function(){
    /* ── Sidebar / mobile toggle ── */
    var sidebar   = document.getElementById('seo-sidebar');
    var overlay   = document.getElementById('seo-sidebar-overlay');
    var hamburger = document.getElementById('seo-hamburger');
    if(hamburger){
        hamburger.addEventListener('click',function(){sidebar.classList.toggle('open');overlay.classList.toggle('open');});
        overlay.addEventListener('click',function(){sidebar.classList.remove('open');overlay.classList.remove('open');});
    }

    /* ── Tab switching ── */
    var navBtns  = document.querySelectorAll('.seo-cl-nav-btn[data-tab]');
    var panels   = document.querySelectorAll('.seo-cl-panel-tab[data-tab]');
    var topTitle = document.getElementById('seo-topbar-title');
    var navIcons = {
        'overview':'📊','analytics':'📈','sc':'🔍','service':'📄',
        'blog':'📝','gmb':'📍','technical':'⚙️','backlinks':'🔗',
        'leads':'🎯','ai':'🤖','account':'👤'
    };
    var navSubtitles = {
        'overview'  : 'Real-time insights across traffic, rankings, backlinks &amp; more',
        'analytics' : 'Understand who visits, how they find you, and what keeps them coming back',
        'sc'        : 'See exactly how Google sees your site — clicks, impressions &amp; rankings',
        'service'   : 'Measure how your most important pages are performing in search',
        'blog'      : 'Discover which content drives traffic and sparks engagement',
        'gmb'       : 'Stay on top of your local presence and Google Business visibility',
        'technical' : 'Catch issues before they cost you rankings — speed, errors &amp; health',
        'backlinks' : 'Track who links to you and how your domain authority is growing',
        'leads'     : 'Every enquiry matters — monitor, manage and convert your leads',
        'ai'        : 'Your smart SEO co-pilot — instant insights, recommendations &amp; answers',
        'account'   : 'Manage your profile, preferences and account settings'
    };
    var navLabels = {
        'overview':'Overview','analytics':'Analytics','sc':'Search Console',
        'service':'Service Pages','blog':'Blog Posts','gmb':'Google Business',
        'technical':'Technical Audit','backlinks':'Backlinks','leads':'Leads',
        'ai':'AI Assistant','account':'Account'
    };
    var customTabTitles = <?php echo json_encode($custom_tab_titles ?? []); ?>;
    var customTabSubs   = <?php echo json_encode($custom_tab_subs ?? []); ?>;

    function activateTab(tab){
        navBtns.forEach(function(b){ b.classList.toggle('active', b.dataset.tab===tab); });
        panels.forEach(function(p){ p.style.display = p.dataset.tab===tab ? '' : 'none'; });
        
        var reportTitle = "<?php echo esc_js(esc_html($report['title'] ?? '')); ?>";
        if(topTitle) {
            if (tab === 'overview') {
                topTitle.innerHTML = customTabTitles['overview'] || 'Your SEO Performance Dashboard';
            } else {
                var cTitle = customTabTitles[tab] || (navLabels[tab] || tab);
                topTitle.innerHTML = (navIcons[tab]||'') + ' ' + cTitle;
            }
        }
        if(document.getElementById('seo-hero-sub')) {
            document.getElementById('seo-hero-sub').innerHTML = customTabSubs[tab] || navSubtitles[tab] || reportTitle;
        }

        var heroEl = document.getElementById('seo-main-topbar');
        if (tab === 'overview') {
            if(document.getElementById('seo-hero-greeting')) document.getElementById('seo-hero-greeting').style.display = '';
            if(document.getElementById('seo-overview-daterange')) document.getElementById('seo-overview-daterange').style.display = '';
            if(document.getElementById('seo-hero-stats')) document.getElementById('seo-hero-stats').style.display = '';
            if(heroEl) heroEl.classList.remove('seo-ov3-hero--compact');
        } else {
            if(document.getElementById('seo-hero-greeting')) document.getElementById('seo-hero-greeting').style.display = 'none';
            if(document.getElementById('seo-overview-daterange')) document.getElementById('seo-overview-daterange').style.display = 'none';
            if(document.getElementById('seo-hero-stats')) document.getElementById('seo-hero-stats').style.display = 'none';
            if(heroEl) heroEl.classList.add('seo-ov3-hero--compact');
        }
        /* close mobile sidebar */
        if(sidebar) sidebar.classList.remove('open');
        if(overlay) overlay.classList.remove('open');

        /* Re-measure any Chart.js instances inside the now-visible panel.
           Charts built while their panel was display:none (Overview's
           traffic/ranked/backlinks charts, and the GMB performance chart —
           both build eagerly on page load via Chart.js polling, not on
           tab activation like Analytics/SC/Service/Blog) get 0x0 canvas
           size and never recover on their own. We use a double-RAF + timeout
           to ensure the browser has fully painted the panel as visible before
           we ask Chart.js to measure and resize. */
        function refreshTabCharts(tabName) {
            var tabPan = document.querySelector('.seo-cl-panel-tab[data-tab="'+tabName+'"]');
            if (tabPan) {
                tabPan.querySelectorAll('canvas').forEach(function(cv){
                    cv.removeAttribute('width');
                    cv.removeAttribute('height');
                    cv.style.width = '100%';
                    cv.style.height = '100%';
                });
            }
            setTimeout(function() {
                if (tabName === 'analytics' && typeof window.seoUpdateAnaChart === 'function') {
                    window.seoUpdateAnaChart();
                } else if (tabName === 'sc' && typeof window.seoUpdateSCChart === 'function') {
                    window.seoUpdateSCChart();
                } else if (tabName === 'service' && typeof window.seoUpdateSPChart === 'function') {
                    window.seoUpdateSPChart();
                } else if (tabName === 'blog' && typeof window.seoUpdateBlogChart === 'function') {
                    window.seoUpdateBlogChart();
                } else if (tabName === 'gmb') {
                    if (typeof window.seoGmbEnsureChart === 'function') window.seoGmbEnsureChart();
                    if (typeof window.buildGmbPerfChart === 'function') window.buildGmbPerfChart();
                    if (typeof window.buildGmbPostsChart === 'function') window.buildGmbPostsChart();
                } else if (tabName === 'technical') {
                    if (typeof window.initTechChartsClient === 'function') window.initTechChartsClient();
                } else if (tabName === 'backlinks') {
                    if (typeof window.renderBacklinksCharts === 'function') {
                        if (window._lastBkMeta) window.renderBacklinksCharts(null, window._lastBkMeta);
                        else if (window._lastBkRows) window.renderBacklinksCharts(window._lastBkRows);
                    }
                } else if (tabName === 'leads') {
                    if (typeof window.ldInitCharts === 'function') window.ldInitCharts();
                    if (typeof window.seoLdEnsureCharts === 'function') window.seoLdEnsureCharts();
                }
                if (typeof Chart !== 'undefined' && Chart.instances) {
                    Object.keys(Chart.instances).forEach(function(key){
                        var inst = Chart.instances[key];
                        if (inst && typeof inst.resize === 'function') {
                            try { inst.resize(); } catch(e){}
                        }
                    });
                }
            }, 60);
        }

        refreshTabCharts(tab);
    }

    navBtns.forEach(function(btn){
        btn.addEventListener('click',function(){
            activateTab(btn.dataset.tab);
        });
    });

    /* Auto-init if tab is already active on load */
    (function(){
        var activeBtn=document.querySelector('.seo-cl-nav-btn.active[data-tab]');
        if(activeBtn && activeBtn.dataset.tab) {
            refreshTabCharts(activeBtn.dataset.tab);
        }
    })();

    /* ── Reveal the correct panel on initial load ──────────────────────
       Every tab panel except 'overview' is rendered with style="display:none;"
       and only becomes visible via activateTab(), which previously only ran
       on a nav-button click. The server marks the correct nav button with
       class "active" (based on $first_visible — the report's first enabled
       tab), but the matching panel stayed hidden until the user clicked a
       tab themselves. Call activateTab() once on load for whichever tab is
       actually active so the page isn't blank on first paint / report switch. */
    (function(){
        var activeBtn = document.querySelector('.seo-cl-nav-btn.active[data-tab]');
        var initialTab = activeBtn ? activeBtn.dataset.tab : 'overview';
        activateTab(initialTab);
    })();

    /* ── Dark / light mode toggle ──────────────────────────────────────
       Moved to a self-contained script next to the theme button itself
       (see the button markup near the top of this template) — it no
       longer lives here because the old version's init call rebuilt
       Analytics/SC/Service/Blog charts before their data had loaded,
       and an uncaught error there used to silently prevent everything
       after it in this script tag (including this block) from running. */


    var docAjax  = (typeof seoDashClient !== 'undefined') ? seoDashClient.ajax : '';
    var docNonce = (typeof seoDashClient !== 'undefined') ? seoDashClient.nonce : '';
    var docRid   = (typeof seoDashClient !== 'undefined') ? seoDashClient.report_id : 0;

    function docFileIcon(type, url) {
        if (type === 'url') return '&#128279;';
        var ext = (url || '').split('.').pop().toLowerCase();
        var map = {pdf:'&#128277;',doc:'&#128216;',docx:'&#128216;',xls:'&#128218;',xlsx:'&#128218;',ppt:'&#128217;',pptx:'&#128217;',jpg:'&#128444;',jpeg:'&#128444;',png:'&#128444;',gif:'&#128444;',zip:'&#128230;'};
        return map[ext] || '&#128196;';
    }

    function docStatusDropdown(docId, current) {
        var opts = ['pending','approved','disapproved','needs_changes'];
        var c = _docStatusColors[current] || _docStatusColors.pending;
        var html = '<select class="seo-doc-status-sel" data-doc-id="'+docId+'" style="padding:5px 8px;border-radius:8px;border:1.5px solid '+c.border+';background:'+c.bg+';color:'+c.color+';font-size:12px;font-weight:700;cursor:pointer;appearance:auto;">';
        opts.forEach(function(o){
            html += '<option value="'+o+'"'+(o===current?' selected':'')+'>'+_docStatusLabels[o]+'</option>';
        });
        html += '</select>';
        return html;
    }

    function loadClientDocs() {
        var $tbody = document.getElementById('seo-cl-doc-tbody');
        var $badge = document.getElementById('seo-cl-doc-pending-badge');
        if (!$tbody || !docAjax || !docRid) return;
        $tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--cc-subtle);">Loading&#8230;</td></tr>';

        var fd = new FormData();
        fd.append('action',    'seo_dash_doc_client_list');
        fd.append('nonce',     docNonce);
        fd.append('report_id', docRid);

        fetch(docAjax, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(r){
            if (!r.success || !r.data || !r.data.length) {
                $tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:48px;color:var(--cc-subtle);"><div style="font-size:36px;margin-bottom:12px;">&#128196;</div><div style="font-weight:600;margin-bottom:6px;">No Documents Yet</div><div style="font-size:13px;">Your agency will share documents here for your review.</div></td></tr>';
                if ($badge) $badge.style.display = 'none';
                return;
            }
            var pending = r.data.filter(function(d){ return d.status === 'pending'; }).length;
            if ($badge) {
                if (pending > 0) { $badge.style.display=''; $badge.textContent = pending + ' pending'; }
                else { $badge.style.display = 'none'; }
            }
            var html = '';
            r.data.forEach(function(d, i) {
                var fileUrl  = d.file_url || '#';
                var hasNote  = !!(d.client_notes && d.client_notes.trim());
                var noteSafe = hasNote ? d.client_notes.replace(/"/g, '&quot;').replace(/</g,'&lt;') : '';
                var rowBg    = (i % 2 === 0) ? 'background:var(--cc-surf);' : 'background:var(--cc-surf2);';
                html += '<tr style="'+rowBg+'border-bottom:1px solid var(--cc-border);">';
                // #
                html += '<td style="padding:10px 14px;color:var(--cc-subtle);font-size:12px;">'+(i+1)+'</td>';
                // Document name with icon + agency note tooltip
                html += '<td style="padding:10px 14px;">';
                html += '<div style="display:flex;align-items:flex-start;gap:8px;">';
                html += '<span style="font-size:18px;line-height:1.3;flex-shrink:0;">'+docFileIcon(d.file_type, d.file_url)+'</span>';
                html += '<div>';
                html += '<div><a href="'+fileUrl+'" target="_blank" style="color:var(--cc-primary,#6366f1);font-weight:600;font-size:13px;text-decoration:none;">'+(d.title || d.file_name || 'Document')+'</a></div>';
                if (d.file_name && d.file_type !== 'url') html += '<div style="font-size:11px;color:var(--cc-subtle);">'+d.file_name+'</div>';
                if (d.admin_notes) html += '<div style="font-size:11px;color:var(--cc-subtle);margin-top:3px;">&#128083; '+d.admin_notes.replace(/</g,'&lt;')+'</div>';
                html += '</div></div>';
                html += '</td>';
                // Date
                html += '<td style="padding:10px 14px;font-size:12px;color:var(--cc-subtle);white-space:nowrap;">'+(d.created_at ? d.created_at.split(' ')[0] : '&#8212;')+'</td>';
                // Status dropdown
                html += '<td style="padding:10px 14px;">'+docStatusDropdown(d.id, d.status || 'pending')+'</td>';
                // Notes
                html += '<td style="padding:10px 14px;">';
                if (hasNote) {
                    html += '<div style="display:flex;gap:6px;align-items:center;">';
                    html += '<button class="seo-doc-note-btn" data-id="'+d.id+'" data-notes="'+noteSafe+'" data-mode="view" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;">&#128065; View</button>';
                    html += '<button class="seo-doc-note-btn" data-id="'+d.id+'" data-notes="'+noteSafe+'" data-mode="edit" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;">&#128221; Edit</button>';
                    html += '</div>';
                } else {
                    html += '<button class="seo-doc-note-btn" data-id="'+d.id+'" data-notes="" data-mode="edit" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;">+ Add Note</button>';
                }
                html += '</td>';
                // View
                html += '<td style="padding:10px 14px;"><a href="'+fileUrl+'" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;font-size:12px;font-weight:600;color:var(--cc-text);text-decoration:none;white-space:nowrap;">&#128065; View</a></td>';
                html += '</tr>';
            });
            $tbody.innerHTML = html;
        })
        .catch(function(){
            $tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:32px;color:#ef4444;">Failed to load documents.</td></tr>';
        });
    }

    // Status dropdown change
    document.addEventListener('change', function(e) {
        var sel = e.target.closest('.seo-doc-status-sel');
        if (!sel) return;
        var docId  = parseInt(sel.getAttribute('data-doc-id'));
        var status = sel.value;
        var c = _docStatusColors[status] || _docStatusColors.pending;
        sel.style.borderColor = c.border;
        sel.style.background  = c.bg;
        sel.style.color       = c.color;
        sel.disabled = true;

        var fd = new FormData();
        fd.append('action',     'seo_dash_doc_client_action');
        fd.append('nonce',      docNonce);
        fd.append('doc_id',     docId);
        fd.append('doc_action', status);
        fd.append('client_notes', ''); // notes saved separately
        fetch(docAjax, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(r){
            sel.disabled = false;
        })
        .catch(function(){ sel.disabled = false; });
    });

    // Notes modal open
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.seo-doc-note-btn');
        if (!btn) return;
        _docNoteId = parseInt(btn.getAttribute('data-id'));
        var mode  = btn.getAttribute('data-mode') || 'edit';
        var notes = btn.getAttribute('data-notes') || '';
        var vb    = document.getElementById('seo-doc-note-view-body');
        var ta    = document.getElementById('seo-doc-note-input');
        var er    = document.getElementById('seo-doc-note-edit-row');
        var title = document.getElementById('seo-doc-note-modal-title');
        if (mode === 'view') {
            if (title) title.textContent = '\u{1F4DD} View Note';
            if (vb)  { vb.textContent = notes; vb.style.display = 'block'; }
            if (ta)  ta.style.display = 'none';
            if (er)  er.style.display = 'none';
        } else {
            if (title) title.textContent = '\u{1F4DD} Add / Edit Note';
            if (vb)  vb.style.display = 'none';
            if (ta)  { ta.value = notes; ta.style.display = 'block'; }
            if (er)  er.style.display = 'flex';
        }
        var modal = document.getElementById('seo-doc-note-modal');
        if (modal) modal.style.display = 'flex';
        if (mode === 'edit' && ta) setTimeout(function(){ ta.focus(); }, 80);
    });

    var _docNoteModal = document.getElementById('seo-doc-note-modal');
    if (_docNoteModal) {
        _docNoteModal.addEventListener('click', function(e){
            if (e.target === this) seoDocCloseNoteModal();
        });
    }

    window.seoDocCloseNoteModal = function() {
        var modal = document.getElementById('seo-doc-note-modal');
        if (modal) modal.style.display = 'none';
    };

    window.seoDocSaveNote = function() {
        var ta    = document.getElementById('seo-doc-note-input');
        var notes = ta ? ta.value : '';
        var saveBtn = document.getElementById('seo-doc-note-save-btn');
        if (!docAjax || !docNonce || !_docNoteId) return;
        if (saveBtn) { saveBtn.textContent = 'Saving\u2026'; saveBtn.disabled = true; }

        // First save notes
        var fd = new FormData();
        fd.append('action',       'seo_dash_doc_client_save_notes');
        fd.append('nonce',        docNonce);
        fd.append('doc_id',       _docNoteId);
        fd.append('client_notes', notes);
        fetch(docAjax, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(r){
            if (saveBtn) { saveBtn.textContent = 'Save Note'; saveBtn.disabled = false; }
            if (r.success) {
                // Update button in table
                var row = document.querySelector('.seo-doc-note-btn[data-id="'+_docNoteId+'"]');
                var td  = row ? row.closest('td') : null;
                if (td) {
                    var safeNotes = notes.replace(/"/g, '&quot;').replace(/</g,'&lt;');
                    if (notes.trim()) {
                        td.innerHTML = '<div style="display:flex;gap:6px;align-items:center;">'
                            + '<button class="seo-doc-note-btn" data-id="'+_docNoteId+'" data-notes="'+safeNotes+'" data-mode="view" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;">&#128065; View</button>'
                            + '<button class="seo-doc-note-btn" data-id="'+_docNoteId+'" data-notes="'+safeNotes+'" data-mode="edit" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;">&#128221; Edit</button>'
                            + '</div>';
                    } else {
                        td.innerHTML = '<button class="seo-doc-note-btn" data-id="'+_docNoteId+'" data-notes="" data-mode="edit" style="background:var(--cc-surf2);border:1px solid var(--cc-border);border-radius:8px;padding:4px 10px;font-size:12px;color:var(--cc-text);cursor:pointer;white-space:nowrap;">+ Add Note</button>';
                    }
                }
                seoDocCloseNoteModal();
            }
        })
        .catch(function(){ if (saveBtn) { saveBtn.textContent = 'Save Note'; saveBtn.disabled = false; } });
    };

})();
</script>

