<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Resolvers;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository as PostMetaRepository;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Resolvers\Image;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase {

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
	private function resolver( array $postmeta = [], ?array $chain = null, array $options = [] ): Image {
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
			static function ( string $path ) use ( $chain, $options ) {
				if ( 'fallback_chains.image' === $path ) {
					return $chain ?? [ 'post_meta_override', 'featured_image', 'first_content_image', 'first_block_image', 'site_master_image' ];
				}
				if ( 'site.master_image_id' === $path ) {
					return $options['site_master_image_id'] ?? 0;
				}
				return null;
			}
		);

		return new Image( $post_repo, $opt_repo );
	}

	public function test_post_meta_override_returns_attachment_id_as_string(): void {
		Filters\expectApplied( 'ogc_resolve_image_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_image_value' )->andReturnFirstArg();
		$r = $this->resolver( [ 'image_id' => 123 ] );
		self::assertSame( '123', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_featured_image_fallback(): void {
		Functions\expect( 'has_post_thumbnail' )->once()->andReturn( true );
		Functions\expect( 'get_post_thumbnail_id' )->once()->andReturn( 456 );
		Filters\expectApplied( 'ogc_resolve_image_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_image_value' )->andReturnFirstArg();

		$r = $this->resolver();
		self::assertSame( '456', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_first_content_image_parses_img_tag(): void {
		Functions\when( 'has_post_thumbnail' )->justReturn( false );
		Functions\when( 'get_post_field' )->justReturn( '<p>Hi</p><img src="https://cdn.example.com/pic.jpg" alt="x"/><img src="second.jpg"/>' );
		Functions\when( 'has_blocks' )->justReturn( false );
		Filters\expectApplied( 'ogc_resolve_image_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_image_value' )->andReturnFirstArg();

		$r = $this->resolver();
		self::assertSame( 'https://cdn.example.com/pic.jpg', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_first_block_image_extracts_attachment_id(): void {
		Functions\when( 'has_post_thumbnail' )->justReturn( false );
		Functions\when( 'get_post_field' )->justReturn(
			'<!-- wp:image {"id":789,"sizeSlug":"large"} --><figure class="wp-block-image"><img src="a.jpg"/></figure><!-- /wp:image -->'
		);
		Functions\when( 'has_blocks' )->justReturn( true );
		Functions\when( 'parse_blocks' )->justReturn(
			[
				[
					'blockName'   => 'core/image',
					'attrs'       => [ 'id' => 789 ],
					'innerHTML'   => '<figure class="wp-block-image"><img src="a.jpg"/></figure>',
					'innerBlocks' => [],
				],
			]
		);
		Filters\expectApplied( 'ogc_resolve_image_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_image_value' )->andReturnFirstArg();

		$r = $this->resolver( [], [ 'first_block_image', 'site_master_image' ] );
		self::assertSame( '789', $r->resolve( Context::for_post( 1 ) ) );
	}

	public function test_site_master_image_fallback(): void {
		Filters\expectApplied( 'ogc_resolve_image_chain' )->andReturnFirstArg();
		Filters\expectApplied( 'ogc_resolve_image_value' )->andReturnFirstArg();
		$r = $this->resolver( [], [ 'site_master_image' ], [ 'site_master_image_id' => 999 ] );
		self::assertSame( '999', $r->resolve( Context::for_front() ) );
	}

	public function test_returns_null_when_chain_exhausts(): void {
		Filters\expectApplied( 'ogc_resolve_image_chain' )->andReturnFirstArg();
		$r = $this->resolver( [], [ 'site_master_image' ] );
		self::assertNull( $r->resolve( Context::for_front() ) );
	}
}
