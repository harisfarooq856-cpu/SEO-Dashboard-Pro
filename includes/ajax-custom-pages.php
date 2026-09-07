<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_seo_dash_save_custom_page_field', function() {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $type      = sanitize_key( $_POST['page_type'] ?? '' );
    $url       = sanitize_text_field( wp_unslash( $_POST['url'] ?? '' ) );
    $field     = sanitize_key( $_POST['field'] ?? '' );
    $value     = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

    if ( ! $report_id || ! $type || ! $url || ! $field ) seo_dash_json_error( 'Missing parameters.' );

    $custom_map = get_option("seo_dash_custom_pages_{$report_id}_{$type}", []);
    if (!is_array($custom_map)) $custom_map = [];

    if (!isset($custom_map[$url])) {
        $custom_map[$url] = [
            'url' => $url,
            'title' => $url,
            'keyword' => '',
            'ranked_page' => '',
            'ai_overview' => 0,
            'month' => '',
            'publish_date' => ''
        ];
        
        // Nuclear fix: Map it so it doesn't default to 'other'
        $ga_map = get_option("seo_dash_sitemap_types_{$report_id}_ga", []);
        if (!is_array($ga_map)) $ga_map = [];
        if (empty($ga_map[$url])) { $ga_map[$url] = $type; update_option("seo_dash_sitemap_types_{$report_id}_ga", $ga_map); }
        
        $sc_map = get_option("seo_dash_sitemap_types_{$report_id}_sc", []);
        if (!is_array($sc_map)) $sc_map = [];
        if (empty($sc_map[$url])) { $sc_map[$url] = $type; update_option("seo_dash_sitemap_types_{$report_id}_sc", $sc_map); }
    }

    $custom_map[$url][$field] = $value;
    update_option("seo_dash_custom_pages_{$report_id}_{$type}", $custom_map);

    if ( function_exists('seo_dash_fetch_kpis_for_urls') ) {
        seo_dash_fetch_kpis_for_urls( $report_id, [ $url ] );
    }

    seo_dash_json_success( 'Saved.' );
});

