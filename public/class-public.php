<?php
/**
 * Public Class
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSKD_Public
 *
 * Handles public-facing functionality
 */
class MSKD_Public {

	/**
	 * Initialize public hooks
	 */
	public function init() {
		add_action( 'init', array( $this, 'handle_unsubscribe' ) );
		add_shortcode( 'mskd_subscribe_form', array( $this, 'subscribe_form_shortcode' ) );
		add_action( 'wp_ajax_mskd_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_mskd_subscribe', array( $this, 'ajax_subscribe' ) );
	}

	/**
	 * Enqueue public assets (called only when shortcode is used)
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'mskd-public-style',
			MSKD_PLUGIN_URL . 'public/css/public-style.css',
			array(),
			MSKD_VERSION
		);

		wp_enqueue_script(
			'mskd-public-script',
			MSKD_PLUGIN_URL . 'public/js/public-script.js',
			array( 'jquery' ),
			MSKD_VERSION,
			true
		);

		wp_localize_script(
			'mskd-public-script',
			'mskd_public',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mskd_public_nonce' ),
				'strings'  => array(
					'subscribing' => __( 'Subscribing...', 'mail-system-by-katsarov-design' ),
					'success'     => __( 'Successfully subscribed!', 'mail-system-by-katsarov-design' ),
					'error'       => __( 'An error occurred. Please try again.', 'mail-system-by-katsarov-design' ),
				),
			)
		);
	}

	/**
	 * Handle unsubscribe requests
	 */
	public function handle_unsubscribe() {
		if ( ! isset( $_GET['mskd_unsubscribe'] ) ) {
			return;
		}

		$token = sanitize_text_field( $_GET['mskd_unsubscribe'] );

		if ( empty( $token ) ) {
			return;
		}

		// Validate token length (tokens are 32 characters)
		if ( strlen( $token ) !== 32 || ! ctype_alnum( $token ) ) {
			wp_die(
				__( 'Invalid unsubscribe link.', 'mail-system-by-katsarov-design' ),
				__( 'Error', 'mail-system-by-katsarov-design' ),
				array( 'response' => 400 )
			);
		}

		// Rate limiting: check transient for this IP
		$ip_hash        = md5( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$rate_limit_key = 'mskd_unsubscribe_' . $ip_hash;
		$attempts       = get_transient( $rate_limit_key );

		if ( $attempts !== false && $attempts >= 10 ) {
			wp_die(
				__( 'Too many attempts. Please try again in 5 minutes.', 'mail-system-by-katsarov-design' ),
				__( 'Error', 'mail-system-by-katsarov-design' ),
				array( 'response' => 429 )
			);
		}

		// Increment attempts
		set_transient( $rate_limit_key, ( $attempts ? $attempts + 1 : 1 ), 5 * MINUTE_IN_SECONDS );

		global $wpdb;

		$subscriber = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE unsubscribe_token = %s",
				$token
			)
		);

		if ( ! $subscriber ) {
			wp_die(
				__( 'Invalid unsubscribe link.', 'mail-system-by-katsarov-design' ),
				__( 'Error', 'mail-system-by-katsarov-design' ),
				array( 'response' => 400 )
			);
		}

		// Update subscriber status
		$wpdb->update(
			$wpdb->prefix . 'mskd_subscribers',
			array( 'status' => 'unsubscribed' ),
			array( 'id' => $subscriber->id ),
			array( '%s' ),
			array( '%d' )
		);

		// Show unsubscribe confirmation page
		include MSKD_PLUGIN_DIR . 'public/partials/unsubscribe.php';
		exit;
	}

	/**
	 * Subscribe form shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function subscribe_form_shortcode( $atts ) {
		// Enqueue assets only when shortcode is used
		$this->enqueue_assets();

		$atts = shortcode_atts(
			array(
				'list_id' => 0,
				'title'   => __( 'Subscribe', 'mail-system-by-katsarov-design' ),
			),
			$atts
		);

		ob_start();
		include MSKD_PLUGIN_DIR . 'public/partials/subscribe-form.php';
		return ob_get_clean();
	}

	/**
	 * AJAX subscribe handler
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'mskd_public_nonce', 'nonce' );

		global $wpdb;

		$email      = sanitize_email( $_POST['email'] );
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : '';
		$list_id    = isset( $_POST['list_id'] ) ? intval( $_POST['list_id'] ) : 0;

		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid email address.', 'mail-system-by-katsarov-design' ),
				)
			);
		}

		// Check if subscriber exists
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE email = %s",
				$email
			)
		);

		if ( $existing ) {
			// Reactivate if unsubscribed
			if ( $existing->status === 'unsubscribed' ) {
				$wpdb->update(
					$wpdb->prefix . 'mskd_subscribers',
					array( 'status' => 'active' ),
					array( 'id' => $existing->id ),
					array( '%s' ),
					array( '%d' )
				);
			}

			$subscriber_id = $existing->id;
		} else {
			// Create new subscriber
			$token = wp_generate_password( 32, false );

			$wpdb->insert(
				$wpdb->prefix . 'mskd_subscribers',
				array(
					'email'             => $email,
					'first_name'        => $first_name,
					'last_name'         => $last_name,
					'status'            => 'active',
					'unsubscribe_token' => $token,
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);

			$subscriber_id = $wpdb->insert_id;
		}

		// Add to list if specified
		if ( $list_id && $subscriber_id ) {
			// Check if already in list
			$in_list = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}mskd_subscriber_list 
                WHERE subscriber_id = %d AND list_id = %d",
					$subscriber_id,
					$list_id
				)
			);

			if ( ! $in_list ) {
				$wpdb->insert(
					$wpdb->prefix . 'mskd_subscriber_list',
					array(
						'subscriber_id' => $subscriber_id,
						'list_id'       => $list_id,
					),
					array( '%d', '%d' )
				);
			}
		}

		wp_send_json_success(
			array(
				'message' => __( 'Successfully subscribed!', 'mail-system-by-katsarov-design' ),
			)
		);
	}
}
