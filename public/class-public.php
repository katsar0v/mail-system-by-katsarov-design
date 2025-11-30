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
		add_action( 'init', array( $this, 'handle_opt_in_confirmation' ) );
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
	 * Handle opt-in confirmation requests.
	 *
	 * Processes confirmation links clicked from opt-in emails to activate subscribers.
	 */
	public function handle_opt_in_confirmation() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public confirmation link does not require nonce.
		if ( ! isset( $_GET['mskd_confirm'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public confirmation link does not require nonce.
		$token = sanitize_text_field( wp_unslash( $_GET['mskd_confirm'] ) );

		if ( empty( $token ) ) {
			return;
		}

		// Validate token length (tokens are 32 characters).
		if ( 32 !== strlen( $token ) || ! ctype_alnum( $token ) ) {
			wp_die(
				esc_html__( 'Invalid confirmation link.', 'mail-system-by-katsarov-design' ),
				esc_html__( 'Error', 'mail-system-by-katsarov-design' ),
				array( 'response' => 400 )
			);
		}

		// Rate limiting: check transient for this IP.
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			wp_die(
				esc_html__( 'Unable to verify request.', 'mail-system-by-katsarov-design' ),
				esc_html__( 'Error', 'mail-system-by-katsarov-design' ),
				array( 'response' => 400 )
			);
		}
		$ip_hash        = md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
		$rate_limit_key = 'mskd_confirm_' . $ip_hash;
		$attempts       = get_transient( $rate_limit_key );

		if ( false !== $attempts && $attempts >= 10 ) {
			wp_die(
				esc_html__( 'Too many attempts. Please try again in 5 minutes.', 'mail-system-by-katsarov-design' ),
				esc_html__( 'Error', 'mail-system-by-katsarov-design' ),
				array( 'response' => 429 )
			);
		}

		// Increment attempts.
		set_transient( $rate_limit_key, ( $attempts ? $attempts + 1 : 1 ), 5 * MINUTE_IN_SECONDS );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for opt-in token lookup.
		$subscriber = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE opt_in_token = %s",
				$token
			)
		);

		if ( ! $subscriber ) {
			wp_die(
				esc_html__( 'Invalid confirmation link.', 'mail-system-by-katsarov-design' ),
				esc_html__( 'Error', 'mail-system-by-katsarov-design' ),
				array( 'response' => 400 )
			);
		}

		// Check if already active (idempotent - allow multiple clicks).
		if ( 'active' === $subscriber->status ) {
			// Already confirmed, show success page anyway.
			include MSKD_PLUGIN_DIR . 'public/partials/opt-in-confirm.php';
			exit;
		}

		// Update subscriber status to active and clear opt-in token.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for status update.
		$wpdb->update(
			$wpdb->prefix . 'mskd_subscribers',
			array(
				'status'       => 'active',
				'opt_in_token' => null,
			),
			array( 'id' => $subscriber->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Show confirmation success page.
		include MSKD_PLUGIN_DIR . 'public/partials/opt-in-confirm.php';
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
	 *
	 * Implements double opt-in workflow:
	 * 1. Creates new subscribers with 'inactive' status.
	 * 2. Sends opt-in confirmation email.
	 * 3. Subscriber must click confirmation link to become active.
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'mskd_public_nonce', 'nonce' );

		global $wpdb;

		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$list_id    = isset( $_POST['list_id'] ) ? intval( $_POST['list_id'] ) : 0;

		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a valid email address.', 'mail-system-by-katsarov-design' ),
				)
			);
		}

		// Check if subscriber exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for subscriber lookup.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE email = %s",
				$email
			)
		);

		if ( $existing ) {
			// Handle existing subscriber based on status.
			if ( 'unsubscribed' === $existing->status ) {
				// Reactivate if unsubscribed - generate new opt-in token and send confirmation.
				$opt_in_token = wp_generate_password( 32, false );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for status update.
				$wpdb->update(
					$wpdb->prefix . 'mskd_subscribers',
					array(
						'status'       => 'inactive',
						'opt_in_token' => $opt_in_token,
						'first_name'   => $first_name ? $first_name : $existing->first_name,
						'last_name'    => $last_name ? $last_name : $existing->last_name,
					),
					array( 'id' => $existing->id ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);

				$subscriber_id = $existing->id;

				// Send opt-in confirmation email.
				$this->send_opt_in_email( $email, $first_name ? $first_name : $existing->first_name, $opt_in_token );
			} elseif ( 'inactive' === $existing->status ) {
				// Already inactive, resend confirmation email.
				$opt_in_token = wp_generate_password( 32, false );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for token update.
				$wpdb->update(
					$wpdb->prefix . 'mskd_subscribers',
					array(
						'opt_in_token' => $opt_in_token,
						'first_name'   => $first_name ? $first_name : $existing->first_name,
						'last_name'    => $last_name ? $last_name : $existing->last_name,
					),
					array( 'id' => $existing->id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);

				$subscriber_id = $existing->id;

				// Resend opt-in confirmation email.
				$this->send_opt_in_email( $email, $first_name ? $first_name : $existing->first_name, $opt_in_token );
			} else {
				// Already active - return early with message.
				wp_send_json_success(
					array(
						'message' => __( 'You are already subscribed!', 'mail-system-by-katsarov-design' ),
					)
				);
			}
		} else {
			// Create new subscriber with inactive status.
			$unsubscribe_token = wp_generate_password( 32, false );
			$opt_in_token      = wp_generate_password( 32, false );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct query needed for subscriber insert.
			$wpdb->insert(
				$wpdb->prefix . 'mskd_subscribers',
				array(
					'email'             => $email,
					'first_name'        => $first_name,
					'last_name'         => $last_name,
					'status'            => 'inactive',
					'unsubscribe_token' => $unsubscribe_token,
					'opt_in_token'      => $opt_in_token,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			$subscriber_id = $wpdb->insert_id;

			// Send opt-in confirmation email.
			$this->send_opt_in_email( $email, $first_name, $opt_in_token );
		}

		// Add to list if specified.
		if ( $list_id && $subscriber_id ) {
			// Check if already in list.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for list lookup.
			$in_list = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}mskd_subscriber_list 
                WHERE subscriber_id = %d AND list_id = %d",
					$subscriber_id,
					$list_id
				)
			);

			if ( ! $in_list ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct query needed for list insert.
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

		// Return success message indicating confirmation email was sent.
		if ( $existing && 'active' === $existing->status ) {
			wp_send_json_success(
				array(
					'message' => __( 'You are already subscribed!', 'mail-system-by-katsarov-design' ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message' => __( 'Please check your email to confirm your subscription.', 'mail-system-by-katsarov-design' ),
				)
			);
		}
	}

	/**
	 * Send opt-in confirmation email.
	 *
	 * @param string $email        Subscriber email address.
	 * @param string $first_name   Subscriber first name.
	 * @param string $opt_in_token Opt-in confirmation token.
	 */
	private function send_opt_in_email( $email, $first_name, $opt_in_token ) {
		$confirm_url = add_query_arg(
			array(
				'mskd_confirm' => $opt_in_token,
			),
			home_url()
		);

		$site_name = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'Confirm your subscription to %s', 'mail-system-by-katsarov-design' ),
			$site_name
		);

		$greeting = $first_name
			/* translators: %s: Subscriber first name */
			? sprintf( __( 'Hello %s,', 'mail-system-by-katsarov-design' ), $first_name )
			: __( 'Hello,', 'mail-system-by-katsarov-design' );

		$body = sprintf(
			/* translators: 1: Greeting, 2: Site name, 3: Confirmation URL, 4: Site name */
			__(
				'%1$s

Thank you for subscribing to %2$s!

Please click the link below to confirm your subscription:

%3$s

If you did not request this subscription, you can safely ignore this email.

Best regards,
%4$s',
				'mail-system-by-katsarov-design'
			),
			$greeting,
			$site_name,
			$confirm_url,
			$site_name
		);

		// Use WordPress wp_mail for sending confirmation email.
		$mail_sent = wp_mail( $email, $subject, $body );
		if ( ! $mail_sent ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for debugging email failures.
			error_log( 'MSKD: Failed to send opt-in confirmation email to ' . $email );
		}
	}
}
