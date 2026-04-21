<?php
declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use Brain\Monkey;
use EvzenLeonenko\OpenGraphControl\OgCard\{FontProvider, GdRenderer, ImagickRenderer, RendererPicker};
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

	public function test_picker_returns_imagick_when_filter_opts_in(): void {
		if ( ! extension_loaded( 'imagick' ) ) {
			$this->markTestSkipped( 'Imagick required' );
		}
		Monkey\Functions\when( 'apply_filters' )->alias(
			fn( $hook, $value ) => 'ogc_card_renderer_prefer_imagick' === $hook ? true : $value
		);
		$picker = new RendererPicker( new FontProvider() );
		$this->assertInstanceOf( ImagickRenderer::class, $picker->pick() );
	}
}
