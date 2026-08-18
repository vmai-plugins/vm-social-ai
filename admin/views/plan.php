<?php
/**
 * Campaign setup and calendar.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_campaign = VMSAI_Planner::active_campaign();
$vmsai_ready    = vmsai()->channels()->ready();
$vmsai_manager  = vmsai()->channels();
?>

<?php if ( ! $vmsai_campaign ) : ?>

	<section class="vmsai-panel">
		<h2 class="vmsai-display"><?php esc_html_e( 'Set a target and a deadline', 'vm-social-ai-pro' ); ?></h2>
		<p class="vmsai-lede">
			<?php esc_html_e( 'The planner does the arithmetic before it commits to anything. Change the numbers and watch what they demand of each post.', 'vm-social-ai-pro' ); ?>
		</p>

		<?php if ( ! $vmsai_ready ) : ?>
			<p class="vmsai-note vmsai-note--warn">
				<?php
				printf(
					/* translators: %s: link to the channels tab */
					esc_html__( 'No channel is connected yet. %s before starting a campaign.', 'vm-social-ai-pro' ),
					'<a href="' . esc_url( VMSAI_Admin::url( 'settings' ) . '#vmsai-section-channels' ) . '">' . esc_html__( 'Connect at least one', 'vm-social-ai-pro' ) . '</a>'
				);
				?>
			</p>
		<?php endif; ?>

		<div class="vmsai-fields" id="vmsai-campaign-form">
			<div class="vmsai-field">
				<label for="vmsai-campaign-name"><?php esc_html_e( 'Campaign name', 'vm-social-ai-pro' ); ?></label>
				<input type="text" id="vmsai-campaign-name" value="<?php echo esc_attr( sprintf( /* translators: %s: date */ __( 'Growth run — %s', 'vm-social-ai-pro' ), date_i18n( 'j M Y' ) ) ); ?>">
			</div>

			<div class="vmsai-field">
				<label for="vmsai-target"><?php esc_html_e( 'View target', 'vm-social-ai-pro' ); ?></label>
				<input type="number" id="vmsai-target" value="200000" min="1000" step="1000">
			</div>

			<div class="vmsai-field">
				<label for="vmsai-horizon"><?php esc_html_e( 'Days to get there', 'vm-social-ai-pro' ); ?></label>
				<input type="number" id="vmsai-horizon" value="50" min="7" max="365">
			</div>

			<div class="vmsai-field">
				<label for="vmsai-perday"><?php esc_html_e( 'Posts per channel per day', 'vm-social-ai-pro' ); ?></label>
				<input type="number" id="vmsai-perday" value="2" min="1" max="8">
			</div>

			<div class="vmsai-field">
				<label for="vmsai-language"><?php esc_html_e( 'Language', 'vm-social-ai-pro' ); ?></label>
				<select id="vmsai-language">
					<option value="en" <?php selected( VMSAI_Settings::get( 'language' ), 'en' ); ?>><?php esc_html_e( 'English', 'vm-social-ai-pro' ); ?></option>
					<option value="hi" <?php selected( VMSAI_Settings::get( 'language' ), 'hi' ); ?>><?php esc_html_e( 'Hindi', 'vm-social-ai-pro' ); ?></option>
				</select>
			</div>

			<div class="vmsai-field">
				<label for="vmsai-flavour"><?php esc_html_e( 'Local Flavour', 'vm-social-ai-pro' ); ?></label>
				<select id="vmsai-flavour">
					<option value="" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), '' ); ?>><?php esc_html_e( 'Standard', 'vm-social-ai-pro' ); ?></option>
					<option value="hinglish" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'hinglish' ); ?>><?php esc_html_e( 'Hinglish', 'vm-social-ai-pro' ); ?></option>
					<option value="mumbai" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'mumbai' ); ?>><?php esc_html_e( 'Mumbai', 'vm-social-ai-pro' ); ?></option>
					<option value="delhi" <?php selected( VMSAI_Settings::get( 'locale_flavour' ), 'delhi' ); ?>><?php esc_html_e( 'Delhi', 'vm-social-ai-pro' ); ?></option>
				</select>
			</div>

			<div class="vmsai-field vmsai-field--wide">
				<span class="vmsai-field__legend"><?php esc_html_e( 'Channels in rotation', 'vm-social-ai-pro' ); ?></span>
				<div class="vmsai-checks">
					<?php foreach ( $vmsai_manager->all() as $vmsai_slug => $vmsai_channel ) : ?>
						<?php $vmsai_connected = in_array( $vmsai_slug, $vmsai_ready, true ); ?>
						<label class="vmsai-check<?php echo $vmsai_connected ? '' : ' is-disabled'; ?>">
							<input type="checkbox" class="vmsai-campaign-channel"
								value="<?php echo esc_attr( $vmsai_slug ); ?>"
								<?php checked( $vmsai_connected ); ?>
								<?php disabled( ! $vmsai_connected ); ?>>
							<span><?php echo esc_html( $vmsai_channel->label() ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="vmsai-mathbox" id="vmsai-math" aria-live="polite">
			<p class="vmsai-muted"><?php esc_html_e( 'Adjust the numbers above to see what the target requires.', 'vm-social-ai-pro' ); ?></p>
		</div>

		<p>
			<button type="button" class="vmsai-btn vmsai-btn--gold" data-vmsai-action="create-campaign" <?php disabled( ! $vmsai_ready ); ?>>
				<?php esc_html_e( 'Start the campaign', 'vm-social-ai-pro' ); ?>
			</button>
			<span class="vmsai-muted"><?php esc_html_e( 'Writes the first fourteen days immediately, then keeps itself topped up.', 'vm-social-ai-pro' ); ?></span>
		</p>
	</section>

<?php else : ?>

	<?php
	$vmsai_from = current_time( 'Y-m-d' );
	$vmsai_to   = gmdate( 'Y-m-d', strtotime( $vmsai_from . ' +13 days' ) );
	$vmsai_rows = VMSAI_Planner::calendar( (int) $vmsai_campaign['id'], $vmsai_from, $vmsai_to );
	$vmsai_days = array();

	foreach ( $vmsai_rows as $vmsai_row ) {
		$vmsai_days[ $vmsai_row['slot_date'] ][] = $vmsai_row;
	}
	?>

	<div class="vmsai-nav-sub">
		<a href="#vmsai-plan-list" class="is-active"><?php esc_html_e( 'Timeline View', 'vm-social-ai-pro' ); ?></a>
		<a href="#vmsai-plan-grid"><?php esc_html_e( 'Visual Calendar', 'vm-social-ai-pro' ); ?></a>
	</div>

	<section class="vmsai-panel vmsai-settings-section" id="vmsai-plan-list">
		<div class="vmsai-panel__head">
			<div>
				<span class="vmsai-eyebrow"><?php echo esc_html( $vmsai_campaign['name'] ); ?></span>
				<h2 class="vmsai-display"><?php esc_html_e( 'The next fortnight', 'vm-social-ai-pro' ); ?></h2>
			</div>
			<div class="vmsai-panel__tools">
				<button type="button" class="vmsai-btn vmsai-btn--gold" data-vmsai-action="open-campaign-planner">✨ Plan a Promotion</button>
				<button type="button" class="vmsai-btn" data-vmsai-action="reset-plan"><?php esc_html_e( 'Retry failed slots', 'vm-social-ai-pro' ); ?></button>
				<button type="button" class="vmsai-btn" data-vmsai-action="extend-plan"><?php esc_html_e( 'Plan another two weeks', 'vm-social-ai-pro' ); ?></button>
			</div>
		</div>

		<!-- Campaign Planner Inline Modal -->
		<div id="vmsai-planner-modal" style="display:none; margin-bottom: 40px; padding: 30px; background: var(--ink); border: 2px solid var(--gold); border-radius: 16px;">
			<?php include VMSAI_PATH . 'admin/views/campaign_gen.php'; ?>
		</div>

		<?php if ( ! $vmsai_days ) : ?>
			<p class="vmsai-muted"><?php esc_html_e( 'Nothing is scheduled in this window yet. Extend the plan to fill it.', 'vm-social-ai-pro' ); ?></p>
		<?php else : ?>
			<div class="vmsai-calendar">
				<?php foreach ( $vmsai_days as $vmsai_date => $vmsai_slots ) : ?>
					<article class="vmsai-day">
						<h3 class="vmsai-day__date">
							<span class="vmsai-day__weekday"><?php echo esc_html( date_i18n( 'D', strtotime( $vmsai_date ) ) ); ?></span>
							<span class="vmsai-day__number"><?php echo esc_html( date_i18n( 'j M', strtotime( $vmsai_date ) ) ); ?></span>
						</h3>

						<ul class="vmsai-day__slots">
							<?php foreach ( $vmsai_slots as $vmsai_slot ) : ?>
								<li class="vmsai-slot is-<?php echo esc_attr( $vmsai_slot['status'] ); ?>" data-pillar="<?php echo esc_attr( $vmsai_slot['pillar'] ); ?>">
									<div style="display:flex; justify-content: space-between; align-items: center;">
										<span class="vmsai-slot__time"><?php echo esc_html( substr( (string) $vmsai_slot['slot_time'], 0, 5 ) ); ?></span>
										<span class="vmsai-slot__channel"><?php echo esc_html( $vmsai_slot['channel'] ); ?></span>
									</div>
									<span class="vmsai-slot__pillar"><?php echo esc_html( VMSAI_Planner::pillars()[ $vmsai_slot['pillar'] ]['label'] ?? $vmsai_slot['pillar'] ); ?></span>
									<p class="vmsai-slot__topic"><?php echo esc_html( $vmsai_slot['title'] ?: $vmsai_slot['topic'] ); ?></p>
									<?php if ( $vmsai_slot['seo_score'] ) : ?>
										<div style="margin-top: 10px; font-size: 10px; color: var(--muted);">SEO Score: <?php echo (int) $vmsai_slot['seo_score']; ?></div>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<section class="vmsai-panel vmsai-settings-section" id="vmsai-plan-grid" style="display:none;">
		<style>
			.vmsai-calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--hairline); border: 1px solid var(--hairline); border-radius: 12px; overflow: hidden; }
			.vmsai-cal-head { background: var(--ink); padding: 15px; text-align: center; font-size: 10px; font-weight: bold; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--hairline); }
			.vmsai-cal-day { background: var(--panel); min-height: 150px; padding: 10px; position: relative; }
			.vmsai-cal-day.is-today { background: rgba(201, 162, 39, 0.05); }
			.vmsai-cal-day.is-outside { opacity: 0.3; background: var(--ink); }
			.vmsai-cal-num { font-size: 11px; font-weight: bold; color: var(--muted); margin-bottom: 8px; }

			.vmsai-cal-slot {
				font-size: 9px; padding: 4px 6px; border-radius: 4px; margin-bottom: 4px;
				background: var(--ink); border-left: 3px solid var(--gold);
				cursor: grab; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
			}
			.vmsai-cal-slot:active { cursor: grabbing; }
			.vmsai-cal-slot.is-composed { border-left-color: var(--green); }
		</style>

		<div class="vmsai-panel__head">
			<h2 class="vmsai-display">Visual Content Grid</h2>
			<div class="vmsai-panel__tools">
				<button class="vmsai-btn" data-vmsai-action="prev-month">◀</button>
				<span id="vmsai-cal-month-label" style="font-weight: bold; min-width: 120px; text-align: center;">Month</span>
				<button class="vmsai-btn" data-vmsai-action="next-month">▶</button>
			</div>
		</div>

		<div class="vmsai-calendar-grid" id="vmsai-calendar-mount">
			<!-- Header -->
			<div class="vmsai-cal-head">Mon</div><div class="vmsai-cal-head">Tue</div><div class="vmsai-cal-head">Wed</div>
			<div class="vmsai-cal-head">Thu</div><div class="vmsai-cal-head">Fri</div><div class="vmsai-cal-head">Sat</div>
			<div class="vmsai-cal-head">Sun</div>
			<!-- Days injected here -->
		</div>
	</section>
<?php endif; ?>
