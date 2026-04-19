<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Platforms;

use Brain\Monkey;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Platforms\AbstractPlatform;
use EvzenLeonenko\OpenGraphControl\Platforms\Bluesky;
use EvzenLeonenko\OpenGraphControl\Platforms\Discord;
use EvzenLeonenko\OpenGraphControl\Platforms\IMessage;
use EvzenLeonenko\OpenGraphControl\Platforms\LinkedIn;
use EvzenLeonenko\OpenGraphControl\Platforms\Mastodon;
use EvzenLeonenko\OpenGraphControl\Platforms\Pinterest;
use EvzenLeonenko\OpenGraphControl\Platforms\Slack;
use EvzenLeonenko\OpenGraphControl\Platforms\Telegram;
use EvzenLeonenko\OpenGraphControl\Platforms\Threads;
use EvzenLeonenko\OpenGraphControl\Platforms\Twitter;
use EvzenLeonenko\OpenGraphControl\Platforms\WhatsApp;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Resolvers\Description;
use EvzenLeonenko\OpenGraphControl\Resolvers\Image;
use EvzenLeonenko\OpenGraphControl\Resolvers\Locale;
use EvzenLeonenko\OpenGraphControl\Resolvers\Title;
use EvzenLeonenko\OpenGraphControl\Resolvers\Type;
use EvzenLeonenko\OpenGraphControl\Resolvers\Url;
use PHPUnit\Framework\TestCase;

final class AllPlatformsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param class-string<AbstractPlatform> $fqcn
	 * @param array<string, mixed>           $options
	 */
	private function make( string $fqcn, array $options = [] ): AbstractPlatform {
		$opt = $this->createStub( OptionsRepository::class );
		$opt->method( 'get_path' )->willReturnCallback(
			static fn ( string $path ) => array_key_exists( $path, $options ) ? $options[ $path ] : null
		);

		$title = $this->createStub( Title::class );
		$title->method( 'resolve' )->willReturn( 'T' );
		$description = $this->createStub( Description::class );
		$description->method( 'resolve' )->willReturn( 'D' );
		$image = $this->createStub( Image::class );
		$image->method( 'resolve' )->willReturn( null );
		$type = $this->createStub( Type::class );
		$type->method( 'resolve' )->willReturn( 'website' );
		$url = $this->createStub( Url::class );
		$url->method( 'resolve' )->willReturn( 'https://example.com/' );
		$locale = $this->createStub( Locale::class );
		$locale->method( 'resolve' )->willReturn( 'en_US' );

		return new $fqcn( $opt, $title, $description, $image, $type, $url, $locale );
	}

	/**
	 * @return array<string, array{0: class-string<AbstractPlatform>, 1: string}>
	 */
	public static function emptyPlatformProvider(): array {
		return [
			'linkedin'  => [ LinkedIn::class, 'linkedin' ],
			'imessage'  => [ IMessage::class, 'imessage' ],
			'threads'   => [ Threads::class, 'threads' ],
			'bluesky'   => [ Bluesky::class, 'bluesky' ],
			'whatsapp'  => [ WhatsApp::class, 'whatsapp' ],
			'pinterest' => [ Pinterest::class, 'pinterest' ],
			'telegram'  => [ Telegram::class, 'telegram' ],
			'slack'     => [ Slack::class, 'slack' ],
		];
	}

	/**
	 * @dataProvider emptyPlatformProvider
	 * @param class-string<AbstractPlatform> $fqcn
	 */
	public function test_silent_platform_exposes_slug_and_no_tags( string $fqcn, string $expected_slug ): void {
		$platform = $this->make( $fqcn );
		self::assertSame( $expected_slug, $platform->slug() );
		self::assertSame( [], $platform->tags( Context::for_front() ) );
	}

	public function test_twitter_emits_card_and_text(): void {
		$platform = $this->make(
			Twitter::class,
			[
				'platforms.twitter.card'    => 'summary_large_image',
				'platforms.twitter.site'    => '@example',
				'platforms.twitter.creator' => '@me',
			]
		);
		$tags     = $platform->tags( Context::for_front() );
		$keys     = array_map( static fn ( $t ) => $t->key, $tags );
		self::assertContains( 'twitter:card', $keys );
		self::assertContains( 'twitter:title', $keys );
		self::assertContains( 'twitter:description', $keys );
		self::assertContains( 'twitter:site', $keys );
		self::assertContains( 'twitter:creator', $keys );
	}

	public function test_twitter_defaults_to_summary_large_image_when_unset(): void {
		$platform = $this->make( Twitter::class );
		$card     = array_values(
			array_filter(
				$platform->tags( Context::for_front() ),
				static fn ( $t ) => 'twitter:card' === $t->key
			)
		);
		self::assertSame( 'summary_large_image', $card[0]->content );
	}

	public function test_twitter_omits_site_and_creator_when_empty(): void {
		$tags = $this->make( Twitter::class )->tags( Context::for_front() );
		$keys = array_map( static fn ( $t ) => $t->key, $tags );
		self::assertNotContains( 'twitter:site', $keys );
		self::assertNotContains( 'twitter:creator', $keys );
	}

	public function test_mastodon_emits_fediverse_creator_when_set(): void {
		$platform = $this->make(
			Mastodon::class,
			[ 'platforms.mastodon.fediverse_creator' => '@me@fosstodon.org' ]
		);
		$tags     = $platform->tags( Context::for_front() );
		self::assertCount( 1, $tags );
		self::assertSame( 'fediverse:creator', $tags[0]->key );
		self::assertSame( '@me@fosstodon.org', $tags[0]->content );
	}

	public function test_mastodon_silent_when_creator_empty(): void {
		self::assertSame( [], $this->make( Mastodon::class )->tags( Context::for_front() ) );
	}

	public function test_discord_emits_theme_color_when_set(): void {
		$platform = $this->make( Discord::class, [ 'site.theme_color' => '#abcdef' ] );
		$tags     = $platform->tags( Context::for_front() );
		self::assertCount( 1, $tags );
		self::assertSame( 'theme-color', $tags[0]->key );
		self::assertSame( '#abcdef', $tags[0]->content );
	}

	public function test_discord_silent_when_theme_color_empty(): void {
		self::assertSame( [], $this->make( Discord::class )->tags( Context::for_front() ) );
	}
}
