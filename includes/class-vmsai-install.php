<?php
/**
 * Activation, schema and teardown.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and migrates the plugin database schema.
 */
class VMSAI_Install {

	const DB_VERSION    = '1.9.3';
	const OPT_DB_VER    = 'vmsai_db_version';
	const CRON_TICK     = 'vmsai_cron_tick';
	const CRON_PLANNER  = 'vmsai_cron_planner';
	const CRON_MODELS   = 'vmsai_cron_model_sync';
	const CRON_METRICS  = 'vmsai_cron_metrics';
	const CRON_NEWS     = 'vmsai_cron_news_sync';
	const CRON_REFLECT  = 'vmsai_cron_reflect';
	const CRON_RECYCLE  = 'vmsai_cron_recycle';
	const CRON_INBOX    = 'vmsai_cron_inbox_sync';
	const CRON_CLEANUP  = 'vmsai_cron_cleanup';

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::create_roles();
		self::seed_options();
		self::schedule_events();
		update_option( self::OPT_DB_VER, self::DB_VERSION, false );
		set_transient( 'vmsai_activation_redirect', 1, 60 );
	}

	/**
	 * Run on deactivation. Data is preserved; only timers stop.
	 *
	 * @return void
	 */
	public static function deactivate() {
		foreach ( array( self::CRON_TICK, self::CRON_PLANNER, self::CRON_MODELS, self::CRON_METRICS, self::CRON_NEWS, self::CRON_REFLECT, self::CRON_RECYCLE, self::CRON_INBOX, self::CRON_CLEANUP ) as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
	}

	/**
	 * Compare stored schema version and upgrade if needed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::OPT_DB_VER ) !== self::DB_VERSION ) {
			self::create_tables();
			self::create_roles();
			self::schedule_events();
			update_option( self::OPT_DB_VER, self::DB_VERSION, false );
		}
	}

	/**
	 * Create specialized roles for social automation.
	 */
	public static function create_roles() {
		// 1. AI Creator: Can draft and plan, but not publish or change settings.
		add_role( 'vmsai_creator', 'Social AI Creator', array(
			'read' => true,
			'vmsai_read' => true,
			'vmsai_edit' => true,
			'vmsai_plan' => true,
		) );

		// 2. AI Manager: Full access.
		add_role( 'vmsai_manager', 'Social AI Manager', array(
			'read' => true,
			'vmsai_read' => true,
			'vmsai_edit' => true,
			'vmsai_plan' => true,
			'vmsai_publish' => true,
			'vmsai_manage_keys' => true,
		) );

		// Add all caps to Administrator
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array( 'vmsai_read', 'vmsai_edit', 'vmsai_plan', 'vmsai_publish', 'vmsai_manage_keys' ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Table name helper.
	 *
	 * @param string $name Bare table name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'vmsai_' . $name;
	}

	/**
	 * Create or migrate all tables via dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$sql     = array();

		// Brain: durable key/value memory of the business identity.
		$sql[] = 'CREATE TABLE ' . self::table( 'brain' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			bucket VARCHAR(60) NOT NULL DEFAULT 'identity',
			meta_key VARCHAR(120) NOT NULL,
			meta_value LONGTEXT NULL,
			weight FLOAT NOT NULL DEFAULT 1,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY bucket_key (bucket, meta_key)
		) $charset;";

		// Campaign: a growth run with a target and a horizon.
		$sql[] = 'CREATE TABLE ' . self::table( 'campaigns' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			objective VARCHAR(40) NOT NULL DEFAULT 'reach',
			target_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
			horizon_days SMALLINT UNSIGNED NOT NULL DEFAULT 50,
			language VARCHAR(10) DEFAULT 'en',
			locale_flavour VARCHAR(60) DEFAULT '',
			channels TEXT NULL,
			pillars LONGTEXT NULL,
			starts_on DATE NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status)
		) $charset;";

		// Plan items: one row per planned slot (day x channel x pillar).
		$sql[] = 'CREATE TABLE ' . self::table( 'plan' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			agent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			day_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			slot_date DATE NULL,
			slot_time TIME NULL,
			channel VARCHAR(40) NOT NULL,
			pillar VARCHAR(60) NOT NULL DEFAULT 'value',
			format VARCHAR(40) NOT NULL DEFAULT 'image',
			language VARCHAR(10) DEFAULT NULL,
			locale_flavour VARCHAR(60) DEFAULT NULL,
			topic TEXT NULL,
			keyword VARCHAR(190) NULL,
			angle TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'planned',
			queue_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			last_error TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY campaign_status (campaign_id, status),
			KEY slot_date (slot_date),
			KEY agent_id (agent_id)
		) $charset;";

		// Agents: named content personas, each with its own purpose, tone,
		// image style, channel targeting and posting weight.
		$sql[] = 'CREATE TABLE ' . self::table( 'agents' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			brief TEXT NULL,
			tone TEXT NULL,
			image_style VARCHAR(40) NOT NULL DEFAULT 'photo',
			image_style_custom TEXT NULL,
			channels TEXT NULL,
			weight TINYINT UNSIGNED NOT NULL DEFAULT 10,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status)
		) $charset;";

		// Queue: generated, approved and dispatched posts.
		$sql[] = 'CREATE TABLE ' . self::table( 'queue' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			plan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			channel VARCHAR(40) NOT NULL,
			format VARCHAR(40) NOT NULL DEFAULT 'image',
			title VARCHAR(255) NULL,
			body LONGTEXT NULL,
			hashtags TEXT NULL,
			first_comment TEXT NULL,
			tags VARCHAR(255) NULL,
			cta VARCHAR(255) NULL,
			link VARCHAR(500) NULL,
			media_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			media_url VARCHAR(500) NULL,
			image_prompt TEXT NULL,
			alt_text VARCHAR(500) NULL,
			seo_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			viral_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			critic_notes TEXT NULL,
			reviewer_notes TEXT NULL,
			video_hook VARCHAR(255) NULL,
			video_template VARCHAR(60) NULL,
			text_provider VARCHAR(40) NULL,
			image_provider VARCHAR(40) NULL,
			scheduled_at DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			is_evergreen TINYINT(1) NOT NULL DEFAULT 0,
			product_data TEXT NULL,
			last_error TEXT NULL,
			variant VARCHAR(20) NULL,
			remote_id VARCHAR(190) NULL,
			permalink VARCHAR(500) NULL,
			published_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY dispatch (status, scheduled_at),
			KEY channel (channel),
			KEY plan_id (plan_id),
			KEY parent_id (parent_id)
		) $charset;";

		// Inbox: comments, mentions and AI-suggested replies.
		$sql[] = 'CREATE TABLE ' . self::table( 'inbox' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			queue_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			channel VARCHAR(40) NOT NULL,
			remote_id VARCHAR(190) NOT NULL,
			author_name VARCHAR(100) NULL,
			author_handle VARCHAR(100) NULL,
			content TEXT NULL,
			suggested_reply TEXT NULL,
			intent VARCHAR(40) NULL,
			lead_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			received_at DATETIME NULL,
			replied_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY remote_msg (channel, remote_id),
			KEY queue_id (queue_id)
		) $charset;";

		// Metrics: per-post performance snapshots pulled back from each network.
		$sql[] = 'CREATE TABLE ' . self::table( 'metrics' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			queue_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			channel VARCHAR(40) NOT NULL,
			captured_on DATE NOT NULL,
			impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
			reach BIGINT UNSIGNED NOT NULL DEFAULT 0,
			engagements BIGINT UNSIGNED NOT NULL DEFAULT 0,
			clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
			followers BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY snapshot (queue_id, captured_on),
			KEY campaign_day (campaign_id, captured_on)
		) $charset;";

		// Models: live-synced model catalogue per provider.
		$sql[] = 'CREATE TABLE ' . self::table( 'models' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			provider VARCHAR(40) NOT NULL,
			model_id VARCHAR(190) NOT NULL,
			label VARCHAR(190) NULL,
			modality VARCHAR(20) NOT NULL DEFAULT 'text',
			context_length INT UNSIGNED NOT NULL DEFAULT 0,
			is_free TINYINT(1) NOT NULL DEFAULT 0,
			meta LONGTEXT NULL,
			synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY provider_model (provider, model_id),
			KEY modality (modality)
		) $charset;";

		// Logs: engine + dispatch audit trail.
		$sql[] = 'CREATE TABLE ' . self::table( 'logs' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(12) NOT NULL DEFAULT 'info',
			scope VARCHAR(40) NOT NULL DEFAULT 'core',
			message TEXT NULL,
			context LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY level_scope (level, scope),
			KEY created_at (created_at)
		) $charset;";

		// Usage: token counts, costs and activity audit.
		$sql[] = 'CREATE TABLE ' . self::table( 'usage' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			provider VARCHAR(40) NOT NULL,
			model VARCHAR(190) NOT NULL,
			modality VARCHAR(20) NOT NULL DEFAULT 'text',
			usage_type VARCHAR(40) NOT NULL DEFAULT 'generation',
			tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
			tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
			cost DECIMAL(12,6) NOT NULL DEFAULT 0.000000,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY provider_model (provider, model),
			KEY created_at (created_at)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Write default settings without clobbering existing ones.
	 *
	 * @return void
	 */
	private static function seed_options() {
		if ( ! get_option( VMSAI_Settings::OPTION ) ) {
			$defaults = VMSAI_Settings::defaults();
			// Multi-site setup: Ensure video is on by default for new installs
			$defaults['video_source'] = 'pollinations';
			add_option( VMSAI_Settings::OPTION, $defaults, '', false );
		}
		if ( ! get_option( VMSAI_Settings::CRED_OPTION ) ) {
			add_option( VMSAI_Settings::CRED_OPTION, array(), '', false );
		}
	}

	/**
	 * Register recurring jobs.
	 *
	 * @return void
	 */
	public static function schedule_events() {
		add_filter( 'cron_schedules', array( 'VMSAI_Install', 'add_schedules' ) );

		if ( ! wp_next_scheduled( self::CRON_TICK ) ) {
			wp_schedule_event( time() + 60, 'vmsai_five_minutes', self::CRON_TICK );
		}
		if ( ! wp_next_scheduled( self::CRON_PLANNER ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_PLANNER );
		}
		if ( ! wp_next_scheduled( self::CRON_MODELS ) ) {
			wp_schedule_event( time() + 120, 'twicedaily', self::CRON_MODELS );
		}
		if ( ! wp_next_scheduled( self::CRON_METRICS ) ) {
			wp_schedule_event( time() + 900, 'hourly', self::CRON_METRICS );
		}
		if ( ! wp_next_scheduled( self::CRON_NEWS ) ) {
			wp_schedule_event( time() + 1800, 'twicedaily', self::CRON_NEWS );
		}
		if ( ! wp_next_scheduled( self::CRON_REFLECT ) ) {
			wp_schedule_event( time() + 3600, 'weekly', self::CRON_REFLECT );
		}
		if ( ! wp_next_scheduled( self::CRON_RECYCLE ) ) {
			wp_schedule_event( time() + 7200, 'weekly', self::CRON_RECYCLE );
		}
		if ( ! wp_next_scheduled( self::CRON_INBOX ) ) {
			wp_schedule_event( time() + 600, 'hourly', self::CRON_INBOX );
		}
		if ( ! wp_next_scheduled( self::CRON_CLEANUP ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::CRON_CLEANUP );
		}
	}

	/**
	 * Self-healing: ensure timers are always running.
	 */
	public static function register_recovery() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}
		self::schedule_events();
	}

	/**
	 * Custom intervals for background tasks. Kept as the single source of
	 * truth — VMSAI_Scheduler no longer defines its own copy.
	 *
	 * @param array $schedules Registered schedules.
	 * @return array
	 */
	public static function add_schedules( $schedules ) {
		$schedules['vmsai_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes (Social AI)', 'vm-social-ai-pro' ),
		);
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display'  => __( 'Once Weekly', 'vm-social-ai-pro' ),
		);
		return $schedules;
	}
}

