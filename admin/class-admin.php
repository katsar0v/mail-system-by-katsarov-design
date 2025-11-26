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
     * Initialize admin hooks
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( $this, 'show_cron_notice' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    /**
     * Register admin menu and submenus
     */
    public function register_menu() {
        // Main menu
        add_menu_page(
            __( 'Мейл Система', 'mail-system-by-katsarov-design' ),
            __( 'Имейли', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'dashboard',
            array( $this, 'render_dashboard' ),
            'dashicons-email-alt',
            26
        );

        // Dashboard submenu (same as main)
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Табло', 'mail-system-by-katsarov-design' ),
            __( 'Табло', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'dashboard',
            array( $this, 'render_dashboard' )
        );

        // Subscribers
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Абонати', 'mail-system-by-katsarov-design' ),
            __( 'Абонати', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'subscribers',
            array( $this, 'render_subscribers' )
        );

        // Lists
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Списъци', 'mail-system-by-katsarov-design' ),
            __( 'Списъци', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'lists',
            array( $this, 'render_lists' )
        );

        // Compose
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Ново писмо', 'mail-system-by-katsarov-design' ),
            __( 'Ново писмо', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'compose',
            array( $this, 'render_compose' )
        );

        // Queue
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Опашка', 'mail-system-by-katsarov-design' ),
            __( 'Опашка', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'queue',
            array( $this, 'render_queue' )
        );

        // Settings
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Настройки', 'mail-system-by-katsarov-design' ),
            __( 'Настройки', 'mail-system-by-katsarov-design' ),
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
                'confirm_delete' => __( 'Сигурни ли сте, че искате да изтриете?', 'mail-system-by-katsarov-design' ),
                'sending'        => __( 'Изпращане...', 'mail-system-by-katsarov-design' ),
                'success'        => __( 'Успешно!', 'mail-system-by-katsarov-design' ),
                'error'          => __( 'Грешка!', 'mail-system-by-katsarov-design' ),
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
                <strong><?php _e( 'Препоръка за Мейл Система:', 'mail-system-by-katsarov-design' ); ?></strong>
                <?php _e( 'За по-надеждно изпращане на имейли препоръчваме да използвате системен cron вместо WP-Cron.', 'mail-system-by-katsarov-design' ); ?>
            </p>
            <p>
                <?php _e( 'Добавете в wp-config.php:', 'mail-system-by-katsarov-design' ); ?>
                <code>define('DISABLE_WP_CRON', true);</code>
            </p>
            <p>
                <?php _e( 'И настройте системен cron:', 'mail-system-by-katsarov-design' ); ?>
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
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Невалиден имейл адрес.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        // Check if email already exists
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mskd_subscribers WHERE email = %s",
            $email
        ) );
        if ( $existing ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Този имейл вече съществува.', 'mail-system-by-katsarov-design' ), 'error' );
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

            add_settings_error( 'mskd_messages', 'mskd_success', __( 'Абонатът е добавен успешно.', 'mail-system-by-katsarov-design' ), 'success' );
        } else {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Грешка при добавяне на абонат.', 'mail-system-by-katsarov-design' ), 'error' );
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
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Невалиден имейл адрес.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        // Check if email exists for another subscriber
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}mskd_subscribers WHERE email = %s AND id != %d",
            $email,
            $id
        ) );
        if ( $existing ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Този имейл вече съществува.', 'mail-system-by-katsarov-design' ), 'error' );
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

        add_settings_error( 'mskd_messages', 'mskd_success', __( 'Абонатът е обновен успешно.', 'mail-system-by-katsarov-design' ), 'success' );
        
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

        add_settings_error( 'mskd_messages', 'mskd_success', __( 'Абонатът е изтрит успешно.', 'mail-system-by-katsarov-design' ), 'success' );
        
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
            add_settings_error( 'mskd_messages', 'mskd_success', __( 'Списъкът е добавен успешно.', 'mail-system-by-katsarov-design' ), 'success' );
        } else {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Грешка при добавяне на списък.', 'mail-system-by-katsarov-design' ), 'error' );
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

        add_settings_error( 'mskd_messages', 'mskd_success', __( 'Списъкът е обновен успешно.', 'mail-system-by-katsarov-design' ), 'success' );
        
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

        add_settings_error( 'mskd_messages', 'mskd_success', __( 'Списъкът е изтрит успешно.', 'mail-system-by-katsarov-design' ), 'success' );
        
        wp_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'lists' ) );
        exit;
    }

    /**
     * Queue email for sending
     */
    private function queue_email() {
        global $wpdb;
        
        $subject = sanitize_text_field( $_POST['subject'] );
        $body    = wp_kses_post( $_POST['body'] );
        $lists   = isset( $_POST['lists'] ) ? array_map( 'intval', $_POST['lists'] ) : array();

        if ( empty( $subject ) || empty( $body ) || empty( $lists ) ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Моля, попълнете всички полета.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

        // Get active subscribers from selected lists
        $placeholders = implode( ',', array_fill( 0, count( $lists ), '%d' ) );
        $query = $wpdb->prepare(
            "SELECT DISTINCT s.id FROM {$wpdb->prefix}mskd_subscribers s
            INNER JOIN {$wpdb->prefix}mskd_subscriber_list sl ON s.id = sl.subscriber_id
            WHERE sl.list_id IN ($placeholders) AND s.status = 'active'",
            $lists
        );
        
        $subscribers = $wpdb->get_col( $query );

        if ( empty( $subscribers ) ) {
            add_settings_error( 'mskd_messages', 'mskd_error', __( 'Няма активни абонати в избраните списъци.', 'mail-system-by-katsarov-design' ), 'error' );
            return;
        }

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
                ),
                array( '%d', '%s', '%s', '%s' )
            );
            
            if ( $result ) {
                $queued++;
            }
        }

        add_settings_error( 
            'mskd_messages', 
            'mskd_success', 
            sprintf( __( 'Добавени са %d писма в опашката за изпращане.', 'mail-system-by-katsarov-design' ), $queued ), 
            'success' 
        );
    }

    /**
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'from_name'  => sanitize_text_field( $_POST['from_name'] ),
            'from_email' => sanitize_email( $_POST['from_email'] ),
            'reply_to'   => sanitize_email( $_POST['reply_to'] ),
        );

        update_option( 'mskd_settings', $settings );
        add_settings_error( 'mskd_messages', 'mskd_success', __( 'Настройките са запазени успешно.', 'mail-system-by-katsarov-design' ), 'success' );
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
}
