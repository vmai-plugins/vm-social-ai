<?php
/**
 * REST endpoints for the admin app.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every admin action goes through these routes so the UI stays a thin layer.
 */
class VMSAI_Rest {

	const NS = 'vm-social-ai/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Capability check for every route.
	 *
	 * @return bool
	 */
	public function can_manage( $request ) {
		$route = $request->get_route();

		// 1. Basic Read/Stats: Needs 'vmsai_read'
		if ( strpos($route, '/stats') !== false ) return current_user_can( 'vmsai_read' );

		// 2. High-level planning/editing: Needs 'vmsai_edit'
		if ( strpos($route, '/brain/') !== false ) return current_user_can( 'vmsai_edit' );
		if ( strpos($route, '/agents/') !== false ) return current_user_can( 'vmsai_edit' );
		if ( strpos($route, '/plan/') !== false ) return current_user_can( 'vmsai_plan' );
		if ( strpos($route, '/queue/update') !== false ) return current_user_can( 'vmsai_edit' );

		// 3. Publishing/Keys: Needs 'vmsai_publish' or 'vmsai_manage_keys'
		if ( strpos($route, '/publish-now') !== false ) return current_user_can( 'vmsai_publish' );
		if ( strpos($route, '/engine/') !== false ) return current_user_can( 'vmsai_manage_keys' );
		if ( strpos($route, '/channel/') !== false ) return current_user_can( 'vmsai_manage_keys' );

		return current_user_can( 'manage_options' );
	}

	/**
	 * Define routes.
	 *
	 * @return void
	 */
	public function routes() {
		$auth = array( $this, 'can_manage' );

		$routes = array(
			'brain/discover'    => array( 'POST', 'discover_brain' ),
			'brain/save'        => array( 'POST', 'save_brain' ),
			'strategy/audit'    => array( 'GET', 'strategy_audit' ),
			'pipeline/list'     => array( 'GET', 'pipeline_list' ),
			'reporting/roi'     => array( 'GET', 'reporting_roi' ),
			'research/trends'   => array( 'GET', 'research_trends' ),
			'research/inject'   => array( 'POST', 'research_inject' ),
			'campaign/create'   => array( 'POST', 'create_campaign' ),
			'campaign/math'     => array( 'POST', 'campaign_math' ),
			'plan/move-slot'    => array( 'POST', 'move_plan_slot' ),
			'plan/generate'     => array( 'POST', 'generate_plan' ),
			'plan/calendar'     => array( 'GET', 'calendar' ),
			'queue/list'        => array( 'GET', 'queue_list' ),
			'queue/update'      => array( 'POST', 'queue_update' ),
			'queue/regenerate'  => array( 'POST', 'queue_regenerate' ),
			'queue/publish-now' => array( 'POST', 'publish_now' ),
			'queue/delete'      => array( 'POST', 'queue_delete' ),
			'queue/regen-image' => array( 'POST', 'queue_regen_image' ),
			'lab/generate-image' => array( 'POST', 'lab_generate_image' ),
			'engine/test'       => array( 'POST', 'test_engine' ),
			'engine/test-provider' => array( 'POST', 'test_provider' ),
			'engine/test-video'    => array( 'POST', 'test_video' ),
			'engine/sync'       => array( 'POST', 'sync_models' ),
			'engine/models'     => array( 'GET', 'models' ),
			'channel/test'      => array( 'POST', 'test_channel' ),
			'tick'              => array( 'POST', 'run_tick' ),
			'stats'             => array( 'GET', 'stats' ),
			'inbox/list'        => array( 'GET', 'inbox_list' ),
			'inbox/suggest'     => array( 'POST', 'suggest_reply' ),
			'inbox/reply'       => array( 'POST', 'inbox_reply' ),
			'rag/sync'          => array( 'POST', 'rag_sync' ),
			'commander/chat'    => array( 'POST', 'commander_chat' ),
			'brain/reflect'     => array( 'POST', 'reflect_on_performance' ),
			'queue/bulk'        => array( 'POST', 'queue_bulk' ),
			'storage/offload'   => array( 'POST', 'offload_existing' ),
			'plan/reset'        => array( 'POST', 'reset_failed_slots' ),
			'cache/clear'       => array( 'POST', 'clear_cache' ),
			'campaign-gen/angles' => array( 'GET', 'campaign_gen_angles' ),
			'campaign-gen/draft'  => array( 'POST', 'campaign_gen_draft' ),
			'campaign-gen/create' => array( 'POST', 'campaign_gen_create' ),
			'campaign-gen/compose' => array( 'POST', 'campaign_gen_compose' ),
			'queue/portal-update'  => array( 'POST', 'portal_update', '__return_true' ),
			'system/test-all'      => array( 'POST', 'test_all' ),
			'system/upcoming'      => array( 'GET', 'upcoming' ),
			'system/activity'      => array( 'GET', 'recent_activity' ),
			'compose/draft'        => array( 'POST', 'compose_draft' ),
			'compose/create'       => array( 'POST', 'compose_create' ),
			'compose/next-slot'    => array( 'GET', 'compose_next_slot' ),
			'compose/image'        => array( 'POST', 'compose_image' ),
			'agents/list'          => array( 'GET', 'agents_list' ),
			'agents/save'          => array( 'POST', 'agents_save' ),
			'agents/delete'        => array( 'POST', 'agents_delete' ),
			'agents/styles'        => array( 'GET', 'agents_styles' ),
		);

		foreach ( $routes as $path => $config ) {
			register_rest_route(
				self::NS,
				'/' . $path,
				array(
					'methods'             => $config[0],
					'callback'            => array( $this, $config[1] ),
					'permission_callback' => $auth,
				)
			);
		}
	}

	/**
	 * Auto-fill the Brain from the site.
	 *
	 * @return WP_REST_Response
	 */
	public function discover_brain() {
		$result = VMSAI_Brain::discover();

		if ( empty( $result['ok'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result['error'] ), 200 );
		}

		return new WP_REST_Response( array( 'ok' => true, 'data' => $result['data'] ), 200 );
	}

