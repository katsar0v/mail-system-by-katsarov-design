# Extending Lists with Hooks

This document describes how third-party plugins can extend the Mail System by Katsarov Design with automated/external subscriber lists.

## Overview

The Mail System provides hooks that allow third-party plugins to register their own subscriber lists. These external lists:
- Appear in the Lists admin page with an "Automated" badge
- Can be selected when composing emails
- Cannot be edited or deleted through the admin interface
- Provide their own subscriber data dynamically

## Available Hooks

### `mskd_register_external_lists`

**Type:** Filter

**Description:** Register external/automated subscriber lists that will be merged with database lists.

**Parameters:**
- `$external_lists` (array) - Array of external list definitions

**Returns:** array - Modified array of external lists

### `mskd_list_is_editable`

**Type:** Filter

**Description:** Control whether a database list can be edited. External lists are never editable.

**Parameters:**
- `$is_editable` (bool) - Whether the list is editable (default: true for database lists)
- `$list_id` (int) - The list ID

**Returns:** bool

## Registering External Lists

### Basic Example

```php
add_filter( 'mskd_register_external_lists', 'my_plugin_register_lists' );

function my_plugin_register_lists( $lists ) {
    // Add a simple list
    $lists[] = array(
        'id'          => 'my_plugin_premium_users',
        'name'        => 'Premium Users',
        'description' => 'All users with premium subscription',
        'provider'    => 'My Plugin Name',
    );
    
    return $lists;
}
```

### List Definition Properties

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `id` | string\|int | Yes | Unique identifier for the list. Will be prefixed with `ext_` automatically. |
| `name` | string | Yes | Display name shown in the admin interface. |
| `description` | string | No | Description shown in the lists table. |
| `provider` | string | No | Name of the plugin/provider. Default: "Външен" (External). |
| `subscriber_callback` | callable | No | Callback function that returns subscriber IDs or email addresses. |

### With Subscriber Callback

The `subscriber_callback` is the key to providing dynamic subscriber lists. It should return an array of either:
- Subscriber IDs (integers) - References to existing subscribers in the `mskd_subscribers` table
- Email addresses (strings) - These will be matched against existing subscribers

```php
add_filter( 'mskd_register_external_lists', 'my_plugin_register_dynamic_lists' );

function my_plugin_register_dynamic_lists( $lists ) {
    // List with subscriber IDs
    $lists[] = array(
        'id'                  => 'active_customers',
        'name'                => 'Active Customers',
        'description'         => 'Customers with active orders',
        'provider'            => 'WooCommerce Integration',
        'subscriber_callback' => 'get_active_customer_subscribers',
    );
    
    // List with email addresses
    $lists[] = array(
        'id'                  => 'newsletter_opt_in',
        'name'                => 'Newsletter Opt-in',
        'description'         => 'Users who opted in for newsletter',
        'provider'            => 'Custom Forms Plugin',
        'subscriber_callback' => function() {
            global $wpdb;
            // Return emails of users who opted in
            return $wpdb->get_col(
                "SELECT user_email FROM {$wpdb->users} 
                WHERE ID IN (
                    SELECT user_id FROM {$wpdb->usermeta} 
                    WHERE meta_key = 'newsletter_opt_in' AND meta_value = '1'
                )"
            );
        },
    );
    
    return $lists;
}

function get_active_customer_subscribers() {
    global $wpdb;
    
    // Get subscriber IDs for customers with recent orders
    // This assumes you've synced customers with MSKD subscribers
    return $wpdb->get_col(
        "SELECT s.id FROM {$wpdb->prefix}mskd_subscribers s
        INNER JOIN {$wpdb->prefix}wc_customer_lookup c ON s.email = c.email
        WHERE c.date_last_active > DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
}
```

### Real-World Example: WooCommerce Integration

