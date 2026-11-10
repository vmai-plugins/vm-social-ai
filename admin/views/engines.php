<?php
/**
 * View: AI Engines & Neural Backbone (Unified Redesign).
 *
 * Combines OmniRoute Unified Gateway, Local ComfyUI GPU Node, and Custom Multi-Provider
 * Failover into a single cohesive, high-end control center with an integrated interactive Model Explorer.
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
$vmsai_vchain = (array) VMSAI_Settings::get( 'video_chain', array( 'omniroute', 'pexels', 'aipuffer' ) );

// Saved models
$vmsai_model  = (array) VMSAI_Settings::get( 'text_model', array() );
$vmsai_imodel = (array) VMSAI_Settings::get( 'image_model', array() );
$vmsai_vmodel = (array) VMSAI_Settings::get( 'video_model', array() );

// Engine preset
$vmsai_preset = VMSAI_Settings::get( 'engine_preset', 'omniroute' );

// Sync metadata
$vmsai_sync = VMSAI_Model_Sync::state();

// OmniRoute credentials
$omni_url = VMSAI_Settings::credential( 'omniroute_url', 'http://127.0.0.1:20128/v1' );
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
		array( 'id' => 'comfyui/laptop2', 'label' => '💻 Laptop 2 ComfyUI GPU (RTX 3060 - DreamShaper 8) · Recommended', 'is_free' => 1 ),
		array( 'id' => 'aihorde/stable_diffusion', 'label' => 'AI Horde Stable Diffusion · Free Cloud', 'is_free' => 1 ),
		array( 'id' => 'aihorde/Flux.1-Schnell fp8 (Compact)', 'label' => 'AI Horde FLUX.1 Schnell · Free Cloud', 'is_free' => 1 ),
		array( 'id' => 'aihorde/AlbedoBase XL (SDXL)', 'label' => 'AI Horde AlbedoBase XL · Free Cloud', 'is_free' => 1 ),
	);
}

// Ensure Laptop 2 ComfyUI is first in list if not already present
$has_laptop2 = false;
foreach ( $omni_image_models as $m ) {
	if ( ( $m['id'] ?? '' ) === 'comfyui/laptop2' ) {
		$has_laptop2 = true;
		break;
	}
}
if ( ! $has_laptop2 ) {
	array_unshift( $omni_image_models, array(
		'id'      => 'comfyui/laptop2',
		'label'   => '💻 Laptop 2 ComfyUI GPU (RTX 3060 - DreamShaper 8) · Recommended',
		'is_free' => 1,
	) );
}

$omni_video_models = VMSAI_Model_Sync::models( 'omniroute', 'video' );
if ( empty( $omni_video_models ) ) {
	$omni_video_models = array(
		array( 'id' => 'pexels/instant-stock', 'label' => '🎥 Pexels HD Motion Stock (Instant Verified) · Recommended', 'is_free' => 1 ),
		array( 'id' => 'veo-free/veo', 'label' => 'Google Veo Free (OmniRoute Hub)', 'is_free' => 1 ),
		array( 'id' => 'veo-free/seedance', 'label' => 'ByteDance Seedance Free', 'is_free' => 1 ),
	);
}

// Full catalogue
$all_synced_models = VMSAI_Model_Sync::all();

// Provider credentials list for advanced mode
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
		'comfyui_url'      => array( __( 'Server URL', 'vm-social-ai-pro' ), 'url', __( 'e.g. https://comfy.vmstudio.digital (Laptop 2 RTX 3060).', 'vm-social-ai-pro' ) ),
		'comfyui_token'    => array( __( 'Bearer token', 'vm-social-ai-pro' ), 'password', '' ),
		'comfyui_workflow' => array( __( 'Workflow JSON', 'vm-social-ai-pro' ), 'textarea', __( 'Leave blank to use the built-in DreamShaper 8 workflow.', 'vm-social-ai-pro' ) ),
	),
	'pexels'       => array( 'pexels_key' => array( __( 'API key', 'vm-social-ai-pro' ), 'password', __( 'Pexels HD Video Stock key.', 'vm-social-ai-pro' ) ) ),
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
	/* Unified AI Engine Studio Styling */
	.vmsai-hub-header {
		display: flex; justify-content: space-between; align-items: center;
		padding: 20px 24px; background: linear-gradient(135deg, rgba(20,20,20,0.95), rgba(12,12,12,0.98));
		border: 1px solid var(--hairline); border-radius: 12px; margin-bottom: 24px;
		box-shadow: 0 10px 30px rgba(0,0,0,0.4);
	}
	.vmsai-mode-pillbox {
		display: inline-flex; background: rgba(0,0,0,0.4); border: 1px solid var(--hairline);
		padding: 4px; border-radius: 8px; gap: 4px;
	}
	.vmsai-mode-btn {
		background: transparent; color: var(--muted); border: none; padding: 8px 18px;
		font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer;
		display: inline-flex; align-items: center; gap: 8px; transition: all 160ms ease;
	}
	.vmsai-mode-btn:hover { color: var(--parchment); }
	.vmsai-mode-btn.is-active {
		background: var(--gold); color: #000; font-weight: 700;
		box-shadow: 0 4px 12px rgba(201, 162, 39, 0.3);
	}

	.vmsai-gateway-card {
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 12px;
		padding: 20px 24px; margin-bottom: 24px; position: relative;
	}
	.vmsai-gateway-grid {
		display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px;
	}
	@media (max-width: 800px) { .vmsai-gateway-grid { grid-template-columns: 1fr; } }

	.vmsai-modality-triad {
		display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px;
	}
	@media (max-width: 1080px) { .vmsai-modality-triad { grid-template-columns: 1fr; } }

	.vmsai-engine-card {
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 12px;
		padding: 20px; display: flex; flex-direction: column; justify-content: space-between;
		position: relative; transition: border-color 180ms ease, box-shadow 180ms ease;
	}
	.vmsai-engine-card:hover { border-color: var(--gold-soft); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
	.vmsai-engine-card__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
	.vmsai-engine-card__title { font-size: 15px; font-weight: 700; color: var(--parchment); margin: 0; display: flex; align-items: center; gap: 8px; }
	.vmsai-engine-card__tag { font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 4px; background: rgba(34,197,94,0.15); color: #22c55e; }
	.vmsai-engine-card__desc { font-size: 12px; color: var(--muted); margin: 0 0 16px; line-height: 1.45; min-height: 34px; }

	.vmsai-engine-card select {
		width: 100%; background: var(--ink); border: 1px solid var(--hairline); color: var(--parchment);
		padding: 10px 12px; border-radius: 6px; font-size: 12px; margin-bottom: 14px;
	}
	.vmsai-engine-card select:focus { border-color: var(--gold); outline: none; }

	.vmsai-engine-card__specs { list-style: none; margin: 0 0 18px; padding: 0; font-size: 11px; color: var(--muted); }
	.vmsai-engine-card__specs li { margin-bottom: 5px; display: flex; align-items: center; gap: 6px; }
	.vmsai-engine-card__specs li span { color: #22c55e; font-weight: bold; }

	.vmsai-engine-card__footer { margin-top: auto; padding-top: 14px; border-top: 1px solid var(--hairline); display: flex; align-items: center; justify-content: space-between; }
	.vmsai-engine-card__feedback { margin-top: 10px; font-size: 11px; line-height: 1.4; display: none; }

	/* Interactive Explorer Drawer */
	.vmsai-explorer-drawer {
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 12px;
		margin-bottom: 24px; overflow: hidden;
	}
	.vmsai-explorer-drawer__head {
		padding: 16px 20px; background: var(--raised); cursor: pointer; display: flex;
		align-items: center; justify-content: space-between; user-select: none;
	}
	.vmsai-explorer-drawer__head:hover { background: rgba(255,255,255,0.02); }
	.vmsai-explorer-body { padding: 20px; display: none; }
	.vmsai-explorer-body.is-open { display: block; }

	.vmsai-cat-table { width: 100%; border-collapse: collapse; font-size: 12px; }
	.vmsai-cat-table th, .vmsai-cat-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--hairline); }
	.vmsai-cat-table th { background: rgba(0,0,0,0.3); color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em; }
	.vmsai-cat-table tr:hover td { background: rgba(255,255,255,0.02); }

	.vmsai-mode-section { display: none; }
	.vmsai-mode-section.is-active { display: block; }
</style>

<div class="vmsai-hub-header">
	<div>
		<div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--gold); font-weight:700; margin-bottom:4px;">
			Neural Backbone & Generation Hub
		</div>
		<h1 style="font-size:22px; font-weight:800; color:#fff; margin:0;">
			AI Engines & Model Architecture
		</h1>
	</div>

	<!-- Clear Architecture Switcher -->
	<div class="vmsai-mode-pillbox" role="tablist">
		<button type="button" class="vmsai-mode-btn <?php echo ( 'chains' !== $vmsai_preset ) ? 'is-active' : ''; ?>" data-mode="omniroute">
			<span>🌟</span> OmniRoute Sovereign Hub (Recommended)
		</button>
		<button type="button" class="vmsai-mode-btn <?php echo ( 'chains' === $vmsai_preset ) ? 'is-active' : ''; ?>" data-mode="chains">
			<span>⚙️</span> Custom Multi-Provider Failover (Advanced)
		</button>
	</div>
