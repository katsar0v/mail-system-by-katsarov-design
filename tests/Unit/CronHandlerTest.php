<?php
/**
 * Cron Handler Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class CronHandlerTest
 *
 * Tests for MSKD_Cron_Handler class.
 */
class CronHandlerTest extends TestCase {

    /**
     * Cron handler instance.
     *
     * @var \MSKD_Cron_Handler
     */
    protected $cron_handler;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();

        // Load the cron handler class.
        require_once MSKD_PLUGIN_DIR . 'includes/services/class-cron-handler.php';

        $this->cron_handler = new \MSKD_Cron_Handler();
    }

    /**
     * Test that process_queue sends pending emails.
     */
    public function test_process_queue_sends_pending_emails(): void {
        $wpdb = $this->setup_wpdb_mock();

        // Mock queue items.
        $queue_items = array(
            (object) array(
                'id'                => 1,
                'subscriber_id'     => 100,
                'email'             => 'user1@example.com',
                'first_name'        => 'User',
                'last_name'         => 'One',
                'subject'           => 'Test Subject',
                'body'              => 'Test Body',
                'status'            => 'pending',
                'attempts'          => 0,
                'unsubscribe_token' => 'abc123def456abc123def456abc12345',
            ),
        );

        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( $queue_items );

        // Mark as processing.
        $wpdb->shouldReceive( 'update' )
            ->twice() // Once for processing, once for sent
            ->andReturn( 1 );

        // Mock settings.
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
                'user1@example.com',
                Mockery::type( 'string' ),
                Mockery::type( 'string' ),
                Mockery::type( 'array' )
            )
            ->andReturn( true );

        $this->cron_handler->process_queue();
    }

    /**
     * Test that process_queue respects batch size.
     */
    public function test_process_queue_respects_batch_size(): void {
        $wpdb = $this->setup_wpdb_mock();

        // Verify the SQL query includes the batch size limit.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on(
                function ( $query ) {
                    // The query should contain LIMIT with MSKD_BATCH_SIZE (10).
                    return strpos( $query, 'LIMIT' ) !== false;
                }
            ) )
            ->andReturn( array() );

        $this->cron_handler->process_queue();
    }

    /**
     * Test that process_queue skips inactive subscribers.
     */
    public function test_process_queue_skips_inactive_subscribers(): void {
        $wpdb = $this->setup_wpdb_mock();

        // Verify the SQL query filters by active status.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on(
                function ( $query ) {
                    // Query should filter for active subscribers only.
                    return strpos( $query, "s.status = 'active'" ) !== false;
                }
            ) )
            ->andReturn( array() );

        $this->cron_handler->process_queue();
    }

    /**
     * Test that successful email is marked as sent.
     */
    public function test_process_queue_marks_sent_on_success(): void {
        $wpdb = $this->setup_wpdb_mock();

        $queue_items = array(
            (object) array(
                'id'                => 1,
                'subscriber_id'     => 100,
                'email'             => 'user@example.com',
                'first_name'        => 'Test',
                'last_name'         => 'User',
                'subject'           => 'Subject',
                'body'              => 'Body',
                'status'            => 'pending',
                'attempts'          => 0,
                'unsubscribe_token' => 'abc123def456abc123def456abc12345',
            ),
        );

        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( $queue_items );

        // First update: mark as processing.
        $wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_mskd_queue',
                Mockery::on(
                    function ( $data ) {
                        return $data['status'] === 'processing';
                    }
                ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        // Second update: mark as sent.
        $wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_mskd_queue',
                Mockery::on(
                    function ( $data ) {
                        return $data['status'] === 'sent' && isset( $data['sent_at'] );
                    }
                ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        Functions\expect( 'get_option' )
            ->andReturn( array() );

        Functions\expect( 'wp_mail' )
            ->once()
            ->andReturn( true );

        $this->cron_handler->process_queue();
    }

    /**
     * Test that failed email is marked as failed.
     */
    public function test_process_queue_marks_failed_on_error(): void {
        $wpdb = $this->setup_wpdb_mock();

        $queue_items = array(
            (object) array(
                'id'                => 1,
                'subscriber_id'     => 100,
                'email'             => 'user@example.com',
                'first_name'        => 'Test',
                'last_name'         => 'User',
                'subject'           => 'Subject',
                'body'              => 'Body',
                'status'            => 'pending',
                'attempts'          => 0,
                'unsubscribe_token' => 'abc123def456abc123def456abc12345',
            ),
        );

        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( $queue_items );

        // First update: mark as processing.
        $wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_mskd_queue',
                Mockery::on(
                    function ( $data ) {
                        return $data['status'] === 'processing';
                    }
                ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        // Second update: mark as failed.
        $wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_mskd_queue',
                Mockery::on(
                    function ( $data ) {
                        return $data['status'] === 'failed' && isset( $data['error_message'] );
                    }
                ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        Functions\expect( 'get_option' )
            ->andReturn( array() );

        // wp_mail fails.
        Functions\expect( 'wp_mail' )
            ->once()
            ->andReturn( false );

        $this->cron_handler->process_queue();
    }

    /**
     * Test placeholder replacement in email content.
     */
    public function test_placeholder_replacement(): void {
        $wpdb = $this->setup_wpdb_mock();

        $queue_items = array(
            (object) array(
                'id'                => 1,
                'subscriber_id'     => 100,
                'email'             => 'john@example.com',
                'first_name'        => 'John',
                'last_name'         => 'Doe',
                'subject'           => 'Hello {first_name}!',
                'body'              => 'Dear {first_name} {last_name}, your email is {email}. Click here to {unsubscribe_link}',
                'status'            => 'pending',
                'attempts'          => 0,
                'unsubscribe_token' => 'testtoken123456789012345678901234',
            ),
        );

        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( $queue_items );

        $wpdb->shouldReceive( 'update' )
            ->twice()
            ->andReturn( 1 );

        Functions\expect( 'get_option' )
            ->andReturn(
                array(
                    'from_name'  => 'Test',
                    'from_email' => 'test@example.com',
                    'reply_to'   => 'reply@example.com',
                )
            );

        // Capture the actual email content.
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

        $this->cron_handler->process_queue();

        // Verify placeholders are replaced.
        $this->assertStringContainsString( 'John', $sent_subject, 'Subject should contain first name' );
        $this->assertStringContainsString( 'John', $sent_body, 'Body should contain first name' );
        $this->assertStringContainsString( 'Doe', $sent_body, 'Body should contain last name' );
        $this->assertStringContainsString( 'john@example.com', $sent_body, 'Body should contain email' );
        $this->assertStringContainsString( 'testtoken123456789012345678901234', $sent_body, 'Body should contain unsubscribe token in URL' );
    }

    /**
     * Test that attempts counter is incremented.
     */
    public function test_attempts_counter_incremented(): void {
        $wpdb = $this->setup_wpdb_mock();

        $queue_items = array(
            (object) array(
                'id'                => 1,
                'subscriber_id'     => 100,
                'email'             => 'user@example.com',
                'first_name'        => 'Test',
                'last_name'         => 'User',
                'subject'           => 'Subject',
                'body'              => 'Body',
                'status'            => 'pending',
                'attempts'          => 2, // Already tried twice.
                'unsubscribe_token' => 'abc123def456abc123def456abc12345',
            ),
        );

        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( $queue_items );

        // Verify attempts is incremented.
        $wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_mskd_queue',
                Mockery::on(
                    function ( $data ) {
                        return $data['attempts'] === 3; // Should be 2 + 1.
                    }
                ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' ),
                Mockery::type( 'array' )
            )
            ->andReturn( 1 );

        $wpdb->shouldReceive( 'update' )
            ->once()
            ->andReturn( 1 );

        Functions\expect( 'get_option' )->andReturn( array() );
        Functions\expect( 'wp_mail' )->once()->andReturn( true );

        $this->cron_handler->process_queue();
    }

    /**
     * Test that empty queue does nothing.
     */
    public function test_empty_queue_does_nothing(): void {
        $wpdb = $this->setup_wpdb_mock();

        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( array() );

        // Should not call any other methods.
        $wpdb->shouldReceive( 'update' )->never();
        Functions\expect( 'wp_mail' )->never();

        $this->cron_handler->process_queue();
    }
}
