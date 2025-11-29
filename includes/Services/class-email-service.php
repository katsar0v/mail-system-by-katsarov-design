<?php
/**
 * Email Service
 *
 * Handles email queuing, campaign management, and scheduling operations.
 *
 * @package MSKD\Services
 * @since   1.1.0
 */

namespace MSKD\Services;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Email_Service
 *
 * Service layer for email and campaign operations.
 */
class Email_Service {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Queue table name.
	 *
	 * @var string
	 */
	private $queue_table;

	/**
	 * Campaigns table name.
	 *
	 * @var string
	 */
	private $campaigns_table;

	/**
	 * Subscriber service instance.
	 *
	 * @var Subscriber_Service
	 */
	private $subscriber_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb               = $wpdb;
		$this->queue_table        = $wpdb->prefix . 'mskd_queue';
		$this->campaigns_table    = $wpdb->prefix . 'mskd_campaigns';
		$this->subscriber_service = new Subscriber_Service();
	}

	/**
	 * Create a campaign and queue emails for subscribers.
	 *
	 * @param array $data {
	 *     Campaign data.
	 *
	 *     @type string $subject      Email subject.
	 *     @type string $body         Email body.
	 *     @type array  $list_ids     Array of list IDs to send to.
	 *     @type array  $subscribers  Array of subscriber objects with email, first_name, etc.
	 *     @type string $scheduled_at MySQL datetime for scheduling.
	 * }
	 * @return int|false Campaign ID on success, false on failure.
	 */
	public function queue_campaign( array $data ) {
		$subject      = $data['subject'] ?? '';
		$body         = $data['body'] ?? '';
		$list_ids     = $data['list_ids'] ?? array();
		$subscribers  = $data['subscribers'] ?? array();
		$scheduled_at = $data['scheduled_at'] ?? mskd_current_time_normalized();

		if ( empty( $subject ) || empty( $body ) || empty( $subscribers ) ) {
			return false;
		}

		// Create campaign record.
		$campaign_data = array(
			'subject'          => $subject,
			'body'             => $body,
			'list_ids'         => wp_json_encode( $list_ids ),
			'type'             => 'campaign',
			'total_recipients' => count( $subscribers ),
			'status'           => 'pending',
			'scheduled_at'     => $scheduled_at,
		);

		$this->wpdb->insert(
			$this->campaigns_table,
			$campaign_data,
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$campaign_id = $this->wpdb->insert_id;

		if ( ! $campaign_id ) {
			return false;
		}

		// Increment persistent campaign counter for share notice.
		$total_campaigns = (int) get_option( 'mskd_total_campaigns_created', 0 );
		update_option( 'mskd_total_campaigns_created', $total_campaigns + 1 );

		// Add subscribers to queue.
		$queued = 0;
		foreach ( $subscribers as $subscriber ) {
			$is_external = \MSKD_List_Provider::is_external_id( $subscriber->id ?? '' );

			// For external subscribers, get or create a subscriber record with unsubscribe token.
			if ( $is_external ) {
				$db_subscriber = $this->subscriber_service->get_or_create(
					$subscriber->email,
					$subscriber->first_name ?? '',
					$subscriber->last_name ?? '',
					Subscriber_Service::SOURCE_EXTERNAL
				);

				// Skip if subscriber is unsubscribed or couldn't be created.
				if ( ! $db_subscriber || 'unsubscribed' === $db_subscriber->status ) {
					continue;
				}

				$subscriber_id = (int) $db_subscriber->id;
			} else {
				// For internal subscribers, check if they're unsubscribed.
				$db_subscriber = $this->subscriber_service->get_by_id( (int) $subscriber->id );
				if ( ! $db_subscriber || 'unsubscribed' === $db_subscriber->status ) {
					continue;
				}
				$subscriber_id = (int) $subscriber->id;
			}

			$queue_data = array(
				'campaign_id'   => $campaign_id,
				'subscriber_id' => $subscriber_id,
				'subject'       => $subject,
				'body'          => $body,
				'status'        => 'pending',
				'scheduled_at'  => $scheduled_at,
			);

			$result = $this->wpdb->insert(
				$this->queue_table,
				$queue_data,
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);

			if ( $result ) {
				++$queued;
			}
		}

		return $campaign_id;
	}

	/**
	 * Queue a one-time email.
	 *
	 * @param array $data {
	 *     Email data.
	 *
	 *     @type string $recipient_email Recipient email address.
	 *     @type string $recipient_name  Recipient name.
	 *     @type string $subject         Email subject.
	 *     @type string $body            Email body.
	 *     @type string $scheduled_at    MySQL datetime for scheduling.
	 *     @type bool   $is_immediate    Whether to mark as already sent.
	 *     @type bool   $sent            Whether the email was sent (for immediate sends).
	 *     @type string $error_message   Error message if sending failed.
	 * }
	 * @return int|false Queue item ID on success, false on failure.
	 */
	public function queue_one_time( array $data ) {
		$recipient_email = $data['recipient_email'] ?? '';
		$recipient_name  = $data['recipient_name'] ?? '';
		$subject         = $data['subject'] ?? '';
		$body            = $data['body'] ?? '';
		$scheduled_at    = $data['scheduled_at'] ?? mskd_current_time_normalized();
		$is_immediate    = $data['is_immediate'] ?? false;
		$sent            = $data['sent'] ?? false;
		$error_message   = $data['error_message'] ?? null;

		if ( empty( $recipient_email ) || empty( $subject ) || empty( $body ) ) {
			return false;
		}

		// Get or create subscriber record for unsubscribe support.
		$subscriber = $this->subscriber_service->get_or_create(
			$recipient_email,
			$recipient_name,
			'',
			Subscriber_Service::SOURCE_ONE_TIME
		);

		// Check if subscriber is unsubscribed.
		if ( $subscriber && 'unsubscribed' === $subscriber->status ) {
			return false;
		}

		$subscriber_id = $subscriber ? (int) $subscriber->id : 0;

		// Create campaign record for the one-time email.
		$campaign_status = $is_immediate ? 'completed' : 'pending';
		$campaign_data   = array(
			'subject'          => $subject,
			'body'             => $body,
			'list_ids'         => null,
			'type'             => 'one_time',
			'total_recipients' => 1,
			'status'           => $campaign_status,
			'scheduled_at'     => $is_immediate ? mskd_current_time_normalized() : $scheduled_at,
		);

		if ( $is_immediate ) {
			$campaign_data['completed_at'] = mskd_current_time_normalized();
		}

		$this->wpdb->insert(
			$this->campaigns_table,
			$campaign_data,
			$is_immediate
				? array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
				: array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$campaign_id = $this->wpdb->insert_id;

		if ( $campaign_id ) {
			// Increment persistent campaign counter for share notice.
			$total_campaigns = (int) get_option( 'mskd_total_campaigns_created', 0 );
			update_option( 'mskd_total_campaigns_created', $total_campaigns + 1 );
		}

		// Queue item data.
		if ( $is_immediate ) {
			$queue_status = $sent ? 'sent' : 'failed';
			$queue_data   = array(
				'campaign_id'   => $campaign_id,
				'subscriber_id' => $subscriber_id,
				'subject'       => $subject,
				'body'          => $body,
				'status'        => $queue_status,
				'scheduled_at'  => mskd_current_time_normalized(),
				'sent_at'       => $sent ? mskd_current_time_normalized() : null,
				'attempts'      => 1,
				'error_message' => $sent ? null : $error_message,
			);
			$format       = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );
		} else {
			$queue_data = array(
				'campaign_id'   => $campaign_id,
				'subscriber_id' => $subscriber_id,
				'subject'       => $subject,
				'body'          => $body,
				'status'        => 'pending',
				'scheduled_at'  => $scheduled_at,
				'sent_at'       => null,
				'attempts'      => 0,
				'error_message' => null,
			);
			$format     = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );
		}

		$result = $this->wpdb->insert( $this->queue_table, $queue_data, $format );

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Cancel a queue item.
	 *
	 * @param int $id Queue item ID.
	 * @return bool True on success, false on failure.
	 */
	public function cancel_queue_item( int $id ): bool {
		// Check if item exists and is cancellable.
		$item = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, status FROM {$this->queue_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $item ) {
			return false;
		}

		if ( ! in_array( $item->status, array( 'pending', 'processing' ), true ) ) {
			return false;
		}

		// Update status to cancelled.
		$result = $this->wpdb->update(
			$this->queue_table,
			array(
				'status'        => 'cancelled',
				'error_message' => __( 'Cancelled by administrator', 'mail-system-by-katsarov-design' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Cancel a campaign and all its pending queue items.
	 *
	 * @param int $id Campaign ID.
	 * @return int|false Number of cancelled items, or false if campaign not found/cancellable.
	 */
	public function cancel_campaign( int $id ) {
		// Check if campaign exists and is cancellable.
		$campaign = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, status FROM {$this->campaigns_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $campaign ) {
			return false;
		}

		if ( ! in_array( $campaign->status, array( 'pending', 'processing' ), true ) ) {
			return false;
		}

		// Cancel all pending/processing queue items for this campaign.
		$cancelled_count = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->queue_table} 
                 SET status = 'cancelled', error_message = %s 
                 WHERE campaign_id = %d AND status IN ('pending', 'processing')",
				__( 'Campaign cancelled by administrator', 'mail-system-by-katsarov-design' ),
				$id
			)
		);

		// Update campaign status.
		$this->wpdb->update(
			$this->campaigns_table,
			array(
				'status'       => 'cancelled',
				'completed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $cancelled_count;
	}

	/**
	 * Get a queue item by ID.
	 *
	 * @param int $id Queue item ID.
	 * @return object|null Queue item or null if not found.
	 */
	public function get_queue_item( int $id ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->queue_table} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get a campaign by ID.
	 *
	 * @param int $id Campaign ID.
	 * @return object|null Campaign or null if not found.
	 */
	public function get_campaign( int $id ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->campaigns_table} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Calculate scheduled time based on user input.
	 *
	 * @param array $post_data POST data containing schedule_type, scheduled_datetime, delay_value, delay_unit.
	 * @return string MySQL datetime string.
	 */
	public function calculate_scheduled_time( array $post_data ): string {
		$schedule_type = isset( $post_data['schedule_type'] ) ? sanitize_text_field( $post_data['schedule_type'] ) : 'now';
		$wp_timezone   = wp_timezone();

		switch ( $schedule_type ) {
			case 'absolute':
				// User picks specific datetime.
				$scheduled_datetime = isset( $post_data['scheduled_datetime'] ) ? sanitize_text_field( $post_data['scheduled_datetime'] ) : '';
				if ( ! empty( $scheduled_datetime ) ) {
					try {
						$date = \DateTime::createFromFormat( 'Y-m-d\TH:i', $scheduled_datetime, $wp_timezone );
						if ( $date ) {
							// Round to nearest 10 minutes.
							$minutes         = (int) $date->format( 'i' );
							$rounded_minutes = round( $minutes / 10 ) * 10;
							if ( $rounded_minutes >= 60 ) {
								$date->modify( '+1 hour' );
								$rounded_minutes = 0;
							}
							$date->setTime( (int) $date->format( 'H' ), $rounded_minutes, 0 );
							return $date->format( 'Y-m-d H:i:s' );
						}
					} catch ( \Exception $e ) {
						// Fall through to default.
					}
				}
				break;

			case 'relative':
				// +N minutes/hours/days from now.
				$delay_value = isset( $post_data['delay_value'] ) ? max( 1, intval( $post_data['delay_value'] ) ) : 1;
				$delay_unit  = isset( $post_data['delay_unit'] ) ? sanitize_text_field( $post_data['delay_unit'] ) : 'hours';

				// Validate unit.
				$allowed_units = array( 'minutes', 'hours', 'days' );
				if ( ! in_array( $delay_unit, $allowed_units, true ) ) {
					$delay_unit = 'hours';
				}

				$date = new \DateTime( 'now', $wp_timezone );
				$date->modify( "+{$delay_value} {$delay_unit}" );

				// Round to nearest 10 minutes for consistency.
				$minutes         = (int) $date->format( 'i' );
				$rounded_minutes = ceil( $minutes / 10 ) * 10;
				if ( $rounded_minutes >= 60 ) {
					$date->modify( '+1 hour' );
					$rounded_minutes = 0;
				}
				$date->setTime( (int) $date->format( 'H' ), $rounded_minutes, 0 );

				return $date->format( 'Y-m-d H:i:s' );

			case 'now':
			default:
				// Send immediately.
				break;
		}

		return mskd_current_time_normalized();
	}

	/**
	 * Check if scheduling is set to immediate send.
	 *
	 * @param array $post_data POST data.
	 * @return bool True if immediate send.
	 */
	public function is_immediate_send( array $post_data ): bool {
		$schedule_type = isset( $post_data['schedule_type'] ) ? sanitize_text_field( $post_data['schedule_type'] ) : 'now';
		return 'now' === $schedule_type;
	}

	/**
	 * Get queue statistics.
	 *
	 * @return array {
	 *     @type int $pending    Pending emails count.
	 *     @type int $processing Processing emails count.
	 *     @type int $sent       Sent emails count.
	 *     @type int $failed     Failed emails count.
	 *     @type int $cancelled  Cancelled emails count.
	 * }
	 */
	public function get_queue_stats(): array {
		$results = $this->wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$this->queue_table} GROUP BY status"
		);

		$stats = array(
			'pending'    => 0,
			'processing' => 0,
			'sent'       => 0,
			'failed'     => 0,
			'cancelled'  => 0,
		);

		foreach ( $results as $row ) {
			if ( isset( $stats[ $row->status ] ) ) {
				$stats[ $row->status ] = (int) $row->count;
			}
		}

		return $stats;
	}

	/**
	 * Truncate queue and campaigns tables (for admin use).
	 *
	 * @return bool True on success.
	 */
	public function truncate_queue(): bool {
		$this->wpdb->query( "TRUNCATE TABLE {$this->queue_table}" );
		$this->wpdb->query( "TRUNCATE TABLE {$this->campaigns_table}" );
		return true;
	}
}
