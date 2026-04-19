<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Renderer;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\Registry as PlatformRegistry;
use EvzenLeonenko\OpenGraphControl\Renderer\Head;
use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Renderer\TagBuilder;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use PHPUnit\Framework\TestCase;

final class HeadTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		if ( ! defined( 'OGC_VERSION' ) ) {
			define( 'OGC_VERSION', '0.0.1-test' );
		}
		$this->stubContextDetection( 'front' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function stubContextDetection( string $kind ): void {
		Functions\when( 'is_404' )->justReturn( 'not_found' === $kind );
		Functions\when( 'is_search' )->justReturn( 'search' === $kind );
		Functions\when( 'is_front_page' )->justReturn( 'front' === $kind );
		Functions\when( 'is_home' )->justReturn( 'blog' === $kind );
		Functions\when( 'is_singular' )->justReturn( 'singular' === $kind );
		Functions\when( 'is_author' )->justReturn( 'author' === $kind );
		Functions\when( 'is_date' )->justReturn( 'date' === $kind );
		Functions\when( 'is_archive' )->justReturn( 'archive' === $kind );
		Functions\when( 'is_category' )->justReturn( false );
		Functions\when( 'is_tag' )->justReturn( false );
		Functions\when( 'is_tax' )->justReturn( false );
		Functions\when( 'get_queried_object' )->justReturn( (object) [ 'ID' => 42 ] );
	}

	private function head(
		PlatformRegistry $registry,
		array $options = [],
		array $exclude = [],
		?\EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository $archive = null
	): Head {
		$opt = $this->createStub( OptionsRepository::class );
		$opt->method( 'get_path' )->willReturnCallback(
			static function ( string $path ) use ( $options ) {
				return array_key_exists( $path, $options ) ? $options[ $path ] : null;
			}
		);
		$postmeta = $this->createStub( \EvzenLeonenko\OpenGraphControl\PostMeta\Repository::class );
		$postmeta->method( 'get' )->willReturn(
			[
				'title'       => '',
				'description' => '',
				'image_id'    => 0,
				'type'        => '',
				'platforms'   => [],
				'exclude'     => $exclude,
			]
		);
		$cache = $this->createStub( \EvzenLeonenko\OpenGraphControl\Renderer\Cache::class );
		$cache->method( 'get' )->willReturn( null );
		if ( null === $archive ) {
			$archive = $this->createStub( \EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository::class );
			$archive->method( 'get_for_term' )->willReturn(
				[
					'title'       => '',
					'description' => '',
					'image_id'    => 0,
					'exclude'     => [],
				]
			);
			$archive->method( 'get_for_user' )->willReturn(
				[
					'title'       => '',
					'description' => '',
					'image_id'    => 0,
					'exclude'     => [],
				]
			);
		}
		return new Head( $registry, new TagBuilder( strict: true ), $opt, $postmeta, $cache, $archive );
	}

	private function registryWithTags( array $tags ): PlatformRegistry {
		$registry = $this->createStub( PlatformRegistry::class );
		$registry->method( 'collect_tags' )->willReturn( $tags );
		$registry->method( 'collect_json_ld' )->willReturn( [] );
		return $registry;
	}

	public function test_register_hooks_wp_head(): void {
		Actions\expectAdded( 'wp_head' )->once()->with( \Mockery::type( 'array' ), 1 );
		$head = $this->head( $this->registryWithTags( [] ) );
		$head->register();
		self::assertTrue( true ); // Brain Monkey's Actions\expectAdded verifies on tearDown.
	}

	public function test_render_emits_nothing_when_no_tags(): void {
		$head = $this->head(
			$this->registryWithTags( [] ),
			[
				'non_post_pages.front.enabled' => true,
				'output.comment_markers'       => true,
			]
		);

		ob_start();
		$head->render();
		self::assertSame( '', ob_get_clean() );
	}

	public function test_render_emits_tags_with_comment_markers(): void {
		$head = $this->head(
			$this->registryWithTags( [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'Hi' ) ] ),
			[
				'non_post_pages.front.enabled' => true,
				'output.comment_markers'       => true,
			]
		);

		ob_start();
		$head->render();
		$output = ob_get_clean();

		self::assertStringContainsString( '<!-- Open Graph Control', $output );
		self::assertStringContainsString( '<meta property="og:title"', $output );
		self::assertStringContainsString( '<!-- /Open Graph Control -->', $output );
	}

	public function test_render_skips_disabled_non_post_context(): void {
		$head = $this->head(
			$this->registryWithTags( [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'Hi' ) ] ),
			[ 'non_post_pages.front.enabled' => false ]
		);

		ob_start();
		$head->render();
		self::assertSame( '', ob_get_clean() );
	}

	public function test_render_always_emits_for_singular(): void {
		$this->stubContextDetection( 'singular' );

		$head = $this->head(
			$this->registryWithTags( [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'Post' ) ] ),
			[ 'output.comment_markers' => false ]
		);

		ob_start();
		$head->render();
		$output = ob_get_clean();
		self::assertStringContainsString( '<meta property="og:title"', $output );
		self::assertStringNotContainsString( '<!-- Open Graph Control', $output );
	}

	public function test_excluded_post_emits_nothing(): void {
		$this->stubContextDetection( 'singular' );

		$head = $this->head(
			$this->registryWithTags( [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'Post' ) ] ),
			[ 'output.comment_markers' => true ],
			[ 'all' ]
		);

		ob_start();
		$head->render();
		self::assertSame( '', ob_get_clean() );
	}

	public function test_non_excluded_post_still_renders(): void {
		$this->stubContextDetection( 'singular' );

		$head = $this->head(
			$this->registryWithTags( [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'Post' ) ] ),
			[ 'output.comment_markers' => false ],
			[ 'search' ] // Other contexts excluded but not 'all'.
		);

		ob_start();
		$head->render();
		self::assertStringContainsString( '<meta property="og:title"', ob_get_clean() );
	}

	public function test_render_emits_json_ld_payloads(): void {
		$registry = $this->createStub( PlatformRegistry::class );
		$registry->method( 'collect_tags' )->willReturn( [] );
		$registry->method( 'collect_json_ld' )->willReturn( [ '{"@type":"Article"}' ] );

		$head = $this->head( $registry, [ 'non_post_pages.front.enabled' => true ] );
		ob_start();
		$head->render();
		$output = ob_get_clean();
		self::assertStringContainsString( '<script type="application/ld+json">', $output );
		self::assertStringContainsString( '{"@type":"Article"}', $output );
	}

	/**
	 * Defense-in-depth: even if a platform class forgets JSON_HEX_TAG,
	 * Head::render must not allow a raw </ sequence inside the JSON-LD
	 * block to close the surrounding <script> tag. Payload contents are
	 * defanged by replacing "</" with "<\/" (valid JSON, inert HTML).
	 */
	public function test_render_defangs_script_breakout_in_json_ld(): void {
		$registry = $this->createStub( PlatformRegistry::class );
		$registry->method( 'collect_tags' )->willReturn( [] );
		$registry->method( 'collect_json_ld' )->willReturn(
			[ '{"headline":"bad</script><img src=x onerror=alert(1)>"}' ]
		);

		$head = $this->head( $registry, [ 'non_post_pages.front.enabled' => true ] );
		ob_start();
		$head->render();
		$output = ob_get_clean();

		// Only the wrapping </script> closer should remain; the payload's
		// attempt to close the tag must be escaped. Anything after the
		// defanged </script> stays inside the script element as inert text.
		self::assertSame( 1, substr_count( $output, '</script>' ) );
		self::assertStringContainsString( '<\/script>', $output );
	}

	public function test_detect_context_returns_archive_term_for_category(): void {
		$this->stubContextDetection( 'archive' );
		Functions\when( 'is_category' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn(
			(object) [
				'term_id'  => 12,
				'taxonomy' => 'category',
			]
		);

		$head = $this->head( $this->registryWithTags( [] ) );
		$ref  = new \ReflectionClass( $head );
		$m    = $ref->getMethod( 'detect_context' );
		$m->setAccessible( true );
		$context = $m->invoke( $head );

		self::assertTrue( $context->is_archive_term() );
		self::assertSame( 12, $context->archive_term_id() );
		self::assertSame( 'category', $context->archive_kind() );
	}

	public function test_archive_context_excluded_emits_nothing(): void {
		$this->stubContextDetection( 'archive' );
		Functions\when( 'is_category' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn(
			(object) [
				'term_id'  => 5,
				'taxonomy' => 'category',
			]
		);
		$archive = $this->createStub(
			\EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository::class
		);
		$archive->method( 'get_for_term' )->willReturn(
			[
				'title'       => '',
				'description' => '',
				'image_id'    => 0,
				'exclude'     => [ 'all' ],
			]
		);

		$head = $this->head(
			$this->registryWithTags( [ new Tag( Tag::KIND_PROPERTY, 'og:title', 'X' ) ] ),
			[ 'non_post_pages.archive.enabled' => true ],
			[],
			$archive
		);

		ob_start();
		$head->render();
		self::assertSame( '', ob_get_clean() );
	}
}
