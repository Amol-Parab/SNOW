<?php
/**
 * Plugin Name:       OzMoneyTalks Tools
 * Description:       Money tools for Indian migrants in Australia: send-money-to-India comparator, settling-in checklist, rent move-in cost calculator, India vs Australia savings comparator, and AUD→INR rate alerts with a weekly email, plus an "Ask" helper that points readers to the right tool or guide.
 * Version:           1.1.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            OzMoneyTalks
 * License:           GPL-2.0-or-later
 * Text Domain:       oz-tools
 */

defined( 'ABSPATH' ) || exit;

// Another copy of this plugin (e.g. one installed under a different folder name) is already
// running. Loading a second copy would crash the site, so this copy stays idle and says so.
if ( defined( 'OZ_TOOLS_VERSION' ) ) {
	add_action( 'admin_notices', function () {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><strong>OzMoneyTalks Tools is installed twice.</strong> Only version '
				. esc_html( OZ_TOOLS_VERSION ) . ' in <code>' . esc_html( basename( OZ_TOOLS_DIR ) ) . '</code> is running. '
				. 'Deactivate the copy you don\'t want, then remove its folder from <code>wp-content/plugins</code> with your host\'s file manager. '
				. 'Don\'t use Delete on the Plugins page: it would also erase the subscriber list that both copies share.</p></div>';
		}
	} );
	return;
}

define( 'OZ_TOOLS_VERSION', '1.1.1' );
define( 'OZ_TOOLS_FILE', __FILE__ );
define( 'OZ_TOOLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'OZ_TOOLS_URL', plugin_dir_url( __FILE__ ) );

require_once OZ_TOOLS_DIR . 'includes/functions.php';
require_once OZ_TOOLS_DIR . 'includes/class-oz-rates.php';
require_once OZ_TOOLS_DIR . 'includes/class-oz-subscribers.php';
require_once OZ_TOOLS_DIR . 'includes/class-oz-alerts.php';
require_once OZ_TOOLS_DIR . 'includes/class-oz-shortcodes.php';
require_once OZ_TOOLS_DIR . 'includes/class-oz-chat-match.php';
require_once OZ_TOOLS_DIR . 'includes/class-oz-chat.php';
require_once OZ_TOOLS_DIR . 'includes/class-oz-admin.php';

register_activation_hook( __FILE__, array( 'Oz_Tools_Subscribers', 'install' ) );
register_activation_hook( __FILE__, array( 'Oz_Tools_Alerts', 'schedule' ) );
register_deactivation_hook( __FILE__, array( 'Oz_Tools_Alerts', 'unschedule' ) );

add_action( 'plugins_loaded', function () {
	Oz_Tools_Subscribers::maybe_upgrade();
	Oz_Tools_Rates::init();
	Oz_Tools_Subscribers::init();
	Oz_Tools_Alerts::init();
	Oz_Tools_Shortcodes::init();
	Oz_Tools_Chat::init();
	if ( is_admin() ) {
		Oz_Tools_Admin::init();
	}
} );
