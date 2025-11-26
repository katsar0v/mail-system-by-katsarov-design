<?php
/**
 * SMTP Mailer Service
 *
 * Handles sending emails via SMTP using PHPMailer.
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MSKD_SMTP_Mailer
 *
 * Provides SMTP email sending functionality with proper error handling and logging.
 */
class MSKD_SMTP_Mailer {

    /**
     * SMTP settings.
     *
     * @var array
     */
    private $settings;

    /**
     * Last error message.
     *
     * @var string
     */
    private $last_error = '';

    /**
     * Debug log.
     *
     * @var array
     */
    private $debug_log = array();

    /**
     * Constructor.
     *
     * @param array $settings SMTP settings array.
     */
    public function __construct( $settings = array() ) {
        if ( empty( $settings ) ) {
            $settings = get_option( 'mskd_settings', array() );
        }
        $this->settings = $settings;
    }

    /**
     * Check if SMTP is enabled and configured.
     *
     * @return bool
     */
    public function is_enabled() {
        return ! empty( $this->settings['smtp_enabled'] ) 
            && ! empty( $this->settings['smtp_host'] );
    }

    /**
     * Get the last error message.
     *
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Get the debug log.
     *
     * @return array
     */
    public function get_debug_log() {
        return $this->debug_log;
    }

    /**
     * Send an email via SMTP.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject.
     * @param string $body    Email body (HTML).
     * @param array  $headers Optional. Additional headers.
     * @return bool True on success, false on failure.
     */
    public function send( $to, $subject, $body, $headers = array() ) {
        $this->last_error = '';
        $this->debug_log  = array();

        // Check if SMTP is enabled.
        if ( ! $this->is_enabled() ) {
            $this->log_error( __( 'SMTP не е активиран или конфигуриран.', 'mail-system-by-katsarov-design' ) );
            return false;
        }

        // Create a local PHPMailer instance to avoid conflicts with global instance.
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

        $mailer = new PHPMailer\PHPMailer\PHPMailer( true );

        try {
            // Configure SMTP.
            $mailer->isSMTP();
            $mailer->Host       = $this->settings['smtp_host'];
            $mailer->Port       = ! empty( $this->settings['smtp_port'] ) ? (int) $this->settings['smtp_port'] : 587;
            $mailer->SMTPSecure = $this->get_smtp_secure();
            $mailer->SMTPAuth   = ! empty( $this->settings['smtp_auth'] );

            if ( $mailer->SMTPAuth ) {
                $mailer->Username = ! empty( $this->settings['smtp_username'] ) ? $this->settings['smtp_username'] : '';
                $mailer->Password = ! empty( $this->settings['smtp_password'] ) ? base64_decode( $this->settings['smtp_password'] ) : '';
            }

            // Set sender.
            $from_email = ! empty( $this->settings['from_email'] ) ? $this->settings['from_email'] : get_bloginfo( 'admin_email' );
            $from_name  = ! empty( $this->settings['from_name'] ) ? $this->settings['from_name'] : get_bloginfo( 'name' );
            $mailer->setFrom( $from_email, $from_name );

            // Set reply-to.
            $reply_to = ! empty( $this->settings['reply_to'] ) ? $this->settings['reply_to'] : $from_email;
            $mailer->addReplyTo( $reply_to );

            // Set recipient.
            $mailer->addAddress( $to );

            // Set email content.
            $mailer->isHTML( true );
            $mailer->CharSet  = 'UTF-8';
            $mailer->Subject  = $subject;
            $mailer->Body     = $body;
            $mailer->AltBody  = wp_strip_all_tags( $body );

            // Process additional headers.
            $this->process_headers( $mailer, $headers );

            // Enable debug mode for logging (internal only).
            $mailer->SMTPDebug = 0;
            $mailer->Debugoutput = function( $str, $level ) {
                $this->debug_log[] = array(
                    'level'   => $level,
                    'message' => $str,
                    'time'    => current_time( 'mysql' ),
                );
            };

            // Attempt to send.
            $result = $mailer->send();

            if ( $result ) {
                $this->log_success( $to, $subject );
            }

            return $result;

        } catch ( PHPMailer\PHPMailer\Exception $e ) {
            $this->log_error( $mailer->ErrorInfo );
            return false;
        } catch ( Exception $e ) {
            $this->log_error( $e->getMessage() );
            return false;
        }
    }

