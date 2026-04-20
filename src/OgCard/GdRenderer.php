<?php
/**
 * GD library-based renderer for Open Graph cards.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Open Graph cards to PNG using PHP's GD library.
 *
 * Creates 1200x630 PNG images with configurable backgrounds (solid, gradient, image),
 * text overlays, site branding, and metadata. Manages canvas lifecycle, color allocation,
 * and PNG output buffering. Requires GD extension to be loaded.
 */
final class GdRenderer implements RendererInterface {

	/**
	 * Canvas width in pixels.
	 */
	private const WIDTH = 1200;

	/**
	 * Canvas height in pixels.
	 */
	private const HEIGHT = 630;

	/**
	 * Creates a new GdRenderer instance.
	 *
	 * @param FontProvider $fonts Font provider for text rendering.
	 */
	public function __construct(
		private readonly FontProvider $fonts
	) {}

	/**
	 * Renders a card to PNG bytes.
	 *
	 * @param Template $template Template configuration.
	 * @param Payload  $payload  Data to render into the template.
	 *
	 * @return string PNG image bytes.
	 *
	 * @throws \RuntimeException When GD extension is not loaded or rendering fails.
	 */
	public function render( Template $template, Payload $payload ): string {
		if ( ! extension_loaded( 'gd' ) ) {
			throw new \RuntimeException( 'GD extension not loaded' );
		}

		$canvas = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
		if ( ! $canvas ) {
			throw new \RuntimeException( 'imagecreatetruecolor failed' );
		}

		imagealphablending( $canvas, true );
		imagesavealpha( $canvas, true );

		$this->paint_background( $canvas, $template );
		if ( $template->logo_id > 0 ) {
			$this->paint_logo( $canvas, $template->logo_id );
		}
		$this->paint_title( $canvas, $template, $payload );
		$this->paint_description( $canvas, $template, $payload );
		if ( $template->show_site_name ) {
			$this->paint_site_name( $canvas, $template, $payload );
		}
		if ( $template->show_meta_line ) {
			$this->paint_meta_line( $canvas, $template, $payload );
		}

		ob_start();
		imagepng( $canvas, null, 6 );
		$bytes = (string) ob_get_clean();

		return $bytes;
	}

	/**
	 * Paints the background on the canvas.
	 *
	 * Dispatches to appropriate background painter based on template bg_type.
	 * Supports solid, gradient, and image backgrounds.
	 *
	 * @param \GdImage $canvas   The canvas image resource.
	 * @param Template $template Template configuration.
	 *
	 * @return void
	 * @throws \RuntimeException When color allocation fails.
	 */
	private function paint_background( \GdImage $canvas, Template $template ): void {
		if ( 'solid' === $template->bg_type ) {
			$rgb   = $this->hex_to_rgb( $template->bg_color );
			$color = imagecolorallocate( $canvas, $rgb[0], $rgb[1], $rgb[2] );
			if ( false === $color ) {
				throw new \RuntimeException( 'imagecolorallocate failed' );
			}
			imagefilledrectangle( $canvas, 0, 0, self::WIDTH, self::HEIGHT, $color );
			return;
		}
		if ( 'gradient' === $template->bg_type ) {
			$this->paint_gradient( $canvas, $template->bg_color, $template->bg_gradient_to );
			return;
		}
		if ( 'image' === $template->bg_type && $template->bg_image_id > 0 ) {
			$this->paint_image_bg( $canvas, $template->bg_image_id );
			return;
		}
		// Fallback to gradient if image fails.
		$this->paint_gradient( $canvas, $template->bg_color, $template->bg_gradient_to );
	}

	/**
	 * Paints a gradient background from one color to another.
	 *
	 * Creates a 135° gradient (top-left to bottom-right) by drawing diagonal
	 * lines with linearly interpolated colors across all diagonals.
	 *
	 * @param \GdImage $canvas   The canvas image resource.
	 * @param string   $from_hex Starting color in hex format.
	 * @param string   $to_hex   Ending color in hex format.
	 *
	 * @return void
	 * @throws \RuntimeException When color allocation fails.
	 */
	private function paint_gradient( \GdImage $canvas, string $from_hex, string $to_hex ): void {
		[ $fr, $fg, $fb ] = $this->hex_to_rgb( $from_hex );
		[ $tr, $tg, $tb ] = $this->hex_to_rgb( $to_hex );
		// 135° gradient (top-left → bottom-right).
		$steps = self::WIDTH + self::HEIGHT;
		for ( $i = 0; $i < $steps; $i++ ) {
			$ratio = $i / $steps;
			$r     = (int) ( $fr + ( $tr - $fr ) * $ratio );
			$g     = (int) ( $fg + ( $tg - $fg ) * $ratio );
			$b     = (int) ( $fb + ( $tb - $fb ) * $ratio );
			$color = imagecolorallocate( $canvas, $r, $g, $b );
			if ( false === $color ) {
				throw new \RuntimeException( 'imagecolorallocate failed' );
			}
			// Diagonal line at constant x+y = i.
			imageline( $canvas, $i, 0, 0, $i, $color );
		}
	}

