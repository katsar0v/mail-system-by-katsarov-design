<?php
/**
 * Lists page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// Load the List Provider service.
require_once MSKD_PLUGIN_DIR . 'includes/services/class-list-provider.php';

$action  = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
$list_id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';

// Get list for editing (only database lists are editable).
$list = null;
if ( $action === 'edit' && $list_id ) {
    // Check if this is an external list (not editable).
    if ( strpos( $list_id, 'ext_' ) === 0 ) {
        wp_redirect( admin_url( 'admin.php?page=mskd-lists' ) );
        exit;
    }
    $list = $wpdb->get_row( $wpdb->prepare( 
        "SELECT * FROM {$wpdb->prefix}mskd_lists WHERE id = %d", 
        intval( $list_id )
    ) );
}
?>

<div class="wrap mskd-wrap">
    <h1>
        <?php _e( 'Lists', 'mail-system-by-katsarov-design' ); ?>
        <?php if ( $action === 'list' ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=add' ) ); ?>" class="page-title-action">
                <?php _e( 'Add new', 'mail-system-by-katsarov-design' ); ?>
            </a>
        <?php endif; ?>
    </h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <?php if ( $action === 'add' || $action === 'edit' ) : ?>
        <!-- Add/Edit Form -->
        <div class="mskd-form-wrap">
            <h2><?php echo $action === 'add' ? __( 'Add list', 'mail-system-by-katsarov-design' ) : __( 'Edit list', 'mail-system-by-katsarov-design' ); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field( $action === 'add' ? 'mskd_add_list' : 'mskd_edit_list', 'mskd_nonce' ); ?>
                
                <?php if ( $action === 'edit' ) : ?>
                    <input type="hidden" name="list_id" value="<?php echo esc_attr( $list_id ); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="name"><?php _e( 'List name', 'mail-system-by-katsarov-design' ); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" required
                                   value="<?php echo $list ? esc_attr( $list->name ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="description"><?php _e( 'Description', 'mail-system-by-katsarov-design' ); ?></label>
                        </th>
                        <td>
                            <textarea name="description" id="description" class="large-text" rows="4"><?php echo $list ? esc_textarea( $list->description ) : ''; ?></textarea>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="<?php echo $action === 'add' ? 'mskd_add_list' : 'mskd_edit_list'; ?>" 
                           class="button button-primary" 
                           value="<?php echo $action === 'add' ? __( 'Add list', 'mail-system-by-katsarov-design' ) : __( 'Save changes', 'mail-system-by-katsarov-design' ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists' ) ); ?>" class="button">
                        <?php _e( 'Cancel', 'mail-system-by-katsarov-design' ); ?>
                    </a>
                </p>
            </form>
        </div>

    <?php else : ?>
        <!-- Lists Table -->
        <?php
        // Get all lists (database + external).
        $all_lists = MSKD_List_Provider::get_all_lists();
        ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e( 'Name', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Description', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Source', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Subscribers', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Date', 'mail-system-by-katsarov-design' ); ?></th>
                    <th scope="col"><?php _e( 'Actions', 'mail-system-by-katsarov-design' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $all_lists ) ) : ?>
                    <?php foreach ( $all_lists as $item ) : ?>
                        <?php
                        $subscriber_count = MSKD_List_Provider::get_list_subscriber_count( $item );
                        $is_external      = $item->source === 'external';
                        ?>
                        <tr<?php echo $is_external ? ' class="mskd-external-list"' : ''; ?>>
                            <td>
                                <strong><?php echo esc_html( $item->name ); ?></strong>
                                <?php if ( $is_external ) : ?>
                                    <span class="mskd-badge mskd-badge-external" title="<?php esc_attr_e( 'Automated list from external plugin', 'mail-system-by-katsarov-design' ); ?>">
                                        <?php _e( 'Automated', 'mail-system-by-katsarov-design' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html( wp_trim_words( $item->description, 10, '...' ) ); ?>
                            </td>
                            <td>
                                <?php if ( $is_external ) : ?>
                                    <?php echo esc_html( $item->provider ); ?>
                                <?php else : ?>
                                    <?php _e( 'Local', 'mail-system-by-katsarov-design' ); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html( $subscriber_count ); ?>
                            </td>
                            <td>
                                <?php if ( $item->created_at ) : ?>
                                    <?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $item->created_at ) ) ); ?>
                                <?php else : ?>
                                    <span class="mskd-readonly-text">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $item->is_editable ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=edit&id=' . $item->id ) ); ?>">
                                        <?php _e( 'Edit', 'mail-system-by-katsarov-design' ); ?>
                                    </a> |
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mskd-lists&action=delete_list&id=' . $item->id ), 'delete_list_' . $item->id ) ); ?>" 
                                       class="mskd-delete-link" style="color: #a00;">
                                        <?php _e( 'Delete', 'mail-system-by-katsarov-design' ); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="mskd-readonly-text" title="<?php esc_attr_e( 'Automated lists cannot be edited', 'mail-system-by-katsarov-design' ); ?>">
                                        <?php _e( 'Read-only', 'mail-system-by-katsarov-design' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6"><?php _e( 'No lists created.', 'mail-system-by-katsarov-design' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
