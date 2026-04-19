<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Resolvers;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Resolvers\Type;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $postmeta
	 * @param array<string, mixed> $options
	 */
	private function resolver( array $postmeta = [], array $options = [] ): Type {
		$post_repo = $this->createStub( PostMetaRepository::class );
		$post_repo->method( 'get' )->willReturn(
			array_merge(
				[
					'title'       => '',
					'description' => '',
					'image_id'    => 0,
					'type'        => '',
					'platforms'   => [],
					'exclude'     => [],
				],
				$postmeta
			)
		);

		$opt_repo = $this->createStub( OptionsRepository::class );
		$opt_repo->method( 'get_path' )->willReturnCallback(
			static fn ( string $path ) => $options[ $path ] ?? null
		);

		return new Type( $post_repo, $opt_repo );
	}

	public function test_post_meta_override_wins(): void {
		Filters\expectApplied( 'ogc_resolve_type_value' )->andReturnFirstArg();
		$r = $this->resolver( [ 'type' => 'product' ] );
		self::assertSame( 'product', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_per_post_type_default(): void {
		Functions\when( 'get_post_type' )->justReturn( 'event' );
		Filters\expectApplied( 'ogc_resolve_type_value' )->andReturnFirstArg();
		$r = $this->resolver( [], [ 'post_types.event.default_type' => 'event' ] );
		self::assertSame( 'event', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_defaults_to_article_for_singular(): void {
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Filters\expectApplied( 'ogc_resolve_type_value' )->andReturnFirstArg();
		$r = $this->resolver();
		self::assertSame( 'article', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_defaults_to_website_for_non_singular(): void {
		Filters\expectApplied( 'ogc_resolve_type_value' )->andReturnFirstArg();
		$r = $this->resolver( [], [ 'site.type' => 'website' ] );
		self::assertSame( 'website', $r->resolve( Context::for_front() ) );
	}

	public function test_final_filter_can_override(): void {
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Filters\expectApplied( 'ogc_resolve_type_value' )->once()->andReturn( 'video.other' );
		$r = $this->resolver();
		self::assertSame( 'video.other', $r->resolve( Context::for_post( 1 ) ) );
	}
}
