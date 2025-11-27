<?php
/**
 * Queue Legacy page - Shows emails without campaign_id (backwards compatibility)
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

// Filter by status
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$where = ' WHERE q.campaign_id IS NULL';
if ( $status_filter ) {
    $where .= $wpdb->prepare( " AND q.status = %s", $status_filter );
}

// Get queue stats for legacy items
$queue_stats = $wpdb->get_row(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM {$wpdb->prefix}mskd_queue
    WHERE campaign_id IS NULL"
);

$total_count      = $queue_stats->total ?? 0;
$pending_count    = $queue_stats->pending ?? 0;
$processing_count = $queue_stats->processing ?? 0;
$sent_count       = $queue_stats->sent ?? 0;
$failed_count     = $queue_stats->failed ?? 0;
$cancelled_count  = $queue_stats->cancelled ?? 0;

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
?>

<div class="wrap mskd-wrap">
    <h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue' ) ); ?>" class="page-title-action" style="margin-right: 10px;">
            ← <?php _e( 'Back to Queue', 'mail-system-by-katsarov-design' ); ?>
        </a>
        <?php _e( 'Legacy Emails', 'mail-system-by-katsarov-design' ); ?>
    </h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <div class="notice notice-info inline">
        <p><?php _e( 'These emails were sent before the campaign system was introduced. They are not grouped by campaign.', 'mail-system-by-katsarov-design' ); ?></p>
    </div>

    <!-- Filters -->
    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&view=legacy' ) ); ?>" 
               class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
                <?php _e( 'All', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $total_count ); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&view=legacy&status=pending' ) ); ?>"
               class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
                <?php _e( 'Pending', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $pending_count ); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&view=legacy&status=sent' ) ); ?>"
               class="<?php echo $status_filter === 'sent' ? 'current' : ''; ?>">
                <?php _e( 'Sent', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $sent_count ); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&view=legacy&status=failed' ) ); ?>"
               class="<?php echo $status_filter === 'failed' ? 'current' : ''; ?>">
                <?php _e( 'Failed', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $failed_count ); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&view=legacy&status=cancelled' ) ); ?>"
               class="<?php echo $status_filter === 'cancelled' ? 'current' : ''; ?>">
                <?php _e( 'Cancelled', 'mail-system-by-katsarov-design' ); ?>
                <span class="count">(<?php echo esc_html( $cancelled_count ); ?>)</span>
            </a>
        </li>
    </ul>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" style="width: 50px;"><?php _e( 'ID', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col"><?php _e( 'Recipient', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col"><?php _e( 'Subject', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col" style="width: 100px;"><?php _e( 'Status', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col" style="width: 80px;"><?php _e( 'Attempts', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col"><?php _e( 'Scheduled', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col"><?php _e( 'Sent', 'mail-system-by-katsarov-design' ); ?></th>
                <th scope="col" style="width: 100px;"><?php _e( 'Actions', 'mail-system-by-katsarov-design' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $queue_items ) ) : ?>
                <?php foreach ( $queue_items as $item ) : ?>
                    <?php
                    $scheduled_timestamp = strtotime( $item->scheduled_at );
                    $is_future_scheduled = $scheduled_timestamp > current_time( 'timestamp' );
                    $can_cancel = in_array( $item->status, array( 'pending', 'processing' ), true );
                    
                    // Get recipient info
                    $external_data = null;
                    if ( $item->subscriber_id == MSKD_Admin::ONE_TIME_EMAIL_SUBSCRIBER_ID && ! empty( $item->subscriber_data ) ) {
                        $external_data = json_decode( $item->subscriber_data );
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html( $item->id ); ?></td>
                        <td>
                            <?php if ( $external_data && ! empty( $external_data->email ) ) : ?>
                                <?php echo esc_html( $external_data->email ); ?>
                                <?php if ( ! empty( $external_data->first_name ) || ! empty( $external_data->last_name ) ) : ?>
                                    <br><small><?php echo esc_html( trim( ( $external_data->first_name ?? '' ) . ' ' . ( $external_data->last_name ?? '' ) ) ); ?></small>
                                <?php endif; ?>
                                <span class="mskd-external-badge"><?php _e( 'External', 'mail-system-by-katsarov-design' ); ?></span>
                            <?php elseif ( $item->subscriber_id == MSKD_Admin::ONE_TIME_EMAIL_SUBSCRIBER_ID ) : ?>
                                <em class="mskd-one-time-email"><?php _e( 'One-time email', 'mail-system-by-katsarov-design' ); ?></em>
                            <?php elseif ( $item->email ) : ?>
                                <?php echo esc_html( $item->email ); ?>
                                <?php if ( $item->first_name || $item->last_name ) : ?>
                                    <br><small><?php echo esc_html( trim( $item->first_name . ' ' . $item->last_name ) ); ?></small>
                                <?php endif; ?>
                            <?php else : ?>
                                <em><?php _e( 'Deleted subscriber', 'mail-system-by-katsarov-design' ); ?></em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $item->subject ); ?></td>
                        <td>
                            <span class="mskd-status mskd-status-<?php echo esc_attr( $item->status ); ?>">
                                <?php
                                $statuses = array(
                                    'pending'    => __( 'Pending', 'mail-system-by-katsarov-design' ),
                                    'processing' => __( 'Processing', 'mail-system-by-katsarov-design' ),
                                    'sent'       => __( 'Sent', 'mail-system-by-katsarov-design' ),
                                    'failed'     => __( 'Failed', 'mail-system-by-katsarov-design' ),
                                    'cancelled'  => __( 'Cancelled', 'mail-system-by-katsarov-design' ),
                                );
                                echo esc_html( $statuses[ $item->status ] ?? $item->status );
                                ?>
                            </span>
                            <?php if ( $is_future_scheduled && $item->status === 'pending' ) : ?>
                                <br><small class="mskd-scheduled-badge"><?php _e( 'Scheduled', 'mail-system-by-katsarov-design' ); ?></small>
                            <?php endif; ?>
                            <?php if ( $item->status === 'failed' && $item->error_message ) : ?>
                                <br><small class="mskd-error-msg"><?php echo esc_html( $item->error_message ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $item->attempts ); ?></td>
                        <td>
                            <?php echo esc_html( date_i18n( 'd.m.Y H:i', $scheduled_timestamp ) ); ?>
                            <?php if ( $is_future_scheduled && $item->status === 'pending' ) : ?>
                                <br><small class="mskd-time-remaining">
                                    <?php 
                                    $diff = $scheduled_timestamp - current_time( 'timestamp' );
                                    if ( $diff < 3600 ) {
                                        printf( __( 'in %d min.', 'mail-system-by-katsarov-design' ), ceil( $diff / 60 ) );
                                    } elseif ( $diff < 86400 ) {
                                        printf( __( 'in %d h.', 'mail-system-by-katsarov-design' ), ceil( $diff / 3600 ) );
                                    } else {
                                        printf( __( 'in %d days', 'mail-system-by-katsarov-design' ), ceil( $diff / 86400 ) );
                                    }
                                    ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $item->sent_at ? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $item->sent_at ) ) ) : '—'; ?>
                        </td>
                        <td>
                            <?php if ( $can_cancel ) : ?>
                                <a href="<?php echo wp_nonce_url( 
                                    admin_url( 'admin.php?page=mskd-queue&action=cancel_queue_item&id=' . $item->id . '&view=legacy' ), 
                                    'cancel_queue_item_' . $item->id 
                                ); ?>" 
                                   class="mskd-delete-link mskd-cancel-link"
                                   title="<?php esc_attr_e( 'Cancel sending', 'mail-system-by-katsarov-design' ); ?>">
                                    <?php _e( 'Cancel', 'mail-system-by-katsarov-design' ); ?>
                                </a>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="8"><?php _e( 'No legacy emails.', 'mail-system-by-katsarov-design' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $base_url = admin_url( 'admin.php?page=mskd-queue&view=legacy' );
                if ( $status_filter ) {
                    $base_url .= '&status=' . $status_filter;
                }
                echo paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%', $base_url ),
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
