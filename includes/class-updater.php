<?php
/**
 * ChristWay GitHub Update Checker
 *
 * HOW TO USE:
 * 1. This file already points to: Damijoe/Christ-Way-Church-Report-System-Plugin
 * 2. When you have a new version:
 *    a) Bump CWR_VERSION in christway-reports.php (e.g. 1.0.1 → 1.0.2)
 *    b) Push all files to GitHub
 *    c) Create a GitHub Release tagged v1.0.2
 *    d) Attach the christway-reports.zip as a release asset
 *    e) In WP Admin → CW Reports → Update Settings → Force Check Now
 *    f) Go to Plugins → click Update
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CWR_Updater {

    const GITHUB_REPO   = 'Damijoe/Christ-Way-Church-Report-System-Plugin';
    const PLUGIN_SLUG   = 'christway-reports/christway-reports.php';
    const PLUGIN_FOLDER = 'christway-reports';
    const CACHE_KEY     = 'cwr_github_release_data';
    const CACHE_TTL     = 43200; // 12 hours

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
        add_filter( 'plugins_api',                           array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_post_install',                 array( __CLASS__, 'after_install' ), 10, 3 );
    }

    // ─── Fetch latest release from GitHub ────────────────────────────────────

    private static function get_github_release() {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) return $cached;

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'     => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) return false;
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) return false;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['tag_name'] ) ) return false;

        // Find attached zip asset first
        $zip_url = '';
        if ( ! empty( $body['assets'] ) ) {
            foreach ( $body['assets'] as $asset ) {
                if ( isset( $asset['name'] ) && substr( $asset['name'], -4 ) === '.zip' ) {
                    $zip_url = $asset['browser_download_url'];
                    break;
                }
            }
        }
        // Fallback: use GitHub auto-generated source zip
        // NOTE: This zip will have wrong folder name (repo-name-v1.0.1/)
        // Always attach christway-reports.zip as a release asset to avoid this
        if ( ! $zip_url ) {
            $zip_url = 'https://github.com/' . self::GITHUB_REPO . '/releases/download/' . $body['tag_name'] . '/christway-reports.zip';
        }

        $data = array(
            'version'      => ltrim( $body['tag_name'], 'v' ),
            'zip_url'      => $zip_url,
            'description'  => ! empty( $body['body'] ) ? $body['body'] : '',
            'release_date' => ! empty( $body['published_at'] ) ? date( 'Y-m-d', strtotime( $body['published_at'] ) ) : '',
        );

        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
        return $data;
    }

    // ─── Tell WordPress an update is available ────────────────────────────────

    public static function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = self::get_github_release();
        if ( ! $release ) return $transient;

        if ( version_compare( $release['version'], CWR_VERSION, '>' ) ) {
            $transient->response[ self::PLUGIN_SLUG ] = (object) array(
                'slug'         => self::PLUGIN_FOLDER,
                'plugin'       => self::PLUGIN_SLUG,
                'new_version'  => $release['version'],
                'url'          => 'https://github.com/' . self::GITHUB_REPO,
                'package'      => $release['zip_url'],
                'tested'       => get_bloginfo( 'version' ),
                'requires'     => '5.8',
                'requires_php' => '7.4',
            );
        }

        return $transient;
    }

    // ─── Plugin info modal ────────────────────────────────────────────────────

    public static function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( empty( $args->slug ) || $args->slug !== self::PLUGIN_FOLDER ) return $result;

        $release = self::get_github_release();
        if ( ! $release ) return $result;

        return (object) array(
            'name'          => 'ChristWay Church Reporting System',
            'slug'          => self::PLUGIN_FOLDER,
            'version'       => $release['version'],
            'author'        => 'Damijoe Digitals',
            'requires'      => '5.8',
            'tested'        => get_bloginfo( 'version' ),
            'requires_php'  => '7.4',
            'last_updated'  => $release['release_date'],
            'download_link' => $release['zip_url'],
            'sections'      => array(
                'description' => '<p>Weekly reporting system for Christ Way Church Treasure House.</p>',
                'changelog'   => nl2br( esc_html( $release['description'] ) ),
            ),
        );
    }

    // ─── After WordPress installs the update ─────────────────────────────────
    // This is the critical part. After WP downloads and unzips the package,
    // it may end up in a wrong-named folder. We rename it to christway-reports/.

    public static function after_install( $response, $hook_extra, $result ) {
        // Only run for our plugin
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::PLUGIN_SLUG ) {
            return $response;
        }

        global $wp_filesystem;

        // Make sure WP_Filesystem is available
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $proper_dir = WP_PLUGIN_DIR . '/' . self::PLUGIN_FOLDER;

        // If the update was extracted to a different folder, rename it
        if ( isset( $result['destination'] ) && realpath( $result['destination'] ) !== realpath( $proper_dir ) ) {
            // Delete the old plugin folder first
            if ( $wp_filesystem->is_dir( $proper_dir ) ) {
                $wp_filesystem->delete( $proper_dir, true );
            }
            // Move extracted folder to the correct location
            $wp_filesystem->move( $result['destination'], $proper_dir, true );
            $result['destination']         = $proper_dir;
            $result['remote_destination']  = $proper_dir;
        }

        // Keep the plugin active
        $active = get_option( 'active_plugins', array() );
        if ( ! in_array( self::PLUGIN_SLUG, $active, true ) ) {
            $active[] = self::PLUGIN_SLUG;
            update_option( 'active_plugins', $active );
        }

        // Clear ALL update caches so WP re-reads the new version number
        delete_transient( self::CACHE_KEY );
        delete_site_transient( 'update_plugins' );
        wp_clean_plugins_cache( true );

        return $result;
    }

    // ─── Admin: Force check now ───────────────────────────────────────────────

    public static function clear_cache() {
        delete_transient( self::CACHE_KEY );
        delete_site_transient( 'update_plugins' );
    }
}
