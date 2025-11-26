<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package MSKD\Tests
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Brain Monkey.
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';

// Define WordPress constants for testing.
define( 'ABSPATH', '/tmp/wordpress/' );
define( 'MSKD_VERSION', '1.0.0' );
define( 'MSKD_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MSKD_PLUGIN_URL', 'https://example.com/wp-content/plugins/mail-system-by-katsarov-design/' );
define( 'MSKD_PLUGIN_BASENAME', 'mail-system-by-katsarov-design/mail-system-by-katsarov-design.php' );
define( 'MSKD_BATCH_SIZE', 10 );
