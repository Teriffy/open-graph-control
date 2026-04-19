<?php
/**
 * Telegram platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Telegram reads og:* for link previews and Instant View. No platform-
 * specific meta tags are needed; class exists for uniform toggles.
 */
final class Telegram extends AbstractPlatform {

	public function slug(): string {
		return 'telegram';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		return [];
	}
}
