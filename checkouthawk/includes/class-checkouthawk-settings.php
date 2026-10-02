<?php
/**
 * Settings storage and defaults.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin settings option.
 */
class CheckoutHawk_Settings {

	const OPTION = 'checkouthawk_settings';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Core.
			'protection_enabled'        => 1,
			'trusted_customer_orders'   => 1,
			'ip_source'                 => 'remote_addr',

			// Checkout traps.
			'honeypot_enabled'          => 1,
			'min_checkout_seconds'      => 4,
			'checkout_rate_limit'       => 6,
			'checkout_rate_window'      => 10,

			// Failed payment velocity.
			'velocity_enabled'          => 1,
			'failed_payment_threshold'  => 3,
			'failed_payment_window'     => 15,
			'auto_block_minutes'        => 720,
			'block_by_email'            => 1,

			// Email and country rules.
			'block_disposable_email'    => 1,
			'custom_disposable_domains' => '',
			'blocked_countries'         => '',
			'min_order_total'           => 0,

			// Panic mode.
			'auto_panic'                => 1,
			'auto_panic_threshold'      => 10,
			'auto_panic_window'         => 10,
			'panic_duration'            => 120,
			'panic_force_login'         => 1,
			'panic_min_total'           => 0,

			// Registration and cart.
			'registration_protection'   => 1,
			'add_to_cart_limit'         => 60,

			// Alerts.
			'alerts_enabled'            => 1,
			'alerts_email'              => '',
			'alert_webhook'             => '',

			// Housekeeping.
			'cleanup_enabled'           => 0,
			'cleanup_days'              => 14,
			'cleanup_only_flagged'      => 1,
			'log_retention_days'        => 30,
			'anonymize_ip'              => 0,
			'delete_data_on_uninstall'  => 0,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );

			if ( ! is_array( $stored ) ) {
				$stored = array();
			}

			self::$cache = wp_parse_args( $stored, self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();

		if ( ! array_key_exists( $key, $all ) ) {
			return $default;
		}

		/**
		 * Filter a single CheckoutHawk setting value.
		 *
		 * @param mixed  $value Setting value.
		 * @param string $key   Setting key.
		 */
		return apply_filters( 'checkouthawk_setting', $all[ $key ], $key );
	}

	/**
	 * Get a boolean setting.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function get_bool( $key ) {
		return (bool) self::get( $key, false );
	}

	/**
	 * Get an integer setting.
	 *
	 * @param string $key Setting key.
	 * @return int
	 */
	public static function get_int( $key ) {
		return (int) self::get( $key, 0 );
	}

	/**
	 * Get a float setting.
	 *
	 * @param string $key Setting key.
	 * @return float
	 */
	public static function get_float( $key ) {
		return (float) self::get( $key, 0 );
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Sanitized settings.
	 * @return void
	 */
	public static function save( array $settings ) {
		$settings    = wp_parse_args( $settings, self::defaults() );
		self::$cache = $settings;

		update_option( self::OPTION, $settings, false );
	}

	/**
	 * Sanitize a settings array coming from the settings form.
	 *
	 * @param array $raw Form input, already unslashed by the caller.
	 * @return array
	 */
	public static function sanitize( array $raw ) {
		$defaults = self::defaults();
		$clean    = array();

		$checkboxes = array(
			'protection_enabled',
			'honeypot_enabled',
			'velocity_enabled',
			'block_by_email',
			'block_disposable_email',
			'auto_panic',
			'panic_force_login',
			'registration_protection',
			'alerts_enabled',
			'cleanup_enabled',
			'cleanup_only_flagged',
			'anonymize_ip',
			'delete_data_on_uninstall',
		);

		$integers = array(
			'trusted_customer_orders',
			'min_checkout_seconds',
			'checkout_rate_limit',
			'checkout_rate_window',
			'failed_payment_threshold',
			'failed_payment_window',
			'auto_block_minutes',
			'auto_panic_threshold',
			'auto_panic_window',
			'panic_duration',
			'add_to_cart_limit',
			'cleanup_days',
			'log_retention_days',
		);

		foreach ( $defaults as $key => $default ) {
			if ( in_array( $key, $checkboxes, true ) ) {
				$clean[ $key ] = ! empty( $raw[ $key ] ) ? 1 : 0;
				continue;
			}

			if ( in_array( $key, $integers, true ) ) {
				$clean[ $key ] = isset( $raw[ $key ] ) ? max( 0, (int) $raw[ $key ] ) : (int) $default;
				continue;
			}

			// Anything that is not a plain scalar (an array injected into the form) is discarded.
			$value = ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ) ? (string) $raw[ $key ] : null;

			switch ( $key ) {
				case 'min_order_total':
				case 'panic_min_total':
					$clean[ $key ] = null !== $value ? (float) max( 0, (float) $value ) : (float) $default;
					break;

				case 'alerts_email':
					$clean[ $key ] = null !== $value ? sanitize_email( $value ) : '';
					break;

				case 'alert_webhook':
					$clean[ $key ] = null !== $value ? esc_url_raw( $value ) : '';
					break;

				case 'ip_source':
					$choice        = null !== $value ? sanitize_key( $value ) : '';
					$sources       = CheckoutHawk_Helpers::ip_sources();
					$clean[ $key ] = isset( $sources[ $choice ] ) ? $choice : 'remote_addr';
					break;

				case 'custom_disposable_domains':
				case 'blocked_countries':
					$text          = null !== $value ? sanitize_textarea_field( $value ) : '';
					$clean[ $key ] = implode( "\n", CheckoutHawk_Helpers::list_to_array( $text ) );
					break;

				default:
					$clean[ $key ] = null !== $value ? sanitize_text_field( $value ) : $default;
					break;
			}
		}

		// Guard rails so a typo cannot lock the store out.
		if ( $clean['failed_payment_threshold'] < 2 ) {
			$clean['failed_payment_threshold'] = 2;
		}

		if ( $clean['failed_payment_window'] < 1 ) {
			$clean['failed_payment_window'] = 1;
		}

		if ( $clean['checkout_rate_limit'] > 0 && $clean['checkout_rate_limit'] < 3 ) {
			$clean['checkout_rate_limit'] = 3;
		}

		if ( $clean['min_checkout_seconds'] > 30 ) {
			$clean['min_checkout_seconds'] = 30;
		}

		return $clean;
	}

	/**
	 * Blocked country codes as an array.
	 *
	 * @return array
	 */
	public static function blocked_countries() {
		$list = CheckoutHawk_Helpers::list_to_array( self::get( 'blocked_countries' ) );

		return array_map( 'strtoupper', $list );
	}

	/**
	 * Alert recipient email.
	 *
	 * @return string
	 */
	public static function alert_recipient() {
		$email = self::get( 'alerts_email' );

		return $email ? $email : get_option( 'admin_email' );
	}
}
