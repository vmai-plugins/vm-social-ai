<?php
/**
 * Programmatic GD poster and carousel slide typography renderer.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Composites readable editorial headlines, badges, and brand footers onto photos.
 */
class VMSAI_Card_Renderer {

	/**
	 * Find the first usable TrueType font on disk.
	 *
	 * @param bool $bold Whether to prefer a bold weight.
	 * @return string Empty string if no TTF font is found.
	 */
	public static function find_font( $bold = true ) {
		$candidates = $bold
			? array(
				'/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
				'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
				'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
			)
			: array(
				'/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
				'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
				'/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
			);

		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) && is_readable( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * Render a branded 1080x1350 social poster/carousel card over an existing photo.
	 *
	 * @param string $src_file Source image path (JPG/PNG/WEBP).
	 * @param array  $args     Keys: headline, subline, badge, cta, width, height, out_path.
	 * @return string Rendered JPEG path on success, or empty string on failure.
	 */
	public static function render_poster( $src_file, array $args = array() ) {
		if ( ! extension_loaded( 'gd' ) || ! file_exists( $src_file ) ) {
			return '';
		}

		$font_bold = self::find_font( true );
		$font_reg  = self::find_font( false ) ?: $font_bold;
		if ( ! $font_bold || ! function_exists( 'imagettftext' ) ) {
			return '';
		}

		$src = self::load_image( $src_file );
		if ( ! $src ) {
			return '';
		}

		$w = max( 600, (int) ( $args['width'] ?? 1080 ) );
		$h = max( 600, (int) ( $args['height'] ?? 1350 ) );

		$sw = imagesx( $src );
		$sh = imagesy( $src );
		if ( $sw < 50 || $sh < 50 ) {
			imagedestroy( $src );
			return '';
		}

		$canvas = imagecreatetruecolor( $w, $h );
		imagealphablending( $canvas, true );
		imagesavealpha( $canvas, true );

		// Cover-fit crop so any aspect ratio fills 1080x1350 without black bars.
		$src_ratio = $sw / $sh;
		$dst_ratio = $w / $h;
		if ( $src_ratio > $dst_ratio ) {
			$crop_h = $sh;
			$crop_w = (int) round( $sh * $dst_ratio );
			$sx     = (int) round( ( $sw - $crop_w ) / 2 );
			$sy     = 0;
		} else {
			$crop_w = $sw;
			$crop_h = (int) round( $sw / $dst_ratio );
			$sx     = 0;
			$sy     = (int) round( ( $sh - $crop_h ) / 2 );
		}
		imagecopyresampled( $canvas, $src, 0, 0, $sx, $sy, $w, $h, $crop_w, $crop_h );
		imagedestroy( $src );

		// Subtle top scrim for pill contrast + smooth deep slate bottom gradient (48% -> 100%).
		for ( $y = 0; $y < 200; $y++ ) {
			$alpha = (int) round( 78 + ( $y / 200 ) * 49 ); // 78 -> 127 transparent
			$col   = imagecolorallocatealpha( $canvas, 15, 23, 42, min( 127, $alpha ) );
			imageline( $canvas, 0, $y, $w, $y, $col );
		}
		$grad_start = (int) round( $h * 0.44 );
		for ( $y = $grad_start; $y < $h; $y++ ) {
			$t     = ( $y - $grad_start ) / max( 1, ( $h - $grad_start ) );
			$curve = pow( $t, 1.35 );
			$alpha = (int) round( 127 - ( $curve * 112 ) ); // 127 -> 15 (#0F172A rich overlay)
			$col   = imagecolorallocatealpha( $canvas, 15, 23, 42, max( 12, min( 127, $alpha ) ) );
			imageline( $canvas, 0, $y, $w, $y, $col );
		}

		$pad_x  = (int) round( $w * 0.068 );
		$max_tw = $w - ( $pad_x * 2 );

		$gold   = imagecolorallocate( $canvas, 245, 158, 11 );
		$slate  = imagecolorallocate( $canvas, 15, 23, 42 );
		$white  = imagecolorallocate( $canvas, 255, 255, 255 );
		$cream  = imagecolorallocate( $canvas, 226, 232, 240 );
		$shadow = imagecolorallocatealpha( $canvas, 0, 0, 0, 55 );

		// Top-left Saffron Badge Pill.
		$badge = trim( (string) ( $args['badge'] ?? '' ) );
		if ( '' !== $badge ) {
			$badge_up  = strtoupper( wp_strip_all_tags( $badge ) );
			$b_size    = (int) round( $w * 0.022 );
			$bbox      = imagettfbbox( $b_size, 0, $font_bold, $badge_up );
			$bw        = abs( $bbox[2] - $bbox[0] ) + 38;
			$bh        = abs( $bbox[7] - $bbox[1] ) + 22;
			$bx        = $pad_x;
			$by        = (int) round( $h * 0.045 );
			imagefilledrectangle( $canvas, $bx, $by, $bx + $bw, $by + $bh, $gold );
			imagettftext( $canvas, $b_size, 0, $bx + 19, $by + $bh - 11, $slate, $font_bold, $badge_up );
		}

		// Clean typography normalization (UTF-8 punctuation safe).
		$headline = self::clean_text( (string) ( $args['headline'] ?? '' ), 110 );
		$subline  = self::clean_text( (string) ( $args['subline'] ?? '' ), 130 );
		$cta      = self::clean_text( (string) ( $args['cta'] ?? ( get_bloginfo( 'name' ) . ' • Sacred India Tours & Cabs' ) ), 72 );

		$footer_y = $h - (int) round( $h * 0.045 );
		$cta_size = (int) round( $w * 0.022 );

		// Thin gold accent divider & footer bar.
		imagefilledrectangle( $canvas, $pad_x, $footer_y - 42, $pad_x + 90, $footer_y - 37, $gold );
		imagettftext( $canvas, $cta_size, 0, $pad_x, $footer_y, $gold, $font_bold, strtoupper( $cta ) );

		$cursor_y = $footer_y - 82;

		// Optional subline right above footer divider.
		if ( '' !== $subline && $subline !== $headline ) {
			$sub_size  = (int) round( $w * 0.026 );
			$sub_lines = array_slice( self::wrap_ttf( $subline, $font_reg, $sub_size, $max_tw ), 0, 2 );
			$sub_lh    = (int) round( $sub_size * 1.42 );
			$cursor_y -= ( count( $sub_lines ) - 1 ) * $sub_lh;
			foreach ( $sub_lines as $idx => $line ) {
				$ly = $cursor_y + ( $idx * $sub_lh );
				imagettftext( $canvas, $sub_size, 0, $pad_x + 2, $ly + 2, $shadow, $font_reg, $line );
				imagettftext( $canvas, $sub_size, 0, $pad_x, $ly, $cream, $font_reg, $line );
			}
			$cursor_y -= 42;
		}

		// Main bold headline.
		if ( '' !== $headline ) {
			$h_size  = (int) round( $w * 0.044 );
			$h_lines = self::wrap_ttf( $headline, $font_bold, $h_size, $max_tw );
			if ( count( $h_lines ) > 4 ) {
				$h_size  = (int) round( $w * 0.037 );
				$h_lines = array_slice( self::wrap_ttf( $headline, $font_bold, $h_size, $max_tw ), 0, 4 );
			}
			$h_lh    = (int) round( $h_size * 1.32 );
			$start_y = $cursor_y - ( ( count( $h_lines ) - 1 ) * $h_lh );
			foreach ( $h_lines as $idx => $line ) {
				$ly = $start_y + ( $idx * $h_lh );
				imagettftext( $canvas, $h_size, 0, $pad_x + 3, $ly + 3, $shadow, $font_bold, $line );
				imagettftext( $canvas, $h_size, 0, $pad_x, $ly, $white, $font_bold, $line );
			}
		}

		$out_path = ! empty( $args['out_path'] )
			? (string) $args['out_path']
			: preg_replace( '/\.[a-z0-9]+$/i', '', $src_file ) . '-card.jpg';

		$tmp = $out_path . '.tmp.jpg';
		$ok  = @imagejpeg( $canvas, $tmp, 88 );
		imagedestroy( $canvas );

		if ( $ok && file_exists( $tmp ) && filesize( $tmp ) > 5000 ) {
			@chmod( $tmp, 0644 );
			@rename( $tmp, $out_path );
			return $out_path;
		}
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		return '';
	}

	/**
	 * Load JPG, PNG, or WEBP into a GD resource safely.
	 *
	 * @param string $path File path.
	 * @return GdImage|resource|false
	 */
	private static function load_image( $path ) {
		$ext = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'webp' === $ext && function_exists( 'imagecreatefromwebp' ) ) {
			$im = @imagecreatefromwebp( $path );
			if ( $im ) {
				return $im;
			}
		}
		$raw = @file_get_contents( $path ); // phpcs:ignore
		return $raw ? @imagecreatefromstring( $raw ) : false;
	}

