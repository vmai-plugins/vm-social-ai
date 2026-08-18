<?php
/**
 * Pollinations image provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Free, keyless image generation. This is the workhorse for high-volume
 * campaigns where paid image credits would otherwise cap daily output.
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
		$model  = VMSAI_Settings::get( 'image_model' )['pollinations'] ?? 'flux';
		$seed   = isset( $args['seed'] ) ? (int) $args['seed'] : wp_rand( 1, 999999 );

		$query = array(
			'width'    => $width,
			'height'   => $height,
			'model'    => $model,
			'seed'     => $seed,
			'nologo'   => 'true',
			'enhance'  => 'true',
			'safe'     => 'true',
			'referrer' => substr( (string) ( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'vmsocialai.local' ), 0, 100 ),
		);

		$token = VMSAI_Settings::credential( 'pollinations_token' );
		if ( $token ) {
			$query['token'] = $token;
		}

		$url = self::BASE . rawurlencode( $this->trim_prompt( $prompt ) ) . '?' . http_build_query( $query );

		$response = VMSAI_Http::get( $url, array(
			'headers' => array( 'Accept' => 'image/jpeg, image/png, image/*' ),
			'timeout' => 120,
			'scope'   => 'engine.image.pollinations'
		) );

		if ( ! $response['ok'] ) {
			return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $response['error'] );
		}

		$binary = $response['body'];

		// Robustness: Verify this is actually an image and not a JSON error masquerading as 200 OK.
		// JPEGs start with FF D8 FF. PNGs start with 89 50 4E 47.
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
	 * Keep the prompt inside a safe URL length.
	 *
	 * @param string $prompt Prompt.
	 * @return string
	 */
	private function trim_prompt( $prompt ) {
		$prompt = preg_replace( '/\s+/', ' ', trim( (string) $prompt ) );
		// Pro Tip: URL length is strictly capped by some CDNs (HTTP 431).
		// 300 chars is safer against HTTP 431 (Header/URL too large) errors.
		return mb_substr( $prompt, 0, 300 );
	}
}
