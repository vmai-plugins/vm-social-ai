<?php
/**
 * Usage and Billing view.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_days      = 30;
$vmsai_stats     = (array) VMSAI_Usage::stats( $vmsai_days );
$vmsai_breakdown = (array) VMSAI_Usage::breakdown( $vmsai_days );
$vmsai_license   = (array) get_option( 'vmsai_license', array( 'plan' => 'free', 'status' => 'active' ) );
?>

<section class="vmsai-panel">
	<div class="vmsai-panel__head">
		<h2 class="vmsai-display"><?php esc_html_e( 'Usage & Billing', 'vm-social-ai-pro' ); ?></h2>
		<span class="vmsai-chip vmsai-chip--score"><?php echo esc_html( strtoupper( $vmsai_license['plan'] ) ); ?> PLAN</span>
	</div>

	<div class="vmsai-grid vmsai-grid--split">
		<div>
			<p class="vmsai-lede"><?php printf( esc_html__( 'Total AI consumption for the last %d days.', 'vm-social-ai-pro' ), (int) $vmsai_days ); ?></p>

			<dl class="vmsai-figures" style="margin-top: 20px;">
				<div>
					<dt><?php esc_html_e( 'Estimated Cost', 'vm-social-ai-pro' ); ?></dt>
					<dd>$<?php echo number_format( (float) $vmsai_stats['total_cost'], 2 ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Total Tokens', 'vm-social-ai-pro' ); ?></dt>
					<dd><?php echo number_format_i18n( (int) $vmsai_stats['total_in'] + (int) $vmsai_stats['total_out'] ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'AI Generations', 'vm-social-ai-pro' ); ?></dt>
					<dd><?php echo number_format_i18n( (int) $vmsai_stats['total_calls'] ); ?></dd>
				</div>
			</dl>

			<div class="vmsai-note vmsai-note--good" style="margin-top: 20px;">
				<strong><?php esc_html_e( 'Value Created', 'vm-social-ai-pro' ); ?>:</strong>
				<?php
				$human_cost = (int) $vmsai_stats['total_calls'] * 15; // Estimating $15 per post for human agency work.
				printf( esc_html__( 'This content would have cost roughly $%s if produced by a traditional agency.', 'vm-social-ai-pro' ), number_format( $human_cost ) );
				?>
			</div>
		</div>

		<div class="vmsai-panel" style="background: var(--ink); border-color: var(--gold);">
			<h3 class="vmsai-subhead" style="margin-top: 0;"><?php esc_html_e( 'Plugin Subscription', 'vm-social-ai-pro' ); ?></h3>
			<p style="font-size: 13px; color: var(--muted);"><?php esc_html_e( 'Unlock advanced features like Viral Rescoring, News-Jacking, and multi-channel scheduling.', 'vm-social-ai-pro' ); ?></p>

			<?php if ( 'free' === $vmsai_license['plan'] ) : ?>
				<a href="https://vmstudio.digital/vm-social-ai/upgrade" target="_blank" class="vmsai-btn vmsai-btn--gold" style="width: 100%; text-align: center; margin-top: 10px;">
					<?php esc_html_e( 'Upgrade to PRO', 'vm-social-ai-pro' ); ?>
				</a>
			<?php else : ?>
				<div class="vmsai-note vmsai-note--good" style="margin-bottom: 0;">
					<?php esc_html_e( 'Your PRO subscription is active.', 'vm-social-ai-pro' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<h3 class="vmsai-subhead"><?php esc_html_e( 'Consumption Breakdown', 'vm-social-ai-pro' ); ?></h3>

	<?php if ( ! $vmsai_breakdown ) : ?>
		<p class="vmsai-muted"><?php esc_html_e( 'No usage recorded yet. Start a campaign or test an engine to see data here.', 'vm-social-ai-pro' ); ?></p>
	<?php else : ?>
		<table class="vmsai-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'vm-social-ai-pro' ); ?></th>
					<th><?php esc_html_e( 'Model', 'vm-social-ai-pro' ); ?></th>
					<th><?php esc_html_e( 'Modality', 'vm-social-ai-pro' ); ?></th>
					<th><?php esc_html_e( 'Generations', 'vm-social-ai-pro' ); ?></th>
					<th><?php esc_html_e( 'Tokens', 'vm-social-ai-pro' ); ?></th>
					<th><?php esc_html_e( 'Est. Cost', 'vm-social-ai-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $vmsai_breakdown as $row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( ucfirst( $row['provider'] ) ); ?></th>
						<td><code style="font-size: 11px;"><?php echo esc_html( $row['model'] ); ?></code></td>
						<td><span class="vmsai-chip"><?php echo esc_html( strtoupper( $row['modality'] ) ); ?></span></td>
						<td><?php echo number_format_i18n( (int) $row['calls'] ); ?></td>
						<td><?php echo 'image' === $row['modality'] ? '—' : number_format_i18n( (int) $row['tokens'] ); ?></td>
						<td>$<?php echo number_format( (float) $row['cost'], 4 ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

</section>
