<?php
/**
 * LinkedIn channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Publishes to a LinkedIn organisation page. Images must be registered and
 * uploaded before they can be referenced in a post.
 */
class VMSAI_Channel_Linkedin extends VMSAI_Channel {

	const API = 'https://api.linkedin.com/v2';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'linkedin';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'LinkedIn';
	}

	/**
	 * Required credentials.
	 *
	 * @return array
	 */
	public function credential_fields() {
		return array(
			'li_access_token' => __( 'Access token', 'vm-social-ai-pro' ),
			'li_org_urn'      => __( 'Organisation URN, e.g. urn:li:organization:12345', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Standard headers.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'Authorization'             => 'Bearer ' . VMSAI_Settings::credential( 'li_access_token' ),
			'X-Restli-Protocol-Version' => '2.0.0',
			'LinkedIn-Version'          => '202405',
		);
	}

	/**
	 * Test the LinkedIn token by fetching the profile.
	 *
	 * @return array
	 */
	public function test_connection() {
		$response = VMSAI_Http::get(
			self::API . '/me',
			array( 'headers' => $this->headers(), 'scope' => 'channel.linkedin', 'retries' => 0 )
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$json = (array) ( $response['json'] ?? array() );
		$name = sprintf( '%s %s', $json['localizedFirstName'] ?? '', $json['localizedLastName'] ?? '' );
		return array( 'ok' => true, 'message' => sprintf( /* translators: %s: user name */ __( 'Connected as %s.', 'vm-social-ai-pro' ), trim( $name ) ?: 'User' ) );
	}

	/**
	 * Publish a post.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function publish( array $post ) {
		$author = VMSAI_Settings::credential( 'li_org_urn' );
		$text   = $this->caption( $post, true, true );

		$media_asset = $this->upload_image( $post, $author );

		$payload = array(
			'author'          => $author,
			'lifecycleState'  => 'PUBLISHED',
			'specificContent' => array(
				'com.linkedin.ugc.ShareContent' => array(
					'shareCommentary'    => array( 'text' => mb_substr( $text, 0, 3000 ) ),
					'shareMediaCategory' => $media_asset ? 'IMAGE' : 'NONE',
				),
			),
			'visibility'      => array( 'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC' ),
		);

		if ( $media_asset ) {
			$payload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = array(
				array(
					'status'      => 'READY',
					'media'       => $media_asset,
					'description' => array( 'text' => mb_substr( (string) $post['alt_text'], 0, 200 ) ),
					'title'       => array( 'text' => mb_substr( (string) $post['title'], 0, 190 ) ),
				),
			);
		}

		$response = VMSAI_Http::post(
			self::API . '/ugcPosts',
			array( 'headers' => $this->headers(), 'json' => $payload, 'scope' => 'channel.linkedin' )
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$urn = (string) ( $response['json']['id'] ?? '' );

		if ( ! $urn ) {
			$headers = $response['headers'] ?? array();
			$urn     = is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ? (string) $headers['x-restli-id'] : '';
		}

		if ( ! $urn ) {
			return $this->fail( __( 'LinkedIn returned no post URN.', 'vm-social-ai-pro' ) );
		}

		return $this->ok( $urn, 'https://www.linkedin.com/feed/update/' . $urn );
	}

	/**
	 * Leave the first comment on a share. This is how a link gets onto a
	 * LinkedIn post without the body carrying it — LinkedIn demotes reach on
	 * posts with outbound links in the commentary itself.
	 *
	 * @param string $remote_id Share URN.
	 * @param string $text      Comment body.
	 * @return bool
	 */
	public function post_comment( $remote_id, $text ) {
		$text = trim( (string) $text );

		if ( '' === $text || '' === (string) $remote_id ) {
			return false;
		}

		$response = VMSAI_Http::post(
			self::API . '/socialActions/' . rawurlencode( $remote_id ) . '/comments',
			array(
				'headers' => $this->headers(),
				'json'    => array(
					'actor'   => VMSAI_Settings::credential( 'li_org_urn' ),
					'message' => array( 'text' => mb_substr( $text, 0, 1250 ) ),
				),
				'scope'   => 'channel.linkedin',
			)
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::warn( 'channel.linkedin', 'First comment failed: ' . $response['error'] );
			return false;
		}

		return true;
	}

	/**
	 * Register and upload the image, returning its asset URN.
	 *
	 * @param array  $post   Queue row.
	 * @param string $author Organisation URN.
	 * @return string
	 */
	private function upload_image( array $post, $author ) {
		$path = $this->media_path( $post );

		if ( ! $path ) {
			return '';
		}

		$registered = VMSAI_Http::post(
			self::API . '/assets?action=registerUpload',
			array(
				'headers' => $this->headers(),
				'json'    => array(
					'registerUploadRequest' => array(
						'recipes'               => array( 'urn:li:digitalmediaRecipe:feedshare-image' ),
						'owner'                 => $author,
						'serviceRelationships'  => array(
							array(
								'relationshipType' => 'OWNER',
								'identifier'       => 'urn:li:userGeneratedContent',
							),
						),
					),
				),
				'scope'   => 'channel.linkedin',
			)
		);

		if ( ! $registered['ok'] ) {
			VMSAI_Logger::warn( 'channel.linkedin', 'Image registration failed; posting text only.', array( 'error' => $registered['error'] ) );
			return '';
		}

		$json       = (array) ( $registered['json'] ?? array() );
		$value      = is_array( $json['value'] ?? null ) ? $json['value'] : array();
		$asset      = (string) ( $value['asset'] ?? '' );
		$mechanism  = is_array( $value['uploadMechanism'] ?? null ) ? $value['uploadMechanism'] : array();
		$mechanism2 = is_array( $mechanism['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest'] ?? null ) ? $mechanism['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest'] : array();
		$upload_url = (string) ( $mechanism2['uploadUrl'] ?? '' );

		if ( ! $asset || ! $upload_url ) {
			return '';
		}

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			VMSAI_Logger::error( 'channel.linkedin', 'Image file missing or unreadable during upload.', array( 'path' => $path ) );
			return '';
		}

		$binary = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$uploaded = VMSAI_Http::request(
			'PUT',
			$upload_url,
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . VMSAI_Settings::credential( 'li_access_token' ) ),
				'body'    => $binary,
				'scope'   => 'channel.linkedin',
				'timeout' => 120,
				'retries' => 1,
			)
		);

		return $uploaded['ok'] ? $asset : '';
	}

	/**
	 * Read organisation share statistics.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function fetch_metrics( array $post ) {
		$empty = array( 'impressions' => 0, 'reach' => 0, 'engagements' => 0, 'clicks' => 0 );

		if ( empty( $post['remote_id'] ) ) {
			return $empty;
		}

		$url = self::API . '/organizationalEntityShareStatistics?' . http_build_query(
			array(
				'q'                  => 'organizationalEntity',
				'organizationalEntity' => VMSAI_Settings::credential( 'li_org_urn' ),
				'shares[0]'          => $post['remote_id'],
			)
		);

		$response = VMSAI_Http::get( $url, array( 'headers' => $this->headers(), 'scope' => 'channel.linkedin', 'retries' => 0, 'timeout' => 25 ) );

		if ( ! $response['ok'] ) {
			return $empty;
		}

		$elements = (array) ( $response['json']['elements'] ?? array() );
		$first    = is_array( $elements[0] ?? null ) ? $elements[0] : array();
		$stats    = (array) ( $first['totalShareStatistics'] ?? array() );

		$empty['impressions'] = (int) ( $stats['impressionCount'] ?? 0 );
		$empty['reach']       = (int) ( $stats['uniqueImpressionsCount'] ?? $empty['impressions'] );
		$empty['clicks']      = (int) ( $stats['clickCount'] ?? 0 );
		$empty['engagements'] = (int) ( $stats['likeCount'] ?? 0 ) + (int) ( $stats['commentCount'] ?? 0 ) + (int) ( $stats['shareCount'] ?? 0 );

		return $empty;
	}
}