	/**
	 * Persist Brain fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_brain( WP_REST_Request $request ) {
		$fields  = (array) $request->get_param( 'fields' );
		$allowed = array_keys( VMSAI_Brain::schema() );
		$clean   = array();

		foreach ( $fields as $key => $value ) {
			$key = sanitize_key( $key );
			if ( in_array( $key, $allowed, true ) ) {
				$clean[ $key ] = sanitize_textarea_field( (string) $value );
			}
		}

		$old_location = VMSAI_Brain::get( 'location' );
		VMSAI_Brain::set( $clean );

		// WORLD CLASS LOCALIZATION: Trigger Local Insight Sync if location changed
		if ( ! empty($clean['location']) && $clean['location'] !== $old_location ) {
			wp_schedule_single_event( time() + 5, 'vmsai_sync_city_insights', array( $clean['location'] ) );
		}

		return new WP_REST_Response( array( 'ok' => true, 'completeness' => VMSAI_Brain::completeness() ), 200 );
	}

	/**
	 * Run a strategic audit of keywords and pillars.
	 */
	public function strategy_audit() {
		return new WP_REST_Response( VMSAI_Strategy::audit(), 200 );
	}

	/**
	 * Fetch the production pipeline.
	 */
	public function pipeline_list() {
		return new WP_REST_Response( VMSAI_Pipeline::get_board(), 200 );
	}

	/**
	 * Generate an AI ROI Report.
	 */
	public function reporting_roi() {
		return new WP_REST_Response( VMSAI_Reporting::generate_roi(), 200 );
	}

	/**
	 * Fetch latest trends for scouting.
	 */
	public function research_trends() {
		return new WP_REST_Response( array( 'ok' => true, 'trends' => VMSAI_Research::scout() ), 200 );
	}

	/**
	 * Inject a trend into the plan.
	 */
	public function research_inject( WP_REST_Request $request ) {
		$trend = sanitize_text_field( $request->get_param( 'trend' ) );
		return new WP_REST_Response( VMSAI_Research::inject( $trend ), 200 );
	}

	/**
	 * Reach arithmetic preview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function campaign_math( WP_REST_Request $request ) {
		$math = VMSAI_Planner::math(
			(int) $request->get_param( 'target_views' ),
			(int) $request->get_param( 'horizon_days' ),
			array_map( 'sanitize_key', (array) $request->get_param( 'channels' ) ),
			(int) $request->get_param( 'per_day' )
		);

		return new WP_REST_Response( array( 'ok' => true, 'math' => $math ), 200 );
	}

	/**
	 * Change the scheduled date of a plan slot.
	 */
	public function move_plan_slot( WP_REST_Request $request ) {
		global $wpdb;
		$id   = (int) $request->get_param( 'id' );
		$date = sanitize_text_field( $request->get_param( 'date' ) );

		$wpdb->update( VMSAI_Install::table('plan'), array( 'slot_date' => $date ), array( 'id' => $id ) );

		// Also update the queue if it's already composed
		$wpdb->query( $wpdb->prepare(
			"UPDATE " . VMSAI_Install::table('queue') . "
			 SET scheduled_at = CONCAT(%s, ' ', TIME(scheduled_at))
			 WHERE plan_id = %d",
			$date,
			$id
		) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Start a campaign and fill the first two weeks.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_campaign( WP_REST_Request $request ) {
		$channels = array_values( array_filter( array_map( 'sanitize_key', (array) $request->get_param( 'channels' ) ) ) );

		if ( ! $channels ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Connect at least one channel first.', 'vm-social-ai-pro' ) ), 200 );
		}

		$id = VMSAI_Planner::create_campaign(
			array(
				'name'           => sanitize_text_field( (string) $request->get_param( 'name' ) ),
				'target_views'   => (int) $request->get_param( 'target_views' ),
				'horizon_days'   => (int) $request->get_param( 'horizon_days' ),
				'channels'       => $channels,
				'language'       => sanitize_text_field( $request->get_param( 'language' ) ),
				'locale_flavour' => sanitize_text_field( $request->get_param( 'locale_flavour' ) ),
			)
		);

		if ( ! $id ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'You have reached the active campaign limit for your plan. Upgrade to unlock unlimited growth runs.', 'vm-social-ai-pro' ) ), 200 );
		}

		$plan = VMSAI_Planner::generate( $id, 14 );
		$plan_data = is_array( $plan ) ? $plan : array();

