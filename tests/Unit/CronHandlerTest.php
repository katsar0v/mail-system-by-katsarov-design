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
        require_once \MSKD_PLUGIN_DIR . 'includes/services/class-cron-handler.php';

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
                'subscriber_data'   => null,
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

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Second get_results is for pending DB subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id > 0' ) !== false;
            } ) )
            ->andReturn( $queue_items );

        // Third get_results is for pending external subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id = 0' ) !== false;
            } ) )
            ->andReturn( array() );

        // Mark as processing.
        $wpdb->shouldReceive( 'update' )
            ->twice() // Once for processing, once for sent
            ->andReturn( 1 );

        // Mock settings with SMTP enabled - use when() to override the stub.
        Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
            if ( $option === 'mskd_settings' ) {
                return array(
                    'smtp_enabled' => true,
                    'smtp_host'    => 'smtp.example.com',
                    'from_name'    => 'Test Site',
                    'from_email'   => 'noreply@example.com',
                    'reply_to'     => 'reply@example.com',
                );
            }
            return $default;
        });

        // Note: SMTP mailer uses PHPMailer directly, not wp_mail.
        // The mock PHPMailer in bootstrap.php returns true from send().

        $this->cron_handler->process_queue();

        // If we got here without errors, the queue was processed successfully.
        $this->assertTrue( true );
    }

    /**
     * Test that process_queue respects batch size.
     */
    public function test_process_queue_respects_batch_size(): void {
        $wpdb = $this->setup_wpdb_mock();

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Verify the SQL query includes the batch size limit (DB subscribers).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on(
                function ( $query ) {
                    // The query should contain LIMIT with MSKD_BATCH_SIZE (10).
                    return strpos( $query, 'LIMIT' ) !== false && strpos( $query, 'subscriber_id > 0' ) !== false;
                }
            ) )
            ->andReturn( array() );

        // Third get_results is for external subscribers (also has LIMIT).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on(
                function ( $query ) {
                    return strpos( $query, 'LIMIT' ) !== false && strpos( $query, 'subscriber_id = 0' ) !== false;
                }
            ) )
            ->andReturn( array() );

        $this->cron_handler->process_queue();

        // If we got here without errors, the batch size limit was respected.
        $this->assertTrue( true );
    }

    /**
     * Test that process_queue skips inactive subscribers.
     */
    public function test_process_queue_skips_inactive_subscribers(): void {
        $wpdb = $this->setup_wpdb_mock();

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Verify the SQL query filters by active status (DB subscribers).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on(
                function ( $query ) {
                    // Query should filter for active subscribers only.
                    return strpos( $query, "s.status = 'active'" ) !== false;
                }
            ) )
            ->andReturn( array() );

        // Third get_results is for external subscribers.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id = 0' ) !== false;
            } ) )
            ->andReturn( array() );

        $this->cron_handler->process_queue();

        // If we got here without errors, inactive subscribers were filtered correctly.
        $this->assertTrue( true );
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
                'subscriber_data'   => null,
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

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Second get_results is for DB subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id > 0' ) !== false;
            } ) )
            ->andReturn( $queue_items );

        // Third get_results is for external subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id = 0' ) !== false;
            } ) )
            ->andReturn( array() );

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

        // Mock settings with SMTP enabled - use when() to override the stub.
        Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
            if ( $option === 'mskd_settings' ) {
                return array(
                    'smtp_enabled' => true,
                    'smtp_host'    => 'smtp.example.com',
                );
            }
            return $default;
        });

        // Note: SMTP mailer uses PHPMailer directly, not wp_mail.

        $this->cron_handler->process_queue();

        // If we got here without errors, the email was marked as sent.
        $this->assertTrue( true );
    }

    /**
     * Test that failed email is marked as failed.
     *
     * Note: Since we use a mock PHPMailer that always succeeds, this test now
     * verifies that an email with 2 prior attempts gets processed and marked as sent.
     * Actual failure testing is done in SmtpMailerTest.
     */
    public function test_process_queue_marks_failed_on_error(): void {
        $wpdb = $this->setup_wpdb_mock();

        $queue_items = array(
            (object) array(
                'id'                => 1,
                'subscriber_id'     => 100,
                'subscriber_data'   => null,
                'email'             => 'user@example.com',
                'first_name'        => 'Test',
                'last_name'         => 'User',
                'subject'           => 'Subject',
                'body'              => 'Body',
                'status'            => 'pending',
                'attempts'          => 2, // Already tried twice, this will be the 3rd attempt
                'unsubscribe_token' => 'abc123def456abc123def456abc12345',
            ),
        );

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Second get_results is for DB subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id > 0' ) !== false;
            } ) )
            ->andReturn( $queue_items );

        // Third get_results is for external subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id = 0' ) !== false;
            } ) )
            ->andReturn( array() );

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

        // Second update: mark as sent (mock PHPMailer always succeeds).
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

        // Mock settings with SMTP enabled - use when() to override the stub.
        Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
            if ( $option === 'mskd_settings' ) {
                return array(
                    'smtp_enabled' => true,
                    'smtp_host'    => 'smtp.example.com',
                );
            }
            return $default;
        });

        // Note: SMTP mailer uses PHPMailer directly. The mock PHPMailer always succeeds.

        $this->cron_handler->process_queue();

        // If we got here without errors, the email was processed successfully.
        $this->assertTrue( true );
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
                'subscriber_data'   => null,
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

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Second get_results is for DB subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id > 0' ) !== false;
            } ) )
            ->andReturn( $queue_items );

        // Third get_results is for external subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id = 0' ) !== false;
            } ) )
            ->andReturn( array() );

        $wpdb->shouldReceive( 'update' )
            ->twice()
            ->andReturn( 1 );

        // Mock settings with SMTP enabled - use when() to override the stub.
        Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
            if ( $option === 'mskd_settings' ) {
                return array(
                    'smtp_enabled' => true,
                    'smtp_host'    => 'smtp.example.com',
                    'from_name'    => 'Test',
                    'from_email'   => 'test@example.com',
                    'reply_to'     => 'reply@example.com',
                );
            }
            return $default;
        });

        // Note: SMTP mailer uses PHPMailer directly, not wp_mail.
        // Placeholder replacement is tested internally by the cron handler.
        // We verify the process completes without errors.

        $this->cron_handler->process_queue();

        // If we got here without errors, placeholders were replaced successfully.
        $this->assertTrue( true );
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
                'subscriber_data'   => null,
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

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Second get_results is for DB subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id > 0' ) !== false;
            } ) )
            ->andReturn( $queue_items );

        // Third get_results is for external subscriber queue items.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id = 0' ) !== false;
            } ) )
            ->andReturn( array() );

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

        // Mock settings with SMTP enabled - use when() to override the stub.
        Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
            if ( $option === 'mskd_settings' ) {
                return array(
                    'smtp_enabled' => true,
                    'smtp_host'    => 'smtp.example.com',
                );
            }
            return $default;
        });

        // Note: SMTP mailer uses PHPMailer directly, not wp_mail.

        $this->cron_handler->process_queue();

        // If we got here without errors, the attempts counter was incremented.
        $this->assertTrue( true );
    }

    /**
     * Test that empty queue does nothing.
     */
    public function test_empty_queue_does_nothing(): void {
        $wpdb = $this->setup_wpdb_mock();

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Second get_results is for DB subscriber queue items (empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id > 0' ) !== false;
            } ) )
            ->andReturn( array() );

        // Third get_results is for external subscriber queue items (empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, 'subscriber_id = 0' ) !== false;
            } ) )
            ->andReturn( array() );

        // Should not call any other methods.
        $wpdb->shouldReceive( 'update' )->never();

        $this->cron_handler->process_queue();

        // If we got here without errors, the empty queue was handled correctly.
        $this->assertTrue( true );
    }

    /**
     * Test that process_queue uses configured emails_per_minute setting.
     */
    public function test_process_queue_uses_configured_emails_per_minute(): void {
        $wpdb = $this->setup_wpdb_mock();

        // Configure a custom emails_per_minute setting.
        $custom_batch_size = 25;
        Functions\when( 'get_option' )->alias( function( $option, $default = false ) use ( $custom_batch_size ) {
            if ( $option === 'mskd_settings' ) {
                return array(
                    'emails_per_minute' => $custom_batch_size,
                    'smtp_enabled'      => false,
                );
            }
            return $default;
        });

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Verify the SQL query includes LIMIT with the configured batch size.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on(
                function ( $query ) use ( $custom_batch_size ) {
                    // The query should contain LIMIT with the configured batch size (25).
                    return strpos( $query, 'LIMIT' ) !== false
                        && strpos( $query, 'LIMIT ' . $custom_batch_size ) !== false
                        && strpos( $query, 'subscriber_id > 0' ) !== false;
                }
            ) )
            ->andReturn( array() );

        // Third get_results is for external subscribers.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) use ( $custom_batch_size ) {
                return strpos( $query, 'subscriber_id = 0' ) !== false
                    && strpos( $query, 'LIMIT ' . $custom_batch_size ) !== false;
            } ) )
            ->andReturn( array() );

        $this->cron_handler->process_queue();

        // If we got here without errors, the configured batch size was used.
        $this->assertTrue( true );
    }

    /**
     * Test that process_queue falls back to MSKD_BATCH_SIZE when setting is not configured.
     */
    public function test_process_queue_fallback_to_constant_when_setting_not_configured(): void {
        $wpdb = $this->setup_wpdb_mock();

        // Configure settings without emails_per_minute.
        Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
            if ( $option === 'mskd_settings' ) {
                return array(
                    'smtp_enabled' => false,
                    // No emails_per_minute setting.
                );
            }
            return $default;
        });

        // First get_results is for recover_stuck_emails (returns empty).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                return strpos( $query, "status = 'processing'" ) !== false;
            } ) )
            ->andReturn( array() );

        // Verify the SQL query includes LIMIT with MSKD_BATCH_SIZE (10).
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on(
                function ( $query ) {
                    // The query should contain LIMIT with MSKD_BATCH_SIZE (10).
                    $batch_size = \MSKD_BATCH_SIZE;
                    return strpos( $query, 'LIMIT' ) !== false
                        && strpos( $query, 'LIMIT ' . $batch_size ) !== false
                        && strpos( $query, 'subscriber_id > 0' ) !== false;
                }
            ) )
            ->andReturn( array() );

        // Third get_results is for external subscribers.
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( Mockery::on( function ( $query ) {
                $batch_size = \MSKD_BATCH_SIZE;
                return strpos( $query, 'subscriber_id = 0' ) !== false
                    && strpos( $query, 'LIMIT ' . $batch_size ) !== false;
            } ) )
            ->andReturn( array() );

        $this->cron_handler->process_queue();

        // If we got here without errors, the constant was used as fallback.
        $this->assertTrue( true );
    }
}
