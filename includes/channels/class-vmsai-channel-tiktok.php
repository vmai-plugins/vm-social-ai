<?php
/**
 * TikTok channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Publishes to TikTok via the Content Posting API.
 * Requires a TikTok for Developers account and a valid Access Token.
 */
class VMSAI_Channel_Tiktok extends VMSAI_Channel {

	const API_BASE = 'https://open.tiktokapis.com/v2';

	public function slug() {
		return 'tiktok';
	}

	public function label() {
		return 'TikTok';
	}

	public function credential_fields() {
		return array(
			'tiktok_access_token' => __( 'TikTok Access Token', 'vm-social-ai-pro' ),
		);
	}

	public function test_connection() {
		$token = VMSAI_Settings::credential( 'tiktok_access_token' );
		if ( ! $token ) {
			return $this->fail( __( 'Missing TikTok Access Token.', 'vm-social-ai-pro' ) );
		}

		$response = VMSAI_Http::get( self::API_BASE . '/user/info/?fields=display_name,username', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			'scope'   => 'channel.tiktok'
		) );

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$user = $response['json']['data']['user'] ?? array();
		return array( 'ok' => true, 'message' => sprintf( /* translators: %s: TikTok username */ __( 'Connected to TikTok as @%s.', 'vm-social-ai-pro' ), $user['username'] ?? 'user' ) );
	}

	public function publish( array $post ) {
		$token = VMSAI_Settings::credential( 'tiktok_access_token' );

		// TikTok ONLY accepts Video for the Posting API
		$video_file = vmsai()->video_engine()->produce( $post );

		if ( ! $video_file ) {
			return $this->fail( __( 'TikTok requires a video asset. Branded production failed.', 'vm-social-ai-pro' ) );
		}

		$filename = basename( $video_file );
		$url      = '';

		// Offload to public URL (R2/S3) as TikTok must fetch the binary
		if ( 'off' !== VMSAI_Settings::get( 'remote_storage' ) ) {
			$res = VMSAI_Storage::upload( $video_file, $filename, 'video/mp4' );
			if ( ! is_wp_error( $res ) ) {
				$url = $res;
			}
		}

		if ( ! $url ) {
			@unlink( $video_file );
			return $this->fail( __( 'TikTok publishing requires Remote Storage (R2/S3) to be enabled and configured.', 'vm-social-ai-pro' ) );
		}

		// Step 1: Initialize Upload
		$init = VMSAI_Http::post( self::API_BASE . '/post/publish/video/init/', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json'
			),
			'json' => array(
				'post_info' => array(
					'title' => mb_substr( $post['title'], 0, 80 ),
					'text'  => mb_substr( $this->caption( $post, false, true ), 0, 150 ),
					'video_url' => $url
				),
				'source_info' => array(
					'source' => 'PULL_FROM_URL',
					'video_size' => filesize( $video_file )
				)
			),
			'scope' => 'channel.tiktok'
		) );

		@unlink( $video_file );

		if ( ! $init['ok'] ) {
			return $this->fail( $init['error'] );
		}

		$publish_id = $init['json']['data']['publish_id'] ?? '';

		if ( ! $publish_id ) {
			return $this->fail( __( 'TikTok did not return a publish id.', 'vm-social-ai-pro' ) );
		}

		// PULL_FROM_URL is asynchronous: init only means TikTok accepted the
		// request and will fetch/process the video in the background. Poll
		// the real status for a bounded window so a definite FAILED (bad
		// URL, rejected format, moderation reject) is caught and reported
		// instead of the queue row being marked "published" while nothing
		// ever actually posted. If it's still processing when the window
		// closes, report success (best effort) rather than risk a retry
		// re-uploading and double-posting a video that may finish fine.
		$deadline = time() + 40;
		while ( time() < $deadline ) {
			sleep( 5 );

			$status = VMSAI_Http::post( self::API_BASE . '/post/publish/status/fetch/', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'json'  => array( 'publish_id' => $publish_id ),
				'scope' => 'channel.tiktok',
			) );

			if ( ! $status['ok'] ) {
				continue;
			}

			$state = (string) ( $status['json']['data']['status'] ?? '' );

			if ( 'PUBLISH_COMPLETE' === $state ) {
				break;
			}

			if ( 'FAILED' === $state ) {
				$reason = (string) ( $status['json']['data']['fail_reason'] ?? 'TikTok rejected the post.' );
				return $this->fail( $reason );
			}
		}

		return $this->ok( $publish_id, 'https://www.tiktok.com/' ); // TikTok doesn't return permalink immediately
	}
}
