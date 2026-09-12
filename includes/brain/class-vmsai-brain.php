<?php
/**
 * The Brain — durable business identity and context.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything the AI needs to sound like this business rather than a generic
 * marketing bot: who it serves, what it sells, how it talks, what it has
 * already said, and what has actually performed.
 */
class VMSAI_Brain {

	/**
	 * Runtime cache of buckets.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Fields that make up the business identity.
	 *
	 * @return array<string,string>
	 */
	public static function schema() {
		return array(
			'business_name'    => __( 'Business name', 'vm-social-ai-pro' ),
			'one_liner'        => __( 'What the business does, in one sentence', 'vm-social-ai-pro' ),
			'industry'         => __( 'Industry', 'vm-social-ai-pro' ),
			'location'         => __( 'City and service area', 'vm-social-ai-pro' ),
			'audience'         => __( 'Who you are trying to reach', 'vm-social-ai-pro' ),
			'pain_points'      => __( 'Problems your customers have', 'vm-social-ai-pro' ),
			'offers'           => __( 'Products and services, with price points', 'vm-social-ai-pro' ),
			'proof'            => __( 'Results, credentials and social proof', 'vm-social-ai-pro' ),
			'differentiator'   => __( 'Why someone picks you over the alternative', 'vm-social-ai-pro' ),
			'voice_lab'        => __( 'Voice Lab: Paste 5-10 examples of your best previous posts', 'vm-social-ai-pro' ),
			'tone'             => __( 'Brand voice', 'vm-social-ai-pro' ),
			'banned_phrases'   => __( 'Words and claims never to use', 'vm-social-ai-pro' ),
			'keywords'         => __( 'Core keywords, one per line', 'vm-social-ai-pro' ),
			'competitors'      => __( 'Competitors worth watching', 'vm-social-ai-pro' ),
			'cta_library'      => __( 'Calls to action, one per line', 'vm-social-ai-pro' ),
			'compliance_notes' => __( 'Legal or regulatory limits on claims', 'vm-social-ai-pro' ),
			'service_area'     => __( 'Specific neighborhoods or local landmarks you serve', 'vm-social-ai-pro' ),
			'google_category'  => __( 'Primary Google Business category', 'vm-social-ai-pro' ),
			'target_timezone'  => __( 'Target audience timezone (e.g. America/New_York)', 'vm-social-ai-pro' ),
			'lead_magnet'      => __( 'Lead Magnet Content: Paste your expert guide, PDF text, or ebook content here', 'vm-social-ai-pro' ),
			'brand_color_1'    => __( 'Primary Brand Color (HEX)', 'vm-social-ai-pro' ),
			'brand_color_2'    => __( 'Secondary Brand Color (HEX)', 'vm-social-ai-pro' ),
			'brand_fonts'      => __( 'Brand Typography Style (e.g. Modern Sans-Serif, Elegant Serif, Playful Script)', 'vm-social-ai-pro' ),
			'visual_reference' => __( 'Visual Aesthetic (e.g. Minimalist Luxury, Tech-Forward, High-Contrast Industrial)', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Read one brain field.
	 *
	 * @param string $key     Field key.
	 * @param string $default Fallback.
	 * @param string $bucket  Bucket name.
	 * @return string
	 */
	public static function get( $key, $default = '', $bucket = 'identity' ) {
		$all = self::all( $bucket );
		return isset( $all[ $key ] ) && '' !== $all[ $key ] ? $all[ $key ] : $default;
	}

	/**
	 * All fields in a bucket.
	 *
	 * @param string $bucket Bucket name.
	 * @return array<string,string>
	 */
	public static function all( $bucket = 'identity' ) {
		if ( isset( self::$cache[ $bucket ] ) ) {
			return self::$cache[ $bucket ];
		}

		$cache_key = 'vmsai_brain_bucket_' . $bucket;
		$cached    = wp_cache_get( $cache_key, 'vmsai' );

		if ( false !== $cached ) {
			self::$cache[ $bucket ] = $cached;
			return $cached;
		}

		global $wpdb;
		$table = VMSAI_Install::table( 'brain' );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM `$table` WHERE bucket = %s", $bucket ), ARRAY_A ); // phpcs:ignore

		$out = array();
		foreach ( $rows as $row ) {
			$val = (string) $row['meta_value'];
			// Attempt to decode JSON for non-identity buckets.
			if ( 'identity' !== $bucket && '' !== $val && ( '[' === $val[0] || '{' === $val[0] ) ) {
				$decoded = json_decode( $val, true );
				if ( null !== $decoded ) {
					$out[ $row['meta_key'] ] = $decoded;
					continue;
				}
			}
			$out[ $row['meta_key'] ] = $val;
		}

		self::$cache[ $bucket ] = $out;
		wp_cache_set( $cache_key, $out, 'vmsai', 3600 );
		return $out;
	}

	/**
	 * Write brain fields.
	 *
	 * @param array  $values Key => value.
	 * @param string $bucket Bucket name.
	 * @return void
	 */
	public static function set( array $values, $bucket = 'identity' ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'brain' );
		$now   = current_time( 'mysql', true );

		foreach ( $values as $key => $value ) {
			$wpdb->query( // phpcs:ignore
				$wpdb->prepare(
					"INSERT INTO `$table` (bucket, meta_key, meta_value, updated_at) VALUES (%s, %s, %s, %s)
					 ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = VALUES(updated_at)", // phpcs:ignore
					$bucket,
					sanitize_key( $key ),
					is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
					$now
				)
			);
		}

		unset( self::$cache[ $bucket ] );
		wp_cache_delete( 'vmsai_brain_bucket_' . $bucket, 'vmsai' );
		wp_cache_delete( 'vmsai_brain_completeness' );
	}

	/**
	 * Whether the Brain has enough to write convincingly.
	 *
	 * @return bool
	 */
	public static function is_ready() {
		$all = self::all();
		return ! empty( $all['business_name'] ) && ! empty( $all['one_liner'] ) && ! empty( $all['audience'] );
	}

	/**
	 * Completeness as a percentage, shown on the dashboard.
	 *
	 * @return int
	 */
	public static function completeness() {
		$cached = wp_cache_get( 'vmsai_brain_completeness' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$schema = self::schema();
		$all    = self::all();
		$filled = 0;

		foreach ( array_keys( $schema ) as $key ) {
			if ( ! empty( $all[ $key ] ) ) {
				$filled++;
			}
		}

		$result = (int) round( ( $filled / max( 1, count( $schema ) ) ) * 100 );
		wp_cache_set( 'vmsai_brain_completeness', $result, '', 3600 );

		return $result;
	}

	/**
	 * Read the site and propose identity values, so setup is a review rather
	 * than a blank form.
	 *
	 * @return array{ok:bool,data:array,error:string}
	 */
	public static function discover() {
		$signals = array(
			'site_title'       => get_bloginfo( 'name' ),
			'tagline'          => get_bloginfo( 'description' ),
			'url'              => home_url( '/' ),
			'recent_headlines' => array(),
			'pages'            => array(),
			'products'         => array(),
		);

		// Performance: Limit fetch count for "Site Audit" to keep it fast.
		foreach ( get_posts( array( 'numberposts' => 6, 'post_status' => 'publish' ) ) as $post ) {
			$signals['recent_headlines'][] = $post->post_title;
		}

		foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => 4, 'post_status' => 'publish' ) ) as $page ) {
			$signals['pages'][] = array(
				'title'   => $page->post_title,
				'excerpt' => wp_trim_words( wp_strip_all_tags( $page->post_content ), 30 ),
			);
		}

		if ( post_type_exists( 'product' ) ) {
			foreach ( get_posts( array( 'post_type' => 'product', 'numberposts' => 6, 'post_status' => 'publish' ) ) as $product ) {
				$signals['products'][] = array(
					'title' => $product->post_title,
					'price' => get_post_meta( $product->ID, '_price', true ),
				);
			}
		}

		$system = 'You are an Elite Brand Strategist and SEO Architect. Your mission is to audit a business website and build a high-conversion social media profile. You focus on specific trust signals, unique selling points (USPs), and high-intent customer search behaviors.';

		$prompt = "Conduct a deep strategic audit of these business signals and generate a social media identity brief.\n\n"
			. wp_json_encode( $signals )
			. "\n\nREQUIRED OUTPUT FORMAT: JSON with exactly these keys: business_name, one_liner, industry, location, audience, pain_points, offers, proof, differentiator, tone, keywords, cta_library.\n"
			. "STRATEGIC RULES:\n"
			. "- keywords: 12-15 specific, high-intent search phrases a customer uses when they are ready to buy. Newline separated.\n"
			. "- cta_library: 5 powerful, action-oriented calls to action (direct, not passive). Newline separated.\n"
			. "- offers: Detail products/services with prices and core benefits.\n"
			. "- differentiator: Find the 'Unfair Advantage' or specific reason this brand beats the competition.\n"
			. 'BE SPECIFIC. Avoid generic words like "quality", "service", or "affordable" unless they are part of a unique fact.';

		$result = vmsai()->text_engine()->generate_json( $system, $prompt, array( 'max_tokens' => 1800, 'temperature' => 0.4 ) );

		if ( empty( $result['ok'] ) ) {
			return array( 'ok' => false, 'data' => array(), 'error' => $result['error'] );
		}

		$allowed = array_keys( self::schema() );
		$clean   = array();

		foreach ( $result['data'] as $key => $value ) {
			$key = sanitize_key( $key );
			if ( in_array( $key, $allowed, true ) ) {
				$clean[ $key ] = is_array( $value ) ? implode( "\n", array_map( 'strval', $value ) ) : (string) $value;
			}
		}

		return array( 'ok' => true, 'data' => $clean, 'error' => '' );
	}

