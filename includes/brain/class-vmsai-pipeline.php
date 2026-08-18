<?php
/**
 * Production Pipeline logic.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages the production flow from scouting to publishing.
 */
class VMSAI_Pipeline {

	/**
	 * Get all items in the pipeline grouped by stage.
	 */
	public static function get_board() {
		try {
			global $wpdb;
			$plan_table  = VMSAI_Install::table( 'plan' );
			$queue_table = VMSAI_Install::table( 'queue' );
			$campaigns   = VMSAI_Install::table( 'campaigns' );

			// Slots belonging to a paused or archived campaign are not in
			// production and should not be shown as though they were.
			$live = "( p.campaign_id = 0 OR c.status = 'active' )";

			// Stage 1: Scouting/Planned (Plan rows not yet in queue)
			$scouting = $wpdb->get_results( // phpcs:ignore
				"SELECT p.id, p.topic, p.channel, p.slot_date as date FROM `$plan_table` p
				 LEFT JOIN `$campaigns` c ON c.id = p.campaign_id
				 WHERE p.status = 'planned' AND p.queue_id = 0 AND $live
				 ORDER BY p.slot_date ASC LIMIT 10", // phpcs:ignore
				ARRAY_A
			);

			// Stage 2: In Production (Plan rows currently being written).
			// updated_at is when composition started, which is what tells the
			// board whether the writer is really working or has died mid-run.
			$production = $wpdb->get_results( // phpcs:ignore
				"SELECT p.id, p.topic, p.channel, p.slot_date as date, p.updated_at,
				        TIMESTAMPDIFF(MINUTE, COALESCE(p.updated_at, p.created_at), UTC_TIMESTAMP()) AS busy_minutes
				 FROM `$plan_table` p
				 LEFT JOIN `$campaigns` c ON c.id = p.campaign_id
				 WHERE p.status = 'processing' AND $live
				 ORDER BY p.updated_at DESC LIMIT 10", // phpcs:ignore
				ARRAY_A
			);

			// Stage 3: Awaiting Audit (Queue rows in draft status)
			$audit = $wpdb->get_results( // phpcs:ignore
				"SELECT id, title, channel, scheduled_at as date, viral_score as score FROM `$queue_table`
				 WHERE status = 'draft'
				 ORDER BY id DESC LIMIT 10", // phpcs:ignore
				ARRAY_A
			);

			// Stage 4: Ready/Scheduled (Queue rows in approved status)
			$ready = $wpdb->get_results( // phpcs:ignore
				"SELECT id, title, channel, scheduled_at as date FROM `$queue_table`
				 WHERE status = 'approved'
				 ORDER BY scheduled_at ASC LIMIT 10", // phpcs:ignore
				ARRAY_A
			);

			// Stage 5: Stalled — everything broken, from both halves of the
			// pipeline. Publishing failures sit in the queue; composition
			// failures never reach it and used to be missing from the board
			// entirely, which hid exactly the work that needed attention.
			$stalled = $wpdb->get_results( // phpcs:ignore
				"SELECT id, title, channel, updated_at as date, last_error as error, 'publish' AS stage FROM `$queue_table`
				 WHERE status = 'failed'
				 ORDER BY updated_at DESC LIMIT 10", // phpcs:ignore
				ARRAY_A
			);

			$stalled_slots = $wpdb->get_results( // phpcs:ignore
				"SELECT p.id, p.topic AS title, p.channel, p.slot_date as date, p.last_error as error, 'compose' AS stage
				 FROM `$plan_table` p
				 LEFT JOIN `$campaigns` c ON c.id = p.campaign_id
				 WHERE p.status = 'failed' AND $live
				 ORDER BY p.slot_date DESC LIMIT 10", // phpcs:ignore
				ARRAY_A
			);

			return array(
				'ok'         => true,
				'error'      => '',
				'scouting'   => $scouting,
				'production' => $production,
				'audit'      => $audit,
				'ready'      => $ready,
				'stalled'    => array_merge( (array) $stalled, (array) $stalled_slots ),
			);
		} catch ( Throwable $e ) {
			VMSAI_Logger::error( 'pipeline', 'Board query failed.', array( 'message' => $e->getMessage() ) );

			return array(
				'ok'         => false,
				/* translators: %s: error message */
				'error'      => sprintf( __( 'The pipeline could not be loaded: %s', 'vm-social-ai-pro' ), $e->getMessage() ),
				'scouting'   => array(),
				'production' => array(),
				'audit'      => array(),
				'ready'      => array(),
				'stalled'    => array(),
			);
		}
	}
}