```php
<?php
/**
 * Plugin Name: MSKD WooCommerce Lists
 * Description: Adds automated subscriber lists based on WooCommerce data
 */

class MSKD_WooCommerce_Lists {
    
    public function __construct() {
        add_filter( 'mskd_register_external_lists', array( $this, 'register_lists' ) );
    }
    
    public function register_lists( $lists ) {
        // Recent Customers (last 30 days)
        $lists[] = array(
            'id'                  => 'wc_recent_customers',
            'name'                => 'Скорошни клиенти (30 дни)',
            'description'         => 'Клиенти с поръчка през последните 30 дни',
            'provider'            => 'WooCommerce',
            'subscriber_callback' => array( $this, 'get_recent_customers' ),
        );
        
        // VIP Customers (spent over 500)
        $lists[] = array(
            'id'                  => 'wc_vip_customers',
            'name'                => 'VIP клиенти',
            'description'         => 'Клиенти с над 500лв общо',
            'provider'            => 'WooCommerce',
            'subscriber_callback' => array( $this, 'get_vip_customers' ),
        );
        
        // Abandoned Cart Users
        $lists[] = array(
            'id'                  => 'wc_abandoned_carts',
            'name'                => 'Изоставени колички',
            'description'         => 'Потребители с изоставени колички',
            'provider'            => 'WooCommerce',
            'subscriber_callback' => array( $this, 'get_abandoned_cart_users' ),
        );
        
        return $lists;
    }
    
    public function get_recent_customers() {
        global $wpdb;
        
        return $wpdb->get_col(
            "SELECT DISTINCT s.id 
            FROM {$wpdb->prefix}mskd_subscribers s
            INNER JOIN {$wpdb->prefix}wc_order_stats os 
                ON s.email = (SELECT billing_email FROM {$wpdb->posts} p 
                              JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                              WHERE pm.meta_key = '_billing_email' AND p.ID = os.order_id)
            WHERE os.date_created > DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND s.status = 'active'"
        );
    }
    
    public function get_vip_customers() {
        global $wpdb;
        
        return $wpdb->get_col(
            "SELECT s.id 
            FROM {$wpdb->prefix}mskd_subscribers s
            INNER JOIN {$wpdb->prefix}wc_customer_lookup c ON s.email = c.email
            WHERE c.total_spend > 500
                AND s.status = 'active'"
        );
    }
    
    public function get_abandoned_cart_users() {
        // Implementation depends on abandoned cart plugin used
        return array();
    }
}

new MSKD_WooCommerce_Lists();
```

## Making Database Lists Non-Editable

In some cases, you may want to prevent editing of specific database lists (not external lists). Use the `mskd_list_is_editable` filter:

```php
add_filter( 'mskd_list_is_editable', 'my_plugin_lock_system_lists', 10, 2 );

function my_plugin_lock_system_lists( $is_editable, $list_id ) {
    // Lock specific list IDs
    $locked_lists = array( 1, 2, 3 ); // System list IDs
    
    if ( in_array( $list_id, $locked_lists, true ) ) {
        return false;
    }
    
    return $is_editable;
}
```

## Using the List Provider Service

For advanced use cases, you can use the `MSKD_List_Provider` class directly:

```php
// Load the service
require_once MSKD_PLUGIN_DIR . 'includes/services/class-list-provider.php';

// Get all lists (database + external)
$all_lists = MSKD_List_Provider::get_all_lists();

// Get only database lists
$db_lists = MSKD_List_Provider::get_database_lists();

// Get only external lists
$external_lists = MSKD_List_Provider::get_external_lists();

// Get a specific list by ID
$list = MSKD_List_Provider::get_list( 'ext_my_plugin_users' );

// Check if a list is editable
$editable = MSKD_List_Provider::is_list_editable( $list_id );

// Get subscriber count
$count = MSKD_List_Provider::get_list_subscriber_count( $list );

// Get active subscriber count
$active_count = MSKD_List_Provider::get_list_active_subscriber_count( $list );

// Get subscriber IDs for sending emails
$subscriber_ids = MSKD_List_Provider::get_list_subscriber_ids( $list );
```

## Best Practices

### 1. Use Unique List IDs

Prefix your list IDs with your plugin slug to avoid collisions:

```php
'id' => 'myplugin_my_list',  // Good
'id' => 'my_list',           // Risky
```

### 2. Cache Expensive Queries

If your subscriber callback performs expensive database queries, consider caching:

```php
function get_premium_subscribers() {
    $cached = wp_cache_get( 'myplugin_premium_subs', 'mskd_external_lists' );
    
    if ( false !== $cached ) {
        return $cached;
    }
    
    global $wpdb;
    $subscribers = $wpdb->get_col( /* expensive query */ );
    
    wp_cache_set( 'myplugin_premium_subs', $subscribers, 'mskd_external_lists', HOUR_IN_SECONDS );
    
    return $subscribers;
}
```

### 3. Handle Empty Results Gracefully

Your callback should always return an array:

```php
function get_my_subscribers() {
    $result = some_function();
    return is_array( $result ) ? $result : array();
}
```

### 4. Document Your Lists

Add clear descriptions so administrators understand what each list contains:

```php
$lists[] = array(
    'id'          => 'wc_repeat_customers',
    'name'        => 'Repeat Customers',
    'description' => 'Customers with 3+ orders in the past year. Updated hourly.',
    'provider'    => 'WooCommerce Integration',
);
```

### 5. Consider Performance

