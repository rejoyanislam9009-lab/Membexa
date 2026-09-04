<?php
/**
 * Plugin Name:       Membexa – Membership & Subscriptions
 * Description:       Create membership plans, subscriptions, gated content, member accounts, WooCommerce entitlements, and modular payment gateway integrations in WordPress.
 * Version:           1.5.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            wpzenora
 * Author URI:        https://profiles.wordpress.org/wpzenora/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       membexa
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEMBEXA_VERSION', '1.5.0' );
define( 'MEMBEXA_FILE', __FILE__ );
define( 'MEMBEXA_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEMBEXA_URL', plugin_dir_url( __FILE__ ) );

require_once MEMBEXA_DIR . 'includes/class-db.php';
require_once MEMBEXA_DIR . 'includes/class-activator.php';
require_once MEMBEXA_DIR . 'includes/class-settings.php';
require_once MEMBEXA_DIR . 'includes/class-account.php';
require_once MEMBEXA_DIR . 'includes/class-plan.php';
require_once MEMBEXA_DIR . 'includes/class-emails.php';
require_once MEMBEXA_DIR . 'includes/class-subscriptions.php';
require_once MEMBEXA_DIR . 'includes/class-gateways.php';
require_once MEMBEXA_DIR . 'includes/class-payment-addons-admin.php';
require_once MEMBEXA_DIR . 'includes/class-access.php';
require_once MEMBEXA_DIR . 'includes/class-commerce.php';
require_once MEMBEXA_DIR . 'includes/class-commerce-lifecycle.php';
require_once MEMBEXA_DIR . 'includes/class-shortcodes.php';
require_once MEMBEXA_DIR . 'includes/class-admin.php';
require_once MEMBEXA_DIR . 'includes/class-integrations-admin.php';
require_once MEMBEXA_DIR . 'includes/class-setup.php';
require_once MEMBEXA_DIR . 'includes/class-help.php';
require_once MEMBEXA_DIR . 'includes/class-privacy.php';
require_once MEMBEXA_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance()->run();
	}
);
