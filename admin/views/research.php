<?php
/**
 * Trend Scouting Desk.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;
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
	var esc = VMSAI.esc;

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
							'<h4>' + esc(t.title) + '</h4>' +
							'<p>' + esc(t.full) + '</p>' +
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
