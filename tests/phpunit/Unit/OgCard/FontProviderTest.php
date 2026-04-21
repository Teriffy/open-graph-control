<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use EvzenLeonenko\OpenGraphControl\OgCard\FontProvider;
use PHPUnit\Framework\TestCase;

final class FontProviderTest extends TestCase {

	public function test_regular_font_path_exists(): void {
		$path = ( new FontProvider() )->path( 'regular' );
		$this->assertFileExists( $path );
		$this->assertStringEndsWith( '.ttf', $path );
	}

	public function test_bold_font_path_exists(): void {
		$path = ( new FontProvider() )->path( 'bold' );
		$this->assertFileExists( $path );
	}

	public function test_unknown_weight_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new FontProvider() )->path( 'italic' );
	}
}
