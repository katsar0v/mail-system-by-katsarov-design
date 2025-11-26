<?php
/**
 * Queue page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// Pagination
$per_page = 50;
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset = ( $current_page - 1 ) * $per_page;

// Filter by status or type
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$type_filter = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
$where = '';
if ( $status_filter ) {
    $where = $wpdb->prepare( " WHERE q.status = %s", $status_filter );
} elseif ( $type_filter === 'one-time' ) {
    $where = $wpdb->prepare( " WHERE q.subscriber_id = %d", MSKD_Admin::ONE_TIME_EMAIL_SUBSCRIBER_ID );
}

// Get counts in a single query for better performance
$queue_counts = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN subscriber_id = %d THEN 1 ELSE 0 END) as one_time
        FROM {$wpdb->prefix}mskd_queue",
        MSKD_Admin::ONE_TIME_EMAIL_SUBSCRIBER_ID
    )
);
$pending_count    = $queue_counts->pending ?? 0;
$processing_count = $queue_counts->processing ?? 0;
$sent_count       = $queue_counts->sent ?? 0;
$failed_count     = $queue_counts->failed ?? 0;
$one_time_count   = $queue_counts->one_time ?? 0;

// Get total count for current filter
$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_queue q" . $where );
$total_pages = ceil( $total_items / $per_page );

// Get queue items
$queue_items = $wpdb->get_results( $wpdb->prepare(
    "SELECT q.*, s.email, s.first_name, s.last_name 
    FROM {$wpdb->prefix}mskd_queue q
    LEFT JOIN {$wpdb->prefix}mskd_subscribers s ON q.subscriber_id = s.id" 
    . $where . 
    " ORDER BY q.created_at DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
) );

// Next cron run
$next_cron = wp_next_scheduled( 'mskd_process_queue' );
?>

<div class="wrap mskd-wrap">
    <h1><?php _e( 'Опашка за изпращане', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <!-- Queue Status -->
    <div class="mskd-queue-status">
        <p>
            <strong><?php _e( 'Следващо изпълнение:', 'mail-system-by-katsarov-design' ); ?></strong>
            <?php if ( $next_cron ) : ?>
                <?php echo date_i18n( 'd.m.Y H:i:s', $next_cron ); ?>
            <?php else : ?>
                <?php _e( 'Не е насрочено', 'mail-system-by-katsarov-design' ); ?>
            <?php endif; ?>
            &nbsp;|&nbsp;
            <strong><?php _e( 'Скорост:', 'mail-system-by-katsarov-design' ); ?></strong>
            <?php printf( __( '%d имейла/мин', 'mail-system-by-katsarov-design' ), MSKD_BATCH_SIZE ); ?>
        </p>
    </div>

    <!-- Filters -->
    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue' ) ); ?>" 
               class="<?php echo empty( $status_filter ) && empty( $type_filter ) ? 'current' : ''; ?>">
                <?php _e( 'Всички', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $pending_count + $processing_count + $sent_count + $failed_count ); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&status=pending' ) ); ?>"
               class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
                <?php _e( 'Чакащи', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $pending_count ); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&status=processing' ) ); ?>"
               class="<?php echo $status_filter === 'processing' ? 'current' : ''; ?>">
                <?php _e( 'В процес', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $processing_count ); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&status=sent' ) ); ?>"
               class="<?php echo $status_filter === 'sent' ? 'current' : ''; ?>">
                <?php _e( 'Изпратени', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $sent_count ); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&status=failed' ) ); ?>"
               class="<?php echo $status_filter === 'failed' ? 'current' : ''; ?>">
                <?php _e( 'Неуспешни', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $failed_count ); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&type=one-time' ) ); ?>"
               class="<?php echo $type_filter === 'one-time' ? 'current' : ''; ?>">
                <?php _e( 'Еднократни', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $one_time_count ); ?>)</span>
            </a>
        </li>
    </ul>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" style="width: 50px;"><?php _e( 'ID', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col"><?php _e( 'Получател', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col"><?php _e( 'Тема', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col" style="width: 100px;"><?php _e( 'Статус', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col" style="width: 80px;"><?php _e( 'Опити', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col"><?php _e( 'Насрочено', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col"><?php _e( 'Изпратено', 'mail-system-by-katsarov-design' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $queue_items ) ) : ?>
                <?php foreach ( $queue_items as $item ) : ?>
                    <tr>
                        <td><?php echo esc_html( $item->id ); ?></td>
                        <td>
                            <?php if ( $item->subscriber_id == MSKD_Admin::ONE_TIME_EMAIL_SUBSCRIBER_ID ) : ?>
                                <em class="mskd-one-time-email"><?php _e( 'Еднократен имейл', 'mail-system-by-katsarov-design' ); ?></em>
                            <?php elseif ( $item->email ) : ?>
                                <?php echo esc_html( $item->email ); ?>
                                <?php if ( $item->first_name || $item->last_name ) : ?>
                                    <br><small><?php echo esc_html( trim( $item->first_name . ' ' . $item->last_name ) ); ?></small>
                                <?php endif; ?>
                            <?php else : ?>
                                <em><?php _e( 'Изтрит абонат', 'mail-system-by-katsarov-design' ); ?></em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $item->subject ); ?></td>
                        <td>
                            <span class="mskd-status mskd-status-<?php echo esc_attr( $item->status ); ?>">
                                <?php
                                $statuses = array(
                                    'pending'    => __( 'Чакащ', 'mail-system-by-katsarov-design' ),
                                    'processing' => __( 'В процес', 'mail-system-by-katsarov-design' ),
                                    'sent'       => __( 'Изпратен', 'mail-system-by-katsarov-design' ),
                                    'failed'     => __( 'Неуспешен', 'mail-system-by-katsarov-design' ),
                                );
                                echo esc_html( $statuses[ $item->status ] ?? $item->status );
                                ?>
                            </span>
                            <?php if ( $item->status === 'failed' && $item->error_message ) : ?>
                                <br><small class="mskd-error-msg"><?php echo esc_html( $item->error_message ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $item->attempts ); ?></td>
                        <td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $item->scheduled_at ) ) ); ?></td>
                        <td>
                            <?php echo $item->sent_at ? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $item->sent_at ) ) ) : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7"><?php _e( 'Няма записи в опашката.', 'mail-system-by-katsarov-design' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'prev_text' => __( '&laquo;', 'mail-system-by-katsarov-design' ),
                    'next_text' => __( '&raquo;', 'mail-system-by-katsarov-design' ),
                    'total'     => $total_pages,
                    'current'   => $current_page,
                ) );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
