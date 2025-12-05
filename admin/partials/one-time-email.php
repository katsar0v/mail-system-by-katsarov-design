<?php
/**
 * One-Time Email page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get preserved form data (if any error occurred)
$form_data = isset( $form_data ) ? $form_data : array();
$recipient_email = isset( $form_data['recipient_email'] ) ? esc_attr( $form_data['recipient_email'] ) : '';
$recipient_name  = isset( $form_data['recipient_name'] ) ? esc_attr( $form_data['recipient_name'] ) : '';
$subject_value   = isset( $form_data['subject'] ) ? esc_attr( $form_data['subject'] ) : '';
$body_value      = isset( $form_data['body'] ) ? $form_data['body'] : '';
$schedule_type   = isset( $form_data['schedule_type'] ) ? esc_attr( $form_data['schedule_type'] ) : 'now';
$scheduled_datetime = isset( $form_data['scheduled_datetime'] ) ? esc_attr( $form_data['scheduled_datetime'] ) : '';
$delay_value     = isset( $form_data['delay_value'] ) ? intval( $form_data['delay_value'] ) : 1;
$delay_unit      = isset( $form_data['delay_unit'] ) ? esc_attr( $form_data['delay_unit'] ) : 'hours';

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
$default_datetime = $scheduled_datetime ? $scheduled_datetime : $min_datetime;
?>

<div class="wrap mskd-wrap">
    <h1><?php _e( 'One-time email', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <p class="description">
        <?php _e( 'Send a one-time email directly to a specific recipient. The email can be sent immediately or scheduled for later.', 'mail-system-by-katsarov-design' ); ?>
    </p>

    <div class="mskd-form-wrap mskd-one-time-email-form">
        <form method="post" action="">
            <?php wp_nonce_field( 'mskd_send_one_time_email', 'mskd_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="recipient_email"><?php _e( 'Recipient email', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <input type="email" name="recipient_email" id="recipient_email" class="regular-text" value="<?php echo $recipient_email; ?>" required>
                        <p class="description"><?php _e( 'The email address to which the message will be sent.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="recipient_name"><?php _e( 'Recipient name', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="recipient_name" id="recipient_name" class="regular-text" value="<?php echo $recipient_name; ?>">
                        <p class="description"><?php _e( 'Recipient name (optional). Can be used in content with {recipient_name}.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="subject"><?php _e( 'Subject', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="subject" id="subject" class="large-text" value="<?php echo $subject_value; ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="body"><?php _e( 'Content', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <?php
                        wp_editor( $body_value, 'body', array(
                            'textarea_name' => 'body',
                            'textarea_rows' => 15,
                            'media_buttons' => true,
                            'teeny'         => false,
                            'quicktags'     => true,
                        ) );
                        ?>
                        <p class="description">
                            <?php _e( 'Available placeholders:', 'mail-system-by-katsarov-design' ); ?>
                            <code>{recipient_name}</code>, <code>{recipient_email}</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bcc"><?php esc_html_e( 'Bcc (Optional)', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="bcc" id="bcc" class="large-text" value="<?php echo isset( $form_data['bcc'] ) ? esc_attr( $form_data['bcc'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'email1@example.com, email2@example.com', 'mail-system-by-katsarov-design' ); ?>">
                        <p class="description">
                            <?php esc_html_e( 'Enter one or more email addresses separated by commas to receive a blind carbon copy of this email. Bcc recipients are hidden from the main recipient.', 'mail-system-by-katsarov-design' ); ?>
                        </p>
                    </td>
                </tr>

                <!-- Custom From Email -->
                <tr>
                    <th scope="row">
                        <label for="use_custom_from"><?php esc_html_e( 'Sender Email', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="use_custom_from" value="default" checked>
                                <?php esc_html_e( 'Use default sender', 'mail-system-by-katsarov-design' ); ?>
                                <span class="description">
                                    <?php
                                    $default_from = get_option( 'mskd_settings', array() );
                                    $default_email = ! empty( $default_from['from_email'] ) ? $default_from['from_email'] : get_bloginfo( 'admin_email' );
                                    printf(
                                        esc_html__( '(%s)', 'mail-system-by-katsarov-design' ),
                                        esc_html( $default_email )
                                    );
                                    ?>
                                </span>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="use_custom_from" value="custom">
                                <?php esc_html_e( 'Use custom sender', 'mail-system-by-katsarov-design' ); ?>
                            </label>
                        </fieldset>
                        
                        <div id="custom_from_fields" style="display: none; margin-top: 10px;">
                            <table class="widefat" style="width: auto;">
                                <tr>
                                    <th style="width: 120px;">
                                        <label for="from_email"><?php esc_html_e( 'From Email', 'mail-system-by-katsarov-design' ); ?> *</label>
                                    </th>
                                    <td>
                                        <input type="email" name="from_email" id="from_email" class="regular-text"
                                               placeholder="<?php esc_attr_e( 'sender@example.com', 'mail-system-by-katsarov-design' ); ?>"
                                               value="<?php echo isset( $form_data['from_email'] ) ? esc_attr( $form_data['from_email'] ) : ''; ?>">
                                        <p class="description">
                                            <?php esc_html_e( 'Email address that will appear as the sender of this email.', 'mail-system-by-katsarov-design' ); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="from_name"><?php esc_html_e( 'From Name', 'mail-system-by-katsarov-design' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="from_name" id="from_name" class="regular-text"
                                               placeholder="<?php esc_attr_e( 'Sender Name', 'mail-system-by-katsarov-design' ); ?>"
                                               value="<?php echo isset( $form_data['from_name'] ) ? esc_attr( $form_data['from_name'] ) : ''; ?>">
                                        <p class="description">
                                            <?php esc_html_e( 'Display name for the sender (optional).', 'mail-system-by-katsarov-design' ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_type"><?php _e( 'Scheduling', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <select name="schedule_type" id="schedule_type" class="mskd-schedule-type">
                            <option value="now" <?php selected( $schedule_type, 'now' ); ?>><?php _e( 'Send now', 'mail-system-by-katsarov-design' ); ?></option>
                            <option value="absolute" <?php selected( $schedule_type, 'absolute' ); ?>><?php _e( 'Specific date and time', 'mail-system-by-katsarov-design' ); ?></option>
                            <option value="relative" <?php selected( $schedule_type, 'relative' ); ?>><?php _e( 'After a set time', 'mail-system-by-katsarov-design' ); ?></option>
                        </select>
                        
                        <div class="mskd-schedule-absolute" style="display: none; margin-top: 10px;">
                            <input type="datetime-local"
                                   name="scheduled_datetime"
                                   id="scheduled_datetime"
                                   class="mskd-datetime-picker"
                                   value="<?php echo esc_attr( $default_datetime ); ?>"
                                   min="<?php echo esc_attr( $min_datetime ); ?>"
                                   step="600">
                            <p class="description">
                                <?php
                                printf(
                                    __( 'Timezone: %s. Select time in 10-minute intervals.', 'mail-system-by-katsarov-design' ),
                                    '<strong>' . esc_html( wp_timezone_string() ) . '</strong>'
                                );
                                ?>
                                <br>
                                <?php
                                $current_time = new DateTime( 'now', $wp_timezone );
                                printf(
                                    __( 'Current server time: %s', 'mail-system-by-katsarov-design' ),
                                    '<strong>' . esc_html( $current_time->format( 'H:i' ) ) . '</strong>'
                                );
                                ?>
                            </p>
                        </div>
                        
                        <div class="mskd-schedule-relative" style="display: none; margin-top: 10px;">
                            <input type="number"
                                   name="delay_value"
                                   id="delay_value"
                                   class="small-text"
                                   value="<?php echo esc_attr( $delay_value ); ?>"
                                   min="1"
                                   max="999">
                            <select name="delay_unit" id="delay_unit">
                                <option value="minutes" <?php selected( $delay_unit, 'minutes' ); ?>><?php _e( 'minutes', 'mail-system-by-katsarov-design' ); ?></option>
                                <option value="hours" <?php selected( $delay_unit, 'hours' ); ?>><?php _e( 'hours', 'mail-system-by-katsarov-design' ); ?></option>
                                <option value="days" <?php selected( $delay_unit, 'days' ); ?>><?php _e( 'days', 'mail-system-by-katsarov-design' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'The email will be sent after the specified time.', 'mail-system-by-katsarov-design' ); ?></p>
                        </div>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="mskd_send_one_time_email" class="button button-primary button-large mskd-submit-btn" 
                       value="<?php _e( 'Send now', 'mail-system-by-katsarov-design' ); ?>" 
                       data-send-now="<?php esc_attr_e( 'Send now', 'mail-system-by-katsarov-design' ); ?>"
                       data-schedule="<?php esc_attr_e( 'Schedule sending', 'mail-system-by-katsarov-design' ); ?>">
            </p>
        </form>
    </div>

    <div class="mskd-info-box">
        <h3><?php _e( 'Information', 'mail-system-by-katsarov-design' ); ?></h3>
        <ul>
            <li><?php _e( 'One-time emails can be sent immediately or scheduled for later.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'They are not saved as templates and do not repeat.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'All sent one-time emails are logged in the audit history.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'Scheduled emails can be cancelled from the Queue page.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'Suitable for: account activation, notifications, event reminders.', 'mail-system-by-katsarov-design' ); ?></li>
        </ul>
    </div>

<script>
jQuery(document).ready(function($) {
    // Schedule type toggle
    $('#schedule_type').on('change', function() {
        var value = $(this).val();
        $('.mskd-schedule-absolute, .mskd-schedule-relative').hide();
        
        if (value === 'absolute') {
            $('.mskd-schedule-absolute').show();
        } else if (value === 'relative') {
            $('.mskd-schedule-relative').show();
        }
        
        // Update button text
        var $btn = $('.mskd-submit-btn');
        if (value === 'now') {
            $btn.val($btn.data('send-now'));
        } else {
            $btn.val($btn.data('schedule'));
        }
    });

    // Custom from email toggle
    $('input[name="use_custom_from"]').on('change', function() {
        var value = $(this).val();
        if (value === 'custom') {
            $('#custom_from_fields').slideDown();
            $('#from_email').prop('required', true);
        } else {
            $('#custom_from_fields').slideUp();
            $('#from_email').prop('required', false);
            $('#from_email, #from_name').val('');
        }
    });

    // Form validation
    $('form').on('submit', function(e) {
        var useCustom = $('input[name="use_custom_from"]:checked').val();
        if (useCustom === 'custom') {
            var fromEmail = $('#from_email').val().trim();
            if (!fromEmail || !isValidEmail(fromEmail)) {
                e.preventDefault();
                alert('<?php esc_html_e( 'Please enter a valid sender email address.', 'mail-system-by-katsarov-design' ); ?>');
                $('#from_email').focus();
                return false;
            }
        }
    });

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
});
</script>
</div>
