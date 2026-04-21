<?php
/**
 * BackfillCron for daily OG card backfill across all posts.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Scheduler for backfilling missing OG cards via daily WP-Cron tick.
 *
 * Runs once daily to find and render cards for posts missing them.
 * Processes in batches of 5 to avoid timeout on sites with many posts.
 * Uses transient-based locking to prevent overlapping executions.
 */
final class BackfillCron {

	/**
	 * WordPress hook name for cron event.
	 *
	 * @var string
	 */
	public const HOOK = 'ogc_og_card_backfill';

	/**
	 * Number of posts to process per tick.
	 *
	 * @var int
	 */
	public const BATCH_SIZE = 5;

	/**
	 * Transient key for execution lock.
	 *
	 * @var string
	 */
	private const LOCK_KEY = 'ogc_card_backfill_lock';

	/**
	 * Callable to provide the current template configuration.
	 *
	 * @var callable(): Template
	 */
	private $template_provider;

	/**
	 * Creates a new BackfillCron instance.
	 *
	 * @param CardGenerator        $generator         Card generator for rendering.
	 * @param CardStore            $store             Store for querying missing posts.
	 * @param callable(): Template $template_provider Callable returning current Template.
	 */
	public function __construct(
		private readonly CardGenerator $generator,
		private readonly CardStore $store,
		$template_provider,
	) {
		$this->template_provider = $template_provider;
	}

	/**
	 * Registers the daily cron event if not already scheduled.
	 *
	 * Schedules the `ogc_og_card_backfill` hook to run daily. Delays first
	 * execution by 5 minutes to avoid blocking plugin activation.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'daily', self::HOOK );
		}
	}

	/**
	 * Processes a batch of missing post cards.
	 *
	 * Fetches up to BATCH_SIZE posts without cards, renders them via generator.ensure(),
	 * and handles transient-based locking to prevent concurrent executions.
	 * If locked, returns early. Otherwise, acquires lock, processes batch, releases lock.
	 *
	 * @return void
	 */
	public function tick(): void {
		if ( get_transient( self::LOCK_KEY ) ) {
			return;
		}

		set_transient( self::LOCK_KEY, true, 60 );

		try {
			$template = ( $this->template_provider )();
			$missing  = $this->store->missing_post_ids( $template, self::BATCH_SIZE );

			foreach ( $missing as $post_id ) {
				$this->generator->ensure( CardKey::for_post( $post_id ) );
			}
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}
}
