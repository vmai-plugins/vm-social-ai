<?php
/**
 * X (Twitter) channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Posts to X using OAuth 1.0a user context, which is what the v1.1 media
 * upload endpoint still requires alongside the v2 tweet endpoint.
 */
class VMSAI_Channel_X extends VMSAI_Channel {

	const TWEET_URL = 'https://api.twitter.com/2/tweets';
	const MEDIA_URL = 'https://upload.twitter.com/1.1/media/upload.json';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'x';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'X (Twitter)';
	}

	/**
	 * Required credentials.
	 *
	 * @return array
	 */
	public function credential_fields() {
		return array(
			'x_api_key'        => __( 'API key', 'vm-social-ai-pro' ),
			'x_api_secret'     => __( 'API key secret', 'vm-social-ai-pro' ),
			'x_access_token'   => __( 'Access token', 'vm-social-ai-pro' ),
			'x_access_secret'  => __( 'Access token secret', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Test the X credentials by fetching the authenticating user.
	 *
	 * @return array
	 */
	public function test_connection() {
		$url = 'https://api.twitter.com/2/users/me';
		$response = VMSAI_Http::get(
			$url,
			array(
				'headers' => array( 'Authorization' => $this->oauth_header( 'GET', $url, array() ) ),
				'scope'   => 'channel.x',
				'retries' => 0,
			)
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$data = $response['json']['data'] ?? array();
		$username = is_array( $data ) ? ( $data['username'] ?? 'user' ) : 'user';
		return array( 'ok' => true, 'message' => sprintf( /* translators: %s: username */ __( 'Connected as @%s.', 'vm-social-ai-pro' ), $username ) );
	}

	/**
	 * Publish a post.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function publish( array $post ) {
		$text = $this->caption( $post, true, true );
		$text = mb_substr( $text, 0, 280 );

		$payload = array( 'text' => $text );

		$media_id = $this->upload_media( $post );
		if ( $media_id ) {
			$payload['media'] = array( 'media_ids' => array( $media_id ) );
		}

		$response = VMSAI_Http::post(
			self::TWEET_URL,
			array(
				'headers' => array( 'Authorization' => $this->oauth_header( 'POST', self::TWEET_URL, array() ) ),
				'json'    => $payload,
				'scope'   => 'channel.x',
			)
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$json = (array) ( $response['json'] ?? array() );
		$data = (array) ( $json['data'] ?? array() );
		$id   = (string) ( $data['id'] ?? '' );

		if ( ! $id ) {
			return $this->fail( __( 'X returned no tweet id.', 'vm-social-ai-pro' ) );
		}

		return $this->ok( $id, 'https://x.com/i/web/status/' . $id );
	}

	/**
	 * Upload the image and return its media id.
	 *
	 * @param array $post Queue row.
	 * @return string
	 */
	private function upload_media( array $post ) {
		$path = $this->media_path( $post );

		if ( ! $path || ! file_exists( $path ) ) {
			return '';
		}

		$binary = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $binary ) {
			return '';
		}

		// media_data is a signed OAuth parameter for this endpoint.
		$params = array( 'media_data' => base64_encode( $binary ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		$response = VMSAI_Http::post(
			self::MEDIA_URL,
			array(
				'headers' => array(
					'Authorization' => $this->oauth_header( 'POST', self::MEDIA_URL, $params ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => http_build_query( $params ),
				'scope'   => 'channel.x',
				'timeout' => 120,
			)
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::warn( 'channel.x', 'Media upload failed; posting text only.', array( 'error' => $response['error'] ) );
			return '';
		}

		return (string) ( $response['json']['media_id_string'] ?? '' );
	}

	/**
	 * Build the OAuth 1.0a Authorization header.
	 *
	 * @param string $method HTTP method.
	 * @param string $url    Endpoint.
	 * @param array  $params Signed body/query parameters.
	 * @return string
	 */
	private function oauth_header( $method, $url, array $params ) {
		$consumer_key    = VMSAI_Settings::credential( 'x_api_key' );
		$consumer_secret = VMSAI_Settings::credential( 'x_api_secret' );
		$token           = VMSAI_Settings::credential( 'x_access_token' );
		$token_secret    = VMSAI_Settings::credential( 'x_access_secret' );

		$oauth = array(
			'oauth_consumer_key'     => $consumer_key,
			'oauth_nonce'            => wp_generate_password( 32, false ),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => (string) time(),
			'oauth_token'            => $token,
			'oauth_version'          => '1.0',
		);

		$signing = array_merge( $oauth, $params );
		ksort( $signing );

		$pairs = array();
		foreach ( $signing as $key => $value ) {
			$pairs[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
		}

		$base = strtoupper( $method ) . '&' . rawurlencode( $url ) . '&' . rawurlencode( implode( '&', $pairs ) );
		$key  = rawurlencode( $consumer_secret ) . '&' . rawurlencode( $token_secret );

		$oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base, $key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		ksort( $oauth );

		$header = array();
		foreach ( $oauth as $k => $v ) {
			$header[] = rawurlencode( $k ) . '="' . rawurlencode( (string) $v ) . '"';
		}

		return 'OAuth ' . implode( ', ', $header );
	}

	/**
	 * Read tweet metrics.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function fetch_metrics( array $post ) {
		$empty = array( 'impressions' => 0, 'reach' => 0, 'engagements' => 0, 'clicks' => 0 );

		if ( empty( $post['remote_id'] ) ) {
			return $empty;
		}

		$url = 'https://api.twitter.com/2/tweets/' . rawurlencode( $post['remote_id'] );
		$qs  = array( 'tweet.fields' => 'public_metrics,non_public_metrics' );

		$response = VMSAI_Http::get(
			$url . '?' . http_build_query( $qs ),
			array(
				'headers' => array( 'Authorization' => $this->oauth_header( 'GET', $url, $qs ) ),
				'scope'   => 'channel.x',
				'retries' => 0,
				'timeout' => 25,
			)
		);

		if ( ! $response['ok'] ) {
			return $empty;
		}

		$json       = (array) ( $response['json'] ?? array() );
		$data       = (array) ( $json['data'] ?? array() );
		$public     = is_array( $data['public_metrics'] ?? null ) ? $data['public_metrics'] : array();
		$non_public = is_array( $data['non_public_metrics'] ?? null ) ? $data['non_public_metrics'] : array();

		$empty['impressions'] = (int) ( $non_public['impression_count'] ?? 0 );
		$empty['reach']       = $empty['impressions'];
		$empty['clicks']      = (int) ( $non_public['url_link_clicks'] ?? 0 );
		$empty['engagements'] = (int) ( $public['like_count'] ?? 0 ) + (int) ( $public['retweet_count'] ?? 0 ) + (int) ( $public['reply_count'] ?? 0 );

		return $empty;
	}
}
