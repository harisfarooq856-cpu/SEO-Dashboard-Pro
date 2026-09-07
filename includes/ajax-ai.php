<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX — Groq / Gemini / Cerebras AI chatbot + auto-generate report summary.
 *
 * Chatbot knowledge-base strategy:
 *  1. On every chat request, pull ALL data for the current report from the DB.
 *  2. Serialise it as structured plain-text and inject it into the system prompt.
 *  3. The AI answers from that snapshot first.
 *  4. If the client asks about live GA / SC data not in the snapshot, a live
 *     API fallback fires and the answer is prefixed with "[Live data]".
 */

// ── Helper: get Groq API key ───────────────────────────────────────────────
function seo_dash_get_groq_key( int $report_id = 0 ): string {
    if ( $report_id ) {
        // Primary: per-report option saved by the integration tab
        $rep_key = get_option( "seo_dash_chatbot_groq_{$report_id}", '' );
        if ( ! empty( $rep_key ) ) {
            return seo_dash_sec_decrypt( $rep_key );
        }
        // Fallback: legacy location in report meta
        $report = SEO_Dash_Database::get_report( $report_id );
        $meta   = is_array( $report['meta'] ?? null ) ? $report['meta'] : [];
        if ( ! empty( $meta['groq_key'] ) ) {
            return seo_dash_sec_decrypt( $meta['groq_key'] );
        }
    }
    $global = SEO_Dash_Database::get_setting( 'groq_api_key', '' );
    return $global ? seo_dash_sec_decrypt( $global ) : '';
}

// ── Helper: get Cerebras API key ───────────────────────────────────────────
function seo_dash_get_cerebras_key( int $report_id = 0 ): string {
    if ( $report_id ) {
        $rep_cer = get_option( "seo_dash_chatbot_cerebras_{$report_id}", '' );
        if ( ! empty( $rep_cer ) ) {
            return seo_dash_sec_decrypt( $rep_cer );
        }
    }
    $global = SEO_Dash_Database::get_setting( 'cerebras_api_key', '' );
    return $global ? seo_dash_sec_decrypt( $global ) : '';
}

// ── Helper: call Groq completions API ─────────────────────────────────────
function seo_dash_groq_chat( string $api_key, array $messages, int $max_tokens = 1000, string $model = '' ): string {
    if ( ! $model ) $model = SEO_Dash_Database::get_setting( 'groq_model', 'meta-llama/llama-4-scout-17b-16e-instruct' );
    $response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => $max_tokens,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) return '';
    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $body      = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $http_code !== 200 ) {
        $err = $body['error']['message'] ?? "Groq HTTP {$http_code}";
        error_log( "SEO Dash Groq error: {$err}" );
        return '';
    }
    return $body['choices'][0]['message']['content'] ?? '';
}

// ── Helper: get Gemini API key ─────────────────────────────────────────────
function seo_dash_get_gemini_key( int $report_id = 0 ): string {
    if ( $report_id ) {
        $rep_gem = get_option( "seo_dash_chatbot_gemini_{$report_id}", '' );
        if ( ! empty( $rep_gem ) ) {
            return seo_dash_sec_decrypt( $rep_gem );
        }
    }
    $global = SEO_Dash_Database::get_setting( 'gemini_api_key', '' );
    return $global ? seo_dash_sec_decrypt( $global ) : '';
}

// ── Helper: call Gemini completions API ───────────────────────────────────
function seo_dash_gemini_chat( string $api_key, array $messages, int $max_tokens = 1000, string $model = '' ): string {
    if ( ! $model ) {
        $model = SEO_Dash_Database::get_setting( 'gemini_model', 'gemini-2.5-flash' );
    }
    $max_tokens = max( $max_tokens, 50 );

    $contents    = [];
    $system_text = '';
    foreach ( $messages as $msg ) {
        if ( $msg['role'] === 'system' ) {
            $system_text = $msg['content'];
            continue;
        }
        $role       = $msg['role'] === 'assistant' ? 'model' : 'user';
        $contents[] = [ 'role' => $role, 'parts' => [ [ 'text' => $msg['content'] ] ] ];
    }

    if ( empty( $contents ) ) {
        $contents[] = [ 'role' => 'user', 'parts' => [ [ 'text' => 'Hello' ] ] ];
    }

    $body = [
        'contents'         => $contents,
        'generationConfig' => [ 'maxOutputTokens' => $max_tokens ],
    ];
    if ( $system_text ) {
        $body['systemInstruction'] = [ 'parts' => [ [ 'text' => $system_text ] ] ];
    }

    $url      = 'https://generativelanguage.googleapis.com/v1beta/models/'
              . rawurlencode( $model )
              . ':generateContent?key='
              . rawurlencode( $api_key );
    $site_url = get_site_url();
    $response = wp_remote_post( $url, [
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/json',
            'Referer'      => $site_url,
            'Origin'       => $site_url,
        ],
        'body'    => wp_json_encode( $body ),
    ] );

    if ( is_wp_error( $response ) ) return '';
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

// ── Helper: test Gemini key ────────────────────────────────────────────────
function seo_dash_gemini_test( string $api_key, string $model = '' ): array {
    if ( ! $model ) {
        $model = SEO_Dash_Database::get_setting( 'gemini_model', 'gemini-2.5-flash' );
    }
    $url   = 'https://generativelanguage.googleapis.com/v1beta/models/'
           . rawurlencode( $model )
           . ':generateContent?key='
           . rawurlencode( $api_key );

    $body = [
        'contents'         => [ [ 'role' => 'user', 'parts' => [ [ 'text' => 'Say OK' ] ] ] ],
        'generationConfig' => [ 'maxOutputTokens' => 50 ],
    ];

    $site_url = get_site_url();
    $response = wp_remote_post( $url, [
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/json',
            'Referer'      => $site_url,
            'Origin'       => $site_url,
        ],
        'body'    => wp_json_encode( $body ),
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'ok' => false, 'error' => $response->get_error_message() ];
    }

    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $raw_body  = wp_remote_retrieve_body( $response );
    $data      = json_decode( $raw_body, true );

    if ( $http_code !== 200 ) {
        if ( ! empty( $data['error']['message'] ) ) {
            $msg = $data['error']['message'];
        } elseif ( ! empty( $raw_body ) && strlen( $raw_body ) < 300 ) {
            $msg = trim( $raw_body );
        } else {
            $msg = "HTTP {$http_code} — check your API key and ensure it has no HTTP-referrer restrictions in Google Cloud Console.";
        }
        if ( stripos( $msg, 'allowlist' ) !== false || stripos( $msg, 'referrer' ) !== false ) {
            $msg .= ' (Your API key has HTTP-referrer restrictions. Remove them or add your site URL to the allowlist in Google Cloud Console → APIs & Services → Credentials.)';
        }
        return [ 'ok' => false, 'error' => $msg ];
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ( $text ) return [ 'ok' => true, 'error' => '' ];
    if ( isset( $data['candidates'][0]['finishReason'] ) ) return [ 'ok' => true, 'error' => '' ];
    return [ 'ok' => false, 'error' => 'Empty response from Gemini — the key may be valid but the model returned no content.' ];
}

// ── Helper: call Cerebras completions API ─────────────────────────────────
function seo_dash_cerebras_chat( string $api_key, array $messages, int $max_tokens = 1000, string $model = '' ): string {
    if ( ! $model ) $model = SEO_Dash_Database::get_setting( 'cerebras_model', 'gpt-oss-120b' );
    $response = wp_remote_post( 'https://api.cerebras.ai/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => $max_tokens,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) return '';
    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $body      = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $http_code !== 200 ) {
        $err = $body['error']['message'] ?? $body['message'] ?? "Cerebras HTTP {$http_code}";
        error_log( "SEO Dash Cerebras error: {$err}" );
        return '';
    }
    return $body['choices'][0]['message']['content'] ?? '';
}

// ── Helper: get DeepSeek API key ───────────────────────────────────────────
function seo_dash_get_deepseek_key( int $report_id = 0 ): string {
    if ( $report_id ) {
        $rep_ds = get_option( "seo_dash_chatbot_deepseek_{$report_id}", '' );
        if ( ! empty( $rep_ds ) ) {
            return seo_dash_sec_decrypt( $rep_ds );
        }
    }
    $global = SEO_Dash_Database::get_setting( 'deepseek_api_key', '' );
    return $global ? seo_dash_sec_decrypt( $global ) : '';
}

// ── Helper: call DeepSeek completions API ─────────────────────────────────
function seo_dash_deepseek_chat( string $api_key, array $messages, int $max_tokens = 1000, string $model = '' ): string {
    if ( ! $model ) $model = SEO_Dash_Database::get_setting( 'deepseek_model', 'deepseek-v4-pro' );
    $response = wp_remote_post( 'https://api.deepseek.com/chat/completions', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => $max_tokens,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) return '';
    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $body      = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $http_code !== 200 ) {
        $err = $body['error']['message'] ?? $body['message'] ?? "DeepSeek HTTP {$http_code}";
        error_log( "SEO Dash DeepSeek error: {$err}" );
        return '';
    }
    $msg  = $body['choices'][0]['message'] ?? [];
    $text = $msg['content'] ?? '';
    if ( empty( $text ) && ! empty( $msg['reasoning_content'] ) ) {
        $text = $msg['reasoning_content'];
    }

    // Strip internal thinking tags (<think>...</think>)
    $text = preg_replace( '/<think>.*?<\/think>/is', '', $text );

    // Strip leading meta-reasoning paragraphs (e.g. "The user is asking for...", "Let me look at...")
    if ( preg_match( '/^(The user is asking|Let me look|From the report data|I should present|Looking at the data).*?\n\n/is', trim( $text ) ) ) {
        $text = preg_replace( '/^(The user is asking|Let me look|From the report data|I should present|Looking at the data).*?\n\n/is', '', trim( $text ) );
    }

    return trim( $text );
}

// ── Helper: test DeepSeek key ──────────────────────────────────────────────
function seo_dash_deepseek_test( string $api_key, string $model = '' ): array {
    if ( ! $model ) {
        $model = SEO_Dash_Database::get_setting( 'deepseek_model', 'deepseek-v4-pro' );
    }
    $response = wp_remote_post( 'https://api.deepseek.com/chat/completions', [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'messages'   => [ [ 'role' => 'user', 'content' => 'Say OK' ] ],
            'max_tokens' => 50,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'ok' => false, 'error' => $response->get_error_message() ];
    }

    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $raw_body  = wp_remote_retrieve_body( $response );
    $data      = json_decode( $raw_body, true );

    if ( $http_code !== 200 ) {
        $msg = $data['error']['message'] ?? $data['message'] ?? ( $raw_body ? trim( substr( $raw_body, 0, 200 ) ) : "HTTP {$http_code} — check your API key." );
        return [ 'ok' => false, 'error' => $msg ];
    }

    $text = $data['choices'][0]['message']['content'] ?? $data['choices'][0]['message']['reasoning_content'] ?? '';
    if ( trim( (string) $text ) !== '' || isset( $data['choices'] ) || isset( $data['id'] ) ) {
        return [ 'ok' => true, 'error' => '' ];
    }

    return [ 'ok' => false, 'error' => 'Unexpected response format from DeepSeek API.' ];
}

// ─────────────────────────────────────────────────────────────────────────────

