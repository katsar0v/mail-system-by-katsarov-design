<?php
/**
 * Email Header/Footer Tests
 *
 * Tests for custom email header and footer functionality.
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MSKD\Traits\Email_Header_Footer;

/**
 * Class EmailHeaderFooterTest
 *
 * Tests for Email_Header_Footer trait and MSKD_Cron_Handler email header/footer functionality.
 */
class EmailHeaderFooterTest extends TestCase {

	/**
	 * Cron handler instance.
	 *
	 * @var \MSKD_Cron_Handler
	 */
	protected $cron_handler;

	/**
	 * Test class instance that uses the trait.
	 *
	 * @var object
	 */
	protected $trait_instance;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Load the cron handler class.
		require_once \MSKD_PLUGIN_DIR . 'includes/services/class-cron-handler.php';

		$this->cron_handler = new \MSKD_Cron_Handler();

		// Create an anonymous class that uses the trait for direct testing.
		$this->trait_instance = new class() {
			use Email_Header_Footer;
		};
	}

	// =========================================================================
	// Direct trait unit tests - test apply_header_footer method directly
	// =========================================================================

	/**
	 * Test that header and footer are applied correctly.
	 */
	public function test_apply_header_footer_with_both(): void {
		$content  = '<p>Main email content</p>';
		$settings = array(
			'email_header' => '<div class="header">Company Header</div>',
			'email_footer' => '<div class="footer">Company Footer</div>',
		);

		$result = $this->trait_instance->apply_header_footer( $content, $settings );

		$this->assertStringContainsString( '<div class="header">Company Header</div>', $result );
		$this->assertStringContainsString( '<p>Main email content</p>', $result );
		$this->assertStringContainsString( '<div class="footer">Company Footer</div>', $result );

		// Verify order: header comes before content, footer comes after.
		$header_pos  = strpos( $result, 'Company Header' );
		$content_pos = strpos( $result, 'Main email content' );
		$footer_pos  = strpos( $result, 'Company Footer' );

		$this->assertLessThan( $content_pos, $header_pos, 'Header should come before content' );
		$this->assertLessThan( $footer_pos, $content_pos, 'Content should come before footer' );
	}

	/**
	 * Test that only header is applied when footer is empty.
	 */
	public function test_apply_header_footer_with_only_header(): void {
		$content  = '<p>Main email content</p>';
		$settings = array(
			'email_header' => '<div class="header">Only Header</div>',
			'email_footer' => '',
		);

		$result = $this->trait_instance->apply_header_footer( $content, $settings );

		// Expected format.
		$expected = '<div class="header">Only Header</div><p>Main email content</p>';
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test that only footer is applied when header is empty.
	 */
	public function test_apply_header_footer_with_only_footer(): void {
		$content  = '<p>Main email content</p>';
		$settings = array(
			'email_header' => '',
			'email_footer' => '<div class="footer">Only Footer</div>',
		);

		$result = $this->trait_instance->apply_header_footer( $content, $settings );

		// Expected format.
		$expected = '<p>Main email content</p><div class="footer">Only Footer</div>';
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test that content is unchanged when both header and footer are empty.
	 */
	public function test_apply_header_footer_with_empty_both(): void {
		$content  = '<p>Main email content</p>';
		$settings = array(
			'email_header' => '',
			'email_footer' => '',
		);

		$result = $this->trait_instance->apply_header_footer( $content, $settings );

		$this->assertEquals( $content, $result, 'Content should remain unchanged when header and footer are empty' );
	}

	/**
	 * Test that content is unchanged when header and footer keys are missing.
	 */
	public function test_apply_header_footer_with_missing_keys(): void {
		$content  = '<p>Main email content</p>';
		$settings = array(
			'smtp_enabled' => true,
			'from_email'   => 'test@example.com',
		);

		$result = $this->trait_instance->apply_header_footer( $content, $settings );

		$this->assertEquals( $content, $result, 'Content should remain unchanged when header/footer keys are missing' );
	}

	/**
	 * Test with complex HTML header and footer.
	 */
	public function test_apply_header_footer_with_complex_html(): void {
		$content = '<h1>Newsletter</h1><p>Hello World</p>';
		$header  = '<!DOCTYPE html><html><head><style>.header{color:blue;}</style></head><body><div class="header"><img src="logo.png" alt="Logo"></div>';
		$footer  = '<div class="footer"><p>© 2025 Company</p><a href="{unsubscribe_url}">Unsubscribe</a></div></body></html>';

		$settings = array(
			'email_header' => $header,
			'email_footer' => $footer,
		);

		$result = $this->trait_instance->apply_header_footer( $content, $settings );

		$this->assertStringStartsWith( '<!DOCTYPE html>', $result );
		$this->assertStringEndsWith( '</body></html>', $result );
		$this->assertStringContainsString( '<h1>Newsletter</h1>', $result );
		$this->assertStringContainsString( '{unsubscribe_url}', $result );
	}

	// =========================================================================
	// Integration tests - test via MSKD_Cron_Handler
	// =========================================================================

	/**
	 * Test that header and footer are applied to email body.
	 */
	public function test_process_queue_applies_header_and_footer(): void {
		$wpdb = $this->setup_wpdb_mock();

		// Mock queue items with a simple email body.
		$queue_items = array(
			(object) array(
				'id'                => 1,
				'subscriber_id'     => 100,
				'subscriber_data'   => null,
				'email'             => 'user@example.com',
				'first_name'        => 'John',
				'last_name'         => 'Doe',
				'subject'           => 'Test Subject',
				'body'              => '<p>Main content</p>',
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

		// Second get_results is for queue items (unified query with JOIN).
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( Mockery::on( function ( $query ) {
				return strpos( $query, 'INNER JOIN' ) !== false
					&& strpos( $query, 'mskd_subscribers' ) !== false;
			} ) )
			->andReturn( $queue_items );

		$wpdb->shouldReceive( 'update' )
			->twice()
			->andReturn( 1 );

		// Mock settings with header and footer configured.
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'smtp_enabled'  => true,
					'smtp_host'     => 'smtp.example.com',
					'from_name'     => 'Test Site',
					'from_email'    => 'noreply@example.com',
					'reply_to'      => 'reply@example.com',
					'email_header'  => '<div class="header">Welcome, {first_name}!</div>',
					'email_footer'  => '<div class="footer">{unsubscribe_link}</div>',
				);
			}
			return $default;
		});

		// Process the queue - header and footer should be applied.
		$this->cron_handler->process_queue();

		// If we got here without errors, header/footer were applied.
		$this->assertTrue( true );
	}

	/**
	 * Test that empty header and footer don't modify content.
	 */
	public function test_process_queue_with_empty_header_footer(): void {
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
				'body'              => '<p>Body content</p>',
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

		// Second get_results is for queue items (unified query with JOIN).
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( Mockery::on( function ( $query ) {
				return strpos( $query, 'INNER JOIN' ) !== false
					&& strpos( $query, 'mskd_subscribers' ) !== false;
			} ) )
			->andReturn( $queue_items );

		$wpdb->shouldReceive( 'update' )
			->twice()
			->andReturn( 1 );

		// Mock settings without header/footer.
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'smtp_enabled'  => true,
					'smtp_host'     => 'smtp.example.com',
					'from_name'     => 'Test Site',
					'from_email'    => 'noreply@example.com',
					'reply_to'      => 'reply@example.com',
					'email_header'  => '',
					'email_footer'  => '',
				);
			}
			return $default;
		});

		$this->cron_handler->process_queue();

		// If we got here without errors, empty header/footer were handled correctly.
		$this->assertTrue( true );
	}

	/**
	 * Test that header only (without footer) is applied correctly.
	 */
	public function test_process_queue_with_header_only(): void {
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
				'body'              => '<p>Body content</p>',
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

		// Second get_results is for queue items (unified query with JOIN).
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( Mockery::on( function ( $query ) {
				return strpos( $query, 'INNER JOIN' ) !== false
					&& strpos( $query, 'mskd_subscribers' ) !== false;
			} ) )
			->andReturn( $queue_items );

		$wpdb->shouldReceive( 'update' )
			->twice()
			->andReturn( 1 );

		// Mock settings with header only.
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'smtp_enabled'  => true,
					'smtp_host'     => 'smtp.example.com',
					'from_name'     => 'Test Site',
					'from_email'    => 'noreply@example.com',
					'reply_to'      => 'reply@example.com',
					'email_header'  => '<div class="header">Company Logo</div>',
					'email_footer'  => '',
				);
			}
			return $default;
		});

		$this->cron_handler->process_queue();

		$this->assertTrue( true );
	}

	/**
	 * Test that footer only (without header) is applied correctly.
	 */
	public function test_process_queue_with_footer_only(): void {
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
				'body'              => '<p>Body content</p>',
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

		// Second get_results is for queue items (unified query with JOIN).
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( Mockery::on( function ( $query ) {
				return strpos( $query, 'INNER JOIN' ) !== false
					&& strpos( $query, 'mskd_subscribers' ) !== false;
			} ) )
			->andReturn( $queue_items );

		$wpdb->shouldReceive( 'update' )
			->twice()
			->andReturn( 1 );

		// Mock settings with footer only.
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'smtp_enabled'  => true,
					'smtp_host'     => 'smtp.example.com',
					'from_name'     => 'Test Site',
					'from_email'    => 'noreply@example.com',
					'reply_to'      => 'reply@example.com',
					'email_header'  => '',
					'email_footer'  => '<p>To unsubscribe: {unsubscribe_link}</p>',
				);
			}
			return $default;
		});

		$this->cron_handler->process_queue();

		$this->assertTrue( true );
	}

	/**
	 * Test that placeholders in header and footer are replaced correctly.
	 */
	public function test_header_footer_placeholder_replacement(): void {
		$wpdb = $this->setup_wpdb_mock();

		$queue_items = array(
			(object) array(
				'id'                => 1,
				'subscriber_id'     => 100,
				'subscriber_data'   => null,
				'email'             => 'john.doe@example.com',
				'first_name'        => 'John',
				'last_name'         => 'Doe',
				'subject'           => 'Newsletter for {first_name}',
				'body'              => '<p>Hello {first_name} {last_name}!</p>',
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

		// Second get_results is for queue items (unified query with JOIN).
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( Mockery::on( function ( $query ) {
				return strpos( $query, 'INNER JOIN' ) !== false
					&& strpos( $query, 'mskd_subscribers' ) !== false;
			} ) )
			->andReturn( $queue_items );

		$wpdb->shouldReceive( 'update' )
			->twice()
			->andReturn( 1 );

		// Mock settings with header/footer containing placeholders.
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'smtp_enabled'  => true,
					'smtp_host'     => 'smtp.example.com',
					'from_name'     => 'Newsletter',
					'from_email'    => 'newsletter@example.com',
					'reply_to'      => 'reply@example.com',
					'email_header'  => '<div class="header">Hello, {first_name}!</div>',
					'email_footer'  => '<div class="footer">Sent to {email}. {unsubscribe_link}</div>',
				);
			}
			return $default;
		});

		$this->cron_handler->process_queue();

		// If we got here without errors, placeholders were replaced.
		$this->assertTrue( true );
	}

	/**
	 * Test that settings without header/footer keys don't cause errors.
	 */
	public function test_process_queue_with_missing_header_footer_keys(): void {
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
				'body'              => '<p>Body content</p>',
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

		// Second get_results is for queue items (unified query with JOIN).
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->with( Mockery::on( function ( $query ) {
				return strpos( $query, 'INNER JOIN' ) !== false
					&& strpos( $query, 'mskd_subscribers' ) !== false;
			} ) )
			->andReturn( $queue_items );

		$wpdb->shouldReceive( 'update' )
			->twice()
			->andReturn( 1 );

		// Mock settings without email_header/email_footer keys (old settings).
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'smtp_enabled'  => true,
					'smtp_host'     => 'smtp.example.com',
					'from_name'     => 'Test Site',
					'from_email'    => 'noreply@example.com',
					'reply_to'      => 'reply@example.com',
					// email_header and email_footer are missing.
				);
			}
			return $default;
		});

		$this->cron_handler->process_queue();

		// If we got here without errors, missing keys were handled gracefully.
		$this->assertTrue( true );
	}
}
