<?php
/**
 * Admin Class (Refactored)
 *
 * Thin orchestrator that delegates to specialized controllers.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Admin
 *
 * Main admin class that coordinates all admin functionality.
 * Delegates to specialized controllers for each feature area.
 */
class Admin {

    /**
     * Plugin pages prefix.
     */
    const PAGE_PREFIX = 'mskd-';

    /**
     * Subscribers controller.
     *
     * @var Admin_Subscribers
     */
    private $subscribers;

    /**
     * Lists controller.
     *
     * @var Admin_Lists
     */
    private $lists;

    /**
     * Email controller.
     *
     * @var Admin_Email
     */
    private $email;

    /**
     * Queue controller.
     *
     * @var Admin_Queue
     */
    private $queue;

    /**
     * Settings controller.
     *
     * @var Admin_Settings
     */
    private $settings;

    /**
     * AJAX handler.
     *
     * @var Admin_Ajax
     */
    private $ajax;

    /**
     * Import/Export controller.
     *
     * @var Admin_Import_Export
     */
    private $import_export;

    /**
     * Templates controller.
     *
     * @var Admin_Templates
     */
    private $templates;

    /**
     * Constructor - initialize controllers.
     */
    public function __construct() {
        $this->subscribers   = new Admin_Subscribers();
        $this->lists         = new Admin_Lists();
        $this->email         = new Admin_Email();
        $this->queue         = new Admin_Queue();
        $this->settings      = new Admin_Settings();
        $this->ajax          = new Admin_Ajax();
        $this->import_export = new Admin_Import_Export();
        $this->templates     = new Admin_Templates();
    }

    /**
     * Initialize admin hooks and controllers.
     *
     * @return void
     */
    public function init(): void {
        // Register hooks.
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( $this, 'show_cron_notice' ) );
        add_action( 'admin_notices', array( $this, 'show_share_notice' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );

        // Initialize AJAX handlers.
        $this->ajax->init();
    }

    /**
     * Register admin menu and submenus.
     *
     * @return void
     */
    public function register_menu(): void {
        // Main menu.
        add_menu_page(
            __( 'Mail System', 'mail-system-by-katsarov-design' ),
            __( 'Emails', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'dashboard',
            array( $this, 'render_dashboard' ),
            'dashicons-email-alt',
            26
        );

        // Dashboard submenu (same as main).
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Dashboard', 'mail-system-by-katsarov-design' ),
            __( 'Dashboard', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'dashboard',
            array( $this, 'render_dashboard' )
        );

        // Subscribers.
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Subscribers', 'mail-system-by-katsarov-design' ),
            __( 'Subscribers', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'subscribers',
            array( $this->subscribers, 'render' )
        );

        // Lists.
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Lists', 'mail-system-by-katsarov-design' ),
            __( 'Lists', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'lists',
            array( $this->lists, 'render' )
        );

        // Templates.
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Templates', 'mail-system-by-katsarov-design' ),
            __( 'Templates', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'templates',
            array( $this->templates, 'render' )
        );

        // Compose.
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'New email', 'mail-system-by-katsarov-design' ),
            __( 'New email', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'compose',
            array( $this->email, 'render_compose' )
        );

        // One-Time Email.
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'One-time email', 'mail-system-by-katsarov-design' ),
            __( 'One-time email', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'one-time-email',
            array( $this->email, 'render_one_time' )
        );

        // Queue.
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Queue', 'mail-system-by-katsarov-design' ),
            __( 'Queue', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'queue',
            array( $this->queue, 'render' )
        );

        // Settings.
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Settings', 'mail-system-by-katsarov-design' ),
            __( 'Settings', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'settings',
            array( $this->settings, 'render' )
        );

