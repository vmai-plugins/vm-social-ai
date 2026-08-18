<?php
/**
 * Hugging Face image provider (FLUX).
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accesses high-end models like FLUX.1-schnell via Hugging Face Inference API.
 * This is the best free option for readable text and posters.
 */
class VMSAI_Image_HuggingFace implements VMSAI_Image_Provider {

	const API_URL = 'https://api-inference.huggingface.co/models/black-forest-labs/FLUX.1-schnell';

	/**
	 * Provider slug.
	 */
	public function slug() {
		return 'huggingface';
	}

	/**
	 * Provider label.
	 */
	public function label() {
		return 'Hugging Face (FLUX)';
	}

	/**
	 * Configured when an API token exists.
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'hf_token' );
	}

	/**
	 * Create an image.
	 */
	public function create( $prompt, array $args = array() ) {
		$token = VMSAI_Settings::credential( 'hf_token' );

		// Pro Tip: For posters/infographics, FLUX needs a clean prompt.
		$payload = array(
			'inputs' => $prompt,
			'parameters' => array(
				'guidance_scale' => 3.5,
				'num_inference_steps' => 4, // Schnell is fast
			)
		);

		if ( ! empty( $args['width'] ) ) {
			$payload['parameters']['width'] = (int) $args['width'];
		}
		if ( ! empty( $args['height'] ) ) {
			$payload['parameters']['height'] = (int) $args['height'];
		}

		$response = VMSAI_Http::post( self::API_URL, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'X-Wait-For-Model' => 'true', // Tell HF to wait if model is loading
			),
			'json'    => $payload,
			'timeout' => 90,
			'scope'   => 'engine.image.huggingface'
		) );

		if ( ! $response['ok'] ) {
			$err = $response['error'];
			// Specific handling for DNS resolution issues (cURL error 6)
			if ( strpos( $err, 'Could not resolve host' ) !== false ) {
				VMSAI_Logger::error( 'engine.image.huggingface', 'DNS Resolution failed. Check your server\'s internet connection or firewall.', array( 'error' => $err ) );
				return $this->fail( __( 'Server DNS error: Could not connect to Hugging Face. Your server may be blocking outbound requests to api-inference.huggingface.co.', 'vm-social-ai-pro' ) );
			}

			// Handle model loading error (sometimes HF returns 503 as error message)
			if ( strpos( $err, 'estimated_time' ) !== false || strpos( $err, 'loading' ) !== false ) {
				return $this->fail( __( 'Model is still loading on Hugging Face. It should be ready in 30-60 seconds.', 'vm-social-ai-pro' ) );
			}

			return $this->fail( $err );
		}

		$status = (int) $response['status'];
		$binary = $response['body'];

		if ( 503 === $status ) {
			return $this->fail( __( 'Model is loading on Hugging Face. Try again in 30 seconds.', 'vm-social-ai-pro' ) );
		}

		if ( 200 !== $status ) {
			return $this->fail( 'HTTP ' . $status . ': ' . $binary );
		}

		if ( ! $binary || strlen( $binary ) < 10000 ) {
			return $this->fail( __( 'Hugging Face returned an invalid image payload.', 'vm-social-ai-pro' ) );
		}

		return array(
			'ok'     => true,
			'binary' => $binary,
			'url'    => '',
			'mime'   => 'image/jpeg',
			'credit' => 'Generated via FLUX on Hugging Face',
			'error'  => '',
		);
	}

	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}
}
