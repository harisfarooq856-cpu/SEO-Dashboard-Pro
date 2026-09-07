<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="seo-modal" id="seo-modal-new-report">
    <div class="seo-modal-bg" data-close="seo-modal-new-report"></div>
    <div class="seo-modal-box" style="max-width:480px;">
        <div class="seo-modal-hd">
            <h3>Create New Report</h3>
            <button class="seo-modal-x" data-close="seo-modal-new-report">&#x2715;</button>
        </div>
        <div class="seo-modal-bd">
            <input type="hidden" id="new-report-id" value="">
            <div class="seo-field">
                <label>Report Title <span style="color:var(--c-red);">*</span></label>
                <input type="text" id="new-report-title" class="seo-in" autocomplete="nope" placeholder="e.g. Acme Corp - April 2025">
            </div>
            <div id="seo-create-report-msg" style="display:none;font-size:13px;color:var(--c-red);margin-top:4px;"></div>
        </div>
        <div class="seo-modal-ft">
            <button class="seo-btn seo-btn-ghost" data-close="seo-modal-new-report">Cancel</button>
            <button class="seo-btn seo-btn-primary" id="seo-create-report-btn">Create Report</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    $('#seo-create-report-btn').on('click', function(){
        var title = $('#new-report-title').val().trim();
        if (!title) {
            $('#seo-create-report-msg').text('Please enter a report title.').show();
            return;
        }
        $('#seo-create-report-msg').hide();
        var $btn = $(this).text('Creating...').prop('disabled', true);

        $.post(seoDash.ajax, {
            action: 'seo_dash_save_report',
            nonce:  seoDash.nonce,
            title:  title
        }, function(r) {
            if (r.success && r.data && r.data.report_id) {
                var rid  = r.data.report_id;
                var base = seoDash.base_url ? seoDash.base_url.split('?')[0].replace(/\/$/, '') : window.location.href.split('?')[0];
                var url  = base + '?seo_page=report&id=' + rid;
                $btn.text('Redirecting...');
                window.location.href = url;
            } else {
                $btn.text('Create Report').prop('disabled', false);
                var msg = (r.data && r.data.message) ? r.data.message : 'Failed to create report.';
                $('#seo-create-report-msg').text(msg).show();
            }
        }).fail(function(xhr) {
            $btn.text('Create Report').prop('disabled', false);
            $('#seo-create-report-msg').text('Server error - check debug log.').show();
            console.error('Create report failed:', xhr.responseText);
        });
    });

    $('#new-report-title').on('keydown', function(e) {
        if (e.key === 'Enter') $('#seo-create-report-btn').trigger('click');
    });

    $('#seo-modal-new-report').on('seo-modal-open', function() {
        $('#new-report-title').val('');
        $('#seo-create-report-msg').hide();
        $('#seo-create-report-btn').text('Create Report').prop('disabled', false);
        setTimeout(function(){ $('#new-report-title').focus(); }, 100);
    });
})(jQuery);
});
</script>
