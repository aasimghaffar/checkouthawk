<?php
/**
 * Panic mode ("under attack") state and rules.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the temporary lockdown state.
 */
class CheckoutHawk_Panic {

	const UNTIL_OPTION  = 'checkouthawk_panic_until';
	const MANUAL_OPTION = 'checkouthawk_panic_manual';

	/**
	 * Is panic mode currently on?
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( (int) get_option( self::MANUAL_OPTION, 0 ) === 1 ) {
			return true;
		}

		$until = (int) get_option( self::UNTIL_OPTION, 0 );

		if ( $until && $until > time() ) {
			return true;
		}

		if ( $until && $until <= time() ) {
			self::disable( 'expired' );
		}

		return false;
	}

	/**
	 * Timestamp panic mode ends, 0 when manual or off.
	 *
	 * @return int
	 */
	public static function ends_at() {
		if ( (int) get_option( self::MANUAL_OPTION, 0 ) === 1 ) {
			return 0;
		}

		return (int) get_option( self::UNTIL_OPTION, 0 );
	}

	/**
	 * Turn panic mode on.
	 *
	 * @param int    $minutes Duration in minutes, 0 for manual until turned off.
	 * @param string $source  auto or manual.
	 * @param string $reason  Why it was enabled.
	 * @return void
	 */
	public static function enable( $minutes = 0, $source = 'manual', $reason = '' ) {
		$was_active = self::is_active();

		if ( 'manual' === $source && $minutes < 1 ) {
			update_option( self::MANUAL_OPTION, 1, false );
			update_option( self::UNTIL_OPTION, 0, false );
		} else {
			$minutes = $minutes > 0 ? (int) $minutes : CheckoutHawk_Settings::get_int( 'panic_duration' );
			$minutes = $minutes > 0 ? $minutes : 120;

			update_option( self::MANUAL_OPTION, 0, false );
			update_option( self::UNTIL_OPTION, time() + ( $minutes * MINUTE_IN_SECONDS ), false );
		}

		if ( ! $was_active ) {
			CheckoutHawk_Logger::log(
				'panic_on',
				array(
					'severity' => 'critical',
					'reason'   => $reason ? $reason : __( 'Panic mode enabled', 'checkouthawk' ),
					'details'  => array(
						'source'  => $source,
						'minutes' => $minutes,
					),
				)
			);

			/**
			 * Fires when panic mode switches on.
			 *
			 * @param string $source Enabling source.
			 * @param string $reason Reason text.
			 */
			do_action( 'checkouthawk_panic_enabled', $source, $reason );
		}
	}

	/**
	 * Turn panic mode off.
	 *
	 * @param string $source Who turned it off.
	 * @return void
	 */
	public static function disable( $source = 'manual' ) {
		$was_active = ( (int) get_option( self::MANUAL_OPTION, 0 ) === 1 ) || ( (int) get_option( self::UNTIL_OPTION, 0 ) > 0 );

		update_option( self::MANUAL_OPTION, 0, false );
		update_option( self::UNTIL_OPTION, 0, false );

		if ( $was_active ) {
			CheckoutHawk_Logger::log(
				'panic_off',
				array(
					'reason'  => __( 'Panic mode switched off', 'checkouthawk' ),
					'details' => array( 'source' => $source ),
				)
			);
		}
	}

	/**
	 * Decide whether an attack is underway and switch panic mode on.
	 *
	 * @return void
	 */
	public static function maybe_auto_enable() {
		if ( ! CheckoutHawk_Settings::get_bool( 'auto_panic' ) || self::is_active() ) {
			return;
		}

		$window    = max( 1, CheckoutHawk_Settings::get_int( 'auto_panic_window' ) );
		$threshold = max( 3, CheckoutHawk_Settings::get_int( 'auto_panic_threshold' ) );
		$failures  = CheckoutHawk_Velocity::count_all( 'failed_payment', $window );

		if ( $failures < $threshold ) {
			return;
		}

		$ips = CheckoutHawk_Velocity::distinct_ips( $window );

		/* translators: 1: number of failed payments, 2: window in minutes */
		$reason = sprintf( __( '%1$d failed payments in %2$d minutes', 'checkouthawk' ), $failures, $window );

		CheckoutHawk_Logger::log(
			'attack_detected',
			array(
				'severity' => 'critical',
				'reason'   => $reason,
				'details'  => array(
					'failures'    => $failures,
					'window'      => $window,
					'unique_ips'  => $ips,
					'auto_panic'  => true,
				),
			)
		);

		self::enable( CheckoutHawk_Settings::get_int( 'panic_duration' ), 'auto', $reason );

		CheckoutHawk_Alerts::attack_alert(
			__( 'card testing attack detected, panic mode is on', 'checkouthawk' ),
			array(
				$reason . '.',
				/* translators: %d: number of unique IP addresses */
				sprintf( _n( 'Unique IP address involved: %d', 'Unique IP addresses involved: %d', $ips, 'checkouthawk' ), $ips ),
				__( 'Panic mode has been switched on automatically. Guest checkout rules and stricter limits are now in force.', 'checkouthawk' ),
			),
			array(
				'failures'   => $failures,
				'window'     => $window,
				'unique_ips' => $ips,
			)
		);
	}

	/**
	 * Multiplier applied to velocity thresholds while panic mode is on.
	 *
	 * @param int $value Normal threshold.
	 * @return int
	 */
	public static function tighten( $value ) {
		$value = (int) $value;

		// 0 means the store owner switched this limit off. Panic mode never turns it back on.
		if ( $value < 1 || ! self::is_active() ) {
			return $value;
		}

		return max( 2, (int) ceil( $value / 2 ) );
	}
}
