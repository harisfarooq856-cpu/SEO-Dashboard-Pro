<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Thin wrapper so class-database.php can call SEO_Dash_Crypto
 * instead of reaching directly into the security module.
 * Delegates to seo_dash_sec_encrypt / seo_dash_sec_decrypt from
 * seo-dash-security.php, which is always loaded first.
 */
class SEO_Dash_Crypto {
    public static function encrypt( string $plaintext ): string {
        return function_exists( 'seo_dash_sec_encrypt' ) ? seo_dash_sec_encrypt( $plaintext ) : $plaintext;
    }
    public static function decrypt( string $stored ): string {
        return function_exists( 'seo_dash_sec_decrypt' ) ? seo_dash_sec_decrypt( $stored ) : $stored;
    }
}
