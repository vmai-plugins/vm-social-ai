<?php
/**
 * Image provider contract.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every image backend implements this.
 */
interface VMSAI_Image_Provider {

	/**
	 * Machine slug.
	 *
	 * @return string
	 */
	public function slug();

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the backend can be called.
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Produce an image.
	 *
	 * @param string $prompt Visual prompt.
	 * @param array  $args   Keys: width, height, style, seed, negative.
	 * @return array{ok:bool,binary:string,url:string,mime:string,credit:string,error:string}
	 */
	public function create( $prompt, array $args = array() );
}
