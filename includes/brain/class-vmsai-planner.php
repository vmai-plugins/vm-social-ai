<?php
/**
 * Campaign planner.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns a view target and a horizon into a dated, per-channel content plan.
 *
 * The planner is deliberately honest about arithmetic. It works out how many
 * posts and what median reach per post the target implies, and shows that
 * number before a campaign starts, so the operator can see whether the goal
 * is a stretch or a fantasy for their current audience size.
 */
class VMSAI_Planner {

	/**
	 * Default content pillars. Reach comes from mixing these, not from
	 * posting the same promotional angle every day.
	 *
	 * @return array<string,array{label:string,weight:int,brief:string}>
	 */
	public static function pillars() {
		return array(
			'hook'      => array(
				'label'  => __( 'Scroll-stopper', 'vm-social-ai-pro' ),
				'weight' => 25,
				'brief'  => 'A bold, technical, or surprising opening that challenges industry norms. Focus on pattern-interrupt formatting (bolding/bullets). Built for viral reach and saves.',
			),
			'value'     => array(
				'label'  => __( 'High-Value Elite Tip', 'vm-social-ai-pro' ),
				'weight' => 25,
				'brief'  => 'A technical deep-dive or lightning-fast tip. Must use bullet points and bold headers to break down complex value into digestible, premium-feeling steps.',
			),
			'proof'     => array(
				'label'  => __( 'Authority & Proof', 'vm-social-ai-pro' ),
				'weight' => 15,
				'brief'  => 'Concrete results, 100% responsive audits, or case studies. Position the brand as the expert using technical benchmarks and social proof.',
			),
			'story'     => array(
				'label'  => __( 'The Agency Vault', 'vm-social-ai-pro' ),
				'weight' => 15,
				'brief'  => 'Behind-the-scenes of high-performance delivery. Focus on quality standards, team culture, and the "why" behind premium choices.',
			),
			'offer'     => array(
				'label'  => __( 'Conversion Closer', 'vm-social-ai-pro' ),
				'weight' => 10,
				'brief'  => 'A high-impact, FOMO-driven pitch. Clear benefit-led bullet points and an unmistakable "DM us" or "Link in Bio" closing sequence.',
			),
			'community' => array(
				'label'  => __( 'Industry Insight', 'vm-social-ai-pro' ),
				'weight' => 10,
				'brief'  => 'A high-authority question designed to pull comments from other experts or serious clients. Engagement that reinforces brand leader status.',
			),
		);
	}

	/**
	 * Reach arithmetic for a target.
	 *
	 * @param int   $target_views Views wanted.
	 * @param int   $days         Horizon in days.
	 * @param array $channels     Enabled channel slugs.
	 * @param int   $per_day      Posts per day per channel.
	 * @return array
	 */
	public static function math( $target_views, $days, array $channels, $per_day = 2 ) {
		$days         = max( 1, (int) $days );
		$per_day      = max( 1, (int) $per_day );
		$channel_n    = max( 1, count( $channels ) );
		$total_posts  = $days * $per_day * $channel_n;
		$per_post     = $total_posts > 0 ? (int) ceil( $target_views / $total_posts ) : 0;

		// Median organic reach per post, by channel, for a small-to-mid account.
		$benchmarks = array(
			'facebook'  => 90,
			'instagram' => 250,
			'x'         => 120,
			'linkedin'  => 350,
			'gbp'       => 180,
			'youtube'   => 400,
		);

		$expected = 0;
		foreach ( $channels as $channel ) {
			$expected += ( $benchmarks[ $channel ] ?? 150 ) * $days * $per_day;
		}

		return array(
			'total_posts'     => $total_posts,
			'posts_per_day'   => $per_day * $channel_n,
			'needed_per_post' => $per_post,
			'baseline_views'  => $expected,
			'gap'             => max( 0, (int) $target_views - $expected ),
			'feasible'        => $expected >= $target_views,
			'shorts_share'    => in_array( 'youtube', $channels, true ) || in_array( 'instagram', $channels, true ),
		);
	}

