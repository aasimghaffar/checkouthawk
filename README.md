=== CheckoutHawk ===
Contributors: cubixsol
Tags: fraud prevention, card testing, fake orders, spam orders, checkout security
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stops card testing, fake orders and bot checkouts on your store, with velocity blocking, panic mode and failed order cleanup.

== Description ==

A card testing run looks like this: hundreds of tiny orders in a few minutes, nearly all declined, a handful approved, then the chargebacks and the dispute fees. Most stores find out from their payment provider, not from their own dashboard.

CheckoutHawk watches your checkout and reacts while it is happening. It counts failed payments per IP address and per email address, blocks the source automatically once the pattern is obvious, and gives you a one click panic mode for the worst of it.

It works on the classic checkout **and** the Checkout block, because plenty of protection plugins only hook the classic one and quietly do nothing on a modern store.

= What it does =

* **Failed payment velocity blocking.** Too many declines from one IP address or email address inside your chosen window and that source is blocked automatically for as long as you like.
* **Panic mode.** One switch that closes guest checkout, refuses small test orders, and halves every limit while an attack is running. It can also switch itself on when it spots an attack, and off again when the wave has passed.
* **Checkout traps.** A honeypot field, a minimum time on the checkout page, and a per IP rate limit on checkout submissions.
* **Cart flood control.** Stops scripts hammering add to cart.
* **Disposable email blocking.** A built in throwaway domain list you can extend, applied to orders and to new accounts.
* **Country and minimum total rules.** Refuse the cheap product the testers always pick.
* **Attack alerts.** An email, and optionally a webhook for Slack style receivers, when a wave is detected. Throttled so your inbox survives.
* **Failed order cleanup.** An attack leaves thousands of failed orders behind. Delete them on a schedule, or in one pass, with an option to touch only the orders CheckoutHawk itself flagged.
* **A readable log.** Every block, with the reason, the IP address, the email address and the order. Exportable as CSV so you can hand it to your payment provider.

= Works with =

WooCommerce classic checkout, the Checkout block and the Store API, and High Performance Order Storage. Payment gateway agnostic: any gateway that marks an order as failed feeds the velocity rules, including Stripe, PayPal and WooPayments.

= Privacy =

Everything stays on your own site. No accounts, no external service, no data sent anywhere. IP addresses can be stored anonymised if you prefer, and the log has a retention setting. CheckoutHawk also plugs into the WordPress privacy tools, so a customer's log entries are included in a personal data export and removed by a personal data erasure request. If you fill in the optional webhook field, and only then, alert data is posted to the URL you chose.

= Behind Cloudflare or a proxy? =

By default CheckoutHawk uses the direct connection address, because proxy headers can be faked and trusting them would let an attacker slip past every rule, or get one of your customers blocked. If your store sits behind Cloudflare, a CDN or a load balancer, pick the matching option under Settings, where the plugin also shows you the address it currently sees.

Built by [Cubixsol](https://cubixsol.com/products/).

== Installation ==

1. Install and activate the plugin. WooCommerce must be active.
2. Go to **CheckoutHawk** in the admin menu. Protection is on with sensible defaults from the first minute.
3. If you are being attacked right now, press **Turn on panic mode** on the dashboard.
4. Optional: set your alert email under **CheckoutHawk → Settings**.

== Frequently Asked Questions ==

= I am being hit right now. What do I do first? =

Turn on panic mode from the CheckoutHawk dashboard, and pick "until I turn it off". Guest checkout closes, small orders are refused and every limit halves. Then lower the failed payment threshold to 2 in 10 minutes in Settings while the wave lasts.

= Does this stop card testing completely? =

Nothing stops it completely, and any plugin that claims otherwise is selling you something. CheckoutHawk makes your store expensive to test against: attackers get a handful of attempts instead of thousands, and you get told it is happening instead of finding out from a dispute notice. Pair it with your gateway's own rules for the best result.

= Will it block real customers? =

The defaults are deliberately loose enough that ordinary shoppers never see them. Logged in customers with a past order skip every rule, and the thresholds count failed payments, not attempts. If you tighten things during an attack, remember to loosen them afterwards.

= Does it work with the Checkout block? =

Yes. The rules run on the Store API as well as the classic checkout.

= Does it need a CAPTCHA? =

No. It uses a honeypot field and timing instead, so shoppers are not asked to identify traffic lights. You can still run a CAPTCHA plugin alongside it.

= Will deleting failed orders lose real sales? =

Failed orders are attempts that never took payment, so no. The cleanup is off by default, and when you turn it on it only removes orders CheckoutHawk flagged unless you say otherwise.

= Does it slow my store down? =

The rules run at checkout, not on every page view, and they are simple indexed lookups. There is no external API call in the request path.

= Can I block a country or a particular email domain? =

Yes, in Settings. Country rules use the WooCommerce geolocation database on your own server, with no external lookup.

= My store is behind Cloudflare. Will it block everyone? =

No, but check one setting first. Out of the box CheckoutHawk reads the direct connection address, which behind a CDN is the CDN's own address. Go to Settings, look at the line showing the address it currently sees, and if that is not your real IP address, choose the Cloudflare or proxy option. It takes ten seconds and the plugin tells you the answer on screen.

== Screenshots ==

1. The dashboard: what was blocked, what failed, and the panic switch.
2. Panic mode on, with the rules that are in force.
3. The activity log, with the reason for every block.
4. The block list, with automatic and manual entries.
5. Settings, grouped so you can find the one you need mid attack.

== Changelog ==

= 1.0.0 =
* First release.
* Failed payment velocity blocking by IP address and email address.
* Panic mode, manual and automatic.
* Honeypot, checkout timing and checkout rate limiting on classic and block checkout.
* Add to cart flood control.
* Disposable email, country and minimum total rules.
* Registration protection.
* Email and webhook attack alerts.
* Failed order cleanup, scheduled or on demand.
* Activity log with CSV export and a block list you can edit.
* Personal data export and erasure through the WordPress privacy tools.

== Upgrade Notice ==

= 1.0.0 =
First release.
