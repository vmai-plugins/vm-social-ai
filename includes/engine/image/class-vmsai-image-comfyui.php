<?php
/**
 * ComfyUI image provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Drives a self-hosted ComfyUI instance over its HTTP API.
 *
 * The admin supplies a saved workflow in API format. Placeholder tokens in
 * that JSON are swapped for the live prompt and dimensions before queueing:
 *
 *   {{prompt}}    the generated visual prompt
 *   {{negative}}  the negative prompt
 *   {{width}}     target width in pixels
 *   {{height}}    target height in pixels
 *   {{seed}}      a fresh random seed
 */
class VMSAI_Image_Comfyui implements VMSAI_Image_Provider {

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'comfyui';
	}

	/**
	 * Provider label.
	 *
	 * @return string
	 */
	public function label() {
		return 'ComfyUI (self-hosted)';
	}

	/**
	 * Server base URL.
	 *
	 * @return string
	 */
	private function base() {
		return untrailingslashit( VMSAI_Settings::credential( 'comfyui_url' ) );
	}

	/**
	 * Needs both a host and a workflow to be useful.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->base() && '' !== VMSAI_Settings::credential( 'comfyui_workflow' );
	}

	/**
	 * Optional auth headers for proxied instances.
	 *
	 * @return array
	 */
	private function headers() {
		$token = VMSAI_Settings::credential( 'comfyui_token' );
		return $token ? array( 'Authorization' => 'Bearer ' . $token ) : array();
	}

	/**
	 * Create an image.
	 *
	 * @param string $prompt Visual prompt.
	 * @param array  $args   Options.
	 * @return array
	 */
	public function create( $prompt, array $args = array() ) {
		$workflow = $this->build_workflow( $prompt, $args );

		if ( ! $workflow ) {
			return $this->fail( __( 'The ComfyUI workflow JSON is invalid.', 'vm-social-ai-pro' ) );
		}

		$client_id = 'vmsai-' . wp_generate_password( 12, false );

		$queued = VMSAI_Http::post(
			$this->base() . '/prompt',
			array(
				'headers' => $this->headers(),
				'json'    => array( 'prompt' => $workflow, 'client_id' => $client_id ),
				'scope'   => 'engine.image.comfyui',
				'retries' => 0,
				'timeout' => 30,
			)
		);

		if ( ! $queued['ok'] ) {
			return $this->fail( $queued['error'] );
		}

		$prompt_id = (string) ( $queued['json']['prompt_id'] ?? '' );
		if ( ! $prompt_id ) {
			return $this->fail( __( 'ComfyUI did not return a prompt id.', 'vm-social-ai-pro' ) );
		}

		$file = $this->poll( $prompt_id );
		if ( ! $file ) {
			return $this->fail( __( 'Timed out waiting for the ComfyUI render.', 'vm-social-ai-pro' ) );
		}

		$url = $this->base() . '/view?' . http_build_query(
			array(
				'filename'  => $file['filename'],
				'subfolder' => $file['subfolder'],
				'type'      => $file['type'],
			)
		);

		$binary = VMSAI_Http::fetch_binary( $url, 60 );

		if ( ! $binary ) {
			return $this->fail( __( 'Could not download the ComfyUI output.', 'vm-social-ai-pro' ) );
		}

		return array( 'ok' => true, 'binary' => $binary, 'url' => $url, 'mime' => 'image/png', 'credit' => '', 'error' => '' );
	}

	/**
	 * Substitute placeholders into the stored workflow.
	 *
	 * @param string $prompt Visual prompt.
	 * @param array  $args   Options.
	 * @return array|null
	 */
	private function build_workflow( $prompt, array $args ) {
		$raw = VMSAI_Settings::credential( 'comfyui_workflow' );

		$replacements = array(
			'{{prompt}}'   => $this->escape( $prompt ),
			'{{negative}}' => $this->escape( (string) ( $args['negative'] ?? 'text, watermark, logo, low quality, distorted, extra fingers' ) ),
			'{{width}}'    => (int) ( $args['width'] ?? 1080 ),
			'{{height}}'   => (int) ( $args['height'] ?? 1350 ),
			'{{seed}}'     => isset( $args['seed'] ) ? (int) $args['seed'] : wp_rand( 1, PHP_INT_MAX ),
		);

		$json = str_replace( array_keys( $replacements ), array_values( $replacements ), $raw );
		$data = json_decode( $json, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Make a string safe to inject into raw JSON.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function escape( $value ) {
		return trim( wp_json_encode( (string) $value ), '"' );
	}

	/**
	 * Poll the history endpoint until an output image appears.
	 *
	 * @param string $prompt_id Queue id.
	 * @return array|null
	 */
	private function poll( $prompt_id ) {
		// A render can legitimately take a while, but this runs inside a cron
		// tick: if the wait exceeded the PHP max_execution_time the worker
		// died mid-run instead of timing out gracefully. Cap the deadline to
		// whatever headroom the process actually has (default 60s builds
		// never reach the old 180s wall).
		$budget   = (int) ini_get( 'max_execution_time' );
		$deadline = time() + ( $budget > 0 ? max( 15, $budget - 10 ) : 60 );

		while ( time() < $deadline ) {
			sleep( 3 );

			$history = VMSAI_Http::get(
				$this->base() . '/history/' . rawurlencode( $prompt_id ),
				array( 'headers' => $this->headers(), 'scope' => 'engine.image.comfyui', 'retries' => 0, 'timeout' => 20 )
			);

			if ( ! $history['ok'] ) {
				continue;
			}

			$entry = $history['json'][ $prompt_id ] ?? array();
			foreach ( (array) ( $entry['outputs'] ?? array() ) as $node ) {
				foreach ( (array) ( $node['images'] ?? array() ) as $image ) {
					if ( ! empty( $image['filename'] ) ) {
						return array(
							'filename'  => (string) $image['filename'],
							'subfolder' => (string) ( $image['subfolder'] ?? '' ),
							'type'      => (string) ( $image['type'] ?? 'output' ),
						);
					}
				}
			}
		}

		return null;
	}

	/**
	 * Failure shape.
	 *
	 * @param string $error Message.
	 * @return array
	 */
	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}
}