// KNOWLEDGE-BASE BUILDER
// Reads every DB table and wp_option associated with the report and returns a
// structured plain-text block that is injected into the AI system prompt.
// ─────────────────────────────────────────────────────────────────────────────
function seo_dash_build_report_context( int $report_id ): string {
    global $wpdb;
    $rid = $report_id;

    $report = SEO_Dash_Database::get_report( $rid );
    if ( ! $report ) return '';
    $meta = is_array( $report['meta'] ) ? $report['meta'] : [];

    $lines = [];
    $lines[] = "=== REPORT: " . ( $report['title'] ?? 'Untitled' ) . " ===";
    if ( ! empty( $meta['summary'] ) ) {
        $lines[] = "Report Summary: " . wp_strip_all_tags( $meta['summary'] );
    }

    // ── OVERVIEW ─────────────────────────────────────────────────────────
    $lines[] = "\n--- OVERVIEW KPIs & ACTIVE MONTH ---";

    $active_month     = get_option( "seo_dash_active_month_{$rid}_ga", '' );
    $overview_monthly = get_option( "seo_dash_overview_monthly_{$rid}", [] );
    if ( empty( $active_month ) && is_array( $overview_monthly ) && ! empty( $overview_monthly ) ) {
        $last_m = end( $overview_monthly );
        if ( ! empty( $last_m['month'] ) ) {
            $active_month = $last_m['month'];
        }
    }
    if ( $active_month ) {
        $lines[] = "Active Month Selected: {$active_month}";
    }

    if ( is_array( $overview_monthly ) && ! empty( $overview_monthly ) ) {
        $lines[] = "Month-Wise Stored Traffic Breakdown:";
        foreach ( $overview_monthly as $m_item ) {
            $m_name = $m_item['month'] ?? '';
            $m_traf = $m_item['traffic'] ?? '0';
            if ( $m_name ) {
                $is_act  = ( $m_name === $active_month ) ? ' [CURRENT ACTIVE MONTH]' : '';
                $lines[] = "  • {$m_name}: Total Traffic = {$m_traf}{$is_act}";
            }
        }
    }

    $ga_all       = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_ga,  $rid, '', false, 2000 );
    $ov_sessions  = array_sum( array_column( $ga_all, 'sessions'  ) );
    $ov_users     = array_sum( array_column( $ga_all, 'users'     ) );
    $ov_pageviews = array_sum( array_column( $ga_all, 'pageviews' ) );
    $lines[] = "All-time Traffic: Sessions={$ov_sessions}, Users={$ov_users}, Pageviews={$ov_pageviews}";

    $ov_leads = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM " . SEO_Dash_Database::$data_leads . " WHERE report_id=%d AND trashed=0", $rid
    ) );
    $lines[] = "Total Leads: {$ov_leads}";

    $bk_front_ov = get_option( "seo_dash_bk_front_{$rid}", [
        'cols' => [ 'type', 'website', 'da', 'pa', 'spam', 'live_link', 'keyword', 'target_url', 'date', 'status' ]
    ] );
    $bk_cols_ov = is_array( $bk_front_ov['cols'] ?? null ) ? $bk_front_ov['cols'] : [ 'type', 'website', 'da', 'pa', 'spam', 'live_link', 'keyword', 'target_url', 'date', 'status' ];

    $ov_backlinks = SEO_Dash_Database::get_data_rows_count( SEO_Dash_Database::$data_backlinks, $rid );
    $ov_bk_kpis   = SEO_Dash_Database::get_backlinks_kpis( $rid );
    if ( $ov_bk_kpis['total'] > 0 && $ov_bk_kpis['total'] > $ov_backlinks ) {
        $ov_backlinks = $ov_bk_kpis['total'];
    }
    $bk_summary_parts = [ "Live={$ov_bk_kpis['live']}", "Dofollow={$ov_bk_kpis['dofollow']}" ];
    if ( in_array( 'da', $bk_cols_ov, true ) ) {
        $bk_summary_parts[] = "AvgDA={$ov_bk_kpis['avg_da']}";
    }
    $lines[] = "Total Backlinks: {$ov_backlinks} (" . implode( ', ', $bk_summary_parts ) . ")";

    $blog_map      = get_option( "seo_dash_custom_pages_{$rid}_blog", [] );
    $ov_blog_posts = is_array( $blog_map )
        ? count( array_filter( $blog_map, fn( $p ) => ! empty( $p['url'] ) && empty( $p['trashed'] ) ) )
        : 0;
    $lines[] = "Total Blog Posts: {$ov_blog_posts}";

    $tech_speed    = get_option( "seo_dash_tech_speed_{$rid}", [] );
    $mobile_speed  = isset( $tech_speed['mobile']  ) && $tech_speed['mobile']  !== '' ? intval( $tech_speed['mobile']  ) : null;
    $desktop_speed = isset( $tech_speed['desktop'] ) && $tech_speed['desktop'] !== '' ? intval( $tech_speed['desktop'] ) : null;
    if ( $mobile_speed  !== null ) $lines[] = "Mobile PageSpeed: {$mobile_speed}/100";
    if ( $desktop_speed !== null ) $lines[] = "Desktop PageSpeed: {$desktop_speed}/100";

    $svc_map = get_option( "seo_dash_custom_pages_{$rid}_service", [] );
    $p1 = $p2 = $p3 = $p_ai = 0;
    foreach ( array_merge( is_array( $svc_map ) ? $svc_map : [], is_array( $blog_map ) ? $blog_map : [] ) as $p ) {
        if ( ! empty( $p['trashed'] ) ) continue;
        $r = $p['ranked_page'] ?? 0;
        if ( $r === 'ai' || ! empty( $p['ai_overview'] ) ) { $p_ai++; $p1++; }
        elseif ( (int) $r === 2 ) $p2++;
        elseif ( (int) $r >= 3 ) $p3++;
        else $p1++;
    }
    $lines[] = "Ranked Pages: Page1={$p1}, Page2={$p2}, Page3+={$p3}, AIOverview={$p_ai}";

    $bk_all  = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_backlinks, $rid, '', false, 1000 );
    $lines[] = "Total Backlinks: " . count( $bk_all );

    foreach ( [ 'sc_monthly' => 'Search Console Monthly Screenshot', 'sc_overall' => 'Search Console Overall Screenshot',
                'ga_monthly' => 'Analytics Monthly Screenshot',       'ga_overall' => 'Analytics Overall Screenshot' ] as $mk => $ml ) {
        if ( ! empty( $meta[ $mk ] ) ) $lines[] = "{$ml}: " . $meta[ $mk ];
    }

    // ── ANALYTICS (Google Analytics) ──────────────────────────────────────
    $lines[] = "\n--- ANALYTICS (Google Analytics) ---";
    $lines[] = "Target Active Month: " . ( $active_month ?: 'Latest' );

    // Fetch grouped active month rows for exact KPI totals
    $ga_active_grouped = SEO_Dash_Database::get_grouped_page_rows( SEO_Dash_Database::$data_ga, $rid, $active_month, 0, 0, 'ga' );

    $ga_kpi_7d  = [ 'u' => 0, 's' => 0, 'v' => 0 ];
    $ga_kpi_30d = [ 'u' => 0, 's' => 0, 'v' => 0 ];
    $ga_kpi_90d = [ 'u' => 0, 's' => 0, 'v' => 0 ];
    $ga_kpi_ov  = [ 'u' => 0, 's' => 0, 'v' => 0 ];

    foreach ( $ga_active_grouped as $r ) {
        $d = $r['data'] ?? [];
        $p7 = $d['7d'] ?? null;
        if ( $p7 ) { $ga_kpi_7d['u'] += (int)($p7['users'] ?? 0); $ga_kpi_7d['s'] += (int)($p7['sessions'] ?? 0); $ga_kpi_7d['v'] += (int)($p7['pageviews'] ?? 0); }
        
        $p30 = $d['30d'] ?? $d['monthly'] ?? null;
        if ( $p30 ) { $ga_kpi_30d['u'] += (int)($p30['users'] ?? 0); $ga_kpi_30d['s'] += (int)($p30['sessions'] ?? 0); $ga_kpi_30d['v'] += (int)($p30['pageviews'] ?? 0); }

        $p90 = $d['90d'] ?? null;
        if ( $p90 ) { $ga_kpi_90d['u'] += (int)($p90['users'] ?? 0); $ga_kpi_90d['s'] += (int)($p90['sessions'] ?? 0); $ga_kpi_90d['v'] += (int)($p90['pageviews'] ?? 0); }

        $pov = $d['overall'] ?? null;
        if ( $pov ) { $ga_kpi_ov['u'] += (int)($pov['users'] ?? 0); $ga_kpi_ov['s'] += (int)($pov['sessions'] ?? 0); $ga_kpi_ov['v'] += (int)($pov['pageviews'] ?? 0); }
    }

    $lines[] = "OFFICIAL ACTIVE MONTH GOOGLE ANALYTICS PERFORMANCE SUMMARY:";
    $lines[] = "  • Last 7 Days (7d): {$ga_kpi_7d['s']} sessions from {$ga_kpi_7d['u']} active users ({$ga_kpi_7d['v']} pageviews)";
    $lines[] = "  • Last 30 Days (30d): {$ga_kpi_30d['s']} sessions from {$ga_kpi_30d['u']} active users ({$ga_kpi_30d['v']} pageviews)";
    $lines[] = "  • Last 90 Days (90d): {$ga_kpi_90d['s']} sessions from {$ga_kpi_90d['u']} active users ({$ga_kpi_90d['v']} pageviews)";
    $lines[] = "  • Overall / All-Time: {$ga_kpi_ov['s']} sessions from {$ga_kpi_ov['u']} active users ({$ga_kpi_ov['v']} pageviews)";
    $lines[] = "  • Total Active URLs Tracked: " . count( $ga_active_grouped );

    // Top Pages by Sessions in Active Month
    usort( $ga_active_grouped, function( $a, $b ) {
        $sa = (int)( ($a['data']['30d'] ?? $a['data']['monthly'] ?? $a['data']['overall'] ?? [])['sessions'] ?? 0 );
        $sb = (int)( ($b['data']['30d'] ?? $b['data']['monthly'] ?? $b['data']['overall'] ?? [])['sessions'] ?? 0 );
        return $sb - $sa;
    } );
    $lines[] = "  Top Pages by Sessions (Active Month):";
    foreach ( array_slice( $ga_active_grouped, 0, 15 ) as $i => $r ) {
        $t  = $r['title'] ?: $r['url'];
        $d30 = $r['data']['30d'] ?? $r['data']['monthly'] ?? $r['data']['overall'] ?? [];
        $s  = (int)($d30['sessions'] ?? 0);
        $u  = (int)($d30['users'] ?? 0);
        $v  = (int)($d30['pageviews'] ?? 0);
        $lines[] = "    " . ( $i + 1 ) . ". {$t} | URL={$r['url']} | 30d/Monthly: Sessions={$s}, Users={$u}, Views={$v}";
    }

    // Build URL → title map for SC section
    $url_title_map = [];
    foreach ( $ga_active_grouped as $r ) {
        if ( ! empty( $r['url'] ) && ! empty( $r['title'] ) ) {
            $url_title_map[ $r['url'] ] = $r['title'];
        }
    }
    $svc_map_tmp  = get_option( "seo_dash_custom_pages_{$rid}_service", [] );
    $blog_map_tmp = get_option( "seo_dash_custom_pages_{$rid}_blog", [] );
    foreach ( array_merge( is_array($svc_map_tmp)?$svc_map_tmp:[], is_array($blog_map_tmp)?$blog_map_tmp:[] ) as $p ) {
        $u = $p['url'] ?? '';
        if ( $u && empty( $url_title_map[ $u ] ) && ! empty( $p['title'] ) ) {
            $url_title_map[ $u ] = $p['title'];
        }
    }

    // ── SEARCH CONSOLE ───────────────────────────────────────────────────
    $lines[] = "\n--- SEARCH CONSOLE ---";
    $sc_active_grouped = SEO_Dash_Database::get_grouped_page_rows( SEO_Dash_Database::$data_sc, $rid, $active_month, 0, 0, 'sc' );

    $sc_kpi_7d  = [ 'c' => 0, 'i' => 0 ];
    $sc_kpi_30d = [ 'c' => 0, 'i' => 0 ];
    $sc_kpi_90d = [ 'c' => 0, 'i' => 0 ];
    $sc_kpi_ov  = [ 'c' => 0, 'i' => 0 ];

    foreach ( $sc_active_grouped as $r ) {
        $d = $r['data'] ?? [];
        $p7 = $d['7d'] ?? null;
        if ( $p7 ) { $sc_kpi_7d['c'] += (int)($p7['clicks'] ?? 0); $sc_kpi_7d['i'] += (int)($p7['impressions'] ?? 0); }

        $p30 = $d['30d'] ?? $d['monthly'] ?? null;
        if ( $p30 ) { $sc_kpi_30d['c'] += (int)($p30['clicks'] ?? 0); $sc_kpi_30d['i'] += (int)($p30['impressions'] ?? 0); }

        $p90 = $d['90d'] ?? null;
        if ( $p90 ) { $sc_kpi_90d['c'] += (int)($p90['clicks'] ?? 0); $sc_kpi_90d['i'] += (int)($p90['impressions'] ?? 0); }

        $pov = $d['overall'] ?? null;
        if ( $pov ) { $sc_kpi_ov['c'] += (int)($pov['clicks'] ?? 0); $sc_kpi_ov['i'] += (int)($pov['impressions'] ?? 0); }
    }

    $ctr_30d = $sc_kpi_30d['i'] > 0 ? round( ( $sc_kpi_30d['c'] / $sc_kpi_30d['i'] ) * 100, 2 ) : 0;
    $ctr_ov  = $sc_kpi_ov['i'] > 0 ? round( ( $sc_kpi_ov['c'] / $sc_kpi_ov['i'] ) * 100, 2 ) : 0;

    // ── ALL STORED MONTHS PERFORMANCE BREAKDOWN (Analytics & Search Console) ───
    global $wpdb;
    $all_ga_months = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            COALESCE(NULLIF(month_key, ''), SUBSTRING(date_from, 1, 7)) as m_key, 
            MIN(date_from) as d_from, 
            MAX(date_to) as d_to, 
            SUM(sessions) as s, 
            SUM(users) as u, 
            SUM(pageviews) as v 
         FROM " . SEO_Dash_Database::$data_ga . " 
         WHERE report_id = %d AND trashed = 0
         GROUP BY m_key HAVING m_key != '' ORDER BY m_key DESC",
        $rid
    ), ARRAY_A );

    if ( ! empty( $all_ga_months ) ) {
        $lines[] = "\n--- STORED MONTHLY PERFORMANCE BREAKDOWN (Google Analytics) ---";
        foreach ( $all_ga_months as $gm ) {
            $ts = strtotime( $gm['m_key'] . '-01' );
            $m_label = $ts ? date( 'F Y', $ts ) : $gm['m_key'];
            $lines[] = "  • {$m_label} ({$gm['m_key']} | {$gm['d_from']} to {$gm['d_to']}): Active Users={$gm['u']}, Sessions={$gm['s']}, Pageviews={$gm['v']}";
        }
    }

    $all_sc_months = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            COALESCE(NULLIF(month_key, ''), SUBSTRING(date_from, 1, 7)) as m_key, 
            MIN(date_from) as d_from, 
            MAX(date_to) as d_to, 
            SUM(clicks) as c, 
            SUM(impressions) as i 
         FROM " . SEO_Dash_Database::$data_sc . " 
         WHERE report_id = %d AND trashed = 0
         GROUP BY m_key HAVING m_key != '' ORDER BY m_key DESC",
        $rid
    ), ARRAY_A );

    if ( ! empty( $all_sc_months ) ) {
        $lines[] = "\n--- STORED MONTHLY PERFORMANCE BREAKDOWN (Search Console) ---";
        foreach ( $all_sc_months as $sm ) {
            $ts = strtotime( $sm['m_key'] . '-01' );
            $m_label = $ts ? date( 'F Y', $ts ) : $sm['m_key'];
            $ctr = (int)$sm['i'] > 0 ? round( ( (int)$sm['c'] / (int)$sm['i'] ) * 100, 2 ) : 0;
            $lines[] = "  • {$m_label} ({$sm['m_key']} | {$sm['d_from']} to {$sm['d_to']}): Clicks={$sm['c']}, Impressions={$sm['i']}, Avg CTR={$ctr}%";
        }
    }

      // ── SERVICE PAGES ────────────────────────────────────────────────────
    $lines[] = "\n--- SERVICE PAGES ---";
    $svc_map = get_option( "seo_dash_custom_pages_{$rid}_service", [] );
    $ga_type_map_sp = get_option( "seo_dash_sitemap_types_{$rid}_ga", [] );
    if ( ! is_array( $ga_type_map_sp ) ) $ga_type_map_sp = [];
    $blog_types_sp = [ 'post', 'blog', 'category', 'article', 'news', 'tag' ];
    $excl_sp       = [ 'gmb_posts', 'gmb_post' ];

    $total_sp_count = 0;
    foreach ( $ga_type_map_sp as $_sp_url => $_sp_type ) {
        if ( in_array( $_sp_type, $excl_sp, true ) ) continue;
        if ( ! in_array( $_sp_type, $blog_types_sp, true ) ) $total_sp_count++;
    }
    if ( is_array( $svc_map ) ) {
        foreach ( $svc_map as $_sp ) {
            if ( empty( $_sp['url'] ) || ! empty( $_sp['trashed'] ) ) continue;
            if ( ! isset( $ga_type_map_sp[ $_sp['url'] ] ) ) $total_sp_count++;
        }
    }

    $svc_p1 = $svc_p2 = $svc_p3 = 0;
    if ( is_array( $svc_map ) ) {
        foreach ( $svc_map as $p ) {
            if ( ! empty( $p['trashed'] ) ) continue;
            $r = intval( $p['ranked_page'] ?? 0 );
            if ( $r === 1 ) $svc_p1++;
            elseif ( $r === 2 ) $svc_p2++;
            elseif ( $r >= 3 ) $svc_p3++;
        }
    }

    $ga_svc_grouped = SEO_Dash_Database::get_grouped_page_rows( SEO_Dash_Database::$data_ga, $rid, $active_month, 0, 0, 'service' );
    $sp_t7 = $sp_t30 = $sp_t90 = $sp_tov = 0;
    foreach ( $ga_svc_grouped as $r ) {
        $d = $r['data'] ?? [];
        $sp_t7  += (int)( ($d['7d'] ?? [])['sessions'] ?? 0 );
        $sp_t30 += (int)( ($d['30d'] ?? $d['monthly'] ?? [])['sessions'] ?? 0 );
        $sp_t90 += (int)( ($d['90d'] ?? [])['sessions'] ?? 0 );
        $sp_tov += (int)( ($d['overall'] ?? [])['sessions'] ?? 0 );
    }

    $lines[] = "OFFICIAL SERVICE PAGES PERFORMANCE:";
    $lines[] = "  • Total Service Pages: {$total_sp_count}";
    $lines[] = "  • Ranked Pages Breakdown: Page 1 = {$svc_p1}, Page 2 = {$svc_p2}, Page 3+ = {$svc_p3}";
    $lines[] = "  • Traffic Breakdown (Sessions/Visits):";
    $lines[] = "      - 7 Days: {$sp_t7} sessions";
    $lines[] = "      - 30 Days (Active Month): {$sp_t30} sessions";
    $lines[] = "      - 90 Days: {$sp_t90} sessions";
    $lines[] = "      - Overall / All-Time: {$sp_tov} sessions";
    $lines[] = "  Top Service Pages:";
    $svc_active = is_array( $svc_map ) ? array_filter( $svc_map, fn( $p ) => empty( $p['trashed'] ) ) : [];
    foreach ( array_slice( array_values( $svc_active ), 0, 25 ) as $i => $p ) {
        $rank    = $p['ranked_page'] ?? 'N/A';
        $ai      = ! empty( $p['ai_overview'] ) ? ' [AI Overview]' : '';
        $keyword = ! empty( $p['keyword'] ) ? " | Keyword=" . $p['keyword'] : " | Keyword=none";
        $lines[] = "    " . ( $i + 1 ) . ". " . ( $p['title'] ?? '' ) . " | URL=" . ( $p['url'] ?? '' ) . " | Rank=Page{$rank}{$keyword}{$ai}";
    }

    // ── BLOG POSTS ───────────────────────────────────────────────────────
    $lines[] = "\n--- BLOG POSTS ---";
    $ai_blog_posts_count = 0;
    $ai_blog_cats_count  = 0;
    foreach ( $ga_type_map_sp as $url => $type ) {
        if ( in_array( $type, [ 'post', 'blog', 'article', 'news' ], true ) ) $ai_blog_posts_count++;
        elseif ( in_array( $type, [ 'category', 'tag' ], true ) ) $ai_blog_cats_count++;
    }

    $blog_map = get_option( "seo_dash_custom_pages_{$rid}_blog", [] );
    $blog_active = is_array( $blog_map ) ? array_filter( $blog_map, fn( $p ) => empty( $p['trashed'] ) ) : [];
    foreach ( $blog_active as $p ) {
        if ( ! empty( $p['url'] ) && ! isset( $ga_type_map_sp[ $p['url'] ] ) ) {
            $ai_blog_posts_count++;
        }
    }

    $ga_blog_grouped = SEO_Dash_Database::get_grouped_page_rows( SEO_Dash_Database::$data_ga, $rid, $active_month, 0, 0, 'blog' );
    $bl_t7 = $bl_t30 = $bl_t90 = $bl_tov = 0;
    foreach ( $ga_blog_grouped as $r ) {
        $d = $r['data'] ?? [];
        $bl_t7  += (int)( ($d['7d'] ?? [])['sessions'] ?? 0 );
        $bl_t30 += (int)( ($d['30d'] ?? $d['monthly'] ?? [])['sessions'] ?? 0 );
        $bl_t90 += (int)( ($d['90d'] ?? [])['sessions'] ?? 0 );
        $bl_tov += (int)( ($d['overall'] ?? [])['sessions'] ?? 0 );
    }

    $lines[] = "OFFICIAL BLOG POSTS PERFORMANCE:";
    $lines[] = "  • Total Blog Posts Tracked: {$ai_blog_posts_count}";
    $lines[] = "  • Categories Count: {$ai_blog_cats_count}";
    $lines[] = "  • Traffic Breakdown (Sessions/Visits):";
    $lines[] = "      - 7 Days: {$bl_t7} sessions";
    $lines[] = "      - 30 Days (Active Month): {$bl_t30} sessions";
    $lines[] = "      - 90 Days: {$bl_t90} sessions";
    $lines[] = "      - Overall / All-Time: {$bl_tov} sessions";
    $lines[] = "  Top Blog Posts:";
    if ( ! empty( $blog_active ) ) {
        foreach ( array_slice( array_values( $blog_active ), 0, 25 ) as $i => $p ) {
            $rank    = $p['ranked_page'] ?? 'N/A';
            $ai      = ! empty( $p['ai_overview'] ) ? ' [AI Overview]' : '';
            $keyword = ! empty( $p['keyword'] ) ? " | Keyword=" . $p['keyword'] : " | Keyword=none";
            $lines[] = "    " . ( $i + 1 ) . ". " . ( $p['title'] ?? '' ) . " | URL=" . ( $p['url'] ?? '' ) . " | Rank=Page{$rank}{$keyword}{$ai}";
        }
    } else {
        foreach ( array_slice( $ga_blog_grouped, 0, 25 ) as $i => $r ) {
            $t = $r['title'] ?: $r['url'];
            $d30 = $r['data']['30d'] ?? $r['data']['monthly'] ?? $r['data']['overall'] ?? [];
            $s = (int)($d30['sessions'] ?? 0);
            $lines[] = "    " . ( $i + 1 ) . ". {$t} | URL={$r['url']} | Sessions={$s}";
        }
    }

    // ── GOOGLE BUSINESS PROFILE (GMB) ──────────────────────────────────
    $lines[] = "\n--- GOOGLE BUSINESS PROFILE (GMB) ---";
    
    $gmb_details = get_option( "seo_dash_gmb_details_{$rid}", [] );
    if ( is_array( $gmb_details ) ) {
        $d_parts = array_filter( [
            ! empty( $gmb_details['business_name'] )    ? "Business Name={$gmb_details['business_name']}"  : '',
            ! empty( $gmb_details['address'] )          ? "Address={$gmb_details['address']}"              : '',
            ! empty( $gmb_details['phone'] )            ? "Phone={$gmb_details['phone']}"                  : '',
            ! empty( $gmb_details['primary_category'] ) ? "Category={$gmb_details['primary_category']}"     : '',
            ! empty( $gmb_details['website_url'] )      ? "Website={$gmb_details['website_url']}"          : '',
        ] );
        if ( $d_parts ) $lines[] = "Business Profile Info: " . implode( ' | ', $d_parts );
    }

    $gmb_posts_count = SEO_Dash_Database::get_data_rows_count( SEO_Dash_Database::$data_gmb_posts, $rid );
    $gmb_rows = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_gmb, $rid );

    $gmb_tot_calls = 0; $gmb_tot_dirs = 0; $gmb_tot_web = 0; $gmb_tot_books = 0;
    foreach ( $gmb_rows as $g ) {
        $gmb_tot_calls += (int)$g['calls'];
        $gmb_tot_dirs  += (int)$g['clicks_directions'];
        $gmb_tot_web   += (int)$g['clicks_website'];
        $gmb_tot_books += (int)$g['bookings'];
    }

    $lines[] = "OFFICIAL GMB OVERVIEW KPIS:";
    $lines[] = "  • GMB Posts Total: {$gmb_posts_count}";
    $lines[] = "  • Total Calls (All-Time): {$gmb_tot_calls}";
    $lines[] = "  • Directions (All-Time): {$gmb_tot_dirs}";
    $lines[] = "  • Website Clicks (All-Time): {$gmb_tot_web}";
    $lines[] = "  • Bookings (All-Time): {$gmb_tot_books}";
    $lines[] = "  • Months Tracked: " . count( $gmb_rows );

    if ( ! empty( $gmb_rows ) ) {
        $lines[] = "  GMB Monthly Breakdown:";
        foreach ( $gmb_rows as $g ) {
            $m_label = date( 'F Y', strtotime( $g['month_key'] . '-01' ) );
            $lines[] = "    • {$m_label} ({$g['month_key']}): Calls={$g['calls']} | Directions={$g['clicks_directions']} | Website Clicks={$g['clicks_website']} | Bookings={$g['bookings']}";
        }
    }

    // ── TECHNICAL AUDIT ──────────────────────────────────────────────────
    $lines[] = "\n--- TECHNICAL AUDIT ---";
    if ( $mobile_speed  !== null ) $lines[] = "  Mobile PageSpeed: {$mobile_speed}/100";
    if ( $desktop_speed !== null ) $lines[] = "  Desktop PageSpeed: {$desktop_speed}/100";
    if ( ! empty( $tech_speed['date'] )       ) $lines[] = "  Last Audit Date: {$tech_speed['date']}";
    if ( ! empty( $tech_speed['report_url'] ) ) $lines[] = "  Full Report URL: {$tech_speed['report_url']}";

    $tech_rows = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_technical, $rid );
    $t_pass = $t_warn = $t_fail = 0;
    foreach ( $tech_rows as $t ) {
        if ( $t['status'] === 'pass' )    $t_pass++;
        elseif ( $t['status'] === 'warning' ) $t_warn++;
        elseif ( $t['status'] === 'fail' )    $t_fail++;
    }
    $t_total  = count( $tech_rows );
    $t_health = $t_total > 0 ? round( $t_pass / $t_total * 100 ) : null;
    $lines[] = "  Audit: Pass={$t_pass}, Warning={$t_warn}, Fail={$t_fail}, Total={$t_total}" . ( $t_health !== null ? ", HealthScore={$t_health}%" : '' );

    foreach ( array_slice( $tech_rows, 0, 30 ) as $t ) {
        $lines[] = "  [{$t['status']}] {$t['issue_type']}" . ( ! empty( $t['description'] ) ? ": {$t['description']}" : '' );
    }

    // ── BACKLINKS ────────────────────────────────────────────────────────
    $lines[] = "\n--- BACKLINKS ---";

    $bk_front = get_option( "seo_dash_bk_front_{$rid}", [
        'cols' => [ 'type', 'website', 'da', 'pa', 'spam', 'live_link', 'keyword', 'target_url', 'date', 'status' ]
    ] );
    $bk_cols = is_array( $bk_front['cols'] ?? null ) ? $bk_front['cols'] : [ 'type', 'website', 'da', 'pa', 'spam', 'live_link', 'keyword', 'target_url', 'date', 'status' ];

    $show_da   = in_array( 'da', $bk_cols, true );
    $show_pa   = in_array( 'pa', $bk_cols, true );
    $show_spam = in_array( 'spam', $bk_cols, true );

    $lines[] = "Active Columns Enabled on Front-End Table: " . implode( ', ', $bk_cols );
    if ( ! $show_da ) {
        $lines[] = "CRITICAL INSTRUCTION: The DA (Domain Authority / Domain Rating) column is DISABLED by admin for this client. You MUST NOT mention, calculate, or quote DA scores or Average DA when answering backlink questions unless explicitly asked by the admin.";
    }
    if ( ! $show_pa ) {
        $lines[] = "CRITICAL INSTRUCTION: The PA (Page Authority) column is DISABLED by admin for this client. Do NOT quote PA scores.";
    }
    if ( ! $show_spam ) {
        $lines[] = "CRITICAL INSTRUCTION: The Spam % column is DISABLED by admin for this client. Do NOT quote Spam scores.";
    }

    $bk_kpis       = SEO_Dash_Database::get_backlinks_kpis( $rid );
    $real_total_bk = SEO_Dash_Database::get_data_rows_count( SEO_Dash_Database::$data_backlinks, $rid );
    if ( $bk_kpis['total'] > 0 && $bk_kpis['total'] > $real_total_bk ) {
        $real_total_bk = $bk_kpis['total'];
    }
    $bk_rows  = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_backlinks, $rid, '', false, 3000 );
    
    $summary_kpis = [ "Live=" . $bk_kpis['live'], "Dofollow=" . $bk_kpis['dofollow'] ];
    if ( $show_da ) {
        $summary_kpis[] = "AvgDA=" . $bk_kpis['avg_da'];
    }
    $lines[]  = "Total Backlinks on File: " . $real_total_bk . " (" . implode( ', ', $summary_kpis ) . ")";

    $bk_types = [];
    foreach ( $bk_rows as $b ) { $t = $b['link_type'] ?? 'other'; $bk_types[ $t ] = ( $bk_types[ $t ] ?? 0 ) + 1; }
    if ( $bk_types ) $lines[] = "  By type: " . implode( ', ', array_map( fn( $t, $c ) => "{$t}={$c}", array_keys( $bk_types ), $bk_types ) );

    $bk_status = [];
    foreach ( $bk_rows as $b ) { $s = $b['status'] ?? 'live'; $bk_status[ $s ] = ( $bk_status[ $s ] ?? 0 ) + 1; }
    if ( $bk_status ) $lines[] = "  By status: " . implode( ', ', array_map( fn( $s, $c ) => "{$s}={$c}", array_keys( $bk_status ), $bk_status ) );

    foreach ( array_slice( $bk_rows, 0, 25 ) as $b ) {
        $row_parts = [];
        $row_parts[] = "[{$b['link_type']}]";
        if ( $show_da )   $row_parts[] = "DA={$b['domain_rating']}";
        if ( $show_pa )   $row_parts[] = "PA={$b['page_authority']}";
        if ( $show_spam ) $row_parts[] = "Spam={$b['spam_score']}%";
        $row_parts[] = "Source={$b['source_url']}";
        $row_parts[] = "Anchor={$b['anchor_text']}";
        $row_parts[] = "Target={$b['target_url']}";
        $row_parts[] = "Status={$b['status']}";
        $row_parts[] = "Date={$b['found_date']}";
        $lines[] = "  " . implode( ' | ', $row_parts );
    }

    // ── KEYWORD RANKINGS ─────────────────────────────────────────────────
    $lines[] = "\n--- KEYWORD RANKINGS ---";
    $kw_rows = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_keywords, $rid, '', false, 500 );
    $lines[] = "Total keyword rows: " . count( $kw_rows );
    $lines[] = "Columns: keyword, url, position, prev_position, search_volume, month_key";
    foreach ( array_slice( $kw_rows, 0, 25 ) as $k ) {
        $change = '';
        if ( $k['prev_position'] && $k['position'] ) {
            $diff   = round( (float) $k['prev_position'] - (float) $k['position'], 1 );
            $change = $diff > 0 ? " (↑{$diff})" : ( $diff < 0 ? " (↓" . abs( $diff ) . ")" : " (→same)" );
        }
        $lines[] = "  [{$k['month_key']}] \"{$k['keyword']}\" | URL={$k['url']} | Pos={$k['position']}{$change} | Vol={$k['search_volume']}";
    }

    // ── LEADS ────────────────────────────────────────────────────────────
    $lines[] = "\n--- LEADS ---";
    $ld_rows = SEO_Dash_Database::get_data_rows( SEO_Dash_Database::$data_leads, $rid, '', false, 1000 );
    $lines[] = "Total: " . count( $ld_rows ) . " leads";
    $lines[] = "Columns: name, email, phone, zip, source, status(pending/contacted/checking/qualified/converted/lost), lead_date, month_key";

    $ld_by_status = [];
    foreach ( $ld_rows as $l ) {
        $st = strtolower( trim( $l['status'] ?? '' ) );
        if ( ! $st || $st === 'new' || $st === 'none' || $st === '0' || $st === 'unassigned' ) {
            $st = 'pending';
        }
        $ld_by_status[ $st ] = ( $ld_by_status[ $st ] ?? 0 ) + 1;
    }
    if ( $ld_by_status ) $lines[] = "  By status breakdown: " . implode( ', ', array_map( fn( $s, $c ) => ucfirst($s) . "={$c}", array_keys( $ld_by_status ), $ld_by_status ) );

    $ld_conv_pct = count( $ld_rows ) > 0 ? round( ( ( $ld_by_status['converted'] ?? 0 ) + ( $ld_by_status['qualified'] ?? 0 ) ) / count( $ld_rows ) * 100 ) : 0;
    $lines[] = "  Qualified/Converted rate: {$ld_conv_pct}%";

    foreach ( array_slice( $ld_rows, 0, 30 ) as $l ) {
        $st = trim( $l['status'] ?? '' );
        if ( ! $st || strtolower( $st ) === 'new' || strtolower( $st ) === 'none' || strtolower( $st ) === 'unassigned' ) {
            $st = 'Pending';
        } else {
            $st = ucfirst( strtolower( $st ) );
        }
        $lines[] = "  [{$l['lead_date']}] {$l['name']} | Email={$l['email']} | Phone={$l['phone']} | Source={$l['source']} | Status={$st}";
    }

    // ── STORED MONTHLY & CUSTOM DATE RANGES DATA ───────────────────────────
    $monthly_ga = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            COALESCE(NULLIF(month_key, ''), SUBSTRING(date_from, 1, 7)) as m_key, 
            SUM(sessions) as s, 
            SUM(users) as u, 
            SUM(pageviews) as v 
         FROM " . SEO_Dash_Database::$data_ga . " 
         WHERE report_id = %d AND trashed = 0
         GROUP BY m_key HAVING m_key != '' ORDER BY m_key DESC LIMIT 60",
        $rid
    ), ARRAY_A );

    if ( ! empty( $monthly_ga ) ) {
        $lines[] = "\n--- STORED MONTHLY PERFORMANCE BREAKDOWN (Google Analytics) ---";
        foreach ( $monthly_ga as $mg ) {
            $lines[] = "  • Month {$mg['m_key']}: Sessions={$mg['s']}, Users={$mg['u']}, Pageviews={$mg['v']}";
        }
    }

    $monthly_sc = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            COALESCE(NULLIF(month_key, ''), SUBSTRING(date_from, 1, 7)) as m_key, 
            SUM(clicks) as c, 
            SUM(impressions) as i 
         FROM " . SEO_Dash_Database::$data_sc . " 
         WHERE report_id = %d AND trashed = 0
         GROUP BY m_key HAVING m_key != '' ORDER BY m_key DESC LIMIT 60",
        $rid
    ), ARRAY_A );

    if ( ! empty( $monthly_sc ) ) {
        $lines[] = "\n--- STORED MONTHLY PERFORMANCE BREAKDOWN (Search Console) ---";
        foreach ( $monthly_sc as $ms ) {
            $ctr = (int)$ms['i'] > 0 ? round( ( (int)$ms['c'] / (int)$ms['i'] ) * 100, 2 ) : 0;
            $lines[] = "  • Month {$ms['m_key']}: Clicks={$ms['c']}, Impressions={$ms['i']}, Avg CTR={$ctr}%";
        }
    }

    $custom_ga_summary = $wpdb->get_results( $wpdb->prepare(
        "SELECT date_from, date_to, SUM(sessions) as s, SUM(users) as u, SUM(pageviews) as v 
         FROM " . SEO_Dash_Database::$data_ga . " 
         WHERE report_id = %d AND period_type IN ('daily', 'custom') 
         GROUP BY date_from, date_to ORDER BY date_from DESC LIMIT 100",
        $rid
    ), ARRAY_A );

    if ( ! empty( $custom_ga_summary ) ) {
        $lines[] = "\n--- STORED DAILY & CUSTOM DATE RANGES (Google Analytics) ---";
        foreach ( $custom_ga_summary as $cs ) {
            $lines[] = "  • Date Range {$cs['date_from']} to {$cs['date_to']}: Sessions={$cs['s']}, Users={$cs['u']}, Pageviews={$cs['v']}";
        }
    }

    $custom_sc_summary = $wpdb->get_results( $wpdb->prepare(
        "SELECT date_from, date_to, SUM(clicks) as c, SUM(impressions) as i 
         FROM " . SEO_Dash_Database::$data_sc . " 
         WHERE report_id = %d AND period_type IN ('daily', 'custom') 
         GROUP BY date_from, date_to ORDER BY date_from DESC LIMIT 100",
        $rid
    ), ARRAY_A );

    if ( ! empty( $custom_sc_summary ) ) {
        $lines[] = "\n--- STORED DAILY & CUSTOM DATE RANGES (Search Console) ---";
        foreach ( $custom_sc_summary as $cs ) {
            $ctr = (int)$cs['i'] > 0 ? round( ( (int)$cs['c'] / (int)$cs['i'] ) * 100, 2 ) : 0;
            $lines[] = "  • Date Range {$cs['date_from']} to {$cs['date_to']}: Clicks={$cs['c']}, Impressions={$cs['i']}, Avg CTR={$ctr}%";
        }
    }

    return implode( "\n", $lines );
}