When providing subscriber callbacks:
- Return only active/valid subscriber IDs or emails
- Limit query results if lists could be very large
- Consider implementing pagination for extremely large lists

## Troubleshooting

### List not appearing

1. Check that your filter hook runs before admin pages load
2. Verify your list has required `id` and `name` properties
3. Check for PHP errors in debug log

### Subscriber count shows 0

1. Verify your callback returns subscriber IDs or emails that exist in `mskd_subscribers` table
2. Check that returned subscribers have `status = 'active'`
3. Test your callback directly to verify it returns data

### Emails not sending to external list

1. Verify `subscriber_callback` is callable
2. Check that returned IDs/emails match active subscribers
3. Review the email queue for any error messages

## API Reference

### MSKD_List_Provider Class

| Method | Description | Returns |
|--------|-------------|---------|
| `get_all_lists()` | Get all lists (database + external) | array |
| `get_database_lists()` | Get only database lists | array |
| `get_external_lists()` | Get external lists from filter | array |
| `get_list( $list_id )` | Get a single list by ID | object\|null |
| `is_list_editable( $list_id )` | Check if list can be edited | bool |
| `get_list_subscriber_count( $list )` | Get total subscriber count | int |
| `get_list_active_subscriber_count( $list )` | Get active subscriber count | int |
| `get_list_subscriber_ids( $list )` | Get active subscriber IDs | array |
| `get_list_subscribers_full( $list )` | Get full subscriber objects | array |
| `list_exists( $list_id )` | Check if list exists | bool |
| `invalidate_cache()` | Clear external lists cache | void |
| `get_all_subscribers( $args )` | Get all subscribers (database + external) | array |
| `get_database_subscribers( $args )` | Get only database subscribers | array |
| `get_external_subscribers( $args )` | Get external subscribers from filter | array |
| `get_subscriber( $id )` | Get a single subscriber by ID | object\|null |
| `is_external_id( $id )` | Check if ID is external (ext_*) | bool |
| `is_subscriber_editable( $id )` | Check if subscriber can be edited | bool |
| `get_total_subscriber_count( $status )` | Get total subscriber count | int |

---

# Extending Subscribers with Hooks

This section describes how third-party plugins can register external subscribers that appear in the Mail System alongside database subscribers.

## Overview

External subscribers:
- Appear in the Subscribers admin page with an "External" badge
- Are marked as read-only (cannot be edited or deleted)
- Can receive emails when included in external lists
- Do not require storage in the `mskd_subscribers` table

## Available Hooks

### `mskd_register_external_subscribers`

**Type:** Filter

**Description:** Register external subscribers that will be merged with database subscribers.

**Parameters:**
- `$external_subscribers` (array) - Array of external subscriber definitions
- `$args` (array) - Query arguments (status filter, pagination)

**Returns:** array - Modified array of external subscribers

### `mskd_subscriber_is_editable`

**Type:** Filter

**Description:** Control whether a database subscriber can be edited. External subscribers are never editable.

**Parameters:**
- `$is_editable` (bool) - Whether the subscriber is editable (default: true for database)
- `$subscriber_id` (int) - The subscriber ID

**Returns:** bool

### `mskd_external_list_subscribers_full`

**Type:** Filter

**Description:** Get full subscriber data for an external list. Used when queuing emails.

**Parameters:**
- `$subscribers` (array) - Array from subscriber_callback
- `$list` (object) - The external list object

**Returns:** array - Array of subscriber data arrays/objects with email, first_name, last_name

## Registering External Subscribers

### Basic Example

```php
add_filter( 'mskd_register_external_subscribers', 'my_plugin_register_subscribers', 10, 2 );

function my_plugin_register_subscribers( $subscribers, $args ) {
    // Add WordPress users as external subscribers
    $users = get_users( array( 'role' => 'subscriber' ) );
    
    foreach ( $users as $user ) {
        $subscribers[] = array(
            'id'         => 'wp_user_' . $user->ID,
            'email'      => $user->user_email,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'status'     => 'active',
            'provider'   => 'WordPress Users',
            'lists'      => array( 'ext_wp_subscribers' ), // Optional: link to external lists
        );
    }
    
    return $subscribers;
}
```

### Subscriber Definition Properties

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `id` | string\|int | Yes | Unique identifier. Will be prefixed with `ext_` automatically. |
| `email` | string | Yes | Valid email address. |
| `first_name` | string | No | Subscriber's first name. |
| `last_name` | string | No | Subscriber's last name. |
| `status` | string | No | Status: `active`, `inactive`, `unsubscribed`. Default: `active`. |
| `provider` | string | No | Name of the plugin/provider. Default: "External". |
| `lists` | array | No | Array of list IDs this subscriber belongs to. |

