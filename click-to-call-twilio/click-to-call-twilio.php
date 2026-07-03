<?php
/**
 * Plugin Name:       Click to Call (Twilio)
 * Plugin URI:        https://example.com/click-to-call-twilio
 * Description:        A 100% self-contained "Click to Call" button. Visitors enter a US phone number, receive a Twilio SMS to confirm, and on replying YES are connected by phone to your sales team. Place it anywhere with the [click_to_call] shortcode or auto-add it to the footer. Full control over size, font, colour and background.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Click to Call
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       click-to-call-twilio
 *
 * This plugin is deliberately standalone: it does not depend on, hook into, or
 * modify any theme or other plugin. Everything it needs lives inside this folder.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'CTC_VERSION', '1.0.0' );
define( 'CTC_PLUGIN_FILE', __FILE__ );
define( 'CTC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CTC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CTC_OPTION_KEY', 'ctc_settings' );
define( 'CTC_REST_NAMESPACE', 'click-to-call/v1' );

require_once CTC_PLUGIN_DIR . 'includes/class-ctc-settings.php';
require_once CTC_PLUGIN_DIR . 'includes/class-ctc-phone.php';
require_once CTC_PLUGIN_DIR . 'includes/class-ctc-twilio.php';
require_once CTC_PLUGIN_DIR . 'includes/class-ctc-rest.php';
require_once CTC_PLUGIN_DIR . 'includes/class-ctc-shortcode.php';

/**
 * Boot the plugin. Each piece is namespaced under the CTC_ prefix and is inert
 * until the site owner adds their Twilio credentials on the settings screen.
 */
function ctc_bootstrap() {
	CTC_Settings::instance();
	CTC_Shortcode::instance();
	CTC_Rest::instance();
}
add_action( 'plugins_loaded', 'ctc_bootstrap' );

/**
 * On activation, seed sensible default settings without overwriting anything the
 * owner may have already saved.
 */
function ctc_activate() {
	$existing = get_option( CTC_OPTION_KEY, array() );
	if ( ! is_array( $existing ) ) {
		$existing = array();
	}
	$defaults = CTC_Settings::defaults();
	update_option( CTC_OPTION_KEY, array_merge( $defaults, $existing ) );
}
register_activation_hook( __FILE__, 'ctc_activate' );
