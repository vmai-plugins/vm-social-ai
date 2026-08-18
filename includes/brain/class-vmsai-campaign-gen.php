<?php
/**
 * Campaign Generator — turns one campaign brief into a full, dated batch of
 * posts covering every angle of the promotion (teaser through recap).
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Unlike VMSAI_Planner (an always-on, evergreen growth mix), this class
 * plans a time-boxed campaign arc: a fixed sequence of angles from first
 * announcement to closing thanks, spread across a start/end date and a set
 * of channels. It reuses the same campaigns/plan/queue tables and the same
 * VMSAI_Composer pipeline, so generated posts land in the normal Queue.
 */
class VMSAI_Campaign_Gen {

	/**
	 * The campaign arc, in chronological order. Each angle maps to an
	 * existing growth pillar (so VMSAI_Composer's pillar brief lookup keeps
	 * working) and a format hint that shapes the image style.
	 *
	 * @return array<string,array{label:string,pillar:string,format:string,brief:string}>
	 */
	public static function angles() {
		return array(
			'teaser'        => array(
				'label'  => __( 'Teaser', 'vm-social-ai-pro' ),
				'pillar' => 'hook',
				'format' => 'image',
				'brief'  => 'TEASER post. Build curiosity that something is coming without revealing the full offer or date yet. Create anticipation.',
			),
			'countdown'     => array(
				'label'  => __( 'Countdown', 'vm-social-ai-pro' ),
				'pillar' => 'hook',
				'format' => 'banner',
				'brief'  => 'COUNTDOWN post. Create urgency by counting down to the campaign date or deadline. State how many days are left explicitly.',
			),
			'behind_scenes' => array(
				'label'  => __( 'Behind the Scenes', 'vm-social-ai-pro' ),
				'pillar' => 'story',
				'format' => 'image',
				'brief'  => 'BEHIND-THE-SCENES post. Show the human side of preparing for this campaign — the process, the team, the effort. Build trust and authenticity.',
			),
			'spotlight'     => array(
				'label'  => __( 'Spotlight', 'vm-social-ai-pro' ),
				'pillar' => 'value',
				'format' => 'image',
				'brief'  => 'SPOTLIGHT post. Highlight one specific product, service, feature or detail relevant to this campaign in depth.',
			),
			'tip'           => array(
				'label'  => __( 'Helpful Tip', 'vm-social-ai-pro' ),
				'pillar' => 'value',
				'format' => 'infographic',
				'brief'  => 'TIP post. Share one genuinely useful, specific tip connected to the campaign theme, structured so it reads well as a short infographic.',
			),
			'community'     => array(
				'label'  => __( 'Ask the Audience', 'vm-social-ai-pro' ),
				'pillar' => 'community',
				'format' => 'image',
				'brief'  => 'COMMUNITY post. Ask a question or invite engagement tied to the campaign to build buzz before the big moment.',
			),
			'proof'         => array(
				'label'  => __( 'Social Proof', 'vm-social-ai-pro' ),
				'pillar' => 'proof',
				'format' => 'image',
				'brief'  => 'PROOF post. Share a testimonial, past result, review or concrete reason to trust this offer.',
			),
			'offer'         => array(
				'label'  => __( 'The Offer', 'vm-social-ai-pro' ),
				'pillar' => 'offer',
				'format' => 'banner',
				'brief'  => 'OFFER post. Directly pitch the specific deal, offer or product of this campaign with an unmistakable call to action.',
			),
			'launch_day'    => array(
				'label'  => __( 'Launch Day', 'vm-social-ai-pro' ),
				'pillar' => 'offer',
				'format' => 'banner',
				'brief'  => "LAUNCH DAY post. It's here — announce availability with maximum energy, the explicit date, and a clear call to action to act now.",
			),
			'last_chance'   => array(
				'label'  => __( 'Last Chance', 'vm-social-ai-pro' ),
				'pillar' => 'offer',
				'format' => 'banner',
				'brief'  => 'LAST CHANCE post. Final hours or days urgency — scarcity, a hard deadline, FOMO, and a strong call to act immediately.',
			),
			'recap'         => array(
				'label'  => __( 'Thank You / Recap', 'vm-social-ai-pro' ),
				'pillar' => 'story',
				'format' => 'image',
				'brief'  => "RECAP post. Thank the audience, recap the campaign's highlights or results, and warmly point to what's next.",
			),
		);
	}

