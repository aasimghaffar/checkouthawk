<?php
/**
 * Settings view.
 *
 * @package CheckoutHawk
 *
 * @var array $settings Current settings.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap checkouthawk-wrap">
	<h1><?php esc_html_e( 'CheckoutHawk settings', 'checkouthawk' ); ?></h1>
	<p class="checkouthawk-sub"><?php esc_html_e( 'The defaults suit most stores. Tighten them if you are being hit right now.', 'checkouthawk' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'checkouthawk_save_settings' ); ?>
		<input type="hidden" name="action" value="checkouthawk_save_settings" />

		<h2 class="title"><?php esc_html_e( 'Protection', 'checkouthawk' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable protection', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[protection_enabled]" value="1" <?php checked( $settings['protection_enabled'], 1 ); ?> />
						<?php esc_html_e( 'Run CheckoutHawk rules on checkout, sign up and cart.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-ipsource"><?php esc_html_e( 'Where visitor IPs come from', 'checkouthawk' ); ?></label></th>
				<td>
					<select name="checkouthawk[ip_source]" id="checkouthawk-ipsource">
						<?php foreach ( CheckoutHawk_Helpers::ip_sources() as $checkouthawk_key => $checkouthawk_label ) : ?>
							<option value="<?php echo esc_attr( $checkouthawk_key ); ?>" <?php selected( $settings['ip_source'], $checkouthawk_key ); ?>><?php echo esc_html( $checkouthawk_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Leave this on the default unless your store sits behind Cloudflare, a CDN or a load balancer. Proxy headers can be faked by anyone, so trusting them on a direct-connection site would let an attacker dodge every rule, or get a real customer blocked.', 'checkouthawk' ); ?>
					</p>
					<p class="description">
						<?php
						$checkouthawk_seen_ip = CheckoutHawk_Helpers::get_ip();
						$checkouthawk_seen_ip = '' !== $checkouthawk_seen_ip ? $checkouthawk_seen_ip : __( 'an unknown address', 'checkouthawk' );

						/* translators: %s: the IP address CheckoutHawk currently sees for the logged in admin */
						echo esc_html( sprintf( __( 'With the current setting, CheckoutHawk sees your connection as %s. If that is not your real IP address, pick the matching option above.', 'checkouthawk' ), $checkouthawk_seen_ip ) );
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-trusted"><?php esc_html_e( 'Trusted customers', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" id="checkouthawk-trusted" name="checkouthawk[trusted_customer_orders]" value="<?php echo esc_attr( $settings['trusted_customer_orders'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Skip all rules for logged in customers with at least this many past orders. 0 means never skip.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Card testing defence', 'checkouthawk' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Failed payment velocity', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[velocity_enabled]" value="1" <?php checked( $settings['velocity_enabled'], 1 ); ?> />
						<?php esc_html_e( 'Watch failed payments and block the source automatically.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-threshold"><?php esc_html_e( 'Block after', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="2" step="1" id="checkouthawk-threshold" name="checkouthawk[failed_payment_threshold]" value="<?php echo esc_attr( $settings['failed_payment_threshold'] ); ?>" class="small-text" />
					<?php esc_html_e( 'failed payments within', 'checkouthawk' ); ?>
					<input type="number" min="1" step="1" name="checkouthawk[failed_payment_window]" value="<?php echo esc_attr( $settings['failed_payment_window'] ); ?>" class="small-text" />
					<?php esc_html_e( 'minutes', 'checkouthawk' ); ?>
					<p class="description"><?php esc_html_e( 'A card testing run produces dozens of declines in a few minutes. Three in fifteen minutes is a safe starting point.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-blockmins"><?php esc_html_e( 'Block length', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" id="checkouthawk-blockmins" name="checkouthawk[auto_block_minutes]" value="<?php echo esc_attr( $settings['auto_block_minutes'] ); ?>" class="small-text" />
					<?php esc_html_e( 'minutes', 'checkouthawk' ); ?>
					<p class="description"><?php esc_html_e( '0 blocks permanently. 720 is twelve hours.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Block by email too', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[block_by_email]" value="1" <?php checked( $settings['block_by_email'], 1 ); ?> />
						<?php esc_html_e( 'Also block the billing email, not only the IP address.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Checkout traps', 'checkouthawk' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Honeypot field', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[honeypot_enabled]" value="1" <?php checked( $settings['honeypot_enabled'], 1 ); ?> />
						<?php esc_html_e( 'Add a hidden field to the classic checkout that only bots fill in.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-minseconds"><?php esc_html_e( 'Minimum time on checkout', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="0" max="30" step="1" id="checkouthawk-minseconds" name="checkouthawk[min_checkout_seconds]" value="<?php echo esc_attr( $settings['min_checkout_seconds'] ); ?>" class="small-text" />
					<?php esc_html_e( 'seconds', 'checkouthawk' ); ?>
					<p class="description"><?php esc_html_e( 'Orders submitted faster than this are refused. 0 turns the check off.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-rate"><?php esc_html_e( 'Checkout rate limit', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" id="checkouthawk-rate" name="checkouthawk[checkout_rate_limit]" value="<?php echo esc_attr( $settings['checkout_rate_limit'] ); ?>" class="small-text" />
					<?php esc_html_e( 'attempts per IP within', 'checkouthawk' ); ?>
					<input type="number" min="1" step="1" name="checkouthawk[checkout_rate_window]" value="<?php echo esc_attr( $settings['checkout_rate_window'] ); ?>" class="small-text" />
					<?php esc_html_e( 'minutes', 'checkouthawk' ); ?>
					<p class="description"><?php esc_html_e( '0 turns the limit off.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-cartlimit"><?php esc_html_e( 'Add to cart limit', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="0" step="1" id="checkouthawk-cartlimit" name="checkouthawk[add_to_cart_limit]" value="<?php echo esc_attr( $settings['add_to_cart_limit'] ); ?>" class="small-text" />
					<?php esc_html_e( 'add to cart actions per IP per minute', 'checkouthawk' ); ?>
					<p class="description"><?php esc_html_e( 'Stops scripts hammering add to cart. 0 turns it off.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Email and country rules', 'checkouthawk' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Disposable email addresses', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[block_disposable_email]" value="1" <?php checked( $settings['block_disposable_email'], 1 ); ?> />
						<?php esc_html_e( 'Refuse orders and sign ups from throwaway email domains.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-domains"><?php esc_html_e( 'Extra blocked domains', 'checkouthawk' ); ?></label></th>
				<td>
					<textarea id="checkouthawk-domains" name="checkouthawk[custom_disposable_domains]" rows="4" class="large-text code" placeholder="example.com"><?php echo esc_textarea( $settings['custom_disposable_domains'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One domain per line, added to the built in list.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-countries"><?php esc_html_e( 'Blocked countries', 'checkouthawk' ); ?></label></th>
				<td>
					<textarea id="checkouthawk-countries" name="checkouthawk[blocked_countries]" rows="3" class="large-text code" placeholder="RU, NG"><?php echo esc_textarea( $settings['blocked_countries'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Two letter country codes, one per line or comma separated. Uses the WooCommerce geolocation database.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-mintotal"><?php esc_html_e( 'Minimum order total', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="0" step="0.01" id="checkouthawk-mintotal" name="checkouthawk[min_order_total]" value="<?php echo esc_attr( $settings['min_order_total'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Card testers use your cheapest product. 0 turns this off.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Panic mode', 'checkouthawk' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Switch on automatically', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[auto_panic]" value="1" <?php checked( $settings['auto_panic'], 1 ); ?> />
						<?php esc_html_e( 'Turn panic mode on by itself when an attack is detected.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-autothreshold"><?php esc_html_e( 'Attack threshold', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="3" step="1" id="checkouthawk-autothreshold" name="checkouthawk[auto_panic_threshold]" value="<?php echo esc_attr( $settings['auto_panic_threshold'] ); ?>" class="small-text" />
					<?php esc_html_e( 'failed payments store wide within', 'checkouthawk' ); ?>
					<input type="number" min="1" step="1" name="checkouthawk[auto_panic_window]" value="<?php echo esc_attr( $settings['auto_panic_window'] ); ?>" class="small-text" />
					<?php esc_html_e( 'minutes', 'checkouthawk' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-panicduration"><?php esc_html_e( 'Stay on for', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="5" step="1" id="checkouthawk-panicduration" name="checkouthawk[panic_duration]" value="<?php echo esc_attr( $settings['panic_duration'] ); ?>" class="small-text" />
					<?php esc_html_e( 'minutes', 'checkouthawk' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'While panic mode is on', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[panic_force_login]" value="1" <?php checked( $settings['panic_force_login'], 1 ); ?> />
						<?php esc_html_e( 'Close guest checkout, so only signed in customers can order.', 'checkouthawk' ); ?>
					</label>
					<p>
						<label for="checkouthawk-panicmin"><?php esc_html_e( 'Refuse orders under', 'checkouthawk' ); ?></label>
						<input type="number" min="0" step="0.01" id="checkouthawk-panicmin" name="checkouthawk[panic_min_total]" value="<?php echo esc_attr( $settings['panic_min_total'] ); ?>" class="small-text" />
					</p>
					<p class="description"><?php esc_html_e( 'Velocity and rate limits are also halved automatically.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Sign ups', 'checkouthawk' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Protect registration', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[registration_protection]" value="1" <?php checked( $settings['registration_protection'], 1 ); ?> />
						<?php esc_html_e( 'Apply the block list and disposable email rules to new accounts.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Alerts', 'checkouthawk' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Send alerts', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[alerts_enabled]" value="1" <?php checked( $settings['alerts_enabled'], 1 ); ?> />
						<?php esc_html_e( 'Email me when an attack is detected. At most one message every 15 minutes.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-alertemail"><?php esc_html_e( 'Send to', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="email" id="checkouthawk-alertemail" name="checkouthawk[alerts_email]" value="<?php echo esc_attr( $settings['alerts_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-webhook"><?php esc_html_e( 'Webhook URL', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="url" id="checkouthawk-webhook" name="checkouthawk[alert_webhook]" value="<?php echo esc_attr( $settings['alert_webhook'] ); ?>" class="regular-text code" placeholder="https://hooks.example.com/..." />
					<p class="description"><?php esc_html_e( 'Optional. A JSON payload is posted here as well, which suits Slack style receivers.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title" id="checkouthawk-cleanup"><?php esc_html_e( 'Failed order cleanup', 'checkouthawk' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Clean up daily', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[cleanup_enabled]" value="1" <?php checked( $settings['cleanup_enabled'], 1 ); ?> />
						<?php esc_html_e( 'Delete old failed orders on a daily schedule.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="checkouthawk-cleandays"><?php esc_html_e( 'Older than', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="1" step="1" id="checkouthawk-cleandays" name="checkouthawk[cleanup_days]" value="<?php echo esc_attr( $settings['cleanup_days'] ); ?>" class="small-text" />
					<?php esc_html_e( 'days', 'checkouthawk' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Which orders', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[cleanup_only_flagged]" value="1" <?php checked( $settings['cleanup_only_flagged'], 1 ); ?> />
						<?php esc_html_e( 'Only orders CheckoutHawk flagged as card testing. Leave this on unless you are sure.', 'checkouthawk' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Deleted orders cannot be restored. Failed orders are never real sales, but check first if you use them for anything.', 'checkouthawk' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Data and privacy', 'checkouthawk' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="checkouthawk-retention"><?php esc_html_e( 'Keep the log for', 'checkouthawk' ); ?></label></th>
				<td>
					<input type="number" min="1" step="1" id="checkouthawk-retention" name="checkouthawk[log_retention_days]" value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>" class="small-text" />
					<?php esc_html_e( 'days', 'checkouthawk' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Anonymise IP addresses', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[anonymize_ip]" value="1" <?php checked( $settings['anonymize_ip'], 1 ); ?> />
						<?php esc_html_e( 'Mask the last part of each IP before storing it. Blocking still works, but is slightly broader.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'On uninstall', 'checkouthawk' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="checkouthawk[delete_data_on_uninstall]" value="1" <?php checked( $settings['delete_data_on_uninstall'], 1 ); ?> />
						<?php esc_html_e( 'Delete the CheckoutHawk tables and settings when the plugin is deleted.', 'checkouthawk' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'checkouthawk' ) ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete matching failed orders now? This cannot be undone.', 'checkouthawk' ) ); ?>');">
		<?php wp_nonce_field( 'checkouthawk_run_cleanup' ); ?>
		<input type="hidden" name="action" value="checkouthawk_run_cleanup" />
		<button type="submit" class="button button-secondary"><?php esc_html_e( 'Run cleanup now', 'checkouthawk' ); ?></button>
		<span class="description"><?php esc_html_e( 'Uses the settings above. Save first if you changed them.', 'checkouthawk' ); ?></span>
	</form>
</div>
