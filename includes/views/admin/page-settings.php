<?php if ( ! defined('ABSPATH') ) exit;
$brand_name    = SEO_Dash_Database::get_setting('brand_name', get_bloginfo('name'));
$agency_url    = SEO_Dash_Database::get_setting('agency_url', '');
$support_email = SEO_Dash_Database::get_setting('support_email', '');
$footer_text   = SEO_Dash_Database::get_setting('footer_text', '');
$notify_emails = SEO_Dash_Database::get_setting('admin_notify_emails', '');
$brand_logo      = SEO_Dash_Database::get_setting('brand_logo', '');
$brand_logo_dark = SEO_Dash_Database::get_setting('brand_logo_dark', '');
$admin_id      = intval(get_option('seo_dash_admin_page_id'));


$chatbot_model   = SEO_Dash_Database::get_setting('chatbot_model', 'groq');

// Gmail OAuth status
$oauth_connected   = seo_dash_oauth_is_connected();
$oauth_email       = seo_dash_oauth_connected_email();

// Email / SMTP configuration (legacy fallback when OAuth not connected)
$smtp_mode       = SEO_Dash_Database::get_setting('smtp_mode', 'gmail');
if ( ! in_array( $smtp_mode, [ 'gmail', 'brevo', 'other', 'gmail_oauth' ], true ) ) $smtp_mode = 'gmail';
$smtp_host       = SEO_Dash_Database::get_setting('smtp_host', '');
$smtp_port       = intval(SEO_Dash_Database::get_setting('smtp_port', 587)) ?: 587;
$smtp_username   = SEO_Dash_Database::get_setting('smtp_username', '');
$smtp_from_name  = SEO_Dash_Database::get_setting('smtp_from_name', '');
$smtp_from_email = SEO_Dash_Database::get_setting('smtp_from_email', '');
$smtp_has_pass   = (bool) SEO_Dash_Database::get_setting('smtp_password', '');
?>
<div class="seo-page">
    <div class="seo-page-hd">
        <div>
            <h1 class="seo-page-title">⚙️ Settings</h1>
            <p class="seo-page-subtitle">Configure your SEO Dashboard plugin</p>
        </div>
        <button class="seo-btn seo-btn-primary" id="seo-settings-save-btn">💾 Save Settings</button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

        <!-- Left column: Branding + Pages -->
        <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Branding -->
        <div class="seo-panel">
            <div class="seo-panel-hd"><h2>🏢 Agency Branding</h2></div>
            <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:14px;">
                <div class="seo-field">
                    <label>Agency / Brand Name</label>
                    <input type="text" id="s-brand-name" class="seo-in" autocomplete="nope" value="<?php echo esc_attr($brand_name); ?>" placeholder="Your Agency Name">
                    <span class="seo-field-hint">Shown in dashboard header and client emails.</span>
                </div>
                <div class="seo-field">
                    <label>Agency Website URL</label>
                    <input type="url" id="s-agency-url" class="seo-in" value="<?php echo esc_attr($agency_url); ?>" placeholder="https://youragency.com">
                </div>
                <div class="seo-field">
                    <label>Support Email</label>
                    <input type="email" id="s-support-email" class="seo-in" autocomplete="nope" value="<?php echo esc_attr($support_email); ?>" placeholder="support@youragency.com">
                </div>
                <div class="seo-field">
                    <label>Footer Text <span style="font-weight:400;color:var(--c-subtle);">(leave blank for default)</span></label>
                    <input type="text" id="s-footer-text" class="seo-in" autocomplete="nope" value="<?php echo esc_attr($footer_text); ?>" placeholder="Powered by [Brand]">
                </div>
                <div class="seo-field">
                    <label>Logo — Light Mode <span style="font-weight:400;color:var(--c-muted);">(shown on white/light background)</span></label>
                    <input type="url" id="s-brand-logo" class="seo-in" value="<?php echo esc_attr($brand_logo); ?>" placeholder="https://…/logo-dark.png">
                    <?php if ($brand_logo) : ?>
                        <img src="<?php echo esc_url($brand_logo); ?>" alt="Light Logo" style="height:32px;margin-top:6px;object-fit:contain;background:#fff;padding:4px;border-radius:4px;border:1px solid var(--c-border);">
                    <?php endif; ?>
                    <span class="seo-field-hint">Use your dark-colored logo here — visible on light backgrounds</span>
                </div>
                <div class="seo-field">
                    <label>Logo — Dark Mode <span style="font-weight:400;color:var(--c-muted);">(shown on dark background)</span></label>
                    <input type="url" id="s-brand-logo-dark" class="seo-in" value="<?php echo esc_attr($brand_logo_dark); ?>" placeholder="https://…/logo-white.png">
                    <?php if ($brand_logo_dark) : ?>
                        <img src="<?php echo esc_url($brand_logo_dark); ?>" alt="Dark Logo" style="height:32px;margin-top:6px;object-fit:contain;background:#1e1e2e;padding:4px;border-radius:4px;border:1px solid var(--c-border);">
                    <?php endif; ?>
                    <span class="seo-field-hint">Use your white/light-colored logo here — visible on dark backgrounds</span>
                </div>
            </div>
        </div><!-- /Branding panel -->

        <!-- Info -->
        <div class="seo-panel">
            <div class="seo-panel-hd"><h2>ℹ️ System Info</h2></div>
            <div class="seo-panel-body">
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <?php
                    $rows = [
                        ['Plugin Version', SEO_DASH_VERSION],
                        ['PHP Version', PHP_VERSION],
                        ['Total Reports', count(SEO_Dash_Database::get_reports())],
                        ['Total Clients', count(SEO_Dash_Database::get_clients())],
                        ['OpenSSL', function_exists('openssl_encrypt') ? '✅ Available' : '❌ Not available'],
                    ];
                    foreach ($rows as [$k,$v]) :
                    ?>
                    <tr style="border-bottom:1px solid var(--c-border);">
                        <td style="padding:8px 0;color:var(--c-muted);font-weight:600;"><?php echo esc_html($k); ?></td>
                        <td style="padding:8px 0;"><?php echo esc_html($v); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div><!-- /System Info panel -->

        <!-- Pages -->
        <div class="seo-panel">
            <div class="seo-panel-hd"><h2>🔔 Admin Notification Emails</h2></div>
            <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:14px;">
                <div class="seo-field">
                    <input type="text" id="s-notify-emails" class="seo-in" autocomplete="nope" value="<?php echo esc_attr($notify_emails); ?>" placeholder="admin@agency.com, manager@agency.com">
                    <span class="seo-field-hint">Comma-separated. Receive plugin admin alerts.</span>
                </div>
            </div>
        </div>

        </div><!-- /left column: Branding + Pages -->

        <!-- Right column: AI & Chatbot -->
        <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- AI & Chatbot -->
        <div class="seo-panel">
            <div class="seo-panel-hd"><h2>🤖 AI & Chatbot Settings</h2></div>
            <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:14px;">
                <style>
                .s-key-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
                .s-key-row input { flex:1; min-width:0; }
                .s-saved-badge { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:#10b981; background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.25); padding:3px 9px; border-radius:20px; white-space:nowrap; flex-shrink:0; }
                .s-provider-row { display:flex; flex-direction:row; gap:8px; flex-wrap:wrap; }
                @media(max-width:600px){ .s-provider-row { flex-direction:column; } }
                </style>
                <!-- Priority note -->
                <?php
                $active_provider   = SEO_Dash_Database::get_setting('active_provider', '');
                $has_deepseek_g    = !empty(SEO_Dash_Database::get_setting('deepseek_api_key'));
                $has_gemini_g      = !empty(SEO_Dash_Database::get_setting('gemini_api_key'));
                $has_cerebras_g    = !empty(SEO_Dash_Database::get_setting('cerebras_api_key'));
                $has_groq_g        = !empty(SEO_Dash_Database::get_setting('groq_api_key'));
                ?>
                <div style="border:1px solid var(--c-border);border-radius:10px;padding:16px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--c-muted);margin-bottom:4px;">Global Active Provider</div>
                    <div style="font-size:12px;color:var(--c-muted);margin-bottom:14px;">Select which AI provider is used for all reports by default.</div>
                    <div class="s-provider-row" id="s-provider-cards">
                        <?php
                        $providers = [
                            'deepseek' => ['label'=>'DeepSeek', 'badge'=>'#8b5cf6','badge_label'=>'DEEPSEEK'],
                            'gemini'   => ['label'=>'Gemini',   'badge'=>'#10b981','badge_label'=>'GOOGLE'],
                            'cerebras' => ['label'=>'Cerebras', 'badge'=>'#06b6d4','badge_label'=>'CEREBRAS'],
                            'groq'     => ['label'=>'Groq',     'badge'=>'#f97316','badge_label'=>'GROQ'],
                        ];
                        $has_map = ['deepseek'=>$has_deepseek_g,'gemini'=>$has_gemini_g,'cerebras'=>$has_cerebras_g,'groq'=>$has_groq_g];
                        foreach ($providers as $pval => $pd):
                            $connected = $has_map[$pval];
                            $is_sel    = ($active_provider === $pval);
                        ?>
                        <label style="display:flex;align-items:center;gap:6px;padding:9px 12px;border:2px solid <?php echo $is_sel ? 'var(--c-primary)' : 'var(--c-border)'; ?>;border-radius:8px;background:<?php echo $is_sel ? 'rgba(99,102,241,.06)' : 'var(--c-surf)'; ?>;<?php echo !$connected ? 'opacity:.5;cursor:not-allowed;' : 'cursor:pointer;'; ?>;transition:all .15s;flex:1;min-width:0;overflow:hidden;"
                               id="s-prov-label-<?php echo $pval; ?>">
                            <input type="radio" name="s_active_provider" value="<?php echo esc_attr($pval); ?>" id="s-prov-<?php echo $pval; ?>" <?php checked($is_sel); ?> <?php echo !$connected ? 'disabled' : ''; ?> style="accent-color:var(--c-primary);width:14px;height:14px;margin:0;flex-shrink:0;">
                            <div style="display:flex;flex-direction:column;gap:2px;min-width:0;overflow:hidden;">
                                <div style="display:flex;align-items:center;gap:5px;flex-wrap:nowrap;">
                                    <span style="font-size:13px;font-weight:600;color:var(--c-text);white-space:nowrap;"><?php echo $pd['label']; ?></span>
                                    <span style="background:<?php echo $pd['badge']; ?>;color:#fff;font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;letter-spacing:.3px;white-space:nowrap;flex-shrink:0;"><?php echo $pd['badge_label']; ?></span>
                                </div>
                                <?php if (!$connected): ?>
                                    <span style="font-size:10px;color:var(--c-muted);white-space:nowrap;">Not connected</span>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── DEEPSEEK ── -->
                <div style="border:1px solid var(--c-border);border-radius:10px;padding:14px;display:flex;flex-direction:column;gap:12px;">
                    <div style="font-size:13px;font-weight:700;color:var(--c-text);display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <span style="background:#8b5cf6;color:#fff;font-size:10px;padding:2px 7px;border-radius:20px;font-weight:700;">DEEPSEEK</span>
                        API Key &amp; Model
                        <?php if ($has_deepseek_g): ?><span class="s-saved-badge">✓ Key saved</span><?php endif; ?>
                    </div>
                    <div class="seo-field" style="margin:0;">
                        <label>DeepSeek API Key</label>
                        <div class="s-key-row">
                            <?php $ds_has = $has_deepseek_g; ?>
                            <?php if ($ds_has): ?>
                            <div style="flex:1;position:relative;">
                                <input type="password" id="s-deepseek-key" class="seo-in" autocomplete="new-password" placeholder="••••••••••••••••••••••••  (saved — enter new key to update)" style="width:100%;color:var(--c-muted);">
                            </div>
                            <?php else: ?>
                            <input type="password" id="s-deepseek-key" class="seo-in" autocomplete="new-password" placeholder="sk-..." style="flex:1;">
                            <?php endif; ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" onclick="var f=document.getElementById('s-deepseek-key');f.type=f.type==='password'?'text':'password';return false;">Show</button>
                        </div>
                    </div>
                    <div class="seo-field" style="margin:0;">
                        <label>DeepSeek Model</label>
                        <?php $deepseek_model = SEO_Dash_Database::get_setting('deepseek_model', 'deepseek-v4-pro'); ?>
                        <select id="s-deepseek-model" class="seo-in">
                            <option value="deepseek-v4-flash" <?php selected($deepseek_model, 'deepseek-v4-flash'); ?>>DeepSeek V4 Flash</option>
                            <option value="deepseek-v4-pro"   <?php selected($deepseek_model, 'deepseek-v4-pro'); ?>>DeepSeek V4 Pro</option>
                        </select>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-test-deepseek-btn">Test DeepSeek Key</button>
                        <?php if ($has_deepseek_g): ?>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-remove-deepseek-btn" style="color:var(--c-red,#ef4444);border-color:var(--c-red,#ef4444);" onclick="return false;">✕ Remove API Key</button>
                        <?php endif; ?>
                        <span id="seo-deepseek-test-result" style="font-size:12px;"></span>
                    </div>
                </div>

                <!-- ── GROQ ── -->
                <div style="border:1px solid var(--c-border);border-radius:10px;padding:14px;display:flex;flex-direction:column;gap:12px;">
                    <div style="font-size:13px;font-weight:700;color:var(--c-text);display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <span style="background:#f97316;color:#fff;font-size:10px;padding:2px 7px;border-radius:20px;font-weight:700;">GROQ</span>
                        API Key &amp; Model
                        <?php if ($has_groq_g): ?><span class="s-saved-badge">✓ Key saved</span><?php endif; ?>
                    </div>
                    <div class="seo-field" style="margin:0;">
                        <label>Groq API Key</label>
                        <div class="s-key-row">
                            <?php $groq_has = $has_groq_g; ?>
                            <?php if ($groq_has): ?>
                            <div style="flex:1;position:relative;">
                                <input type="password" id="s-groq-key" class="seo-in" autocomplete="new-password" placeholder="••••••••••••••••••••••••  (saved — enter new key to update)" style="width:100%;color:var(--c-muted);">
                            </div>
                            <?php else: ?>
                            <input type="password" id="s-groq-key" class="seo-in" autocomplete="new-password" placeholder="gsk_..." style="flex:1;">
                            <?php endif; ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" onclick="var f=document.getElementById('s-groq-key');f.type=f.type==='password'?'text':'password';return false;">Show</button>
                        </div>
                    </div>
                    <div class="seo-field" style="margin:0;">
                        <label>Groq Model</label>
                        <?php $groq_model = SEO_Dash_Database::get_setting('groq_model', 'meta-llama/llama-4-scout-17b-16e-instruct'); ?>
                        <select id="s-groq-model" class="seo-in">
                            <option value="meta-llama/llama-4-scout-17b-16e-instruct" <?php selected($groq_model, 'meta-llama/llama-4-scout-17b-16e-instruct'); ?>>Llama 4 Scout 17B — Best Free</option>
                            <option value="llama-3.3-70b-versatile"                   <?php selected($groq_model, 'llama-3.3-70b-versatile'); ?>>Llama 3.3 70B Versatile</option>
                            <option value="groq/compound"                             <?php selected($groq_model, 'groq/compound'); ?>>Groq Compound</option>
                            <option value="groq/compound-mini"                        <?php selected($groq_model, 'groq/compound-mini'); ?>>Groq Compound Mini</option>
                            <option value="llama-3.1-8b-instant"                      <?php selected($groq_model, 'llama-3.1-8b-instant'); ?>>Llama 3.1 8B — Fastest</option>
                        </select>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-test-groq-btn">Test Groq Key</button>
                        <?php if ($has_groq_g): ?>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-remove-groq-btn" style="color:var(--c-red,#ef4444);border-color:var(--c-red,#ef4444);" onclick="return false;">✕ Remove API Key</button>
                        <?php endif; ?>
                        <span id="seo-groq-test-result" style="font-size:12px;"></span>
                    </div>
                </div>

                <!-- ── CEREBRAS ── -->
                <div style="border:1px solid var(--c-border);border-radius:10px;padding:14px;display:flex;flex-direction:column;gap:12px;">
                    <div style="font-size:13px;font-weight:700;color:var(--c-text);display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <span style="background:#06b6d4;color:#fff;font-size:10px;padding:2px 7px;border-radius:20px;font-weight:700;">CEREBRAS</span>
                        API Key &amp; Model
                        <?php if ($has_cerebras_g): ?><span class="s-saved-badge">✓ Key saved</span><?php endif; ?>
                    </div>
                    <div class="seo-field" style="margin:0;">
                        <label>Cerebras API Key</label>
                        <div class="s-key-row">
                            <?php $cer_has = $has_cerebras_g; ?>
                            <?php if ($cer_has): ?>
                            <div style="flex:1;position:relative;">
                                <input type="password" id="s-cerebras-key" class="seo-in" autocomplete="new-password" placeholder="••••••••••••••••••••••••  (saved — enter new key to update)" style="width:100%;color:var(--c-muted);">
                            </div>
                            <?php else: ?>
                            <input type="password" id="s-cerebras-key" class="seo-in" autocomplete="new-password" placeholder="csk_..." style="flex:1;">
                            <?php endif; ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" onclick="var f=document.getElementById('s-cerebras-key');f.type=f.type==='password'?'text':'password';return false;">Show</button>
                        </div>
                    </div>
                    <div class="seo-field" style="margin:0;">
                        <label>Cerebras Model</label>
                        <?php $cerebras_model = SEO_Dash_Database::get_setting('cerebras_model', 'gpt-oss-120b'); ?>
                        <select id="s-cerebras-model" class="seo-in">
                            <option value="gpt-oss-120b" <?php selected($cerebras_model, 'gpt-oss-120b'); ?>>GPT-OSS 120B — Best Free</option>
                            <option value="llama3.1-8b"  <?php selected($cerebras_model, 'llama3.1-8b'); ?>>Llama 3.1 8B — Fastest</option>
                        </select>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-test-cerebras-btn">Test Cerebras Key</button>
                        <?php if ($has_cerebras_g): ?>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-remove-cerebras-btn" style="color:var(--c-red,#ef4444);border-color:var(--c-red,#ef4444);" onclick="return false;">✕ Remove API Key</button>
                        <?php endif; ?>
                        <span id="seo-cerebras-test-result" style="font-size:12px;"></span>
                    </div>
                </div>

                <!-- ── GEMINI ── -->
                <div style="border:1px solid var(--c-border);border-radius:10px;padding:14px;display:flex;flex-direction:column;gap:12px;">
                    <div style="font-size:13px;font-weight:700;color:var(--c-text);display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <span style="background:#10b981;color:#fff;font-size:10px;padding:2px 7px;border-radius:20px;font-weight:700;">GOOGLE</span>
                        Gemini API Key &amp; Model
                        <?php if ($has_gemini_g): ?><span class="s-saved-badge">✓ Key saved</span><?php endif; ?>
                    </div>
                    <div class="seo-field" style="margin:0;">
                        <label>Gemini Model</label>
                        <?php $gemini_model = SEO_Dash_Database::get_setting('gemini_model', 'gemini-2.5-flash'); ?>
                        <select id="s-gemini-model" class="seo-in">
                            <option value="gemini-2.5-flash"      <?php selected($gemini_model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash — Best</option>
                            <option value="gemini-2.0-flash"      <?php selected($gemini_model, 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash (Recommended)</option>
                            <option value="gemini-2.0-flash-lite" <?php selected($gemini_model, 'gemini-2.0-flash-lite'); ?>>Gemini 2.0 Flash Lite</option>
                            <option value="gemini-1.5-flash"      <?php selected($gemini_model, 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash</option>
                            <option value="gemini-1.5-flash-8b"   <?php selected($gemini_model, 'gemini-1.5-flash-8b'); ?>>Gemini 1.5 Flash 8B</option>
                            <option value="gemini-2.5-flash-preview-05-20" <?php selected($gemini_model, 'gemini-2.5-flash-preview-05-20'); ?>>Gemini 2.5 Flash Preview</option>
                        </select>
                    </div>
                    <div class="seo-field" style="margin:0;">
                        <label>Gemini API Key</label>
                        <div class="s-key-row">
                            <?php $gem_has = $has_gemini_g; ?>
                            <?php if ($gem_has): ?>
                            <div style="flex:1;position:relative;">
                                <input type="password" id="s-gemini-key" class="seo-in" autocomplete="new-password" placeholder="••••••••••••••••••••••••  (saved — enter new key to update)" style="width:100%;color:var(--c-muted);">
                            </div>
                            <?php else: ?>
                            <input type="password" id="s-gemini-key" class="seo-in" autocomplete="new-password" placeholder="AIza..." style="flex:1;">
                            <?php endif; ?>
                            <button class="seo-btn seo-btn-ghost seo-btn-sm" onclick="var f=document.getElementById('s-gemini-key');f.type=f.type==='password'?'text':'password';return false;">Show</button>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-test-gemini-btn">Test Gemini Key</button>
                        <?php if ($has_gemini_g): ?>
                        <button class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-remove-gemini-btn" style="color:var(--c-red,#ef4444);border-color:var(--c-red,#ef4444);" onclick="return false;">✕ Remove API Key</button>
                        <?php endif; ?>
                        <span id="seo-gemini-test-result" style="font-size:12px;line-height:1.5;"></span>
                    </div>
                </div>
            </div>
        </div>

        </div><!-- /right column: AI & Chatbot -->

    </div>

    <!-- ════════════════════════════════════════════════════════════════
         Email Settings — Gmail OAuth + SMTP fallback
         ════════════════════════════════════════════════════════════════ -->
    <div class="seo-panel" style="margin-top:20px;" id="seo-gmail-oauth-panel">
        <div class="seo-panel-hd"><h2>📧 Email Settings</h2></div>
        <div class="seo-panel-body">
        <style>
            /* ── Gmail OAuth Card ── */
            .seo-email-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
            @media(max-width:760px){ .seo-email-grid { grid-template-columns:1fr; } }

            .seo-oauth-card {
                background:var(--c-surf2); border:1px solid var(--c-border);
                border-radius:12px; padding:20px; display:flex; flex-direction:column; gap:14px;
            }
            .seo-oauth-card-hd {
                display:flex; align-items:center; gap:10px;
                font-size:13px; font-weight:700; color:var(--c-text);
            }
            .seo-oauth-card-hd .seo-oauth-badge {
                font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px;
                background:rgba(63,185,80,.15); color:#2da44e; border:1px solid rgba(63,185,80,.3);
            }
            .seo-oauth-card-hd .seo-oauth-badge.disconnected {
                background:rgba(227,179,65,.12); color:#b08800; border-color:rgba(227,179,65,.3);
            }

            .seo-oauth-account-row {
                display:flex; align-items:center; gap:12px;
                background:rgba(63,185,80,.06); border:1px solid rgba(63,185,80,.2);
                border-radius:8px; padding:10px 14px;
            }
            .seo-oauth-account-icon {
                width:36px; height:36px; border-radius:50%; flex-shrink:0;
                background:linear-gradient(135deg,#4285F4,#34A853);
                display:flex; align-items:center; justify-content:center;
                font-size:16px; color:#fff;
            }
            .seo-oauth-account-info { flex:1; min-width:0; }
            .seo-oauth-account-info strong { display:block; font-size:12.5px; color:var(--c-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .seo-oauth-account-info span  { font-size:11.5px; color:var(--c-muted); }

            .seo-oauth-actions { display:flex; gap:8px; flex-wrap:wrap; }

            .seo-oauth-google-btn {
                display:inline-flex; align-items:center; gap:10px;
                background:#fff; color:#3c4043; border:1px solid #dadce0;
                border-radius:8px; padding:11px 20px; font-size:13.5px;
                font-weight:600; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.1);
                transition:box-shadow .15s; width:100%; justify-content:center;
            }
            .seo-oauth-google-btn:hover { box-shadow:0 2px 8px rgba(0,0,0,.18); }
            .seo-oauth-google-btn svg { width:20px; height:20px; flex-shrink:0; }

            /* ── SMTP Card ── */
            .seo-smtp-card {
                background:var(--c-surf2); border:1px solid var(--c-border);
                border-radius:12px; padding:20px; display:flex; flex-direction:column; gap:14px;
            }
            .seo-smtp-card-hd {
                display:flex; align-items:center; justify-content:space-between; gap:10px;
            }
            .seo-smtp-card-hd-left {
                display:flex; align-items:center; gap:10px;
                font-size:13px; font-weight:700; color:var(--c-text);
            }
            .seo-smtp-toggle-wrap {
                display:flex; align-items:center; gap:8px; font-size:12px; color:var(--c-muted);
            }
            /* iOS-style toggle */
            .seo-ios-toggle { position:relative; display:inline-block; width:38px; height:22px; flex-shrink:0; }
            .seo-ios-toggle input { opacity:0; width:0; height:0; }
            .seo-ios-slider {
                position:absolute; cursor:pointer; inset:0; background:#ccc; border-radius:22px;
                transition:.25s;
            }
            .seo-ios-slider:before {
                content:""; position:absolute; width:16px; height:16px;
                left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.25s;
                box-shadow:0 1px 3px rgba(0,0,0,.2);
            }
            .seo-ios-toggle input:checked + .seo-ios-slider { background:var(--c-primary); }
            .seo-ios-toggle input:checked + .seo-ios-slider:before { transform:translateX(16px); }

            .seo-smtp-fields { display:flex; flex-direction:column; gap:12px; }
            .seo-smtp-fields.disabled { opacity:.38; pointer-events:none; user-select:none; }

            .seo-smtp-provider-tabs { display:flex; gap:6px; margin-bottom:4px; }
            .seo-smtp-ptab {
                padding:6px 14px; font-size:12px; font-weight:700; border-radius:6px;
                border:1px solid var(--c-border); background:var(--c-surf); color:var(--c-muted);
                cursor:pointer; transition:all .15s;
            }
            .seo-smtp-ptab.active {
                background:var(--c-primary); color:#fff; border-color:var(--c-primary);
            }

            #seo-oauth-test-result { font-size:12.5px; min-height:18px; }
        </style>

        <?php
        $smtp_override_enabled = (bool) SEO_Dash_Database::get_setting( 'smtp_override_enabled', false );
        ?>

        <!-- ══ Two-column grid ══════════════════════════════════════════ -->
        <div class="seo-email-grid">

            <!-- ── LEFT: Gmail OAuth ──────────────────────────────────── -->
            <div class="seo-oauth-card">
                <div class="seo-oauth-card-hd">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span>🔐 Gmail OAuth</span>
                        <?php if ( $oauth_connected ) : ?>
                            <span class="seo-oauth-badge">● Connected</span>
                        <?php else : ?>
                            <span class="seo-oauth-badge disconnected">○ Not Connected</span>
                        <?php endif; ?>
                    </div>
                    <?php if ( $oauth_connected ) : ?>
                    <div style="display:flex;gap:6px;" id="seo-oauth-hd-actions">
                        <button type="button" class="seo-btn seo-btn-sm seo-btn-ghost" id="seo-oauth-change-btn" style="font-size:11.5px;padding:4px 10px;">
                            🔄 Change
                        </button>
                        <button type="button" class="seo-btn seo-btn-sm" id="seo-oauth-disconnect-btn"
                            style="font-size:11.5px;padding:4px 10px;background:rgba(220,53,69,.1);color:#dc3545;border-color:rgba(220,53,69,.3);">
                            🔌 Disconnect
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ( $oauth_connected ) : ?>
                <!-- Connected -->
                <div class="seo-oauth-account-row" id="seo-oauth-connected-box">
                    <div class="seo-oauth-account-icon">✉️</div>
                    <div class="seo-oauth-account-info">
                        <strong id="seo-oauth-email-display"><?php echo esc_html( $oauth_email ); ?></strong>
                        <span>Sending via Gmail API — no warnings</span>
                    </div>
                </div>
                <!-- Test button -->
                <button type="button" class="seo-btn seo-btn-sm" id="seo-oauth-test-btn"
                    style="background:var(--c-green);color:#fff;border-color:var(--c-green);">
                    ✉️ Send Test Email
                </button>
                <div id="seo-oauth-test-result"></div>

                <!-- Settings fields -->
                <div class="seo-field">
                    <label>Display Name <span style="font-weight:400;color:var(--c-muted);">(shown in inbox)</span></label>
                    <input type="text" id="seo-oauth-from-name" class="seo-in"
                        value="<?php echo esc_attr( SEO_Dash_Database::get_setting('smtp_from_name','') ); ?>"
                        placeholder="Your Agency Name">
                </div>
                <div class="seo-field">
                    <label>Test Email Recipient</label>
                    <input type="email" id="seo-oauth-test-to" class="seo-in"
                        value="<?php echo esc_attr( SEO_Dash_Database::get_setting('oauth_test_email', get_option('admin_email','')) ); ?>"
                        placeholder="you@yourdomain.com">
                </div>
                <button type="button" class="seo-btn seo-btn-primary seo-btn-sm" id="seo-oauth-save-name-btn">
                    💾 Save Settings
                </button>



                <?php else : ?>
                <!-- Not connected -->
                <p style="font-size:12.5px;color:var(--c-muted);margin:0;">
                    Connect your Gmail account with one click.<br>
                    <strong style="color:var(--c-text);">No app passwords. No DNS records. Zero config.</strong>
                    Emails send directly from Gmail — no spam warnings ever.
                </p>
                <button type="button" id="seo-oauth-connect-btn" class="seo-oauth-google-btn">
                    <svg viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.2l6.7-6.7C35.8 2.3 30.3 0 24 0 14.7 0 6.7 5.4 2.7 13.3l7.8 6.1C12.5 13 17.8 9.5 24 9.5z"/><path fill="#4285F4" d="M46.6 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4 7.2-10 7.2-17z"/><path fill="#FBBC05" d="M10.5 28.6A14.8 14.8 0 0 1 9.5 24c0-1.6.3-3.2.7-4.6l-7.8-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.7 10.7l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.3 0 11.6-2.1 15.5-5.7l-7.5-5.8c-2.1 1.4-4.8 2.2-8 2.2-6.2 0-11.5-4.2-13.4-9.9l-7.8 6.1C6.7 42.6 14.7 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
                    Connect Gmail Account
                </button>
                <p style="font-size:11.5px;color:var(--c-muted);margin:0;text-align:center;">
                    A Google sign-in popup will open. Sign in → Allow → Done.
                </p>
                <?php endif; ?>
            </div>

            <!-- ── RIGHT: Custom SMTP ─────────────────────────────────── -->
            <div class="seo-smtp-card">
                <div class="seo-smtp-card-hd">
                    <div class="seo-smtp-card-hd-left">
                        <span>⚙️ Custom SMTP</span>
                    </div>
                    <div class="seo-smtp-toggle-wrap">
                        <span id="seo-smtp-toggle-label" style="font-size:12px;color:var(--c-muted);">
                            <?php echo $smtp_override_enabled ? 'Enabled' : 'Disabled'; ?>
                        </span>
                        <label class="seo-ios-toggle" title="Enable custom SMTP instead of Gmail OAuth">
                            <input type="checkbox" id="seo-smtp-override-toggle"
                                <?php echo $smtp_override_enabled ? 'checked' : ''; ?>>
                            <span class="seo-ios-slider"></span>
                        </label>
                    </div>
                </div>

                <p style="font-size:12px;color:var(--c-muted);margin:0;">
                    Use your own SMTP server (Outlook, Zoho, cPanel, etc.) instead of Gmail OAuth.
                    Enable the toggle above to activate.
                </p>

                <div class="seo-smtp-fields <?php echo $smtp_override_enabled ? '' : 'disabled'; ?>" id="seo-smtp-fields-wrap">
                    <!-- Provider tabs -->
                    <div class="seo-smtp-provider-tabs">
                        <button type="button" class="seo-smtp-ptab <?php echo ($smtp_mode !== 'other') ? 'active' : ''; ?>" data-smtp-tab="gmail">✉️ Gmail</button>
                        <button type="button" class="seo-smtp-ptab <?php echo $smtp_mode === 'other' ? 'active' : ''; ?>" data-smtp-tab="other">⚙️ Other</button>
                    </div>

                    <!-- Gmail SMTP -->
                    <div class="seo-smtp-pane" data-smtp-pane="gmail" style="<?php echo ($smtp_mode === 'other') ? 'display:none;' : ''; ?>">
                        <div style="font-size:11.5px;line-height:1.6;color:var(--c-muted);background:rgba(99,102,241,.06);border:1px solid rgba(99,102,241,.18);border-radius:7px;padding:9px 12px;margin-bottom:10px;">
                            Requires <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--c-primary);">Gmail App Password</a> — enable 2-Step Verification first.
                        </div>
                        <div class="seo-grid-2">
                            <div class="seo-field">
                                <label>Gmail Address</label>
                                <input type="email" id="smtp-gmail-username" class="seo-in" autocomplete="off"
                                    value="<?php echo esc_attr( $smtp_mode === 'gmail' ? SEO_Dash_Database::get_setting('smtp_username','') : '' ); ?>"
                                    placeholder="you@gmail.com">
                            </div>
                            <div class="seo-field">
                                <label>App Password</label>
                                <div style="display:flex;gap:6px;">
                                    <input type="password" id="smtp-gmail-password" class="seo-in" style="flex:1;" autocomplete="new-password"
                                        placeholder="<?php echo ( $smtp_mode === 'gmail' && SEO_Dash_Database::get_setting('smtp_password','') ) ? '•••••••••• (saved)' : '16-char app password'; ?>">
                                    <button type="button" class="seo-btn seo-btn-ghost seo-btn-sm seo-smtp-toggle-pass" data-target="smtp-gmail-password">Show</button>
                                </div>
                            </div>
                        </div>
                        <div class="seo-grid-2" style="margin-top:10px;">
                            <div class="seo-field">
                                <label>From Name</label>
                                <input type="text" id="smtp-gmail-from-name" class="seo-in"
                                    value="<?php echo esc_attr( $smtp_mode === 'gmail' ? SEO_Dash_Database::get_setting('smtp_from_name','') : '' ); ?>"
                                    placeholder="Your Agency">
                            </div>
                            <div class="seo-field">
                                <label>From Email</label>
                                <input type="email" id="smtp-gmail-from-email" class="seo-in"
                                    value="<?php echo esc_attr( $smtp_mode === 'gmail' ? SEO_Dash_Database::get_setting('smtp_from_email','') : '' ); ?>"
                                    placeholder="you@gmail.com">
                            </div>
                        </div>
                    </div>

                    <!-- Other SMTP -->
                    <div class="seo-smtp-pane" data-smtp-pane="other" style="<?php echo $smtp_mode === 'other' ? '' : 'display:none;'; ?>">
                        <div class="seo-grid-2">
                            <div class="seo-field">
                                <label>SMTP Host</label>
                                <input type="text" id="smtp-other-host" class="seo-in" autocomplete="off"
                                    value="<?php echo esc_attr( $smtp_mode === 'other' ? SEO_Dash_Database::get_setting('smtp_host','') : '' ); ?>"
                                    placeholder="smtp.yourprovider.com">
                            </div>
                            <div class="seo-field">
                                <label>Port</label>
                                <?php $other_port = $smtp_mode === 'other' ? intval(SEO_Dash_Database::get_setting('smtp_port',587)) : 587; ?>
                                <select id="smtp-other-port" class="seo-in">
                                    <option value="587" <?php selected($other_port,587); ?>>587 — TLS</option>
                                    <option value="465" <?php selected($other_port,465); ?>>465 — SSL</option>
                                    <option value="25"  <?php selected($other_port,25);  ?>>25 — Plain</option>
                                </select>
                            </div>
                        </div>
                        <div class="seo-grid-2" style="margin-top:10px;">
                            <div class="seo-field">
                                <label>Username</label>
                                <input type="text" id="smtp-other-username" class="seo-in" autocomplete="off"
                                    value="<?php echo esc_attr( $smtp_mode === 'other' ? SEO_Dash_Database::get_setting('smtp_username','') : '' ); ?>"
                                    placeholder="you@yourdomain.com">
                            </div>
                            <div class="seo-field">
                                <label>Password</label>
                                <div style="display:flex;gap:6px;">
                                    <input type="password" id="smtp-other-password" class="seo-in" style="flex:1;" autocomplete="new-password"
                                        placeholder="<?php echo ( $smtp_mode === 'other' && SEO_Dash_Database::get_setting('smtp_password','') ) ? '•••••••••• (saved)' : 'Password'; ?>">
                                    <button type="button" class="seo-btn seo-btn-ghost seo-btn-sm seo-smtp-toggle-pass" data-target="smtp-other-password">Show</button>
                                </div>
                            </div>
                        </div>
                        <div class="seo-grid-2" style="margin-top:10px;">
                            <div class="seo-field">
                                <label>From Name</label>
                                <input type="text" id="smtp-other-from-name" class="seo-in"
                                    value="<?php echo esc_attr( $smtp_mode === 'other' ? SEO_Dash_Database::get_setting('smtp_from_name','') : '' ); ?>"
                                    placeholder="Your Agency">
                            </div>
                            <div class="seo-field">
                                <label>From Email</label>
                                <input type="email" id="smtp-other-from-email" class="seo-in"
                                    value="<?php echo esc_attr( $smtp_mode === 'other' ? SEO_Dash_Database::get_setting('smtp_from_email','') : '' ); ?>"
                                    placeholder="you@yourdomain.com">
                            </div>
                        </div>
                    </div>

                    <!-- SMTP action row -->
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px;padding-top:12px;border-top:1px solid var(--c-border);">
                        <button type="button" class="seo-btn seo-btn-primary seo-btn-sm" id="seo-save-email-settings-btn">💾 Save SMTP</button>
                        <button type="button" class="seo-btn seo-btn-sm" id="seo-smtp-test-btn-legacy"
                            style="background:var(--c-green);color:#fff;border-color:var(--c-green);">✉️ Test</button>
                        <span id="seo-smtp-test-result" style="font-size:12px;flex:1;"></span>
                    </div>
                </div>
            </div>

        </div><!-- /seo-email-grid -->
        </div>
    </div><!-- /Email OAuth panel -->

    <!-- ════════════════════════════════════════════════════════════════
         ⚡ Automated Background Schedulers
         ════════════════════════════════════════════════════════════════ -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2>⚡ Automation &amp; Background Schedulers</h2>
            <span style="font-size:12px;color:var(--c-muted);">Hands-free automated data syncing and sitemap re-crawling</span>
        </div>
        <div class="seo-panel-body" style="display:flex;flex-direction:column;gap:18px;">
            <!-- 1. Monthly GA4 / SC Snapshot Sync -->
            <div style="border:1px solid var(--c-border);border-radius:10px;padding:16px;background:var(--c-surf);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div>
                        <strong style="font-size:14px;color:var(--c-text);">📅 Automated Monthly Analytics Snapshot Fetch</strong>
                        <p style="margin:4px 0 0;font-size:12px;color:var(--c-muted);">
                            Automatically fetches and locks the previous month’s complete analytics snapshot (7d, 30d, 90d, overall) on the 1st of every month for all reports with connected Google credentials.
                        </p>
                    </div>
                    <label class="seo-ios-toggle" style="margin-left:16px;">
                        <input type="checkbox" id="s-auto-monthly-sync" <?php checked(get_option('seo_dash_auto_monthly_sync_enabled', '1'), '1'); ?>>
                        <span class="seo-ios-slider"></span>
                    </label>
                </div>
                <div style="display:flex;align-items:center;gap:10px;margin-top:12px;">
                    <button type="button" class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-trigger-monthly-sync-btn">▶ Run Monthly Analytics Fetch Now</button>
                    <span id="seo-monthly-sync-status" style="font-size:12px;color:var(--c-muted);"></span>
                </div>
            </div>

            <!-- 2. Scheduled Sitemap Re-Crawling -->
            <div style="border:1px solid var(--c-border);border-radius:10px;padding:16px;background:var(--c-surf);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div>
                        <strong style="font-size:14px;color:var(--c-text);">🕷️ Scheduled Sitemap Re-Crawling &amp; Auto-Import</strong>
                        <p style="margin:4px 0 0;font-size:12px;color:var(--c-muted);">
                            Re-crawls registered XML sitemaps, applies custom URL routing rules, runs strict deduplication against existing database rows, and imports newly published URLs automatically.
                        </p>
                    </div>
                    <label class="seo-ios-toggle" style="margin-left:16px;">
                        <input type="checkbox" id="s-auto-sitemap-recrawl" <?php checked(get_option('seo_dash_auto_sitemap_recrawl_enabled', '1'), '1'); ?>>
                        <span class="seo-ios-slider"></span>
                    </label>
                </div>
                <div style="display:flex;align-items:center;gap:12px;margin-top:12px;flex-wrap:wrap;">
                    <label style="font-size:12px;font-weight:600;color:var(--c-text);">Recrawl Frequency:</label>
                    <?php $sm_freq = get_option('seo_dash_auto_sitemap_recrawl_freq', 'weekly'); ?>
                    <select id="s-auto-sitemap-freq" class="seo-in" style="width:auto;padding:4px 10px;font-size:12px;">
                        <option value="daily" <?php selected($sm_freq, 'daily'); ?>>Daily</option>
                        <option value="weekly" <?php selected($sm_freq, 'weekly'); ?>>Weekly (Recommended)</option>
                        <option value="monthly" <?php selected($sm_freq, 'monthly'); ?>>Monthly</option>
                    </select>
                    <button type="button" class="seo-btn seo-btn-ghost seo-btn-sm" id="seo-trigger-sitemap-recrawl-btn">▶ Run Sitemap Re-Crawl Now</button>
                    <span id="seo-sitemap-recrawl-status" style="font-size:12px;color:var(--c-muted);"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         Email Authentication Health Check (plain-English SPF/DKIM/DMARC)
         ════════════════════════════════════════════════════════════════ -->
    <div class="seo-panel" style="margin-top:20px;">
        <div class="seo-panel-hd">
            <h2>🩺 Email Authentication Health Check</h2>
            <button type="button" class="seo-btn seo-btn-sm" id="seo-authcheck-run-btn">🔄 Run Check</button>
        </div>
        <div class="seo-panel-body">
            <p class="seo-field-hint" style="margin-top:0;">
                This checks whether emails sent from your domain will look trustworthy to Gmail and other providers — without needing to understand DKIM, SPF, or any of that. Run it any time after changing your email settings above.
            </p>

            <div id="seo-authcheck-domain" style="font-size:12.5px;color:var(--c-muted);margin-bottom:14px;display:none;">Checking domain: <strong id="seo-authcheck-domain-name"></strong></div>

            <div id="seo-authcheck-results" style="display:flex;flex-direction:column;gap:10px;">
                <div class="seo-authcheck-row" data-check="spf" style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1px solid var(--c-border);border-radius:8px;background:var(--c-surf2);">
                    <span class="seo-authcheck-icon" style="font-size:18px;line-height:1.4;">⏳</span>
                    <div style="flex:1;">
                        <div class="seo-authcheck-title" style="font-size:13px;font-weight:700;color:var(--c-text);">SPF — who's allowed to send as your domain</div>
                        <div class="seo-authcheck-detail" style="font-size:12px;color:var(--c-muted);margin-top:2px;">Click "Run Check" above to test this.</div>
                        <div class="seo-authcheck-fix" style="display:none;margin-top:10px;"></div>
                    </div>
                </div>
                <div class="seo-authcheck-row" data-check="dkim" style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1px solid var(--c-border);border-radius:8px;background:var(--c-surf2);">
                    <span class="seo-authcheck-icon" style="font-size:18px;line-height:1.4;">⏳</span>
                    <div style="flex:1;">
                        <div class="seo-authcheck-title" style="font-size:13px;font-weight:700;color:var(--c-text);">DKIM — proves emails weren't tampered with</div>
                        <div class="seo-authcheck-detail" style="font-size:12px;color:var(--c-muted);margin-top:2px;">Click "Run Check" above to test this.</div>
                        <div class="seo-authcheck-fix" style="display:none;margin-top:10px;"></div>
                    </div>
                </div>
                <div class="seo-authcheck-row" data-check="dmarc" style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1px solid var(--c-border);border-radius:8px;background:var(--c-surf2);">
                    <span class="seo-authcheck-icon" style="font-size:18px;line-height:1.4;">⏳</span>
                    <div style="flex:1;">
                        <div class="seo-authcheck-title" style="font-size:13px;font-weight:700;color:var(--c-text);">DMARC — tells Gmail what to do if a check fails</div>
                        <div class="seo-authcheck-detail" style="font-size:12px;color:var(--c-muted);margin-top:2px;">Click "Run Check" above to test this.</div>
                        <div class="seo-authcheck-fix" style="display:none;margin-top:10px;"></div>
                    </div>
                </div>
            </div>

            <div id="seo-authcheck-error" style="display:none;margin-top:12px;font-size:12.5px;color:var(--c-red);"></div>
        </div>
    </div><!-- /Email Authentication Health Check panel -->

</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    /* ── Email Authentication Health Check ───────────────────────────── */
    function authcheckSetRow(check, ok, detailHtml, fixHtml){
        var $row  = $('.seo-authcheck-row[data-check="'+check+'"]');
        var $icon = $row.find('.seo-authcheck-icon');
        var $det  = $row.find('.seo-authcheck-detail');
        var $fix  = $row.find('.seo-authcheck-fix');
        $icon.text(ok ? '✅' : '⚠️');
        $row.css('border-color', ok ? 'rgba(63,185,80,.35)' : 'rgba(227,179,65,.4)');
        $det.html(detailHtml);
        if (fixHtml) {
            $fix.html(fixHtml).show();
        } else {
            $fix.hide().empty();
        }
    }

    function authcheckCopyBtn(text){
        var id = 'authcopy_' + Math.random().toString(36).slice(2);
        setTimeout(function(){
            document.getElementById(id).addEventListener('click', function(){
                navigator.clipboard.writeText(text).then(function(){
                    var btn = document.getElementById(id);
                    var orig = btn.textContent;
                    btn.textContent = '✅ Copied!';
                    setTimeout(function(){ btn.textContent = orig; }, 2000);
                });
            });
        }, 0);
        return '<button type="button" id="'+id+'" class="seo-btn seo-btn-ghost seo-btn-sm" style="margin-top:6px;">📋 Copy this record</button>';
    }

    $('#seo-authcheck-run-btn').on('click', function(){
        var $btn = $(this).text('Checking…').prop('disabled', true);
        $('#seo-authcheck-error').hide().text('');
        $('.seo-authcheck-row .seo-authcheck-icon').text('⏳');
        $('.seo-authcheck-row .seo-authcheck-detail').text('Checking…');
        $('.seo-authcheck-row .seo-authcheck-fix').hide().empty();

        $.post(seoDash.ajax, { action: 'seo_dash_check_email_auth', nonce: seoDash.nonce }, function(r){
            $btn.text('🔄 Run Check').prop('disabled', false);

            if (!r.success) {
                $('#seo-authcheck-error').show().text((r.data && r.data.message) || 'Could not run the check.');
                return;
            }

            var d = r.data || {};

            if (d.dns_unavailable) {
                $('#seo-authcheck-error').show().text(r.message || 'Your server doesn\'t allow live DNS lookups. Use mail-tester.com to check this domain instead.');
                return;
            }

            $('#seo-authcheck-domain').show();
            $('#seo-authcheck-domain-name').text(d.domain || '');

            // SPF
            if (d.spf && d.spf.found) {
                authcheckSetRow('spf', true, 'Found — your domain has published who\'s allowed to send email on its behalf.');
            } else {
                var spfFix = '<div style="font-size:12px;color:var(--c-muted);">No SPF record found for <strong>'+ (d.domain||'') +'</strong>. Add a TXT record at your domain\'s DNS host';
                if (d.spf && d.spf.suggestion) {
                    spfFix += ' with this value:</div><div style="margin-top:6px;background:var(--c-surf);border:1px solid var(--c-border);border-radius:6px;padding:8px 10px;font-family:monospace;font-size:11.5px;word-break:break-all;">'+ d.spf.suggestion +'</div>' + authcheckCopyBtn(d.spf.suggestion);
                } else if (d.smtp_mode === 'brevo') {
                    spfFix += '. (Brevo doesn\'t require an SPF record — this is optional for your setup, DKIM alone is enough.)</div>';
                } else {
                    spfFix += '. Ask your email provider for their SPF "include" value.</div>';
                }
                authcheckSetRow('spf', false, 'Not found.', spfFix);
            }

            // DKIM
            if (d.dkim && d.dkim.found) {
                authcheckSetRow('dkim', true, 'Found (selector: '+ d.dkim.selector +') — emails from your domain can be cryptographically verified.');
            } else {
                var dkimFix = '<div style="font-size:12px;color:var(--c-muted);">No DKIM record found among common selectors. ';
                if (d.smtp_mode === 'gmail') {
                    dkimFix += 'Since you\'re sending via Gmail/Workspace: ask whoever has Google Workspace admin access to go to <strong>admin.google.com → Apps → Google Workspace → Gmail → Authenticate email</strong>, generate a DKIM key, and give you the DNS record it provides to add at your domain host.';
                } else if (d.smtp_mode === 'brevo') {
                    dkimFix += 'Log into Brevo → <strong>Senders, Domains & Dedicated IPs → Domains</strong>, open your domain, and add the DKIM record it shows you to your domain\'s DNS.';
                } else {
                    dkimFix += 'Ask your email provider for their DKIM / "domain authentication" record and add it at your domain\'s DNS host.';
                }
                dkimFix += '</div>';
                authcheckSetRow('dkim', false, 'Not found.', dkimFix);
            }

            // DMARC
            if (d.dmarc && d.dmarc.found) {
                authcheckSetRow('dmarc', true, 'Found (policy: '+ (d.dmarc.policy || 'none') +').');
            } else {
                var dmarcFix = '<div style="font-size:12px;color:var(--c-muted);">No DMARC record found. Add a TXT record at <strong>_dmarc.'+ (d.domain||'') +'</strong> with this value to start (safe, monitor-only setting):</div>';
                if (d.dmarc && d.dmarc.suggestion) {
                    dmarcFix += '<div style="margin-top:6px;background:var(--c-surf);border:1px solid var(--c-border);border-radius:6px;padding:8px 10px;font-family:monospace;font-size:11.5px;word-break:break-all;">'+ d.dmarc.suggestion +'</div>' + authcheckCopyBtn(d.dmarc.suggestion);
                }
                authcheckSetRow('dmarc', false, 'Not found.', dmarcFix);
            }
        }).fail(function(){
            $btn.text('🔄 Run Check').prop('disabled', false);
            $('#seo-authcheck-error').show().text('Network error — please try again.');
        });
    });

    // Auto-run once on page load so the panel isn't sitting empty.
    $('#seo-authcheck-run-btn').trigger('click');
})(jQuery);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
(function($){
    $('#seo-groq-show-btn').on('click',function(){
        var $f=$('#s-groq-key');
        $f.attr('type',$f.attr('type')==='password'?'text':'password');
    });
    $('#seo-test-deepseek-btn').on('click',function(){
        var key=$('#s-deepseek-key').val().trim();
        var model=$('#s-deepseek-model').val();
        var $r=$('#seo-deepseek-test-result').text('Testing…').css('color','inherit');
        $.post(seoDash.ajax,{action:'seo_dash_test_deepseek',nonce:seoDash.nonce,api_key:key,test_model:model},function(r){
            if(r.success){ $r.text('✅ '+(r.data&&r.data.message?r.data.message:'Connected')).css('color','var(--c-green)'); }
            else { var e=(r.data&&r.data.message)?r.data.message:'Connection failed.'; $r.text('❌ '+e).css('color','var(--c-red)'); }
        }).fail(function(){ $r.text('❌ Request failed').css('color','var(--c-red)'); });
    });
    $('#seo-test-groq-btn').on('click',function(){
        var key=$('#s-groq-key').val().trim();
        var model=$('#s-groq-model').val();
        var $r=$('#seo-groq-test-result').text('Testing…').css('color','inherit');
        $.post(seoDash.ajax,{action:'seo_dash_test_groq',nonce:seoDash.nonce,api_key:key,test_model:model},function(r){
            if(r.success){ $r.text('✅ '+(r.data&&r.data.message?r.data.message:'Connected')).css('color','var(--c-green)'); }
            else { var e=(r.data&&r.data.message)?r.data.message:'Connection failed.'; $r.text('❌ '+e).css('color','var(--c-red)'); }
        });
    });
    $('#seo-test-cerebras-btn').on('click',function(){
        var key=$('#s-cerebras-key').val().trim();
        var model=$('#s-cerebras-model').val();
        var $r=$('#seo-cerebras-test-result').text('Testing…').css('color','inherit');
        $.post(seoDash.ajax,{action:'seo_dash_test_cerebras',nonce:seoDash.nonce,api_key:key,test_model:model},function(r){
            if(r.success){ $r.text('✅ '+(r.data&&r.data.message?r.data.message:'Connected')).css('color','var(--c-green)'); }
            else { var e=(r.data&&r.data.message)?r.data.message:'Connection failed.'; $r.text('❌ '+e).css('color','var(--c-red)'); }
        });
    });
    $('#seo-test-gemini-btn').on('click',function(){
        var key=$('#s-gemini-key').val().trim();
        var model=$('#s-gemini-model').val();
        var $r=$('#seo-gemini-test-result').text('Testing…').css('color','inherit');
        $.post(seoDash.ajax,{action:'seo_dash_test_gemini',nonce:seoDash.nonce,api_key:key,test_model:model},function(r){
            if(r.success){
                $r.text('✅ Connected successfully').css('color','var(--c-green)');
            } else {
                var errMsg = (r.data && r.data.message) ? r.data.message : 'Connection failed.';
                $r.html('❌ ' + errMsg).css('color','var(--c-red)');
            }
        }).fail(function(){ $r.text('❌ Request failed').css('color','var(--c-red)'); });
    });
    $('#seo-remove-deepseek-btn').on('click',function(){
        if(!confirm('Remove the saved DeepSeek API key? This cannot be undone.')) return;
        var $btn=$(this).text('Removing…').prop('disabled',true);
        var $r=$('#seo-deepseek-test-result');
        $.post(seoDash.ajax,{action:'seo_dash_remove_deepseek_key',nonce:seoDash.nonce},function(r){
            if(r.success){
                $btn.remove();
                $('#s-deepseek-key').val('').attr('placeholder','sk-...');
                $('.s-saved-badge').first().remove();
                $r.text('✅ API key removed.').css('color','var(--c-green)');
                setTimeout(function(){ $r.text(''); },3000);
            } else {
                $btn.text('✕ Remove API Key').prop('disabled',false);
                $r.text('❌ '+(r.data&&r.data.message?r.data.message:'Failed to remove.')).css('color','var(--c-red)');
            }
        }).fail(function(){ $btn.text('✕ Remove API Key').prop('disabled',false); });
    });
    $('#seo-remove-groq-btn').on('click',function(){
        if(!confirm('Remove the saved Groq API key? This cannot be undone.')) return;
        var $btn=$(this).text('Removing…').prop('disabled',true);
        var $r=$('#seo-groq-test-result');
        $.post(seoDash.ajax,{action:'seo_dash_remove_groq_key',nonce:seoDash.nonce},function(r){
            if(r.success){
                $btn.remove();
                $('#s-groq-key').val('').attr('placeholder','gsk_...');
                $('.s-saved-badge').first().remove();
                $r.text('✅ API key removed.').css('color','var(--c-green)');
                setTimeout(function(){ $r.text(''); },3000);
            } else {
                $btn.text('✕ Remove API Key').prop('disabled',false);
                $r.text('❌ '+(r.data&&r.data.message?r.data.message:'Failed to remove.')).css('color','var(--c-red)');
            }
        }).fail(function(){ $btn.text('✕ Remove API Key').prop('disabled',false); });
    });
    $('#seo-remove-cerebras-btn').on('click',function(){
        if(!confirm('Remove the saved Cerebras API key? This cannot be undone.')) return;
        var $btn=$(this).text('Removing…').prop('disabled',true);
        var $r=$('#seo-cerebras-test-result');
        $.post(seoDash.ajax,{action:'seo_dash_remove_cerebras_key',nonce:seoDash.nonce},function(r){
            if(r.success){
                $btn.remove();
                $('#s-cerebras-key').val('').attr('placeholder','csk_...');
                $('.s-saved-badge').first().remove();
                $r.text('✅ API key removed.').css('color','var(--c-green)');
                setTimeout(function(){ $r.text(''); },3000);
            } else {
                $btn.text('✕ Remove API Key').prop('disabled',false);
                $r.text('❌ '+(r.data&&r.data.message?r.data.message:'Failed to remove.')).css('color','var(--c-red)');
            }
        }).fail(function(){ $btn.text('✕ Remove API Key').prop('disabled',false); });
    });
    $('#seo-remove-gemini-btn').on('click',function(){
        if(!confirm('Remove the saved Gemini API key? This cannot be undone.')) return;
        var $btn=$(this).text('Removing…').prop('disabled',true);
        var $r=$('#seo-gemini-test-result');
        $.post(seoDash.ajax,{action:'seo_dash_remove_gemini_key',nonce:seoDash.nonce},function(r){
            if(r.success){
                $btn.remove();
                $('#s-gemini-key').val('').attr('placeholder','AIza...');
                // Remove the "key saved" badge
                $('.s-saved-badge').remove();
                $r.text('✅ API key removed.').css('color','var(--c-green)');
                setTimeout(function(){ $r.text(''); },3000);
            } else {
                $btn.text('✕ Remove API Key').prop('disabled',false);
                $r.text('❌ '+(r.data&&r.data.message?r.data.message:'Failed to remove.')).css('color','var(--c-red)');
            }
        }).fail(function(){ $btn.text('✕ Remove API Key').prop('disabled',false); });
    });
    $('#seo-recreate-client-page-btn').on('click',function(){
        if(!confirm('Create a new client portal page?')) return;
        var $btn=$(this).text('Creating…').prop('disabled',true);
        $.post(seoDash.ajax,{action:'seo_dash_recreate_page',nonce:seoDash.nonce},function(r){
            $btn.text('🔄 Recreate Client Portal Page').prop('disabled',false);
            if(r.success) seoToast('Page created: '+r.data.page_url,'ok');
            else seoToast(r.data.message||'Failed.','err');
        });
    });
    // Style provider labels when a radio is selected
    $(document).on('change', '#s-provider-cards input[type="radio"]', function() {
        var val = $(this).val();
        $('#s-provider-cards label').each(function(){
            $(this).css({'border-color':'var(--c-border)','background':'var(--c-surf)'});
        });
        $('#s-prov-label-'+val).css({'border-color':'var(--c-primary)','background':'rgba(99,102,241,.06)'});
    });

    $('#seo-settings-save-btn').on('click',function(){
        var $btn=$(this).text('Saving…').prop('disabled',true);
        var data={action:'seo_dash_save_settings',nonce:seoDash.nonce,
            brand_name:$('#s-brand-name').val(),
            agency_url:$('#s-agency-url').val(),
            support_email:$('#s-support-email').val(),
            footer_text:$('#s-footer-text').val(),
            admin_notify_emails:$('#s-notify-emails').val(),
            brand_logo:$('#s-brand-logo').val(),
            brand_logo_dark:$('#s-brand-logo-dark').val(),
        };
        data.deepseek_model=$('#s-deepseek-model').val();
        data.groq_model=$('#s-groq-model').val();
        data.cerebras_model=$('#s-cerebras-model').val();
        data.gemini_model=$('#s-gemini-model').val();
        // Schedulers
        data.auto_monthly_sync = $('#s-auto-monthly-sync').is(':checked') ? 1 : 0;
        data.auto_sitemap_recrawl = $('#s-auto-sitemap-recrawl').is(':checked') ? 1 : 0;
        data.auto_sitemap_freq = $('#s-auto-sitemap-freq').val();
        // Read active_provider directly from the checked radio (most reliable)
        var ap = $('input[name="s_active_provider"]:checked').val();
        if(ap) data.active_provider = ap;
        var dsk=$('#s-deepseek-key').val().trim();
        if(dsk) data.deepseek_api_key=dsk;
        var gk=$('#s-groq-key').val().trim();
        if(gk) data.groq_api_key=gk;
        var ck=$('#s-cerebras-key').val().trim();
        if(ck) data.cerebras_api_key=ck;
        var gmk=$('#s-gemini-key').val().trim();
        if(gmk) data.gemini_api_key=gmk;
        $.post(seoDash.ajax,data,function(r){
            $btn.text('💾 Save Settings').prop('disabled',false);
            seoToast(r.data&&r.data.message?r.data.message:(r.success?'Settings saved.':'Error.'),r.success?'ok':'err');
        });
    });

    // Schedulers Manual Trigger Handlers
    $('#seo-trigger-monthly-sync-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this).text('Queuing…').prop('disabled', true);
        var $status = $('#seo-monthly-sync-status').text('').css('color', 'inherit');
        $.post(seoDash.ajax, { action: 'seo_dash_trigger_monthly_sync', nonce: seoDash.nonce }, function(r) {
            $btn.text('▶ Run Monthly Analytics Fetch Now').prop('disabled', false);
            if (r.success) {
                $status.text('✅ ' + (r.data && r.data.message ? r.data.message : 'Queued successfully')).css('color', 'var(--c-green)');
            } else {
                $status.text('❌ ' + (r.data && r.data.message ? r.data.message : 'Failed.')).css('color', 'var(--c-red)');
            }
        }).fail(function() {
            $btn.text('▶ Run Monthly Analytics Fetch Now').prop('disabled', false);
            $status.text('❌ Request failed').css('color', 'var(--c-red)');
        });
    });

    $('#seo-trigger-sitemap-recrawl-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this).text('Crawling…').prop('disabled', true);
        var $status = $('#seo-sitemap-recrawl-status').text('').css('color', 'inherit');
        $.post(seoDash.ajax, { action: 'seo_dash_trigger_sitemap_recrawl', nonce: seoDash.nonce }, function(r) {
            $btn.text('▶ Run Sitemap Re-Crawl Now').prop('disabled', false);
            if (r.success) {
                $status.text('✅ ' + (r.data && r.data.message ? r.data.message : 'Sitemap recrawl completed')).css('color', 'var(--c-green)');
            } else {
                $status.text('❌ ' + (r.data && r.data.message ? r.data.message : 'Failed.')).css('color', 'var(--c-red)');
            }
        }).fail(function() {
            $btn.text('▶ Run Sitemap Re-Crawl Now').prop('disabled', false);
            $status.text('❌ Request failed').css('color', 'var(--c-red)');
        });
    });

    /* ── Gmail OAuth ─────────────────────────────────────────────────── */

    // Listen for the OAuth popup to send back success message — just reload the page
    window.addEventListener('message', function(e) {
        if (!e.data || e.data.seo_dash_oauth !== 'connected') return;
        var email = e.data.email || '';
        seoToast('✅ Gmail connected — ' + email + '. Refreshing…', 'ok');
        setTimeout(function(){ location.reload(); }, 1000);
    });

    // Connect button — opens Google OAuth popup
    function bindOauthButtons() {
        $('#seo-oauth-connect-btn').off('click').on('click', function() {
            var $btn = $(this).text('Opening Google…').prop('disabled', true);
            $.post(seoDash.ajax, { action: 'seo_dash_gmail_oauth_start', nonce: seoDash.nonce }, function(r) {
                $btn.text('Connect Gmail Account').prop('disabled', false);
                if (r.success && r.data && r.data.url) {
                    var w = 520, h = 620;
                    var left = (screen.width/2) - (w/2);
                    var top  = (screen.height/2) - (h/2);
                    window.open(r.data.url, 'gmailOAuth',
                        'width='+w+',height='+h+',top='+top+',left='+left+',scrollbars=yes');
                } else {
                    seoToast('Failed to start OAuth flow.', 'err');
                }
            }).fail(function() {
                $btn.text('Connect Gmail Account').prop('disabled', false);
                seoToast('Request failed.', 'err');
            });
        });

        // Change account — keeps tokens but opens new OAuth flow
        $('#seo-oauth-change-btn').off('click').on('click', function() {
            var $btn = $(this).text('Opening Google…').prop('disabled', true);
            $.post(seoDash.ajax, { action: 'seo_dash_gmail_oauth_start', nonce: seoDash.nonce }, function(r) {
                $btn.text('🔄 Change Account').prop('disabled', false);
                if (r.success && r.data && r.data.url) {
                    var w = 520, h = 620;
                    var left = (screen.width/2) - (w/2);
                    var top  = (screen.height/2) - (h/2);
                    window.open(r.data.url, 'gmailOAuth', 'width='+w+',height='+h+',top='+top+',left='+left+',scrollbars=yes');
                } else {
                    seoToast('Failed to start OAuth flow.', 'err');
                }
            }).fail(function() {
                $btn.text('🔄 Change Account').prop('disabled', false);
                seoToast('Request failed.', 'err');
            });
        });

        // Disconnect — fully removes OAuth tokens
        $('#seo-oauth-disconnect-btn').off('click').on('click', function() {
            if (!confirm('Disconnect Gmail account completely? Email sending will stop until you reconnect.')) return;
            var $btn = $(this).text('Disconnecting…').prop('disabled', true);
            $.post(seoDash.ajax, { action: 'seo_dash_gmail_oauth_disconnect', nonce: seoDash.nonce }, function(r) {
                seoToast(r.data && r.data.message ? r.data.message : 'Disconnected.', r.success ? 'ok' : 'err');
                if (r.success) setTimeout(function(){ location.reload(); }, 800);
                else $btn.text('🔌 Disconnect').prop('disabled', false);
            });
        });


        // Save OAuth settings (display name + test recipient + notify emails)
        $('#seo-oauth-save-name-btn').off('click').on('click', function() {
            var $btn = $(this).text('Saving…').prop('disabled', true);
            $.post(seoDash.ajax, {
                action: 'seo_dash_save_email_settings',
                nonce: seoDash.nonce,
                smtp_mode: 'gmail_oauth',
                smtp_from_name: $('#seo-oauth-from-name').val().trim(),
                oauth_test_email: $('#seo-oauth-test-to').val().trim()
            }, function(r) {
                $btn.text('💾 Save Settings').prop('disabled', false);
                seoToast(r.success ? '✅ Settings saved.' : 'Error saving.', r.success ? 'ok' : 'err');
            });
        });

        // Pass custom test-to email when sending test
        $('#seo-oauth-test-btn').off('click').on('click', function() {
            var $btn = $(this).text('Sending…').prop('disabled', true);
            var $r = $('#seo-oauth-test-result').text('').css('color','inherit');
            var testTo = $('#seo-oauth-test-to').val().trim();
            $.post(seoDash.ajax, { action: 'seo_dash_gmail_oauth_test', nonce: seoDash.nonce, test_to: testTo }, function(r) {
                $btn.text('✉️ Send Test Email').prop('disabled', false);
                var msg = r.data && r.data.message ? r.data.message : (r.success ? 'Sent!' : 'Failed.');
                $r.text(msg).css('color', r.success ? 'var(--c-green)' : 'var(--c-red)');
                seoToast(msg, r.success ? 'ok' : 'err');
            }).fail(function() {
                $btn.text('✉️ Send Test Email').prop('disabled', false);
                $r.text('❌ Request failed.').css('color','var(--c-red)');
            });
        });
    }
    bindOauthButtons();

    // SMTP override toggle — enable/disable fields instantly, then save
    $('#seo-smtp-override-toggle').on('change', function() {
        var enabled = $(this).is(':checked') ? '1' : '0';

        // Update UI immediately — don't wait for server
        if (enabled === '1') {
            $('#seo-smtp-fields-wrap').removeClass('disabled');
            $('#seo-smtp-toggle-label').text('Enabled');
        } else {
            $('#seo-smtp-fields-wrap').addClass('disabled');
            $('#seo-smtp-toggle-label').text('Disabled');
        }

        // Save to server
        $.post(seoDash.ajax, {
            action: 'seo_dash_save_email_settings',
            nonce: seoDash.nonce,
            smtp_mode: enabled === '1' ? '<?php echo esc_js($smtp_mode !== "gmail_oauth" ? $smtp_mode : "gmail"); ?>' : 'gmail_oauth',
            smtp_override_enabled: enabled
        }, function(r) {
            seoToast(r.success ? (enabled==='1' ? '⚙️ Custom SMTP enabled.' : '✅ Gmail OAuth active.') : 'Error saving.', r.success ? 'ok' : 'err');
        });
    });

    /* ── Legacy SMTP (inside <details> fallback) ─────────────────────── */
    var smtpActiveMode = '<?php echo esc_js( $smtp_mode ); ?>';

    $('.seo-smtp-ptab').on('click', function(){
        var tab = $(this).data('smtp-tab');
        $('.seo-smtp-ptab').removeClass('active');
        $(this).addClass('active');
        $('.seo-smtp-pane').hide();
        $('.seo-smtp-pane[data-smtp-pane="'+tab+'"]').show();
        if (tab === 'gmail' || tab === 'other') smtpActiveMode = tab;
    });

    $('.seo-smtp-toggle-pass').on('click', function(){
        var $f = $('#'+$(this).data('target'));
        var show = $f.attr('type') === 'password';
        $f.attr('type', show ? 'text' : 'password');
        $(this).text(show ? 'Hide' : 'Show');
    });

    function smtpCollectData(){
        var data = { smtp_mode: smtpActiveMode };
        if (smtpActiveMode === 'gmail') {
            data.smtp_username   = $('#smtp-gmail-username').val().trim();
            data.smtp_from_name  = $('#smtp-gmail-from-name').val().trim();
            data.smtp_from_email = $('#smtp-gmail-from-email').val().trim();
            var gp = $('#smtp-gmail-password').val().trim();
            if (gp) data.smtp_password = gp;
        } else {
            data.smtp_host       = $('#smtp-other-host').val().trim();
            data.smtp_port       = $('#smtp-other-port').val();
            data.smtp_username   = $('#smtp-other-username').val().trim();
            data.smtp_from_name  = $('#smtp-other-from-name').val().trim();
            data.smtp_from_email = $('#smtp-other-from-email').val().trim();
            var op = $('#smtp-other-password').val().trim();
            if (op) data.smtp_password = op;
        }
        return data;
    }

    $('#seo-save-email-settings-btn').on('click', function(){
        var $btn = $(this).text('Saving…').prop('disabled', true);
        var data = $.extend({ action: 'seo_dash_save_email_settings', nonce: seoDash.nonce }, smtpCollectData());
        $.post(seoDash.ajax, data, function(r){
            $btn.text('💾 Save SMTP Settings').prop('disabled', false);
            seoToast(r.data && r.data.message ? r.data.message : (r.success ? 'Saved.' : 'Error.'), r.success ? 'ok' : 'err');
            if (r.success) $('#smtp-gmail-password, #smtp-other-password').val('');
        }).fail(function(){
            $btn.text('💾 Save SMTP Settings').prop('disabled', false);
            seoToast('Request failed.', 'err');
        });
    });

    $('#seo-smtp-test-btn-legacy').on('click', function(){
        var $btn = $(this).text('Sending…').prop('disabled', true);
        var $r = $('#seo-smtp-test-result').text('').css('color','inherit');
        var data = $.extend({ action: 'seo_dash_send_test_email', nonce: seoDash.nonce }, smtpCollectData());
        $.post(seoDash.ajax, data, function(r){
            $btn.text('✉️ Test').prop('disabled', false);
            var msg = r.data && r.data.message ? r.data.message : (r.success ? 'Sent!' : 'Failed.');
            $r.text(msg).css('color', r.success ? 'var(--c-green)' : 'var(--c-red)');
        }).fail(function(){
            $btn.text('✉️ Test').prop('disabled', false);
            $r.text('❌ Request failed.').css('color','var(--c-red)');
        });
    });

})(jQuery);
});
</script>