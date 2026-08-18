<?php
/**
 * Log viewer.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$vmsai_lines = VMSAI_Logger::recent( 200, $vmsai_level );
$vmsai_cron  = wp_next_scheduled( VMSAI_Install::CRON_TICK );
?>

<section class="vmsai-panel">
	<div class="vmsai-panel__head">
		<div>
			<h2 class="vmsai-display"><?php esc_html_e( 'What the engine has been doing', 'vm-social-ai-pro' ); ?></h2>
			<p class="vmsai-lede">
				<?php
				if ( $vmsai_cron ) {
					printf(
						/* translators: %s: human readable time difference */
						esc_html__( 'Next run in %s.', 'vm-social-ai-pro' ),
						esc_html( human_time_diff( time(), $vmsai_cron ) )
					);
				} else {
					esc_html_e( 'The scheduler is not registered. Deactivating and reactivating the plugin will restore it.', 'vm-social-ai-pro' );
				}
				?>
			</p>
		</div>
		<div class="vmsai-filters">
			<?php
			foreach ( array(
				''      => __( 'All', 'vm-social-ai-pro' ),
				'error' => __( 'Errors', 'vm-social-ai-pro' ),
				'warn'  => __( 'Warnings', 'vm-social-ai-pro' ),
				'info'  => __( 'Activity', 'vm-social-ai-pro' ),
			) as $vmsai_key => $vmsai_label ) :
				?>
				<a class="vmsai-filter<?php echo $vmsai_key === $vmsai_level ? ' is-current' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'level', $vmsai_key, VMSAI_Admin::url( 'logs' ) ) ); ?>">
					<?php echo esc_html( $vmsai_label ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( ! $vmsai_lines ) : ?>
		<p class="vmsai-muted"><?php esc_html_e( 'Nothing logged yet.', 'vm-social-ai-pro' ); ?></p>
	<?php else : ?>
		<table class="vmsai-table vmsai-table--log">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'When', 'vm-social-ai-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Level', 'vm-social-ai-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Where', 'vm-social-ai-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'What happened', 'vm-social-ai-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $vmsai_lines as $vmsai_line ) : ?>
					<tr class="is-<?php echo esc_attr( $vmsai_line['level'] ); ?>">
						<td class="vmsai-mono"><?php echo esc_html( get_date_from_gmt( $vmsai_line['created_at'], 'j M H:i:s' ) ); ?></td>
						<td><span class="vmsai-chip vmsai-chip--<?php echo esc_attr( $vmsai_line['level'] ); ?>"><?php echo esc_html( $vmsai_line['level'] ); ?></span></td>
						<td class="vmsai-mono"><?php echo esc_html( $vmsai_line['scope'] ); ?></td>
						<td>
							<?php echo esc_html( $vmsai_line['message'] ); ?>
							<?php if ( $vmsai_line['context'] ) : ?>
								<code class="vmsai-context"><?php echo esc_html( mb_substr( (string) $vmsai_line['context'], 0, 300 ) ); ?></code>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