	/**
	 * Compact context block injected into every generation prompt.
	 *
	 * @param string $channel Optional channel for channel-specific notes.
	 * @return string
	 */
	public static function context( $channel = '' ) {
		$b     = self::all();
		$lines = array();

		// VM SEO Brain Integration (guarded: either class name may exist,
		// and profile() may be absent — never fatal the prompt build).
		if ( class_exists( 'VMSB_Brain' ) || class_exists( 'VMSB_Core' ) ) {
			$seo_brain = null;
			if ( class_exists( 'VMSB_Brain' ) && method_exists( 'VMSB_Brain', 'profile' ) ) {
				$seo_brain = new VMSB_Brain();
			} elseif ( class_exists( 'VMSB_Core' ) && method_exists( 'VMSB_Core', 'profile' ) ) {
				$seo_brain = new VMSB_Core();
			}
			if ( $seo_brain ) {
				$profile = (array) $seo_brain->profile();

				// Override with SEO Brain's deeper profile if fields are empty here.
				if ( empty( $b['business_name'] ) && ! empty( $profile['name'] ) ) $b['business_name'] = $profile['name'];
				if ( empty( $b['one_liner'] ) && ! empty( $profile['description'] ) )     $b['one_liner']     = $profile['description'];
				if ( empty( $b['industry'] ) && ! empty( $profile['type'] ) )      $b['industry']      = $profile['type'];
				if ( empty( $b['audience'] ) && ! empty( $profile['audience'] ) )      $b['audience']      = $profile['audience'];
				if ( empty( $b['tone'] ) && ! empty( $profile['tone'] ) )          $b['tone']          = $profile['tone'];
			}
		}

		$lines[] = 'BUSINESS: ' . ( $b['business_name'] ?? get_bloginfo( 'name' ) );
		$map = array(
			'one_liner'      => 'WHAT IT DOES',
			'industry'       => 'INDUSTRY',
			'location'       => 'SERVICE AREA',
			'audience'       => 'AUDIENCE',
			'pain_points'    => 'CUSTOMER PROBLEMS',
			'offers'         => 'OFFERS',
			'cta_library'    => 'PREFERRED CALLS TO ACTION (pick or adapt one of these for the cta field, don\'t invent a generic one)',
			'proof'          => 'PROOF',
			'differentiator' => 'EDGE OVER COMPETITORS',
			'competitors'    => 'COMPETITORS TO DIFFERENTIATE FROM',
			'tone'           => 'VOICE',
			'voice_lab'      => 'BRAND VOICE EXAMPLES (match this rhythm and style)',
			'keywords'       => 'TARGET KEYWORDS',
		);

		foreach ( $map as $key => $label ) {
			if ( ! empty( $b[ $key ] ) ) {
				$lines[] = $label . ': ' . self::flatten( $b[ $key ] );
			}
		}

		$dna = self::get( 'voice_dna', '', 'context' );
		if ( $dna ) {
			$lines[] = 'STYLISTIC DNA: ' . $dna;
		}

		if ( ! empty( $b['banned_phrases'] ) ) {
			$lines[] = 'NEVER USE: ' . self::flatten( $b['banned_phrases'] );
		}

		if ( ! empty( $b['compliance_notes'] ) ) {
			$lines[] = 'COMPLIANCE LIMITS: ' . self::flatten( $b['compliance_notes'] );
		}

		if ( ! empty( $b['location'] ) ) {
			$lines[] = 'BUSINESS LOCATION: ' . $b['location'];

			// WORLD CLASS LOCALIZATION: Injected City Landmarks and Slang instructions
			$lines[] = "LOCAL CONTEXT RULES: Since the business is in {$b['location']}, occasionally mention specific local landmarks, neighborhoods, or cultural nuances unique to this area. Use local terminology where appropriate to sound like a local industry leader, not a remote bot.";
		}

		if ( ! empty( $b['service_area'] ) ) {
			$lines[] = 'SPECIFIC LOCAL LANDMARKS/AREAS: ' . self::flatten( $b['service_area'] );
		}

		if ( ! empty( $b['google_category'] ) ) {
			$lines[] = 'GMB CATEGORY: ' . $b['google_category'];
		}

		$city_insights = self::get( 'city_insights', '', 'context' );
		if ( $city_insights ) {
			$lines[] = 'CITY LANDMARKS & CULTURAL VIBE: ' . self::flatten( $city_insights );
		}

		if ( ! empty( $b['lead_magnet'] ) ) {
			$text = (string) $b['lead_magnet'];
			// Split into paragraphs and pick a random segment to provide deep knowledge without blowing token limit
			$segments = array_filter( explode( "\n\n", $text ) );
			if ( $segments ) {
				$segment = $segments[ array_rand( $segments ) ];
				$lines[] = 'EXPERT KNOWLEDGE SNIPPET (use this to teach the audience something specific and valuable): ' . self::flatten( $segment );
			}
		}

		$flavour = VMSAI_Settings::get( 'locale_flavour' );
		if ( $flavour ) {
			$lines[] = 'LOCALISATION: ' . $flavour;
		}

		$recent = self::recent_topics( 25 );
		if ( $recent ) {
			$lines[] = 'ALREADY PUBLISHED (do not repeat these angles): ' . implode( ' | ', $recent );
		}

		$winners = self::top_performers( 5 );
		if ( $winners ) {
			$lines[] = 'BEST PERFORMING POSTS SO FAR (lean into what worked): ' . implode( ' | ', $winners );
		}

		$news = self::get( 'recent_industry_news', array(), 'context' );
		if ( $news ) {
			$lines[] = 'CURRENT INDUSTRY NEWS: ' . implode( ' | ', (array) $news );
		}

		$competitor_intel = self::get( 'competitor_intel', '', 'context' );
		if ( $competitor_intel ) {
			$lines[] = 'COMPETITOR INTELLIGENCE (use the gap, don\'t copy the move): ' . $competitor_intel;
		}

		if ( $channel ) {
			$lines[] = 'CHANNEL: ' . $channel;
		}

		$lines[] = 'CURRENT MARKETING STAGE: ' . VMSAI_Research::get_funnel_stage();

		return implode( "\n", $lines );
	}

