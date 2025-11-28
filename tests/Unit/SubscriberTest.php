<?php
/**
 * Subscriber Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Mockery;

/**
 * Class SubscriberTest
 *
 * Tests for subscriber management functionality in MSKD_Admin class.
 */
class SubscriberTest extends TestCase {

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

        // Set up a base wpdb mock BEFORE creating admin class.
        // Services capture $wpdb in their constructors.
        $this->setup_wpdb_mock();
        $this->wpdb->shouldIgnoreMissing();

        // Stub capability check.
        Functions\stubs( array( 'current_user_can' => true ) );

        // Load the admin class.
        require_once \MSKD_PLUGIN_DIR . 'admin/class-admin.php';

        $this->admin = new \MSKD_Admin();
    }

    /**
     * Test adding a subscriber with valid email.
     */
    public function test_add_subscriber_with_valid_email(): void {
        // Use the wpdb mock set up in setUp().
        $wpdb = $this->wpdb;

        // Set up POST data.
        $_POST['mskd_add_subscriber'] = true;
        $_POST['mskd_nonce']          = 'valid_nonce';
        $_POST['email']               = 'test@example.com';
        $_POST['first_name']          = 'John';
        $_POST['last_name']           = 'Doe';
        $_POST['status']              = 'active';
        $_POST['lists']               = array( 1, 2 );

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'valid_nonce', 'mskd_add_subscriber' )
            ->andReturn( true );

        // Check email doesn't exist - service calls get_var.
        $wpdb->shouldReceive( 'get_var' )
            ->atLeast()
            ->times( 1 )
            ->andReturn( null );

        // Insert subscriber.
        $wpdb->insert_id = 123;
        $wpdb->shouldReceive( 'insert' )
            ->once()
            ->with(
                'wp_mskd_subscribers',
                Mockery::on(
                    function ( $data ) {
                        return $data['email'] === 'test@example.com'
                            && $data['first_name'] === 'John'
                            && $data['last_name'] === 'Doe'
                            && $data['status'] === 'active'
                            && ! empty( $data['unsubscribe_token'] );
                    }
                ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        // Insert into lists (2 times).
        $wpdb->shouldReceive( 'insert' )
            ->twice()
            ->with(
                'wp_mskd_subscriber_list',
                Mockery::type( 'array' ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        $success_called = false;
        Functions\expect( 'add_settings_error' )
            ->once()
            ->with( 'mskd_messages', 'mskd_success', Mockery::any(), 'success' )
            ->andReturnUsing( function() use ( &$success_called ) {
                $success_called = true;
            } );

        $this->admin->handle_actions();
        
        $this->assertTrue( $success_called, 'add_settings_error should be called with success' );
    }

    /**
     * Test that invalid email is rejected.
     */
    public function test_add_subscriber_invalid_email_rejected(): void {
        // wpdb mock already set up in setUp().

        $_POST['mskd_add_subscriber'] = true;
        $_POST['mskd_nonce']          = 'valid_nonce';
        $_POST['email']               = 'invalid-email';
        $_POST['first_name']          = 'John';
        $_POST['last_name']           = 'Doe';
        $_POST['status']              = 'active';

        // Use when() to override stubs.
        Functions\when( 'wp_verify_nonce' )->justReturn( true );

        // Override is_email to return false.
        Functions\when( 'is_email' )->justReturn( false );

        $error_called = false;
        Functions\expect( 'add_settings_error' )
            ->once()
            ->with( 'mskd_messages', 'mskd_error', Mockery::any(), 'error' )
            ->andReturnUsing( function() use ( &$error_called ) {
                $error_called = true;
            } );

        $this->admin->handle_actions();
        
        $this->assertTrue( $error_called, 'add_settings_error should be called for invalid email' );
    }

    /**
     * Test that duplicate email is rejected.
     */
    public function test_add_subscriber_duplicate_email_rejected(): void {
        // Use the wpdb mock set up in setUp().
        $wpdb = $this->wpdb;

        $_POST['mskd_add_subscriber'] = true;
        $_POST['mskd_nonce']          = 'valid_nonce';
        $_POST['email']               = 'existing@example.com';
        $_POST['first_name']          = 'John';
        $_POST['last_name']           = 'Doe';
        $_POST['status']              = 'active';

        // Use when() to override stubs.
        Functions\when( 'wp_verify_nonce' )->justReturn( true );

        // Email already exists - service checks via get_var.
        $wpdb->shouldReceive( 'get_var' )
            ->atLeast()
            ->times( 1 )
            ->andReturn( 42 );

        $error_called = false;
        Functions\expect( 'add_settings_error' )
            ->once()
            ->with( 'mskd_messages', 'mskd_error', Mockery::any(), 'error' )
            ->andReturnUsing( function() use ( &$error_called ) {
                $error_called = true;
            } );

        $this->admin->handle_actions();
        
        $this->assertTrue( $error_called, 'add_settings_error should be called for duplicate email' );
    }

    /**
     * Test editing a subscriber updates data correctly.
     */
    public function test_edit_subscriber_updates_data(): void {
        // Use the wpdb mock set up in setUp().
        $wpdb = $this->wpdb;

        $_POST['mskd_edit_subscriber'] = true;
        $_POST['mskd_nonce']           = 'valid_nonce';
        $_POST['subscriber_id']        = 123;
        $_POST['email']                = 'updated@example.com';
        $_POST['first_name']           = 'Jane';
        $_POST['last_name']            = 'Smith';
        $_POST['status']               = 'inactive';
        $_POST['lists']                = array( 3 );

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'valid_nonce', 'mskd_edit_subscriber' )
            ->andReturn( true );

        // No duplicate email - service checks via get_var.
        $wpdb->shouldReceive( 'get_var' )
            ->atLeast()
            ->times( 1 )
            ->andReturn( null );

        // Update subscriber.
        $wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_mskd_subscribers',
                Mockery::on(
                    function ( $data ) {
                        return $data['email'] === 'updated@example.com'
                            && $data['first_name'] === 'Jane'
                            && $data['last_name'] === 'Smith'
                            && $data['status'] === 'inactive';
                    }
                ),
                array( 'id' => 123 ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        // Delete old list associations.
        $wpdb->shouldReceive( 'delete' )
            ->once()
            ->with( 'wp_mskd_subscriber_list', array( 'subscriber_id' => 123 ), Mockery::type( 'array' ) )
            ->andReturn( 1 );

        // Insert new list association.
        $wpdb->shouldReceive( 'insert' )
            ->once()
            ->with( 'wp_mskd_subscriber_list', Mockery::type( 'array' ), Mockery::type( 'array' ) )
            ->andReturn( 1 );

        Functions\expect( 'add_settings_error' )
            ->once()
            ->with( 'mskd_messages', 'mskd_success', Mockery::any(), 'success' );

        Functions\expect( 'wp_redirect' )
            ->once()
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'redirect_called' );
                }
            );

        try {
            $this->admin->handle_actions();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'redirect_called', $e->getMessage() );
        }
    }

    /**
     * Test deleting a subscriber removes from lists.
     */
    public function test_delete_subscriber_removes_from_lists(): void {
        // Use the wpdb mock set up in setUp().
        $wpdb = $this->wpdb;

        $_GET['action']   = 'delete_subscriber';
        $_GET['id']       = 123;
        $_GET['_wpnonce'] = 'valid_nonce';

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'valid_nonce', 'delete_subscriber_123' )
            ->andReturn( true );

        // Service calls delete in order: pivot table, queue, subscribers.
        // Allow flexible matching for all three delete calls.
        $wpdb->shouldReceive( 'delete' )
            ->with( 'wp_mskd_subscriber_list', Mockery::type( 'array' ), Mockery::type( 'array' ) )
            ->andReturn( 2 );

        $wpdb->shouldReceive( 'delete' )
            ->with( 'wp_mskd_queue', Mockery::type( 'array' ), Mockery::type( 'array' ) )
            ->andReturn( 0 );

        $wpdb->shouldReceive( 'delete' )
            ->with( 'wp_mskd_subscribers', Mockery::type( 'array' ), Mockery::type( 'array' ) )
            ->andReturn( 1 );

        Functions\expect( 'add_settings_error' )
            ->once()
            ->with( 'mskd_messages', 'mskd_success', Mockery::any(), 'success' );

        Functions\expect( 'wp_redirect' )
            ->once()
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'redirect_called' );
                }
            );

        try {
            $this->admin->handle_actions();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'redirect_called', $e->getMessage() );
        }
    }

    /**
     * Test deleting a subscriber removes pending queue items.
     */
    public function test_delete_subscriber_removes_pending_queue(): void {
        // Use the wpdb mock set up in setUp().
        $wpdb = $this->wpdb;

        $_GET['action']   = 'delete_subscriber';
        $_GET['id']       = 456;
        $_GET['_wpnonce'] = 'valid_nonce';

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'valid_nonce', 'delete_subscriber_456' )
            ->andReturn( true );

        // Service calls delete in order: pivot table, queue, subscribers.
        // Allow flexible matching for all three delete calls.
        $wpdb->shouldReceive( 'delete' )
            ->with( 'wp_mskd_subscriber_list', Mockery::type( 'array' ), Mockery::type( 'array' ) )
            ->andReturn( 0 );

        // Delete pending queue items - this is what we're testing.
        $wpdb->shouldReceive( 'delete' )
            ->with( 'wp_mskd_queue', Mockery::type( 'array' ), Mockery::type( 'array' ) )
            ->andReturn( 5 ); // 5 pending items removed

        $wpdb->shouldReceive( 'delete' )
            ->with( 'wp_mskd_subscribers', Mockery::type( 'array' ), Mockery::type( 'array' ) )
            ->andReturn( 1 );

        Functions\expect( 'add_settings_error' )
            ->once();

        Functions\expect( 'wp_redirect' )
            ->once()
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'redirect_called' );
                }
            );

        try {
            $this->admin->handle_actions();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'redirect_called', $e->getMessage() );
        }
    }

    /**
     * Test that invalid status defaults to active.
     */
    public function test_subscriber_status_validation(): void {
        // Use the wpdb mock set up in setUp().
        $wpdb = $this->wpdb;

        $_POST['mskd_add_subscriber'] = true;
        $_POST['mskd_nonce']          = 'valid_nonce';
        $_POST['email']               = 'test@example.com';
        $_POST['first_name']          = 'Test';
        $_POST['last_name']           = 'User';
        $_POST['status']              = 'invalid_status'; // Invalid status

        // Use when() to override stubs.
        Functions\when( 'wp_verify_nonce' )->justReturn( true );

        // No existing email - service checks via get_var.
        $wpdb->shouldReceive( 'get_var' )
            ->atLeast()
            ->times( 1 )
            ->andReturn( null );

        // Verify status defaults to 'active'.
        $wpdb->insert_id = 1;
        $wpdb->shouldReceive( 'insert' )
            ->once()
            ->with(
                'wp_mskd_subscribers',
                Mockery::on(
                    function ( $data ) {
                        return $data['status'] === 'active'; // Should default to active
                    }
                ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        $success_called = false;
        Functions\expect( 'add_settings_error' )
            ->once()
            ->with( 'mskd_messages', 'mskd_success', Mockery::any(), 'success' )
            ->andReturnUsing( function() use ( &$success_called ) {
                $success_called = true;
            } );

        $this->admin->handle_actions();
        
        $this->assertTrue( $success_called, 'add_settings_error should be called with success' );
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        unset( $_POST['mskd_add_subscriber'] );
        unset( $_POST['mskd_edit_subscriber'] );
        unset( $_POST['mskd_nonce'] );
        unset( $_POST['email'] );
        unset( $_POST['first_name'] );
        unset( $_POST['last_name'] );
        unset( $_POST['status'] );
        unset( $_POST['lists'] );
        unset( $_POST['subscriber_id'] );
        unset( $_GET['action'] );
        unset( $_GET['id'] );
        unset( $_GET['_wpnonce'] );

        parent::tearDown();
    }
}
