<?php
/**
 * Multi-Agent Critic System.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Acts as the "Quality Guard". Reviews every draft post before it reaches
 * the user, providing an internal score and tactical notes for improvement.
 */
class VMSAI_Critic {

	/**
	 * Run a post through the auditor agent.
	 *
	 * @param array $data Draft post data.
	 * @param array $slot Plan slot data.
	 * @param array $spec Channel specifications.
	 * @return array{score:int,notes:string,rewrite:string}
	 */
	public static function review( array $data, array $slot, array $spec ) {
		$system = 'You are a Ruthless Agency Director. Your reputation depends on elite, high-conversion copy. You reject anything that looks like generic AI output, lacks formatting, or has a weak hook.

YOUR STANDARDS:
1. THE HOOK: Must be a bold, scroll-stopping statement.
2. FORMATTING: Must use bolding (🚀 **TEXT**) and bullet points (✨) for readability. No blocks of plain text.
3. AUTHORITY: Must sound like a senior expert, not a chatbot.
4. RELEVANCY: Image prompt must be a literal, concrete scene, not abstract gear.';

		$prompt = "AUDIT THIS SOCIAL MEDIA POST:\n\n"
			. "Topic: {$slot['topic']}\n"
			. "Body: {$data['body']}\n"
			. "Image Prompt: " . ( $data['image_prompt'] ?? '' ) . "\n\n"
			. "CRITERIA:\n"
			. "1. Does it use bolding and bullet points effectively?\n"
			. "2. Is the hook authoritative and punchy?\n"
			. "3. Is there enough white space (short paragraphs)?\n"
			. "4. Does the CTA create urgency or clear next steps?\n"
			. "5. Does the image prompt match the topic exactly?\n\n"
			. "If any of these fail, Provide a full ELITE REWRITE in the 'rewrite' field and set score < 80."
			. "Return ONLY JSON:\n"
			. "{\n"
			. "  \"score\": 0-100,\n"
			. "  \"notes\": \"Direct feedback to the writer.\",\n"
			. "  \"rewrite\": \"Provide a full, elite, formatted rewrite if score < 85. Use bolding and bullets.\",\n"
			. "  \"image_prompt_rewrite\": \"Rewrite the image prompt if it lacks concrete relevancy.\"\n"
			. "}";

		$result = vmsai()->text_engine()->generate_json( $system, $prompt, array( 'temperature' => 0.2 ) );

		if ( empty( $result['ok'] ) ) {
			return array( 'score' => 85, 'notes' => '', 'rewrite' => '', 'image_prompt_rewrite' => '' );
		}

		return array(
			'score'                => (int) ( $result['data']['score'] ?? 85 ),
			'notes'                => self::flatten( $result['data']['notes'] ?? '' ),
			'rewrite'              => self::flatten( $result['data']['rewrite'] ?? '' ),
			'image_prompt_rewrite' => self::flatten( $result['data']['image_prompt_rewrite'] ?? '' ),
		);
	}

	/**
	 * Coerce a model field to text.
	 *
	 * The prompt asks for a string, but models routinely answer with a list
	 * of bullet points instead. Casting an array with (string) yields the
	 * literal word "Array", which is exactly what then got saved to the post
	 * and shown to the reviewer as its audit feedback.
	 *
	 * @param mixed $value Model output.
	 * @return string
	 */
	private static function flatten( $value ) {
		if ( is_array( $value ) ) {
			$parts = array();

			foreach ( $value as $item ) {
				$item = self::flatten( $item );
				if ( '' !== $item ) {
					$parts[] = $item;
				}
			}

			return implode( ' ', $parts );
		}

		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}

		return '';
	}
}
