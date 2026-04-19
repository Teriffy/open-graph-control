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
 *  - added/updated/deleted_term_meta / deleted_term → flush archive-term context
 *  - added/updated/deleted_user_meta / deleted_user → flush author context
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
		add_action( 'added_term_meta', [ $this, 'flush_term_from_meta' ], 10, 4 );
		add_action( 'updated_term_meta', [ $this, 'flush_term_from_meta' ], 10, 4 );
		add_action( 'deleted_term_meta', [ $this, 'flush_term_from_meta' ], 10, 4 );
		add_action( 'deleted_term', [ $this, 'flush_term_on_delete' ], 10, 3 );
		add_action( 'added_user_meta', [ $this, 'flush_user_from_meta' ], 10, 4 );
		add_action( 'updated_user_meta', [ $this, 'flush_user_from_meta' ], 10, 4 );
		add_action( 'deleted_user_meta', [ $this, 'flush_user_from_meta' ], 10, 4 );
		add_action( 'deleted_user', [ $this, 'flush_user_on_delete' ], 10, 1 );
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
			(string) ( $context->archive_kind() ?? '' ),
			(string) ( $context->archive_term_id() ?? '' ),
			(string) ( $context->user_id() ?? '' ),
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

	/**
	 * Flush the archive-term cache when `_ogc_meta` is written for a term.
	 *
	 * @param int    $meta_id    Ignored.
	 * @param int    $term_id    Term ID whose meta changed.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Ignored.
	 */
	public function flush_term_from_meta( int $meta_id, int $term_id, string $meta_key, mixed $meta_value ): void {
		unset( $meta_id, $meta_value );
		if ( '_ogc_meta' !== $meta_key ) {
			return;
		}
		$this->flush_term( $term_id );
	}

	/**
	 * Flush the archive-term cache on term deletion.
	 *
	 * @param int    $term_id  Deleted term ID.
	 * @param int    $tt_id    Ignored.
	 * @param string $taxonomy Ignored (looked up from term object).
	 */
	public function flush_term_on_delete( int $term_id, int $tt_id, string $taxonomy ): void {
		unset( $tt_id, $taxonomy );
		$this->flush_term( $term_id );
	}

	/**
	 * Flush the author cache when `_ogc_meta` is written for a user.
	 *
	 * @param int    $meta_id    Ignored.
	 * @param int    $user_id    User ID whose meta changed.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Ignored.
	 */
	public function flush_user_from_meta( int $meta_id, int $user_id, string $meta_key, mixed $meta_value ): void {
		unset( $meta_id, $meta_value );
		if ( '_ogc_meta' !== $meta_key ) {
			return;
		}
		$this->flush_user( $user_id );
	}

	/**
	 * Flush the author cache on user deletion.
	 *
	 * @param int $user_id Deleted user ID.
	 */
	public function flush_user_on_delete( int $user_id ): void {
		$this->flush_user( $user_id );
	}

	public function flush_all(): void {
		// Rotate the global salt — every existing key becomes unreachable and
		// expires naturally. Avoids scanning wp_options for transient rows.
		update_option( self::GLOBAL_SALT_OPTION, (string) time(), false );
	}

	private function flush_term( int $term_id ): void {
		$term = get_term( $term_id );
		if ( ! is_object( $term ) || ! isset( $term->taxonomy, $term->term_id ) ) {
			return;
		}
		$context = Context::for_archive_term( (string) $term->taxonomy, $term_id );
		delete_transient( $this->key_for( $context ) );
	}

	private function flush_user( int $user_id ): void {
		$context = Context::for_author( $user_id );
		delete_transient( $this->key_for( $context ) );
	}

	private function salt(): string {
		$stored = get_option( self::GLOBAL_SALT_OPTION, '' );
		return is_string( $stored ) ? $stored : '';
	}
}
