<?php
/**
 * Admin Settings Controller
 *
 * Handles settings-related admin actions and page rendering.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Settings
 *
 * Controller for settings admin page.
 */
class Admin_Settings {

	/**
	 * Handle settings-related actions.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle settings save.
		if ( isset( $_POST['mskd_save_settings'] ) && wp_verify_nonce( $_POST['mskd_nonce'], 'mskd_save_settings' ) ) {
			$this->handle_save();
		}
	}

	/**
	 * Handle save settings action.
	 *
	 * @return void
	 */
	private function handle_save(): void {
		// Validate SMTP port.
		$smtp_port = isset( $_POST['smtp_port'] ) ? absint( $_POST['smtp_port'] ) : 587;
		if ( $smtp_port < 1 || $smtp_port > 65535 ) {
			$smtp_port = 587;
		}

		// Validate SMTP security.
		$smtp_security    = isset( $_POST['smtp_security'] ) ? sanitize_text_field( wp_unslash( $_POST['smtp_security'] ) ) : '';
		$allowed_security = array( '', 'ssl', 'tls' );
		if ( ! in_array( $smtp_security, $allowed_security, true ) ) {
			$smtp_security = 'tls';
		}

		// Validate emails per minute (1-1000, default to MSKD_BATCH_SIZE).
		$emails_per_minute = isset( $_POST['emails_per_minute'] ) ? absint( $_POST['emails_per_minute'] ) : MSKD_BATCH_SIZE;
		if ( $emails_per_minute < 1 ) {
			$emails_per_minute = 1;
		} elseif ( $emails_per_minute > 1000 ) {
			$emails_per_minute = 1000;
		}

		// Sanitize email header and footer (allow HTML for email templates).
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Admin-only, nonce-verified email HTML content uses custom sanitizer.
		$email_header = isset( $_POST['email_header'] ) ? mskd_kses_email( wp_unslash( $_POST['email_header'] ) ) : '';
		$email_footer = isset( $_POST['email_footer'] ) ? mskd_kses_email( wp_unslash( $_POST['email_footer'] ) ) : '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Validate styling colors (hex color format).
		$highlight_color   = isset( $_POST['highlight_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['highlight_color'] ) ) : '#2271b1';
		$button_text_color = isset( $_POST['button_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['button_text_color'] ) ) : '#ffffff';

		// Ensure valid colors (fallback to defaults if invalid).
		if ( empty( $highlight_color ) ) {
			$highlight_color = '#2271b1';
		}
		if ( empty( $button_text_color ) ) {
			$button_text_color = '#ffffff';
		}

		// Handle password: preserve existing if field is empty (not changing password).
		$current_settings = get_option( 'mskd_settings', array() );
		if ( isset( $_POST['smtp_password'] ) && ! empty( $_POST['smtp_password'] ) ) {
			// New password provided - encrypt it.
			$smtp_password = mskd_encrypt( sanitize_text_field( wp_unslash( $_POST['smtp_password'] ) ) );
		} elseif ( isset( $current_settings['smtp_password'] ) ) {
			// No password provided - keep existing.
			$smtp_password = $current_settings['smtp_password'];
		} else {
			// No password at all.
			$smtp_password = '';
		}

		$settings = array(
			'from_name'         => sanitize_text_field( wp_unslash( $_POST['from_name'] ) ),
			'from_email'        => sanitize_email( wp_unslash( $_POST['from_email'] ) ),
			'reply_to'          => sanitize_email( wp_unslash( $_POST['reply_to'] ) ),
			// Sending settings.
			'emails_per_minute' => $emails_per_minute,
			// Email template settings.
			'email_header'      => $email_header,
			'email_footer'      => $email_footer,
			// Styling settings.
			'highlight_color'   => $highlight_color,
			'button_text_color' => $button_text_color,
			// SMTP Settings.
			'smtp_enabled'      => isset( $_POST['smtp_enabled'] ) ? 1 : 0,
			'smtp_host'         => sanitize_text_field( wp_unslash( $_POST['smtp_host'] ) ),
			'smtp_port'         => $smtp_port,
			'smtp_security'     => $smtp_security,
			'smtp_auth'         => isset( $_POST['smtp_auth'] ) ? 1 : 0,
			'smtp_username'     => sanitize_text_field( wp_unslash( $_POST['smtp_username'] ) ),
			'smtp_password'     => $smtp_password,
		);

		update_option( 'mskd_settings', $settings );

		add_settings_error(
			'mskd_messages',
			'mskd_success',
			__( 'Settings saved successfully.', 'mail-system-by-katsarov-design' ),
			'success'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		include MSKD_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	/**
	 * Get current settings.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		$defaults = array(
			'from_name'         => get_bloginfo( 'name' ),
			'from_email'        => get_bloginfo( 'admin_email' ),
			'reply_to'          => get_bloginfo( 'admin_email' ),
			'emails_per_minute' => MSKD_BATCH_SIZE,
			'email_header'      => '',
			'email_footer'      => '',
			'highlight_color'   => '#2271b1',
			'button_text_color' => '#ffffff',
			'smtp_enabled'      => 0,
			'smtp_host'         => '',
			'smtp_port'         => 587,
			'smtp_security'     => 'tls',
			'smtp_auth'         => 1,
			'smtp_username'     => '',
			'smtp_password'     => '',
		);

		$settings = get_option( 'mskd_settings', array() );

		return wp_parse_args( $settings, $defaults );
	}
}
