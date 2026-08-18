<?php
/**
 * News-Jacking Engine (RAG).
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Monitors external RSS feeds or industry news and provides them as context
 * to the AI planner for "Trending" posts.
 */
class VMSAI_RAG {

	const OPTION = 'vmsai_rag_feeds';

	/**
	 * Register the news-jacking cron.
	 */
	public function register() {
		add_action( 'vmsai_cron_news_sync', array( $this, 'sync' ) );
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'feed_lifetime' ), 10, 2 );
	}

	/**
	 * Fetch and store news summaries in the Brain.
	 *
	 * @param bool $force Bypass cache.
	 * @return int Number of news items found.
	 */
	public function sync( $force = false ) {
		$feeds = (array) get_option( self::OPTION, array() );
		if ( ! $feeds ) {
			return 0;
		}

		if ( $force ) {
			add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'force_refresh' ), 999 );
		}

		require_once ABSPATH . WPINC . '/feed.php';
		$summaries = array();
		$start     = microtime( true );

		foreach ( $feeds as $url ) {
			if ( ! $url ) continue;

			// Optimization: Stop if we've been fetching for more than 20 seconds.
			if ( ( microtime( true ) - $start ) > 20 ) {
				VMSAI_Logger::info( 'rag', 'News sync throttled to prevent timeout.' );
				break;
			}

			$rss = fetch_feed( $url );

			if ( is_wp_error( $rss ) ) {
				VMSAI_Logger::warn( 'rag', 'Feed fetch failed: ' . $rss->get_error_message(), array( 'url' => $url ) );
				continue;
			}

			// SimplePie check
			if ( method_exists( $rss, 'get_items' ) ) {
				$items = $rss->get_items( 0, 5 );
				foreach ( $items as $item ) {
					$summaries[] = $item->get_title() . ': ' . wp_trim_words( $item->get_description(), 30 );
				}
			}
		}

		if ( $force ) {
			remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'force_refresh' ), 999 );
		}

		if ( $summaries ) {
			VMSAI_Brain::set( array( 'recent_industry_news' => $summaries ), 'context' );
			VMSAI_Logger::info( 'rag', 'News-jacking engine refreshed industry context.', array( 'count' => count( $summaries ) ) );
		}

		return count( $summaries );
	}

	/**
	 * Helper for forced refresh.
	 */
	public function force_refresh() {
		return 0;
	}

	/**
	 * Force fresh feeds for RAG.
	 */
	public function feed_lifetime( $seconds, $url ) {
		$feeds = (array) get_option( self::OPTION, array() );
		if ( in_array( $url, $feeds, true ) ) {
			return 1200; // 20 minutes.
		}
		return $seconds;
	}
}
