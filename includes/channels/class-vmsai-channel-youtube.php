<?php
/**
 * YouTube Shorts channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Uploads Shorts via the YouTube Data API.
 *
 * A still image is not a video, so this channel renders one first. If ffmpeg
 * is available on the server it builds a short vertical clip with a slow
 * Ken Burns push from the generated image. Without ffmpeg the post is held
 * rather than published, because a failed upload is worse than a delay.
 */
class VMSAI_Channel_Youtube extends VMSAI_Channel {

	const UPLOAD = 'https://www.googleapis.com/upload/youtube/v3/videos';
	const API    = 'https://www.googleapis.com/youtube/v3';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'youtube';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'YouTube Shorts';
	}

	/**
	 * Required credentials. The Google OAuth trio is shared with GBP.
	 *
	 * @return array
	 */
	public function credential_fields() {
		return array(
			'google_client_id'     => __( 'OAuth client ID', 'vm-social-ai-pro' ),
			'google_client_secret' => __( 'OAuth client secret', 'vm-social-ai-pro' ),
			'google_refresh_token' => __( 'Refresh token', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Test the YouTube connection by fetching the channel name.
	 *
	 * @return array
	 */
	public function test_connection() {
		$token = VMSAI_Channel_Gbp::access_token();

		if ( ! $token ) {
			return $this->fail( __( 'Could not obtain a Google access token.', 'vm-social-ai-pro' ) );
		}

		$response = VMSAI_Http::get(
			self::API . '/channels?' . http_build_query( array( 'part' => 'snippet', 'mine' => 'true' ) ),
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ), 'scope' => 'channel.youtube', 'retries' => 0 )
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$items   = (array) ( $response['json']['items'] ?? array() );
		$snippet = is_array( $items[0] ?? null ) ? ( $items[0]['snippet'] ?? array() ) : array();
		$channel = is_array( $snippet ) ? ( $snippet['title'] ?? 'YouTube Channel' ) : 'YouTube Channel';

		return array( 'ok' => true, 'message' => sprintf( /* translators: %s: youtube channel name */ __( 'Connected to "%s".', 'vm-social-ai-pro' ), $channel ) );
	}

	/**
	 * Publish a Short.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function publish( array $post ) {
		$token = VMSAI_Channel_Gbp::access_token();

		if ( ! $token ) {
			return $this->fail( __( 'Could not obtain a Google access token.', 'vm-social-ai-pro' ) );
		}

		// Use the high-end production suite for branded Shorts.
		$video_file = vmsai()->video_engine()->produce( $post );

		if ( ! $video_file ) {
			return $this->fail( __( 'No video source could be produced.', 'vm-social-ai-pro' ) );
		}

		$title = mb_substr( wp_strip_all_tags( (string) $post['title'] ), 0, 95 );

		// The #Shorts tag in the description is what classifies the upload.
		$description = trim( $this->caption( $post, true, true ) . "\n\n#Shorts" );

		$metadata = array(
			'snippet' => array(
				'title'       => $title,
				'description' => mb_substr( $description, 0, 4900 ),
				'tags'        => $this->tags( $post ),
				'categoryId'  => '22',
			),
			'status'  => array(
				'privacyStatus'           => 'public',
				'selfDeclaredMadeForKids' => false,
			),
		);

		$session = VMSAI_Http::post(
			self::UPLOAD . '?' . http_build_query( array( 'uploadType' => 'resumable', 'part' => 'snippet,status' ) ),
			array(
				'headers' => array(
					'Authorization'           => 'Bearer ' . $token,
					'X-Upload-Content-Type'   => 'video/mp4',
					'X-Upload-Content-Length' => (string) filesize( $video_file ),
				),
				'json'    => $metadata,
				'scope'   => 'channel.youtube',
				'timeout' => 60,
			)
		);

		if ( ! $session['ok'] ) {
			$this->cleanup( $video_file );
			return $this->fail( $session['error'] );
		}

		$headers  = (array) ( $session['headers'] ?? array() );
		$location = (string) ( $headers['location'] ?? '' );

		if ( ! $location ) {
			$this->cleanup( $video_file );
			return $this->fail( __( 'YouTube did not return an upload session URL.', 'vm-social-ai-pro' ) );
		}

		$uploaded = VMSAI_Http::request(
			'PUT',
			$location,
			array(
				'headers' => array( 'Content-Type' => 'video/mp4' ),
				'body'    => file_get_contents( $video_file ), // phpcs:ignore WordPress.WP.AlternativeFunctions
				'scope'   => 'channel.youtube',
				'timeout' => 600,
				'retries' => 1,
			)
		);

		$this->cleanup( $video_file );

		if ( ! $uploaded['ok'] ) {
			return $this->fail( $uploaded['error'] );
		}

		$id = (string) ( $uploaded['json']['id'] ?? '' );

		if ( ! $id ) {
			return $this->fail( __( 'YouTube returned no video id.', 'vm-social-ai-pro' ) );
		}

		return $this->ok( $id, 'https://www.youtube.com/shorts/' . $id );
	}

	/**
	 * Convert hashtags into YouTube tags.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	private function tags( array $post ) {
		$tags = array_filter( array_map(
			fn( $t ) => ltrim( trim( $t ), '#' ),
			explode( ' ', (string) $post['hashtags'] )
		) );

		return array_slice( array_values( $tags ), 0, 15 );
	}

	/**
	 * Delete a temporary render.
	 *
	 * @param string $path File path.
	 * @return void
	 */
	private function cleanup( $path ) {
		if ( $path && file_exists( $path ) ) {
			@unlink( $path ); // phpcs:ignore
		}
	}

	/**
	 * Read video statistics.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function fetch_metrics( array $post ) {
		$empty = array( 'impressions' => 0, 'reach' => 0, 'engagements' => 0, 'clicks' => 0 );
		$token = VMSAI_Channel_Gbp::access_token();

		if ( ! $token || empty( $post['remote_id'] ) ) {
			return $empty;
		}

		$response = VMSAI_Http::get(
			self::API . '/videos?' . http_build_query( array( 'part' => 'statistics', 'id' => $post['remote_id'] ) ),
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'scope'   => 'channel.youtube',
				'retries' => 0,
				'timeout' => 25,
			)
		);

		if ( ! $response['ok'] ) {
			return $empty;
		}

		$items = (array) ( $response['json']['items'] ?? array() );
		$stats = is_array( $items[0] ?? null ) ? ( $items[0]['statistics'] ?? array() ) : array();

		$empty['impressions'] = (int) ( $stats['viewCount'] ?? 0 );
		$empty['reach']       = $empty['impressions'];
		$empty['engagements'] = (int) ( $stats['likeCount'] ?? 0 ) + (int) ( $stats['commentCount'] ?? 0 );

		return $empty;
	}
}
