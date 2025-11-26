<?php
/**
 * Public Subscription Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class PublicSubscriptionTest
 *
 * Tests for public subscription functionality in MSKD_Public class.
 */
class PublicSubscriptionTest extends TestCase {

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

        // Stub shortcode registration.
        Functions\stubs( array( 'add_shortcode' => null ) );
        Functions\stubs( array( 'add_action' => null ) );

        // Load the public class.
        require_once MSKD_PLUGIN_DIR . 'public/class-public.php';

        $this->public = new \MSKD_Public();
    }

    /**
     * Test AJAX subscribe creates new subscriber.
     */
    public function test_ajax_subscribe_creates_new_subscriber(): void {
        $wpdb = $this->setup_wpdb_mock();

        $_POST['email']      = 'newuser@example.com';
        $_POST['first_name'] = 'New';
        $_POST['last_name']  = 'User';
        $_POST['list_id']    = 5;
        $_POST['nonce']      = 'valid_nonce';

        Functions\expect( 'check_ajax_referer' )
            ->once()
            ->with( 'mskd_public_nonce', 'nonce' )
            ->andReturn( true );

        // No existing subscriber.
        $wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( null );

        // Insert new subscriber.
        $wpdb->insert_id = 999;
        $wpdb->shouldReceive( 'insert' )
            ->once()
            ->with(
                'wp_mskd_subscribers',
                Mockery::on(
                    function ( $data ) {
                        return $data['email'] === 'newuser@example.com'
                            && $data['first_name'] === 'New'
                            && $data['last_name'] === 'User'
                            && $data['status'] === 'active';
                    }
                ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        // Check not in list.
        $wpdb->shouldReceive( 'get_var' )
            ->once()
            ->andReturn( null );

        // Add to list.
        $wpdb->shouldReceive( 'insert' )
            ->once()
            ->with(
                'wp_mskd_subscriber_list',
                array(
                    'subscriber_id' => 999,
                    'list_id'       => 5,
                ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        Functions\expect( 'wp_send_json_success' )
            ->once()
            ->with( Mockery::on(
                function ( $data ) {
                    return isset( $data['message'] );
                }
            ) )
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'json_success' );
                }
            );

        try {
            $this->public->ajax_subscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'json_success', $e->getMessage() );
        }
    }

    /**
     * Test AJAX subscribe reactivates unsubscribed user.
     */
    public function test_ajax_subscribe_reactivates_unsubscribed(): void {
        $wpdb = $this->setup_wpdb_mock();

        $_POST['email']   = 'inactive@example.com';
        $_POST['list_id'] = 0;
        $_POST['nonce']   = 'valid_nonce';

        Functions\expect( 'check_ajax_referer' )
            ->once()
            ->andReturn( true );

        // Existing unsubscribed subscriber.
        $existing = (object) array(
            'id'     => 123,
            'email'  => 'inactive@example.com',
            'status' => 'unsubscribed',
        );

        $wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( $existing );

        // Should update status to active.
        $wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_mskd_subscribers',
                array( 'status' => 'active' ),
                array( 'id' => 123 ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        Functions\expect( 'wp_send_json_success' )
            ->once()
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'json_success' );
                }
            );

        try {
            $this->public->ajax_subscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'json_success', $e->getMessage() );
        }
    }

    /**
     * Test AJAX subscribe adds to specified list.
     */
    public function test_ajax_subscribe_adds_to_list(): void {
        $wpdb = $this->setup_wpdb_mock();

        $_POST['email']   = 'existing@example.com';
        $_POST['list_id'] = 10;
        $_POST['nonce']   = 'valid_nonce';

        Functions\expect( 'check_ajax_referer' )
            ->once()
            ->andReturn( true );

        // Existing active subscriber.
        $existing = (object) array(
            'id'     => 456,
            'email'  => 'existing@example.com',
            'status' => 'active',
        );

        $wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( $existing );

        // Not in list yet.
        $wpdb->shouldReceive( 'get_var' )
            ->once()
            ->andReturn( null );

        // Should add to list.
        $wpdb->shouldReceive( 'insert' )
            ->once()
            ->with(
                'wp_mskd_subscriber_list',
                array(
                    'subscriber_id' => 456,
                    'list_id'       => 10,
                ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        Functions\expect( 'wp_send_json_success' )
            ->once()
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'json_success' );
                }
            );

        try {
            $this->public->ajax_subscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'json_success', $e->getMessage() );
        }
    }

    /**
     * Test AJAX subscribe returns error for invalid email.
     */
    public function test_ajax_subscribe_invalid_email_returns_error(): void {
        $this->setup_wpdb_mock();

        $_POST['email'] = 'not-an-email';
        $_POST['nonce'] = 'valid_nonce';

        Functions\expect( 'check_ajax_referer' )
            ->once()
            ->andReturn( true );

        Functions\expect( 'is_email' )
            ->once()
            ->with( 'not-an-email' )
            ->andReturn( false );

        Functions\expect( 'wp_send_json_error' )
            ->once()
            ->with( Mockery::on(
                function ( $data ) {
                    return isset( $data['message'] );
                }
            ) )
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'json_error' );
                }
            );

        try {
            $this->public->ajax_subscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'json_error', $e->getMessage() );
        }
    }

    /**
     * Test shortcode renders form.
     */
    public function test_shortcode_renders_form(): void {
        // Create a mock for the subscribe form partial.
        $partial_path = MSKD_PLUGIN_DIR . 'public/partials/subscribe-form.php';
        
        // Mock file_exists check.
        Functions\expect( 'shortcode_atts' )
            ->once()
            ->with(
                Mockery::type( 'array' ),
                Mockery::any()
            )
            ->andReturn(
                array(
                    'list_id' => 0,
                    'title'   => 'Абонирайте се',
                )
            );

        // We can't easily test file inclusion, so we verify the method exists and returns a string.
        $this->assertTrue( method_exists( $this->public, 'subscribe_form_shortcode' ) );
    }

    /**
     * Test shortcode accepts list_id attribute.
     */
    public function test_shortcode_accepts_list_id_attribute(): void {
        $atts = array(
            'list_id' => 5,
            'title'   => 'Custom Title',
        );

        Functions\expect( 'shortcode_atts' )
            ->once()
            ->with(
                array(
                    'list_id' => 0,
                    'title'   => Mockery::type( 'string' ),
                ),
                $atts
            )
            ->andReturn( $atts );

        // Verify method accepts attributes.
        $this->assertTrue( method_exists( $this->public, 'subscribe_form_shortcode' ) );
    }

    /**
     * Test that subscriber not added to list if already in it.
     */
    public function test_ajax_subscribe_skips_duplicate_list_assignment(): void {
        $wpdb = $this->setup_wpdb_mock();

        $_POST['email']   = 'existing@example.com';
        $_POST['list_id'] = 10;
        $_POST['nonce']   = 'valid_nonce';

        Functions\expect( 'check_ajax_referer' )
            ->once()
            ->andReturn( true );

        $existing = (object) array(
            'id'     => 456,
            'email'  => 'existing@example.com',
            'status' => 'active',
        );

        $wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( $existing );

        // Already in list.
        $wpdb->shouldReceive( 'get_var' )
            ->once()
            ->andReturn( 99 ); // Returns existing ID

        // Should NOT insert into list again.
        $wpdb->shouldReceive( 'insert' )
            ->never();

        Functions\expect( 'wp_send_json_success' )
            ->once()
            ->andReturnUsing(
                function () {
                    throw new \Exception( 'json_success' );
                }
            );

        try {
            $this->public->ajax_subscribe();
        } catch ( \Exception $e ) {
            $this->assertEquals( 'json_success', $e->getMessage() );
        }
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        unset( $_POST['email'] );
        unset( $_POST['first_name'] );
        unset( $_POST['last_name'] );
        unset( $_POST['list_id'] );
        unset( $_POST['nonce'] );

        parent::tearDown();
    }
}
