<?php
/**
 * BackfillCron daily card backfill tests.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\OgCard\{BackfillCron, CardGenerator, CardKey, CardStore, Payload, RendererInterface, Template};
use PHPUnit\Framework\TestCase;

/**
 * Tests for BackfillCron scheduler.
 *
 * Verifies daily cron registration, batch processing of missing posts,
 * and transient-based locking to prevent overlapping executions.
 */
final class BackfillCronTest extends TestCase {

	/**
	 * Temporary base directory for CardStore.
	 *
	 * @var string
	 */
	private string $base;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->base = sys_get_temp_dir() . '/' . uniqid( 'ogc_test_', true );
	}

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
					// phpcs:ignore WordPress.WP.AlternativeFunctions -- Acceptable for test cleanup.
					unlink( $path );
				}
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions -- Acceptable for test cleanup.
		rmdir( $dir );
	}

	public function test_register_schedules_daily_event_if_unscheduled(): void {
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_event' )->once()->with(
			\Mockery::type( 'int' ),
			'daily',
			'ogc_og_card_backfill'
		);

		BackfillCron::register();
		$this->assertTrue( true );
	}

	public function test_tick_processes_batch_of_5(): void {
		$store    = new CardStore( $this->base );
		$renderer = $this->createMock( RendererInterface::class );
		$renderer->method( 'render' )->willReturn( 'fake_png_data' );

		$generator = new CardGenerator(
			picker:               fn() => $renderer,
			store:                $store,
			template_provider:    fn() => Template::default(),
			payload_provider:     fn( CardKey $key ) => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);

		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );

		// Mock get_posts to return test post IDs.
		// phpcs:ignore Universal.Arrays.DisallowShortArraySyntax -- Project standard.
		Functions\when( 'get_posts' )->justReturn( [ 1, 2, 3, 4, 5 ] );

		$cron = new BackfillCron( $generator, $store, fn() => Template::default() );
		$cron->tick();

		// Verify that cards were created for each post ID.
		$template = Template::default();
		for ( $i = 1; $i <= 5; ++$i ) {
			$key = CardKey::for_post( $i );
			$this->assertTrue( $store->exists( $key, $template, 'landscape' ) );
		}
	}

	public function test_tick_skips_when_locked(): void {
		Functions\when( 'get_transient' )->justReturn( true );
		Functions\when( 'set_transient' )->justReturn( true );

		$store    = new CardStore( $this->base );
		$renderer = $this->createMock( RendererInterface::class );

		$generator = new CardGenerator(
			picker:               fn() => $renderer,
			store:                $store,
			template_provider:    fn() => Template::default(),
			payload_provider:     fn( CardKey $key ) => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);

		// This should not be called because lock is active.
		Functions\expect( 'get_posts' )->never();

		$cron = new BackfillCron( $generator, $store, fn() => Template::default() );
		$cron->tick();

		$this->assertTrue( true );
	}
}
