<?php
/**
 * Deactivator Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class DeactivatorTest
 *
 * Tests for MSKD_Deactivator class.
 */
class DeactivatorTest extends TestCase {

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();

        // Load the deactivator class.
        require_once MSKD_PLUGIN_DIR . 'includes/class-deactivator.php';
    }

    /**
     * Test that cron is unscheduled on deactivation.
     */
    public function test_cron_unscheduled_on_deactivation(): void {
        $timestamp = 1234567890;

        Functions\expect( 'wp_next_scheduled' )
            ->once()
            ->with( 'mskd_process_queue' )
            ->andReturn( $timestamp );

        Functions\expect( 'wp_unschedule_event' )
            ->once()
            ->with( $timestamp, 'mskd_process_queue' )
            ->andReturn( true );

        Functions\expect( 'wp_clear_scheduled_hook' )
            ->once()
            ->with( 'mskd_process_queue' )
            ->andReturn( 1 );

        \MSKD_Deactivator::deactivate();
    }

    /**
     * Test deactivation when no cron is scheduled.
     */
    public function test_deactivation_when_no_cron_scheduled(): void {
        Functions\expect( 'wp_next_scheduled' )
            ->once()
            ->with( 'mskd_process_queue' )
            ->andReturn( false );

        // Should NOT call wp_unschedule_event.
        Functions\expect( 'wp_unschedule_event' )
            ->never();

        // But should still clear all scheduled hooks.
        Functions\expect( 'wp_clear_scheduled_hook' )
            ->once()
            ->with( 'mskd_process_queue' )
            ->andReturn( 0 );

        \MSKD_Deactivator::deactivate();
    }
}