	/**
	 * Business priority order — which angles to keep first when the
	 * requested post count is smaller than the full arc.
	 *
	 * @return string[]
	 */
	private static function priority() {
		return array( 'teaser', 'offer', 'launch_day', 'proof', 'last_chance', 'countdown', 'spotlight', 'community', 'behind_scenes', 'tip', 'recap' );
	}

	/**
	 * Work out which angle each of the N posts should be, in chronological
	 * order. Fewer posts than the full arc: sample the highest-priority
	 * angles, then re-sort them chronologically. More posts than the arc:
	 * repeat the arc, marking later passes as a fresh round.
	 *
	 * @param int $count Requested post count.
	 * @return string[] Angle keys, in posting order.
	 */
	public static function sequence( $count ) {
		$angles = array_keys( self::angles() );
		$total  = count( $angles );
		$count  = max( 1, (int) $count );

		if ( $count >= $total ) {
			$seq = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$seq[] = $angles[ $i % $total ];
			}
			return $seq;
		}

		$chosen = array_slice( self::priority(), 0, $count );
		usort(
			$chosen,
			function ( $a, $b ) use ( $angles ) {
				return array_search( $a, $angles, true ) <=> array_search( $b, $angles, true );
			}
		);

		return $chosen;
	}

	/**
	 * Ask the AI to expand a short campaign name (plus any rough notes the
	 * user already typed) into a full brief: talking points, a CTA, and a
	 * sensible duration and post count for a campaign of this size.
	 *
	 * @param string $name Campaign name.
	 * @param string $idea Optional rough notes from the user to build on.
	 * @return array{ok:bool,details:string,cta:string,duration_days:int,post_count:int,error:string}
	 */
	public static function draft( $name, $idea = '' ) {
		$name = trim( (string) $name );

		if ( '' === $name ) {
			return array( 'ok' => false, 'error' => __( 'Give the campaign a name first.', 'vm-social-ai-pro' ) );
		}

		if ( ! VMSAI_Brain::is_ready() ) {
			return array( 'ok' => false, 'error' => __( 'Fill in the Brain first — at minimum the business name, one-liner and audience.', 'vm-social-ai-pro' ) );
		}

		$system = 'You are a sharp campaign strategist who plans time-boxed marketing pushes — festival sales, product launches, limited offers — for small businesses. You write concrete, specific briefs with real details, never generic filler.';

		$prompt = VMSAI_Brain::context() . "\n\n"
			. "Draft a campaign brief for: \"{$name}\"."
			. ( '' !== trim( $idea ) ? " The user has already noted: \"{$idea}\". Build on it, don't ignore it." : '' ) . "\n\n"
			. "Return JSON:\n"
			. "  details        — 2-4 sentences: what's on offer, the hook, and specific talking points to weave across posts (invent concrete, plausible specifics — a discount percentage, a date range, a product name — rather than staying vague).\n"
			. "  cta            — one specific call to action for this campaign.\n"
			. "  duration_days  — integer. How many days this campaign should run: a flash sale might be 2-3, a festival season 10-14.\n"
			. "  post_count     — integer between 5 and 20. How many posts this campaign's scope justifies.\n";

		$result = vmsai()->text_engine()->generate_json( $system, $prompt, array( 'max_tokens' => 700, 'temperature' => 0.8 ) );

		if ( empty( $result['ok'] ) ) {
			return array( 'ok' => false, 'error' => $result['error'] );
		}

		$data = $result['data'];

		return array(
			'ok'            => true,
			'details'       => trim( (string) ( $data['details'] ?? '' ) ),
			'cta'           => trim( (string) ( $data['cta'] ?? '' ) ),
			'duration_days' => max( 1, min( 60, (int) ( $data['duration_days'] ?? 7 ) ) ),
			'post_count'    => max( 3, min( 30, (int) ( $data['post_count'] ?? 10 ) ) ),
		);
	}

	/**
	 * Create a campaign and write one dated plan slot per requested post.
	 *
	 * @param array $args {
	 *     @type string $name       Campaign name.
	 *     @type string $details    Free-text campaign brief (offer, occasion, specifics).
	 *     @type string $start_date Y-m-d.
	 *     @type string $end_date   Y-m-d.
	 *     @type int    $count      How many posts to plan.
	 *     @type array  $channels   Channel slugs.
	 *     @type string $keyword    Optional target keyword.
	 *     @type string $cta        Optional preferred call to action.
	 *     @type string $language   Optional language.
	 *     @type string $flavour    Optional local flavour.
	 * }
	 * @return array{ok:bool,campaign_id:int,slots:array,error:string}
	 */
	public static function create( array $args ) {
		if ( ! VMSAI_License::has_feature( 'unlimited_campaigns' ) ) {
			return array( 'ok' => false, 'campaign_id' => 0, 'slots' => array(), 'error' => __( 'One-off marketing campaigns are a Pro feature. Upgrade to unlock the Campaign Planner.', 'vm-social-ai-pro' ) );
		}

		if ( ! VMSAI_Brain::is_ready() ) {
			return array( 'ok' => false, 'campaign_id' => 0, 'slots' => array(), 'error' => __( 'Fill in the Brain first — at minimum the business name, one-liner and audience.', 'vm-social-ai-pro' ) );
		}

		$channels = array_values( array_filter( (array) ( $args['channels'] ?? array() ) ) );

		if ( ! $channels ) {
			return array( 'ok' => false, 'campaign_id' => 0, 'slots' => array(), 'error' => __( 'Pick at least one connected channel.', 'vm-social-ai-pro' ) );
		}

		$name  = sanitize_text_field( (string) ( $args['name'] ?? '' ) ) ?: __( 'Untitled campaign', 'vm-social-ai-pro' );
		$count = max( 3, min( 30, (int) ( $args['count'] ?? 10 ) ) );

		$start = sanitize_text_field( (string) ( $args['start_date'] ?? '' ) ) ?: current_time( 'Y-m-d' );
		$end   = sanitize_text_field( (string) ( $args['end_date'] ?? '' ) ) ?: gmdate( 'Y-m-d', strtotime( $start . ' +6 days' ) );

		if ( strtotime( $end ) < strtotime( $start ) ) {
			$end = $start;
		}

		$span_days = max( 0, (int) round( ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS ) );

		$lang    = sanitize_text_field( $args['language'] ?? VMSAI_Settings::get('language', 'en') );
		$flavour = sanitize_text_field( $args['flavour'] ?? VMSAI_Settings::get('locale_flavour', '') );

		$campaign_id = self::create_campaign_row( $name, $channels, $start, $end, $span_days, $lang, $flavour );

		$angle_defs = self::angles();
		$total_arc  = count( $angle_defs );
		$sequence   = self::sequence( $count );

		// ... (keep logic)
		$best_hours = array();
		foreach ( $channels as $channel ) {
			$best_hours[ $channel ] = (int) VMSAI_Analytics::get_optimal_hour( $channel );
		}

		$details = sanitize_textarea_field( (string) ( $args['details'] ?? '' ) );
		$keyword = sanitize_text_field( (string) ( $args['keyword'] ?? '' ) ) ?: $name;
		$cta     = sanitize_text_field( (string) ( $args['cta'] ?? '' ) );

		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );
		$now   = current_time( 'mysql', true );
		$slots = array();

		foreach ( $sequence as $i => $angle_key ) {
			$angle   = $angle_defs[ $angle_key ];
			$channel = $channels[ $i % count( $channels ) ];

			$day_offset = $count > 1 ? (int) round( $i * $span_days / ( $count - 1 ) ) : 0;
			$date       = gmdate( 'Y-m-d', strtotime( $start . ' +' . $day_offset . ' day' ) );

			$time_base = sprintf( '%02d:00', $best_hours[ $channel ] ?? 10 );
			$jitter    = wp_rand( -10, 10 );
			$seconds   = strtotime( '2000-01-01 ' . $time_base . ':00' ) + ( $jitter * 60 );
			$time      = gmdate( 'H:i', $seconds );

			$angle_text = $angle['brief'];

			$round = intdiv( $i, $total_arc ) + 1;
			if ( $round > 1 ) {
				$angle_text .= ' This is a later pass through this angle — use a fresh example or detail, do not repeat earlier posts in the campaign.';
			}

			if ( '' !== $details ) {
				$angle_text = 'CAMPAIGN: "' . $name . '". CAMPAIGN BRIEF: ' . $details . ' ' . $angle_text;
			}

			if ( '' !== $cta ) {
				$angle_text .= ' Preferred call to action for this campaign: ' . $cta;
			}

			$wpdb->insert( // phpcs:ignore
				$table,
				array(
					'campaign_id'    => $campaign_id,
					'slot_date'      => $date,
					'slot_time'      => $time . ':00',
					'channel'        => $channel,
					'pillar'         => $angle['pillar'],
					'format'         => $angle['format'],
					'language'       => $lang,
					'locale_flavour' => $flavour,
					'topic'          => $name . ' — ' . $angle['label'],
					'keyword'        => mb_substr( $keyword, 0, 190 ),
					'angle'          => $angle_text,
					'status'         => 'planned',
					'created_at'     => $now,
				)
			);

			$slots[] = array(
				'id'      => (int) $wpdb->insert_id,
				'angle'   => $angle['label'],
				'channel' => $channel,
				'date'    => $date,
			);
		}

		return array( 'ok' => true, 'campaign_id' => $campaign_id, 'slots' => $slots, 'error' => '' );
	}

	/**
	 * Insert the campaign row.
	 *
	 * @param string $name       Campaign name.
	 * @param array  $channels   Channel slugs.
	 * @param string $start      Y-m-d.
	 * @param string $end        Y-m-d.
	 * @param int    $span_days  Days between start and end.
	 * @param string $lang       Language code.
	 * @param string $flavour    Locale flavour.
	 * @return int Campaign id.
	 */
	private static function create_campaign_row( $name, array $channels, $start, $end, $span_days, $lang = 'en', $flavour = '' ) {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore
			VMSAI_Install::table( 'campaigns' ),
			array(
				'name'           => $name,
				'status'         => 'active',
				'objective'      => 'campaign',
				'target_views'   => 0,
				'horizon_days'   => max( 1, $span_days + 1 ),
				'language'       => $lang,
				'locale_flavour' => $flavour,
				'channels'       => wp_json_encode( array_values( $channels ) ),
				'pillars'        => wp_json_encode( self::angles() ),
				'starts_on'      => $start,
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Compose a single planned slot into a finished, queued post.
	 *
	 * @param int $slot_id Plan row id.
	 * @return array
	 */
	public static function compose_slot( $slot_id ) {
		global $wpdb;

		$slot = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . VMSAI_Install::table( 'plan' ) . '` WHERE id = %d', (int) $slot_id ), ARRAY_A ); // phpcs:ignore

		if ( ! $slot ) {
			return array( 'ok' => false, 'error' => __( 'Slot not found.', 'vm-social-ai-pro' ) );
		}

		// Already written — hand back what was produced.
		if ( $slot['queue_id'] ) {
			return self::queue_summary( (int) $slot['queue_id'] );
		}

		// Something else is mid-composition; a second run would duplicate it.
		if ( 'processing' === $slot['status'] ) {
			return array( 'ok' => false, 'error' => __( 'This slot is being written right now.', 'vm-social-ai-pro' ) );
		}

		// 'planned' and 'failed' both proceed — retrying a failed slot is the
		// whole point of the Generate button on a failed row.
		if ( ! in_array( $slot['status'], array( 'planned', 'failed' ), true ) ) {
			return array( 'ok' => false, 'error' => __( 'This slot cannot be composed.', 'vm-social-ai-pro' ) );
		}

		$result = VMSAI_Composer::compose( $slot );

		if ( empty( $result['ok'] ) ) {
			return array( 'ok' => false, 'error' => $result['error'] );
		}

		return self::queue_summary( (int) $result['queue_id'] );
	}

	/**
	 * Fetch a finished queue row in the shape the UI needs.
	 *
	 * @param int $queue_id Queue row id.
	 * @return array
	 */
	private static function queue_summary( $queue_id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . VMSAI_Install::table( 'queue' ) . '` WHERE id = %d', $queue_id ), ARRAY_A ); // phpcs:ignore

		if ( ! $row ) {
			return array( 'ok' => false, 'error' => __( 'Composed but the post could not be reloaded.', 'vm-social-ai-pro' ) );
		}

		return array(
			'ok'           => true,
			'queue_id'     => $queue_id,
			'title'        => (string) $row['title'],
			'body'         => (string) $row['body'],
			'hashtags'     => (string) $row['hashtags'],
			'cta'          => (string) $row['cta'],
			'media_url'    => (string) $row['media_url'],
			'channel'      => (string) $row['channel'],
			'status'       => (string) $row['status'],
			'scheduled_at' => (string) $row['scheduled_at'],
		);
	}
}
