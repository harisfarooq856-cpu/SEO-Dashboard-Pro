<?php if ( ! defined('ABSPATH') ) exit;
// Fetch trashed reports separately
global $wpdb;
$trashed_reports = $wpdb->get_results(
    "SELECT * FROM " . SEO_Dash_Database::$reports . " WHERE status = 'trash' ORDER BY updated_at DESC",
    ARRAY_A
) ?: [];
?>
<div class="seo-page">

    <div class="seo-page-hd">
        <div>
            <h1 class="seo-page-title">📋 Reports</h1>
            <p class="seo-page-subtitle">Manage all client SEO reports</p>
        </div>
        <div class="seo-page-actions" style="display:flex;gap:8px;align-items:center;">
            <button class="seo-btn seo-btn-ghost seo-btn-sm" id="rpt-toggle-trash"
                    style="position:relative;">
                🗑 Trash
                <?php if (!empty($trashed_reports)) : ?>
                <span style="background:var(--c-red);color:#fff;font-size:10px;font-weight:700;padding:1px 5px;border-radius:10px;margin-left:4px;"><?php echo count($trashed_reports); ?></span>
                <?php endif; ?>
            </button>
            <button class="seo-btn seo-btn-primary" onclick="seoOpenModal('seo-modal-new-report')">＋ New Report</button>
        </div>
    </div>

    <!-- ══ ACTIVE REPORTS ══════════════════════════════════════════════ -->
    <div id="rpt-active-section">
        <?php if ( empty($reports) ) : ?>
        <div class="seo-panel">
            <div class="seo-empty">
                <div class="seo-empty-icon">📋</div>
                <h3>No reports yet</h3>
                <p>Create your first report to start managing client SEO dashboards.</p>
                <button class="seo-btn seo-btn-primary seo-btn-lg" onclick="seoOpenModal('seo-modal-new-report')">＋ Create First Report</button>
            </div>
        </div>
        <?php else : ?>
        <div class="seo-panel">

            <!-- Toolbar -->
            <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--c-border);flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;white-space:nowrap;">
                    <input type="checkbox" id="rpt-select-all" style="width:15px;height:15px;accent-color:var(--c-primary);cursor:pointer;">
                    <span id="rpt-sel-label" style="color:var(--c-muted);">Select All</span>
                </label>
                <div style="width:1px;height:20px;background:var(--c-border);"></div>
                <select id="rpt-bulk-action" class="seo-in seo-in-sm" style="width:180px;">
                    <option value="">— Bulk Action —</option>
                    <option value="trash">🗑 Move to Trash</option>
                    <option value="delete">💥 Delete Permanently</option>
                </select>
                <button id="rpt-bulk-apply" class="seo-btn seo-btn-sm" style="background:var(--c-primary);color:#fff;white-space:nowrap;">▶ Apply</button>
                <span id="rpt-bulk-status" style="font-size:12px;color:var(--c-muted);"></span>
                <div style="flex:1;min-width:20px;"></div>
                <div style="position:relative;">
                    <span style="position:absolute;left:8px;top:50%;transform:translateY(-50%);pointer-events:none;">🔍</span>
                    <input type="text" class="seo-in seo-in-sm" id="rpt-search" placeholder="Search reports…"
                           style="padding-left:30px;width:230px;" autocomplete="nope">
                </div>
                <span id="rpt-count-badge" class="seo-count-chip"><?php echo $reports_total; ?> report<?php echo $reports_total!==1?'s':''; ?></span>
            </div>

            <!-- Table -->
            <div class="seo-table-wrap">
                <table class="seo-table" id="rpt-reports-table">
                    <thead>
                        <tr>
                            <th style="width:36px;padding:10px 12px;"></th>
                            <th style="padding:10px 12px;">#</th>
                            <th style="padding:10px 12px;">Report</th>
                            <th style="padding:10px 12px;">Clients</th>
                            <th style="padding:10px 12px;">Data</th>
                            <th style="padding:10px 12px;">Created</th>
                            <th style="padding:10px 12px;text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rpt-reports-tbody">
                    <?php foreach ($reports as $i => $r) :
                        $cids      = SEO_Dash_Database::get_report_client_ids(intval($r['id']));
                        $ga_months = SEO_Dash_Database::get_months(SEO_Dash_Database::$data_ga, intval($r['id']));
                        $edit_url  = add_query_arg(['seo_page'=>'report','id'=>$r['id']], $base);
                    ?>
                    <tr data-id="<?php echo intval($r['id']); ?>"
                        data-title="<?php echo esc_attr($r['title']); ?>"
                        data-search="<?php echo esc_attr(strtolower($r['title'])); ?>">
                        <td style="padding:10px 12px;text-align:center;">
                            <input type="checkbox" class="rpt-row-chk" value="<?php echo intval($r['id']); ?>"
                                   style="width:15px;height:15px;accent-color:var(--c-primary);cursor:pointer;">
                        </td>
                        <td style="padding:10px 12px;font-size:12px;color:var(--c-muted);font-weight:700;"><?php echo $i+1; ?></td>
                        <td style="padding:10px 12px;">
                            <div style="font-weight:700;font-size:14px;"><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($r['title']); ?></a></div>
                            <div style="font-size:11px;color:var(--c-subtle);margin-top:2px;">ID #<?php echo intval($r['id']); ?></div>
                        </td>
                        <td style="padding:10px 12px;">
                            <?php if (empty($cids)) : ?>
                                <span style="color:var(--c-subtle);font-size:12px;">None</span>
                            <?php else : ?>
                                <span class="seo-badge"><?php echo count($cids); ?> client<?php echo count($cids)!==1?'s':''; ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px 12px;color:var(--c-muted);font-size:12px;"><?php echo count($ga_months); ?> month<?php echo count($ga_months)!==1?'s':''; ?></td>
                        <td style="padding:10px 12px;color:var(--c-muted);font-size:12px;"><?php echo esc_html(date_i18n('M j, Y', strtotime($r['created_at']))); ?></td>
                        <td style="padding:10px 12px;">
                            <div style="display:flex;gap:6px;align-items:center;justify-content:center;">
                                <a href="<?php echo esc_url($edit_url); ?>" class="seo-btn seo-btn-ghost seo-btn-xs">✏️ Edit</a>
                                <button class="seo-btn seo-btn-ghost seo-btn-xs rpt-trash-single"
                                        data-id="<?php echo intval($r['id']); ?>"
                                        data-title="<?php echo esc_attr($r['title']); ?>"
                                        title="Move to Trash">🗑</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div style="padding:10px 16px;border-top:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center;">
                <span id="rpt-selected-count" style="font-size:12px;color:var(--c-muted);">0 selected</span>
                <span style="font-size:12px;color:var(--c-muted);"><?php echo $reports_total; ?> total</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Load More -->
    <?php if ( count($reports) < $reports_total ) : ?>
    <div id="rpt-load-more-wrap" style="text-align:center;padding:16px 0;">
        <button type="button" id="rpt-load-more-btn" class="seo-btn seo-btn-ghost"
                data-offset="<?php echo count($reports); ?>"
                data-total="<?php echo intval($reports_total); ?>"
                style="min-width:160px;"
                onclick="if(window.__seoLoadMoreHandler){window.__seoLoadMoreHandler(this);}">
            ⬇ Load More Reports
            <span style="font-size:11px;color:var(--c-muted);margin-left:6px;">(<?php echo count($reports); ?> of <?php echo $reports_total; ?>)</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- ══ TRASH BIN ═══════════════════════════════════════════════════ -->
    <div id="rpt-trash-section" style="display:none;margin-top:20px;">
        <div class="seo-panel">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--c-border);flex-wrap:wrap;gap:10px;">
                <h2 style="font-size:14px;font-weight:700;color:var(--c-text);margin:0;">
                    🗑 Trash Bin
                    <span class="seo-count-chip" style="margin-left:8px;" id="rpt-trash-count"><?php echo count($trashed_reports); ?></span>
                </h2>
                <?php if (!empty($trashed_reports)) : ?>
                <button id="rpt-empty-trash-btn" class="seo-btn seo-btn-danger seo-btn-sm">💥 Empty Trash</button>
                <?php endif; ?>
            </div>

            <?php if (empty($trashed_reports)) : ?>
            <div style="padding:40px;text-align:center;color:var(--c-muted);font-size:13px;">Trash is empty.</div>
            <?php else : ?>
            <div class="seo-table-wrap">
                <table class="seo-table" id="rpt-trash-table">
                    <thead>
                        <tr>
                            <th style="padding:10px 12px;">Report</th>
                            <th style="padding:10px 12px;">Trashed</th>
                            <th style="padding:10px 12px;text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rpt-trash-tbody">
                    <?php foreach ($trashed_reports as $r) : ?>
                    <tr data-id="<?php echo intval($r['id']); ?>" data-title="<?php echo esc_attr($r['title']); ?>">
                        <td style="padding:10px 12px;">
                            <div style="font-weight:600;font-size:13px;color:var(--c-text);"><?php echo esc_html($r['title']); ?></div>
                            <div style="font-size:11px;color:var(--c-subtle);">ID #<?php echo intval($r['id']); ?></div>
                        </td>
                        <td style="padding:10px 12px;color:var(--c-muted);font-size:12px;"><?php echo esc_html(date_i18n('M j, Y', strtotime($r['updated_at']))); ?></td>
                        <td style="padding:10px 12px;">
                            <div style="display:flex;gap:6px;align-items:center;justify-content:center;">
                                <button class="seo-btn seo-btn-ghost seo-btn-xs rpt-restore-btn"
                                        data-id="<?php echo intval($r['id']); ?>"
                                        data-title="<?php echo esc_attr($r['title']); ?>">↩ Restore</button>
                                <button class="seo-icon-btn seo-icon-btn-d rpt-perma-delete-btn"
                                        data-id="<?php echo intval($r['id']); ?>"
                                        data-title="<?php echo esc_attr($r['title']); ?>"
                                        title="Delete permanently">💥</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.seo-page -->

