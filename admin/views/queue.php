<?php
/**
 * War Room (Queue) Refresh.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$vmsai_table   = VMSAI_Install::table( 'queue' );
$vmsai_filters = array(
	'draft'     => array( 'label' => __( 'Drafts', 'vm-social-ai-pro' ), 'icon' => '📝' ),
	'approved'  => array( 'label' => __( 'Scheduled', 'vm-social-ai-pro' ), 'icon' => '📅' ),
	'published' => array( 'label' => __( 'Live', 'vm-social-ai-pro' ), 'icon' => '✅' ),
	'failed'    => array( 'label' => __( 'Failed', 'vm-social-ai-pro' ), 'icon' => '⚠️' ),
);

// Only the known buckets are addressable; anything else silently rendered an
// empty page that looked like "you have no posts" rather than "that filter
// does not exist".
$vmsai_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'draft'; // phpcs:ignore WordPress.Security.NonceVerification
if ( 'all' !== $vmsai_filter && ! isset( $vmsai_filters[ $vmsai_filter ] ) ) {
	$vmsai_filter = 'draft';
}

$vmsai_per_page = 30;
$vmsai_paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
$vmsai_offset   = ( $vmsai_paged - 1 ) * $vmsai_per_page;

if ( 'all' === $vmsai_filter ) {
	$vmsai_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$vmsai_table`" ); // phpcs:ignore
	$vmsai_posts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$vmsai_table` ORDER BY id DESC LIMIT %d OFFSET %d", $vmsai_per_page, $vmsai_offset ), ARRAY_A ); // phpcs:ignore
} else {
	$vmsai_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$vmsai_table` WHERE status = %s", $vmsai_filter ) ); // phpcs:ignore
	$vmsai_order = 'published' === $vmsai_filter ? 'published_at DESC' : 'scheduled_at ASC';
	$vmsai_posts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$vmsai_table` WHERE status = %s ORDER BY $vmsai_order LIMIT %d OFFSET %d", $vmsai_filter, $vmsai_per_page, $vmsai_offset ), ARRAY_A ); // phpcs:ignore
}

$vmsai_pages  = max( 1, (int) ceil( $vmsai_total / $vmsai_per_page ) );
$vmsai_counts = VMSAI_Analytics::queue_counts();

// Real performance numbers for the Live tab, fetched in one query rather
// than one per card.
$vmsai_metrics = 'published' === $vmsai_filter
	? VMSAI_Analytics::metrics_for( wp_list_pluck( $vmsai_posts, 'id' ) )
	: array();

/*
 * Build a day-by-day timeline. Posts are grouped under the local date they
 * are due (or went out), and on the Scheduled tab the plan slots that have
 * not been written yet are folded in at their reserved time — those are the
 * gaps, and they are invisible everywhere else in the admin.
 */
$vmsai_timeline = array();

foreach ( $vmsai_posts as $vmsai_p ) {
	$vmsai_when = 'published' === $vmsai_filter && ! empty( $vmsai_p['published_at'] )
		? $vmsai_p['published_at']
		: $vmsai_p['scheduled_at'];

	// A row with no timestamp at all still has to appear somewhere.
	$vmsai_local = $vmsai_when ? get_date_from_gmt( $vmsai_when, 'Y-m-d H:i:s' ) : '';
	$vmsai_day   = $vmsai_local ? substr( $vmsai_local, 0, 10 ) : '0000-00-00';

	$vmsai_timeline[ $vmsai_day ][] = array(
		'sort'  => $vmsai_local ?: '0000-00-00 00:00:00',
		'time'  => $vmsai_local ? mysql2date( 'g:i A', $vmsai_local ) : '—',
		'type'  => 'post',
		'row'   => $vmsai_p,
	);
}

/*
 * Plan slots are folded into the tab that matches their state: a reserved or
 * in-flight slot belongs with the schedule, a slot whose composition failed
 * belongs with the other failures. Previously every slot landed on Scheduled
 * while the failed ones were counted on the Failed tab, so the badge and the
 * list disagreed.
 */
