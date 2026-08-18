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
