<?php
/**
 * General settings — Unified Hub.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_quiet = (array) VMSAI_Settings::get( 'quiet_hours', array( '01:00', '06:00' ) );
$vmsai_mark  = (int) VMSAI_Settings::get( 'watermark_id' );
$vmsai_level = VMSAI_Settings::get( 'log_level', 'info' );
?>

<div class="vmsai-nav-sub">
	<a href="#vmsai-section-general" class="is-active"><?php esc_html_e( 'General', 'vm-social-ai-pro' ); ?></a>
	<?php if ( current_user_can( 'vmsai_manage_keys' ) ) : ?>
		<a href="#vmsai-section-engines"><?php esc_html_e( 'AI Engines', 'vm-social-ai-pro' ); ?></a>
		<a href="#vmsai-section-channels"><?php esc_html_e( 'Social Channels', 'vm-social-ai-pro' ); ?></a>
	<?php endif; ?>
	<a href="#vmsai-section-logs"><?php esc_html_e( 'System Logs', 'vm-social-ai-pro' ); ?></a>
	<?php if ( current_user_can( 'vmsai_manage_keys' ) ) : ?>
		<a href="#vmsai-section-plans"><?php esc_html_e( 'Plan &amp; Licence', 'vm-social-ai-pro' ); ?></a>
	<?php endif; ?>
</div>

<div id="vmsai-section-general" class="vmsai-settings-section">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'vmsai_save' ); ?>
		<input type="hidden" name="action" value="vmsai_save">
		<input type="hidden" name="section" value="settings">

		<section class="vmsai-panel">
			<h2 class="vmsai-display"><?php esc_html_e( 'How much rope to give it', 'vm-social-ai-pro' ); ?></h2>

			<div class="vmsai-radios">
				<?php
				$vmsai_modes = array(
					'full'     => array( __( 'Run unattended', 'vm-social-ai-pro' ), __( 'Plans, writes, illustrates and publishes on its own. Nothing waits for you.', 'vm-social-ai-pro' ) ),
					'assisted' => array( __( 'Review before sending', 'vm-social-ai-pro' ), __( 'Everything is written and scheduled, but the channels you mark for review hold in the queue.', 'vm-social-ai-pro' ) ),
					'manual'   => array( __( 'Pause everything', 'vm-social-ai-pro' ), __( 'No planning, no writing, no publishing. Existing drafts stay where they are.', 'vm-social-ai-pro' ) ),
				);

				foreach ( $vmsai_modes as $vmsai_value => $vmsai_mode ) :
					?>
					<label class="vmsai-radio">
						<input type="radio" name="autonomy" value="<?php echo esc_attr( $vmsai_value ); ?>" <?php checked( VMSAI_Settings::get( 'autonomy' ), $vmsai_value ); ?>>
						<span>
							<strong><?php echo esc_html( $vmsai_mode[0] ); ?></strong>
							<em><?php echo esc_html( $vmsai_mode[1] ); ?></em>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Volume and timing', 'vm-social-ai-pro' ); ?></h2>

			<div class="vmsai-fields">
				<div class="vmsai-field">
					<label for="vmsai-perchannel"><?php esc_html_e( 'Posts per channel per day', 'vm-social-ai-pro' ); ?></label>
					<input type="number" id="vmsai-perchannel" name="per_channel_cap" min="1" max="12" value="<?php echo esc_attr( VMSAI_Settings::get( 'per_channel_cap', 2 ) ); ?>">
					<p class="vmsai-hint"><?php esc_html_e( 'Raising this is the fastest lever on reach, and also the fastest way to exhaust an audience. Two to three is the usual sweet spot.', 'vm-social-ai-pro' ); ?></p>
				</div>

				<div class="vmsai-field">
					<label for="vmsai-dailycap"><?php esc_html_e( 'Hard daily ceiling, all channels', 'vm-social-ai-pro' ); ?></label>
					<input type="number" id="vmsai-dailycap" name="daily_post_cap" min="1" max="200" value="<?php echo esc_attr( VMSAI_Settings::get( 'daily_post_cap', 24 ) ); ?>">
				</div>

				<div class="vmsai-field">
					<label for="vmsai-quietstart"><?php esc_html_e( 'Quiet hours start', 'vm-social-ai-pro' ); ?></label>
					<input type="time" id="vmsai-quietstart" name="quiet_start" value="<?php echo esc_attr( $vmsai_quiet[0] ?? '01:00' ); ?>">
				</div>

				<div class="vmsai-field">
					<label for="vmsai-quietend"><?php esc_html_e( 'Quiet hours end', 'vm-social-ai-pro' ); ?></label>
					<input type="time" id="vmsai-quietend" name="quiet_end" value="<?php echo esc_attr( $vmsai_quiet[1] ?? '06:00' ); ?>">
				</div>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Voice and links', 'vm-social-ai-pro' ); ?></h2>

			<div class="vmsai-fields">
				<div class="vmsai-field">
					<label for="vmsai-language"><?php esc_html_e( 'Primary Language', 'vm-social-ai-pro' ); ?></label>
					<select id="vmsai-language" name="language">
						<option value="en" <?php selected( VMSAI_Settings::get( 'language' ), 'en' ); ?>><?php esc_html_e( 'English', 'vm-social-ai-pro' ); ?></option>
						<option value="hi" <?php selected( VMSAI_Settings::get( 'language' ), 'hi' ); ?>><?php esc_html_e( 'Hindi', 'vm-social-ai-pro' ); ?></option>
					</select>
				</div>

				<div class="vmsai-field vmsai-field--wide">
					<label for="vmsai-flavour"><?php esc_html_e( 'Local flavour / Dialect', 'vm-social-ai-pro' ); ?></label>
					<select id="vmsai-flavour" name="locale_flavour">
						<option value="" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), '' ); ?>><?php esc_html_e( 'Standard (Neural Default)', 'vm-social-ai-pro' ); ?></option>
						<option value="hinglish" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'hinglish' ); ?>><?php esc_html_e( 'Hinglish (Urban Mix)', 'vm-social-ai-pro' ); ?></option>
						<option value="mumbai" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'mumbai' ); ?>><?php esc_html_e( 'Mumbai Tapori / Bambaiyya', 'vm-social-ai-pro' ); ?></option>
						<option value="delhi" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'delhi' ); ?>><?php esc_html_e( 'Delhi NCR (Aggressive/Bold)', 'vm-social-ai-pro' ); ?></option>
					</select>
					<p class="vmsai-hint"><?php esc_html_e( 'Passed to the strategic composer. Hinglish is a mix of Hindi and English for maximum Tier-2 city engagement.', 'vm-social-ai-pro' ); ?></p>
				</div>

				<div class="vmsai-field">
					<label for="vmsai-emoji"><?php esc_html_e( 'Emoji', 'vm-social-ai-pro' ); ?></label>
					<select id="vmsai-emoji" name="emoji_density">
						<?php
						foreach ( array(
							'none'  => __( 'None', 'vm-social-ai-pro' ),
							'light' => __( 'A few', 'vm-social-ai-pro' ),
							'heavy' => __( 'Plenty', 'vm-social-ai-pro' ),
						) as $vmsai_value => $vmsai_label ) :
							?>
							<option value="<?php echo esc_attr( $vmsai_value ); ?>" <?php selected( VMSAI_Settings::get( 'emoji_density' ), $vmsai_value ); ?>><?php echo esc_html( $vmsai_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="vmsai-field">
					<label for="vmsai-utm"><?php esc_html_e( 'UTM source', 'vm-social-ai-pro' ); ?></label>
					<input type="text" id="vmsai-utm" name="utm_source" value="<?php echo esc_attr( VMSAI_Settings::get( 'utm_source', 'vm-social-ai-pro' ) ); ?>">
				</div>

				<div class="vmsai-field vmsai-field--wide">
					<label for="vmsai-link"><?php esc_html_e( 'Where posts should send people', 'vm-social-ai-pro' ); ?></label>
					<input type="url" id="vmsai-link" name="link_in_bio" value="<?php echo esc_attr( VMSAI_Settings::get( 'link_in_bio', '' ) ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
				</div>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Brand mark on images', 'vm-social-ai-pro' ); ?></h2>

			<label class="vmsai-check">
				<input type="checkbox" name="brand_watermark" value="1" <?php checked( VMSAI_Settings::get( 'brand_watermark' ) ); ?>>
				<span><?php esc_html_e( 'Stamp every generated image with the logo', 'vm-social-ai-pro' ); ?></span>
			</label>

			<div class="vmsai-watermark">
				<input type="hidden" id="vmsai-watermark-id" name="watermark_id" value="<?php echo esc_attr( $vmsai_mark ); ?>">
				<div id="vmsai-watermark-preview" class="vmsai-watermark__preview">
					<?php if ( $vmsai_mark ) : ?>
						<img src="<?php echo esc_url( (string) wp_get_attachment_url( $vmsai_mark ) ); ?>" alt="">
					<?php endif; ?>
				</div>
				<button type="button" class="vmsai-btn" data-vmsai-action="pick-watermark"><?php esc_html_e( 'Choose a PNG logo', 'vm-social-ai-pro' ); ?></button>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Interface', 'vm-social-ai-pro' ); ?></h2>
			<div class="vmsai-fields">
				<div class="vmsai-field">
					<label for="vmsai-theme"><?php esc_html_e( 'Admin theme', 'vm-social-ai-pro' ); ?></label>
					<select id="vmsai-theme" name="admin_theme">
						<option value="dark" <?php selected( VMSAI_Settings::get( 'admin_theme' ), 'dark' ); ?>><?php esc_html_e( 'Obsidian (Dark)', 'vm-social-ai-pro' ); ?></option>
						<option value="light" <?php selected( VMSAI_Settings::get( 'admin_theme' ), 'light' ); ?>><?php esc_html_e( 'Parchment (Light)', 'vm-social-ai-pro' ); ?></option>
					</select>
				</div>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Cloud Storage (Experimental)', 'vm-social-ai-pro' ); ?></h2>
			<p class="vmsai-lede"><?php esc_html_e( 'Offload generated images to Cloudflare R2 to save disk space on your VPS. Social networks will fetch images directly from the cloud.', 'vm-social-ai-pro' ); ?></p>

			<div class="vmsai-fields">
				<div class="vmsai-field">
					<label for="vmsai-storage"><?php esc_html_e( 'Remote Storage', 'vm-social-ai-pro' ); ?></label>
					<select id="vmsai-storage" name="remote_storage">
						<option value="off" <?php selected( VMSAI_Settings::get( 'remote_storage' ), 'off' ); ?>><?php esc_html_e( 'Disabled (Local VPS)', 'vm-social-ai-pro' ); ?></option>
						<option value="r2" <?php selected( VMSAI_Settings::get( 'remote_storage' ), 'r2' ); ?>><?php esc_html_e( 'Cloudflare R2', 'vm-social-ai-pro' ); ?></option>
					</select>
				</div>
			</div>

			<div id="vmsai-r2-fields" style="<?php echo 'r2' === VMSAI_Settings::get( 'remote_storage' ) ? '' : 'display:none;'; ?>">
				<div class="vmsai-fields">
					<div class="vmsai-field">
						<label><?php esc_html_e( 'Account ID', 'vm-social-ai-pro' ); ?></label>
						<input type="text" name="r2_account_id" value="<?php echo esc_attr( VMSAI_Settings::credential( 'r2_account_id' ) ); ?>">
					</div>
					<div class="vmsai-field">
						<label><?php esc_html_e( 'Bucket Name', 'vm-social-ai-pro' ); ?></label>
						<input type="text" name="r2_bucket" value="<?php echo esc_attr( VMSAI_Settings::credential( 'r2_bucket' ) ); ?>">
					</div>
					<div class="vmsai-field">
						<label><?php esc_html_e( 'Access Key ID', 'vm-social-ai-pro' ); ?></label>
						<input type="text" name="r2_key" value="<?php echo esc_attr( VMSAI_Settings::credential( 'r2_key' ) ); ?>">
					</div>
					<div class="vmsai-field">
						<label><?php esc_html_e( 'Secret Access Key', 'vm-social-ai-pro' ); ?></label>
						<input type="password" name="r2_secret" value="<?php echo esc_attr( VMSAI_Settings::mask( 'r2_secret' ) ); ?>">
					</div>
					<div class="vmsai-field vmsai-field--wide">
						<label><?php esc_html_e( 'Public URL / Custom Domain', 'vm-social-ai-pro' ); ?></label>
						<input type="url" name="r2_public_url" value="<?php echo esc_attr( VMSAI_Settings::credential( 'r2_public_url' ) ); ?>" placeholder="https://pub-xyz.r2.dev">
						<p class="vmsai-hint"><?php esc_html_e( 'The base URL where your R2 bucket is publicly accessible.', 'vm-social-ai-pro' ); ?></p>
					</div>
				</div>

				<p>
					<button type="button" class="vmsai-btn" data-vmsai-action="offload-existing">
						<?php esc_html_e( 'Offload existing images to R2', 'vm-social-ai-pro' ); ?>
					</button>
					<span class="vmsai-hint"><?php esc_html_e( 'Copies all images currently in your queue to Cloudflare. This does not delete local files yet.', 'vm-social-ai-pro' ); ?></span>
				</p>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Video & Reels Source', 'vm-social-ai-pro' ); ?></h2>
			<p class="vmsai-lede"><?php esc_html_e( 'Choose where YouTube Shorts and Instagram Reels get their video background. Stock video often converts better for professional businesses.', 'vm-social-ai-pro' ); ?></p>

			<div class="vmsai-fields">
				<div class="vmsai-field">
					<label for="vmsai-video-source"><?php esc_html_e( 'Default Video Engine', 'vm-social-ai-pro' ); ?></label>
					<select id="vmsai-video-source" name="video_source">
						<option value="off" <?php selected( VMSAI_Settings::get( 'video_source' ), 'off' ); ?>><?php esc_html_e( 'Disabled (Ken Burns Still Image)', 'vm-social-ai-pro' ); ?></option>
						<option value="aipuffer" <?php selected( VMSAI_Settings::get( 'video_source' ), 'aipuffer' ); ?>><?php esc_html_e( 'AI Puffer (Google Veo)', 'vm-social-ai-pro' ); ?></option>
						<option value="pexels" <?php selected( VMSAI_Settings::get( 'video_source' ), 'pexels' ); ?>><?php esc_html_e( 'Pexels Stock Video (Recommended)', 'vm-social-ai-pro' ); ?></option>
					<option value="pollinations" <?php selected( VMSAI_Settings::get( 'video_source' ), 'pollinations' ); ?>><?php esc_html_e( 'Pollinations AI (Free / Keyless)', 'vm-social-ai-pro' ); ?></option>
					<option value="cogvideox" <?php selected( VMSAI_Settings::get( 'video_source' ), 'cogvideox' ); ?>><?php esc_html_e( 'CogVideoX (via Hugging Face)', 'vm-social-ai-pro' ); ?></option>
					<option value="minimax" <?php selected( VMSAI_Settings::get( 'video_source' ), 'minimax' ); ?>><?php esc_html_e( 'Minimax / Hailuo AI (Cinematic)', 'vm-social-ai-pro' ); ?></option>
					<option value="luma" <?php selected( VMSAI_Settings::get( 'video_source' ), 'luma' ); ?>><?php esc_html_e( 'Luma Dream Machine (Elite)', 'vm-social-ai-pro' ); ?></option>
					<option value="svd" <?php selected( VMSAI_Settings::get( 'video_source' ), 'svd' ); ?>><?php esc_html_e( 'Stable Video Diffusion (Local GPU)', 'vm-social-ai-pro' ); ?></option>
					</select>
				</div>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Agency White-Label', 'vm-social-ai-pro' ); ?></h2>
			<p class="vmsai-lede"><?php esc_html_e( 'Hide the VM Studio identity and replace it with your own brand across the dashboard.', 'vm-social-ai-pro' ); ?></p>

			<div class="vmsai-fields">
				<div class="vmsai-field">
					<label class="vmsai-check">
						<input type="checkbox" name="white_label" value="1" <?php checked( VMSAI_Settings::get( 'white_label' ) ); ?>>
						<span><?php esc_html_e( 'Enable White-Label Mode', 'vm-social-ai-pro' ); ?></span>
					</label>
				</div>
				<div class="vmsai-field">
					<label><?php esc_html_e( 'Agency / Brand Name', 'vm-social-ai-pro' ); ?></label>
					<input type="text" name="agency_name" value="<?php echo esc_attr( VMSAI_Settings::get( 'agency_name', 'Agency' ) ); ?>" placeholder="e.g. Acme Marketing">
				</div>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Usage & Billing', 'vm-social-ai-pro' ); ?></h2>
			<?php
			$vmsai_days      = 30;
			$vmsai_stats     = (array) VMSAI_Usage::stats( $vmsai_days );
			$vmsai_breakdown = (array) VMSAI_Usage::breakdown( $vmsai_days );
			?>
			<div class="vmsai-figures" style="margin-bottom: 20px;">
				<div>
					<dt><?php esc_html_e( 'Estimated Cost (30d)', 'vm-social-ai-pro' ); ?></dt>
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
			</div>
			<p class="vmsai-hint">Total AI consumption across all providers. Check the main Usage tab for detailed model breakdowns.</p>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Network & Connectivity', 'vm-social-ai-pro' ); ?></h2>
			<div class="vmsai-fields">
				<div class="vmsai-field vmsai-field--wide">
					<label><?php esc_html_e( 'Outbound Proxy URL', 'vm-social-ai-pro' ); ?></label>
					<input type="text" name="outbound_proxy" value="<?php echo esc_attr( VMSAI_Settings::credential( 'outbound_proxy' ) ); ?>" placeholder="http://user:pass@proxy.example.com:8080">
					<p class="vmsai-hint"><?php esc_html_e( 'Route social network API calls through a specific IP. Useful for large agencies to avoid rate limits.', 'vm-social-ai-pro' ); ?></p>
				</div>
			</div>
		</section>

		<section class="vmsai-panel">
			<h2 class="vmsai-panel__title"><?php esc_html_e( 'Failure handling and housekeeping', 'vm-social-ai-pro' ); ?></h2>

			<div class="vmsai-fields">
				<div class="vmsai-field">
					<label for="vmsai-threshold"><?php esc_html_e( 'Failures before a provider is benched', 'vm-social-ai-pro' ); ?></label>
					<input type="number" id="vmsai-threshold" name="circuit_threshold" min="2" max="20" value="<?php echo esc_attr( VMSAI_Settings::get( 'circuit_threshold', 5 ) ); ?>">
				</div>

				<div class="vmsai-field">
					<label for="vmsai-cooldown"><?php esc_html_e( 'How long it stays benched (seconds)', 'vm-social-ai-pro' ); ?></label>
					<input type="number" id="vmsai-cooldown" name="circuit_cooldown" min="300" max="86400" step="300" value="<?php echo esc_attr( VMSAI_Settings::get( 'circuit_cooldown', 1800 ) ); ?>">
				</div>

				<div class="vmsai-field">
					<label for="vmsai-retention"><?php esc_html_e( 'Keep logs for (days)', 'vm-social-ai-pro' ); ?></label>
					<input type="number" id="vmsai-retention" name="retention_days" min="7" max="730" value="<?php echo esc_attr( VMSAI_Settings::get( 'retention_days', 120 ) ); ?>">
				</div>

				<div class="vmsai-field">
					<label for="vmsai-loglevel"><?php esc_html_e( 'Log detail', 'vm-social-ai-pro' ); ?></label>
					<select id="vmsai-loglevel" name="log_level">
						<?php
						foreach ( array(
							'debug' => __( 'Everything', 'vm-social-ai-pro' ),
							'info'  => __( 'Normal', 'vm-social-ai-pro' ),
							'warn'  => __( 'Warnings and errors', 'vm-social-ai-pro' ),
							'error' => __( 'Errors only', 'vm-social-ai-pro' ),
						) as $vmsai_value => $vmsai_label ) :
							?>
							<option value="<?php echo esc_attr( $vmsai_value ); ?>" <?php selected( $vmsai_level, $vmsai_value ); ?>><?php echo esc_html( $vmsai_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="vmsai-field">
					<label class="vmsai-check">
						<input type="checkbox" name="alert_on_failure" value="1" <?php checked( VMSAI_Settings::get( 'alert_on_failure', 1 ) ); ?>>
						<span><?php esc_html_e( 'Email me when a provider gets benched or a post gives up for good', 'vm-social-ai-pro' ); ?></span>
					</label>
				</div>

				<div class="vmsai-field">
					<label for="vmsai-alert-email"><?php esc_html_e( 'Alert email (optional)', 'vm-social-ai-pro' ); ?></label>
					<input type="email" id="vmsai-alert-email" name="alert_email" value="<?php echo esc_attr( VMSAI_Settings::get( 'alert_email', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					<p class="vmsai-hint"><?php esc_html_e( 'Leave blank to use the site admin email.', 'vm-social-ai-pro' ); ?></p>
				</div>
			</div>

			<p><button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Save settings', 'vm-social-ai-pro' ); ?></button></p>
		</section>
	</form>
</div>

<div id="vmsai-section-engines" class="vmsai-settings-section" style="display:none;">
	<?php include VMSAI_PATH . 'admin/views/engines.php'; ?>
</div>

<div id="vmsai-section-channels" class="vmsai-settings-section" style="display:none;">
	<?php include VMSAI_PATH . 'admin/views/channels.php'; ?>
</div>

<div id="vmsai-section-logs" class="vmsai-settings-section" style="display:none;">
	<?php include VMSAI_PATH . 'admin/views/logs.php'; ?>
</div>

<?php if ( current_user_can( 'vmsai_manage_keys' ) ) : ?>
	<div id="vmsai-section-plans" class="vmsai-settings-section" style="display:none;">
		<?php include VMSAI_PATH . 'admin/views/plans.php'; ?>
	</div>
<?php endif; ?>
