<?php
/**
 * <meta> tag renderer.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Renderer;

/**
 * Turns Tag[] into escaped <meta> HTML.
 *
 * Non-strict mode additionally emits a <meta name="og:*"> fallback for every
 * property-kind OG tag. That's HTML5-valid (og:* is registered under WHATWG
 * MetaExtensions) and maximizes compatibility with scrapers that only read
 * name=. Strict mode limits each tag to its canonical form.
 */
final class TagBuilder {

	public function __construct( private bool $strict = false ) {}

	/**
	 * @param Tag[] $tags
	 */
	public function render( array $tags ): string {
		$lines = [];
		foreach ( $tags as $tag ) {
			if ( '' === $tag->content ) {
				continue;
			}
			$content = esc_attr( $tag->content );
			$key     = esc_attr( $tag->key );

			if ( Tag::KIND_PROPERTY === $tag->kind ) {
				$lines[] = sprintf( '<meta property="%s" content="%s" />', $key, $content );
				if ( ! $this->strict ) {
					$lines[] = sprintf( '<meta name="%s" content="%s" />', $key, $content );
				}
			} else {
				$lines[] = sprintf( '<meta name="%s" content="%s" />', $key, $content );
			}
		}
		return implode( "\n", $lines );
	}
}