### WooCommerce Customer Example

```php
add_filter( 'mskd_register_external_subscribers', 'woo_register_customers', 10, 2 );

function woo_register_customers( $subscribers, $args ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return $subscribers;
    }
    
    // Get customers with orders
    $customers = get_users( array(
        'role'   => 'customer',
        'number' => 100,
    ) );
    
    foreach ( $customers as $customer ) {
        $subscribers[] = array(
            'id'         => 'woo_customer_' . $customer->ID,
            'email'      => $customer->user_email,
            'first_name' => get_user_meta( $customer->ID, 'billing_first_name', true ),
            'last_name'  => get_user_meta( $customer->ID, 'billing_last_name', true ),
            'status'     => 'active',
            'provider'   => 'WooCommerce',
            'lists'      => array( 'ext_woo_customers' ),
        );
    }
    
    return $subscribers;
}
```

## External List with Full Subscriber Data

When your external list contains subscribers who don't exist in the MSKD database, you need to provide full subscriber data for email sending:

```php
// Register the external list
add_filter( 'mskd_register_external_lists', function( $lists ) {
    $lists[] = array(
        'id'                  => 'crm_contacts',
        'name'                => 'CRM Contacts',
        'description'         => 'Contacts from external CRM',
        'provider'            => 'My CRM Plugin',
        'subscriber_callback' => 'get_crm_contacts',
    );
    return $lists;
});

// Provide full subscriber data for queuing
add_filter( 'mskd_external_list_subscribers_full', function( $subscribers, $list ) {
    if ( $list->id !== 'ext_crm_contacts' ) {
        return $subscribers;
    }
    
    // Return array of subscriber data
    return array(
        array(
            'id'         => 'crm_1',
            'email'      => 'contact1@example.com',
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ),
        array(
            'id'         => 'crm_2',
            'email'      => 'contact2@example.com',
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
        ),
    );
}, 10, 2 );

function get_crm_contacts() {
    // This can return IDs, emails, or full data
    // The mskd_external_list_subscribers_full filter handles the conversion
    return array( 'contact1@example.com', 'contact2@example.com' );
}
```

## How External Subscribers Are Sent Emails

When composing an email to an external list:

1. The system calls `get_list_subscribers_full()` to get complete subscriber data
2. For external subscribers, data is stored inline in the queue (JSON in `subscriber_data` column)
3. During cron processing, the inline data is used directly (no database lookup needed)
4. Placeholders like `{first_name}`, `{email}` work normally with inline data

This means external subscribers can receive emails even if they don't exist in `mskd_subscribers`.

## UI Display

External subscribers appear in the Subscribers admin page with:
- An **"External"** badge next to their email
- A **Source** column showing the provider name
- **"Read-only"** in the Actions column (no Edit/Delete links)
- A subtle background color to distinguish from database subscribers

## Best Practices

### 1. Use Unique IDs

Prefix your subscriber IDs with your plugin slug:

```php
'id' => 'myplugin_user_' . $user_id,  // Good
'id' => 'user_' . $user_id,            // Risky - may collide
```

### 2. Cache Expensive Queries

```php
function get_crm_subscribers() {
    $cached = wp_cache_get( 'myplugin_crm_subs', 'mskd_external' );
    
    if ( false !== $cached ) {
        return $cached;
    }
    
    $subscribers = fetch_from_crm_api();
    
    wp_cache_set( 'myplugin_crm_subs', $subscribers, 'mskd_external', HOUR_IN_SECONDS );
    
    return $subscribers;
}
```

### 3. Respect Status Filters

```php
add_filter( 'mskd_register_external_subscribers', function( $subscribers, $args ) {
    $my_subscribers = get_my_subscribers();
    
    // Apply status filter if provided
    if ( ! empty( $args['status'] ) ) {
        $my_subscribers = array_filter( $my_subscribers, function( $sub ) use ( $args ) {
            return $sub['status'] === $args['status'];
        });
    }
    
    return array_merge( $subscribers, $my_subscribers );
}, 10, 2 );
```

### 4. Handle Unsubscribes

External subscribers have temporary unsubscribe tokens. Listen for unsubscribe actions:

```php
add_action( 'mskd_subscriber_unsubscribed', function( $email, $token ) {
    if ( strpos( $token, 'ext_' ) === 0 ) {
        // This is an external subscriber unsubscribing
        // Update your external system accordingly
        my_crm_mark_unsubscribed( $email );
    }
}, 10, 2 );
```

---

For more information or support, visit [Katsarov Design](https://katsarov.design).
