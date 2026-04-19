<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Images;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Images\SizeRegistry;
use PHPUnit\Framework\TestCase;

final class SizeRegistryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_adds_three_sizes(): void {
		Functions\expect( 'add_image_size' )->once()->with( SizeRegistry::LANDSCAPE, 1200, 630, true );
		Functions\expect( 'add_image_size' )->once()->with( SizeRegistry::SQUARE, 600, 600, true );
		Functions\expect( 'add_image_size' )->once()->with( SizeRegistry::PINTEREST, 1000, 1500, true );

		( new SizeRegistry() )->register();
		self::assertTrue( true );
	}
}
