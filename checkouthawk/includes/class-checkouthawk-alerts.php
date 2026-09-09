<?php
/**
 * Email and webhook alerts.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends throttled alerts when an attack is detected.
 */
class CheckoutHawk_Alerts {

	const THROTTLE_OPTION  = 'checkouthawk_last_alert';
	const THROTTLE_MINUTES = 15;

	/**
	 * Send an attack alert.
	 *
	 * @param string $subject Short subject line.
	 * @param array  $lines   Body lines.
	 * @param array  $context Extra data for the webhook payload.
	 * @return bool True when something was sent.
	 */
	public static function attack_alert( $subject, $lines = array(), $context = array() ) {
		if ( ! CheckoutHawk_Settings::get_bool( 'alerts_enabled' ) ) {
			return false;
		}

		if ( self::is_throttled() ) {
			return false;
		}

		update_option( self::THROTTLE_OPTION, time(), false );

		$sent = self::send_email( $subject, $lines );

		self::send_webhook( $subject, $context );

		return $sent;
	}

	/**
	 * Has an alert already gone out recently?
	 *
	 * @return bool
	 */
	protected static function is_throttled() {
		$last = (int) get_option( self::THROTTLE_OPTION, 0 );

		/**
		 * Filter the minutes between alert emails.
		 *
		 * @param int $minutes Throttle window.
		 */
		$minutes = (int) apply_filters( 'checkouthawk_alert_throttle_minutes', self::THROTTLE_MINUTES );

		return $last && ( time() - $last ) < ( $minutes * MINUTE_IN_SECONDS );
	}

	/**
	 * Send the alert email.
	 *
	 * @param string $subject Subject.
	 * @param array  $lines   Body lines.
	 * @return bool
	 */
	protected static function send_email( $subject, $lines ) {
		$to = CheckoutHawk_Settings::alert_recipient();

		if ( ! is_email( $to ) ) {
			return false;
		}

		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		/* translators: 1: site name, 2: alert subject */
		$mail_subject = sprintf( __( '[%1$s] CheckoutHawk: %2$s', 'checkouthawk' ), $site, $subject );

		$body = array_merge(
			array(
				__( 'CheckoutHawk has detected unusual checkout activity on your store.', 'checkouthawk' ),
				'',
			),
			(array) $lines,
			array(
				'',
				__( 'Open the CheckoutHawk dashboard:', 'checkouthawk' ),
				admin_url( 'admin.php?page=checkouthawk' ),
			)
		);

		return (bool) wp_mail( $to, $mail_subject, implode( "\n", $body ) );
	}

	/**
	 * POST the alert to a webhook if one is configured.
	 *
	 * @param string $subject Subject.
	 * @param array  $context Payload data.
	 * @return void
	 */
	protected static function send_webhook( $subject, $context ) {
		$url = CheckoutHawk_Settings::get( 'alert_webhook' );

		if ( ! $url ) {
			return;
		}

		$payload = array(
			'site'      => home_url(),
			'plugin'    => 'CheckoutHawk',
			'event'     => 'attack_detected',
			'subject'   => $subject,
			'timestamp' => CheckoutHawk_Helpers::now(),
			'context'   => $context,
			// Slack and Discord style receivers read a plain text field.
			'text'      => sprintf( '%s - %s', get_bloginfo( 'name' ), $subject ),
		);

		wp_remote_post(
			$url,
			array(
				'timeout'  => 10,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( $payload ),
			)
		);
	}
}
