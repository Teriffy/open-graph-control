<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Resolvers;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository as ArchiveMetaRepository;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Resolvers\Title;
use PHPUnit\Framework\TestCase;

final class TitleTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed>  $postmeta
	 * @param array<int, string>    $chain
	 */
	private function resolver( array $postmeta = [], ?array $chain = null, ?ArchiveMetaRepository $archive = null ): Title {
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
		$opt_repo->method( 'get_path' )->willReturn( $chain ?? [ 'post_meta_override', 'archive_override', 'seo_plugin_title', 'post_title', 'site_name' ] );

		return new Title( $post_repo, $opt_repo, $archive ?? $this->createStub( ArchiveMetaRepository::class ) );
	}

	public function test_returns_post_meta_override_first(): void {
		Functions\when( 'get_the_title' )->justReturn( 'Fallback' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_title_value' )->andReturnFirstArg();

		$t = $this->resolver( [ 'title' => 'Custom Title' ] );
		self::assertSame( 'Custom Title', $t->resolve( Context::for_post( 1 ) ) );
	}

	public function test_falls_through_to_post_title(): void {
		Functions\when( 'get_the_title' )->justReturn( 'Post Title' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_title_value' )->andReturnFirstArg();

		$t = $this->resolver();
		self::assertSame( 'Post Title', $t->resolve( Context::for_post( 1 ) ) );
	}

	public function test_front_context_falls_through_to_site_name(): void {
		Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_title_value' )->andReturnFirstArg();

		$t = $this->resolver();
		self::assertSame( 'My Site', $t->resolve( Context::for_front() ) );
	}

	public function test_seo_plugin_step_uses_filter(): void {
		Functions\when( 'get_the_title' )->justReturn( 'Post' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_seo_plugin_title' )->once()->andReturn( 'From SEO Plugin' );
		Filters\expectApplied( 'ogc_resolve_title_value' )->andReturnFirstArg();

		$t = $this->resolver();
		self::assertSame( 'From SEO Plugin', $t->resolve( Context::for_post( 1 ) ) );
	}

	public function test_chain_can_be_filtered(): void {
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Filters\expectApplied( 'ogc_resolve_title_chain' )->once()->andReturn( [ 'site_name' ] );
		Filters\expectApplied( 'ogc_resolve_title_value' )->andReturnFirstArg();

		$t = $this->resolver();
		self::assertSame( 'Site', $t->resolve( Context::for_post( 1 ) ) );
	}

	public function test_applies_final_value_filter(): void {
		Functions\when( 'get_the_title' )->justReturn( 'Raw' );
		Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_title_value' )->once()->with( 'Raw', \Mockery::type( Context::class ) )->andReturn( 'Filtered' );

		$t = $this->resolver();
		self::assertSame( 'Filtered', $t->resolve( Context::for_post( 1 ) ) );
	}

	public function test_returns_null_when_chain_exhausts(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();

		$t = $this->resolver( [], [ 'site_name' ] );
		self::assertNull( $t->resolve( Context::for_front() ) );
	}

	public function test_archive_override_returns_meta_for_archive_term(): void {
		Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_title_value' )->andReturnFirstArg();

		$archive = $this->createStub( ArchiveMetaRepository::class );
		$archive->method( 'get_for_term' )->willReturn(
			[
				'title'       => 'Recepty z české spíže',
				'description' => '',
				'image_id'    => 0,
				'exclude'     => [],
			]
		);

		$t = $this->resolver( [], [ 'archive_override' ], $archive );
		self::assertSame(
			'Recepty z české spíže',
			$t->resolve( Context::for_archive_term( 'category', 12 ) )
		);
	}

	public function test_archive_override_returns_meta_for_author(): void {
		Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_title_value' )->andReturnFirstArg();

		$archive = $this->createStub( ArchiveMetaRepository::class );
		$archive->method( 'get_for_user' )->willReturn(
			[
				'title'       => 'Evžen Leonenko',
				'description' => '',
				'image_id'    => 0,
				'exclude'     => [],
			]
		);

		$t = $this->resolver( [], [ 'archive_override' ], $archive );
		self::assertSame( 'Evžen Leonenko', $t->resolve( Context::for_author( 3 ) ) );
	}

	public function test_archive_override_null_on_post_context(): void {
		Filters\expectApplied( 'ogc_resolve_title_chain' )->andReturnFirstArg();

		$archive = $this->createStub( ArchiveMetaRepository::class );

		$t = $this->resolver( [], [ 'archive_override' ], $archive );
		self::assertNull( $t->resolve( Context::for_post( 1 ) ) );
	}
}
