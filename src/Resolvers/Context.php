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

	public function post_id(): ?int {
		return $this->post_id;
	}

	public function extra( string $key, mixed $fallback = null ): mixed {
		return $this->extra[ $key ] ?? $fallback;
	}
}
