<?php
/**
 * Analytics and pacing.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pulls performance numbers back from each network and reports whether the
 * campaign is actually on pace for its target.
 */
class VMSAI_Analytics {

	/**
	 * Refresh metrics for recently published posts.
	 *
	 * @return int Rows updated.
	 */
	public static function collect() {
		global $wpdb;
		$queue = VMSAI_Install::table( 'queue' );

		// Posts keep accruing views for a while, so a rolling window is
		// re-polled rather than only the newest ones.
		$rows = (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM `$queue` WHERE status = 'published' AND remote_id <> ''
				 AND published_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
				 ORDER BY published_at DESC LIMIT %d", // phpcs:ignore
				(int) apply_filters( 'vmsai_metrics_batch', 20 )
			),
			ARRAY_A
		);

		$manager = vmsai()->channels();
		$updated = 0;
		$today   = current_time( 'Y-m-d' );
		$start   = microtime( true );

		foreach ( $rows as $row ) {
			// Safety: Don't hog the cron process for more than 40 seconds.
			if ( ( microtime( true ) - $start ) > 40 ) {
				VMSAI_Logger::info( 'analytics', 'Metrics fetch paused to prevent timeout.' );
				break;
			}

			$channel = $manager->get( $row['channel'] );

			if ( ! $channel || ! $channel->is_connected() ) {
				continue;
			}

			$metrics = $channel->fetch_metrics( $row );

			if ( array_sum( $metrics ) <= 0 ) {
				continue;
			}

			$wpdb->query( // phpcs:ignore
				$wpdb->prepare(
					'INSERT INTO `' . VMSAI_Install::table( 'metrics' ) . '`
					 (queue_id, campaign_id, channel, captured_on, impressions, reach, engagements, clicks)
					 VALUES (%d, %d, %s, %s, %d, %d, %d, %d)
					 ON DUPLICATE KEY UPDATE impressions = VALUES(impressions), reach = VALUES(reach),
					 engagements = VALUES(engagements), clicks = VALUES(clicks)', // phpcs:ignore
					(int) $row['id'],
					(int) $row['campaign_id'],
					$row['channel'],
					$today,
					(int) $metrics['impressions'],
					(int) $metrics['reach'],
					(int) $metrics['engagements'],
					(int) $metrics['clicks']
				)
			);

			$updated++;
		}

		VMSAI_Logger::info( 'analytics', sprintf( 'Refreshed metrics for %d posts.', $updated ) );

		// Clear pace transients so stats reflect new data.
		global $wpdb;
		$campaigns = $wpdb->get_col( "SELECT id FROM " . VMSAI_Install::table('campaigns') );
		foreach ( (array) $campaigns as $cid ) {
			delete_transient( 'vmsai_pace_' . $cid );
		}

		// OPTIMAL WINDOW REBALANCING: Adjust upcoming posts to best times.
		self::rebalance_schedule();

		// SELF-LEARNING LOOP: Adjust content strategy based on performance.
		self::double_down_on_winners();

		self::prune();
		VMSAI_Logger::prune();

