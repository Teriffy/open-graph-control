<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Platforms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\Facebook;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Resolvers\Description;
use EvzenLeonenko\OpenGraphControl\Resolvers\Image;
use EvzenLeonenko\OpenGraphControl\Resolvers\Locale;
use EvzenLeonenko\OpenGraphControl\Resolvers\Title;
use EvzenLeonenko\OpenGraphControl\Resolvers\Type;
use EvzenLeonenko\OpenGraphControl\Resolvers\Url;
use PHPUnit\Framework\TestCase;

final class FacebookTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $options
	 * @param array<string, string|null> $resolved
	 */
	private function platform( array $options = [], array $resolved = [] ): Facebook {
		$opt = $this->createStub( OptionsRepository::class );
		$opt->method( 'get_path' )->willReturnCallback(
			static fn ( string $path ) => $options[ $path ] ?? null
		);

		$title       = $this->stubResolver( Title::class, $resolved['title'] ?? 'Title' );
		$description = $this->stubResolver( Description::class, $resolved['description'] ?? 'Description' );
		$image       = $this->stubResolver( Image::class, $resolved['image'] ?? null );
		$type        = $this->stubResolver( Type::class, $resolved['type'] ?? 'website' );
		$url         = $this->stubResolver( Url::class, $resolved['url'] ?? 'https://example.com/' );
		$locale      = $this->stubResolver( Locale::class, $resolved['locale'] ?? 'en_US' );

		return new Facebook( $opt, $title, $description, $image, $type, $url, $locale );
	}

	/**
	 * @template T of object
	 * @param class-string<T> $fqcn
	 * @return T
	 */
	private function stubResolver( string $fqcn, ?string $value ): object {
		$stub = $this->createStub( $fqcn );
		$stub->method( 'resolve' )->willReturn( $value );
		return $stub;
	}

	public function test_emits_core_og_tags(): void {
		$tags = $this->platform(
			[ 'site.name' => 'Example Site' ],
			[
				'title'       => 'My Title',
				'description' => 'My Desc',
				'url'         => 'https://example.com/post/',
				'type'        => 'article',
				'locale'      => 'cs_CZ',
			]
		)->tags( Context::for_front() );

		$keys = array_map( static fn ( $t ) => $t->key, $tags );
		self::assertContains( 'og:title', $keys );
		self::assertContains( 'og:description', $keys );
		self::assertContains( 'og:url', $keys );
		self::assertContains( 'og:type', $keys );
		self::assertContains( 'og:locale', $keys );
		self::assertContains( 'og:site_name', $keys );
	}

	public function test_fb_app_id_emitted_when_configured(): void {
		$tags = $this->platform( [ 'platforms.facebook.fb_app_id' => '123456' ] )
			->tags( Context::for_front() );

		$keys = array_map( static fn ( $t ) => $t->key, $tags );
		self::assertContains( 'fb:app_id', $keys );
	}

	public function test_fb_app_id_omitted_when_empty(): void {
		$tags = $this->platform()->tags( Context::for_front() );
		$keys = array_map( static fn ( $t ) => $t->key, $tags );
		self::assertNotContains( 'fb:app_id', $keys );
	}

	public function test_article_tags_emitted_for_singular_article(): void {
		Functions\when( 'get_post' )->justReturn(
			(object) [
				'post_date_gmt'     => '2026-04-19 10:00:00',
				'post_modified_gmt' => '2026-04-19 11:00:00',
				'post_author'       => 5,
			]
		);
		Functions\when( 'mysql2date' )->alias( static fn ( $fmt, $gmt ) => $gmt );
		Functions\when( 'get_the_author_meta' )->justReturn( 'Author Name' );
		Functions\when( 'get_the_category' )->justReturn( [ (object) [ 'name' => 'News' ] ] );
		Functions\when( 'get_the_tags' )->justReturn( [ (object) [ 'name' => 'wp' ], (object) [ 'name' => 'og' ] ] );

		$tags = $this->platform( [], [ 'type' => 'article' ] )
			->tags( Context::for_post( 1 ) );

		$keys = array_map( static fn ( $t ) => $t->key, $tags );
		self::assertContains( 'article:published_time', $keys );
		self::assertContains( 'article:modified_time', $keys );
		self::assertContains( 'article:author', $keys );
		self::assertContains( 'article:section', $keys );
		self::assertSame( 2, count( array_filter( $tags, static fn ( $t ) => 'article:tag' === $t->key ) ) );
	}

	public function test_article_tags_skipped_for_non_article_type(): void {
		$tags = $this->platform( [], [ 'type' => 'website' ] )
			->tags( Context::for_post( 1 ) );

		$keys = array_map( static fn ( $t ) => $t->key, $tags );
		self::assertNotContains( 'article:published_time', $keys );
	}

	public function test_image_url_passed_through_when_not_numeric(): void {
		$tags = $this->platform(
			[],
			[ 'image' => 'https://cdn.example.com/hero.jpg' ]
		)->tags( Context::for_front() );

		$image_tags = array_filter( $tags, static fn ( $t ) => 'og:image' === $t->key );
		self::assertCount( 1, $image_tags );
		self::assertSame( 'https://cdn.example.com/hero.jpg', array_values( $image_tags )[0]->content );
	}

	public function test_image_id_resolves_to_attachment_url_and_dimensions(): void {
		Functions\when( 'wp_get_attachment_image_src' )->justReturn(
			[ 'https://example.com/wp-content/uploads/hero-1200x630.jpg', 1200, 630, false ]
		);
		Functions\when( 'get_post_meta' )->justReturn( 'Alt text' );

		$tags = $this->platform( [], [ 'image' => '456' ] )->tags( Context::for_front() );
		$keys = array_map( static fn ( $t ) => $t->key, $tags );
		self::assertContains( 'og:image', $keys );
		self::assertContains( 'og:image:width', $keys );
		self::assertContains( 'og:image:height', $keys );
		self::assertContains( 'og:image:alt', $keys );
	}

	public function test_slug_is_facebook(): void {
		self::assertSame( 'facebook', $this->platform()->slug() );
	}

	public function test_enabled_reflects_option(): void {
		self::assertNull( $this->platform()->is_enabled() ? null : null ); // non-assertive
		self::assertTrue(
			$this->platform( [ 'platforms.facebook.enabled' => true ] )->is_enabled()
		);
		self::assertFalse(
			$this->platform( [ 'platforms.facebook.enabled' => false ] )->is_enabled()
		);
	}
}
