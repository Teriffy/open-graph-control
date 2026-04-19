<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Renderer;

use EvzenLeonenko\OpenGraphControl\Renderer\Tag;
use EvzenLeonenko\OpenGraphControl\Renderer\TagBuilder;
use PHPUnit\Framework\TestCase;

final class TagBuilderTest extends TestCase {

	public function test_property_tag_renders(): void {
		$html = ( new TagBuilder( strict: true ) )->render(
			[ new Tag( Tag::KIND_PROPERTY, 'og:title', 'Hello' ) ]
		);
		self::assertStringContainsString( '<meta property="og:title" content="Hello"', $html );
	}

	public function test_name_tag_renders(): void {
		$html = ( new TagBuilder( strict: true ) )->render(
			[ new Tag( Tag::KIND_NAME, 'twitter:card', 'summary' ) ]
		);
		self::assertStringContainsString( '<meta name="twitter:card" content="summary"', $html );
		self::assertStringNotContainsString( 'property="twitter:card"', $html );
	}

	public function test_non_strict_mode_emits_name_fallback_for_property_tags(): void {
		$html = ( new TagBuilder( strict: false ) )->render(
			[ new Tag( Tag::KIND_PROPERTY, 'og:title', 'X' ) ]
		);
		self::assertStringContainsString( 'property="og:title"', $html );
		self::assertStringContainsString( 'name="og:title"', $html );
	}

	public function test_strict_mode_property_only(): void {
		$html = ( new TagBuilder( strict: true ) )->render(
			[ new Tag( Tag::KIND_PROPERTY, 'og:title', 'X' ) ]
		);
		self::assertStringNotContainsString( 'name="og:title"', $html );
	}

	public function test_escapes_content(): void {
		$html = ( new TagBuilder( strict: true ) )->render(
			[ new Tag( Tag::KIND_PROPERTY, 'og:title', 'A & B "C" <D>' ) ]
		);
		self::assertStringContainsString( 'content="A &amp; B &quot;C&quot; &lt;D&gt;"', $html );
		self::assertStringNotContainsString( '"C"', $html );
	}

	public function test_skips_empty_content(): void {
		$html = ( new TagBuilder( strict: true ) )->render(
			[ new Tag( Tag::KIND_PROPERTY, 'og:title', '' ) ]
		);
		self::assertSame( '', trim( $html ) );
	}

	public function test_renders_multiple_tags_on_separate_lines(): void {
		$html = ( new TagBuilder( strict: true ) )->render(
			[
				new Tag( Tag::KIND_PROPERTY, 'og:title', 'A' ),
				new Tag( Tag::KIND_PROPERTY, 'og:description', 'B' ),
			]
		);
		self::assertStringContainsString( "\n", $html );
		self::assertSame( 2, substr_count( $html, '<meta ' ) );
	}
}
