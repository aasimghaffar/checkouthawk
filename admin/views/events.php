<?php
/**
 * Activity log view.
 *
 * @package CheckoutHawk
 *
 * @var CheckoutHawk_Events_Table $table Prepared list table.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap checkouthawk-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Activity log', 'checkouthawk' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="checkouthawk-inline-form">
		<?php wp_nonce_field( 'checkouthawk_export_events' ); ?>
		<input type="hidden" name="action" value="checkouthawk_export_events" />
		<button type="submit" class="page-title-action"><?php esc_html_e( 'Export CSV', 'checkouthawk' ); ?></button>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="checkouthawk-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete every logged event?', 'checkouthawk' ) ); ?>');">
		<?php wp_nonce_field( 'checkouthawk_clear_log' ); ?>
		<input type="hidden" name="action" value="checkouthawk_clear_log" />
		<button type="submit" class="page-title-action"><?php esc_html_e( 'Clear log', 'checkouthawk' ); ?></button>
	</form>

	<hr class="wp-header-end" />

	<form method="get">
		<input type="hidden" name="page" value="checkouthawk-events" />
		<?php
		$table->search_box( __( 'Search log', 'checkouthawk' ), 'checkouthawk-events' );
		$table->display();
		?>
	</form>
</div>
