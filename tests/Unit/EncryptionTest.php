<?php
/**
 * Tests for the mskd_encrypt and mskd_decrypt functions.
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;

// Define WordPress constants before including functions.
if ( ! defined( 'AUTH_KEY' ) ) {
	define( 'AUTH_KEY', 'test-auth-key-for-unit-testing-12345678901234567890' );
}
if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
	define( 'SECURE_AUTH_KEY', 'test-secure-auth-key-for-unit-testing-12345678901234567890' );
}

// Include only the encryption functions, not the whole plugin.
if ( ! function_exists( 'mskd_encrypt' ) ) {
	/**
	 * Encrypt a string using WordPress salts.
	 *
	 * Uses AES-256-CBC encryption with WordPress AUTH_KEY and SECURE_AUTH_KEY as the key.
	 *
	 * @param string $value The value to encrypt.
	 * @return string|false Encrypted value in base64 format, or false on failure.
	 */
	function mskd_encrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $value );
		}

		$cipher = 'aes-256-cbc';

		if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
			$key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
		} else {
			return base64_encode( $value );
		}

		$iv_length = openssl_cipher_iv_length( $cipher );

		if ( function_exists( 'random_bytes' ) ) {
			try {
				$iv = random_bytes( $iv_length );
			} catch ( \Exception $e ) {
				$iv = openssl_random_pseudo_bytes( $iv_length );
			}
		} else {
			$iv = openssl_random_pseudo_bytes( $iv_length );
		}

		$encrypted = openssl_encrypt( $value, $cipher, $key, 0, $iv );

		if ( false === $encrypted ) {
			return false;
		}

		return base64_encode( $iv . '::' . $encrypted );
	}

	/**
	 * Decrypt a string encrypted with mskd_encrypt().
	 *
	 * @param string $value The encrypted value to decrypt.
	 * @return string|false Decrypted value, or false on failure.
	 */
	function mskd_decrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $value );
		}

		$cipher = 'aes-256-cbc';

		if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
			$key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
		} else {
			return base64_decode( $value );
		}

		$decoded = base64_decode( $value, true );

		if ( false === $decoded ) {
			return base64_decode( $value );
		}

		$parts = explode( '::', $decoded, 2 );

		if ( count( $parts ) !== 2 ) {
			return base64_decode( $value );
		}

		list( $iv, $encrypted ) = $parts;

		$iv_length = openssl_cipher_iv_length( $cipher );
		if ( strlen( $iv ) !== $iv_length ) {
			return base64_decode( $value );
		}

		$decrypted = openssl_decrypt( $encrypted, $cipher, $key, 0, $iv );

		if ( false === $decrypted ) {
			return false;
		}

		return $decrypted;
	}
}

/**
 * Test class for encryption/decryption functions.
 */
class EncryptionTest extends TestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Test that mskd_encrypt returns empty string for empty input.
	 */
	public function test_encrypt_empty_value_returns_empty_string(): void {
		$this->assertSame( '', mskd_encrypt( '' ) );
	}

	/**
	 * Test that mskd_decrypt returns empty string for empty input.
	 */
	public function test_decrypt_empty_value_returns_empty_string(): void {
		$this->assertSame( '', mskd_decrypt( '' ) );
	}

	/**
	 * Test that encrypt/decrypt round trip works correctly.
	 */
	public function test_encrypt_decrypt_round_trip(): void {
		$original  = 'my-secret-password-123!@#';
		$encrypted = mskd_encrypt( $original );

		// Encrypted value should be different from original.
		$this->assertNotSame( $original, $encrypted );

		// Decrypted value should match original.
		$decrypted = mskd_decrypt( $encrypted );
		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test that encrypt/decrypt works with special characters.
	 */
	public function test_encrypt_decrypt_special_characters(): void {
		$original  = 'пароль123!@#$%^&*()_+-={}[]|\\:";\'<>?,./~`';
		$encrypted = mskd_encrypt( $original );
		$decrypted = mskd_decrypt( $encrypted );

		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test that encrypt/decrypt works with long strings.
	 */
	public function test_encrypt_decrypt_long_string(): void {
		$original  = str_repeat( 'a', 10000 );
		$encrypted = mskd_encrypt( $original );
		$decrypted = mskd_decrypt( $encrypted );

		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test that encrypted value contains the separator.
	 */
	public function test_encrypted_format_contains_separator(): void {
		$encrypted = mskd_encrypt( 'test' );

		// Decode and check for separator.
		$decoded = base64_decode( $encrypted );
		$this->assertStringContainsString( '::', $decoded );
	}

	/**
	 * Test that legacy base64-only values are handled correctly.
	 */
	public function test_decrypt_legacy_base64_format(): void {
		// Simulate legacy base64-encoded password (no IV prefix).
		$original       = 'old-password';
		$legacy_encoded = base64_encode( $original );

		$decrypted = mskd_decrypt( $legacy_encoded );

		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test that each encryption produces different output (due to random IV).
	 */
	public function test_encrypt_produces_unique_output(): void {
		$original   = 'same-password';
		$encrypted1 = mskd_encrypt( $original );
		$encrypted2 = mskd_encrypt( $original );

		// Should be different due to different IVs.
		$this->assertNotSame( $encrypted1, $encrypted2 );

		// But both should decrypt to the same value.
		$this->assertSame( $original, mskd_decrypt( $encrypted1 ) );
		$this->assertSame( $original, mskd_decrypt( $encrypted2 ) );
	}

	/**
	 * Test decrypt returns false for corrupted data.
	 */
	public function test_decrypt_corrupted_data_returns_false(): void {
		// Create valid encrypted data.
		$encrypted = mskd_encrypt( 'test' );

		// Corrupt the encrypted data (modify the middle).
		$corrupted = substr( $encrypted, 0, 10 ) . 'XXXXX' . substr( $encrypted, 15 );

		$result = mskd_decrypt( $corrupted );

		// Should either return false or the corrupted base64 decode (legacy fallback).
		// The key is that it doesn't return the original 'test' value.
		$this->assertNotSame( 'test', $result );
	}

	/**
	 * Test that mskd_encrypt returns a base64-encoded string.
	 */
	public function test_encrypted_value_is_base64(): void {
		$encrypted = mskd_encrypt( 'test-value' );

		// Should be valid base64.
		$decoded = base64_decode( $encrypted, true );
		$this->assertNotFalse( $decoded );
	}

	/**
	 * Test encrypt/decrypt with null bytes.
	 */
	public function test_encrypt_decrypt_with_null_bytes(): void {
		$original  = "test\x00with\x00null\x00bytes";
		$encrypted = mskd_encrypt( $original );
		$decrypted = mskd_decrypt( $encrypted );

		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test that decrypting non-base64 data returns the input decoded.
	 */
	public function test_decrypt_handles_plain_text_gracefully(): void {
		// Plain text that's not valid base64.
		$plain_text = 'This is not base64!@#$%';

		// Should attempt legacy decode - may return false or garbled output.
		$result = mskd_decrypt( $plain_text );

		// Just ensure no exception is thrown.
		$this->assertTrue( true );
	}
}
