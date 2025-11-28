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

// Load the List Provider service.
require_once MSKD_PLUGIN_DIR . 'includes/services/class-list-provider.php';

use MSKD\Services\Template_Service;

// Get all lists (database + external).
$lists = MSKD_List_Provider::get_all_lists();

// Get pre-selected list IDs from URL parameter (supports both single and multiple).
$preselected_list_ids = array();
if ( isset( $_GET['list_id'] ) ) {
	$list_id_param = sanitize_text_field( wp_unslash( $_GET['list_id'] ) );
	// Support comma-separated list IDs.
	$raw_ids = array_map( 'trim', explode( ',', $list_id_param ) );
	foreach ( $raw_ids as $raw_id ) {
		// Validate that the list exists (works for both numeric and ext_* IDs).
		$list = MSKD_List_Provider::get_list( $raw_id );
		if ( $list ) {
			$preselected_list_ids[] = $list->id;
		}
	}
}

// Get template if specified.
$selected_template = null;
$prefilled_subject = '';
$prefilled_content = '';
if ( isset( $_GET['template_id'] ) ) {
	$template_service  = new Template_Service();
	$selected_template = $template_service->get_by_id( intval( $_GET['template_id'] ) );
	if ( $selected_template ) {
		$prefilled_subject = $selected_template->subject;
		$prefilled_content = $selected_template->content;
	}
}

// Get minimum datetime for picker (now + 10 minutes, rounded to nearest 10 min)
$wp_timezone     = wp_timezone();
$now             = new DateTime( 'now', $wp_timezone );
$minutes         = (int) $now->format( 'i' );
$rounded_minutes = ceil( ( $minutes + 1 ) / 10 ) * 10;
if ( $rounded_minutes >= 60 ) {
	$now->modify( '+1 hour' );
	$rounded_minutes = 0;
}
$now->setTime( (int) $now->format( 'H' ), $rounded_minutes, 0 );
$min_datetime = $now->format( 'Y-m-d\TH:i' );
?>

<div class="wrap mskd-wrap">
	<h1><?php esc_html_e( 'New campaign', 'mail-system-by-katsarov-design' ); ?></h1>

    <?php settings_errors( 'mskd_messages' ); ?>

    <!-- Editor type selection -->
    <div class="mskd-editor-toggle" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
        <p style="margin: 0 0 10px; font-weight: 600;"><?php esc_html_e( 'Choose how to compose your email:', 'mail-system-by-katsarov-design' ); ?></p>
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-templates' ) ); ?>" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-layout" style="margin: 0;"></span>
                <?php esc_html_e( 'Use a Template', 'mail-system-by-katsarov-design' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-visual-editor' ) ); ?>" class="button button-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-welcome-widgets-menus" style="margin: 0;"></span>
                <?php esc_html_e( 'Open Visual Editor', 'mail-system-by-katsarov-design' ); ?>
            </a>
        </div>
        <p class="description" style="margin-top: 10px;">
            <?php esc_html_e( 'Or use the standard editor below to compose your email with HTML.', 'mail-system-by-katsarov-design' ); ?>
        </p>
    </div>

    <div class="mskd-form-wrap mskd-compose-form">
        <form method="post" action="">
            <?php wp_nonce_field( 'mskd_send_email', 'mskd_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mskd-lists-select"><?php _e( 'Send to lists', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <?php if ( ! empty( $lists ) ) : ?>
                            <select name="lists[]" id="mskd-lists-select" class="mskd-slimselect-lists" multiple required>
                                <?php foreach ( $lists as $list ) : ?>
                                    <?php
                                    $subscriber_count = MSKD_List_Provider::get_list_active_subscriber_count( $list );
                                    $is_external      = $list->source === 'external';
                                    $is_preselected   = in_array( $list->id, $preselected_list_ids, true );
                                    $badge            = $is_external ? ' [' . __( 'Automated', 'mail-system-by-katsarov-design' ) . ']' : '';
                                    ?>
                                    <option value="<?php echo esc_attr( $list->id ); ?>" 
                                            data-subscribers="<?php echo esc_attr( $subscriber_count ); ?>"
                                            data-external="<?php echo esc_attr( $is_external ? '1' : '0' ); ?>"
                                            <?php selected( $is_preselected ); ?>>
                                        <?php echo esc_html( $list->name . $badge . ' (' . sprintf( __( '%d subscribers', 'mail-system-by-katsarov-design' ), $subscriber_count ) . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description" style="margin-top: 8px;">
                                <?php _e( 'Start typing to search. You can select multiple lists.', 'mail-system-by-katsarov-design' ); ?>
                            </p>
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
                        <label for="subject"><?php esc_html_e( 'Subject', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="subject" id="subject" class="large-text" required value="<?php echo esc_attr( $prefilled_subject ); ?>">
                        <?php if ( $selected_template ) : ?>
                            <p class="description">
                                <?php
                                /* translators: %s: template name */
                                printf( esc_html__( 'Using template: %s', 'mail-system-by-katsarov-design' ), '<strong>' . esc_html( $selected_template->name ) . '</strong>' );
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="body"><?php esc_html_e( 'Content', 'mail-system-by-katsarov-design' ); ?> *</label>
                    </th>
                    <td>
                        <?php
                        wp_editor(
                            $prefilled_content,
                            'body',
                            array(
                                'textarea_name' => 'body',
                                'textarea_rows' => 15,
                                'media_buttons' => true,
                                'teeny'         => false,
                                'quicktags'     => true,
                            )
                        );
                        ?>
                        <p class="description">
                            <?php esc_html_e( 'Available placeholders:', 'mail-system-by-katsarov-design' ); ?>
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
