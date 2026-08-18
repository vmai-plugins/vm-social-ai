<?php
/**
 * Trend Scouting Desk.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! VMSAI_License::has_feature( 'news_jacking' ) ) :
?>
	<section class="vmsai-panel" style="text-align: center; padding: 120px 40px; background: linear-gradient(135deg, #14141a, #0b0b0f); border: 1px solid var(--gold); border-radius: 16px; position: relative; overflow: hidden;">
		<div style="position: absolute; top: -50px; right: -50px; font-size: 200px; opacity: 0.05; transform: rotate(15deg);">🗞️</div>
		<div style="font-size: 60px; margin-bottom: 20px; filter: drop-shadow(0 0 15px var(--gold));">⚡</div>
		<h2 class="vmsai-display" style="font-size: 32px;">Real-Time News-Jacking Locked</h2>
		<p class="vmsai-lede" style="margin: 0 auto 30px; max-width: 500px;">Tavily-powered industry scouting and instant trend injection into your calendar is a Pro feature designed to keep you at the center of the conversation.</p>
		<div style="display: flex; gap: 15px; justify-content: center;">
			<a href="<?php echo esc_url( VMSAI_Admin::upgrade_url() ); ?>" class="vmsai-btn vmsai-btn--gold" style="padding: 15px 40px; font-size: 16px;">Activate Pro Access</a>
		</div>
	</section>
<?php
	return;
endif;
?>

<style>
	.vmsai-research-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
	.vmsai-trend-card-pro {
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 12px;
		padding: 25px; display: flex; flex-direction: column; justify-content: space-between;
		transition: all 0.3s ease; position: relative;
	}
	.vmsai-trend-card-pro:hover { border-color: var(--gold); transform: translateY(-5px); }
	.vmsai-trend-card-pro h4 { margin: 0 0 15px; font-size: 15px; line-height: 1.5; color: var(--parchment); }
	.vmsai-trend-card-pro p { font-size: 13px; color: var(--muted); margin-bottom: 20px; flex-grow: 1; }
</style>

<div class="vmsai-panel">
	<div class="vmsai-panel__head">
		<div>
			<h2 class="vmsai-display">Trend Scouting Desk</h2>
			<p class="vmsai-lede">Real-time industry signals scouted via Tavily and Google Grounding. Click to "News-Jack" any trend.</p>
		</div>
		<button class="vmsai-btn" data-vmsai-action="refresh-research">🔄 Scout Now</button>
	</div>

	<div id="vmsai-research-trends" class="vmsai-research-grid" style="margin-top: 30px;">
		<div style="grid-column: 1 / -1; text-align: center; padding: 60px 0; color: var(--muted);">
			<p>Initializing scouting desk...</p>
		</div>
	</div>
</div>

<script>
(function($) {
	function refreshResearch() {
		var $grid = $('#vmsai-research-trends');
		$grid.html('<div style="grid-column: 1 / -1; text-align: center; padding: 60px 0; color: var(--muted);"><p>Scanning industry signals...</p></div>');

		VMSAI.api('/research/trends', 'GET').then( function(res) {
			if (!res.ok) return;

			$grid.empty();
			if (res.trends.length === 0) {
				$grid.append('<div style="grid-column: 1 / -1; text-align: center; padding: 60px 0; color: var(--muted);"><p>No trends found. Connect Tavily or enable Gemini Grounding.</p></div>');
				return;
			}

			res.trends.forEach( function(t) {
				$grid.append(
					'<div class="vmsai-trend-card-pro">' +
						'<div>' +
							'<div class="vmsai-pill vmsai-pill--good" style="margin-bottom: 15px; display: inline-block;">NEW SIGNAL</div>' +
							'<h4>' + t.title + '</h4>' +
							'<p>' + t.full + '</p>' +
						'</div>' +
						'<button class="vmsai-btn vmsai-btn--gold" data-vmsai-action="inject-trend" data-trend="' + btoa(t.full) + '">⚡ News-Jack This</button>' +
					'</div>'
				);
			});
		});
	}

	$(document).on('click', '[data-vmsai-action="inject-trend"]', function() {
		var $btn = $(this);
		var trend = atob($btn.data('trend'));

		$btn.text('Injecting...').prop('disabled', true);

		VMSAI.api('/research/inject', 'POST', { trend: trend }).then( function(res) {
			if (res.ok) {
				VMSAI.toast('Injected into tomorrow\'s schedule.');
				$btn.text('Injected ✅');
			} else {
				alert(res.message);
				$btn.text('Try again').prop('disabled', false);
			}
		});
	});

	$(document).on('click', '[data-vmsai-action="refresh-research"]', refreshResearch);
	$(document).ready(refreshResearch);
})(jQuery);
</script>
