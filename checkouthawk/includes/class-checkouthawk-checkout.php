<?php
/**
 * Checkout protection for both the classic checkout and the Checkout block (Store API).
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs the rule set against every checkout attempt.
 */
class CheckoutHawk_Checkout {

	const SESSION_KEY = 'checkouthawk_started';

	/**
	 * Hook everything up.
	 */
	public function __construct() {
		// Classic checkout.
		add_action( 'woocommerce_before_checkout_form', array( $this, 'start_timer' ) );
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'render_honeypot' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic' ), 10, 2 );

		// Checkout block / Store API.
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_store_api' ), 10, 2 );
		add_action( 'wp', array( $this, 'start_timer_blocks' ) );

		// Cart flood control.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );

		// Start the clock again after each completed order.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'reset_timer' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'reset_timer' ) );
	}

	/**
	 * Should the rules run for this request at all?
	 *
	 * @return bool
	 */
	protected function is_active() {
		if ( ! CheckoutHawk_Settings::get_bool( 'protection_enabled' ) ) {
			return false;
		}

		if ( CheckoutHawk_Helpers::is_trusted_user() ) {
			return false;
		}

		/**
		 * Allow integrations to skip CheckoutHawk for a request.
		 *
		 * @param bool $active Whether protection runs.
		 */
		return (bool) apply_filters( 'checkouthawk_protection_active', true );
	}

