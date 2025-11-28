<?php
/**
 * Admin Email Controller
 *
 * Handles email compose and one-time email admin actions.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

use MSKD\Services\Email_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Admin_Email
 *
 * Controller for email compose and one-time email pages.
 */
class Admin_Email {

    /**
     * Email service instance.
     *
     * @var Email_Service
     */
    private $service;

    /**
     * Form data preserved on error for one-time email.
     *
     * @var array
     */
    private $one_time_email_form_data = array();

    /**
     * Last mail error.
     *
     * @var string
     */
    private $last_mail_error = '';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->service = new Email_Service();
    }

    /**
     * Handle email-related actions.
     *
     * @return void
     */
    public function handle_actions(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle compose/send action.
        if ( isset( $_POST['mskd_send_email'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_send_email' ) ) {
            $this->handle_queue_email();
        }

        // Handle one-time email send action.
        if ( isset( $_POST['mskd_send_one_time_email'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_send_one_time_email' ) ) {
            $this->handle_one_time_email();
        }
    }

    /**
     * Handle queue email (campaign) action.
     *
     * @return void
     */
    private function handle_queue_email(): void {
        // Load the List Provider service.
        require_once MSKD_PLUGIN_DIR . 'includes/services/class-list-provider.php';

        $subject  = sanitize_text_field( $_POST['subject'] );
        $body     = wp_kses_post( $_POST['body'] );
        $list_ids = isset( $_POST['lists'] ) ? array_map( 'sanitize_text_field', $_POST['lists'] ) : array();

        if ( empty( $subject ) || empty( $body ) || empty( $list_ids ) ) {
            add_settings_error(
                'mskd_messages',
                'mskd_error',
                __( 'Please fill in all fields.', 'mail-system-by-katsarov-design' ),
                'error'
            );
            return;
        }

        // Get active subscribers from selected lists with full data.
        $all_subscribers = array();
        $seen_emails     = array();

        foreach ( $list_ids as $list_id ) {
            $list_subscribers = \MSKD_List_Provider::get_list_subscribers_full( $list_id );
            foreach ( $list_subscribers as $subscriber ) {
                // Dedupe by email.
                if ( ! in_array( $subscriber->email, $seen_emails, true ) ) {
                    $all_subscribers[] = $subscriber;
                    $seen_emails[]     = $subscriber->email;
                }
            }
        }

        if ( empty( $all_subscribers ) ) {
            add_settings_error(
                'mskd_messages',
                'mskd_error',
                __( 'No active subscribers in the selected lists.', 'mail-system-by-katsarov-design' ),
                'error'
            );
            return;
        }

        // Calculate scheduled time.
        $scheduled_at = $this->service->calculate_scheduled_time( $_POST );
        $is_immediate = $this->service->is_immediate_send( $_POST );

        // Queue the campaign.
        $campaign_id = $this->service->queue_campaign( array(
            'subject'      => $subject,
            'body'         => $body,
            'list_ids'     => $list_ids,
            'subscribers'  => $all_subscribers,
            'scheduled_at' => $scheduled_at,
        ) );

        if ( ! $campaign_id ) {
            add_settings_error(
                'mskd_messages',
                'mskd_error',
                __( 'Error creating campaign.', 'mail-system-by-katsarov-design' ),
                'error'
            );
            return;
        }

        $queued = count( $all_subscribers );

        if ( $is_immediate ) {
            add_settings_error(
                'mskd_messages',
                'mskd_success',
                sprintf(
                    __( '%d emails have been added to the sending queue.', 'mail-system-by-katsarov-design' ),
                    $queued
                ),
                'success'
            );
        } else {
            // Format scheduled time for display.
            $wp_timezone    = wp_timezone();
            $scheduled_date = new \DateTime( $scheduled_at, $wp_timezone );
            $formatted_date = $scheduled_date->format( 'd.m.Y H:i' );

            add_settings_error(
                'mskd_messages',
                'mskd_success',
                sprintf(
                    __( '%1$d emails have been scheduled for %2$s.', 'mail-system-by-katsarov-design' ),
                    $queued,
                    esc_html( $formatted_date )
                ),
                'success'
            );
        }
    }

    /**
     * Handle one-time email action.
     *
     * @return void
     */
    private function handle_one_time_email(): void {
        $recipient_email = sanitize_email( $_POST['recipient_email'] );
        $recipient_name  = sanitize_text_field( $_POST['recipient_name'] );
        $subject         = sanitize_text_field( $_POST['subject'] );
        $body            = wp_kses_post( $_POST['body'] );
        $schedule_type   = isset( $_POST['schedule_type'] ) ? sanitize_text_field( $_POST['schedule_type'] ) : 'now';

        // Store form data for preservation on error.
        $this->one_time_email_form_data = array(
            'recipient_email'    => $recipient_email,
            'recipient_name'     => $recipient_name,
            'subject'            => $subject,
            'body'               => $body,
            'schedule_type'      => $schedule_type,
            'scheduled_datetime' => isset( $_POST['scheduled_datetime'] ) ? sanitize_text_field( $_POST['scheduled_datetime'] ) : '',
            'delay_value'        => isset( $_POST['delay_value'] ) ? intval( $_POST['delay_value'] ) : 1,
            'delay_unit'         => isset( $_POST['delay_unit'] ) ? sanitize_text_field( $_POST['delay_unit'] ) : 'hours',
        );

        // Validate required fields.
        if ( empty( $recipient_email ) || empty( $subject ) || empty( $body ) ) {
            add_settings_error(
                'mskd_messages',
                'mskd_error',
                __( 'Please fill in all required fields.', 'mail-system-by-katsarov-design' ),
                'error'
            );
            return;
        }

        // Validate email format.
        if ( ! is_email( $recipient_email ) ) {
            add_settings_error(
                'mskd_messages',
                'mskd_error',
                __( 'Invalid recipient email address.', 'mail-system-by-katsarov-design' ),
                'error'
            );
            return;
        }

        // Replace basic placeholders.
        $body = str_replace(
            array( '{recipient_name}', '{recipient_email}' ),
            array( $recipient_name, $recipient_email ),
            $body
        );
        $subject = str_replace(
            array( '{recipient_name}', '{recipient_email}' ),
            array( $recipient_name, $recipient_email ),
            $subject
        );

        // Calculate scheduled time.
        $scheduled_at = $this->service->calculate_scheduled_time( $_POST );
        $is_immediate = $this->service->is_immediate_send( $_POST );

        // Load SMTP Mailer.
        require_once MSKD_PLUGIN_DIR . 'includes/services/class-smtp-mailer.php';
        $mailer = new \MSKD_SMTP_Mailer();

        if ( $is_immediate ) {
            // Send immediately (via SMTP if configured, otherwise via PHP mail).
            $sent = $mailer->send( $recipient_email, $subject, $body );

            if ( ! $sent ) {
                $this->last_mail_error = $mailer->get_last_error();
            }

            // Queue for logging.
            $this->service->queue_one_time( array(
                'recipient_email' => $recipient_email,
                'recipient_name'  => $recipient_name,
                'subject'         => $subject,
                'body'            => $body,
                'scheduled_at'    => $scheduled_at,
                'is_immediate'    => true,
                'sent'            => $sent,
                'error_message'   => $sent ? null : ( $this->last_mail_error ?: __( 'wp_mail() failed for one-time email', 'mail-system-by-katsarov-design' ) ),
            ) );

            if ( $sent ) {
                // Clear form data on success.
                $this->one_time_email_form_data = array();
                add_settings_error(
                    'mskd_messages',
                    'mskd_success',
                    sprintf(
                        __( 'One-time email sent successfully to %s.', 'mail-system-by-katsarov-design' ),
                        esc_html( $recipient_email )
                    ),
                    'success'
                );
            } else {
                $error_message = __( 'Error sending one-time email.', 'mail-system-by-katsarov-design' );
                if ( ! empty( $this->last_mail_error ) ) {
                    $error_message .= ' ' . sprintf(
                        __( 'Reason: %s', 'mail-system-by-katsarov-design' ),
                        esc_html( $this->last_mail_error )
                    );
                } else {
                    $error_message .= ' ' . __( 'Please try again.', 'mail-system-by-katsarov-design' );
                }
                add_settings_error( 'mskd_messages', 'mskd_error', $error_message, 'error' );
            }
        } else {
            // Schedule for later.
            $result = $this->service->queue_one_time( array(
                'recipient_email' => $recipient_email,
                'recipient_name'  => $recipient_name,
                'subject'         => $subject,
                'body'            => $body,
                'scheduled_at'    => $scheduled_at,
                'is_immediate'    => false,
            ) );

            if ( $result ) {
                // Clear form data on success.
                $this->one_time_email_form_data = array();

                // Format scheduled time for display.
                $wp_timezone    = wp_timezone();
                $scheduled_date = new \DateTime( $scheduled_at, $wp_timezone );
                $formatted_date = $scheduled_date->format( 'd.m.Y H:i' );

                add_settings_error(
                    'mskd_messages',
                    'mskd_success',
                    sprintf(
                        __( 'One-time email to %1$s has been scheduled for %2$s.', 'mail-system-by-katsarov-design' ),
                        esc_html( $recipient_email ),
                        esc_html( $formatted_date )
                    ),
                    'success'
                );
            } else {
                add_settings_error(
                    'mskd_messages',
                    'mskd_error',
                    __( 'Error scheduling email. Please try again.', 'mail-system-by-katsarov-design' ),
                    'error'
                );
            }
        }
    }

    /**
     * Render the compose page.
     *
     * @return void
     */
    public function render_compose(): void {
        include MSKD_PLUGIN_DIR . 'admin/partials/compose-wizard.php';
    }

    /**
     * Render the legacy compose page (direct access).
     *
     * @return void
     */
    public function render_compose_legacy(): void {
        include MSKD_PLUGIN_DIR . 'admin/partials/compose.php';
    }

    /**
     * Render the one-time email page.
     *
     * @return void
     */
    public function render_one_time(): void {
        $form_data = $this->get_one_time_email_form_data();
        include MSKD_PLUGIN_DIR . 'admin/partials/one-time-email.php';
    }

    /**
     * Get preserved form data for one-time email.
     *
     * @return array
     */
    public function get_one_time_email_form_data(): array {
        return $this->one_time_email_form_data;
    }

    /**
     * Get the service instance.
     *
     * @return Email_Service
     */
    public function get_service(): Email_Service {
        return $this->service;
    }
}
