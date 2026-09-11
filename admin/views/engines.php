<?php
/**
 * Engine configuration.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_text   = vmsai()->text_engine();
$vmsai_image  = vmsai()->image_engine();
$vmsai_video  = vmsai()->video_engine();

$vmsai_chain  = (array) VMSAI_Settings::get( 'text_chain', array() );
$vmsai_ichain = (array) VMSAI_Settings::get( 'image_chain', array() );
$vmsai_vchain = (array) VMSAI_Settings::get( 'video_chain', array( 'aipuffer', 'omniroute', 'minimax', 'luma', 'pollinations', 'pexels' ) );

$vmsai_models = (array) VMSAI_Settings::get( 'text_model', array() );
$vmsai_imodel = (array) VMSAI_Settings::get( 'image_model', array() );
$vmsai_vmodel = (array) VMSAI_Settings::get( 'video_model', array() );
$vmsai_sync   = VMSAI_Model_Sync::state();
$vmsai_rendered_creds = array();

$vmsai_creds = array(
	'openai'       => array(
		'openai_key'       => array( __( 'API Key', 'vm-social-ai-pro' ), 'password', __( 'From platform.openai.com or your Puter.js bridge.', 'vm-social-ai-pro' ) ),
		'openai_image_url' => array( __( 'Custom Base URL', 'vm-social-ai-pro' ), 'url', __( 'Optional. Only if you use a proxy or bridge like Puter.', 'vm-social-ai-pro' ) ),
	),
	'aipuffer'     => array(
		'aipuffer_site'   => array( __( 'AI Puffer site URL', 'vm-social-ai-pro' ), 'url', __( 'Leave blank to use this site. Note: Many servers block "loopback" requests to themselves. If testing fails with a timeout, leave this blank.', 'vm-social-ai-pro' ) ),
		'aipuffer_key'    => array( __( 'REST API key', 'vm-social-ai-pro' ), 'password', __( 'From the AI Power settings, sent as a Bearer token.', 'vm-social-ai-pro' ) ),
		'aipuffer_bot_id' => array( __( 'Chatbot ID', 'vm-social-ai-pro' ), 'text', __( 'Optional. Routes through a bot so its knowledge base applies.', 'vm-social-ai-pro' ) ),
	),
	'anthropic'    => array( 'anthropic_key' => array( __( 'Anthropic API Key', 'vm-social-ai-pro' ), 'password', __( 'From console.anthropic.com. Unlocks Claude 3.5 Sonnet.', 'vm-social-ai-pro' ) ) ),
	'gemini'       => array( 'gemini_key' => array( __( 'API key', 'vm-social-ai-pro' ), 'password', __( 'From Google AI Studio.', 'vm-social-ai-pro' ) ) ),
	'openrouter'   => array( 'openrouter_key' => array( __( 'API key', 'vm-social-ai-pro' ), 'password', __( 'Free models in the list cost nothing per call.', 'vm-social-ai-pro' ) ) ),
	'nvidia'       => array(
		'nvidia_key' => array( __( 'API key', 'vm-social-ai-pro' ), 'password', __( 'From build.nvidia.com.', 'vm-social-ai-pro' ) ),
		'nvidia_url' => array( __( 'Custom endpoint', 'vm-social-ai-pro' ), 'url', __( 'Only if you host your own NIM container.', 'vm-social-ai-pro' ) ),
	),
	'ollama'       => array(
		'ollama_url'   => array( __( 'Server URL', 'vm-social-ai-pro' ), 'url', __( 'For example http://127.0.0.1:11434 on your VPS.', 'vm-social-ai-pro' ) ),
		'ollama_token' => array( __( 'Bearer token', 'vm-social-ai-pro' ), 'password', __( 'Only if you put it behind a proxy.', 'vm-social-ai-pro' ) ),
	),
	'pollinations' => array( 'pollinations_token' => array( __( 'Token', 'vm-social-ai-pro' ), 'password', __( 'Optional. Raises rate limits.', 'vm-social-ai-pro' ) ) ),
	'huggingface'  => array( 'hf_token' => array( __( 'Hugging Face Token', 'vm-social-ai-pro' ), 'password', __( 'Get a free token at huggingface.co. Supports FLUX for high-quality text-in-image.', 'vm-social-ai-pro' ) ) ),
	'cloudflare'   => array( 'cf_token' => array( __( 'Cloudflare API Token', 'vm-social-ai-pro' ), 'password', __( 'Needs "Workers AI" permissions. Reuses Account ID from Settings.', 'vm-social-ai-pro' ) ) ),
	'comfyui'      => array(
		'comfyui_url'      => array( __( 'Server URL', 'vm-social-ai-pro' ), 'url', __( 'For example http://127.0.0.1:8188.', 'vm-social-ai-pro' ) ),
		'comfyui_token'    => array( __( 'Bearer token', 'vm-social-ai-pro' ), 'password', '' ),
		'comfyui_workflow' => array( __( 'Workflow JSON (API format)', 'vm-social-ai-pro' ), 'textarea', __( 'Export from ComfyUI with "Save (API format)". Use {{prompt}}, {{negative}}, {{width}}, {{height}} and {{seed}} where the values should go.', 'vm-social-ai-pro' ) ),
	),
	'pexels'       => array( 'pexels_key' => array( __( 'API key', 'vm-social-ai-pro' ), 'password', __( 'Used as the last resort so a post never ships without an image.', 'vm-social-ai-pro' ) ) ),
	'omniroute'    => array(
		'omniroute_url' => array( __( 'OmniRoute URL', 'vm-social-ai-pro' ), 'url', __( 'Your self-hosted OmniRoute instance URL.', 'vm-social-ai-pro' ) ),
		'omniroute_key' => array( __( 'API Key', 'vm-social-ai-pro' ), 'password', __( 'The API key configured in your OmniRoute instance.', 'vm-social-ai-pro' ) ),
	),
	'minimax'      => array( 'minimax_key' => array( __( 'API Key', 'vm-social-ai-pro' ), 'password', __( 'From platform.minimaxi.com. High-fidelity cinematic video.', 'vm-social-ai-pro' ) ) ),
	'luma'         => array( 'luma_key'    => array( __( 'API Key', 'vm-social-ai-pro' ), 'password', __( 'From lumalabs.ai. High-end Dream Machine video generation.', 'vm-social-ai-pro' ) ) ),
	'heygen'       => array(
		'heygen_key'       => array( __( 'API Key', 'vm-social-ai-pro' ), 'password', __( 'From app.heygen.com. Unlocks talking AI Avatars.', 'vm-social-ai-pro' ) ),
		'heygen_avatar_id' => array( __( 'Avatar ID', 'vm-social-ai-pro' ), 'text', __( 'The ID of your Digital Twin or chosen avatar.', 'vm-social-ai-pro' ) ),
		'heygen_voice_id'  => array( __( 'Voice ID', 'vm-social-ai-pro' ), 'text', __( 'The ID of the voice to use.', 'vm-social-ai-pro' ) ),
	),
	'svd'          => array( 'svd_url'     => array( __( 'Local SVD API URL', 'vm-social-ai-pro' ), 'url', __( 'The endpoint for your self-hosted Stable Video Diffusion instance.', 'vm-social-ai-pro' ) ) ),
	'elevenlabs'   => array( 'elevenlabs_key' => array( __( 'ElevenLabs API Key', 'vm-social-ai-pro' ), 'password', __( 'Optional. Unlocks professional AI Voiceovers for Reels and Shorts.', 'vm-social-ai-pro' ) ) ),
	'tavily'       => array( 'tavily_key' => array( __( 'Tavily API Key', 'vm-social-ai-pro' ), 'password', __( 'Optional. Enables deep real-time research for trending industry news.', 'vm-social-ai-pro' ) ) ),
);

/**
 * Whether a provider's detail panel will actually contain anything.
 *
 * Credentials are rendered once and shared (AI Puffer's key is the same key
 * whether it is generating text, images or video), so a provider further
 * down the page can have no model list and no fields left to show. The panel
 * was rendered regardless, leaving an empty bordered box floating between
 * sections.
 *
 * @param string $slug       Provider slug.
 * @param array  $model_list Models available for it.
 * @return bool
 */
