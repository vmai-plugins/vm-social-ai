<?php
/**
 * Masthead and navigation.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_tabs     = VMSAI_Admin::tabs();
$vmsai_current  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification
$vmsai_current  = isset( $vmsai_tabs[ $vmsai_current ] ) ? $vmsai_current : 'dashboard';
$vmsai_autonomy = VMSAI_Settings::get( 'autonomy', 'assisted' );
$vmsai_ready    = vmsai()->channels()->ready();
$vmsai_chain    = vmsai()->text_engine()->chain();
$vmsai_plan     = VMSAI_License::plan();

// White-Label Logic
$vmsai_is_wl = (bool) VMSAI_Settings::get( 'white_label' );
$vmsai_brand = $vmsai_is_wl ? VMSAI_Settings::get( 'agency_name' ) : 'VM Studio Creatives';
$vmsai_name  = $vmsai_is_wl ? 'Social Engine' : 'Social AI';
?>

<header class="vmsai-masthead">
	<div class="vmsai-masthead__id">
		<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
			<span class="vmsai-eyebrow"><?php echo esc_html( $vmsai_brand ); ?></span>
			<span class="vmsai-chip vmsai-chip--<?php echo esc_attr( $vmsai_plan ); ?>" style="font-size: 8px; padding: 2px 6px;">
				<?php echo esc_html( strtoupper( $vmsai_plan ) ); ?>
			</span>
			<a href="<?php echo esc_url( VMSAI_Admin::url( 'settings' ) . '#vmsai-section-updates' ); ?>" class="vmsai-chip" style="font-size: 8px; padding: 2px 6px; text-decoration: none; color: var(--gold); border: 1px solid rgba(201,162,39,0.3); background: rgba(201,162,39,0.08);" title="<?php esc_attr_e( 'View GitHub Updates', 'vm-social-ai-pro' ); ?>">
				v<?php echo esc_html( VMSAI_VERSION ); ?>
			</a>
		</div>
		<h1 class="vmsai-wordmark"><?php echo esc_html( $vmsai_name ); ?></h1>
	</div>

	<dl class="vmsai-masthead__status">
		<div>
			<dt><?php esc_html_e( 'Mode', 'vm-social-ai-pro' ); ?></dt>
			<dd class="vmsai-status vmsai-status--<?php echo esc_attr( 'full' === $vmsai_autonomy ? 'live' : 'held' ); ?>">
				<?php
				echo esc_html(
					array(
						'full'     => __( 'Running unattended', 'vm-social-ai-pro' ),
						'assisted' => __( 'Review before sending', 'vm-social-ai-pro' ),
						'manual'   => __( 'Paused', 'vm-social-ai-pro' ),
					)[ $vmsai_autonomy ]
				);
				?>
			</dd>
		</div>
		<div>
			<dt><?php esc_html_e( 'Text engine', 'vm-social-ai-pro' ); ?></dt>
			<dd class="vmsai-status vmsai-status--<?php echo esc_attr( $vmsai_chain ? 'live' : 'down' ); ?>">
				<?php echo $vmsai_chain ? esc_html( implode( ' → ', $vmsai_chain ) ) : esc_html__( 'Not configured', 'vm-social-ai-pro' ); ?>
			</dd>
		</div>
		<div>
			<dt><?php esc_html_e( 'Channels', 'vm-social-ai-pro' ); ?></dt>
			<dd class="vmsai-status vmsai-status--<?php echo esc_attr( $vmsai_ready ? 'live' : 'down' ); ?>">
				<?php
				echo $vmsai_ready
					? esc_html( sprintf( /* translators: %d: channel count */ _n( '%d connected', '%d connected', count( $vmsai_ready ), 'vm-social-ai-pro' ), count( $vmsai_ready ) ) )
					: esc_html__( 'None connected', 'vm-social-ai-pro' );
				?>
			</dd>
		</div>
	</dl>
</header>

<nav class="vmsai-nav" aria-label="<?php esc_attr_e( 'Sections', 'vm-social-ai-pro' ); ?>">
	<?php foreach ( $vmsai_tabs as $vmsai_slug => $vmsai_label ) : ?>
		<a href="<?php echo esc_url( VMSAI_Admin::url( $vmsai_slug ) ); ?>"
			class="vmsai-nav__link<?php echo $vmsai_slug === $vmsai_current ? ' is-current' : ''; ?>"
			<?php echo $vmsai_slug === $vmsai_current ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( $vmsai_label ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<?php settings_errors( 'vmsai_settings' ); ?>

<?php if ( isset( $_GET['saved'] ) && empty( get_settings_errors( 'vmsai_settings' ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
	<div class="vmsai-note vmsai-note--good"><?php esc_html_e( 'Saved.', 'vm-social-ai-pro' ); ?></div>
<?php endif; ?>

<div id="vmsai-toast" class="vmsai-toast" role="status" aria-live="polite"></div>
