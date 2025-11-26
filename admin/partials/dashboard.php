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

// Get statistics
$total_subscribers = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscribers" );
$active_subscribers = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscribers WHERE status = 'active'" );
$inactive_subscribers = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscribers WHERE status = 'inactive'" );
$unsubscribed = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscribers WHERE status = 'unsubscribed'" );
$total_lists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_lists" );
$pending_emails = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_queue WHERE status = 'pending'" );
$sent_emails = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_queue WHERE status = 'sent'" );
$failed_emails = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_queue WHERE status = 'failed'" );
$one_time_emails = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_queue WHERE subscriber_id = 0" );

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
