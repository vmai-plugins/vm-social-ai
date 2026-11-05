<?php
/**
 * Autonomous Site Image Auditor, Optimizer & High-Definition Regenerator.
 *
 * Scans the WordPress media library, posts, pages, trips, and events for:
 * 1. Unused / Orphan attachments (Safe Quarantine with 1-click Restore).
 * 2. Missing or Low-Resolution (< 800px) featured images.
 * 3. Heavy / Bloated files (>= 1MB) requiring WebP conversion.
 * 4. Autonomous AI Image Regeneration via VMSAI_Image_Engine with background cron processing.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Image_Optimizer {

	const CRON_HOOK      = 'vmsai_cron_optimizer_batch';
	const QUEUE_OPTION   = 'vmsai_optimizer_regen_queue';
	const QUARANTINE_DIR = 'quarantine';

	/**
	 * Run a full audit and return summary metrics.
	 *
	 * @param bool $force_refresh Skip cached transients.
	 * @return array
	 */
	public static function scan_summary( $force_refresh = false ) {
		global $wpdb;

		$transient_key = 'vmsai_opt_summary_cache';
		if ( ! $force_refresh ) {
			$cached = get_transient( $transient_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		// 1. All DB Attachments
		$attachments = $wpdb->get_results(
			"SELECT ID, post_title, post_mime_type, post_date FROM {$wpdb->posts} WHERE post_type = 'attachment'",
			ARRAY_A
		);
		$total_attachments = count( $attachments );

		// 2. Used Attachments: Featured Images
		$featured_ids = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'" );
		$featured_map = array_flip( array_filter( $featured_ids ) );

		// WooCommerce Galleries
		$wc_galleries = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery'" );
		$gallery_map  = array();
		foreach ( $wc_galleries as $g ) {
			if ( ! empty( $g ) ) {
				foreach ( explode( ',', $g ) as $gid ) {
					$gid = trim( $gid );
					if ( $gid ) {
						$gallery_map[ $gid ] = true;
					}
				}
			}
		}

		// Elementor References
		$elementor_metas = $wpdb->get_results(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value != ''",
			ARRAY_A
		);
		$elementor_ids   = array();
		$elementor_names = array();
		foreach ( $elementor_metas as $em ) {
			if ( preg_match_all( '/"id"\s*:\s*"?(\d+)"?/', $em['meta_value'], $m ) ) {
				foreach ( $m[1] as $uid ) {
					if ( $uid > 0 ) {
						$elementor_ids[ $uid ] = true;
					}
				}
			}
			if ( preg_match_all( '/"url"\s*:\s*"[^"]*\/wp-content\/uploads\/([^"]+)"/', $em['meta_value'], $m ) ) {
				foreach ( $m[1] as $ufile ) {
					$elementor_names[ basename( stripslashes( $ufile ) ) ] = true;
				}
			}
		}

		// Post Content References
		$content_posts = $wpdb->get_results(
			"SELECT post_content FROM {$wpdb->posts} WHERE post_type IN ('post', 'page', 'togo_trip', 'event', 'product') AND post_status IN ('publish', 'draft')",
			ARRAY_A
		);
		$content_ids   = array();
		$content_names = array();
		foreach ( $content_posts as $cp ) {
			if ( empty( $cp['post_content'] ) ) {
				continue;
			}
			if ( preg_match_all( '/wp-image-(\d+)/', $cp['post_content'], $m ) ) {
				foreach ( $m[1] as $cid ) {
					$content_ids[ $cid ] = true;
				}
			}
			if ( preg_match_all( '/\/wp-content\/uploads\/[0-9]{4}\/[0-9]{2}\/([a-zA-Z0-9_\-\.]+)/', $cp['post_content'], $m ) ) {
				foreach ( $m[1] as $cname ) {
					$content_names[ $cname ] = true;
				}
			}
		}

		$site_icon   = get_option( 'site_icon' );
		$custom_logo = get_theme_mod( 'custom_logo' );

		// Separate Used vs Orphan Attachments
		$orphan_count = 0;
		$used_count   = 0;
		$quarantined  = 0;

		foreach ( $attachments as $att ) {
			$aid = (string) $att['ID'];
			$is_q = get_post_meta( $att['ID'], '_vmsai_quarantined', true );
			if ( $is_q ) {
				$quarantined++;
				continue;
			}

			$file = get_attached_file( $att['ID'] );
			$base = $file ? basename( $file ) : '';

			$is_used = false;
			if ( isset( $featured_map[ $aid ] ) || isset( $gallery_map[ $aid ] ) || isset( $elementor_ids[ $aid ] ) || isset( $content_ids[ $aid ] ) || $aid == $site_icon || $aid == $custom_logo ) {
				$is_used = true;
			} elseif ( $base && ( isset( $elementor_names[ $base ] ) || isset( $content_names[ $base ] ) ) ) {
				$is_used = true;
			}

			if ( $is_used ) {
				$used_count++;
			} else {
				$orphan_count++;
			}
		}

		// 3. Inspect Low-Resolution / Missing Featured Images across active post types
		$low_res = self::get_low_res_posts( 100 );

		// 4. Inspect Heavy Images
		$heavy = self::get_heavy_images( 100 );

		// 5. Inspect 404 Broken Images
		$broken = self::get_broken_images( 100 );

		// 6. Active Queue Length
		$queue = get_option( self::QUEUE_OPTION, array() );

		$summary = array(
			'total_attachments'  => $total_attachments,
			'used_attachments'   => $used_count,
			'orphan_attachments' => $orphan_count,
			'quarantined'        => $quarantined,
			'low_res_count'      => count( $low_res ),
			'heavy_count'        => count( $heavy ),
			'broken_count'       => count( $broken ),
			'queue_count'        => count( $queue ),
			'last_scan'          => current_time( 'mysql' ),
		);

		set_transient( $transient_key, $summary, 15 * MINUTE_IN_SECONDS );
		return $summary;
	}

	/**
	 * Retrieve posts, trips, and events that have missing or low-res (< 800px) featured images.
	 *
	 * @param int $limit Max items to return.
	 * @return array
	 */
	public static function get_low_res_posts( $limit = 50 ) {
		global $wpdb;

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_value as thumb_id 
				FROM {$wpdb->posts} p 
				LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id') 
				WHERE p.post_type IN ('post', 'page', 'togo_trip', 'event', 'product') 
				  AND p.post_status = 'publish' 
				ORDER BY p.ID DESC 
				LIMIT %d",
				$limit * 3
			),
			ARRAY_A
		);

		$results = array();

		foreach ( $posts as $p ) {
			if ( count( $results ) >= $limit ) {
				break;
			}

			$thumb_id = (int) ( $p['thumb_id'] ?? 0 );
			$reason   = '';
			$dims     = 'None';
			$url      = '';

			if ( ! $thumb_id ) {
				$reason = 'missing';
			} else {
				$meta = wp_get_attachment_metadata( $thumb_id );
				$w    = (int) ( $meta['width'] ?? 0 );
				$h    = (int) ( $meta['height'] ?? 0 );
				$url  = (string) wp_get_attachment_image_url( $thumb_id, 'medium' );

				if ( $w > 0 && $h > 0 ) {
					$dims = "{$w}x{$h}";
					if ( $w < 800 || $h < 500 ) {
						$reason = 'low_resolution';
					}
				} else {
					$reason = 'missing_metadata';
				}
			}

			if ( $reason ) {
				$results[] = array(
					'post_id'      => (int) $p['ID'],
					'title'        => $p['post_title'],
					'type'         => $p['post_type'],
					'thumb_id'     => $thumb_id,
					'thumb_url'    => $url,
					'dimensions'   => $dims,
					'issue'        => $reason,
					'recommended'  => self::craft_prompt( $p['ID'], $p['post_title'], $p['post_type'] ),
				);
			}
		}

		return $results;
	}

	/**
	 * Retrieve sample orphan attachments for review.
	 *
	 * @param int $limit Max items to return.
	 * @return array
	 */
	public static function get_orphan_attachments( $limit = 50 ) {
		global $wpdb;

		// Fetch all attachments
		$attachments = $wpdb->get_results(
			"SELECT ID, post_title, post_mime_type, post_date FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID DESC",
			ARRAY_A
		);

		$featured_ids = array_flip( array_filter( $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'" ) ) );
		$wc_galleries = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery'" );
		$gallery_map  = array();
		foreach ( $wc_galleries as $g ) {
			if ( ! empty( $g ) ) {
				foreach ( explode( ',', $g ) as $gid ) {
					$gid = trim( $gid );
					if ( $gid ) $gallery_map[ $gid ] = true;
				}
			}
		}

		$orphans = array();

		foreach ( $attachments as $att ) {
			if ( count( $orphans ) >= $limit ) {
				break;
			}

			$aid = (string) $att['ID'];
			if ( isset( $featured_ids[ $aid ] ) || isset( $gallery_map[ $aid ] ) ) {
				continue;
			}

			if ( get_post_meta( $att['ID'], '_vmsai_quarantined', true ) ) {
				continue;
			}

			$file = get_attached_file( $att['ID'] );
			if ( ! $file || ! file_exists( $file ) ) {
				continue;
			}

			$size_bytes = filesize( $file );
			$orphans[]  = array(
				'id'        => (int) $att['ID'],
				'title'     => $att['post_title'] ?: basename( $file ),
				'url'       => wp_get_attachment_image_url( $att['ID'], 'thumbnail' ) ?: wp_get_attachment_url( $att['ID'] ),
				'filename'  => basename( $file ),
				'size'      => size_format( $size_bytes ),
				'size_raw'  => $size_bytes,
				'date'      => substr( $att['post_date'], 0, 10 ),
			);
		}

		return $orphans;
	}

	/**
	 * Retrieve heavy image files (>= 1MB) on disk.
	 *
	 * @param int $limit Max items.
	 * @return array
	 */
	public static function get_heavy_images( $limit = 50 ) {
		$upload_info = wp_upload_dir();
		$basedir     = $upload_info['basedir'];
		$baseurl     = $upload_info['baseurl'];

		$heavy = array();

		try {
			$dir_it = new RecursiveDirectoryIterator( $basedir, RecursiveDirectoryIterator::SKIP_DOTS );
			$it     = new RecursiveIteratorIterator( $dir_it, RecursiveIteratorIterator::LEAVES_ONLY );

			foreach ( $it as $file ) {
				if ( ! $file->isFile() ) continue;
				$size = $file->getSize();
				if ( $size >= 1024 * 1024 ) { // >= 1MB
					$ext = strtolower( $file->getExtension() );
					if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
						$rel = str_replace( $basedir, '', $file->getPathname() );
						$heavy[] = array(
							'filename' => $file->getFilename(),
							'path'     => $file->getPathname(),
							'url'      => $baseurl . str_replace( '\\', '/', $rel ),
							'size'     => size_format( $size ),
							'size_raw' => $size,
							'ext'      => $ext,
						);
						if ( count( $heavy ) >= $limit * 2 ) {
							break;
						}
					}
				}
			}
		} catch ( Exception $e ) {
			// Directory traversal safe fallback
		}

		usort( $heavy, function( $a, $b ) {
			return $b['size_raw'] <=> $a['size_raw'];
		} );

		return array_slice( $heavy, 0, $limit );
	}

	/**
	 * Craft high-converting visual prompts tailored for pilgrimage, Varanasi, and travel destinations.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Post title.
	 * @param string $type    Post type.
	 * @return string
	 */
	public static function craft_prompt( $post_id, $title, $type = 'post' ) {
		$clean_title = preg_replace( '/[^\w\s\-]/u', '', $title );
		$clean_title = trim( preg_replace( '/\s+/', ' ', $clean_title ) );

		if ( 'togo_trip' === $type || false !== stripos( $clean_title, 'tour' ) || false !== stripos( $clean_title, 'package' ) ) {
			return "Breathtaking luxury travel editorial photography of {$clean_title}, authentic Indian heritage architecture, golden hour ambient sunlight, cinematic lighting, shot on 35mm lens, 8k resolution, vibrant and elegant";
		}

		if ( false !== stripos( $clean_title, 'diwali' ) || false !== stripos( $clean_title, 'ghat' ) || false !== stripos( $clean_title, 'aarti' ) ) {
			return "Magical evening scene of {$clean_title} along the sacred Ganga ghats of Varanasi, thousands of glowing earthen diyas floating on water, majestic temple silhouettes, ambient spiritual illumination, 8k commercial photography";
		}

		return "Award-winning commercial editorial photography representing {$clean_title}, vibrant natural colors, ultra-crisp focus, professional travel magazine cover quality, 8k resolution, masterfully composed";
	}

	/**
	 * Regenerate a high-definition replacement image for a post and auto-assign as featured image.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $custom_prompt Custom prompt or blank for automatic.
	 * @param string $provider      Specific AI provider (omniroute, pollinations, etc.).
	 * @return array
	 */
	public static function regenerate_post_image( $post_id, $custom_prompt = '', $provider = '' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'ok' => false, 'error' => __( 'Post not found.', 'vm-social-ai-pro' ) );
		}

		$prompt = ! empty( $custom_prompt ) ? $custom_prompt : self::craft_prompt( $post_id, $post->post_title, $post->post_type );

		$engine = new VMSAI_Image_Engine();

		$args = array(
			'channel' => 'facebook', // Native 1200x630 landscape hero aspect ratio
			'model'   => '',
		);

		$meta = array(
			'post_id' => $post_id,
			'title'   => $post->post_title . ' - Official Tour & Pilgrimage',
			'topic'   => $post->post_title,
			'format'  => 'image',
			'channel' => 'featured',
		);

		if ( ! empty( $provider ) ) {
			$meta['prefer'] = sanitize_key( $provider );
		}

		VMSAI_Logger::info( 'optimizer', sprintf( 'Starting AI image regeneration for Post #%d ("%s")', $post_id, $post->post_title ) );

		$result = $engine->create( $prompt, $args, $meta );

		if ( empty( $result['ok'] ) || empty( $result['attachment_id'] ) ) {
			$error = ! empty( $result['error'] ) ? $result['error'] : __( 'Image generation failed across all providers.', 'vm-social-ai-pro' );
			VMSAI_Logger::error( 'optimizer', sprintf( 'Regeneration failed for Post #%d: %s', $post_id, $error ) );
			return array( 'ok' => false, 'error' => $error, 'tried' => $result['tried'] ?? array() );
		}

		$new_att_id = (int) $result['attachment_id'];

		// Store previous featured image for rollback capability
		$prev_thumb = get_post_thumbnail_id( $post_id );
		if ( $prev_thumb ) {
			update_post_meta( $post_id, '_vmsai_prev_thumbnail_id', $prev_thumb );
		}

		// Update post featured image
		set_post_thumbnail( $post_id, $new_att_id );
		update_post_meta( $post_id, '_vmsai_regenerated_at', current_time( 'mysql' ) );

		// Invalidate cache
		delete_transient( 'vmsai_opt_summary_cache' );

		VMSAI_Logger::info( 'optimizer', sprintf( 'Post #%d featured image successfully updated to Attachment #%d', $post_id, $new_att_id ) );

		return array(
			'ok'            => true,
			'post_id'       => $post_id,
			'attachment_id' => $new_att_id,
			'url'           => $result['url'],
			'provider'      => $result['provider'] ?? 'ai',
			'prompt'        => $prompt,
		);
	}

	/**
	 * Add post IDs to the background regeneration queue.
	 *
	 * @param array $post_ids Array of post IDs.
	 * @return int Total queued items.
	 */
	public static function queue_posts( array $post_ids ) {
		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		foreach ( $post_ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid > 0 && ! in_array( $pid, $queue, true ) ) {
				$queue[] = $pid;
			}
		}
		update_option( self::QUEUE_OPTION, array_values( $queue ) );

		// Ensure cron event is scheduled
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 30, 'hourly', self::CRON_HOOK );
		}

		delete_transient( 'vmsai_opt_summary_cache' );
		return count( $queue );
	}

	/**
	 * Process a batch from the regeneration queue (invoked by WP-Cron or manual batch).
	 *
	 * @param int $batch_size Number of items to process.
	 * @return array Processed results.
	 */
	public static function process_batch( $batch_size = 5 ) {
		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		if ( empty( $queue ) ) {
			return array( 'processed' => 0, 'remaining' => 0 );
		}

		$to_process = array_splice( $queue, 0, $batch_size );
		$results    = array();

		foreach ( $to_process as $post_id ) {
			$res = self::regenerate_post_image( (int) $post_id );
			$results[] = array(
				'post_id' => $post_id,
				'success' => ! empty( $res['ok'] ),
				'error'   => $res['error'] ?? '',
			);
			// Throttle slightly to respect API rate limits
			sleep( 1 );
		}

		update_option( self::QUEUE_OPTION, array_values( $queue ) );
		delete_transient( 'vmsai_opt_summary_cache' );

		return array(
			'processed' => count( $results ),
			'remaining' => count( $queue ),
			'items'     => $results,
		);
	}

	/**
	 * Move orphan attachment to protected quarantine directory (SAFE STAGING - NO PERMANENT DELETION).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	public static function quarantine_attachment( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return array( 'ok' => false, 'error' => __( 'File not found on disk.', 'vm-social-ai-pro' ) );
		}

		$upload_info = wp_upload_dir();
		$basedir     = $upload_info['basedir'];

		$rel_path    = str_replace( $basedir, '', $file );
		$target_file = $basedir . '/' . self::QUARANTINE_DIR . $rel_path;
		$target_dir  = dirname( $target_file );

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return array( 'ok' => false, 'error' => __( 'Failed to create quarantine directory.', 'vm-social-ai-pro' ) );
		}

		if ( ! @rename( $file, $target_file ) ) {
			return array( 'ok' => false, 'error' => __( 'Failed to move file to quarantine.', 'vm-social-ai-pro' ) );
		}

		// Save quarantine metadata so it can be 1-click restored anytime
		update_post_meta( $attachment_id, '_vmsai_quarantined', true );
		update_post_meta( $attachment_id, '_vmsai_orig_path', $file );
		update_post_meta( $attachment_id, '_vmsai_quarantine_path', $target_file );
		update_post_meta( $attachment_id, '_vmsai_quarantined_at', current_time( 'mysql' ) );

		delete_transient( 'vmsai_opt_summary_cache' );

		return array(
			'ok'            => true,
			'attachment_id' => $attachment_id,
			'message'       => __( 'Safely moved to quarantine. Can be restored anytime with 1 click.', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Restore an attachment from quarantine back to its original location.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	public static function restore_attachment( $attachment_id ) {
		$orig_path = get_post_meta( $attachment_id, '_vmsai_orig_path', true );
		$q_path    = get_post_meta( $attachment_id, '_vmsai_quarantine_path', true );

		if ( ! $q_path || ! file_exists( $q_path ) ) {
			return array( 'ok' => false, 'error' => __( 'Quarantined file not found.', 'vm-social-ai-pro' ) );
		}

		$orig_dir = dirname( $orig_path );
		if ( ! wp_mkdir_p( $orig_dir ) ) {
			return array( 'ok' => false, 'error' => __( 'Failed to prepare original destination directory.', 'vm-social-ai-pro' ) );
		}

		if ( ! @rename( $q_path, $orig_path ) ) {
			return array( 'ok' => false, 'error' => __( 'Failed to restore file.', 'vm-social-ai-pro' ) );
		}

		delete_post_meta( $attachment_id, '_vmsai_quarantined' );
		delete_post_meta( $attachment_id, '_vmsai_orig_path' );
		delete_post_meta( $attachment_id, '_vmsai_quarantine_path' );
		delete_post_meta( $attachment_id, '_vmsai_quarantined_at' );

		delete_transient( 'vmsai_opt_summary_cache' );

		return array(
			'ok'            => true,
			'attachment_id' => $attachment_id,
			'message'       => __( 'Attachment restored to active uploads directory.', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Retrieve all 404 broken images in post_content and broken attachments.
	 *
	 * @param int $limit Max items to return.
	 * @return array
	 */
	public static function get_broken_images( $limit = 50 ) {
		global $wpdb;

		$upload_info = wp_upload_dir();
		$basedir     = $upload_info['basedir'];
		$baseurl     = $upload_info['baseurl'];

		$broken = array();

		// 1. Scan published post_content for broken local image URLs
		$posts = $wpdb->get_results(
			"SELECT ID, post_title, post_type, post_content 
			FROM {$wpdb->posts} 
			WHERE post_type IN ('post', 'page', 'togo_trip', 'event', 'product') 
			  AND post_status = 'publish' 
			ORDER BY ID DESC",
			ARRAY_A
		);

		foreach ( $posts as $p ) {
			if ( count( $broken ) >= $limit ) {
				break;
			}
			if ( empty( $p['post_content'] ) ) {
				continue;
			}

			if ( preg_match_all( '/https?:\/\/[^\s"\'<>]+\/wp-content\/uploads\/([^\s"\'<>]+)/i', $p['post_content'], $matches ) ) {
				foreach ( $matches[1] as $idx => $rel_path ) {
					if ( count( $broken ) >= $limit ) {
						break;
					}
					$clean_rel  = preg_replace( '/[?#].*$/', '', $rel_path );
					$clean_rel  = rtrim( $clean_rel, '\\/' );
					$local_path = $basedir . '/' . ltrim( $clean_rel, '/' );

					if ( ! file_exists( $local_path ) ) {
						$full_url = $matches[0][ $idx ];
						$filename = basename( $clean_rel );

						// Deduplicate by URL
						$key = md5( $full_url . $p['ID'] );
						$broken[ $key ] = array(
							'id'          => $key,
							'post_id'     => (int) $p['ID'],
							'post_title'  => $p['post_title'],
							'post_type'   => $p['post_type'],
							'url'         => $full_url,
							'filename'    => $filename,
							'missing_file'=> $clean_rel,
							'inferred'    => self::infer_prompt_for_broken_image( $p['ID'], $filename, $p['post_title'] ),
						);
					}
				}
			}
		}

		return array_values( $broken );
	}

	/**
	 * Infer visual prompt from broken image filename, parent post context, and destination topic.
	 *
	 * @param int    $post_id    Parent post ID.
	 * @param string $filename   Broken image filename.
	 * @param string $post_title Parent post title.
	 * @return string
	 */
	public static function infer_prompt_for_broken_image( $post_id, $filename, $post_title = '' ) {
		$base_name   = pathinfo( $filename, PATHINFO_FILENAME );
		$clean_name  = preg_replace( '/-\d+x\d+$/', '', $base_name );
		$clean_name  = preg_replace( '/-scaled$/', '', $clean_name );
		$clean_name  = preg_replace( '/-\d+$/', '', $clean_name );
		$words       = trim( str_replace( array( '-', '_' ), ' ', $clean_name ) );

		if ( empty( $post_title ) && $post_id ) {
			$post_title = get_the_title( $post_id );
		}
		$clean_title = preg_replace( '/[^\w\s\-]/u', '', $post_title );
		$clean_title = trim( preg_replace( '/\s+/', ' ', $clean_title ) );

		$is_generic = preg_match( '/^(image|img|event-detail|detail|p\d+|rectangle|banner|photo|dsc|pic)/i', $clean_name );

		if ( ! $is_generic && strlen( $words ) > 4 ) {
			$subject = ucwords( $words );
			return "High-definition travel photography of {$subject} for {$clean_title}, vibrant sacred atmosphere, cinematic lighting, 8k resolution, authentic editorial quality";
		}

		if ( false !== stripos( $clean_title, 'buddha' ) || false !== stripos( $clean_title, 'bodhgaya' ) ) {
			return "Luminous Mahabodhi Temple in Bodhgaya, monks in saffron robes meditating under the sacred Bodhi tree, serene morning golden sunlight, authentic spiritual heritage, 8k documentary travel photography";
		}
		if ( false !== stripos( $clean_title, 'magh mela' ) || false !== stripos( $clean_title, 'prayagraj' ) || false !== stripos( $clean_title, 'sangam' ) ) {
			return "Majestic Triveni Sangam in Prayagraj during sacred holy bathing ritual, colorful traditional boats, migratory Siberian gulls flying over confluence waters, morning sunbeams, 8k photography";
		}
		if ( false !== stripos( $clean_title, 'aarti' ) || false !== stripos( $clean_title, 'dashashwamedh' ) ) {
			return "Spectacular evening Ganga Aarti ceremony at Dashashwamedh Ghat Varanasi, priests holding multi-tiered blazing brass lamps, sacred river reflections, viewed from wooden boat, 8k cinematic photography";
		}
		if ( false !== stripos( $clean_title, 'offer' ) || false !== stripos( $clean_title, 'deal' ) ) {
			return "Luxury pilgrimage and cultural heritage travel in India, iconic Varanasi ghats and temples, premium holiday package promotional editorial aesthetic, 8k commercial photography";
		}

		return "Exquisite professional travel photography capturing the essence of {$clean_title}, vibrant natural colors, ultra-crisp focus, cinematic lighting, 8k commercial magazine quality";
	}

	/**
	 * Autonomous 404 Image Healer: Generates high-definition replacement and places it at the missing file path.
	 *
	 * @param int    $post_id       Parent post ID.
	 * @param string $broken_url    Missing image URL or relative path.
	 * @param string $custom_prompt Custom prompt or auto-inferred.
	 * @return array
	 */
	public static function heal_broken_image( $post_id, $broken_url, $custom_prompt = '' ) {
		$upload_info = wp_upload_dir();
		$basedir     = $upload_info['basedir'];
		$baseurl     = $upload_info['baseurl'];

		$rel_path    = str_replace( array( $baseurl, 'https://tripcosmos.co/wp-content/uploads', 'http://tripcosmos.co/wp-content/uploads' ), '', $broken_url );
		$rel_path    = preg_replace( '/[?#].*$/', '', $rel_path );
		$rel_path    = '/' . ltrim( $rel_path, '/' );
		$target_path = $basedir . $rel_path;
		$filename    = basename( $target_path );

		$post  = $post_id ? get_post( $post_id ) : null;
		$title = $post ? $post->post_title : $filename;

		$prompt = ! empty( $custom_prompt ) ? $custom_prompt : self::infer_prompt_for_broken_image( $post_id, $filename, $title );

		$engine = new VMSAI_Image_Engine();
		$meta   = array(
			'channel' => 'facebook',
			'post_id' => $post_id,
			'title'   => $title,
			'topic'   => $title,
			'format'  => 'image',
		);

		VMSAI_Logger::info( 'optimizer', sprintf( 'Starting 404 Image healing for "%s" in Post #%d', $filename, $post_id ) );

		$result = $engine->create( $prompt, $meta );

		if ( empty( $result['ok'] ) || empty( $result['attachment_id'] ) ) {
			return array(
				'ok'    => false,
				'error' => ! empty( $result['error'] ) ? $result['error'] : __( 'AI generation failed.', 'vm-social-ai-pro' ),
			);
		}

		$new_att_id = (int) $result['attachment_id'];
		$new_file   = get_attached_file( $new_att_id );

		$target_dir = dirname( $target_path );
		wp_mkdir_p( $target_dir );

		// Place generated file directly into target missing path so any existing URL or HTML immediately resolves
		if ( file_exists( $new_file ) && ! file_exists( $target_path ) ) {
			$target_ext = strtolower( (string) pathinfo( $target_path, PATHINFO_EXTENSION ) );
			$src_ext    = strtolower( (string) pathinfo( $new_file, PATHINFO_EXTENSION ) );

			if ( in_array( $target_ext, array( 'webp', 'avif' ), true ) && 'webp' !== $src_ext && function_exists( 'imagewebp' ) ) {
				$img = @imagecreatefromjpeg( $new_file );
				if ( ! $img ) {
					$img = @imagecreatefrompng( $new_file );
				}
				if ( $img ) {
					imagewebp( $img, $target_path, 85 );
					imagedestroy( $img );
				} else {
					@copy( $new_file, $target_path );
				}
			} else {
				@copy( $new_file, $target_path );
			}
		}

		// Also update post_content if post_id is provided
		if ( $post && ! empty( $post->post_content ) ) {
			$new_url = wp_get_attachment_url( $new_att_id );
			if ( $new_url && false !== strpos( $post->post_content, $broken_url ) ) {
				$updated_content = str_replace( $broken_url, $new_url, $post->post_content );
				wp_update_post( array(
					'ID'           => $post->ID,
					'post_content' => $updated_content,
				) );
			}
		}

		delete_transient( 'vmsai_opt_summary_cache' );

		VMSAI_Logger::info( 'optimizer', sprintf( 'Successfully healed 404 image: "%s" via Attachment #%d', $filename, $new_att_id ) );

		return array(
			'ok'            => true,
			'post_id'       => $post_id,
			'attachment_id' => $new_att_id,
			'url'           => wp_get_attachment_url( $new_att_id ),
			'healed_path'   => $target_path,
			'prompt'        => $prompt,
		);
	}
}
