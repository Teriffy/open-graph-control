<?php
/**
 * WhatsApp platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * WhatsApp reads og:* for link previews. It is strict about image size
 * (< ~300 KB); the validation layer will warn the author when an image
 * exceeds the configured threshold. This class emits no extra tags.
 */
final class WhatsApp extends AbstractPlatform {

	public function slug(): string {
		return 'whatsapp';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		return [];
	}
}
