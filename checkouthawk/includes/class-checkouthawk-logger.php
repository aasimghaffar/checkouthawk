<?php
/**
 * Event log.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes and reads protection events.
 */
class CheckoutHawk_Logger {

	/**
	 * Record an event.
	 *
	 * @param string $type    Event type key.
	 * @param array  $args    Event data.
	 * @return int Inserted row id, 0 on failure.
	 */
	public static function log( $type, $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'severity' => 'info',
				'ip'       => '',
				'email'    => '',
				'country'  => '',
				'order_id' => 0,
				'reason'   => '',
				'details'  => '',
			)
		);

		$ip = CheckoutHawk_Helpers::maybe_anonymize_ip( (string) $args['ip'] );

		$details = $args['details'];

		if ( is_array( $details ) ) {
			$details = wp_json_encode( $details );
		}

		$data = array(
			'event_time' => CheckoutHawk_Helpers::now(),
			'event_type' => substr( sanitize_key( $type ), 0, 40 ),
			'severity'   => substr( sanitize_key( $args['severity'] ), 0, 20 ),
			'ip'         => substr( $ip, 0, 100 ),
			'email'      => substr( CheckoutHawk_Helpers::normalize_email( $args['email'] ), 0, 191 ),
			'country'    => substr( strtoupper( (string) $args['country'] ), 0, 2 ),
			'order_id'   => (int) $args['order_id'],
			'reason'     => substr( sanitize_text_field( (string) $args['reason'] ), 0, 191 ),
			'details'    => is_string( $details ) ? $details : '',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( CheckoutHawk_Install::events_table(), $data, array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ) );

		/**
		 * Fires after CheckoutHawk records an event.
		 *
		 * @param string $type Event type.
		 * @param array  $data Stored row data.
		 */
		do_action( 'checkouthawk_event_logged', $type, $data );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Fetch events.
	 *
	 * @param array $args Query args: per_page, page, type, search.
	 * @return array
	 */
	public static function get_events( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'per_page' => 25,
				'page'     => 1,
				'type'     => '',
				'search'   => '',
			)
		);

		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );
		$type     = sanitize_key( $args['type'] );
		$search   = sanitize_text_field( $args['search'] );
		$like     = '%' . $wpdb->esc_like( $search ) . '%';

		// The filters are optional, so each one is written as "no filter given, or it matches".
		// That keeps the query a single literal string with a fixed placeholder list.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				 WHERE ( %s = '' OR event_type = %s )
				 AND ( %s = '' OR ip LIKE %s OR email LIKE %s OR reason LIKE %s )
				 ORDER BY event_time DESC, id DESC
				 LIMIT %d OFFSET %d",
				CheckoutHawk_Install::events_table(),
				$type,
				$type,
				$search,
				$like,
				$like,
				$like,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count events matching the same filters.
	 *
	 * @param array $args Query args.
	 * @return int
	 */
	public static function count_events( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'type'   => '',
				'search' => '',
			)
		);

		$type   = sanitize_key( $args['type'] );
		$search = sanitize_text_field( $args['search'] );
		$like   = '%' . $wpdb->esc_like( $search ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i
				 WHERE ( %s = '' OR event_type = %s )
				 AND ( %s = '' OR ip LIKE %s OR email LIKE %s OR reason LIKE %s )",
				CheckoutHawk_Install::events_table(),
				$type,
				$type,
				$search,
				$like,
				$like,
				$like
			)
		);
	}

	/**
	 * Count events of a type within the last N minutes.
	 *
	 * @param string $type    Event type.
	 * @param int    $minutes Window in minutes.
	 * @return int
	 */
	public static function count_recent( $type, $minutes ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE event_type = %s AND event_time >= %s',
				CheckoutHawk_Install::events_table(),
				sanitize_key( $type ),
				CheckoutHawk_Helpers::time_offset( - absint( $minutes ) )
			)
		);
	}

	/**
	 * Dashboard counters.
	 *
	 * @param int $hours Look back window in hours.
	 * @return array
	 */
	public static function summary( $hours = 24 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - ( absint( $hours ) * HOUR_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT event_type, COUNT(*) AS total FROM %i WHERE event_time >= %s GROUP BY event_type',
				CheckoutHawk_Install::events_table(),
				$since
			),
			ARRAY_A
		);

		$out = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ $row['event_type'] ] = (int) $row['total'];
			}
		}

		return $out;
	}

	/**
	 * Top offending IP addresses.
	 *
	 * @param int $hours Look back window in hours.
	 * @param int $limit Rows to return.
	 * @return array
	 */
	public static function top_offenders( $hours = 24, $limit = 10 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - ( absint( $hours ) * HOUR_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ip, COUNT(*) AS total, MAX(event_time) AS last_seen
				 FROM %i
				 WHERE event_time >= %s AND ip <> '' AND severity <> 'info'
				 GROUP BY ip ORDER BY total DESC LIMIT %d",
				CheckoutHawk_Install::events_table(),
				$since,
				absint( $limit )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Daily totals for the mini chart.
	 *
	 * @param int $days Number of days.
	 * @return array
	 */
	public static function daily_totals( $days = 14 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d 00:00:00', time() - ( absint( $days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(event_time) AS day, COUNT(*) AS total
				 FROM %i
				 WHERE event_time >= %s AND severity <> 'info'
				 GROUP BY DATE(event_time) ORDER BY day ASC",
				CheckoutHawk_Install::events_table(),
				$since
			),
			ARRAY_A
		);

		$totals = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$totals[ $row['day'] ] = (int) $row['total'];
			}
		}

		$out = array();

		for ( $i = absint( $days ) - 1; $i >= 0; $i-- ) {
			$day         = gmdate( 'Y-m-d', time() - ( $i * DAY_IN_SECONDS ) );
			$out[ $day ] = isset( $totals[ $day ] ) ? $totals[ $day ] : 0;
		}

		return $out;
	}

	/**
	 * Delete events older than the retention window.
	 *
	 * @return int Rows removed.
	 */
	public static function prune() {
		global $wpdb;

		$days = CheckoutHawk_Settings::get_int( 'log_retention_days' );

		if ( $days < 1 ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$removed = $wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i WHERE event_time < %s', CheckoutHawk_Install::events_table(), $cutoff )
		);

		return (int) $removed;
	}

	/**
	 * Delete every stored event.
	 *
	 * @return void
	 */
	public static function truncate() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', CheckoutHawk_Install::events_table() ) );
	}
}
