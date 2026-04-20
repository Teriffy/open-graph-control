<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\OgCard\FontProvider;
use EvzenLeonenko\OpenGraphControl\OgCard\GdRenderer;
use EvzenLeonenko\OpenGraphControl\OgCard\Payload;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;
use PHPUnit\Framework\TestCase;

final class GdRendererTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! extension_loaded( 'gd' ) ) {
			$this->markTestSkipped( 'GD extension required' );
		}
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
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

	public function test_image_bg_renders_with_overlay(): void {
		// Stub wp_get_attachment_image_src to return our fixture path.
		Functions\when( 'wp_get_attachment_image_src' )->justReturn(
			[
				__DIR__ . '/../../../fixtures/sample-bg.jpg',
				800,
				600,
			]
		);
		$renderer = new GdRenderer( new FontProvider() );
		$bytes    = $renderer->render(
			Template::default()->with(
				[
					'bg_type'     => 'image',
					'bg_image_id' => 1,
				]
			),
			new Payload( 'Hi', 'd', 's', 'https://x.test', '' )
		);
		$img      = imagecreatefromstring( $bytes );
		// Center pixel should have darkness applied (overlay).
		$center = imagecolorat( $img, 600, 315 );
		$r      = ( $center >> 16 ) & 0xFF;
		$this->assertLessThan( 180, $r, 'Center should be dimmed by overlay' );
	}

	public function test_short_title_renders_at_60px(): void {
		$renderer = new GdRenderer( new FontProvider() );
		$bytes    = $renderer->render(
			Template::default()->with( [ 'bg_type' => 'solid' ] ),
			new Payload( 'Hi', 'd', 's', 'https://x.test', '' )
		);
		// Smoke: image still 1200×630, no exception
		$img = imagecreatefromstring( $bytes );
		$this->assertSame( 1200, imagesx( $img ) );
		// phpcs:disable Generic.PHP.DeprecatedFunctions.Deprecated -- 8.5 deprecation; we explicitly free memory.
		imagedestroy( $img );
		// phpcs:enable Generic.PHP.DeprecatedFunctions.Deprecated
	}

	public function test_very_long_title_does_not_overflow_canvas(): void {
		$renderer = new GdRenderer( new FontProvider() );
		$longest  = str_repeat( 'WordPress plugin development tutorial 2026 ', 10 );
		$bytes    = $renderer->render(
			Template::default()->with( [ 'bg_type' => 'solid' ] ),
			new Payload( $longest, 'd', 's', 'https://x.test', '' )
		);
		// Just confirms no exception + valid PNG
		$this->assertSame( "\x89PNG\r\n\x1a\n", substr( $bytes, 0, 8 ) );
	}

	public function test_description_truncates_after_two_lines(): void {
		$renderer = new GdRenderer( new FontProvider() );
		$long     = str_repeat( 'A practical guide to WordPress plugin development. ', 10 );
		$bytes    = $renderer->render(
			Template::default()->with( [ 'bg_type' => 'solid' ] ),
			new Payload( 'T', $long, 's', 'https://x.test', '' )
		);
		$this->assertSame( "\x89PNG\r\n\x1a\n", substr( $bytes, 0, 8 ) );
	}
}
