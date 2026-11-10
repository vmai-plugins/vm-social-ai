<?php
/**
 * View: AI Engines & Neural Backbone (Merged Single-Page Control Center).
 *
 * Merges OmniRoute Unified Gateway, Laptop 2 ComfyUI GPU Node, Direct Fallback Keys,
 * and the Live Model Catalogue Explorer onto a single, zero-duplicate luxury control panel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

// Engine instances
$vmsai_text  = vmsai()->text_engine();
$vmsai_image = vmsai()->image_engine();
$vmsai_video = vmsai()->video_engine();

// Saved settings
$vmsai_model  = (array) VMSAI_Settings::get( 'text_model', array() );
$vmsai_imodel = (array) VMSAI_Settings::get( 'image_model', array() );
$vmsai_vmodel = (array) VMSAI_Settings::get( 'video_model', array() );

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

// Ensure Laptop 2 ComfyUI is first in image list
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
		array( 'id' => 'comfyui/laptop2', 'label' => '🖥️ ComfyUI (Laptop 2 GPU Node · AnimateDiff)', 'is_free' => 1 ),
		array( 'id' => 'veo-free/veo', 'label' => 'Google Veo Free (OmniRoute Hub)', 'is_free' => 1 ),
		array( 'id' => 'veo-free/seedance', 'label' => 'ByteDance Seedance Free', 'is_free' => 1 ),
	);
} else {
	array_unshift( $omni_video_models, array( 'id' => 'comfyui/laptop2', 'label' => '🖥️ ComfyUI (Laptop 2 GPU Node · AnimateDiff)', 'is_free' => 1 ) );
	array_unshift( $omni_video_models, array( 'id' => 'pexels/instant-stock', 'label' => '🎥 Pexels HD Motion Stock (Instant Verified) · Recommended', 'is_free' => 1 ) );
}

// Full catalogue
$all_synced_models = VMSAI_Model_Sync::all();
?>

<style>
	/* Single-Page Merged AI Control Center */
	.vmsai-hub-header {
		display: flex; justify-content: space-between; align-items: center;
		padding: 22px 26px; background: linear-gradient(135deg, rgba(22,22,22,0.98), rgba(12,12,12,0.99));
		border: 1px solid var(--hairline); border-radius: 12px; margin-bottom: 24px;
		box-shadow: 0 10px 30px rgba(0,0,0,0.4);
	}

	.vmsai-gateway-card {
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 12px;
		padding: 22px 24px; margin-bottom: 24px; position: relative;
	}
	.vmsai-gateway-grid {
		display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px;
	}
	@media (max-width: 800px) { .vmsai-gateway-grid { grid-template-columns: 1fr; } }

	.vmsai-modality-triad {
		display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px;
	}
	@media (max-width: 1100px) { .vmsai-modality-triad { grid-template-columns: 1fr; } }

	.vmsai-engine-card {
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 12px;
		padding: 22px; display: flex; flex-direction: column; justify-content: space-between;
		position: relative; transition: border-color 180ms ease, box-shadow 180ms ease;
	}
	.vmsai-engine-card:hover { border-color: var(--gold-soft); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
	.vmsai-engine-card__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
	.vmsai-engine-card__title { font-size: 15px; font-weight: 700; color: var(--parchment); margin: 0; display: flex; align-items: center; gap: 8px; }
	.vmsai-engine-card__tag { font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 4px; }
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

	/* Expandable Direct API Key Trays inside cards */
	.vmsai-card-accordion {
		margin-top: 12px; padding-top: 10px; border-top: 1px dashed var(--hairline);
	}
	.vmsai-card-accordion summary {
		font-size: 11px; color: var(--muted); font-weight: 600; cursor: pointer; user-select: none;
	}
	.vmsai-card-accordion summary:hover { color: var(--gold); }
	.vmsai-card-accordion__content {
		margin-top: 10px; padding: 10px; background: var(--ink); border-radius: 6px; border: 1px solid var(--hairline);
	}

	/* Auxiliary & Settings Bar */
	.vmsai-aux-section {
		display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;
	}
	@media (max-width: 800px) { .vmsai-aux-section { grid-template-columns: 1fr; } }

	/* Interactive Model Catalogue Explorer */
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
</style>

<div class="vmsai-hub-header">
	<div>
		<div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--gold); font-weight:700; margin-bottom:4px;">
			Unified Neural Command Center
		</div>
		<h1 style="font-size:22px; font-weight:800; color:#fff; margin:0;">
			AI Engines & Model Pipeline
		</h1>
	</div>
	<div style="display:flex; gap:12px; align-items:center;">
		<span class="vmsai-status vmsai-status--live">
			● <?php echo count( $all_synced_models ); ?> Live Models Synced
		</span>
		<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-action="sync-models">
			🔄 Sync Gateway
		</button>
	</div>