		return $updated;
	}

	/**
	 * Analyze which content pillars are performing best across all channels.
	 *
	 * @param int $days Window.
	 * @return array<string,float> Pillar => Engagement Rate.
	 */
	public static function pillar_performance( $days = 60 ) {
		global $wpdb;
		$metrics = VMSAI_Install::table( 'metrics' );
		$queue   = VMSAI_Install::table( 'queue' );
		$plan    = VMSAI_Install::table( 'plan' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.pillar, AVG(m.engagements) as avg_eng
			 FROM `$queue` q
			 INNER JOIN `$metrics` m ON m.queue_id = q.id
			 LEFT JOIN `$plan` p ON p.id = q.plan_id
			 WHERE q.status = 'published'
			 AND q.published_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
			 AND p.pillar IS NOT NULL
			 GROUP BY p.pillar
			 ORDER BY avg_eng DESC",
			(int) $days
		), ARRAY_A );

		$out = array();
		foreach ( $rows as $row ) {
			$out[ $row['pillar'] ] = (float) $row['avg_eng'];
		}

		return $out;
	}

	/**
	 * Adjust the Strategic Pillar Mix based on what is actually working.
	 */
	public static function double_down_on_winners() {
		$performance = self::pillar_performance( 60 );
		if ( count($performance) < 2 ) return;

		$best_pillar = array_key_first( $performance );
		$worst_pillar = array_key_last( $performance );

		VMSAI_Logger::info( 'analytics', "Self-Learning Loop: Best pillar is '{$best_pillar}', Worst is '{$worst_pillar}'. Adjusting strategy..." );

		// Logic to update the Planner's default weights?
		// Actually, let's update a transient that VMSAI_Planner reads.
		set_transient( 'vmsai_winner_pillar', $best_pillar, DAY_IN_SECONDS * 7 );
		set_transient( 'vmsai_loser_pillar', $worst_pillar, DAY_IN_SECONDS * 7 );
	}

	/**
	 * Maintenance: Delete old metrics to keep the DB lean.
	 */
	public static function prune() {
		global $wpdb;
		$table = VMSAI_Install::table( 'metrics' );
		$days  = max( 30, (int) VMSAI_Settings::get( 'retention_days', 120 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$table` WHERE captured_on < DATE_SUB(CURDATE(), INTERVAL %d DAY)", $days ) ); // phpcs:ignore
	}

	/**
	 * Adjust upcoming approved posts to the optimal viral window.
	 */
	public static function rebalance_schedule() {
		global $wpdb;
		$queue = VMSAI_Install::table( 'queue' );

		$channels = (array) VMSAI_Settings::get( 'enabled_channels', array() );
		$enabled  = array_keys( array_filter( $channels ) );
		$quiet    = (array) VMSAI_Settings::get( 'quiet_hours', array( '01:00', '06:00' ) );

		foreach ( $enabled as $channel ) {
			$best_hour = self::get_optimal_hour( $channel );

			// SAFETY: If optimal hour is in quiet window, move it to 1 hour after quiet window ends.
			$q_start = (int) str_replace( ':', '', $quiet[0] ?? '01:00' );
			$q_end   = (int) str_replace( ':', '', $quiet[1] ?? '06:00' );
			$target_time = $best_hour * 100;

			$is_quiet = false;
			if ( $q_start > $q_end ) {
				$is_quiet = ( $target_time >= $q_start || $target_time < $q_end );
			} else {
				$is_quiet = ( $target_time >= $q_start && $target_time < $q_end );
			}

			if ( $is_quiet ) {
				$best_hour = ( intval(substr($quiet[1], 0, 2)) + 1 ) % 24;
			}

			// Move posts scheduled in the next 7 days.
			// Pro Fix: Handle multiple posts per day by spreading them out starting from the best hour.
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, DATE(scheduled_at) as day_date FROM `$queue`
				 WHERE channel = %s
				 AND status = 'approved'
				 AND parent_id = 0
				 AND TIMESTAMPDIFF(SECOND, created_at, updated_at) < 5
				 AND scheduled_at > %s
				 ORDER BY day_date ASC, id ASC",
				$channel,
				current_time( 'mysql', true )
			), ARRAY_A );

			if ( ! $rows ) continue;

			$day_counts = array();
			foreach ( $rows as $row ) {
				$day = $row['day_date'];
				$index = $day_counts[ $day ] ?? 0;
				$day_counts[ $day ] = $index + 1;

				// Offset subsequent posts on the same day by 4 hours to avoid squashing.
				$hour = ( $best_hour + ( $index * 4 ) ) % 24;
				$time = sprintf( '%02d:00:00', $hour );

				$wpdb->update( $queue, array( 'scheduled_at' => $day . ' ' . $time ), array( 'id' => $row['id'] ) );
			}
		}
	}

	/**
	 * Campaign totals and pace against the target.
	 *
	 * @param int $campaign_id Campaign id.
	 * @return array
	 */
	public static function pace( $campaign_id ) {
		$cache_key = 'vmsai_pace_' . $campaign_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$metrics   = VMSAI_Install::table( 'metrics' );
		$queue     = VMSAI_Install::table( 'queue' );
		$campaign  = VMSAI_Planner::campaign( $campaign_id );

		if ( ! $campaign ) {
			return array();
		}

		// Each post's peak snapshot is its contribution; snapshots are
		// cumulative per post, so summing the latest per post avoids
		// double counting across days.
		$totals = $wpdb->get_row( // phpcs:ignore
			$wpdb->prepare(
				"SELECT SUM(peak_views) AS views, SUM(peak_eng) AS engagements, SUM(peak_clicks) AS clicks
				 FROM (SELECT MAX(impressions) AS peak_views, MAX(engagements) AS peak_eng, MAX(clicks) AS peak_clicks
				       FROM `$metrics` WHERE campaign_id = %d GROUP BY queue_id) AS per_post", // phpcs:ignore
				(int) $campaign_id
			),
			ARRAY_A
		);

		$views     = (int) ( $totals['views'] ?? 0 );
		$target    = (int) $campaign['target_views'];
		$horizon   = max( 1, (int) $campaign['horizon_days'] );
		$elapsed   = max( 1, min( $horizon, (int) floor( ( time() - strtotime( (string) $campaign['starts_on'] ) ) / DAY_IN_SECONDS ) + 1 ) );
		$expected  = (int) round( $target * ( $elapsed / $horizon ) );
		$run_rate  = (int) round( $views / $elapsed );
		$projected = $run_rate * $horizon;

		$published = (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare( "SELECT COUNT(*) FROM `$queue` WHERE campaign_id = %d AND status = 'published'", (int) $campaign_id ) // phpcs:ignore
		);

		$result = array(
			'campaign'      => $campaign,
			'views'         => $views,
			'engagements'   => (int) ( $totals['engagements'] ?? 0 ),
			'clicks'        => (int) ( $totals['clicks'] ?? 0 ),
			'published'     => $published,
			'target'        => $target,
			'day'           => $elapsed,
			'horizon'       => $horizon,
			'expected'      => $expected,
			'run_rate'      => $run_rate,
			'projected'     => $projected,
			'on_pace'       => $views >= $expected,
			'progress'      => $target > 0 ? min( 100, (int) round( ( $views / $target ) * 100 ) ) : 0,
			'avg_per_post'  => $published > 0 ? (int) round( $views / $published ) : 0,
		);

		set_transient( 'vmsai_pace_' . $campaign_id, $result, HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Daily view series for the trend chart.
	 *
	 * @param int $campaign_id Campaign id.
	 * @param int $days        Window.
	 * @return array
	 */
	public static function series( $campaign_id, $days = 30 ) {
		global $wpdb;
		$metrics = VMSAI_Install::table( 'metrics' );

		$rows = (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT captured_on, SUM(impressions) AS views, SUM(engagements) AS engagements
				 FROM `$metrics` WHERE campaign_id = %d AND captured_on >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
				 GROUP BY captured_on ORDER BY captured_on ASC", // phpcs:ignore
				(int) $campaign_id,
				(int) $days
			),
			ARRAY_A
		);

		return $rows;
	}

	/**
	 * Per-channel totals for the breakdown table.
	 *
	 * @param int $campaign_id Campaign id.
	 * @return array
	 */
	public static function by_channel( $campaign_id ) {
		global $wpdb;
		$metrics = VMSAI_Install::table( 'metrics' );

		return (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT channel, SUM(peak_views) AS views, SUM(peak_eng) AS engagements, COUNT(*) AS posts
				 FROM (SELECT queue_id, channel, MAX(impressions) AS peak_views, MAX(engagements) AS peak_eng
				       FROM `$metrics` WHERE campaign_id = %d GROUP BY queue_id) AS per_post
				 GROUP BY channel ORDER BY views DESC", // phpcs:ignore
				(int) $campaign_id
			),
			ARRAY_A
		);
	}

	/**
	 * Counts by queue status, for the dashboard tiles.
	 *
	 * @return array
	 */
	public static function queue_counts() {
		global $wpdb;
		$queue_table = VMSAI_Install::table( 'queue' );
		$plan_table  = VMSAI_Install::table( 'plan' );

		$q_rows = (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM `$queue_table` GROUP BY status", ARRAY_A ); // phpcs:ignore

		// Composition failures live in the plan table, publishing failures in
		// the queue. The Failed tab lists both, so the badge counts both —
		// these two numbers have to be derived from the same rule or the tab
		// shows a different total to the one on it.
		$p_fail = VMSAI_Planner::failed_slot_count();

		$out = array( 'draft' => 0, 'approved' => 0, 'published' => 0, 'failed' => $p_fail );

		foreach ( $q_rows as $row ) {
			if ( 'failed' === $row['status'] ) {
				$out['failed'] += (int) $row['n'];
			} else {
				$out[ $row['status'] ] = (int) $row['n'];
			}
		}

		return $out;
	}

	/**
	 * Analyze historical engagement to find the optimal UTC hour for a platform.
	 */
	public static function get_optimal_hour( $channel ) {
		global $wpdb;
		$metrics = VMSAI_Install::table( 'metrics' );
		$queue   = VMSAI_Install::table( 'queue' );

		// Get engagement data by hour from the last 90 days.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT HOUR(q.published_at) as hr, AVG(m.engagements) as avg_eng
			 FROM `$queue` q INNER JOIN `$metrics` m ON q.id = m.queue_id
			 WHERE q.channel = %s AND q.status = 'published' AND q.published_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)
			 GROUP BY hr ORDER BY avg_eng DESC LIMIT 1",
			$channel
		), ARRAY_A );

		if ( ! $rows ) {
			// Fallback to platform-standard golden hours.
			$golden = VMSAI_Planner::golden_hours();
			$slots  = $golden[ $channel ] ?? array( '10:00' );
			$time   = $slots[ array_rand( $slots ) ];
			return (int) substr( $time, 0, 2 );
		}

		return (int) $rows[0]['hr'];
	}

	/**
	 * Determine which variant is winning on a specific platform.
	 * Returns 'a', 'b', or 'balanced'.
	 */
	public static function get_winning_variant( $channel ) {
		global $wpdb;
		$metrics = VMSAI_Install::table( 'metrics' );
		$queue   = VMSAI_Install::table( 'queue' );

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				SUM(CASE WHEN q.variant = 'a' THEN m.engagements ELSE 0 END) as eng_a,
				SUM(CASE WHEN q.variant = 'b' THEN m.engagements ELSE 0 END) as eng_b
			 FROM `$queue` q INNER JOIN `$metrics` m ON q.id = m.queue_id
			 WHERE q.channel = %s AND q.status = 'published' AND q.published_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)",
			$channel
		) );

		if ( ! $stats || ( $stats->eng_a == 0 && $stats->eng_b == 0 ) ) {
			return 'balanced';
		}

		if ( $stats->eng_b > ( $stats->eng_a * 1.2 ) ) {
			return 'b'; // 20% lead for B.
		}
		if ( $stats->eng_a > ( $stats->eng_b * 1.2 ) ) {
			return 'a'; // 20% lead for A.
		}

		return 'balanced';
	}

	/**
	 * Approved posts due to go out soon, for the dashboard's "what's coming"
	 * widget.
	 *
	 * @param int $hours Look-ahead window.
	 * @param int $limit Row count.
	 * @return array
	 */
	public static function upcoming( $hours = 48, $limit = 12 ) {
		global $wpdb;
		$queue = VMSAI_Install::table( 'queue' );

		return (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT id, channel, title, scheduled_at FROM `$queue`
				 WHERE status = 'approved' AND scheduled_at BETWEEN %s AND %s
				 ORDER BY scheduled_at ASC LIMIT %d", // phpcs:ignore
				current_time( 'mysql', true ),
				gmdate( 'Y-m-d H:i:s', time() + $hours * HOUR_IN_SECONDS ),
				(int) $limit
			),
			ARRAY_A
		);
	}

	/**
	 * Most recent published/failed posts, with the failure reason inline —
	 * a dashboard glance shouldn't require a trip to the Logs tab.
	 *
	 * @param int $limit Row count.
	 * @return array
	 */
	public static function recent_activity( $limit = 10 ) {
		global $wpdb;
		$queue = VMSAI_Install::table( 'queue' );

		return (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT id, channel, title, status, last_error, permalink, published_at, created_at FROM `$queue`
				 WHERE status IN ('published', 'failed')
				 ORDER BY GREATEST( COALESCE(published_at, '1970-01-01'), COALESCE(updated_at, created_at) ) DESC
				 LIMIT %d", // phpcs:ignore
				(int) $limit
			),
			ARRAY_A
		);
	}

	/**
	 * Peak recorded figures for a set of posts, keyed by queue id.
	 *
	 * Metrics arrive as cumulative daily snapshots, so the highest row per
	 * post is the real number — summing the snapshots would multiply it by
	 * the number of days the post has been observed.
	 *
	 * @param array $queue_ids Queue row ids.
	 * @return array<int,array{impressions:int,reach:int,engagements:int,clicks:int,rate:float}>
	 */
	public static function metrics_for( array $queue_ids ) {
		$queue_ids = array_values( array_unique( array_filter( array_map( 'intval', $queue_ids ) ) ) );

		if ( ! $queue_ids ) {
			return array();
		}

		global $wpdb;
		$metrics      = VMSAI_Install::table( 'metrics' );
		$placeholders = implode( ',', array_fill( 0, count( $queue_ids ), '%d' ) );

		$rows = (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT queue_id,
				        MAX(impressions) AS impressions,
				        MAX(reach) AS reach,
				        MAX(engagements) AS engagements,
				        MAX(clicks) AS clicks
				 FROM `$metrics`
				 WHERE queue_id IN ($placeholders)
				 GROUP BY queue_id", // phpcs:ignore
				$queue_ids
			),
			ARRAY_A
		);

		$out = array();

		foreach ( $rows as $row ) {
			$impressions = (int) $row['impressions'];
			$engagements = (int) $row['engagements'];

			$out[ (int) $row['queue_id'] ] = array(
				'impressions' => $impressions,
				'reach'       => (int) $row['reach'],
				'engagements' => $engagements,
				'clicks'      => (int) $row['clicks'],
				'rate'        => $impressions > 0 ? round( ( $engagements / $impressions ) * 100, 1 ) : 0.0,
			);
		}

		return $out;
	}

	/**
	 * Consecutive weeks that carried at least one published post.
	 *
	 * An autonomous plugin can fail silently — every provider trips its
	 * breaker, cron stops firing, and the dashboard still looks calm because
	 * every number it shows is a lifetime total. A streak is the one figure
	 * that visibly breaks the moment publishing stops.
	 *
	 * The current week is only counted once it has a post, but its absence
	 * does not end the streak: an unfinished week is not a missed one.
	 *
	 * @return array{weeks:int,posted_today:bool,posts_this_week:int,last_published:string}
	 */
	public static function streak() {
		$cached = get_transient( 'vmsai_streak' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$queue = VMSAI_Install::table( 'queue' );

		$dates = (array) $wpdb->get_col( // phpcs:ignore
			"SELECT DISTINCT DATE(published_at) FROM `$queue`
			 WHERE status = 'published' AND published_at IS NOT NULL
			 AND published_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 400 DAY)" // phpcs:ignore
		);

		// Bucket by ISO year-week so the walk back is safe across year
		// boundaries, where a naive week number wraps from 01 to 52.
		$weeks = array();
		foreach ( $dates as $date ) {
			$stamp = strtotime( (string) $date );
			if ( $stamp ) {
				$weeks[ gmdate( 'oW', $stamp ) ] = true;
			}
		}

		$cursor = time();

		if ( ! isset( $weeks[ gmdate( 'oW', $cursor ) ] ) ) {
			$cursor -= WEEK_IN_SECONDS;
		}

		$count = 0;
		while ( isset( $weeks[ gmdate( 'oW', $cursor ) ] ) && $count < 520 ) {
			$count++;
			$cursor -= WEEK_IN_SECONDS;
		}

		$today = gmdate( 'Y-m-d' );

		$posts_this_week = (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$queue` WHERE status = 'published' AND YEARWEEK(published_at, 3) = YEARWEEK(%s, 3)", // phpcs:ignore
				$today
			)
		);

		$out = array(
			'weeks'           => $count,
			'posted_today'    => in_array( $today, array_map( 'strval', $dates ), true ),
			'posts_this_week' => $posts_this_week,
			'last_published'  => (string) $wpdb->get_var( "SELECT MAX(published_at) FROM `$queue` WHERE status = 'published'" ), // phpcs:ignore
		);

		// Rolling weekly figures do not change between page loads, but they
		// cost three queries to derive. Publishing clears this immediately, so
		// the streak still reacts the moment something goes out.
		set_transient( 'vmsai_streak', $out, 15 * MINUTE_IN_SECONDS );

		return $out;
	}

	/**
	 * This week against the week before it.
	 *
	 * Lifetime totals never say whether things are getting better; the
	 * week-over-week delta does. Metrics are stored as cumulative daily
	 * snapshots (one row per post per day), so reach and engagement have to
	 * be taken as the peak per post and then summed — adding the snapshots
	 * up would count the same impressions once for every day they were
	 * observed.
	 *
	 * @return array<string,array{current:int,previous:int,delta:int|null}>
	 */
	public static function weekly_pulse() {
		$now  = time();
		$this_from = gmdate( 'Y-m-d 00:00:00', $now - ( 6 * DAY_IN_SECONDS ) );
		$this_to   = gmdate( 'Y-m-d 23:59:59', $now );
		$prev_from = gmdate( 'Y-m-d 00:00:00', $now - ( 13 * DAY_IN_SECONDS ) );
		$prev_to   = gmdate( 'Y-m-d 23:59:59', $now - ( 7 * DAY_IN_SECONDS ) );

		return array(
			'posts'       => self::compare( self::posts_published( $this_from, $this_to ), self::posts_published( $prev_from, $prev_to ) ),
			'reach'       => self::compare( self::peak_metric( 'reach', $this_from, $this_to ), self::peak_metric( 'reach', $prev_from, $prev_to ) ),
			'engagements' => self::compare( self::peak_metric( 'engagements', $this_from, $this_to ), self::peak_metric( 'engagements', $prev_from, $prev_to ) ),
			'followers'   => self::compare( self::followers_on( $this_to ), self::followers_on( $prev_to ) ),
		);
	}

	/**
	 * Package two numbers with the percentage change between them.
	 *
	 * @param int $current  This period.
	 * @param int $previous Previous period.
	 * @return array{current:int,previous:int,delta:int|null}
	 */
	private static function compare( $current, $previous ) {
		$delta = null;

		if ( $previous > 0 ) {
			$delta = (int) round( ( ( $current - $previous ) / $previous ) * 100 );
		} elseif ( 0 === (int) $current ) {
			$delta = 0;
		}

		// A jump from zero has no meaningful percentage — the view shows
		// "new" for a null delta rather than a misleading infinity.
		return array( 'current' => (int) $current, 'previous' => (int) $previous, 'delta' => $delta );
	}

	/**
	 * Posts published inside a window.
	 *
	 * @param string $from UTC datetime.
	 * @param string $to   UTC datetime.
	 * @return int
	 */
	private static function posts_published( $from, $to ) {
		global $wpdb;
		$queue = VMSAI_Install::table( 'queue' );

		return (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$queue` WHERE status = 'published' AND published_at BETWEEN %s AND %s", // phpcs:ignore
				$from,
				$to
			)
		);
	}

	/**
	 * Sum of each post's best recorded figure, for posts published in the
	 * window. Peak-then-sum, never sum-of-snapshots.
	 *
	 * @param string $column One of reach, impressions, engagements, clicks.
	 * @param string $from   UTC datetime.
	 * @param string $to     UTC datetime.
	 * @return int
	 */
	private static function peak_metric( $column, $from, $to ) {
		global $wpdb;

		if ( ! in_array( $column, array( 'reach', 'impressions', 'engagements', 'clicks' ), true ) ) {
			return 0;
		}

		$metrics = VMSAI_Install::table( 'metrics' );
		$queue   = VMSAI_Install::table( 'queue' );

		return (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT COALESCE(SUM(peak), 0) FROM (
					SELECT MAX(m.`$column`) AS peak
					FROM `$queue` q
					INNER JOIN `$metrics` m ON m.queue_id = q.id
					WHERE q.status = 'published' AND q.published_at BETWEEN %s AND %s
					GROUP BY q.id
				) t", // phpcs:ignore
				$from,
				$to
			)
		);
	}

	/**
	 * Total audience as of a given day: the most recent follower count on or
	 * before that date for each channel, added together. Snapshots repeat the
	 * same follower number on every post row for that day, so it has to be
	 * collapsed per channel before summing.
	 *
	 * @param string $as_of UTC datetime.
	 * @return int
	 */
	private static function followers_on( $as_of ) {
		global $wpdb;
		$metrics = VMSAI_Install::table( 'metrics' );

		return (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT COALESCE(SUM(f), 0) FROM (
					SELECT MAX(m.followers) AS f
					FROM `$metrics` m
					INNER JOIN (
						SELECT channel, MAX(captured_on) AS d
						FROM `$metrics`
						WHERE captured_on <= DATE(%s) AND followers > 0
						GROUP BY channel
					) latest ON latest.channel = m.channel AND latest.d = m.captured_on
					GROUP BY m.channel
				) t", // phpcs:ignore
				$as_of
			)
		);
	}

	/**
	 * Published posts worth resurfacing with a fresh take — the highest
	 * engagement per post, joined back to the plan row that spawned them so
	 * the topic/keyword/pillar can seed a new slot.
	 *
	 * @param int $limit Row count.
	 * @param int $days  How far back to look.
	 * @return array
	 */
	public static function top_performers_for_recycling( $limit = 3, $days = 90 ) {
		global $wpdb;
		$metrics = VMSAI_Install::table( 'metrics' );
		$queue   = VMSAI_Install::table( 'queue' );
		$plan    = VMSAI_Install::table( 'plan' );

		return (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT q.id AS queue_id, q.channel, q.title, q.campaign_id,
				        p.pillar, p.keyword, p.topic, MAX(m.engagements) AS peak_eng
				 FROM `$queue` q
				 INNER JOIN `$metrics` m ON m.queue_id = q.id
				 LEFT JOIN `$plan` p ON p.id = q.plan_id
				 WHERE q.status = 'published' AND q.published_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				 GROUP BY q.id
				 ORDER BY peak_eng DESC
				 LIMIT %d", // phpcs:ignore
				(int) $days,
				(int) $limit
			),
			ARRAY_A
		);
	}

	/**
	 * Analyze top performers and update the Brain's tone.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public static function reflect() {
		$winners = VMSAI_Brain::top_performers( 10 );

		if ( ! $winners ) {
			return array( 'ok' => false, 'message' => __( 'Not enough performance data yet.', 'vm-social-ai-pro' ) );
		}

		$current_tone = VMSAI_Brain::get( 'tone' );

		$system = 'You are a brand growth strategist. You analyze top performing social media posts to refine a brand voice.';
		$prompt = "CURRENT TONE: " . $current_tone . "\n\n"
			. "TOP PERFORMING POSTS:\n" . implode( "\n", $winners ) . "\n\n"
			. "Task: Describe the 'winning' voice of these posts in 3 concise sentences. "
			. "Focus on what specifically worked (humor, urgency, education?). "
			. "Return ONLY the 3 sentences. No preamble.";

		$result = vmsai()->text_engine()->generate( $system, $prompt, array( 'temperature' => 0.4 ) );

		if ( empty( $result['ok'] ) ) {
			return array( 'ok' => false, 'message' => $result['error'] );
		}

		VMSAI_Brain::set( array( 'tone' => $result['text'] ) );
		VMSAI_Logger::info( 'analytics', 'Brain reflected on performance and updated the brand tone.' );

		// VOICE LAB ANALYSIS: If user provided examples in the Brain, analyze the stylistic DNA.
		$voice_examples = VMSAI_Brain::get( 'voice_lab' );
		$current_dna    = VMSAI_Brain::get( 'voice_dna', '', 'context' );

		if ( $voice_examples ) {
			$voice_system = 'You are a Linguist and Brand Voice Architect. Your task is to extract the "Stylistic DNA" of a brand to ensure perfect AI mimicry.';
			$voice_prompt = "ACTUAL BRAND EXAMPLES:\n{$voice_examples}\n\n"
				. ( $winners ? "TOP PERFORMING RECENT POSTS:\n" . implode( "\n", $winners ) . "\n\n" : "" )
				. ( $current_dna ? "CURRENT DNA (Refine this):\n{$current_dna}\n\n" : "" )
				. "Task: Analyze the rhythmic and structural patterns (sentence length, emoji placement, vocabulary choice, use of questions, etc.). "
				. "CRITICAL: If the examples use a mix of languages (e.g. Hinglish, Spanglish) or specific local slang, ensure the DNA instruction explicitly directs the AI to maintain that exact linguistic blend and ratio.\n"
				. "Output a 2-sentence 'Stylistic DNA' instruction that tells an AI exactly how to mimic this rhythm.\n"
				. "Be extremely technical about the structure (e.g. 'Uses short, punchy opening statements followed by 3 bullet points with a specific emoji style').";

			$voice_res = vmsai()->text_engine()->generate( $voice_system, $voice_prompt, array( 'temperature' => 0.3 ) );
			if ( ! empty( $voice_res['ok'] ) ) {
				VMSAI_Brain::set( array( 'voice_dna' => $voice_res['text'] ), 'context' );
				VMSAI_Logger::info( 'analytics', 'Voice Lab analyzed and DNA updated recursively.' );
			}
		}

		return array( 'ok' => true, 'message' => __( 'Brand voice refined based on top performers and Voice Lab.', 'vm-social-ai-pro' ) );
	}
}
