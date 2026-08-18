<?php
/**
 * Agent Feed — schedules posts from the Agent roster.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a dedicated campaign ('agents' objective, kept separate from the
 * evergreen Plan's 'reach' campaign and Campaign Planner's 'campaign' runs)
 * topped up with plan slots, picking an agent each time by its configured
 * weight and routing it only to channels it's allowed to post to.
 */
class VMSAI_Agent_Feed {

	/**
	 * The current agent-feed campaign row, if one exists.
	 *
	 * @return array|null
	 */
	public static function feed_campaign() {
		global $wpdb;
		$table = VMSAI_Install::table( 'campaigns' );

		return $wpdb->get_row( "SELECT * FROM `$table` WHERE objective = 'agents' AND status = 'active' ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore
	}

	/**
	 * Find or create the agent-feed campaign.
	 *
	 * @return int Campaign id.
	 */
	public static function ensure_feed_campaign() {
		$existing = self::feed_campaign();

		if ( $existing ) {
			return (int) $existing['id'];
		}

		global $wpdb;

		$wpdb->insert( // phpcs:ignore
			VMSAI_Install::table( 'campaigns' ),
			array(
				'name'         => __( 'Agent Feed', 'vm-social-ai' ),
				'status'       => 'active',
				'objective'    => 'agents',
				'target_views' => 0,
				'horizon_days' => 3650, // Ongoing, not a fixed-horizon run.
				'channels'     => wp_json_encode( vmsai()->channels()->ready() ),
				'pillars'      => wp_json_encode( array() ),
				'starts_on'    => current_time( 'Y-m-d' ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Top the feed up with new plan slots if it's running low. Safe to call
	 * repeatedly — a no-op once the floor is met.
	 *
	 * @param int $floor Minimum planned slots to keep in the pipeline.
	 * @return int Slots created.
	 */
	public static function top_up( $floor = 6 ) {
		if ( ! VMSAI_Brain::is_ready() ) {
			return 0;
		}

		$agents = VMSAI_Agents::all( true );

		if ( ! $agents ) {
			return 0;
		}

		$ready_channels = vmsai()->channels()->ready();

		if ( ! $ready_channels ) {
			return 0;
		}

		$campaign_id = self::ensure_feed_campaign();

		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );

		$remaining = (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$table` WHERE campaign_id = %d AND status = 'planned' AND slot_date >= %s", // phpcs:ignore
				$campaign_id,
				current_time( 'Y-m-d' )
			)
		);

		if ( $remaining >= $floor ) {
			return 0;
		}

		$needed     = $floor - $remaining;
		$start      = self::next_open_date( $campaign_id );
		$day_offset = 0;
		$now        = current_time( 'mysql', true );
		$created    = 0;
		$attempts   = 0;
		$max_attempts = $needed * 4 + 10; // Safety valve against a roster where no agent has a usable channel.

		while ( $created < $needed && $attempts < $max_attempts ) {
			$attempts++;
			$agent = self::pick_weighted( $agents );

			if ( ! $agent ) {
				break;
			}

			$allowed    = VMSAI_Agents::allowed_channels( $agent );
			$candidates = $allowed ? array_values( array_intersect( $allowed, $ready_channels ) ) : $ready_channels;

			if ( ! $candidates ) {
				continue; // This agent has no usable channel right now — try a different pick.
			}

			$channel   = $candidates[ array_rand( $candidates ) ];
			$date      = gmdate( 'Y-m-d', strtotime( $start . ' +' . $day_offset . ' day' ) );
			$best_hour = (int) VMSAI_Analytics::get_optimal_hour( $channel );
			$jitter    = wp_rand( -10, 10 );
			$seconds   = strtotime( '2000-01-01 ' . sprintf( '%02d:00', $best_hour ) . ':00' ) + ( $jitter * 60 );
			$time      = gmdate( 'H:i:s', $seconds );

			// WORLD CLASS POLISH: Inject fresh Industry News if picking the Trend Watcher.
			$brief = (string) $agent['brief'];
			$slug  = preg_replace( '/^the-/', '', sanitize_title( (string) $agent['name'] ) );

			if ( 'trend-watcher' === $slug || 'news-bot' === $slug ) {
				$news = VMSAI_Brain::get( 'recent_industry_news', array(), 'context' );
				if ( ! empty($news) && is_array($news) ) {
					$news_item = $news[ array_rand($news) ];
					$brief = "BREAKING NEWS CONTEXT: " . $news_item . "\n\nTASK: " . $brief;
				}
			}

			$wpdb->insert( // phpcs:ignore
				$table,
				array(
					'campaign_id' => $campaign_id,
					'agent_id'    => (int) $agent['id'],
					'slot_date'   => $date,
					'slot_time'   => $time,
					'channel'     => $channel,
					'pillar'      => 'agent',
					'format'      => (string) $agent['image_style'],
					'topic'       => $agent['name'] . ' — ' . wp_trim_words( $brief, 8 ),
					'keyword'     => '',
					'angle'       => $brief,
					'status'      => 'planned',
					'created_at'  => $now,
				)
			);

			$created++;
			$day_offset++;
		}

		if ( $created ) {
			VMSAI_Logger::info( 'agent-feed', sprintf( 'Queued %d agent-sourced slot(s).', $created ) );
		}

		return $created;
	}

	/**
	 * Weighted-random pick from the active roster.
	 *
	 * @param array $agents Active agent rows.
	 * @return array|null
	 */
	private static function pick_weighted( array $agents ) {
		if ( ! $agents ) {
			return null;
		}

		$total = array_sum( array_map( fn( $a ) => (int) $a['weight'], $agents ) );

		if ( $total <= 0 ) {
			return $agents[ array_rand( $agents ) ];
		}

		$roll   = wp_rand( 1, $total );
		$cursor = 0;

		foreach ( $agents as $agent ) {
			$cursor += (int) $agent['weight'];
			if ( $roll <= $cursor ) {
				return $agent;
			}
		}

		return end( $agents );
	}

	/**
	 * Start filling from the day after the last planned slot.
	 *
	 * @param int $campaign_id Campaign id.
	 * @return string
	 */
	private static function next_open_date( $campaign_id ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );

		$last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(slot_date) FROM `$table` WHERE campaign_id = %d", $campaign_id ) ); // phpcs:ignore

		if ( $last && $last >= current_time( 'Y-m-d' ) ) {
			return gmdate( 'Y-m-d', strtotime( $last . ' +1 day' ) );
		}

		return current_time( 'Y-m-d' );
	}
}
