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

		// Strategic Boost: large binary downloads and media ingestion are
		// slow; JSON generation payloads benefit from a generous ceiling too.
		// Small pingbacks/status polls should not pay for that.
		$is_large = ( ! empty( $args['json'] ) && ( ! empty( $args['json']['model'] ) || ! empty( $args['json']['model_id'] ) || ! empty( $args['json']['images'] ) || ! empty( $args['json']['video_url'] ) ) )
			|| ( isset( $args['stream'] ) && true === $args['stream'] )
			|| ( false !== stripos( rawurldecode( $url ), '/upload/' ) )
			|| ( false !== stripos( rawurldecode( $url ), '/video/' ) )
			|| ( false !== stripos( rawurldecode( $url ), '/generate' ) )
			|| ( false !== stripos( rawurldecode( $url ), '/create' ) );
		if ( $is_large ) {
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

		// PROXY SUPPORT: Route social API calls via proxy if configured.
		// Loopback/private targets bypass the proxy — they would fail
		// through an egress proxy, matching the SSL decision above.
		$proxy = VMSAI_Settings::credential( 'outbound_proxy' );
		if ( $proxy && ! self::is_local_host( wp_parse_url( $url, PHP_URL_HOST ) ) ) {
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
	 * Upload a local file as the raw request body WITHOUT loading it into
	 * memory. Streams via cURL when available (multi-hundred-MB videos);
	 * falls back to an in-memory upload for small files only.
	 *
	 * @param string $method  HTTP verb (PUT or POST).
	 * @param string $url     Endpoint.
	 * @param string $path    Local file path.
	 * @param array  $headers Headers (Content-Type, Authorization, ...).
	 * @param string $scope   Log scope.
	 * @param int    $timeout Seconds.
	 * @return array Same shape as request().
	 */
	public static function send_file( $method, $url, $path, array $headers = array(), $scope = 'http', $timeout = 300 ) {
		$fail = function ( $error, $status = 0, $body = '' ) {
			return array(
				'ok'     => false,
				'status' => $status,
				'body'   => $body,
				'json'   => (array) json_decode( (string) $body, true ),
				'error'  => $error,
			);
		};

		if ( ! is_string( $path ) || ! $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			VMSAI_Logger::error( $scope, 'File upload skipped: file missing or unreadable.', array( 'path' => (string) $path ) );
			return $fail( 'File missing or unreadable.' );
		}

		$size = (int) filesize( $path );
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( function_exists( 'curl_init' ) ) {
			$fh = fopen( $path, 'rb' );
			if ( ! $fh ) {
				return $fail( 'Could not open file for upload.' );
			}

			$header_lines = array();
			foreach ( $headers as $name => $value ) {
				$header_lines[] = $name . ': ' . $value;
			}

			$local = self::is_local_host( $host );
			$ch    = curl_init( $url );
			curl_setopt_array( $ch, array(
				CURLOPT_CUSTOMREQUEST  => strtoupper( $method ),
				CURLOPT_UPLOAD         => true,
				CURLOPT_INFILE         => $fh,
				CURLOPT_INFILESIZE     => $size,
				CURLOPT_HTTPHEADER     => $header_lines,
				CURLOPT_TIMEOUT        => $timeout,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 3,
				CURLOPT_USERAGENT      => 'VM-Social-AI/' . VMSAI_VERSION . '; ' . home_url( '/' ),
				CURLOPT_SSL_VERIFYPEER => ! $local,
				CURLOPT_SSL_VERIFYHOST => $local ? 0 : 2,
			) );

			// Same proxy policy as request(): loopback/private targets bypass.
			$proxy = VMSAI_Settings::credential( 'outbound_proxy' );
			if ( $proxy && ! $local ) {
				curl_setopt( $ch, CURLOPT_PROXY, $proxy );
			}

			$raw    = curl_exec( $ch );
			$status = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$err    = curl_error( $ch );
			curl_close( $ch );
			fclose( $fh );

			if ( 0 === $status && '' !== $err ) {
				VMSAI_Logger::warn( $scope, 'File upload transport failed: ' . $err, array( 'url' => self::safe_url( $url ) ) );
				return $fail( $err );
			}

			if ( $status < 200 || $status >= 300 ) {
				$last = 'HTTP ' . $status . ' ' . self::extract_error( (string) $raw );
				VMSAI_Logger::warn( $scope, 'File upload failed: ' . $last, array( 'url' => self::safe_url( $url ) ) );
				return $fail( $last, $status, (string) $raw );
			}

			return array(
				'ok'      => true,
				'status'  => $status,
				'body'    => (string) $raw,
				'json'    => (array) json_decode( (string) $raw, true ),
				'headers' => array(),
				'error'   => '',
			);
		}

		// No cURL available: only small files can go through memory.
		if ( $size > 24 * 1024 * 1024 ) {
			return $fail( 'File too large to upload without cURL streaming support.' );
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $content ) {
			return $fail( 'Could not read local file.' );
		}

		return self::request(
			strtoupper( $method ),
			$url,
			array(
				'headers' => $headers,
				'body'    => $content,
				'scope'   => $scope,
				'timeout' => $timeout,
				'retries' => 1,
			)
		);
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

	/**
	 * Whether a hostname resolves to a local/loopback address.
	 *
	 * Note: this answers "is the literal host private/loopback", not "does
	 * it resolve to a private IP" — a public hostname that resolves
	 * privately (DNS rebinding) still returns false.
	 *
	 * @param string $host Hostname (no scheme or port).
	 * @return bool
	 */
	public static function is_local_host( $host ) {
		if ( ! $host || ! is_string( $host ) ) {
			return false;
		}
		$host = strtolower( trim( (string) $host ) );
		// Strip brackets from IPv6 literals and trailing dot from FQDNs.
		if ( strlen( $host ) > 2 && '[' === $host[0] && ']' === substr( $host, -1 ) ) {
			$host = substr( $host, 1, -1 );
		}
		$host = rtrim( $host, '.' );
		if ( '' === $host ) {
			return false;
		}
		if ( in_array( $host, array( 'localhost', '::1', '::ffff:127.0.0.1' ), true ) ) {
			return true;
		}
		if ( false !== strpos( $host, '.local' ) || false !== strpos( $host, '.localhost' ) || false !== strpos( $host, '.test' ) || false !== strpos( $host, '.example' ) || false !== strpos( $host, '.invalid' ) ) {
			return true;
		}
		// IPv4 private/loopback/link-local ranges.
		if ( (bool) preg_match( '/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|169\.254\.)/', $host ) ) {
			return true;
		}
		// IPv6 unique-local / link-local / loopback.
		if ( false !== strpos( $host, ':' ) ) {
			$packed = @inet_pton( $host ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			if ( false !== $packed && strlen( $packed ) === 16 ) {
				$first = ord( $packed[0] );
				// fc00::/7 unique-local, fe80::/10 link-local, ::1 loopback.
				if ( 0xfc === ( $first & 0xfe ) || ( 0xfe === $first && 0x80 === ( ord( $packed[1] ) & 0xc0 ) ) || $packed === str_repeat( "\x00", 15 ) . "\x01" ) {
					return true;
				}
			}
		}
		return false;
	}
}
