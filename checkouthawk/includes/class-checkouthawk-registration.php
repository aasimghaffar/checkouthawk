<?php
/**
 * Registration and account spam protection.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies the block list and disposable email rules to sign ups.
 */
class CheckoutHawk_Registration {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_filter( 'woocommerce_registration_errors', array( $this, 'validate_wc_registration' ), 10, 3 );
		add_filter( 'registration_errors', array( $this, 'validate_wp_registration' ), 10, 3 );
	}

	/**
	 * Should the rules run?
	 *
	 * @return bool
	 */
	protected function is_active() {
		return CheckoutHawk_Settings::get_bool( 'protection_enabled' )
			&& CheckoutHawk_Settings::get_bool( 'registration_protection' )
			&& ! current_user_can( 'manage_woocommerce' );
	}

	/**
	 * WooCommerce account registration.
	 *
	 * @param WP_Error $errors   Errors object.
	 * @param string   $username Username.
	 * @param string   $email    Email address.
	 * @return WP_Error
	 */
	public function validate_wc_registration( $errors, $username, $email ) {
		unset( $username );

		return $this->check( $errors, $email );
	}

	/**
	 * Core WordPress registration.
	 *
	 * @param WP_Error $errors   Errors object.
	 * @param string   $login    Username.
	 * @param string   $email    Email address.
	 * @return WP_Error
	 */
	public function validate_wp_registration( $errors, $login, $email ) {
		unset( $login );

		return $this->check( $errors, $email );
	}

	/**
	 * Shared rule check.
	 *
	 * @param WP_Error $errors Errors object.
	 * @param string   $email  Email address.
	 * @return WP_Error
	 */
	protected function check( $errors, $email ) {
		if ( ! $this->is_active() || ! is_wp_error( $errors ) ) {
			return $errors;
		}

		$ip     = CheckoutHawk_Helpers::get_ip();
		$email  = CheckoutHawk_Helpers::normalize_email( $email );
		$reason = CheckoutHawk_Blocklist::match( $ip, $email );

		if ( '' === $reason && CheckoutHawk_Settings::get_bool( 'block_disposable_email' ) && CheckoutHawk_Helpers::is_disposable_email( $email ) ) {
			$reason = 'disposable_email';
		}

		if ( '' === $reason ) {
			CheckoutHawk_Velocity::record( 'register', $ip, $email );

			return $errors;
		}

		CheckoutHawk_Logger::log(
			'register_blocked',
			array(
				'severity' => 'warning',
				'ip'       => $ip,
				'email'    => $email,
				'reason'   => CheckoutHawk_Checkout::reason_label( $reason ),
				'details'  => array( 'rule' => $reason ),
			)
		);

		$errors->add(
			'checkouthawk_registration_blocked',
			'disposable_email' === $reason
				? __( 'Please use a permanent email address to create an account.', 'checkouthawk' )
				: __( 'We could not create an account with these details.', 'checkouthawk' )
		);

		return $errors;
	}
}