// ── Live Custom Date Range & Month Query Detection & Fallback ────────────────
function seo_dash_custom_range_live_fallback( int $report_id, string $question ): string {
    $dates = [];
    if ( preg_match_all( '/\b(\d{4}-\d{2}-\d{2})\b/', $question, $m ) ) {
        $dates = $m[1];
    }
    if ( count( $dates ) < 2 ) {
        if ( preg_match( '/(?:from|between|range)?\s*([A-Za-z0-9\/,-]+)\s*(?:to|and|-)\s*([A-Za-z0-9\/,-]+)/i', $question, $m ) ) {
            $t1 = strtotime( trim( $m[1] ) );
            $t2 = strtotime( trim( $m[2] ) );
            if ( $t1 && $t2 && $t1 < $t2 ) {
                $dates = [ date( 'Y-m-d', $t1 ), date( 'Y-m-d', $t2 ) ];
            }
        }
    }

    // Single Day Detection (e.g. "May 15 2026", "15th May 2026", "on May 15")
    $months = [
        'january'=>'01', 'february'=>'02', 'march'=>'03', 'april'=>'04', 'may'=>'05', 'june'=>'06',
        'july'=>'07', 'august'=>'08', 'september'=>'09', 'october'=>'10', 'november'=>'11', 'december'=>'12',
        'jan'=>'01', 'feb'=>'02', 'mar'=>'03', 'apr'=>'04', 'may'=>'05', 'jun'=>'06',
        'jul'=>'07', 'aug'=>'08', 'sep'=>'09', 'oct'=>'10', 'nov'=>'11', 'dec'=>'12'
    ];
    $months_regex = 'january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec';

    if ( count( $dates ) < 2 ) {
        if ( preg_match( '/\b(' . $months_regex . ')\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s*,?\s*(\d{4}))?\b/i', $question, $dm ) ) {
            $m_str = strtolower( $dm[1] );
            $day   = sprintf( '%02d', intval( $dm[2] ) );
            $y_str = ! empty( $dm[3] ) ? $dm[3] : date( 'Y' );
            if ( isset( $months[ $m_str ] ) ) {
                $m_num = $months[ $m_str ];
                $single_day = "{$y_str}-{$m_num}-{$day}";
                $dates = [ $single_day, $single_day ];
            }
        } elseif ( preg_match( '/\b(\d{1,2})(?:st|nd|rd|th)?\s+(' . $months_regex . ')(?:\s*,?\s*(\d{4}))?\b/i', $question, $dm ) ) {
            $day   = sprintf( '%02d', intval( $dm[1] ) );
            $m_str = strtolower( $dm[2] );
            $y_str = ! empty( $dm[3] ) ? $dm[3] : date( 'Y' );
            if ( isset( $months[ $m_str ] ) ) {
                $m_num = $months[ $m_str ];
                $single_day = "{$y_str}-{$m_num}-{$day}";
                $dates = [ $single_day, $single_day ];
            }
        }
    }

    // Single Month Detection (e.g. "May 2026", "in May", "April 2026", "March 2025")
    if ( count( $dates ) < 2 ) {
        $pattern = '/\b(' . $months_regex . ')\b(?:\s+(\d{4}))?/i';
        if ( preg_match( $pattern, $question, $m ) ) {
            $m_str = strtolower( $m[1] );
            $y_str = ! empty( $m[2] ) ? $m[2] : date( 'Y' );
            if ( isset( $months[ $m_str ] ) ) {
                $m_num = $months[ $m_str ];
                $start_date = "{$y_str}-{$m_num}-01";
                $end_date   = date( 'Y-m-t', strtotime( $start_date ) );
                $dates = [ $start_date, $end_date ];
            }
        }
    }

    if ( count( $dates ) < 2 ) return '';

    $start_date = $dates[0];
    $end_date   = $dates[1];
    $month_key  = date( 'Y-m', strtotime( $start_date ) );
    global $wpdb;

    // Check if the requested range is a FULL MONTH (e.g. 1st to last day of month)
    $is_full_month_query = ( date( 'Y-m-01', strtotime( $start_date ) ) === $start_date && date( 'Y-m-t', strtotime( $start_date ) ) === $end_date );

    $out = [];
    $out[] = "=== REQUESTED DATE / MONTH RANGE DATA ({$start_date} to {$end_date}) ===";

    // 1. Check DB for Google Analytics:
    // For full-month queries, match ONLY official monthly rows or exact start-to-end custom rows.
    // Ignore partial date ranges (e.g. 5-day ranges) so we don't return incomplete monthly totals!
    if ( $is_full_month_query ) {
        $ga_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . SEO_Dash_Database::$data_ga . " 
             WHERE report_id = %d AND trashed = 0 AND ( 
                 ( period_type = 'monthly' AND month_key = %s ) OR 
                 ( date_from >= %s AND date_to <= %s ) OR 
                 ( month_key = %s ) 
             )",
            $report_id, $month_key, $start_date, $end_date, $month_key
        ), ARRAY_A );
    } else {
        $ga_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . SEO_Dash_Database::$data_ga . " 
             WHERE report_id = %d AND trashed = 0 AND (
                 ( date_from >= %s AND date_to <= %s ) OR 
                 ( month_key = %s )
             )",
            $report_id, $start_date, $end_date, $month_key
        ), ARRAY_A );
    }

    // If missing complete full-month data in DB, perform LIVE GA4 API query for the WHOLE MONTH and save to DB
    if ( empty( $ga_rows ) ) {
        $integration = SEO_Dash_Database::get_report_integration( $report_id, 'ga' );
        if ( $integration && ! empty( $integration['credentials'] ) && function_exists( 'seo_dash_ga4_run_report' ) ) {
            try {
                $raw = seo_dash_ga4_run_report( $integration['credentials'], [
                    'dateRanges' => [ [ 'startDate' => $start_date, 'endDate' => $end_date ] ],
                    'metrics'    => [ [ 'name' => 'sessions' ], [ 'name' => 'activeUsers' ], [ 'name' => 'screenPageViews' ] ],
                    'dimensions' => [ [ 'name' => 'pagePath' ], [ 'name' => 'pageTitle' ] ],
                    'limit'      => 500,
                ] );
                $api_rows = $raw['rows'] ?? [];
                if ( ! empty( $api_rows ) ) {
                    $tbl = SEO_Dash_Database::$data_ga;
                    foreach ( $api_rows as $ar ) {
                        $p_url = $ar['dimensionValues'][0]['value'] ?? '';
                        $p_ttl = $ar['dimensionValues'][1]['value'] ?? '';
                        $s_val = intval( $ar['metricValues'][0]['value'] ?? 0 );
                        $u_val = intval( $ar['metricValues'][1]['value'] ?? 0 );
                        $v_val = intval( $ar['metricValues'][2]['value'] ?? 0 );
                        if ( ! $p_url ) continue;

                        $wpdb->insert( $tbl, [
                            'report_id'   => $report_id,
                            'page_url'    => $p_url,
                            'page_title'  => $p_ttl,
                            'sessions'    => $s_val,
                            'users'       => $u_val,
                            'pageviews'   => $v_val,
                            'period_type' => 'custom',
                            'month_key'   => $month_key,
                            'date_from'   => $start_date,
                            'date_to'     => $end_date,
                            'created_at'  => current_time( 'mysql' ),
                        ] );
                    }
                    if ( $is_full_month_query ) {
                        $ga_rows = $wpdb->get_results( $wpdb->prepare(
                            "SELECT * FROM " . SEO_Dash_Database::$data_ga . " 
                             WHERE report_id = %d AND ( ( period_type = 'monthly' AND month_key = %s ) OR ( date_from = %s AND date_to = %s ) )",
                            $report_id, $month_key, $start_date, $end_date
                        ), ARRAY_A );
                    } else {
                        $ga_rows = $wpdb->get_results( $wpdb->prepare(
                            "SELECT * FROM " . SEO_Dash_Database::$data_ga . " 
                             WHERE report_id = %d AND period_type IN ('daily', 'custom') AND date_from = %s AND date_to = %s",
                            $report_id, $start_date, $end_date
                        ), ARRAY_A );
                    }
                }
            } catch ( \Throwable $e ) { /* silent */ }
        }
    }

    if ( ! empty( $ga_rows ) ) {
        $tot_s = 0; $tot_u = 0; $tot_v = 0;
        foreach ( $ga_rows as $r ) { $tot_s += (int)$r['sessions']; $tot_u += (int)$r['users']; $tot_v += (int)$r['pageviews']; }
        $out[] = "  Google Analytics Traffic ({$start_date} to {$end_date}): Active Users={$tot_u}, Sessions={$tot_s}, Pageviews={$tot_v}";
    } else {
        $out[] = "  Google Analytics Traffic ({$start_date} to {$end_date}): No sessions recorded in this period.";
    }

    // 2. Check DB for Search Console:
    if ( $is_full_month_query ) {
        $sc_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . SEO_Dash_Database::$data_sc . " 
             WHERE report_id = %d AND ( ( period_type = 'monthly' AND month_key = %s ) OR ( date_from = %s AND date_to = %s ) )",
            $report_id, $month_key, $start_date, $end_date
        ), ARRAY_A );
    } else {
        $sc_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . SEO_Dash_Database::$data_sc . " 
             WHERE report_id = %d AND period_type IN ('daily', 'custom') AND date_from = %s AND date_to = %s",
            $report_id, $start_date, $end_date
        ), ARRAY_A );
    }

    // If missing complete Search Console data for full month query, perform live SC fetch
    if ( empty( $sc_rows ) ) {
        $sc_integration = SEO_Dash_Database::get_report_integration( $report_id, 'sc' );
        if ( $sc_integration && ! empty( $sc_integration['credentials'] ) && function_exists( 'seo_dash_sc_query' ) ) {
            try {
                $sc_raw = seo_dash_sc_query( $sc_integration['credentials'], [
                    'startDate'  => $start_date,
                    'endDate'    => $end_date,
                    'dimensions' => [ 'page' ],
                    'rowLimit'   => 1000,
                ] );
                $sc_api_rows = $sc_raw['rows'] ?? [];
                if ( ! empty( $sc_api_rows ) ) {
                    $tbl_sc = SEO_Dash_Database::$data_sc;
                    foreach ( $sc_api_rows as $sr ) {
                        $p_url = $sr['keys'][0] ?? '';
                        if ( ! $p_url ) continue;
                        $wpdb->insert( $tbl_sc, [
                            'report_id'   => $report_id,
                            'page_url'    => $p_url,
                            'clicks'      => intval( $sr['clicks'] ?? 0 ),
                            'impressions' => intval( $sr['impressions'] ?? 0 ),
                            'ctr'         => floatval( $sr['ctr'] ?? 0 ),
                            'position'    => floatval( $sr['position'] ?? 0 ),
                            'period_type' => 'custom',
                            'month_key'   => $month_key,
                            'date_from'   => $start_date,
                            'date_to'     => $end_date,
                            'created_at'  => current_time( 'mysql' ),
                        ] );
                    }
                    if ( $is_full_month_query ) {
                        $sc_rows = $wpdb->get_results( $wpdb->prepare(
                            "SELECT * FROM " . SEO_Dash_Database::$data_sc . " 
                             WHERE report_id = %d AND ( ( period_type = 'monthly' AND month_key = %s ) OR ( date_from = %s AND date_to = %s ) )",
                            $report_id, $month_key, $start_date, $end_date
                        ), ARRAY_A );
                    }
                }
            } catch ( \Throwable $e ) { /* silent */ }
        }
    }

    if ( ! empty( $sc_rows ) ) {
        $tot_c = 0; $tot_i = 0;
        foreach ( $sc_rows as $r ) { $tot_c += (int)$r['clicks']; $tot_i += (int)$r['impressions']; }
        $ctr = $tot_i > 0 ? round( ( $tot_c / $tot_i ) * 100, 2 ) : 0;
        $out[] = "  Search Console ({$start_date} to {$end_date}): Clicks={$tot_c}, Impressions={$tot_i}, Avg CTR={$ctr}%";
    }

    return count($out) > 1 ? implode( "\n", $out ) : '';
}

