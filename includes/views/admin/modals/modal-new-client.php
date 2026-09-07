<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="seo-modal" id="seo-modal-new-client">
    <div class="seo-modal-bg" data-close="seo-modal-new-client"></div>
    <div class="seo-modal-box" style="max-width:560px;">
        <div class="seo-modal-hd">
            <h3>Create Client</h3>
            <button class="seo-modal-x" data-close="seo-modal-new-client">✕</button>
        </div>
        <div class="seo-modal-bd">
            <input type="hidden" id="seo-client-id" value="">

            <!-- Inner tabs -->
            <div style="display:flex;gap:0;border-bottom:2px solid var(--c-border);margin-bottom:20px;">
                <button class="seo-modal-tab-btn" data-mtab="details" style="background:none;border:none;padding:8px 16px;font-size:13px;font-weight:700;color:var(--c-primary);border-bottom:3px solid var(--c-primary);margin-bottom:-2px;cursor:pointer;font-family:var(--font);">Details</button>
                <button class="seo-modal-tab-btn" data-mtab="wp" style="background:none;border:none;padding:8px 16px;font-size:13px;font-weight:600;color:var(--c-muted);border-bottom:3px solid transparent;margin-bottom:-2px;cursor:pointer;font-family:var(--font);">WP Account</button>
                <button class="seo-modal-tab-btn" data-mtab="reports" style="background:none;border:none;padding:8px 16px;font-size:13px;font-weight:600;color:var(--c-muted);border-bottom:3px solid transparent;margin-bottom:-2px;cursor:pointer;font-family:var(--font);">Assign Report</button>
            </div>

            <!-- Details -->
            <div id="client-mtab-details">
                <div class="seo-grid-2">
                    <div class="seo-field"><label>First Name</label><input type="text" id="seo-client-fname" class="seo-in" placeholder="John" autocomplete="nope"></div>
                    <div class="seo-field"><label>Last Name</label><input type="text" id="seo-client-lname" class="seo-in" placeholder="Smith" autocomplete="nope"></div>
                </div>
                <div class="seo-field" style="margin-top:12px;"><label>Email <span style="color:var(--c-red);">*</span></label><input type="email" id="seo-client-email" class="seo-in" placeholder="john@example.com" autocomplete="nope"></div>
                <div class="seo-field" style="margin-top:12px;"><label>Company</label><input type="text" id="seo-client-company" class="seo-in" placeholder="Acme Corp" autocomplete="nope"></div>
                <div class="seo-field" style="margin-top:12px;"><label>Phone</label><input type="text" id="seo-client-phone" class="seo-in" placeholder="+1 555 000 0000" autocomplete="nope"></div>
                <div class="seo-field" style="margin-top:12px;"><label>Internal Notes</label><textarea id="seo-client-notes" class="seo-in" rows="2"></textarea></div>
            </div>

            <!-- WP Account -->
            <div id="client-mtab-wp" style="display:none;">
                <div class="seo-alert seo-alert-info" style="margin-bottom:16px;">Creates a WordPress user with the <strong>seo_client</strong> role and links them to their dashboard page.</div>
                <div class="seo-field"><label>Password <span style="font-weight:400;color:var(--c-subtle);">(blank = auto-generate)</span></label><input type="password" id="seo-client-pw" class="seo-in" autocomplete="new-password" placeholder="Leave blank to auto-generate"></div>
                <div class="seo-field" style="margin-top:12px;"><label>Assign to Report (optional)</label>
                    <select id="seo-client-report-id" class="seo-in">
                        <option value="">— Select report —</option>
                        <?php foreach ($reports as $r) : ?><option value="<?php echo intval($r['id']); ?>"><?php echo esc_html($r['title']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div id="seo-new-user-result" style="display:none;margin-top:14px;background:rgba(63,185,80,.08);border:1px solid rgba(63,185,80,.25);border-radius:var(--r);padding:14px;font-size:13px;color:var(--c-text);line-height:1.6;"></div>
            </div>

            <!-- Assign Report -->
            <div id="client-mtab-reports" style="display:none;">
                <p style="font-size:13px;color:var(--c-muted);margin-bottom:12px;">Select reports to assign after saving the client.</p>
                <?php foreach ($reports as $r) : ?>
                <label style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--c-border);cursor:pointer;">
                    <input type="checkbox" class="seo-client-report-check" value="<?php echo intval($r['id']); ?>" style="width:16px;height:16px;accent-color:var(--c-primary);">
                    <span style="font-size:13px;font-weight:600;"><?php echo esc_html($r['title']); ?></span>
                </label>
                <?php endforeach; ?>
                <?php if (empty($reports)) : ?><p style="color:var(--c-subtle);font-size:13px;">No reports yet.</p><?php endif; ?>
            </div>
        </div>
        <div class="seo-modal-ft">
            <span id="seo-client-modal-msg" style="font-size:12px;flex:1;color:var(--c-red);"></span>
            <button class="seo-btn seo-btn-ghost" data-close="seo-modal-new-client">Cancel</button>
            <button class="seo-btn seo-btn-primary" id="seo-save-client-btn">Save Client</button>
            <button class="seo-btn seo-btn-primary" id="seo-create-user-btn" style="display:none;">Create WP User</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    // Inner tab switching
    $(document).on('click','.seo-modal-tab-btn',function(){
        var tab=$(this).data('mtab');
        $('.seo-modal-tab-btn').css({'border-bottom-color':'transparent','color':'var(--c-muted)','font-weight':600});
        $(this).css({'border-bottom-color':'var(--c-primary)','color':'var(--c-primary)','font-weight':700});
        $('[id^="client-mtab-"]').hide();
        $('#client-mtab-'+tab).show();
        $('#seo-save-client-btn').toggle(tab!=='wp');
        $('#seo-create-user-btn').toggle(tab==='wp');
    });

    // Reset modal on open
    $('#seo-modal-new-client').on('seo-modal-open',function(){
        $('#seo-client-id,#seo-client-fname,#seo-client-lname,#seo-client-email,#seo-client-company,#seo-client-phone,#seo-client-notes,#seo-client-pw').val('');
        $('#seo-client-modal-msg').text('');
        $('#seo-new-user-result').hide();
        $('.seo-client-report-check').prop('checked',false);
        $('#seo-modal-new-client .seo-modal-hd h3').text('Create Client');
        $('[data-mtab="details"]').trigger('click');
    });

    // Save client details
    $('#seo-save-client-btn').on('click',function(){
        var fname=$('#seo-client-fname').val().trim(), lname=$('#seo-client-lname').val().trim();
        var name=(fname+' '+lname).trim()||$('#seo-client-email').val();
        if(!name){$('#seo-client-modal-msg').text('Name or email required.'); return;}
        var $btn=$(this).text('Saving…').prop('disabled',true);
        $.post(seoDash.ajax,{action:'seo_dash_save_client',nonce:seoDash.nonce,
            client_id:$('#seo-client-id').val(), name:name,
            email:$('#seo-client-email').val(), company:$('#seo-client-company').val(),
            phone:$('#seo-client-phone').val(), notes:$('#seo-client-notes').val()
        },function(r){
            if(r.success){
                var cid=r.data.client_id;
                // FIX: previously each checked report fired its own
                // fire-and-forget $.post and the page reloaded after a
                // fixed 900ms timer regardless of whether they'd finished.
                // With 2+ reports checked, the slower one(s) could lose the
                // race and never get saved before reload. Now we collect
                // every assign request and only reload once ALL of them
                // have actually completed.
                var assignRequests = [];
                $('.seo-client-report-check:checked').each(function(){
                    assignRequests.push($.post(seoDash.ajax,{action:'seo_dash_assign_client',nonce:seoDash.nonce,report_id:$(this).val(),client_id:cid}));
                });
                $.when.apply($, assignRequests).always(function(){
                    $btn.text('Save Client').prop('disabled',false);
                    seoToast('Client saved.','ok');
                    location.reload();
                });
            } else {
                $btn.text('Save Client').prop('disabled',false);
                $('#seo-client-modal-msg').text(r.data&&r.data.message?r.data.message:'Error saving.');
            }
        });
    });

    // Create WP user
    $('#seo-create-user-btn').on('click',function(){
        var fname=$('#seo-client-fname').val().trim(), lname=$('#seo-client-lname').val().trim();
        var $btn=$(this).text('Creating…').prop('disabled',true);
        $.post(seoDash.ajax,{action:'seo_dash_create_client_user',nonce:seoDash.nonce,
            first_name:fname, last_name:lname,
            email:$('#seo-client-email').val(), company:$('#seo-client-company').val(),
            password:$('#seo-client-pw').val(), report_id:$('#seo-client-report-id').val()
        },function(r){
            $btn.text('Create WP User').prop('disabled',false);
            if(r.success){
                var d=r.data;
                $('#seo-new-user-result').html(
                    '<strong>✅ Account created!</strong><br>'+
                    'Username: <code>'+d.username+'</code><br>'+
                    (d.password?'Password: <code>'+d.password+'</code><br>':'')+
                    'Dashboard: <a href="'+d.dashboard_url+'" target="_blank" style="color:var(--c-blue);">'+d.dashboard_url+'</a>'
                ).show();
                seoToast('WP user created.','ok');
            } else {
                $('#seo-client-modal-msg').text(r.data&&r.data.message?r.data.message:'Failed.');
            }
        });
    });
})(jQuery);
});
</script>
