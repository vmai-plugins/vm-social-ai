<?php
/**
 * SEO Plugin Sync (RankMath/Yoast).
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syncs focus keywords from the most popular SEO plugins to give the AI
 * real-world data without manual entry.
 */
class VMSAI_SEO {

	/**
	 * Get focus keywords for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Focus keywords.
	 */
	public static function get_keywords( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}

		// 1. Rank Math
		if ( class_exists( 'RankMath' ) ) {
			$keywords = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			if ( $keywords ) {
				return $keywords;
			}
		}

		// 2. Yoast SEO
		if ( defined( 'WPSEO_VERSION' ) ) {
			$keywords = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
			if ( $keywords ) {
				return $keywords;
			}
		}

		// 3. VM SEO Brain
		if ( class_exists( 'VMSB_Keywords' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'vmsb_keywords';
			$kw    = $wpdb->get_var( $wpdb->prepare( "SELECT keyword FROM `$table` WHERE post_id = %d LIMIT 1", $post_id ) );
			if ( $kw ) {
				return $kw;
			}
		}

		// 4. Fallback: Post Tags
		$tags = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
		return ! is_wp_error( $tags ) ? implode( ', ', array_slice( $tags, 0, 3 ) ) : '';
	}
}
