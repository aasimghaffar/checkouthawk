<?php
/**
 * Attempt tracking and velocity rules.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Records attempts and decides when a visitor has crossed a threshold.
 */
class CheckoutHawk_Velocity {

	/**
	 * Record an attempt.
	 *
	 * @param string $type     Attempt type: failed_payment, checkout, register, add_to_cart.
	 * @param string $ip       IP address.
	 * @param string $email    Email address.
	 * @param int    $order_id Related order id.
	 * @return void
	 */
	public static function record( $type, $ip = '', $email = '', $order_id = 0 ) {
		global $wpdb;

		$ip = '' !== $ip ? $ip : CheckoutHawk_Helpers::get_ip();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			CheckoutHawk_Install::attempts_table(),
			array(
				'attempt_time' => CheckoutHawk_Helpers::now(),
				'attempt_type' => substr( sanitize_key( $type ), 0, 30 ),
				'ip'           => substr( CheckoutHawk_Helpers::maybe_anonymize_ip( $ip ), 0, 100 ),
				'email'        => substr( CheckoutHawk_Helpers::normalize_email( $email ), 0, 191 ),
				'order_id'     => (int) $order_id,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Count attempts inside a time window.
	 *
	 * @param string $type    Attempt type.
	 * @param string $field   Column to match: ip or email.
	 * @param string $value   Value to match.
	 * @param int    $minutes Window length in minutes.
	 * @return int
	 */
	public static function count( $type, $field, $value, $minutes ) {
		global $wpdb;

		$by_email = ( 'email' === $field );
		$value    = $by_email ? CheckoutHawk_Helpers::normalize_email( $value ) : CheckoutHawk_Helpers::maybe_anonymize_ip( $value );

		if ( '' === $value ) {
			return 0;
		}

		$table = CheckoutHawk_Install::attempts_table();
		$type  = sanitize_key( $type );
		$since = CheckoutHawk_Helpers::time_offset( - absint( $minutes ) );

		// Two literal queries rather than one with the column name interpolated in.
		if ( $by_email ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE attempt_type = %s AND email = %s AND attempt_time >= %s',
					$table,
					$type,
					$value,
					$since
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE attempt_type = %s AND ip = %s AND attempt_time >= %s',
				$table,
				$type,
				$value,
				$since
			)
		);
	}

	/**
	 * Count all attempts of a type in a window, site wide.
	 *
	 * @param string $type    Attempt type.
	 * @param int    $minutes Window length in minutes.
	 * @return int
	 */
	public static function count_all( $type, $minutes ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE attempt_type = %s AND attempt_time >= %s',
				CheckoutHawk_Install::attempts_table(),
				sanitize_key( $type ),
				CheckoutHawk_Helpers::time_offset( - absint( $minutes ) )
			)
		);
	}

	/**
	 * How many distinct IPs produced failed payments recently.
	 *
	 * @param int $minutes Window length in minutes.
	 * @return int
	 */
	public static function distinct_ips( $minutes ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT ip) FROM %i WHERE attempt_type = %s AND attempt_time >= %s',
				CheckoutHawk_Install::attempts_table(),
				'failed_payment',
				CheckoutHawk_Helpers::time_offset( - absint( $minutes ) )
			)
		);
	}

	/**
	 * Delete attempts older than 7 days, or the log retention window when shorter.
	 *
	 * @return int
	 */
	public static function prune() {
		global $wpdb;

		$days = CheckoutHawk_Settings::get_int( 'log_retention_days' );
		$days = ( $days > 0 && $days < 7 ) ? $days : 7;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE attempt_time < %s',
				CheckoutHawk_Install::attempts_table(),
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			)
		);
	}
}