		return new WP_REST_Response(
			array(
				'ok'          => ! empty( $plan_data['ok'] ),
				'campaign_id' => $id,
				'created'     => (int) ( $plan_data['created'] ?? 0 ),
				'message'     => (string) ( $plan_data['error'] ?? '' ),
			),
			200
		);
	}

	/**
	 * Extend the plan.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function generate_plan( WP_REST_Request $request ) {
		$campaign_id = (int) $request->get_param( 'campaign_id' );
		$campaign_id = $campaign_id ?: (int) ( VMSAI_Planner::active_campaign()['id'] ?? 0 );

		if ( ! $campaign_id ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'No active campaign.', 'vm-social-ai-pro' ) ), 200 );
		}

		$result = VMSAI_Planner::generate( $campaign_id, max( 1, (int) $request->get_param( 'days' ) ?: 14 ) );
		$res_data = is_array( $result ) ? $result : array();

		return new WP_REST_Response(
			array(
				'ok'      => ! empty( $res_data['ok'] ),
				'created' => (int) ( $res_data['created'] ?? 0 ),
				'message' => (string) ( $res_data['error'] ?? '' )
			),
			200
		);
	}

	/**
	 * Calendar rows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function calendar( WP_REST_Request $request ) {
		$campaign = VMSAI_Planner::active_campaign();

		if ( ! $campaign ) {
			return new WP_REST_Response( array( 'ok' => true, 'rows' => array() ), 200 );
		}

		$from = sanitize_text_field( (string) $request->get_param( 'from' ) ) ?: current_time( 'Y-m-d' );
		$to   = sanitize_text_field( (string) $request->get_param( 'to' ) ) ?: gmdate( 'Y-m-d', strtotime( $from . ' +13 days' ) );

		return new WP_REST_Response(
			array( 'ok' => true, 'rows' => VMSAI_Planner::calendar( (int) $campaign['id'], $from, $to ) ),
			200
		);
	}

	/**
	 * Queue rows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function queue_list( WP_REST_Request $request ) {
		global $wpdb;
		$table  = VMSAI_Install::table( 'queue' );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$limit  = min( 200, max( 1, (int) $request->get_param( 'limit' ) ?: 50 ) );

		if ( $status && 'all' !== $status ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE status = %s ORDER BY scheduled_at ASC LIMIT %d", $status, $limit ), ARRAY_A ); // phpcs:ignore
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore
		}

		return new WP_REST_Response( array( 'ok' => true, 'rows' => $rows ), 200 );
	}

	/**
	 * Edit or approve a queued post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function queue_update( WP_REST_Request $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		if ( ! $id ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Missing post id.', 'vm-social-ai-pro' ) ), 200 );
		}

		$data   = array();
		$format = array();

		foreach ( array( 'body', 'title', 'hashtags', 'first_comment', 'tags', 'cta', 'link', 'reviewer_notes', 'is_evergreen' ) as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				if ( 'is_evergreen' === $field ) {
					$data[ $field ] = $value ? 1 : 0;
					$format[] = '%d';
				} else {
					$data[ $field ]  = in_array( $field, array( 'body', 'reviewer_notes', 'first_comment' ), true ) ? sanitize_textarea_field( (string) $value ) : sanitize_text_field( (string) $value );
					$format[]        = '%s';
				}
			}
		}

		$status = sanitize_key( (string) $request->get_param( 'status' ) );

		if ( in_array( $status, array( 'draft', 'approved', 'paused', 'failed' ), true ) ) {
			$data['status'] = $status;
			$format[]       = '%s';

			// Approving a failed post gives it a fresh set of attempts.
			if ( 'approved' === $status ) {
				$data['attempts'] = 0;
				$format[]         = '%d';
			}
		}

		$scheduled = sanitize_text_field( (string) $request->get_param( 'scheduled_at' ) );

		if ( $scheduled ) {
			$data['scheduled_at'] = get_gmt_from_date( $scheduled );
			$format[]             = '%s';
		}

		if ( ! $data ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Nothing to update.', 'vm-social-ai-pro' ) ), 200 );
		}

		$data['updated_at'] = current_time( 'mysql', true );
		$format[]           = '%s';

		$wpdb->update( VMSAI_Install::table( 'queue' ), $data, array( 'id' => $id ), $format, array( '%d' ) ); // phpcs:ignore

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Rewrite a post from its original plan slot.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function queue_regenerate( WP_REST_Request $request ) {
		global $wpdb;

		$id  = (int) $request->get_param( 'id' );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . VMSAI_Install::table( 'queue' ) . '` WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore

		if ( ! $row ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Post not found.', 'vm-social-ai-pro' ) ), 200 );
		}

		$slot = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . VMSAI_Install::table( 'plan' ) . '` WHERE id = %d', (int) $row['plan_id'] ), ARRAY_A ); // phpcs:ignore

		if ( ! $slot ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'The original plan slot is gone.', 'vm-social-ai-pro' ) ), 200 );
		}

		// A reviewer's rejection reason (Queue tab "Reject") is the single
		// most useful signal a regeneration can get — feed it into the same
		// 'angle' field the composer already reads as strategic direction,
		// rather than silently discarding it and hoping the rewrite happens
		// to fix whatever was wrong.
		if ( ! empty( $row['reviewer_notes'] ) ) {
			$slot['angle'] = trim( (string) $slot['angle'] ) . "\n\nREVIEWER FEEDBACK ON THE PREVIOUS DRAFT — fix this specifically: " . $row['reviewer_notes'];
		}

		$wpdb->delete( VMSAI_Install::table( 'queue' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
		$wpdb->update( VMSAI_Install::table( 'plan' ), array( 'status' => 'planned', 'queue_id' => 0 ), array( 'id' => (int) $slot['id'] ), array( '%s', '%d' ), array( '%d' ) ); // phpcs:ignore

		$result = VMSAI_Composer::compose( $slot );
		$res_data = is_array( $result ) ? $result : array();

		return new WP_REST_Response(
			array(
				'ok'       => ! empty( $res_data['ok'] ),
				'queue_id' => (int) ( $res_data['queue_id'] ?? 0 ),
				'message'  => (string) ( $res_data['error'] ?? '' )
			),
			200
		);
	}

	/**
	 * Push a post immediately.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function publish_now( WP_REST_Request $request ) {
		global $wpdb;

		$id  = (int) $request->get_param( 'id' );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . VMSAI_Install::table( 'queue' ) . '` WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore

		if ( ! $row ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Post not found.', 'vm-social-ai-pro' ) ), 200 );
		}

		if ( 'published' === $row['status'] ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'This post is already published.', 'vm-social-ai-pro' ) ), 200 );
		}

		$channel = vmsai()->channels()->get( $row['channel'] );

		if ( ! $channel || ! $channel->is_connected() ) {
			$missing = array();
			if ( $channel ) {
				foreach ( array_keys( $channel->credential_fields() ) as $f ) {
					if ( ! VMSAI_Settings::credential( $f ) ) $missing[] = $f;
				}
			}
			/* translators: %s: list of missing fields */
			$msg = $missing ? sprintf( __( 'Missing credentials: %s', 'vm-social-ai-pro' ), implode( ', ', $missing ) ) : __( 'That channel is not connected.', 'vm-social-ai-pro' );
			return new WP_REST_Response( array( 'ok' => false, 'message' => $msg ), 200 );
		}

		VMSAI_Logger::info( 'rest', sprintf( 'Manual publish triggered for %s post #%d.', $row['channel'], $id ) );

		$result = $channel->publish( $row );

		if ( empty( $result['ok'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result['error'] ), 200 );
		}

		if ( ! empty( $row['first_comment'] ) && ! empty( $result['remote_id'] ) ) {
			$channel->post_comment( $result['remote_id'], (string) $row['first_comment'] );
		}

		$wpdb->update( // phpcs:ignore
			VMSAI_Install::table( 'queue' ),
			array(
				'status'       => 'published',
				'remote_id'    => $result['remote_id'],
				'permalink'    => $result['permalink'],
				'published_at' => current_time( 'mysql', true ),
				'last_error'   => null,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return new WP_REST_Response( array( 'ok' => true, 'permalink' => $result['permalink'] ), 200 );
	}

	/**
	 * Remove a queued post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function queue_delete( WP_REST_Request $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$wpdb->delete( VMSAI_Install::table( 'queue' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Regenerate only the image for a queued post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function queue_regen_image( WP_REST_Request $request ) {
		global $wpdb;
		$id  = (int) $request->get_param( 'id' );
		$provider_override = sanitize_key( (string) $request->get_param( 'provider' ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . VMSAI_Install::table( 'queue' ) . '` WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore

		if ( ! $row ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Post not found.', 'vm-social-ai-pro' ) ), 200 );
		}

		// Pro Fix: Fetch plan data to pass post_id to the image engine
		$plan = $wpdb->get_row( $wpdb->prepare( "SELECT post_id FROM " . VMSAI_Install::table('plan') . " WHERE id = %d", (int) $row['plan_id'] ) );

		$result = vmsai()->image_engine()->create(
			(string) ( $row['image_prompt'] ?: $row['title'] ),
			array(
				'channel' => $row['channel'],
				'format'  => $row['format'],
				'topic'   => $row['title'],
				'keyword' => $row['alt_text'],
				'prompt'  => $row['image_prompt'],
				'post_id' => $plan ? (int) $plan->post_id : 0,
				'prefer'  => $provider_override ?: $row['image_provider']
			)
		);

		if ( ! $result['ok'] ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result['error'] ), 200 );
		}

		$wpdb->update(
			VMSAI_Install::table( 'queue' ),
			array(
				'media_id'       => (int) $result['attachment_id'],
				'media_url'      => (string) $result['url'],
				'image_provider' => (string) $result['provider'],
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		return new WP_REST_Response( array( 'ok' => true, 'url' => $result['url'], 'provider' => $result['provider'] ), 200 );
	}

	/**
	 * Send an approved reply back to the social network.
	 */
	public function inbox_reply( WP_REST_Request $request ) {
		global $wpdb;
		$id    = (int) $request->get_param( 'id' );
		$text  = sanitize_textarea_field( (string) $request->get_param( 'reply' ) );
		$table = VMSAI_Install::table( 'inbox' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `$table` WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Interaction not found.', 'vm-social-ai-pro' ) ), 200 );
		}

		$channel = vmsai()->channels()->get( $row['channel'] );
		if ( ! $channel || ! method_exists( $channel, 'reply' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'This channel does not support automated replies.', 'vm-social-ai-pro' ) ), 200 );
		}

		$result = $channel->reply( $row['remote_id'], $text );

		if ( ! empty($result['ok']) ) {
			$wpdb->update( $table, array(
				'status'      => 'replied',
				'replied_at'  => current_time( 'mysql', true ),
				'suggested_reply' => $text
			), array( 'id' => $id ) );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		return new WP_REST_Response( array( 'ok' => false, 'message' => $result['error'] ), 200 );
	}

	/**
	 * Run a visual experiment in the Lab.
	 */
	public function lab_generate_image( WP_REST_Request $request ) {
		$agent_slug = sanitize_key( $request->get_param( 'agent' ) );
		$topic      = sanitize_textarea_field( $request->get_param( 'topic' ) );
		$channel    = sanitize_key( $request->get_param( 'channel' ) );

		$agent = VMSAI_Agents::get( $agent_slug );
		$full_prompt = $topic . '. ' . $agent['visual'];

		$res = vmsai()->image_engine()->create( $full_prompt, array(
			'channel' => $channel,
			'topic'   => $topic,
			'format'  => $agent['style']
		) );

		if ( ! $res['ok'] ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => $res['error'] ), 200 );
		}

		return new WP_REST_Response( array(
			'ok'  => true,
			'url' => $res['url']
		), 200 );
	}

	/**
	 * AI-draft copy for the manual composer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function compose_draft( WP_REST_Request $request ) {
		$result = VMSAI_Manual::draft(
			(string) $request->get_param( 'topic' ),
			sanitize_key( (string) $request->get_param( 'channel' ) ),
			sanitize_key( (string) $request->get_param( 'agent' ) ),
			array(
				'language' => (string) $request->get_param( 'language' ),
				'flavour'  => (string) $request->get_param( 'flavour' ),
			)
		);

		if ( empty( $result['ok'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result['error'] ), 200 );
		}

		return new WP_REST_Response( array( 'ok' => true, 'data' => $result['data'] ), 200 );
	}

	/**
	 * Save a manually composed post to the queue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function compose_create( WP_REST_Request $request ) {
		$result = VMSAI_Manual::create(
			array(
				'channels'      => (array) $request->get_param( 'channels' ),
				'body'          => (string) $request->get_param( 'body' ),
				'title'         => (string) $request->get_param( 'title' ),
				'hashtags'      => (string) $request->get_param( 'hashtags' ),
				'first_comment' => (string) $request->get_param( 'first_comment' ),
				'tags'          => (string) $request->get_param( 'tags' ),
				'cta'           => (string) $request->get_param( 'cta' ),
				'link'          => (string) $request->get_param( 'link' ),
				'format'        => (string) $request->get_param( 'format' ),
				'media_id'      => (int) $request->get_param( 'media_id' ),
				'media_url'     => (string) $request->get_param( 'media_url' ),
				'alt_text'      => (string) $request->get_param( 'alt_text' ),
				'image_prompt'  => (string) $request->get_param( 'image_prompt' ),
				'scheduled_at'  => (string) $request->get_param( 'scheduled_at' ),
				'publish_now'   => (bool) $request->get_param( 'publish_now' ),
				'status'        => (string) $request->get_param( 'status' ),
			)
		);

		if ( empty( $result['ok'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $result['error'] ), 200 );
		}

		return new WP_REST_Response( array( 'ok' => true, 'ids' => $result['ids'] ), 200 );
	}

	/**
	 * The next open scheduling slot for a channel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function compose_next_slot( WP_REST_Request $request ) {
		$channel = sanitize_key( (string) $request->get_param( 'channel' ) );
		$utc     = VMSAI_Manual::next_open_slot( $channel );

		return new WP_REST_Response(
			array(
				'ok'    => true,
				'utc'   => $utc,
				'local' => get_date_from_gmt( $utc, 'Y-m-d H:i' ),
				'label' => get_date_from_gmt( $utc, 'D j M, g:i a' ),
			),
			200
		);
	}

	/**
	 * Generate an image for the manual composer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function compose_image( WP_REST_Request $request ) {
		$prompt  = sanitize_textarea_field( (string) $request->get_param( 'prompt' ) );
		$channel = sanitize_key( (string) $request->get_param( 'channel' ) );
		$format  = sanitize_key( (string) $request->get_param( 'format' ) );

		if ( '' === $prompt ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Describe the image first.', 'vm-social-ai-pro' ) ), 200 );
		}

		$image = vmsai()->image_engine()->create(
			$prompt,
			array( 'channel' => $channel, 'format' => $format ?: 'image', 'prompt' => $prompt )
		);

		if ( empty( $image['ok'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $image['error'] ?? __( 'Image generation failed.', 'vm-social-ai-pro' ) ), 200 );
		}

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'url'      => $image['url'],
				'media_id' => (int) ( $image['attachment_id'] ?? 0 ),
				'alt'      => (string) ( $image['alt'] ?? '' ),
			),
			200
		);
	}

	/**
	 * List the Agent roster.
	 *
	 * @return WP_REST_Response
	 */
	public function agents_list() {
		return new WP_REST_Response( array( 'ok' => true, 'agents' => VMSAI_Agents::all() ), 200 );
	}

	/**
	 * Create or update an agent.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function agents_save( WP_REST_Request $request ) {
		$id = VMSAI_Agents::save( array(
			'id'                 => (int) $request->get_param( 'id' ),
			'name'               => (string) $request->get_param( 'name' ),
			'brief'              => (string) $request->get_param( 'brief' ),
			'tone'               => (string) $request->get_param( 'tone' ),
			'image_style'        => (string) $request->get_param( 'image_style' ),
			'image_style_custom' => (string) $request->get_param( 'image_style_custom' ),
			'channels'           => (array) $request->get_param( 'channels' ),
			'weight'             => (int) $request->get_param( 'weight' ),
			'status'             => (string) $request->get_param( 'status' ),
		) );

		if ( ! $id ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Could not save the agent.', 'vm-social-ai-pro' ) ), 200 );
		}

		return new WP_REST_Response( array( 'ok' => true, 'agent' => VMSAI_Agents::get( $id ) ), 200 );
	}

	/**
	 * Remove an agent from the roster.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function agents_delete( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( ! $id ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Missing agent id.', 'vm-social-ai-pro' ) ), 200 );
		}

		VMSAI_Agents::delete( $id );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * The curated image-style choices agents can pick from.
	 *
	 * @return WP_REST_Response
	 */
	public function agents_styles() {
		$styles = array();

		foreach ( VMSAI_Agents::styles() as $key => $style ) {
			$styles[] = array( 'key' => $key, 'label' => $style['label'] );
		}

		return new WP_REST_Response( array( 'ok' => true, 'styles' => $styles ), 200 );
	}

	public function test_engine( WP_REST_Request $request ) {
		$which = sanitize_key( (string) $request->get_param( 'engine' ) );
		$creds = (array) $request->get_param( 'credentials' );

		if ( $creds ) {
			VMSAI_Settings::update_credentials( $creds );
		}

		if ( 'image' === $which ) {
			$engine = (string) $request->get_param( 'provider' );
			$result = vmsai()->image_engine()->create(
				'A clean, well-lit desk with a notebook and a cup of coffee, soft morning light, shallow depth of field',
				array(
					'channel' => 'instagram',
					'topic' => 'engine test',
					'keyword' => 'engine test',
					'prefer' => $engine
				)
			);

			$res_data = is_array( $result ) ? $result : array();

			return new WP_REST_Response(
				array(
					'ok'       => ! empty( $res_data['ok'] ),
					'provider' => (string) ( $res_data['provider'] ?? '' ),
					'url'      => (string) ( $res_data['url'] ?? '' ),
					'tried'    => (array) ( $res_data['tried'] ?? array() ),
					'message'  => (string) ( $res_data['error'] ?? '' ),
				),
				200
			);
		}

		$result = vmsai()->text_engine()->generate(
			'You are a concise assistant.',
			'Reply with exactly one short sentence confirming you are reachable. Do not guess or state which model you are — you often get this wrong, and the real model id is already reported separately.',
			array( 'max_tokens' => 120, 'temperature' => 0.3 )
		);

		$res_text = is_array( $result ) ? $result : array();

		return new WP_REST_Response(
			array(
				'ok'       => ! empty( $res_text['ok'] ),
				'provider' => (string) ( $res_text['provider'] ?? '' ),
				'model'    => (string) ( $res_text['model'] ?? '' ),
				'text'     => (string) ( $res_text['text'] ?? '' ),
				'tried'    => (array) ( $res_text['tried'] ?? array() ),
				'message'  => (string) ( $res_text['error'] ?? '' ),
			),
			200
		);
	}

	/**
	 * Test one specific provider directly, bypassing the chain and circuit
	 * breaker entirely — the whole-chain test only ever reports the first
	 * provider that happens to succeed, which hides whether any individual
	 * link (e.g. a specific API key or model) is actually working.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function test_provider( WP_REST_Request $request ) {
		$engine_type = sanitize_key( (string) $request->get_param( 'engine' ) );
		$slug        = sanitize_key( (string) $request->get_param( 'provider' ) );
		$creds       = (array) $request->get_param( 'credentials' );

		if ( $creds ) {
			VMSAI_Settings::update_credentials( $creds );
		}

		if ( 'image' === $engine_type ) {
			$result = vmsai()->image_engine()->create(
				'A clean, well-lit desk with a notebook and a cup of coffee, soft morning light, shallow depth of field',
				array( 'channel' => 'instagram', 'topic' => 'engine test', 'keyword' => 'engine test', 'prefer' => $slug )
			);

			$res_data = is_array( $result ) ? $result : array();

			return new WP_REST_Response(
				array(
					'ok'      => ! empty( $res_data['ok'] ),
					'url'     => (string) ( $res_data['url'] ?? '' ),
					'message' => (string) ( $res_data['error'] ?? '' ),
				),
				200
			);
		}

		if ( 'video' === $engine_type ) {
			$result = vmsai()->video_engine()->create(
				'A cinematic drone shot of a beautiful tropical beach at sunset',
				'travel_adventure',
				$slug
			);

			if ( $result['ok'] && ! empty( $result['binary'] ) ) {
				$uploads  = wp_upload_dir();
				$filename = 'vmsai-test-video-' . $slug . '.mp4';
				$path     = trailingslashit( $uploads['basedir'] ) . $filename;
				file_put_contents( $path, $result['binary'] );
				$url = trailingslashit( $uploads['baseurl'] ) . $filename;

				return new WP_REST_Response( array( 'ok' => true, 'url' => $url ), 200 );
			}

			return new WP_REST_Response( array( 'ok' => false, 'message' => $result['error'] ?: 'Video generation failed.' ), 200 );
		}

		$provider = vmsai()->text_engine()->provider( $slug );

		if ( ! $provider ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Unknown text provider.', 'vm-social-ai-pro' ) ), 200 );
		}

		if ( ! $provider->is_configured() ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Not configured — add a key first.', 'vm-social-ai-pro' ) ), 200 );
		}

		$result = $provider->generate(
			'You are a concise assistant.',
			'Reply with exactly one short sentence confirming you are reachable. Do not guess or state which model you are — you often get this wrong, and the real model id is already reported separately.',
			array( 'max_tokens' => 120, 'temperature' => 0.3 )
		);

		$res_data = is_array( $result ) ? $result : array();

		return new WP_REST_Response(
			array(
				'ok'      => ! empty( $res_data['ok'] ),
				'model'   => (string) ( $res_data['model'] ?? '' ),
				'text'    => (string) ( $res_data['text'] ?? '' ),
				'message' => (string) ( $res_data['error'] ?? '' ),
			),
			200
		);
	}

	/**
	 * Test the video engine chain.
	 */
	public function test_video( WP_REST_Request $request ) {
		$creds = (array) $request->get_param( 'credentials' );

		if ( $creds ) {
			VMSAI_Settings::update_credentials( $creds );
		}

		$result = vmsai()->video_engine()->create(
			'A cinematic drone shot of a beautiful tropical beach at sunset',
			'travel_adventure'
		);

		if ( $result['ok'] && ! empty( $result['binary'] ) ) {
			$uploads  = wp_upload_dir();
			$filename = 'vmsai-test-video-chain.mp4';
			$path     = trailingslashit( $uploads['basedir'] ) . $filename;
			file_put_contents( $path, $result['binary'] );
			$url = trailingslashit( $uploads['baseurl'] ) . $filename;

			return new WP_REST_Response( array( 'ok' => true, 'url' => $url, 'tried' => $result['tried'] ?? array() ), 200 );
		}

		return new WP_REST_Response( array( 'ok' => false, 'message' => $result['error'] ?: 'Video generation failed.', 'tried' => $result['tried'] ?? array() ), 200 );
	}

	/**
	 * Refresh the model catalogue.
	 *
	 * @return WP_REST_Response
	 */
	public function sync_models( WP_REST_Request $request ) {
		// Pro UX: Persist any keys the user might have typed before syncing.
		$creds = (array) $request->get_param( 'credentials' );
		if ( $creds ) {
			VMSAI_Settings::update_credentials( $creds );
		}

		$result = VMSAI_Model_Sync::run();

		return new WP_REST_Response(
			array( 'ok' => true, 'synced' => $result['synced'], 'providers' => $result['providers'], 'errors' => $result['errors'] ),
			200
		);
	}

	/**
	 * Cached models for a provider.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function models( WP_REST_Request $request ) {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		$modality = sanitize_key( (string) $request->get_param( 'modality' ) ) ?: 'text';

		return new WP_REST_Response(
			array( 'ok' => true, 'models' => VMSAI_Model_Sync::models( $provider, $modality ) ),
			200
		);
	}

	/**
	 * Test a single channel connection.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function test_channel( WP_REST_Request $request ) {
		try {
			$slug    = sanitize_key( (string) $request->get_param( 'slug' ) );
			$channel = vmsai()->channels()->get( $slug );

			if ( ! $channel ) {
				return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Invalid channel.', 'vm-social-ai-pro' ) ), 200 );
			}

			// Pro Feature: Use submitted credentials for the test if provided.
			$creds = (array) $request->get_param( 'credentials' );
			if ( $creds ) {
				VMSAI_Settings::update_credentials( $creds );
			}

			$result = $channel->test_connection();

			return new WP_REST_Response( $result, 200 );
		} catch ( Throwable $e ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Run the engine loop on demand.
	 *
	 * @return WP_REST_Response
	 */
	public function run_tick() {
		$scheduler = new VMSAI_Scheduler();
		$result    = $scheduler->tick();

		return new WP_REST_Response( array( 'ok' => true, 'result' => $result ), 200 );
	}

	/**
	 * Dashboard payload.
	 *
	 * @return WP_REST_Response
	 */
	public function stats() {
		$campaign = VMSAI_Planner::active_campaign();

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'pace'     => $campaign ? VMSAI_Analytics::pace( (int) $campaign['id'] ) : array(),
				'series'   => $campaign ? VMSAI_Analytics::series( (int) $campaign['id'], 30 ) : array(),
				'channels' => $campaign ? VMSAI_Analytics::by_channel( (int) $campaign['id'] ) : array(),
				'queue'    => VMSAI_Analytics::queue_counts(),
				'health'   => VMSAI_Circuit::state(),
			),
			200
		);
	}

	/**
	 * Fetch real conversations.
	 *
	 * @return WP_REST_Response
	 */
	public function inbox_list() {
		return new WP_REST_Response( array( 'ok' => true, 'comments' => VMSAI_Inbox::list_recent() ), 200 );
	}

	/**
	 * Suggest a reply to a social comment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function suggest_reply( WP_REST_Request $request ) {
		$result = VMSAI_Inbox::suggest_reply(
			(string) $request->get_param( 'text' ),
			(string) $request->get_param( 'author' ),
			(string) $request->get_param( 'channel' ),
			array( 'rating' => $request->get_param( 'rating' ) )
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Analyze performance and update tone.
	 *
	 * @return WP_REST_Response
	 */
	public function reflect_on_performance() {
		$result = VMSAI_Analytics::reflect();
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Perform bulk actions on the queue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function queue_bulk( WP_REST_Request $request ) {
		global $wpdb;
		$action = sanitize_key( (string) $request->get_param( 'bulk_action' ) );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$table  = VMSAI_Install::table( 'queue' );
		$count  = 0;

		switch ( $action ) {
			case 'approve_all':
				$count = $wpdb->update( $table, array( 'status' => 'approved', 'attempts' => 0 ), array( 'status' => 'draft' ), array( '%s', '%d' ), array( '%s' ) );
				break;
			case 'retry_failed':
				$count = $wpdb->update( $table, array( 'status' => 'approved', 'attempts' => 0 ), array( 'status' => 'failed' ), array( '%s', '%d' ), array( '%s' ) );
				break;
			case 'delete_drafts':
				$count = $wpdb->delete( $table, array( 'status' => 'draft' ), array( '%s' ) );
				break;
		}

		return new WP_REST_Response( array( 'ok' => true, 'count' => (int) $count ), 200 );
	}

	/**
	 * Reset failed plan slots back to planned.
	 *
	 * @return WP_REST_Response
	 */
	public function reset_failed_slots() {
		global $wpdb;
		$table = VMSAI_Install::table( 'plan' );

		// Only retry slots that are still due. Resetting a slot dated weeks ago
		// would have it composed on the next tick and then published instantly,
		// because its scheduled time is already in the past — one click could
		// dump a backlog of stale posts live all at once.
		$count = (int) $wpdb->query( // phpcs:ignore
			$wpdb->prepare(
				"UPDATE `$table` SET status = 'planned', last_error = NULL, updated_at = %s
				 WHERE status = 'failed' AND slot_date >= %s", // phpcs:ignore
				current_time( 'mysql', true ),
				current_time( 'Y-m-d' )
			)
		);

		$stale = (int) $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare( "SELECT COUNT(*) FROM `$table` WHERE status = 'failed' AND slot_date < %s", current_time( 'Y-m-d' ) ) // phpcs:ignore
		);

		return new WP_REST_Response( array( 'ok' => true, 'count' => $count, 'skipped_stale' => $stale ), 200 );
	}

	/**
	 * Trigger RAG sync immediately.
	 *
	 * @return WP_REST_Response
	 */
	public function rag_sync() {
		$count = ( new VMSAI_RAG() )->sync( true );
		return new WP_REST_Response( array( 'ok' => true, 'count' => (int) $count ), 200 );
	}

	/**
	 * Handle natural language commander commands.
	 */
	public function commander_chat( WP_REST_Request $request ) {
		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$result  = ( new VMSAI_Commander() )->handle_chat( $message );
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Wipe all transients and runtime caches.
	 *
	 * @return WP_REST_Response
	 */
	public function clear_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_vmsai_%' OR option_name LIKE '_transient_timeout_vmsai_%'" );
		wp_cache_flush();
		VMSAI_Logger::info( 'rest', 'System cache cleared by user.' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Offload existing queue images to R2.
	 *
	 * @return WP_REST_Response
	 */
	public function offload_existing() {
		global $wpdb;
		$table = VMSAI_Install::table( 'queue' );
		$rows  = $wpdb->get_results( "SELECT id, media_id, media_url FROM `$table` WHERE media_id > 0", ARRAY_A ); // phpcs:ignore

		$count = 0;
		foreach ( $rows as $row ) {
			$media_id = (int) $row['media_id'];

			// Skip if already offloaded.
			if ( get_post_meta( $media_id, '_vmsai_remote_url', true ) ) {
				continue;
			}

			$path = get_attached_file( $media_id );
			if ( ! $path || ! file_exists( $path ) ) {
				continue;
			}

			$filename = basename( $path );
			$mime     = get_post_mime_type( $media_id ) ?: 'image/jpeg';

			$remote_url = VMSAI_Storage::upload( $path, $filename, $mime );

			if ( ! is_wp_error( $remote_url ) ) {
				update_post_meta( $media_id, '_vmsai_remote_url', $remote_url );

				// Update the queue record too for immediate UI feedback.
				$wpdb->update( $table, array( 'media_url' => $remote_url ), array( 'id' => $row['id'] ) );

				$count++;
			}
		}

		return new WP_REST_Response( array( 'ok' => true, 'count' => $count ), 200 );
	}

	/**
	 * The campaign generator's angle arc, for the form preview.
	 *
	 * @return WP_REST_Response
	 */
	public function campaign_gen_angles() {
		$angles = array();

		foreach ( VMSAI_Campaign_Gen::angles() as $key => $angle ) {
			$angles[] = array( 'key' => $key, 'label' => $angle['label'], 'format' => $angle['format'] );
		}

		return new WP_REST_Response( array( 'ok' => true, 'angles' => $angles ), 200 );
	}

	/**
	 * Expand a campaign name into a full AI-drafted brief.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function campaign_gen_draft( WP_REST_Request $request ) {
		$result = VMSAI_Campaign_Gen::draft(
			(string) $request->get_param( 'name' ),
			(string) $request->get_param( 'idea' )
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Plan a campaign's worth of slots.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function campaign_gen_create( WP_REST_Request $request ) {
		$result = VMSAI_Campaign_Gen::create(
			array(
				'name'       => (string) $request->get_param( 'name' ),
				'details'    => (string) $request->get_param( 'details' ),
				'start_date' => (string) $request->get_param( 'start_date' ),
				'end_date'   => (string) $request->get_param( 'end_date' ),
				'count'      => (int) $request->get_param( 'count' ),
				'channels'   => array_map( 'sanitize_key', (array) $request->get_param( 'channels' ) ),
				'keyword'    => (string) $request->get_param( 'keyword' ),
				'cta'        => (string) $request->get_param( 'cta' ),
				'language'   => (string) $request->get_param( 'language' ),
				'flavour'    => (string) $request->get_param( 'flavour' ),
			)
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Compose one campaign slot into a finished, queued post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function campaign_gen_compose( WP_REST_Request $request ) {
		try {
			$result = VMSAI_Campaign_Gen::compose_slot( (int) $request->get_param( 'slot_id' ) );
			return new WP_REST_Response( $result, 200 );
		} catch ( Throwable $e ) {
			VMSAI_Logger::error( 'rest', 'Campaign composition fatal error.', array( 'message' => $e->getMessage() ) );
			return new WP_REST_Response( array(
				'ok'    => false,
				/* translators: %s: error message */
				'error' => sprintf( __( 'Composition failed: %s', 'vm-social-ai-pro' ), $e->getMessage() )
			), 500 );
		}
	}

	/**
	 * Secure public endpoint for client approvals.
	 */
	public function portal_update( WP_REST_Request $request ) {
		$id    = (int) $request->get_param( 'id' );
		$token = sanitize_text_field( $request->get_param( 'token' ) );
		$status = sanitize_key( $request->get_param( 'status' ) );
		$notes = sanitize_textarea_field( $request->get_param( 'reviewer_notes' ) );

		if ( ! VMSAI_Crypto::verify_portal_token( $id, $token ) ) {
			return new WP_Error( 'unauthorized', 'Invalid portal token.', array( 'status' => 403 ) );
		}

		global $wpdb;
		$table = VMSAI_Install::table( 'queue' );

		$wpdb->update( $table, array(
			'status' => $status,
			'reviewer_notes' => $notes,
			'updated_at' => current_time( 'mysql', true )
		), array( 'id' => $id ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * One-click health check: test every configured text provider, image
	 * provider, and connected channel in a single pass. Each check bypasses
	 * the chain/circuit breaker the same way engine/test-provider does, so
	 * this reports the truth about every individual link, not just whichever
	 * one happens to win a fallback race.
	 *
	 * @return WP_REST_Response
	 */
	public function test_all() {
		$results = array( 'text' => array(), 'image' => array(), 'channels' => array() );

		foreach ( vmsai()->text_engine()->providers() as $slug => $provider ) {
			if ( ! $provider->is_configured() ) {
				continue;
			}

			try {
				$r = $provider->generate(
					'You are a concise assistant.',
					'Reply with exactly one short sentence confirming you are reachable.',
					array( 'max_tokens' => 60, 'temperature' => 0.3 )
				);
			} catch ( Throwable $e ) {
				$r = array( 'ok' => false, 'error' => $e->getMessage() );
			}

			$results['text'][ $slug ] = array( 'ok' => ! empty( $r['ok'] ), 'message' => (string) ( $r['error'] ?? '' ) );
		}

		foreach ( vmsai()->image_engine()->providers() as $slug => $provider ) {
			if ( ! $provider->is_configured() ) {
				continue;
			}

			try {
				$r = $provider->create(
					'A simple test graphic, a blue circle on a white background',
					array( 'channel' => 'instagram', 'topic' => 'health check', 'keyword' => 'health check' )
				);
			} catch ( Throwable $e ) {
				$r = array( 'ok' => false, 'error' => $e->getMessage() );
			}

			$results['image'][ $slug ] = array( 'ok' => ! empty( $r['ok'] ), 'message' => (string) ( $r['error'] ?? '' ) );
		}

		foreach ( vmsai()->channels()->all() as $slug => $channel ) {
			if ( ! $channel->is_connected() ) {
				continue;
			}

			try {
				$r = $channel->test_connection();
			} catch ( Throwable $e ) {
				$r = array( 'ok' => false, 'message' => $e->getMessage() );
			}

			$results['channels'][ $slug ] = array( 'ok' => ! empty( $r['ok'] ), 'message' => (string) ( $r['message'] ?? '' ) );
		}

		$total  = 0;
		$passed = 0;

		foreach ( $results as $group ) {
			foreach ( $group as $row ) {
				$total++;
				if ( $row['ok'] ) {
					$passed++;
				}
			}
		}

		return new WP_REST_Response( array( 'ok' => true, 'passed' => $passed, 'total' => $total, 'results' => $results ), 200 );
	}

	/**
	 * Posts due out in the next N hours, for the dashboard.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function upcoming( WP_REST_Request $request ) {
		$hours = max( 1, (int) ( $request->get_param( 'hours' ) ?: 48 ) );

		return new WP_REST_Response( array( 'ok' => true, 'rows' => VMSAI_Analytics::upcoming( $hours ) ), 200 );
	}

	/**
	 * Recent publish activity, for the dashboard.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function recent_activity( WP_REST_Request $request ) {
		$limit = max( 1, min( 50, (int) ( $request->get_param( 'limit' ) ?: 10 ) ) );

		return new WP_REST_Response( array( 'ok' => true, 'rows' => VMSAI_Analytics::recent_activity( $limit ) ), 200 );
	}
}
