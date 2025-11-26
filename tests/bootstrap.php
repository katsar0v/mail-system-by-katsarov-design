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

// Load base TestCase class.
require_once __DIR__ . '/Unit/TestCase.php';

// Define WordPress constants for testing.
define( 'ABSPATH', '/tmp/wordpress/' );
define( 'WPINC', 'wp-includes' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MSKD_VERSION', '1.0.0' );
define( 'MSKD_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MSKD_PLUGIN_URL', 'https://example.com/wp-content/plugins/mail-system-by-katsarov-design/' );
define( 'MSKD_PLUGIN_BASENAME', 'mail-system-by-katsarov-design/mail-system-by-katsarov-design.php' );
define( 'MSKD_BATCH_SIZE', 10 );

// Create mock directory structure for PHPMailer.
$phpmailer_dir = '/tmp/wordpress/wp-includes/PHPMailer';
if ( ! is_dir( $phpmailer_dir ) ) {
    mkdir( $phpmailer_dir, 0777, true );
}

// Create mock PHPMailer classes for testing.
if ( ! file_exists( $phpmailer_dir . '/PHPMailer.php' ) ) {
    file_put_contents( $phpmailer_dir . '/PHPMailer.php', '<?php
namespace PHPMailer\PHPMailer;
class PHPMailer {
    public $Host = "";
    public $Port = 587;
    public $SMTPSecure = "";
    public $SMTPAuth = false;
    public $Username = "";
    public $Password = "";
    public $CharSet = "UTF-8";
    public function __construct($exceptions = null) {}
    public function isSMTP() {}
    public function setFrom($address, $name = "") {}
    public function addReplyTo($address, $name = "") {}
    public function addAddress($address, $name = "") {}
    public function isHTML($isHtml = true) {}
    public function send() { return true; }
}
' );
}

if ( ! file_exists( $phpmailer_dir . '/SMTP.php' ) ) {
    file_put_contents( $phpmailer_dir . '/SMTP.php', '<?php
namespace PHPMailer\PHPMailer;
class SMTP {}
' );
}

if ( ! file_exists( $phpmailer_dir . '/Exception.php' ) ) {
    file_put_contents( $phpmailer_dir . '/Exception.php', '<?php
namespace PHPMailer\PHPMailer;
class Exception extends \Exception {}
' );
}
