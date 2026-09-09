<?php
/**
 * Plugin bootstrap.
 *
 * @package CheckoutHawk
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the moving parts.
 */
class CheckoutHawk_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var CheckoutHawk_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Checkout protection.
	 *
	 * @var CheckoutHawk_Checkout
	 */
	public $checkout;

	/**
	 * Failed payment watcher.
	 *
	 * @var CheckoutHawk_Failed_Payments
	 */
	public $failed_payments;

	/**
	 * Registration protection.
	 *
	 * @var CheckoutHawk_Registration
	 */
	public $registration;

	/**
	 * Cleanup routines.
	 *
	 * @var CheckoutHawk_Cleanup
	 */
	public $cleanup;

	/**
	 * Privacy tools.
	 *
	 * @var CheckoutHawk_Privacy
	 */
	public $privacy;

	/**
	 * Get the instance.
	 *
	 * @return CheckoutHawk_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set everything up.
	 */
	protected function __construct() {
		CheckoutHawk_Install::maybe_upgrade();

		$this->checkout        = new CheckoutHawk_Checkout();
		$this->failed_payments = new CheckoutHawk_Failed_Payments();
		$this->registration    = new CheckoutHawk_Registration();
		$this->cleanup         = new CheckoutHawk_Cleanup();
		$this->privacy         = new CheckoutHawk_Privacy();

		if ( is_admin() ) {
			require_once CHECKOUTHAWK_PATH . 'admin/class-checkouthawk-admin.php';
			require_once CHECKOUTHAWK_PATH . 'admin/class-checkouthawk-events-table.php';
			require_once CHECKOUTHAWK_PATH . 'admin/class-checkouthawk-blocks-table.php';

			new CheckoutHawk_Admin();
		}

		add_filter( 'plugin_action_links_' . CHECKOUTHAWK_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Add a settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=checkouthawk-settings' ) ) . '">' . esc_html__( 'Settings', 'checkouthawk' ) . '</a>';

		array_unshift( $links, $settings );

		return $links;
	}
}
