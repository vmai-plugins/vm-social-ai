<?php
/**
 * Strategic Intelligence Engine.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Performs high-level audits of keyword density, content gaps, and ROI potential.
 */
class VMSAI_Strategy {

	/**
	 * Perform a full strategic audit.
	 *
	 * @return array
	 */
	public static function audit() {
		// This used to hardcode ok => true, so a failure anywhere inside left
		// the page sitting on "Initializing strategic audit…" with nothing to
		// explain why.
		try {
			return array(
				'ok'        => true,
				'error'     => '',
				'keywords'  => self::keyword_heatmap(),
				'gaps'      => self::pillar_gap_analysis(),
				'trends'    => self::trend_scouting(),
				'benchmark' => self::competitor_benchmarking(),
			);
		} catch ( Throwable $e ) {
			VMSAI_Logger::error( 'strategy', 'Audit failed.', array( 'message' => $e->getMessage() ) );

			return array(
				'ok'        => false,
				/* translators: %s: error message */
				'error'     => sprintf( __( 'The strategic audit could not complete: %s', 'vm-social-ai-pro' ), $e->getMessage() ),
				'keywords'  => array(),
				'gaps'      => array(),
				'trends'    => array(),
				'benchmark' => array(),
			);
		}
	}

	/**
	 * Scrape/Fetch current benchmark data for competitors.
	 */
	private static function competitor_benchmarking() {
		$competitors = array_filter( array_map( 'trim', explode( "\n", (string) VMSAI_Brain::get( 'competitors' ) ) ) );
		if ( ! $competitors ) return array();

		$benchmarks = array();

		// Add the user's site first as the baseline
		$my_reach = (int) VMSAI_Analytics::queue_counts()['published'] * 150; // Estimated organic reach baseline
		$benchmarks[] = array(
			'name'  => 'You (Estimated)',
			'reach' => $my_reach,
			'is_me' => true
		);

		foreach ( array_slice($competitors, 0, 3) as $comp ) {
			$cached = get_transient( 'vmsai_bench_' . md5($comp) );
			if ( $cached ) {
				$benchmarks[] = $cached;
				continue;
			}

			$intel = VMSAI_Research::search( sprintf( 'current social media follower count for "%s"', $comp ) );

			// Use what the search actually returned. Where no public figure
			// can be found the row is marked unknown and drawn with no bar —
			// this used to invent a number with wp_rand() and present it as
			// competitive intelligence, which is worse than showing nothing.
			$reach_val = self::extract_audience_figure( (string) $intel );

			$data = array(
				'name'    => $comp,
				'reach'   => $reach_val,
				'unknown' => ( 0 === $reach_val ),
				'is_me'   => false,
			);

			set_transient( 'vmsai_bench_' . md5($comp), $data, DAY_IN_SECONDS );
			$benchmarks[] = $data;
		}

		return $benchmarks;
	}

	/**
	 * Pull the largest audience figure out of a block of search text.
	 *
	 * Handles the shapes these results actually come back in — "1.2 million
	 * followers", "45K followers", "45,000 followers" — and only accepts a
	 * number that is actually described as an audience, so a stray year or
	 * price is not mistaken for a follower count.
	 *
	 * @param string $text Search result text.
	 * @return int Followers found, or 0 when nothing credible is present.
	 */
	private static function extract_audience_figure( $text ) {
		if ( '' === trim( $text ) ) {
			return 0;
		}

		$pattern = '/([0-9][0-9,.]*)\s*(k|m|million|thousand)?\s*(?:\+\s*)?(?:followers|subscribers|fans|audience)/i';

		if ( ! preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER ) ) {
			return 0;
		}

		$best = 0;

		foreach ( $matches as $match ) {
			$value = (float) str_replace( ',', '', $match[1] );
			$unit  = strtolower( $match[2] ?? '' );

			if ( 'k' === $unit || 'thousand' === $unit ) {
				$value *= 1000;
			} elseif ( 'm' === $unit || 'million' === $unit ) {
				$value *= 1000000;
			}

			$best = max( $best, (int) $value );
		}

