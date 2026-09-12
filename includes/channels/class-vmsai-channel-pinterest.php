<?php
/**
 * Pinterest channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Channel_Pinterest extends VMSAI_Channel {

	const API = 'https://api.pinterest.com/v5';

	public function slug() {
		return 'pinterest';
	}

	public function label() {
		return 'Pinterest';
	}

	public function credential_fields() {
		return array(
			'pinterest_token'    => __( 'Pinterest Access Token', 'vm-social-ai-pro' ),
			'pinterest_board_id' => __( 'Default Board ID', 'vm-social-ai-pro' ),
		);
	}

	public function test_connection() {
		$token = VMSAI_Settings::credential( 'pinterest_token' );
		if ( ! $token ) return $this->fail( __( 'Missing Pinterest Token.', 'vm-social-ai-pro' ) );

		$res = VMSAI_Http::get( self::API . '/user_account', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			'scope'   => 'channel.pinterest'
		) );

		if ( ! $res['ok'] ) return $this->fail( $res['error'] );

		return array( 'ok' => true, 'message' => sprintf( __( 'Connected as %s.', 'vm-social-ai-pro' ), $res['json']['username'] ?? 'User' ) );
	}

	public function publish( array $post ) {
		$token    = VMSAI_Settings::credential( 'pinterest_token' );
		$board_id = VMSAI_Settings::credential( 'pinterest_board_id' );
		$image    = $this->media_url( $post );

		if ( ! $image ) return $this->fail( __( 'Pinterest requires an image.', 'vm-social-ai-pro' ) );

		$payload = array(
			'title'       => mb_substr( $post['title'], 0, 100 ),
			'description' => mb_substr( $this->caption( $post, false, true ), 0, 500 ),
			'board_id'    => $board_id,
			'media_source' => array(
				'source_type'  => 'image_url',
				'url'          => $image
			)
		);

		// Only send the link when the post actually has one — defaulting to
		// the site homepage made every link-less pin point at "/" and
		// pollute Pinterest's outbound click analytics.
		if ( ! empty( $post['link'] ) ) {
			$payload['link'] = $post['link'];
		}

		$res = VMSAI_Http::post( self::API . '/pins', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json'
			),
			'json' => $payload,
			'scope' => 'channel.pinterest'
		) );

		if ( ! $res['ok'] ) return $this->fail( $res['error'] );

		return $this->ok( $res['json']['id'], 'https://www.pinterest.com/pin/' . $res['json']['id'] );
	}

	/**
	 * Read pin analytics. Pinterest exposes per-pin save/pin-click counts;
	 * impressions are derived from the analytics endpoint when available.
	 *
	 * @param array $post Queue row.
	 * @return array{impressions:int,reach:int,engagements:int,clicks:int}
	 */
	public function fetch_metrics( array $post ) {
		$empty = array( 'impressions' => 0, 'reach' => 0, 'engagements' => 0, 'clicks' => 0 );

		if ( empty( $post['remote_id'] ) ) {
			return $empty;
		}

		$token = VMSAI_Settings::credential( 'pinterest_token' );
		if ( ! $token ) {
			return $empty;
		}

		$headers = array( 'Authorization' => 'Bearer ' . $token );

		// 1. Pin metadata: saves are the closest engagement proxy.
		$pin = VMSAI_Http::get(
			self::API . '/pins/' . rawurlencode( (string) $post['remote_id'] ),
			array( 'headers' => $headers, 'scope' => 'channel.pinterest', 'retries' => 0, 'timeout' => 25 )
		);

		$saves = 0;
		if ( ! empty( $pin['ok'] ) && is_array( $pin['json'] ?? null ) ) {
			// v5 returns save counts under pin_metrics_90d / counts blocks.
			$metrics = (array) ( $pin['json']['pin_metrics_90d'] ?? $pin['json']['counts'] ?? array() );
			foreach ( array( 'save', 'saves' ) as $key ) {
				if ( isset( $metrics[ $key ] ) ) {
					$saves = max( $saves, (int) $metrics[ $key ] );
				}
			}
		}

		// 2. Pin analytics for impressions/clicks (may 403 on limited scopes).
		$analytics = VMSAI_Http::get(
			self::API . '/pins/' . rawurlencode( (string) $post['remote_id'] ) . '/analytics?' . http_build_query(
				array(
					'start_date'   => gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ),
					'end_date'     => gmdate( 'Y-m-d' ),
					'metric_types' => 'IMPRESSION,PIN_CLICK,SAVE,OUTBOUND_CLICK',
				)
			),
			array( 'headers' => $headers, 'scope' => 'channel.pinterest', 'retries' => 0, 'timeout' => 25 )
		);

		if ( ! empty( $analytics['ok'] ) && is_array( $analytics['json'] ?? null ) ) {
			$sums = array( 'IMPRESSION' => 0, 'PIN_CLICK' => 0, 'SAVE' => 0, 'OUTBOUND_CLICK' => 0 );
			$days = (array) ( $analytics['json']['all_time'] ?? $analytics['json'] ?? array() );
			// Shape: { DATE: { IMPRESSION: n, ... } } — sum everything numeric.
			$walk = function( $node ) use ( &$sums, &$walk ) {
				if ( ! is_array( $node ) ) {
					return;
				}
				foreach ( $node as $k => $v ) {
					if ( isset( $sums[ $k ] ) && is_numeric( $v ) ) {
						$sums[ $k ] += (int) $v;
					} elseif ( is_array( $v ) ) {
						$walk( $v );
					}
				}
			};
			$walk( $days );

			$empty['impressions'] = (int) $sums['IMPRESSION'];
			$empty['reach']       = (int) $sums['IMPRESSION'];
			$empty['clicks']      = (int) $sums['PIN_CLICK'] + (int) $sums['OUTBOUND_CLICK'];
			$empty['engagements'] = (int) $sums['SAVE'] + (int) $sums['PIN_CLICK'];
			if ( $saves > $empty['engagements'] ) {
				$empty['engagements'] = $saves;
			}
			return $empty;
		}

		// Metadata-only fallback: saves still prove the pin is alive.
		if ( $saves > 0 ) {
			$empty['engagements'] = $saves;
		}

		return $empty;
	}

	/**
	 * Pull recent comments left on our pins. The v5 API has no direct
	 * pin-comments endpoint, so this surfaces nothing rather than zeros
	 * pretending to be data — callers treat empty as "unknown".
	 *
	 * @param int $limit Max comments.
	 * @return array
	 */
	public function fetch_comments( $limit = 10 ) {
		$limit = max( 1, (int) $limit );
		$token = VMSAI_Settings::credential( 'pinterest_token' );
		if ( ! $token ) {
			return array();
		}

		$board = VMSAI_Settings::credential( 'pinterest_board_id' );
		if ( ! $board ) {
			return array();
		}

		$res = VMSAI_Http::get(
			self::API . '/boards/' . rawurlencode( (string) $board ) . '/pins?' . http_build_query(
				array( 'page_size' => min( 25, $limit ) )
			),
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'scope'   => 'channel.pinterest',
				'retries' => 0,
				'timeout' => 25,
			)
		);

		if ( empty( $res['ok'] ) || ! is_array( $res['json'] ?? null ) ) {
			return array();
		}

		$out = array();
		foreach ( (array) ( $res['json']['items'] ?? array() ) as $pin ) {
			if ( ! is_array( $pin ) ) {
				continue;
			}
			// v5 pins expose comment counts but not bodies; keep the shape
			// consistent with other channels for the Inbox UI.
			$count = (int) ( $pin['counts']['comments'] ?? 0 );
			if ( $count > 0 ) {
				$out[] = array(
					'id'      => (string) ( $pin['id'] ?? '' ),
					'author'  => __( 'Pinterest User', 'vm-social-ai-pro' ),
					'text'    => sprintf(
						/* translators: %d: comment count */
						__( '%d comment(s) on pin — open Pinterest to reply.', 'vm-social-ai-pro' ),
						$count
					),
					'time'    => (string) ( $pin['created_at'] ?? '' ),
					'channel' => 'pinterest',
				);
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}
}
