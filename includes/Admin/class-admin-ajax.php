<?php
/**
 * Admin AJAX Controller
 *
 * Handles all AJAX requests for the admin area.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

use MSKD\Services\Subscriber_Service;
use MSKD\Services\List_Service;
use MSKD\Services\Email_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Admin_Ajax
 *
 * Handles AJAX requests for admin functionality.
 */
class Admin_Ajax {

    /**
     * Initialize AJAX hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'wp_ajax_mskd_test_smtp', array( $this, 'test_smtp' ) );
        add_action( 'wp_ajax_mskd_truncate_subscribers', array( $this, 'truncate_subscribers' ) );
        add_action( 'wp_ajax_mskd_truncate_lists', array( $this, 'truncate_lists' ) );
        add_action( 'wp_ajax_mskd_truncate_queue', array( $this, 'truncate_queue' ) );
        add_action( 'wp_ajax_mskd_dismiss_share_notice', array( $this, 'dismiss_share_notice' ) );
    }

    /**
     * AJAX handler for SMTP test.
     *
     * @return void
     */
    public function test_smtp(): void {
        // Verify nonce.
        if ( ! check_ajax_referer( 'mskd_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid request. Please refresh the page.', 'mail-system-by-katsarov-design' ),
            ) );
        }

        // Check permissions.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission for this operation.', 'mail-system-by-katsarov-design' ),
            ) );
        }

        // Load SMTP Mailer.
        require_once MSKD_PLUGIN_DIR . 'includes/services/class-smtp-mailer.php';

        $smtp_mailer = new \MSKD_SMTP_Mailer();
        $result      = $smtp_mailer->test_connection();

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message' => $result['message'],
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result['message'],
            ) );
        }
    }

    /**
     * AJAX handler: Truncate subscribers table.
     *
     * @return void
     */
    public function truncate_subscribers(): void {
        check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission for this operation.', 'mail-system-by-katsarov-design' ),
            ) );
        }

        $service = new Subscriber_Service();
        $service->truncate_all();

        wp_send_json_success( array(
            'message' => __( 'All subscribers deleted successfully.', 'mail-system-by-katsarov-design' ),
        ) );
    }

    /**
     * AJAX handler: Truncate lists table.
     *
     * @return void
     */
    public function truncate_lists(): void {
        check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission for this operation.', 'mail-system-by-katsarov-design' ),
            ) );
        }

        $service = new List_Service();
        $service->truncate_all();

        wp_send_json_success( array(
            'message' => __( 'All lists deleted successfully.', 'mail-system-by-katsarov-design' ),
        ) );
    }

    /**
     * AJAX handler: Truncate queue table.
     *
     * @return void
     */
    public function truncate_queue(): void {
        check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission for this operation.', 'mail-system-by-katsarov-design' ),
            ) );
        }

        $service = new Email_Service();
        $service->truncate_queue();

        wp_send_json_success( array(
            'message' => __( 'All queued emails deleted successfully.', 'mail-system-by-katsarov-design' ),
        ) );
    }

    /**
     * AJAX handler: Dismiss share notice permanently.
     *
     * @return void
     */
    public function dismiss_share_notice(): void {
        check_ajax_referer( 'mskd_dismiss_share_notice', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        update_option( 'mskd_share_notice_dismissed', true );
        wp_send_json_success();
    }
}
