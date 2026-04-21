<?php
/**
 * Open Graph Card WP-CLI subcommands.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Cli;

use EvzenLeonenko\OpenGraphControl\OgCard\BackfillCron;
use EvzenLeonenko\OpenGraphControl\OgCard\CardGenerator;
use EvzenLeonenko\OpenGraphControl\OgCard\CardKey;
use EvzenLeonenko\OpenGraphControl\OgCard\CardStore;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands for OG card generation.
 *
 * Registered under the `ogc cards` namespace:
 *
 *     wp ogc cards generate --post-id=<id>
 *     wp ogc cards regenerate [--post-type=<type>] [--all] [--dry-run]
 *     wp ogc cards status
 *     wp ogc cards purge
 */
final class CardsCommand {

	public function __construct(
		private readonly CardGenerator $generator,
		private readonly CardStore $store,
	) {}

	/**
	 * Generate a card for a single post.
	 *
	 * ## OPTIONS
	 *
	 * --post-id=<id>
	 * : The post ID to generate.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ogc cards generate --post-id=42
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args  Ignored.
	 * @param array<string, string> $assoc Associative arguments.
	 */
	public function generate( array $args, array $assoc ): void {
		unset( $args );
		$id = (int) ( $assoc['post-id'] ?? 0 );
		if ( $id <= 0 ) {
			\WP_CLI::error( 'Missing or invalid --post-id' );
		}
		$path = $this->generator->ensure( CardKey::for_post( $id ) );
		if ( null === $path ) {
			\WP_CLI::error( 'Render failed (see error log)' );
		}
		\WP_CLI::success( "Generated: {$path}" );
	}

	/**
	 * Regenerate cards for published posts.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<type>]
	 * : Restrict to a specific post type. Default: all public types.
	 *
	 * [--all]
	 * : Force regenerate even if card already exists.
	 *
	 * [--dry-run]
	 * : Log what would be regenerated without rendering.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ogc cards regenerate
	 *     wp ogc cards regenerate --post-type=post --all
	 *     wp ogc cards regenerate --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args  Ignored.
	 * @param array<string, string> $assoc Associative arguments.
	 */
	public function regenerate( array $args, array $assoc ): void {
		unset( $args );
		$dry      = isset( $assoc['dry-run'] );
		$type     = (string) ( $assoc['post-type'] ?? 'any' );
		$force    = isset( $assoc['all'] );
		$template = Template::from_array( (array) get_option( 'ogc_card_template', [] ) );

		$ids = get_posts(
			[
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$count = 0;
		foreach ( (array) $ids as $id ) {
			$key = CardKey::for_post( (int) $id );
			if ( ! $force && $this->store->exists( $key, $template, 'landscape' ) ) {
				continue;
			}
			if ( $dry ) {
				\WP_CLI::line( "Would regenerate post {$id}" );
			} else {
				$this->generator->ensure( $key );
			}
			++$count;
		}
		\WP_CLI::success( "{$count} card(s) " . ( $dry ? 'identified' : 'regenerated' ) );
	}

	/**
	 * Show generated / missing / total counts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ogc cards status
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args  Ignored.
	 * @param array<string, string> $assoc Ignored.
	 */
	public function status( array $args, array $assoc ): void {
		unset( $args, $assoc );
		$template  = Template::from_array( (array) get_option( 'ogc_card_template', [] ) );
		$counts    = wp_count_posts();
		$total     = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$missing   = count( $this->store->missing_post_ids( $template, 99999 ) );
		$generated = max( 0, $total - $missing );
		\WP_CLI::line( sprintf( 'Generated: %d / Missing: %d / Total: %d', $generated, $missing, $total ) );
	}

	/**
	 * Delete all generated cards and reset the backfill cron.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ogc cards purge
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args  Ignored.
	 * @param array<string, string> $assoc Ignored.
	 */
	public function purge( array $args, array $assoc ): void {
		unset( $args, $assoc );
		$this->store->purge_all();
		wp_schedule_single_event( time() + 1, BackfillCron::HOOK );
		\WP_CLI::success( 'All cards purged; backfill rescheduled' );
	}
}
