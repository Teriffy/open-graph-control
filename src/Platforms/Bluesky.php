<?php
/**
 * Bluesky platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Bluesky reads og:* for embed cards; no Bluesky-specific meta tags exist
 * yet (late 2025). Class exists so the platform toggle surfaces uniformly.
 */
final class Bluesky extends AbstractPlatform {

	public function slug(): string {
		return 'bluesky';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		return [];
	}
}
