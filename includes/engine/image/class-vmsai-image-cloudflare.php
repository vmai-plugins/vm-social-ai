<?php
/**
 * Cloudflare Workers AI image provider (SDXL).
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generates images via Cloudflare Workers AI.
 * Uses Stable Diffusion XL Lightning for high speed and free tier compatibility.
 */
class VMSAI_Image_Cloudflare implements VMSAI_Image_Provider {

	/**
	 * Provider slug.
	 */
	public function slug() {
		return 'cloudflare';
	}

	/**
	 * Provider label.
	 */
	public function label() {
		return 'Cloudflare Workers AI';
	}

	/**
	 * Configured when a Cloudflare API token exists (the same token powers
	 * Workers AI; r2_account_id alone — i.e. R2 storage only — is not enough).
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'cf_token' );
	}

	/**
	 * Create an image.
	 */
	public function create( $prompt, array $args = array() ) {
		$account_id = VMSAI_Settings::credential( 'r2_account_id' );
		$token      = VMSAI_Settings::credential( 'cf_token' );

		// Honour the model picked in the UI (list_models ids) so the list is
		// not decorative. Full "@cf/..." ids pass through unchanged.
		$model_key = (string) ( $args['model'] ?? '' );
		$known     = array(
			'stable-diffusion-xl-lightning' => '@cf/bytedance/stable-diffusion-xl-lightning',
			'stable-diffusion-xl-base-1.0'  => '@cf/stabilityai/stable-diffusion-xl-base-1.0',
		);
		$model = $known[ $model_key ] ?? ( $model_key ?: '@cf/bytedance/stable-diffusion-xl-lightning' );

		$url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/ai/run/{$model}";

		$response = VMSAI_Http::post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
			'json'    => array(
				'prompt' => $prompt,
				// Lightning is distilled for 4 steps; the base model needs more.
				'num_steps' => ( strpos( $model, 'lightning' ) !== false ) ? 4 : 20,
			),
			'timeout' => 90,
			'scope'   => 'engine.image.cloudflare'
		) );

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$binary = $response['body'];

		if ( ! $binary || strlen( $binary ) < 5000 ) {
			return $this->fail( __( 'Cloudflare returned an invalid image payload.', 'vm-social-ai-pro' ) );
		}

		return array(
			'ok'     => true,
			'binary' => $binary,
			'url'    => '',
			'mime'   => 'image/png',
			'credit' => 'Generated via Cloudflare Workers AI',
			'error'  => '',
		);
	}

	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}

	/**
	 * Model list.
	 */
	public function list_models() {
		return array(
			array( 'id' => 'stable-diffusion-xl-lightning', 'label' => 'SDXL Lightning (Fastest)' ),
			array( 'id' => 'stable-diffusion-xl-base-1.0', 'label' => 'SDXL Base 1.0' ),
		);
	}
}
