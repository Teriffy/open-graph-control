<?php
/**
 * Platform contract.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * A platform produces zero or more <meta> tags for a rendering context.
 *
 * Most platforms read the global og:* stream from the Facebook platform;
 * they exist as classes so UI can toggle them, validation can warn per
 * platform, and future versions can add platform-specific overrides.
 */
interface PlatformInterface {

	public function slug(): string;

	public function is_enabled(): bool;

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array;

	/**
	 * Optional JSON-LD payload (e.g. Pinterest Rich Pins). Null when not applicable.
	 */
	public function json_ld( Context $context ): ?string;
}
