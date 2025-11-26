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
    const DB_VERSION = '1.0.0';

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

        // Queue table
        $table_queue = $wpdb->prefix . 'mskd_queue';
        $sql_queue = "CREATE TABLE $table_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) UNSIGNED NOT NULL,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            status enum('pending','processing','sent','failed') DEFAULT 'pending',
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            attempts int(11) DEFAULT 0,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscriber_id (subscriber_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        dbDelta( $sql_subscribers );
        dbDelta( $sql_lists );
        dbDelta( $sql_subscriber_list );
        dbDelta( $sql_queue );
    }

    /**
     * Schedule cron events
     */
    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'mskd_process_queue' ) ) {
            wp_schedule_event( time(), 'mskd_every_minute', 'mskd_process_queue' );
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
