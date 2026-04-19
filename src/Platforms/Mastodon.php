<?php
/**
 * Mastodon platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Mastodon reads og:* for the card and additionally uses the
 * fediverse:creator meta (name=) to link the shared post to a
 * specific Mastodon account via the verified-link mechanism.
 */
final class Mastodon extends AbstractPlatform {

	public function slug(): string {
		return 'mastodon';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		$creator = (string) $this->options->get_path( 'platforms.mastodon.fediverse_creator' );
		if ( '' === $creator ) {
			return [];
		}
		return [ new Tag( Tag::KIND_NAME, 'fediverse:creator', $creator ) ];
	}
}
