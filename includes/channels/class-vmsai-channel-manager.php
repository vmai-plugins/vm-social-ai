<?php
/**
 * Channel registry.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds one instance of every publishing destination.
 */
class VMSAI_Channel_Manager {

	/**
	 * Instantiated channels keyed by slug.
	 *
	 * @var VMSAI_Channel[]
	 */
	private $channels = array();

	/**
	 * Build the registry.
	 */
	public function __construct() {
		$registry = array(
			'facebook'  => 'VMSAI_Channel_Facebook',
			'instagram' => 'VMSAI_Channel_Instagram',
			'threads'   => 'VMSAI_Channel_Threads',
			'bluesky'   => 'VMSAI_Channel_Bluesky',
			'pinterest' => 'VMSAI_Channel_Pinterest',
			'telegram'  => 'VMSAI_Channel_Telegram',
			'x'         => 'VMSAI_Channel_X',
			'linkedin'  => 'VMSAI_Channel_Linkedin',
			'gbp'       => 'VMSAI_Channel_Gbp',
			'tiktok'    => 'VMSAI_Channel_Tiktok',
			'youtube'   => 'VMSAI_Channel_Youtube',
		);

		/**
		 * Filter the channel class map.
		 *
		 * @param array $registry slug => class name.
		 */
		$registry = apply_filters( 'vmsai_channels', $registry );

		foreach ( $registry as $slug => $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			// One misbehaving channel (built-in or added via the
			// vmsai_channels filter) must not take every other channel
			// down with it — without this, a throw/fatal in any single
			// constructor aborted the whole loop, breaking publishing and
			// the Inbox for channels that were working fine.
			try {
				$this->channels[ $slug ] = new $class();
			} catch ( Throwable $e ) {
				VMSAI_Logger::error( 'channel.manager', "Failed to load channel '{$slug}': " . $e->getMessage() );
			}
		}
	}

	/**
	 * All channels.
	 *
	 * @return VMSAI_Channel[]
	 */
	public function all() {
		return $this->channels;
	}

	/**
	 * One channel.
	 *
	 * @param string $slug Channel slug.
	 * @return VMSAI_Channel|null
	 */
	public function get( $slug ) {
		return $this->channels[ $slug ] ?? null;
	}

	/**
	 * Channel objects that are switched on and fully credentialed.
	 *
	 * ready() hands back slugs, which is what the planner and composer work
	 * in. Anything that needs to talk to the channel itself — the Inbox
	 * pulling comments, for one — needs the objects, and called enabled()
	 * for them long before the method existed.
	 *
	 * @return VMSAI_Channel[] Keyed by slug.
	 */
	public function enabled() {
		$out = array();

		foreach ( $this->channels as $slug => $channel ) {
			if ( VMSAI_Settings::channel_enabled( $slug ) && $channel->is_connected() ) {
				$out[ $slug ] = $channel;
			}
		}

		return $out;
	}

	/**
	 * Channels that are both switched on and fully credentialed.
	 *
	 * @return string[]
	 */
	public function ready() {
		return array_keys( $this->enabled() );
	}
}
