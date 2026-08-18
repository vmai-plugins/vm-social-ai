<?php
/**
 * Dashboard Refresh.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_campaign = VMSAI_Planner::active_campaign();
$vmsai_pace     = (array) ( $vmsai_campaign ? VMSAI_Analytics::pace( (int) $vmsai_campaign['id'] ) : array() );
$vmsai_queue    = (array) VMSAI_Analytics::queue_counts();
$vmsai_health   = (array) VMSAI_Circuit::state();
$vmsai_brain    = (int) VMSAI_Brain::completeness();

$vmsai_text_providers  = vmsai()->text_engine()->providers();
$vmsai_image_providers = vmsai()->image_engine()->providers();
$vmsai_channels        = vmsai()->channels()->all();
$vmsai_upcoming        = VMSAI_Analytics::upcoming( 48, 8 );
$vmsai_activity        = VMSAI_Analytics::recent_activity( 8 );
$vmsai_streak          = (array) VMSAI_Analytics::streak();
$vmsai_pulse           = (array) VMSAI_Analytics::weekly_pulse();

/**
 * Render one week-over-week figure.
 *
 * @param string $label Metric name.
 * @param array  $data  current/previous/delta triple.
 * @return void
 */
if ( ! function_exists( 'vmsai_pulse_stat' ) ) :
function vmsai_pulse_stat( $label, array $data ) {
	$delta = $data['delta'];
	$dir   = 'flat';

	if ( null === $delta ) {
		$dir = 'new';
	} elseif ( $delta > 0 ) {
		$dir = 'up';
	} elseif ( $delta < 0 ) {
		$dir = 'down';
	}

	$badge = array(
		'up'   => '▲ ' . abs( (int) $delta ) . '%',
		'down' => '▼ ' . abs( (int) $delta ) . '%',
		'flat' => '— 0%',
		'new'  => __( 'new', 'vm-social-ai-pro' ),
	)[ $dir ];
	?>
	<div class="vmsai-pulse-stat">
		<span class="vmsai-eyebrow"><?php echo esc_html( $label ); ?></span>
		<div class="vmsai-pulse-num"><?php echo esc_html( number_format_i18n( $data['current'] ) ); ?></div>
		<span class="vmsai-pulse-delta is-<?php echo esc_attr( $dir ); ?>"><?php echo esc_html( $badge ); ?></span>
		<span class="vmsai-pulse-prev"><?php
			/* translators: %s: previous week's value */
			printf( esc_html__( 'vs %s last week', 'vm-social-ai-pro' ), esc_html( number_format_i18n( $data['previous'] ) ) );
		?></span>
	</div>
	<?php
}
endif;
?>

