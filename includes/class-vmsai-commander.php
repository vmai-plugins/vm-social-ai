<?php
/**
 * The Commander — Autonomous Decision Maker.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * The highest level of intelligence in the engine. The Commander doesn't just
 * follow the plan; it looks for opportunities to go viral, counter-strike
 * competitors, and recycle winning content.
 */
class VMSAI_Commander {

	/**
	 * Register autonomous actions.
	 */
	public function register() {
		add_action( VMSAI_Install::CRON_PLANNER, array( $this, 'autonomous_review' ) );
	}

	/**
	 * Run a high-level review of the brand status.
	 */
	public function autonomous_review() {
		if ( 'full' !== VMSAI_Settings::get( 'autonomy' ) ) {
			return;
		}

		$this->check_for_gaps();
		$this->recycle_winners();
	}

	/**
	 * Handle a natural language command from the Dashboard.
	 *
	 * @param string $message User input.
	 * @return array{reply:string,refresh:bool}
	 */
	public function handle_chat( $message ) {
		if ( empty( $message ) ) {
			return array( 'reply' => 'I am listening. How can I help with your strategy?', 'refresh' => false );
		}

		$system = 'You are the Social AI Strategic Director. You are authoritative and proactive. Identify the user intent and provide a strategic response.';
		$prompt = VMSAI_Brain::context() . "\n\n"
			. "USER COMMAND: \"{$message}\"\n\n"
			. "TASK:\n"
			. "1. Identify the INTENT: 'plan_30_days', 'research_now', 'promote_inventory', 'flash_sale', 'promote_reviews', 'spy_competitors', 'status_report', 'general_chat'.\n"
			. "2. Provide a concise, professional reply.\n"
			. "Return ONLY JSON: {\"intent\": \"...\", \"reply\": \"...\"}";

		$result = vmsai()->text_engine()->generate_json( $system, $prompt, array( 'temperature' => 0.4 ) );

		if ( empty( $result['ok'] ) ) {
			return array( 'reply' => 'Connection to brain failed. Please try again.', 'refresh' => false );
		}

		$data = $result['data'];

		// Robustness: If the AI returned a list with one item, pull it out.
		if ( isset( $data[0] ) && is_array( $data[0] ) && ! isset( $data['intent'] ) ) {
			$data = $data[0];
		}

		$intent = $data['intent'] ?? 'general_chat';
		$refresh = false;

		switch ( $intent ) {
			case 'plan_30_days':
				$active_campaign = VMSAI_Planner::active_campaign();
				if ( ! $active_campaign ) {
					return array( 'reply' => 'I cannot generate a plan without an active campaign. Please launch one in the Plan tab first.', 'refresh' => false );
				}

				if ( VMSAI_License::has_feature( 'unlimited_campaigns' ) ) {
					VMSAI_Planner::generate( (int) $active_campaign['id'], 30 );
				} else {
					return array( 'reply' => 'A 30-day plan is a PRO feature. Please upgrade to unlock full autonomy.', 'refresh' => false );
				}
				$refresh = true;
				break;
			case 'research_now':
				if ( VMSAI_License::has_feature( 'news_jacking' ) ) {
					( new VMSAI_RAG() )->sync();
				} else {
					return array( 'reply' => 'News-Jacking is a PRO feature.', 'refresh' => false );
				}
				$refresh = true;
				break;
			case 'promote_inventory':
				$this->promote_random_product();
				$refresh = true;
				break;
			case 'flash_sale':
				$this->run_flash_sale();
				$refresh = true;
				break;
			case 'promote_reviews':
				$this->promote_best_review();
				$refresh = true;
				break;
			case 'spy_competitors':
				$intel = VMSAI_Research::spy_competitors();
				if ( $intel ) {
					return array( 'reply' => "I have completed the reconnaissance. COMPETITOR MOVE: {$intel['move']}. OUR OPPORTUNITY: {$intel['gap']}. Logic: {$intel['logic']}", 'refresh' => false );
				}
				break;
		}

		return array( 'reply' => $data['reply'] ?? 'Acknowledged.', 'refresh' => $refresh );
	}

