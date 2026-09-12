<?php
/**
 * Content composer.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns one plan slot into a finished post: copy written to the channel's
 * native shape, an SEO keyword worked in naturally, hashtags, a call to
 * action, a tracked link, and a generated image with alt text.
 */
class VMSAI_Composer {

	/**
	 * Hard limits and house rules per channel.
	 *
	 * @return array
	 */
	public static function specs() {
		return array(
			'facebook'  => array(
				'limit'    => 2000,
				'sweet'    => 480,
				'hashtags' => 3,
				'brief'    => 'Conversational and specific. Open with a line that works as a standalone hook because Facebook truncates after roughly two lines. Short paragraphs, plenty of white space.',
			),
			'instagram' => array(
				'limit'    => 2200,
				'sweet'    => 700,
				'hashtags' => 12,
				'brief'    => 'The first sentence is the whole game — it is all anyone sees before "more". Warm, visual, personal. Line breaks between thoughts. Hashtags on their own block at the end.',
			),
			'x'         => array(
				'limit'    => 280,
				'sweet'    => 240,
				'hashtags' => 2,
				'brief'    => 'One idea, said tightly. No throat-clearing, no "Thread 🧵" unless it truly is one. Cut every adjective that is not carrying weight.',
			),
			'linkedin'  => array(
				'limit'    => 3000,
				'sweet'    => 900,
				'hashtags' => 4,
				'brief'    => 'Professional but human. Lead with a specific observation or result, not a platitude. Single-line paragraphs. End with a question that invites a considered reply.',
			),
			'gbp'       => array(
				'limit'    => 1500,
				'sweet'    => 260,
				'hashtags' => 0,
				'brief'    => 'This is a local search surface, not a feed. Plain, factual, useful. Name the city and service explicitly. No hashtags, no emoji clutter.',
			),
			'youtube'   => array(
				'limit'    => 1000,
				'sweet'    => 320,
				'hashtags' => 3,
				'brief'    => 'Write a Shorts title under 60 characters plus a description. The title carries the search weight — front-load the keyword.',
			),
			'threads'   => array(
				'limit'    => 500,
				'sweet'    => 380,
				'hashtags' => 3,
				'brief'    => 'Conversational and quick. Threads rewards replies, so end with a genuine question or a take people can react to. No link walls.',
			),
			'bluesky'   => array(
				'limit'    => 300,
				'sweet'    => 260,
				'hashtags' => 2,
				'brief'    => 'Tight and text-first — the hard limit is 300 characters. One idea, one link, hashtags sparingly at the end.',
			),
			'tiktok'    => array(
				'limit'    => 2200,
				'sweet'    => 150,
				'hashtags' => 5,
				'brief'    => 'This caption rides a video, so hook in the first 40 characters — that is all viewers see before the video takes over. Short, punchy, trend-aware. Hashtags at the end.',
			),
			'telegram'  => array(
				'limit'    => 4096,
				'sweet'    => 900,
				'hashtags' => 3,
				'brief'    => 'A broadcast channel, not a feed. Structured and skimmable: bold key lines, short paragraphs, one clear call to action. Use **bold** markers for emphasis.',
			),
			'pinterest' => array(
				'limit'    => 500,
				'sweet'    => 300,
				'hashtags' => 3,
				'brief'    => 'This is search, not social. Front-load the keywords someone would actually type, describe what the pin shows, and keep hashtags minimal at the end.',
			),
		);
	}

