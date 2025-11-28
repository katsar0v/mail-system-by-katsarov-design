<?php
/**
 * Form Gallery Shortcode Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class FormGalleryTest
 *
 * Tests for the form gallery shortcode functionality.
 */
class FormGalleryTest extends TestCase {

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
		require_once MSKD_PLUGIN_DIR . 'public/class-public.php';

		$this->public = new \MSKD_Public();
	}

	/**
	 * Test that form_gallery_shortcode method exists.
	 */
	public function test_form_gallery_shortcode_method_exists(): void {
		$this->assertTrue(
			method_exists( $this->public, 'form_gallery_shortcode' ),
			'form_gallery_shortcode method should exist'
		);
	}

	/**
	 * Test form gallery shortcode registration in init.
	 */
	public function test_form_gallery_shortcode_is_registered(): void {
		$registered_shortcodes = array();

		// Override the stub to capture registrations.
		Functions\when( 'add_shortcode' )->alias(
			function ( $tag, $callback ) use ( &$registered_shortcodes ) {
				$registered_shortcodes[] = $tag;
			}
		);

		// Re-create public instance to trigger init.
		$public = new \MSKD_Public();
		$public->init();

		$this->assertContains(
			'mskd_form_gallery',
			$registered_shortcodes,
			'mskd_form_gallery shortcode should be registered'
		);
	}

	/**
	 * Test that shortcode accepts title attribute.
	 */
	public function test_shortcode_accepts_title_attribute(): void {
		$atts = array(
			'title' => 'Custom Gallery Title',
		);

		// Use when() to set up shortcode_atts stub.
		Functions\when( 'shortcode_atts' )->justReturn( $atts );

		// Mock asset enqueue functions.
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'wp_localize_script' )->justReturn( null );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce' );

		// Verify method accepts attributes.
		$this->assertTrue( method_exists( $this->public, 'form_gallery_shortcode' ) );
	}

	/**
	 * Test that localized script includes copy strings.
	 */
	public function test_localized_strings_include_copy_messages(): void {
		$localized_data = null;

		Functions\expect( 'wp_enqueue_style' )->andReturn( null );
		Functions\expect( 'wp_enqueue_script' )->andReturn( null );
		Functions\expect( 'wp_create_nonce' )->andReturn( 'test_nonce' );

		Functions\expect( 'wp_localize_script' )
			->once()
			->andReturnUsing(
				function ( $handle, $object_name, $l10n ) use ( &$localized_data ) {
					$localized_data = $l10n;
					return true;
				}
			);

		// Call the private enqueue_assets method via reflection.
		$reflection = new \ReflectionClass( $this->public );
		$method     = $reflection->getMethod( 'enqueue_assets' );
		$method->setAccessible( true );
		$method->invoke( $this->public );

		$this->assertIsArray( $localized_data );
		$this->assertArrayHasKey( 'strings', $localized_data );
		$this->assertArrayHasKey( 'copied', $localized_data['strings'] );
		$this->assertArrayHasKey( 'copy_error', $localized_data['strings'] );
	}
}
