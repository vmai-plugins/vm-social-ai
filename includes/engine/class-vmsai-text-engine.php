<?php
/**
 * Engine 1 — text.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routes a generation request down an ordered chain of providers until one
 * succeeds. Every failure is logged and counted against that provider's
 * circuit breaker, so a dead key degrades the chain instead of the plugin.
 */
class VMSAI_Text_Engine {

	/**
	 * Instantiated providers keyed by slug.
	 *
	 * @var VMSAI_Text_Provider[]
	 */
	private $providers = array();

	/**
	 * Build the provider registry.
	 */
	public function __construct() {
		$registry = array(
			'aipuffer'   => 'VMSAI_Text_Aipuffer',
			'anthropic'  => 'VMSAI_Text_Anthropic',
			'gemini'     => 'VMSAI_Text_Gemini',
			'openrouter' => 'VMSAI_Text_Openrouter',
			'nvidia'     => 'VMSAI_Text_Nvidia',
			'ollama'     => 'VMSAI_Text_Ollama',
		);

		/**
		 * Filter the text provider class map.
		 *
		 * @param array $registry slug => class name.
		 */
		$registry = apply_filters( 'vmsai_text_providers', $registry );

		foreach ( $registry as $slug => $class ) {
			if ( class_exists( $class ) ) {
				$this->providers[ $slug ] = new $class();
			}
		}
	}

	/**
	 * All registered providers.
	 *
	 * @return VMSAI_Text_Provider[]
	 */
	public function providers() {
		return $this->providers;
	}

	/**
	 * Fetch one provider.
	 *
	 * @param string $slug Provider slug.
	 * @return VMSAI_Text_Provider|null
	 */
	public function provider( $slug ) {
		return $this->providers[ $slug ] ?? null;
	}

	/**
	 * The configured chain, filtered to providers that can actually run.
	 *
	 * @param bool $respect_breaker Skip tripped providers.
	 * @return string[]
	 */
	public function chain( $respect_breaker = true ) {
		$chain = (array) VMSAI_Settings::get( 'text_chain', array() );
		$out   = array();

		foreach ( $chain as $slug ) {
			$provider = $this->provider( $slug );
			if ( ! $provider || ! $provider->is_configured() ) {
				continue;
			}
			if ( $respect_breaker && ! VMSAI_Circuit::is_open( 'text:' . $slug ) ) {
				continue;
			}
			$out[] = $slug;
		}

		return $out;
	}

