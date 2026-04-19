<?php
/**
 * WP-CLI commands.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Cli;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Images\Regenerator;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\Registry as PlatformRegistry;
use EvzenLeonenko\OpenGraphControl\Renderer\TagBuilder;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Validation\Validator;
use EvzenLeonenko\OpenGraphControl\Validation\Warning;

/**
 * Open Graph Control WP-CLI commands.
 *
 * Registered under the `ogc` namespace:
 *
 *     wp ogc tags <post_id>
 *     wp ogc validate <post_id>
 *     wp ogc regenerate
 */
final class Commands {

	public function __construct(
		private PlatformRegistry $registry,
		private TagBuilder $builder,
		private OptionsRepository $options,
		private Validator $validator,
		private Regenerator $regenerator
	) {}

	/**
	 * Print the rendered OG tags for a given post (or front page if 0).
	 *
	 * ## OPTIONS
	 *
	 * [<post_id>]
	 * : Post ID. 0 or omitted = front page.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ogc tags 42
	 *     wp ogc tags
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args
	 * @param array<string, mixed> $assoc Ignored.
	 */
	public function tags( array $args, array $assoc ): void {
		unset( $assoc );
		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		$context = $post_id > 0 ? Context::for_post( $post_id ) : Context::for_front();
		$html    = $this->builder->render( $this->registry->collect_tags( $context ) );

		if ( '' === trim( $html ) ) {
			\WP_CLI::warning( 'No tags emitted for this context.' );
			return;
		}
		\WP_CLI::line( $html );
	}

	/**
	 * Run the validator against a post's resolved tag stream.
	 *
	 * ## OPTIONS
	 *
	 * [<post_id>]
	 * : Post ID. 0 or omitted = front page.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ogc validate 42
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args
	 * @param array<string, mixed> $assoc Ignored.
	 */
	public function validate( array $args, array $assoc ): void {
		unset( $assoc );
		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		$context = $post_id > 0 ? Context::for_post( $post_id ) : Context::for_front();

		$tags = [];
		foreach ( $this->registry->collect_tags( $context ) as $tag ) {
			$tags[ $tag->kind . ':' . $tag->key ] = $tag->content;
		}

		$warnings = $this->validator->validate( $tags, $this->options->get() );
		if ( [] === $warnings ) {
			\WP_CLI::success( 'No warnings.' );
			return;
		}
		foreach ( $warnings as $w ) {
			$line = sprintf( '[%s] %s: %s', $w->severity, $w->field, $w->message );
			if ( Warning::ERROR === $w->severity ) {
				\WP_CLI::warning( $line );
			} else {
				\WP_CLI::line( $line );
			}
		}
	}

	/**
	 * Regenerate OG size variants for all attachments. Runs in-process (no
	 * WP-Cron), walks the entire library.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ogc regenerate
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args  Ignored.
	 * @param array<string, mixed> $assoc Ignored.
	 */
	public function regenerate( array $args, array $assoc ): void {
		unset( $args, $assoc );
		$this->regenerator->start();
		do {
			$this->regenerator->run_batch();
			$status = $this->regenerator->status();
			\WP_CLI::line( sprintf( 'Processed %d…', $status['processed'] ) );
		} while ( 'running' === $status['status'] );

		\WP_CLI::success( sprintf( 'Done — %d attachments.', $status['processed'] ) );
	}
}