	/**
	 * Word-wrap text based on true pixel width for imagettftext.
	 *
	 * @param string $text     Input string.
	 * @param string $font     Font path.
	 * @param int    $size     Font size.
	 * @param int    $max_w    Max width in pixels.
	 * @return string[]
	 */
	private static function wrap_ttf( $text, $font, $size, $max_w ) {
		$words = preg_split( '/\s+/', trim( $text ) ) ?: array();
		$lines = array();
		$cur   = '';
		foreach ( $words as $word ) {
			$cand = '' === $cur ? $word : $cur . ' ' . $word;
			$box  = imagettfbbox( $size, 0, $font, $cand );
			if ( abs( $box[2] - $box[0] ) <= $max_w || '' === $cur ) {
				$cur = $cand;
			} else {
				$lines[] = $cur;
				$cur     = $word;
			}
		}
		if ( '' !== $cur ) {
			$lines[] = $cur;
		}
		return $lines;
	}

	/**
	 * Clean HTML, emojis that Noto lacks, and excessive length from card text.
	 *
	 * @param string $text Input text.
	 * @param int    $max  Character cap.
	 * @return string
	 */
	private static function clean_text( $text, $max ) {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		// Strip astral emoji symbols that render as tofu boxes in NotoSans TTF.
		$text = preg_replace( '/[\x{10000}-\x{10FFFF}]/u', '', $text );
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
		if ( mb_strlen( $text ) > $max ) {
			$text = rtrim( mb_substr( $text, 0, $max - 1 ) ) . '...';
		}
		return $text;
	}
}