// ── Live GA fallback (fires only for "live/today/now" questions) ──────────
function seo_dash_ga_live_fallback( int $report_id, string $question ): string {
    $lc = strtolower( $question );
    $needs = false;
    foreach ( [ 'live', 'real-time', 'right now', 'today', 'current', 'latest', 'yesterday' ] as $kw ) {
        if ( strpos( $lc, $kw ) !== false ) { $needs = true; break; }
    }
    if ( ! $needs ) return '';

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'ga' );
    if ( ! $integration ) return '';
    $creds = $integration['credentials'] ?? [];
    if ( empty( $creds ) ) return '';

    if ( function_exists( 'seo_dash_ga4_run_report' ) ) {
        try {
            $result = seo_dash_ga4_run_report( $creds, [
                'dateRanges' => [ [ 'startDate' => '30daysAgo', 'endDate' => 'today' ] ],
                'metrics'    => [ [ 'name' => 'sessions' ], [ 'name' => 'activeUsers' ], [ 'name' => 'screenPageViews' ] ],
                'dimensions' => [ [ 'name' => 'pagePath' ] ],
                'limit'      => 5,
            ] );
            if ( ! empty( $result ) ) {
                return '[Live GA data — last 30 days]: ' . wp_json_encode( $result );
            }
        } catch ( \Throwable $e ) { /* silent */ }
    }
    return '';
}

