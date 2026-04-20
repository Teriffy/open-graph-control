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
	public function __construct( private readonly FontProvider $fonts ) {}

	/**
	 * Gets the font provider.
	 *
	 * @return FontProvider The font provider for text rendering.
	 *
	 * @internal Used in Tasks 2.4+ for title and description rendering.
	 */
	protected function get_fonts(): FontProvider {
		return $this->fonts;
	}

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
		// phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- imagedestroy is deprecated in PHP 8.0+ but still necessary for compatibility.
		imagedestroy( $canvas );

		return $bytes;
	}

	/**
	 * Paints the background on the canvas.
	 *
	 * Currently supports solid backgrounds only. Future enhancements will add
	 * gradient and image backgrounds.
	 *
	 * @param \GdImage $canvas   The canvas image resource.
	 * @param Template $template Template configuration.
	 *
	 * @return void
	 * @throws \RuntimeException When color allocation fails.
	 */
	private function paint_background( \GdImage $canvas, Template $template ): void {
		$rgb   = $this->hex_to_rgb( $template->bg_color );
		$color = imagecolorallocate( $canvas, $rgb[0], $rgb[1], $rgb[2] );
		if ( false === $color ) {
			throw new \RuntimeException( 'imagecolorallocate failed' );
		}
		imagefilledrectangle( $canvas, 0, 0, self::WIDTH, self::HEIGHT, $color );
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
