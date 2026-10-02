<?php
/**
 * Failed order cleanup and housekeeping.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Removes the order debris a card testing wave leaves behind.
 */
class CheckoutHawk_Cleanup {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'checkouthawk_maintenance', array( $this, 'maintenance' ) );
		add_action( 'checkouthawk_cleanup_orders', array( $this, 'scheduled_cleanup' ) );
	}

	/**
	 * Hourly housekeeping.
	 *
	 * @return void
	 */
	public function maintenance() {
		CheckoutHawk_Logger::prune();
		CheckoutHawk_Velocity::prune();
		CheckoutHawk_Blocklist::purge_expired();

		// Switch panic mode off once its window has passed.
		CheckoutHawk_Panic::is_active();
	}

	/**
	 * Daily cleanup, only when the setting is on.
	 *
	 * @return void
	 */
	public function scheduled_cleanup() {
		if ( ! CheckoutHawk_Settings::get_bool( 'cleanup_enabled' ) ) {
			return;
		}

		$this->run( 200 );
	}

	/**
	 * Find failed orders older than the configured age.
	 *
	 * @param int  $limit        Maximum orders to fetch.
	 * @param bool $only_flagged Restrict to orders CheckoutHawk flagged.
	 * @return array Order ids.
	 */
	public function find_orders( $limit = 200, $only_flagged = null ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$days = max( 1, CheckoutHawk_Settings::get_int( 'cleanup_days' ) );

		if ( null === $only_flagged ) {
			$only_flagged = CheckoutHawk_Settings::get_bool( 'cleanup_only_flagged' );
		}

		$args = array(
			'status'       => array( 'failed' ),
			'limit'        => max( 1, (int) $limit ),
			'return'       => 'ids',
			'date_created' => '<' . ( time() - ( $days * DAY_IN_SECONDS ) ),
			'orderby'      => 'date',
			'order'        => 'ASC',
		);

		/**
		 * Filter the query used to find cleanup candidates.
		 *
		 * @param array $args wc_get_orders arguments.
		 */
		$args = (array) apply_filters( 'checkouthawk_cleanup_query_args', $args );

		$ids = wc_get_orders( $args );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		if ( ! $only_flagged ) {
			return $ids;
		}

		$flagged = array();

		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );

			if ( $order && 'yes' === $order->get_meta( '_checkouthawk_flagged' ) ) {
				$flagged[] = (int) $id;
			}
		}

		return $flagged;
	}

	/**
	 * Delete failed orders.
	 *
	 * @param int  $limit        Maximum orders to remove in this pass.
	 * @param bool $only_flagged Restrict to flagged orders.
	 * @return int Number of orders deleted.
	 */
	public function run( $limit = 200, $only_flagged = null ) {
		$ids     = $this->find_orders( $limit, $only_flagged );
		$deleted = 0;

		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );

			if ( ! $order ) {
				continue;
			}

			if ( $order->delete( true ) ) {
				$deleted++;
			}
		}

		if ( $deleted > 0 ) {
			CheckoutHawk_Logger::log(
				'cleanup',
				array(
					/* translators: %d: number of orders removed */
					'reason'  => sprintf( _n( 'Removed %d failed order', 'Removed %d failed orders', $deleted, 'checkouthawk' ), $deleted ),
					'details' => array( 'deleted' => $deleted ),
				)
			);
		}

		return $deleted;
	}

	/**
	 * Count failed orders currently on the store.
	 *
	 * @return int
	 */
	public static function failed_order_count() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$ids = wc_get_orders(
			array(
				'status' => array( 'failed' ),
				'limit'  => 500,
				'return' => 'ids',
			)
		);

		return is_array( $ids ) ? count( $ids ) : 0;
	}
}
