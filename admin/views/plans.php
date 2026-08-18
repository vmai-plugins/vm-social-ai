<?php
/**
 * Pricing and Plans view.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_current_plan = VMSAI_License::plan();
$vmsai_limits = VMSAI_License::limits();

// Cashfree Link Generator: Pointing to your central site.
$vmsai_checkout_base = 'https://vmstudio.digital/checkout/';
$vmsai_site_url = urlencode( home_url() );
?>

<style>
	.vmsai-plans-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-top: 30px; }
	.vmsai-plan-card {
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 16px;
		padding: 40px 30px; display: flex; flex-direction: column; position: relative;
		transition: all 0.3s ease;
	}
	.vmsai-plan-card.is-active { border-color: var(--gold); box-shadow: 0 10px 30px rgba(201, 162, 39, 0.1); }
	.vmsai-plan-card.is-active::after {
		content: "CURRENT PLAN"; position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
		background: var(--gold); color: var(--ink); font-size: 10px; font-weight: bold; padding: 4px 12px; border-radius: 20px;
	}

	.vmsai-plan-head h3 { margin: 0; font-family: var(--serif); font-size: 24px; color: var(--parchment); }
	.vmsai-plan-price { font-size: 36px; font-weight: bold; margin: 20px 0; color: var(--gold); }
	.vmsai-plan-price span { font-size: 14px; color: var(--muted); font-weight: normal; }

	.vmsai-plan-features { list-style: none; margin: 30px 0; padding: 0; flex-grow: 1; }
	.vmsai-plan-features li { margin-bottom: 12px; font-size: 13px; color: #ccc; display: flex; align-items: center; gap: 10px; }
	.vmsai-plan-features li::before { content: "✓"; color: var(--green); font-weight: bold; }
	.vmsai-plan-features li.is-missing { opacity: 0.4; text-decoration: line-through; }
	.vmsai-plan-features li.is-missing::before { content: "✕"; color: var(--red); }
</style>

<section class="vmsai-panel">
	<div class="vmsai-panel__head" style="text-align: center; display: block;">
		<h2 class="vmsai-display">Choose Your Growth Speed</h2>
		<p class="vmsai-lede" style="margin: 0 auto;">Select a plan to unlock high-performance AI engines and advanced social automation.</p>
	</div>

	<div class="vmsai-plans-grid">
		<!-- FREE PLAN -->
		<div class="vmsai-plan-card <?php echo 'free' === $vmsai_current_plan ? 'is-active' : ''; ?>">
			<div class="vmsai-plan-head">
				<h3>Starter</h3>
				<div class="vmsai-plan-price">FREE <span>/ forever</span></div>
			</div>
			<ul class="vmsai-plan-features">
				<li>1 Social Channel</li>
				<li>2 Posts per day</li>
				<li>Standard AI Engines</li>
				<li class="is-missing">News-Jacking</li>
				<li class="is-missing">Pro Video Engine</li>
				<li class="is-missing">Voice Lab Mimicry</li>
			</ul>
			<button class="vmsai-btn" disabled><?php 'free' === $vmsai_current_plan ? esc_html_e('Currently Active', 'vm-social-ai-pro') : esc_html_e('Free Tier', 'vm-social-ai-pro'); ?></button>
		</div>

		<!-- PRO PLAN -->
		<div class="vmsai-plan-card <?php echo 'pro' === $vmsai_current_plan ? 'is-active' : ''; ?>">
			<div class="vmsai-plan-head">
				<h3>Pro Growth</h3>
				<div class="vmsai-plan-price">₹1,999 <span>/ month</span></div>
			</div>
			<ul class="vmsai-plan-features">
				<li>10 Social Channels</li>
				<li>10 Posts per day</li>
				<li>News-Jacking & RAG</li>
				<li>Strategy Hub Access</li>
				<li class="is-missing">Pro Video Engine</li>
				<li class="is-missing">Executive Reporting</li>
			</ul>
			<a href="<?php echo $vmsai_checkout_base; ?>?plan=pro&site=<?php echo $vmsai_site_url; ?>" target="_blank" class="vmsai-btn vmsai-btn--gold">
				<?php echo 'pro' === $vmsai_current_plan ? esc_html__('Active', 'vm-social-ai-pro') : esc_html__('Upgrade via Cashfree', 'vm-social-ai-pro'); ?>
			</a>
		</div>

		<!-- ELITE PLAN -->
		<div class="vmsai-plan-card <?php echo 'elite' === $vmsai_current_plan ? 'is-active' : ''; ?>">
			<div class="vmsai-plan-head">
				<h3>Elite Agency</h3>
				<div class="vmsai-plan-price">₹4,999 <span>/ month</span></div>
			</div>
			<ul class="vmsai-plan-features">
				<li>Unlimited Everything</li>
				<li>Pro Video (Luma/Minimax)</li>
				<li>Voice Lab Recursive DNA</li>
				<li>Executive ROI Synthesis</li>
				<li>Production Kanban Board</li>
				<li>Priority Agent Support</li>
			</ul>
			<a href="<?php echo $vmsai_checkout_base; ?>?plan=elite&site=<?php echo $vmsai_site_url; ?>" target="_blank" class="vmsai-btn vmsai-btn--gold">
				<?php echo 'elite' === $vmsai_current_plan ? esc_html__('Active', 'vm-social-ai-pro') : esc_html__('Get Elite Access', 'vm-social-ai-pro'); ?>
			</a>
		</div>
	</div>

	<div style="margin-top: 50px; text-align: center;">
		<p class="vmsai-muted">Payments handled securely via **Cashfree Payments**. Subscriptions can be managed at vmstudio.digital.</p>
	</div>

	<section class="vmsai-panel" style="margin-top: 40px; border-top: 2px solid var(--gold);">
		<h2 class="vmsai-panel__title"><?php esc_html_e( 'Activate Your Key', 'vm-social-ai-pro' ); ?></h2>
		<p class="vmsai-lede"><?php esc_html_e( 'Enter the license key received via email to unlock your premium features.', 'vm-social-ai-pro' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;" onsubmit="this.querySelector('button').textContent='Verifying...'; this.querySelector('button').disabled=true;">
			<?php wp_nonce_field( 'vmsai_save' ); ?>
			<input type="hidden" name="action" value="vmsai_save">
			<input type="hidden" name="section" value="plans">

			<div class="vmsai-fields">
				<div class="vmsai-field">
					<label for="vmsai-license-key"><?php esc_html_e( 'License Key', 'vm-social-ai-pro' ); ?></label>
					<div style="display: flex; gap: 15px; align-items: center;">
						<?php
							$vmsai_saved_key = get_option( 'vmsai_license', array() )['key'] ?? '';
							$vmsai_display_key = get_transient( 'vmsai_attempted_key' ) ?: $vmsai_saved_key;
						?>
						<input type="text" id="vmsai-license-key" name="license_key" value="<?php echo esc_attr( $vmsai_display_key ); ?>" placeholder="VM-PRO-XXXX-XXXX" style="flex-grow: 1;">
						<button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Activate', 'vm-social-ai-pro' ); ?></button>
					</div>
				</div>
			</div>
		</form>
	</section>
</section>
