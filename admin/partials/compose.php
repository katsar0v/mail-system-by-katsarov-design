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

// Get minimum datetime for picker (now + 10 minutes, rounded to nearest 10 min)
$wp_timezone = wp_timezone();
$now = new DateTime( 'now', $wp_timezone );
$minutes = (int) $now->format( 'i' );
$rounded_minutes = ceil( ( $minutes + 1 ) / 10 ) * 10;
if ( $rounded_minutes >= 60 ) {
    $now->modify( '+1 hour' );
    $rounded_minutes = 0;
}
$now->setTime( (int) $now->format( 'H' ), $rounded_minutes, 0 );
$min_datetime = $now->format( 'Y-m-d\TH:i' );
?>

<div class="wrap mskd-wrap">
    <h1><?php _e( 'New email', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <div class="mskd-form-wrap mskd-compose-form">
        <form method="post" action="">
            <?php wp_nonce_field( 'mskd_send_email', 'mskd_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Send to lists', 'mail-system-by-katsarov-design' ); ?> *</label>
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
                                    <span class="description">(<?php printf( __( '%d active subscribers', 'mail-system-by-katsarov-design' ), $subscriber_count ); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="description">
                                <?php _e( 'No lists created.', 'mail-system-by-katsarov-design' ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=add' ) ); ?>">
                                    <?php _e( 'Create list', 'mail-system-by-katsarov-design' ); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="subject"><?php _e( 'Subject', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="subject" id="subject" class="large-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="body"><?php _e( 'Content', 'mail-system-by-katsarov-design' ); ?> *</label>
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
                            <?php _e( 'Available placeholders:', 'mail-system-by-katsarov-design' ); ?>
                            <code>{first_name}</code>, <code>{last_name}</code>, <code>{email}</code>, <code>{unsubscribe_link}</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="schedule_type"><?php _e( 'Scheduling', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <select name="schedule_type" id="schedule_type" class="mskd-schedule-type">
                            <option value="now"><?php _e( 'Send now', 'mail-system-by-katsarov-design' ); ?></option>
                            <option value="absolute"><?php _e( 'Specific date and time', 'mail-system-by-katsarov-design' ); ?></option>
                            <option value="relative"><?php _e( 'After a set time', 'mail-system-by-katsarov-design' ); ?></option>
                        </select>
                        
                        <div class="mskd-schedule-absolute" style="display: none; margin-top: 10px;">
                            <input type="datetime-local" 
                                   name="scheduled_datetime" 
                                   id="scheduled_datetime" 
                                   class="mskd-datetime-picker"
                                   value="<?php echo esc_attr( $min_datetime ); ?>"
                                   min="<?php echo esc_attr( $min_datetime ); ?>"
                                   step="600">
                            <p class="description">
                                <?php 
                                printf( 
                                    __( 'Timezone: %s. Select time in 10-minute intervals.', 'mail-system-by-katsarov-design' ), 
                                    '<strong>' . esc_html( wp_timezone_string() ) . '</strong>'
                                ); 
                                ?>
                            </p>
                        </div>
                        
                        <div class="mskd-schedule-relative" style="display: none; margin-top: 10px;">
                            <input type="number" 
                                   name="delay_value" 
                                   id="delay_value" 
                                   class="small-text" 
                                   value="1" 
                                   min="1" 
                                   max="999">
                            <select name="delay_unit" id="delay_unit">
                                <option value="minutes"><?php _e( 'minutes', 'mail-system-by-katsarov-design' ); ?></option>
                                <option value="hours" selected><?php _e( 'hours', 'mail-system-by-katsarov-design' ); ?></option>
                                <option value="days"><?php _e( 'days', 'mail-system-by-katsarov-design' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Emails will be sent after the specified time.', 'mail-system-by-katsarov-design' ); ?></p>
                        </div>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="mskd_send_email" class="button button-primary button-large mskd-submit-btn" 
                       value="<?php _e( 'Add to queue', 'mail-system-by-katsarov-design' ); ?>"
                       data-send-now="<?php esc_attr_e( 'Add to queue', 'mail-system-by-katsarov-design' ); ?>"
                       data-schedule="<?php esc_attr_e( 'Schedule sending', 'mail-system-by-katsarov-design' ); ?>">
            </p>
        </form>
    </div>
</div>