</div>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="vmsai-engines-form">
	<?php wp_nonce_field( 'vmsai_save' ); ?>
	<input type="hidden" name="action" value="vmsai_save">
	<input type="hidden" name="section" value="engines">
	<input type="hidden" name="engine_preset" id="vmsai-engine-preset" value="<?php echo esc_attr( $vmsai_preset ); ?>">

	<!-- ======================================================== -->
	<!-- SECTION 1: OMNIROUTE SOVEREIGN HUB (RECOMMENDED)          -->
	<!-- ======================================================== -->
	<div id="section-omniroute" class="vmsai-mode-section <?php echo ( 'chains' !== $vmsai_preset ) ? 'is-active' : ''; ?>">
		<!-- Gateway Configuration -->
		<div class="vmsai-gateway-card">
			<div style="display:flex; justify-content:space-between; align-items:center;">
				<div>
					<h2 style="font-size:16px; font-weight:700; color:#fff; margin:0 0 4px;">
						⚡ OmniRoute Unified Gateway
					</h2>
					<p style="margin:0; font-size:12px; color:var(--muted);">
						Your self-hosted neural gateway routing high-speed generation across all three core modalities without external API subscriptions.
					</p>
				</div>
				<span class="vmsai-status <?php echo $omni_configured ? 'vmsai-status--live' : 'vmsai-status--down'; ?>">
					<?php echo $omni_configured ? '● Connected (' . count( $all_synced_models ) . ' Models)' : '○ Unconfigured'; ?>
				</span>
			</div>

			<div class="vmsai-gateway-grid">
				<div class="vmsai-field">
					<label for="vmsai-omni-url"><strong>Gateway Endpoint URL</strong></label>
					<input type="url" id="vmsai-omni-url" name="omniroute_url" data-vmsai-cred="1"
						placeholder="http://127.0.0.1:20128/v1"
						value="<?php echo esc_attr( $omni_url ); ?>" style="width: 100%;">
					<p class="vmsai-hint">Local Docker gateway (<code>http://127.0.0.1:20128/v1</code>) or public URL (<code>https://ai.vmstudio.digital/v1</code>).</p>
				</div>
				<div class="vmsai-field">
					<label for="vmsai-omni-key"><strong>OmniRoute Bearer API Key</strong></label>
					<input type="password" id="vmsai-omni-key" name="omniroute_key" data-vmsai-cred="1"
						placeholder="sk-..."
						value="<?php echo esc_attr( VMSAI_Settings::mask( 'omniroute_key' ) ); ?>" style="width: 100%;">
					<p class="vmsai-hint">Encrypted securely with site salts. Powers all modalities simultaneously.</p>
				</div>
			</div>
		</div>

		<!-- The 3 Core Modalities -->
		<div class="vmsai-modality-triad">
			<!-- 1. Text / Copywriting -->
			<div class="vmsai-engine-card">
				<div>
					<div class="vmsai-engine-card__head">
						<h3 class="vmsai-engine-card__title"><span>✍️</span> Copywriting & Captions</h3>
						<span class="vmsai-engine-card__tag">TEXT</span>
					</div>
					<p class="vmsai-engine-card__desc">Drafts viral social hooks, carousel slides, captions, and hashtags.</p>

					<label for="vmsai-omni-text-model" style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--muted); display:block; margin-bottom:6px;">
						Active Text Model
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

					<ul class="vmsai-engine-card__specs">
						<li><span>✓</span> Grok 4.6 / Gemini 2.5 Flash / DeepSeek V3</li>
						<li><span>✓</span> Automatic markdown formatting</li>
						<li><span>✓</span> High-speed streaming response</li>
					</ul>
				</div>

				<div>
					<div class="vmsai-engine-card__footer">
						<button type="button" class="vmsai-btn vmsai-btn--quiet" data-vmsai-action="test-provider" data-engine="text" data-provider="omniroute">
							⚡ Test Text Engine
						</button>
					</div>
					<div class="vmsai-engine-card__feedback vmsai-chain__result" id="omni-test-result-text" aria-live="polite"></div>
				</div>
			</div>

			<!-- 2. Image / Illustration (ComfyUI RTX 3060 Featured) -->
			<div class="vmsai-engine-card" style="border-color: rgba(201,162,39,0.3);">
				<div>
					<div class="vmsai-engine-card__head">
						<h3 class="vmsai-engine-card__title"><span>🎨</span> Visual Art & Graphics</h3>
						<span class="vmsai-engine-card__tag" style="background:rgba(201,162,39,0.15); color:var(--gold);">LOCAL GPU / CLOUD</span>
					</div>
					<p class="vmsai-engine-card__desc">Creates photorealistic marketing artwork, illustrations, and ad graphics.</p>

					<label for="vmsai-omni-img-model" style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--muted); display:block; margin-bottom:6px;">
						Active Image Engine
					</label>
					<select id="vmsai-omni-img-model" name="image_model[omniroute]">
						<?php foreach ( $omni_image_models as $m ) :
							$mid = $m['model_id'] ?? $m['id'];
							$sel = ( ($vmsai_imodel['omniroute'] ?? 'comfyui/laptop2') === $mid );
						?>
							<option value="<?php echo esc_attr( $mid ); ?>" <?php selected( $sel ); ?>>
								<?php echo esc_html( $m['label'] ?? $mid ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<ul class="vmsai-engine-card__specs">
						<li><span>✓</span> <strong>Laptop 2 ComfyUI (RTX 3060)</strong>: 4-6s render</li>
						<li><span>✓</span> Auto-ratio multiples of 64 (512x768 / 768x512)</li>
						<li><span>✓</span> Cloud AI Horde (FLUX.1 Schnell / SDXL) fallback</li>
					</ul>
				</div>

				<div>
					<div class="vmsai-engine-card__footer">
						<button type="button" class="vmsai-btn vmsai-btn--quiet" data-vmsai-action="test-provider" data-engine="image" data-provider="comfyui">
							⚡ Test ComfyUI GPU
						</button>
					</div>
					<div class="vmsai-engine-card__feedback vmsai-chain__result" id="omni-test-result-image" aria-live="polite"></div>
				</div>
			</div>

			<!-- 3. Video / Motion Lab (Pexels HD Stock Featured) -->
			<div class="vmsai-engine-card">
				<div>
					<div class="vmsai-engine-card__head">
						<h3 class="vmsai-engine-card__title"><span>🎬</span> Cinematic Motion Video</h3>
						<span class="vmsai-engine-card__tag" style="background:rgba(59,130,246,0.15); color:#60a5fa;">VERIFIED LIVE</span>
					</div>
					<p class="vmsai-engine-card__desc">Produces vertical 9:16 reels, Shorts, and cinematic background footage.</p>

					<label for="vmsai-omni-vid-model" style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--muted); display:block; margin-bottom:6px;">
						Active Video Sourcing
					</label>
					<select id="vmsai-omni-vid-model" name="video_model[omniroute]">
						<?php foreach ( $omni_video_models as $m ) :
							$mid = $m['model_id'] ?? $m['id'];
							$sel = ( ($vmsai_vmodel['omniroute'] ?? 'pexels/instant-stock') === $mid );
						?>
							<option value="<?php echo esc_attr( $mid ); ?>" <?php selected( $sel ); ?>>
								<?php echo esc_html( $m['label'] ?? $mid ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<ul class="vmsai-engine-card__specs">
						<li><span>✓</span> <strong>Pexels HD Video Stock</strong>: Instant 3s rendering</li>
						<li><span>✓</span> 1080p / 4K portrait 9:16 format</li>
						<li><span>✓</span> Google Veo / Seedance API support</li>
					</ul>
				</div>

				<div>
					<div class="vmsai-engine-card__footer">
						<button type="button" class="vmsai-btn vmsai-btn--quiet" data-vmsai-action="test-video">
							⚡ Test Video Engine
						</button>
					</div>
					<div class="vmsai-engine-card__feedback vmsai-chain__result" id="omni-test-result-video" aria-live="polite"></div>
				</div>
			</div>
		</div>

		<!-- Interactive Model Explorer Drawer (Integrated & Actionable) -->
		<div class="vmsai-explorer-drawer">
			<div class="vmsai-explorer-drawer__head" id="vmsai-toggle-explorer">
				<div style="display:flex; align-items:center; gap:10px;">
					<span style="font-size:16px;">🔍</span>
					<div>
						<strong style="color:#fff; font-size:13px;">Live Model Catalogue Explorer</strong>
						<span style="color:var(--muted); font-size:11px; margin-left:8px;">(<?php echo count( $all_synced_models ); ?> models synced from OmniRoute)</span>
					</div>
				</div>
				<span style="color:var(--gold); font-size:11px; font-weight:600;" id="vmsai-explorer-indicator">▼ Click to Search & Browse Models</span>
			</div>

			<div class="vmsai-explorer-body" id="vmsai-explorer-panel">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; flex-wrap:wrap;">
					<div class="vmsai-cat-filter" style="display:flex; gap:8px;">
						<input type="search" id="vmsai-cat-search" class="vmsai-input" placeholder="Search models by name or id (e.g. grok, gemini, flux)..." style="min-width:300px;">
						<button type="button" class="vmsai-btn vmsai-btn--sm is-active" data-vmsai-filter="all">All</button>
						<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-filter="text">Text</button>
						<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-filter="image">Image</button>
						<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-filter="video">Video</button>
					</div>
					<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-action="sync-models">
						🔄 Refresh Sync
					</button>
				</div>

				<div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--hairline); border-radius: 8px;">
					<table class="vmsai-cat-table">
						<thead>
							<tr>
								<th>Modality</th>
								<th>Model ID</th>
								<th>Display Name</th>
								<th>Provider</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody id="vmsai-cat-body">
							<?php
							$count = 0;
							foreach ( $all_synced_models as $m ) :
								if ( ++$count > 300 ) break;
								$mod = $m['modality'] ?? 'text';
								$mid = $m['model_id'] ?? $m['id'];
								$lbl = $m['label'] ?? $mid;
							?>
								<tr data-modality="<?php echo esc_attr( $mod ); ?>" data-search="<?php echo esc_attr( strtolower( $mid . ' ' . $lbl ) ); ?>">
									<td><span class="vmsai-engine-card__tag" style="font-size:9px;"><?php echo esc_html( strtoupper( $mod ) ); ?></span></td>
									<td><code><?php echo esc_html( $mid ); ?></code></td>
									<td><strong><?php echo esc_html( $lbl ); ?></strong></td>
									<td><?php echo esc_html( $m['provider'] ?? 'omniroute' ); ?></td>
									<td>
										<button type="button" class="vmsai-btn vmsai-btn--sm vmsai-btn-use-model"
											data-modality="<?php echo esc_attr( $mod ); ?>"
											data-model-id="<?php echo esc_attr( $mid ); ?>"
											data-model-label="<?php echo esc_attr( $lbl ); ?>"
											style="padding:2px 8px; font-size:10px;">
											Use as Active
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div style="display: flex; gap: 12px; align-items: center; justify-content: flex-end; margin-top: 20px;">
			<button type="submit" class="vmsai-btn vmsai-btn--gold" style="padding: 12px 36px; font-weight:700; font-size:14px;">
				💾 Save AI Engines Configuration
			</button>
		</div>
	</div>

	<!-- ======================================================== -->
	<!-- SECTION 2: CUSTOM MULTI-PROVIDER CHAINS (ADVANCED)       -->
	<!-- ======================================================== -->
	<div id="section-chains" class="vmsai-mode-section <?php echo ( 'chains' === $vmsai_preset ) ? 'is-active' : ''; ?>">
		<!-- Chain 1: Words -->
		<section class="vmsai-panel">
			<div class="vmsai-panel__head">
				<div>
					<h2 class="vmsai-display"><?php esc_html_e( 'Text Engine — Direct Provider Failover', 'vm-social-ai-pro' ); ?></h2>
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
					<h2 class="vmsai-display"><?php esc_html_e( 'Image Engine — Direct Artwork Failover', 'vm-social-ai-pro' ); ?></h2>
					<p class="vmsai-lede"><?php esc_html_e( 'Failover chain for artwork. Laptop 2 ComfyUI GPU and cloud providers cascade down.', 'vm-social-ai-pro' ); ?></p>
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
					<h2 class="vmsai-display"><?php esc_html_e( 'Video Engine — Direct Motion Sourcing', 'vm-social-ai-pro' ); ?></h2>
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
					$vmsai_vmeta = $vmsai_video->providers()[ $vmsai_slug ] ?? null;
					if ( ! $vmsai_vmeta ) continue;

					$vmsai_on    = in_array( $vmsai_slug, $vmsai_vchain, true );
					$vmsai_vlist = VMSAI_Model_Sync::models( $vmsai_slug, 'video' );
					?>
					<li class="vmsai-chain__item<?php echo $vmsai_video->is_configured( $vmsai_slug ) ? ' is-ready' : ''; ?>" draggable="true">
						<div class="vmsai-chain__bar">
							<span class="vmsai-chain__grip" aria-hidden="true">⠿</span>
							<label class="vmsai-check">
								<input type="checkbox" name="video_chain[]" value="<?php echo esc_attr( $vmsai_slug ); ?>" <?php checked( $vmsai_on ); ?>>
								<span class="vmsai-chain__name"><?php echo esc_html( $vmsai_slug ); ?></span>
							</label>
							<span class="vmsai-chain__state">
								<?php echo $vmsai_video->is_configured( $vmsai_slug ) ? esc_html__( 'Configured', 'vm-social-ai-pro' ) : esc_html__( 'Needs setup', 'vm-social-ai-pro' ); ?>
							</span>
							<button type="button" class="vmsai-btn vmsai-btn--quiet vmsai-chain__test" data-vmsai-action="test-provider" data-engine="video" data-provider="<?php echo esc_attr( $vmsai_slug ); ?>" <?php disabled( ! $vmsai_video->is_configured( $vmsai_slug ) ); ?>><?php esc_html_e( 'Test', 'vm-social-ai-pro' ); ?></button>
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

		<!-- Auxiliary Intelligence (Voice & Deep Research) -->
		<section class="vmsai-panel">
			<div class="vmsai-panel__head">
				<div>
					<h2 class="vmsai-display"><?php esc_html_e( 'Auxiliary Intelligence & Tools', 'vm-social-ai-pro' ); ?></h2>
					<p class="vmsai-lede"><?php esc_html_e( 'Voiceover generation and deep news research engines.', 'vm-social-ai-pro' ); ?></p>
				</div>
			</div>

			<ol class="vmsai-chain">
				<?php foreach ( array( 'elevenlabs', 'tavily' ) as $vmsai_slug ) :
					$vmsai_label = ( 'elevenlabs' === $vmsai_slug ) ? 'ElevenLabs (Voiceover Studio)' : 'Tavily (Deep Real-Time Research)';
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
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Request Behaviour & Resilience', 'vm-social-ai-pro' ); ?></h2>
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
			<p><button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Save Multi-Provider Chains', 'vm-social-ai-pro' ); ?></button></p>
		</section>
	</div>
