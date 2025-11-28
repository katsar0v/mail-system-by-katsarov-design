<?php
/**
 * Plugin Deactivator
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSKD_Deactivator
 *
 * Handles plugin deactivation tasks
 */
class MSKD_Deactivator {

	/**
	 * Deactivate the plugin
	 */
	public static function deactivate() {
		self::unschedule_cron();
	}

	/**
	 * Unschedule cron events
	 */
	private static function unschedule_cron() {
		$timestamp = wp_next_scheduled( 'mskd_process_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'mskd_process_queue' );
		}

		// Clear all scheduled hooks for this plugin
		wp_clear_scheduled_hook( 'mskd_process_queue' );
	}
}
