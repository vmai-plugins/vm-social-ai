<?php
/**
 * Uninstall routine.
 *
 * Data is preserved unless the site owner explicitly opts in by defining
 * VMSAI_REMOVE_DATA in wp-config.php. Deleting a campaign's history because
 * someone deactivated a plugin for ten minutes would be unforgivable.
 *
 * @package VM_Social_AI
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'VMSAI_REMOVE_DATA' ) || ! VMSAI_REMOVE_DATA ) {
	return;
}

global $wpdb;

$vmsai_tables = array( 'agents', 'brain', 'campaigns', 'inbox', 'plan', 'queue', 'metrics', 'models', 'usage', 'logs' );

foreach ( $vmsai_tables as $vmsai_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'vmsai_' . $vmsai_table . '`' ); // phpcs:ignore
}

foreach ( array( 'vmsai_settings', 'vmsai_credentials', 'vmsai_db_version', 'vmsai_circuit_state', 'vmsai_model_sync_state', 'vmsai_rag_feeds', 'vmsai_pillar_ranking' ) as $vmsai_option ) {
	delete_option( $vmsai_option );
}

delete_transient( 'vmsai_google_access_token' );
delete_transient( 'vmsai_tick_lock' );
delete_transient( 'vmsai_activation_redirect' );

foreach ( array(
	'vmsai_cron_tick',
	'vmsai_cron_planner',
	'vmsai_cron_model_sync',
	'vmsai_cron_metrics',
	'vmsai_cron_news_sync',
	'vmsai_cron_reflect',
	'vmsai_cron_recycle',
	'vmsai_cron_inbox_sync',
	'vmsai_cron_cleanup',
) as $vmsai_hook ) {
	wp_clear_scheduled_hook( $vmsai_hook );
}

// Sweep any leftover VMSAI transients (pace_*, winner_*, oauth tokens, locks)
// without having to enumerate every dynamic name.
foreach ( array( '_transient_vmsai_', '_transient_timeout_vmsai_', '_site_transient_vmsai_', '_site_transient_timeout_vmsai_' ) as $vmsai_like ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $vmsai_like ) . '%' ) ); // phpcs:ignore
}
