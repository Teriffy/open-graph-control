<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use Brain\Monkey;
use EvzenLeonenko\OpenGraphControl\OgCard\{FontProvider, GdRenderer, RendererPicker};
use PHPUnit\Framework\TestCase;

final class RendererPickerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_picker_returns_gd_by_default(): void {
		Monkey\Functions\when( 'apply_filters' )->returnArg( 2 );
		$picker = new RendererPicker( new FontProvider() );
		$this->assertInstanceOf( GdRenderer::class, $picker->pick() );
	}

	public function test_picker_returns_gd_even_when_imagick_filter_opts_in_v04(): void {
		Monkey\Functions\when( 'apply_filters' )->alias(
			fn( $hook, $value ) => 'ogc_card_renderer_prefer_imagick' === $hook ? true : $value
		);
		$picker = new RendererPicker( new FontProvider() );
		// v0.4: Imagick is deferred to v0.5; picker always returns GdRenderer.
		$this->assertInstanceOf( GdRenderer::class, $picker->pick() );
	}
}