        // Import/Export.
        add_submenu_page(
            self::PAGE_PREFIX . 'dashboard',
            __( 'Import / Export', 'mail-system-by-katsarov-design' ),
            __( 'Import / Export', 'mail-system-by-katsarov-design' ),
            'manage_options',
            self::PAGE_PREFIX . 'import-export',
            array( $this->import_export, 'render' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets( string $hook ): void {
        // Only load on plugin pages.
        if ( strpos( $hook, self::PAGE_PREFIX ) === false ) {
            return;
        }

        // SlimSelect CSS.
        wp_enqueue_style(
            'slimselect',
            'https://cdn.jsdelivr.net/npm/slim-select@2.9.2/dist/slimselect.min.css',
            array(),
            '2.9.2'
        );

        wp_enqueue_style(
            'mskd-admin-style',
            MSKD_PLUGIN_URL . 'admin/css/admin-style.css',
            array( 'slimselect' ),
            MSKD_VERSION
        );

        // SlimSelect JS.
        wp_enqueue_script(
            'slimselect',
            'https://cdn.jsdelivr.net/npm/slim-select@2.9.2/dist/slimselect.min.js',
            array(),
            '2.9.2',
            false
        );

        wp_enqueue_script(
            'mskd-admin-script',
            MSKD_PLUGIN_URL . 'admin/js/admin-script.js',
            array( 'jquery', 'slimselect' ),
            MSKD_VERSION,
            true
        );

        wp_localize_script( 'mskd-admin-script', 'mskd_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mskd_admin_nonce' ),
            'strings'  => array(
                'confirm_delete'               => __( 'Are you sure you want to delete?', 'mail-system-by-katsarov-design' ),
                'sending'                      => __( 'Sending...', 'mail-system-by-katsarov-design' ),
                'success'                      => __( 'Success!', 'mail-system-by-katsarov-design' ),
                'error'                        => __( 'Error!', 'mail-system-by-katsarov-design' ),
                'timeout'                      => __( 'Connection timed out. Check SMTP settings.', 'mail-system-by-katsarov-design' ),
                'datetime_past'                => __( 'Please select a future date and time.', 'mail-system-by-katsarov-design' ),
                'processing'                   => __( 'Processing...', 'mail-system-by-katsarov-design' ),
                'confirm_truncate_subscribers' => __( 'Are you sure you want to delete ALL subscribers? This action cannot be undone!', 'mail-system-by-katsarov-design' ),
                'confirm_truncate_lists'       => __( 'Are you sure you want to delete ALL lists? This action cannot be undone!', 'mail-system-by-katsarov-design' ),
                'confirm_truncate_queue'       => __( 'Are you sure you want to delete ALL campaigns? This action cannot be undone!', 'mail-system-by-katsarov-design' ),
                'select_lists_placeholder'     => __( 'Select lists...', 'mail-system-by-katsarov-design' ),
                'search_placeholder'           => __( 'Search...', 'mail-system-by-katsarov-design' ),
                'no_results'                   => __( 'No results found', 'mail-system-by-katsarov-design' ),
            ),
        ) );
    }

