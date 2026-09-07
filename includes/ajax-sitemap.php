<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: Import Sitemap
 * Ultra-robust parser — works with Yoast, RankMath, Google, etc.
 * Uses regex on raw body so XML namespaces never cause 0 results.
 */
add_action( 'wp_ajax_seo_dash_import_sitemap', function () {
    seo_dash_verify_admin_ajax();

    $sitemap_url = esc_url_raw( wp_unslash( $_POST['sitemap_url'] ?? '' ) );
    if ( ! $sitemap_url ) {
        wp_send_json_error( [ 'message' => 'Please provide a sitemap URL.' ] );
    }

    $result = seo_dash_do_sitemap_import( $sitemap_url );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    $count = count( $result['urls'] );

    if ( $count === 0 ) {
        wp_send_json_error( [
            'message' => 'No URLs found in this sitemap. Make sure it is a valid XML sitemap and is publicly accessible.',
        ] );
    }

    SEO_Dash_Database::log_activity(
        'sitemap_import', 'success',
        "{$count} URLs imported from: {$sitemap_url}",
        'integration'
    );

    wp_send_json_success( [
        'urls'  => array_values( $result['urls'] ),
        'types' => $result['types'],
        'count' => $count,
    ] );
} );

/* =========================================================================
   AJAX: Push sitemap URLs → Analytics (GA) or Search Console (SC)
   — deduplicates: only inserts URLs not already present
   — persists a url→type map to wp_options so the view can group reliably
     without any URL-path heuristics
   ========================================================================= */
