<?php
/**
 * X / Twitter platform.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Platforms;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Images\SizeRegistry;
use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Emits the twitter:* meta stream.
 *
 * X / Twitter falls back to og:* when twitter:* is missing, but explicit
 * twitter tags give us control over card type and let us swap in a
 * different image variant (square for 'summary', landscape for
 * 'summary_large_image').
 */
final class Twitter extends AbstractPlatform {

	public function slug(): string {
		return 'twitter';
	}

	/**
	 * @return Tag[]
	 */
	public function tags( Context $context ): array {
		$card = (string) $this->options->get_path( 'platforms.twitter.card' );
		if ( '' === $card ) {
			$card = 'summary_large_image';
		}

		$tags = [
			new Tag( Tag::KIND_NAME, 'twitter:card', $card ),
			new Tag( Tag::KIND_NAME, 'twitter:title', (string) $this->title->resolve( $context ) ),
			new Tag( Tag::KIND_NAME, 'twitter:description', (string) $this->description->resolve( $context ) ),
		];

		$site = (string) $this->options->get_path( 'platforms.twitter.site' );
		if ( '' !== $site ) {
			$tags[] = new Tag( Tag::KIND_NAME, 'twitter:site', $site );
		}
		$creator = (string) $this->options->get_path( 'platforms.twitter.creator' );
		if ( '' !== $creator ) {
			$tags[] = new Tag( Tag::KIND_NAME, 'twitter:creator', $creator );
		}

		foreach ( $this->image_tags( $context, $card ) as $tag ) {
			$tags[] = $tag;
		}

		return $tags;
	}

	/**
	 * @return Tag[]
	 */
	private function image_tags( Context $context, string $card ): array {
		$reference = $this->image->resolve( $context );
		if ( null === $reference || '' === $reference ) {
			return [];
		}

		if ( ! ctype_digit( $reference ) ) {
			return [ new Tag( Tag::KIND_NAME, 'twitter:image', $reference ) ];
		}

		$size = 'summary' === $card ? SizeRegistry::SQUARE : SizeRegistry::LANDSCAPE;
		$src  = wp_get_attachment_image_src( (int) $reference, $size );
		if ( ! is_array( $src ) ) {
			return [];
		}

		$tags = [ new Tag( Tag::KIND_NAME, 'twitter:image', (string) $src[0] ) ];

		$alt = (string) get_post_meta( (int) $reference, '_wp_attachment_image_alt', true );
		if ( '' !== $alt ) {
			$tags[] = new Tag( Tag::KIND_NAME, 'twitter:image:alt', $alt );
		}
		return $tags;
	}
}
