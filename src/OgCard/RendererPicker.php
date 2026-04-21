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
 * Default renderer is GdRenderer. The `ogc_card_renderer_prefer_imagick` filter is
 * reserved for v0.5+. In v0.4, RendererPicker always returns GdRenderer; the filter
 * is evaluated to expose it in plugin audits and prepare integrators for v0.5.
 *
 * ImagickRenderer full implementation is deferred to v0.5; pick() always returns
 * GdRenderer in v0.4.
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
	 * In v0.4, always returns GdRenderer. The `ogc_card_renderer_prefer_imagick`
	 * filter is evaluated to expose it in plugin audits and prepare integrators.
	 *
	 * @return RendererInterface The selected renderer instance (always GdRenderer in v0.4).
	 */
	public function pick(): RendererInterface {
		/**
		 * Imagick opt-in filter — reserved for v0.5+.
		 * v0.4 always returns GdRenderer regardless of this filter; the filter is
		 * evaluated to expose it in plugin audits and prepare integrators.
		 */
		apply_filters( 'ogc_card_renderer_prefer_imagick', false );

		return new GdRenderer( $this->fonts );
	}
}
