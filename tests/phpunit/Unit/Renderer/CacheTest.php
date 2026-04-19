<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Renderer;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Options\Repository as OptionsRepository;
use EvzenLeonenko\OpenGraphControl\Renderer\Cache;
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function options( int $ttl ): OptionsRepository {
		$opt = $this->createStub( OptionsRepository::class );
		$opt->method( 'get_path' )->willReturn( $ttl );
		return $opt;
	}

	public function test_disabled_when_ttl_zero(): void {
		$cache = new Cache( $this->options( 0 ) );
		self::assertFalse( $cache->is_enabled() );
		self::assertNull( $cache->get( Context::for_front() ) );
	}

	public function test_set_and_get_roundtrip(): void {
		$cache = new Cache( $this->options( 3600 ) );
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\expect( 'set_transient' )->once()->with( \Mockery::any(), 'payload', 3600 );
		$cache->set( Context::for_post( 1 ), 'payload' );
		self::assertTrue( $cache->is_enabled() );
	}

	public function test_get_returns_transient_when_hit(): void {
		$cache = new Cache( $this->options( 3600 ) );
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\expect( 'get_transient' )->once()->andReturn( '<html>' );
		self::assertSame( '<html>', $cache->get( Context::for_front() ) );
	}

	public function test_register_binds_invalidation_hooks(): void {
		Actions\expectAdded( 'save_post' )->once();
		Actions\expectAdded( 'delete_post' )->once();
		Actions\expectAdded( 'updated_post_meta' )->once();
		Actions\expectAdded( 'update_option_ogc_settings' )->once();
		Actions\expectAdded( 'switch_theme' )->once();
		Actions\expectAdded( 'update_option_blogname' )->once();
		Actions\expectAdded( 'update_option_blogdescription' )->once();

		( new Cache( $this->options( 3600 ) ) )->register();
		self::assertTrue( true );
	}

	public function test_flush_all_rotates_salt(): void {
		Functions\expect( 'update_option' )->once()->with(
			Cache::GLOBAL_SALT_OPTION,
			\Mockery::type( 'string' ),
			false
		);
		( new Cache( $this->options( 3600 ) ) )->flush_all();
		self::assertTrue( true );
	}

	public function test_flush_post_deletes_transient(): void {
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\expect( 'delete_transient' )->once();
		( new Cache( $this->options( 3600 ) ) )->flush_post( 42 );
		self::assertTrue( true );
	}

	public function test_flush_post_from_meta_only_fires_for_ogc_meta_key(): void {
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\expect( 'delete_transient' )->never();
		( new Cache( $this->options( 3600 ) ) )->flush_post_from_meta( 1, 42, 'unrelated' );
		self::assertTrue( true );
	}
}
