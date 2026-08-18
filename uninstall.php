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

$vmsai_tables = array( 'brain', 'campaigns', 'plan', 'queue', 'metrics', 'models', 'logs' );

foreach ( $vmsai_tables as $vmsai_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'vmsai_' . $vmsai_table . '`' ); // phpcs:ignore
}

foreach ( array( 'vmsai_settings', 'vmsai_credentials', 'vmsai_db_version', 'vmsai_circuit_state', 'vmsai_model_sync_state', 'vmsai_rag_feeds' ) as $vmsai_option ) {
	delete_option( $vmsai_option );
}

delete_transient( 'vmsai_google_access_token' );
delete_transient( 'vmsai_tick_lock' );
delete_transient( 'vmsai_activation_redirect' );

foreach ( array( 'vmsai_cron_tick', 'vmsai_cron_planner', 'vmsai_cron_model_sync', 'vmsai_cron_metrics', 'vmsai_cron_news_sync' ) as $vmsai_hook ) {
	wp_clear_scheduled_hook( $vmsai_hook );
}
