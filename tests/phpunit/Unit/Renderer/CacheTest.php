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
		Actions\expectAdded( 'added_term_meta' )->once();
		Actions\expectAdded( 'updated_term_meta' )->once();
		Actions\expectAdded( 'deleted_term_meta' )->once();
		Actions\expectAdded( 'deleted_term' )->once();
		Actions\expectAdded( 'added_user_meta' )->once();
		Actions\expectAdded( 'updated_user_meta' )->once();
		Actions\expectAdded( 'deleted_user_meta' )->once();
		Actions\expectAdded( 'deleted_user' )->once();

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

	public function test_flush_term_from_meta_short_circuits_on_wrong_key(): void {
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\expect( 'get_term' )->never();
		Functions\expect( 'delete_transient' )->never();
		( new Cache( $this->options( 3600 ) ) )->flush_term_from_meta( 1, 42, 'some_other_key', [] );
		self::assertTrue( true );
	}

	public function test_flush_term_from_meta_flushes_on_ogc_meta(): void {
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\when( 'get_term' )->justReturn(
			(object) [
				'term_id'  => 42,
				'taxonomy' => 'category',
			]
		);
		$expected_key = 'ogc_cache_' . md5( implode( ':', [ Context::TYPE_ARCHIVE, '', 'category', '42', '', 'salt1' ] ) );
		Functions\expect( 'delete_transient' )->once()->with( $expected_key );
		( new Cache( $this->options( 3600 ) ) )->flush_term_from_meta( 1, 42, '_ogc_meta', [] );
		self::assertTrue( true );
	}

	public function test_flush_term_on_delete_flushes_term_context(): void {
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\when( 'get_term' )->justReturn(
			(object) [
				'term_id'  => 7,
				'taxonomy' => 'post_tag',
			]
		);
		$expected_key = 'ogc_cache_' . md5( implode( ':', [ Context::TYPE_ARCHIVE, '', 'post_tag', '7', '', 'salt1' ] ) );
		Functions\expect( 'delete_transient' )->once()->with( $expected_key );
		( new Cache( $this->options( 3600 ) ) )->flush_term_on_delete( 7, 99, 'post_tag' );
		self::assertTrue( true );
	}

	public function test_flush_term_skips_when_term_not_found(): void {
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\when( 'get_term' )->justReturn( null );
		Functions\expect( 'delete_transient' )->never();
		( new Cache( $this->options( 3600 ) ) )->flush_term_from_meta( 1, 42, '_ogc_meta', [] );
		self::assertTrue( true );
	}

	public function test_flush_user_from_meta_short_circuits_on_wrong_key(): void {
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\expect( 'delete_transient' )->never();
		( new Cache( $this->options( 3600 ) ) )->flush_user_from_meta( 1, 42, 'some_other_key', [] );
		self::assertTrue( true );
	}

	public function test_flush_user_from_meta_flushes_on_ogc_meta(): void {
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		$expected_key = 'ogc_cache_' . md5( implode( ':', [ Context::TYPE_AUTHOR, '', '', '', '42', 'salt1' ] ) );
		Functions\expect( 'delete_transient' )->once()->with( $expected_key );
		( new Cache( $this->options( 3600 ) ) )->flush_user_from_meta( 1, 42, '_ogc_meta', [] );
		self::assertTrue( true );
	}

	public function test_flush_user_from_meta_accepts_array_meta_ids_from_deleted_user_meta(): void {
		// `deleted_user_meta` passes an array of meta IDs, not a single int.
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		$expected_key = 'ogc_cache_' . md5( implode( ':', [ Context::TYPE_AUTHOR, '', '', '', '42', 'salt1' ] ) );
		Functions\expect( 'delete_transient' )->once()->with( $expected_key );
		( new Cache( $this->options( 3600 ) ) )->flush_user_from_meta( [ 1, 2 ], 42, '_ogc_meta', [] );
		self::assertTrue( true );
	}

	public function test_flush_term_from_meta_accepts_array_meta_ids_from_deleted_term_meta(): void {
		// `deleted_term_meta` passes an array of meta IDs, not a single int.
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		Functions\when( 'get_term' )->justReturn(
			(object) [
				'taxonomy' => 'category',
				'term_id'  => 42,
			]
		);
		$expected_key = 'ogc_cache_' . md5( implode( ':', [ Context::TYPE_ARCHIVE, '', 'category', '42', '', 'salt1' ] ) );
		Functions\expect( 'delete_transient' )->once()->with( $expected_key );
		( new Cache( $this->options( 3600 ) ) )->flush_term_from_meta( [ 1, 2 ], 42, '_ogc_meta', [] );
		self::assertTrue( true );
	}

	public function test_flush_user_on_delete_flushes_author_context(): void {
		Functions\when( 'get_option' )->justReturn( 'salt1' );
		$expected_key = 'ogc_cache_' . md5( implode( ':', [ Context::TYPE_AUTHOR, '', '', '', '42', 'salt1' ] ) );
		Functions\expect( 'delete_transient' )->once()->with( $expected_key );
		( new Cache( $this->options( 3600 ) ) )->flush_user_on_delete( 42 );
		self::assertTrue( true );
	}

	public function test_key_for_distinguishes_archive_terms(): void {
		$opt = $this->createStub( OptionsRepository::class );
		$opt->method( 'get_path' )->willReturn( 0 ); // cache enabled state doesn't matter for key_for
		Functions\when( 'get_option' )->justReturn( 'test-salt' );

		$cache = new Cache( $opt );

		$k_cat_12 = $cache->key_for( Context::for_archive_term( 'category', 12 ) );
		$k_cat_13 = $cache->key_for( Context::for_archive_term( 'category', 13 ) );
		$k_tag_12 = $cache->key_for( Context::for_archive_term( 'post_tag', 12 ) );
		$k_usr_5  = $cache->key_for( Context::for_author( 5 ) );
		$k_usr_6  = $cache->key_for( Context::for_author( 6 ) );

		self::assertNotSame( $k_cat_12, $k_cat_13 ); // same taxonomy, different term
		self::assertNotSame( $k_cat_12, $k_tag_12 ); // same term id, different taxonomy
		self::assertNotSame( $k_usr_5, $k_usr_6 );   // different authors
		self::assertNotSame( $k_cat_12, $k_usr_5 );  // never collide across context types
	}
}