<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
document.addEventListener('DOMContentLoaded', function(){
(function($){

    var PAGE           = 5;
    var searchTimer    = null;
    var currentSearch  = '';

    /* ── helpers ─────────────────────────────────────────────────────────── */
    function ajaxPost(action, extraData, cb) {
        $.post(seoDash.ajax, $.extend({action:action, nonce:seoDash.nonce}, extraData), cb)
         .fail(function(){ seoToast('Network error.','err'); });
    }

    function updateSelCount() {
        var n = $('.rpt-row-chk:checked').length;
        $('#rpt-selected-count').text(n + ' selected');
        $('#rpt-sel-label').text(n > 0 ? n + ' selected' : 'Select All');
    }

    function esc(str) {
        return $('<div>').text(str).html();
    }

    function buildRow(r, idx) {
        var editUrl = seoDash.base_url + '?seo_page=report&id=' + r.id;
        var clients = r.client_count > 0
            ? '<span class="seo-badge">'+r.client_count+' client'+(r.client_count!==1?'s':'')+'</span>'
            : '<span style="color:var(--c-subtle);font-size:12px;">None</span>';
        var months  = (r.ga_months||0)+' month'+((r.ga_months||0)!==1?'s':'');
        var created = r.created_at ? r.created_at.substring(0,10) : '';
        return '<tr data-id="'+r.id+'" data-title="'+esc(r.title)+'" data-search="'+esc(r.title.toLowerCase())+'">'
            +'<td style="padding:10px 12px;text-align:center;"><input type="checkbox" class="rpt-row-chk" value="'+r.id+'" style="width:15px;height:15px;accent-color:var(--c-primary);cursor:pointer;"></td>'
            +'<td style="padding:10px 12px;font-size:12px;color:var(--c-muted);font-weight:700;">'+idx+'</td>'
            +'<td style="padding:10px 12px;"><div style="font-weight:700;font-size:14px;"><a href="'+editUrl+'">'+esc(r.title)+'</a></div>'
            +'<div style="font-size:11px;color:var(--c-subtle);margin-top:2px;">ID #'+r.id+'</div></td>'
            +'<td style="padding:10px 12px;">'+clients+'</td>'
            +'<td style="padding:10px 12px;color:var(--c-muted);font-size:12px;">'+months+'</td>'
            +'<td style="padding:10px 12px;color:var(--c-muted);font-size:12px;">'+created+'</td>'
            +'<td style="padding:10px 12px;"><div style="display:flex;gap:6px;align-items:center;justify-content:center;">'
            +'<a href="'+editUrl+'" class="seo-btn seo-btn-ghost seo-btn-xs">✏️ Edit</a>'
            +'<button class="seo-btn seo-btn-ghost seo-btn-xs rpt-trash-single" data-id="'+r.id+'" data-title="'+esc(r.title)+'" title="Move to Trash">🗑</button>'
            +'</div></td>'
            +'</tr>';
    }

    /* ── Server-side search (searches ALL reports, not just loaded rows) ─── */
    $('#rpt-search').on('input', function(){
        clearTimeout(searchTimer);
        var q = $(this).val().trim();
        searchTimer = setTimeout(function(){
            currentSearch = q;
            var $btn = $('#rpt-load-more-btn');
            ajaxPost('seo_dash_get_reports_paged', {limit: PAGE, offset: 0, search: q}, function(r){
                if (!r.success) { seoToast('Search failed.','err'); return; }
                var rows  = r.data.reports || [];
                var total = r.data.total   || 0;
                var newOff = r.data.offset || rows.length;

                // Rebuild tbody
                $('#rpt-reports-tbody').empty();
                if (rows.length === 0) {
                    $('#rpt-reports-tbody').append(
                        '<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--c-muted);font-size:13px;">'
                        + (q ? 'No reports match <strong>'+esc(q)+'</strong>.' : 'No reports yet.')
                        + '</td></tr>'
                    );
                } else {
                    $.each(rows, function(i, rep){ $('#rpt-reports-tbody').append(buildRow(rep, i+1)); });
                }

                // Update count badge and footer total
                $('#rpt-count-badge').text(total + ' report' + (total!==1?'s':''));
                $('#rpt-selected-count').text('0 selected');
                $('#rpt-select-all').prop('checked', false).prop('indeterminate', false);

                // Reset Load More
                if (newOff < total) {
                    $btn.data('offset', newOff).data('total', total)
                        .html('⬇ Load More Reports <span style="font-size:11px;color:var(--c-muted);margin-left:6px;">('+newOff+' of '+total+')</span>')
                        .prop('disabled', false);
                    $('#rpt-load-more-wrap').show();
                } else {
                    $('#rpt-load-more-wrap').hide();
                }
            });
        }, 300);
    });

    /* ── Load More (respects current search) ─────────────────────────────── */
    $(document).on('click', '#rpt-load-more-btn', function(){
        var $btn   = $(this);
        var offset = parseInt($btn.data('offset')) || 0;
        var total  = parseInt($btn.data('total'))  || 0;
        $btn.prop('disabled', true).text('Loading…');

        ajaxPost('seo_dash_get_reports_paged', {limit: PAGE, offset: offset, search: currentSearch}, function(r){
            if (!r.success) {
                seoToast('Failed to load reports.','err');
                $btn.prop('disabled', false).text('⬇ Load More Reports');
                return;
            }
            var rows   = r.data.reports || [];
            var newOff = r.data.offset  || (offset + rows.length);
            var total  = r.data.total   || 0;
            var rowCount = $('#rpt-reports-tbody tr[data-id]').length;

            $.each(rows, function(i, rep){
                $('#rpt-reports-tbody').append(buildRow(rep, rowCount + i + 1));
            });

            $btn.data('offset', newOff);
            if (newOff >= total) {
                $('#rpt-load-more-wrap').hide();
            } else {
                $btn.prop('disabled', false)
                    .html('⬇ Load More Reports <span style="font-size:11px;color:var(--c-muted);margin-left:6px;">('+newOff+' of '+total+')</span>');
            }
        });
    });

    /* ── Toggle trash section ─────────────────────────────────────────────── */
    $('#rpt-toggle-trash').on('click', function(){
        var $t = $('#rpt-trash-section'), $a = $('#rpt-active-section');
        if ($t.is(':visible')) { $t.hide(); $a.show(); }
        else                   { $t.show(); $a.hide(); }
    });

    /* ── Select All ──────────────────────────────────────────────────────── */
    $('#rpt-select-all').on('change', function(){
        $('#rpt-reports-tbody tr:visible .rpt-row-chk').prop('checked', this.checked);
        updateSelCount();
    });
    $('#rpt-reports-tbody').on('change', '.rpt-row-chk', function(){
        var total   = $('#rpt-reports-tbody tr:visible .rpt-row-chk').length;
        var checked = $('#rpt-reports-tbody tr:visible .rpt-row-chk:checked').length;
        $('#rpt-select-all').prop('indeterminate', checked > 0 && checked < total)
                             .prop('checked', checked === total && total > 0);
        updateSelCount();
    });

    /* ── Single: move to trash ───────────────────────────────────────────── */
    $(document).on('click', '.rpt-trash-single', function(){
        var id = $(this).data('id'), title = $(this).data('title'), $row = $(this).closest('tr');
        if (!confirm('Move "'+title+'" to trash?')) return;
        $(this).prop('disabled', true);
        ajaxPost('seo_dash_trash_report', {report_id:id}, function(r){
            if (r.success){
                $row.fadeOut(300, function(){ $(this).remove(); });
                seoToast('Moved to trash.','ok');
                setTimeout(function(){ window.location.reload(); }, 500);
            } else seoToast(r.data&&r.data.message?r.data.message:'Failed.','err');
        });
    });

    /* ── Bulk Apply ──────────────────────────────────────────────────────── */
    $('#rpt-bulk-apply').on('click', function(){
        var action   = $('#rpt-bulk-action').val();
        var $checked = $('.rpt-row-chk:checked');
        if (!action)          { seoToast('Choose a bulk action.','err'); return; }
        if (!$checked.length) { seoToast('Select at least one report.','err'); return; }

        var ids   = $checked.map(function(){ return $(this).val(); }).get();
        var names = $checked.map(function(){ return $(this).closest('tr').data('title'); }).get();
        var ajaxAction = action === 'trash' ? 'seo_dash_trash_report' : 'seo_dash_delete_report';
        var confirmMsg = action === 'trash'
            ? 'Move '+ids.length+' report(s) to trash?\n\n'+names.join('\n')
            : 'PERMANENTLY delete '+ids.length+' report(s)?\n\n'+names.join('\n')+'\n\nThis cannot be undone.';

        if (!confirm(confirmMsg)) return;

        var done=0, $btn=$(this).prop('disabled', true);
        $('#rpt-bulk-status').text('Processing…');

        ids.forEach(function(id){
            var $row = $('#rpt-reports-tbody tr[data-id="'+id+'"]');
            ajaxPost(ajaxAction, {report_id:id}, function(r){
                done++;
                if (r.success) $row.fadeOut(250, function(){ $(this).remove(); });
                if (done === ids.length){
                    $btn.prop('disabled', false);
                    $('#rpt-bulk-status').text('');
                    $('#rpt-select-all').prop('checked',false).prop('indeterminate',false);
                    updateSelCount();
                    seoToast(done+' report(s) '+(action==='trash'?'moved to trash.':'deleted.'),'ok');
                    setTimeout(function(){ window.location.reload(); }, 500);
                }
            });
        });
    });

    /* ── Trash bin: Restore ──────────────────────────────────────────────── */
    $(document).on('click', '.rpt-restore-btn', function(){
        var id=$(this).data('id'), title=$(this).data('title'), $row=$(this).closest('tr');
        if (!confirm('Restore "'+title+'"?')) return;
        $(this).prop('disabled', true);
        ajaxPost('seo_dash_restore_report', {report_id:id}, function(r){
            if (r.success){
                $row.fadeOut(300, function(){
                    $(this).remove();
                    $('#rpt-trash-count').text(Math.max(0, parseInt($('#rpt-trash-count').text())-1));
                });
                seoToast('Report restored.','ok');
                setTimeout(function(){ window.location.reload(); }, 500);
            } else seoToast(r.data&&r.data.message?r.data.message:'Failed.','err');
        });
    });

    /* ── Trash bin: Permanent delete ─────────────────────────────────────── */
    $(document).on('click', '.rpt-perma-delete-btn', function(){
        var id=$(this).data('id'), title=$(this).data('title'), $row=$(this).closest('tr');
        if (!confirm('Permanently delete "'+title+'" and ALL its data?\n\nThis cannot be undone.')) return;
        $(this).prop('disabled', true);
        ajaxPost('seo_dash_delete_report', {report_id:id}, function(r){
            if (r.success){
                $row.fadeOut(300, function(){
                    $(this).remove();
                    $('#rpt-trash-count').text(Math.max(0, parseInt($('#rpt-trash-count').text())-1));
                });
                seoToast('Permanently deleted.','ok');
            } else seoToast(r.data&&r.data.message?r.data.message:'Failed.','err');
        });
    });

    /* ── Empty Trash ─────────────────────────────────────────────────────── */
    $('#rpt-empty-trash-btn').on('click', function(){
        var rows = $('#rpt-trash-tbody tr');
        if (!rows.length) return;
        if (!confirm('Permanently delete ALL '+rows.length+' trashed reports and their data?\n\nThis cannot be undone.')) return;
        var $btn=$(this).prop('disabled',true).text('Deleting…');
        var done=0, total=rows.length;
        rows.each(function(){
            var id=$(this).data('id'), $row=$(this);
            ajaxPost('seo_dash_delete_report', {report_id:id}, function(r){
                done++;
                if (r.success) $row.fadeOut(250, function(){ $(this).remove(); });
                if (done===total){
                    $btn.remove();
                    $('#rpt-trash-count').text(0);
                    seoToast('Trash emptied.','ok');
                }
            });
        });
    });

})(jQuery);
});
</script>

