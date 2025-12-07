<?php
/**
 * Dashboard Widget Test
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class DashboardWidgetTest
 *
 * Tests for the dashboard widget functionality.
 */
class DashboardWidgetTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Set up global wpdb for Admin class dependencies.
		$this->setup_wpdb_mock();

		// Add stubs for esc_html_e that is used in the template.
		Functions\stubs(
			array(
				'esc_html_e' => function ( $text, $domain = 'default' ) {
					echo $text;
				},
			)
		);
	}

	/**
	 * Test that the dashboard widget is registered with proper capability.
	 */
	public function test_register_dashboard_widget_with_capability(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		Functions\expect( 'wp_add_dashboard_widget' )
			->once()
			->with(
				'mskd_queue_stats_widget',
				'Mail System - Queue Statistics',
				Mockery::type( 'array' )
			);

		$admin = new \MSKD\Admin\Admin();
		$admin->register_dashboard_widget();

		// Verify the expectation was met.
		$this->assertTrue( true );
	}

	/**
	 * Test that the dashboard widget is not registered without capability.
	 */
	public function test_register_dashboard_widget_without_capability(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		Functions\expect( 'wp_add_dashboard_widget' )
			->never();

		$admin = new \MSKD\Admin\Admin();
		$admin->register_dashboard_widget();

		// Verify the expectation was met.
		$this->assertTrue( true );
	}

	/**
	 * Test that the dashboard widget renders with queue statistics.
	 */
	public function test_render_dashboard_widget_with_stats(): void {
		// Mock queue statistics.
		$stats          = new \stdClass();
		$stats->pending = 5;
		$stats->sent    = 100;
		$stats->failed  = 3;

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $stats );

		// Override get_option stub to return our test values.
		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) {
					if ( 'mskd_last_cron_run' === $option ) {
						return 1700000000;
					}
					if ( 'date_format' === $option ) {
						return 'Y-m-d';
					}
					if ( 'time_format' === $option ) {
						return 'H:i:s';
					}
					return $default;
				},
				'date_i18n'  => function () {
					return '2023-11-14 22:13:20';
				},
			)
		);

		// Start output buffering to capture output.
		ob_start();
		$admin = new \MSKD\Admin\Admin();
		$admin->render_dashboard_widget();
		$output = ob_get_clean();

		// Verify that the output contains the expected values.
		$this->assertStringContainsString( '5', $output );
		$this->assertStringContainsString( '100', $output );
		$this->assertStringContainsString( '3', $output );
		$this->assertStringContainsString( 'Last cron run:', $output );
	}

	/**
	 * Test that the dashboard widget renders when cron has never run.
	 */
	public function test_render_dashboard_widget_cron_never_run(): void {
		// Mock queue statistics with nulls.
		$stats          = new \stdClass();
		$stats->pending = null;
		$stats->sent    = null;
		$stats->failed  = null;

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $stats );

		// Override get_option stub to return 0 for mskd_last_cron_run.
		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) {
					if ( 'mskd_last_cron_run' === $option ) {
						return 0;
					}
					return $default;
				},
			)
		);

		// Start output buffering to capture output.
		ob_start();
		$admin = new \MSKD\Admin\Admin();
		$admin->render_dashboard_widget();
		$output = ob_get_clean();

		// Verify that the output indicates cron has not run.
		$this->assertStringContainsString( 'Cron has not run yet.', $output );
	}

	/**
	 * Test that the cron handler records last run timestamp.
	 */
	public function test_cron_handler_records_last_run(): void {
		// Mock wpdb query for stuck emails recovery.
		$this->wpdb->shouldReceive( 'get_results' )
			->andReturn( array() );

		// Track if update_option was called.
		$update_called = false;
		Functions\stubs(
			array(
				'update_option' => function ( $option, $value ) use ( &$update_called ) {
					if ( 'mskd_last_cron_run' === $option && is_int( $value ) ) {
						$update_called = true;
					}
					return true;
				},
			)
		);

		require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-cron-handler.php';
		$cron_handler = new \MSKD_Cron_Handler();
		$cron_handler->process_queue();

		$this->assertTrue( $update_called, 'update_option should be called with mskd_last_cron_run' );
	}
}
