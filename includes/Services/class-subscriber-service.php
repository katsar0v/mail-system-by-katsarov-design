<?php
/**
 * Subscriber Service
 *
 * Handles all subscriber-related database operations.
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
 * Class Subscriber_Service
 *
 * Service layer for subscriber CRUD operations.
 */
class Subscriber_Service {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Subscribers table name.
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
     * Queue table name.
     *
     * @var string
     */
    private $queue_table;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb        = $wpdb;
        $this->table       = $wpdb->prefix . 'mskd_subscribers';
        $this->pivot_table = $wpdb->prefix . 'mskd_subscriber_list';
        $this->queue_table = $wpdb->prefix . 'mskd_queue';
    }

    /**
     * Get all subscribers with optional filtering and pagination.
     *
     * @param array $args {
     *     Optional. Arguments for filtering subscribers.
     *
     *     @type string $status   Filter by status (active, inactive, unsubscribed).
     *     @type int    $list_id  Filter by list ID.
     *     @type string $search   Search term for email, first_name, or last_name.
     *     @type int    $per_page Number of results per page. Default 20.
     *     @type int    $page     Current page number. Default 1.
     *     @type string $orderby  Column to order by. Default 'created_at'.
     *     @type string $order    Order direction (ASC or DESC). Default 'DESC'.
     * }
     * @return array {
     *     @type array $items      Array of subscriber objects.
     *     @type int   $total      Total number of subscribers matching criteria.
     *     @type int   $pages      Total number of pages.
     * }
     */
    public function get_all( array $args = array() ): array {
        $defaults = array(
            'status'   => '',
            'list_id'  => 0,
            'search'   => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        // Build WHERE clause.
        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 's.status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where[]  = '(s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s)';
            $search   = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }

        // Handle list filtering with JOIN.
        $join = '';
        if ( ! empty( $args['list_id'] ) ) {
            $join     = "INNER JOIN {$this->pivot_table} sl ON s.id = sl.subscriber_id";
            $where[]  = 'sl.list_id = %d';
            $values[] = $args['list_id'];
        }

        $where_sql = implode( ' AND ', $where );

        // Validate orderby to prevent SQL injection.
        $allowed_orderby = array( 'id', 'email', 'first_name', 'last_name', 'status', 'created_at' );
        $orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Get total count.
        $count_sql = "SELECT COUNT(DISTINCT s.id) FROM {$this->table} s {$join} WHERE {$where_sql}";
        if ( ! empty( $values ) ) {
            $count_sql = $this->wpdb->prepare( $count_sql, $values );
        }
        $total = (int) $this->wpdb->get_var( $count_sql );

        // Calculate pagination.
        $per_page = max( 1, (int) $args['per_page'] );
        $page     = max( 1, (int) $args['page'] );
        $pages    = ceil( $total / $per_page );
        $offset   = ( $page - 1 ) * $per_page;

        // Get items.
        $sql = "SELECT DISTINCT s.* FROM {$this->table} s {$join} WHERE {$where_sql} ORDER BY s.{$orderby} {$order} LIMIT %d OFFSET %d";
        
        $query_values   = $values;
        $query_values[] = $per_page;
        $query_values[] = $offset;

        $items = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $query_values ) );

        return array(
            'items' => $items ?: array(),
            'total' => $total,
            'pages' => $pages,
        );
    }

    /**
     * Get a subscriber by ID.
     *
     * @param int $id Subscriber ID.
     * @return object|null Subscriber object or null if not found.
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
     * Get a subscriber by email.
     *
     * @param string $email Subscriber email.
     * @return object|null Subscriber object or null if not found.
     */
    public function get_by_email( string $email ): ?object {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE email = %s",
                $email
            )
        );
    }

    /**
     * Create a new subscriber.
     *
     * @param array $data {
     *     Subscriber data.
     *
     *     @type string $email      Required. Subscriber email.
     *     @type string $first_name Optional. First name.
     *     @type string $last_name  Optional. Last name.
     *     @type string $status     Optional. Status (active, inactive, unsubscribed). Default 'active'.
     * }
     * @return int|false Subscriber ID on success, false on failure.
     */
    public function create( array $data ) {
        $defaults = array(
            'email'      => '',
            'first_name' => '',
            'last_name'  => '',
            'status'     => 'active',
        );

        $data = wp_parse_args( $data, $defaults );

        // Generate unsubscribe token.
        $token = wp_generate_password( 32, false );

        $result = $this->wpdb->insert(
            $this->table,
            array(
                'email'             => $data['email'],
                'first_name'        => $data['first_name'],
                'last_name'         => $data['last_name'],
                'status'            => $data['status'],
                'unsubscribe_token' => $token,
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $result ) {
            return $this->wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update a subscriber.
     *
     * @param int   $id   Subscriber ID.
     * @param array $data Data to update (email, first_name, last_name, status).
     * @return bool True on success, false on failure.
     */
    public function update( int $id, array $data ): bool {
        $allowed_fields = array( 'email', 'first_name', 'last_name', 'status' );
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
     * Delete a subscriber.
     *
     * @param int $id Subscriber ID.
     * @return bool True on success, false on failure.
     */
    public function delete( int $id ): bool {
        // Delete from pivot table.
        $this->wpdb->delete(
            $this->pivot_table,
            array( 'subscriber_id' => $id ),
            array( '%d' )
        );

        // Delete pending queue items.
        $this->wpdb->delete(
            $this->queue_table,
            array(
                'subscriber_id' => $id,
                'status'        => 'pending',
            ),
            array( '%d', '%s' )
        );

        // Delete subscriber.
        $result = $this->wpdb->delete(
            $this->table,
            array( 'id' => $id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Get lists for a subscriber.
     *
     * @param int $subscriber_id Subscriber ID.
     * @return array Array of list IDs.
     */
    public function get_lists( int $subscriber_id ): array {
        $results = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT list_id FROM {$this->pivot_table} WHERE subscriber_id = %d",
                $subscriber_id
            )
        );

        return array_map( 'intval', $results ?: array() );
    }

    /**
     * Sync subscriber's list associations.
     *
     * Removes all existing associations and adds the new ones.
     *
     * @param int   $subscriber_id Subscriber ID.
     * @param array $list_ids      Array of list IDs to associate.
     * @return void
     */
    public function sync_lists( int $subscriber_id, array $list_ids ): void {
        // Remove all existing associations.
        $this->wpdb->delete(
            $this->pivot_table,
            array( 'subscriber_id' => $subscriber_id ),
            array( '%d' )
        );

        // Add new associations.
        foreach ( $list_ids as $list_id ) {
            $this->wpdb->insert(
                $this->pivot_table,
                array(
                    'subscriber_id' => $subscriber_id,
                    'list_id'       => (int) $list_id,
                ),
                array( '%d', '%d' )
            );
        }
    }

    /**
     * Check if an email already exists.
     *
     * @param string   $email      Email to check.
     * @param int|null $exclude_id Optional. Subscriber ID to exclude from check.
     * @return bool True if email exists, false otherwise.
     */
    public function email_exists( string $email, ?int $exclude_id = null ): bool {
        if ( $exclude_id ) {
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->table} WHERE email = %s AND id != %d",
                    $email,
                    $exclude_id
                )
            );
        } else {
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->table} WHERE email = %s",
                    $email
                )
            );
        }

        return (bool) $exists;
    }

    /**
     * Get total subscriber count.
     *
     * @param string $status Optional. Filter by status.
     * @return int Total count.
     */
    public function count( string $status = '' ): int {
        if ( ! empty( $status ) ) {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
                    $status
                )
            );
        }

        return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
    }

    /**
     * Truncate all subscribers (for admin use).
     *
     * @return bool True on success.
     */
    public function truncate_all(): bool {
        // First truncate the pivot table.
        $this->wpdb->query( "TRUNCATE TABLE {$this->pivot_table}" );

        // Then truncate the subscribers table.
        $this->wpdb->query( "TRUNCATE TABLE {$this->table}" );

        return true;
    }
}