	/**
	 * Create a campaign row.
	 *
	 * @param array $args Campaign fields.
	 * @return int Campaign id.
	 */
	public static function create_campaign( array $args ) {
		if ( ! VMSAI_License::has_feature( 'unlimited_campaigns' ) ) {
			global $wpdb;
			$table = VMSAI_Install::table( 'campaigns' );

			// Count only evergreen growth campaigns. The Agent Feed and the
			// Campaign Planner keep their own always-active campaign rows
			// ('agents' / 'campaign' objectives) that the operator never sees
			// as campaigns — counting those made the free tier's single slot
			// permanently unavailable, so no growth campaign could be created
			// at all.
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE status = 'active' AND objective = 'reach'" ); // phpcs:ignore

			if ( $count >= 1 ) {
				// Free users are limited to 1 active evergreen campaign.
				return 0;
			}
		}

		global $wpdb;

		$defaults = array(
			'name'           => sprintf( /* translators: %s: date */ __( 'Growth run — %s', 'vm-social-ai-pro' ), date_i18n( 'j M Y' ) ),
			'objective'      => 'reach',
			'target_views'   => 200000,
			'horizon_days'   => 50,
			'language'       => VMSAI_Settings::get( 'language', 'en' ),
			'locale_flavour' => VMSAI_Settings::get( 'locale_flavour', '' ),
			'channels'       => array_keys( array_filter( (array) VMSAI_Settings::get( 'enabled_channels', array() ) ) ),
			'starts_on'      => current_time( 'Y-m-d' ),
			'status'         => 'active',
		);

		$args = wp_parse_args( $args, $defaults );

		$wpdb->insert( // phpcs:ignore
			VMSAI_Install::table( 'campaigns' ),
			array(
				'name'           => sanitize_text_field( $args['name'] ),
				'status'         => sanitize_key( $args['status'] ),
				'objective'      => sanitize_key( $args['objective'] ),
				'target_views'   => (int) $args['target_views'],
				'horizon_days'   => (int) $args['horizon_days'],
				'language'       => sanitize_text_field( $args['language'] ),
				'locale_flavour' => sanitize_text_field( $args['locale_flavour'] ),
				'channels'       => wp_json_encode( array_values( (array) $args['channels'] ) ),
				'pillars'        => wp_json_encode( self::pillars() ),
				'starts_on'      => $args['starts_on'],
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Ask the AI for a themed topic bank and write plan rows for a window.
	 *
	 * @param int $campaign_id Campaign id.
	 * @param int $days        How many days ahead to fill.
	 * @return array{ok:bool,created:int,error:string}
	 */
	public static function generate( $campaign_id, $days = 14 ) {
		$campaign = self::campaign( $campaign_id );

		if ( ! $campaign ) {
			return array( 'ok' => false, 'created' => 0, 'error' => __( 'Campaign not found.', 'vm-social-ai-pro' ) );
		}

		if ( ! VMSAI_Brain::is_ready() ) {
			return array( 'ok' => false, 'created' => 0, 'error' => __( 'Fill in the Brain first — at minimum the business name, one-liner and audience.', 'vm-social-ai-pro' ) );
		}

		$channels = (array) json_decode( (string) $campaign['channels'], true );
		$channels = array_values( array_filter( $channels ) );

		if ( ! $channels ) {
			return array( 'ok' => false, 'created' => 0, 'error' => __( 'This campaign has no channels connected.', 'vm-social-ai-pro' ) );
		}

		$per_day = max( 1, (int) VMSAI_Settings::get( 'per_channel_cap', 2 ) );
		$total_needed = $days * count( $channels ) * $per_day;
		$last_error = '';

		// Pro Feature: Smart Batch Planning.
		// Break the request into smaller chunks to ensure AI reliability and JSON stability.
		$batch_size = 6;

		// Every batch is a separate model call, so the model has no memory of
		// what the previous batches planned. The already-published list in the
		// Brain context only covers composed posts, never the rows we are
		// writing right now — so without carrying the run's own topics forward
		// the batches happily plan the same angle several times over. Collect
		// everything first, deduplicate as we go, then write the calendar once.
		$posts       = array();
		$seen        = array();
		$planned_now = array();
		$stalls      = 0;

		while ( count( $posts ) < $total_needed ) {
			$chunk_needed = min( $batch_size, $total_needed - count( $posts ) );
			$result       = self::generate_batch( $campaign, $channels, $chunk_needed, $planned_now );

			if ( ! $result['ok'] ) {
				$last_error = $result['error'];
				break;
			}

			$added = 0;

			foreach ( $result['posts'] as $post ) {
				$post  = (array) $post;
				$topic = trim( (string) ( $post['topic'] ?? '' ) );
				$key   = self::topic_key( $topic );

				if ( '' === $key || isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ]  = true;
				$planned_now[] = $topic;
				$posts[]       = $post;
				$added++;
			}

			// A batch that returns nothing usable twice in a row means the model
			// has run out of distinct angles (or is misbehaving) — stop rather
			// than loop forever asking for more.
			$stalls = $added ? 0 : $stalls + 1;

			if ( $stalls >= 2 ) {
				break;
			}
		}

		if ( ! $posts ) {
			return array( 'ok' => false, 'created' => 0, 'error' => $last_error ?: __( 'The planner returned no usable topics.', 'vm-social-ai-pro' ) );
		}

		$written = self::write_plan( $campaign, $posts, $channels );

		return array(
			'ok'      => $written['created'] > 0,
			'created' => $written['created'],
			'error'   => $written['created'] > 0 ? '' : $last_error,
		);
	}

	/**
	 * Normalise a topic into a comparison key for de-duplication. Must stay
	 * Unicode-aware: this plugin generates Hindi and Hinglish content, and an
	 * ASCII-only key would reduce every Devanagari topic to an empty string
	 * and silently discard the whole plan.
	 *
	 * @param string $topic Raw topic.
	 * @return string
	 */
	private static function topic_key( $topic ) {
		$key = mb_strtolower( (string) $topic );
		$key = preg_replace( '/[\p{P}\p{S}]+/u', '', $key );
		$key = preg_replace( '/\s+/u', ' ', (string) $key );

		return trim( (string) $key );
	}

	/**
	 * Ask the model for one chunk of topics.
	 *
	 * @param array $campaign    Campaign row.
	 * @param array $channels    Channels in rotation.
	 * @param int   $needed      How many posts this chunk should return.
	 * @param array $planned_now Topics already planned earlier in this run.
	 * @return array{ok:bool,posts:array,error:string}
	 */
	private static function generate_batch( $campaign, $channels, $needed, array $planned_now = array() ) {
		$pillars = self::pillars();

		// SELF-LEARNING LOOP: Adjust weights based on performance data.
		// Transients are fast but volatile — fall back to the durable
		// option written by Analytics::double_down_on_winners().
		$winner = get_transient( 'vmsai_winner_pillar' );
		$loser  = get_transient( 'vmsai_loser_pillar' );
		if ( ! $winner || ! $loser ) {
			$ranking = get_option( 'vmsai_pillar_ranking', array() );
			if ( ! $winner && ! empty( $ranking['winner'] ) ) {
				$winner = $ranking['winner'];
			}
			if ( ! $loser && ! empty( $ranking['loser'] ) ) {
				$loser = $ranking['loser'];
			}
		}

		if ( $winner && isset( $pillars[ $winner ] ) ) {
			$pillars[ $winner ]['weight'] += 15;
			$pillars[ $winner ]['label']  .= ' (TOP PERFORMING)';
		}
		if ( $loser && isset( $pillars[ $loser ] ) ) {
			$pillars[ $loser ]['weight'] = max( 5, $pillars[ $loser ]['weight'] - 10 );
		}

		$system = 'You are a social media strategist who plans campaigns that earn reach through genuine usefulness. You never repeat an angle.';
		$prompt = VMSAI_Brain::context() . "\n\n"
			. "Plan exactly {$needed} distinct social media posts.\n"
			. 'Channels in rotation: ' . implode( ', ', $channels ) . ".\n"
			. "Content pillars and what each is for:\n";

		foreach ( $pillars as $key => $pillar ) {
			$prompt .= "- {$key} ({$pillar['weight']}% of the mix): {$pillar['brief']}\n";
		}

		if ( $planned_now ) {
			// Only the tail matters — the model needs enough to avoid repeating
			// itself without the prompt growing unbounded across a long run.
			$recent = array_slice( $planned_now, -40 );
			$prompt .= "\nALREADY PLANNED EARLIER IN THIS SAME RUN — do not repeat or rephrase any of these:\n- "
				. implode( "\n- ", $recent ) . "\n";
		}

		$prompt .= "\nReturn JSON: {\"posts\":[{\"topic\":\"...\",\"angle\":\"...\",\"keyword\":\"...\",\"pillar\":\"hook|value|proof|story|offer|community\",\"channel\":\"one of the channels above\",\"format\":\"image|carousel|short|text\"}]}\n"
			. "Rules:\n"
			. "- Respect the pillar percentages.\n"
			. "- Every topic must be distinct from the already-published list and from anything already planned in this run.\n"
			. "- CAROUSELS: For high-value education (value pillar), prefer 'carousel' format (Instagram/LinkedIn only).\n";

		$result = vmsai()->text_engine()->generate_json(
			$system,
			$prompt,
			array( 'max_tokens' => 6000, 'temperature' => 0.9, 'timeout' => 180 )
		);

		if ( empty( $result['ok'] ) ) {
			return array( 'ok' => false, 'posts' => array(), 'error' => $result['error'] );
		}

		return array(
			'ok'    => true,
			'posts' => (array) ( $result['data']['posts'] ?? $result['data'] ),
			'error' => '',
		);
	}

	/**
	 * Maintenance: Clean up old tactical slots that were never processed.
	 */
	public static function prune_tactical_slots() {
		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );

		// Delete any 'planned' or 'failed' tactical slots older than 3 days.
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM `$table`
			 WHERE topic LIKE 'TRENDING: %%'
			 AND (status = 'planned' OR status = 'failed')
			 AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY)"
		) );
	}

	/**
	 *persists plan rows across dated, timed slots.
	 *
	 * @param array $campaign Campaign row.
	 * @param array $posts    AI topic bank.
	 * @param array $channels Channels.
	 * @return array
	 */
	private static function write_plan( array $campaign, array $posts, array $channels ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );
		$now   = current_time( 'mysql', true );

		$start   = self::next_open_date( (int) $campaign['id'], (string) $campaign['starts_on'] );
		$created = 0;
		$cursor  = 0;

		// Map posts to dates.
		$per_day = max( 1, (int) VMSAI_Settings::get( 'per_channel_cap', 2 ) );
		$day_offset = 0;

		while ( isset( $posts[ $cursor ] ) ) {
			$date = gmdate( 'Y-m-d', strtotime( $start . ' +' . $day_offset . ' day' ) );

			foreach ( $channels as $channel ) {
				for ( $n = 0; $n < $per_day; $n++ ) {
					if ( ! isset( $posts[ $cursor ] ) ) break 2;

					$post = (array) $posts[ $cursor ];
					$cursor++;

					$best_hour = VMSAI_Analytics::get_optimal_hour( $channel );
					$time_base = str_pad( $best_hour, 2, '0', STR_PAD_LEFT ) . ':00';

					$jitter  = wp_rand( -15, 15 );
					$seconds = ( strtotime( "2000-01-01 $time_base:00" ) ) + ( $jitter * 60 );
					$time    = gmdate( 'H:i', $seconds );

					// Formats only some networks can actually carry have to be
					// downgraded here, not just discouraged in the prompt — a
					// carousel planned for X would otherwise burn five image
					// generations on a channel that cannot post one.
					$format = sanitize_key( $post['format'] ?? 'image' );
					if ( 'short' === $format && ! in_array( $channel, array( 'youtube', 'instagram' ), true ) ) {
						$format = 'image';
					}
					if ( 'carousel' === $format && ! in_array( $channel, array( 'instagram', 'linkedin' ), true ) ) {
						$format = 'image';
					}

					$wpdb->insert( // phpcs:ignore
						$table,
						array(
							'campaign_id'    => (int) $campaign['id'],
							'slot_date'      => $date,
							'slot_time'      => $time . ':00',
							'channel'        => $channel,
							'pillar'         => sanitize_key( $post['pillar'] ?? 'value' ),
							'format'         => $format,
							'language'       => $campaign['language'] ?? 'en',
							'locale_flavour' => $campaign['locale_flavour'] ?? '',
							'topic'          => sanitize_text_field( (string) ( $post['topic'] ?? '' ) ),
							'keyword'        => sanitize_text_field( mb_substr( (string) ( $post['keyword'] ?? '' ), 0, 190 ) ),
							'angle'          => sanitize_textarea_field( (string) ( $post['angle'] ?? '' ) ),
							'status'         => 'planned',
							'created_at'     => $now,
						)
					);
					$created++;
				}
			}
			$day_offset++;
		}

		return array( 'ok' => true, 'created' => $created, 'error' => '' );
	}

	/**
	 * Posting windows that historically carry the most reach per network.
	 *
	 * @return array<string,array<string>>
	 */
	public static function golden_hours() {
		$hours = array(
			'facebook'  => array( '09:15', '13:30', '19:45' ),
			'instagram' => array( '11:00', '18:30', '21:00' ),
			'x'         => array( '08:30', '12:15', '17:00', '20:30' ),
			'linkedin'  => array( '08:00', '10:30', '17:30' ),
			'gbp'       => array( '10:00', '16:00' ),
			'youtube'   => array( '15:00', '20:00' ),
		);

		/**
		 * Filter the posting windows per channel.
		 *
		 * @param array $hours channel => list of HH:MM strings.
		 */
		return apply_filters( 'vmsai_golden_hours', $hours );
	}

	/**
	 * Start filling from the day after the last planned slot.
	 *
	 * @param int    $campaign_id Campaign id.
	 * @param string $fallback    Campaign start date.
	 * @return string
	 */
	private static function next_open_date( $campaign_id, $fallback ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );

		$last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(slot_date) FROM `$table` WHERE campaign_id = %d", $campaign_id ) ); // phpcs:ignore

		if ( $last && $last >= current_time( 'Y-m-d' ) ) {
			return gmdate( 'Y-m-d', strtotime( $last . ' +1 day' ) );
		}

		return $fallback && $fallback >= current_time( 'Y-m-d' ) ? $fallback : current_time( 'Y-m-d' );
	}

	/**
	 * Fetch a campaign row.
	 *
	 * @param int $id Campaign id.
	 * @return array|null
	 */
	public static function campaign( $id ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'campaigns' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore
	}

	/**
	 * The evergreen growth campaign currently running. Excludes one-off,
	 * time-boxed campaigns created by the Campaign Planner (objective
	 * 'campaign') — those are a separate concept and must never hijack the
	 * Plan tab's calendar, "extend plan" action, or the dashboard stats.
	 *
	 * @return array|null
	 */
	public static function active_campaign() {
		global $wpdb;
		$table = VMSAI_Install::table( 'campaigns' );
		// Explicit allowlist, not a denylist — the growth Plan tab's own
		// campaign concept ('reach') is the only one this should ever
		// return. Denylisting 'campaign' alone already caused one real bug
		// (Campaign Planner hijacking the Plan tab); a denylist has to be
		// updated by hand every time a new campaign objective is added, an
		// allowlist can't silently regress the same way.
		return $wpdb->get_row( "SELECT * FROM `$table` WHERE status = 'active' AND objective = 'reach' ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore
	}

	/**
	 * Plan rows still waiting to be written into posts.
	 *
	 * @param int $limit Row count.
	 * @return array
	 */
	public static function pending_slots( $limit = 12 ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );

		// Recovery: reset slots stuck in 'processing' for more than 1 hour.
		// This MUST measure from updated_at (when the slot entered processing),
		// not created_at (when it was planned, often weeks earlier) — otherwise
		// the one-hour grace period is always already expired and a second,
		// overlapping tick will reset and re-compose a slot that is still being
		// composed right now, producing duplicate posts.
		$wpdb->query( "UPDATE `$table` SET status = 'planned' WHERE status = 'processing' AND updated_at IS NOT NULL AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)" ); // phpcs:ignore

		// Slots that predate the updated_at column and are stuck have no
		// timestamp to measure from; fall back to their creation time.
		$wpdb->query( "UPDATE `$table` SET status = 'planned' WHERE status = 'processing' AND updated_at IS NULL AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)" ); // phpcs:ignore

		// Look one day ahead so generation happens before the slot is due.
		$horizon   = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +1 day' ) );
		$campaigns = VMSAI_Install::table( 'campaigns' );

		// Join the campaign so pausing or archiving one actually stops its
		// content. Slots with no campaign (campaign_id = 0, e.g. tactical
		// news-jacking injections) have nothing to check and always qualify.
		return (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT p.* FROM `$table` p
				 LEFT JOIN `$campaigns` c ON c.id = p.campaign_id
				 WHERE p.status = 'planned'
				 AND p.slot_date <= %s
				 AND ( p.campaign_id = 0 OR c.status = 'active' )
				 ORDER BY p.slot_date ASC, p.slot_time ASC
				 LIMIT %d", // phpcs:ignore
				$horizon,
				(int) $limit
			),
			ARRAY_A
		);
	}

	/**
	 * Upcoming slots that have not become posts yet.
	 *
	 * These are the gaps in the schedule: a time is reserved and a topic is
	 * chosen, but nothing has been written. The Queue shows them alongside
	 * finished posts so the operator can see the whole runway at once rather
	 * than having to cross-reference the Plan tab.
	 *
	 * @param int $limit Row count.
	 * @return array
	 */
	public static function uncomposed_slots( $limit = 40, array $statuses = array( 'planned', 'processing' ), $future_only = true ) {
		global $wpdb;
		$table     = VMSAI_Install::table( 'plan' );
		$campaigns = VMSAI_Install::table( 'campaigns' );

		$statuses = array_values( array_intersect( $statuses, array( 'planned', 'processing', 'failed' ) ) );

		if ( ! $statuses ) {
			return array();
		}

		$in   = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$args = $statuses;

		// Failed slots are worth showing even once their slot date has passed —
		// they are the backlog of things that never got written.
		$date_clause = '';
		if ( $future_only ) {
			$date_clause = 'AND p.slot_date >= %s';
			$args[]      = current_time( 'Y-m-d' );
		}

		$args[] = (int) $limit;

		return (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT p.* FROM `$table` p
				 LEFT JOIN `$campaigns` c ON c.id = p.campaign_id
				 WHERE p.status IN ($in)
				 $date_clause
				 AND ( p.campaign_id = 0 OR c.status = 'active' )
				 ORDER BY p.slot_date ASC, p.slot_time ASC
				 LIMIT %d", // phpcs:ignore
				$args
			),
			ARRAY_A
		);
	}

	/**
	 * How many plan slots failed to compose. Shown alongside queue failures
	 * so the Failed count and the Failed list describe the same thing.
	 *
	 * @return int
	 */
	public static function failed_slot_count() {
		global $wpdb;
		$table     = VMSAI_Install::table( 'plan' );
		$campaigns = VMSAI_Install::table( 'campaigns' );

		return (int) $wpdb->get_var( // phpcs:ignore
			"SELECT COUNT(*) FROM `$table` p
			 LEFT JOIN `$campaigns` c ON c.id = p.campaign_id
			 WHERE p.status = 'failed' AND ( p.campaign_id = 0 OR c.status = 'active' )" // phpcs:ignore
		);
	}

	/**
	 * Calendar rows for a date range.
	 *
	 * @param int    $campaign_id Campaign id.
	 * @param string $from        Y-m-d.
	 * @param string $to          Y-m-d.
	 * @return array
	 */
	public static function calendar( $campaign_id, $from, $to ) {
		global $wpdb;
		$plan  = VMSAI_Install::table( 'plan' );
		$queue = VMSAI_Install::table( 'queue' );

		return (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT p.*, q.title, q.body, q.media_url, q.status AS queue_status, q.seo_score
				 FROM `$plan` p LEFT JOIN `$queue` q ON q.id = p.queue_id
				 WHERE p.campaign_id = %d AND p.slot_date BETWEEN %s AND %s
				 ORDER BY p.slot_date ASC, p.slot_time ASC", // phpcs:ignore
				(int) $campaign_id,
				$from,
				$to
			),
			ARRAY_A
		);
	}
}
