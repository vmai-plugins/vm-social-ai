<?php
/**
 * Google Business Profile channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Publishes local posts to a Google Business Profile location. This is the
 * highest-intent surface of the six: the audience is already searching for
 * the service in the area.
 */
class VMSAI_Channel_Gbp extends VMSAI_Channel {

	const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const API       = 'https://mybusiness.googleapis.com/v4';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'gbp';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Google Business Profile';
	}

	/**
	 * Required credentials.
	 *
	 * @return array
	 */
	public function credential_fields() {
		return array(
			'google_client_id'     => __( 'OAuth client ID', 'vm-social-ai-pro' ),
			'google_client_secret' => __( 'OAuth client secret', 'vm-social-ai-pro' ),
			'google_refresh_token' => __( 'Refresh token', 'vm-social-ai-pro' ),
			'gbp_location'         => __( 'Location path, e.g. accounts/123/locations/456', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Exchange the refresh token for a short-lived access token.
	 *
	 * @return string
	 */
	public static function access_token() {
		$cached = get_transient( 'vmsai_google_access_token' );

		if ( $cached ) {
			return (string) $cached;
		}

		$response = VMSAI_Http::post(
			self::TOKEN_URL,
			array(
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query(
					array(
						'client_id'     => VMSAI_Settings::credential( 'google_client_id' ),
						'client_secret' => VMSAI_Settings::credential( 'google_client_secret' ),
						'refresh_token' => VMSAI_Settings::credential( 'google_refresh_token' ),
						'grant_type'    => 'refresh_token',
					)
				),
				'scope'   => 'channel.google',
				'timeout' => 30,
			)
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::error( 'channel.google', 'Token refresh failed.', array( 'error' => $response['error'] ) );
			return '';
		}

		$token   = (string) ( $response['json']['access_token'] ?? '' );
		$expires = (int) ( $response['json']['expires_in'] ?? 3600 );

		if ( $token ) {
			set_transient( 'vmsai_google_access_token', $token, max( 300, $expires - 120 ) );
		}

		return $token;
	}

	/**
	 * Test the Google token by fetching the location name.
	 *
	 * @return array
	 */
	public function test_connection() {
		$token    = self::access_token();
		$location = trim( (string) VMSAI_Settings::credential( 'gbp_location' ), '/' );

		if ( ! $token || ! $location ) {
			return $this->fail( __( 'Missing Google credentials or location.', 'vm-social-ai-pro' ) );
		}

		$response = VMSAI_Http::get(
			self::API . '/' . $location,
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ), 'scope' => 'channel.gbp', 'retries' => 0 )
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$json = (array) ( $response['json'] ?? array() );
		return array( 'ok' => true, 'message' => sprintf( /* translators: %s: business location name */ __( 'Connected to "%s".', 'vm-social-ai-pro' ), $json['locationName'] ?? 'Business' ) );
	}

	/**
	 * Publish a local post.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function publish( array $post ) {
		$token = self::access_token();

		if ( ! $token ) {
			return $this->fail( __( 'Could not obtain a Google access token.', 'vm-social-ai-pro' ) );
		}

		$location = trim( (string) VMSAI_Settings::credential( 'gbp_location' ), '/' );
		$summary  = $this->caption( $post, false, false );

		$payload = array(
			'languageCode' => VMSAI_Settings::get( 'language', 'en' ),
			'summary'      => mb_substr( $summary, 0, 1500 ),
			'topicType'    => 'STANDARD',
		);

		if ( ! empty( $post['link'] ) ) {
			$payload['callToAction'] = array(
				'actionType' => 'LEARN_MORE',
				'url'        => $post['link'],
			);
		}

		$image = $this->media_url( $post );
		if ( $image ) {
			$payload['media'] = array(
				array( 'mediaFormat' => 'PHOTO', 'sourceUrl' => $image ),
			);
		}

		$response = VMSAI_Http::post(
			self::API . '/' . $location . '/localPosts',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'json'    => $payload,
				'scope'   => 'channel.gbp',
			)
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$name = (string) ( $response['json']['name'] ?? '' );

		if ( ! $name ) {
			return $this->fail( __( 'Google returned no local post name.', 'vm-social-ai-pro' ) );
		}

		return $this->ok( $name, (string) ( $response['json']['searchUrl'] ?? '' ) );
	}

	/**
	 * Read local post insights.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function fetch_metrics( array $post ) {
		$empty = array( 'impressions' => 0, 'reach' => 0, 'engagements' => 0, 'clicks' => 0 );
		$token = self::access_token();

		if ( ! $token || empty( $post['remote_id'] ) ) {
			return $empty;
		}

		$location = trim( (string) VMSAI_Settings::credential( 'gbp_location' ), '/' );

		$response = VMSAI_Http::post(
			self::API . '/' . $location . '/localPosts:reportInsights',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'json'    => array(
					'localPostNames' => array( $post['remote_id'] ),
					'basicRequest'   => array(
						'metricRequests' => array(
							array( 'metric' => 'LOCAL_POST_VIEWS_SEARCH' ),
							array( 'metric' => 'LOCAL_POST_ACTIONS_CALL_TO_ACTION' ),
						),
					),
				),
				'scope'   => 'channel.gbp',
				'retries' => 0,
				'timeout' => 25,
			)
		);

		if ( ! $response['ok'] ) {
			return $empty;
		}

		$metrics_list = (array) ( $response['json']['localPostMetrics'] ?? array() );
		$first_metric = is_array( $metrics_list[0] ?? null ) ? $metrics_list[0] : array();
		$values       = (array) ( $first_metric['metricValues'] ?? array() );

		foreach ( $values as $metric ) {
			$value = (int) ( $metric['totalValue']['value'] ?? 0 );

			if ( 'LOCAL_POST_VIEWS_SEARCH' === ( $metric['metric'] ?? '' ) ) {
				$empty['impressions'] = $value;
				$empty['reach']       = $value;
			} else {
				$empty['clicks'] += $value;
			}
		}

		return $empty;
	}

	/**
	 * Pull latest reviews from Google Business Profile.
	 *
	 * @param int $limit Max reviews.
	 * @return array
	 */
	public function fetch_comments( $limit = 5 ) {
		$token = self::access_token();
		if ( ! $token ) {
			return array();
		}

		$location = trim( (string) VMSAI_Settings::credential( 'gbp_location' ), '/' );

		// Reviews endpoint: accounts/{acc}/locations/{loc}/reviews
		$url = self::API . '/' . $location . '/reviews';

		$response = VMSAI_Http::get( $url, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			'scope'   => 'channel.gbp',
			'retries' => 0,
			'timeout' => 25,
		) );

		if ( ! $response['ok'] ) {
			return array();
		}

		$out = array();
		$reviews = (array) ( $response['json']['reviews'] ?? array() );

		foreach ( $reviews as $review ) {
			if ( ! is_array( $review ) ) {
				continue;
			}

			$out[] = array(
				'id'      => $review['reviewId'] ?? '',
				'author'  => $review['reviewer']['displayName'] ?? 'Google User',
				'text'    => $review['comment'] ?? '',
				'time'    => $review['createTime'] ?? '',
				'channel' => 'gbp',
				'rating'  => $review['starRating'] ?? 0,
			);
		}

		return array_slice( $out, 0, $limit );
	}
}
