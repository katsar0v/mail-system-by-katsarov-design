<?php
/**
 * One-Time Email page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap mskd-wrap">
    <h1><?php _e( 'Еднократен имейл', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <p class="description">
        <?php _e( 'Изпратете еднократен имейл директно до конкретен получател. Този имейл няма да бъде запазен като шаблон и ще бъде изпратен незабавно.', 'mail-system-by-katsarov-design' ); ?>
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
                        <input type="email" name="recipient_email" id="recipient_email" class="regular-text" required>
                        <p class="description"><?php _e( 'Имейл адресът, до който ще бъде изпратено писмото.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="recipient_name"><?php _e( 'Име на получателя', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="recipient_name" id="recipient_name" class="regular-text">
                        <p class="description"><?php _e( 'Име на получателя (по избор). Може да се използва в съдържанието с {recipient_name}.', 'mail-system-by-katsarov-design' ); ?></p>
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
                            <code>{recipient_name}</code>, <code>{recipient_email}</code>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="mskd_send_one_time_email" class="button button-primary button-large" 
                       value="<?php _e( 'Изпрати сега', 'mail-system-by-katsarov-design' ); ?>">
            </p>
        </form>
    </div>

    <div class="mskd-info-box">
        <h3><?php _e( 'Информация', 'mail-system-by-katsarov-design' ); ?></h3>
        <ul>
            <li><?php _e( 'Еднократните имейли се изпращат незабавно.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'Те не се записват като шаблони и не се повтарят.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'Всички изпратени еднократни имейли се записват в историята за одит.', 'mail-system-by-katsarov-design' ); ?></li>
            <li><?php _e( 'Подходящи за: активиране на акаунти, уведомления, напомняния за събития.', 'mail-system-by-katsarov-design' ); ?></li>
        </ul>
    </div>
</div>
