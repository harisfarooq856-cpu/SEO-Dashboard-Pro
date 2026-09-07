<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="seo-page seo-page-documentation">
<style>
/* ── Documentation Page Styles ─────────────────────────────────────── */
.seo-docs-wrap {
    max-width: 960px;
    margin: 0 auto;
    padding: 32px 24px 80px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.seo-docs-hero {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    border-radius: 16px;
    padding: 40px 36px;
    margin-bottom: 36px;
    color: #fff;
}
.seo-docs-hero h1 {
    margin: 0 0 8px;
    font-size: 28px;
    font-weight: 700;
    color: #fff;
}
.seo-docs-hero p {
    margin: 0;
    font-size: 15px;
    color: rgba(255,255,255,0.75);
    max-width: 560px;
}
.seo-docs-toc {
    background: var(--seo-card, #1e1e2e);
    border: 1px solid var(--seo-border, #2a2a3e);
    border-radius: 12px;
    padding: 24px 28px;
    margin-bottom: 32px;
}
.seo-docs-toc h3 {
    margin: 0 0 14px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--seo-muted, #888);
}
.seo-docs-toc ol {
    margin: 0;
    padding-left: 20px;
    columns: 2;
    column-gap: 24px;
}
.seo-docs-toc li {
    margin-bottom: 6px;
}
.seo-docs-toc a {
    color: var(--seo-accent, #6c8ebf);
    text-decoration: none;
    font-size: 13.5px;
    transition: color .15s;
}
.seo-docs-toc a:hover { color: var(--seo-accent-hover, #89aed8); text-decoration: underline; }

.seo-doc-section {
    background: var(--seo-card, #1e1e2e);
    border: 1px solid var(--seo-border, #2a2a3e);
    border-radius: 14px;
    margin-bottom: 28px;
    overflow: hidden;
}
.seo-doc-section-header {
    padding: 22px 28px 18px;
    border-bottom: 1px solid var(--seo-border, #2a2a3e);
    display: flex;
    align-items: center;
    gap: 12px;
}
.seo-doc-section-header .doc-icon {
    font-size: 24px;
    line-height: 1;
}
.seo-doc-section-header h2 {
    margin: 0 0 3px;
    font-size: 17px;
    font-weight: 700;
    color: var(--seo-text, #e8e8f0);
}
.seo-doc-section-header p {
    margin: 0;
    font-size: 13px;
    color: var(--seo-muted, #888);
}
.seo-doc-section-body {
    padding: 24px 28px;
}
.seo-doc-step {
    display: flex;
    gap: 16px;
    margin-bottom: 22px;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--seo-border, #2a2a3e);
}
.seo-doc-step:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}
.seo-step-num {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--seo-accent, #6c8ebf);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 2px;
}
.seo-step-content { flex: 1; min-width: 0; }
.seo-step-content h4 {
    margin: 0 0 6px;
    font-size: 14.5px;
    font-weight: 600;
    color: var(--seo-text, #e8e8f0);
}
.seo-step-content p {
    margin: 0 0 10px;
    font-size: 13.5px;
    color: var(--seo-muted, #aaa);
    line-height: 1.6;
}
.seo-step-content p:last-child { margin-bottom: 0; }
.seo-tip {
    background: rgba(108,142,191,.12);
    border-left: 3px solid var(--seo-accent, #6c8ebf);
    border-radius: 0 6px 6px 0;
    padding: 10px 14px;
    margin-top: 10px;
    font-size: 13px;
    color: var(--seo-text, #ccc);
    line-height: 1.55;
}
.seo-tip strong { color: var(--seo-accent, #89aed8); }
.seo-warn {
    background: rgba(234,179,8,.10);
    border-left: 3px solid #eab308;
    border-radius: 0 6px 6px 0;
    padding: 10px 14px;
    margin-top: 10px;
    font-size: 13px;
    color: var(--seo-text, #ccc);
    line-height: 1.55;
}
.seo-warn strong { color: #eab308; }
.seo-code {
    display: inline-block;
    background: rgba(0,0,0,.35);
    color: #a8d8a8;
    font-family: 'SFMono-Regular', Consolas, monospace;
    font-size: 12.5px;
    padding: 2px 7px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,.08);
}
.seo-badge {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.seo-badge-blue  { background: rgba(108,142,191,.2); color: #89aed8; }
.seo-badge-green { background: rgba(34,197,94,.15);  color: #4ade80; }
.seo-badge-yellow{ background: rgba(234,179,8,.15);  color: #fbbf24; }

/* Light-mode overrides */
[data-seo-theme="light"] .seo-docs-toc,
[data-seo-theme="light"] .seo-doc-section {
    background: #fff;
    border-color: #e5e7eb;
}
[data-seo-theme="light"] .seo-doc-section-header { border-color: #f3f4f6; }
[data-seo-theme="light"] .seo-doc-step          { border-color: #f3f4f6; }
[data-seo-theme="light"] .seo-doc-section-header h2,
[data-seo-theme="light"] .seo-step-content h4   { color: #111; }
[data-seo-theme="light"] .seo-doc-section-header p,
[data-seo-theme="light"] .seo-step-content p,
[data-seo-theme="light"] .seo-docs-toc h3       { color: #555; }
[data-seo-theme="light"] .seo-docs-toc a        { color: #3b6fb5; }
[data-seo-theme="light"] .seo-tip               { color: #333; }
[data-seo-theme="light"] .seo-warn              { color: #333; }
[data-seo-theme="light"] .seo-code              { background: #f0f0f0; color: #207820; border-color: #ddd; }

@media (max-width: 640px) {
    .seo-docs-toc ol { columns: 1; }
    .seo-docs-wrap   { padding: 20px 16px 60px; }
    .seo-docs-hero   { padding: 28px 20px; }
}
</style>

<div class="seo-docs-wrap">

    <!-- Hero -->
    <div class="seo-docs-hero">
        <h1>📚 Plugin Documentation</h1>
        <p>Everything you need to know about SEO Client Reporting Dashboard Pro — step by step, for beginners and pros alike.</p>
    </div>

    <!-- Table of Contents -->
    <div class="seo-docs-toc">
        <h3>Table of Contents</h3>
        <ol>
            <li><a href="#doc-first-setup">First-Time Setup</a></li>
            <li><a href="#doc-reports">Creating Reports</a></li>
            <li><a href="#doc-clients">Managing Clients</a></li>
            <li><a href="#doc-sitemap">Adding Sitemap URLs</a></li>
            <li><a href="#doc-integrations">Google Integrations</a></li>
            <li><a href="#doc-ga">Google Analytics Setup</a></li>
            <li><a href="#doc-gsc">Search Console Setup</a></li>
            <li><a href="#doc-design">Branding</a></li>
            <li><a href="#doc-settings">Plugin Settings</a></li>
            <li><a href="#doc-ai">AI Features (Groq)</a></li>
            <li><a href="#doc-client-portal">Client Portal</a></li>
            <li><a href="#doc-troubleshoot">Troubleshooting</a></li>
        </ol>
    </div>

    <!-- ── 1. First-Time Setup ───────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-first-setup">
        <div class="seo-doc-section-header">
            <span class="doc-icon">🚀</span>
            <div>
                <h2>1. First-Time Setup</h2>
                <p>Get the plugin running in under 5 minutes</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Install &amp; Activate the Plugin</h4>
                    <p>Upload the plugin zip via <strong>WordPress Admin → Plugins → Add New → Upload Plugin</strong>. Once uploaded, click <strong>Activate Plugin</strong>. The plugin automatically creates two pages on your site: an <em>SEO Admin Dashboard</em> page and an <em>SEO Dashboard</em> (client portal) page.</p>
                    <div class="seo-tip"><strong>✅ Good to know:</strong> You don't need to configure any pages manually — the plugin does it for you on first activation.</div>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Open the Admin Dashboard</h4>
                    <p>In your WordPress sidebar, click <strong>SEO Dashboard</strong>. You'll see a panel with the URL of your admin dashboard and an <strong>Open Admin Dashboard →</strong> button. Click it to enter the full frontend admin panel where all management happens.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Set Your Brand Name &amp; Logo</h4>
                    <p>Inside the admin dashboard, go to <strong>Settings</strong> and find the <strong>Agency Branding</strong> section. Enter your agency or business name and optionally a logo URL. This appears on all client-facing portals.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">4</div>
                <div class="seo-step-content">
                    <h4>Check System Info</h4>
                    <p>Back in the WordPress Admin under <strong>SEO Dashboard</strong>, scroll down to the <em>System Info</em> table. Make sure all items show a green ✅. If Database Tables shows ❌, deactivate and reactivate the plugin to rebuild them.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 2. Creating Reports ───────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-reports">
        <div class="seo-doc-section-header">
            <span class="doc-icon">📊</span>
            <div>
                <h2>2. Creating Reports</h2>
                <p>Reports are the core of the plugin — one per client website</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Go to the Reports Section</h4>
                    <p>In the Admin Dashboard top navigation, click <strong>Reports</strong>. This shows a list of all your existing reports (empty at first).</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Click "New Report"</h4>
                    <p>Press the <strong>+ New Report</strong> button (top right of the reports list). A modal dialog will open asking for report details.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Fill in Report Details</h4>
                    <p>Enter the <strong>Report Name</strong> (e.g. "ABC Company – Monthly SEO"), the <strong>website domain</strong>, and any other fields shown. Then click <strong>Create Report</strong>.</p>
                    <div class="seo-tip"><strong>💡 Tip:</strong> Use a clear naming convention like "ClientName – Month Year" so you can find reports quickly later.</div>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">4</div>
                <div class="seo-step-content">
                    <h4>Open the Report &amp; Explore Tabs</h4>
                    <p>Click the report name to open it. You'll see multiple tabs: <strong>Overview, Analytics, Search Console, Backlinks, Leads, Documents, Clients, Integrations,</strong> and more. Fill in each section as you add data.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 3. Managing Clients ───────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-clients">
        <div class="seo-doc-section-header">
            <span class="doc-icon">👥</span>
            <div>
                <h2>3. Managing Clients</h2>
                <p>Add clients and give them access to their private portal</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Go to Clients</h4>
                    <p>Click <strong>Clients</strong> in the top navigation of the Admin Dashboard. You'll see your client list.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Add a New Client</h4>
                    <p>Click <strong>+ Add Client</strong>. Fill in: <strong>Client Name</strong>, <strong>Email Address</strong>, and optionally assign them to a report. The plugin will automatically create a WordPress user with the <em>SEO Client</em> role and a personal dashboard page for them.</p>
                    <div class="seo-tip"><strong>📧 Good to know:</strong> The client receives their login credentials via email automatically. Share the <em>Client Portal URL</em> shown on their profile with them.</div>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Assign the Client to a Report</h4>
                    <p>Inside a Report → go to the <strong>Clients</strong> tab → select the client from the dropdown and click <strong>Assign</strong>. The client can now log in and see that report's data in their portal.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">4</div>
                <div class="seo-step-content">
                    <h4>View a Client's Portal URL</h4>
                    <p>In the Clients list, each client has a <strong>Portal URL</strong> column. Copy this URL and send it to your client — it's their private, password-protected dashboard.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 4. Sitemap URLs ───────────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-sitemap">
        <div class="seo-doc-section-header">
            <span class="doc-icon">🗺️</span>
            <div>
                <h2>4. Adding Sitemap URLs</h2>
                <p>Import and track pages from your client's sitemap</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Open a Report</h4>
                    <p>From the Reports list, click on the report you want to add sitemap data to.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Go to the Technical or Database Tab</h4>
                    <p>Inside the report, click the <strong>Technical</strong> tab (or <strong>Database</strong> depending on your version). Look for the <strong>Sitemap</strong> section — it's usually labeled "Sitemap URLs" or "Import Sitemap".</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Enter the Sitemap URL</h4>
                    <p>In the Sitemap URL field, type or paste the full URL of the client's XML sitemap. It usually looks like: <span class="seo-code">https://example.com/sitemap.xml</span></p>
                    <div class="seo-tip"><strong>💡 Common sitemap locations:</strong><br>
                        • <span class="seo-code">/sitemap.xml</span> — WordPress default<br>
                        • <span class="seo-code">/sitemap_index.xml</span> — Yoast SEO / RankMath<br>
                        • <span class="seo-code">/page-sitemap.xml</span> — pages only<br>
                        • <span class="seo-code">/post-sitemap.xml</span> — posts only
                    </div>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">4</div>
                <div class="seo-step-content">
                    <h4>Click "Fetch Sitemap" / "Import"</h4>
                    <p>Press the fetch or import button next to the sitemap field. The plugin will retrieve the XML file and import all the page URLs into the report. This may take a few seconds for large sitemaps.</p>
                    <div class="seo-warn"><strong>⚠️ Note:</strong> The sitemap URL must be publicly accessible. If the site is password protected or uses a robots.txt block, the fetch will fail. Ask your client to whitelist your server IP if needed.</div>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">5</div>
                <div class="seo-step-content">
                    <h4>Review Imported URLs</h4>
                    <p>After import, you'll see a list of all URLs from the sitemap. You can review them, remove unwanted URLs, and then save. These URLs are used throughout the report for tracking and analysis.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 5. Integrations ───────────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-integrations">
        <div class="seo-doc-section-header">
            <span class="doc-icon">🔗</span>
            <div>
                <h2>5. Connecting Integrations</h2>
                <p>Link Google and other services for live data</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Open the Integrations Page</h4>
                    <p>Click <strong>Integrations</strong> in the top navigation. Here you manage all your Google API connections. You can have multiple integration accounts (e.g. one per client Google account).</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Add a New Integration</h4>
                    <p>Click <strong>+ New Integration</strong>. Choose the integration type (e.g. <em>Google Analytics 4</em> or <em>Google Search Console</em>). You will be asked to provide OAuth credentials or an API key depending on the service.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Assign Integration to a Report</h4>
                    <p>Once an integration is saved, open a Report → go to the <strong>Integrations</strong> tab → select the integration from the dropdown for the relevant section (e.g. Analytics, Search Console) and click <strong>Save</strong>.</p>
                    <div class="seo-tip"><strong>💡 Tip:</strong> You can assign different integration accounts to different reports — useful when managing clients who each have their own Google accounts.</div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 6. Google Analytics ───────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-ga">
        <div class="seo-doc-section-header">
            <span class="doc-icon">📈</span>
            <div>
                <h2>6. Google Analytics 4 Setup</h2>
                <p>Pull live GA4 traffic data into reports</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Create a Google Cloud Project</h4>
                    <p>Go to <a href="https://console.cloud.google.com" target="_blank" style="color:var(--seo-accent,#6c8ebf);">console.cloud.google.com</a> → create a new project (or use an existing one) → give it a name like "SEO Dashboard".</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Enable the Google Analytics Data API</h4>
                    <p>In Google Cloud Console → <strong>APIs &amp; Services → Library</strong> → search for <em>"Google Analytics Data API"</em> → click it → click <strong>Enable</strong>.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Create OAuth Credentials</h4>
                    <p>Go to <strong>APIs &amp; Services → Credentials → Create Credentials → OAuth 2.0 Client ID</strong>. Set Application Type to <em>Web Application</em>. Add your site's redirect URI (shown in the plugin's Integrations page).</p>
                    <div class="seo-tip"><strong>💡 The redirect URI</strong> is shown in the "New Integration" modal — copy it exactly into Google Cloud Console's Authorised Redirect URIs field.</div>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">4</div>
                <div class="seo-step-content">
                    <h4>Copy Client ID &amp; Secret to Plugin</h4>
                    <p>After creating the OAuth app, Google shows you a <strong>Client ID</strong> and <strong>Client Secret</strong>. Copy both into the integration form in the plugin and click <strong>Save &amp; Authorise</strong>. You'll be redirected to Google to approve access.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">5</div>
                <div class="seo-step-content">
                    <h4>Select the GA4 Property</h4>
                    <p>After authorization, return to the plugin. In your report's <strong>Analytics</strong> tab, select the GA4 property from the dropdown. Click <strong>Fetch Data</strong> to pull in sessions, users, traffic sources, and more.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 7. Google Search Console ─────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-gsc">
        <div class="seo-doc-section-header">
            <span class="doc-icon">🔍</span>
            <div>
                <h2>7. Google Search Console Setup</h2>
                <p>Import keyword rankings, impressions, and clicks</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Enable the Search Console API</h4>
                    <p>In Google Cloud Console (same project as GA) → <strong>APIs &amp; Services → Library</strong> → search <em>"Google Search Console API"</em> → Enable it.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Use the Same OAuth Credentials</h4>
                    <p>You can use the same Client ID and Secret you created for Google Analytics. Just add a new Integration in the plugin and select <strong>Search Console</strong> as the type, then authorise with the same Google account that has access to Search Console for the client's site.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Select the Site Property</h4>
                    <p>In the report's <strong>Search Console</strong> tab, select the site property from the dropdown (e.g. <span class="seo-code">sc-domain:example.com</span> or <span class="seo-code">https://example.com/</span>). Click <strong>Fetch Data</strong> to pull keywords, impressions, clicks, and average position.</p>
                    <div class="seo-warn"><strong>⚠️ Note:</strong> The Google account you authorise must be a verified owner or full user in Search Console for the client's site. Ask your client to add your Google account as a user in their Search Console.</div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 8. Branding ──────────────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-design">
        <div class="seo-doc-section-header">
            <span class="doc-icon">🎨</span>
            <div>
                <h2>8. Branding</h2>
                <p>White-label the client portal with your agency's brand</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Open Settings</h4>
                    <p>Click <strong>Settings</strong> in the Admin Dashboard top navigation, then find the <strong>Agency Branding</strong> section.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Set Your Brand Name</h4>
                    <p>Enter your agency or business name in the <strong>Brand Name</strong> field. This appears in the top-left logo area of the client portal and admin panel.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Set a Logo URL</h4>
                    <p>Paste a URL to your logo image (PNG or SVG recommended, transparent background) in the <strong>Brand Logo URL</strong> field. The logo will replace the default icon in the navigation bar.</p>
                    <div class="seo-tip"><strong>💡 Best size:</strong> Use a logo around 200×50px or wider ratio. Avoid very tall logos as they affect the navbar height.</div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 9. Settings ───────────────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-settings">
        <div class="seo-doc-section-header">
            <span class="doc-icon">⚙️</span>
            <div>
                <h2>9. Plugin Settings</h2>
                <p>Configure global plugin behaviour and API keys</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Open Settings</h4>
                    <p>Click <strong>Settings</strong> in the Admin Dashboard top navigation. This page controls global plugin options.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Google Sheets Integration</h4>
                    <p>If you want to export report data to Google Sheets, enter your <strong>Google Sheets API key</strong> here. Then in any report, you'll find a "Export to Sheets" button in the relevant tabs.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Email Notifications</h4>
                    <p>Configure whether clients receive automated email notifications (e.g. when a report is updated). You can also set a custom <em>From Name</em> and <em>From Email</em> for outgoing emails.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">4</div>
                <div class="seo-step-content">
                    <h4>Save Settings</h4>
                    <p>Always click the <strong>Save Settings</strong> button after making changes. A green success notice confirms the save.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 10. AI Features ───────────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-ai">
        <div class="seo-doc-section-header">
            <span class="doc-icon">🤖</span>
            <div>
                <h2>10. AI Features (Groq)</h2>
                <p>Generate AI-powered SEO insights and content using Groq</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Get a Free Groq API Key</h4>
                    <p>Visit <a href="https://console.groq.com" target="_blank" style="color:var(--seo-accent,#6c8ebf);">console.groq.com</a> and create a free account. Go to <strong>API Keys</strong> and generate a new key. Groq is free for generous usage limits.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Add the Key to Plugin Settings</h4>
                    <p>In the Admin Dashboard → <strong>Settings</strong>, find the <strong>Groq API Key</strong> field and paste your key. Click <strong>Save Settings</strong>.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Use AI Inside Reports</h4>
                    <p>Open any report and look for <strong>AI Generate</strong> or <strong>Ask AI</strong> buttons throughout the report tabs. You can generate SEO summaries, recommendations, content briefs, and analysis with one click.</p>
                    <div class="seo-tip"><strong>💡 Tip:</strong> The AI uses data already loaded in the report (keywords, analytics, etc.) to give context-aware responses. The more data you've added to the report, the smarter the AI output.</div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 11. Client Portal ─────────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-client-portal">
        <div class="seo-doc-section-header">
            <span class="doc-icon">🌐</span>
            <div>
                <h2>11. Client Portal</h2>
                <p>What your clients see and how to share access</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>The Client Portal URL</h4>
                    <p>Your client portal lives at a WordPress page on your site — typically something like <span class="seo-code">https://yoursite.com/seo-dashboard/</span>. This is set automatically when the plugin is activated. Find it in <strong>WordPress Admin → SEO Dashboard → System Info</strong>.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Share Login Details with Your Client</h4>
                    <p>When you create a client in the plugin, WordPress automatically creates a user account for them. Send the client: (1) the portal URL, (2) their username/email, and (3) a password reset link via <strong>WordPress Admin → Users → send password reset</strong>.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>What Clients Can See</h4>
                    <p>Clients log in and see <em>only their own data</em>: their assigned reports, analytics, keyword rankings, leads, and documents. They cannot see other clients' data. The portal is fully branded with your logo and colors.</p>
                    <div class="seo-tip"><strong>✅ Privacy:</strong> Each client's data is isolated. A client with one report cannot view reports assigned to other clients.</div>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">4</div>
                <div class="seo-step-content">
                    <h4>Preview the Portal Yourself</h4>
                    <p>In the Admin Dashboard, click the <strong>Client Portal →</strong> link in the top-right navigation. This opens the portal in a new tab so you can see exactly what your clients see.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- ── 12. Troubleshooting ───────────────────────────────────────── -->
    <div class="seo-doc-section" id="doc-troubleshoot">
        <div class="seo-doc-section-header">
            <span class="doc-icon">🛠️</span>
            <div>
                <h2>12. Troubleshooting</h2>
                <p>Common issues and how to fix them quickly</p>
            </div>
        </div>
        <div class="seo-doc-section-body">

            <div class="seo-doc-step">
                <div class="seo-step-num">1</div>
                <div class="seo-step-content">
                    <h4>Database Tables Missing (❌ in System Info)</h4>
                    <p>Go to <strong>WordPress Admin → Plugins</strong>, deactivate the plugin, then reactivate it. This runs the database installer and creates all missing tables.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">2</div>
                <div class="seo-step-content">
                    <h4>Admin Dashboard Page Returns 404</h4>
                    <p>Go to <strong>WordPress Admin → Settings → Permalinks</strong> and click <strong>Save Changes</strong> (without changing anything). This flushes rewrite rules and fixes most 404 errors on plugin pages.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">3</div>
                <div class="seo-step-content">
                    <h4>Google API Returns Error / Data Not Loading</h4>
                    <p>Check that: (1) the correct API is enabled in Google Cloud Console, (2) the redirect URI matches exactly, (3) the OAuth app is not in "Testing" mode or — if it is — your account is added as a test user. Re-authorise the integration by clicking <strong>Reconnect</strong> in the Integrations page.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">4</div>
                <div class="seo-step-content">
                    <h4>Sitemap Fetch Fails</h4>
                    <p>Make sure the sitemap URL is publicly accessible (open it in a browser while logged out). Check that your server can make outbound HTTP requests (some hosts block this — ask your host to whitelist outbound cURL). Also verify the sitemap returns valid XML.</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">5</div>
                <div class="seo-step-content">
                    <h4>Client Cannot Log In</h4>
                    <p>Confirm the client's WordPress user exists at <strong>WordPress Admin → Users</strong> and has the <em>SEO Client</em> role. Send a password reset from there. Also confirm they're using the correct portal URL (not the WordPress admin URL).</p>
                </div>
            </div>

            <div class="seo-doc-step">
                <div class="seo-step-num">6</div>
                <div class="seo-step-content">
                    <h4>Check the Activity Log</h4>
                    <p>In the Admin Dashboard, click <strong>Log</strong> in the top navigation. This shows a full activity log of all actions taken in the plugin, including errors, API calls, and data fetches — very useful for diagnosing issues.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer note -->
    <div style="text-align:center;padding:24px 0 0;color:var(--seo-muted,#888);font-size:13px;">
        SEO Client Reporting Dashboard Pro &nbsp;·&nbsp; v<?php echo esc_html( SEO_DASH_VERSION ); ?> &nbsp;·&nbsp; Need more help? Check the Activity Log or contact your plugin developer.
    </div>

</div>
</div>
