<?php
/**
 * Admin screens and actions.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the admin area.
 */
class CheckoutHawk_Admin {

	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'panic_notice' ) );

		add_action( 'admin_post_checkouthawk_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_checkouthawk_toggle_panic', array( $this, 'handle_toggle_panic' ) );
		add_action( 'admin_post_checkouthawk_add_block', array( $this, 'handle_add_block' ) );
		add_action( 'admin_post_checkouthawk_remove_block', array( $this, 'handle_remove_block' ) );
		add_action( 'admin_post_checkouthawk_run_cleanup', array( $this, 'handle_run_cleanup' ) );
		add_action( 'admin_post_checkouthawk_clear_log', array( $this, 'handle_clear_log' ) );
		add_action( 'admin_post_checkouthawk_export_events', array( $this, 'handle_export_events' ) );
	}

	/**
	 * Register menu pages.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page(
			__( 'CheckoutHawk', 'checkouthawk' ),
			__( 'CheckoutHawk', 'checkouthawk' ),
			self::CAPABILITY,
			'checkouthawk',
			array( $this, 'render_dashboard' ),
			'dashicons-shield-alt',
			57
		);

		add_submenu_page(
			'checkouthawk',
			__( 'Dashboard', 'checkouthawk' ),
			__( 'Dashboard', 'checkouthawk' ),
			self::CAPABILITY,
			'checkouthawk',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'checkouthawk',
			__( 'Activity log', 'checkouthawk' ),
			__( 'Activity log', 'checkouthawk' ),
			self::CAPABILITY,
			'checkouthawk-events',
			array( $this, 'render_events' )
		);

		add_submenu_page(
			'checkouthawk',
			__( 'Block list', 'checkouthawk' ),
			__( 'Block list', 'checkouthawk' ),
			self::CAPABILITY,
			'checkouthawk-blocks',
			array( $this, 'render_blocks' )
		);

		add_submenu_page(
			'checkouthawk',
			__( 'Settings', 'checkouthawk' ),
			__( 'Settings', 'checkouthawk' ),
			self::CAPABILITY,
			'checkouthawk-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Enqueue admin styles on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'checkouthawk' ) ) {
			return;
		}

		wp_enqueue_style(
			'checkouthawk-admin',
			CHECKOUTHAWK_URL . 'admin/assets/css/admin.css',
			array(),
			CHECKOUTHAWK_VERSION
		);
	}

	/**
	 * Persistent notice while panic mode is on.
	 *
	 * @return void
	 */
	public function panic_notice() {
		if ( ! current_user_can( self::CAPABILITY ) || ! CheckoutHawk_Panic::is_active() ) {
			return;
		}

		// Kept to the screens a shop manager works on, so it never follows anyone around the admin.
		if ( ! $this->is_store_screen() ) {
			return;
		}

		$ends = CheckoutHawk_Panic::ends_at();

		echo '<div class="notice notice-warning"><p><strong>';
		echo esc_html__( 'CheckoutHawk panic mode is on.', 'checkouthawk' );
		echo '</strong> ';

		if ( $ends ) {
			/* translators: %s: date and time panic mode ends */
			echo esc_html( sprintf( __( 'Stricter checkout rules are in force until %s.', 'checkouthawk' ), CheckoutHawk_Helpers::local_time( gmdate( 'Y-m-d H:i:s', $ends ) ) ) );
		} else {
			echo esc_html__( 'Stricter checkout rules stay in force until you switch them off.', 'checkouthawk' );
		}

		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=checkouthawk' ) ) . '">' . esc_html__( 'Open CheckoutHawk', 'checkouthawk' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Is the current admin screen one where a store owner would expect to hear about the checkout?
	 *
	 * @return bool
	 */
	protected function is_store_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		if ( 'dashboard' === $screen->id || false !== strpos( $screen->id, 'checkouthawk' ) ) {
			return true;
		}

		if ( false !== strpos( $screen->id, 'woocommerce' ) || false !== strpos( $screen->id, 'wc-orders' ) ) {
			return true;
		}

		return 'shop_order' === $screen->post_type;
	}

	/**
	 * Verify a request came from an admin form.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	protected function verify( $action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'checkouthawk' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirect back to a plugin page with a status message.
	 *
	 * @param string $page    Page slug.
	 * @param string $message Message key.
	 * @return void
	 */
	protected function redirect( $page, $message = '' ) {
		$url = admin_url( 'admin.php?page=' . sanitize_key( $page ) );

		if ( $message ) {
			$url = add_query_arg( 'checkouthawk_message', sanitize_key( $message ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Show the message set by a redirect.
	 *
	 * @return void
	 */
	protected function render_message() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only notice text.
		$key = isset( $_GET['checkouthawk_message'] ) ? sanitize_key( wp_unslash( $_GET['checkouthawk_message'] ) ) : '';

		if ( '' === $key ) {
			return;
		}

		$messages = array(
			'settings_saved' => __( 'Settings saved.', 'checkouthawk' ),
			'panic_on'       => __( 'Panic mode is on. Stricter checkout rules are now in force.', 'checkouthawk' ),
			'panic_off'      => __( 'Panic mode is off. Normal checkout rules are back.', 'checkouthawk' ),
			'block_added'    => __( 'Block added.', 'checkouthawk' ),
			'block_failed'   => __( 'That value could not be blocked. Check the format and try again.', 'checkouthawk' ),
			'block_removed'  => __( 'Block removed.', 'checkouthawk' ),
			'cleanup_done'   => __( 'Failed orders removed.', 'checkouthawk' ),
			'log_cleared'    => __( 'Activity log cleared.', 'checkouthawk' ),
		);

		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}

		$class = 'block_failed' === $key ? 'notice-error' : 'notice-success';

		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		$this->verify( 'checkouthawk_save_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce and capability verified above; each field is sanitized per type in CheckoutHawk_Settings::sanitize().
		$raw = isset( $_POST['checkouthawk'] ) ? (array) wp_unslash( $_POST['checkouthawk'] ) : array();

		CheckoutHawk_Settings::save( CheckoutHawk_Settings::sanitize( $raw ) );

		$this->redirect( 'checkouthawk-settings', 'settings_saved' );
	}

	/**
	 * Turn panic mode on or off.
	 *
	 * @return void
	 */
	public function handle_toggle_panic() {
		$this->verify( 'checkouthawk_toggle_panic' );

		if ( CheckoutHawk_Panic::is_active() ) {
			CheckoutHawk_Panic::disable( 'manual' );

			$this->redirect( 'checkouthawk', 'panic_off' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$minutes = isset( $_POST['panic_minutes'] ) ? absint( wp_unslash( $_POST['panic_minutes'] ) ) : 0;

		CheckoutHawk_Panic::enable( $minutes, 'manual', __( 'Switched on from the dashboard', 'checkouthawk' ) );

		$this->redirect( 'checkouthawk', 'panic_on' );
	}

	/**
	 * Add a manual block.
	 *
	 * @return void
	 */
	public function handle_add_block() {
		$this->verify( 'checkouthawk_add_block' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- verified above.
		$type  = isset( $_REQUEST['block_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['block_type'] ) ) : 'ip';
		$value = isset( $_REQUEST['block_value'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['block_value'] ) ) : '';
		$hours = isset( $_REQUEST['block_hours'] ) ? absint( wp_unslash( $_REQUEST['block_hours'] ) ) : 0;
		$note  = isset( $_REQUEST['block_reason'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['block_reason'] ) ) : '';
		// phpcs:enable

		$added = CheckoutHawk_Blocklist::add(
			$type,
			$value,
			array(
				'reason'  => $note ? $note : __( 'Added by hand', 'checkouthawk' ),
				'source'  => 'manual',
				'minutes' => $hours * 60,
			)
		);

		$this->redirect( 'checkouthawk-blocks', $added ? 'block_added' : 'block_failed' );
	}

	/**
	 * Remove a block.
	 *
	 * @return void
	 */
	public function handle_remove_block() {
		$this->verify( 'checkouthawk_remove_block' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$id = isset( $_REQUEST['block'] ) ? absint( wp_unslash( $_REQUEST['block'] ) ) : 0;

		if ( $id ) {
			CheckoutHawk_Blocklist::remove( $id );
		}

		$this->redirect( 'checkouthawk-blocks', 'block_removed' );
	}

	/**
	 * Run the failed order cleanup now.
	 *
	 * @return void
	 */
	public function handle_run_cleanup() {
		$this->verify( 'checkouthawk_run_cleanup' );

		checkouthawk()->cleanup->run( 200 );

		$this->redirect( 'checkouthawk-settings', 'cleanup_done' );
	}

	/**
	 * Empty the activity log.
	 *
	 * @return void
	 */
	public function handle_clear_log() {
		$this->verify( 'checkouthawk_clear_log' );

		CheckoutHawk_Logger::truncate();

		$this->redirect( 'checkouthawk-events', 'log_cleared' );
	}

	/**
	 * Export the activity log as CSV.
	 *
	 * @return void
	 */
	public function handle_export_events() {
		$this->verify( 'checkouthawk_export_events' );

		$rows = CheckoutHawk_Logger::get_events(
			array(
				'per_page' => 200,
				'page'     => 1,
			)
		);

		$lines = array( $this->csv_line( array( 'time_utc', 'event', 'severity', 'ip', 'email', 'country', 'order_id', 'reason' ) ) );

		foreach ( $rows as $row ) {
			$lines[] = $this->csv_line(
				array(
					$row['event_time'],
					$row['event_type'],
					$row['severity'],
					$row['ip'],
					$row['email'],
					$row['country'],
					$row['order_id'],
					$row['reason'],
				)
			);
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=checkouthawk-log-' . gmdate( 'Y-m-d' ) . '.csv' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, each field is escaped by csv_line().
		echo implode( "\r\n", $lines );
		exit;
	}

	/**
	 * Build one CSV row.
	 *
	 * @param array $fields Field values.
	 * @return string
	 */
	protected function csv_line( $fields ) {
		$out = array();

		foreach ( (array) $fields as $field ) {
			$field = (string) $field;

			// Stop spreadsheet formula injection.
			if ( '' !== $field && in_array( $field[0], array( '=', '+', '-', '@' ), true ) ) {
				$field = "'" . $field;
			}

			$out[] = '"' . str_replace( '"', '""', $field ) . '"';
		}

		return implode( ',', $out );
	}

	/**
	 * Dashboard screen.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		$this->render_message();

		$summary       = CheckoutHawk_Logger::summary( 24 );
		$summary_week  = CheckoutHawk_Logger::summary( 24 * 7 );
		$offenders     = CheckoutHawk_Logger::top_offenders( 24 * 7, 8 );
		$daily         = CheckoutHawk_Logger::daily_totals( 14 );
		$recent        = CheckoutHawk_Logger::get_events( array( 'per_page' => 10 ) );
		$active_blocks = CheckoutHawk_Blocklist::active_count();
		$failed_orders = CheckoutHawk_Cleanup::failed_order_count();
		$panic         = CheckoutHawk_Panic::is_active();

		include CHECKOUTHAWK_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Activity log screen.
	 *
	 * @return void
	 */
	public function render_events() {
		$this->render_message();

		$table = new CheckoutHawk_Events_Table();
		$table->prepare_items();

		include CHECKOUTHAWK_PATH . 'admin/views/events.php';
	}

	/**
	 * Block list screen.
	 *
	 * @return void
	 */
	public function render_blocks() {
		$this->render_message();

		$table = new CheckoutHawk_Blocks_Table();
		$table->prepare_items();

		include CHECKOUTHAWK_PATH . 'admin/views/blocks.php';
	}

	/**
	 * Settings screen.
	 *
	 * @return void
	 */
	public function render_settings() {
		$this->render_message();

		$settings = CheckoutHawk_Settings::all();

		include CHECKOUTHAWK_PATH . 'admin/views/settings.php';
	}
}
