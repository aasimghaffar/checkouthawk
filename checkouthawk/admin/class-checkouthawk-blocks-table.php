<?php
/**
 * Block list table.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists active block rules.
 */
class CheckoutHawk_Blocks_Table extends WP_List_Table {

	/**
	 * Set up.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'checkouthawk_block',
				'plural'   => 'checkouthawk_blocks',
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
			'block_value' => __( 'Blocked value', 'checkouthawk' ),
			'block_type'  => __( 'Type', 'checkouthawk' ),
			'reason'      => __( 'Reason', 'checkouthawk' ),
			'source'      => __( 'Added by', 'checkouthawk' ),
			'hits'        => __( 'Blocked attempts', 'checkouthawk' ),
			'created_at'  => __( 'Added', 'checkouthawk' ),
			'expires_at'  => __( 'Expires', 'checkouthawk' ),
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
		$type   = isset( $_GET['block_type'] ) ? sanitize_key( wp_unslash( $_GET['block_type'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:enable

		$args = array(
			'per_page' => $per_page,
			'page'     => $paged,
			'type'     => $type,
			'search'   => $search,
		);

		$this->items = CheckoutHawk_Blocklist::get_blocks( $args );
		$total       = CheckoutHawk_Blocklist::count_blocks( $args );

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
		esc_html_e( 'No blocks yet. CheckoutHawk adds them automatically when it sees a card testing pattern.', 'checkouthawk' );
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
	 * Value column with a remove action.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_block_value( $item ) {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'checkouthawk_remove_block',
					'block'  => (int) $item['id'],
				),
				admin_url( 'admin-post.php' )
			),
			'checkouthawk_remove_block'
		);

		$actions = array(
			'remove' => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Remove', 'checkouthawk' ) . '</a>',
		);

		return '<strong>' . esc_html( $item['block_value'] ) . '</strong>' . $this->row_actions( $actions );
	}

	/**
	 * Type column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_block_type( $item ) {
		$types = CheckoutHawk_Blocklist::types();

		return isset( $types[ $item['block_type'] ] ) ? esc_html( $types[ $item['block_type'] ] ) : esc_html( $item['block_type'] );
	}

	/**
	 * Source column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_source( $item ) {
		return 'manual' === $item['source']
			? esc_html__( 'You', 'checkouthawk' )
			: esc_html__( 'CheckoutHawk', 'checkouthawk' );
	}

	/**
	 * Created column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_created_at( $item ) {
		return esc_html( CheckoutHawk_Helpers::local_time( $item['created_at'] ) );
	}

	/**
	 * Expiry column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_expires_at( $item ) {
		if ( empty( $item['expires_at'] ) ) {
			return esc_html__( 'Never', 'checkouthawk' );
		}

		if ( $item['expires_at'] < CheckoutHawk_Helpers::now() ) {
			return '<span class="checkouthawk-muted">' . esc_html__( 'Expired', 'checkouthawk' ) . '</span>';
		}

		return esc_html( CheckoutHawk_Helpers::local_time( $item['expires_at'] ) );
	}
}
