<?php
/**
 * PayloadBuilder factory for OG cards.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

use EvzenLeonenko\OpenGraphControl\Resolvers\{Description, Title, Context};

defined( 'ABSPATH' ) || exit;

/**
 * Factory for creating Payload instances using dependency-injected resolvers.
 *
 * PayloadBuilder integrates Title and Description resolvers (which are
 * dependency-injected via Bootstrap) and exposes factory methods for
 * different context types (post, archive term, author). Each method
 * creates a Context, invokes the resolvers, and returns a populated Payload.
 */
final class PayloadBuilder {

	/**
	 * Constructs a PayloadBuilder with resolver dependencies.
	 *
	 * @param Title       $title       Title resolver instance.
	 * @param Description $description Description resolver instance.
	 */
	public function __construct(
		private readonly Title $title,
		private readonly Description $description,
	) {}

	/**
	 * Creates a Payload for a post.
	 *
	 * Resolves title and description using the configured resolvers.
	 * Falls back to post_title if title resolver returns null.
	 * Includes publish date and author name in meta_line.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return Payload The constructed payload.
	 *
	 * @throws \InvalidArgumentException If post is not found.
	 */
	public function for_post( int $post_id ): Payload {
		$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( ! $post ) {
			throw new \InvalidArgumentException( esc_html( "Post not found: {$post_id}" ) );
		}

		$context     = Context::for_post( $post_id );
		$title       = (string) ( $this->title->resolve( $context ) ?? $post->post_title );
		$description = (string) ( $this->description->resolve( $context ) ?? '' );
		$site_name   = (string) get_bloginfo( 'name' );
		$url         = (string) get_permalink( $post_id );
		$author      = get_userdata( (int) $post->post_author );
		$author_name = $author && isset( $author->display_name ) ? (string) $author->display_name : '';
		$date        = wp_date( 'F Y', strtotime( $post->post_date ) ? strtotime( $post->post_date ) : 0 );
		$parsed_url  = wp_parse_url( $url );
		$host        = is_array( $parsed_url ) && isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$meta_line   = trim(
			implode(
				' · ',
				array_filter(
					[
						$host,
						$date,
						$author_name,
					]
				)
			)
		);

		return new Payload( $title, $description, $site_name, $url, $meta_line );
	}

	/**
	 * Creates a Payload for an archive term (category, tag, etc.).
	 *
	 * Resolves title and description using the configured resolvers.
	 * Falls back to term name if title resolver returns null.
	 * Uses empty meta_line for archive terms.
	 *
	 * @param string $taxonomy Taxonomy name (e.g., 'category', 'post_tag').
	 * @param int    $term_id  Term ID.
	 *
	 * @return Payload The constructed payload.
	 */
	public function for_archive_term( string $taxonomy, int $term_id ): Payload {
		$context        = Context::for_archive_term( $taxonomy, $term_id );
		$title_resolved = $this->title->resolve( $context );
		$term           = function_exists( 'get_term' ) ? get_term( $term_id ) : null;
		$title          = (string) ( $title_resolved ?? ( $term->name ?? 'Archive' ) );
		$description    = (string) ( $this->description->resolve( $context ) ?? '' );
		$url            = '';
		if ( function_exists( 'get_term_link' ) ) {
			$link = get_term_link( $term_id, $taxonomy );
			$url  = is_string( $link ) ? $link : '';
		}

		return new Payload( $title, $description, (string) get_bloginfo( 'name' ), $url, '' );
	}

	/**
	 * Creates a Payload for an author archive.
	 *
	 * Resolves title and description using the configured resolvers.
	 * Falls back to user display_name if title resolver returns null.
	 * Uses empty meta_line for author archives.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return Payload The constructed payload.
	 */
	public function for_author( int $user_id ): Payload {
		$context     = Context::for_author( $user_id );
		$user        = function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : null;
		$title       = (string) ( $this->title->resolve( $context ) ?? ( $user->display_name ?? 'Author' ) );
		$description = (string) ( $this->description->resolve( $context ) ?? '' );
		$url         = function_exists( 'get_author_posts_url' ) ? (string) get_author_posts_url( $user_id ) : '';

		return new Payload( $title, $description, (string) get_bloginfo( 'name' ), $url, '' );
	}
}
