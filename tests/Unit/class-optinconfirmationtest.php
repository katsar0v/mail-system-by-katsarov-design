<?php
/**
 * Opt-in Confirmation Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class OptInConfirmationTest
 *
 * Tests for opt-in confirmation functionality in MSKD_Public class.
 */
class OptInConfirmationTest extends TestCase {

	/**
	 * Exception message used to prevent exit() in tests.
	 */
	const TEST_COMPLETE_EXCEPTION = 'test_complete';

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
		require_once \MSKD_PLUGIN_DIR . 'public/class-mskd-public.php';

		$this->public = new \MSKD_Public();

		// Set default SERVER vars.
		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';
	}

	/**
	 * Test valid token confirms subscriber.
	 *
	 * Note: This test verifies that database update is called. We throw an exception
	 * after the update to prevent reaching include/exit.
	 */
	public function test_valid_token_confirms_subscriber(): void {
		$wpdb = $this->setup_wpdb_mock();

		$valid_token          = 'abc123def456abc123def456abc12345'; // 32 chars.
		$_GET['mskd_confirm'] = $valid_token;

		// Rate limit check - no previous attempts.
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		Functions\expect( 'set_transient' )
			->once()
			->andReturn( true );

		// Valid subscriber found.
		$subscriber = (object) array(
			'id'           => 123,
			'email'        => 'user@example.com',
			'status'       => 'inactive',
			'opt_in_token' => $valid_token,
		);
		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $subscriber );

		// Should update status to active and clear opt_in_token.
		$update_called = false;
		$wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_mskd_subscribers',
				array(
					'status'       => 'active',
					'opt_in_token' => null,
				),
				array( 'id' => 123 ),
				Mockery::type( 'array' ),
				Mockery::type( 'array' )
			)
			->andReturnUsing(
				function () use ( &$update_called ) {
					$update_called = true;
					// Throw to prevent reaching include/exit.
					throw new \Exception( self::TEST_COMPLETE_EXCEPTION );
				}
			);

		try {
			$this->public->handle_opt_in_confirmation();
		} catch ( \Exception $e ) {
			$this->assertEquals( self::TEST_COMPLETE_EXCEPTION, $e->getMessage() );
		}
	}

	/**
	 * Test invalid token returns error.
	 */
	public function test_invalid_token_returns_error(): void {
		$wpdb = $this->setup_wpdb_mock();

		$invalid_token        = 'validformat1234567890123456789ab'; // 32 chars but not in DB.
		$_GET['mskd_confirm'] = $invalid_token;

		// Rate limit check.
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( false );

		Functions\expect( 'set_transient' )
			->once()
			->andReturn( true );

		// No subscriber found.
		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		Functions\expect( 'wp_die' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::type( 'string' ),
				Mockery::on(
					function ( $args ) {
						return (bool) esc_html( $args['response'] === 400 );
					}
				)
			)
			->andReturnUsing(
				function () {
					throw new \Exception( 'wp_die_called' );
				}
			);

		try {
			$this->public->handle_opt_in_confirmation();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'wp_die_called', $e->getMessage() );
		}
	}

	/**
	 * Test invalid token format is rejected.
	 */
	public function test_invalid_token_format_rejected(): void {
		$wpdb = $this->setup_wpdb_mock();

		// Token too short.
		$_GET['mskd_confirm'] = 'shorttoken';

		Functions\expect( 'wp_die' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::type( 'string' ),
				Mockery::on(
					function ( $args ) {
						return (bool) esc_html( $args['response'] === 400 );
					}
				)
			)
			->andReturnUsing(
				function () {
					throw new \Exception( 'wp_die_format_error' );
				}
			);

		try {
			$this->public->handle_opt_in_confirmation();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'wp_die_format_error', $e->getMessage() );
		}
	}

	/**
	 * Test token with special characters is rejected.
	 */
	public function test_token_with_special_chars_rejected(): void {
		$wpdb = $this->setup_wpdb_mock();

		// Token with special characters (32 chars but invalid).
		$_GET['mskd_confirm'] = 'abc123!@#$%^abc123def456abc123de';

		Functions\expect( 'wp_die' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::type( 'string' ),
				Mockery::on(
					function ( $args ) {
						return (bool) esc_html( $args['response'] === 400 );
					}
				)
			)
			->andReturnUsing(
				function () {
					throw new \Exception( 'wp_die_special_chars' );
				}
			);

		try {
			$this->public->handle_opt_in_confirmation();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'wp_die_special_chars', $e->getMessage() );
		}
	}

	/**
	 * Test rate limiting prevents abuse.
	 *
	 * Note: This test verifies that rate limiting is working correctly.
	 */
	public function test_rate_limiting_prevents_abuse(): void {
		$wpdb = $this->setup_wpdb_mock();

		$valid_token = 'abc123def456abc123def456abc12345';

		$_GET['mskd_confirm'] = $valid_token;

		// Already at rate limit (10 attempts).
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( 10 );

		Functions\expect( 'set_transient' )
			->once()
			->andReturn( true );

		// Valid subscriber found.
		$subscriber = (object) array(
			'id'     => 123,
			'email'  => 'user@example.com',
			'status' => 'active',
		);
		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $subscriber );

		// Should increment to 11.
		Functions\expect( 'set_transient' )
			->once()
			->with(
				Mockery::type( 'string' ),
				6,
				5 * 60 // 5 minutes in seconds.
			)
			->andReturn( true );

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		Functions\expect( 'wp_die' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::type( 'string' ),
				Mockery::on(
					function ( $args ) {
						return (bool) esc_html( $args['response'] === 429 ); // Too Many Requests.
					}
				)
			)
			->andReturnUsing(
				function () {
					throw new \Exception( 'rate_limited' );
				}
			);

		try {
			$this->public->handle_opt_in_confirmation();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'rate_limited', $e->getMessage() );
		}
	}

	/**
	 * Test rate limit counter is incremented.
	 *
	 * Note: This test verifies that the rate limit counter is incremented correctly.
	 */
	public function test_rate_limit_counter_incremented(): void {
		$wpdb = $this->setup_wpdb_mock();

		$valid_token = 'abc123def456abc123def456abc12345';

		$_GET['mskd_confirm'] = $valid_token;

		// 5 previous attempts.
		Functions\expect( 'get_transient' )
			->once()
			->andReturn( 5 );

		Functions\expect( 'set_transient' )
			->once()
			->with(
				Mockery::type( 'string' ),
				6,
				5 * 60 // 5 minutes in seconds.
			)
			->andReturn( true );

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		Functions\expect( 'wp_die' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::type( 'string' ),
				Mockery::on(
					function ( $args ) {
						return (bool) esc_html( $args['response'] === 429 ); // Too Many Requests.
					}
				)
			)
			->andReturnUsing(
				function () {
					throw new \Exception( 'rate_limited' );
				}
			);

		try {
			$this->public->handle_opt_in_confirmation();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'rate_limited', $e->getMessage() );
		}
	}

	/**
	 * Test unsubscribe changes status to unsubscribed.
	 *
	 * Note: This test verifies that database update is called with correct data.
	 * We throw an exception after the update to prevent reaching include/exit.
	 */
	public function test_unsubscribe_changes_status_to_unsubscribed(): void {
		$wpdb = $this->setup_wpdb_mock();

		$valid_token = 'abc123def456abc123def456abc12345';

		$_GET['mskd_confirm'] = $valid_token;

		$update_called = false;
		$wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_mskd_subscribers',
				array(
					'status'       => 'unsubscribed',
					'opt_in_token' => null,
				),
				array( 'id' => 123 ),
				Mockery::type( 'array' ),
				Mockery::type( 'array' ),
				Mockery::on(
					function ( $args ) {
						return $args['status'] === 'unsubscribed'
							&& array_key_exists( 'opt_in_token', $args )
							&& null === $args['opt_in_token'];
					}
				)
			)
			->andReturnUsing(
				function () use ( &$update_called ) {
					$update_called = true;
					// Throw to prevent reaching include/exit.
					throw new \Exception( self::TEST_COMPLETE_EXCEPTION );
				}
			);

		try {
			$this->public->handle_opt_in_confirmation();
		} catch ( \Exception $e ) {
			$this->assertEquals( self::TEST_COMPLETE_EXCEPTION, $e->getMessage() );
		}
	}

	/**
	 * Test that no query param does nothing.
	 */
	public function test_no_query_param_does_nothing(): void {
		$wpdb = $this->setup_wpdb_mock();

		// No confirm param.
		unset( $_GET['mskd_confirm'] );

		// Should return early without doing anything.
		Functions\expect( 'wp_die' )->never();
		Functions\expect( 'get_transient' )->never();
		Functions\expect( 'set_transient' )->never();

		$this->public->handle_opt_in_confirmation();

		// If we get here without exceptions, the test passes.
		$this->assertTrue( true );
	}

	/**
	 * Test empty token does nothing.
	 */
	public function test_empty_token_does_nothing(): void {
		$wpdb = $this->setup_wpdb_mock();

		$_GET['mskd_confirm'] = '';

		// Should return early.
		Functions\expect( 'wp_die' )->never();
		Functions\expect( 'get_transient' )->never();
		Functions\expect( 'set_transient' )->never();

		$this->public->handle_opt_in_confirmation();

		// If we get here without exceptions, the test passes.
		$this->assertTrue( true );
	}

	/**
	 * Test missing REMOTE_ADDR returns error.
	 */
	public function test_missing_remote_addr_returns_error(): void {
		$wpdb = $this->setup_wpdb_mock();

		$valid_token = 'abc123def456abc123def456abc12345';

		$_GET['mskd_confirm'] = $valid_token;

		// Remove REMOTE_ADDR.
		unset( $_SERVER['REMOTE_ADDR'] );

		Functions\expect( 'wp_die' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::type( 'string' ),
				Mockery::on(
					function ( $args ) {
						return (bool) esc_html( $args['response'] === 400 );
					}
				)
			)
			->andReturnUsing(
				function () {
					throw new \Exception( 'wp_die_no_ip' );
				}
			);

		try {
			$this->public->handle_opt_in_confirmation();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'wp_die_no_ip', $e->getMessage() );
		}
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		unset( $_GET['mskd_confirm'] );
		unset( $_SERVER['REMOTE_ADDR'] );
		parent::tearDown();
	}
}
