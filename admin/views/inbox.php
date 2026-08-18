<?php
/**
 * Engagement Inbox.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

// We fetch these via JS now, but we can seed the first 5 for speed if connected.
$vmsai_comments = VMSAI_Inbox::list_recent();
?>

<section class="vmsai-panel">
	<div class="vmsai-panel__head">
		<div>
			<h2 class="vmsai-display"><?php esc_html_e( 'Conversations', 'vm-social-ai-pro' ); ?></h2>
			<p class="vmsai-lede"><?php esc_html_e( 'Respond to your audience across all channels. Use the "Suggest" button to let the Brain draft a reply with sentiment analysis.', 'vm-social-ai-pro' ); ?></p>
		</div>
		<div class="vmsai-panel__tools">
			<button type="button" class="vmsai-btn" data-vmsai-action="refresh-inbox"><?php esc_html_e( 'Refresh Comments', 'vm-social-ai-pro' ); ?></button>
		</div>
	</div>

	<div class="vmsai-cards" id="vmsai-inbox-grid">
		<?php if ( ! $vmsai_comments ) : ?>
			<p class="vmsai-muted"><?php esc_html_e( 'No recent comments found on connected channels.', 'vm-social-ai-pro' ); ?></p>
		<?php else : ?>
			<?php foreach ( $vmsai_comments as $vmsai_item ) : ?>
				<div class="vmsai-card vmsai-inbox-item"
					data-id="<?php echo esc_attr( $vmsai_item['id'] ); ?>"
					data-author="<?php echo esc_attr( $vmsai_item['author'] ); ?>"
					data-channel="<?php echo esc_attr( $vmsai_item['channel'] ); ?>"
					data-text="<?php echo esc_attr( $vmsai_item['text'] ); ?>"
					data-rating="<?php echo esc_attr( $vmsai_item['rating'] ?? '' ); ?>">

					<div class="vmsai-card__body">
						<div class="vmsai-card__head">
							<span class="vmsai-chip"><?php echo esc_html( strtoupper( $vmsai_item['channel'] ) ); ?></span>
							<strong><?php echo esc_html( $vmsai_item['author'] ); ?></strong>
							<?php if ( ! empty( $vmsai_item['rating'] ) ) : ?>
								<span class="vmsai-rating" style="color: var(--gold-soft); margin-left: 10px;">
									<?php echo str_repeat( '★', (int) $vmsai_item['rating'] ); ?>
								</span>
							<?php endif; ?>
							<time><?php echo esc_html( date_i18n( 'j M, H:i', strtotime( $vmsai_item['time'] ) ) ); ?></time>
							<span class="vmsai-inbox-meta-tags" style="margin-left: 10px; display: flex; gap: 5px;">
								<?php if ( ! empty($vmsai_item['intent']) ) : ?>
									<span class="vmsai-chip"><?php echo esc_html($vmsai_item['intent']); ?></span>
								<?php endif; ?>
								<?php if ( ! empty($vmsai_item['lead_score']) ) : ?>
									<span class="vmsai-chip vmsai-chip--score" title="Lead Score">🔥 <?php echo (int) $vmsai_item['lead_score']; ?></span>
								<?php endif; ?>
							</span>
						</div>

						<p class="vmsai-card__copy">"<?php echo esc_html( $vmsai_item['text'] ); ?>"</p>

						<div class="vmsai-field vmsai-field--wide" style="margin-top: 15px;">
							<textarea placeholder="<?php esc_attr_e( 'Draft your reply...', 'vm-social-ai-pro' ); ?>" rows="2"></textarea>
						</div>

						<div class="vmsai-card__actions">
							<button type="button" class="vmsai-btn vmsai-btn--gold" data-vmsai-action="suggest-reply">
								<?php esc_html_e( 'Suggest Reply', 'vm-social-ai-pro' ); ?>
							</button>
							<button type="button" class="vmsai-btn">
								<?php esc_html_e( 'Send to Network', 'vm-social-ai-pro' ); ?>
							</button>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>

<section class="vmsai-panel">
	<h2 class="vmsai-panel__title"><?php esc_html_e( 'Manual Sandbox', 'vm-social-ai-pro' ); ?></h2>
	<p class="vmsai-hint"><?php esc_html_e( 'Simulate any comment to see how the AI detects sentiment and intent.', 'vm-social-ai-pro' ); ?></p>

	<div class="vmsai-card vmsai-inbox-item" data-author="Test User" data-channel="instagram" data-text="I had a bad experience with the shipping. Who do I talk to?">
		<div class="vmsai-card__body">
			<div class="vmsai-field vmsai-field--wide">
				<label><?php esc_html_e( 'Incoming Comment', 'vm-social-ai-pro' ); ?></label>
				<input type="text" class="vmsai-test-comment-input" value="I had a bad experience with the shipping. Who do I talk to?" oninput="this.closest('.vmsai-inbox-item').dataset.text = this.value">
			</div>

			<div class="vmsai-field vmsai-field--wide" style="margin-top: 15px;">
				<label><?php esc_html_e( 'AI Intelligence & Draft', 'vm-social-ai-pro' ); ?> <span class="vmsai-inbox-meta-tags"></span></label>
				<textarea rows="2"></textarea>
			</div>

			<div class="vmsai-card__actions">
				<button type="button" class="vmsai-btn vmsai-btn--gold" data-vmsai-action="suggest-reply">
					<?php esc_html_e( 'Suggest Reply', 'vm-social-ai-pro' ); ?>
				</button>
			</div>
		</div>
	</div>
</section>
