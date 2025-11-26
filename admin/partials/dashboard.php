<?php
/**
 * Dashboard page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// Get subscriber statistics in a single query
$subscriber_stats = $wpdb->get_row(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed
    FROM {$wpdb->prefix}mskd_subscribers"
);
$total_subscribers    = $subscriber_stats->total ?? 0;
$active_subscribers   = $subscriber_stats->active ?? 0;
$inactive_subscribers = $subscriber_stats->inactive ?? 0;
$unsubscribed         = $subscriber_stats->unsubscribed ?? 0;

// Get list count
$total_lists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_lists" );

// Get queue statistics in a single query
$queue_stats = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN subscriber_id = %d THEN 1 ELSE 0 END) as one_time
        FROM {$wpdb->prefix}mskd_queue",
        MSKD_Admin::ONE_TIME_EMAIL_SUBSCRIBER_ID
    )
);
$pending_emails  = $queue_stats->pending ?? 0;
$sent_emails     = $queue_stats->sent ?? 0;
$failed_emails   = $queue_stats->failed ?? 0;
$one_time_emails = $queue_stats->one_time ?? 0;

// Get next cron run
$next_cron = wp_next_scheduled( 'mskd_process_queue' );
?>

<div class="wrap mskd-wrap">
    <h1><?php _e( 'Табло - Мейл Система', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <div class="mskd-dashboard">
        <!-- Quick Actions -->
        <div class="mskd-quick-actions">
            <h2><?php _e( 'Бързи действия', 'mail-system-by-katsarov-design' ); ?></h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&action=add' ) ); ?>" class="button button-primary">
                <?php _e( 'Добави абонат', 'mail-system-by-katsarov-design' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=add' ) ); ?>" class="button button-primary">
                <?php _e( 'Добави списък', 'mail-system-by-katsarov-design' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose' ) ); ?>" class="button button-primary">
                <?php _e( 'Ново писмо', 'mail-system-by-katsarov-design' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-one-time-email' ) ); ?>" class="button button-secondary">
                <?php _e( 'Еднократен имейл', 'mail-system-by-katsarov-design' ); ?>
            </a>
        </div>

        <div class="mskd-stats-grid">
            <!-- Subscribers Stats -->
            <div class="mskd-stat-box">
                <h3><?php _e( 'Абонати', 'mail-system-by-katsarov-design' ); ?></h3>
                <div class="mskd-stat-number"><?php echo esc_html( $total_subscribers ); ?></div>
                <div class="mskd-stat-details">
                    <span class="mskd-status-active"><?php printf( __( 'Активни: %d', 'mail-system-by-katsarov-design' ), $active_subscribers ); ?></span>
                    <span class="mskd-status-inactive"><?php printf( __( 'Неактивни: %d', 'mail-system-by-katsarov-design' ), $inactive_subscribers ); ?></span>
                    <span class="mskd-status-unsubscribed"><?php printf( __( 'Отписани: %d', 'mail-system-by-katsarov-design' ), $unsubscribed ); ?></span>
                </div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers' ) ); ?>" class="button">
                    <?php _e( 'Виж всички', 'mail-system-by-katsarov-design' ); ?>
                </a>
            </div>

            <!-- Lists Stats -->
            <div class="mskd-stat-box">
                <h3><?php _e( 'Списъци', 'mail-system-by-katsarov-design' ); ?></h3>
                <div class="mskd-stat-number"><?php echo esc_html( $total_lists ); ?></div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists' ) ); ?>" class="button">
                    <?php _e( 'Управление', 'mail-system-by-katsarov-design' ); ?>
                </a>
            </div>

            <!-- Queue Stats -->
            <div class="mskd-stat-box">
                <h3><?php _e( 'Опашка', 'mail-system-by-katsarov-design' ); ?></h3>
                <div class="mskd-stat-number"><?php echo esc_html( $pending_emails ); ?></div>
                <div class="mskd-stat-details">
                    <span class="mskd-status-pending"><?php printf( __( 'Чакащи: %d', 'mail-system-by-katsarov-design' ), $pending_emails ); ?></span>
                    <span class="mskd-status-sent"><?php printf( __( 'Изпратени: %d', 'mail-system-by-katsarov-design' ), $sent_emails ); ?></span>
                    <span class="mskd-status-failed"><?php printf( __( 'Неуспешни: %d', 'mail-system-by-katsarov-design' ), $failed_emails ); ?></span>
                    <span class="mskd-status-one-time"><?php printf( __( 'Еднократни: %d', 'mail-system-by-katsarov-design' ), $one_time_emails ); ?></span>
                </div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue' ) ); ?>" class="button">
                    <?php _e( 'Виж опашка', 'mail-system-by-katsarov-design' ); ?>
                </a>
            </div>

            <!-- Cron Status -->
            <div class="mskd-stat-box">
                <h3><?php _e( 'Cron статус', 'mail-system-by-katsarov-design' ); ?></h3>
                <?php if ( $next_cron ) : ?>
                    <div class="mskd-stat-info">
                        <span class="mskd-cron-active"><?php _e( 'Активен', 'mail-system-by-katsarov-design' ); ?></span>
                        <p><?php printf( __( 'Следващо изпълнение: %s', 'mail-system-by-katsarov-design' ), date_i18n( 'd.m.Y H:i:s', $next_cron ) ); ?></p>
                    </div>
                <?php else : ?>
                    <div class="mskd-stat-info">
                        <span class="mskd-cron-inactive"><?php _e( 'Неактивен', 'mail-system-by-katsarov-design' ); ?></span>
                    </div>
                <?php endif; ?>
                <p class="description">
                    <?php printf( __( 'Изпращане: %d имейла/мин', 'mail-system-by-katsarov-design' ), MSKD_BATCH_SIZE ); ?>
                </p>
            </div>
        </div>
    </div>
</div>
