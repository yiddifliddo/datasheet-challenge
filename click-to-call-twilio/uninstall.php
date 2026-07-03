<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes the single settings option and any leftover transients so the plugin
 * leaves no trace — it is fully self-contained.
 *
 * @package click-to-call-twilio
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ctc_settings' );

// Best-effort cleanup of our transients (pending confirmations + rate limits).
global $wpdb;
$like = $wpdb->esc_like( '_transient_ctc_' ) . '%';
$like_to = $wpdb->esc_like( '_transient_timeout_ctc_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $like_to ) ); // phpcs:ignore WordPress.DB