	/**
	 * Remember when the classic checkout form was rendered.
	 *
	 * @return void
	 */
	public function start_timer() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		if ( ! WC()->session->get( self::SESSION_KEY ) ) {
			WC()->session->set( self::SESSION_KEY, time() );
		}
	}

	/**
	 * Remember when a block based checkout page was first viewed.
	 *
	 * @return void
	 */
	public function start_timer_blocks() {
		if ( is_admin() || ! function_exists( 'is_checkout' ) ) {
			return;
		}

		if ( ! is_checkout() && ! is_cart() ) {
			return;
		}

		$this->start_timer();
	}

	/**
	 * Seconds since the checkout page was opened.
	 *
	 * @return int Seconds on the page, 0 when the checkout page was never rendered, -1 when there is no session to judge.
	 */
	protected function seconds_on_page() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return -1; // No session to read, so the timing check cannot judge this request.
		}

		$started = (int) WC()->session->get( self::SESSION_KEY );

		if ( ! $started ) {
			// A session exists but the checkout page was never rendered: a direct POST.
			return 0;
		}

		return max( 0, time() - $started );
	}

	/**
	 * Reset the timer once an order has been placed, so the next order in the same session is judged fresh.
	 *
	 * @return void
	 */
	public function reset_timer() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, 0 );
		}
	}

	/**
	 * Output the honeypot field on the classic checkout.
	 *
	 * @return void
	 */
	public function render_honeypot() {
		if ( ! CheckoutHawk_Settings::get_bool( 'honeypot_enabled' ) || ! $this->is_active() ) {
			return;
		}

		echo '<div class="checkouthawk-field" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden;">';
		echo '<label for="checkouthawk_website_url">' . esc_html__( 'Leave this field empty', 'checkouthawk' ) . '</label>';
		echo '<input type="text" name="checkouthawk_website_url" id="checkouthawk_website_url" value="" tabindex="-1" autocomplete="off" />';
		echo '</div>';
	}

	/**
	 * Classic checkout validation.
	 *
	 * @param array     $data   Posted checkout data.
	 * @param WP_Error $errors Error object.
	 * @return void
	 */
	public function validate_classic( $data, $errors ) {
		if ( ! $this->is_active() ) {
			return;
		}

		// WooCommerce already rejected this submission (missing field, bad postcode and so on).
		// The order will not be placed, so there is nothing to count and nothing to block.
		if ( is_wp_error( $errors ) && $errors->has_errors() ) {
			return;
		}

		$email = isset( $data['billing_email'] ) ? $data['billing_email'] : '';
		$total = ( function_exists( 'WC' ) && WC()->cart ) ? (float) WC()->cart->get_total( 'edit' ) : 0;

		$flags = array(
			'honeypot' => false,
			'too_fast' => false,
		);

		if ( CheckoutHawk_Settings::get_bool( 'honeypot_enabled' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before this hook.
			$pot = isset( $_POST['checkouthawk_website_url'] ) ? sanitize_text_field( wp_unslash( $_POST['checkouthawk_website_url'] ) ) : '';

			$flags['honeypot'] = ( '' !== $pot );
		}

		$min_seconds = CheckoutHawk_Settings::get_int( 'min_checkout_seconds' );
		$elapsed     = $this->seconds_on_page();

		if ( $min_seconds > 0 && $elapsed > -1 && $elapsed < $min_seconds ) {
			$flags['too_fast'] = true;
		}

		$reason = $this->evaluate( $email, $total, $flags );

		if ( '' === $reason ) {
			return;
		}

		$this->log_block( $reason, $email );

		if ( is_wp_error( $errors ) ) {
			$errors->add( 'checkouthawk_blocked', $this->customer_message( $reason ) );
		}
	}

	/**
	 * Store API (Checkout block) validation.
	 *
	 * @param WC_Order        $order   Draft order.
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When the attempt is blocked.
	 */
	public function validate_store_api( $order, $request ) {
		unset( $request );

		if ( ! $this->is_active() || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$email = $order->get_billing_email();
		$total = (float) $order->get_total();

		$flags = array(
			'honeypot' => false,
			'too_fast' => false,
		);

		$min_seconds = CheckoutHawk_Settings::get_int( 'min_checkout_seconds' );
		$elapsed     = $this->seconds_on_page();

		// Only judge the timing when the checkout page was actually rendered in this session.
		// Express payment buttons reach the Store API straight from a product page or the mini
		// cart, so a missing timer here is normal and must not refuse the order.
		if ( $min_seconds > 0 && $elapsed > 0 && $elapsed < $min_seconds ) {
			$flags['too_fast'] = true;
		}

		$reason = $this->evaluate( $email, $total, $flags );

		if ( '' === $reason ) {
			return;
		}

		$this->log_block( $reason, $email, $order->get_id() );

		if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'checkouthawk_blocked',
				esc_html( $this->customer_message( $reason ) ),
				403
			);
		}

		// Fallback for older WooCommerce versions.
		throw new Exception( esc_html( $this->customer_message( $reason ) ) );
	}

	/**
	 * Run every rule. Returns an empty string when the attempt is allowed.
	 *
	 * @param string $email Billing email.
	 * @param float  $total Order total.
	 * @param array  $flags Trap results.
	 * @return string Reason key.
	 */
	public function evaluate( $email, $total, $flags = array() ) {
		$ip    = CheckoutHawk_Helpers::get_ip();
		$email = CheckoutHawk_Helpers::normalize_email( $email );

		if ( ! empty( $flags['honeypot'] ) ) {
			return 'honeypot';
		}

		if ( ! empty( $flags['too_fast'] ) ) {
			return 'too_fast';
		}

		$blocked = CheckoutHawk_Blocklist::match( $ip, $email );

		if ( '' !== $blocked ) {
			return $blocked;
		}

		if ( CheckoutHawk_Settings::get_bool( 'block_disposable_email' ) && CheckoutHawk_Helpers::is_disposable_email( $email ) ) {
			return 'disposable_email';
		}

		$blocked_countries = CheckoutHawk_Settings::blocked_countries();

		if ( ! empty( $blocked_countries ) ) {
			$country = CheckoutHawk_Helpers::get_country();

			if ( $country && in_array( $country, $blocked_countries, true ) ) {
				return 'blocked_country';
			}
		}

		$min_total = CheckoutHawk_Settings::get_float( 'min_order_total' );

		if ( $min_total > 0 && $total > 0 && $total < $min_total ) {
			return 'below_min_total';
		}

		if ( CheckoutHawk_Panic::is_active() ) {
			if ( CheckoutHawk_Settings::get_bool( 'panic_force_login' ) && ! is_user_logged_in() ) {
				return 'panic_guest';
			}

			$panic_min = CheckoutHawk_Settings::get_float( 'panic_min_total' );

			if ( $panic_min > 0 && $total > 0 && $total < $panic_min ) {
				return 'panic_min_total';
			}
		}

		$rate_limit = CheckoutHawk_Panic::tighten( CheckoutHawk_Settings::get_int( 'checkout_rate_limit' ) );
		$window     = max( 1, CheckoutHawk_Settings::get_int( 'checkout_rate_window' ) );

		if ( $rate_limit > 0 ) {
			$attempts = CheckoutHawk_Velocity::count( 'checkout', 'ip', $ip, $window );

			if ( $attempts >= $rate_limit ) {
				return 'checkout_rate';
			}
		}

		// Passed. Record the attempt so the rate window keeps moving.
		CheckoutHawk_Velocity::record( 'checkout', $ip, $email );

		/**
		 * Final say on whether a checkout attempt is blocked.
		 *
		 * @param string $reason Empty string means allowed.
		 * @param string $email  Billing email.
		 * @param float  $total  Order total.
		 */
		return (string) apply_filters( 'checkouthawk_checkout_reason', '', $email, $total );
	}

	/**
	 * Add to cart flood control.
	 *
	 * @param bool $passed     Current validation state.
	 * @param int  $product_id Product id.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		unset( $quantity );

		if ( ! $passed || ! $this->is_active() ) {
			return $passed;
		}

		$limit = CheckoutHawk_Panic::tighten( CheckoutHawk_Settings::get_int( 'add_to_cart_limit' ) );

		if ( $limit < 1 ) {
			return $passed;
		}

		$ip    = CheckoutHawk_Helpers::get_ip();
		$count = CheckoutHawk_Velocity::count( 'add_to_cart', 'ip', $ip, 1 );

		if ( $count >= $limit ) {
			CheckoutHawk_Logger::log(
				'add_to_cart_blocked',
				array(
					'severity' => 'warning',
					'ip'       => $ip,
					'reason'   => __( 'Add to cart flood limit reached', 'checkouthawk' ),
					'details'  => array(
						'product_id' => (int) $product_id,
						'per_minute' => $count,
					),
				)
			);

			wc_add_notice( __( 'Too many requests. Please wait a moment and try again.', 'checkouthawk' ), 'error' );

			return false;
		}

		CheckoutHawk_Velocity::record( 'add_to_cart', $ip );

		return $passed;
	}

	/**
	 * Log a blocked checkout.
	 *
	 * @param string $reason   Reason key.
	 * @param string $email    Billing email.
	 * @param int    $order_id Draft order id.
	 * @return void
	 */
	protected function log_block( $reason, $email, $order_id = 0 ) {
		CheckoutHawk_Logger::log(
			'checkout_blocked',
			array(
				'severity' => 'warning',
				'ip'       => CheckoutHawk_Helpers::get_ip(),
				'email'    => $email,
				'country'  => CheckoutHawk_Helpers::get_country_if_needed(),
				'order_id' => (int) $order_id,
				'reason'   => self::reason_label( $reason ),
				'details'  => array( 'rule' => $reason ),
			)
		);

		/**
		 * Fires when a checkout attempt is blocked.
		 *
		 * @param string $reason Reason key.
		 * @param string $email  Billing email.
		 */
		do_action( 'checkouthawk_checkout_blocked', $reason, $email );
	}

	/**
	 * The message the shopper sees. Deliberately vague so bots learn nothing.
	 *
	 * @param string $reason Reason key.
	 * @return string
	 */
	protected function customer_message( $reason ) {
		$message = __( 'We could not process this order. Please contact us if you believe this is a mistake.', 'checkouthawk' );

		if ( 'panic_guest' === $reason ) {
			$message = __( 'Guest checkout is temporarily unavailable. Please sign in or create an account to place your order.', 'checkouthawk' );
		}

		if ( 'checkout_rate' === $reason ) {
			$message = __( 'Too many checkout attempts from this connection. Please wait a few minutes and try again.', 'checkouthawk' );
		}

		if ( 'disposable_email' === $reason ) {
			$message = __( 'Please use a permanent email address to place your order.', 'checkouthawk' );
		}

		/**
		 * Filter the message shown to a blocked shopper.
		 *
		 * @param string $message Message text.
		 * @param string $reason  Reason key.
		 */
		return (string) apply_filters( 'checkouthawk_customer_message', $message, $reason );
	}

	/**
	 * Readable label for a reason key.
	 *
	 * @param string $reason Reason key.
	 * @return string
	 */
	public static function reason_label( $reason ) {
		$labels = array(
			'honeypot'         => __( 'Honeypot field filled in', 'checkouthawk' ),
			'too_fast'         => __( 'Checkout submitted too fast', 'checkouthawk' ),
			'blocked_ip'       => __( 'IP address on the block list', 'checkouthawk' ),
			'blocked_email'    => __( 'Email address on the block list', 'checkouthawk' ),
			'blocked_domain'   => __( 'Email domain on the block list', 'checkouthawk' ),
			'disposable_email' => __( 'Disposable email address', 'checkouthawk' ),
			'blocked_country'  => __( 'Blocked country', 'checkouthawk' ),
			'below_min_total'  => __( 'Order total below the minimum', 'checkouthawk' ),
			'panic_guest'      => __( 'Panic mode: guest checkout disabled', 'checkouthawk' ),
			'panic_min_total'  => __( 'Panic mode: order total below the minimum', 'checkouthawk' ),
			'checkout_rate'    => __( 'Too many checkout attempts', 'checkouthawk' ),
		);

		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : ucfirst( str_replace( '_', ' ', (string) $reason ) );
	}
}