		return $best;
	}

	/**
	 * Analyze keyword coverage in recently published and queued posts.
	 */
	private static function keyword_heatmap() {
		$target_keywords = explode( "\n", VMSAI_Brain::get( 'keywords' ) );
		$target_keywords = array_filter( array_map( 'trim', $target_keywords ) );

		if ( empty( $target_keywords ) ) {
			return array();
		}

		global $wpdb;
		$queue_table = VMSAI_Install::table( 'queue' );
		$all_content = $wpdb->get_col( "SELECT CONCAT(title, ' ', body) FROM `$queue_table` WHERE created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)" ); // phpcs:ignore

		// Lowercase the corpus once. This used to sit inside the keyword loop,
		// allocating a fresh multi-megabyte copy of every post from the last
		// 30 days for each keyword being measured.
		$all_content = strtolower( implode( ' ', $all_content ) );

		$heatmap = array();
		foreach ( $target_keywords as $kw ) {
			$count = substr_count( $all_content, strtolower( $kw ) );
			$heatmap[] = array(
				'keyword' => $kw,
				'count'   => $count,
				'score'   => min( 100, $count * 20 ), // Simple scaling: 5 mentions = 100% coverage
			);
		}

		// Sort by count descending
		usort( $heatmap, function( $a, $b ) { return $b['count'] <=> $a['count']; } );

		return $heatmap;
	}

	/**
	 * Find which content pillars are being neglected.
	 */
	private static function pillar_gap_analysis() {
		global $wpdb;
		$plan_table = VMSAI_Install::table( 'plan' );
		$pillars = VMSAI_Planner::pillars();
		$campaign = VMSAI_Planner::active_campaign();

		$where = "WHERE status != 'failed'";
		if ( $campaign ) {
			$where .= $wpdb->prepare( " AND campaign_id = %d", (int) $campaign['id'] );
		} else {
			// Window fallback if no active campaign
			$where .= " AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)";
		}

		$stats = $wpdb->get_results(
			"SELECT pillar, COUNT(*) as n FROM `$plan_table`
			 $where
			 GROUP BY pillar",
			ARRAY_A
		);

		$actual = array();
		foreach ( $stats as $s ) {
			$actual[ $s['pillar'] ] = (int) $s['n'];
		}

		$total = array_sum( $actual ) ?: 1;
		$gaps  = array();

		foreach ( $pillars as $key => $p ) {
			$target_pct = (int) $p['weight'];
			$actual_count = $actual[ $key ] ?? 0;
			$actual_pct = round( ( $actual_count / $total ) * 100 );

			$diff = $target_pct - $actual_pct;

			// If no data at all, mark as 'balanced' to avoid red bars on first launch
			$status = 'balanced';
			if ($total > 1) {
				if ($diff > 12) $status = 'neglected';
				elseif ($diff < -15) $status = 'over-saturated';
			} else {
				$status = 'initializing';
			}

			$gaps[] = array(
				'pillar' => $p['label'],
				'target' => $target_pct,
				'actual' => $actual_pct,
				'gap'    => $diff,
				'status' => $status,
			);
		}

		return $gaps;
	}

	/**
	 * Scout RAG context for potential trends that haven't been turned into posts yet.
	 */
	private static function trend_scouting() {
		$news = VMSAI_Brain::get( 'recent_industry_news', array(), 'context' );

		// If no RAG context, fallback to influencer discovery signals
		if ( empty($news) && class_exists('VMSAI_Research') ) {
			$industry = VMSAI_Brain::get('industry');
			if ($industry) {
				$news = array( "Trending discussions in {$industry} identified by Agent Scout." );
			}
		}

		if ( ! is_array( $news ) ) return array();

		global $wpdb;
		$plan_table = VMSAI_Install::table( 'plan' );

		// Only recent injections can duplicate what is being scouted now, and
		// this list is scanned once per news item — an unbounded SELECT over
		// every trend ever injected gets slower forever.
		$existing_topics = $wpdb->get_col( // phpcs:ignore
			"SELECT topic FROM `$plan_table`
			 WHERE topic LIKE 'TRENDING: %'
			 AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 DAY)
			 ORDER BY id DESC
			 LIMIT 200" // phpcs:ignore
		);

		$scouted = array();

		foreach ( $news as $item ) {
			$needle = mb_substr( (string) $item, 0, 30 );
			$is_already_planned = false;

			foreach ( $existing_topics as $topic ) {
				if ( '' !== $needle && false !== mb_stripos( (string) $topic, $needle ) ) {
					$is_already_planned = true;
					break;
				}
			}

			if ( ! $is_already_planned ) {
				$scouted[] = array(
					'trend' => $item,
					'urgency' => 'high',
				);
			}
		}

		return array_slice( $scouted, 0, 5 );
	}
}
