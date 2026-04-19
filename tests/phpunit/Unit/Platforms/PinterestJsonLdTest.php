<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Platforms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Images\SizeRegistry;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\Pinterest;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Resolvers\Description;
use EvzenLeonenko\OpenGraphControl\Resolvers\Image;
use EvzenLeonenko\OpenGraphControl\Resolvers\Locale;
use EvzenLeonenko\OpenGraphControl\Resolvers\Title;
use EvzenLeonenko\OpenGraphControl\Resolvers\Type;
use EvzenLeonenko\OpenGraphControl\Resolvers\Url;
use PHPUnit\Framework\TestCase;

final class PinterestJsonLdTest extends TestCase {

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
	 */
	private function platform( array $options = [], ?string $image_ref = null ): Pinterest {
		$opt = $this->createStub( OptionsRepository::class );
		$opt->method( 'get_path' )->willReturnCallback(
			static fn ( string $path ) => $options[ $path ] ?? null
		);

		$title = $this->createStub( Title::class );
		$title->method( 'resolve' )->willReturn( 'My Title' );
		$description = $this->createStub( Description::class );
		$description->method( 'resolve' )->willReturn( 'My Description' );
		$image = $this->createStub( Image::class );
		$image->method( 'resolve' )->willReturn( $image_ref );
		$type = $this->createStub( Type::class );
		$type->method( 'resolve' )->willReturn( 'article' );
		$url = $this->createStub( Url::class );
		$url->method( 'resolve' )->willReturn( 'https://example.com/p/1/' );
		$locale = $this->createStub( Locale::class );
		$locale->method( 'resolve' )->willReturn( 'en_US' );

		return new Pinterest( $opt, $title, $description, $image, $type, $url, $locale );
	}

	public function test_json_ld_null_for_non_singular(): void {
		self::assertNull( $this->platform()->json_ld( Context::for_front() ) );
	}

	public function test_article_schema_for_article_type(): void {
		Functions\when( 'get_post' )->justReturn(
			(object) [
				'post_date_gmt' => '2026-04-19 10:00:00',
				'post_author'   => 5,
			]
		);
		Functions\when( 'mysql2date' )->alias( static fn ( $fmt, $d ) => $d );
		Functions\when( 'get_the_author_meta' )->justReturn( 'Author' );

		$payload = $this->platform(
			[ 'platforms.pinterest.rich_pins_type' => 'article' ],
			null
		)->json_ld( Context::for_post( 42 ) );

		self::assertNotNull( $payload );
		$decoded = json_decode( (string) $payload, true );
		self::assertSame( 'https://schema.org', $decoded['@context'] );
		self::assertSame( 'Article', $decoded['@type'] );
		self::assertSame( 'My Title', $decoded['headline'] );
		self::assertSame( 'My Description', $decoded['description'] );
		self::assertSame( 'https://example.com/p/1/', $decoded['url'] );
		self::assertSame( 'Author', $decoded['author']['name'] );
	}

	public function test_image_included_when_url_resolvable(): void {
		Functions\when( 'get_post' )->justReturn(
			(object) [
				'post_date_gmt' => '2026-04-19 10:00:00',
				'post_author'   => 5,
			]
		);
		Functions\when( 'mysql2date' )->alias( static fn ( $fmt, $d ) => $d );
		Functions\when( 'get_the_author_meta' )->justReturn( 'Author' );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/pic.jpg' );

		$payload = $this->platform(
			[ 'platforms.pinterest.rich_pins_type' => 'article' ],
			'456'
		)->json_ld( Context::for_post( 42 ) );

		$decoded = json_decode( (string) $payload, true );
		self::assertSame( 'https://example.com/pic.jpg', $decoded['image'] );
	}

	public function test_product_schema_selected(): void {
		$payload = $this->platform(
			[ 'platforms.pinterest.rich_pins_type' => 'product' ],
			null
		)->json_ld( Context::for_post( 1 ) );
		$decoded = json_decode( (string) $payload, true );
		self::assertSame( 'Product', $decoded['@type'] );
		self::assertSame( 'My Title', $decoded['name'] );
	}

	public function test_recipe_schema_selected(): void {
		$payload = $this->platform(
			[ 'platforms.pinterest.rich_pins_type' => 'recipe' ],
			null
		)->json_ld( Context::for_post( 1 ) );
		$decoded = json_decode( (string) $payload, true );
		self::assertSame( 'Recipe', $decoded['@type'] );
	}
}
