<?php
/**
 * Imagick-based renderer for Open Graph cards (stub).
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Stub implementation of Imagick renderer.
 *
 * Full implementation will be added in Phase 10. Currently throws RuntimeException
 * to signal that the feature is not yet available.
 */
final class ImagickRenderer implements RendererInterface {

	/**
	 * Creates a new ImagickRenderer instance.
	 *
	 * @param FontProvider $fonts Font provider for text rendering.
	 * @phpstan-ignore-next-line -- Stub; $fonts used in Phase 10 implementation.
	 */
	public function __construct( private readonly FontProvider $fonts ) {}

	/**
	 * Not yet implemented (Phase 10).
	 *
	 * @param Template $template Template configuration.
	 * @param Payload  $payload  Data to render into the template.
	 *
	 * @return string PNG image bytes.
	 *
	 * @throws \RuntimeException Always, as this is a stub.
	 */
	public function render( Template $template, Payload $payload ): string {
		throw new \RuntimeException( 'ImagickRenderer not yet implemented (Phase 10)' );
	}
}
