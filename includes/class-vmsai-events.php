<?php
/**
 * Proof-of-life event listener.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Listens for site activity (new posts, products) and injects them into the
 * AI planning cycle immediately.
 */
class VMSAI_Events {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'publish_post', array( $this, 'on_publish' ), 10, 2 );
		add_action( 'woocommerce_new_product', array( $this, 'on_new_product' ), 10, 1 );

		// SCARCITY TRIGGER: Monitor stock levels
		add_action( 'woocommerce_product_set_stock', array( $this, 'on_stock_change' ) );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_stock_change' ) );
	}

	/**
	 * Trigger on stock reduction to catch scarcity moments.
	 */
	public function on_stock_change( $product ) {
		if ( ! is_object( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product || 'publish' !== $product->get_status() ) return;

		$stock = $product->get_stock_quantity();
		$threshold = (int) apply_filters( 'vmsai_scarcity_threshold', 5 );

		// Only trigger if we just hit the scarcity zone
		if ( $stock > 0 && $stock <= $threshold ) {
			$last_trigger = get_post_meta( $product->get_id(), '_vmsai_last_scarcity_at', true );

			// Avoid spamming (max once per week per product)
			if ( $last_trigger && ( time() - $last_trigger ) < WEEK_IN_SECONDS ) return;

			$angle = sprintf(
				"SCARCITY ALERT: Only %d remaining in stock. Product: %s. Price: %s. URL: %s. URGENCY: High.",
				$stock,
				$product->get_name(),
				$product->get_price(),
				$product->get_permalink()
			);

			$this->inject_event(
				sprintf( /* translators: %s: product name */ __( 'Low Stock Alert: %s', 'vm-social-ai-pro' ), $product->get_name() ),
				$angle,
				'offer',
				$product->get_id()
			);

			update_post_meta( $product->get_id(), '_vmsai_last_scarcity_at', time() );
			VMSAI_Logger::info( 'events', "Scarcity trigger activated for {$product->get_name()} (Stock: {$stock})." );
		}
	}

	/**
	 * Trigger on a new blog post.
	 *
	 * @param int     $id   Post ID.
	 * @param WP_Post $post Post object.
	 */
	public function on_publish( $id, $post ) {
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// Get a clean snippet of the post content to help the AI write a better caption.
		$snippet = wp_trim_words( wp_strip_all_tags( $post->post_content ), 150 );

		$this->inject_event(
			sprintf( /* translators: %s: blog post title */ __( 'New blog post: %s', 'vm-social-ai-pro' ), $post->post_title ),
			get_permalink( $id ),
			'value', // must be one of VMSAI_Planner::pillars() — 'education' does not exist
			$id,
			$snippet
		);
	}

	/**
	 * Trigger on a new product.
	 *
	 * @param int $id Product ID.
	 */
	public function on_new_product( $id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product = wc_get_product( $id );
		if ( ! $product ) {
			return;
		}

		$price = $product->get_price();
		$currency = get_woocommerce_currency_symbol();
		$desc = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );

		$angle = sprintf(
			"PRODUCT DETAIL: Price %s%s. Description: %s. URL: %s",
			$currency,
			$price,
			mb_substr( $desc, 0, 300 ),
			$product->get_permalink()
		);

		$this->inject_event(
			sprintf( /* translators: %s: product name */ __( 'New product launch: %s', 'vm-social-ai-pro' ), $product->get_name() ),
			$angle,
			'offer',
			$id
		);
	}

	/**
	 * Insert an immediate slot into the active campaign.
	 *
	 * @param string $topic   What happened.
	 * @param string $link    Source URL.
	 * @param string $pillar  Category.
	 * @param int    $post_id Originating ID.
	 * @param string $snippet Clean snippet of content.
	 */
	private function inject_event( $topic, $link, $pillar, $post_id = 0, $snippet = '' ) {
		$campaign = VMSAI_Planner::active_campaign();
		if ( ! $campaign ) {
			return;
		}

		$seo_keywords = VMSAI_SEO::get_keywords( $post_id );
		$brief_context = $snippet ? "\nCONTENT SNIPPET: " . $snippet : '';

		// Pro Feature: VM SEO Brain Context Sync.
		// Pull the original editorial brief to give the social AI deep understanding.
		if ( $post_id && class_exists( 'VMSB_Core' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'vmsb_plan';
			$plan_row = $wpdb->get_row( $wpdb->prepare( "SELECT brief, intent FROM `$table` WHERE post_id = %d LIMIT 1", $post_id ) );
			if ( $plan_row ) {
				$brief_context = "\nSEO BRIEF: " . $plan_row->brief;
				if ( ! empty( $plan_row->intent ) ) {
					$brief_context .= "\nSEARCH INTENT: " . $plan_row->intent;
				}
			}
		}

		global $wpdb;
		$channels = (array) json_decode( (string) $campaign['channels'], true );

		foreach ( $channels as $channel ) {
			$wpdb->insert(
				VMSAI_Install::table( 'plan' ),
				array(
					'campaign_id' => (int) $campaign['id'],
					'post_id'     => (int) $post_id,
					'slot_date'   => current_time( 'Y-m-d' ),
					'slot_time'   => current_time( 'H:i:s' ),
					'channel'     => $channel,
					'pillar'      => $pillar,
					'format'      => 'image',
					'topic'       => $topic,
					'keyword'     => $seo_keywords,
					'angle'       => $link . $brief_context,
					'status'      => 'planned',
					'created_at'  => current_time( 'mysql', true ),
				)
			);
		}

		VMSAI_Logger::info( 'events', 'Site activity detected; injected new slots into the plan.', array( 'topic' => $topic ) );
	}
}
