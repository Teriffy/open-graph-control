<?php
/**
 * Rendering context.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);


namespace EvzenLeonenko\OpenGraphControl\Resolvers;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable value object describing the current request for tag rendering.
 *
 * Resolvers branch on context type; platforms inspect it to decide which
 * tags are appropriate (e.g. article:* only on singular posts).
 */
final class Context {

	public const TYPE_SINGULAR = 'singular';
	public const TYPE_FRONT    = 'front';
	public const TYPE_BLOG     = 'blog';
	public const TYPE_ARCHIVE  = 'archive';
	public const TYPE_AUTHOR   = 'author';
	public const TYPE_DATE     = 'date';
	public const TYPE_SEARCH   = 'search';
	public const TYPE_404      = 'not_found';

	/**
	 * @param array<string, mixed> $extra
	 */
	private function __construct(
		private string $type,
		private ?int $post_id = null,
		private array $extra = []
	) {}

	public static function for_post( int $post_id ): self {
		return new self( self::TYPE_SINGULAR, $post_id );
	}

	public static function for_front(): self {
		return new self( self::TYPE_FRONT );
	}

	public static function for_blog(): self {
		return new self( self::TYPE_BLOG );
	}

	public static function for_archive( string $archive_kind ): self {
		return new self( self::TYPE_ARCHIVE, null, [ 'archive_kind' => $archive_kind ] );
	}

	public static function for_archive_term( string $taxonomy, int $term_id ): self {
		return new self(
			self::TYPE_ARCHIVE,
			null,
			[
				'archive_kind'    => $taxonomy,
				'archive_term_id' => $term_id,
			]
		);
	}

	public static function for_author( int $user_id ): self {
		return new self( self::TYPE_AUTHOR, null, [ 'user_id' => $user_id ] );
	}

	public static function for_date(): self {
		return new self( self::TYPE_DATE );
	}

	public static function for_search(): self {
		return new self( self::TYPE_SEARCH );
	}

	public static function for_404(): self {
		return new self( self::TYPE_404 );
	}

	public function type(): string {
		return $this->type;
	}

	public function is_singular(): bool {
		return self::TYPE_SINGULAR === $this->type;
	}

	public function is_archive(): bool {
		return self::TYPE_ARCHIVE === $this->type;
	}

	public function is_archive_term(): bool {
		return self::TYPE_ARCHIVE === $this->type
			&& is_int( $this->extra['archive_term_id'] ?? null );
	}

	public function is_author(): bool {
		return self::TYPE_AUTHOR === $this->type;
	}

	public function post_id(): ?int {
		return $this->post_id;
	}

	public function archive_kind(): ?string {
		$kind = $this->extra['archive_kind'] ?? null;

		return is_string( $kind ) ? $kind : null;
	}

	public function archive_term_id(): ?int {
		$term_id = $this->extra['archive_term_id'] ?? null;

		return is_int( $term_id ) ? $term_id : null;
	}

	public function user_id(): ?int {
		$user_id = $this->extra['user_id'] ?? null;

		return is_int( $user_id ) ? $user_id : null;
	}

	public function archive_taxonomy(): ?string {
		return is_string( $this->extra['archive_kind'] ?? null )
			? $this->extra['archive_kind']
			: null;
	}

	public function extra( string $key, mixed $fallback = null ): mixed {
		return $this->extra[ $key ] ?? $fallback;
	}
}
