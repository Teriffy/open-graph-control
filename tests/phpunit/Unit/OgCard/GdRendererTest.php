<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use EvzenLeonenko\OpenGraphControl\OgCard\FontProvider;
use EvzenLeonenko\OpenGraphControl\OgCard\GdRenderer;
use EvzenLeonenko\OpenGraphControl\OgCard\Payload;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;
use PHPUnit\Framework\TestCase;

final class GdRendererTest extends TestCase {

	protected function setUp(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'GD extension required' );
		}
	}

	public function test_render_returns_png_bytes(): void {
		$renderer = new GdRenderer( new FontProvider() );
		$bytes    = $renderer->render(
			Template::default()->with( [ 'bg_type' => 'solid' ] ),
			new Payload( 'Hello world', 'A test card', 'example.com', 'https://example.com', 'today' )
		);
		// PNG magic bytes: 89 50 4E 47 0D 0A 1A 0A
		$this->assertSame( "\x89PNG\r\n\x1a\n", substr( $bytes, 0, 8 ) );
	}

	public function test_rendered_image_is_1200x630(): void {
		$renderer = new GdRenderer( new FontProvider() );
		$bytes    = $renderer->render(
			Template::default()->with( [ 'bg_type' => 'solid' ] ),
			new Payload( 'Hello', 'World', 'site', 'https://x.test', '' )
		);
		$img      = imagecreatefromstring( $bytes );
		$this->assertSame( 1200, imagesx( $img ) );
		$this->assertSame( 630, imagesy( $img ) );
	}

	public function test_gradient_background_blends_colors(): void {
		$renderer = new GdRenderer( new FontProvider() );
		$bytes    = $renderer->render(
			Template::default()->with(
				[
					'bg_type'        => 'gradient',
					'bg_color'       => '#000000',
					'bg_gradient_to' => '#ffffff',
				]
			),
			new Payload( 'Hi', 'd', 's', 'https://x.test', '' )
		);
		$img      = imagecreatefromstring( $bytes );
		// Top-left pixel should be near black, bottom-right near white.
		$tl = imagecolorat( $img, 0, 0 );
		$br = imagecolorat( $img, 1199, 629 );
		$this->assertLessThan( 30, ( $tl >> 16 ) & 0xFF, 'Top-left red channel near 0' );
		$this->assertGreaterThan( 220, ( $br >> 16 ) & 0xFF, 'Bottom-right red channel near 255' );
	}
}
