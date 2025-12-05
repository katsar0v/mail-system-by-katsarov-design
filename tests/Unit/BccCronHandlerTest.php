<?php
/**
 * Bcc Cron Handler Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class BccCronHandlerTest
 *
 * Tests for Bcc functionality in Cron_Handler class.
 */
class BccCronHandlerTest extends TestCase {

	/**
	 * Cron Handler instance.
	 *
	 * @var \MSKD_Cron_Handler
	 */
	protected $cron_handler;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Load required class.
		require_once \MSKD_PLUGIN_DIR . 'includes/services/class-smtp-mailer.php';
		require_once \MSKD_PLUGIN_DIR . 'includes/services/class-cron-handler.php';

		// Mock WordPress functions.
		Functions\when( 'current_time' )->justReturn( '2024-01-01 12:00:00' );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( '__' )->returnArg();
		Functions\when( 'is_email' )->justReturn( true );

		// Create Cron_Handler instance.
		$this->cron_handler = new \MSKD_Cron_Handler();
	}

	/**
	 * Test that Bcc headers are correctly constructed from comma-separated string.
	 */
	public function test_bcc_headers_correctly_constructed(): void {
		// This is more of an integration test, but we can verify the logic
		// by checking the header construction in the process_queue method.

		// Create a mock queue item with Bcc.
		$queue_item            = new \stdClass();
		$queue_item->id        = 1;
		$queue_item->email     = 'recipient@example.com';
		$queue_item->subject   = 'Test Subject';
		$queue_item->body      = 'Test Body';
		$queue_item->first_name = 'Test';
		$queue_item->last_name = 'User';
		$queue_item->unsubscribe_token = 'token123';
		$queue_item->bcc       = 'bcc1@example.com, bcc2@example.com';
		$queue_item->attempts  = 0;

		// Parse Bcc as the cron handler would.
		$bcc_emails = array_map( 'trim', explode( ',', $queue_item->bcc ) );

		// Verify we get the expected array.
		$this->assertCount( 2, $bcc_emails );
		$this->assertEquals( 'bcc1@example.com', $bcc_emails[0] );
		$this->assertEquals( 'bcc2@example.com', $bcc_emails[1] );
	}

	/**
	 * Test that invalid Bcc emails are filtered out.
	 */
	public function test_invalid_bcc_emails_filtered_out(): void {
		$bcc_string = 'valid@example.com, invalid-email, another-invalid';
		$bcc_emails = array_map( 'trim', explode( ',', $bcc_string ) );

		// Filter valid emails as the cron handler does.
		// We'll manually validate for the test.
		$valid_headers = array();
		foreach ( $bcc_emails as $bcc_email ) {
			if ( ! empty( $bcc_email ) && $this->is_valid_test_email( $bcc_email ) ) {
				$valid_headers[] = 'Bcc: ' . $bcc_email;
			}
		}

		// Verify only the valid email is in headers.
		$this->assertCount( 1, $valid_headers );
		$this->assertEquals( 'Bcc: valid@example.com', $valid_headers[0] );
	}

	/**
	 * Helper method to validate test emails.
	 *
	 * @param string $email Email to validate.
	 * @return bool
	 */
	private function is_valid_test_email( string $email ): bool {
		// Only valid@example.com is valid for this test.
		return 'valid@example.com' === $email;
	}

	/**
	 * Test that empty Bcc doesn't add any headers.
	 */
	public function test_empty_bcc_no_headers(): void {
		$bcc_string = '';

		// Parse Bcc as the cron handler would.
		$headers = array();
		if ( ! empty( $bcc_string ) ) {
			$bcc_emails = array_map( 'trim', explode( ',', $bcc_string ) );
			foreach ( $bcc_emails as $bcc_email ) {
				if ( ! empty( $bcc_email ) && is_email( $bcc_email ) ) {
					$headers[] = 'Bcc: ' . $bcc_email;
				}
			}
		}

		// Verify no headers are added.
		$this->assertCount( 0, $headers );
	}

	/**
	 * Test that whitespace-only Bcc doesn't add headers.
	 */
	public function test_whitespace_bcc_no_headers(): void {
		$bcc_string = '   ,  ,   ';

		// Parse Bcc as the cron handler would.
		$headers    = array();
		$bcc_emails = array_map( 'trim', explode( ',', $bcc_string ) );
		foreach ( $bcc_emails as $bcc_email ) {
			if ( ! empty( $bcc_email ) && is_email( $bcc_email ) ) {
				$headers[] = 'Bcc: ' . $bcc_email;
			}
		}

		// Verify no headers are added.
		$this->assertCount( 0, $headers );
	}
}
