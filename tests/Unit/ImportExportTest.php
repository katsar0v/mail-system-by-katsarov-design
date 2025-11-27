<?php
/**
 * Import/Export Service Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MSKD\Services\Import_Export_Service;

/**
 * Class ImportExportTest
 *
 * Tests for Import/Export service functionality.
 */
class ImportExportTest extends TestCase {

	/**
	 * Import/Export service instance.
	 *
	 * @var Import_Export_Service
	 */
	protected $service;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create a fresh wpdb mock with flexible prepare handling.
		$this->wpdb = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';
		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query, ...$args ) {
					// Handle the case where an array is passed as second argument.
					if ( count( $args ) === 1 && is_array( $args[0] ) ) {
						$args = $args[0];
					}
					// Simple replacement for testing.
					$result = $query;
					foreach ( $args as $arg ) {
						$replacement = is_string( $arg ) ? "'" . $arg . "'" : (string) $arg;
						$result = preg_replace( '/%[sd]/', $replacement, $result, 1 );
					}
					return $result;
				}
			);
		$this->wpdb->shouldIgnoreMissing();
		$GLOBALS['wpdb'] = $this->wpdb;

		// Load the service.
		$this->service = new Import_Export_Service();
	}

	/**
	 * Test export subscribers to CSV format.
	 */
	public function test_export_subscribers_csv_returns_valid_csv(): void {
		// Mock subscriber results.
		$subscribers = array(
			(object) array(
				'id'         => 1,
				'email'      => 'john@example.com',
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'status'     => 'active',
				'created_at' => '2024-01-15 10:00:00',
			),
			(object) array(
				'id'         => 2,
				'email'      => 'jane@example.com',
				'first_name' => 'Jane',
				'last_name'  => 'Smith',
				'status'     => 'inactive',
				'created_at' => '2024-01-16 11:00:00',
			),
		);

		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 2 );

		$this->wpdb->shouldReceive( 'get_results' )
			->andReturn( $subscribers );

		$this->wpdb->shouldReceive( 'get_col' )
			->andReturn( array() ); // No lists assigned.

		$csv = $this->service->export_subscribers_csv();

		// Check that CSV contains headers and data.
		$this->assertStringContainsString( 'email', $csv );
		$this->assertStringContainsString( 'first_name', $csv );
		$this->assertStringContainsString( 'john@example.com', $csv );
		$this->assertStringContainsString( 'Jane', $csv );
	}

	/**
	 * Test export subscribers to JSON format.
	 */
	public function test_export_subscribers_json_returns_valid_json(): void {
		// Mock subscriber results.
		$subscribers = array(
			(object) array(
				'id'         => 1,
				'email'      => 'john@example.com',
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'status'     => 'active',
				'created_at' => '2024-01-15 10:00:00',
			),
		);

		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 1 );

		$this->wpdb->shouldReceive( 'get_results' )
			->andReturn( $subscribers );

		$this->wpdb->shouldReceive( 'get_col' )
			->andReturn( array() );

		$json = $this->service->export_subscribers_json();

		// Check that JSON is valid.
		$data = json_decode( $json, true );
		$this->assertNotNull( $data );
		$this->assertIsArray( $data );
		$this->assertCount( 1, $data );
		$this->assertEquals( 'john@example.com', $data[0]['email'] );
	}

	/**
	 * Test export lists to CSV format.
	 */
	public function test_export_lists_csv_returns_valid_csv(): void {
		$lists = array(
			(object) array(
				'id'               => 1,
				'name'             => 'Newsletter',
				'description'      => 'Weekly newsletter',
				'subscriber_count' => 50,
				'created_at'       => '2024-01-01 00:00:00',
			),
		);

		$this->wpdb->shouldReceive( 'get_results' )
			->andReturn( $lists );

		$csv = $this->service->export_lists_csv();

		$this->assertStringContainsString( 'name', $csv );
		$this->assertStringContainsString( 'Newsletter', $csv );
		$this->assertStringContainsString( 'Weekly newsletter', $csv );
	}

	/**
	 * Test parse subscribers CSV validates email column.
	 */
	public function test_parse_subscribers_csv_requires_email_column(): void {
		// Create a temp file with missing email column.
		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, "first_name,last_name\nJohn,Doe" );

		$result = $this->service->parse_subscribers_csv( $temp_file );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'email', $result['error'] );

		unlink( $temp_file );
	}

	/**
	 * Test parse subscribers CSV with valid data.
	 */
	public function test_parse_subscribers_csv_with_valid_data(): void {
		$csv_content = "email,first_name,last_name,status\n";
		$csv_content .= "john@example.com,John,Doe,active\n";
		$csv_content .= "jane@example.com,Jane,Smith,inactive\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, $csv_content );

		$result = $this->service->parse_subscribers_csv( $temp_file );

		$this->assertTrue( $result['valid'] );
		$this->assertCount( 2, $result['rows'] );
		$this->assertEquals( 'john@example.com', $result['rows'][0]['email'] );
		$this->assertEquals( 'Jane', $result['rows'][1]['first_name'] );
		$this->assertEmpty( $result['errors'] );

		unlink( $temp_file );
	}

	/**
	 * Test parse subscribers CSV reports invalid emails.
	 */
	public function test_parse_subscribers_csv_reports_invalid_emails(): void {
		$csv_content = "email,first_name\n";
		$csv_content .= "invalid-email,John\n";
		$csv_content .= "jane@example.com,Jane\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, $csv_content );

		$result = $this->service->parse_subscribers_csv( $temp_file );

		$this->assertTrue( $result['valid'] );
		$this->assertCount( 1, $result['rows'] ); // Only valid row.
		$this->assertCount( 1, $result['errors'] ); // One error for invalid email.
		$this->assertStringContainsString( 'Invalid email', $result['errors'][0] );

		unlink( $temp_file );
	}

	/**
	 * Test parse subscribers JSON with valid data.
	 */
	public function test_parse_subscribers_json_with_valid_data(): void {
		$json_content = json_encode( array(
			array(
				'email'      => 'john@example.com',
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'status'     => 'active',
			),
			array(
				'email'      => 'jane@example.com',
				'first_name' => 'Jane',
			),
		) );

		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, $json_content );

		$result = $this->service->parse_subscribers_json( $temp_file );

		$this->assertTrue( $result['valid'] );
		$this->assertCount( 2, $result['rows'] );
		$this->assertEquals( 'john@example.com', $result['rows'][0]['email'] );
		$this->assertEquals( 'active', $result['rows'][0]['status'] );

		unlink( $temp_file );
	}

	/**
	 * Test parse subscribers JSON handles invalid JSON.
	 */
	public function test_parse_subscribers_json_handles_invalid_json(): void {
		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, 'not valid json {' );

		$result = $this->service->parse_subscribers_json( $temp_file );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'JSON', $result['error'] );

		unlink( $temp_file );
	}

	/**
	 * Test import subscribers creates new subscribers.
	 */
	public function test_import_subscribers_creates_new(): void {
		$rows = array(
			array(
				'email'      => 'new@example.com',
				'first_name' => 'New',
				'last_name'  => 'User',
				'status'     => 'active',
				'lists'      => '',
			),
		);

		// Mock no existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn( null );

		// Mock insert.
		$this->wpdb->insert_id = 1;
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result = $this->service->import_subscribers( $rows );

		$this->assertEquals( 1, $result['imported'] );
		$this->assertEquals( 0, $result['updated'] );
		$this->assertEquals( 0, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test import subscribers skips existing by default.
	 */
	public function test_import_subscribers_skips_existing_by_default(): void {
		$rows = array(
			array(
				'email'      => 'existing@example.com',
				'first_name' => 'Existing',
				'last_name'  => 'User',
				'status'     => 'active',
				'lists'      => '',
			),
		);

		// Mock existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn( (object) array( 'id' => 1, 'email' => 'existing@example.com' ) );

		$result = $this->service->import_subscribers( $rows );

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 0, $result['updated'] );
		$this->assertEquals( 1, $result['skipped'] );
	}

	/**
	 * Test import subscribers updates existing when option enabled.
	 */
	public function test_import_subscribers_updates_existing_when_enabled(): void {
		$rows = array(
			array(
				'email'      => 'existing@example.com',
				'first_name' => 'Updated',
				'last_name'  => 'Name',
				'status'     => 'inactive',
				'lists'      => '',
			),
		);

		// Mock existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn( (object) array( 'id' => 1, 'email' => 'existing@example.com' ) );

		// Mock update.
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->andReturn( 1 );

		$result = $this->service->import_subscribers( $rows, array( 'update_existing' => true ) );

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 1, $result['updated'] );
		$this->assertEquals( 0, $result['skipped'] );
	}

	/**
	 * Test parse lists CSV requires name column.
	 */
	public function test_parse_lists_csv_requires_name_column(): void {
		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, "description\nSome description" );

		$result = $this->service->parse_lists_csv( $temp_file );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'name', $result['error'] );

		unlink( $temp_file );
	}

	/**
	 * Test parse lists CSV with valid data.
	 */
	public function test_parse_lists_csv_with_valid_data(): void {
		$csv_content = "name,description\n";
		$csv_content .= "Newsletter,Weekly updates\n";
		$csv_content .= "Alerts,System alerts\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, $csv_content );

		$result = $this->service->parse_lists_csv( $temp_file );

		$this->assertTrue( $result['valid'] );
		$this->assertCount( 2, $result['rows'] );
		$this->assertEquals( 'Newsletter', $result['rows'][0]['name'] );
		$this->assertEquals( 'System alerts', $result['rows'][1]['description'] );

		unlink( $temp_file );
	}

	/**
	 * Test import lists creates new lists.
	 */
	public function test_import_lists_creates_new(): void {
		$rows = array(
			array(
				'name'        => 'New List',
				'description' => 'A new mailing list',
			),
		);

		// Mock no existing list.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn( null );

		// Mock insert.
		$this->wpdb->insert_id = 1;
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result = $this->service->import_lists( $rows );

		$this->assertEquals( 1, $result['imported'] );
		$this->assertEquals( 0, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test import lists skips existing.
	 */
	public function test_import_lists_skips_existing(): void {
		$rows = array(
			array(
				'name'        => 'Existing List',
				'description' => 'Description',
			),
		);

		// Mock existing list.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn( (object) array( 'id' => 1, 'name' => 'Existing List' ) );

		$result = $this->service->import_lists( $rows );

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 1, $result['skipped'] );
	}

	/**
	 * Test validate import file checks file size.
	 */
	public function test_validate_import_file_checks_size(): void {
		$file = array(
			'name'     => 'test.csv',
			'type'     => 'text/csv',
			'size'     => 6000000, // 6MB - exceeds limit.
			'tmp_name' => '/tmp/test.csv',
			'error'    => UPLOAD_ERR_OK,
		);

		$result = $this->service->validate_import_file( $file, 'csv' );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( '5MB', $result['error'] );
	}

	/**
	 * Test validate import file checks extension.
	 */
	public function test_validate_import_file_checks_extension(): void {
		$file = array(
			'name'     => 'test.txt',
			'type'     => 'text/plain',
			'size'     => 1000,
			'tmp_name' => '/tmp/test.txt',
			'error'    => UPLOAD_ERR_OK,
		);

		$result = $this->service->validate_import_file( $file, 'csv' );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'CSV', $result['error'] );
	}

	/**
	 * Test CSV handles UTF-8 BOM.
	 */
	public function test_parse_subscribers_csv_handles_utf8_bom(): void {
		// Create CSV with UTF-8 BOM.
		$csv_content = "\xEF\xBB\xBFemail,first_name\n";
		$csv_content .= "test@example.com,Test\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, $csv_content );

		$result = $this->service->parse_subscribers_csv( $temp_file );

		$this->assertTrue( $result['valid'] );
		$this->assertCount( 1, $result['rows'] );
		$this->assertEquals( 'test@example.com', $result['rows'][0]['email'] );

		unlink( $temp_file );
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		parent::tearDown();
	}
}
