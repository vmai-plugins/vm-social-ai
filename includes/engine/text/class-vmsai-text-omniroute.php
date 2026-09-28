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
		$saved_m  = VMSAI_Settings::get( 'text_model', array() );
		$model    = $args['model'] ?? ( $saved_m['omniroute'] ?? 'auto/best-coding' );
		if ( empty( $model ) ) {
			$model = 'auto/best-coding';
		}

		$messages = array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $prompt ),
		);

		$payload = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => (float) ( $args['temperature'] ?? 0.7 ),
			'max_tokens'  => (int) ( $args['max_tokens'] ?? 1000 ),
			'stream'      => false,
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

			// Skip pure video models from text list
			if ( str_contains( $id, 'veo-free' ) || str_contains( $id, 'veoaifree' ) ) continue;

			$models[] = array(
				'id'    => $id,
				'label' => $model['name'] ?? $id,
			);
		}

		return $models ?: $this->default_models();
	}

	private function default_models() {
		return array(
			array( 'id' => 'auto/best-coding', 'label' => 'Auto Best Coding (Recommended)' ),
			array( 'id' => 'auto/best-chat', 'label' => 'Auto Best Chat' ),
			array( 'id' => 'auto/best-free', 'label' => 'Auto Best Free' ),
			array( 'id' => 'gemini-2.5-flash', 'label' => 'Google Gemini 2.5 Flash' ),
			array( 'id' => 'auto/deepseek-v3', 'label' => 'DeepSeek V3' ),
		);
	}
}
