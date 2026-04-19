<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Admin\Rest;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Admin\Rest\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_under_limit_returns_true_and_increments_transient(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'get_transient' )->justReturn( 3 );
		Functions\expect( 'set_transient' )->once()->with( \Mockery::any(), 4, 60 );

		self::assertTrue( ( new RateLimiter( 20, 60 ) )->check( 'preview' ) );
	}

	public function test_at_limit_returns_false_and_does_not_increment(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'get_transient' )->justReturn( 20 );
		Functions\expect( 'set_transient' )->never();

		self::assertFalse( ( new RateLimiter( 20, 60 ) )->check( 'preview' ) );
	}

	public function test_different_keys_have_independent_counters(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		$seen = [];
		Functions\when( 'get_transient' )->alias(
			static function ( $key ) use ( &$seen ) {
				return $seen[ $key ] ?? 0;
			}
		);
		Functions\when( 'set_transient' )->alias(
			static function ( $key, $value ) use ( &$seen ) {
				$seen[ $key ] = $value;
				return true;
			}
		);

		$limiter = new RateLimiter( 2, 60 );
		self::assertTrue( $limiter->check( 'preview' ) );
		self::assertTrue( $limiter->check( 'debug' ) );
		self::assertCount( 2, $seen );
	}

	public function test_different_users_have_independent_counters(): void {
		$buckets = [];
		Functions\when( 'get_transient' )->alias(
			static function ( $key ) use ( &$buckets ) {
				return $buckets[ $key ] ?? 0;
			}
		);
		Functions\when( 'set_transient' )->alias(
			static function ( $key, $value ) use ( &$buckets ) {
				$buckets[ $key ] = $value;
				return true;
			}
		);

		$limiter = new RateLimiter( 2, 60 );

		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		$limiter->check( 'preview' );

		Functions\when( 'get_current_user_id' )->justReturn( 2 );
		$limiter->check( 'preview' );

		self::assertCount( 2, $buckets );
	}
}
