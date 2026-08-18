<?php
/**
 * Ollama text provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Self-hosted Ollama backend. Costs nothing per call, so it is the natural
 * last link in the chain: the site keeps producing even if every paid
 * provider is rate limited or down.
 */
class VMSAI_Text_Ollama implements VMSAI_Text_Provider {

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'ollama';
	}

	/**
	 * Provider label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Ollama (self-hosted)';
	}

	/**
	 * Base URL of the Ollama daemon.
	 *
	 * @return string
	 */
	private function base() {
		$url = VMSAI_Settings::credential( 'ollama_url', 'http://127.0.0.1:11434' );
		return untrailingslashit( $url );
	}

	/**
	 * Configured when a host is set.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->base();
	}

	/**
	 * Optional bearer auth for reverse-proxied installs.
	 *
	 * @return array
	 */
	private function headers() {
		$token = VMSAI_Settings::credential( 'ollama_token' );
		return $token ? array( 'Authorization' => 'Bearer ' . $token ) : array();
	}

	/**
	 * Generate text.
	 *
	 * @param string $system System instruction.
	 * @param string $prompt User prompt.
	 * @param array  $args   Options.
	 * @return array
	 */
	public function generate( $system, $prompt, array $args = array() ) {
		$models = (array) VMSAI_Settings::get( 'text_model', array() );
		$model  = ! empty( $args['model'] ) ? $args['model'] : ( ! empty( $models['ollama'] ) ? $models['ollama'] : 'llama3.1:8b' );

		$payload = array(
			'model'    => $model,
			'stream'   => false,
			'messages' => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
			'options'  => array(
				'temperature' => (float) ( $args['temperature'] ?? 0.85 ),
				'num_predict' => (int) ( $args['max_tokens'] ?? 1600 ),
			),
		);

		if ( ! empty( $args['json'] ) ) {
			$payload['format'] = 'json';
		}

		$response = VMSAI_Http::post(
			$this->base() . '/api/chat',
			array(
				'headers' => $this->headers(),
				'json'    => $payload,
				'scope'   => 'engine.text.ollama',
				'retries' => 0,
				'timeout' => max( 120, (int) VMSAI_Settings::get( 'request_timeout', 90 ) ),
			)
		);

		if ( ! $response['ok'] ) {
			return array( 'ok' => false, 'text' => '', 'model' => $model, 'error' => $response['error'], 'status' => (int) ( $response['status'] ?? 0 ) );
		}

		$text = (string) ( $response['json']['message']['content'] ?? $response['json']['response'] ?? '' );

		if ( '' === trim( $text ) ) {
			return array( 'ok' => false, 'text' => '', 'model' => $model, 'error' => 'Empty Ollama response.' );
		}

		return array( 'ok' => true, 'text' => $text, 'model' => $model, 'error' => '' );
	}

	/**
	 * Locally pulled models.
	 *
	 * @return array
	 */
	public function list_models() {
		$response = VMSAI_Http::get(
			$this->base() . '/api/tags',
			array( 'headers' => $this->headers(), 'scope' => 'engine.text.ollama', 'retries' => 0, 'timeout' => 15 )
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::error( 'engine.text.ollama', 'Model sync failed: ' . $response['error'] );
			return array();
		}

		$models = array();
		foreach ( (array) ( $response['json']['models'] ?? array() ) as $model ) {
			$id = (string) ( $model['name'] ?? '' );
			if ( ! $id ) {
				continue;
			}
			$size     = isset( $model['size'] ) ? size_format( (int) $model['size'] ) : '';
			$models[] = array(
				'id'      => $id,
				'label'   => $size ? $id . ' (' . $size . ')' : $id,
				'context' => 0,
				'free'    => true,
			);
		}

		return $models;
	}
}
