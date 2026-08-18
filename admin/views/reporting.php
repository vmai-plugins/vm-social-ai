<?php
/**
 * Executive Reporting.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;
?>

<style>
	.vmsai-report-body { background: var(--panel); padding: 40px; border-radius: 16px; border: 1px solid var(--hairline); line-height: 1.8; }
	.vmsai-report-meta { display: flex; gap: 30px; margin-bottom: 40px; border-bottom: 1px solid var(--hairline); padding-bottom: 20px; }
	.vmsai-report-stat { flex: 1; }
	.vmsai-report-stat dt { font-size: 10px; text-transform: uppercase; color: var(--muted); letter-spacing: 1px; margin-bottom: 5px; }
	.vmsai-report-stat dd { font-family: var(--serif); font-size: 24px; color: var(--gold); margin: 0; }

	.vmsai-executive-summary { font-size: 15px; color: #ccc; }
	.vmsai-executive-summary p { margin-bottom: 20px; }
</style>

<div class="vmsai-panel">
	<div class="vmsai-panel__head">
		<div>
			<h2 class="vmsai-display">Executive ROI Synthesis</h2>
			<p class="vmsai-lede">Performance data synthesized into a strategic briefing for leadership.</p>
		</div>
		<button class="vmsai-btn vmsai-btn--gold" data-vmsai-action="generate-report">✨ Generate Synthesis</button>
	</div>

	<div id="vmsai-reporting-container" style="margin-top: 30px;">
		<div style="text-align: center; padding: 60px 0; color: var(--muted);" id="vmsai-reporting-empty">
			<div style="font-size: 40px; margin-bottom: 20px;">📊</div>
			<p>Click "Generate Synthesis" to start AI performance analysis.</p>
		</div>

		<div class="vmsai-report-body" id="vmsai-reporting-content" style="display:none;">
			<div class="vmsai-report-meta">
				<dl class="vmsai-report-stat">
					<dt>Total Visibility</dt>
					<dd id="vmsai-rep-views">0</dd>
				</dl>
				<dl class="vmsai-report-stat">
					<dt>Engagement Rate</dt>
					<dd id="vmsai-rep-eng">0%</dd>
				</dl>
				<dl class="vmsai-report-stat">
					<dt>Direct Conversions</dt>
					<dd id="vmsai-rep-clicks">0</dd>
				</dl>
				<dl class="vmsai-report-stat">
					<dt>Campaign Pace</dt>
					<dd id="vmsai-rep-pace">0%</dd>
				</dl>
			</div>

			<div class="vmsai-executive-summary" id="vmsai-rep-text">
				<!-- AI report text -->
			</div>
		</div>
	</div>
</div>

<script>
(function($) {
	function generateReport(button) {
		var $btn = $(button);
		var label = $btn.text();

		$btn.text('Synthesizing Data...').prop('disabled', true);
		$('#vmsai-reporting-empty').hide();
		$('#vmsai-reporting-content').hide();

		VMSAI.api('/reporting/roi', 'GET').then( function(res) {
			$btn.text(label).prop('disabled', false);

			if (!res.ok) {
				alert(res.message);
				$('#vmsai-reporting-empty').show();
				return;
			}

			$('#vmsai-rep-views').text(new Intl.NumberFormat().format(res.stats.views));
			$('#vmsai-rep-eng').text(((res.stats.engagements / Math.max(1, res.stats.views)) * 100).toFixed(1) + '%');
			$('#vmsai-rep-clicks').text(new Intl.NumberFormat().format(res.stats.clicks));
			$('#vmsai-rep-pace').text(res.stats.progress + '%');

			// Format paragraphs
			var paras = res.report.split("\n\n").map( function(p) { return '<p>' + p + '</p>'; } ).join("");
			$('#vmsai-rep-text').html(paras);

			$('#vmsai-reporting-content').fadeIn();
		});
	}

	$(document).on('click', '[data-vmsai-action="generate-report"]', function() {
		generateReport(this);
	});
})(jQuery);
</script>
