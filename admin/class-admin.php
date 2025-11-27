<?php
/**
 * Legacy Admin Class - Facade for backward compatibility
 *
 * @package MSKD
 * @deprecated Use MSKD\Admin\Admin instead
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MSKD\Admin\Admin;

/**
 * Class MSKD_Admin
 *
 * Legacy facade that delegates to the new namespaced Admin class.
 * Maintained for backward compatibility with existing code.
 *
 * @deprecated 2.0.0 Use MSKD\Admin\Admin directly
 */
class MSKD_Admin {

    /**
     * Plugin pages prefix
     */
    const PAGE_PREFIX = 'mskd-';

    /**
     * Subscriber ID used to identify one-time emails (not associated with any subscriber)
     */
    const ONE_TIME_EMAIL_SUBSCRIBER_ID = 0;

    /**
     * The new Admin instance
     *
     * @var Admin
     */
    private Admin $admin;

    /**
     * Constructor - initialize the new Admin class
     */
    public function __construct() {
        $this->admin = new Admin();
    }

    /**
     * Initialize admin hooks
     * Delegates to the new Admin class
     */
    public function init(): void {
        $this->admin->init();
    }

    /**
     * Get preserved form data for one-time email
     *
     * @return array
     */
    public function get_one_time_email_form_data(): array {
        return $this->admin->get_one_time_email_form_data();
    }

    /**
     * Register admin menu and submenus
     * Delegates to the new Admin class
     */
    public function register_menu(): void {
        $this->admin->register_menu();
    }

    /**
     * Enqueue admin assets
     * Delegates to the new Admin class
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {
        $this->admin->enqueue_assets( $hook );
    }

    /**
     * Show WP-Cron warning notice
     * Delegates to the new Admin class
     */
    public function show_cron_notice(): void {
        $this->admin->show_cron_notice();
    }

    /**
     * Show share notice
     * Delegates to the new Admin class
     */
    public function show_share_notice(): void {
        $this->admin->show_share_notice();
    }

    /**
     * Handle admin actions (add, edit, delete)
     * Delegates to the new Admin class
     */
    public function handle_actions(): void {
        $this->admin->handle_actions();
    }

    /**
     * Render Dashboard page
     * Delegates to the new Admin class
     */
    public function render_dashboard(): void {
        $this->admin->render_dashboard();
    }

    /**
     * Render Subscribers page
     * Delegates to the new Admin class
     */
    public function render_subscribers(): void {
        $this->admin->render_subscribers();
    }

    /**
     * Render Lists page
     * Delegates to the new Admin class
     */
    public function render_lists(): void {
        $this->admin->render_lists();
    }

    /**
     * Render Compose page
     * Delegates to the new Admin class
     */
    public function render_compose(): void {
        $this->admin->render_compose();
    }

    /**
     * Render Queue page
     * Delegates to the new Admin class
     */
    public function render_queue(): void {
        $this->admin->render_queue();
    }

    /**
     * Render Settings page
     * Delegates to the new Admin class
     */
    public function render_settings(): void {
        $this->admin->render_settings();
    }

    /**
     * Render One-Time Email page
     * Delegates to the new Admin class
     */
    public function render_one_time_email(): void {
        $this->admin->render_one_time_email();
    }

    /**
     * AJAX handler to dismiss share notice
     * Delegates to the new Admin class
     */
    public function ajax_dismiss_share_notice(): void {
        $this->admin->ajax_dismiss_share_notice();
    }

    /**
     * AJAX handler for SMTP test
     * Delegates to the new Admin class
     */
    public function ajax_test_smtp(): void {
        $this->admin->ajax_test_smtp();
    }

    /**
     * AJAX handler: Truncate subscribers table
     * Delegates to the new Admin class
     */
    public function ajax_truncate_subscribers(): void {
        $this->admin->ajax_truncate_subscribers();
    }

    /**
     * AJAX handler: Truncate lists table
     * Delegates to the new Admin class
     */
    public function ajax_truncate_lists(): void {
        $this->admin->ajax_truncate_lists();
    }

    /**
     * AJAX handler: Truncate queue table
     * Delegates to the new Admin class
     */
    public function ajax_truncate_queue(): void {
        $this->admin->ajax_truncate_queue();
    }
}
