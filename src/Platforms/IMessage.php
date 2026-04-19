<?php
/**
 * IMessage (iOS) platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * IMessage renders rich previews from og:* plus (optionally) twitter:card.
 * This class contributes no extra tags for MVP; the "prefer_square" option
 * will influence image sizing in a later phase via a filter.
 */
final class IMessage extends AbstractPlatform {

	public function slug(): string {
		return 'imessage';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		return [];
	}
}
