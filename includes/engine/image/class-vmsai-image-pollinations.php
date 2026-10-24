<?php
/**
 * Pollinations image provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Free, keyless image generation. High-quality photorealistic generation
 * powered by Sana (NVIDIA Linear Attention Diffusion) and Flux.
 */
class VMSAI_Image_Pollinations implements VMSAI_Image_Provider {

	const BASE = 'https://image.pollinations.ai/prompt/';

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'pollinations';
	}

	/**
	 * Provider label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Pollinations (free)';
	}

	/**
	 * Always available — no key required.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return true;
	}

	/**
	 * Create an image.
	 *
	 * @param string $prompt Visual prompt.
	 * @param array  $args   Options.
	 * @return array
	 */
	public function create( $prompt, array $args = array() ) {
		$width  = (int) ( $args['width'] ?? 1080 );
		$height = (int) ( $args['height'] ?? 1350 );

		$configured_model = '';
		$all_models       = VMSAI_Settings::get( 'image_model', array() );
		if ( is_array( $all_models ) && ! empty( $all_models['pollinations'] ) ) {
			$configured_model = trim( (string) $all_models['pollinations'] );
		}

		$token = VMSAI_Settings::credential( 'pollinations_token' );

		// Sana is Pollinations' official free diffusion model (NVIDIA Linear Attention, ICLR 2025).
		// Legacy 'flux' models now require a paid token; without a token Pollinations throttles with HTTP 402.
		$default_model = $token ? 'flux-realism' : 'sana';
		$model         = ! empty( $args['model'] ) ? $args['model'] : ( ! empty( $configured_model ) ? $configured_model : $default_model );

		if ( ! $token && in_array( $model, array( 'flux', 'flux-realism', 'turbo' ), true ) ) {
			$model = 'sana';
		}

		$seed = isset( $args['seed'] ) ? (int) $args['seed'] : wp_rand( 1, 999999 );

		// CRITICAL: enhance=false preserves prompt fidelity. When set to true, Pollinations' internal
		// LLM hallucinates and distorts temple/cultural scenes into fantasy or deformed artifacts.
		$enhance = isset( $args['enhance'] ) ? ( $args['enhance'] ? 'true' : 'false' ) : 'false';

		$query = array(
			'width'    => $width,
			'height'   => $height,
			'model'    => $model,
			'seed'     => $seed,
			'nologo'   => 'true',
			'enhance'  => $enhance,
			'safe'     => 'true',
			'referrer' => substr( (string) ( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'vmsocialai.local' ), 0, 100 ),
		);

		if ( $token ) {
			$query['token'] = $token;
		}

		$clean_prompt = $this->trim_prompt( $prompt );
		$url          = self::BASE . rawurlencode( $clean_prompt ) . '?' . http_build_query( $query );

		$response = VMSAI_Http::get( $url, array(
			'headers' => array( 'Accept' => 'image/jpeg, image/png, image/*' ),
			'timeout' => 120,
			'scope'   => 'engine.image.pollinations',
		) );

		$binary = $response['body'] ?? '';

		// Resilient Fallback: If primary model encounters an issue, fallback directly to 'sana'
		if ( strlen( $binary ) < 3000 && 'sana' !== $model ) {
			VMSAI_Logger::warn( 'engine.image.pollinations', sprintf( 'Model %s returned empty payload, retrying with sana.', $model ), array( 'error' => $response['error'] ?? '' ) );
			$query['model'] = 'sana';
			$url            = self::BASE . rawurlencode( $clean_prompt ) . '?' . http_build_query( $query );
			$response       = VMSAI_Http::get( $url, array(
				'headers' => array( 'Accept' => 'image/jpeg, image/png, image/*' ),
				'timeout' => 120,
				'scope'   => 'engine.image.pollinations',
			) );
			$binary = $response['body'] ?? '';
		}

		// Robustness & Soft-402 Resilience:
		// Pollinations occasionally returns an HTTP 402 header as a payment nag even while
		// successfully transmitting the entire generated JPEG binary in the body.
		// If binary starts with valid JPEG (FF D8 FF) or PNG (89 50 4E 47), accept it as valid!
		$is_image = false;
		$mime     = 'image/jpeg';
		if ( strlen( $binary ) > 10 ) {
			$header = bin2hex( substr( $binary, 0, 4 ) );
			if ( 'ffd8ff' === substr( $header, 0, 6 ) ) {
				$is_image = true;
				$mime     = 'image/jpeg';
			} elseif ( '89504e47' === $header ) {
				$is_image = true;
				$mime     = 'image/png';
			}
		}

		if ( ! $is_image || strlen( $binary ) < 3000 ) {
			if ( ! empty( $response['error'] ) ) {
				return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $response['error'] );
			}
			VMSAI_Logger::error( 'engine.image.pollinations', 'Pollinations returned invalid binary.', array( 'size' => strlen( $binary ), 'preview' => substr( $binary, 0, 100 ) ) );
			return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => __( 'Pollinations returned an empty or invalid payload.', 'vm-social-ai-pro' ) );
		}

		return array(
			'ok'     => true,
			'binary' => $binary,
			'url'    => $url,
			'mime'   => $mime,
			'credit' => '',
			'error'  => '',
		);
	}

	/**
	 * Keep the prompt inside a safe URL length while preserving full professional detail.
	 *
	 * @param string $prompt Prompt.
	 * @return string
	 */
	private function trim_prompt( $prompt ) {
		$prompt = preg_replace( '/\s+/', ' ', trim( (string) $prompt ) );

		// Cap at 1000 characters to prevent URL clipping while ensuring all camera,
		// lighting, resolution, and photorealistic modifiers are preserved.
		$limit = 1000;
		if ( mb_strlen( $prompt ) <= $limit ) {
			return $prompt;
		}

		$trimmed = mb_substr( $prompt, 0, $limit );
		$cut     = mb_strrpos( $trimmed, ' ' );

		return $cut ? mb_substr( $trimmed, 0, $cut ) : $trimmed;
	}
}
