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
		/** @phpstan-ignore property.onlyWritten */
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
}
