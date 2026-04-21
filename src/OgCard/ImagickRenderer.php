<?php
/**
 * Imagick-based renderer for Open Graph cards.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Imagick-based renderer — DEFERRED TO v0.5.
 *
 * The class is kept as a type-safe placeholder so RendererPicker's filter hook
 * (`ogc_card_renderer_prefer_imagick`) has a return type it can reference in
 * signatures. In v0.4, RendererPicker::pick() never instantiates this class;
 * GdRenderer handles all rendering.
 *
 * Full Imagick implementation (mirroring GdRenderer's gradient + text + logo
 * pipeline via Imagick / ImagickDraw) is planned for v0.5 when we have a CI
 * environment with ext-imagick for visual regression testing.
 */
final class ImagickRenderer implements RendererInterface {

	/**
	 * Creates a new ImagickRenderer instance.
	 *
	 * @param FontProvider $fonts Font provider for text rendering.
	 * @phpstan-ignore-next-line -- Placeholder; $fonts will be used in v0.5 implementation.
	 */
	public function __construct( private readonly FontProvider $fonts ) {}

	/**
	 * Not yet implemented — deferred to v0.5.
	 *
	 * @param Template $template Template configuration.
	 * @param Payload  $payload  Data to render into the template.
	 *
	 * @return string PNG image bytes.
	 *
	 * @throws \RuntimeException Always in v0.4, as this is deferred to v0.5.
	 */
	public function render( Template $template, Payload $payload ): string {
		throw new \RuntimeException(
			'ImagickRenderer is deferred to v0.5. v0.4 uses GdRenderer exclusively.'
		);
	}
}
