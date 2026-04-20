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
	 * Supports solid, gradient, and image (fallthrough) backgrounds.
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
		// Image bg handled in next task.
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
