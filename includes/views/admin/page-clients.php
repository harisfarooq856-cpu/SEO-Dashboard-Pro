<?php if ( ! defined('ABSPATH') ) exit;
// $clients, $reports, $base passed from class-frontend-admin.php
$client_page_url = get_permalink( intval(get_option('seo_dash_client_page_id')) );
?>
<div class="seo-page">

    <div class="seo-page-hd">
        <div>
            <div class="seo-page-title">Clients</div>
            <p class="seo-page-subtitle">Manage client accounts and dashboard access</p>
        </div>
    </div>

    <!-- How clients work -->
    <div style="background:rgba(88,166,255,.08);border:1px solid rgba(88,166,255,.25);border-radius:var(--r);padding:14px 18px;margin-bottom:22px;display:flex;align-items:flex-start;gap:12px;">
        <span style="font-size:22px;margin-top:1px;flex-shrink:0;">&#8505;&#65039;</span>
        <div>
            <strong style="color:var(--c-blue);font-size:13px;">How Clients work</strong>
            <p style="margin:4px 0 0;font-size:13px;color:var(--c-text);line-height:1.6;">Add clients here with their credentials and permissions. To assign a client to a report and send them their dashboard email, go to the <strong>Integrations tab</strong> inside any SEO Report.</p>
        </div>
    </div>

    <!-- ── ADD NEW CLIENT FORM ──────────────────────────────────── -->
    <div class="seo-panel" style="margin-bottom:22px;">
        <div class="seo-panel-hd"><h2>+ Add New Client</h2></div>
        <div class="seo-panel-body">
            <div class="seo-grid-3" style="margin-bottom:14px;">
                <div class="seo-field">
                    <label>Full Name <span style="color:var(--c-red);">*</span></label>
                    <input type="text" id="scd-new-name" class="seo-in" placeholder="Jane Smith" autocomplete="nope">
                </div>
                <div class="seo-field">
                    <label>Email Address</label>
                    <input type="email" id="scd-new-email" class="seo-in" placeholder="jane@example.com" autocomplete="nope">
                </div>
                <div class="seo-field">
                    <label>Password <span style="color:var(--c-red);">*</span></label>
                    <div style="display:flex;gap:6px;">
                        <input type="text" id="scd-new-password" class="seo-in" placeholder="Min 8 characters" autocomplete="new-password" style="flex:1;">
                        <button type="button" id="scd-gen-pass" class="seo-btn seo-btn-ghost seo-btn-sm" title="Generate password" style="white-space:nowrap;">&#128273; Gen</button>
                    </div>
                </div>
            </div>

            <!-- Send Welcome Email -->
            <div style="background:rgba(63,185,80,.07);border:1px solid rgba(63,185,80,.25);border-radius:var(--r);padding:12px 16px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;">
                <input type="checkbox" id="scd-new-send-email" checked style="width:18px;height:18px;accent-color:var(--c-green);margin-top:2px;cursor:pointer;flex-shrink:0;">
                <div>
                    <label for="scd-new-send-email" style="font-size:13px;font-weight:700;color:var(--c-green);cursor:pointer;">&#128231; Send Welcome Email to Client</label>
                    <p style="margin:3px 0 0;font-size:12px;color:var(--c-muted);">When checked, the client will automatically receive an email with their dashboard URL and login credentials (username &amp; password) as soon as you add them.</p>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:12px;">
                <button type="button" id="scd-add-client-btn" class="seo-btn seo-btn-primary">+ Add Client</button>
                <div id="scd-add-result" style="font-size:13px;font-weight:600;"></div>
            </div>
        </div>
    </div>

    <!-- ── ALL CLIENTS TABLE ────────────────────────────────────── -->
    <div class="seo-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--c-border);flex-wrap:wrap;gap:10px;">
            <h2 style="font-size:14px;font-weight:700;color:var(--c-text);margin:0;">
                &#128203; All Clients
                <span id="scd-count-badge" class="seo-count-chip" style="margin-left:8px;"><?php echo intval($clients_total); ?></span>
            </h2>
            <div class="seo-search">
                <span class="seo-search-ico">&#128269;</span>
                <input type="text" id="scd-search" class="seo-in seo-in-sm" placeholder="Search clients…" style="padding-left:30px;width:240px;" autocomplete="nope">
            </div>
        </div>

        <?php if (empty($clients)) : ?>
        <div style="text-align:center;padding:48px 20px;color:var(--c-subtle);font-size:13px;">
            No clients yet. Add your first client above.
        </div>
        <?php else : ?>
        <div class="seo-table-wrap">
            <table class="seo-table" id="scd-clients-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client Name</th>
                        <th>Email</th>
                        <th>Password</th>
                        <th>Dashboard</th>
                        <th style="text-align:center;" title="What the client can edit in their Account Settings">&#9881;&#65039; Permissions</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="scd-clients-tbody">
                <?php foreach ($clients as $i => $c) :
                    $wp_user = !empty($c['wp_user_id']) ? get_userdata(intval($c['wp_user_id'])) : null;
                    $pass_display = '';
                    $perm_name  = !empty($c['allow_name_change']);
                    $perm_email = !empty($c['allow_email_change']);
                    $perm_pass  = !empty($c['allow_password_change']);
                    $perm_avatar = !empty($c['allow_avatar_change']);
                ?>
                <tr data-id="<?php echo intval($c['id']); ?>"
                    data-wp-user-id="<?php echo intval($c['wp_user_id'] ?? 0); ?>"
                    data-name="<?php echo esc_attr($c['name']); ?>"
                    data-search="<?php echo esc_attr(strtolower($c['name'].' '.($c['email']??''))); ?>">
                    <td style="color:var(--c-subtle);font-size:11px;font-weight:700;"><?php echo $i+1; ?></td>
                    <td>
                        <span class="scd-view-name" style="font-weight:600;"><?php echo esc_html($c['name']); ?></span>
                        <input type="text" class="seo-in seo-in-sm scd-edit-name" autocomplete="nope" value="<?php echo esc_attr($c['name']); ?>" style="display:none;min-width:140px;">
                    </td>
                    <td>
                        <span class="scd-view-email" style="font-size:13px;color:var(--c-muted);"><?php echo esc_html($c['email'] ?? ''); ?></span>
                        <input type="email" class="seo-in seo-in-sm scd-edit-email" autocomplete="nope" value="<?php echo esc_attr($c['email'] ?? ''); ?>" style="display:none;min-width:160px;">
                    </td>
                    <td>
                        <div class="scd-view-password" style="display:flex;align-items:center;gap:5px;">
                            <span class="scd-pwd-stars" style="font-family:monospace;font-size:13px;letter-spacing:2px;">—</span>
                        </div>
                        <input type="text" class="seo-in seo-in-sm scd-edit-password" autocomplete="new-password" value="" style="display:none;min-width:140px;" placeholder="Leave blank to keep current">
                    </td>
                    <td>
                        <?php if ($c['dashboard_url']) : ?>
                            <a href="<?php echo esc_url($c['dashboard_url']); ?>" target="_blank" style="display:inline-block;background:rgba(88,166,255,.1);color:var(--c-blue);font-size:11px;font-weight:700;padding:3px 9px;border-radius:10px;white-space:nowrap;">&#128203; View Portal</a>
                        <?php elseif ($client_page_url) : ?>
                            <a href="<?php echo esc_url($client_page_url); ?>" target="_blank" style="font-size:11px;color:var(--c-subtle);">View →</a>
                        <?php else : ?>
                            <span style="color:var(--c-subtle);font-size:12px;font-style:italic;">— not set —</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;min-width:160px;" class="scd-perms-td">
                        <label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--c-muted);margin:2px 4px;" title="Allow client to change their display name">
                            <input type="checkbox" class="scd-perm-name" data-id="<?php echo intval($c['id']); ?>" <?php checked($perm_name); ?> disabled style="accent-color:var(--c-primary);width:14px;height:14px;opacity:0.6;cursor:default;">
                            Name
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--c-muted);margin:2px 4px;" title="Allow client to change their email">
                            <input type="checkbox" class="scd-perm-email" data-id="<?php echo intval($c['id']); ?>" <?php checked($perm_email); ?> disabled style="accent-color:var(--c-primary);width:14px;height:14px;opacity:0.6;cursor:default;">
                            Email
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--c-muted);margin:2px 4px;" title="Allow client to change password">
                            <input type="checkbox" class="scd-perm-pass" data-id="<?php echo intval($c['id']); ?>" <?php checked($perm_pass); ?> disabled style="accent-color:var(--c-primary);width:14px;height:14px;opacity:0.6;cursor:default;">
                            Password
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--c-muted);margin:2px 4px;" title="Allow client to change their avatar photo">
                            <input type="checkbox" class="scd-perm-avatar" data-id="<?php echo intval($c['id']); ?>" <?php checked($perm_avatar); ?> disabled style="accent-color:var(--c-primary);width:14px;height:14px;opacity:0.6;cursor:default;">
                            Avatar
                        </label>
                    </td>
                    <td style="text-align:center;white-space:nowrap;">
                        <button type="button" class="scd-resend-welcome-btn seo-icon-btn" data-id="<?php echo esc_attr($c['id']); ?>" title="Send Welcome Mail" style="display:inline-flex;align-items:center;justify-content:center;background:var(--c-surf2);border:1px solid var(--c-border);color:var(--c-text);text-decoration:none;">&#128231;</button>
                        <button type="button" class="scd-edit-btn seo-btn seo-btn-ghost seo-btn-xs" title="Edit">&#9999; Edit</button>
                        <button type="button" class="scd-save-btn seo-btn seo-btn-primary seo-btn-xs" title="Save" style="display:none;">&#128190; Save</button>
                        <button type="button" class="scd-delete-btn seo-icon-btn seo-icon-btn-d" title="Delete">&#128465;</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Load More -->
        <?php if ( count($clients) < $clients_total ) : ?>
        <div id="scd-load-more-wrap" style="text-align:center;padding:16px 0;">
            <button id="scd-load-more-btn" class="seo-btn seo-btn-ghost"
                    data-offset="<?php echo count($clients); ?>"
                    data-total="<?php echo intval($clients_total); ?>"
                    style="min-width:160px;">
                ⬇ Load More Clients
                <span style="font-size:11px;color:var(--c-muted);margin-left:6px;">(<?php echo count($clients); ?> of <?php echo $clients_total; ?>)</span>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    /* ── Generate password ─────────────────────────────────── */
    $('#scd-gen-pass').on('click', function(){
        var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        var pass = ''; for (var i=0;i<12;i++) pass += chars[Math.floor(Math.random()*chars.length)];
        $('#scd-new-password').val(pass);
    });

    /* ── Add new client ───────────────────────────────────── */
    $('#scd-add-client-btn').on('click', function(){
        var name      = $('#scd-new-name').val().trim();
        var email     = $('#scd-new-email').val().trim();
        var password  = $('#scd-new-password').val().trim();
        var sendEmail = $('#scd-new-send-email').is(':checked') ? '1' : '0';
        var $result   = $('#scd-add-result');

        if (!name)     { flash($result,'Name is required.',false); return; }
        if (!password) { flash($result,'Password is required.',false); return; }
        if (sendEmail === '1' && !email) { flash($result,'Email required to send welcome email.',false); return; }

        var $btn = $(this).text('Adding…').prop('disabled',true);
        $.post(seoDash.ajax, {
            action:'seo_dash_save_client_v2', nonce:seoDash.nonce,
            name:name, email:email, password:password, send_email:sendEmail
        }, function(r){
            $btn.text('+ Add Client').prop('disabled',false);
            if (r.success) {
                var emailNote = r.data.email_sent ? ' &#128231; Welcome email sent!' : (r.data.email_failed ? ' &#9888; Added but email failed.' : '');
                flash($result, '&#9989; Client added!' + emailNote, true);
                $('#scd-new-name,#scd-new-email,#scd-new-password').val('');
                setTimeout(function(){ location.reload(); }, 1200);
            } else {
                flash($result, '&#10060; ' + (r.data && r.data.message ? r.data.message : 'Error'), false);
            }
        });
    });

    /* ── Search ───────────────────────────────────────────── */
    $('#scd-search').on('input', function(){
        var q = $(this).val().toLowerCase();
        $('#scd-clients-tbody tr').each(function(){
            $(this).toggle(!q || $(this).data('search').indexOf(q) > -1);
        });
    });

    /* ── Password reveal toggle ──────────────────────────── */
    $(document).on('click', '.scd-reveal-pwd', function(){
        var $span = $(this).siblings('.scd-pwd-stars');
        var pwd   = $span.data('pwd');
        if ($span.text() === '••••••••') {
            $span.text(pwd);
            $(this).text('🙈');
        } else {
            $span.text('••••••••');
            $(this).text('👁');
        }
    });

    /* ── Row: edit / save / delete ───────────────────────── */
    function bindRow(tr) {
        var $tr = $(tr);

        $tr.find('.scd-edit-btn').on('click', function(){
            $tr.find('.scd-view-name,.scd-view-email,.scd-view-password').hide();
            $tr.find('.scd-edit-name,.scd-edit-email,.scd-edit-password').show();
            $tr.find('.scd-perms-td input[type=checkbox]').prop('disabled', false).css({'opacity':'1','cursor':'pointer'});
            $tr.find('.scd-perms-td label').css('cursor','pointer');
            $(this).hide();
            $tr.find('.scd-save-btn').show();
        });

        $tr.find('.scd-save-btn').on('click', function(){
            var id       = $tr.data('id');
            var name     = $tr.find('.scd-edit-name').val().trim();
            var email    = $tr.find('.scd-edit-email').val().trim();
            var password = $tr.find('.scd-edit-password').val().trim();
            if (!name) { alert('Name is required.'); return; }
            var $btn = $(this).text('Saving…').prop('disabled',true);
            $.post(seoDash.ajax,{action:'seo_dash_save_client_v2',nonce:seoDash.nonce,client_id:id,name:name,email:email,password:password}, function(r){
                $btn.text('Save').prop('disabled',false);
                if (r.success){
                    $tr.find('.scd-view-name').text(name).show();
                    $tr.find('.scd-view-email').text(email).show();
                    var retPass = (r.data && r.data.client && r.data.client.password) ? r.data.client.password : password;
                    if (retPass) {
                        $tr.find('.scd-pwd-stars').data('pwd', retPass).text('••••••••');
                        $tr.find('.scd-reveal-pwd').text('👁');
                    }
                    $tr.find('.scd-view-password').show();
                    $tr.find('.scd-edit-name,.scd-edit-email,.scd-edit-password').hide();
                    $tr.find('.scd-perms-td input[type=checkbox]').prop('disabled', true).css({'opacity':'0.6','cursor':'default'});
                    $tr.find('.scd-perms-td label').css('cursor','default');
                    $tr.find('.scd-save-btn').hide();
                    $tr.find('.scd-edit-btn').show();
                    seoToast('Client saved.','ok');
                } else { seoToast(r.data&&r.data.message?r.data.message:'Error saving.','err'); }
            });
        });

        $tr.find('.scd-delete-btn').on('click', function(){
            var id = $tr.data('id'), name = $tr.data('name'), wpUid = $tr.data('wpUserId');
            var msg = 'Delete client "'+name+'"?';
            if (wpUid) msg += '\n\nThis will also delete their WordPress login account.';
            msg += '\n\nThis cannot be undone.';
            if (!confirm(msg)) return;
            $(this).prop('disabled',true);
            $.post(seoDash.ajax,{action:'seo_dash_delete_client',nonce:seoDash.nonce,client_id:id}, function(r){
                if (r.success){
                    $tr.fadeOut(300, function(){ $(this).remove(); });
                    var $b = $('#scd-count-badge'); $b.text(Math.max(0, parseInt($b.text())-1));
                    seoToast('Client deleted.','ok');
                } else { seoToast('Failed.','err'); }
            });
        });

        $tr.find('.scd-resend-welcome-btn').on('click', function(){
            var id = $tr.data('id');
            var $btn = $(this);
            $btn.css('opacity','0.5').prop('disabled',true);
            $.post(seoDash.ajax, { action: 'seo_dash_resend_client_welcome_mail', nonce: seoDash.nonce, client_id: id }, function(r) {
                if (r.success) seoToast('Welcome email sent successfully!', 'ok');
                else seoToast('Failed to send welcome email.', 'err');
                $btn.css('opacity','1').prop('disabled',false);
            });
        });
    }

    $('#scd-clients-tbody tr').each(function(){ bindRow(this); });

    /* ── Permission toggles (only active in edit mode) ──────── */
    $('#scd-clients-tbody').on('change', '.scd-perm-name,.scd-perm-email,.scd-perm-pass,.scd-perm-avatar', function(){
        var $chk = $(this);
        if ($chk.prop('disabled')) return;
        var id  = $chk.data('id');
        var $tr = $chk.closest('tr');
        var pName   = $tr.find('.scd-perm-name').is(':checked')   ? '1':'0';
        var pEmail  = $tr.find('.scd-perm-email').is(':checked')  ? '1':'0';
        var pPass   = $tr.find('.scd-perm-pass').is(':checked')   ? '1':'0';
        var pAvatar = $tr.find('.scd-perm-avatar').is(':checked') ? '1':'0';
        $.post(seoDash.ajax,{
            action:'seo_dash_save_client_perms',nonce:seoDash.nonce,
            client_id:id,allow_name_change:pName,allow_email_change:pEmail,allow_password_change:pPass,allow_avatar_change:pAvatar
        }, function(r){
            if (!r.success){ $chk.prop('checked', !$chk.is(':checked')); seoToast('Could not save permissions.','err'); }
            else { seoToast('Permissions saved.','ok'); }
        });
    });

    function esc(s){ return $('<div>').text(s == null ? '' : s).html(); }

    function buildClientRow(c, idx) {
        var pwd = c.pass_display || '';
        var pwdCell = pwd
            ? '<span class="scd-pwd-stars" data-pwd="'+esc(pwd)+'" style="font-family:monospace;font-size:13px;letter-spacing:2px;">••••••••</span>'
              +'<button type="button" class="scd-reveal-pwd seo-btn seo-btn-ghost seo-btn-xs" title="Show/hide password" style="padding:1px 5px;font-size:11px;">👁</button>'
            : '<span class="scd-pwd-stars" data-pwd="" style="font-family:monospace;font-size:13px;letter-spacing:2px;">—</span>';

        var dashCell;
        if (c.has_dashboard) {
            dashCell = '<a href="'+esc(c.client_link)+'" target="_blank" style="display:inline-block;background:rgba(88,166,255,.1);color:var(--c-blue);font-size:11px;font-weight:700;padding:3px 9px;border-radius:10px;white-space:nowrap;">&#128203; View Portal</a>';
        } else if (c.client_link) {
            dashCell = '<a href="'+esc(c.client_link)+'" target="_blank" style="font-size:11px;color:var(--c-subtle);">View →</a>';
        } else {
            dashCell = '<span style="color:var(--c-subtle);font-size:12px;font-style:italic;">— not set —</span>';
        }

        var search = (c.name + ' ' + (c.email || '')).toLowerCase();

        return '<tr data-id="'+c.id+'" data-wp-user-id="'+(c.wp_user_id||0)+'" data-name="'+esc(c.name)+'" data-search="'+esc(search)+'">'
            +'<td style="color:var(--c-subtle);font-size:11px;font-weight:700;">'+idx+'</td>'
            +'<td>'
                +'<span class="scd-view-name" style="font-weight:600;">'+esc(c.name)+'</span>'
                +'<input type="text" class="seo-in seo-in-sm scd-edit-name" autocomplete="nope" value="'+esc(c.name)+'" style="display:none;min-width:140px;">'
            +'</td>'
            +'<td>'
                +'<span class="scd-view-email" style="font-size:13px;color:var(--c-muted);">'+esc(c.email)+'</span>'
                +'<input type="email" class="seo-in seo-in-sm scd-edit-email" autocomplete="nope" value="'+esc(c.email)+'" style="display:none;min-width:160px;">'
            +'</td>'
            +'<td>'
                +'<div class="scd-view-password" style="display:flex;align-items:center;gap:5px;">'+pwdCell+'</div>'
                +'<input type="text" class="seo-in seo-in-sm scd-edit-password" autocomplete="new-password" value="'+esc(pwd)+'" style="display:none;min-width:140px;" placeholder="Leave blank to keep current">'
            +'</td>'
            +'<td>'+dashCell+'</td>'
            +'<td style="text-align:center;min-width:160px;" class="scd-perms-td">'
                +'<label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--c-muted);margin:2px 4px;" title="Allow client to change their display name">'
                    +'<input type="checkbox" class="scd-perm-name" data-id="'+c.id+'" '+(c.allow_name_change ? 'checked ' : '')+'disabled style="accent-color:var(--c-primary);width:14px;height:14px;opacity:0.6;cursor:default;"> Name'
                +'</label>'
                +'<label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--c-muted);margin:2px 4px;" title="Allow client to change their email">'
                    +'<input type="checkbox" class="scd-perm-email" data-id="'+c.id+'" '+(c.allow_email_change ? 'checked ' : '')+'disabled style="accent-color:var(--c-primary);width:14px;height:14px;opacity:0.6;cursor:default;"> Email'
                +'</label>'
                +'<label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--c-muted);margin:2px 4px;" title="Allow client to change password">'
                    +'<input type="checkbox" class="scd-perm-pass" data-id="'+c.id+'" '+(c.allow_password_change ? 'checked ' : '')+'disabled style="accent-color:var(--c-primary);width:14px;height:14px;opacity:0.6;cursor:default;"> Password'
                +'</label>'
                +'<label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--c-muted);margin:2px 4px;" title="Allow client to change their avatar photo">'
                    +'<input type="checkbox" class="scd-perm-avatar" data-id="'+c.id+'" '+(c.allow_avatar_change ? 'checked ' : '')+'disabled style="accent-color:var(--c-primary);width:14px;height:14px;opacity:0.6;cursor:default;"> Avatar'
                +'</label>'
            +'</td>'
            +'<td style="text-align:center;white-space:nowrap;">'
                +'<button type="button" class="scd-resend-welcome-btn seo-icon-btn" data-id="'+c.id+'" title="Send Welcome Mail" style="display:inline-flex;align-items:center;justify-content:center;background:var(--c-surf2);border:1px solid var(--c-border);color:var(--c-text);text-decoration:none;">&#128231;</button>'
                +'<button type="button" class="scd-edit-btn seo-btn seo-btn-ghost seo-btn-xs" title="Edit">&#9999; Edit</button>'
                +'<button type="button" class="scd-save-btn seo-btn seo-btn-primary seo-btn-xs" title="Save" style="display:none;">&#128190; Save</button>'
                +'<button type="button" class="scd-delete-btn seo-icon-btn seo-icon-btn-d" title="Delete">&#128465;</button>'
            +'</td>'
            +'</tr>';
    }

    /* ── Load More clients ───────────────────────────────── */
    $('#scd-load-more-btn').on('click', function(){
        var $btn   = $(this);
        var offset = parseInt($btn.data('offset'));
        var total  = parseInt($btn.data('total'));
        $btn.prop('disabled', true).text('Loading…');

        $.post(seoDash.ajax, {
            action: 'seo_dash_get_clients_paged',
            nonce:  seoDash.nonce,
            limit:  5,
            offset: offset
        }, function(r){
            if (!r.success) { seoToast('Failed to load clients.','err'); $btn.prop('disabled',false).text('⬇ Load More Clients'); return; }
            var rows   = r.data.clients || [];
            var newOff = r.data.offset  || (offset + rows.length);
            var rowCount = $('#scd-clients-tbody tr').length;
            $.each(rows, function(i, c){
                var $row = $(buildClientRow(c, rowCount + i + 1));
                $('#scd-clients-tbody').append($row);
                bindRow($row[0]);
            });
            $btn.data('offset', newOff);
            if (newOff >= total) {
                $('#scd-load-more-wrap').hide();
            } else {
                $btn.prop('disabled',false)
                    .html('⬇ Load More Clients <span style="font-size:11px;color:var(--c-muted);margin-left:6px;">('+newOff+' of '+total+')</span>');
            }
        }).fail(function(){ seoToast('Network error.','err'); $btn.prop('disabled',false).text('⬇ Load More Clients'); });
    });

    function flash(el, msg, ok){
        el.css('color', ok ? 'var(--c-green)' : 'var(--c-red)').html(msg);
        setTimeout(function(){ el.text(''); }, 4000);
    }

})(jQuery);
});
</script>
