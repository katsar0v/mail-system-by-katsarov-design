<?php
/**
 * Activator Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class ActivatorTest
 *
 * Tests for MSKD_Activator class.
 */
class ActivatorTest extends TestCase {

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();

        // Load the activator class.
        require_once MSKD_PLUGIN_DIR . 'includes/class-activator.php';
    }

    /**
     * Test that activation calls dbDelta for all required tables.
     */
    public function test_tables_created_on_activation(): void {
        $wpdb = $this->setup_wpdb_mock();
        $wpdb->shouldReceive( 'get_charset_collate' )
            ->once()
            ->andReturn( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );

        // Track dbDelta calls.
        $db_delta_calls = array();
        Functions\expect( 'dbDelta' )
            ->times( 4 )
            ->andReturnUsing(
                function ( $sql ) use ( &$db_delta_calls ) {
                    $db_delta_calls[] = $sql;
                    return array();
                }
            );

        // Mock other required functions.
        Functions\expect( 'wp_next_scheduled' )
            ->once()
            ->with( 'mskd_process_queue' )
            ->andReturn( false );

        Functions\expect( 'wp_schedule_event' )
            ->once()
            ->andReturn( true );

        Functions\expect( 'get_option' )
            ->with( 'mskd_settings' )
            ->andReturn( false );

        Functions\expect( 'update_option' )
            ->times( 2 )
            ->andReturn( true );

        Functions\expect( 'flush_rewrite_rules' )
            ->once()
            ->andReturn( null );

        // Run activation.
        \MSKD_Activator::activate();

        // Verify all 4 tables are created.
        $this->assertCount( 4, $db_delta_calls, 'Should call dbDelta 4 times for 4 tables' );

        // Check table names in SQL.
        $all_sql = implode( ' ', $db_delta_calls );
        $this->assertStringContainsString( 'wp_mskd_subscribers', $all_sql, 'Should create subscribers table' );
        $this->assertStringContainsString( 'wp_mskd_lists', $all_sql, 'Should create lists table' );
        $this->assertStringContainsString( 'wp_mskd_subscriber_list', $all_sql, 'Should create subscriber_list pivot table' );
        $this->assertStringContainsString( 'wp_mskd_queue', $all_sql, 'Should create queue table' );
    }

    /**
     * Test that cron is scheduled on activation.
     */
    public function test_cron_scheduled_on_activation(): void {
        $wpdb = $this->setup_wpdb_mock();
        $wpdb->shouldReceive( 'get_charset_collate' )
            ->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

        Functions\stubs( array( 'dbDelta' => array() ) );
        Functions\stubs( array( 'flush_rewrite_rules' => null ) );

        // Cron not yet scheduled.
        Functions\expect( 'wp_next_scheduled' )
            ->once()
            ->with( 'mskd_process_queue' )
            ->andReturn( false );

        // Should schedule the cron event.
        Functions\expect( 'wp_schedule_event' )
            ->once()
            ->with( Mockery::type( 'int' ), 'mskd_every_minute', 'mskd_process_queue' )
            ->andReturn( true );

        Functions\expect( 'get_option' )
            ->with( 'mskd_settings' )
            ->andReturn( false );

        Functions\expect( 'update_option' )
            ->andReturn( true );

        \MSKD_Activator::activate();
    }

    /**
     * Test that cron is not re-scheduled if already exists.
     */
    public function test_cron_not_rescheduled_if_exists(): void {
        $wpdb = $this->setup_wpdb_mock();
        $wpdb->shouldReceive( 'get_charset_collate' )
            ->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

        Functions\stubs( array( 'dbDelta' => array() ) );
        Functions\stubs( array( 'flush_rewrite_rules' => null ) );

        // Cron already scheduled.
        Functions\expect( 'wp_next_scheduled' )
            ->once()
            ->with( 'mskd_process_queue' )
            ->andReturn( 1234567890 );

        // Should NOT schedule the cron event again.
        Functions\expect( 'wp_schedule_event' )
            ->never();

        Functions\expect( 'get_option' )
            ->with( 'mskd_settings' )
            ->andReturn( false );

        Functions\expect( 'update_option' )
            ->andReturn( true );

        \MSKD_Activator::activate();
    }

    /**
     * Test that default options are set on activation.
     */
    public function test_default_options_set(): void {
        $wpdb = $this->setup_wpdb_mock();
        $wpdb->shouldReceive( 'get_charset_collate' )
            ->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

        Functions\stubs( array( 'dbDelta' => array() ) );
        Functions\stubs( array( 'flush_rewrite_rules' => null ) );
        Functions\stubs( array( 'wp_next_scheduled' => 1234567890 ) );

        // No existing settings.
        Functions\expect( 'get_option' )
            ->with( 'mskd_settings' )
            ->andReturn( false );

        // Should set default options.
        $saved_settings = null;
        Functions\expect( 'update_option' )
            ->with(
                'mskd_settings',
                Mockery::on(
                    function ( $settings ) use ( &$saved_settings ) {
                        $saved_settings = $settings;
                        return is_array( $settings );
                    }
                )
            )
            ->andReturn( true );

        Functions\expect( 'update_option' )
            ->with( 'mskd_db_version', \MSKD_Activator::DB_VERSION )
            ->andReturn( true );

        \MSKD_Activator::activate();

        // Verify default settings structure.
        $this->assertIsArray( $saved_settings );
        $this->assertArrayHasKey( 'from_name', $saved_settings );
        $this->assertArrayHasKey( 'from_email', $saved_settings );
        $this->assertArrayHasKey( 'reply_to', $saved_settings );
    }

    /**
     * Test that existing options are not overwritten.
     */
    public function test_existing_options_not_overwritten(): void {
        $wpdb = $this->setup_wpdb_mock();
        $wpdb->shouldReceive( 'get_charset_collate' )
            ->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

        Functions\stubs( array( 'dbDelta' => array() ) );
        Functions\stubs( array( 'flush_rewrite_rules' => null ) );
        Functions\stubs( array( 'wp_next_scheduled' => 1234567890 ) );

        // Existing settings already present.
        $existing_settings = array(
            'from_name'  => 'Custom Name',
            'from_email' => 'custom@example.com',
            'reply_to'   => 'reply@example.com',
        );

        Functions\expect( 'get_option' )
            ->with( 'mskd_settings' )
            ->andReturn( $existing_settings );

        // Should NOT update mskd_settings (but will update db_version).
        Functions\expect( 'update_option' )
            ->with( 'mskd_db_version', Mockery::any() )
            ->once()
            ->andReturn( true );

        \MSKD_Activator::activate();
    }

    /**
     * Test that database version is stored.
     */
    public function test_db_version_stored(): void {
        $wpdb = $this->setup_wpdb_mock();
        $wpdb->shouldReceive( 'get_charset_collate' )
            ->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

        Functions\stubs( array( 'dbDelta' => array() ) );
        Functions\stubs( array( 'flush_rewrite_rules' => null ) );
        Functions\stubs( array( 'wp_next_scheduled' => 1234567890 ) );

        Functions\expect( 'get_option' )
            ->with( 'mskd_settings' )
            ->andReturn( array() );

        Functions\expect( 'update_option' )
            ->with( 'mskd_db_version', '1.0.0' )
            ->once()
            ->andReturn( true );

        \MSKD_Activator::activate();
    }
}