	/**
	 * Run a prompt through the chain.
	 *
	 * @param string $system System instruction.
	 * @param string $prompt User prompt.
	 * @param array  $args   Keys: temperature, max_tokens, json, prefer, persona.
	 * @return array{ok:bool,text:string,provider:string,model:string,error:string,tried:array}
	 */
	public function generate( $system, $prompt, array $args = array() ) {
		$chain = $this->chain();

		// Persona Injection (Adopted from VMAI SEO Autopilot)
		$persona = $args['persona'] ?? 'strategist';
		$personas = array(
			'strategist' => 'You are an Elite Social Media Growth Architect. You focus on virality, engagement hooks, and brand authority.',
			'wordsmith'   => 'You are a Senior Copywriter. You write punchy, human-like copy that sounds like a professional expert, not a bot.',
			'critic'     => 'You are a Brutal Content Auditor. You find generic AI filler, corporate jargon, and weak hooks and kill them.',
			'researcher' => 'You are a Market Intelligence Analyst. You find trending signals and news-worthy angles.'
		);
		$persona_instruction = $personas[ $persona ] ?? $personas['strategist'];
		$system = $persona_instruction . "\n\n" . $system;

		// An explicit preference jumps the queue for this call only.
		if ( ! empty( $args['prefer'] ) && in_array( $args['prefer'], $chain, true ) ) {
			$chain = array_merge( array( $args['prefer'] ), array_diff( $chain, array( $args['prefer'] ) ) );
		}

		// Skip specific providers for this call (e.g. on semantic retry).
		if ( ! empty( $args['skip'] ) ) {
			$chain = array_diff( $chain, (array) $args['skip'] );
		}

		if ( ! $chain ) {
			return array(
				'ok'       => false,
				'text'     => '',
				'provider' => '',
				'model'    => '',
				'error'    => __( 'No text provider is configured. Add a key under Engines.', 'vm-social-ai-pro' ),
				'tried'    => array(),
			);
		}

		$tried = array();

		foreach ( $chain as $slug ) {
			$provider = $this->provider( $slug );
			$models   = (array) VMSAI_Settings::get( 'text_model', array() );
			$call     = $args;

			if ( empty( $call['model'] ) && ! empty( $models[ $slug ] ) ) {
				$call['model'] = $models[ $slug ];
			}

			$started = microtime( true );
			$result  = $provider->generate( $system, $prompt, $call );
			$elapsed = round( ( microtime( true ) - $started ) * 1000 );

			if ( ! empty( $result['ok'] ) ) {
				VMSAI_Circuit::success( 'text:' . $slug );
				VMSAI_Logger::debug(
					'engine.text',
					sprintf( 'Generated via %s in %dms.', $slug, $elapsed ),
					array( 'model' => $result['model'], 'chars' => strlen( $result['text'] ) )
				);

				// Record Usage.
				VMSAI_Usage::record( array(
					'provider'   => $slug,
					'model'      => $result['model'],
					'modality'   => 'text',
					'usage_type' => 'generation',
					'tokens_in'  => (int) ( $result['usage']['prompt_tokens'] ?? ( strlen( $system . $prompt ) / 4 ) ),
					'tokens_out' => (int) ( $result['usage']['completion_tokens'] ?? ( strlen( $result['text'] ) / 4 ) ),
					'cost'       => (float) ( $result['usage']['total_cost'] ?? 0 ),
				) );

				$cleaned_text = $this->clean( $result['text'] );

				// QUALITY CHECKPASS (Recursive refinement)
				if ( ! empty($args['quality_check']) && $persona === 'wordsmith' ) {
					$checker_system = $personas['critic'];
					$checker_prompt = "Review this social post copy. If it sounds like generic AI (e.g. uses 'in today\'s world', 'let\'s dive in', or repetitive openers), reply with 'REVISE: [Reason]'. Otherwise reply 'PASS'.\n\nCOPY:\n" . $cleaned_text;

					$check_res = $this->generate( $checker_system, $checker_prompt, array( 'persona' => 'critic', 'temperature' => 0.1 ) );
					if ( $check_res['ok'] && stripos($check_res['text'], 'REVISE') !== false ) {
						$feedback = trim(str_ireplace('REVISE:', '', $check_res['text']));
						VMSAI_Logger::info( 'engine.text', 'Quality check rejected copy. Attempting one-time rewrite.', array( 'reason' => $feedback ) );

						$new_args = $args;
						unset($new_args['quality_check']); // Stop recursion
						$new_system = $system . "\n\nCRITICAL FEEDBACK ON LAST ATTEMPT (DO NOT REPEAT THESE MISTAKES): " . $feedback;
						return $this->generate($new_system, $prompt, $new_args);
					}
				}

				return array(
					'ok'       => true,
					'text'     => $cleaned_text,
					'provider' => $slug,
					'model'    => (string) $result['model'],
					'error'    => '',
					'tried'    => $tried,
				);
			}

			$tried[ $slug ]  = $result['error'];
			$status          = (int) ( $result['status'] ?? 0 );
			$is_rate_limited = in_array( $status, array( 429, 408 ), true );

			// Rate limiting means the provider is healthy but busy right now,
			// not broken — count it toward the trip threshold at half weight
			// so it takes a sustained burst, not one busy moment, to take a
			// working provider offline for the full cooldown.
			VMSAI_Circuit::failure( 'text:' . $slug, $result['error'], $is_rate_limited ? 0.5 : 1.0 );
			VMSAI_Logger::warn( 'engine.text', sprintf( '%s failed, falling through.', $slug ), array( 'error' => $result['error'], 'status' => $status ) );
		}

		if ( ! empty( $tried ) ) {
			$first_provider = array_key_first( $tried );
			/* translators: 1: provider name, 2: error message */
			$main_error     = sprintf( __( 'Primary provider (%1$s) reported: %2$s', 'vm-social-ai-pro' ), $first_provider, $tried[ $first_provider ] );
		} else {
			$main_error = __( 'Every text provider in the chain failed.', 'vm-social-ai-pro' );
		}

		return array(
			'ok'       => false,
			'text'     => '',
			'provider' => '',
			'model'    => '',
			'error'    => $main_error,
			'tried'    => $tried,
		);
	}