add_action( 'wp_ajax_seo_dash_remove_custom_page', function() {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $type      = sanitize_key( $_POST['page_type'] ?? '' );
    $urls      = isset($_POST['urls']) ? array_map('sanitize_text_field', wp_unslash((array)$_POST['urls'])) : [];

    if ( ! $report_id || ! $type || empty($urls) ) seo_dash_json_error( 'Missing parameters.' );

    $custom_map = get_option("seo_dash_custom_pages_{$report_id}_{$type}", []);
    if (!is_array($custom_map)) $custom_map = [];

    $ga_map = get_option("seo_dash_sitemap_types_{$report_id}_ga", []);
    $sc_map = get_option("seo_dash_sitemap_types_{$report_id}_sc", []);
    global $wpdb;

    $removed = 0;
    foreach ($urls as $u) {
        $u_escaped = esc_url_raw( $u );
        // Nuclear: try direct key first (sanitize_text_field version and esc_url_raw version)
        $found_key = null;
        if (isset($custom_map[$u])) {
            $found_key = $u;
        } elseif (isset($custom_map[$u_escaped])) {
            $found_key = $u_escaped;
        } else {
            // Deep scan — match by urldecode or html_entity_decode
            foreach (array_keys($custom_map) as $stored_u) {
                if (
                    urldecode($stored_u) === urldecode($u) ||
                    urldecode($stored_u) === urldecode($u_escaped) ||
                    html_entity_decode($stored_u) === html_entity_decode($u) ||
                    $stored_u === $u_escaped
                ) {
                    $found_key = $stored_u;
                    break;
                }
            }
        }
        if ($found_key !== null) {
            unset($custom_map[$found_key]);
            $removed++;
        }
        
        $u_safe = esc_sql($u);
        $u_safe2 = esc_sql($u_escaped);
        $wpdb->query("DELETE FROM {$wpdb->prefix}seodash_data_ga WHERE report_id={$report_id} AND page_url='{$u_safe}'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}seodash_data_ga WHERE report_id={$report_id} AND page_url='{$u_safe2}'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}seodash_data_sc WHERE report_id={$report_id} AND page_url='{$u_safe}'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}seodash_data_sc WHERE report_id={$report_id} AND page_url='{$u_safe2}'");
    }
    
    // Always save — nuclear
    update_option("seo_dash_custom_pages_{$report_id}_{$type}", $custom_map);

    seo_dash_json_success( ['removed' => $removed, 'map_size' => count($custom_map)] );
});
add_action( 'wp_ajax_seo_dash_bulk_custom_pages', function() {
    seo_dash_verify_admin_ajax();
    $report_id   = intval( $_POST['report_id'] ?? 0 );
    $type        = sanitize_key( $_POST['page_type'] ?? '' );
    $bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );
    $urls        = isset($_POST['urls']) ? array_map('sanitize_text_field', wp_unslash((array)$_POST['urls'])) : [];

    if ( ! $report_id || ! $type || empty($urls) || ! $bulk_action ) seo_dash_json_error( 'Missing parameters.' );

    $custom_map = get_option("seo_dash_custom_pages_{$report_id}_{$type}", []);
    if (!is_array($custom_map)) $custom_map = [];

    $changed = false;
    $newly_added_urls = [];
    foreach ($urls as $u) {
        if (!isset($custom_map[$u])) {
            $custom_map[$u] = [
                'url' => $u,
                'title' => $u,
                'keyword' => '',
                'ranked_page' => '',
                'ai_overview' => 0,
                'month' => '',
                'publish_date' => ''
            ];
            
            if ($bulk_action === 'add') {
                $newly_added_urls[] = $u;
                $ga_map = get_option("seo_dash_sitemap_types_{$report_id}_ga", []);
                if (!is_array($ga_map)) $ga_map = [];
                if (!isset($ga_map[$u])) {
                    $ga_map[$u] = $type;
                    update_option("seo_dash_sitemap_types_{$report_id}_ga", $ga_map);
                }
                
                $sc_map = get_option("seo_dash_sitemap_types_{$report_id}_sc", []);
                if (!is_array($sc_map)) $sc_map = [];
                if (!isset($sc_map[$u])) {
                    $sc_map[$u] = $type;
                    update_option("seo_dash_sitemap_types_{$report_id}_sc", $sc_map);
                }
            }
        }
        
        if ($bulk_action === 'trash') {
            $custom_map[$u]['trashed'] = 1;
            $changed = true;
        } else if ($bulk_action === 'restore') {
            $custom_map[$u]['trashed'] = 0;
            $changed = true;
        } else if ($bulk_action === 'remove') {
            unset($custom_map[$u]);
            $changed = true;
            
            global $wpdb;
            $u_safe = esc_sql($u);
            $wpdb->query("DELETE FROM {$wpdb->prefix}seodash_data_ga WHERE report_id={$report_id} AND page_url='{$u_safe}'");
            $wpdb->query("DELETE FROM {$wpdb->prefix}seodash_data_sc WHERE report_id={$report_id} AND page_url='{$u_safe}'");
        } else if ($bulk_action === 'ai_ov') {
            $custom_map[$u]['ai_overview'] = 1;
            $changed = true;
        } else if ($bulk_action === 'ai_uncheck') {
            $custom_map[$u]['ai_overview'] = 0;
            $changed = true;
        } else if ($bulk_action === 'set_rank') {
            $val = sanitize_text_field($_POST['rank_val'] ?? '');
            if ($val) {
                $custom_map[$u]['ranked_page'] = $val;
                $changed = true;
            }
        } else if ($bulk_action === 'add_overview') {
            $custom_map[$u]['show_on_overview'] = 1;
            $changed = true;
        }
    }
    
    if ($bulk_action === 'remove_all') {
        global $wpdb;
        
        foreach ($custom_map as $u => $cdata) {
            $u_safe = esc_sql($u);
            $wpdb->query("DELETE FROM {$wpdb->prefix}seodash_data_ga WHERE report_id={$report_id} AND page_url='{$u_safe}'");
            $wpdb->query("DELETE FROM {$wpdb->prefix}seodash_data_sc WHERE report_id={$report_id} AND page_url='{$u_safe}'");
        }

        $custom_map = [];
        $changed = true;
    }

    if ($changed) {
        update_option("seo_dash_custom_pages_{$report_id}_{$type}", $custom_map);
    }
    
    if ( $bulk_action === 'add' && ! empty( $newly_added_urls ) && function_exists('seo_dash_fetch_kpis_for_urls') ) {
        seo_dash_fetch_kpis_for_urls( $report_id, $newly_added_urls );
    }

    seo_dash_json_success( 'Action processed.' );
});
