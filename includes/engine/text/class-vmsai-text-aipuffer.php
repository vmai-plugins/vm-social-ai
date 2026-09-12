<?php
/**
 * AI Puffer (AIPKit / AI Power / Meow Apps) Provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Universal Bridge to AI Puffer ecosystems. Supports AIPKit, AI Power (legacy),
 * and Meow Apps namespaces with RAG context and local/remote routing.
 */
class VMSAI_Text_Aipuffer implements VMSAI_Text_Provider {

	public function slug() { return 'aipuffer'; }
	public function label() { return 'AI Puffer'; }

	public function is_configured() {
		$key = VMSAI_Settings::credential( 'aipuffer_key' );
		if ( ! empty($key) ) return true;

		// AUTO-CONFIG: Detect if AI Puffer is installed on this same site.
		$opts = get_option('aipkit_options');
		if ( is_array($opts) && ! empty($opts['api_keys']['public_api_key']) ) {
			return true;
		}
		$legacy = get_option('wpaicg_options');
		if ( is_array($legacy) && ! empty($legacy['rest_api_key']) ) {
			return true;
		}

		return false;
	}

	/**
	 * Probe the site to detect the active AI namespace and endpoint.
	 */
	private function detect_endpoint() {
		$site = VMSAI_Settings::credential( 'aipuffer_site' );
		$site = $site ? untrailingslashit( $site ) : untrailingslashit( home_url() );
		$key  = VMSAI_Settings::credential( 'aipuffer_key' );

		$cache_key = 'vmsai_aipuffer_endpoint_' . md5( $site );
		$cached = get_transient( $cache_key );
		if ( $cached ) return $cached;

		$namespaces = array( 'aipkit/v1', 'wpaicg/v1', 'mwai/v1' );
		foreach ( $namespaces as $ns ) {
			$url = $site . '/wp-json/' . $ns;

			// Check connectivity with a models call.
			$res = VMSAI_Http::get( $url . ( $ns === 'mwai/v1' ? '/bots' : '/models' ), array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'timeout' => 8,
				'retries' => 0
			) );

			if ( $res['ok'] ) {
				set_transient( $cache_key, $url, DAY_IN_SECONDS );
				return $url;
			}
		}

