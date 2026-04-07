<?php
/**
 * ChristWay GitHub Update Checker
 *
 * Hooks into WordPress's native update system to check for new releases
 * on GitHub. When a new release is published on GitHub, WordPress will
 * show an "Update available" notice in the Plugins page — just like any
 * other plugin.
 *
 * HOW TO USE:
 * 1. Push this plugin to a GitHub repository
 * 2. Set GITHUB_REPO below to your "username/repository-name"
 * 3. When you have a new version, bump CWR_VERSION in christway-reports.php
 * 4. Create a new GitHub Release tagged as e.g. "v1.0.1"
 *    and attach the plugin zip as a release asset
 * 5. WordPress will detect the update automatically
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CWR_Updater {

    // ── CONFIGURE THIS ──────────────────────────────────────────────────────
    const GITHUB_REPO = 'YOUR-USERNAME/christway-reports';
    // e.g. 'johndoe/christway-reports'
    // ────────────────────────────────────────────────────────────────────────

    const PLUGIN_SLUG = 'christway-reports/christway-reports.php';
    const CACHE_KEY   = 'cwr_github_update_data';
    const CACHE_TTL   = 43200; // 12 hours in seconds

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
        add_filter( 'plugins_api',                           array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_post_install',                 array( __CLASS__, 'after_install' ), 10, 3 );
        add_action( 'admin_notices',                         array( __CLASS__, 'maybe_show_config_notice' ) );
    }

    /**
     * Fetch latest release data from GitHub API
     * Caches for 12 hours to avoid hitting GitHub rate limits
     */
    private static function get_github_release() {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) return $cached;

        $repo = self::GITHUB_REPO;

        // Skip if not configured yet
        if ( $repo === 'YOUR-USERNAME/christway-reports' ) return false;

        $url      = "https://api.github.com/repos/{$repo}/releases/latest";
        $response = wp_remote_get( $url, array(
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
                'Accept'     => 'application/vnd.github.v3+json',
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) return false;
        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) return false;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['tag_name'] ) ) return false;

        // Find the zip asset
        $zip_url = '';
        if ( ! empty( $body['assets'] ) ) {
            foreach ( $body['assets'] as $asset ) {
                if ( isset( $asset['content_type'] ) &&
                     strpos( $asset['content_type'], 'zip' ) !== false ) {
                    $zip_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        // Fallback: use the auto-generated source zip
        if ( ! $zip_url ) {
            $zip_url = "https://github.com/{$repo}/archive/refs/tags/{$body['tag_name']}.zip";
        }

        $data = array(
            'version'      => ltrim( $body['tag_name'], 'v' ), // strip leading 'v' from e.g. "v1.0.1"
            'zip_url'      => $zip_url,
            'description'  => $body['body'] ?? '',
            'release_date' => isset( $body['published_at'] ) ? date( 'Y-m-d', strtotime( $body['published_at'] ) ) : '',
            'tag'          => $body['tag_name'],
        );

        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
        return $data;
    }

    /**
     * WordPress calls this to check if updates are available.
     * We inject our GitHub release data into the transient.
     */
    public static function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = self::get_github_release();
        if ( ! $release ) return $transient;

        $current_version = CWR_VERSION;

        // Only flag as update if GitHub version is newer
        if ( version_compare( $release['version'], $current_version, '>' ) ) {
            $transient->response[ self::PLUGIN_SLUG ] = (object) array(
                'slug'        => 'christway-reports',
                'plugin'      => self::PLUGIN_SLUG,
                'new_version' => $release['version'],
                'url'         => 'https://github.com/' . self::GITHUB_REPO,
                'package'     => $release['zip_url'],
                'tested'      => get_bloginfo('version'),
                'requires'    => '5.8',
                'requires_php'=> '7.4',
            );
        }

        return $transient;
    }

    /**
     * Provides plugin info for the "View version details" modal
     * when clicking the version link in the Plugins page.
     */
    public static function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== 'christway-reports' ) return $result;

        $release = self::get_github_release();
        if ( ! $release ) return $result;

        $repo = self::GITHUB_REPO;

        return (object) array(
            'name'          => 'ChristWay Church Reporting System',
            'slug'          => 'christway-reports',
            'version'       => $release['version'],
            'author'        => '<a href="https://damijoe.com">Damijoe Digitals</a>',
            'requires'      => '5.8',
            'tested'        => get_bloginfo('version'),
            'requires_php'  => '7.4',
            'last_updated'  => $release['release_date'],
            'homepage'      => 'https://github.com/' . $repo,
            'download_link' => $release['zip_url'],
            'sections'      => array(
                'description' => '<p>Weekly reporting system for Christ Way Church Treasure House. Manages churches, zones, pastors, attendance and financial reports.</p>',
                'changelog'   => '<pre>' . esc_html( $release['description'] ) . '</pre>',
            ),
        );
    }

    /**
     * After WordPress installs the update, make sure the plugin folder
     * is named correctly (GitHub zips sometimes add a suffix like -main or -v1.0.1)
     */
    public static function after_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) ||
             $hook_extra['plugin'] !== self::PLUGIN_SLUG ) {
            return $response;
        }

        global $wp_filesystem;
        $plugin_dir = WP_PLUGIN_DIR . '/christway-reports';

        // If WordPress extracted to a differently-named folder, rename it
        if ( isset( $result['destination'] ) &&
             $result['destination'] !== $plugin_dir ) {
            $wp_filesystem->move( $result['destination'], $plugin_dir );
            $result['destination'] = $plugin_dir;
        }

        // Re-activate plugin after update
        $active_plugins = get_option( 'active_plugins', array() );
        if ( ! in_array( self::PLUGIN_SLUG, $active_plugins ) ) {
            $active_plugins[] = self::PLUGIN_SLUG;
            update_option( 'active_plugins', $active_plugins );
        }

        return $result;
    }

    /**
     * Show a notice in WP Admin if the GitHub repo hasn't been configured yet
     */
    public static function maybe_show_config_notice() {
        if ( self::GITHUB_REPO === 'YOUR-USERNAME/christway-reports' ) {
            $screen = get_current_screen();
            if ( $screen && in_array( $screen->id, array( 'plugins', 'dashboard' ) ) ) {
                echo '<div class="notice notice-warning is-dismissible">
                    <p><strong>ChristWay Reports:</strong> GitHub repository not configured.
                    Open <code>wp-content/plugins/christway-reports/includes/class-updater.php</code>
                    and set <code>GITHUB_REPO</code> to your GitHub username/repository to enable automatic updates.</p>
                </div>';
            }
        }
    }

    /**
     * Force-clear the update cache (useful after publishing a new release)
     * Call via: CWR_Updater::clear_cache();
     */
    public static function clear_cache() {
        delete_transient( self::CACHE_KEY );
    }
}
