<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Actions;
use EvzenLeonenko\OpenGraphControl\Container;
use EvzenLeonenko\OpenGraphControl\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_boot_registers_init_action(): void {
		Actions\expectAdded( 'init' )->once();
		$plugin = new Plugin( new Container() );
		$plugin->boot();
		self::assertInstanceOf( Plugin::class, $plugin );
	}

	public function test_container_is_accessible(): void {
		$c      = new Container();
		$plugin = new Plugin( $c );
		self::assertSame( $c, $plugin->container() );
	}
}
