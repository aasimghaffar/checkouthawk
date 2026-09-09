<?php
/**
 * Block list view.
 *
 * @package CheckoutHawk
 *
 * @var CheckoutHawk_Blocks_Table $table Prepared list table.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap checkouthawk-wrap">
	<h1><?php esc_html_e( 'Block list', 'checkouthawk' ); ?></h1>
	<p class="checkouthawk-sub"><?php esc_html_e( 'CheckoutHawk adds blocks automatically when it sees repeated failed payments. You can add your own here.', 'checkouthawk' ); ?></p>

	<div class="checkouthawk-card">
		<h2><?php esc_html_e( 'Add a block', 'checkouthawk' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="checkouthawk-add-block">
			<?php wp_nonce_field( 'checkouthawk_add_block' ); ?>
			<input type="hidden" name="action" value="checkouthawk_add_block" />

			<label for="checkouthawk-block-type"><?php esc_html_e( 'Type', 'checkouthawk' ); ?></label>
			<select name="block_type" id="checkouthawk-block-type">
				<?php foreach ( CheckoutHawk_Blocklist::types() as $checkouthawk_key => $checkouthawk_label ) : ?>
					<option value="<?php echo esc_attr( $checkouthawk_key ); ?>"><?php echo esc_html( $checkouthawk_label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="checkouthawk-block-value"><?php esc_html_e( 'Value', 'checkouthawk' ); ?></label>
			<input type="text" name="block_value" id="checkouthawk-block-value" class="regular-text" placeholder="203.0.113.10" required="required" />

			<label for="checkouthawk-block-hours"><?php esc_html_e( 'Hours', 'checkouthawk' ); ?></label>
			<input type="number" name="block_hours" id="checkouthawk-block-hours" min="0" step="1" value="0" class="small-text" />
			<span class="description"><?php esc_html_e( '0 keeps the block until you remove it.', 'checkouthawk' ); ?></span>

			<label for="checkouthawk-block-reason"><?php esc_html_e( 'Note', 'checkouthawk' ); ?></label>
			<input type="text" name="block_reason" id="checkouthawk-block-reason" class="regular-text" />

			<button type="submit" class="button button-primary"><?php esc_html_e( 'Add block', 'checkouthawk' ); ?></button>
		</form>
	</div>

	<form method="get">
		<input type="hidden" name="page" value="checkouthawk-blocks" />
		<?php
		$table->search_box( __( 'Search blocks', 'checkouthawk' ), 'checkouthawk-blocks' );
		$table->display();
		?>
	</form>
</div>
