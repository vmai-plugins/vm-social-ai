<?php
/**
 * View: Site Image Studio, Auditor & Autonomous Optimizer.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

$vmsai_opt_summary = VMSAI_Image_Optimizer::scan_summary();
$vmsai_low_res     = VMSAI_Image_Optimizer::get_low_res_posts( 30 );
$vmsai_orphans     = VMSAI_Image_Optimizer::get_orphan_attachments( 25 );
$vmsai_heavy       = VMSAI_Image_Optimizer::get_heavy_images( 20 );
$vmsai_broken      = VMSAI_Image_Optimizer::get_broken_images( 35 );
?>

<div class="vmsai-optimizer" id="vmsai-optimizer-app">
	<section class="vmsai-panel">
		<div class="vmsai-panel__head" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
			<div>
				<h2 class="vmsai-display"><?php esc_html_e( 'Site Image Studio & Autonomous Optimizer', 'vm-social-ai-pro' ); ?></h2>
				<p class="vmsai-lede"><?php esc_html_e( 'Audit entire site media health, safely quarantine unused files without deletion, and regenerate high-definition 8K photography for low-resolution pilgrimage & tour packages.', 'vm-social-ai-pro' ); ?></p>
			</div>
			<div style="display: flex; gap: 10px;">
				<button type="button" class="vmsai-btn vmsai-btn--secondary" id="vmsai-opt-refresh-btn">
					<span>🔄</span> <?php esc_html_e( 'Rescan Site Media', 'vm-social-ai-pro' ); ?>
				</button>
			</div>
		</div>

		<!-- Summary Metric Cards -->
		<div class="vmsai-metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 15px; margin-top: 25px;">
			<div class="vmsai-metric-card" style="background: var(--raised, #13151f); border: 1px solid var(--hairline, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px;">
				<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted, #94a3b8); margin-bottom: 6px;"><?php esc_html_e( 'Orphan / Unused in DB', 'vm-social-ai-pro' ); ?></div>
				<div style="font-size: 28px; font-weight: 800; color: #f59e0b;" id="vmsai-metric-orphans"><?php echo esc_html( number_format_i18n( $vmsai_opt_summary['orphan_attachments'] ) ); ?></div>
				<div style="font-size: 11px; color: var(--muted, #94a3b8); margin-top: 4px;"><?php esc_html_e( 'Not linked to any page/trip/event', 'vm-social-ai-pro' ); ?></div>
			</div>

			<div class="vmsai-metric-card" style="background: var(--raised, #13151f); border: 1px solid var(--hairline, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px;">
				<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted, #94a3b8); margin-bottom: 6px;"><?php esc_html_e( 'Low-Resolution / Missing', 'vm-social-ai-pro' ); ?></div>
				<div style="font-size: 28px; font-weight: 800; color: #ef4444;" id="vmsai-metric-lowres"><?php echo esc_html( number_format_i18n( $vmsai_opt_summary['low_res_count'] ) ); ?></div>
				<div style="font-size: 11px; color: var(--muted, #94a3b8); margin-top: 4px;"><?php esc_html_e( 'Featured images < 800px or missing', 'vm-social-ai-pro' ); ?></div>
			</div>

			<div class="vmsai-metric-card" style="background: var(--raised, #13151f); border: 1px solid var(--hairline, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px;">
				<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted, #94a3b8); margin-bottom: 6px;"><?php esc_html_e( 'Heavy Files (>= 1MB)', 'vm-social-ai-pro' ); ?></div>
				<div style="font-size: 28px; font-weight: 800; color: #3b82f6;" id="vmsai-metric-heavy"><?php echo esc_html( number_format_i18n( $vmsai_opt_summary['heavy_count'] ) ); ?></div>
				<div style="font-size: 11px; color: var(--muted, #94a3b8); margin-top: 4px;"><?php esc_html_e( 'Candidates for WebP compression', 'vm-social-ai-pro' ); ?></div>
			</div>

			<div class="vmsai-metric-card" style="background: var(--raised, #13151f); border: 1px solid var(--hairline, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px;">
				<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted, #94a3b8); margin-bottom: 6px;"><?php esc_html_e( '404 Broken Images', 'vm-social-ai-pro' ); ?></div>
				<div style="font-size: 28px; font-weight: 800; color: #ec4899;" id="vmsai-metric-broken"><?php echo esc_html( number_format_i18n( $vmsai_opt_summary['broken_count'] ?? count( $vmsai_broken ) ) ); ?></div>
				<div style="font-size: 11px; color: var(--muted, #94a3b8); margin-top: 4px;"><?php esc_html_e( 'Missing URLs returning 404', 'vm-social-ai-pro' ); ?></div>
			</div>

			<div class="vmsai-metric-card" style="background: var(--raised, #13151f); border: 1px solid var(--hairline, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px;">
				<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted, #94a3b8); margin-bottom: 6px;"><?php esc_html_e( 'Regeneration Queue', 'vm-social-ai-pro' ); ?></div>
				<div style="font-size: 28px; font-weight: 800; color: var(--gold, #d97706);" id="vmsai-metric-queue"><?php echo esc_html( number_format_i18n( $vmsai_opt_summary['queue_count'] ) ); ?></div>
				<div style="font-size: 11px; color: var(--muted, #94a3b8); margin-top: 4px;"><?php esc_html_e( 'Automated WP-Cron batch items', 'vm-social-ai-pro' ); ?></div>
			</div>
		</div>

		<!-- Studio Sub-Navigation Tabs -->
		<div style="display: flex; gap: 10px; border-bottom: 1px solid var(--hairline, rgba(255,255,255,0.08)); margin-top: 30px; padding-bottom: 12px; overflow-x: auto;">
			<button type="button" class="vmsai-btn vmsai-btn--secondary is-active vmsai-opt-tab-btn" data-tab="lowres" style="border-radius: 8px;">
				✨ <?php esc_html_e( 'Low-Res & Missing Images (AI Regenerate)', 'vm-social-ai-pro' ); ?>
			</button>
			<button type="button" class="vmsai-btn vmsai-btn--secondary vmsai-opt-tab-btn" data-tab="broken" style="border-radius: 8px; color: #ec4899;">
				🩹 <?php esc_html_e( '404 Broken Images (Infer & Heal)', 'vm-social-ai-pro' ); ?>
			</button>
			<button type="button" class="vmsai-btn vmsai-btn--secondary vmsai-opt-tab-btn" data-tab="orphans" style="border-radius: 8px;">
				🛡️ <?php esc_html_e( 'Unused Assets (Safe Quarantine)', 'vm-social-ai-pro' ); ?>
			</button>
			<button type="button" class="vmsai-btn vmsai-btn--secondary vmsai-opt-tab-btn" data-tab="heavy" style="border-radius: 8px;">
				⚡ <?php esc_html_e( 'Heavy Bloated Images', 'vm-social-ai-pro' ); ?>
			</button>
		</div>

		<!-- TAB 1: LOW-RES & MISSING IMAGES (AI REGENERATOR) -->
		<div id="vmsai-opt-tab-lowres" class="vmsai-opt-view-pane" style="margin-top: 25px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
				<div>
					<h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #fff;"><?php esc_html_e( 'High-Definition AI Replacement Studio', 'vm-social-ai-pro' ); ?></h3>
					<p style="margin: 4px 0 0 0; font-size: 12px; color: var(--muted, #94a3b8);"><?php esc_html_e( 'Replace blurry, low-resolution pilgrimage banners with 8K commercial travel photography. Automatically set as featured image upon generation.', 'vm-social-ai-pro' ); ?></p>
				</div>
				<button type="button" class="vmsai-btn vmsai-btn--primary" id="vmsai-opt-queue-all-btn">
					<span>⚡</span> <?php esc_html_e( 'Queue All for Background Auto-Regen', 'vm-social-ai-pro' ); ?>
				</button>
			</div>

			<div style="overflow-x: auto;">
				<table class="wp-list-table widefat fixed striped" style="background: transparent; border: 1px solid var(--hairline, rgba(255,255,255,0.08)); border-radius: 8px; color: #cbd5e1;">
					<thead>
						<tr>
							<th style="width: 70px;"><?php esc_html_e( 'Preview', 'vm-social-ai-pro' ); ?></th>
							<th><?php esc_html_e( 'Post / Tour Title', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 110px;"><?php esc_html_e( 'Type', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'Current Res', 'vm-social-ai-pro' ); ?></th>
							<th><?php esc_html_e( 'AI Prompt Preview', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 140px; text-align: right;"><?php esc_html_e( 'Action', 'vm-social-ai-pro' ); ?></th>
						</tr>
					</thead>
					<tbody id="vmsai-opt-lowres-body">
						<?php if ( empty( $vmsai_low_res ) ) : ?>
							<tr>
								<td colspan="6" style="text-align: center; padding: 25px; color: #10b981;">
									🎉 <?php esc_html_e( 'All audited posts and tours have crisp, high-resolution featured images!', 'vm-social-ai-pro' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $vmsai_low_res as $item ) : ?>
								<tr id="vmsai-row-<?php echo esc_attr( $item['post_id'] ); ?>">
									<td>
										<?php if ( ! empty( $item['thumb_url'] ) ) : ?>
											<img src="<?php echo esc_url( $item['thumb_url'] ); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);" alt="" />
										<?php else : ?>
											<div style="width: 50px; height: 50px; background: rgba(239,68,68,0.15); border: 1px dashed #ef4444; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 18px;">❌</div>
										<?php endif; ?>
									</td>
									<td>
										<strong><a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>" target="_blank" style="color: #60a5fa;"><?php echo esc_html( $item['title'] ); ?></a></strong>
										<div style="font-size: 11px; color: var(--muted, #94a3b8);">ID: <?php echo esc_html( $item['post_id'] ); ?></div>
									</td>
									<td>
										<span style="font-size: 11px; padding: 2px 6px; border-radius: 4px; background: rgba(255,255,255,0.06);"><?php echo esc_html( $item['type'] ); ?></span>
									</td>
									<td>
										<span style="color: #ef4444; font-weight: 600; font-size: 12px;"><?php echo esc_html( $item['dimensions'] ); ?></span>
									</td>
									<td>
										<span style="font-size: 11px; color: #94a3b8; font-style: italic; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo esc_html( $item['recommended'] ); ?></span>
									</td>
									<td style="text-align: right;">
										<button type="button" class="vmsai-btn vmsai-btn--secondary vmsai-opt-regen-single-btn" data-id="<?php echo esc_attr( $item['post_id'] ); ?>" data-title="<?php echo esc_attr( $item['title'] ); ?>" style="padding: 5px 10px; font-size: 11px;">
											⚡ <?php esc_html_e( 'Regenerate', 'vm-social-ai-pro' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- TAB: 404 BROKEN IMAGES (INFER & HEAL) -->
		<div id="vmsai-opt-tab-broken" class="vmsai-opt-view-pane" style="display: none; margin-top: 25px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
				<div>
					<h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #fff;"><?php esc_html_e( '🩹 404 Image Inference & Auto-Healer Studio', 'vm-social-ai-pro' ); ?></h3>
					<p style="margin: 4px 0 0 0; font-size: 12px; color: var(--muted, #94a3b8);"><?php esc_html_e( 'Detects missing 404 images in published articles, infers the intended visual subject from context & filenames, and regenerates 8K replacements.', 'vm-social-ai-pro' ); ?></p>
				</div>
			</div>

			<div style="overflow-x: auto;">
				<table class="wp-list-table widefat fixed striped" style="background: transparent; border: 1px solid var(--hairline, rgba(255,255,255,0.08)); border-radius: 8px; color: #cbd5e1;">
					<thead>
						<tr>
							<th style="width: 60px;"><?php esc_html_e( 'Status', 'vm-social-ai-pro' ); ?></th>
							<th><?php esc_html_e( 'Missing Image / Location', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 140px;"><?php esc_html_e( 'Page / Event', 'vm-social-ai-pro' ); ?></th>
							<th><?php esc_html_e( 'Inferred Visual Brief', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 150px; text-align: right;"><?php esc_html_e( 'Action', 'vm-social-ai-pro' ); ?></th>
						</tr>
					</thead>
					<tbody id="vmsai-opt-broken-body">
						<?php if ( empty( $vmsai_broken ) ) : ?>
							<tr>
								<td colspan="5" style="text-align: center; padding: 25px; color: #10b981;">
									🎉 <?php esc_html_e( 'Zero 404 broken images detected across published posts and pages!', 'vm-social-ai-pro' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $vmsai_broken as $b_item ) : ?>
								<tr id="vmsai-broken-<?php echo esc_attr( $b_item['id'] ); ?>">
									<td>
										<span style="display: inline-block; padding: 3px 6px; border-radius: 4px; background: rgba(239,68,68,0.2); color: #ef4444; font-weight: 700; font-size: 11px;">404</span>
									</td>
									<td>
										<strong style="color: #f87171; word-break: break-all;"><?php echo esc_html( $b_item['filename'] ); ?></strong>
										<div style="font-size: 11px; color: var(--muted, #94a3b8); word-break: break-all; margin-top: 2px;"><?php echo esc_html( $b_item['missing_file'] ); ?></div>
									</td>
									<td>
										<a href="<?php echo esc_url( get_permalink( $b_item['post_id'] ) ); ?>" target="_blank" style="color: #60a5fa; font-size: 12px; font-weight: 600;">
											<?php echo esc_html( $b_item['post_title'] ); ?>
										</a>
										<div style="font-size: 10px; color: var(--muted, #94a3b8);"><?php echo esc_html( $b_item['post_type'] ); ?></div>
									</td>
									<td>
										<span style="font-size: 11px; color: #cbd5e1; font-style: italic; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo esc_html( $b_item['inferred'] ); ?></span>
									</td>
									<td style="text-align: right;">
										<button type="button" class="vmsai-btn vmsai-btn--secondary vmsai-opt-heal-btn" data-postid="<?php echo esc_attr( $b_item['post_id'] ); ?>" data-url="<?php echo esc_attr( $b_item['url'] ); ?>" data-prompt="<?php echo esc_attr( $b_item['inferred'] ); ?>" style="padding: 5px 10px; font-size: 11px; color: #ec4899; border-color: rgba(236,72,153,0.3);">
											⚡ <?php esc_html_e( 'Infer & Heal', 'vm-social-ai-pro' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- TAB 2: UNUSED ORPHAN ASSETS (SAFE QUARANTINE) -->
		<div id="vmsai-opt-tab-orphans" class="vmsai-opt-view-pane" style="display: none; margin-top: 25px;">
			<div style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); border-radius: 8px; padding: 15px; margin-bottom: 20px;">
				<strong style="color: #f59e0b;"><?php esc_html_e( '🛡️ Strict Safety Guarantee — No Permanent Deletion', 'vm-social-ai-pro' ); ?></strong>
				<p style="margin: 6px 0 0 0; font-size: 12px; color: #cbd5e1;">
					<?php esc_html_e( 'Quarantine moves files to a protected staging directory (wp-content/uploads/quarantine/). It hides them from the public web but retains complete database metadata. You can inspect or restore any image with a single click anytime.', 'vm-social-ai-pro' ); ?>
				</p>
			</div>

			<div style="overflow-x: auto;">
				<table class="wp-list-table widefat fixed striped" style="background: transparent; border: 1px solid var(--hairline, rgba(255,255,255,0.08)); border-radius: 8px; color: #cbd5e1;">
					<thead>
						<tr>
							<th style="width: 60px;"><?php esc_html_e( 'Preview', 'vm-social-ai-pro' ); ?></th>
							<th><?php esc_html_e( 'File / Title', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Size', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'Uploaded', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 140px; text-align: right;"><?php esc_html_e( 'Safety Action', 'vm-social-ai-pro' ); ?></th>
						</tr>
					</thead>
					<tbody id="vmsai-opt-orphans-body">
						<?php foreach ( $vmsai_orphans as $orphan ) : ?>
							<tr id="vmsai-orphan-<?php echo esc_attr( $orphan['id'] ); ?>">
								<td>
									<img src="<?php echo esc_url( $orphan['url'] ); ?>" style="width: 45px; height: 45px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);" alt="" />
								</td>
								<td>
									<strong><?php echo esc_html( $orphan['title'] ); ?></strong>
									<div style="font-size: 11px; color: var(--muted, #94a3b8);"><?php echo esc_html( $orphan['filename'] ); ?></div>
								</td>
								<td><?php echo esc_html( $orphan['size'] ); ?></td>
								<td><?php echo esc_html( $orphan['date'] ); ?></td>
								<td style="text-align: right;">
									<button type="button" class="vmsai-btn vmsai-btn--secondary vmsai-opt-quarantine-btn" data-id="<?php echo esc_attr( $orphan['id'] ); ?>" style="padding: 5px 10px; font-size: 11px; color: #f59e0b; border-color: rgba(245,158,11,0.3);">
										📦 <?php esc_html_e( 'Quarantine', 'vm-social-ai-pro' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- TAB 3: HEAVY BLOATED IMAGES -->
		<div id="vmsai-opt-tab-heavy" class="vmsai-opt-view-pane" style="display: none; margin-top: 25px;">
			<div style="margin-bottom: 18px;">
				<h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #fff;"><?php esc_html_e( 'Heavy Uncompressed Images on Disk (>= 1MB)', 'vm-social-ai-pro' ); ?></h3>
				<p style="margin: 4px 0 0 0; font-size: 12px; color: var(--muted, #94a3b8);"><?php esc_html_e( 'Raw stock photo uploads that consume server disk space and bandwidth. Replace with optimized WebP.', 'vm-social-ai-pro' ); ?></p>
			</div>

			<div style="overflow-x: auto;">
				<table class="wp-list-table widefat fixed striped" style="background: transparent; border: 1px solid var(--hairline, rgba(255,255,255,0.08)); border-radius: 8px; color: #cbd5e1;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Filename', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Format', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'File Size', 'vm-social-ai-pro' ); ?></th>
							<th style="width: 100px; text-align: right;"><?php esc_html_e( 'Link', 'vm-social-ai-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $vmsai_heavy as $hf ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $hf['filename'] ); ?></strong>
								</td>
								<td><span style="font-size: 11px; padding: 2px 6px; border-radius: 4px; background: rgba(255,255,255,0.06);"><?php echo esc_html( strtoupper( $hf['ext'] ) ); ?></span></td>
								<td><strong style="color: #3b82f6;"><?php echo esc_html( $hf['size'] ); ?></strong></td>
								<td style="text-align: right;">
									<a href="<?php echo esc_url( $hf['url'] ); ?>" target="_blank" class="vmsai-btn vmsai-btn--secondary" style="padding: 4px 8px; font-size: 11px;">
										👁️ View
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
</div>
