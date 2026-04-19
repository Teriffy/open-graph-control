<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Resolvers;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use EvzenLeonenko\OpenGraphControl\Resolvers\Url;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		unset( $_SERVER['REQUEST_URI'] );
	}

	private function resolver(): Url {
		return new Url();
	}

	public function test_singular_uses_permalink(): void {
		Functions\expect( 'get_permalink' )->once()->with( 42 )->andReturn( 'https://example.com/post-42/' );
		Filters\expectApplied( 'ogc_resolve_url_value' )->andReturnFirstArg();
		self::assertSame( 'https://example.com/post-42/', $this->resolver()->resolve( Context::for_post( 42 ) ) );
	}

	public function test_non_singular_uses_home_url_plus_request_uri(): void {
		$_SERVER['REQUEST_URI'] = '/category/tech/';
		Functions\when( 'home_url' )->alias( static fn ( $path ) => 'https://example.com' . $path );
		Functions\when( 'esc_url_raw' )->returnArg();
		Filters\expectApplied( 'ogc_resolve_url_value' )->andReturnFirstArg();

		self::assertSame( 'https://example.com/category/tech/', $this->resolver()->resolve( Context::for_archive( 'category' ) ) );
	}

	public function test_singular_with_empty_permalink_returns_null(): void {
		Functions\expect( 'get_permalink' )->once()->andReturn( false );
		Filters\expectApplied( 'ogc_resolve_url_value' )->andReturnFirstArg();
		self::assertNull( $this->resolver()->resolve( Context::for_post( 42 ) ) );
	}
}
