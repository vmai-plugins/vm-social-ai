<?php
/**
 * OmniRoute text provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accesses OmniRoute AI Gateway for text generation.
 */
class VMSAI_Text_OmniRoute implements VMSAI_Text_Provider {

	public function slug() {
		return 'omniroute';
	}

	public function label() {
		return 'OmniRoute';
	}

	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'omniroute_key' ) && '' !== VMSAI_Settings::credential( 'omniroute_url' );
	}

	public function generate( $system, $prompt, array $args = array() ) {
		$key      = VMSAI_Settings::credential( 'omniroute_key' );
		$base_url = VMSAI_Settings::credential( 'omniroute_url' );
		$model    = $args['model'] ?? 'gpt-4o';

		$messages = array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $prompt ),
		);

		$payload = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => (float) ( $args['temperature'] ?? 0.7 ),
			'max_tokens'  => (int) ( $args['max_tokens'] ?? 1000 ),
		);

		if ( ! empty( $args['json'] ) ) {
			$payload['response_format'] = array( 'type' => 'json_object' );
		}

		$response = VMSAI_Http::post(
			rtrim( $base_url, '/' ) . '/chat/completions',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'json'    => $payload,
				'scope'   => 'engine.text.omniroute',
				'timeout' => 60,
			)
		);

		if ( ! $response['ok'] ) {
			return array( 'ok' => false, 'text' => '', 'model' => '', 'error' => $response['error'], 'status' => (int) ( $response['status'] ?? 0 ) );
		}

		$text = $response['json']['choices'][0]['message']['content'] ?? '';
		$real_model = $response['json']['model'] ?? $model;

		return array(
			'ok'    => true,
			'text'  => $text,
			'model' => $real_model,
			'usage' => array(
				'prompt_tokens'     => $response['json']['usage']['prompt_tokens'] ?? 0,
				'completion_tokens' => $response['json']['usage']['completion_tokens'] ?? 0,
			),
			'error' => '',
		);
	}

	public function list_models() {
		$key      = VMSAI_Settings::credential( 'omniroute_key' );
		$base_url = VMSAI_Settings::credential( 'omniroute_url' );

		if ( ! $key || ! $base_url ) {
			return $this->default_models();
		}

		$response = VMSAI_Http::get(
			rtrim( $base_url, '/' ) . '/models',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'scope'   => 'engine.text.omniroute.models',
				'timeout' => 10,
			)
		);

		if ( ! $response['ok'] || empty( $response['json']['data'] ) ) {
			return $this->default_models();
		}

		$models = array();
		foreach ( $response['json']['data'] as $model ) {
			$id = $model['id'] ?? '';
			if ( ! $id ) continue;

			$models[] = array(
				'id'    => $id,
				'label' => $model['name'] ?? $id,
			);
		}

		return $models ?: $this->default_models();
	}

	private function default_models() {
		return array(
			array( 'id' => 'gpt-4o', 'label' => 'GPT-4o' ),
			array( 'id' => 'claude-3-5-sonnet', 'label' => 'Claude 3.5 Sonnet' ),
			array( 'id' => 'meta-llama/llama-3.1-405b-instruct', 'label' => 'Llama 3.1 405B' ),
		);
	}
}
