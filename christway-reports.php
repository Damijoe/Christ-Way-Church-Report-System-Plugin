<?php
/**
 * Plugin Name: ChristWay Church Reporting System
 * Plugin URI:  https://christwaychurch.org
 * Description: Weekly reporting system for Christ Way Church Treasure House — manages churches, zones, pastors, attendance and financial reports with Excel/PDF exports.
 * Version:     1.0.0
 * Author:      Damijoe Digitals
 * Text Domain: christway-reports
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CWR_VERSION',   '1.0.0' );
define( 'CWR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CWR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CWR_DB_VERSION', '1.0' );

require_once CWR_PLUGIN_DIR . 'includes/class-database.php';
require_once CWR_PLUGIN_DIR . 'includes/class-roles.php';
require_once CWR_PLUGIN_DIR . 'includes/class-auth.php';
require_once CWR_PLUGIN_DIR . 'includes/class-api.php';
require_once CWR_PLUGIN_DIR . 'includes/class-notifications.php';
require_once CWR_PLUGIN_DIR . 'includes/class-updater.php';
require_once CWR_PLUGIN_DIR . 'includes/class-xlsx-writer.php';
require_once CWR_PLUGIN_DIR . 'includes/class-pdf-writer.php';
require_once CWR_PLUGIN_DIR . 'includes/class-export.php';
require_once CWR_PLUGIN_DIR . 'admin/admin-page.php';

register_activation_hook( __FILE__,   [ 'CWR_Database', 'install' ] );
register_deactivation_hook( __FILE__, [ 'CWR_Roles',    'remove_roles' ] );

add_action( 'plugins_loaded', 'cwr_init' );
function cwr_init() {
    CWR_Roles::init();
    CWR_API::init();
    CWR_Notifications::init();
    CWR_Auth::init();
    CWR_Updater::init();
}

// Enqueue frontend assets
add_action( 'wp_enqueue_scripts', 'cwr_enqueue_assets' );
function cwr_enqueue_assets() {
    global $post;
    if ( ! $post || ! has_shortcode( $post->post_content, 'christway_portal' ) ) return;
    wp_enqueue_style( 'cwr-style', CWR_PLUGIN_URL . 'assets/css/portal.css', [], CWR_VERSION );
    wp_enqueue_script( 'cwr-app', CWR_PLUGIN_URL . 'frontend/build/main.js', [], CWR_VERSION, true );
    wp_localize_script( 'cwr-app', 'CWR_CONFIG', [
        'apiUrl'    => rest_url( 'christway/v1' ),
        'nonce'     => wp_create_nonce( 'wp_rest' ),
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'siteUrl'   => site_url(),
        'pluginUrl' => CWR_PLUGIN_URL,
        'userId'    => get_current_user_id(),
        'userRole'  => cwr_get_user_role( get_current_user_id() ),
    ] );
}

// Shortcode to embed portal
add_shortcode( 'christway_portal', 'cwr_render_portal' );
function cwr_render_portal() {
    ob_start(); ?>
    <div id="cwr-root" data-nonce="<?php echo wp_create_nonce('wp_rest'); ?>"></div>
    <?php return ob_get_clean();
}

function cwr_get_user_role( $user_id ) {
    if ( ! $user_id ) return 'guest';
    $user = get_userdata( $user_id );
    if ( ! $user ) return 'guest';
    $roles = (array) $user->roles;
    if ( in_array( 'cwr_admin',       $roles ) ) return 'admin';
    if ( in_array( 'cwr_area_pastor', $roles ) ) return 'area_pastor';
    if ( in_array( 'cwr_pastor',      $roles ) ) return 'pastor';
    if ( in_array( 'administrator',   $roles ) ) return 'admin';
    return 'guest';
}
