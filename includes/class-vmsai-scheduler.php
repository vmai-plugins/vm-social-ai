<?php
/**
 * Autonomous scheduler.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * The loop that makes the plugin autonomous: it keeps the plan topped up,
 * composes ahead of each slot, and dispatches whatever is due.
 */
class VMSAI_Scheduler {

	const LOCK = 'vmsai_tick_lock';

	/**
	 * Wire up cron hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( VMSAI_Install::CRON_TICK, array( $this, 'tick' ) );
		add_action( VMSAI_Install::CRON_PLANNER, array( $this, 'top_up_plan' ) );
		add_action( VMSAI_Install::CRON_MODELS, array( 'VMSAI_Model_Sync', 'run' ) );
		add_action( VMSAI_Install::CRON_METRICS, array( 'VMSAI_Analytics', 'collect' ) );
		add_action( VMSAI_Install::CRON_REFLECT, array( 'VMSAI_Analytics', 'reflect' ) );
		add_action( VMSAI_Install::CRON_NEWS, array( $this, 'sync_news' ) );
		add_action( VMSAI_Install::CRON_RECYCLE, array( $this, 'recycle_top_performers' ) );
		add_action( VMSAI_Install::CRON_RECYCLE, array( $this, 'recycle_evergreen' ) );
		add_action( VMSAI_Install::CRON_INBOX, array( $this, 'sync_inbox' ) );
		add_action( VMSAI_Install::CRON_CLEANUP, array( $this, 'cleanup_assets' ) );
		add_action( VMSAI_Install::CRON_PLANNER, array( 'VMSAI_Agent_Feed', 'top_up' ) );
	}

	/**
	 * Run Inbox sync in the background.
	 */
	public function sync_inbox() {
		( new VMSAI_Inbox() )->sync();
	}

	/**
	 * Run RAG sync in the background.
	 */
	public function sync_news() {
		( new VMSAI_RAG() )->sync();

		// Rotates through one competitor per run (spy_competitors()'s own
		// design), so the twice-daily cadence works through the whole list
		// over time without spending a Tavily + AI call on every single one.
		if ( VMSAI_License::has_feature( 'news_jacking' ) ) {
			VMSAI_Research::spy_competitors();
		}

		// TACTICAL INJECTION: News-Jacking
		if ( VMSAI_License::has_feature( 'news_jacking' ) && 'manual' !== VMSAI_Settings::get( 'autonomy', 'assisted' ) ) {
			$this->inject_news_jacking();
		}
	}

	/**
	 * Maintenance: Cleanup temporary assets to save disk space.
	 */
	public function cleanup_assets() {
		$uploads = wp_upload_dir();
		$path = $uploads['basedir'];

		// 1. Delete temporary video/audio binaries older than 48 hours
		$patterns = array( $path . '/vmsai-prod-*.mp4', $path . '/vmsai-raw-*.mp4', $path . '/vmsai-tts-*.mp3', $path . '/vmsai-test-video-*.mp4' );
		foreach ( $patterns as $pattern ) {
			foreach ( glob( $pattern ) as $file ) {
				if ( is_file( $file ) && ( time() - filemtime( $file ) ) > ( 2 * DAY_IN_SECONDS ) ) {
					@unlink( $file );
				}
			}
		}

		// 2. Delete local images that have been offloaded to R2 (older than 7 days)
		// Only published posts qualify: drafts/failed rows have
		// published_at IS NULL and may still need their assets.
		if ( 'off' !== VMSAI_Settings::get( 'remote_storage' ) ) {
			global $wpdb;
			$table = VMSAI_Install::table( 'queue' );
			$rows = $wpdb->get_results( "SELECT media_id FROM `$table` WHERE media_id > 0 AND status = 'published' AND published_at IS NOT NULL AND published_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)" );

			foreach ( $rows as $row ) {
				$media_id = (int) $row->media_id;
				$remote_url = get_post_meta( $media_id, '_vmsai_remote_url', true );

				if ( $remote_url ) {
					$local_file = get_attached_file( $media_id );
					if ( $local_file && is_file( $local_file ) ) {
						@unlink( $local_file );
						VMSAI_Logger::debug( 'scheduler', "Cleanup: local file unlinked for media #{$media_id} (offloaded)." );
					}
				}
			}
		}
	}

