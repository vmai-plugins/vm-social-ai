<?php
/**
 * External Client Approval Portal.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Portal {

	public function register() {
		add_action( 'template_redirect', array( $this, 'render_portal' ) );
	}

	public function render_portal() {
		if ( ! isset( $_GET['vmsai_portal'] ) ) {
			return;
		}

		// These were read unguarded: any request to ?vmsai_portal=1 without
		// both params emitted warnings before the 403.
		if ( ! isset( $_GET['id'], $_GET['token'] ) ) {
			wp_die( 'Unauthorized or expired review link.', 'Social AI Portal', array( 'response' => 403 ) );
		}

		$queue_id = (int) $_GET['id'];
		$token    = sanitize_text_field( wp_unslash( $_GET['token'] ) );

		if ( ! $queue_id || ! VMSAI_Crypto::verify_portal_token( $queue_id, $token ) ) {
			wp_die( 'Unauthorized or expired review link.', 'Social AI Portal', array( 'response' => 403 ) );
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . VMSAI_Install::table('queue') . " WHERE id = %d", $queue_id ), ARRAY_A );

		if ( ! $row ) wp_die( 'Post no longer exists.' );

		// Only posts actually awaiting review may be acted on. Without this,
		// a review link could flip a processing/failed/published row back to
		// draft — effectively un-publishing live content.
		$actionable = in_array( $row['status'], array( 'draft', 'approved' ), true );

		$is_wl = (bool) VMSAI_Settings::get( 'white_label' );
		$brand = $is_wl ? VMSAI_Settings::get( 'agency_name' ) : 'VM Social AI';
		$logo_id = (int) VMSAI_Settings::get( 'watermark_id' );
		$logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';

		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>Social Post Review — <?php echo esc_html($brand); ?></title>
			<link rel="stylesheet" href="<?php echo VMSAI_URL; ?>admin/assets/css/admin.css">
			<style>
				body { background: #000; color: #fff; display: grid; place-items: center; min-height: 100vh; padding: 20px; }
				.portal-card { background: #111; border: 1px solid #c9a227; border-radius: 20px; max-width: 600px; width: 100%; overflow: hidden; }
				.portal-header { padding: 30px; text-align: center; border-bottom: 1px solid #222; }
				.portal-logo { max-height: 40px; margin-bottom: 15px; }
				.portal-media { width: 100%; aspect-ratio: 9/16; background: #000; display: grid; place-items: center; }
				.portal-media img, .portal-media video { max-width: 100%; max-height: 100%; }
				.portal-body { padding: 30px; }
				.portal-actions { display: flex; gap: 15px; margin-top: 30px; }
				.btn-approve { background: #c9a227; color: #000; flex: 1; padding: 15px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
				.btn-reject { background: #222; color: #fff; flex: 1; padding: 15px; border: 1px solid #444; border-radius: 8px; cursor: pointer; }
			</style>
		</head>
		<body class="vmsai vmsai--dark">
			<div class="portal-card">
				<div class="portal-header">
					<?php if($logo_url): ?><img src="<?php echo esc_url($logo_url); ?>" class="portal-logo"><?php endif; ?>
					<h1 style="font-size: 20px; margin: 0; color: #c9a227;"><?php echo esc_html($brand); ?> Portal</h1>
					<p style="font-size: 12px; color: #888; margin-top: 5px;">Secure Client Review Room</p>
				</div>

				<div class="portal-media">
					<?php if ( $row['media_url'] ) : ?>
						<?php if ( preg_match( '/\.(mp4|webm|mov|m4v)(\?|$)/i', (string) $row['media_url'] ) ) : ?>
							<video src="<?php echo esc_url( $row['media_url'] ); ?>" controls playsinline preload="metadata"></video>
						<?php else : ?>
							<img src="<?php echo esc_url( $row['media_url'] ); ?>" alt="">
						<?php endif; ?>
					<?php else : ?>
						<div style="color:#444;">No Preview Available</div>
					<?php endif; ?>
				</div>

				<div class="portal-body">
					<div style="font-size: 11px; text-transform: uppercase; color: #c9a227; font-weight: bold; margin-bottom: 10px;">Proposed Caption</div>
					<div style="line-height: 1.6; font-size: 15px; white-space: pre-wrap;"><?php echo esc_html($row['body']); ?></div>

					<?php if ( $actionable ) : ?>
					<div class="portal-actions" id="portal-actions">
						<button class="btn-approve" onclick="portalAction('approved')">Approve Post</button>
						<button class="btn-reject" onclick="portalAction('draft')">Request Edits</button>
					</div>
					<div id="portal-status" style="display:none; text-align:center; padding: 20px; font-weight: bold;"></div>
					<?php else : ?>
					<div style="text-align:center; padding: 20px; color:#888;">
						This post is not awaiting review (current status: <?php echo esc_html( $row['status'] ); ?>). This link is read-only.
					</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $actionable ) : ?>
			<script>
				function portalAction(status) {
					var reason = status === 'draft' ? prompt('What would you like to change?') : '';
					if (status === 'draft' && !reason) return;

					var btnBox = document.getElementById('portal-actions');
					var statusBox = document.getElementById('portal-status');
					btnBox.style.opacity = '0.3';
					btnBox.style.pointerEvents = 'none';

					fetch('<?php echo esc_url(rest_url(VMSAI_Rest::NS . "/queue/portal-update")); ?>', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({
							id: <?php echo (int) $queue_id; ?>,
							token: '<?php echo esc_js( $token ); ?>',
							status: status,
							reviewer_notes: reason
						})
					}).then( function(res) {
						return res.json();
					}).then( function(data) {
						if (data.ok) {
							btnBox.style.display = 'none';
							statusBox.style.display = 'block';
							statusBox.textContent = status === 'approved' ? '✓ Post Approved. Thank you!' : '✍️ Changes Requested.';
							statusBox.style.color = status === 'approved' ? '#2ecc71' : '#f1c40f';
						} else {
							alert('Error: ' + data.message);
							btnBox.style.opacity = '1';
							btnBox.style.pointerEvents = 'auto';
						}
					}).catch( function(err) {
						alert('Connection Error');
						btnBox.style.opacity = '1';
						btnBox.style.pointerEvents = 'auto';
					});
				}
				</script>
				<?php endif; ?>
			</body>
		</html>
		<?php
		exit;
	}
}
