<?php
/**
 * Channel connections.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_manager  = vmsai()->channels();
$vmsai_enabled  = (array) VMSAI_Settings::get( 'enabled_channels', array() );
$vmsai_approval = (array) VMSAI_Settings::get( 'require_approval', array() );
$vmsai_rendered_creds = array();

$vmsai_notes = array(
	'facebook'  => __( 'Connect to a Facebook Page (Personal Profiles are not supported). Use a Page Access Token with these scopes: pages_manage_posts, pages_read_engagement, pages_show_list.', 'vm-social-ai-pro' ),
	'instagram' => __( 'Needs a Business account linked to your Page. Reuses the Page token above. Scopes: instagram_basic, instagram_content_publish.', 'vm-social-ai-pro' ),
	'threads'   => __( 'Uses the Threads API. Requires a valid user token and User ID.', 'vm-social-ai-pro' ),
	'bluesky'   => __( 'Uses an App Password (not your main password). Get one under Settings > App Passwords on Bluesky.', 'vm-social-ai-pro' ),
	'x'         => __( 'Create a project in the X developer portal with read and write permissions, then generate access tokens for your own account.', 'vm-social-ai-pro' ),
	'linkedin'  => __( 'Requires an approved LinkedIn app with the Community Management API and the w_organization_social scope.', 'vm-social-ai-pro' ),
	'gbp'       => __( 'Uses a Google Cloud OAuth client with the Business Profile API enabled. The refresh token is generated once and reused.', 'vm-social-ai-pro' ),
	'tiktok'    => __( 'Requires a TikTok for Developers app with the video.upload.direct scope. Video assets must be offloaded to Cloudflare R2 for TikTok to fetch them.', 'vm-social-ai-pro' ),
	'youtube'   => __( 'Shares the Google credentials above, with the YouTube Data API enabled. Rendering a Short from a still image needs ffmpeg installed on the server.', 'vm-social-ai-pro' ),
	'telegram'  => __( 'Broadcast to a channel using a Telegram Bot. The bot must be an Admin of the channel.', 'vm-social-ai-pro' ),
);

$vmsai_tg_pass = substr( wp_hash( home_url() ), 0, 8 );
$vmsai_tg_webhook = rest_url('vm-social-ai/v1/telegram/webhook');
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'vmsai_save' ); ?>
	<input type="hidden" name="action" value="vmsai_save">
	<input type="hidden" name="section" value="channels">

	<section class="vmsai-panel">
		<h2 class="vmsai-display"><?php esc_html_e( 'Where the posts go', 'vm-social-ai-pro' ); ?></h2>
		<p class="vmsai-lede"><?php esc_html_e( 'Keys are encrypted before they touch the database. You can also define them as constants in wp-config.php, which takes priority over anything stored here.', 'vm-social-ai-pro' ); ?></p>
	</section>

	<?php foreach ( $vmsai_manager->all() as $vmsai_slug => $vmsai_channel ) : ?>
		<section class="vmsai-panel vmsai-channel<?php echo $vmsai_channel->is_connected() ? ' is-connected' : ''; ?>">
			<div class="vmsai-panel__head">
				<div>
					<h2 class="vmsai-panel__title"><?php echo esc_html( $vmsai_channel->label() ); ?></h2>
					<p class="vmsai-hint"><?php echo esc_html( $vmsai_notes[ $vmsai_slug ] ?? '' ); ?></p>
				</div>
				<span class="vmsai-status vmsai-status--<?php echo esc_attr( $vmsai_channel->is_connected() ? 'live' : 'down' ); ?>">
					<?php echo $vmsai_channel->is_connected() ? esc_html__( 'Connected', 'vm-social-ai-pro' ) : esc_html__( 'Not connected', 'vm-social-ai-pro' ); ?>
				</span>
				<button type="button" class="vmsai-btn vmsai-btn--gold" style="margin-left: 15px; padding: 4px 10px; font-size: 11px;" data-vmsai-action="test-channel" data-slug="<?php echo esc_attr( $vmsai_slug ); ?>">
					<?php esc_html_e( 'Test Connection', 'vm-social-ai-pro' ); ?>
				</button>
			</div>

			<div class="vmsai-toggles">
				<label class="vmsai-check">
					<input type="checkbox" name="enabled[<?php echo esc_attr( $vmsai_slug ); ?>]" value="1" <?php checked( ! empty( $vmsai_enabled[ $vmsai_slug ] ) ); ?>>
					<span><?php esc_html_e( 'Post to this channel', 'vm-social-ai-pro' ); ?></span>
				</label>
				<label class="vmsai-check">
					<input type="checkbox" name="approval[<?php echo esc_attr( $vmsai_slug ); ?>]" value="1" <?php checked( ! empty( $vmsai_approval[ $vmsai_slug ] ) ); ?>>
					<span><?php esc_html_e( 'Hold for my review first', 'vm-social-ai-pro' ); ?></span>
				</label>
			</div>

			<div class="vmsai-fields">
				<?php if ( 'facebook' === $vmsai_slug || 'instagram' === $vmsai_slug ) :
					$meta_profile = VMSAI_OAuth::meta_profile();
				?>
					<?php if ( $meta_profile['connected'] ) : ?>
						<div class="vmsai-field vmsai-field--wide" style="display:flex; align-items:center; justify-content:space-between; padding:14px 18px; background:rgba(34,197,94,0.06); border:1px solid rgba(34,197,94,0.25); border-radius:10px; margin-bottom:12px;">
							<div style="display:flex; align-items:center; gap:12px;">
								<?php if ( 'instagram' === $vmsai_slug && ! empty( $meta_profile['ig_picture'] ) ) : ?>
									<img src="<?php echo esc_url( $meta_profile['ig_picture'] ); ?>" style="width:36px; height:36px; border-radius:50%; border:2px solid #22c55e;">
								<?php else : ?>
									<div style="width:36px; height:36px; border-radius:50%; background:#1877f2; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">f</div>
								<?php endif; ?>
								<div>
									<div style="font-weight:700; font-size:13px; color:#fff;">
										<?php if ( 'instagram' === $vmsai_slug ) : ?>
											<?php echo esc_html( $meta_profile['ig_username'] ? '@' . $meta_profile['ig_username'] : 'Instagram Business' ); ?>
										<?php else : ?>
											<?php echo esc_html( $meta_profile['fb_page_name'] ?: 'Facebook Page' ); ?>
										<?php endif; ?>
									</div>
									<div style="font-size:11px; color:var(--muted);">1-Click OAuth Active · Permanent Token</div>
								</div>
							</div>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . VMSAI_Admin::SLUG . '&tab=settings&vmsai_oauth=meta_disconnect&nonce=' . wp_create_nonce( 'vmsai_meta_disconnect' ) ) ); ?>" class="vmsai-btn vmsai-btn--ghost" style="padding:4px 10px; font-size:11px; color:#ef4444; border-color:rgba(239,68,68,0.3);" onclick="return confirm('Disconnect Meta account?');">
								Disconnect
							</a>
						</div>
					<?php else : ?>
						<div class="vmsai-field vmsai-field--wide" style="padding:16px 18px; background:rgba(24,119,242,0.06); border:1px solid rgba(24,119,242,0.25); border-radius:10px; margin-bottom:12px;">
							<p style="margin:0 0 10px; font-size:12px; color:var(--text);">Connect your Page and Instagram Business Account automatically with permanent token generation.</p>
							<a href="<?php echo esc_url( VMSAI_OAuth::meta_connect_url() ); ?>" class="vmsai-btn vmsai-btn--gold" style="display:inline-flex; align-items:center; gap:8px; font-weight:700; text-decoration:none; padding:8px 16px; border-radius:6px; font-size:12px;">
								<svg width="14" height="14" viewBox="0 0 24 24"><path fill="#fff" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
								<span>Connect Facebook & Instagram (1-Click OAuth)</span>
							</a>
						</div>
					<?php endif; ?>

					<?php
					$vmsai_fb_has_creds = ( ! empty( VMSAI_Settings::credential( 'fb_page_id' ) ) || ! empty( VMSAI_Settings::credential( 'fb_page_token' ) ) );
					?>
					<details class="vmsai-field vmsai-field--wide" style="margin-bottom:10px;" <?php echo $vmsai_fb_has_creds ? 'open' : ''; ?>>
						<summary style="cursor:pointer; font-size:11px; color:var(--muted); font-weight:600;">⚙️ Advanced: Manual Credentials (Developer Mode) <?php echo $vmsai_fb_has_creds ? '<span style="color:#22c55e;">(Configured)</span>' : ''; ?></summary>
						<div style="margin-top:10px;">
							<?php
							foreach ( $vmsai_channel->credential_fields() as $vmsai_field => $vmsai_label ) :
								if ( in_array( $vmsai_field, $vmsai_rendered_creds, true ) ) {
									continue;
								}
								$vmsai_rendered_creds[] = $vmsai_field;
								$vmsai_secret = (bool) preg_match( '/(token|secret|key)$/', $vmsai_field );
								$vmsai_value  = $vmsai_secret ? VMSAI_Settings::mask( $vmsai_field ) : VMSAI_Settings::credential( $vmsai_field );
							?>
								<div class="vmsai-field" style="margin-top:8px;">
									<label for="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"><?php echo esc_html( $vmsai_label ); ?></label>
									<input type="<?php echo esc_attr( $vmsai_secret ? 'password' : 'text' ); ?>"
										id="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"
										name="<?php echo esc_attr( $vmsai_field ); ?>"
										autocomplete="off"
										value="<?php echo esc_attr( $vmsai_value ); ?>">
								</div>
							<?php endforeach; ?>
						</div>
					</details>
				<?php elseif ( 'youtube' === $vmsai_slug ) :
					$yt_profile = VMSAI_OAuth::youtube_profile();
				?>
					<?php if ( $yt_profile['connected'] ) : ?>
						<div class="vmsai-field vmsai-field--wide" style="display:flex; align-items:center; justify-content:space-between; padding:14px 18px; background:rgba(34,197,94,0.06); border:1px solid rgba(34,197,94,0.25); border-radius:10px; margin-bottom:12px;">
							<div style="display:flex; align-items:center; gap:12px;">
								<?php if ( ! empty( $yt_profile['channel_thumbnail'] ) ) : ?>
									<img src="<?php echo esc_url( $yt_profile['channel_thumbnail'] ); ?>" style="width:36px; height:36px; border-radius:50%; border:2px solid #22c55e;">
								<?php else : ?>
									<div style="width:36px; height:36px; border-radius:50%; background:#ff0000; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">YT</div>
								<?php endif; ?>
								<div>
									<div style="font-weight:700; font-size:13px; color:#fff;"><?php echo esc_html( $yt_profile['channel_title'] ); ?></div>
									<div style="font-size:11px; color:var(--muted);">
										YouTube Channel Active
										<?php if ( $yt_profile['subscriber_count'] > 0 ) : ?>
											· <?php echo esc_html( number_format( $yt_profile['subscriber_count'] ) ); ?> subscribers
										<?php endif; ?>
									</div>
								</div>
							</div>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . VMSAI_Admin::SLUG . '&tab=settings&vmsai_oauth=youtube_disconnect&nonce=' . wp_create_nonce( 'vmsai_youtube_disconnect' ) ) ); ?>" class="vmsai-btn vmsai-btn--ghost" style="padding:4px 10px; font-size:11px; color:#ef4444; border-color:rgba(239,68,68,0.3);" onclick="return confirm('Disconnect YouTube channel?');">
								Disconnect
							</a>
						</div>
					<?php else : ?>
						<div class="vmsai-field vmsai-field--wide" style="padding:16px 18px; background:rgba(255,0,0,0.06); border:1px solid rgba(255,0,0,0.25); border-radius:10px; margin-bottom:12px;">
							<p style="margin:0 0 10px; font-size:12px; color:var(--text);">Connect your YouTube channel in 1 click to publish YouTube Shorts automatically.</p>
							<a href="<?php echo esc_url( VMSAI_OAuth::youtube_connect_url() ); ?>" class="vmsai-btn vmsai-btn--gold" style="display:inline-flex; align-items:center; gap:8px; font-weight:700; text-decoration:none; padding:8px 16px; border-radius:6px; font-size:12px;">
								<svg width="14" height="14" viewBox="0 0 24 24"><path fill="#fff" d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
								<span>Connect YouTube Channel (1-Click OAuth)</span>
							</a>
						</div>
					<?php endif; ?>

					<?php
					$vmsai_yt_has_creds = ( ! empty( VMSAI_Settings::credential( 'google_client_id' ) ) || ! empty( VMSAI_Settings::credential( 'google_refresh_token' ) ) );
					?>
					<details class="vmsai-field vmsai-field--wide" style="margin-bottom:10px;" <?php echo $vmsai_yt_has_creds ? 'open' : ''; ?>>
						<summary style="cursor:pointer; font-size:11px; color:var(--muted); font-weight:600;">⚙️ Advanced: Manual Google OAuth Credentials <?php echo $vmsai_yt_has_creds ? '<span style="color:#22c55e;">(Configured)</span>' : ''; ?></summary>
						<div style="margin-top:10px;">
							<?php
							foreach ( $vmsai_channel->credential_fields() as $vmsai_field => $vmsai_label ) :
								if ( in_array( $vmsai_field, $vmsai_rendered_creds, true ) ) {
									continue;
								}
								$vmsai_rendered_creds[] = $vmsai_field;
								$vmsai_secret = (bool) preg_match( '/(token|secret|key)$/', $vmsai_field );
								$vmsai_value  = $vmsai_secret ? VMSAI_Settings::mask( $vmsai_field ) : VMSAI_Settings::credential( $vmsai_field );
							?>
								<div class="vmsai-field" style="margin-top:8px;">
									<label for="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"><?php echo esc_html( $vmsai_label ); ?></label>
									<input type="<?php echo esc_attr( $vmsai_secret ? 'password' : 'text' ); ?>"
										id="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"
										name="<?php echo esc_attr( $vmsai_field ); ?>"
										autocomplete="off"
										value="<?php echo esc_attr( $vmsai_value ); ?>">
								</div>
							<?php endforeach; ?>
						</div>
					</details>

					<div class="vmsai-field">
						<label for="vmsai-ffmpeg_path"><?php esc_html_e( 'ffmpeg path', 'vm-social-ai-pro' ); ?></label>
						<input type="text" id="vmsai-ffmpeg_path" name="ffmpeg_path" value="<?php echo esc_attr( VMSAI_Settings::credential( 'ffmpeg_path' ) ); ?>" placeholder="/usr/bin/ffmpeg">
						<p class="vmsai-hint"><?php esc_html_e( 'Leave blank to search the usual locations.', 'vm-social-ai-pro' ); ?></p>
					</div>
				<?php else : ?>
					<?php
					foreach ( $vmsai_channel->credential_fields() as $vmsai_field => $vmsai_label ) :
						if ( in_array( $vmsai_field, $vmsai_rendered_creds, true ) ) {
							continue;
						}
						$vmsai_rendered_creds[] = $vmsai_field;
					?>
						<div class="vmsai-field">
							<label for="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"><?php echo esc_html( $vmsai_label ); ?></label>
							<?php
							$vmsai_secret = (bool) preg_match( '/(token|secret|key)$/', $vmsai_field );
							$vmsai_value  = $vmsai_secret ? VMSAI_Settings::mask( $vmsai_field ) : VMSAI_Settings::credential( $vmsai_field );
							?>
							<input type="<?php echo esc_attr( $vmsai_secret ? 'password' : 'text' ); ?>"
								id="vmsai-<?php echo esc_attr( $vmsai_field ); ?>"
								name="<?php echo esc_attr( $vmsai_field ); ?>"
								autocomplete="off"
								value="<?php echo esc_attr( $vmsai_value ); ?>">
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php if ( 'telegram' === $vmsai_slug ) : ?>
					<div class="vmsai-field vmsai-field--wide" style="margin-top: 20px; padding: 20px; background: rgba(201, 162, 39, 0.05); border-radius: 12px; border: 1px solid var(--gold);">
						<h3 style="margin-top: 0; color: var(--gold);">📱 Mobile Command Center</h3>
						<p style="font-size: 12px; line-height: 1.6;">Control your agents from your phone. Send topics via Telegram to draft posts instantly.</p>

						<ol style="font-size: 11px; margin-left: 20px;">
							<li>Message your bot on Telegram.</li>
							<li>Send this command to authorize: <code>/start <?php echo $vmsai_tg_pass; ?></code></li>
							<li>You can then send <code>/write Your Topic</code> to draft content on the go.</li>
						</ol>

						<div style="margin-top: 15px; font-size: 10px; color: var(--muted);">
							<strong>Webhook URL:</strong> <code><?php echo esc_url($vmsai_tg_webhook); ?></code>
							<p>Use this URL in your bot settings if manual webhook registration is required.</p>
							<strong>Secret token:</strong> <code><?php echo esc_html( VMSAI_Telegram_Bot::webhook_secret() ); ?></code>
							<p>Pass this as the <code>secret_token</code> parameter on Telegram's <code>setWebhook</code> call (e.g. <code>https://api.telegram.org/bot&lt;TOKEN&gt;/setWebhook?url=&lt;WEBHOOK URL&gt;&amp;secret_token=&lt;SECRET&gt;</code>). Without it, this endpoint accepts any request that reaches it, not only ones from Telegram.</p>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( 'youtube' === $vmsai_slug ) : ?>
					<div class="vmsai-field">
						<label for="vmsai-ffmpeg_path"><?php esc_html_e( 'ffmpeg path', 'vm-social-ai-pro' ); ?></label>
						<input type="text" id="vmsai-ffmpeg_path" name="ffmpeg_path" value="<?php echo esc_attr( VMSAI_Settings::credential( 'ffmpeg_path' ) ); ?>" placeholder="/usr/bin/ffmpeg">
						<p class="vmsai-hint"><?php esc_html_e( 'Leave blank to search the usual locations.', 'vm-social-ai-pro' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endforeach; ?>

	<p><button type="submit" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Save connections', 'vm-social-ai-pro' ); ?></button></p>
</form>
