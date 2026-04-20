<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

interface RendererInterface {

	/**
	 * Render a card to PNG bytes.
	 *
	 * @throws \RuntimeException When rendering fails (font load, library error).
	 */
	public function render( Template $template, Payload $payload ): string;
}
