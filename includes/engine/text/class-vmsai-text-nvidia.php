<?php
/**
 * NVIDIA NIM text provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * NVIDIA build.nvidia.com / NIM backend, which speaks the OpenAI
 * chat-completions shape.
 */
class VMSAI_Text_Nvidia implements VMSAI_Text_Provider {

	const BASE = 'https://integrate.api.nvidia.com/v1';

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'nvidia';
	}

	/**
	 * Provider label.
	 *
	 * @return string
	 */
	public function label() {
		return 'NVIDIA NIM';
	}

	/**
	 * Endpoint, overridable for a locally hosted NIM container.
	 *
	 * @return string
	 */
	private function base() {
		$custom = VMSAI_Settings::credential( 'nvidia_url' );
		return $custom ? untrailingslashit( $custom ) : self::BASE;
	}

	/**
	 * Configured when an API key exists.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'nvidia_key' );
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
		// An empty string (saved whenever the Engines form is submitted with
		// this field left blank) is not null, so ?? alone would never reach
		// the hardcoded default — treat blank the same as unset.
		$model = ( $args['model'] ?? '' ) ?: ( ( VMSAI_Settings::get( 'text_model' )['nvidia'] ?? '' ) ?: self::default_model() );

		$response = VMSAI_Http::post(
			$this->base() . '/chat/completions',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . VMSAI_Settings::credential( 'nvidia_key' ) ),
				'json'    => array(
					'model'       => $model,
					'messages'    => array(
						array( 'role' => 'system', 'content' => $system ),
						array( 'role' => 'user', 'content' => $prompt ),
					),
					'temperature' => (float) ( $args['temperature'] ?? 0.8 ),
					'max_tokens'  => (int) ( $args['max_tokens'] ?? 1600 ),
					'stream'      => false,
				),
				'scope'   => 'engine.text.nvidia',
				'retries' => 1,
			)
		);

		if ( ! $response['ok'] ) {
			return array( 'ok' => false, 'text' => '', 'model' => $model, 'error' => $response['error'], 'status' => (int) ( $response['status'] ?? 0 ) );
		}

		$text = (string) ( $response['json']['choices'][0]['message']['content'] ?? '' );

		if ( '' === trim( $text ) ) {
			return array( 'ok' => false, 'text' => '', 'model' => $model, 'error' => 'Empty NVIDIA response.' );
		}

		return array( 'ok' => true, 'text' => $text, 'model' => $model, 'error' => '' );
	}

	/**
	 * A model that's actually likely to work today — prefer whatever the
	 * last "Refresh model list" sync found still live, and only fall back to
	 * a hardcoded guess if no sync has ever run for NVIDIA. Note: unlike
	 * Gemini's catalogue, NVIDIA's model list isn't filtered for chat
	 * capability (list_models() below returns everything NIM publishes,
	 * including non-chat models), so this is a best-effort pick, not a
	 * guarantee.
	 *
	 * @return string
	 */
	private static function default_model() {
		$synced = VMSAI_Model_Sync::models( 'nvidia', 'text' );

		// Priority 1: Pick a strong, balanced Llama 3.1 instruct model if available.
		foreach ( $synced as $m ) {
			if ( false !== strpos( $m['model_id'], 'llama-3.1-70b-instruct' ) ) {
				return (string) $m['model_id'];
			}
		}

		// Priority 2: Any Llama 3.1 instruct.
		foreach ( $synced as $m ) {
			if ( false !== strpos( $m['model_id'], 'llama-3.1' ) && false !== strpos( $m['model_id'], 'instruct' ) ) {
				return (string) $m['model_id'];
			}
		}

		// Priority 3: NVIDIA's own Nemotron (often very good for formatting).
		foreach ( $synced as $m ) {
			if ( false !== strpos( $m['model_id'], 'nemotron' ) ) {
				return (string) $m['model_id'];
			}
		}

		if ( ! empty( $synced[0]['model_id'] ) ) {
			return (string) $synced[0]['model_id'];
		}

		return 'meta/llama-3.1-70b-instruct';
	}

	/**
	 * Live model list.
	 *
	 * @return array
	 */
	public function list_models() {
		if ( ! $this->is_configured() ) {
			return array();
		}

		$response = VMSAI_Http::get(
			$this->base() . '/models',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . VMSAI_Settings::credential( 'nvidia_key' ) ),
				'scope'   => 'engine.text.nvidia',
				'retries' => 0,
				'timeout' => 25,
			)
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::error( 'engine.text.nvidia', 'Model sync failed: ' . $response['error'] );
			return array();
		}

		$models = array();
		foreach ( (array) ( $response['json']['data'] ?? array() ) as $model ) {
			$id = (string) ( $model['id'] ?? '' );
			if ( ! $id ) {
				continue;
			}

			// Filter: NVIDIA NIM lists everything (embedding, reranking, multimodal).
			// We only want models compatible with chat-completions.
			$is_bad = preg_match( '/(embed|rerank|clip|canary|sdxl|translation|vila|medusa)/i', $id );
			if ( $is_bad ) {
				continue;
			}

			// Label cleaning: "meta/llama-3.1-70b-instruct" -> "Llama 3.1 70b Instruct"
			$label = str_replace( array( '/', '-', '_' ), ' ', $id );
			$label = ucwords( $label );

			// Determine if it's likely a chat/instruct model (preferred).
			$is_chat = preg_match( '/(instruct|chat|it|message|nemotron)/i', $id );

			// If it's a base model (no instruct/chat), we might still allow it but deprioritize?
			// For now, let's include everything that isn't explicitly "bad".

			$models[] = array(
				'id'      => $id,
				'label'   => $label,
				'context' => 0,
				'free'    => false,
			);
		}

		return $models;
	}
}
