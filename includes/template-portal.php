<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( get_the_title() ); ?> &mdash; <?php bloginfo( 'name' ); ?></title>
<?php wp_head(); ?>
<style>
/* Full reset so no theme bleeds in */
*, *::before, *::after { box-sizing: border-box; }
html { margin-top: 0 !important; }
body { margin: 0; padding: 0; background: #0d1117; min-height: 100vh; font-size: 16px; }

/* Hide WP admin bar and all theme chrome */
#wpadminbar,
.site-header, .site-footer, header.site-header,
nav.main-navigation, .breadcrumbs,
.wp-block-post-title, .entry-header {
    display: none !important;
    pointer-events: none !important;
    height: 0 !important;
    overflow: hidden !important;
}

/* Force remove admin-bar body/html margin injected by WP JS */
html.wp-toolbar { padding-top: 0 !important; }
body.admin-bar { margin-top: 0 !important; padding-top: 0 !important; }

/* Strip theme layout constraints */
#page, #content, #primary, #main, .site-content,
.entry-content, main, article {
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
    float: none !important;
}

/* Stop WordPress converting emoji to <img> tags being oversized */
img.emoji {
    display: inline !important;
    height: 1em !important;
    width: 1em !important;
    margin: 0 0.1em !important;
    vertical-align: -0.1em !important;
}

/* Ensure our h1 inside .seo-app never gets theme font-size */
.seo-app h1 { font-size: 22px !important; }
.seo-page-title { font-size: 22px !important; font-weight: 800 !important; }

/* WP Media Modal UI Fixes */
.screen-reader-text { border: 0; clip: rect(1px, 1px, 1px, 1px); -webkit-clip-path: inset(50%); clip-path: inset(50%); height: 1px; margin: -1px; overflow: hidden; padding: 0; position: absolute; width: 1px; word-wrap: normal !important; }
.media-modal h1 { margin: 0; padding: 0 16px; font-size: 22px; line-height: 50px; }
.media-modal-close { position: absolute; top: 0; right: 0; width: 50px; height: 50px; margin: 0; padding: 0; border: 0; background: 0 0; cursor: pointer; }
.media-modal-close span.media-modal-icon { display: block; text-align: center; }
.media-modal select { padding: 0 24px 0 8px !important; height: auto !important; line-height: normal !important; }
</style>
</head>
<body>
<script>
/* Remove WP admin-bar margin that gets injected after CSS */
(function() {
    function stripAdminBarMargin() {
        document.documentElement.style.setProperty('margin-top', '0', 'important');
        document.documentElement.style.setProperty('padding-top', '0', 'important');
        document.body && (document.body.style.setProperty('margin-top', '0', 'important'));
        var bar = document.getElementById('wpadminbar');
        if (bar) { bar.style.setProperty('display', 'none', 'important'); bar.style.setProperty('pointer-events', 'none', 'important'); }
    }
    stripAdminBarMargin();
    document.addEventListener('DOMContentLoaded', stripAdminBarMargin);
    // Also run after all scripts (WP injects margin-top via JS at footer)
    window.addEventListener('load', stripAdminBarMargin);
})();
</script>
<?php
while ( have_posts() ) {
    the_post();
    the_content();
}
wp_footer();
?>
</body>
</html>
