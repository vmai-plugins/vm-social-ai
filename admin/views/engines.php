<?php
/**
 * View: AI Engines & Neural Pipelines (Redesigned Unified Architecture).
 *
 * Provides a clean OmniRoute All-In-One Powerhouse mode as the recommended default,
 * alongside advanced multi-provider failover chains and the live model catalogue.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

// Engine instances
$vmsai_text  = vmsai()->text_engine();
$vmsai_image = vmsai()->image_engine();
$vmsai_video = vmsai()->video_engine();

// Saved chains
$vmsai_chain  = (array) VMSAI_Settings::get( 'text_chain', array( 'omniroute', 'aipuffer', 'gemini' ) );
$vmsai_ichain = (array) VMSAI_Settings::get( 'image_chain', array( 'omniroute', 'pollinations', 'gemini' ) );
$vmsai_vchain = (array) VMSAI_Settings::get( 'video_chain', array( 'omniroute', 'aipuffer', 'minimax', 'pollinations', 'pexels' ) );

// Saved models
$vmsai_model  = (array) VMSAI_Settings::get( 'text_model', array() );
$vmsai_imodel = (array) VMSAI_Settings::get( 'image_model', array() );
$vmsai_vmodel = (array) VMSAI_Settings::get( 'video_model', array() );

// Engine preset
$vmsai_preset = VMSAI_Settings::get( 'engine_preset', 'omniroute' );

// Sync metadata
$vmsai_sync = VMSAI_Model_Sync::state();

// OmniRoute credentials
$omni_url = VMSAI_Settings::credential( 'omniroute_url', 'https://ai.vmstudio.digital/v1' );
$omni_key = VMSAI_Settings::credential( 'omniroute_key' );
$omni_configured = ! empty( $omni_url ) && ! empty( $omni_key );

// OmniRoute models
$omni_text_models = VMSAI_Model_Sync::models( 'omniroute', 'text' );
if ( empty( $omni_text_models ) ) {
	$omni_text_models = array(
		array( 'id' => 'auto/best-coding', 'label' => 'Auto Best Coding (Grok 4.6 backend) · Recommended', 'is_free' => 1 ),
		array( 'id' => 'auto/best-chat', 'label' => 'Auto Best Chat', 'is_free' => 1 ),
		array( 'id' => 'auto/best-free', 'label' => 'Auto Best Free', 'is_free' => 1 ),
		array( 'id' => 'gemini-2.5-flash', 'label' => 'Google Gemini 2.5 Flash', 'is_free' => 1 ),
		array( 'id' => 'auto/deepseek-v3', 'label' => 'DeepSeek V3', 'is_free' => 1 ),
	);
}

$omni_image_models = VMSAI_Model_Sync::models( 'omniroute', 'image' );
if ( empty( $omni_image_models ) ) {
	$omni_image_models = array(
		array( 'id' => 'aihorde/stable_diffusion', 'label' => 'AI Horde Stable Diffusion · 100% Free (Recommended)', 'is_free' => 1 ),
		array( 'id' => 'aihorde/Flux.1-Schnell fp8 (Compact)', 'label' => 'AI Horde FLUX.1 Schnell · Free', 'is_free' => 1 ),
		array( 'id' => 'aihorde/AlbedoBase XL (SDXL)', 'label' => 'AI Horde AlbedoBase XL · Free', 'is_free' => 1 ),
		array( 'id' => 'aihorde/SDXL 1.0', 'label' => 'AI Horde SDXL 1.0 · Free', 'is_free' => 1 ),
	);
}

$omni_video_models = VMSAI_Model_Sync::models( 'omniroute', 'video' );
if ( empty( $omni_video_models ) ) {
	$omni_video_models = array(
		array( 'id' => 'veo-free/veo', 'label' => 'Google Veo Free (Recommended)', 'is_free' => 1 ),
		array( 'id' => 'veoaifree-web/veo', 'label' => 'Veo AI Free Web', 'is_free' => 1 ),
		array( 'id' => 'veo-free/seedance', 'label' => 'ByteDance Seedance Free', 'is_free' => 1 ),
		array( 'id' => 'auto/inkling', 'label' => 'Auto Inkling Video', 'is_free' => 1 ),
	);
}

// Full catalogue for tab 3
$all_synced_models = VMSAI_Model_Sync::all();

// Provider credentials list for advanced tab
$vmsai_rendered_creds = array( 'omniroute_url', 'omniroute_key' );
$vmsai_creds = array(
	'openai'       => array(
		'openai_key'       => array( __( 'OpenAI API Key', 'vm-social-ai-pro' ), 'password', __( 'From platform.openai.com. Powers DALL-E 3.', 'vm-social-ai-pro' ) ),
		'openai_image_url' => array( __( 'Custom Endpoint', 'vm-social-ai-pro' ), 'url', __( 'Optional reverse proxy or Azure OpenAI URL.', 'vm-social-ai-pro' ) ),
	),
	'aipuffer'     => array(
		'aipuffer_site'   => array( __( 'AI Puffer site URL', 'vm-social-ai-pro' ), 'url', __( 'Leave blank to use this site.', 'vm-social-ai-pro' ) ),
		'aipuffer_key'    => array( __( 'REST API key', 'vm-social-ai-pro' ), 'password', __( 'From AI Power settings.', 'vm-social-ai-pro' ) ),
		'aipuffer_bot_id' => array( __( 'Chatbot ID', 'vm-social-ai-pro' ), 'text', __( 'Optional knowledge base bot ID.', 'vm-social-ai-pro' ) ),
	),
	'anthropic'    => array( 'anthropic_key' => array( __( 'Anthropic API Key', 'vm-social-ai-pro' ), 'password', __( 'From console.anthropic.com. Unlocks Claude 3.5.', 'vm-social-ai-pro' ) ) ),
	'gemini'       => array( 'gemini_key' => array( __( 'API key', 'vm-social-ai-pro' ), 'password', __( 'From Google AI Studio.', 'vm-social-ai-pro' ) ) ),
	'openrouter'   => array( 'openrouter_key' => array( __( 'API key', 'vm-social-ai-pro' ), 'password', __( 'Free models cost nothing per call.', 'vm-social-ai-pro' ) ) ),
	'nvidia'       => array(
		'nvidia_key' => array( __( 'API key', 'vm-social-ai-pro' ), 'password', __( 'From build.nvidia.com.', 'vm-social-ai-pro' ) ),
		'nvidia_url' => array( __( 'Custom endpoint', 'vm-social-ai-pro' ), 'url', __( 'Self-hosted NIM container.', 'vm-social-ai-pro' ) ),
	),
	'ollama'       => array(
		'ollama_url'   => array( __( 'Server URL', 'vm-social-ai-pro' ), 'url', __( 'e.g. http://127.0.0.1:11434 on your VPS.', 'vm-social-ai-pro' ) ),
		'ollama_token' => array( __( 'Bearer token', 'vm-social-ai-pro' ), 'password', '' ),
	),
	'pollinations' => array( 'pollinations_token' => array( __( 'Token', 'vm-social-ai-pro' ), 'password', __( 'Optional. Keyless by default.', 'vm-social-ai-pro' ) ) ),
	'huggingface'  => array( 'hf_token' => array( __( 'Hugging Face Token', 'vm-social-ai-pro' ), 'password', __( 'From huggingface.co.', 'vm-social-ai-pro' ) ) ),
	'cloudflare'   => array( 'cf_token' => array( __( 'Cloudflare API Token', 'vm-social-ai-pro' ), 'password', __( 'Needs Workers AI permissions.', 'vm-social-ai-pro' ) ) ),
	'comfyui'      => array(
		'comfyui_url'      => array( __( 'Server URL', 'vm-social-ai-pro' ), 'url', __( 'e.g. http://127.0.0.1:8188.', 'vm-social-ai-pro' ) ),
		'comfyui_token'    => array( __( 'Bearer token', 'vm-social-ai-pro' ), 'password', '' ),
		'comfyui_workflow' => array( __( 'Workflow JSON', 'vm-social-ai-pro' ), 'textarea', '' ),
	),
	'pexels'       => array( 'pexels_key' => array( __( 'API key', 'vm-social-ai-pro' ), 'password', __( 'Stock fallback.', 'vm-social-ai-pro' ) ) ),
	'minimax'      => array( 'minimax_key' => array( __( 'API Key', 'vm-social-ai-pro' ), 'password', __( 'From platform.minimaxi.com.', 'vm-social-ai-pro' ) ) ),
	'luma'         => array( 'luma_key'    => array( __( 'API Key', 'vm-social-ai-pro' ), 'password', __( 'From lumalabs.ai.', 'vm-social-ai-pro' ) ) ),
	'heygen'       => array(
		'heygen_key'       => array( __( 'API Key', 'vm-social-ai-pro' ), 'password', __( 'From app.heygen.com.', 'vm-social-ai-pro' ) ),
		'heygen_avatar_id' => array( __( 'Avatar ID', 'vm-social-ai-pro' ), 'text', '' ),
		'heygen_voice_id'  => array( __( 'Voice ID', 'vm-social-ai-pro' ), 'text', '' ),
	),
	'svd'          => array( 'svd_url'     => array( __( 'Local SVD API URL', 'vm-social-ai-pro' ), 'url', '' ) ),
	'elevenlabs'   => array( 'elevenlabs_key' => array( __( 'ElevenLabs API Key', 'vm-social-ai-pro' ), 'password', __( 'AI Voiceovers for Reels/Shorts.', 'vm-social-ai-pro' ) ) ),
	'tavily'       => array( 'tavily_key' => array( __( 'Tavily API Key', 'vm-social-ai-pro' ), 'password', __( 'Deep news research for RAG.', 'vm-social-ai-pro' ) ) ),
);
?>

<style>
	/* Redesigned AI Engine Layout */
	.vmsai-engine-nav { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--hairline); padding-bottom: 12px; }
	.vmsai-engine-tab {
		background: var(--raised); color: var(--muted); border: 1px solid var(--hairline);
		padding: 10px 20px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer;
		display: inline-flex; align-items: center; gap: 8px; transition: all 160ms ease;
	}
	.vmsai-engine-tab:hover { color: var(--parchment); border-color: var(--gold); }
	.vmsai-engine-tab.is-active {
		background: var(--gold); color: var(--ink); border-color: var(--gold);
		box-shadow: 0 4px 14px rgba(201, 162, 39, 0.25);
	}

	.vmsai-omni-hero {
		background: linear-gradient(145deg, var(--panel) 0%, var(--raised) 100%);
		border: 1px solid var(--gold); border-radius: 8px; padding: 24px; margin-bottom: 24px;
		box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative;
	}
	.vmsai-omni-hero__badge {
		position: absolute; top: 20px; right: 20px; font-size: 11px; font-weight: 700;
		padding: 4px 12px; border-radius: 20px; letter-spacing: 0.05em; text-transform: uppercase;
	}
	.vmsai-badge--live { background: rgba(111, 168, 138, 0.15); color: var(--green); border: 1px solid var(--green); }
	.vmsai-badge--unconf { background: rgba(196, 96, 79, 0.15); color: var(--red); border: 1px solid var(--red); }

	.vmsai-omni-creds {
		display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 18px;
		background: var(--ink); padding: 18px; border-radius: 6px; border: 1px solid var(--hairline);
	}
	@media (max-width: 782px) { .vmsai-omni-creds { grid-template-columns: 1fr; } }

	.vmsai-omni-grid {
		display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 24px;
	}
	@media (max-width: 1024px) { .vmsai-omni-grid { grid-template-columns: 1fr; } }

	.vmsai-modality-card {
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 8px;
		padding: 20px; display: flex; flex-direction: column; justify-content: space-between;
		transition: transform 180ms ease, border-color 180ms ease;
	}
	.vmsai-modality-card:hover { border-color: var(--gold-soft); transform: translateY(-2px); }
	.vmsai-modality-card__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
	.vmsai-modality-card__title { font-size: 15px; font-weight: 700; color: var(--parchment); margin: 0; display: flex; align-items: center; gap: 8px; }
	.vmsai-modality-card__pill { font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 4px; background: rgba(201, 162, 39, 0.15); color: var(--gold); }
	.vmsai-modality-card__desc { font-size: 12px; color: var(--muted); margin: 0 0 16px; line-height: 1.45; min-height: 34px; }

	.vmsai-modality-card__select select {
		width: 100%; background: var(--ink); border: 1px solid var(--hairline); color: var(--parchment);
		padding: 8px 12px; border-radius: 4px; font-size: 12px; margin-bottom: 12px;
	}
	.vmsai-modality-card__select select:focus { border-color: var(--gold); outline: none; }

	.vmsai-modality-card__features { list-style: none; margin: 0 0 16px; padding: 0; font-size: 11px; color: var(--muted); }
	.vmsai-modality-card__features li { margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
	.vmsai-modality-card__features li span { color: var(--green); font-weight: bold; }

	.vmsai-modality-card__actions { margin-top: auto; padding-top: 12px; border-top: 1px solid var(--hairline); display: flex; align-items: center; justify-content: space-between; }
	.vmsai-modality-card__result { margin-top: 10px; font-size: 11px; line-height: 1.4; }
	.vmsai-modality-card__result:empty { display: none; }
	.vmsai-modality-card__result.is-ok { color: var(--green); }
	.vmsai-modality-card__result.is-bad { color: var(--red); }

	/* Catalogue Table */
	.vmsai-cat-filter { display: flex; gap: 10px; margin-bottom: 16px; }
	.vmsai-cat-filter input { max-width: 320px; }
	.vmsai-cat-table { width: 100%; border-collapse: collapse; font-size: 12px; background: var(--panel); border: 1px solid var(--hairline); border-radius: 6px; overflow: hidden; }
	.vmsai-cat-table th, .vmsai-cat-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--hairline); }
	.vmsai-cat-table th { background: var(--raised); color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em; }
	.vmsai-cat-table tr:hover td { background: rgba(255,255,255,0.02); }

	.vmsai-tab-content { display: none; }
	.vmsai-tab-content.is-active { display: block; }
