<?php
/**
 * CardsCommand WP-CLI subcommands tests.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\Cli;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\Cli\CardsCommand;
use EvzenLeonenko\OpenGraphControl\OgCard\CardGenerator;
use EvzenLeonenko\OpenGraphControl\OgCard\CardKey;
use EvzenLeonenko\OpenGraphControl\OgCard\CardStore;
use EvzenLeonenko\OpenGraphControl\OgCard\Payload;
use EvzenLeonenko\OpenGraphControl\OgCard\RendererInterface;
use EvzenLeonenko\OpenGraphControl\OgCard\Template;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CardsCommand WP-CLI integration.
 *
 * Uses the WP_CLI shim from bootstrap.php which records calls and throws on
 * error() by default, making it easy to assert both happy-path and error cases.
 */
final class CardsCommandTest extends TestCase {

	/**
	 * Temporary base directory for CardStore operations.
	 *
	 * @var string
	 */
	private string $base;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		\WP_CLI::reset();
		$this->base = sys_get_temp_dir() . '/' . uniqid( 'ogc_test_cli_', true );
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
					unlink( $path );// phpcs:ignore WordPress.WP.AlternativeFunctions
				}
			}
		}
		rmdir( $dir );// phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Helper to build a CardGenerator backed by a real CardStore + mock renderer.
	 *
	 * @param string      $render_return  What renderer->render() returns.
	 * @param CardStore   $store          Store to use.
	 *
	 * @return CardGenerator
	 */
	private function make_generator( string $render_return, CardStore $store ): CardGenerator {
		$renderer = $this->createMock( RendererInterface::class );
		$renderer->method( 'render' )->willReturn( $render_return );
		return new CardGenerator(
			picker:            fn() => $renderer,
			store:             $store,
			template_provider: fn() => Template::default(),
			payload_provider:  fn() => new Payload( 'Title', 'Desc', 'Site', 'https://example.com', '2026-04-20' ),
		);
	}

	// -----------------------------------------------------------------------
	// generate
	// -----------------------------------------------------------------------

	public function test_generate_without_post_id_errors(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'WP_CLI::error: Missing or invalid --post-id' );

		$store   = new CardStore( $this->base );
		$gen     = $this->make_generator( 'PNG', $store );
		$command = new CardsCommand( $gen, $store );

		$command->generate( [], [] );
	}

	public function test_generate_with_zero_post_id_errors(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'WP_CLI::error: Missing or invalid --post-id' );

		$store   = new CardStore( $this->base );
		$gen     = $this->make_generator( 'PNG', $store );
		$command = new CardsCommand( $gen, $store );

		$command->generate( [], [ 'post-id' => '0' ] );
	}

	public function test_generate_calls_ensure_with_correct_key(): void {
		$store   = new CardStore( $this->base );
		$gen     = $this->make_generator( 'FAKEPNG', $store );
		$command = new CardsCommand( $gen, $store );

		Functions\when( 'apply_filters' )->returnArg( 2 );

		$command->generate( [], [ 'post-id' => '42' ] );

		$success_calls = array_filter( \WP_CLI::$calls, fn( $c ) => 'success' === $c['method'] );
		$this->assertCount( 1, $success_calls );
		$call = reset( $success_calls );
		$this->assertStringContainsString( 'Generated:', $call['message'] );
	}

	public function test_generate_errors_when_render_returns_null(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'WP_CLI::error: Render failed (see error log)' );

		$store = new CardStore( $this->base );

		// Generator with a renderer that fails (returns empty string which write will still handle),
		// instead use a filter that blocks generation.
		$renderer = $this->createMock( RendererInterface::class );
		$renderer->method( 'render' )->willReturn( 'PNG' );

		// Force ensure() to return null via the filter.
		Functions\when( 'apply_filters' )->justReturn( false );

		$gen     = new CardGenerator(
			picker:            fn() => $renderer,
			store:             $store,
			template_provider: fn() => Template::default(),
			payload_provider:  fn() => new Payload( 'T', 'D', 'S', 'https://x.test', '' ),
		);
		$command = new CardsCommand( $gen, $store );

		$command->generate( [], [ 'post-id' => '5' ] );
	}

	// -----------------------------------------------------------------------
	// regenerate
	// -----------------------------------------------------------------------

	public function test_regenerate_skips_existing_unless_force(): void {
		$store = new CardStore( $this->base );
		$store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'EXISTING' );

		$gen = $this->make_generator( 'NEW', $store );

		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_posts' )->justReturn( [ 1 ] );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$command = new CardsCommand( $gen, $store );
		$command->regenerate( [], [] );

		// 0 regenerated — existing card was skipped.
		$success_calls = array_filter( \WP_CLI::$calls, fn( $c ) => 'success' === $c['method'] );
		$this->assertCount( 1, $success_calls );
		$call = reset( $success_calls );
		$this->assertStringContainsString( '0 card(s) regenerated', $call['message'] );
	}

	public function test_regenerate_force_rerenders_existing(): void {
		$store = new CardStore( $this->base );
		$store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'OLD' );

		$gen = $this->make_generator( 'NEW', $store );

		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_posts' )->justReturn( [ 1 ] );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$command = new CardsCommand( $gen, $store );
		$command->regenerate( [], [ 'all' => true ] );

		$success_calls = array_filter( \WP_CLI::$calls, fn( $c ) => 'success' === $c['method'] );
		$this->assertCount( 1, $success_calls );
		$call = reset( $success_calls );
		$this->assertStringContainsString( '1 card(s) regenerated', $call['message'] );
	}

	public function test_regenerate_dry_run_does_not_render(): void {
		$store = new CardStore( $this->base );

		// Use a renderer that should never be called.
		$renderer = $this->createMock( RendererInterface::class );
		$renderer->expects( $this->never() )->method( 'render' );

		$gen = new CardGenerator(
			picker:            fn() => $renderer,
			store:             $store,
			template_provider: fn() => Template::default(),
			payload_provider:  fn() => new Payload( 'T', 'D', 'S', 'https://x.test', '' ),
		);

		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'get_posts' )->justReturn( [ 10, 11 ] );

		$command = new CardsCommand( $gen, $store );
		$command->regenerate( [], [ 'dry-run' => true ] );

		$log_calls = array_filter( \WP_CLI::$calls, fn( $c ) => 'line' === $c['method'] );
		$this->assertCount( 2, $log_calls, 'Two "Would regenerate" log entries expected' );

		$success_calls = array_filter( \WP_CLI::$calls, fn( $c ) => 'success' === $c['method'] );
		$this->assertCount( 1, $success_calls );
		$call = reset( $success_calls );
		$this->assertStringContainsString( '2 card(s) identified', $call['message'] );
	}

	// -----------------------------------------------------------------------
	// status
	// -----------------------------------------------------------------------

	public function test_status_prints_counts(): void {
		$store = new CardStore( $this->base );
		$store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'PNG' );

		// get_option → empty array (default template)
		// wp_count_posts → object with publish = 3
		// get_posts (called inside missing_post_ids) → returns [ 1, 2, 3 ], post 1 has card.
		Functions\when( 'get_option' )->justReturn( [] );

		$counts          = new \stdClass();
		$counts->publish = 3;
		Functions\when( 'wp_count_posts' )->justReturn( $counts );
		Functions\when( 'get_posts' )->justReturn( [ 1, 2, 3 ] );

		$gen     = $this->make_generator( 'PNG', $store );
		$command = new CardsCommand( $gen, $store );
		$command->status( [], [] );

		$line_calls = array_filter( \WP_CLI::$calls, fn( $c ) => 'line' === $c['method'] );
		$this->assertCount( 1, $line_calls );
		$call = reset( $line_calls );
		// Posts 2 and 3 are missing, post 1 exists → Generated: 1 / Missing: 2 / Total: 3.
		$this->assertStringContainsString( 'Generated: 1', $call['message'] );
		$this->assertStringContainsString( 'Missing: 2', $call['message'] );
		$this->assertStringContainsString( 'Total: 3', $call['message'] );
	}

	// -----------------------------------------------------------------------
	// purge
	// -----------------------------------------------------------------------

	public function test_purge_calls_store_and_schedules_cron(): void {
		$store = new CardStore( $this->base );
		$store->write( CardKey::for_post( 1 ), Template::default(), 'landscape', 'PNG' );

		$card_path = $store->path( CardKey::for_post( 1 ), Template::default(), 'landscape' );
		$this->assertFileExists( $card_path );

		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				\Mockery::type( 'int' ),
				'ogc_og_card_backfill'
			);

		$gen     = $this->make_generator( 'PNG', $store );
		$command = new CardsCommand( $gen, $store );
		$command->purge( [], [] );

		$this->assertFileDoesNotExist( $card_path );

		$success_calls = array_filter( \WP_CLI::$calls, fn( $c ) => 'success' === $c['method'] );
		$this->assertCount( 1, $success_calls );
		$call = reset( $success_calls );
		$this->assertSame( 'All cards purged; backfill rescheduled', $call['message'] );
	}
}
