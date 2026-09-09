<?php
/**
 * GDPR personal data export and erasure.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks CheckoutHawk into the core privacy tools.
 */
class CheckoutHawk_Privacy {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['checkouthawk'] = array(
			'exporter_friendly_name' => __( 'CheckoutHawk checkout security log', 'checkouthawk' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['checkouthawk'] = array(
			'eraser_friendly_name' => __( 'CheckoutHawk checkout security log', 'checkouthawk' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export the log entries recorded against an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function export( $email, $page = 1 ) {
		global $wpdb;

		$email    = CheckoutHawk_Helpers::normalize_email( $email );
		$page     = max( 1, (int) $page );
		$per_page = 100;
		$data     = array();

		if ( '' === $email ) {
			return array(
				'data' => $data,
				'done' => true,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d',
				CheckoutHawk_Install::events_table(),
				$email,
				$per_page,
				( $page - 1 ) * $per_page
			),
			ARRAY_A
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$data[] = array(
					'group_id'    => 'checkouthawk',
					'group_label' => __( 'CheckoutHawk checkout security log', 'checkouthawk' ),
					'item_id'     => 'checkouthawk-event-' . (int) $row['id'],
					'data'        => array(
						array(
							'name'  => __( 'Date', 'checkouthawk' ),
							'value' => CheckoutHawk_Helpers::local_time( $row['event_time'] ),
						),
						array(
							'name'  => __( 'Event', 'checkouthawk' ),
							'value' => CheckoutHawk_Helpers::event_label( $row['event_type'] ),
						),
						array(
							'name'  => __( 'Reason', 'checkouthawk' ),
							'value' => $row['reason'],
						),
						array(
							'name'  => __( 'IP address', 'checkouthawk' ),
							'value' => $row['ip'],
						),
						array(
							'name'  => __( 'Email address', 'checkouthawk' ),
							'value' => $row['email'],
						),
					),
				);
			}
		}

		return array(
			'data' => $data,
			'done' => count( (array) $rows ) < $per_page,
		);
	}

	/**
	 * Erase the log and attempt rows recorded against an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function erase( $email, $page = 1 ) {
		global $wpdb;

		unset( $page );

		$email   = CheckoutHawk_Helpers::normalize_email( $email );
		$removed = 0;

		if ( '' === $email ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$removed += (int) $wpdb->delete( CheckoutHawk_Install::events_table(), array( 'email' => $email ), array( '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$removed += (int) $wpdb->delete( CheckoutHawk_Install::attempts_table(), array( 'email' => $email ), array( '%s' ) );

		$messages = array();
		$retained = false;

		// A live block is a security measure, so it is kept and reported rather than silently dropped.
		if ( CheckoutHawk_Blocklist::find( 'email', $email ) ) {
			$retained   = true;
			$messages[] = __( 'This email address is on the CheckoutHawk block list because of suspected card testing, so the block itself was kept. Remove it under CheckoutHawk then Block list if it is a mistake.', 'checkouthawk' );
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Suggest privacy policy text.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . __( 'This store uses CheckoutHawk to protect the checkout from card testing and fake orders. When an order attempt is refused, or a payment fails, CheckoutHawk records the IP address, the email address given at checkout, the reason and the time. These records stay on this website, are never sent anywhere else, and are deleted automatically after the retention period set by the store owner.', 'checkouthawk' ) . '</p>';

		wp_add_privacy_policy_content( 'CheckoutHawk', wp_kses_post( wpautop( $content, false ) ) );
	}
}