    /**
     * Show WP-Cron warning notice only on plugin pages.
     *
     * @return void
     */
    public function show_cron_notice(): void {
        $screen = get_current_screen();

        // Only show on plugin pages.
        if ( ! $screen || strpos( $screen->id, self::PAGE_PREFIX ) === false ) {
            return;
        }

        // Check if WP-Cron is disabled.
        if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
            return;
        }

        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Recommendation for Mail System:', 'mail-system-by-katsarov-design' ); ?></strong>
                <?php esc_html_e( 'For more reliable email sending, we recommend using system cron instead of WP-Cron.', 'mail-system-by-katsarov-design' ); ?>
            </p>
            <p>
                <?php esc_html_e( 'Add to wp-config.php:', 'mail-system-by-katsarov-design' ); ?>
                <code>define('DISABLE_WP_CRON', true);</code>
            </p>
            <p>
                <?php esc_html_e( 'And set up system cron:', 'mail-system-by-katsarov-design' ); ?>
                <code>* * * * * php <?php echo esc_html( ABSPATH . 'wp-cron.php' ); ?></code>
            </p>
        </div>
        <?php
    }

    /**
     * Show share notice asking users to share the plugin.
     *
     * @return void
     */
    public function show_share_notice(): void {
        // Check if notice was already dismissed.
        if ( get_option( 'mskd_share_notice_dismissed' ) ) {
            return;
        }

        // Only show to users who can manage options.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = get_current_screen();

        // Only show on plugin pages.
        if ( ! $screen || strpos( $screen->id, self::PAGE_PREFIX ) === false ) {
            return;
        }

        // Only show after at least 3 campaigns have been created.
        $campaign_count = (int) get_option( 'mskd_total_campaigns_created', 0 );
        if ( $campaign_count < 3 ) {
            return;
        }

        ?>
        <div class="notice notice-info mskd-share-notice" style="padding: 15px;">
            <p style="font-size: 14px; margin-bottom: 10px;">
                <strong><?php esc_html_e( 'Enjoying Mail System by Katsarov Design?', 'mail-system-by-katsarov-design' ); ?></strong>
                <?php esc_html_e( 'If you like this plugin, please share it with your friends!', 'mail-system-by-katsarov-design' ); ?>
            </p>
            <p>
                <a href="#" class="button button-primary mskd-share-dismiss" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mskd_dismiss_share_notice' ) ); ?>">
                    <?php esc_html_e( 'Yes, of course!', 'mail-system-by-katsarov-design' ); ?>
                </a>
                <a href="https://github.com/katsar0v/mail-system-by-katsarov-design" target="_blank" class="button" style="margin-left: 10px;">
                    <?php esc_html_e( 'Nah, I do not like the plugin', 'mail-system-by-katsarov-design' ); ?>
                </a>
            </p>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.mskd-share-dismiss').on('click', function(e) {
                    e.preventDefault();
                    var $notice = $(this).closest('.mskd-share-notice');
                    var nonce = $(this).data('nonce');

                    $.post(ajaxurl, {
                        action: 'mskd_dismiss_share_notice',
                        nonce: nonce
                    }, function() {
                        $notice.fadeOut();
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Handle admin actions by delegating to controllers.
     *
     * @return void
     */
    public function handle_actions(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Delegate to appropriate controller.
        $this->subscribers->handle_actions();
        $this->lists->handle_actions();
        $this->email->handle_actions();
        $this->queue->handle_actions();
        $this->settings->handle_actions();
        $this->import_export->handle_actions();
        $this->templates->handle_actions();
    }

    /**
     * Render Dashboard page.
     *
     * @return void
     */
    public function render_dashboard(): void {
        include MSKD_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * Get the subscribers controller.
     *
     * @return Admin_Subscribers
     */
    public function get_subscribers_controller(): Admin_Subscribers {
        return $this->subscribers;
    }

    /**
     * Get the lists controller.
     *
     * @return Admin_Lists
     */
    public function get_lists_controller(): Admin_Lists {
        return $this->lists;
    }

    /**
     * Get the email controller.
     *
     * @return Admin_Email
     */
    public function get_email_controller(): Admin_Email {
        return $this->email;
    }

    /**
     * Get the queue controller.
     *
     * @return Admin_Queue
     */
    public function get_queue_controller(): Admin_Queue {
        return $this->queue;
    }

    /**
     * Get the settings controller.
     *
     * @return Admin_Settings
     */
    public function get_settings_controller(): Admin_Settings {
        return $this->settings;
    }

    /**
     * Get the AJAX handler.
     *
     * @return Admin_Ajax
     */
    public function get_ajax_handler(): Admin_Ajax {
        return $this->ajax;
    }

    /**
     * Get the import/export controller.
     *
     * @return Admin_Import_Export
     */
    public function get_import_export_controller(): Admin_Import_Export {
        return $this->import_export;
    }

    /**
     * Get the templates controller.
     *
     * @return Admin_Templates
     */
    public function get_templates_controller(): Admin_Templates {
        return $this->templates;
    }
}
