<?php
/**
 * List Subscriber DAO
 *
 * Data Access Object for external list subscribers.
 * Encapsulates subscriber data structure and validation.
 *
 * @package MSKD
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSKD_List_Subscriber
 *
 * Represents a subscriber from an external list callback.
 *
 * Required fields:
 * - email (string) - Valid email address
 *
 * Optional fields:
 * - first_name (string) - Subscriber's first name
 * - last_name (string) - Subscriber's last name
 *
 * @since 1.3.0
 */
class MSKD_List_Subscriber {

	/**
	 * Subscriber email address.
	 *
	 * @var string
	 */
	private $email;

	/**
	 * Subscriber first name.
	 *
	 * @var string
	 */
	private $first_name;

	/**
	 * Subscriber last name.
	 *
	 * @var string
	 */
	private $last_name;

	/**
	 * Constructor.
	 *
	 * @param string $email      Required. Valid email address.
	 * @param string $first_name Optional. First name.
	 * @param string $last_name  Optional. Last name.
	 *
	 * @throws InvalidArgumentException If email is invalid.
	 */
	public function __construct( string $email, string $first_name = '', string $last_name = '' ) {
		$sanitized_email = sanitize_email( $email );

		if ( empty( $sanitized_email ) || ! is_email( $sanitized_email ) ) {
			throw new InvalidArgumentException( 'Invalid email address: ' . esc_html( $email ) );
		}

		$this->email      = $sanitized_email;
		$this->first_name = sanitize_text_field( $first_name );
		$this->last_name  = sanitize_text_field( $last_name );
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Subscriber data array with 'email' required, 'first_name' and 'last_name' optional.
	 * @return self|null Subscriber object or null if invalid.
	 */
	public static function from_array( array $data ): ?self {
		if ( empty( $data['email'] ) ) {
			return null;
		}

		try {
			return new self(
				$data['email'],
				$data['first_name'] ?? '',
				$data['last_name'] ?? ''
			);
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Create multiple subscribers from callback result.
	 *
	 * @param array $subscribers_data Array of subscriber data arrays.
	 * @return self[] Array of valid MSKD_List_Subscriber objects.
	 */
	public static function from_callback_result( array $subscribers_data ): array {
		$subscribers = array();

		foreach ( $subscribers_data as $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}

			$subscriber = self::from_array( $data );
			if ( null !== $subscriber ) {
				$subscribers[] = $subscriber;
			}
		}

		return $subscribers;
	}

	/**
	 * Validate callback result format.
	 *
	 * Checks if the callback result follows the expected format:
	 * array of arrays, each with at least 'email' key.
	 *
	 * @param mixed $result Callback result to validate.
	 * @return bool True if valid format, false otherwise.
	 */
	public static function is_valid_callback_result( $result ): bool {
		if ( ! is_array( $result ) || empty( $result ) ) {
			return false;
		}

		$first = reset( $result );

		return is_array( $first ) && isset( $first['email'] );
	}

	/**
	 * Get email address.
	 *
	 * @return string
	 */
	public function get_email(): string {
		return $this->email;
	}

	/**
	 * Get first name.
	 *
	 * @return string
	 */
	public function get_first_name(): string {
		return $this->first_name;
	}

	/**
	 * Get last name.
	 *
	 * @return string
	 */
	public function get_last_name(): string {
		return $this->last_name;
	}

	/**
	 * Get full name.
	 *
	 * @return string First and last name combined, or empty string if both are empty.
	 */
	public function get_full_name(): string {
		return trim( $this->first_name . ' ' . $this->last_name );
	}

	/**
	 * Convert to array.
	 *
	 * @return array Subscriber data as array.
	 */
	public function to_array(): array {
		return array(
			'email'      => $this->email,
			'first_name' => $this->first_name,
			'last_name'  => $this->last_name,
		);
	}

	/**
	 * Convert to object (for compatibility with database subscriber format).
	 *
	 * @return object Subscriber data as stdClass object.
	 */
	public function to_object(): object {
		$obj              = new stdClass();
		$obj->email       = $this->email;
		$obj->first_name  = $this->first_name;
		$obj->last_name   = $this->last_name;
		$obj->source      = 'external';
		$obj->is_editable = false;

		return $obj;
	}

	/**
	 * Convert to JSON string (for queue storage).
	 *
	 * @return string JSON encoded subscriber data.
	 */
	public function to_json(): string {
		return wp_json_encode( $this->to_array() );
	}
}