$vmsai_slot_statuses = array();
if ( 'approved' === $vmsai_filter ) {
	$vmsai_slot_statuses = array( 'planned', 'processing' );
} elseif ( 'failed' === $vmsai_filter ) {
	$vmsai_slot_statuses = array( 'failed' );
}

if ( $vmsai_slot_statuses && 1 === $vmsai_paged ) {
	// Past-dated failures still matter, so the date floor only applies to the
	// forward-looking schedule.
	$vmsai_future_only = 'failed' !== $vmsai_filter;

	foreach ( VMSAI_Planner::uncomposed_slots( 40, $vmsai_slot_statuses, $vmsai_future_only ) as $vmsai_slot ) {
		$vmsai_local = trim( $vmsai_slot['slot_date'] . ' ' . ( $vmsai_slot['slot_time'] ?: '10:00:00' ) );

		$vmsai_timeline[ $vmsai_slot['slot_date'] ][] = array(
			'sort' => $vmsai_local,
			'time' => mysql2date( 'g:i A', $vmsai_local ),
			'type' => 'slot',
			'row'  => $vmsai_slot,
		);
	}
}

ksort( $vmsai_timeline );

foreach ( $vmsai_timeline as &$vmsai_day_rows ) {
	usort( $vmsai_day_rows, fn( $a, $b ) => strcmp( $a['sort'], $b['sort'] ) );
}
unset( $vmsai_day_rows );

// The Live tab reads newest-first; everything else is a runway, so it reads
// forwards from today.
if ( 'published' === $vmsai_filter ) {
	$vmsai_timeline = array_reverse( $vmsai_timeline, true );
}

$vmsai_today    = current_time( 'Y-m-d' );
$vmsai_tomorrow = gmdate( 'Y-m-d', strtotime( $vmsai_today . ' +1 day' ) );

/**
 * Human label for a timeline day.
 *
 * @param string $date     Y-m-d.
 * @param string $today    Y-m-d.
 * @param string $tomorrow Y-m-d.
 * @return string
 */
if ( ! function_exists( 'vmsai_day_label' ) ) :
function vmsai_day_label( $date, $today, $tomorrow ) {
	if ( '0000-00-00' === $date ) {
		return __( 'Unscheduled', 'vm-social-ai-pro' );
	}
	if ( $date === $today ) {
		return __( 'Today', 'vm-social-ai-pro' );
	}
	if ( $date === $tomorrow ) {
		return __( 'Tomorrow', 'vm-social-ai-pro' );
	}

	return mysql2date( 'l', $date );
}
endif;
?>