	/**
	 * Scans the web for high-urgency trends and injects a post.
	 */
	private function inject_news_jacking() {
		$industry = VMSAI_Brain::get( 'industry' );
		if ( ! $industry ) return;

		VMSAI_Logger::info( 'scheduler', 'Scouting for news-jacking opportunities...' );

		$trend = VMSAI_Research::search( "breaking news and trending topics in {$industry} for social media hooks today" );

		if ( ! $trend ) return;

		$campaign = VMSAI_Planner::active_campaign();
		if ( ! $campaign ) return;

		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );
		$topic = 'TRENDING: ' . wp_trim_words( $trend, 10 );

		// Duplication Check: Don't inject if this topic was already planned today.
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM `$table` WHERE topic = %s AND slot_date = %s",
			$topic,
			current_time( 'Y-m-d' )
		) );

		if ( $exists ) return;

		$channels = (array) json_decode( (string) $campaign['channels'], true );
		$channel  = $channels[0] ?? 'facebook'; // Use first connected campaign channel

		$wpdb->insert(
			$table,
			array(
				'campaign_id' => (int) $campaign['id'],
				'slot_date'   => current_time( 'Y-m-d' ),
				'slot_time'   => current_time( 'H:i:s' ),
				'channel'     => $channel,
				'pillar'      => 'hook',
				'format'      => 'image',
				'topic'       => $topic,
				'angle'       => "TACTICAL NEWS-JACKING: Respond to this trending trend immediately: {$trend}. Make it urgent and relevant to our brand DNA.",
				'status'      => 'planned',
				'created_at'  => current_time( 'mysql', true ),
			)
		);

		VMSAI_Logger::info( 'scheduler', 'News-jacking slot injected into today\'s plan.' );
	}

	/**
	 * Register the five-minute interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	/**
	 * Legacy alias kept for backward compatibility. The canonical
	 * implementation lives in VMSAI_Install::add_schedules(); this simply
	 * delegates so any third-party code still calling it keeps working.
	 *
	 * @param array $schedules Registered schedules.
	 * @return array
	 */
	public function add_schedule( $schedules ) {
		return VMSAI_Install::add_schedules( $schedules );
	}

	/**
	 * One pass of the engine.
	 *
	 * @return array{composed:int,published:int,skipped:string}
	 */
	public function tick() {
		if ( get_transient( self::LOCK ) ) {
			return array( 'composed' => 0, 'published' => 0, 'skipped' => 'locked' );
		}

		// Self-Healing: Re-register cron events during manual tick if they are missing.
		VMSAI_Install::schedule_events();

		// Cleanup old tactical clutter
		VMSAI_Planner::prune_tactical_slots();

		// Pro Stress-Test: Extend execution time for heavy composition batches.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // 5 minutes
		}

		set_transient( self::LOCK, 1, 5 * MINUTE_IN_SECONDS );

		$composed  = 0;
		$published = 0;

		try {
			if ( 'manual' !== VMSAI_Settings::get( 'autonomy', 'assisted' ) ) {
				$composed = $this->compose_due();
			}

			$published = $this->dispatch_due();
		} catch ( Throwable $e ) {
			VMSAI_Logger::error( 'scheduler', 'Tick threw an exception.', array( 'message' => $e->getMessage() ) );
		} finally {
			delete_transient( self::LOCK );
		}

		return array( 'composed' => $composed, 'published' => $published, 'skipped' => '' );
	}

	/**
	 * Write posts for plan slots coming up.
	 *
	 * @return int Number composed.
	 */
	private function compose_due() {
		// Composition is the expensive half, so it is rate limited per tick.
		$batch = (int) apply_filters( 'vmsai_compose_batch', 3 );
		$slots = VMSAI_Planner::pending_slots( $batch );
		$count = 0;

		global $wpdb;
		$plan_table = VMSAI_Install::table( 'plan' );

		foreach ( $slots as $slot ) {
			// Mark as processing immediately to prevent re-selection in parallel
			// ticks. updated_at is what the stuck-slot recovery measures from, so
			// it has to be stamped here or an overlapping tick will reclaim this
			// row while it is still being composed.
			$wpdb->update( // phpcs:ignore
				$plan_table,
				array( 'status' => 'processing', 'updated_at' => current_time( 'mysql', true ) ),
				array( 'id' => (int) $slot['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$result = VMSAI_Composer::compose( $slot );

			if ( ! empty( $result['ok'] ) ) {
				$count++;
				continue;
			}

			// Retry logic for composition failures (similar to dispatch)
			$attempts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT attempts FROM `$plan_table` WHERE id = %d", (int) $slot['id'] ) ) + 1; // phpcs:ignore
			$max_attempts = max( 1, (int) VMSAI_Settings::get( 'max_attempts', 3 ) );

			if ( $attempts >= $max_attempts ) {
				$wpdb->update( // phpcs:ignore
					$plan_table,
					array( 'status' => 'failed', 'attempts' => $attempts, 'last_error' => mb_substr( $result['error'], 0, 500 ), 'updated_at' => current_time( 'mysql', true ) ),
					array( 'id' => (int) $slot['id'] ),
					array( '%s', '%d', '%s', '%s' ),
					array( '%d' )
				);
				VMSAI_Logger::error( 'scheduler', 'Composition failed permanently for a slot.', array( 'slot' => $slot['id'], 'error' => $result['error'] ) );
			} else {
				// Exponential backoff with a small jitter: 5 min, 20 min,
				// 80 min ... Push slot_date forward so the retry is not
				// eligible for compose_due() until the delay has elapsed.
				// (Previously $delay was computed but never applied, so
				// every retry ran on the very next tick.)
				$delay = 5 * MINUTE_IN_SECONDS * ( 4 ** ( $attempts - 1 ) );
				$jitter = wp_rand( 0, 5 * MINUTE_IN_SECONDS );
				$retry_after = gmdate( 'Y-m-d H:i:s', time() + $delay + $jitter );
				$retry_date  = gmdate( 'Y-m-d', time() + $delay + $jitter );
				$retry_time  = gmdate( 'H:i:s', time() + $delay + $jitter );
				$wpdb->update( // phpcs:ignore
					$plan_table,
					array(
						'attempts'     => $attempts,
						'last_error'   => mb_substr( $result['error'], 0, 500 ),
						'status'       => 'planned',
						'slot_date'    => $retry_date,
						'slot_time'    => $retry_time,
						'updated_at'   => current_time( 'mysql', true ),
					),
					array( 'id' => (int) $slot['id'] ),
					array( '%d', '%s', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
				VMSAI_Logger::warn( 'scheduler', 'Composition failed, will retry later.', array( 'slot' => $slot['id'], 'attempt' => $attempts, 'retry_after' => $retry_after, 'error' => $result['error'] ) );
			}
		}

		return $count;
	}

	/**
	 * Send everything that is approved and due.
	 */
	private function dispatch_due() {
		global $wpdb;
		$table = VMSAI_Install::table( 'queue' );

		// Recovery: reset posts stuck in 'processing' for more than 30 minutes.
		$wpdb->query( "UPDATE `$table` SET status = 'approved' WHERE status = 'processing' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)" );

		if ( $this->daily_cap_reached() ) {
			VMSAI_Logger::info( 'scheduler', 'Daily post cap reached; holding the queue.' );
			return 0;
		}

		$rows = (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM `$table` WHERE status = 'approved' AND parent_id = 0 AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d", // phpcs:ignore
				current_time( 'mysql', true ),
				(int) apply_filters( 'vmsai_dispatch_batch', 5 )
			),
			ARRAY_A
		);

		$manager   = vmsai()->channels();
		$published = 0;

		// Cap dispatch across channels, not just globally: without this a
		// single hot channel can eat the whole daily budget in one tick.
		$daily_cap       = max( 1, (int) VMSAI_Settings::get( 'daily_post_cap', 24 ) );
		$channel_count   = max( 1, count( $manager->all() ) );
		$per_channel_cap = max( 1, (int) ceil( $daily_cap / $channel_count ) );

		$published_by_channel = array();
		foreach ( (array) $wpdb->get_results( // phpcs:ignore
			"SELECT channel, COUNT(*) AS c FROM `$table` WHERE status = 'published' AND published_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) GROUP BY channel",
			ARRAY_A
		) as $count_row ) {
			$published_by_channel[ $count_row['channel'] ] = (int) $count_row['c'];
		}

		foreach ( $rows as $row ) {
			if ( $this->is_quiet_hours() ) {
				break;
			}

			// Per-channel cap: skip this row (leave it approved and due) once
			// the channel has hit its share of today's published posts.
			$channel_published = (int) ( $published_by_channel[ $row['channel'] ] ?? 0 );
			if ( $channel_published >= $per_channel_cap ) {
				continue;
			}

			// Mark as processing immediately to prevent re-selection.
			$wpdb->update( $table, array( 'status' => 'processing', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );

			// Stress-Test Jitter: Avoid firing multiple requests simultaneously.
			if ( $published > 0 ) {
				usleep( wp_rand( 500000, 2000000 ) ); // 0.5s to 2s pause
			}

			$channel = $manager->get( $row['channel'] );

			if ( ! $channel || ! $channel->is_connected() ) {
				$this->mark_failed( (int) $row['id'], __( 'Channel is not connected.', 'vm-social-ai-pro' ), true, $row['channel'] );
				continue;
			}

			if ( ! VMSAI_Circuit::is_open( 'channel:' . $row['channel'] ) ) {
				// Breaker is soft: revert to approved so the row is retried
				// on a later tick instead of stranding in processing.
				$wpdb->update( $table, array( 'status' => 'approved', 'last_error' => __( 'Paused: channel circuit breaker is open.', 'vm-social-ai-pro' ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );
				VMSAI_Logger::warn( 'scheduler', 'Dispatch paused: channel circuit breaker is open.', array( 'queue' => $row['id'], 'channel' => $row['channel'] ) );
				continue;
			}

			$result = $channel->publish( $row );

			if ( ! empty( $result['ok'] ) ) {
				VMSAI_Circuit::success( 'channel:' . $row['channel'] );

				$wpdb->update( // phpcs:ignore
					$table,
					array(
						'status'       => 'published',
						'remote_id'    => $result['remote_id'],
						'permalink'    => $result['permalink'],
						'published_at' => current_time( 'mysql', true ),
						'last_error'   => null,
					),
					array( 'id' => (int) $row['id'] ),
					array( '%s', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);

				$wpdb->update( // phpcs:ignore
					VMSAI_Install::table( 'plan' ),
					array( 'status' => 'published' ),
					array( 'id' => (int) $row['plan_id'] ),
					array( '%s' ),
					array( '%d' )
				);

				$published++;
				$published_by_channel[ $row['channel'] ] = ( $published_by_channel[ $row['channel'] ] ?? 0 ) + 1;

				if ( ! empty( $row['first_comment'] ) && ! empty( $result['remote_id'] ) ) {
					$channel->post_comment( $result['remote_id'], (string) $row['first_comment'] );
				}

				VMSAI_Logger::info( 'scheduler', sprintf( 'Published to %s.', $row['channel'] ), array( 'queue' => $row['id'], 'url' => $result['permalink'] ) );

				/**
				 * Fires after a post reaches a network.
				 *
				 * @param array $row    Queue row.
				 * @param array $result Publish result.
				 */
				do_action( 'vmsai_published', $row, $result );
				continue;
			}

			VMSAI_Circuit::failure( 'channel:' . $row['channel'], $result['error'] );
			$this->mark_failed( (int) $row['id'], $result['error'], false, $row['channel'] );
		}

		return $published;
	}

	/**
	 * Record a failure and retire the post once it runs out of attempts.
	 *
	 * @param int    $id       Queue id.
	 * @param string $error    Message.
	 * @param bool   $terminal Skip retries.
	 * @return void
	 */
	private function mark_failed( $id, $error, $terminal = false, $channel = '' ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'queue' );

		$attempts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT attempts FROM `$table` WHERE id = %d", $id ) ) + 1; // phpcs:ignore
		$max      = max( 1, (int) VMSAI_Settings::get( 'max_attempts', 3 ) );

		if ( $terminal || $attempts >= $max ) {
			$wpdb->update( // phpcs:ignore
				$table,
				array( 'status' => 'failed', 'attempts' => $attempts, 'last_error' => mb_substr( $error, 0, 500 ) ),
				array( 'id' => $id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);

			VMSAI_Logger::error( 'scheduler', 'Post retired after repeated failures.', array( 'queue' => $id, 'error' => $error ) );
			VMSAI_Alerts::post_retired( $id, $channel, $error );
			return;
		}

		// Exponential backoff: 15 minutes, then an hour, then four.
		$delay = 15 * MINUTE_IN_SECONDS * ( 4 ** ( $attempts - 1 ) );

		$wpdb->update( // phpcs:ignore
			$table,
			array(
				'attempts'     => $attempts,
				'last_error'   => mb_substr( $error, 0, 500 ),
				'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Refill the plan when it runs low.
	 *
	 * @return void
	 */
	public function top_up_plan() {
		$campaign = VMSAI_Planner::active_campaign();

		if ( ! $campaign || 'manual' === VMSAI_Settings::get( 'autonomy', 'assisted' ) ) {
			return;
		}

		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );

		$remaining = (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$table` WHERE campaign_id = %d AND status = 'planned' AND slot_date >= %s", // phpcs:ignore
				(int) $campaign['id'],
				current_time( 'Y-m-d' )
			)
		);

		// Keep roughly a week of runway ahead of the scheduler.
		$floor = max( 6, count( (array) json_decode( (string) $campaign['channels'], true ) ) * 6 );

		if ( $remaining > $floor ) {
			return;
		}

		$elapsed = (int) floor( ( time() - strtotime( (string) $campaign['starts_on'] ) ) / DAY_IN_SECONDS );

		if ( $elapsed >= (int) $campaign['horizon_days'] ) {
			$wpdb->update( // phpcs:ignore
				VMSAI_Install::table( 'campaigns' ),
				array( 'status' => 'complete' ),
				array( 'id' => (int) $campaign['id'] ),
				array( '%s' ),
				array( '%d' )
			);

			VMSAI_Logger::info( 'scheduler', 'Campaign reached its horizon and closed.' );
			return;
		}

		$window = min( 14, (int) $campaign['horizon_days'] - $elapsed );
		VMSAI_Planner::generate( (int) $campaign['id'], max( 3, $window ) );
	}

	/**
	 * Resurface whatever has actually worked. Weekly, queue a couple of
	 * fresh takes on the highest-engagement posts from the last 90 days —
	 * not a repost of the identical copy (platforms penalize that and it
	 * reads as lazy), a new pass at the same proven topic/hook.
	 *
	 * @return int Slots created.
	 */
	public function recycle_top_performers() {
		$campaign = VMSAI_Planner::active_campaign();

		if ( ! $campaign || 'manual' === VMSAI_Settings::get( 'autonomy', 'assisted' ) ) {
			return 0;
		}

		$winners = VMSAI_Analytics::top_performers_for_recycling( 2, 90 );

		if ( ! $winners ) {
			return 0;
		}

		global $wpdb;
		$table   = VMSAI_Install::table( 'plan' );
		$now     = current_time( 'mysql', true );
		$date    = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +2 days' ) );
		$created = 0;

		foreach ( $winners as $winner ) {
			$channel   = (string) $winner['channel'];
			$best_hour = (int) VMSAI_Analytics::get_optimal_hour( $channel );
			$time      = sprintf( '%02d:00:00', $best_hour );
			$topic     = (string) ( $winner['topic'] ?: $winner['title'] );

			if ( '' === trim( $topic ) ) {
				continue;
			}

			$wpdb->insert( // phpcs:ignore
				$table,
				array(
					'campaign_id' => (int) $campaign['id'],
					'slot_date'   => $date,
					'slot_time'   => $time,
					'channel'     => $channel,
					'pillar'      => (string) ( $winner['pillar'] ?: 'proof' ),
					'format'      => 'image',
					'topic'       => $topic,
					'keyword'     => (string) ( $winner['keyword'] ?? '' ),
					'angle'       => 'RECYCLE: this exact topic earned strong engagement when posted before. Write a genuinely fresh take on the same core idea — a new hook, new wording, a new example or angle of proof. Do not repeat the earlier post verbatim.',
					'status'      => 'planned',
					'created_at'  => $now,
				)
			);

			$created++;
		}

		if ( $created ) {
			VMSAI_Logger::info( 'scheduler', sprintf( 'Queued %d recycled top performer(s).', $created ) );
		}

		return $created;
	}

	/**
	 * Find posts marked as evergreen that haven't been recycled in 90 days.
	 */
	public function recycle_evergreen() {
		$campaign = VMSAI_Planner::active_campaign();
		if ( ! $campaign || 'manual' === VMSAI_Settings::get( 'autonomy', 'assisted' ) ) {
			return 0;
		}

		global $wpdb;
		$queue = VMSAI_Install::table( 'queue' );
		$plan  = VMSAI_Install::table( 'plan' );

		// Find evergreen posts published > 90 days ago that aren't already in the upcoming plan.
		// NOT EXISTS avoids the NULL pitfall of NOT IN: if the subquery ever
		// returns a NULL topic, NOT IN evaluates to UNKNOWN for every row
		// and recycling silently stops.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT q.*, p.pillar, p.keyword, p.topic
			 FROM `$queue` q
			 LEFT JOIN `$plan` p ON p.id = q.plan_id
			 WHERE q.is_evergreen = 1
			 AND q.status = 'published'
			 AND q.published_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)
			 AND p.topic IS NOT NULL
			 AND NOT EXISTS (SELECT 1 FROM `$plan` p2 WHERE p2.status = 'planned' AND p2.topic = p.topic)
			 LIMIT 5"
		), ARRAY_A );

		if ( ! $rows ) return 0;

		$created = 0;
		foreach ( $rows as $row ) {
			$wpdb->insert( $plan, array(
				'campaign_id' => (int) $campaign['id'],
				'slot_date'   => gmdate( 'Y-m-d', time() + ( DAY_IN_SECONDS * 3 ) ), // Plan for 3 days out
				'slot_time'   => '10:00:00',
				'channel'     => $row['channel'],
				'pillar'      => $row['pillar'] ?: 'value',
				'format'      => $row['format'],
				'topic'       => $row['topic'],
				'keyword'     => $row['keyword'],
				'angle'       => 'EVERGREEN RECYCLE: This post performed well. Write a fresh, updated version of this core concept for a new audience. Do not duplicate exactly.',
				'status'      => 'planned',
				'created_at'  => current_time( 'mysql', true ),
			) );
			$created++;
		}

		if ( $created ) {
			VMSAI_Logger::info( 'scheduler', sprintf( 'Re-queued %d evergreen post(s) for recycling.', $created ) );
		}

		return $created;
	}

	/**
	 * Whether today's publishing budget is spent.
	 *
	 * @return bool
	 */
	private function daily_cap_reached() {
		global $wpdb;
		$table = VMSAI_Install::table( 'queue' );

		// Measure from current local day (WordPress timezone) to match user expectations.
		$today = (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$table` WHERE status = 'published' AND DATE(published_at) = %s", // phpcs:ignore
				current_time( 'Y-m-d' )
			)
		);

		return $today >= (int) VMSAI_Settings::get( 'daily_post_cap', 24 );
	}

	/**
	 * Whether the current local time falls inside the quiet window.
	 *
	 * @return bool
	 */
	private function is_quiet_hours() {
		$quiet = (array) VMSAI_Settings::get( 'quiet_hours', array() );

		if ( count( $quiet ) !== 2 || empty( $quiet[0] ) || empty( $quiet[1] ) ) {
			return false;
		}

		$now   = (int) current_time( 'Hi' );
		$start = (int) str_replace( ':', '', $quiet[0] );
		$end   = (int) str_replace( ':', '', $quiet[1] );

		// A window that wraps past midnight.
		if ( $start > $end ) {
			return $now >= $start || $now < $end;
		}

		return $now >= $start && $now < $end;
	}
}
