<?php
/**
 * 1-Click OAuth Manager for VM Social AI.
 * Handles unified Meta (Facebook & Instagram) and Google (YouTube) authentication.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_OAuth {

	/** Connect Hub Broker endpoint for zero-config 1-Click OAuth. */
	const BROKER_URL = 'https://connect.vmstudio.digital';

	/** Meta Graph API endpoint. */
	const META_GRAPH = 'https://graph.facebook.com/v21.0';

	/** State TTL in seconds (24h). */
	const STATE_TTL = 86400;

	/* ---------------------------------------------------------------- Meta (Facebook & Instagram) */

	/**
	 * Build 1-Click Meta Connect URL.
	 *
	 * @return string
	 */
	public static function meta_connect_url() {
		$state = hash( 'sha256', random_bytes( 32 ) . time() );
		set_transient( 'vmsai_meta_oauth_state', $state, self::STATE_TTL );

		$params = array(
			'service'      => 'meta',
			'site_url'     => home_url(),
			'redirect_uri' => admin_url( 'admin.php?page=' . VMSAI_Admin::SLUG . '&tab=settings&vmsai_oauth=meta_callback' ),
			'state'        => $state,
		);

		return self::BROKER_URL . '/oauth/meta/start?' . http_build_query( $params );
	}

	/**
	 * Verify state for Meta OAuth.
	 *
	 * @param string $state Received state.
	 * @return bool
	 */
	public static function verify_meta_state( $state ) {
		if ( empty( $state ) ) {
			return false;
		}
		$expected = get_transient( 'vmsai_meta_oauth_state' );
		if ( empty( $expected ) || ! hash_equals( (string) $expected, (string) $state ) ) {
			return false;
		}
		delete_transient( 'vmsai_meta_oauth_state' );
		return true;
	}

	/**
	 * Process Meta callback from Connect Hub broker or direct OAuth.
	 *
	 * @param array $params Query parameters.
	 * @return true|WP_Error
	 */
	public static function handle_meta_callback( array $params ) {
		$state = isset( $params['state'] ) ? sanitize_text_field( wp_unslash( $params['state'] ) ) : '';
		if ( ! self::verify_meta_state( $state ) ) {
			return new WP_Error( 'vmsai_oauth_state', __( 'That authorization link has expired. Please try connecting again.', 'vm-social-ai-pro' ) );
		}

		$page_id    = isset( $params['page_id'] ) ? sanitize_text_field( wp_unslash( $params['page_id'] ) ) : '';
		$page_token = isset( $params['page_token'] ) ? sanitize_text_field( wp_unslash( $params['page_token'] ) ) : '';
		$page_name  = isset( $params['page_name'] ) ? sanitize_text_field( wp_unslash( $params['page_name'] ) ) : '';
		$ig_id      = isset( $params['ig_id'] ) ? sanitize_text_field( wp_unslash( $params['ig_id'] ) ) : '';
		$ig_user    = isset( $params['ig_username'] ) ? sanitize_text_field( wp_unslash( $params['ig_username'] ) ) : '';
		$ig_pic     = isset( $params['ig_picture'] ) ? esc_url_raw( wp_unslash( $params['ig_picture'] ) ) : '';

		// If user token was passed instead of direct page token, resolve via Graph API
		if ( empty( $page_token ) && ! empty( $params['user_token'] ) ) {
			$user_token = sanitize_text_field( wp_unslash( $params['user_token'] ) );
			$accounts   = VMSAI_Http::get(
				self::META_GRAPH . '/me/accounts?fields=id,name,access_token,category,instagram_business_account{id,username,profile_picture_url}&access_token=' . rawurlencode( $user_token ),
				array( 'scope' => 'oauth.meta', 'retries' => 1 )
			);

			if ( $accounts['ok'] && ! empty( $accounts['json']['data'][0] ) ) {
				$primary    = $accounts['json']['data'][0];
				$page_id    = $primary['id'] ?? '';
				$page_name  = $primary['name'] ?? '';
				$page_token = $primary['access_token'] ?? '';

				if ( ! empty( $primary['instagram_business_account'] ) ) {
					$ig_obj  = $primary['instagram_business_account'];
					$ig_id   = $ig_obj['id'] ?? '';
					$ig_user = $ig_obj['username'] ?? '';
					$ig_pic  = $ig_obj['profile_picture_url'] ?? '';
				}
			}
		}

		if ( empty( $page_token ) || empty( $page_id ) ) {
			return new WP_Error( 'vmsai_oauth_failed', __( 'No Facebook Page with posting permissions was found.', 'vm-social-ai-pro' ) );
		}

		// Update encrypted credentials
		$creds = array(
			'fb_page_id'    => $page_id,
			'fb_page_token' => $page_token,
		);
		if ( ! empty( $ig_id ) ) {
			$creds['ig_user_id'] = $ig_id;
		}
		VMSAI_Settings::update_credentials( $creds );

		// Update channel metadata
		$meta_patch = array(
			'meta_connected'     => 1,
			'fb_page_name'       => $page_name,
			'ig_username'        => $ig_user,
			'ig_profile_picture' => $ig_pic,
		);

		// Automatically enable facebook and instagram channels
		$enabled = (array) VMSAI_Settings::get( 'enabled_channels', array() );
		$enabled['facebook'] = 1;
		if ( ! empty( $ig_id ) ) {
			$enabled['instagram'] = 1;
		}
		$meta_patch['enabled_channels'] = $enabled;

		VMSAI_Settings::update( $meta_patch );

		// Reset circuit breakers for channels
		VMSAI_Circuit::reset( 'channel:facebook' );
		VMSAI_Circuit::reset( 'channel:instagram' );

		return true;
	}

	/**
	 * Disconnect Meta channels.
	 *
	 * @return void
	 */
	public static function meta_disconnect() {
		VMSAI_Settings::update_credentials(
			array(
				'fb_page_id'    => '',
				'fb_page_token' => '',
				'ig_user_id'    => '',
			)
		);

		$enabled = (array) VMSAI_Settings::get( 'enabled_channels', array() );
		$enabled['facebook']  = 0;
		$enabled['instagram'] = 0;

		VMSAI_Settings::update(
			array(
				'meta_connected'     => 0,
				'fb_page_name'       => '',
				'ig_username'        => '',
				'ig_profile_picture' => '',
				'enabled_channels'   => $enabled,
			)
		);

		delete_transient( 'vmsai_meta_oauth_state' );
		VMSAI_Circuit::reset( 'channel:facebook' );
		VMSAI_Circuit::reset( 'channel:instagram' );
	}

	/**
	 * Get current Meta connected profile status.
	 *
	 * @return array
	 */
	public static function meta_profile() {
		$page_id    = VMSAI_Settings::credential( 'fb_page_id' );
		$page_token = VMSAI_Settings::credential( 'fb_page_token' );
		$ig_id      = VMSAI_Settings::credential( 'ig_user_id' );

		$connected = ( ! empty( $page_id ) && ! empty( $page_token ) );

		return array(
			'connected'   => $connected,
			'fb_page_id'  => $page_id,
			'fb_page_name'=> VMSAI_Settings::get( 'fb_page_name', 'Facebook Page' ),
			'ig_user_id'  => $ig_id,
			'ig_username' => VMSAI_Settings::get( 'ig_username', '' ),
			'ig_picture'  => VMSAI_Settings::get( 'ig_profile_picture', '' ),
		);
	}

	/* ---------------------------------------------------------------- Google / YouTube */

	/**
	 * Build 1-Click YouTube Connect URL.
	 *
	 * @return string
	 */
	public static function youtube_connect_url() {
		$state = hash( 'sha256', random_bytes( 32 ) . time() );
		set_transient( 'vmsai_google_oauth_state', $state, self::STATE_TTL );

		$params = array(
			'service'      => 'youtube',
			'site_url'     => home_url(),
			'redirect_uri' => admin_url( 'admin.php?page=' . VMSAI_Admin::SLUG . '&tab=settings&vmsai_oauth=youtube_callback' ),
			'state'        => $state,
		);

		return self::BROKER_URL . '/oauth/google/start?' . http_build_query( $params );
	}

	/**
	 * Verify state for Google OAuth.
	 *
	 * @param string $state Received state.
	 * @return bool
	 */
	public static function verify_google_state( $state ) {
		if ( empty( $state ) ) {
			return false;
		}
		$expected = get_transient( 'vmsai_google_oauth_state' );
		if ( empty( $expected ) || ! hash_equals( (string) $expected, (string) $state ) ) {
			return false;
		}
		delete_transient( 'vmsai_google_oauth_state' );
		return true;
	}

	/**
	 * Process YouTube callback from Connect Hub broker.
	 *
	 * @param array $params Query parameters.
	 * @return true|WP_Error
	 */
	public static function handle_youtube_callback( array $params ) {
		$state = isset( $params['state'] ) ? sanitize_text_field( wp_unslash( $params['state'] ) ) : '';
		if ( ! self::verify_google_state( $state ) ) {
			return new WP_Error( 'vmsai_oauth_state', __( 'That authorization link has expired. Please try connecting again.', 'vm-social-ai-pro' ) );
		}

		$refresh = isset( $params['refresh_token'] ) ? sanitize_text_field( wp_unslash( $params['refresh_token'] ) ) : '';
		if ( empty( $refresh ) ) {
			return new WP_Error( 'vmsai_oauth_token', __( 'No refresh token returned by Connect Hub.', 'vm-social-ai-pro' ) );
		}

		// Store encrypted refresh token
		VMSAI_Settings::update_credentials( array( 'google_refresh_token' => $refresh ) );

		if ( ! empty( $params['access_token'] ) ) {
			$ttl = isset( $params['expires_in'] ) ? max( 60, (int) $params['expires_in'] - 60 ) : 3540;
			set_transient( 'vmsai_google_access_token', sanitize_text_field( wp_unslash( $params['access_token'] ) ), $ttl );
		}

		// Auto-discover YouTube channel
		self::refresh_youtube_channel_profile();

		// Auto enable YouTube channel
		$enabled = (array) VMSAI_Settings::get( 'enabled_channels', array() );
		$enabled['youtube'] = 1;
		VMSAI_Settings::update(
			array(
				'youtube_connected' => 1,
				'enabled_channels'  => $enabled,
			)
		);

		VMSAI_Circuit::reset( 'channel:youtube' );

		return true;
	}

	/**
	 * Discover YouTube channel title and subscriber count.
	 *
	 * @return array|null
	 */
	public static function refresh_youtube_channel_profile() {
		$token = VMSAI_Channel_Gbp::access_token();
		if ( empty( $token ) ) {
			return null;
		}

		$res = VMSAI_Http::get(
			'https://www.googleapis.com/youtube/v3/channels?part=snippet,statistics&mine=true',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'scope'   => 'channel.youtube',
				'retries' => 1,
			)
		);

		if ( $res['ok'] && ! empty( $res['json']['items'][0] ) ) {
			$item = $res['json']['items'][0];
			$info = array(
				'yt_channel_id'       => $item['id'] ?? '',
				'yt_channel_title'    => $item['snippet']['title'] ?? '',
				'yt_channel_thumbnail'=> $item['snippet']['thumbnails']['default']['url'] ?? '',
				'yt_subscriber_count' => (int) ( $item['statistics']['subscriberCount'] ?? 0 ),
				'youtube_connected'   => 1,
			);
			VMSAI_Settings::update( $info );
			return $info;
		}

		return null;
	}

	/**
	 * Disconnect YouTube.
	 *
	 * @return void
	 */
	public static function youtube_disconnect() {
		VMSAI_Settings::update_credentials( array( 'google_refresh_token' => '' ) );

		$enabled = (array) VMSAI_Settings::get( 'enabled_channels', array() );
		$enabled['youtube'] = 0;

		VMSAI_Settings::update(
			array(
				'youtube_connected'    => 0,
				'yt_channel_id'        => '',
				'yt_channel_title'     => '',
				'yt_channel_thumbnail' => '',
				'yt_subscriber_count'  => 0,
				'enabled_channels'     => $enabled,
			)
		);

		delete_transient( 'vmsai_google_oauth_state' );
		delete_transient( 'vmsai_google_access_token' );
		VMSAI_Circuit::reset( 'channel:youtube' );
	}

	/**
	 * Get current YouTube channel profile.
	 *
	 * @return array
	 */
	public static function youtube_profile() {
		$token = VMSAI_Settings::credential( 'google_refresh_token' );
		$connected = ! empty( $token );

		return array(
			'connected'        => $connected,
			'channel_id'       => VMSAI_Settings::get( 'yt_channel_id', '' ),
			'channel_title'    => VMSAI_Settings::get( 'yt_channel_title', 'YouTube Channel' ),
			'channel_thumbnail'=> VMSAI_Settings::get( 'yt_channel_thumbnail', '' ),
			'subscriber_count' => (int) VMSAI_Settings::get( 'yt_subscriber_count', 0 ),
		);
	}
}
