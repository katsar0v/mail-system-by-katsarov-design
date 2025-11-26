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

            <h2><?php _e( 'Информация за системата', 'mail-system-by-katsarov-design' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Версия на плъгина', 'mail-system-by-katsarov-design' ); ?></th>
                    <td><code><?php echo esc_html( MSKD_VERSION ); ?></code></td>
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