</div>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="vmsai-engines-form">
	<?php wp_nonce_field( 'vmsai_save' ); ?>
	<input type="hidden" name="action" value="vmsai_save">
	<input type="hidden" name="section" value="engines">
	<input type="hidden" name="engine_preset" value="omniroute">

	<!-- 1. Unified Gateway Hub -->
	<div class="vmsai-gateway-card">
		<div style="display:flex; justify-content:space-between; align-items:center;">
			<div>
				<h2 style="font-size:16px; font-weight:700; color:#fff; margin:0 0 4px;">
					⚡ OmniRoute Gateway Hub
				</h2>
				<p style="margin:0; font-size:12px; color:var(--muted);">
					Centralized neural gateway powering high-speed copy, illustrations, and motion video across 1,000+ pooled models at zero marginal cost.
				</p>
			</div>
			<span class="vmsai-status <?php echo $omni_configured ? 'vmsai-status--live' : 'vmsai-status--down'; ?>">
				<?php echo $omni_configured ? '● Connected' : '○ Not Configured'; ?>
			</span>
		</div>

		<div class="vmsai-gateway-grid">
			<div class="vmsai-field">
				<label for="vmsai-omni-url"><strong>Gateway Endpoint URL</strong></label>
				<input type="url" id="vmsai-omni-url" name="omniroute_url" data-vmsai-cred="1"
					placeholder="http://127.0.0.1:20128/v1"
					value="<?php echo esc_attr( $omni_url ); ?>" style="width: 100%;">
				<p class="vmsai-hint">Local Docker gateway (<code>http://127.0.0.1:20128/v1</code>) or public reverse proxy (<code>https://ai.vmstudio.digital/v1</code>).</p>
			</div>
			<div class="vmsai-field">
				<label for="vmsai-omni-key"><strong>OmniRoute Bearer API Key</strong></label>
				<input type="password" id="vmsai-omni-key" name="omniroute_key" data-vmsai-cred="1"
					placeholder="sk-..."
					value="<?php echo esc_attr( VMSAI_Settings::mask( 'omniroute_key' ) ); ?>" style="width: 100%;">
				<p class="vmsai-hint">AES-256-GCM encrypted. Manages token pooling and model routing.</p>
			</div>
		</div>
	</div>

	<!-- 2. The 3 Core Modalities (Merged & De-duplicated) -->
	<div class="vmsai-modality-triad">
		<!-- Modality 1: Copywriting (Text) -->
		<div class="vmsai-engine-card">
			<div>
				<div class="vmsai-engine-card__head">
					<h3 class="vmsai-engine-card__title"><span>✍️</span> Copywriting Engine</h3>
					<span class="vmsai-engine-card__tag" style="background:rgba(34,197,94,0.15); color:#22c55e;">TEXT</span>
				</div>
				<p class="vmsai-engine-card__desc">Generates viral social hooks, carousel slides, captions, and hashtags.</p>

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
					<li><span>✓</span> Grok 4.6 backend / Gemini 2.5 Flash / DeepSeek V3</li>
					<li><span>✓</span> Automatic markdown social formatting</li>
					<li><span>✓</span> Ultra-low latency streaming inference</li>
				</ul>

				<!-- Direct API Fallback Drawer -->
				<details class="vmsai-card-accordion">
					<summary>⚙️ Direct API Keys (Optional Fallback)</summary>
					<div class="vmsai-card-accordion__content">
						<div class="vmsai-field" style="margin-bottom:8px;">
							<label style="font-size:11px;">Google Gemini API Key</label>
							<input type="password" name="gemini_key" data-vmsai-cred="1" placeholder="AIzaSy..." value="<?php echo esc_attr( VMSAI_Settings::mask( 'gemini_key' ) ); ?>" style="width:100%; font-size:11px;">
						</div>
						<div class="vmsai-field" style="margin-bottom:8px;">
							<label style="font-size:11px;">Anthropic API Key (Claude 3.5)</label>
							<input type="password" name="anthropic_key" data-vmsai-cred="1" placeholder="sk-ant-..." value="<?php echo esc_attr( VMSAI_Settings::mask( 'anthropic_key' ) ); ?>" style="width:100%; font-size:11px;">
						</div>
						<div class="vmsai-field">
							<label style="font-size:11px;">OpenAI API Key (GPT-4o)</label>
							<input type="password" name="openai_key" data-vmsai-cred="1" placeholder="sk-..." value="<?php echo esc_attr( VMSAI_Settings::mask( 'openai_key' ) ); ?>" style="width:100%; font-size:11px;">
						</div>
					</div>
				</details>
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

		<!-- Modality 2: Visual Art & Creative (Image) -->
		<div class="vmsai-engine-card" style="border-color: rgba(201,162,39,0.35);">
			<div>
				<div class="vmsai-engine-card__head">
					<h3 class="vmsai-engine-card__title"><span>🎨</span> Visual Art & Graphics</h3>
					<span class="vmsai-engine-card__tag" style="background:rgba(201,162,39,0.15); color:var(--gold);">LOCAL GPU / CLOUD</span>
				</div>
				<p class="vmsai-engine-card__desc">Creates photorealistic marketing artwork, social banners, and illustrations.</p>

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
					<li><span>✓</span> Auto multiples of 64 (512x768 Portrait, 768x512 Landscape)</li>
					<li><span>✓</span> AI Horde (FLUX.1 Schnell & SDXL) free cloud backup</li>
				</ul>

				<!-- ComfyUI Node Details & Direct Cloud Keys -->
				<details class="vmsai-card-accordion" open>
					<summary>💻 Local ComfyUI GPU Node Configuration</summary>
					<div class="vmsai-card-accordion__content">
						<div class="vmsai-field" style="margin-bottom:8px;">
							<label style="font-size:11px;">ComfyUI Endpoint URL</label>
							<input type="url" name="comfyui_url" data-vmsai-cred="1" placeholder="https://comfy.vmstudio.digital" value="<?php echo esc_attr( VMSAI_Settings::credential( 'comfyui_url', 'https://comfy.vmstudio.digital' ) ); ?>" style="width:100%; font-size:11px;">
							<p class="vmsai-hint" style="font-size:10px; margin-top:2px;">Secured Cloudflare Tunnel routed to Laptop 2 RTX 3060.</p>
						</div>
						<div class="vmsai-field">
							<label style="font-size:11px;">Hugging Face Token (Optional)</label>
							<input type="password" name="hf_token" data-vmsai-cred="1" placeholder="hf_..." value="<?php echo esc_attr( VMSAI_Settings::mask( 'hf_token' ) ); ?>" style="width:100%; font-size:11px;">
						</div>
					</div>
				</details>
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

		<!-- Modality 3: Motion & Video Lab -->
		<div class="vmsai-engine-card">
			<div>
				<div class="vmsai-engine-card__head">
					<h3 class="vmsai-engine-card__title"><span>🎬</span> Cinematic Motion Video</h3>
					<span class="vmsai-engine-card__tag" style="background:rgba(59,130,246,0.15); color:#60a5fa;">VERIFIED LIVE</span>
				</div>
				<p class="vmsai-engine-card__desc">Produces vertical 9:16 reels, Shorts, and cinematic background video clips.</p>

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
					<li><span>✓</span> <strong>Pexels HD Video Stock</strong>: Instant 3-second delivery</li>
					<li><span>✓</span> 1080p / 4K portrait 9:16 aspect ratio</li>
					<li><span>✓</span> Google Veo & ByteDance Seedance support</li>
				</ul>

				<!-- Direct Video Keys Accordion -->
				<details class="vmsai-card-accordion">
					<summary>⚙️ Video API Keys (Pexels / MiniMax / Luma)</summary>
					<div class="vmsai-card-accordion__content">
						<div class="vmsai-field" style="margin-bottom:8px;">
							<label style="font-size:11px;">Pexels Stock API Key (Active)</label>
							<input type="password" name="pexels_key" data-vmsai-cred="1" placeholder="Pexels Key..." value="<?php echo esc_attr( VMSAI_Settings::mask( 'pexels_key' ) ); ?>" style="width:100%; font-size:11px;">
						</div>
						<div class="vmsai-field" style="margin-bottom:8px;">
							<label style="font-size:11px;">MiniMax / Hailuo API Key</label>
							<input type="password" name="minimax_key" data-vmsai-cred="1" placeholder="MiniMax Key..." value="<?php echo esc_attr( VMSAI_Settings::mask( 'minimax_key' ) ); ?>" style="width:100%; font-size:11px;">
						</div>
						<div class="vmsai-field">
							<label style="font-size:11px;">Luma Dream Machine API Key</label>
							<input type="password" name="luma_key" data-vmsai-cred="1" placeholder="Luma Key..." value="<?php echo esc_attr( VMSAI_Settings::mask( 'luma_key' ) ); ?>" style="width:100%; font-size:11px;">
						</div>
					</div>
				</details>
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

	<!-- 3. Auxiliary Tools (Voiceover & Deep Research) -->
	<div class="vmsai-aux-section">
		<div class="vmsai-gateway-card" style="margin-bottom:0;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
				<h3 style="margin:0; font-size:14px; color:#fff; display:flex; align-items:center; gap:8px;">
					<span>🎙️</span> AI Voiceover Studio (ElevenLabs)
				</h3>
				<span class="vmsai-status <?php echo ! empty( VMSAI_Settings::credential( 'elevenlabs_key' ) ) ? 'vmsai-status--live' : 'vmsai-status--down'; ?>">
					<?php echo ! empty( VMSAI_Settings::credential( 'elevenlabs_key' ) ) ? 'Configured' : 'Optional'; ?>
				</span>
			</div>
			<p style="margin:0 0 12px; font-size:12px; color:var(--muted);">Generates studio-grade spoken voiceovers for TikToks, YouTube Shorts, and Reels.</p>
			<div class="vmsai-field">
				<label for="vmsai-elevenlabs-key" style="font-size:11px; font-weight:600;">ElevenLabs API Key</label>
				<input type="password" id="vmsai-elevenlabs-key" name="elevenlabs_key" data-vmsai-cred="1" placeholder="xi-api-key-..." value="<?php echo esc_attr( VMSAI_Settings::mask( 'elevenlabs_key' ) ); ?>" style="width:100%;">
			</div>
		</div>

		<div class="vmsai-gateway-card" style="margin-bottom:0;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
				<h3 style="margin:0; font-size:14px; color:#fff; display:flex; align-items:center; gap:8px;">
					<span>🌐</span> Real-Time Web Intelligence (Tavily)
				</h3>
				<span class="vmsai-status <?php echo ! empty( VMSAI_Settings::credential( 'tavily_key' ) ) ? 'vmsai-status--live' : 'vmsai-status--down'; ?>">
					<?php echo ! empty( VMSAI_Settings::credential( 'tavily_key' ) ) ? 'Configured' : 'Optional'; ?>
				</span>
			</div>
			<p style="margin:0 0 12px; font-size:12px; color:var(--muted);">Powers live news-jacking and competitor research to ground social posts in real-time trends.</p>
			<div class="vmsai-field">
				<label for="vmsai-tavily-key" style="font-size:11px; font-weight:600;">Tavily API Key</label>
				<input type="password" id="vmsai-tavily-key" name="tavily_key" data-vmsai-cred="1" placeholder="tvly-..." value="<?php echo esc_attr( VMSAI_Settings::mask( 'tavily_key' ) ); ?>" style="width:100%;">
			</div>
		</div>
	</div>

	<!-- 4. Interactive Live Model Explorer Drawer -->
	<div class="vmsai-explorer-drawer" style="margin-top:24px;">
		<div class="vmsai-explorer-drawer__head" id="vmsai-toggle-explorer">
			<div style="display:flex; align-items:center; gap:10px;">
				<span style="font-size:16px;">🔍</span>
				<div>
					<strong style="color:#fff; font-size:13px;">Live Model Catalogue Explorer</strong>
					<span style="color:var(--muted); font-size:11px; margin-left:8px;">(<?php echo count( $all_synced_models ); ?> models synced from OmniRoute)</span>
				</div>
			</div>
			<span style="color:var(--gold); font-size:11px; font-weight:600;" id="vmsai-explorer-indicator">▼ Click to Search & Pick Active Models</span>
		</div>

		<div class="vmsai-explorer-body" id="vmsai-explorer-panel">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; flex-wrap:wrap;">
				<div class="vmsai-cat-filter" style="display:flex; gap:8px;">
					<input type="search" id="vmsai-cat-search" class="vmsai-input" placeholder="Search models by name or id (e.g. grok, gemini, flux, deepseek)..." style="min-width:320px;">
					<button type="button" class="vmsai-btn vmsai-btn--sm is-active" data-vmsai-filter="all">All</button>
					<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-filter="text">Text</button>
					<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-filter="image">Image</button>
					<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-filter="video">Video</button>
				</div>
				<button type="button" class="vmsai-btn vmsai-btn--sm" data-vmsai-action="sync-models">
					🔄 Refresh Sync
				</button>
			</div>

			<div style="max-height: 420px; overflow-y: auto; border: 1px solid var(--hairline); border-radius: 8px;">
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
								<td><span class="vmsai-engine-card__tag" style="font-size:9px; background:rgba(255,255,255,0.06); color:var(--muted);"><?php echo esc_html( strtoupper( $mod ) ); ?></span></td>
								<td><code><?php echo esc_html( $mid ); ?></code></td>
								<td><strong><?php echo esc_html( $lbl ); ?></strong></td>
								<td><?php echo esc_html( $m['provider'] ?? 'omniroute' ); ?></td>
								<td>
									<button type="button" class="vmsai-btn vmsai-btn--sm vmsai-btn-use-model"
										data-modality="<?php echo esc_attr( $mod ); ?>"
										data-model-id="<?php echo esc_attr( $mid ); ?>"
										data-model-label="<?php echo esc_attr( $lbl ); ?>"
										style="padding:3px 10px; font-size:11px;">
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

	<!-- 5. Network Resiliency & Global Save -->
	<div style="display:flex; justify-content:space-between; align-items:center; padding:18px 24px; background:var(--panel); border:1px solid var(--hairline); border-radius:12px;">
		<div style="display:flex; gap:20px; align-items:center;">
			<div class="vmsai-field" style="margin:0;">
				<label for="vmsai-timeout" style="font-size:11px; margin-bottom:2px; display:block;">Timeout per Request</label>
				<input type="number" id="vmsai-timeout" name="request_timeout" min="15" max="600" value="<?php echo esc_attr( VMSAI_Settings::get( 'request_timeout', 90 ) ); ?>" style="width:100px; font-size:12px;">
				<span style="font-size:10px; color:var(--muted);">seconds</span>
			</div>
			<div class="vmsai-field" style="margin:0;">
				<label for="vmsai-attempts" style="font-size:11px; margin-bottom:2px; display:block;">Max Retry Attempts</label>
				<input type="number" id="vmsai-attempts" name="max_attempts" min="1" max="10" value="<?php echo esc_attr( VMSAI_Settings::get( 'max_attempts', 3 ) ); ?>" style="width:100px; font-size:12px;">
				<span style="font-size:10px; color:var(--muted);">tries</span>
			</div>
		</div>

		<button type="submit" class="vmsai-btn vmsai-btn--gold" style="padding: 12px 40px; font-weight:700; font-size:14px; box-shadow:0 4px 14px rgba(201,162,39,0.3);">
			💾 Save All AI Engines & Settings
		</button>
	</div>
</form>

<script>
(function($) {
	// Collapsible Explorer Drawer
	$('#vmsai-toggle-explorer').on('click', function() {
		var $panel = $('#vmsai-explorer-panel');
		$panel.toggleClass('is-open');
		var isOpen = $panel.hasClass('is-open');
		$('#vmsai-explorer-indicator').text(isOpen ? '▲ Hide Model Catalogue' : '▼ Click to Search & Pick Active Models');
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
			if (window.VMSAI && VMSAI.toast) VMSAI.toast('Active Text model set to ' + lbl);
		} else if (mod === 'image') {
			var $select = $('#vmsai-omni-img-model');
			if ($select.find('option[value="' + mid + '"]').length === 0) {
				$select.append(new Option(lbl, mid, true, true));
			} else {
				$select.val(mid);
			}
			if (window.VMSAI && VMSAI.toast) VMSAI.toast('Active Image engine set to ' + lbl);
		} else if (mod === 'video') {
			var $select = $('#vmsai-omni-vid-model');
			if ($select.find('option[value="' + mid + '"]').length === 0) {
				$select.append(new Option(lbl, mid, true, true));
			} else {
				$select.val(mid);
			}
			if (window.VMSAI && VMSAI.toast) VMSAI.toast('Active Video engine set to ' + lbl);
		}

		// Smooth scroll to top cards
		$('html, body').animate({ scrollTop: $('.vmsai-modality-triad').offset().top - 80 }, 300);
	});
})(jQuery);
</script>
