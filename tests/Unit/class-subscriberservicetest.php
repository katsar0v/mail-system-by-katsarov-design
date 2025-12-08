<?php
/**
 * Subscriber Service Test
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use MSKD\Services\Subscriber_Service;
use Mockery;

/**
 * Class Subscriber_Service_Test
 *
 * Test batch processing functionality in Subscriber_Service.
 */
class Subscriber_Service_Test extends TestCase {

	/**
	 * Subscriber service instance.
	 *
	 * @var Subscriber_Service
	 */
	private $subscriber_service;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Set up wpdb mock.
		$this->setup_wpdb_mock();
		
		// Create subscriber service.
		$this->subscriber_service = new Subscriber_Service();
		
		// Replace wpdb with our mock.
		$reflection = new \ReflectionClass( $this->subscriber_service );
		$property = $reflection->getProperty( 'wpdb' );
		$property->setAccessible( true );
		$property->setValue( $this->subscriber_service, $this->wpdb );
		
		// Replace table property.
		$table_property = $reflection->getProperty( 'table' );
		$table_property->setAccessible( true );
		$table_property->setValue( $this->subscriber_service, 'wp_mskd_subscribers' );
	}

	/**
	 * Test batch_get_or_create with existing subscribers.
	 */
	public function test_batch_get_or_create_with_existing_subscribers() {
		$emails_data = array(
			array(
				'email' => 'existing1@example.com',
				'first_name' => 'Existing',
				'last_name' => 'One',
				'source' => 'external',
			),
			array(
				'email' => 'existing2@example.com',
				'first_name' => 'Existing',
				'last_name' => 'Two',
				'source' => 'external',
			),
		);

		// Mock wpdb get_results to return existing subscribers.
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					(object) array(
						'id' => 1,
						'email' => 'existing1@example.com',
						'first_name' => 'Existing',
						'last_name' => 'One',
						'status' => 'active',
					),
					(object) array(
						'id' => 2,
						'email' => 'existing2@example.com',
						'first_name' => 'Existing',
						'last_name' => 'Two',
						'status' => 'active',
					),
				)
			);

		// Note: prepare() is already mocked in create_wpdb_mock() with andReturnUsing().

		$result = $this->subscriber_service->batch_get_or_create( $emails_data );

		// Assert existing subscribers are returned.
		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'existing1@example.com', $result );
		$this->assertArrayHasKey( 'existing2@example.com', $result );
		$this->assertEquals( 1, $result['existing1@example.com']->id );
		$this->assertEquals( 2, $result['existing2@example.com']->id );
	}

	/**
	 * Test batch_get_or_create with new subscribers.
	 */
	public function test_batch_get_or_create_with_new_subscribers() {
		$emails_data = array(
			array(
				'email' => 'new1@example.com',
				'first_name' => 'New',
				'last_name' => 'One',
				'source' => 'external',
			),
			array(
				'email' => 'new2@example.com',
				'first_name' => 'New',
				'last_name' => 'Two',
				'source' => 'external',
			),
		);

		// Mock wpdb get_results to return no existing subscribers.
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		// Note: prepare() is already mocked in create_wpdb_mock() with andReturnUsing().

		// Mock wpdb insert for new subscribers.
		$insert_id = 0;
		$this->wpdb->shouldReceive( 'insert' )
			->twice()
			->andReturnUsing( function () use ( &$insert_id ) {
				++$insert_id;
				$this->wpdb->insert_id = $insert_id;
				return true;
			} );

		// Mock get_by_id for retrieving created subscribers.
		$this->wpdb->shouldReceive( 'get_row' )
			->twice()
			->andReturnUsing( function () use ( &$insert_id ) {
				static $call_count = 0;
				++$call_count;
				return (object) array(
					'id' => $call_count,
					'email' => "new{$call_count}@example.com",
					'first_name' => 'New',
					'last_name' => $call_count === 1 ? 'One' : 'Two',
					'status' => 'active',
				);
			} );

		$result = $this->subscriber_service->batch_get_or_create( $emails_data );

		// Assert new subscribers are created and returned.
		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'new1@example.com', $result );
		$this->assertArrayHasKey( 'new2@example.com', $result );
		$this->assertEquals( 1, $result['new1@example.com']->id );
		$this->assertEquals( 2, $result['new2@example.com']->id );
	}

	/**
	 * Test batch_get_by_ids with valid IDs.
	 */
	public function test_batch_get_by_ids_with_valid_ids() {
		$ids = array( 1, 2, 3 );

		// Mock wpdb get_results.
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn(
				array(
					(object) array(
						'id' => 1,
						'email' => 'test1@example.com',
						'first_name' => 'Test',
						'last_name' => 'One',
						'status' => 'active',
					),
					(object) array(
						'id' => 2,
						'email' => 'test2@example.com',
						'first_name' => 'Test',
						'last_name' => 'Two',
						'status' => 'active',
					),
					(object) array(
						'id' => 3,
						'email' => 'test3@example.com',
						'first_name' => 'Test',
						'last_name' => 'Three',
						'status' => 'active',
					),
				)
			);

		// Note: prepare() is already mocked in create_wpdb_mock() with andReturnUsing().

		$result = $this->subscriber_service->batch_get_by_ids( $ids );

		// Assert subscribers are returned indexed by ID.
		$this->assertCount( 3, $result );
		$this->assertArrayHasKey( 1, $result );
		$this->assertArrayHasKey( 2, $result );
		$this->assertArrayHasKey( 3, $result );
		$this->assertEquals( 'test1@example.com', $result[1]->email );
		$this->assertEquals( 'test2@example.com', $result[2]->email );
		$this->assertEquals( 'test3@example.com', $result[3]->email );
	}

	/**
	 * Test batch_get_by_ids with empty IDs.
	 */
	public function test_batch_get_by_ids_with_empty_ids() {
		$ids = array();

		$result = $this->subscriber_service->batch_get_by_ids( $ids );

		// Assert empty result.
		$this->assertEmpty( $result );
	}

	/**
	 * Test batch_get_by_ids with invalid IDs.
	 */
	public function test_batch_get_by_ids_with_invalid_ids() {
		$ids = array( 0, -1, 'invalid' );

		$result = $this->subscriber_service->batch_get_by_ids( $ids );

		// Assert empty result after filtering invalid IDs.
		$this->assertEmpty( $result );
	}

	/**
	 * Test batch_create with valid subscriber data.
	 */
	public function test_batch_create_with_valid_data() {
		$subscribers_data = array(
			array(
				'email' => 'batch1@example.com',
				'first_name' => 'Batch',
				'last_name' => 'One',
				'source' => 'external',
			),
			array(
				'email' => 'batch2@example.com',
				'first_name' => 'Batch',
				'last_name' => 'Two',
				'source' => 'external',
			),
		);

		// Mock wpdb insert with dynamic insert_id.
		$insert_id = 0;
		$this->wpdb->shouldReceive( 'insert' )
			->twice()
			->andReturnUsing( function () use ( &$insert_id ) {
				++$insert_id;
				$this->wpdb->insert_id = $insert_id;
				return true;
			} );

		$result = $this->subscriber_service->batch_create( $subscribers_data );

		// Assert IDs are returned.
		$this->assertCount( 2, $result );
		$this->assertEquals( 1, $result[0] );
		$this->assertEquals( 2, $result[1] );
	}

	/**
	 * Test batch_create with empty data.
	 */
	public function test_batch_create_with_empty_data() {
		$subscribers_data = array();

		$result = $this->subscriber_service->batch_create( $subscribers_data );

		// Assert empty result.
		$this->assertEmpty( $result );
	}
}