    /**
     * Test SMTP connection.
     *
     * @return array Result array with 'success' and 'message' keys.
     */
    public function test_connection() {
        $this->last_error = '';
        $this->debug_log  = array();

        // Check if SMTP is enabled.
        if ( ! $this->is_enabled() ) {
            return array(
                'success' => false,
                'message' => __( 'SMTP не е активиран или конфигуриран. Моля, попълнете SMTP настройките.', 'mail-system-by-katsarov-design' ),
            );
        }

        // Validate from_email before proceeding.
        $from_email = ! empty( $this->settings['from_email'] ) ? $this->settings['from_email'] : get_bloginfo( 'admin_email' );
        if ( ! is_email( $from_email ) ) {
            return array(
                'success' => false,
                'message' => __( 'Невалиден имейл адрес на подателя.', 'mail-system-by-katsarov-design' ),
            );
        }

        // Use WordPress PHPMailer.
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

        $mailer = new PHPMailer\PHPMailer\PHPMailer( true );

        try {
            // Configure SMTP.
            $mailer->isSMTP();
            $mailer->Host       = $this->settings['smtp_host'];
            $mailer->Port       = ! empty( $this->settings['smtp_port'] ) ? (int) $this->settings['smtp_port'] : 587;
            $mailer->SMTPSecure = $this->get_smtp_secure();
            $mailer->SMTPAuth   = ! empty( $this->settings['smtp_auth'] );
            $mailer->Timeout    = 15;

            if ( $mailer->SMTPAuth ) {
                $mailer->Username = ! empty( $this->settings['smtp_username'] ) ? $this->settings['smtp_username'] : '';
                $mailer->Password = ! empty( $this->settings['smtp_password'] ) ? base64_decode( $this->settings['smtp_password'] ) : '';
            }

            // Set sender.
            $from_name  = ! empty( $this->settings['from_name'] ) ? $this->settings['from_name'] : get_bloginfo( 'name' );
            $mailer->setFrom( $from_email, $from_name );

            // Set test recipient.
            $to = get_bloginfo( 'admin_email' );
            $mailer->addAddress( $to );

            // Set test email content.
            $mailer->isHTML( true );
            $mailer->CharSet = 'UTF-8';
            $mailer->Subject = sprintf(
                /* translators: %s: Site name */
                __( '[%s] SMTP Тестов имейл', 'mail-system-by-katsarov-design' ),
                get_bloginfo( 'name' )
            );
            $mailer->Body = sprintf(
                /* translators: %1$s: Current time, %2$s: SMTP host, %3$s: SMTP port */
                __( '<h2>SMTP Тестов имейл</h2><p>Този имейл потвърждава, че SMTP настройките работят правилно.</p><p><strong>Време:</strong> %1$s</p><p><strong>SMTP сървър:</strong> %2$s:%3$s</p>', 'mail-system-by-katsarov-design' ),
                current_time( 'mysql' ),
                $this->settings['smtp_host'],
                $this->settings['smtp_port']
            );
            $mailer->AltBody = wp_strip_all_tags( $mailer->Body );

            // Try to send.
            $result = $mailer->send();

            if ( $result ) {
                $this->log_success( $to, $mailer->Subject );
                return array(
                    'success' => true,
                    'message' => sprintf(
                        /* translators: %s: Admin email address */
                        __( 'Тестовият имейл е изпратен успешно до %s!', 'mail-system-by-katsarov-design' ),
                        $to
                    ),
                );
            }

            return array(
                'success' => false,
                'message' => __( 'Неуспешно изпращане на тестовия имейл.', 'mail-system-by-katsarov-design' ),
            );

        } catch ( PHPMailer\PHPMailer\Exception $e ) {
            $this->log_error( $mailer->ErrorInfo );
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: Error message */
                    __( 'SMTP грешка: %s', 'mail-system-by-katsarov-design' ),
                    $mailer->ErrorInfo
                ),
            );
        } catch ( Exception $e ) {
            $this->log_error( $e->getMessage() );
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: Error message */
                    __( 'Грешка: %s', 'mail-system-by-katsarov-design' ),
                    $e->getMessage()
                ),
            );
        }
    }

    /**
     * Get SMTP secure setting.
     *
     * @return string PHPMailer secure type.
     */
    private function get_smtp_secure() {
        $security = ! empty( $this->settings['smtp_security'] ) ? $this->settings['smtp_security'] : '';

        switch ( $security ) {
            case 'ssl':
                return PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            case 'tls':
                return PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            default:
                return '';
        }
    }

    /**
     * Process additional headers.
     *
     * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
     * @param array                          $headers   Headers array.
     */
    private function process_headers( $phpmailer, $headers ) {
        if ( empty( $headers ) || ! is_array( $headers ) ) {
            return;
        }

        foreach ( $headers as $header ) {
            if ( is_string( $header ) && strpos( $header, ':' ) !== false ) {
                list( $name, $value ) = explode( ':', $header, 2 );
                $name  = trim( $name );
                $value = trim( $value );

                // Skip headers that PHPMailer handles.
                if ( in_array( strtolower( $name ), array( 'from', 'to', 'cc', 'bcc', 'reply-to', 'content-type' ), true ) ) {
                    continue;
                }

                $phpmailer->addCustomHeader( $name, $value );
            }
        }
    }

    /**
     * Log successful email send.
     *
     * @param string $to      Recipient.
     * @param string $subject Subject.
     */
    private function log_success( $to, $subject ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( sprintf(
                '[MSKD SMTP] Email sent successfully to %s: %s',
                $to,
                $subject
            ) );
        }
    }

    /**
     * Log an error.
     *
     * @param string $message Error message.
     */
    private function log_error( $message ) {
        $this->last_error = $message;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( sprintf( '[MSKD SMTP] Error: %s', $message ) );
        }
    }
}
