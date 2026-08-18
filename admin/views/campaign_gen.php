<?php
/**
 * Campaign Generator — one campaign brief in, a full dated batch of posts out.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_manager = vmsai()->channels();
$vmsai_ready   = $vmsai_manager->ready();
$vmsai_brain_ok = VMSAI_Brain::is_ready();

$vmsai_angles = array();
foreach ( VMSAI_Campaign_Gen::angles() as $vmsai_key => $vmsai_angle ) {
	$vmsai_angles[] = array( 'key' => $vmsai_key, 'label' => $vmsai_angle['label'] );
}
?>

<section class="vmsai-panel">
	<h2 class="vmsai-display"><?php esc_html_e( 'Plan a campaign', 'vm-social-ai-pro' ); ?></h2>
	<p class="vmsai-lede">
		<?php esc_html_e( 'Describe what you\'re promoting — a festival sale, a product launch, a limited-time offer — and get a full, dated set of posts covering the whole arc: teaser, build-up, the offer itself, proof, launch day, last chance, and a thank-you. Each post gets its own caption with a call to action and a matching generated image.', 'vm-social-ai-pro' ); ?>
	</p>

	<?php if ( ! $vmsai_brain_ok ) : ?>
		<p class="vmsai-note vmsai-note--warn">
			<?php
			printf(
				/* translators: %s: link to the brain tab */
				esc_html__( 'Fill in the Brain first — business name, one-liner and audience — so posts sound like you. %s', 'vm-social-ai-pro' ),
				'<a href="' . esc_url( VMSAI_Admin::url( 'brain' ) ) . '">' . esc_html__( 'Go to Brain', 'vm-social-ai-pro' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( ! $vmsai_ready ) : ?>
		<p class="vmsai-note vmsai-note--warn">
			<?php
			printf(
				/* translators: %s: link to the channels tab */
				esc_html__( 'No channel is connected yet. %s before generating a campaign.', 'vm-social-ai-pro' ),
				'<a href="' . esc_url( VMSAI_Admin::url( 'settings' ) . '#vmsai-section-channels' ) . '">' . esc_html__( 'Connect at least one', 'vm-social-ai-pro' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<div class="vmsai-fields" id="vmsai-cg-form">
		<div class="vmsai-field vmsai-field--wide">
			<label for="vmsai-cg-name"><?php esc_html_e( 'Campaign name', 'vm-social-ai-pro' ); ?></label>
			<input type="text" id="vmsai-cg-name" placeholder="<?php esc_attr_e( 'e.g. Diwali Mega Sale, Summer Menu Launch, Black Friday Weekend', 'vm-social-ai-pro' ); ?>">
		</div>

		<div class="vmsai-field vmsai-field--wide">
			<label for="vmsai-cg-details">
				<?php esc_html_e( 'Campaign details', 'vm-social-ai-pro' ); ?>
				<button type="button" class="vmsai-btn vmsai-btn--quiet vmsai-cg-draft-btn" data-vmsai-action="draft-campaign">✨ <?php esc_html_e( 'Draft with AI', 'vm-social-ai-pro' ); ?></button>
			</label>
			<textarea id="vmsai-cg-details" rows="4" placeholder="<?php esc_attr_e( 'Type a rough note (optional), then hit Draft with AI — or write the full brief yourself: what is it, what\'s the offer, why now?', 'vm-social-ai-pro' ); ?>"></textarea>
			<p class="vmsai-muted"><?php esc_html_e( 'Name the campaign, optionally jot a rough note here, then let AI expand it into a full brief — including a suggested post count and end date below. Everything stays editable before you generate.', 'vm-social-ai-pro' ); ?></p>
		</div>

		<div class="vmsai-field">
			<label for="vmsai-cg-start"><?php esc_html_e( 'Starts', 'vm-social-ai-pro' ); ?></label>
			<input type="date" id="vmsai-cg-start" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
		</div>

		<div class="vmsai-field">
			<label for="vmsai-cg-end"><?php esc_html_e( 'Ends', 'vm-social-ai-pro' ); ?></label>
			<input type="date" id="vmsai-cg-end" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +6 days' ) ) ); ?>">
		</div>

		<div class="vmsai-field">
			<label for="vmsai-cg-count"><?php esc_html_e( 'How many posts', 'vm-social-ai-pro' ); ?></label>
			<input type="number" id="vmsai-cg-count" value="10" min="3" max="30">
			<div class="vmsai-cg-presets">
				<button type="button" class="vmsai-btn vmsai-btn--quiet" data-vmsai-count="5">5</button>
				<button type="button" class="vmsai-btn vmsai-btn--quiet" data-vmsai-count="10">10</button>
				<button type="button" class="vmsai-btn vmsai-btn--quiet" data-vmsai-count="15">15</button>
			</div>
		</div>

		<div class="vmsai-field">
			<label for="vmsai-cg-cta"><?php esc_html_e( 'Call to action (optional)', 'vm-social-ai-pro' ); ?></label>
			<input type="text" id="vmsai-cg-cta" placeholder="<?php esc_attr_e( 'e.g. Shop now at the link in bio', 'vm-social-ai-pro' ); ?>">
		</div>

		<div class="vmsai-field">
			<label for="vmsai-cg-language"><?php esc_html_e( 'Language', 'vm-social-ai-pro' ); ?></label>
			<select id="vmsai-cg-language">
				<option value="en" <?php selected( VMSAI_Settings::get( 'language' ), 'en' ); ?>><?php esc_html_e( 'English', 'vm-social-ai-pro' ); ?></option>
				<option value="hi" <?php selected( VMSAI_Settings::get( 'language' ), 'hi' ); ?>><?php esc_html_e( 'Hindi', 'vm-social-ai-pro' ); ?></option>
			</select>
		</div>

		<div class="vmsai-field">
			<label for="vmsai-cg-flavour"><?php esc_html_e( 'Local Flavour', 'vm-social-ai-pro' ); ?></label>
			<select id="vmsai-cg-flavour">
				<option value="" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), '' ); ?>><?php esc_html_e( 'Standard', 'vm-social-ai-pro' ); ?></option>
				<option value="hinglish" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'hinglish' ); ?>><?php esc_html_e( 'Hinglish', 'vm-social-ai-pro' ); ?></option>
				<option value="mumbai" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'mumbai' ); ?>><?php esc_html_e( 'Mumbai', 'vm-social-ai-pro' ); ?></option>
				<option value="delhi" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'delhi' ); ?>><?php esc_html_e( 'Delhi', 'vm-social-ai-pro' ); ?></option>
			</select>
		</div>

		<div class="vmsai-field vmsai-field--wide">
			<span class="vmsai-field__legend"><?php esc_html_e( 'Channels', 'vm-social-ai-pro' ); ?></span>
			<div class="vmsai-checks">
				<?php foreach ( $vmsai_manager->all() as $vmsai_slug => $vmsai_channel ) : ?>
					<?php $vmsai_connected = in_array( $vmsai_slug, $vmsai_ready, true ); ?>
					<label class="vmsai-check<?php echo $vmsai_connected ? '' : ' is-disabled'; ?>">
						<input type="checkbox" class="vmsai-cg-channel"
							value="<?php echo esc_attr( $vmsai_slug ); ?>"
							<?php checked( $vmsai_connected ); ?>
							<?php disabled( ! $vmsai_connected ); ?>>
						<span><?php echo esc_html( $vmsai_channel->label() ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<div class="vmsai-mathbox" id="vmsai-cg-preview" aria-live="polite"></div>

	<p>
		<button type="button" class="vmsai-btn vmsai-btn--gold" data-vmsai-action="generate-campaign" <?php disabled( ! $vmsai_ready || ! $vmsai_brain_ok ); ?>>
			<?php esc_html_e( 'Generate the campaign', 'vm-social-ai-pro' ); ?>
		</button>
		<span class="vmsai-muted"><?php esc_html_e( 'Plans the whole arc, then writes and illustrates each post one at a time — this takes a minute or two.', 'vm-social-ai-pro' ); ?></span>
	</p>
</section>

<section class="vmsai-panel" id="vmsai-cg-progress" style="display:none;">
	<div class="vmsai-panel__head">
		<div>
			<h2 class="vmsai-display"><?php esc_html_e( 'Generating', 'vm-social-ai-pro' ); ?></h2>
			<p class="vmsai-lede" id="vmsai-cg-progress-text"></p>
		</div>
	</div>
	<div class="vmsai-cg-grid" id="vmsai-cg-grid"></div>
	<p id="vmsai-cg-done" style="display:none;">
		<a href="<?php echo esc_url( VMSAI_Admin::url( 'queue' ) . '&status=all' ); ?>" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Review the campaign in the Queue', 'vm-social-ai-pro' ); ?></a>
	</p>
</section>

<script type="application/json" id="vmsai-cg-angles"><?php echo wp_json_encode( $vmsai_angles ); ?></script>

<style>
	.vmsai-cg-draft-btn { margin-left: 10px; padding: 2px 10px; font-size: 11px; }
	.vmsai-cg-presets { display: flex; gap: 6px; margin-top: 6px; }
	.vmsai-cg-presets .vmsai-btn { padding: 4px 12px; font-size: 12px; }
	.vmsai-cg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; margin-top: 20px; }
	.vmsai-cg-card { background: var(--panel); border: 1px solid var(--hairline); border-radius: 12px; overflow: hidden; }
	.vmsai-cg-card.is-waiting { opacity: 0.5; }
	.vmsai-cg-card.is-busy { border-color: var(--gold); }
	.vmsai-cg-card.is-failed { border-color: #b33; }
	.vmsai-cg-card__media { aspect-ratio: 1.2; background: var(--ink); display: grid; place-items: center; }
	.vmsai-cg-card__media img { width: 100%; height: 100%; object-fit: cover; }
	.vmsai-cg-card__media span { font-size: 11px; color: var(--muted); }
	.vmsai-cg-card__body { padding: 12px 14px; }
	.vmsai-cg-card__angle { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: var(--gold); font-weight: 700; }
	.vmsai-cg-card__meta { font-size: 11px; color: var(--muted); margin: 2px 0 8px; }
	.vmsai-cg-card__title { font-size: 13px; font-weight: 600; margin: 0 0 6px; }
	.vmsai-cg-card__snippet { font-size: 12px; color: var(--muted); line-height: 1.5; max-height: 4.5em; overflow: hidden; }
	.vmsai-cg-card__error { font-size: 12px; color: #e88; }
</style>
