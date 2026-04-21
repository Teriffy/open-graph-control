<?php
/**
 * ResolverHook test.
 *
 * @package EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use EvzenLeonenko\OpenGraphControl\OgCard\{CardStore, CardGenerator, CardKey, ResolverHook, Template, RendererInterface, Payload};
use EvzenLeonenko\OpenGraphControl\Resolvers\Context;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ResolverHook.
 *
 * @covers \EvzenLeonenko\OpenGraphControl\OgCard\ResolverHook
 */
final class ResolverHookTest extends TestCase {

	/**
	 * Temporary base directory for store tests.
	 *
	 * @var string
	 */
	private string $base;

	protected function setUp(): void {
		\Brain\Monkey\setUp();
		$this->base = sys_get_temp_dir() . '/' . uniqid( 'ogc_test_', true );
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		if ( is_dir( $this->base ) ) {
			$this->remove_dir_recursively( $this->base );
		}
	}

	/**
	 * Recursively removes a directory and all its contents.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function remove_dir_recursively( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = scandir( $dir );
		if ( false === $files ) {
			return;
		}
		foreach ( $files as $file ) {
			if ( '.' !== $file && '..' !== $file ) {
				$path = $dir . '/' . $file;
				if ( is_dir( $path ) ) {
					$this->remove_dir_recursively( $path );
				} else {
					unlink( $path );
				}
			}
		}
		rmdir( $dir );
	}

	/**
	 * Build a real CardGenerator instance for testing.
	 *
	 * @return CardGenerator
	 */
	private function build_generator_stub(): CardGenerator {
		$renderer = $this->createMock( RendererInterface::class );
		return new CardGenerator(
			picker: fn() => $renderer,
			store: new CardStore( $this->base ),
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);
	}

	public function test_returns_null_when_feature_disabled(): void {
		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( [ 'enabled' => false ] );
		$store = new CardStore( $this->base );
		$generator = $this->build_generator_stub();
		$hook = new ResolverHook( $store, $generator, fn() => Template::default() );
		$value = $hook->on_step( null, 'auto_card', Context::for_post( 1 ) );
		$this->assertNull( $value );
	}

	public function test_returns_attachment_url_string_when_card_exists(): void {
		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( [ 'enabled' => true ] );
		$store = new CardStore( $this->base );
		// Write a test card to the store
		$store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'test-card-data' );
		$generator = $this->build_generator_stub();
		$hook = new ResolverHook( $store, $generator, fn() => Template::default() );
		$value = $hook->on_step( null, 'auto_card', Context::for_post( 1 ) );
		$this->assertIsString( $value );
		$this->assertStringContainsString( 'post-1-', $value );
	}

	public function test_passes_through_when_step_is_not_auto_card(): void {
		$store = new CardStore( $this->base );
		$generator = $this->build_generator_stub();
		$hook = new ResolverHook( $store, $generator, fn() => Template::default() );
		$value = $hook->on_step( 'existing-value', 'featured_image', Context::for_post( 1 ) );
		$this->assertSame( 'existing-value', $value );
	}

	public function test_passes_through_when_value_is_already_set(): void {
		$store = new CardStore( $this->base );
		$generator = $this->build_generator_stub();
		$hook = new ResolverHook( $store, $generator, fn() => Template::default() );
		$value = $hook->on_step( '99', 'auto_card', Context::for_post( 1 ) );
		$this->assertSame( '99', $value );
	}

	public function test_cold_start_schedules_render_and_returns_null(): void {
		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( [ 'enabled' => true ] );
		\Brain\Monkey\Functions\when( 'get_transient' )->justReturn( false );
		\Brain\Monkey\Functions\expect( 'set_transient' )->once();
		\Brain\Monkey\Functions\expect( 'add_action' )->with( 'shutdown', Mockery::type( 'Closure' ) )->once();
		$store = new CardStore( $this->base );
		$generator = $this->build_generator_stub();
		$hook = new ResolverHook( $store, $generator, fn() => Template::default() );
		$value = $hook->on_step( null, 'auto_card', Context::for_post( 1 ) );
		$this->assertNull( $value );
	}
}
