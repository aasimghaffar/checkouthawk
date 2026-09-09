<?php
/**
 * Plugin Name:       CheckoutHawk
 * Plugin URI:        https://cubixsol.com/products/
 * Description:       Stops card testing, fake orders and bot checkouts. Failed-payment velocity blocking, panic mode, checkout traps and order cleanup.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Cubixsol
 * Author URI:        https://cubixsol.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       checkouthawk
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

define( 'CHECKOUTHAWK_VERSION', '1.0.0' );
define( 'CHECKOUTHAWK_FILE', __FILE__ );
define( 'CHECKOUTHAWK_PATH', plugin_dir_path( __FILE__ ) );
define( 'CHECKOUTHAWK_URL', plugin_dir_url( __FILE__ ) );
define( 'CHECKOUTHAWK_BASENAME', plugin_basename( __FILE__ ) );

require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-helpers.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-install.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-settings.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-logger.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-blocklist.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-velocity.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-alerts.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-panic.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-checkout.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-failed-payments.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-registration.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-cleanup.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-privacy.php';
require_once CHECKOUTHAWK_PATH . 'includes/class-checkouthawk-plugin.php';

register_activation_hook( __FILE__, array( 'CheckoutHawk_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CheckoutHawk_Install', 'deactivate' ) );

/**
 * Main plugin instance.
 *
 * @return CheckoutHawk_Plugin
 */
function checkouthawk() {
	return CheckoutHawk_Plugin::instance();
}

/**
 * Declare compatibility with WooCommerce feature flags.
 *
 * @return void
 */
function checkouthawk_declare_compatibility() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CHECKOUTHAWK_FILE, true );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CHECKOUTHAWK_FILE, true );
}
add_action( 'before_woocommerce_init', 'checkouthawk_declare_compatibility' );

/**
 * Boot the plugin once WooCommerce is available.
 *
 * @return void
 */
function checkouthawk_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'checkouthawk_missing_wc_notice' );
		return;
	}

	checkouthawk();
}
add_action( 'plugins_loaded', 'checkouthawk_boot', 20 );

/**
 * Admin notice shown when WooCommerce is not active.
 *
 * @return void
 */
function checkouthawk_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'CheckoutHawk requires WooCommerce to be installed and active. The plugin has stopped running until WooCommerce is available.', 'checkouthawk' );
	echo '</p></div>';
}
