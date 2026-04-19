<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\PostMeta;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\PostMeta\Repository;
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

	public function test_get_returns_shape_when_meta_absent(): void {
		Functions\expect( 'get_post_meta' )->once()->with( 42, '_ogc_meta', true )->andReturn( '' );
		$meta = ( new Repository() )->get( 42 );
		self::assertSame( '', $meta['title'] );
		self::assertSame( '', $meta['description'] );
		self::assertSame( 0, $meta['image_id'] );
		self::assertSame( '', $meta['type'] );
		self::assertSame( [], $meta['platforms'] );
		self::assertSame( [], $meta['exclude'] );
	}

	public function test_get_merges_stored_values(): void {
		Functions\when( 'get_post_meta' )->justReturn(
			[
				'title'    => 'Custom',
				'image_id' => 99,
			]
		);
		$meta = ( new Repository() )->get( 1 );
		self::assertSame( 'Custom', $meta['title'] );
		self::assertSame( 99, $meta['image_id'] );
		self::assertSame( '', $meta['description'] );
	}

	public function test_get_coerces_wrong_types(): void {
		Functions\when( 'get_post_meta' )->justReturn(
			[
				'image_id'  => '42',
				'platforms' => 'not-an-array',
				'exclude'   => null,
			]
		);
		$meta = ( new Repository() )->get( 1 );
		self::assertSame( 42, $meta['image_id'] );
		self::assertSame( [], $meta['platforms'] );
		self::assertSame( [], $meta['exclude'] );
	}

	public function test_save_writes_allowlisted_keys_only(): void {
		Functions\expect( 'update_post_meta' )->once()->with(
			7,
			'_ogc_meta',
			\Mockery::on(
				static function ( $payload ) {
					return is_array( $payload )
						&& ! isset( $payload['malicious'] )
						&& 'Hi' === ( $payload['title'] ?? null );
				}
			)
		)->andReturn( true );

		$saved = ( new Repository() )->save(
			7,
			[
				'title'     => 'Hi',
				'malicious' => 'x',
			]
		);

		self::assertTrue( $saved );
	}

	public function test_delete_calls_delete_post_meta(): void {
		Functions\expect( 'delete_post_meta' )->once()->with( 5, '_ogc_meta' )->andReturn( true );
		self::assertTrue( ( new Repository() )->delete( 5 ) );
	}
}
