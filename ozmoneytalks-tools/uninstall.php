<?php
/**
 * Runs when the plugin is deleted from Plugins → Installed Plugins.
 * Removes settings, cached rates, the helper's answers and question log, and the subscriber table.
 * Export your subscribers (Settings → OzMoneyTalks Tools) before deleting.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oz_subscribers" ); // phpcs:ignore
delete_option( 'oz_tools_settings' );
delete_option( 'oz_tools_providers' );
delete_option( 'oz_tools_db_version' );
delete_option( 'oz_tools_rate_last_good' );
delete_option( 'oz_tools_chat_answers' );
delete_option( 'oz_tools_chat_log' );
delete_transient( 'oz_tools_rate_cache' );
wp_clear_scheduled_hook( 'oz_tools_daily' );
wp_clear_scheduled_hook( 'oz_tools_digest_batch' );