<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-skip-lazy data-pagespeed-no-defer>
/* ────────────────────────────────────────────────────────────────────────────
 * Load More Reports — self-contained vanilla handler.
 *
 * Why this exists: the main script block above runs inside a
 * DOMContentLoaded + IIFE(jQuery) wrapper. If this view is injected into the
 * page after DOMContentLoaded has already fired (admin tab/SPA-style loading),
 * that listener never runs and the jQuery click handler never binds — so the
 * Load More button does nothing. This handler binds immediately on the
 * document in the CAPTURE phase, so:
 *   • it works whether or not the jQuery block ran;
 *   • it does not depend on jQuery being present;
 *   • capture-phase + stopImmediatePropagation prevents the bubble-phase
 *     jQuery handler (if it DID bind) from also firing, so rows are never
 *     double-appended.
 * Offset is computed from the rows actually on screen, so it stays correct
 * regardless of what jQuery's internal .data() cache holds after a search.
 * Scope is limited to this file; no other feature is touched.
 * ──────────────────────────────────────────────────────────────────────── */
(function(){
    if (window.__seoRptLoadMoreBound) return;   // never bind twice
    window.__seoRptLoadMoreBound = true;

    var PAGE = 5;

    function esc(str){
        var d = document.createElement('div');
        d.textContent = (str == null ? '' : String(str));
        return d.innerHTML;
    }

    function baseUrl(){
        return (window.seoDash && seoDash.base_url) ? seoDash.base_url : '';
    }

    function buildRowHtml(r, idx){
        var editUrl = baseUrl() + '?seo_page=report&id=' + r.id;
        var cc = parseInt(r.client_count, 10) || 0;
        var clients = cc > 0
            ? '<span class="seo-badge">'+cc+' client'+(cc!==1?'s':'')+'</span>'
            : '<span style="color:var(--c-subtle);font-size:12px;">None</span>';
        var gm = parseInt(r.ga_months, 10) || 0;
        var months = gm + ' month' + (gm!==1?'s':'');
        var created = r.created_at ? String(r.created_at).substring(0,10) : '';
        var title = r.title || '';
        return '<tr data-id="'+r.id+'" data-title="'+esc(title)+'" data-search="'+esc(title.toLowerCase())+'">'
            +'<td style="padding:10px 12px;text-align:center;"><input type="checkbox" class="rpt-row-chk" value="'+r.id+'" style="width:15px;height:15px;accent-color:var(--c-primary);cursor:pointer;"></td>'
            +'<td style="padding:10px 12px;font-size:12px;color:var(--c-muted);font-weight:700;">'+idx+'</td>'
            +'<td style="padding:10px 12px;"><div style="font-weight:700;font-size:14px;"><a href="'+editUrl+'">'+esc(title)+'</a></div>'
            +'<div style="font-size:11px;color:var(--c-subtle);margin-top:2px;">ID #'+r.id+'</div></td>'
            +'<td style="padding:10px 12px;">'+clients+'</td>'
            +'<td style="padding:10px 12px;color:var(--c-muted);font-size:12px;">'+months+'</td>'
            +'<td style="padding:10px 12px;color:var(--c-muted);font-size:12px;">'+created+'</td>'
            +'<td style="padding:10px 12px;"><div style="display:flex;gap:6px;align-items:center;justify-content:center;">'
            +'<a href="'+editUrl+'" class="seo-btn seo-btn-ghost seo-btn-xs">✏️ Edit</a>'
            +'<button class="seo-btn seo-btn-ghost seo-btn-xs rpt-trash-single" data-id="'+r.id+'" data-title="'+esc(title)+'" title="Move to Trash">🗑</button>'
            +'</div></td>'
            +'</tr>';
    }

    function toast(msg, type){
        if (typeof window.seoToast === 'function') { window.seoToast(msg, type || 'err'); }
    }

    function setBtnLabel(btn, offset, total){
        btn.innerHTML = '⬇ Load More Reports <span style="font-size:11px;color:var(--c-muted);margin-left:6px;">('+offset+' of '+total+')</span>';
    }

    function handle(btn){
        if (btn.dataset.loading === '1') return;   // guard against rapid double-clicks
        btn.dataset.loading = '1';

        var tbody = document.getElementById('rpt-reports-tbody');
        // Offset = number of report rows currently rendered (source of truth).
        var offset = tbody ? tbody.querySelectorAll('tr[data-id]').length : (parseInt(btn.getAttribute('data-offset'),10) || 0);
        var searchEl = document.getElementById('rpt-search');
        var search = searchEl ? searchEl.value.trim() : '';

        btn.disabled = true;
        btn.textContent = 'Loading…';

        var ajaxUrl = (window.seoDash && seoDash.ajax) ? seoDash.ajax : '';
        var nonce   = (window.seoDash && seoDash.nonce) ? seoDash.nonce : '';

        var params = new URLSearchParams();
        params.append('action', 'seo_dash_get_reports_paged');
        params.append('nonce', nonce);
        params.append('limit', PAGE);
        params.append('offset', offset);
        params.append('search', search);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: params.toString()
        })
        .then(function(res){ return res.json(); })
        .then(function(r){
            btn.dataset.loading = '0';
            if (!r || !r.success || !r.data) {
                toast('Failed to load reports.','err');
                btn.disabled = false;
                btn.textContent = '⬇ Load More Reports';
                return;
            }
            var rows  = r.data.reports || [];
            var total = parseInt(r.data.total, 10) || 0;
            var newOff = (typeof r.data.offset !== 'undefined')
                ? (parseInt(r.data.offset, 10) || (offset + rows.length))
                : (offset + rows.length);

            if (tbody) {
                var baseIdx = tbody.querySelectorAll('tr[data-id]').length;
                var html = '';
                for (var i = 0; i < rows.length; i++) {
                    html += buildRowHtml(rows[i], baseIdx + i + 1);
                }
                tbody.insertAdjacentHTML('beforeend', html);
            }

            var wrap = document.getElementById('rpt-load-more-wrap');
            if (newOff >= total) {
                if (wrap) wrap.style.display = 'none';
            } else {
                btn.setAttribute('data-offset', newOff);
                btn.disabled = false;
                setBtnLabel(btn, newOff, total);
            }
        })
        .catch(function(){
            btn.dataset.loading = '0';
            toast('Network error.','err');
            btn.disabled = false;
            btn.textContent = '⬇ Load More Reports';
        });
    }

    // Expose handler so the button's inline onclick attribute can call it directly.
    // This is the ultimate fallback: inline event attributes cannot be deferred or
    // stripped by any host optimizer, so the button always works even if the script
    // blocks below are somehow delayed.
    window.__seoLoadMoreHandler = handle;

    // Capture phase: runs before the bubble-phase jQuery delegated handler and
    // stops it, so only ONE handler ever processes the click.
    document.addEventListener('click', function(e){
        var btn = e.target && e.target.closest ? e.target.closest('#rpt-load-more-btn') : null;
        if (!btn) return;
        e.preventDefault();
        e.stopImmediatePropagation();   // block the jQuery handler from also firing
        handle(btn);
    }, true);
})();
</script>
