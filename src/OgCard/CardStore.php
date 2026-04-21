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
	 * @param string $base_dir  Base directory for card storage (usually wp-content/uploads/ogc).
	 * @param string $base_url  Base URL for public access to cards (defaults to empty for backward compat).
	 */
	public function __construct(
		private readonly string $base_dir,
		private readonly string $base_url = ''
	) {}

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

	/**
	 * Checks if a card image file exists on disk.
	 *
	 * @param CardKey  $key      Identifies the card target.
	 * @param Template $template Card template (used to generate versioning hash).
	 * @param string   $size     Card size (e.g., 'landscape', 'square').
	 *
	 * @return bool True if file exists, false otherwise.
	 */
	public function exists( CardKey $key, Template $template, string $size ): bool {
		return file_exists( $this->path( $key, $template, $size ) );
	}

	/**
	 * Returns the public URL for a card image, or null if it doesn't exist.
	 *
	 * @param CardKey  $key      Identifies the card target.
	 * @param Template $template Card template (used to generate versioning hash).
	 * @param string   $size     Card size (e.g., 'landscape', 'square').
	 *
	 * @return string|null Public URL if file exists, null otherwise.
	 */
	public function url( CardKey $key, Template $template, string $size ): ?string {
		if ( ! $this->exists( $key, $template, $size ) ) {
			return null;
		}
		return rtrim( $this->base_url, '/' ) . '/og-cards/' . $key->segment . '-' . $template->hash() . '-' . $size . '.png';
	}

	/**
	 * Writes card bytes to disk atomically.
	 *
	 * Uses .tmp → rename pattern to prevent partial reads if concurrent save
	 * happens or if the process is killed mid-write. Auto-creates directory
	 * on first write.
	 *
	 * @param CardKey  $key      Identifies the card target.
	 * @param Template $template Card template (used to generate versioning hash).
	 * @param string   $size     Card size (e.g., 'landscape', 'square').
	 * @param string   $bytes    PNG image bytes to write.
	 *
	 * @return string Path to the written file.
	 *
	 * @throws \RuntimeException If directory creation or file write fails.
	 */
	public function write( CardKey $key, Template $template, string $size, string $bytes ): string {
		$path = $this->path( $key, $template, $size );
		$dir  = dirname( $path );
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new \RuntimeException( "Failed to create dir: {$dir}" );
		}
		$tmp = $path . '.tmp';
		if ( file_put_contents( $tmp, $bytes ) === false ) {
			throw new \RuntimeException( "Failed to write tmp: {$tmp}" );
		}
		if ( ! rename( $tmp, $path ) ) {
			@unlink( $tmp );
			throw new \RuntimeException( "Failed to rename {$tmp} -> {$path}" );
		}
		return $path;
	}
}
