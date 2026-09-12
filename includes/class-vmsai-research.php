<?php
/**
 * Real-time Research Engine (Tavily).
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Searches the live web for industry trends and niche influencers to keep
 * the brand's social presence at the cutting edge.
 */
class VMSAI_Research {

	/**
	 * Perform a deep research search via Tavily.
	 *
	 * @param string $query The research topic.
	 * @return string|null Summary of findings.
	 */
	public static function search( $query ) {
		// Try Tavily first if configured
		$api_key = VMSAI_Settings::credential( 'tavily_key' );
		if ( $api_key ) {
			$response = wp_remote_post( 'https://api.tavily.com/search', array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'api_key'        => $api_key,
					'query'          => $query,
					'search_depth'   => 'advanced',
					'include_answer' => true,
					'max_results'    => 5,
				) ),
				'timeout' => 30,
			) );

			if ( ! is_wp_error( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $data['answer'] ) ) return $data['answer'];
				if ( ! empty( $data['results'] ) ) {
					$out = "";
					foreach ( $data['results'] as $res ) {
						$out .= "- " . ( $res['title'] ?? '' ) . ": " . ( $res['content'] ?? '' ) . "\n";
					}
					return $out;
				}
			}
		}

		// FALLBACK: Use Gemini Grounding (Google Search Retrieval)
		$gemini = vmsai()->text_engine()->provider('gemini');
		if ( $gemini && $gemini->is_configured() ) {
			$system = "You are a research assistant. Use Google Search to provide a factual, data-driven summary of the query.";
			$result = vmsai()->text_engine()->generate( $system, $query, array(
				'prefer' => 'gemini',
				'grounding' => true,
				'max_tokens' => 800
			) );

			if ( $result['ok'] ) {
				return $result['text'];
			}
		}

		return null;
	}

	/**
	 * Find trending influencers in the niche.
	 */
	public static function discover_influencers() {
		$industry = VMSAI_Brain::get( 'industry' );
		$query = "top influencers and active industry leaders in {$industry} social media 2025";
		$raw = self::search( $query );

		if ( ! $raw ) return array();

		$system = 'You are a social media scout. Identify 3 trending influencers from the data provided.';
		$prompt = "DATA: {$raw}\n\nReturn ONLY a JSON array of handles starting with @: [\"@handle1\", \"@handle2\"]";

		$result = vmsai()->text_engine()->generate_json( $system, $prompt );
		return $result['ok'] ? $result['data'] : array();
	}

	/**
	 * Search for and analyze competitor moves.
	 *
	 * @return array{move:string,gap:string,logic:string}
	 */
	public static function spy_competitors() {
		$competitors = array_filter( array_map( 'trim', explode( "\n", (string) VMSAI_Brain::get( 'competitors' ) ) ) );
		if ( ! $competitors ) {
			return array();
		}

		$target = $competitors[ array_rand( $competitors ) ];
		$query  = "latest social media strategy and viral posts for competitor \"{$target}\" 2025";
		$raw    = self::search( $query );

		if ( ! $raw ) return array();

		$system = 'You are a competitive intelligence analyst. Analyze raw web data to identify a competitor move and an opportunity gap.';
		$prompt = "COMPETITOR: {$target}\nDATA: {$raw}\n\n"
			. "TASK: Identify one 'Competitor Move' and one 'Opportunity Gap' where we can do better.\n"
			. "Return ONLY JSON: {\"move\":\"...\",\"gap\":\"...\",\"logic\":\"...\"}";

		$result = vmsai()->text_engine()->generate_json( $system, $prompt );

		if ( ! empty( $result['ok'] ) ) {
			set_transient( 'vmsai_comp_intel_' . md5( $target ), $result['data'], DAY_IN_SECONDS );
			return $result['data'];
		}

		return array();
	}

	/**
	 * Scout for latest trends via RAG and search.
	 */
	public static function scout() {
		$news = VMSAI_Brain::get( 'recent_industry_news', array(), 'context' );
		if ( ! is_array( $news ) ) $news = array();

		$industry = VMSAI_Brain::get( 'industry' );

		// ALWAYS add a live search result for maximum "freshness"
		if ( $industry ) {
			$search_res = self::search( "latest breaking viral trends and industry news in {$industry} for social media hooks today" );
			if ( $search_res ) {
				// Split into individual news items if it's a list
				$lines = array_filter(array_map('trim', explode("\n", $search_res)));
				foreach ( $lines as $line ) {
					$line = preg_replace('/^[-*•]\s+/', '', $line);
					if ( strlen($line) > 20 ) $news[] = $line;
				}
			}
		}

		$news = array_unique(array_filter($news));

		return array_map( function( $item ) {
			return array(
				'title' => mb_substr( wp_strip_all_tags( $item ), 0, 100 ) . (strlen($item) > 100 ? '...' : ''),
				'full'  => $item,
				'id'    => md5( $item )
			);
		}, $news );
	}

	/**
	 * Inject a trending topic into the plan immediately.
	 */
	public static function inject( $trend ) {
		global $wpdb;
		$campaign = VMSAI_Planner::active_campaign();
		if ( ! $campaign ) return array( 'ok' => false, 'message' => 'No active campaign.' );

		$table = VMSAI_Install::table( 'plan' );
		$topic = 'TRENDING: ' . mb_substr( $trend, 0, 100 );

		// Duplication Check: Don't inject the same trend if it's already planned for tomorrow or today.
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM `$table` WHERE topic = %s AND slot_date >= %s",
			$topic,
			current_time( 'Y-m-d' )
		) );

		if ( $exists ) {
			return array( 'ok' => false, 'message' => 'This trend has already been injected into the plan.' );
		}

		$channels = (array) json_decode( (string) $campaign['channels'], true );
		$channel  = $channels[0] ?? 'facebook';

		// Create a planned slot for tomorrow morning.
		$date = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
		$time = '09:00:00';

		$wpdb->insert(
			$table,
			array(
				'campaign_id' => (int) $campaign['id'],
				'slot_date'   => $date,
				'slot_time'   => $time,
				'channel'     => $channel,
				'pillar'      => 'hook',
				'format'      => 'image',
				'topic'       => $topic,
				'angle'       => "TACTICAL NEWS-JACKING: Respond to this trend with authority: " . $trend,
				'status'      => 'planned',
				'created_at'  => current_time( 'mysql', true ),
			)
		);

		return array( 'ok' => true, 'message' => 'Trend injected into tomorrow\'s schedule.' );
	}

	/**
	 * Fetch local landmarks and cultural "vibe" for a city.
	 */
	public static function sync_city_insights( $city ) {
		$query = "major landmarks, popular neighborhoods, local slang, and cultural vibe of {$city} for social media marketing 2025";
		$raw = self::search( $query );

		if ( $raw ) {
			VMSAI_Brain::set( array( 'city_insights' => $raw ), 'context' );
			VMSAI_Logger::info( 'research', "Local insights synchronized for {$city}." );
		}
	}

	/**
	 * Decide the current marketing funnel stage based on the week of the year.
	 */
	public static function get_funnel_stage() {
		$stages = array( 'Awareness (Educational)', 'Consideration (Problem Solving)', 'Decision (Sales/Trust)' );
		$week_num = (int) current_time( 'W' );
		return $stages[ $week_num % 3 ];
	}
}
