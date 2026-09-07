<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO_Dash_Roles
 *
 * Manages the 'seo_client' custom role and the login redirect
 * that sends clients straight to their dashboard page.
 */
class SEO_Dash_Roles {

    /**
     * Register the 'seo_client' role.
     * Called during activation and on 'init' so the role exists even if
     * a site admin manually removes it via another plugin.
     */
    public static function register(): void {
        if ( !get_role( 'seo_client' ) ) {
            add_role(
                'seo_client',
                __( 'SEO Client', 'seo-dashboard' ),
                [
                    'read'         => true,   // required to access the WP front end
                    'upload_files' => false,
                ]
            );
        }
    }

    /**
     * Remove the role on plugin uninstall (call from uninstall.php only).
     */
    public static function remove(): void {
        remove_role( 'seo_client' );
    }

    /**
     * Boot hooks. Called from the main loader once per request.
     */
    public static function boot(): void {
        // Re-register the role on every init in case it was removed.
        add_action( 'init', [ __CLASS__, 'register' ] );

        // Redirect seo_client users to their dashboard after login.
        add_filter( 'login_redirect', [ __CLASS__, 'login_redirect' ], 10, 3 );

        // Block seo_client users from accessing wp-admin.
        add_action( 'admin_init', [ __CLASS__, 'block_client_admin_access' ] );
    }

    /**
     * After login, send seo_client users to the frontend dashboard page.
     * All other users get the default redirect.
     *
     * @param string           $redirect_to
     * @param string           $requested_redirect_to
     * @param WP_User|WP_Error $user
     * @return string
     */
    public static function login_redirect( string $redirect_to, string $requested_redirect_to, $user ): string {
        if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
            return $redirect_to;
        }
        if ( in_array( 'seo_client', (array) $user->roles, true ) ) {
            $pid = intval( get_option( 'seo_dash_client_page_id' ) );
            return $pid ? get_permalink( $pid ) : home_url();
        }
        return $redirect_to;
    }

    /**
     * Prevent seo_client users from loading any wp-admin page.
     */
    public static function block_client_admin_access(): void {
        if ( ! is_user_logged_in() ) return;
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;

        $user = wp_get_current_user();
        if ( in_array( 'seo_client', (array) $user->roles, true ) ) {
            $pid = intval( get_option( 'seo_dash_client_page_id' ) );
            $url = $pid ? get_permalink( $pid ) : home_url();
            wp_safe_redirect( $url );
            exit;
        }
    }
}

// Boot role hooks immediately.
SEO_Dash_Roles::boot();
