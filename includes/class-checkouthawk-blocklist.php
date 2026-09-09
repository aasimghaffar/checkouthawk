<?php
/**
 * Block rules (IP, email, email domain, country).
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages the block table.
 */
class CheckoutHawk_Blocklist {

	/**
	 * Allowed block types.
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'ip'     => __( 'IP address', 'checkouthawk' ),
			'email'  => __( 'Email address', 'checkouthawk' ),
			'domain' => __( 'Email domain', 'checkouthawk' ),
		);
	}

	/**
	 * Add or refresh a block.
	 *
	 * @param string $type    Block type: ip, email, domain.
	 * @param string $value   Value to block.
	 * @param array  $args    Optional: reason, source, minutes (0 = permanent).
	 * @return bool
	 */
	public static function add( $type, $value, $args = array() ) {
		global $wpdb;

		$type  = sanitize_key( $type );
		$value = strtolower( trim( (string) $value ) );

		if ( ! array_key_exists( $type, self::types() ) || '' === $value ) {
			return false;
		}

		if ( 'ip' === $type ) {
			$value = CheckoutHawk_Helpers::sanitize_ip( $value );

			if ( '' === $value ) {
				return false;
			}
		}

		if ( 'email' === $type ) {
			$value = CheckoutHawk_Helpers::normalize_email( $value );

			if ( '' === $value ) {
				return false;
			}
		}

		$args = wp_parse_args(
			$args,
			array(
				'reason'  => '',
				'source'  => 'auto',
				'minutes' => 0,
			)
		);

		$expires = (int) $args['minutes'] > 0 ? CheckoutHawk_Helpers::time_offset( (int) $args['minutes'] ) : null;

		$existing = self::find( $type, $value );

		if ( $existing ) {
			$update = array(
				'reason'     => substr( sanitize_text_field( (string) $args['reason'] ), 0, 191 ),
				'expires_at' => $expires,
			);

			// Never shorten a permanent or manual block automatically.
			if ( 'manual' === $existing['source'] && 'auto' === $args['source'] ) {
				return true;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( CheckoutHawk_Install::blocks_table(), $update, array( 'id' => (int) $existing['id'] ) );

			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			CheckoutHawk_Install::blocks_table(),
			array(
				'block_type'  => $type,
				'block_value' => substr( $value, 0, 191 ),
				'reason'      => substr( sanitize_text_field( (string) $args['reason'] ), 0, 191 ),
				'source'      => 'manual' === $args['source'] ? 'manual' : 'auto',
				'hits'        => 0,
				'created_at'  => CheckoutHawk_Helpers::now(),
				'expires_at'  => $expires,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( $inserted ) {
			CheckoutHawk_Logger::log(
				'manual' === $args['source'] ? 'manual_block' : 'auto_block',
				array(
					'severity' => 'warning',
					'ip'       => 'ip' === $type ? $value : '',
					'email'    => 'email' === $type ? $value : '',
					'reason'   => $args['reason'],
					'details'  => array(
						'type'    => $type,
						'value'   => $value,
						'expires' => $expires,
					),
				)
			);

			/**
			 * Fires after a block rule is created.
			 *
			 * @param string $type  Block type.
			 * @param string $value Blocked value.
			 * @param array  $args  Block args.
			 */
			do_action( 'checkouthawk_block_added', $type, $value, $args );
		}

		return (bool) $inserted;
	}

	/**
	 * Find a block row.
	 *
	 * @param string $type  Block type.
	 * @param string $value Block value.
	 * @return array|null
	 */
	public static function find( $type, $value ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE block_type = %s AND block_value = %s LIMIT 1',
				CheckoutHawk_Install::blocks_table(),
				sanitize_key( $type ),
				strtolower( trim( (string) $value ) )
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Is a value currently blocked?
	 *
	 * @param string $type  Block type.
	 * @param string $value Value to test.
	 * @return bool
	 */
	public static function is_blocked( $type, $value ) {
		$row = self::find( $type, $value );

		if ( ! $row ) {
			return false;
		}

		if ( ! empty( $row['expires_at'] ) && $row['expires_at'] < CheckoutHawk_Helpers::now() ) {
			self::remove( (int) $row['id'], false );

			return false;
		}

		self::record_hit( (int) $row['id'] );

		return true;
	}

	/**
	 * Check every rule that applies to a checkout attempt.
	 *
	 * @param string $ip    IP address.
	 * @param string $email Email address.
	 * @return string Empty string when allowed, otherwise a short reason key.
	 */
	public static function match( $ip, $email ) {
		if ( $ip && self::is_blocked( 'ip', $ip ) ) {
			return 'blocked_ip';
		}

		$email = CheckoutHawk_Helpers::normalize_email( $email );

		if ( $email ) {
			if ( self::is_blocked( 'email', $email ) ) {
				return 'blocked_email';
			}

			$domain = CheckoutHawk_Helpers::email_domain( $email );

			if ( $domain && self::is_blocked( 'domain', $domain ) ) {
				return 'blocked_domain';
			}
		}

		return '';
	}

	/**
	 * Increment the hit counter for a block.
	 *
	 * @param int $id Block id.
	 * @return void
	 */
	protected static function record_hit( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare( 'UPDATE %i SET hits = hits + 1 WHERE id = %d', CheckoutHawk_Install::blocks_table(), (int) $id )
		);
	}

	/**
	 * Remove a block by id.
	 *
	 * @param int  $id  Block id.
	 * @param bool $log Whether to log the release.
	 * @return bool
	 */
	public static function remove( $id, $log = true ) {
		global $wpdb;

		$row = null;

		if ( $log ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', CheckoutHawk_Install::blocks_table(), (int) $id ),
				ARRAY_A
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( CheckoutHawk_Install::blocks_table(), array( 'id' => (int) $id ), array( '%d' ) );

		if ( $deleted && $row ) {
			CheckoutHawk_Logger::log(
				'block_released',
				array(
					'ip'     => 'ip' === $row['block_type'] ? $row['block_value'] : '',
					'email'  => 'email' === $row['block_type'] ? $row['block_value'] : '',
					'reason' => __( 'Released from the block list', 'checkouthawk' ),
				)
			);
		}

		return (bool) $deleted;
	}

	/**
	 * Get block rows.
	 *
	 * @param array $args Query args: per_page, page, type, search.
	 * @return array
	 */
	public static function get_blocks( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'per_page' => 25,
				'page'     => 1,
				'type'     => '',
				'search'   => '',
			)
		);

		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );
		$type     = sanitize_key( $args['type'] );
		$search   = sanitize_text_field( $args['search'] );
		$like     = '%' . $wpdb->esc_like( $search ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				 WHERE ( %s = '' OR block_type = %s )
				 AND ( %s = '' OR block_value LIKE %s )
				 ORDER BY created_at DESC, id DESC
				 LIMIT %d OFFSET %d",
				CheckoutHawk_Install::blocks_table(),
				$type,
				$type,
				$search,
				$like,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count block rows.
	 *
	 * @param array $args Query args.
	 * @return int
	 */
	public static function count_blocks( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'type'   => '',
				'search' => '',
			)
		);

		$type   = sanitize_key( $args['type'] );
		$search = sanitize_text_field( $args['search'] );
		$like   = '%' . $wpdb->esc_like( $search ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i
				 WHERE ( %s = '' OR block_type = %s )
				 AND ( %s = '' OR block_value LIKE %s )",
				CheckoutHawk_Install::blocks_table(),
				$type,
				$type,
				$search,
				$like
			)
		);
	}

	/**
	 * Number of blocks currently in force.
	 *
	 * @return int
	 */
	public static function active_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE expires_at IS NULL OR expires_at > %s',
				CheckoutHawk_Install::blocks_table(),
				CheckoutHawk_Helpers::now()
			)
		);
	}

	/**
	 * Delete expired blocks.
	 *
	 * @return int
	 */
	public static function purge_expired() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE expires_at IS NOT NULL AND expires_at < %s',
				CheckoutHawk_Install::blocks_table(),
				CheckoutHawk_Helpers::now()
			)
		);
	}

	/**
	 * Remove every block rule.
	 *
	 * @return void
	 */
	public static function clear_all() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', CheckoutHawk_Install::blocks_table() ) );
	}
}
