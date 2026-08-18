<?php
/**
 * Text provider contract.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every text generation backend implements this.
 */
interface VMSAI_Text_Provider {

	/**
	 * Machine slug, e.g. "gemini".
	 *
	 * @return string
	 */
	public function slug();

	/**
	 * Human label for the admin.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether credentials/endpoints are present.
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Generate a completion.
	 *
	 * @param string $system  System instruction.
	 * @param string $prompt  User prompt.
	 * @param array  $args    Keys: model, temperature, max_tokens, json.
	 * @return array{ok:bool,text:string,model:string,error:string}
	 */
	public function generate( $system, $prompt, array $args = array() );

	/**
	 * Live model catalogue.
	 *
	 * @return array List of array{id:string,label:string,context:int,free:bool}.
	 */
	public function list_models();
}