</style>

<div class="vmsai-masthead" style="margin-bottom: 20px;">
	<div class="vmsai-masthead__id">
		<span class="vmsai-eyebrow"><?php esc_html_e( 'Neural Backbone', 'vm-social-ai-pro' ); ?></span>
		<h1 class="vmsai-wordmark"><?php esc_html_e( 'AI Engines & Generation Hub', 'vm-social-ai-pro' ); ?></h1>
	</div>
	<div class="vmsai-masthead__status">
		<dl>
			<dt><?php esc_html_e( 'Live Models Synced', 'vm-social-ai-pro' ); ?></dt>
			<dd><span class="vmsai-status vmsai-status--live"><?php echo number_format( (int) ( $vmsai_sync['total'] ?? 0 ) ); ?> models</span></dd>
		</dl>
		<dl>
			<dt><?php esc_html_e( 'OmniRoute Gateway', 'vm-social-ai-pro' ); ?></dt>
			<dd><span class="vmsai-status <?php echo $omni_configured ? 'vmsai-status--live' : 'vmsai-status--down'; ?>"><?php echo $omni_configured ? esc_html__( 'Connected', 'vm-social-ai-pro' ) : esc_html__( 'Not configured', 'vm-social-ai-pro' ); ?></span></dd>
		</dl>
	</div>
