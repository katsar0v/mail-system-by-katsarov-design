<?php
/**
 * Plugin Activator
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MSKD_Activator
 * 
 * Handles plugin activation tasks including database table creation
 */
class MSKD_Activator {

    /**
     * Database version for tracking schema updates
     */
    const DB_VERSION = '1.2.0';

    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::schedule_cron();
        self::set_default_options();
        
        // Store database version
        update_option( 'mskd_db_version', self::DB_VERSION );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Check and perform database upgrades if needed
     */
    public static function maybe_upgrade() {
        $installed_version = get_option( 'mskd_db_version', '1.0.0' );

        if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
            self::upgrade( $installed_version );
            update_option( 'mskd_db_version', self::DB_VERSION );
        }
    }

    /**
     * Perform database upgrades based on current version
     *
     * @param string $from_version The version being upgraded from.
     */
    private static function upgrade( $from_version ) {
        global $wpdb;

        // Upgrade from 1.0.0 to 1.1.0: Add subscriber_data column to queue table.
        if ( version_compare( $from_version, '1.1.0', '<' ) ) {
            $table_queue = $wpdb->prefix . 'mskd_queue';

            // Check if column exists.
            $column_exists = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table_queue} LIKE %s",
                    'subscriber_data'
                )
            );

            if ( empty( $column_exists ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query(
                    "ALTER TABLE {$table_queue} ADD COLUMN subscriber_data text DEFAULT NULL AFTER subscriber_id"
                );
            }
        }

        // Upgrade from 1.1.0 to 1.2.0: Add campaigns table and campaign_id to queue.
        if ( version_compare( $from_version, '1.2.0', '<' ) ) {
            self::create_campaigns_table();

            $table_queue = $wpdb->prefix . 'mskd_queue';

            // Add campaign_id column to queue table.
            $column_exists = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table_queue} LIKE %s",
                    'campaign_id'
                )
            );

            if ( empty( $column_exists ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query(
                    "ALTER TABLE {$table_queue} ADD COLUMN campaign_id bigint(20) UNSIGNED DEFAULT NULL AFTER id"
                );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                $wpdb->query(
                    "ALTER TABLE {$table_queue} ADD KEY campaign_id (campaign_id)"
                );
            }
        }
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Subscribers table
        $table_subscribers = $wpdb->prefix . 'mskd_subscribers';
        $sql_subscribers = "CREATE TABLE $table_subscribers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            first_name varchar(100) DEFAULT '',
            last_name varchar(100) DEFAULT '',
            status enum('active','inactive','unsubscribed') DEFAULT 'active',
            unsubscribe_token varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY unsubscribe_token (unsubscribe_token)
        ) $charset_collate;";

        // Lists table
        $table_lists = $wpdb->prefix . 'mskd_lists';
        $sql_lists = "CREATE TABLE $table_lists (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Subscriber-List pivot table
        $table_subscriber_list = $wpdb->prefix . 'mskd_subscriber_list';
        $sql_subscriber_list = "CREATE TABLE $table_subscriber_list (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) UNSIGNED NOT NULL,
            list_id bigint(20) UNSIGNED NOT NULL,
            subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY subscriber_list (subscriber_id, list_id),
            KEY subscriber_id (subscriber_id),
            KEY list_id (list_id)
        ) $charset_collate;";

        // Campaigns table (groups emails by send operation)
        $table_campaigns = $wpdb->prefix . 'mskd_campaigns';
        $sql_campaigns = "CREATE TABLE $table_campaigns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            list_ids text DEFAULT NULL,
            type enum('campaign','one_time') DEFAULT 'campaign',
            total_recipients int(11) DEFAULT 0,
            status enum('pending','processing','completed','cancelled') DEFAULT 'pending',
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY type (type)
        ) $charset_collate;";

        // Queue table
        $table_queue = $wpdb->prefix . 'mskd_queue';
        $sql_queue = "CREATE TABLE $table_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED DEFAULT NULL,
            subscriber_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            subscriber_data text DEFAULT NULL,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            status enum('pending','processing','sent','failed') DEFAULT 'pending',
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            attempts int(11) DEFAULT 0,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        dbDelta( $sql_subscribers );
        dbDelta( $sql_lists );
        dbDelta( $sql_subscriber_list );
        dbDelta( $sql_campaigns );
        dbDelta( $sql_queue );
    }

    /**
     * Create campaigns table (used for upgrades)
     */
    private static function create_campaigns_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $table_campaigns = $wpdb->prefix . 'mskd_campaigns';
        $sql_campaigns = "CREATE TABLE $table_campaigns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            list_ids text DEFAULT NULL,
            type enum('campaign','one_time') DEFAULT 'campaign',
            total_recipients int(11) DEFAULT 0,
            status enum('pending','processing','completed','cancelled') DEFAULT 'pending',
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY type (type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_campaigns );
    }

    /**
     * Schedule cron events
     */
    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'mskd_process_queue' ) ) {
            // Schedule at the start of the next minute (00 seconds)
            $next_minute = mskd_normalize_timestamp( time() + 60 );
            wp_schedule_event( $next_minute, 'mskd_every_minute', 'mskd_process_queue' );
        }
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = array(
            'from_name'    => get_bloginfo( 'name' ),
            'from_email'   => get_bloginfo( 'admin_email' ),
            'reply_to'     => get_bloginfo( 'admin_email' ),
        );

        if ( ! get_option( 'mskd_settings' ) ) {
            update_option( 'mskd_settings', $defaults );
        }
    }
}
