<?php
/**
 * List Provider Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class ListProviderTest
 *
 * Tests for the MSKD_List_Provider class.
 */
class ListProviderTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Load the List Provider class using the global constant.
		require_once \MSKD_PLUGIN_DIR . 'includes/services/class-list-provider.php';
	}

	/**
	 * Test get_database_lists returns formatted lists.
	 */
	public function test_get_database_lists_returns_formatted_lists() {
		$this->setup_wpdb_mock();

		$db_lists = array(
			(object) array(
				'id'          => 1,
				'name'        => 'Newsletter',
				'description' => 'Main newsletter list',
				'created_at'  => '2024-01-01 00:00:00',
			),
			(object) array(
				'id'          => 2,
				'name'        => 'Promotions',
				'description' => 'Promotional emails',
				'created_at'  => '2024-01-02 00:00:00',
			),
		);

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $db_lists );

		$lists = \MSKD_List_Provider::get_database_lists();

		$this->assertCount( 2, $lists );
		$this->assertEquals( 'database', $lists[0]->source );
		$this->assertTrue( $lists[0]->is_editable );
		$this->assertNull( $lists[0]->provider );
		$this->assertEquals( 'Newsletter', $lists[0]->name );
	}

	/**
	 * Test get_database_lists returns empty array when no lists.
	 */
	public function test_get_database_lists_returns_empty_when_no_lists() {
		$this->setup_wpdb_mock();

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( null );

		$lists = \MSKD_List_Provider::get_database_lists();

		$this->assertIsArray( $lists );
		$this->assertEmpty( $lists );
	}

	/**
	 * Test get_external_lists returns registered lists.
	 */
	public function test_get_external_lists_returns_registered_lists() {
		$this->setup_wpdb_mock();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_lists', array() )
			->andReturn(
				array(
					array(
						'id'          => 'test_list',
						'name'        => 'Test External List',
						'description' => 'A test list',
						'provider'    => 'Test Plugin',
					),
				)
			);

		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'test_list' )
			->andReturn( 'test_list' );

		$lists = \MSKD_List_Provider::get_external_lists();

		$this->assertCount( 1, $lists );
		$this->assertEquals( 'ext_test_list', $lists[0]->id );
		$this->assertEquals( 'Test External List', $lists[0]->name );
		$this->assertEquals( 'external', $lists[0]->source );
		$this->assertFalse( $lists[0]->is_editable );
		$this->assertEquals( 'Test Plugin', $lists[0]->provider );
	}

	/**
	 * Test get_external_lists rejects invalid list definitions.
	 */
	public function test_get_external_lists_rejects_invalid_definitions() {
		$this->setup_wpdb_mock();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_lists', array() )
			->andReturn(
				array(
					array(
						// Missing required 'id' field
						'name' => 'Invalid List',
					),
					array(
						// Missing required 'name' field
						'id' => 'another_invalid',
					),
					array(
						// Valid
						'id'   => 'valid_list',
						'name' => 'Valid List',
					),
				)
			);

		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'valid_list' )
			->andReturn( 'valid_list' );

		$lists = \MSKD_List_Provider::get_external_lists();

		// Only the valid list should be included.
		$this->assertCount( 1, $lists );
		$this->assertEquals( 'ext_valid_list', $lists[0]->id );
	}

	/**
	 * Test get_external_lists returns empty when filter returns non-array.
	 */
	public function test_get_external_lists_handles_non_array_filter_result() {
		$this->setup_wpdb_mock();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_lists', array() )
			->andReturn( 'not an array' );

		$lists = \MSKD_List_Provider::get_external_lists();

		$this->assertIsArray( $lists );
		$this->assertEmpty( $lists );
	}

	/**
	 * Test get_list returns database list by numeric ID.
	 */
	public function test_get_list_returns_database_list_by_id() {
		$this->setup_wpdb_mock();

		$db_list = (object) array(
			'id'          => 5,
			'name'        => 'Test List',
			'description' => 'Description',
			'created_at'  => '2024-01-01 00:00:00',
		);

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $db_list );

		$list = \MSKD_List_Provider::get_list( 5 );

		$this->assertNotNull( $list );
		$this->assertEquals( 5, $list->id );
		$this->assertEquals( 'database', $list->source );
		$this->assertTrue( $list->is_editable );
	}

	/**
	 * Test get_list returns external list by prefixed ID.
	 */
	public function test_get_list_returns_external_list_by_prefixed_id() {
		$this->setup_wpdb_mock();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_lists', array() )
			->andReturn(
				array(
					array(
						'id'       => 'my_external_list',
						'name'     => 'My External List',
						'provider' => 'My Plugin',
					),
				)
			);

		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'my_external_list' )
			->andReturn( 'my_external_list' );

		$list = \MSKD_List_Provider::get_list( 'ext_my_external_list' );

		$this->assertNotNull( $list );
		$this->assertEquals( 'ext_my_external_list', $list->id );
		$this->assertEquals( 'external', $list->source );
		$this->assertFalse( $list->is_editable );
	}

	/**
	 * Test get_list returns null for non-existent list.
	 */
	public function test_get_list_returns_null_for_nonexistent_list() {
		$this->setup_wpdb_mock();

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		$list = \MSKD_List_Provider::get_list( 999 );

		$this->assertNull( $list );
	}

	/**
	 * Test is_list_editable returns false for external lists.
	 */
	public function test_is_list_editable_returns_false_for_external_lists() {
		$result = \MSKD_List_Provider::is_list_editable( 'ext_some_list' );

		$this->assertFalse( $result );
	}

	/**
	 * Test is_list_editable returns true for database lists by default.
	 */
	public function test_is_list_editable_returns_true_for_database_lists() {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_list_is_editable', true, 5 )
			->andReturn( true );

		$result = \MSKD_List_Provider::is_list_editable( 5 );

		$this->assertTrue( $result );
	}

	/**
	 * Test is_list_editable respects filter for database lists.
	 */
	public function test_is_list_editable_respects_filter() {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_list_is_editable', true, 3 )
			->andReturn( false );

		$result = \MSKD_List_Provider::is_list_editable( 3 );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_list_subscriber_count for database list.
	 */
	public function test_get_list_subscriber_count_for_database_list() {
		$this->setup_wpdb_mock();

		$list = (object) array(
			'id'     => 1,
			'name'   => 'Test',
			'source' => 'database',
		);

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 42 );

		$count = \MSKD_List_Provider::get_list_subscriber_count( $list );

		$this->assertEquals( 42, $count );
	}

	/**
	 * Test get_list_subscriber_count for external list with callback.
	 */
	public function test_get_list_subscriber_count_for_external_list_with_callback() {
		$list = (object) array(
			'id'                  => 'ext_test',
			'name'                => 'External Test',
			'source'              => 'external',
			'subscriber_callback' => function () {
				return array( 1, 2, 3, 4, 5 );
			},
		);

		$count = \MSKD_List_Provider::get_list_subscriber_count( $list );

		$this->assertEquals( 5, $count );
	}

	/**
	 * Test get_list_subscriber_count returns 0 for external list without callback.
	 */
	public function test_get_list_subscriber_count_returns_zero_without_callback() {
		$list = (object) array(
			'id'     => 'ext_test',
			'name'   => 'External Test',
			'source' => 'external',
		);

		$count = \MSKD_List_Provider::get_list_subscriber_count( $list );

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test get_all_lists merges database and external lists.
	 */
	public function test_get_all_lists_merges_database_and_external_lists() {
		$this->setup_wpdb_mock();

		$db_lists = array(
			(object) array(
				'id'          => 1,
				'name'        => 'DB List',
				'description' => '',
				'created_at'  => '2024-01-01 00:00:00',
			),
		);

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $db_lists );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_lists', array() )
			->andReturn(
				array(
					array(
						'id'   => 'ext_list',
						'name' => 'External List',
					),
				)
			);

		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'ext_list' )
			->andReturn( 'ext_list' );

		$all_lists = \MSKD_List_Provider::get_all_lists();

		$this->assertCount( 2, $all_lists );
		$this->assertEquals( 'database', $all_lists[0]->source );
		$this->assertEquals( 'external', $all_lists[1]->source );
	}

	/**
	 * Test list_exists returns true for existing database list.
	 */
	public function test_list_exists_returns_true_for_existing_list() {
		$this->setup_wpdb_mock();

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn(
				(object) array(
					'id'   => 1,
					'name' => 'Test',
				)
			);

		$exists = \MSKD_List_Provider::list_exists( 1 );

		$this->assertTrue( $exists );
	}

	/**
	 * Test list_exists returns false for non-existent list.
	 */
	public function test_list_exists_returns_false_for_nonexistent_list() {
		$this->setup_wpdb_mock();

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		$exists = \MSKD_List_Provider::list_exists( 999 );

		$this->assertFalse( $exists );
	}

	/**
	 * Test external list without callback returns empty array.
	 */
	public function test_external_list_without_callback_returns_empty() {
		$list = (object) array(
			'id'     => 'ext_no_callback',
			'name'   => 'No Callback List',
			'source' => 'external',
		);

		$subscriber_ids = \MSKD_List_Provider::get_list_subscriber_ids( $list );

		$this->assertIsArray( $subscriber_ids );
		$this->assertEmpty( $subscriber_ids );
	}

	/**
	 * Test external list with callback returning empty array.
	 */
	public function test_external_list_callback_returning_empty() {
		$list = (object) array(
			'id'                  => 'ext_empty_list',
			'name'                => 'Empty List',
			'source'              => 'external',
			'subscriber_callback' => function () {
				return array();
			},
		);

		$subscriber_ids = \MSKD_List_Provider::get_list_subscriber_ids( $list );

		$this->assertIsArray( $subscriber_ids );
		$this->assertEmpty( $subscriber_ids );
	}

	// =========================================================================
	// External Subscribers Tests
	// =========================================================================

	/**
	 * Test is_external_id correctly identifies external IDs.
	 */
	public function test_is_external_id_identifies_external_ids() {
		$this->assertTrue( \MSKD_List_Provider::is_external_id( 'ext_user_123' ) );
		$this->assertTrue( \MSKD_List_Provider::is_external_id( 'ext_woo_customer' ) );
		$this->assertFalse( \MSKD_List_Provider::is_external_id( 123 ) );
		$this->assertFalse( \MSKD_List_Provider::is_external_id( '123' ) );
		$this->assertFalse( \MSKD_List_Provider::is_external_id( 'user_123' ) );
	}

	/**
	 * Test get_external_subscribers returns registered subscribers.
	 */
	public function test_get_external_subscribers_returns_registered_subscribers() {
		$this->setup_wpdb_mock();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_subscribers', array(), Mockery::any() )
			->andReturn(
				array(
					array(
						'id'         => 'wp_user_1',
						'email'      => 'user1@example.com',
						'first_name' => 'John',
						'last_name'  => 'Doe',
						'status'     => 'active',
						'provider'   => 'WordPress',
					),
					array(
						'id'         => 'wp_user_2',
						'email'      => 'user2@example.com',
						'first_name' => 'Jane',
						'last_name'  => 'Smith',
						'status'     => 'active',
						'provider'   => 'WordPress',
					),
				)
			);

		Functions\expect( 'is_email' )->andReturn( true );
		Functions\expect( 'sanitize_key' )->andReturnUsing(
			function ( $key ) {
				return $key;
			}
		);
		Functions\expect( 'sanitize_email' )->andReturnUsing(
			function ( $email ) {
				return $email;
			}
		);
		Functions\expect( 'sanitize_text_field' )->andReturnUsing(
			function ( $str ) {
				return $str;
			}
		);
		Functions\expect( 'wp_salt' )->andReturn( 'test_salt' );

		$subscribers = \MSKD_List_Provider::get_external_subscribers();

		$this->assertCount( 2, $subscribers );
		$this->assertEquals( 'ext_wp_user_1', $subscribers[0]->id );
		$this->assertEquals( 'user1@example.com', $subscribers[0]->email );
		$this->assertEquals( 'external', $subscribers[0]->source );
		$this->assertFalse( $subscribers[0]->is_editable );
		$this->assertEquals( 'WordPress', $subscribers[0]->provider );
	}

	/**
	 * Test get_external_subscribers filters by status.
	 */
	public function test_get_external_subscribers_filters_by_status() {
		$this->setup_wpdb_mock();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_subscribers', array(), Mockery::any() )
			->andReturn(
				array(
					array(
						'id'     => 'user_1',
						'email'  => 'active@example.com',
						'status' => 'active',
					),
					array(
						'id'     => 'user_2',
						'email'  => 'inactive@example.com',
						'status' => 'inactive',
					),
				)
			);

		Functions\expect( 'is_email' )->andReturn( true );
		Functions\expect( 'sanitize_key' )->andReturnUsing(
			function ( $key ) {
				return $key;
			}
		);
		Functions\expect( 'sanitize_email' )->andReturnUsing(
			function ( $email ) {
				return $email;
			}
		);
		Functions\expect( 'sanitize_text_field' )->andReturnUsing(
			function ( $str ) {
				return $str;
			}
		);
		Functions\expect( 'wp_salt' )->andReturn( 'test_salt' );

		$subscribers = \MSKD_List_Provider::get_external_subscribers( array( 'status' => 'active' ) );

		$this->assertCount( 1, $subscribers );
		$this->assertEquals( 'active@example.com', $subscribers[0]->email );
	}

	/**
	 * Test get_external_subscribers rejects invalid emails.
	 */
	public function test_get_external_subscribers_rejects_invalid_emails() {
		$this->setup_wpdb_mock();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_subscribers', array(), Mockery::any() )
			->andReturn(
				array(
					array(
						'id'    => 'user_1',
						'email' => 'invalid-email',
					),
				)
			);

		Functions\expect( 'is_email' )->with( 'invalid-email' )->andReturn( false );
		Functions\expect( 'sanitize_key' )->andReturnUsing(
			function ( $key ) {
				return $key;
			}
		);
		Functions\expect( 'sanitize_email' )->andReturnUsing(
			function ( $email ) {
				return $email;
			}
		);

		$subscribers = \MSKD_List_Provider::get_external_subscribers();

		$this->assertEmpty( $subscribers );
	}

	/**
	 * Test is_subscriber_editable returns false for external subscribers.
	 */
	public function test_is_subscriber_editable_returns_false_for_external() {
		$editable = \MSKD_List_Provider::is_subscriber_editable( 'ext_user_123' );

		$this->assertFalse( $editable );
	}

	/**
	 * Test is_subscriber_editable returns true for database subscribers.
	 */
	public function test_is_subscriber_editable_returns_true_for_database() {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_subscriber_is_editable', true, 123 )
			->andReturn( true );

		$editable = \MSKD_List_Provider::is_subscriber_editable( 123 );

		$this->assertTrue( $editable );
	}

	/**
	 * Test get_subscriber returns external subscriber.
	 */
	public function test_get_subscriber_returns_external_subscriber() {
		$this->setup_wpdb_mock();

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_subscribers', array(), Mockery::any() )
			->andReturn(
				array(
					array(
						'id'         => 'crm_contact_1',
						'email'      => 'contact@example.com',
						'first_name' => 'Test',
						'last_name'  => 'Contact',
					),
				)
			);

		Functions\expect( 'is_email' )->andReturn( true );
		Functions\expect( 'sanitize_key' )->andReturnUsing(
			function ( $key ) {
				return $key;
			}
		);
		Functions\expect( 'sanitize_email' )->andReturnUsing(
			function ( $email ) {
				return $email;
			}
		);
		Functions\expect( 'sanitize_text_field' )->andReturnUsing(
			function ( $str ) {
				return $str;
			}
		);
		Functions\expect( 'wp_salt' )->andReturn( 'test_salt' );

		$subscriber = \MSKD_List_Provider::get_subscriber( 'ext_crm_contact_1' );

		$this->assertNotNull( $subscriber );
		$this->assertEquals( 'contact@example.com', $subscriber->email );
		$this->assertEquals( 'external', $subscriber->source );
	}

	/**
	 * Test get_subscriber returns database subscriber.
	 */
	public function test_get_subscriber_returns_database_subscriber() {
		$this->setup_wpdb_mock();

		$db_subscriber = (object) array(
			'id'         => 42,
			'email'      => 'db@example.com',
			'first_name' => 'Database',
			'last_name'  => 'User',
			'status'     => 'active',
		);

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->with(
				Mockery::on(
					function ( $query ) {
						return strpos( $query, 'mskd_subscribers' ) !== false && strpos( $query, '42' ) !== false;
					}
				)
			)
			->andReturn( $db_subscriber );

		$subscriber = \MSKD_List_Provider::get_subscriber( 42 );

		$this->assertNotNull( $subscriber );
		$this->assertEquals( 'db@example.com', $subscriber->email );
		$this->assertEquals( 'database', $subscriber->source );
		$this->assertTrue( $subscriber->is_editable );
	}

	/**
	 * Test get_total_subscriber_count includes external subscribers.
	 */
	public function test_get_total_subscriber_count_includes_external() {
		$this->setup_wpdb_mock();

		// Database count
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 10 );

		// External subscribers
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'mskd_register_external_subscribers', array(), Mockery::any() )
			->andReturn(
				array(
					array(
						'id'    => 'ext_1',
						'email' => 'ext1@example.com',
					),
					array(
						'id'    => 'ext_2',
						'email' => 'ext2@example.com',
					),
					array(
						'id'    => 'ext_3',
						'email' => 'ext3@example.com',
					),
				)
			);

		Functions\expect( 'is_email' )->andReturn( true );
		Functions\expect( 'sanitize_key' )->andReturnUsing(
			function ( $key ) {
				return $key;
			}
		);
		Functions\expect( 'sanitize_email' )->andReturnUsing(
			function ( $email ) {
				return $email;
			}
		);
		Functions\expect( 'sanitize_text_field' )->andReturnUsing(
			function ( $str ) {
				return $str;
			}
		);
		Functions\expect( 'wp_salt' )->andReturn( 'test_salt' );

		$total = \MSKD_List_Provider::get_total_subscriber_count();

		$this->assertEquals( 13, $total ); // 10 database + 3 external.
	}

	/**
	 * Test get_list_subscribers_full returns complete subscriber objects.
	 */
	public function test_get_list_subscribers_full_returns_complete_objects() {
		$this->setup_wpdb_mock();

		$db_subscribers = array(
			(object) array(
				'id'                => 1,
				'email'             => 'sub1@example.com',
				'first_name'        => 'Sub',
				'last_name'         => 'One',
				'status'            => 'active',
				'unsubscribe_token' => 'token1',
			),
		);

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with(
				Mockery::on(
					function ( $query ) {
						return strpos( $query, 'mskd_subscribers' ) !== false
						&& strpos( $query, 'mskd_subscriber_list' ) !== false
						&& strpos( $query, "status = 'active'" ) !== false;
					}
				)
			)
			->andReturn( $db_subscribers );

		$list = (object) array(
			'id'     => 1,
			'name'   => 'Test List',
			'source' => 'database',
		);

		$subscribers = \MSKD_List_Provider::get_list_subscribers_full( $list );

		$this->assertCount( 1, $subscribers );
		$this->assertEquals( 'sub1@example.com', $subscribers[0]->email );
		$this->assertEquals( 'database', $subscribers[0]->source );
	}
}
