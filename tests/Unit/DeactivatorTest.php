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

        $unschedule_called = false;
        Functions\expect( 'wp_unschedule_event' )
            ->once()
            ->with( $timestamp, 'mskd_process_queue' )
            ->andReturnUsing( function() use ( &$unschedule_called ) {
                $unschedule_called = true;
                return true;
            } );

        Functions\expect( 'wp_clear_scheduled_hook' )
            ->once()
            ->with( 'mskd_process_queue' )
            ->andReturn( 1 );

        \MSKD_Deactivator::deactivate();
        
        $this->assertTrue( $unschedule_called, 'wp_unschedule_event should be called during deactivation' );
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
        $clear_called = false;
        Functions\expect( 'wp_clear_scheduled_hook' )
            ->once()
            ->with( 'mskd_process_queue' )
            ->andReturnUsing( function() use ( &$clear_called ) {
                $clear_called = true;
                return 0;
            } );

        \MSKD_Deactivator::deactivate();
        
        $this->assertTrue( $clear_called, 'wp_clear_scheduled_hook should be called during deactivation' );
    }
}
