<?php
/**
 * LinkedIn platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Platforms;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * LinkedIn reads the og:* tags emitted by the Facebook platform, so this
 * class contributes no extra tags. It exists so the platform toggle in
 * settings can be surfaced uniformly and so LinkedIn-specific validation
 * (image dimensions, length limits) can hook into it later.
 */
final class LinkedIn extends AbstractPlatform {

	public function slug(): string {
		return 'linkedin';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		return [];
	}
}
