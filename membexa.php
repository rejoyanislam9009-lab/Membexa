<?php
/**
 * Plugin Name:       Membexa – Membership & Subscription Plugin
 * Description:       Create membership plans, recurring subscriptions, gated content, member accounts, and Stripe payments in WordPress.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            wpzenora
 * Author URI:        https://profiles.wordpress.org/wpzenora/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       membexa
 * Domain Path:       /languages
 *
 * @package Membexa
 */

namespace Membexa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEMBEXA_VERSION', '1.0.0' );
define( 'MEMBEXA_FILE', __FILE__ );
define( 'MEMBEXA_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEMBEXA_URL', plugin_dir_url( __FILE__ ) );

require_once MEMBEXA_DIR . 'includes/class-membexa-db.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-activator.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-settings.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-plan.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-emails.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-subscriptions.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-stripe.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-access.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-shortcodes.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-admin.php';
require_once MEMBEXA_DIR . 'includes/class-membexa-privacy.php';
require_once MEMBEXA_DIR . 'includes/class-membexa.php';

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance()->run();
	}
);
