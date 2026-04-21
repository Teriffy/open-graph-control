<?php
/**
 * Scheduler hook adapter tests.
 *
 * @package EvzenLeonenko\OpenGraphControl
 */

declare(strict_types=1);

namespace EvzenLeonenko\OpenGraphControl\Tests\Unit\OgCard;

use Brain\Monkey;
use Brain\Monkey\Functions;
use EvzenLeonenko\OpenGraphControl\OgCard\{CardGenerator, CardKey, CardStore, Payload, RendererInterface, Scheduler, Template};
use PHPUnit\Framework\TestCase;

/**
 * Tests for Scheduler hook adapter.
 *
 * Verifies that Scheduler correctly adapts WordPress hooks to CardGenerator
 * and CardStore operations, with proper filtering and deferral to shutdown.
 */
final class SchedulerTest extends TestCase {

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
					unlink( $path );
				}
			}
		}
		rmdir( $dir );
	}

	public function test_on_save_post_skips_drafts(): void {
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'is_post_type_viewable' )->justReturn( true );
		Functions\expect( 'add_action' )->never();

		$store     = new CardStore( $this->base );
		$renderer  = $this->createMock( RendererInterface::class );
		$generator = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);
		$scheduler = new Scheduler( $generator, $store );
		$renderer->expects( $this->never() )->method( 'render' );

		$post = (object) [
			'ID'          => 1,
			'post_status' => 'draft',
			'post_type'   => 'post',
		];
		$scheduler->on_save_post( 1, $post );
	}

	public function test_on_save_post_skips_revisions(): void {
		Functions\when( 'wp_is_post_revision' )->justReturn( true );
		Functions\expect( 'add_action' )->never();

		$store     = new CardStore( $this->base );
		$renderer  = $this->createMock( RendererInterface::class );
		$generator = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);
		$scheduler = new Scheduler( $generator, $store );
		$renderer->expects( $this->never() )->method( 'render' );
		$scheduler->on_save_post(
			1,
			(object) [
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);
	}

	public function test_on_save_post_calls_add_action_for_publish(): void {
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'is_post_type_viewable' )->justReturn( true );
		Functions\expect( 'add_action' )->with( 'shutdown', \Mockery::type( 'Closure' ) )->once();

		$store     = new CardStore( $this->base );
		$renderer  = $this->createMock( RendererInterface::class );
		$generator = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);
		$scheduler = new Scheduler( $generator, $store );
		$scheduler->on_save_post(
			1,
			(object) [
				'ID'          => 1,
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);
		$this->assertTrue( true );
	}

	public function test_on_edited_term_schedules_shutdown_for_viewable_tax(): void {
		Functions\when( 'is_taxonomy_viewable' )->justReturn( true );
		Functions\expect( 'add_action' )->with( 'shutdown', \Mockery::type( 'Closure' ) )->once();

		$store     = new CardStore( $this->base );
		$renderer  = $this->createMock( RendererInterface::class );
		$generator = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);
		$scheduler = new Scheduler( $generator, $store );
		$scheduler->on_edited_term( 5, 10, 'category' );
		$this->assertTrue( true );
	}

	public function test_on_edited_term_skips_non_viewable_tax(): void {
		Functions\when( 'is_taxonomy_viewable' )->justReturn( false );
		Functions\expect( 'add_action' )->never();

		$store     = new CardStore( $this->base );
		$renderer  = $this->createMock( RendererInterface::class );
		$generator = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);
		$scheduler = new Scheduler( $generator, $store );
		$scheduler->on_edited_term( 5, 10, 'private_tax' );
		$this->assertTrue( true );
	}

	public function test_on_profile_update_schedules_shutdown(): void {
		Functions\expect( 'add_action' )->with( 'shutdown', \Mockery::type( 'Closure' ) )->once();

		$store     = new CardStore( $this->base );
		$renderer  = $this->createMock( RendererInterface::class );
		$generator = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);
		$scheduler = new Scheduler( $generator, $store );
		$scheduler->on_profile_update( 42 );
		$this->assertTrue( true );
	}

	public function test_on_delete_post_calls_store_delete_directly(): void {
		$store     = new CardStore( $this->base );
		$renderer  = $this->createMock( RendererInterface::class );
		$generator = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);

		// Create a dummy card file for the post
		$template = Template::default();
		$key      = CardKey::for_post( 99 );
		$path     = $store->path( $key, $template, 'landscape' );
		@mkdir( dirname( $path ), 0777, true );
		file_put_contents( $path, 'dummy' );
		$this->assertTrue( file_exists( $path ), 'Card file should exist before deletion' );

		$scheduler = new Scheduler( $generator, $store );
		$scheduler->on_delete_post( 99 );

		$this->assertFalse( file_exists( $path ), 'Card file should be deleted after on_delete_post' );
	}

	public function test_on_delete_term_calls_store_delete_with_archive_key(): void {
		$store     = new CardStore( $this->base );
		$renderer  = $this->createMock( RendererInterface::class );
		$generator = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);

		// Create a dummy card file for the term archive
		$template = Template::default();
		$key      = CardKey::for_archive( 'category', 7 );
		$path     = $store->path( $key, $template, 'landscape' );
		@mkdir( dirname( $path ), 0777, true );
		file_put_contents( $path, 'dummy' );
		$this->assertTrue( file_exists( $path ), 'Card file should exist before deletion' );

		$scheduler = new Scheduler( $generator, $store );
		$scheduler->on_delete_term( 7, 10, 'category' );

		$this->assertFalse( file_exists( $path ), 'Card file should be deleted after on_delete_term' );
	}

	public function test_on_delete_user_calls_store_delete_with_author_key(): void {
		$store     = new CardStore( $this->base );
		$renderer  = $this->createMock( RendererInterface::class );
		$generator = new CardGenerator(
			picker: fn() => $renderer,
			store: $store,
			template_provider: fn() => Template::default(),
			payload_provider: fn() => new Payload( 'T', 'D', 'S', 'https://x.test', 'today' ),
		);

		// Create a dummy card file for the author
		$template = Template::default();
		$key      = CardKey::for_author( 5 );
		$path     = $store->path( $key, $template, 'landscape' );
		@mkdir( dirname( $path ), 0777, true );
		file_put_contents( $path, 'dummy' );
		$this->assertTrue( file_exists( $path ), 'Card file should exist before deletion' );

		$scheduler = new Scheduler( $generator, $store );
		$scheduler->on_delete_user( 5 );

		$this->assertFalse( file_exists( $path ), 'Card file should be deleted after on_delete_user' );
	}
}
