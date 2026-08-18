<?php
/**
 * Production Pipeline Kanban.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! VMSAI_License::has_feature( 'production_pipeline' ) ) :
?>
	<section class="vmsai-panel" style="text-align: center; padding: 120px 40px; background: linear-gradient(135deg, #14141a, #0b0b0f); border: 1px solid var(--gold); border-radius: 16px; position: relative; overflow: hidden;">
		<div style="position: absolute; top: -50px; right: -50px; font-size: 200px; opacity: 0.05; transform: rotate(15deg);">🏭</div>
		<div style="font-size: 60px; margin-bottom: 20px; filter: drop-shadow(0 0 15px var(--gold));">👑</div>
		<h2 class="vmsai-display" style="font-size: 32px;">The Factory Floor is for Elite Users</h2>
		<p class="vmsai-lede" style="margin: 0 auto 30px; max-width: 500px;">Track your AI agents, monitor production pulses, and manage social assets via the Kanban Pipeline. Elite Agency access required.</p>
		<div style="display: flex; gap: 15px; justify-content: center;">
			<a href="<?php echo esc_url( VMSAI_Admin::upgrade_url() ); ?>" class="vmsai-btn vmsai-btn--gold" style="padding: 15px 40px; font-size: 16px;">Go Elite</a>
		</div>
	</section>
<?php
	return;
endif;
?>

<style>
	.vmsai-kanban { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; overflow-x: auto; padding-bottom: 20px; }
	.vmsai-kanban-col { background: rgba(0,0,0,0.1); border-radius: 12px; min-height: 600px; display: flex; flex-direction: column; }
	.vmsai-kanban-head { padding: 15px 20px; border-bottom: 1px solid var(--hairline); display: flex; justify-content: space-between; align-items: center; }
	.vmsai-kanban-head h3 { margin: 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
	.vmsai-kanban-count { background: var(--hairline); padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }

	.vmsai-kanban-list { padding: 15px; flex-grow: 1; }
	.vmsai-pipeline-card {
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 8px;
		padding: 15px; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
		transition: transform 0.2s ease;
	}
	.vmsai-pipeline-card:hover { transform: scale(1.02); border-color: var(--gold); }
	.vmsai-pipeline-card__meta { font-size: 10px; text-transform: uppercase; font-weight: bold; color: var(--gold); margin-bottom: 8px; display: flex; justify-content: space-between; }
	.vmsai-pipeline-card__title { font-size: 13px; font-weight: 600; line-height: 1.4; margin-bottom: 10px; color: var(--parchment); }
	.vmsai-pipeline-card__date { font-size: 11px; color: var(--muted); }

	/* Agent Pulse */
	.vmsai-agent-pulse { display: flex; align-items: center; gap: 8px; font-size: 10px; font-weight: bold; color: var(--green); margin-top: 10px; text-transform: uppercase; }
	.vmsai-pulse-dot { width: 6px; height: 6px; background: var(--green); border-radius: 50%; animation: vmsai-pulse 1.5s infinite; }
	@keyframes vmsai-pulse { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
</style>

<div class="vmsai-panel">
	<div class="vmsai-panel__head">
		<div>
			<h2 class="vmsai-display">Production Pipeline</h2>
			<p class="vmsai-lede">Tracking content from high-level plans to final deployment.</p>
		</div>
		<button class="vmsai-btn" data-vmsai-action="refresh-pipeline">🔄 Sync Board</button>
	</div>

	<div class="vmsai-kanban" id="vmsai-pipeline-board">
		<div class="vmsai-kanban-col" data-stage="scouting">
			<div class="vmsai-kanban-head">
				<h3>Scouting</h3>
				<span class="vmsai-kanban-count">0</span>
			</div>
			<div class="vmsai-kanban-list"></div>
		</div>

		<div class="vmsai-kanban-col" data-stage="production">
			<div class="vmsai-kanban-head">
				<h3>Production</h3>
				<span class="vmsai-kanban-count">0</span>
			</div>
			<div class="vmsai-kanban-list"></div>
		</div>

		<div class="vmsai-kanban-col" data-stage="audit">
			<div class="vmsai-kanban-head">
				<h3>Awaiting Audit</h3>
				<span class="vmsai-kanban-count">0</span>
			</div>
			<div class="vmsai-kanban-list"></div>
		</div>

		<div class="vmsai-kanban-col" data-stage="ready">
			<div class="vmsai-kanban-head">
				<h3>Ready to Fly</h3>
				<span class="vmsai-kanban-count">0</span>
			</div>
			<div class="vmsai-kanban-list"></div>
		</div>

		<div class="vmsai-kanban-col" data-stage="stalled">
			<div class="vmsai-kanban-head">
				<h3 style="color:var(--red);">Stalled</h3>
				<span class="vmsai-kanban-count">0</span>
			</div>
			<div class="vmsai-kanban-list"></div>
		</div>
	</div>
</div>

<script>
(function($) {
	// Titles are model-generated and error text can carry third-party API
	// output — neither is safe to drop into innerHTML unescaped.
	var esc = VMSAI.esc;

	function truncate(text, max) {
		var s = String(text || '');
		return s.length > max ? s.slice(0, max) + '…' : s;
	}

	function refreshPipeline() {
		VMSAI.api('/pipeline/list', 'GET').then( function(res) {
			if (!res.ok) {
				VMSAI.toast(res.error || 'The pipeline could not be loaded.', true);
				return;
			}

			['scouting', 'production', 'audit', 'ready', 'stalled'].forEach( function(stage) {
				var items = res[stage] || [];
				var $col = $('.vmsai-kanban-col[data-stage="' + stage + '"]');
				var $list = $col.find('.vmsai-kanban-list');

				$col.find('.vmsai-kanban-count').text(items.length);
				$list.empty();

				if (!items.length) {
					$list.append('<p class="vmsai-muted" style="font-size:11px; padding:10px 5px;">Nothing here.</p>');
					return;
				}

				items.forEach( function(item) {
					var title = item.title || item.topic || 'Untitled';
					var scoreTag = item.score
						? '<span style="background:var(--ink); padding: 2px 6px; border-radius:4px; font-size:9px;">🧠 ' + esc(item.score) + '</span>'
						: '';

					var statusHtml = '';

					if (stage === 'production') {
						// Only claim the writer is running if it plausibly is.
						var busy = parseInt(item.busy_minutes, 10) || 0;
						statusHtml = busy > 10
							? '<div style="color:var(--red); font-size:9px; margin-top:10px;">⚠️ No progress for ' + esc(busy) + ' min — will be retried.</div>'
							: '<div class="vmsai-agent-pulse"><span class="vmsai-pulse-dot"></span><span>Writer Agent Active</span></div>';
					}

					if (stage === 'stalled') {
						var isCompose = item.stage === 'compose';
						var fixLabel = isCompose ? 'Check AI Engines' : 'Fix Connection';
						var fixHash = isCompose ? '#vmsai-section-engines' : '#vmsai-section-channels';

						statusHtml = '<div style="color:var(--red); font-size:9px; margin-top:10px; line-height:1.3;">' +
								esc(item.error ? truncate(item.error, 60) : 'Unknown failure') +
							'</div>' +
							'<div style="margin-top:10px; display:flex; gap:5px;">' +
								'<a class="vmsai-btn vmsai-btn--quiet" style="font-size:8px; padding:3px 6px;" ' +
								   'href="' + esc(VMSAI.page + '&tab=settings' + fixHash) + '">' + esc(fixLabel) + '</a>' +
							'</div>';
					}

					$list.append(
						'<div class="vmsai-pipeline-card">' +
							'<div class="vmsai-pipeline-card__meta">' +
								'<span>' + esc(item.channel) + '</span>' +
								scoreTag +
							'</div>' +
							'<div class="vmsai-pipeline-card__title">' + esc(title) + '</div>' +
							'<div class="vmsai-pipeline-card__date">📅 ' + esc(item.date) + '</div>' +
							statusHtml +
						'</div>'
					);
				});
			});
		});
	}

	$(document).on('click', '[data-vmsai-action="refresh-pipeline"]', refreshPipeline);
	$(document).ready(refreshPipeline);
})(jQuery);
</script>
