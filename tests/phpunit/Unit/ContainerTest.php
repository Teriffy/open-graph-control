<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit;

use EvzenLeonenko\OpenGraphControl\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase {

	public function test_set_and_get_returns_same_instance(): void {
		$c       = new Container();
		$service = new \stdClass();
		$c->set( 'service', static fn() => $service );
		self::assertSame( $service, $c->get( 'service' ) );
	}

	public function test_get_memoizes_factory_result(): void {
		$c = new Container();
		$c->set( 'counter', static fn() => (object) [ 'n' => random_int( 1, PHP_INT_MAX ) ] );
		self::assertSame( $c->get( 'counter' ), $c->get( 'counter' ) );
	}

	public function test_has_returns_true_for_registered_id(): void {
		$c = new Container();
		$c->set( 'x', static fn() => new \stdClass() );
		self::assertTrue( $c->has( 'x' ) );
		self::assertFalse( $c->has( 'y' ) );
	}

	public function test_get_throws_for_unknown_id(): void {
		$this->expectException( \OutOfBoundsException::class );
		( new Container() )->get( 'nope' );
	}

	public function test_factory_receives_container(): void {
		$c = new Container();
		$c->set( 'a', static fn() => 'value-a' );
		$c->set( 'b', static fn( Container $c ) => $c->get( 'a' ) . '-b' );
		self::assertSame( 'value-a-b', $c->get( 'b' ) );
	}
}
