<?php
/**
 * Cron Handler Service
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSKD_Cron_Handler
 *
 * Handles WP-Cron queue processing
 */
class MSKD_Cron_Handler {

	/**
	 * Maximum retry attempts for failed emails
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Timeout in minutes for stuck processing emails
	 */
	const PROCESSING_TIMEOUT_MINUTES = 5;

	/**
	 * SMTP Mailer instance.
	 *
	 * @var MSKD_SMTP_Mailer|null
	 */
	private $smtp_mailer = null;

	/**
	 * Initialize cron hooks
	 */
	public function init() {
		add_action( 'mskd_process_queue', array( $this, 'process_queue' ) );
	}

	/**
	 * Process email queue
	 *
	 * Sends up to the configured emails_per_minute limit per run.
	 * Supports both database subscribers (subscriber_id > 0) and
	 * external subscribers (subscriber_id = 0 with subscriber_data JSON).
	 */
	public function process_queue() {
		global $wpdb;

		// First, recover stuck emails (processing for too long)
		$this->recover_stuck_emails();

		// Get the configured batch size (emails per minute).
		$batch_size = $this->get_batch_size();

		// Get pending emails for database subscribers (subscriber_id > 0).
		$db_queue_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.*, s.email, s.first_name, s.last_name, s.unsubscribe_token
            FROM {$wpdb->prefix}mskd_queue q
            INNER JOIN {$wpdb->prefix}mskd_subscribers s ON q.subscriber_id = s.id
            WHERE q.status = 'pending' 
            AND q.subscriber_id > 0
            AND q.scheduled_at <= %s
            AND s.status = 'active'
            ORDER BY q.scheduled_at ASC
            LIMIT %d",
				current_time( 'mysql' ),
				$batch_size
			)
		);

		// Get pending emails for external subscribers (subscriber_id = 0).
		$remaining_slots = $batch_size - count( $db_queue_items );
		$ext_queue_items = array();

		if ( $remaining_slots > 0 ) {
			$ext_queue_items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT q.*
                FROM {$wpdb->prefix}mskd_queue q
                WHERE q.status = 'pending' 
                AND q.subscriber_id = 0
                AND q.subscriber_data IS NOT NULL
                AND q.scheduled_at <= %s
                ORDER BY q.scheduled_at ASC
                LIMIT %d",
					current_time( 'mysql' ),
					$remaining_slots
				)
			);

			// Parse subscriber_data JSON for external subscribers.
			foreach ( $ext_queue_items as $item ) {
				$data = json_decode( $item->subscriber_data, false );
				if ( $data ) {
					$item->email             = $data->email ?? '';
					$item->first_name        = $data->first_name ?? '';
					$item->last_name         = $data->last_name ?? '';
					$item->unsubscribe_token = $data->unsubscribe_token ?? '';
					$item->is_external       = true;
				}
			}
		}

		$queue_items = array_merge( $db_queue_items, $ext_queue_items );

		if ( empty( $queue_items ) ) {
			return;
		}

		// Get settings
		$settings = get_option( 'mskd_settings', array() );

		// Initialize mailer (uses SMTP if configured, otherwise PHP mail).
		require_once MSKD_PLUGIN_DIR . 'includes/services/class-smtp-mailer.php';
		$this->smtp_mailer = new MSKD_SMTP_Mailer( $settings );

		foreach ( $queue_items as $item ) {
			// Skip external items with invalid email.
			if ( empty( $item->email ) || ! is_email( $item->email ) ) {
				$wpdb->update(
					$wpdb->prefix . 'mskd_queue',
					array(
						'status'        => 'failed',
						'error_message' => __( 'Invalid or missing email address', 'mail-system-by-katsarov-design' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				continue;
			}

			// Mark as processing
			$wpdb->update(
				$wpdb->prefix . 'mskd_queue',
				array(
					'status'   => 'processing',
					'attempts' => $item->attempts + 1,
				),
				array( 'id' => $item->id ),
				array( '%s', '%d' ),
				array( '%d' )
			);

			// Prepare email content with header, footer, and placeholders.
			$body    = $this->apply_header_footer( $item->body, $settings );
			$body    = $this->replace_placeholders( $body, $item );
			$subject = $this->replace_placeholders( $item->subject, $item );

			// Send email using SMTP mailer.
			$sent          = false;
			$error_message = '';

			$sent = $this->smtp_mailer->send( $item->email, $subject, $body );
			if ( ! $sent ) {
				$error_message = $this->smtp_mailer->get_last_error();
			}

			if ( $sent ) {
				// Mark as sent
				$wpdb->update(
					$wpdb->prefix . 'mskd_queue',
					array(
						'status'  => 'sent',
						'sent_at' => current_time( 'mysql' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			} else {
				// Check if we should retry or mark as failed
				$new_attempts = $item->attempts + 1;

				// Build error message with details.
				$base_error = __( 'SMTP sending failed', 'mail-system-by-katsarov-design' );

				if ( $new_attempts < self::MAX_ATTEMPTS ) {
					// Schedule for retry - set back to pending with delayed schedule
					$retry_delay   = $new_attempts * 2; // 2, 4 minutes delay
					$retry_message = sprintf(
						/* translators: 1: Attempt number, 2: Error details */
						__( 'Attempt %1$d failed. %2$s Will retry.', 'mail-system-by-katsarov-design' ),
						$new_attempts,
						$error_message ? '(' . $error_message . ')' : ''
					);
					// Normalize to 00 seconds
					$retry_timestamp = mskd_normalize_timestamp( strtotime( "+{$retry_delay} minutes" ) );
					$wpdb->update(
						$wpdb->prefix . 'mskd_queue',
						array(
							'status'        => 'pending',
							'scheduled_at'  => date( 'Y-m-d H:i:s', $retry_timestamp ),
							'error_message' => $retry_message,
						),
						array( 'id' => $item->id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);
				} else {
					// Max attempts reached, mark as failed
					$fail_message = sprintf(
						/* translators: 1: Base error message, 2: Max attempts, 3: Error details */
						__( '%1$s after %2$d attempts. %3$s', 'mail-system-by-katsarov-design' ),
						$base_error,
						self::MAX_ATTEMPTS,
						$error_message ? '(' . $error_message . ')' : ''
					);
					$wpdb->update(
						$wpdb->prefix . 'mskd_queue',
						array(
							'status'        => 'failed',
							'error_message' => $fail_message,
						),
						array( 'id' => $item->id ),
						array( '%s', '%s' ),
						array( '%d' )
					);
				}
			}

			// Update campaign status if this item belongs to a campaign.
			if ( ! empty( $item->campaign_id ) ) {
				$this->update_campaign_status( $item->campaign_id );
			}
		}
	}

	/**
	 * Update campaign status based on queue item statuses
	 *
	 * @param int $campaign_id The campaign ID to update.
	 */
	private function update_campaign_status( $campaign_id ) {
		global $wpdb;

		// Get counts of queue items for this campaign.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM {$wpdb->prefix}mskd_queue
            WHERE campaign_id = %d",
				$campaign_id
			)
		);

		if ( ! $stats ) {
			return;
		}

		// Determine campaign status.
		$pending = intval( $stats->pending );
		$total   = intval( $stats->total );

		if ( $pending > 0 ) {
			// Still has pending emails - mark as processing.
			$wpdb->update(
				$wpdb->prefix . 'mskd_campaigns',
				array( 'status' => 'processing' ),
				array( 'id' => $campaign_id ),
				array( '%s' ),
				array( '%d' )
			);
		} else {
			// All emails are done - mark as completed.
			$wpdb->update(
				$wpdb->prefix . 'mskd_campaigns',
				array(
					'status'       => 'completed',
					'completed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $campaign_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Recover stuck emails that have been processing for too long
	 */
	private function recover_stuck_emails() {
		global $wpdb;

		// Normalize to 00 seconds
		$timeout_timestamp = mskd_normalize_timestamp( strtotime( '-' . self::PROCESSING_TIMEOUT_MINUTES . ' minutes' ) );
		$timeout_threshold = date( 'Y-m-d H:i:s', $timeout_timestamp );

		// Find emails stuck in processing status
		$stuck_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, attempts FROM {$wpdb->prefix}mskd_queue 
            WHERE status = 'processing' 
            AND scheduled_at < %s",
				$timeout_threshold
			)
		);

		foreach ( $stuck_items as $item ) {
			if ( $item->attempts < self::MAX_ATTEMPTS ) {
				// Reset to pending for retry
				$wpdb->update(
					$wpdb->prefix . 'mskd_queue',
					array(
						'status'        => 'pending',
						'scheduled_at'  => mskd_current_time_normalized(),
						'error_message' => __( 'Recovered after stuck in processing', 'mail-system-by-katsarov-design' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				// Max attempts reached, mark as failed
				$wpdb->update(
					$wpdb->prefix . 'mskd_queue',
					array(
						'status'        => 'failed',
						'error_message' => __( 'Failed after maximum attempts (stuck)', 'mail-system-by-katsarov-design' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Replace placeholders in email content
	 *
	 * @param string $content Email content
	 * @param object $subscriber Subscriber data
	 * @return string
	 */
	private function replace_placeholders( $content, $subscriber ) {
		$unsubscribe_url = add_query_arg(
			array(
				'mskd_unsubscribe' => $subscriber->unsubscribe_token,
			),
			home_url()
		);

		$placeholders = array(
			'{first_name}'       => $subscriber->first_name,
			'{last_name}'        => $subscriber->last_name,
			'{email}'            => $subscriber->email,
			'{unsubscribe_link}' => '<a href="' . esc_url( $unsubscribe_url ) . '">' . __( 'Unsubscribe', 'mail-system-by-katsarov-design' ) . '</a>',
			'{unsubscribe_url}'  => $unsubscribe_url,
		);

		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
	}

	/**
	 * Apply custom header and footer to email content.
	 *
	 * Prepends the configured email header and appends the configured email footer
	 * to the email body. Both header and footer support the same template variables
	 * as the main email content.
	 *
	 * @param string $content  Email body content.
	 * @param array  $settings Plugin settings array.
	 * @return string Email content with header prepended and footer appended.
	 */
	private function apply_header_footer( $content, $settings ) {
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

	/**
	 * Get the batch size (emails per minute) from settings.
	 *
	 * Falls back to MSKD_BATCH_SIZE constant if setting is not configured.
	 *
	 * @return int The number of emails to send per minute.
	 */
	private function get_batch_size() {
		$settings = get_option( 'mskd_settings', array() );

		if ( isset( $settings['emails_per_minute'] ) && absint( $settings['emails_per_minute'] ) > 0 ) {
			return absint( $settings['emails_per_minute'] );
		}

		return MSKD_BATCH_SIZE;
	}
}
