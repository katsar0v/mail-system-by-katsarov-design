<?php
/**
 * List Service
 *
 * Handles all mailing list-related database operations.
 *
 * @package MSKD\Services
 * @since   1.1.0
 */

namespace MSKD\Services;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class List_Service
 *
 * Service layer for mailing list CRUD operations.
 */
class List_Service {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Lists table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Subscriber-list pivot table name.
	 *
	 * @var string
	 */
	private $pivot_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb        = $wpdb;
		$this->table       = $wpdb->prefix . 'mskd_lists';
		$this->pivot_table = $wpdb->prefix . 'mskd_subscriber_list';
	}

	/**
	 * Get all lists.
	 *
	 * @param array $args {
	 *     Optional. Arguments for filtering lists.
	 *
	 *     @type string $orderby Column to order by. Default 'name'.
	 *     @type string $order   Order direction (ASC or DESC). Default 'ASC'.
	 * }
	 * @return array Array of list objects.
	 */
	public function get_all( array $args = array() ): array {
		$defaults = array(
			'orderby' => 'name',
			'order'   => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate orderby to prevent SQL injection.
		$allowed_orderby = array( 'id', 'name', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$results = $this->wpdb->get_results(
			"SELECT * FROM {$this->table} ORDER BY {$orderby} {$order}"
		);

		return $results ?: array();
	}

	/**
	 * Get a list by ID.
	 *
	 * @param int $id List ID.
	 * @return object|null List object or null if not found.
	 */
	public function get_by_id( int $id ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get a list by name.
	 *
	 * @param string $name List name.
	 * @return object|null List object or null if not found.
	 */
	public function get_by_name( string $name ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE name = %s",
				$name
			)
		);
	}

	/**
	 * Create a new list.
	 *
	 * @param array $data {
	 *     List data.
	 *
	 *     @type string $name        Required. List name.
	 *     @type string $description Optional. List description.
	 * }
	 * @return int|false List ID on success, false on failure.
	 */
	public function create( array $data ) {
		$defaults = array(
			'name'        => '',
			'description' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $this->wpdb->insert(
			$this->table,
			array(
				'name'        => $data['name'],
				'description' => $data['description'],
			),
			array( '%s', '%s' )
		);

		if ( $result ) {
			return $this->wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a list.
	 *
	 * @param int   $id   List ID.
	 * @param array $data Data to update (name, description).
	 * @return bool True on success, false on failure.
	 */
	public function update( int $id, array $data ): bool {
		$allowed_fields = array( 'name', 'description' );
		$update_data    = array();
		$format         = array();

		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
				$format[]              = '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Delete a list.
	 *
	 * @param int $id List ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $id ): bool {
		// Delete from pivot table.
		$this->wpdb->delete(
			$this->pivot_table,
			array( 'list_id' => $id ),
			array( '%d' )
		);

		// Delete list.
		$result = $this->wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get subscriber count for a list.
	 *
	 * @param int    $list_id List ID.
	 * @param string $status  Optional. Filter by subscriber status.
	 * @return int Subscriber count.
	 */
	public function get_subscriber_count( int $list_id, string $status = '' ): int {
		if ( ! empty( $status ) ) {
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(DISTINCT sl.subscriber_id) 
                     FROM {$this->pivot_table} sl 
                     INNER JOIN {$this->wpdb->prefix}mskd_subscribers s ON sl.subscriber_id = s.id 
                     WHERE sl.list_id = %d AND s.status = %s",
					$list_id,
					$status
				)
			);
		}

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->pivot_table} WHERE list_id = %d",
				$list_id
			)
		);
	}

	/**
	 * Get subscribers for a list.
	 *
	 * @param int    $list_id List ID.
	 * @param string $status  Optional. Filter by subscriber status.
	 * @return array Array of subscriber objects.
	 */
	public function get_subscribers( int $list_id, string $status = '' ): array {
		$where = '';
		if ( ! empty( $status ) ) {
			$where = $this->wpdb->prepare( ' AND s.status = %s', $status );
		}

		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT s.* 
                 FROM {$this->wpdb->prefix}mskd_subscribers s 
                 INNER JOIN {$this->pivot_table} sl ON s.id = sl.subscriber_id 
                 WHERE sl.list_id = %d{$where}
                 ORDER BY s.email ASC",
				$list_id
			)
		);

		return $results ?: array();
	}

	/**
	 * Get total list count.
	 *
	 * @return int Total count.
	 */
	public function count(): int {
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Truncate all lists (for admin use).
	 *
	 * @return bool True on success.
	 */
	public function truncate_all(): bool {
		// First truncate the pivot table.
		$this->wpdb->query( "TRUNCATE TABLE {$this->pivot_table}" );

		// Then truncate the lists table.
		$this->wpdb->query( "TRUNCATE TABLE {$this->table}" );

		return true;
	}

	/**
	 * Get all lists with subscriber counts.
	 *
	 * @return array Array of list objects with 'subscriber_count' property.
	 */
	public function get_all_with_counts(): array {
		$results = $this->wpdb->get_results(
			"SELECT l.*, COUNT(sl.subscriber_id) as subscriber_count 
             FROM {$this->table} l 
             LEFT JOIN {$this->pivot_table} sl ON l.id = sl.list_id 
             GROUP BY l.id 
             ORDER BY l.name ASC"
		);

		return $results ?: array();
	}
}
