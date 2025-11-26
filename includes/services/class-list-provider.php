<?php
/**
 * List Provider Service
 *
 * Provides hooks for third-party plugins to register automated/external lists.
 *
 * @package MSKD
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSKD_List_Provider
 *
 * Handles list retrieval with support for external/automated lists via hooks.
 *
 * Third-party plugins can register lists using the 'mskd_register_external_lists' filter.
 * External lists are non-editable and provide their own subscriber data.
 *
 * @since 1.1.0
 */
class MSKD_List_Provider {

	/**
	 * Cache key for external lists.
	 *
	 * @var string
	 */
	const EXTERNAL_LISTS_CACHE_KEY = 'mskd_external_lists';

	/**
	 * Cache expiration in seconds.
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 300;

	/**
	 * Get all lists (database + external).
	 *
	 * @since 1.1.0
	 *
	 * @return array Array of list objects with 'source' property indicating origin.
	 */
	public static function get_all_lists() {
		$database_lists = self::get_database_lists();
		$external_lists = self::get_external_lists();

		return array_merge( $database_lists, $external_lists );
	}

	/**
	 * Get lists from the database.
	 *
	 * @since 1.1.0
	 *
	 * @return array Array of database list objects.
	 */
	public static function get_database_lists() {
		global $wpdb;

		$lists = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}mskd_lists ORDER BY name ASC"
		);

		if ( ! $lists ) {
			return array();
		}

		// Add metadata for database lists.
		foreach ( $lists as $list ) {
			$list->source      = 'database';
			$list->is_editable = true;
			$list->provider    = null;
		}

		return $lists;
	}

	/**
	 * Get external/automated lists registered via filter.
	 *
	 * Third-party plugins can register lists using the 'mskd_register_external_lists' filter.
	 *
	 * Each external list should be an array with the following keys:
	 * - 'id' (string|int) - Unique identifier for the list (required, prefixed with 'ext_')
	 * - 'name' (string) - Display name for the list (required)
	 * - 'description' (string) - Description of the list (optional)
	 * - 'provider' (string) - Name of the plugin/provider (optional)
	 * - 'subscriber_callback' (callable) - Callback that returns subscriber IDs or emails (optional)
	 *
	 * @since 1.1.0
	 *
	 * @return array Array of external list objects.
	 */
	public static function get_external_lists() {
		/**
		 * Filter to register external/automated lists.
		 *
		 * @since 1.1.0
		 *
		 * @param array $external_lists Array of external list definitions.
		 *                              Each list should have: id, name, description (optional),
		 *                              provider (optional), subscriber_callback (optional).
		 */
		$external_lists = apply_filters( 'mskd_register_external_lists', array() );

		if ( ! is_array( $external_lists ) ) {
			return array();
		}

		$formatted_lists = array();

		foreach ( $external_lists as $list_data ) {
			$formatted_list = self::format_external_list( $list_data );
			if ( $formatted_list ) {
				$formatted_lists[] = $formatted_list;
			}
		}

		return $formatted_lists;
	}

	/**
	 * Format and validate external list data.
	 *
	 * @since 1.1.0
	 *
	 * @param array $list_data Raw list data from filter.
	 * @return object|null Formatted list object or null if invalid.
	 */
	private static function format_external_list( $list_data ) {
		// Validate required fields.
		if ( ! isset( $list_data['id'] ) || ! isset( $list_data['name'] ) ) {
			return null;
		}

		$list = new stdClass();

		// External list IDs are prefixed with 'ext_' to avoid collision with database IDs.
		$list->id                  = 'ext_' . sanitize_key( $list_data['id'] );
		$list->name                = sanitize_text_field( $list_data['name'] );
		$list->description         = isset( $list_data['description'] ) ? sanitize_textarea_field( $list_data['description'] ) : '';
		$list->created_at          = current_time( 'mysql' );
		$list->source              = 'external';
		$list->is_editable         = false;
		$list->provider            = isset( $list_data['provider'] ) ? sanitize_text_field( $list_data['provider'] ) : __( 'External', 'mail-system-by-katsarov-design' );
		$list->subscriber_callback = isset( $list_data['subscriber_callback'] ) && is_callable( $list_data['subscriber_callback'] )
			? $list_data['subscriber_callback']
			: null;

		return $list;
	}

	/**
	 * Get a single list by ID.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int $list_id List ID (numeric for database, 'ext_*' for external).
	 * @return object|null List object or null if not found.
	 */
	public static function get_list( $list_id ) {
		// Check if it's an external list.
		if ( is_string( $list_id ) && strpos( $list_id, 'ext_' ) === 0 ) {
			$external_lists = self::get_external_lists();
			foreach ( $external_lists as $list ) {
				if ( $list->id === $list_id ) {
					return $list;
				}
			}
			return null;
		}

		// Database list.
		global $wpdb;
		$list = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mskd_lists WHERE id = %d",
				intval( $list_id )
			)
		);

		if ( $list ) {
			$list->source      = 'database';
			$list->is_editable = true;
			$list->provider    = null;
		}

		return $list;
	}

	/**
	 * Check if a list is editable.
	 *
	 * Database lists are editable by default.
	 * External lists are not editable.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int $list_id List ID.
	 * @return bool True if editable, false otherwise.
	 */
	public static function is_list_editable( $list_id ) {
		// External lists are never editable.
		if ( is_string( $list_id ) && strpos( $list_id, 'ext_' ) === 0 ) {
			return false;
		}

		/**
		 * Filter whether a database list is editable.
		 *
		 * @since 1.1.0
		 *
		 * @param bool       $is_editable Whether the list is editable. Default true for database lists.
		 * @param string|int $list_id     The list ID.
		 */
		return apply_filters( 'mskd_list_is_editable', true, $list_id );
	}

	/**
	 * Get subscriber count for a list.
	 *
	 * For database lists, counts from the pivot table.
	 * For external lists, uses the subscriber callback if provided.
	 *
	 * @since 1.1.0
	 *
	 * @param object $list List object.
	 * @return int Subscriber count.
	 */
	public static function get_list_subscriber_count( $list ) {
		if ( $list->source === 'external' ) {
			if ( isset( $list->subscriber_callback ) && is_callable( $list->subscriber_callback ) ) {
				$subscribers = call_user_func( $list->subscriber_callback );
				return is_array( $subscribers ) ? count( $subscribers ) : 0;
			}
			return 0;
		}

		// Database list.
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscriber_list WHERE list_id = %d",
				intval( $list->id )
			)
		);
	}

	/**
	 * Get active subscriber count for a list.
	 *
	 * @since 1.1.0
	 *
	 * @param object $list List object.
	 * @return int Active subscriber count.
	 */
	public static function get_list_active_subscriber_count( $list ) {
		if ( $list->source === 'external' ) {
			return self::get_external_list_active_count( $list );
		}

		// Database list.
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscriber_list sl
				INNER JOIN {$wpdb->prefix}mskd_subscribers s ON sl.subscriber_id = s.id
				WHERE sl.list_id = %d AND s.status = 'active'",
				intval( $list->id )
			)
		);
	}

	/**
	 * Get active subscriber count for an external list.
	 *
	 * @since 1.1.0
	 *
	 * @param object $list External list object.
	 * @return int Active subscriber count.
	 */
	private static function get_external_list_active_count( $list ) {
		if ( ! isset( $list->subscriber_callback ) || ! is_callable( $list->subscriber_callback ) ) {
			return 0;
		}

		$subscribers = call_user_func( $list->subscriber_callback );
		if ( ! is_array( $subscribers ) || empty( $subscribers ) ) {
			return 0;
		}

		// Check if subscribers are IDs or emails.
		$first = reset( $subscribers );

		if ( is_numeric( $first ) ) {
			// Subscriber IDs - count active.
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $subscribers ), '%d' ) );
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscribers 
					WHERE id IN ($placeholders) AND status = 'active'",
					$subscribers
				)
			);
		}

		if ( is_email( $first ) ) {
			// Email addresses - count those that exist and are active.
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $subscribers ), '%s' ) );
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscribers 
					WHERE email IN ($placeholders) AND status = 'active'",
					$subscribers
				)
			);
		}

		return 0;
	}

	/**
	 * Get active subscriber IDs for a list.
	 *
	 * @since 1.1.0
	 *
	 * @param object|string|int $list List object or list ID.
	 * @return array Array of subscriber IDs.
	 */
	public static function get_list_subscriber_ids( $list ) {
		if ( ! is_object( $list ) ) {
			$list = self::get_list( $list );
		}

		if ( ! $list ) {
			return array();
		}

		if ( $list->source === 'external' ) {
			return self::get_external_list_subscriber_ids( $list );
		}

		// Database list.
		global $wpdb;
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT s.id FROM {$wpdb->prefix}mskd_subscribers s
				INNER JOIN {$wpdb->prefix}mskd_subscriber_list sl ON s.id = sl.subscriber_id
				WHERE sl.list_id = %d AND s.status = 'active'",
				intval( $list->id )
			)
		);
	}

	/**
	 * Get subscriber IDs for an external list.
	 *
	 * @since 1.1.0
	 *
	 * @param object $list External list object.
	 * @return array Array of subscriber IDs.
	 */
	private static function get_external_list_subscriber_ids( $list ) {
		if ( ! isset( $list->subscriber_callback ) || ! is_callable( $list->subscriber_callback ) ) {
			return array();
		}

		$subscribers = call_user_func( $list->subscriber_callback );
		if ( ! is_array( $subscribers ) || empty( $subscribers ) ) {
			return array();
		}

		$first = reset( $subscribers );

		// If already IDs, filter to active subscribers.
		if ( is_numeric( $first ) ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $subscribers ), '%d' ) );
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}mskd_subscribers 
					WHERE id IN ($placeholders) AND status = 'active'",
					$subscribers
				)
			);
		}

		// If emails, convert to IDs.
		if ( is_email( $first ) ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $subscribers ), '%s' ) );
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}mskd_subscribers 
					WHERE email IN ($placeholders) AND status = 'active'",
					$subscribers
				)
			);
		}

		return array();
	}

	/**
	 * Check if a list exists.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int $list_id List ID.
	 * @return bool True if exists, false otherwise.
	 */
	public static function list_exists( $list_id ) {
		return self::get_list( $list_id ) !== null;
	}

	/**
	 * Invalidate external lists cache.
	 *
	 * Call this when external list data may have changed.
	 *
	 * @since 1.1.0
	 */
	public static function invalidate_cache() {
		delete_transient( self::EXTERNAL_LISTS_CACHE_KEY );
	}
}
