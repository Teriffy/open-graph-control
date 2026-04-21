<?php
/**
 * CardGenerator orchestrator tests.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Actions;
use EvzenLeonenko\OpenGraphControl\OgCard\CardGenerator;
use EvzenLeonenko\OpenGraphControl\OgCard\CardKey;
use EvzenLeonenko\OpenGraphControl\OgCard\CardStore;
use EvzenLeonenko\OpenGraphControl\OgCard\Payload;
use EvzenLeonenko\OpenGraphControl\OgCard\RendererInterface;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CardGenerator orchestrator.
 *
 * Verifies that CardGenerator correctly orchestrates rendering and storage
 * of OG cards, with proper short-circuiting when cards already exist and
 * error handling when rendering fails.
 */
final class CardGeneratorTest extends TestCase {

	/**
	 * Temporary base directory for write tests.
	 *
	 * @var string
	 */
	private string $base;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->base = sys_get_temp_dir() . '/' . uniqid( 'ogc_test_', true );
	}

	/**
	 * Cleans up temporary files after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		if ( is_dir( $this->base ) ) {
			$this->remove_dir_recursively( $this->base );
		}
	}

	/**
	 * Recursively removes a directory and all its contents.
	 *
	 * @param string $dir Directory path.
	 *
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
	 * Tests ensure() renders and writes when card is missing.
	 *
	 * @return void
	 */
	public function test_ensure_renders_and_writes_when_missing(): void {
		$store    = new CardStore( $this->base );
		$renderer = $this->createMock( RendererInterface::class );
		$renderer->expects( $this->once() )
				->method( 'render' )
				->willReturn( 'PNGBYTES' );

		$gen = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);

		$path = $gen->ensure( CardKey::for_post( 1 ) );
		$this->assertNotNull( $path );
		$this->assertSame( 'PNGBYTES', file_get_contents( $path ) );
	}

	/**
	 * Tests ensure() skips render when card already exists.
	 *
	 * @return void
	 */
	public function test_ensure_skips_render_when_already_exists(): void {
		$store = new CardStore( $this->base );
		$store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'EXISTING' );
		$renderer = $this->createMock( RendererInterface::class );
		$renderer->expects( $this->never() )->method( 'render' );

		$gen = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', '' ),
		);

		$path = $gen->ensure( CardKey::for_post( 1 ) );
		$this->assertSame( 'EXISTING', file_get_contents( $path ) );
	}

	/**
	 * Tests ensure() returns null without rendering when ogc_card_should_generate returns false.
	 *
	 * @return void
	 */
	public function test_ensure_skips_when_should_generate_returns_false(): void {
		Filters\expectApplied( 'ogc_card_should_generate' )
			->once()
			->andReturn( false );

		$store    = new CardStore( $this->base );
		$renderer = $this->createMock( RendererInterface::class );
		$renderer->expects( $this->never() )->method( 'render' );

		$gen = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', '' ),
		);

		$result = $gen->ensure( CardKey::for_post( 42 ) );
		$this->assertNull( $result );
	}

	/**
	 * Tests ensure() fires ogc_card_generated action after a successful write.
	 *
	 * @return void
	 */
	public function test_ensure_fires_generated_action_after_write(): void {
		$store    = new CardStore( $this->base );
		$renderer = $this->createMock( RendererInterface::class );
		$renderer->method( 'render' )->willReturn( 'PNGBYTES' );

		$key = CardKey::for_post( 7 );

		Actions\expectDone( 'ogc_card_generated' )
			->once()
			->with( $key, \Mockery::type( 'string' ) );

		$gen = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', '' ),
		);

		$path = $gen->ensure( $key );
		$this->assertNotNull( $path );
	}
}
