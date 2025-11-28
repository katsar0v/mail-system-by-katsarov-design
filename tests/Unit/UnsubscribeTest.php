<?php
/**
 * Unsubscribe Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class UnsubscribeTest
 *
 * Tests for unsubscribe functionality in MSKD_Public class.
 */
class UnsubscribeTest extends TestCase {

    /**
     * Exception message used to prevent exit() in tests.
     */
    const TEST_COMPLETE_EXCEPTION = 'test_complete';

    /**
     * Public class instance.
     *
     * @var \MSKD_Public
     */
    protected $public;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();

        // Stub shortcode and action registration.
        Functions\stubs( array( 'add_shortcode' => null ) );
        Functions\stubs( array( 'add_action' => null ) );

        // Load the public class.
        require_once \MSKD_PLUGIN_DIR . 'public/class-public.php';

        $this->public = new \MSKD_Public();

        // Set default SERVER vars.
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
    }

    /**
     * Test valid token unsubscribes user.
     *
     * Note: This test verifies the database update is called. We throw an exception
     * after the update to prevent the code from reaching include/exit.
     */
    public function test_valid_token_unsubscribes_user(): void {
        $wpdb = $this->setup_wpdb_mock();

        $valid_token = 'abc123def456abc123def456abc12345'; // 32 chars.
        $_GET['mskd_unsubscribe'] = $valid_token;

        // Rate limit check - no previous attempts.
        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( false );

        Functions\expect( 'set_transient' )
            ->once()
            ->andReturn( true );

        // Valid subscriber found.
        $subscriber = (object) array(
            'id'     => 123,
            'email'  => 'user@example.com',
            'status' => 'active',
        );

        $wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( $subscriber );

        // Should update status to unsubscribed.
        // Throw exception after update to prevent include/exit.
        $update_called = false;
        $wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_mskd_subscribers',
                array( 'status' => 'unsubscribed' ),
                array( 'id' => 123 ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' )
            )
            ->andReturnUsing( function() use ( &$update_called ) {
                $update_called = true;
                // Throw to prevent reaching include/exit
                throw new \Exception( self::TEST_COMPLETE_EXCEPTION );
            } );
        
        try {
            $this->public->handle_unsubscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( self::TEST_COMPLETE_EXCEPTION, $e->getMessage() );
        }
        
        // Verify the update was called.
        $this->assertTrue( $update_called, 'Database update should be called to set status to unsubscribed' );
    }

    /**
     * Test invalid token returns error.
     */
    public function test_invalid_token_returns_error(): void {
        $wpdb = $this->setup_wpdb_mock();

        $invalid_token = 'validformat1234567890123456789ab'; // 32 chars but not in DB.
        $_GET['mskd_unsubscribe'] = $invalid_token;

        // Rate limit check.
        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( false );

        Functions\expect( 'set_transient' )
            ->once()
            ->andReturn( true );

        // No subscriber found.
        $wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( null );

        Functions\expect( 'wp_die' )
            ->once()
            ->with(
                Mockery::type( 'string' ),
                Mockery::type( 'string' ),
                Mockery::on(
                    function ( $args ) {
                        return $args['response'] === 400;
                    }
                )
            )
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'wp_die_called' );
                }
            );

        try {
            $this->public->handle_unsubscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'wp_die_called', $e->getMessage() );
        }
    }

    /**
     * Test invalid token format is rejected.
     */
    public function test_invalid_token_format_rejected(): void {
        $this->setup_wpdb_mock();

        // Token too short.
        $_GET['mskd_unsubscribe'] = 'shorttoken';

        Functions\expect( 'wp_die' )
            ->once()
            ->with(
                Mockery::type( 'string' ),
                Mockery::type( 'string' ),
                Mockery::on(
                    function ( $args ) {
                        return $args['response'] === 400;
                    }
                )
            )
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'wp_die_format_error' );
                }
            );

        try {
            $this->public->handle_unsubscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'wp_die_format_error', $e->getMessage() );
        }
    }

    /**
     * Test token with special characters is rejected.
     */
    public function test_token_with_special_chars_rejected(): void {
        $this->setup_wpdb_mock();

        // Token with special characters (32 chars but invalid).
        $_GET['mskd_unsubscribe'] = 'abc123!@#$%^abc123def456abc123de';

        Functions\expect( 'wp_die' )
            ->once()
            ->with(
                Mockery::type( 'string' ),
                Mockery::type( 'string' ),
                Mockery::on(
                    function ( $args ) {
                        return $args['response'] === 400;
                    }
                )
            )
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'wp_die_special_chars' );
                }
            );

        try {
            $this->public->handle_unsubscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'wp_die_special_chars', $e->getMessage() );
        }
    }

    /**
     * Test rate limiting prevents abuse.
     */
    public function test_rate_limiting_prevents_abuse(): void {
        $this->setup_wpdb_mock();

        $valid_token = 'abc123def456abc123def456abc12345';
        $_GET['mskd_unsubscribe'] = $valid_token;

        // Already at rate limit (10 attempts).
        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( 10 );

        Functions\expect( 'wp_die' )
            ->once()
            ->with(
                Mockery::type( 'string' ),
                Mockery::type( 'string' ),
                Mockery::on(
                    function ( $args ) {
                        return $args['response'] === 429; // Too Many Requests.
                    }
                )
            )
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'rate_limited' );
                }
            );

        try {
            $this->public->handle_unsubscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'rate_limited', $e->getMessage() );
        }
    }

    /**
     * Test rate limit counter is incremented.
     */
    public function test_rate_limit_counter_incremented(): void {
        $wpdb = $this->setup_wpdb_mock();

        $valid_token = 'abc123def456abc123def456abc12345';
        $_GET['mskd_unsubscribe'] = $valid_token;

        // 5 previous attempts.
        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( 5 );

        // Should increment to 6.
        Functions\expect( 'set_transient' )
            ->once()
            ->with(
                Mockery::type( 'string' ),
                6,
                5 * 60 // 5 minutes in seconds.
            )
            ->andReturn( true );

        $wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( null );

        Functions\expect( 'wp_die' )
            ->once()
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'wp_die_called' );
                }
            );

        try {
            $this->public->handle_unsubscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'wp_die_called', $e->getMessage() );
        }
    }

    /**
     * Test unsubscribe changes status to unsubscribed.
     *
     * Note: This test verifies the database update is called with correct data.
     * We throw an exception after the update to prevent include/exit.
     */
    public function test_unsubscribe_changes_status_to_unsubscribed(): void {
        $wpdb = $this->setup_wpdb_mock();

        $valid_token = 'abc123def456abc123def456abc12345';
        $_GET['mskd_unsubscribe'] = $valid_token;

        Functions\expect( 'get_transient' )
            ->once()
            ->andReturn( false );

        Functions\expect( 'set_transient' )
            ->once()
            ->andReturn( true );

        $subscriber = (object) array(
            'id'     => 999,
            'email'  => 'subscriber@example.com',
            'status' => 'active',
        );

        $wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( $subscriber );

        // Verify the exact update call.
        // Throw exception after update to prevent include/exit.
        $update_called = false;
        $wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_mskd_subscribers',
                array( 'status' => 'unsubscribed' ),
                array( 'id' => 999 ),
                array( '%s' ),
                array( '%d' )
            )
            ->andReturnUsing( function() use ( &$update_called ) {
                $update_called = true;
                // Throw to prevent reaching include/exit
                throw new \Exception( self::TEST_COMPLETE_EXCEPTION );
            } );
        
        try {
            $this->public->handle_unsubscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( self::TEST_COMPLETE_EXCEPTION, $e->getMessage() );
        }
        
        // Verify the update was called.
        $this->assertTrue( $update_called, 'Status should be updated to unsubscribed' );
    }

    /**
     * Test that no query param does nothing.
     */
    public function test_no_query_param_does_nothing(): void {
        $this->setup_wpdb_mock();

        // No unsubscribe param.
        unset( $_GET['mskd_unsubscribe'] );

        // Should return early without doing anything.
        Functions\expect( 'wp_die' )->never();
        Functions\expect( 'get_transient' )->never();

        $this->public->handle_unsubscribe();

        // If we get here without exceptions, the test passes.
        $this->assertTrue( true );
    }

    /**
     * Test empty token does nothing.
     */
    public function test_empty_token_does_nothing(): void {
        $this->setup_wpdb_mock();

        $_GET['mskd_unsubscribe'] = '';

        // Should return early.
        Functions\expect( 'wp_die' )->never();
        Functions\expect( 'get_transient' )->never();

        $this->public->handle_unsubscribe();

        $this->assertTrue( true );
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        unset( $_GET['mskd_unsubscribe'] );
        unset( $_SERVER['REMOTE_ADDR'] );

        parent::tearDown();
    }
}
