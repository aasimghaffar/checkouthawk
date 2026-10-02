<?php
/**
 * Activity log list table.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists protection events.
 */
class CheckoutHawk_Events_Table extends WP_List_Table {

	/**
	 * Set up.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'checkouthawk_event',
				'plural'   => 'checkouthawk_events',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'event_time' => __( 'When', 'checkouthawk' ),
			'event_type' => __( 'Event', 'checkouthawk' ),
			'reason'     => __( 'Reason', 'checkouthawk' ),
			'ip'         => __( 'IP', 'checkouthawk' ),
			'email'      => __( 'Email', 'checkouthawk' ),
			'order_id'   => __( 'Order', 'checkouthawk' ),
		);
	}

	/**
	 * Load rows.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = 25;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read only filters.
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$type   = isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:enable

		$args = array(
			'per_page' => $per_page,
			'page'     => $paged,
			'type'     => $type,
			'search'   => $search,
		);

		$this->items = CheckoutHawk_Logger::get_events( $args );
		$total       = CheckoutHawk_Logger::count_events( $args );

		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'Nothing logged yet. That is good news.', 'checkouthawk' );
	}

	/**
	 * Filter dropdown.
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only filter.
		$current = isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : '';

		$types = array(
			'checkout_blocked'    => __( 'Checkout blocked', 'checkouthawk' ),
			'failed_payment'      => __( 'Failed payment', 'checkouthawk' ),
			'auto_block'          => __( 'Auto block added', 'checkouthawk' ),
			'manual_block'        => __( 'Manual block added', 'checkouthawk' ),
			'register_blocked'    => __( 'Registration blocked', 'checkouthawk' ),
			'add_to_cart_blocked' => __( 'Add to cart blocked', 'checkouthawk' ),
			'attack_detected'     => __( 'Attack detected', 'checkouthawk' ),
			'panic_on'            => __( 'Panic mode on', 'checkouthawk' ),
			'cleanup'             => __( 'Order cleanup', 'checkouthawk' ),
		);

		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="checkouthawk-event-type">' . esc_html__( 'Filter by event', 'checkouthawk' ) . '</label>';
		echo '<select name="event_type" id="checkouthawk-event-type">';
		echo '<option value="">' . esc_html__( 'All events', 'checkouthawk' ) . '</option>';

		foreach ( $types as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
		submit_button( __( 'Filter', 'checkouthawk' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Default column output.
	 *
	 * @param array  $item   Row.
	 * @param string $column Column key.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		$value = isset( $item[ $column ] ) ? $item[ $column ] : '';

		return esc_html( (string) $value );
	}

	/**
	 * Time column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_event_time( $item ) {
		return esc_html( CheckoutHawk_Helpers::local_time( $item['event_time'] ) );
	}

	/**
	 * Event column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_event_type( $item ) {
		$class = 'checkouthawk-pill checkouthawk-pill-' . sanitize_html_class( $item['severity'] );

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( CheckoutHawk_Helpers::event_label( $item['event_type'] ) ) . '</span>';
	}

	/**
	 * IP column with a block action.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_ip( $item ) {
		if ( empty( $item['ip'] ) ) {
			return '&mdash;';
		}

		$actions = array();

		if ( ! CheckoutHawk_Blocklist::find( 'ip', $item['ip'] ) ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action'      => 'checkouthawk_add_block',
						'block_type'  => 'ip',
						'block_value' => rawurlencode( $item['ip'] ),
					),
					admin_url( 'admin-post.php' )
				),
				'checkouthawk_add_block'
			);

			$actions['block'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Block this IP', 'checkouthawk' ) . '</a>';
		} else {
			$actions['blocked'] = '<span class="checkouthawk-muted">' . esc_html__( 'Already blocked', 'checkouthawk' ) . '</span>';
		}

		return esc_html( $item['ip'] ) . $this->row_actions( $actions );
	}

	/**
	 * Order column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_order_id( $item ) {
		$order_id = (int) $item['order_id'];

		if ( ! $order_id ) {
			return '&mdash;';
		}

		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;

		if ( ! $order ) {
			return esc_html( '#' . $order_id );
		}

		return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( '#' . $order_id ) . '</a>';
	}
}
