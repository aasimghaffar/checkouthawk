<?php
/**
 * Activation, database tables and scheduled events.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class CheckoutHawk_Install {

	const DB_VERSION_OPTION = 'checkouthawk_db_version';
	const DB_VERSION        = '1.0.0';

	/**
	 * Events table name.
	 *
	 * @return string
	 */
	public static function events_table() {
		global $wpdb;

		return $wpdb->prefix . 'checkouthawk_events';
	}

	/**
	 * Blocks table name.
	 *
	 * @return string
	 */
	public static function blocks_table() {
		global $wpdb;

		return $wpdb->prefix . 'checkouthawk_blocks';
	}

	/**
	 * Attempts table name.
	 *
	 * @return string
	 */
	public static function attempts_table() {
		global $wpdb;

		return $wpdb->prefix . 'checkouthawk_attempts';
	}

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();

		if ( false === get_option( CheckoutHawk_Settings::OPTION, false ) ) {
			CheckoutHawk_Settings::save( CheckoutHawk_Settings::defaults() );
		}

		self::schedule_events();

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'checkouthawk_maintenance' );
		wp_clear_scheduled_hook( 'checkouthawk_cleanup_orders' );
	}

	/**
	 * Make sure tables exist when the plugin version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::create_tables();
		self::schedule_events();

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Register cron events.
	 *
	 * @return void
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( 'checkouthawk_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'checkouthawk_maintenance' );
		}

		if ( ! wp_next_scheduled( 'checkouthawk_cleanup_orders' ) ) {
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'daily', 'checkouthawk_cleanup_orders' );
		}
	}

	/**
	 * Create or update the plugin tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$events          = self::events_table();
		$blocks          = self::blocks_table();
		$attempts        = self::attempts_table();

		$sql = array();

		$sql[] = "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_time datetime NOT NULL,
			event_type varchar(40) NOT NULL DEFAULT '',
			severity varchar(20) NOT NULL DEFAULT 'info',
			ip varchar(100) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			country varchar(2) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reason varchar(191) NOT NULL DEFAULT '',
			details text NULL,
			PRIMARY KEY  (id),
			KEY event_time (event_time),
			KEY event_type (event_type),
			KEY ip (ip),
			KEY order_id (order_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$blocks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			block_type varchar(20) NOT NULL DEFAULT 'ip',
			block_value varchar(191) NOT NULL DEFAULT '',
			reason varchar(191) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT 'auto',
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			expires_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY block_unique (block_type,block_value),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$attempts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attempt_time datetime NOT NULL,
			attempt_type varchar(30) NOT NULL DEFAULT '',
			ip varchar(100) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY attempt_time (attempt_time),
			KEY attempt_type (attempt_type),
			KEY ip (ip),
			KEY email (email)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
