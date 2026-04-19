<?php
/**
 * Slack platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Platforms;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Slack reads both og:* and twitter:* for unfurls and prefers the more
 * informative of the two. No Slack-specific meta tags required.
 */
final class Slack extends AbstractPlatform {

	public function slug(): string {
		return 'slack';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		return [];
	}
}