$vmsai_has_body = function ( $slug, $model_list ) use ( $vmsai_creds, &$vmsai_rendered_creds ) {
	if ( ! empty( $model_list ) ) {
		return true;
	}

	foreach ( array_keys( $vmsai_creds[ $slug ] ?? array() ) as $vmsai_cred_field ) {
		if ( ! in_array( $vmsai_cred_field, $vmsai_rendered_creds, true ) ) {
			return true;
		}
	}

	return false;
};
?>

<style>
	.vmsai-chain__test { margin-left: auto; padding: 3px 12px; font-size: 11px; }
	.vmsai-chain__result { padding: 0 16px; font-size: 12px; }
	.vmsai-chain__result:empty { display: none; }
	.vmsai-chain__result.is-ok { color: var(--green, #2ecc71); }
	.vmsai-chain__result.is-bad { color: var(--red, #e74c3c); }
</style>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'vmsai_save' ); ?>
	<input type="hidden" name="action" value="vmsai_save">
	<input type="hidden" name="section" value="engines">

	<section class="vmsai-panel">
		<div class="vmsai-panel__head">
			<div>
				<h2 class="vmsai-display"><?php esc_html_e( 'Engine one — words', 'vm-social-ai-pro' ); ?></h2>
				<p class="vmsai-lede"><?php esc_html_e( 'Requests run down this list in order. When one fails or rate limits, the next takes over without dropping the post. Drag to reorder.', 'vm-social-ai-pro' ); ?></p>
			</div>
			<div class="vmsai-panel__tools">
				<button type="button" class="vmsai-btn" data-vmsai-action="sync-models"><?php esc_html_e( 'Refresh model list', 'vm-social-ai-pro' ); ?></button>
				<button type="button" class="vmsai-btn" data-vmsai-action="test-text"><?php esc_html_e( 'Test the chain', 'vm-social-ai-pro' ); ?></button>
			</div>
		</div>

		<?php if ( ! empty( $vmsai_sync['ran_at'] ) ) : ?>
			<p class="vmsai-muted">
				<?php
				printf(
					/* translators: 1: model count, 2: human readable time difference */
					esc_html__( '%1$d models catalogued, refreshed %2$s ago.', 'vm-social-ai-pro' ),
					(int) $vmsai_sync['total'],
					esc_html( human_time_diff( (int) $vmsai_sync['ran_at'] ) )
				);
				?>
			</p>
		<?php endif; ?>

		<ol class="vmsai-chain" data-chain="text">
			<?php
			$vmsai_ordered = array_merge( $vmsai_chain, array_diff( array_keys( $vmsai_text->providers() ), $vmsai_chain ) );

			foreach ( $vmsai_ordered as $vmsai_slug ) :
				$vmsai_provider = $vmsai_text->provider( $vmsai_slug );

				if ( ! $vmsai_provider ) {
					continue;
				}

				$vmsai_on   = in_array( $vmsai_slug, $vmsai_chain, true );
				$vmsai_list = VMSAI_Model_Sync::models( $vmsai_slug, 'text' );
				?>
				<li class="vmsai-chain__item<?php echo $vmsai_provider->is_configured() ? ' is-ready' : ''; ?>" draggable="true">
					<div class="vmsai-chain__bar">
						<span class="vmsai-chain__grip" aria-hidden="true">⠿</span>
						<label class="vmsai-check">
							<input type="checkbox" name="text_chain[]" value="<?php echo esc_attr( $vmsai_slug ); ?>" <?php checked( $vmsai_on ); ?>>
							<span class="vmsai-chain__name"><?php echo esc_html( $vmsai_provider->label() ); ?></span>
						</label>
						<span class="vmsai-chain__state">
							<?php echo $vmsai_provider->is_configured() ? esc_html__( 'Configured', 'vm-social-ai-pro' ) : esc_html__( 'Needs a key', 'vm-social-ai-pro' ); ?>
						</span>
						<button type="button" class="vmsai-btn vmsai-btn--quiet vmsai-chain__test" data-vmsai-action="test-provider" data-engine="text" data-provider="<?php echo esc_attr( $vmsai_slug ); ?>" <?php disabled( ! $vmsai_provider->is_configured() ); ?>><?php esc_html_e( 'Test', 'vm-social-ai-pro' ); ?></button>
					</div>

					<div class="vmsai-chain__result" aria-live="polite"></div>

					<?php if ( $vmsai_has_body( $vmsai_slug, $vmsai_list ) ) : ?>
					<div class="vmsai-chain__body">
						<?php if ( $vmsai_list ) : ?>
							<div class="vmsai-field">
								<label for="vmsai-model-<?php echo esc_attr( $vmsai_slug ); ?>"><?php esc_html_e( 'Model', 'vm-social-ai-pro' ); ?></label>
								<select id="vmsai-model-<?php echo esc_attr( $vmsai_slug ); ?>" name="text_model[<?php echo esc_attr( $vmsai_slug ); ?>]">
									<option value=""><?php esc_html_e( 'Provider default', 'vm-social-ai-pro' ); ?></option>
									<?php foreach ( $vmsai_list as $vmsai_model ) : ?>
										<option value="<?php echo esc_attr( $vmsai_model['model_id'] ); ?>" <?php selected( $vmsai_models[ $vmsai_slug ] ?? '', $vmsai_model['model_id'] ); ?>>
											<?php
											echo esc_html( $vmsai_model['label'] );
											echo $vmsai_model['is_free'] ? ' · ' . esc_html__( 'free', 'vm-social-ai-pro' ) : '';
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php endif; ?>

						<?php
						foreach ( $vmsai_creds[ $vmsai_slug ] ?? array() as $vmsai_field => $vmsai_meta ) :
							if ( in_array( $vmsai_field, $vmsai_rendered_creds, true ) ) {
								continue;
							}
							$vmsai_rendered_creds[] = $vmsai_field;
						?>
							<div class="vmsai-field<?php echo 'textarea' === $vmsai_meta[1] ? ' vmsai-field--wide' : ''; ?>">
								<label for="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"><?php echo esc_html( $vmsai_meta[0] ); ?></label>
								<?php if ( 'textarea' === $vmsai_meta[1] ) : ?>
									<textarea id="vmsai-<?php echo esc_attr( $vmsai_field ); ?>" name="<?php echo esc_attr( $vmsai_field ); ?>" data-vmsai-cred="1" rows="5" class="vmsai-mono"><?php echo esc_textarea( VMSAI_Settings::credential( $vmsai_field ) ); ?></textarea>
								<?php else : ?>
									<input type="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? 'password' : 'text' ); ?>"
										id="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"
										name="<?php echo esc_attr( $vmsai_field ); ?>" data-vmsai-cred="1"
										autocomplete="off"
										value="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? VMSAI_Settings::mask( $vmsai_field ) : VMSAI_Settings::credential( $vmsai_field ) ); ?>">
								<?php endif; ?>
								<?php if ( $vmsai_meta[2] ) : ?>
									<p class="vmsai-hint"><?php echo esc_html( $vmsai_meta[2] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>

		<div id="vmsai-text-test" class="vmsai-testbox" aria-live="polite"></div>
	</section>

	<section class="vmsai-panel">
		<div class="vmsai-panel__head">
			<div>
				<h2 class="vmsai-display"><?php esc_html_e( 'Engine two — pictures', 'vm-social-ai-pro' ); ?></h2>
				<p class="vmsai-lede"><?php esc_html_e( 'Same idea: generation first, licensed stock at the end so a scheduled post never goes out bare.', 'vm-social-ai-pro' ); ?></p>
			</div>
			<div class="vmsai-panel__tools">
				<button type="button" class="vmsai-btn vmsai-btn--gold" data-vmsai-action="test-image"><?php esc_html_e( 'Test the Picture Chain', 'vm-social-ai-pro' ); ?></button>
			</div>
		</div>

		<ol class="vmsai-chain" data-chain="image">
			<?php
			$vmsai_iordered = array_merge( $vmsai_ichain, array_diff( array_keys( $vmsai_image->providers() ), $vmsai_ichain ) );

			foreach ( $vmsai_iordered as $vmsai_slug ) :
				$vmsai_provider = $vmsai_image->providers()[ $vmsai_slug ] ?? null;

				if ( ! $vmsai_provider ) {
					continue;
				}

				$vmsai_on   = in_array( $vmsai_slug, $vmsai_ichain, true );
				$vmsai_list = VMSAI_Model_Sync::models( $vmsai_slug, 'image' );
				?>
				<li class="vmsai-chain__item<?php echo $vmsai_provider->is_configured() ? ' is-ready' : ''; ?>" draggable="true">
					<div class="vmsai-chain__bar">
						<span class="vmsai-chain__grip" aria-hidden="true">⠿</span>
						<label class="vmsai-check">
							<input type="checkbox" name="image_chain[]" value="<?php echo esc_attr( $vmsai_slug ); ?>" <?php checked( $vmsai_on ); ?>>
							<span class="vmsai-chain__name"><?php echo esc_html( $vmsai_provider->label() ); ?></span>
						</label>
						<span class="vmsai-chain__state">
							<?php echo $vmsai_provider->is_configured() ? esc_html__( 'Configured', 'vm-social-ai-pro' ) : esc_html__( 'Needs setup', 'vm-social-ai-pro' ); ?>
						</span>
						<button type="button" class="vmsai-btn vmsai-btn--quiet vmsai-chain__test" data-vmsai-action="test-provider" data-engine="image" data-provider="<?php echo esc_attr( $vmsai_slug ); ?>" <?php disabled( ! $vmsai_provider->is_configured() ); ?>><?php esc_html_e( 'Test', 'vm-social-ai-pro' ); ?></button>
					</div>

					<div class="vmsai-chain__result" aria-live="polite"></div>

					<?php if ( $vmsai_has_body( $vmsai_slug, $vmsai_list ) ) : ?>
					<div class="vmsai-chain__body">
						<?php if ( $vmsai_list ) : ?>
							<div class="vmsai-field">
								<label for="vmsai-imodel-<?php echo esc_attr( $vmsai_slug ); ?>"><?php esc_html_e( 'Renderer', 'vm-social-ai-pro' ); ?></label>
								<select id="vmsai-imodel-<?php echo esc_attr( $vmsai_slug ); ?>" name="image_model[<?php echo esc_attr( $vmsai_slug ); ?>]">
									<option value=""><?php esc_html_e( 'Provider default', 'vm-social-ai-pro' ); ?></option>
									<?php foreach ( $vmsai_list as $vmsai_model ) : ?>
										<option value="<?php echo esc_attr( $vmsai_model['model_id'] ); ?>" <?php selected( $vmsai_imodel[ $vmsai_slug ] ?? '', $vmsai_model['model_id'] ); ?>>
											<?php
											echo esc_html( $vmsai_model['label'] );
											echo ! empty( $vmsai_model['is_free'] ) ? ' · ' . esc_html__( 'free', 'vm-social-ai-pro' ) : '';
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php endif; ?>

						<?php
						foreach ( $vmsai_creds[ $vmsai_slug ] ?? array() as $vmsai_field => $vmsai_meta ) :
							if ( in_array( $vmsai_field, $vmsai_rendered_creds, true ) ) {
								continue;
							}
							$vmsai_rendered_creds[] = $vmsai_field;
						?>
							<div class="vmsai-field<?php echo 'textarea' === $vmsai_meta[1] ? ' vmsai-field--wide' : ''; ?>">
								<label for="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"><?php echo esc_html( $vmsai_meta[0] ); ?></label>
								<?php if ( 'textarea' === $vmsai_meta[1] ) : ?>
									<textarea id="vmsai-<?php echo esc_attr( $vmsai_field ); ?>" name="<?php echo esc_attr( $vmsai_field ); ?>" data-vmsai-cred="1" rows="6" class="vmsai-mono"><?php echo esc_textarea( VMSAI_Settings::credential( $vmsai_field ) ); ?></textarea>
								<?php else : ?>
									<input type="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? 'password' : 'text' ); ?>"
										id="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"
										name="<?php echo esc_attr( $vmsai_field ); ?>" data-vmsai-cred="1"
										autocomplete="off"
										value="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? VMSAI_Settings::mask( $vmsai_field ) : VMSAI_Settings::credential( $vmsai_field ) ); ?>">
								<?php endif; ?>
								<?php if ( $vmsai_meta[2] ) : ?>
									<p class="vmsai-hint"><?php echo esc_html( $vmsai_meta[2] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>

		<div id="vmsai-image-test" class="vmsai-testbox" aria-live="polite"></div>
	</section>

	<section class="vmsai-panel">
		<div class="vmsai-panel__head">
			<div>
				<h2 class="vmsai-display"><?php esc_html_e( 'Engine three — moving pictures', 'vm-social-ai-pro' ); ?></h2>
				<p class="vmsai-lede"><?php esc_html_e( 'Configure the default directorial style and cinematic fallback for Reels and Shorts.', 'vm-social-ai-pro' ); ?></p>
			</div>
			<div class="vmsai-panel__tools">
				<button type="button" class="vmsai-btn vmsai-btn--gold" data-vmsai-action="test-video"><?php esc_html_e( 'Test Video Engine', 'vm-social-ai-pro' ); ?></button>
			</div>
		</div>

		<div class="vmsai-fields" style="margin-bottom: 30px;">
			<div class="vmsai-field">
				<label for="vmsai-video-default-template"><?php esc_html_e( 'Default Directorial Style', 'vm-social-ai-pro' ); ?></label>
				<select id="vmsai-video-default-template" name="video_default_template">
					<?php foreach ( VMSAI_Video_Engine::templates() as $slug => $tpl ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( VMSAI_Settings::get( 'video_default_template', 'cinematic_product' ), $slug ); ?>>
							<?php echo esc_html( $tpl['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="vmsai-field">
				<label for="vmsai-video-audio-default"><?php esc_html_e( 'Default Audio Vibe', 'vm-social-ai-pro' ); ?></label>
				<select id="vmsai-video-audio-default" name="video_audio_default">
					<option value="none" <?php selected( VMSAI_Settings::get( 'video_audio_default', 'none' ), 'none' ); ?>>Silent (No Audio)</option>
					<option value="lofi" <?php selected( VMSAI_Settings::get( 'video_audio_default' ), 'lofi' ); ?>>Lofi Street Beats</option>
					<option value="corporate" <?php selected( VMSAI_Settings::get( 'video_audio_default' ), 'corporate' ); ?>>Corporate Modern</option>
					<option value="tech" <?php selected( VMSAI_Settings::get( 'video_audio_default' ), 'tech' ); ?>>Tech Pulse</option>
					<option value="nature" <?php selected( VMSAI_Settings::get( 'video_audio_default' ), 'nature' ); ?>>Nature Ambience</option>
				</select>
			</div>
		</div>

		<ol class="vmsai-chain" data-chain="video">
			<?php
			$vmsai_vordered = array_merge( $vmsai_vchain, array_diff( array_keys( $vmsai_video->providers() ), $vmsai_vchain ) );

			foreach ( $vmsai_vordered as $vmsai_slug ) :
				$vmsai_label = $vmsai_video->providers()[ $vmsai_slug ] ?? $vmsai_slug;
				$vmsai_on    = in_array( $vmsai_slug, $vmsai_vchain, true );

				// Config check logic for video
				$vmsai_is_ready = true;
				if ( 'aipuffer' === $vmsai_slug ) {
					// Usable either with an explicit key or with an AIPKit
					// install on this same site, which needs no key at all.
					$vmsai_is_ready = VMSAI_Video_Engine::aipuffer_ready();
				} elseif ( in_array($vmsai_slug, array('minimax', 'luma', 'pexels', 'svd', 'cogvideox', 'omniroute')) ) {
					if ( 'omniroute' === $vmsai_slug ) {
						$vmsai_is_ready = ! empty( VMSAI_Settings::credential( 'omniroute_key' ) ) && ! empty( VMSAI_Settings::credential( 'omniroute_url' ) );
					} else {
						$key_field = ('svd' === $vmsai_slug) ? 'svd_url' : ( ('cogvideox' === $vmsai_slug) ? 'hf_token' : $vmsai_slug . '_key' );
						$vmsai_is_ready = ! empty( VMSAI_Settings::credential( $key_field ) );
					}
				}

				// Fall back to the provider's known models when nothing has
				// been synced, so a model is still selectable.
				$vmsai_vlist = VMSAI_Model_Sync::models( $vmsai_slug, 'video' );
				if ( ! $vmsai_vlist ) {
					$vmsai_vlist = $vmsai_video->list_models( $vmsai_slug );
				}
				?>
				<li class="vmsai-chain__item<?php echo $vmsai_is_ready ? ' is-ready' : ''; ?>" draggable="true">
					<div class="vmsai-chain__bar">
						<span class="vmsai-chain__grip" aria-hidden="true">⠿</span>
						<label class="vmsai-check">
							<input type="checkbox" name="video_chain[]" value="<?php echo esc_attr( $vmsai_slug ); ?>" <?php checked( $vmsai_on ); ?>>
							<span class="vmsai-chain__name"><?php echo esc_html( $vmsai_label ); ?></span>
						</label>
						<span class="vmsai-chain__state">
							<?php echo $vmsai_is_ready ? esc_html__( 'Configured', 'vm-social-ai-pro' ) : esc_html__( 'Needs setup', 'vm-social-ai-pro' ); ?>
						</span>
						<button type="button" class="vmsai-btn vmsai-btn--quiet vmsai-chain__test" data-vmsai-action="test-provider" data-engine="video" data-provider="<?php echo esc_attr( $vmsai_slug ); ?>" <?php disabled( ! $vmsai_is_ready ); ?>><?php esc_html_e( 'Test', 'vm-social-ai-pro' ); ?></button>
					</div>

					<div class="vmsai-chain__result" aria-live="polite"></div>

					<?php if ( $vmsai_has_body( $vmsai_slug, $vmsai_vlist ) ) : ?>
					<div class="vmsai-chain__body">
						<?php if ( $vmsai_vlist ) : ?>
							<div class="vmsai-field">
								<label for="vmsai-vmodel-<?php echo esc_attr( $vmsai_slug ); ?>"><?php esc_html_e( 'Model', 'vm-social-ai-pro' ); ?></label>
								<select id="vmsai-vmodel-<?php echo esc_attr( $vmsai_slug ); ?>" name="video_model[<?php echo esc_attr( $vmsai_slug ); ?>]">
									<option value=""><?php esc_html_e( 'Provider default', 'vm-social-ai-pro' ); ?></option>
									<?php foreach ( $vmsai_vlist as $vmsai_model ) :
										$v_id = $vmsai_model['model_id'] ?? $vmsai_model['id'];
									?>
										<option value="<?php echo esc_attr( $v_id ); ?>" <?php selected( $vmsai_vmodel[ $vmsai_slug ] ?? '', $v_id ); ?>>
											<?php
											echo esc_html( $vmsai_model['label'] );
											echo ! empty( $vmsai_model['is_free'] ) ? ' · ' . esc_html__( 'free', 'vm-social-ai-pro' ) : '';
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php endif; ?>

						<?php
						foreach ( $vmsai_creds[ $vmsai_slug ] ?? array() as $vmsai_field => $vmsai_meta ) :
							if ( in_array( $vmsai_field, $vmsai_rendered_creds, true ) ) continue;
							$vmsai_rendered_creds[] = $vmsai_field;
						?>
							<div class="vmsai-field">
								<label for="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"><?php echo esc_html( $vmsai_meta[0] ); ?></label>
								<input type="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? 'password' : 'text' ); ?>"
									id="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"
									name="<?php echo esc_attr( $vmsai_field ); ?>" data-vmsai-cred="1"
									autocomplete="off"
									value="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? VMSAI_Settings::mask( $vmsai_field ) : VMSAI_Settings::credential( $vmsai_field ) ); ?>">
								<?php if ( $vmsai_meta[2] ) : ?>
									<p class="vmsai-hint"><?php echo esc_html( $vmsai_meta[2] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>

		<div id="vmsai-video-test" class="vmsai-testbox" aria-live="polite"></div>
	</section>

	<section class="vmsai-panel">
		<div class="vmsai-panel__head">
			<div>
				<h2 class="vmsai-display"><?php esc_html_e( 'Engine four — auxiliary intelligence', 'vm-social-ai-pro' ); ?></h2>
				<p class="vmsai-lede"><?php esc_html_e( 'Voiceover and deep research engines to enhance your automated assets.', 'vm-social-ai-pro' ); ?></p>
			</div>
		</div>

		<ol class="vmsai-chain">
			<?php foreach ( array( 'elevenlabs', 'tavily' ) as $vmsai_slug ) :
				$vmsai_label = ( 'elevenlabs' === $vmsai_slug ) ? 'ElevenLabs (Voiceover)' : 'Tavily (Deep Research)';
				$vmsai_is_ready = ! empty( VMSAI_Settings::credential( $vmsai_slug . '_key' ) );
			?>
				<li class="vmsai-chain__item<?php echo $vmsai_is_ready ? ' is-ready' : ''; ?>">
					<div class="vmsai-chain__bar">
						<label class="vmsai-check" style="margin-left: 10px;">
							<span class="vmsai-chain__name"><?php echo esc_html( $vmsai_label ); ?></span>
						</label>
						<span class="vmsai-chain__state">
							<?php echo $vmsai_is_ready ? esc_html__( 'Configured', 'vm-social-ai-pro' ) : esc_html__( 'Needs a key', 'vm-social-ai-pro' ); ?>
						</span>
					</div>
					<?php if ( $vmsai_has_body( $vmsai_slug, array() ) ) : ?>
					<div class="vmsai-chain__body">
						<?php
						foreach ( $vmsai_creds[ $vmsai_slug ] ?? array() as $vmsai_field => $vmsai_meta ) :
							if ( in_array( $vmsai_field, $vmsai_rendered_creds, true ) ) continue;
							$vmsai_rendered_creds[] = $vmsai_field;
						?>
							<div class="vmsai-field">
								<label for="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"><?php echo esc_html( $vmsai_meta[0] ); ?></label>
								<input type="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? 'password' : 'text' ); ?>"
									id="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"
									name="<?php echo esc_attr( $vmsai_field ); ?>" data-vmsai-cred="1"
									autocomplete="off"
									value="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? VMSAI_Settings::mask( $vmsai_field ) : VMSAI_Settings::credential( $vmsai_field ) ); ?>">
								<?php if ( $vmsai_meta[2] ) : ?>
									<p class="vmsai-hint"><?php echo esc_html( $vmsai_meta[2] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>

	<section class="vmsai-panel">
		<h2 class="vmsai-panel__title"><?php esc_html_e( 'Request behaviour', 'vm-social-ai-pro' ); ?></h2>
		<div class="vmsai-fields">
			<div class="vmsai-field">
				<label for="vmsai-timeout"><?php esc_html_e( 'Timeout per request (seconds)', 'vm-social-ai-pro' ); ?></label>
				<input type="number" id="vmsai-timeout" name="request_timeout" min="15" max="600" value="<?php echo esc_attr( VMSAI_Settings::get( 'request_timeout', 90 ) ); ?>">
			</div>
			<div class="vmsai-field">
				<label for="vmsai-attempts"><?php esc_html_e( 'Publish attempts before giving up', 'vm-social-ai-pro' ); ?></label>
				<input type="number" id="vmsai-attempts" name="max_attempts" min="1" max="10" value="<?php echo esc_attr( VMSAI_Settings::get( 'max_attempts', 3 ) ); ?>">
			</div>
		</div>

		<p><button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Save engine setup', 'vm-social-ai-pro' ); ?></button></p>
	</section>
</form>
