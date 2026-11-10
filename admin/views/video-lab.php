<?php
/**
 * Video Lab — Cinematic Preview and Generation.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;
?>

<style>
	.vmsai-video-grid { display: grid; grid-template-columns: 1fr 340px; gap: var(--gap); }
	.vmsai-video-preview-wrapper {
		background: #000; border-radius: 20px; aspect-ratio: 9/16; max-height: 750px;
		display: grid; place-items: center; position: relative; overflow: hidden;
		border: 1px solid var(--hairline); box-shadow: 0 30px 60px rgba(0,0,0,0.8);
		background: radial-gradient(circle at center, #1a1a1a 0%, #000 100%);
	}
	.vmsai-video-preview-wrapper video { width: 100%; height: 100%; object-fit: cover; }

	.vmsai-lab-status {
		position: absolute; top: 20px; left: 20px; z-index: 10;
		background: rgba(0,0,0,0.6); padding: 5px 12px; border-radius: 20px;
		font-size: 10px; font-weight: bold; letter-spacing: 1px; color: var(--gold);
		border: 1px solid var(--gold); backdrop-filter: blur(5px);
	}

	.vmsai-template-card {
		background: var(--raised); border: 1px solid var(--hairline); border-radius: 12px;
		padding: 20px; margin-bottom: 20px; cursor: pointer; transition: all 0.2s ease;
	}
	.vmsai-template-card:hover { border-color: var(--gold); transform: translateX(5px); }
	.vmsai-template-card.is-active { border-color: var(--gold); background: rgba(201, 162, 39, 0.05); box-shadow: 0 0 20px rgba(201, 162, 39, 0.1); }
	.vmsai-template-card h4 { margin: 0 0 5px; font-size: 14px; color: var(--parchment); }
	.vmsai-template-card p { margin: 0; font-size: 11px; color: var(--muted); line-height: 1.4; }

	.vmsai-provider-card {
		display: flex; align-items: center; gap: 12px; padding: 12px;
		background: var(--ink); border: 1px solid var(--hairline); border-radius: 8px;
		margin-bottom: 10px; cursor: pointer; transition: all 0.2s ease;
	}
	.vmsai-provider-card:hover { border-color: var(--muted); }
	.vmsai-provider-card.is-active { border-color: var(--gold); background: rgba(201, 162, 39, 0.05); }
	.vmsai-provider-info { flex-grow: 1; }
	.vmsai-provider-name { display: block; font-size: 13px; font-weight: 600; color: var(--parchment); }
	.vmsai-provider-type { font-size: 10px; text-transform: uppercase; color: var(--muted); letter-spacing: 0.5px; }
	.vmsai-provider-badge { font-size: 9px; padding: 2px 6px; border-radius: 4px; background: var(--hairline); color: var(--muted); }
	.vmsai-provider-badge.is-free { background: rgba(111, 168, 138, 0.1); color: var(--green); }
</style>

<div class="vmsai-video-grid">
	<div class="vmsai-video-main">
		<section class="vmsai-panel">
			<div class="vmsai-panel__head">
				<div>
					<h2 class="vmsai-display">Cinematic Video Lab</h2>
					<p class="vmsai-lede">High-fidelity production suite for viral social media assets.</p>
				</div>
			</div>

			<div class="vmsai-field vmsai-field--wide">
				<label>Concept / Subject</label>
				<input type="text" id="vmsai-lab-video-prompt" placeholder="e.g. A high-end espresso machine pouring coffee in slow motion..." style="width: 100%; margin-bottom: 20px; font-size: 16px; padding: 15px;">
			</div>

			<div id="vmsai-lab-video-viewport" class="vmsai-video-preview-wrapper">
				<div class="vmsai-lab-status">IDLE</div>
				<div style="color: var(--muted); font-size: 12px;" id="vmsai-lab-placeholder">Visualizing concept...</div>
			</div>

			<p class="vmsai-hint" style="text-align: center; margin-top: 20px;">
				<button class="vmsai-btn vmsai-btn--gold" style="padding: 12px 40px; font-size: 14px;" data-vmsai-action="lab-generate-video">🚀 Produce Elite Asset</button>
			</p>
		</section>
	</div>

	<div class="vmsai-video-side">
		<section class="vmsai-panel">
			<h3 class="vmsai-subhead" style="margin-top:0;">Video Engine</h3>
			<div id="vmsai-video-providers">
				<?php
				$providers = array(
					'omniroute'    => array( 'label' => 'OmniRoute AI (Veo/Seedance)', 'type' => 'Free OmniRoute Hub', 'badge' => 'RECOMMENDED' ),
					'pexels'       => array( 'label' => 'Pexels Motion Stock', 'type' => 'Instant Stock Footage', 'badge' => 'INSTANT' ),
					'comfyui'      => array( 'label' => 'ComfyUI (Laptop 2 / RTX 3060)', 'type' => 'Local GPU AnimateDiff', 'badge' => 'GPU NODE' ),
					'pollinations' => array( 'label' => 'Pollinations AI', 'type' => 'Keyless Experimental', 'badge' => 'FREE' ),
					'aipuffer'     => array( 'label' => 'AI Puffer (Veo)', 'type' => 'AIPKit Multi-Model', 'badge' => 'ACTIVE' ),
					'cogvideox'    => array( 'label' => 'CogVideoX', 'type' => 'Hugging Face API', 'badge' => 'FREE' ),
					'svd'          => array( 'label' => 'Stable Video Diffusion', 'type' => 'Local GPU Node', 'badge' => 'LOCAL' ),
					'minimax'      => array( 'label' => 'Minimax (Hailuo)', 'type' => 'Cinematic Motion', 'badge' => 'API KEY' ),
					'luma'         => array( 'label' => 'Luma Dream Machine', 'type' => 'Dream Machine Video', 'badge' => 'API KEY' ),
				);
				$current_provider = VMSAI_Settings::get( 'video_source', 'omniroute' );
				foreach ( $providers as $slug => $p ) :
				?>
					<div class="vmsai-provider-card <?php echo $slug === $current_provider ? 'is-active' : ''; ?>" data-provider="<?php echo esc_attr($slug); ?>">
						<div class="vmsai-provider-info">
							<span class="vmsai-provider-name"><?php echo esc_html($p['label']); ?></span>
							<span class="vmsai-provider-type"><?php echo esc_html($p['type']); ?></span>
						</div>
						<span class="vmsai-provider-badge is-free"><?php echo esc_html($p['badge']); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="vmsai-panel">
			<h3 class="vmsai-subhead" style="margin-top:0;">Directorial Styles</h3>
			<div id="vmsai-video-templates" style="max-height: 400px; overflow-y: auto; margin-bottom: 20px;">
				<?php foreach ( VMSAI_Video_Engine::templates() as $slug => $tpl ) : ?>
					<div class="vmsai-template-card <?php echo $slug === 'cinematic_product' ? 'is-active' : ''; ?>" data-template="<?php echo esc_attr($slug); ?>">
						<h4><?php echo esc_html($tpl['label']); ?></h4>
						<p><?php echo esc_html($tpl['prompt']); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="vmsai-panel">
			<h3 class="vmsai-subhead" style="margin-top:0;">Audio Vibe</h3>
			<select id="vmsai-video-audio-track" style="width: 100%;">
				<option value="none">No Audio (Silent)</option>
				<option value="lofi">Lofi Street Beats</option>
				<option value="corporate">Modern Upbeat</option>
				<option value="nature">Natural Ambience</option>
				<option value="tech">Tech Pulse</option>
			</select>

			<div class="vmsai-audio-visualizer">
				<?php for($i=0;$i<24;$i++): ?>
					<div class="vmsai-audio-bar" style="height: <?php echo rand(10, 80); ?>%;"></div>
				<?php endfor; ?>
			</div>
		</section>
	</div>
</div>

<script>
(function($) {
	var activeTemplate = 'cinematic_product';
	var activeProvider = $('.vmsai-provider-card.is-active').data('provider') || 'omniroute';

	$(document).on('click', '.vmsai-template-card', function() {
		$('.vmsai-template-card').removeClass('is-active');
		$(this).addClass('is-active');
		activeTemplate = $(this).data('template');
	});

	$(document).on('click', '.vmsai-provider-card', function() {
		$('.vmsai-provider-card').removeClass('is-active');
		$(this).addClass('is-active');
		activeProvider = $(this).data('provider');

		// Visual cue for deployment health
		if (activeProvider === 'svd' || activeProvider === 'cogvideox') {
			VMSAI.toast('Ensure API keys are set in Settings > AI Engines for this provider.', true);
		}
	});

	$(document).on('click', '[data-vmsai-action="lab-generate-video"]', function() {
		var $btn = $(this);
		var prompt = $('#vmsai-lab-video-prompt').val();
		var $viewport = $('#vmsai-lab-video-viewport');
		var $status = $('.vmsai-lab-status');

		if (!prompt) {
			if (window.VMSAI && VMSAI.toast) {
				VMSAI.toast('Please enter a concept first.', true);
			} else {
				alert('Please enter a concept first.');
			}
			return;
		}

		$btn.text('Producing Asset...').prop('disabled', true);
		$status.text('PRODUCING...').css('color', 'var(--gold)');
		$('#vmsai-lab-placeholder').show().html('<div style="text-align:center"><div class="vmsai-spinner" style="margin: 0 auto 10px;"></div>Rendering cinematic asset via ' + $('[data-provider="' + activeProvider + '"] .vmsai-provider-name').text() + '...<br><span style="font-size:11px;color:var(--muted)">Synthesizing video motion frames...</span></div>');

		VMSAI.api('/engine/test-video', 'POST', {
			prompt: prompt,
			template: activeTemplate,
			provider: activeProvider
		}).then( function(res) {
			$btn.text('🚀 Produce Elite Asset').prop('disabled', false);

			if (res.ok) {
				$status.text('LIVE').css('color', 'var(--green)');
				$('#vmsai-lab-placeholder').hide();
				$viewport.html('<div class="vmsai-lab-status" style="color:var(--green); border-color:var(--green);">LIVE</div><video controls autoplay loop playsinline style="width:100%;height:100%;object-fit:cover;"><source src="' + res.url + '" type="video/mp4"></video>');
				if (window.VMSAI && VMSAI.toast) {
					VMSAI.toast('Video created successfully!');
				}
			} else {
				$status.text('FAILED').css('color', 'var(--red)');
				var errMsg = res.message || 'Video generation failed.';
				$('#vmsai-lab-placeholder').html('<div style="color:var(--red);padding:24px;text-align:center;"><div style="font-size:24px;margin-bottom:8px;">⚠️</div><strong style="color:var(--parchment);font-size:13px;">' + errMsg + '</strong><p style="font-size:11px;color:var(--muted);margin-top:10px;line-height:1.5;">Selected provider encountered an issue. Try selecting <strong>OmniRoute AI</strong> or <strong>Pexels Stock</strong> from the Video Engine sidebar for instant generation.</p></div>');
				if (window.VMSAI && VMSAI.toast) {
					VMSAI.toast(errMsg, true);
				}
			}
		}).catch( function(err) {
			$btn.text('🚀 Produce Elite Asset').prop('disabled', false);
			$status.text('FAILED').css('color', 'var(--red)');
			$('#vmsai-lab-placeholder').html('<div style="color:var(--red);padding:20px;text-align:center;">Network error communicating with video engine API.</div>');
			if (window.VMSAI && VMSAI.toast) {
				VMSAI.toast('Network error communicating with video engine.', true);
			}
		});
	});
})(jQuery);
</script>
