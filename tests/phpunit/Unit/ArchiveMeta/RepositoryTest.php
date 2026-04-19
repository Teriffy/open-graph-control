<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\ArchiveMeta;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\ArchiveMeta\Repository;
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

	public function test_get_for_term_returns_zero_values_when_unset(): void {
		Functions\when( 'get_term_meta' )->justReturn( '' );
		$r = new Repository();
		self::assertSame(
			array(
				'title'       => '',
				'description' => '',
				'image_id'    => 0,
				'exclude'     => array(),
			),
			$r->get_for_term( 5 )
		);
	}

	public function test_get_for_term_returns_stored_values(): void {
		Functions\when( 'get_term_meta' )->justReturn(
			array(
				'title'    => 'Recepty',
				'image_id' => 42,
			)
		);
		$r   = new Repository();
		$out = $r->get_for_term( 5 );
		self::assertSame( 'Recepty', $out['title'] );
		self::assertSame( 42, $out['image_id'] );
		self::assertSame( '', $out['description'] );
		self::assertSame( array(), $out['exclude'] );
	}

	public function test_get_for_user_returns_stored_values(): void {
		Functions\when( 'get_user_meta' )->justReturn(
			array(
				'title'       => 'Evžen',
				'description' => 'Admin',
				'exclude'     => array( 'all' ),
			)
		);
		$r   = new Repository();
		$out = $r->get_for_user( 3 );
		self::assertSame( 'Evžen', $out['title'] );
		self::assertSame( 'Admin', $out['description'] );
		self::assertSame( array( 'all' ), $out['exclude'] );
		self::assertSame( 0, $out['image_id'] );
	}

	public function test_save_allowlist_filters_unknown_keys(): void {
		$captured = null;
		Functions\expect( 'update_term_meta' )
			->once()
			->andReturnUsing(
				function ( $term_id, $key, $value ) use ( &$captured ) {
					$captured = $value;
					return true;
				}
			);
		$r = new Repository();
		$r->save(
			'term',
			5,
			array(
				'title'      => 'X',
				'random_key' => 'dropped',
				'image_id'   => 9,
				'exclude'    => array( 'all' ),
				'__proto__'  => 'nope',
			)
		);
		self::assertSame(
			array(
				'title'    => 'X',
				'image_id' => 9,
				'exclude'  => array( 'all' ),
			),
			$captured
		);
	}

	public function test_save_routes_user_kind_to_user_meta(): void {
		Functions\expect( 'update_user_meta' )->once()->andReturn( true );
		Functions\expect( 'update_term_meta' )->never();
		self::assertTrue( ( new Repository() )->save( 'user', 3, array( 'title' => 'Evžen' ) ) );
	}

	public function test_register_meta_iterates_public_taxonomies(): void {
		$registered = [];
		Functions\when( 'get_taxonomies' )->justReturn(
			[
				'category'       => 'category',
				'post_tag'       => 'post_tag',
				'portfolio_type' => 'portfolio_type',
				'attachment'     => 'attachment',
			]
		);
		Functions\when( 'register_term_meta' )->alias(
			static function ( string $tax, string $key ) use ( &$registered ): void {
				$registered[] = "term:{$tax}:{$key}";
			}
		);
		Functions\when( 'register_meta' )->alias(
			static function ( string $type, string $key ) use ( &$registered ): void {
				$registered[] = "{$type}:{$key}";
			}
		);

		( new Repository() )->register_meta();

		self::assertContains( 'term:category:_ogc_meta', $registered );
		self::assertContains( 'term:post_tag:_ogc_meta', $registered );
		self::assertContains( 'term:portfolio_type:_ogc_meta', $registered );
		self::assertContains( 'user:_ogc_meta', $registered );
		// attachment must be excluded
		self::assertNotContains( 'term:attachment:_ogc_meta', $registered );
	}
}
