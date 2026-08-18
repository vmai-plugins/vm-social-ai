<?php
/**
 * Content Agents — a roster of named personas, each with its own purpose,
 * tone, image style and channel targeting.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where the growth Plan's pillars are one fixed, evergreen mix for the whole
 * business, an Agent is a distinct voice with its own job — a storyteller,
 * a promoter, a trend-watcher, a quote curator — each with its own visual
 * identity. CRUD lives here; selection/scheduling lives in
 * VMSAI_Agent_Feed.
 */
class VMSAI_Agents {

	/**
	 * Curated image styles. 'custom' unlocks a free-text style field on the
	 * agent instead of one of these.
	 *
	 * @return array<string,array{label:string,brief:string,format:string}>
	 */
	public static function styles() {
		return array(
			'photo'       => array(
				'label' => __( 'Photo-realistic', 'vm-social-ai' ),
				'brief' => 'PHOTO-REALISTIC VISUAL BRIEF. Style: Modern professional photography, high-end editorial, cinematic lighting (8k, highly detailed). Describe a scene that matches the topic exactly. No text, no logos.',
			),
			'banner'      => array(
				'label' => __( 'Business Promo / Banner', 'vm-social-ai' ),
				'brief' => 'MARKETING BANNER VISUAL BRIEF. Style: Bold, high-impact promotional graphic design — dynamic color-blocked background, strong diagonal composition, premium poster-campaign aesthetic, generous clear space reserved for a headline. No literal text, no logos.',
			),
			'infographic' => array(
				'label' => __( 'Infographic', 'vm-social-ai' ),
				'brief' => 'INFOGRAPHIC VISUAL BRIEF. Style: Clean modern infographic and data-visualization design, minimal flat-icon illustration, organized visual hierarchy, soft brand-friendly color palette. No literal text, no logos.',
			),
			'ghibli'      => array(
				'label' => __( 'Ghibli-style Illustration', 'vm-social-ai' ),
				'brief' => 'HAND-DRAWN ANIMATED FILM VISUAL BRIEF. Style: Studio-Ghibli-inspired watercolor animation — soft painterly backgrounds, warm natural light, gentle whimsical character or scene design, lush detailed nature, nostalgic and cozy atmosphere. No text, no logos.',
			),
			'quote_card'  => array(
				'label' => __( 'Quote Card', 'vm-social-ai' ),
				'brief' => 'MINIMALIST QUOTE-CARD VISUAL BRIEF. Style: Clean, elegant, mostly negative space, a single soft focal image or subtle texture in the background suited to the mood of the quote, muted sophisticated color palette, generous empty space for a large centered quote. No literal text, no logos.',
			),
			'editorial'   => array(
				'label' => __( 'Editorial News', 'vm-social-ai' ),
				'brief' => 'EDITORIAL NEWS PHOTOGRAPHY. Style: Realistic, high-contrast, professional journalism aesthetic. Sharp focus on a subject relevant to industry news. 8k resolution, cinematic natural lighting. No text.',
			),
			'custom'      => array(
				'label' => __( 'Custom style…', 'vm-social-ai' ),
				'brief' => '', // Overridden per-agent by image_style_custom.
			),
		);
	}