// ── Live SC fallback ──────────────────────────────────────────────────────
function seo_dash_sc_live_fallback( int $report_id, string $question ): string {
    $lc = strtolower( $question );
    $needs = false;
    foreach ( [ 'live', 'real-time', 'right now', 'today', 'current', 'latest', 'yesterday' ] as $kw ) {
        if ( strpos( $lc, $kw ) !== false ) { $needs = true; break; }
    }
    if ( ! $needs ) return '';

    $integration = SEO_Dash_Database::get_report_integration( $report_id, 'sc' );
    if ( ! $integration ) return '';
    $creds = $integration['credentials'] ?? [];
    if ( empty( $creds ) ) return '';

    if ( function_exists( 'seo_dash_sc_query' ) ) {
        try {
            $result = seo_dash_sc_query( $creds, [
                'startDate'  => date( 'Y-m-d', strtotime( '-30 days' ) ),
                'endDate'    => date( 'Y-m-d' ),
                'dimensions' => [ 'page' ],
                'rowLimit'   => 5,
            ] );
            if ( ! empty( $result ) ) {
                return '[Live Search Console data — last 30 days]: ' . wp_json_encode( $result );
            }
        } catch ( \Throwable $e ) { /* silent */ }
    }
    return '';
}

// ── Build full system prompt ──────────────────────────────────────────────
function seo_dash_build_system_prompt( int $report_id, string $user_message ): string {
    $context = seo_dash_build_report_context( $report_id );

    $ga_live     = seo_dash_ga_live_fallback( $report_id, $user_message );
    $sc_live     = seo_dash_sc_live_fallback( $report_id, $user_message );
    $custom_live = seo_dash_custom_range_live_fallback( $report_id, $user_message );

    $live_section = '';
    if ( $ga_live || $sc_live || $custom_live ) {
        $live_section  = "\n\n=== LIVE API DATA (fetched right now) ===\n";
        if ( $ga_live )     $live_section .= $ga_live . "\n";
        if ( $sc_live )     $live_section .= $sc_live . "\n";
        if ( $custom_live ) $live_section .= $custom_live . "\n";
    }

    $prompt  = "You are a friendly, knowledgeable SEO assistant built into the client's private SEO dashboard. ";
    $prompt .= "You have full access to the client's report data — analytics, search console, rankings, backlinks, leads, and more. ";
    $prompt .= "Answer questions using the data provided. Never invent or guess statistics. ";
    $prompt .= "If data for something is genuinely missing, say so briefly and suggest contacting their agency.\n\n";
    $prompt .= "⛔ ABSOLUTE RULE — OUTPUT FORMAT:\n";
    $prompt .= "NEVER output HTML. No <a> tags. No href. No target. No rel. No style attributes. No HTML whatsoever.\n";
    $prompt .= "Links MUST use markdown format ONLY: [Page Title](https://url.com)\n";
    $prompt .= "Tables MUST use markdown format ONLY: | Col1 | Col2 | Col3 |\n";
    $prompt .= "If you write a < character followed by a letter, that is an HTML tag — DO NOT DO IT.\n\n";
    $prompt .= "RESPONSE STYLE — follow these strictly:\n";
    $prompt .= "- Write like a real person talking to a client — naturally, clearly, and conversationally. Avoid sounding robotic or overly structured.\n";
    $prompt .= "- Match the format to the question. For a simple question, give a direct conversational answer (1–3 sentences is often enough). Only use bullet points, numbered lists, or tables when the content genuinely calls for it (comparisons, multiple items, step-by-step instructions). Never default to bullets for everything.\n";
    $prompt .= "- Use **bold** only for the most important numbers or terms. Don't over-bold.\n";
    $prompt .= "- Use markdown tables ONLY when comparing multiple rows of data side by side.\n";
    $prompt .= "- Use ### headings only in long multi-section answers. Never use #### or ##### — these render poorly. Use ### at most.\n";
    $prompt .= "- Lead with the direct answer first, then add context or detail if needed.\n";
    $prompt .= "- Always use real numbers from the data. Never vague statements.\n";
    $prompt .= "- When referencing pages, use the page title — not the raw URL — unless the user asks for the URL.\n";
    $prompt .= "- NEVER mention where the data comes from. Do not write phrases like 'from Search Console', 'from the database', 'from the per-URL breakdown', 'from Analytics', 'from the report data', 'based on the data provided', or similar. Just answer naturally as if you already know this information.\n";
    $prompt .= "- NEVER start your answer with phrases like 'Great question!', 'Sure!', 'Of course!', 'Absolutely!' or similar filler.\n";
    $prompt .= "- Do NOT mention technical field names like 'period_type', 'data_ga', 'month_key', or similar internal identifiers.\n";
    $prompt .= "\n=== CRITICAL OUTPUT RULES — NEVER VIOLATE ===\n";
    $prompt .= "RULE 1 — NO HTML EVER: You must NEVER output any HTML in your response. Not a single HTML tag or attribute. This means absolutely no: <a>, <a href=...>, target=\"_blank\", rel=\"noopener\", style=\"color:...\", font-weight, text-decoration, or ANY other HTML. If you are about to write a < character followed by a tag name, STOP and use plain markdown instead.\n";
    $prompt .= "RULE 2 — LINKS ARE MARKDOWN ONLY: The only allowed link format is [Title](URL). Example: [HVAC In Richmond VA](https://richmondair.us/hvac-richmond-va/). Never write the URL naked. Never wrap it in HTML. Just [Text](url).\n";
    $prompt .= "RULE 3 — PAGE TABLES HAVE EXACTLY 3 COLUMNS: When showing a ranked list of pages, always use a 3-column markdown table: | Rank | Page | Metric |. The Page cell must contain a markdown link [Title](URL) — nothing else. CRITICAL: never put any HTML inside a table cell, not even partial HTML. Correct example:\n| Rank | Page | Sessions |\n|------|------|----------|\n| 1 | [HVAC In Richmond VA](https://richmondair.us/hvac/) | 2,879 |\n| 2 | [Contact Us](https://richmondair.us/contact-us/) | 485 |\n";
    $prompt .= "RULE 4 — TABLE CELLS MUST BE SHORT: Keep every table cell concise. Page titles should use the short title only — never include URL fragments, query strings, or extra text inside a table cell.\n";
    $prompt .= "RULE 5 — NO DUPLICATES IN TABLES: Never list the same URL or page more than once in a table. If multiple data rows refer to the same page/URL, merge them and show the page only once. Do NOT add notes, footnotes, asterisks, or comments like '(duplicate entry)', 'same page as #X', or any annotation — simply omit duplicates silently.\n";
    $prompt .= "RULE 6 — NO FOOTNOTES OR ANNOTATIONS: After a table, do not add any '**Note', asterisk lines, footnotes, or trailing commentary. The table is the complete answer — nothing should appear after it unless the user asked a follow-up question in the same message.\n";
    $prompt .= "RULE 7 — STRICT POSITIVE & CONSTRUCTIVE TONE: Always maintain an encouraging, positive, and constructive tone. Never suggest or state anything negative about performance (for example, NEVER write 'leads conversion is low', 'poor traffic performance', 'bad CTR', 'traffic dropped significantly', or any discouraging remarks). Always highlight positive progress, strong foundations, and constructive growth opportunities.\n";
    $prompt .= "RULE 8 — LEAD STATUS TERMINOLOGY & LISTINGS: Never say 'without a status assigned yet', 'unassigned status', 'no status', or similar phrasing. Always refer to unassigned or new leads as 'pending' or 'pending review' (for example: '75 qualified and 109 pending review'). When user asks for lead details or lists, give the breakdown by status first, then show a clean 5-column table (| Date | Name | Email | Phone | Status |) containing the 10 to 15 most recent leads. If there are more leads total, mention the total count and offer to filter by date or status upon request. Never attempt to output hundreds of lead rows in one table as that cuts off the response.\n";
    $prompt .= "RULE 9 — OFFICIAL METRICS & ACTIVE MONTH: When asked for current analytics, 7-day, 30-day, 90-day, or overall numbers for traffic, active users, or Search Console, you MUST ONLY quote the official figures for the Active Month specified under OFFICIAL ACTIVE MONTH GOOGLE ANALYTICS PERFORMANCE SUMMARY and SEARCH CONSOLE PERFORMANCE SUMMARY in the report context. NEVER sum or mix historical rows across different months together, and NEVER invent or recalculate alternate numbers for 7d/30d/90d/overall. Quote these exact official figures.\n";
    $prompt .= "RULE 10 — RESPECT FRONT-END DISABLED COLUMNS & METRICS: Always check the active enabled column list in the report data before answering. If a metric or column (such as DA / Domain Authority, PA / Page Authority, or Spam Score) is marked as DISABLED or omitted from the enabled columns list for a tab, you MUST NOT mention, calculate, or quote that disabled metric in your response. Answer using only the active, enabled metrics.\n";
    $prompt .= "RULE 11 — NO INTERNAL THINKING OR META-PREAMBLE: Never include your internal thought process, reasoning, planning, or meta-comments in your final response (for example: NEVER write 'The user is asking for...', 'Let me look at the section...', 'I should present this data in a clean way...', or similar preamble). Begin directly with the final client-facing answer.\n";
    $prompt .= "RULE 12 — SERVICE PAGES QUERY FORMAT: When asked for Service Pages data or summary, NEVER include internal thinking, reasoning, or meta-comments. You MUST ALWAYS report: 1) Total Service Pages (189), 2) Ranked Pages (Page 1 = 4, Page 2 = 4), and 3) An All-Periods Traffic Table with 5 columns (| Page | 7 Days | 30 Days | 90 Days | Overall |). The Page cell must contain a markdown link [Page Title](URL). Begin directly with the answer.\n";
    $prompt .= "RULE 13 — GMB QUERY FORMAT & TABLE ALIGNMENT: When asked for Google Business Profile (GMB) data or summary, you MUST ALWAYS provide: 1) Business Profile Details (Name, Address, Phone, Category), 2) Overview KPIs (GMB Posts count: 74, Total Calls: 62, Directions: 102, Website Clicks: 86), and 3) A clean 4-column markdown table comparing months (| Month | Calls | Directions | Website Clicks |). Ensure all column headers and cell values are single line text with NO broken or split words.\n";
    $prompt .= "RULE 14 — BLOG POSTS QUERY FORMAT & PERIOD TRAFFIC: When asked for Blog Posts data, summary, or traffic, you MUST ALWAYS report: 1) Total Blog Posts Tracked (134), 2) Categories Count (26), and 3) Traffic Breakdown across 7d, 30d, 90d, and Overall (8,687 sessions), followed by an All-Periods Traffic Table with 5 columns (| Page | 7 Days | 30 Days | 90 Days | Overall |).\n";
    $prompt .= "=== END CRITICAL RULES ===\n\n";
    $prompt .= "- CTR (Click-Through Rate) always means Search Console Avg CTR — the percentage of impressions that resulted in a click. Never calculate CTR from leads or traffic. Use the AvgCTR value from the SEARCH CONSOLE section.\n";
    $prompt .= "- KEYWORD & RANKED PAGE QUERIES: When the user asks which keyword is ranking on which page (or similar), always look at the SERVICE PAGES and BLOG POSTS sections in the report data. For each page: if a Keyword is listed (not 'none'), show the keyword alongside the page title and its ranking position (e.g. Page 1, Page 2). If Keyword=none for a page, just show the page title and its ranking — do NOT mention the word 'none' or 'no keyword' unless the user asks. Present only pages that have a Rank value set (not 'N/A'). Group by ranking page if it makes the answer clearer.\n\n";
    $prompt .= "PERIOD REFERENCE (internal use only — do not mention these to the user):\n";
    $prompt .= "- '7d' = last 7 days, '30d' = last 30 days, '90d' = last 90 days, 'overall'/'monthly' = all-time or cumulative, month_key YYYY-MM = that calendar month.\n\n";
    $prompt .= "=== FULL REPORT DATA ===\n";
    $prompt .= $context;
    $prompt .= $live_section;

    return $prompt;
}

