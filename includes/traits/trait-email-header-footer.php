<?php
/**
 * Email Header Footer Trait
 *
 * Shared functionality for applying email header and footer content.
 *
 * @package MSKD
 */

namespace MSKD\Traits;

/**
 * Trait Email_Header_Footer
 *
 * Provides shared header/footer application logic for email classes.
 * Used by both Admin_Email (immediate sends) and MSKD_Cron_Handler (queued sends).
 */
trait Email_Header_Footer {

	/**
	 * Apply custom header and footer to email content.
	 *
	 * Prepends the configured email header and appends the configured email footer
	 * to the email body. Both header and footer support the same template variables
	 * as the main email content (e.g., {first_name}, {last_name}, {email}).
	 *
	 * @param string $content  Email body content.
	 * @param array  $settings Plugin settings array containing 'email_header' and 'email_footer' keys.
	 * @return string Email content with header prepended and footer appended.
	 */
	public function apply_header_footer( string $content, array $settings ): string {
		$header = $settings['email_header'] ?? '';
		$footer = $settings['email_footer'] ?? '';

		// Only modify content if header or footer is set.
		if ( empty( $header ) && empty( $footer ) ) {
			return $content;
		}

		// Prepend header if set.
		if ( ! empty( $header ) ) {
			$content = $header . $content;
		}

		// Append footer if set.
		if ( ! empty( $footer ) ) {
			$content = $content . $footer;
		}

		return $content;
	}
}