	/**
	 * All agents.
	 *
	 * @param bool $only_active Restrict to status = 'active'.
	 * @return array
	 */
	public static function all( $only_active = false ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'agents' );
		$sql   = "SELECT * FROM `$table`" . ( $only_active ? " WHERE status = 'active'" : '' ) . ' ORDER BY id ASC';

		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
	}

	/**
	 * One agent, hydrated with the derived fields (slug, persona, visual,
	 * style) that callers like the Composer and Image Lab rely on. Accepts
	 * either a numeric id (admin CRUD) or a slug (Composer/Image Lab). Never
	 * returns null — falls back to the top active agent, or a generic
	 * default persona if the roster is empty, so callers can use the result
	 * unconditionally.
	 *
	 * @param int|string $id_or_slug Agent id or slug.
	 * @return array
	 */
	public static function get( $id_or_slug ) {
		$agent = null;

		if ( is_numeric( $id_or_slug ) && (int) $id_or_slug > 0 ) {
			global $wpdb;
			$table = VMSAI_Install::table( 'agents' );
			$agent = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE id = %d", (int) $id_or_slug ), ARRAY_A ); // phpcs:ignore
		} elseif ( is_string( $id_or_slug ) && '' !== $id_or_slug ) {
			foreach ( self::all() as $candidate ) {
				if ( self::slug( $candidate ) === $id_or_slug ) {
					$agent = $candidate;
					break;
				}
			}
		}

		if ( ! $agent ) {
			$active = self::all( true );
			$agent  = $active ? $active[0] : self::default_agent();
		}

		return self::hydrate( $agent );
	}

	/**
	 * Slug → {label, style} listing for UIs like the Image Lab that just
	 * need to list agents without the full roster row. 'style' here is the
	 * human-readable style name for display — for the raw style key
	 * (needed by the image engine's format branching), use get()['style'].
	 *
	 * @return array<string,array{label:string,style:string}>
	 */
	public static function registry() {
		$out = array();

		foreach ( self::all( true ) as $agent ) {
			$hydrated = self::hydrate( $agent );
			$out[ $hydrated['slug'] ] = array(
				'label' => $hydrated['name'],
				'style' => $hydrated['style_label'],
			);
		}

		return $out;
	}

	/**
	 * Pick the agent slug best suited to a content pillar, for callers (the
	 * evergreen Plan, Campaign Planner) that don't already have a specific
	 * agent chosen. The Agent Feed sets plan.agent_id directly and skips
	 * this — routing is only a sensible default for everything else.
	 *
	 * @param string $pillar Pillar key from VMSAI_Planner::pillars().
	 * @return string Agent slug, or '' if no active agents exist.
	 */
	public static function route( $pillar ) {
		$agents = self::all( true );

		if ( ! $agents ) {
			return '';
		}

		$map = array(
			'hook'      => 'trend-watcher',
			'value'     => 'trend-watcher',
			'community' => 'socialite',
			'proof'     => 'promoter',
			'offer'     => 'promoter',
			'story'     => 'storyteller',
			'authority' => 'trend-watcher',
			'education' => 'trend-watcher',
		);

		$preferred = $map[ (string) $pillar ] ?? '';

		if ( $preferred ) {
			foreach ( $agents as $agent ) {
				if ( self::slug( $agent ) === $preferred ) {
					return $preferred;
				}
			}
		}

		// No agent with that slug (custom roster) — fall back to the
		// heaviest-weighted active agent so routing never dead-ends.
		usort( $agents, fn( $a, $b ) => (int) $b['weight'] <=> (int) $a['weight'] );

		return self::slug( $agents[0] );
	}

	/**
	 * Derive a stable slug from an agent's name (e.g. "The Storyteller" →
	 * "storyteller"). Computed rather than stored so renaming an agent
	 * doesn't orphan a saved slug column.
	 *
	 * @param array $agent Agent row.
	 * @return string
	 */
	private static function slug( array $agent ) {
		$slug = preg_replace( '/^the-/', '', sanitize_title( (string) ( $agent['name'] ?? '' ) ) );

		return $slug ?: ( 'agent-' . (int) ( $agent['id'] ?? 0 ) );
	}

	/**
	 * Add the fields derived from an agent's stored style/name/tone that
	 * the Composer and Image Lab consume directly.
	 *
	 * @param array $agent Raw agent row.
	 * @return array
	 */
	private static function hydrate( array $agent ) {
		$styles    = self::styles();
		$style_key = (string) ( $agent['image_style'] ?? 'photo' );
		$style_def = $styles[ $style_key ] ?? $styles['photo'];

		$agent['slug']        = self::slug( $agent );
		// Raw style key — this is what the image engine's format branching
		// (banner/infographic) matches against, so it must stay a key, not
		// the display label. Use style_label for anything shown to a human.
		$agent['style']       = $style_key;
		$agent['style_label'] = $style_def['label'];
		$agent['visual'] = ( 'custom' === $style_key && ! empty( $agent['image_style_custom'] ) )
			? $agent['image_style_custom']
			: $style_def['brief'];

		$voice = trim( (string) ( $agent['tone'] ?? '' ) ) ?: trim( (string) ( $agent['brief'] ?? '' ) );
		$agent['persona'] = trim( sprintf(
			/* translators: 1: agent name, 2: agent tone/brief */
			__( 'You are %1$s. %2$s', 'vm-social-ai' ),
			(string) ( $agent['name'] ?? __( 'a specialized content agent', 'vm-social-ai' ) ),
			$voice
		) );

		return $agent;
	}

	/**
	 * A safe stand-in used only when the roster is completely empty (e.g.
	 * before first seed, or every agent deleted) so get() never returns
	 * null to a caller that indexes straight into the result.
	 *
	 * @return array
	 */
	private static function default_agent() {
		return array(
			'id'                 => 0,
			'name'               => __( 'Brand Voice', 'vm-social-ai' ),
			'brief'              => '',
			'tone'               => '',
			'image_style'        => 'photo',
			'image_style_custom' => '',
			'channels'           => '[]',
			'weight'             => 10,
			'status'             => 'active',
		);
	}

	/**
	 * Create or update an agent.
	 *
	 * @param array $data Posted fields.
	 * @return int Agent id.
	 */
	public static function save( array $data ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'agents' );
		$id    = (int) ( $data['id'] ?? 0 );

		$style = (string) ( $data['image_style'] ?? 'photo' );
		if ( ! array_key_exists( $style, self::styles() ) ) {
			$style = 'photo';
		}

		$row = array(
			'name'               => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'brief'              => sanitize_textarea_field( (string) ( $data['brief'] ?? '' ) ),
			'tone'               => sanitize_textarea_field( (string) ( $data['tone'] ?? '' ) ),
			'image_style'        => $style,
			'image_style_custom' => sanitize_textarea_field( (string) ( $data['image_style_custom'] ?? '' ) ),
			'channels'           => wp_json_encode( array_values( array_filter( array_map( 'sanitize_key', (array) ( $data['channels'] ?? array() ) ) ) ) ),
			'weight'             => max( 1, min( 100, (int) ( $data['weight'] ?? 10 ) ) ),
			'status'             => in_array( $data['status'] ?? '', array( 'active', 'paused' ), true ) ? $data['status'] : 'active',
		);

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore
			return $id;
		}

		$row['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( $table, $row ); // phpcs:ignore

		return (int) $wpdb->insert_id;
	}

	/**
	 * Remove an agent. Existing composed posts are untouched; future plan
	 * slots simply stop being generated for it.
	 *
	 * @param int $id Agent id.
	 * @return void
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( VMSAI_Install::table( 'agents' ), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore
	}

	/**
	 * Which channels an agent is allowed to post to. An empty list means
	 * "every connected channel" — most people won't bother restricting.
	 *
	 * @param array $agent Agent row.
	 * @return array
	 */
	public static function allowed_channels( array $agent ) {
		$decoded = json_decode( (string) ( $agent['channels'] ?? '' ), true );
		return is_array( $decoded ) ? array_values( array_filter( $decoded ) ) : array();
	}

	/**
	 * Seed a working starter roster on first use, matching the four kinds
	 * of agent described when this feature was designed. Idempotent — does
	 * nothing once any agent exists, including if they've all been deleted.
	 *
	 * @return void
	 */
	public static function maybe_seed_starter_roster() {
		if ( get_option( 'vmsai_agents_seeded' ) ) {
			return;
		}

		update_option( 'vmsai_agents_seeded', 1, false );

		if ( self::all() ) {
			return;
		}

		$starters = array(
			array(
				'name'   => __( 'The Storyteller', 'vm-social-ai' ),
				'brief'  => 'Tell a short, emotionally engaging story connected to the business — a moment from its history, a customer journey, a "why we started this" beat, or a small human detail. Never a direct pitch; the story is the whole post.',
				'weight' => 15,
				'image_style' => 'ghibli',
			),
			array(
				'name'   => __( 'The Promoter', 'vm-social-ai' ),
				'brief'  => 'Directly pitch a specific product, service or offer with a clear, unmistakable call to action. Confident, specific, no hedging.',
				'weight' => 20,
				'image_style' => 'banner',
			),
			array(
				'name'   => __( 'The Trend Watcher', 'vm-social-ai' ),
				'brief'  => "Comment on a current, relevant trend, seasonal moment, or piece of industry news, and connect it back to the business's own angle or expertise.",
				'weight' => 15,
				'image_style' => 'editorial',
			),
			array(
				'name'   => __( 'The Quote Curator', 'vm-social-ai' ),
				'brief'  => "Share one short, genuinely relevant quote — from a customer, a team member, or a well-known figure whose words fit the business's values — with a one-line caption of context. The quote is the point, not a sales pitch.",
				'weight' => 10,
				'image_style' => 'quote_card',
			),
			array(
				'name'   => __( 'The Socialite', 'vm-social-ai' ),
				'brief'  => "Listen for community signals and provide helpful, engaging, and high-value responses. Focus on building trust and identifying potential leads.",
				'weight' => 10,
				'image_style' => 'photo',
			),
		);

		foreach ( $starters as $agent ) {
			self::save( $agent );
		}
	}
}