		return $site . '/wp-json/aipkit/v1'; // Default
	}

	/**
	 * Route request through internal REST or remote HTTP.
	 */
	private function request( $method, $url, array $payload = array() ) {
		$key = VMSAI_Settings::credential( 'aipuffer_key' );
		$is_local = ( strpos( $url, home_url() ) !== false );

		// AUTO-DETECT LOCAL KEY (If missing in settings)
		if ( $is_local && empty($key) ) {
			// Try AIPKit options
			$opts = get_option('aipkit_options');
			if ( is_array($opts) && ! empty($opts['api_keys']['public_api_key']) ) {
				$key = $opts['api_keys']['public_api_key'];
			} else {
				// Try legacy WPAICG options
				$legacy = get_option('wpaicg_options');
				if ( is_array($legacy) && ! empty($legacy['rest_api_key']) ) {
					$key = $legacy['rest_api_key'];
				}
			}
		}

		if ( $is_local ) {
			// Extract relative path for rest_do_request.
			$path = wp_parse_url( $url, PHP_URL_PATH );
			$path = preg_replace( '/.*wp-json/', '', $path );

			$request = new \WP_REST_Request( $method, $path );
			$request->set_header( 'Authorization', 'Bearer ' . $key );
			$request->set_header( 'Content-Type', 'application/json' );

			if ( ! empty( $payload ) ) {
				$request->set_body( wp_json_encode( $payload ) );
			}

			// The wrapped plugin runs in-process via rest_do_request(). If it
			// throws a fatal (e.g. a missing API key passed into a
			// string-typed argument), PHP 7+ raises a catchable Throwable —
			// but left uncaught it kills this entire request, replacing our
			// JSON response with WordPress's raw fatal-error HTML page. Catch
			// it here so a crash in the wrapped plugin can't take down ours.
			try {
				$response = rest_do_request( $request );
			} catch ( \Throwable $e ) {
				VMSAI_Logger::error( 'engine.text.aipuffer', 'Local REST dispatch crashed.', array( 'message' => $e->getMessage(), 'path' => $path ) );
				return array(
					'ok'    => false,
					'error' => sprintf(
						/* translators: %s: underlying error message */
						__( "AI Puffer's backend plugin crashed while generating (%s). It likely has no API key configured — check that plugin's own settings, or reorder the text engine chain.", 'vm-social-ai-pro' ),
						$e->getMessage()
					),
				);
			}

			if ( ! $response->is_error() ) {
				return array( 'ok' => true, 'json' => $response->get_data() );
			}

			// Log detailed internal error
			VMSAI_Logger::error( 'engine.text.aipuffer', 'Local REST Fail.', array(
				'status' => $response->get_status(),
				'path' => $path,
				'data' => $response->get_data()
			) );

			// Fallback: Internal REST failed, try loopback HTTP.
		}

		return VMSAI_Http::request( $method, $url, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $key ),
			'json'    => $payload,
			'timeout' => 60,
			'retries' => 1
		) );
	}

	public function generate( $system, $prompt, array $args = array() ) {
		$bot_id   = VMSAI_Settings::credential( 'aipuffer_bot_id' );
		$endpoint = $this->detect_endpoint();
		$is_mwai  = ( strpos( $endpoint, 'mwai/v1' ) !== false );
		$is_aipkit = ( strpos( $endpoint, 'aipkit/v1' ) !== false );

		$payload = array();
		$url     = '';

		if ( $bot_id ) {
			// AIPKit's /chat/{bot_id}/message route requires a numeric bot
			// ID in the URL itself (route regex is \d+) — a non-numeric
			// value (e.g. a bot name typed in by mistake) 404s before the
			// request even reaches AIPKit's own code, producing a confusing
			// generic error. Catch it here with an actionable message.
			if ( $is_aipkit && ! is_numeric( $bot_id ) ) {
				return array(
					'ok' => false,
					'text' => '',
					'model' => '',
					'error' => __( 'AI Puffer Chatbot ID must be the numeric bot ID from AIPKit, not a bot name.', 'vm-social-ai-pro' ),
				);
			}

			if ( $is_mwai ) {
				$url     = $endpoint . '/simpleChatbotQuery';
				$payload = array( 'botId' => $bot_id, 'message' => $system . "\n\n" . $prompt, 'newChat' => true );
			} else {
				$url     = $endpoint . '/chat/' . rawurlencode( $bot_id ) . '/message';
				$payload = array(
					'messages' => array( array( 'role' => 'user', 'content' => trim( $system . "\n\n" . $prompt ) ) ),
					'bot_id'   => $bot_id,
					'context'  => array( 'business_dna' => VMSAI_Brain::get( 'one_liner' ), 'source' => 'vm-social-ai-pro' ),
					'stream'   => false,
					'aipkit_api_key' => VMSAI_Settings::credential( 'aipuffer_key' )
				);
			}
		} elseif ( $is_aipkit ) {
			// AIPKit's keyless /generate route requires 'provider' and
			// 'model' as REQUIRED parameters in its own REST schema — with
			// no bot configured, vm-social-ai has no way to know which
			// upstream provider/model the site owner wants AIPKit to use.
			// Sending the request anyway is guaranteed to fail WordPress's
			// own arg validation (400 rest_missing_callback_param) before
			// AIPKit's code even runs, so fail fast with a clear reason
			// instead of wasting a doomed round trip.
			return array(
				'ok' => false,
				'text' => '',
				'model' => '',
				'error' => __( 'AI Puffer needs a Chatbot ID configured (AIPKit has no keyless generation endpoint vm-social-ai can call — set up a bot in AIPKit and enter its numeric ID).', 'vm-social-ai-pro' ),
			);
		} else {
			// Non-AIPKit backend (legacy WPAICG / Meow Apps) — leave the
			// original generic /generate attempt for those namespaces,
			// since their actual schema hasn't been verified here.
			$url     = $endpoint . '/generate';
			$payload = array(
				'messages'    => array( array( 'role' => 'system', 'content' => $system ), array( 'role' => 'user', 'content' => $prompt ) ),
				'max_tokens'  => (int) ( $args['max_tokens'] ?? 1200 ),
				'temperature' => (float) ( $args['temperature'] ?? 0.7 ),
			);
		}

		$res = $this->request( 'POST', $url, $payload );

		if ( ! $res['ok'] ) {
			return array( 'ok' => false, 'text' => '', 'model' => '', 'error' => $res['error'], 'status' => (int) ( $res['status'] ?? 0 ) );
		}

		// ROBUST DEEP SCAN FOR REPLY (Adopted from VMAI SEO Autopilot)
		$reply = '';
		$data = $res['json'];
		$targets = array();
		if ( isset( $data['data'] ) ) $targets[] = $data['data'];
		$targets[] = $data;

		foreach ( $targets as $target ) {
			if ( is_string( $target ) && ! empty( $target ) ) {
				$reply = $target; break;
			}
			if ( is_array( $target ) ) {
				foreach ( array( 'reply', 'content', 'response', 'text', 'output', 'answer', 'result' ) as $f ) {
					if ( ! empty( $target[ $f ] ) && is_string( $target[ $f ] ) ) {
						$reply = $target[ $f ]; break 2;
					}
				}
			}
		}

		if ( ! $reply ) {
			VMSAI_Logger::error( 'engine.text.aipuffer', 'Empty response from Puffer. JSON: ' . wp_json_encode( $data ) );
			return array( 'ok' => false, 'text' => '', 'model' => '', 'error' => 'Puffer returned no text content.' );
		}

		// AIPKit's chat response includes the bot's actually-configured
		// model (class-aipkit-rest-chat-handler.php: 'model' =>
		// $bot_settings['model']) — authoritative, unlike anything the
		// model's own reply text might claim about itself when asked
		// ("what model are you" answers are notoriously unreliable). Prefer
		// it when present.
		$real_model = is_array( $data ) ? (string) ( $data['model'] ?? '' ) : '';
		$model      = $real_model ?: ( $bot_id ? 'aipuffer:bot:' . $bot_id : 'aipuffer:gen' );

		return array( 'ok' => true, 'text' => $reply, 'model' => $model, 'error' => '' );
	}

	public function list_models() {
		if ( ! $this->is_configured() ) return array();
		$endpoint = $this->detect_endpoint();
		$is_mwai   = ( strpos( $endpoint, 'mwai/v1' ) !== false );
		$is_aipkit = ( strpos( $endpoint, 'aipkit/v1' ) !== false );

		$models = array();

		// LOCAL SYNC: If on same site, try to reach into the backend classes directly.
		if ( ( ! VMSAI_Settings::credential( 'aipuffer_site' ) || strpos( home_url(), VMSAI_Settings::credential( 'aipuffer_site' ) ) !== false ) ) {
			// 1. AIPKit (Modern) — method_exists guard: renamed/removed
			// statics in newer AIPKit builds would fatal this listing.
			if ( class_exists( '\WPAICG\AIPKit_Providers' ) && method_exists( '\WPAICG\AIPKit_Providers', 'get_model_list' ) ) {
				$aip_providers = array( 'OpenAI', 'Google', 'Claude', 'OpenRouter', 'DeepSeek', 'xAI' );
				foreach ( $aip_providers as $p ) {
					$list = \WPAICG\AIPKit_Providers::get_model_list( $p );
					if ( is_array( $list ) ) {
						foreach ( $list as $m ) {
							$id = is_array( $m ) ? ( $m['id'] ?? '' ) : (string) $m;
							if ( $id ) {
								$models[] = array(
									'id' => $id,
									'label' => 'AIP: ' . ( is_array( $m ) ? ( $m['name'] ?? $id ) : $id ),
									'context' => 0,
									'free' => ( false !== stripos( $id, 'free' ) ) || ( false !== stripos( $id, 'flash' ) )
								);
							}
						}
					}
				}
			}

			// 2. Chatbots (AIPKit)
			if ( class_exists( '\WPAICG\Chat\Storage\BotStorage' ) ) {
				$storage = new \WPAICG\Chat\Storage\BotStorage();
				$all_bots = $storage->get_chatbots( false );
				if ( is_array( $all_bots ) ) {
					foreach ( $all_bots as $bot ) {
						$models[] = array(
							'id'      => $bot->ID,
							'label'   => ( $bot->post_title ?? $bot->ID ) . ' (AIP Bot)',
							'context' => 0,
							'free'    => false
						);
					}
				}
			}
		}

		// If we found local models, return them.
		if ( ! empty( $models ) ) {
			$unique = array();
			foreach ( $models as $m ) $unique[ $m['id'] ] = $m;
			return array_values( $unique );
		}

		// REMOTE SYNC / FALLBACK
		if ( $is_aipkit ) {
			return array( array( 'id' => 'default', 'label' => 'Default Puffer (set the numeric Chatbot ID above to target a specific bot)' ) );
		}

		// 1. Fetch Standard Models via REST (Legacy/Meow)
		$res = $this->request( 'GET', $endpoint . ( $is_mwai ? '/bots' : '/models' ) );

		if ( $res['ok'] ) {
			$list = $res['json']['models'] ?? $res['json']['data'] ?? $res['json']['bots'] ?? $res['json'] ?? array();
			foreach ( (array) $list as $m ) {
				$id = is_array( $m ) ? ( $m['id'] ?? '' ) : (string) $m;
				if ( $id ) {
					$models[] = array( 'id' => $id, 'label' => is_array( $m ) ? ( $m['name'] ?? $id ) : $id, 'context' => 0, 'free' => false );
				}
			}
		}

		// 2. Fetch Strategic Bots (RAG/Vector)
		$bot_paths = array( '/chat/list', '/chat/bots' );
		if ( $is_mwai ) $bot_paths = array( '/bots' );

		foreach ( $bot_paths as $path ) {
			$res_b = $this->request( 'GET', $endpoint . $path );
			if ( $res_b['ok'] ) {
				$bots = $res_b['json']['bots'] ?? $res_b['json']['data'] ?? $res_b['json']['chatbots'] ?? $res_b['json'] ?? array();
				if ( is_array($bots) ) {
					foreach ( $bots as $bot ) {
						if ( isset( $bot['id'] ) ) {
							$models[] = array(
								'id'      => $bot['id'],
								'label'   => ( $bot['name'] ?? $bot['id'] ) . ' (Bot/RAG)',
								'context' => 0,
								'free'    => false
							);
						}
					}
				}
			}
		}

		// De-duplicate by ID
		$unique = array();
		foreach ( $models as $m ) $unique[ $m['id'] ] = $m;

		return array_values( $unique ) ?: array( array( 'id' => 'default', 'label' => 'Default Puffer' ) );
	}
}
