<?php
/**
 * Manual composer — one-off posts written by a human.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything else in the plugin flows Plan slot → Composer → Queue. This is
 * the manual lane: a person writes (or AI-drafts) a single post and it goes
 * straight into the queue with no plan row behind it, for the everyday
 * "we're closed tomorrow" case the autonomous engine will never predict.
 */
class VMSAI_Manual {

	/**
	 * AI-draft copy for a topic, tailored to one channel.
	 *
	 * @param string $topic    What the post is about.
	 * @param string $channel  Channel slug.
	 * @param string $style    Optional agent slug for voice and visual style.
	 * @param array  $settings Optional language settings {language: string, flavour: string}.
	 * @return array{ok:bool,data:array,error:string}
	 */
	public static function draft( $topic, $channel, $style = '', array $settings = array() ) {
		$topic = trim( (string) $topic );

		if ( '' === $topic ) {
			return array( 'ok' => false, 'data' => array(), 'error' => __( 'Tell the writer what the post is about.', 'vm-social-ai-pro' ) );
		}

		$specs = VMSAI_Composer::specs();
		$spec  = $specs[ $channel ] ?? $specs['facebook'];
		$agent = VMSAI_Agents::get( $style );

		$system = $agent['persona'] . ' You write short, high-impact social posts that sound like a real business owner, never like an AI.';

		$lang    = sanitize_text_field( $settings['language'] ?? VMSAI_Settings::get( 'language', 'en' ) );
		$flavour = sanitize_text_field( $settings['flavour'] ?? VMSAI_Settings::get( 'locale_flavour', '' ) );

		$lang_rules = "\nLanguage: {$lang}.";

		if ( 'hi' === $lang || !empty($flavour) ) {
			$lang_rules = "\nLANGUAGE RULES:\n";
			if ( 'hinglish' === $flavour ) {
				$lang_rules .= "- Write in HINGLISH (A mix of Hindi written in Latin script and English). Example: 'Top results ke liye ready ho jao!'.\n";
			} elseif ( 'mumbai' === $flavour ) {
				$lang_rules .= "- Write in Mumbai 'Bambaiyya' style (Hinglish with Mumbai local slang like 'Apna', 'Kya bolti public', 'Ek number').\n";
			} elseif ( 'delhi' === $flavour ) {
				$lang_rules .= "- Write in Delhi NCR style (Bold, energetic Hinglish with local urban undertones).\n";
			}

			$lang_rules .= "- Primary language context: " . ( 'hi' === $lang ? 'Hindi' : 'English' ) . ".\n"
				. "- Use a mix of native Hindi and English keywords where it increases relatability.\n"
				. "- Language: {$lang}.";
		}

		$prompt = VMSAI_Brain::context( $channel ) . "\n\n"
			. "Write ONE social post.\n"
			. "Channel: {$channel}\n"
			. "Character limit: {$spec['limit']} (sweet spot {$spec['sweet']}).\n"
			. "Channel style: {$spec['brief']}\n"
			. "What the post is about: {$topic}\n\n"
			. "Return ONLY JSON with:\n"
			. "  body          — the post copy. No markdown asterisks.\n"
			. "  hashtags      — exactly {$spec['hashtags']} relevant hashtags, no # symbol.\n"
			. "  cta           — one specific call to action.\n"
			. "  first_comment — hashtags or a link that belong in the first comment rather than the caption, or an empty string.\n"
			. "  image_prompt  — a literal visual brief for an accompanying image. Use: {$agent['visual']}\n"
			. "  alt_text      — descriptive alt text.\n\n"
			. 'Emoji density: ' . VMSAI_Settings::get( 'emoji_density', 'light' ) . ".\n"
			. $lang_rules;

		$result = vmsai()->text_engine()->generate_json( $system, $prompt, array( 'max_tokens' => 1200, 'temperature' => 0.75 ) );

		if ( empty( $result['ok'] ) ) {
			return array( 'ok' => false, 'data' => array(), 'error' => $result['error'] );
		}

		return array( 'ok' => true, 'data' => (array) $result['data'], 'error' => '' );
	}