	/**
	 * Find a random product and inject a promotion into the plan.
	 */
	private function promote_random_product() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return;
		}

		$products = wc_get_products( array(
			'limit'   => 10,
			'status'  => 'publish',
			'orderby' => 'rand',
		) );

		if ( ! $products ) {
			return;
		}

		$product = $products[0];
		$price = $product->get_price();
		$currency = get_woocommerce_currency_symbol();
		$desc = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );

		$angle = sprintf(
			"PRODUCT PROMOTION: Price %s%s. Description: %s. URL: %s",
			$currency,
			$price,
			mb_substr( $desc, 0, 300 ),
			$product->get_permalink()
		);

		$this->inject_immediate_slot(
			sprintf( /* translators: %s: product name */ __( 'Featured Product: %s', 'vm-social-ai-pro' ), $product->get_name() ),
			$angle,
			'offer',
			$product->get_id()
		);
	}

	/**
	 * Run a high-urgency Flash Sale campaign for a specific product.
	 */
	public function run_flash_sale() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return;
		}

		// Look for products with high stock to "move" inventory.
		$products = wc_get_products( array(
			'limit'        => 5,
			'status'       => 'publish',
			'stock_status' => 'instock',
			'orderby'      => 'meta_value_num',
			'meta_key'     => '_stock',
			'order'        => 'DESC',
		) );

		if ( ! $products ) {
			// Fallback to regular random products if stock tracking is off.
			$products = wc_get_products( array( 'limit' => 1, 'orderby' => 'rand' ) );
		}

		if ( ! $products ) {
			return;
		}

		$product = $products[0];
		$price   = $product->get_price();
		$name    = $product->get_name();

		VMSAI_Logger::info( 'commander', "Launching Flash Sale for: '{$name}'." );

		// We inject 3 posts over the next 24 hours to create a "Sale Loop".
		$steps = array(
			array( 'time' => '+0 hours', 'hook' => 'The Flash Sale is ON. 24 hours only.' ),
			array( 'time' => '+12 hours', 'hook' => 'Halfway there. Stock is moving fast.' ),
			array( 'time' => '+22 hours', 'hook' => 'FINAL CALL. 2 hours remaining.' ),
		);

		foreach ( $steps as $step ) {
			$angle = sprintf(
				"FLASH SALE ALERT: %s. Price: %s. URL: %s. URGENCY: %s",
				$name,
				$price,
				$product->get_permalink(),
				$step['hook']
			);

			$this->inject_scheduled_slot(
				"FLASH SALE: " . $name,
				$angle,
				'offer',
				$product->get_id(),
				date( 'Y-m-d', strtotime( $step['time'] ) ),
				date( 'H:i:s', strtotime( $step['time'] ) )
			);
		}
	}

	/**
	 * Find a high-rating product review and showcase it.
	 */
	public function promote_best_review() {
		$args = array(
			'status' => 'approve',
			'type'   => 'review',
			'number' => 10,
		);

		$comments = get_comments( $args );

		// Filter for 5-star reviews specifically.
		$best = array();
		foreach ( $comments as $comment ) {
			$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
			if ( $rating >= 5 ) {
				$best[] = $comment;
			}
		}

		if ( ! $best ) {
			return;
		}

		$review = $best[ array_rand( $best ) ];
		$product_id = (int) $review->comment_post_ID;
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		$angle = sprintf(
			"CUSTOMER LOVE STORY: '%s' says: \"%s\". Featured Product: %s. Link: %s",
			$review->comment_author,
			mb_substr( wp_strip_all_tags( $review->comment_content ), 0, 250 ),
			$product ? $product->get_name() : get_the_title( $product_id ),
			get_permalink( $product_id )
		);

		VMSAI_Logger::info( 'commander', "Showcasing review from {$review->comment_author}." );

		$this->inject_immediate_slot(
			__( 'Customer Success Story', 'vm-social-ai-pro' ),
			$angle,
			'proof',
			$product_id
		);
	}

	/**
	 * Helper to inject a slot at a specific time.
	 */
	private function inject_scheduled_slot( $topic, $link, $pillar, $post_id, $date, $time ) {
		$campaign = VMSAI_Planner::active_campaign();
		if ( ! $campaign ) return;

		global $wpdb;
		$channels = (array) json_decode( (string) $campaign['channels'], true );

		foreach ( $channels as $channel ) {
			// A single ambiguous chat message can trigger this for every
			// channel at once (flash sales inject 3 slots x N channels) —
			// respect the same per-channel daily cap the regular planner
			// enforces, rather than silently committing unlimited real AI
			// generation cost on the strength of an LLM's own intent guess.
			if ( self::channel_slot_count_for_date( (int) $campaign['id'], $channel, $date ) >= self::per_channel_cap() ) {
				VMSAI_Logger::info( 'commander', sprintf( 'Skipped injecting into %s on %s — per-channel daily cap already reached.', $channel, $date ) );
				continue;
			}

			$wpdb->insert(
				VMSAI_Install::table( 'plan' ),
				array(
					'campaign_id' => (int) $campaign['id'],
					'post_id'     => (int) $post_id,
					'slot_date'   => $date,
					'slot_time'   => $time,
					'channel'     => $channel,
					'pillar'      => $pillar,
					'format'      => 'image',
					'topic'       => $topic,
					'angle'       => $link,
					'status'      => 'planned',
					'created_at'  => current_time( 'mysql', true ),
				)
			);
		}
	}

	/**
	 * The configured per-channel daily cap, same setting the regular
	 * planner respects.
	 *
	 * @return int
	 */
	private static function per_channel_cap() {
		return max( 1, (int) VMSAI_Settings::get( 'per_channel_cap', 2 ) );
	}

	/**
	 * How many plan slots a channel already has on a given date.
	 *
	 * @param int    $campaign_id Campaign id.
	 * @param string $channel     Channel slug.
	 * @param string $date        Y-m-d.
	 * @return int
	 */
	private static function channel_slot_count_for_date( $campaign_id, $channel, $date ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );

		return (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$table` WHERE campaign_id = %d AND channel = %s AND slot_date = %s", // phpcs:ignore
				$campaign_id,
				$channel,
				$date
			)
		);
	}

	/**
	 * If the calendar is looking empty, find site content that hasn't been
	 * shared yet and inject it.
	 */
	private function check_for_gaps() {
		$campaign = VMSAI_Planner::active_campaign();
		if ( ! $campaign ) {
			return;
		}

		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );
		$now   = current_time( 'Y-m-d' );

		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `$table` WHERE campaign_id = %d AND slot_date >= %s",
			(int) $campaign['id'],
			$now
		) );

		if ( $count < 5 ) {
			VMSAI_Logger::info( 'commander', 'Calendar gap detected. Scouting site for unshared content...' );
			$this->inject_unshared_content();
		}
	}

	/**
	 * Find a blog post or product that has never reached the social queue.
	 */
	private function inject_unshared_content() {
		global $wpdb;
		$queue_table = VMSAI_Install::table( 'queue' );

		$posts = get_posts( array(
			'post_type'   => array( 'post', 'product' ),
			'post_status' => 'publish',
			'numberposts' => 10,
		) );

		foreach ( $posts as $post ) {
			// Check if this URL exists in the queue link column.
			$link = get_permalink( $post->ID );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `$queue_table` WHERE link LIKE %s LIMIT 1", '%' . $wpdb->esc_like( $link ) . '%' ) );

			if ( ! $exists ) {
				VMSAI_Logger::info( 'commander', "Found unshared content: '{$post->post_title}'. Injecting into plan." );
				$this->inject_immediate_slot( $post->post_title, $link, 'value', $post->ID );
				break; // Only inject one per review pass.
			}
		}
	}

	/**
	 * Find a high-performing post and rewrite it for a fresh audience.
	 */
	private function recycle_winners() {
		$winners = VMSAI_Brain::top_performers_data( 1 );
		if ( ! $winners ) {
			return;
		}

		$winner = $winners[0];

		// Probability check to avoid over-recycling (10% chance per pass).
		if ( wp_rand( 1, 100 ) > 10 ) {
			return;
		}

		VMSAI_Logger::info( 'commander', "Recycling top-performing post: '{$winner['title']}' for extra reach." );

		$this->inject_immediate_slot(
			"REVISITED: " . $winner['title'],
			'',
			'hook',
			(int) $winner['post_id']
		);
	}

	/**
	 * Helper to put a slot in for today.
	 */
	private function inject_immediate_slot( $topic, $link, $pillar = 'value', $post_id = 0 ) {
		$campaign = VMSAI_Planner::active_campaign();
		if ( ! $campaign ) return;

		global $wpdb;
		$channels = (array) json_decode( (string) $campaign['channels'], true );
		if ( ! $channels ) {
			VMSAI_Logger::warn( 'commander', 'Skipped injecting slot: campaign has no channels.' );
			return;
		}
		$channel  = $channels[ array_rand( $channels ) ];
		$today    = current_time( 'Y-m-d' );

		if ( self::channel_slot_count_for_date( (int) $campaign['id'], $channel, $today ) >= self::per_channel_cap() ) {
			VMSAI_Logger::info( 'commander', sprintf( 'Skipped injecting into %s today — per-channel daily cap already reached.', $channel ) );
			return;
		}

		$wpdb->insert(
			VMSAI_Install::table( 'plan' ),
			array(
				'campaign_id' => (int) $campaign['id'],
				'post_id'     => (int) $post_id,
				'slot_date'   => $today,
				'slot_time'   => current_time( 'H:i:s' ),
				'channel'     => $channel,
				'pillar'      => $pillar,
				'format'      => 'image',
				'topic'       => $topic,
				'angle'       => $link,
				'status'      => 'planned',
				'created_at'  => current_time( 'mysql', true ),
			)
		);
	}
}
