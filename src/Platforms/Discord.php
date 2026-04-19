<?php
/**
 * Discord platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Discord renders embed cards from og:* and colors the left bar using
 * <meta name="theme-color">. The value comes from site.theme_color.
 */
final class Discord extends AbstractPlatform {

	public function slug(): string {
		return 'discord';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		$color = (string) $this->options->get_path( 'site.theme_color' );
		if ( '' === $color ) {
			return [];
		}
		return [ new Tag( Tag::KIND_NAME, 'theme-color', $color ) ];
	}
}
