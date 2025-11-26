<?php
/**
 * Plugin Name:       Mail System by Katsarov Design
 * Plugin URI:        https://katsarov.design/plugins/mail-system
 * Description:       Email newsletter management system with subscribers, lists, and sending queue.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Katsarov Design
 * Author URI:        https://katsarov.design
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mail-system-by-katsarov-design
 * Domain Path:       /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants
 */
define( 'MSKD_VERSION', '1.0.0' );
define( 'MSKD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSKD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MSKD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MSKD_BATCH_SIZE', 10 ); // Number of emails to send per minute

/**
 * Autoloader for MSKD_ classes
 */
spl_autoload_register( function( $class ) {
    // Only autoload MSKD_ prefixed classes
    if ( strpos( $class, 'MSKD_' ) !== 0 ) {
        return;
    }

    // Convert class name to file path
    $class_name = str_replace( 'MSKD_', '', $class );
    $class_name = strtolower( str_replace( '_', '-', $class_name ) );
    
    // Possible file locations
    $locations = array(
        MSKD_PLUGIN_DIR . 'includes/class-' . $class_name . '.php',
        MSKD_PLUGIN_DIR . 'includes/models/class-' . $class_name . '.php',
        MSKD_PLUGIN_DIR . 'includes/services/class-' . $class_name . '.php',
        MSKD_PLUGIN_DIR . 'admin/class-' . $class_name . '.php',
        MSKD_PLUGIN_DIR . 'public/class-' . $class_name . '.php',
    );

    foreach ( $locations as $file ) {
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
});

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, array( 'MSKD_Activator', 'activate' ) );

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, array( 'MSKD_Deactivator', 'deactivate' ) );

/**
 * Load plugin textdomain for translations
 */
function mskd_load_textdomain() {
    load_plugin_textdomain(
        'mail-system-by-katsarov-design',
        false,
        dirname( MSKD_PLUGIN_BASENAME ) . '/languages'
    );
}
add_action( 'init', 'mskd_load_textdomain' );

/**
 * Initialize the plugin
 */
function mskd_init() {
    // Load required files
    require_once MSKD_PLUGIN_DIR . 'includes/class-activator.php';
    require_once MSKD_PLUGIN_DIR . 'includes/class-deactivator.php';
    
    // Initialize admin
    if ( is_admin() ) {
        require_once MSKD_PLUGIN_DIR . 'admin/class-admin.php';
        $admin = new MSKD_Admin();
        $admin->init();
    }
    
    // Initialize public
    require_once MSKD_PLUGIN_DIR . 'public/class-public.php';
    $public = new MSKD_Public();
    $public->init();
    
    // Initialize cron handler
    require_once MSKD_PLUGIN_DIR . 'includes/services/class-cron-handler.php';
    $cron = new MSKD_Cron_Handler();
    $cron->init();
}
add_action( 'plugins_loaded', 'mskd_init' );

/**
 * Register custom cron schedules
 */
function mskd_cron_schedules( $schedules ) {
    $schedules['mskd_every_minute'] = array(
        'interval' => 60,
        'display'  => __( 'Every minute', 'mail-system-by-katsarov-design' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'mskd_cron_schedules' );
