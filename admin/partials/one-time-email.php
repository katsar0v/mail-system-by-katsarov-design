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
    <h1><?php _e( 'Еднократен имейл', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <p class="description">
        <?php _e( 'Изпратете еднократен имейл директно до конкретен получател. Имейлът може да бъде изпратен незабавно или насрочен за по-късно.', 'mail-system-by-katsarov-design' ); ?>
    </p>

    <div class="mskd-form-wrap mskd-one-time-email-form">
        <form method="post" action="">
            <?php wp_nonce_field( 'mskd_send_one_time_email', 'mskd_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="recipient_email"><?php _e( 'Имейл на получателя', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <input type="email" name="recipient_email" id="recipient_email" class="regular-text" value="<?php echo $recipient_email; ?>" required>
                        <p class="description"><?php _e( 'Имейл адресът, до който ще бъде изпратено писмото.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="recipient_name"><?php _e( 'Име на получателя', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="recipient_name" id="recipient_name" class="regular-text" value="<?php echo $recipient_name; ?>">
                        <p class="description"><?php _e( 'Име на получателя (по избор). Може да се използва в съдържанието с {recipient_name}.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="subject"><?php _e( 'Тема', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="subject" id="subject" class="large-text" value="<?php echo $subject_value; ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="body"><?php _e( 'Съдържание', 'mail-system-by-katsarov-design' ); ?> *</label>
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
                            <?php _e( 'Налични плейсхолдери:', 'mail-system-by-katsarov-design' ); ?>
                            <code>{recipient_name}</code>, <code>{recipient_email}</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="schedule_type"><?php _e( 'Насрочване', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <select name="schedule_type" id="schedule_type" class="mskd-schedule-type">
                            <option value="now" <?php selected( $schedule_type, 'now' ); ?>><?php _e( 'Изпрати сега', 'mail-system-by-katsarov-design' ); ?></option>
                            <option value="absolute" <?php selected( $schedule_type, 'absolute' ); ?>><?php _e( 'Конкретна дата и час', 'mail-system-by-katsarov-design' ); ?></option>
                            <option value="relative" <?php selected( $schedule_type, 'relative' ); ?>><?php _e( 'След определено време', 'mail-system-by-katsarov-design' ); ?></option>
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
                                    __( 'Часова зона: %s. Изберете време на всеки 10 минути.', 'mail-system-by-katsarov-design' ), 
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
                                   value="<?php echo esc_attr( $delay_value ); ?>" 
                                   min="1" 
                                   max="999">
                            <select name="delay_unit" id="delay_unit">
                                <option value="minutes" <?php selected( $delay_unit, 'minutes' ); ?>><?php _e( 'минути', 'mail-system-by-katsarov-design' ); ?></option>
                                <option value="hours" <?php selected( $delay_unit, 'hours' ); ?>><?php _e( 'часа', 'mail-system-by-katsarov-design' ); ?></option>
                                <option value="days" <?php selected( $delay_unit, 'days' ); ?>><?php _e( 'дни', 'mail-system-by-katsarov-design' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Имейлът ще бъде изпратен след посоченото време.', 'mail-system-by-katsarov-design' ); ?></p>
                        </div>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="mskd_send_one_time_email" class="button button-primary button-large mskd-submit-btn" 
                       value="<?php _e( 'Изпрати сега', 'mail-system-by-katsarov-design' ); ?>" 
                       data-send-now="<?php esc_attr_e( 'Изпрати сега', 'mail-system-by-katsarov-design' ); ?>"
                       data-schedule="<?php esc_attr_e( 'Насрочи изпращане', 'mail-system-by-katsarov-design' ); ?>">
            </p>
        </form>
    </div>

    <div class="mskd-info-box">
        <h3><?php _e( 'Информация', 'mail-system-by-katsarov-design' ); ?></h3>
        <ul>
            <li><?php _e( 'Еднократните имейли могат да бъдат изпратени незабавно или насрочени за по-късно.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'Те не се записват като шаблони и не се повтарят.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'Всички изпратени еднократни имейли се записват в историята за одит.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'Насрочените имейли могат да бъдат отменени от страницата Опашка.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'Подходящи за: активиране на акаунти, уведомления, напомняния за събития.', 'mail-system-by-katsarov-design' ); ?></li>
        </ul>
    </div>
</div>
