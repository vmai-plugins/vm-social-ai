<?php
/**
 * Image Lab — High-End Visual Laboratory.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_agents = VMSAI_Agents::registry();
?>

<div class="vmsai-lab">
	<section class="vmsai-panel">
		<div class="vmsai-panel__head">
			<div>
				<h2 class="vmsai-display"><?php esc_html_e( 'Visual DNA Laboratory', 'vm-social-ai-pro' ); ?></h2>
				<p class="vmsai-lede"><?php esc_html_e( 'Experiment with specialized Agents and their unique Visual DNA before automating your schedule.', 'vm-social-ai-pro' ); ?></p>
			</div>
		</div>

		<div class="vmsai-lab__grid" style="display: grid; grid-template-columns: 350px 1fr; gap: 30px; margin-top: 30px;">
			<!-- Control Panel -->
			<div class="vmsai-lab__controls">
				<div class="vmsai-fields">
					<div class="vmsai-field">
						<label><?php esc_html_e( 'Select Agent', 'vm-social-ai-pro' ); ?></label>
						<div class="vmsai-agent-selector" style="display: grid; gap: 10px;">
							<?php foreach ( $vmsai_agents as $slug => $agent ) : ?>
								<label class="vmsai-agent-card" style="background: var(--raised); padding: 15px; border-radius: 12px; border: 1px solid var(--hairline); cursor: pointer; transition: all 0.2s ease;">
									<input type="radio" name="lab_agent" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, 'storyteller' ); ?> style="display: none;">
									<div style="font-weight: bold; color: var(--gold);"><?php echo esc_html( $agent['label'] ); ?></div>
									<div style="font-size: 10px; color: var(--muted); margin-top: 5px;"><?php echo esc_html( $agent['style'] ); ?></div>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="vmsai-field">
						<label><?php esc_html_e( 'Subject / Topic', 'vm-social-ai-pro' ); ?></label>
						<textarea id="vmsai-lab-topic" rows="3" placeholder="e.g. A futuristic coffee shop in Kanban, India..."></textarea>
					</div>

					<div class="vmsai-field">
						<label><?php esc_html_e( 'Destination Canvas', 'vm-social-ai-pro' ); ?></label>
						<select id="vmsai-lab-channel">
							<option value="instagram">Instagram Portrait (1080x1350)</option>
							<option value="facebook">Facebook Landscape (1200x630)</option>
							<option value="youtube">YouTube Short (1080x1920)</option>
						</select>
					</div>

					<button type="button" class="vmsai-btn vmsai-btn--gold" id="vmsai-lab-generate" style="width: 100%; padding: 15px; font-size: 16px;">
						<?php esc_html_e( 'Run Visual Experiment', 'vm-social-ai-pro' ); ?>
					</button>
				</div>
			</div>

			<!-- Result Display -->
			<div class="vmsai-lab__preview" style="background: #000; border-radius: 16px; border: 1px solid var(--hairline); display: grid; place-items: center; min-height: 500px; position: relative; overflow: hidden;">
				<div id="vmsai-lab-output" style="width: 100%; height: 100%; display: grid; place-items: center;">
					<div style="text-align: center; color: var(--muted);">
						<div style="font-size: 40px; margin-bottom: 20px;">🔬</div>
						<p>Result will appear here...</p>
					</div>
				</div>

				<div id="vmsai-lab-loader" style="display: none; position: absolute; inset: 0; background: rgba(0,0,0,0.8); z-index: 10; place-items: center; flex-direction: column; gap: 20px; color: #fff;">
					<span class="vmsai-pulse-dot" style="width: 20px; height: 20px;"></span>
					<div style="font-family: var(--serif); font-style: italic;">Agent is dreaming...</div>
				</div>
			</div>
		</div>
	</section>
</div>

<style>
	.vmsai-agent-card:has(input:checked) { border-color: var(--gold); box-shadow: 0 0 15px rgba(201, 162, 39, 0.2); background: rgba(201, 162, 39, 0.05); }
	.vmsai-lab__preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
</style>

<script>
(function($) {
	$(document).on('click', '#vmsai-lab-generate', function() {
		var $btn = $(this);
		var $output = $('#vmsai-lab-output');
		var $loader = $('#vmsai-lab-loader');

		var agent = $('input[name="lab_agent"]:checked').val();
		var topic = $('#vmsai-lab-topic').val();
		var channel = $('#vmsai-lab-channel').val();

		if (!topic) {
			alert('Please enter a topic.');
			return;
		}

		$loader.css('display', 'grid');
		$btn.prop('disabled', true);

		VMSAI.api('/lab/generate-image', 'POST', {
			agent: agent,
			topic: topic,
			channel: channel
		}).then( function(res) {
			$loader.hide();
			$btn.prop('disabled', false);

			if (res.ok) {
				$output.html('<img src="' + res.url + '" style="width:100%; height:100%; object-fit:contain;">');
			} else {
				$output.html('<div style="color:var(--red); padding:40px;">Experiment Failed: ' + res.error + '</div>');
			}
		}).catch( function(err) {
			$loader.hide();
			$btn.prop('disabled', false);
			$output.html('<div style="color:var(--red); padding:40px;">System Error</div>');
		});
	});
})(jQuery);
</script>
