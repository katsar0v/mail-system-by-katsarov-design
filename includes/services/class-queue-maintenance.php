<?php
/**
 * Queue Maintenance Service
 *
 * @package MSKD\Services
 */

namespace MSKD\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps queue and campaign state consistent when recipients become inactive.
 */
class Queue_Maintenance {

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
	 * Subscribers table name.
	 *
	 * @var string
	 */
	private $subscribers_table;

	/**
	 * Campaigns table name.
	 *
	 * @var string
	 */
	private $campaigns_table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional database instance for tests.
	 */
	public function __construct( $wpdb = null ) {
		if ( null === $wpdb ) {
			global $wpdb;
		}

		$this->wpdb              = $wpdb;
		$this->queue_table       = $wpdb->prefix . 'mskd_queue';
		$this->subscribers_table = $wpdb->prefix . 'mskd_subscribers';
		$this->campaigns_table   = $wpdb->prefix . 'mskd_campaigns';
	}

	/**
	 * Cancel pending deliveries whose recipient is no longer active.
	 *
	 * This is the recurring self-healing path for status changes made through
	 * imports, admin tools, older plugin versions, or interrupted requests.
	 *
	 * @return int Number of queue rows cancelled.
	 */
	public function cancel_pending_for_inactive_subscribers(): int {
		$campaign_ids = $this->wpdb->get_col(
			"SELECT DISTINCT q.campaign_id
			FROM {$this->queue_table} q
			INNER JOIN {$this->campaigns_table} c ON c.id = q.campaign_id
			LEFT JOIN {$this->subscribers_table} s ON s.id = q.subscriber_id
			WHERE q.status = 'pending'
			AND (s.id IS NULL OR s.status <> 'active')
			AND c.status IN ('pending', 'processing')"
		);

		$campaign_ids = $this->normalize_campaign_ids( $campaign_ids );
		if ( empty( $campaign_ids ) ) {
			return 0;
		}

		$cancelled = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->queue_table} q
				INNER JOIN {$this->campaigns_table} c ON c.id = q.campaign_id
				LEFT JOIN {$this->subscribers_table} s ON s.id = q.subscriber_id
				SET q.status = 'cancelled',
					q.processing_started_at = NULL,
					q.error_message = %s
				WHERE q.status = 'pending'
				AND (s.id IS NULL OR s.status <> 'active')
				AND c.status IN ('pending', 'processing')",
				__( 'Recipient is no longer active; delivery cancelled.', 'mail-system' )
			)
		);

		if ( false === $cancelled ) {
			return 0;
		}

		$this->reconcile_campaigns( $campaign_ids );

		return (int) $cancelled;
	}

	/**
	 * Cancel all still-pending deliveries for one subscriber.
	 *
	 * Called immediately after an unsubscribe so an old scheduled campaign can
	 * never be revived if the address subscribes again later.
	 *
	 * @param int $subscriber_id Subscriber database ID.
	 * @return int Number of queue rows cancelled.
	 */
	public function cancel_pending_for_subscriber( int $subscriber_id ): int {
		if ( $subscriber_id < 1 ) {
			return 0;
		}

		$campaign_ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT DISTINCT q.campaign_id
				FROM {$this->queue_table} q
				INNER JOIN {$this->campaigns_table} c ON c.id = q.campaign_id
				WHERE q.subscriber_id = %d
				AND q.status = 'pending'
				AND c.status IN ('pending', 'processing')",
				$subscriber_id
			)
		);

		$campaign_ids = $this->normalize_campaign_ids( $campaign_ids );
		if ( empty( $campaign_ids ) ) {
			return 0;
		}

		$cancelled = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->queue_table} q
				INNER JOIN {$this->campaigns_table} c ON c.id = q.campaign_id
				SET q.status = 'cancelled',
					q.processing_started_at = NULL,
					q.error_message = %s
				WHERE q.subscriber_id = %d
				AND q.status = 'pending'
				AND c.status IN ('pending', 'processing')",
				__( 'Recipient unsubscribed before delivery; delivery cancelled.', 'mail-system' ),
				$subscriber_id
			)
		);

		if ( false === $cancelled ) {
			return 0;
		}

		$this->reconcile_campaigns( $campaign_ids );

		return (int) $cancelled;
	}

	/**
	 * Recalculate a campaign status from its queue rows.
	 *
	 * @param int $campaign_id Campaign database ID.
	 * @return void
	 */
	public function reconcile_campaign( int $campaign_id ): void {
		if ( $campaign_id < 1 ) {
			return;
		}

		$stats = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT c.status AS campaign_status, c.scheduled_at,
					COUNT(q.id) AS total,
					SUM(CASE WHEN q.status IN ('pending', 'processing') THEN 1 ELSE 0 END) AS open_count
				FROM {$this->campaigns_table} c
				LEFT JOIN {$this->queue_table} q ON q.campaign_id = c.id
				WHERE c.id = %d
				GROUP BY c.id, c.status, c.scheduled_at",
				$campaign_id
			)
		);

		if ( ! $stats || ! in_array( $stats->campaign_status, array( 'pending', 'processing' ), true ) ) {
			return;
		}

		$current_status = (string) $stats->campaign_status;
		$open_count     = (int) $stats->open_count;

		if ( $open_count > 0 ) {
			$new_status = 'processing';
			if ( 'pending' === $current_status && $stats->scheduled_at > current_time( 'mysql' ) ) {
				$new_status = 'pending';
			}

			if ( $new_status === $current_status ) {
				return;
			}

			$this->wpdb->update(
				$this->campaigns_table,
				array( 'status' => $new_status ),
				array(
					'id'     => $campaign_id,
					'status' => $current_status,
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
			return;
		}

		$this->wpdb->update(
			$this->campaigns_table,
			array(
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => $campaign_id,
				'status' => $current_status,
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Reconcile multiple campaigns.
	 *
	 * @param array $campaign_ids Campaign IDs.
	 * @return void
	 */
	private function reconcile_campaigns( array $campaign_ids ): void {
		foreach ( $campaign_ids as $campaign_id ) {
			$this->reconcile_campaign( $campaign_id );
		}
	}

	/**
	 * Normalize raw database IDs.
	 *
	 * @param array|null $campaign_ids Raw campaign IDs.
	 * @return array<int>
	 */
	private function normalize_campaign_ids( $campaign_ids ): array {
		$campaign_ids = array_map( 'intval', (array) $campaign_ids );
		$campaign_ids = array_filter( $campaign_ids );

		return array_values( array_unique( $campaign_ids ) );
	}
}