	/**
	 * Save a manually written post into the queue, one row per channel.
	 *
	 * @param array $args Composer fields.
	 * @return array{ok:bool,ids:array,error:string}
	 */
	public static function create( array $args ) {
		$channels = array_values( array_filter( array_map( 'sanitize_key', (array) ( $args['channels'] ?? array() ) ) ) );
		$body     = trim( (string) ( $args['body'] ?? '' ) );

		if ( ! $channels ) {
			return array( 'ok' => false, 'ids' => array(), 'error' => __( 'Pick at least one channel.', 'vm-social-ai-pro' ) );
		}

		if ( '' === $body ) {
			return array( 'ok' => false, 'ids' => array(), 'error' => __( 'The post is empty.', 'vm-social-ai-pro' ) );
		}

		$publish_now = ! empty( $args['publish_now'] );
		$ids         = array();

		foreach ( $channels as $channel ) {
			$scheduled = $publish_now
				? current_time( 'mysql', true )
				: self::resolve_time( $channel, (string) ( $args['scheduled_at'] ?? '' ) );

			$ids[] = VMSAI_Composer::save(
				array(
					'plan_id'       => 0,
					'campaign_id'   => 0,
					'channel'       => $channel,
					'format'        => sanitize_key( (string) ( $args['format'] ?? 'image' ) ),
					'title'         => sanitize_text_field( (string) ( $args['title'] ?? wp_trim_words( $body, 8 ) ) ),
					'body'          => $body,
					'hashtags'      => sanitize_text_field( (string) ( $args['hashtags'] ?? '' ) ),
					'first_comment' => sanitize_textarea_field( (string) ( $args['first_comment'] ?? '' ) ),
					'tags'          => sanitize_text_field( (string) ( $args['tags'] ?? '' ) ),
					'cta'           => sanitize_text_field( (string) ( $args['cta'] ?? '' ) ),
					'link'          => esc_url_raw( (string) ( $args['link'] ?? '' ) ),
					'media_id'      => (int) ( $args['media_id'] ?? 0 ),
					'media_url'     => esc_url_raw( (string) ( $args['media_url'] ?? '' ) ),
					'alt_text'      => sanitize_text_field( (string) ( $args['alt_text'] ?? '' ) ),
					'image_prompt'  => sanitize_textarea_field( (string) ( $args['image_prompt'] ?? '' ) ),
					'scheduled_at'  => $scheduled,
					'status'        => $publish_now ? 'approved' : sanitize_key( (string) ( $args['status'] ?? 'approved' ) ),
				)
			);
		}

		$ids = array_values( array_filter( $ids ) );

		if ( ! $ids ) {
			return array( 'ok' => false, 'ids' => array(), 'error' => __( 'Could not save the post.', 'vm-social-ai-pro' ) );
		}

		VMSAI_Logger::info( 'manual', sprintf( 'Manually composed %d post(s).', count( $ids ) ) );

		return array( 'ok' => true, 'ids' => $ids, 'error' => '' );
	}

	/**
	 * The next free slot on a channel — the day's optimal hour, pushed
	 * forward until it lands in the future and isn't already taken.
	 *
	 * @param string $channel Channel slug.
	 * @return string UTC datetime.
	 */
	public static function next_open_slot( $channel ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'queue' );

		$hour = (int) VMSAI_Analytics::get_optimal_hour( $channel );
		$day  = 0;

		while ( $day < 30 ) {
			$local     = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +' . $day . ' day' ) );
			$candidate = get_gmt_from_date( $local . ' ' . sprintf( '%02d:00:00', $hour ) );

			if ( strtotime( $candidate ) > time() ) {
				$taken = (int) $wpdb->get_var( // phpcs:ignore
					$wpdb->prepare(
						"SELECT COUNT(*) FROM `$table` WHERE channel = %s AND DATE(scheduled_at) = DATE(%s) AND status IN ('approved','draft','pending')", // phpcs:ignore
						$channel,
						$candidate
					)
				);

				if ( $taken < (int) VMSAI_Settings::get( 'per_channel_cap', 2 ) ) {
					return $candidate;
				}
			}

			$day++;
		}

		return get_gmt_from_date( current_time( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Turn the requested schedule into a UTC datetime.
	 *
	 * @param string $channel   Channel slug.
	 * @param string $requested Local datetime, or '' for next open slot.
	 * @return string
	 */
	private static function resolve_time( $channel, $requested ) {
		$requested = trim( $requested );

		if ( '' === $requested ) {
			return self::next_open_slot( $channel );
		}

		$timestamp = strtotime( $requested );

		return $timestamp ? get_gmt_from_date( gmdate( 'Y-m-d H:i:s', $timestamp ) ) : self::next_open_slot( $channel );
	}
}