<style>
	.vmsai-health-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
	.vmsai-health-col h4 { margin: 0 0 10px; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); }
	.vmsai-health-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: var(--ink); border-radius: 6px; font-size: 12px; margin-bottom: 6px; }
	.vmsai-health-row .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
	.vmsai-health-row .dot.ok { background: var(--green); }
	.vmsai-health-row .dot.bad { background: var(--red, #e05252); }
	.vmsai-health-row .dot.off { background: var(--muted); opacity: .5; }
	.vmsai-health-empty { font-size: 12px; color: var(--muted); padding: 8px 0; }
	.vmsai-activity-row { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--hairline); font-size: 12px; }
	.vmsai-activity-row:last-child { border-bottom: none; }
	.vmsai-activity-row .err { color: var(--red, #e05252); display: block; margin-top: 2px; }

	/* Streak + weekly pulse */
	.vmsai-pulse-bar {
		display: grid; grid-template-columns: 260px 1fr; gap: 30px; align-items: center;
		background: var(--panel); border: 1px solid var(--hairline); border-radius: 16px;
		padding: 24px 28px; margin-bottom: 30px;
	}
	.vmsai-streak { display: flex; align-items: center; gap: 16px; }
	.vmsai-streak__ring {
		width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center;
		font-size: 24px; font-weight: bold; font-family: var(--serif); flex-shrink: 0;
		border: 3px solid var(--hairline); color: var(--muted);
	}
	.vmsai-streak__ring.is-live { border-color: var(--gold); color: var(--gold); box-shadow: 0 0 20px rgba(201,162,39,0.18); }
	.vmsai-streak__ring.is-cold { border-color: var(--red, #e05252); color: var(--red, #e05252); }
	.vmsai-streak__label { font-size: 13px; font-weight: 600; color: var(--parchment); }
	.vmsai-streak__hint { font-size: 11px; color: var(--muted); margin-top: 3px; line-height: 1.4; }
	.vmsai-streak__hint a { color: var(--gold); text-decoration: none; font-weight: bold; }

	.vmsai-pulse-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
	.vmsai-pulse-stat { border-left: 1px solid var(--hairline); padding-left: 18px; }
	.vmsai-pulse-num { font-size: 26px; font-family: var(--serif); font-weight: bold; color: var(--parchment); margin: 4px 0 2px; line-height: 1.1; }
	.vmsai-pulse-delta { font-size: 11px; font-weight: bold; }
	.vmsai-pulse-delta.is-up { color: var(--green); }
	.vmsai-pulse-delta.is-down { color: var(--red, #e05252); }
	.vmsai-pulse-delta.is-flat, .vmsai-pulse-delta.is-new { color: var(--muted); }
	.vmsai-pulse-prev { display: block; font-size: 10px; color: var(--muted); margin-top: 3px; }

	@media (max-width: 1100px) {
		.vmsai-pulse-bar { grid-template-columns: 1fr; }
		.vmsai-pulse-grid { grid-template-columns: repeat(2, 1fr); }
	}
</style>

<style>
	/* Critical UI Fixes */
	.vmsai-commander-toggle {
		position: fixed !important; bottom: 30px !important; right: 30px !important;
		width: 65px !important; height: 65px !important; z-index: 99999 !important;
		background: var(--gold) !important; border-radius: 50% !important;
		display: grid !important; place-items: center !important; cursor: pointer !important;
		box-shadow: 0 8px 25px rgba(0,0,0,0.5) !important;
	}
	.vmsai-commander-toggle svg { width: 30px !important; height: 30px !important; fill: var(--ink) !important; }
	.vmsai-commander-popup {
		position: fixed !important; bottom: 110px !important; right: 30px !important;
		width: 420px !important; max-width: calc(100vw - 60px) !important;
		z-index: 99998 !important; border-radius: 16px !important; overflow: hidden !important;
		box-shadow: 0 15px 50px rgba(0,0,0,0.7) !important; border: 1px solid var(--gold) !important;
		display: flex !important; flex-direction: column !important;
		background: var(--panel) !important; opacity: 0; visibility: hidden; transform: translateY(20px); transition: all 0.3s ease;
	}
	.vmsai-commander-popup.is-open { opacity: 1 !important; visibility: visible !important; transform: translateY(0) !important; }

	/* Dashboard Components */
	.vmsai-grid--four { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
	.vmsai-stat-card {
		background: var(--panel); padding: 25px; border-radius: 12px; border: 1px solid var(--hairline);
		transition: all 0.3s ease; border-left: 4px solid var(--hairline);
	}
	.vmsai-stat-card:hover { transform: translateY(-5px); border-color: var(--gold); }
	.vmsai-stat-card[data-stat="brain"] { border-left-color: var(--gold); }
	.vmsai-stat-card[data-stat="reach"] { border-left-color: var(--green); }
	.vmsai-stat-card[data-stat="queue"] { border-left-color: var(--gold-soft); }
	.vmsai-stat-card[data-stat="conversion"] { border-left-color: #6c5ce7; }

	.vmsai-big-num { font-size: 36px; font-family: var(--serif); font-weight: bold; margin: 10px 0; color: var(--parchment); }
	.vmsai-mini-progress { height: 4px; background: var(--ink); border-radius: 2px; margin: 10px 0; overflow: hidden; }
	.vmsai-mini-progress div { height: 100%; }

	/* Custom Arc */
	.vmsai-pro-arc { background: linear-gradient(135deg, #14141a, #1a1a24); border: 1px solid var(--hairline); padding: 40px; border-radius: 16px; position: relative; overflow: hidden; }
	.vmsai-pro-arc::before { content: ""; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--gold); }
	.vmsai-arc-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
	.vmsai-pill { padding: 5px 12px; border-radius: 20px; font-size: 10px; font-weight: bold; letter-spacing: 1px; }
	.vmsai-pill--good { background: rgba(111, 168, 138, 0.2); color: var(--green); border: 1px solid var(--green); }
	.vmsai-pill--warn { background: rgba(201, 162, 39, 0.2); color: var(--gold); border: 1px solid var(--gold); }

	/* News Ticker */
	.vmsai-news-box { display: grid; gap: 10px; }
	.vmsai-news-row { background: var(--ink); padding: 12px 15px; border-radius: 8px; font-size: 13px; border-left: 2px solid var(--gold); color: #ccc; }

	/* Reporting Synthesis */
	.vmsai-report-body { background: var(--ink); padding: 30px; border-radius: 12px; border: 1px solid var(--hairline); line-height: 1.7; margin-top: 20px; }
	.vmsai-executive-summary { font-size: 14px; color: #ccc; }
	.vmsai-executive-summary p { margin-bottom: 15px; }
</style>

<!-- Floating Director -->
<div class="vmsai-commander-toggle" id="vmsai-commander-toggle" title="Chat with Director">
	<svg viewBox="0 0 24 24"><path d="M20,2H4c-1.1,0-2,0.9-2,2v18l4-4h14c1.1,0,2-0.9,2-2V4C22,2.9,21.1,2,20,2z"/></svg>
</div>

<section class="vmsai-commander-popup" id="vmsai-commander-popup">
	<div style="padding: 20px; background: var(--ink); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--hairline);">
		<h3 style="margin:0; font-size: 16px;">Strategic Director</h3>
		<span class="vmsai-pill vmsai-pill--good">AI ACTIVE</span>
	</div>
	<div id="vmsai-commander-log" style="height: 350px; overflow-y: auto; padding: 20px; background: var(--ink); line-height: 1.5;">
		<div style="margin-bottom: 15px;">
			<span class="vmsai-director-avatar" style="width:24px; height:24px; font-size:10px; margin-bottom:5px;">SD</span>
			<div style="background:var(--gold); color:var(--ink); padding:10px 14px; border-radius: 0 15px 15px 15px; font-size: 13px;">Ready for orders. How can I help you grow today?</div>
		</div>
	</div>
	<div style="padding: 20px; background: var(--raised); border-top: 1px solid var(--hairline);">
		<div style="display: flex; gap: 10px;">
			<input type="text" id="vmsai-commander-input" placeholder="Type a command..." style="flex-grow: 1; border-radius: 20px; padding: 8px 15px;">
			<button data-vmsai-action="commander-chat" class="vmsai-btn vmsai-btn--gold" style="border-radius: 50%; width: 38px; height: 38px; padding:0; display:grid; place-items:center;">🚀</button>
		</div>
	</div>
</section>

<!-- Streak + Weekly Pulse -->
<?php
$vmsai_streak_weeks = (int) $vmsai_streak['weeks'];
$vmsai_last_pub     = $vmsai_streak['last_published'];
$vmsai_days_quiet   = $vmsai_last_pub ? (int) floor( ( time() - strtotime( $vmsai_last_pub . ' UTC' ) ) / DAY_IN_SECONDS ) : -1;

// The ring turns red once a week has gone by with nothing published — that
// is the signal that the engine has stopped, whatever the totals say.
$vmsai_ring_state = 'is-cold';
if ( $vmsai_streak_weeks > 0 && $vmsai_days_quiet >= 0 && $vmsai_days_quiet <= 7 ) {
	$vmsai_ring_state = 'is-live';
}
?>
<div class="vmsai-pulse-bar">
	<div class="vmsai-streak">
		<div class="vmsai-streak__ring <?php echo esc_attr( $vmsai_ring_state ); ?>"><?php echo esc_html( $vmsai_streak_weeks ); ?></div>
		<div>
			<div class="vmsai-streak__label">
				<?php
				printf(
					esc_html( _n( '%s week streak', '%s week streak', $vmsai_streak_weeks, 'vm-social-ai-pro' ) ),
					esc_html( number_format_i18n( $vmsai_streak_weeks ) )
				);
				?>
			</div>
			<div class="vmsai-streak__hint">
				<?php if ( $vmsai_days_quiet < 0 ) : ?>
					<?php esc_html_e( 'Nothing published yet.', 'vm-social-ai-pro' ); ?>
					<a href="<?php echo esc_url( VMSAI_Admin::url( 'compose' ) ); ?>"><?php esc_html_e( 'Write your first post →', 'vm-social-ai-pro' ); ?></a>
				<?php elseif ( $vmsai_days_quiet > 7 ) : ?>
					<span style="color: var(--red, #e05252); font-weight: bold;">
						<?php
						printf(
							esc_html( _n( 'Nothing published for %s day.', 'Nothing published for %s days.', $vmsai_days_quiet, 'vm-social-ai-pro' ) ),
							esc_html( number_format_i18n( $vmsai_days_quiet ) )
						);
						?>
					</span>
					<a href="<?php echo esc_url( VMSAI_Admin::url( 'queue' ) ); ?>"><?php esc_html_e( 'Check the queue →', 'vm-social-ai-pro' ); ?></a>
				<?php elseif ( ! empty( $vmsai_streak['posted_today'] ) ) : ?>
					<?php
					printf(
						esc_html( _n( '%s post published today. Keep it going.', '%s posts published today. Keep it going.', (int) $vmsai_streak['posts_this_week'], 'vm-social-ai-pro' ) ),
						esc_html( number_format_i18n( (int) $vmsai_streak['posts_this_week'] ) )
					);
					?>
				<?php else : ?>
					<?php
					printf(
						esc_html( _n( '%s post this week. Nothing yet today.', '%s posts this week. Nothing yet today.', (int) $vmsai_streak['posts_this_week'], 'vm-social-ai-pro' ) ),
						esc_html( number_format_i18n( (int) $vmsai_streak['posts_this_week'] ) )
					);
					?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div>
		<span class="vmsai-eyebrow" style="display:block; margin-bottom:12px;">
			<?php esc_html_e( 'Weekly pulse · last 7 days vs the 7 before', 'vm-social-ai-pro' ); ?>
		</span>
		<div class="vmsai-pulse-grid">
			<?php
			vmsai_pulse_stat( __( 'Posts', 'vm-social-ai-pro' ), $vmsai_pulse['posts'] );
			vmsai_pulse_stat( __( 'Reach', 'vm-social-ai-pro' ), $vmsai_pulse['reach'] );
			vmsai_pulse_stat( __( 'Engagements', 'vm-social-ai-pro' ), $vmsai_pulse['engagements'] );
			vmsai_pulse_stat( __( 'Followers', 'vm-social-ai-pro' ), $vmsai_pulse['followers'] );
			?>
		</div>
	</div>
</div>

<!-- Stats Row -->
<div class="vmsai-grid vmsai-grid--four">
	<div class="vmsai-stat-card" data-stat="brain">
		<span class="vmsai-eyebrow">Strategy</span>
		<div class="vmsai-big-num"><?php echo (int) $vmsai_brain; ?>%</div>
		<div class="vmsai-mini-progress"><div style="width:<?php echo (int) $vmsai_brain; ?>%; background:var(--gold);"></div></div>
		<p class="vmsai-hint">Brain Completeness</p>
	</div>
	<div class="vmsai-stat-card" data-stat="reach">
		<span class="vmsai-eyebrow">Visibility</span>
		<div class="vmsai-big-num"><?php echo number_format_i18n( (int) ($vmsai_pace['views'] ?? 0) ); ?></div>
		<p class="vmsai-hint"><?php echo (int) ($vmsai_pace['progress'] ?? 0); ?>% of goal</p>
	</div>
	<div class="vmsai-stat-card" data-stat="queue">
		<span class="vmsai-eyebrow">Automation</span>
		<div class="vmsai-big-num"><?php echo number_format_i18n( $vmsai_queue['approved'] ); ?></div>
		<p class="vmsai-hint">Posts Scheduled</p>
	</div>
	<div class="vmsai-stat-card" data-stat="conversion">
		<span class="vmsai-eyebrow">Results</span>
		<div class="vmsai-big-num"><?php echo number_format_i18n( (int) ($vmsai_pace['clicks'] ?? 0) ); ?></div>
		<p class="vmsai-hint">Direct Link Clicks</p>
	</div>
</div>

<div class="vmsai-grid vmsai-grid--split" style="margin-bottom: 30px;">
	<section class="vmsai-panel">
		<div class="vmsai-panel__head">
			<h3 class="vmsai-subhead" style="margin:0;">Executive ROI Synthesis</h3>
			<?php if ( VMSAI_License::has_feature( 'executive_reporting' ) ) : ?>
				<button class="vmsai-btn vmsai-btn--gold" style="padding: 4px 10px; font-size: 11px;" data-vmsai-action="generate-report">✨ Generate</button>
			<?php endif; ?>
		</div>
		<div id="vmsai-reporting-container">
			<?php if ( VMSAI_License::has_feature( 'executive_reporting' ) ) : ?>
				<div style="text-align: center; padding: 30px 0; color: var(--muted);" id="vmsai-reporting-empty">
					<p style="font-size: 12px;">Click "Generate" for AI performance analysis.</p>
				</div>
				<div class="vmsai-report-body" id="vmsai-reporting-content" style="display:none;">
					<div class="vmsai-executive-summary" id="vmsai-rep-text"></div>
				</div>
			<?php else : ?>
				<div style="text-align: center; padding: 20px 0;">
					<p class="vmsai-muted" style="font-size: 12px;">Monthly ROI Reports are an **Elite** feature.</p>
					<a href="<?php echo esc_url( VMSAI_Admin::upgrade_url() ); ?>" style="font-size: 11px; color: var(--gold); text-decoration: none; font-weight: bold;">Upgrade Now →</a>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="vmsai-panel">
		<h3 class="vmsai-subhead" style="margin:0;">System Health</h3>
		<div class="vmsai-health-grid" style="grid-template-columns: 1fr; margin-top: 15px;">
			<div class="vmsai-health-row">
				<span><span class="dot ok"></span>Text Engine</span>
				<span>Active</span>
			</div>
			<div class="vmsai-health-row">
				<span><span class="dot ok"></span>Image Engine</span>
				<span>Active</span>
			</div>
			<div class="vmsai-health-row">
				<?php
					$vmsai_all_ready = true;
					foreach($vmsai_channels as $v_slug => $v_c) {
						if($v_c->is_connected() && !VMSAI_Circuit::is_open('channel:'.$v_slug)) $vmsai_all_ready = false;
					}
				?>
				<span><span class="dot <?php echo $vmsai_all_ready ? 'ok' : 'bad'; ?>"></span>Channels</span>
				<span><?php echo count($vmsai_channels); ?> Linked</span>
			</div>
		</div>
		<button type="button" class="vmsai-btn" style="width:100%; margin-top:15px;" data-vmsai-action="test-all">Run Full Diagnostics</button>
	</section>
</div>

<div class="vmsai-grid vmsai-grid--split" style="margin-bottom: 30px;">
	<section class="vmsai-panel">
		<h3 class="vmsai-subhead" style="margin-top:0;"><?php esc_html_e( 'Next 48 Hours', 'vm-social-ai-pro' ); ?></h3>
		<?php if ( ! $vmsai_upcoming ) : ?>
			<p class="vmsai-muted"><?php esc_html_e( 'Nothing scheduled in this window.', 'vm-social-ai-pro' ); ?></p>
		<?php else : ?>
			<?php foreach ( $vmsai_upcoming as $vmsai_row ) : ?>
				<div class="vmsai-activity-row">
					<span><?php echo esc_html( ucfirst( $vmsai_row['channel'] ) ); ?> — <?php echo esc_html( $vmsai_row['title'] ?: __( '(untitled)', 'vm-social-ai-pro' ) ); ?></span>
					<span class="vmsai-muted"><?php echo esc_html( get_date_from_gmt( $vmsai_row['scheduled_at'], 'D j M, H:i' ) ); ?></span>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>

	<section class="vmsai-panel">
		<h3 class="vmsai-subhead" style="margin-top:0;"><?php esc_html_e( 'Recent Activity', 'vm-social-ai-pro' ); ?></h3>
		<?php if ( ! $vmsai_activity ) : ?>
			<p class="vmsai-muted"><?php esc_html_e( 'Nothing published or failed yet.', 'vm-social-ai-pro' ); ?></p>
		<?php else : ?>
			<?php foreach ( $vmsai_activity as $vmsai_row ) : ?>
				<div class="vmsai-activity-row">
					<span>
						<?php echo 'published' === $vmsai_row['status'] ? '✅' : '⚠️'; ?>
						<?php echo esc_html( ucfirst( $vmsai_row['channel'] ) ); ?> — <?php echo esc_html( $vmsai_row['title'] ?: __( '(untitled)', 'vm-social-ai-pro' ) ); ?>
						<?php if ( 'failed' === $vmsai_row['status'] && $vmsai_row['last_error'] ) : ?>
							<span class="err"><?php echo esc_html( mb_substr( $vmsai_row['last_error'], 0, 120 ) ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( 'published' === $vmsai_row['status'] && $vmsai_row['permalink'] ) : ?>
						<a href="<?php echo esc_url( $vmsai_row['permalink'] ); ?>" target="_blank" class="vmsai-muted"><?php esc_html_e( 'View', 'vm-social-ai-pro' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
</div>

<?php if ( ! $vmsai_campaign ) : ?>
	<section class="vmsai-panel vmsai-empty">
		<h2 class="vmsai-display">System Status: Standby</h2>
		<p class="vmsai-lede">Complete the three-step ignition sequence to launch your autonomous campaign.</p>
		<div style="display: flex; gap: 20px; margin-top: 30px;">
			<div style="flex:1; background: var(--ink); padding: 25px; border-radius: 12px; text-align: center;">
				<h4 style="margin-bottom:15px;">1. Business DNA</h4>
				<a href="<?php echo esc_url( VMSAI_Admin::url( 'brain' ) ); ?>" class="vmsai-btn">Setup</a>
			</div>
			<div style="flex:1; background: var(--ink); padding: 25px; border-radius: 12px; text-align: center;">
				<h4 style="margin-bottom:15px;">2. Channels</h4>
				<a href="<?php echo esc_url( VMSAI_Admin::url( 'settings' ) . '#vmsai-section-channels' ); ?>" class="vmsai-btn">Connect</a>
			</div>
			<div style="flex:1; background: var(--ink); padding: 25px; border-radius: 12px; text-align: center;">
				<h4 style="margin-bottom:15px;">3. Target</h4>
				<a href="<?php echo esc_url( VMSAI_Admin::url( 'plan' ) ); ?>" class="vmsai-btn vmsai-btn--gold">Launch</a>
			</div>
		</div>
	</section>
<?php else : ?>
	<section class="vmsai-pro-arc">
		<div class="vmsai-arc-header">
			<div>
				<span class="vmsai-eyebrow">Active Run</span>
				<h2 class="vmsai-display" style="margin:5px 0 0!important;"><?php echo esc_html( $vmsai_campaign['name'] ); ?></h2>
			</div>
			<div class="vmsai-pill <?php echo $vmsai_pace['on_pace'] ? 'vmsai-pill--good' : 'vmsai-pill--warn'; ?>">
				<?php echo $vmsai_pace['on_pace'] ? 'OPTIMAL PACE' : 'BEHIND TARGET'; ?>
			</div>
		</div>

		<div style="font-size: 28px; font-weight: bold; margin-bottom: 10px;">
			<?php echo number_format_i18n( (int) $vmsai_pace['views'] ); ?> <span style="color:var(--muted); font-size: 16px;">/ <?php echo number_format_i18n( (int) $vmsai_pace['target'] ); ?> Views</span>
		</div>

		<div style="height: 12px; background: var(--ink); border-radius: 6px; overflow: hidden; margin: 20px 0; position: relative;">
			<div style="width:<?php echo (int) $vmsai_pace['progress']; ?>%; height: 100%; background: linear-gradient(90deg, var(--gold), var(--gold-soft)); box-shadow: 0 0 15px rgba(201,162,39,0.5);"></div>
		</div>

		<div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px;">
			<span>Day <?php echo (int) $vmsai_pace['day']; ?> of <?php echo (int) $vmsai_pace['horizon']; ?></span>
			<button class="vmsai-btn vmsai-btn--gold" data-vmsai-action="tick" style="margin-top: -10px;">Process Loop Now</button>
		</div>
	</section>
<?php endif; ?>

<script>
(function($) {
	$(document).on('click', '[data-vmsai-action="generate-report"]', function() {
		var $btn = $(this);
		$btn.text('Analyzing...').prop('disabled', true);
		$('#vmsai-reporting-empty').hide();

		VMSAI.api('/reporting/roi', 'GET').then( function(res) {
			$btn.text('✨ Regenerate').prop('disabled', false);
			if (res.ok) {
				var paras = res.report.split("\n\n").map( function(p) { return '<p>' + p + '</p>'; } ).join("");
				$('#vmsai-rep-text').html(paras);
				$('#vmsai-reporting-content').fadeIn();
			}
		});
	});
})(jQuery);
</script>
