<?php
/**
 * Bcc Email Service Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class BccEmailServiceTest
 *
 * Tests for Bcc functionality in Email_Service class.
 */
class BccEmailServiceTest extends TestCase {

	/**
	 * Email Service instance.
	 *
	 * @var \MSKD\Services\Email_Service
	 */
	protected $email_service;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Set up wpdb mock.
		$this->setup_wpdb_mock();

		// Load required services.
		require_once \MSKD_PLUGIN_DIR . 'includes/Services/class-subscriber-service.php';
		require_once \MSKD_PLUGIN_DIR . 'includes/Services/class-email-service.php';

		// Mock WordPress functions.
		Functions\when( 'wp_json_encode' )->returnArg();
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );

		// Mock mskd_current_time_normalized.
		Functions\when( 'mskd_current_time_normalized' )->justReturn( '2024-01-01 12:00:00' );

		// Create Email_Service instance.
		$this->email_service = new \MSKD\Services\Email_Service();
	}

	/**
	 * Test that queue_campaign() with Bcc stores it in database.
	 */
	public function test_queue_campaign_with_bcc_stores_in_database(): void {
		$bcc_value = 'admin@example.com, cc@example.com';

		// Mock wpdb->get_row for subscriber checks.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn(
				(object) array(
					'id'     => 1,
					'status' => 'active',
				)
			);

		// Mock wpdb->get_results for batch processing.
		$this->wpdb->shouldReceive( 'get_results' )
			->andReturn(
				array(
					(object) array(
						'id'     => 1,
						'email'  => 'subscriber@example.com',
						'status' => 'active',
					),
				)
			);

		// Mock wpdb insert to capture data.
		$captured_data = null;
		$this->wpdb->shouldReceive( 'insert' )
			->with(
				Mockery::pattern( '/mskd_campaigns$/' ),
				Mockery::on(
					function ( $data ) use ( &$captured_data, $bcc_value ) {
						$captured_data = $data;
						return isset( $data['bcc'] ) && $bcc_value === $data['bcc'];
					}
				),
				Mockery::any()
			)
			->andReturn( 1 );

		$this->wpdb->insert_id = 123;

		// Mock wpdb query for batch queue inserts.
		$this->wpdb->shouldReceive( 'query' )
			->andReturn( 1 );

		// Create campaign with Bcc.
		$result = $this->email_service->queue_campaign(
			array(
				'subject'     => 'Test Subject',
				'body'        => 'Test Body',
				'list_ids'    => array( 1, 2 ),
				'subscribers' => array(
					(object) array(
						'id'         => 1,
						'email'      => 'subscriber@example.com',
						'first_name' => 'Test',
						'last_name'  => 'User',
					),
				),
				'bcc'         => $bcc_value,
			)
		);

		// Verify campaign was created.
		$this->assertEquals( 123, $result );
		$this->assertNotNull( $captured_data );
		$this->assertEquals( $bcc_value, $captured_data['bcc'] );
	}

	/**
	 * Test that queue_campaign() without Bcc works as before (backward compatibility).
	 */
	public function test_queue_campaign_without_bcc_works_as_before(): void {
		// Mock wpdb->get_row for subscriber checks.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn(
				(object) array(
					'id'     => 1,
					'status' => 'active',
				)
			);

		// Mock wpdb->get_results for batch processing.
		$this->wpdb->shouldReceive( 'get_results' )
			->andReturn(
				array(
					(object) array(
						'id'     => 1,
						'email'  => 'subscriber@example.com',
						'status' => 'active',
					),
				)
			);

		// Mock wpdb insert.
		$captured_data = null;
		$this->wpdb->shouldReceive( 'insert' )
			->with(
				Mockery::pattern( '/mskd_campaigns$/' ),
				Mockery::on(
					function ( $data ) use ( &$captured_data ) {
						$captured_data = $data;
						// Bcc should be empty string when not provided.
						return isset( $data['bcc'] ) && '' === $data['bcc'];
					}
				),
				Mockery::any()
			)
			->andReturn( 1 );

		$this->wpdb->insert_id = 456;

		// Mock wpdb query for batch queue inserts.
		$this->wpdb->shouldReceive( 'query' )
			->andReturn( 1 );

		// Create campaign without Bcc.
		$result = $this->email_service->queue_campaign(
			array(
				'subject'     => 'Test Subject',
				'body'        => 'Test Body',
				'list_ids'    => array( 1 ),
				'subscribers' => array(
					(object) array(
						'id'         => 1,
						'email'      => 'subscriber@example.com',
						'first_name' => 'Test',
						'last_name'  => 'User',
					),
				),
			)
		);

		// Verify campaign was created.
		$this->assertEquals( 456, $result );
		$this->assertNotNull( $captured_data );
		$this->assertEquals( '', $captured_data['bcc'] );
	}

	/**
	 * Test that queue_one_time() with Bcc stores it in database.
	 */
	public function test_queue_one_time_with_bcc_stores_in_database(): void {
		$bcc_value = 'bcc@example.com';

		// Mock subscriber service methods.
		$subscriber_mock = Mockery::mock( '\MSKD\Services\Subscriber_Service' );
		$subscriber_mock->shouldReceive( 'get_or_create' )
			->once()
			->andReturn(
				(object) array(
					'id'     => 1,
					'status' => 'active',
				)
			);

		// Override the subscriber service in email service.
		$reflection = new \ReflectionClass( $this->email_service );
		$property   = $reflection->getProperty( 'subscriber_service' );
		$property->setAccessible( true );
		$property->setValue( $this->email_service, $subscriber_mock );

		// Mock wpdb insert to capture campaign data.
		$captured_campaign_data = null;
		$this->wpdb->shouldReceive( 'insert' )
			->with(
				Mockery::pattern( '/mskd_campaigns$/' ),
				Mockery::on(
					function ( $data ) use ( &$captured_campaign_data, $bcc_value ) {
						$captured_campaign_data = $data;
						return isset( $data['bcc'] ) && $bcc_value === $data['bcc'];
					}
				),
				Mockery::any()
			)
			->andReturn( 1 );

		$this->wpdb->insert_id = 789;

		// Mock wpdb insert for queue item.
		$this->wpdb->shouldReceive( 'insert' )
			->with(
				Mockery::pattern( '/mskd_queue$/' ),
				Mockery::any(),
				Mockery::any()
			)
			->andReturn( 1 );

		// Create one-time email with Bcc.
		$result = $this->email_service->queue_one_time(
			array(
				'recipient_email' => 'recipient@example.com',
				'recipient_name'  => 'Test Recipient',
				'subject'         => 'Test Subject',
				'body'            => 'Test Body',
				'bcc'             => $bcc_value,
			)
		);

		// Verify one-time email was queued (returns the queue item ID from last insert).
		$this->assertEquals( 789, $result );
		$this->assertNotNull( $captured_campaign_data );
		$this->assertEquals( $bcc_value, $captured_campaign_data['bcc'] );
	}
}
