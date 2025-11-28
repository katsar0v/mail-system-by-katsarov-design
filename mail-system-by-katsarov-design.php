<?php
/**
 * Plugin Name:       Mail System by Katsarov Design
 * Plugin URI:        https://katsarov.design/plugins/mail-system
 * Description:       Email newsletter management system with subscribers, lists, and sending queue.
 * Version:           1.1.0
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
define( 'MSKD_VERSION', '1.1.0' );
define( 'MSKD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSKD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MSKD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MSKD_BATCH_SIZE', 10 ); // Number of emails to send per minute

/**
 * Plugin autoloader.
 *
 * This autoloader handles all plugin classes without requiring Composer.
 * It supports both legacy MSKD_ prefixed classes and PSR-4 namespaced classes.
 *
 * Composer autoloader is only used for development (tests, coding standards).
 * The plugin works completely without the vendor/ directory.
 *
 * Class patterns handled:
 * - MSKD_* (legacy): MSKD_Admin, MSKD_Cron_Handler, etc.
 * - MSKD\Admin\* (PSR-4): MSKD\Admin\Admin, MSKD\Admin\Admin_Email, etc.
 * - MSKD\Services\* (PSR-4): MSKD\Services\List_Service, etc.
 */
spl_autoload_register(
	function ( $class ) {
		// Handle PSR-4 namespaced classes (MSKD\*)
		if ( strpos( $class, 'MSKD\\' ) === 0 ) {
				// Skip test classes - they require Composer autoloader (dev only).
			if ( strpos( $class, 'MSKD\\Tests\\' ) === 0 ) {
				return;
			}

			// Convert namespace to file path.
			// MSKD\Admin\Admin_Email -> includes/Admin/class-admin-email.php
			// MSKD\Services\List_Service -> includes/Services/class-list-service.php
			$relative_class = substr( $class, 5 ); // Remove 'MSKD\' prefix.
			$parts          = explode( '\\', $relative_class );

			// Get class name (last part) and namespace parts.
			$class_name     = array_pop( $parts );
			$namespace_path = implode( '/', $parts );

			// Convert class name to file name (Admin_Email -> admin-email).
			$file_name = strtolower( str_replace( '_', '-', $class_name ) );
			$file      = MSKD_PLUGIN_DIR . 'includes/' . $namespace_path . '/class-' . $file_name . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Handle legacy MSKD_ prefixed classes.
		if ( strpos( $class, 'MSKD_' ) === 0 ) {
			// Convert class name to file path.
			$class_name = str_replace( 'MSKD_', '', $class );
			$class_name = strtolower( str_replace( '_', '-', $class_name ) );

			// Possible file locations for legacy classes.
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
		}
	}
);

/**
 * Load Composer autoloader if available (development only).
 *
 * This is only needed for:
 * - Running tests (PHPUnit, Brain Monkey)
 * - Coding standards checks (PHPCS)
 * - Other dev dependencies
 *
 * The plugin works without this in production.
 */
if ( file_exists( MSKD_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once MSKD_PLUGIN_DIR . 'vendor/autoload.php';
}

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

	// Check for database upgrades.
	MSKD_Activator::maybe_upgrade();

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

/**
 * Normalize a timestamp to have 00 seconds.
 *
 * This ensures all scheduled events and times are on clean minute boundaries.
 *
 * @param int|null $timestamp Unix timestamp. If null, uses current time.
 * @return int Unix timestamp with seconds set to 00.
 */
function mskd_normalize_timestamp( $timestamp = null ) {
	if ( null === $timestamp ) {
		$timestamp = time();
	}
	// Round down to the start of the current minute (remove seconds)
	return (int) ( floor( $timestamp / 60 ) * 60 );
}

/**
 * Get current time in MySQL format with seconds set to 00.
 *
 * @return string MySQL datetime string with 00 seconds.
 */
function mskd_current_time_normalized() {
	$wp_timezone = wp_timezone();
	$now         = new DateTime( 'now', $wp_timezone );
	$now->setTime( (int) $now->format( 'H' ), (int) $now->format( 'i' ), 0 );
	return $now->format( 'Y-m-d H:i:s' );
}

/**
 * Sanitize HTML content for email templates.
 *
 * Unlike wp_kses_post(), this function allows email-specific HTML tags
 * including <style>, <head>, <body>, and full document structure needed
 * for MJML-generated email templates.
 *
 * @param string $content The HTML content to sanitize.
 * @return string Sanitized HTML content.
 */
function mskd_kses_email( $content ) {
	// Define allowed HTML tags for email templates.
	// This includes all standard email HTML elements plus style tags.
	$allowed_tags = array(
		// Document structure.
		'html'       => array(
			'lang'  => true,
			'xmlns' => true,
		),
		'head'       => array(),
		'title'      => array(),
		'meta'       => array(
			'charset'    => true,
			'name'       => true,
			'content'    => true,
			'http-equiv' => true,
		),
		'style'      => array(
			'type' => true,
		),
		'body'       => array(
			'style' => true,
			'class' => true,
		),
		// Text formatting.
		'div'        => array(
			'style' => true,
			'class' => true,
			'id'    => true,
			'align' => true,
		),
		'span'       => array(
			'style' => true,
			'class' => true,
		),
		'p'          => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'br'         => array(),
		'hr'         => array(
			'style' => true,
		),
		'h1'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'h2'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'h3'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'h4'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'strong'     => array(
			'style' => true,
		),
		'b'          => array(
			'style' => true,
		),
		'em'         => array(
			'style' => true,
		),
		'i'          => array(
			'style' => true,
		),
		'u'          => array(
			'style' => true,
		),
		'a'          => array(
			'href'   => true,
			'target' => true,
			'style'  => true,
			'class'  => true,
			'title'  => true,
			'rel'    => true,
		),
		// Tables (essential for email layout).
		'table'      => array(
			'style'       => true,
			'class'       => true,
			'width'       => true,
			'border'      => true,
			'cellpadding' => true,
			'cellspacing' => true,
			'align'       => true,
			'bgcolor'     => true,
			'role'        => true,
		),
		'thead'      => array(
			'style' => true,
		),
		'tbody'      => array(
			'style' => true,
		),
		'tr'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'td'         => array(
			'style'   => true,
			'class'   => true,
			'width'   => true,
			'height'  => true,
			'align'   => true,
			'valign'  => true,
			'bgcolor' => true,
			'colspan' => true,
			'rowspan' => true,
		),
		'th'         => array(
			'style'   => true,
			'class'   => true,
			'width'   => true,
			'align'   => true,
			'valign'  => true,
			'colspan' => true,
			'rowspan' => true,
		),
		// Images.
		'img'        => array(
			'src'    => true,
			'alt'    => true,
			'width'  => true,
			'height' => true,
			'style'  => true,
			'class'  => true,
			'border' => true,
			'title'  => true,
		),
		// Lists.
		'ul'         => array(
			'style' => true,
			'class' => true,
		),
		'ol'         => array(
			'style' => true,
			'class' => true,
		),
		'li'         => array(
			'style' => true,
			'class' => true,
		),
		// Other elements.
		'blockquote' => array(
			'style' => true,
		),
		'center'     => array(
			'style' => true,
		),
		'font'       => array(
			'color' => true,
			'face'  => true,
			'size'  => true,
			'style' => true,
		),
		// Comments are preserved.
	);

	// Apply KSES filtering with email-specific allowed tags.
	return wp_kses( $content, $allowed_tags );
}
