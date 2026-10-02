<?php
/**
 * Dashboard view.
 *
 * @package CheckoutHawk
 *
 * @var array $summary       Event totals for the last 24 hours.
 * @var array $summary_week  Event totals for the last 7 days.
 * @var array $offenders     Top offending IP addresses.
 * @var array $daily         Daily totals for the chart.
 * @var array $recent        Recent events.
 * @var int   $active_blocks Active block rules.
 * @var int   $failed_orders Failed orders on the store.
 * @var bool  $panic         Panic mode state.
 */

defined( 'ABSPATH' ) || exit;

$checkouthawk_blocked_24    = isset( $summary['checkout_blocked'] ) ? (int) $summary['checkout_blocked'] : 0;
$checkouthawk_failed_24     = isset( $summary['failed_payment'] ) ? (int) $summary['failed_payment'] : 0;
$checkouthawk_blocked_week  = isset( $summary_week['checkout_blocked'] ) ? (int) $summary_week['checkout_blocked'] : 0;
$checkouthawk_failed_week   = isset( $summary_week['failed_payment'] ) ? (int) $summary_week['failed_payment'] : 0;
$checkouthawk_max_day       = max( 1, max( array_map( 'intval', $daily ) ) );
$checkouthawk_protection_on = CheckoutHawk_Settings::get_bool( 'protection_enabled' );
?>
<div class="wrap checkouthawk-wrap">
	<h1><?php esc_html_e( 'CheckoutHawk', 'checkouthawk' ); ?></h1>
	<p class="checkouthawk-sub"><?php esc_html_e( 'Card testing, fake orders and bot checkouts, in one place.', 'checkouthawk' ); ?></p>

	<?php if ( ! $checkouthawk_protection_on ) : ?>
		<div class="notice notice-error inline">
			<p>
				<?php esc_html_e( 'Protection is switched off, so no rules are running.', 'checkouthawk' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=checkouthawk-settings' ) ); ?>"><?php esc_html_e( 'Turn it on in settings', 'checkouthawk' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<div class="checkouthawk-panic <?php echo esc_attr( $panic ? 'is-on' : '' ); ?>">
		<div class="checkouthawk-panic-text">
			<h2><?php echo $panic ? esc_html__( 'Panic mode is ON', 'checkouthawk' ) : esc_html__( 'Panic mode is off', 'checkouthawk' ); ?></h2>
			<p>
				<?php
				if ( $panic ) {
					$checkouthawk_ends = CheckoutHawk_Panic::ends_at();

					if ( $checkouthawk_ends ) {
						/* translators: %s: date and time */
						echo esc_html( sprintf( __( 'Stricter rules are in force until %s.', 'checkouthawk' ), CheckoutHawk_Helpers::local_time( gmdate( 'Y-m-d H:i:s', $checkouthawk_ends ) ) ) );
					} else {
						esc_html_e( 'Stricter rules stay in force until you switch them off.', 'checkouthawk' );
					}
				} else {
					esc_html_e( 'Switch this on while you are under attack. Guest checkout can be closed, limits are halved and small test orders are refused.', 'checkouthawk' );
				}
				?>
			</p>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="checkouthawk-panic-form">
			<?php wp_nonce_field( 'checkouthawk_toggle_panic' ); ?>
			<input type="hidden" name="action" value="checkouthawk_toggle_panic" />

			<?php if ( ! $panic ) : ?>
				<label for="checkouthawk-panic-minutes" class="screen-reader-text"><?php esc_html_e( 'Duration in minutes', 'checkouthawk' ); ?></label>
				<select name="panic_minutes" id="checkouthawk-panic-minutes">
					<option value="60"><?php esc_html_e( '1 hour', 'checkouthawk' ); ?></option>
					<option value="120" selected="selected"><?php esc_html_e( '2 hours', 'checkouthawk' ); ?></option>
					<option value="360"><?php esc_html_e( '6 hours', 'checkouthawk' ); ?></option>
					<option value="1440"><?php esc_html_e( '24 hours', 'checkouthawk' ); ?></option>
					<option value="0"><?php esc_html_e( 'Until I turn it off', 'checkouthawk' ); ?></option>
				</select>
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Turn on panic mode', 'checkouthawk' ); ?></button>
			<?php else : ?>
				<button type="submit" class="button button-secondary button-hero"><?php esc_html_e( 'Turn off panic mode', 'checkouthawk' ); ?></button>
			<?php endif; ?>
		</form>
	</div>

	<div class="checkouthawk-tiles">
		<div class="checkouthawk-tile">
			<span class="checkouthawk-tile-label"><?php esc_html_e( 'Attempts blocked (24h)', 'checkouthawk' ); ?></span>
			<span class="checkouthawk-tile-value"><?php echo esc_html( number_format_i18n( $checkouthawk_blocked_24 ) ); ?></span>
			<span class="checkouthawk-tile-foot">
				<?php
				/* translators: %s: number of blocked attempts */
				echo esc_html( sprintf( __( '%s in the last 7 days', 'checkouthawk' ), number_format_i18n( $checkouthawk_blocked_week ) ) );
				?>
			</span>
		</div>
		<div class="checkouthawk-tile">
			<span class="checkouthawk-tile-label"><?php esc_html_e( 'Failed payments (24h)', 'checkouthawk' ); ?></span>
			<span class="checkouthawk-tile-value"><?php echo esc_html( number_format_i18n( $checkouthawk_failed_24 ) ); ?></span>
			<span class="checkouthawk-tile-foot">
				<?php
				/* translators: %s: number of failed payments */
				echo esc_html( sprintf( __( '%s in the last 7 days', 'checkouthawk' ), number_format_i18n( $checkouthawk_failed_week ) ) );
				?>
			</span>
		</div>
		<div class="checkouthawk-tile">
			<span class="checkouthawk-tile-label"><?php esc_html_e( 'Active blocks', 'checkouthawk' ); ?></span>
			<span class="checkouthawk-tile-value"><?php echo esc_html( number_format_i18n( $active_blocks ) ); ?></span>
			<span class="checkouthawk-tile-foot">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=checkouthawk-blocks' ) ); ?>"><?php esc_html_e( 'Manage block list', 'checkouthawk' ); ?></a>
			</span>
		</div>
		<div class="checkouthawk-tile">
			<span class="checkouthawk-tile-label"><?php esc_html_e( 'Failed orders on store', 'checkouthawk' ); ?></span>
			<span class="checkouthawk-tile-value"><?php echo esc_html( $failed_orders >= 500 ? '500+' : number_format_i18n( $failed_orders ) ); ?></span>
			<span class="checkouthawk-tile-foot">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=checkouthawk-settings#checkouthawk-cleanup' ) ); ?>"><?php esc_html_e( 'Clean them up', 'checkouthawk' ); ?></a>
			</span>
		</div>
	</div>

	<div class="checkouthawk-columns">
		<div class="checkouthawk-card">
			<h2><?php esc_html_e( 'Last 14 days', 'checkouthawk' ); ?></h2>
			<div class="checkouthawk-chart">
				<?php foreach ( $daily as $checkouthawk_day => $checkouthawk_total ) : ?>
					<?php $checkouthawk_height = max( 2, (int) round( ( (int) $checkouthawk_total / $checkouthawk_max_day ) * 100 ) ); ?>
					<span class="checkouthawk-bar" style="height:<?php echo esc_attr( $checkouthawk_height ); ?>%">
						<span class="screen-reader-text">
							<?php echo esc_html( $checkouthawk_day . ': ' . $checkouthawk_total ); ?>
						</span>
						<span class="checkouthawk-bar-tip"><?php echo esc_html( $checkouthawk_day . ' — ' . number_format_i18n( (int) $checkouthawk_total ) ); ?></span>
					</span>
				<?php endforeach; ?>
			</div>
			<p class="checkouthawk-muted"><?php esc_html_e( 'Blocked attempts and failed payments per day.', 'checkouthawk' ); ?></p>
		</div>

		<div class="checkouthawk-card">
			<h2><?php esc_html_e( 'Worst offenders (7 days)', 'checkouthawk' ); ?></h2>
			<?php if ( empty( $offenders ) ) : ?>
				<p class="checkouthawk-muted"><?php esc_html_e( 'Nothing worth reporting yet.', 'checkouthawk' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'IP', 'checkouthawk' ); ?></th>
							<th><?php esc_html_e( 'Events', 'checkouthawk' ); ?></th>
							<th><?php esc_html_e( 'Last seen', 'checkouthawk' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $offenders as $checkouthawk_row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $checkouthawk_row['ip'] ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( (int) $checkouthawk_row['total'] ) ); ?></td>
							<td><?php echo esc_html( CheckoutHawk_Helpers::local_time( $checkouthawk_row['last_seen'] ) ); ?></td>
							<td>
								<?php if ( CheckoutHawk_Blocklist::find( 'ip', $checkouthawk_row['ip'] ) ) : ?>
									<span class="checkouthawk-muted"><?php esc_html_e( 'Blocked', 'checkouthawk' ); ?></span>
								<?php else : ?>
									<?php
									$checkouthawk_url = wp_nonce_url(
										add_query_arg(
											array(
												'action'      => 'checkouthawk_add_block',
												'block_type'  => 'ip',
												'block_value' => rawurlencode( $checkouthawk_row['ip'] ),
											),
											admin_url( 'admin-post.php' )
										),
										'checkouthawk_add_block'
									);
									?>
									<a class="button button-small" href="<?php echo esc_url( $checkouthawk_url ); ?>"><?php esc_html_e( 'Block', 'checkouthawk' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<div class="checkouthawk-card">
		<h2><?php esc_html_e( 'Latest activity', 'checkouthawk' ); ?></h2>
		<?php if ( empty( $recent ) ) : ?>
			<p class="checkouthawk-muted"><?php esc_html_e( 'Nothing logged yet. That is good news.', 'checkouthawk' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When', 'checkouthawk' ); ?></th>
						<th><?php esc_html_e( 'Event', 'checkouthawk' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'checkouthawk' ); ?></th>
						<th><?php esc_html_e( 'IP', 'checkouthawk' ); ?></th>
						<th><?php esc_html_e( 'Email', 'checkouthawk' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $recent as $checkouthawk_event ) : ?>
					<tr>
						<td><?php echo esc_html( CheckoutHawk_Helpers::local_time( $checkouthawk_event['event_time'] ) ); ?></td>
						<td><span class="checkouthawk-pill checkouthawk-pill-<?php echo esc_attr( sanitize_html_class( $checkouthawk_event['severity'] ) ); ?>"><?php echo esc_html( CheckoutHawk_Helpers::event_label( $checkouthawk_event['event_type'] ) ); ?></span></td>
						<td><?php echo esc_html( $checkouthawk_event['reason'] ); ?></td>
						<td><?php echo $checkouthawk_event['ip'] ? '<code>' . esc_html( $checkouthawk_event['ip'] ) . '</code>' : '&mdash;'; ?></td>
						<td><?php echo $checkouthawk_event['email'] ? esc_html( $checkouthawk_event['email'] ) : '&mdash;'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=checkouthawk-events' ) ); ?>"><?php esc_html_e( 'See the full activity log', 'checkouthawk' ); ?></a></p>
		<?php endif; ?>
	</div>
</div>
