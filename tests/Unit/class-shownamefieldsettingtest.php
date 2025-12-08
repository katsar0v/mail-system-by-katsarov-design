<?php
/**
 * Show Name Field Setting Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MSKD\Admin\Admin_Settings;

/**
 * Class ShowNameFieldSettingTest
 *
 * Tests for show_name_field setting functionality.
 */
class ShowNameFieldSettingTest extends TestCase {

	/**
	 * Admin Settings instance.
	 *
	 * @var Admin_Settings
	 */
	protected $admin_settings;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Define MSKD_BATCH_SIZE if not defined.
		if ( ! defined( 'MSKD_BATCH_SIZE' ) ) {
			define( 'MSKD_BATCH_SIZE', 10 );
		}

		$this->admin_settings = new Admin_Settings();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		// Clean up any POST data.
		$_POST = array();

		parent::tearDown();
	}

	/**
	 * Test default show_name_field setting is returned when no settings exist.
	 */
	public function test_get_settings_returns_default_show_name_field(): void {
		// Override get_option stub for this test.
		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) {
					if ( 'mskd_settings' === $option ) {
						return array(); // No settings saved.
					}
					return $default;
				},
			)
		);

		$settings = $this->admin_settings->get_settings();

		$this->assertArrayHasKey( 'show_name_field', $settings );
		$this->assertEquals( 1, $settings['show_name_field'] ); // Default should be 1 (enabled).
	}

	/**
	 * Test custom show_name_field setting is returned when saved.
	 */
	public function test_get_settings_returns_custom_show_name_field(): void {
		$custom_settings = array(
			'show_name_field' => 0, // Disabled.
		);

		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) use ( $custom_settings ) {
					if ( 'mskd_settings' === $option ) {
						return $custom_settings;
					}
					return $default;
				},
			)
		);

		$settings = $this->admin_settings->get_settings();

		$this->assertEquals( 0, $settings['show_name_field'] );
	}

	/**
	 * Test show_name_field setting is merged with defaults.
	 */
	public function test_get_settings_merges_show_name_field_with_defaults(): void {
		// Only show_name_field is set.
		$partial_settings = array(
			'show_name_field' => 0,
		);

		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) use ( $partial_settings ) {
					if ( 'mskd_settings' === $option ) {
						return $partial_settings;
					}
					return $default;
				},
			)
		);

		$settings = $this->admin_settings->get_settings();

		// Custom show_name_field.
		$this->assertEquals( 0, $settings['show_name_field'] );
		// Default from_name.
		$this->assertArrayHasKey( 'from_name', $settings );
	}
}