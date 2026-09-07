<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="seo-page">

    <div class="seo-page-hd">
        <div>
            <h1 class="seo-page-title">Welcome back, <?php echo esc_html( wp_get_current_user()->display_name ); ?></h1>
            <p class="seo-page-subtitle">Here's what's happening across your SEO reports</p>
        </div>
        <div class="seo-page-actions">

            <button class="seo-btn seo-btn-primary" onclick="seoOpenModal('seo-modal-new-report')">+ New Report</button>
        </div>
    </div>

    <!-- KPI stats -->
    <div class="seo-stats-row">
        <div class="seo-stat-card seo-ac-blue">
            <div class="seo-stat-val"><?php echo count($reports); ?></div>
            <div class="seo-stat-lbl">Active Reports</div>
            <div class="seo-stat-sub">Client dashboards</div>
        </div>
        <div class="seo-stat-card seo-ac-violet">
            <div class="seo-stat-val"><?php echo count($clients); ?></div>
            <div class="seo-stat-lbl">Clients</div>
            <div class="seo-stat-sub">With portal access</div>
        </div>
        <div class="seo-stat-card seo-ac-green">
            <div class="seo-stat-val"><?php echo $total_leads; ?></div>
            <div class="seo-stat-lbl">Total Leads</div>
            <div class="seo-stat-sub">Across all reports</div>
        </div>
        <div class="seo-stat-card seo-ac-orange">
            <div class="seo-stat-val"><?php echo $total_backlinks; ?></div>
            <div class="seo-stat-lbl">Backlinks</div>
            <div class="seo-stat-sub">Tracked links</div>
        </div>
        <div class="seo-stat-card seo-ac-teal">
            <div class="seo-stat-val"><?php echo $total_intgs; ?></div>
            <div class="seo-stat-lbl">Integrations</div>
            <div class="seo-stat-sub">API connections</div>
        </div>
    </div>

    <div class="seo-two-col">

        <!-- Recent Reports -->
        <div>
            <div class="seo-section-bar">
                <div>
                    <h2 class="seo-section-title">Recent Reports</h2>
                </div>
                <a href="<?php echo esc_url( add_query_arg('seo_page','reports',$base) ); ?>" class="seo-btn seo-btn-ghost seo-btn-sm">View all</a>
            </div>

            <?php if ( empty($reports) ) : ?>
            <div class="seo-panel">
                <div class="seo-empty">
                    <h3>No reports yet</h3>
                    <p>Create your first SEO report to get started.</p>
                    <button class="seo-btn seo-btn-primary" onclick="seoOpenModal('seo-modal-new-report')">+ Create First Report</button>
                </div>
            </div>
            <?php else : ?>
            <div class="seo-card-grid">
                <?php foreach ( array_slice($reports, 0, 6) as $r ) :
                    $cids  = SEO_Dash_Database::get_report_client_ids( intval($r['id']) );
                    $url   = add_query_arg( ['seo_page'=>'report','id'=>$r['id']], $base );
                    global $wpdb;
                    $sessions = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT SUM(sessions) FROM " . SEO_Dash_Database::$data_ga . " WHERE report_id=%d AND trashed=0",
                        $r['id']
                    ) );
                    $leads = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_leads . " WHERE report_id=%d AND trashed=0",
                        $r['id']
                    ) );
                ?>
                <div class="seo-report-card">
                    <div class="seo-report-card-bar"></div>
                    <div class="seo-report-card-body">
                        <div class="seo-report-card-title"><?php echo esc_html($r['title']); ?></div>
                        <div class="seo-report-card-meta">
                            <span><?php echo count($cids); ?> client<?php echo count($cids)!==1?'s':''; ?></span>
                            <span style="color:var(--c-subtle);">·</span>
                            <span><?php echo esc_html( date_i18n('M j, Y', strtotime($r['created_at'])) ); ?></span>
                        </div>
                    </div>
                    <div class="seo-kpi-row">
                        <div class="seo-kpi">
                            <span class="seo-kpi-v seo-kpi-green"><?php echo number_format($sessions); ?></span>
                            <span class="seo-kpi-l">Sessions</span>
                        </div>
                        <div class="seo-kpi">
                            <span class="seo-kpi-v seo-kpi-orange"><?php echo $leads; ?></span>
                            <span class="seo-kpi-l">Leads</span>
                        </div>
                    </div>
                    <div class="seo-report-card-foot">
                        <a href="<?php echo esc_url($url); ?>" class="seo-btn seo-btn-primary seo-btn-sm" style="flex:1;justify-content:center;">Open Report</a>
                        <button class="seo-icon-btn seo-icon-btn-d seo-del-report-btn"
                                data-id="<?php echo intval($r['id']); ?>"
                                data-title="<?php echo esc_attr($r['title']); ?>"
                                title="Delete">&#128465;</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right column -->
        <div style="display:flex;flex-direction:column;gap:20px;">

            <!-- Quick actions -->
            <div class="seo-panel">
                <div class="seo-panel-hd"><h2>Quick Actions</h2></div>
                <div class="seo-panel-body">
                    <div class="seo-qa-grid">
                        <button class="seo-qa-btn" onclick="seoOpenModal('seo-modal-new-report')">
                            <span class="seo-qa-icon" style="font-size:22px;">&#128203;</span>
                            <span>New Report</span>
                        </button>
                        <button class="seo-qa-btn" onclick="seoOpenModal('seo-modal-new-client')">
                            <span class="seo-qa-icon" style="font-size:22px;">&#128100;</span>
                            <span>Add Client</span>
                        </button>
                        <a href="<?php echo esc_url(add_query_arg('seo_page','integrations',$base)); ?>" class="seo-qa-btn">
                            <span class="seo-qa-icon" style="font-size:22px;">&#128268;</span>
                            <span>Integrations</span>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('seo_page','settings',$base)); ?>" class="seo-qa-btn">
                            <span class="seo-qa-icon" style="font-size:22px;">&#9881;&#65039;</span>
                            <span>Settings</span>
                        </a>

                    </div>
                </div>
            </div>

            <!-- Setup checklist -->
            <div class="seo-panel">
                <div class="seo-panel-hd"><h2>Setup Checklist</h2></div>
                <div class="seo-panel-body">
                    <?php
                    $checks = [
                        [ count($reports) > 0,                                      'Create your first report',         add_query_arg('seo_page','reports',$base) ],
                        [ count($clients) > 0,                                      'Add a client',                     add_query_arg('seo_page','clients',$base) ],
                        [ count(SEO_Dash_Database::get_integrations()) > 0,         'Connect a Google integration',     add_query_arg('seo_page','integrations',$base) ],
                        [ !empty(SEO_Dash_Database::get_setting('groq_api_key')),   'Add Groq API key for AI',          add_query_arg('seo_page','settings',$base) ],
                        [ !empty(SEO_Dash_Database::get_setting('brand_name')),     'Set your agency brand name',       add_query_arg('seo_page','settings',$base) ],
                    ];
                    $done = count(array_filter($checks, fn($c)=>$c[0]));
                    $pct  = round($done/count($checks)*100);
                    ?>
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--c-subtle);margin-bottom:6px;">
                        <span><?php echo $done; ?>/<?php echo count($checks); ?> complete</span>
                        <span><?php echo $pct; ?>%</span>
                    </div>
                    <div class="seo-prog-bar" style="margin-bottom:14px;">
                        <div class="seo-prog-fill" style="width:<?php echo $pct; ?>%;"></div>
                    </div>
                    <ul class="seo-checklist">
                        <?php foreach ($checks as [$done_item, $label, $link]) : ?>
                        <li class="seo-check-item">
                            <span><?php echo $done_item ? '&#9989;' : '&#9744;'; ?></span>
                            <?php if ($done_item) : ?>
                                <span class="seo-check-done"><?php echo esc_html($label); ?></span>
                            <?php else : ?>
                                <a href="<?php echo esc_url($link); ?>" style="color:var(--c-primary);font-size:13px;"><?php echo esc_html($label); ?></a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    $(document).on('click', '.seo-del-report-btn', function(){
        var id    = $(this).data('id');
        var title = $(this).data('title');
        if (!confirm('Delete "' + title + '"? This cannot be undone.')) return;
        var $card = $(this).closest('.seo-report-card');
        $.post(seoDash.ajax, {action:'seo_dash_delete_report',nonce:seoDash.nonce,report_id:id}, function(r){
            if (r.success) { $card.fadeOut(300); seoToast('Report deleted.','ok'); }
            else seoToast('Failed to delete.','err');
        });
    });
})(jQuery);
});
</script>
