<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="seo-modal" id="seo-modal-new-integration">
    <div class="seo-modal-bg" data-close="seo-modal-new-integration"></div>
    <div class="seo-modal-box" style="max-width:580px;">
        <div class="seo-modal-hd">
            <h3>Add Integration</h3>
            <button class="seo-modal-x" data-close="seo-modal-new-integration">✕</button>
        </div>
        <div class="seo-modal-bd">
            <input type="hidden" id="intg-id" value="">
            <div class="seo-field">
                <label>Label <span style="color:var(--c-red);">*</span></label>
                <input type="text" id="intg-label" class="seo-in" autocomplete="nope" placeholder="e.g. Acme Corp – Google">
            </div>
            <div class="seo-field" style="margin-top:12px;">
                <label>Type <span style="color:var(--c-red);">*</span></label>
                <select id="intg-type" class="seo-in">
                    <option value="">— Select type —</option>
                    <option value="google_analytics">📈 Google Analytics (GA4)</option>
                    <option value="search_console">🔍 Search Console</option>
                    <option value="gmb">📍 Google Business Profile</option>
                    <option value="psi">⚡ PageSpeed Insights</option>
                    <option value="groq">🤖 Groq AI</option>
                </select>
            </div>
            <div id="intg-creds-wrap" style="margin-top:4px;"></div>
            <div id="intg-test-result" style="display:none;margin-top:10px;padding:10px 14px;border-radius:var(--r);font-size:13px;"></div>
        </div>
        <div class="seo-modal-ft">
            <button class="seo-btn seo-btn-ghost seo-btn-sm" id="intg-test-live-btn" style="display:none;margin-right:auto;">🔍 Test</button>
            <button class="seo-btn seo-btn-ghost" data-close="seo-modal-new-integration">Cancel</button>
            <button class="seo-btn seo-btn-primary" id="seo-save-intg-btn">Save Integration</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    var credFields = {
        google_analytics: '<div class="seo-alert seo-alert-info" style="margin-top:12px;">Paste your Google Service Account JSON. Must have <strong>Analytics Viewer</strong> access to the GA4 property.</div><div class="seo-field" style="margin-top:12px;"><label>GA4 Property ID</label><input type="text" name="ga4_property_id" class="seo-in seo-in-sm" placeholder="123456789"></div><div class="seo-field" style="margin-top:12px;"><label>Service Account JSON</label><textarea name="service_account_json" class="seo-in" rows="7" placeholder=\'{"type":"service_account","client_email":"…","private_key":"-----BEGIN RSA PRIVATE KEY-----\\n…"}\'></textarea></div>',
        search_console:   '<div class="seo-alert seo-alert-info" style="margin-top:12px;">Service Account must be added as Owner/User in Google Search Console.</div><div class="seo-field" style="margin-top:12px;"><label>Site URL</label><input type="url" name="sc_site_url" class="seo-in seo-in-sm" placeholder="https://example.com/"></div><div class="seo-field" style="margin-top:12px;"><label>Service Account JSON</label><textarea name="service_account_json" class="seo-in" rows="7" placeholder=\'{"type":"service_account",…}\'></textarea></div>',
        gmb:              '<div class="seo-field" style="margin-top:12px;"><label>Service Account JSON</label><textarea name="service_account_json" class="seo-in" rows="7" placeholder=\'{"type":"service_account",…}\'></textarea></div>',
        psi:              '<div class="seo-alert seo-alert-info" style="margin-top:12px;">Get a free API key at <a href="https://console.cloud.google.com" target="_blank" style="color:var(--c-blue);">Google Cloud Console</a> — enable the PageSpeed Insights API.</div><div class="seo-field" style="margin-top:12px;"><label>API Key</label><input type="text" name="api_key" class="seo-in" placeholder="AIza…"></div>',
        groq:             '<div class="seo-alert seo-alert-info" style="margin-top:12px;">Get a free key at <a href="https://console.groq.com" target="_blank" style="color:var(--c-blue);">console.groq.com</a>.</div><div class="seo-field" style="margin-top:12px;"><label>Groq API Key</label><input type="password" name="api_key" class="seo-in" placeholder="gsk_…"></div>',
    };

    $('#intg-type').on('change',function(){
        var type=$(this).val();
        $('#intg-creds-wrap').html(credFields[type]||'');
        $('#intg-test-live-btn').toggle(!!type);
    });

    // Test live in modal
    $('#intg-test-live-btn').on('click',function(){
        var $btn=$(this).text('Testing…').prop('disabled',true);
        var data={action:'seo_dash_test_integration',nonce:seoDash.nonce,
            integration_id:$('#intg-id').val(), type:$('#intg-type').val()};
        $('#intg-creds-wrap [name]').each(function(){data[$(this).attr('name')]=$(this).val();});
        $.post(seoDash.ajax,data,function(r){
            $btn.text('🔍 Test').prop('disabled',false);
            var ok=r.success, msg=r.data&&r.data.message?r.data.message:(ok?'Connection OK':'Failed.');
            $('#intg-test-result')
                .css({background:ok?'rgba(63,185,80,.1)':'rgba(248,81,73,.1)',
                    color:ok?'var(--c-green)':'var(--c-red)',
                    border:'1px solid '+(ok?'rgba(63,185,80,.3)':'rgba(248,81,73,.3)')})
                .text((ok?'✅ ':'❌ ')+msg).show();
        });
    });

    // Save integration
    $('#seo-save-intg-btn').on('click',function(){
        var $btn=$(this).text('Saving…').prop('disabled',true);
        var data={action:'seo_dash_save_integration',nonce:seoDash.nonce,
            integration_id:$('#intg-id').val(),
            label:$('#intg-label').val(),
            type:$('#intg-type').val()
        };
        $('#intg-creds-wrap [name]').each(function(){data[$(this).attr('name')]=$(this).val();});
        $.post(seoDash.ajax,data,function(r){
            $btn.text('Save Integration').prop('disabled',false);
            if(r.success){seoToast('Integration saved.','ok'); setTimeout(function(){location.reload();},900);}
            else seoToast(r.data&&r.data.message?r.data.message:'Failed.','err');
        });
    });
})(jQuery);
});
</script>