</form>

<script>
(function($) {
	// Mode Switcher (OmniRoute vs Chains)
	$(document).on('click', '.vmsai-mode-btn', function() {
		var mode = $(this).data('mode');
		$('.vmsai-mode-btn').removeClass('is-active');
		$(this).addClass('is-active');

		$('.vmsai-mode-section').removeClass('is-active');
		$('#section-' + mode).addClass('is-active');

		$('#vmsai-engine-preset').val(mode);
		if (window.localStorage) {
			window.localStorage.setItem('vmsai_engine_active_mode', mode);
		}
	});

	// Restore mode
	if (window.localStorage) {
		var savedMode = window.localStorage.getItem('vmsai_engine_active_mode');
		if (savedMode && $('[data-mode="' + savedMode + '"]').length) {
			$('[data-mode="' + savedMode + '"]').trigger('click');
		}
	}

	// Collapsible Explorer Drawer
	$('#vmsai-toggle-explorer').on('click', function() {
		var $panel = $('#vmsai-explorer-panel');
		$panel.toggleClass('is-open');
		var isOpen = $panel.hasClass('is-open');
		$('#vmsai-explorer-indicator').text(isOpen ? '▲ Hide Model Catalogue' : '▼ Click to Search & Browse Models');
	});

	// Model Search Filter
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

	// Use Model as Active
	$(document).on('click', '.vmsai-btn-use-model', function() {
		var mod = $(this).data('modality');
		var mid = $(this).data('model-id');
		var lbl = $(this).data('model-label');

		if (mod === 'text') {
			var $select = $('#vmsai-omni-text-model');
			if ($select.find('option[value="' + mid + '"]').length === 0) {
				$select.append(new Option(lbl, mid, true, true));
			} else {
				$select.val(mid);
			}
			VMSAI.toast('Active Text model updated to ' + lbl);
		} else if (mod === 'image') {
			var $select = $('#vmsai-omni-img-model');
			if ($select.find('option[value="' + mid + '"]').length === 0) {
				$select.append(new Option(lbl, mid, true, true));
			} else {
				$select.val(mid);
			}
			VMSAI.toast('Active Image engine updated to ' + lbl);
		} else if (mod === 'video') {
			var $select = $('#vmsai-omni-vid-model');
			if ($select.find('option[value="' + mid + '"]').length === 0) {
				$select.append(new Option(lbl, mid, true, true));
			} else {
				$select.val(mid);
			}
			VMSAI.toast('Active Video engine updated to ' + lbl);
		}

		// Scroll up smoothly to the card
		$('html, body').animate({ scrollTop: $('#section-omniroute').offset().top - 40 }, 300);
	});
})(jQuery);
</script>
