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
		$smtp_security    = isset( $_POST['smtp_security'] ) ? sanitize_text_field( $_POST['smtp_security'] ) : '';
		$allowed_security = array( '', 'ssl', 'tls' );
		if ( ! in_array( $smtp_security, $allowed_security, true ) ) {
			$smtp_security = 'tls';
		}

		$settings = array(
			'from_name'     => sanitize_text_field( $_POST['from_name'] ),
			'from_email'    => sanitize_email( $_POST['from_email'] ),
			'reply_to'      => sanitize_email( $_POST['reply_to'] ),
			// SMTP Settings.
			'smtp_enabled'  => isset( $_POST['smtp_enabled'] ) ? 1 : 0,
			'smtp_host'     => sanitize_text_field( $_POST['smtp_host'] ),
			'smtp_port'     => $smtp_port,
			'smtp_security' => $smtp_security,
			'smtp_auth'     => isset( $_POST['smtp_auth'] ) ? 1 : 0,
			'smtp_username' => sanitize_text_field( $_POST['smtp_username'] ),
			'smtp_password' => isset( $_POST['smtp_password'] ) ? base64_encode( sanitize_text_field( $_POST['smtp_password'] ) ) : '',
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
			'from_name'     => get_bloginfo( 'name' ),
			'from_email'    => get_bloginfo( 'admin_email' ),
			'reply_to'      => get_bloginfo( 'admin_email' ),
			'smtp_enabled'  => 0,
			'smtp_host'     => '',
			'smtp_port'     => 587,
			'smtp_security' => 'tls',
			'smtp_auth'     => 1,
			'smtp_username' => '',
			'smtp_password' => '',
		);

		$settings = get_option( 'mskd_settings', array() );

		return wp_parse_args( $settings, $defaults );
	}
}
