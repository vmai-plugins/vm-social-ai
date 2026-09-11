<?php
/**
 * Plugin container.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single entry point holding the engines, channels and scheduler.
 */
class VMSAI_Plugin {

	/**
	 * Singleton.
	 *
	 * @var VMSAI_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Text engine.
	 *
	 * @var VMSAI_Text_Engine|null
	 */
	private $text_engine = null;

	/**
	 * Image engine.
	 *
	 * @var VMSAI_Image_Engine|null
	 */
	private $image_engine = null;

	/**
	 * Channel registry.
	 *
	 * @var VMSAI_Channel_Manager|null
	 */
	private $channels = null;

	/**
	 * Video engine.
	 *
	 * @var VMSAI_Video_Engine|null
	 */
	private $video_engine = null;

	/**
	 * GitHub updater.
	 *
	 * @var VMSAI_Github_Updater|null
	 */
	private $updater = null;

	/**
	 * Accessor.
	 *
	 * @return VMSAI_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot.
	 */
	private function __construct() {
		load_plugin_textdomain( 'vm-social-ai-pro', false, dirname( VMSAI_BASENAME ) . '/languages' );

		if ( is_admin() ) {
			VMSAI_Install::maybe_upgrade();
			VMSAI_Install::register_recovery();
			( new VMSAI_Admin() )->register();
			VMSAI_Github_Updater::instance();
		}

		( new VMSAI_Scheduler() )->register();
		( new VMSAI_Rest() )->register();
		( new VMSAI_Events() )->register();
		( new VMSAI_RAG() )->register();
		( new VMSAI_Commander() )->register();
		( new VMSAI_Portal() )->register();
		( new VMSAI_Telegram_Bot() )->register();

		// LOCALIZATION: Sync city insights hook
		add_action( 'vmsai_sync_city_insights', array( 'VMSAI_Research', 'sync_city_insights' ) );

		add_filter( 'cron_schedules', array( 'VMSAI_Install', 'add_schedules' ) );
	}

	/**
	 * Lazily built GitHub updater.
	 *
	 * @return VMSAI_Github_Updater
	 */
	public function updater() {
		if ( null === $this->updater ) {
			$this->updater = VMSAI_Github_Updater::instance();
		}
		return $this->updater;
	}

	/**
	 * Lazily built text engine.
	 *
	 * @return VMSAI_Text_Engine
	 */
	public function text_engine() {
		if ( null === $this->text_engine ) {
			$this->text_engine = new VMSAI_Text_Engine();
		}
		return $this->text_engine;
	}

	/**
	 * Lazily built image engine.
	 *
	 * @return VMSAI_Image_Engine
	 */
	public function image_engine() {
		if ( null === $this->image_engine ) {
			$this->image_engine = new VMSAI_Image_Engine();
		}
		return $this->image_engine;
	}

	/**
	 * Lazily built channel registry.
	 *
	 * @return VMSAI_Channel_Manager
	 */
	public function channels() {
		if ( null === $this->channels ) {
			$this->channels = new VMSAI_Channel_Manager();
		}
		return $this->channels;
	}

	/**
	 * Lazily built video engine.
	 *
	 * @return VMSAI_Video_Engine
	 */
	public function video_engine() {
		if ( null === $this->video_engine ) {
			$this->video_engine = new VMSAI_Video_Engine();
		}
		return $this->video_engine;
	}

	/**
	 * Add a settings shortcut on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=vm-social-ai-pro' ) ) . '">' . esc_html__( 'Dashboard', 'vm-social-ai-pro' ) . '</a>'
		);
		return $links;
	}
}
