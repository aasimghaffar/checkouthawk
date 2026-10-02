<?php
/**
 * Uninstall handler.
 *
 * Data is only removed when the store owner opted in from the settings screen.
 *
 * @package CheckoutHawk
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove the plugin data for the current site.
 *
 * @return void
 */
function checkouthawk_uninstall_site() {
	// Cron is stored per site, so this has to run for every site, opt in or not.
	wp_clear_scheduled_hook( 'checkouthawk_maintenance' );
	wp_clear_scheduled_hook( 'checkouthawk_cleanup_orders' );

	$settings = get_option( 'checkouthawk_settings', array() );

	if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'checkouthawk_events',
		$wpdb->prefix . 'checkouthawk_blocks',
		$wpdb->prefix . 'checkouthawk_attempts',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	delete_option( 'checkouthawk_settings' );
	delete_option( 'checkouthawk_db_version' );
	delete_option( 'checkouthawk_panic_until' );
	delete_option( 'checkouthawk_panic_manual' );
	delete_option( 'checkouthawk_last_alert' );
}

if ( is_multisite() ) {
	$checkouthawk_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( (array) $checkouthawk_sites as $checkouthawk_site_id ) {
		switch_to_blog( (int) $checkouthawk_site_id );
		checkouthawk_uninstall_site();
		restore_current_blog();
	}
} else {
	checkouthawk_uninstall_site();
}
