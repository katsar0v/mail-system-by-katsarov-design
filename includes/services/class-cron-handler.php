<?php
/**
 * Cron Handler Service
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MSKD_Cron_Handler
 * 
 * Handles WP-Cron queue processing
 */
class MSKD_Cron_Handler {

    /**
     * Maximum retry attempts for failed emails
     */
    const MAX_ATTEMPTS = 3;

    /**
     * Timeout in minutes for stuck processing emails
     */
    const PROCESSING_TIMEOUT_MINUTES = 5;

    /**
     * SMTP Mailer instance.
     *
     * @var MSKD_SMTP_Mailer|null
     */
    private $smtp_mailer = null;

    /**
     * Initialize cron hooks
     */
    public function init() {
        add_action( 'mskd_process_queue', array( $this, 'process_queue' ) );
    }

    /**
     * Process email queue
     * 
     * Sends up to MSKD_BATCH_SIZE emails per run
     */
    public function process_queue() {
        global $wpdb;

        // First, recover stuck emails (processing for too long)
        $this->recover_stuck_emails();

        // Get pending emails (including retries)
        $queue_items = $wpdb->get_results( $wpdb->prepare(
            "SELECT q.*, s.email, s.first_name, s.last_name, s.unsubscribe_token
            FROM {$wpdb->prefix}mskd_queue q
            INNER JOIN {$wpdb->prefix}mskd_subscribers s ON q.subscriber_id = s.id
            WHERE q.status = 'pending' 
            AND q.scheduled_at <= %s
            AND s.status = 'active'
            ORDER BY q.scheduled_at ASC
            LIMIT %d",
            current_time( 'mysql' ),
            MSKD_BATCH_SIZE
        ) );

        if ( empty( $queue_items ) ) {
            return;
        }

        // Get settings
        $settings = get_option( 'mskd_settings', array() );

        // Initialize SMTP mailer - required for sending emails.
        require_once MSKD_PLUGIN_DIR . 'includes/services/class-smtp-mailer.php';
        $this->smtp_mailer = new MSKD_SMTP_Mailer( $settings );

        if ( ! $this->smtp_mailer->is_enabled() ) {
            // SMTP not configured, cannot send emails
            error_log( 'MSKD: SMTP not configured. Cannot process email queue.' );
            return;
        }

        foreach ( $queue_items as $item ) {
            // Mark as processing
            $wpdb->update(
                $wpdb->prefix . 'mskd_queue',
                array( 
                    'status'   => 'processing',
                    'attempts' => $item->attempts + 1,
                ),
                array( 'id' => $item->id ),
                array( '%s', '%d' ),
                array( '%d' )
            );

            // Prepare email content with placeholders
            $body = $this->replace_placeholders( $item->body, $item );
            $subject = $this->replace_placeholders( $item->subject, $item );

            // Send email using SMTP mailer.
            $sent          = false;
            $error_message = '';

            $sent = $this->smtp_mailer->send( $item->email, $subject, $body );
            if ( ! $sent ) {
                $error_message = $this->smtp_mailer->get_last_error();
            }

            if ( $sent ) {
                // Mark as sent
                $wpdb->update(
                    $wpdb->prefix . 'mskd_queue',
                    array(
                        'status'  => 'sent',
                        'sent_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $item->id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            } else {
                // Check if we should retry or mark as failed
                $new_attempts = $item->attempts + 1;

                // Build error message with details.
                $base_error = __( 'SMTP sending failed', 'mail-system-by-katsarov-design' );
                
                if ( $new_attempts < self::MAX_ATTEMPTS ) {
                    // Schedule for retry - set back to pending with delayed schedule
                    $retry_delay   = $new_attempts * 2; // 2, 4 minutes delay
                    $retry_message = sprintf(
                        /* translators: 1: Attempt number, 2: Error details */
                        __( 'Attempt %1$d failed. %2$s Will retry.', 'mail-system-by-katsarov-design' ),
                        $new_attempts,
                        $error_message ? '(' . $error_message . ')' : ''
                    );
                    $wpdb->update(
                        $wpdb->prefix . 'mskd_queue',
                        array(
                            'status'        => 'pending',
                            'scheduled_at'  => date( 'Y-m-d H:i:s', strtotime( "+{$retry_delay} minutes" ) ),
                            'error_message' => $retry_message,
                        ),
                        array( 'id' => $item->id ),
                        array( '%s', '%s', '%s' ),
                        array( '%d' )
                    );
                } else {
                    // Max attempts reached, mark as failed
                    $fail_message = sprintf(
                        /* translators: 1: Base error message, 2: Max attempts, 3: Error details */
                        __( '%1$s after %2$d attempts. %3$s', 'mail-system-by-katsarov-design' ),
                        $base_error,
                        self::MAX_ATTEMPTS,
                        $error_message ? '(' . $error_message . ')' : ''
                    );
                    $wpdb->update(
                        $wpdb->prefix . 'mskd_queue',
                        array(
                            'status'        => 'failed',
                            'error_message' => $fail_message,
                        ),
                        array( 'id' => $item->id ),
                        array( '%s', '%s' ),
                        array( '%d' )
                    );
                }
            }
        }
    }

    /**
     * Recover stuck emails that have been processing for too long
     */
    private function recover_stuck_emails() {
        global $wpdb;

        $timeout_threshold = date( 'Y-m-d H:i:s', strtotime( '-' . self::PROCESSING_TIMEOUT_MINUTES . ' minutes' ) );

        // Find emails stuck in processing status
        $stuck_items = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, attempts FROM {$wpdb->prefix}mskd_queue 
            WHERE status = 'processing' 
            AND scheduled_at < %s",
            $timeout_threshold
        ) );

        foreach ( $stuck_items as $item ) {
            if ( $item->attempts < self::MAX_ATTEMPTS ) {
                // Reset to pending for retry
                $wpdb->update(
                    $wpdb->prefix . 'mskd_queue',
                    array(
                        'status'        => 'pending',
                        'scheduled_at'  => current_time( 'mysql' ),
                        'error_message' => __( 'Recovered after stuck in processing', 'mail-system-by-katsarov-design' ),
                    ),
                    array( 'id' => $item->id ),
                    array( '%s', '%s', '%s' ),
                    array( '%d' )
                );
            } else {
                // Max attempts reached, mark as failed
                $wpdb->update(
                    $wpdb->prefix . 'mskd_queue',
                    array(
                        'status'        => 'failed',
                        'error_message' => __( 'Failed after maximum attempts (stuck)', 'mail-system-by-katsarov-design' ),
                    ),
                    array( 'id' => $item->id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            }
        }
    }

    /**
     * Replace placeholders in email content
     *
     * @param string $content Email content
     * @param object $subscriber Subscriber data
     * @return string
     */
    private function replace_placeholders( $content, $subscriber ) {
        $unsubscribe_url = add_query_arg( array(
            'mskd_unsubscribe' => $subscriber->unsubscribe_token,
        ), home_url() );

        $placeholders = array(
            '{first_name}'       => $subscriber->first_name,
            '{last_name}'        => $subscriber->last_name,
            '{email}'            => $subscriber->email,
            '{unsubscribe_link}' => '<a href="' . esc_url( $unsubscribe_url ) . '">' . __( 'Unsubscribe', 'mail-system-by-katsarov-design' ) . '</a>',
            '{unsubscribe_url}'  => $unsubscribe_url,
        );

        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
    }
}