add_action( 'wp_ajax_seo_dash_sitemap_push', function () {
    seo_dash_verify_admin_ajax();

    global $wpdb;

    $report_id = intval( $_POST['report_id'] ?? 0 );
    $scope     = sanitize_key( $_POST['scope'] ?? '' ); // 'ga' or 'sc'
    
    // Support both JSON-encoded string (for large payloads bypassing max_input_vars) and standard array
    $raw_urls = $_POST['urls'] ?? [];
    if ( is_string( $raw_urls ) ) {
        $decoded = json_decode( wp_unslash( $raw_urls ), true );
        if ( is_array( $decoded ) ) {
            $raw_urls = $decoded;
        }
    }
    if ( ! is_array( $raw_urls ) ) {
        $raw_urls = [];
    }

    if ( ! $report_id || ! in_array( $scope, [ 'ga', 'sc' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Missing report_id or invalid scope.' ] );
    }
    if ( empty( $raw_urls ) ) {
        wp_send_json_error( [ 'message' => 'No URLs provided.' ] );
    }

    /* --- Sanitise & build incoming map: url → type -------------------- */
    $incoming = [];   // url => type
    foreach ( $raw_urls as $entry ) {
        if ( is_string( $entry ) ) {
            $u = sanitize_text_field( wp_unslash( $entry ) );
            $t = 'page';
        } elseif ( is_array( $entry ) ) {
            $u = sanitize_text_field( wp_unslash( $entry['url'] ?? '' ) );
            $t = sanitize_key( wp_unslash( $entry['type'] ?? 'page' ) );
        } else {
            continue;
        }
        if ( $t === '' ) $t = 'page';
        if ( $u && filter_var( $u, FILTER_VALIDATE_URL ) ) {
            $incoming[ $u ] = $t;
        }
    }
    if ( empty( $incoming ) ) {
        wp_send_json_error( [ 'message' => 'No valid URLs provided.' ] );
    }

    $month_key = date( 'Y-m' );
    $date_val  = date( 'Y-m-01' );

    /* --- Option key that stores the persistent url→type map ----------- */
    $opt_key = "seo_dash_sitemap_types_{$report_id}_{$scope}";

    /* Clean existing map so relative paths/duplicates never inflate counts */
    $clean_existing_map = [];
    foreach ( (array) $existing_map as $k => $v ) {
        $k_trim = trim( (string) $k );
        if ( filter_var( $k_trim, FILTER_VALIDATE_URL ) ) {
            $clean_existing_map[ $k_trim ] = sanitize_key( $v );
        }
    }
    $existing_map = $clean_existing_map;

    /* --- Determine which table to use --------------------------------- */
    $table = ( $scope === 'ga' ) ? SEO_Dash_Database::$data_ga : SEO_Dash_Database::$data_sc;

    /* Fetch existing page_urls for this report (all months, not trashed) */
    $existing_urls = $wpdb->get_col( $wpdb->prepare(
        "SELECT page_url FROM {$table} WHERE report_id = %d AND trashed = 0",
        $report_id
    ) );
    
    // Build comprehensive deduplication set covering full URLs, relative paths, trailing/untrailing slashes, and lowercase
    $existing_set = [];
    foreach ( (array) $existing_urls as $eu ) {
        $eu = trim( (string) $eu );
        if ( ! $eu ) continue;
        $existing_set[ $eu ] = true;
        $existing_set[ trailingslashit( $eu ) ] = true;
        $existing_set[ untrailingslashit( $eu ) ] = true;
        $existing_set[ strtolower( $eu ) ] = true;
        $existing_set[ strtolower( trailingslashit( $eu ) ) ] = true;
        $existing_set[ strtolower( untrailingslashit( $eu ) ) ] = true;

        $p = parse_url( $eu, PHP_URL_PATH );
        if ( $p && $p !== '/' ) {
            $existing_set[ $p ] = true;
            $existing_set[ trailingslashit( $p ) ] = true;
            $existing_set[ untrailingslashit( $p ) ] = true;
            $existing_set[ strtolower( $p ) ] = true;
            $existing_set[ strtolower( trailingslashit( $p ) ) ] = true;
            $existing_set[ strtolower( untrailingslashit( $p ) ) ] = true;
        }
    }

    /* --- Build insert rows (new only) --------------------------------- */
    $rows        = [];
    $skipped     = 0;
    $type_counts = [];

    foreach ( $incoming as $url => $type ) {
        $u_trim = trim( $url );
        if ( ! filter_var( $u_trim, FILTER_VALIDATE_URL ) ) continue;

        /* Track canonical URL in persistent map */
        $existing_map[ $u_trim ] = $type;
        $url_path = parse_url( $u_trim, PHP_URL_PATH );

        $type_counts[ $type ] = ( $type_counts[ $type ] ?? 0 ) + 1;

        // Check if URL or any of its path/slash variants already exists in DB
        $is_dup = false;
        $u_trim = trim( $url );
        $u_low  = strtolower( $u_trim );
        
        if (
            isset( $existing_set[ $u_trim ] ) ||
            isset( $existing_set[ trailingslashit( $u_trim ) ] ) ||
            isset( $existing_set[ untrailingslashit( $u_trim ) ] ) ||
            isset( $existing_set[ $u_low ] ) ||
            isset( $existing_set[ strtolower( trailingslashit( $u_trim ) ) ] ) ||
            isset( $existing_set[ strtolower( untrailingslashit( $u_trim ) ) ] )
        ) {
            $is_dup = true;
        } elseif ( $url_path && $url_path !== '/' ) {
            $p_low = strtolower( $url_path );
            if (
                isset( $existing_set[ $url_path ] ) ||
                isset( $existing_set[ trailingslashit( $url_path ) ] ) ||
                isset( $existing_set[ untrailingslashit( $url_path ) ] ) ||
                isset( $existing_set[ $p_low ] ) ||
                isset( $existing_set[ strtolower( trailingslashit( $url_path ) ) ] ) ||
                isset( $existing_set[ strtolower( untrailingslashit( $url_path ) ) ] )
            ) {
                $is_dup = true;
            }
        }

        if ( $is_dup ) {
            $skipped++;
            continue;
        }

        // Mark as existing so intra-sitemap duplicates in the same payload are also skipped
        $existing_set[ $u_trim ] = true;
        $existing_set[ trailingslashit( $u_trim ) ] = true;
        $existing_set[ untrailingslashit( $u_trim ) ] = true;
        $existing_set[ $u_low ] = true;
        if ( $url_path && $url_path !== '/' ) {
            $existing_set[ $url_path ] = true;
            $existing_set[ trailingslashit( $url_path ) ] = true;
            $existing_set[ untrailingslashit( $url_path ) ] = true;
        }

        if ( $scope === 'ga' ) {
            $rows[] = [
                'report_id'   => $report_id,
                'period_type' => 'monthly',
                'month_key'   => $month_key,
                'date_from'   => $date_val,
                'date_to'     => $date_val,
                'page_url'    => $url,
                'page_title'  => '', // leave empty, will be populated by GA fetch
                'sessions'    => 0,
                'users'       => 0,
                'pageviews'   => 0,
                'bounces'     => 0,
            ];
        } else {
            $rows[] = [
                'report_id'   => $report_id,
                'period_type' => 'monthly',
                'month_key'   => $month_key,
                'date_from'   => $date_val,
                'date_to'     => $date_val,
                'query'       => '', // leave empty
                'page_url'    => $url,
                'clicks'      => 0,
                'impressions' => 0,
                'ctr'         => 0,
                'position'    => 0,
            ];
        }
    }

    /* --- Persist the updated url→type map ----------------------------- */
    update_option( $opt_key, $existing_map, false );

    /* --- Batch insert new placeholder rows into the database ---------- */
    $inserted_count = 0;
    if ( ! empty( $rows ) ) {
        $inserted_count = SEO_Dash_Database::insert_data_rows( $table, $rows, 500 );
    }

    SEO_Dash_Database::log_activity(
        "sitemap_push_{$scope}", 'success',
        strtoupper( $scope ) . ": " . count( $incoming ) . " URLs registered ({$inserted_count} new DB rows).",
        'report', $report_id
    );

    wp_send_json_success( [
        'inserted'    => count( $incoming ),
        'db_inserted' => $inserted_count,
        'skipped'     => $skipped,
        'scope'       => $scope,
        'type_counts' => $type_counts,
    ] );
} );

/* =========================================================================
   Core parser — recursive, namespace-safe, handles index + regular sitemaps
   ========================================================================= */

function seo_dash_do_sitemap_import( string $url, int $depth = 0 ) {
    if ( $depth > 4 ) {
        return [ 'urls' => [], 'types' => [] ];
    }

    /* -- Fetch ------------------------------------------------------------ */
    $response = seo_dash_safe_remote_get( $url, [
        'timeout'    => 30,
        'user-agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'headers'    => [
            'Accept'          => 'text/xml,application/xml,application/xhtml+xml,*/*;q=0.8',
            'Accept-Encoding' => 'identity',   // ask for plain text — avoids gzip decode issues
            'Accept-Language' => 'en-US,en;q=0.9',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_Error(
            'fetch_failed',
            'Could not reach the sitemap URL: ' . $response->get_error_message()
        );
    }

    $http_code = (int) wp_remote_retrieve_response_code( $response );

    if ( $http_code !== 200 ) {
        return new WP_Error( 'http_error', "The sitemap URL returned HTTP {$http_code}. Please verify the URL is correct and publicly accessible." );
    }

    $body = wp_remote_retrieve_body( $response );

    /* Handle gzip-encoded body (just in case) */
    if ( substr( $body, 0, 2 ) === "\x1f\x8b" && function_exists( 'gzdecode' ) ) {
        $body = gzdecode( $body );
    }

    if ( empty( trim( $body ) ) ) {
        return new WP_Error( 'empty_body', 'The sitemap URL returned an empty response.' );
    }

    /* Strip BOM if present */
    $body = ltrim( $body, "\xEF\xBB\xBF" );

    // 8. Validate the response content to ensure it contains legitimate sitemap XML tags.
    if ( stripos( $body, '<urlset' ) === false && 
         stripos( $body, '<sitemapindex' ) === false && 
         stripos( $body, '<sitemap' ) === false && 
         stripos( $body, '<url' ) === false && 
         stripos( $body, '<loc' ) === false ) {
        return new WP_Error( 'invalid_content', 'The sitemap URL returned invalid content (not a valid sitemap).' );
    }

    /* -- Parse ------------------------------------------------------------ */
    return seo_dash_parse_sitemap_body( $body, $url, $depth );
}

/**
 * Parse raw sitemap XML body using regex (namespace-safe).
 */
function seo_dash_parse_sitemap_body( string $body, string $source_url, int $depth ): array {
    $urls  = [];
    $types = [];

    /* Detect whether this is a sitemap index */
    $is_index = (
        stripos( $body, '<sitemapindex' ) !== false ||
        stripos( $body, ':sitemapindex' ) !== false
    );

    if ( $is_index ) {
        /*
         * Sitemap index: contains <sitemap><loc>…</loc></sitemap> blocks.
         * Extract the child URLs and recurse.
         */
        preg_match_all(
            '/<sitemap[\s>][\s\S]*?<loc[\s>]*>([\s\S]*?)<\/loc>/i',
            $body,
            $matches
        );
        $child_locs = $matches[1] ?? [];

        /* Fallback: just grab every <loc> if the above found nothing */
        if ( empty( $child_locs ) ) {
            preg_match_all( '/<loc[\s>]*>([\s\S]*?)<\/loc>/i', $body, $m2 );
            $child_locs = $m2[1] ?? [];
        }

        $seen_child = [];
        foreach ( $child_locs as $raw_loc ) {
            $child_url = trim( seo_dash_decode_loc( $raw_loc ) );
            if ( ! $child_url || ! filter_var( $child_url, FILTER_VALIDATE_URL ) ) continue;

            $child = seo_dash_do_sitemap_import( $child_url, $depth + 1 );
            if ( is_array( $child ) ) {
                foreach ( $child['urls'] as $child_entry ) {
                    $u_val = is_array( $child_entry ) ? ( $child_entry['url'] ?? '' ) : (string) $child_entry;
                    $t_val = is_array( $child_entry ) ? ( $child_entry['type'] ?? 'page' ) : 'page';
                    $u_key = strtolower( rtrim( $u_val, '/' ) );
                    if ( $u_val && ! isset( $seen_child[ $u_key ] ) ) {
                        $seen_child[ $u_key ] = true;
                        $urls[] = [ 'url' => $u_val, 'type' => $t_val ];
                        $types[ $t_val ] = ( $types[ $t_val ] ?? 0 ) + 1;
                    }
                }
            }
        }

    } else {
        /*
         * Regular urlset: <url><loc>…</loc></url>
         * Extract every <loc> that is NOT inside a <sitemap> wrapper.
         */

        /* First try to get <loc> only inside <url> blocks */
        preg_match_all(
            '/<url[\s>][\s\S]*?<loc[\s>]*>([\s\S]*?)<\/loc>[\s\S]*?<\/url>/i',
            $body,
            $matches
        );
        $locs = $matches[1] ?? [];

        /* Fallback: any <loc> in the doc */
        if ( empty( $locs ) ) {
            preg_match_all( '/<loc[\s>]*>([\s\S]*?)<\/loc>/i', $body, $m2 );
            $locs = $m2[1] ?? [];
        }

        $type = seo_dash_detect_type_from_sitemap_url( $source_url );

        $seen = [];
        foreach ( $locs as $raw_loc ) {
            $loc = trim( seo_dash_decode_loc( $raw_loc ) );
            if ( ! $loc ) continue;

            /* Validate it looks like a URL */
            if ( ! filter_var( $loc, FILTER_VALIDATE_URL ) ) continue;

            /* Skip duplicate (case and slash-insensitive) */
            $loc_key = strtolower( rtrim( $loc, '/' ) );
            if ( isset( $seen[ $loc_key ] ) ) continue;
            $seen[ $loc_key ] = true;

            $t = $type ?: seo_dash_guess_type_from_page_url( $loc );
            $urls[] = [ 'url' => $loc, 'type' => $t ];
            $types[ $t ] = ( $types[ $t ] ?? 0 ) + 1;
        }
    }

    return [ 'urls' => $urls, 'types' => $types ];
}

/**
 * Decode a <loc> value: handle CDATA, HTML entities, whitespace.
 */
function seo_dash_decode_loc( string $raw ): string {
    /* Strip CDATA wrapper */
    $raw = preg_replace( '/^\s*<!\[CDATA\[(.*)\]\]>\s*$/s', '$1', trim( $raw ) );
    /* Decode HTML entities (&amp; → &, etc.) */
    return html_entity_decode( $raw, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

/**
 * Detect content type from the sitemap filename.
 * Examples:  post-sitemap.xml → "post"
 *            page-sitemap.xml → "page"
 *            city-sitemap2.xml → "city"
 *            sitemap_product.xml → "product"
 */
function seo_dash_detect_type_from_sitemap_url( string $url ): string {
    $file = strtolower( basename( parse_url( $url, PHP_URL_PATH ) ?? '' ) );

    /* Yoast:    {type}-sitemap{N}.xml */
    if ( preg_match( '/^(.+?)-sitemap\d*\.xml$/', $file, $m ) ) {
        return seo_dash_normalise_type( $m[1] );
    }

    /* RankMath: sitemap_{type}.xml  or  sitemap-{type}.xml */
    if ( preg_match( '/^sitemap[_-](.+?)(?:\d*)\.xml$/', $file, $m ) ) {
        return seo_dash_normalise_type( $m[1] );
    }

    return '';
}

function seo_dash_normalise_type( string $type ): string {
    $type = strtolower( trim( $type ) );
    $map  = [
        'posts'      => 'post',
        'pages'      => 'page',
        'products'   => 'product',
        'categories' => 'category',
        'tags'       => 'tag',
        'authors'    => 'author',
    ];
    return $map[ $type ] ?? $type;
}

/**
 * Retrieve custom URL routing rules for a report (or fallback to defaults).
 */
function seo_dash_get_url_routing_rules( int $report_id = 0 ): array {
    $rules = [];
    if ( $report_id > 0 ) {
        $rules = get_option( "seo_dash_url_rules_{$report_id}", null );
    }
    if ( ! is_array( $rules ) ) {
        $rules = get_option( "seo_dash_global_url_rules", [
            [ 'pattern' => '*/locations/*', 'type' => 'city' ],
            [ 'pattern' => '*/location/*',  'type' => 'city' ],
            [ 'pattern' => '*/areas/*',     'type' => 'city' ],
            [ 'pattern' => '*/services/*',  'type' => 'service' ],
            [ 'pattern' => '*/service/*',   'type' => 'service' ],
            [ 'pattern' => '*/blog/*',      'type' => 'post' ],
            [ 'pattern' => '*/news/*',      'type' => 'post' ],
            [ 'pattern' => '*/category/*',  'type' => 'category' ],
            [ 'pattern' => '*/author/*',    'type' => 'author' ],
        ] );
    }
    return is_array( $rules ) ? $rules : [];
}

/**
 * Match a URL against custom pattern rules.
 */
function seo_dash_match_url_rule( string $url, array $rules ): ?array {
    $path = strtolower( parse_url( $url, PHP_URL_PATH ) ?? '/' );
    $url_lower = strtolower( $url );

    foreach ( $rules as $rule ) {
        $pattern = trim( (string) ( $rule['pattern'] ?? '' ) );
        $type    = seo_dash_normalise_type( (string) ( $rule['type'] ?? '' ) );
        if ( ! $pattern || ! $type ) continue;

        // 1. Regex mode (starts and ends with '/')
        if ( strlen( $pattern ) > 2 && $pattern[0] === '/' && substr( $pattern, -1 ) === '/' ) {
            if ( @preg_match( $pattern, $url_lower ) || @preg_match( $pattern, $path ) ) {
                return [ 'type' => $type, 'pattern' => $pattern ];
            }
        }

        // 2. Wildcard mode (contains '*')
        if ( strpos( $pattern, '*' ) !== false ) {
            $regex = '#^' . str_replace( '\*', '.*', preg_quote( strtolower( $pattern ), '#' ) ) . '$#i';
            if ( @preg_match( $regex, $url_lower ) || @preg_match( $regex, $path ) ) {
                return [ 'type' => $type, 'pattern' => $pattern ];
            }
        }

        // 3. Substring match
        $p_low = strtolower( $pattern );
        if ( strpos( $path, $p_low ) !== false || strpos( $url_lower, $p_low ) !== false ) {
            return [ 'type' => $type, 'pattern' => $pattern ];
        }
    }
    return null;
}

/**
 * Guess content type from the actual page URL path.
 */
if ( ! function_exists( 'seo_dash_guess_type_from_page_url' ) ) {
function seo_dash_guess_type_from_page_url( string $url, int $report_id = 0 ): string {
    $custom_rules = seo_dash_get_url_routing_rules( $report_id );
    $matched = seo_dash_match_url_rule( $url, $custom_rules );
    if ( $matched && ! empty( $matched['type'] ) ) {
        return $matched['type'];
    }

    $path = strtolower( parse_url( $url, PHP_URL_PATH ) ?? '/' );

    $hints = [
        '/product/'   => 'product',
        '/shop/'      => 'product',
        '/blog/'      => 'post',
        '/news/'      => 'post',
        '/article/'   => 'post',
        '/category/'  => 'category',
        '/tag/'       => 'tag',
        '/author/'    => 'author',
        '/city/'      => 'city',
        '/location/'  => 'city',
        '/area/'      => 'city',
        '/service/'   => 'service',
        '/services/'  => 'service',
        '/portfolio/' => 'portfolio',
    ];

    foreach ( $hints as $seg => $type ) {
        if ( strpos( $path, $seg ) !== false ) return $type;
    }

    /* Depth heuristic */
    $parts = array_filter( explode( '/', trim( $path, '/' ) ) );
    if ( count( $parts ) === 0 ) return 'home';
    if ( count( $parts ) === 1 ) return 'page';
    return 'post';
}
} // end function_exists seo_dash_guess_type_from_page_url

// ── AJAX: Get Custom URL Routing Rules ──────────────────────────────────────
add_action( 'wp_ajax_seo_dash_get_url_rules', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $rules = seo_dash_get_url_routing_rules( $report_id );
    seo_dash_json_success( [ 'rules' => $rules ] );
} );

// ── AJAX: Save Custom URL Routing Rules ─────────────────────────────────────
add_action( 'wp_ajax_seo_dash_save_url_rules', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $raw_rules = $_POST['rules'] ?? [];
    if ( ! is_array( $raw_rules ) ) $raw_rules = [];

    $clean = [];
    foreach ( $raw_rules as $r ) {
        $p = sanitize_text_field( wp_unslash( $r['pattern'] ?? '' ) );
        $t = seo_dash_normalise_type( sanitize_key( $r['type'] ?? '' ) );
        if ( $p && $t ) {
            $clean[] = [ 'pattern' => $p, 'type' => $t ];
        }
    }

    if ( $report_id > 0 ) {
        update_option( "seo_dash_url_rules_{$report_id}", $clean );
    } else {
        update_option( "seo_dash_global_url_rules", $clean );
    }

    seo_dash_json_success( [ 'rules' => $clean ], 'URL routing rules saved successfully.' );
} );

// ── AJAX: Test Custom URL Routing Rule ──────────────────────────────────────
add_action( 'wp_ajax_seo_dash_test_url_rule', function () {
    seo_dash_verify_admin_ajax();
    $report_id = intval( $_POST['report_id'] ?? 0 );
    $url       = esc_url_raw( wp_unslash( $_POST['test_url'] ?? '' ) );
    if ( ! $url ) seo_dash_json_error( 'Please enter a valid URL to test.' );

    $custom_rules = seo_dash_get_url_routing_rules( $report_id );
    $matched      = seo_dash_match_url_rule( $url, $custom_rules );
    $final_type   = seo_dash_guess_type_from_page_url( $url, $report_id );

    seo_dash_json_success( [
        'url'          => $url,
        'final_type'   => $final_type,
        'matched_rule' => $matched ? $matched['pattern'] : null,
    ], "URL resolved to: " . ucfirst( $final_type ) );
} );
