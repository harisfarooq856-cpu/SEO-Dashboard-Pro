<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO_Dash_Frontend_Render
 * Renders the complete client-facing dashboard HTML.
 */
class SEO_Dash_Frontend_Render {

    private static array $report     = [];
    private static array $report_ids = [];
    private static int   $user_id    = 0;
    private static array $design     = [];

    // ── Entry point ────────────────────────────────────────────────────────

    public static function render( array $report, array $report_ids, int $user_id, array $override_show = [], string $template = 'dashboard.php' ): void {
        self::$report     = $report;
        self::$report_ids = array_map( 'intval', $report_ids );
        self::$user_id    = $user_id;

        $design_json    = SEO_Dash_Database::get_setting( 'design_options', '{}' );
        self::$design   = json_decode( $design_json, true ) ?: [];

        $rid     = intval( $report['id'] );
        $meta    = is_array( $report['meta'] ) ? $report['meta'] : ( json_decode( $report['meta'] ?? '{}', true ) ?: [] );
        $user    = get_userdata( $user_id );
        $brand   = SEO_Dash_Database::get_setting( 'brand_name', get_bloginfo( 'name' ) );
        $logo      = SEO_Dash_Database::get_setting( 'brand_logo', '' );
        $logo_dark = SEO_Dash_Database::get_setting( 'brand_logo_dark', '' );
        $agency  = SEO_Dash_Database::get_setting( 'agency_url', '' );
        $footer  = SEO_Dash_Database::get_setting( 'footer_text', '' );
        $support = SEO_Dash_Database::get_setting( 'support_email', '' );
        $primary = self::$design['primary_color'] ?? '#6366f1';

        // Which tabs are visible (report meta default).
        $show = [
            'overview'   => (bool)( $meta['show_overview']   ?? true ),
            'analytics'  => (bool)( $meta['show_analytics']  ?? true ),
            'sc'         => (bool)( $meta['show_sc']         ?? true ),
            'service'    => (bool)( $meta['show_service']    ?? true ),
            'blog'       => (bool)( $meta['show_blog']       ?? true ),
            'gmb'        => (bool)( $meta['show_gmb']        ?? true ),
            'technical'  => (bool)( $meta['show_technical']  ?? true ),
            'backlinks'  => (bool)( $meta['show_backlinks']  ?? true ),
            'leads'      => (bool)( $meta['show_leads']      ?? true ),
            'ai'         => (bool)( $meta['show_ai']         ?? true ),
            'account'    => (bool)( $meta['show_account']    ?? true ),
        ];

        // Apply visibility overrides if provided.
        if ( ! empty( $override_show ) ) {
            foreach ( $show as $k => $v ) {
                if ( isset( $override_show[$k] ) && ! $override_show[$k] ) {
                    $show[$k] = false;
                }
            }
        }

        // ── Overview tab: section visibility + per-section config ──────────
        // Controlled from the admin Report → Overview → "Front Tabs" sub-tab.
        $ov_show = [
            'kpis'        => (bool) ( $meta['show_ov_kpis']        ?? true ),
            'charts'      => (bool) ( $meta['show_ov_charts']      ?? true ),
            'table'       => (bool) ( $meta['show_ov_table']       ?? true ),
            'screenshots' => (bool) ( $meta['show_ov_screenshots'] ?? true ),
            'summary'     => (bool) ( $meta['show_ov_summary']     ?? true ),
        ];

        // Per-panel section toggles (the "Show Section" checkbox on each panel header)
        $ov_section_vis = [
            'sections'    => (bool) ( $meta['show_ov_sections']            ?? true ),
            'kpi_section' => (bool) ( $meta['show_ov_kpi_section']         ?? true ),
            'charts_sec'  => (bool) ( $meta['show_ov_charts_section']      ?? true ),
            'table_sec'   => (bool) ( $meta['show_ov_table_section']       ?? true ),
            'ss_sec'      => (bool) ( $meta['show_ov_screenshots_section'] ?? true ),
            'summary_sec' => (bool) ( $meta['show_ov_summary_section']     ?? true ),
        ];
        // If the "Overview Sections" panel toggle is off, hide every sub-section
        if ( ! $ov_section_vis['sections'] ) {
            $ov_show['kpis']        = false;
            $ov_show['charts']      = false;
            $ov_show['table']       = false;
            $ov_show['screenshots'] = false;
            $ov_show['summary']     = false;
        }
        // Individual panel-level toggles override their own section
        if ( ! $ov_section_vis['kpi_section'] )  $ov_show['kpis']        = false;
        if ( ! $ov_section_vis['charts_sec'] )   $ov_show['charts']      = false;
        if ( ! $ov_section_vis['table_sec'] )    $ov_show['table']       = false;
        if ( ! $ov_section_vis['ss_sec'] )       $ov_show['screenshots'] = false;
        if ( ! $ov_section_vis['summary_sec'] )  $ov_show['summary']     = false;
        $ov_kpi_cfg   = get_option( "seo_dash_overview_kpis_{$rid}", [] );
        $ov_table_cfg = get_option( "seo_dash_overview_table_cols_{$rid}", [] );
        $ov_ss_cfg    = get_option( "seo_dash_overview_screenshots_{$rid}", [] );

        // ── Analytics tab: section visibility + per-section config ─────────
        // Controlled from the admin Report → Client Dashboard → "Analytics" sub-tab.
        $an_show = [
            'kpis'       => (bool) ( $meta['show_an_kpis']       ?? true ),
            'chart'      => (bool) ( $meta['show_an_chart']      ?? true ),
            'table'      => (bool) ( $meta['show_an_table']      ?? true ),
            'pagedetail' => (bool) ( $meta['show_an_pagedetail'] ?? true ),
        ];
        // Per-panel section toggles (the "Show Section" checkbox on each panel header)
        $an_section_vis = [
            'sections'        => (bool) ( $meta['show_an_sections']          ?? true ),
            'kpi_section'     => (bool) ( $meta['show_an_kpi_section']       ?? true ),
            'chart_sec'       => (bool) ( $meta['show_an_chart_section']     ?? true ),
            'table_sec'       => (bool) ( $meta['show_an_table_section']     ?? true ),
            'pagedetail_sec'  => (bool) ( $meta['show_an_pagedetail_section']?? true ),
        ];
        // If the master "Analytics Sections" toggle is off, hide every sub-section
        if ( ! $an_section_vis['sections'] ) {
            $an_show['kpis']       = false;
            $an_show['chart']      = false;
            $an_show['table']      = false;
            $an_show['pagedetail'] = false;
        }
        // Individual panel-level toggles override their own section
        if ( ! $an_section_vis['kpi_section'] )    $an_show['kpis']       = false;
        if ( ! $an_section_vis['chart_sec'] )      $an_show['chart']      = false;
        if ( ! $an_section_vis['table_sec'] )      $an_show['table']      = false;
        if ( ! $an_section_vis['pagedetail_sec'] ) $an_show['pagedetail'] = false;
        $an_kpi_cfg = get_option( "seo_dash_analytics_kpis_{$rid}", [] );

        // ── Search Console tab: section visibility + per-section config ────
        // Controlled from the admin Report → Client Dashboard → "Search Console" sub-tab.
        $scn_show = [
            'kpis'       => (bool) ( $meta['show_scn_kpis']       ?? true ),
            'chart'      => (bool) ( $meta['show_scn_chart']      ?? true ),
            'table'      => (bool) ( $meta['show_scn_table']      ?? true ),
            'pagedetail' => (bool) ( $meta['show_scn_pagedetail'] ?? true ),
        ];
        $scn_section_vis = [
            'sections'       => (bool) ( $meta['show_scn_sections']          ?? true ),
            'kpi_section'    => (bool) ( $meta['show_scn_kpi_section']       ?? true ),
            'chart_sec'      => (bool) ( $meta['show_scn_chart_section']     ?? true ),
            'table_sec'      => (bool) ( $meta['show_scn_table_section']     ?? true ),
            'pagedetail_sec' => (bool) ( $meta['show_scn_pagedetail_section']?? true ),
        ];
        if ( ! $scn_section_vis['sections'] ) {
            $scn_show['kpis']       = false;
            $scn_show['chart']      = false;
            $scn_show['table']      = false;
            $scn_show['pagedetail'] = false;
        }
        if ( ! $scn_section_vis['kpi_section'] )    $scn_show['kpis']       = false;
        if ( ! $scn_section_vis['chart_sec'] )      $scn_show['chart']      = false;
        if ( ! $scn_section_vis['table_sec'] )      $scn_show['table']      = false;
        if ( ! $scn_section_vis['pagedetail_sec'] ) $scn_show['pagedetail'] = false;
        $scn_kpi_cfg = get_option( "seo_dash_sc_kpis_{$rid}", [] );

        // ── Service Pages tab: section visibility + per-section config ─────
        // Controlled from the admin Report → Client Dashboard → "Service Pages" sub-tab.
        $sp_show = [
            'kpis'       => (bool) ( $meta['show_sp_kpis']       ?? true ),
            'chart'      => (bool) ( $meta['show_sp_chart']      ?? true ),
            'table'      => (bool) ( $meta['show_sp_table']      ?? true ),
            'pagedetail' => (bool) ( $meta['show_sp_pagedetail'] ?? true ),
        ];
        $sp_section_vis = [
            'sections'       => (bool) ( $meta['show_sp_sections']          ?? true ),
            'kpi_section'    => (bool) ( $meta['show_sp_kpi_section']       ?? true ),
            'chart_sec'      => (bool) ( $meta['show_sp_chart_section']     ?? true ),
            'table_sec'      => (bool) ( $meta['show_sp_table_section']     ?? true ),
            'pagedetail_sec' => (bool) ( $meta['show_sp_pagedetail_section']?? true ),
        ];
        if ( ! $sp_section_vis['sections'] ) {
            $sp_show['kpis']       = false;
            $sp_show['chart']      = false;
            $sp_show['table']      = false;
            $sp_show['pagedetail'] = false;
        }
        if ( ! $sp_section_vis['kpi_section'] )    $sp_show['kpis']       = false;
        if ( ! $sp_section_vis['chart_sec'] )      $sp_show['chart']      = false;
        if ( ! $sp_section_vis['table_sec'] )      $sp_show['table']      = false;
        if ( ! $sp_section_vis['pagedetail_sec'] ) $sp_show['pagedetail'] = false;
        $sp_kpi_cfg   = get_option( "seo_dash_sp_kpis_{$rid}", [] );

        // ── Blog Posts tab: section visibility + per-section config ────────
        // Controlled from the admin Report → Client Dashboard → "Blog Posts" sub-tab.
        $bl_show = [
            'kpis'       => (bool) ( $meta['show_bl_kpis']       ?? true ),
            'chart'      => (bool) ( $meta['show_bl_chart']      ?? true ),
            'table'      => (bool) ( $meta['show_bl_table']      ?? true ),
            'pagedetail' => (bool) ( $meta['show_bl_pagedetail'] ?? true ),
        ];
        $bl_section_vis = [
            'sections'       => (bool) ( $meta['show_bl_sections']          ?? true ),
            'kpi_section'    => (bool) ( $meta['show_bl_kpi_section']       ?? true ),
            'chart_sec'      => (bool) ( $meta['show_bl_chart_section']     ?? true ),
            'table_sec'      => (bool) ( $meta['show_bl_table_section']     ?? true ),
            'pagedetail_sec' => (bool) ( $meta['show_bl_pagedetail_section']?? true ),
        ];
        if ( ! $bl_section_vis['sections'] ) {
            $bl_show['kpis']       = false;
            $bl_show['chart']      = false;
            $bl_show['table']      = false;
            $bl_show['pagedetail'] = false;
        }
        if ( ! $bl_section_vis['kpi_section'] )    $bl_show['kpis']       = false;
        if ( ! $bl_section_vis['chart_sec'] )      $bl_show['chart']      = false;
        if ( ! $bl_section_vis['table_sec'] )      $bl_show['table']      = false;
        if ( ! $bl_section_vis['pagedetail_sec'] ) $bl_show['pagedetail'] = false;
        $bl_kpi_cfg   = get_option( "seo_dash_blog_kpis_{$rid}", [] );

        // ── Google Business (GMB) tab: section visibility + per-section config ──
        // Controlled from the admin Report → Client Dashboard → "Google Business" sub-tab.
        $gmb_show = [
            'kpis'       => (bool) ( $meta['show_gmb_kpis']       ?? true ),
            'details'    => (bool) ( $meta['show_gmb_details']    ?? true ),
            'perf_chart' => (bool) ( $meta['show_gmb_perf_chart'] ?? true ),
            'perf_table' => (bool) ( $meta['show_gmb_perf_table'] ?? true ),
            'posts_chart'=> (bool) ( $meta['show_gmb_posts_chart']?? true ),
            'posts_table'=> (bool) ( $meta['show_gmb_posts_table']?? true ),
        ];
        $gmb_section_vis = [
            'sections'        => (bool) ( $meta['show_gmb_sections']           ?? true ),
            'kpi_section'     => (bool) ( $meta['show_gmb_kpi_section']        ?? true ),
            'details_sec'     => (bool) ( $meta['show_gmb_details_section']    ?? true ),
            'perf_chart_sec'  => (bool) ( $meta['show_gmb_perf_chart_section'] ?? true ),
            'perf_table_sec'  => (bool) ( $meta['show_gmb_perf_table_section'] ?? true ),
            'posts_chart_sec' => (bool) ( $meta['show_gmb_posts_chart_section']?? true ),
            'posts_table_sec' => (bool) ( $meta['show_gmb_posts_table_section']?? true ),
        ];
        if ( ! $gmb_section_vis['sections'] ) {
            $gmb_show['kpis']        = false;
            $gmb_show['details']     = false;
            $gmb_show['perf_chart']  = false;
            $gmb_show['perf_table']  = false;
            $gmb_show['posts_chart'] = false;
            $gmb_show['posts_table'] = false;
        }
        if ( ! $gmb_section_vis['kpi_section'] )     $gmb_show['kpis']        = false;
        if ( ! $gmb_section_vis['details_sec'] )     $gmb_show['details']     = false;
        if ( ! $gmb_section_vis['perf_chart_sec'] )  $gmb_show['perf_chart']  = false;
        if ( ! $gmb_section_vis['perf_table_sec'] )  $gmb_show['perf_table']  = false;
        if ( ! $gmb_section_vis['posts_chart_sec'] ) $gmb_show['posts_chart'] = false;
        if ( ! $gmb_section_vis['posts_table_sec'] ) $gmb_show['posts_table'] = false;
        $gmb_kpi_cfg = get_option( "seo_dash_gmb_kpis_{$rid}", [] );



        $tab_labels = [
            'overview'  => 'Overview',
            'analytics' => 'Analytics',
            'sc'        => 'Search Console',
            'service'   => 'Service Pages',
            'blog'      => 'Blog Posts',
            'gmb'       => 'Google Business',
            'technical' => 'Technical',
            'backlinks' => 'Backlinks',
            'leads'     => 'Leads',
            'ai'        => 'AI Assistant',
            'account'   => 'Account',
        ];

        $logout_url  = wp_logout_url( get_permalink( intval( get_option( 'seo_dash_client_page_id' ) ) ) );
        $months_ga   = SEO_Dash_Database::get_months( SEO_Dash_Database::$data_ga,        $rid );
        $months_sc   = SEO_Dash_Database::get_months( SEO_Dash_Database::$data_sc,        $rid );
        $months_sv   = SEO_Dash_Database::get_months( SEO_Dash_Database::$data_pages,     $rid );
        $months_gmb  = SEO_Dash_Database::get_months( SEO_Dash_Database::$data_gmb,       $rid );
        $months_bk   = SEO_Dash_Database::get_months( SEO_Dash_Database::$data_backlinks, $rid );
        $months_ld   = SEO_Dash_Database::get_months( SEO_Dash_Database::$data_leads,     $rid );
        $months_tc   = SEO_Dash_Database::get_months( SEO_Dash_Database::$data_technical, $rid );
        // Summary is authored via the classic (TinyMCE) editor in admin and
        // stored as sanitized HTML (see seo_dash_save_report_meta / AI
        // generation). Re-run through wp_kses_post defensively before output.
        $raw_summary = $meta['summary'] ?? '';
        if ( $raw_summary !== '' && strip_tags( $raw_summary ) === $raw_summary ) {
            // Older summaries (saved before the Markdown→HTML conversion was
            // added) are stored as raw Markdown or plain text with no HTML
            // tags at all — run those through the converter so headings,
            // bold text, lists, tables and paragraph breaks still display
            // correctly without needing to be regenerated.
            $raw_summary = function_exists( 'seo_dash_markdown_to_html' ) ? seo_dash_markdown_to_html( $raw_summary ) : nl2br( esc_html( $raw_summary ) );
        }
        $summary     = $raw_summary !== '' ? wp_kses_post( $raw_summary ) : '';

        $assigned_reports_list = [];
        if ( count( self::$report_ids ) > 1 ) {
            global $wpdb;
            $ids_csv = implode( ',', self::$report_ids );
            $assigned_reports_list = $wpdb->get_results( "SELECT id, title FROM " . SEO_Dash_Database::$reports . " WHERE id IN ($ids_csv)", ARRAY_A );
        }

        // ── KPI computations for Overview tab ──────────────────────────────
        global $wpdb;

        // 1. Overall traffic — from the admin "Overview" option
        $ov_overall_opt   = get_option( "seo_dash_overview_overall_{$rid}", [] );
        $kpi_overall_traffic = intval( $ov_overall_opt['traffic'] ?? 0 );

        // 2. Last 30-day traffic — from the most recent entry in monthly overview
        $ov_monthly_opt  = get_option( "seo_dash_overview_monthly_{$rid}", [] );
        $kpi_30d_traffic = 0;
        if ( ! empty( $ov_monthly_opt ) && is_array( $ov_monthly_opt ) ) {
            // Sort by month descending and take the latest value
            usort( $ov_monthly_opt, fn($a,$b) => strcmp( $b['month'] ?? '', $a['month'] ?? '' ) );
            $kpi_30d_traffic = intval( $ov_monthly_opt[0]['traffic'] ?? 0 );
        }

        // Fallback: also try period_type='30d' from GA database if above is empty
        if ( $kpi_30d_traffic === 0 ) {
            $kpi_30d_traffic = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(users) FROM " . SEO_Dash_Database::$data_ga .
                " WHERE report_id = %d AND period_type = '30d' AND trashed = 0",
                $rid
            ) );
        }
        if ( $kpi_overall_traffic === 0 ) {
            $kpi_overall_traffic = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(users) FROM " . SEO_Dash_Database::$data_ga .
                " WHERE report_id = %d AND period_type = 'overall' AND trashed = 0",
                $rid
            ) );
        }

        // 3. Total backlinks — all non-trashed backlinks (matches admin count)
        $kpi_backlinks = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_backlinks .
            " WHERE report_id = %d AND trashed = 0",
            $rid
        ) );
        // ───────────────────────────────────────────────────────────────────

        // ── Client permissions ────────────────────────────────────────────
        $client_record   = SEO_Dash_Database::get_client_by_user( $user_id );
        $perm_name       = $client_record ? !empty( $client_record['allow_name_change'] )     : true;
        $perm_email      = $client_record ? !empty( $client_record['allow_email_change'] )    : true;
        $perm_password   = $client_record ? !empty( $client_record['allow_password_change'] ) : true;
        $perm_avatar     = $client_record ? !empty( $client_record['allow_avatar_change'] )   : false;
        // ───────────────────────────────────────────────────────────────────

        include SEO_DASH_PATH . 'includes/views/client/' . $template;
    }

    // ── Helper: pages tab panel (service or blog) ──────────────────────────
    public static function pages_panel( string $type, string $label, array $months, int $rid ): string {
        ob_start();
        ?>
        <div class="seo-cl-panel">
            <div class="seo-cl-panel-hd">
                <h3><?php echo esc_html($label); ?></h3>
                <?php if (!empty($months)) : ?>
                <select class="seo-cl-month-sel" data-scope="<?php echo esc_attr($type); ?>">
                    <option value="">All months</option>
                    <?php foreach ($months as $m) : ?>
                    <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html(date_i18n('F Y', strtotime($m.'-01'))); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            <div class="seo-cl-table-wrap">
                <table class="seo-cl-table" id="seo-cl-<?php echo $type; ?>-table">
                    <thead style="background:var(--c-surf2);">
                        <tr>
                            <th rowspan="2" style="vertical-align:bottom;padding-bottom:12px;border-right:1px solid var(--cc-border);">Page</th>
                            <th colspan="3" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">7 DAYS</th>
                            <th colspan="3" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">30 DAYS</th>
                            <th colspan="3" style="text-align:center;border-right:1px solid var(--cc-border);border-bottom:1px solid var(--cc-border);">90 DAYS</th>
                            <th colspan="3" style="text-align:center;border-bottom:1px solid var(--cc-border);">OVERALL</th>
                        </tr>
                        <tr>
                            <?php for($i=0;$i<4;$i++): $br = $i<3 ? 'border-right:1px solid var(--cc-border);' : ''; ?>
                            <th style="font-size:10px;text-align:right;">Sess.</th>
                            <th style="font-size:10px;text-align:right;">Users</th>
                            <th style="font-size:10px;text-align:right;<?php echo $br;?>">Views</th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody class="seo-cl-tbody"><tr><td colspan="13" style="text-align:center;padding:24px;color:var(--cc-subtle);">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
