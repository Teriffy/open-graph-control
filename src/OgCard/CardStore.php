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
		if ( ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( esc_html( "Failed to create dir: {$dir}" ) );
		}
		$tmp = $path . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- card renderer owns dedicated uploads/og-cards dir; WP_Filesystem init is overkill on every render.
		if ( file_put_contents( $tmp, $bytes ) === false ) {
			throw new \RuntimeException( esc_html( "Failed to write tmp: {$tmp}" ) );
		}
		$filesystem = self::filesystem();
		if ( null === $filesystem ) {
			if ( ! @rename( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPress.PHP.NoSilencedErrors.Discouraged -- WP_Filesystem unavailable fallback; suppress warning on cross-filesystem rename.
				wp_delete_file( $tmp );
				throw new \RuntimeException( esc_html( "Failed to rename {$tmp} -> {$path}" ) );
			}
		} elseif ( ! $filesystem->move( $tmp, $path, true ) ) {
			wp_delete_file( $tmp );
			throw new \RuntimeException( esc_html( "Failed to rename {$tmp} -> {$path}" ) );
		}
		return $path;
	}

	/**
	 * Finds published post IDs without cached cards for the given template.
	 *
	 * Overfetches 4× batch_size from get_posts() to account for posts
	 * that already have cards. Stops early once enough missing cards found.
	 *
	 * @param Template $template Template to check for missing cards.
	 * @param int      $batch_size Maximum posts to return.
	 *
	 * @return list<int> Post IDs lacking cards for this template, up to batch_size.
	 */
	public function missing_post_ids( Template $template, int $batch_size ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return [];
		}
		$ids     = get_posts(
			[
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => $batch_size * 4,
				'fields'         => 'ids',
			]
		);
		$missing = [];
		foreach ( (array) $ids as $id ) {
			if ( ! $this->exists( CardKey::for_post( (int) $id ), $template, 'landscape' ) ) {
				$missing[] = (int) $id;
				if ( count( $missing ) >= $batch_size ) {
					break;
				}
			}
		}
		return $missing;
	}

	/**
	 * Deletes all template-version variants of a card key.
	 *
	 * Removes all cached PNG files for a given CardKey, regardless of template
	 * version or size. Used when template config changes to remove orphaned
	 * versions of cards.
	 *
	 * @param CardKey $key Card key to delete all versions for.
	 *
	 * @return void
	 */
	public function delete_for_key( CardKey $key ): void {
		$glob  = rtrim( $this->base_dir, '/' ) . '/og-cards/' . $key->segment . '-*-landscape.png';
		$files = glob( $glob );
		if ( false === $files ) {
			return;
		}
		foreach ( $files as $file ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Purges the entire og-cards directory.
	 *
	 * Completely removes all cached card files. Used by "Regenerate all" admin
	 * action and other bulk operations. Silently succeeds if directory doesn't exist.
	 *
	 * @return void
	 */
	public function purge_all(): void {
		$dir = rtrim( $this->base_dir, '/' ) . '/og-cards';
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$filesystem = self::filesystem();
		$iter       = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $file ) {
			if ( $file->isDir() ) {
				if ( null !== $filesystem ) {
					$filesystem->rmdir( $file->getPathname() );
				} else {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged -- WP_Filesystem unavailable fallback; rmdir is harmless on missing/non-empty dirs.
					@rmdir( $file->getPathname() );
				}
				continue;
			}
			wp_delete_file( $file->getPathname() );
		}
	}

	/**
	 * Returns an initialised WP_Filesystem instance in direct mode, or null if unavailable.
	 *
	 * Cached across calls on the same request so repeated store operations don't re-initialise.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private static function filesystem(): ?\WP_Filesystem_Base {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			if ( ! defined( 'ABSPATH' ) ) {
				return null;
			}
			$file_admin = ABSPATH . 'wp-admin/includes/file.php';
			if ( ! is_file( $file_admin ) ) {
				return null;
			}
			require_once $file_admin;
		}
		if ( ! WP_Filesystem() ) {
			return null;
		}
		global $wp_filesystem;
		return $wp_filesystem instanceof \WP_Filesystem_Base ? $wp_filesystem : null;
	}
}
