<?php if ( ! defined('ABSPATH') ) exit;
// Global integrations stored in wp_options (same structure as old plugin)
$all_intg = seo_dash_get_global_integrations();
?>
<div class="seo-page">

    <div class="seo-page-hd">
        <div>
            <div class="seo-page-title">Global Integrations</div>
            <p class="seo-page-subtitle">Create unlimited integrations, each with a unique name. Assign any integration to a report from the report's <strong>Integration tab</strong>. All integration names appear in the assignment dropdown.</p>
        </div>
        <button class="seo-btn seo-btn-primary" id="intg-add-new-btn">+ Add New Integration</button>
    </div>

    <div class="seo-alert seo-alert-info">
        All Service Account JSON keys are encrypted before storage. Keys are never exposed in the browser after saving.
    </div>

    <!-- Integration card grid -->
    <div id="intg-list-wrap" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;">
        <?php if (empty($all_intg)) : ?>
        <div id="intg-empty-state" style="grid-column:1/-1;text-align:center;padding:60px 20px;background:var(--c-surf);border:2px dashed var(--c-border2);border-radius:var(--r);">
            <div style="font-size:40px;margin-bottom:12px;">&#128268;</div>
            <strong style="display:block;font-size:15px;color:var(--c-muted);margin-bottom:6px;">No integrations yet</strong>
            <p style="font-size:13px;color:var(--c-subtle);margin:0 0 16px;">Click <strong>Add New Integration</strong> to create your first credential set.</p>
            <button class="seo-btn seo-btn-primary" id="intg-add-new-btn-2">+ Add New Integration</button>
        </div>
        <?php else : foreach ($all_intg as $intg) :
            $has_ga4 = !empty($intg['ga4_json_key']) && !empty($intg['ga4_property_id']);
            $has_gsc = !empty($intg['gsc_json_key']) && !empty($intg['gsc_site_url']);
            $has_psi = !empty($intg['psi_api_key']);
        ?>
        <div class="seo-intg-card" id="intg-card-<?php echo esc_attr($intg['id']); ?>">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;">
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--c-primary);margin-bottom:4px;">Integration Name</div>
                    <div style="font-size:16px;font-weight:800;color:var(--c-text);"><?php echo esc_html($intg['name']); ?></div>
                </div>
                <span style="font-size:11px;color:var(--c-subtle);white-space:nowrap;margin-top:4px;">
                    <?php
                    // Count reports using this integration
                    $report_count = 0;
                    if (!empty($intg['id'])) {
                        global $wpdb;
                        $like = '%"' . $wpdb->esc_like( $intg['id'] ) . '"%';
                        $report_count = (int)$wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'seo_dash_report_global_intg_%%' AND option_value LIKE %s",
                            $like
                        ));
                    }
                    echo $report_count.' report'.($report_count!==1?'s':'');
                    ?>
                </span>
            </div>

            <!-- Status badges -->
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
                <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;font-size:11px;font-weight:700;<?php echo $has_ga4 ? 'background:#dcfce7;color:#15803d;' : 'background:#fef3c7;color:#92400e;'; ?>">
                    &#128200; GA4 <?php echo $has_ga4 ? '&#9989;' : '&#9888;'; ?>
                </span>
                <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;font-size:11px;font-weight:700;<?php echo $has_gsc ? 'background:#dcfce7;color:#15803d;' : 'background:#fef3c7;color:#92400e;'; ?>">
                    &#128269; GSC <?php echo $has_gsc ? '&#9989;' : '&#9888;'; ?>
                </span>
                <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;font-size:11px;font-weight:700;<?php echo $has_psi ? 'background:#dcfce7;color:#15803d;' : 'background:#fef3c7;color:#92400e;'; ?>">
                    &#9889; PSI <?php echo $has_psi ? '&#9989;' : '&#9888;'; ?>
                </span>
                <?php $has_gsheet = !empty($intg['gsheet_id']); ?>
                <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;font-size:11px;font-weight:700;<?php echo $has_gsheet ? 'background:#dcfce7;color:#15803d;' : 'background:#f1f5f9;color:#64748b;'; ?>">
                    &#128202; Sheets <?php echo $has_gsheet ? '&#9989; <small style="font-weight:400;">' . esc_html($intg['gsheet_name']) . '</small>' : '&#8212;'; ?>
                </span>
            </div>

            <?php if (!empty($intg['notes'])) : ?>
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 12px;line-height:1.5;"><?php echo esc_html($intg['notes']); ?></p>
            <?php endif; ?>

            <div style="display:flex;gap:8px;margin-top:4px;">
                <button type="button" class="seo-btn seo-btn-primary seo-btn-sm intg-open-edit"
                    data-id="<?php echo esc_attr($intg['id']); ?>"
                    data-name="<?php echo esc_attr($intg['name']); ?>"
                    data-notes="<?php echo esc_attr($intg['notes'] ?? ''); ?>"
                    data-ga4prop="<?php echo esc_attr($intg['ga4_property_id'] ?? ''); ?>"
                    data-gscsite="<?php echo esc_attr($intg['gsc_site_url'] ?? ''); ?>"
                    data-psikey="<?php echo esc_attr($intg['psi_api_key'] ?? ''); ?>"
                    data-hasga4json="<?php echo $has_ga4 ? '1' : '0'; ?>"
                    data-hasgscjson="<?php echo $has_gsc ? '1' : '0'; ?>"
                    data-gsheetid="<?php echo esc_attr($intg['gsheet_id'] ?? ''); ?>"
                    data-gsheetname="<?php echo esc_attr($intg['gsheet_name'] ?? ''); ?>">
                    &#9999; Edit
                </button>
                <button type="button" class="seo-btn seo-btn-danger seo-btn-sm intg-delete-btn"
                    data-id="<?php echo esc_attr($intg['id']); ?>"
                    data-name="<?php echo esc_attr($intg['name']); ?>">
                    &#128465; Delete
                </button>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- ── ADD / EDIT MODAL ─────────────────────────────────────── -->
