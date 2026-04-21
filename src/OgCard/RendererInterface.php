<?php
/**
 * Open Graph card renderer contract.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

interface RendererInterface {

	/**
	 * Render a card to PNG bytes.
	 *
	 * @param Template $template Template configuration.
	 * @param Payload  $payload  Data to render into the template.
	 * @return string PNG image bytes.
	 * @throws \RuntimeException When rendering fails (font load, library error).
	 */
	public function render( Template $template, Payload $payload ): string;
}
