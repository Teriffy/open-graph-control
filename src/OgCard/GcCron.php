<?php
/**
 * Garbage collection cron for stale template-version card files.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Garbage collection cron handler for orphaned OG card image files.
 *
 * When users change the OG card template configuration, the hash of the new template
 * differs from the old one. Old card files become orphaned and are no longer referenced
 * by ResolverHook. GcCron daily scans the cards directory for files whose embedded hash
 * no longer matches the current template, and deletes them after a 7-day grace period
 * (to allow caching services like Facebook/Twitter to refresh their cache copies).
 */
final class GcCron {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	public const HOOK = 'ogc_og_card_gc';

	/**
	 * Grace period in days before deletion of stale files.
	 *
	 * @var int
	 */
	private const GRACE_DAYS = 7;

	/**
	 * Creates a new GcCron instance.
	 *
	 * @param string    $cards_dir          Base directory where card images are stored.
	 * @param callable(): Template $template_provider Callable that returns current template.
	 */
	public function __construct(
		private readonly string $cards_dir,
		private readonly mixed $template_provider,
	) {}

	/**
	 * Registers the daily garbage collection cron event.
	 *
	 * Schedules the ogc_og_card_gc hook to run daily if not already scheduled.
	 * Uses wp_schedule_event to hook into WordPress's cron system.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 600, 'daily', self::HOOK );
		}
	}

	/**
	 * Garbage collection tick: deletes stale card files.
	 *
	 * Scans the cards directory for PNG files with template hashes. If the hash
	 * no longer matches the current template and the file is older than the grace period,
	 * deletes it. Non-PNG files and files with invalid hash patterns are ignored.
	 *
	 * @return void
	 */
	public function tick(): void {
		if ( ! is_dir( $this->cards_dir ) ) {
			return;
		}

		$current_hash = ( $this->template_provider )()->hash();
		$cutoff       = time() - ( self::GRACE_DAYS * DAY_IN_SECONDS );

		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->cards_dir, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iter as $file ) {
			/** @var \SplFileInfo $file */

			// Only process regular PNG files.
			if ( ! $file->isFile() || ! str_ends_with( $file->getFilename(), '.png' ) ) {
				continue;
			}

			// Extract hash from filename pattern: {segment}-{hash}-landscape.png
			// Hashes are 8 hex digits.
			if ( ! preg_match( '/-([0-9a-f]{8})-landscape\.png$/', $file->getFilename(), $m ) ) {
				continue;
			}

			// Keep files with the current template hash.
			if ( $m[1] === $current_hash ) {
				continue;
			}

			// Delete files past grace period.
			if ( $file->getMTime() < $cutoff ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Direct filesystem cleanup, not WP uploads.
				@unlink( $file->getPathname() );
			}
		}//end foreach
	}
}