<div class="seo-modal" id="intg-modal">
    <div class="seo-modal-bg" id="intg-modal-bg"></div>
    <div class="seo-modal-box" style="max-width:680px;">
        <div class="seo-modal-hd">
            <h3 id="intg-modal-title">New Integration</h3>
            <button class="seo-modal-x" id="intg-modal-close">&#x2715;</button>
        </div>
        <div class="seo-modal-bd" style="gap:0;padding:0;">

            <!-- Integration name (highlighted) -->
            <div style="background:rgba(99,102,241,.07);border:2px solid var(--c-primary);border-radius:var(--r);padding:16px 20px;margin:20px 22px 0;">
                <div class="seo-field">
                    <label style="color:var(--c-primary);">Integration Name <span style="color:var(--c-red);">*</span></label>
                    <input type="text" id="intg-name" class="seo-in" autocomplete="nope" placeholder="e.g. Acme Corp, Google Agency Account" style="font-size:15px;font-weight:600;">
                    <div class="seo-field-hint">This name appears in the integration dropdown on each report's Integration tab.</div>
                </div>
            </div>

            <!-- Notes -->
            <div style="padding:16px 22px 0;">
                <div class="seo-field">
                    <label>Notes <span style="font-weight:400;color:var(--c-subtle);">(optional)</span></label>
                    <input type="text" id="intg-notes" class="seo-in" autocomplete="nope" placeholder="e.g. Shared service account for EU clients">
                </div>
            </div>

            <!-- GA4 section -->
            <div style="padding:16px 22px 0;">
                <div style="font-size:13px;font-weight:700;color:var(--c-primary);border-bottom:1px solid var(--c-border);padding-bottom:8px;margin-bottom:14px;">&#128200; Google Analytics 4</div>
                <div class="seo-field" style="margin-bottom:12px;">
                    <label>GA4 Property ID</label>
                    <input type="text" id="intg-ga4-prop" class="seo-in" autocomplete="nope" placeholder="e.g. 123456789">
                </div>
                <div class="seo-field">
                    <label>Service Account JSON Key</label>
                    <div id="intg-ga4-upload-area" style="border:2px dashed var(--c-border2);border-radius:var(--r);padding:12px 16px;background:var(--c-surf2);display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:8px;" onclick="document.getElementById('intg-ga4-file').click()">
                        <span style="font-size:20px;">&#128194;</span>
                        <div>
                            <strong id="intg-ga4-upload-label" style="display:block;color:var(--c-primary);font-size:13px;">Click to upload JSON file</strong>
                            <span style="font-size:12px;color:var(--c-muted);">or paste below</span>
                        </div>
                    </div>
                    <input type="file" id="intg-ga4-file" accept=".json,application/json" style="display:none;">
                    <textarea id="intg-ga4-json" class="seo-in" rows="4" style="font-family:monospace;font-size:12px;resize:vertical;" placeholder='{"type":"service_account","client_email":"…","private_key":"-----BEGIN RSA PRIVATE KEY-----\n…"}'></textarea>
                    <div id="intg-ga4-json-status" style="font-size:12px;margin-top:4px;"></div>
                </div>
            </div>

            <!-- GSC section -->
            <div style="padding:16px 22px 0;">
                <div style="font-size:13px;font-weight:700;color:var(--c-primary);border-bottom:1px solid var(--c-border);padding-bottom:8px;margin-bottom:14px;">&#128269; Google Search Console</div>
                <div class="seo-field" style="margin-bottom:12px;">
                    <label>Site URL <span style="font-weight:400;color:var(--c-subtle);">(verified in Search Console)</span></label>
                    <input type="url" id="intg-gsc-site" class="seo-in" placeholder="https://example.com/">
                </div>
                <div class="seo-field">
                    <label>Service Account JSON Key <span style="font-weight:400;color:var(--c-subtle);">(can reuse GA4 key)</span></label>
                    <div id="intg-gsc-upload-area" style="border:2px dashed var(--c-border2);border-radius:var(--r);padding:12px 16px;background:var(--c-surf2);display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:8px;" onclick="document.getElementById('intg-gsc-file').click()">
                        <span style="font-size:20px;">&#128194;</span>
                        <div>
                            <strong id="intg-gsc-upload-label" style="display:block;color:var(--c-primary);font-size:13px;">Click to upload JSON file</strong>
                            <span style="font-size:12px;color:var(--c-muted);">or paste below</span>
                        </div>
                    </div>
                    <input type="file" id="intg-gsc-file" accept=".json,application/json" style="display:none;">
                    <textarea id="intg-gsc-json" class="seo-in" rows="4" style="font-family:monospace;font-size:12px;resize:vertical;" placeholder='{"type":"service_account",…}'></textarea>
                    <div id="intg-gsc-json-status" style="font-size:12px;margin-top:4px;"></div>
                </div>
            </div>

            <!-- Google Sheets section -->
            <div style="padding:16px 22px 20px;">
                <div style="font-size:13px;font-weight:700;color:var(--c-primary);border-bottom:1px solid var(--c-border);padding-bottom:8px;margin-bottom:14px;">📊 Google Sheets</div>
                <div style="font-size:12px;color:var(--c-muted);margin-bottom:12px;line-height:1.6;">
                    Share your Google Sheet with the <strong>service account email</strong> from the GA4 JSON key above, then click <em>Load My Sheets</em> to pick a spreadsheet. The linked sheet can be synced into Database, Service Pages, and Blog Post tabs.
                </div>
                <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center;">
                    <button type="button" id="intg-gsheet-load-btn" class="seo-btn seo-btn-ghost seo-btn-sm" style="color:var(--c-primary);border-color:var(--c-primary);">
                        📋 Load My Sheets
                    </button>
                    <button type="button" id="intg-gsheet-hidden-toggle-btn" style="display:none;font-size:11px;color:var(--c-muted);background:none;border:none;cursor:pointer;text-decoration:underline;">Show hidden sheets</button>
                    <span id="intg-gsheet-status" style="font-size:12px;color:var(--c-muted);align-self:center;"></span>
                </div>
                <div id="intg-gsheet-picker-wrap" style="display:none;">
                    <div class="seo-field" style="margin-bottom:10px;">
                        <label>Select Spreadsheet <span style="font-weight:400;color:var(--c-muted);">(✕ removes a sheet from this list — it does not revoke Google Drive access)</span></label>
                        <div id="intg-gsheet-list" style="border:1px solid var(--c-border2);border-radius:6px;max-height:220px;overflow-y:auto;"></div>
                    </div>
                    <div id="intg-gsheet-saved-wrap" style="display:none;background:rgba(99,102,241,.07);border:1px solid var(--c-primary);border-radius:6px;padding:10px 14px;font-size:12px;color:var(--c-primary);">
                        ✅ Linked: <strong id="intg-gsheet-saved-name"></strong>
                        <button type="button" id="intg-gsheet-rename-btn" style="margin-left:12px;font-size:11px;color:var(--c-primary);background:none;border:none;cursor:pointer;text-decoration:underline;">✏️ Rename</button>
                        <button type="button" id="intg-gsheet-unlink-btn" style="margin-left:12px;font-size:11px;color:var(--c-red);background:none;border:none;cursor:pointer;">✕ Unlink</button>
                    </div>
                </div>
                <input type="hidden" id="intg-gsheet-id" value="">
                <input type="hidden" id="intg-gsheet-name" value="">
            </div>


            <div style="padding:16px 22px 20px;">
                <div style="font-size:13px;font-weight:700;color:var(--c-primary);border-bottom:1px solid var(--c-border);padding-bottom:8px;margin-bottom:14px;">&#9889; PageSpeed Insights</div>
                <div class="seo-field">
                    <label>API Key
                        <button type="button" id="intg-psi-show" style="margin-left:8px;padding:2px 8px;font-size:11px;cursor:pointer;border:1px solid var(--c-border2);border-radius:4px;background:var(--c-surf2);color:var(--c-muted);">Show</button>
                    </label>
                    <input type="password" id="intg-psi-key" class="seo-in" autocomplete="new-password" placeholder="AIza…">
                </div>
            </div>

            <div id="intg-modal-msg" style="display:none;margin:0 22px 16px;padding:10px 14px;border-radius:var(--r-sm);font-size:13px;"></div>

        </div>
        <div class="seo-modal-ft">
            <input type="hidden" id="intg-modal-id" value="">
            <button class="seo-btn seo-btn-ghost" id="intg-modal-cancel">Cancel</button>
            <button class="seo-btn seo-btn-primary" id="intg-save-btn">&#128190; Save Integration</button>
        </div>
    </div>
