<?php
/**
 * Prompt Library — Category and Post Type overrides.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allows defining specific tones, CTAs, and instructions for different
 * parts of the website (e.g. Products get a sales tone, Blog posts get educational).
 */
class VMSAI_Prompts {

	const OPTION = 'vmsai_prompt_overrides';

	/**
	 * Get overrides for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{tone:string,cta:string,instructions:string}
	 */
	public static function get_overrides( $post_id ) {
		if ( ! $post_id ) {
			return array();
		}

		$overrides = get_option( self::OPTION, array() );
		$post      = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		// 1. Category Match (Specific niche strategy)
		$categories = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'slugs' ) );
		foreach ( (array) $categories as $slug ) {
			if ( ! empty( $overrides['category'][ $slug ] ) ) {
				return $overrides['category'][ $slug ];
			}
		}

		// 2. Post Type Match (Product vs Post)
		if ( ! empty( $overrides['type'][ $post->post_type ] ) ) {
			return $overrides['type'][ $post->post_type ];
		}

		return array();
	}

	/**
	 * Save an override template.
	 */
	public static function save( $group, $key, $data ) {
		$all = get_option( self::OPTION, array() );
		$all[ $group ][ $key ] = array(
			'tone'         => sanitize_text_field( $data['tone'] ?? '' ),
			'cta'          => sanitize_text_field( $data['cta'] ?? '' ),
			'instructions' => sanitize_textarea_field( $data['instructions'] ?? '' ),
		);
		update_option( self::OPTION, $all );
	}
}