	/**
	 * Generate and decode a JSON payload, tolerating fenced or chatty output.
	 *
	 * @param string $system System instruction.
	 * @param string $prompt User prompt.
	 * @param array  $args   Options.
	 * @return array{ok:bool,data:array,provider:string,error:string}
	 */
	public function generate_json( $system, $prompt, array $args = array() ) {
		$args['json'] = true;
		$system      .= "\n\nReturn only valid JSON. No prose, no markdown fences, no commentary.";

		$attempts = 0;
		$last     = '';
		$skip     = array();

		while ( $attempts < 2 ) {
			$attempts++;
			$result = $this->generate( $system, $prompt, array_merge( $args, array( 'skip' => $skip ) ) );

			if ( empty( $result['ok'] ) ) {
				return array( 'ok' => false, 'data' => array(), 'provider' => '', 'error' => $result['error'] );
			}

			$data = self::decode_json( $result['text'] );

			if ( is_array( $data ) ) {
				return array( 'ok' => true, 'data' => $data, 'provider' => $result['provider'], 'error' => '' );
			}

			// If provider returned bad JSON, skip them on the next attempt.
			$skip[] = $result['provider'];
			VMSAI_Circuit::failure( 'text:' . $result['provider'], 'Malformed JSON response' );

			$last     = __( 'Model returned malformed JSON.', 'vm-social-ai-pro' );
			$args['temperature'] = 0.3;
		}

		VMSAI_Logger::error( 'engine.text', 'JSON parse failed after retry.', array( 'raw_text' => $result['text'] ) );
		return array( 'ok' => false, 'data' => array(), 'provider' => '', 'error' => $last );
	}

	/**
	 * Pull the first JSON object or array out of a string.
	 *
	 * @param string $text Raw model output.
	 * @return array|null
	 */
	public static function decode_json( $text ) {
		$text = trim( (string) $text );
		if ( ! $text ) {
			return null;
		}

		// 1. Direct attempt.
		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// 2. Strip markdown fences.
		$text = preg_replace( '/^```(?:json)?\s*|\s*```$/m', '', $text );
		$text = trim( $text );
		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// 3. Brute force: find the outermost braces.
		$first_curly = strpos( $text, '{' );
		$last_curly  = strrpos( $text, '}' );
		if ( false !== $first_curly && false !== $last_curly && $last_curly > $first_curly ) {
			$candidate = substr( $text, $first_curly, $last_curly - $first_curly + 1 );

			// Fix common trailing comma error before decoding
			$candidate = preg_replace( '/,\s*([\}\]])/', '$1', $candidate );

			$decoded   = json_decode( $candidate, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		$first_bracket = strpos( $text, '[' );
		$last_bracket  = strrpos( $text, ']' );
		if ( false !== $first_bracket && false !== $last_bracket && $last_bracket > $first_bracket ) {
			$candidate = substr( $text, $first_bracket, $last_bracket - $first_bracket + 1 );

			// Fix common trailing comma error
			$candidate = preg_replace( '/,\s*([\}\]])/', '$1', $candidate );

			$decoded   = json_decode( $candidate, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * Normalise model output for publishing.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function clean( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = preg_replace( '/^```[a-z]*\s*|\s*```$/m', '', $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		// Models like to wrap the whole caption in quotes.
		// Strip them only if they are the very first and last characters.
		$text = trim( $text );
		if ( strlen( $text ) > 2 && '"' === $text[0] && '"' === substr( $text, -1 ) ) {
			$text = substr( $text, 1, -1 );
		}
		return trim( $text );
	}
}
