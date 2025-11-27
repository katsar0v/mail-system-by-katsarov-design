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

// Load the List Provider service.
require_once MSKD_PLUGIN_DIR . 'includes/services/class-list-provider.php';

$action        = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
$subscriber_id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';

// Get all lists for dropdown (database + external).
$lists = MSKD_List_Provider::get_all_lists();

// Get subscriber for editing (only database subscribers are editable).
$subscriber       = null;
$subscriber_lists = array();
if ( $action === 'edit' && $subscriber_id ) {
    // Check if this is an external subscriber (not editable).
    if ( MSKD_List_Provider::is_external_id( $subscriber_id ) ) {
        wp_redirect( admin_url( 'admin.php?page=mskd-subscribers' ) );
        exit;
    }
    $subscriber = $wpdb->get_row( $wpdb->prepare( 
        "SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE id = %d", 
        intval( $subscriber_id )
    ) );
    $subscriber_lists = $wpdb->get_col( $wpdb->prepare(
        "SELECT list_id FROM {$wpdb->prefix}mskd_subscriber_list WHERE subscriber_id = %d",
        intval( $subscriber_id )
    ) );
}
?>

<div class="wrap mskd-wrap">
    <h1>
        <?php _e( 'Subscribers', 'mail-system-by-katsarov-design' ); ?>
        <?php if ( $action === 'list' ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&action=add' ) ); ?>" class="page-title-action">
                <?php _e( 'Add new', 'mail-system-by-katsarov-design' ); ?>
            </a>
        <?php endif; ?>
    </h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <?php if ( $action === 'add' || $action === 'edit' ) : ?>
        <!-- Add/Edit Form -->
        <div class="mskd-form-wrap">
            <h2><?php echo $action === 'add' ? __( 'Add subscriber', 'mail-system-by-katsarov-design' ) : __( 'Edit subscriber', 'mail-system-by-katsarov-design' ); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field( $action === 'add' ? 'mskd_add_subscriber' : 'mskd_edit_subscriber', 'mskd_nonce' ); ?>
                
                <?php if ( $action === 'edit' ) : ?>
                    <input type="hidden" name="subscriber_id" value="<?php echo esc_attr( $subscriber_id ); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="email"><?php _e( 'Email', 'mail-system-by-katsarov-design' ); ?> *</label>
                        </th>
                        <td>
                            <input type="email" name="email" id="email" class="regular-text" required
                                   value="<?php echo $subscriber ? esc_attr( $subscriber->email ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="first_name"><?php _e( 'First name', 'mail-system-by-katsarov-design' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="first_name" id="first_name" class="regular-text"
                                   value="<?php echo $subscriber ? esc_attr( $subscriber->first_name ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="last_name"><?php _e( 'Last name', 'mail-system-by-katsarov-design' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="last_name" id="last_name" class="regular-text"
                                   value="<?php echo $subscriber ? esc_attr( $subscriber->last_name ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="status"><?php _e( 'Status', 'mail-system-by-katsarov-design' ); ?></label>
                        </th>
                        <td>
                            <select name="status" id="status">
                                <option value="active" <?php selected( $subscriber ? $subscriber->status : 'active', 'active' ); ?>>
                                    <?php _e( 'Active', 'mail-system-by-katsarov-design' ); ?>
                                </option>
                                <option value="inactive" <?php selected( $subscriber ? $subscriber->status : '', 'inactive' ); ?>>
                                    <?php _e( 'Inactive', 'mail-system-by-katsarov-design' ); ?>
                                </option>
                                <option value="unsubscribed" <?php selected( $subscriber ? $subscriber->status : '', 'unsubscribed' ); ?>>
                                    <?php _e( 'Unsubscribed', 'mail-system-by-katsarov-design' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Lists', 'mail-system-by-katsarov-design' ); ?></label>
                        </th>
                        <td>
                            <?php 
                            // Only show database lists for subscriber assignment (external lists manage their own subscribers).
                            $database_lists = array_filter( $lists, function( $list ) {
                                return $list->source === 'database';
                            });
                            ?>
                            <?php if ( ! empty( $database_lists ) ) : ?>
                                <?php foreach ( $database_lists as $list ) : ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="lists[]" value="<?php echo esc_attr( $list->id ); ?>"
                                               <?php checked( in_array( (string) $list->id, array_map( 'strval', $subscriber_lists ), true ) ); ?>>
                                        <?php echo esc_html( $list->name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="description"><?php _e( 'No lists created.', 'mail-system-by-katsarov-design' ); ?></p>
                            <?php endif; ?>
                            <?php 
                            // Show external lists as info (not selectable).
                            $external_lists = array_filter( $lists, function( $list ) {
                                return $list->source === 'external';
                            });
                            if ( ! empty( $external_lists ) ) : ?>
                                <p class="description" style="margin-top: 10px;">
                                    <?php _e( 'Automated lists (membership managed by external plugins):', 'mail-system-by-katsarov-design' ); ?>
                                    <?php 
                                    $external_names = array_map( function( $list ) {
                                        return esc_html( $list->name ) . ' (' . esc_html( $list->provider ) . ')';
                                    }, $external_lists );
                                    echo implode( ', ', $external_names );
                                    ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="<?php echo $action === 'add' ? 'mskd_add_subscriber' : 'mskd_edit_subscriber'; ?>" 
                           class="button button-primary" 
                           value="<?php echo $action === 'add' ? __( 'Add subscriber', 'mail-system-by-katsarov-design' ) : __( 'Save changes', 'mail-system-by-katsarov-design' ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers' ) ); ?>" class="button">
                        <?php _e( 'Cancel', 'mail-system-by-katsarov-design' ); ?>
                    </a>
                </p>
            </form>
        </div>

    <?php else : ?>
        <!-- Subscribers List -->
        <?php
        // Pagination
        $per_page     = 20;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

        // Filter by status
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

        // Get subscribers using List Provider (database + external).
        $all_subscribers = MSKD_List_Provider::get_all_subscribers( array(
            'status'           => $status_filter,
            'per_page'         => $per_page,
            'page'             => $current_page,
            'include_external' => true,
        ) );

        // Get total count for pagination (database subscribers only for now, external are appended).
        $total_items = MSKD_List_Provider::get_total_subscriber_count( $status_filter );
        $total_pages = ceil( $total_items / $per_page );

        // Get external subscribers to append (they're shown on every page for visibility).
        $external_subscribers = MSKD_List_Provider::get_external_subscribers( array( 'status' => $status_filter ) );
        $has_external = ! empty( $external_subscribers );
        ?>

        <!-- Filters -->
        <ul class="subsubsub">
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers' ) ); ?>" 
                   class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
                    <?php _e( 'All', 'mail-system-by-katsarov-design' ); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&status=active' ) ); ?>"
                   class="<?php echo $status_filter === 'active' ? 'current' : ''; ?>">
                    <?php _e( 'Active', 'mail-system-by-katsarov-design' ); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&status=inactive' ) ); ?>"
                   class="<?php echo $status_filter === 'inactive' ? 'current' : ''; ?>">
                    <?php _e( 'Inactive', 'mail-system-by-katsarov-design' ); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&status=unsubscribed' ) ); ?>"
                   class="<?php echo $status_filter === 'unsubscribed' ? 'current' : ''; ?>">
                    <?php _e( 'Unsubscribed', 'mail-system-by-katsarov-design' ); ?>
                </a>
            </li>
        </ul>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e( 'Email', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Name', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Status', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Source', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Lists', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Date', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Actions', 'mail-system-by-katsarov-design' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $all_subscribers ) ) : ?>
                    <?php foreach ( $all_subscribers as $sub ) : ?>
                        <?php
                        $is_external = isset( $sub->source ) && $sub->source === 'external';
                        $is_editable = isset( $sub->is_editable ) ? $sub->is_editable : true;
                        
                        // Get lists for database subscribers.
                        $sub_lists = array();
                        if ( ! $is_external && isset( $sub->id ) ) {
                            $sub_lists = $wpdb->get_col( $wpdb->prepare(
                                "SELECT l.name FROM {$wpdb->prefix}mskd_lists l
                                INNER JOIN {$wpdb->prefix}mskd_subscriber_list sl ON l.id = sl.list_id
                                WHERE sl.subscriber_id = %d",
                                $sub->id
                            ) );
                        } elseif ( $is_external && isset( $sub->lists ) && is_array( $sub->lists ) ) {
                            // For external subscribers, show their list names.
                            foreach ( $sub->lists as $list_id ) {
                                $list = MSKD_List_Provider::get_list( $list_id );
                                if ( $list ) {
                                    $sub_lists[] = $list->name;
                                }
                            }
                        }
                        ?>
                        <tr<?php echo $is_external ? ' class="mskd-external-list"' : ''; ?>>
                            <td>
                                <strong><?php echo esc_html( $sub->email ); ?></strong>
                                <?php if ( $is_external ) : ?>
                                    <span class="mskd-badge mskd-badge-external" title="<?php esc_attr_e( 'External subscriber from plugin', 'mail-system-by-katsarov-design' ); ?>">
                                        <?php _e( 'External', 'mail-system-by-katsarov-design' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html( trim( ( $sub->first_name ?? '' ) . ' ' . ( $sub->last_name ?? '' ) ) ); ?>
                            </td>
                            <td>
                                <span class="mskd-status mskd-status-<?php echo esc_attr( $sub->status ); ?>">
                                    <?php
                                    $statuses = array(
                                        'active'       => __( 'Active', 'mail-system-by-katsarov-design' ),
                                        'inactive'     => __( 'Inactive', 'mail-system-by-katsarov-design' ),
                                        'unsubscribed' => __( 'Unsubscribed', 'mail-system-by-katsarov-design' ),
                                    );
                                    echo esc_html( $statuses[ $sub->status ] ?? $sub->status );
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ( $is_external ) : ?>
                                    <?php echo esc_html( $sub->provider ?? __( 'External', 'mail-system-by-katsarov-design' ) ); ?>
                                <?php else : ?>
                                    <?php _e( 'Local', 'mail-system-by-katsarov-design' ); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo ! empty( $sub_lists ) ? esc_html( implode( ', ', $sub_lists ) ) : '—'; ?>
                            </td>
                            <td>
                                <?php if ( isset( $sub->created_at ) && $sub->created_at ) : ?>
                                    <?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $sub->created_at ) ) ); ?>
                                <?php else : ?>
                                    <span class="mskd-readonly-text">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $is_editable ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&action=edit&id=' . $sub->id ) ); ?>">
                                        <?php _e( 'Edit', 'mail-system-by-katsarov-design' ); ?>
                                    </a> |
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mskd-subscribers&action=delete_subscriber&id=' . $sub->id ), 'delete_subscriber_' . $sub->id ) ); ?>" 
                                       class="mskd-delete-link" style="color: #a00;">
                                        <?php _e( 'Delete', 'mail-system-by-katsarov-design' ); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="mskd-readonly-text" title="<?php esc_attr_e( 'External subscribers cannot be edited', 'mail-system-by-katsarov-design' ); ?>">
                                        <?php _e( 'Read-only', 'mail-system-by-katsarov-design' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7"><?php _e( 'No subscribers found.', 'mail-system-by-katsarov-design' ); ?></td>
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
        
        <?php if ( $has_external ) : ?>
            <p class="description" style="margin-top: 15px;">
                <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                <?php _e( 'External subscribers are managed by third-party plugins and appear as read-only.', 'mail-system-by-katsarov-design' ); ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
