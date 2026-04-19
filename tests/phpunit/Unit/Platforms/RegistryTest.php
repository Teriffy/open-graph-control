<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Platforms;

use EvzenLeonenko\OpenGraphControl\Platforms\PlatformInterface;
use EvzenLeonenko\OpenGraphControl\Platforms\Registry;
use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase {

	/**
	 * @param Tag[] $tags
	 */
	private function platform( string $slug, bool $enabled, array $tags = [], ?string $json_ld = null ): PlatformInterface {
		return new class($slug, $enabled, $tags, $json_ld) implements PlatformInterface {

			/**
			 * @param Tag[] $tags
			 */
			public function __construct(
				private string $slug,
				private bool $enabled,
				private array $tags,
				private ?string $json_ld
			) {}

			public function slug(): string {
				return $this->slug;
			}

			public function is_enabled(): bool {
				return $this->enabled;
			}

			public function tags( Context $context ): array {
				return $this->tags;
			}

			public function json_ld( Context $context ): ?string {
				return $this->json_ld;
			}
		};
	}

	public function test_register_and_all(): void {
		$registry = new Registry();
		$registry->register( $this->platform( 'a', true ) );
		$registry->register( $this->platform( 'b', true ) );
		self::assertCount( 2, $registry->all() );
	}

	public function test_duplicate_slug_is_last_one_wins(): void {
		$registry = new Registry();
		$registry->register( $this->platform( 'a', true ) );
		$registry->register( $this->platform( 'a', false ) );
		self::assertCount( 1, $registry->all() );
		self::assertFalse( $registry->all()[0]->is_enabled() );
	}

	public function test_enabled_filters_out_disabled(): void {
		$registry = new Registry();
		$registry->register( $this->platform( 'a', true ) );
		$registry->register( $this->platform( 'b', false ) );
		$enabled = $registry->enabled();
		self::assertCount( 1, $enabled );
		self::assertSame( 'a', $enabled[0]->slug() );
	}

	public function test_collect_tags_concatenates_from_enabled_only(): void {
		$registry = new Registry();
		$registry->register( $this->platform( 'a', true, [ new Tag( Tag::KIND_PROPERTY, 'og:a', '1' ) ] ) );
		$registry->register( $this->platform( 'b', false, [ new Tag( Tag::KIND_PROPERTY, 'og:b', '2' ) ] ) );
		$registry->register( $this->platform( 'c', true, [ new Tag( Tag::KIND_NAME, 'twitter:card', 'summary' ) ] ) );

		$tags = $registry->collect_tags( Context::for_front() );
		self::assertCount( 2, $tags );
		self::assertSame( 'og:a', $tags[0]->key );
		self::assertSame( 'twitter:card', $tags[1]->key );
	}

	public function test_collect_tags_deduplicates_by_kind_and_key(): void {
		$registry = new Registry();
		$registry->register(
			$this->platform( 'a', true, [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'First' ) ] )
		);
		$registry->register(
			$this->platform( 'b', true, [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'Second' ) ] )
		);
		$tags = $registry->collect_tags( Context::for_front() );
		self::assertCount( 1, $tags );
		self::assertSame( 'First', $tags[0]->content );
	}

	public function test_collect_tags_preserves_both_property_and_name_kinds(): void {
		$registry = new Registry();
		$registry->register(
			$this->platform( 'a', true, [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'P' ) ] )
		);
		$registry->register(
			$this->platform( 'b', true, [ new Tag( Tag::KIND_NAME, 'og:title', 'N' ) ] )
		);
		$tags = $registry->collect_tags( Context::for_front() );
		self::assertCount( 2, $tags );
	}

	public function test_collect_json_ld_concatenates_from_enabled_only(): void {
		$registry = new Registry();
		$registry->register( $this->platform( 'a', true, [], '{"@type":"Article"}' ) );
		$registry->register( $this->platform( 'b', false, [], '{"@type":"Other"}' ) );
		$registry->register( $this->platform( 'c', true, [], null ) );

		$payloads = $registry->collect_json_ld( Context::for_post( 1 ) );
		self::assertSame( [ '{"@type":"Article"}' ], $payloads );
	}
}
