<?php
/**
 * Compose email page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// Get all lists
$lists = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mskd_lists ORDER BY name ASC" );
?>

<div class="wrap mskd-wrap">
    <h1><?php _e( 'Ново писмо', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <div class="mskd-form-wrap mskd-compose-form">
        <form method="post" action="">
            <?php wp_nonce_field( 'mskd_send_email', 'mskd_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Изпрати до списъци', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <?php if ( ! empty( $lists ) ) : ?>
                            <?php foreach ( $lists as $list ) : ?>
                                <?php
                                $subscriber_count = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscriber_list sl
                                    INNER JOIN {$wpdb->prefix}mskd_subscribers s ON sl.subscriber_id = s.id
                                    WHERE sl.list_id = %d AND s.status = 'active'",
                                    $list->id
                                ) );
                                ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="lists[]" value="<?php echo esc_attr( $list->id ); ?>">
                                    <?php echo esc_html( $list->name ); ?>
                                    <span class="description">(<?php printf( __( '%d активни абонати', 'mail-system-by-katsarov-design' ), $subscriber_count ); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="description">
                                <?php _e( 'Няма създадени списъци.', 'mail-system-by-katsarov-design' ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=add' ) ); ?>">
                                    <?php _e( 'Създай списък', 'mail-system-by-katsarov-design' ); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="subject"><?php _e( 'Тема', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="subject" id="subject" class="large-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="body"><?php _e( 'Съдържание', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <?php
                        wp_editor( '', 'body', array(
                            'textarea_name' => 'body',
                            'textarea_rows' => 15,
                            'media_buttons' => true,
                            'teeny'         => false,
                            'quicktags'     => true,
                        ) );
                        ?>
                        <p class="description">
                            <?php _e( 'Налични плейсхолдери:', 'mail-system-by-katsarov-design' ); ?>
                            <code>{first_name}</code>, <code>{last_name}</code>, <code>{email}</code>, <code>{unsubscribe_link}</code>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="mskd_send_email" class="button button-primary button-large" 
                       value="<?php _e( 'Добави в опашката', 'mail-system-by-katsarov-design' ); ?>">
            </p>
        </form>
    </div>
</div>