	/**
	 * Paints an image background with cover crop and 60% black overlay.
	 *
	 * Loads the image from the given attachment ID, scales and crops it to cover
	 * the canvas (center-aligned), and applies a semi-transparent black overlay
	 * for text contrast.
	 *
	 * @param \GdImage $canvas        The canvas image resource.
	 * @param int      $attachment_id Attachment ID to load image from.
	 *
	 * @return void
	 */
	private function paint_image_bg( \GdImage $canvas, int $attachment_id ): void {
		if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
			return;
		}
		$src = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! $src || ! file_exists( $src[0] ) ) {
			return;
		}
		$bg = $this->load_image( $src[0] );
		if ( ! $bg ) {
			return;
		}
		$bg_w = imagesx( $bg );
		$bg_h = imagesy( $bg );
		// Cover crop: scale shorter side to canvas, crop center.
		$scale    = max( self::WIDTH / $bg_w, self::HEIGHT / $bg_h );
		$scaled_w = (int) ( $bg_w * $scale );
		$scaled_h = (int) ( $bg_h * $scale );
		$offset_x = (int) ( ( self::WIDTH - $scaled_w ) / 2 );
		$offset_y = (int) ( ( self::HEIGHT - $scaled_h ) / 2 );
		imagecopyresampled( $canvas, $bg, $offset_x, $offset_y, 0, 0, $scaled_w, $scaled_h, $bg_w, $bg_h );
		// phpcs:disable Generic.PHP.DeprecatedFunctions.Deprecated -- 8.5 deprecation; we explicitly free memory.
		imagedestroy( $bg );
		// phpcs:enable Generic.PHP.DeprecatedFunctions.Deprecated
		// 60% black overlay for text contrast.
		$overlay = imagecolorallocatealpha( $canvas, 0, 0, 0, 51 );
		if ( false !== $overlay ) {
			imagefilledrectangle( $canvas, 0, 0, self::WIDTH, self::HEIGHT, $overlay );
		}
	}

	/**
	 * Loads an image from disk and returns a GD image resource.
	 *
	 * Supports JPEG, PNG, GIF, and WebP (if available). Returns null if the file
	 * cannot be loaded or its MIME type is not supported.
	 *
	 * @param string $path File path to the image.
	 *
	 * @return ?\GdImage GD image resource or null on failure.
	 */
	private function load_image( string $path ): ?\GdImage {
		$info = getimagesize( $path );
		if ( ! $info ) {
			return null;
		}
		$result = match ( $info['mime'] ) {
			'image/jpeg' => imagecreatefromjpeg( $path ),
			'image/png'  => imagecreatefrompng( $path ),
			'image/gif'  => imagecreatefromgif( $path ),
			'image/webp' => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $path ) : null,
			default      => null,
		};
		return false === $result ? null : $result;
	}

	/**
	 * Converts a hex color string to RGB values.
	 *
	 * @param string $hex Hex color string (e.g., '#ff0000' or 'ff0000').
	 *
	 * @return array<int,int> RGB values as [red, green, blue].
	 */
	private function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		return [
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		];
	}

	// 1200 - 2*80.
	private const PADDING_X      = 80;
	private const TITLE_MAX_W    = 1040;
	private const TITLE_TOP_Y    = 240;
	private const TITLE_LINE_GAP = 12;
	private const DESC_TOP_Y     = 470;
	private const DESC_LINE_GAP  = 8;
	private const DESC_MAX_LINES = 2;
	private const SITE_NAME_Y    = 100;
	private const META_Y         = 570;
	private const LOGO_SIZE      = 36;
	private const LOGO_X         = 80;
	private const LOGO_Y         = 70;

	/**
	 * Paints the title on the canvas with auto-shrink and word-wrap.
	 *
	 * Renders the title using the template's text color and bold font. Automatically
	 * shrinks font size from 60px down to 36px if needed to fit within 3 lines. If even
	 * 36px exceeds 3 lines, truncates with ellipsis.
	 *
	 * @param \GdImage $canvas   The canvas image resource.
	 * @param Template $template Template configuration.
	 * @param Payload  $payload  Payload data containing the title.
	 *
	 * @return void
	 * @throws \RuntimeException When color allocation fails.
	 */
	private function paint_title( \GdImage $canvas, Template $template, Payload $payload ): void {
		$font_path = $this->fonts->path( 'bold' );
		$rgb       = $this->hex_to_rgb( $template->text_color );
		$color     = imagecolorallocate( $canvas, $rgb[0], $rgb[1], $rgb[2] );
		if ( false === $color ) {
			throw new \RuntimeException( 'imagecolorallocate failed' );
		}

		[ $size, $lines ] = $this->fit_title( $payload->title, $font_path );

		$y = self::TITLE_TOP_Y;
		foreach ( $lines as $line ) {
			$bbox = imagettfbbox( $size, 0, $font_path, $line );
			if ( false === $bbox ) {
				continue;
			}
			/** @phpstan-var int $bbox_1 */
			$bbox_1 = $bbox[1];
			/** @phpstan-var int $bbox_7 */
			$bbox_7 = $bbox[7];
			$line_h = abs( $bbox_7 - $bbox_1 );
			imagettftext( $canvas, $size, 0, self::PADDING_X, $y + $line_h, $color, $font_path, $line );
			$y += $line_h + self::TITLE_LINE_GAP;
		}
	}

	/**
	 * Picks the smallest acceptable font size that wraps title to ≤ 3 lines.
	 *
	 * Tries font sizes 60, 52, 44, 36 in order. If even 36px gives >3 lines,
	 * keeps 36px and truncates to 3 lines with ellipsis.
	 *
	 * @param string $title     The title to fit.
	 * @param string $font_path Path to the TrueType font file.
	 *
	 * @return array{0:int,1:list<string>} Tuple of [font_size, wrapped_lines].
	 */
	private function fit_title( string $title, string $font_path ): array {
		foreach ( [ 60, 52, 44, 36 ] as $size ) {
			$lines = $this->wrap_to_lines( $title, $font_path, $size, self::TITLE_MAX_W );
			if ( count( $lines ) <= 3 ) {
				return [ $size, $lines ];
			}
		}
		// Last resort: keep 36px, truncate to 3 lines with ellipsis.
		$lines   = $this->wrap_to_lines( $title, $font_path, 36, self::TITLE_MAX_W );
		$kept    = array_slice( $lines, 0, 3 );
		$kept[2] = rtrim( $kept[2], ' ' ) . ' …';
		return [ 36, $kept ];
	}

	/**
	 * Wraps text to multiple lines based on available width.
	 *
	 * Uses greedy word-wrap: tries to fit as many words as possible on each line
	 * before moving to the next line.
	 *
	 * @param string $text     The text to wrap.
	 * @param string $font_path Path to the TrueType font file.
	 * @param int    $size     Font size in pixels.
	 * @param int    $max_w    Maximum line width in pixels.
	 *
	 * @return list<string> Array of wrapped lines.
	 */
	private function wrap_to_lines( string $text, string $font_path, int $size, int $max_w ): array {
		$words = preg_split( '/\s+/u', trim( $text ) );
		if ( false === $words ) {
			$words = [];
		}
		$lines = [];
		$cur   = '';
		foreach ( $words as $word ) {
			$candidate = '' === $cur ? $word : $cur . ' ' . $word;
			$bbox      = imagettfbbox( $size, 0, $font_path, $candidate );
			if ( false === $bbox ) {
				$cur = $candidate;
				continue;
			}
			/** @phpstan-var int $bbox_0 */
			$bbox_0 = $bbox[0];
			/** @phpstan-var int $bbox_2 */
			$bbox_2 = $bbox[2];
			$width  = abs( $bbox_2 - $bbox_0 );
			if ( $width > $max_w && '' !== $cur ) {
				$lines[] = $cur;
				$cur     = $word;
			} else {
				$cur = $candidate;
			}
		}
		if ( '' !== $cur ) {
			$lines[] = $cur;
		}
		return $lines;
	}

	/**
	 * Paints the description on the canvas with word-wrap and 2-line truncation.
	 *
	 * Renders the description using the template's text color and regular font. Wraps text
	 * to fit within the available width and truncates to a maximum of 2 lines with ellipsis.
	 * Returns early if the description is empty.
	 *
	 * @param \GdImage $canvas   The canvas image resource.
	 * @param Template $template Template configuration.
	 * @param Payload  $payload  Payload data containing the description.
	 *
	 * @return void
	 * @throws \RuntimeException When color allocation fails.
	 */
	private function paint_description( \GdImage $canvas, Template $template, Payload $payload ): void {
		if ( '' === $payload->description ) {
			return;
		}
		$font_path = $this->fonts->path( 'regular' );
		$rgb       = $this->hex_to_rgb( $template->text_color );
		$color     = imagecolorallocate( $canvas, $rgb[0], $rgb[1], $rgb[2] );
		if ( false === $color ) {
			throw new \RuntimeException( 'imagecolorallocate failed' );
		}

		$size  = 28;
		$lines = $this->wrap_to_lines( $payload->description, $font_path, $size, self::TITLE_MAX_W );
		if ( count( $lines ) > self::DESC_MAX_LINES ) {
			$lines                             = array_slice( $lines, 0, self::DESC_MAX_LINES );
			$lines[ self::DESC_MAX_LINES - 1 ] = rtrim( $lines[ self::DESC_MAX_LINES - 1 ], ' ' ) . ' …';
		}

		$y = self::DESC_TOP_Y;
		foreach ( $lines as $line ) {
			$bbox = imagettfbbox( $size, 0, $font_path, $line );
			if ( false === $bbox ) {
				continue;
			}
			/** @phpstan-var int $bbox_1 */
			$bbox_1 = $bbox[1];
			/** @phpstan-var int $bbox_7 */
			$bbox_7 = $bbox[7];
			$line_h = abs( $bbox_7 - $bbox_1 );
			imagettftext( $canvas, $size, 0, self::PADDING_X, $y + $line_h, $color, $font_path, $line );
			$y += $line_h + self::DESC_LINE_GAP;
		}
	}

	/**
	 * Paints the site name header on the canvas.
	 *
	 * Renders the site name in uppercase bold font at the top of the card. Returns early
	 * if the site name is empty. Applies logo offset if a logo is present.
	 *
	 * @param \GdImage $canvas   The canvas image resource.
	 * @param Template $template Template configuration.
	 * @param Payload  $payload  Payload data containing the site name.
	 *
	 * @return void
	 * @throws \RuntimeException When color allocation fails.
	 */
	private function paint_site_name( \GdImage $canvas, Template $template, Payload $payload ): void {
		if ( '' === $payload->site_name ) {
			return;
		}
		$font_path = $this->fonts->path( 'bold' );
		$rgb       = $this->hex_to_rgb( $template->text_color );
		$color     = imagecolorallocate( $canvas, $rgb[0], $rgb[1], $rgb[2] );
		if ( false === $color ) {
			throw new \RuntimeException( 'imagecolorallocate failed' );
		}
		$text = mb_strtoupper( $payload->site_name );
		// Logo width + gap.
		$logo_offset = $template->logo_id > 0 ? 56 : 0;
		imagettftext( $canvas, 18, 0, self::PADDING_X + $logo_offset, self::SITE_NAME_Y, $color, $font_path, $text );
	}

	/**
	 * Paints the meta line footer on the canvas.
	 *
	 * Renders the meta information line at 70% opacity (30% transparent) at the bottom
	 * of the card using regular font. Returns early if the meta line is empty.
	 *
	 * @param \GdImage $canvas   The canvas image resource.
	 * @param Template $template Template configuration.
	 * @param Payload  $payload  Payload data containing the meta line.
	 *
	 * @return void
	 * @throws \RuntimeException When color allocation fails.
	 */
	private function paint_meta_line( \GdImage $canvas, Template $template, Payload $payload ): void {
		if ( '' === $payload->meta_line ) {
			return;
		}
		$font_path     = $this->fonts->path( 'regular' );
		[ $r, $g, $b ] = $this->hex_to_rgb( $template->text_color );
		// 70% opacity via alpha 38/127 ≈ 30% transparent.
		$color = imagecolorallocatealpha( $canvas, $r, $g, $b, 38 );
		if ( false === $color ) {
			throw new \RuntimeException( 'imagecolorallocatealpha failed' );
		}
		imagettftext( $canvas, 20, 0, self::PADDING_X, self::META_Y, $color, $font_path, $payload->meta_line );
	}

	/**
	 * Paints a logo on the canvas.
	 *
	 * Loads the logo image from the given attachment ID, center-crops it to a square,
	 * and scales it to a fixed size. Returns early if the attachment doesn't exist or
	 * the image cannot be loaded.
	 *
	 * @param \GdImage $canvas        The canvas image resource.
	 * @param int      $attachment_id Attachment ID to load logo from.
	 *
	 * @return void
	 */
	private function paint_logo( \GdImage $canvas, int $attachment_id ): void {
		if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
			return;
		}
		$src = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
		if ( ! $src || ! file_exists( $src[0] ) ) {
			return;
		}
		$logo = $this->load_image( $src[0] );
		if ( ! $logo ) {
			return;
		}
		$w = imagesx( $logo );
		$h = imagesy( $logo );
		// Center crop to square, then scale to LOGO_SIZE.
		$side   = min( $w, $h );
		$crop_x = (int) ( ( $w - $side ) / 2 );
		$crop_y = (int) ( ( $h - $side ) / 2 );
		imagecopyresampled( $canvas, $logo, self::LOGO_X, self::LOGO_Y, $crop_x, $crop_y, self::LOGO_SIZE, self::LOGO_SIZE, $side, $side );
		// phpcs:disable Generic.PHP.DeprecatedFunctions.Deprecated -- 8.5 deprecation; we explicitly free memory.
		imagedestroy( $logo );
		// phpcs:enable Generic.PHP.DeprecatedFunctions.Deprecated
	}
}
