<?php
/**
 * Subscribers page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
$subscriber_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

// Get all lists for dropdown
$lists = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mskd_lists ORDER BY name ASC" );

// Get subscriber for editing
$subscriber = null;
$subscriber_lists = array();
if ( $action === 'edit' && $subscriber_id ) {
    $subscriber = $wpdb->get_row( $wpdb->prepare( 
        "SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE id = %d", 
        $subscriber_id 
    ) );
    $subscriber_lists = $wpdb->get_col( $wpdb->prepare(
        "SELECT list_id FROM {$wpdb->prefix}mskd_subscriber_list WHERE subscriber_id = %d",
        $subscriber_id
    ) );
}
?>

<div class="wrap mskd-wrap">
    <h1>
        <?php _e( 'Абонати', 'mail-system-by-katsarov-design' ); ?>
        <?php if ( $action === 'list' ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&action=add' ) ); ?>" class="page-title-action">
                <?php _e( 'Добави нов', 'mail-system-by-katsarov-design' ); ?>
            </a>
        <?php endif; ?>
    </h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <?php if ( $action === 'add' || $action === 'edit' ) : ?>
        <!-- Add/Edit Form -->
        <div class="mskd-form-wrap">
            <h2><?php echo $action === 'add' ? __( 'Добави абонат', 'mail-system-by-katsarov-design' ) : __( 'Редактирай абонат', 'mail-system-by-katsarov-design' ); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field( $action === 'add' ? 'mskd_add_subscriber' : 'mskd_edit_subscriber', 'mskd_nonce' ); ?>
                
                <?php if ( $action === 'edit' ) : ?>
                    <input type="hidden" name="subscriber_id" value="<?php echo esc_attr( $subscriber_id ); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="email"><?php _e( 'Имейл', 'mail-system-by-katsarov-design' ); ?> *</label>
                        </th>
                        <td>
                            <input type="email" name="email" id="email" class="regular-text" required
                                   value="<?php echo $subscriber ? esc_attr( $subscriber->email ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="first_name"><?php _e( 'Име', 'mail-system-by-katsarov-design' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="first_name" id="first_name" class="regular-text"
                                   value="<?php echo $subscriber ? esc_attr( $subscriber->first_name ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="last_name"><?php _e( 'Фамилия', 'mail-system-by-katsarov-design' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="last_name" id="last_name" class="regular-text"
                                   value="<?php echo $subscriber ? esc_attr( $subscriber->last_name ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="status"><?php _e( 'Статус', 'mail-system-by-katsarov-design' ); ?></label>
                        </th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected( $subscriber ? $subscriber->status : 'active', 'active' ); ?>>
                                    <?php _e( 'Активен', 'mail-system-by-katsarov-design' ); ?>
                                </option>
                                <option value="inactive" <?php selected( $subscriber ? $subscriber->status : '', 'inactive' ); ?>>
                                    <?php _e( 'Неактивен', 'mail-system-by-katsarov-design' ); ?>
                                </option>
                                <option value="unsubscribed" <?php selected( $subscriber ? $subscriber->status : '', 'unsubscribed' ); ?>>
                                    <?php _e( 'Отписан', 'mail-system-by-katsarov-design' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Списъци', 'mail-system-by-katsarov-design' ); ?></label>
                        </th>
                        <td>
                            <?php if ( ! empty( $lists ) ) : ?>
                                <?php foreach ( $lists as $list ) : ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="lists[]" value="<?php echo esc_attr( $list->id ); ?>"
                                               <?php checked( in_array( $list->id, $subscriber_lists ) ); ?>>
                                        <?php echo esc_html( $list->name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="description"><?php _e( 'Няма създадени списъци.', 'mail-system-by-katsarov-design' ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="<?php echo $action === 'add' ? 'mskd_add_subscriber' : 'mskd_edit_subscriber'; ?>" 
                           class="button button-primary" 
                           value="<?php echo $action === 'add' ? __( 'Добави абонат', 'mail-system-by-katsarov-design' ) : __( 'Запази промените', 'mail-system-by-katsarov-design' ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers' ) ); ?>" class="button">
                        <?php _e( 'Отказ', 'mail-system-by-katsarov-design' ); ?>
                    </a>
                </p>
            </form>
        </div>

    <?php else : ?>
        <!-- Subscribers List -->
        <?php
        // Pagination
        $per_page = 20;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;

        // Filter by status
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $where = '';
        if ( $status_filter ) {
            $where = $wpdb->prepare( " WHERE status = %s", $status_filter );
        }

        // Get total count
        $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscribers" . $where );
        $total_pages = ceil( $total_items / $per_page );

        // Get subscribers
        $subscribers = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mskd_subscribers" . $where . " ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );
        ?>

        <!-- Filters -->
        <ul class="subsubsub">
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers' ) ); ?>" 
                   class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
                    <?php _e( 'Всички', 'mail-system-by-katsarov-design' ); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&status=active' ) ); ?>"
                   class="<?php echo $status_filter === 'active' ? 'current' : ''; ?>">
                    <?php _e( 'Активни', 'mail-system-by-katsarov-design' ); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&status=inactive' ) ); ?>"
                   class="<?php echo $status_filter === 'inactive' ? 'current' : ''; ?>">
                    <?php _e( 'Неактивни', 'mail-system-by-katsarov-design' ); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&status=unsubscribed' ) ); ?>"
                   class="<?php echo $status_filter === 'unsubscribed' ? 'current' : ''; ?>">
                    <?php _e( 'Отписани', 'mail-system-by-katsarov-design' ); ?>
                </a>
            </li>
        </ul>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e( 'Имейл', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Име', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Статус', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Списъци', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Дата', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Действия', 'mail-system-by-katsarov-design' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $subscribers ) ) : ?>
                    <?php foreach ( $subscribers as $sub ) : ?>
                        <?php
                        $sub_lists = $wpdb->get_col( $wpdb->prepare(
                            "SELECT l.name FROM {$wpdb->prefix}mskd_lists l
                            INNER JOIN {$wpdb->prefix}mskd_subscriber_list sl ON l.id = sl.list_id
                            WHERE sl.subscriber_id = %d",
                            $sub->id
                        ) );
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $sub->email ); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html( trim( $sub->first_name . ' ' . $sub->last_name ) ); ?>
                            </td>
                            <td>
                                <span class="mskd-status mskd-status-<?php echo esc_attr( $sub->status ); ?>">
                                    <?php
                                    $statuses = array(
                                        'active'       => __( 'Активен', 'mail-system-by-katsarov-design' ),
                                        'inactive'     => __( 'Неактивен', 'mail-system-by-katsarov-design' ),
                                        'unsubscribed' => __( 'Отписан', 'mail-system-by-katsarov-design' ),
                                    );
                                    echo esc_html( $statuses[ $sub->status ] ?? $sub->status );
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php echo ! empty( $sub_lists ) ? esc_html( implode( ', ', $sub_lists ) ) : '—'; ?>
                            </td>
                            <td>
                                <?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $sub->created_at ) ) ); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&action=edit&id=' . $sub->id ) ); ?>">
                                    <?php _e( 'Редактирай', 'mail-system-by-katsarov-design' ); ?>
                                </a> |
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mskd-subscribers&action=delete_subscriber&id=' . $sub->id ), 'delete_subscriber_' . $sub->id ) ); ?>" 
                                   class="mskd-delete-link" style="color: #a00;">
                                    <?php _e( 'Изтрий', 'mail-system-by-katsarov-design' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6"><?php _e( 'Няма намерени абонати.', 'mail-system-by-katsarov-design' ); ?></td>
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
    <?php endif; ?>
</div>
