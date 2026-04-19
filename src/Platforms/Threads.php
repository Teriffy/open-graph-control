<?php
/**
 * Threads (Meta) platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Platforms;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Threads reads og:* exactly like Facebook; no extra tags needed.
 */
final class Threads extends AbstractPlatform {

	public function slug(): string {
		return 'threads';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		return [];
	}
}
