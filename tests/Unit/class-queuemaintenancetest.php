<?php
/**
 * Queue Maintenance Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Mockery;
use MSKD\Services\Queue_Maintenance;

/**
 * Tests queue cleanup and campaign reconciliation.
 */
class QueueMaintenanceTest extends TestCase {

	/**
	 * Queue maintenance service.
	 *
	 * @var Queue_Maintenance
	 */
	private $service;

	/**
	 * Set up the service and database mock.
	 */
	protected function setUp(): void {
		parent::setUp();
		require_once \MSKD_PLUGIN_DIR . 'includes/services/class-queue-maintenance.php';

		$wpdb          = $this->setup_wpdb_mock();
		$this->service = new Queue_Maintenance( $wpdb );
	}

	/**
	 * Historical pending rows for inactive recipients are cancelled and their
	 * campaigns are completed once no open queue rows remain.
	 */
	public function test_inactive_pending_rows_are_cancelled_and_campaigns_completed(): void {
		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->with(
				Mockery::on(
					function ( $query ) {
						return false !== strpos( $query, "q.status = 'pending'" )
							&& false !== strpos( $query, "s.status <> 'active'" )
							&& false !== strpos( $query, "c.status IN ('pending', 'processing')" );
					}
				)
			)
			->andReturn( array( '40', '41' ) );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with(
				Mockery::on(
					function ( $query ) {
						return false !== strpos( $query, 'UPDATE wp_mskd_queue q' )
							&& false !== strpos( $query, "q.status = 'cancelled'" )
							&& false !== strpos( $query, "s.status <> 'active'" );
					}
				)
			)
			->andReturn( 6 );

		$this->wpdb->shouldReceive( 'get_row' )
			->twice()
			->andReturn(
				(object) array(
					'campaign_status' => 'processing',
					'scheduled_at'    => '2026-08-09 10:00:00',
					'total'           => 664,
					'open_count'      => 0,
				)
			);

		$completed_campaigns = array();
		$this->wpdb->shouldReceive( 'update' )
			->twice()
			->with(
				'wp_mskd_campaigns',
				Mockery::on(
					function ( $data ) {
						return 'completed' === $data['status'] && isset( $data['completed_at'] );
					}
				),
				Mockery::on(
					function ( $where ) use ( &$completed_campaigns ) {
						$completed_campaigns[] = $where['id'];
						return 'processing' === $where['status'];
					}
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			)
			->andReturn( 1 );

		$this->assertSame( 6, $this->service->cancel_pending_for_inactive_subscribers() );
		sort( $completed_campaigns );
		$this->assertSame( array( 40, 41 ), $completed_campaigns );
	}

	/**
	 * The recurring cleanup performs no write when every pending recipient is active.
	 */
	public function test_inactive_cleanup_is_a_noop_when_no_rows_match(): void {
		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array() );
		$this->wpdb->shouldReceive( 'query' )->never();
		$this->wpdb->shouldReceive( 'update' )->never();

		$this->assertSame( 0, $this->service->cancel_pending_for_inactive_subscribers() );
	}

	/**
	 * Unsubscribing immediately cancels that subscriber's pending delivery and
	 * completes a campaign that has no remaining open rows.
	 */
	public function test_subscriber_cleanup_cancels_pending_rows_and_reconciles_campaign(): void {
		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->with( Mockery::on( fn( $query ) => false !== strpos( $query, 'q.subscriber_id = 123' ) ) )
			->andReturn( array( 7 ) );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with(
				Mockery::on(
					function ( $query ) {
						return false !== strpos( $query, "q.status = 'cancelled'" )
							&& false !== strpos( $query, 'q.subscriber_id = 123' );
					}
				)
			)
			->andReturn( 1 );

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn(
				(object) array(
					'campaign_status' => 'processing',
					'scheduled_at'    => '2026-08-11 10:00:00',
					'total'           => 658,
					'open_count'      => 0,
				)
			);

		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_mskd_campaigns',
				Mockery::on( fn( $data ) => 'completed' === $data['status'] ),
				array(
					'id'     => 7,
					'status' => 'processing',
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			)
			->andReturn( 1 );

		$this->assertSame( 1, $this->service->cancel_pending_for_subscriber( 123 ) );
	}

	/**
	 * Cancelling one recipient does not prematurely start a future campaign that
	 * still has other pending recipients.
	 */
	public function test_future_campaign_remains_pending_when_open_rows_remain(): void {
		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->andReturn( array( 12 ) );
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 );
		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn(
				(object) array(
					'campaign_status' => 'pending',
					'scheduled_at'    => '2999-01-01 00:00:00',
					'total'           => 10,
					'open_count'      => 9,
				)
			);
		$this->wpdb->shouldReceive( 'update' )->never();

		$this->assertSame( 1, $this->service->cancel_pending_for_subscriber( 123 ) );
	}
}
