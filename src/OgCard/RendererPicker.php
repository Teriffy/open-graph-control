<?php
/**
 * Renderer picker with Imagick opt-in support.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Picks the appropriate card renderer based on user preference and availability.
 *
 * Default renderer is GdRenderer. Users can opt in to ImagickRenderer via the
 * `ogc_card_renderer_prefer_imagick` filter. Imagick is only used if the filter
 * returns true AND the imagick extension is loaded.
 */
final class RendererPicker {

	/**
	 * Creates a new RendererPicker instance.
	 *
	 * @param FontProvider $fonts Font provider for text rendering.
	 */
	public function __construct( private readonly FontProvider $fonts ) {}

	/**
	 * Picks and returns an appropriate renderer.
	 *
	 * Checks the `ogc_card_renderer_prefer_imagick` filter. If true and
	 * imagick extension is loaded, returns ImagickRenderer. Otherwise,
	 * returns GdRenderer.
	 *
	 * @return RendererInterface The selected renderer instance.
	 */
	public function pick(): RendererInterface {
		$prefer = (bool) apply_filters( 'ogc_card_renderer_prefer_imagick', false );
		if ( $prefer && extension_loaded( 'imagick' ) ) {
			return new ImagickRenderer( $this->fonts );
		}
		return new GdRenderer( $this->fonts );
	}
}
