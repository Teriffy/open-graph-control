<?php
/**
 * CardStore for managing OG card file operations.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton-like store managing OG card file paths and I/O.
 *
 * Provides a single interface for all card-related filesystem operations,
 * including path generation with template version hashing.
 */
final class CardStore {

	/**
	 * Creates a new CardStore instance.
	 *
	 * @param string $base_dir Base directory for card storage (usually wp-content/uploads/ogc).
	 */
	public function __construct( private readonly string $base_dir ) {}

	/**
	 * Generates the filesystem path for a card image.
	 *
	 * Path includes template hash for cache invalidation when template changes.
	 * Format varies by card type:
	 *   - post: {base}/og-cards/post-{id}-{hash}-{size}.png
	 *   - archive: {base}/og-cards/archive/{taxonomy}-{id}-{hash}-{size}.png
	 *   - author: {base}/og-cards/author/{id}-{hash}-{size}.png
	 *
	 * @param CardKey  $key      Identifies the card target.
	 * @param Template $template Card template (used to generate versioning hash).
	 * @param string   $size     Card size (e.g., 'landscape', 'square').
	 *
	 * @return string Absolute filesystem path.
	 */
	public function path( CardKey $key, Template $template, string $size ): string {
		return rtrim( $this->base_dir, '/' ) . '/og-cards/' . $key->segment . '-' . $template->hash() . '-' . $size . '.png';
	}
}