</div>

<nav class="vmsai-engine-nav" aria-label="<?php esc_attr_e( 'Engine Configuration Views', 'vm-social-ai-pro' ); ?>">
	<button type="button" class="vmsai-engine-tab is-active" data-vmsai-tab="tab-omniroute">
		<span>🌟</span> <?php esc_html_e( 'OmniRoute All-In-One Hub (Recommended)', 'vm-social-ai-pro' ); ?>
	</button>
	<button type="button" class="vmsai-engine-tab" data-vmsai-tab="tab-chains">
		<span>⚙️</span> <?php esc_html_e( 'Custom Multi-Provider Chains (Advanced)', 'vm-social-ai-pro' ); ?>
	</button>
	<button type="button" class="vmsai-engine-tab" data-vmsai-tab="tab-catalogue">
		<span>📊</span> <?php esc_html_e( 'Model Catalogue', 'vm-social-ai-pro' ); ?> (<?php echo number_format( (int) ( $vmsai_sync['total'] ?? 0 ) ); ?>)
	</button>
</nav>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="vmsai-engines-form">
	<?php wp_nonce_field( 'vmsai_save' ); ?>
	<input type="hidden" name="action" value="vmsai_save">
	<input type="hidden" name="section" value="engines">
	<input type="hidden" name="engine_preset" id="vmsai-engine-preset" value="<?php echo esc_attr( $vmsai_preset ); ?>">

	<!-- ========================================== -->
	<!-- TAB 1: OMNIROUTE ALL-IN-ONE HUB             -->
	<!-- ========================================== -->
	<div id="tab-omniroute" class="vmsai-tab-content is-active">
		<div class="vmsai-omni-hero">
			<span class="vmsai-omni-hero__badge <?php echo $omni_configured ? 'vmsai-badge--live' : 'vmsai-badge--unconf'; ?>">
				<?php echo $omni_configured ? '● ' . esc_html__( 'OmniRoute Active', 'vm-social-ai-pro' ) : '○ ' . esc_html__( 'Setup Required', 'vm-social-ai-pro' ); ?>
			</span>

			<h2 class="vmsai-display" style="margin-top:0; font-size:22px;">
				<?php esc_html_e( 'OmniRoute Unified AI Powerhouse', 'vm-social-ai-pro' ); ?>
			</h2>
			<p class="vmsai-lede" style="max-width: 780px;">
				<?php esc_html_e( 'Your self-hosted OmniRoute instance provides high-speed, zero-cost generation across all three core modalities: text copy, illustrations, and motion video without needing individual third-party API keys.', 'vm-social-ai-pro' ); ?>
			</p>

			<div class="vmsai-omni-creds">
				<div class="vmsai-field">
					<label for="vmsai-omni-url"><strong><?php esc_html_e( 'OmniRoute Gateway URL', 'vm-social-ai-pro' ); ?></strong></label>
					<input type="url" id="vmsai-omni-url" name="omniroute_url" data-vmsai-cred="1"
						placeholder="https://ai.vmstudio.digital/v1"
						value="<?php echo esc_attr( $omni_url ); ?>" style="width: 100%;">
					<p class="vmsai-hint"><?php esc_html_e( 'Base endpoint for your self-hosted OmniRoute instance.', 'vm-social-ai-pro' ); ?></p>
				</div>
				<div class="vmsai-field">
					<label for="vmsai-omni-key"><strong><?php esc_html_e( 'OmniRoute Bearer API Key', 'vm-social-ai-pro' ); ?></strong></label>
					<input type="password" id="vmsai-omni-key" name="omniroute_key" data-vmsai-cred="1"
						placeholder="sk-..."
						value="<?php echo esc_attr( VMSAI_Settings::mask( 'omniroute_key' ) ); ?>" style="width: 100%;">
					<p class="vmsai-hint"><?php esc_html_e( 'Encrypted with AES-256-GCM using your site salts.', 'vm-social-ai-pro' ); ?></p>
				</div>
			</div>

			<!-- 3-in-1 Modality Matrix -->
			<div class="vmsai-omni-grid">
				<!-- 1. Text -->
				<div class="vmsai-modality-card">
					<div>
						<div class="vmsai-modality-card__head">
							<h3 class="vmsai-modality-card__title"><span>✍️</span> <?php esc_html_e( 'Text Engine', 'vm-social-ai-pro' ); ?></h3>
							<span class="vmsai-modality-card__pill"><?php esc_html_e( '100% FREE', 'vm-social-ai-pro' ); ?></span>
						</div>
						<p class="vmsai-modality-card__desc">
							<?php esc_html_e( 'Drafts viral hooks, captions, hashtags, and carousel slides.', 'vm-social-ai-pro' ); ?>
						</p>

						<div class="vmsai-modality-card__select">
							<label for="vmsai-omni-text-model" style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--muted); display:block; margin-bottom:4px;">
								<?php esc_html_e( 'Active Text Model', 'vm-social-ai-pro' ); ?>
							</label>
							<select id="vmsai-omni-text-model" name="text_model[omniroute]">
								<?php foreach ( $omni_text_models as $m ) :
									$mid = $m['model_id'] ?? $m['id'];
									$sel = ( ($vmsai_model['omniroute'] ?? 'auto/best-coding') === $mid );
								?>
									<option value="<?php echo esc_attr( $mid ); ?>" <?php selected( $sel ); ?>>
										<?php echo esc_html( $m['label'] ?? $mid ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<ul class="vmsai-modality-card__features">
							<li><span>✓</span> <?php esc_html_e( 'Grok 4.6 / Gemini 2.5 Flash backend', 'vm-social-ai-pro' ); ?></li>
							<li><span>✓</span> <?php esc_html_e( 'Non-streaming payload (zero timeouts)', 'vm-social-ai-pro' ); ?></li>
							<li><span>✓</span> <?php esc_html_e( 'Average response time: ~4 seconds', 'vm-social-ai-pro' ); ?></li>
						</ul>
					</div>

					<div>
						<div class="vmsai-modality-card__actions">
							<button type="button" class="vmsai-btn vmsai-btn--quiet" data-vmsai-action="test-provider" data-engine="text" data-provider="omniroute">
								⚡ <?php esc_html_e( 'Test Text Engine', 'vm-social-ai-pro' ); ?>
							</button>
						</div>
						<div class="vmsai-modality-card__result vmsai-chain__result" id="omni-test-result-text" aria-live="polite"></div>
					</div>
				</div>

				<!-- 2. Image -->
				<div class="vmsai-modality-card">
					<div>
						<div class="vmsai-modality-card__head">
							<h3 class="vmsai-modality-card__title"><span>🎨</span> <?php esc_html_e( 'Image Engine', 'vm-social-ai-pro' ); ?></h3>
							<span class="vmsai-modality-card__pill"><?php esc_html_e( '100% FREE', 'vm-social-ai-pro' ); ?></span>
						</div>
						<p class="vmsai-modality-card__desc">
							<?php esc_html_e( 'Generates illustrations, infographics, and ad creative.', 'vm-social-ai-pro' ); ?>
						</p>

						<div class="vmsai-modality-card__select">
							<label for="vmsai-omni-img-model" style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--muted); display:block; margin-bottom:4px;">
								<?php esc_html_e( 'Active Image Model', 'vm-social-ai-pro' ); ?>
							</label>
							<select id="vmsai-omni-img-model" name="image_model[omniroute]">
								<?php foreach ( $omni_image_models as $m ) :
									$mid = $m['model_id'] ?? $m['id'];
									$sel = ( ($vmsai_imodel['omniroute'] ?? 'aihorde/stable_diffusion') === $mid );
								?>
									<option value="<?php echo esc_attr( $mid ); ?>" <?php selected( $sel ); ?>>
										<?php echo esc_html( $m['label'] ?? $mid ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<ul class="vmsai-modality-card__features">
							<li><span>✓</span> <?php esc_html_e( 'AI Horde crowdsourced cluster (Free)', 'vm-social-ai-pro' ); ?></li>
							<li><span>✓</span> <?php esc_html_e( 'Magic-byte WebP & auto media sideloading', 'vm-social-ai-pro' ); ?></li>
							<li><span>✓</span> <?php esc_html_e( 'Pollinations AI keyless fallback floor', 'vm-social-ai-pro' ); ?></li>
						</ul>
					</div>

					<div>
						<div class="vmsai-modality-card__actions">
							<button type="button" class="vmsai-btn vmsai-btn--quiet" data-vmsai-action="test-provider" data-engine="image" data-provider="omniroute">
								⚡ <?php esc_html_e( 'Test Image Engine', 'vm-social-ai-pro' ); ?>
							</button>
						</div>
						<div class="vmsai-modality-card__result vmsai-chain__result" id="omni-test-result-image" aria-live="polite"></div>
					</div>
				</div>

				<!-- 3. Video -->
				<div class="vmsai-modality-card">
					<div>
						<div class="vmsai-modality-card__head">
							<h3 class="vmsai-modality-card__title"><span>🎬</span> <?php esc_html_e( 'Video Engine', 'vm-social-ai-pro' ); ?></h3>
							<span class="vmsai-modality-card__pill"><?php esc_html_e( '100% FREE', 'vm-social-ai-pro' ); ?></span>
						</div>
						<p class="vmsai-modality-card__desc">
							<?php esc_html_e( 'Produces short-form reels, TikToks, and motion clips.', 'vm-social-ai-pro' ); ?>
						</p>

						<div class="vmsai-modality-card__select">
							<label for="vmsai-omni-vid-model" style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--muted); display:block; margin-bottom:4px;">
								<?php esc_html_e( 'Active Video Model', 'vm-social-ai-pro' ); ?>
							</label>
							<select id="vmsai-omni-vid-model" name="video_model[omniroute]">
								<?php foreach ( $omni_video_models as $m ) :
									$mid = $m['model_id'] ?? $m['id'];
									$sel = ( ($vmsai_vmodel['omniroute'] ?? 'veo-free/veo') === $mid );
								?>
									<option value="<?php echo esc_attr( $mid ); ?>" <?php selected( $sel ); ?>>
										<?php echo esc_html( $m['label'] ?? $mid ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<ul class="vmsai-modality-card__features">
							<li><span>✓</span> <?php esc_html_e( 'Google Veo Free & Seedance models', 'vm-social-ai-pro' ); ?></li>
							<li><span>✓</span> <?php esc_html_e( '9:16 vertical social format output', 'vm-social-ai-pro' ); ?></li>
							<li><span>✓</span> <?php esc_html_e( 'Direct MP4 result URL streaming', 'vm-social-ai-pro' ); ?></li>
						</ul>
					</div>

					<div>
						<div class="vmsai-modality-card__actions">
							<button type="button" class="vmsai-btn vmsai-btn--quiet" data-vmsai-action="test-provider" data-engine="video" data-provider="omniroute">
								⚡ <?php esc_html_e( 'Test Video Engine', 'vm-social-ai-pro' ); ?>
							</button>
						</div>
						<div class="vmsai-modality-card__result vmsai-chain__result" id="omni-test-result-video" aria-live="polite"></div>
					</div>
				</div>
			</div>

			<div style="display: flex; gap: 12px; margin-top: 24px; align-items: center; justify-content: flex-end;">
				<button type="button" class="vmsai-btn" data-vmsai-action="sync-models">
					🔄 <?php esc_html_e( 'Refresh Live Catalogue', 'vm-social-ai-pro' ); ?>
				</button>
				<button type="submit" class="vmsai-btn vmsai-btn--gold" style="padding: 10px 30px; font-weight:700;">
					💾 <?php esc_html_e( 'Save OmniRoute Setup', 'vm-social-ai-pro' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- ========================================== -->
	<!-- TAB 2: ADVANCED MULTI-PROVIDER CHAINS       -->
	<!-- ========================================== -->
	<div id="tab-chains" class="vmsai-tab-content">
		<!-- Chain 1: Words -->
		<section class="vmsai-panel">
			<div class="vmsai-panel__head">
				<div>
					<h2 class="vmsai-display"><?php esc_html_e( 'Engine one — words', 'vm-social-ai-pro' ); ?></h2>
					<p class="vmsai-lede"><?php esc_html_e( 'Failover chain for copywriting. Requests cascade down this list in order. Drag to reorder.', 'vm-social-ai-pro' ); ?></p>
				</div>
				<div class="vmsai-panel__tools">
					<button type="button" class="vmsai-btn" data-vmsai-action="test-text"><?php esc_html_e( 'Test Text Chain', 'vm-social-ai-pro' ); ?></button>
				</div>
			</div>

			<ol class="vmsai-chain" data-chain="text">
				<?php
				$vmsai_ordered = array_merge( $vmsai_chain, array_diff( array_keys( $vmsai_text->providers() ), $vmsai_chain ) );

				foreach ( $vmsai_ordered as $vmsai_slug ) :
					$vmsai_provider = $vmsai_text->provider( $vmsai_slug );
					if ( ! $vmsai_provider ) continue;

					$vmsai_on   = in_array( $vmsai_slug, $vmsai_chain, true );
					$vmsai_list = VMSAI_Model_Sync::models( $vmsai_slug, 'text' );
					?>
					<li class="vmsai-chain__item<?php echo $vmsai_provider->is_configured() ? ' is-ready' : ''; ?>" draggable="true">
						<div class="vmsai-chain__bar">
							<span class="vmsai-chain__grip" aria-hidden="true">⠿</span>
							<label class="vmsai-check">
								<input type="checkbox" name="text_chain[]" value="<?php echo esc_attr( $vmsai_slug ); ?>" <?php checked( $vmsai_on ); ?>>
								<span class="vmsai-chain__name"><?php echo esc_html( $vmsai_slug ); ?></span>
							</label>
							<span class="vmsai-chain__state">
								<?php echo $vmsai_provider->is_configured() ? esc_html__( 'Configured', 'vm-social-ai-pro' ) : esc_html__( 'Needs setup', 'vm-social-ai-pro' ); ?>
							</span>
							<button type="button" class="vmsai-btn vmsai-btn--quiet vmsai-chain__test" data-vmsai-action="test-provider" data-engine="text" data-provider="<?php echo esc_attr( $vmsai_slug ); ?>" <?php disabled( ! $vmsai_provider->is_configured() ); ?>><?php esc_html_e( 'Test', 'vm-social-ai-pro' ); ?></button>
						</div>

						<div class="vmsai-chain__result" aria-live="polite"></div>

						<div class="vmsai-chain__body">
							<?php if ( $vmsai_list ) : ?>
								<div class="vmsai-field">
									<label for="vmsai-model-<?php echo esc_attr( $vmsai_slug ); ?>"><?php esc_html_e( 'Model', 'vm-social-ai-pro' ); ?></label>
									<select id="vmsai-model-<?php echo esc_attr( $vmsai_slug ); ?>" name="text_model[<?php echo esc_attr( $vmsai_slug ); ?>]">
										<option value=""><?php esc_html_e( 'Provider default', 'vm-social-ai-pro' ); ?></option>
										<?php foreach ( $vmsai_list as $vmsai_model_item ) :
											$v_id = $vmsai_model_item['model_id'] ?? $vmsai_model_item['id'];
										?>
											<option value="<?php echo esc_attr( $v_id ); ?>" <?php selected( $vmsai_model[ $vmsai_slug ] ?? '', $v_id ); ?>>
												<?php
												echo esc_html( $vmsai_model_item['label'] );
												echo ! empty( $vmsai_model_item['is_free'] ) ? ' · ' . esc_html__( 'free', 'vm-social-ai-pro' ) : '';
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
									<input type="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? 'password' : ( 'textarea' === $vmsai_meta[1] ? 'text' : $vmsai_meta[1] ) ); ?>"
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
					</li>
				<?php endforeach; ?>
			</ol>
			<div id="vmsai-text-test" class="vmsai-testbox" aria-live="polite"></div>
		</section>

		<!-- Chain 2: Images -->
		<section class="vmsai-panel">
			<div class="vmsai-panel__head">
				<div>
					<h2 class="vmsai-display"><?php esc_html_e( 'Engine two — images', 'vm-social-ai-pro' ); ?></h2>
					<p class="vmsai-lede"><?php esc_html_e( 'Failover chain for artwork and creative. Stock fallback sits at the end.', 'vm-social-ai-pro' ); ?></p>
				</div>
				<div class="vmsai-panel__tools">
					<button type="button" class="vmsai-btn" data-vmsai-action="test-image"><?php esc_html_e( 'Test Image Chain', 'vm-social-ai-pro' ); ?></button>
				</div>
			</div>

			<ol class="vmsai-chain" data-chain="image">
				<?php
				$vmsai_iordered = array_merge( $vmsai_ichain, array_diff( array_keys( $vmsai_image->providers() ), $vmsai_ichain ) );

				foreach ( $vmsai_iordered as $vmsai_slug ) :
					$vmsai_provider = $vmsai_image->providers()[ $vmsai_slug ] ?? null;
					if ( ! $vmsai_provider ) continue;

					$vmsai_on   = in_array( $vmsai_slug, $vmsai_ichain, true );
					$vmsai_ilist = VMSAI_Model_Sync::models( $vmsai_slug, 'image' );
					?>
					<li class="vmsai-chain__item<?php echo $vmsai_provider->is_configured() ? ' is-ready' : ''; ?>" draggable="true">
						<div class="vmsai-chain__bar">
							<span class="vmsai-chain__grip" aria-hidden="true">⠿</span>
							<label class="vmsai-check">
								<input type="checkbox" name="image_chain[]" value="<?php echo esc_attr( $vmsai_slug ); ?>" <?php checked( $vmsai_on ); ?>>
								<span class="vmsai-chain__name"><?php echo esc_html( $vmsai_slug ); ?></span>
							</label>
							<span class="vmsai-chain__state">
								<?php echo $vmsai_provider->is_configured() ? esc_html__( 'Configured', 'vm-social-ai-pro' ) : esc_html__( 'Needs setup', 'vm-social-ai-pro' ); ?>
							</span>
							<button type="button" class="vmsai-btn vmsai-btn--quiet vmsai-chain__test" data-vmsai-action="test-provider" data-engine="image" data-provider="<?php echo esc_attr( $vmsai_slug ); ?>" <?php disabled( ! $vmsai_provider->is_configured() ); ?>><?php esc_html_e( 'Test', 'vm-social-ai-pro' ); ?></button>
						</div>

						<div class="vmsai-chain__result" aria-live="polite"></div>

						<div class="vmsai-chain__body">
							<?php if ( $vmsai_ilist ) : ?>
								<div class="vmsai-field">
									<label for="vmsai-imodel-<?php echo esc_attr( $vmsai_slug ); ?>"><?php esc_html_e( 'Model', 'vm-social-ai-pro' ); ?></label>
									<select id="vmsai-imodel-<?php echo esc_attr( $vmsai_slug ); ?>" name="image_model[<?php echo esc_attr( $vmsai_slug ); ?>]">
										<option value=""><?php esc_html_e( 'Provider default', 'vm-social-ai-pro' ); ?></option>
										<?php foreach ( $vmsai_ilist as $vmsai_model_item ) :
											$v_id = $vmsai_model_item['model_id'] ?? $vmsai_model_item['id'];
										?>
											<option value="<?php echo esc_attr( $v_id ); ?>" <?php selected( $vmsai_imodel[ $vmsai_slug ] ?? '', $v_id ); ?>>
												<?php
												echo esc_html( $vmsai_model_item['label'] );
												echo ! empty( $vmsai_model_item['is_free'] ) ? ' · ' . esc_html__( 'free', 'vm-social-ai-pro' ) : '';
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
									<input type="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? 'password' : ( 'textarea' === $vmsai_meta[1] ? 'text' : $vmsai_meta[1] ) ); ?>"
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
					</li>
				<?php endforeach; ?>
			</ol>
			<div id="vmsai-image-test" class="vmsai-testbox" aria-live="polite"></div>
		</section>

		<!-- Chain 3: Video -->
		<section class="vmsai-panel">
			<div class="vmsai-panel__head">
				<div>
					<h2 class="vmsai-display"><?php esc_html_e( 'Engine three — video', 'vm-social-ai-pro' ); ?></h2>
					<p class="vmsai-lede"><?php esc_html_e( 'Produces short-form video for TikTok, YouTube Shorts, Instagram Reels and Facebook Reels.', 'vm-social-ai-pro' ); ?></p>
				</div>
				<div class="vmsai-panel__tools">
					<button type="button" class="vmsai-btn" data-vmsai-action="test-video"><?php esc_html_e( 'Test Video Chain', 'vm-social-ai-pro' ); ?></button>
				</div>
			</div>

			<ol class="vmsai-chain" data-chain="video">
				<?php
				$vmsai_vordered = array_merge( $vmsai_vchain, array_diff( array_keys( $vmsai_video->providers() ), $vmsai_vchain ) );

				foreach ( $vmsai_vordered as $vmsai_slug ) :
					$vmsai_label = $vmsai_video->providers()[ $vmsai_slug ] ?? $vmsai_slug;
					$vmsai_on    = in_array( $vmsai_slug, $vmsai_vchain, true );
					$vmsai_is_ready = $vmsai_video->is_configured( $vmsai_slug );
					$vmsai_vlist = VMSAI_Model_Sync::models( $vmsai_slug, 'video' );
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

						<div class="vmsai-chain__body">
							<?php if ( $vmsai_vlist ) : ?>
								<div class="vmsai-field">
									<label for="vmsai-vmodel-<?php echo esc_attr( $vmsai_slug ); ?>"><?php esc_html_e( 'Model', 'vm-social-ai-pro' ); ?></label>
									<select id="vmsai-vmodel-<?php echo esc_attr( $vmsai_slug ); ?>" name="video_model[<?php echo esc_attr( $vmsai_slug ); ?>]">
										<option value=""><?php esc_html_e( 'Provider default', 'vm-social-ai-pro' ); ?></option>
										<?php foreach ( $vmsai_vlist as $vmsai_model_item ) :
											$v_id = $vmsai_model_item['model_id'] ?? $vmsai_model_item['id'];
										?>
											<option value="<?php echo esc_attr( $v_id ); ?>" <?php selected( $vmsai_vmodel[ $vmsai_slug ] ?? '', $v_id ); ?>>
												<?php
												echo esc_html( $vmsai_model_item['label'] );
												echo ! empty( $vmsai_model_item['is_free'] ) ? ' · ' . esc_html__( 'free', 'vm-social-ai-pro' ) : '';
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
									<input type="<?php echo esc_attr( 'password' === $vmsai_meta[1] ? 'password' : ( 'textarea' === $vmsai_meta[1] ? 'text' : $vmsai_meta[1] ) ); ?>"
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
					</li>
				<?php endforeach; ?>
			</ol>
			<div id="vmsai-video-test" class="vmsai-testbox" aria-live="polite"></div>
		</section>

		<!-- Chain 4: Auxiliary Intelligence -->
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
					</li>
				<?php endforeach; ?>
			</ol>
		</section>

		<!-- Request Behaviour -->
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
			<p><button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Save Advanced Chains', 'vm-social-ai-pro' ); ?></button></p>
		</section>
	</div>

	<!-- ========================================== -->
	<!-- TAB 3: LIVE MODEL CATALOGUE                 -->
	<!-- ========================================== -->
	<div id="tab-catalogue" class="vmsai-tab-content">
		<section class="vmsai-panel">
			<div class="vmsai-panel__head">
				<div>
					<h2 class="vmsai-display"><?php esc_html_e( 'Live Model Catalogue', 'vm-social-ai-pro' ); ?></h2>
					<p class="vmsai-lede"><?php printf( esc_html__( '%d models synchronized from your connected gateways.', 'vm-social-ai-pro' ), count( $all_synced_models ) ); ?></p>
				</div>
				<div class="vmsai-panel__tools">
					<button type="button" class="vmsai-btn" data-vmsai-action="sync-models">🔄 <?php esc_html_e( 'Refresh from OmniRoute', 'vm-social-ai-pro' ); ?></button>
				</div>
			</div>

			<div class="vmsai-cat-filter">
				<input type="search" id="vmsai-cat-search" class="vmsai-input" placeholder="<?php esc_attr_e( 'Filter models by name or id...', 'vm-social-ai-pro' ); ?>">
				<button type="button" class="vmsai-btn vmsai-btn--sm is-active" data-vmsai-filter="all"><?php esc_html_e( 'All', 'vm-social-ai-pro' ); ?></button>
				<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-filter="text"><?php esc_html_e( 'Text', 'vm-social-ai-pro' ); ?></button>
				<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-filter="image"><?php esc_html_e( 'Image', 'vm-social-ai-pro' ); ?></button>
				<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-filter="video"><?php esc_html_e( 'Video', 'vm-social-ai-pro' ); ?></button>
			</div>

			<div style="max-height: 520px; overflow-y: auto;">
				<table class="vmsai-cat-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Modality', 'vm-social-ai-pro' ); ?></th>
							<th><?php esc_html_e( 'Model ID', 'vm-social-ai-pro' ); ?></th>
							<th><?php esc_html_e( 'Display Name', 'vm-social-ai-pro' ); ?></th>
							<th><?php esc_html_e( 'Provider', 'vm-social-ai-pro' ); ?></th>
							<th><?php esc_html_e( 'Tier', 'vm-social-ai-pro' ); ?></th>
						</tr>
					</thead>
					<tbody id="vmsai-cat-body">
						<?php
						$count = 0;
						foreach ( $all_synced_models as $m ) :
							if ( ++$count > 250 ) break; // Cap initial render for snappy browser perf
							$mod = $m['modality'] ?? 'text';
							$is_free = ! empty( $m['is_free'] );
						?>
							<tr data-modality="<?php echo esc_attr( $mod ); ?>" data-search="<?php echo esc_attr( strtolower( ( $m['model_id'] ?? '' ) . ' ' . ( $m['label'] ?? '' ) ) ); ?>">
								<td><span class="vmsai-modality-card__pill" style="font-size:9px;"><?php echo esc_html( strtoupper( $mod ) ); ?></span></td>
								<td><code><?php echo esc_html( $m['model_id'] ?? '' ); ?></code></td>
								<td><strong><?php echo esc_html( $m['label'] ?? '' ); ?></strong></td>
								<td><?php echo esc_html( $m['provider'] ?? 'omniroute' ); ?></td>
								<td><span style="color: <?php echo $is_free ? 'var(--green)' : 'var(--muted)'; ?>; font-weight:600;"><?php echo $is_free ? esc_html__( 'Free', 'vm-social-ai-pro' ) : esc_html__( 'Standard', 'vm-social-ai-pro' ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>
</form>

<script>
(function($) {
	// Tab Switching
	$(document).on('click', '.vmsai-engine-tab', function() {
		var tabId = $(this).data('vmsai-tab');
		$('.vmsai-engine-tab').removeClass('is-active');
		$(this).addClass('is-active');

		$('.vmsai-tab-content').removeClass('is-active');
		$('#' + tabId).addClass('is-active');

		if (tabId === 'tab-omniroute') {
			$('#vmsai-engine-preset').val('omniroute');
		} else if (tabId === 'tab-chains') {
			$('#vmsai-engine-preset').val('chains');
		}

		if (window.localStorage) {
			window.localStorage.setItem('vmsai_active_engine_tab', tabId);
		}
	});

	// Restore tab
	if (window.localStorage) {
		var savedTab = window.localStorage.getItem('vmsai_active_engine_tab');
		if (savedTab && $('#' + savedTab).length) {
			$('[data-vmsai-tab="' + savedTab + '"]').trigger('click');
		}
	}

	// Catalogue Search Filter
	$('#vmsai-cat-search').on('input', function() {
		var q = $(this).val().toLowerCase();
		var activeMod = $('.vmsai-cat-filter button.is-active').data('vmsai-filter');
		$('#vmsai-cat-body tr').each(function() {
			var searchStr = $(this).data('search');
			var modality = $(this).data('modality');
			var matchText = !q || searchStr.indexOf(q) !== -1;
			var matchMod = (activeMod === 'all') || (modality === activeMod);
			$(this).toggle(matchText && matchMod);
		});
	});

	// Modality Filter buttons
	$('.vmsai-cat-filter button').on('click', function() {
		$('.vmsai-cat-filter button').removeClass('is-active');
		$(this).addClass('is-active');
		$('#vmsai-cat-search').trigger('input');
	});
})(jQuery);
</script>