	/**
	 * Titles published recently, used to suppress repetition.
	 *
	 * @param int $limit Row count.
	 * @return array
	 */
	public static function recent_topics( $limit = 25 ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'queue' );

		return (array) $wpdb->get_col( // phpcs:ignore
			$wpdb->prepare( "SELECT title FROM `$table` WHERE status IN ('published','approved') AND title <> '' ORDER BY id DESC LIMIT %d", (int) $limit ) // phpcs:ignore
		);
	}

	/**
	 * Detailed data of top performing posts.
	 */
	public static function top_performers_data( $limit = 5 ) {
		global $wpdb;
		$queue   = VMSAI_Install::table( 'queue' );
		$metrics = VMSAI_Install::table( 'metrics' );
		$plan    = VMSAI_Install::table( 'plan' );

		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT q.title, q.channel, p.post_id, MAX(m.impressions) AS views, MAX(m.engagements) AS eng
			 FROM `$metrics` m
			 INNER JOIN `$queue` q ON q.id = m.queue_id
			 LEFT JOIN `$plan` p ON p.id = q.plan_id
			 WHERE q.title <> '' GROUP BY m.queue_id ORDER BY eng DESC, views DESC LIMIT %d",
			(int) $limit
		), ARRAY_A );
	}

	/**
	 * Titles of the highest-engagement posts, used to reinforce what works.
	 *
	 * @param int $limit Row count.
	 * @return array
	 */
	public static function top_performers( $limit = 5 ) {
		global $wpdb;
		$queue   = VMSAI_Install::table( 'queue' );
		$metrics = VMSAI_Install::table( 'metrics' );

		$rows = (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT q.title, q.channel, MAX(m.impressions) AS views, MAX(m.engagements) AS eng
				 FROM `$metrics` m INNER JOIN `$queue` q ON q.id = m.queue_id
				 WHERE q.title <> '' GROUP BY m.queue_id ORDER BY eng DESC, views DESC LIMIT %d", // phpcs:ignore
				(int) $limit
			),
			ARRAY_A
		);

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = sprintf( '%s (%s, %s views)', $row['title'], $row['channel'], number_format_i18n( (int) $row['views'] ) );
		}

		return $out;
	}

	/**
	 * Collapse a multi-line field to a single prompt-friendly line.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function flatten( $value ) {
		$value = preg_replace( '/\s*\n\s*/', '; ', trim( (string) $value ) );
		// Token Economy: Strictly cap individual field context to 500 chars.
		return mb_substr( $value, 0, 500 );
	}
}
