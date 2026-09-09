<?php
/**
 * Shared helper functions.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static helper utilities.
 */
class CheckoutHawk_Helpers {

	/**
	 * Get the visitor IP address, honouring common proxy headers when enabled.
	 *
	 * @return string
	 */
	public static function get_ip() {
		// REMOTE_ADDR is the only value a visitor cannot forge, so it is the default.
		// Proxy headers are used only when the store owner says the site sits behind one,
		// otherwise an attacker could rotate X-Forwarded-For to dodge every rule here,
		// or send a victim's address to get that customer blocked.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$header = self::ip_source_header();

		if ( '' !== $header && isset( $_SERVER[ $header ] ) ) {
			$chain = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );

			// A proxy appends the address it saw, so the last entry is the only one it controls.
			// Reading the first entry would let a visitor prepend any address they like.
			$forwarded = self::sanitize_ip( end( $chain ) );

			if ( '' !== $forwarded ) {
				$ip = $forwarded;
			}
		}

		$ip = self::sanitize_ip( $ip );

		/**
		 * Filter the IP address CheckoutHawk uses for all rules.
		 *
		 * @param string $ip Detected IP address.
		 */
		return (string) apply_filters( 'checkouthawk_visitor_ip', $ip );
	}

	/**
	 * The $_SERVER key to read the visitor IP from, when the site is behind a proxy.
	 *
	 * @return string Empty string when proxy headers are not trusted.
	 */
	public static function ip_source_header() {
		$sources = array(
			'remote_addr'      => '',
			'cf_connecting_ip' => 'HTTP_CF_CONNECTING_IP',
			'x_forwarded_for'  => 'HTTP_X_FORWARDED_FOR',
			'x_real_ip'        => 'HTTP_X_REAL_IP',
			'true_client_ip'   => 'HTTP_TRUE_CLIENT_IP',
		);

		$choice = (string) CheckoutHawk_Settings::get( 'ip_source', 'remote_addr' );

		return isset( $sources[ $choice ] ) ? $sources[ $choice ] : '';
	}

	/**
	 * Available IP source options for the settings screen.
	 *
	 * @return array
	 */
	public static function ip_sources() {
		return array(
			'remote_addr'      => __( 'Direct connection (default, cannot be faked)', 'checkouthawk' ),
			'cf_connecting_ip' => __( 'Behind Cloudflare (CF-Connecting-IP)', 'checkouthawk' ),
			'x_forwarded_for'  => __( 'Behind a proxy or load balancer (X-Forwarded-For)', 'checkouthawk' ),
			'x_real_ip'        => __( 'Behind a proxy (X-Real-IP)', 'checkouthawk' ),
			'true_client_ip'   => __( 'Behind Akamai or Cloudflare Enterprise (True-Client-IP)', 'checkouthawk' ),
		);
	}

	/**
	 * Validate and normalise an IP address string.
	 *
	 * @param string $ip Raw IP.
	 * @return string Empty string when invalid.
	 */
	public static function sanitize_ip( $ip ) {
		$ip = trim( (string) $ip );

		if ( '' === $ip ) {
			return '';
		}

		// Proxy headers can carry a chain, so take the first entry.
		if ( false !== strpos( $ip, ',' ) ) {
			$parts = explode( ',', $ip );
			$ip    = trim( $parts[0] );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Normalise an email address for comparison (lowercase, gmail dot/plus folding off by default).
	 *
	 * @param string $email Raw email.
	 * @return string
	 */
	public static function normalize_email( $email ) {
		$email = sanitize_email( (string) $email );

		return $email ? strtolower( $email ) : '';
	}

	/**
	 * Get the domain part of an email address.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	public static function email_domain( $email ) {
		$email = self::normalize_email( $email );

		if ( '' === $email || false === strpos( $email, '@' ) ) {
			return '';
		}

		$parts = explode( '@', $email );

		return trim( end( $parts ) );
	}

	/**
	 * Anonymise an IP address for storage when privacy mode is on.
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	public static function maybe_anonymize_ip( $ip ) {
		if ( ! CheckoutHawk_Settings::get_bool( 'anonymize_ip' ) ) {
			return $ip;
		}

		if ( function_exists( 'wp_privacy_anonymize_ip' ) ) {
			return (string) wp_privacy_anonymize_ip( $ip );
		}

		return $ip;
	}

	/**
	 * Get the two letter country code for the current visitor.
	 *
	 * @return string
	 */
	public static function get_country() {
		if ( ! class_exists( 'WC_Geolocation' ) ) {
			return '';
		}

		static $cache = array();

		$ip = self::get_ip();

		if ( '' === $ip ) {
			return '';
		}

		if ( isset( $cache[ $ip ] ) ) {
			return $cache[ $ip ];
		}

		// The third argument keeps this local: no fallback request to an external geolocation API.
		$data = WC_Geolocation::geolocate_ip( $ip, false, false );

		$cache[ $ip ] = isset( $data['country'] ) ? strtoupper( (string) $data['country'] ) : '';

		return $cache[ $ip ];
	}

	/**
	 * Country code, but only when a country rule is actually configured.
	 *
	 * Avoids a geolocation lookup on stores that do not use country blocking.
	 *
	 * @return string
	 */
	public static function get_country_if_needed() {
		$rules = CheckoutHawk_Settings::blocked_countries();

		return empty( $rules ) ? '' : self::get_country();
	}

	/**
	 * Current MySQL formatted UTC time.
	 *
	 * @return string
	 */
	public static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * MySQL formatted UTC time, offset by minutes.
	 *
	 * @param int $minutes Minutes to add (negative to subtract).
	 * @return string
	 */
	public static function time_offset( $minutes ) {
		return gmdate( 'Y-m-d H:i:s', time() + ( (int) $minutes * MINUTE_IN_SECONDS ) );
	}

	/**
	 * Convert a stored UTC datetime to the site timezone for display.
	 *
	 * @param string $datetime MySQL datetime in UTC.
	 * @return string
	 */
	public static function local_time( $datetime ) {
		if ( empty( $datetime ) ) {
			return '';
		}

		$timestamp = strtotime( $datetime . ' UTC' );

		if ( ! $timestamp ) {
			return $datetime;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Check whether the current request comes from a logged in customer we trust.
	 *
	 * @return bool
	 */
	public static function is_trusted_user() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		$min_orders = CheckoutHawk_Settings::get_int( 'trusted_customer_orders' );

		if ( $min_orders < 1 ) {
			return false;
		}

		$user_id = get_current_user_id();
		$count   = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $user_id ) : 0;

		return $count >= $min_orders;
	}

	/**
	 * Split a textarea list into a clean array of lowercase values.
	 *
	 * @param string $raw Raw textarea content.
	 * @return array
	 */
	public static function list_to_array( $raw ) {
		$lines = preg_split( '/[\r\n,]+/', (string) $raw );
		$out   = array();

		if ( ! is_array( $lines ) ) {
			return $out;
		}

		foreach ( $lines as $line ) {
			$line = strtolower( trim( $line ) );

			if ( '' !== $line ) {
				$out[] = $line;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Load the bundled disposable email domain list.
	 *
	 * @return array
	 */
	public static function disposable_domains() {
		static $cache     = array();
		static $bundled   = null;
		$custom_setting   = (string) CheckoutHawk_Settings::get( 'custom_disposable_domains' );
		$cache_key        = md5( $custom_setting );

		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		if ( null === $bundled ) {
			$file    = CHECKOUTHAWK_PATH . 'includes/data/disposable-domains.php';
			$bundled = file_exists( $file ) ? (array) require $file : array();
		}

		$domains = $bundled;
		$custom  = self::list_to_array( $custom_setting );

		if ( ! empty( $custom ) ) {
			$domains = array_merge( $domains, $custom );
		}

		/**
		 * Filter the disposable email domain list.
		 *
		 * @param array $domains Domain names.
		 */
		$domains = (array) apply_filters( 'checkouthawk_disposable_domains', $domains );
		$domains = array_flip( array_map( 'strtolower', $domains ) );

		$cache[ $cache_key ] = $domains;

		return $domains;
	}

	/**
	 * Is this email address on a disposable domain?
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function is_disposable_email( $email ) {
		$domain = self::email_domain( $email );

		if ( '' === $domain ) {
			return false;
		}

		$domains = self::disposable_domains();

		return isset( $domains[ $domain ] );
	}

	/**
	 * Human readable label for an event type.
	 *
	 * @param string $type Event type key.
	 * @return string
	 */
	public static function event_label( $type ) {
		$labels = array(
			'checkout_blocked'    => __( 'Checkout blocked', 'checkouthawk' ),
			'register_blocked'    => __( 'Registration blocked', 'checkouthawk' ),
			'failed_payment'      => __( 'Failed payment', 'checkouthawk' ),
			'auto_block'          => __( 'Auto block added', 'checkouthawk' ),
			'manual_block'        => __( 'Manual block added', 'checkouthawk' ),
			'block_released'      => __( 'Block released', 'checkouthawk' ),
			'panic_on'            => __( 'Panic mode on', 'checkouthawk' ),
			'panic_off'           => __( 'Panic mode off', 'checkouthawk' ),
			'attack_detected'     => __( 'Attack detected', 'checkouthawk' ),
			'cleanup'             => __( 'Order cleanup', 'checkouthawk' ),
			'add_to_cart_blocked' => __( 'Add to cart blocked', 'checkouthawk' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( str_replace( '_', ' ', (string) $type ) );
	}
}
