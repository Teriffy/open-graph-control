<?php
/**
 * Output cache for rendered wp_head payloads.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Renderer;

defined( 'ABSPATH' ) || exit;

use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;

/**
 * Caches the finished HTML for a given context as a transient, keyed by a
 * hash of the context signature. Opt-in — only active when
 * `output.cache_ttl > 0`.
 *
 * Invalidation hooks:
 *  - save_post / delete_post / updated_post_meta → flush for that post
 *  - update_option('ogc_settings') → flush everything
 *  - switch_theme / update_option('blogname'|'blogdescription') → flush all
 */
class Cache {

	public const GLOBAL_SALT_OPTION = 'ogc_cache_salt';

	public function __construct( private OptionsRepository $options ) {}

	public function register(): void {
		add_action( 'save_post', [ $this, 'flush_post' ], 10, 1 );
		add_action( 'delete_post', [ $this, 'flush_post' ], 10, 1 );
		add_action( 'updated_post_meta', [ $this, 'flush_post_from_meta' ], 10, 3 );
		add_action( 'update_option_ogc_settings', [ $this, 'flush_all' ] );
		add_action( 'switch_theme', [ $this, 'flush_all' ] );
		add_action( 'update_option_blogname', [ $this, 'flush_all' ] );
		add_action( 'update_option_blogdescription', [ $this, 'flush_all' ] );
	}

	public function ttl(): int {
		$ttl = (int) $this->options->get_path( 'output.cache_ttl' );
		return $ttl > 0 ? $ttl : 0;
	}

	public function is_enabled(): bool {
		return $this->ttl() > 0;
	}

	public function key_for( Context $context ): string {
		$parts = [
			$context->type(),
			(string) ( $context->post_id() ?? '' ),
			(string) $this->salt(),
		];
		return 'ogc_cache_' . md5( implode( ':', $parts ) );
	}

	public function get( Context $context ): ?string {
		if ( ! $this->is_enabled() ) {
			return null;
		}
		$hit = get_transient( $this->key_for( $context ) );
		return is_string( $hit ) ? $hit : null;
	}

	public function set( Context $context, string $html ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}
		set_transient( $this->key_for( $context ), $html, $this->ttl() );
	}

	public function flush_post( int $post_id ): void {
		$context = Context::for_post( $post_id );
		delete_transient( $this->key_for( $context ) );
	}

	/**
	 * @param int    $meta_id  Ignored.
	 * @param int    $post_id  Post ID whose meta changed.
	 * @param string $meta_key Meta key.
	 */
	public function flush_post_from_meta( int $meta_id, int $post_id, string $meta_key ): void {
		unset( $meta_id );
		if ( '_ogc_meta' !== $meta_key ) {
			return;
		}
		$this->flush_post( $post_id );
	}

	public function flush_all(): void {
		// Rotate the global salt — every existing key becomes unreachable and
		// expires naturally. Avoids scanning wp_options for transient rows.
		update_option( self::GLOBAL_SALT_OPTION, (string) time(), false );
	}

	private function salt(): string {
		$stored = get_option( self::GLOBAL_SALT_OPTION, '' );
		return is_string( $stored ) ? $stored : '';
	}
}
