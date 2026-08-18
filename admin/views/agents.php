<?php
/**
 * Agent Roster — manage the content personas the Agent Feed rotates through.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

VMSAI_Agents::maybe_seed_starter_roster();

$vmsai_agents_all = VMSAI_Agents::all();
$vmsai_styles     = VMSAI_Agents::styles();
$vmsai_channels   = array(
	'facebook'  => __( 'Facebook', 'vm-social-ai-pro' ),
	'instagram' => __( 'Instagram', 'vm-social-ai-pro' ),
	'threads'   => __( 'Threads', 'vm-social-ai-pro' ),
	'bluesky'   => __( 'Bluesky', 'vm-social-ai-pro' ),
	'x'         => __( 'X', 'vm-social-ai-pro' ),
	'linkedin'  => __( 'LinkedIn', 'vm-social-ai-pro' ),
	'gbp'       => __( 'Google Business', 'vm-social-ai-pro' ),
	'tiktok'    => __( 'TikTok', 'vm-social-ai-pro' ),
	'youtube'   => __( 'YouTube', 'vm-social-ai-pro' ),
);
?>

<style>
	.vmsai-agents-grid { display: grid; grid-template-columns: 1fr 380px; gap: 25px; margin-top: 25px; align-items: start; }
	.vmsai-agent-list { display: grid; gap: 14px; }
	.vmsai-agent-item {
		display: grid; grid-template-columns: auto 1fr auto; gap: 14px; align-items: center;
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 12px;
		padding: 16px 18px; cursor: pointer; transition: all 0.15s ease;
	}
	.vmsai-agent-item:hover { border-color: var(--gold); }
	.vmsai-agent-item.is-selected { border-color: var(--gold); box-shadow: 0 0 0 1px var(--gold); }
	.vmsai-agent-item.is-paused { opacity: 0.55; }
	.vmsai-agent-item__badge {
		width: 40px; height: 40px; border-radius: 10px; background: var(--raised);
		display: grid; place-items: center; font-size: 18px;
	}
	.vmsai-agent-item__name { font-weight: 600; color: var(--text, #eee); }
	.vmsai-agent-item__meta { font-size: 12px; color: var(--muted); margin-top: 2px; }
	.vmsai-agent-item__weight {
		font-size: 11px; color: var(--gold); background: rgba(201,162,39,0.12);
		padding: 3px 9px; border-radius: 20px; white-space: nowrap;
	}
	.vmsai-agent-form { background: var(--panel); border: 1px solid var(--hairline); border-radius: 14px; padding: 20px; position: sticky; top: 20px; }
	.vmsai-agent-form h3 { margin: 0 0 16px; }
	.vmsai-agent-form .vmsai-field { margin-bottom: 14px; }
	.vmsai-agent-form label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; }
	.vmsai-agent-form input[type=text], .vmsai-agent-form textarea, .vmsai-agent-form select, .vmsai-agent-form input[type=number] {
		width: 100%; box-sizing: border-box;
	}
	.vmsai-channel-chips { display: flex; flex-wrap: wrap; gap: 8px; }
	.vmsai-channel-chip { display: flex; align-items: center; gap: 5px; font-size: 12px; background: var(--raised); border: 1px solid var(--hairline); border-radius: 20px; padding: 5px 10px; cursor: pointer; }
	.vmsai-channel-chip input { margin: 0; }
	.vmsai-agent-form__actions { display: flex; gap: 10px; margin-top: 18px; }
	#vmsai-agent-custom-style-field { display: none; }
	.vmsai-agents-empty { color: var(--muted); padding: 30px; text-align: center; }
</style>

<div class="vmsai-lab">
	<section class="vmsai-panel">
		<div class="vmsai-panel__head">
			<div>
				<h2 class="vmsai-display"><?php esc_html_e( 'Agent Roster', 'vm-social-ai-pro' ); ?></h2>
				<p class="vmsai-lede"><?php esc_html_e( 'Each Agent is a distinct voice — storyteller, promoter, trend-watcher, quote curator, or your own — with its own tone, visual style and channels. The Agent Feed rotates through active agents automatically, separate from your evergreen Plan pillars.', 'vm-social-ai-pro' ); ?></p>
			</div>
			<button type="button" class="vmsai-btn vmsai-btn--gold" id="vmsai-agent-new"><?php esc_html_e( '+ New Agent', 'vm-social-ai-pro' ); ?></button>
		</div>

		<div class="vmsai-agents-grid">
			<div class="vmsai-agent-list" id="vmsai-agent-list">
				<?php if ( ! $vmsai_agents_all ) : ?>
					<div class="vmsai-agents-empty"><?php esc_html_e( 'No agents yet — create your first one.', 'vm-social-ai-pro' ); ?></div>
				<?php endif; ?>
				<?php foreach ( $vmsai_agents_all as $vmsai_agent ) : ?>
					<?php
					$vmsai_style_def = $vmsai_styles[ $vmsai_agent['image_style'] ] ?? $vmsai_styles['photo'];
					$vmsai_agent_channels = json_decode( (string) $vmsai_agent['channels'], true );
					$vmsai_agent_channels = is_array( $vmsai_agent_channels ) ? array_filter( $vmsai_agent_channels ) : array();
					$vmsai_channel_summary = $vmsai_agent_channels
						? implode( ', ', array_map( fn( $c ) => $vmsai_channels[ $c ] ?? $c, $vmsai_agent_channels ) )
						: __( 'All connected channels', 'vm-social-ai-pro' );
					?>
					<div class="vmsai-agent-item<?php echo 'paused' === $vmsai_agent['status'] ? ' is-paused' : ''; ?>" data-agent-id="<?php echo esc_attr( $vmsai_agent['id'] ); ?>">
						<div class="vmsai-agent-item__badge">🎭</div>
						<div>
							<div class="vmsai-agent-item__name"><?php echo esc_html( $vmsai_agent['name'] ); ?></div>
							<div class="vmsai-agent-item__meta">
								<?php echo esc_html( $vmsai_style_def['label'] ); ?> · <?php echo esc_html( $vmsai_channel_summary ); ?>
								<?php if ( 'paused' === $vmsai_agent['status'] ) : ?> · <?php esc_html_e( 'Paused', 'vm-social-ai-pro' ); ?><?php endif; ?>
							</div>
						</div>
						<div class="vmsai-agent-item__weight"><?php echo esc_html( $vmsai_agent['weight'] ); ?>%</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="vmsai-agent-form">
				<h3 id="vmsai-agent-form-title"><?php esc_html_e( 'New Agent', 'vm-social-ai-pro' ); ?></h3>
				<input type="hidden" id="vmsai-agent-id" value="0">

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Name', 'vm-social-ai-pro' ); ?></label>
					<input type="text" id="vmsai-agent-name" placeholder="e.g. The Storyteller">
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( "Agent's job (what it posts about)", 'vm-social-ai-pro' ); ?></label>
					<textarea id="vmsai-agent-brief" rows="3" placeholder="e.g. Tell a short, emotionally engaging story connected to the business..."></textarea>
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Voice / tone', 'vm-social-ai-pro' ); ?></label>
					<textarea id="vmsai-agent-tone" rows="2" placeholder="e.g. Warm, reflective, a little nostalgic."></textarea>
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Image style', 'vm-social-ai-pro' ); ?></label>
					<select id="vmsai-agent-style">
						<?php foreach ( $vmsai_styles as $vmsai_style_key => $vmsai_style ) : ?>
							<option value="<?php echo esc_attr( $vmsai_style_key ); ?>"><?php echo esc_html( $vmsai_style['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="vmsai-field" id="vmsai-agent-custom-style-field">
					<label><?php esc_html_e( 'Custom visual style brief', 'vm-social-ai-pro' ); ?></label>
					<textarea id="vmsai-agent-style-custom" rows="3" placeholder="Describe the exact visual style for this agent's images..."></textarea>
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Channels (none selected = all connected channels)', 'vm-social-ai-pro' ); ?></label>
					<div class="vmsai-channel-chips" id="vmsai-agent-channels">
						<?php foreach ( $vmsai_channels as $vmsai_ch_slug => $vmsai_ch_label ) : ?>
							<label class="vmsai-channel-chip">
								<input type="checkbox" value="<?php echo esc_attr( $vmsai_ch_slug ); ?>">
								<?php echo esc_html( $vmsai_ch_label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Weight (relative share of the rotation)', 'vm-social-ai-pro' ); ?></label>
					<input type="number" id="vmsai-agent-weight" min="1" max="100" value="10">
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Status', 'vm-social-ai-pro' ); ?></label>
					<select id="vmsai-agent-status">
						<option value="active"><?php esc_html_e( 'Active', 'vm-social-ai-pro' ); ?></option>
						<option value="paused"><?php esc_html_e( 'Paused', 'vm-social-ai-pro' ); ?></option>
					</select>
				</div>

				<div class="vmsai-agent-form__actions">
					<button type="button" class="vmsai-btn vmsai-btn--gold" id="vmsai-agent-save"><?php esc_html_e( 'Save Agent', 'vm-social-ai-pro' ); ?></button>
					<button type="button" class="vmsai-btn" id="vmsai-agent-delete" style="display:none; color: var(--red);"><?php esc_html_e( 'Delete', 'vm-social-ai-pro' ); ?></button>
				</div>
			</div>
		</div>
	</section>
</div>

<script>
(function($) {
	var agents = <?php echo wp_json_encode( $vmsai_agents_all ); ?>;

	function byId(id) {
		for (var i = 0; i < agents.length; i++) {
			if (parseInt(agents[i].id, 10) === parseInt(id, 10)) return agents[i];
		}
		return null;
	}

	function toggleCustomStyle() {
		$('#vmsai-agent-custom-style-field').css('display', $('#vmsai-agent-style').val() === 'custom' ? 'block' : 'none');
	}

	function resetForm() {
		$('#vmsai-agent-form-title').text('<?php echo esc_js( __( 'New Agent', 'vm-social-ai-pro' ) ); ?>');
		$('#vmsai-agent-id').val('0');
		$('#vmsai-agent-name').val('');
		$('#vmsai-agent-brief').val('');
		$('#vmsai-agent-tone').val('');
		$('#vmsai-agent-style').val('photo');
		$('#vmsai-agent-style-custom').val('');
		$('#vmsai-agent-channels input').prop('checked', false);
		$('#vmsai-agent-weight').val(10);
		$('#vmsai-agent-status').val('active');
		$('#vmsai-agent-delete').hide();
		$('.vmsai-agent-item').removeClass('is-selected');
		toggleCustomStyle();
	}

	function populateForm(agent) {
		$('#vmsai-agent-form-title').text(agent.name);
		$('#vmsai-agent-id').val(agent.id);
		$('#vmsai-agent-name').val(agent.name);
		$('#vmsai-agent-brief').val(agent.brief);
		$('#vmsai-agent-tone').val(agent.tone);
		$('#vmsai-agent-style').val(agent.image_style || 'photo');
		$('#vmsai-agent-style-custom').val(agent.image_style_custom || '');

		var channels = [];
		try { channels = JSON.parse(agent.channels || '[]'); } catch (e) { channels = []; }
		$('#vmsai-agent-channels input').each(function() {
			$(this).prop('checked', channels.indexOf($(this).val()) !== -1);
		});

		$('#vmsai-agent-weight').val(agent.weight || 10);
		$('#vmsai-agent-status').val(agent.status || 'active');
		$('#vmsai-agent-delete').show();
		toggleCustomStyle();
	}

	$(document).on('change', '#vmsai-agent-style', toggleCustomStyle);

	$(document).on('click', '#vmsai-agent-new', function() {
		resetForm();
	});

	$(document).on('click', '.vmsai-agent-item', function() {
		var id = $(this).data('agent-id');
		var agent = byId(id);
		if (!agent) return;
		$('.vmsai-agent-item').removeClass('is-selected');
		$(this).addClass('is-selected');
		populateForm(agent);
	});

	$(document).on('click', '#vmsai-agent-save', function() {
		var $btn = $(this);
		var name = $('#vmsai-agent-name').val().trim();

		if (!name) {
			alert('<?php echo esc_js( __( 'Give this agent a name.', 'vm-social-ai-pro' ) ); ?>');
			return;
		}

		var channels = [];
		$('#vmsai-agent-channels input:checked').each(function() { channels.push($(this).val()); });

		$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving…', 'vm-social-ai-pro' ) ); ?>');

		VMSAI.api('/agents/save', 'POST', {
			id: parseInt($('#vmsai-agent-id').val(), 10) || 0,
			name: name,
			brief: $('#vmsai-agent-brief').val(),
			tone: $('#vmsai-agent-tone').val(),
			image_style: $('#vmsai-agent-style').val(),
			image_style_custom: $('#vmsai-agent-style-custom').val(),
			channels: channels,
			weight: parseInt($('#vmsai-agent-weight').val(), 10) || 10,
			status: $('#vmsai-agent-status').val()
		}).then( function(res) {
			if (res.ok) {
				window.location.reload();
			} else {
				alert(res.message || '<?php echo esc_js( __( 'Could not save the agent.', 'vm-social-ai-pro' ) ); ?>');
			}
		}).catch( function(err) {
			alert('<?php echo esc_js( __( 'System error while saving.', 'vm-social-ai-pro' ) ); ?>');
		}).finally( function() {
			$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Agent', 'vm-social-ai-pro' ) ); ?>');
		});
	});

	$(document).on('click', '#vmsai-agent-delete', function() {
		var id = parseInt($('#vmsai-agent-id').val(), 10);
		if (!id) return;
		if (!confirm('<?php echo esc_js( __( 'Delete this agent? Its past posts stay untouched.', 'vm-social-ai-pro' ) ); ?>')) return;

		VMSAI.api('/agents/delete', 'POST', { id: id }).then( function(res) {
			if (res.ok) {
				window.location.reload();
			} else {
				alert(res.message || '<?php echo esc_js( __( 'Could not delete the agent.', 'vm-social-ai-pro' ) ); ?>');
			}
		}).catch( function(err) {
			alert('<?php echo esc_js( __( 'System error while deleting.', 'vm-social-ai-pro' ) ); ?>');
		});
	});

	toggleCustomStyle();
})(jQuery);
</script>
