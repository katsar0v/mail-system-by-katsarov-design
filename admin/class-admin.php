<?php
/**
 * Admin Class
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MSKD_Admin
 * 
 * Handles all admin functionality
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
     * Form data preserved on error for one-time email
     *
     * @var array
     */
    private $one_time_email_form_data = array();

    /**
     * Last wp_mail error
     *
     * @var string
     */
    private $last_mail_error = '';

    /**
     * Initialize admin hooks
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( $this, 'show_cron_notice' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
        add_action( 'wp_ajax_mskd_test_smtp', array( $this, 'ajax_test_smtp' ) );
    }

    /**
     * Register admin menu and submenus
     */
    public function register_menu() {
        // Main menu
        add_menu_page(
            __( 'Mail System', 'mail-system-by-katsarov-design' ),
            __( 'Emails', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'dashboard',
            array( $this, 'render_dashboard' ),
            'dashicons-email-alt',
            26
        );

        // Dashboard submenu (same as main)
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Dashboard', 'mail-system-by-katsarov-design' ),
            __( 'Dashboard', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'dashboard',
            array( $this, 'render_dashboard' )
        );

        // Subscribers
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Subscribers', 'mail-system-by-katsarov-design' ),
            __( 'Subscribers', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'subscribers',
            array( $this, 'render_subscribers' )
        );

        // Lists
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Lists', 'mail-system-by-katsarov-design' ),
            __( 'Lists', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'lists',
            array( $this, 'render_lists' )
        );

        // Compose
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'New email', 'mail-system-by-katsarov-design' ),
            __( 'New email', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'compose',
            array( $this, 'render_compose' )
        );

        // One-Time Email
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'One-time email', 'mail-system-by-katsarov-design' ),
            __( 'One-time email', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'one-time-email',
            array( $this, 'render_one_time_email' )
        );

        // Queue
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Queue', 'mail-system-by-katsarov-design' ),
            __( 'Queue', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'queue',
            array( $this, 'render_queue' )
        );

        // Settings
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Settings', 'mail-system-by-katsarov-design' ),
            __( 'Settings', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'settings',
            array( $this, 'render_settings' )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets( $hook ) {
        // Only load on plugin pages
        if ( strpos( $hook, self::PAGE_PREFIX ) === false ) {
            return;
        }

        wp_enqueue_style(
            'mskd-admin-style',
            MSKD_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            MSKD_VERSION
        );

        wp_enqueue_script(
            'mskd-admin-script',
            MSKD_PLUGIN_URL . 'admin/js/admin-script.js',
            array( 'jquery' ),
            MSKD_VERSION,
            true
        );

        wp_localize_script( 'mskd-admin-script', 'mskd_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mskd_admin_nonce' ),
            'strings'  => array(
                'confirm_delete' => __( 'Are you sure you want to delete?', 'mail-system-by-katsarov-design' ),
                'sending'        => __( 'Sending...', 'mail-system-by-katsarov-design' ),
                'success'        => __( 'Success!', 'mail-system-by-katsarov-design' ),
                'error'          => __( 'Error!', 'mail-system-by-katsarov-design' ),
                'timeout'        => __( 'Connection timed out. Check SMTP settings.', 'mail-system-by-katsarov-design' ),
                'datetime_past'  => __( 'Please select a future date and time.', 'mail-system-by-katsarov-design' ),
            ),
        ) );
    }

    /**
     * Show WP-Cron warning notice only on plugin pages
     */
    public function show_cron_notice() {
        $screen = get_current_screen();
        
        // Only show on plugin pages
        if ( ! $screen || strpos( $screen->id, self::PAGE_PREFIX ) === false ) {
            return;
        }

        // Check if WP-Cron is disabled
        if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
            return;
        }

        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e( 'Recommendation for Mail System:', 'mail-system-by-katsarov-design' ); ?></strong>
                <?php _e( 'For more reliable email sending, we recommend using system cron instead of WP-Cron.', 'mail-system-by-katsarov-design' ); ?>
            </p>
            <p>
                <?php _e( 'Add to wp-config.php:', 'mail-system-by-katsarov-design' ); ?>
                <code>define('DISABLE_WP_CRON', true);</code>
            </p>
            <p>
                <?php _e( 'And set up system cron:', 'mail-system-by-katsarov-design' ); ?>
                <code>* * * * * php <?php echo esc_html( ABSPATH . 'wp-cron.php' ); ?></code>
            </p>
        </div>
        <?php
    }

    /**
     * Handle admin actions (add, edit, delete)
     */
    public function handle_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle subscriber actions
        if ( isset( $_POST['mskd_add_subscriber'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_add_subscriber' ) ) {
            $this->add_subscriber();
        }

        if ( isset( $_POST['mskd_edit_subscriber'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_edit_subscriber' ) ) {
            $this->edit_subscriber();
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_subscriber' && isset( $_GET['id'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_subscriber_' . $_GET['id'] ) ) {
                $this->delete_subscriber( intval( $_GET['id'] ) );
            }
        }

        // Handle list actions
        if ( isset( $_POST['mskd_add_list'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_add_list' ) ) {
            $this->add_list();
        }

        if ( isset( $_POST['mskd_edit_list'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_edit_list' ) ) {
            $this->edit_list();
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_list' && isset( $_GET['id'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_list_' . $_GET['id'] ) ) {
                $this->delete_list( intval( $_GET['id'] ) );
            }
        }

        // Handle compose/send action
        if ( isset( $_POST['mskd_send_email'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_send_email' ) ) {
            $this->queue_email();
        }

        // Handle one-time email send action
        if ( isset( $_POST['mskd_send_one_time_email'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_send_one_time_email' ) ) {
            $this->send_one_time_email();
        }

        // Handle cancel queue item action
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'cancel_queue_item' && isset( $_GET['id'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'cancel_queue_item_' . $_GET['id'] ) ) {
                $this->cancel_queue_item( intval( $_GET['id'] ) );
            }
        }

        // Handle settings save
        if ( isset( $_POST['mskd_save_settings'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_save_settings' ) ) {
            $this->save_settings();
        }
    }

    /**
     * Add subscriber
     */
    private function add_subscriber() {
        global $wpdb;
        
        $email      = sanitize_email( $_POST['email'] );
        $first_name = sanitize_text_field( $_POST['first_name'] );
        $last_name  = sanitize_text_field( $_POST['last_name'] );
        $status     = sanitize_text_field( $_POST['status'] );
        $lists      = isset( $_POST['lists'] ) ? array_map( 'intval', $_POST['lists'] ) : array();

        // Validate status
        $allowed_statuses = array( 'active', 'inactive', 'unsubscribed' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'active';
        }

        if ( ! is_email( $email ) ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Invalid email address.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        // Check if email already exists
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mskd_subscribers WHERE email = %s",
            $email
        ) );
        if ( $existing ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'This email already exists.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        $token = wp_generate_password( 32, false );

        $result = $wpdb->insert(
            $wpdb->prefix . 'mskd_subscribers',
            array(
                'email'             => $email,
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'status'            => $status,
                'unsubscribe_token' => $token,
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $result ) {
            $subscriber_id = $wpdb->insert_id;
            
            // Add to lists
            foreach ( $lists as $list_id ) {
                $wpdb->insert(
                    $wpdb->prefix . 'mskd_subscriber_list',
                    array(
                        'subscriber_id' => $subscriber_id,
                        'list_id'       => $list_id,
                    ),
                    array( '%d', '%d' )
                );
            }

            add_settings_error( 'mskd_messages', 'mskd_success', __( 'Subscriber added successfully.', 'mail-system-by-katsarov-design' ), 'success' );
        } else {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Error adding subscriber.', 'mail-system-by-katsarov-design' ), 'error' );
        }
    }

    /**
     * Edit subscriber
     */
    private function edit_subscriber() {
        global $wpdb;
        
        $id         = intval( $_POST['subscriber_id'] );
        $email      = sanitize_email( $_POST['email'] );
        $first_name = sanitize_text_field( $_POST['first_name'] );
        $last_name  = sanitize_text_field( $_POST['last_name'] );
        $status     = sanitize_text_field( $_POST['status'] );
        $lists      = isset( $_POST['lists'] ) ? array_map( 'intval', $_POST['lists'] ) : array();

        // Validate status
        $allowed_statuses = array( 'active', 'inactive', 'unsubscribed' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'active';
        }

        if ( ! is_email( $email ) ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Invalid email address.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        // Check if email exists for another subscriber
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mskd_subscribers WHERE email = %s AND id != %d",
            $email,
            $id
        ) );
        if ( $existing ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'This email already exists.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'mskd_subscribers',
            array(
                'email'      => $email,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'status'     => $status,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        // Update list associations
        $wpdb->delete( $wpdb->prefix . 'mskd_subscriber_list', array( 'subscriber_id' => $id ), array( '%d' ) );
        foreach ( $lists as $list_id ) {
            $wpdb->insert(
                $wpdb->prefix . 'mskd_subscriber_list',
                array(
                    'subscriber_id' => $id,
                    'list_id'       => $list_id,
                ),
                array( '%d', '%d' )
            );
        }

        add_settings_error( 'mskd_messages', 'mskd_success', __( 'Subscriber updated successfully.', 'mail-system-by-katsarov-design' ), 'success' );
        
        wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'subscribers' ) );
        exit;
    }

    /**
     * Delete subscriber
     */
    private function delete_subscriber( $id ) {
        global $wpdb;
        
        // Delete from subscriber_list pivot table
        $wpdb->delete( $wpdb->prefix . 'mskd_subscriber_list', array( 'subscriber_id' => $id ), array( '%d' ) );
        
        // Delete pending queue items for this subscriber
        $wpdb->delete( $wpdb->prefix . 'mskd_queue', array( 'subscriber_id' => $id, 'status' => 'pending' ), array( '%d', '%s' ) );
        
        // Delete subscriber
        $wpdb->delete( $wpdb->prefix . 'mskd_subscribers', array( 'id' => $id ), array( '%d' ) );

        add_settings_error( 'mskd_messages', 'mskd_success', __( 'Subscriber deleted successfully.', 'mail-system-by-katsarov-design' ), 'success' );
        
        wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'subscribers' ) );
        exit;
    }

    /**
     * Add list
     */
    private function add_list() {
        global $wpdb;
        
        $name        = sanitize_text_field( $_POST['name'] );
        $description = sanitize_textarea_field( $_POST['description'] );

        $result = $wpdb->insert(
            $wpdb->prefix . 'mskd_lists',
            array(
                'name'        => $name,
                'description' => $description,
            ),
            array( '%s', '%s' )
        );

        if ( $result ) {
            add_settings_error( 'mskd_messages', 'mskd_success', __( 'List added successfully.', 'mail-system-by-katsarov-design' ), 'success' );
        } else {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Error adding list.', 'mail-system-by-katsarov-design' ), 'error' );
        }
    }

    /**
     * Edit list
     */
    private function edit_list() {
        global $wpdb;
        
        $id          = intval( $_POST['list_id'] );
        $name        = sanitize_text_field( $_POST['name'] );
        $description = sanitize_textarea_field( $_POST['description'] );

        $wpdb->update(
            $wpdb->prefix . 'mskd_lists',
            array(
                'name'        => $name,
                'description' => $description,
            ),
            array( 'id' => $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        add_settings_error( 'mskd_messages', 'mskd_success', __( 'List updated successfully.', 'mail-system-by-katsarov-design' ), 'success' );
        
        wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'lists' ) );
        exit;
    }

    /**
     * Delete list
     */
    private function delete_list( $id ) {
        global $wpdb;
        
        // Delete from subscriber_list pivot table
        $wpdb->delete( $wpdb->prefix . 'mskd_subscriber_list', array( 'list_id' => $id ), array( '%d' ) );
        
        // Delete list
        $wpdb->delete( $wpdb->prefix . 'mskd_lists', array( 'id' => $id ), array( '%d' ) );

        add_settings_error( 'mskd_messages', 'mskd_success', __( 'List deleted successfully.', 'mail-system-by-katsarov-design' ), 'success' );
        
        wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'lists' ) );
        exit;
    }

    /**
     * Cancel a queue item
     *
     * @param int $id Queue item ID
     */
    private function cancel_queue_item( $id ) {
        global $wpdb;

        // Check if item exists and is cancellable (pending or processing)
        $item = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM {$wpdb->prefix}mskd_queue WHERE id = %d",
            $id
        ) );

        if ( ! $item ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Record not found.', 'mail-system-by-katsarov-design' ), 'error' );
            wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'queue' ) );
            exit;
        }

        if ( ! in_array( $item->status, array( 'pending', 'processing' ), true ) ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'This email cannot be cancelled.', 'mail-system-by-katsarov-design' ), 'error' );
            wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'queue' ) );
            exit;
        }

        // Update status to cancelled
        $result = $wpdb->update(
            $wpdb->prefix . 'mskd_queue',
            array( 
                'status'        => 'cancelled',
                'error_message' => __( 'Cancelled by administrator', 'mail-system-by-katsarov-design' ),
            ),
            array( 'id' => $id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            add_settings_error( 'mskd_messages', 'mskd_success', __( 'Email cancelled successfully.', 'mail-system-by-katsarov-design' ), 'success' );
        } else {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Error cancelling email.', 'mail-system-by-katsarov-design' ), 'error' );
        }

        wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'queue' ) );
        exit;
    }

    /**
     * Queue email for sending
     */
    private function queue_email() {
        global $wpdb;
        
        // Load the List Provider service.
        require_once MSKD_PLUGIN_DIR . 'includes/services/class-list-provider.php';
        
        $subject    = sanitize_text_field( $_POST['subject'] );
        $body       = wp_kses_post( $_POST['body'] );
        $list_ids   = isset( $_POST['lists'] ) ? array_map( 'sanitize_text_field', $_POST['lists'] ) : array();

        if ( empty( $subject ) || empty( $body ) || empty( $list_ids ) ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Please fill in all fields.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        // Get active subscribers from selected lists (supports both database and external lists).
        $subscribers = array();
        
        foreach ( $list_ids as $list_id ) {
            $list_subscribers = MSKD_List_Provider::get_list_subscriber_ids( $list_id );
            $subscribers = array_merge( $subscribers, $list_subscribers );
        }
        
        // Remove duplicates.
        $subscribers = array_unique( $subscribers );

        if ( empty( $subscribers ) ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'No active subscribers in the selected lists.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        // Calculate scheduled time
        $scheduled_at = $this->calculate_scheduled_time( $_POST );
        $is_immediate = $this->is_immediate_send( $_POST );

        // Add to queue
        $queued = 0;
        foreach ( $subscribers as $subscriber_id ) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'mskd_queue',
                array(
                    'subscriber_id' => $subscriber_id,
                    'subject'       => $subject,
                    'body'          => $body,
                    'status'        => 'pending',
                    'scheduled_at'  => $scheduled_at,
                ),
                array( '%d', '%s', '%s', '%s', '%s' )
            );
            
            if ( $result ) {
                $queued++;
            }
        }

        if ( $is_immediate ) {
            add_settings_error( 
                'mskd_messages', 
                'mskd_success', 
                sprintf( __( '%d emails have been added to the sending queue.', 'mail-system-by-katsarov-design' ), $queued ), 
                'success' 
            );
        } else {
            // Format scheduled time for display
            $wp_timezone = wp_timezone();
            $scheduled_date = new DateTime( $scheduled_at, $wp_timezone );
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
     * Save settings
     */
    private function save_settings() {
        // Validate SMTP port.
        $smtp_port = isset( $_POST['smtp_port'] ) ? absint( $_POST['smtp_port'] ) : 587;
        if ( $smtp_port < 1 || $smtp_port > 65535 ) {
            $smtp_port = 587;
        }

        // Validate SMTP security.
        $smtp_security        = isset( $_POST['smtp_security'] ) ? sanitize_text_field( $_POST['smtp_security'] ) : '';
        $allowed_security     = array( '', 'ssl', 'tls' );
        if ( ! in_array( $smtp_security, $allowed_security, true ) ) {
            $smtp_security = 'tls';
        }

        $settings = array(
            'from_name'     => sanitize_text_field( $_POST['from_name'] ),
            'from_email'    => sanitize_email( $_POST['from_email'] ),
            'reply_to'      => sanitize_email( $_POST['reply_to'] ),
            // SMTP Settings.
            'smtp_enabled'  => isset( $_POST['smtp_enabled'] ) ? 1 : 0,
            'smtp_host'     => sanitize_text_field( $_POST['smtp_host'] ),
            'smtp_port'     => $smtp_port,
            'smtp_security' => $smtp_security,
            'smtp_auth'     => isset( $_POST['smtp_auth'] ) ? 1 : 0,
            'smtp_username' => sanitize_text_field( $_POST['smtp_username'] ),
            'smtp_password' => isset( $_POST['smtp_password'] ) ? base64_encode( sanitize_text_field( $_POST['smtp_password'] ) ) : '',
        );

        update_option( 'mskd_settings', $settings );
        add_settings_error( 'mskd_messages', 'mskd_success', __( 'Settings saved successfully.', 'mail-system-by-katsarov-design' ), 'success' );
    }

    /**
     * Capture wp_mail errors
     *
     * @param WP_Error $wp_error The WP_Error object.
     */
    public function capture_mail_error( $wp_error ) {
        if ( is_wp_error( $wp_error ) ) {
            $this->last_mail_error = $wp_error->get_error_message();
        }
    }

    /**
     * Get preserved form data for one-time email
     *
     * @return array
     */
    public function get_one_time_email_form_data() {
        return $this->one_time_email_form_data;
    }

    /**
     * Calculate scheduled time based on user input
     *
     * @param array $post_data POST data containing schedule_type, scheduled_datetime, delay_value, delay_unit
     * @return string MySQL datetime string
     */
    private function calculate_scheduled_time( $post_data ) {
        $schedule_type = isset( $post_data['schedule_type'] ) ? sanitize_text_field( $post_data['schedule_type'] ) : 'now';
        $wp_timezone   = wp_timezone();

        switch ( $schedule_type ) {
            case 'absolute':
                // User picks specific datetime (input is in WP timezone)
                $scheduled_datetime = isset( $post_data['scheduled_datetime'] ) ? sanitize_text_field( $post_data['scheduled_datetime'] ) : '';
                if ( ! empty( $scheduled_datetime ) ) {
                    try {
                        // Parse the datetime-local input (format: Y-m-d\TH:i)
                        $date = DateTime::createFromFormat( 'Y-m-d\TH:i', $scheduled_datetime, $wp_timezone );
                        if ( $date ) {
                            // Round to nearest 10 minutes
                            $minutes = (int) $date->format( 'i' );
                            $rounded_minutes = round( $minutes / 10 ) * 10;
                            if ( $rounded_minutes >= 60 ) {
                                $date->modify( '+1 hour' );
                                $rounded_minutes = 0;
                            }
                            $date->setTime( (int) $date->format( 'H' ), $rounded_minutes, 0 );
                            return $date->format( 'Y-m-d H:i:s' );
                        }
                    } catch ( Exception $e ) {
                        // Fall through to default
                    }
                }
                break;

            case 'relative':
                // +N minutes/hours/days from now
                $delay_value = isset( $post_data['delay_value'] ) ? max( 1, intval( $post_data['delay_value'] ) ) : 1;
                $delay_unit  = isset( $post_data['delay_unit'] ) ? sanitize_text_field( $post_data['delay_unit'] ) : 'hours';

                // Validate unit
                $allowed_units = array( 'minutes', 'hours', 'days' );
                if ( ! in_array( $delay_unit, $allowed_units, true ) ) {
                    $delay_unit = 'hours';
                }

                $date = new DateTime( 'now', $wp_timezone );
                $date->modify( "+{$delay_value} {$delay_unit}" );

                // Round to nearest 10 minutes for consistency
                $minutes = (int) $date->format( 'i' );
                $rounded_minutes = ceil( $minutes / 10 ) * 10;
                if ( $rounded_minutes >= 60 ) {
                    $date->modify( '+1 hour' );
                    $rounded_minutes = 0;
                }
                $date->setTime( (int) $date->format( 'H' ), $rounded_minutes, 0 );

                return $date->format( 'Y-m-d H:i:s' );

            case 'now':
            default:
                // Send immediately
                break;
        }

        return mskd_current_time_normalized();
    }

    /**
     * Check if scheduling is set to immediate send
     *
     * @param array $post_data POST data
     * @return bool
     */
    private function is_immediate_send( $post_data ) {
        $schedule_type = isset( $post_data['schedule_type'] ) ? sanitize_text_field( $post_data['schedule_type'] ) : 'now';
        return $schedule_type === 'now';
    }

    /**
     * Send one-time email directly to a recipient
     */
    private function send_one_time_email() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;

        $recipient_email = sanitize_email( $_POST['recipient_email'] );
        $recipient_name  = sanitize_text_field( $_POST['recipient_name'] );
        $subject         = sanitize_text_field( $_POST['subject'] );
        $body            = wp_kses_post( $_POST['body'] );
        $schedule_type   = isset( $_POST['schedule_type'] ) ? sanitize_text_field( $_POST['schedule_type'] ) : 'now';

        // Store form data for preservation on error
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

        // Validate required fields
        if ( empty( $recipient_email ) || empty( $subject ) || empty( $body ) ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Please fill in all required fields.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        // Validate email format
        if ( ! is_email( $recipient_email ) ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Invalid recipient email address.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        // Replace basic placeholders (no subscriber-specific placeholders for one-time emails)
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

        // Calculate scheduled time
        $scheduled_at  = $this->calculate_scheduled_time( $_POST );
        $is_immediate  = $this->is_immediate_send( $_POST );

        // Load SMTP Mailer
        require_once MSKD_PLUGIN_DIR . 'includes/services/class-smtp-mailer.php';
        $mailer = new MSKD_SMTP_Mailer();

        // Check if SMTP is enabled
        if ( ! $mailer->is_enabled() ) {
            $this->last_mail_error = __( 'SMTP is not configured. Please set up SMTP in the plugin settings.', 'mail-system-by-katsarov-design' );
            add_settings_error( 'mskd_messages', 'mskd_error', $this->last_mail_error, 'error' );
            return;
        }

        if ( $is_immediate ) {
            // Send immediately via SMTP
            $sent = $mailer->send( $recipient_email, $subject, $body );
            
            if ( ! $sent ) {
                $this->last_mail_error = $mailer->get_last_error();
            }

            // Log the one-time email in the queue table for audit purposes
            $log_status = $sent ? 'sent' : 'failed';
            $wpdb->insert(
                $wpdb->prefix . 'mskd_queue',
                array(
                    'subscriber_id' => self::ONE_TIME_EMAIL_SUBSCRIBER_ID,
                    'subject'       => $subject,
                    'body'          => $body,
                    'status'        => $log_status,
                    'scheduled_at'  => mskd_current_time_normalized(),
                    'sent_at'       => $sent ? mskd_current_time_normalized() : null,
                    'attempts'      => 1,
                    'error_message' => $sent ? null : ( $this->last_mail_error ?: __( 'wp_mail() failed for one-time email', 'mail-system-by-katsarov-design' ) ),
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
            );

            if ( $sent ) {
                // Clear form data on success
                $this->one_time_email_form_data = array();
                add_settings_error(
                    'mskd_messages',
                    'mskd_success',
                    sprintf( __( 'One-time email sent successfully to %s.', 'mail-system-by-katsarov-design' ), esc_html( $recipient_email ) ),
                    'success'
                );
            } else {
                $error_message = __( 'Error sending one-time email.', 'mail-system-by-katsarov-design' );
                if ( ! empty( $this->last_mail_error ) ) {
                    $error_message .= ' ' . sprintf( __( 'Reason: %s', 'mail-system-by-katsarov-design' ), esc_html( $this->last_mail_error ) );
                } else {
                    $error_message .= ' ' . __( 'Please try again.', 'mail-system-by-katsarov-design' );
                }
                add_settings_error(
                    'mskd_messages',
                    'mskd_error',
                    $error_message,
                    'error'
                );
            }
        } else {
            // Schedule for later - add to queue with pending status
            $result = $wpdb->insert(
                $wpdb->prefix . 'mskd_queue',
                array(
                    'subscriber_id' => self::ONE_TIME_EMAIL_SUBSCRIBER_ID,
                    'subject'       => $subject,
                    'body'          => $body,
                    'status'        => 'pending',
                    'scheduled_at'  => $scheduled_at,
                    'sent_at'       => null,
                    'attempts'      => 0,
                    'error_message' => null,
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
            );

            if ( $result ) {
                // Clear form data on success
                $this->one_time_email_form_data = array();
                
                // Format scheduled time for display
                $wp_timezone = wp_timezone();
                $scheduled_date = new DateTime( $scheduled_at, $wp_timezone );
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
     * Render Dashboard page
     */
    public function render_dashboard() {
        include MSKD_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * Render Subscribers page
     */
    public function render_subscribers() {
        include MSKD_PLUGIN_DIR . 'admin/partials/subscribers.php';
    }

    /**
     * Render Lists page
     */
    public function render_lists() {
        include MSKD_PLUGIN_DIR . 'admin/partials/lists.php';
    }

    /**
     * Render Compose page
     */
    public function render_compose() {
        include MSKD_PLUGIN_DIR . 'admin/partials/compose.php';
    }

    /**
     * Render Queue page
     */
    public function render_queue() {
        include MSKD_PLUGIN_DIR . 'admin/partials/queue.php';
    }

    /**
     * Render Settings page
     */
    public function render_settings() {
        include MSKD_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    /**
     * AJAX handler for SMTP test.
     */
    public function ajax_test_smtp() {
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

        $smtp_mailer = new MSKD_SMTP_Mailer();
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
     * Render One-Time Email page
     */
    public function render_one_time_email() {
        $form_data = $this->get_one_time_email_form_data();
        include MSKD_PLUGIN_DIR . 'admin/partials/one-time-email.php';
    }
}