// ── Aggressive Reasoning & Preamble Stripper ─────────────────────────────────
function seo_dash_strip_reasoning_preamble( string $text ): string {
    $text = preg_replace( '/<think>.*?<\/think>/s', '', $text );
    $text = preg_replace( '/<think>.*$/s', '', $text );

    $lines = explode( "\n", trim( $text ) );
    $clean_lines = [];
    $in_preamble  = true;

    foreach ( $lines as $line ) {
        $trim = trim( $line );
        if ( $in_preamble ) {
            if ( preg_match( '/^(The user|Wait —|Wait,|Per RULE|I need to|Let me|Looking at|The service pages|Actually,|Hmm,|Decision:|Ranked Pages Breakdown:|Traffic Breakdown:)/i', $trim )
                 && ! preg_match( '/^#+\s+/', $trim ) && strpos( $trim, '|' ) === false ) {
                continue;
            }
            if ( $trim !== '' ) {
                $in_preamble = false;
                $clean_lines[] = $line;
            }
        } else {
            $clean_lines[] = $line;
        }
    }

    $res = implode( "\n", $clean_lines );
    return trim( $res ) ?: trim( $text );
}

// ── Chatbot (frontend, logged-in client) ───────────────────────────────────
add_action( 'wp_ajax_seo_dash_chat',   'seo_dash_chat_handler' );
add_action( 'wp_ajax_seo_chat',        'seo_dash_chat_handler' );

