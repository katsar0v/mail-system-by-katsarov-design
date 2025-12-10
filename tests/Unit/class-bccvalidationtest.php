<?php
/**
 * Bcc Validation Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class BccValidationTest
 *
 * Tests for Bcc validation in Admin_Email class.
 */
class BccValidationTest extends TestCase {

	/**
	 * Admin Email instance.
	 *
	 * @var \MSKD\Admin\Admin_Email
	 */
	protected $admin_email;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Set up wpdb mock - Admin_Email creates Email_Service which needs wpdb.
		$this->setup_wpdb_mock();

		// Load required services and classes.
<<<<<<< HEAD
		require_once \MSKD_PLUGIN_DIR . 'includes/services/class-subscriber-service.php';
		require_once \MSKD_PLUGIN_DIR . 'includes/services/class-email-service.php';
=======
		require_once \MSKD_PLUGIN_DIR . 'includes/Services/class-subscriber-service.php';
		require_once \MSKD_PLUGIN_DIR . 'includes/Services/class-email-service.php';
>>>>>>> 0c090b1 (Add subscriber statistics box to admin subscribers page)
		require_once \MSKD_PLUGIN_DIR . 'includes/Admin/class-admin-email.php';

		// Mock WordPress functions.
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'add_settings_error' )->justReturn( null );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();

		// Create Admin_Email instance.
		$this->admin_email = new \MSKD\Admin\Admin_Email();
	}

	/**
	 * Test that empty Bcc returns true.
	 */
	public function test_empty_bcc_returns_true(): void {
		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $this->admin_email );
		$method     = $reflection->getMethod( 'validate_bcc_emails' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_email, '' );

		$this->assertTrue( $result );
	}

	/**
	 * Test that single valid email returns true.
	 */
	public function test_single_valid_email_returns_true(): void {
		Functions\when( 'is_email' )->justReturn( true );

		$reflection = new \ReflectionClass( $this->admin_email );
		$method     = $reflection->getMethod( 'validate_bcc_emails' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_email, 'valid@example.com' );

		$this->assertTrue( $result );
	}

	/**
	 * Test that multiple valid emails (comma-separated) returns true.
	 */
	public function test_multiple_valid_emails_returns_true(): void {
		Functions\when( 'is_email' )->justReturn( true );

		$reflection = new \ReflectionClass( $this->admin_email );
		$method     = $reflection->getMethod( 'validate_bcc_emails' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_email, 'email1@example.com, email2@example.com, email3@example.com' );

		$this->assertTrue( $result );
	}

	/**
	 * Test that single invalid email returns error message with the invalid email.
	 */
	public function test_single_invalid_email_returns_error_message(): void {
		Functions\when( 'is_email' )->justReturn( false );

		$reflection = new \ReflectionClass( $this->admin_email );
		$method     = $reflection->getMethod( 'validate_bcc_emails' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_email, 'invalid-email' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Invalid Bcc email address', $result );
		$this->assertStringContainsString( 'invalid-email', $result );
	}

	/**
	 * Test that mix of valid and invalid emails returns error for the first invalid one.
	 */
	public function test_mixed_emails_returns_error_for_first_invalid(): void {
		// Mock is_email to return true for valid@example.com and false for invalid-email.
		Functions\expect( 'is_email' )
			->andReturnUsing(
				function ( $email ) {
					return 'valid@example.com' === $email;
				}
			);

		$reflection = new \ReflectionClass( $this->admin_email );
		$method     = $reflection->getMethod( 'validate_bcc_emails' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->admin_email, 'valid@example.com, invalid-email' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Invalid Bcc email address', $result );
		$this->assertStringContainsString( 'invalid-email', $result );
	}

	/**
	 * Test that emails with extra whitespace are handled correctly.
	 */
	public function test_emails_with_whitespace_handled_correctly(): void {
		Functions\when( 'is_email' )->justReturn( true );

		$reflection = new \ReflectionClass( $this->admin_email );
		$method     = $reflection->getMethod( 'validate_bcc_emails' );
		$method->setAccessible( true );

		// Test with extra spaces around emails.
		$result = $method->invoke( $this->admin_email, '  email1@example.com  ,  email2@example.com  ' );

		$this->assertTrue( $result );
	}
}
