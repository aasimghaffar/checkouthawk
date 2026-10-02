<?php
/**
 * Failed payment velocity: the core card testing defence.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Watches failed payments and blocks repeat offenders.
 */
class CheckoutHawk_Failed_Payments {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_failed', array( $this, 'on_failed_order' ), 10, 2 );
		add_action( 'checkouthawk_record_failed_payment', array( $this, 'record' ), 10, 3 );
	}

	/**
	 * Handle a WooCommerce order moving to failed.
	 *
	 * @param int      $order_id Order id.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	public function on_failed_order( $order_id, $order = null ) {
		if ( ! CheckoutHawk_Settings::get_bool( 'velocity_enabled' ) ) {
			return;
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$email = $order->get_billing_email();

		// The address stored on the order is the shopper's. The live request is only a fallback,
		// and only for a real page request: gateway webhooks and IPN callbacks are front end
		// requests too, and using their IP would blame the payment processor for the attack.
		$ip = CheckoutHawk_Helpers::sanitize_ip( $order->get_customer_ip_address() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the request context only, no data is used.
		$is_machine_request = is_admin() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! empty( $_GET['wc-api'] );

		if ( '' === $ip && ! $is_machine_request ) {
			$ip = CheckoutHawk_Helpers::get_ip();
		}

		$this->record( $ip, $email, (int) $order_id );
	}

	/**
	 * Record a failed payment and apply the velocity rules.
	 *
	 * @param string $ip       IP address.
	 * @param string $email    Email address.
	 * @param int    $order_id Order id.
	 * @return void
	 */
	public function record( $ip, $email = '', $order_id = 0 ) {
		$ip    = CheckoutHawk_Helpers::sanitize_ip( $ip );
		$email = CheckoutHawk_Helpers::normalize_email( $email );

		CheckoutHawk_Velocity::record( 'failed_payment', $ip, $email, $order_id );

		CheckoutHawk_Logger::log(
			'failed_payment',
			array(
				'severity' => 'notice',
				'ip'       => $ip,
				'email'    => $email,
				'order_id' => (int) $order_id,
				'reason'   => __( 'Payment failed', 'checkouthawk' ),
			)
		);

		$window    = max( 1, CheckoutHawk_Settings::get_int( 'failed_payment_window' ) );
		$threshold = CheckoutHawk_Panic::tighten( max( 2, CheckoutHawk_Settings::get_int( 'failed_payment_threshold' ) ) );
		$minutes   = CheckoutHawk_Settings::get_int( 'auto_block_minutes' );
		$blocked   = false;

		if ( '' !== $ip ) {
			$ip_failures = CheckoutHawk_Velocity::count( 'failed_payment', 'ip', $ip, $window );

			if ( $ip_failures >= $threshold ) {
				/* translators: 1: number of failed payments, 2: window in minutes */
				$reason = sprintf( __( '%1$d failed payments in %2$d minutes', 'checkouthawk' ), $ip_failures, $window );

				$blocked = CheckoutHawk_Blocklist::add(
					'ip',
					$ip,
					array(
						'reason'  => $reason,
						'source'  => 'auto',
						'minutes' => $minutes,
					)
				) || $blocked;
			}
		}

		if ( CheckoutHawk_Settings::get_bool( 'block_by_email' ) && '' !== $email ) {
			$email_failures = CheckoutHawk_Velocity::count( 'failed_payment', 'email', $email, $window );

			if ( $email_failures >= $threshold ) {
				/* translators: 1: number of failed payments, 2: window in minutes */
				$reason = sprintf( __( '%1$d failed payments in %2$d minutes', 'checkouthawk' ), $email_failures, $window );

				$blocked = CheckoutHawk_Blocklist::add(
					'email',
					$email,
					array(
						'reason'  => $reason,
						'source'  => 'auto',
						'minutes' => $minutes,
					)
				) || $blocked;
			}
		}

		if ( $blocked && $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				$order->update_meta_data( '_checkouthawk_flagged', 'yes' );
				$order->add_order_note( __( 'CheckoutHawk: this order matched a card testing pattern and the source was blocked.', 'checkouthawk' ) );
				$order->save();
			}
		}

		CheckoutHawk_Panic::maybe_auto_enable();
	}
}
