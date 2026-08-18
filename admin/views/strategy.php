<?php
/**
 * Strategy Hub.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! VMSAI_License::has_feature( 'strategy_hub' ) ) :
?>
	<section class="vmsai-panel" style="text-align: center; padding: 120px 40px; background: linear-gradient(135deg, #14141a, #0b0b0f); border: 1px solid var(--gold); border-radius: 16px; position: relative; overflow: hidden;">
		<div style="position: absolute; top: -50px; right: -50px; font-size: 200px; opacity: 0.05; transform: rotate(15deg);">🎯</div>
		<div style="font-size: 60px; margin-bottom: 20px; filter: drop-shadow(0 0 15px var(--gold));">🔒</div>
		<h2 class="vmsai-display" style="font-size: 32px;">Strategic Intelligence is Locked</h2>
		<p class="vmsai-lede" style="margin: 0 auto 30px; max-width: 500px;">The Keyword Heatmap, Pillar Gap Analysis, and Trend Scouting are Pro features designed for high-growth businesses ready to dominate their niche.</p>
		<div style="display: flex; gap: 15px; justify-content: center;">
			<a href="<?php echo esc_url( VMSAI_Admin::upgrade_url() ); ?>" class="vmsai-btn vmsai-btn--gold" style="padding: 15px 40px; font-size: 16px;">View Pro Pricing</a>
		</div>
	</section>
<?php
	return;
endif;
?>

<style>
	.vmsai-strat-grid { display: grid; grid-template-columns: 2fr 1fr; gap: var(--gap); }
	.vmsai-heatmap { display: flex; flex-wrap: wrap; gap: 10px; padding: 20px; background: var(--ink); border-radius: 8px; }
	.vmsai-heatmap-item {
		padding: 8px 15px; border-radius: 4px; border: 1px solid var(--hairline);
		font-size: 13px; transition: all 0.2s ease;
	}
	.vmsai-heatmap-item.score-low { opacity: 0.5; border-color: var(--hairline); }
	.vmsai-heatmap-item.score-mid { background: rgba(201, 162, 39, 0.1); border-color: var(--gold); color: var(--gold); }
	.vmsai-heatmap-item.score-high { background: var(--gold); color: var(--ink); font-weight: bold; }

	.vmsai-gap-row { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
	.vmsai-gap-label { width: 140px; font-size: 12px; font-weight: 600; color: var(--muted); }
	.vmsai-gap-bar-wrap { flex-grow: 1; height: 8px; background: var(--ink); border-radius: 4px; overflow: hidden; position: relative; }
	.vmsai-gap-bar-target { position: absolute; top:0; height: 100%; border-right: 2px solid #fff; z-index: 2; opacity: 0.3; }
	.vmsai-gap-bar-actual { height: 100%; border-radius: 4px; transition: width 0.8s ease; }
	.vmsai-gap-status { width: 80px; text-align: right; font-size: 10px; font-weight: bold; text-transform: uppercase; }

	/* Benchmarking */
	.vmsai-bench-row { display: flex; align-items: center; gap: 15px; margin-bottom: 12px; }
	.vmsai-bench-name { width: 120px; font-size: 11px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.vmsai-bench-bar-wrap { flex-grow: 1; height: 16px; background: var(--ink); border-radius: 4px; overflow: hidden; position: relative; border: 1px solid var(--hairline); }
	.vmsai-bench-bar { height: 100%; transition: width 1s ease; border-radius: 4px; }
	.vmsai-bench-val { width: 60px; font-size: 10px; font-weight: bold; color: var(--muted); text-align: right; }

	.vmsai-trend-card { background: var(--raised); padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 3px solid var(--gold); }
</style>

<div class="vmsai-strat-grid">
	<div class="vmsai-strat-main">
		<section class="vmsai-panel">
			<div class="vmsai-panel__head">
				<div>
					<h2 class="vmsai-display">Keyword Lab</h2>
					<p class="vmsai-lede">Visualizing your SEO footprint. High-intensity blocks show keywords currently being "owned" in your feed.</p>
				</div>
				<button class="vmsai-btn" data-vmsai-action="refresh-strategy">🔄 Audit Now</button>
			</div>

			<div id="vmsai-strategy-heatmap" class="vmsai-heatmap">
				<p class="vmsai-muted">Initializing strategic audit...</p>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-display">Pillar Gap Analysis</h2>
			<p class="vmsai-lede">The white tick shows your target mix. The bars show what the AI is actually producing.</p>

			<div id="vmsai-strategy-gaps" style="margin-top: 30px;">
				<!-- Gaps injected here -->
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-display">Competitor Benchmark</h2>
			<p class="vmsai-lede">Comparing your estimated footprint against industry rivals.</p>

			<div id="vmsai-strategy-benchmark" style="margin-top: 30px;">
				<!-- Benchmark injected here -->
			</div>
		</section>
	</div>

	<div class="vmsai-strat-side">
		<section class="vmsai-panel">
			<div class="vmsai-panel__head">
				<h3 class="vmsai-subhead" style="margin-top:0;">Trend Scouting</h3>
				<button class="vmsai-btn vmsai-btn--quiet" style="padding: 2px 8px; font-size: 10px;" data-vmsai-action="scout-trends">🔍 Scout Live</button>
			</div>
			<p class="vmsai-hint">Real-time signals from your industry feeds and the live web.</p>

			<div id="vmsai-strategy-trends">
				<!-- Trends injected here -->
			</div>
		</section>

		<section class="vmsai-panel" style="background: linear-gradient(135deg, var(--gold), var(--gold-soft)); border: none;">
			<h3 class="vmsai-subhead" style="margin-top:0; color: var(--ink); border-color: rgba(0,0,0,0.1);">Strategy Insight</h3>
			<p style="color: var(--ink); font-weight: 500; font-size: 13px;" id="vmsai-strategy-insight">
				Analyzing your content trajectory...
			</p>
		</section>
	</div>
</div>

<script>
(function($) {
	// Trend text originates from RSS feeds and live web search — it is not
	// ours and must never be dropped into HTML unescaped.
	var esc = VMSAI.esc;

	function refreshStrategy() {
		var $heatmap = $('#vmsai-strategy-heatmap');
		var $gaps = $('#vmsai-strategy-gaps');
		var $trends = $('#vmsai-strategy-trends');
		var $insight = $('#vmsai-strategy-insight');
		var $bench = $('#vmsai-strategy-benchmark');

		VMSAI.api('/strategy/audit', 'GET').then( function(res) {
			if (!res.ok) {
				$heatmap.html('<p class="vmsai-muted">' + esc(res.error || 'The audit could not run.') + '</p>');
				$insight.text(res.error || 'The audit could not run. Check the Logs tab.');
				return;
			}

			// Heatmap
			$heatmap.empty();
			(res.keywords || []).forEach( function(kw) {
				var cls = 'score-low';
				if (kw.score > 70) cls = 'score-high';
				else if (kw.score > 30) cls = 'score-mid';

				$heatmap.append('<span class="vmsai-heatmap-item ' + cls + '" title="' + esc(kw.count) + ' mentions in 30 days">' + esc(kw.keyword) + '</span>');
			});

			if (!(res.keywords || []).length) {
				$heatmap.append('<p class="vmsai-muted">No target keywords set. Add them in the Brain.</p>');
			}

			// Gaps
			$gaps.empty();
			(res.gaps || []).forEach( function(g) {
				var color = 'var(--muted)';
				if (g.status === 'neglected') color = 'var(--red)';
				if (g.status === 'balanced') color = 'var(--green)';
				if (g.status === 'over-saturated') color = 'var(--gold)';

				$gaps.append(
					'<div class="vmsai-gap-row">' +
						'<div class="vmsai-gap-label">' + esc(g.pillar) + '</div>' +
						'<div class="vmsai-gap-bar-wrap">' +
							'<div class="vmsai-gap-bar-target" style="left: ' + (parseInt(g.target, 10) || 0) + '%"></div>' +
							'<div class="vmsai-gap-bar-actual" style="width: ' + (parseInt(g.actual, 10) || 0) + '%; background: ' + color + '"></div>' +
						'</div>' +
						'<div class="vmsai-gap-status" style="color: ' + color + '">' + esc(g.status) + '</div>' +
					'</div>'
				);
			});

			// Benchmarking
			$bench.empty();
			var bench = res.benchmark || [];
			if (bench.length > 0) {
				var reaches = bench.map( function(b) { return parseInt(b.reach, 10) || 0; } );
				reaches.push(1);
				var maxReach = Math.max.apply( null, reaches );

				bench.forEach( function(b) {
					var reach = parseInt(b.reach, 10) || 0;
					var width = b.unknown ? 0 : (reach / maxReach) * 100;
					var color = b.is_me ? 'var(--gold)' : 'var(--hairline)';
					var value = b.unknown ? 'no data' : VMSAI.number(reach);
					$bench.append(
						'<div class="vmsai-bench-row">' +
							'<div class="vmsai-bench-name" title="' + esc(b.name) + '">' + esc(b.name) + '</div>' +
							'<div class="vmsai-bench-bar-wrap">' +
								'<div class="vmsai-bench-bar" style="width: ' + width + '%; background: ' + color + ';"></div>' +
							'</div>' +
							'<div class="vmsai-bench-val">' + esc(value) + '</div>' +
						'</div>'
					);
				});
				$bench.append('<p class="vmsai-hint" style="margin-top:15px;">Your own figure is an estimate from published volume. Competitor figures are only shown when a public number could actually be found.</p>');
			} else {
				$bench.append('<p class="vmsai-muted">No competitors listed in the Brain. Add some to see comparative data.</p>');
			}

			// Trends
			$trends.empty();
			var trends = res.trends || [];
			if (trends.length === 0) {
				$trends.append('<p class="vmsai-muted">No new trends scouted. Add more RSS feeds to the Brain.</p>');
			} else {
				trends.forEach( function(t) {
					$trends.append(
						'<div class="vmsai-trend-card">' +
							'<div style="display:flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px;">' +
								'<div style="font-size: 11px; color: var(--gold); font-weight: bold;">URGENT</div>' +
								'<button class="vmsai-btn vmsai-btn--quiet" style="padding: 2px 8px; font-size: 10px;" data-vmsai-action="inject-trend" data-trend="' + esc(t.trend) + '">⚡ Inject</button>' +
							'</div>' +
							'<div style="font-size: 13px; line-height: 1.4;">' + esc(t.trend) + '</div>' +
						'</div>'
					);
				});
			}

			// Insight
			var neglected = (res.gaps || []).filter( function(g) { return g.status === 'neglected'; });
			if (neglected.length > 0) {
				$insight.text('Your strategy is currently neglecting "' + neglected[0].pillar + '". Consider manually adding a few slots to the plan to rebalance.');
			} else {
				$insight.text("Your content mix is perfectly balanced according to your content pillars. Automation is holding the line.");
			}

		});
	}

	$(document).on('click', '[data-vmsai-action="refresh-strategy"]', refreshStrategy);

	$(document).on('click', '[data-vmsai-action="scout-trends"]', function() {
		var $btn = $(this);
		$btn.text('Scouting...').prop('disabled', true);

		VMSAI.api('/research/trends', 'GET').then( function(res) {
			$btn.text('🔍 Scout Live').prop('disabled', false);
			var $trends = $('#vmsai-strategy-trends');
			$trends.empty();
			if (!res.trends || res.trends.length === 0) {
				$trends.append('<p class="vmsai-muted">No new trends found. Try adding industry keywords to the Brain.</p>');
			} else {
				res.trends.forEach( function(t) {
					$trends.append(
						'<div class="vmsai-trend-card">' +
							'<div style="display:flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px;">' +
								'<div style="font-size: 11px; color: var(--gold); font-weight: bold;">TRENDING</div>' +
								'<button class="vmsai-btn vmsai-btn--quiet" style="padding: 2px 8px; font-size: 10px;" data-vmsai-action="inject-trend" data-trend="' + esc(t.full) + '">⚡ Inject</button>' +
							'</div>' +
							'<div style="font-size: 13px; line-height: 1.4;">' + esc(t.title) + '</div>' +
						'</div>'
					);
				});
			}
		});
	});

	$(document).on('click', '[data-vmsai-action="inject-trend"]', function() {
		var $btn = $(this);
		var trend = $btn.data('trend');
		$btn.text('Injecting...').prop('disabled', true);

		VMSAI.api('/research/inject', 'POST', { trend: trend }).then( function(res) {
			$btn.text('⚡ Injected').addClass('is-success');
			VMSAI.toast(res.message);
		});
	});

	// Initial load
	$(document).ready(refreshStrategy);
})(jQuery);
</script>
