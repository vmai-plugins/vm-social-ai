<?php
/**
 * The Brain editor.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_schema = VMSAI_Brain::schema();
$vmsai_values = VMSAI_Brain::all();
$vmsai_long   = array( 'offers', 'pain_points', 'proof', 'keywords', 'cta_library', 'banned_phrases', 'compliance_notes', 'differentiator', 'audience', 'voice_lab', 'lead_magnet' );
?>

<div class="vmsai-nav-sub">
	<a href="#vmsai-brain-business" class="is-active"><?php esc_html_e( 'Business Identity', 'vm-social-ai-pro' ); ?></a>
	<a href="#vmsai-brain-branding"><?php esc_html_e( 'The Brand Kit', 'vm-social-ai-pro' ); ?></a>
	<a href="#vmsai-brain-intel"><?php esc_html_e( 'Expertise & Intel', 'vm-social-ai-pro' ); ?></a>
</div>

<div class="vmsai-settings-container" style="margin-top: 20px;">
	<section class="vmsai-panel vmsai-settings-section" id="vmsai-brain-business">
		<div class="vmsai-panel__head">
			<div>
				<h2 class="vmsai-display"><?php esc_html_e( 'What the AI knows about this business', 'vm-social-ai-pro' ); ?></h2>
				<p class="vmsai-lede">
					<?php esc_html_e( 'Everything here gets injected into every post the engine writes. Vague answers produce vague posts, so be specific.', 'vm-social-ai-pro' ); ?>
				</p>
			</div>
			<div class="vmsai-meter" title="<?php esc_attr_e( 'How complete the Brain is', 'vm-social-ai-pro' ); ?>">
				<span class="vmsai-meter__value"><?php echo esc_html( VMSAI_Brain::completeness() ); ?>%</span>
				<span class="vmsai-meter__label"><?php esc_html_e( 'complete', 'vm-social-ai-pro' ); ?></span>
			</div>
		</div>

		<p>
			<button type="button" class="vmsai-btn vmsai-btn--gold" data-vmsai-action="discover">
				<?php esc_html_e( 'Read my site and draft this', 'vm-social-ai-pro' ); ?>
			</button>
			<button type="button" class="vmsai-btn" data-vmsai-action="reflect">
				<?php esc_html_e( 'Refine voice from performance', 'vm-social-ai-pro' ); ?>
			</button>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="vmsai-brain-form">
			<?php wp_nonce_field( 'vmsai_save' ); ?>
			<input type="hidden" name="action" value="vmsai_save">
			<input type="hidden" name="section" value="brain">

			<div class="vmsai-fields">
				<?php
				$vmsai_core_fields = array( 'business_name', 'google_category', 'target_timezone', 'differentiator', 'audience', 'voice_lab', 'offers', 'pain_points', 'proof', 'keywords', 'cta_library', 'banned_phrases', 'compliance_notes' );
				foreach ( $vmsai_core_fields as $vmsai_key ) :
					$vmsai_label = $vmsai_schema[ $vmsai_key ] ?? $vmsai_key;
					$vmsai_is_long = in_array( $vmsai_key, $vmsai_long, true );
				?>
					<div class="vmsai-field<?php echo $vmsai_is_long ? ' vmsai-field--wide' : ''; ?>">
						<label for="vmsai-brain-<?php echo esc_attr( $vmsai_key ); ?>"><?php echo esc_html( $vmsai_label ); ?></label>
						<?php if ( $vmsai_is_long ) : ?>
							<textarea id="vmsai-brain-<?php echo esc_attr( $vmsai_key ); ?>" name="brain[<?php echo esc_attr( $vmsai_key ); ?>]" rows="4" data-brain-field="<?php echo esc_attr( $vmsai_key ); ?>"><?php echo esc_textarea( $vmsai_values[ $vmsai_key ] ?? '' ); ?></textarea>
						<?php else : ?>
							<input type="text" id="vmsai-brain-<?php echo esc_attr( $vmsai_key ); ?>" name="brain[<?php echo esc_attr( $vmsai_key ); ?>]" value="<?php echo esc_attr( $vmsai_values[ $vmsai_key ] ?? '' ); ?>" data-brain-field="<?php echo esc_attr( $vmsai_key ); ?>">
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<p><button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Save Identity', 'vm-social-ai-pro' ); ?></button></p>
		</form>
	</section>

	<section class="vmsai-panel vmsai-settings-section" id="vmsai-brain-branding" style="display:none;">
		<div class="vmsai-panel__head">
			<h2 class="vmsai-display"><?php esc_html_e( 'The Brand Kit', 'vm-social-ai-pro' ); ?></h2>
			<p class="vmsai-lede"><?php esc_html_e( 'Enforce visual consistency across all AI-generated assets.', 'vm-social-ai-pro' ); ?></p>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'vmsai_save' ); ?>
			<input type="hidden" name="action" value="vmsai_save">
			<input type="hidden" name="section" value="brain">

			<div class="vmsai-fields">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
					<div class="vmsai-field">
						<label><?php echo esc_html( $vmsai_schema['brand_color_1'] ); ?></label>
						<input type="text" name="brain[brand_color_1]" value="<?php echo esc_attr( VMSAI_Brain::get('brand_color_1') ); ?>" placeholder="#000000">
					</div>
					<div class="vmsai-field">
						<label><?php echo esc_html( $vmsai_schema['brand_color_2'] ); ?></label>
						<input type="text" name="brain[brand_color_2]" value="<?php echo esc_attr( VMSAI_Brain::get('brand_color_2') ); ?>" placeholder="#FFFFFF">
					</div>
				</div>
				<div class="vmsai-field">
					<label><?php echo esc_html( $vmsai_schema['brand_fonts'] ); ?></label>
					<input type="text" name="brain[brand_fonts]" value="<?php echo esc_attr( VMSAI_Brain::get('brand_fonts') ); ?>" placeholder="e.g. Modern Minimalist Sans-Serif">
				</div>
				<div class="vmsai-field">
					<label><?php echo esc_html( $vmsai_schema['visual_reference'] ); ?></label>
					<textarea name="brain[visual_reference]" rows="3" placeholder="Describe the overall 'vibe' of your brand visuals..."><?php echo esc_textarea( VMSAI_Brain::get('visual_reference') ); ?></textarea>
				</div>
			</div>
			<p><button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Save Brand Kit', 'vm-social-ai-pro' ); ?></button></p>
		</form>
	</section>

	<section class="vmsai-panel vmsai-settings-section" id="vmsai-brain-intel" style="display:none;">
		<div class="vmsai-panel__head">
			<h2 class="vmsai-display"><?php esc_html_e( 'Expertise & Intelligence', 'vm-social-ai-pro' ); ?></h2>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'vmsai_save' ); ?>
			<input type="hidden" name="action" value="vmsai_save">
			<input type="hidden" name="section" value="brain">

			<div class="vmsai-fields">
				<div class="vmsai-field vmsai-field--wide">
					<label><?php echo esc_html( $vmsai_schema[ 'lead_magnet' ] ); ?></label>
					<textarea name="brain[lead_magnet]" rows="12"><?php echo esc_textarea( VMSAI_Brain::get( 'lead_magnet' ) ); ?></textarea>
				</div>
			</div>

			<h3 class="vmsai-subhead" style="margin-top:40px;"><?php esc_html_e( 'News-Jacking (RAG) Feeds', 'vm-social-ai-pro' ); ?></h3>
			<div class="vmsai-field vmsai-field--wide">
				<label><?php esc_html_e( 'RSS Feeds (one per line)', 'vm-social-ai-pro' ); ?></label>
				<textarea name="rag_feeds" rows="4"><?php echo esc_textarea( implode( "\n", (array) get_option( VMSAI_RAG::OPTION, array() ) ) ); ?></textarea>
			</div>

			<p><button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Save Intel & Feeds', 'vm-social-ai-pro' ); ?></button></p>
		</form>
	</section>
</div>
