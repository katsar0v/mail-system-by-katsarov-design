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

// Sending settings.
$emails_per_minute = isset( $settings['emails_per_minute'] ) ? absint( $settings['emails_per_minute'] ) : MSKD_BATCH_SIZE;

// Email template settings.
$email_header = isset( $settings['email_header'] ) ? $settings['email_header'] : '';
$email_footer = isset( $settings['email_footer'] ) ? $settings['email_footer'] : '';

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
    <h1><?php _e( 'Settings', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <div class="mskd-form-wrap">
        <form method="post" action="">
            <?php wp_nonce_field( 'mskd_save_settings', 'mskd_nonce' ); ?>

            <h2><?php _e( 'Sender settings', 'mail-system-by-katsarov-design' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="from_name"><?php _e( 'Sender name', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="from_name" id="from_name" class="regular-text"
                               value="<?php echo esc_attr( $from_name ); ?>">
                        <p class="description"><?php _e( 'The name that will appear as sender.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="from_email"><?php _e( 'Sender email', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="from_email" id="from_email" class="regular-text"
                               value="<?php echo esc_attr( $from_email ); ?>">
                        <p class="description"><?php _e( 'The email from which messages will be sent.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="reply_to"><?php _e( 'Reply to', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="reply_to" id="reply_to" class="regular-text"
                               value="<?php echo esc_attr( $reply_to ); ?>">
                        <p class="description"><?php _e( 'Email for replies from recipients.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2><?php _e( 'Sending settings', 'mail-system-by-katsarov-design' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="emails_per_minute"><?php _e( 'Emails per minute', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="emails_per_minute" id="emails_per_minute" class="small-text"
                               value="<?php echo esc_attr( $emails_per_minute ); ?>"
                               min="1" max="1000">
                        <p class="description"><?php _e( 'Maximum number of emails to send per minute. Higher values may exceed your hosting provider limits.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2><?php _e( 'Email Template Settings', 'mail-system-by-katsarov-design' ); ?></h2>
            <p class="description"><?php _e( 'Configure custom header and footer that will be added to all outgoing emails. Supports HTML and template variables.', 'mail-system-by-katsarov-design' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="email_header"><?php _e( 'Email Header', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <textarea name="email_header" id="email_header" class="large-text code" rows="6"><?php echo esc_textarea( $email_header ); ?></textarea>
                        <p class="description"><?php _e( 'HTML content to prepend to all emails. Leave empty to disable.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="email_footer"><?php _e( 'Email Footer', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <textarea name="email_footer" id="email_footer" class="large-text code" rows="6"><?php echo esc_textarea( $email_footer ); ?></textarea>
                        <p class="description"><?php _e( 'HTML content to append to all emails. Leave empty to disable.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php _e( 'Available Variables', 'mail-system-by-katsarov-design' ); ?>
                    </th>
                    <td>
                        <p class="description">
                            <code>{first_name}</code> - <?php _e( 'Subscriber first name', 'mail-system-by-katsarov-design' ); ?><br>
                            <code>{last_name}</code> - <?php _e( 'Subscriber last name', 'mail-system-by-katsarov-design' ); ?><br>
                            <code>{email}</code> - <?php _e( 'Subscriber email address', 'mail-system-by-katsarov-design' ); ?><br>
                            <code>{unsubscribe_link}</code> - <?php _e( 'Clickable unsubscribe link', 'mail-system-by-katsarov-design' ); ?><br>
                            <code>{unsubscribe_url}</code> - <?php _e( 'Raw unsubscribe URL', 'mail-system-by-katsarov-design' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2><?php _e( 'SMTP Settings', 'mail-system-by-katsarov-design' ); ?></h2>
            <p class="description"><?php _e( 'Configure an SMTP server for more reliable email sending.', 'mail-system-by-katsarov-design' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="smtp_enabled"><?php _e( 'Enable SMTP', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="smtp_enabled" id="smtp_enabled" value="1"
                                <?php checked( $smtp_enabled ); ?>>
                            <?php _e( 'Use SMTP for sending emails', 'mail-system-by-katsarov-design' ); ?>
                        </label>
                        <p class="description"><?php _e( 'When enabled, emails will be sent via the specified SMTP server instead of wp_mail().', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label for="smtp_host"><?php _e( 'SMTP Host', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="smtp_host" id="smtp_host" class="regular-text"
                               value="<?php echo esc_attr( $smtp_host ); ?>" 
                               placeholder="smtp.example.com">
                        <p class="description"><?php _e( 'SMTP server address (e.g. smtp.gmail.com, smtp.mailgun.org).', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label for="smtp_port"><?php _e( 'SMTP Port', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="smtp_port" id="smtp_port" class="small-text"
                               value="<?php echo esc_attr( $smtp_port ); ?>" 
                               min="1" max="65535">
                        <p class="description"><?php _e( 'Standard ports: 25, 465 (SSL), 587 (TLS/StartTLS).', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label for="smtp_security"><?php _e( 'Encryption', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <select name="smtp_security" id="smtp_security">
                            <option value="" <?php selected( $smtp_security, '' ); ?>><?php _e( 'No encryption', 'mail-system-by-katsarov-design' ); ?></option>
                            <option value="ssl" <?php selected( $smtp_security, 'ssl' ); ?>>SSL</option>
                            <option value="tls" <?php selected( $smtp_security, 'tls' ); ?>>TLS (StartTLS)</option>
                        </select>
                        <p class="description"><?php _e( 'It is recommended to use TLS or SSL for a secure connection.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label for="smtp_auth"><?php _e( 'SMTP Authentication', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="smtp_auth" id="smtp_auth" value="1"
                                <?php checked( $smtp_auth ); ?>>
                            <?php _e( 'Use authentication (username and password)', 'mail-system-by-katsarov-design' ); ?>
                        </label>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting mskd-smtp-auth-setting">
                    <th scope="row">
                        <label for="smtp_username"><?php _e( 'SMTP Username', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="smtp_username" id="smtp_username" class="regular-text"
                               value="<?php echo esc_attr( $smtp_username ); ?>" 
                               autocomplete="off">
                        <p class="description"><?php _e( 'Usually this is your email address or username.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting mskd-smtp-auth-setting">
                    <th scope="row">
                        <label for="smtp_password"><?php _e( 'SMTP Password', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="smtp_password" id="smtp_password" class="regular-text"
                               value="<?php echo esc_attr( $smtp_password ); ?>" 
                               autocomplete="new-password">
                        <p class="description"><?php _e( 'For Gmail, use App Password instead of the regular password.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
                <tr class="mskd-smtp-setting">
                    <th scope="row">
                        <label><?php _e( 'Connection test', 'mail-system-by-katsarov-design' ); ?></label>
                    </th>
                    <td>
                        <button type="button" id="mskd-smtp-test" class="button button-secondary">
                            <?php _e( 'Send test email', 'mail-system-by-katsarov-design' ); ?>
                        </button>
                        <span id="mskd-smtp-test-result"></span>
                        <p class="description"><?php _e( 'Sends a test email to the administrator email.', 'mail-system-by-katsarov-design' ); ?></p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2><?php _e( 'System information', 'mail-system-by-katsarov-design' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Plugin version', 'mail-system-by-katsarov-design' ); ?></th>
                    <td><code><?php echo esc_html( MSKD_VERSION ); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Sending method', 'mail-system-by-katsarov-design' ); ?></th>
                    <td>
                        <?php if ( $smtp_enabled && ! empty( $smtp_host ) ) : ?>
                            <span class="mskd-smtp-active"><?php _e( 'SMTP', 'mail-system-by-katsarov-design' ); ?></span>
                            <code><?php echo esc_html( $smtp_host . ':' . $smtp_port ); ?></code>
                        <?php else : ?>
                            <span class="mskd-smtp-inactive"><?php _e( 'wp_mail() (default)', 'mail-system-by-katsarov-design' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Sending speed', 'mail-system-by-katsarov-design' ); ?></th>
                    <td>
                        <code><?php echo esc_html( $emails_per_minute ); ?> <?php _e( 'emails/minute', 'mail-system-by-katsarov-design' ); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'WP-Cron status', 'mail-system-by-katsarov-design' ); ?></th>
                    <td>
                        <?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
                            <span class="mskd-cron-disabled"><?php _e( 'Disabled (using system cron)', 'mail-system-by-katsarov-design' ); ?></span>
                        <?php else : ?>
                            <span class="mskd-cron-enabled"><?php _e( 'Active (visitor-based)', 'mail-system-by-katsarov-design' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Next run', 'mail-system-by-katsarov-design' ); ?></th>
                    <td>
                        <?php
                        $next_cron = wp_next_scheduled( 'mskd_process_queue' );
                        if ( $next_cron ) {
                            echo esc_html( date_i18n( 'd.m.Y H:i:s', $next_cron ) );
                        } else {
                            _e( 'Not scheduled', 'mail-system-by-katsarov-design' );
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="mskd_save_settings" class="button button-primary" 
                       value="<?php _e( 'Save settings', 'mail-system-by-katsarov-design' ); ?>">
            </p>
        </form>

        <hr>

        <h2 class="mskd-danger-heading"><?php _e( 'Danger Zone', 'mail-system-by-katsarov-design' ); ?></h2>
        <p class="description mskd-danger-description"><?php _e( 'These actions are irreversible. Use with caution.', 'mail-system-by-katsarov-design' ); ?></p>

        <table class="form-table mskd-danger-zone">
            <tr>
                <th scope="row">
                    <label><?php _e( 'Truncate Subscribers', 'mail-system-by-katsarov-design' ); ?></label>
                </th>
                <td>
                    <button type="button" id="mskd-truncate-subscribers" class="button mskd-button-danger">
                        <?php _e( 'Delete all subscribers', 'mail-system-by-katsarov-design' ); ?>
                    </button>
                    <span id="mskd-truncate-subscribers-result"></span>
                    <p class="description"><?php _e( 'Permanently deletes all subscribers and their list associations.', 'mail-system-by-katsarov-design' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e( 'Truncate Lists', 'mail-system-by-katsarov-design' ); ?></label>
                </th>
                <td>
                    <button type="button" id="mskd-truncate-lists" class="button mskd-button-danger">
                        <?php _e( 'Delete all lists', 'mail-system-by-katsarov-design' ); ?>
                    </button>
                    <span id="mskd-truncate-lists-result"></span>
                    <p class="description"><?php _e( 'Permanently deletes all mailing lists and subscriber associations.', 'mail-system-by-katsarov-design' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e( 'Truncate Campaigns', 'mail-system-by-katsarov-design' ); ?></label>
                </th>
                <td>
                    <button type="button" id="mskd-truncate-queue" class="button mskd-button-danger">
                        <?php _e( 'Delete all campaigns', 'mail-system-by-katsarov-design' ); ?>
                    </button>
                    <span id="mskd-truncate-queue-result"></span>
                    <p class="description"><?php _e( 'Permanently deletes all campaigns and their queued emails (pending, processing, sent, and failed).', 'mail-system-by-katsarov-design' ); ?></p>
                </td>
            </tr>
        </table>
    </div>
</div>
