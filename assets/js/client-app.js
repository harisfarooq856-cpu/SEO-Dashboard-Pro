/* SEO Dashboard Pro v5 — client-app.js */
(function ($) {
    'use strict';

    if (!window.seoDashClient) return;
    var C = window.seoDashClient;

    /* ── Generic Page Detail type-filter + live search helper ──────────
       Builds: a dynamic sitemap-type <select> (in #{prefix}-type-toggle),
       a live search box (#{prefix}-search / #{prefix}-results / #{prefix}-search-clear)
       and a page <select> (#{prefix}-url-select) — mirrors the Analytics /
       Search Console "Page Detail" filter UX. */
    window.seoInitPageDetailFilter = function (cfg) {
        var tab        = cfg.tab;
        var prefix     = cfg.prefix;
        var buildExtra = cfg.buildExtra || function () { return null; };
        var onSelect   = cfg.onSelect || function () {};

        var allItems    = {}; // type -> [{url,title}]
        var dataMap      = {}; // url -> {url,title,type,extra,row}
        var currentType  = 'all';

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
            'other':    { icon: '🔗', label: 'Other' }
        };
        function getTypeConfig(t) {
            return typeConfig[t] || { icon: '🔗', label: t.charAt(0).toUpperCase() + t.slice(1) };
        }
        function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        function escAttr(s) { return String(s).replace(/"/g,'&quot;'); }
        function hlMatch(text, query) {
            if (!query) return escHtml(text);
            var idx = text.toLowerCase().indexOf(query);
            if (idx === -1) return escHtml(text);
            return escHtml(text.slice(0, idx))
                 + '<mark style="background:rgba(139,92,246,0.25); color:var(--cc-text); border-radius:2px;">' + escHtml(text.slice(idx, idx + query.length)) + '</mark>'
                 + escHtml(text.slice(idx + query.length));
        }

        function buildTypeSelect(types) {
            var $container = document.getElementById(prefix + '-type-toggle');
            if (!$container) return;
            var allTypes = ['all'].concat(types);
            var totalCount = 0;
            types.forEach(function (t) { totalCount += (allItems[t] || []).length; });

            var sel = $container.querySelector('#' + prefix + '-type-select');
            if (!sel) {
                sel = document.createElement('select');
                sel.id = prefix + '-type-select';
                sel.style.cssText = 'padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; background:var(--cc-surf2); color:var(--cc-text); border:1px solid var(--cc-border); outline:none; min-width:140px;';
                sel.addEventListener('change', function () {
                    currentType = this.value;
                    renderDropdown();
                    var $s = document.getElementById(prefix + '-search');
                    if ($s && $s.value) showLiveResults($s.value);
                });
                $container.innerHTML = '';
                $container.appendChild(sel);
            }
            var currentVal = sel.value || currentType;
            sel.innerHTML = '';
            allTypes.forEach(function (t) {
                var c = getTypeConfig(t);
                var count = t === 'all' ? totalCount : (allItems[t] || []).length;
                var opt = document.createElement('option');
                opt.value = t;
                opt.textContent = c.icon + ' ' + c.label + ' (' + count + ')';
                sel.appendChild(opt);
            });
            sel.value = allTypes.indexOf(currentVal) !== -1 ? currentVal : currentType;
            currentType = sel.value;
        }

        function getFilteredItems(query) {
            var items = [];
            if (currentType === 'all') {
                Object.values(allItems).forEach(function (arr) { items = items.concat(arr); });
            } else {
                items = (allItems[currentType] || []).slice();
            }
            var q = (query || '').trim().toLowerCase();
            if (q) {
                items = items.filter(function (item) {
                    return item.url.toLowerCase().indexOf(q) !== -1 || item.title.toLowerCase().indexOf(q) !== -1;
                });
            }
            items.sort(function (a, b) { return a.title.localeCompare(b.title); });
            return items;
        }

        function renderDropdown() {
            var $sel = document.getElementById(prefix + '-url-select');
            if (!$sel) return;
            var $search = document.getElementById(prefix + '-search');
            var q = $search ? $search.value : '';
            var items = getFilteredItems(q);
            var cfg2 = getTypeConfig(currentType);
            var singular = currentType === 'all' ? 'page' : cfg2.label.toLowerCase().replace(/s$/, '');
            var currentVal = $sel.value;
            var html = '<option value="">Select a ' + singular + '... (' + items.length + ' results)</option>';
            items.forEach(function (item) {
                var label = item.title && item.title !== item.url ? item.title : item.url;
                html += '<option value="' + item.url.replace(/"/g, '&quot;') + '">' + label + '</option>';
            });
            $sel.innerHTML = html;
            if (currentVal && items.some(function (i) { return i.url === currentVal; })) $sel.value = currentVal;
        }

        function showLiveResults(query) {
            var $results = document.getElementById(prefix + '-results');
            var $clear   = document.getElementById(prefix + '-search-clear');
            if (!$results) return;

            var items = getFilteredItems(query);
            if ($clear) $clear.style.display = query ? 'block' : 'none';

            if (!query.trim()) { $results.style.display = 'none'; return; }

            if (items.length === 0) {
                $results.style.display = 'block';
                $results.innerHTML = '<div style="padding:10px 14px; color:var(--cc-muted); font-size:13px;">No results for "' + escHtml(query) + '"</div>';
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
                html += '<div class="' + prefix + '-result-row" data-url="' + escAttr(item.url) + '" style="padding:8px 14px; cursor:pointer; border-bottom:1px solid var(--cc-border); display:flex; flex-direction:column; gap:2px; transition:background 0.1s; background:var(--cc-surf, #1e2130);">'
                      + '<span style="font-size:13px; font-weight:600; color:var(--cc-text);">' + labelHl + '</span>'
                      + '<span style="font-size:11px; color:var(--cc-muted);">' + urlHl + '</span>'
                      + '</div>';
            }
            if (items.length > 40) {
                html += '<div style="padding:8px 14px; font-size:12px; color:var(--cc-muted);">+ ' + (items.length - 40) + ' more — refine your search</div>';
            }
            $results.innerHTML = html;
            $results.style.display = 'block';

            $results.querySelectorAll('.' + prefix + '-result-row').forEach(function (row) {
                row.addEventListener('mouseenter', function () { this.style.background = 'var(--cc-surf2)'; });
                row.addEventListener('mouseleave', function () { this.style.background = ''; });
                row.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    selectPage(this.getAttribute('data-url'));
                });
            });
        }

        function selectPage(url) {
            var $search  = document.getElementById(prefix + '-search');
            var $results = document.getElementById(prefix + '-results');
            var $clear   = document.getElementById(prefix + '-search-clear');
            var $sel     = document.getElementById(prefix + '-url-select');
            var item = dataMap[url] || null;
            if ($search) $search.value = item && item.title !== item.url ? item.title : url;
            if ($results) $results.style.display = 'none';
            if ($clear) $clear.style.display = 'block';
            renderDropdown();
            if ($sel) $sel.value = url;
            onSelect(url, item);
        }

        function refresh() {
            var rows = (window.seoTabRowCache && window.seoTabRowCache[tab]) || [];
            var $sel = document.getElementById(prefix + '-url-select');
            if (!$sel) return;
            var currentVal = $sel.value;

            allItems = {};
            dataMap = {};

            rows.forEach(function (r) {
                var type = r.type || 'other';
                var title = r.title || r.url || '';
                if (!title || title === r.url) {
                    try {
                        var u = new URL(r.url);
                        var path = u.pathname.replace(/\/$/, '').split('/').pop();
                        title = path ? path.replace(/-/g, ' ') : 'Home';
                        title = title.charAt(0).toUpperCase() + title.slice(1);
                    } catch (e) { title = r.url; }
                }
                if (!allItems[type]) allItems[type] = [];
                allItems[type].push({ url: r.url, title: title });
                dataMap[r.url] = { url: r.url, title: title, type: type, extra: buildExtra(r), row: r };
            });

            var types = Object.keys(allItems).sort();
            buildTypeSelect(types);
            renderDropdown();

            if (currentVal && dataMap[currentVal]) $sel.value = currentVal;
        }

        var $search  = document.getElementById(prefix + '-search');
        var $results = document.getElementById(prefix + '-results');
        var $clear   = document.getElementById(prefix + '-search-clear');
        var $sel     = document.getElementById(prefix + '-url-select');
        var searchTimer;

        if ($search) {
            $search.addEventListener('input', function () {
                clearTimeout(searchTimer);
                var q = this.value;
                searchTimer = setTimeout(function () {
                    showLiveResults(q);
                    renderDropdown();
                }, 150);
            });
            $search.addEventListener('focus', function () {
                this.style.borderColor = 'var(--cc-primary)';
                if (this.value) showLiveResults(this.value);
            });
            $search.addEventListener('blur', function () {
                this.style.borderColor = 'var(--cc-border)';
                setTimeout(function () {
                    if ($results) $results.style.display = 'none';
                }, 180);
            });
            $search.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    if ($results) $results.style.display = 'none';
                    this.blur();
                }
            });
        }

        if ($clear) {
            $clear.addEventListener('click', function () {
                if ($search) { $search.value = ''; $search.focus(); }
                if ($results) $results.style.display = 'none';
                this.style.display = 'none';
                renderDropdown();
                if ($sel) $sel.value = '';
                onSelect('', null);
            });
        }

        if ($sel) {
            $sel.addEventListener('change', function () {
                var url = this.value;
                var item = dataMap[url] || null;
                if (url && $search) $search.value = item && item.title !== item.url ? item.title : url;
                if (url && $clear) $clear.style.display = 'block';
                onSelect(url, item);
            });
        }

        document.addEventListener('click', function (e) {
            var wrap = document.getElementById(prefix + '-search-wrap');
            if (wrap && !wrap.contains(e.target) && $results) {
                $results.style.display = 'none';
            }
        });

        return { refresh: refresh, getItem: function (url) { return dataMap[url]; } };
    };

    /* ── Tab switching ──────────────────────────────────── */
    $(document).on('click', '.seo-cl-nav-btn', function () {
        var tab = $(this).data('tab');
        // Load data on first show
        if (!$(this).data('loaded')) {
            $(this).data('loaded', true);
            loadTab(tab);
        }
    });

    /* ── Scopes that are server-paginated (non-grouped) ─────────────────
       These scopes do NOT fetch all rows at once. Instead:
         1. fetchMeta()  -> seo_dash_get_report_meta  -> aggregated totals for KPI/charts
         2. fetchPage()  -> seo_dash_get_report_data  -> only SERVER_PER_PAGE rows
       Grouped scopes (ga, sc, service, blog) keep the existing fetchData path
       because they require all rows for their multi-period grouped structure. ── */
    var SERVER_PAGED_SCOPES = ['backlinks', 'leads', 'technical', 'gmb', 'keywords'];

    // Per-tab server pagination state for non-grouped scopes
    var serverTabPages  = {};  // tab -> current page (1-based)
    var serverTabMeta   = {};  // tab -> last meta response {total_rows, type_counts, month_totals, kpi_aggregates}
    var SERVER_PER_PAGE = 20;

    /* ── Load tab data ──────────────────────────────────── */
    function loadTab(tab) {
        var scopeMap = {
            analytics: window.seoAnaFilterScope || 'ga', sc: window.seoSCFilterScope || 'sc',
            service: 'service', blog: 'blog',
            gmb: 'gmb', technical: 'technical',
            backlinks: 'backlinks', leads: 'leads'
        };
        var scope = scopeMap[tab];
        if (!scope) return;

        var $panel = $('.seo-cl-panel-tab[data-tab="' + tab + '"]');
        var month  = $panel.find('.seo-cl-month-sel').val() || '';

        if (SERVER_PAGED_SCOPES.indexOf(scope) !== -1) {
            // ── Server-paginated path (backlinks, leads, technical, gmb, keywords) ──
            serverTabPages[tab] = 1; // reset to page 1 on tab (re)load
            loadServerPagedTab(tab, scope, month);
        } else {
            // ── Grouped path (ga, sc, service, blog) — unchanged ──
            fetchData(scope, month, function (rows, months) {
                renderMonths($panel, scope, months);
                renderRows(tab, rows);
            });
        }
    }

    /* ── Month change ───────────────────────────────────── */
    $(document).on('change', '.seo-cl-month-sel', function () {
        var scope = $(this).data('scope');
        var month = $(this).val();
        var tab   = $(this).closest('.seo-cl-panel-tab').data('tab');

        if (SERVER_PAGED_SCOPES.indexOf(scope) !== -1) {
            serverTabPages[tab] = 1;
            loadServerPagedTab(tab, scope, month);
        } else {
            fetchData(scope, month, function (rows) {
                renderRows(tab, rows);
            });
        }
    });

    /* ── Fetch data via AJAX (grouped scopes: ga, sc, service, blog) ─── */
    function fetchData(scope, month, cb) {
        $.post(C.ajax, {
            action:    'seo_dash_get_report_data',
            nonce:     C.nonce,
            report_id: C.report_id,
            scope:     scope,
            month_key: month,
            per_page:  99999, // fetch all URLs; SQL groups by page_url so 1 row per URL
            page:      1
        }, function (r) {
            if (r.success && r.data) {
                var rows = r.data.rows || [];
                var months = r.data.months || [];
                cb(rows, months);
            } else {
                cb([], []);
            }
        }).fail(function(){
            cb([], []);
        });
    }

    /* ── Fetch aggregated meta for a scope (KPI totals, type counts, monthly trend) ── */
    // Used by server-paginated tabs (backlinks, leads, technical, gmb, keywords).
    // Returns: { total_rows, type_counts, month_totals, kpi_aggregates, months }
    function fetchMeta(scope, month, cb) {
        $.post(C.ajax, {
            action:    'seo_dash_get_report_meta',
            nonce:     C.nonce,
            report_id: C.report_id,
            scope:     scope,
            month_key: month || ''
        }, function (r) {
            if (r.success && r.data) {
                cb(r.data);
            } else {
                cb({ total_rows: 0, type_counts: {}, month_totals: {}, kpi_aggregates: {}, months: [] });
            }
        }).fail(function () {
            cb({ total_rows: 0, type_counts: {}, month_totals: {}, kpi_aggregates: {}, months: [] });
        });
    }

    /* ── Fetch one page of rows for a server-paginated scope ─────────── */
    function fetchPage(scope, month, page, cb) {
        $.post(C.ajax, {
            action:    'seo_dash_get_report_data',
            nonce:     C.nonce,
            report_id: C.report_id,
            scope:     scope,
            month_key: month || '',
            per_page:  SERVER_PER_PAGE,
            page:      page
        }, function (r) {
            if (r.success && r.data) {
                cb(r.data.rows || [], r.data.pagination || {});
            } else {
                cb([], {});
            }
        }).fail(function () {
            cb([], {});
        });
    }

    /* ── Load a server-paginated tab (two parallel requests) ─────────── */
    // Fires fetchMeta + fetchPage simultaneously. Once both resolve, renders:
    //   - Month dropdown (from meta.months)
    //   - Table rows (from page response)
    //   - Pagination controls driven by meta.total_rows (true total, not page slice)
    //   - Charts/KPIs fed from meta aggregates — not from raw rows
    function loadServerPagedTab(tab, scope, month) {
        var page = serverTabPages[tab] || 1;
        var $panel = $('.seo-cl-panel-tab[data-tab="' + tab + '"]');

        // Show loading state
        var $tbody = $panel.find('.seo-cl-tbody');
        if ($tbody.length) {
            $tbody.html('<tr><td colspan="10" style="text-align:center;padding:32px;color:var(--cc-subtle);">Loading…</td></tr>');
        }

        var metaDone  = false;
        var pageDone  = false;
        var metaResult = null;
        var pageRows   = null;
        var pagePag    = null;

        function tryRender() {
            if (!metaDone || !pageDone) return;

            // Store meta for this tab (charts/KPIs in dashboard.php read this)
            serverTabMeta[tab] = metaResult;
            window.seoServerTabMeta = serverTabMeta; // expose globally for dashboard.php

            // Populate month dropdown from meta (first time only)
            if (metaResult.months && metaResult.months.length) {
                renderMonths($panel, scope, metaResult.months);
            }

            // Render the table rows for this page
            // We pass rows to renderRows but also tell it the server-side total
            // so pagination shows the real total, not just the current page count.
            renderServerPagedRows(tab, pageRows, pagePag, metaResult);

            // Fire charts/KPI update for backlinks using meta data
            if (tab === 'backlinks' && typeof renderBacklinksCharts === 'function') {
                // Pass meta so dashboard.php can rebuild charts without full rows
                renderBacklinksCharts(null, metaResult);
            }

            // Fire seo:rowsLoaded event so dashboard.php KPI blocks can update
            $(document).trigger('seo:metaLoaded', [tab, metaResult]);
        }

        fetchMeta(scope, month, function (meta) {
            metaResult = meta;
            metaDone   = true;
            tryRender();
        });

        fetchPage(scope, month, page, function (rows, pag) {
            pageRows = rows;
            pagePag  = pag;
            pageDone = true;
            tryRender();
        });
    }

    /* ── Render rows + server-driven pagination for non-grouped tabs ─── */
    function renderServerPagedRows(tab, rows, pag, meta) {
        var total       = (pag && pag.total_rows)  ? pag.total_rows  : (meta ? meta.total_rows : 0);
        var totalPages  = (pag && pag.total_pages) ? pag.total_pages : Math.ceil(total / SERVER_PER_PAGE);
        var currentPage = serverTabPages[tab] || 1;

        // Expose rows in the global cache so other parts of the code can read them
        tabRowCache[tab] = rows;
        tabFullDataCache[tab + '_current'] = rows;
        window.seoTabRowCache = tabRowCache;
        window.seoTabFullDataCache = tabFullDataCache;

        // Fire event so dashboard.php listeners get updated row set
        $(document).trigger('seo:rowsLoaded', [tab, rows, tabFullDataCache]);

        // Render table rows using the existing renderRows HTML builder
        // We pass rows directly (already the correct page slice from server)
        // and use a special flag so renderRows doesn't try to re-paginate client-side
        renderRowsServerPaged(tab, rows);

        // Render server-driven pagination
        renderServerPagination(tab, total, currentPage, totalPages);
    }

    /* ── Render HTML rows for a server-paged tab (no client slicing) ── */
    function renderRowsServerPaged(tab, rows) {
        // Store in cache (no client-side filtering for server-paged tabs)
        tabRowCache[tab] = rows || [];

        var $tbody = $('.seo-cl-panel-tab[data-tab="' + tab + '"] .seo-cl-tbody');
        if (!$tbody.length) return;
        $tbody.empty();

        if (!rows || !rows.length) {
            $tbody.html('<tr><td colspan="10" style="text-align:center;padding:24px;color:var(--cc-subtle);">No data for this period.</td></tr>');
            return;
        }

        rows.forEach(function (r, idx) {
            var tr = '';
            var rowNum = ((serverTabPages[tab] || 1) - 1) * SERVER_PER_PAGE + idx + 1;
            switch (tab) {
                case 'backlinks':
                    var colsStr = $('#seo-cl-bk-table').attr('data-cols') || '[]';
                    var cols = [];
                    try { cols = JSON.parse(colsStr); } catch(e){}
                    var host = r.source_url ? (r.source_url.match(/\/\/([^\/]+)/) || [,''])[1] : '—';
                    tr = '<tr>';
                    if(cols.indexOf('row_num') > -1)  tr += '<td style="text-align:center;font-size:11px;color:var(--cc-muted);border-right:1px solid var(--cc-border);">' + rowNum + '</td>';
                    if(cols.indexOf('type') > -1)     tr += '<td><span class="seo-cl-badge seo-cl-badge-gray" style="font-size:10px;">' + esc((r.link_type||'').replace('_',' ')) + '</span></td>';
                    if(cols.indexOf('website') > -1)  tr += '<td style="font-size:12px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+esc(r.source_url)+'">' + esc(host) + '</td>';
                    if(cols.indexOf('da') > -1)       tr += '<td style="text-align:center;font-weight:700;">' + (r.domain_rating || '—') + '</td>';
                    if(cols.indexOf('pa') > -1)       tr += '<td style="text-align:center;font-weight:700;">' + (r.page_authority || '—') + '</td>';
                    if(cols.indexOf('spam') > -1)     tr += '<td style="text-align:center;font-weight:700;">' + (r.spam_score!==null ? r.spam_score+'%' : '—') + '</td>';
                    if(cols.indexOf('live_link') > -1) {
                        var link = r.live_link ? '<a href="'+esc(r.live_link)+'" target="_blank" rel="noopener" style="color:var(--cc-primary);font-weight:600;text-decoration:none;">Visit</a>' : '—';
                        tr += '<td style="text-align:center;">' + link + '</td>';
                    }
                    if(cols.indexOf('keyword') > -1)    tr += '<td style="font-size:12px;color:var(--cc-muted);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(r.anchor_text || '—') + '</td>';
                    if(cols.indexOf('target_url') > -1) {
                        var target = r.target_url ? '<a href="'+esc(r.target_url)+'" target="_blank" rel="noopener" style="color:var(--cc-primary);font-weight:600;text-decoration:none;">Visit</a>' : '—';
                        tr += '<td style="font-size:12px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+esc(r.target_url)+'">' + target + '</td>';
                    }
                    if(cols.indexOf('date') > -1)  tr += '<td style="font-size:11px;color:var(--cc-subtle);">' + esc(r.found_date || r.month_key) + '</td>';
                    if(cols.indexOf('month') > -1) {
                        var dStr = r.found_date || r.month_key;
                        var m = dStr ? new Date(dStr).toLocaleString('default', { month: 'short' }) : '—';
                        tr += '<td style="font-size:11px;color:var(--cc-muted);">' + esc(m) + '</td>';
                    }
                    if(cols.indexOf('year') > -1) {
                        var dStr2 = r.found_date || r.month_key;
                        var y = dStr2 ? new Date(dStr2).getFullYear() : '—';
                        tr += '<td style="font-size:11px;color:var(--cc-muted);">' + esc(y) + '</td>';
                    }
                    if(cols.indexOf('status') > -1) {
                        var c = r.status==='live' ? 'var(--cc-green)' : (r.status==='lost' ? 'var(--cc-red)' : '#f59e0b');
                        tr += '<td><span style="color:'+c+';font-weight:600;font-size:11px;">' + cap(r.status) + '</span></td>';
                    }
                    tr += '</tr>';
                    break;

                case 'leads':
                    var statusCls = {new:'',contacted:'seo-cl-badge-orange',converted:'seo-cl-badge-green',lost:'seo-cl-badge-red'};
                    var sc2 = statusCls[r.status] || '';
                    var dateStr = r.lead_date ? new Date(r.lead_date).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—';
                    var ldScore = 0;
                    if ((r.name || '').trim().length >= 3) ldScore += 25;
                    if ((r.phone || '').replace(/[^0-9]/g, '').length >= 7) ldScore += 25;
                    if ((r.email || '').trim().indexOf('@') > 0) ldScore += 25;
                    var stStr = (r.status || '').toLowerCase();
                    if ((r.message || '').trim() || stStr === 'qualified' || stStr === 'converted') ldScore += 25;
                    var ldLabel = 'Weak', ldColor = '#ef4444';
                    if (ldScore >= 80) { ldLabel = 'High'; ldColor = '#10b981'; }
                    else if (ldScore >= 50) { ldLabel = 'Good'; ldColor = '#06b6d4'; }
                    var ldBadgeHtml = '<div style="display:inline-flex;align-items:center;gap:8px;" title="Lead Strength: ' + ldScore + '%">' +
                        '<div style="position:relative;width:38px;height:38px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
                        '<svg width="38" height="38" viewBox="0 0 36 36" style="transform:rotate(-90deg);"><path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--cc-border)" stroke-width="2.5" /><path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="' + ldColor + '" stroke-width="3" stroke-dasharray="' + ldScore + ', 100" stroke-linecap="round" /></svg>' +
                        '<span style="position:absolute;font-size:10px;font-weight:800;color:var(--cc-text);line-height:1;">' + ldScore + '%</span></div>' +
                        '<span style="font-size:11.5px;font-weight:700;color:' + ldColor + ';">' + ldLabel + '</span></div>';

                    tr = '<tr><td style="font-weight:600;min-width:140px;max-width:180px;word-break:break-word;line-height:1.4;">' + esc(r.name || '—') + '</td>' +
                         '<td style="color:var(--cc-muted);font-size:12px;">' + esc(r.source || '—') + '</td>' +
                         '<td style="font-size:12px;">' + dateStr + '</td>' +
                         '<td><span class="seo-cl-badge ' + sc2 + '">' + cap(r.status) + '</span></td>' +
                         '<td>' + ldBadgeHtml + '</td></tr>';
                    break;

                case 'technical':
                    var sevCls = {low:'seo-cl-badge-gray',medium:'',high:'seo-cl-badge-orange',critical:'seo-cl-badge-red'};
                    tr = '<tr><td style="font-weight:600;font-size:13px;">' + esc(r.issue_type) + '</td>' +
                         '<td style="font-size:11px;color:var(--cc-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(r.url || '') + '</td>' +
                         '<td><span class="seo-cl-badge ' + (sevCls[r.severity]||'') + '">' + cap(r.severity) + '</span></td>' +
                         '<td><span class="seo-cl-badge seo-cl-badge-gray">' + cap(r.status) + '</span></td></tr>';
                    break;
            }
            if (tr) $tbody.append(tr);
        });
    }

    /* ── Server-driven pagination controls ───────────────────────────── */
    function renderServerPagination(tab, total, currentPage, totalPages) {
        var $panel = $('.seo-cl-panel-tab[data-tab="' + tab + '"]');
        var $pag   = $panel.find('.seo-cl-tab-pagination');
        if (!$pag.length) return;
        if (!total) { $pag.hide().html(''); return; }
        $pag.show();

        var html = '<div style="display:flex;align-items:center;gap:6px;justify-content:center;flex-wrap:wrap;">';
        html += '<span style="font-size:12px;color:var(--cc-text);margin-right:12px;">Page ' + currentPage + ' of ' + totalPages + ' &nbsp;|&nbsp; <strong>' + total.toLocaleString() + '</strong> total rows</span>';

        if (totalPages > 1) {
            var d1 = currentPage === 1          ? ' disabled' : '';
            var d2 = currentPage === totalPages ? ' disabled' : '';
            html += '<button class="seo-bk-page-btn seo-srv-pg-btn" data-tab="' + tab + '" data-page="1"' + d1 + '>«</button>';
            html += '<button class="seo-bk-page-btn seo-srv-pg-btn" data-tab="' + tab + '" data-page="' + (currentPage - 1) + '"' + d1 + '>‹</button>';
            for (var p = Math.max(1, currentPage - 2); p <= Math.min(totalPages, currentPage + 2); p++) {
                if (p === currentPage) {
                    html += '<button class="seo-bk-page-btn active" disabled>' + p + '</button>';
                } else {
                    html += '<button class="seo-bk-page-btn seo-srv-pg-btn" data-tab="' + tab + '" data-page="' + p + '">' + p + '</button>';
                }
            }
            html += '<button class="seo-bk-page-btn seo-srv-pg-btn" data-tab="' + tab + '" data-page="' + (currentPage + 1) + '"' + d2 + '>›</button>';
            html += '<button class="seo-bk-page-btn seo-srv-pg-btn" data-tab="' + tab + '" data-page="' + totalPages + '"' + d2 + '>»</button>';
        }
        html += '</div>';
        $pag.html(html);
    }

    /* ── Server pagination button click ──────────────────────────────── */
    $(document).on('click', '.seo-srv-pg-btn', function () {
        var tab  = $(this).data('tab');
        var page = parseInt($(this).data('page'), 10);
        if (!tab || !page) return;

        var scopeMap = {
            backlinks: 'backlinks', leads: 'leads',
            technical: 'technical', gmb: 'gmb', keywords: 'keywords'
        };
        var scope = scopeMap[tab];
        if (!scope) return;

        serverTabPages[tab] = page;
        var $panel = $('.seo-cl-panel-tab[data-tab="' + tab + '"]');
        var month  = $panel.find('.seo-cl-month-sel').val() || '';

        // Only fetch the new page — meta is already cached (total count doesn't change)
        var $tbody = $panel.find('.seo-cl-tbody');
        if ($tbody.length) {
            $tbody.html('<tr><td colspan="10" style="text-align:center;padding:32px;color:var(--cc-subtle);">Loading page ' + page + '…</td></tr>');
        }

        fetchPage(scope, month, page, function (rows, pag) {
            var meta = serverTabMeta[tab] || {};
            renderServerPagedRows(tab, rows, pag, meta);
            $panel[0] && $panel[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    /* ── Populate month dropdowns ───────────────────────── */
    function renderMonths($panel, scope, months) {
        var $sel = $panel.find('.seo-cl-month-sel[data-scope="' + scope + '"]');
        if (!months.length || $sel.find('option').length > 1) return;
        months.forEach(function (m) {
            var d = new Date(m + '-01');
            var label = d.toLocaleDateString('en', { month: 'long', year: 'numeric' });
            $sel.append('<option value="' + m + '">' + label + '</option>');
        });
        if (months.length) { $sel.val(months[0]).trigger('change'); }
    }

    /* ── Pagination state per tab ───────────────────────── */
    var tabPages = {};
    var TAB_ROWS_PER_PAGE = 20;

    function renderPagination(tab, total, currentPage) {
        var totalPages = Math.max(1, Math.ceil(total / TAB_ROWS_PER_PAGE));
        var $panel = $('.seo-cl-panel-tab[data-tab="' + tab + '"]');
        var $pag = $panel.find('.seo-cl-tab-pagination');
        if (!$pag.length) return;
        if (!total) { $pag.hide().html(''); return; }
        $pag.show();
        var html = '<div style="display:flex;align-items:center;gap:6px;justify-content:center;flex-wrap:wrap;">';
        html += '<span style="font-size:12px;color:var(--cc-text);margin-right:12px;">Page ' + currentPage + ' of ' + totalPages + ' &nbsp;|&nbsp; <strong>' + total + '</strong> total rows</span>';
        if (totalPages > 1) {
            var d1 = currentPage === 1 ? ' disabled' : '';
            var d2 = currentPage === totalPages ? ' disabled' : '';
            html += '<button class="seo-bk-page-btn seo-tab-pg-btn" data-tab="' + tab + '" data-page="1"' + d1 + '>«</button>';
            html += '<button class="seo-bk-page-btn seo-tab-pg-btn" data-tab="' + tab + '" data-page="' + (currentPage - 1) + '"' + d1 + '>‹</button>';
            for (var p = Math.max(1, currentPage - 2); p <= Math.min(totalPages, currentPage + 2); p++) {
                if (p === currentPage) {
                    html += '<button class="seo-bk-page-btn active" disabled>' + p + '</button>';
                } else {
                    html += '<button class="seo-bk-page-btn seo-tab-pg-btn" data-tab="' + tab + '" data-page="' + p + '">' + p + '</button>';
                }
            }
            html += '<button class="seo-bk-page-btn seo-tab-pg-btn" data-tab="' + tab + '" data-page="' + (currentPage + 1) + '"' + d2 + '>›</button>';
            html += '<button class="seo-bk-page-btn seo-tab-pg-btn" data-tab="' + tab + '" data-page="' + totalPages + '"' + d2 + '>»</button>';
        }
        html += '</div>';
        $pag.html(html);
    }

    $(document).on('click', '.seo-tab-pg-btn', function () {
        var tab  = $(this).data('tab');
        var page = parseInt($(this).data('page'), 10);
        if (!tab || !page) return;
        tabPages[tab] = page;
        var cachedRows = tabRowCache[tab];
        if (cachedRows) {
            renderRows(tab, cachedRows, true);
            var $panel = $('.seo-cl-panel-tab[data-tab="' + tab + '"]');
            $panel[0] && $panel[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    var tabRowCache = {};
    var tabFullDataCache = {}; // stores unfiltered full dataset for KPI/chart — never overwritten by filter switches
    window.seoTabRowCache = tabRowCache; // expose for KPI/chart functions in dashboard.php
    window.seoTabFullDataCache = tabFullDataCache; // full data cache for KPI/chart totals

    /* ── Sub-type filter state per tab ─────────────────────── */
    var tabSubTypeFilter = {}; // tab -> active type ('all' or specific type string)

    /* ── Build dynamic sub-type filter dropdown ──────────────── */
    function buildSubTypeFilters(tab, rows) {
        var containerId = tab === 'service' ? 'seo-sp-subtype-filters' : (tab === 'blog' ? 'seo-blog-subtype-filters' : null);
        if (!containerId) return;
        var $container = $('#' + containerId);
        if (!$container.length) return;

        // Count rows per type
        var counts = {};
        rows.forEach(function(r) {
            var t = r.type || 'other';
            counts[t] = (counts[t] || 0) + 1;
        });

        var types = Object.keys(counts);
        if (types.length <= 1) { $container.hide(); return; }

        // Nice labels and icons per type
        var labels = {
            page: '📄 Pages', service: '🛠️ Services', location: '📍 Locations',
            city: '🏙️ Cities', product: '🛒 Products', portfolio: '🖼️ Portfolio',
            post: '✍️ Posts', blog: '📝 Blog', category: '🗂️ Category',
            article: '📰 Articles', news: '📡 News', tag: '🏷️ Tags', other: '🌍 Other'
        };

        var activeType = tabSubTypeFilter[tab] || 'all';

        var options = '<option value="all"' + (activeType === 'all' ? ' selected' : '') + '>🌍 All Types (' + rows.length + ')</option>';
        types.sort().forEach(function(t) {
            var label = labels[t] || ('🔹 ' + t.charAt(0).toUpperCase() + t.slice(1));
            options += '<option value="' + t + '"' + (activeType === t ? ' selected' : '') + '>' + label + ' (' + counts[t] + ')</option>';
        });

        var $select = $container.find('select.seo-sp-subtype-select');
        if (!$select.length) {
            $container.html('<select class="seo-sp-subtype-select" data-tab="' + tab + '" style="padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; background:var(--cc-surf2); color:var(--cc-text); border:1px solid var(--cc-border); outline:none; min-width:160px;"></select>');
            $select = $container.find('select.seo-sp-subtype-select');
        }
        $select.html(options);

        $container.show();
    }

    /* ── Sub-type filter dropdown change ──────────────────────── */
    $(document).on('change', '.seo-sp-subtype-select', function() {
        var tab  = $(this).data('tab');
        var type = $(this).val();
        tabSubTypeFilter[tab] = type;
        tabPages[tab] = 1;
        // Re-render with filter applied
        var allRows = tabRowCache[tab] || [];
        renderRows(tab, allRows, false, type);
    });

    /* ── Table search filter (Service / Blog) ──────────────────── */
    var tabSearchQuery = {}; // tab -> search query string

    $(document).on('input', '#seo-sp-table-search, #seo-blog-table-search', function() {
        var tab = $(this).data('tab');
        var q = $(this).val();
        tabSearchQuery[tab] = q;
        tabPages[tab] = 1;
        var allRows = tabRowCache[tab] || [];
        renderRows(tab, allRows, false, tabSubTypeFilter[tab] || 'all');
    });

    /* ── Deduplicate rows by URL (merge period data) ──────── */
    function deduplicateRows(rows) {
        var seen = {};
        var out = [];
        rows.forEach(function(r) {
            var key = r.url || r.source_url || JSON.stringify(r);
            if (!seen[key]) {
                seen[key] = r;
                out.push(r);
            } else {
                // Merge period data — keep max values per metric
                var existing = seen[key];
                var d = r.data || {};
                Object.keys(d).forEach(function(period) {
                    if (!existing.data) existing.data = {};
                    if (!existing.data[period]) {
                        existing.data[period] = d[period];
                    } else {
                        var ep = existing.data[period];
                        var np = d[period];
                        Object.keys(np).forEach(function(metric) {
                            if (typeof np[metric] === 'number' && (ep[metric] === undefined || ep[metric] === 0)) {
                                ep[metric] = np[metric];
                            } else if (typeof np[metric] === 'number' && np[metric] > ep[metric]) {
                                ep[metric] = np[metric];
                            }
                        });
                    }
                });
                // Keep best title
                if (!existing.title && r.title) existing.title = r.title;
                // Keep type from first occurrence
            }
        });
        return out;
    }

    /* ── Render rows per tab ────────────────────────────── */
    function renderRows(tab, rows, keepPage, forceTypeFilter) {
        // Deduplicate by URL so multiple month rows for same page collapse into one
        rows = deduplicateRows(rows || []);

        // Store full deduplicated dataset in cache (unfiltered — all types)
        if (!keepPage || forceTypeFilter === undefined) {
            tabRowCache[tab] = rows;
            // Always keep the unfiltered 'all' version for reference
            var currentScope = (window.seoSCFilterScope && tab === 'sc') ? window.seoSCFilterScope :
                               (window.seoAnaFilterScope && tab === 'analytics' ? window.seoAnaFilterScope : tab);
            tabFullDataCache[tab + '_' + currentScope] = rows;
            if (tab === 'analytics') tabFullDataCache[tab + '_ga_all'] = rows;
            if (!tabFullDataCache[tab + '_all']) tabFullDataCache[tab + '_all'] = rows;
        }
        if (!keepPage) {
            tabPages[tab] = 1;
            tabSubTypeFilter[tab] = tabSubTypeFilter[tab] || 'all';
        }

        // Build dynamic sub-type filter buttons for service/blog tabs (from full unfiltered data)
        if (tab === 'service' || tab === 'blog') {
            buildSubTypeFilters(tab, tabRowCache[tab] || rows);
        }

        // Apply sub-type filter if active (service/blog tabs)
        var activeType = forceTypeFilter !== undefined ? forceTypeFilter : (tabSubTypeFilter[tab] || 'all');
        tabSubTypeFilter[tab] = activeType;
        if (activeType !== 'all' && (tab === 'service' || tab === 'blog')) {
            rows = rows.filter(function(r) { return (r.type || 'other') === activeType; });
        }

        // Apply analytics type filter (from dynamic sitemap type buttons) — client-side
        if (tab === 'analytics' && window.seoAnaTypeFilter) {
            rows = rows.filter(function(r) { return (r.type || 'other') === window.seoAnaTypeFilter; });
        }

        // Apply SC type filter (from dynamic sitemap type select) — client-side
        if (tab === 'sc' && window.seoSCTypeFilter) {
            rows = rows.filter(function(r) { return (r.type || 'other') === window.seoSCTypeFilter; });
        }

        // Apply table search filter (service/blog tables) — client-side
        if ((tab === 'service' || tab === 'blog') && tabSearchQuery[tab]) {
            var sq = tabSearchQuery[tab].trim().toLowerCase();
            if (sq) {
                rows = rows.filter(function(r) {
                    var title = (r.title || '').toLowerCase();
                    var url = (r.url || '').toLowerCase();
                    return title.indexOf(sq) !== -1 || url.indexOf(sq) !== -1;
                });
            }
        }

        // Store the post-filter rows so KPI/chart functions use the SAME filtered dataset
        // that is shown in the table — this is what the user expects to see reflected
        tabFullDataCache[tab + '_current'] = rows;

        var currentPage = tabPages[tab] || 1;

        // Fire event with the current filtered rows so KPI/chart update to match the table
        $(document).trigger('seo:rowsLoaded', [tab, rows, tabFullDataCache]);

        var $tbody = $('.seo-cl-panel-tab[data-tab="' + tab + '"] .seo-cl-tbody');
        if (!$tbody.length) return;
        $tbody.empty();

        if (!rows || !rows.length) {
            $tbody.html('<tr><td colspan="10" style="text-align:center;padding:24px;color:var(--cc-subtle);">No data for this period.</td></tr>');
            renderPagination(tab, 0, 1);
            return;
        }

        var total = rows.length;
        // All tabs use JS pagination now
        var noPaginate = false;
        var pageRows, rowOffset;
        if (noPaginate) {
            pageRows  = rows;
            rowOffset = 0;
        } else {
            var start = (currentPage - 1) * TAB_ROWS_PER_PAGE;
            rowOffset = start;
            pageRows  = rows.slice(start, Math.min(start + TAB_ROWS_PER_PAGE, total));
        }

        pageRows.forEach(function (r) {
            var tr = '';
            switch (tab) {
                case 'analytics':
                case 'service':
                case 'blog':
                    var title = r.title;
                    if ((!title || title === r.url) && r.url) {
                        try {
                            var u = new URL(r.url);
                            var path = u.pathname.replace(/\/$/, '').split('/').pop();
                            if (!path) title = 'Home';
                            else {
                                title = path.replace(/-/g, ' ');
                                title = title.charAt(0).toUpperCase() + title.slice(1);
                            }
                        } catch(e) {
                            title = r.url;
                        }
                    }
                    if (!title) title = r.url;
                    var d = r.data || {};
                    var p7 = d['7d'] || null;
                    var p30 = d['30d'] || null;
                    var p90 = d['90d'] || null;
                    var pall = d['overall'] || null;
                    
                    var fSess = function(p) { return p ? num(p.sessions) : '0'; };
                    var fUsers = function(p) { return p ? num(p.users) : '0'; };
                    var fViews = function(p) { return p ? num(p.pageviews) : '0'; };
                    
                    tr = '<tr>' +
                         '<td style="text-align:center;border-right:1px solid var(--cc-border);font-size:11px;color:var(--cc-muted);">' + (rowOffset + $tbody.children().length + 1) + '</td>' +
                         '<td style="font-size:12px;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-right:1px solid var(--cc-border);" title="'+esc(r.url)+'"><a href="'+esc(r.url)+'" target="_blank" style="color:var(--cc-text);text-decoration:none;font-weight:600;">' + esc(title) + '</a></td>' +
                         '<td style="text-align:center;border-right:1px solid var(--cc-border);"><a href="'+esc(r.url)+'" target="_blank" style="font-size:14px;text-decoration:none;color:var(--cc-primary);font-weight:700;">↗</a></td>' +
                         
                         '<td data-col="7d" style="text-align:center;color:#0ea5e9;">' + fUsers(p7) + '</td>' +
                         '<td data-col="7d" style="text-align:center;color:#0ea5e9;">' + fSess(p7) + '</td>' +
                         '<td data-col="7d" style="text-align:center;color:#0ea5e9;border-right:1px solid var(--cc-border);">' + fViews(p7) + '</td>' +
                         
                         '<td data-col="30d" style="text-align:center;color:#8b5cf6;">' + fUsers(p30) + '</td>' +
                         '<td data-col="30d" style="text-align:center;color:#8b5cf6;">' + fSess(p30) + '</td>' +
                         '<td data-col="30d" style="text-align:center;color:#8b5cf6;border-right:1px solid var(--cc-border);">' + fViews(p30) + '</td>' +
                         
                         '<td data-col="90d" style="text-align:center;color:#10b981;">' + fUsers(p90) + '</td>' +
                         '<td data-col="90d" style="text-align:center;color:#10b981;">' + fSess(p90) + '</td>' +
                         '<td data-col="90d" style="text-align:center;color:#10b981;border-right:1px solid var(--cc-border);">' + fViews(p90) + '</td>' +
                         
                         '<td data-col="overall" style="text-align:center;color:#f59e0b;">' + fUsers(pall) + '</td>' +
                         '<td data-col="overall" style="text-align:center;color:#f59e0b;">' + fSess(pall) + '</td>' +
                         '<td data-col="overall" style="text-align:center;color:#f59e0b;">' + fViews(pall) + '</td>' +
                         
                         '</tr>';
                    break;
                case 'sc':
                    var title = r.title;
                    if ((!title || title === r.url) && r.url) {
                        try {
                            var u = new URL(r.url);
                            var path = u.pathname.replace(/\/$/, '').split('/').pop();
                            if (!path) title = 'Home';
                            else {
                                title = path.replace(/-/g, ' ');
                                title = title.charAt(0).toUpperCase() + title.slice(1);
                            }
                        } catch(e) {
                            title = r.url;
                        }
                    }
                    if (!title) title = r.url;

                    var d = r.data || {};
                    var p7 = d['7d'] || null;
                    var p30 = d['30d'] || null;
                    var p90 = d['90d'] || null;
                    var pall = d['overall'] || null;
                    
                    var fClk = function(p) { return p ? num(p.clicks) : '0'; };
                    var fImp = function(p) { return p ? num(p.impressions) : '0'; };
                    var fCtr = function(p) { return p ? parseFloat(p.ctr || 0).toFixed(1) + '%' : '0%'; };
                    var fPos = function(p) { return p ? parseFloat(p.position || 0).toFixed(1) : '0'; };
                    
                    var trType = r.type || 'other';
                    tr = '<tr data-type="' + esc(trType) + '">' +
                         '<td style="text-align:center;border-right:1px solid var(--cc-border);font-size:11px;color:var(--cc-muted);">' + (rowOffset + $tbody.children().length + 1) + '</td>' +
                         '<td style="font-size:12px;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-right:1px solid var(--cc-border);" title="'+esc(r.url)+'"><a href="'+esc(r.url)+'" target="_blank" style="color:var(--cc-text);text-decoration:none;font-weight:600;">' + esc(title) + '</a></td>' +
                         '<td style="text-align:center;border-right:1px solid var(--cc-border);"><a href="'+esc(r.url)+'" target="_blank" style="font-size:14px;text-decoration:none;color:var(--cc-primary);font-weight:700;">↗</a></td>' +
                         
                         '<td data-col="7d" style="text-align:right;color:#0ea5e9;">' + fClk(p7) + '</td>' +
                         '<td data-col="7d" style="text-align:right;color:#0ea5e9;">' + fImp(p7) + '</td>' +
                         '<td data-col="7d" style="text-align:right;color:#0ea5e9;">' + fCtr(p7) + '</td>' +
                         '<td data-col="7d" style="text-align:right;color:#0ea5e9;border-right:1px solid var(--cc-border);">' + fPos(p7) + '</td>' +
                         
                         '<td data-col="30d" style="text-align:right;color:#8b5cf6;">' + fClk(p30) + '</td>' +
                         '<td data-col="30d" style="text-align:right;color:#8b5cf6;">' + fImp(p30) + '</td>' +
                         '<td data-col="30d" style="text-align:right;color:#8b5cf6;">' + fCtr(p30) + '</td>' +
                         '<td data-col="30d" style="text-align:right;color:#8b5cf6;border-right:1px solid var(--cc-border);">' + fPos(p30) + '</td>' +
                         
                         '<td data-col="90d" style="text-align:right;color:#10b981;">' + fClk(p90) + '</td>' +
                         '<td data-col="90d" style="text-align:right;color:#10b981;">' + fImp(p90) + '</td>' +
                         '<td data-col="90d" style="text-align:right;color:#10b981;">' + fCtr(p90) + '</td>' +
                         '<td data-col="90d" style="text-align:right;color:#10b981;border-right:1px solid var(--cc-border);">' + fPos(p90) + '</td>' +
                         
                         '<td data-col="overall" style="text-align:right;color:#f59e0b;">' + fClk(pall) + '</td>' +
                         '<td data-col="overall" style="text-align:right;color:#f59e0b;">' + fImp(pall) + '</td>' +
                         '<td data-col="overall" style="text-align:right;color:#f59e0b;">' + fCtr(pall) + '</td>' +
                         '<td data-col="overall" style="text-align:right;color:#f59e0b;">' + fPos(pall) + '</td>' +
                         
                         '</tr>';
                    break;
                case 'backlinks':
                    var colsStr = $('#seo-cl-bk-table').attr('data-cols') || '[]';
                    var cols = [];
                    try { cols = JSON.parse(colsStr); } catch(e){}
                    var host = r.source_url ? (r.source_url.match(/\/\/([^\/]+)/) || [,''])[1] : '—';
                    tr = '<tr>';
                    if(cols.indexOf('row_num') > -1) tr += '<td style="text-align:center;font-size:11px;color:var(--cc-muted);border-right:1px solid var(--cc-border);">' + (rowOffset + $tbody.children().length + 1) + '</td>';
                    if(cols.indexOf('type') > -1) tr += '<td><span class="seo-cl-badge seo-cl-badge-gray" style="font-size:10px;">' + esc((r.link_type||'').replace('_',' ')) + '</span></td>';
                    if(cols.indexOf('website') > -1) tr += '<td style="font-size:12px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+esc(r.source_url)+'">' + esc(host) + '</td>';
                    if(cols.indexOf('da') > -1) tr += '<td style="text-align:center;font-weight:700;">' + (r.domain_rating || '—') + '</td>';
                    if(cols.indexOf('pa') > -1) tr += '<td style="text-align:center;font-weight:700;">' + (r.page_authority || '—') + '</td>';
                    if(cols.indexOf('spam') > -1) tr += '<td style="text-align:center;font-weight:700;">' + (r.spam_score!==null ? r.spam_score+'%' : '—') + '</td>';
                    if(cols.indexOf('live_link') > -1) {
                        var link = r.live_link ? '<a href="'+esc(r.live_link)+'" target="_blank" rel="noopener" style="color:var(--cc-primary);font-weight:600;text-decoration:none;">Visit</a>' : '—';
                        tr += '<td style="text-align:center;">' + link + '</td>';
                    }
                    if(cols.indexOf('keyword') > -1) tr += '<td style="font-size:12px;color:var(--cc-muted);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(r.anchor_text || '—') + '</td>';
                    if(cols.indexOf('target_url') > -1) {
                        var target = r.target_url ? '<a href="'+esc(r.target_url)+'" target="_blank" rel="noopener" style="color:var(--cc-primary);font-weight:600;text-decoration:none;">Visit</a>' : '—';
                        tr += '<td style="font-size:12px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+esc(r.target_url)+'">' + target + '</td>';
                    }
                    if(cols.indexOf('date') > -1) tr += '<td style="font-size:11px;color:var(--cc-subtle);">' + esc(r.found_date || r.month_key) + '</td>';
                    if(cols.indexOf('month') > -1) {
                        var dStr = r.found_date || r.month_key;
                        var m = dStr ? new Date(dStr).toLocaleString('default', { month: 'short' }) : '—';
                        tr += '<td style="font-size:11px;color:var(--cc-muted);">' + esc(m) + '</td>';
                    }
                    if(cols.indexOf('year') > -1) {
                        var dStr2 = r.found_date || r.month_key;
                        var y = dStr2 ? new Date(dStr2).getFullYear() : '—';
                        tr += '<td style="font-size:11px;color:var(--cc-muted);">' + esc(y) + '</td>';
                    }
                    if(cols.indexOf('status') > -1) {
                        var c = r.status==='live' ? 'var(--cc-green)' : (r.status==='lost' ? 'var(--cc-red)' : '#f59e0b');
                        tr += '<td><span style="color:'+c+';font-weight:600;font-size:11px;">' + cap(r.status) + '</span></td>';
                    }
                    tr += '</tr>';
                    break;
                case 'leads':
                    var statusCls = {new:'',contacted:'seo-cl-badge-orange',converted:'seo-cl-badge-green',lost:'seo-cl-badge-red'};
                    var sc2 = statusCls[r.status] || '';
                    var dateStr = r.lead_date ? new Date(r.lead_date).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—';
                    tr = '<tr><td style="font-weight:600;">' + esc(r.name || '—') + '</td>' +
                         '<td style="color:var(--cc-muted);font-size:12px;">' + esc(r.source || '—') + '</td>' +
                         '<td style="font-size:12px;">' + dateStr + '</td>' +
                         '<td><span class="seo-cl-badge ' + sc2 + '">' + cap(r.status) + '</span></td></tr>';
                    break;
                case 'technical':
                    var sevCls = {low:'seo-cl-badge-gray',medium:'',high:'seo-cl-badge-orange',critical:'seo-cl-badge-red'};
                    tr = '<tr><td style="font-weight:600;font-size:13px;">' + esc(r.issue_type) + '</td>' +
                         '<td style="font-size:11px;color:var(--cc-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(r.url || '') + '</td>' +
                         '<td><span class="seo-cl-badge ' + (sevCls[r.severity]||'') + '">' + cap(r.severity) + '</span></td>' +
                         '<td><span class="seo-cl-badge seo-cl-badge-gray">' + cap(r.status) + '</span></td></tr>';
                    break;
            }
            if (tr) $tbody.append(tr);
        });
        if (!noPaginate) renderPagination(tab, total, currentPage);
    }

    /* ── Type filtering ───────────────────────────────────── */
    // Blog types — these go to 'blog' scope; everything else is 'service' scope
    var ANA_BLOG_TYPES = ['post', 'blog', 'category', 'article', 'news', 'tag'];
    // Current active GA type filter (null = all)
    window.seoAnaTypeFilter = null;

    $(document).on('change', '#seo-ana-type-select', function () {
        var type = $(this).val();

        if (type === 'all') {
            window.seoAnaFilterScope = 'ga';
            window.seoAnaTypeFilter = null;
        } else {
            window.seoAnaFilterScope = 'ga';
            window.seoAnaTypeFilter = type;
        }

        // Show loading state
        var $tbody = $('.seo-cl-panel-tab[data-tab="analytics"] .seo-cl-tbody');
        if ($tbody.length) $tbody.html('<tr><td colspan="15" style="text-align:center;padding:32px;color:var(--cc-subtle);">Loading…</td></tr>');

        loadTab('analytics');
    });
    
    // Current active SC type filter (null = all)
    window.seoSCTypeFilter = null;

    $(document).on('change', '#seo-sc-type-select', function () {
        var type = $(this).val();
        window.seoSCTypeFilter = (type === 'all') ? null : type;

        // Show loading state
        var $tbody = $('.seo-cl-panel-tab[data-tab="sc"] .seo-cl-tbody');
        if ($tbody.length) $tbody.html('<tr><td colspan="19" style="text-align:center;padding:32px;color:var(--cc-subtle);">Loading…</td></tr>');

        loadTab('sc');
    });
    

    /* ── Custom Date Range Apply ────────────────────────────── */
    $(document).on('click', '#seo-cl-custom-date-btn', function () {
        var $btn = $(this);
        $btn.text('Applying...');
        
        var $tbody = $('.seo-cl-panel-tab[data-tab="analytics"] .seo-cl-tbody');
        if ($tbody.length) $tbody.html('<tr><td colspan="15" style="text-align:center;padding:32px;color:var(--cc-subtle);">Applying Custom Range...</td></tr>');
        
        setTimeout(function() {
            loadTab('analytics');
            $btn.text('Apply');
            $btn.css({
                'background': 'var(--cc-primary)',
                'color': '#fff'
            });
            setTimeout(function() {
                $btn.css({
                    'background': 'var(--cc-surf2)',
                    'color': 'var(--cc-text)'
                });
            }, 2000);
        }, 800);
    });

    /* ── Overview KPI fetch ─────────────────────────────── */
    function loadKPIs() {
        $.post(C.ajax, {
            action: 'seo_dash_get_kpis', nonce: C.nonce, report_id: C.report_id
        }, function (r) {
            if (!r.success || !r.data) return;
            var d = r.data;
            $('#seo-kpi-sessions').text(num(d.sessions));
            $('#seo-kpi-clicks').text(num(d.clicks));
            $('#seo-kpi-leads').text(num(d.leads));
            $('#seo-kpi-backlinks').text(num(d.backlinks));
        });
    }

    /* ── AI chat ────────────────────────────────────────── */
    var chatHistory = [];

    $('#seo-cl-chat-send').on('click', sendChat);
    $('#seo-cl-chat-input').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
    });

    function sendChat() {
        var msg = $('#seo-cl-chat-input').val().trim();
        if (!msg) return;
        $('#seo-cl-chat-input').val('');
        appendMsg('user', msg);
        chatHistory.push({ role: 'user', content: msg });
        var $typing = $('<div class="seo-cl-msg seo-cl-msg-assistant"><div class="seo-cl-msg-bubble" style="opacity:.5;">Thinking...</div></div>').appendTo('#seo-cl-chat-messages');
        scrollChat();
        $.post(C.ajax, {
            action: 'seo_dash_chat', nonce: C.nonce,
            report_id: C.report_id, message: msg, history: chatHistory
        }, function (r) {
            $typing.remove();
            if ( r.success && r.data && r.data.reply ) {
                var reply = r.data.reply;
                appendMsg('assistant', reply);
                chatHistory.push({ role: 'assistant', content: reply });
            } else {
                var errMsg = (r.data && r.data.message) ? r.data.message : 'Sorry, I could not get a response.';
                appendMsg('assistant', '⚠️ ' + errMsg);
            }
            scrollChat();
        }).fail(function (xhr) {
            $typing.remove();
            var errDetail = '';
            try {
                var parsed = JSON.parse(xhr.responseText);
                errDetail = parsed.data && parsed.data.message ? parsed.data.message : xhr.responseText.substring(0, 200);
            } catch(e) {
                errDetail = xhr.responseText ? xhr.responseText.substring(0, 200) : 'HTTP ' + xhr.status;
            }
            appendMsg('assistant', '⚠️ Error: ' + errDetail);
            scrollChat();
        });
    }

    function mdToHtml(text) {
        var s = text;

        // Trim and collapse 3+ blank lines to 2
        s = s.replace(/\r\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();

        // STEP 1: Strip raw HTML <a> tags the AI may have emitted despite instructions
        // Pass 1: well-formed <a href="URL" ...>Label</a> -> [Label](URL)
        s = s.replace(/<a\s[^>]*href=["']([^"']+)["'][^>]*>([\s\S]*?)<\/a>/gi, function(_, url, label) {
            return '[' + label.replace(/<[^>]*>/g,'').trim() + '](' + url.trim() + ')';
        });
        // Pass 2: orphan opening/closing <a> tags
        s = s.replace(/<a\s[^>]*>/gi, '');
        s = s.replace(/<\/a>/gi, '');
        // Pass 3: leftover HTML attribute fragments after a URL
        s = s.replace(/"\s+target="_blank"\s+rel="noopener"\s+style="[^"]*">/gi, '"');
        s = s.replace(/'\s+target='_blank'\s+rel='noopener'\s+style='[^']*'>/gi, "'");
        s = s.replace(/\s+target=["']_blank["']/gi, '');
        s = s.replace(/\s+rel=["']noopener["']/gi, '');
        s = s.replace(/\s+style=["'][^"']*["']/gi, '');

        // STEP 2: Save markdown links, code blocks, and bare URLs as placeholders
        // BEFORE HTML-escaping so the generated tags are not escaped.
        var placeholders = [];
        function savePH(html) {
            var idx = placeholders.length;
            placeholders.push(html);
            return '\x00PH' + idx + '\x00';
        }

        // Code fences ```...```
        s = s.replace(/```[\w]*\n?([\s\S]*?)```/g, function(_, code) {
            return savePH('<pre style="background:rgba(0,0,0,.25);border-radius:8px;padding:12px;overflow-x:auto;font-size:12px;margin:6px 0;"><code>' + code.trim() + '</code></pre>');
        });

        // Inline code `...`
        s = s.replace(/`([^`\n]+)`/g, function(_, code) {
            return savePH('<code style="background:rgba(99,102,241,.15);padding:2px 6px;border-radius:4px;font-size:12px;">' + code + '</code>');
        });

        // Markdown links [text](url) — MUST be saved before HTML escaping
        // Match both https:// and protocol-relative URLs (AI sometimes omits https://)
        s = s.replace(/\[([^\]]+)\]\(((?:https?:\/\/)?[^)\s]+)\)/g, function(_, label, url) {
            var href = /^https?:\/\//.test(url) ? url : 'https://' + url;
            return savePH('<a href="' + href + '" target="_blank" rel="noopener" style="color:var(--cc-primary,#6366f1);text-decoration:underline;font-weight:600;">' + label + '</a>');
        });
        // Clean up any orphan [text] that didn't match (no URL part) — just show the text
        s = s.replace(/\[([^\]]+)\](?!\()/g, '$1');

        // STEP 3: HTML-escape the remaining plain text safely
        s = s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

        // STEP 4: Markdown formatting (on escaped text, with placeholders intact)

        // Tables | col | col |
        s = s.replace(/(?:^|\n)((?:\|[^\n]+\|\n?)+)/gm, function(_, block) {
            var rows = block.trim().split('\n').filter(function(r){ return r.trim(); });
            // Filter out separator rows to count real data rows
            var dataRows = rows.filter(function(r){ return !/^\|[-:\s|]+\|$/.test(r.trim()); });
            if (dataRows.length < 1) return _;
            var html = '<div style="overflow-x:auto;margin:6px 0;"><table style="width:100%;border-collapse:collapse;font-size:13px;">';
            var dataIdx = 0;
            var numCols = 0;
            rows.forEach(function(row) {
                // Skip separator rows (| --- | --- |)
                if (/^\|[-:\s|]+\|$/.test(row.trim())) return;
                // Split on | but protect placeholders (they contain no pipes so this is safe)
                var rawCells = row.split('|').filter(function(c,idx,arr){ return idx>0&&idx<arr.length-1; });
                if (dataIdx === 0) numCols = rawCells.length;
                // Clamp to header column count: merge extra cells into the last column
                var cells = rawCells.slice(0, numCols);
                if (rawCells.length > numCols) {
                    cells[numCols - 1] = rawCells.slice(numCols - 1).join(' ').trim();
                }
                var isHeader = dataIdx === 0;
                var tag = isHeader ? 'th' : 'td';
                html += '<tr>' + cells.map(function(c, ci){
                    var isFirst = ci === 0;
                    var isLast = ci === numCols - 1;
                    // Rank col narrow, Sessions col narrow, Page col fills remaining space
                    var width = (numCols === 3 && isFirst) ? 'width:52px;' : (numCols === 3 && isLast) ? 'width:90px;' : '';
                    var style = isHeader
                        ? width + 'padding:7px 12px;background:rgba(99,102,241,.12);color:var(--cc-text);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid rgba(99,102,241,.3);text-align:left;'
                        : width + 'padding:8px 12px;border-bottom:1px solid var(--cc-border);color:var(--cc-text);vertical-align:middle;word-break:break-word;';
                    return '<' + tag + ' style="' + style + '">' + c.trim() + '</' + tag + '>';
                }).join('') + '</tr>';
                dataIdx++;
            });
            html += '</table></div>';
            return html;
        });

        // Bold **text**
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

        // Italic *text*
        s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');

        // Headings — longest match first so #### isn't consumed by ###
        s = s.replace(/^##### (.+)$/gm, '<div style="font-size:12px;font-weight:800;color:var(--cc-primary,#6366f1);margin:8px 0 3px;text-transform:uppercase;letter-spacing:.5px;">$1</div>');
        s = s.replace(/^#### (.+)$/gm,  '<div style="font-size:13px;font-weight:800;color:var(--cc-primary,#6366f1);margin:10px 0 4px;">$1</div>');
        s = s.replace(/^### (.+)$/gm,   '<div style="font-size:13px;font-weight:800;color:var(--cc-primary,#6366f1);margin:10px 0 4px;">$1</div>');
        s = s.replace(/^## (.+)$/gm,    '<div style="font-size:14px;font-weight:800;color:var(--cc-text);margin:10px 0 4px;border-bottom:1px solid var(--cc-border);padding-bottom:3px;">$1</div>');
        s = s.replace(/^# (.+)$/gm,     '<div style="font-size:15px;font-weight:800;color:var(--cc-text);margin:10px 0 5px;">$1</div>');

        // Bare URLs not already saved — convert to clickable links
        s = s.replace(/(https?:\/\/[^\s<>"'\x00)]+)/g, function(url) {
            var rawUrl = url.replace(/&amp;/g,'&');
            try {
                var parsed = new URL(rawUrl);
                var display = parsed.hostname.replace(/^www\./, '') + (parsed.pathname && parsed.pathname !== '/' ? parsed.pathname : '');
                if (display.length > 45) display = display.substring(0, 43) + '\u2026';
                return savePH('<a href="' + rawUrl + '" target="_blank" rel="noopener" style="color:var(--cc-primary,#6366f1);text-decoration:underline;font-weight:600;">' + display + '</a>');
            } catch(e) { return url; }
        });

        // Unordered list items
        s = s.replace(/^[-*] (.+)$/gm, '<li style="margin:3px 0;padding-left:2px;">$1</li>');
        s = s.replace(/(<li[^>]*>[\s\S]*?<\/li>\n?)+/g, function(m){
            return '<ul style="margin:5px 0;padding-left:18px;list-style:disc;">'+m+'</ul>';
        });

        // Ordered list items
        s = s.replace(/^\d+\. (.+)$/gm, '<oli>$1</oli>');
        s = s.replace(/(<oli>[\s\S]*?<\/oli>\n?)+/g, function(m){
            return '<ol style="margin:5px 0;padding-left:18px;">'+m.replace(/<oli>/g,'<li style="margin:3px 0;">').replace(/<\/oli>/g,'</li>')+'</ol>';
        });

        // Horizontal rule ---
        s = s.replace(/^---+$/gm, '<hr style="border:none;border-top:1px solid var(--cc-border);margin:8px 0;">');

        // Paragraphs
        var parts = s.split(/\n\n+/);
        parts = parts.map(function(p) {
            var trimmed = p.trim();
            if (!trimmed) return '';
            // Don't wrap block-level HTML or placeholder tokens
            if (/^<(div|ul|ol|pre|table|hr|blockquote)/.test(trimmed)) return trimmed;
            if (/^\x00PH\d+\x00/.test(trimmed)) return trimmed;
            // Split on single newlines and check each line — don't wrap lines that are placeholders
            var lines = trimmed.split('\n');
            var result = [];
            var buf = [];
            lines.forEach(function(line) {
                if (/^\x00PH\d+\x00$/.test(line.trim())) {
                    if (buf.length) { result.push('<p style="margin:0 0 6px 0;">' + buf.join(' ') + '</p>'); buf = []; }
                    result.push(line.trim());
                } else {
                    buf.push(line);
                }
            });
            if (buf.length) result.push('<p style="margin:0 0 6px 0;">' + buf.join(' ') + '</p>');
            return result.join('');
        });
        s = parts.filter(Boolean).join('');

        // STEP 5: Restore all placeholders
        s = s.replace(/\x00PH(\d+)\x00/g, function(_, idx) {
            return placeholders[parseInt(idx)] || '';
        });

        return s;
    }

    function appendMsg(role, text) {
        var cls = role === 'user' ? 'seo-cl-msg-user' : 'seo-cl-msg-assistant';
        var content = role === 'user'
            ? esc(text).replace(/\n/g, '<br>')
            : mdToHtml(text);
        var bubbleStyle = role === 'assistant'
            ? 'max-width:100%;line-height:1.65;'
            : '';
        var copyBtn = role === 'assistant'
            ? '<button class="seo-cl-copy-btn" title="Copy message" data-text="' + text.replace(/"/g, '&quot;') + '">' +
              '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
              '<rect x="9" y="9" width="13" height="13" rx="2"/>' +
              '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>' +
              '</svg> <span class="seo-cl-copy-label">Copy</span></button>'
            : '';
        $('#seo-cl-chat-messages').append(
            '<div class="seo-cl-msg ' + cls + '">' +
            '<div class="seo-cl-msg-bubble" style="' + bubbleStyle + '">' + content + '</div>' +
            copyBtn +
            '</div>'
        );
    }

    // Copy button handler
    $(document).on('click', '.seo-cl-copy-btn', function() {
        var txt = $(this).data('text');
        if (navigator.clipboard && txt) {
            navigator.clipboard.writeText(txt).then(function() {}).catch(function(){});
        } else {
            var ta = document.createElement('textarea');
            ta.value = txt; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch(e) {}
            document.body.removeChild(ta);
        }
        var $btn = $(this);
        $btn.find('.seo-cl-copy-label').text('Copied!');
        $btn.addClass('seo-cl-copy-btn--done');
        setTimeout(function(){
            $btn.find('.seo-cl-copy-label').text('Copy');
            $btn.removeClass('seo-cl-copy-btn--done');
        }, 1500);
    });
    function scrollChat() {
        var el = document.getElementById('seo-cl-chat-messages');
        if (el) el.scrollTop = el.scrollHeight;
    }

    /* ── Account save ───────────────────────────────────── */
    // Account save is handled by the inline PHP script in dashboard.php
    // (which uses the correct seo_dash_client_account action + nonce).
    // We intentionally do NOT bind a second handler here to avoid conflicts.

    /* ── CSV export ─────────────────────────────────────── */
    $(document).on('click', '.seo-cl-export-btn', function () {
        var tableId = $(this).data('table');
        var rows = document.querySelectorAll('#' + tableId + ' tr');
        var csv = Array.from(rows).map(function (r) {
            return Array.from(r.querySelectorAll('th,td'))
                .map(function (c) { return '"' + c.innerText.replace(/"/g, '""') + '"'; })
                .join(',');
        }).join('\n');
        var a = document.createElement('a');
        a.href = 'data:text/csv,' + encodeURIComponent(csv);
        a.download = tableId + '.csv'; a.click();
    });

    /* ── Init ───────────────────────────────────────────── */
    loadKPIs();
    // Auto-load the tab that is actually active/first-visible for this report
    // (the server marks it with class "active" — see $first_visible in dashboard.php).
    // Previously this was hardcoded to 'analytics', so if a report's first visible
    // tab was something else (e.g. Backlinks), its data never loaded on page load
    // — the panel appeared blank until the user clicked the nav button themselves.
    (function () {
        var $activeBtn = $('.seo-cl-nav-btn.active');
        var activeTab  = $activeBtn.data('tab') || 'analytics';
        $activeBtn.data('loaded', true);
        loadTab(activeTab);
    })();

    /* ── Helpers ────────────────────────────────────────── */
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function num(n) { return parseInt(n || 0).toLocaleString(); }
    function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
    
    // Populate SC PD Dates dynamically
    $(document).ready(function() {
        var dEnd = new Date();
        dEnd.setDate(dEnd.getDate() - 2); // SC lags 2 days
        var endStr = dEnd.toISOString().split('T')[0];
        
        var d7 = new Date(dEnd); d7.setDate(d7.getDate() - 6);
        $('#sc-pd-7d-date').text(d7.toISOString().split('T')[0] + ' – ' + endStr);
        
        var d30 = new Date(dEnd); d30.setDate(d30.getDate() - 29);
        $('#sc-pd-30d-date').text(d30.toISOString().split('T')[0] + ' – ' + endStr);
        
        var d90 = new Date(dEnd); d90.setDate(d90.getDate() - 89);
        $('#sc-pd-90d-date').text(d90.toISOString().split('T')[0] + ' – ' + endStr);
        
        // Overall: let's assume ~16 months max data from SC
        var dO = new Date(dEnd); dO.setMonth(dO.getMonth() - 16);
        $('#sc-pd-overall-date').text(dO.toISOString().split('T')[0] + ' – ' + endStr);
    });
})(jQuery);