</div>

<style>
.seo-intg-card {
    background: var(--c-surf);
    border: 1px solid var(--c-border);
    border-radius: var(--r);
    padding: 20px;
    transition: box-shadow .2s, border-color .2s;
}
.seo-intg-card:hover {
    border-color: var(--c-border2);
    box-shadow: 0 4px 18px rgba(99,102,241,.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    var nonce = seoDash.nonce;

    /* ── JSON file upload helper ─────────────────────────── */
    function setupUpload(fileId, taId, stId, lblId) {
        var f = document.getElementById(fileId),
            ta = document.getElementById(taId),
            st = document.getElementById(stId),
            lbl = document.getElementById(lblId);
        if (!f) return;
        f.addEventListener('change', function() {
            var file = f.files[0]; if (!file) return;
            var r = new FileReader();
            r.onload = function(e) {
                try {
                    var p = JSON.parse(e.target.result);
                    if (!p.private_key || !p.client_email) {
                        st.textContent = 'Invalid JSON — missing private_key or client_email';
                        st.style.color = '#f85149'; return;
                    }
                    ta.value = e.target.result;
                    lbl.textContent = 'Loaded: ' + file.name;
                    st.textContent = 'JSON loaded — save to keep.';
                    st.style.color = 'var(--c-green)';
                } catch(err) {
                    st.textContent = 'Not valid JSON: ' + err.message;
                    st.style.color = '#f85149';
                }
            };
            r.readAsText(file);
        });
    }
    setupUpload('intg-ga4-file','intg-ga4-json','intg-ga4-json-status','intg-ga4-upload-label');
    setupUpload('intg-gsc-file','intg-gsc-json','intg-gsc-json-status','intg-gsc-upload-label');

    /* ── PSI key show/hide ───────────────────────────────── */
    $('#intg-psi-show').on('click', function(){
        var f = document.getElementById('intg-psi-key');
        f.type = (f.type === 'password') ? 'text' : 'password';
        $(this).text(f.type === 'password' ? 'Show' : 'Hide');
    });

    /* ── Open modal (new) ────────────────────────────────── */
    function openNewModal() {
        $('#intg-modal-id').val('');
        $('#intg-modal-title').text('New Integration');
        $('#intg-name,#intg-notes,#intg-ga4-prop,#intg-ga4-json,#intg-gsc-site,#intg-gsc-json,#intg-psi-key').val('');
        $('#intg-ga4-upload-label').text('Click to upload JSON file');
        $('#intg-gsc-upload-label').text('Click to upload JSON file');
        $('#intg-ga4-json-status,#intg-gsc-json-status').text('');
        showMsg('','');
        $('#intg-modal').addClass('seo-open');
        setTimeout(function(){ document.getElementById('intg-name').focus(); }, 100);
    }
    $('#intg-add-new-btn, #intg-add-new-btn-2').on('click', openNewModal);

    /* ── Open modal (edit) ───────────────────────────────── */
    $(document).on('click', '.intg-open-edit', function(){
        var d = $(this).data();
        $('#intg-modal-id').val(d.id);
        $('#intg-modal-title').text('Edit Integration');
        $('#intg-name').val(d.name);
        $('#intg-notes').val(d.notes || '');
        $('#intg-ga4-prop').val(d.ga4prop || '');
        $('#intg-ga4-json').val('');
        $('#intg-ga4-upload-label').text(d.hasga4json === '1' ? 'JSON saved — upload to replace' : 'Click to upload JSON file');
        $('#intg-ga4-json-status').text('');
        $('#intg-gsc-site').val(d.gscsite || '');
        $('#intg-gsc-json').val('');
        $('#intg-gsc-upload-label').text(d.hasgscjson === '1' ? 'JSON saved — upload to replace' : 'Click to upload JSON file');
        $('#intg-gsc-json-status').text('');
        $('#intg-psi-key').val(d.psikey || '');
        showMsg('','');
        $('#intg-modal').addClass('seo-open');
    });

    /* ── Close modal ─────────────────────────────────────── */
    $('#intg-modal-close, #intg-modal-cancel').on('click', function(){
        $('#intg-modal').removeClass('seo-open');
    });
    $('#intg-modal-bg').on('click', function(){
        $('#intg-modal').removeClass('seo-open');
    });

    /* ── Save integration ────────────────────────────────── */
    $('#intg-save-btn').on('click', function(){
        var name = $('#intg-name').val().trim();
        if (!name) { showMsg('err','Integration name is required.'); return; }

        var $btn = $(this).text('Saving…').prop('disabled', true);
        var fd = new FormData();
        fd.append('action',           'seo_dash_save_global_integration');
        fd.append('nonce',            nonce);
        fd.append('id',               $('#intg-modal-id').val());
        fd.append('name',             name);
        fd.append('notes',            $('#intg-notes').val().trim());
        fd.append('ga4_property_id',  $('#intg-ga4-prop').val().trim());
        fd.append('ga4_json_key',     $('#intg-ga4-json').val().trim());
        fd.append('gsc_site_url',     $('#intg-gsc-site').val().trim());
        fd.append('gsc_json_key',     $('#intg-gsc-json').val().trim());
        fd.append('psi_api_key',      $('#intg-psi-key').val().trim());
        fd.append('gsheet_id',        $('#intg-gsheet-id').val().trim());
        fd.append('gsheet_name',      $('#intg-gsheet-name').val().trim());

        fetch(seoDash.ajax, {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(res){
                $btn.text('Save Integration').prop('disabled', false);
                if (res.success) {
                    showMsg('ok','Saved! Reloading…');
                    setTimeout(function(){ location.reload(); }, 700);
                } else {
                    showMsg('err', res.data && res.data.message ? res.data.message : (res.data || 'Save failed'));
                }
            })
            .catch(function(err){
                $btn.text('Save Integration').prop('disabled', false);
                showMsg('err', err.message);
            });
    });

    /* ── Delete integration ──────────────────────────────── */
    $(document).on('click', '.intg-delete-btn', function(){
        var id = $(this).data('id'), name = $(this).data('name');
        if (!confirm('Delete integration "' + name + '"?\n\nReports using this integration will lose their credential assignment.')) return;
        var $card = $('#intg-card-' + id);
        var fd = new FormData();
        fd.append('action', 'seo_dash_delete_global_integration');
        fd.append('nonce',  nonce);
        fd.append('id',     id);
        fetch(seoDash.ajax, {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    $card.fadeOut(300, function(){ $(this).remove(); });
                    seoToast('Integration deleted.','ok');
                } else {
                    seoToast(res.data || 'Delete failed','err');
                }
            });
    });

    function showMsg(type, text) {
        var $m = $('#intg-modal-msg');
        if (!text) { $m.hide(); return; }
        $m.css({
            background: type === 'ok' ? 'rgba(63,185,80,.12)' : 'rgba(248,81,73,.12)',
            color:      type === 'ok' ? 'var(--c-green)' : 'var(--c-red)',
            border:     '1px solid ' + (type === 'ok' ? 'rgba(63,185,80,.3)' : 'rgba(248,81,73,.3)')
        }).text(text).show();
    }

    /* ── Google Sheets picker ────────────────────────────── */
    var gsheetShowHidden = false;

    function resetGsheetPicker() {
        $('#intg-gsheet-picker-wrap').hide();
        $('#intg-gsheet-list').html('');
        $('#intg-gsheet-saved-wrap').hide();
        $('#intg-gsheet-id,#intg-gsheet-name').val('');
        $('#intg-gsheet-status').text('');
        $('#intg-gsheet-hidden-toggle-btn').hide();
        gsheetShowHidden = false;
    }

    function showGsheetSaved(id, name) {
        if (id && name) {
            $('#intg-gsheet-id').val(id);
            $('#intg-gsheet-name').val(name);
            $('#intg-gsheet-saved-name').text(name);
            $('#intg-gsheet-saved-wrap').show();
            $('#intg-gsheet-picker-wrap').show();
        }
    }

    function gsheetErrText(res) {
        // seo_dash_json_error() returns { message: "..." } under res.data,
        // so the human-readable text lives at res.data.message.
        if (res && res.data) {
            if (typeof res.data === 'string') return res.data;
            if (res.data.message) return res.data.message;
        }
        return 'Failed to load sheets.';
    }

    function renderGsheetRows(sheets) {
        var $list = $('#intg-gsheet-list');
        $list.html('');
        var selectedId = $('#intg-gsheet-id').val();
        sheets.forEach(function(s){
            var $row = $('<div>').css({
                display:'flex', alignItems:'center', justifyContent:'space-between',
                padding:'8px 12px', borderBottom:'1px solid var(--c-border)',
                cursor:'pointer', opacity: s.hidden ? 0.55 : 1
            }).addClass('intg-gsheet-row').attr('data-id', s.id).attr('data-name', s.name);

            var $label = $('<span>').text(s.name + (s.hidden ? ' (hidden)' : ''));
            if (s.id === selectedId) $label.css('fontWeight', 700).css('color', 'var(--c-primary)');
            $row.append($label);

            var $btnWrap = $('<span>').css({display:'flex', gap:'10px', alignItems:'center'});

            var $renameBtn = $('<button>').attr('type','button').css({
                fontSize:'11px', background:'none', border:'none', cursor:'pointer', color:'var(--c-primary)'
            }).text('✏️ Rename');
            $renameBtn.on('click', function(ev){
                ev.stopPropagation();
                renameGsheet($('#intg-modal-id').val(), s.id, s.name);
            });
            $btnWrap.append($renameBtn);

            var $actionBtn = $('<button>').attr('type','button').css({
                fontSize:'11px', background:'none', border:'none', cursor:'pointer',
                color: s.hidden ? 'var(--c-green)' : 'var(--c-red)'
            }).text(s.hidden ? '↺ Unhide' : '✕ Hide');

            $actionBtn.on('click', function(ev){
                ev.stopPropagation();
                toggleHideGsheet($('#intg-modal-id').val(), s.id, !s.hidden);
            });
            $btnWrap.append($actionBtn);
            $row.append($btnWrap);

            $row.on('click', function(){
                $('#intg-gsheet-id').val(s.id);
                $('#intg-gsheet-name').val(s.name);
                $('#intg-gsheet-saved-name').text(s.name);
                $('#intg-gsheet-saved-wrap').show();
                renderGsheetRows(sheets); // re-render to update bold/selected state
            });

            $list.append($row);
        });
        if (!sheets.length) {
            $list.append($('<div>').css({padding:'10px 12px', fontSize:'12px', color:'var(--c-muted)'}).text('No spreadsheets in this view.'));
        }
    }

    function loadGsheets(intgId, isRetry) {
        $('#intg-gsheet-status').css('color', 'var(--c-muted)').text('Loading…');
        var fd = new FormData();
        fd.append('action',  'seo_dash_gsheet_list');
        fd.append('nonce',   nonce);
        fd.append('intg_id', intgId);
        if (gsheetShowHidden) fd.append('show_hidden', '1');

        fetch(seoDash.ajax, { method:'POST', body:fd })
            .then(function(r){
                if (!r.ok) { throw new Error('Server returned HTTP ' + r.status); }
                return r.json();
            })
            .then(function(res){
                if (!res.success) {
                    // Auto-refresh an expired nonce once, then retry.
                    if (res.data && res.data.nonce_expired && !isRetry) {
                        var rfd = new FormData();
                        rfd.append('action', 'seo_dash_refresh_nonce');
                        return fetch(seoDash.ajax, { method:'POST', body:rfd })
                            .then(function(rr){ return rr.json(); })
                            .then(function(nr){
                                if (nr.success && nr.data && nr.data.nonce) {
                                    nonce = nr.data.nonce;
                                    loadGsheets(intgId, true);
                                } else {
                                    $('#intg-gsheet-status').css('color', '#f85149').text('Session expired — reload the page.');
                                }
                            });
                    }
                    $('#intg-gsheet-status').css('color', '#f85149').text(gsheetErrText(res));
                    return;
                }
                var sheets = res.data.sheets || [];
                var hiddenCount = res.data.hidden_count || 0;
                renderGsheetRows(sheets);
                $('#intg-gsheet-picker-wrap').show();
                if (hiddenCount > 0) {
                    $('#intg-gsheet-hidden-toggle-btn').show()
                        .text(gsheetShowHidden ? 'Hide hidden sheets' : 'Show hidden sheets (' + hiddenCount + ')');
                } else {
                    $('#intg-gsheet-hidden-toggle-btn').hide();
                }
                if (sheets.length) {
                    $('#intg-gsheet-status').css('color', 'var(--c-green)').text(sheets.length + ' sheet(s) found.');
                } else {
                    $('#intg-gsheet-status').css('color', '#f85149')
                        .text('No spreadsheets found. Share the sheet with the service account email, then try again.');
                }
            })
            .catch(function(e){
                $('#intg-gsheet-status').css('color', '#f85149').text('Error: ' + e.message);
            });
    }

    function toggleHideGsheet(intgId, sheetId, hide) {
        if (!intgId) return;
        var fd = new FormData();
        fd.append('action', 'seo_dash_gsheet_hide');
        fd.append('nonce', nonce);
        fd.append('intg_id', intgId);
        fd.append('spreadsheet_id', sheetId);
        if (hide) fd.append('hide', '1');
        fetch(seoDash.ajax, { method:'POST', body:fd })
            .then(function(r){ return r.json(); })
            .then(function(){ loadGsheets(intgId, false); });
    }

    function renameGsheet(intgId, sheetId, currentName) {
        if (!intgId) return;
        var newName = prompt('Rename this Google Sheet to:', currentName);
        if (newName === null) return; // cancelled
        newName = newName.trim();
        if (!newName || newName === currentName) return;

        $('#intg-gsheet-status').css('color', 'var(--c-muted)').text('Renaming…');
        var fd = new FormData();
        fd.append('action', 'seo_dash_gsheet_rename');
        fd.append('nonce', nonce);
        fd.append('intg_id', intgId);
        fd.append('spreadsheet_id', sheetId);
        fd.append('new_name', newName);
        fetch(seoDash.ajax, { method:'POST', body:fd })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res && res.success) {
                    $('#intg-gsheet-status').css('color', 'var(--c-green)').text('✅ Renamed.');
                    // If the renamed sheet is the currently-selected one, update the saved name shown too.
                    if ($('#intg-gsheet-id').val() === sheetId) {
                        $('#intg-gsheet-name').val(newName);
                        $('#intg-gsheet-saved-name').text(newName);
                    }
                    loadGsheets(intgId, false);
                } else {
                    var em = (res && res.data && (res.data.message || res.data)) || 'Rename failed.';
                    $('#intg-gsheet-status').css('color', '#f85149').text(em);
                }
            });
    }

    $('#intg-gsheet-load-btn').on('click', function(){
        var intgId = $('#intg-modal-id').val();
        if (!intgId) {
            $('#intg-gsheet-status').css('color', '#f85149')
                .text('Save the integration first, then load sheets.');
            return;
        }
        loadGsheets(intgId, false);
    });

    $('#intg-gsheet-hidden-toggle-btn').on('click', function(){
        gsheetShowHidden = !gsheetShowHidden;
        loadGsheets($('#intg-modal-id').val(), false);
    });

    $('#intg-gsheet-rename-btn').on('click', function(){
        var intgId = $('#intg-modal-id').val();
        var sheetId = $('#intg-gsheet-id').val();
        var sheetName = $('#intg-gsheet-name').val();
        if (!intgId || !sheetId) return;
        renameGsheet(intgId, sheetId, sheetName);
    });

    $('#intg-gsheet-unlink-btn').on('click', function(){
        $('#intg-gsheet-id,#intg-gsheet-name').val('');
        $('#intg-gsheet-saved-wrap').hide();
        $('#intg-gsheet-list .intg-gsheet-row').css({fontWeight:400, color:''});
    });

    // Restore sheet data when opening edit modal.

    $(document).on('click', '.intg-open-edit', function(){
        resetGsheetPicker();
        var id = $(this).data('id');
        // Load saved sheet for this integration from wp_options (passed via data attr if needed).
        var savedSheetId   = $(this).data('gsheetid')   || '';
        var savedSheetName = $(this).data('gsheetname') || '';
        if (savedSheetId) showGsheetSaved(savedSheetId, savedSheetName);
    });

})(jQuery);
});
</script>