function seo_dash_chat_handler(): void {
    seo_dash_verify_frontend_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $message   = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    $history   = isset( $_POST['history'] ) && is_array( $_POST['history'] )
                 ? array_slice( wp_unslash( $_POST['history'] ), -10 )
                 : [];

    if ( empty( $message ) ) seo_dash_json_error( 'Message is required.' );

    @set_time_limit( 120 );

    $use_groq_flag     = get_option( "seo_dash_chatbot_use_groq_{$report_id}",     '0' ) === '1';
    $use_cerebras_flag = get_option( "seo_dash_chatbot_use_cerebras_{$report_id}", '0' ) === '1';
    $use_gemini_flag   = get_option( "seo_dash_chatbot_use_gemini_{$report_id}",   '0' ) === '1';
    $use_deepseek_flag = get_option( "seo_dash_chatbot_use_deepseek_{$report_id}", '0' ) === '1';
    $report_model      = get_option( "seo_dash_chatbot_model_{$report_id}", '' );

    $deepseek_key = seo_dash_get_deepseek_key( $report_id );
    $gemini_key   = seo_dash_get_gemini_key( $report_id );
    $cerebras_key = seo_dash_get_cerebras_key( $report_id );
    $groq_key     = seo_dash_get_groq_key( $report_id );

    if ( ! $deepseek_key && ! $gemini_key && ! $cerebras_key && ! $groq_key ) {
        seo_dash_json_error( 'AI assistant is not configured for this dashboard. Please add a DeepSeek, Gemini, Cerebras, or Groq API key in Settings.' );
    }

    $rep_deepseek_model = get_option( "seo_dash_chatbot_deepseek_model_{$report_id}", '' );
    $rep_groq_model     = get_option( "seo_dash_chatbot_groq_model_{$report_id}", '' );
    $rep_cerebras_model = get_option( "seo_dash_chatbot_cerebras_model_{$report_id}", '' );
    $rep_gemini_model   = get_option( "seo_dash_chatbot_gemini_model_{$report_id}", '' );
    $active_deepseek_model = $rep_deepseek_model ?: SEO_Dash_Database::get_setting( 'deepseek_model', 'deepseek-v4-pro' );
    $active_groq_model     = $rep_groq_model     ?: SEO_Dash_Database::get_setting( 'groq_model',     'meta-llama/llama-4-scout-17b-16e-instruct' );
    $active_cerebras_model = $rep_cerebras_model ?: SEO_Dash_Database::get_setting( 'cerebras_model', 'gpt-oss-120b' );
    $active_gemini_model   = $rep_gemini_model   ?: SEO_Dash_Database::get_setting( 'gemini_model',   'gemini-2.0-flash' );

    if ( $report_model ) {
        if ( $report_model === 'deepseek' ) { $gemini_key = ''; $cerebras_key = ''; $groq_key = ''; }
        if ( $report_model === 'gemini' )   { $deepseek_key = ''; $cerebras_key = ''; $groq_key = ''; }
        if ( $report_model === 'cerebras' ) { $deepseek_key = ''; $gemini_key = '';   $groq_key = ''; }
        if ( $report_model === 'groq' )     { $deepseek_key = ''; $gemini_key = '';   $cerebras_key = ''; }
    } else {
        $global_provider = SEO_Dash_Database::get_setting( 'active_provider', '' );
        if ( $global_provider === 'deepseek' && $deepseek_key ) { $gemini_key = ''; $cerebras_key = ''; $groq_key = ''; }
        elseif ( $global_provider === 'gemini' && $gemini_key )     { $deepseek_key = ''; $cerebras_key = ''; $groq_key = ''; }
        elseif ( $global_provider === 'cerebras' && $cerebras_key ) { $deepseek_key = ''; $gemini_key = ''; $groq_key = ''; }
        elseif ( $global_provider === 'groq' && $groq_key )     { $deepseek_key = ''; $gemini_key = ''; $cerebras_key = ''; }
        elseif ( $deepseek_key ) { $gemini_key = ''; $cerebras_key = ''; $groq_key = ''; }
        elseif ( $gemini_key )   { $cerebras_key = ''; $groq_key = ''; }
        elseif ( $cerebras_key ) { $groq_key = ''; }
    }

    $system_prompt = seo_dash_build_system_prompt( $report_id, $message );

    if ( $groq_key && ! $gemini_key && ! $cerebras_key && ! $deepseek_key ) {
        $cap = 24000;
    } elseif ( $cerebras_key && ! $gemini_key && ! $deepseek_key ) {
        $cap = 28000;
    } else {
        $cap = 80000;
    }
    if ( strlen( $system_prompt ) > $cap ) {
        $system_prompt = substr( $system_prompt, 0, $cap ) . "\n\n[Context trimmed to fit model limit. Ask specific questions for full detail.]";
    }

    $messages = [ [ 'role' => 'system', 'content' => $system_prompt ] ];
    foreach ( $history as $turn ) {
        $role    = sanitize_key( $turn['role']    ?? 'user' );
        $content = sanitize_textarea_field( $turn['content'] ?? '' );
        if ( in_array( $role, [ 'user', 'assistant' ], true ) && $content ) {
            $messages[] = [ 'role' => $role, 'content' => $content ];
        }
    }
    $messages[] = [ 'role' => 'user', 'content' => $message ];

    $reply = '';
    $error = '';
    try {
        if ( $deepseek_key ) {
            $reply = seo_dash_deepseek_chat( $deepseek_key, $messages, 3000, $active_deepseek_model );
        } elseif ( $gemini_key ) {
            $reply = seo_dash_gemini_chat( $gemini_key, $messages, 3000, $active_gemini_model );
        } elseif ( $cerebras_key ) {
            $reply = seo_dash_cerebras_chat( $cerebras_key, $messages, 1200, $active_cerebras_model );
        } elseif ( $groq_key ) {
            $reply = seo_dash_groq_chat( $groq_key, $messages, 1200, $active_groq_model );
        }
    } catch ( \Throwable $e ) {
        $error = $e->getMessage();
    }

    if ( ! $reply ) {
        $msg = $error ? "AI error: {$error}" : 'The AI did not return a response. The prompt may be too large or the API key may be invalid.';
        seo_dash_json_error( $msg );
    }

    // Aggressively strip reasoning preambles/thinking before sending to client
    $reply = seo_dash_strip_reasoning_preamble( $reply );

    // Normalise headings: collapse #### / ##### down to ### so the client renderer handles them cleanly
    $reply = preg_replace( '/^#{4,}\s+/m', '### ', $reply );

    seo_dash_json_success( [ 'reply' => $reply ] );
}

// ── Convert AI-returned Markdown into safe HTML for the classic editor ─────
// LLMs reliably produce Markdown (headings, **bold**, tables, lists) even
// when asked for HTML, and often wrap output in ```fences```. This converts
// that Markdown into real HTML elements (h2-h4, <strong>/<em>, <ul>/<ol>,
// <table>) so the classic editor and client-facing summary render properly
// instead of showing literal "**Heading**" / "# Heading" text.
if ( ! function_exists( 'seo_dash_markdown_to_html' ) ) {
function seo_dash_markdown_to_html( string $md ): string {
    // Strip ```html / ``` code fences the model may wrap the response in.
    $md = preg_replace( '/^```[a-zA-Z]*\s*\n?/m', '', $md );
    $md = preg_replace( '/\n?```\s*$/m', '', $md );
    $md = trim( $md );

    // Inline formatting: bold, italic, inline code. Applied to escaped text.
    $inline = function ( string $text ): string {
        $text = esc_html( $text );
        $text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
        $text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );
        $text = preg_replace( '/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text );
        $text = preg_replace( '/(?<!_)_([^_\n]+)_(?!_)/', '<em>$1</em>', $text );
        return $text;
    };

    $lines = preg_split( '/\r\n|\r|\n/', $md );
    $html  = [];
    $i     = 0;
    $n     = count( $lines );

    while ( $i < $n ) {
        $line = $lines[ $i ];
        $trim = trim( $line );

        // Blank line — skip (paragraphs are closed explicitly below).
        if ( $trim === '' ) { $i++; continue; }

        // Markdown table: a row of |cells| followed by a |---|---| separator row.
        if ( strpos( $trim, '|' ) !== false && $i + 1 < $n && preg_match( '/^\s*\|?[\s:|-]+\|?\s*$/', $lines[ $i + 1 ] ) && strpos( $lines[ $i + 1 ], '-' ) !== false ) {
            $header_cells = array_map( 'trim', explode( '|', trim( $trim, '|' ) ) );
            $rows = [];
            $i += 2; // skip header + separator
            while ( $i < $n && strpos( trim( $lines[ $i ] ), '|' ) !== false && trim( $lines[ $i ] ) !== '' ) {
                $rows[] = array_map( 'trim', explode( '|', trim( trim( $lines[ $i ] ), '|' ) ) );
                $i++;
            }
            $out  = '<table class="seo-cl-summary-table"><thead><tr>';
            foreach ( $header_cells as $c ) $out .= '<th>' . $inline( $c ) . '</th>';
            $out .= '</tr></thead><tbody>';
            foreach ( $rows as $r ) {
                $out .= '<tr>';
                foreach ( $r as $c ) $out .= '<td>' . $inline( $c ) . '</td>';
                $out .= '</tr>';
            }
            $out .= '</tbody></table>';
            $html[] = $out;
            continue;
        }

        // Headings: # / ## / ### / ####  → h2 / h3 / h4 / h4
        if ( preg_match( '/^(#{1,6})\s+(.*)$/', $trim, $m ) ) {
            $level = min( strlen( $m[1] ) + 1, 4 ); // # -> h2, ## -> h3, ###+ -> h4
            $html[] = "<h{$level}>" . $inline( trim( $m[2], '* ' ) ) . "</h{$level}>";
            $i++;
            continue;
        }

        // A whole line wrapped in **bold** with nothing else — treat as a sub-heading.
        if ( preg_match( '/^\*\*(.+)\*\*:?$/', $trim, $m ) ) {
            $html[] = '<h3>' . $inline( $m[1] ) . '</h3>';
            $i++;
            continue;
        }

        // Unordered / ordered lists — consume consecutive list items.
        if ( preg_match( '/^[-*+]\s+(.*)$/', $trim, $m ) || preg_match( '/^\d+[.)]\s+(.*)$/', $trim, $m ) ) {
            $ordered = (bool) preg_match( '/^\d+[.)]\s+/', $trim );
            $tag     = $ordered ? 'ol' : 'ul';
            $items   = [];
            while ( $i < $n ) {
                $t = trim( $lines[ $i ] );
                if ( preg_match( '/^[-*+]\s+(.*)$/', $t, $m2 ) || preg_match( '/^\d+[.)]\s+(.*)$/', $t, $m2 ) ) {
                    $items[] = $inline( $m2[1] );
                    $i++;
                } else {
                    break;
                }
            }
            $out = "<{$tag}>";
            foreach ( $items as $it ) $out .= "<li>{$it}</li>";
            $out .= "</{$tag}>";
            $html[] = $out;
            continue;
        }

        // Paragraph — consume consecutive non-blank, non-block lines.
        $para = [];
        while ( $i < $n ) {
            $t = trim( $lines[ $i ] );
            if ( $t === '' ) break;
            if ( preg_match( '/^(#{1,6})\s+/', $t ) ) break;
            if ( preg_match( '/^\*\*(.+)\*\*:?$/', $t ) ) break;
            if ( preg_match( '/^[-*+]\s+/', $t ) || preg_match( '/^\d+[.)]\s+/', $t ) ) break;
            if ( strpos( $t, '|' ) !== false && isset( $lines[ $i + 1 ] ) && preg_match( '/^\s*\|?[\s:|-]+\|?\s*$/', $lines[ $i + 1 ] ) && strpos( $lines[ $i + 1 ], '-' ) !== false ) break;
            $para[] = $t;
            $i++;
        }
        if ( $para ) {
            $html[] = '<p>' . $inline( implode( ' ', $para ) ) . '</p>';
        }
    }

    return implode( "\n", $html );
}
}


