/**
 * SEO Client Reporting Dashboard Pro — Admin App JS
 */
(function($){
    'use strict';

    /* ── Toast ──────────────────────────────────────────────────────────── */
    var $toastWrap;
    function ensureToastWrap() {
        if (!$toastWrap || !$toastWrap.length) {
            $toastWrap = $('#seo-toast');
            if (!$toastWrap.length) {
                $toastWrap = $('<div id="seo-toast"></div>').appendTo('body');
            }
        }
        return $toastWrap;
    }
    window.seoToast = function(msg, type) {
        var wrap = ensureToastWrap();
        var icon = type === 'ok' ? '✅' : type === 'err' ? '❌' : 'ℹ️';
        var $toast = $('<div class="seo-toast"></div>')
            .addClass(type || 'info')
            .html('<span>' + icon + '</span><span>' + msg + '</span>');
        wrap.append($toast);
        setTimeout(function() {
            $toast.css({ opacity: 0, transform: 'translateY(10px)', transition: 'opacity .3s, transform .3s' });
            setTimeout(function() { $toast.remove(); }, 350);
        }, 3200);
    };

    /* ── Modal open/close ────────────────────────────────────────────────── */
    window.seoOpenModal = function(id) {
        var $modal = $('#' + id);
        if (!$modal.length) return;
        $modal.css('display', 'flex').hide().fadeIn(150);
        $('body').css('overflow', 'hidden');
    };

    window.seoCloseModal = function(id) {
        var $modal = id ? $('#' + id) : $('.seo-modal:visible');
        $modal.fadeOut(150, function() {
            $('body').css('overflow', '');
        });
    };

    // Close modal on backdrop click
    $(document).on('click', '.seo-modal', function(e) {
        if ($(e.target).hasClass('seo-modal')) {
            window.seoCloseModal();
        }
    });

    // Close modal on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') window.seoCloseModal();
    });

    // Wire up close buttons — supports both data-close-modal and data-close attributes
    $(document).on('click', '[data-close-modal], [data-close], .seo-modal-close, .seo-modal-x', function() {
        window.seoCloseModal();
    });

    /* ── Dark / Light theme toggle ──────────────────────────────────────── */
    var THEME_KEY = 'seo_dash_admin_theme';

    function getApp() { return document.getElementById('seo-app'); }

    function applyTheme(theme) {
        var app = getApp();
        if (!app) return;
        if (theme === 'light') {
            document.documentElement.setAttribute('data-seo-theme', 'light');
            app.classList.add('seo-lt');
            document.body.classList.add('seo-lt');
            document.body.style.setProperty('background', '#f1f5f9', 'important');
        } else {
            document.documentElement.removeAttribute('data-seo-theme');
            app.classList.remove('seo-lt');
            document.body.classList.remove('seo-lt');
            document.body.style.setProperty('background', '#0d1117', 'important');
        }
        var btn = document.getElementById('seo-theme-btn');
        if (btn) btn.innerHTML = theme === 'light' ? '🌙' : '☀️';
        try { localStorage.setItem(THEME_KEY, theme); } catch(e) {}
    }

    function initTheme() {
        var saved = 'dark';
        try { saved = localStorage.getItem(THEME_KEY) || 'dark'; } catch(e) {}
        applyTheme(saved);
    }

    /* ── Disable autocomplete on all admin inputs ───────────────────────── */
    function disableAutocomplete() {
        // Modern browsers ignore autocomplete="off" for email/password.
        // Use "new-password" for passwords, and a custom token for text/email.
        $('#seo-app input[type="text"], #seo-app input[type="search"]').each(function() {
            $(this).attr('autocomplete', 'nope');
        });
        $('#seo-app input[type="email"]').each(function() {
            $(this).attr('autocomplete', 'nope');
            // Also clear any browser-autofilled value that isn't the real value
            var real = $(this).val();
            $(this).val('').val(real);
        });
        $('#seo-app input[type="password"]').each(function() {
            $(this).attr('autocomplete', 'new-password');
        });
        // Handle dynamically added inputs via mutation observer
        if (window.MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(m) {
                    m.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            $(node).find('input[type="text"], input[type="search"]').attr('autocomplete', 'nope');
                            $(node).find('input[type="email"]').attr('autocomplete', 'nope');
                            $(node).find('input[type="password"]').attr('autocomplete', 'new-password');
                        }
                    });
                });
            });
            var app = getApp();
            if (app) observer.observe(app, { childList: true, subtree: true });
        }
    }

    /* ── Document ready ─────────────────────────────────────────────────── */
    $(function() {
        // Apply theme immediately on DOM ready
        initTheme();
        disableAutocomplete();

        // Theme toggle click
        $(document).on('click', '#seo-theme-btn', function() {
            var app = getApp();
            if (!app) return;
            var isLight = app.classList.contains('seo-lt');
            applyTheme(isLight ? 'dark' : 'light');
        });

        // Loading state helper
        $.fn.seoLoading = function(state) {
            return this.each(function() {
                var $el = $(this);
                if (state) {
                    $el.data('_orig', $el.html()).addClass('seo-loading').prop('disabled', true);
                } else {
                    var orig = $el.data('_orig');
                    if (orig !== undefined) $el.html(orig);
                    $el.removeClass('seo-loading').prop('disabled', false);
                }
            });
        };

        // Auto-dismiss non-error notices
        setTimeout(function() {
            $('.notice.is-dismissible').not('.notice-error').fadeOut(600);
        }, 4000);
    });

})(jQuery);
