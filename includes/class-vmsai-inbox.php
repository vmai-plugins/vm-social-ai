<?php
/**
 * AI Inbox Closer.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Automates community management by drafting suggested replies to
 * comments and mentions, focusing on conversion and sales.
 */
class VMSAI_Inbox {

	/**
	 * Fetch new interactions from connected channels.
	 */
	public function sync() {
		$manager = vmsai()->channels();
		foreach ( $manager->enabled() as $channel ) {
			if ( method_exists( $channel, 'fetch_comments' ) ) {
				$items = $channel->fetch_comments( 10 );
				foreach ( $items as $item ) {
					$inbox_id = $this->store( $item );
					if ( $inbox_id ) {
						// Auto-score high priority items
						$this->auto_score( $inbox_id, $item );
					}
				}
			}
		}
	}

	/**
	 * Store and draft a reply for an interaction.
	 */
	private function store( array $item ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'inbox' );

		// Check if we already have this interaction.
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `$table` WHERE channel = %s AND remote_id = %s", $item['channel'], $item['id'] ) );
		if ( $exists ) {
			return 0;
		}

		// received_at is a MySQL DATETIME column. Sources send wildly
		// different formats (Facebook: ISO8601 string, Instagram: unix
		// timestamp) — normalise everything or the insert becomes
		// 0000-00-00 00:00:00 / fails under strict SQL mode.
		$received = current_time( 'mysql', true );
		if ( ! empty( $item['time'] ) ) {
			$ts = is_numeric( $item['time'] ) ? (int) $item['time'] : strtotime( (string) $item['time'] );
			if ( $ts ) {
				$received = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		$wpdb->insert( $table, array(
			'channel'         => $item['channel'],
			'remote_id'       => $item['id'],
			'author_name'     => $item['author'],
			'content'         => $item['text'],
			'status'          => 'pending',
			'received_at'     => $received,
			'created_at'      => current_time( 'mysql', true ),
		) );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Automatically score an incoming interaction.
	 */
	private function auto_score( $id, $item ) {
		$analysis = self::suggest_reply( $item['text'], $item['author'], $item['channel'] );

		if ( $analysis['ok'] ) {
			global $wpdb;
			$wpdb->update( VMSAI_Install::table( 'inbox' ), array(
				'suggested_reply' => $analysis['reply'],
				'intent'          => $analysis['intent'],
				'lead_score'      => $analysis['lead_score']
			), array( 'id' => $id ) );

			if ( $analysis['lead_score'] >= 80 ) {
				VMSAI_Logger::info( 'inbox', "🔥 High-intent lead detected: {$item['author']} on {$item['channel']}", array( 'score' => $analysis['lead_score'] ) );

				// WORLD CLASS ALERT: Telegram Lead Notification
				$lead_msg = "🔥 <b>High-Intent Lead Detected!</b>\n"
					. "From: <b>{$item['author']}</b> on " . strtoupper($item['channel']) . "\n"
					. "Message: <i>\"{$item['text']}\"</i>\n\n"
					. "Suggested Reply: <i>\"{$analysis['reply']}\"</i>";

				$kb = array( array(
					array( 'text' => '💬 View in Inbox', 'url' => admin_url('admin.php?page=vm-social-ai-pro&tab=inbox') )
				) );

				VMSAI_Telegram_Bot::alert( $lead_msg, $kb );
			}
		}
	}

	/**
	 * List recent interactions for the UI.
	 *
	 * @param int $limit Count.
	 * @return array
	 */
	public static function list_recent( $limit = 20 ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'inbox' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, author_name as author, channel, content as text, received_at as time, suggested_reply, intent, lead_score, status
			 FROM `$table` ORDER BY received_at DESC LIMIT %d",
			(int) $limit
		), ARRAY_A );

		return (array) $rows;
	}

	/**
	 * Use AI to suggest a high-conversion reply.
	 *
	 * @param string $text    Original comment.
	 * @param string $author  Author name.
	 * @param string $channel Channel slug.
	 * @param array  $args    Optional args (rating, etc).
	 * @return array
	 */
	public static function suggest_reply( $text, $author, $channel, $args = array() ) {
		$system = 'You are The Socialite, a World-Class Social Listening Expert and Lead Closer. Your goal is to identify high-intent prospects and provide white-glove engagement.

		LEAD SCORING RULES:
		Score 100: Ready to buy, asking for price/link.
		Score 80: Asking a specific technical question about the product.
		Score 50: General positive interest or curiosity.
		Score 20: Just saying "Nice post" or general praise.
		Score 0: Complaints or irrelevant noise.';

		$prompt = VMSAI_Brain::context( $channel ) . "\n\n"
			. "INCOMING COMMENT:\n"
			. "Author: {$author}\n"
			. "Content: {$text}\n"
			. ( ! empty( $args['rating'] ) ? "Rating: {$args['rating']} stars\n" : '' ) . "\n"
			. "TASK: Analyze sentiment and intent. Draft a warm, professional reply. Also assign a lead_score (0-100).\n"
			. "Return ONLY JSON with these keys: reply (the text), sentiment (positive|negative|neutral), intent (question|complaint|praise|lead), lead_score (0-100).";

		$result = vmsai()->text_engine()->generate_json( $system, $prompt );

		if ( ! $result['ok'] ) {
			return array( 'ok' => false, 'message' => $result['error'] );
		}

		return array(
			'ok'        => true,
			'reply'     => $result['data']['reply'] ?? '',
			'sentiment' => $result['data']['sentiment'] ?? 'neutral',
			'intent'    => $result['data']['intent'] ?? 'question',
			'lead_score' => (int) ($result['data']['lead_score'] ?? 0),
		);
	}
}