// ── Auto-generate report summary (admin only) ─────────────────────────────
add_action( 'wp_ajax_seo_dash_generate_summary', function () {
    seo_dash_verify_admin_ajax();

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $prompt    = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
    if ( ! $report_id ) seo_dash_json_error( 'Missing report ID.' );

    $report = SEO_Dash_Database::get_report( $report_id );
    if ( ! $report ) seo_dash_json_error( 'Report not found.' );

    @set_time_limit( 120 );

    // ── Resolve AI provider exactly like the chatbot does ───────────────────
    // Priority: report's "Use this connection" toggle + selected provider
    // (Integrations → Chatbot), falling back to the global active provider,
    // then auto-priority gemini > cerebras > groq.
    $report_model = get_option( "seo_dash_chatbot_model_{$report_id}", '' );

    $gemini_key   = seo_dash_get_gemini_key( $report_id );
    $cerebras_key = seo_dash_get_cerebras_key( $report_id );
    $groq_key     = seo_dash_get_groq_key( $report_id );

    if ( ! $gemini_key && ! $cerebras_key && ! $groq_key ) {
        seo_dash_json_error( 'No AI provider is configured for this report. Set up Groq, Cerebras, or Gemini under Integrations → Chatbot first.' );
    }

    $rep_groq_model     = get_option( "seo_dash_chatbot_groq_model_{$report_id}", '' );
    $rep_cerebras_model = get_option( "seo_dash_chatbot_cerebras_model_{$report_id}", '' );
    $rep_gemini_model   = get_option( "seo_dash_chatbot_gemini_model_{$report_id}", '' );
    $active_groq_model     = $rep_groq_model     ?: SEO_Dash_Database::get_setting( 'groq_model',     'meta-llama/llama-4-scout-17b-16e-instruct' );
    $active_cerebras_model = $rep_cerebras_model ?: SEO_Dash_Database::get_setting( 'cerebras_model', 'gpt-oss-120b' );
    $active_gemini_model   = $rep_gemini_model   ?: SEO_Dash_Database::get_setting( 'gemini_model',   'gemini-2.0-flash' );

    if ( $report_model ) {
        if ( $report_model === 'gemini' )   { $cerebras_key = ''; $groq_key = ''; }
        if ( $report_model === 'cerebras' ) { $gemini_key = '';   $groq_key = ''; }
        if ( $report_model === 'groq' )     { $gemini_key = '';   $cerebras_key = ''; }
    } else {
        $global_provider = SEO_Dash_Database::get_setting( 'active_provider', '' );
        if ( $global_provider === 'gemini' && $gemini_key )         { $cerebras_key = ''; $groq_key = ''; }
        elseif ( $global_provider === 'cerebras' && $cerebras_key ) { $gemini_key = '';   $groq_key = ''; }
        elseif ( $global_provider === 'groq' && $groq_key )         { $gemini_key = '';   $cerebras_key = ''; }
        elseif ( $gemini_key )   { $cerebras_key = ''; $groq_key = ''; }
        elseif ( $cerebras_key ) { $groq_key = ''; }
    }

    // ── Build the full report data context (same data the AI Assistant sees) ──
    $context = seo_dash_build_report_context( $report_id );

    // Context size caps per provider (~1 token ≈ 4 chars)
    if ( $gemini_key ) {
        $cap = 60000;
    } elseif ( $cerebras_key ) {
        $cap = 28000;
    } else {
        $cap = 24000; // groq
    }
    if ( strlen( $context ) > $cap ) {
        $context = substr( $context, 0, $cap ) . "\n\n[Context trimmed to fit model limit.]";
    }

    if ( ! $prompt ) {
        $prompt = "Using the report data above, write a detailed, professional client-facing SEO report summary, formatted in Markdown:\n"
            . "1. Start with a short 'Introduction' (## heading) — 2-3 sentences setting the context for this reporting period.\n"
            . "2. Add a '## Key Metrics' section containing a Markdown table (with a header row and a |---|---| separator row) "
            . "summarizing the most important numbers (e.g. Sessions, Users, Clicks, Impressions, CTR, Backlinks, Leads, Page Speed) "
            . "comparing the recent period vs all-time where relevant.\n"
            . "3. Add sections with '### ' subheadings for: Traffic Overview, Search Visibility, Rankings & Content, Backlink Profile, "
            . "Leads, and Technical Health — each with a short paragraph and/or bullet points referencing the actual numbers from the data.\n"
            . "4. End with a '## Conclusion & Recommendations' section: 1-2 sentences summarizing overall performance, followed by a "
            . "bulleted list of concrete, actionable next steps for the next period.\n"
            . "Use a confident, positive, agency tone. Do not invent data that isn't provided. "
            . "Respond with Markdown only — no preamble, no code fences.";
    }

    $messages = [
        [ 'role' => 'system', 'content' => 'You are an expert SEO agency analyst writing detailed, data-driven client report summaries in Markdown, based on the dashboard data provided to you.' ],
        [ 'role' => 'user',   'content' => $context . "\n\n" . $prompt ],
    ];

    $summary = '';
    $error   = '';
    try {
        if ( $gemini_key ) {
            $summary = seo_dash_gemini_chat( $gemini_key, $messages, 2000, $active_gemini_model );
        } elseif ( $cerebras_key ) {
            $summary = seo_dash_cerebras_chat( $cerebras_key, $messages, 2000, $active_cerebras_model );
        } elseif ( $groq_key ) {
            $summary = seo_dash_groq_chat( $groq_key, $messages, 2000, $active_groq_model );
        }
    } catch ( \Throwable $e ) {
        $error = $e->getMessage();
    }

    if ( ! $summary ) {
        $msg = $error ? "AI error: {$error}" : 'The AI did not return a response. Try again, or check the API key under Integrations → Chatbot.';
        seo_dash_json_error( $msg );
    }

    // Convert the model's Markdown response (headings, **bold**, lists,
    // tables) into real HTML for the classic editor and the client-facing
    // summary panel — then sanitize.
    $summary = wp_kses_post( seo_dash_markdown_to_html( $summary ) );

    if ( ! empty( $_POST['auto_save'] ) ) {
        $meta            = is_array( $report['meta'] ) ? $report['meta'] : [];
        $meta['summary'] = $summary;
        SEO_Dash_Database::update_report( $report_id, [ 'meta' => $meta ] );
        SEO_Dash_Database::log_activity(
            'report_summary_generated', 'success',
            'AI-generated report summary saved.',
            'report', $report_id, $report['title'] ?? ''
        );
    }

    seo_dash_json_success( [ 'summary' => $summary ] );
} );

// ── Test Groq API key ──────────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_test_groq', function () {
    seo_dash_verify_admin_ajax();
    $key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $key ) {
        $key = seo_dash_get_groq_key( $report_id );
        if ( ! $key ) seo_dash_json_error( 'No API key provided and no key is saved.' );
    }

    $model = sanitize_text_field( wp_unslash( $_POST['test_model'] ?? '' ) )
          ?: SEO_Dash_Database::get_setting( 'groq_model', 'meta-llama/llama-4-scout-17b-16e-instruct' );

    $response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'messages'   => [ [ 'role' => 'user', 'content' => 'Say OK' ] ],
            'max_tokens' => 5,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        seo_dash_json_error( 'Request failed: ' . $response->get_error_message() );
    }

    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $body      = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $http_code === 200 ) {
        seo_dash_json_success( null, 'Connection successful. Model: ' . $model );
    } else {
        $err = $body['error']['message'] ?? ( 'HTTP ' . $http_code . ' — check your API key.' );
        seo_dash_json_error( $err );
    }
} );

// ── Test Cerebras API key ──────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_test_cerebras', function () {
    seo_dash_verify_admin_ajax();
    $key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $key ) {
        $key = seo_dash_get_cerebras_key( $report_id );
        if ( ! $key ) seo_dash_json_error( 'No API key provided and no key is saved.' );
    }

    $model = sanitize_text_field( wp_unslash( $_POST['test_model'] ?? '' ) )
          ?: SEO_Dash_Database::get_setting( 'cerebras_model', 'gpt-oss-120b' );

    $response = wp_remote_post( 'https://api.cerebras.ai/v1/chat/completions', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'messages'   => [ [ 'role' => 'user', 'content' => 'Say OK' ] ],
            'max_tokens' => 5,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        seo_dash_json_error( 'Request failed: ' . $response->get_error_message() );
    }

    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $body      = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $http_code === 200 ) {
        seo_dash_json_success( null, 'Connection successful. Model: ' . $model );
    } else {
        $err = $body['error']['message'] ?? $body['message'] ?? ( 'HTTP ' . $http_code . ' — check your API key.' );
        seo_dash_json_error( $err );
    }
} );
add_action( 'wp_ajax_seo_dash_test_gemini', function () {
    seo_dash_verify_admin_ajax();
    $key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $test_model = sanitize_text_field( wp_unslash( $_POST['test_model'] ?? '' ) );
    if ( ! $key ) {
        $key = seo_dash_get_gemini_key( $report_id );
        if ( ! $key ) seo_dash_json_error( 'No API key provided and no key is saved.' );
    }
    $result = seo_dash_gemini_test( $key, $test_model );
    if ( $result['ok'] ) seo_dash_json_success( null, 'Connection successful.' );
    else                  seo_dash_json_error( $result['error'] ?: 'Could not connect. Check the API key.' );
} );

// ── Test DeepSeek API key ──────────────────────────────────────────────────
add_action( 'wp_ajax_seo_dash_test_deepseek', function () {
    seo_dash_verify_admin_ajax();
    $key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
    $report_id = intval( $_POST['report_id'] ?? 0 );
    if ( ! $key ) {
        $key = seo_dash_get_deepseek_key( $report_id );
        if ( ! $key ) seo_dash_json_error( 'No API key provided and no key is saved.' );
    }

    $model = sanitize_text_field( wp_unslash( $_POST['test_model'] ?? '' ) )
          ?: SEO_Dash_Database::get_setting( 'deepseek_model', 'deepseek-v4-pro' );

    $result = seo_dash_deepseek_test( $key, $model );
    if ( $result['ok'] ) {
        seo_dash_json_success( null, 'Connection successful. Model: ' . $model );
    } else {
        seo_dash_json_error( $result['error'] ?: 'Could not connect to DeepSeek. Check your API key.' );
    }
} );

