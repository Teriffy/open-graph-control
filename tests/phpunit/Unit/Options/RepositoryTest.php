<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Options;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Options\DefaultSettings;
use EvzenLeonenko\OpenGraphControl\Options\Repository;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_returns_defaults_when_option_missing(): void {
		Functions\expect( 'get_option' )->once()->with( 'ogc_settings', [] )->andReturn( [] );
		$repo = new Repository();
		self::assertSame( DefaultSettings::all(), $repo->get() );
	}

	public function test_get_merges_stored_over_defaults(): void {
		Functions\expect( 'get_option' )->once()->andReturn(
			[
				'version' => 1,
				'site'    => [ 'name' => 'My Site' ],
			]
		);
		$repo   = new Repository();
		$merged = $repo->get();
		self::assertSame( 'My Site', $merged['site']['name'] );
		// Backfilled from defaults.
		self::assertSame( '', $merged['site']['description'] );
		self::assertArrayHasKey( 'facebook', $merged['platforms'] );
	}

	public function test_stored_values_override_defaults_recursively(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'platforms' => [
					'twitter' => [ 'card' => 'summary' ],
				],
			]
		);
		$repo   = new Repository();
		$merged = $repo->get();
		self::assertSame( 'summary', $merged['platforms']['twitter']['card'] );
		// Sibling default keys preserved.
		self::assertTrue( $merged['platforms']['twitter']['enabled'] );
		self::assertSame( '', $merged['platforms']['twitter']['site'] );
	}

	public function test_update_calls_wp_update_option_with_merged_payload(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'update_option' )->once()->with(
			'ogc_settings',
			\Mockery::on(
				static function ( $payload ) {
					return is_array( $payload ) && 'X' === $payload['site']['name'];
				}
			),
			true
		)->andReturn( true );
		$repo = new Repository();
		self::assertTrue( $repo->update( [ 'site' => [ 'name' => 'X' ] ] ) );
	}

	public function test_get_path_walks_dot_notation(): void {
		Functions\when( 'get_option' )->justReturn( [ 'site' => [ 'name' => 'Found' ] ] );
		$repo = new Repository();
		self::assertSame( 'Found', $repo->get_path( 'site.name' ) );
		self::assertNull( $repo->get_path( 'nope.here' ) );
	}

	public function test_flush_cache_forces_reread(): void {
		Functions\expect( 'get_option' )->twice()->andReturn(
			[ 'site' => [ 'name' => 'First' ] ],
			[ 'site' => [ 'name' => 'Second' ] ]
		);
		$repo = new Repository();
		self::assertSame( 'First', $repo->get_path( 'site.name' ) );
		$repo->flush_cache();
		self::assertSame( 'Second', $repo->get_path( 'site.name' ) );
	}

	public function test_list_values_are_replaced_not_merged(): void {
		// Numeric lists should replace wholesale so users can shorten a chain.
		Functions\when( 'get_option' )->justReturn(
			[
				'fallback_chains' => [
					'title' => [ 'post_title', 'site_name' ],
				],
			]
		);
		$repo = new Repository();
		self::assertSame( [ 'post_title', 'site_name' ], $repo->get_path( 'fallback_chains.title' ) );
	}
}
