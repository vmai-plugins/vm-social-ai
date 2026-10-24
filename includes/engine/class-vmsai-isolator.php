<?php
/**
 * Commercial Podium Studio Scene Compositor for VM Social AI.
 *
 * Composites product visuals and graphics onto high-converting commercial
 * 3D podiums, luxury marble blocks, minimalist studio sets, and neon stages.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Isolator {

	/**
	 * Available commercial studio podium presets.
	 *
	 * @return array<string, array{label:string, prompt:string}>
	 */
	public static function get_backdrop_presets() {
		return array(
			'marble_gold'    => array(
				'label'  => '🏛️ Luxury Marble & Brushed Gold Podium',
				'prompt' => 'Commercial studio product podium, polished Italian white carrara marble cylinder with brushed gold trim, soft volumetric warm lighting, blurred silk curtain backdrop, high-end 8k commercial advertising photography background, empty center, no product',
			),
			'apple_minimal'  => array(
				'label'  => '📐 Apple Minimalist Architectural Matte',
				'prompt' => 'Minimalist commercial photography studio set, smooth matte grey concrete rectangular block, high-key clean soft lighting, pristine modern architectural composition, empty center, no product',
			),
			'cyber_neon'     => array(
				'label'  => '⚡ Cyberpunk Neon Obsidian Stage',
				'prompt' => 'Futuristic commercial display stage, dark reflective black obsidian platform, subtle glowing cyan and violet neon rim lighting, atmospheric studio haze, 8k commercial ad backdrop, empty center, no product',
			),
			'botanical'      => array(
				'label'  => '🌿 Botanical River Stone & Dewdrop Moss',
				'prompt' => 'Biophilic commercial product display set, flat smooth wet river stone pedestal, surrounded by fresh lush monstera leaves with dewdrops, organic soft morning sunlight, 8k photorealistic commercial background, empty center, no product',
			),
			'velvet_luxury'  => array(
				'label'  => '💎 Deep Crimson Velvet Spotlight',
				'prompt' => 'Ultra-luxury product display pedestal draped in rich deep burgundy crimson velvet fabric, dramatic focused spotlight from above, dark moody vignette, 8k luxury commercial advertising still, empty center, no product',
			),
		);
	}

	/**
	 * Generate a studio commercial backdrop for a social media campaign.
	 *
	 * @param string $preset Preset key from get_backdrop_presets().
	 * @param string $channel Target social channel for aspect ratio.
	 * @return array{ok:bool, url:string, provider:string, error?:string}
	 */
	public static function generate_backdrop( $preset = 'marble_gold', $channel = 'instagram' ) {
		$presets = self::get_backdrop_presets();
		$config  = $presets[ $preset ] ?? $presets['marble_gold'];

		$prompt = $config['prompt'];

		// Use Social AI's native Image Engine
		$engine = new VMSAI_Image_Engine();
		return $engine->generate(
			$prompt,
			[
				'channel'    => $channel,
				'aspect'     => 'instagram' === $channel ? '4:5' : '1:1',
				'style'      => 'commercial',
			]
		);
	}
}
