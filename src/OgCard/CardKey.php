<?php
/**
 * CardKey value object for identifying OG card targets.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\OgCard;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object identifying an OG card target (post, archive, or author).
 *
 * Provides factory methods for each card type and accessors for programmatic
 * access to underlying identifiers without string parsing.
 */
final class CardKey {

	/**
	 * Creates a new CardKey instance.
	 *
	 * @param string      $kind     Card type: 'post', 'archive', or 'author'.
	 * @param string      $segment  Path segment for file naming.
	 * @param int|null    $post_id  Post ID (for post cards).
	 * @param int|null    $term_id  Term ID (for archive cards).
	 * @param string|null $taxonomy Taxonomy name (for archive cards).
	 * @param int|null    $user_id  User ID (for author cards).
	 */
	private function __construct(
		public readonly string $kind,
		public readonly string $segment,
		private readonly ?int $post_id = null,
		private readonly ?int $term_id = null,
		private readonly ?string $taxonomy = null,
		private readonly ?int $user_id = null,
	) {}

	/**
	 * Creates a CardKey for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return self
	 */
	public static function for_post( int $post_id ): self {
		return new self( 'post', "post-{$post_id}", post_id: $post_id );
	}

	/**
	 * Creates a CardKey for an archive (taxonomy term).
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $term_id  Term ID.
	 *
	 * @return self
	 */
	public static function for_archive( string $taxonomy, int $term_id ): self {
		return new self( 'archive', "archive/{$taxonomy}-{$term_id}", term_id: $term_id, taxonomy: $taxonomy );
	}

	/**
	 * Creates a CardKey for an author archive.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return self
	 */
	public static function for_author( int $user_id ): self {
		return new self( 'author', "author/{$user_id}", user_id: $user_id );
	}

	/**
	 * Returns the post ID if this is a post card, otherwise null.
	 *
	 * @return int|null
	 */
	public function post_id(): ?int {
		return $this->post_id;
	}

	/**
	 * Returns the term ID if this is an archive card, otherwise null.
	 *
	 * @return int|null
	 */
	public function term_id(): ?int {
		return $this->term_id;
	}

	/**
	 * Returns the taxonomy name if this is an archive card, otherwise null.
	 *
	 * @return string|null
	 */
	public function taxonomy(): ?string {
		return $this->taxonomy;
	}

	/**
	 * Returns the user ID if this is an author card, otherwise null.
	 *
	 * @return int|null
	 */
	public function user_id(): ?int {
		return $this->user_id;
	}
}
