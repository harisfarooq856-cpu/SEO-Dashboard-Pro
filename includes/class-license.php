<?php
/**
 * SEO_Dash_License — License system removed.
 * All methods return "licensed/valid" so no functionality is gated.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SEO_Dash_License {

    const OPTION_KEY       = 'seo_dash_license_key_enc';
    const STATUS_CACHE_KEY = 'seo_dash_license_status';
    const UPDATE_CACHE_KEY = 'seo_dash_update_info';
    const CACHE_TTL        = HOUR_IN_SECONDS;
    const UPDATE_TTL       = 6 * HOUR_IN_SECONDS;
    const SLM_SERVER       = '';
    const CRON_HOOK        = 'seo_dash_license_cron_check';

    public static function init(): void {}
    public static function cron_check(): void {}
    public static function clear_cron(): void {}
    public static function clear_update_cache(): void {}

    /** Always licensed — no key required. */
    public static function is_licensed(): bool {
        return true;
    }

    public static function get_cached_status(): array {
        return [
            'valid'       => true,
            'message'     => 'License Active.',
            'expires_at'  => null,
            'key_display' => '',
        ];
    }

    public static function validate_key( string $key ): array {
        return self::get_cached_status();
    }

    public static function force_revalidate(): array {
        return self::get_cached_status();
    }

    public static function save_and_activate( string $key ): array {
        return self::get_cached_status();
    }

    public static function deactivate(): void {}

    public static function get_key(): string {
        return '';
    }

    public static function check_update(): ?array {
        return null;
    }

    public static function get_debug_info(): array {
        return [ 'checked' => false ];
    }

    public static function inject_update_info( $transient ) {
        return $transient;
    }

    public static function plugin_info( $result, $action, $args ) {
        return $result;
    }

    public static function maybe_revalidate(): void {}
}
