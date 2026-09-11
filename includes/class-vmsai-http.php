<?php
/**
 * Outbound HTTP helper.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper over wp_remote_request with retries and JSON convenience.
 */
class VMSAI_Http {

	/**
	 * Perform a request.
	 *
	 * @param string $method  HTTP verb.
	 * @param string $url     Endpoint.
	 * @param array  $args    Keys: headers, body, json, timeout, retries, scope.
	 * @return array{ok:bool,status:int,body:string,json:array,error:string}
	 */
	public static function request( $method, $url, array $args = array() ) {
		$timeout = isset( $args['timeout'] ) ? (int) $args['timeout'] : (int) VMSAI_Settings::get( 'request_timeout', 90 );

		// Strategic Boost: Images and JSON generation are slow.
		if ( strpos( $url, '/images/' ) !== false || ! empty( $args['json'] ) ) {
			$timeout = max( $timeout, 120 );
		}

		$retries = isset( $args['retries'] ) ? max( 0, (int) $args['retries'] ) : 1;
		$scope   = $args['scope'] ?? 'http';
		$headers = (array) ( $args['headers'] ?? array() );
		$body    = $args['body'] ?? null;

		if ( isset( $args['json'] ) ) {
			$body                    = wp_json_encode( $args['json'] );
			$headers['Content-Type'] = 'application/json';
		}

		// Note: SSL verification is intentionally NOT relaxed based on the
		// site's WP_ENVIRONMENT_TYPE. Real third-party API keys (Gemini,
		// OpenRouter, NVIDIA, etc.) go out over these requests regardless of
		// whether this site is labelled local/staging/production — only
		// genuinely local/loopback targets should ever skip certificate
		// verification.
		//
		// This used to be relaxed via a global `http_request_args` filter that
		// was only removed on the failure path, so every successful call left a
		// closure attached that went on rewriting `sslverify` for every other
		// plugin's requests for the rest of the page load. The decision is made
		// here instead, on this request only.
		$ssl_verify = ! self::is_local_host( wp_parse_url( $url, PHP_URL_HOST ) );

		$request = array(
			'method'      => strtoupper( $method ),
			'timeout'     => $timeout,
			'headers'     => $headers,
			'redirection' => 5,
			'sslverify'   => $ssl_verify,
			'user-agent'  => 'VM-Social-AI/' . VMSAI_VERSION . '; ' . home_url( '/' ),
		);

		// PROXY SUPPORT: Route social API calls via proxy if configured
		$proxy = VMSAI_Settings::credential( 'outbound_proxy' );
		if ( $proxy && strpos( $url, 'localhost' ) === false ) {
			$request['proxy_host'] = wp_parse_url( $proxy, PHP_URL_HOST );
			$request['proxy_port'] = wp_parse_url( $proxy, PHP_URL_PORT );
			$user = wp_parse_url( $proxy, PHP_URL_USER );
			$pass = wp_parse_url( $proxy, PHP_URL_PASS );
			if ( $user && $pass ) {
				$request['headers']['Proxy-Authorization'] = 'Basic ' . base64_encode( "$user:$pass" );
			}
		}

		if ( null !== $body ) {
			$request['body'] = $body;
		}

		$attempt     = 0;
		$last        = '';
		$retry_after = 0;

		do {
			$response = wp_remote_request( $url, $request );

			if ( is_wp_error( $response ) ) {
				$last = $response->get_error_message();
			} else {
				$status = (int) wp_remote_retrieve_response_code( $response );
				$raw    = (string) wp_remote_retrieve_body( $response );

				if ( $status >= 200 && $status < 300 ) {
					$decoded = json_decode( $raw, true );
					return array(
						'ok'      => true,
						'status'  => $status,
						'body'    => $raw,
						'json'    => is_array( $decoded ) ? $decoded : array(),
						'headers' => wp_remote_retrieve_headers( $response ),
						'error'   => '',
					);
				}

				$last = 'HTTP ' . $status . ' ' . self::extract_error( $raw );

				// 4xx other than rate limiting will not improve on retry.
				if ( $status >= 400 && $status < 500 && 429 !== $status && 408 !== $status ) {
					$decoded = json_decode( $raw, true );
					return array(
						'ok'     => false,
						'status' => $status,
						'body'   => $raw,
						'json'   => is_array( $decoded ) ? $decoded : array(),
						'error'  => $last,
					);
				}

				// A real rate limit tells us how long to wait — a fixed
				// 0.5s backoff almost never clears it, which meant 429s
				// were failing the retry and tripping the circuit breaker
				// exactly like a dead API key would. Honor it when given.
				if ( 429 === $status ) {
					$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
				}
			}

			$attempt++;
			if ( $attempt <= $retries ) {
				if ( $retry_after > 0 ) {
					usleep( min( 10000000, $retry_after * 1000000 ) );
					$retry_after = 0;
				} else {
					// Pro Optimization: Shorter, non-blocking-feeling sleep.
					usleep( min( 2000000, 500000 * ( 2 ** ( $attempt - 1 ) ) ) );
				}
			}
		} while ( $attempt <= $retries );

		VMSAI_Logger::warn( $scope, 'Request failed: ' . $last, array( 'url' => self::safe_url( $url ) ) );

		return array(
			'ok'     => false,
			'status' => 0,
			'body'   => '',
			'json'   => array(),
			'error'  => $last,
		);
	}

	/**
	 * Convenience GET.
	 *
	 * @param string $url  Endpoint.
	 * @param array  $args Options.
	 * @return array
	 */
	public static function get( $url, array $args = array() ) {
		return self::request( 'GET', $url, $args );
	}

	/**
	 * Convenience POST.
	 *
	 * @param string $url  Endpoint.
	 * @param array  $args Options.
	 * @return array
	 */
	public static function post( $url, array $args = array() ) {
		return self::request( 'POST', $url, $args );
	}

	/**
	 * Download binary content into a string.
	 *
	 * @param string $url     Remote file.
	 * @param int    $timeout Seconds.
	 * @return string|false
	 */
	public static function fetch_binary( $url, $timeout = 60 ) {
		$response = self::get( $url, array( 'timeout' => $timeout ) );

		if ( ! $response['ok'] ) {
			return false;
		}

		return $response['body'];
	}

	/**
	 * Pull a readable message out of an error payload.
	 *
	 * @param string $raw Response body.
	 * @return string
	 */
	private static function extract_error( $raw ) {
		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			foreach ( array( 'error', 'message', 'detail', 'error_message' ) as $key ) {
				if ( isset( $decoded[ $key ] ) ) {
					$candidate = $decoded[ $key ];
					if ( is_array( $candidate ) ) {
						$candidate = $candidate['message'] ?? wp_json_encode( $candidate );
					}
					return substr( (string) $candidate, 0, 300 );
				}
			}
		}

		return substr( wp_strip_all_tags( $raw ), 0, 200 );
	}

	/**
	 * Strip query strings before logging a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function safe_url( $url ) {
		$parts = wp_parse_url( $url );
		return ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . ( $parts['path'] ?? '' );
	}
}
