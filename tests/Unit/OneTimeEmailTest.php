<?php
/**
 * One-Time Email Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class OneTimeEmailTest
 *
 * Tests for one-time email functionality in MSKD_Admin class.
 */
class OneTimeEmailTest extends TestCase {

    /**
     * Admin instance.
     *
     * @var \MSKD_Admin
     */
    protected $admin;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();

        // Load the admin class.
        require_once MSKD_PLUGIN_DIR . 'admin/class-admin.php';

        $this->admin = new \MSKD_Admin();
    }

    /**
     * Test that one-time email menu is registered.
     */
    public function test_one_time_email_menu_registered(): void {
        Functions\expect( 'add_menu_page' )->once()->andReturn( 'mskd-dashboard' );
        Functions\expect( 'add_submenu_page' )->times( 7 )->andReturn( true );

        $this->admin->register_menu();

        // Verify the expectations were met (implicit via Mockery)
        $this->assertTrue( true );
    }

    /**
     * Test one-time email validation rejects empty fields.
     */
    public function test_one_time_email_validation_rejects_empty_fields(): void {
        $_POST = array(
            'mskd_send_one_time_email' => 1,
            'mskd_nonce'               => 'test_nonce',
            'recipient_email'          => '',
            'recipient_name'           => '',
            'subject'                  => '',
            'body'                     => '',
        );

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'test_nonce', 'mskd_send_one_time_email' )
            ->andReturn( true );

        Functions\expect( 'current_user_can' )
            ->once()
            ->with( 'manage_options' )
            ->andReturn( true );

        $error_called = false;
        Functions\expect( 'add_settings_error' )
            ->once()
            ->andReturnUsing( function ( $setting, $code, $message, $type ) use ( &$error_called ) {
                $error_called = true;
                $this->assertEquals( 'mskd_messages', $setting );
                $this->assertEquals( 'mskd_error', $code );
                $this->assertEquals( 'error', $type );
            } );

        $this->admin->handle_actions();
        $this->assertTrue( $error_called, 'add_settings_error should be called for empty fields' );
    }

    /**
     * Test one-time email validation rejects invalid email.
     * Note: is_email is stubbed in TestCase to use filter_var validation.
     */
    public function test_one_time_email_validation_rejects_invalid_email(): void {
        $_POST = array(
            'mskd_send_one_time_email' => 1,
            'mskd_nonce'               => 'test_nonce',
            'recipient_email'          => 'not-an-email',
            'recipient_name'           => 'Test User',
            'subject'                  => 'Test Subject',
            'body'                     => 'Test Body',
        );

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'test_nonce', 'mskd_send_one_time_email' )
            ->andReturn( true );

        Functions\expect( 'current_user_can' )
            ->once()
            ->with( 'manage_options' )
            ->andReturn( true );

        $error_called = false;
        Functions\expect( 'add_settings_error' )
            ->once()
            ->andReturnUsing( function ( $setting, $code, $message, $type ) use ( &$error_called ) {
                $error_called = true;
                $this->assertEquals( 'mskd_messages', $setting );
                $this->assertEquals( 'mskd_error', $code );
                $this->assertEquals( 'error', $type );
            } );

        $this->admin->handle_actions();
        $this->assertTrue( $error_called, 'add_settings_error should be called for invalid email' );
    }

    /**
     * Test one-time email sends successfully.
     */
    public function test_one_time_email_sends_successfully(): void {
        $wpdb = $this->setup_wpdb_mock();

        $_POST = array(
            'mskd_send_one_time_email' => 1,
            'mskd_nonce'               => 'test_nonce',
            'recipient_email'          => 'user@example.com',
            'recipient_name'           => 'Test User',
            'subject'                  => 'Hello {recipient_name}!',
            'body'                     => 'Dear {recipient_name}, your email is {recipient_email}.',
        );

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'test_nonce', 'mskd_send_one_time_email' )
            ->andReturn( true );

        Functions\expect( 'current_user_can' )
            ->once()
            ->with( 'manage_options' )
            ->andReturn( true );

        Functions\expect( 'get_option' )
            ->with( 'mskd_settings', Mockery::type( 'array' ) )
            ->andReturn(
                array(
                    'from_name'  => 'Test Site',
                    'from_email' => 'noreply@example.com',
                    'reply_to'   => 'reply@example.com',
                )
            );

        // Mock wp_mail success.
        Functions\expect( 'wp_mail' )
            ->once()
            ->with(
                'user@example.com',
                'Hello Test User!',
                'Dear Test User, your email is user@example.com.',
                Mockery::type( 'array' )
            )
            ->andReturn( true );

        // Expect database insert for logging.
        $wpdb->shouldReceive( 'insert' )
            ->once()
            ->with(
                'wp_mskd_queue',
                Mockery::on(
                    function ( $data ) {
                        return $data['subscriber_id'] === 0
                            && $data['status'] === 'sent'
                            && strpos( $data['subject'], 'Test User' ) !== false;
                    }
                ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        $success_called = false;
        Functions\expect( 'add_settings_error' )
            ->once()
            ->andReturnUsing( function ( $setting, $code, $message, $type ) use ( &$success_called ) {
                $success_called = true;
                $this->assertEquals( 'mskd_messages', $setting );
                $this->assertEquals( 'mskd_success', $code );
                $this->assertEquals( 'success', $type );
            } );

        $this->admin->handle_actions();
        $this->assertTrue( $success_called, 'add_settings_error should be called for success' );
    }

    /**
     * Test one-time email logs failure.
     */
    public function test_one_time_email_logs_failure(): void {
        $wpdb = $this->setup_wpdb_mock();

        $_POST = array(
            'mskd_send_one_time_email' => 1,
            'mskd_nonce'               => 'test_nonce',
            'recipient_email'          => 'user@example.com',
            'recipient_name'           => 'Test User',
            'subject'                  => 'Test Subject',
            'body'                     => 'Test Body',
        );

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'test_nonce', 'mskd_send_one_time_email' )
            ->andReturn( true );

        Functions\expect( 'current_user_can' )
            ->once()
            ->with( 'manage_options' )
            ->andReturn( true );

        Functions\expect( 'get_option' )
            ->with( 'mskd_settings', Mockery::type( 'array' ) )
            ->andReturn( array() );

        // Mock wp_mail failure.
        Functions\expect( 'wp_mail' )
            ->once()
            ->andReturn( false );

        // Expect database insert with failed status.
        $wpdb->shouldReceive( 'insert' )
            ->once()
            ->with(
                'wp_mskd_queue',
                Mockery::on(
                    function ( $data ) {
                        return $data['subscriber_id'] === 0
                            && $data['status'] === 'failed'
                            && isset( $data['error_message'] );
                    }
                ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        $error_called = false;
        Functions\expect( 'add_settings_error' )
            ->once()
            ->andReturnUsing( function ( $setting, $code, $message, $type ) use ( &$error_called ) {
                $error_called = true;
                $this->assertEquals( 'mskd_messages', $setting );
                $this->assertEquals( 'mskd_error', $code );
                $this->assertEquals( 'error', $type );
            } );

        $this->admin->handle_actions();
        $this->assertTrue( $error_called, 'add_settings_error should be called for failure' );
    }

    /**
     * Test placeholder replacement in one-time email.
     */
    public function test_placeholder_replacement_in_one_time_email(): void {
        $wpdb = $this->setup_wpdb_mock();

        $_POST = array(
            'mskd_send_one_time_email' => 1,
            'mskd_nonce'               => 'test_nonce',
            'recipient_email'          => 'john@example.com',
            'recipient_name'           => 'John Doe',
            'subject'                  => 'Welcome {recipient_name}',
            'body'                     => 'Hello {recipient_name}, your email is {recipient_email}.',
        );

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->andReturn( true );

        Functions\expect( 'current_user_can' )
            ->once()
            ->andReturn( true );

        Functions\expect( 'get_option' )
            ->andReturn(
                array(
                    'from_name'  => 'Test',
                    'from_email' => 'test@example.com',
                    'reply_to'   => 'reply@example.com',
                )
            );

        // Capture the sent email content.
        $sent_subject = '';
        $sent_body    = '';

        Functions\expect( 'wp_mail' )
            ->once()
            ->with(
                'john@example.com',
                Mockery::capture( $sent_subject ),
                Mockery::capture( $sent_body ),
                Mockery::type( 'array' )
            )
            ->andReturn( true );

        $wpdb->shouldReceive( 'insert' )
            ->once()
            ->andReturn( 1 );

        Functions\expect( 'add_settings_error' )
            ->once();

        $this->admin->handle_actions();

        // Verify placeholders are replaced.
        $this->assertStringContainsString( 'John Doe', $sent_subject, 'Subject should contain recipient name' );
        $this->assertStringContainsString( 'John Doe', $sent_body, 'Body should contain recipient name' );
        $this->assertStringContainsString( 'john@example.com', $sent_body, 'Body should contain recipient email' );
    }
}

