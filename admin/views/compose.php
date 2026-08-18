<?php
/**
 * Compose — write a one-off post by hand, with live platform previews.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_ready   = vmsai()->channels()->ready();
$vmsai_manager = vmsai()->channels();
$vmsai_agents  = VMSAI_Agents::registry();
$vmsai_specs   = VMSAI_Composer::specs();
$vmsai_site    = get_bloginfo( 'name' );
?>

<style>
	.vmsai-compose { display: grid; grid-template-columns: 1fr 420px; gap: 28px; align-items: start; margin-top: 24px; }
	.vmsai-compose__pane { background: var(--panel); border: 1px solid var(--hairline); border-radius: 16px; padding: 22px; }
	.vmsai-compose__preview { position: sticky; top: 20px; }

	.vmsai-chan-picker { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
	.vmsai-chan-pill {
		display: flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 30px;
		background: var(--raised); border: 1px solid var(--hairline); cursor: pointer; font-size: 13px;
		transition: all 0.15s ease; user-select: none;
	}
	.vmsai-chan-pill:hover { border-color: var(--gold); }
	.vmsai-chan-pill.is-on { border-color: var(--gold); background: rgba(201,162,39,0.12); color: var(--gold); }
	.vmsai-chan-pill input { display: none; }
	.vmsai-chan-pill.is-off { opacity: 0.4; cursor: not-allowed; }

	.vmsai-compose textarea#vmsai-compose-body { width: 100%; min-height: 190px; font-size: 15px; line-height: 1.55; resize: vertical; box-sizing: border-box; }
	.vmsai-compose .vmsai-field { margin-bottom: 16px; }
	.vmsai-compose label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; }
	.vmsai-compose input[type=text], .vmsai-compose textarea, .vmsai-compose select, .vmsai-compose input[type=datetime-local] { width: 100%; box-sizing: border-box; }

	.vmsai-counter { display: flex; justify-content: space-between; font-size: 11px; color: var(--muted); margin-top: 6px; }
	.vmsai-counter.is-over { color: var(--red, #e5484d); font-weight: bold; }

	.vmsai-compose__toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin: 14px 0 20px; }

	.vmsai-media-box { display: flex; gap: 14px; align-items: flex-start; }
	.vmsai-media-thumb {
		width: 120px; height: 120px; border-radius: 10px; background: #0a0a0a; border: 1px solid var(--hairline);
		display: grid; place-items: center; overflow: hidden; flex-shrink: 0;
	}
	.vmsai-media-thumb img { width: 100%; height: 100%; object-fit: cover; }

	/* Live previews */
	.vmsai-pv-tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
	.vmsai-pv-tab { font-size: 11px; padding: 6px 12px; border-radius: 20px; background: var(--raised); border: 1px solid var(--hairline); cursor: pointer; }
	.vmsai-pv-tab.is-on { border-color: var(--gold); color: var(--gold); }

	.vmsai-pv { background: #fff; color: #1c1e21; border-radius: 10px; overflow: hidden; font-family: -apple-system, "Segoe UI", Roboto, sans-serif; box-shadow: 0 6px 24px rgba(0,0,0,0.35); }
	.vmsai-pv__head { display: flex; align-items: center; gap: 10px; padding: 12px; }
	.vmsai-pv__avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg,#c9a227,#7a6015); display: grid; place-items: center; color: #fff; font-weight: bold; font-size: 15px; flex-shrink: 0; }
	.vmsai-pv__who { font-weight: 600; font-size: 14px; line-height: 1.2; }
	.vmsai-pv__when { font-size: 12px; color: #65676b; }
	.vmsai-pv__body { padding: 0 12px 12px; font-size: 14px; line-height: 1.4; white-space: pre-wrap; word-break: break-word; }
	.vmsai-pv__more { color: #65676b; cursor: pointer; }
	.vmsai-pv__media { width: 100%; background: #000; display: block; }
	.vmsai-pv__media img { width: 100%; display: block; }
	.vmsai-pv__media.is-square img { aspect-ratio: 1/1; object-fit: cover; }
	.vmsai-pv__media.is-portrait img { aspect-ratio: 4/5; object-fit: cover; }
	.vmsai-pv__media.is-landscape img { aspect-ratio: 1.91/1; object-fit: cover; }
	.vmsai-pv__bar { display: flex; justify-content: space-around; border-top: 1px solid #ced0d4; padding: 8px 0; font-size: 13px; color: #65676b; }
	.vmsai-pv__comment { padding: 10px 12px; border-top: 1px solid #eceef0; font-size: 13px; }
	.vmsai-pv__comment b { font-weight: 600; }
	.vmsai-pv__empty { padding: 40px 20px; text-align: center; color: #90949c; font-size: 13px; }

	.vmsai-pv--ig .vmsai-pv__bar { justify-content: flex-start; gap: 16px; padding-left: 12px; }
	.vmsai-pv--li { font-family: -apple-system, "Segoe UI", Roboto, sans-serif; }
	.vmsai-pv--li .vmsai-pv__when { font-size: 11px; }

	.vmsai-pv-note { font-size: 11px; color: var(--muted); margin-top: 12px; line-height: 1.5; }
	.vmsai-schedule-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: end; }
</style>

<div class="vmsai-compose-wrap">
	<section class="vmsai-panel">
		<div class="vmsai-panel__head">
			<div>
				<h2 class="vmsai-display"><?php esc_html_e( 'Compose', 'vm-social-ai-pro' ); ?></h2>
				<p class="vmsai-lede"><?php esc_html_e( 'Write a one-off post and see exactly how it will land on each network before it goes out.', 'vm-social-ai-pro' ); ?></p>
			</div>
		</div>

		<?php if ( ! $vmsai_ready ) : ?>
			<div class="vmsai-empty" style="padding: 50px; text-align: center; color: var(--muted);">
				<p><?php esc_html_e( 'No channels are connected yet.', 'vm-social-ai-pro' ); ?></p>
				<a class="vmsai-btn vmsai-btn--gold" href="<?php echo esc_url( VMSAI_Admin::url( 'settings' ) ); ?>#vmsai-section-channels"><?php esc_html_e( 'Connect a channel', 'vm-social-ai-pro' ); ?></a>
			</div>
		<?php else : ?>

		<div class="vmsai-compose">
			<div class="vmsai-compose__pane">
				<div class="vmsai-field">
					<label><?php esc_html_e( 'Publish to', 'vm-social-ai-pro' ); ?></label>
					<div class="vmsai-chan-picker" id="vmsai-compose-channels">
						<?php foreach ( $vmsai_manager->all() as $vmsai_slug => $vmsai_channel ) : ?>
							<?php $vmsai_is_ready = in_array( $vmsai_slug, $vmsai_ready, true ); ?>
							<label class="vmsai-chan-pill<?php echo $vmsai_is_ready ? '' : ' is-off'; ?>" title="<?php echo $vmsai_is_ready ? '' : esc_attr__( 'Not connected', 'vm-social-ai-pro' ); ?>">
								<input type="checkbox" value="<?php echo esc_attr( $vmsai_slug ); ?>" <?php disabled( ! $vmsai_is_ready ); ?>>
								<?php echo esc_html( $vmsai_channel->label() ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Post', 'vm-social-ai-pro' ); ?></label>
					<textarea id="vmsai-compose-body" placeholder="<?php esc_attr_e( 'What do you want to say?', 'vm-social-ai-pro' ); ?>"></textarea>
					<div class="vmsai-counter" id="vmsai-compose-counter">
						<span id="vmsai-counter-count">0</span>
						<span id="vmsai-counter-limit"></span>
					</div>
				</div>

				<div class="vmsai-compose__toolbar">
					<input type="text" id="vmsai-compose-topic" placeholder="<?php esc_attr_e( 'Topic for the AI writer…', 'vm-social-ai-pro' ); ?>" style="flex: 1; min-width: 200px;">
					<select id="vmsai-compose-agent" style="width: auto;">
						<option value=""><?php esc_html_e( 'Default voice', 'vm-social-ai-pro' ); ?></option>
						<?php foreach ( $vmsai_agents as $vmsai_agent_slug => $vmsai_agent ) : ?>
							<option value="<?php echo esc_attr( $vmsai_agent_slug ); ?>"><?php echo esc_html( $vmsai_agent['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<select id="vmsai-compose-language" style="width: auto;">
						<option value="en" <?php selected( VMSAI_Settings::get( 'language' ), 'en' ); ?>><?php esc_html_e( 'English', 'vm-social-ai-pro' ); ?></option>
						<option value="hi" <?php selected( VMSAI_Settings::get( 'language' ), 'hi' ); ?>><?php esc_html_e( 'Hindi', 'vm-social-ai-pro' ); ?></option>
					</select>
					<select id="vmsai-compose-flavour" style="width: auto;">
						<option value="" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), '' ); ?>><?php esc_html_e( 'Standard', 'vm-social-ai-pro' ); ?></option>
						<option value="hinglish" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'hinglish' ); ?>><?php esc_html_e( 'Hinglish', 'vm-social-ai-pro' ); ?></option>
						<option value="mumbai" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'mumbai' ); ?>><?php esc_html_e( 'Mumbai', 'vm-social-ai-pro' ); ?></option>
						<option value="delhi" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'delhi' ); ?>><?php esc_html_e( 'Delhi', 'vm-social-ai-pro' ); ?></option>
					</select>
					<button type="button" class="vmsai-btn" id="vmsai-compose-draft">✨ <?php esc_html_e( 'Write it for me', 'vm-social-ai-pro' ); ?></button>
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Image', 'vm-social-ai-pro' ); ?></label>
					<div class="vmsai-media-box">
						<div class="vmsai-media-thumb" id="vmsai-compose-thumb"><span style="color: var(--muted); font-size: 26px;">🖼</span></div>
						<div style="flex: 1;">
							<textarea id="vmsai-compose-imgprompt" rows="2" placeholder="<?php esc_attr_e( 'Describe the image to generate…', 'vm-social-ai-pro' ); ?>"></textarea>
							<div style="display: flex; gap: 8px; margin-top: 8px;">
								<button type="button" class="vmsai-btn" id="vmsai-compose-genimg"><?php esc_html_e( 'Generate', 'vm-social-ai-pro' ); ?></button>
								<button type="button" class="vmsai-btn" id="vmsai-compose-pickimg"><?php esc_html_e( 'Media library', 'vm-social-ai-pro' ); ?></button>
								<button type="button" class="vmsai-btn" id="vmsai-compose-clearimg" style="display:none;"><?php esc_html_e( 'Remove', 'vm-social-ai-pro' ); ?></button>
							</div>
						</div>
					</div>
					<input type="hidden" id="vmsai-compose-media-id" value="0">
					<input type="hidden" id="vmsai-compose-media-url" value="">
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Hashtags (space separated, no #)', 'vm-social-ai-pro' ); ?></label>
					<input type="text" id="vmsai-compose-hashtags" placeholder="marketing smallbusiness">
				</div>

				<div class="vmsai-field">
					<label>
						<?php esc_html_e( 'First comment', 'vm-social-ai-pro' ); ?>
						<span style="color: var(--gold);"><?php esc_html_e( '— put hashtags here for Instagram, or your link here for LinkedIn', 'vm-social-ai-pro' ); ?></span>
					</label>
					<textarea id="vmsai-compose-first-comment" rows="2" placeholder="<?php esc_attr_e( 'Posted as the first comment right after publishing…', 'vm-social-ai-pro' ); ?>"></textarea>
					<div style="display: flex; gap: 8px; margin-top: 8px;">
						<button type="button" class="vmsai-btn" id="vmsai-move-tags"><?php esc_html_e( '↓ Move hashtags here', 'vm-social-ai-pro' ); ?></button>
						<button type="button" class="vmsai-btn" id="vmsai-move-link"><?php esc_html_e( '↓ Move link here', 'vm-social-ai-pro' ); ?></button>
					</div>
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Link', 'vm-social-ai-pro' ); ?></label>
					<input type="text" id="vmsai-compose-link" placeholder="https://">
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'Tags (for filtering your queue)', 'vm-social-ai-pro' ); ?></label>
					<input type="text" id="vmsai-compose-tags" placeholder="promo, festive">
				</div>

				<div class="vmsai-field">
					<label><?php esc_html_e( 'When', 'vm-social-ai-pro' ); ?></label>
					<div class="vmsai-schedule-row">
						<input type="datetime-local" id="vmsai-compose-when">
						<button type="button" class="vmsai-btn" id="vmsai-compose-nextslot"><?php esc_html_e( 'Next available', 'vm-social-ai-pro' ); ?></button>
					</div>
					<p class="vmsai-pv-note" id="vmsai-slot-hint"><?php esc_html_e( 'Leave empty to use the next open slot at the best-performing hour for each channel.', 'vm-social-ai-pro' ); ?></p>
				</div>

				<div style="display: flex; gap: 10px; margin-top: 22px; border-top: 1px solid var(--hairline); padding-top: 20px;">
					<button type="button" class="vmsai-btn vmsai-btn--gold" id="vmsai-compose-schedule"><?php esc_html_e( 'Schedule Post', 'vm-social-ai-pro' ); ?></button>
					<button type="button" class="vmsai-btn" id="vmsai-compose-savedraft"><?php esc_html_e( 'Save Draft', 'vm-social-ai-pro' ); ?></button>
					<button type="button" class="vmsai-btn" id="vmsai-compose-now" style="margin-left: auto;"><?php esc_html_e( 'Publish Now', 'vm-social-ai-pro' ); ?></button>
				</div>
				<div id="vmsai-compose-result" style="margin-top: 14px;"></div>
			</div>

			<div class="vmsai-compose__pane vmsai-compose__preview">
				<div class="vmsai-pv-tabs" id="vmsai-pv-tabs"></div>
				<div id="vmsai-pv-stage">
					<div class="vmsai-pv"><div class="vmsai-pv__empty"><?php esc_html_e( 'Pick a channel to see the preview.', 'vm-social-ai-pro' ); ?></div></div>
				</div>
				<p class="vmsai-pv-note" id="vmsai-pv-note"></p>
			</div>
		</div>

		<?php endif; ?>
	</section>
</div>

<script>
(function($) {
	var SPECS = <?php echo wp_json_encode( $vmsai_specs ); ?>;
	var SITE  = <?php echo wp_json_encode( $vmsai_site ); ?>;
	var previewChannel = '';

	var LABELS = {
		facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn', x: 'X',
		threads: 'Threads', bluesky: 'Bluesky', gbp: 'Google Business', youtube: 'YouTube',
		tiktok: 'TikTok', pinterest: 'Pinterest', telegram: 'Telegram'
	};

	// How each network crops the image, and where the caption gets cut off.
	var SHAPE = { instagram: 'is-portrait', facebook: 'is-landscape', linkedin: 'is-landscape', x: 'is-landscape' };
	var TRUNCATE = { facebook: 250, instagram: 125, linkedin: 210, x: 280 };

	function selectedChannels() {
		var out = [];
		$('#vmsai-compose-channels input:checked').each(function() { out.push($(this).val()); });
		return out;
	}

	function fullCaption() {
		var body = $('#vmsai-compose-body').val() || '';
		var first = ($('#vmsai-compose-first-comment').val() || '').trim();
		var link = ($('#vmsai-compose-link').val() || '').trim();
		var tags = ($('#vmsai-compose-hashtags').val() || '').trim();
		var parts = [body];

		// Anything living in the first comment must not also appear in the caption.
		if (link && first.indexOf(link) === -1) parts.push(link);
		if (tags && first.indexOf(tags) === -1) {
			parts.push(tags.split(/\s+/).filter(Boolean).map(function(t) {
				return t.charAt(0) === '#' ? t : '#' + t;
			}).join(' '));
		}

		return parts.filter(Boolean).join('\n\n');
	}

	function updateCounter() {
		var chans = selectedChannels();
		var text = fullCaption();
		var limit = 0;

		chans.forEach(function(c) {
			if (SPECS[c] && (limit === 0 || SPECS[c].limit < limit)) limit = SPECS[c].limit;
		});

		$('#vmsai-counter-count').text(text.length + ' characters');

		if (limit) {
			$('#vmsai-counter-limit').text('limit ' + limit);
			$('#vmsai-compose-counter').toggleClass('is-over', text.length > limit);
		} else {
			$('#vmsai-counter-limit').text('');
			$('#vmsai-compose-counter').removeClass('is-over');
		}
	}

	function esc(s) {
		return $('<div>').text(s == null ? '' : String(s)).html();
	}

	function renderTabs() {
		var chans = selectedChannels();
		var $tabs = $('#vmsai-pv-tabs').empty();

		if (!chans.length) { previewChannel = ''; return; }
		if (chans.indexOf(previewChannel) === -1) previewChannel = chans[0];

		chans.forEach(function(c) {
			$('<div>')
				.addClass('vmsai-pv-tab' + (c === previewChannel ? ' is-on' : ''))
				.attr('data-pv', c)
				.text(LABELS[c] || c)
				.appendTo($tabs);
		});
	}

	function renderPreview() {
		var $stage = $('#vmsai-pv-stage');
		var chans = selectedChannels();

		if (!chans.length) {
			$stage.html('<div class="vmsai-pv"><div class="vmsai-pv__empty">Pick a channel to see the preview.</div></div>');
			$('#vmsai-pv-note').text('');
			return;
		}

		var ch = previewChannel || chans[0];
		var text = fullCaption();
		var cut = TRUNCATE[ch] || 300;
		var img = $('#vmsai-compose-media-url').val();
		var first = ($('#vmsai-compose-first-comment').val() || '').trim();

		var shown = text.length > cut ? text.slice(0, cut) : text;
		var clipped = text.length > cut;

		var body = esc(shown) + (clipped ? '<span class="vmsai-pv__more">… more</span>' : '');
		var shape = SHAPE[ch] || 'is-square';
		var initial = (SITE || 'V').charAt(0).toUpperCase();

		var bar = ch === 'instagram'
			? '<div class="vmsai-pv__bar"><span>♡</span><span>💬</span><span>➤</span></div>'
			: '<div class="vmsai-pv__bar"><span>👍 Like</span><span>💬 Comment</span><span>↗ Share</span></div>';

		var mediaHtml = img ? '<div class="vmsai-pv__media ' + shape + '"><img src="' + esc(img) + '" alt=""></div>' : '';

		// Instagram puts the caption under the image; the others lead with text.
		var inner = ch === 'instagram'
			? mediaHtml + bar + '<div class="vmsai-pv__body"><b>' + esc(SITE) + '</b> ' + body + '</div>'
			: '<div class="vmsai-pv__body">' + body + '</div>' + mediaHtml + bar;

		var commentHtml = first
			? '<div class="vmsai-pv__comment"><b>' + esc(SITE) + '</b> ' + esc(first) + '</div>'
			: '';

		$stage.html(
			'<div class="vmsai-pv vmsai-pv--' + esc(ch) + '">' +
				'<div class="vmsai-pv__head">' +
					'<div class="vmsai-pv__avatar">' + esc(initial) + '</div>' +
					'<div><div class="vmsai-pv__who">' + esc(SITE) + '</div><div class="vmsai-pv__when">Just now · 🌐</div></div>' +
				'</div>' + inner + commentHtml +
			'</div>'
		);

		var notes = [];
		if (clipped) notes.push(LABELS[ch] + ' cuts the caption at about ' + cut + ' characters — everything before "… more" is all most people read.');
		if (ch === 'instagram' && !img) notes.push('Instagram will reject a feed post with no image.');
		if (ch === 'linkedin' && $('#vmsai-compose-link').val() && !first) notes.push('LinkedIn suppresses reach on posts with outbound links. Move the link to the first comment.');
		if (first) notes.push('The first comment posts automatically right after publishing.');
		$('#vmsai-pv-note').text(notes.join(' '));
	}

	function refresh() { renderTabs(); renderPreview(); updateCounter(); }

	$(document).on('change', '#vmsai-compose-channels input', function() {
		$(this).closest('.vmsai-chan-pill').toggleClass('is-on', this.checked);
		refresh();
	});

	$(document).on('click', '.vmsai-pv-tab', function() {
		previewChannel = $(this).data('pv');
		refresh();
	});

	$(document).on('input', '#vmsai-compose-body, #vmsai-compose-hashtags, #vmsai-compose-link, #vmsai-compose-first-comment', refresh);

	$(document).on('click', '#vmsai-move-tags', function() {
		var tags = ($('#vmsai-compose-hashtags').val() || '').trim();
		if (!tags) return;
		var formatted = tags.split(/\s+/).filter(Boolean).map(function(t) {
			return t.charAt(0) === '#' ? t : '#' + t;
		}).join(' ');
		var current = ($('#vmsai-compose-first-comment').val() || '').trim();
		$('#vmsai-compose-first-comment').val(current ? current + '\n\n' + formatted : formatted);
		refresh();
	});

	$(document).on('click', '#vmsai-move-link', function() {
		var link = ($('#vmsai-compose-link').val() || '').trim();
		if (!link) return;
		var current = ($('#vmsai-compose-first-comment').val() || '').trim();
		if (current.indexOf(link) !== -1) return;
		$('#vmsai-compose-first-comment').val(current ? current + '\n' + link : link);
		refresh();
	});

	$(document).on('click', '#vmsai-compose-draft', function() {
		var $btn = $(this);
		var topic = ($('#vmsai-compose-topic').val() || '').trim();
		var chans = selectedChannels();

		if (!topic) { alert('Tell the writer what the post is about.'); return; }
		if (!chans.length) { alert('Pick a channel first — the copy is written to its shape.'); return; }

		$btn.prop('disabled', true).text('Writing…');

		VMSAI.api('/compose/draft', 'POST', {
			topic: topic,
			channel: chans[0],
			agent: $('#vmsai-compose-agent').val(),
			language: $('#vmsai-compose-language').val(),
			flavour: $('#vmsai-compose-flavour').val()
		}).then( function(res) {
			if (res.ok) {
				var d = res.data || {};
				if (d.body) $('#vmsai-compose-body').val(d.body);
				if (d.hashtags) {
					$('#vmsai-compose-hashtags').val(
						(Array.isArray(d.hashtags) ? d.hashtags : String(d.hashtags).split(/[\s,]+/))
							.filter(Boolean).map(function(t) { return String(t).replace(/^#/, ''); }).join(' ')
					);
				}
				if (d.first_comment) $('#vmsai-compose-first-comment').val(d.first_comment);
				if (d.image_prompt) $('#vmsai-compose-imgprompt').val(d.image_prompt);
				if (d.alt_text) $('#vmsai-compose-thumb').attr('data-alt', d.alt_text);
				refresh();
			} else {
				alert(res.message || 'The writer could not produce a draft.');
			}
		}).catch( function(e) {
			alert('System error while drafting.');
		}).finally( function() {
			$btn.prop('disabled', false).html('✨ Write it for me');
		});
	});

	$(document).on('click', '#vmsai-compose-genimg', function() {
		var $btn = $(this);
		var prompt = ($('#vmsai-compose-imgprompt').val() || '').trim();
		var chans = selectedChannels();

		if (!prompt) { alert('Describe the image first.'); return; }

		$btn.prop('disabled', true).text('Generating…');

		VMSAI.api('/compose/image', 'POST', {
			prompt: prompt, channel: chans[0] || 'facebook', format: 'image'
		}).then( function(res) {
			if (res.ok) {
				$('#vmsai-compose-media-url').val(res.url);
				$('#vmsai-compose-media-id').val(res.media_id || 0);
				$('#vmsai-compose-thumb').html('<img src="' + res.url + '" alt="">');
				$('#vmsai-compose-clearimg').show();
				refresh();
			} else {
				alert(res.message || 'Image generation failed.');
			}
		}).catch( function(e) {
			alert('System error while generating the image.');
		}).finally( function() {
			$btn.prop('disabled', false).text('Generate');
		});
	});

	$(document).on('click', '#vmsai-compose-pickimg', function() {
		var frame = wp.media({ title: 'Choose an image', multiple: false, library: { type: 'image' } });
		frame.on('select', function() {
			var att = frame.state().get('selection').first().toJSON();
			$('#vmsai-compose-media-url').val(att.url);
			$('#vmsai-compose-media-id').val(att.id);
			$('#vmsai-compose-thumb').html('<img src="' + att.url + '" alt="">');
			$('#vmsai-compose-clearimg').show();
			refresh();
		});
		frame.open();
	});

	$(document).on('click', '#vmsai-compose-clearimg', function() {
		$('#vmsai-compose-media-url').val('');
		$('#vmsai-compose-media-id').val(0);
		$('#vmsai-compose-thumb').html('<span style="color: var(--muted); font-size: 26px;">🖼</span>');
		$(this).hide();
		refresh();
	});

	$(document).on('click', '#vmsai-compose-nextslot', function() {
		var chans = selectedChannels();
		if (!chans.length) { alert('Pick a channel first.'); return; }

		VMSAI.api('/compose/next-slot?channel=' + encodeURIComponent(chans[0]), 'GET').then( function(res) {
			if (res.ok) {
				$('#vmsai-compose-when').val(res.local.replace(' ', 'T'));
				$('#vmsai-slot-hint').text('Next open slot on ' + (LABELS[chans[0]] || chans[0]) + ': ' + res.label);
			}
		}).catch( function(e) {
			alert('Could not work out the next slot.');
		});
	});

	function submit(mode, $btn, originalLabel) {
		var chans = selectedChannels();
		var body = ($('#vmsai-compose-body').val() || '').trim();

		if (!chans.length) { alert('Pick at least one channel.'); return; }
		if (!body) { alert('The post is empty.'); return; }

		if (mode === 'now' && !confirm('Publish to ' + chans.length + ' channel(s) right now?')) return;

		$btn.prop('disabled', true).text('Working…');

		VMSAI.api('/compose/create', 'POST', {
			channels: chans,
			body: body,
			hashtags: $('#vmsai-compose-hashtags').val(),
			first_comment: $('#vmsai-compose-first-comment').val(),
			link: $('#vmsai-compose-link').val(),
			tags: $('#vmsai-compose-tags').val(),
			media_id: parseInt($('#vmsai-compose-media-id').val(), 10) || 0,
			media_url: $('#vmsai-compose-media-url').val(),
			image_prompt: $('#vmsai-compose-imgprompt').val(),
			alt_text: $('#vmsai-compose-thumb').attr('data-alt') || '',
			scheduled_at: $('#vmsai-compose-when').val().replace('T', ' '),
			status: mode === 'draft' ? 'draft' : 'approved',
			publish_now: mode === 'now'
		}).then( function(res) {
			if (!res.ok) {
				$('#vmsai-compose-result').html('<div style="color: var(--red);">' + esc(res.message || 'Could not save.') + '</div>');
				return;
			}

			if (mode === 'now') {
				var published = 0, failed = 0;
				var ids = res.ids || [];

				var publishNext = function(idx) {
					if (idx >= ids.length) {
						$('#vmsai-compose-result').html(
							'<div style="color: var(--gold);">Published to ' + published + ' channel(s)' +
							(failed ? ', ' + failed + ' failed — check the Queue for the reason.' : '.') + '</div>'
						);
						return;
					}

					VMSAI.api('/queue/publish-now', 'POST', { id: ids[idx] }).then( function(pub) {
						if (pub.ok) { published++; } else { failed++; }
						publishNext(idx + 1);
					}).catch( function() {
						failed++;
						publishNext(idx + 1);
					});
				};

				publishNext(0);
			} else {
				$('#vmsai-compose-result').html(
					'<div style="color: var(--gold);">Saved ' + res.ids.length + ' post(s). ' +
					'<a href="' + VMSAI.page + '&tab=queue">Open the Queue →</a></div>'
				);
			}
		}).catch( function(e) {
			$('#vmsai-compose-result').html('<div style="color: var(--red);">System error.</div>');
		}).finally( function() {
			$btn.prop('disabled', false).text(originalLabel);
		});
	}

	$(document).on('click', '#vmsai-compose-schedule', function() { submit('schedule', $(this), 'Schedule Post'); });
	$(document).on('click', '#vmsai-compose-savedraft', function() { submit('draft', $(this), 'Save Draft'); });
	$(document).on('click', '#vmsai-compose-now', function() { submit('now', $(this), 'Publish Now'); });

	refresh();
})(jQuery);
</script>
