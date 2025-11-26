<?php
/**
 * Settings page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'mskd_settings', array() );
$from_name = isset( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );
$from_email = isset( $settings['from_email'] ) ? $settings['from_email'] : get_bloginfo( 'admin_email' );
$reply_to = isset( $settings['reply_to'] ) ? $settings['reply_to'] : get_bloginfo( 'admin_email' );

// SMTP Settings.
$smtp_enabled  = isset( $settings['smtp_enabled'] ) ? (bool) $settings['smtp_enabled'] : false;
$smtp_host     = isset( $settings['smtp_host'] ) ? $settings['smtp_host'] : '';
$smtp_port     = isset( $settings['smtp_port'] ) ? $settings['smtp_port'] : '587';
$smtp_security = isset( $settings['smtp_security'] ) ? $settings['smtp_security'] : 'tls';
$smtp_auth     = isset( $settings['smtp_auth'] ) ? (bool) $settings['smtp_auth'] : true;
$smtp_username = isset( $settings['smtp_username'] ) ? $settings['smtp_username'] : '';
$smtp_password = isset( $settings['smtp_password'] ) ? base64_decode( $settings['smtp_password'] ) : '';
?>

<div class="wrap mskd-wrap">
    <h1><?php _e( 'Настройки', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <div class="mskd-form-wrap">
        <form method="post" action="">
            <?php wp_nonce_field( 'mskd_save_settings', 'mskd_nonce' ); ?>

            <h2><?php _e( 'Настройки на изпращача', 'mail-system-by-katsarov-design' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="from_name"><?php _e( 'Име на подател', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="from_name" id="from_name" class="regular-text"
                               value="<?php echo esc_attr( $from_name ); ?>">
                        <p class="description"><?php _e( 'Името, което ще се показва като подател.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="from_email"><?php _e( 'Имейл на подател', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="from_email" id="from_email" class="regular-text"
                               value="<?php echo esc_attr( $from_email ); ?>">
                        <p class="description"><?php _e( 'Имейлът, от който ще се изпращат писмата.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="reply_to"><?php _e( 'Отговор до', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="reply_to" id="reply_to" class="regular-text"
                               value="<?php echo esc_attr( $reply_to ); ?>">
                        <p class="description"><?php _e( 'Имейл за отговори от получателите.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2><?php _e( 'SMTP Настройки', 'mail-system-by-katsarov-design' ); ?></h2>
            <p class="description"><?php _e( 'Конфигурирайте SMTP сървър за по-надеждно изпращане на имейли.', 'mail-system-by-katsarov-design' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="smtp_enabled"><?php _e( 'Активиране на SMTP', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="smtp_enabled" id="smtp_enabled" value="1"
                                <?php checked( $smtp_enabled ); ?>>
                            <?php _e( 'Използване на SMTP за изпращане на имейли', 'mail-system-by-katsarov-design' ); ?>
                        </label>
                        <p class="description"><?php _e( 'Когато е активирано, имейлите ще се изпращат чрез посочения SMTP сървър вместо wp_mail().', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label for="smtp_host"><?php _e( 'SMTP Хост', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="smtp_host" id="smtp_host" class="regular-text"
                               value="<?php echo esc_attr( $smtp_host ); ?>" 
                               placeholder="smtp.example.com">
                        <p class="description"><?php _e( 'Адрес на SMTP сървъра (напр. smtp.gmail.com, smtp.mailgun.org).', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label for="smtp_port"><?php _e( 'SMTP Порт', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="smtp_port" id="smtp_port" class="small-text"
                               value="<?php echo esc_attr( $smtp_port ); ?>" 
                               min="1" max="65535">
                        <p class="description"><?php _e( 'Стандартни портове: 25, 465 (SSL), 587 (TLS/StartTLS).', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label for="smtp_security"><?php _e( 'Криптиране', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <select name="smtp_security" id="smtp_security">
                            <option value="" <?php selected( $smtp_security, '' ); ?>><?php _e( 'Без криптиране', 'mail-system-by-katsarov-design' ); ?></option>
                            <option value="ssl" <?php selected( $smtp_security, 'ssl' ); ?>>SSL</option>
                            <option value="tls" <?php selected( $smtp_security, 'tls' ); ?>>TLS (StartTLS)</option>
                        </select>
                        <p class="description"><?php _e( 'Препоръчително е да използвате TLS или SSL за сигурна връзка.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label for="smtp_auth"><?php _e( 'SMTP Удостоверяване', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="smtp_auth" id="smtp_auth" value="1"
                                <?php checked( $smtp_auth ); ?>>
                            <?php _e( 'Използване на удостоверяване (потребителско име и парола)', 'mail-system-by-katsarov-design' ); ?>
                        </label>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting mskd-smtp-auth-setting">
                    <th scope="row">
                        <label for="smtp_username"><?php _e( 'SMTP Потребителско име', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="smtp_username" id="smtp_username" class="regular-text"
                               value="<?php echo esc_attr( $smtp_username ); ?>" 
                               autocomplete="off">
                        <p class="description"><?php _e( 'Обикновено това е вашият имейл адрес или потребителско име.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting mskd-smtp-auth-setting">
                    <th scope="row">
                        <label for="smtp_password"><?php _e( 'SMTP Парола', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="smtp_password" id="smtp_password" class="regular-text"
                               value="<?php echo esc_attr( $smtp_password ); ?>" 
                               autocomplete="new-password">
                        <p class="description"><?php _e( 'За Gmail използвайте App Password вместо обичайната парола.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label><?php _e( 'Тест на връзката', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <button type="button" id="mskd-smtp-test" class="button button-secondary">
                            <?php _e( 'Изпрати тестов имейл', 'mail-system-by-katsarov-design' ); ?>
                        </button>
                        <span id="mskd-smtp-test-result"></span>
                        <p class="description"><?php _e( 'Изпраща тестов имейл до имейла на администратора.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2><?php _e( 'Информация за системата', 'mail-system-by-katsarov-design' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Версия на плъгина', 'mail-system-by-katsarov-design' ); ?></th>
                    <td><code><?php echo esc_html( MSKD_VERSION ); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Метод за изпращане', 'mail-system-by-katsarov-design' ); ?></th>
                    <td>
                        <?php if ( $smtp_enabled && ! empty( $smtp_host ) ) : ?>
                            <span class="mskd-smtp-active"><?php _e( 'SMTP', 'mail-system-by-katsarov-design' ); ?></span>
                            <code><?php echo esc_html( $smtp_host . ':' . $smtp_port ); ?></code>
                        <?php else : ?>
                            <span class="mskd-smtp-inactive"><?php _e( 'wp_mail() (по подразбиране)', 'mail-system-by-katsarov-design' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Скорост на изпращане', 'mail-system-by-katsarov-design' ); ?></th>
                    <td>
                        <code><?php echo esc_html( MSKD_BATCH_SIZE ); ?> <?php _e( 'имейла/минута', 'mail-system-by-katsarov-design' ); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'WP-Cron статус', 'mail-system-by-katsarov-design' ); ?></th>
                    <td>
                        <?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
                            <span class="mskd-cron-disabled"><?php _e( 'Деактивиран (използва се системен cron)', 'mail-system-by-katsarov-design' ); ?></span>
                        <?php else : ?>
                            <span class="mskd-cron-enabled"><?php _e( 'Активен (базиран на посещения)', 'mail-system-by-katsarov-design' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Следващо изпълнение', 'mail-system-by-katsarov-design' ); ?></th>
                    <td>
                        <?php
                        $next_cron = wp_next_scheduled( 'mskd_process_queue' );
                        if ( $next_cron ) {
                            echo esc_html( date_i18n( 'd.m.Y H:i:s', $next_cron ) );
                        } else {
                            _e( 'Не е насрочено', 'mail-system-by-katsarov-design' );
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="mskd_save_settings" class="button button-primary" 
                       value="<?php _e( 'Запази настройките', 'mail-system-by-katsarov-design' ); ?>">
            </p>
        </form>
    </div>
</div>