	/**
	 * Compose a post from a plan row.
	 *
	 * @param array $slot Plan row.
	 * @return array{ok:bool,queue_id:int,error:string}
	 */
	public static function compose( array $slot ) {
		// Resilience: Increase resources for this heavy process.
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		// Enforce Plan Limits before spending tokens.
		$allowance = VMSAI_Usage::check_allowance();
		if ( ! $allowance['ok'] ) {
			return array( 'ok' => false, 'queue_id' => 0, 'error' => $allowance['message'] );
		}

		// Feature Enforcement: Carousel and Video
		if ( 'carousel' === $slot['format'] && ! VMSAI_License::has_feature( 'carousel' ) ) {
			$slot['format'] = 'image';
		}

		$channel = (string) $slot['channel'];
		$specs   = self::specs();
		$spec    = $specs[ $channel ] ?? $specs['facebook'];
		$pillars = VMSAI_Planner::pillars();
		$pillar  = $pillars[ $slot['pillar'] ] ?? $pillars['value'];

		// AGENT ROUTING: the Agent Feed already picked a specific agent for
		// its own slots (plan.agent_id); everything else (evergreen Plan,
		// Campaign Planner) gets a sensible default routed from its pillar.
		if ( ! empty( $slot['agent_id'] ) ) {
			$agent = VMSAI_Agents::get( (int) $slot['agent_id'] );
		} else {
			$agent = VMSAI_Agents::get( VMSAI_Agents::route( $slot['pillar'] ) );
		}

		// Ad Template Selection for Videos: Default to agent-mapped style
		$video_template = ( ! empty($slot['video_template']) ) ? $slot['video_template'] : VMSAI_Video_Engine::map_style_to_template( $agent['image_style'] );

		$system = $agent['persona'] . ' You are an Elite Agency-Grade Social Media Architect. Your mission is to stop the scroll and drive measurable business ROI.

KEY WRITING ATTRIBUTES:
- AUTHORITATIVE: Use strong, confident statements. No passive voice.
- FORMATTING: Use structural formatting: a bold hook (using UNICODE PSEUDO-BOLD like 🚀 𝐇𝐎Ｏ𝐊), bullet points, and plenty of white space.
- HOOK-FIRST: The first sentence must be a high-impact pattern interrupt.
- EMOTIONAL TRIGGERS: Focus on the "Cost of Inaction" or the "Elite Result".
- EMOJIS: Use premium, relevant emojis to guide the eye, but keep it classy.';

		// TACTICAL AWARENESS: Adjust tone for News-Jacking or New Content
		$is_news = ( strpos( (string) $slot['angle'], 'TACTICAL NEWS-JACKING' ) !== false );
		$is_new_content = ( strpos( (string) $slot['topic'], 'New blog post:' ) !== false );

		if ( $is_news ) {
			$system = 'You are a Real-Time Industry Journalist and Growth Hacker. Your mission is to respond to a breaking trend with high urgency, authority, and professional relevance. Make the brand look like a leading voice in the conversation. Use a "Breaking News" tone.';
		} elseif ( $is_new_content ) {
			$system = 'You are a Viral Content Promoter and Social Strategist. Your mission is to create massive hype for a newly published expert resource. Use curiosity loops and specific value-teasers to drive traffic from the social feed to the website.';
		}

		// ... (keep the overrides logic)
		$overrides = VMSAI_Prompts::get_overrides( (int) ( $slot['post_id'] ?? 0 ) );
		$extra_instructions = '';

		if ( ! empty( $overrides ) ) {
			$extra_instructions = "\nCONTEXT OVERRIDES FOR THIS CONTENT TYPE:\n";
			if ( ! empty( $overrides['tone'] ) ) $extra_instructions .= "- Use this TONE instead: " . $overrides['tone'] . "\n";
			if ( ! empty( $overrides['cta'] ) )  $extra_instructions .= "- Use this CTA instead: " . $overrides['cta'] . "\n";
			if ( ! empty( $overrides['instructions'] ) ) $extra_instructions .= "- " . $overrides['instructions'] . "\n";
		}

		// VISUAL DNA: Apply the Agent's specific visual style and Brand Kit rules
		$brand_kit = array(
			'primary'   => VMSAI_Brain::get('brand_color_1'),
			'secondary' => VMSAI_Brain::get('brand_color_2'),
			'fonts'     => VMSAI_Brain::get('brand_fonts'),
			'vibe'      => VMSAI_Brain::get('visual_reference'),
		);

		$image_style = "Style: {$agent['visual']}. ";
		if ( ! empty($brand_kit['primary']) ) $image_style .= "USE BRAND COLORS: {$brand_kit['primary']} and {$brand_kit['secondary']}. ";
		if ( ! empty($brand_kit['fonts']) )   $image_style .= "TYPOGRAPHY: {$brand_kit['fonts']}. ";
		if ( ! empty($brand_kit['vibe']) )    $image_style .= "OVERALL AESTHETIC: {$brand_kit['vibe']}. ";
		$image_style .= "No literal text unless infographic or banner format.";

		if ( 'banner' === $slot['format'] ) {
			$image_style = 'Style: High-end promotional graphic design. Bold typography for the main headline, dynamic layout, premium brand colors, clean professional graphics. Include the core offer or headline visually.';
		} elseif ( 'infographic' === $slot['format'] ) {
			$image_style = 'Style: Professional educational infographic. Modern flat vector illustration, 3-4 distinct visual sections or steps, clean iconography, minimal text labels, organized visual hierarchy. Use a premium aesthetic.';
		}

		$prompt = VMSAI_Brain::context( $channel ) . "\n\n"
			. "MISSION: WRITE ONE ELITE SOCIAL POST.\n"
			. ( $is_new_content ? "SPECIAL CONTEXT: This is a PROMOTIONAL post for a brand new blog article. Do NOT just summarize it; tease the most valuable insight to create a 'Need to Know' gap.\n" : "" )
			. "Channel: {$channel}\n"
			. "Technical Specs: Character limit {$spec['limit']}, Sweet spot {$spec['sweet']} chars.\n"
			. "Pillar: {$slot['pillar']} — {$pillar['brief']}\n"
			. "Topic: {$slot['topic']}\n"
			. "Strategic Angle: {$slot['angle']}\n"
			. "Target SEO Keyword: {$slot['keyword']}\n"
			. 'Format: ' . $slot['format'] . "\n"
			. $extra_instructions . "\n\n"
			. "REQUIRED JSON RESPONSE STRUCTURE:\n"
			. "  title          — internal label, under 70 characters.\n"
			. "  body           — high-impact elite post copy. Use structural formatting: a bold hook, 3-5 bullet points for benefits/features, and clear paragraphs. Use UNICODE PSEUDO-BOLD (e.g., 🚀 𝐇Ｏ𝐎𝐊) for emphasis — NEVER use Markdown asterisks (**text**).\n"
			. "  video_hook     — 3-5 words in ALL CAPS for a video overlay (e.g. 'STOP WASTING MONEY').\n"
			. "  hashtags       — exactly {$spec['hashtags']} hyper-relevant hashtags.\n"
			. "  cta            — compelling, specific call to action using a lead-in emoji (e.g. 📩 **Ready to grow?**).\n"
			. "  image_prompt   — VISUAL DESIGN BRIEF. If format is 'image', provide a literal commercial photo prompt starting with the subject. If format is 'banner' or 'infographic', describe a high-end graphic design layout: mention specific text/headlines to include, background colors, and icon placement. Use: {$image_style}\n"
			. "  alt_text       — descriptive, SEO-optimized alt text.\n"
			. "  viral_score    — 0-100 score.\n"
			. "  seo_score      — 0-100 score.\n\n"
			. "CRITICAL WRITING RULES:\n"
			. "- THE HOOK: Must be a bold, high-energy statement that stops the scroll immediately.\n"
			. "- THE BODY: Use technical authority. Don't just say 'we bake bread', say '48-hour fermented artisanal sourdough that redefines your palate'.\n"
			. "- LISTS: Use bullet points (✨, ⚡, 🔍) to break down value.\n"
			. "- THE CTA: Must be strong, urgent, and lead with a DM or Link instruction.\n"
			. "- NO AI FLUFF: Never use 'In today\'s fast-paced world' or 'Are you looking to...'. Get straight to the value.\n"
			. "- THE IMAGE PROMPT must be a LITERAL representation of the subject. If the post is about 'Technical SEO', visualize 'A professional office setup with a high-end monitor showing search data'. Avoid generic 'concepts' — choose a concrete scene.\n"
			. "- Use short, punchy sentences. High readability.\n"
			. "- NEVER mention you are an AI or describe the subject neutrally — speak as the business owner/expert.\n"
			. '- Emoji density: ' . VMSAI_Settings::get( 'emoji_density', 'light' ) . ".\n";

		// ... (keep the language logic)
		$lang    = sanitize_text_field( $slot['language'] ?? VMSAI_Settings::get( 'language', 'en' ) );
		$flavour = sanitize_text_field( $slot['locale_flavour'] ?? VMSAI_Settings::get( 'locale_flavour', '' ) );
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

		$prompt .= $lang_rules;

		// VISION AUGMENTATION: If we are using an existing image (Featured Image),
		// let the AI "see" it to write a better caption.
		$gen_args = array(
			'max_tokens'    => 2000,
			'temperature'   => 0.7,
			'persona'       => 'wordsmith',
			'quality_check' => true
		);

		$post_id = (int) ( $slot['post_id'] ?? 0 );
		if ( $post_id && has_post_thumbnail( $post_id ) ) {
			$img_path = get_attached_file( get_post_thumbnail_id( $post_id ) );
			if ( $img_path && file_exists( $img_path ) ) {
				$gen_args['image_binary'] = file_get_contents( $img_path );
				$gen_args['image_mime']   = get_post_mime_type( get_post_thumbnail_id( $post_id ) );
				$prompt .= "\n\nCRITICAL: I have attached the actual image for this post. DESCRIBE exactly what you see in the image and weave those specific details into your caption to make it authentic.";
			}
		}

		VMSAI_Logger::debug( 'composer', "Starting composition for slot #{$slot['id']}" );

		// ENHANCED GENERATION: Trigger Persona and Quality Check
		$result = vmsai()->text_engine()->generate_json( $system, $prompt, $gen_args );

		if ( empty( $result['ok'] ) ) {
			VMSAI_Logger::warn( 'composer', "Text generation failed for slot #{$slot['id']}: {$result['error']}" );
			return array( 'ok' => false, 'queue_id' => 0, 'error' => $result['error'] );
		}

		$data = $result['data'];
		VMSAI_Logger::debug( 'composer', "Text generated successfully for slot #{$slot['id']}" );

		// CAROUSEL HANDLER: Generate slides if format is carousel.
		if ( 'carousel' === $slot['format'] ) {
			return self::compose_carousel( $slot, $data, $spec );
		}

		// Pro Feature: Viral Potential Safety Net.
		// If the score is low, attempt one high-impact rewrite pass.
		if ( VMSAI_License::has_feature( 'viral_rescoring' ) && (int) ( $data['viral_score'] ?? 0 ) < 70 ) {
			$viral_system = 'You are a Viral Growth Hacker. Your mission is to maximize reach and "Viral Velocity".';
			$viral_prompt = "Original Draft:\n" . ( $data['body'] ?? '' ) . "\n\n"
				. "Task: Optimize the 'Curiosity Loop' and 'Retention Triggers'. "
				. "1. Rewrite the Hook to be a pattern-interrupt.\n"
				. "2. Add 'Save this' or 'Tag a friend' triggers where they feel natural.\n"
				. "3. Ensure the CTA creates FOMO.\n\n"
				. "Return ONLY JSON with the updated body and viral_score (target 85+).";

			$viral_result = vmsai()->text_engine()->generate_json( $viral_system, $viral_prompt, array( 'temperature' => 0.95 ) );
			if ( ! empty( $viral_result['ok'] ) ) {
				$data['body'] = $viral_result['data']['body'] ?? $data['body'];
				$data['viral_score'] = $viral_result['data']['viral_score'] ?? 85;
			}
		}

		// Robustness: If the AI returned a list with one item, pull it out.
		if ( isset( $data[0] ) && is_array( $data[0] ) && ! isset( $data['body'] ) ) {
			$data = $data[0];
		}

		// Pro Feature: Automatic A/B Testing with Reinforcement Learning.
		// Favors the winning variant if a 20% lead is detected.
		$variant = 'a';
		if ( wp_rand( 1, 100 ) <= 20 ) {
			$winner = VMSAI_Analytics::get_winning_variant( $channel );
			$use_b = false;

			if ( 'b' === $winner ) {
				$use_b = ( wp_rand( 1, 100 ) <= 80 ); // B is winning! Use B 80% of the time.
			} elseif ( 'a' === $winner ) {
				$use_b = ( wp_rand( 1, 100 ) > 80 );  // A is winning! Use B only 20% of the time.
			} else {
				$use_b = ( wp_rand( 1, 100 ) > 50 );  // Balanced/No data. 50/50 split.
			}

			if ( $use_b ) {
				$variant_prompt = $prompt . "\n\nVariant B Task: Rewrite the post copy with a completely different hook (e.g. if the first was a question, make this a bold statement). Keep all other JSON keys the same.";
				$variant_result = vmsai()->text_engine()->generate_json( $system, $variant_prompt, array( 'max_tokens' => 2000, 'temperature' => 0.95 ) );
				if ( ! empty( $variant_result['ok'] ) ) {
					$data = $variant_result['data'];
					$variant = 'b';
				}
			}
		}

		$body = trim( (string) ( $data['body'] ?? '' ) );

		if ( '' === $body ) {
			return array( 'ok' => false, 'queue_id' => 0, 'error' => __( 'The composer returned empty copy.', 'vm-social-ai-pro' ) );
		}

		// Run the critic before spending an image generation on weak copy.
		$critique = VMSAI_Critic::review( $data, $slot, $spec );

		if ( ! empty( $critique['rewrite'] ) ) {
			$body                = $critique['rewrite'];
			$data['body']        = $body;
		}

		if ( ! empty( $critique['image_prompt_rewrite'] ) ) {
			$data['image_prompt'] = $critique['image_prompt_rewrite'];
		}

		$hashtags = self::normalise_hashtags( $data['hashtags'] ?? array(), (int) $spec['hashtags'] );
		$cta      = trim( (string) ( $data['cta'] ?? '' ) );

		// Quality gate: the model can return syntactically valid JSON that
		// is still missing the fields that matter. Retry once, cheaply,
		// for just the missing pieces rather than silently saving gaps.
		$needs_hashtags = ! $hashtags && (int) $spec['hashtags'] > 0;
		$needs_cta      = '' === $cta;

		if ( $needs_hashtags || $needs_cta ) {
			$fix_prompt = "This finished social post is missing required metadata:\n\nPOST BODY:\n{$body}\n\n"
				. VMSAI_Brain::context( $channel ) . "\n\n"
				. "Return ONLY JSON with:\n"
				. ( $needs_hashtags ? "  hashtags — exactly {$spec['hashtags']} hyper-relevant hashtags for this specific post.\n" : '' )
				. ( $needs_cta ? "  cta — one specific, compelling call to action grounded in the business's actual offers or preferred CTAs above. Never a generic \"learn more\".\n" : '' );

			$fix = vmsai()->text_engine()->generate_json(
				'You fill in missing metadata for a finished social post. Be specific and grounded in the business context given, never generic.',
				$fix_prompt,
				array( 'max_tokens' => 300, 'temperature' => 0.6 )
			);

			if ( ! empty( $fix['ok'] ) ) {
				if ( $needs_hashtags && ! empty( $fix['data']['hashtags'] ) ) {
					$hashtags = self::normalise_hashtags( $fix['data']['hashtags'], (int) $spec['hashtags'] );
				}
				if ( $needs_cta && ! empty( $fix['data']['cta'] ) ) {
					$cta = trim( (string) $fix['data']['cta'] );
				}
			}
		}

		// Last resort: ground the CTA in the business's own CTA library
		// rather than ever saving an empty call to action.
		if ( '' === $cta ) {
			$cta = self::fallback_cta();
		}

		$body = self::enforce_limit( $body, (int) $spec['limit'], $hashtags, $channel );

		// Pro Feature: Smart Media Selection.
		// If promoting a specific product/post and it has a featured image, use it
		// instead of generating a generic AI image for better selling context.
		$image = array();
		$use_existing = false;
		$post_id = (int) ( $slot['post_id'] ?? 0 );

		if ( $post_id && has_post_thumbnail( $post_id ) ) {
			$thumb_id = get_post_thumbnail_id( $post_id );
			$image = array(
				'attachment_id' => $thumb_id,
				'url'           => wp_get_attachment_url( $thumb_id ),
				'provider'      => 'original',
				'alt'           => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ?: $slot['topic'],
			);
			$use_existing = true;
		}

		if ( ! $use_existing ) {
			VMSAI_Logger::debug( 'composer', "Generating image for slot #{$slot['id']}..." );
			$image = vmsai()->image_engine()->create(
				(string) ( $data['image_prompt'] ?? $slot['topic'] ),
				array(
					'channel' => $channel,
					'format'  => (string) $slot['format'],
					'topic'   => $slot['topic'],
					'keyword' => $slot['keyword'],
					'alt'     => $data['alt_text'] ?? '',
					'prompt'  => $data['image_prompt'] ?? '',
				)
			);
			VMSAI_Logger::debug( 'composer', "Image generation complete for slot #{$slot['id']} (Status: " . ( $image['ok'] ? 'OK' : 'Failed' ) . ")" );
		}

		// Final Polish: Merge for visibility and editing.
		// We store the full formatted post in 'body' for the War Room view,
		// while keeping the metadata columns for channel-specific API features.
		$full_body = $body;
		if ( $cta ) {
			$full_body .= "\n\n" . $cta;
		}
		if ( ! empty($hashtags) ) {
			$full_body .= "\n\n" . implode( ' ', array_map( fn( $h ) => '#' . $h, $hashtags ) );
		}

		// SHOPPABLE METADATA: Link Woo product details if this is a product-led post
		$product_data = array();
		if ( $post_id && function_exists('wc_get_product') ) {
			$product = wc_get_product($post_id);
			if ( $product ) {
				$product_data = array(
					'id'    => $product->get_id(),
					'sku'   => $product->get_sku(),
					'price' => $product->get_price(),
					'url'   => $product->get_permalink(),
					'currency' => get_woocommerce_currency()
				);
			}
		}

		$queue_id = self::save(
			array(
				'plan_id'        => (int) $slot['id'],
				'campaign_id'    => (int) $slot['campaign_id'],
				'channel'        => $channel,
				'format'         => (string) $slot['format'],
				'title'          => (string) ( $data['title'] ?? $slot['topic'] ),
				'body'           => $full_body,
				'hashtags'       => implode( ' ', array_map( fn( $h ) => '#' . $h, $hashtags ) ),
				'cta'            => $cta,
				'link'           => self::tracked_link( $slot ),
				'media_id'       => (int) ( $image['attachment_id'] ?? 0 ),
				'media_url'      => (string) ( $image['url'] ?? '' ),
				'image_prompt'   => (string) ( $data['image_prompt'] ?? '' ),
				'alt_text'       => (string) ( $image['alt'] ?? ( $data['alt_text'] ?? '' ) ),
				'seo_score'      => min( 100, max( 0, (int) ( $data['seo_score'] ?? 0 ) ) ),
				'viral_score'    => min( 100, max( 0, (int) ( $data['viral_score'] ?? 0 ) ) ),
				'critic_notes'   => (string) ( $critique['notes'] ?? '' ),
				'variant'        => $variant,
				'video_hook'     => (string) ( $data['video_hook'] ?? '' ),
				'video_template' => $video_template,
				'product_data'   => ! empty($product_data) ? wp_json_encode($product_data) : null,
				'text_provider'  => (string) $result['provider'],
				'image_provider' => (string) ( $image['provider'] ?? '' ),
				'scheduled_at'   => self::slot_timestamp( $slot ),
				'status'         => self::initial_status( $channel ),
			)
		);

		if ( ! $queue_id ) {
			return array( 'ok' => false, 'queue_id' => 0, 'error' => __( 'Could not save the composed post.', 'vm-social-ai-pro' ) );
		}

		global $wpdb;
		$wpdb->update( // phpcs:ignore
			VMSAI_Install::table( 'plan' ),
			array( 'status' => 'composed', 'queue_id' => $queue_id, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $slot['id'] ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		VMSAI_Logger::info(
			'composer',
			sprintf( 'Composed %s post #%d.', $channel, $queue_id ),
			array( 'text' => $result['provider'], 'image' => $image['provider'] ?? 'none' )
		);

		// WORLD CLASS ALERT: Telegram Post Review
		if ( 'draft' === self::initial_status( $channel ) ) {
			$alert_msg = "💎 <b>New Draft Ready for Review</b>\n"
				. "Channel: " . strtoupper( $channel ) . "\n"
				. "Topic: " . $slot['topic'] . "\n\n"
				. "Click below to manage:";

			$kb = array(
				array(
					array( 'text' => '✅ Approve Now', 'callback_data' => 'approve:' . $queue_id ),
					array( 'text' => '❌ Reject', 'callback_data' => 'reject:' . $queue_id ),
				),
				array(
					array( 'text' => '✍️ Open War Room', 'url' => admin_url( 'admin.php?page=vm-social-ai-pro&tab=queue' ) ),
				)
			);

			VMSAI_Telegram_Bot::alert( $alert_msg, $kb );
		}

		return array( 'ok' => true, 'queue_id' => $queue_id, 'error' => '' );
	}

	/**
	 * Compose a carousel post with multiple slides.
	 */
	private static function compose_carousel( array $slot, array $data, array $spec ) {
		$system = 'You are a Social Media Storyteller. You turn complex topics into highly engaging 5-slide carousels. Each slide must have its own concise copy and image prompt.';

		$prompt = VMSAI_Brain::context() . "\n\n"
			. "CREATE A 5-SLIDE CAROUSEL FOR: {$slot['topic']}\n"
			. "Strategic Angle: {$slot['angle']}\n\n"
			. "STRUCTURE:\n"
			. "Slide 1: THE HOOK. Must stop the scroll.\n"
			. "Slide 2-4: THE VALUE. Three key points or steps.\n"
			. "Slide 5: THE CTA. What to do next.\n\n"
			. "Return ONLY JSON: {\"slides\": [{\"body\":\"...\", \"image_prompt\":\"...\"}, ...]}";

		$result = vmsai()->text_engine()->generate_json( $system, $prompt, array( 'max_tokens' => 2000 ) );

		if ( empty($result['ok']) ) {
			return array( 'ok' => false, 'queue_id' => 0, 'error' => $result['error'] );
		}

		$slides = $result['data']['slides'] ?? array();
		$parent_id = 0;

		foreach ( $slides as $i => $slide ) {
			$image = vmsai()->image_engine()->create( $slide['image_prompt'], array( 'topic' => $slot['topic'] . " Slide " . ($i+1) ) );

			$row = array(
				'plan_id'        => (int) $slot['id'],
				'parent_id'      => $parent_id,
				'campaign_id'    => (int) $slot['campaign_id'],
				'channel'        => $slot['channel'],
				'format'         => 'carousel_slide',
				'title'          => "Slide " . ($i+1),
				'body'           => $slide['body'],
				'media_id'       => (int) ( $image['attachment_id'] ?? 0 ),
				'media_url'      => $image['url'] ?? '',
				'image_prompt'   => $slide['image_prompt'],
				'text_provider'  => $result['provider'],
				'image_provider' => $image['provider'] ?? '',
				'status'         => 'approved',
				'scheduled_at'   => self::slot_timestamp( $slot ),
			);

			$id = self::save( $row );
			if ( $i === 0 ) {
				$parent_id = $id;
				// Update the parent's body to include hashtags/cta from original data if needed
				$full_body = $slide['body'] . "\n\n" . ( $data['cta'] ?? '' ) . "\n\n" . implode(' ', array_map(fn($h) => '#'.$h, self::normalise_hashtags($data['hashtags'] ?? [], 5)));
				global $wpdb;
				$wpdb->update( VMSAI_Install::table('queue'), array('body' => $full_body), array('id' => $id) );
			}
		}

		return array( 'ok' => true, 'queue_id' => $parent_id, 'error' => '' );
	}

	/**
	 * Insert a queue row.
	 *
	 * @param array $row Row data.
	 * @return int
	 */
	public static function save( array $row ) {
		global $wpdb;

		$row['created_at'] = current_time( 'mysql', true );
		$row['updated_at'] = $row['created_at'];

		$wpdb->insert( VMSAI_Install::table( 'queue' ), $row ); // phpcs:ignore

		return (int) $wpdb->insert_id;
	}

	/**
	 * Posts go straight to approved unless the channel requires a human look.
	 *
	 * @param string $channel Channel slug.
	 * @return string
	 */
	private static function initial_status( $channel ) {
		$autonomy = VMSAI_Settings::get( 'autonomy', 'assisted' );

		if ( 'manual' === $autonomy ) {
			return 'draft';
		}

		if ( 'full' === $autonomy ) {
			return 'approved';
		}

		$requires = (array) VMSAI_Settings::get( 'require_approval', array() );
		return empty( $requires[ $channel ] ) ? 'approved' : 'draft';
	}

	/**
	 * Pick a CTA from the Brain's own library when the model returns none,
	 * rather than ever saving a post with an empty call to action.
	 *
	 * @return string
	 */
	private static function fallback_cta() {
		$library = (string) VMSAI_Brain::get( 'cta_library' );
		$lines   = array_values( array_filter( array_map( 'trim', explode( "\n", $library ) ) ) );

		if ( $lines ) {
			return $lines[ array_rand( $lines ) ];
		}

		return __( 'Learn more — link in bio.', 'vm-social-ai-pro' );
	}

	/**
	 * Convert the plan slot date and time into a UTC timestamp string.
	 * Includes strategic jitter and target audience offsets.
	 *
	 * @param array $slot Plan row.
	 * @return string
	 */
	private static function slot_timestamp( array $slot ) {
		$target_tz = VMSAI_Brain::get( 'target_timezone' );
		$local_time = trim( $slot['slot_date'] . ' ' . ( $slot['slot_time'] ?: '10:00:00' ) );

		if ( $target_tz && in_array( $target_tz, timezone_identifiers_list(), true ) ) {
			try {
				$dt = new DateTime( $local_time, new DateTimeZone( $target_tz ) );
				$dt->setTimezone( new DateTimeZone( 'UTC' ) );
				$timestamp = $dt->format( 'Y-m-d H:i:s' );
			} catch ( Exception $e ) {
				$timestamp = get_gmt_from_date( $local_time );
			}
		} else {
			$timestamp = get_gmt_from_date( $local_time );
		}

		// STRATEGIC JITTER: Add/Subtract up to 15 minutes to look more human.
		$jitter = wp_rand( -900, 900 );
		$final_time = strtotime( $timestamp ) + $jitter;

		return gmdate( 'Y-m-d H:i:s', $final_time );
	}

	/**
	 * Build the UTM-tagged destination link.
	 *
	 * @param array $slot Plan row.
	 * @return string
	 */
	private static function tracked_link( array $slot ) {
		$base = VMSAI_Settings::get( 'link_in_bio' );
		$base = $base ?: home_url( '/' );

		return add_query_arg(
			array(
				'utm_source'   => VMSAI_Settings::get( 'utm_source', 'vm-social-ai-pro' ),
				'utm_medium'   => 'social',
				'utm_campaign' => 'c' . (int) $slot['campaign_id'],
				'utm_content'  => sanitize_title( mb_substr( (string) $slot['keyword'], 0, 40 ) ) ?: 'slot-' . (int) $slot['id'],
			),
			$base
		);
	}

	/**
	 * Clean and cap the hashtag list.
	 *
	 * @param mixed $raw   Model output.
	 * @param int   $limit Max tags.
	 * @return array
	 */
	private static function normalise_hashtags( $raw, $limit ) {
		if ( $limit <= 0 ) {
			return array();
		}

		$tags = is_array( $raw ) ? $raw : preg_split( '/[\s,]+/', (string) $raw );
		$out  = array();

		foreach ( (array) $tags as $tag ) {
			// Pro Fix: Support multi-lingual hashtags (Unicode).
			// Only strip punctuation and spaces, not the characters themselves.
			$tag = preg_replace( '/[^\p{L}\p{N}_]/u', '', (string) $tag );

			if ( '' === $tag || mb_strlen( $tag ) > 40 ) {
				continue;
			}
			$out[ mb_strtolower( $tag ) ] = $tag;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return array_values( $out );
	}

	/**
	 * Trim copy so the body plus hashtags fits the channel limit.
	 *
	 * @param string $body     Copy.
	 * @param int    $limit    Character limit.
	 * @param array  $hashtags Tags.
	 * @param string $channel  Channel slug.
	 * @return string
	 */
	private static function enforce_limit( $body, $limit, array $hashtags, $channel ) {
		$tag_length = $hashtags ? strlen( implode( ' #', $hashtags ) ) + 3 : 0;
		$ceiling    = $limit - $tag_length - 120; // Room for a long tracked link (UTMs etc)

		if ( mb_strlen( $body ) <= $ceiling ) {
			return $body;
		}

		// Cut at the last sentence boundary that fits rather than mid-word.
		$trimmed = mb_substr( $body, 0, max( 40, $ceiling ) );
		$cut     = max( mb_strrpos( $trimmed, '.' ), mb_strrpos( $trimmed, '!' ), mb_strrpos( $trimmed, '?' ) );

		if ( $cut && $cut > $ceiling * 0.5 ) {
			return trim( mb_substr( $trimmed, 0, $cut + 1 ) );
		}

		return trim( $trimmed ) . '…';
	}
}
