<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Options;

use EvzenLeonenko\OpenGraphControl\Options\DefaultSettings;
use PHPUnit\Framework\TestCase;

final class DefaultSettingsTest extends TestCase {

	public function test_has_all_top_level_keys(): void {
		$defaults = DefaultSettings::all();
		foreach ( [ 'version', 'site', 'platforms', 'post_types', 'non_post_pages', 'integrations', 'output', 'fallback_chains' ] as $key ) {
			self::assertArrayHasKey( $key, $defaults, "Missing key: {$key}" );
		}
	}

	public function test_lists_all_twelve_platforms(): void {
		$platforms = DefaultSettings::all()['platforms'];
		$expected  = [ 'facebook', 'twitter', 'linkedin', 'imessage', 'threads', 'mastodon', 'bluesky', 'whatsapp', 'discord', 'pinterest', 'telegram', 'slack' ];
		foreach ( $expected as $slug ) {
			self::assertArrayHasKey( $slug, $platforms, "Missing platform: {$slug}" );
			self::assertTrue( $platforms[ $slug ]['enabled'], "Platform {$slug} should default to enabled" );
		}
	}

	public function test_twitter_defaults_to_summary_large_image(): void {
		self::assertSame( 'summary_large_image', DefaultSettings::all()['platforms']['twitter']['card'] );
	}

	public function test_pinterest_defaults_to_article_schema(): void {
		self::assertSame( 'article', DefaultSettings::all()['platforms']['pinterest']['rich_pins_type'] );
	}

	public function test_output_defaults_are_conservative(): void {
		$out = DefaultSettings::all()['output'];
		self::assertFalse( $out['strict_mode'], 'Strict mode should be off by default for broader scraper compatibility' );
		self::assertTrue( $out['comment_markers'] );
		self::assertSame( 0, $out['cache_ttl'], 'Cache off by default' );
	}

	public function test_search_and_404_are_disabled_by_default(): void {
		$pages = DefaultSettings::all()['non_post_pages'];
		self::assertFalse( $pages['search']['enabled'] );
		self::assertFalse( $pages['not_found']['enabled'] );
	}

	public function test_fallback_chains_have_title_description_image(): void {
		$chains = DefaultSettings::all()['fallback_chains'];
		self::assertIsArray( $chains['title'] );
		self::assertContains( 'post_meta_override', $chains['title'] );
		self::assertIsArray( $chains['description'] );
		self::assertContains( 'post_content_trim', $chains['description'] );
		self::assertIsArray( $chains['image'] );
		self::assertContains( 'featured_image', $chains['image'] );
	}

	public function test_schema_version_is_exposed(): void {
		self::assertSame( DefaultSettings::SCHEMA_VERSION, DefaultSettings::all()['version'] );
	}
}
