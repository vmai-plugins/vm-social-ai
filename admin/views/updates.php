<?php
/**
 * GitHub Updates and Version Sync View.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_info = VMSAI_Github_Updater::instance()->get_remote_info( false );
$vmsai_has_update = ! empty( $vmsai_info['has_update'] );
$vmsai_latest = $vmsai_info['version'] ?? VMSAI_VERSION;
$vmsai_notes = $vmsai_info['release_notes'] ?? '';
$vmsai_checked = $vmsai_info['last_checked'] ?? '';
?>

<div class="vmsai-panel">
	<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
		<div>
			<h2 class="vmsai-display" style="margin: 0;"><?php esc_html_e( 'GitHub Updates &amp; Sync', 'vm-social-ai-pro' ); ?></h2>
			<p class="vmsai-lede" style="margin: 5px 0 0 0;">
				<?php esc_html_e( 'Check for updates directly from the official GitHub repository and install them with one click.', 'vm-social-ai-pro' ); ?>
			</p>
		</div>
		<div style="text-align: right;">
			<a href="https://github.com/vmai-plugins/vm-social-ai" target="_blank" rel="noopener noreferrer" class="vmsai-btn" style="display: inline-flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-external"></span>
				<?php esc_html_e( 'GitHub Repository', 'vm-social-ai-pro' ); ?>
			</a>
		</div>
	</div>

	<!-- Update Status Box -->
	<div class="vmsai-panel" style="background: var(--raised); border: 1px solid var(--hairline); border-radius: 8px; padding: 24px; margin-bottom: 25px;">
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
			<div>
				<dt style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted);"><?php esc_html_e( 'Installed Version', 'vm-social-ai-pro' ); ?></dt>
				<dd style="font-size: 22px; font-weight: 700; color: var(--parchment); margin: 4px 0 0 0;" id="vmsai-installed-ver"><?php echo esc_html( VMSAI_VERSION ); ?></dd>
			</div>
			<div>
				<dt style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted);"><?php esc_html_e( 'Latest on GitHub', 'vm-social-ai-pro' ); ?></dt>
				<dd style="font-size: 22px; font-weight: 700; color: var(--gold); margin: 4px 0 0 0;" id="vmsai-latest-ver"><?php echo esc_html( $vmsai_latest ); ?></dd>
			</div>
			<div>
				<dt style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted);"><?php esc_html_e( 'Update Status', 'vm-social-ai-pro' ); ?></dt>
				<dd style="margin: 6px 0 0 0;" id="vmsai-update-status-badge">
					<?php if ( $vmsai_has_update ) : ?>
						<span class="vmsai-chip" style="background: rgba(201,162,39,0.2); color: var(--gold); border: 1px solid var(--gold); padding: 4px 10px; font-weight: 600;">
							⚡ <?php esc_html_e( 'New Version Available', 'vm-social-ai-pro' ); ?>
						</span>
					<?php else : ?>
						<span class="vmsai-chip" style="background: rgba(111,168,138,0.2); color: var(--green); border: 1px solid var(--green); padding: 4px 10px; font-weight: 600;">
							✓ <?php esc_html_e( 'Up to date', 'vm-social-ai-pro' ); ?>
						</span>
					<?php endif; ?>
				</dd>
			</div>
			<div>
				<dt style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted);"><?php esc_html_e( 'Last Checked', 'vm-social-ai-pro' ); ?></dt>
				<dd style="font-size: 13px; color: var(--muted); margin: 6px 0 0 0;" id="vmsai-last-checked-time">
					<?php echo $vmsai_checked ? esc_html( $vmsai_checked ) : esc_html__( 'Never', 'vm-social-ai-pro' ); ?>
				</dd>
			</div>
		</div>

		<!-- Action Buttons -->
		<div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; padding-top: 16px; border-top: 1px solid var(--hairline);">
			<button type="button" class="vmsai-btn" data-vmsai-action="check-github-update" id="vmsai-btn-check-update">
				<span class="dashicons dashicons-update" style="font-size: 16px; vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Check for Updates', 'vm-social-ai-pro' ); ?>
			</button>

			<button type="button" class="vmsai-btn vmsai-btn--gold" data-vmsai-action="run-github-update" id="vmsai-btn-run-update">
				<span class="dashicons dashicons-download" style="font-size: 16px; vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Update from GitHub Now', 'vm-social-ai-pro' ); ?>
			</button>

			<span id="vmsai-update-spinner" style="display:none; color: var(--gold); font-size: 13px; align-items: center; gap: 6px;">
				<span class="spinner is-active" style="float:none; margin:0;"></span>
				<span id="vmsai-update-progress-text"><?php esc_html_e( 'Communicating with GitHub…', 'vm-social-ai-pro' ); ?></span>
			</span>
		</div>
	</div>

	<!-- Release Notes / Changelog preview -->
	<div id="vmsai-release-notes-panel" style="<?php echo ! empty( $vmsai_notes ) ? '' : 'display:none;'; ?> margin-bottom: 30px;">
		<h3 class="vmsai-panel__title" style="margin-bottom: 10px;"><?php esc_html_e( 'Release Notes / Changes', 'vm-social-ai-pro' ); ?></h3>
		<pre id="vmsai-release-notes-body" style="background: var(--ink); border: 1px solid var(--hairline); border-radius: 6px; padding: 16px; color: var(--parchment); font-family: var(--mono); font-size: 12px; line-height: 1.6; white-space: pre-wrap; max-height: 250px; overflow-y: auto;"><?php echo esc_html( $vmsai_notes ); ?></pre>
	</div>

	<!-- GitHub Settings Form -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'vmsai_save' ); ?>
		<input type="hidden" name="action" value="vmsai_save">
		<input type="hidden" name="section" value="updates">

		<section class="vmsai-panel" style="margin-top: 20px; border-top: 1px solid var(--hairline); padding-top: 20px;">
			<h3 class="vmsai-panel__title"><?php esc_html_e( 'GitHub Access Configuration (Optional)', 'vm-social-ai-pro' ); ?></h3>
			<p class="vmsai-lede"><?php esc_html_e( 'By default, public updates use keyless GitHub API calls. You can supply a GitHub Personal Access Token if accessing a private repo or to expand rate limits from 60 to 5,000 requests/hour.', 'vm-social-ai-pro' ); ?></p>

			<div class="vmsai-fields">
				<div class="vmsai-field vmsai-field--wide">
					<label for="vmsai-github-token"><?php esc_html_e( 'GitHub Personal Access Token (classic or fine-grained)', 'vm-social-ai-pro' ); ?></label>
					<input type="password" id="vmsai-github-token" name="github_token" value="<?php echo esc_attr( VMSAI_Settings::mask( 'github_token' ) ); ?>" placeholder="ghp_xxxxxxxxxxxxxxxxxxxx">
					<p class="vmsai-hint"><?php esc_html_e( 'Leave empty for public repository updates. Stored securely and masked.', 'vm-social-ai-pro' ); ?></p>
				</div>
			</div>

			<p style="margin-top: 20px;">
				<button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Save GitHub Settings', 'vm-social-ai-pro' ); ?></button>
			</p>
		</section>
	</form>
</div>
