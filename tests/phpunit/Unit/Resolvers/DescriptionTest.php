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
use EvzenLeonenko\OpenGraphControl\Resolvers\Description;
use PHPUnit\Framework\TestCase;

final class DescriptionTest extends TestCase {

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
	 */
	private function resolver( array $postmeta = [], ?array $chain = null, ?ArchiveMetaRepository $archive = null ): Description {
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
		$opt_repo->method( 'get_path' )->willReturn(
			$chain ?? [ 'post_meta_override', 'archive_override', 'seo_plugin_desc', 'post_excerpt', 'post_content_trim', 'site_description' ]
		);

		return new Description( $post_repo, $opt_repo, $archive ?? $this->createStub( ArchiveMetaRepository::class ) );
	}

	public function test_post_meta_override_wins(): void {
		Filters\expectApplied( 'ogc_resolve_description_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_description_value' )->andReturnFirstArg();
		$r = $this->resolver( [ 'description' => 'Override desc' ] );
		self::assertSame( 'Override desc', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_falls_through_to_post_excerpt(): void {
		Functions\when( 'get_the_excerpt' )->justReturn( 'An excerpt' );
		Functions\when( 'get_bloginfo' )->justReturn( 'Site tagline' );
		Filters\expectApplied( 'ogc_resolve_description_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_description_value' )->andReturnFirstArg();

		$r = $this->resolver();
		self::assertSame( 'An excerpt', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_post_content_trim_strips_tags_and_truncates(): void {
		Functions\when( 'get_the_excerpt' )->justReturn( '' );
		Functions\when( 'get_post_field' )->justReturn(
			'<p><strong>Hello</strong> world. ' . str_repeat( 'Lorem ipsum dolor sit amet. ', 20 ) . '</p>'
		);
		Functions\when( 'strip_shortcodes' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->alias(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test stub for wp_strip_all_tags itself.
			static fn( string $s ) => trim( strip_tags( $s ) )
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Site' );
		Filters\expectApplied( 'ogc_resolve_description_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_description_value' )->andReturnFirstArg();

		$r      = $this->resolver( [], [ 'post_content_trim', 'site_description' ] );
		$output = $r->resolve( Context::for_post( 1 ) );
		self::assertStringStartsWith( 'Hello world.', (string) $output );
		self::assertStringEndsWith( '…', (string) $output );
		// Stripped tags, so no <strong>.
		self::assertStringNotContainsString( '<', (string) $output );
	}

	public function test_front_falls_through_to_site_description(): void {
		Functions\when( 'get_bloginfo' )->justReturn( 'Site tagline' );
		Filters\expectApplied( 'ogc_resolve_description_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_description_value' )->andReturnFirstArg();

		$r = $this->resolver();
		self::assertSame( 'Site tagline', $r->resolve( Context::for_front() ) );
	}

	public function test_seo_plugin_filter_step(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '' );
		Filters\expectApplied( 'ogc_resolve_description_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_seo_plugin_desc' )->once()->andReturn( 'Yoast desc' );
		Filters\expectApplied( 'ogc_resolve_description_value' )->andReturnFirstArg();

		$r = $this->resolver();
		self::assertSame( 'Yoast desc', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_archive_override_returns_meta_for_archive_term(): void {
		Filters\expectApplied( 'ogc_resolve_description_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_description_value' )->andReturnFirstArg();

		$archive = $this->createStub( ArchiveMetaRepository::class );
		$archive->method( 'get_for_term' )->willReturn(
			[
				'title'       => '',
				'description' => 'Category description override',
				'image_id'    => 0,
				'exclude'     => [],
			]
		);

		$r = $this->resolver( [], [ 'archive_override' ], $archive );
		self::assertSame(
			'Category description override',
			$r->resolve( Context::for_archive_term( 'category', 12 ) )
		);
	}

	public function test_archive_override_returns_meta_for_author(): void {
		Filters\expectApplied( 'ogc_resolve_description_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_description_value' )->andReturnFirstArg();

		$archive = $this->createStub( ArchiveMetaRepository::class );
		$archive->method( 'get_for_user' )->willReturn(
			[
				'title'       => '',
				'description' => 'Author bio override',
				'image_id'    => 0,
				'exclude'     => [],
			]
		);

		$r = $this->resolver( [], [ 'archive_override' ], $archive );
		self::assertSame( 'Author bio override', $r->resolve( Context::for_author( 3 ) ) );
	}

	public function test_archive_override_null_on_post_context(): void {
		Filters\expectApplied( 'ogc_resolve_description_chain' )->andReturnFirstArg();

		$archive = $this->createStub( ArchiveMetaRepository::class );

		$r = $this->resolver( [], [ 'archive_override' ], $archive );
		self::assertNull( $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_per_step_filter_can_supply_value_for_unknown_step(): void {
		Filters\expectApplied( 'ogc_resolve_description_chain' )
			->andReturn( [ 'post_meta_override', 'custom_step' ] );
		Filters\expectApplied( 'ogc_resolve_description_step' )
			->with( null, 'custom_step', \Mockery::type( Context::class ) )
			->andReturn( 'Custom description' );
		Filters\expectApplied( 'ogc_resolve_description_value' )
			->with( 'Custom description', \Mockery::type( Context::class ) )
			->andReturn( 'Custom description' );

		$r = $this->resolver( [], [ 'post_meta_override', 'custom_step' ] );
		self::assertSame( 'Custom description', $r->resolve( Context::for_post( 1 ) ) );
	}
}