<style>
	/* Pro Queue Styling */
	.vmsai-tabs-nav { display: flex; gap: 10px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--hairline); }
	.vmsai-tab-link {
		display: flex; align-items: center; gap: 12px; padding: 12px 20px;
		background: var(--ink); border-radius: 8px; text-decoration: none;
		color: var(--muted); border: 1px solid var(--hairline); transition: all 0.2s ease;
	}
	.vmsai-tab-link.is-active { background: var(--raised); border-color: var(--gold); color: var(--gold); }
	.vmsai-tab-link .vmsai-tab-count { background: var(--hairline); padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
	.vmsai-tab-link.is-active .vmsai-tab-count { background: var(--gold); color: var(--ink); }

	.vmsai-pro-cards { display: grid; gap: 25px; }
	.vmsai-card-v2 {
		display: grid; grid-template-columns: 320px 1fr; background: var(--panel);
		border-radius: 16px; overflow: hidden; border: 1px solid var(--hairline);
		box-shadow: 0 5px 25px rgba(0,0,0,0.2); transition: transform 0.2s ease;
		min-height: 400px;
	}
	.vmsai-card-v2:hover { transform: scale(1.005); border-color: var(--gold); }

	.vmsai-card-v2__media {
		position: relative; background: #0a0a0a; display: flex; flex-direction: column;
		justify-content: center; align-items: center; overflow: hidden;
		border-right: 1px solid var(--hairline);
	}
	.vmsai-card-v2__media-wrap {
		width: 100%; height: 100%; display: grid; place-items: center;
		background: radial-gradient(circle, #1a1a1a 0%, #000 100%);
	}
	.vmsai-card-v2__media img { max-width: 100%; max-height: 100%; object-fit: contain; }

	.vmsai-card-v2__media-controls {
		position: absolute; bottom: 0; left: 0; right: 0;
		background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
		padding: 20px 15px 15px; display: flex; align-items: center; gap: 8px;
		opacity: 0; transition: opacity 0.2s ease;
	}
	.vmsai-card-v2:hover .vmsai-card-v2__media-controls { opacity: 1; }

	.vmsai-media-provider-select {
		background: rgba(255,255,255,0.1) !important; border: 1px solid rgba(255,255,255,0.2) !important;
		color: #fff !important; font-size: 11px !important; height: 28px !important;
		padding: 0 8px !important; flex-grow: 1; border-radius: 4px;
	}
	.vmsai-media-provider-select option { background: #111; color: #fff; }

	.vmsai-card-v2__media .vmsai-media-tag {
		position: absolute; top: 15px; left: 15px; background: var(--gold);
		padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: bold; color: #000;
		text-transform: uppercase; letter-spacing: 0.05em; z-index: 2;
	}

	.vmsai-card-v2__body { padding: 30px; display: flex; flex-direction: column; }
	.vmsai-card-v2__meta { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }

	.vmsai-badge { padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
	.vmsai-badge--fb { background: #1877f2; color: #fff; }
	.vmsai-badge--ig { background: linear-gradient(45deg, #f09433, #dc2743, #bc1888); color: #fff; }
	.vmsai-badge--li { background: #0a66c2; color: #fff; }
	.vmsai-badge--x { background: #000; color: #fff; border: 1px solid #333; }

	.vmsai-score-tag { background: var(--ink); padding: 4px 10px; border-radius: 4px; font-size: 11px; border: 1px solid var(--hairline); }

	.vmsai-editor-box { flex-grow: 1; margin-bottom: 20px; }
	.vmsai-editor-box textarea {
		background: var(--ink) !important; border: 1px solid var(--hairline) !important;
		padding: 15px !important; border-radius: 8px !important; line-height: 1.6 !important;
	}

	.vmsai-card-actions {
		display: flex; justify-content: space-between; align-items: center;
		padding-top: 20px; border-top: 1px solid var(--hairline);
	}

	/* Day-grouped timeline */
	.vmsai-day-head {
		display: flex; align-items: baseline; gap: 10px;
		margin: 34px 0 16px; padding-bottom: 10px;
		border-bottom: 1px solid var(--hairline);
	}
	.vmsai-day-head:first-child { margin-top: 0; }
	.vmsai-day-head h3 { margin: 0; font-size: 17px; font-family: var(--serif); color: var(--parchment); }
	.vmsai-day-head span { font-size: 13px; color: var(--muted); }
	.vmsai-day-head .vmsai-day-count { margin-left: auto; font-size: 11px; color: var(--muted); }

	.vmsai-slot-row { display: grid; grid-template-columns: 92px 1fr; gap: 18px; margin-bottom: 18px; align-items: start; }
	.vmsai-slot-time { font-size: 12px; color: var(--muted); text-align: right; padding-top: 14px; white-space: nowrap; }

	/* A reserved time with nothing written for it yet */
	.vmsai-ghost {
		border: 1px dashed var(--hairline); border-radius: 12px; padding: 16px 20px;
		display: flex; align-items: center; gap: 14px; background: rgba(255,255,255,0.012);
	}
	.vmsai-ghost:hover { border-color: var(--gold); }
	.vmsai-ghost__body { flex-grow: 1; min-width: 0; }
	.vmsai-ghost__topic { font-size: 13px; color: #ccc; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.vmsai-ghost__meta { font-size: 11px; color: var(--muted); margin-top: 3px; }
	.vmsai-ghost__meta.is-failed { color: var(--red, #e05252); }

	/* Performance strip on published posts */
	.vmsai-metric-strip {
		display: flex; flex-wrap: wrap; gap: 26px; margin-top: 18px; padding-top: 16px;
		border-top: 1px solid var(--hairline);
	}
	.vmsai-metric-strip div { min-width: 60px; }
	.vmsai-metric-strip dt { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin: 0 0 4px; }
	.vmsai-metric-strip dd { margin: 0; font-size: 17px; font-weight: 600; color: var(--parchment); }
	.vmsai-metric-empty { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--hairline); font-size: 11px; color: var(--muted); }

	.vmsai-btn-group { display: flex; flex-wrap: wrap; gap: 6px; }

	/* Media that could not be loaded says so, instead of leaving a torn-image
	   icon centred on a large black panel. */
	.vmsai-card-v2__media-wrap.is-missing::after {
		content: "Image unavailable";
		font-size: 11px; color: var(--muted); letter-spacing: .04em;
	}

	@media (max-width: 900px) {
		.vmsai-slot-row { grid-template-columns: 1fr; gap: 6px; }
		.vmsai-slot-time { text-align: left; padding-top: 0; }
	}
</style>

<section class="vmsai-panel">
	<div class="vmsai-nav-sub">
		<a href="<?php echo esc_url( VMSAI_Admin::url( 'queue' ) ); ?>" class="<?php echo empty($_GET['view']) ? 'is-active' : ''; ?>"><?php esc_html_e( 'Review Queue', 'vm-social-ai-pro' ); ?></a>
		<?php if ( VMSAI_License::has_feature( 'production_pipeline' ) ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'view', 'pipeline', VMSAI_Admin::url( 'queue' ) ) ); ?>" class="<?php echo ($_GET['view'] ?? '') === 'pipeline' ? 'is-active' : ''; ?>"><?php esc_html_e( 'Production Pipeline', 'vm-social-ai-pro' ); ?></a>
		<?php endif; ?>
	</div>

	<?php if ( ($_GET['view'] ?? '') === 'pipeline' && VMSAI_License::has_feature( 'production_pipeline' ) ) : ?>
		<?php include VMSAI_PATH . 'admin/views/pipeline.php'; ?>
	<?php else : ?>
		<div class="vmsai-panel__head" style="margin-bottom: 30px;">
		<div>
			<h2 class="vmsai-display">The War Room</h2>
			<p class="vmsai-lede">Reviewing and deploying high-impact social assets.</p>
		</div>
		<div>
			<?php if ( 'draft' === $vmsai_filter ) : ?>
				<button data-vmsai-action="bulk-queue" data-bulk="approve_all" class="vmsai-btn vmsai-btn--gold">🚀 Approve All</button>
			<?php endif; ?>
		</div>
	</div>

	<nav class="vmsai-tabs-nav">
		<?php foreach ( $vmsai_filters as $vmsai_k => $vmsai_f ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'status', $vmsai_k, VMSAI_Admin::url( 'queue' ) ) ); ?>" class="vmsai-tab-link <?php echo $vmsai_k === $vmsai_filter ? 'is-active' : ''; ?>">
				<span><?php echo $vmsai_f['icon']; ?> <?php echo esc_html( $vmsai_f['label'] ); ?></span>
				<span class="vmsai-tab-count"><?php echo number_format_i18n( $vmsai_counts[ $vmsai_k ] ?? 0 ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<div style="display:flex; justify-content:flex-end; margin-bottom:6px;">
		<span style="font-size:11px; color:var(--muted);">
			🌐 <?php echo esc_html( wp_timezone_string() ); ?>
		</span>
	</div>

	<?php if ( ! $vmsai_timeline ) : ?>
		<div style="text-align:center; padding: 60px 0; color: var(--muted);">
			<div style="font-size: 40px; margin-bottom: 20px;">🏜️</div>
			<p>No posts in this tactical bucket.</p>
		</div>
	<?php else : ?>
		<?php foreach ( $vmsai_timeline as $vmsai_date => $vmsai_entries ) : ?>
			<div class="vmsai-day-head">
				<h3><?php echo esc_html( vmsai_day_label( $vmsai_date, $vmsai_today, $vmsai_tomorrow ) ); ?></h3>
				<?php if ( '0000-00-00' !== $vmsai_date ) : ?>
					<span><?php echo esc_html( mysql2date( 'F j', $vmsai_date ) ); ?></span>
				<?php endif; ?>
				<span class="vmsai-day-count">
					<?php
					printf(
						esc_html( _n( '%s item', '%s items', count( $vmsai_entries ), 'vm-social-ai-pro' ) ),
						esc_html( number_format_i18n( count( $vmsai_entries ) ) )
					);
					?>
				</span>
			</div>

			<?php foreach ( $vmsai_entries as $vmsai_entry ) : ?>
			<div class="vmsai-slot-row">
				<div class="vmsai-slot-time"><?php echo esc_html( $vmsai_entry['time'] ); ?></div>
				<div>

				<?php if ( 'slot' === $vmsai_entry['type'] ) : ?>
					<?php $vmsai_slot = $vmsai_entry['row']; ?>
					<div class="vmsai-ghost" data-slot-id="<?php echo esc_attr( $vmsai_slot['id'] ); ?>">
						<span class="vmsai-badge vmsai-badge--<?php echo esc_attr( $vmsai_slot['channel'] ); ?>"><?php echo esc_html( ucfirst( $vmsai_slot['channel'] ) ); ?></span>
						<div class="vmsai-ghost__body">
							<div class="vmsai-ghost__topic"><?php echo esc_html( $vmsai_slot['topic'] ?: __( 'Untitled slot', 'vm-social-ai-pro' ) ); ?></div>
							<?php if ( 'failed' === $vmsai_slot['status'] ) : ?>
								<div class="vmsai-ghost__meta is-failed">
									⚠️ <?php echo esc_html( $vmsai_slot['last_error'] ? wp_trim_words( $vmsai_slot['last_error'], 18 ) : __( 'Generation failed.', 'vm-social-ai-pro' ) ); ?>
								</div>
							<?php elseif ( 'processing' === $vmsai_slot['status'] ) : ?>
								<div class="vmsai-ghost__meta"><?php esc_html_e( 'Writing now…', 'vm-social-ai-pro' ); ?></div>
							<?php else : ?>
								<div class="vmsai-ghost__meta"><?php esc_html_e( 'Slot reserved — not written yet.', 'vm-social-ai-pro' ); ?></div>
							<?php endif; ?>
						</div>
						<button data-vmsai-action="compose-slot" class="vmsai-btn" title="<?php esc_attr_e( 'Write this post now', 'vm-social-ai-pro' ); ?>">
							✨ <?php esc_html_e( 'Generate', 'vm-social-ai-pro' ); ?>
						</button>
					</div>
				<?php else : ?>
					<?php $vmsai_p = $vmsai_entry['row']; ?>
					<article class="vmsai-card-v2" data-id="<?php echo esc_attr( $vmsai_p['id'] ); ?>">
					<div class="vmsai-card-v2__media">
						<div class="vmsai-media-tag"><?php echo esc_html( strtoupper($vmsai_p['image_provider'] ?: 'AI') ); ?></div>

						<div class="vmsai-card-v2__media-wrap">
							<?php if ( $vmsai_p['media_url'] ) : ?>
								<a href="<?php echo esc_url( set_url_scheme($vmsai_p['media_url']) ); ?>" target="_blank">
									<?php
									/*
									 * An attachment can be deleted, offloaded, or written by a
									 * provider whose URL later 404s. Without a failure path the
									 * card showed a torn-image icon on a large black panel with
									 * nothing to explain it.
									 */
									?>
									<img src="<?php echo esc_url( set_url_scheme($vmsai_p['media_url']) ); ?>"
										alt="<?php echo esc_attr( $vmsai_p['alt_text'] ?: $vmsai_p['title'] ); ?>"
										loading="lazy"
										onerror="this.closest('.vmsai-card-v2__media-wrap').classList.add('is-missing'); this.remove();">
								</a>
							<?php else : ?>
								<div style="color: var(--muted); font-size: 12px;"><?php esc_html_e( 'No Image Asset', 'vm-social-ai-pro' ); ?></div>
							<?php endif; ?>
						</div>

						<div class="vmsai-card-v2__media-controls">
							<select class="vmsai-media-provider-select">
								<?php
								$vmsai_img_engine = vmsai()->image_engine();
								foreach ( $vmsai_img_engine->providers() as $v_slug => $v_prov ) :
									if ( ! $v_prov->is_configured() && $v_slug !== 'pollinations' ) continue;
								?>
									<option value="<?php echo esc_attr( $v_slug ); ?>" <?php selected( $vmsai_p['image_provider'], $v_slug ); ?>>
										<?php echo esc_html( $v_prov->label() ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button data-vmsai-action="regen-image" class="vmsai-btn vmsai-btn--gold" style="padding: 5px 10px; white-space: nowrap;" title="<?php esc_attr_e( 'Regenerate with the selected model', 'vm-social-ai-pro' ); ?>"><?php esc_html_e( 'Redraw', 'vm-social-ai-pro' ); ?></button>
						</div>
					</div>

					<div class="vmsai-card-v2__body">
						<div class="vmsai-card-v2__meta">
							<span class="vmsai-badge vmsai-badge--<?php echo esc_attr( $vmsai_p['channel'] ); ?>"><?php echo esc_html( ucfirst($vmsai_p['channel']) ); ?></span>

							<?php
							if ( ! empty($vmsai_p['product_data']) ) :
								$vmsai_pd = json_decode($vmsai_p['product_data'], true);
							?>
								<span class="vmsai-badge" style="background: var(--green); color: var(--ink);" title="Linked to WooCommerce Product">
									🛒 <?php echo esc_html($vmsai_pd['price'] ?? ''); ?> <?php echo esc_html($vmsai_pd['currency'] ?? ''); ?>
								</span>
							<?php endif; ?>

							<label class="vmsai-check" style="font-size: 10px; margin-left: 5px;" title="Automatically re-queue every 90 days">
								<input type="checkbox" class="vmsai-evergreen-toggle" <?php checked( $vmsai_p['is_evergreen'] ); ?>>
								<span>Evergreen</span>
							</label>
							<?php if ( 'carousel' === $vmsai_p['format'] ) : ?>
								<span class="vmsai-badge" style="background: var(--gold); color: var(--ink);">CAROUSEL</span>
							<?php endif; ?>
							<span class="vmsai-score-tag" title="Agent Audit Score">🧠 Critic: <?php echo (int)($vmsai_p['viral_score'] ?: 85); ?></span>
							<span class="vmsai-score-tag">🔍 SEO: <?php echo (int)$vmsai_p['seo_score']; ?></span>
							<time style="margin-left:auto; font-size:11px; color:var(--muted);"><?php echo esc_html( get_date_from_gmt( $vmsai_p['scheduled_at'], 'j M, H:i' ) ); ?></time>
						</div>

						<h3 style="margin: 0 0 15px; font-size: 18px;"><?php echo esc_html( $vmsai_p['title'] ); ?></h3>

						<?php if ( ! empty($vmsai_p['critic_notes']) ) : ?>
							<div style="background: rgba(255,215,0,0.05); border-left: 3px solid var(--gold); padding: 10px 15px; margin-bottom: 20px; font-size: 12px; color: var(--gold);">
								<strong>AGENT AUDIT:</strong> <?php
								$vmsai_notes = $vmsai_p['critic_notes'];
								if ( is_string($vmsai_notes) && strpos($vmsai_notes, '[') === 0 ) {
									$vmsai_notes = json_decode($vmsai_notes, true);
								}
								echo esc_html( is_array($vmsai_notes) ? implode(' ', $vmsai_notes) : $vmsai_notes );
								?>
							</div>
						<?php endif; ?>

						<div class="vmsai-editor-box">
							<textarea class="vmsai-editor-textarea" rows="6" data-field="body" style="width:100%;"><?php echo esc_textarea( $vmsai_p['body'] ); ?></textarea>
						</div>

						<div class="vmsai-editor-box" style="margin-top: 10px;">
							<label style="display:block; font-size:11px; color:var(--muted); margin-bottom:5px;">
								<?php esc_html_e( 'First comment', 'vm-social-ai-pro' ); ?>
							</label>
							<textarea class="vmsai-editor-textarea" rows="2" data-field="first_comment" style="width:100%;" placeholder="<?php esc_attr_e( 'Posted automatically right after publishing — hashtags for Instagram, links for LinkedIn.', 'vm-social-ai-pro' ); ?>"><?php echo esc_textarea( (string) ( $vmsai_p['first_comment'] ?? '' ) ); ?></textarea>
						</div>

						<?php if ( ! empty( $vmsai_p['reviewer_notes'] ) ) : ?>
							<p style="font-size:12px; color:var(--red,#e05252); margin: -8px 0 15px;">
								👎 <strong><?php esc_html_e( 'Rejected:', 'vm-social-ai-pro' ); ?></strong> <?php echo esc_html( $vmsai_p['reviewer_notes'] ); ?>
							</p>
						<?php endif; ?>

						<?php if ( 'published' === $vmsai_p['status'] ) : ?>
							<?php $vmsai_m = $vmsai_metrics[ (int) $vmsai_p['id'] ] ?? null; ?>
							<?php if ( $vmsai_m ) : ?>
								<dl class="vmsai-metric-strip">
									<div>
										<dt><?php esc_html_e( 'Impressions', 'vm-social-ai-pro' ); ?></dt>
										<dd><?php echo esc_html( number_format_i18n( $vmsai_m['impressions'] ) ); ?></dd>
									</div>
									<div>
										<dt><?php esc_html_e( 'Reach', 'vm-social-ai-pro' ); ?></dt>
										<dd><?php echo esc_html( number_format_i18n( $vmsai_m['reach'] ) ); ?></dd>
									</div>
									<div>
										<dt><?php esc_html_e( 'Engagements', 'vm-social-ai-pro' ); ?></dt>
										<dd><?php echo esc_html( number_format_i18n( $vmsai_m['engagements'] ) ); ?></dd>
									</div>
									<div>
										<dt><?php esc_html_e( 'Eng. rate', 'vm-social-ai-pro' ); ?></dt>
										<dd><?php echo esc_html( number_format_i18n( $vmsai_m['rate'], 1 ) ); ?>%</dd>
									</div>
									<div>
										<dt><?php esc_html_e( 'Clicks', 'vm-social-ai-pro' ); ?></dt>
										<dd><?php echo esc_html( number_format_i18n( $vmsai_m['clicks'] ) ); ?></dd>
									</div>
								</dl>
							<?php else : ?>
								<p class="vmsai-metric-empty">
									<?php esc_html_e( 'No performance data pulled back yet — networks report a few hours after publishing.', 'vm-social-ai-pro' ); ?>
								</p>
							<?php endif; ?>
						<?php endif; ?>

						<div class="vmsai-card-actions">
							<?php if ( 'published' === $vmsai_p['status'] ) : ?>
								<a href="<?php echo esc_url( $vmsai_p['permalink'] ); ?>" target="_blank" class="vmsai-btn vmsai-btn--gold">View Post</a>
							<?php else : ?>
								<?php
								/*
								 * Text labels, not bare emoji. These controls publish and
								 * delete real content, and an icon-only button whose whole
								 * label is a glyph has no accessible name and disappears
								 * entirely if anything on the site rewrites emoji into
								 * images — which is exactly what was happening here.
								 */
								?>
								<div class="vmsai-btn-group">
									<button data-vmsai-action="save-post" class="vmsai-btn"><?php esc_html_e( 'Save', 'vm-social-ai-pro' ); ?></button>
									<button data-vmsai-action="approve-post" class="vmsai-btn vmsai-btn--gold"><?php esc_html_e( 'Approve', 'vm-social-ai-pro' ); ?></button>
									<button data-vmsai-action="reject-post" class="vmsai-btn vmsai-btn--quiet" title="<?php esc_attr_e( 'Send back with a reason', 'vm-social-ai-pro' ); ?>"><?php esc_html_e( 'Reject', 'vm-social-ai-pro' ); ?></button>
								</div>
								<div class="vmsai-btn-group">
									<button data-vmsai-action="regenerate-post" class="vmsai-btn" title="<?php esc_attr_e( 'Rewrite the copy', 'vm-social-ai-pro' ); ?>"><?php esc_html_e( 'Rewrite', 'vm-social-ai-pro' ); ?></button>
									<button data-vmsai-action="publish-post" class="vmsai-btn" title="<?php esc_attr_e( 'Publish immediately', 'vm-social-ai-pro' ); ?>"><?php esc_html_e( 'Publish now', 'vm-social-ai-pro' ); ?></button>
									<button data-vmsai-action="copy-portal-link" class="vmsai-btn vmsai-btn--quiet" title="<?php esc_attr_e( 'Copy client review link', 'vm-social-ai-pro' ); ?>" data-token="<?php echo esc_attr( VMSAI_Crypto::generate_portal_token( $vmsai_p['id'], 2 * WEEK_IN_SECONDS ) ); ?>"><?php esc_html_e( 'Share link', 'vm-social-ai-pro' ); ?></button>
									<button data-vmsai-action="delete-post" class="vmsai-btn vmsai-btn--quiet" title="<?php esc_attr_e( 'Delete this post', 'vm-social-ai-pro' ); ?>"><?php esc_html_e( 'Delete', 'vm-social-ai-pro' ); ?></button>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</article>
				<?php endif; ?>

				</div>
			</div>
			<?php endforeach; ?>
		<?php endforeach; ?>

		<?php if ( $vmsai_pages > 1 ) : ?>
			<?php
			$vmsai_page_base = add_query_arg( 'status', $vmsai_filter, VMSAI_Admin::url( 'queue' ) );
			?>
			<nav class="vmsai-pager" style="display:flex; align-items:center; justify-content:space-between; gap:15px; margin-top:34px; padding-top:20px; border-top:1px solid var(--hairline);">
				<?php if ( $vmsai_paged > 1 ) : ?>
					<a class="vmsai-btn" href="<?php echo esc_url( add_query_arg( 'paged', $vmsai_paged - 1, $vmsai_page_base ) ); ?>">← <?php esc_html_e( 'Newer', 'vm-social-ai-pro' ); ?></a>
				<?php else : ?>
					<span></span>
				<?php endif; ?>

				<span style="font-size:11px; color:var(--muted);">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages, 3: total posts */
						esc_html__( 'Page %1$s of %2$s · %3$s posts', 'vm-social-ai-pro' ),
						esc_html( number_format_i18n( $vmsai_paged ) ),
						esc_html( number_format_i18n( $vmsai_pages ) ),
						esc_html( number_format_i18n( $vmsai_total ) )
					);
					?>
				</span>

				<?php if ( $vmsai_paged < $vmsai_pages ) : ?>
					<a class="vmsai-btn" href="<?php echo esc_url( add_query_arg( 'paged', $vmsai_paged + 1, $vmsai_page_base ) ); ?>"><?php esc_html_e( 'Older', 'vm-social-ai-pro' ); ?> →</a>
				<?php else : ?>
					<span></span>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
<?php endif; ?>
</section>
